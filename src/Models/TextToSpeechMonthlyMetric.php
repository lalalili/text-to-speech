<?php

namespace Lalalili\TextToSpeech\Models;

use Illuminate\Database\Eloquent\Model;

class TextToSpeechMonthlyMetric extends Model
{
    protected $table = 'text_to_speech_monthly_metrics';

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
            'month' => 'date',
            'requests_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'retry_requests_count' => 'integer',
            'retry_count_sum' => 'integer',
            'character_count_sum' => 'integer',
            'estimated_cost_micros_sum' => 'integer',
        ];
    }
}
