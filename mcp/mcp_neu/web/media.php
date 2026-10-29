<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) - Geschützter Partner-Medien- & Video-Streaming-Proxy
 * Bedient die Routen:
 *  - /ProfilePicture/{id}          -> ?type=profile_picture&id=...
 *  - /AlbumPhoto/{photo_id}        -> ?type=album_photo&photo_id=...
 *  - /Player/{id}                  -> ?type=player&id=...
 *  - /PlayerPoster/{id}            -> ?type=player_poster&id=...
 *  - /amoredea/RedirectContent/... -> ?type=amoredea_redirect&content_id=...&month=...
 *
 * Vollständig kompatibel mit PHP 8.0.30 & PHP 8.4.27.
 */

if (!defined('SAFE_INC')) {
    define('SAFE_INC', 1);
}

session_cache_limiter('none');

require_once __DIR__ . '/../app/bootstrap.php';

$type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

// Authentifizierung für alle geschützten MCP-Medien prüfen
if (!function_exists('is_logged_in') || is_logged_in('mcp') !== true || empty($_SESSION['merchant_id'])) {
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    if ($type === 'amoredea_redirect') {
        $loginUrl = defined('LOGIN_URL') ? (string)LOGIN_URL : 'https://login.pay4coins.com';
        header('Location: ' . $loginUrl . '?ref=erocloud');
        exit;
    }
    http_response_code(403);
    exit;
}

$merchantId = abs((int)$_SESSION['merchant_id']);

/**
 * Bereinigt eine ID aus Rewrite-Pfaden (z. B. falls Legacy-Links &w=800 statt ?w=800 anhängen)
 * und übernimmt angehängte Parameter automatisch in $_GET.
 */
function mcp_media_extract_clean_id(string $raw): string
{
    $raw = trim($raw);
    if (str_contains($raw, '&')) {
        $parts = explode('&', $raw, 2);
        $raw = trim($parts[0]);
        if (isset($parts[1]) && $parts[1] !== '') {
            $extraParams = [];
            parse_str($parts[1], $extraParams);
            foreach ($extraParams as $k => $v) {
                if (is_string($k) && !isset($_GET[$k])) {
                    $_GET[$k] = $v;
                }
            }
        }
    }
    return $raw;
}

/**
 * Liefert das Standard-Fallback-Bild aus und beendet den Request.
 */
function mcp_media_send_fallback_and_exit(bool $privateCache = true): never
{
    $fallback = __DIR__ . '/assets/img/movie_poster_nopic.jpg';
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Cache-Control: ' . ($privateCache ? 'private, max-age=3600' : 'public, max-age=86400'));
    header('Content-Type: image/jpeg');
    if (is_file($fallback)) {
        header('Content-Length: ' . (int)filesize($fallback));
        readfile($fallback);
    }
    exit;
}

/**
 * Liest den If-Modified-Since Request-Header aus.
 */
function mcp_media_get_if_modified_since(): string
{
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        return trim((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers) && isset($headers['If-Modified-Since'])) {
            return trim((string)$headers['If-Modified-Since']);
        }
    }
    return '';
}

/**
 * Skaliert ein Bild proportional über GD (inkl. EXIF-Korrektur & optionalem Disk-Cache).
 */
