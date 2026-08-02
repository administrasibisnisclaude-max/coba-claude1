<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubtitleJob extends Model
{
    protected $fillable = [
        'video_url',
        'video_type',
        'status',
        'error_message',
        'subtitle_vtt',
        'subtitle_srt',
        'language',
        'audio_path',
    ];

    public function isYoutube(): bool
    {
        return $this->video_type === 'youtube';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
