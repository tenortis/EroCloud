<?php
declare(strict_types=1);

/**
 * EroCloud ACP (v2.0) - Geschützter Medien-, Video-Stream-, VTT- & Thumbnail-Proxy
 * Ersetzt acp/includes/movie_player.php, acp/includes/movie_poster.php und Cross-Domain-Aufrufe.
 */

if (!defined('SAFE_INC')) {
    define('SAFE_INC', 1);
}

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
// 2a. DREHPARTNER & ID-CHECK MEDIEN / ZERTIFIKAT (type=co_actor)
// ============================================================================
if ($type === 'co_actor') {
    if (!class_exists('CoActorManager') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/CoActorManager.inc.php')) {
        require_once SOURCEDIR . '/includes/klassen/CoActorManager.inc.php';
    }
    $coActorId = isset($_GET['id']) ? abs((int)$_GET['id']) : 0;
    $subType   = isset($_GET['sub']) ? trim((string)$_GET['sub']) : 'id_shot';

    if ($coActorId <= 0 || !class_exists('CoActorManager')) {
        p4c_close(DB_HOST);
        acp_media_output_svg_placeholder('Ungültige ID', '', 200, 140);
    }

    $coActor = \CoActorManager::getCoActorById($coActorId);
    @session_write_close();

    if ($coActor === null) {
        p4c_close(DB_HOST);
        acp_media_output_svg_placeholder('Nicht gefunden', 'ID #' . $coActorId, 200, 140);
    }

    $merchantId = (int)$coActor['merchant_id'];
    $storageDir = \CoActorManager::getStorageDir($merchantId, $coActorId);

    if ($subType === 'certificate' || $subType === 'consent' || $subType === 'consent_raw') {
        $consentFile = (string)($coActor['consent_doc_file'] ?? '');
        $sigData     = $coActor['signature_data'] ?? null;

        if ($subType === 'consent_raw' || ($consentFile !== '' && empty($sigData) && is_file($storageDir . '/' . $consentFile))) {
            $filePath = $storageDir . '/' . basename($consentFile);
            p4c_close(DB_HOST);
            if (is_file($filePath)) {
                $ext  = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'pdf'   => 'application/pdf',
                    'png'   => 'image/png',
                    'webp'  => 'image/webp',
                    default => 'image/jpeg',
                };
                header('Content-Type: ' . $mime);
                header('Content-Disposition: inline; filename="Einverstaendnis_' . $coActor['ref_code'] . '.' . $ext . '"');
                header('Content-Length: ' . (string)filesize($filePath));
                header('Cache-Control: private, max-age=300');
                readfile($filePath);
                exit;
            }
        }

        $sigDataUri = '';
        if ($consentFile !== '' && is_file($storageDir . '/' . basename($consentFile))) {
            $rawBytes = @file_get_contents($storageDir . '/' . basename($consentFile));
            if (is_string($rawBytes) && $rawBytes !== '') {
                $sigDataUri = 'data:image/png;base64,' . base64_encode($rawBytes);
            }
        }

        $merchantIdent = \CoActorManager::getMerchantIdentity($merchantId);
        p4c_close(DB_HOST);
        header('Content-Type: text/html; charset=UTF-8');
        echo \CoActorManager::renderConsentDocumentHtml($coActor, $merchantIdent, $sigDataUri);
        exit;
    }

    p4c_close(DB_HOST);

    $fileColumnMap = [
        'face_ref' => (string)($coActor['face_ref_img'] ?? ''),
        'id_shot'  => (string)($coActor['id_shot_img1'] ?? ''),
        'id_front' => (string)($coActor['id_front_img'] ?? ''),
        'id_back'  => (string)($coActor['id_back_img'] ?? ''),
    ];
    $fileName = $fileColumnMap[$subType] ?? '';
    $filePath = $fileName !== '' ? ($storageDir . '/' . basename($fileName)) : '';

    if ($filePath === '' || !is_file($filePath)) {
        $labelMap = [
            'face_ref' => '1. KI-Selfie',
            'id_shot'  => '2. ID-Shot',
            'id_front' => '3. Ausweis V.',
            'id_back'  => '4. Ausweis R.',
        ];
        acp_media_output_svg_placeholder($labelMap[$subType] ?? 'Dokument', (string)($coActor['ref_code'] ?? ''), 240, 160);
    }

    $ext  = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png'   => 'image/png',
        'webp'  => 'image/webp',
        'pdf'   => 'application/pdf',
        default => 'image/jpeg',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($filePath));
    header('Cache-Control: private, max-age=300');
    readfile($filePath);
    exit;
}

