<?php

namespace Lalalili\TextToSpeech\Console;

use Illuminate\Console\Command;
use Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;
use Throwable;

class SynthesizeCommand extends Command
{
    protected $signature = 'tts:synthesize
        {text : 要合成的文字或 SSML 內容}
        {--driver= : TTS driver（google|azure|gemini）}
        {--voice= : 覆寫音色}
        {--language= : 覆寫語言代碼}
        {--ssml : 以 SSML 模式傳入}
        {--sync : 同步合成（否則 dispatch queue job）}';

    protected $description = '合成語音，輸出可播放的音檔 URL';

    public function handle(TextToSpeechServiceInterface $tts): int
    {
        $inputArgument = $this->argument('text');
        if (! is_string($inputArgument) || $inputArgument === '') {
            $this->error('請提供要合成的文字或 SSML 內容');

            return self::FAILURE;
        }

        $driverOption = $this->option('driver');
        $driver = is_string($driverOption) && $driverOption !== '' ? $driverOption : null;

        $options = TextToSpeechOptions::fromConfig($driver);

        $voiceOption = $this->option('voice');
        if (is_string($voiceOption) && $voiceOption !== '') {
            $voice = $voiceOption;
            $options->voice = $voice;
        }

        $languageOption = $this->option('language');
        if (is_string($languageOption) && $languageOption !== '') {
            $language = $languageOption;
            $options->languageCode = $language;
        }

        if ($this->option('ssml')) {
            $options->inputType = 'ssml';
        }

        try {
            if ($this->option('sync')) {
                $request = $tts->synthesizeSync($inputArgument, $options);
                $this->line($request->url ?? '(URL pending)');
                $this->info("status={$request->status} driver={$request->driver} cache_hit=".($request->cache_hit ? 'true' : 'false'));
            } else {
                $request = $tts->queue($inputArgument, $options);
                $this->info("已排入佇列。request_id={$request->id} status={$request->status}");
            }
        } catch (Throwable $e) {
            $this->error('語音合成失敗：'.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
