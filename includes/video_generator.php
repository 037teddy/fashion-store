<?php
declare(strict_types=1);

/**
 * Builds a square slideshow MP4 from product photos using FFmpeg.
 *  - each photo is fitted inside the square over a blurred copy of itself (no stretching)
 *  - photos crossfade into each other
 *  - total length = photos * slide + fade, always kept under VIDEO_MAX_SECONDS
 *  - no audio in version 1
 */

function fmt_num(float $n): string
{
    return number_format($n, 2, '.', '');   // always a dot decimal, whatever the server locale
}

/** Seconds each photo is shown, reduced when there are many photos so the total stays under the cap. */
function slideshow_slide_seconds(int $photoCount): float
{
    $usable = VIDEO_MAX_SECONDS - 0.2 - VIDEO_FADE_SECONDS;      // total = n * slide + fade
    return floor(min(VIDEO_SLIDE_SECONDS, $usable / $photoCount) * 100) / 100;
}

function slideshow_estimated_seconds(int $photoCount): float
{
    $photoCount = max(0, min($photoCount, VIDEO_MAX_PHOTOS));
    return $photoCount < 1 ? 0.0 : $photoCount * slideshow_slide_seconds($photoCount) + VIDEO_FADE_SECONDS;
}

function slideshow_filter_graph(int $n, float $slide): string
{
    $s = VIDEO_SIZE;
    $fade = fmt_num(VIDEO_FADE_SECONDS);
    $parts = [];

    for ($i = 0; $i < $n; $i++) {
        $parts[] = "[{$i}:v]split=2[bg{$i}][fg{$i}]";
        $parts[] = "[bg{$i}]scale=270:270:force_original_aspect_ratio=increase,crop=270:270,boxblur=10:2,scale={$s}:{$s}[bb{$i}]";
        $parts[] = "[fg{$i}]scale={$s}:{$s}:force_original_aspect_ratio=decrease[ff{$i}]";
        $parts[] = "[bb{$i}][ff{$i}]overlay=(W-w)/2:(H-h)/2,setsar=1,format=yuv420p[v{$i}]";
    }

    $prev = 'v0';
    for ($i = 1; $i < $n; $i++) {
        $offset = fmt_num($i * $slide);
        $out = ($i === $n - 1) ? 'vout' : "x{$i}";
        $parts[] = "[{$prev}][v{$i}]xfade=transition=fade:duration={$fade}:offset={$offset}[{$out}]";
        $prev = $out;
    }
    return implode(';', $parts);
}

/** Runs a command (argument array, no shell). stderr goes to a temp file; the process is killed on timeout. */
function run_process(array $cmd, int $timeoutSeconds, ?string &$stderr = null): int
{
    $logFile = tempnam(sys_get_temp_dir(), 'ffl');
    $devNull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $spec = [0 => ['pipe', 'r'], 1 => ['file', $devNull, 'w'], 2 => ['file', $logFile, 'w']];

    $proc = @proc_open($cmd, $spec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        $stderr = 'Could not start the process. Check FFMPEG_PATH and that proc_open is not disabled in php.ini.';
        @unlink($logFile);
        return -1;
    }
    fclose($pipes[0]);

    $start = time();
    $exit = -1;
    while (true) {
        $st = proc_get_status($proc);
        if (!$st['running']) {
            $exit = (int) $st['exitcode'];
            break;
        }
        if (time() - $start > $timeoutSeconds) {
            proc_terminate($proc);
            $exit = -1;
            file_put_contents($logFile, "Timed out after {$timeoutSeconds}s.\n", FILE_APPEND);
            break;
        }
        usleep(200000);
    }
    proc_close($proc);

    $stderr = (string) @file_get_contents($logFile);
    @unlink($logFile);
    return $exit;
}

/** Duration in seconds from ffprobe, or null if the file cannot be read as video. */
function probe_duration(string $file): ?float
{
    $cmd = [FFPROBE_PATH, '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $file];
    $devNull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $devNull, 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        return null;
    }
    fclose($pipes[0]);
    $out = trim((string) stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    proc_close($proc);
    return is_numeric($out) ? (float) $out : null;
}

function ffmpeg_available(): bool
{
    $hasDir = str_contains(FFMPEG_PATH, '/') || str_contains(FFMPEG_PATH, '\\');
    return !$hasDir || is_file(FFMPEG_PATH);
}

/**
 * @param string[] $imagePaths absolute paths of the photos, in display order
 * @return array{ok:bool, duration?:float, poster?:bool, error?:string}
 */
function generate_slideshow(array $imagePaths, string $outMp4, string $outPoster): array
{
    @set_time_limit(VIDEO_TIMEOUT_SECONDS + 30);

    $imagePaths = array_values(array_slice($imagePaths, 0, VIDEO_MAX_PHOTOS));
    $n = count($imagePaths);
    if ($n < VIDEO_MIN_PHOTOS) {
        return ['ok' => false, 'error' => 'Add at least ' . VIDEO_MIN_PHOTOS . ' photos first.'];
    }
    if (!ffmpeg_available()) {
        return ['ok' => false, 'error' => 'FFmpeg was not found. Check FFMPEG_PATH in config/config.php.'];
    }
    foreach ($imagePaths as $p) {
        if (!is_readable($p)) {
            return ['ok' => false, 'error' => 'A product photo is missing on disk. Re-upload it and try again.'];
        }
    }

    $dir = dirname($outMp4);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create the video folder.'];
    }

    $slide = slideshow_slide_seconds($n);
    $clipLen = fmt_num($slide + VIDEO_FADE_SECONDS);
    $tmp = preg_replace('/\.mp4$/', '.tmp.mp4', $outMp4);

    $cmd = [FFMPEG_PATH, '-hide_banner', '-nostdin', '-y', '-loglevel', 'error'];
    foreach ($imagePaths as $p) {
        array_push($cmd, '-loop', '1', '-framerate', '25', '-t', $clipLen, '-i', $p);
    }
    array_push(
        $cmd,
        '-filter_complex', slideshow_filter_graph($n, $slide),
        '-map', '[vout]',
        '-c:v', 'libx264', '-preset', 'medium', '-crf', '26',
        '-maxrate', '3M', '-bufsize', '6M',
        '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', '25',
        '-movflags', '+faststart', '-an',
        '-t', (string) VIDEO_MAX_SECONDS,
        $tmp
    );

    $log = '';
    $code = run_process($cmd, VIDEO_TIMEOUT_SECONDS, $log);
    if ($code !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        error_log('[slideshow] ffmpeg failed (' . $code . '): ' . $log);
        return ['ok' => false, 'error' => 'FFmpeg could not build the video. ' . substr(trim($log), -300)];
    }

    $duration = probe_duration($tmp);
    if ($duration === null || $duration > VIDEO_MAX_SECONDS + 0.05) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'The generated video failed the length check.'];
    }
    if (!rename($tmp, $outMp4)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Could not save the generated video.'];
    }

    // Poster = first frame region of the finished video (the first photo, fully visible).
    $posterLog = '';
    $pc = run_process([
        FFMPEG_PATH, '-hide_banner', '-nostdin', '-y', '-loglevel', 'error',
        '-ss', '0.4', '-i', $outMp4, '-frames:v', '1', '-q:v', '3', $outPoster,
    ], 30, $posterLog);
    $posterOk = $pc === 0 && is_file($outPoster);

    return ['ok' => true, 'duration' => round($duration, 2), 'poster' => $posterOk];
}
