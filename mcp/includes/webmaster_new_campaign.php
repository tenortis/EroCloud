<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (is_numeric($_GET['sid'])) {
    $site_id = abs($_GET['sid']);
} else {
    $site_id = p4c_escape_string($_GET['sid']);
}

// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$website = new Site($mysql,$site_id);

if ($website->get_var("id") == '') {
    header('Location: '.MCP_URL.'/Webmaster/Ads');
    exit;
}

// Wenn Seite nicht fuer Webmaster aktiviert ist
if ($website->get_var("is_eroads_active") == 0) {
    header('Location: '.MCP_URL.'/Webmaster/Ads');
    exit;
}

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

$c['name'] = '';
$c['description'] = '';

$s['id'] = $website->get_var("id");
$s['domain'] = $website->get_var("domain");
$s['webmaster_commision'] = $website->get_var("webmaster_commision");

$replace_name_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['new_campaign'])) {
    
    if (!isset($_SESSION['new_campaignb_id'])) {
        $cid = randomString(10);
        $_SESSION['new_campaignb_id'] = $cid;
    } else {
        $cid = $_SESSION['new_campaignb_id'];
    }
    
    $c['name'] = trim(str_replace($replace_name_ary, '', $_POST['cname']));
    if (empty($c['name'])) {
        $c['name'] = $cid;
    }

    $c['description'] = trim(strip_tags($_POST['cdescription']));
    
    if (p4c_query("INSERT INTO `ads_campaigns` SET 
        `merchant_id`= '".p4c_escape_string($merchant->id())."',
        `p4c_partner_id`= '".p4c_escape_string($merchant->partner_id())."',
        `site_id`       = '".$s['id']."',
        `campaign_id`   = '".p4c_escape_string($cid)."',
        `name`          = '".p4c_escape_string($c['name'])."',
        `description`   = '".p4c_escape_string($c['description'])."',
        `create_date_time` = '".date("Y-m-d H:i:s")."'
    ",__FILE__,__LINE__)) {
        header('Location: '.MCP_URL.'/Webmaster/Edit-Campaign?cid='.$cid);
        exit;
    }
    
    
} else {
    $cid = randomString(10);
    $_SESSION['new_campaignb_id'] = $cid;
}

$site .= '
<div style="width:700px;">
    <h1 class="h4">Neue Kampagne erstellen</h1>
    
    <script type="text/javascript">
    // <![CDATA[
        jQuery(document).ready(function() {

        })

    // ]]>
    </script>
    
    <style>
        .edit_content{margin-bottom:20px;}
        input.button.ui-widget {font-size:16px !important;}
    </style>';

    if (isset($error) AND !empty($error)) {
        $site .= '<div class="ui-state-error" style="padding:10px; margin-top:10px;">'.$error.'</div>';
    }
    $site .= '
    <form action="" method="post">
        <div class="ui-widget-content" style="padding:10px;">
            <table style="width:100%;">
                <tr>
                    <td style="width:45%;">
                        <div class="edit_title">Webseite</div>
                        <div class="edit_content" style="font-size:20px; font-weight:400;">
                            '.$s['domain'].'
                        </div>
                    </td>
                    <td style="width:40%;">
                        <div class="edit_title">Kampagnen-ID (CID)</div>
                        <div class="edit_content" style="font-size:20px; font-weight:400;">
                            '.$cid.'
                        </div>
                    </td>
                    <td style="width:20%">
                        <div class="edit_title">Ihre Provision</div>
                        <div class="edit_content" style="font-size:20px; font-weight:400;">
                            '.$s['webmaster_commision'].'%
                        </div>
                    </td>
                </tr>
            </table>

            <div class="edit_title">Name der Kampagne</div>
            <div class="edit_content">
                <input type="text" name="cname" value="'.$c['name'].'" placeholder="Beispiel: Twitter-Werbung" style="font-size:18px;" />
            </div>

            <div class="edit_title">Beschreibe diese Kampagne oder gib sonstige Informationen zu dieser Kampagene an.<br />
            Somit wei&szlig;t du auch zu einem sp&auml;teren Zeitpunkt noch f&uuml;r was du diese Kampagne erstellt hast.</div>
            <div class="edit_content">
                <textarea name="cdescription" style="height:150px;">'.$c['description'].'</textarea>
            </div>
        </div>

        <div style="margin-top:15px; margin-bottom:30px; text-align:right;">
            <table style="width:100%;">
                <tr>
                    <td style="width:50%">
                        <input type="hidden" name="cid" value="'.$cid.'" />
                        <input type="submit" name="new_campaign" class="button" value="Kampange erstellen" />
                    </td>
                </tr>
            </table>
        </div>
    </form>
</div>';

?>