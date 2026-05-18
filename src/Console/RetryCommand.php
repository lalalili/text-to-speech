<?php

namespace Lalalili\TextToSpeech\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Lalalili\TextToSpeech\Jobs\GenerateTextToSpeechAudioJob;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

class RetryCommand extends Command
{
    protected $signature = 'tts:retry
        {--limit=50 : 最多重試筆數}
        {--id= : 指定單筆 request ID}';

    protected $description = '重新佇列失敗的語音合成請求';

    public function handle(): int
    {
        $query = TextToSpeechRequest::query()->where('status', TextToSpeechRequest::STATUS_FAILED);

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        } else {
            $query->limit((int) $this->option('limit'));
        }

        $requests = $query->get();

        if ($requests->isEmpty()) {
            $this->info('沒有需要重試的失敗請求。');

            return self::SUCCESS;
        }

        $connection = config('text-to-speech.queue.connection');
        $queue = config('text-to-speech.queue.name');

        foreach ($requests as $request) {
            $input = $request->meta['input'] ?? null;
            $optionsArray = $request->meta['options'] ?? [];

            if (! is_string($input) || $input === '') {
                $this->warn("request_id={$request->id} 缺少 meta.input，跳過。");

                continue;
            }

            try {
                $options = TextToSpeechOptions::fromArray($optionsArray);
            } catch (InvalidArgumentException) {
                $options = new TextToSpeechOptions(
                    inputType: $request->input_type,
                    voice: $request->voice,
                    languageCode: $request->language_code,
                    speakingRate: (float) $request->speaking_rate,
                    pitch: (float) $request->pitch,
                    audioFormat: $request->audio_format,
                );
            }

            $job = new GenerateTextToSpeechAudioJob($request->id, $input, $options->toArray());

            if ($connection) {
                $job->onConnection($connection);
            }

            if ($queue) {
                $job->onQueue($queue);
            }

            $request->status = TextToSpeechRequest::STATUS_PENDING;
            $request->save();

            dispatch($job);

            $this->line("已重試 request_id={$request->id}");
        }

        $this->info("共重試 {$requests->count()} 筆。");

        return self::SUCCESS;
    }
}
