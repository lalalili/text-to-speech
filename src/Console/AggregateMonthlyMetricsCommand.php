<?php

namespace Lalalili\TextToSpeech\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lalalili\TextToSpeech\Models\TextToSpeechMonthlyMetric;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;
use Throwable;

class AggregateMonthlyMetricsCommand extends Command
{
    protected $signature = 'tts:aggregate-monthly
        {--month= : 彙總月份 YYYY-MM（預設為上個月）}';

    protected $description = '彙總每月語音合成指標至 text_to_speech_monthly_metrics';

    public function handle(): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');
        $monthStart = $month.'-01';

        $rows = TextToSpeechRequest::query()
            ->whereYear('created_at', substr($month, 0, 4))
            ->whereMonth('created_at', substr($month, 5, 2))
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
            TextToSpeechMonthlyMetric::updateOrCreate(
                ['month' => $monthStart, 'driver' => $row->driver],
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
        $totalCostMicros = (int) $rows->sum('estimated_cost_micros_sum');
        $totalChars = (int) $rows->sum('character_count_sum');

        $this->info("已彙總 {$month}：{$totalRequests} 筆請求，{$totalSuccess} 成功，{$totalFailed} 失敗。");

        $this->checkAlerts($month, $totalCostMicros, $totalChars);

        return self::SUCCESS;
    }

    private function checkAlerts(string $month, int $costMicros, int $characters): void
    {
        $channel = config('text-to-speech.metrics.alerts.channel');
        $costLimit = config('text-to-speech.metrics.alerts.monthly_cost_micros');
        $charLimit = config('text-to-speech.metrics.alerts.monthly_characters');

        $alerts = [];

        if ($costLimit !== null && $costMicros >= (int) $costLimit) {
            $alerts[] = "月成本 {$costMicros} micros 超過門檻 {$costLimit}";
        }

        if ($charLimit !== null && $characters >= (int) $charLimit) {
            $alerts[] = "月字元數 {$characters} 超過門檻 {$charLimit}";
        }

        if ($alerts === [] || ! is_string($channel) || $channel === '') {
            return;
        }

        $message = '[TTS Alert] '.$month.'：'.implode('；', $alerts);

        try {
            Log::channel($channel)->warning($message, [
                'month' => $month,
                'cost_micros' => $costMicros,
                'characters' => $characters,
            ]);
        } catch (Throwable $e) {
            $this->warn("告警發送失敗（channel={$channel}）：".$e->getMessage());
        }

        $this->warn($message);
    }
}
