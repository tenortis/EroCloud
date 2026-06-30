<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!isset($_GET['id'])) {exit;}

$movie_id = abs($_GET['id']);

$m = new Movie($mysql,$movie_id);

if ($m->field('id') == '') {
    header('Location: '.ACP_URL.'/Filme-pruefen');
    exit;
}

$movie['merchant_id'] = $m->field('merchant_id');
$movie['actor_id'] = $m->field('actor_id');
$movie['file_id'] = $m->field('file_id');
$movie['fsk16'] = $m->field('preview_image_fsk16');
$movie['fsk18'] = $m->field('preview_image_fsk18');
$movie['title'] = $m->field('title');
$movie['description'] = utf8_to_latin1swedishci($m->field('description'));
$movie['movie_language'] = $m->field('movie_language');
$movie['category_master'] = $m->field('category_master');
$movie['category_slave'] = $m->field('category_slave');
$movie['storage_location'] = $m->field('storage_location');
$movie['online_at'] = date("Y-m-d H:i", strtotime($m->field('online_at')));
$movie['playtime_seconds'] = $m->field('playtime_seconds');
$movie['amount_second'] = $m->field('amount_second');
$movie['amount_own'] = $m->field('amount_own');
$movie['amount_webmaster'] = $m->field('amount_webmaster');
$movie['as_download'] = $m->field('as_download');
$movie['amount_download'] = $m->field('amount_download');
$movie['meta_title'] = $m->field('meta_title');
$movie['meta_description'] = utf8_to_latin1swedishci($m->field('meta_description'));
$movie['seo_url'] = $m->field('seo_url');
$movie['quality'] = $m->field('quality');
$movie['preview'] = $m->field('preview');
$movie['status'] = $m->field('status');
$movie['visible_for_website'] = $m->field('visible_for_website');
$movie['admin_infos'] = $m->field('admin_infos');

$merchant = new Merchant($mysql,$m->field('merchant_id'));

include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
$actor = new Actor($movie['actor_id']);

