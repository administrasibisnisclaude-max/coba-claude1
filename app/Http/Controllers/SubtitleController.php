<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSubtitleJob;
use App\Models\SubtitleJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SubtitleController extends Controller
{
    public function index(): View
    {
        return view('subtitle.index');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_url' => ['required', 'url', 'max:2048'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $videoUrl = $validated['video_url'];
        $language = $validated['language'] ?? 'auto';
        $videoType = $this->detectVideoType($videoUrl);

        $job = SubtitleJob::create([
            'video_url' => $videoUrl,
            'video_type' => $videoType,
            'language' => $language,
            'status' => 'pending',
        ]);

        ProcessSubtitleJob::dispatch($job->id);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
            'video_url' => $videoUrl,
            'video_type' => $videoType,
        ]);
    }

    public function status(SubtitleJob $subtitleJob): JsonResponse
    {
        return response()->json([
            'id' => $subtitleJob->id,
            'status' => $subtitleJob->status,
            'video_url' => $subtitleJob->video_url,
            'video_type' => $subtitleJob->video_type,
            'subtitle_vtt' => $subtitleJob->subtitle_vtt,
            'error_message' => $subtitleJob->error_message,
        ]);
    }

    public function downloadSrt(SubtitleJob $subtitleJob)
    {
        abort_unless($subtitleJob->isCompleted(), 404);

        return response($subtitleJob->subtitle_srt, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="subtitle_' . $subtitleJob->id . '.srt"',
        ]);
    }

    public function downloadVtt(SubtitleJob $subtitleJob)
    {
        abort_unless($subtitleJob->isCompleted(), 404);

        return response($subtitleJob->subtitle_vtt, 200, [
            'Content-Type' => 'text/vtt',
            'Content-Disposition' => 'attachment; filename="subtitle_' . $subtitleJob->id . '.vtt"',
        ]);
    }

    private function detectVideoType(string $url): string
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        }

        return 'direct';
    }
}
