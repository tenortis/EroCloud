<?php

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

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

/*
function pixelate($image, $thumb_path, $size=200, $pixelate_x = 3, $pixelate_y = 3) {

    // get the input file extension and create a GD resource from it
    $ext = pathinfo($image, PATHINFO_EXTENSION);
    
    if($ext == "jpg" || $ext == "jpeg") {
        
        // Wenn noch kein Thumbnail erstellt wurde
        if(!is_file($thumb_path) OR filesize($thumb_path) <= 3100) {
           
            $image_data = file_get_contents($image);
            list($width, $height) = getimagesizefromstring($image_data);

            $prop = $height/$width;
            $neueBreite = $size;
            $neueHoehe = $size * $prop;
            
            $altesBild = @imagecreatefromjpeg($image);
            if (!$altesBild) {
                exit;
                $altesBild= imagecreatefromstring(file_get_contents($image));
            }
            $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
            
            
            imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $width, $height);        

            // start from the top-left pixel and keep looping until we have the desired effect
            for($y = 0;$y < $neueHoehe;$y += $pixelate_y+1) {
                for($x = 0;$x < $neueBreite;$x += $pixelate_x+1) {
                    // get the color for current pixel
                    $rgb = imagecolorsforindex($neuesBild, imagecolorat($neuesBild, $x, $y));

                    // get the closest color from palette
                    $color = imagecolorclosest($neuesBild, $rgb['red'], $rgb['green'], $rgb['blue']);
                    imagefilledrectangle($neuesBild, $x, $y, $x+$pixelate_x, $y+$pixelate_y, $color);
                }       
            }
            
            ImageJPEG($neuesBild,  $thumb_path, 100);
            imagedestroy($neuesBild); 
        }

        $mime_content_type = mime_content_type($thumb_path);
        
        header('Content-type: '.$mime_content_type);
        header("Content-Length: ".filesize($thumb_path));
        #readfile($thumb_path);
        $img = new Imagick($thumb_path);
        #$img->blurImage(50,45);
        $img->blurImage(10,8);
        echo $img;
        
    } else if($ext == "png") {
        
        // Wenn noch kein Thumbnail erstellt wurde
        if(!is_file($thumb_path)) {
        
            $image_data = file_get_contents($image);
            list($width, $height) = getimagesizefromstring($image_data);

            $prop = $height/$width;
            $neueBreite = $size;
            $neueHoehe = $size * $prop;
            
            $altesBild = imagecreatefrompng($image);
            $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);  
            
            ImageJPEG($neuesBild,  $thumb_path, 100);
            imagedestroy($neuesBild); 
        }
        
        $mime_content_type = mime_content_type($thumb_path);
        
        header('Content-type: '.$mime_content_type);
        header("Content-Length: ".filesize($thumb_path));
        #readfile($thumb_path);
        $img = new Imagick($thumb_path);
        #$img->blurImage(50,45);
        $img->blurImage(5,5);
        echo $img;
    }
}
*/