// ============================================================================
// 2a-bis. DARSTELLER-PROFILBILDER FSK16 / FSK18 (type=actor_avatar)
// ============================================================================
if ($type === 'actor_avatar') {
    $actorId  = isset($_GET['actor_id']) ? abs((int)$_GET['actor_id']) : 0;
    $fsk      = (isset($_GET['fsk']) && abs((int)$_GET['fsk']) === 18) ? 18 : 16;
    $reqWidth = isset($_GET['w']) ? abs((int)$_GET['w']) : 0;

    if ($actorId <= 0) {
        @session_write_close();
        p4c_close(DB_HOST);
        acp_media_output_svg_placeholder('Kein Profil', 'FSK' . $fsk, 160, 160);
    }

    $rsAct = p4c_query(
        "SELECT `id`, `merchant_id`, `username`, `profile_image_fsk16`, `profile_image_fsk18`
         FROM `actors`
         WHERE `id` = '" . $actorId . "'
         LIMIT 1;",
        __FILE__,
        __LINE__
    );
    @session_write_close();

    if (!$rsAct || p4c_num_rows($rsAct) === 0) {
        p4c_close(DB_HOST);
        acp_media_output_svg_placeholder('Profil #' . $actorId, 'Nicht gefunden', 160, 160);
    }

    $actObj   = p4c_fetch_object($rsAct);
    p4c_close(DB_HOST);

    $colName  = $fsk === 18 ? 'profile_image_fsk18' : 'profile_image_fsk16';
    $imgFile  = trim((string)($actObj->$colName ?? ''));
    $uname    = trim((string)($actObj->username ?? ('#' . $actorId)));

    if ($imgFile === '') {
        acp_media_output_svg_placeholder('Kein FSK' . $fsk, $uname, 160, 160);
    }

    $candidate = rtrim((string)PROFILE_IMAGE_PATH, '/\\') . '/'
        . (string)MERCHANT_DEFAULT_DIR . '/'
        . abs((int)$actObj->merchant_id) . '/'
        . abs((int)$actObj->id) . '/'
        . basename($imgFile);

    if (!is_file($candidate) || filesize($candidate) === 0) {
        acp_media_output_svg_placeholder('FSK' . $fsk . ' Bild', $uname, 160, 160);
    }

    $mtime = (int)@filemtime($candidate);
    $mime  = function_exists('mime_content_type') ? (string)@mime_content_type($candidate) : 'image/jpeg';
    if ($mime === '') {
        $mime = 'image/jpeg';
    }

    header('Cache-Control: private, max-age=600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime ?: time()) . ' GMT');

    if ($reqWidth > 0 && $reqWidth <= 800 && ($mime === 'image/jpeg' || $mime === 'image/png') && function_exists('imagecreatetruecolor')) {
        $imgSize = @getimagesize($candidate);
        if (is_array($imgSize) && $imgSize[0] > $reqWidth && $imgSize[1] > 0) {
            $srcImg = $mime === 'image/png' ? @imagecreatefrompng($candidate) : @imagecreatefromjpeg($candidate);
            if ($srcImg !== false) {
                $ratio     = $imgSize[0] / $imgSize[1];
                $newWidth  = $reqWidth;
                $newHeight = max(1, (int)round($newWidth / $ratio));
                $dstImg    = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $imgSize[0], $imgSize[1]);
                header('Content-Type: image/jpeg');
                imagejpeg($dstImg, null, 88);
                imagedestroy($dstImg);
                imagedestroy($srcImg);
                exit;
            }
        }
    }

    header('Content-Type: ' . $mime);
    readfile($candidate);
    exit;
}

