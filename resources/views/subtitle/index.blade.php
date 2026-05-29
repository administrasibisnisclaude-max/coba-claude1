<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>AI Video Subtitle Generator</title>

    <!-- Video.js -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .video-js { width: 100%; border-radius: 0.75rem; overflow: hidden; }
        .vjs-default-skin .vjs-big-play-button { left: 50%; top: 50%; transform: translate(-50%, -50%); }
        .subtitle-track { background: rgba(0,0,0,0.75); }
        ::cue { background-color: rgba(0,0,0,0.8); color: #fff; font-size: 1.1em; padding: 2px 6px; border-radius: 4px; }
        .loader-dots span { animation: blink 1.4s infinite both; }
        .loader-dots span:nth-child(2) { animation-delay: 0.2s; }
        .loader-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes blink { 0%,80%,100%{opacity:0} 40%{opacity:1} }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900 min-h-screen text-white">

<div class="container mx-auto px-4 py-10 max-w-4xl">

    <!-- Header -->
    <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-600 rounded-2xl mb-4 shadow-lg shadow-purple-500/30">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
            </svg>
        </div>
        <h1 class="text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
            AI Subtitle Generator
        </h1>
        <p class="text-slate-400 text-lg">Paste URL video — subtitle otomatis dihasilkan oleh AI (OpenAI Whisper)</p>
    </div>

    <!-- Form Card -->
    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 mb-6 shadow-xl">
        <form id="subtitleForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">URL Video</label>
                <div class="flex gap-3">
                    <input
                        id="videoUrl"
                        type="url"
                        placeholder="https://www.youtube.com/watch?v=... atau URL video langsung"
                        class="flex-1 bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                        required
                    />
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Bahasa (opsional)</label>
                    <select id="language" class="w-full bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
                        <option value="auto">Auto Detect</option>
                        <option value="id">Indonesia</option>
                        <option value="en">English</option>
                        <option value="ms">Melayu</option>
                        <option value="ja">日本語</option>
                        <option value="ko">한국어</option>
                        <option value="zh">中文</option>
                        <option value="es">Español</option>
                        <option value="fr">Français</option>
                        <option value="de">Deutsch</option>
                        <option value="ar">العربية</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        type="submit"
                        id="submitBtn"
                        class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 rounded-xl font-semibold transition-all transform hover:scale-105 shadow-lg shadow-purple-500/30 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Generate Subtitle
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Status / Loading -->
    <div id="statusSection" class="hidden">
        <div id="loadingState" class="bg-white/5 border border-white/10 rounded-2xl p-6 text-center fade-in">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-purple-600/20 rounded-full mb-4 animate-pulse">
                <svg class="w-7 h-7 text-purple-400 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
            <p class="text-lg font-semibold text-purple-300">Memproses Video</p>
            <p id="statusText" class="text-slate-400 mt-1">Menginisialisasi<span class="loader-dots"><span>.</span><span>.</span><span>.</span></span></p>
            <div class="mt-4 bg-slate-800 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full transition-all duration-500" style="width: 10%"></div>
            </div>
        </div>

        <div id="errorState" class="hidden bg-red-900/20 border border-red-500/30 rounded-2xl p-6 fade-in">
            <div class="flex items-start gap-3">
                <div class="text-red-400 mt-0.5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-red-300">Gagal Memproses</p>
                    <p id="errorMessage" class="text-red-400 text-sm mt-1"></p>
                </div>
            </div>
            <button onclick="resetForm()" class="mt-4 text-sm text-slate-400 hover:text-white transition">← Coba Lagi</button>
        </div>
    </div>

    <!-- Player Section -->
    <div id="playerSection" class="hidden fade-in">
        <!-- YouTube embed -->
        <div id="youtubeContainer" class="hidden">
            <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden shadow-xl">
                <div class="aspect-video">
                    <iframe id="youtubeFrame" class="w-full h-full" frameborder="0" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                    </iframe>
                </div>
            </div>
        </div>

        <!-- Direct video player -->
        <div id="directContainer" class="hidden">
            <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden shadow-xl">
                <video id="videoPlayer" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" data-setup='{}'>
                </video>
            </div>
        </div>

        <!-- Subtitle Display -->
        <div class="mt-4 bg-white/5 border border-white/10 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Subtitle
                </h2>
                <div id="downloadButtons" class="flex gap-2">
                    <a id="downloadSrt" href="#" class="text-xs px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        SRT
                    </a>
                    <a id="downloadVtt" href="#" class="text-xs px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        VTT
                    </a>
                </div>
            </div>

            <!-- Subtitle text area -->
            <div id="subtitleText" class="bg-slate-900/60 rounded-xl p-4 max-h-64 overflow-y-auto text-sm leading-relaxed text-slate-300 font-mono whitespace-pre-wrap">
                Subtitle akan muncul di sini setelah diproses...
            </div>

            <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                <svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Dihasilkan oleh OpenAI Whisper AI
            </div>
        </div>

        <div class="mt-4 text-center">
            <button onclick="resetForm()" class="text-sm text-slate-400 hover:text-white transition flex items-center gap-2 mx-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Proses Video Lain
            </button>
        </div>
    </div>

</div>

<script>
    let currentJobId = null;
    let pollInterval = null;
    let videoPlayer = null;

    const form = document.getElementById('subtitleForm');
    const submitBtn = document.getElementById('submitBtn');
    const statusSection = document.getElementById('statusSection');
    const loadingState = document.getElementById('loadingState');
    const errorState = document.getElementById('errorState');
    const playerSection = document.getElementById('playerSection');
    const statusText = document.getElementById('statusText');
    const progressBar = document.getElementById('progressBar');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const videoUrl = document.getElementById('videoUrl').value.trim();
        const language = document.getElementById('language').value;

        if (!videoUrl) return;

        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
        form.parentElement.classList.add('opacity-60');

        statusSection.classList.remove('hidden');
        loadingState.classList.remove('hidden');
        errorState.classList.add('hidden');
        playerSection.classList.add('hidden');

        setProgress(15, 'Mengirim permintaan...');

        try {
            const res = await fetch('/subtitle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ video_url: videoUrl, language }),
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Gagal memulai proses');
            }

            const data = await res.json();
            currentJobId = data.job_id;
            setProgress(25, 'Mengunduh audio...');
            startPolling();
        } catch (err) {
            showError(err.message);
        }
    });

    function startPolling() {
        let ticks = 0;
        pollInterval = setInterval(async () => {
            ticks++;
            try {
                const res = await fetch(`/subtitle/${currentJobId}/status`);
                const data = await res.json();

                if (data.status === 'processing') {
                    const pct = Math.min(25 + ticks * 5, 85);
                    setProgress(pct, 'Transkripsi audio dengan AI...');
                } else if (data.status === 'completed') {
                    clearInterval(pollInterval);
                    setProgress(100, 'Selesai!');
                    setTimeout(() => showPlayer(data), 500);
                } else if (data.status === 'failed') {
                    clearInterval(pollInterval);
                    showError(data.error_message || 'Terjadi kesalahan saat memproses.');
                }
            } catch (err) {
                // network glitch, keep polling
            }
        }, 3000);
    }

    function setProgress(pct, text) {
        progressBar.style.width = pct + '%';
        statusText.innerHTML = text + (pct < 100 ? '<span class="loader-dots"><span>.</span><span>.</span><span>.</span></span>' : '');
    }

    function showPlayer(data) {
        loadingState.classList.add('hidden');
        statusSection.classList.add('hidden');
        playerSection.classList.remove('hidden');
        playerSection.classList.add('fade-in');

        const youtubeContainer = document.getElementById('youtubeContainer');
        const directContainer = document.getElementById('directContainer');

        if (data.video_type === 'youtube') {
            youtubeContainer.classList.remove('hidden');
            directContainer.classList.add('hidden');

            const youtubeId = extractYoutubeId(data.video_url);
            const iframe = document.getElementById('youtubeFrame');

            // YouTube doesn't support external subtitle overlay easily,
            // show subtitles in the text box only
            if (youtubeId) {
                iframe.src = `https://www.youtube.com/embed/${youtubeId}?rel=0`;
            }
        } else {
            directContainer.classList.remove('hidden');
            youtubeContainer.classList.add('hidden');

            // Destroy previous player
            if (videoPlayer) {
                videoPlayer.dispose();
                const oldEl = document.getElementById('videoPlayer');
                const container = directContainer.querySelector('.bg-white\\/5');
                const newEl = document.createElement('video');
                newEl.id = 'videoPlayer';
                newEl.className = 'video-js vjs-default-skin vjs-big-play-centered';
                newEl.setAttribute('controls', '');
                newEl.setAttribute('preload', 'auto');
                container.appendChild(newEl);
            }

            if (data.subtitle_vtt) {
                const vttBlob = new Blob([data.subtitle_vtt], { type: 'text/vtt' });
                const vttUrl = URL.createObjectURL(vttBlob);

                videoPlayer = videojs('videoPlayer', {
                    sources: [{ src: data.video_url, type: guessVideoType(data.video_url) }],
                    tracks: [{ kind: 'subtitles', label: 'Subtitle', srclang: 'id', src: vttUrl, default: true }],
                });
            } else {
                videoPlayer = videojs('videoPlayer', {
                    sources: [{ src: data.video_url, type: guessVideoType(data.video_url) }],
                });
            }
        }

        // Show subtitle text
        if (data.subtitle_vtt) {
            const subtitleLines = parseVttToText(data.subtitle_vtt);
            document.getElementById('subtitleText').textContent = subtitleLines;
        }

        // Download links
        document.getElementById('downloadSrt').href = `/subtitle/${data.id}/download/srt`;
        document.getElementById('downloadVtt').href = `/subtitle/${data.id}/download/vtt`;
    }

    function showError(message) {
        loadingState.classList.add('hidden');
        errorState.classList.remove('hidden');
        document.getElementById('errorMessage').textContent = message;
    }

    function resetForm() {
        clearInterval(pollInterval);
        currentJobId = null;

        if (videoPlayer) {
            try { videoPlayer.dispose(); } catch(e) {}
            videoPlayer = null;
        }

        form.parentElement.classList.remove('opacity-60');
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
        document.getElementById('videoUrl').value = '';

        statusSection.classList.add('hidden');
        playerSection.classList.add('hidden');
        loadingState.classList.remove('hidden');
        errorState.classList.add('hidden');

        setProgress(10, 'Menginisialisasi');
    }

    function extractYoutubeId(url) {
        const regExp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
        const match = url.match(regExp);
        return match ? match[1] : null;
    }

    function guessVideoType(url) {
        if (url.includes('.mp4')) return 'video/mp4';
        if (url.includes('.webm')) return 'video/webm';
        if (url.includes('.ogg')) return 'video/ogg';
        if (url.includes('.m3u8')) return 'application/x-mpegURL';
        return 'video/mp4';
    }

    function parseVttToText(vtt) {
        return vtt
            .split('\n')
            .filter(line => line && !line.startsWith('WEBVTT') && !line.match(/^\d+$/) && !line.match(/\d{2}:\d{2}:\d{2}\.\d{3}/))
            .join('\n')
            .trim();
    }
</script>

</body>
</html>
