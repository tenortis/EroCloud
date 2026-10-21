<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
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
    echo json_encode(array('error' => 'Nicht autorisiert. Bitte melden Sie sich an.'));
    exit;
}

@set_time_limit(300);
$start_time = microtime(true);

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

if (!function_exists('sa_scan_dir_bytes')) {
    function sa_scan_dir_bytes($dir, &$file_count = 0) {
        $size = 0;
        if (!is_dir($dir)) {
            if (is_file($dir)) {
                $file_count++;
                return (float)@filesize($dir);
            }
            return 0;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'lost+found') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $size += sa_scan_dir_bytes($path, $file_count);
            } elseif (is_file($path)) {
                $file_count++;
                $size += (float)@filesize($path);
            }
        }
        return $size;
    }
}

if (!function_exists('sa_get_disk_metrics')) {
    function sa_get_disk_metrics($disk_check_path, $fallback_used_bytes = 0.0) {
        $disk_total = 0.0;
        $disk_free  = 0.0;
        $disabled   = array_map('trim', explode(',', strtolower((string)@ini_get('disable_functions'))));

        if (function_exists('disk_total_space') && !in_array('disk_total_space', $disabled)) {
            $t = @disk_total_space($disk_check_path);
            if ($t === false || $t <= 0) {
                $rp = @realpath($disk_check_path);
                if ($rp) {
                    $t = @disk_total_space($rp);
                }
            }
            if ($t !== false && $t > 0) {
                $disk_total = (float)$t;
            }
        }

        if (function_exists('disk_free_space') && !in_array('disk_free_space', $disabled)) {
            $f = @disk_free_space($disk_check_path);
            if ($f === false || $f <= 0) {
                $rp = @realpath($disk_check_path);
                if ($rp) {
                    $f = @disk_free_space($rp);
                }
            }
            if ($f !== false && $f > 0) {
                $disk_free = (float)$f;
            }
        }

        // Fallback 1: Linux 'df -P -k' falls disk_total_space in php.ini (disable_functions) gesperrt ist
        if ($disk_total <= 0 && function_exists('exec')) {
            if (!in_array('exec', $disabled)) {
                $df_out = array();
                $df_ret = 1;
                @exec('df -P -k ' . escapeshellarg($disk_check_path) . ' 2>/dev/null', $df_out, $df_ret);
                if ($df_ret === 0 && isset($df_out[1])) {
                    $parts = preg_split('/\s+/', trim($df_out[1]));
                    if (count($parts) >= 4 && is_numeric($parts[1]) && (float)$parts[1] > 0) {
                        $disk_total = (float)$parts[1] * 1024.0;
                        if ($disk_free <= 0 && is_numeric($parts[3])) {
                            $disk_free = (float)$parts[3] * 1024.0;
                        }
                    }
                }
            }
        }

        // Fallback 2: Wenn disk_total_space & exec deaktiviert sind, aber disk_free_space + physischer Scan vorliegen
        if ($disk_total <= 0 && $disk_free > 0 && $fallback_used_bytes > 0) {
            $disk_used  = (float)$fallback_used_bytes;
            $disk_total = $disk_used + $disk_free;
        } else {
            $disk_used = ($disk_total > 0) ? max(0.0, $disk_total - $disk_free) : 0.0;
        }

        $disk_used_percent = ($disk_total > 0) ? round(($disk_used / $disk_total) * 100, 1) : 0.0;

        return array(
            'path'              => $disk_check_path,
            'total_formatted'   => sa_format_bytes($disk_total),
            'used_formatted'    => sa_format_bytes($disk_used),
            'free_formatted'    => sa_format_bytes($disk_free),
            'used_percent'      => $disk_used_percent
        );
    }
}

$cache_file = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
$cache_lifetime = 86400; // 24 Stunden Cache
$force_refresh = (isset($_GET['force_refresh']) && $_GET['force_refresh'] == '1');

