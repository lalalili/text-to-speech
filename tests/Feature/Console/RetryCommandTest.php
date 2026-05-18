<?php

use Illuminate\Support\Facades\Bus;
use Lalalili\TextToSpeech\Jobs\GenerateTextToSpeechAudioJob;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;

it('dispatches job for failed requests', function () {
    Bus::fake();

    TextToSpeechRequest::create([
        'hash' => 'abc123',
        'driver' => 'gemini',
        'input_type' => 'text',
        'voice' => 'Kore',
        'language_code' => 'cmn-TW',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
        'audio_format' => 'mp3',
        'character_count' => 3,
        'retry_count' => 1,
        'cache_hit' => false,
        'limit_exceeded' => false,
        'status' => TextToSpeechRequest::STATUS_FAILED,
        'disk' => 'local',
        'path' => 'tts/abc123.mp3',
        'meta' => ['input' => '你好世界', 'options' => []],
    ]);

    $this->artisan('tts:retry')
        ->assertExitCode(0);

    Bus::assertDispatched(GenerateTextToSpeechAudioJob::class);
});

it('skips requests missing meta input', function () {
    Bus::fake();

    TextToSpeechRequest::create([
        'hash' => 'noinput',
        'driver' => 'gemini',
        'input_type' => 'text',
        'voice' => 'Kore',
        'language_code' => 'cmn-TW',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
        'audio_format' => 'mp3',
        'character_count' => 0,
        'retry_count' => 0,
        'cache_hit' => false,
        'limit_exceeded' => false,
        'status' => TextToSpeechRequest::STATUS_FAILED,
        'disk' => 'local',
        'path' => 'tts/noinput.mp3',
        'meta' => [],
    ]);

    $this->artisan('tts:retry')
        ->expectsOutputToContain('缺少 meta.input')
        ->assertExitCode(0);

    Bus::assertNothingDispatched();
});

it('shows message when no failed requests', function () {
    $this->artisan('tts:retry')
        ->expectsOutputToContain('沒有需要重試')
        ->assertExitCode(0);
});
