<?php

namespace Lalalili\TextToSpeech\Console;

use Illuminate\Console\Command;
use Lalalili\TextToSpeech\Models\TextToSpeechDailyMetric;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;

class AggregateDailyMetricsCommand extends Command
{
    protected $signature = 'tts:aggregate-daily
        {--date= : 彙總日期 YYYY-MM-DD（預設為昨日）}';

    protected $description = '彙總每日語音合成指標至 text_to_speech_daily_metrics';

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $rows = TextToSpeechRequest::query()
            ->whereDate('created_at', $date)
            ->selectRaw(implode(', ', [
                'driver',
                'COUNT(*) as requests_count',
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as success_count',
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as failed_count',
                'COALESCE(SUM(CASE WHEN retry_count > 0 THEN 1 ELSE 0 END), 0) as retry_requests_count',
                'COALESCE(SUM(retry_count), 0) as retry_count_sum',
                'COALESCE(SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END), 0) as cache_hit_count',
                'COALESCE(SUM(character_count), 0) as character_count_sum',
                'COALESCE(SUM(estimated_cost_micros), 0) as estimated_cost_micros_sum',
            ]), [TextToSpeechRequest::STATUS_READY, TextToSpeechRequest::STATUS_FAILED])
            ->groupBy('driver')
            ->get();

        foreach ($rows as $row) {
            TextToSpeechDailyMetric::updateOrCreate(
                ['date' => $date, 'driver' => $row->driver],
                [
                    'requests_count' => (int) $row->requests_count,
                    'success_count' => (int) $row->success_count,
                    'failed_count' => (int) $row->failed_count,
                    'retry_requests_count' => (int) $row->retry_requests_count,
                    'retry_count_sum' => (int) $row->retry_count_sum,
                    'cache_hit_count' => (int) $row->cache_hit_count,
                    'character_count_sum' => (int) $row->character_count_sum,
                    'estimated_cost_micros_sum' => (int) $row->estimated_cost_micros_sum,
                ],
            );
        }

        $totalRequests = $rows->sum('requests_count');
        $totalSuccess = $rows->sum('success_count');
        $totalFailed = $rows->sum('failed_count');

        $this->info("已彙總 {$date}：{$totalRequests} 筆請求，{$totalSuccess} 成功，{$totalFailed} 失敗。");

        return self::SUCCESS;
    }
}