if (!$force_refresh && file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_lifetime)) {
    $cached_json = @file_get_contents($cache_file);
    if ($cached_json !== false && $cached_json !== '') {
        $cached_data = json_decode($cached_json, true);
        if (is_array($cached_data)) {
            if (isset($cached_data['disk']['total_formatted']) && $cached_data['disk']['total_formatted'] === '0 Bytes') {
                $cached_used_bytes = 0.0;
                if (isset($cached_data['categories']) && is_array($cached_data['categories'])) {
                    foreach ($cached_data['categories'] as $cat_item) {
                        if (isset($cat_item['actual_bytes'])) {
                            $cached_used_bytes += (float)$cat_item['actual_bytes'];
                        }
                    }
                }
                $cloud_root_path = MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR;
                $disk_check_path = is_dir($cloud_root_path) ? $cloud_root_path : MOVIES_PATH;
                $cached_data['disk'] = sa_get_disk_metrics($disk_check_path, $cached_used_bytes);
                @file_put_contents($cache_file, json_encode($cached_data));
            }
            $cached_data['from_cache'] = true;
            $cached_data['cache_age_minutes'] = round((time() - filemtime($cache_file)) / 60);
            echo json_encode($cached_data);
            p4c_close(DB_HOST);
            exit;
        }
    }
}

// -----------------------------------------------------------------------------
// 1. ONLINE MOVIES LOOKUP
// -----------------------------------------------------------------------------
$online_file_ids = array();
$rs_mo = p4c_query("SELECT `file_id`, `status` FROM `movies_online`;", __FILE__, __LINE__);
while ($mo_row = p4c_fetch_object($rs_mo)) {
    $online_file_ids[$mo_row->file_id] = $mo_row->status;
}