if ($actor->get("id") == '') {
    
    $rs_rejection_reason_movie = p4c_query("SELECT * FROM `rejection_reason_movie` WHERE `id_name`='no_actor_selected' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_rejection_reason_movie)) {
        $reson_obj = p4c_fetch_object($rs_rejection_reason_movie);

        if ($rejection_reason_movie == '0') {
            $error_rejection_reason = 'Es wurde kein Ablehungsgrund angegeben.';
        } else {
            p4c_query("INSERT INTO `rejection_reason_movie_history` SET 
                `id_name`       = '".p4c_escape_string($reson_obj->id_name)."',
                `movie_file_id` = '".p4c_escape_string($m->field('file_id'))."',
                `text`          = '".p4c_escape_string($reson_obj->de_long)."',
                `datetime`      = '".date("Y-m-d H:i:s")."'
            ",__FILE__,__LINE__);

            // Status des Film auf "Abgelehnt" setzten (0 = noch nicht vom Amateur freigegeben / 1 = zur Prüfung freigegeben / 2 = Abgelehnt
            p4c_query("UPDATE `movies` SET `released`='2' WHERE `file_id`='".p4c_escape_string($m->field('file_id'))."' LIMIT 1;",__FILE__,__LINE__);
        }
    }
    header('Location: '.ACP_URL.'/Filme-pruefen');
    exit;
}

$file_path = MOVIES_PATH.'/'.$m->field('storage_location').'/'.$m->field('merchant_id').'/'.$movie_id.'/'.$m->field('filename');

if (isset($_POST['btn']['submit_rejection_reason_movie'])) {
    $rejection_reason_movie_long = trim(strip_tags($_POST['rejection_reason_movie_long']));
    $rejection_reason_movie = trim(strip_tags($_POST['rejection_reason_movie']));

    if ($rejection_reason_movie == '0') {
        $error_rejection_reason = 'Es wurde kein Ablehungsgrund angegeben.';
    } else {
        p4c_query("INSERT INTO `rejection_reason_movie_history` SET 
            `id_name`       = '".p4c_escape_string($rejection_reason_movie)."',
            `movie_file_id` = '".p4c_escape_string($m->field('file_id'))."',
            `text`          = '".p4c_escape_string($rejection_reason_movie_long)."',
            `datetime`      = '".date("Y-m-d H:i:s")."'
        ",__FILE__,__LINE__);

        // Status des Film auf "Abgelehnt" setzten (0 = noch nicht vom Amateur freigegeben / 1 = zur Prüfung freigegeben / 2 = Abgelehnt
        p4c_query("UPDATE `movies` SET `released`='2' WHERE `file_id`='".p4c_escape_string($m->field('file_id'))."' LIMIT 1;",__FILE__,__LINE__);

        header('Location: '.ACP_URL.'/Filme-pruefen');
        exit;
    }
}

$streaming_preis = round($movie['playtime_seconds'] * $movie['amount_second']);

$amount_webmaster_ary = array(0, 5, 10, 15, 20, 25);
$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['btn']['save_movie'])) {
        
    if (isset($_POST['actor_id']) AND $_POST['actor_id'] == 0) {
        $error = 'Bitte w&auml;hlen Sie den Hauptdartseller aus.';
    } else {
        $movie['actor_id'] = abs($_POST['actor_id']);
    }
    
    $movie['category_master'] = trim(strip_tags($_POST['category_master']));
        
    if (!isset($_POST['category_slave']) OR !is_array($_POST['category_slave'])) {
        $error = 'W&auml;hle alle Kategorien die zum Film passen.';
    } else {
        $movie['category_slave'] = trim(strip_tags(implode(',', $_POST['category_slave'])));
    }
    
   
    if(!isset($_POST['title'])) {
        $error = 'Geben Sie einen aussagekr&auml;ftigen Filmtitel an.';
    } else {
        $movie['title'] = trim(str_replace($replace_title_ary, '', $_POST['title']));
        if (empty($movie['title'])) {
            $error = 'Geben Sie einen aussagekr&auml;ftigen Filmtitel an.';    
        } 
    }

    // Entfernt alle "ZERO WIDTH SPACE"-Varianten. 
    $zero_width_spaces_ary = ["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBF\xBD", "\u200B", "\u200C", "\u200D"];

    $movie['title'] = str_replace($zero_width_spaces_ary, "", $movie['title']);
    $movie['title'] = str_replace(["#?", "?en"], ["#", "en"], $movie['title']);
    
    // Euro-Zeichen in Text umwandeln
    $movie['title'] = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $movie['title']);

    
    if(!isset($_POST['description'])) {
        $error = 'Geben Sie eine gute und aussagekr&auml;ftige Beschreibung des Films an.';
    } else {
        $allowed_tags = '<ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><p>';
        $movie['description'] = trim(strip_tags($_POST['description'], $allowed_tags));
        if (empty($movie['description'])) {
            $error = 'Geben Sie eine gute und aussagekr&auml;ftige Beschreibung des Films an.';    
        }
    }
    
    $movie['description'] = str_replace($zero_width_spaces_ary, "", $movie['description']);
    $movie['description'] = str_replace(["#?", "?en"], ["#", "en"], $movie['description']);
    
    if(isset($_POST['online_at'])) {
        $movie['online_at'] = date("Y-m-d H:i", strtotime($movie['online_at']));
    }
    
    if(!isset($_POST['amount_second'])) {
        $error = 'Bitte geben Sie an, wieviel der Film auf Ihrer eigenen Webseite kosten soll.';
    } else {
        $movie['amount_second'] = number_format($_POST['amount_second'], 1, '.', '');
    }
    
    if (!isset($_POST['fsk18']) OR empty($_POST['fsk18'])) {
        $error = 'Bitte w&auml;hlen Sie ein FSK18 Vorschaubild aus.';
    } else {
        $movie['fsk18'] = abs($_POST['fsk18']);
    }
    
    if (!isset($_POST['fsk16']) OR empty($_POST['fsk16'])) {
        $error = 'Bitte w&auml;hlen Sie ein FSK16 Vorschaubild aus.';
    } else {
        $movie['fsk16'] = abs($_POST['fsk16']);
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
        $movie['meta_title'] = substr(trim(str_replace($movie['title'])), 0, 65);
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
    
    $movie['movie_language'] = substr(trim(strip_tags($_POST['movie_language'])), 0, 2);
    
    $movie_quality_ary = array('sd','hd','fhd','2k','4k','6k');
    if (in_array($_POST['quality'], $movie_quality_ary)) {
        $movie['quality'] = $_POST['quality'];
    } else {
        $movie['quality'] = 'hd';
    }

    $movie['preview'] = abs($_POST['preview']);

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
    
    $status = p4c_escape_string(filter_input(INPUT_POST, 'movie_status', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));
    $admin_infos = p4c_escape_string(filter_input(INPUT_POST, 'admin_infos', FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW));
    
    /*
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
    exit;
    */
    
    
    
    if (!isset($error) OR empty($error)) {
              
    if (p4c_query("UPDATE `movies` SET
            `actor_id`='".p4c_escape_string($movie['actor_id'])."',
            `checksum`='".movie_checksum($movie)."',
            `quality`='".p4c_escape_string($movie['quality'])."',
            `preview_image_fsk16`='".abs($movie['fsk16'])."',
            `preview_image_fsk18`='".abs($movie['fsk18'])."',
            `title` = '".p4c_escape_string($movie['title'])."',
            `description` = '".p4c_escape_string($movie['description'])."',
            `meta_title` = '".p4c_escape_string($movie['meta_title'])."',
            `meta_description` = '".p4c_escape_string($movie['meta_description'])."',
            `movie_language` = '".p4c_escape_string($movie['movie_language'])."',
            `category_master` = '".p4c_escape_string($movie['category_master'])."',
            `category_slave` = '".p4c_escape_string($movie['category_slave'])."',
            `seo_url` = '".p4c_escape_string($movie['seo_url'])."',
            `online_at` = '".p4c_escape_string($movie['online_at'])."',
            `amount_second` = '".abs($movie['amount_second'])."',
            `amount_webmaster` = '".abs($movie['amount_webmaster'])."',
            `as_download` = '".abs($movie['as_download'])."',
            `amount_download` = '".abs($movie['amount_download'])."',
            `movie_checked` = '".date("Y-m-d H:i:s")."',
            `preview`='".abs($movie['preview'])."',
            `status`='".p4c_escape_string($status)."',
            `visible_for_website`= '".p4c_escape_string($movie['visible_for_website'])."',
            `admin_infos` ='".$admin_infos."',
            `released_from` = '".abs($_SESSION['employee_id'])."',
            `released_datetime` = '".date("Y-m-d H:i:s")."'
            WHERE `id`='".abs($movie_id)."' LIMIT 1;",__FILE__,__LINE__)) {
            
            // Prüfe ob Film schon online in der Cloud existiert
            $rs_movie_online = p4c_query("SELECT * FROM `movies_online` WHERE `file_id`='".p4c_escape_string($m->field('file_id'))."' LIMIT 1;",__FILE__,__LINE__);
            // Wenn Film existiert - aktualisieren
            if (p4c_num_rows($rs_movie_online) > 0) {
                p4c_query("UPDATE `movies_online` SET
                    `actor_id`='".p4c_escape_string($movie['actor_id'])."',
                    `checksum`='".movie_checksum($movie)."',
                    `quality`='".p4c_escape_string($movie['quality'])."',
                    `preview_image_fsk16`='".abs($movie['fsk16'])."',
                    `preview_image_fsk18`='".abs($movie['fsk18'])."',
                    `title` = '".p4c_escape_string($movie['title'])."',
                    `description` = '".p4c_escape_string($movie['description'])."',
                    `meta_title` = '".p4c_escape_string($movie['meta_title'])."',
                    `meta_description` = '".p4c_escape_string($movie['meta_description'])."',
                    `movie_language` = '".p4c_escape_string($movie['movie_language'])."',
                    `category_master` = '".p4c_escape_string($movie['category_master'])."',
                    `category_slave` = '".p4c_escape_string($movie['category_slave'])."',
                    `seo_url` = '".p4c_escape_string($movie['seo_url'])."',
                    `online_at` = '".p4c_escape_string($movie['online_at'])."',
                    `amount_second` = '".abs($movie['amount_second'])."',
                    `amount_webmaster` = '".abs($movie['amount_webmaster'])."',
                    `as_download` = '".abs($movie['as_download'])."',
                    `amount_download` = '".abs($movie['amount_download'])."',
                    `movie_checked` = '".date("Y-m-d H:i:s")."',
                    `preview`='".abs($movie['preview'])."',
                    `status`='".p4c_escape_string($status)."',
                    `visible_for_website`= '".p4c_escape_string($movie['visible_for_website'])."',
                    `admin_infos` ='".$admin_infos."',
                    `released_from` = '".abs($_SESSION['employee_id'])."',
                    `released_datetime` = '".date("Y-m-d H:i:s")."'
                    WHERE `file_id` = '".p4c_escape_string($m->field('file_id'))."' LIMIT 1;",__FILE__,__LINE__);
            }
            
            
            // Wenn Fim noch nicht online existiert - anlegen
            else {
                
                
                // Nur Film zur Online Datenbank hinzufügen wenn er aktiviert/ freigeschaltet wurde
                if ($status == 'active') {

                    p4c_query("INSERT INTO `movies_online` SET
                        `actor_id`='".p4c_escape_string($movie['actor_id'])."',
                        `merchant_id` = '".abs($m->field('merchant_id'))."',
                        `storage_location` = '".p4c_escape_string($movie['storage_location'])."',
                        `checksum`='".movie_checksum($movie)."',
                        `quality`='".p4c_escape_string($movie['quality'])."',
                        `file_id` = '".p4c_escape_string($m->field('file_id'))."',
                        `filename` = '".p4c_escape_string($m->field('filename'))."',
                        `resolution` = '".p4c_escape_string($m->field('resolution'))."',
                        `create_datetime` = '".p4c_escape_string($m->field('create_datetime'))."',
                        `playtime_string` = '".p4c_escape_string($m->field('playtime_string'))."',
                        `playtime_seconds` = '".p4c_escape_string($m->field('playtime_seconds'))."',
                        `preview_image_fsk16` = '".p4c_escape_string($m->field('preview_image_fsk16'))."',
                        `preview_image_fsk18` = '".p4c_escape_string($m->field('preview_image_fsk18'))."',
                        `title` = '".p4c_escape_string($movie['title'])."',
                        `description` = '".p4c_escape_string($movie['description'])."',
                        `movie_language` = '".p4c_escape_string($movie['movie_language'])."',
                        `meta_title` = '".p4c_escape_string($movie['meta_title'])."',
                        `meta_description` = '".p4c_escape_string($movie['meta_description'])."',
                        `category_master` = '".p4c_escape_string($movie['category_master'])."',
                        `category_slave` = '".p4c_escape_string($movie['category_slave'])."',
                        `seo_url` = '".p4c_escape_string($movie['seo_url'])."',
                        `online_at` = '".p4c_escape_string($movie['online_at'])."',
                        `amount_second` = '".abs($movie['amount_second'])."',
                        `amount_own` = '".abs($m->field('amount_second'))."',
                        `amount_webmaster` = '".abs($movie['amount_webmaster'])."',
                        `as_download` = '".abs($movie['as_download'])."',
                        `amount_download` = '".abs($movie['amount_download'])."',
                        `movie_checked` = '".date("Y-m-d H:i:s")."',
                        `preview`='".abs($movie['preview'])."',
                        `status`='".p4c_escape_string($status)."',
                        `visible_for_website`= '".p4c_escape_string($movie['visible_for_website'])."',
                        `admin_infos` ='".$admin_infos."',
                        `released_from` = '".abs($_SESSION['employee_id'])."',
                        `released_datetime` = '".date("Y-m-d H:i:s")."'
                    ;",__FILE__,__LINE__);


                    /**
                     * Punktesystem - Für Upload von kostenpflichtigen Album
                     */
                    if ($movie['amount_second'] > 0) {
                        include_once(SOURCEDIR.'/includes/klassen/PointsSystem.inc.php');

                        $points_cls = new PointsSystem;
                        $points_cls->group = 'movie_upload';
                        $points_cls->points_for = 'new_movie';
                        $points_cls->actor_id = $movie['actor_id'];
                        $points_cls->date = date("Y-m-d");

                        if ($points_cls->get_points() != false) {
                            $points = $points_cls->get_points();
                            $points_cls->set_points($points);
                        };
                    }
                }
            }
            
            header('Location: '.ACP_URL.'/Filme-pruefen');
            exit;
		
        } else {
            $error = 'Der Film konnte nicht gespeichert werden!';
        }
    }
}


$finded_poppers = false;
$category_ary = explode(',', $movie['category_slave']);
$rs_movie_categories = p4c_query("SELECT * FROM `movie_categories` WHERE `category_group`='fetish' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
$fetish_categories = '';
while($category_obj = p4c_fetch_object($rs_movie_categories)) {
    if (in_array($category_obj->name_id, $category_ary)) {
        $checked_cat_slave = 'checked="checked"';
        $style = 'background-color: #fff6d0;';
        
        if ($category_obj->name_id == 'poppers') {
            $finded_poppers = true;
        }
        
    } else if (find_categorie_in_string($category_obj) === true) {
        $checked_cat_slave = 'checked="checked"';
        $style = 'background-color: #fff6d0;';
        
        if ($category_obj->name_id == 'poppers') {
            $finded_poppers = true;
        }
        
    } else {
        $checked_cat_slave='';
        $style = '';
    }

    $fetish_categories .= '
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
    .material-symbols-outlined.md-error {color:#FF0000;}

-->
</style>

<form action="" method="post">

    <div style="width:600px; float:left;">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:10px;">Film pr&uuml;fen und ver&ouml;ffentlichen</div> 
        ';
        $rs_check_is_online = p4c_query("SELECT * FROM `movies_online` WHERE `file_id`='". p4c_escape_string($m->field('file_id'))."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_check_is_online) > 0) {
            $online_obj = p4c_fetch_object($rs_check_is_online);
            if ($m->field('status') == 'active') {
                $site .= '
                <div class="info_box" style="padding:10px; margin-bottom:10px;">
                    Dieser Film ist schon &ouml;ffentlich. Es wurden scheinbar nur &Auml;nderungen vorgenommen.
                </div>';
            } else {
                $stats = '';
                
                $rs_buys = p4c_query("SELECT * FROM `movies_access` WHERE `movie_id` LIKE '".$m->field('file_id')."' ORDER BY `buy_timestamp` DESC",__FILE__,__LINE__);
                $count_buys = p4c_num_rows($rs_buys);

                if ($count_buys > 0) {
                    
                    $rs_last_view = p4c_query("SELECT `access_token_datetime` FROM `movies_access` WHERE `movie_id` LIKE '".$m->field('file_id')."' ORDER BY `access_token_datetime` DESC",__FILE__,__LINE__);
                    $rs_last_buy = p4c_query("SELECT `buy_timestamp` FROM `movies_access` WHERE `movie_id` LIKE '".$m->field('file_id')."' ORDER BY `buy_timestamp` DESC LIMIT 1;",__FILE__,__LINE__);
                    $stats = '<br />Zuletzt gekauft am: <b>'.p4c_result($rs_last_buy, 0).'</b><br />
                        Das letzte mal angesehen am <b>'.p4c_result($rs_last_view, 0).'</b>.';
                }
                
                $site .= '
                <div class="ui-state-error" style="padding:10px; margin-bottom:10px;">
                    Dieser Film ist gesperrt oder zur l&ouml;schung vorgemert. Er existiert jedoch noch in der Online-Datenbank, ist aber nur f&uuml;r User sichtbar die ihn bereits gekauft haben.
                </div>
                
                <div class="info_box" style="padding:10px; margin-bottom:10px;">
                    Der Film wurde <b>'.$count_buys.'x gekauft</b>. '.$stats.'
                </div>';
                
            }
        }
        

        $site .= '
        <script type="text/javascript">
        // <![CDATA[
            jQuery(document).ready(function() {
                jQuery(".group1").colorbox({photo:true, rel:"group1", maxWidth:"100%", width:"auto", maxHeight:"80%"});
                            
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

                jQuery.fn.change_rejection_reason_movie = function() {
                    jQuery(".rejection_reason_movie_long").hide();

                    var option_value = jQuery("#rejection_reason_movie").val();

                    var text = jQuery("#long_"+option_value+" textarea").val();

                    jQuery("#rejection_reason_movie_long").val(text);
                    jQuery("#long_"+option_value).show();
                }

                flatpickr(".flatpickr", {
                    enableTime: true,
                    weekNumbers: true,
                    altInput: true,
                    altFormat: "Y-m-d H:i",
                    minDate: "'.$movie['online_at'].'",
                    time_24hr: true,
                    "locale": "de"
                });
                
                jQuery("[name=movie_status]").change(function() {
                    var movie_status = jQuery(this).val();
                    jQuery("#movie_status").hide();
                    if (movie_status == "deleted") {
                        jQuery("#alert_status").show();
                        jQuery("#alert_status_mess").html("ACHTUNG!<br />Sofern der Film noch nicht gekauft wurde, wird er unwiderruflich gel&ouml;scht. Sollte er bereits gekaufte worden sein, wird er nur nicht mehr angezeigt und stehen den Kunden, die diesen Film gekauft haben, weiterhin zur Verf&uuml;gung.");
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
                    <td style="width:25%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                        Schritt 2<br />
                        <i class="material-symbols-outlined md-40 md-ok">cloud_upload</i><br />
                        Film hochladen
                    </td>
                    ';
                    if ($m->field('convert_status') <= 1) {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#FF9900; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-progress">settings</i><br />
                            Film wird konvertiert
                        </td>
                        ';
                        
                    } else if ($m->field('convert_status') == 2) {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#008000; font-weight:bold; font-size:15px;">
                            Schritt 3<br />
                            <i class="material-symbols-outlined md-40 md-ok">settings</i><br />
                            Film konvertiert
                        </td>
                        ';
                        
                    } else {
                        $site .= '
                        <td style="width:25%; text-align:center; color:#FF0000; font-size:15px;">
                            Konvertrierung<br />
                            <i class="material-symbols-outlined md-40 md-error">error</i><br />
                            Abgebrochen!
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
        </div>
        ';

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
            <video
                id="video_'.$m->field('id').'"
                poster="'.ACP_URL.'/includes/movie_poster.php?id='.$m->field('file_id').'&w='.$width.'&h='.$height.'"
                width="'.$width.'" height="'.$height.'"
                preload="none"
                controls >
                <source src="'.ACP_URL.'/includes/movie_player.php?id='.$m->field('file_id').'" type="video/mp4" />
            </video>
        </center>
        <div class="ui-widget-content" style="padding:5px 10px">';
            /*
            if ($movie['quality'] == 'sd') {
                $selected['quality']['sd'] = 'selected';
            } else {$selected['quality']['sd'] = '';}
            */
        
            if ($m->field('resolution') == '1280x720') {
                $quality = '720p HD';
                $quality_s = 'hd';
            } else if ($m->field('resolution') == '1920x1080' OR $m->field('resolution') == '1440x1080') {
                $quality = '1080p Full HD';
                $quality_s = 'fhd';
            } else if ($m->field('resolution') == '2048x1080') { 
                $quality = '2K';
                $quality_s = '2k';
            } else if ($m->field('resolution') == '4096x2160' OR $m->field('resolution') == '3840x2160') { 
                $quality = '4K';
                $quality_s = '4k';
            } else if ($m->field('resolution') == '6144x3160') { 
                $quality = '6K';
                $quality_s = '6k';
            } else {
                $quality = 'SD?';
                $quality_s = 'sd';
            }
            
            $select_quality_ary = array(
                'sd' => 'SD',
                'hd' => 'HD',
                'fhd' => 'Full HD',
                '2k' => '2K',
                '4k' => '4K',
                '6k' => '6K'
            );

            if (empty($movie['quality']) OR $movie['quality'] != $quality_s) {
                $movie['quality'] = $quality_s;
            }
            
            $selected_preview_0 = '';
            $selected_preview_1 = '';
            if ($movie['preview'] == '1') {$selected_preview_1 = 'selected';}
            
            $site .= '
            <table style="width:100%;">
                <tr>
                    <td style="width:40%">
                        Aufl&ouml;sung: '.$m->field('resolution').' ('.$quality.')
                    </td>
                    <td style="width:20%; text-align:center;">
                        Qualit&auml;t <select name="quality">';
                        foreach ($select_quality_ary as $key => $value) {
                            if ($key == $movie['quality']) {$selected = 'selected';} else {$selected='';}
                            $site .= '<option '.$selected.' value="'.$key.'">'.$value.'</option>';
                        }
                        $site .= '
                        </select>
                    </td>
                    <td style="width:40%; text-align:right;">
                        Ist der Film ein Trailer? <select name="preview">
                            <option value="0">Nein</option>
                            <option '.$selected_preview_1.' value="1">Ja</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="box-sizing:border-box;">
            <div style="width:50%; display:inline-block; float:left;">
                <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Bitte w&auml;hle die Haupt-Filmsprache</div>
                <div class="ui-widget-content edit_content" style="padding:0px; border-top:none;">
                    <select id="movie_language" name="movie_language">';
                        $movie_language_ary = array(
                            'de' => 'Deutsch',
                            'en' => 'Englisch',
                            'fr' => 'Franz&ouml;sisch',
                            'es' => 'Spanisch',
                            'nl' => 'Niederl&auml;ndisch',
                            'ru' => 'Russisch',
                            'pl' => 'Polnisch'
                        );

                        foreach($movie_language_ary as $iso => $name) {
                            if ($iso == $movie['movie_language']) {$selected='selected="selected"';} else {$selected = '';}
                            $site .= '<option '.$selected.' value="'.$iso.'">'.$name.'</option>';
                        }
                        $site .= '            
                    </select>
                </div>
            </div>
            
            <div style="width:50%; margin-left:10px; width: calc(50% - 10px); display:inline-block;">
                <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Sichtbar auf</div>
                <div class="ui-widget-content edit_content" style="padding:0; border-top:none; font-size: 0.9rem;">
                    <select name="visible_for_website">';
                        // Nur auf anderen Websites vermarkten wenn kein Poppers-Video
                        if ($finded_poppers == false) {
                            $site .= '<option value="public"> allen Partnerwebsites</option>';
                        }
                        
                        $rs_websites = p4c_query("SELECT * FROM `sites` WHERE `partner_id`='". p4c_escape_string($merchant->partner_id())."' AND `status`='1' ORDER BY `domain` ASC;",__FILE__,__LINE__);
                        if (p4c_num_rows($rs_websites) > 0) {
                            while($site_obj = p4c_fetch_object($rs_websites)) {
                                $selected = '';
                                if ($movie['visible_for_website'] == $site_obj->domain) {
                                    $selected = 'selected';
                                }
                                
                                $site .= '<option '.$selected.' value="'.$site_obj->domain.'"> '.$site_obj->domain.'</option>';
                            }
                        }

                        $site .= '
                    </select>
                </div>
            </div>
            
            <div style="clear:both;"></div>
        </div>


        ';

        if (isset($error) AND !empty($error)) {
            $site .= '<div class="ui-state-error" style="padding:10px; margin-top:10px;">'.$error.'</div>';
        }

        if ($m->field('convert_status') <= 1) {
            $site .= '<div class="ui-state-error" style="margin-top:10px; padding:10px;">Derzeit befindet sich der Film in der Konvertierung. </div>';
        } else if ($m->field('convert_status') == 2) {
            $site .= '
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Vorschaubild ausw&auml;hlen</div>
            <div class="ui-widget-content" style="padding:10px 0 5px 10px; border-top:none;">';
                for($i=1;$i<=10;$i++) {
                    if ($movie['fsk16'] == $i) {$selected_fsk16 = 'checked="checked"';} else {$selected_fsk16 = '';}
                    if ($movie['fsk18'] == $i) {$selected_fsk18 = 'checked="checked"';} else {$selected_fsk18 = '';}
                    $site .= '
                    <div style="float:left; padding-right:10px; padding-bottom:5px; position: relative;">
                        <img class="group1" href="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number='.$i.'&w=800" src="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number='.$i.'&w=186" alt="" style="width:186px; height:auto;" />
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
                    <img class="group1" href="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number=11&w=800" src="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number=11&w=186&'.time().'" alt="" style="width:186px; height:auto;" />
                    <div class="ui-widget-header" style="text-align:center; border:none; color:rgb(111, 111, 111);">
                        <label for="fsk16_11"><input type="radio" name="fsk16" '.$selected_fsk16.' value="11" id="fsk16_11" /> individuelles FSK16</label>
                    </div> 
                </div>
                <div style="float:left; padding:0px 10px 5px 0px; position: relative;">
                    <img class="group1" href="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number=12&w=800" src="'.MCP_URL.'/thumb.php?movie_id='.$m->field('file_id').'&thumb_number=12&w=186&'.time().'" alt="" style="width:186px; height:auto;" />
                    <div class="ui-widget-header" style="text-align:center; border:none; color:rgb(111, 111, 111);">
                        <label for="fsk18_12"><input type="radio" name="fsk18" '.$selected_fsk18.' value="12" id="fsk18_12" /> individuelles FSK18</label>
                    </div>
                </div>
                <div style="clear:both"></div>
            </div>';
        }

        $site .= '
        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Angaben zum Film</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">
            <div class="edit_title">Gib einen aussagekr&auml;ftigen Filmtitel an. <span id="anzahl_title">(max. 65 Zeichen)</span></div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="title" value="'.$movie['title'].'" onkeyup="jQuery(this).zaehle_zeichen(65, \'anzahl_title\')" placeholder="Geben Sie einen aussagekräftigen Filmtitel an." style="font-size:18px;" />
            </div>

            <div class="edit_title">Geben Sie eine gute und aussagekr&auml;ftige <b>Beschreibung</b> des Films an.</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <textarea name="description" id="description" placeholder="" >'.$movie['description'].'</textarea>
                <script>
                    CKEDITOR.replace("description", {customConfig: "'.MCP_URL.'/fw/ckeditor/movie_upload_config.js?v=1"});
                </script>
            </div>

            <div class="edit_title">Ab wann soll der Film fr&uuml;hsten ver&ouml;ffentlicht werden?</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input class="flatpickr" name="online_at" type="text" value="'.$movie['online_at'].'" />
            </div>

            <div class="edit_title">Wieviel soll der Film kosten?</div>
            <div class="edit_content" style="margin-bottom:8px; font-size: 16px;">
                <span id="streamingpreis" style="padding-left:5px;">'.$streaming_preis.'</span> Coins = <select name="amount_second" onchange="jQuery(this).preis_je_sekunde();" style="width:170px;">';
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
                <span style="font-size:10px;">Dies ist der Preis f&uuml;r Streaming (zum online anschauen). 1 Coin = 1 Cent (0,01 EUR)</span>
            </div>
        </div>
        <div class="ui-widget-header" style="padding:5px 10px; border-top:none;">weitere Einstellungen</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">
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
                <span style="font-size:10px;">Empfohlen! Dies steigert deinen Umsatz.</span>
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
                <textarea type="text" name="meta_description" style="height:100px;" onkeyup="jQuery(this).zaehle_zeichen(156, \'anzahl_meta_description\')">'.$movie['meta_description'].'</textarea>
                <span style="font-size:10px;">Beschreiben Sie den Film so interessant wie m&ouml;glich mit maximal 156 Zeichen.</span>
            </div>

            <div class="edit_title">Meta Title <span id="anzahl_meta_title">(max. 65 Zeichen)</span></div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="meta_title" value="'.$movie['meta_title'].'" onkeyup="jQuery(this).zaehle_zeichen(65, \'anzahl_meta_title\')" />
                <span style="font-size:10px;">Der Meta-Title sollte exat der selbe gleiche sein wie der Filmtitel.</span>
            </div>

            <div class="edit_title">SEO-URL (URL-Name)</div>
            <div class="edit_content" style="margin-bottom:8px;">
                <input type="text" name="seo_url" value="'.$movie['seo_url'].'" />
                <span style="font-size:10px; color:#ff0000;">Wenn die URL ge&auml;ndert wird, verliert der Film sein Ranking in den Suchmaschinen!</span>
            </div>
        </div>      

        <div style="margin-top:15px; margin-bottom:30px;">
            <table style="width:100%">
                <tr>
                    <td style="width:50%">

                    </td>
                    <td style="width:50%; text-align:right;">';
                        if ($m->field('convert_status') == 2) {
                            $site .= '<input type="submit" id="save_movie" name="btn[save_movie]" class="button" value="Gepr&uuml;ft. Jetzt in der '.PROJECTNAME.' ver&ouml;ffentlichen" />';
                        } else {
                            $site .= '<input type="submit" id="save_movie" class="button" disabled value="Gepr&uuml;ft. Jetzt in der '.PROJECTNAME.' ver&ouml;ffentlichen" />';
                        }
                        $site .= '
                    </td>
                </tr>
            </table>
        </div> 
    </div>

    <div style="width:600px; margin-left:620px;">

    <div class="ui-widget-header" style="padding:5px 10px;">Informationen zur Datei</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; ">
            <div style="display: inline-block; width: 300px;">
                file_id: '.$movie['file_id'].'<br />
                P4C-PartnerID: <a href="'.Pay4Coins_ACP_URL.'/Haendler/'.$merchant->partner_id().'" target="_blank">'.$merchant->partner_id().'</a><br />
                P4C-Username: <a href="'.Pay4Coins_ACP_URL.'/Haendler/'.$merchant->partner_id().'" target="_blank">'.$merchant->username('aes_decrypt').'</a><br />
                Meta-Daten zum Film: <a class="movie_metainfos" href="javascript:;">anzeigen</a>
            </div>
            
            <div style="display: inline-block; vertical-align:top;">
                Hauptdarsteller im Film:<br />
                <select name="actor_id" style="font-size:25px;">
                    <option value="0">Bitte w&auml;hlen</option> 
                    ';
                    $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id` = '".abs($merchant->id())."' ORDER BY `username` ASC;",__FILE__,__LINE__);
                    if (p4c_num_rows($rs_actors) > 0) {
                        while($actor_obj = p4c_fetch_object($rs_actors)) {
                            if ($actor_obj->id == $movie['actor_id']) {
                                $selected = 'selected="selected"';
                            } else {
                                $selected = '';
                            }
                            
                            $site .= '<option '.$selected.' value="'.$actor_obj->id.'">'.$actor_obj->username.'</option>';
                        }
                    }

                    $site .= '
                </select><br />
                <a href="'.ACP_URL.'/Actor/'.$movie['actor_id'].'" target="_blank">Profil anzeigen</a>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px; ">Film-Status</div>
        <div class="ui-widget-content radioset" style="font-size:12px; padding:10px; border-top:none; margin-bottom:10px;">';
        
            // gesperrt = Film nicht sichtbar. Bereits gekaufter Content weiterhin online.
            // gelöscht = Sofern der Film noch nicht gekauft wurde, wird er gelöscht. Ansonsten ist er nur nicht sichbar. Für Kunden die Ihn gesehen haben trotzdem weiterhin online

            if ($movie['status'] == 'active') {
                $staus1 = 'checked="checked"';
                $staus2 = '';
                $staus3 = '';
            } else if ($movie['status'] == 'blocked') {
                $staus1 = '';
                $staus2 = 'checked="checked"';
                $staus3 = '';
            } else if ($movie['status'] == 'deleted') {
                $staus1 = '';
                $staus2 = '';
                $staus3 = 'checked="checked"';
            }
            $site .= '
            <label for="status1">Aktiv</label><input '.$staus1.' class="radio" type="radio" name="movie_status" value="active" id="status1" /> 
            <label for="status2">Gesperrt</label><input '.$staus2.' class="radio" type="radio" name="movie_status" value="blocked" id="status2" />';
            if ($_SESSION['employee_rule'] == 'write') {
                $site .= '
                <label for="status3">Gel&ouml;scht</label><input '.$staus3.' class="radio" type="radio" name="movie_status" value="deleted" id="status3" /> 
                ';
            }
            $site .= '            
            <div class="ui-state-error" id="alert_status" style="display:none; padding:10px; margin-top:5px;">
                <span id="alert_status_mess"></span>
            </div>
        </div>
        

        <div class="ui-widget-header" style="padding:5px 10px;">Admin-Infos zum Film</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none;">
            <textarea style="min-height:100px; width:100%; width: -webkit-fill-available; width: -moz-available;" name="admin_infos">'.$movie['admin_infos'].'</textarea>
        </div>

        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-top:10px;">Film ablehnen</div>';

        if (isset($error_rejection_reason) AND !empty($error_rejection_reason)) {
            $site .= '<div class="ui-state-error" style="padding:10px;">'.$error_rejection_reason.'</div>';
        }

        $site .= '
        <div class="ui-widget-content edit_content" style="margin-bottom:8px; border-top:none;">
            <select id="rejection_reason_movie" name="rejection_reason_movie" onchange="jQuery(this).change_rejection_reason_movie();">
                <option value="0">Grund f&uuml;r die Ablehnung w&auml;hlen...</option>';
                $rs_rejection_reason_movie = p4c_query("SELECT * FROM `rejection_reason_movie` ORDER BY `de_short` DESC;",__FILE__,__LINE__);
                while($reson_obj = p4c_fetch_object($rs_rejection_reason_movie)) {
                    $site .= '<option value="'.$reson_obj->id_name.'">'.$reson_obj->de_short.'</option>';
                }
                $site .= '            
            </select>
            <div style="display:none;">
                <input type="hidden" id="rejection_reason_movie_long" name="rejection_reason_movie_long" value="" />
            </div>';

            $rs_rejection_reason_movie = p4c_query("SELECT * FROM `rejection_reason_movie` ORDER BY `de_short` DESC;",__FILE__,__LINE__);
            while($reson_obj = p4c_fetch_object($rs_rejection_reason_movie)) {
                $site .= '
                <div class="rejection_reason_movie_long" style="display:none;" id="long_'.$reson_obj->id_name.'">
                    <textarea style="width:100%; height:150px;">'.$reson_obj->de_long.'</textarea>
                </div>';
            }
            $site .= '
            <div style="text-align:center; margin:5px 0;">
                <input class="button" type="submit" name="btn[submit_rejection_reason_movie]" onclick="jQuery(this).change_rejection_reason_movie();" value="Film ablehnen" />
            </div>
        </div>';

        $rs_rejection_reason_movie_history = p4c_query("SELECT * FROM `rejection_reason_movie_history` WHERE `movie_file_id`='".p4c_escape_string($m->field('file_id'))."' ORDER BY `datetime`DESC;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_rejection_reason_movie_history) > 0) {
            $site .= '
            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">History</div>
            <div class="ui-widget-content edit_content" style="margin-bottom:8px; border-bottom:none; border-top:none;">';
                while($reson_history_obj = p4c_fetch_object($rs_rejection_reason_movie_history)) {
                    $rs_rejection_reason_movie = p4c_query("SELECT `de_short` FROM `rejection_reason_movie` WHERE `id_name`='".$reson_history_obj->id_name."' ORDER BY `de_short` ASC LIMIT 1;",__FILE__,__LINE__);
                    $employee_name = p4c_query("SELECT `username` FROM `employee` WHERE `id`='".abs($reson_history_obj->employee_id)."' LIMIT 1;",__FILE__,__LINE__);
                    $rejection_reason_username = p4c_result($employee_name, 0);
                    if (empty($rejection_reason_username)) {
                        $rejection_reason_username = '-unbekannt-';
                    }
                    $site .= '
                    <div style="padding:5px; border-bottom:1px solid">
                        <div><b>'. p4c_result($rs_rejection_reason_movie, 0).'</b></div>
                        <div>Zeitpunkt: '.$reson_history_obj->datetime.'</div>
                        <div>Abgeleht von: '.$rejection_reason_username.'</div>
                        <div>Mehr Infos zur Ablehnung:<br />'.nl2br($reson_history_obj->text).'</div>
                    </div>';
                }
                $site .= '
            </div>';
        }
        
        function find_categorie_in_string($category_obj) {
            global $movie;
            
            $search_category_string = strtolower($movie['title'].' '.$movie['description']);
            if (strpos($search_category_string, strtolower($category_obj->name_id)) !== FALSE OR strpos($search_category_string, strtolower($category_obj->de_name_value)) !== FALSE) {
                return true;
            }

            if (trim($category_obj->more_search_words) != '') {      
                $more_search_words_ary = explode(',',strtolower($category_obj->more_search_words));
                foreach ($more_search_words_ary as $value) {
                    if (strpos($search_category_string, trim($value)) !== FALSE) {
                        return true;
                    }
                }
            }
        }
        
        $actor_categories = explode(',',$actor->get('actor_categories'));
        
        /**
         * Kategorien
         */
        $site .= '
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-top:20px;">Film kategorisieren</div> 

        <div class="ui-widget-header" style="padding:5px 10px; border-top:none;">Hauptkategorie</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; border-top:none;">';
            if ($movie['category_master'] == 'porn' OR $movie['category_master'] == '') {$checked_porn = 'checked="checked"';} else {$checked_porn='';}
            $site .= '
            <div style="padding:5px; border-bottom:1px solid">
                <div style="font-weight:bold;">
                    <label for="porn"><input type="radio" '.$checked_porn.' id="porn" name="category_master" value="porn" /> Porno</label>
                </div>
                <div style="padding-left:20px;">
                    W&auml;hle diese Kategorie, wenn der Film pornografische Inhalte enth&auml;lt.
                </div>
            </div>';
            if ($movie['category_master'] == 'fetish' OR in_array('fetish', $actor_categories)) {
                $checked_fetish = 'checked="checked"';
            } else {
                $checked_fetish='';
                
            }
            $site .= '
            <div style="padding:5px;">
                <div style="font-weight:bold;">
                    <label for="fetish"><input type="radio" '.$checked_fetish.' id="fetish" name="category_master" value="fetish" /> Fetisch</label>
                </div>
                <div style="padding-left:20px;">
                    W&auml;hle diese Kategorie, wenn der Film ein reiner Fetischfilm, wie SM, BDSM usw. ist.
                </div>
            </div>
        </div>

        <div class="ui-widget-header" style="padding:5px 10px; ">W&auml;hle alle Kategorien die zum Film passen</div>
        <div class="ui-widget-content edit_content" style="border-bottom:none; margin-bottom:8px; border-top:none;">
           <div style="padding:5px; border-bottom:1px solid; font-weight:bold;">Anzahl der Personen & sexuelle Orientierung</div>';
            $category_ary = explode(',', $movie['category_slave']);
            $rs_movie_categories = p4c_query("SELECT * FROM `movie_categories` WHERE `category_group`='number_of_people' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_movie_categories)) {
                if (in_array($category_obj->name_id, $category_ary)) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                /*
                } else if (find_categorie_in_string($category_obj) === true) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                */
                } else {
                    // Wenn nichts gewählt wurde
                    if ($category_obj->name_id == 'solo_girl') {
                        $checked_cat_slave = 'checked="checked"';
                        $style = 'background-color: #fff6d0;';
                    } else {
                        $checked_cat_slave='';
                        $style = '';
                    }
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
            $category_ary = explode(',', $movie['category_slave']);
            $rs_movie_categories = p4c_query("SELECT * FROM `movie_categories` WHERE `category_group`='look_and_body' ORDER BY `name_id` ASC;",__FILE__,__LINE__);

            while($category_obj = p4c_fetch_object($rs_movie_categories)) {
                if (in_array($category_obj->name_id, $category_ary) OR in_array($category_obj->name_id, $actor_categories) OR (in_array('hair_darkblonde', $actor_categories) AND $category_obj->name_id == 'blonde')) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                } else if (find_categorie_in_string($category_obj) === true) {
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
            <div style="padding:15px 5px 5px 5px; border-bottom:1px solid; font-weight:bold;">Fetisch</div>
            '.$fetish_categories.'
                
            <div style="padding:15px 5px 5px 5px; border-bottom:1px solid; font-weight:bold;">Sonstige</div>';
            $category_ary = explode(',', $movie['category_slave']);
            $rs_movie_categories = p4c_query("SELECT * FROM `movie_categories` WHERE `category_group`='porn' ORDER BY `name_id` ASC;",__FILE__,__LINE__);
            while($category_obj = p4c_fetch_object($rs_movie_categories)) {
                if (in_array($category_obj->name_id, $category_ary)) {
                    $checked_cat_slave = 'checked="checked"';
                    $style = 'background-color: #fff6d0;';
                } else if (find_categorie_in_string($category_obj) === true) {
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
</form>';

?>