<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('text_to_speech_daily_metrics')) {
            return;
        }

        if (! Schema::hasColumn('text_to_speech_daily_metrics', 'retry_requests_count')) {
            Schema::table('text_to_speech_daily_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('retry_requests_count')->default(0)->after('failed_count');
            });
        }

        if (! Schema::hasColumn('text_to_speech_daily_metrics', 'retry_count_sum')) {
            Schema::table('text_to_speech_daily_metrics', function (Blueprint $table): void {
                $table->unsignedBigInteger('retry_count_sum')->default(0)->after('failed_count');
            });
        }

        if (! Schema::hasColumn('text_to_speech_daily_metrics', 'cache_hit_count')) {
            Schema::table('text_to_speech_daily_metrics', function (Blueprint $table): void {
                $table->unsignedInteger('cache_hit_count')->default(0)->after('retry_count_sum');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('text_to_speech_daily_metrics')) {
            return;
        }

        Schema::table('text_to_speech_daily_metrics', function (Blueprint $table): void {
            $columns = collect(['retry_requests_count', 'retry_count_sum', 'cache_hit_count'])
                ->filter(fn (string $column): bool => Schema::hasColumn('text_to_speech_daily_metrics', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
