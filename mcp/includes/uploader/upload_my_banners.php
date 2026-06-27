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

function upload_file($site_obj) {
    global $fileTypes;
    
    $site_id = $site_obj->id;
    $path = ADS_PATH.'/'.ADS_DEFAULT_DIR.'/'.$site_id;

    // erstelle Speicher für Albumbilder
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    } 
    
    $i = 1;
    foreach($_FILES["banners"]["name"] as $key => $value) {
        
        $tmpFileName = $_FILES['banners']['tmp_name'][$key];
        
        if (!file_exists($tmpFileName)) {
            file_error("Keine Datei ausgew&auml;hlt.");

        } else if (filesize($tmpFileName) > 5242880) {
            file_error('Datei ('.$_FILES['banners']['name'][$key].') zu gro&szlig;');
            
        } else {

            $fileParts = pathinfo($_FILES['banners']['name'][$key]);
            $extension_img	= $fileParts['extension'];
            $extension_img	= strtolower($extension_img);
            
            if (in_array($extension_img,$fileTypes)) {
                
                $file_name = date("Ymd_His_".$i).'.'.$extension_img;        

                $file_path = $path.'/'.$file_name;
                
                list($width, $height) = getimagesize($tmpFileName);
                
                // Groessen pruefen
                if (
                    // Banner
                    ($width ==  80 AND $height ==  31) OR // Micro Bar
                    ($width == 120 AND $height ==  90) OR // Button 1
                    ($width == 120 AND $height ==  60) OR // Button 2
                    ($width == 120 AND $height == 240) OR // Vertical Banner
                    ($width == 125 AND $height == 125) OR // Square Button
                    ($width == 234 AND $height ==  60) OR // Half Banner
                    ($width == 468 AND $height ==  60) OR // Full Banner
                    ($width == 728 AND $height ==  90) OR // Leaderboard / Superbanner / Supersize Banner
                        
                    // Rectangle
                    ($width == 180 AND $height == 150) OR // Rectangle
                    ($width == 300 AND $height == 250) OR // Medium Rectangle
                    ($width == 240 AND $height == 400) OR // Square Pop-Up
                    ($width == 250 AND $height == 250) OR // Vertical Rectangle
                    ($width == 400 AND $height == 400) OR // Superstitial / Flying Layer / AdLayer / Interstitial

                    // Skyscraper
                    ($width == 160 AND $height == 600) OR // Wide Skyscraper
                    ($width == 120 AND $height == 600) OR // Skyscraper
                    ($width == 200 AND $height == 600) OR // Wide Skyscraper alternative
                    ($width == 300 AND $height == 600) OR // Half Page Ad
                    ($width == 420 AND $height == 600)    // Expandable Skyscraper
                ) {
                    if(move_uploaded_file($tmpFileName,$file_path)) {
                        if (!file_exists($file_path)) {
                            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
                        }

                        $file_id = strtolower(md5($site_id.$file_name));

                        p4c_query("INSERT INTO `ads_media` SET
                            `file_id` = '". p4c_escape_string($file_id)."',
                            `storage_location` = '".ADS_DEFAULT_DIR."',
                            `site_id` = '". p4c_escape_string($site_obj->id)."',
                            `type`     = '".p4c_escape_string($extension_img)."',
                            `width`    = '".p4c_escape_string($width)."',
                            `height`   = '".p4c_escape_string($height)."',
                            `filename` = '". p4c_escape_string($file_name)."',
                            `new_filename` = '". p4c_escape_string($file_name)."',
                            `upload_datetime` = '". date("Y-m-d H:i:s")."'
                        ",__FILE__,__LINE__);

                        $i++;

                        $ret[]= '';

                    } else {
                        file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")<br />".$file_path);
                    }
                    
                } else {
                    file_error("Das Banner (".$_FILES['banners']['name'][$key].") liegt au&szlig;erhalb Standardgr&ouml;&szlig;e.");
                }
                

                
            }         
        }
    }
}


$fileTypes = array('jpg','jpeg','png','gif'); // File extensions

if(isset($_FILES["banners"]) AND isset($_GET['site_id'])) {
    $ret = array();
    
    $site_id = abs($_GET['site_id']); 

    $merchant = new Merchant($mysql,$_SESSION['merchant_id']);

    $rs_sites = p4c_query("SELECT * FROM `sites` WHERE
        `id`        = '".$site_id."' AND
        `status`    = '1' AND
        `is_eroads_active`= '1' AND
        `partner_id` = '".p4c_escape_string($merchant->partner_id())."';
    ",__FILE__,__LINE__);

    if (p4c_num_rows($rs_sites) == 0) {
        file_error('Diese Seite existiert nicht.');
    }
    
    $site_obj = p4c_fetch_object($rs_sites);
    
    $error = $_FILES["banners"]["error"];
    
    foreach($error as $key => $value) {
        if ($value != 0) {
            file_error('Error: '.$phpFileUploadErrors[$_FILES["banners"]["error"]].'<br />Datei: '.$_FILES["banners"]["name"][$key]);
        }
    }
    
    upload_file($site_obj);

    echo json_encode($ret);
}




p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>