<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

// Session erstellen und prüfen
function p4c_session_start($session_write_close=false) {
    
    // Session starten
    if ($session_write_close === true) {
        $ok = session_start(['read_and_close' => true]);
    } else {
        $ok = session_start();
    }
  
    /** Schutz vor Session Hijacking **/
    if (!isset($_SESSION['initiated'])){  
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
 
    // prüfe ob Session fehlerhaft ist. Wenn nicht, neue session generieren 
    if(!$ok){
        session_regenerate_id(true);
    }
}

function is_logged_in($area) {
    /** $area => "acp" AdminControlPanel **/ 
    
    /** Passwort verschlüsseln 
    $salt 		= mt_rand();
    $saltedHash	= saltPassword('ganh6HzC%BN%', $salt);
    $password   = $saltedHash.':'.$salt;
    echo $password;
    **/    
    
    if ($area == 'acp') {
        if (isset($_SESSION['logged_id']) AND $_SESSION['logged_id'] == 'acp')  {
            if (isset($_SESSION['last_activity']) AND $_SESSION['last_activity'] >= ACP_SESSION_DURATION) {
                $_SESSION['last_activity'] = time();            
                return true;
            
            // Ausloggen wenn Sitzung zu alt ist      
            } else {
                if (isset($_SESSION['employee_id'])) {
                    unset($_SESSION);
                    session_destroy();        
                }
            }
        } 
    } else if ($area == 'mcp') {
        if (isset($_SESSION['logged_id']) AND $_SESSION['logged_id'] == 'mcp')  {
            if (isset($_SESSION['last_activity']) AND $_SESSION['last_activity'] >= MCP_SESSION_DURATION) {
                $_SESSION['last_activity'] = time();            
                return true;
            
            // Ausloggen wenn Sitzung zu alt ist      
            } else {
                if (isset($_SESSION['merchant_id'])) {
                    unset($_SESSION);
                    session_destroy();        
                }
            }
        } 
    }
       
    return false;
}

function saltPassword($password, $salt)	{
     return hash('sha512', $password.$salt);
}

function hash_password($new_password) {
    $new_password  = trim(htmlspecialchars(strip_tags($new_password),ENT_QUOTES, 'ISO-8859-1'));
    $salt           = mt_rand();
    $saltedHash	   = saltPassword($new_password, $salt);
    return $saltedHash.':'.$salt;
}

/** Prüfen ob Merchant existiert **/
function check_merchant_exists($merchant_id,$file,$line) {
    $rs_merchant = p4c_query("SELECT * FROM `merchants` WHERE `id`='".abs($merchant_id)."' LIMIT 1;",$file,$line);
    if (p4c_num_rows($rs_merchant) == 0) {die("Merchant nicht bekannt.");}
    return p4c_fetch_object($rs_merchant);
}


// eigene MySQL-Funktionen
function p4c_fetch_array($string) {
	global $mysql;
	return $mysql->fetch_array($string);
}

function p4c_fetch_object($string) {
	global $mysql;
	return $mysql->fetch_object($string);
}

function p4c_fetch_row($string) {
	global $mysql;
	return $mysql->fetch_row($string);
}

function p4c_num_rows($string, $file=__FILE__, $line=__LINE__) {
	global $mysql;
	return $mysql->num_rows($string, $file, $line);
}

function p4c_query($string, $file=__FILE__, $line=__LINE__){
	global $db_server, $class_errorlog, $mysql;
	$res = $mysql->query("$string", $file, $line);
	return $res;
}

function p4c_escape_string($string) {
    global $mysql;
	return $mysql->real_escape_string($string);
}

function p4c_affected_rows() {
    global $mysql;
	return $mysql->affected_rows();
}

function p4c_insert_id() {
    global $mysql;
	return $mysql->insert_id();
}
    
function p4c_close($string) {
    global $mysql;
	return $mysql->close($string);       
}

function p4c_result($string, $row, $field=0) {
    global $mysql;
    return $mysql->result($string, $row, $field);
}


// Zufallscode $len = länge zb. 5 
function make_seed(){ 
    list($usec , $sec) = explode (' ', microtime()); 
    return (float) $sec + ((float) $usec * 100000); 
}
function randomString($len) { 
    srand(make_seed());  

    //Der String $possible enthält alle Zeichen, die verwendet werden sollen 
    $possible="ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789"; 
    $str=""; 
    while(strlen($str)<$len) { 
        $str.=substr($possible,(rand()%(strlen($possible))),1); 
    } 
    return($str); 
}

// PHP Fehlermeldung loggen
function p4c_errorlog($error) {
    global $class_errorlog;

	if (is_string($error)) { 
		$error=trim($error);
	}
    
	if(!empty($error) AND !empty($error['file'])) {
		$class_errorlog->log($error['message'],$error['file'],$error['line']);
	}
    
}

function getUserIP() {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
    }
}

