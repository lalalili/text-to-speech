<?php

use Lalalili\TextToSpeech\Models\TextToSpeechDailyMetric;
use Lalalili\TextToSpeech\Models\TextToSpeechMonthlyMetric;

it('shows daily stats for given date', function () {
    TextToSpeechDailyMetric::create([
        'date' => '2026-03-01',
        'driver' => 'gemini',
        'requests_count' => 10,
        'success_count' => 8,
        'failed_count' => 2,
        'retry_requests_count' => 1,
        'retry_count_sum' => 3,
        'cache_hit_count' => 4,
        'character_count_sum' => 1000,
        'estimated_cost_micros_sum' => 500,
    ]);

    $this->artisan('tts:stats', ['--date' => '2026-03-01'])
        ->expectsOutputToContain('總請求數')
        ->expectsOutputToContain('10')
        ->assertExitCode(0);
});

it('shows monthly stats for given month', function () {
    TextToSpeechMonthlyMetric::create([
        'month' => '2026-02-01',
        'driver' => 'gemini',
        'requests_count' => 200,
        'success_count' => 180,
        'failed_count' => 20,
        'retry_requests_count' => 10,
        'retry_count_sum' => 30,
        'cache_hit_count' => 50,
        'character_count_sum' => 50000,
        'estimated_cost_micros_sum' => 25000,
    ]);

    $this->artisan('tts:stats', ['--month' => '2026-02'])
        ->expectsOutputToContain('月指標')
        ->expectsOutputToContain('200')
        ->assertExitCode(0);
});

it('warns when no data found', function () {
    $this->artisan('tts:stats', ['--date' => '2020-01-01'])
        ->expectsOutputToContain('找不到')
        ->assertExitCode(0);
});
