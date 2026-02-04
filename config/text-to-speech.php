<?php

use Lalalili\TextToSpeech\Support\DefaultUrlResolver;

$maxCharacters = env('TTS_MAX_CHARACTERS');
$maxCharacters = $maxCharacters !== null ? (int) $maxCharacters : null;

$sampleRateHertz = env('GOOGLE_TTS_SAMPLE_RATE_HERTZ');
$sampleRateHertz = $sampleRateHertz !== null ? (int) $sampleRateHertz : null;

$costPerMillionMicros = env('GOOGLE_TTS_COST_PER_MILLION_MICROS');
$costPerMillionMicros = $costPerMillionMicros !== null ? (int) $costPerMillionMicros : null;

return [
    'default' => env('TTS_DRIVER', 'google'),

    'queue' => [
        'connection' => env('TTS_QUEUE_CONNECTION'),
        'name' => env('TTS_QUEUE_NAME', 'tts'),
        'lock_ttl_seconds' => env('TTS_QUEUE_LOCK_TTL_SECONDS', 600),
    ],

    'storage' => [
        'disk' => env('TTS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'directory' => env('TTS_STORAGE_DIRECTORY', 'tts'),
        'visibility' => env('TTS_STORAGE_VISIBILITY'),
        'temporary_url_ttl_minutes' => env('TTS_TEMPORARY_URL_TTL_MINUTES', 15),
        'url_resolver' => [DefaultUrlResolver::class, 'resolve'],
    ],

    'limits' => [
        'max_characters' => $maxCharacters,
    ],

    'drivers' => [
        'google' => [
            'credentials' => env('GOOGLE_TTS_CREDENTIALS'),
            'language_code' => env('GOOGLE_TTS_LANGUAGE_CODE', 'cmn-TW'),
            'voice' => env('GOOGLE_TTS_VOICE', 'cmn-TW-Wavenet-A'),
            'speaking_rate' => (float) env('GOOGLE_TTS_SPEAKING_RATE', 1.0),
            'pitch' => (float) env('GOOGLE_TTS_PITCH', 0.0),
            'audio_format' => env('GOOGLE_TTS_AUDIO_FORMAT', 'mp3'),
            'sample_rate_hertz' => $sampleRateHertz,
            'effects_profile_id' => env('GOOGLE_TTS_EFFECTS_PROFILE_ID'),
            'pricing' => [
                'currency' => env('GOOGLE_TTS_PRICING_CURRENCY', 'USD'),
                'cost_per_million_micros' => $costPerMillionMicros,
            ],
        ],
        'azure' => [
            'key' => env('AZURE_TTS_KEY'),
            'region' => env('AZURE_TTS_REGION'),
            'endpoint' => env('AZURE_TTS_ENDPOINT'),
            'user_agent' => env('AZURE_TTS_USER_AGENT', 'text-to-speech'),
            'output_format' => env('AZURE_TTS_OUTPUT_FORMAT'),
            'language_code' => env('AZURE_TTS_LANGUAGE_CODE'),
            'voice' => env('AZURE_TTS_VOICE'),
            'speaking_rate' => (float) env('AZURE_TTS_SPEAKING_RATE', 1.0),
            'pitch' => (float) env('AZURE_TTS_PITCH', 0.0),
            'audio_format' => env('AZURE_TTS_AUDIO_FORMAT', 'mp3'),
        ],
    ],
];
