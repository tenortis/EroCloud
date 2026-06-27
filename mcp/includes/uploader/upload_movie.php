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
    echo 'not logedin';
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

function upload_file($rs_movie, $movie_id) {
    global $fileTypes, $class_errorlog;

    $fileName = $_FILES["movie"]["name"];
    $tmpFileName = $_FILES["movie"]["tmp_name"];
		
    include_once(MCP_DIR.'/includes/getid3/getid3.php');
    $getID3 = new getID3;
    $fileinfo = $getID3->analyze($tmpFileName);
    
    #getid3_lib::CopyTagsToComments($fileinfo);
    $play_time		= $fileinfo['playtime_string'];
    $play_time_sec	= round($fileinfo['playtime_seconds']);
    
    // Prüfe ob Film länger als 120 Sekunden
    
    if ($play_time_sec < 120) {
        #file_error("Der Film muss mindesten eine L&auml;nge von 2 Minuten haben.<br />(Dein Film: ".$play_time_sec." Sekunden)");
    }
    
    if (isset($fileinfo['video']['resolution_x']) AND isset($fileinfo['video']['resolution_y'])) {
        $width = round($fileinfo['video']['resolution_x']);
        $height = round($fileinfo['video']['resolution_y']);

        if ($width < 640 OR $height < 480) {
            #file_error("Der Film muss mindesten eine Aufl&ouml;sung von 640x480 (Pixel) haben.");    
        }
    } else {
        $class_errorlog->log(print_r($fileinfo, true),__FILE__,__LINE__);
        file_error("Der Film enth&auml;lt keine Angaben zur Aufl&ouml;sung (resolution_x / resolution_y).");
    }

    $fileParts = pathinfo($fileName);
    $extension = $fileParts['extension'];
    $extension = strtolower($extension);

    if (!in_array($extension,$fileTypes)) {
        file_error("Falsches Dateiformat!<br />Es sind nur avi, flv, m4v, mkv, mov, mp4, mpg, wmv erlaubt.");
    } else {

        $movie_ary = p4c_fetch_object($rs_movie);

        $file_name = $movie_id.'_'.$movie_ary->file_id.'.'.$extension;

        // erstelle Speicher für Filme
        if (!file_exists(MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$_SESSION['merchant_id'].'/'.$movie_id)) {
            mkdir(MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$_SESSION['merchant_id'].'/'.$movie_id, 0777, true);
        }
		
        $file_path = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$_SESSION['merchant_id'].'/'.$movie_id.'/'.$file_name;
              
        if(move_uploaded_file($tmpFileName,$file_path)) {
            if (file_exists($file_path)) {
                $resolution = $width.'x'.$height;

                $amount_own = round($play_time_sec*$movie_ary->amount_second);
                
                if (p4c_query("UPDATE `movies` SET
                    `filename`          = '".p4c_escape_string($file_name)."',
                    `playtime_string`   = '".p4c_escape_string($play_time)."',
                    `playtime_seconds`  = '".p4c_escape_string($play_time_sec)."',
                    `resolution`        = '".p4c_escape_string($resolution)."',
                    `amount_own`        = '".abs($amount_own)."'
                    WHERE `id` ='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__)) {
                        
                        if (isset($_POST['released']) AND $_POST['released'] == 'true') {
                            p4c_query("UPDATE `movies` SET
                                `create_datetime` = '".date("Y-m-d H:i:s")."',
                                `movie_checked` = '0000-00-00 00:00:00',
                                `released`='1'
                                WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
                        }
                        
                        $ret[]= $fileName;
                }

            } else {
                file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
            }

        } else {
            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")"); 	
        }
    }
}

$fileTypes = array('avi','flv','m4v','mkv','mov','mp4','mpg','wmv'); // File extensions

if(isset($_FILES["movie"]) AND isset($_GET['movie_id'])) {
    $ret = array();
	
    $movie_id = abs($_GET['movie_id']); 
    
    $rs_movie = p4c_query("SELECT * FROM `movies` WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND (`filename`='' OR `convert_status`='0') LIMIT 1;",__FILE__,__LINE__);
    
    // Nur Updaten wenn Datei noch nicht hochgeladen wurde
    if (p4c_num_rows($rs_movie) == 1) {
    	$error = $_FILES["movie"]["error"];
    	if ($error != 0) {
            file_error($phpFileUploadErrors[$_FILES["movie"]["error"]]);
    	}
        
        upload_file($rs_movie, $movie_id);
    } else {
        file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")"); 	
    }

    echo json_encode($ret);
}


 

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>