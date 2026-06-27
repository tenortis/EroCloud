<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

function delete_directory($dirname, $album_obj) {
    if(is_dir($dirname)) {
        $dir_handle = opendir($dirname);
    }
    
    //Falls Verzeichnis nicht geoeffnet werden kann, mit Fehlermeldung terminieren
    if($dir_handle) {
        while($file=readdir($dir_handle)) {
            if($file!="." && $file!="..")  {
                //Datei loeschen 
                if(!is_dir($dirname."/".$file)) {
                    @unlink($dirname."/".$file);
                //Falls es sich um ein Verzeichnis handelt, "delete_directory" aufrufen
                } else {
                    delete_directory($dirname.'/'.$file, $album_obj);
                }
            }
        }

        closedir($dir_handle);
        //Verzeichnis loeschen
        rmdir($dirname);
    } 
    
    $merchant_id = $_SESSION['merchant_id'];
    
    p4c_query("DELETE FROM `photo_albums_photos` WHERE
        `album_id`='". p4c_escape_string($album_obj->album_id)."' AND
        `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

    p4c_query("DELETE FROM `photo_albums` WHERE
        `album_id`='". p4c_escape_string($album_obj->album_id)."' AND
        `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);        
    
    return true;
}


if (isset($_POST['checking'])) {
    $album_id = abs($_POST['checking']);

    $album = new PhotoAlbum($mysql,$album_id);

    if ($album->field('id') == '') {
        header('Location: '.MCP_URL.'/Photo-Albums');
        exit;
    }
    
    $rs_count_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='". p4c_escape_string($album->field('album_id'))."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
    
    p4c_query("UPDATE `photo_albums` SET
        `number_of_photos`='".abs(p4c_num_rows($rs_count_photos))."',
        `album_checked` = '0000-00-00 00:00:00',
        `released`='1'
        WHERE `id`='".abs($album_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Photo-Album-Upload?step=3&album_id='.$album_id);
    exit;
}


