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

function upload_file($album, $fsk) {
    global $fileTypes;

    $fileName = $_FILES["album_image"]["name"];
    $tmpFileName = $_FILES["album_image"]["tmp_name"];
    
    $fileParts = pathinfo($fileName);
    $extension = $fileParts['extension'];
    $extension = strtolower($extension);

    if (!in_array($extension,$fileTypes)) {
        file_error("Falsches Dateiformat! Es ist nur jpg erlaubt...");
    } else {
        
        $album_id = $album->field('id');
        
        $path = PHOTO_ALBUMS_PATH.'/'.PHOTO_ALBUMS_DEFAULT_DIR.'/'.$_SESSION['merchant_id'].'/'.$album_id;
        
        $file_id = strtolower(md5($album_id.time()));
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
                $file_name_delete = $album->field('preview_image_fsk16');
            } else if ($fsk == '18') {
                $file_name_delete = $album->field('preview_image_fsk18');
            }
            
            // Allte Profilbilder löschen
            if (file_exists($path.'/'.$file_name_delete)) {
                if (is_file($path.'/'.$file_name_delete)) {
                    @unlink($path.'/'.$file_name_delete);
                }
            }       
            
            p4c_query("UPDATE `photo_albums` SET
                `preview_image_fsk".abs($fsk)."`='".p4c_escape_string($file_name)."'
            WHERE 
                `id` = '".abs($album_id)."' AND
                `merchant_id` = '".abs($_SESSION['merchant_id'])."'
            LIMIT 1;",__FILE__,__LINE__);


            $a['merchant_id'] = $album->field('merchant_id');
            $a['number_of_photos'] = $album->field('number_of_photos');
            $a['preview_image_fsk16'] = $album->field('preview_image_fsk16');
            $a['preview_image_fsk18'] = $album->field('preview_image_fsk18');
            $a['title'] = $album->field('title');
            $a['description'] = $album->field('description');
            $a['meta_title'] = $album->field('meta_title');
            $a['meta_description'] = $album->field('meta_description');
            $a['seo_url'] = $album->field('seo_url');
            $a['online_at'] = $album->field('online_at');
            $a['amount_webmaster'] = $album->field('amount_webmaster');
            $a['amount_download'] = $album->field('amount_download');
            
            $m5d_checksum = photo_album_checksum($a);
            
            p4c_query("UPDATE `photo_albums` SET `checksum`='".p4c_escape_string($m5d_checksum)."' WHERE
                `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                `id`='".abs($album_id)."' LIMIT 1;
            ",__FILE__,__LINE__);
            
            $ret[]= '';
            
        } else {
            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")<br />".$file_path);
        }
    }
}

$fileTypes = array('jpg','jpeg'); // File extensions

if(isset($_FILES["album_image"]) AND isset($_GET['album_id']) AND isset($_GET['fsk'])) {
    $ret = array();
    
    $album_id = abs($_GET['album_id']); 
    $fsk = abs($_GET['fsk']); 
    
    // Fotoalbum-Klasse einbinden 
    include_once(SOURCEDIR.'/includes/klassen/PhotoAlbum.inc.php');
    $album = new PhotoAlbum($mysql,$album_id);

    if ($album->field("id") == '') {
        file_error('Dieses Album ('.abs($album_id).') existiert nicht.');
    }
    
    $error = $_FILES["album_image"]["error"];
    if ($error != 0) {
        file_error($phpFileUploadErrors[$_FILES["album_image"]["error"]]);
    }
	
    upload_file($album, $fsk);

    echo json_encode($ret);
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>