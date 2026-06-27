<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

#$_POST = utf8decodeArray($_POST);

if (isset($_GET['logout'])) {
    if (isset($_SESSION['merchant_id'])) {
        if ($_GET['logout'] == 'auto') {
            log_action('Vom System abgemeldet. (auto)');
        } else {
            log_action('Vom System abgemeldet. (manuell)');
        }
    }
    
    if (isset($_SESSION['logged_in_as']) AND $_SESSION['logged_in_as'] == 'merchant') {
        $logout_url = LOGIN_URL.'/?logout&ref=erocloud';
    } else {
        $logout_url = MCP_URL;
    }
    
    unset($_SESSION);
    session_destroy();
    header('Location: '.$logout_url);
    exit;
}

$meta_title = '';
$meta_description = '';

$site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>{meta_title}'.PROJECTNAME.'</title>
    <meta name="{meta_description}">

    <meta charset="iso-8859-15" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    

    <script>
        var mcp_url = "'.MCP_URL.'"; 
    </script>
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/fw/DataTables/media/css/jquery.dataTables_themeroller.css" />
    <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/style.css?id=24" />
    ';
    if (isset($_GET['mod']) AND $_GET['mod']=='webmaster') {
        $site .= '<link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/webmaster.css?id=1" />';
    }

    $site .= ' 
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/global_left_menu.css?id=6" />
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/tabs.css?id=1" />

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script type="text/javascript" src="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.js"></script>
    <script type="text/javascript" src="//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="'.MCP_URL.'/js/script.js?id=6"></script>
    <script type="text/javascript" src="'.MCP_URL.'/js/global_left_menu.js?id=3"></script>
    <script type="text/javascript" src="'.MCP_URL.'/js/tabs.js?id=2"></script>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">


    <link rel="apple-touch-icon" sizes="57x57" href="'.MCP_URL.'/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="'.MCP_URL.'/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="'.MCP_URL.'/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="'.MCP_URL.'/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="'.MCP_URL.'/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="'.MCP_URL.'/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="'.MCP_URL.'/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="'.MCP_URL.'/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="'.MCP_URL.'/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="'.MCP_URL.'/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="'.MCP_URL.'/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="'.MCP_URL.'/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="'.MCP_URL.'/favicon/favicon-16x16.png">
    <link rel="manifest" href="'.MCP_URL.'/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="'.MCP_URL.'/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <meta name="google-site-verification" content="HvDFag7fjpLLg8fDB3yvJ8tUKP2W7CE3Wm9aP2kkADE" />

    ';
    if (isset($_GET['mod']) AND $_GET['mod'] == 'studiologin') {
        $site .= '<script src="https://www.google.com/recaptcha/api.js?render='.RECAPTCHA_SITEKEY.'"></script>';
    }
    $site .= '
