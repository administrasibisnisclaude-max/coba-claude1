<?php

namespace App\Jobs;

use App\Models\SubtitleJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class ProcessSubtitleJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(public int $subtitleJobId) {}

    public function handle(): void
    {
        $job = SubtitleJob::findOrFail($this->subtitleJobId);
        $job->update(['status' => 'processing']);

        try {
            $audioPath = $this->extractAudio($job);
            $job->update(['audio_path' => $audioPath]);

            $transcription = $this->transcribeAudio($audioPath, $job->language);

            $vtt = $this->toWebVTT($transcription);
            $srt = $this->toSRT($transcription);

            $job->update([
                'status' => 'completed',
                'subtitle_vtt' => $vtt,
                'subtitle_srt' => $srt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Subtitle job failed', ['id' => $job->id, 'error' => $e->getMessage()]);
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        } finally {
            if (!empty($audioPath) && Storage::exists($audioPath)) {
                Storage::delete($audioPath);
            }
        }
    }

    private function extractAudio(SubtitleJob $job): string
    {
        $filename = 'audio_' . $job->id . '_' . uniqid() . '.mp3';
        $outputPath = storage_path('app/temp/' . $filename);

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $ytdlpPath = trim(shell_exec('which yt-dlp') ?: '');
        if (empty($ytdlpPath)) {
            $ytdlpPath = '/usr/local/bin/yt-dlp';
        }

        if ($job->video_type === 'youtube') {
            $url = escapeshellarg($job->video_url);
            $out = escapeshellarg($outputPath);
            $cmd = "{$ytdlpPath} -x --audio-format mp3 --audio-quality 0 -o {$out} {$url} 2>&1";
            $output = shell_exec($cmd);

            if (!file_exists($outputPath)) {
                throw new \RuntimeException("yt-dlp failed to extract audio.\n" . $output);
            }
        } else {
            // Direct video URL — download and extract audio with ffmpeg
            $tempVideo = storage_path('app/temp/video_' . $job->id . '_' . uniqid() . '.mp4');
            $videoUrl = escapeshellarg($job->video_url);
            $tmpOut = escapeshellarg($tempVideo);
            $audioOut = escapeshellarg($outputPath);

            $dlCmd = "curl -sL --max-time 300 -o {$tmpOut} {$videoUrl} 2>&1";
            shell_exec($dlCmd);

            if (!file_exists($tempVideo) || filesize($tempVideo) === 0) {
                throw new \RuntimeException('Failed to download video from URL.');
            }

            $ffmpegPath = trim(shell_exec('which ffmpeg') ?: '/usr/bin/ffmpeg');
            $ffCmd = "{$ffmpegPath} -i {$tmpOut} -vn -acodec libmp3lame -q:a 2 {$audioOut} -y 2>&1";
            shell_exec($ffCmd);

            @unlink($tempVideo);

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException('Failed to extract audio with ffmpeg.');
            }
        }

        return 'temp/' . $filename;
    }

    private function transcribeAudio(string $storagePath, string $language): array
    {
        $fullPath = storage_path('app/' . $storagePath);

        if (filesize($fullPath) > 25 * 1024 * 1024) {
            throw new \RuntimeException('Audio file exceeds 25MB limit for Whisper API.');
        }

        $params = [
            'model' => 'whisper-1',
            'file' => fopen($fullPath, 'r'),
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['segment'],
        ];

        if ($language !== 'auto') {
            $params['language'] = $language;
        }

        $response = OpenAI::audio()->transcribe($params);

        return $response->segments ?? [];
    }

    private function toWebVTT(array $segments): string
    {
        $lines = ["WEBVTT\n"];

        foreach ($segments as $i => $segment) {
            $start = $this->formatVttTime($segment->start ?? 0);
            $end = $this->formatVttTime($segment->end ?? 0);
            $text = trim($segment->text ?? '');

            $lines[] = ($i + 1) . "\n{$start} --> {$end}\n{$text}\n";
        }

        return implode("\n", $lines);
    }

    private function toSRT(array $segments): string
    {
        $lines = [];

        foreach ($segments as $i => $segment) {
            $start = $this->formatSrtTime($segment->start ?? 0);
            $end = $this->formatSrtTime($segment->end ?? 0);
            $text = trim($segment->text ?? '');

            $lines[] = ($i + 1) . "\n{$start} --> {$end}\n{$text}\n";
        }

        return implode("\n", $lines);
    }

    private function formatVttTime(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds - ($h * 3600) - ($m * 60);

        return sprintf('%02d:%02d:%06.3f', $h, $m, $s);
    }

    private function formatSrtTime(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = floor($seconds - ($h * 3600) - ($m * 60));
        $ms = round(($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
    }
}
