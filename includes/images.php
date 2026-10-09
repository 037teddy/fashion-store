<?php
declare(strict_types=1);

function upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE                        => 'No file was selected.',
        default                                   => 'The upload failed.',
    };
}

/**
 * Validates an uploaded photo, re-encodes it as a JPEG (max 1600px, white background for transparency)
 * and stores it under uploads/products/{id}/. Returns the path relative to /uploads.
 */
function save_uploaded_image(array $file, int $productId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text((int) $file['error']));
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Each photo must be under ' . (MAX_IMAGE_BYTES / 1048576) . ' MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Only JPG, PNG or WebP photos are allowed.');
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] * $info[1] > 40_000_000) {
        throw new RuntimeException('That image is unreadable or too large.');
    }

    $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($src === false) {
        throw new RuntimeException('That image could not be processed.');
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };
        if ($rotated !== false) {
            $src = $rotated;
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, 1600 / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $relDir = "products/{$productId}";
    $absDir = UPLOAD_DIR . '/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
        throw new RuntimeException('Could not create the upload folder.');
    }
    $name = bin2hex(random_bytes(8)) . '.jpg';
    if (!imagejpeg($dst, "{$absDir}/{$name}", 85)) {
        throw new RuntimeException('Could not save the photo.');
    }
    return "{$relDir}/{$name}";
}

/** Deletes a file inside /uploads. Ignores paths that try to leave the folder. */
function delete_upload(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '' || str_contains($relativePath, '..')) {
        return;
    }
    $abs = UPLOAD_DIR . '/' . ltrim($relativePath, '/');
    if (is_file($abs)) {
        @unlink($abs);
    }
}