</head>
<body>
';
    if (is_logged_in('mcp') === true) {
        
        $merchant = new Merchant($mysql,$_SESSION['merchant_id']);
        
        $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`!='deleted' ORDER BY `username` ASC;",__FILE__,__LINE__);
        $count_actors = p4c_num_rows($rs_actors);
        
        $site .= '
        <div id="mcp">
            <div class="ui-widget-header" style="border-top:none; border-left:none; border-right:none; padding:5px 10px; font-weight:normal;">
                <table>
                    <tr>
                        <td style="padding:5px 0 5px 10px;">
                            <img src="'.MCP_URL.'/erocloud_logo.png" alt="" style="width:auto; height:40px;" />
                        </td>
                        <td style="padding:5px 0 5px 30px;">
                            <div style="font-size:20px;">Meine EroCloud</div>
                            Eingeloggt als: '.$_SESSION['merchant_username'].' - <b><a href="'.MCP_URL.'/index.php?logout">Logout</a></b><br />
                        </td>
                    </tr>
                </table>
            </div>

            <table style="width:100%">
                <tr>
                    <td class="ui-widget-content left_menu">';
                        $navHome = '';
                        $navAmoredea = '';
                        $navAmoredeaComments = '';
                        $navAmoredeaMessages = '';
                        $navMovieUpload = '';
                        $navMovies = '';
                        $navPhotoAlbumUpload = '';
                        $navPhotoAlbums = '';
                        $navMessenger = '';
                        $navGroups = '';
                        $navStatistics = '';
                        $navStatistics_total_sales = '';
                        $navStatistics_actors_sales = '';
                        $navHotline = '';
                        $navComments = '';
                        $navActors = '';
                        $navActor = '';
                        $navActorNew = '';
                        $navCoActor = '';
                        $navFAQ = '';
                        $navWebmaster = '';
                        $navWebmasterAds = '';
                        $navWebmasterNewAds = '';
                        $navWebmasterStats = '';
                        $navWebmasterAdPartner = '';
                        
                        if (isset($_GET['mod']) AND $_GET['mod']=='home') {
                            $navHome = 'active';
                            $main = 'home.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='movie_upload' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navMovieUpload = 'active';
                            $main = 'movie_upload.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='movies' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navMovies = 'active';
                            $main = 'movies.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='movie' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navMovies = 'active';
                            $main = 'movie.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='photo_album_upload' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navPhotoAlbumUpload = 'active';
                            $main = 'photo_album_upload.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='photo_albums' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navPhotoAlbums = 'active';
                            $main = 'photo_albums.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='photo_album' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navPhotoAlbums = 'active';
                            $main = 'photo_album.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='groups' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navGroups = 'active';
                            $main = 'groups.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='statistics_total_sales' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navStatistics_total_sales = 'active';
                            $main = 'statistics.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='statistics_actors_sales' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navStatistics_actors_sales = 'active';
                            $main = 'statistics/statistics_actors_sales.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='statistics_actor_sales' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navStatistics_actors_sales = 'active';
                            $main = 'statistics/statistics_actor_sales.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='statistics_actor_sales_day' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navStatistics_actors_sales = 'active';
                            $main = 'statistics/statistics_actor_sales_day.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='hotline' AND $_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                            $navHotline = 'active';
                            $main = 'hotline.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_ads' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterAds = 'active';
                            $main = 'webmaster_ads.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_ads_domain' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterAds = 'active';
                            $main = 'webmaster_ads_domain.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_newads' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterNewAds = 'active';
                            $main = 'webmaster_newads.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_edit_campaign' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterAds = 'active';
                            $main = 'webmaster_edit_campaign.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_new_campaign' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterAds = 'active';
                            $main = 'webmaster_new_campaign.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_stats' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterStats = 'active';
                            $main = 'webmaster_stats.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster_advertise_partner' AND $_SESSION['logged_in_as'] == 'merchant') {
                            $navWebmasterAdPartner = 'active';
                            $main = 'webmaster_advertise_partner.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='actors') {
                            $navActors = 'active';
                            $main = 'actors.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='actor' AND $count_actors > 0) {
                            $navActors = 'active';
                            $main = 'actor.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='co_actors') {
                            $navCoActors = 'active';
                            $main = 'co_actors.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='co_actor') {
                            $navCoActors = 'active';
                            $main = 'co_actor.php';

                        } else if (isset($_GET['mod']) AND $_GET['mod']=='co_actor_create') {
                            $navCoActors = 'active';
                            $main = 'co_actor_create.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='amoredea_comments' AND $count_actors > 0) {
                            $navAmoredeaComments = 'active';
                            $main = 'amoredea/comments.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='amoredea_messages' AND $count_actors > 0) {
                            $navAmoredeaMessages = 'active';
                            $main = 'amoredea/messages.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='new_actor' AND $count_actors < $merchant->minimum_number_actor_profiles()) {
                            $navActorNew = 'active';
                            $main = 'new_actor.php';
                            
                        } else if (isset($_GET['mod']) AND $_GET['mod']=='faq') {
                            $navFAQ = 'active';
                            $main = 'faq.php';
                            
			} else {
                            header('Location: '.MCP_URL.'/Startseite');
                            exit;
                        }
                        
                        $site .= '
                        <a class="'.$navHome.'" href="'.MCP_URL.'/Startseite">
                            <i class="material-symbols-outlined md-30">home</i>
                            <span>Meine Startseite</span>
                        </a>
                        ';
                        
                        if ($count_actors > 0) {
                            
                            #SELECT * FROM `group_actors` WHERE `group_id` = '".abs($_SESSION['my_chatgroup'])."' ORDER BY `actor_id` DESC
                            if ($_SESSION['logged_in_as'] == 'merchant') {
                                $rs_count_chats = p4c_query("SELECT * FROM `group_actors`, `chat_messages`, `actors` WHERE
                                    `group_actors`.`actor_id`=`chat_messages`.`an_id` AND
                                    `actors`.`id`=`chat_messages`.`an_id` AND
                                    `group_actors`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                                    `chat_messages`.`von`='member' AND
                                    `chat_messages`.`gelesen` != '1' AND
                                    (`actors`.`status`='active' OR `actors`.`status`='inactive')
                                GROUP BY `chat_messages`.`chat_id` ASC;",__FILE__,__LINE__);
                            } else {
                                $rs_count_chats = p4c_query("SELECT * FROM `group_actors`, `chat_messages`, `actors` WHERE
                                    `group_actors`.`actor_id`=`chat_messages`.`an_id` AND
                                    `actors`.`id`=`chat_messages`.`an_id` AND
                                    `group_actors`.`group_id` = '".abs($_SESSION['my_chatgroup'])."' AND
                                    `group_actors`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                                    `chat_messages`.`von`='member' AND
                                    `chat_messages`.`gelesen` != '1' AND
                                    (`actors`.`status`='active' OR `actors`.`status`='inactive')
                                GROUP BY `chat_messages`.`chat_id` ASC;",__FILE__,__LINE__);                                
                            }
                               
                            $count_chats = p4c_num_rows($rs_count_chats);

                            $number_of_chats = '';
                            if ($count_chats > 0) {
                                $number_of_chats = ' <b>('.$count_chats.')</b>';
                            }

                            $site .= '
                            <a onclick="jQuery(this).checkMessengerIsOpen(\''.MCP_URL.'/Messenger\');return false;" href="javascript:;" target="EroMessenger">
                                <i class="material-symbols-outlined md-30">forum</i>
                                <span>Messenger'.$number_of_chats.'</span>
                            </a>
                            
                            <a class="'.$navHotline.'" href="'.MCP_URL.'/Hotline">
                                <i class="material-symbols-outlined md-30">phone</i>
                                <span>09005-Hotline</span>
                            </a>';                            

                        }

                        if ($_SESSION['logged_in_as'] == 'merchant') {
                            if ($count_actors > 0) {

                                if (isset($_GET['test'])) {
                                $site .= '
                                <a class="'.$navAmoredea.' submenu" data-submenu="submenu_amoredea">
                                    <i class="material-symbols-outlined arrow">arrow_right</i>
                                    <span><span class="amoredea_icon">a</span> amoredea</span>
                                </a>

                                <div class="submenu_amoredea">
                                    <a class="'.$navAmoredeaComments.'" href="'.MCP_URL.'/amoredea/Comments">
                                        <i class="material-symbols-outlined md-30">notifications_none</i>
                                        <span>Kommentare</span>
                                    </a>
                                </div>
                                
                                <div class="submenu_amoredea">
                                    <a class="'.$navAmoredeaMessages.'" href="'.MCP_URL.'/amoredea/Messages">
                                        <i class="material-symbols-outlined md-30">forum</i>
                                        <span>Nachrichten</span>
                                    </a>
                                </div>';
                                
                                if (isset($_GET['mod']) AND ($_GET['mod'] == 'comments')) {
                                    $site .= '
                                    <script>
                                        jQuery(document).ready(function() {
                                            jQuery(this).open_submenu("submenu_amoredea");
                                        })
                                    </script>';
                                }
                                
                                }
                                
                                $rs_movies = p4c_query("SELECT `id`, `convert_status`, `filename`, `title`, `movie_checked`, `released` FROM `movies` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC", __FILE__, __LINE__);
                                $count_movies = p4c_num_rows($rs_movies);
                                
                                $rs_albums = p4c_query("SELECT * FROM `photo_albums` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC", __FILE__, __LINE__);
                                $count_albums = p4c_num_rows($rs_albums);
                                
                                $site .= '
                                <a class="submenu" data-submenu="submenu_upload" href="javascript:;">
                                    <i class="material-symbols-outlined arrow">arrow_right</i>
                                    <i class="material-symbols-outlined md-30">cloud_upload</i>
                                    <span>Content hochladen</span>
                                </a>

                                <div class="submenu_upload">
                                    <a class="'.$navMovieUpload.'" href="'.MCP_URL.'/Movie-Upload">
                                        <i class="material-symbols-outlined md-30">add_box</i>
                                        <span>Film hochladen</span>
                                    </a>

                                    <a class="'.$navPhotoAlbumUpload.'" href="'.MCP_URL.'/Photo-Album-Upload">
                                        <i class="material-symbols-outlined md-30">add_photo_alternate</i>
                                        <span>Fotos hochladen</span>
                                    </a>
                                </div>

                                <a class="submenu" data-submenu="submenu_online" style="position:relative;">
                                    <div id="count_content_online">'.$count_movies.'</div>
                                    <i class="material-symbols-outlined arrow">arrow_right</i>
                                    <i class="material-symbols-outlined md-30">cloud</i>
                                    <span>Content in der Cloud</span>
                                </a>

                                <div class="submenu_online">
                                    <a class="'.$navMovies.'" href="'.MCP_URL.'/Movies">
                                        <i class="material-symbols-outlined md-30">video_library</i>
                                        <span>Filme in der Cloud</span>
                                    </a>

                                    <a class="'.$navPhotoAlbums.'" href="'.MCP_URL.'/Photo-Albums">
                                        <i class="material-symbols-outlined md-30">photo_library</i>
                                        <span>Fotos in der Cloud</span>
                                    </a>
                                </div>
                                ';

                                if (isset($_GET['mod']) AND ($_GET['mod'] == 'photo_album_upload' OR $_GET['mod'] == 'movie_upload')) {
                                    $site .= '
                                    <script>
                                        jQuery(document).ready(function() {
                                            jQuery(this).open_submenu("submenu_upload");
                                        })
                                    </script>';
                                }

                                if (isset($_GET['mod']) AND ($_GET['mod'] == 'movie' OR $_GET['mod'] == 'movies' OR $_GET['mod'] == 'photo_album' OR $_GET['mod'] == 'photo_albums' )) {
                                    $site .= '
                                    <script>
                                        jQuery(document).ready(function() {
                                            jQuery(this).open_submenu("submenu_online");
                                        })
                                    </script>';
                                }

                            }
                            
                            
                            if ($count_actors > 0 AND isset($_GET['test'])) {
                                $site .= '
                                <a class="'.$navCoActors.'" href="'.MCP_URL.'/CoActors">
                                    <i class="material-symbols-outlined md-30">id_card</i>
                                    <span>Drehpartner</span>
                                </a>';
                            }
                            
                            /*
                            $site .= '
                            <a class="'.$navActors.'" href="'.MCP_URL.'/Actors">
                                <i class="material-symbols-outlined md-30">account_circle</i> <span>Profile
                            </a>
                            ';
                            */
                            
                            if (isset($_GET['mod']) AND ($_GET['mod'] == 'actors' OR $_GET['mod'] == 'actor') OR $_GET['mod'] == 'new_actor') {
                                $site .= '
                                <script>
                                    jQuery(document).ready(function() {
                                        jQuery(this).open_submenu("submenu_actors");
                                    })
                                </script>';
                            }
                            
                            $site .= '
                            <a class="'.$navActors.' submenu" data-submenu="submenu_actors">
                                <i class="material-symbols-outlined arrow">arrow_right</i>
                                <i class="material-symbols-outlined md-30">account_circle</i>
                                <span>Profile</span>
                            </a>

                            <div class="submenu_actors">';
                                if ($count_actors > 1) {
                                    $site .= '
                                    <a class="'.$navActorNew.'" href="'.MCP_URL.'/Actors">
                                        <i class="material-symbols-outlined md-30">supervised_user_circle</i>
                                        <span>Alle Profile anzeigen</span>
                                    </a>';
                                }

                                if ($count_actors < $merchant->minimum_number_actor_profiles()) {
                                    $site .= '
                                    <a class="'.$navActorNew.'" href="'.MCP_URL.'/New-Actor">
                                        <i class="material-symbols-outlined md-30">person_add</i>
                                        <span>Profil anlegen</span>
                                    </a>';
                                }

                                if ($count_actors > 0) {
                                    while($actor_obj = p4c_fetch_object($rs_actors)) {
                                        $avatar = MCP_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16;
                                        if (isset($_GET['id']) AND $actor_obj->id == $_GET['id']) {$navActor = 'active';} else {$navActor='';}
                                        $site .= ' 
                                        <a class="'.$navActor.'" href="'.MCP_URL.'/Actor/'.$actor_obj->id.'">
                                            <div class="avatar"><img src="'.$avatar.'" alt="" /></div> '.$actor_obj->username.'
                                        </a>';
                                    }                                                    
                                }
                            $site .= ' 
                            </div>';
                            
                            if ($count_actors > 0) {
                                $site .= '
                                <a class="'.$navGroups.'" href="'.MCP_URL.'/Groups">
                                    <i class="material-symbols-outlined md-30">group</i>
                                    <span>Gruppen</span>
                                </a>';
                                
                                if (isset($_GET['mod']) AND (
                                    $_GET['mod'] == 'statistics_actors_sales' OR
                                    $_GET['mod'] == 'statistics_actor_sales' OR
                                    $_GET['mod'] == 'statistics_actor_sales_day' OR
                                    $_GET['mod'] == 'statistics_total_sales'
                                )) {
                                    $site .= '
                                    <script>
                                        jQuery(document).ready(function() {
                                            jQuery(this).open_submenu("submenu_statistics");
                                        })
                                    </script>';
                                }                                

                                
                                $site .= '
                                <a class="'.$navStatistics.' submenu" data-submenu="submenu_statistics">
                                    <i class="material-symbols-outlined arrow">arrow_right</i>
                                    <i class="material-symbols-outlined md-30">monetization_on</i>
                                    <span>Ums&auml;tze</span>
                                </a>

                                <div class="submenu_statistics">
                                    <a class="'.$navStatistics_total_sales.'" href="'.MCP_URL.'/Statistics/TotalSales">
                                        <i class="material-symbols-outlined md-30">monetization_on</i>
                                        <span>nach Tage</span>
                                    </a>
                                    <a class="'.$navStatistics_actors_sales.'" href="'.MCP_URL.'/Statistics/TotalActorsSales">
                                        <i class="material-symbols-outlined md-30">group</i>
                                        <span>nach Profile</span>
                                    </a>
                                </div>';
                                
                            }
                            
                            if (isset($_GET['mod']) AND (
                                $_GET['mod'] == 'webmaster_newads' OR
                                $_GET['mod'] == 'webmaster_ads' OR
                                $_GET['mod'] == 'webmaster_ads_domain' OR
                                $_GET['mod'] == 'webmaster_stats') OR
                                $_GET['mod'] == 'webmaster_new_campaign' OR
                                $_GET['mod'] == 'webmaster_edit_campaign' OR
                                $_GET['mod'] == 'webmaster_advertise_partner'
                            ) {
                                $site .= '
                                <script>
                                    jQuery(document).ready(function() {
                                        jQuery(this).open_submenu("submenu_webmaster");
                                    })
                                </script>';
                            }

                            $site .= '
                            <a class="'.$navWebmaster.' submenu" data-submenu="submenu_webmaster">
                                <i class="material-symbols-outlined arrow">arrow_right</i>
                                <i class="material-symbols-outlined md-30">attach_money</i>
                                <span>Partnerprogramm</span>
                            </a>

                            <div class="submenu_webmaster">
                                <a class="'.$navWebmasterAds.'" href="'.MCP_URL.'/Webmaster/Ads">
                                    <i class="material-symbols-outlined md-30">layers</i>
                                    <span>Kunden werben</span>
                                </a>
                                <a class="'.$navWebmasterNewAds.'" href="'.MCP_URL.'/Webmaster/NewAds">
                                    <i class="material-symbols-outlined md-30">layers</i>
                                    <span>alle Banner</span>
                                </a>
                                <a class="'.$navWebmasterStats.'" href="'.MCP_URL.'/Webmaster/Statistics">
                                    <i class="material-symbols-outlined md-30">show_chart</i>
                                    <span>Statistiken</span>
                                </a>
                                <a class="'.$navWebmasterAdPartner.'" href="'.MCP_URL.'/Webmaster/Advertise-Partner">
                                    <i class="material-symbols-outlined md-30">layers</i>
                                    <span>Darsteller werben</span>
                                </a>
                            </div>';

                            
                            
                            $site .= ' 
                            <div style="margin-top:15px; text-align:center; color:rgba(0,0,0,.38);">
                                Abrechnungen und Meine Daten 
                            </div>

                            <div style="margin-top:3px; padding-top:10px; border-top:1px solid rgba(0,0,0,.10); color:rgba(0,0,0,.38);">
                                <a href="'.Pay4Coins_MCP_URL.'" target="_blank">
                                    <img src="'.Pay4Coins_MCP_URL.'/pay4coins_logo.svg" />
                                </a>                            
                            </div>';
                            
                        }
                         
                        $site .= '
                        <div id="support" style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(0,0,0,.10); color:rgba(0,0,0,.38);">
                            <a class="'.$navFAQ.'" href="'.MCP_URL.'/faq">
                                <i class="material-symbols-outlined md-30">help</i>
                                <span>FAQ\'s</span>
                            </a>';
                        
                            if ($_SESSION['logged_in_as'] == 'merchant') {
                                $site .= '
                                <a class="api_key" href="javascript:;">
                                    <i class="material-symbols-outlined md-30">vpn_key</i>
                                    <span>Mein API-Key</span>
                                </a>';
                            }
                            
                            $site .= ' 
                            <a class="contact_footer" href="javascript:;">
                                <i class="material-symbols-outlined md-30">contact_support</i>
                                <span>Support & Kontakt</span>
                            </a>
                            <a class="imprint" href="javascript:;">
                                <i class="material-symbols-outlined md-30">info</i>
                                <span>Impressum</span>
                            </a>
                        </div>
                        
                        <div style="margin-top:10px; padding:10px; border-top:1px solid rgba(0,0,0,.10); color:rgba(0,0,0,.38);">
                            <center>'.PROJECTNAME.' ist ein Service der<br/>'.COMPANYNAME.'</center>
                        </div>

                    </td>
                    <td style="vertical-align:top; padding:10px;">';
                        if (file_exists(MCP_DIR.'/includes/'.$main)) {
                            include_once(MCP_DIR.'/includes/'.$main);
                        } elseif (file_exists(MCP_DIR.'/includes/home.php')) {
                            include_once(MCP_DIR.'/includes/home.php');
                        } else {
                            $site = 'Die Seite "'.$main.'" konnte nicht gefunden werden!';
                        }
                    $site .= '
                    </td>
                </tr>        
            </table>
        </div>
        
        <div class="api_key_popup">
            <div style="text-align:right; top:20px; right:10px; position:absolute;">
                <a href="javascript:;" class="close_overlay"><b>&#x2715;</b></a>
            </div>
            <div style="font-size:25px; margin-bottom:20px;">Mein '.PROJECTNAME.'-API-Key</div>
            <div class="edit_content" style="font-size:20px; padding:10px; margin-bottom:20px; border-top:none;">
                <input id="api_key" style="font-size:20px; padding:5px; font-family:curier new; text-align:center;" type="text" value="'.$merchant->api_key('aes_decrypt').'" readonly />
                <div style="margin-top:5px; color:#ff0000; text-align:center;">
                    Behandeln Sie den API-Key wie die Pin f&uuml;r Ihr Bankkonto.
                </div>
                <div style="margin-top:15px;">
                    Der API-Key erm&ouml;glicht es dir, Darsteller und deren Content aus der EroCloud auf deiner eigene Webseite zu importieren.<br />
                    <br />
                    Mehr Infos zum Import von Filmen auf deine eigene Webseite, bekommst du hier:<br />
                    <a hreF="https://pay4coins.net/2018/07/11/erocloud-wie-importiere-ich-darsteller-auf-meine-webseite/" target="_blank">https://pay4coins.net/2018/07/11/erocloud-wie-importiere-ich-darsteller-auf-meine-webseite/</a>
                </div>
            </div>

            <div style="text-align:right; float:right;">
                <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
            </div>
        </div>

        ';
    } else {
        
        $site .= ' 
        <div id="site_header" class="ui-widget-header" style="border-top:none; border-left:none; border-right:none; padding:10px 20px; font-weight:normal;">
            <div style="margin:0 auto;">
                <table style="width:100%;">
                    <tr>
                        <td style="width:60px;">
                            <a href="'.MCP_URL.'"><img src="'.MCP_URL.'/erocloud_logo.png" alt="" style="width:auto; height:40px;" /></a>
                        </td>
                        <td style="width:auto; padding:5px 0 5px 30px; font-size:20px;">Meine EroCloud</td>
                        <td style="text-align:right;">
                            <i title="Partnerseiten" id="headline_apps" class="material-symbols-outlined">apps</i>
                            <div id="partner_sites" class="overlay-box_shadow ui-widget-content">
                                <div>
                                    <a href="'.MCP_URL.'">
                                        <img src="'.MCP_URL.'/erocloud_logo.png" alt="EroCloud" />
                                    </a>
                                </div>
                                
                                <div>
                                    <a href="'.Pay4Coins_URL.'" target="_blank">
                                        <img src="'.Pay4Coins_MCP_URL.'/pay4coins_logo.svg" alt="Pay4Coins" />
                                    </a>
                                </div>

                                <div>
                                    <a href="http://erocms.net" target="_blank">
                                        <img src="https://erocms.net//templates/default/images/logo.png" alt="EroCMS" />
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        ';
        
        if (isset($_GET['mod']) AND $_GET['mod']=='home') {
            include_once(MCP_DIR.'/includes/home_loggetout.php');
            
        } else if (isset($_GET['mod']) AND $_GET['mod']=='studiologin') {
            include_once(MCP_DIR.'/includes/login.php');
        
        } else if (isset($_GET['mod']) AND $_GET['mod']=='webmaster') {
            include_once(MCP_DIR.'/includes/home_webmaster.php');
            
        } else {
            header('Location: '.MCP_URL.'/Startseite');
            exit;
        }
        
        $site .= '  
        <footer>
            <div class="ui-widget-header">
                <div style="max-width:840px; margin:0 auto; padding:15px 10px; text-align:center;">
                    <div>'.PROJECTNAME.' ist ein Service der '.COMPANYNAME.' <span id="support">- <a class="contact_footer" href="javascript:;">deutscher & englischer Support</a> - <a class="imprint" href="javascript:;">Impressum</a></span></div>
                </div>
            </div>
        </footer>';        
    }
    
    $site .= '
	
    <div class="help_popup">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b></a>
        </div>';        
                include_once(MCP_DIR.'/includes/overlays/help.php');
		$site .= '
        <div style="text-align:right; float:right;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
        </div>
    </div>
	
    <div class="imprint_popup">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b></a>
        </div>';        
        include_once(MCP_DIR.'/includes/overlays/imprint.php');
		$site .= '
        <div style="text-align:right; float:right;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
        </div>
    </div>

    <div class="clicks_popup">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="javascript:;" class="close_overlay" style="width:35px; padding:0px; text-align:center;"><b>&#x2715;</b></a>
        </div>
        <div id="clicks_popup_content"></div>
        <div style="text-align:right; float:right;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
        </div>
    </div>
    
    <div class="impressions_popup">
        <div style="text-align:right; top:20px; right:10px; position:absolute;">
            <a href="javascript:;" class="close_overlay" style="width:35px; padding:0px; text-align:center;"><b>&#x2715;</b></a>
        </div>
        <div id="impressions_popup_content"></div>
        <div style="text-align:right; float:right;">
            <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
        </div>
    </div>

    <div id="overlay"></div>
    ';
                
                
    if (is_logged_in('mcp') === true) {
        if ($_GET['mod'] == 'photo_album_upload' OR $_GET['mod'] == 'photo_album') {
            $site .= ' 
                
            <link rel="stylesheet" href="'.MCP_URL.'/css/photo_albums.css" />
            <link rel="stylesheet" href="'.MCP_URL.'/fw/colorbox/colorbox.css" />
                
            <script type="text/javascript" src="'.MCP_URL.'/js/jquery.form.js"></script>
            <script type="text/javascript" src="'.MCP_URL.'/js/photo_albums.js"></script>
            <script type="text/javascript" src="'.MCP_URL.'/fw/colorbox/jquery.colorbox.js"></script>

            <script type="text/javascript">
            <!--
                jQuery.noConflict();

                jQuery(document).ready(function(){
                    jQuery(".zoom_goup, .img_group_preview").colorbox({photo:true, rel:"zoom_goup", maxWidth:"100%", width:"auto", maxHeight:"80%"});
                    
                    ';
                    if (isset($_GET['to_big'])) {
                        $site .= '
                            jQuery(".upload_images_error").show().html("Die Datei '.htmlspecialchars(strip_tags($_GET['to_big'])).'  ist zu gro&szlig;.");
                        ';
                    }

                    $site .= ' 
                    jQuery(".delete_picture").click(function() {
                        var photo_id = jQuery(this).attr("data-photo-id");
                        var album_id = jQuery(this).attr("data-album-id");

                        var parent = jQuery(this).parent();
                        var loader = jQuery(".loader", jQuery(parent).parent());
                        var zoom = jQuery(".zoom", jQuery(parent).parent());
                            
                        loader.show();    
                        zoom.hide();

                        jQuery.ajax({
                            url: mcp_url+"/Ajax/delete_album_photo.php",
                            data: "photo_id="+photo_id,
                            method: "POST",
                            dataType: "json",
                            async: true,
                            success: function (data) {
                                if (data.error) {
                                    loader.hide();
                                    zoom.show();
                                    alert(data.error);
                                } else {
                                    window.location.href="'.MCP_URL.'/Photo-Album-Upload?step=2&album_id="+album_id;
                                }
                            }
                        })

                    })
                })
            //-->
            </script>';
           
        } else {
            
            $site .= ' 
            <script type="text/javascript">
            <!--
                jQuery(document).ready(function(){
                ';
                if (isset($_POST['activeTabId']) OR isset($_GET['activeTabId'])) {
                    if (isset($_POST['activeTabId'])) {
                        $site .= ' 
                        jQuery(".tabs ul li").removeClass("current");
                        jQuery(".tabs .tab-content").removeClass("current");

                        jQuery(".tabs ul li[data-tab=tab-'.abs($_POST['activeTabId']).']").addClass("current");
                        jQuery(".tabs #tab-'.abs($_POST['activeTabId']).'").addClass("current");
                        ';   
                    } else if (isset($_GET['activeTabId'])) {
                        $site .= ' 
                        jQuery(".tabs ul li").removeClass("current");
                        jQuery(".tabs .tab-content").removeClass("current");

                        jQuery(".tabs ul li[data-tab=tab-'.abs($_GET['activeTabId']).']").addClass("current");
                        jQuery(".tabs #tab-'.abs($_GET['activeTabId']).'").addClass("current");
                        ';              
                    }
                }
                $site .= '
                })
            //-->
            </script>'; 
        }
    }
    
    $site .= ' 
        
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-9606436-40"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag("js", new Date());

        gtag("config", "UA-9606436-40");
    </script>

</body>
</html>';

if (!empty($meta_title)) {$meta_title = $meta_title.' - ';}
    
$site = str_replace('{meta_title}', $meta_title, $site);
$site = str_replace('{meta_description}', $meta_description, $site);


echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>