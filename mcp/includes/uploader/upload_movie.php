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

$fileTypes = array('avi','flv','m4v','mkv','mov','mp4','mpg','wmv'); // File extensions
$ret = array();

/**
 * Analysiert die fertig gespeicherte Videodatei via getID3 und aktualisiert den Datenbank-Eintrag.
 */
function finalize_uploaded_movie($file_path, $file_name, $originalFileName, $movie_ary, $movie_id, $merchant_id, $isReleased) {
    global $class_errorlog, $ret;

    clearstatcache(true, $file_path);
    if (!file_exists($file_path) || (int)@filesize($file_path) <= 0) {
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): Zieldatei existiert nicht oder ist leer ({$file_path}).", __FILE__, __LINE__);
        }
        file_error("Die Datei " . htmlspecialchars((string)$originalFileName, ENT_QUOTES, 'UTF-8') . " konnte nicht gespeichert werden. (" . __LINE__ . ")");
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
        $fileinfo = @$getID3->analyze($file_path);
    } catch (Throwable $e) {
        if (isset($class_errorlog)) {
            $class_errorlog->log("getID3 Warnung bei Movie #{$movie_id} (Merchant #{$merchant_id}): " . $e->getMessage(), __FILE__, __LINE__);
        }
        $fileinfo = array();
    }

    $play_time     = (isset($fileinfo['playtime_string']) && $fileinfo['playtime_string'] !== '') ? (string)$fileinfo['playtime_string'] : '0:00';
    $play_time_sec = (isset($fileinfo['playtime_seconds']) && is_numeric($fileinfo['playtime_seconds'])) ? (int)round((float)$fileinfo['playtime_seconds']) : 0;

    if (isset($fileinfo['video']['resolution_x']) && isset($fileinfo['video']['resolution_y']) && is_numeric($fileinfo['video']['resolution_x']) && is_numeric($fileinfo['video']['resolution_y']) && round((float)$fileinfo['video']['resolution_x']) > 0 && round((float)$fileinfo['video']['resolution_y']) > 0) {
        $width = (int)round((float)$fileinfo['video']['resolution_x']);
        $height = (int)round((float)$fileinfo['video']['resolution_y']);
    } else {
        // Fallback bei fehlenden/defekten Container-Metadaten (wird bei der FFmpeg-Konvertierung repariert und aktualisiert)
        $width = 1920;
        $height = 1080;
    }

    $resolution = $width.'x'.$height;
    $amount_own = round((float)$play_time_sec * (float)$movie_ary->amount_second);

    if (p4c_query("UPDATE `movies` SET
        `filename`          = '".p4c_escape_string($file_name)."',
        `playtime_string`   = '".p4c_escape_string($play_time)."',
        `playtime_seconds`  = '".p4c_escape_string((string)$play_time_sec)."',
        `resolution`        = '".p4c_escape_string($resolution)."',
        `amount_own`        = '".abs((int)$amount_own)."'
        WHERE `id` ='".abs((int)$movie_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' LIMIT 1;",__FILE__,__LINE__)) {

        if ($isReleased) {
            p4c_query("UPDATE `movies` SET
                `create_datetime` = '".date("Y-m-d H:i:s")."',
                `movie_checked` = '0000-00-00 00:00:00',
                `released`='1'
                WHERE `id`='".abs((int)$movie_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
        }

        $ret[] = $originalFileName;
    } else {
        file_error("Die Datei " . htmlspecialchars((string)$originalFileName, ENT_QUOTES, 'UTF-8') . " konnte in der Datenbank nicht aktualisiert werden. (" . __LINE__ . ")");
    }
}

function upload_file($rs_movie, $movie_id, $merchant_id) {
    global $fileTypes, $class_errorlog;

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

    $movie_ary = p4c_fetch_object($rs_movie);
    $file_name = $movie_id.'_'.$movie_ary->file_id.'.'.$extension;

    // erstelle Speicher für Filme
    $target_dir = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$merchant_id.'/'.$movie_id;
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }

    $file_path = $target_dir.'/'.$file_name;

    if (@move_uploaded_file($tmpFileName, $file_path)) {
        $isReleased = (isset($_POST['released']) && $_POST['released'] == 'true') || (isset($_GET['released']) && $_GET['released'] == 'true');
        finalize_uploaded_movie($file_path, $file_name, $fileName, $movie_ary, $movie_id, $merchant_id, $isReleased);
    } else {
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): move_uploaded_file fehlgeschlagen nach {$file_path}.", __FILE__, __LINE__);
        }
        file_error("Die Datei ".htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8')." konnte nicht verschoben werden. (".__LINE__.")"); 	
    }
}

