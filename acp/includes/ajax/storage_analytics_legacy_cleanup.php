<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 *
 * Sichere Einzel-Video-Vorschau & Bereinigung für veraltete Mobil-Formate (m_*.mp4, m_*.webm, m_*.ogv)
 */

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (is_logged_in('acp') === false) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(array('error' => 'Nicht autorisiert. Bitte melden Sie sich im Adminbereich an.'));
    exit;
}

if (!function_exists('sa_format_bytes')) {
    function sa_format_bytes($bytes) {
        $bytes = (float)$bytes;
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2, ',', '.') . ' TB';
        } elseif ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return number_format($bytes, 0, ',', '.') . ' Bytes';
    }
}

/**
 * Prüft ein einzelnes Film-Verzeichnis nach strengsten Sicherheitsregeln.
 * Gibt alle geschützten Dateien (Desktop-MP4, Thumbnails) und die eindeutig
 * als veraltete Mobil-Dateien (m_*.mp4, m_*.webm, m_*.ogv) identifizierten Dateien zurück.
 */
function sa_inspect_movie_folder($m) {
    $loc = ($m->storage_location !== '') ? $m->storage_location : MOVIES_DEFAULT_DIR;
    $folder = MOVIES_PATH . '/' . $loc . '/' . abs($m->merchant_id) . '/' . abs($m->id);

    $db_filename = trim((string)$m->filename);
    $expected_desktop_name = abs($m->id) . '_' . $m->file_id . '.mp4';
    $desktop_filename = ($db_filename !== '') ? $db_filename : $expected_desktop_name;
    $desktop_full_path = $folder . '/' . $desktop_filename;

    $folder_exists = is_dir($folder);
    $desktop_exists = ($folder_exists && is_file($desktop_full_path));
    $desktop_size = $desktop_exists ? (float)@filesize($desktop_full_path) : 0.0;
    $desktop_realpath = $desktop_exists ? @realpath($desktop_full_path) : false;

    $protected_files = array();
    $deletable_files = array();
    $thumbnails_count = 0;
    $thumbnails_bytes = 0.0;
    $deletable_total_bytes = 0.0;

    if ($folder_exists) {
        $items = @scandir($folder);
        if ($items !== false) {
            foreach ($items as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $fpath = $folder . '/' . $f;
                if (!is_file($fpath)) {
                    continue;
                }

                $fsize = (float)@filesize($fpath);
                $f_realpath = @realpath($fpath);
                $lower_f = strtolower($f);
                $ext = pathinfo($lower_f, PATHINFO_EXTENSION);

                if (strpos($lower_f, 'thumb_') === 0) {
                    $thumbnails_count++;
                    $thumbnails_bytes += $fsize;
                    $protected_files[] = array(
                        'filename'       => $f,
                        'full_path'      => $fpath,
                        'size_bytes'     => $fsize,
                        'size_formatted' => sa_format_bytes($fsize),
                        'role'           => 'Vorschaubild / Cover (Geschützt)'
                    );
                    continue;
                }

                // 7-Stufen-Sicherheitsprüfung für zu löschende Mobil-Dateien (m_*.mp4, m_*.webm, m_*.ogv):
                // 1. Dateiname muss zwingend mit "m_" beginnen
                $cond_starts_with_m = (strpos($lower_f, 'm_') === 0);
                // 2. Dateiendung muss exakt mp4, webm oder ogv sein
                $cond_valid_ext = in_array($ext, array('mp4', 'webm', 'ogv'), true);
                // 3. Dateiname muss exakt dem Schema m_{movie_id}_{file_id}.{ext} entsprechen
                $cond_regex_match = (bool)preg_match('/^m_[0-9]+_[a-z0-9]+\.(mp4|webm|ogv)$/i', $f);
                // 4. Präfix muss zwingend zur aktuellen Movie-ID gehören (m_{id}_)
                $cond_belongs_to_id = (strpos($lower_f, 'm_' . abs($m->id) . '_') === 0);
                // 5. Darf NIEMALS mit dem Desktop-Dateinamen aus der DB oder dem Standard-Desktop-Namen übereinstimmen
                $cond_not_desktop_name = (
                    $lower_f !== strtolower($desktop_filename) &&
                    $lower_f !== strtolower($expected_desktop_name)
                );
                // 6. Desktop-MP4 muss physisch existieren (> 0 Bytes) und einen anderen Realpath besitzen
                $cond_desktop_intact = (
                    $desktop_exists === true &&
                    $desktop_size > 0 &&
                    $f_realpath !== false &&
                    $desktop_realpath !== false &&
                    $f_realpath !== $desktop_realpath
                );
                // 7. Bei m_*.mp4 muss die mobile Datei kleiner sein als die Haupt-Desktop-MP4 (1000k vs 8000k)
                $cond_size_plausible = ($ext !== 'mp4') ? true : ($fsize < $desktop_size);

                if (
                    $cond_starts_with_m &&
                    $cond_valid_ext &&
                    $cond_regex_match &&
                    $cond_belongs_to_id &&
                    $cond_not_desktop_name &&
                    $cond_desktop_intact &&
                    $cond_size_plausible
                ) {
                    $format_label = 'Mobile ' . strtoupper($ext);
                    if ($ext === 'mp4') {
                        $format_label = 'Mobile MP4 (640px, 1000k - ungenutzt)';
                    } elseif ($ext === 'webm') {
                        $format_label = 'Mobile WebM (VP8/Vorbis - veraltet)';
                    } elseif ($ext === 'ogv') {
                        $format_label = 'Mobile OGV (Theora/Vorbis - veraltet)';
                    }

                    $deletable_files[] = array(
                        'filename'       => $f,
                        'full_path'      => $fpath,
                        'extension'      => $ext,
                        'format_label'   => $format_label,
                        'size_bytes'     => $fsize,
                        'size_formatted' => sa_format_bytes($fsize)
                    );
                    $deletable_total_bytes += $fsize;
                } else {
                    $role = ($lower_f === strtolower($desktop_filename) || $lower_f === strtolower($expected_desktop_name))
                        ? 'Desktop-Hauptvideo (GESCHÜTZT - BLEIBT ERHALTEN)'
                        : 'Sonstige Datei (Geschützt)';
                    $protected_files[] = array(
                        'filename'       => $f,
                        'full_path'      => $fpath,
                        'size_bytes'     => $fsize,
                        'size_formatted' => sa_format_bytes($fsize),
                        'role'           => $role
                    );
                }
            }
        }
    }

    return array(
        'movie_id'              => (int)$m->id,
        'file_id'               => (string)$m->file_id,
        'merchant_id'           => (int)$m->merchant_id,
        'actor_id'              => (int)$m->actor_id,
        'actor_username'        => isset($m->actor_username) && $m->actor_username !== '' ? $m->actor_username : 'Unbekannt',
        'title'                 => (string)$m->title,
        'status'                => (string)$m->status,
        'resolution'            => (string)$m->resolution,
        'playtime_string'       => (string)$m->playtime_string,
        'folder_path'           => $folder,
        'folder_exists'         => $folder_exists,
        'desktop_filename'      => $desktop_filename,
        'desktop_full_path'     => $desktop_full_path,
        'desktop_exists'        => $desktop_exists,
        'desktop_size_bytes'    => $desktop_size,
        'desktop_size_formatted'=> sa_format_bytes($desktop_size),
        'thumbnails_count'      => $thumbnails_count,
        'thumbnails_formatted'  => sa_format_bytes($thumbnails_bytes),
        'deletable_files'       => $deletable_files,
        'deletable_count'       => count($deletable_files),
        'deletable_total_bytes' => $deletable_total_bytes,
        'deletable_total_fmt'   => sa_format_bytes($deletable_total_bytes),
        'protected_files'       => $protected_files,
        'can_delete_safely'     => ($desktop_exists && $desktop_size > 0 && count($deletable_files) > 0)
    );
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'preview';

// =============================================================================
// AKTION 1: VORSCHAU FÜR GENAU EIN VIDEO LADEN (KEINE LÖSCHUNG!)
// =============================================================================
if ($action === 'preview') {
    $filter_movie_id    = isset($_GET['movie_id']) ? abs((int)$_GET['movie_id']) : 0;
    $filter_merchant_id = isset($_GET['merchant_id']) ? abs((int)$_GET['merchant_id']) : 0;
    $skip_count         = isset($_GET['skip']) ? abs((int)$_GET['skip']) : 0;

    $where_parts = array("`m`.`convert_status` = '2'");
    if ($filter_movie_id > 0) {
        $where_parts[] = "`m`.`id` = '" . $filter_movie_id . "'";
    }
    if ($filter_merchant_id > 0) {
        $where_parts[] = "`m`.`merchant_id` = '" . $filter_merchant_id . "'";
    }

    $where_sql = implode(' AND ', $where_parts);

    $rs_movies = p4c_query("
        SELECT 
            `m`.`id`,
            `m`.`file_id`,
            `m`.`merchant_id`,
            `m`.`actor_id`,
            `m`.`storage_location`,
            `m`.`filename`,
            `m`.`title`,
            `m`.`status`,
            `m`.`resolution`,
            `m`.`playtime_string`,
            `a`.`username` AS `actor_username`
        FROM `movies` AS `m`
        LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
        WHERE " . $where_sql . "
        ORDER BY `m`.`id` DESC;
    ", __FILE__, __LINE__);

    if (p4c_num_rows($rs_movies) == 0) {
        echo json_encode(array(
            'found'   => false,
            'message' => 'Es wurde kein konvertierter Film zu den angegebenen Filterkriterien in der Datenbank gefunden.'
        ));
        p4c_close(DB_HOST);
        exit;
    }

    $matched_index = 0;
    $inspected_folders = 0;
    $selected_inspection = null;

    while ($m = p4c_fetch_object($rs_movies)) {
        $info = sa_inspect_movie_folder($m);
        if ($info['folder_exists']) {
            $inspected_folders++;
        }

        // Wenn gezielt nach einer bestimmten Movie-ID gesucht wurde, geben wir genau dieses Video zurück
        // (auch wenn dessen m_*-Dateien bereits gelöscht wurden, damit man den Nachher-Zustand prüfen kann!)
        if ($filter_movie_id > 0) {
            $selected_inspection = $info;
            break;
        }

        // Andernfalls suchen wir das nächste Video, das noch löschbare m_*-Dateien besitzt und alle Sicherheitsprüfungen erfüllt
        if ($info['can_delete_safely']) {
            if ($matched_index >= $skip_count) {
                $selected_inspection = $info;
                break;
            }
            $matched_index++;
        }
    }

    if ($selected_inspection === null) {
        $msg = ($inspected_folders === 0)
            ? 'Auf diesem System wurden keine physischen Film-Verzeichnisse unter ' . MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR . ' gefunden (lokale Entwicklungsumgebung). Auf dem Live-Server wird hier automatisch das erste Video mit vorhandenen m_*-Dateien geladen.'
            : 'Es wurden keine weiteren Videos gefunden, die noch unbereinigte m_*-Dateien (m_*.mp4, m_*.webm, m_*.ogv) enthalten.';
        echo json_encode(array(
            'found'             => false,
            'inspected_folders' => $inspected_folders,
            'message'           => $msg
        ));
        p4c_close(DB_HOST);
        exit;
    }

    echo json_encode(array(
        'found' => true,
        'movie' => $selected_inspection
    ));
    p4c_close(DB_HOST);
    exit;
}

// =============================================================================
// AKTION 2: BESTÄTIGTE EINZEL-LÖSCHUNG FÜR GENAU EIN EINZIGES VIDEO
// =============================================================================
if ($action === 'delete_single') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('error' => 'Ungültige Anfrage-Methode. Löschungen sind nur per POST erlaubt.'));
        p4c_close(DB_HOST);
        exit;
    }

    $movie_id        = isset($_POST['movie_id']) ? abs((int)$_POST['movie_id']) : 0;
    $confirm_file_id = isset($_POST['confirm_file_id']) ? trim((string)$_POST['confirm_file_id']) : '';

    if ($movie_id <= 0 || $confirm_file_id === '') {
        echo json_encode(array('error' => 'Sicherheitssperre: Film-ID oder File-ID zur Bestätigung fehlt.'));
        p4c_close(DB_HOST);
        exit;
    }

    $rs_movie = p4c_query("
        SELECT 
            `m`.`id`,
            `m`.`file_id`,
            `m`.`merchant_id`,
            `m`.`actor_id`,
            `m`.`storage_location`,
            `m`.`filename`,
            `m`.`title`,
            `m`.`status`,
            `m`.`resolution`,
            `m`.`playtime_string`,
            `a`.`username` AS `actor_username`
        FROM `movies` AS `m`
        LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
        WHERE `m`.`id` = '" . $movie_id . "'
          AND `m`.`file_id` = '" . p4c_escape_string($confirm_file_id) . "'
        LIMIT 1;
    ", __FILE__, __LINE__);

    if (p4c_num_rows($rs_movie) !== 1) {
        echo json_encode(array('error' => 'Sicherheitssperre: Das angegebene Video wurde mit dieser Kombination aus ID und File-ID nicht gefunden.'));
        p4c_close(DB_HOST);
        exit;
    }

    $m = p4c_fetch_object($rs_movie);
    $before_info = sa_inspect_movie_folder($m);

    // Zwingende Vorab-Prüfung: Desktop-MP4 MUSS existieren und > 0 Bytes groß sein!
    if (!$before_info['desktop_exists'] || $before_info['desktop_size_bytes'] <= 0) {
        echo json_encode(array(
            'error' => 'ABBRUCH DURCH SICHERHEITSSPERRE: Die Desktop-Hauptdatei (' . $before_info['desktop_full_path'] . ') existiert nicht oder hat 0 Bytes. Es wurde keine einzige Datei gelöscht!'
        ));
        p4c_close(DB_HOST);
        exit;
    }

    if ($before_info['deletable_count'] === 0) {
        echo json_encode(array(
            'error' => 'Für dieses Video (#' . $movie_id . ') sind keine löschbaren m_*-Dateien (mehr) vorhanden.'
        ));
        p4c_close(DB_HOST);
        exit;
    }

    $desktop_size_before = $before_info['desktop_size_bytes'];
    $desktop_realpath    = @realpath($before_info['desktop_full_path']);

    $deleted_log = array();
    $failed_log  = array();
    $freed_bytes = 0.0;

    foreach ($before_info['deletable_files'] as $del_item) {
        $target_path = $del_item['full_path'];
        $target_name = $del_item['filename'];
        $target_real = @realpath($target_path);

        // Letzte unmittelbare Sicherheitsbarriere direkt vor unlink():
        // 1. Basename muss mit "m_" beginnen
        // 2. Darf nicht dem Desktop-Dateinamen entsprechen
        // 3. Realpath darf nicht dem Desktop-Realpath entsprechen
        if (
            strpos(strtolower(basename($target_path)), 'm_') === 0 &&
            strtolower(basename($target_path)) !== strtolower($before_info['desktop_filename']) &&
            $target_real !== false &&
            $desktop_realpath !== false &&
            $target_real !== $desktop_realpath &&
            is_file($target_path)
        ) {
            if (@unlink($target_path)) {
                clearstatcache(true, $target_path);
                $still_exists = file_exists($target_path);
                $deleted_log[] = array(
                    'filename'       => $target_name,
                    'full_path'      => $target_path,
                    'size_formatted' => $del_item['size_formatted'],
                    'size_bytes'     => $del_item['size_bytes'],
                    'verified_gone'  => !$still_exists
                );
                $freed_bytes += $del_item['size_bytes'];
            } else {
                $failed_log[] = array(
                    'filename'  => $target_name,
                    'full_path' => $target_path,
                    'reason'    => 'unlink() fehlgeschlagen (Dateiberechtigungen prüfen)'
                );
            }
        } else {
            $failed_log[] = array(
                'filename'  => $target_name,
                'full_path' => $target_path,
                'reason'    => 'Durch Sicherheitsbarriere blockiert'
            );
        }
    }

    // Stat-Cache leeren und Nachher-Zustand des Ordners prüfen
    clearstatcache();
    $after_info = sa_inspect_movie_folder($m);

    // 24h-Speicher-Analyse-Cache invalidieren, damit bei Neuberechnung aktuelle Werte geladen werden
    $cache_file = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }

    $desktop_verified_intact = (
        $after_info['desktop_exists'] === true &&
        $after_info['desktop_size_bytes'] === $desktop_size_before &&
        $after_info['desktop_size_bytes'] > 0
    );

    echo json_encode(array(
        'success'                 => true,
        'movie_id'                => $movie_id,
        'deleted_count'           => count($deleted_log),
        'deleted_files'           => $deleted_log,
        'failed_files'            => $failed_log,
        'freed_bytes'             => $freed_bytes,
        'freed_formatted'         => sa_format_bytes($freed_bytes),
        'desktop_verified_intact' => $desktop_verified_intact,
        'desktop_full_path'       => $after_info['desktop_full_path'],
        'desktop_size_formatted'  => $after_info['desktop_size_formatted'],
        'thumbnails_count_after'  => $after_info['thumbnails_count'],
        'movie_after'             => $after_info
    ));
    p4c_close(DB_HOST);
    exit;
}

echo json_encode(array('error' => 'Unbekannte Aktion.'));
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
exit;
?>