if (isset($_POST['delete_album'])) {
    $album_id = abs($_POST['album_id']);
    $merchant_id = $_SESSION['merchant_id'];
    
    $rs_albums = p4c_query("SELECT * FROM `photo_albums` WHERE
        `id`='". abs($album_id)."' AND
        `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

    if (p4c_num_rows($rs_albums) > 0) {
        $album_obj = p4c_fetch_object($rs_albums);

        $filename = PHOTO_ALBUMS_PATH.'/'.$album_obj->storage_location.'/'.$album_obj->merchant_id.'/'.$album_obj->id;

        if (delete_directory($filename, $album_obj) === true) {
            header('Location: '.MCP_URL.'/Photo-Albums?del=ok');
            exit;
            
        } else {
            $error = "Das Album konnte nicht gel&ouml;scht werden.";
        }
    }
    
    exit;
}


$album['title'] = '';
$album['description'] = '';
$album['online_at'] = date("Y-m-d H:i", strtotime("+90 minutes"));
$album['amount_webmaster'] = 10;
$album['amount_download'] = 1;
$album['meta_title'] = '';
$album['meta_description'] = '';
$album['seo_url'] = '';
$album['actor_id'] = '';

$amount_webmaster_ary = array(0, 5, 10, 15, 20, 25);
$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['upload_content'])) {
    
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
    
    $album_id = '';
    if (isset($_SESSION['upload_album']['album_id'])) {
        $album_id = abs($_SESSION['upload_album']['album_id']);
    }
    
    // Prüfe ob bei diesem Kunden bereichts ein Fotoalbum mit diesem Title existiert
    $rs_check_album_exists = p4c_query("SELECT `id`  FROM `photo_albums` WHERE `title`='".p4c_escape_string($album['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`!='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_album_exists) == 1) {
        $error = 'Du hast bereits eine Fotoalbum mit diesem Titel hochgeladen.';
        $duplicate_title = p4c_result($rs_check_album_exists, 0);
    }

    // Prüfe ob bei diesem Kunden exakt dieses Album schon existiert -> dann updaten nicht neu anlegen
    $rs_check_album_exists = p4c_query("SELECT `id`, `album_id`  FROM `photo_albums` WHERE `title`='".p4c_escape_string($album['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_album_exists) == 1) {
        $album_exists = true;  
    }
    
    $_SESSION['upload_album'] = $album;
    $_SESSION['upload_album']['album_id'] = $album_id;
    
    if (!isset($error) OR empty($error)) {
        
        if (isset($album_exists) AND $album_exists === true) {
            
            $album_obj = p4c_fetch_object($rs_check_album_exists);
            
            $rs_count_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='". p4c_escape_string($album_obj->album_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
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
                `amount_download` = '".abs($album['amount_download'])."'
                WHERE `id`='".abs($album_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__)
            ) {

                $_SESSION['upload_album']['album_id'] = $album_id;

                header('Location: '.MCP_URL.'/Photo-Album-Upload?step=2&album_id='.$album_id);
                exit;

            } else {
                $error = 'Das Fotoalbum konnte nicht gespeichert werden!';
            }        
            
        } else {
        
            $file_id = md5($_SESSION['merchant_id'].time());

            if (p4c_query("INSERT INTO `photo_albums` SET
                `actor_id` = '".abs($album['actor_id'])."',
                `album_id` = '".p4c_escape_string($file_id)."',
                `create_datetime` = '".date("Y-m-d H:i:s")."',
                `merchant_id` = '".abs($_SESSION['merchant_id'])."',
                `storage_location` = '".PHOTO_ALBUMS_DEFAULT_DIR."',
                `title` = '".p4c_escape_string($album['title'])."',
                `description` = '".p4c_escape_string($album['description'])."',
                `meta_title` = '".p4c_escape_string($album['meta_title'])."',
                `meta_description` = '".p4c_escape_string($album['meta_description'])."',
                `seo_url` = '".p4c_escape_string($album['seo_url'])."',
                `online_at` = '".p4c_escape_string($album['online_at'])."',
                `amount_webmaster` = '".abs($album['amount_webmaster'])."',
                `amount_download` = '".abs($album['amount_download'])."';",__FILE__,__LINE__)
            ) {
                $album_id = p4c_insert_id();

                $_SESSION['upload_album']['album_id'] = $album_id;

                header('Location: '.MCP_URL.'/Photo-Album-Upload?step=2&album_id='.$album_id);
                exit;

            } else {
                $error = 'Das Fotoalbum konnte nicht gespeichert werden!';
            }
        }
    }
    
} else if (isset($_SESSION['upload_album'])) {
    $album = $_SESSION['upload_album']; 
    $album_id = $_SESSION['upload_album']['album_id'];
}

$site .= '
<style type="text/css">
<!--
    input.button.ui-widget {font-size:16px !important;}
-->
</style>

<div id="site_photo_album_upload" style="width:745px;">
    <h1 class="h4">Fotoalbum anlegen und in die EroCoud hochladen</h1>
    ';

    if (!isset($_GET['step']) OR (isset($_GET['step']) AND $_GET['step'] == 1)) {
        
        if (isset($album_id)) {
            
            $f = new PhotoAlbum($mysql,$album_id);

            if ($f->field('id') != '') {
                $album['actor_id'] = $f->field('actor_id');
                $album['title'] = $f->field('title');
                $album['description'] = $f->field('description');
                $album['online_at'] = $f->field('online_at');
                $album['amount_download'] = $f->field('amount_download');
                $album['meta_description'] = $f->field('meta_description');
                $album['meta_title'] = $f->field('meta_title');
                $album['seo_url'] = $f->field('seo_url');
                $album['released'] = $f->field('released');
            }
        }
        $site .= '    
        <link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
        <script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.1.4/l10n/de.js"></script>

        <script src="//cdn.ckeditor.com/4.6.2/full/ckeditor.js"></script>

        <script type="text/javascript">
        // <![CDATA[
        	jQuery(document).ready(function() {
           
                jQuery(".content_ruels").click(function() {
                    jQuery("#overlay").show(function() {
                        jQuery(".content_ruels_popup").show();
                    });
                });

                jQuery(".close_overlay").click(function() {
                    jQuery(".content_ruels_popup").hide(function() {
                        jQuery("#overlay").hide();          
                    });
                }); 
           
                jQuery.fn.zaehle_zeichen = function(max, id){
                    var anzahl_zeichen = jQuery(this).val().length;
                    var verbleibend = max-anzahl_zeichen;
                    if (verbleibend >= 0) {
                        jQuery("#"+id).html("(noch "+verbleibend+" Zeichen)");
                    } else {
                        jQuery("#"+id).html("(<span style=\"color:#ff0000;\"><strong>noch "+verbleibend+" Zeichen</strong></span>)");
                    }                 
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
        
        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:600px">
                <tr>
                    <td style="width:33.3%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                        Schritt 1<br />
                        <i class="material-symbols-outlined md-40 md-ok">subject</i><br />
                        Albuminfos angeben
                    </td>
                    ';
                    if (isset($album_exists) AND $album_exists === true) {
                        $site .= '
                        <td style="width:33.4%; text-align:center; font-size:15px; color:#FF9900; font-weight:bold;">
                            Schritt 2<br />
                            <a href="?step=2&album_id='.$album_id.'"><i class="material-symbols-outlined md-40 md-progress">cloud_upload</i></a><br />
                            Fotos hochladen
                        </td>';
                    } else {
                        $site .= ' 
                        <td style="width:33.4%; text-align:center; font-size:15px;">
                            Schritt 2<br />
                            <i class="material-symbols-outlined md-40">cloud_upload</i><br />
                            Fotos hochladen
                        </td>';
                    }
                    $site .= ' 
                    <td style="width:33.3%; text-align:center; font-size:15px;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40">cloud_done</i><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>
		
        <div style="text-align:center; margin:10px 0 5px 0;"><a class="photo_album_tips" href="javascript:;">Hinweise zur Qualit&auml;t deiner Fotoalben</a></div>
        ';
                    
        if (isset($album['released']) AND $album['released'] == 2) {
            $rs_rejection_reason_album_history = p4c_query("SELECT * FROM `rejection_reason_photo_album_history` WHERE `album_id`='".p4c_escape_string($f->field('album_id'))."' ORDER BY `datetime` DESC LIMIT 1;",__FILE__,__LINE__);
            $reson_history_obj = p4c_fetch_object($rs_rejection_reason_album_history);
            $rs_rejection_reason_album = p4c_query("SELECT `id_name`, `de_short` FROM `rejection_reason_photo_album` WHERE `id_name`='".$reson_history_obj->id_name."' LIMIT 1;",__FILE__,__LINE__);
            
            $grund = '';
            if (p4c_result($rs_rejection_reason_album, 0, 0) != 'free_text') {
                $grund = $grund = p4c_result($rs_rejection_reason_album, 0, 1);
            }
            
            $site .= '
            <div class="ui-state-error" style="font-size:18px; font-weight:bold; margin-top:10px; padding:5px 10px">Das Album wurde leider abgelehnt.</div>
            <div class="ui-state-error" style="margin-bottom:10px; border-top:none;">
                <div style="padding:5px 10px">
                    Um den Kunden bestm&ouml;gliche Qualit&auml;t an Fotos anbieten zu k&ouml;nnen und um deine Verkaufszahlen zu erh&ouml;hen, achten wir streng auf die Qualit&auml;t deiner Fotos. Bitte lies dir auch die <a class="photo_album_tips" href="javascript:;">Hinweise zur Qualit&auml;t deiner Fotoalben</a> durch.
                </div>
			
                <div style="font-weight:bold; padding:5px 10px">Grund: '.$grund.'</div>
                <div style="padding:5px 10px 10px 10px;">
                    '.nl2br($reson_history_obj->text).'
                </div>
            </div>
            ';
        }
    
        if (isset($error) AND !empty($error)) {
            $site .= '<div class="ui-state-error" style="padding:10px; margin-top:10px;">'.$error.'</div>';
            
            if (isset($duplicate_title)) {
                $rs_dublicate_album = p4c_query("SELECT * FROM `photo_albums` WHERE `id`='".abs($duplicate_title)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
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
        <form action="" method="post">
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Angaben zum Fotoalbum</div>
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
                    <input type="text" name="meta_title" value="'.$album['meta_title'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" />
                </div>
               
                <div class="edit_title">SEO-URL (URL-Name)</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="seo_url" value="'.$album['seo_url'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" />
                </div>
            </div>      
           
            <div style="margin-top:15px; margin-bottom:30px; text-align:right;">
                <input type="button" class="content_ruels button" value="Speichern und weiter mit Schritt 2" />
            </div>';

            include_once(MCP_DIR.'/includes/overlays/content_rules.php');
                    
            $site .= '          
        </form>
        ';
    } else if (isset($_GET['step']) AND $_GET['step'] == 2 AND isset($_GET['album_id'])) {
        $album_id = abs(filter_input(INPUT_GET, 'album_id', FILTER_SANITIZE_NUMBER_INT));

        $album_exists = true;
        
        // Prüfe ob dieses Album existiert
        $rs_check_album_exists = p4c_query("SELECT `id`  FROM `photo_albums` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_check_album_exists) == 0) {
            $album_exists = false;
        }

        // Prüfen ob dieser Album noch nicht veröffentlicht wurde.
        $rs_check_photo_albums_online_exists = p4c_query("SELECT `id`  FROM `photo_albums_online` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `album_id`='".abs($album_id)."' LIMIT 1;",__FILE__,__LINE__);        
        if (p4c_num_rows($rs_check_photo_albums_online_exists) == 1) {
            $album_exists = true;
        }
        
        if ($album_exists == false) {
            header('Location: '.MCP_URL.'/Photo-Albums');
            exit;
        }
       
        $f = new PhotoAlbum($mysql,$album_id);

        if ($f->field('id') == '') {
            header('Location: '.MCP_URL.'/Photo-Albums');
            exit;
        }
        
        $_SESSION['upload_album']['album_id'] = $album_id;
/*        
        if (isset($_SESSION['upload_album']['album_id'])) {
            $album_id = abs($_SESSION['upload_album']['album_id']);
            $rs_check_album_exists = p4c_query("SELECT * FROM `photo_albums` WHERE `id`='".abs($album_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_check_album_exists)) {
                $album_exists = true;
            }
        }
  */
        
        $rs_photos = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `album_id`='".p4c_escape_string($f->field('album_id'))."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC;",__FILE__,__LINE__);
        $count_photos = p4c_num_rows($rs_photos);
        
        $site .= '
        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:100%;">
                <tr>
                    <td style="width:33.3%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 1<br />
                        <a href="'.MCP_URL.'/Photo-Album-Upload?step=1"><i class="material-symbols-outlined md-40 md-ok">subject</i></a><br />
                        Albuminfos angeben
                    </td>
                    <td style="width:33.4%; text-align:center; font-size:15px; color:#FF9900; font-weight:bold;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40 md-progress">cloud_upload</i><br />
                        Fotos hochladen
                    </td>
                    <td style="width:33.3%; text-align:center; font-size:15px;">
                        Schritt 4<br />
                        <i class="material-symbols-outlined md-40">cloud_done</i><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>';
        
        if ($f->field('released') == 2) {
            $rs_rejection_reason_album_history = p4c_query("SELECT * FROM `rejection_reason_photo_album_history` WHERE `album_id`='".p4c_escape_string($f->field('album_id'))."' ORDER BY `datetime` DESC LIMIT 1;",__FILE__,__LINE__);
            $reson_history_obj = p4c_fetch_object($rs_rejection_reason_album_history);
            $rs_rejection_reason_album = p4c_query("SELECT `id_name`, `de_short` FROM `rejection_reason_photo_album` WHERE `id_name`='".$reson_history_obj->id_name."' LIMIT 1;",__FILE__,__LINE__);
            
            $grund = '';
            if (p4c_result($rs_rejection_reason_album, 0, 0) != 'free_text') {
                $grund = $grund = p4c_result($rs_rejection_reason_album, 0, 1);
            }
            
            $site .= '
            <div class="ui-state-error" style="font-size:18px; font-weight:bold; margin-top:10px; padding:5px 10px">Das Album wurde leider abgelehnt.</div>
            <div class="ui-state-error" style="margin-bottom:10px; border-top:none;">
                <div style="padding:5px 10px">
                    Um den Kunden bestm&ouml;gliche Qualit&auml;t an Fotos anbieten zu k&ouml;nnen und um deine Verkaufszahlen zu erh&ouml;hen, achten wir streng auf die Qualit&auml;t deiner Fotos. Bitte lies dir auch die <a class="photo_album_tips" href="javascript:;">Hinweise zur Qualit&auml;t deiner Fotoalben</a> durch.
                </div>
			
                <div style="font-weight:bold; padding:5px 10px">Grund: '.$grund.'</div>
                <div style="padding:5px 10px 10px 10px;">
                    '.nl2br($reson_history_obj->text).'
                </div>
            </div>
            ';
        }
        
        
        $local_nav = '
        <div style="margin-top:15px;">
            <table style="width:100%">
                <tr>
                    <td style="width:140px; text-align:left;">
                        <form action="'.MCP_URL.'/Photo-Album-Upload" method="get">
                            <input type="hidden" name="step" value="1" />
                            <input type="submit" class="button" value="Albuminfos" />
                        </form>
                    </td>
                    <td style="width:280px; text-align:center;">
                        <form action="'.MCP_URL.'/Photo-Album-Upload" method="post">
                            <input type="hidden" name="album_id" value="'.$album_id.'" />
                            <input type="submit" name="delete_album" class="button" value="Album l&ouml;schen" />
                        </form>
                    </td>
                    <td style="width:auto; text-align:right;">';
                        if($count_photos > 0) {
                            $local_nav .= '
                                <form action="'.MCP_URL.'/Photo-Album-Upload" method="post">
                                    <input type="hidden" name="checking" value="'.$album_id.'" />
                                    <input type="submit" id="save_photo_album" class="button" value="Jetzt in der '.PROJECTNAME.' ver&ouml;ffentlichen" />
                                </form>';
                        } else {
                            $local_nav .= '<input type="submit" id="save_photo_album" class="button" disabled value="Jetzt in der '.PROJECTNAME.' ver&ouml;ffentlichen" />';
                        }
                        $local_nav .= '
                    </td>
                </tr>
            </table>
        </div>';
        
        $site .= $local_nav;
        
        $site .= '
        <div class="ui-widget-header" style="padding:5px 10px; margin-top:15px; font-size:15px">Vorschaubilder hochladen</div>
        <div class="ui-widget-content" style="position:relative; padding:10px; border-top:none;">
            <div style="width:354px; margin-right:5px; overflow:hidden; display: inline-block; box-sizing: border-box;">
                <div class="img_group_preview" title="FSK16 Vorschaubild" href="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$f->field('album_id').'?w=800&cs='.$f->field('checksum').'">
                    <img src="'.API_URL.'/PhotoAlbumPoster/FSK16/'.$f->field('album_id').'?w=200&cs='.$f->field('checksum').'" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                </div>
                <div style="font-size:13px; padding:5px 0; text-align: center; width: inherit;">FSK16 (unter 18 Jahre)</div>
                
                <div id="upload_fsk16">
                    <div class="upload_image_fsk16">
                        <i class="material-symbols-outlined">add</i> <div>Vorschaubild hochladen</div>
                        <form id="form_upload_image_fsk16" action="'.MCP_URL.'/includes/uploader/upload_photo_album_preview.php?fsk=16&album_id='.$album_id.'" method="post" enctype="multipart/form-data">
                            <input type="file" id="upload_image_fsk16" name="album_image" accept="image/x-png,image/jpeg">
                        </form>
                    </div>
                    <div class="abort_upload ui-state-error">Abbrechen</div>

                    <div class="progress">
                        <div class="bar"></div >
                        <div class="percent">0%</div >
                    </div>

                    <div class="status"></div>
                </div>
            </div>
            
            <div style="width:354px; margin-left:5px; overflow:hidden; display: inline-block; box-sizing: border-box;">
                <div class="img_group_preview" title="FSK18 Vorschaubild" href="'.API_URL.'/PhotoAlbumPoster/FSK18/'.$f->field('album_id').'?w=800&cs='.$f->field('checksum').'">
                    <img src="'.API_URL.'/PhotoAlbumPoster/FSK18/'.$f->field('album_id').'?w=200&cs='.$f->field('checksum').'" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                </div>
                <div style="font-size:13px; padding:5px 0; text-align: center;">FSK18 (ab 18 Jahre)</div>
                
                <div id="upload_fsk18">
                    <div class="upload_image_fsk18">
                        <i class="material-symbols-outlined">add</i> <div>Vorschaubild hochladen</div>
                        <form id="form_upload_image_fsk18" action="'.MCP_URL.'/includes/uploader/upload_photo_album_preview.php?fsk=18&album_id='.$album_id.'" method="post" enctype="multipart/form-data">
                            <input type="file" id="upload_image_fsk18" name="album_image" accept="image/x-png,image/jpeg">
                        </form>
                    </div>
                    <div class="abort_upload ui-state-error">Abbrechen</div>

                    <div class="progress">
                        <div class="bar"></div >
                        <div class="percent">0%</div >
                    </div>

                    <div class="status"></div>
                </div>
            </div>
            
            <div class="upload_image_fsk18_error ui-state-error"></div>
            <div class="upload_image_fsk16_error ui-state-error"></div>

            <div class="info_box" id="info_preview">
                <div>Erlaubte Dateiformate: jpg</div>
                <div>Maximale Dateigr&ouml;&szlig;e: 6 MB</div>
                <div>Optimale Fotoaufl&ouml;sung: 16:9</div>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:20px; font-size:15px">Fotos zum Album hinzuf&uuml;gen</div>
        <div class="ui-widget-content" style="position:relative; padding: 10px 0 10px 10px; border-top:none;">
            <div class="info_box" id="info_photos">
                <div>Erlaubte Dateiformate: jpg, png</div>
                <div>Maximale Dateigr&ouml;&szlig;e: 6 MB</div>
                <div>Maximal k&ouml;nnen 200 Bilder gleichzeitig hochgeladen werden.</div>
                <div><b>Verbotenes Material:</b> Klinik-Fetisch</div>
            </div>
            
            <div class="upload_images_error ui-state-error"></div>

            <ul id="photos">
                <li>
                    <div id="upload_photos">
                        <div class="upload_images">
                            <i class="material-symbols-outlined">add</i>
                            <form id="form_upload_images" action="'.MCP_URL.'/includes/uploader/upload_photo_album_images.php?fsk=18&album_id='.$album_id.'" method="post" enctype="multipart/form-data">
                                <input type="file" id="upload_images" multiple name="album_images[]" accept="image/x-png,image/jpeg">
                            </form>
                        </div>
                        <div class="abort_upload ui-state-error"><i class="material-symbols-outlined">clear</i><br />Abbrechen</div>

                        <div class="progress">
                            <div class="bar"></div >
                            <div class="percent">0%</div >
                        </div>

                        <div class="status"></div>
                    </div>
                </li>
                ';
                if($count_photos > 0) {
                    $i=1;
                    while($foto_obj = p4c_fetch_object($rs_photos)) {
                        if ($foto_obj->rejected == '1') {$rejected='style="display:inline-block;"';} else {$rejected='';}
                        $site .= '
                        <li class="ss">
                            <img src="'.MCP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=200" alt="" />
                            <div class="zoom">
                                <span class="material-symbols-outlined zoom_goup" href="'.MCP_URL.'/AlbumPhoto/'.$foto_obj->file_id.'?w=800">zoom_in</span>
                                <div class="delete_picture" data-tooltip="Foto l&ouml;schen" data-photo-id="'.$foto_obj->file_id.'" data-album-id="'.$foto_obj->album_id.'">
                                    <span class="material-symbols-outlined">delete_forever</span>
                                </div>
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
        ';
                        
        $site .= $local_nav;

    } else if (isset($_GET['step']) AND $_GET['step'] == 3 AND isset($_GET['album_id'])) {
        unset($_SESSION['upload_album']);
        $album_id = abs($_GET['album_id']);
        $site .= '
        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:100%;">
                <tr>
                    <td style="width:33.3%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 1<br />
                        <a href="'.MCP_URL.'/Photo-Album-Upload?step=1"><i class="material-symbols-outlined md-40 md-ok">subject</i></a><br />
                        Albuminfos angeben
                    </td>
                    <td style="width:33.4%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 2<br />
                        <a href="'.MCP_URL.'/Photo-Album-Upload?step=2&album_id'.$album_id.'"><i class="material-symbols-outlined md-40 md-ok">cloud_upload</i></a><br />
                        Fotos hochladen
                    </td>
                    <td style="width:33.4%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 3<br />
                        <a href="'.MCP_URL.'/Photo-Album-Upload?step=2&album_id'.$album_id.'"><i class="material-symbols-outlined md-40 md-ok">cloud_upload</i></a><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="ui-widget-content" style="padding:10px 10px 20px 10px; margin-top:10px;">
            <div style="color:#339966; text-align:center;"><i class="material-symbols-outlined md-80">done</i></div>
            <div style="font-size:80px; color:#339966; font-weight:bold; margin-bottom:15px; text-align:center;">Fertig!</div>
            <div style="text-align:center">
                Das Album wird nun gepr&uuml;ft und in K&uuml;rze ver&ouml;ffentlicht.<br />
                <div style="font-size:15px; margin-top:10px;"><a href="'.MCP_URL.'/Photo-Album/'.$album_id.'">Album zum Bearbeiten anzeigen</a></div>
            </div>    
        </div>
        ';
    }
    
    $site .= '
    <div class="album_tips_popup">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="#" class="close_overlay"><b>&#x2715;</b></a>
        </div>';        

        include_once(MCP_DIR.'/includes/overlays/album_tips.php');

        $site .= '
        <div style="text-align:right; float:right;">
            <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
        </div>
    </div>
</div>';

?>