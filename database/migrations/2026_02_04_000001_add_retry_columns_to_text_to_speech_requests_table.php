<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('text_to_speech_requests')) {
            return;
        }

        if (! Schema::hasColumn('text_to_speech_requests', 'retry_count')) {
            Schema::table('text_to_speech_requests', function (Blueprint $table): void {
                $table->unsignedInteger('retry_count')->default(0)->after('limit_exceeded');
            });
        }

        if (! Schema::hasColumn('text_to_speech_requests', 'last_error_code')) {
            Schema::table('text_to_speech_requests', function (Blueprint $table): void {
                $table->string('last_error_code', 20)->nullable()->after('error_message');
            });
        }

        if (! Schema::hasColumn('text_to_speech_requests', 'cache_hit')) {
            Schema::table('text_to_speech_requests', function (Blueprint $table): void {
                $table->boolean('cache_hit')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('text_to_speech_requests')) {
            return;
        }

        Schema::table('text_to_speech_requests', function (Blueprint $table): void {
            $columns = collect(['retry_count', 'last_error_code', 'cache_hit'])
                ->filter(fn (string $column): bool => Schema::hasColumn('text_to_speech_requests', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
