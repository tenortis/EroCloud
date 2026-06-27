<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (is_logged_in('mcp') === false) {
    exit;   
}

$phpFileUploadErrors = array(
    0 => 'There is no error, the file uploaded with success',
    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
    3 => 'The uploaded file was only partially uploaded',
    4 => 'No file was uploaded',
    6 => 'Missing a temporary folder',
    7 => 'Failed to write file to disk.',
    8 => 'A PHP extension stopped the file upload.',
);

function file_error($msg) {
    $custom_error= array();
    $custom_error['jquery-upload-file-error']=$msg;
    echo json_encode($custom_error);	

    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit; 
}

function upload_file($actor, $fsk) {
    global $fileTypes;

    $fileName = $_FILES["profile_image"]["name"];
    $tmpFileName = $_FILES["profile_image"]["tmp_name"];
    
    $fileParts = pathinfo($fileName);
    $extension = $fileParts['extension'];
    $extension = strtolower($extension);

    if (!in_array($extension,$fileTypes)) {
        file_error("Falsches Dateiformat! Es sind nur jpg oder png erlaubt...");
    } else {
        
        $actor_id = $actor->get('id');
        
        $path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$_SESSION['merchant_id'].'/'.$actor_id;
        
        $file_id = strtolower(md5($actor_id.time()));
        $file_name = $file_id.'_'.$fsk.'.'.$extension;        
        
        $file_path = $path.'/'.$file_name;
	
        // erstelle Speicher für Profilbilder
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        
        if(move_uploaded_file($tmpFileName,$file_path)) {
            if (!file_exists($file_path)) {
		file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
            }
            
            if ($fsk == '16') {
                $file_name_delete = $actor->get('profile_image_fsk16');
            } else if ($fsk == '18') {
                $file_name_delete = $actor->get('profile_image_fsk18');
            }
            
            // Allte Profilbilder löschen
            if (file_exists($path.'/'.$file_name_delete)) {
                if (is_file($path.'/'.$file_name_delete)) {
                    @unlink($path.'/'.$file_name_delete);
                }
            }            
            
            p4c_query("UPDATE `actors` SET
                `profile_image_fsk".abs($fsk)."`='".p4c_escape_string($file_name)."'
            WHERE 
                `id` = '".abs($actor_id)."' AND
                `merchant_id` = '".abs($_SESSION['merchant_id'])."'
            LIMIT 1;",__FILE__,__LINE__);

            
            $m5d_checksum = actor_checksum($actor_id);

            p4c_query("UPDATE `actors` SET `md5_checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
                `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                `id`='".abs($actor_id)."' LIMIT 1;
            ",__FILE__,__LINE__);
            
        } else {
            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")<br />".$file_path);
        }
    }
}

$fileTypes = array('jpg','jpeg','png'); // File extensions

if(isset($_FILES["profile_image"]) AND isset($_GET['actor_id']) AND isset($_GET['fsk'])) {
    $ret = array();
    
    $actor_id = abs($_GET['actor_id']); 
    $fsk = abs($_GET['fsk']); 
    
    include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
    $actor = new Actor($actor_id);

    if ($actor->get("id") == '') {
        file_error('Das Profil ('.abs($actor_id).') existiert nicht.');
    }
    
    $error = $_FILES["profile_image"]["error"];
    if ($error != 0) {
        file_error($phpFileUploadErrors[$_FILES["profile_image"]["error"]]);
    }
	
    upload_file($actor, $fsk);

    echo json_encode($ret);
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>