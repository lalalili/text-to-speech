<?php

namespace Lalalili\TextToSpeech\Console;

use Illuminate\Console\Command;
use Lalalili\TextToSpeech\Models\TextToSpeechDailyMetric;
use Lalalili\TextToSpeech\Models\TextToSpeechMonthlyMetric;

class StatsCommand extends Command
{
    protected $signature = 'tts:stats
        {--date= : 指定日期 YYYY-MM-DD（顯示日指標）}
        {--month= : 指定月份 YYYY-MM（顯示月指標）}';

    protected $description = '顯示語音合成指標摘要';

    public function handle(): int
    {
        $monthOption = $this->option('month');
        if (is_string($monthOption) && $monthOption !== '') {
            $month = $monthOption;
            $this->showMonthly($month);
        } else {
            $dateOption = $this->option('date');
            $date = is_string($dateOption) && $dateOption !== ''
                ? $dateOption
                : now()->toDateString();
            $this->showDaily($date);
        }

        return self::SUCCESS;
    }

    private function showDaily(string $date): void
    {
        $metric = TextToSpeechDailyMetric::query()->whereDate('date', $date)->first();

        if (! $metric) {
            $this->warn("找不到 {$date} 的日指標（尚未彙總或無資料）。");

            return;
        }

        $this->info("=== TTS 日指標：{$date} ===");
        $this->table(
            ['項目', '數值'],
            $this->formatRows($metric->toArray()),
        );
    }

    private function showMonthly(string $month): void
    {
        $metric = TextToSpeechMonthlyMetric::query()
            ->whereYear('month', substr($month, 0, 4))
            ->whereMonth('month', substr($month, 5, 2))
            ->first();

        if (! $metric) {
            $this->warn("找不到 {$month} 的月指標（尚未彙總或無資料）。");

            return;
        }

        $this->info("=== TTS 月指標：{$month} ===");
        $this->table(
            ['項目', '數值'],
            $this->formatRows($metric->toArray()),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{string, mixed}>
     */
    private function formatRows(array $data): array
    {
        $labels = [
            'requests_count'            => '總請求數',
            'success_count'             => '成功數',
            'failed_count'              => '失敗數',
            'retry_requests_count'      => '重試請求數',
            'retry_count_sum'           => '重試總次數',
            'cache_hit_count'           => '快取命中數',
            'character_count_sum'       => '字元總數',
            'estimated_cost_micros_sum' => '估算費用（micros）',
        ];

        $rows = [];

        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $data)) {
                $rows[] = [$label, $data[$key]];
            }
        }

        return $rows;
    }
}
