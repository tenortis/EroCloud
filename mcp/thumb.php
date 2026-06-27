<?php


/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_GET['movie_id'])) {exit;}

$rs_movie = p4c_query("SELECT `id`, `merchant_id`, `filename`, `storage_location` FROM `movies` WHERE `file_id`='". p4c_escape_string($_GET['movie_id'])."' LIMIT 1;",__FILE__,__LINE__);

// Wenn die movie_id nicht gefunden wurde
if (p4c_num_rows($rs_movie) == 0) {
    $file = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: image/jpeg');
    
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
    header('Content-transfer-encoding: binary');
    header('Content-length: '.filesize($file));
    readfile($file);
    exit;
}

$movie_ary = p4c_fetch_object($rs_movie);

$file = 'thumb_'.substr($movie_ary->filename,0,-4).'_'.abs($_GET['thumb_number']).'.';
$file = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/'.$file;

if (file_exists($file.'jpg')) {
    $file = $file.'jpg';
} else if (file_exists($file.'jpeg')) {
    $file = $file.'jpeg';
} else if (file_exists($file.'png')) {
    $file = $file.'png';
} else {
    $file = $file.'jpg';
}

function getRequestHeaders() {
    if (function_exists("apache_request_headers")) {
        if($headers = apache_request_headers()) {
            return $headers;
        }
    }
    $headers = array();
    // Grab the IF_MODIFIED_SINCE header
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $headers['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
    }
    return $headers;
}

function to_thumb($bild, $size=0) {
    global $sourcedir;
    
    # Bilddaten feststellen  
    $file = getimagesize($bild);
    $mime_type = $file['mime'];
	
    $breite = $file[0];
    $hoehe = $file[1];  

    if ($breite > 1024 OR $size == 0) {
        $size = 1024;
    }

    $prop = $hoehe/$breite;
    $neueBreite = $size;
    $neueHoehe = $size * $prop;
    
	# GIF
    if($mime_type == 'image/gif') {  
        $altesBild = ImageCreateFromGIF($bild);  
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);  
       
        ob_start();
        ImageGIF($neuesBild,  null);
        $size = ob_get_length();
        header("Content-Length: " . $size);
        ob_end_flush();
    
	# JPG  
	} elseif($mime_type == 'image/jpeg') {
        $altesBild = ImageCreateFromJPEG($bild);  
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);  
        
        ob_start();
        ImageJPEG($neuesBild,  null, 100);
        $size = ob_get_length();
        header("Content-Length: " . $size);
        ob_end_flush();
		
	# PNG  
    } elseif($mime_type == 'image/png') {  
        $altesBild = ImageCreateFromPNG($bild);  
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);  

        ob_start();
        ImagePNG($neuesBild,  null);
        $size = ob_get_length();
        header("Content-Length: " . $size);
        ob_end_flush();
    }  
}

$headers = getRequestHeaders();

// Wenn Datei nicht existiert
if (!file_exists($file)) {
    $file = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: image/jpeg');
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        header('Content-length: '.filesize($file));
        readfile($file);
    }

// Wenn Datei leer ist dann löschen
} elseif(filesize($file) == 0) {
    @unlink($file);
    $file = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: image/jpeg');
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        header('Content-length: '.filesize($file));
        readfile($file);
    }

} else {
    
    $mime_content_type = mime_content_type($file);
    
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: '.$mime_content_type);
   
    $width = 0;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }
    
    if ($width > 1024) {$width = 1024;}
    if ($width < 50) {$width = 50;}
   
    if ($mime_content_type == 'image/gif') {
          
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file))) {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
        } else {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
            header('Content-transfer-encoding: binary');
            header('Content-length: '.filesize($file));
            readfile($file);
        }
    
    } else {
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file))) {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
        } else {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
            header('Content-transfer-encoding: binary');
            to_thumb($file, $width);
        }
    }
}

// Garbage Collection
p4c_close(DB_HOST);

unset($rs_file);
unset($file);

	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>