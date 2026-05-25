<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Lalalili\TextToSpeech\Drivers\AzureTextToSpeechDriver;
use Lalalili\TextToSpeech\Jobs\GenerateTextToSpeechAudioJob;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Lalalili\TextToSpeech\Services\TextToSpeechService;
use Lalalili\TextToSpeech\Support\DefaultCharacterCounter;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

it('reuses cached audio when file exists', function () {
    config()->set('text-to-speech.storage.disk', 'local');
    config()->set('text-to-speech.storage.directory', 'tts');
    config()->set('filesystems.disks.local.url', 'http://localhost/storage');

    Storage::fake('local');

    $service = app(TextToSpeechService::class);
    $options = TextToSpeechOptions::fromConfig();

    Bus::fake();

    $request = $service->queue('你好，世界', $options);

    Bus::assertDispatched(GenerateTextToSpeechAudioJob::class);

    Storage::disk('local')->put($request->path, 'audio');
    $request->status = TextToSpeechRequest::STATUS_READY;
    $request->save();

    Bus::fake();

    $cached = $service->queue('你好，世界', $options);

    Bus::assertNothingDispatched();
    expect($cached->id)->toBe($request->id);
});

it('strips ssml tags when counting characters', function () {
    $counter = new DefaultCharacterCounter();
    $ssml = '<speak>你好</speak>';

    expect($counter->count($ssml, 'ssml'))->toBe(mb_strlen('你好', 'UTF-8'));
});

it('counts plain text characters without stripping', function () {
    $counter = new DefaultCharacterCounter();

    expect($counter->count('你好世界', 'text'))->toBe(4);
});

it('throws for azure driver stub', function () {
    $driver = new AzureTextToSpeechDriver();
    $options = TextToSpeechOptions::fromConfig();

    $driver->synthesize('hello', $options);
})->throws(RuntimeException::class);
