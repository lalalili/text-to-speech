<?php

namespace Lalalili\TextToSpeech\Support;

use InvalidArgumentException;

final class TextToSpeechOptions
{
    public function __construct(
        public string $inputType,
        public string $voice,
        public string $languageCode,
        public float $speakingRate,
        public float $pitch,
        public string $audioFormat,
        public ?int $sampleRateHertz = null,
        public ?string $effectsProfileId = null,
        public ?string $driver = null,
        public ?string $rawInput = null,
    ) {
        $this->audioFormat = strtolower($this->audioFormat);
    }

    public static function fromConfig(?string $driver = null): self
    {
        $driver = $driver ?? (string) config('text-to-speech.default');
        $config = (array) config("text-to-speech.drivers.{$driver}", []);

        return new self(
            inputType: 'text',
            voice: (string) ($config['voice'] ?? ''),
            languageCode: (string) ($config['language_code'] ?? 'cmn-TW'),
            speakingRate: (float) ($config['speaking_rate'] ?? 1.0),
            pitch: (float) ($config['pitch'] ?? 0.0),
            audioFormat: (string) ($config['audio_format'] ?? 'mp3'),
            sampleRateHertz: $config['sample_rate_hertz'] ?? null,
            effectsProfileId: $config['effects_profile_id'] ?? null,
            driver: $driver,
            rawInput: null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['input_type'], $data['voice'], $data['language_code'], $data['audio_format'])) {
            throw new InvalidArgumentException('Invalid TextToSpeechOptions payload.');
        }

        return new self(
            inputType: (string) $data['input_type'],
            voice: (string) $data['voice'],
            languageCode: (string) $data['language_code'],
            speakingRate: (float) ($data['speaking_rate'] ?? 1.0),
            pitch: (float) ($data['pitch'] ?? 0.0),
            audioFormat: (string) $data['audio_format'],
            sampleRateHertz: isset($data['sample_rate_hertz']) ? (int) $data['sample_rate_hertz'] : null,
            effectsProfileId: isset($data['effects_profile_id']) ? (string) $data['effects_profile_id'] : null,
            driver: isset($data['driver']) ? (string) $data['driver'] : null,
            rawInput: isset($data['raw_input']) ? (string) $data['raw_input'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'input_type' => $this->inputType,
            'voice' => $this->voice,
            'language_code' => $this->languageCode,
            'speaking_rate' => $this->speakingRate,
            'pitch' => $this->pitch,
            'audio_format' => $this->audioFormat,
            'sample_rate_hertz' => $this->sampleRateHertz,
            'effects_profile_id' => $this->effectsProfileId,
            'driver' => $this->driver,
            'raw_input' => $this->rawInput,
        ];
    }

    public function fileExtension(): string
    {
        return match ($this->audioFormat) {
            'mp3' => 'mp3',
            'ogg_opus' => 'ogg',
            'linear16' => 'wav',
            default => $this->audioFormat,
        };
    }
}
