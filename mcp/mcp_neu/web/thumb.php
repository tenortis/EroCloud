<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) - Szenen-Thumbnail-Endpunkt (/thumb.php)
 * Liefert Vorschaubilder (1..12) eines Films skaliert und gecacht über GD aus.
 * Vollständig kompatibel mit PHP 8.0.30 & PHP 8.4.27.
 */

if (!defined('SAFE_INC')) {
    define('SAFE_INC', 1);
}

require_once __DIR__ . '/../app/bootstrap.php';

if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

header('Access-Control-Allow-Origin: *');

/**
 * Liefert die Standard-Fallback-Grafik aus und beendet das Skript.
 */
function mcp_thumb_send_fallback_and_exit(): never
{
    $fallback = __DIR__ . '/assets/img/movie_poster_nopic.jpg';
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Pragma: cache');
    header('Cache-Control: max-age=31536000, public');
    header('Expires: ' . gmdate(DATE_RFC1123, time() + 31536000));
    header('Content-Type: image/jpeg');
    if (is_file($fallback)) {
        header('Content-Length: ' . (int)filesize($fallback));
        readfile($fallback);
    }
    exit;
}

/**
 * Gibt eine Bilddatei unverändert als Binär-Stream aus.
 */
function mcp_thumb_output_raw_and_exit(string $filePath, string $mimeType): never
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (int)filesize($filePath));
    readfile($filePath);
    exit;
}

/**
 * Skaliert ein Vorschaubild proportional über GD und gibt es als Binär-Stream aus.
 */
function mcp_thumb_render_resized_and_exit(string $filePath, int $targetWidth = 0): never
{
    if (!is_file($filePath) || (int)@filesize($filePath) === 0) {
        mcp_thumb_send_fallback_and_exit();
    }

    $imageInfo = @getimagesize($filePath);
    if (!is_array($imageInfo)) {
        $mimeType = function_exists('mime_content_type') ? (string)@mime_content_type($filePath) : '';
        if ($mimeType === '') {
            $mimeType = 'image/jpeg';
        }
        mcp_thumb_output_raw_and_exit($filePath, $mimeType);
    }

    $mimeType = (string)($imageInfo['mime'] ?? 'image/jpeg');
    $srcWidth = (int)($imageInfo[0] ?? 0);
    $srcHeight = (int)($imageInfo[1] ?? 0);

    if ($srcWidth <= 0 || $srcHeight <= 0) {
        mcp_thumb_send_fallback_and_exit();
    }

    if ($targetWidth <= 0 || $targetWidth > 1280) {
        $targetWidth = 1024;
    }

    if ($targetWidth >= $srcWidth) {
        mcp_thumb_output_raw_and_exit($filePath, $mimeType);
    }

    $srcImage = null;
    if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
        $rawBytes = @file_get_contents($filePath);
        if (is_string($rawBytes) && $rawBytes !== '') {
            $srcImage = @imagecreatefromstring($rawBytes);
            if ($srcImage instanceof \GdImage && function_exists('exif_read_data')) {
                $exif = @exif_read_data($filePath);
                if (is_array($exif) && !empty($exif['Orientation'])) {
                    $orientation = (int)$exif['Orientation'];
                    if ($orientation === 3) {
                        $rotated = @imagerotate($srcImage, 180, 0);
                        if ($rotated instanceof \GdImage) {
                            $srcImage = $rotated;
                        }
                    } elseif ($orientation === 6) {
                        $rotated = @imagerotate($srcImage, -90, 0);
                        if ($rotated instanceof \GdImage) {
                            $srcImage = $rotated;
                            $srcWidth = imagesx($srcImage);
                            $srcHeight = imagesy($srcImage);
                        }
                    } elseif ($orientation === 8) {
                        $rotated = @imagerotate($srcImage, 90, 0);
                        if ($rotated instanceof \GdImage) {
                            $srcImage = $rotated;
                            $srcWidth = imagesx($srcImage);
                            $srcHeight = imagesy($srcImage);
                        }
                    }
                }
            }
        }
    } elseif ($mimeType === 'image/png') {
        $srcImage = @imagecreatefrompng($filePath);
    } elseif ($mimeType === 'image/gif') {
        $srcImage = @imagecreatefromgif($filePath);
    } elseif ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $srcImage = @imagecreatefromwebp($filePath);
    }

    if (!($srcImage instanceof \GdImage)) {
        mcp_thumb_output_raw_and_exit($filePath, $mimeType);
    }

    $ratio = $srcHeight / max(1, $srcWidth);
    $newWidth = max(1, $targetWidth);
    $newHeight = max(1, (int)round($newWidth * $ratio));

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    if (!($dstImage instanceof \GdImage)) {
        mcp_thumb_output_raw_and_exit($filePath, $mimeType);
    }

    if ($mimeType === 'image/png' || $mimeType === 'image/gif' || $mimeType === 'image/webp') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    ob_start();
    if ($mimeType === 'image/png') {
        imagepng($dstImage, null, 9);
    } elseif ($mimeType === 'image/gif') {
        imagegif($dstImage, null);
    } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($dstImage, null, 88);
    } else {
        imagejpeg($dstImage, null, 90);
    }
    $binaryData = (string)ob_get_clean();

    header('Content-Length: ' . strlen($binaryData));
    echo $binaryData;

    unset($dstImage, $srcImage);
    exit;
}