function getBrowserFingerprint() {
    $client_ip = getUserIP();
    $useragent = @$_SERVER['HTTP_USER_AGENT'];
    $accept   = @$_SERVER['HTTP_ACCEPT'];
    if (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) {$charset = $_SERVER['HTTP_ACCEPT_CHARSET'];} else {$charset='';}
    $encoding = @$_SERVER['HTTP_ACCEPT_ENCODING'];
    $language = @$_SERVER['HTTP_ACCEPT_LANGUAGE'];
    $data = '';
    $data .= $client_ip;
    $data .= $useragent;
    $data .= $accept;
    $data .= $charset;
    $data .= $encoding;
    $data .= $language;
    /* Apply SHA256 hash to the browser fingerprint */
    $hash = hash('sha256', $data);
    return $hash;
}

function getBrowserName() {

    $ExactBrowserNameUA=$_SERVER['HTTP_USER_AGENT'];

    if (strpos(strtolower($ExactBrowserNameUA), "safari/") and strpos(strtolower($ExactBrowserNameUA), "opr/")) {
        // OPERA
        $ExactBrowserNameBR="Opera";
    } elseIf (strpos(strtolower($ExactBrowserNameUA), "safari/") and strpos(strtolower($ExactBrowserNameUA), "chrome/")) {
        // CHROME
        $ExactBrowserNameBR="Chrome";
    } elseIf (strpos(strtolower($ExactBrowserNameUA), "msie")) {
        // INTERNET EXPLORER
        $ExactBrowserNameBR="Internet Explorer";
    } elseIf (strpos(strtolower($ExactBrowserNameUA), "firefox/")) {
        // FIREFOX
        $ExactBrowserNameBR="Firefox";
    } elseIf (strpos(strtolower($ExactBrowserNameUA), "safari/") and strpos(strtolower($ExactBrowserNameUA), "opr/")==false and strpos(strtolower($ExactBrowserNameUA), "chrome/")==false) {
        // SAFARI
        $ExactBrowserNameBR="Safari";
    } else {
        // OUT OF DATA
        $ExactBrowserNameBR="OUT OF DATA";
    };

    return $ExactBrowserNameBR;
}

function loginHash($partner_id='') {
    $data = getBrowserFingerprint().$partner_id;
    return hash('SHA512', $data);
}

function utf8decodeArray($array) {
    foreach($array as $key =>  $value){
        if(is_array($value)){
            $array[$key] = utf8decodeArray($value);
        } elseif(mb_detect_encoding($value, 'UTF-8', true)) {
            $array[$key] = utf8_decode($value);
        }
    }
    
    return $array;
}


