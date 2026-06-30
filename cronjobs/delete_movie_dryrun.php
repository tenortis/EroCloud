#!/usr/bin/php
<?php

// Diese Datei simuliert das Loeschen von Filmen nach den neuen 5 Regeln (Dry-Run).
// Es werden KEINE Dateien geloescht und KEINE Datenbank-Aenderungen vorgenommen.

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

// Zeichencodierung setzen
ini_set('default_charset','UTF-8');
mb_internal_encoding('UTF-8');

// Ermittelt die Groesse vom Inhalt eines Verzeichnis
function getFolderSize($folderPath) {
    $size = 0;
    if (!is_dir($folderPath)) {
        return 0;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $size += $file->getSize();
    }
    return $size;
}

echo "======================================================================\n";
echo "STARTING CORRECTED DRY-RUN SIMULATION\n";
echo "======================================================================\n\n";

$now_minus_30 = date("Y-m-d H:i:s", strtotime("-30 days"));
$now_minus_365 = date("Y-m-d H:i:s", strtotime("-365 days"));
$now_minus_2years = date("Y-m-d H:i:s", strtotime("-2 years"));
$now_minus_180days = date("Y-m-d H:i:s", strtotime("-180 days"));

// Pruefung der Schutzfristen direkt im SQL Query
$sql_query = "
    SELECT * FROM `movies` 
    WHERE 
      -- Regel 1: Nie gekauft & Soft-Deleted
      (
        `status` = 'deleted'
        AND NOT EXISTS (
            SELECT 1 FROM `movies_access` WHERE `movies_access`.`movie_id` = `movies`.`file_id`
        )
      )
      OR
      -- Regel 2: Inaktiv (> 2 Jahre) & Soft-Deleted -> 30 Tage Schutzfrist abgelaufen
      (
        `status` = 'deleted'
        AND EXISTS (
            SELECT 1 FROM `movies_access` WHERE `movies_access`.`movie_id` = `movies`.`file_id`
        )
        AND NOT EXISTS (
            SELECT 1 FROM `movies_access` 
            WHERE `movies_access`.`movie_id` = `movies`.`file_id` 
              AND (
                   `movies_access`.`buy_timestamp` >= '{$now_minus_2years}' 
                   OR (`movies_access`.`access_token_datetime` != '0000-00-00 00:00:00' AND `movies_access`.`access_token_datetime` >= '{$now_minus_2years}')
              )
        )
        AND `deleted_datetime` != '0000-00-00 00:00:00'
        AND `deleted_datetime` < '{$now_minus_30}'
      )
      OR
      -- Regel 3: Aktiv (< 2 Jahre) & Soft-Deleted -> 365 Tage Schutzfrist abgelaufen
      (
        `status` = 'deleted'
        AND EXISTS (
            SELECT 1 FROM `movies_access` 
            WHERE `movies_access`.`movie_id` = `movies`.`file_id` 
              AND (
                   `movies_access`.`buy_timestamp` >= '{$now_minus_2years}' 
                   OR (`movies_access`.`access_token_datetime` != '0000-00-00 00:00:00' AND `movies_access`.`access_token_datetime` >= '{$now_minus_2years}')
              )
        )
        AND `deleted_datetime` != '0000-00-00 00:00:00'
        AND `deleted_datetime` < '{$now_minus_365}'
      )
      OR
      -- Regel 4: Alt & Abgelehnt (> 180 Tage)
      (
        (`released` = '2' OR `status` = 'blocked')
        AND `status` != 'deleted'
        AND COALESCE(
            NULLIF(`movie_checked`, '0000-00-00 00:00:00'),
            NULLIF(`online_at`, '0000-00-00 00:00:00'),
            NULLIF(`create_datetime`, '0000-00-00 00:00:00'),
            NULLIF(`last_updated_datetime`, '0000-00-00 00:00:00')
        ) < '{$now_minus_180days}'
      )
      OR
      -- Regel 5: Inaktive Entwuerfe (> 180 Tage)
      (
        `released` = '0'
        AND `status` != 'deleted'
        AND COALESCE(
            NULLIF(`create_datetime`, '0000-00-00 00:00:00'),
            NULLIF(`online_at`, '0000-00-00 00:00:00'),
            NULLIF(`last_updated_datetime`, '0000-00-00 00:00:00')
        ) < '{$now_minus_180days}'
      )
    ORDER BY `id` ASC;
";

$rs_movies = p4c_query($sql_query, __FILE__, __LINE__);
$count_delete_movie = p4c_num_rows($rs_movies);
$fallback_mode = false;

