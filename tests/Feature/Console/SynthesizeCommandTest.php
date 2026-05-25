<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Lalalili\TextToSpeech\Jobs\GenerateTextToSpeechAudioJob;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;

it('dispatches job when synthesizing asynchronously', function () {
    config()->set('text-to-speech.drivers.google.language_code', 'cmn-TW');
    config()->set('text-to-speech.drivers.google.voice', 'cmn-TW-Wavenet-A');
    config()->set('text-to-speech.drivers.google.audio_format', 'mp3');
    config()->set('text-to-speech.storage.disk', 'local');

    Storage::fake('local');
    Bus::fake();

    $this->artisan('tts:synthesize', ['text' => '你好', '--driver' => 'google'])
        ->assertExitCode(0);

    Bus::assertDispatched(GenerateTextToSpeechAudioJob::class);
});

it('outputs url when using --sync and synthesizeSync succeeds', function () {
    config()->set('text-to-speech.drivers.google.language_code', 'cmn-TW');
    config()->set('text-to-speech.drivers.google.voice', 'cmn-TW-Wavenet-A');
    config()->set('text-to-speech.drivers.google.audio_format', 'mp3');
    config()->set('text-to-speech.storage.disk', 'local');
    config()->set('filesystems.disks.local.url', 'http://localhost/storage');

    Storage::fake('local');

    $stub = new TextToSpeechRequest();
    $stub->url = 'http://localhost/storage/tts/abc.mp3';
    $stub->status = TextToSpeechRequest::STATUS_READY;
    $stub->driver = 'google';
    $stub->cache_hit = false;

    $this->mock(\Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface::class)
        ->shouldReceive('synthesizeSync')
        ->once()
        ->andReturn($stub);

    $this->artisan('tts:synthesize', ['text' => '你好', '--driver' => 'google', '--sync' => true])
        ->expectsOutputToContain('http://localhost/storage/tts/abc.mp3')
        ->assertExitCode(0);
});

it('returns failure when synthesizeSync throws', function () {
    $this->mock(\Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface::class)
        ->shouldReceive('synthesizeSync')
        ->once()
        ->andThrow(new RuntimeException('API key missing'));

    $this->artisan('tts:synthesize', ['text' => '你好', '--sync' => true])
        ->expectsOutputToContain('語音合成失敗')
        ->assertExitCode(1);
});
