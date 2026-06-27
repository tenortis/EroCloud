<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (isset($_POST['delete_movie'])) {
    
    function delete_directory($dirname, $movie_obj) {
        if(is_dir($dirname)) {
            $dir_handle = opendir($dirname);
        }
        
        //Falls Verzeichnis nicht geoeffnet werden kann, mit Fehlermeldung terminieren
        if(isset($dir_handle)) {
            while($file=readdir($dir_handle)) {
                if($file!="." && $file!="..")  {
                    //Datei loeschen 
                    if(!is_dir($dirname."/".$file)) {
                        @unlink($dirname."/".$file);
                    //Falls es sich um ein Verzeichnis handelt, "delete_directory" aufrufen
                    } else {
                        delete_directory($dirname.'/'.$file, $movie_obj);
                    }
                }
            }

            closedir($dir_handle);
            //Verzeichnis loeschen
            rmdir($dirname);
        }

        $merchant_id = $_SESSION['merchant_id'];

        p4c_query("DELETE FROM `movies` WHERE
            `id`='". p4c_escape_string($movie_obj->id)."' AND
            `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);

        p4c_query("DELETE FROM `movies_online` WHERE
            `id`='". p4c_escape_string($movie_obj->id)."' AND
            `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);        

        return true;
    }
    
    
    $movie_id = abs($_POST['movie_id']);
    
    $rs_check_movie_exists = p4c_query("SELECT * FROM `movies` WHERE `id`='".$movie_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_movie_exists) == 1) {
        $movie_obj = p4c_fetch_object($rs_check_movie_exists);
        
        $dirname = MOVIES_PATH.'/'.$movie_obj->storage_location.'/'.$_SESSION['merchant_id'].'/'.$movie_id;
        
        if (delete_directory($dirname, $movie_obj) === true) {
            header('Location: '.MCP_URL.'/Movies?del=ok');
            exit;
            
        } else {
            $error = "Der Film konnte nicht gel&ouml;scht werden.";
        }
        
    }
}


$movie['title'] = '';
$movie['description'] = '';
$movie['online_at'] = date("Y-m-d H:i", strtotime("+90 minutes"));
$movie['amount_second'] = '0.8';
$movie['amount_webmaster'] = 10;
$movie['as_download'] = 1;
$movie['amount_download'] = 10;
$movie['meta_title'] = '';
$movie['meta_description'] = '';
$movie['seo_url'] = '';
$movie['actor_id'] = '';

