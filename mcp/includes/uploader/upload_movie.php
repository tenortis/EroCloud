<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */


define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

@set_time_limit(0);
@ini_set('max_execution_time', '0');
header('Content-Type: application/json; charset=utf-8');

$phpFileUploadErrors = array(
    0 => 'Die Datei wurde erfolgreich hochgeladen.',
    1 => 'Deine Videodatei überschreitet das aktuelle Server-Limit (' . ini_get('upload_max_filesize') . '). Bitte verkleinere die Datei etwas und versuche es erneut.',
    2 => 'Deine Videodatei überschreitet die maximal erlaubte Dateigröße von 2,0 GB.',
    3 => 'Die Datei wurde nur teilweise übertragen (Verbindungsabbruch). Bitte prüfe deine Internetverbindung und stelle sicher, dass die Datei lokal auf deinem Gerät gespeichert ist (nicht nur in der Cloud).',
    4 => 'Es wurde keine Datei übertragen. Bitte wähle deine Videodatei erneut aus.',
    6 => 'Server-Fehler: Temporärer Upload-Ordner fehlt. Bitte kontaktiere den Support.',
    7 => 'Server-Fehler: Die Datei konnte nicht gespeichert werden (Speicherplatz/Schreibrechte). Bitte kontaktiere den Support.',
    8 => 'Der Datei-Upload wurde durch eine Server-Erweiterung gestoppt.',
);

function file_error($msg) {
    $custom_error = array();
    $custom_error['jquery-upload-file-error'] = $msg;
    echo json_encode($custom_error);	

    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit; 
}

if (is_logged_in('mcp') === false) {
    @session_write_close();
    file_error('Deine Sitzung ist abgelaufen. Bitte lade die Seite neu, melde dich erneut an und starte den Upload noch einmal.');
}

$merchant_id = abs((int)($_SESSION['merchant_id'] ?? 0));
// Session-Lock sofort freigeben, damit parallele Requests (Keep-Alive) den Upload nicht blockieren
@session_write_close();

