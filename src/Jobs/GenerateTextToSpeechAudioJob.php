<?php

namespace Lalalili\TextToSpeech\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Lalalili\TextToSpeech\Services\TextToSpeechService;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;
use Throwable;

class GenerateTextToSpeechAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $requestId,
        public string $input,
        public array $options,
    ) {}

    public function handle(TextToSpeechService $service): void
    {
        $request = TextToSpeechRequest::query()->find($this->requestId);

        if (! $request) {
            return;
        }

        if ($request->status === TextToSpeechRequest::STATUS_READY
            && Storage::disk($request->disk)->exists($request->path)) {
            return;
        }

        $ttlSeconds = (int) config('text-to-speech.queue.lock_ttl_seconds', 600);
        $lock = Cache::lock('text-to-speech:request:'.$request->id, $ttlSeconds);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->recordAttempt($request);
            $options = TextToSpeechOptions::fromArray($this->options);

            $service->synthesizeForRequest($request, $this->input, $options);
        } catch (Throwable $exception) {
            $this->recordFailure($request, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function recordAttempt(TextToSpeechRequest $request): void
    {
        $attempts = max(1, (int) $this->attempts());
        $retryCount = max(0, $attempts - 1);

        if ((int) $request->retry_count !== $retryCount) {
            $request->retry_count = $retryCount;
            $request->save();
        }
    }

    private function recordFailure(TextToSpeechRequest $request, Throwable $exception): void
    {
        $statusCode = $this->extractStatusCode($exception);

        if ($statusCode !== null) {
            $request->last_error_code = (string) $statusCode;
            $request->save();
        }
    }

    private function extractStatusCode(Throwable $exception): ?int
    {
        if ($exception instanceof RequestException) {
            return $exception->response->status();
        }

        if ($exception instanceof ConnectionException) {
            return null;
        }

        if (preg_match('/status\\s+(\\d{3})/i', $exception->getMessage(), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