function mcp_media_output_resized_image(
    string $filePath,
    int $targetWidth,
    int $maxWidth = 1280,
    string $cachePrefix = ''
): never {
    if (!is_file($filePath) || (int)@filesize($filePath) === 0) {
        mcp_media_send_fallback_and_exit();
    }

    $imageInfo = @getimagesize($filePath);
    if (!is_array($imageInfo)) {
        $mimeType = function_exists('mime_content_type') ? (string)@mime_content_type($filePath) : 'image/jpeg';
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'image/jpeg'));
        header('Content-Length: ' . (int)filesize($filePath));
        readfile($filePath);
        exit;
    }

    $mimeType = (string)($imageInfo['mime'] ?? 'image/jpeg');
    $srcWidth = (int)($imageInfo[0] ?? 0);
    $srcHeight = (int)($imageInfo[1] ?? 0);

    if ($srcWidth <= 0 || $srcHeight <= 0) {
        mcp_media_send_fallback_and_exit();
    }

    if ($targetWidth <= 0 || $targetWidth > $maxWidth) {
        $targetWidth = min(1024, $maxWidth);
    }

    if ($targetWidth >= $srcWidth) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (int)filesize($filePath));
        readfile($filePath);
        exit;
    }

    $ratio = $srcHeight / max(1, $srcWidth);
    $newWidth = max(1, $targetWidth);
    $newHeight = max(1, (int)round($newWidth * $ratio));

    $cacheFile = '';
    if ($cachePrefix !== '') {
        $cacheBase = (defined('TEMP_DIR') && is_dir((string)TEMP_DIR))
            ? rtrim((string)TEMP_DIR, '/\\')
            : sys_get_temp_dir();
        $cacheDir = $cacheBase . '/ec_poster_cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir)) {
            $cacheFile = $cacheDir . '/' . $cachePrefix . '_' . md5($filePath . '|' . (int)@filemtime($filePath) . '|' . $newWidth) . '.bin';
            if (is_file($cacheFile) && (int)@filesize($cacheFile) > 0) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . (int)filesize($cacheFile));
                readfile($cacheFile);
                exit;
            }
        }
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
                        $rot = @imagerotate($srcImage, 180, 0);
                        if ($rot instanceof \GdImage) {
                            $srcImage = $rot;
                        }
                    } elseif ($orientation === 6) {
                        $rot = @imagerotate($srcImage, -90, 0);
                        if ($rot instanceof \GdImage) {
                            $srcImage = $rot;
                            $srcWidth = imagesx($srcImage);
                            $srcHeight = imagesy($srcImage);
                            $newHeight = max(1, (int)round($newWidth * ($srcHeight / max(1, $srcWidth))));
                        }
                    } elseif ($orientation === 8) {
                        $rot = @imagerotate($srcImage, 90, 0);
                        if ($rot instanceof \GdImage) {
                            $srcImage = $rot;
                            $srcWidth = imagesx($srcImage);
                            $srcHeight = imagesy($srcImage);
                            $newHeight = max(1, (int)round($newWidth * ($srcHeight / max(1, $srcWidth))));
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
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (int)filesize($filePath));
        readfile($filePath);
        exit;
    }

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    if (!($dstImage instanceof \GdImage)) {
        mcp_media_send_fallback_and_exit();
    }

    if ($mimeType === 'image/png' || $mimeType === 'image/gif' || $mimeType === 'image/webp') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    ob_start();
    if ($mimeType === 'image/png') {
        imagepng($dstImage, null, 9);
    } elseif ($mimeType === 'image/gif') {
        imagegif($dstImage, null);
    } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($dstImage, null, 88);
    } else {
        imagejpeg($dstImage, null, 88);
    }
    $binaryData = (string)ob_get_clean();

    if ($cacheFile !== '' && $binaryData !== '') {
        @file_put_contents($cacheFile, $binaryData);
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($binaryData));
    echo $binaryData;

    unset($dstImage, $srcImage);
    exit;
}

