<?php

namespace Lalalili\TextToSpeech\Models;

use Illuminate\Database\Eloquent\Model;

class TextToSpeechDailyMetric extends Model
{
    protected $table = 'text_to_speech_daily_metrics';

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
            'date' => 'date',
            'requests_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'character_count_sum' => 'integer',
            'estimated_cost_micros_sum' => 'integer',
        ];
    }
}
