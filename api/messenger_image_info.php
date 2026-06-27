<?php

define('SAFE_INC', 1);

$session_write_close = true;

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

// Bild aus EroCMS gesendet
if (isset($_GET['type']) AND $_GET['type'] === 'erocms') {
    
    $img_url = 'https://'.$_GET['url'].'/'.$_GET['actor_id'].'/'.$_GET['filename'];

    // Wenn URL keine echte URL ist
    if (!filter_var($img_url, FILTER_VALIDATE_URL)) {
        $img_url = MCP_DIR.'/images/movie_poster_nopic.jpg';
    }
    
    if(false === ($file_path = @file_get_contents($img_url))){
        $nopic_url = MCP_DIR.'/images/movie_poster_nopic.jpg';
        $file_path = file_get_contents($nopic_url);
        $img_url = $nopic_url;
        $class_errorlog->log("Foto existiert nicht mehr:\nURL: ".$img_url."\n".print_r($_GET,true),__FILE__,__LINE__);
    }
    
    $width = 300;
    $height = 300;

    list($width_orig, $height_orig) = getimagesize($img_url);
    $width = round(($width_orig / 100) * ($height / ($height_orig / 100)));
    
    $img_src = 'data:image/jpeg;base64,'.base64_encode($file_path);
    
    $load = '&type=erocms&url='.$_GET['url'].'/'.$_GET['actor_id'].'/'.$_GET['filename'];
    
    
    if (isset($_GET['load'])) {
        
        $img_url = str_replace('thumb_', '', $img_url);
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($img_url));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($file_path));
        ob_clean();
        flush();
        readfile($img_url);
        exit;
    }
 
// Bild aus EroCloud gesendt
} else {
    
    $actor_id = abs($_GET['actor_id']);
    $merchant_id = abs($_GET['merchant_id']);
    $file_name = preg_replace( '/[^a-z0-9.]/i', '', $_GET['filename']);

    $no_pic = MCP_DIR.'/images/movie_poster_nopic.jpg';

    $file_path = PROFILE_IMAGE_PATH.'/'.MERCHANT_DEFAULT_DIR.'/'.$merchant_id.'/messenger/'.$actor_id.'/'.$file_name;

    if (isset($_GET['load'])) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($file_path));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        ob_clean();
        flush();
        readfile($file_path);
        exit;
    }
    
    
    if (!file_exists($file_path)) {
        $filename = $no_pic;
    }

    $width = 300;
    $height = 300;

    list($width_orig, $height_orig) = getimagesize($file_path);
    $width = round(($width_orig / 100) * ($height / ($height_orig / 100)));

    $image      = $file_path;
    $image_file = fopen($image, 'r');
    $image_data = fread($image_file, filesize($image));

    $img_src = 'data:image/jpeg;base64,'.base64_encode($image_data);
    $load = $merchant_id.'/'.$actor_id.'/'.$file_name;
    
}





$site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>'.PROJECTNAME.'</title>
    <meta charset="utf-8" />

    <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-16x16.png" sizes="16x16">
    <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-96x96.png" sizes="96x96">

    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/style.css?id=1" />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/icon?family=Material+Icons" />

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script type="text/javascript" src="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.js"></script>

</head>
<body class="ui-widget-content" style="border:none; max-width:600px;">
    <div style="position:absolute; bottom:5px; right:5px">
        <a href="'.MCP_URL.'" target="_blank"><img src="'.MCP_URL.'/erocloud_logo.png" alt="" style="width:80px; height:auto;" /></a>
    </div>
    <div id="head_info" class="ui-widget-header" style="border-top:none; border-left:none; border-right:none; padding:5px 10px;">
        Bild herunterladen
    </div>
    <div style="padding:10px;">
        <div style="width:'.$width.'px; max-width:330px; height:'.$height.'px; max-height:300px; overflow:hidden; margin:0 auto;">
            <img src="'.$img_src.'" style="width:100%; height:100%; object-fit: cover;" />
        </div>
        
        <div style="text-align:center; margin-top:15px;">
            <a href="'.API_URL.'/MessengerImageInfo/'.$load.'&amp;load">Bild in Originalgr&ouml;&szlig;e herunterladen</a>
        </div>
    </div>
</body>
</html>
';

echo $site;

// Garbage Collection
p4c_close(DB_HOST);
	
// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>
