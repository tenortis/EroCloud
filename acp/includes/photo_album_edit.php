<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!isset($_GET['id'])) {exit;}

$album_id = abs($_GET['id']);

$a = new PhotoAlbumOnline($mysql,$album_id);

if ($a->field('id') == '') {
    header('Location: '.ACP_URL.'/Fotoalben-online');
    exit;
}

$merchant = new Merchant($mysql,$a->field('merchant_id'));

include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
$actor = new Actor($a->field('actor_id'));

if ($actor->get("id") == '') {
    header('Location: '.ACP_URL.'/Actors');
    exit;    
}

if ($a->field('number_of_photos') == 0) {
    $rs_count_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='". p4c_escape_string($a->field('album_id'))."'",__FILE__,__LINE__);
    $album['number_of_photos'] = p4c_num_rows($rs_count_photos);
} else {
    $album['number_of_photos'] = $a->field('number_of_photos');
}

$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['btn']['save_album'])) {
    
    if (isset($_POST['actor_id']) AND $_POST['actor_id'] == 0) {
        $error = 'Bitte w&auml;hlen Sie den Hauptdartseller aus.';
    } else {
        $album['actor_id'] = abs($_POST['actor_id']);
    }
    
    $album['category_master'] = trim(strip_tags($_POST['category_master']));
        
    if (!isset($_POST['category_slave']) OR !is_array($_POST['category_slave'])) {
        $error = 'W&auml;hle alle Kategorien die zum Film passen.';
    } else {
        $album['category_slave'] = trim(strip_tags(implode(',', $_POST['category_slave'])));
    }
    
    if(!isset($_POST['title']) OR trim($_POST['title']) == '') {
        $error = 'Gib einen aussagekr&auml;ftigen Titel f&uuml;r das Fotoalbum an.';    
    } else {
        $album['title'] = trim(str_replace($replace_title_ary, '', $_POST['title']));
        if (empty($album['title'])) {
            $error = 'Gib einen aussagekr&auml;ftigen Titel f&uuml;r das Fotoalbum an.';    
        } 
    }

    $album['title'] = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $album['title']);

    if (strlen(utf8_decode($album['title'])) > 65) {
        $error = 'Der Albumtitel ist leider zu lang.';
    }
    
    if(!isset($_POST['description'])) {
        $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung f&uuml;r das Fotoalbum an.';
    } else {
        $allowed_tags = '<ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><p>';
        $album['description'] = trim(strip_tags($_POST['description'], $allowed_tags));
        if (empty($album['description'])) {
            $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung f&uuml;r das Fotoalbum an.';
        }
    }
    
    if(isset($_POST['actor_id'])) {
        $album['actor_id'] = abs($_POST['actor_id']);
    }
    
    if(isset($_POST['online_at'])) {
        $album['online_at'] = date("Y-m-d H:i", strtotime($_POST['online_at']));
    }

    if(isset($_POST['amount_download'])) {
        $amount_download = abs($_POST['amount_download']);
        if ($amount_download <= 5000 AND $amount_download >= 0) {
            $album['amount_download'] = $amount_download;
        }
    }

    if(!isset($_POST['meta_title'])) {
        $album['meta_title'] = substr(trim(str_replace($album['title'])), 0, 65);
    } else {
        $album['meta_title'] = substr(trim(str_replace($replace_title_ary, '', $_POST['meta_title'])), 0, 65);
        $album['meta_title'] = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $album['meta_title']);
        $album['meta_title'] = str_replace(array("\n", "\r"), '', $album['meta_title']);
        if (empty($album['meta_title'])) {$album['meta_title'] = substr(trim($album['title']), 0, 65);}   
    }
    
    if(!isset($_POST['meta_description'])) {
        $album['meta_description'] = substr(trim(strip_tags($album['description'])), 0, 156);
    } else {
        $album['meta_description'] = substr(trim(strip_tags($_POST['meta_description'])), 0, 156);
        $album['meta_description'] = str_replace(array("\n", "\r"), '', $album['meta_description']);
        if (empty($album['meta_description'])) {$album['meta_description'] = substr(trim(strip_tags($album['description'])), 0, 156);}
    }
    
    if (!isset($_POST['seo_url']) OR $_POST['seo_url'] == '') {
        $album['seo_url'] = seo_url($album['title']);
    } else {
        $album['seo_url'] = seo_url($_POST['seo_url']);
    }
    
    // Prüfe ob bei diesem Kunden bereichts ein Fotoalbum mit diesem Title existiert
    $rs_check_album_exists = p4c_query("SELECT `id`  FROM `photo_albums_online` WHERE `title`='".p4c_escape_string($album['title'])."' AND `id`!='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_album_exists) == 1) {
        $error = 'Es existiert bereits eine Fotoalbum mit diesem Titel.';
        $duplicate_title = p4c_result($rs_check_album_exists, 0);
    }
    
    $status = p4c_escape_string(filter_input(INPUT_POST, 'album_status', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));
    $admin_infos = p4c_escape_string(filter_input(INPUT_POST, 'admin_infos', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW));
    
    
    if (!isset($error) OR empty($error)) {
        
        if (p4c_query("UPDATE `photo_albums_online` SET
            `actor_id` = '".abs($album['actor_id'])."',
            `number_of_photos`='".abs($album['number_of_photos'])."',
            `title` = '".p4c_escape_string($album['title'])."',
            `description` = '".p4c_escape_string($album['description'])."',
            `meta_title` = '".p4c_escape_string($album['meta_title'])."',
            `meta_description` = '".p4c_escape_string($album['meta_description'])."',
            `seo_url` = '".p4c_escape_string($album['seo_url'])."',
            `category_master` = '".p4c_escape_string($album['category_master'])."',
            `category_slave` = '".p4c_escape_string($album['category_slave'])."',
            `online_at` = '".p4c_escape_string($album['online_at'])."',
            `amount_download` = '".p4c_escape_string($album['amount_download'])."',
            `album_checked` = '".date("Y-m-d H:i:s")."',
            `status`='".$status."',
            `admin_infos` ='".$admin_infos."'
            WHERE `id`='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__)
        ) {

            if (p4c_query("UPDATE `photo_albums` SET
                `actor_id` = '".abs($album['actor_id'])."',
                `number_of_photos`='".abs($album['number_of_photos'])."',
                `title` = '".p4c_escape_string($album['title'])."',
                `description` = '".p4c_escape_string($album['description'])."',
                `meta_title` = '".p4c_escape_string($album['meta_title'])."',
                `meta_description` = '".p4c_escape_string($album['meta_description'])."',
                `seo_url` = '".p4c_escape_string($album['seo_url'])."',
                `category_master` = '".p4c_escape_string($album['category_master'])."',
                `category_slave` = '".p4c_escape_string($album['category_slave'])."',
                `online_at` = '".p4c_escape_string($album['online_at'])."',
                `amount_download` = '".p4c_escape_string($album['amount_download'])."',
                `album_checked` = '".date("Y-m-d H:i:s")."',
                `status`='".$status."',
                `admin_infos` ='".$admin_infos."'
                WHERE `album_id`='".p4c_escape_string($a->field('album_id'))."' LIMIT 1;",__FILE__,__LINE__)
            ) {
                header('Location: '.ACP_URL.'/Fotoalbum-bearbeiten/'.$album_id);
                exit;
            }
                
            $error = 'Das Fotoalbum konnte nicht gespeichert werden!';
            
        } else {
            $error = 'Das Fotoalbum konnte nicht gespeichert werden!';
        }
    }
}

$rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='".p4c_escape_string($a->field('album_id'))."' ORDER BY `id` DESC;",__FILE__,__LINE__);
$count_photos = p4c_num_rows($rs_photos);

$album['title'] = $a->field('title');
$album['description'] = $a->field('description');
$album['online_at'] = $a->field('online_at');
$album['amount_webmaster'] = $a->field('amount_webmaster');
$album['amount_download'] = $a->field('amount_download');
$album['meta_title'] = $a->field('meta_title');
$album['meta_description'] = $a->field('meta_description');
$album['seo_url'] = $a->field('seo_url');
$album['checksum'] = $a->field('checksum');
$album['album_id'] = $a->field('album_id');
$album['actor_id'] = $a->field('actor_id');
$album['category_master'] = $a->field('category_master');
$album['category_slave'] = $a->field('category_slave');
$album['admin_infos'] = $a->field('admin_infos');
$album['status'] = $a->field('status');


$site .= ' 
<link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
<script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.1.4/l10n/de.js"></script>

<script src="//cdn.ckeditor.com/4.6.2/full/ckeditor.js"></script>

<style type="text/css">
<!--
    input.button.ui-widget {font-size:16px !important;}
    
    .edit_title {
        margin-bottom:2px;
    }

    .edit_content input[type="text"],
    .edit_content input[type="password"],
    .edit_content textarea,
    .edit_content select {
        width:100%;
        font-size:16px;
        box-sizing : border-box;
        padding:5px;
        border:1px solid #D1D1D1;
    }

    .edit_content textarea {
        height:60px;
    }
    
    .material-symbols-outlined.md-40 {
       font-size: 40px;
       vertical-align:bottom;
       color:#C0C0C0;
    }

    .material-symbols-outlined.md-80 {
       font-size: 80px;
    }

    .material-symbols-outlined.md-100 {
       font-size: 100px;
    }


    .material-symbols-outlined.md-ok {color:#008000;}
    .material-symbols-outlined.md-progress {color:#FF9900;}

-->
</style>

<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {

        jQuery.fn.zaehle_zeichen = function(max, id){
            var anzahl_zeichen = jQuery(this).val().length;
            var verbleibend = max-anzahl_zeichen;
            if (verbleibend >= 0) {
                jQuery("#"+id).html("(noch "+verbleibend+" Zeichen)");
            } else {
                jQuery("#"+id).html("(<span style=\"color:#ff0000;\"><strong>noch "+verbleibend+" Zeichen</strong></span>)");
            }                 
        }
        
        jQuery.fn.change_rejection_reason_photo_album = function() {
            jQuery(".rejection_reason_photo_album_long").hide();

            var option_value = jQuery("#rejection_reason_photo_album").val();

            var text = jQuery("#long_"+option_value+" textarea").val();

            jQuery("#rejection_reason_photo_album").val(text);
            jQuery("#long_"+option_value).show();
        }

        flatpickr(".flatpickr", {
            enableTime: true,
            weekNumbers: true,
            altInput: true,
            altFormat: "Y-m-d H:i",
            minDate: "'.date("Y-m-d H:i", strtotime("+90 minutes")).'",
            time_24hr: true,
            "locale": "de"
        });

    })

// ]]>
</script>    

<form action="" method="post">
<div id="site_photo_album">
    <div style="width:745px; float:left;">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:10px;">Fotoalbum pr&uuml;fen und ver&ouml;ffentlichen</div> 

        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:100%;">
                <tr>
                    <td style="width:33.3%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 1<br />
                        <i class="material-symbols-outlined md-40 md-ok">subject</i><br />
                        Albuminfos angeben
                    </td>
                    <td style="width:33.4%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40 md-ok">cloud_upload</i><br />
                        Fotos hochladen
                    </td>
                    <td style="width:33.4%; text-align:center; font-size:15px; color:#FF9900; font-weight:bold;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40 md-progress">cloud_upload</i><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>';

        if (isset($error) AND !empty($error)) {
            $site .= '<div class="ui-state-error" style="padding:10px; margin-top:10px;">'.$error.'</div>';

            if (isset($duplicate_title)) {
                $rs_dublicate_album = p4c_query("SELECT * FROM `photo_albums` WHERE `id`='".abs($duplicate_title)."' LIMIT 1;",__FILE__,__LINE__);
                if (p4c_num_rows($rs_dublicate_album) == 1) {
                    $dublicat_ary = p4c_fetch_object($rs_dublicate_album);
                    $site .= '
                    <div class="ui-widget-header" style="border-top:none; border-bottom:none; padding:5px 10px">'.$dublicat_ary->title.'</div>
                    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
                        <table style="width:100%;">
                            <tr>
                                <td style="width:110px; vertical-align:top;">
                                    <img src="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$dublicat_ary->album_id.'?w=200&cs='.$dublicat_ary->checksum.'" style="width:100px; height:auto;" />
                                </td>
                                <td style="vertical-align:middle;">
                                    <a href="'.MCP_URL.'/Photo-Album/'.$dublicat_ary->id.'" target="_blank">Klicke hier um zum Fotoalbum zu wechseln.</a>
                                    <div style="margin:10px 0;"><b>ODER</b></div>
                                    Wenn dieses Fotoalbum tats&auml;chlich ein anderes ist, benutze hier einen neuen Titel.
                                </td>
                            </tr>
                        </table>
                    </div>';
                }
            }
        }
    
        $site .= '

        <div class="ui-widget-content" style="margin:10px 0; padding:10px;">
            <div style="display: inline-block; width: 300px;">
                P4C-PartnerID: <a href="'.Pay4Coins_ACP_URL.'/Haendler/'.$merchant->partner_id().'" target="_blank">'.$merchant->partner_id().'</a><br />
                P4C-Username: <a href="'.Pay4Coins_ACP_URL.'/Haendler/'.$merchant->partner_id().'" target="_blank">'.$merchant->username('aes_decrypt').'</a>
            </div>
            
            <div style="display: inline-block; vertical-align:top;">
                <select name="actor_id" style="font-size:25px;">
                    <option value="0">Bitte w&auml;hlen</option> 
                    ';
                    $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id` = '".abs($merchant->id())."' ORDER BY `username` ASC;",__FILE__,__LINE__);
                    if (p4c_num_rows($rs_actors) > 0) {
                        while($actor_obj = p4c_fetch_object($rs_actors)) {
                            if ($actor_obj->id == $album['actor_id']) {
                                $selected = 'selected="selected"';
                            } else {
                                $selected = '';
                            }
                            
                            $site .= '<option '.$selected.' value="'.$actor_obj->id.'">'.$actor_obj->username.'</option>';
                        }
                    }

                    $site .= '
                </select><br />
                <a href="'.ACP_URL.'/Actor/'.$album['actor_id'].'" target="_blank">Profil anzeigen</a>
            </div>

        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:15px; font-size:15px">Vorschaubilder</div>
        <div class="ui-widget-content" style="position:relative; padding:10px; border-top:none;">
            <div style="width:354px; margin-right:5px; overflow:hidden; display: inline-block; box-sizing: border-box;">
                <div class="img_group_preview" title="FSK16 Vorschaubild" href="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$album['album_id'].'?w=800&cs='.$album['checksum'].'">
                    <img src="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$album['album_id'].'?w=200&cs='.$album['checksum'].'" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                </div>
                <div style="font-size:13px; padding:5px 0; text-align: center; width: inherit;">FSK16 (unter 18 Jahre)</div>
            </div>

            <div style="width:354px; margin-left:5px; overflow:hidden; display: inline-block; box-sizing: border-box;">
                <div class="img_group_preview" title="FSK18 Vorschaubild" href="'.API_URL.'/PhotoAlbumPoster/FSK18/'.$album['album_id'].'?w=800&cs='.$album['checksum'].'">
                    <img src="'.API_URL.'/PhotoAlbumPoster/FSK18/'.$album['album_id'].'?w=200&cs='.$album['checksum'].'" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                </div>
                <div style="font-size:13px; padding:5px 0; text-align: center;">FSK18 (ab 18 Jahre)</div>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:20px; font-size:15px">'.$album['number_of_photos'].' Fotos im Album</div>
        <div class="ui-widget-content" style="position:relative; padding: 10px 0 10px 10px; border-top:none;">
            <ul id="photos">
                ';
                if($count_photos > 0) {
                    while($foto_obj = p4c_fetch_object($rs_photos)) {
                        $site .= '
                        <li class="ss">
                            <img src="'.ACP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=200" alt="" />
                            <div class="zoom">
                                <span class="material-symbols-outlined zoom_goup" href="'.ACP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=800">zoom_in</span>
                            </div>
                        </li>
                        ';
                    }
                }
                $site .= ' 
            </ul>
        </div>
        
        <div class="ui-widget-header" style="padding:5px 10px; margin-top:20px; font-size:15px">Angaben zum Album</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">
            <div class="edit_title">Gib einen aussagekr&auml;ftigen Titel f&uuml;r das Album an. <span id="anzahl_title">(max. 65 Zeichen)</span></div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="title" value="'.$album['title'].'" onkeyup="jQuery(this).zaehle_zeichen(65, \'anzahl_title\')" placeholder="Gib einen aussagekr&auml;ftigen Titel f&uuml;r das Fotoalbum an." style="font-size:18px;" />
            </div>

            <div class="edit_title">Gib eine gute und aussagekr&auml;ftige <b>Beschreibung</b> f&uuml;r das Fotoalbum an.</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <textarea name="description" id="description" >'.$album['description'].'</textarea>
                <script>
                    CKEDITOR.replace("description", {customConfig: "'.MCP_URL.'/fw/ckeditor/movie_upload_config.js"});
                </script>
            </div>

            <div class="edit_title">Ab wann soll das Fotoalbum fr&uuml;hsten ver&ouml;ffentlicht werden?</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input class="flatpickr" name="online_at" type="text" value="'.$album['online_at'].'" />
            </div>

            <div class="edit_title">Wieviel soll das Fotoalbum kosten?</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <select name="amount_download">';
                    for($i=0;$i<=50;$i++) {

                        $amount = $i*100; // in cent
                        
                        if (($album['amount_download'] == $amount)) {
                                $selected = 'selected="selected"';
                        } else {
                                $selected = '';
                        }

                        $site .= '<option '.$selected.' value="'.$amount.'">'.number_format($i, '2', '.', '').' EUR</option>';
                    }
                    unset($i);
                    $site .= '
                </select>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Suchmaschinenoptimierung (SEO)</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:10px;">
            <div class="edit_title">Meta Description <span id="anzahl_meta_description">(max. 165 Zeichen)</span> <b>KEINE Keywords oder Hashtags!</b></div>
            <div class="edit_content" style="margin-bottom:8px;">
                <textarea type="text" name="meta_description" placeholder="Gib hier eine kurze aussagekr&auml;ftige Beschreibung an." onkeyup="jQuery(this).zaehle_zeichen(156, \'anzahl_meta_description\')">'.$album['meta_description'].'</textarea>
                <span style="font-size:10px;">Beschreibe das Fotoalbum so interessant wie m&ouml;glich mit maximal 156 Zeichen.</span>
            </div>

            <div class="edit_title">Meta Title <span id="anzahl_meta_title">(max. 65 Zeichen)</span></div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="meta_title" value="'.$album['meta_title'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" />
            </div>

            <div class="edit_title">SEO-URL (URL-Name)</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="seo_url" value="'.$album['seo_url'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" />
                <span style="font-size:10px; color:#ff0000;">Wenn die URL ge&auml;ndert wird, verliert das Fotoalbum sein Ranking in den Suchmaschinen!</span>
            </div>
        </div>

        <div style="margin-top:15px; margin-bottom:30px;">
            <table style="width:100%">
                <tr>
                    <td style="width:50%">

                    </td>
                    <td style="width:50%; text-align:right;">
                        <input type="submit" id="save_movie" name="btn[save_album]" class="button" value="&Auml;nderung speichern" />
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div style="width:600px; margin-left:765px;">';

        $rs_rejection_reason_photo_album_history = p4c_query("SELECT * FROM `rejection_reason_photo_album_history` WHERE `album_id`='".p4c_escape_string($a->field('album_id'))."' ORDER BY `datetime` DESC;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_rejection_reason_photo_album_history) > 0) {
            $site .= '
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">History</div>
            <div class="ui-widget-content edit_content" style="margin-bottom:8px; border-bottom:none; border-top:none;">';
                while($reson_history_obj = p4c_fetch_object($rs_rejection_reason_photo_album_history)) {
                    $rs_rejection_reason_photo_album = p4c_query("SELECT `de_short` FROM `rejection_reason_photo_album` WHERE `id_name`='".$reson_history_obj->id_name."' ORDER BY `de_short` ASC LIMIT 1;",__FILE__,__LINE__);
                    
                    $employee_name = p4c_query("SELECT `username` FROM `employee` WHERE `id`='".abs($reson_history_obj->employee_id)."' LIMIT 1;",__FILE__,__LINE__);
                    $rejection_reason_username = p4c_result($employee_name, 0);
                    if (empty($rejection_reason_username)) {
                        $rejection_reason_username = '-unbekannt-';
                    }
                    
                    $site .= '
                    <div style="padding:5px; border-bottom:1px solid">
                        <div><b>'. p4c_result($rs_rejection_reason_photo_album, 0).'</b></div>
                        <div>Zeitpunkt: '.$reson_history_obj->datetime.'</div>
                        <div>Abgeleht von: '.$rejection_reason_username.'</div>
                        <div>Mehr Infos zur Ablehnung:<br />'.nl2br($reson_history_obj->text).'</div>
                    </div>';
                }
                $site .= '
            </div>';
        }
        
        $site .= '
        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Album-Status</div>
        <div class="ui-widget-content radioset" style="font-size:12px; padding:10px; border-top:none; margin-bottom:10px;">';
        
            // gesperrt = Album nicht sichtbar. Bereits gekaufter Content weiterhin online.
            // gelöscht = Sofern das Album noch nicht gekauft wurde, wird es gelöscht. Ansonsten ist es nur nicht sichbar. Für Kunden die es gesehen haben trotzdem weiterhin online
        
            if ($album['status'] == 'active') {
                $staus1 = 'checked="checked"';
                $staus2 = '';
                $staus3 = '';
            } else if ($album['status'] == 'blocked') {
                $staus1 = '';
                $staus2 = 'checked="checked"';
                $staus3 = '';
            } else if ($album['status'] == 'deleted') {
                $staus1 = '';
                $staus2 = '';
                $staus3 = 'checked="checked"';
            }
            
            $site .= '
            <label for="status1">Aktiv</label><input '.$staus1.' class="radio" type="radio" name="album_status" value="active" id="status1" />
            <label for="status2">Gesperrt</label><input '.$staus2.' class="radio" type="radio" name="album_status" value="blocked" id="status2" /> 
            <label for="status3">Gel&ouml;scht</label><input '.$staus3.' class="radio" type="radio" name="album_status" value="deleted" id="status3" /> 
            <div class="ui-state-error" id="alert_status" style="display:none; padding:10px; margin-top:5px;">
                <span id="alert_status_mess"></span>
            </div>
            ';
            if ($album['released_from'] != '-') {
                $site .= '
                <div style="margin-top:10px;">Freigeschaltet am '.date("d.m.Y \u\m H:i", strtotime($album['released_date'])).' von '.$album['released_from'].'</div>';
            }

            if ($album['last_updated_by'] != '-') {
                $site .= '
                <div style="margin-top:10px;">Zuletzt bearbeitet am '.date("d.m.Y \u\m H:i", strtotime($album['last_updated_date'])).' Uhr von '.$album['last_updated_by'].'</div>';
            }
            $site .= '
        </div>
        
        <div class="ui-widget-header" style="padding:5px 10px;">Admin-Infos zum Album</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">
            <textarea style="min-height:100px; width:100%; width: -webkit-fill-available; width: -moz-available;" name="admin_infos">'.$album['admin_infos'].'</textarea>
        </div>';
        
        /**
         * Kategorien
         */
        $site .= '
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin:20px 0px 10px 0px;">Fotoalbum kategorisieren</div> 

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Hauptkategorie</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:8px; border-top:none;">';
            if ($album['category_master'] == 'porn' OR $album['category_master'] == '') {$checked_porn = 'checked="checked"';} else {$checked_porn='';}
            $site .= '
            <div style="padding:5px; border-bottom:1px solid">
                <div style="font-weight:bold;">
                    <label for="porn"><input type="radio" '.$checked_porn.' id="porn" name="category_master" value="porn" /> Porno</label>
                </div>
                <div style="padding-left:20px;">
                    W&auml;hle diese Kategorie, wenn das Fotoalbum pornografische Inhalte enth&auml;lt.
                </div>
            </div>';
            if ($album['category_master'] == 'fetish') {$checked_fetish = 'checked="checked"';} else {$checked_fetish='';}
            $site .= '
            <div style="padding:5px; border-bottom:1px solid">
                <div style="font-weight:bold;">
                    <label for="fetish"><input type="radio" '.$checked_fetish.' id="fetish" name="category_master" value="fetish" /> Fetisch</label>
                </div>
                <div style="padding-left:20px;">
                    W&auml;hle diese Kategorie, wenn das Fotoalbum ein reines Fetischalbum, wie SM, BDSM usw. ist.
                </div>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">W&auml;hle alle Kategorien die zum Fotoalbum passen</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:8px; border-top:none;">
           <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">Anzahl der Personen & sexuelle Orientierung</div>';
            $category_ary = explode(',', $album['category_slave']);
            $rs_photo_albums_categories = p4c_query("SELECT * FROM `photo_albums_categories` WHERE `category_group`='number_of_people' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_photo_albums_categories)) {
                if (in_array($category_obj->name_id, $category_ary)) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                } else {
                    $checked_cat_slave='';
                    $style = '';
                }
                
                $site .= '
                <div style="padding:5px 5px 5px 20px; border-bottom:1px solid; '.$style.'">
                    <div style="font-weight:bold;">
                        <label for="'.$category_obj->name_id.'"><input type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="category_slave[]" value="'.$category_obj->name_id.'" /> '.$category_obj->de_name_value.'</label>
                    </div>
                    <div style="padding-left:20px;">
                        '.$category_obj->de_name_text.'
                    </div>
                </div>';
            }
            $site .= '
            <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">K&ouml;rper und Aussehen</div>';
            $category_ary = explode(',', $album['category_slave']);
            $rs_photo_albums_categories = p4c_query("SELECT * FROM `photo_albums_categories` WHERE `category_group`='look_and_body' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            $actor_categories = explode(',',$actor->get('actor_categories'));

            while($category_obj = p4c_fetch_object($rs_photo_albums_categories)) {

                if (in_array($category_obj->name_id, $category_ary) OR in_array($category_obj->name_id, $actor_categories)) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                } else {
                    $checked_cat_slave='';
                    $style = '';
                }                

                $site .= '
                <div style="padding:5px 5px 5px 20px; border-bottom:1px solid; '.$style.'">
                    <div style="font-weight:bold;">
                        <label for="'.$category_obj->name_id.'"><input type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="category_slave[]" value="'.$category_obj->name_id.'" /> '.$category_obj->de_name_value.'</label>
                    </div>
                    <div style="padding-left:20px;">
                        '.$category_obj->de_name_text.'
                    </div>
                </div>';
            }
            $site .= '
            <div style="padding:15px 5px 5px 5px; border-bottom:1px solid; font-weight:bold;">Fetisch</div>';
            $category_ary = explode(',', $album['category_slave']);
            $rs_photo_albums_categories = p4c_query("SELECT * FROM `photo_albums_categories` WHERE `category_group`='fetish' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_photo_albums_categories)) {
                if (in_array($category_obj->name_id, $category_ary)) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                } else {
                    $checked_cat_slave='';
                    $style = '';
                }

                $site .= '
                <div style="padding:5px 5px 5px 20px; border-bottom:1px solid; '.$style.'">
                    <div style="font-weight:bold;">
                        <label for="'.$category_obj->name_id.'"><input type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="category_slave[]" value="'.$category_obj->name_id.'" /> '.$category_obj->de_name_value.'</label>
                    </div>
                    <div style="padding-left:20px;">
                        '.$category_obj->de_name_text.'
                    </div>
                </div>';
            }
            $site .= '
            <div style="padding:15px 5px 5px 5px; border-bottom:1px solid; font-weight:bold;">Sonstige</div>';
            $category_ary = explode(',', $album['category_slave']);
            $rs_photo_albums_categories = p4c_query("SELECT * FROM `photo_albums_categories` WHERE `category_group`='porn' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_photo_albums_categories)) {
                if (in_array($category_obj->name_id, $category_ary)) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                } else {
                    $checked_cat_slave='';
                    $style = '';
                }

                $site .= '
                <div style="padding:5px 5px 5px 20px; border-bottom:1px solid; '.$style.'">
                    <div style="font-weight:bold;">
                        <label for="'.$category_obj->name_id.'"><input type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="category_slave[]" value="'.$category_obj->name_id.'" /> '.$category_obj->de_name_value.'</label>
                    </div>
                    <div style="padding-left:20px;">
                        '.$category_obj->de_name_text.'
                    </div>
                </div>';
            }
            $site .= '
        </div>
    </div>
</div>
</form>';