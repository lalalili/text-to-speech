<?php

namespace Lalalili\TextToSpeech\Facades;

use Illuminate\Support\Facades\Facade;
use Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface;

class TextToSpeech extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TextToSpeechServiceInterface::class;
    }
}
