<?php

namespace Lalalili\TextToSpeech\Contracts;

use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

interface TextToSpeechServiceInterface
{
    public function queue(string $input, ?TextToSpeechOptions $options = null): TextToSpeechRequest;

    public function synthesizeSync(string $input, ?TextToSpeechOptions $options = null): TextToSpeechRequest;
}
