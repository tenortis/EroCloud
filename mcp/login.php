<?php
 
define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['pid']) OR strlen($_GET['pid']) != 10) {
    header('Location: '.LOGIN_URL.'?ref=erocloud');
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit;
}

$get_pid = trim(preg_replace('/[^a-z0-9]/i', '', $_GET['pid']));
$login_hash = loginHash($get_pid);


/** Check Login **/
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, Pay4Coins_API_URL.'/remote/merchant_login.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, 'h='.$login_hash.'&pid='.$get_pid);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
$data = curl_exec($ch);

curl_close($ch);

if ($data == 'ok') {
    
    /** GET Merchant data **/
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, Pay4Coins_API_URL.'/remote/erocloud_merchant_data.php');
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'h='.$login_hash.'&pid='.$get_pid);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME." - ".COMPANYNAME." - ".URL);
    $merchant_data = curl_exec($ch);
    curl_close($ch);
    
    // Check if result is XML    
    preg_match('/<pay4coins>(.*)<\/pay4coins>/smU',$merchant_data, $xml_data);
    if (isset($xml_data[1])) {

        if($xml = @simplexml_load_string($merchant_data)) {

            $merchant_xml_obj = $xml->user;

            // Check if merchant exists => update
            $rs_user = p4c_query("SELECT `id`, `api_key` FROM `merchants` WHERE `partner_id`='".p4c_escape_string($merchant_xml_obj->partner_id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_user) > 0) {
                $user_ary = p4c_fetch_object($rs_user);

                $merchant_id = abs($user_ary->id);

                // If api_key not exist
                if (trim($user_ary->api_key) == '') {
                    p4c_query("UPDATE `merchants` SET
                        `api_key`     = AES_ENCRYPT('".strtoupper(randomString(32))."','".AES_KEY."')
                    WHERE `partner_id` = '".p4c_escape_string($merchant_xml_obj->partner_id)."' LIMIT 1;",__FILE__,__LINE__);
                }

                p4c_query("UPDATE `merchants` SET
                    `username`      = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->username)."','".AES_KEY."'),
                    `email`         = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->email)."','".AES_KEY."'),
                    `salutation`    = '".p4c_escape_string($merchant_xml_obj->salutation)."',
                    `firstname`     = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->firstname)."','".AES_KEY."'),
                    `surname`       = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->surname)."','".AES_KEY."'),
                    `birthday`       = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->birthday)."','".AES_KEY."')
                WHERE `partner_id` = '".p4c_escape_string($merchant_xml_obj->partner_id)."' LIMIT 1;",__FILE__,__LINE__);

            // if merchant not exists - create
            } else {

                if(p4c_query("INSERT INTO `merchants` SET
                    `api_key`     = AES_ENCRYPT('".strtoupper(randomString(32))."','".AES_KEY."'),
                    `partner_id`    = '".p4c_escape_string($merchant_xml_obj->partner_id)."', 
                    `username`      = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->username)."','".AES_KEY."'),
                    `email`         = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->email)."','".AES_KEY."'),
                    `salutation`    = '".p4c_escape_string($merchant_xml_obj->salutation)."',
                    `firstname`     = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->firstname)."','".AES_KEY."'),
                    `surname`       = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->surname)."','".AES_KEY."'),
                    `birthday`       = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->birthday)."','".AES_KEY."'),
                    `last_ip`       = AES_ENCRYPT('".p4c_escape_string(getUserIP())."','".AES_KEY."'),
                    `last_ip_datetime`='".date("Y-m-d H:i:s")."'
                    ;",__FILE__,__LINE__)
                ) {
                    $merchant_id = p4c_insert_id();
                }
            }


            if (isset($merchant_id) AND trim($merchant_id) != '') {
                $merchant = new Merchant($mysql,$merchant_id);

                $_SESSION['logged_in_as'] = 'merchant';
                $_SESSION['logged_id'] = 'mcp';
                $_SESSION['merchant_id'] = $merchant->id();
                $_SESSION['merchant_username'] = $merchant->username('aes_decrypt');
                $_SESSION['last_activity'] = time();

                $arr = array (
                    'pid' => $merchant->partner_id(),
                    'sid' => session_id()
                );

                setcookie("erocloud", base64_encode(json_encode($arr)), strtotime("+3 month"), '/', '.'.MCP_DOMAIN);
                
                log_action('Am System angemeldet.');

                p4c_query("UPDATE `merchants` SET
                    `username`      = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->username)."','".AES_KEY."'),
                    `email`         = AES_ENCRYPT('".p4c_escape_string($merchant_xml_obj->email)."','".AES_KEY."'),
                    `last_ip`=AES_ENCRYPT('".p4c_escape_string(getUserIP())."','".AES_KEY."'),
                    `last_ip_datetime`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
                
                header('Location: '.MCP_URL.'/Startseite');
                exit;
            }
        }
    }
}

p4c_close(DB_HOST);
header('Location: '.LOGIN_URL.'?logout&ref=erocloud');
p4c_errorlog(error_get_last());
exit;


?>