<?php

namespace Lalalili\TextToSpeech\Support;

final class TextToSpeechHasher
{
    public function make(string $input, TextToSpeechOptions $options, string $driver): string
    {
        $payload = [
            'driver' => $driver,
            'input' => $input,
            'input_type' => $options->inputType,
            'voice' => $options->voice,
            'language_code' => $options->languageCode,
            'speaking_rate' => $options->speakingRate,
            'pitch' => $options->pitch,
            'audio_format' => $options->audioFormat,
            'sample_rate_hertz' => $options->sampleRateHertz,
            'effects_profile_id' => $options->effectsProfileId,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            $encoded = '';
        }

        return hash('sha256', $encoded);
    }
}
