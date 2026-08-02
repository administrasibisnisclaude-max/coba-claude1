<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subtitle_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('video_url');
            $table->string('video_type')->default('direct');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->longText('subtitle_vtt')->nullable();
            $table->longText('subtitle_srt')->nullable();
            $table->string('language')->default('auto');
            $table->string('audio_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtitle_jobs');
    }
};