switch ($type) {
    // =========================================================================
    // 1. Darsteller-Profilbilder (/ProfilePicture/{id})
    // =========================================================================
    case 'profile_picture':
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $rawFileId = isset($_GET['id']) ? basename(mcp_media_extract_clean_id((string)$_GET['id'])) : '';
        if ($rawFileId === '') {
            mcp_media_send_fallback_and_exit();
        }

        $fileIdEsc = p4c_escape_string($rawFileId);
        $rsActor = p4c_query(
            "SELECT `id`, `merchant_id`
             FROM `actors`
             WHERE (`profile_image_fsk16` = '" . $fileIdEsc . "' OR `profile_image_fsk18` = '" . $fileIdEsc . "')
               AND `merchant_id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            mcp_media_send_fallback_and_exit();
        }

        $actorObj = p4c_fetch_object($rsActor);
        $candidate = rtrim((string)PROFILE_IMAGE_PATH, '/\\') . '/' . trim((string)MERCHANT_DEFAULT_DIR, '/\\')
            . '/' . abs((int)$actorObj->merchant_id) . '/' . abs((int)$actorObj->id) . '/' . $rawFileId;

        if (!is_file($candidate) || (int)@filesize($candidate) === 0) {
            mcp_media_send_fallback_and_exit();
        }

        $mtime = (int)@filemtime($candidate) ?: time();
        header('Cache-Control: private, max-age=86400');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

        $ims = mcp_media_get_if_modified_since();
        if ($ims !== '' && strtotime($ims) === $mtime) {
            http_response_code(304);
            exit;
        }

        $width = isset($_GET['w']) && (int)$_GET['w'] > 0 ? abs((int)$_GET['w']) : 300;
        if ($width > 900) {
            $width = 900;
        }
        if ($width < 40) {
            $width = 40;
        }

        header('Content-Transfer-Encoding: binary');
        mcp_media_output_resized_image($candidate, $width, 900, 'mcppp');

    // =========================================================================
    // 2. Fotoalbum-Einzelfotos (/AlbumPhoto/{photo_id})
    // =========================================================================
    case 'album_photo':
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $photoId = isset($_GET['photo_id']) ? mcp_media_extract_clean_id((string)$_GET['photo_id']) : '';
        if ($photoId === '') {
            mcp_media_send_fallback_and_exit();
        }

        $rsPhoto = p4c_query(
            "SELECT `pa`.`id` AS `album_db_id`,
                    `pa`.`storage_location`,
                    `pap`.`merchant_id`,
                    `pap`.`filename`
             FROM `photo_albums_photos` AS `pap`
             INNER JOIN `photo_albums` AS `pa` ON `pap`.`album_id` = `pa`.`album_id`
             WHERE `pap`.`file_id` = '" . p4c_escape_string($photoId) . "'
               AND `pap`.`merchant_id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsPhoto || p4c_num_rows($rsPhoto) === 0) {
            mcp_media_send_fallback_and_exit();
        }

        $photoObj = p4c_fetch_object($rsPhoto);
        $filePath = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . trim((string)$photoObj->storage_location, '/\\')
            . '/' . abs((int)$photoObj->merchant_id) . '/' . abs((int)$photoObj->album_db_id)
            . '/images/' . basename((string)$photoObj->filename);

        if (!is_file($filePath) || (int)@filesize($filePath) === 0) {
            mcp_media_send_fallback_and_exit();
        }

        $mtime = (int)@filemtime($filePath) ?: time();
        header('Cache-Control: private, max-age=31536000');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

        $ims = mcp_media_get_if_modified_since();
        if ($ims !== '' && strtotime($ims) === $mtime) {
            http_response_code(304);
            exit;
        }

        $width = isset($_GET['w']) && (int)$_GET['w'] > 0 ? abs((int)$_GET['w']) : 1024;
        if ($width > 1280) {
            $width = 1280;
        }
        if ($width < 50) {
            $width = 50;
        }

        header('Content-Transfer-Encoding: binary');
        mcp_media_output_resized_image($filePath, $width, 1280, '');

    // =========================================================================
    // 3. Video-Streaming-Player (/Player/{id})
    // =========================================================================
    case 'player':
        require_once SOURCEDIR . '/includes/klassen/VideoStream.inc.php';

        $fileIdRaw = isset($_GET['id']) ? mcp_media_extract_clean_id((string)$_GET['id']) : '';
        if ($fileIdRaw === '' || $merchantId <= 0) {
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            http_response_code(404);
            exit;
        }

        // Fast-Path über Session-Stream-Cache (0 DB-Queries beim Spulen im Video-Player)
        $cacheKey = 'mcp_' . $merchantId . '_' . $fileIdRaw;
        if (
            isset($_SESSION['erocloud_stream_cache'][$cacheKey])
            && is_array($_SESSION['erocloud_stream_cache'][$cacheKey])
        ) {
            $cachedEntry = $_SESSION['erocloud_stream_cache'][$cacheKey];
            $cachedAt = isset($cachedEntry['ts']) ? (int)$cachedEntry['ts'] : 0;
            $cachedFile = isset($cachedEntry['file']) ? (string)$cachedEntry['file'] : '';

            if (($cachedAt + 1800) >= time() && $cachedFile !== '' && is_file($cachedFile)) {
                if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                    @session_write_close();
                }
                p4c_close(DB_HOST);

                $stream = new \VideoStream($cachedFile, 'video/mp4');
                $stream->start();
                exit;
            }
        }

        $rsMovie = p4c_query(
            "SELECT `id`, `merchant_id`, `storage_location`, `filename`
             FROM `movies`
             WHERE `file_id` = '" . p4c_escape_string($fileIdRaw) . "'
               AND `merchant_id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            http_response_code(404);
            exit;
        }

        $movieObj = p4c_fetch_object($rsMovie);
        $videoFile = rtrim((string)MOVIES_PATH, '/\\') . '/' . trim((string)$movieObj->storage_location, '/\\')
            . '/' . abs((int)$movieObj->merchant_id) . '/' . abs((int)$movieObj->id) . '/' . basename((string)$movieObj->filename);

        if (!is_file($videoFile)) {
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            http_response_code(404);
            exit;
        }

        if (!isset($_SESSION['erocloud_stream_cache']) || !is_array($_SESSION['erocloud_stream_cache'])) {
            $_SESSION['erocloud_stream_cache'] = [];
        }
        if (count($_SESSION['erocloud_stream_cache']) >= 20) {
            array_shift($_SESSION['erocloud_stream_cache']);
        }
        $_SESSION['erocloud_stream_cache'][$cacheKey] = [
            'file' => $videoFile,
            'ts'   => time(),
        ];

        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        p4c_close(DB_HOST);

        $stream = new \VideoStream($videoFile, 'video/mp4');
        $stream->start();
        exit;

    // =========================================================================
    // 4. Video-Player-Poster mit Qualitäts-Badge (/PlayerPoster/{id})
    // =========================================================================
    case 'player_poster':
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $fileIdRaw = isset($_GET['id']) ? mcp_media_extract_clean_id((string)$_GET['id']) : '';
        if ($fileIdRaw === '') {
            mcp_media_send_fallback_and_exit();
        }

        $rsMovie = p4c_query(
            "SELECT `id`, `file_id`, `merchant_id`, `storage_location`, `preview_image_fsk18`, `quality`, `resolution`
             FROM `movies`
             WHERE `file_id` = '" . p4c_escape_string($fileIdRaw) . "'
               AND `merchant_id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            mcp_media_send_fallback_and_exit();
        }

        $movieObj = p4c_fetch_object($rsMovie);
        $movieDbId = abs((int)$movieObj->id);
        $fsk18Idx = abs((int)($movieObj->preview_image_fsk18 ?? 1));
        if ($fsk18Idx <= 0) {
            $fsk18Idx = 1;
        }

        $baseFolder = rtrim((string)MOVIES_PATH, '/\\') . '/' . trim((string)$movieObj->storage_location, '/\\')
            . '/' . abs((int)$movieObj->merchant_id) . '/' . $movieDbId . '/';

        $posterPath = $baseFolder . 'thumb_' . $movieDbId . '_' . (string)$movieObj->file_id . '_' . $fsk18Idx . '.jpg';
        if (!is_file($posterPath)) {
            $posterPath = $baseFolder . 'thumb_' . $movieDbId . '_' . (string)$movieObj->file_id . '_1.jpg';
        }

        if (!is_file($posterPath) || (int)@filesize($posterPath) === 0) {
            mcp_media_send_fallback_and_exit();
        }

        $imgInfo = @getimagesize($posterPath);
        if (!is_array($imgInfo)) {
            mcp_media_send_fallback_and_exit();
        }

        $mimeType = (string)($imgInfo['mime'] ?? 'image/jpeg');
        $srcWidth = (int)($imgInfo[0] ?? 0);
        $srcHeight = (int)($imgInfo[1] ?? 0);
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            mcp_media_send_fallback_and_exit();
        }

        $reqWidth = isset($_GET['w']) && (int)$_GET['w'] > 0 ? abs((int)$_GET['w']) : 800;
        if ($reqWidth > 1280) {
            $reqWidth = 1280;
        }
        if ($reqWidth < 50) {
            $reqWidth = 50;
        }
        $targetWidth = min($srcWidth, $reqWidth);
        $targetHeight = max(1, (int)round($targetWidth * ($srcHeight / max(1, $srcWidth))));

        // Qualitäts-Badge ermitteln (inkl. 9:16 Hochformat)
        $logoFile = 'sd.png';
        $quality = strtolower(trim((string)($movieObj->quality ?? '')));
        $resolution = trim((string)($movieObj->resolution ?? ''));

        if ($quality === 'hd') {
            $logoFile = 'hd.png';
        } elseif ($quality === 'fhd') {
            $logoFile = 'fullhd.png';
        } elseif ($quality === '2k') {
            $logoFile = '2k.png';
        } elseif ($quality === '4k') {
            $logoFile = '4k.png';
        } elseif ($quality === '6k') {
            $logoFile = '6k.png';
        } elseif ($resolution !== '') {
            $resParts = explode('x', strtolower($resolution));
            $resW = isset($resParts[0]) ? abs((int)$resParts[0]) : 0;
            $resH = isset($resParts[1]) ? abs((int)$resParts[1]) : 0;
            $maxDim = max($resW, $resH);
            $minDim = min($resW, $resH);

            if ($maxDim >= 5500 || $minDim >= 2800) {
                $logoFile = '6k.png';
            } elseif ($maxDim >= 3200 || $minDim >= 1800) {
                $logoFile = '4k.png';
            } elseif ($maxDim >= 2000 || $minDim >= 1300) {
                $logoFile = '2k.png';
            } elseif ($maxDim >= 1600 || $minDim >= 900) {
                $logoFile = 'fullhd.png';
            } elseif ($maxDim >= 1000 || $minDim >= 650) {
                $logoFile = 'hd.png';
            }
        }

        if ($mimeType === 'image/gif' && (!isset($_GET['stop']) || $_GET['stop'] !== 'gif')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('Content-Type: image/gif');
            header('Content-Length: ' . (int)filesize($posterPath));
            readfile($posterPath);
            exit;
        }

        $srcImg = null;
        if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
            $srcImg = @imagecreatefromjpeg($posterPath);
        } elseif ($mimeType === 'image/png') {
            $srcImg = @imagecreatefrompng($posterPath);
        } elseif ($mimeType === 'image/gif') {
            $srcImg = @imagecreatefromgif($posterPath);
        }

        if (!($srcImg instanceof \GdImage)) {
            mcp_media_send_fallback_and_exit();
        }

        $wmPath = __DIR__ . '/assets/img/movie_quality_buttons/' . $logoFile;
        if (($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') && is_file($wmPath)) {
            $wmImg = @imagecreatefrompng($wmPath);
            $wmInfo = @getimagesize($wmPath);
            if ($wmImg instanceof \GdImage && is_array($wmInfo) && (int)$wmInfo[0] > 0 && (int)$wmInfo[1] > 0) {
                $wmSize = max(16, (int)round($srcWidth * 0.15));
                $posX = max(0, $srcWidth - $wmSize - 10);
                $posY = 10;
                imagealphablending($srcImg, true);
                imagecopyresampled($srcImg, $wmImg, $posX, $posY, 0, 0, $wmSize, $wmSize, (int)$wmInfo[0], (int)$wmInfo[1]);
                unset($wmImg);
            }
        }

        $dstImg = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!($dstImg instanceof \GdImage)) {
            mcp_media_send_fallback_and_exit();
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
        }

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Cache-Control: private, max-age=3600');
        header('Content-Type: ' . $mimeType);
        ob_start();
        if ($mimeType === 'image/png') {
            imagepng($dstImg, null, 9);
        } elseif ($mimeType === 'image/gif') {
            imagegif($dstImg, null);
        } else {
            imagejpeg($dstImg, null, 92);
        }
        $binary = (string)ob_get_clean();

        header('Content-Length: ' . strlen($binary));
        echo $binary;

        unset($dstImg, $srcImg);
        exit;

    // =========================================================================
    // 5. Legacy Amoredea Timeline-Kommentar-Weiterleitung (/amoredea/RedirectContent/...)
    // =========================================================================
    case 'amoredea_redirect':
        global $mysql;

        $contentId = isset($_GET['content_id']) ? abs((int)$_GET['content_id']) : 0;
        $month = isset($_GET['month']) ? abs((int)$_GET['month']) : 0;
        $fallbackTarget = (defined('MCP_URL') ? rtrim((string)MCP_URL, '/') : 'https://erocloud.net') . '/Startseite';

        if ($contentId <= 0 || $month <= 0 || !is_object($mysql) || !method_exists($mysql, 'change_connect')) {
            header('Location: ' . $fallbackTarget);
            exit;
        }

        $mysql->change_connect('amoredea');
        $rsComments = p4c_query(
            "SELECT `actor_id`
             FROM `timeline_comments_" . $month . "`
             WHERE `content_id` = '" . $contentId . "'
               AND `merchant_id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        $actorId = 0;
        if ($rsComments && p4c_num_rows($rsComments) > 0) {
            $commentObj = p4c_fetch_object($rsComments);
            $actorId = abs((int)($commentObj->actor_id ?? 0));
        }

        $mysql->change_connect('');

        if ($actorId <= 0) {
            header('Location: ' . $fallbackTarget);
            exit;
        }

        $actor = new \Actor($actorId);
        $actorUsername = trim((string)$actor->get('username'));
        if ((string)$actor->get('id') === '' || $actorUsername === '') {
            header('Location: ' . $fallbackTarget);
            exit;
        }

        $amoredeaUrl = defined('amoredea_URL') ? rtrim((string)amoredea_URL, '/') : 'https://amoredea.com';
        header('Location: ' . $amoredeaUrl . '/' . rawurlencode($actorUsername) . '/?content_id=' . $contentId . '_' . $month);
        exit;

    default:
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        http_response_code(400);
        exit;
}
