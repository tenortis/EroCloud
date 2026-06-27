<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

$site = '';

if (is_logged_in('mcp') === false) {
    exit;
}

if (!isset($_POST['file_id'])) {
    echo 'Das Banner existiert nicht.';
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit;
}

// Bannercode erstellen
$file_id = p4c_escape_string($_POST['file_id']);

$rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE
    `file_id` = '".p4c_escape_string($file_id)."'
LIMIT 1;",__FILE__,__LINE__);

// Prüfen ob Seite existiert - wenn nicht, zu EroCloud leten
if (p4c_num_rows($rs_ads) == 0) {
    echo 'Das Banner existiert nicht.2';
    p4c_close(DB_HOST);
    p4c_errorlog(error_get_last());
    exit;
}

$media_obj = p4c_fetch_object($rs_ads);

$site_id = abs($_POST['site_id']);

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

$rs_sites = p4c_query("SELECT * FROM `sites` WHERE
    `id`        = '".$site_id."' AND
    `status`    = '1' AND
    `is_eroads_active`= '1' AND
    `partner_id` = '".p4c_escape_string($merchant->partner_id())."';
",__FILE__,__LINE__);

if (p4c_num_rows($rs_sites) == 0) {
    exit;
}
    
$site_obj = p4c_fetch_object($rs_sites);

if ($media_obj->type == "png") {
    $upload_type = 'image/x-png';
} else if ($media_obj->type == "jpg") {
    $upload_type = 'image/jpeg';
} else if ($media_obj->type == "gif") {
    $upload_type = 'image/gif';
} else {
    $upload_type = 'image/x-png,image/jpeg,image/gif';
}

$media_type = $media_obj->type;
if ($media_type == 'jpeg' OR $media_obj->type == 'jpg') {$media_type = 'jpg,jpeg';}

$site .= '
<script>
    jQuery(document).ready(function() {
        jQuery(".my_banner_update_popup .close_overlay").click(function() {
            jQuery(".my_banner_update_popup").hide(function(){
                var site_id = 178;

                jQuery("#overlay").show(function() {
                    jQuery.ajax({
                        url: mcp_url+"/includes/overlays/webmaster_my_banners.php",
                        data: "site_id='.$site_obj->id.'",
                        method: "POST",
                        dataType: "html",
                        async: true,
                        success: function (data) {
                            jQuery("#my_banners_popup_content").html(data);
                            jQuery(".my_banners_popup").show();
                        }
                    })
                });
            });
        })
    } );

    var site_id = '.$site_obj->id.';
    var mcp_url = "'.MCP_URL.'";
    var allowedImagesTypes = "'.$media_type.'";
</script>

<link rel="stylesheet" href="'.MCP_URL.'/css/my_banners_upload.css?v=1" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
<script type="text/javascript" src="'.MCP_URL.'/js/my_banner_upload.js?v=1"></script>

<h1>Banner eretzen / aktualisieren</h1>

<div style="margin:20px 0; font-size:15px;">
    Webseite:  <b>'.$site_obj->domain.'</b>
</div>

<div style="margin:20px 0;">
    Hier kannst du das aktuelle Banner durch ein neues Banner ersetzen.<br />
</div>

<div class="ui-widget-content" style="position:relative; padding: 10px; margin-bottom:20px;">
    <div class="info_box" style="margin-top:0; margin-bottom:10px;">
        <div>Erlaubtes Dateiformate: <b>'.$media_obj->type.'</b></div>
        <div>Aufl&ouml;sung: <b>'.$media_obj->width.' x '.$media_obj->height.'</b> (H&ouml;he x Breite)</div>
        <div>Maximale Dateigr&ouml;&szlig;e: 5 MB</div>
        <div><b>Bitte beachten:</b> Wir &uuml;berpr&uuml;fen das Banner bevor es Webmastern zur Verf&uuml;gung gestellt wird.</div>
    </div>

    <div class="upload_banners_error ui-state-error"></div>

    <div id="upload_photos">
        <div class="upload_banners">
            <span>Banner hier rein ziehen</span>
            <form id="form_upload_banners" action="'.MCP_URL.'/includes/uploader/upload_my_banner.php?site_id='.$site_obj->id.'&file_id='.$file_id.'" method="post" enctype="multipart/form-data">
                <input type="file" id="upload_banners" multiple name="banners[]" accept="'.$upload_type.'">
            </form>
        </div>
        <div class="abort_upload ui-state-error"><span>Abbrechen</span></div>

        <div class="progress">
            <div class="bar"></div >
            <div class="percent">0%</div >
        </div>

        <div class="status"></div>
    </div>
</div>

<div id="ok-message" class="ui-state-highlight" style="display:none; padding:10px;">Upload erfolgreich.<br />Das Banner wird nun von uns gepr&uumlft. Sobald die Pr&uuml;fung erfolgreich abgeschlossen ist, wird das alte Banner mit dem Neuen ersetzt.</div>

<h1>Aktuelles Banner</h1>
<div style="padding: 10px; margin-bottom:20px; text-align:center;">
    <img src="'.ADS_URL.'/b/'.$media_obj->file_id.'" />
</div>
';

if ($media_obj->new_filename != '') {

    $new_file = ADS_PATH.'/'.ADS_DEFAULT_DIR.'/'.$site_obj->id.'/'.$media_obj->new_filename;
    
    $contents = file_get_contents($new_file);
    $base64   = base64_encode($contents); 
    $src = 'data:image/'.$media_obj->type.';base64,'.$base64;

    if ($media_obj->rejected == '0') {
        $site .= '<h1>Banner in Pr&uuml;fung</h1>';
    } else if ($media_obj->rejected == '3') {
        $site .= '
        <h1 style="color:#F44336;">Banner abgelehnt.</h1>
        <div>Du kannst gern erneut ein &uuml;berarbeitetes Banner hochladen.<div>
        ';
    }
    
    
    $site .= '
    <div style="padding: 10px; margin-bottom:20px; text-align:center;">
        <img src="'.$src.'" />
    </div>
    ';    
}



echo $site;

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