function seo_url($string) {
    global $class_errorlog;

    if (!empty($string)) {
        
        $string = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $string);

        $search = array("ä", "ö", "ü", "ß", "Ä", "Ö", "Ñ",
                        "Ü", "&", "é", "á", "ó", "ñ",
                        " :)", " :D", " :-)", " :P",
                        " :O", " ;D", " ;)", " ^^", 
                        " :|", " :-/", ":)", ":D", 
                        ":-)", ":P", ":O", ";D", ";)", 
                        "^^", ":|", ":-/", "(", ")", "[", "]", 
                        "<", ">", "!", "\"", "§", "$", "%", "&", 
                        "/", "(", ")", "=", "?", "`", "?", "*", "'", 
                        "_", ":", ";", "²", "³", "{", "}", 
                        "\\", "~", "#", "+", ".", ",", 
                        "=", ":", "=)");
        $replace = array("ae", "oe", "ue", "ss", "Ae", "Oe", "N",
                         "Ue", "und", "e", "a", "o", "n",
                         "", "", "", "",
                         "", "", "", "",
                         "", "", "", "",
                         "", "", "", "", "",
                         "", "", "", "", "", "", "",
                         "", "", "", "", "", "", "", "",
                         "", "", "", "", "", "", "", "", "",
                         "", "", "", "", "", "", "",
                         "", "", "", "", "", "",
                         "", "", "",);
        
        $string = str_replace($search, $replace, $string);  

        $string = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $string);
        $string = str_replace(array(" ", "_", "_-_"), "-", $string);
        $string = str_replace(array("&auml;", "&Auml;", "ä"), "ae", $string);
        $string = str_replace(array("&ouml;", "&Ouml;"), "oe", $string);
        $string = str_replace(array("&uuml;", "&Uuml;"), "ue", $string);
        $string = str_replace(array("&szlig;"), "ss", $string);
        $string = str_replace(array("`", "´", "'"), "", $string);    
        $string = str_replace("_&_", "-", $string);
        $string = str_replace(array(":", "°", "^", "!", '"', "§", "$", "%", "&", "/", "(", ")", "=", "?", "{", "}", "[", "]", ",", ".", ">", "<", "|", "*", "+", "~", "#", "@", "µ"), "-", $string);

        // Wenn alles entfernt wurde, bleiben vermutlich mehrere - (Minus) in Reihe zurueck. Diese muessen bis auf eins reduziert werden. 
        $string = preg_replace('~([-]{2,})~', '-', $string);

        // Wenn erstes zeichen ein "-", dann abschneiden
        if (substr($string, 0, 1) == '-') {$string = substr($string, 1);}

        // Wenn letztes zeichen ein "-", dann abschneiden 
        if (substr($string, -1) == '-') {$string = substr($string, 0, -1);}

        // leerzeichen am Anfang und Ende entfernen 
        $string = trim($string);
    }
	
    return $string;
}


function movie_checksum($movie) {
    return md5(
        $movie['merchant_id'].
        $movie['fsk16'].
        $movie['fsk18'].
        $movie['title'].
        $movie['description'].
        $movie['meta_title'].
        $movie['meta_description'].
        $movie['seo_url'].
        $movie['online_at'].
        $movie['amount_second'].
        $movie['amount_own'].
        $movie['amount_webmaster'].
        $movie['as_download'].
        $movie['amount_download']
    );
}

function photo_album_checksum($album) {
    return md5(
        $album['merchant_id'].
        $album['number_of_photos'].
        $album['fsk16'].
        $album['fsk18'].
        $album['title'].
        $album['description'].
        $album['meta_title'].
        $album['meta_description'].
        $album['seo_url'].
        $album['online_at'].
        $album['amount_webmaster'].
        $album['amount_download']
    );
}

