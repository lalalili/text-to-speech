<?php

use Illuminate\Support\Facades\Http;
use Lalalili\TextToSpeech\Drivers\GeminiTextToSpeechDriver;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

function geminiConfig(array $overrides = []): void
{
    config()->set('text-to-speech.drivers.gemini', array_merge([
        'api_key' => 'test-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
        'model' => 'gemini-test-tts',
        'voice' => 'Kore',
        'audio_format' => 'linear16',
        'ffmpeg_path' => 'ffmpeg',
        'timeout_seconds' => 10,
        'connect_timeout_seconds' => 5,
        'retry_times' => 0,
        'retry_sleep_ms' => 0,
        'retry_on_statuses' => [],
    ], $overrides));
}

function fakePcmResponse(int $sampleRate = 24000): array
{
    $pcm = str_repeat("\x00\x00", 48);

    return [
        'candidates' => [[
            'content' => [
                'parts' => [[
                    'inlineData' => [
                        'mimeType' => "audio/L16;codec=pcm;rate={$sampleRate}",
                        'data' => base64_encode($pcm),
                    ],
                ]],
            ],
        ]],
    ];
}

it('synthesizes audio and returns WAV bytes starting with RIFF', function () {
    Http::fake(['*' => Http::response(fakePcmResponse())]);
    geminiConfig(['audio_format' => 'linear16']);

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $result = $driver->synthesize('你好', $options);

    expect($result)->toStartWith('RIFF');
});

it('includes AUDIO responseModalities and correct voiceName in request', function () {
    Http::fake(['*' => Http::response(fakePcmResponse())]);
    geminiConfig(['voice' => 'Puck', 'audio_format' => 'linear16']);

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Puck',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $driver->synthesize('你好', $options);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return in_array('AUDIO', $body['generationConfig']['responseModalities'] ?? [])
            && ($body['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] ?? '') === 'Puck';
    });
});

it('sends the api key in x-goog-api-key header', function () {
    Http::fake(['*' => Http::response(fakePcmResponse())]);
    geminiConfig(['api_key' => 'my-secret-key', 'audio_format' => 'linear16']);

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $driver->synthesize('test', $options);

    Http::assertSent(fn ($request) => $request->header('x-goog-api-key')[0] === 'my-secret-key');
});

it('throws InvalidArgumentException for ssml input type', function () {
    geminiConfig();

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'ssml',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $driver->synthesize('<speak>你好</speak>', $options);
})->throws(InvalidArgumentException::class, 'SSML');

it('throws RuntimeException on non-2xx response', function () {
    Http::fake(['*' => Http::response('Internal Server Error', 500)]);
    geminiConfig();

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $driver->synthesize('hello', $options);
})->throws(RuntimeException::class, '500');

it('throws RuntimeException when model is not configured', function () {
    geminiConfig(['model' => '']);

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $driver->synthesize('hello', $options);
})->throws(RuntimeException::class, 'GEMINI_TTS_MODEL');

it('throws RuntimeException when api key is not configured', function () {
    geminiConfig(['api_key' => '']);

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $driver->synthesize('hello', $options);
})->throws(RuntimeException::class, 'GEMINI_TTS_API_KEY');

it('parses sample rate from mimeType into WAV header', function () {
    Http::fake(['*' => Http::response(fakePcmResponse(22050))]);
    geminiConfig(['audio_format' => 'linear16']);

    $driver = new GeminiTextToSpeechDriver;
    $options = new TextToSpeechOptions(
        inputType: 'text',
        voice: 'Kore',
        languageCode: 'cmn-TW',
        speakingRate: 1.0,
        pitch: 0.0,
        audioFormat: 'linear16',
    );

    $wav = $driver->synthesize('你好', $options);

    // bytes 24-27 of WAV header = sample rate (little-endian)
    $parsedRate = unpack('V', substr($wav, 24, 4))[1];
    expect($parsedRate)->toBe(22050);
});
