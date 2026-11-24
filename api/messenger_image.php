<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

// Garbage Collection
p4c_close(DB_HOST);

$actor_id = (isset($_GET['actor_id']) && is_scalar($_GET['actor_id'])) ? abs((int)$_GET['actor_id']) : 0;
$merchant_id = (isset($_GET['merchant_id']) && is_scalar($_GET['merchant_id'])) ? abs((int)$_GET['merchant_id']) : 0;
$file_name = (isset($_GET['filename']) && is_string($_GET['filename'])) ? (string)preg_replace('/[^a-z0-9._-]/i', '', $_GET['filename']) : '';
$file_name = basename($file_name);

$no_pic = is_file(MCP_DIR.'/mcp_neu/web/assets/img/movie_poster_nopic.jpg')
    ? MCP_DIR.'/mcp_neu/web/assets/img/movie_poster_nopic.jpg'
    : MCP_DIR.'/images/movie_poster_nopic.jpg';

$file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$merchant_id.'/messenger/'.$actor_id.'/'.$file_name;

if ($file_name === '' || $file_name === '.' || $file_name === '..' || !is_file($file_path)) {
    $file_path = $no_pic;
}

if (!is_file($file_path)) {
    http_response_code(404);
    p4c_errorlog(error_get_last());
    exit;
}

function to_thumb($bild, $size=0) {
   
    # Bilddaten feststellen  
    $file = @getimagesize($bild);
    if (!is_array($file) || empty($file[0]) || empty($file[1])) {
        if (is_file($bild)) {
            header("Content-Length: " . (int)@filesize($bild));
            readfile($bild);
        }
        return;
    }
    $mime_type = $file['mime'] ?? 'image/jpeg';
	
    $breite = (int)$file[0];
    $hoehe = (int)$file[1];  

    if ($breite > 800 OR $size == 0) {
        $size = 800;
    }

    $prop = $hoehe / $breite;
    $neueBreite = max(1, (int)$size);
    $neueHoehe = max(1, (int)round($neueBreite * $prop));
    
    # JPG  
    if($mime_type == 'image/jpeg') {
        $altesBild = @ImageCreateFromJPEG($bild);  
        if (!$altesBild) {
            header("Content-Length: " . (int)@filesize($bild));
            readfile($bild);
            return;
        }
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
        if (!$neuesBild) {
            unset($altesBild);
            header("Content-Length: " . (int)@filesize($bild));
            readfile($bild);
            return;
        }
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);  
       
        ob_start();
        ImageJPEG($neuesBild, null);
        $size = ob_get_length();
        header("Content-Length: " . (int)$size);
        ob_end_flush();
        unset($neuesBild, $altesBild);
		
    # PNG  
    } elseif($mime_type == 'image/png') {  
        $altesBild = @ImageCreateFromPNG($bild);  
        if (!$altesBild) {
            header("Content-Length: " . (int)@filesize($bild));
            readfile($bild);
            return;
        }
        $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
        if (!$neuesBild) {
            unset($altesBild);
            header("Content-Length: " . (int)@filesize($bild));
            readfile($bild);
            return;
        }
        imagealphablending($neuesBild, false);
        imagesavealpha($neuesBild, true);
        imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);  
       
        ob_start();
        ImagePNG($neuesBild, null);
        $size = ob_get_length();
        header("Content-Length: " . (int)$size);
        ob_end_flush();
        unset($neuesBild, $altesBild);
    } else {
        header("Content-Length: " . (int)@filesize($bild));
        readfile($bild);
    }
}

$etagFile = @md5_file($file_path);

if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    $etagHeader = trim((string)$_SERVER['HTTP_IF_NONE_MATCH']);
} else { 
    $etagHeader = false;
}


if(!is_file($file_path) || (int)@filesize($file_path) === 0) {
    $filename = $no_pic;
    $mtime = (int)@filemtime($filename);
    if ($mtime <= 0) { $mtime = time(); }

    if ($etagFile !== false) {
        header("Etag: $etagFile");
    }
    header('Cache-Control: public');
    
    //check if page has changed. If not, send 304 and exit
    if ($etagHeader !== false && $etagFile !== false && $etagHeader === $etagFile) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 304);
        p4c_errorlog(error_get_last());
        exit;
    }

    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 200);
    header('Cache-control: max-age='.(60*60*24*180).', public');
    header('Expires: '.gmdate(DATE_RFC1123, strtotime("+6 month")));
    header("Pragma: cache");
    header('Content-transfer-encoding: binary');
    if (is_file($filename)) {
        readfile($filename);
    }
   
} else {
    
    $mime_content_type = @mime_content_type($file_path) ?: 'image/jpeg';

    header('Content-type: '.$mime_content_type);
   
    $width = 150;
    if (isset($_GET['w']) AND is_scalar($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs((int)$_GET['w']);
    }
    
    if ($width > 900) {$width = 900;}
    if ($width < 50) {$width = 50;}

    $mtime = (int)@filemtime($file_path);
    if ($mtime <= 0) { $mtime = time(); }

    if ($etagFile !== false) {
        header("Etag: $etagFile");
    }
    header('Cache-Control: public');
    
    //check if page has changed. If not, send 304 and exit
    if ($etagHeader !== false && $etagFile !== false && $etagHeader === $etagFile) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 304);
        p4c_errorlog(error_get_last());
        exit;
    }
    
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true, 200);
    header('Cache-control: max-age='.(60*60*24*180).', public');
    header('Expires: '.gmdate(DATE_RFC1123, strtotime("+6 month")));
    header("Pragma: cache");
    header('Content-transfer-encoding: binary');
    to_thumb($file_path, $width);

}

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>
