<?php

namespace Lalalili\TextToSpeech\Drivers;

use Lalalili\TextToSpeech\Contracts\TextToSpeechDriverInterface;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;
use RuntimeException;

class AzureTextToSpeechDriver implements TextToSpeechDriverInterface
{
    public function synthesize(string $input, TextToSpeechOptions $options): string
    {
        throw new RuntimeException('Azure Text-to-Speech driver is not implemented yet.');
    }
}
