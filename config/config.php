<?php
declare(strict_types=1);

// ---- Paths and URLs -------------------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/fashion-store');            // folder name under C:\xampp\htdocs
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// ---- Database (XAMPP defaults) ---------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'fashion_store');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- FFmpeg ---------------------------------------------------------------
// Forward slashes work on Windows. Environment variables override (used for testing).
define('FFMPEG_PATH',  getenv('FFMPEG_PATH')  ?: 'C:/ffmpeg/bin/ffmpeg.exe');
define('FFPROBE_PATH', getenv('FFPROBE_PATH') ?: 'C:/ffmpeg/bin/ffprobe.exe');

// ---- Slideshow video ------------------------------------------------------
define('VIDEO_SIZE', 1080);             // square 1:1
define('VIDEO_MAX_SECONDS', 20);        // hard cap, also verified with ffprobe
define('VIDEO_SLIDE_SECONDS', 3.0);     // time each photo is shown (reduced automatically for many photos)
define('VIDEO_FADE_SECONDS', 0.5);      // crossfade between photos
define('VIDEO_MIN_PHOTOS', 2);
define('VIDEO_MAX_PHOTOS', 8);
define('VIDEO_TIMEOUT_SECONDS', 120);   // kill FFmpeg if it runs longer than this

// ---- Image uploads --------------------------------------------------------
define('MAX_IMAGE_BYTES', 5 * 1024 * 1024);
define('MAX_IMAGES_PER_PRODUCT', 12);
