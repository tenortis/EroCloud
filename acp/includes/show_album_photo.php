<?php

define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

function no_image() {
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header('Content-type: image/jpeg');
    header('Content-transfer-encoding: binary');
    header('Content-length: '.filesize($filename));
    readfile($filename);
    
    // Garbage Collection
    p4c_close(DB_HOST);

    // PHP Fehlermeldung loggen
    p4c_errorlog(error_get_last());
}

if (!isset($_GET['photo_id']) OR is_logged_in('acp') === false) {
    no_image();
    exit;
}

$photo_id = $_GET['photo_id'];

$rs_photo = p4c_query("SELECT `photo_albums`.`id` AS `id`, `photo_albums`.`storage_location`, `photo_albums_photos`.`merchant_id`, `photo_albums_photos`.`filename`
    FROM `photo_albums_photos`INNER JOIN `photo_albums` ON `photo_albums_photos`.`album_id`=`photo_albums`.`album_id` WHERE
        `file_id`='".p4c_escape_string($photo_id)."'
    LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_photo) == 0) {
    no_image();
    exit;
}

$photo_obj = p4c_fetch_object($rs_photo);

$filename = PHOTO_ALBUMS_PATH.'/'.$photo_obj->storage_location.'/'.$photo_obj->merchant_id.'/'.$photo_obj->id.'/images/'.$photo_obj->filename;

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
    global $album_ary;

    # Bilddaten feststellen
    $image = new Imagick($bild);
    $mime_type = $image->getImageMimeType();
    $resolution_ary = $image->getImageResolution();

    $breite = $image->getImageWidth();
    $hoehe = $image->getImageHeight();

    if ($size == 0) {
        $size = 800;
    }

    $prop = $hoehe / $breite;
    $neueBreite = $size;
    $neueHoehe = $size * $prop;

    $neue_wHoehe = $breite / 100 * 15;
    $neue_wBreite = $neue_wHoehe;

    # Exif-Daten überprüfen und Bild ausrichten (nur für JPEG)
    if ($mime_type == 'image/jpeg') {
        $exif = @exif_read_data($bild);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $image->rotateImage("#000", 180);
                    break;
                case 6:
                    $image->rotateImage("#000", 90);
                    break;
                case 8:
                    $image->rotateImage("#000", -90);
                    break;
            }
        }
    }

    # Bildgröße ändern und ausgeben
    $image->resizeImage($neueBreite, $neueHoehe, Imagick::FILTER_LANCZOS, 1);
    $image->setImageCompressionQuality(100);

    header("Content-Type: $mime_type");
    echo $image;

    # Speicher freigeben
    $image->clear();
    $image->destroy();
}

$headers = getRequestHeaders();

// Wenn Datei nicht existiert
if (!is_file($filename)) {
    no_image();
    exit;

// Wenn Datei leer ist dann löschen
} elseif(filesize($filename) == 0) {
    @unlink($filename);
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    no_image();
    exit;

} else {

    $mime_content_type = mime_content_type($filename);
    
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: '.$mime_content_type);
   
    $width = 0;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }
    
    if ($width > 900) {$width = 900;}
    if ($width < 50) {$width = 50;}
   
    if ($mime_content_type == 'image/gif') {
        if (isset($_GET['stop']) AND $_GET['stop'] == 'gif') {    

            if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
                header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
            } else {
                header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
                header('Content-transfer-encoding: binary');
                to_thumb($filename, $width);
            }

        } else {
            
            if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
                header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
            } else {
                header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
                header('Content-transfer-encoding: binary');
                header('Content-length: '.filesize($filename));
                readfile($filename);
            }
        }
    
    } else {
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
        } else {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
            header('Content-transfer-encoding: binary');
            to_thumb($filename, $width);
        }
    }
}

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>