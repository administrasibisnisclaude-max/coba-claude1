<?php

use App\Http\Controllers\SubtitleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubtitleController::class, 'index']);

Route::post('/subtitle', [SubtitleController::class, 'store'])->name('subtitle.store');
Route::get('/subtitle/{subtitleJob}/status', [SubtitleController::class, 'status'])->name('subtitle.status');
Route::get('/subtitle/{subtitleJob}/download/srt', [SubtitleController::class, 'downloadSrt'])->name('subtitle.download.srt');
Route::get('/subtitle/{subtitleJob}/download/vtt', [SubtitleController::class, 'downloadVtt'])->name('subtitle.download.vtt');
