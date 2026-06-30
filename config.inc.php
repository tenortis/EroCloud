<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");
	
/*
open_basedir
%HOME%/cloud_storage/:%HOME%/htdocs/:%HOME%/apps/:%HOME%/priv/:%HOME%/tmp/:/usr/share/pear/:/usr/share/php/:/tmp/
*/
	
	
// Datenbank
#$config['db_host'] = 'localhost';
$config['db_user'] = 'xfjsgebccu';
$config['db_pass'] = 'UcijLakdedtan#';
$config['db_name'] = 'btuzunczvw';
// Datenbank
define("DB_HOST",   'localhost');
define("DB_NAME",   $config['db_name']);
define("DB_USER",   $config['db_user']);
define("DB_PASS",   $config['db_pass']);

// amoredea.com -Datenbank
define("DB_NAME_AMOREDEA", "usr_web2_1");
define("DB_USER_AMOREDEA", "web2");
define("DB_PASS_AMOREDEA", "4oksavCewfEcceg");

// E-Mail Adressen
define('SUPPORT_EMAIL','techsupport@pay4coins.com');
define('TECHSUPPORT_EMAIL','techsupport@pay4coins.com');

// Telefon
define('SUPPORT_PHONE_NUMBER','+49 (0)6106 625 918 27');

// Domain
define('COMPANYNAME','CIPA MEDIA S.L.');
define('PROJECTNAME','EroCloud');

$is_local = false;
if (isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'erocloud.local') !== false || 
    $_SERVER['HTTP_HOST'] === 'localhost' || 
    $_SERVER['HTTP_HOST'] === '127.0.0.1'
)) {
    $is_local = true;
} elseif (DIRECTORY_SEPARATOR === '\\') {
    $is_local = true;
}

if ($is_local) {
    define('DOMAIN',    'erocloud.local');
    define('URL',       'https://erocloud.local');
} else {
    define('DOMAIN',    'erocloud.net');
    define('URL',       'https://erocloud.net');
}

// amoredeoa
define('amoredea_URL','https://amoredea.com');

// Pay4Coins
define('Pay4Coins_URL','https://pay4coins.com');
define('Pay4Coins_ACP_URL','https://acp.pay4coins.com');
define('Pay4Coins_API_URL','https://api.pay4coins.com');
define('Pay4Coins_MCP_URL','https://merchant.pay4coins.com');

// ACP Domain
if ($is_local) {
    define('ACP_DOMAIN','acp.erocloud.local');
    define('ACP_URL',   'https://acp.erocloud.local');
} else {
    define('ACP_DOMAIN','acp.erocloud.net');
    define('ACP_URL',   'https://acp.erocloud.net');
}
define('ACP_SESSION_DURATION', strtotime("-30 minutes")); // Nach diesen Minuten inaktivitï¿½t, wird der Admin automatisch abgemeldet

// MCP Domain (Merchant-Control-Panel)
if ($is_local) {
    define('MCP_DOMAIN','erocloud.local');
    define('MCP_URL',   'https://erocloud.local');
} else {
    define('MCP_DOMAIN','erocloud.net');
    define('MCP_URL',   'https://erocloud.net');
}
define('MCP_SESSION_DURATION', strtotime("-180 minutes")); // Nach diesen Minuten inaktivitï¿½t, wird der Admin automatisch abgemeldet

// API Domain
if ($is_local) {
    define('API_DOMAIN','api.erocloud.local');
    define('API_URL',   'https://api.erocloud.local');
} else {
    define('API_DOMAIN','api.erocloud.net');
    define('API_URL',   'https://api.erocloud.net');
}

// Ads Domain
if ($is_local) {
    define('ADS_DOMAIN','www1.erocloud.local');
    define('ADS_URL',   'https://www1.erocloud.local');
} else {
    define('ADS_DOMAIN','www1.erocloud.net');
    define('ADS_URL',   'https://www1.erocloud.net');
}

// Login Domain
define('LOGIN_DOMAIN','login.pay4coins.com');
define('LOGIN_URL',   'https://login.pay4coins.com');

define('SOURCEDIR', dirname(__FILE__));
define('ACP_DIR', SOURCEDIR.'/acp');
define('API_DIR', SOURCEDIR.'/api');
define('MCP_DIR', SOURCEDIR.'/mcp');

