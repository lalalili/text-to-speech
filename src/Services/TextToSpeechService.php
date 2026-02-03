<?php

namespace Lalalili\TextToSpeech\Services;

use Illuminate\Support\Facades\Storage;
use Lalalili\TextToSpeech\Contracts\CharacterCounterInterface;
use Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface;
use Lalalili\TextToSpeech\Jobs\GenerateTextToSpeechAudioJob;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Lalalili\TextToSpeech\Support\TextToSpeechHasher;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;
use Lalalili\TextToSpeech\TextToSpeechManager;
use Throwable;

class TextToSpeechService implements TextToSpeechServiceInterface
{
    public function __construct(
        private readonly TextToSpeechManager $manager,
        private readonly CharacterCounterInterface $characterCounter,
        private readonly TextToSpeechHasher $hasher,
    ) {}

    public function queue(string $input, ?TextToSpeechOptions $options = null): TextToSpeechRequest
    {
        $options = $this->normalizeOptions($options);
        $driver = $this->resolveDriver($options);

        $hash = $this->hasher->make($input, $options, $driver);
        $characterCount = $this->characterCounter->count($input, $options->inputType);

        $existing = TextToSpeechRequest::query()->where('hash', $hash)->first();

        if ($existing && $this->isReusable($existing)) {
            return $this->ensureUrl($existing);
        }

        $request = $this->fillRequest(
            $existing ?? new TextToSpeechRequest,
            $hash,
            $driver,
            $options,
            $characterCount,
        );

        $request->save();

        $this->dispatchGenerationJob($request, $input, $options);

        return $request;
    }

    public function synthesizeSync(string $input, ?TextToSpeechOptions $options = null): TextToSpeechRequest
    {
        $options = $this->normalizeOptions($options);
        $driver = $this->resolveDriver($options);

        $hash = $this->hasher->make($input, $options, $driver);
        $characterCount = $this->characterCounter->count($input, $options->inputType);

        $existing = TextToSpeechRequest::query()->where('hash', $hash)->first();

        if ($existing && $this->isReusable($existing)) {
            return $this->ensureUrl($existing);
        }

        $request = $this->fillRequest(
            $existing ?? new TextToSpeechRequest,
            $hash,
            $driver,
            $options,
            $characterCount,
        );

        $request->save();

        return $this->synthesizeForRequest($request, $input, $options);
    }

    public function synthesizeForRequest(TextToSpeechRequest $request, string $input, TextToSpeechOptions $options): TextToSpeechRequest
    {
        $request->status = TextToSpeechRequest::STATUS_PROCESSING;
        $request->error_message = null;
        $request->save();

        try {
            $driver = $this->manager->driver($request->driver);
            $audioContent = $driver->synthesize($input, $options);

            $this->storeAudio($request, $audioContent);

            $request->url = $this->resolveUrl($request->disk, $request->path);
            $request->status = TextToSpeechRequest::STATUS_READY;
            $request->error_message = null;
            $request->save();

            return $request;
        } catch (Throwable $exception) {
            $request->status = TextToSpeechRequest::STATUS_FAILED;
            $request->error_message = $exception->getMessage();
            $request->save();

            throw $exception;
        }
    }

    private function normalizeOptions(?TextToSpeechOptions $options): TextToSpeechOptions
    {
        return $options ?? TextToSpeechOptions::fromConfig();
    }

    private function resolveDriver(TextToSpeechOptions $options): string
    {
        $driver = $options->driver ?? (string) config('text-to-speech.default');
        $options->driver = $driver;

        return $driver;
    }

    private function isReusable(TextToSpeechRequest $request): bool
    {
        if ($request->status !== TextToSpeechRequest::STATUS_READY) {
            return false;
        }

        return Storage::disk($request->disk)->exists($request->path);
    }

    private function ensureUrl(TextToSpeechRequest $request): TextToSpeechRequest
    {
        $request->url = $this->resolveUrl($request->disk, $request->path);
        $request->save();

        return $request;
    }

    private function fillRequest(
        TextToSpeechRequest $request,
        string $hash,
        string $driver,
        TextToSpeechOptions $options,
        int $characterCount,
    ): TextToSpeechRequest {
        $request->hash = $hash;
        $request->driver = $driver;
        $request->input_type = $options->inputType;
        $request->voice = $options->voice;
        $request->language_code = $options->languageCode;
        $request->speaking_rate = $options->speakingRate;
        $request->pitch = $options->pitch;
        $request->audio_format = $options->audioFormat;
        $request->sample_rate_hertz = $options->sampleRateHertz;
        $request->effects_profile_id = $options->effectsProfileId;
        $request->character_count = $characterCount;
        $request->estimated_cost_micros = $this->estimateCostMicros($driver, $characterCount);
        $request->limit_exceeded = $this->isLimitExceeded($characterCount);
        $request->status = TextToSpeechRequest::STATUS_PENDING;
        $request->disk = (string) config('text-to-speech.storage.disk');
        $request->path = $this->buildPath($hash, $options->fileExtension());
        $request->url = null;
        $request->error_message = null;
        $request->meta = array_merge((array) $request->meta, [
            'options' => $options->toArray(),
        ]);

        return $request;
    }

    private function estimateCostMicros(string $driver, int $characterCount): ?int
    {
        $costPerMillionMicros = config("text-to-speech.drivers.{$driver}.pricing.cost_per_million_micros");

        if ($costPerMillionMicros === null) {
            return null;
        }

        return (int) ceil($characterCount * ((int) $costPerMillionMicros) / 1_000_000);
    }

    private function isLimitExceeded(int $characterCount): bool
    {
        $maxCharacters = config('text-to-speech.limits.max_characters');

        if ($maxCharacters === null) {
            return false;
        }

        return $characterCount > (int) $maxCharacters;
    }

    private function buildPath(string $hash, string $extension): string
    {
        $directory = trim((string) config('text-to-speech.storage.directory', 'tts'), '/');

        return $directory.'/'.$hash.'.'.$extension;
    }

    private function storeAudio(TextToSpeechRequest $request, string $audioContent): void
    {
        $visibility = config('text-to-speech.storage.visibility');

        $options = [];

        if ($visibility !== null) {
            $options['visibility'] = $visibility;
        }

        Storage::disk($request->disk)->put($request->path, $audioContent, $options);

        $request->meta = array_merge((array) $request->meta, [
            'bytes' => strlen($audioContent),
        ]);
    }

    private function resolveUrl(string $disk, string $path): string
    {
        $resolver = config('text-to-speech.storage.url_resolver');
        $ttl = config('text-to-speech.storage.temporary_url_ttl_minutes');

        return call_user_func($resolver, $disk, $path, $ttl);
    }

    private function dispatchGenerationJob(TextToSpeechRequest $request, string $input, TextToSpeechOptions $options): void
    {
        $job = new GenerateTextToSpeechAudioJob(
            $request->id,
            $input,
            $options->toArray(),
        );

        $connection = config('text-to-speech.queue.connection');
        $queue = config('text-to-speech.queue.name');

        if ($connection) {
            $job->onConnection($connection);
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);
    }
}
