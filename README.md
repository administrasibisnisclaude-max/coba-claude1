# AI Video Subtitle Generator

Website Laravel yang secara otomatis menghasilkan subtitle untuk video menggunakan OpenAI Whisper AI.

## Fitur

- Paste URL video YouTube atau URL video langsung (mp4, webm, dll)
- Generate subtitle otomatis dengan OpenAI Whisper API
- Video player dengan subtitle overlay (format WebVTT)
- Download subtitle dalam format SRT atau VTT
- Queue-based background processing
- Loading/progress state yang responsif
- Tampilan modern dengan TailwindCSS

## Teknologi

- **Backend**: Laravel 11, PHP 8.2+
- **AI**: OpenAI Whisper API (transcription dengan timestamps)
- **Video download**: yt-dlp (YouTube), ffmpeg (direct URLs)
- **Frontend**: Blade templates, TailwindCSS CDN, Video.js
- **Queue**: Laravel Database Queue

## Instalasi

### 1. Prasyarat

```bash
# Install yt-dlp
pip install yt-dlp

# Install ffmpeg (untuk direct video URLs)
apt-get install ffmpeg   # Ubuntu/Debian
brew install ffmpeg      # macOS
```

### 2. Clone & Install Dependencies

```bash
git clone <repo-url>
cd coba-claude1
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Konfigurasi .env

```env
# Wajib: OpenAI API Key
OPENAI_API_KEY=sk-your-openai-api-key-here

# Queue harus database
QUEUE_CONNECTION=database

# Database (SQLite default, atau gunakan MySQL/PostgreSQL)
DB_CONNECTION=sqlite
```

### 4. Migrasi Database & Jalankan

```bash
php artisan migrate
php artisan serve
```

### 5. Jalankan Queue Worker (terminal terpisah)

```bash
php artisan queue:work --timeout=600
```

## Cara Penggunaan

1. Buka `http://localhost:8000`
2. Paste URL video (YouTube atau URL langsung)
3. Pilih bahasa (opsional, default: auto-detect)
4. Klik **Generate Subtitle**
5. Tunggu proses (bisa 1-5 menit tergantung durasi video)
6. Video player dengan subtitle akan muncul otomatis
7. Download subtitle dalam format SRT atau VTT

## Catatan

- File audio maksimal 25MB (batasan OpenAI Whisper API)
- Video YouTube memerlukan yt-dlp
- Direct video URL memerlukan ffmpeg untuk ekstraksi audio
- OPENAI_API_KEY harus valid dan memiliki akses ke Whisper API

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