// ============================================================================
// 2b. FOTOALBUM COVER-POSTER (type=album_poster) & EINZELFOTO (type=album_photo)
// ============================================================================
if ($type === 'album_poster' || $type === 'album_photo') {
    $albumParam = isset($_GET['album_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['album_id']) : '';
    $photoParam = isset($_GET['photo_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['photo_id']) : '';
    $reqWidth   = isset($_GET['w']) ? abs((int)$_GET['w']) : 0;

    $targetFile = '';
    $fallbackTitle = 'Fotoalbum';
    $fallbackSub   = '';

    if ($type === 'album_poster') {
        $fsk = (isset($_GET['fsk']) && abs((int)$_GET['fsk']) === 18) ? 18 : 16;
        $numId = ctype_digit($albumParam) ? abs((int)$albumParam) : 0;
        $whereSql = $numId > 0
            ? "(`album_id` = '" . p4c_escape_string($albumParam) . "' OR `id` = '" . $numId . "')"
            : "`album_id` = '" . p4c_escape_string($albumParam) . "'";

        $rsAlb = p4c_query(
            "SELECT `id`, `merchant_id`, `storage_location`, `album_id`, `preview_image_fsk16`, `preview_image_fsk18`
             FROM `photo_albums`
             WHERE {$whereSql}
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        @session_write_close();

        if (!$rsAlb || p4c_num_rows($rsAlb) === 0) {
            p4c_close(DB_HOST);
            acp_media_output_svg_placeholder('FSK' . $fsk . ' Cover', 'Album nicht gefunden', 240, 180);
        }

        $alb = p4c_fetch_object($rsAlb);
        $albId = abs((int)$alb->id);
        $fallbackTitle = 'FSK' . $fsk . ' Cover';
        $fallbackSub   = 'Album #' . $albId;

        $albumFolder = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/'
            . (string)$alb->storage_location . '/'
            . abs((int)$alb->merchant_id) . '/'
            . $albId . '/';

        $colPri = $fsk === 18 ? 'preview_image_fsk18' : 'preview_image_fsk16';
        $colAlt = $fsk === 18 ? 'preview_image_fsk16' : 'preview_image_fsk18';

        if (!empty($alb->$colPri) && is_file($albumFolder . $alb->$colPri)) {
            $targetFile = $albumFolder . $alb->$colPri;
        } elseif (!empty($alb->$colAlt) && is_file($albumFolder . $alb->$colAlt)) {
            $targetFile = $albumFolder . $alb->$colAlt;
        } else {
            $rsFirst = p4c_query(
                "SELECT `filename` FROM `photo_albums_photos`
                 WHERE `album_id` = '" . p4c_escape_string((string)$alb->album_id) . "'
                 ORDER BY `id` ASC LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if ($rsFirst && p4c_num_rows($rsFirst) > 0) {
                $firstObj = p4c_fetch_object($rsFirst);
                $cand = $albumFolder . 'images/' . (string)$firstObj->filename;
                if (is_file($cand)) {
                    $targetFile = $cand;
                }
            }
        }
        p4c_close(DB_HOST);
    } else {
        // type === 'album_photo'
        $rsPh = p4c_query(
            "SELECT pa.`id` AS `album_db_id`, pa.`storage_location`, pap.`merchant_id`, pap.`filename`, pap.`id` AS `photo_db_id`
             FROM `photo_albums_photos` pap
             INNER JOIN `photo_albums` pa ON pap.`album_id` = pa.`album_id`
             WHERE pap.`file_id` = '" . p4c_escape_string($photoParam) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        @session_write_close();

        if (!$rsPh || p4c_num_rows($rsPh) === 0) {
            p4c_close(DB_HOST);
            acp_media_output_svg_placeholder('Foto fehlt', $photoParam, 240, 180);
        }

        $ph = p4c_fetch_object($rsPh);
        p4c_close(DB_HOST);

        $fallbackTitle = 'Foto #' . abs((int)$ph->photo_db_id);
        $fallbackSub   = 'Album #' . abs((int)$ph->album_db_id);

        $cand = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/'
            . (string)$ph->storage_location . '/'
            . abs((int)$ph->merchant_id) . '/'
            . abs((int)$ph->album_db_id) . '/images/'
            . (string)$ph->filename;

        if (is_file($cand) && filesize($cand) > 0) {
            $targetFile = $cand;
        }
    }

    if ($targetFile === '' || !is_file($targetFile)) {
        acp_media_output_svg_placeholder($fallbackTitle, $fallbackSub, 260, 180);
    }

    $mtime = (int)@filemtime($targetFile);
    $mime  = function_exists('mime_content_type') ? (string)@mime_content_type($targetFile) : 'image/jpeg';
    if ($mime === '') {
        $mime = 'image/jpeg';
    }

    header('Cache-Control: private, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime ?: time()) . ' GMT');

    if ($reqWidth > 0 && $reqWidth <= 1024 && ($mime === 'image/jpeg' || $mime === 'image/png') && function_exists('imagecreatetruecolor')) {
        $imgSize = @getimagesize($targetFile);
        if (is_array($imgSize) && $imgSize[0] > $reqWidth && $imgSize[1] > 0) {
            $srcImg = $mime === 'image/png' ? @imagecreatefrompng($targetFile) : @imagecreatefromjpeg($targetFile);
            if ($srcImg !== false) {
                $ratio     = $imgSize[0] / $imgSize[1];
                $newWidth  = $reqWidth;
                $newHeight = max(1, (int)round($newWidth / $ratio));
                $dstImg    = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $imgSize[0], $imgSize[1]);
                header('Content-Type: image/jpeg');
                imagejpeg($dstImg, null, 88);
                imagedestroy($dstImg);
                imagedestroy($srcImg);
                exit;
            }
        }
    }

    header('Content-Type: ' . $mime);
    readfile($targetFile);
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
