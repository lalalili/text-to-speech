<?php

namespace Lalalili\TextToSpeech\Drivers;

use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Lalalili\TextToSpeech\Contracts\TextToSpeechDriverInterface;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

class GoogleCloudTextToSpeechDriver implements TextToSpeechDriverInterface
{
    public function synthesize(string $input, TextToSpeechOptions $options): string
    {
        $client = new TextToSpeechClient($this->clientOptions());

        try {
            $synthesisInput = new SynthesisInput;

            if ($options->inputType === 'ssml') {
                $synthesisInput->setSsml($input);
            } else {
                $synthesisInput->setText($input);
            }

            $voice = (new VoiceSelectionParams)
                ->setLanguageCode($options->languageCode)
                ->setName($options->voice);

            $audioConfig = (new AudioConfig)
                ->setAudioEncoding($this->resolveAudioEncoding($options->audioFormat))
                ->setSpeakingRate($options->speakingRate)
                ->setPitch($options->pitch);

            if ($options->sampleRateHertz !== null) {
                $audioConfig->setSampleRateHertz($options->sampleRateHertz);
            }

            if ($options->effectsProfileId !== null && $options->effectsProfileId !== '') {
                $audioConfig->setEffectsProfileId($this->normalizeEffectsProfileId($options->effectsProfileId));
            }

            $response = $client->synthesizeSpeech($synthesisInput, $voice, $audioConfig);

            return $response->getAudioContent();
        } finally {
            $client->close();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function clientOptions(): array
    {
        $credentials = config('text-to-speech.drivers.google.credentials');

        if ($credentials === null || $credentials === '') {
            return [];
        }

        return ['credentials' => $credentials];
    }

    private function resolveAudioEncoding(string $audioFormat): int
    {
        return match ($audioFormat) {
            'mp3' => AudioEncoding::MP3,
            'ogg_opus' => AudioEncoding::OGG_OPUS,
            'linear16' => AudioEncoding::LINEAR16,
            default => AudioEncoding::MP3,
        };
    }

    /**
     * @return array<int, string>
     */
    private function normalizeEffectsProfileId(string $effectsProfileId): array
    {
        $values = array_map('trim', explode(',', $effectsProfileId));

        return array_values(array_filter($values, fn (string $value): bool => $value !== ''));
    }
}
