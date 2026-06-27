<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

// Site-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/site.inc.php');

function private_substr($str, $len=20) {
    if (strlen($str) > $len) {
        $str = substr($str,0,($len-5)).'[...]';
    }
    
    if(mb_detect_encoding($str) != 'UTF-8') {$str = utf8_encode($str);}
    return $str;
}

$rs_chats = p4c_query("SELECT * FROM `chat_messages` ORDER BY `id` DESC;", __FILE__, __LINE__);

if (p4c_num_rows($rs_chats) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_chats),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );
        
    $i = 1;
    while($chat_obj = p4c_fetch_object($rs_chats)) {
        if ($chat_obj->von == 'member') {
            $actor_id = $chat_obj->an_id;
            $user_id =  $chat_obj->von_id;
        } else {
            $actor_id = $chat_obj->von_id;
            $user_id =  $chat_obj->an_id;
        }
                
        $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
        $actor_obj = p4c_fetch_object($rs_actor);

        $rs_users = p4c_query("SELECT * FROM `members` WHERE `id`='".abs($user_id)."' LIMIT 1;",__FILE__,__LINE__);
        $user_obj = p4c_fetch_object($rs_users);
        

        $website = new Site($mysql,$chat_obj->p4c_shop_id);        
        
        /** Prüfen ob Nachricht ein gesendetes EroCLoud-Bild ist **/
        if (substr($chat_obj->message, 0, 15) == 'erocloud_pdf:::') {
            $explode = explode(":::",$chat_obj->message);

            $merchant_id    = $explode[1];
            $actor_id       = $explode[2];
            $filename       = $explode[3];

            $message = '<a href="'.API_URL.'/MessengerPDF/'.$chat_obj->merchant_id.'/'.$actor_id.'/'.$filename.'" target="_blank">
                <img src="'.API_URL.'/MessengerPDFpreview/'.$chat_obj->merchant_id.'/'.$actor_id.'/'.$filename.'" style="width:auto; height:100px;" />
            </a>';  
        } 
        
        /** Prüfen ob Nachricht ein gesendetes EroCMS-Bild ist **/
        else if (substr($chat_obj->message, 0, 8) == 'image:::') {
            $explode_mess = explode(":::",$chat_obj->message);
            $pfad_thumb = $explode_mess[2];
            
            $image_url = 'https://'.$website->get_var("domain").'/'.$pfad_thumb;
            $messenger_image_info_url = API_URL."/MessengerImageInfo/&type=erocms&url=".$website->get_var("domain")."/".$pfad_thumb;
            
            $message = '
            <a href="javascript:;" onclick="window.open(\''.$messenger_image_info_url.'\', \'Bildinfos\', \'width=350, height=500, location=no, locationbar=no, menubar=no, scrollbars=no, status=no, resizable=yes\');">
                <img src="'.$image_url.'" style="width:auto; height:100px;" />
            </a>';
        }
        
        /** Prüfen ob Nachricht ein gesendetes EroCLoud-Bild ist **/
        else if (substr($chat_obj->message, 0, 17) == 'erocloud_image:::') {
            $explode = explode(":::",$chat_obj->message);

            if (isset($explode[3])) {
                $merchant_id    = $explode[1];
                $actor_id       = $explode[2];
                $filename       = $explode[3];

                $file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$chat_obj->merchant_id.'/messenger/'.$actor_id.'/'.$filename;
                if (is_file($file_path)) {
                    $image_url = API_URL.'/MessengerImage/'.$merchant_id.'/'.$actor_id.'/'.$filename;
                    $messenger_image_info_url = API_URL."/MessengerImageInfo/".$merchant_id.'/'.$actor_id.'/'.$filename;

                    $message = '
                    <a href="javascript:;" onclick="window.open(\''.$messenger_image_info_url.'\', \'Bildinfos\', \'width=350, height=500, location=no, locationbar=no, menubar=no, scrollbars=no, status=no, resizable=yes\');">
                        <img src="'.$image_url.'" style="width:auto; height:100px;" />
                    </a>';
                } else {
                    $message = str_replace('\n','<br />', htmlentities(strip_tags($chat_obj->message)));
                }
            }
        }
            
        // normale Nachricht
        else {
            
            $message = str_replace('\n','<br />', strip_tags($chat_obj->message));
        }        
        
    	$row = array();
        $row[] = '<a href="'.ACP_URL.'/Chat/'.$chat_obj->chat_id.'" target="_blank">'.$chat_obj->chat_id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Actor/'.$actor_id.'" target="_blank">'.$actor_obj->username.'</a>';
        $row[] = '<a href="'.ACP_URL.'/User/'.$user_id.'" target="_blank">'.$user_obj->username.'</a>';
        $row[] = $website->get_var("domain");
        $row[] = $chat_obj->datetime;
        $row[] = $chat_obj->message_price;        
        $row[] = '<span>'.$message.'</span>';
    
    	$output['aaData'][] = $row;
        
        if ($i==5000) {break;}
        $i++;
    }

} else {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => 0,
    	"iTotalDisplayRecords" => 0,
    	"aaData" => 0
    );
}

echo json_encode($output);


p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());


?>