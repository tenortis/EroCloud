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

function upload_file($rs_movie, $movie_id, $fsk) {
    global $fileTypes;

    $fileName = $_FILES["movie_poster"]["name"];
    $tmpFileName = $_FILES["movie_poster"]["tmp_name"];
    
    $fileParts = pathinfo($fileName);
    $extension = $fileParts['extension'];
    $extension = strtolower($extension);

    if (!in_array($extension,$fileTypes)) {
        file_error("Falsches Dateiformat! Es sind nur jpg oder png erlaubt...");
    } else {
    
        if ($fsk == 16) {
            $img_id = 11;
        } else {
            $fsk = 18;
            $img_id = 12;
        }

        $movie_obj = p4c_fetch_object($rs_movie);

        $file_name = 'thumb_'.$movie_id.'_'.strtolower($movie_obj->file_id).'_'.$img_id.'.'.$extension;
        $file_path = MOVIES_PATH.'/'.$movie_obj->storage_location.'/'.$_SESSION['merchant_id'].'/'.$movie_id.'/'.$file_name;

        $tmp_file_name = $_SESSION['merchant_id'].'_'.md5($_SESSION['merchant_id'].time()).'.'.$extension;

        if(move_uploaded_file($tmpFileName,$file_path)) {
           
            if (!file_exists($file_path)) {
		file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
            }
            
            $movie['merchant_id'] = $_SESSION['merchant_id'];
            $movie['preview_image_fsk16'] = time();
            $movie['preview_image_fsk18'] = time();
            $movie['title'] = $movie_obj->title;
            $movie['description'] = $movie_obj->description;
            $movie['meta_title'] = $movie_obj->meta_title;
            $movie['meta_description'] = $movie_obj->meta_description;
            $movie['seo_url'] = $movie_obj->seo_url;
            $movie['online_at'] = $movie_obj->online_at;
            $movie['amount_second'] = $movie_obj->amount_second;
            $movie['amount_own'] = $movie_obj->amount_own;
            $movie['amount_webmaster'] = $movie_obj->amount_webmaster;
            $movie['as_download'] = $movie_obj->as_download;
            $movie['amount_download'] = $movie_obj->amount_download;
            
            $movie['fsk16'] = $movie['preview_image_fsk16'];
            $movie['fsk18'] = $movie['preview_image_fsk18'];
            
            p4c_query("UPDATE `movies` SET
                `checksum`='".movie_checksum($movie)."',
                `movie_checked` = '0000-00-00 00:00:00',
                `released`='1'
                WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
            
            
        } else {
            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
        }
    }
}

$fileTypes = array('jpg','jpeg','png'); // File extensions

if(isset($_FILES["movie_poster"]) AND isset($_GET['movie_id']) AND isset($_GET['fsk'])) {
    $ret = array();
    
    $movie_id = abs($_GET['movie_id']); 
    $fsk = abs($_GET['fsk']); 
    
    $rs_movie = p4c_query("SELECT * FROM `movies` WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
    
    // Nur Updaten wenn Datei noch nicht hochgeladen wurde
    if (p4c_num_rows($rs_movie) == 1) {
       
    	$error = $_FILES["movie_poster"]["error"];
    	if ($error != 0) {
            file_error($phpFileUploadErrors[$_FILES["movie_poster"]["error"]]);
    	}
	
        upload_file($rs_movie, $movie_id, $fsk);
    }
    echo json_encode($ret);
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>