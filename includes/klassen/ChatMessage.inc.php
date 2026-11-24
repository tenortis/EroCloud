<?php

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * Beispiel - Nachricht senden
 ************************************

$mess = new ChatMessange;
 
$mess->von = 'actor';
$mess->von_id = abs($_SESSION['sender_id']);
$mess->an_id = abs(0);
$mess->message = "Tolle Nachricht";
$mess->preis = 0;
$mess->systemnachricht = 1;
$mess->gelesen = 0;

$mess->send();

*************************************/

class ChatMessage {

    public $chat_id        = '';
    public $p4c_shop_id    = 0;
    public $merchant_id    = 0;
    public $erocms_von_id  = 0;
    public $erocms_an_id   = 0;
    public $von            = 'actor'; // actor / member / system
    public $von_id         = 0;
    public $an_id          = 0;
    public $message_price  = 0;
    public $message        = 'DUMMY';
    public $actor_commision = 0;
    public $actor_commision_percent = 0;
    public $datetime       = 0;
    public $is_mobile      = 0;
    public $systemnachricht= 0;
    public $gelesen        = 0;
    public $beantwortet    = 0;
    public $geloescht      = 0;
    public $domain         = '';
    public $send_to        = '';
    public $mysql;
    public $class_errorlog;
    public $preis          = 0;
    
    public function __construct() {
        global $mysql, $class_errorlog;
        $this->mysql = $mysql;
        $this->class_errorlog = $class_errorlog;
    }
 
