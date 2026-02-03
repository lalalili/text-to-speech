<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_to_speech_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('hash', 64)->unique();
            $table->string('driver', 50);
            $table->string('input_type', 20);
            $table->string('voice', 100);
            $table->string('language_code', 20);
            $table->decimal('speaking_rate', 5, 2);
            $table->decimal('pitch', 5, 2);
            $table->string('audio_format', 20);
            $table->unsignedInteger('sample_rate_hertz')->nullable();
            $table->string('effects_profile_id', 100)->nullable();
            $table->unsignedInteger('character_count');
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->boolean('limit_exceeded')->default(false);
            $table->string('status', 20);
            $table->string('disk', 50);
            $table->string('path', 255);
            $table->text('url')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_to_speech_requests');
    }
};
