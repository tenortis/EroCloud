#!/usr/bin/php
<?php

// Diese Datei ist dafuer zustaendig, Filme nach den neuen 5 Regeln physisch zu loeschen.
// Das alte Skript bleibt unberuehrt als Backup bestehen.

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

// Zeichencodierung setzen
ini_set('default_charset','UTF-8');
mb_internal_encoding('UTF-8');

// Loesche ein Verzeichnis inkl. Inhalt
function deleteFolder($folderPath) {
    if (!is_dir($folderPath)) {
        return true; // Falls das Verzeichnis nicht mehr existiert
    }
    $files = array_diff(scandir($folderPath), array('.', '..'));
    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath); // Rekursive Loeschung von Unterverzeichnissen
        } else {
            unlink($filePath); // Datei loeschen
        }
    }
    return rmdir($folderPath); // Verzeichnis selbst loeschen
}

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

$now_minus_30 = date("Y-m-d H:i:s", strtotime("-30 days"));
$now_minus_365 = date("Y-m-d H:i:s", strtotime("-365 days"));
$now_minus_2years = date("Y-m-d H:i:s", strtotime("-2 years"));
$now_minus_180days = date("Y-m-d H:i:s", strtotime("-180 days"));

// Abfrage der Loeschkandidaten nach den neuen 5 Regeln
$rs_movies = p4c_query("
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
", __FILE__, __LINE__);

$count_delete_movie = p4c_num_rows($rs_movies);
$count_old_movie = 0;

if ($count_delete_movie == 0) {
    // Fallback: Automatische Speicherplatz-Optimierung bei Inaktivitaet (> 6 Jahre alt)
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
}

if ($count_delete_movie > 0 OR $count_old_movie > 0) {
    $total_size = 0;
    $count_movies = 0;
    
    while ($movie_obj = p4c_fetch_object($rs_movies)) {
        $folder = MOVIES_PATH.'/'.$movie_obj->storage_location.'/'.$movie_obj->merchant_id.'/'.$movie_obj->id;
        $folder_size = getFolderSize($folder);
        
        echo $folder.' / '.number_format($folder_size / 1024 / 1024 / 1024, 2).' GB / ';
       
        if (deleteFolder($folder)) {
            if (!is_dir($folder)) {
                $count_movies++;
                $total_size = $total_size + $folder_size;
                
                // Array der zu uebernehmenden Spalten
                $cols = [
                  'movie_id','merchant_id','actor_id','storage_location','checksum','file_id',
                  'filename','resolution','quality','create_datetime','playtime_string',
                  'playtime_seconds','preview_image_fsk16','preview_image_fsk18','title',
                  'description','meta_title','meta_description','movie_language','seo_url',
                  'category_master','category_slave','online_at','amount_second','amount_own',
                  'amount_webmaster','as_download','amount_download','convert_status',
                  'convert_starttime','convert_endtime','views','last_view','preview',
                  'purchases','last_purchase','movie_checked','released','status',
                  'deleted_datetime','visible_for_website','admin_infos','released_from',
                  'released_datetime','last_updated_by','last_updated_datetime',
                  'folder_size_bytes','deleted_by'
                ];

                $sql = "
                  INSERT INTO movies_deleted (" . implode(',',$cols) . ")
                  SELECT
                    m.id, m.merchant_id, m.actor_id, m.storage_location, m.checksum, m.file_id,
                    m.filename, m.resolution, m.quality, m.create_datetime, m.playtime_string,
                    m.playtime_seconds, m.preview_image_fsk16, m.preview_image_fsk18, m.title,
                    m.description, m.meta_title, m.meta_description, m.movie_language, m.seo_url,
                    m.category_master, m.category_slave, m.online_at, m.amount_second, m.amount_own,
                    m.amount_webmaster, m.as_download, m.amount_download, m.convert_status,
                    m.convert_starttime, m.convert_endtime, m.views, m.last_view, m.preview,
                    m.purchases, m.last_purchase, m.movie_checked, m.released, m.status,
                    m.deleted_datetime, m.visible_for_website, m.admin_infos, m.released_from,
                    m.released_datetime, m.last_updated_by, m.last_updated_datetime,
                    " . (int)$folder_size . " AS folder_size_bytes,
                    'cronjob' AS deleted_by
                  FROM movies m
                  WHERE m.id = " . (int)$movie_obj->id . "
                ";
                p4c_query($sql, __FILE__, __LINE__);
                
                echo "Verzeichnis erfolgreich geloescht. / ";
                
                p4c_query("DELETE FROM `movies_access` WHERE `movie_id` = '".$movie_obj->file_id."'",__FILE__,__LINE__);
                p4c_query("DELETE FROM `movies` WHERE `file_id` = '".$movie_obj->file_id."' LIMIT 1;",__FILE__,__LINE__);
                p4c_query("DELETE FROM `movies_online` WHERE `file_id` = '".$movie_obj->file_id."' LIMIT 1;",__FILE__,__LINE__);
            } else {
                echo "Verzeichnis sollte geloescht werden, existiert aber noch. / ";
            }
        } else {
            echo "Fehler beim Loeschen des Verzeichnisses. / ";
        }
        echo "\n";
    }  
    echo "\nGesamtgroesse der geloeschten Daten: ".$count_movies." Filme / ".number_format($total_size / 1024 / 1024 / 1024, 2)." GB";
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
?>
