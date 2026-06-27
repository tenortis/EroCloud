#!/usr/bin/php
<?php

// Diese Datei ist dafür zuständig, Filme zu löschen die gelöscht werden soll und . 


define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

// Zeichencodierung setzen
ini_set('default_charset','UTF-8');
mb_internal_encoding('UTF-8');

// Lösche ein Verzeichnis inkl. Inhalt
function deleteFolder($folderPath) {

    if (!is_dir($folderPath)) {
        return true; // Falls das Verzeichnis nicht mehr existiert
    }

    $files = array_diff(scandir($folderPath), array('.', '..'));

    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;

        if (is_dir($filePath)) {
            deleteFolder($filePath); // Rekursive Löschung von Unterverzeichnissen
        } else {
            unlink($filePath); // Datei löschen
        }
    }
    
    return rmdir($folderPath); // Verzeichnis selbst löschen
}

// Ermittelt die Größe vom Inhalt eines Verzeichnis
function getFolderSize($folderPath) {
    $size = 0;

    if (!is_dir($folderPath)) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $size += $file->getSize();
    }

    return $size;
}

// SELECT * FROM `movies` WHERE `online_at` < '2020-04-29' ORDER BY `movies`.`id` ASC


$rs_movies = p4c_query("SELECT * FROM `movies` WHERE 
    `status`='deleted' AND
    (`deleted_datetime` = '0000-00-00 00:00:00' OR `deleted_datetime` < '".date("Y-m-d H:i:s", strtotime("-1 year"))."')
ORDER BY `movies`.`id` ASC", __FILE__,__LINE__);

$count_delete_movie = p4c_num_rows($rs_movies);

if ($count_delete_movie == 0) {
    $rs_movies = p4c_query("SELECT m.*
    FROM movies m
    WHERE m.online_at < '".date("Y-m-d H:i:s", strtotime("-6 year"))."'
      AND NOT EXISTS (
        SELECT 1
        FROM movies_access ma
        WHERE ma.movie_id = m.file_id
          AND ma.access_token_datetime >= '".date("Y-m-d H:i:s", strtotime("-6 year"))."'
      )
    ORDER BY m.online_at ASC
    LIMIT 10;", __FILE__,__LINE__);

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
                
                // -----  
                // 1) Erstelle vor der Lösch-Routine ein Array mit den Spalten,  
                //    die Du übernehmen willst (plus Log-Spalten ganz am Ende):
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
                  // Log-Felder:
                  'folder_size_bytes','deleted_by'
                ];

                // 2) Und baue dann in der Schleife ein INSERT ? SELECT,  
                //    so sparst Du Dir das manuelle Escapen jeder Spalte:

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
                
                echo "Verzeichnis erfolgreich gelöscht. / ";
                
                p4c_query("DELETE FROM `movies_access` WHERE `movie_id` = '".$movie_obj->file_id."'",__FILE__,__LINE__);
                p4c_query("DELETE FROM `movies` WHERE `file_id` = '".$movie_obj->file_id."' LIMIT 1;",__FILE__,__LINE__);
                p4c_query("DELETE FROM `movies_online` WHERE `file_id` = '".$movie_obj->file_id."' LIMIT 1;",__FILE__,__LINE__);

                
            } else {
                echo "Verzeichnis sollte gelöscht werden, existiert aber noch. / ";
            }
        } else {
            echo "Fehler beim Löschen des Verzeichnisses. / ";
        }
    
        echo "\n";
    }  
    
    echo "\nGesamtgröße der geloeschten Daten: ".$count_movies." Filme / ".number_format($total_size / 1024 / 1024 / 1024, 2)." GB";
}



p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
