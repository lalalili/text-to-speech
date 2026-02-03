<?php

namespace Lalalili\TextToSpeech\Contracts;

use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

interface TextToSpeechDriverInterface
{
    public function synthesize(string $input, TextToSpeechOptions $options): string;
}
