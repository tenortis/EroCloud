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
        if($dir_handle) {
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


if (!isset($_GET['id'])) {exit;}

$movie_id = abs($_GET['id']);

$m = new Movie($mysql,$movie_id);

if ($m->field('id') == '') {
    header('Location: '.MCP_URL.'/Movies');
    exit;
}

if ($m->field('status') == 'deleted') {
    header('Location: '.MCP_URL.'/Movies');
    exit;
}

$movie['merchant_id'] = $m->field('merchant_id');
$movie['fsk16'] = $m->field('preview_image_fsk16');
$movie['fsk18'] = $m->field('preview_image_fsk18');
$movie['title'] = $m->field('title');
$movie['description'] = $m->field('description');
$movie['online_at'] = date("Y-m-d H:i", strtotime($m->field('online_at')));
$movie['playtime_seconds'] = $m->field('playtime_seconds');
$movie['amount_second'] = $m->field('amount_second');
$movie['amount_own'] = $m->field('amount_own');
$movie['amount_webmaster'] = $m->field('amount_webmaster');
$movie['as_download'] = $m->field('as_download');
$movie['amount_download'] = $m->field('amount_download');
$movie['meta_title'] = $m->field('meta_title');
$movie['meta_description'] = $m->field('meta_description');
$movie['seo_url'] = $m->field('seo_url');
$movie['actor_id'] = $m->field('actor_id');
$movie['visible_for_website'] = $m->field('visible_for_website');

if ($movie['visible_for_website'] == 'public') {
    $movie['visible_for_website'] = 'alle Partnerwebsites';
}

$streaming_preis = round($movie['playtime_seconds'] * $movie['amount_second']);

if ($m->field('filename') == '') {
    $_SESSION['upload_movie'] = $movie;
    $_SESSION['upload_movie']['movie_id'] = $movie_id;   
     header('Location: '.MCP_URL.'/Movie-Upload?step=2&movie_id='.$movie_id);
     exit;
}

$rs_movie_online = p4c_query("SELECT * FROM `movies_online` WHERE `file_id`='".p4c_escape_string($m->field('file_id'))."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
$count_movie_online = p4c_num_rows($rs_movie_online);

$amount_webmaster_ary = array(0, 5, 10, 15, 20, 25);
$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['edit_movie']) OR isset($_POST['save_movie'])) {

    if(!isset($_POST['title'])) {
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
    
    // Prüfe ob bei diesem Kunden bereichts ein Film mit diesem Title existiert
    $rs_check_movie_exists = p4c_query("SELECT `id`  FROM `movies` WHERE `title`='".p4c_escape_string($movie['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`!='".abs($movie_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_movie_exists) == 1) {
        $error = 'Du hast bereits einen Film mit dem Titel hochgeladen.';
        $duplicate_title = p4c_result($rs_check_movie_exists, 0);
        
    } else {

        if(!isset($_POST['description'])) {
            $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung des Films an.';
        } else {
            $allowed_tags = '<ul><ol><li><u><em><strong><h1 class="h4"><h2><h3><h4><h5><h6><pre><address><p>';
            $movie['description'] = trim(strip_tags($_POST['description'], $allowed_tags));
            if (empty($movie['description'])) {
                $error = 'Gib eine gute und aussagekr&auml;ftige Beschreibung des Films an.';    
            }
        }
        
        $movie['description'] = str_replace($zero_width_spaces_ary, "", $movie['description']);
        $movie['description'] = str_replace(["#?", "?en"], ["#", "en"], $movie['description']);

        
        if(isset($_POST['actor_id'])) {
            $movie['actor_id'] = abs($_POST['actor_id']);
        }
        
        if($movie['actor_id'] == 0) {
            $error = 'Bitte w&auml;hle ein Darstellerprofil aus.';
        }
        
        if(isset($_POST['online_at'])) {
            $movie['online_at'] = date("Y-m-d H:i", strtotime($_POST['online_at']));
        }

        if(!isset($_POST['amount_second'])) {
            $error = 'Gib an, wieviel der Film auf Ihrer eigenen Webseite kosten soll.';
        } else {
            $movie['amount_second'] = number_format($_POST['amount_second'], 1, '.', '');
        }

        // Wenn Video konvwertiert wurde
        if ($m->field('convert_status') > 1) {
            if (!isset($_POST['fsk18']) OR empty($_POST['fsk18'])) {
                $error = 'Bitte w&auml;hle ein FSK18 Vorschaubild aus.';
            } else {
                $movie['fsk18'] = abs($_POST['fsk18']);
            }

            if (!isset($_POST['fsk16']) OR empty($_POST['fsk16'])) {
                $error = 'Bitte w&auml;hle ein FSK16 Vorschaubild aus.';
            } else {
                $movie['fsk16'] = abs($_POST['fsk16']);
            }
        } else {
            $movie['fsk16'] = '';
            $movie['fsk18'] = '';   
        }

        if(isset($_POST['amount_webmaster'])) {
            $amount_webmaster = abs($_POST['amount_webmaster']);
            if (in_array($amount_webmaster, $amount_webmaster_ary)) {
                $movie['amount_webmaster'] = $amount_webmaster;
            }
        }

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

        if(!isset($_POST['meta_title'])) {
            $movie['meta_title'] = substr(trim($movie['title']), 0, 65);
        } else {
            $movie['meta_title'] = substr(trim(str_replace($replace_title_ary, '', $_POST['meta_title'])), 0, 65);
            $movie['meta_title'] = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $movie['meta_title']);
            $movie['meta_title'] = str_replace(array("\n", "\r"), '', $movie['meta_title']);
            if (empty($movie['meta_title'])) {$movie['meta_title'] = substr(trim($movie['title']), 0, 65);}   
        }

        if(!isset($_POST['meta_description'])) {
            $movie['meta_description'] = substr(trim(strip_tags($movie['description'])), 0, 156);
        } else {
            $movie['meta_description'] = substr(trim(strip_tags($_POST['meta_description'])), 0, 156);
            $movie['meta_description'] = str_replace(array("\n", "\r"), '', $movie['meta_description']);
            if (empty($movie['meta_description'])) {$movie['meta_description'] = substr(trim(strip_tags($movie['description'])), 0, 156);}
        }

        if (!isset($_POST['seo_url']) OR $_POST['seo_url'] == '') {
            $movie['seo_url'] = seo_url($movie['title']);
        } else {
            $movie['seo_url'] = seo_url($_POST['seo_url']);
        }

        if (isset($_POST['trailer']) AND $_POST['trailer'] == 'on') {
            echo 'test';
            $movie['preview'] = '1';
            $movie['amount_second'] = 0.0;
            $movie['amount_download'] = 0.0;
        } else {
            $movie['preview'] = '0';
        }
        
        /*
        echo '<pre>';
        print_r($_POST);
        echo '</pre>';
        exit;
        */
    }
    
    if (!isset($error) OR empty($error)) {
              
        if (p4c_query("UPDATE `movies` SET
            `actor_id` = '".abs($movie['actor_id'])."',
            `checksum`='".movie_checksum($movie)."',
            `preview_image_fsk16`='".abs($movie['fsk16'])."',
            `preview_image_fsk18`='".abs($movie['fsk18'])."',
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
            `preview`='".abs($movie['preview'])."'
            WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__)) {
           
            if (isset($_POST['edit_movie'])) {
                p4c_query("UPDATE `movies` SET
                    `movie_checked` = '0000-00-00 00:00:00',
                    `released`='1'
                    WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
                
                header('Location: '.MCP_URL.'/video/'.$movie_id.'&step=2');
            } else if (isset($_POST['save_movie'])) {
                
                p4c_query("UPDATE `movies` SET
                    `create_datetime` = '".date("Y-m-d H:i:s")."',
                    `movie_checked` = '0000-00-00 00:00:00',
                    `released`='1'
                    WHERE `id`='".abs($movie_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
                
                header('Location: '.MCP_URL.'/video/'.$movie_id.'&step=2');
            }
            
            exit;
		
        } else {
            $error = 'Der Film konnte nicht gespeichert werden!';
        }
    }
}

$site .= '
<link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/uploadfile.css" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.uploadfile.min.js"></script>

<link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
<script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.1.4/l10n/de.js"></script>

<script src="//cdn.ckeditor.com/4.6.2/full/ckeditor.js"></script>

<style type="text/css">
<!--
    input.button.ui-widget {font-size:16px !important;}
-->
</style>

<div style="width:600px;">
    <h1 class="h4">Film bearbeiten</h1>

    ';

    if (!isset($_GET['step']) OR (isset($_GET['step']) AND $_GET['step'] == 1)) {
    
        $min_online_at = $movie['online_at'];
        if ($min_online_at > date("Y-m-d H:i")) {
            $min_online_at = date("Y-m-d H:i");
        }
        
        $site .= '    
        <script type="text/javascript">
        // <![CDATA[
            jQuery(document).ready(function() {
           
                jQuery.fn.preis_je_sekunde = function() {
                    dauer = parseInt('.round($movie['playtime_seconds']).');
                    preis = jQuery(this).val();
                    gesamt = Math.round(dauer * preis);
                    jQuery("#streamingpreis").html(gesamt);
                }

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
                    minDate: "'.$min_online_at.'",
                    time_24hr: true,
                    defaultDate: "'.$movie['online_at'].'",
                    "locale": "de"
                });
                
                jQuery("#edit_movie").click(function() {
                    jQuery("#submit_param").attr("name","edit_movie");

                    jQuery("#dialog-edit").dialog({
                        resizable: false,
                        height: "auto",
                        width: 500,
                        modal: true,
                        buttons: {
                            "Abbrechen": function() {
                                jQuery( this ).dialog( "close" );
                            },
                            "Verstanden. Jetzt speichern.": function() {
                                jQuery("#form_edit").submit();
                            }
                        }
                    });
                });
                
                jQuery("#save_movie").click(function() {
                    jQuery("#submit_param").attr("name","save_movie");
                    
                    jQuery("#dialog-save").dialog({
                        resizable: false,
                        height: "auto",
                        width: 500,
                        modal: true,
                        buttons: {
                            "Abbrechen": function() {
                                jQuery( this ).dialog( "close" );
                            },
                            "Verstanden. Jetzt speichern.": function() {
                                jQuery("#form_edit").submit();
                            }
                        }
                    });
                });
                
                jQuery("#trailer").click(function() {
                    jQuery("#streamingpreis_container_a").toggle();
                });
                
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
                    <td style="width:25%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40 md-ok">cloud_upload</i><br />
                        Film hochladen
                    </td>
                    ';
                    if ($m->field('convert_status') <= 1 OR $m->field('convert_status') > 2) {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#FF9900; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-progress">settings</i><br />
                            Film wird konvertiert
                        </td>
                        ';
                    } else {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-ok">settings</i><br />
                            Film konvertiert
                        </td>
                        ';                        
                    }
                    
                    if ($m->field('movie_checked') != '0000-00-00 00:00:00') {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-ok">cloud_done</i><br />
                            Online
                        </td>
                        ';    
                    
                    // Film zur Prüfung freigegeben
                    } else if ($m->field('movie_checked') == '0000-00-00 00:00:00' AND $m->field('released') == 1) {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#FF9900; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-progress">cloud_done</i><br />
                            in Pr&uuml;fung
                        </td>
                        ';  
                        
                    // Film abgelehnt
                    } else if ($m->field('movie_checked') == '0000-00-00 00:00:00' AND $m->field('released') == 2) {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#ff0000; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-error">cloud_off</i><br />
                            Abgelehnt!
                        </td>
                        ';  
                        
                    } else if ($m->field('convert_status') == 2) {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#FF9900; font-size:15px;">
                            Schritt 4<br />
                            <i class="material-symbols-outlined md-40 md-progress">cloud_done</i><br />
                            ver&ouml;ffentlichen
                        </td>';
                    } else {
                        $site .= '
                        <td style="width:25%; text-align:center; font-size:15px;">
                            Schritt 4<br />
                            <i class="material-symbols-outlined md-40">cloud_done</i><br />
                            ver&ouml;ffentlichen
                        </td>';
                    }
                    $site .= '
                </tr>
            </table>
        </div>';
                    
        if ($m->field('movie_checked') == '0000-00-00 00:00:00' AND $m->field('released') == 2) {
            $rs_rejection_reason_movie_history = p4c_query("SELECT * FROM `rejection_reason_movie_history` WHERE `movie_file_id`='".p4c_escape_string($m->field('file_id'))."' ORDER BY `datetime` DESC LIMIT 1;",__FILE__,__LINE__);
            $reson_history_obj = p4c_fetch_object($rs_rejection_reason_movie_history);
            $rs_rejection_reason_movie = p4c_query("SELECT `id_name`, `de_short` FROM `rejection_reason_movie` WHERE `id_name`='".$reson_history_obj->id_name."' LIMIT 1;",__FILE__,__LINE__);
            
            $grund = '';
            if (p4c_result($rs_rejection_reason_movie, 0, 0) != 'free_text') {
                $grund = $grund = p4c_result($rs_rejection_reason_movie, 0, 1);
            }
            
            $site .= '
            <div class="ui-state-error" style="font-size:18px; font-weight:bold; margin-top:10px; padding:5px 10px">Der Film wurde leider abgelehnt.</div>
            <div class="ui-state-error" style="border-top:none;">
                <div style="padding:5px 10px">
                        Um den Kunden bestm&ouml;gliche Qualit&auml;t an Filmen anbieten zu k&ouml;nnen und um deine Verkaufszahlen zu erh&ouml;hen, achten wir streng auf die Qualit&auml;t deiner Filme. Bitte lies dir auch die <a class="movie_tips" href="javascript:;">Hinweise zur Qualit&auml;t deiner Filme</a> durch.
                </div>
			
                <div style="font-weight:bold; padding:5px 10px">Grund: '.$grund.'</div>
                <div style="padding:5px 10px 10px 10px;">
                    '.nl2br($reson_history_obj->text).'
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
            </div>
            <div class="ui-state-error" style="margin-bottom:10px; border-top:none; padding:5px; ">
                Du kannst den Film &uuml;berarbeiten und erneut zur Pr&uuml;fung einreichen oder jetzt l&ouml;schen.
            </div>
            ';
        }
                    
        if (isset($error) AND !empty($error)) {
            $site .= '
            <script type="text/javascript">
            // <![CDATA[
                jQuery(document).ready(function() {
                    jQuery("#warning").effect("pulsate", { times:5 }, 5000);
                })

            // ]]>
            </script>
            <div class="ui-state-error" id="warning" style="padding:10px; margin-top:10px;">'.$error.'</div>';

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
                    
        // Player erst anzeign wenn Film erfolgreich konvertiert wurde
        if ($m->field('convert_status') > 1) {
            if ($m->field('resolution') != '') {
                $explode = explode('x', $m->field('resolution'));
                $width = $explode[0];
                $height = $explode[1];
                
                // 16:9
                if ($width=='720' AND $height=='400') {
                    $width = '600';
                    $height = '363';
                // 16:9
                } elseif ($width=='1024' AND $height=='576') {
                    $width = '600';
                    $height = '363';
                // 16:9
                } elseif ($width=='1224' AND $height=='720') {
                    $width = '600';
                    $height = '363';
                // 16:9
                } elseif ($width=='1280' AND $height=='720') {
                    $width = '600';
                    $height = '363';
                // 4:3
                } elseif ($width=='1280' AND $height=='1024') {
                    $width = '600';
                    $height = '450';
                // 4:3                                    
                } elseif ($width=='1440' AND $height=='1080') {
                    $width = '600';
                    $height = '450';
                // 16:9
                } elseif ($width > '1280'){
                    $width = '600';
                    $height = '363';
                // 4:3
                } elseif ($width=='1000' AND $height=='750') {
                    $width = '600';
                    $height = '450';
                // 4:3
                } elseif ($width=='320' AND $height=='240') {
                    $width = '600';
                    $height = '450';
                } else {
                    $width = '600';
                    $height = '478';
                }
            } else {
                $width = '600';
                $height = '478';
            }

            $width = round($width);
            $height = round($height);

            $site .= '
            <center>
                <video id="video_'.$m->field('id').'"
                    poster="'.MCP_URL.'/PlayerPoster/'.$m->field('file_id').'&w='.$width.'&h='.$height.'"
                    width="'.$width.'" height="'.$height.'"
                    preload="auto" controls >
                    <source src="'.MCP_URL.'/Player/'.$m->field('file_id').'" type="video/mp4" />
                </video>
            </center>';
        }
        
        $site .= '
        <form id="form_edit" action="" method="post">';
            if ($m->field('convert_status') <= 1 OR $m->field('convert_status') > 2) {
                $site .= '<div class="ui-state-error" style="margin-top:10px; padding:10px;">Derzeit befindet sich der Film in der Konvertierung. </div>';
            } else if ($m->field('convert_status') == 2) {
                $site .= '
                <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Vorschaubild ausw&auml;hlen</div>
                <div class="ui-widget-content" style="padding:10px 0 5px 0px; border-top:none;">
                    <div style="margin-left:10px;">';
                        for($i=1;$i<=10;$i++) {
                            if ($movie['fsk16'] == $i) {$selected_fsk16 = 'checked="checked"';} else {$selected_fsk16 = '';}
                            if ($movie['fsk18'] == $i) {$selected_fsk18 = 'checked="checked"';} else {$selected_fsk18 = '';}
                            $site .= '
                            <div style="float:left; padding:0px 10px 5px 0px; position: relative;">
                                <img src="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number='.$i.'&w=186" alt="" style="width:186px; height:auto;" />
                                <div class="ui-widget-header" style="border:none; color:rgb(111, 111, 111);">
                                    <table style="width:100%">
                                        <tr>
                                            <td style="width:50px; text-align:center;">
                                                <label for="fsk16_'.$i.'">FSK16 <input type="radio" name="fsk16" '.$selected_fsk16.' value="'.$i.'" id="fsk16_'.$i.'" /></label>
                                            </td>
                                            <td style="width:50px; text-align:center;">
                                                <label for="fsk18_'.$i.'"><input type="radio" name="fsk18" '.$selected_fsk18.' value="'.$i.'" id="fsk18_'.$i.'" /> FSK18</label>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>';
                        }
                        if ($movie['fsk16'] == 11) {$selected_fsk16 = 'checked="checked"';} else {$selected_fsk16 = '';}
                        if ($movie['fsk18'] == 12) {$selected_fsk18 = 'checked="checked"';} else {$selected_fsk18 = '';}
                        $site .= '
                        <div style="float:left; padding:0px 10px 5px 0px; position: relative;">
                            <img src="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number=11&w=186&'.time().'" alt="" style="width:186px; height:auto;" />
                            <div class="ui-widget-header" style="text-align:center; border:none; color:rgb(111, 111, 111);">
                                <label for="fsk16_11"><input type="radio" name="fsk16" '.$selected_fsk16.' value="11" id="fsk16_11" /> individuelles FSK16</label>
                            </div> 
                        </div>
                        <div style="float:left; padding:0px 10px 5px 0px; position: relative;">
                            <img src="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number=12&w=186&'.time().'" alt="" style="width:186px; height:auto;" />
                            <div class="ui-widget-header" style="text-align:center; border:none; color:rgb(111, 111, 111);">
                                <label for="fsk18_12"><input type="radio" name="fsk18" '.$selected_fsk18.' value="12" id="fsk18_12" /> individuelles FSK18</label>
                            </div>
                        </div>

                        <div style="clear:both"></div>
                    </div>
        
                    <script type="text/javascript">
                        // <![CDATA[
                            jQuery(document).ready(function() {
                            ';
                              // http://hayageek.com/docs/jquery-upload-file.php
                            $site .= '
                            jQuery("#uploader_fsk16_preview_image").uploadFile({
                                url:"'.MCP_URL.'/includes/uploader/upload_movie_poster.php?fsk=16&movie_id='.$movie_id.'",
                                fileName:"movie_poster",
                                uploadStr:"FSK16 Vorschaubild hochladen",
                                abortStr:"Abbrechen",
                                maxFileSize:5120*1024, // 5 MB
                                sizeErrorStr:"ist zu gro&szlig;. Erlaubte maximale Deteigr&ouml;&szlig;e: ",
                                acceptFiles:"image/x-png,image/jpeg",
                                allowedTypes:"jpg,jpeg,png",
                                returnType:"json",
                                showPreview:true,
                                previewHeight: "100px",
                                previewWidth: "100px",
                                multiple:false,
                                showProgress:true,
                                onSuccess:function(files,data,xhr,pd) {
                                    window.location.href="'.MCP_URL.'/video/'.$movie_id.'";
                                }
                            });
                            
                            jQuery("#uploader_fsk18_preview_image").uploadFile({
                                url:"'.MCP_URL.'/includes/uploader/upload_movie_poster.php?fsk=18&movie_id='.$movie_id.'",
                                fileName:"movie_poster",
                                uploadStr:"FSK18 Vorschaubild hochladen",
                                abortStr:"Abbrechen",
                                maxFileSize:5120*1024, // 5 MB
                                sizeErrorStr:"ist zu gro&szlig;. Erlaubte maximale Deteigr&ouml;&szlig;e: ",
                                acceptFiles:"image/x-png,image/jpeg",
                                allowedTypes:"jpg,jpeg,png",
                                returnType:"json",
                                showPreview:true,
                                previewHeight: "100px",
                                previewWidth: "100px",
                                multiple:false,
                                showProgress:true,
                                onSuccess:function(files,data,xhr,pd) {
                                    window.location.href="'.MCP_URL.'/video/'.$movie_id.'";
                                }
                            });
                        })

                    // ]]>
                    </script>

                    <div class="ui-widget-header" style="padding:5px 10px; border-left:none; border-right:none;">Hier kannst du individuelle Vorschaubilder hochladen.</div>
                    <div class="ui-widget-content" style="padding:10px; border:none;">
                        <div style="margin-bottom:15px;">
                            Erlaubte Dateiformate: jpg & png<br />
                            Maximale Dateigr&ouml;&szlig;e: 5 MB
                        </div>
                        <div id="uploader_fsk16_preview_image">FSK16 Vorschaubild hochladen</div>
                        <div id="uploader_fsk18_preview_image" style="margin-top:10px;">FSK18 Vorschaubild hochladen</div>
                    </div>

                </div>';
            }
            
            $site .= '
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Angaben zum Film</div>
            <div class="ui-widget-content" style="padding:10px; border-top:none;">
                <div class="edit_title">Gib einen aussagekr&auml;ftigen Filmtitel an. <span id="anzahl_title">(max. 65 Zeichen)</span></div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="title" value="'.$movie['title'].'" onkeyup="jQuery(this).zaehle_zeichen(65, \'anzahl_title\')" placeholder="Gib einen aussagekr&auml;ftigen Filmtitel an." style="font-size:18px;" />
                </div>
    
                <div class="edit_title">Gib eine gute und aussagekr&auml;ftige <b>Beschreibung</b> des Films an.</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <textarea name="description" id="description">'.$movie['description'].'</textarea>
                    <script>
                        CKEDITOR.replace("description", {customConfig: "'.MCP_URL.'/fw/ckeditor/movie_upload_config.js"});
                    </script>
                </div>
                ';

                // Wenn der Film noch nicht ver&ouml;ffentlicht wurde
                if (($m->field('movie_checked') == '0000-00-00 00:00:00' AND $count_movie_online == 0) OR $movie['online_at'] > date("Y-m-d H:i")) {
                    $site .= '
                    <div class="edit_title">Ab wann soll der Film fr&uuml;hsten ver&ouml;ffentlicht werden?</div>
                    <div class="edit_content" style="margin-bottom:8px;">
                        <input class="flatpickr" name="online_at" type="text" value="'.$movie['online_at'].'" />
                    </div>';
                } else {
                    $site .= '
                    <div class="edit_title">Der Film wurde ver&ouml;ffentlicht am:</div>
                    <div class="edit_content" style="margin-bottom:8px;">
                        <input name="online_at" type="text" value="'.$movie['online_at'].'" readonly="readonly" disabled="disapled" />
                    </div>';
                    
                }
                
                $streamingpreis_bis = 30.1;
                if ($m->field('visible_for_website') != 'public') {
                    $streamingpreis_bis = 100.0; 
                }
                
                $site .= ' 
                <div class="edit_title">Wieviel soll der Film kosten?</div>
                <div class="edit_content" style="margin-bottom:8px; font-size: 16px;">
                    <div id="streamingpreis_container_a">
                        <span id="streamingpreis" style="padding-left:5px;">'.$streaming_preis.'</span> Coins = <select name="amount_second" onchange="jQuery(this).preis_je_sekunde();" style="width:170px;">';
                            $i=0.0;
                            while($i<=$streamingpreis_bis) {
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
                        <span style="font-size:10px; padding-left:5px;">Dies ist der Preis f&uuml;r Streaming (zum online anschauen). 1 Coin = 1 Cent (0,01 EUR)</span>
                    </div>
                </div>
                <div style="padding:5px;">
                    <label for="trailer" ><input type="checkbox" name="trailer" id="trailer" /> Der Film ist ein Trailer/ Vorstellungsvideo und soll den Kunden kostenlos angeboten werden.</label>
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
                        }
                        $site .= ' 
                    </select>
                </div>

                <div class="edit_title">Auf welcher Website soll der Film ver&ouml;ffentlicht werden?</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <!--
                    <select name="visible_for_website">
                        <option value="public"> alle Partnerwebsites</option>';
                        $rs_websites = p4c_query("SELECT * FROM `sites` WHERE `partner_id`='". p4c_escape_string($merchant->partner_id())."' AND `status`='1' ORDER BY `domain` ASC;",__FILE__,__LINE__);
                        if (p4c_num_rows($rs_websites) > 0) {
                            while($site_obj = p4c_fetch_object($rs_websites)) {
                                $site .= '<option value="'.$site_obj->domain.'"> '.$site_obj->domain.'</option>';
                            }
                        }
                        $site .= '
                    </select><br />
                    //-->
                    <input type="text" value="'.$movie['visible_for_website'].'" readonly="readonly" disabled="disapled" />
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
                <div class="edit_title">Meta Description <span id="anzahl_meta_description">(max. 165 Zeichen)</span></div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <textarea type="text" name="meta_description" onkeyup="jQuery(this).zaehle_zeichen(156, \'anzahl_meta_description\')">'.$movie['meta_description'].'</textarea>
                    <span style="font-size:10px;">Beschreibe den Film so interessant wie m&ouml;glich mit maximal 156 Zeichen.</span>
                </div>
                
                <div class="edit_title">Meta Title <span id="anzahl_meta_title">(max. 65 Zeichen)</span></div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="meta_title" value="'.$movie['meta_title'].'" readonly="readonly" disabled="disapled" />
                </div>
                
                <div class="edit_title">SEO-URL (URL-Name)</div>
                <div class="edit_content" style="margin-bottom:8px;">
                    <input type="text" name="seo_url" value="'.$movie['seo_url'].'" readonly="readonly" disabled="disapled" />
                </div>
            </div>      
            
            <input type="hidden" id="submit_param" name="edit_movie" />

            <div style="margin-top:15px; margin-bottom:30px;">
                <table style="width:100%">
                    <tr>';
                        if ($m->field('movie_checked') == '0000-00-00 00:00:00' AND $m->field('released') != 1 AND $count_movie_online == 0) {
                            $site .= '             
                            <td style="width:200px; text-align:left; vertical-align:middle;">
                                <form action="'.MCP_URL.'/Movie-Upload?step=1" method="post">
                                    <input type="hidden" name="movie_id" value="'.$movie_id.'" />
                                    <input type="submit" class="content_ruels button" name="delete_movie" value="Film l&ouml;schen" />
                                </form>
                            </td>';
                        }
                        $site .= '
                        <td style="width:auto; text-align:right;  vertical-align:middle;">';
                            if ($m->field('convert_status') == 2 AND $m->field('released') == 0) {
                                $site .= '<input type="button" id="save_movie" class="button" value="Jetzt in der '.PROJECTNAME.' ver&ouml;ffentlichen" />';
                            } else {
                                $site .= '<input type="button" id="edit_movie" class="button" value="Film erneut zur Pr&uuml;fung einreichen" />';
                            }
                            $site .= '
                        </td>
                    </tr>
                </table>
            </div>
        </form>

        <div id="dialog-edit" style="display:none;" title="Hinweis">
            <p style="line-height:22px;">
                Beachte bitte, dass vor der Ver&ouml;ffentlichung, alle &Auml;nderungen von einem Mitarbeiter &uuml;berpr&uuml;ft werden.
            </p>
        </div>
        
        <div id="dialog-save" style="display:none;" title="Hinweis">
            <p style="line-height:22px;">
                Beachte bitte, dass vor der Ver&ouml;ffentlichung, alle &Auml;nderungen von einem Mitarbeiter &uuml;berpr&uuml;ft werden.
                Dieser Vorgang wird etwas Zeit in anspruch nehmen.
                Soll der Film jetzt zur Pr&uuml;fung freigegeben und anschlie&szlig;end ver&ouml;ffentlicht werden?
            </p>
        </div>

        ';
    } else if (isset($_GET['step']) AND $_GET['step'] == 2) {
        $site .= '        
        <div class="ui-widget-content" style="padding:10px 10px 20px 10px; margin-top:10px;">
            <div style="color:#339966; text-align:center;"><i class="material-symbols-outlined md-80">done</i></div>
            <div style="font-size:80px; color:#339966; font-weight:bold; margin-bottom:15px; text-align:center;">Fertig!</div>
            <div style="text-align:center">
                <div style="font-size:15px;">Der Film wird nun gepr&uuml;ft und in der Cloud ver&ouml;ffentlicht.</div>
            </div>    
        </div>
        ';
    } else {
        header('Location: '.MCP_URL.'/Movies');
        exit;
    }
    
    $site .= '
</div>';

?>