// -----------------------------------------------------------------------------
// 2. MOVIES STORAGE & FILE FORMAT BREAKDOWN
// -----------------------------------------------------------------------------
$movie_formats = array(
    'desktop_mp4' => array('count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'mobile_mp4'  => array('count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'mobile_webm' => array('count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'mobile_ogv'  => array('count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'thumbnails'  => array('count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'originals_other' => array('count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0)
);

$movie_statuses = array(
    'online'   => array('count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'drafts'   => array('count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'rejected' => array('count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0),
    'deleted'  => array('count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0)
);

$movie_desktop_sizes = array(); // file_id => actual or estimated desktop MP4 size in bytes
$physical_movie_folders_found = 0;

$rs_movies = p4c_query("SELECT `id`, `file_id`, `merchant_id`, `storage_location`, `filename`, `playtime_seconds`, `status`, `released`, `convert_status` FROM `movies`;", __FILE__, __LINE__);
$total_db_movies = p4c_num_rows($rs_movies);

while ($m = p4c_fetch_object($rs_movies)) {
    $playtime = (int)$m->playtime_seconds;
    $loc = ($m->storage_location !== '') ? $m->storage_location : MOVIES_DEFAULT_DIR;
    $folder = MOVIES_PATH . '/' . $loc . '/' . $m->merchant_id . '/' . $m->id;

    // Status-Kategorie bestimmen
    $status_key = 'drafts';
    if ($m->status === 'deleted') {
        $status_key = 'deleted';
    } elseif ($m->status === 'blocked' || $m->released == '2') {
        $status_key = 'rejected';
    } elseif (isset($online_file_ids[$m->file_id]) && $online_file_ids[$m->file_id] === 'active') {
        $status_key = 'online';
    } elseif ($m->released == '1' && $m->status === 'active') {
        $status_key = 'online';
    }

    $movie_statuses[$status_key]['count']++;
    $movie_statuses[$status_key]['playtime_sec'] += $playtime;

    // Theoretische Schätzung anhand FFmpeg Bitraten:
    // Desktop MP4: 8000 kbit/s Video + ~128 kbit/s Audio ≈ 1.016.000 Bytes/Sekunde
    // Mobile MP4:  1000 kbit/s Video + 196 kbit/s Audio  ≈ 149.500 Bytes/Sekunde
    // Mobile WebM: 1000 kbit/s Video + ~128 kbit/s Audio ≈ 141.000 Bytes/Sekunde
    // Mobile OGV:  1000 kbit/s Video + ~128 kbit/s Audio ≈ 141.000 Bytes/Sekunde
    // Thumbnails:  10 JPEGs à ~85 KB                     ≈ 850.000 Bytes pro Film
    $est_desktop = ($m->convert_status == '2') ? ($playtime * 1016000.0) : 0.0;
    $est_m_mp4   = ($m->convert_status == '2') ? ($playtime * 149500.0) : 0.0;
    $est_m_webm  = ($m->convert_status == '2') ? ($playtime * 141000.0) : 0.0;
    $est_m_ogv   = ($m->convert_status == '2') ? ($playtime * 141000.0) : 0.0;
    $est_thumbs  = ($m->convert_status == '2' && $playtime > 0) ? 850000.0 : 0.0;

    $movie_formats['desktop_mp4']['est_bytes'] += $est_desktop;
    $movie_formats['mobile_mp4']['est_bytes']  += $est_m_mp4;
    $movie_formats['mobile_webm']['est_bytes'] += $est_m_webm;
    $movie_formats['mobile_ogv']['est_bytes']  += $est_m_ogv;
    $movie_formats['thumbnails']['est_bytes']  += $est_thumbs;

    $est_folder_total = $est_desktop + $est_m_mp4 + $est_m_webm + $est_m_ogv + $est_thumbs;
    $movie_statuses[$status_key]['est_bytes'] += $est_folder_total;

    $actual_desktop_bytes = 0.0;
    $folder_bytes = 0.0;

    if (is_dir($folder)) {
        $physical_movie_folders_found++;
        $files = @scandir($folder);
        if ($files !== false) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $fpath = $folder . '/' . $f;
                if (!is_file($fpath)) {
                    continue;
                }
                $fsize = (float)@filesize($fpath);
                $folder_bytes += $fsize;
                $lower_f = strtolower($f);
                $ext = pathinfo($lower_f, PATHINFO_EXTENSION);

                if (strpos($lower_f, 'thumb_') === 0) {
                    $movie_formats['thumbnails']['count']++;
                    $movie_formats['thumbnails']['bytes'] += $fsize;
                } elseif (strpos($lower_f, 'm_') === 0 && $ext === 'mp4') {
                    $movie_formats['mobile_mp4']['count']++;
                    $movie_formats['mobile_mp4']['bytes'] += $fsize;
                } elseif (strpos($lower_f, 'm_') === 0 && $ext === 'webm') {
                    $movie_formats['mobile_webm']['count']++;
                    $movie_formats['mobile_webm']['bytes'] += $fsize;
                } elseif (strpos($lower_f, 'm_') === 0 && $ext === 'ogv') {
                    $movie_formats['mobile_ogv']['count']++;
                    $movie_formats['mobile_ogv']['bytes'] += $fsize;
                } elseif ($ext === 'mp4' && strpos($lower_f, 'neu_') !== 0) {
                    $movie_formats['desktop_mp4']['count']++;
                    $movie_formats['desktop_mp4']['bytes'] += $fsize;
                    $actual_desktop_bytes = $fsize;
                } else {
                    $movie_formats['originals_other']['count']++;
                    $movie_formats['originals_other']['bytes'] += $fsize;
                }
            }
        }
        $movie_statuses[$status_key]['bytes'] += $folder_bytes;
    }

    $movie_desktop_sizes[$m->file_id] = array(
        'actual'      => $actual_desktop_bytes,
        'estimated'   => $est_desktop,
        'playtime'    => $playtime
    );
}

// -----------------------------------------------------------------------------
// 3. OTHER STORAGE DIRECTORIES (PHOTO ALBUMS, ADS, MERCHANT FILES)
// -----------------------------------------------------------------------------
$photo_albums_dir = PHOTO_ALBUMS_PATH . '/' . PHOTO_ALBUMS_DEFAULT_DIR;
$photo_files_count = 0;
$photo_albums_bytes = sa_scan_dir_bytes($photo_albums_dir, $photo_files_count);

$ads_dir = ADS_PATH . '/' . ADS_DEFAULT_DIR;
$ads_files_count = 0;
$ads_bytes = sa_scan_dir_bytes($ads_dir, $ads_files_count);

$merchant_dir = PROFILE_IMAGE_PATH . '/' . MERCHANT_DEFAULT_DIR;
$merchant_files_count = 0;
$merchant_bytes = sa_scan_dir_bytes($merchant_dir, $merchant_files_count);

// DB Counts für Fotoalben & Banner
$rs_pa_cnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `status` != 'deleted';", __FILE__, __LINE__);
$db_photo_albums_count = (int)p4c_fetch_object($rs_pa_cnt)->cnt;

$rs_pao_cnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_online`;", __FILE__, __LINE__);
$db_photo_albums_online_count = (int)p4c_fetch_object($rs_pao_cnt)->cnt;

$rs_photos_cnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_photos`;", __FILE__, __LINE__);
$db_photos_count = (int)p4c_fetch_object($rs_photos_cnt)->cnt;

$rs_ads_cnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `ads_media`;", __FILE__, __LINE__);
$db_ads_count = (int)p4c_fetch_object($rs_ads_cnt)->cnt;

// Gesamtgröße Filme (Physisch vs. Geschätzt)
$total_movies_actual_bytes = 0.0;
$total_movies_est_bytes = 0.0;
foreach ($movie_formats as $fmt_key => $fmt_val) {
    $total_movies_actual_bytes += $fmt_val['bytes'];
    $total_movies_est_bytes += $fmt_val['est_bytes'];
}

$is_local_simulation = ($physical_movie_folders_found == 0 && $total_db_movies > 0);
$effective_movies_bytes = $is_local_simulation ? $total_movies_est_bytes : $total_movies_actual_bytes;

// Einsparpotenzial durch veraltete Formate (WebM + OGV sowie optional Mobile MP4)
$legacy_webm_ogv_bytes = $is_local_simulation
    ? ($movie_formats['mobile_webm']['est_bytes'] + $movie_formats['mobile_ogv']['est_bytes'])
    : ($movie_formats['mobile_webm']['bytes'] + $movie_formats['mobile_ogv']['bytes']);

$mobile_mp4_bytes = $is_local_simulation
    ? $movie_formats['mobile_mp4']['est_bytes']
    : $movie_formats['mobile_mp4']['bytes'];

// Server Disk Path
$cloud_root_path = MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR;
$disk_check_path = is_dir($cloud_root_path) ? $cloud_root_path : MOVIES_PATH;

// -----------------------------------------------------------------------------
// 4. STREAMING & DOWNLOAD TRAFFIC (LETZTE 6 MONATE)
// -----------------------------------------------------------------------------
$six_months_ago = date("Y-m-d H:i:s", strtotime("-6 months"));

$rs_access_6m = p4c_query("
    SELECT 
        `movie_id`,
        `user_id`,
        `buy_as`,
        `buy_timestamp`,
        `access_token_datetime`,
        GREATEST(
            IFNULL(`buy_timestamp`, '0000-00-00 00:00:00'),
            IFNULL(`access_token_datetime`, '0000-00-00 00:00:00')
        ) AS `activity_date`
    FROM `movies_access`
    WHERE `buy_timestamp` >= '" . p4c_escape_string($six_months_ago) . "'
       OR `access_token_datetime` >= '" . p4c_escape_string($six_months_ago) . "'
    ORDER BY `activity_date` DESC;
", __FILE__, __LINE__);

$traffic_6m = array(
    'streaming_count' => 0,
    'download_count'  => 0,
    'total_count'     => 0,
    'unique_users'    => array(),
    'unique_movies'   => array(),
    'streaming_bytes' => 0.0,
    'download_bytes'  => 0.0,
    'total_bytes'     => 0.0,
    'streaming_sec'   => 0,
    'download_sec'    => 0
);

$monthly_breakdown = array();
for ($i = 0; $i < 6; $i++) {
    $m_key = date("Y-m", strtotime("-$i months"));
    $m_label = date("m / Y", strtotime("-$i months"));
    $monthly_breakdown[$m_key] = array(
        'month_key'       => $m_key,
        'month_label'     => $m_label,
        'streaming_count' => 0,
        'download_count'  => 0,
        'streaming_bytes' => 0.0,
        'download_bytes'  => 0.0,
        'total_bytes'     => 0.0,
        'playtime_hours'  => 0.0
    );
}

while ($acc = p4c_fetch_object($rs_access_6m)) {
    $fid = $acc->movie_id;
    $m_size_info = isset($movie_desktop_sizes[$fid]) ? $movie_desktop_sizes[$fid] : array('actual' => 0.0, 'estimated' => 0.0, 'playtime' => 0);
    
    // Dateigröße bestimmen (echte Größe auf Live-Server, Fallback auf Bitraten-Schätzung)
    $file_bytes = ($m_size_info['actual'] > 0) ? $m_size_info['actual'] : $m_size_info['estimated'];
    $playtime_s = $m_size_info['playtime'];

    $traffic_6m['total_count']++;
    if ($acc->user_id > 0) {
        $traffic_6m['unique_users'][(int)$acc->user_id] = true;
    }
    if ($fid !== '') {
        $traffic_6m['unique_movies'][$fid] = true;
    }

    $month_key = substr($acc->activity_date, 0, 7);
    if (!isset($monthly_breakdown[$month_key])) {
        $monthly_breakdown[$month_key] = array(
            'month_key'       => $month_key,
            'month_label'     => date("m / Y", strtotime($acc->activity_date)),
            'streaming_count' => 0,
            'download_count'  => 0,
            'streaming_bytes' => 0.0,
            'download_bytes'  => 0.0,
            'total_bytes'     => 0.0,
            'playtime_hours'  => 0.0
        );
    }

    if ($acc->buy_as === 'download') {
        $traffic_6m['download_count']++;
        $traffic_6m['download_bytes'] += $file_bytes;
        $traffic_6m['download_sec']   += $playtime_s;
        $monthly_breakdown[$month_key]['download_count']++;
        $monthly_breakdown[$month_key]['download_bytes'] += $file_bytes;
    } else {
        $traffic_6m['streaming_count']++;
        $traffic_6m['streaming_bytes'] += $file_bytes;
        $traffic_6m['streaming_sec']   += $playtime_s;
        $monthly_breakdown[$month_key]['streaming_count']++;
        $monthly_breakdown[$month_key]['streaming_bytes'] += $file_bytes;
    }

    $traffic_6m['total_bytes'] += $file_bytes;
    $monthly_breakdown[$month_key]['total_bytes'] += $file_bytes;
    $monthly_breakdown[$month_key]['playtime_hours'] += ($playtime_s / 3600.0);
}

ksort($monthly_breakdown);
$monthly_rows = array();
foreach (array_reverse($monthly_breakdown, true) as $mk => $mdata) {
    $monthly_rows[] = array(
        'month_key'             => $mdata['month_key'],
        'month_label'           => $mdata['month_label'],
        'streaming_count'       => $mdata['streaming_count'],
        'download_count'        => $mdata['download_count'],
        'total_count'           => $mdata['streaming_count'] + $mdata['download_count'],
        'playtime_hours'        => number_format($mdata['playtime_hours'], 1, ',', '.') . ' Std.',
        'streaming_formatted'   => sa_format_bytes($mdata['streaming_bytes']),
        'download_formatted'    => sa_format_bytes($mdata['download_bytes']),
        'total_bytes'           => $mdata['total_bytes'],
        'total_formatted'       => sa_format_bytes($mdata['total_bytes']),
        'realistic_2x_formatted'=> sa_format_bytes($mdata['total_bytes'] * 2.0)
    );
}

$avg_monthly_bytes_1x  = $traffic_6m['total_bytes'] / 6.0;
$avg_monthly_bytes_2x  = $avg_monthly_bytes_1x * 2.0; // Inkl. Spulen / Mehrfach-Ansicht

// -----------------------------------------------------------------------------
// 5. CLOUD PROVIDER KOSTEN-KALKULATION
// -----------------------------------------------------------------------------
$total_storage_bytes_now = $effective_movies_bytes + $photo_albums_bytes + $ads_bytes + $merchant_bytes;
$total_storage_bytes_cleaned = max(0.0, $total_storage_bytes_now - $legacy_webm_ogv_bytes);

$tb_now     = max(0.1, $total_storage_bytes_now / 1099511627776.0);
$tb_cleaned = max(0.1, $total_storage_bytes_cleaned / 1099511627776.0);
$tb_traffic = $avg_monthly_bytes_2x / 1099511627776.0;

$providers = array(
    array(
        'name'              => 'Backblaze B2 (Object Storage)',
        'rate_info'         => 'ca. 6,00 $ / TB Speicher | Traffic bis 3x Speichervolumen kostenlos',
        'adult_policy'      => 'Erlaubt (legaler Adult-Content / BRAM-konform)',
        'cost_now_eur'      => number_format(($tb_now * 6.0) + (max(0.0, $tb_traffic - ($tb_now * 3.0)) * 10.0), 2, ',', '.') . ' $ / Monat',
        'cost_cleaned_eur'  => number_format(($tb_cleaned * 6.0) + (max(0.0, $tb_traffic - ($tb_cleaned * 3.0)) * 10.0), 2, ',', '.') . ' $ / Monat'
    ),
    array(
        'name'              => 'OVHcloud (Object Storage Standard)',
        'rate_info'         => 'ca. 7,50 € / TB Speicher | Ausgehender Traffic (Egress) 0,00 €',
        'adult_policy'      => 'Erlaubt (EU-Standorte DE / FR)',
        'cost_now_eur'      => number_format($tb_now * 7.50, 2, ',', '.') . ' € / Monat',
        'cost_cleaned_eur'  => number_format($tb_cleaned * 7.50, 2, ',', '.') . ' € / Monat'
    ),
    array(
        'name'              => 'Wasabi (Hot Cloud Storage)',
        'rate_info'         => 'ca. 6,99 $ / TB Speicher | Traffic kostenlos (Fair-Use bis 1x Speicher)',
        'adult_policy'      => 'Erlaubt (90 Tage Mindest-Speicherdauer beachten)',
        'cost_now_eur'      => number_format($tb_now * 6.99, 2, ',', '.') . ' $ / Monat',
        'cost_cleaned_eur'  => number_format($tb_cleaned * 6.99, 2, ',', '.') . ' $ / Monat'
    ),
    array(
        'name'              => 'Bunny.net (Bunny Storage + Volume CDN)',
        'rate_info'         => 'ca. 10,00 $ / TB Speicher + 5,00 $ / TB Streaming-Traffic (inkl. SFTP)',
        'adult_policy'      => 'Explizit erlaubt (Spezialist für Adult-Streaming)',
        'cost_now_eur'      => number_format(($tb_now * 10.0) + ($tb_traffic * 5.0), 2, ',', '.') . ' $ / Monat',
        'cost_cleaned_eur'  => number_format(($tb_cleaned * 10.0) + ($tb_traffic * 5.0), 2, ',', '.') . ' $ / Monat'
    )
);

// -----------------------------------------------------------------------------
// 6. RESPONSE AUFBEREITEN
// -----------------------------------------------------------------------------
$duration_ms = round((microtime(true) - $start_time) * 1000);

$response = array(
    'from_cache'            => false,
    'cache_age_minutes'     => 0,
    'generated_at'          => date("d.m.Y H:i:s"),
    'duration_ms'           => $duration_ms,
    'is_local_simulation'   => $is_local_simulation,
    'physical_movie_folders'=> $physical_movie_folders_found,
    'total_db_movies'       => $total_db_movies,

    'disk' => sa_get_disk_metrics($disk_check_path, $total_storage_bytes_now),

    'summary' => array(
        'total_cloud_formatted'         => sa_format_bytes($total_storage_bytes_now),
        'movies_total_formatted'        => sa_format_bytes($effective_movies_bytes),
        'legacy_savings_formatted'      => sa_format_bytes($legacy_webm_ogv_bytes),
        'legacy_with_m_mp4_formatted'   => sa_format_bytes($legacy_webm_ogv_bytes + $mobile_mp4_bytes),
        'cleaned_cloud_formatted'       => sa_format_bytes($total_storage_bytes_cleaned)
    ),

    'categories' => array(
        array(
            'name'          => 'Videos (cloud_storage/{merchant_id}/{movie_id}/)',
            'db_info'       => number_format($total_db_movies, 0, ',', '.') . ' Filme in DB (' . number_format($movie_statuses['online']['count'], 0, ',', '.') . ' online)',
            'files_count'   => number_format(
                $movie_formats['desktop_mp4']['count'] +
                $movie_formats['mobile_mp4']['count'] +
                $movie_formats['mobile_webm']['count'] +
                $movie_formats['mobile_ogv']['count'] +
                $movie_formats['thumbnails']['count'] +
                $movie_formats['originals_other']['count'], 0, ',', '.'
            ) . ' Dateien',
            'actual_bytes'  => $total_movies_actual_bytes,
            'actual_fmt'    => sa_format_bytes($total_movies_actual_bytes),
            'est_fmt'       => sa_format_bytes($total_movies_est_bytes),
            'effective_fmt' => sa_format_bytes($effective_movies_bytes)
        ),
        array(
            'name'          => 'Fotoalben (cloud_storage/photo_albums/)',
            'db_info'       => number_format($db_photo_albums_count, 0, ',', '.') . ' Alben (' . number_format($db_photo_albums_online_count, 0, ',', '.') . ' online, ' . number_format($db_photos_count, 0, ',', '.') . ' Fotos)',
            'files_count'   => number_format($photo_files_count, 0, ',', '.') . ' Dateien',
            'actual_bytes'  => $photo_albums_bytes,
            'actual_fmt'    => sa_format_bytes($photo_albums_bytes),
            'est_fmt'       => sa_format_bytes($photo_albums_bytes),
            'effective_fmt' => sa_format_bytes($photo_albums_bytes)
        ),
        array(
            'name'          => 'Werbebanner (cloud_storage/ads/)',
            'db_info'       => number_format($db_ads_count, 0, ',', '.') . ' Banner in DB',
            'files_count'   => number_format($ads_files_count, 0, ',', '.') . ' Dateien',
            'actual_bytes'  => $ads_bytes,
            'actual_fmt'    => sa_format_bytes($ads_bytes),
            'est_fmt'       => sa_format_bytes($ads_bytes),
            'effective_fmt' => sa_format_bytes($ads_bytes)
        ),
        array(
            'name'          => 'Händler- & Profilbilder (priv/merchant_files/)',
            'db_info'       => 'Ausweise, Verifizierungen & Profilbilder',
            'files_count'   => number_format($merchant_files_count, 0, ',', '.') . ' Dateien',
            'actual_bytes'  => $merchant_bytes,
            'actual_fmt'    => sa_format_bytes($merchant_bytes),
            'est_fmt'       => sa_format_bytes($merchant_bytes),
            'effective_fmt' => sa_format_bytes($merchant_bytes)
        )
    ),

    'formats' => array(
        array(
            'type'          => 'Desktop Hauptvideo ({id}_{file_id}.mp4)',
            'codec'         => 'H.264 (libx264, 8000 kbit/s, Originalauflösung)',
            'usage_status'  => 'AKTIV GENUTZT (Streaming & Download)',
            'badge_color'   => '#008000',
            'count'         => $movie_formats['desktop_mp4']['count'],
            'actual_fmt'    => sa_format_bytes($movie_formats['desktop_mp4']['bytes']),
            'est_fmt'       => sa_format_bytes($movie_formats['desktop_mp4']['est_bytes']),
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_formats['desktop_mp4']['est_bytes'] : $movie_formats['desktop_mp4']['bytes'])
        ),
        array(
            'type'          => 'Mobile MP4 (m_{id}_{file_id}.mp4)',
            'codec'         => 'H.264 (libx264, 1000 kbit/s, 640px Breite)',
            'usage_status'  => 'INAKTIV (Player-Bug: wird in movie_player.php ignoriert)',
            'badge_color'   => '#d05c00',
            'count'         => $movie_formats['mobile_mp4']['count'],
            'actual_fmt'    => sa_format_bytes($movie_formats['mobile_mp4']['bytes']),
            'est_fmt'       => sa_format_bytes($movie_formats['mobile_mp4']['est_bytes']),
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_formats['mobile_mp4']['est_bytes'] : $movie_formats['mobile_mp4']['bytes'])
        ),
        array(
            'type'          => 'Mobile WebM (m_{id}_{file_id}.webm)',
            'codec'         => 'VP8 / Vorbis (libvpx, 1000 kbit/s, 640px Breite)',
            'usage_status'  => 'VERALTET / NUTZLOS (Kann gelöscht werden)',
            'badge_color'   => '#c00000',
            'count'         => $movie_formats['mobile_webm']['count'],
            'actual_fmt'    => sa_format_bytes($movie_formats['mobile_webm']['bytes']),
            'est_fmt'       => sa_format_bytes($movie_formats['mobile_webm']['est_bytes']),
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_formats['mobile_webm']['est_bytes'] : $movie_formats['mobile_webm']['bytes'])
        ),
        array(
            'type'          => 'Mobile OGV (m_{id}_{file_id}.ogv)',
            'codec'         => 'Theora / Vorbis (libtheora, 1000 kbit/s, 640px Breite)',
            'usage_status'  => 'VERALTET / NUTZLOS (Kann gelöscht werden)',
            'badge_color'   => '#c00000',
            'count'         => $movie_formats['mobile_ogv']['count'],
            'actual_fmt'    => sa_format_bytes($movie_formats['mobile_ogv']['bytes']),
            'est_fmt'       => sa_format_bytes($movie_formats['mobile_ogv']['est_bytes']),
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_formats['mobile_ogv']['est_bytes'] : $movie_formats['mobile_ogv']['bytes'])
        ),
        array(
            'type'          => 'Vorschaubilder & Covers (thumb_*.jpg / .png)',
            'codec'         => 'JPEG / PNG (10 Auto-Thumbs + Custom FSK16/18)',
            'usage_status'  => 'AKTIV GENUTZT (Vorschau & Player-Poster)',
            'badge_color'   => '#008000',
            'count'         => $movie_formats['thumbnails']['count'],
            'actual_fmt'    => sa_format_bytes($movie_formats['thumbnails']['bytes']),
            'est_fmt'       => sa_format_bytes($movie_formats['thumbnails']['est_bytes']),
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_formats['thumbnails']['est_bytes'] : $movie_formats['thumbnails']['bytes'])
        ),
        array(
            'type'          => 'Unkonvertierte Originale / Temporäre Dateien',
            'codec'         => 'Rohdaten vor/während Konvertierung',
            'usage_status'  => 'TEMPORÄR',
            'badge_color'   => '#666666',
            'count'         => $movie_formats['originals_other']['count'],
            'actual_fmt'    => sa_format_bytes($movie_formats['originals_other']['bytes']),
            'est_fmt'       => sa_format_bytes($movie_formats['originals_other']['est_bytes']),
            'effective_fmt' => sa_format_bytes($movie_formats['originals_other']['bytes'])
        )
    ),

    'statuses' => array(
        array(
            'label'         => 'Filme Online (Veröffentlicht)',
            'count'         => $movie_statuses['online']['count'],
            'hours'         => number_format($movie_statuses['online']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_statuses['online']['est_bytes'] : $movie_statuses['online']['bytes'])
        ),
        array(
            'label'         => 'Filme in Planung (Entwürfe)',
            'count'         => $movie_statuses['drafts']['count'],
            'hours'         => number_format($movie_statuses['drafts']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_statuses['drafts']['est_bytes'] : $movie_statuses['drafts']['bytes'])
        ),
        array(
            'label'         => 'Abgelehnte & Gesperrte Filme',
            'count'         => $movie_statuses['rejected']['count'],
            'hours'         => number_format($movie_statuses['rejected']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_statuses['rejected']['est_bytes'] : $movie_statuses['rejected']['bytes'])
        ),
        array(
            'label'         => 'Soft-Gelöschte Filme (in Schutzfrist)',
            'count'         => $movie_statuses['deleted']['count'],
            'hours'         => number_format($movie_statuses['deleted']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
            'effective_fmt' => sa_format_bytes($is_local_simulation ? $movie_statuses['deleted']['est_bytes'] : $movie_statuses['deleted']['bytes'])
        )
    ),

    'traffic' => array(
        'streaming_count_6m'        => number_format($traffic_6m['streaming_count'], 0, ',', '.'),
        'download_count_6m'         => number_format($traffic_6m['download_count'], 0, ',', '.'),
        'total_count_6m'            => number_format($traffic_6m['total_count'], 0, ',', '.'),
        'unique_users_6m'           => number_format(count($traffic_6m['unique_users']), 0, ',', '.'),
        'unique_movies_6m'          => number_format(count($traffic_6m['unique_movies']), 0, ',', '.'),
        'streaming_hours_6m'        => number_format($traffic_6m['streaming_sec'] / 3600.0, 1, ',', '.') . ' Std.',
        'download_hours_6m'         => number_format($traffic_6m['download_sec'] / 3600.0, 1, ',', '.') . ' Std.',
        'streaming_bytes_6m_fmt'    => sa_format_bytes($traffic_6m['streaming_bytes']),
        'download_bytes_6m_fmt'     => sa_format_bytes($traffic_6m['download_bytes']),
        'total_bytes_6m_fmt'        => sa_format_bytes($traffic_6m['total_bytes']),
        'avg_monthly_1x_fmt'        => sa_format_bytes($avg_monthly_bytes_1x),
        'avg_monthly_2x_fmt'        => sa_format_bytes($avg_monthly_bytes_2x),
        'monthly_rows'              => $monthly_rows
    ),

    'providers' => $providers
);

@file_put_contents($cache_file, json_encode($response));

echo json_encode($response);

p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
exit;
?>
