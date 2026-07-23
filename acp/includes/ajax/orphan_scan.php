<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 */

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

header('Content-Type: application/json; charset=utf-8');

if (is_logged_in('acp') === false) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(array('error' => 'Nicht autorisiert. Bitte melden Sie sich an.'));
    exit;   
}

$start_time = microtime(true);

// 1. Helper function for folder size
if (!function_exists('scan_folder_size')) {
    function scan_folder_size($dir) {
        $size = 0;
        if (!is_dir($dir)) {
            return is_file($dir) ? filesize($dir) : 0;
        }
        $files = @scandir($dir);
        if ($files === false) return 0;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $size += scan_folder_size($path);
            } else {
                $size += filesize($path);
            }
        }
        return $size;
    }
}

// 2. Helper function to format bytes (in Sie-Form/German style)
if (!function_exists('format_bytes')) {
    function format_bytes($bytes) {
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2, ',', '.') . ' TB';
        } elseif ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' Bytes';
    }
}

// 3. Cache Database references into memory arrays (very fast, O(1) lookups)
$merchants = array();
$rs_merchants = p4c_query("SELECT `id` FROM `merchants`;", __FILE__, __LINE__);
while ($row = p4c_fetch_object($rs_merchants)) {
    $merchants[intval($row->id)] = true;
}

$movies = array();
$rs_movies = p4c_query("SELECT `id`, `merchant_id`, `status` FROM `movies`;", __FILE__, __LINE__);
while ($row = p4c_fetch_object($rs_movies)) {
    $movies[intval($row->merchant_id)][intval($row->id)] = $row->status;
}

$albums = array();
$rs_albums = p4c_query("SELECT `id`, `merchant_id`, `status` FROM `photo_albums`;", __FILE__, __LINE__);
while ($row = p4c_fetch_object($rs_albums)) {
    $albums[intval($row->merchant_id)][intval($row->id)] = $row->status;
}

$sites = array();
$rs_sites = p4c_query("SELECT `id` FROM `sites`;", __FILE__, __LINE__);
while ($row = p4c_fetch_object($rs_sites)) {
    $sites[intval($row->id)] = true;
}

$ads = array();
$rs_ads = p4c_query("SELECT `site_id`, `filename`, `new_filename` FROM `ads_media`;", __FILE__, __LINE__);
while ($row = p4c_fetch_object($rs_ads)) {
    $site_id = intval($row->site_id);
    if ($row->filename !== '') {
        $ads[$site_id][$row->filename] = true;
    }
    if ($row->new_filename !== '') {
        $ads[$site_id][$row->new_filename] = true;
    }
}

// 4. File scan logic
$base_path = MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR;
$base_path = rtrim($base_path, '/\\');

$orphans = array();
$total_size = 0;

