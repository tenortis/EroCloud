<?php

/**
 * Fotoalbum Downloader
 * 
 */

define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

if (!isset($_GET['user_key'])) {
    $api['error'] = 'user_key not exists';
    print_xml($api);
}

if (!isset($_GET['album_id'])) {
    $api['error'] = 'album_id not exists';
    print_xml($api);
}

if (!isset($_GET['access_token'])) {
    $api['error'] = 'access denid 1';
    print_xml($api);
}

$user_key = $_GET['user_key'];

// Prüfen ob der user_streaming_key existiert
$rs_user_key = p4c_query("SELECT * FROM `photo_albums_access` WHERE `user_key` = '".p4c_escape_string($user_key)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_user_key) == 0) {
    $api['error'] = 'access denid 2';
    print_xml($api);
}

$access_obj = p4c_fetch_object($rs_user_key);

// Wenn das Accesstoken nicht korrekt oder abgelaufen ist
if ($access_obj->access_token != $_GET['access_token'] OR $access_obj->access_token_datetime < date("Y-m-d H:i:s")) {
    $api['error'] = 'access denid 3';
    print_xml($api);    
}

$album_id = p4c_escape_string($_GET['album_id']);

$rs_album = p4c_query("SELECT * FROM `photo_albums` WHERE `album_id`='".$album_id."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_album) == 0) {
    $api['error'] = 'album_id not exists';
    print_xml($api);;   
}

$album_ary = p4c_fetch_object($rs_album);

$rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='".p4c_escape_string($album_id)."' AND `rejected`='0';",__FILE__,__LINE__);


$album_dir = PROFILE_IMAGE_PATH.'/'.$album_ary->storage_location.'/'.$album_ary->merchant_id.'/'.$album_ary->id;
$zip_file = $album_dir.'/album.zip';

if (is_file($zip_file)) {
    @unlink($zip_file);
}

$zip = new ZipArchive();

if ($zip->open($zip_file, ZipArchive::CREATE)!==TRUE) {
    $api['error'] = 'cannot open zipfile\n';
    print_xml($api);;   
}

$zip->addFromString($album_ary->seo_url.'.txt', strip_tags($album_ary->description));
while($photo_ary = p4c_fetch_object($rs_photos)) {        
    $zip->addFile($album_dir.'/images/'.$photo_ary->filename, $photo_ary->filename);
}
$zip->close();

$size = filesize($zip_file);


if (isset($_GET['start']) AND $_GET['start'] == 'download') { 
    
    session_write_close();

    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private',false);
    header('Content-Type: application/zip');
    header('Content-Type: application/force-download');
    header('Content-Type: application/download');
    header('Content-Disposition: attachment; filename="'. seo_url($album_ary->title).'".zip');
    header('Content-Description: File Transfer');
    header('Content-Length: '.$size);
    

    $bis = 1024*1024;
    $count = ceil($size/(int)$bis);
    $handle = fopen ($zip_file, "r");	
        for ($i=0;$i<=$count;$i++) {
            $ftell = ftell($handle)+$bis;
            $contents = fread ($handle, $bis);
            fseek($handle,$ftell);
            echo $contents; 
        }
    fclose ($handle);

} else {
    header('Content-Type: text/html; charset=utf-8');
    header('refresh:5; '.API_URL.'/PhotoAlbumDownloader/'.$user_key.'/'.$album_id.'/'.$_GET['access_token'].'&start=download');

    $site = '<!DOCTYPE html>
    <html lang="de">
    <head>
        <title>'.PROJECTNAME.'</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-96x96.png" sizes="96x96">

        <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.css" />
        <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/style.css?id=1" />
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/icon?family=Material+Icons" />

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script type="text/javascript" src="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.js"></script>

        <style>
            div {font-size:1em;}
        </style>

        <script type="text/javascript"><!--
            jQuery.noConflict();

            jQuery(document).ready(function() {
                setTimeout(function(){
                    jQuery("#dl_info").html("Das Album wird herunter geladen...");
                }, 4000);
            })

        --></script>

    </head>
    <body class="ui-widget-content" style="border:none;">
        <div style="position:absolute; bottom:5px; right:5px">
            <a href="'.MCP_URL.'" target="_blank"><img src="'.MCP_URL.'/erocloud_logo.png" alt="" style="width:80px; height:auto;" /></a>
        </div>
        <div id="head_info" class="ui-widget-header" style="border-top:none; border-left:none; border-right:none; padding:5px 10px;">
            '.$album_ary->title.'
        </div>
        <div style="padding:10px;">
            <div id="dl_info" style="margin-bottom:10px;">Der Download startet in wenigen Sekunden...</div>
            <div>Dateigr&ouml;&szlig;e: '.convertBytes($size).'</div>
        </div>
    </body>
    </html>
    ';

    echo $site;
}

p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>