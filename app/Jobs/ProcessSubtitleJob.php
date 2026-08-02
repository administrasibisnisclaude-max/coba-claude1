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
            $url    = escapeshellarg($job->video_url);
            $out    = escapeshellarg($outputPath);
            $output = '';

            // Build shared flags (cookies + proxy optional via .env)
            $sharedFlags = $this->buildYtdlpFlags();

            // Try each player client in order until one succeeds
            $clients = ['android_vr', 'ios', 'mweb', 'android'];
            foreach ($clients as $client) {
                $cmd = implode(' ', array_filter([
                    $ytdlpPath,
                    '-x',
                    '--audio-format mp3',
                    '--audio-quality 0',
                    "--extractor-args \"youtube:player_client={$client}\"",
                    '--no-check-certificates',
                    '--retries 3',
                    '--fragment-retries 3',
                    $sharedFlags,
                    '-o', $out,
                    $url,
                    '2>&1',
                ]));

                $output .= "\n---client={$client}---\n" . shell_exec($cmd);

                if (file_exists($outputPath)) {
                    break;
                }
            }

            if (!file_exists($outputPath)) {
                $hint = "YouTube memblokir server ini.\n"
                    . "Solusi:\n"
                    . "1. Set YTDLP_COOKIES_FILE=/path/to/cookies.txt di .env (export cookies dari browser)\n"
                    . "2. Set YTDLP_PROXY=http://proxy:port di .env\n"
                    . "3. Atau gunakan URL video langsung (bukan YouTube)\n\n"
                    . "Detail error:\n" . $output;
                throw new \RuntimeException($hint);
            }
        } else {
            // Direct video URL — try yt-dlp first (handles more formats/auth),
            // then fall back to curl + ffmpeg
            $videoUrl  = $job->video_url;
            $audioOut  = escapeshellarg($outputPath);
            $sharedFlags = $this->buildYtdlpFlags();

            // Pre-flight: quick HEAD check to detect blocked/inaccessible URLs early
            $preflightCmd = implode(' ', [
                'curl -sI --max-time 15 --connect-timeout 10',
                '--retry 1',
                '-A', escapeshellarg('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'),
                '--no-check-certificate',
                '-o /dev/null -w "%{http_code}"',
                escapeshellarg($videoUrl),
            ]);
            $httpCode = trim(shell_exec($preflightCmd) ?: '0');

            if (in_array($httpCode, ['403', '401', '0'], true)) {
                $codeLabel = $httpCode === '0' ? 'tidak dapat dijangkau (timeout/DNS)' : "HTTP {$httpCode}";
                throw new \RuntimeException(
                    "URL video tidak dapat diakses dari server ini ({$codeLabel}).\n\n"
                    . "Kemungkinan penyebab:\n"
                    . "- Server memblokir IP hosting ini (hotlink protection / host_not_allowed)\n"
                    . "- Video memerlukan autentikasi\n"
                    . "- URL salah atau file tidak ada\n\n"
                    . "Solusi: Gunakan URL video yang dapat diakses secara publik tanpa pembatasan IP."
                );
            }

            // Attempt 1: yt-dlp (supports range requests, cookies, redirects)
            $ytCmd = implode(' ', array_filter([
                $ytdlpPath,
                '-x',
                '--audio-format mp3',
                '--audio-quality 0',
                '--no-check-certificates',
                '--retries 3',
                '--socket-timeout 30',
                $sharedFlags,
                '-o', $audioOut,
                escapeshellarg($videoUrl),
                '2>&1',
            ]));
            $ytOutput = shell_exec($ytCmd);

            // Attempt 2: ffmpeg direct stream (no full download needed)
            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                $ffmpegPath = trim(shell_exec('which ffmpeg') ?: '/usr/bin/ffmpeg');
                $referer = parse_url($videoUrl, PHP_URL_SCHEME) . '://' . parse_url($videoUrl, PHP_URL_HOST) . '/';
                $ffDirectCmd = implode(' ', [
                    $ffmpegPath,
                    '-timeout 30000000',
                    '-user_agent', escapeshellarg('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'),
                    '-headers', escapeshellarg('Referer: ' . $referer),
                    '-i', escapeshellarg($videoUrl),
                    '-vn -acodec libmp3lame -q:a 2',
                    $audioOut,
                    '-y 2>&1',
                ]);
                $ffDirectOutput = shell_exec($ffDirectCmd);
            }

            // Attempt 3: curl download + ffmpeg extract
            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                $tempVideo = storage_path('app/temp/video_' . $job->id . '_' . uniqid() . '.mp4');
                $tmpOut    = escapeshellarg($tempVideo);
                $referer   = parse_url($videoUrl, PHP_URL_SCHEME) . '://' . parse_url($videoUrl, PHP_URL_HOST) . '/';

                $dlCmd = implode(' ', [
                    'curl -sL --max-time 300',
                    '--retry 3',
                    '-A', escapeshellarg('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'),
                    '-e', escapeshellarg($referer),
                    '--no-check-certificate',
                    '-o', $tmpOut,
                    escapeshellarg($videoUrl),
                    '2>&1',
                ]);
                $curlOutput = shell_exec($dlCmd);

                if (file_exists($tempVideo) && filesize($tempVideo) > 0) {
                    $ffmpegPath = trim(shell_exec('which ffmpeg') ?: '/usr/bin/ffmpeg');
                    $ffCmd = "{$ffmpegPath} -i {$tmpOut} -vn -acodec libmp3lame -q:a 2 {$audioOut} -y 2>&1";
                    shell_exec($ffCmd);
                }

                @unlink($tempVideo ?? '');
            }

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException(
                    "Gagal mengunduh audio dari URL video.\n\n"
                    . "Kemungkinan penyebab:\n"
                    . "- Server video memblokir IP hosting (hotlink protection)\n"
                    . "- URL tidak dapat diakses dari server ini\n"
                    . "- Format video tidak didukung\n\n"
                    . "Detail: " . ($ytOutput ?? '') . ($ffDirectOutput ?? '') . ($curlOutput ?? '')
                );
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

    private function buildYtdlpFlags(): string
    {
        $flags = [];

        // Cookies file (export from browser via EditThisCookie or similar)
        $cookiesFile = env('YTDLP_COOKIES_FILE');
        if ($cookiesFile && file_exists($cookiesFile)) {
            $flags[] = '--cookies ' . escapeshellarg($cookiesFile);
        }

        // HTTP proxy (e.g. http://user:pass@proxy.example.com:3128)
        $proxy = env('YTDLP_PROXY');
        if ($proxy) {
            $flags[] = '--proxy ' . escapeshellarg($proxy);
        }

        return implode(' ', $flags);
    }
}
