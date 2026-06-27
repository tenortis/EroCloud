<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


$actor_id = abs($_GET['actor_id']);
$merchant_id = abs($_GET['merchant_id']);
$file_name = preg_replace( '/[^a-z0-9.]/i', '', $_GET['filename']);

$no_pic = MCP_DIR.'/images/movie_poster_nopic.jpg';

$file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$merchant_id.'/messenger/'.$actor_id.'/'.$file_name;


if (!file_exists($file_path)) {
    $filename = $no_pic;
}

function to_thumb($bild, $size=0) {
   
    # Bilddaten feststellen  
    $file = getimagesize($bild);
    $mime_type = $file['mime'];
	
    $breite = $file[0];
    $hoehe = $file[1];  

    if ($breite > 800 OR $size == 0) {
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

$etagFile = md5_file($file_path);

if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    $etagHeader = trim($_SERVER['HTTP_IF_NONE_MATCH']);
} else { 
    $etagHeader = false;
}


if(filesize($file_path) == 0) {
    //@unlink($file_path);
    $filename = $no_pic;

    #header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModified)." GMT");
    header("Etag: $etagFile");
    header('Cache-Control: public');
    
    //check if page has changed. If not, send 304 and exit
    if ($etagHeader == $etagFile) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
        exit;
    }

    header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
    header('Cache-control: max-age='.strtotime("+6 month").', public');
    header('Expires: '.gmdate('D, d M Y H:i:s', strtotime("+6 month")));
    header("Pragma: cache");
    header('Content-transfer-encoding: binary');
    readfile($no_pic);
   
} else {
    
    $mime_content_type = mime_content_type($file_path);

    header('Content-type: '.$mime_content_type);
   
    $width = 150;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }
    
    if ($width > 900) {$width = 900;}
    if ($width < 50) {$width = 50;}

    #header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModified)." GMT");
    header("Etag: $etagFile");
    header('Cache-Control: public');
    
    //check if page has changed. If not, send 304 and exit
    if ($etagHeader == $etagFile) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 304);
        exit;
    }
    
    header("test: 2");
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 200);
    header('Cache-control: max-age='.strtotime("6 month").', public');
    header('Expires: '.gmdate('D, d M Y H:i:s', strtotime("6 month")));
    header("Pragma: cache");
    header('Content-transfer-encoding: binary');
    to_thumb($file_path, $width);

}


?>