$amount_webmaster_ary = array(0, 5, 10, 15, 20, 25);
$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['upload_content'])) {
    
    if(!isset($_POST['title']) OR trim($_POST['title']) == '') {
        $error = 'Gib einen aussagekr&auml;ftigen Filmtitel an.';
    } else {
        $movie['title'] = trim(str_replace($replace_title_ary, '', $_POST['title']));
        if (empty($movie['title'])) {
            $error = 'Gib einen aussagekr&auml;ftigen Filmtitel an.';    
        } 
    }

    // Entfernt alle "ZERO WIDTH SPACE"-Varianten. 
    $zero_width_spaces_ary = ["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBF\xBD", "\u200B", "\u200C", "\u200D"];

    $movie['title'] = str_replace($zero_width_spaces_ary, "", $movie['title']);
    $movie['title'] = str_replace(["#?", "?en"], ["#", "en"], $movie['title']);
    
    // Euro-Zeichen in Text umwandeln
    $movie['title'] = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $movie['title']);

    
    if (strlen(utf8_decode($movie['title'])) > 65) {
        $error = 'Der Filmtitel ist leider zu lang.';
    }
    
    if(!isset($_POST['description'])) {
        $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung des Films an.';
    } else {
        $allowed_tags = '<ul><ol><li><u><em><strong><h1 class="h4"><h2><h3><h4><h5><h6><pre><address><p>';
        $movie['description'] = trim(strip_tags($_POST['description'], $allowed_tags));
        if (empty($movie['description'])) {
            $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung des Films an.';    
        }
    }
    
    // Entfernt alle "ZERO WIDTH SPACE"-Varianten. 
    $movie['description'] = str_replace($zero_width_spaces_ary, "", $movie['description']);
    $movie['description'] = str_replace(["#?", "?en"], ["#", "en"], $movie['description']);

    
    if(isset($_POST['actor_id'])) {
        $movie['actor_id'] = abs($_POST['actor_id']);
    }
    
    if ($movie['actor_id'] <= 0) {
        $error = 'Bitte zun&auml;chst eine Profil anlegen.';
    }
    
    if(isset($_POST['online_at'])) {
        $movie['online_at'] = date("Y-m-d H:i", strtotime($_POST['online_at']));
    }

    if(!isset($_POST['amount_second'])) {
        $error = 'Bitte gib an, wieviel der Film kosten soll.';
    } else {
        $movie['amount_second'] = number_format($_POST['amount_second'], 1, '.', '');
    }

    if(!isset($_POST['visible_for_website'])) {
        $movie['visible_for_website'] = 'public';
    } else {
        $movie['visible_for_website'] = trim(strip_tags($_POST['visible_for_website']));
        
        if ($movie['visible_for_website'] != 'public') {
            // Wenn Website nicht existiert
            $rs_websites = p4c_query("SELECT * FROM `sites` WHERE
                `partner_id`='". p4c_escape_string($merchant->partner_id())."' AND
                `domain`='". p4c_escape_string($movie['visible_for_website'])."' AND
                `status`='1'
            LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_websites) == 0) {
                $movie['visible_for_website'] = 'public';
            }
        }        
    }

    
    /*
    if(isset($_POST['amount_webmaster'])) {
        $amount_webmaster = abs($_POST['amount_webmaster']);
        if (in_array($amount_webmaster, $amount_webmaster_ary)) {
            $movie['amount_webmaster'] = $amount_webmaster;
        }
    }
    */
    
    if(isset($_POST['as_download'])) {
        $movie['as_download'] = abs($_POST['as_download']);
        if ($movie['as_download'] == 0) {
            $movie['as_download'] = 0;
        } else {
            $movie['as_download'] = 1;
        }
    }

    if(isset($_POST['amount_download'])) {
        $amount_download = abs($_POST['amount_download']);
        if ($amount_download <= 150 AND $amount_download >= 0) {
            $movie['amount_download'] = $amount_download;
        }
    }

    $movie['meta_title'] = substr($_POST['title'], 0, 65);
    
    $movie['seo_url'] = seo_url($movie['title']);

    if(!isset($_POST['meta_description']) OR trim($_POST['meta_description']) == '') {
        $movie['meta_description'] = substr(trim(strip_tags($movie['description'])), 0, 156);
    } else {
        $movie['meta_description'] = substr(trim(strip_tags($_POST['meta_description'])), 0, 156);
        if (empty($movie['meta_description'])) {$movie['meta_description'] = substr(trim(strip_tags($movie['description'])), 0, 156);}
    }

    $movie_id = '';
    if (isset($_SESSION['upload_movie']['movie_id'])) {
        $movie_id = abs($_SESSION['upload_movie']['movie_id']);
    }
    
    // Prüfe ob bei diesem Kunden bereichts ein Film mit diesem Title existiert
    $rs_check_movie_exists = p4c_query("SELECT `id` FROM `movies` WHERE `title`='".p4c_escape_string($movie['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`!='".abs($movie_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_movie_exists) == 1) {
        $error = 'Du hast bereits einen Film mit dem Titel hochgeladen.';
        $duplicate_title = p4c_result($rs_check_movie_exists, 0);
        
    }

    // Prüfe ob bei diesem Kunden exakt dieser Film schon existiert -> dann updaten nicht neu anlegen
    $rs_check_movie_exists = p4c_query("SELECT `id`  FROM `movies` WHERE `title`='".p4c_escape_string($movie['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($movie_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_movie_exists) == 1) {
        $movie_exists = true;  
    }
    
    $_SESSION['upload_movie'] = $movie;
    $_SESSION['upload_movie']['movie_id'] = $movie_id;
    
    /*
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
    */
    if (!isset($error) OR empty($error)) {
        
        if (isset($movie_exists) AND $movie_exists === true) {
            if (p4c_query("UPDATE `movies` SET
                `actor_id` = '".abs($movie['actor_id'])."',
                `title` = '".p4c_escape_string($movie['title'])."',
                `description` = '".p4c_escape_string($movie['description'])."',
                `meta_title` = '".p4c_escape_string($movie['meta_title'])."',
                `meta_description` = '".p4c_escape_string($movie['meta_description'])."',
                `seo_url` = '".p4c_escape_string($movie['seo_url'])."',
                `online_at` = '".p4c_escape_string($movie['online_at'])."',
                `amount_second` = '".abs($movie['amount_second'])."',
                `amount_webmaster` = '".abs($movie['amount_webmaster'])."',
                `as_download` = '".abs($movie['as_download'])."',
                `amount_download` = '".abs($movie['amount_download'])."',
                `visible_for_website`= '".p4c_escape_string($movie['visible_for_website'])."'
                WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__)) {

                $_SESSION['upload_movie']['movie_id'] = $movie_id;

                header('Location: '.MCP_URL.'/Movie-Upload?step=2&movie_id='.$movie_id);
                exit;

            } else {
                $error = 'Der Film konnte nicht gespeichert werden!';
            }
            
        } else {
            $file_id = md5($_SESSION['merchant_id'].time());
                
            if (p4c_query("INSERT INTO `movies` SET
                `actor_id` = '".abs($movie['actor_id'])."',
                `file_id` = '".p4c_escape_string($file_id)."',
                `merchant_id` = '".abs($_SESSION['merchant_id'])."',
                `storage_location` = '".MOVIES_DEFAULT_DIR."',
                `title` = '".p4c_escape_string($movie['title'])."',
                `description` = '".p4c_escape_string($movie['description'])."',
                `meta_title` = '".p4c_escape_string($movie['meta_title'])."',
                `meta_description` = '".p4c_escape_string($movie['meta_description'])."',
                `seo_url` = '".p4c_escape_string($movie['seo_url'])."',
                `online_at` = '".p4c_escape_string($movie['online_at'])."',
                `amount_second` = '".abs($movie['amount_second'])."',
                `amount_webmaster` = '".abs($movie['amount_webmaster'])."',
                `as_download` = '".abs($movie['as_download'])."',
                `amount_download` = '".abs($movie['amount_download'])."',
                `visible_for_website`= '".p4c_escape_string($movie['visible_for_website'])."';",__FILE__,__LINE__)) {

                $movie_id = p4c_insert_id();

                $_SESSION['upload_movie']['movie_id'] = $movie_id;

                header('Location: '.MCP_URL.'/Movie-Upload?step=2&movie_id='.$movie_id);
                exit;

            } else {
                $error = 'Der Film konnte nicht gespeichert werden!';
            }
            
        }

    }
    
} else if (isset($_SESSION['upload_movie'])) {
    $movie = $_SESSION['upload_movie'];  
    $movie_id = $_SESSION['upload_movie']['movie_id'];
}

$site .= '
<link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/uploadfile.css" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.uploadfile.min.js"></script>

<link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
<script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.1.4/l10n/de.js"></script>

<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>

<style type="text/css">
<!--
    input.button.ui-widget {font-size:16px !important;}
-->
</style>

<div style="width:600px;">
    <h1 class="h4">Film in die EroCloud hochladen</h1>
    ';

    if (!isset($_GET['step']) OR (isset($_GET['step']) AND $_GET['step'] == 1)) {
    
        $site .= '    
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
                
                jQuery("#visible_for_website").change(function(){
                    if (jQuery("#visible_for_website").val() == "public") {
                        jQuery("#amount_second").show().attr("name", "amount_second");
                        jQuery("#amount_second_webmaster").hide().attr("name", "");
                    } else {
                        jQuery("#amount_second").hide().attr("name", "");
                        jQuery("#amount_second_webmaster").show().attr("name", "amount_second");
                    }
                })
                
            })
            
        // ]]>
        </script>
    
        
        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:600px">
                <tr>
                    <td style="width:25%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                        Schritt 1<br />
                        <i class="material-symbols-outlined md-40 md-ok">subject</i><br />
                        Filminfos angeben
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40">cloud_upload</i><br />
                        Film hochladen
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40">settings</i><br />
                        Film konvertieren
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px;">
                        Schritt 4<br />
                        <i class="material-symbols-outlined md-40">cloud_done</i><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>
		
        <div style="margin:10px 0 5px 0;">
            <div style="width:50%; display:inline-block; text-align:center;">
                <a class="movie_tips" href="javascript:;">Hinweise zur Qualit&auml;t deiner Filme</a>
            </div>
            <div style="width:49%; display:inline-block; text-align:center;">
                <a class="movie_rendering_tips" href="javascript:;">So renderst du deine Filme richtig.</a>
            </div>
        </div>
        
        <div class="movie_tips_popup">
            <div style="text-align:right; top:20px; right:10px; position:absolute;">
                <a href="#" class="close_overlay"><b>&#x2715;</b></a>
            </div>';        
                include_once(MCP_DIR.'/includes/overlays/movie_tips.php');
                $site .= '
            <div style="text-align:right; float:right;">
                <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
            </div>
        </div>
        
        <div class="movie_rendering_tips_popup">
            <div style="text-align:right; top:20px; right:10px; position:absolute;">
                <a href="#" class="close_overlay"><b>&#x2715;</b></a>
            </div>';        
            include_once(MCP_DIR.'/includes/overlays/movie_rendering_tips.php');
            $site .= '
            <div style="text-align:right; float:right;">
                <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
            </div>
        </div>

        ';
    
        if (isset($error) AND !empty($error)) {
            $site .= '<div class="ui-state-error" style="padding:10px; margin-top:10px;">'.$error.'</div>';
            
            if (isset($duplicate_title)) {
                $rs_dublicate_movie = p4c_query("SELECT * FROM `movies` WHERE `id`='".abs($duplicate_title)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
                if (p4c_num_rows($rs_dublicate_movie) == 1) {
                    $dublicat_ary = p4c_fetch_object($rs_dublicate_movie);
                    $site .= '
                    <div class="ui-widget-header" style="border-top:none; border-bottom:none; padding:5px 10px">'.$dublicat_ary->title.'</div>
                    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
                        <table style="width:100%;">
                            <tr>
                                <td style="width:110px; vertical-align:top;">
                                    <img src="'.MCP_URL.'/PlayerPoster/'.$dublicat_ary->file_id.'&w=100" style="width:100px; height:auto;" />
                                </td>
                                <td style="vertical-align:middle;">
                                    <a href="'.MCP_URL.'/video/'.$dublicat_ary->id.'" target="_blank">Klicke hier um zum Film zu wechseln.</a>
                                    <div style="margin:10px 0;"><b>ODER</b></div>
                                    Wenn dieser Film tats&auml;chlich ein anderer ist, benutze hier einen neuen Titel.
                                </td>
                            </tr>
                        </table>
                    </div>';
                }
            }
            
        }
        $site .= '
        <form action="" method="post">
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Angaben zum Film</div>
            <div class="ui-widget-content" style="padding:10px; border-top:none;">
                <div class="edit_title">Gib einen aussagekr&auml;ftigen Filmtitel an. <span id="anzahl_title">(max. 65 Zeichen)</span></div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="title" value="'.$movie['title'].'" onkeyup="jQuery(this).zaehle_zeichen(65, \'anzahl_title\')" placeholder="Gib einen aussagekr&auml;ftigen Filmtitel an." style="font-size:18px;" />
                </div>
    
                <div class="edit_title">Gib eine gute und aussagekr&auml;ftige <b>Beschreibung</b> des Films an.</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <textarea name="description" id="description" >'.$movie['description'].'</textarea>
                    <script>
                        CKEDITOR.replace("description", {customConfig: "'.MCP_URL.'/fw/ckeditor/movie_upload_config.js"});
                    </script>
                </div>
    
                <div class="edit_title">Ab wann soll der Film fr&uuml;hsten ver&ouml;ffentlicht werden?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input class="flatpickr" name="online_at" type="text" value="'.$movie['online_at'].'" />
                </div>
                
                <div class="edit_title">Wieviel soll der Film kosten?</div>
                <div class="edit_content" style="margin-bottom:8px; font-size: 16px;">
                    <select id="amount_second" name="amount_second" style="width:170px;">';
            		$i=0.0;
            		while($i<=30.1) {
                            if (strlen($i)<=2) {$i=$i.'.0';}
                            if (strval($i) == strval($movie['amount_second'])) {
                                $selected = 'selected="selected"';
                            } else {
                                $selected = '';
                            }
                            
                            $text = $i;
                            if (strval($i) == '0.0') {$text = 'kostenlos';}
                            if (strval($i) == '0.8') {$text = $i.' (Empfohlen)';}
                        
                            $site .= '<option '.$selected.' value="'.$i.'">'.$text.'</option>';
                            $i = $i+0.1;
            		}
            		unset($i);
                        $site .= '
                    </select>
                    
                    <select id="amount_second_webmaster" name="" style="width:170px; display:none;">';
            		$i=0.0;
            		while($i<=100.0) {
                            if (strlen($i)<=2) {$i=$i.'.0';}
                            if (strval($i) == strval($movie['amount_second'])) {
                                $selected = 'selected="selected"';
                            } else {
                                $selected = '';
                            }
                        
                            $text = $i;
                            if (strval($i) == '0.0') {$text = 'kostenlos';}
                            if (strval($i) == '0.8') {$text = $i.' (Empfohlen)';}
                        
                            $site .= '<option '.$selected.' value="'.$i.'">'.$text.'</option>';
                            $i = $i+0.1;
            		}
            		unset($i);
                        $site .= '
                    </select> Cent je Sekunde<br />
                    <span style="font-size:10px;">Dies ist der Preis f&uuml;r Streaming (zum online anschauen). Den exakten Preis bekommst du auf der n&auml;chsten Seite angezeigt.</span>
                </div>
            </div>
            <div class="ui-widget-header" style="padding:5px 10px; border-top:none;">weitere Einstellungen</div>
            <div class="ui-widget-content" style="padding:10px; border-top:none;">
                
                <div class="edit_title">Welchem Darsteller soll der Film zugeordnet werden?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <select name="actor_id">';
                        $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`='active' ORDER BY `username` ASC",__FILE__,__LINE__);
                        if (p4c_num_rows($rs_actors) > 0) {
                            while($actor_obj = p4c_fetch_object($rs_actors)) {
                                if ($movie['actor_id'] == $actor_obj->id) {$selected = 'selected';} else {$selected = '';}
                                $site .= '<option value="'.$actor_obj->id.'" '.$selected.'> '.$actor_obj->username.'</option>';
                            }
                        } else {
                            $site .= '<option value="0">Bitte zun&auml;chst Profil anlegen!</option>';
                        }
                        $site .= ' 
                    </select>
                </div>

                <div class="edit_title">Auf welcher Website soll der Film ver&ouml;ffentlicht werden?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <select id="visible_for_website" name="visible_for_website">
                        <option value="public"> alle Partnerwebsites</option>';
                        $rs_websites = p4c_query("SELECT * FROM `sites` WHERE `partner_id`='". p4c_escape_string($merchant->partner_id())."' AND `status`='1' ORDER BY `domain` ASC;",__FILE__,__LINE__);
                        if (p4c_num_rows($rs_websites) > 0) {
                            while($site_obj = p4c_fetch_object($rs_websites)) {
                                $site .= '<option value="'.$site_obj->domain.'"> '.$site_obj->domain.'</option>';
                            }
                        }
                        $site .= '
                    </select><br />
                    <span style="font-size:10px;">Bei der Ver&ouml;ffentlichung auf Partnerwebsites, erh&auml;lst du 25% Provision vom Umsatz dieses Filmes.</span>
                </div>

                <!--
                <div class="edit_title">F&uuml;r wieviel Prozent mehr, soll der Film auf unseren Partnerseiten angeboten werden?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <select name="amount_webmaster">';
                        foreach($amount_webmaster_ary as $percent) {
                            
                            if ($percent == $movie['amount_webmaster']) {
                                    $selected = 'selected="selected"';
                            } else {
                                    $selected = '';
                            }
                            
                            $text = $percent.'%';
                            if ($percent == 0) {
                                $text = $percent.'% (selber Preis wie bei mir)';
                            } else {
                                $text = '+'.$percent.'%';
                            }                                    
                            
                            $site .= '<option '.$selected.' value="'.$percent.'"> '.$text.'</option>';
                        }
                    $site .= '
                    </select>
                    <span style="font-size:10px;">Dies ist der Streaming-Preis f&uuml;r Kunden auf Partnerseiten.</span>
                </div>
                //-->
                
                <div class="edit_title">Darf der Film zum Download angeboten werden?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <select name="as_download">';
                        if ($movie['as_download'] == 0) {$selected = 'selected';} else {$selected = '';}
                        $site .= ' 
                        <option value="1"> Ja</option>
                        <option value="0" '.$selected.'> Nein</option>
                    </select><br />
                    <span style="font-size:10px;">Empfohlen! Dies steigert Ihren Umsatz.</span>
                </div>
                
                <div class="edit_title">F&uuml;r wieviel Prozent mehr, m&ouml;chtest du den Film als Download anbieten?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <select name="amount_download">';
            		for($i=0;$i<=150;$i++) {
            
                            if (($movie['amount_download'] == $i)) {
                                    $selected = 'selected="selected"';
                            } else {
                                    $selected = '';
                            }

                            $text = '';
                            if (strval($i) == '0') {$text = '(genau so teuer wie )';}
                            if (strval($i) == '10') {$text = '(Empfohlen)';}                    

                            $site .= '<option '.$selected.' value="'.$i.'">+'.$i.'&percnt; '.$text.'</option>';
            		}
            		unset($i);
                    $site .= '
            		</select>
                </div>
    
            </div>
            
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Suchmaschinenoptimierung (SEO)</div>
            <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:10px;">
                <div class="edit_title">Meta Description <span id="anzahl_meta_description">(max. 165 Zeichen)</span> <b><span style="color:#ff0000;">KEINE einzelnen Worte/Keywords oder Hashtags!</span></b></div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <textarea type="text" name="meta_description" placeholder="Gib hier eine kurze aussagekr&auml;ftige Beschreibung des Films an. Keine Stichworte! Diese Kurzbeschreibung wird unteranderem f&uuml;r die Google-Suche benutzt." onkeyup="jQuery(this).zaehle_zeichen(156, \'anzahl_meta_description\')">'.$movie['meta_description'].'</textarea>
                    <span style="font-size:10px;">Beschreibe den Film so interessant wie m&ouml;glich mit maximal 156 Zeichen.</span>
                </div>
                
                <div class="edit_title">Meta Title <span id="anzahl_meta_title">(max. 65 Zeichen)</span></div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="meta_title" value="'.$movie['meta_title'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" />
                </div>
               
                <div class="edit_title">SEO-URL (URL-Name)</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="seo_url" value="'.$movie['seo_url'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" />
                </div>
            </div>      

            <div style="margin-top:15px; margin-bottom:30px; text-align:right;">
                <table style="width:100%;">
                    <tr>
                        <td style="width:50%; text-align:left;">';
                            if (isset($_SESSION['upload_movie']['movie_id'])) {
                                $site .= '                                 
                                <input type="hidden" name="movie_id" value="'.$movie_id.'" />
                                <input type="submit" class="content_ruels button" name="delete_movie" value="Film l&ouml;schen" />
                                ';
                            }
                            $site .= '
                        </td>

                        <td style="width:50%">
                            <input type="button" class="content_ruels button" value="Speichern und weiter mit Schritt 2" />
                        </td>
                    </tr>
                </table>
            </div>';

            include_once(MCP_DIR.'/includes/overlays/content_rules.php');
                    
            $site .= '
        </form>
        ';
    } else if (isset($_GET['step']) AND $_GET['step'] == 2 AND isset($_GET['movie_id'])) {
        $movie_id = abs($_GET['movie_id']);
        
        // Prüfe ob dieser Film existiert
        $rs_check_movie_exists = p4c_query("SELECT `id`  FROM `movies` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($movie_id)."' LIMIT 1;",__FILE__,__LINE__);
       
        if (p4c_num_rows($rs_check_movie_exists) == 0) {
            header('Location: '.MCP_URL.'/Movies');
            exit;
        }
        
        $m = new Movie($mysql,$movie_id);

        if ($m->field('id') == '') {
            header('Location: '.MCP_URL.'/Movies');
            exit;
        }
        
        // Prüfen ob dieser Film noch nicht veröffentlicht wurde.
        $rs_check_movie_online_exists = p4c_query("SELECT `id`  FROM `movies_online` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `file_id`='". p4c_escape_string($m->field('file_id'))."' LIMIT 1;",__FILE__,__LINE__);        
        if (p4c_num_rows($rs_check_movie_online_exists) == 1) {
            header('Location: '.MCP_URL.'/Movies');
            exit;
        }
        
        $_SESSION['upload_movie']['movie_id'] = $movie_id;

        $site .= '
        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:100%;">
                <tr>
                    <td style="width:25%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 1<br />
                        <a href="?step=1"><i class="material-symbols-outlined md-40 md-ok">subject</i></a><br />
                        Filminfos angeben
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px; color:#FF9900; font-weight:bold;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40 md-progress">cloud_upload</i><br />
                        Film hochladen
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40">settings</i><br />
                        Film konvertieren
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px;">
                        Schritt 4<br />
                        <i class="material-symbols-outlined md-40">cloud_done</i><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>

        <style>

        #upload_file {position: absolute; cursor: pointer; top: 0px; width: 100%; height: 100%; left: 0px; z-index: 100; opacity: 0;}

        .abort_upload {
            position: relative;
            display:none;
            width:120px;
            padding:5px 10px;
            cursor:pointer !important;
            font-size: 14px !important;
            text-align:center;
            float:left;
        }

        .upload_movie {
            position: relative;
            background-color:#2f8ab9;
            color:#fff;
            width:120px;
            padding:5px 10px;
            cursor:pointer !important;
            font-size: 14px !important;
            font-weight: bold;
            text-align:center;
        }

        .upload_error {display:none; padding:5px; margin-top:5px;}

        .progress {
            border-top: 1px solid #008000;
            border-right: 1px solid #008000;
            border-bottom: 1px solid #008000;
            position:relative;
            margin-left:142px;
            display:none;
            width:auto;
            box-sizing:border-box;
        }

        .bar { background-color: #B4F5B4; width:0%; height:26px; border-radius: 3px; }
        .percent { position:absolute; display:inline-block; top:5px; left:48%; }
        
        .info_box {
            margin-top:0;
            border-top:none;
        }
        
        .info_box li,
        .info_box div {
            font-size:12px;
        }
        
        </style>


        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">So renderst du deine Filme richtig</div>
        <div class="info_box">
            <div style="margin-bottom:10px; line-height:1.4">
                Immer wenn ein Film fertig gestellt wurde, kommt die Frage nach den richtigen Einstellungen zum Rendern.
                Die optimale Render-Einstellung gibt es leider nicht. Mit den folgenden Settings sollte es dir aber gelingen einen Film vern&uuml;nfig in mp4 zu Rendern.
            </div>

            <div style="margin-bottom:5px;">
                <ul style="list-style-type:square; margin-left:40px; line-height:1.4">
                    <li>Video-Codec: AVC / H.264</li>
                    <li>Audio-Codec: AAC</li>
                    <li>Aufl&uuml;sung: 1920x1080 (FullHD / 1080p)</li>
                    <li>Profil: Hoch</li>
                    <li>Framerate: 25,000 (PAL)</li>
                    <li>Keine Halbbilder: Progressive Scan</li>
                    <li>Variable Bitrate, 10.000.000 Bit/s</li>
                    <li>Audio: 192kBit/s</li>
                </ul>
            </div>
            
            <a class="movie_tips" href="javascript:;">Beachte bitte auch die allgemeinen Hinweise zur Qualit&auml;t deiner Filme.</a>
            
            <div class="movie_tips_popup">
                <div style="text-align:right; top:20px; right:10px; position:absolute;">
                    <a href="#" class="close_overlay"><b>&#x2715;</b></a>
                </div>';        
                include_once(MCP_DIR.'/includes/overlays/movie_tips.php');
                $site .= '
                <div style="text-align:right; float:right;">
                    <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
                </div>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Film hochladen</div>
        <div class="ui-widget-content" style="position:relative; padding:10px; border-top:none;">

            <div style="margin-bottom:8px;">
                Nach dem Upload kannst du den Film noch einmal bearbeiten und ein Vorschaubild ausw&auml;hlen oder ein eigenes Vorschaubild hochladen.
            </div>

            <div style="margin-bottom:8px;">
                <input style="position:relative; vertical-align:middle;" type="checkbox" id="upload_movie_released" /> 
                <label for="upload_movie_released"><b>ODER:</b> Den Film direkt nach dem Upload zur Pr&uuml;fung freigeben und in der EroCloud ver&ouml;ffentlichen.</label>
            </div>

            <div style="margin-bottom:15px;">
                Erlaubte Dateiformate: avi, flv, m4v, mkv, mov, mp4, mpg, wmv<br />
                Maximale Dateigr&ouml;&szlig;e: 2000 MB (2,0 GB)
            </div>

            <div class="upload_movie">
                Film hochladen
                <form id="form_upload_movie" action="'.MCP_URL.'/includes/uploader/upload_movie.php?movie_id='.$movie_id.'" method="post" enctype="multipart/form-data">
                    <input type="file" id="upload_file" name="movie" accept="video/*">
                </form>
            </div>
            <div class="abort_upload ui-state-error">Abbrechen</div>

            <div class="progress">
                <div class="bar"></div >
                <div class="percent">0%</div >
            </div>

            <div class="upload_error ui-state-error"></div>
            <div id="status"></div>

        </div>
        
        <div style="margin-top:15px; margin-bottom:30px; text-align:right;">
            <form action="'.MCP_URL.'/Movie-Upload?step=1" method="post">
                <input type="hidden" name="movie_id" value="'.$movie_id.'" />
                <input type="submit" class="content_ruels button" name="delete_movie" value="Film l&ouml;schen" />
            </form>
        </div>

        <script type="text/javascript">
            // <![CDATA[
            jQuery(document).ready(function() {
                var bar = jQuery(".bar");
                var percent = jQuery(".percent");
                var status = jQuery("#status");
                var progress = jQuery(".progress");
                var abort = jQuery(".abort_upload");
                var upload = jQuery(".upload_movie");
                var movie_released = jQuery("#upload_movie_released");
                
                var percent_total = 1;

                jQuery("#form_upload_movie").ajaxForm({
                    clearForm: true,
                    resetForm: true,
                    forceSync: true,
                    dataType:  "json",
                    data: {released: movie_released.is(":checked")},
                    beforeSend: function(xhr) {
                        jQuery(".upload_error").hide().html("");
                        progress.show();
                        upload.hide();
                        abort.show();
                        movie_released.attr("disabled", true);
                        abort.click(function () {
                            xhr.abort();
                            jQuery("#upload_file").val("");
                            upload.show();
                            abort.hide();
                            movie_released.attr("disabled", false);
                            progress.hide();
                        });

                        status.empty();
                        var percentVal = "0%";
                        bar.width(percentVal)
                        percent.html(percentVal);
                    },
                    uploadProgress: function(event, position, total, percentComplete) {
                        var percentVal = percentComplete + "%";
                        bar.width(percentVal)
                        percent.html(percentVal);
                        
                        if (percentComplete > percent_total) {
                            percent_total = percentComplete;
                            // set logged in status
                            jQuery.get("'.MCP_URL.'/Ajax/set_loggedin_status.php");
                        }
                    },
                    success: function(data) {
                        var percentVal = "100%";
                        bar.width(percentVal)
                        percent.html(percentVal);
                        // If error
                        if (data["jquery-upload-file-error"]) {
                            var error = data["jquery-upload-file-error"];
                            jQuery(".upload_error").show().html(error);

                            jQuery(".upload_file").val("");
                            upload.show();
                            abort.hide();
                            movie_released.attr("disabled", false);
                            progress.hide();

                        } else {
                            console.log("1");
                            window.location.href="'.MCP_URL.'/Movie-Upload?step=3&movie_id='.$movie_id.'";
                        }
                    },
                    complete: function(data) {

                    }
                }); 


                var s = jQuery.extend({
                    allowedTypes: "mp4,avi,flv,m4v,mkv,mov,mp4,mpg,wmv",
                    maxFileSize: 4294967296
                    // maxFileSize: 3221225472, // 3000 MB
                    // maxFileSize: 2097152000, // 2000 MB                    
                });

                function uploadMovie(f) {

                var file = f.files[0],
                    fileName = file.name,
                    fileSize = file.size;

                    if(!isFileTypeAllowed(s, fileName)) {
                        error = "Es sind nur "+s.allowedTypes+" erlaubt.";
                        return false;
                    }

                    if(fileSize > s.maxFileSize) {
                        error = "Datei zu gro&ouml;";
                        return false;
                    }
                    return true;
                };

                function isFileTypeAllowed(s, fileName) {
                    var fileExtensions = s.allowedTypes.toLowerCase().split(/[\s,]+/g);
                    var ext = fileName.split(".").pop().toLowerCase();
                    if(s.allowedTypes != "*" && jQuery.inArray(ext, fileExtensions) < 0) {
                        return false;
                    }
                    return true;
                }

                jQuery("#upload_file").on("change", function(){
                    if (!uploadMovie(this)) {
                        jQuery(".upload_error").show().html(error);
                    } else {
                        jQuery("#form_upload_movie").trigger("submit");
                    }
                });
            })
        // ]]>
        </script>
        
        ';
        
    } else if (isset($_GET['step']) AND $_GET['step'] == 3 AND isset($_GET['movie_id'])) {
        unset($_SESSION['upload_movie']);
        
        $movie_id = abs($_GET['movie_id']);
        
        $site .= '
        <div class="ui-widget-content" style="padding:10px 0;">
            <table style="width:100%;">
                <tr>
                    <td style="width:25%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 1<br />
                        <i class="material-symbols-outlined md-40 md-ok">subject</i><br />
                        Filminfos angeben
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px; color:#008000; font-weight:bold;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40 md-ok">cloud_upload</i><br />
                        Film hochladen
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px; color:#FF9900; font-weight:bold;">
                        Schritt 3<br />
                        <i class="material-symbols-outlined md-40 md-progress">settings</i><br />
                        Film konvertieren
                    </td>
                    <td style="width:25%; text-align:center; font-size:15px;">
                        Schritt 4<br />
                        <i class="material-symbols-outlined md-40">cloud_done</i><br />
                        ver&ouml;ffentlichen
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="ui-widget-content" style="padding:10px 10px 20px 10px; margin-top:10px;">
            <div style="color:#339966; text-align:center;"><i class="material-symbols-outlined md-80">done</i></div>
            <div style="font-size:80px; color:#339966; font-weight:bold; margin-bottom:15px; text-align:center;">Fertig!</div>
            <div style="text-align:center">
                Der Film wird in K&uuml;rze konvertiert.<br />
                <div style="font-size:15px; margin-top:10px;"><a href="'.MCP_URL.'/video/'.$movie_id.'">Film zum Bearbeiten anzeigen</a></div>
            </div>    
        </div>
        ';
    }
    
    $site .= '
</div>';

?>