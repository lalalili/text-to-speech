<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_to_speech_daily_metrics', function (Blueprint $table): void {
            $table->unsignedInteger('retry_requests_count')->default(0)->after('failed_count');
            $table->unsignedBigInteger('retry_count_sum')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('text_to_speech_daily_metrics', function (Blueprint $table): void {
            $table->dropColumn(['retry_requests_count', 'retry_count_sum']);
        });
    }
};
