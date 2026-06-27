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

function upload_file($album) {
    global $fileTypes;
    
    $album_id = $album->field('id');
    $path = PHOTO_ALBUMS_PATH.'/'.PHOTO_ALBUMS_DEFAULT_DIR.'/'.$_SESSION['merchant_id'].'/'.$album_id.'/images';

    // erstelle Speicher für Albumbilder
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    } 
    
    $i = 1;
    foreach($_FILES["album_images"]["name"] as $key => $value) {
        
        $tmpFileName = $_FILES['album_images']['tmp_name'][$key];
        
        if (!file_exists($tmpFileName)) {
            file_error("Keine Datei ausgew&auml;hlt.");

        } else if (filesize($tmpFileName) > 6291456) {
            file_error('<script>window.location.href="'.MCP_URL.'/Photo-Album-Upload?step=2&album_id='.$album_id.'&to_big='.$_FILES['album_images']['name'][$key].'";</script>');
            
        } else {

            $fileParts = pathinfo($_FILES['album_images']['name'][$key]);
            $extension_img	= $fileParts['extension'];
            $extension_img	= strtolower($extension_img);
            
            if (in_array($extension_img,$fileTypes)) {
                
                $file_id = strtolower(md5($album_id.time()));
                $file_name = 'IMG_'.date("Ymd_His_".$i).'.'.$extension_img;        

                $file_path = $path.'/'.$file_name;
                
                if(move_uploaded_file($tmpFileName,$file_path)) {
                    if (!file_exists($file_path)) {
                        file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
                    }
                    
                    $file_id = strtolower(md5($album_id.$file_name));
                    
                    p4c_query("INSERT INTO `photo_albums_photos` SET
                        `file_id` = '". p4c_escape_string($file_id)."',
                        `album_id` = '". p4c_escape_string($album->field('album_id'))."',
                        `merchant_id` = '". abs($_SESSION['merchant_id'])."',
                        `filename` = '". p4c_escape_string($file_name)."',
                        `upload_datetime` = '". date("Y-m-d H:i:s")."'
                    ",__FILE__,__LINE__);
                    
                    $i++;
                    
                    $ret[]= '';

                } else {
                    file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")<br />".$file_path);
                }
                
            }         
        }
    }
}


$fileTypes = array('jpg','jpeg','png'); // File extensions

if(isset($_FILES["album_images"]) AND isset($_GET['album_id']) AND isset($_GET['fsk'])) {
    $ret = array();
    
    $album_id = abs($_GET['album_id']); 
    $fsk = abs($_GET['fsk']); 
    
    // Fotoalbum-Klasse einbinden 
    include_once(SOURCEDIR.'/includes/klassen/PhotoAlbum.inc.php');
    $album = new PhotoAlbum($mysql,$album_id);

    if ($album->field("id") == '') {
        file_error('Dieses Album ('.abs($album_id).') existiert nicht.');
    }    
    
    $error = $_FILES["album_images"]["error"];
    
    foreach($error as $key => $value) {
        if ($value != 0) {
            file_error('Error: '.$phpFileUploadErrors[$_FILES["album_images"]["error"]].'<br />Datei: '.$_FILES["images"]["name"][$key]);
        }
    }
    
    upload_file($album);

    echo json_encode($ret);
}




p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>