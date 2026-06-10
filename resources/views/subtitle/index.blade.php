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

    <!-- YouTube IFrame API -->
    <script src="https://www.youtube.com/iframe_api"></script>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Video.js player */
        .video-js { width: 100%; }
        .vjs-big-play-button { left: 50% !important; top: 50% !important; transform: translate(-50%, -50%) !important; }
        ::cue { background-color: rgba(0,0,0,0.85); color: #fff; font-size: 1.15em; }

        /* YouTube custom subtitle overlay */
        #ytSubtitleOverlay {
            position: absolute;
            bottom: 56px;
            left: 0; right: 0;
            display: flex;
            justify-content: center;
            pointer-events: none;
            z-index: 10;
            padding: 0 12px;
        }
        #ytSubtitleText {
            background: rgba(0,0,0,0.82);
            color: #fff;
            font-size: 1.15rem;
            line-height: 1.5;
            padding: 6px 14px;
            border-radius: 6px;
            text-align: center;
            max-width: 90%;
            text-shadow: 0 1px 3px rgba(0,0,0,.8);
            transition: opacity 0.15s;
        }
        #ytSubtitleText:empty { display: none; }

        /* Scrollable transcript */
        .transcript-line { cursor: pointer; border-left: 3px solid transparent; transition: all .15s; }
        .transcript-line:hover { background: rgba(255,255,255,.06); border-left-color: #a78bfa; }
        .transcript-line.active { background: rgba(139,92,246,.15); border-left-color: #a78bfa; color: #e9d5ff; }

        /* Transcript panel — match video column height on desktop */
        @media (min-width: 1024px) {
            #transcriptPanel {
                max-height: 520px;
            }
            #transcriptList {
                max-height: unset !important;
                flex: 1 1 0%;
                min-height: 0;
            }
        }

        /* Loaders & animations */
        .loader-dots span { animation: blink 1.4s infinite both; }
        .loader-dots span:nth-child(2) { animation-delay: .2s; }
        .loader-dots span:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%,80%,100%{opacity:0} 40%{opacity:1} }
        .fade-in { animation: fadeIn .5s ease-in; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900 min-h-screen text-white">

<div class="container mx-auto px-4 py-10 max-w-7xl">

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

        <!-- ── Baris utama: video (kiri) + transkrip (kanan) ── -->
        <div class="flex flex-col lg:flex-row gap-5 items-start">

            <!-- Kolom video (kiri, 60%) -->
            <div class="w-full lg:w-[60%] shrink-0">

                <!-- YouTube player dengan subtitle overlay -->
                <div id="youtubeContainer" class="hidden">
                    <div class="relative bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                        <div class="aspect-video" id="ytPlayerWrap">
                            <div id="ytPlayer" style="width:100%;height:100%;"></div>
                        </div>
                        <div id="ytSubtitleOverlay">
                            <span id="ytSubtitleText"></span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 text-center">Subtitle overlay real-time — dihasilkan oleh OpenAI Whisper</p>
                </div>

                <!-- Direct video player (Video.js + VTT track) -->
                <div id="directContainer" class="hidden">
                    <div class="bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                        <video
                            id="videoPlayer"
                            class="video-js vjs-default-skin vjs-big-play-centered vjs-fluid"
                            controls
                            preload="auto"
                            crossorigin="anonymous"
                        ></video>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 text-center">Subtitle otomatis aktif saat diputar</p>
                </div>

                <div class="mt-4 text-center">
                    <button onclick="resetForm()"
                        class="text-sm text-slate-400 hover:text-white transition flex items-center gap-2 mx-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Proses Video Lain
                    </button>
                </div>
            </div>

            <!-- Kolom transkrip (kanan, 40%) -->
            <div class="w-full lg:flex-1 bg-white/5 border border-white/10 rounded-2xl shadow-xl overflow-hidden flex flex-col" id="transcriptPanel">

                <!-- Header transkrip -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-white/10 shrink-0">
                    <h2 class="text-base font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h12"/>
                        </svg>
                        Transkrip
                    </h2>
                    <div class="flex gap-2">
                        <a id="downloadSrt" href="#"
                            class="text-xs px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg transition flex items-center gap-1 font-medium">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            SRT
                        </a>
                        <a id="downloadVtt" href="#"
                            class="text-xs px-3 py-1.5 bg-purple-700 hover:bg-purple-600 rounded-lg transition flex items-center gap-1 font-medium">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            VTT
                        </a>
                    </div>
                </div>

                <!-- List transkrip — tinggi mengikuti video -->
                <div id="transcriptList"
                    class="divide-y divide-white/5 overflow-y-auto text-sm text-slate-300 flex-1"
                    style="max-height: 420px;">
                </div>

                <div class="px-5 py-3 border-t border-white/10 shrink-0 flex items-center gap-2 text-xs text-slate-500">
                    <svg class="w-3.5 h-3.5 text-green-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    OpenAI Whisper AI · klik untuk loncat ke waktu
                </div>
            </div>

        </div><!-- /baris utama -->
    </div>

</div>

<script>
    /* ─── State ─────────────────────────────────── */
    let currentJobId   = null;
    let pollInterval   = null;
    let vjsPlayer      = null;   // Video.js instance (direct video)
    let ytPlayer       = null;   // YouTube IFrame API instance
    let ytSubTimer     = null;   // setInterval for YouTube subtitle sync
    let parsedSegments = [];     // [{start, end, text}]

    /* ─── DOM refs ───────────────────────────────── */
    const form          = document.getElementById('subtitleForm');
    const submitBtn     = document.getElementById('submitBtn');
    const statusSection = document.getElementById('statusSection');
    const loadingState  = document.getElementById('loadingState');
    const errorState    = document.getElementById('errorState');
    const playerSection = document.getElementById('playerSection');
    const statusText    = document.getElementById('statusText');
    const progressBar   = document.getElementById('progressBar');

    /* ─── Form submit ────────────────────────────── */
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
                const d = await res.json();
                throw new Error(d.message || 'Gagal memulai proses');
            }
            const data = await res.json();
            currentJobId = data.job_id;
            setProgress(25, 'Mengunduh audio dari video...');
            startPolling();
        } catch (err) {
            showError(err.message);
        }
    });

    /* ─── Polling ────────────────────────────────── */
    function startPolling() {
        let ticks = 0;
        pollInterval = setInterval(async () => {
            ticks++;
            try {
                const res  = await fetch(`/subtitle/${currentJobId}/status`);
                const data = await res.json();
                if (data.status === 'processing') {
                    setProgress(Math.min(30 + ticks * 5, 85), 'Transkripsi audio dengan AI Whisper...');
                } else if (data.status === 'completed') {
                    clearInterval(pollInterval);
                    setProgress(100, 'Selesai!');
                    setTimeout(() => showPlayer(data), 600);
                } else if (data.status === 'failed') {
                    clearInterval(pollInterval);
                    showError(data.error_message || 'Terjadi kesalahan saat memproses.');
                }
            } catch (_) { /* keep polling on network glitch */ }
        }, 3000);
    }

    /* ─── Show player ────────────────────────────── */
    function showPlayer(data) {
        loadingState.classList.add('hidden');
        statusSection.classList.add('hidden');
        playerSection.classList.remove('hidden');

        // Parse VTT into segments array for transcript & overlay
        if (data.subtitle_vtt) {
            parsedSegments = parseVtt(data.subtitle_vtt);
        }

        if (data.video_type === 'youtube') {
            initYouTubePlayer(data);
        } else {
            initDirectPlayer(data);
        }

        buildTranscript(parsedSegments, data.video_type);

        document.getElementById('downloadSrt').href = `/subtitle/${data.id}/download/srt`;
        document.getElementById('downloadVtt').href = `/subtitle/${data.id}/download/vtt`;
    }

    /* ─── YouTube player ─────────────────────────── */
    function initYouTubePlayer(data) {
        document.getElementById('youtubeContainer').classList.remove('hidden');
        document.getElementById('directContainer').classList.add('hidden');

        const videoId = extractYoutubeId(data.video_url);
        if (!videoId) { showError('Tidak dapat mengekstrak ID YouTube.'); return; }

        // Destroy old YT player if exists
        if (ytPlayer && typeof ytPlayer.destroy === 'function') {
            ytPlayer.destroy();
            ytPlayer = null;
        }
        clearInterval(ytSubTimer);

        // Reset div (YT API replaces the element)
        document.getElementById('ytPlayer').innerHTML = '';

        ytPlayer = new YT.Player('ytPlayer', {
            videoId,
            width:  '100%',
            height: '100%',
            playerVars: { rel: 0, modestbranding: 1, playsinline: 1 },
            events: {
                onReady: () => {
                    // Start subtitle sync loop
                    ytSubTimer = setInterval(() => syncYtSubtitle(), 200);
                },
            },
        });
    }

    function syncYtSubtitle() {
        if (!ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;
        const t    = ytPlayer.getCurrentTime();
        const seg  = parsedSegments.find(s => t >= s.start && t < s.end);
        const el   = document.getElementById('ytSubtitleText');
        el.textContent = seg ? seg.text : '';
        highlightTranscriptLine(t);
    }

    /* ─── Direct video player (Video.js) ─────────── */
    function initDirectPlayer(data) {
        document.getElementById('directContainer').classList.remove('hidden');
        document.getElementById('youtubeContainer').classList.add('hidden');

        // Tear down previous instance
        if (vjsPlayer) {
            try { vjsPlayer.dispose(); } catch (_) {}
            vjsPlayer = null;
        }

        // Re-create the <video> element (dispose removes the DOM node)
        const wrap = document.querySelector('#directContainer .bg-black');
        wrap.innerHTML =
            '<video id="videoPlayer" class="video-js vjs-default-skin vjs-big-play-centered vjs-fluid" controls preload="auto" crossorigin="anonymous"></video>';

        const opts = {
            fluid: true,
            responsive: true,
            sources: [{ src: data.video_url, type: guessVideoType(data.video_url) }],
        };

        vjsPlayer = videojs('videoPlayer', opts);

        // Add subtitle track after player ready
        if (data.subtitle_vtt && parsedSegments.length) {
            const vttBlob = new Blob([data.subtitle_vtt], { type: 'text/vtt' });
            const vttUrl  = URL.createObjectURL(vttBlob);

            vjsPlayer.ready(() => {
                vjsPlayer.addRemoteTextTrack({
                    kind:    'subtitles',
                    label:   'Subtitle (AI)',
                    srclang: 'id',
                    src:     vttUrl,
                    default: true,
                }, false);

                // Force track to showing mode
                const tracks = vjsPlayer.textTracks();
                for (let i = 0; i < tracks.length; i++) {
                    if (tracks[i].kind === 'subtitles') {
                        tracks[i].mode = 'showing';
                    }
                }

                // Sync transcript highlight with timeupdate
                vjsPlayer.on('timeupdate', () => {
                    highlightTranscriptLine(vjsPlayer.currentTime());
                });
            });
        }
    }

    /* ─── Transcript panel ───────────────────────── */
    function buildTranscript(segments, videoType) {
        const list = document.getElementById('transcriptList');
        list.innerHTML = '';
        if (!segments.length) {
            list.innerHTML = '<p class="px-5 py-4 text-slate-500 text-sm">Tidak ada transkrip.</p>';
            return;
        }

        segments.forEach((seg, idx) => {
            const div = document.createElement('div');
            div.className = 'transcript-line flex gap-3 px-5 py-2.5';
            div.dataset.idx   = idx;
            div.dataset.start = seg.start;
            div.innerHTML = `
                <span class="text-purple-400 font-mono text-xs shrink-0 pt-0.5 w-14">${formatTime(seg.start)}</span>
                <span class="text-slate-300 text-sm leading-relaxed">${escHtml(seg.text)}</span>`;

            div.addEventListener('click', () => seekTo(seg.start, videoType));
            list.appendChild(div);
        });
    }

    function highlightTranscriptLine(t) {
        const idx = parsedSegments.findIndex(s => t >= s.start && t < s.end);
        document.querySelectorAll('.transcript-line').forEach((el, i) => {
            el.classList.toggle('active', i === idx);
        });
        if (idx >= 0) {
            const active = document.querySelector(`.transcript-line[data-idx="${idx}"]`);
            if (active) active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function seekTo(seconds, videoType) {
        if (videoType === 'youtube' && ytPlayer && typeof ytPlayer.seekTo === 'function') {
            ytPlayer.seekTo(seconds, true);
        } else if (vjsPlayer) {
            vjsPlayer.currentTime(seconds);
            vjsPlayer.play();
        }
    }

    /* ─── Helpers ────────────────────────────────── */
    function setProgress(pct, text) {
        progressBar.style.width = pct + '%';
        statusText.innerHTML = text + (pct < 100
            ? '<span class="loader-dots"><span>.</span><span>.</span><span>.</span></span>'
            : '');
    }

    function showError(message) {
        loadingState.classList.add('hidden');
        errorState.classList.remove('hidden');
        document.getElementById('errorMessage').textContent = message;
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
        form.parentElement.classList.remove('opacity-60');
    }

    function resetForm() {
        clearInterval(pollInterval);
        clearInterval(ytSubTimer);
        currentJobId   = null;
        parsedSegments = [];

        if (vjsPlayer) { try { vjsPlayer.dispose(); } catch (_) {} vjsPlayer = null; }
        if (ytPlayer && typeof ytPlayer.destroy === 'function') { ytPlayer.destroy(); ytPlayer = null; }

        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
        form.parentElement.classList.remove('opacity-60');
        document.getElementById('videoUrl').value = '';
        statusSection.classList.add('hidden');
        playerSection.classList.add('hidden');
        loadingState.classList.remove('hidden');
        errorState.classList.add('hidden');
        setProgress(10, 'Menginisialisasi');
    }

    function extractYoutubeId(url) {
        const m = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
        return m ? m[1] : null;
    }

    function guessVideoType(url) {
        if (/\.webm/i.test(url))  return 'video/webm';
        if (/\.ogg/i.test(url))   return 'video/ogg';
        if (/\.m3u8/i.test(url))  return 'application/x-mpegURL';
        return 'video/mp4';
    }

    function formatTime(s) {
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = Math.floor(s % 60);
        return h > 0
            ? `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
            : `${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    /* Parse WebVTT string → [{start, end, text}] */
    function parseVtt(vtt) {
        const result = [];
        const blocks = vtt.split(/\n\s*\n/);
        const timeRe = /(\d{1,2}):(\d{2}):(\d{2})[.,](\d{3})\s*-->\s*(\d{1,2}):(\d{2}):(\d{2})[.,](\d{3})/;
        for (const block of blocks) {
            const lines = block.trim().split('\n');
            const timeLine = lines.find(l => timeRe.test(l));
            if (!timeLine) continue;
            const m = timeLine.match(timeRe);
            const toSec = (h,min,s,ms) => +h*3600 + +min*60 + +s + +ms/1000;
            const start = toSec(m[1],m[2],m[3],m[4]);
            const end   = toSec(m[5],m[6],m[7],m[8]);
            const text  = lines.slice(lines.indexOf(timeLine)+1).join(' ').trim();
            if (text) result.push({ start, end, text });
        }
        return result;
    }
</script>

</body>
</html>
