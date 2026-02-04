<?php

namespace Lalalili\TextToSpeech\Models;

use Illuminate\Database\Eloquent\Model;

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