function upload_file($rs_movie, $movie_id, $merchant_id) {
    global $fileTypes, $class_errorlog, $ret;

    $fileName = (string)($_FILES["movie"]["name"] ?? '');
    $tmpFileName = (string)($_FILES["movie"]["tmp_name"] ?? '');

    $fileParts = pathinfo($fileName);
    $extension = isset($fileParts['extension']) ? strtolower((string)$fileParts['extension']) : '';

    if (!in_array($extension, $fileTypes, true)) {
        file_error("Falsches Dateiformat!<br />Es sind nur avi, flv, m4v, mkv, mov, mp4, mpg, wmv erlaubt.");
    }

    if ($tmpFileName === '' || !is_uploaded_file($tmpFileName)) {
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): Temporäre Upload-Datei nicht gefunden ({$tmpFileName}).", __FILE__, __LINE__);
        }
        file_error("Die hochgeladene Datei konnte auf dem Server nicht verarbeitet werden. Bitte stelle sicher, dass die Datei lokal auf deinem Gerät liegt und versuche es erneut.");
    }

    $fileinfo = array();
    try {
        include_once(MCP_DIR.'/includes/getid3/getid3.php');
        $getID3 = new getID3;
        $getID3->option_tag_id3v1 = false;
        $getID3->option_tag_id3v2 = false;
        $getID3->option_tag_lyrics3 = false;
        $getID3->option_tag_apetag = false;
        $getID3->option_save_attachments = false;
        $fileinfo = @$getID3->analyze($tmpFileName);
    } catch (Throwable $e) {
        if (isset($class_errorlog)) {
            $class_errorlog->log("getID3 Warnung bei Movie #{$movie_id} (Merchant #{$merchant_id}): " . $e->getMessage(), __FILE__, __LINE__);
        }
        $fileinfo = array();
    }
    
    #getid3_lib::CopyTagsToComments($fileinfo);
    $play_time		= (isset($fileinfo['playtime_string']) && $fileinfo['playtime_string'] !== '') ? (string)$fileinfo['playtime_string'] : '0:00';
    $play_time_sec	= (isset($fileinfo['playtime_seconds']) && is_numeric($fileinfo['playtime_seconds'])) ? (int)round((float)$fileinfo['playtime_seconds']) : 0;
    
    // Prüfe ob Film länger als 120 Sekunden
    
    if ($play_time_sec < 120) {
        #file_error("Der Film muss mindesten eine L&auml;nge von 2 Minuten haben.<br />(Dein Film: ".$play_time_sec." Sekunden)");
    }
    
    if (isset($fileinfo['video']['resolution_x']) AND isset($fileinfo['video']['resolution_y']) AND is_numeric($fileinfo['video']['resolution_x']) AND is_numeric($fileinfo['video']['resolution_y']) AND round((float)$fileinfo['video']['resolution_x']) > 0 AND round((float)$fileinfo['video']['resolution_y']) > 0) {
        $width = (int)round((float)$fileinfo['video']['resolution_x']);
        $height = (int)round((float)$fileinfo['video']['resolution_y']);

        if ($width < 640 OR $height < 480) {
            #file_error("Der Film muss mindesten eine Aufl&ouml;sung von 640x480 (Pixel) haben.");    
        }
    } else {
        // Fallback bei fehlenden/defekten Container-Metadaten (wird bei der FFmpeg-Konvertierung repariert und aktualisiert)
        $width = 1920;
        $height = 1080;
    }

    $movie_ary = p4c_fetch_object($rs_movie);

    $file_name = $movie_id.'_'.$movie_ary->file_id.'.'.$extension;

    // erstelle Speicher für Filme
    $target_dir = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$merchant_id.'/'.$movie_id;
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }
	
    $file_path = $target_dir.'/'.$file_name;
          
    if (@move_uploaded_file($tmpFileName, $file_path)) {
        if (file_exists($file_path)) {
            $resolution = $width.'x'.$height;

            $amount_own = round((float)$play_time_sec * (float)$movie_ary->amount_second);
            
            if (p4c_query("UPDATE `movies` SET
                `filename`          = '".p4c_escape_string($file_name)."',
                `playtime_string`   = '".p4c_escape_string($play_time)."',
                `playtime_seconds`  = '".p4c_escape_string($play_time_sec)."',
                `resolution`        = '".p4c_escape_string($resolution)."',
                `amount_own`        = '".abs((int)$amount_own)."'
                WHERE `id` ='".abs((int)$movie_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' LIMIT 1;",__FILE__,__LINE__)) {
                    
                    if (isset($_POST['released']) AND $_POST['released'] == 'true') {
                        p4c_query("UPDATE `movies` SET
                            `create_datetime` = '".date("Y-m-d H:i:s")."',
                            `movie_checked` = '0000-00-00 00:00:00',
                            `released`='1'
                            WHERE `id`='".abs((int)$movie_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
                    }
                    
                    $ret[] = $fileName;
            }

        } else {
            if (isset($class_errorlog)) {
                $class_errorlog->log("Movie-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): Zieldatei existiert nach move_uploaded_file nicht ({$file_path}).", __FILE__, __LINE__);
            }
            file_error("Die Datei ".htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8')." konnte nicht gespeichert werden. (".__LINE__.")");
        }

    } else {
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): move_uploaded_file fehlgeschlagen nach {$file_path}.", __FILE__, __LINE__);
        }
        file_error("Die Datei ".htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8')." konnte nicht verschoben werden. (".__LINE__.")"); 	
    }
}

$fileTypes = array('avi','flv','m4v','mkv','mov','mp4','mpg','wmv'); // File extensions
$ret = array();

if (isset($_FILES["movie"]) AND isset($_GET['movie_id'])) {
    $movie_id = abs((int)$_GET['movie_id']); 
    
    $rs_movie = p4c_query("SELECT * FROM `movies` WHERE `id`='".abs((int)$movie_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' AND (`filename`='' OR `convert_status` IN ('0', '3')) LIMIT 1;",__FILE__,__LINE__);
    
    // Nur Updaten wenn Datei noch nicht hochgeladen wurde
    if (p4c_num_rows($rs_movie) == 1) {
    	$error = (int)($_FILES["movie"]["error"] ?? 4);
    	if ($error !== 0) {
            if (isset($class_errorlog)) {
                $class_errorlog->log("Movie-Upload PHP-File-Error #{$error} (Merchant #{$merchant_id}, Movie #{$movie_id}, upload_max_filesize=" . ini_get('upload_max_filesize') . ", post_max_size=" . ini_get('post_max_size') . ").", __FILE__, __LINE__);
            }
            $errMsg = isset($phpFileUploadErrors[$error]) ? $phpFileUploadErrors[$error] : ('Upload-Fehler (Code ' . $error . ').');
            file_error($errMsg);
    	}
        
        upload_file($rs_movie, $movie_id, $merchant_id);
    } else {
        $uploadedFileName = isset($_FILES["movie"]["name"]) ? htmlspecialchars((string)$_FILES["movie"]["name"], ENT_QUOTES, 'UTF-8') : '';
        file_error("Der Film-Eintrag wurde nicht gefunden oder für diesen Film wurde bereits eine Videodatei hochgeladen. (".__LINE__.")"); 	
    }

    echo json_encode($ret);
} else {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $movie_id = isset($_GET['movie_id']) ? abs((int)$_GET['movie_id']) : 0;
    if (isset($class_errorlog)) {
        $class_errorlog->log("Movie-Upload abgebrochen (Merchant #{$merchant_id}, Movie #{$movie_id}): \$_FILES['movie'] ist leer. CONTENT_LENGTH={$contentLength}, post_max_size=" . ini_get('post_max_size') . ", upload_max_filesize=" . ini_get('upload_max_filesize'), __FILE__, __LINE__);
    }
    if ($contentLength > 0) {
        file_error('Deine Videodatei (' . number_format($contentLength / (1024 * 1024), 1, ',', '.') . ' MB) überschreitet das aktuelle Server-Limit (post_max_size: ' . ini_get('post_max_size') . ', upload_max_filesize: ' . ini_get('upload_max_filesize') . '). Bitte verkleinere die Datei oder kontaktiere den Support.');
    } else {
        file_error('Es wurden keine Dateidaten empfangen. Bitte stelle sicher, dass deine Videodatei lokal auf deinem Gerät gespeichert ist (nicht nur in iCloud/Google Drive/OneDrive) und versuche es erneut.');
    }
}


 

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>