<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 *
 * Sichere Einzel-Video-Vorschau, Einzel-Bereinigung & Kontrollierte Stapel-Bereinigung
 * für veraltete Mobil-Formate (m_*.mp4, m_*.webm, m_*.ogv)
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

@set_time_limit(120);

if (!function_exists('sa_safe_utf8')) {
    function sa_safe_utf8($str) {
        if ($str === null || $str === false) {
            return '';
        }
        $str = (string)$str;
        if (!mb_check_encoding($str, 'UTF-8')) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
        }
        return $str;
    }
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
    $folder = MOVIES_PATH . '/' . $loc . '/' . abs((int)$m->merchant_id) . '/' . abs((int)$m->id);

    $db_filename = trim((string)$m->filename);
    $expected_desktop_name = abs((int)$m->id) . '_' . $m->file_id . '.mp4';
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
                        'filename'       => sa_safe_utf8($f),
                        'full_path'      => sa_safe_utf8($fpath),
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
                $cond_belongs_to_id = (strpos($lower_f, 'm_' . abs((int)$m->id) . '_') === 0);
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
                        'filename'       => sa_safe_utf8($f),
                        'full_path'      => sa_safe_utf8($fpath),
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
                        'filename'       => sa_safe_utf8($f),
                        'full_path'      => sa_safe_utf8($fpath),
                        'size_bytes'     => $fsize,
                        'size_formatted' => sa_format_bytes($fsize),
                        'role'           => $role
                    );
                }
            }
        }
    }

    $online_id = isset($m->online_id) && (int)$m->online_id > 0 ? (int)$m->online_id : 0;

    return array(
        'movie_id'              => (int)$m->id,
        'online_id'             => $online_id,
        'file_id'               => (string)$m->file_id,
        'merchant_id'           => (int)$m->merchant_id,
        'actor_id'              => (int)$m->actor_id,
        'actor_username'        => isset($m->actor_username) && $m->actor_username !== '' ? sa_safe_utf8($m->actor_username) : 'Unbekannt',
        'title'                 => sa_safe_utf8($m->title),
        'status'                => (string)$m->status,
        'resolution'            => (string)$m->resolution,
        'playtime_string'       => (string)$m->playtime_string,
        'folder_path'           => sa_safe_utf8($folder),
        'folder_exists'         => $folder_exists,
        'desktop_filename'      => sa_safe_utf8($desktop_filename),
        'desktop_full_path'     => sa_safe_utf8($desktop_full_path),
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

/**
 * Hilfsfunktion: Löscht die verifizierten m_*-Dateien eines einzelnen Films
 * und prüft unmittelbar danach, ob die Desktop-MP4 weiterhin exakt unverändert existiert.
 */
function sa_execute_safe_movie_cleanup($m, $before_info) {
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

    clearstatcache();
    $after_info = sa_inspect_movie_folder($m);

    $desktop_verified_intact = (
        $after_info['desktop_exists'] === true &&
        $after_info['desktop_size_bytes'] === $desktop_size_before &&
        $after_info['desktop_size_bytes'] > 0
    );

    return array(
        'deleted_log'             => $deleted_log,
        'failed_log'              => $failed_log,
        'freed_bytes'             => $freed_bytes,
        'after_info'              => $after_info,
        'desktop_verified_intact' => $desktop_verified_intact
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

    $where_parts = array();
    $order_by_sql = "`mo`.`id` DESC, `m`.`id` DESC";

    if ($filter_movie_id > 0) {
        // WICHTIG: In "/Filme-online" wird die ID aus `movies_online.id` angezeigt,
        // während das physische Verzeichnis nach `movies.id` benannt ist (verknüpft über `file_id`).
        // Wir prüfen daher primär auf `movies_online.id` und als Fallback auf `movies.id`!
        $where_parts[] = "(`mo`.`id` = '" . $filter_movie_id . "' OR `m`.`id` = '" . $filter_movie_id . "')";
        $order_by_sql = "(CASE WHEN `mo`.`id` = '" . $filter_movie_id . "' THEN 0 ELSE 1 END) ASC, `m`.`id` DESC";
    } else {
        $where_parts[] = "`m`.`convert_status` = '2'";
    }

    if ($filter_merchant_id > 0) {
        $where_parts[] = "`m`.`merchant_id` = '" . $filter_merchant_id . "'";
    }

    $where_sql = implode(' AND ', $where_parts);

    $rs_movies = p4c_query("
        SELECT 
            `m`.`id`,
            `mo`.`id` AS `online_id`,
            `m`.`file_id`,
            `m`.`merchant_id`,
            `m`.`actor_id`,
            `m`.`storage_location`,
            `m`.`filename`,
            IFNULL(`mo`.`title`, `m`.`title`) AS `title`,
            `m`.`status`,
            `m`.`resolution`,
            `m`.`playtime_string`,
            `a`.`username` AS `actor_username`
        FROM `movies` AS `m`
        LEFT JOIN `movies_online` AS `mo` ON `m`.`file_id` = `mo`.`file_id`
        LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
        WHERE " . $where_sql . "
        ORDER BY " . $order_by_sql . ";
    ", __FILE__, __LINE__);

    if (p4c_num_rows($rs_movies) == 0) {
        echo json_encode(array(
            'found'   => false,
            'message' => 'Es wurde kein Film mit der ID #' . $filter_movie_id . ' (weder in Filme-online noch in der Haupttabelle movies) gefunden.'
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

        // Wenn gezielt nach einer bestimmten ID gesucht wurde, geben wir sofort den besten Treffer zurück
        // (Priorität 1: `movies_online.id`, Priorität 2: `movies.id`)
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
            `mo`.`id` AS `online_id`,
            `m`.`file_id`,
            `m`.`merchant_id`,
            `m`.`actor_id`,
            `m`.`storage_location`,
            `m`.`filename`,
            IFNULL(`mo`.`title`, `m`.`title`) AS `title`,
            `m`.`status`,
            `m`.`resolution`,
            `m`.`playtime_string`,
            `a`.`username` AS `actor_username`
        FROM `movies` AS `m`
        LEFT JOIN `movies_online` AS `mo` ON `m`.`file_id` = `mo`.`file_id`
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

    $exec_res = sa_execute_safe_movie_cleanup($m, $before_info);
    $after_info = $exec_res['after_info'];

    // 24h-Speicher-Analyse-Cache invalidieren, damit bei Neuberechnung aktuelle Werte geladen werden
    $cache_file = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }

    echo json_encode(array(
        'success'                 => true,
        'movie_id'                => $movie_id,
        'online_id'               => $after_info['online_id'],
        'deleted_count'           => count($exec_res['deleted_log']),
        'deleted_files'           => $exec_res['deleted_log'],
        'failed_files'            => $exec_res['failed_log'],
        'freed_bytes'             => $exec_res['freed_bytes'],
        'freed_formatted'         => sa_format_bytes($exec_res['freed_bytes']),
        'desktop_verified_intact' => $exec_res['desktop_verified_intact'],
        'desktop_full_path'       => $after_info['desktop_full_path'],
        'desktop_size_formatted'  => $after_info['desktop_size_formatted'],
        'thumbnails_count_after'  => $after_info['thumbnails_count'],
        'movie_after'             => $after_info
    ));
    p4c_close(DB_HOST);
    exit;
}

// =============================================================================
// AKTION 3: KONTROLLIERTE STAPEL-BEREINIGUNG (BATCH-MODUS MIT 7-STUFEN-PRÜFUNG)
// =============================================================================
if ($action === 'delete_batch') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('error' => 'Ungültige Anfrage-Methode. Stapel-Löschungen sind nur per POST erlaubt.'));
        p4c_close(DB_HOST);
        exit;
    }

    $batch_limit     = isset($_POST['batch_limit']) ? abs((int)$_POST['batch_limit']) : 10;
    if ($batch_limit < 1) {
        $batch_limit = 1;
    } elseif ($batch_limit > 250) {
        $batch_limit = 250;
    }

    $filter_merchant_id = isset($_POST['merchant_id']) ? abs((int)$_POST['merchant_id']) : 0;
    $cursor_movie_id    = isset($_POST['cursor_movie_id']) ? abs((int)$_POST['cursor_movie_id']) : 0;

    $where_parts = array("`m`.`convert_status` = '2'");
    if ($filter_merchant_id > 0) {
        $where_parts[] = "`m`.`merchant_id` = '" . $filter_merchant_id . "'";
    }
    if ($cursor_movie_id > 0) {
        $where_parts[] = "`m`.`id` < '" . $cursor_movie_id . "'";
    }

    $where_sql = implode(' AND ', $where_parts);

    // Gesamtanzahl der Kandidaten in der DB für Fortschrittsanzeige ermitteln
    $where_total_parts = array("`m`.`convert_status` = '2'");
    if ($filter_merchant_id > 0) {
        $where_total_parts[] = "`m`.`merchant_id` = '" . $filter_merchant_id . "'";
    }
    $rs_total_cnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` AS `m` WHERE " . implode(' AND ', $where_total_parts) . ";", __FILE__, __LINE__);
    $total_db_candidates = (int)p4c_fetch_object($rs_total_cnt)->cnt;

    // Wir prüfen pro Request maximal 500 Ordner am Stück, damit der Request immer schnell antwortet
    $max_scan_per_call = 500;

    $rs_movies = p4c_query("
        SELECT 
            `m`.`id`,
            `mo`.`id` AS `online_id`,
            `m`.`file_id`,
            `m`.`merchant_id`,
            `m`.`actor_id`,
            `m`.`storage_location`,
            `m`.`filename`,
            IFNULL(`mo`.`title`, `m`.`title`) AS `title`,
            `m`.`status`,
            `m`.`resolution`,
            `m`.`playtime_string`,
            `a`.`username` AS `actor_username`
        FROM `movies` AS `m`
        LEFT JOIN `movies_online` AS `mo` ON `m`.`file_id` = `mo`.`file_id`
        LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
        WHERE " . $where_sql . "
        ORDER BY `m`.`id` DESC
        LIMIT " . $max_scan_per_call . ";
    ", __FILE__, __LINE__);

    $rows_fetched = p4c_num_rows($rs_movies);

    $cleaned_movies      = array();
    $skipped_movies      = array();
    $scanned_in_call     = 0;
    $deleted_files_total = 0;
    $freed_bytes_total   = 0.0;
    $last_seen_movie_id  = $cursor_movie_id;
    $emergency_stop      = false;
    $emergency_message   = '';

    while ($m = p4c_fetch_object($rs_movies)) {
        $scanned_in_call++;
        $last_seen_movie_id = (int)$m->id;

        $before_info = sa_inspect_movie_folder($m);

        // Falls der physische Ordner auf dem Server gar nicht existiert -> weiter
        if (!$before_info['folder_exists']) {
            continue;
        }

        // Falls der Ordner bereits bereinigt ist (keine m_*-Dateien mehr vorhanden) -> weiter
        if ($before_info['deletable_count'] === 0) {
            continue;
        }

        // Falls m_*-Dateien existieren, aber die Desktop-MP4 fehlt oder 0 Bytes hat -> SICHERHEITS-SKIP!
        if (!$before_info['desktop_exists'] || $before_info['desktop_size_bytes'] <= 0) {
            $skipped_movies[] = array(
                'movie_id'    => (int)$m->id,
                'online_id'   => $before_info['online_id'],
                'merchant_id' => (int)$m->merchant_id,
                'title'       => $before_info['title'],
                'folder_path' => $before_info['folder_path'],
                'reason'      => 'Desktop-MP4 fehlt oder hat 0 Bytes – Ordner zu 100 % unberührt übersprungen!'
            );
            continue;
        }

        // Sichere Löschung der m_*-Dateien für dieses Video durchführen
        $exec_res = sa_execute_safe_movie_cleanup($m, $before_info);

        $file_names_deleted = array();
        foreach ($exec_res['deleted_log'] as $dl) {
            $file_names_deleted[] = $dl['filename'];
        }

        $deleted_cnt = count($exec_res['deleted_log']);
        $deleted_files_total += $deleted_cnt;
        $freed_bytes_total   += $exec_res['freed_bytes'];

        $cleaned_movies[] = array(
            'movie_id'                => (int)$m->id,
            'online_id'               => $before_info['online_id'],
            'merchant_id'             => (int)$m->merchant_id,
            'title'                   => $before_info['title'],
            'folder_path'             => $before_info['folder_path'],
            'deleted_count'           => $deleted_cnt,
            'deleted_filenames'       => implode(', ', $file_names_deleted),
            'freed_bytes'             => $exec_res['freed_bytes'],
            'freed_formatted'         => sa_format_bytes($exec_res['freed_bytes']),
            'desktop_filename'        => $before_info['desktop_filename'],
            'desktop_size_formatted'  => $exec_res['after_info']['desktop_size_formatted'],
            'desktop_verified_intact' => $exec_res['desktop_verified_intact']
        );

        // NOT-AUS: Falls jemals nach dem Löschen die Desktop-MP4 nicht mehr 1:1 verifiziert werden kann, sofort abbrechen!
        if (!$exec_res['desktop_verified_intact']) {
            $emergency_stop = true;
            $emergency_message = 'SICHERHEITS-STOPP bei Video #' . (int)$m->id . ': Nachprüfung der Desktop-MP4 fehlgeschlagen. Stapellauf wurde sofort angehalten!';
            break;
        }

        // Sobald die gewünschte Anzahl Videos ($batch_limit) bereinigt wurde, Schleife beenden
        if (count($cleaned_movies) >= $batch_limit) {
            break;
        }
    }

    // Prüfen, ob es noch weitere Filme mit kleinerer ID in der Datenbank gibt
    $has_more_in_db = false;
    if (!$emergency_stop && $last_seen_movie_id > 0) {
        $where_rem_parts = array("`m`.`convert_status` = '2'", "`m`.`id` < '" . $last_seen_movie_id . "'");
        if ($filter_merchant_id > 0) {
            $where_rem_parts[] = "`m`.`merchant_id` = '" . $filter_merchant_id . "'";
        }
        $rs_rem = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` AS `m` WHERE " . implode(' AND ', $where_rem_parts) . ";", __FILE__, __LINE__);
        $remaining_db_rows = (int)p4c_fetch_object($rs_rem)->cnt;
        $has_more_in_db = ($remaining_db_rows > 0);
    } else {
        $remaining_db_rows = 0;
    }

    // Wenn Dateien gelöscht wurden, 24h-Caches invalidieren
    if ($deleted_files_total > 0) {
        $cache_file_sa = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
        if (file_exists($cache_file_sa)) {
            @unlink($cache_file_sa);
        }
        $cache_file_mo = sys_get_temp_dir() . '/erocloud_online_movies_size_cache.txt';
        if (file_exists($cache_file_mo)) {
            @unlink($cache_file_mo);
        }
    }

    echo json_encode(array(
        'success'               => !$emergency_stop,
        'emergency_stop'        => $emergency_stop,
        'emergency_message'     => $emergency_message,
        'batch_limit'           => $batch_limit,
        'scanned_in_call'       => $scanned_in_call,
        'cleaned_movies_count'  => count($cleaned_movies),
        'cleaned_movies'        => $cleaned_movies,
        'skipped_movies_count'  => count($skipped_movies),
        'skipped_movies'        => $skipped_movies,
        'deleted_files_count'   => $deleted_files_total,
        'freed_bytes'           => $freed_bytes_total,
        'freed_formatted'       => sa_format_bytes($freed_bytes_total),
        'next_cursor_movie_id'  => $last_seen_movie_id,
        'has_more'              => $has_more_in_db,
        'total_db_candidates'   => $total_db_candidates,
        'remaining_db_rows'     => $remaining_db_rows
    ));
    p4c_close(DB_HOST);
    exit;
}

echo json_encode(array('error' => 'Unbekannte Aktion.'));
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
exit;
?>
