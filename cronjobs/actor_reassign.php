#!/usr/bin/php
<?php
 
define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

/**
 * Ablauf:
 * 1. Datenbankeinträge neu zuordnen
 * 2. Wenn DB-Zuordnung erledigt, dann Dateien (Filme, Fotos usw. neu zuorden/ kopieren) 
 */

function delete_directory($dirname) {
    global $class_errorlog;
    
    if(is_dir($dirname)) {
        $dir_handle = opendir($dirname);
    }
    
    //Falls Verzeichnis nicht geoeffnet werden kann, mit Fehlermeldung terminieren
    if(isset($dir_handle)) {
        while($file=readdir($dir_handle)) {
            if($file!="." && $file!="..")  {
                //Datei loeschen 
                if(!is_dir($dirname."/".$file)) {
                    @unlink($dirname."/".$file);
                //Falls es sich um ein Verzeichnis handelt, "delete_directory" aufrufen
                } else {
                    delete_directory($dirname."/".$file);
                }
            }
        }

        closedir($dir_handle);
        
        //Verzeichnis loeschen
        rmdir($dirname);
        
        return true;
    } 
    
    return false;
}

// Wenn DB-Zuordnung erledigt, dann Dateien (Filme, Fotos usw. neu zuorden/ kopieren) 
function data_mapping($id=0) {
    global $class_errorlog;

    if ($id == 0) {
        exit;
    }
    
    $rs_check_actor_reassign = p4c_query("SELECT * FROM `actor_reassign` WHERE `data_mapping_completed`!='0000-00-00 00:00:00' AND `files_mapping_completed`='0000-00-00 00:00:00' AND `id`='".abs($id)."' ORDER BY `id` ASC LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_actor_reassign) == 1) {
        $actor_reassign_obj = p4c_fetch_object($rs_check_actor_reassign);
        
        if ($actor_reassign_obj->files_mapping_started == '0000-00-00 00:00:00') {
            p4c_query("UPDATE `actor_reassign` SET `files_mapping_started`='".date("Y-m-d H:i:s")."' WHERE  `id`='".abs($actor_reassign_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
        }

        $new_merchant_id = $actor_reassign_obj->new_merchant_id;
        $old_merchant_id = $actor_reassign_obj->old_merchant_id;
        $actor_id = $actor_reassign_obj->actor_id;
        
        // Dateien kopieren
        
        // erstelle Speicher für Filme
        $new_movie_path = MOVIES_PATH.'/'.MOVIES_DEFAULT_DIR.'/'.$new_merchant_id;
        if (!file_exists($new_movie_path)) {
            mkdir($new_movie_path, 0777, true);
        }
        
        // erstelle Speicher für Fotoalben
        $new_photo_album_path = PHOTO_ALBUMS_PATH.'/'.PHOTO_ALBUMS_DEFAULT_DIR.'/'.$new_merchant_id;
        if (!file_exists($new_photo_album_path)) {
            mkdir($new_photo_album_path, 0777, true);
        } 

        // Dateien kopieren / verschieben
        $rs_get_actor_reassign_files = p4c_query("SELECT * FROM `actor_reassign_files` WHERE `actor_id`='".abs($actor_id)."' AND `old_merchant_id`='".abs($old_merchant_id)."';",__FILE__,__LINE__);
        if (p4c_num_rows($rs_get_actor_reassign_files) > 0) {
            while($reassing_file_obj = p4c_fetch_object($rs_get_actor_reassign_files)) {
                if ($reassing_file_obj->type == 'movie') {
                    $old_path = MOVIES_PATH.'/'.MOVIES_DEFAULT_DIR.'/'.$old_merchant_id.'/'.$reassing_file_obj->file_id;
                    $new_path = MOVIES_PATH.'/'.MOVIES_DEFAULT_DIR.'/'.$new_merchant_id.'/'.$reassing_file_obj->file_id;
                    
                    // Dateien kopieren
                    copyDir($old_path, $new_path);   
                    
                    // Prüfen ob Dateien erfolgreich kopiert wurden
                    if (file_exists($new_path.'/'.$reassing_file_obj->filename)) {

                        // Dateien bei alten Merchant löschen
                        if (delete_directory($old_path) === true) {

                            // Dateien in DB neuem Merchant zuordnen
                            p4c_query("UPDATE `movies`          SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `filename`='". p4c_escape_string($reassing_file_obj->filename)."' LIMIT 1;",__FILE__,__LINE__);
                            p4c_query("UPDATE `movies_online`   SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `filename`='". p4c_escape_string($reassing_file_obj->filename)."' LIMIT 1;",__FILE__,__LINE__);
                        }
                    }

                    
                } else if ($reassing_file_obj->type == 'photo_album') {
                    $old_path = PHOTO_ALBUMS_PATH.'/'.PHOTO_ALBUMS_DEFAULT_DIR.'/'.$old_merchant_id.'/'.$reassing_file_obj->file_id;
                    $new_path = PHOTO_ALBUMS_PATH.'/'.PHOTO_ALBUMS_DEFAULT_DIR.'/'.$new_merchant_id.'/'.$reassing_file_obj->file_id;
                    
                    // Dateien kopieren    
                    copyDir($old_path, $new_path);

                    // Dateien bei alten Merchant löschen
                    if (delete_directory($old_path) === true) {

                        // Dateien in DB neuem Merchant zuordnen
                        p4c_query("UPDATE `photo_albums`        SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `album_id`='". p4c_escape_string($reassing_file_obj->filename)."' LIMIT 1;",__FILE__,__LINE__);
                        p4c_query("UPDATE `photo_albums_online` SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."' AND `album_id`='". p4c_escape_string($reassing_file_obj->filename)."' LIMIT 1;",__FILE__,__LINE__);
                        p4c_query("UPDATE `photo_albums_photos` SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `album_id`='". p4c_escape_string($reassing_file_obj->filename)."';",__FILE__,__LINE__);
                    }
                }
                
                p4c_query("DELETE FROM `actor_reassign_files` WHERE `id`='".abs($reassing_file_obj->id)."' LIMIT 1;",__FILE__,__LINE__);                

            }            
        }
        
        // Profilbilder kopieren
        $new_img_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$new_merchant_id.'/'.$actor_id;
        $old_img_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$old_merchant_id.'/'.$actor_id;

        // Verzeichniss für Profilbilder erstellen
        mkdir($new_img_path, 0777, true);
        // Profilbilder von alten zu neuen Merchant kopieren
        copyDir($old_img_path, $new_img_path);
        // Profilbilder bei alten Merchant löschen
        delete_directory($old_img_path);

        
        p4c_query("UPDATE `actor_reassign` SET `files_mapping_completed`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($actor_reassign_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
    }
}


// Datenbankeinträge neu zuordnen
$rs_check_actor_reassign = p4c_query("SELECT * FROM `actor_reassign` WHERE `data_mapping_completed`='0000-00-00 00:00:00' ORDER BY `actor_reassign`.`id` ASC LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_check_actor_reassign) == 1) {

    $actor_reassign_obj = p4c_fetch_object($rs_check_actor_reassign);
   
    $new_merchant_id = $actor_reassign_obj->new_merchant_id;
    $old_merchant_id = $actor_reassign_obj->old_merchant_id;
    $actor_id = $actor_reassign_obj->actor_id;
    
    p4c_query("UPDATE `actor_reassign` SET `data_mapping_started`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($actor_reassign_obj->id)."' LIMIT 1;",__FILE__,__LINE__);    
    
    // Darsteller in DB neuem Merchant zuordnen
    p4c_query("UPDATE `actors`                  SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `id`='".abs($actor_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `actor_cams`              SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `actor_member_info`       SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `chat_messages`           SET `merchant_id`='".abs($new_merchant_id)."' WHERE (`von`='actor' AND `von_id`='".abs($actor_id)."') AND `merchant_id`='".abs($old_merchant_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `chat_messages`           SET `merchant_id`='".abs($new_merchant_id)."' WHERE (`von`='member' AND `an_id`='".abs($actor_id)."') AND `merchant_id`='".abs($old_merchant_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `chat_messages_history`   SET `merchant_id`='".abs($new_merchant_id)."' WHERE (`von`='actor' AND `von_id`='".abs($actor_id)."') AND `merchant_id`='".abs($old_merchant_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `chat_messages_history`   SET `merchant_id`='".abs($new_merchant_id)."' WHERE (`von`='member' AND `an_id`='".abs($actor_id)."') AND `merchant_id`='".abs($old_merchant_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `group_actors`            SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `messenger_sync`          SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    p4c_query("UPDATE `revenue_webcam`          SET `merchant_id`='".abs($new_merchant_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
   
    $rs_group = p4c_query("SELECT `id` FROM `groups` ORDER BY `groups`.`id` ASC LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_group) == 1) {
        $group_id = p4c_result($rs_group,0);
        p4c_query("UPDATE `group_actors` SET `group_id`='".abs($group_id)."' WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    }    
    
    // zu kopierende Filme tämporär in DB speichern speichern
    $rs_movies = p4c_query("SELECT * FROM `movies` WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    if (p4c_num_rows($rs_movies) > 0) {
        while($movie_obj = p4c_fetch_object($rs_movies)) {
            $rs_actor_reassign_files = p4c_query("SELECT * FROM `actor_reassign_files` WHERE `file_id`='".abs($movie_obj->id)."' AND `type`='movie' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_actor_reassign_files) == 0) {
                p4c_query("INSERT INTO `actor_reassign_files` SET
                    `file_id`='".abs($movie_obj->id)."',
                    `filename`='".p4c_escape_string($movie_obj->filename)."',
                    `type`='movie',
                    `actor_id`='".abs($movie_obj->actor_id)."',
                    `old_merchant_id`='".abs($movie_obj->merchant_id)."',
                    `new_merchant_id`='".abs($new_merchant_id)."'
                ",__FILE__,__LINE__);
            }
        }
    }
    
    // zu kopierende Fotoalben tämporär in DB speichern speichern
    $rs_photo_albums = p4c_query("SELECT * FROM `photo_albums` WHERE `merchant_id`='".abs($old_merchant_id)."' AND `actor_id`='".abs($actor_id)."';",__FILE__,__LINE__);
    if (p4c_num_rows($rs_photo_albums) > 0) {
        while($album_obj = p4c_fetch_object($rs_photo_albums)) {
            $rs_actor_reassign_files = p4c_query("SELECT * FROM `actor_reassign_files` WHERE `file_id`='".abs($album_obj->id)."' AND `type`='photo_album' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_actor_reassign_files) == 0) {
                p4c_query("INSERT INTO `actor_reassign_files` SET
                    `file_id`='".abs($album_obj->id)."',
                    `filename`='". p4c_escape_string($album_obj->album_id)."',
                    `type`='photo_album',
                    `actor_id`='".abs($album_obj->actor_id)."',
                    `old_merchant_id`='".abs($album_obj->merchant_id)."',
                    `new_merchant_id`='".abs($new_merchant_id)."'
                ",__FILE__,__LINE__);
            }
        }
    }

    p4c_query("UPDATE `actor_reassign` SET `data_mapping_completed`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($actor_reassign_obj->id)."' LIMIT 1;",__FILE__,__LINE__);

    data_mapping($actor_reassign_obj->id);
}


p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>