$rawMovieId = isset($_GET['movie_id']) ? trim((string)$_GET['movie_id']) : '';
if ($rawMovieId === '') {
    mcp_thumb_send_fallback_and_exit();
}

$movieIdEsc = p4c_escape_string($rawMovieId);
$thumbNum = isset($_GET['thumb_number']) ? abs((int)$_GET['thumb_number']) : 1;
if ($thumbNum <= 0) {
    $thumbNum = 1;
}

$rsMovie = p4c_query(
    "SELECT `id`, `file_id`, `merchant_id`, `filename`, `storage_location`
     FROM `movies`
     WHERE `file_id` = '" . $movieIdEsc . "'
     LIMIT 1;",
    __FILE__,
    __LINE__
);

if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
    mcp_thumb_send_fallback_and_exit();
}

$movieObj = p4c_fetch_object($rsMovie);
$movieDbId = abs((int)($movieObj->id ?? 0));
$merchantId = abs((int)($movieObj->merchant_id ?? 0));
$fileId = (string)($movieObj->file_id ?? '');
$filename = (string)($movieObj->filename ?? '');
$storageLoc = trim((string)($movieObj->storage_location ?? ''), '/\\');
$baseNameNoExt = strlen($filename) > 4 ? substr($filename, 0, -4) : $filename;

$folderPath = rtrim((string)MOVIES_PATH, '/\\') . '/' . $storageLoc . '/' . $merchantId . '/' . $movieDbId . '/';

$patterns = [
    $folderPath . 'thumb_' . $movieDbId . '_' . $fileId . '_' . $thumbNum,
    $folderPath . 'thumb_' . $baseNameNoExt . '_' . $thumbNum,
    $folderPath . 'thumb_' . $movieDbId . '_' . $fileId . '_1',
    $folderPath . 'thumb_' . $baseNameNoExt . '_1',
];

$targetFile = '';
foreach ($patterns as $pattern) {
    foreach (['.jpg', '.jpeg', '.png', '.gif', '.webp'] as $ext) {
        if (is_file($pattern . $ext) && (int)@filesize($pattern . $ext) > 0) {
            $targetFile = $pattern . $ext;
            break 2;
        }
    }
}

if ($targetFile === '') {
    mcp_thumb_send_fallback_and_exit();
}

$mtime = (int)@filemtime($targetFile);
if ($mtime <= 0) {
    $mtime = time();
}

$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
if ($ifModifiedSince === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    if (is_array($reqHeaders) && isset($reqHeaders['If-Modified-Since'])) {
        $ifModifiedSince = trim((string)$reqHeaders['If-Modified-Since']);
    }
}

$mimeType = function_exists('mime_content_type') ? (string)@mime_content_type($targetFile) : '';
if ($mimeType === '') {
    $mimeType = 'image/jpeg';
}

header('Pragma: cache');
header('Cache-Control: max-age=31536000, public');
header('Expires: ' . gmdate(DATE_RFC1123, time() + 31536000));
header('Content-Type: ' . $mimeType);

if ($ifModifiedSince !== '' && strtotime($ifModifiedSince) === $mtime) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT', true, 304);
    exit;
}

$width = isset($_GET['w']) ? abs((int)$_GET['w']) : 0;
if ($width > 1280) {
    $width = 1280;
}
if ($width > 0 && $width < 50) {
    $width = 50;
}

header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT', true, 200);
header('Content-Transfer-Encoding: binary');

mcp_thumb_render_resized_and_exit($targetFile, $width);
