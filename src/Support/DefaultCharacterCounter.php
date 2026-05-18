<?php

namespace Lalalili\TextToSpeech\Support;

use Lalalili\TextToSpeech\Contracts\CharacterCounterInterface;

class DefaultCharacterCounter implements CharacterCounterInterface
{
    public function count(string $input, string $inputType): int
    {
        if ($inputType === 'ssml') {
            $input = strip_tags($input);
        }

        return mb_strlen($input, 'UTF-8');
    }
}