if ($count_delete_movie == 0) {
    echo "No movies matched Rules 1-5. Falling back to 6-year inactivity check...\n\n";
    
    $rs_movies = p4c_query("
        SELECT m.*
        FROM movies m
        WHERE m.online_at < '".date("Y-m-d H:i:s", strtotime("-6 year"))."'
          AND NOT EXISTS (
            SELECT 1
            FROM movies_access ma
            WHERE ma.movie_id = m.file_id
              AND ma.access_token_datetime >= '".date("Y-m-d H:i:s", strtotime("-6 year"))."'
          )
        ORDER BY m.online_at ASC
        LIMIT 10;
    ", __FILE__, __LINE__);
    
    $count_old_movie = p4c_num_rows($rs_movies);
    $fallback_mode = true;
}

$total_size = 0;
$count_movies = 0;

if (($fallback_mode && $count_old_movie > 0) || (!$fallback_mode && $count_delete_movie > 0)) {
    while ($movie_obj = p4c_fetch_object($rs_movies)) {
        $folder = MOVIES_PATH.'/'.$movie_obj->storage_location.'/'.$movie_obj->merchant_id.'/'.$movie_obj->id;
        $folder_size = getFolderSize($folder);
        
        $total_size += $folder_size;
        $count_movies++;
        
        $size_formatted = number_format($folder_size / 1024 / 1024, 2) . " MB";
        if ($folder_size >= 1073741824) {
            $size_formatted = number_format($folder_size / 1073741824, 2) . " GB";
        }
        
        // Regel klassifizieren
        $rule_name = '';
        if ($fallback_mode) {
            $rule_name = 'Fallback (Inaktivität > 6 Jahre)';
        } else {
            $rs_access = p4c_query("SELECT buy_timestamp, access_token_datetime FROM `movies_access` WHERE `movie_id` = '".p4c_escape_string($movie_obj->file_id)."' ORDER BY `buy_timestamp` DESC;", __FILE__, __LINE__);
            $purchases_count = p4c_num_rows($rs_access);
            
            if ($movie_obj->released == '0' && $movie_obj->status !== 'deleted') {
                $rule_name = 'Regel 5: Inaktive Entwürfe';
            } elseif ($movie_obj->status !== 'deleted') {
                $rule_name = 'Regel 4: Alt & Abgelehnt';
            } elseif ($purchases_count == 0) {
                $rule_name = 'Regel 1: Nie gekauft';
            } else {
                $buy_obj = p4c_fetch_object($rs_access);
                $last_buy_ts = strtotime($buy_obj->buy_timestamp);
                
                $rs_view = p4c_query("SELECT `access_token_datetime` FROM `movies_access` WHERE `movie_id` = '".p4c_escape_string($movie_obj->file_id)."' AND `access_token_datetime` != '0000-00-00 00:00:00' ORDER BY `access_token_datetime` DESC LIMIT 1;", __FILE__, __LINE__);
                $last_view_ts = 0;
                if (p4c_num_rows($rs_view) > 0) {
                    $view_obj = p4c_fetch_object($rs_view);
                    $last_view_ts = strtotime($view_obj->access_token_datetime);
                }
                
                $two_years_ago = strtotime("-2 years");
                if ($last_buy_ts < $two_years_ago && $last_view_ts < $two_years_ago) {
                    $rule_name = 'Regel 2: Inaktiv (> 2 Jahre)';
                } else {
                    $rule_name = 'Regel 3: Aktiv (< 2 Jahre)';
                }
            }
        }
        
        echo "[DRY-RUN] Film-ID: {$movie_obj->id}\n";
        echo "  Titel:    " . utf8_decode($movie_obj->title) . "\n";
        echo "  Regel:    {$rule_name}\n";
        echo "  Ordner:   {$folder}\n";
        echo "  Groesse:  {$size_formatted}\n";
        echo "  Aktion:   [SIMULIERT] Loesche Verzeichnis, logge in movies_deleted, loesche DB-Eintraege\n";
        echo "----------------------------------------------------------------------\n";
    }
    
    $total_saved_text = number_format($total_size / 1024 / 1024, 2) . " MB";
    if ($total_size >= 1073741824) {
        $total_saved_text = number_format($total_size / 1073741824, 2) . " GB";
    }
    
    echo "\n======================================================================\n";
    echo "DRY-RUN SUMMARY:\n";
    echo "  Total movies that WOULD be deleted: " . $count_movies . "\n";
    echo "  Total storage space that WOULD be freed: " . $total_saved_text . "\n";
    echo "======================================================================\n";
} else {
    echo "No movies matched any deletion criteria. Nothing would be deleted.\n";
    echo "======================================================================\n";
}

p4c_close(DB_HOST);
?>
