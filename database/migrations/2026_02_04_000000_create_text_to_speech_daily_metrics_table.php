<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_to_speech_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('driver', 50);
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('character_count_sum')->default(0);
            $table->unsignedBigInteger('estimated_cost_micros_sum')->default(0);
            $table->timestamps();

            $table->unique(['date', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_to_speech_daily_metrics');
    }
};