function pixelate($image, $thumb_path, $size = 200, $pixelate_x = 3, $pixelate_y = 3) {
    // get the input file extension and create a GD resource from it
    $ext = pathinfo($image, PATHINFO_EXTENSION);

    if ($ext === "jpg" || $ext === "jpeg" || $ext === "png") {
        // Wenn noch kein Thumbnail erstellt wurde
        if (!is_file($thumb_path) || filesize($thumb_path) <= 3100) {
            $image_data = file_get_contents($image);
            $width = $height = $prop = 0;

            if ($ext === "jpg" || $ext === "jpeg") {
                
                /*
                list($width, $height) = getimagesizefromstring($image_data);
                $altesBild = @imagecreatefromjpeg($image);
                if (!$altesBild) {
                    $altesBild = imagecreatefromstring($image_data);
                }
                 */
                
                $im = imagecreatefromstring($image_data);

                // Check for Exif data and rotate if needed
                $exif = @exif_read_data($image);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $im = imagerotate($im, 180, 0);
                            break;
                        case 6:
                            $im = imagerotate($im, -90, 0);
                            break;
                        case 8:
                            $im = imagerotate($im, 90, 0);
                            break;
                    }
                }

                $width = imagesx($im);
                $height = imagesy($im);
            } elseif ($ext === "png") {
                $im = imagecreatefromstring($image_data);
                $width = imagesx($im);
                $height = imagesy($im);
                $altesBild = imagecreatetruecolor($width, $height);
                imagecopy($altesBild, $im, 0, 0, 0, 0, $width, $height);
            }

            $prop = $height / $width;
            $neueBreite = $size;
            $neueHoehe = $size * $prop;

            $neuesBild = imagecreatetruecolor($neueBreite, $neueHoehe);

            imagecopyresampled($neuesBild, $altesBild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $width, $height);

            // start from the top-left pixel and keep looping until we have the desired effect
            for ($y = 0; $y < $neueHoehe; $y += $pixelate_y + 1) {
                for ($x = 0; $x < $neueBreite; $x += $pixelate_x + 1) {
                    // get the color for current pixel
                    // prüfen, ob die Variablen $neueBreite und $neueHoehe die korrekten Werte haben und ob diese Werte das tatsächliche Bild nicht überschreiten
                    if ($x < $neueBreite && $y < $neueHoehe) {
                        $rgb = imagecolorsforindex($neuesBild, imagecolorat($neuesBild, $x, $y));
                    }
                    #$rgb = imagecolorsforindex($neuesBild, imagecolorat($neuesBild, $x, $y));

                    // get the closest color from palette
                    $color = imagecolorclosest($neuesBild, $rgb['red'], $rgb['green'], $rgb['blue']);
                    imagefilledrectangle($neuesBild, $x, $y, $x + $pixelate_x, $y + $pixelate_y, $color);
                }
            }

            if ($ext === "jpg" || $ext === "jpeg") {
                imagejpeg($neuesBild, $thumb_path, 100);
            } elseif ($ext === "png") {
                imagepng($neuesBild, $thumb_path);
            }

            imagedestroy($neuesBild);
            imagedestroy($altesBild);
        }

        $mime_content_type = mime_content_type($thumb_path);

        header('Content-type: ' . $mime_content_type);
        header("Content-Length: " . filesize($thumb_path));

        if ($ext === "jpg" || $ext === "jpeg") {
            readfile($thumb_path);
        } elseif ($ext === "png") {
            // display the PNG file with proper content headers
            $fp = fopen($thumb_path, 'rb');
            if (!$fp) {
                throw new Exception("Could not open file {$thumb_path}");
            }

            // send the appropriate headers to the browser
            header("Content-Type: image/png");
            header("Content-Length: " . filesize($thumb_path));

            // send the file contents to the browser
            fpassthru($fp);

            fclose($fp);
        }
    }
}     

if (!isset($_GET['photo_id'])) {
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

$file_dir   = PHOTO_ALBUMS_PATH.'/'.$photo_obj->storage_location.'/'.$photo_obj->merchant_id.'/'.$photo_obj->id.'/images/';
$file_name  = $photo_obj->filename;
$file_path  = $file_dir.$file_name;

$thumb_path = $file_dir.'thumb_'.$file_name;

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

$headers = getRequestHeaders();

// Wenn Thumbnail nicht existiert
if (!is_file($file_path)) {
    no_image();
    exit;

// Wenn Datei leer ist dann löschen
} elseif(filesize($file_path) == 0) {
    echo __LINE__;
    exit;
    @unlink($file_path);
    $file_path = MCP_DIR.'/images/movie_poster_nopic.jpg';
} else {
    $mime_content_type = mime_content_type($file_path);
    
    header("Pragma: cache");
    header('Cache-control: max-age='.(60*60*24*360).', public');
    header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24*365));
    header('Content-type: '.$mime_content_type);
   
    $width = 200;
    if (isset($_GET['w']) AND !empty($_GET['w'])) {
        $width = abs($_GET['w']);
    }

    if ($width > 200) {$width = 200;}
    if ($width < 50) {$width = 50;}
    
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file_path))) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 304);
    } else {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 200);
        header('Content-transfer-encoding: binary');
        pixelate($file_path, $thumb_path, $width);

    }
}

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>