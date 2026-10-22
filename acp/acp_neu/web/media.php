<?php
declare(strict_types=1);

/**
 * EroCloud ACP (v2.0) - Geschützter Medien-, Video-Stream-, VTT- & Thumbnail-Proxy
 * Ersetzt acp/includes/movie_player.php, acp/includes/movie_poster.php und Cross-Domain-Aufrufe.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!function_exists('is_logged_in') || is_logged_in('acp') !== true || empty($_SESSION['employee_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Zugriff verweigert.';
    exit;
}

$type    = isset($_GET['type']) ? trim((string)$_GET['type']) : 'thumb';
$fileId  = isset($_GET['file_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['file_id']) : '';
$movieId = isset($_GET['movie_id']) ? abs((int)$_GET['movie_id']) : 0;

/**
 * Hilfsfunktion: Lädt Film-Pfad-Metadaten (mit Session-Cache für schnelle Range-Requests & Thumbnail-Grids)
 *
 * @return array<string, mixed>|null
 */
function acp_media_resolve_movie(string $fileId = '', int $movieId = 0): ?array
{
    $cacheKey = $fileId !== '' ? 'fid_' . $fileId : 'mid_' . $movieId;
    if (isset($_SESSION['erocloud_acp_media_cache'][$cacheKey]) && is_array($_SESSION['erocloud_acp_media_cache'][$cacheKey])) {
        return $_SESSION['erocloud_acp_media_cache'][$cacheKey];
    }

    if ($fileId !== '') {
        $where = "`file_id` = '" . p4c_escape_string($fileId) . "'";
    } elseif ($movieId > 0) {
        $where = "`id` = '" . abs($movieId) . "'";
    } else {
        return null;
    }

    $rs = p4c_query(
        "SELECT `id`, `merchant_id`, `file_id`, `filename`, `storage_location`,
                `preview_image_fsk16`, `preview_image_fsk18`, `title`, `resolution`
         FROM `movies`
         WHERE {$where}
         LIMIT 1;",
        __FILE__,
        __LINE__
    );

    if (!$rs || p4c_num_rows($rs) === 0) {
        return null;
    }

    $m = p4c_fetch_object($rs);
    $info = [
        'id'                  => abs((int)$m->id),
        'merchant_id'         => abs((int)$m->merchant_id),
        'file_id'             => (string)$m->file_id,
        'filename'            => (string)$m->filename,
        'storage_location'    => (string)$m->storage_location,
        'preview_image_fsk16' => abs((int)($m->preview_image_fsk16 ?: 1)),
        'preview_image_fsk18' => abs((int)($m->preview_image_fsk18 ?: 1)),
        'title'               => (string)($m->title ?? ''),
        'resolution'          => (string)($m->resolution ?? ''),
    ];

    if (!isset($_SESSION['erocloud_acp_media_cache']) || !is_array($_SESSION['erocloud_acp_media_cache'])) {
        $_SESSION['erocloud_acp_media_cache'] = [];
    }
    if (count($_SESSION['erocloud_acp_media_cache']) > 60) {
        $_SESSION['erocloud_acp_media_cache'] = array_slice($_SESSION['erocloud_acp_media_cache'], -30, null, true);
    }
    $_SESSION['erocloud_acp_media_cache']['fid_' . $info['file_id']] = $info;
    $_SESSION['erocloud_acp_media_cache']['mid_' . $info['id']]      = $info;

    return $info;
}

/**
 * Hilfsfunktion: Erzeugt ein sauberes SVG-Platzhalterbild falls die Bilddatei lokal (z.B. XAMPP) fehlt
 */
function acp_media_output_svg_placeholder(string $label, string $sublabel = '', int $width = 320, int $height = 180): void
{
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeSub   = htmlspecialchars($sublabel, ENT_QUOTES, 'UTF-8');
    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1e293b"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
  </defs>
  <rect width="100%" height="100%" fill="url(#bg)"/>
  <rect x="6" y="6" width="calc(100% - 12px)" height="calc(100% - 12px)" rx="8" fill="none" stroke="#334155" stroke-width="1" stroke-dasharray="4 4"/>
  <text x="50%" y="46%" dominant-baseline="middle" text-anchor="middle" fill="#60a5fa" font-family="Inter, Arial, sans-serif" font-size="15" font-weight="700">{$safeLabel}</text>
  <text x="50%" y="64%" dominant-baseline="middle" text-anchor="middle" fill="#94a3b8" font-family="Inter, Arial, sans-serif" font-size="11">{$safeSub}</text>
</svg>
SVG;
    exit;
}

