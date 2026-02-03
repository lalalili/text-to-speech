<?php

namespace Lalalili\TextToSpeech\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Lalalili\TextToSpeech\Services\TextToSpeechService;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

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
            $options = TextToSpeechOptions::fromArray($this->options);

            $service->synthesizeForRequest($request, $this->input, $options);
        } finally {
            $lock->release();
        }
    }
}
