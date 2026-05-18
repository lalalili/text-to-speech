<?php

namespace Lalalili\TextToSpeech;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lalalili\TextToSpeech\Console\AggregateDailyMetricsCommand;
use Lalalili\TextToSpeech\Console\AggregateMonthlyMetricsCommand;
use Lalalili\TextToSpeech\Console\CleanupCommand;
use Lalalili\TextToSpeech\Console\RetryCommand;
use Lalalili\TextToSpeech\Console\StatsCommand;
use Lalalili\TextToSpeech\Console\SynthesizeCommand;
use Lalalili\TextToSpeech\Contracts\CharacterCounterInterface;
use Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface;
use Lalalili\TextToSpeech\Services\TextToSpeechService;
use Lalalili\TextToSpeech\Support\DefaultCharacterCounter;
use Lalalili\TextToSpeech\Support\TextToSpeechHasher;

class TextToSpeechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/text-to-speech.php', 'text-to-speech');

        $this->app->singleton(TextToSpeechManager::class);
        $this->app->singleton(TextToSpeechHasher::class);
        $this->app->bind(CharacterCounterInterface::class, DefaultCharacterCounter::class);
        $this->app->singleton(TextToSpeechServiceInterface::class, function ($app): TextToSpeechService {
            return new TextToSpeechService(
                $app->make(TextToSpeechManager::class),
                $app->make(CharacterCounterInterface::class),
                $app->make(TextToSpeechHasher::class),
            );
        });
        $this->app->alias(TextToSpeechServiceInterface::class, TextToSpeechService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/text-to-speech.php' => config_path('text-to-speech.php'),
        ], 'text-to-speech-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'text-to-speech-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SynthesizeCommand::class,
                RetryCommand::class,
                CleanupCommand::class,
                AggregateDailyMetricsCommand::class,
                AggregateMonthlyMetricsCommand::class,
                StatsCommand::class,
            ]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $dailyTime = (string) config('text-to-speech.metrics.daily_time', '01:15');
                $dailyTz = config('text-to-speech.metrics.timezone');
                $monthlyTime = (string) config('text-to-speech.metrics.monthly_time', '01:30');
                $monthlyTz = config('text-to-speech.metrics.monthly_timezone');
                $cleanupTime = (string) config('text-to-speech.cleanup.time', '02:40');

                $daily = $schedule->command('tts:aggregate-daily')->dailyAt($dailyTime);
                if (is_string($dailyTz) && $dailyTz !== '') {
                    $daily->timezone($dailyTz);
                }

                $monthly = $schedule->command('tts:aggregate-monthly')->monthlyOn(1, $monthlyTime);
                if (is_string($monthlyTz) && $monthlyTz !== '') {
                    $monthly->timezone($monthlyTz);
                }

                $schedule->command('tts:cleanup')->dailyAt($cleanupTime);
            });
        }
    }
}
