<?php
// Command-line test for the slideshow generator, no database needed.
//   C:\xampp\php\php.exe tools\test_slideshow.php photo1.jpg photo2.jpg photo3.jpg
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/video_generator.php';

$images = array_slice($argv, 1);
if (count($images) < VIDEO_MIN_PHOTOS) {
    fwrite(STDERR, "Usage: php tools/test_slideshow.php photo1.jpg photo2.jpg [...]\n");
    exit(1);
}

$out = BASE_PATH . '/uploads/videos/test-slideshow.mp4';
$poster = BASE_PATH . '/uploads/videos/test-slideshow.jpg';
$t = microtime(true);
$r = generate_slideshow($images, $out, $poster);
$secs = round(microtime(true) - $t, 1);

if (!$r['ok']) {
    fwrite(STDERR, "FAILED: {$r['error']}\n");
    exit(2);
}
echo "OK in {$secs}s\n";
echo "Video:    {$out}\n";
echo "Duration: {$r['duration']}s (cap " . VIDEO_MAX_SECONDS . "s)\n";
echo "Size:     " . round(filesize($out) / 1048576, 2) . " MB\n";
echo "Poster:   " . ($r['poster'] ? $poster : 'not created') . "\n";
