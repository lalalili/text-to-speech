<?php

namespace Lalalili\TextToSpeech\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lalalili\TextToSpeech\Models\TextToSpeechRequest;

class CleanupCommand extends Command
{
    protected $signature = 'tts:cleanup
        {--days= : 保留天數（預設讀取 TTS_CLEANUP_DAYS）}
        {--dry-run : 僅列出不實際刪除}';

    protected $description = '清理逾期音檔與資料庫記錄';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('text-to-speech.cleanup.days', 30));
        $dryRun = $this->option('dry-run') || (bool) config('text-to-speech.cleanup.dry_run', false);
        $chunk = (int) config('text-to-speech.cleanup.chunk', 200);

        $cutoff = now()->subDays($days);

        $this->info(sprintf(
            '清理 %d 天前的記錄（截止 %s）%s',
            $days,
            $cutoff->toDateTimeString(),
            $dryRun ? ' [dry-run]' : '',
        ));

        $total = 0;

        TextToSpeechRequest::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunk($chunk, function ($requests) use ($dryRun, &$total): void {
                foreach ($requests as $request) {
                    $this->line("  id={$request->id} status={$request->status} path={$request->path}");

                    if (! $dryRun) {
                        if ($request->status === TextToSpeechRequest::STATUS_READY && $request->path !== '') {
                            Storage::disk($request->disk)->delete($request->path);
                        }

                        $request->delete();
                    }

                    $total++;
                }
            });

        if ($dryRun) {
            $this->info("找到 {$total} 筆（dry-run，未刪除）。");
        } else {
            $this->info("已刪除 {$total} 筆。");
        }

        return self::SUCCESS;
    }
}
