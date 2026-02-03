<?php

namespace Lalalili\TextToSpeech\Contracts;

interface CharacterCounterInterface
{
    public function count(string $input, string $inputType): int;
}