// MD5-Hash for changing Profile-Check
function actor_checksum($actor_id) {
    include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
    $actor = new Actor($actor_id);

    $md5_string = 
        $actor->get("is_displayed_as").
        $actor->get("pn_amount").
        $actor->get("pn_free_if_webcam").
        $actor->get("cam_amount").
        $actor->get("usercam_if_amacam").
        $actor->get("profile_image_fsk16").  
        $actor->get("profile_image_fsk18").
        $actor->get("username").
        $actor->get("status").
        $actor->get("check_gender").
        $actor->get("gender").
        $actor->get("check_age").
        $actor->get("age").
        $actor->get("check_star_sign").
        $actor->get("star_sign").
        $actor->get("check_body_height").
        $actor->get("body_height").
        $actor->get("check_eye_color").
        $actor->get("eye_color").
        $actor->get("check_hair_color").
        $actor->get("hair_color").
        $actor->get("check_body_weight").
        $actor->get("body_weight").
        $actor->get("check_cup_size").
        $actor->get("cup_size").
        $actor->get("check_shaven").
        $actor->get("shaven").
        $actor->get("check_look").
        $actor->get("look").
        $actor->get("check_marital_status").
        $actor->get("marital_status").
        $actor->get("check_sexual_orientation").
        $actor->get("sexual_orientation").
        $actor->get("check_looking_for").
        $actor->get("looking_for").
        $actor->get("check_interests").
        $actor->get("interests").
        $actor->get("check_sexual_preferences").
        $actor->get("sexual_preferences").
        $actor->get("check_about_me").
        $actor->get("about_me");

    return md5($md5_string); 
}

function print_xml($api) {
    global $config;
    echo json_encode($api);
    exit;
}


// Der Streaming-Key erstellt sich wie folgt:
function generate_user_streaming_key($array) {
    /** IN DIESER FUNKTION NICHTS ÄNDERN !!! **/
    $param = array(
        "movie_id"      => $array['movie_id'],
        "user_id"       => $array['user_id'],
        "shop_id"       => $array['shop_id'],
        "buy_as"        => $array['buy_as']
    );

    $param  = array_filter($param, "strlen"); // leere Eintraege Entfernen
    ksort($param);
    $param  = http_build_query($param);
    
    return hash("sha512", $param);
}

function generate_user_album_key($array) {
    /** IN DIESER FUNKTION NICHTS ÄNDERN !!! **/
    $param = array(
        "album_id"      => $array['album_id'],
        "user_id"       => $array['user_id'],
        "shop_id"       => $array['shop_id'],
        "buy_as"        => $array['buy_as']
    );

    $param  = array_filter($param, "strlen"); // leere Eintraege Entfernen
    ksort($param);
    $param  = http_build_query($param);
    
    return hash("sha512", $param);
}

// Zugriff auf die API nur von gelistetet IPs zulassen
function check_ip_whitelist($ip) {
    $whitelist_ary = array (
        '91.184.46.111', // 
        '91.184.46.112', // srv1

        '91.184.62.20',  // srv2
        '91.184.62.196',  // srv2

        '91.184.62.132', // srv3
        '91.184.62.65',  // srv3

        '91.184.62.165', // srv4
        '91.184.62.191', // srv4
        
        '91.184.62.136', // srv5
        '91.184.62.166', // srv5        
        
        '91.184.62.151', // srv9 -> ladyjulina.com
        
        '91.184.62.140', // kitty-core.net
        '91.184.62.173', // annabalmassina.com
        '91.184.62.8',   // orient-bear.com
        
        '91.184.62.199',  // erocloud.net 
        
        '91.184.62.205', // node1
        '91.184.62.206', // node2
        '91.184.62.207' // node3
    );
    
    if (!in_array($ip, $whitelist_ary, true)) {
	return false;
    }
    
    return true;
}

function convertBytes($size, $sourceUnit = 'B', $targetUnit = 'MB') { 
    $units = array(
        'bit' => 0,
          'B' => 1,
         'KB' => 2,
         'MB' => 3,
         'GB' => 4,
         'TB' => 5,
         'PB' => 6,
         'EB' => 7,
         'ZB' => 8,
         'YB' => 9
    );
 
    if( $units[$sourceUnit] <= $units[$targetUnit] ) {
        for( $i = $units[$sourceUnit]; $size >= 1024; $i++ ) {
            if( $i === 0 ) {
                $size /= 8;
            } else {
                $size /= 1024;
            }
        }
    } else {
        for( $i = $units[$sourceUnit]; $i > $units[$targetUnit]; $i-- ) {
            if( $i === 1 ) {
                $size *= 8;
            } else {
                $size *= 1024;
            }
        }
    }
    return number_format(round($size, 2),2,',','').' '. array_keys($units)[$i];
    
    #echo convertBytes( 1234567890 ); // 1.15 GB
    #echo convertBytes( 1.149780945852399, 'GB', 'B' ); // 1234567890 B
} 