// 1. Chunked-Upload-Modus (5 MB Teilabschnitte via FormData oder php://input)
if (isset($_GET['movie_id'], $_GET['chunk_index'], $_GET['total_chunks'], $_GET['upload_uid'])) {
    $movie_id = abs((int)$_GET['movie_id']);
    $chunkIndex = abs((int)$_GET['chunk_index']);
    $totalChunks = abs((int)$_GET['total_chunks']);
    $chunkOffset = (isset($_GET['chunk_offset']) && is_numeric($_GET['chunk_offset']) && (float)$_GET['chunk_offset'] >= 0) ? (int)$_GET['chunk_offset'] : ($chunkIndex * 5242880);
    $totalFileSize = (isset($_GET['file_size']) && is_numeric($_GET['file_size']) && (float)$_GET['file_size'] > 0) ? (int)$_GET['file_size'] : 0;
    $uploadUid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['upload_uid']);
    $originalFileName = trim((string)($_GET['file_name'] ?? ($_FILES['movie']['name'] ?? '')));
    $isReleased = ((isset($_GET['released']) && $_GET['released'] === 'true') || (isset($_POST['released']) && $_POST['released'] === 'true'));

    if ($totalChunks < 1 || $chunkIndex >= $totalChunks || $uploadUid === '') {
        file_error('Ungültige Upload-Parameter. Bitte lade die Seite neu und versuche es erneut.');
    }

    if ($totalFileSize > 2147483648) {
        file_error('Deine Videodatei überschreitet die maximal erlaubte Dateigröße von 2,0 GB.');
    }

    $fileParts = pathinfo($originalFileName);
    $extension = isset($fileParts['extension']) ? strtolower((string)$fileParts['extension']) : '';
    if (!in_array($extension, $fileTypes, true)) {
        file_error("Falsches Dateiformat!<br />Es sind nur avi, flv, m4v, mkv, mov, mp4, mpg, wmv erlaubt.");
    }

    $rs_movie = p4c_query("SELECT * FROM `movies` WHERE `id`='".abs((int)$movie_id)."' AND `merchant_id`='".abs((int)$merchant_id)."' AND (`filename`='' OR `convert_status` IN ('0', '3')) LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_movie) != 1) {
        file_error("Der Film-Eintrag wurde nicht gefunden oder für diesen Film wurde bereits eine Videodatei hochgeladen. (".__LINE__.")");
    }

    $movie_ary = p4c_fetch_object($rs_movie);
    $target_dir = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$merchant_id.'/'.$movie_id;
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }

    $partFilePath = $target_dir.'/.part_'.$movie_id.'_'.$uploadUid.'.tmp';

    if ($chunkIndex === 0 && $chunkOffset === 0) {
        $staleParts = @glob($target_dir.'/.part_*.tmp');
        if (is_array($staleParts)) {
            foreach ($staleParts as $stalePart) {
                if (is_file($stalePart)) {
                    @unlink($stalePart);
                }
            }
        }
    }

    $inFp = false;
    if (isset($_FILES['movie']) && is_array($_FILES['movie'])) {
        $chunkErr = (int)($_FILES['movie']['error'] ?? 4);
        $chunkTmp = (string)($_FILES['movie']['tmp_name'] ?? '');
        if ($chunkErr === 0 && $chunkTmp !== '' && (is_uploaded_file($chunkTmp) || file_exists($chunkTmp))) {
            $inFp = @fopen($chunkTmp, 'rb');
        } elseif ($chunkErr !== 0 && $chunkErr !== 4) {
            if (isset($class_errorlog)) {
                $class_errorlog->log("Movie-Chunk-Upload PHP-File-Error #{$chunkErr} (Merchant #{$merchant_id}, Movie #{$movie_id}, Chunk {$chunkIndex}/{$totalChunks}).", __FILE__, __LINE__);
            }
            $errMsg = isset($phpFileUploadErrors[$chunkErr]) ? $phpFileUploadErrors[$chunkErr] : ('Upload-Fehler (Code ' . $chunkErr . ').');
            file_error($errMsg);
        }
    }

    if (!$inFp) {
        $inFp = @fopen('php://input', 'rb');
    }

    $outFp = @fopen($partFilePath, ($chunkIndex === 0 && $chunkOffset === 0) ? 'wb' : 'c+b');
    if (!$outFp) {
        if ($inFp) {
            @fclose($inFp);
        }
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Chunk-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): Konnte Part-Datei nicht zum Schreiben öffnen ({$partFilePath}).", __FILE__, __LINE__);
        }
        file_error('Server-Fehler: Die Videodatei konnte auf dem Server nicht zwischengespeichert werden. Bitte kontaktiere den Support.');
    }

    if ($chunkOffset > 0) {
        @fseek($outFp, $chunkOffset, SEEK_SET);
    }

    @flock($outFp, LOCK_EX);

    $bytesWritten = 0;
    if ($inFp) {
        while (!feof($inFp)) {
            $buf = fread($inFp, 65536);
            if ($buf === false || $buf === '') {
                break;
            }
            $written = fwrite($outFp, $buf);
            if ($written === false) {
                break;
            }
            $bytesWritten += $written;
        }
        @fclose($inFp);
    }

    @fflush($outFp);
    @flock($outFp, LOCK_UN);
    @fclose($outFp);

    if ($bytesWritten <= 0) {
        $reqMethod = isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
        $contentLen = isset($_SERVER['CONTENT_LENGTH']) ? (string)$_SERVER['CONTENT_LENGTH'] : 'unset';
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? (string)$_SERVER['CONTENT_TYPE'] : 'unset';
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Chunk-Upload abgebrochen (Merchant #{$merchant_id}, Movie #{$movie_id}, Chunk {$chunkIndex}/{$totalChunks}): 0 Bytes empfangen. METHOD={$reqMethod}, CONTENT_LENGTH={$contentLen}, CONTENT_TYPE={$contentType}", __FILE__, __LINE__);
        }
        file_error('Es wurden keine Daten für diesen Dateiabschnitt empfangen (0 Bytes).');
    }

    if ($chunkIndex < $totalChunks - 1) {
        echo json_encode(array(
            'status' => 'chunk_ok',
            'chunk_index' => $chunkIndex,
            'bytes' => $bytesWritten
        ));
        p4c_close(DB_HOST);
        p4c_errorlog(error_get_last());
        exit;
    }

    // Letzter Chunk empfangen -> Gesamtgröße prüfen und Datei finalisieren
    clearstatcache(true, $partFilePath);
    $actualSize = file_exists($partFilePath) ? (int)@filesize($partFilePath) : 0;
    if ($totalFileSize > 0 && $actualSize !== $totalFileSize) {
        if (isset($class_errorlog)) {
            $class_errorlog->log("Movie-Chunk-Upload Größenabweichung (Merchant #{$merchant_id}, Movie #{$movie_id}): erwartet={$totalFileSize}, vorhanden={$actualSize}.", __FILE__, __LINE__);
        }
        @unlink($partFilePath);
        file_error('Die Übertragung der Videodatei war unvollständig (' . number_format($actualSize / (1024 * 1024), 1, ',', '.') . ' MB von ' . number_format($totalFileSize / (1024 * 1024), 1, ',', '.') . ' MB). Bitte starte den Upload erneut.');
    }

    $file_name = $movie_id.'_'.$movie_ary->file_id.'.'.$extension;
    $file_path = $target_dir.'/'.$file_name;

    if (file_exists($file_path)) {
        @unlink($file_path);
    }

    if (!@rename($partFilePath, $file_path)) {
        if (!@copy($partFilePath, $file_path)) {
            if (isset($class_errorlog)) {
                $class_errorlog->log("Movie-Chunk-Upload Fehler (Merchant #{$merchant_id}, Movie #{$movie_id}): Konnte {$partFilePath} nicht nach {$file_path} verschieben.", __FILE__, __LINE__);
            }
            file_error("Die Datei ".htmlspecialchars($originalFileName, ENT_QUOTES, 'UTF-8')." konnte nicht gespeichert werden. (".__LINE__.")");
        }
        @unlink($partFilePath);
    }

    finalize_uploaded_movie($file_path, $file_name, $originalFileName, $movie_ary, $movie_id, $merchant_id, $isReleased);

    echo json_encode(array(
        'status' => 'complete',
        'file' => $originalFileName
    ));
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit;
}

