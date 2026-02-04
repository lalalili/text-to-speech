# Text-to-Speech (Laravel Package)

Laravel text-to-speech driver package with Google/Azure drivers, queueing, caching, metrics, and CLI utilities.

## Features
- Google Cloud Text-to-Speech driver
- Azure Text-to-Speech driver
- Queue-based generation + cache lock
- Hash-based cache reuse (`cache_hit`)
- Daily / monthly metrics and alerts
- Retry + cleanup + stats commands
- Security guardrails (SSML allow/deny tags, allowlists)

## Installation
```bash
composer require lalalili/text-to-speech
```

Publish config and migrations:
```bash
php artisan vendor:publish --tag=text-to-speech-config
php artisan vendor:publish --tag=text-to-speech-migrations
php artisan migrate
```

## Configuration
Set these in `.env` (examples):

```dotenv
TTS_DRIVER=google
TTS_STORAGE_DISK=local
TTS_STORAGE_DIRECTORY=tts
TTS_QUEUE_NAME=tts
TTS_QUEUE_RETRY_TIMES=3
TTS_QUEUE_RETRY_BACKOFF_SECONDS=30,120,300

TTS_ALLOW_SSML=false
TTS_SSML_ALLOWED_TAGS=speak,voice,prosody,break,emphasis,sub,lang
TTS_SSML_DISALLOWED_TAGS=audio,lexicon,mark,metadata,desc
```

Google:
```dotenv
GOOGLE_TTS_CREDENTIALS=/path/to/google-tts.json
GOOGLE_TTS_LANGUAGE_CODE=cmn-TW
GOOGLE_TTS_VOICE=cmn-TW-Wavenet-A
GOOGLE_TTS_AUDIO_FORMAT=mp3
```

Azure:
```dotenv
AZURE_TTS_KEY=...
AZURE_TTS_REGION=eastus
AZURE_TTS_VOICE=zh-TW-HsiaoChenNeural
AZURE_TTS_LANGUAGE_CODE=zh-TW
```

## Usage
```php
use Lalalili\TextToSpeech\Contracts\TextToSpeechServiceInterface;
use Lalalili\TextToSpeech\Support\TextToSpeechOptions;

$options = TextToSpeechOptions::fromConfig('azure');
$request = app(TextToSpeechServiceInterface::class)->queue('測試文字', $options);
```

Sync:
```php
$request = app(TextToSpeechServiceInterface::class)->synthesizeSync('測試文字', $options);
```

## CLI Commands
```bash
php artisan tts:synthesize "測試文字" --driver=azure --voice=zh-TW-HsiaoChenNeural --language=zh-TW
php artisan tts:retry --limit=50
php artisan tts:cleanup --days=30 --dry-run
php artisan tts:aggregate-daily --date=2026-02-04
php artisan tts:aggregate-monthly --month=2026-02
php artisan tts:stats
```

## Testing
```bash
vendor/bin/pest
```

## License
MIT
