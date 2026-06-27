<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

$session_write_close = true;

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

function upload_file($actor, $chat_id) {
    global $fileTypes;

    $fileName = $_FILES["send_file"]["name"];
    $tmpFileName = $_FILES["send_file"]["tmp_name"];
    
    $fileParts = pathinfo($fileName);
    $extension = $fileParts['extension'];
    $extension = strtolower($extension);

    if (!in_array($extension,$fileTypes)) {
        file_error("Es sind nur Bilder (jpg, png) und PDF-Dateien erlaubt.");
    } else {
        
        $datei_typ = $extension;
        
        if ($extension == 'jpeg' OR $extension == 'jpg'  OR $extension == 'png') {
            $datei_typ = 'image';
        } else if ($extension == 'png') {
            $datei_typ = 'png';
        }
        
        $actor_id   = $actor->get('id');
        $merchant_id= abs($_SESSION['merchant_id']);
        
        $file_id = strtolower(md5($actor_id.time()));
        
        $file_name = $file_id.'.'.$extension;
        $file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$_SESSION['merchant_id'].'/messenger/'.$actor_id.'/'.$file_name;
	
        // erstelle Speicher für Filme
        if (!file_exists(PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$_SESSION['merchant_id'].'/messenger/'.$actor_id)) {
            mkdir(PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$_SESSION['merchant_id'].'/messenger/'.$actor_id, 0777, true);
        }
        
        if(move_uploaded_file($tmpFileName,$file_path)) {
            if (!file_exists($file_path)) {
		file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")");
            }
            
            $message = 'erocloud_'.$datei_typ.':::'.$merchant_id.':::'.$actor_id.':::'.$file_name;

            $rs_actor_member_info = p4c_query("SELECT * FROM `actor_member_info` WHERE `chat_id`='". p4c_escape_string($chat_id)."' AND `merchant_id`='".abs($merchant_id)."' AND `actor_id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);

            if (p4c_num_rows($rs_actor_member_info) === 1) {
                $member_info_obj = p4c_fetch_object($rs_actor_member_info);

                include_once(SOURCEDIR.'/includes/klassen/ChatMessage.inc.php');

                $mess = new ChatMessage;

                $mess->chat_id      = $member_info_obj->chat_id;
                $mess->p4c_shop_id  = $member_info_obj->p4c_shop_id;
                $mess->merchant_id  = $member_info_obj->merchant_id;
                $mess->erocms_von_id= $member_info_obj->erocms_amateur_id;
                $mess->erocms_an_id = $member_info_obj->erocms_member_id;
                $mess->domain       = $member_info_obj->domain;
                $mess->von          = 'actor';
                $mess->von_id       = $member_info_obj->actor_id;
                $mess->an_id        = $member_info_obj->member_id;
                $mess->message_price= $member_info_obj->pn_amount;
                $mess->message      = $message;
                $mess->gelesen      = 2; // 2=gesendet
                $mess->send_to      = 'erocms';
                echo $mess->send();
            } else {
                @unlink($file_path);
                file_error("Die Datei $fileName konnte nicht gesendet werden. (".__LINE__.")");
            }
            
        } else {
            file_error("Die Datei $fileName konnte nicht hochgeladen werden. (".__LINE__.")<br />".$file_path);
        }
    }
}

$fileTypes = array('jpg','jpeg','png','pdf'); // File extensions

if(isset($_FILES["send_file"]) AND isset($_POST['chat_id'])) {
    
    if ($_FILES['send_file']['type'] != 'image/jpg' AND
        $_FILES['send_file']['type'] != 'image/jpeg' AND
        $_FILES['send_file']['type'] != 'image/png' AND
        $_FILES['send_file']['type'] != 'application/pdf'
    ) {
        file_error("Es sind nur Bilder (jpg, png) und PDF-Dateien erlaubt.");
    }
    
    $ret = array();
        
    $chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_POST['chat_id']);

    $explode = explode('_', $chat_id);
    
    $actor_id = $explode[0];
    
    include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
    $actor = new Actor($actor_id);

    if ($actor->get("id") == '') {
        file_error('Die Datein konnte nicht gesendet werden. ('.__LINE__.')');
    }
    
    $error = $_FILES["send_file"]["error"];
    if ($error != 0) {
        file_error($phpFileUploadErrors[$_FILES["send_file"]["error"]]);
    }
	
    upload_file($actor, $chat_id);

    echo json_encode($ret);
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>