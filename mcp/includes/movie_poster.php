<?php

define('SAFE_INC', 1);

session_cache_limiter('none');

include_once("../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('mcp') === false) {
    exit;   
}

$file_id = $_GET['id'];

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='".p4c_escape_string($file_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header('Content-type: image/jpeg');
    header('Content-transfer-encoding: binary');
    header('Content-length: '.filesize($filename));
    readfile($filename);
    exit;
}

$movie_ary = p4c_fetch_object($rs_movie);

// Vorschaubild welches in DB gespeichert ist
$filename = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/thumb_'.$movie_ary->id.'_'.$movie_ary->file_id.'_'.$movie_ary->preview_image_fsk18.'.jpg';

// Wenn kein Bild gespeichert ist bzw. es nicht existiert, dann nimm das Erste 
if (!file_exists($filename)) {
    $filename = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/thumb_'.$movie_ary->id.'_'.$movie_ary->file_id.'_1.jpg';
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

    if ($breite > 800 OR $size == 0) {
        $size = 800;
    }

    $prop = $hoehe/$breite;
    $neueBreite = $size;
    $neueHoehe = $size * $prop;
    
    // Watermark 
    $logo = 'sd.png';
    
    if ($movie_ary->quality == 'hd') {
        $logo = 'hd.png';
    } else if ($movie_ary->quality == 'fhd') {
        $logo = 'fullhd.png';
    } else if ($movie_ary->quality == '2k') { 
        $logo = '2k.png';
    } else if ($movie_ary->quality == '4k') { 
        $logo = '4k.png';
    } else if ($movie_ary->quality == '6k') { 
        $logo = '6k.png';
    
    // Wenn keine Qualität angegeben wurde nimm die Auflösung
    } else if ($movie_ary->resolution == '1280x720') {
        $logo = 'hd.png';
    } else if ($movie_ary->resolution == '1920x1080' OR $movie_ary->resolution == '1440x1080') {
        $logo = 'fullhd.png';
    } else if ($movie_ary->resolution == '2048x1080') { 
        $logo = '2k.png';
    } else if ($movie_ary->resolution == '4096x2160') { 
        $logo = '4k.png';
    } else if ($movie_ary->resolution == '6144x3160') { 
        $logo = '6k.png';
    }
    
    $watermark = ImageCreateFromPNG(MCP_DIR.'/images/movie_quality_buttons/'.$logo);
    $wfile = getimagesize(MCP_DIR.'/images/movie_quality_buttons/'.$logo);
    $wbreite = $wfile[0];
    $whoehe = $wfile[1];  
   
    $neue_wHoehe = $breite/100*15;
    $neue_wBreite = $neue_wHoehe;
    
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

        // Watermark
        $positzion_x = $breite-$neue_wBreite-10;
        $positzion_y = 10;
        imagecopyresampled($altesBild, $watermark, $positzion_x, $positzion_y, 0, 0, $neue_wBreite, $neue_wHoehe, $wbreite, $whoehe); 
        
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
if (!file_exists($filename)) {
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
    header('Content-type: image/jpeg');
    #header("Pragma: cache");
    #header('Cache-control: max-age='.(60*60*24*360).', public');
    #header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
        #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
    } else {
        #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        header('Content-length: '.filesize($filename));
        readfile($filename);
    }

// Wenn Datei leer ist dann löschen
} elseif(filesize($filename) == 0) {
    @unlink($filename);
    $filename = MCP_DIR.'/images/movie_poster_nopic.jpg';
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
    
    #header("Pragma: cache");
    #header('Cache-control: max-age='.(60*60*24*360).', public');
    #header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
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
                #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
            } else {
                #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
                header('Content-transfer-encoding: binary');
                to_thumb($filename, $width);
            }

        } else {
            
            if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
                #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
            } else {
                #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
                header('Content-transfer-encoding: binary');
                header('Content-length: '.filesize($filename));
                readfile($filename);
            }
        }
    
    } else {
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($filename))) {
            #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 304);
        } else {
            #header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT', true, 200);
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