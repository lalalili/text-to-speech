<?php

namespace Lalalili\TextToSpeech;

use Illuminate\Support\Manager;
use Lalalili\TextToSpeech\Drivers\AzureTextToSpeechDriver;
use Lalalili\TextToSpeech\Drivers\GoogleCloudTextToSpeechDriver;

class TextToSpeechManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('text-to-speech.default', 'google');
    }

    public function createGoogleDriver(): GoogleCloudTextToSpeechDriver
    {
        return new GoogleCloudTextToSpeechDriver;
    }

    public function createAzureDriver(): AzureTextToSpeechDriver
    {
        return new AzureTextToSpeechDriver;
    }
}