if (!is_dir($base_path)) {
    echo json_encode(array('error' => 'Das Verzeichnis "' . htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8') . '" existiert nicht oder ist nicht lesbar.'));
    exit;
}

$items = @scandir($base_path);
if ($items !== false) {
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === 'lost+found') {
            continue;
        }
        
        $item_path = $base_path . '/' . $item;
        
        if ($item === 'photo_albums') {
            // Scan photo albums
            if (is_dir($item_path)) {
                $sub_items = @scandir($item_path);
                if ($sub_items !== false) {
                    foreach ($sub_items as $sub_item) {
                        if ($sub_item === '.' || $sub_item === '..') continue;
                        $sub_item_path = $item_path . '/' . $sub_item;
                        
                        if (is_dir($sub_item_path) && is_numeric($sub_item)) {
                            $merchant_id = intval($sub_item);
                            if (!isset($merchants[$merchant_id])) {
                                // Merchant folder in photo_albums is orphaned
                                $size = scan_folder_size($sub_item_path);
                                $orphans[] = array(
                                    'path' => 'cloud_storage/photo_albums/' . $sub_item,
                                    'type' => 'Fotoalben Händler-Ordner',
                                    'reason' => 'Der Händler (ID ' . $merchant_id . ') existiert nicht in der Datenbank.',
                                    'size' => $size,
                                    'size_formatted' => format_bytes($size)
                                );
                                $total_size += $size;
                            } else {
                                $albums_in_dir = @scandir($sub_item_path);
                                if ($albums_in_dir !== false) {
                                    foreach ($albums_in_dir as $album_dir) {
                                        if ($album_dir === '.' || $album_dir === '..') continue;
                                        $album_path = $sub_item_path . '/' . $album_dir;
                                        
                                        if (is_dir($album_path) && is_numeric($album_dir)) {
                                            $album_id = intval($album_dir);
                                            $is_orphan = false;
                                            $reason = '';
                                            
                                            if (!isset($albums[$merchant_id][$album_id])) {
                                                $is_orphan = true;
                                                $reason = 'Das Fotoalbum (ID ' . $album_id . ') existiert nicht in der Datenbank.';
                                            }
                                            
                                            if ($is_orphan) {
                                                $size = scan_folder_size($album_path);
                                                $orphans[] = array(
                                                    'path' => 'cloud_storage/photo_albums/' . $sub_item . '/' . $album_dir,
                                                    'type' => 'Fotoalbum-Ordner',
                                                    'reason' => $reason,
                                                    'size' => $size,
                                                    'size_formatted' => format_bytes($size)
                                                );
                                                $total_size += $size;
                                            }
                                        } else {
                                            // Non-numeric item in merchant's photo album folder
                                            $size = scan_folder_size($album_path);
                                            $orphans[] = array(
                                                'path' => 'cloud_storage/photo_albums/' . $sub_item . '/' . $album_dir,
                                                'type' => is_dir($album_path) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                                'reason' => 'Ungültige Verzeichnisstruktur im Fotoalben-Händler-Ordner.',
                                                'size' => $size,
                                                'size_formatted' => format_bytes($size)
                                            );
                                            $total_size += $size;
                                        }
                                    }
                                }
                            }
                        } else {
                            // Non-numeric folder or file directly in photo_albums root
                            $size = scan_folder_size($sub_item_path);
                            $orphans[] = array(
                                'path' => 'cloud_storage/photo_albums/' . $sub_item,
                                'type' => is_dir($sub_item_path) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                'reason' => 'Ungültige Verzeichnisstruktur im Fotoalben-Hauptordner.',
                                'size' => $size,
                                'size_formatted' => format_bytes($size)
                                                      );
                            $total_size += $size;
                        }
                    }
                }
            }
        } elseif ($item === 'ads') {
            // Scan ads
            if (is_dir($item_path)) {
                $sub_items = @scandir($item_path);
                if ($sub_items !== false) {
                    foreach ($sub_items as $sub_item) {
                        if ($sub_item === '.' || $sub_item === '..') continue;
                        $sub_item_path = $item_path . '/' . $sub_item;
                        
                        if (is_dir($sub_item_path) && is_numeric($sub_item)) {
                            $site_id = intval($sub_item);
                            if (!isset($sites[$site_id])) {
                                // Entire site folder is orphaned
                                $size = scan_folder_size($sub_item_path);
                                $orphans[] = array(
                                    'path' => 'cloud_storage/ads/' . $sub_item,
                                    'type' => 'Werbebanner Webseiten-Ordner',
                                    'reason' => 'Die Webseite (ID ' . $site_id . ') existiert nicht in der Datenbank.',
                                    'size' => $size,
                                    'size_formatted' => format_bytes($size)
                                );
                                $total_size += $size;
                            } else {
                                // Check files inside site folder
                                $files = @scandir($sub_item_path);
                                if ($files !== false) {
                                    foreach ($files as $file) {
                                        if ($file === '.' || $file === '..') continue;
                                        $file_path = $sub_item_path . '/' . $file;
                                        
                                        if (is_file($file_path)) {
                                            if (!isset($ads[$site_id][$file])) {
                                                $size = filesize($file_path);
                                                $orphans[] = array(
                                                    'path' => 'cloud_storage/ads/' . $sub_item . '/' . $file,
                                                    'type' => 'Werbebanner-Datei',
                                                    'reason' => 'Die Datei ist nicht der Webseite zugeordnet oder registriert.',
                                                    'size' => $size,
                                                    'size_formatted' => format_bytes($size)
                                                );
                                                $total_size += $size;
                                            }
                                        } else {
                                            // Subdirectory inside ads/[site_id]
                                            $size = scan_folder_size($file_path);
                                            $orphans[] = array(
                                                'path' => 'cloud_storage/ads/' . $sub_item . '/' . $file,
                                                'type' => 'Unbekannter Ordner',
                                                'reason' => 'Unerwartetes Verzeichnis im Webseiten-Banner-Ordner.',
                                                'size' => $size,
                                                'size_formatted' => format_bytes($size)
                                            );
                                            $total_size += $size;
                                        }
                                    }
                                }
                            }
                        } else {
                            // Non-numeric item in ads root
                            $size = scan_folder_size($sub_item_path);
                            $orphans[] = array(
                                'path' => 'cloud_storage/ads/' . $sub_item,
                                'type' => is_dir($sub_item_path) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                'reason' => 'Ungültige Struktur im Banner-Hauptordner.',
                                'size' => $size,
                                'size_formatted' => format_bytes($size)
                            );
                            $total_size += $size;
                        }
                    }
                }
            }
        } else {
            // Check if merchant folder (numeric)
            if (is_dir($item_path) && is_numeric($item)) {
                $merchant_id = intval($item);
                if (!isset($merchants[$merchant_id])) {
                    // Entire merchant folder is orphaned!
                    $size = scan_folder_size($item_path);
                    $orphans[] = array(
                        'path' => 'cloud_storage/' . $item,
                        'type' => 'Händler-Ordner',
                        'reason' => 'Der Händler (ID ' . $merchant_id . ') existiert nicht in der Datenbank.',
                        'size' => $size,
                        'size_formatted' => format_bytes($size)
                    );
                    $total_size += $size;
                } else {
                    // Scan merchant's movies
                    $movies_in_dir = @scandir($item_path);
                    if ($movies_in_dir !== false) {
                        foreach ($movies_in_dir as $movie_dir) {
                            if ($movie_dir === '.' || $movie_dir === '..') continue;
                            $movie_path = $item_path . '/' . $movie_dir;
                            
                            if (is_dir($movie_path) && is_numeric($movie_dir)) {
                                $movie_id = intval($movie_dir);
                                $is_orphan = false;
                                $reason = '';
                                
                                if (!isset($movies[$merchant_id][$movie_id])) {
                                    $is_orphan = true;
                                    $reason = 'Der Film (ID ' . $movie_id . ') existiert nicht in der Datenbank.';
                                }
                                
                                if ($is_orphan) {
                                    $size = scan_folder_size($movie_path);
                                    $orphans[] = array(
                                        'path' => 'cloud_storage/' . $item . '/' . $movie_dir,
                                        'type' => 'Film-Ordner',
                                        'reason' => $reason,
                                        'size' => $size,
                                        'size_formatted' => format_bytes($size)
                                    );
                                    $total_size += $size;
                                }
                            } else {
                                // Non-numeric item in merchant folder
                                $size = scan_folder_size($movie_path);
                                $orphans[] = array(
                                    'path' => 'cloud_storage/' . $item . '/' . $movie_dir,
                                    'type' => is_dir($movie_path) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                    'reason' => 'Ungültige Verzeichnisstruktur im Händler-Ordner.',
                                    'size' => $size,
                                    'size_formatted' => format_bytes($size)
                                );
                                $total_size += $size;
                            }
                        }
                    }
                }
            } else {
                // Unknown file or directory directly in cloud_storage root
                $size = scan_folder_size($item_path);
                $orphans[] = array(
                    'path' => 'cloud_storage/' . $item,
                    'type' => is_dir($item_path) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                    'reason' => 'Verzeichnis oder Datei befindet sich direkt im Hauptordner.',
                    'size' => $size,
                    'size_formatted' => format_bytes($size)
                );
                $total_size += $size;
            }
        }
    }
}

$duration = round((microtime(true) - $start_time) * 1000, 2);

echo json_encode(array(
    'status' => 'success',
    'duration_ms' => $duration,
    'total_count' => count($orphans),
    'total_size_formatted' => format_bytes($total_size),
    'data' => $orphans
));

p4c_close(DB_HOST);

// Log errors if any
p4c_errorlog(error_get_last());
