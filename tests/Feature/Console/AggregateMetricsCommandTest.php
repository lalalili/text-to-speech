<?php

use Lalalili\TextToSpeech\Models\TextToSpeechDailyMetric;
use Lalalili\TextToSpeech\Models\TextToSpeechMonthlyMetric;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;

function makeRequest(string $hash, string $driver, string $status, int $characterCount, bool $cacheHit = false, int $retryCount = 0, ?int $costMicros = null): void
{
    TextToSpeechRequest::create([
        'hash' => $hash,
        'driver' => $driver,
        'input_type' => 'text',
        'voice' => 'Kore',
        'language_code' => 'cmn-TW',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
        'audio_format' => 'mp3',
        'character_count' => $characterCount,
        'estimated_cost_micros' => $costMicros,
        'retry_count' => $retryCount,
        'cache_hit' => $cacheHit,
        'limit_exceeded' => false,
        'status' => $status,
        'disk' => 'local',
        'path' => "tts/{$hash}.mp3",
        'meta' => [],
    ]);
}

it('aggregates daily metrics per driver', function () {
    $today = now()->toDateString();

    makeRequest('d1', 'gemini', TextToSpeechRequest::STATUS_READY, 100, false, 0, 1000);
    makeRequest('d2', 'gemini', TextToSpeechRequest::STATUS_READY, 200, true, 1, 2000);
    makeRequest('d3', 'azure', TextToSpeechRequest::STATUS_FAILED, 50, false, 2, null);

    $this->artisan('tts:aggregate-daily', ['--date' => $today])
        ->assertExitCode(0);

    $gemini = TextToSpeechDailyMetric::query()
        ->whereDate('date', $today)
        ->where('driver', 'gemini')
        ->first();

    expect($gemini)->not->toBeNull()
        ->and($gemini->requests_count)->toBe(2)
        ->and($gemini->success_count)->toBe(2)
        ->and($gemini->failed_count)->toBe(0)
        ->and($gemini->cache_hit_count)->toBe(1)
        ->and($gemini->retry_requests_count)->toBe(1)
        ->and($gemini->retry_count_sum)->toBe(1)
        ->and($gemini->character_count_sum)->toBe(300)
        ->and($gemini->estimated_cost_micros_sum)->toBe(3000);

    $azure = TextToSpeechDailyMetric::query()
        ->whereDate('date', $today)
        ->where('driver', 'azure')
        ->first();

    expect($azure)->not->toBeNull()
        ->and($azure->requests_count)->toBe(1)
        ->and($azure->failed_count)->toBe(1);
});

it('is idempotent when run twice', function () {
    $today = now()->toDateString();

    makeRequest('idem1', 'gemini', TextToSpeechRequest::STATUS_READY, 10, false, 0);

    $this->artisan('tts:aggregate-daily', ['--date' => $today])->assertExitCode(0);
    $this->artisan('tts:aggregate-daily', ['--date' => $today])->assertExitCode(0);

    expect(
        TextToSpeechDailyMetric::query()
            ->whereDate('date', $today)
            ->where('driver', 'gemini')
            ->count()
    )->toBe(1);
});

it('aggregates monthly metrics per driver', function () {
    $month = now()->format('Y-m');

    makeRequest('m1', 'gemini', TextToSpeechRequest::STATUS_READY, 500, false, 0, 5000);
    makeRequest('m2', 'azure', TextToSpeechRequest::STATUS_FAILED, 300, false, 3, null);

    $this->artisan('tts:aggregate-monthly', ['--month' => $month])
        ->assertExitCode(0);

    $gemini = TextToSpeechMonthlyMetric::query()
        ->whereYear('month', substr($month, 0, 4))
        ->whereMonth('month', substr($month, 5, 2))
        ->where('driver', 'gemini')
        ->first();

    expect($gemini)->not->toBeNull()
        ->and($gemini->requests_count)->toBe(1)
        ->and($gemini->success_count)->toBe(1)
        ->and($gemini->character_count_sum)->toBe(500)
        ->and($gemini->estimated_cost_micros_sum)->toBe(5000);
});