// Temporï¿½res Verzeichnis fï¿½r z.B. Sessions
define('TEMP_DIR', dirname(dirname(__FILE__)).'/tmp');

// Pfad in welchem die Platten (Verzeichnise fï¿½r die Filme) eingebunden sind
define('MOVIES_PATH', dirname(dirname(__FILE__)));
// Verzeichnis in welches aktuell Filme geladen werden sollen
define('MOVIES_DEFAULT_DIR', 'cloud_storage'); 

// Pfad in welchem die Platten (Verzeichnise fï¿½r die Filme) eingebunden sind
define('PHOTO_ALBUMS_PATH', dirname(dirname(__FILE__)));
// Verzeichnis in welches aktuell Fotoalben geladen werden sollen
define('PHOTO_ALBUMS_DEFAULT_DIR', 'cloud_storage/photo_albums');

// Pfad in welchem die Banner (Verzeichnise fï¿½r die Filme) eingebunden sind
define('ADS_PATH', dirname(dirname(__FILE__)));
// Verzeichnis in welches aktuell Banner geladen werden sollen
define('ADS_DEFAULT_DIR', 'cloud_storage/ads');

define('FFMPEG_PATH', '/usr/bin');
define('FFMPEG_SIMULTANEOUS_CONV', 2);

// Pfad in welchem die Platten (Verzeichnise fï¿½r die Dateien vom Merchant und Messenger) eingebunden sind
define('PROFILE_IMAGE_PATH', dirname(dirname(__FILE__)));
// Verzeichnis in welches aktuell Profilbilder geladen werden sollen
define('MERCHANT_DEFAULT_DIR', 'priv/merchant_files'); 

//Live-Cam
#define('RTMP_STREASERVER_1', 'rtmp://stream.erocms.net/show');
#define('HLS_STREASERVER_1', 'https://stream.erocms.net/hls/');
#define('RTMP_STREASERVER_1', 'rtmp://stream.me-on.de/convert');
#define('HLS_STREASERVER_1', 'https://stream.me-on.de/hls/');

$streamserver_ary = [
    
    'server1' => [
        'name' => 'Server 1',
        'rtmp_url' => 'rtmp://stream.me-on.de/convert',
        'http_url' => 'https://stream.me-on.de/hls/'
    ],
    
    'server2' => [
        'name' => 'Server 2',
        'rtmp_url' => 'rtmp://stream.erocms.net/convert',
        'http_url' => 'https://stream.erocms.net/hls/'
    ]

];

define('STREAMSERVER', $streamserver_ary);

// AES DB KEY
define("AES_KEY", "q8nV1T46FigiD8fg8w6sTDjOPa206VH6"); // NOT CHANGE!!!!!

define("RECAPTCHA_SITEKEY", "6LfMi18UAAAAAJOy8DEhTLQessYKhQdkT9jlrXks");
define("RECAPTCHA_SECRETKEY", "6LfMi18UAAAAADeyost-9ch7QnuQKRksftezXfok");

ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', SOURCEDIR.'/log/.errorlog');

if (function_exists('date_default_timezone_set')) {
   date_default_timezone_set('Europe/Berlin');
}

$formattedMonthArray = array(
    "01" => "Januar",
    "02" => "Februar",
    "03" => "M&auml;rz",
    "04" => "April",
    "05" => "Mai",
    "06" => "Juni",
    "07" => "Juli",
    "08" => "August",
    "09" => "September",
    "10" => "Oktober",
    "11" => "November",
    "12" => "Dezember"
);

// amoredea.com AES DB Key
define("AMOREDEA_AES_KEY",   "QOW56PvA9mKUgksFcV014YZ78P3wbHM2");
define("AMOREDEA_P4C_SHOPID", 50311);


// Für cURL
$user_agents = array(
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 15_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/97.0.4692.72 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (iPad; CPU OS 15_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/97.0.4692.72 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:95.0) Gecko/20100101 Firefox/95.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 12.1; rv:95.0) Gecko/20100101 Firefox/95.0',
    'Mozilla/5.0 (X11; Linux i686; rv:95.0) Gecko/20100101 Firefox/95.0',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 12_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/40.0 Mobile/15E148 Safari/605.1.15',
    'Mozilla/5.0 (iPad; CPU OS 12_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/40.0 Mobile/15E148 Safari/605.1.15',
    'Mozilla/5.0 (Android 12; Mobile; rv:68.0) Gecko/68.0 Firefox/95.0'
);


?>