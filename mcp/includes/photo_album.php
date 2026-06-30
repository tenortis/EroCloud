<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!isset($_GET['id'])) {exit;}

$album_id = abs($_GET['id']);

$a = new PhotoAlbum($mysql,$album_id);

if ($a->field('id') == '') {
    header('Location: '.MCP_URL.'/Photo-Albums');
    exit;
}


$amount_webmaster_ary = array(0, 5, 10, 15, 20, 25);
$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['edit_album'])) {
    
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
    
    // Prüfe ob bei diesem Kunden bereichts ein Fotoalbum mit diesem Title existiert
    $rs_check_album_exists = p4c_query("SELECT `id`  FROM `photo_albums` WHERE `title`='".p4c_escape_string($album['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`!='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_album_exists) == 1) {
        $error = 'Du hast bereits eine Fotoalbum mit diesem Titel hochgeladen.';
        $duplicate_title = p4c_result($rs_check_album_exists, 0);
    } else {
    
        if(!isset($_POST['description'])) {
            $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung f&uuml;r das Fotoalbum an.';
        } else {
            $allowed_tags = '<ul><ol><li><u><em><strong><h1 class="h4"><h2><h3><h4><h5><h6><pre><address><p>';
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
        
        if(isset($_POST['amount_webmaster'])) {
            $amount_webmaster = abs($_POST['amount_webmaster']);
            if (in_array($amount_webmaster, $amount_webmaster_ary)) {
                $album['amount_webmaster'] = $amount_webmaster;
            }
        }

        $album['meta_title'] = substr($_POST['title'], 0, 65);

        $album['seo_url'] = seo_url($album['title']);

        if(!isset($_POST['meta_description']) OR trim($_POST['meta_description']) == '') {
            $album['meta_description'] = substr(trim(strip_tags($album['description'])), 0, 156);
        } else {
            $album['meta_description'] = substr(trim(strip_tags($_POST['meta_description'])), 0, 156);
            if (empty($album['meta_description'])) {$album['meta_description'] = substr(trim(strip_tags($album['description'])), 0, 156);}
        }

        if (!isset($error) OR empty($error)) {
            $rs_count_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='". p4c_escape_string($a->field('album_id'))."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
            $album['number_of_photos'] = p4c_num_rows($rs_count_photos);

            if (p4c_query("UPDATE `photo_albums` SET
                `actor_id` = '".abs($album['actor_id'])."',
                `number_of_photos`='".abs($album['number_of_photos'])."',
                `title` = '".p4c_escape_string($album['title'])."',
                `description` = '".p4c_escape_string($album['description'])."',
                `meta_title` = '".p4c_escape_string($album['meta_title'])."',
                `meta_description` = '".p4c_escape_string($album['meta_description'])."',
                `seo_url` = '".p4c_escape_string($album['seo_url'])."',
                `online_at` = '".p4c_escape_string($album['online_at'])."',
                `amount_webmaster` = '".abs($album['amount_webmaster'])."',
                `amount_download` = '".abs($album['amount_download'])."',
                `album_checked` = '0000-00-00 00:00:00',
                `released`='1'
                WHERE `id`='".abs($album_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__)
            ) {
                header('Location: '.MCP_URL.'/Photo-Album-Upload?step=3&album_id='.$album_id);
                exit;

            } else {
                $error = 'Das Fotoalbum konnte nicht gespeichert werden!';
            }        
        }
    }
}


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
$album['album_checked']  = $a->field('album_checked');
$album['released'] = $a->field('released');

$rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='".p4c_escape_string($a->field('album_id'))."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC;",__FILE__,__LINE__);
$count_photos = p4c_num_rows($rs_photos);

$site .= '
<style type="text/css">
<!--
    input.button.ui-widget {font-size:16px !important;}
-->
</style>

<link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
<script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.1.4/l10n/de.js"></script>

<script src="//cdn.ckeditor.com/4.6.2/full/ckeditor.js"></script>

<div id="site_photo_album_upload" style="width:745px;">
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
                ';
                // Album zur Prüfung freigegeben
                if ($album['album_checked'] == '0000-00-00 00:00:00' AND $album['released'] == 1) {
                    $site .= '
                    <td style="width:33.3%; text-align:center; color:#FF9900; font-size:15px;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40 md-progress">cloud_done</i><br />
                        in Pr&uuml;fung
                    </td>
                    ';
                // Album abgelehnt
                } else if ($album['album_checked'] == '0000-00-00 00:00:00' AND $album['released'] == 2) {
                    $site .= '
                    <td style="width:33.3%; text-align:center; color:#ff0000; font-size:15px;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40 md-error">cloud_off</i><br />
                        Abgelehnt!
                    </td>
                    ';  
                } else {
                    $site .= '
                    <td style="width:33.3%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40 md-ok">cloud_upload</i><br />
                        ver&ouml;ffentlichen
                    </td>';
                }
                $site .= '
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

    <div class="ui-widget-header" style="padding:5px 10px; margin-top:20px; font-size:15px">'.$count_photos.' Fotos im Album</div>
    <div class="ui-widget-content" style="position:relative; padding: 10px 0 10px 10px; border-top:none;">
        <ul id="photos">
            ';
            if($count_photos > 0) {
                $i=1;
                while($foto_obj = p4c_fetch_object($rs_photos)) {
                    if ($foto_obj->rejected == '1') {$rejected='style="display:inline-block;"';} else {$rejected='';}
                    $site .= '
                    <li>
                        <img src="'.MCP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=200" alt="" />
                        <div class="zoom">
                            <span class="material-symbols-outlined zoom_goup" href="'.MCP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=800">zoom_in</span>
                            <div class="picture_id"><span>ID: '.$i.'</span></div>
                        </div>
                        <div class="rejected_picture" '.$rejected.' data-tooltip="Dieses Foto wurde abgelehnt.">
                            <span class="material-symbols-outlined zoom_goup" href="'.ACP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=800">clear</span>
                            <div class="delete_picture" data-tooltip="Foto l&ouml;schen" data-photo-id="'.$foto_obj->file_id.'" data-album-id="'.$foto_obj->album_id.'">
                                <span class="material-symbols-outlined">delete_forever</span>
                            </div>
                            <div class="picture_id"><span>ID: '.$i.'</span></div>
                        </div>
                        <div class="loader"></div>
                    </li>
                    ';
                    $i++;
                }
            }
            $site .= ' 
        </ul>
    </div>

    <form action="'.MCP_URL.'/Photo-Album/'.$album_id.'" method="post">
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
                    CKEDITOR.replace("description", {customConfig: "'.MCP_URL.'/fw/ckeditor/movie_upload_config.js?v=1"});
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

        <div class="ui-widget-header" style="padding:5px 10px; border-top:none;">weitere Einstellungen</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">

            <div class="edit_title">Welchem Darsteller soll das Album zugeordnet werden?</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <select name="actor_id">';
                    $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`='active' ORDER BY `username` ASC",__FILE__,__LINE__);
                    if (p4c_num_rows($rs_actors) > 0) {
                        while($actor_obj = p4c_fetch_object($rs_actors)) {

                            if ($album['actor_id'] == $actor_obj->id) {$selected = 'selected';} else {$selected = '';}
                            $site .= '<option value="'.$actor_obj->id.'" '.$selected.'> '.$actor_obj->username.'</option>';
                        }
                    }
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
                <input type="text" name="meta_title" value="'.$album['meta_title'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" disabled="disapled" />
            </div>

            <div class="edit_title">SEO-URL (URL-Name)</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="seo_url" value="'.$album['seo_url'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" disabled="disapled" />
            </div>
        </div>

        <div style="margin-top:15px; margin-bottom:30px; text-align:right;">
            <input type="submit" name="edit_album" class="button" value="&Auml;nderung speichern" />
        </div>
    </form>
</div>';