// ============================================================================
// 1. VIDEO-STREAMING (Byte-Range fähig via VideoStream)
// ============================================================================
if ($type === 'video') {
    $movie = acp_media_resolve_movie($fileId, $movieId);
    // Session-Lock sofort freigeben, damit paralleles Spulen & AJAX-Requests nicht blockieren!
    @session_write_close();

    if ($movie === null) {
        http_response_code(404);
        exit('Video nicht gefunden.');
    }

    $videoPath = rtrim((string)MOVIES_PATH, '/\\') . '/'
        . $movie['storage_location'] . '/'
        . $movie['merchant_id'] . '/'
        . $movie['id'] . '/'
        . $movie['filename'];

    if (!is_file($videoPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Lokale Videodatei auf diesem System nicht vorhanden: ' . $movie['filename'];
        exit;
    }

    require_once KLASSEN_DIR . '/VideoStream.inc.php';
    p4c_close(DB_HOST);
    $stream = new \VideoStream($videoPath);
    $stream->start();
    exit;
}

// ============================================================================
// 2. WEBVTT UNTERTITEL-AUSLIEFERUNG (für HTML5 <track>)
// ============================================================================
if ($type === 'vtt') {
    $movie = acp_media_resolve_movie($fileId, $movieId);
    @session_write_close();

    header('Content-Type: text/vtt; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    if ($movie === null) {
        echo "WEBVTT\n\n";
        exit;
    }

    $vttFile = rtrim((string)MOVIES_PATH, '/\\') . '/'
        . $movie['storage_location'] . '/'
        . $movie['merchant_id'] . '/'
        . $movie['id'] . '/sub_' . $movie['id'] . '_' . $movie['file_id'] . '.vtt';

    if (is_file($vttFile)) {
        readfile($vttFile);
        p4c_close(DB_HOST);
        exit;
    }

    // Fallback: Direkt aus movies_ai_analysis.spoken_segments_json generieren
    require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
    $rsAi = p4c_query(
        "SELECT `detected_language`, `spoken_segments_json`
         FROM `movies_ai_analysis`
         WHERE `movie_id` = '" . abs((int)$movie['id']) . "'
         LIMIT 1;",
        __FILE__,
        __LINE__
    );
    if ($rsAi && p4c_num_rows($rsAi) > 0) {
        $aiRow = p4c_fetch_object($rsAi);
        $segs  = !empty($aiRow->spoken_segments_json) ? @json_decode((string)$aiRow->spoken_segments_json, true) : [];
        if (is_array($segs) && !empty($segs) && function_exists('aic_build_vtt_content')) {
            echo aic_build_vtt_content($segs, (string)($aiRow->detected_language ?: 'de'));
            p4c_close(DB_HOST);
            exit;
        }
    }

    p4c_close(DB_HOST);
    echo "WEBVTT\n\n";
    exit;
}

// ============================================================================
// 3. SZENEN-THUMBNAIL (1..12) ODER POSTER (FSK16 / FSK18)
// ============================================================================
$movie = acp_media_resolve_movie($fileId, $movieId);
@session_write_close();

if ($movie === null) {
    p4c_close(DB_HOST);
    acp_media_output_svg_placeholder('Film nicht gefunden', '');
}

$thumbNum = isset($_GET['num']) ? abs((int)$_GET['num']) : 0;
if ($type === 'poster') {
    $fsk = isset($_GET['fsk']) && (int)$_GET['fsk'] === 18 ? 18 : 16;
    $thumbNum = $fsk === 18 ? (int)$movie['preview_image_fsk18'] : (int)$movie['preview_image_fsk16'];
}
if ($thumbNum < 1 || $thumbNum > 12) {
    $thumbNum = 1;
}

$movieDir  = rtrim((string)MOVIES_PATH, '/\\') . '/' . $movie['storage_location'] . '/' . $movie['merchant_id'] . '/' . $movie['id'];
$thumbPath = $movieDir . '/thumb_' . $movie['id'] . '_' . $movie['file_id'] . '_' . $thumbNum . '.jpg';

if (!is_file($thumbPath) && $thumbNum > 10) {
    // Falls kein eigenes Cover (11/12) existiert, Fallback auf Szene 1
    $fallbackPath = $movieDir . '/thumb_' . $movie['id'] . '_' . $movie['file_id'] . '_1.jpg';
    if (is_file($fallbackPath)) {
        $thumbPath = $fallbackPath;
    }
}

p4c_close(DB_HOST);

if (!is_file($thumbPath)) {
    $label = $thumbNum === 11 ? 'Eigenes FSK16' : ($thumbNum === 12 ? 'Eigenes FSK18' : 'Szene #' . $thumbNum);
    acp_media_output_svg_placeholder($label, 'Film #' . $movie['id'] . ' (' . ($movie['resolution'] ?: 'HD') . ')');
}

$reqWidth = isset($_GET['w']) ? abs((int)$_GET['w']) : 0;
$mtime    = (int)@filemtime($thumbPath);

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime ?: time()) . ' GMT');

if ($reqWidth > 0 && $reqWidth <= 800 && function_exists('imagecreatefromjpeg')) {
    $imgSize = @getimagesize($thumbPath);
    if (is_array($imgSize) && $imgSize[0] > $reqWidth && $imgSize[1] > 0) {
        $srcImg = @imagecreatefromjpeg($thumbPath);
        if ($srcImg !== false) {
            $ratio     = $imgSize[0] / $imgSize[1];
            $newWidth  = $reqWidth;
            $newHeight = max(1, (int)round($newWidth / $ratio));
            $dstImg    = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $imgSize[0], $imgSize[1]);
            imagejpeg($dstImg, null, 88);
            imagedestroy($dstImg);
            imagedestroy($srcImg);
            exit;
        }
    }
}

readfile($thumbPath);
exit;
