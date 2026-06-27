<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

$site = '';

if (is_logged_in('mcp') === false) {
    exit;
}

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

$site .= '
<script>
    var site_id = '.$site_obj->id.';
    var mcp_url = "'.MCP_URL.'";
</script>

<link rel="stylesheet" href="'.MCP_URL.'/css/my_banners_upload.css?v=4" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
<script type="text/javascript" src="'.MCP_URL.'/js/my_banners_upload.js?v=4"></script>

<h1>Banner hinzuf&uuml;gen und bearbeiten</h1>

<div style="margin:20px 0; font-size:15px;">
    Webseite:  <b>'.$site_obj->domain.'</b>
</div>

<div style="margin:20px 0;">
    Es sind nur Banner, Skyscraper und Buttons in den bekanntesten Standardgr&ouml;&szlig;en erlaubt.
    <div id="standard_banner_sizes">
        <div style="margin-bottom:10px;">Hier erf&auml;hrst du noch mehr &uuml;ber Standard-Banner-Gr&ouml;&szlig;en: <a href="https://de.wikipedia.org/wiki/Werbebanner#Standardgr%C3%B6%C3%9Fen" target="_blank">https://de.wikipedia.org/wiki/Werbebanner#Standardgr&ouml;&szlig;en</a></div>
        <div style="margin-bottom:10px;">
            <div class="ui-widget-header" style="padding:0 3px;">Bannerset erstellen lassen</div>
            <div class="ui-widget-content" style="border-top:none; padding:5px; line-height:1.1em;">
                &Uuml;ber unseren Partner In-Picture.de, kannst dir ein Bannerset mit 6 Banner f&uuml; <b>nur 150,- EUR</b> erstellen lassen.<br />
                Das Bannerset enth&auml;lt <b>6 Banner</b> in verschiedenen gr&ouml;&szlig;en, mit einem einheitlichen Design. Die Banner sind statisch und nicht animiert.
                Sonderw&uuml;nsche sind kein Problem. <b>Kontaktaktiere Rico</b>, deinen Grafiker, einfach per E-Mail an <a href="mailto:kontakt@in-picture.de">kontakt@in-picture.de</a> 
                oder schnell und unkompliziert per WhatsApp <a target="_blank" href="https://wa.me/+4917660406280?text='. urldecode("Hallo Enrico, ich habe interesse an einem Bannerset f&uuml;r meine Website ".$site_obj->domain).'">+49 (0)176 604 062 80</a>
            </div>
        </div>
        <table style="width:100%; max-width:756px;">
            <tr>
                <td style="vertical-align:top;">
                    <h3>Banner und Buttons</h3>
                    <table>
                        <tr><td>80 x 31</td><td>Micro Bar</td></tr>
                        <tr><td>120 x 90</td><td>Button 1</td></tr>
                        <tr><td>120 x 60</td><td>Button 2</td></tr>
                        <tr><td>120 x 240</td><td>Vertical Banner</td></tr>
                        <tr><td>125 x 125</td><td>Square Button</td></tr>
                        <tr><td>234 x 60</td><td>Half Banner</td></tr>
                        <tr><td>468 x 60</td><td>Full Banner</td></tr>
                        <tr><td style="vertical-align:top;">728 x 90</td><td>Superbanner</td></tr>
                    </table>
                </td>
                <td style="vertical-align:top;">
                    <h3>Rectangle</h3>
                    <table>
                        <tr><td>180 x 150</td><td>Rectangle</td></tr>
                        <tr><td>300 x 250</td><td>Medium Rectangle</td></tr>
                        <tr><td>240 x 400</td><td>Square Pop-Up</td></tr>
                        <tr><td>250 x 250</td><td>Vertical Rectangle</td></tr>
                        <tr><td style="vertical-align:top;">400 x 400</td><td>AdLayer</td></tr>
                    </table>
                </td>
                <td style="vertical-align:top;">
                    <h3>Skyscraper</h3>
                    <table>
                        <tr><td>160 x 650</td><td>Wide Skyscraper</td></tr>
                        <tr><td>120 x 600</td><td>Skyscraper</td></tr>
                        <tr><td>200 x 600</td><td>Wide Skyscraper alternative</td></tr>
                        <tr><td>300 x 600</td><td>Half Page Ad</td></tr>
                        <tr><td>420 x 600</td><td>Expandable Skyscraper</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="ui-widget-header" style="padding:5px 10px; margin-top:20px; font-size:15px">Neue Banner f&uuml;r die Webseite '.$site_obj->domain.' hochladen</div>
    <div class="ui-widget-content" style="position:relative; padding: 10px; border-top:none; margin-bottom:20px;">
        <div class="info_box" style="margin-top:0; margin-bottom:10px;">
            <div>Erlaubte Dateiformate: jpg, png, gif</div>
            <div>Maximale Dateigr&ouml;&szlig;e: 5 MB</div>
            <div><b>Bitte beachten:</b> Wir pr&uuml;fen jedes Banner bevor es Webmastern zur Verf&uuml;gung steht.</div>
        </div>

        <div class="upload_banners_error ui-state-error"></div>

        <div id="upload_photos">
            <div class="upload_banners">
                <span>Klicke hier um ein oder mehrere Banner hinzuzuf&uuml;gen.<br />Oder ziehe sie mit der Maus hier rein.</span>
                <form id="form_upload_banners" action="'.MCP_URL.'/includes/uploader/upload_my_banners.php?site_id='.$site_obj->id.'" method="post" enctype="multipart/form-data">
                    <input type="file" id="upload_banners" multiple name="banners[]" accept="image/x-png,image/jpeg,image/gif">
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

    <div id="ok-message" class="ui-state-highlight" style="display:none; padding:10px;">Banner hinzugef&uuml;gt.<br />Nachdem wir das Banner gepr&uuml;ft und freigeschaltet haben, steht es Webmastern zur Verf&uuml;gung.</div>

    <div class="ui-widget-header" style="padding:5px 10px; margin-top:20px; font-size:15px">Banner f&uuml;r die Webseite '.$site_obj->domain.' bearbeiten</div>
    <div class="ui-widget-content" style="padding:5px 10px; border-top:none;">F&uuml;r eine bessere &Uuml;bersicht werden die Banner hier verkleinert angezeigt.</div>
    <div class="ui-widget-content ads" style="position:relative; padding: 10px; border-top:none; margin-bottom:20px;"></div>

</div>';
    
echo $site;

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