function p4c_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1); 
    curl_setopt($ch, CURLOPT_REFERER, URL);
    curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
    $data = curl_exec($ch);
    return $data;
    curl_close($ch);
}


function validate_url($url) {
    $url = trim($url);
   
    $urlregex = "^(https?|ftp)\:\/\/([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?[a-z0-9+\$_-]+(\.[a-z0-9+\$_-]+)*(\:[0-9]{2,5})?(\/([a-z0-9+\$_-]\.?)+)*\/?(\?[a-z+&\$_.-][a-z0-9;:@/&%=+\$_.-]*)?(#[a-z_.-][a-z0-9+\$_.-]*)?\$";
    if (eregi($urlregex, $url)) {
        return true;
    } else {
        return false;
    }

}

function copyDir($sSourcePath, $sTargetPath) {  
    if (is_dir($sSourcePath) && !is_dir($sTargetPath)) {
        mkdir($sTargetPath, 0755);
        foreach ($oIterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sSourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST) as $oItem)
        {
            if ($oItem->isDir()) {
                mkdir($sTargetPath.DIRECTORY_SEPARATOR.$oIterator->getSubPathName());
            } else {
                copy($oItem, $sTargetPath.DIRECTORY_SEPARATOR.$oIterator->getSubPathName());
            }
        }
        return true;
    }
    return false;
}


function utf8_to_latin1swedishci($string = '') {

    // https://www.utf8-chartable.de/unicode-utf8-table.pl?start=8192&number=128&utf8=string-literal
    // https://www.toptal.com/designers/htmlarrows/punctuation/
    
    if (trim($string) != '') {
        # \xE2\x80\x8B
        $string = mb_ereg_replace("\xe2\x80\x80", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x82", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x83", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x84", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x85", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x86", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x87", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x88", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x89", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x8a", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x8b", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x8c", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x8d", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x8e", "", $string);
        $string = mb_ereg_replace("\xe2\x80\x8f", "", $string);
 
        $string = mb_ereg_replace("\xe2\x80\x90", "&#8208;", $string);
        $string = mb_ereg_replace("\xe2\x80\x91", "&#8209;", $string);
        $string = mb_ereg_replace("\xe2\x80\x92", "&#8210;", $string);
        $string = mb_ereg_replace("\xe2\x80\x93", "&#8211;", $string);
        $string = mb_ereg_replace("\xe2\x80\x94", "&#8212;", $string);
        $string = mb_ereg_replace("\xe2\x80\x95", "&#8213;", $string);
        $string = mb_ereg_replace("\xe2\x80\x96", "&#8214;", $string);
        $string = mb_ereg_replace("\xe2\x80\x97", "&#8215;", $string);
        $string = mb_ereg_replace("\xe2\x80\x98", "&#8216;", $string);
        $string = mb_ereg_replace("\xe2\x80\x99", "&#8217;", $string);
        $string = mb_ereg_replace("\xe2\x80\x9a", "&#8218;", $string);
        $string = mb_ereg_replace("\xe2\x80\x9b", "&#8219;", $string);
        $string = mb_ereg_replace("\xe2\x80\x9c", "&#8220;", $string);
        $string = mb_ereg_replace("\xe2\x80\x9d", "&#8221;", $string);
        $string = mb_ereg_replace("\xe2\x80\x9e", "&#8222;", $string);
    }
    return $string;
}

    
?>