    public function send() {
        global $class_errorlog;

    	if ($this->systemnachricht == 1) {$this->preis = 0;}
    	
        if ($this->datetime === 0) {
            $this->datetime = date("Y-m-d H:i:s");
        }
        
        // Punktesystem
        if ($this->von === 'actor' AND $this->systemnachricht == 0) {
            $points_for = '';

            $rs_get_last_member_message = p4c_query("SELECT * FROM `chat_messages` WHERE `chat_id`='".p4c_escape_string($this->chat_id)."' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);

            // Punkte für neuen User anschreiben
            if ($rs_get_last_member_message && p4c_num_rows($rs_get_last_member_message) == 0) {
                $points_for = 'contat_new_user';

            // Punkte für Antworten auf eine Nachricht
            } elseif ($rs_get_last_member_message) {
                
                $last_mess_obj = p4c_fetch_object($rs_get_last_member_message);
                
                #$class_errorlog->log('Punkte: '.$points_for,__FILE__,__LINE__);

                // Wenn die letzte Nachricht vom User ist
                if ($last_mess_obj && $last_mess_obj->von == 'member') {
                    $lastTs = strtotime((string)($last_mess_obj->datetime ?? ''));
                    if ($lastTs !== false) {
                        // Wenn Antwort unter 8 Stunden
                        if ($lastTs > strtotime("-8 hours")) {
                            $points_for = 'till8hours';

                        // Wenn Antwort unter 16 Stunden
                        } else if ($lastTs > strtotime("-16 hours")) {
                            $points_for = 'till16hours';

                        // Wenn Antwort unter 24 Stunden
                        } else if ($lastTs > strtotime("-24 hours")) {
                            $points_for = 'till24hours';
                        }
                    }
                }
            }

            if ($points_for != '') {
                if (!class_exists('PointsSystem') && defined('SOURCEDIR') && is_file(SOURCEDIR . '/includes/klassen/PointsSystem.inc.php')) {
                    include_once(SOURCEDIR . '/includes/klassen/PointsSystem.inc.php');
                }
                if (class_exists('PointsSystem')) {
                    $points_cls = new PointsSystem();
                    $points_cls->group = 'messenger';
                    $points_cls->points_for = $points_for;
                    $points_cls->actor_id = $this->von_id;
                    $points_cls->date = date("Y-m-d");

                    $points = $points_cls->get_points();
                    if ($points !== false) {
                        $points_cls->set_points($points);
                    }
                }
            }

        }
        
        $message = trim((string)$this->message);
    	#$this->message = str_replace("¤", "&euro;", $this->message);
   	
    	// Für korrekte Kodierung von Sonderzeichen wie EURO-Zeichen, Ü, Ä
    	p4c_query("SET NAMES 'utf8mb4';", __FILE__, __LINE__);

        $dtTs = strtotime((string)$this->datetime);
        $dtFormatted = ($dtTs !== false) ? date("Y-m-d H:i:s", $dtTs) : date("Y-m-d H:i:s");
        
        if (p4c_query('INSERT INTO `chat_messages` SET 
            `chat_id`       = "'.p4c_escape_string($this->chat_id).'",
            `p4c_shop_id`   = "'.abs((int)$this->p4c_shop_id).'",
            `merchant_id`   = "'.abs((int)$this->merchant_id).'",
            `erocms_von_id` = "'.abs((int)$this->erocms_von_id).'",
            `erocms_an_id`  = "'.abs((int)$this->erocms_an_id).'",
            `von`           = "'.p4c_escape_string($this->von).'",
            `von_id`        = "'.abs((int)$this->von_id).'",
            `an_id`         = "'.abs((int)$this->an_id).'",
            `message_price` = "'.abs((float)$this->message_price).'",
            `actor_commision`           = "'.abs((float)$this->actor_commision).'",
            `actor_commision_percent`   = "'.abs((float)$this->actor_commision_percent).'",
            `message`       = "'.p4c_escape_string($message).'",
            `datetime`      = "'.$dtFormatted.'",
            `is_mobile`     = "'.abs((int)$this->is_mobile).'",
            `systemnachricht` = "'.abs((int)$this->systemnachricht).'",
            `gelesen`       = "'.abs((int)$this->gelesen).'",
            `beantwortet`   = "'.abs((int)$this->beantwortet).'",
            `geloescht`     = "'.abs((int)$this->geloescht).'";',__FILE__,__LINE__)) {
            
            $insert_id_chat_messages = p4c_insert_id();
            
            p4c_query('INSERT INTO `chat_messages_history` SET 
                `chat_id`       = "'.p4c_escape_string($this->chat_id).'",
                `p4c_shop_id`   = "'.abs((int)$this->p4c_shop_id).'",
                `merchant_id`   = "'.abs((int)$this->merchant_id).'",
                `erocms_von_id` = "'.abs((int)$this->erocms_von_id).'",
                `erocms_an_id`  = "'.abs((int)$this->erocms_an_id).'",
                `von`           = "'.p4c_escape_string($this->von).'",
                `von_id`        = "'.abs((int)$this->von_id).'",
                `an_id`         = "'.abs((int)$this->an_id).'",
                `message_price` = "'.abs((float)$this->message_price).'",
                `actor_commision`           = "'.abs((float)$this->actor_commision).'",
                `actor_commision_percent`   = "'.abs((float)$this->actor_commision_percent).'",
                `message`       = "'.p4c_escape_string($message).'",
                `datetime`      = "'.$dtFormatted.'",
                `is_mobile`     = "'.abs((int)$this->is_mobile).'",
                `systemnachricht` = "'.abs((int)$this->systemnachricht).'",
                `gelesen`       = "'.abs((int)$this->gelesen).'",
                `beantwortet`   = "'.abs((int)$this->beantwortet).'",
                `geloescht`     = "'.abs((int)$this->geloescht).'";',__FILE__,__LINE__);
            
            $insert_id_chat_messages_history = p4c_insert_id();
            
            if($this->von === 'actor' AND $this->send_to === 'erocms') {
                return $this->send_to_erocms($insert_id_chat_messages, $insert_id_chat_messages_history, $this->chat_id, $this->p4c_shop_id, $this->von_id);
            } else {
                
                p4c_query("UPDATE `chat_messages` SET `gelesen` = '0' WHERE `id`='".abs((int)$insert_id_chat_messages)."' LIMIT 1;",__FILE__,__LINE__);
                p4c_query("UPDATE `chat_messages_history` SET `gelesen` = '0' WHERE `id`='".abs((int)$insert_id_chat_messages_history)."' LIMIT 1;",__FILE__,__LINE__);
                
                return 'ok';
            }
        }
    }
    
    public function send_to_erocms($insert_id_chat_messages, $insert_id_chat_messages_history, $chat_id, $p4c_shop_id, $p4c_actor_id) {
        global $class_errorlog;
        
        $merchant = new Merchant($this->mysql,$this->von_id);

        $search = array('http://', 'https://', 'www.');
        $domain = str_replace($search, '', (string)$this->domain);
        
        $rs_messenger_sync = p4c_query("SELECT AES_DECRYPT(`apikey`, '".AES_KEY."') FROM `messenger_sync` WHERE `actor_id`='".abs((int)$p4c_actor_id)."' AND `p4c_shop_id`='".abs((int)$p4c_shop_id)."' LIMIT 1;",__FILE__,__LINE__);

        $param = array(
            "api_key"   => (string)p4c_result($rs_messenger_sync, 0),
            "von"       => $this->von,
            "von_id"    => $this->erocms_von_id,
            "an_id"     => $this->erocms_an_id,
            "message"   => $this->message
        );

        $param = array_filter($param, static function ($v) {
            return $v !== null && $v !== false && strlen((string)$v) > 0;
        }); // leere Einträge Entfernen
        ksort($param); // Alphabetische Sortierung
        $query = http_build_query($param);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://'.$domain.'/erocloud_api/get_new_message.php?'.$query);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1); 
        curl_setopt($ch, CURLOPT_REFERER, URL);
        curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
        $data = curl_exec($ch);
        if($data != false) {
            if ($data === 'ok') {
                p4c_query("UPDATE `actor_member_info` SET `marked_as_unanswered`='0' WHERE `chat_id` = '".p4c_escape_string($chat_id)."' LIMIT 1;",__FILE__,__LINE__);
                p4c_query("UPDATE `chat_messages` SET `gelesen`='0' WHERE `id`='".abs((int)$insert_id_chat_messages)."' LIMIT 1;",__FILE__,__LINE__);
                p4c_query("UPDATE `chat_messages_history` SET `gelesen`='0' WHERE `id`='".abs((int)$insert_id_chat_messages_history)."' LIMIT 1;",__FILE__,__LINE__);
            } else {
                $class_errorlog->log($data."\n".'https://'.$domain.'/erocloud_api/get_new_message.php?'.$query,__FILE__,__LINE__);
            }
        }
        unset($ch);        
    }
}