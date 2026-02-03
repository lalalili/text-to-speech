<?php

namespace Lalalili\TextToSpeech\Support;

use Lalalili\TextToSpeech\Contracts\CharacterCounterInterface;

class DefaultCharacterCounter implements CharacterCounterInterface
{
    public function count(string $input, string $inputType): int
    {
        return mb_strlen($input, 'UTF-8');
    }
}
