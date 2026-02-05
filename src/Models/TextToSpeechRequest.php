<?php

namespace Lalalili\TextToSpeech\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $hash
 * @property string $driver
 * @property string $input_type
 * @property string $voice
 * @property string $language_code
 * @property float|null $speaking_rate
 * @property float|null $pitch
 * @property string $audio_format
 * @property int|null $sample_rate_hertz
 * @property string|null $effects_profile_id
 * @property int $character_count
 * @property int|null $estimated_cost_micros
 * @property bool $limit_exceeded
 * @property int $retry_count
 * @property bool $cache_hit
 * @property string $status
 * @property string $disk
 * @property string $path
 * @property string|null $url
 * @property string|null $error_message
 * @property string|null $last_error_code
 * @property array<string, mixed> $meta
 * @property \DateTimeInterface|null $created_at
 * @property \DateTimeInterface|null $updated_at
 */
class TextToSpeechRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'text_to_speech_requests';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speaking_rate' => 'float',
            'pitch' => 'float',
            'character_count' => 'integer',
            'estimated_cost_micros' => 'integer',
            'limit_exceeded' => 'boolean',
            'retry_count' => 'integer',
            'cache_hit' => 'boolean',
            'meta' => 'array',
        ];
    }
}