// 2. Klassischer Single-POST-Modus (Fallback)
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
    $reqMethod = isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? (string)$_SERVER['CONTENT_TYPE'] : 'unset';
    $movie_id = isset($_GET['movie_id']) ? abs((int)$_GET['movie_id']) : 0;
    if (isset($class_errorlog)) {
        $class_errorlog->log("Movie-Upload abgebrochen (Merchant #{$merchant_id}, Movie #{$movie_id}): \$_FILES['movie'] ist leer. METHOD={$reqMethod}, CONTENT_LENGTH={$contentLength}, CONTENT_TYPE={$contentType}, post_max_size=" . ini_get('post_max_size') . ", upload_max_filesize=" . ini_get('upload_max_filesize'), __FILE__, __LINE__);
    }
    if ($contentLength > 0) {
        file_error('Deine Videodatei (' . number_format($contentLength / (1024 * 1024), 1, ',', '.') . ' MB) überschreitet das aktuelle Server-Limit (post_max_size: ' . ini_get('post_max_size') . ', upload_max_filesize: ' . ini_get('upload_max_filesize') . '). Bitte verkleinere die Datei oder kontaktiere den Support.');
    } else {
        file_error('Es wurden keine Dateidaten empfangen. Bitte lade die Seite mit Strg+F5 neu und starte den Upload erneut.');
    }
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>