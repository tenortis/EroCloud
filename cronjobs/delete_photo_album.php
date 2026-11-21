<?php
declare(strict_types=1);

/**
 * EroCloud Cronjob: Bereinigung ungekaufter gelöschter Fotoalben & verwaister Entwürfe
 *
 * VERBINDLICHE KÄUFERSCHUTZ-REGEL:
 *  - Fotoalben mit mindestens 1 Kauf (photo_albums_access > 0 ODER purchases > 0) werden
 *    durch diesen Cronjob NIEMALS physisch gelöscht! Gekaufte Alben dürfen ausschließlich
 *    manuell durch einen Administrator im ACP (#/photo-album-edit -> Hard-Delete) bei
 *    zwingenden Rechts-/Persönlichkeitsrechts- oder DSGVO-Fällen gelöscht werden.
 *
 * Automatische Bereinigungsregeln (nur bei 0 Käufen):
 *  - Regel 1: Soft-Delete-Alben (status = 'deleted') nach >= 30 Tagen Wartezeit
 *             (damit alle angeschlossenen EroCMS-Portale das <status>deleted</status>-Signal
 *             zuvor sicher synchronisiert und lokal del_fotoalbum() ausgeführt haben).
 *  - Regel 2: Unveröffentlichte Entwürfe (released = 0) oder abgelehnte Alben (released = 2),
 *             die nie in photo_albums_online standen und seit >= 180 Tagen inaktiv sind.
 *
 * Kompatibel mit PHP 8.0.30 & PHP 8.4.27.
 */

define('SAFE_INC', 1);

require_once dirname(__DIR__) . '/config.inc.php';
require_once SOURCEDIR . '/includes/common.inc.php';

p4c_query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", __FILE__, __LINE__);

/**
 * Löscht ein Album-Verzeichnis inkl. Unterordner images/ rekursiv.
 */
function ec_delete_photo_album_folder(string $dir): int
{
    $deletedFiles = 0;
    if ($dir === '' || !is_dir($dir)) {
        return 0;
    }

    $items = @scandir($dir);
    if (!is_array($items)) {
        return 0;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $dir . '/' . $item;
        if (is_dir($full) && !is_link($full)) {
            $deletedFiles += ec_delete_photo_album_folder($full);
            @rmdir($full);
        } elseif (is_file($full) || is_link($full)) {
            if (@unlink($full)) {
                $deletedFiles++;
            }
        }
    }

    @rmdir($dir);
    return $deletedFiles;
}

$cutoffDeleted30d = date('Y-m-d H:i:s', strtotime('-30 days'));
$cutoffDrafts180d = date('Y-m-d H:i:s', strtotime('-180 days'));

// Kandidaten ermitteln (ausschließlich 0 Käufe in photo_albums, photo_albums_online UND photo_albums_access!)
$sqlCandidates = "
    SELECT
        pa.`id`,
        pa.`merchant_id`,
        pa.`album_id`,
        pa.`storage_location`,
        pa.`status`,
        pa.`released`,
        pa.`purchases`,
        pa.`deleted_datetime`,
        pao.`id` AS `online_id`,
        COALESCE(pao.`purchases`, 0) AS `online_purchases`
    FROM `photo_albums` pa
    LEFT JOIN `photo_albums_online` pao ON pao.`album_id` = pa.`album_id`
    WHERE COALESCE(pa.`purchases`, 0) = 0
      AND COALESCE(pao.`purchases`, 0) = 0
      AND NOT EXISTS (
          SELECT 1 FROM `photo_albums_access` paa
          WHERE paa.`album_id` = pa.`album_id`
      )
      AND (
          -- Regel 1: Soft-Delete seit mindestens 30 Tagen
          (
              pa.`status` = 'deleted'
              AND (
                  (pa.`deleted_datetime` != '0000-00-00 00:00:00' AND pa.`deleted_datetime` <= '" . p4c_escape_string($cutoffDeleted30d) . "')
                  OR (pa.`deleted_datetime` = '0000-00-00 00:00:00' AND pa.`last_updated_datetime` != '0000-00-00 00:00:00' AND pa.`last_updated_datetime` <= '" . p4c_escape_string($cutoffDeleted30d) . "')
              )
          )
          OR
          -- Regel 2: Verwaiste Entwürfe / abgelehnte Alben (> 180 Tage inaktiv, nie in photo_albums_online)
          (
              pao.`id` IS NULL
              AND pa.`released` IN (0, 2)
              AND pa.`create_datetime` != '0000-00-00 00:00:00'
              AND pa.`create_datetime` <= '" . p4c_escape_string($cutoffDrafts180d) . "'
              AND (pa.`last_updated_datetime` = '0000-00-00 00:00:00' OR pa.`last_updated_datetime` <= '" . p4c_escape_string($cutoffDrafts180d) . "')
          )
      )
    ORDER BY pa.`id` ASC
    LIMIT 50;
";

$rsCandidates = p4c_query($sqlCandidates, __FILE__, __LINE__);
$cleanedCount = 0;

if ($rsCandidates && p4c_num_rows($rsCandidates) > 0) {
    $basePath   = defined('PHOTO_ALBUMS_PATH') ? rtrim((string)PHOTO_ALBUMS_PATH, '/\\') : '';
    $defaultDir = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';

    while ($row = p4c_fetch_object($rsCandidates)) {
        $albumDbId   = abs((int)$row->id);
        $merchantId  = abs((int)$row->merchant_id);
        $albumIdHash = trim((string)$row->album_id);

        if ($albumDbId === 0 || $albumIdHash === '') {
            continue;
        }

        // Doppelte Sicherheitsprüfung direkt vor der physischen Löschung: 0 Einträge in photo_albums_access!
        $rsDoubleCheck = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `photo_albums_access` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
            __FILE__,
            __LINE__
        );
        $buyerCnt = ($rsDoubleCheck && p4c_num_rows($rsDoubleCheck) > 0)
            ? abs((int)(p4c_fetch_object($rsDoubleCheck)->cnt ?? 0))
            : 0;

        if ($buyerCnt > 0 || abs((int)$row->purchases) > 0 || abs((int)$row->online_purchases) > 0) {
            continue;
        }

        $storageLoc = trim((string)$row->storage_location) !== '' ? trim((string)$row->storage_location, '/\\') : $defaultDir;
        if ($basePath !== '' && $merchantId > 0) {
            $albumFolder = $basePath . '/' . $storageLoc . '/' . $merchantId . '/' . $albumDbId;
            ec_delete_photo_album_folder($albumFolder);
        }

        p4c_query("DELETE FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';", __FILE__, __LINE__);
        p4c_query("DELETE FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';", __FILE__, __LINE__);
        p4c_query("DELETE FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' LIMIT 1;", __FILE__, __LINE__);

        if (function_exists('ec_invalidate_photo_album_api_cache')) {
            ec_invalidate_photo_album_api_cache($albumIdHash);
        }

        $cleanedCount++;
    }
}

p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
