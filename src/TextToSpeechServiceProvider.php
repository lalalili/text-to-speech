<?php

namespace Lalalili\TextToSpeech;

use Illuminate\Support\ServiceProvider;
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
        $this->app->singleton(TextToSpeechServiceInterface::class, TextToSpeechService::class);
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
    }
}
