<?php

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * Beispiel - Nachricht senden
 ************************************

$mess = new ChatMessage;

$mess->chat_id      = $chat_id;
$mess->p4c_shop_id  = P4C_SHOPID;
$mess->merchant_id  = $actor_obj->merchant_id();
$mess->amoredea_von_id= $member_id;
$mess->amoredea_an_id = $actor_id;
$mess->domain       = DOMAIN;
$mess->von          = 'member';
$mess->von_id       = $member_id;
$mess->an_id        = $actor_id;
$mess->message_price= $actor_obj->pn_amount;
$mess->message      = $message;
$mess->gelesen      = 2; // 2=gesendet
$mess->send_to      = 'erocloud';
echo $mess->send();

*************************************/

class ChatMessage {

    var $chat_id        = '';
    var $p4c_shop_id    = 0;
    var $merchant_id    = 0;
    var $erocms_von_id  = 0;
    var $erocms_an_id   = 0;
    var $von            = 'actor'; // actor / member / system
    var $von_id         = 0;
    var $an_id          = 0;
    var $message_price  = 0;
    var $message        = 'DUMMY';
    var $actor_commision = 0;
    var $actor_commision_percent = 0;
    var $datetime       = 0;
    var $is_mobile      = 0;
    var $systemnachricht= 0;
    var $gelesen        = 0;
    var $beantwortet    = 0;
    var $geloescht      = 0;
    var $domain         = '';
    var $send_to        = '';
    
    function __construct() {
        global $mysql, $class_errorlog;
        $this->mysql = $mysql;
        $this->class_errorlog = $class_errorlog;
    }
 
    function send() {

    	if ($this->systemnachricht == 1) {$this->preis = 0;}
    	
        if ($this->datetime === 0) {
            $this->datetime = date("Y-m-d H:i:s");
        }
        
        $message = trim($this->message);
    	#$this->message = str_replace("¤", "&euro;", $this->message);
   	
    	// Für korrekte Kodierung von Sonderzeichen wie EURO-Zeichen, Ü, Ä
    	p4c_query('SET NAMES "utf8"');
    	
        if (p4c_query('INSERT INTO `amoredea_messages` SET 
            `chat_id`       = "'.p4c_escape_string($this->chat_id).'",
            `p4c_shop_id`   = "'.abs((int)$this->p4c_shop_id).'",
            `merchant_id`   = "'.abs((int)$this->merchant_id).'",
            `amoredea_von_id` = "'.abs((int)$this->amoredea_von_id).'",
            `amoredea_an_id`  = "'.abs((int)$this->amoredea_an_id).'",
            `von`           = "'.p4c_escape_string($this->von).'",
            `von_id`        = "'.abs((int)$this->von_id).'",
            `an_id`         = "'.abs((int)$this->an_id).'",
            `message_price` = "'.abs((float)$this->message_price).'",
            `actor_commision`           = "'.abs((float)$this->actor_commision).'",
            `actor_commision_percent`   = "'.abs((float)$this->actor_commision_percent).'",
            `message`       = "'.p4c_escape_string($message).'",
            `datetime`      = "'.date("Y-m-d H:i:s", strtotime($this->datetime)).'",
            `is_mobile`     = "'.abs((int)$this->is_mobile).'",
            `systemnachricht` = "'.abs((int)$this->systemnachricht).'",
            `gelesen`       = "'.abs((int)$this->gelesen).'",
            `beantwortet`   = "'.abs((int)$this->beantwortet).'",
            `geloescht`     = "'.abs((int)$this->geloescht).'";',__FILE__,__LINE__)) {
            
            $insert_id_chat_messages = p4c_insert_id();
            
            p4c_query('INSERT INTO `amoredea_messages_history` SET 
                `chat_id`       = "'.p4c_escape_string($this->chat_id).'",
                `p4c_shop_id`   = "'.abs((int)$this->p4c_shop_id).'",
                `merchant_id`   = "'.abs((int)$this->merchant_id).'",
                `amoredea_von_id` = "'.abs((int)$this->amoredea_von_id).'",
                `amoredea_an_id`  = "'.abs((int)$this->amoredea_an_id).'",
                `von`           = "'.p4c_escape_string($this->von).'",
                `von_id`        = "'.abs((int)$this->von_id).'",
                `an_id`         = "'.abs((int)$this->an_id).'",
                `message_price` = "'.abs((float)$this->message_price).'",
                `actor_commision`           = "'.abs((float)$this->actor_commision).'",
                `actor_commision_percent`   = "'.abs((float)$this->actor_commision_percent).'",
                `message`       = "'.p4c_escape_string($message).'",
                `datetime`      = "'.date("Y-m-d H:i:s", strtotime($this->datetime)).'",
                `is_mobile`     = "'.abs((int)$this->is_mobile).'",
                `systemnachricht` = "'.abs((int)$this->systemnachricht).'",
                `gelesen`       = "'.abs((int)$this->gelesen).'",
                `beantwortet`   = "'.abs((int)$this->beantwortet).'",
                `geloescht`     = "'.abs((int)$this->geloescht).'";',__FILE__,__LINE__);
            
            $insert_id_chat_messages_history = p4c_insert_id();
            
            p4c_query("UPDATE `amoredea_messages` SET `gelesen` = '0' WHERE `id`='".abs((int)$insert_id_chat_messages)."' LIMIT 1;",__FILE__,__LINE__);
            p4c_query("UPDATE `amoredea_messages_history` SET `gelesen` = '0' WHERE `id`='".abs((int)$insert_id_chat_messages_history)."' LIMIT 1;",__FILE__,__LINE__);
                
            return 'ok';
        }
    }
}