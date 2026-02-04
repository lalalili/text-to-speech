<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_to_speech_requests', function (Blueprint $table): void {
            $table->unsignedInteger('retry_count')->default(0)->after('limit_exceeded');
            $table->string('last_error_code', 20)->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('text_to_speech_requests', function (Blueprint $table): void {
            $table->dropColumn(['retry_count', 'last_error_code']);
        });
    }
};
