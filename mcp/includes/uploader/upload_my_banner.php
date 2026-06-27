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

function upload_file($ads_obj) {
    
    $site_id = $ads_obj->site_id;
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
            
            if($extension_img == 'jpg' OR $extension_img == 'jpeg') {
                $ads_type = 'jpg';
                $mime_type = 'jpg';
            } else {
                $mime_type = $extension_img;
                $ads_type = $ads_obj->type;
            }

            if ($mime_type == $ads_type) {
                $file_name = date("Ymd_His_".$i).'.'.$extension_img;        

                $file_path = $path.'/'.$file_name;
                
                list($width, $height) = getimagesize($tmpFileName);
                
                // Bannergröße prüfen
                if ($width == $ads_obj->width AND $height == $ads_obj->height) {
                    if(move_uploaded_file($tmpFileName,$file_path)) {
                        if (!file_exists($file_path)) {
                            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
                        }

                        // Wenn eine noch nicht freigeschatlete Datei existiert, löschen 
                        if ($ads_obj->new_filename != '' AND is_file($path.'/'.$ads_obj->new_filename)) {
                            unlink($path.'/'.$ads_obj->new_filename);
                        }
                        
                        p4c_query("UPDATE `ads_media` SET
                            `new_filename` = '". p4c_escape_string($file_name)."',
                            `rejected` = '0'
                        WHERE 
                            `file_id` = '". p4c_escape_string($ads_obj->file_id)."' LIMIT 1;",__FILE__,__LINE__);

                        $i++;

                        $ret[]= '';

                    } else {
                        file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")<br />".$file_path);
                    }
                    
                } else {
                    file_error("Das neue Banner (".$_FILES['banners']['name'][$key].") hat nicht die selbe Gr&ouml;&szlig;e wie das Alte.");
                }
                
            } else {
                file_error("Die hochzuladene Datei muss ebenfalls eine ".$ads_obj->type."-Datei sein.");
            }         
        }
    }
}

if(isset($_FILES["banners"]) AND isset($_GET['file_id'])) {
    $ret = array();

    $file_id = p4c_escape_string($_GET['file_id']);

    $rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE
        `file_id` = '".p4c_escape_string($file_id)."'
    LIMIT 1;",__FILE__,__LINE__);

    // Prüfen ob Seite existiert - wenn nicht, zu EroCloud leten
    if (p4c_num_rows($rs_ads) == 0) {
        file_error('Dieses Banner existiert nicht.');
    }
    
    $ads_obj = p4c_fetch_object($rs_ads);
    
    $error = $_FILES["banners"]["error"];
    
    foreach($error as $key => $value) {
        if ($value != 0) {
            file_error('Error: '.$phpFileUploadErrors[$_FILES["banners"]["error"]].'<br />Datei: '.$_FILES["banners"]["name"][$key]);
        }
    }
    
    upload_file($ads_obj);

    echo json_encode($ret);
}




p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>