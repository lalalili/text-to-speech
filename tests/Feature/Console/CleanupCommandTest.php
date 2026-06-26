<?php

use Illuminate\Support\Facades\Storage;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;

function makeReadyRequest(string $hash, string $path, int $daysAgo = 0): TextToSpeechRequest
{
    $request = TextToSpeechRequest::create([
        'hash' => $hash,
        'driver' => 'gemini',
        'input_type' => 'text',
        'voice' => 'Kore',
        'language_code' => 'cmn-TW',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
        'audio_format' => 'mp3',
        'character_count' => 2,
        'retry_count' => 0,
        'cache_hit' => false,
        'limit_exceeded' => false,
        'status' => TextToSpeechRequest::STATUS_READY,
        'disk' => 'local',
        'path' => $path,
        'url' => 'http://localhost/'.$path,
        'meta' => [],
    ]);

    if ($daysAgo > 0) {
        $request->created_at = now()->subDays($daysAgo);
        $request->save();
    }

    return $request;
}

it('deletes old records and files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('tts/old.mp3', 'audio');

    $old = makeReadyRequest('old', 'tts/old.mp3', 35);
    $recent = makeReadyRequest('recent', 'tts/recent.mp3', 0);

    $this->artisan('tts:cleanup', ['--days' => 30])
        ->assertExitCode(0);

    expect(TextToSpeechRequest::find($old->id))->toBeNull();
    expect(TextToSpeechRequest::find($recent->id))->not->toBeNull();
    Storage::disk('local')->assertMissing('tts/old.mp3');
});

it('does not delete in dry-run mode', function () {
    Storage::fake('local');
    Storage::disk('local')->put('tts/dryold.mp3', 'audio');

    $old = makeReadyRequest('dryold', 'tts/dryold.mp3', 40);

    $this->artisan('tts:cleanup', ['--days' => 30, '--dry-run' => true])
        ->expectsOutputToContain('dry-run')
        ->assertExitCode(0);

    expect(TextToSpeechRequest::find($old->id))->not->toBeNull();
    Storage::disk('local')->assertExists('tts/dryold.mp3');
});
