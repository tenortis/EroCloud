<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (isset($_POST['delete_movie'])) {
    $movie_id = abs($_POST['movie_id']);
    
    $rs_check_movie_exists = p4c_query("SELECT * FROM `movies` WHERE `id`='".$movie_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_movie_exists) == 1) {
        $movie_obj = p4c_fetch_object($rs_check_movie_exists);
        
        // Watertight check to determine if the movie was ever approved, is online, or has purchases
        $is_published_or_purchased = false;
        
        if ($movie_obj->movie_checked != '0000-00-00 00:00:00') {
            $is_published_or_purchased = true;
        }
        
        if (!$is_published_or_purchased) {
            $rs_online = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id`='".p4c_escape_string($movie_obj->file_id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_online) > 0) {
                $is_published_or_purchased = true;
            }
        }
        
        if (!$is_published_or_purchased) {
            $rs_access = p4c_query("SELECT `id` FROM `movies_access` WHERE `movie_id`='".p4c_escape_string($movie_obj->file_id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_access) > 0) {
                $is_published_or_purchased = true;
            }
        }
        
        $deleted_datetime = '0000-00-00 00:00:00';
        if ($is_published_or_purchased) {
            $deleted_datetime = date("Y-m-d H:i:s");
        }
        
        // Soft delete: update status and deleted_datetime in movies and movies_online
        p4c_query("UPDATE `movies` SET 
            `status`='deleted', 
            `deleted_datetime`='".$deleted_datetime."' 
            WHERE `id`='".$movie_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
            
        p4c_query("UPDATE `movies_online` SET 
            `status`='deleted', 
            `deleted_datetime`='".$deleted_datetime."' 
            WHERE `file_id`='".p4c_escape_string($movie_obj->file_id)."' LIMIT 1;",__FILE__,__LINE__);
            
        // Clear upload session
        if (isset($_SESSION['upload_movie'])) {
            unset($_SESSION['upload_movie']);
        }
        
        header('Location: '.MCP_URL.'/Movies?del=ok');
        exit;
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
$movie['category_master'] = 'porn';
$movie['category_slave'] = '';
$movie['visible_for_website'] = 'public';

$amount_webmaster_ary = array(0, 5, 10, 15, 20, 25);
$replace_title_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['upload_content']) OR isset($_POST['submit_step1'])) {
    
    $errors = array();

    if(!isset($_POST['title']) OR trim($_POST['title']) == '') {
        $errors[] = 'Geben Sie bitte einen aussagekr&auml;ftigen Filmtitel an.';
    } else {
        $movie['title'] = trim(str_replace($replace_title_ary, '', $_POST['title']));
        if (empty($movie['title'])) {
            $errors[] = 'Geben Sie bitte einen aussagekr&auml;ftigen Filmtitel an.';    
        } 
    }

    // Entfernt alle "ZERO WIDTH SPACE"-Varianten. 
    $zero_width_spaces_ary = ["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBF\xBD", "\u200B", "\u200C", "\u200D"];

    $movie['title'] = str_replace($zero_width_spaces_ary, "", $movie['title']);
    $movie['title'] = str_replace(["#?", "?en"], ["#", "en"], $movie['title']);
    
    // Euro-Zeichen in Text umwandeln
    $movie['title'] = str_replace(array('&#8364;','&euro;','Â€','€','â‚¬','&#x20AC;'), "EUR", $movie['title']);

    
    if (strlen(utf8_decode($movie['title'])) > 65) {
        $errors[] = 'Der Filmtitel darf maximal 65 Zeichen lang sein.';
    }
    
    if(!isset($_POST['description'])) {
        $errors[] = 'Geben Sie bitte eine gute und aussagekr&auml;ftige Beschreibung des Films an.';
    } else {
        $allowed_tags = '<ul><ol><li><u><em><strong><h1 class="h4"><h2><h3><h4><h5><h6><pre><address><p>';
        $movie['description'] = trim(strip_tags($_POST['description'], $allowed_tags));
        if (empty($movie['description'])) {
            $errors[] = 'Geben Sie bitte eine gute und aussagekr&auml;ftige Beschreibung des Films an.';    
        }
    }
    
    // Entfernt alle "ZERO WIDTH SPACE"-Varianten. 
    $movie['description'] = str_replace($zero_width_spaces_ary, "", $movie['description']);
    $movie['description'] = str_replace(["#?", "?en"], ["#", "en"], $movie['description']);

    
    if(isset($_POST['category_master'])) {
        $movie['category_master'] = trim(strip_tags($_POST['category_master']));
    } else {
        $movie['category_master'] = 'porn';
    }

    if (!isset($_POST['category_slave']) OR !is_array($_POST['category_slave']) OR count($_POST['category_slave']) < 1) {
        $errors[] = 'W&auml;hlen Sie bitte mindestens 1 passende Unterkategorie f&uuml;r den Film aus.';
        $movie['category_slave'] = '';
    } else {
        $movie['category_slave'] = trim(strip_tags(implode(',', $_POST['category_slave'])));
    }

    if(isset($_POST['actor_id'])) {
        $movie['actor_id'] = abs($_POST['actor_id']);
    }
    
    if ($movie['actor_id'] <= 0) {
        $errors[] = 'Bitte w&auml;hlen Sie ein Darsteller-Profil aus (oder legen Sie zuerst ein neues Profil an).';
    }
    
    if(isset($_POST['online_at'])) {
        $movie['online_at'] = date("Y-m-d H:i", strtotime($_POST['online_at']));
    }

    if(!isset($_POST['amount_second']) OR trim($_POST['amount_second']) === '') {
        $errors[] = 'Geben Sie bitte an, wie viel der Film kosten soll.';
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
        $errors[] = 'Sie haben bereits einen Film mit diesem Titel hochgeladen.';
        $duplicate_title = p4c_result($rs_check_movie_exists, 0);
        
    }

    // Prüfe ob bei diesem Kunden exakt dieser Film schon existiert -> dann updaten nicht neu anlegen
    $rs_check_movie_exists = p4c_query("SELECT `id`  FROM `movies` WHERE `title`='".p4c_escape_string($movie['title'])."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($movie_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_movie_exists) == 1) {
        $movie_exists = true;  
    }
    
    $_SESSION['upload_movie'] = $movie;
    $_SESSION['upload_movie']['movie_id'] = $movie_id;
    
    if (!empty($errors)) {
        $error = '<strong>Bitte korrigieren Sie die folgenden Eingaben:</strong><ul class="mb-0 mt-2 ps-3">';
        foreach ($errors as $err_msg) {
            $error .= '<li>' . $err_msg . '</li>';
        }
        $error .= '</ul>';
    }
    
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
                `category_master` = '".p4c_escape_string($movie['category_master'])."',
                `category_slave` = '".p4c_escape_string($movie['category_slave'])."',
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
                `category_master` = '".p4c_escape_string($movie['category_master'])."',
                `category_slave` = '".p4c_escape_string($movie['category_slave'])."',
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

<div style="max-width: 1600px;">
    <h1 class="h4">Film in die EroCloud hochladen</h1>
    ';

function get_upload_wizard_html($active_step = 1) {
    $steps = [
        1 => ["title" => "Filminfos angeben"],
        2 => ["title" => "Film hochladen"],
        3 => ["title" => "Film konvertiert"],
        4 => ["title" => "Ver&ouml;ffentlichen"]
    ];

    $html = '
        <!-- Top Step Wizard Header -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="row text-center g-2">';

    foreach ($steps as $step_num => $step_data) {
        $is_completed = ($step_num < $active_step);
        $is_current = ($step_num == $active_step);

        if ($is_completed) {
            $box_class = 'bg-success text-white shadow-sm';
            $badge_html = '<span class="d-inline-flex align-items-center justify-content-center bg-white text-success rounded-circle mb-1 p-0 shadow-sm" style="width:24px; height:24px; font-size:14px;"><i class="bi bi-check-lg"></i></span>';
        } else if ($is_current) {
            $box_class = 'bg-success text-white shadow-sm fw-bold';
            $badge_html = '<span class="d-inline-flex align-items-center justify-content-center bg-white text-success rounded-circle mb-1 p-0 shadow-sm" style="width:24px; height:24px; font-size:12px; font-weight:bold;">'.$step_num.'</span>';
        } else {
            $box_class = 'bg-light text-muted';
            $badge_html = '<span class="d-inline-flex align-items-center justify-content-center bg-secondary text-white rounded-circle mb-1 p-0" style="width:24px; height:24px; font-size:12px; font-weight:bold;">'.$step_num.'</span>';
        }

        $html .= '
                    <div class="col-3">
                        <div class="p-2 rounded '.$box_class.' h-100 d-flex flex-column justify-content-center align-items-center" style="min-height: 70px;">
                            '.$badge_html.'
                            <small class="d-block lh-sm">Schritt '.$step_num.'<br><span class="fw-normal">'.$step_data['title'].'</span></small>
                        </div>
                    </div>';
    }

    $html .= '
                </div>
            </div>
        </div>';

    return $html;
}

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
    
        
        
' . get_upload_wizard_html(1) . '

        <!-- Buttons for Quality & Rendering Modals -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn btn-outline-info shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMovieTips">
                <i class="bi bi-info-circle me-1"></i> Hinweise zur Qualit&auml;t Ihrer Filme
            </button>
            <button type="button" class="btn btn-outline-secondary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMovieRenderingTips">
                <i class="bi bi-gear me-1"></i> So rendern Sie Ihre Filme richtig
            </button>
        </div>

        <!-- Modal Movie Tips -->
        <div class="modal fade" id="modalMovieTips" tabindex="-1" aria-labelledby="modalMovieTipsLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="modalMovieTipsLabel"><i class="bi bi-info-circle me-2 text-info"></i>Hinweise zur Qualit&auml;t Ihrer Filme</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">';
                        ob_start();
                        include_once(MCP_DIR.'/includes/overlays/movie_tips.php');
                        $movie_tips_content = ob_get_clean();
                        $site .= $movie_tips_content . '
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schlie&szlig;en</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Movie Rendering Tips -->
        <div class="modal fade" id="modalMovieRenderingTips" tabindex="-1" aria-labelledby="modalMovieRenderingTipsLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="modalMovieRenderingTipsLabel"><i class="bi bi-gear me-2 text-secondary"></i>So rendern Sie Ihre Filme richtig</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">';
                        ob_start();
                        include_once(MCP_DIR.'/includes/overlays/movie_rendering_tips.php');
                        $movie_rendering_tips_content = ob_get_clean();
                        $site .= $movie_rendering_tips_content . '
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schlie&szlig;en</button>
                    </div>
                </div>
            </div>
        </div>';

        if (isset($error) AND !empty($error)) {
            $site .= '<div class="alert alert-danger mb-4 shadow-sm">'.$error.'</div>';
            
            if (isset($duplicate_title)) {
                $rs_dublicate_movie = p4c_query("SELECT * FROM `movies` WHERE `id`='".abs($duplicate_title)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
                if (p4c_num_rows($rs_dublicate_movie) == 1) {
                    $dublicat_ary = p4c_fetch_object($rs_dublicate_movie);
                    $site .= '
                    <div class="card shadow-sm border-warning mb-4">
                        <div class="card-header bg-warning bg-opacity-10 fw-bold">'.$dublicat_ary->title.'</div>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <img src="'.MCP_URL.'/PlayerPoster/'.$dublicat_ary->file_id.'&w=100" class="rounded shadow-sm" style="width:100px; height:auto;" />
                                <div>
                                    <a href="'.MCP_URL.'/video/'.$dublicat_ary->id.'" target="_blank" class="btn btn-sm btn-outline-primary mb-2">Klicken Sie hier, um zum Film zu wechseln</a>
                                    <div class="fw-bold text-muted my-1">ODER</div>
                                    <small class="text-muted">Wenn dieser Film tats&auml;chlich ein anderer ist, verwenden Sie bitte einen neuen Titel.</small>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
            }
        }

        $site .= '
        <form action="" method="post">
            <!-- Zeile 1: Links Angaben zum Film, Rechts Film kategorisieren -->
            <div class="row g-4 mb-4">
                <!-- Links: Angaben zum Film -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-header bg-light fw-bold py-3"><i class="bi bi-film me-2"></i>Angaben zum Film</div>
                        <div class="card-body">
                            <div class="edit_title fw-bold mb-1">Geben Sie einen aussagekr&auml;ftigen Filmtitel an. <span id="anzahl_title" class="text-muted fw-normal">(max. 65 Zeichen)</span></div>
                            <div class="edit_content mb-3">
                                <input type="text" class="form-control form-control-lg" name="title" value="'.$movie['title'].'" onkeyup="jQuery(this).zaehle_zeichen(65, \'anzahl_title\')" placeholder="Geben Sie einen aussagekr&auml;ftigen Filmtitel an." style="font-size:18px;" />
                            </div>
                
                            <div class="edit_title fw-bold mb-1">Geben Sie eine gute und aussagekr&auml;ftige <b>Beschreibung</b> des Films an.</div>
                            <div class="edit_content mb-3">
                                <textarea class="form-control" name="description" id="description">'.$movie['description'].'</textarea>
                                <script>
                                    CKEDITOR.replace("description", {customConfig: "'.MCP_URL.'/fw/ckeditor/movie_upload_config.js?v=1"});
                                </script>
                            </div>
                
                            <div class="edit_title fw-bold mb-1">Ab wann soll der Film fr&uuml;hsten ver&ouml;ffentlicht werden?</div>
                            <div class="edit_content mb-3">
                                <input class="flatpickr form-control" name="online_at" type="text" value="'.$movie['online_at'].'" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rechts: Film kategorisieren -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-header bg-light fw-bold py-3"><i class="bi bi-tags me-2"></i>Film kategorisieren</div>
                        <div class="card-body p-0">
                            <div class="p-3 bg-light border-bottom font-weight-bold fw-bold">Hauptkategorie</div>
                            <div class="p-3 border-bottom">';
                                if ($movie['category_master'] == 'porn' OR empty($movie['category_master'])) {$checked_porn = 'checked="checked"';} else {$checked_porn='';}
                                $site .= '
                                <div class="p-2 border-bottom">
                                    <div class="fw-bold">
                                        <label for="porn"><input type="radio" '.$checked_porn.' id="porn" name="category_master" value="porn" /> Porno</label>
                                    </div>
                                    <div class="small text-muted ms-4">W&auml;hlen Sie diese Kategorie, wenn der Film pornografische Inhalte enth&auml;lt.</div>
                                </div>';
                                if ($movie['category_master'] == 'fetish') {$checked_fetish = 'checked="checked"';} else {$checked_fetish='';}
                                $site .= '
                                <div class="p-2">
                                    <div class="fw-bold">
                                        <label for="fetish"><input type="radio" '.$checked_fetish.' id="fetish" name="category_master" value="fetish" /> Fetisch</label>
                                    </div>
                                    <div class="small text-muted ms-4">W&auml;hlen Sie diese Kategorie, wenn der Film ein reiner Fetischfilm, wie SM, BDSM usw. ist.</div>
                                </div>
                            </div>
                            <div class="p-3 bg-light border-bottom font-weight-bold fw-bold">W&auml;hlen Sie alle Kategorien, die zum Film passen</div>
                            <div class="p-3 border-bottom text-dark small" style="background-color: #fff6d0; border-color: #ffe8a1;">
                                <i class="bi bi-info-circle-fill text-warning me-2"></i><strong>Hinweis:</strong> Vom System automatisch vorausgew&auml;hlte Kategorien werden gelb hervorgehoben. Bitte w&auml;hlen Sie mindestens 1 passende Unterkategorie aus (empfohlen: 2–4 Unterkategorien für optimale Auffindbarkeit in der Suche).
                            </div>
                            <div class="p-3">';
                            
                                $saved_category_ary = !empty($movie['category_slave']) ? explode(',', $movie['category_slave']) : [];
                                $actor_categories = [];
                                if (!empty($movie['actor_id'])) {
                                    $rs_actor_cat = p4c_query("SELECT `actor_categories` FROM `actors` WHERE `id`='".abs($movie['actor_id'])."';",__FILE__,__LINE__);
                                    if ($rs_actor_cat AND p4c_num_rows($rs_actor_cat) > 0) {
                                        $actor_row = p4c_fetch_object($rs_actor_cat);
                                        $actor_categories = explode(',', $actor_row->actor_categories);
                                    }
                                }

                                $search_text = strtolower(strip_tags($movie['title'] . ' ' . $movie['description']));

                                $site .= '
                                <div class="accordion accordion-flush" id="categoryAccordion">';

                                $cat_groups = [
                                    ["id" => "cat_people", "title" => "Anzahl der Personen &amp; sexuelle Orientierung", "group" => "number_of_people"],
                                    ["id" => "cat_body", "title" => "K&ouml;rper und Aussehen", "group" => "look_and_body"],
                                    ["id" => "cat_fetish", "title" => "Fetisch", "group" => "fetish"],
                                    ["id" => "cat_other", "title" => "Sonstige", "group" => "porn"]
                                ];

                                foreach($cat_groups as $cat_g) {
                                    $rs_count = p4c_query("SELECT * FROM `movie_categories` WHERE `category_group`='".$cat_g['group']."';",__FILE__,__LINE__);
                                    $selected_count = 0;
                                    $items_html = '';

                                    while($category_obj = p4c_fetch_object($rs_count)) {
                                        $is_saved = in_array($category_obj->name_id, $saved_category_ary);

                                        // Check system auto-detection (by title/description keywords or actor categories)
                                        $is_system_detected = false;
                                        if (!empty($search_text)) {
                                            if (strpos($search_text, strtolower($category_obj->name_id)) !== false OR strpos($search_text, strtolower($category_obj->de_name_value)) !== false) {
                                                $is_system_detected = true;
                                            } else if (!empty($category_obj->more_search_words)) {
                                                $words = explode(',', strtolower($category_obj->more_search_words));
                                                foreach ($words as $w) {
                                                    $w = trim($w);
                                                    if (!empty($w) AND strpos($search_text, $w) !== false) {
                                                        $is_system_detected = true;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                        if (!$is_system_detected AND !empty($actor_categories) AND in_array($category_obj->name_id, $actor_categories)) {
                                            $is_system_detected = true;
                                        }

                                        // Determine selection state & yellow styling
                                        if ($is_saved OR $is_system_detected) {
                                            $checked_cat_slave = 'checked="checked"';
                                            $selected_count++;

                                            if ($is_system_detected) {
                                                $card_extra_class = 'border-warning';
                                                $inline_style = 'style="background-color: #fff6d0; border: 1px solid #ffe8a1;"';
                                            } else {
                                                $card_extra_class = 'bg-white border-primary shadow-sm';
                                                $inline_style = '';
                                            }
                                        } else {
                                            $checked_cat_slave = '';
                                            $card_extra_class = 'bg-light border-light';
                                            $inline_style = '';
                                        }

                                        $items_html .= '
                                        <div class="col">
                                            <div class="p-2 rounded h-100 '.$card_extra_class.'" '.$inline_style.'>
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" '.$checked_cat_slave.' id="'.$category_obj->name_id.'" name="category_slave[]" value="'.$category_obj->name_id.'" />
                                                    <label class="form-check-label fw-bold small text-dark" for="'.$category_obj->name_id.'">'.$category_obj->de_name_value.'</label>
                                                </div>
                                                '.(!empty($category_obj->de_name_text) ? '<div class="text-muted ms-4 mt-1" style="font-size: 11px; line-height: 1.2;">'.$category_obj->de_name_text.'</div>' : '').'
                                            </div>
                                        </div>';
                                    }

                                    $badge_html = '';
                                    if ($selected_count > 0) {
                                        $badge_html = '<span class="badge bg-primary ms-2">'.$selected_count.' ausgew&auml;hlt</span>';
                                    }

                                    $site .= '
                                    <div class="accordion-item border-bottom">
                                        <h2 class="accordion-header" id="heading_'.$cat_g['id'].'">
                                            <button class="accordion-button collapsed fw-bold py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_'.$cat_g['id'].'" aria-expanded="false" aria-controls="collapse_'.$cat_g['id'].'">
                                                '.$cat_g['title'].' '.$badge_html.'
                                            </button>
                                        </h2>
                                        <div id="collapse_'.$cat_g['id'].'" class="accordion-collapse collapse" aria-labelledby="heading_'.$cat_g['id'].'" data-bs-parent="#categoryAccordion">
                                            <div class="accordion-body p-2">
                                                <div class="row row-cols-1 row-cols-md-2 g-2">
                                                    '.$items_html.'
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                                }

                                $site .= '
                                </div>
                                <script type="text/javascript">
                                    jQuery(document).ready(function() {
                                        jQuery("#categoryAccordion .accordion-button").click(function(e) {
                                            var target = jQuery(this).attr("data-bs-target");
                                            if (!target) target = jQuery(this).attr("data-target");
                                            if (target) {
                                                jQuery(target).collapse("toggle");
                                            }
                                        });
                                    });
                                </script>';
                            $site .= '
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

            $site .= '
            <!-- Zeile 2: Links Preise & Download, Rechts Darsteller & Sichtbarkeit -->
            <div class="row g-4 mb-4">
                <!-- Links: Preise & Download -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-header bg-light fw-bold py-3"><i class="bi bi-coin me-2"></i>Preise &amp; Download</div>
                        <div class="card-body">
                            <div class="edit_title fw-bold mb-1">Wieviel soll der Film kosten?</div>
                            <div class="edit_content mb-3">
                                <select id="amount_second" class="form-select mb-2" name="amount_second">';
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
                                
                                <select id="amount_second_webmaster" class="form-select mb-2" name="" style="display:none;">';
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
                                </select>
                                <small class="text-muted d-block">Preis in Cent je Sekunde f&uuml;r Streaming. 1 Coin = 1 Cent (0,01 EUR)</small>
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" name="trailer" id="trailer" />
                                <label class="form-check-label fw-bold" for="trailer">Der Film ist ein Trailer/ Vorstellungsvideo und soll den Kunden kostenlos angeboten werden.</label>
                            </div>

                            <div class="edit_title fw-bold mb-1">Darf der Film zum Download angeboten werden?</div>
                            <div class="edit_content mb-3">
                                <select class="form-select" name="as_download">';
                                    if ($movie['as_download'] == 0) {$selected = 'selected';} else {$selected = '';}
                                    $site .= ' 
                                    <option value="1"> Ja</option>
                                    <option value="0" '.$selected.'> Nein</option>
                                </select>
                                <small class="text-muted d-block mt-1">Empfohlen! Dies steigert Ihren Umsatz.</small>
                            </div>
                            
                            <div class="edit_title fw-bold mb-1">F&uuml;r wie viel Prozent Aufpreis m&ouml;chten Sie den Film als Download anbieten?</div>
                            <div class="edit_content mb-3">
                                <select class="form-select" name="amount_download">';
                                    for($i=0;$i<=150;$i++) {
                                        if (($movie['amount_download'] == $i)) {
                                            $selected = 'selected="selected"';
                                        } else {
                                            $selected = '';
                                        }

                                        $text = '';
                                        if (strval($i) == '0') {$text = '(genau so teuer wie Streaming)';}
                                        if (strval($i) == '10') {$text = '(Empfohlen)';}                    

                                        $site .= '<option '.$selected.' value="'.$i.'">+'.$i.'&percnt; '.$text.'</option>';
                                    }
                                    unset($i);
                                    $site .= '
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rechts: Darsteller & Sichtbarkeit -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-header bg-light fw-bold py-3"><i class="bi bi-person-badge me-2"></i>Darsteller &amp; Sichtbarkeit</div>
                        <div class="card-body">
                            <div class="edit_title fw-bold mb-1">Welchem Darsteller soll der Film zugeordnet werden?</div>
                            <div class="edit_content mb-3">
                                <select class="form-select" name="actor_id">';
                                    $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`='active' ORDER BY `username` ASC",__FILE__,__LINE__);
                                    if (p4c_num_rows($rs_actors) > 0) {
                                        while($actor_obj = p4c_fetch_object($rs_actors)) {
                                            if ($movie['actor_id'] == $actor_obj->id) {$selected = 'selected';} else {$selected = '';}
                                            $site .= '<option value="'.$actor_obj->id.'" '.$selected.'> '.$actor_obj->username.'</option>';
                                        }
                                    } else {
                                        $site .= '<option value="0">Bitte legen Sie zuerst ein Profil an!</option>';
                                    }
                                    $site .= ' 
                                </select>
                            </div>

                            <div class="edit_title fw-bold mb-1">Auf welcher Website soll der Film ver&ouml;ffentlicht werden?</div>
                            <div class="edit_content mb-3">
                                <select id="visible_for_website" class="form-select" name="visible_for_website">';
                                    $rs_websites = p4c_query("SELECT * FROM `sites` WHERE `partner_id`='". p4c_escape_string($merchant->partner_id())."' AND `status`='1' ORDER BY `domain` ASC;",__FILE__,__LINE__);
                                    if (p4c_num_rows($rs_websites) > 0) {
                                        while($site_obj = p4c_fetch_object($rs_websites)) {
                                            $site .= '<option value="'.$site_obj->domain.'"> '.$site_obj->domain.'</option>';
                                        }
                                    }
                                    $site .= '
                                </select>
                                <small class="text-muted d-block mt-1">Bei der Ver&ouml;ffentlichung auf Partnerwebsites erhalten Sie 25% Provision vom Umsatz dieses Films.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zeile 3: Rechts Suchmaschinenoptimierung (SEO) -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-header bg-light fw-bold py-3"><i class="bi bi-search me-2"></i>Suchmaschinenoptimierung (SEO)</div>
                        <div class="card-body">
                            <div class="edit_title fw-bold mb-1">Meta Description <span id="anzahl_meta_description" class="text-muted fw-normal">(max. 165 Zeichen)</span> <b class="text-danger">KEINE einzelnen Worte/Keywords oder Hashtags!</b></div>
                            <div class="edit_content mb-3">
                                <textarea class="form-control" rows="3" name="meta_description" placeholder="Geben Sie hier eine kurze aussagekr&auml;ftige Beschreibung des Films an. Keine Stichworte! Diese Kurzbeschreibung wird unter anderem f&uuml;r die Google-Suche verwendet." onkeyup="jQuery(this).zaehle_zeichen(156, \'anzahl_meta_description\')">'.$movie['meta_description'].'</textarea>
                                <small class="text-muted d-block mt-1">Beschreiben Sie den Film so interessant wie m&ouml;glich mit maximal 156 Zeichen.</small>
                            </div>
                            
                            <div class="edit_title fw-bold mb-1">Meta Title <span id="anzahl_meta_title" class="text-muted fw-normal">(max. 65 Zeichen)</span></div>
                            <div class="edit_content mb-3">
                                <input type="text" class="form-control" name="meta_title" value="'.$movie['meta_title'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" disabled="disabled" />
                            </div>
                           
                            <div class="edit_title fw-bold mb-1">SEO-URL (URL-Name)</div>
                            <div class="edit_content mb-3">
                                <input type="text" class="form-control" name="seo_url" value="'.$movie['seo_url'].'" placeholder="Wird nach dem Speichern automatisch ausgef&uuml;llt" readonly="readonly" disabled="disabled" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="d-flex justify-content-between align-items-center my-4 p-3 bg-light rounded border shadow-sm">
                <div>';
                    if (isset($_SESSION['upload_movie']['movie_id'])) {
                        $site .= '                                 
                        <input type="hidden" name="movie_id" value="'.$movie_id.'" />
                        <input type="submit" class="btn btn-outline-danger content_ruels" name="delete_movie" value="Film l&ouml;schen" />
                        ';
                    }
                    $site .= '
                </div>

                <div>
                    <input type="submit" class="btn btn-primary btn-lg content_ruels" name="submit_step1" value="Speichern und weiter mit Schritt 2" />
                </div>
            </div>';

            include_once(MCP_DIR.'/includes/overlays/content_rules.php');
                    
            $site .= '
        </form>
        ';} else if (isset($_GET['step']) AND $_GET['step'] == 2 AND isset($_GET['movie_id'])) {
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
' . get_upload_wizard_html(2) . '

        <style>
            #upload_file {position: absolute; cursor: pointer; top: 0px; width: 100%; height: 100%; left: 0px; z-index: 100; opacity: 0;}
            .progress { display: none; }
            .abort_upload { display: none; }
            .upload_error { display: none; }
        </style>

        <div class="row g-4 mb-4">
            <!-- Left Column: Upload Box -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100 mb-0">
                    <div class="card-header bg-light fw-bold py-3"><i class="bi bi-cloud-arrow-up me-2"></i>Film hochladen</div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 px-3 small mb-3 shadow-sm">
                            <i class="bi bi-info-circle-fill me-2"></i>Nach dem Upload k&ouml;nnen Sie den Film noch einmal bearbeiten und ein Vorschaubild ausw&auml;hlen oder ein eigenes Vorschaubild hochladen.
                        </div>

                        <div class="form-check form-switch mb-3 p-3 bg-light rounded border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="upload_movie_released" style="cursor:pointer;" /> 
                            <label class="form-check-label fw-bold text-dark" for="upload_movie_released" style="cursor:pointer;">
                                Den Film direkt nach dem Upload zur Pr&uuml;fung freigeben und in der EroCloud ver&ouml;ffentlichen.
                            </label>
                        </div>

                        <div class="p-3 bg-light rounded border mb-4 small text-muted">
                            <div class="mb-1"><i class="bi bi-file-earmark-play me-2 text-primary"></i><strong>Erlaubte Dateiformate:</strong> avi, flv, m4v, mkv, mov, mp4, mpg, wmv</div>
                            <div><i class="bi bi-hdd me-2 text-primary"></i><strong>Maximale Dateigr&ouml;&szlig;e:</strong> 2000 MB (2,0 GB)</div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <div class="upload_movie btn btn-primary btn-lg position-relative overflow-hidden px-4 shadow-sm">
                                <i class="bi bi-upload me-2"></i>Film hochladen
                                <form id="form_upload_movie" action="'.MCP_URL.'/includes/uploader/upload_movie.php?movie_id='.$movie_id.'" method="post" enctype="multipart/form-data">
                                    <input type="file" id="upload_file" name="movie" accept="video/*">
                                </form>
                            </div>
                            <button type="button" class="abort_upload btn btn-outline-danger btn-lg shadow-sm">
                                <i class="bi bi-x-circle me-1"></i>Abbrechen
                            </button>
                        </div>

                        <div class="progress mt-3 shadow-sm" style="height: 25px;">
                            <div class="bar progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;">
                                <span class="percent fw-bold text-dark">0%</span>
                            </div>
                        </div>

                        <div class="upload_error alert alert-danger mt-3 mb-0 shadow-sm"></div>
                        <div id="status" class="mt-2"></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Render Hints -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100 mb-0">
                    <div class="card-header bg-light fw-bold py-3"><i class="bi bi-gear me-2"></i>So rendern Sie Ihre Filme richtig</div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Immer wenn ein Film fertig gestellt wurde, kommt die Frage nach den richtigen Einstellungen zum Rendern.
                            Die optimale Render-Einstellung gibt es leider nicht. Mit den folgenden Einstellungen sollte es Ihnen aber gelingen, einen Film vern&uuml;nftig in mp4 zu rendern.
                        </p>
                        
                        <ul class="small mb-3 ps-3 text-secondary" style="line-height: 1.6;">
                            <li><strong>Video-Codec:</strong> AVC / H.264</li>
                            <li><strong>Audio-Codec:</strong> AAC</li>
                            <li><strong>Aufl&ouml;sung:</strong> 1920x1080 (FullHD / 1080p)</li>
                            <li><strong>Profil:</strong> Hoch</li>
                            <li><strong>Framerate:</strong> 25,000 (PAL)</li>
                            <li><strong>Scan-Typ:</strong> Progressive Scan (Keine Halbbilder)</li>
                            <li><strong>Bitrate:</strong> Variable Bitrate, 10.000.000 Bit/s</li>
                            <li><strong>Audio Bitrate:</strong> 192 kBit/s</li>
                        </ul>
                        
                        <button type="button" class="btn btn-sm btn-outline-info shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMovieTips">
                            <i class="bi bi-info-circle me-1"></i> Hinweise zur Qualit&auml;t Ihrer Filme anzeigen
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Bar -->
        <div class="d-flex justify-content-between align-items-center my-4 p-3 bg-light rounded border shadow-sm">
            <div>
                <form action="'.MCP_URL.'/Movie-Upload?step=1" method="post">
                    <input type="hidden" name="movie_id" value="'.$movie_id.'" />
                    <input type="submit" class="btn btn-outline-danger content_ruels" name="delete_movie" value="Film l&ouml;schen" />
                </form>
            </div>
        </div>';

        $site .= '
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
' . get_upload_wizard_html(3) . '
        
        <div class="card shadow-sm border-0 my-4 text-center py-5">
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle p-4" style="width: 90px; height: 90px;">
                        <i class="bi bi-check-lg display-4"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-success display-6 mb-3">Fertig!</h2>
                <p class="lead text-muted mb-4">
                    Der Film wurde erfolgreich hochgeladen und wird in K&uuml;rze konvertiert.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="'.MCP_URL.'/video/'.$movie_id.'" class="btn btn-primary btn-lg shadow-sm">
                        <i class="bi bi-pencil-square me-2"></i>Film zum Bearbeiten anzeigen
                    </a>
                    <a href="'.MCP_URL.'/Movie-Upload" class="btn btn-outline-secondary btn-lg shadow-sm">
                        <i class="bi bi-plus-lg me-2"></i>Weiteren Film hochladen
                    </a>
                </div>
            </div>
        </div>
        ';
    }
    
    $site .= '
</div>';

?>