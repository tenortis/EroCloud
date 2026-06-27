<?php

define('SAFE_INC', 1);

session_cache_limiter('none');

include_once("../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('mcp') === false) {
    exit;   
}

$file_id = $_GET['id'];

if (strpos($file_id, '_16.')) {
    $fsk = 16;
} else {
    $fsk = 18;
}

$no_pic = MCP_DIR.'/images/movie_poster_nopic.jpg';

$filename = $no_pic;

$rs_picture = p4c_query("SELECT * FROM `actors` WHERE `profile_image_fsk".abs($fsk)."`='".p4c_escape_string($file_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_picture) == 1) {
    
    $actor_obj = p4c_fetch_object($rs_picture);

    // Profilbild welches in DB gespeichert ist
    $filename = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$actor_obj->merchant_id.'/'.$actor_obj->id.'/'.$file_id;
    // Wenn kein Bild gespeichert ist bzw. es nicht existiert, dann nimm das Erste 
    if (!is_file($filename)) {
        $filename = $no_pic;
    }
    
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
    global $movie_ary;
   
    # Bilddaten feststellen  
    $file = getimagesize($bild);
    $mime_type = $file['mime'];
	
    $breite = $file[0];
    $hoehe = $file[1];  

    if ($breite > 800 AND $size > 800 OR $size == 0) {
        $size = 800;
    }

    $prop = $hoehe/$breite;
    $neueBreite = $size;
    $neueHoehe = $size * $prop;
      
    $neue_wHoehe = $breite/100*15;
    $neue_wBreite = $neue_wHoehe;
    
    # JPG  
    if($mime_type == 'image/jpeg') {
        $altesBild = ImageCreateFromJPEG($bild);  
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);  
       
        ob_start();
        ImageJPEG($neuesBild,  null);
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

if(filesize($filename) == 0) {
    @unlink($filename);
    $filename = $no_pic;
    #header("Pragma: cache");
    #header('Cache-control: max-age='.(60*60*24*360).', public');
    #header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: image/jpeg');
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
    } else {
        #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        #header('Content-transfer-encoding: binary');
        header('Content-length: '.filesize($filename));
        readfile($filename);
    }

} else {

    $mime_content_type = mime_content_type($filename);

    header('Content-type: '.$mime_content_type);
   
    $width = 300;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }
    
    if ($width > 900) {$width = 900;}
    if ($width < 50) {$width = 50;}


    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
    } else {
        #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        to_thumb($filename, $width);
    }
}

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>