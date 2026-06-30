<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
  
define('SAFE_INC', 1);

include_once("../config.inc.php");

include_once(ACP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

#$_POST = utf8decodeArray($_POST);

if (isset($_GET['logout'])) {
    if (isset($_SESSION['employee_id'])) {
        if ($_GET['logout'] == 'auto') {
            log_action('Vom System abgemeldet. (auto)');
        } else {
            log_action('Vom System abgemeldet. (manuell)');
        }
    }
    unset($_SESSION);
    session_destroy();
    header('Location: '.ACP_URL.'/Startseite');
    exit;
}

$site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>ACP - '.COMPANYNAME.'</title>
    <meta charset="iso-8859-15" />

    <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-16x16.png" sizes="16x16">
    <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="'.URL.'/images/favicons/favicon-96x96.png" sizes="96x96">

    <link type="text/css" rel="Stylesheet" href="'.ACP_URL.'/includes/frameworks/jquery-ui/jquery-ui.min.css" />

    <link type="text/css" rel="Stylesheet" href="'.ACP_URL.'/includes/frameworks/colorbox/colorbox.css" />
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script type="text/javascript" src="'.ACP_URL.'/includes/frameworks/jquery-ui/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />

    <script type="text/javascript" src="'.ACP_URL.'/includes/frameworks/DataTables/media/js/jquery.dataTables.min.js"></script>
    <link type="text/css" href="'.ACP_URL.'/includes/frameworks/DataTables/media/css/jquery.dataTables_themeroller.css" rel="Stylesheet" />

    <link type="text/css" rel="Stylesheet" href="'.ACP_URL.'/css/site_actor.css?v=1" />
    <link type="text/css" rel="Stylesheet" href="'.ACP_URL.'/css/style.css?t=1" />
    <link type="text/css" rel="Stylesheet" href="'.MCP_URL.'/css/global_left_menu.css?=1" />
    
    <script type="text/javascript" src="'.ACP_URL.'/includes/frameworks/colorbox/jquery.colorbox.js"></script>

    <script src="https://vjs.zencdn.net/6.6.3/video.js"></script>
    <script src="'.MCP_URL.'/Messenger/js/videojs-contrib-hls.js"></script>
        
    <script type="text/javascript" src="'.ACP_URL.'/js/script.js?id=1"></script>
    <script type="text/javascript" src="'.MCP_URL.'/js/global_left_menu.js?id=1"></script>

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
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="'.MCP_URL.'/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
</head>
<body>';
    
    if (is_logged_in('acp') === true) {
		
        // Land in dem der User die Vorkasse ausgelöst hat
        #$dbip = new IP2Country($mysql);
        #$ip = '94.219.178.92';
        #$ip = getUserIP();
        #$inf = $dbip->Lookup($ip);
        #echo $inf->country;
        
        $rs_employee = p4c_query("SELECT * FROM `employee` WHERE `id`='".abs($_SESSION['employee_id'])."' LIMIT 1;");
        $employee = p4c_fetch_object($rs_employee);
        
        $site .= '
        <script type="text/javascript">
        <!--
            jQuery.noConflict();
            
            jQuery(document).ready(function() {
                var Zeit = '.(time()-ACP_SESSION_DURATION).';
                jQuery.fn.logout_time = function() {
                    if (Zeit >= 0) {
                        
                        var i = Zeit--;
                        var seconds = i%60;
                        var minutes = (i-seconds)/60;
                        if (seconds < 10) {seconds = "0"+seconds;}
                        if (minutes == 0) {
                            minutes = "";
                        } else if (minutes == 1) {
                            minutes = "einer Minute und ";
                        } else {
                            minutes = minutes+" Minuten und ";
                        }
                        
                        var text = "in "+minutes+seconds+" Sekunden.";
                        
                        jQuery("#logout_time").html(text);
                        setTimeout("jQuery(this).logout_time()", 1000);
                    } else {
                        window.location.href="'.ACP_URL.'/index.php?logout=auto";
                    }
                }
                jQuery(this).logout_time(); 

                ';
                if (isset($_GET['mod']) AND $_GET['mod']=='home') {
                    $site .= ' 
                    jQuery.post("'.ACP_URL.'/includes/ajax/update_user_stats.php");';
                }
                $site .= '
            })
    
        -->    
        </script>

        <div class="ui-widget-header" style="border-top:none; border-left:none; border-right:none; padding:5px 10px; font-weight:normal;">
            <table>
                <tr>
                    <td style="padding:5px 0 5px 10px;">
                        <img src="'.MCP_URL.'/erocloud_logo.png" alt="" style="width:auto; height:40px;" />
                    </td>
                    <td style="padding:5px 0 5px 30px;">
                        <div style="margin-bottom:5px;">Eingeloggt als: '.$_SESSION['employee_username'].'</div>
                        <div>Automatischer <b><a href="'.ACP_URL.'/index.php?logout">Logout</a></b> <span id="logout_time"></span></div>
                    </td>
                </tr>
            </table>
        </div>
        
        <table style="width:100%">
            <tr>
                <td class="ui-widget-content left_menu">';
                    
                    $rs_movies_checking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `movie_checked`='000-00-00 00:00:00' AND `released`='1' AND `convert_status` > '1';", __FILE__, __LINE__);
                    $count_movies_checking = p4c_fetch_object($rs_movies_checking)->cnt;

                    $rs_movies_blocked = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` > '1' AND (`released`='2' OR `status`!='active');", __FILE__, __LINE__);
                    $count_movies_blocked = p4c_fetch_object($rs_movies_blocked)->cnt;

                    $rs_photo_albums_checking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `album_checked`='000-00-00 00:00:00' AND `released`='1';", __FILE__, __LINE__);
                    $count_photo_albums_checking = p4c_fetch_object($rs_photo_albums_checking)->cnt;
                    
                    $count_content_checking = $count_movies_checking + $count_photo_albums_checking;

                    $rs_movies_online = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_online`;", __FILE__, __LINE__);
                    $count_movies_online = p4c_fetch_object($rs_movies_online)->cnt;

                    $rs_movies_drafts = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released`='0' AND `status`!='deleted';", __FILE__, __LINE__);
                    $count_movies_drafts = p4c_fetch_object($rs_movies_drafts)->cnt;
                    
                    $rs_photo_albums_online = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_online`;", __FILE__, __LINE__);
                    $count_photo_albums_online = p4c_fetch_object($rs_photo_albums_online)->cnt;
                    
                    $count_content_online = $count_movies_online + $count_photo_albums_online;
                    
                    $rs_count_new_banners = p4c_query("SELECT COUNT(*) AS `cnt` FROM `ads_media` WHERE `new_filename`!='' AND `rejected`='0';", __FILE__, __LINE__);
                    $count_new_banners = p4c_fetch_object($rs_count_new_banners)->cnt;
                    
                    $rs_actor_cams = p4c_query("SELECT `streamserver_url`, `stream_id`, `username`, `actor_id` FROM `actor_cams` LEFT JOIN `actors` ON
                        `actor_cams`.`actor_id`=`actors`.`id` WHERE
                        `datetime` >= '".date("Y-m-d H-i-s", strtotime("-80 seconds"))."';",__FILE__,__LINE__);

                    $rs_count_new_actors = p4c_query("SELECT COUNT(*) AS `cnt` FROM `actors` WHERE `created_datetime`>='".date("Y-m-d H:i:s",strtotime("-5 days"))."' AND `status`='inactive';",__FILE__,__LINE__);
                    $count_new_actors = p4c_fetch_object($rs_count_new_actors)->cnt;
                    $count_new_actors_hmtl = '';
                    if ($count_new_actors > 0) {
                        $count_new_actors_hmtl = ' <span style="color: coral;" title="'.$count_new_actors.' Profile m&uuml;ssen gepr&uuml;ft werden.">('.$count_new_actors.')</span>';
                    }

                    $navHome = '';
                    $navMerchants = '';
                    $navProfiles = '';
                    $navMovies = '';
                    $navMoviesOnline = '';
                    $navMoviesBlocked = '';
                    $navFotoalben = '';
                    $navFotoalbenOnline = '';
                    $navWebcams = '';
                    $navSites = '';
                    $navContentCleanup = '';
                    $navMoviesDrafts = '';
                    $navWMPartner = '';
                    $navBanners = '';
                    $navNewBanners = '';
                    $navUsers = '';
                    $navAllChats = '';
                    $navPointssystem = '';
                    $navErrors = '';
                    
                    
                    if (isset($_GET['mod']) AND $_GET['mod']=='home') {
                        $navHome = 'class="active"';
                        $main = 'home.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='actors') {
                        $navProfiles = 'class="active"';
                        $main = 'actors.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='actor') {
                        $navProfiles = 'class="active"';
                        $main = 'actor.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='merchants') {
                        $navMerchants = 'class="active"';
                        $main = 'merchants.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='merchant') {
                        $navMerchants = 'class="active"';
                        $main = 'merchant.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='users') {
                        $navUsers = 'class="active"';
                        $main = 'users.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='user') {
                        $navUsers = 'class="active"';
                        $main = 'user.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='sites') {
                        $navSites = 'class="active"';
                        $main = 'sites.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='site') {
                        $navSites = 'class="active"';
                        $main = 'site.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='banners') {
                        $navBanners = 'class="active"';
                        $main = 'banners.php';
                   } elseif (isset($_GET['mod']) AND $_GET['mod']=='banners_new') {
                        $navNewBanners = 'class="active"';
                        $main = 'banners_new.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='movies_checking') {
                        $navMovies = 'class="active"';
                        $main = 'movies_checking.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='movie_checking') {
                        $navMovies = 'class="active"';
                        $main = 'movie_checking.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='movies_blocked') {
                        $navMoviesBlocked = 'class="active"';
                        $main = 'movies_blocked.php';                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='movies_drafts') {
                        $navMoviesDrafts = 'class="active"';
                        $main = 'movies_drafts.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='movies_online') {
                        $navMoviesOnline = 'class="active"';
                        $main = 'movies_online.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='movie_edit') {
                        $navMoviesOnline = 'class="active"';
                        $main = 'movie_edit.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='content_cleanup') {
                        $navContentCleanup = 'class="active"';
                        $main = 'content_cleanup.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='photo_albums_checking') {
                        $navFotoalben = 'class="active"';
                        $main = 'photo_albums_checking.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='photo_album_checking') {
                        $navFotoalben = 'class="active"';
                        $main = 'photo_album_checking.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='photo_albums_online') {
                        $navFotoalbenOnline = 'class="active"';
                        $main = 'photo_albums_online.php';
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='photo_album_edit') {
                        $navFotoalbenOnline = 'class="active"';
                        $main = 'photo_album_edit.php';
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='webcams') {
                        $navWebcams = 'class="active"';
                        $main = 'webcams.php';

                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='all_chats') {
                        $navAllChats = 'class="active"';
                        $main = 'all_chats.php';  

                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='chat') {
                        $navAllChats = 'class="active"';
                        $main = 'chat.php';  
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='errors') {
                        $navErrors = 'class="active"';
                        $main = 'errors.php'; 
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='pointssystem') {
                        $navPointssystem = 'class="active"';
                        $main = 'pointssystem.php'; 
                        
                    } elseif (isset($_GET['mod']) AND $_GET['mod']=='webmaster_partner') {
                        $navWMPartner = 'class="active"';
                        $main = 'webmaster_partner.php'; 
                                                
                    } else {
                        header('Location: '.ACP_URL.'/Startseite');
                	exit;
                    }
                    
                    $site .= '
                    <a '.$navHome.' href="'.ACP_URL.'/Startseite">
                        <i class="material-symbols-outlined md-30">home</i>
                        <span>Startseite</span>
                    </a>
                    ';
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('content_check', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a class="submenu" data-submenu="submenu_checking">
                            <i class="material-symbols-outlined arrow">arrow_right</i> 
                            <i class="material-symbols-outlined md-30">cloud_off</i>
                            <span>Content Pr&uuml;fen ('.$count_content_checking.')</span>
                        </a>

                        <div class="submenu_checking">
                            <a '.$navMovies.' href="'.ACP_URL.'/Filme-pruefen">
                                <i class="material-symbols-outlined md-30">add_box</i>
                                <span>Filme pr&uuml;fen ('.$count_movies_checking.')</span>
                            </a>

                            <a '.$navFotoalben.' href="'.ACP_URL.'/Fotoalben-pruefen">
                                <i class="material-symbols-outlined md-30">add_photo_alternate</i>
                                <span>Fotoalben pr&uuml;fen ('.$count_photo_albums_checking.')</span>
                            </a>
                            
                            <a '.$navMoviesBlocked.' href="'.ACP_URL.'/gesperrte-Filme">
                                <i class="material-symbols-outlined md-30">add_box</i>
                                <span>Filme abgelehnt ('.$count_movies_blocked.')</span>
                            </a>
                        </div>
                        ';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('content_online', $_SESSION['employee_access_area'])) {
                        $site .= '                    
                        <a class="submenu" data-submenu="submenu_online">
                            <i class="material-symbols-outlined arrow">arrow_right</i>
                            <i class="material-symbols-outlined md-30">cloud_done</i>
                            <span>Content Online</span>
                        </a>

                        <div class="submenu_online">
                            <a '.$navMoviesOnline.' href="'.ACP_URL.'/Filme-online">
                                <i class="material-symbols-outlined md-30">video_library</i>
                                <span>Filme online ('.$count_movies_online.')</span>
                            </a>

                            <a '.$navMoviesDrafts.' href="'.ACP_URL.'/Filme-in-Planung">
                                <i class="material-symbols-outlined md-30">pending_actions</i>
                                <span>Filme in Planung ('.$count_movies_drafts.')</span>
                            </a>

                            <a '.$navFotoalbenOnline.' href="'.ACP_URL.'/Fotoalben-online">
                                <i class="material-symbols-outlined md-30">photo_library</i>
                                <span>Fotoalben online ('.$count_photo_albums_online.')</span>
                            </a>

                            <a '.$navContentCleanup.' href="'.ACP_URL.'/Content-Bereinigung">
                                <i class="material-symbols-outlined md-30">delete_sweep</i>
                                <span>Content-Bereinigung</span>
                            </a>
                        </div>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('actors', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navProfiles.' href="'.ACP_URL.'/Actors">
                            <i class="material-symbols-outlined md-30">account_box</i> 
                            <span>Profile'.$count_new_actors_hmtl.'</span>
                        </a>
                        ';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('websites', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navSites.' href="'.ACP_URL.'/Sites">
                            <i class="material-symbols-outlined md-30">shopping_cart</i>
                            <span>Webseiten</span>
                        </a>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('affiliate_program', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a class="submenu" data-submenu="submenu_banners">
                            <i class="material-symbols-outlined arrow">arrow_right</i>
                            <i class="material-symbols-outlined md-30">attach_money</i>
                            <span>Partnerprogramm ('.$count_new_banners.')</span>
                        </a>

                        <div class="submenu_banners">
                            <a '.$navWMPartner.' href="'.ACP_URL.'/Webmaster/Partner">
                                <i class="material-symbols-outlined md-30">supervisor_account</i>
                                <span>aktive Webmaster</span>
                            </a>
                            <a '.$navBanners.' href="'.ACP_URL.'/Banner">
                                <i class="material-symbols-outlined md-30">layers</i>
                                <span>aktive Banner</span>
                            </a>
                            <a '.$navNewBanners.' href="'.ACP_URL.'/Neue-Banner">
                                <i class="material-symbols-outlined md-30">new_releases</i>
                                <span>neue Banner  ('.$count_new_banners.')</span>
                            </a>
                        </div>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('webcams', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navWebcams.' href="'.ACP_URL.'/Webcams">
                            <i class="material-symbols-outlined md-30">videocam</i>
                            <span>Webcams ('. p4c_num_rows($rs_actor_cams).')</span>
                        </a>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('merchants', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navMerchants.' href="'.ACP_URL.'/Haendler">
                            <i class="material-symbols-outlined md-30">supervisor_account</i>
                            <span>P4C-Merchants</span>
                        </a>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('users', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navUsers.' href="'.ACP_URL.'/User">
                            <i class="material-symbols-outlined md-30">people</i>
                            <span>Cloud-User</span>
                        </a>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('point_system', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navPointssystem.' href="'.ACP_URL.'/Punktesystem">
                            <i class="material-symbols-outlined md-30">star_rate</i>
                            <span>Punktesystem</span>
                        </a>';
                    }

                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('chats', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a class="submenu" data-submenu="submenu_chats">
                            <i class="material-symbols-outlined arrow">arrow_right</i> <span><i class="material-symbols-outlined md-30">chat</i>
                            <span>Chats</span>
                        </a>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('all_messages', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <div class="submenu_chats">
                            <a '.$navAllChats.' href="'.ACP_URL.'/alle-Nachrichten">
                                <i class="material-symbols-outlined md-30">forum</i>
                                <span>alle Nachrichten</span>
                            </a>
                        </div>';
                    }
                    
                    if (in_array('all', $_SESSION['employee_access_area']) OR in_array('error_log', $_SESSION['employee_access_area'])) {
                        $site .= '
                        <a '.$navErrors.' href="'.ACP_URL.'/Errors">
                            <i class="material-symbols-outlined md-30">error</i>
                            <span>Error-Log</span>
                        </a>';
                    }
                    
                    $site .= '
                    <div style="margin-top:10px; padding:10px 0 10px 0; border-top:1px solid rgba(0,0,0,.10); color:rgba(0,0,0,.38);">
                        <a target="pay4coins_acp" href="https://acp.pay4coins.com">
                            <i class="material-symbols-outlined md-30">account_balance</i>
                            <span>Pay4Coins</span>
                        </a>
                    </div>
                </td>
                <td style="vertical-align:top; padding:10px;">';
                    if (file_exists(ACP_DIR.'/includes/'.$main)) {
                        include_once(ACP_DIR.'/includes/'.$main);
                    } elseif (file_exists(ACP_DIR.'/includes/home.php')) {
                        include_once(ACP_DIR.'/includes/home.php');
                    } else {
                        $site = 'Die Seite "'.$main.'" konnte nicht gefunden werden!';
                    }
                $site .= '
                </td>
            </tr>        
        </table>
        
        <div id="overlay"></div>
        ';
        if (isset($_GET['mod']) AND ($_GET['mod'] == 'movie_checking' OR $_GET['mod'] == 'movie_edit')) {
            $site .= '
            <div class="movie_metainfos_popup">
                <div style="text-align:right; top:20px; right:10px; position:absolute;">
                    <a href="#" class="close_overlay"><b>&#x2715;</b></a>
                </div>
                <div style="font-size:25px; margin-bottom:20px;">Meta-Daten zum Film</div>
                <div class="edit_content" style="font-size:15px; padding:10px; margin-bottom:20px; border-top:none;">';
                    include_once(MCP_DIR.'/includes/getid3/getid3.php');
                    $getID3 = new getID3;
                    $fileinfo = $getID3->analyze($file_path);
                    
                    $site .= '
                    <pre>'.print_r($fileinfo,true).'</pre>
                </div>
                <div style="text-align:right; float:right;">
                    <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
                </div>
            </div>';
        }
                
                
        if (isset($_GET['mod']) AND ($_GET['mod'] == 'movies_blocked' OR $_GET['mod'] == 'movies_checking' OR $_GET['mod'] == 'movie_checking' OR $_GET['mod'] == 'photo_albums_checking' OR $_GET['mod'] == 'photo_album_checking')) {
            $site .= '
            <script>
                jQuery(document).ready(function() {
                    jQuery(this).open_submenu("submenu_checking");
                })
            </script>';
        }
        
        if (isset($_GET['mod']) AND ($_GET['mod'] == 'movies_online' OR $_GET['mod'] == 'movie_edit' OR $_GET['mod'] == 'photo_albums_online' OR $_GET['mod'] == 'photo_album_edit')) {
            $site .= '
            <script>
                jQuery(document).ready(function() {
                    jQuery(this).open_submenu("submenu_online");
                })
            </script>';
        }
        
        if (isset($_GET['mod']) AND ($_GET['mod'] == 'webmaster_partner' OR $_GET['mod'] == 'banners' OR $_GET['mod'] == 'banners_new')) {
            $site .= '
            <script>
                jQuery(document).ready(function() {
                    jQuery(this).open_submenu("submenu_banners");
                })
            </script>';
        }
        
        if ($_GET['mod'] == 'photo_album_checking' OR $_GET['mod'] == 'photo_album_edit') {
            $site .= ' 
            <link rel="stylesheet" href="'.ACP_URL.'/css/site_photo_album.css" />
            <link rel="stylesheet" href="'.MCP_URL.'/fw/colorbox/colorbox.css" />
                
            <script type="text/javascript" src="'.MCP_URL.'/fw/colorbox/jquery.colorbox.js"></script>

            <script type="text/javascript">
            <!--
                jQuery.noConflict();

                jQuery(document).ready(function(){
                    jQuery(".zoom_goup, .img_group_preview").colorbox({photo:true, rel:"zoom_goup", maxWidth:"100%", width:"auto", maxHeight:"80%"});
                })
            //-->
            </script>';
        }
        
        if (isset($_GET['mod']) AND ($_GET['mod'] == 'all_messenges' OR $_GET['mod'] == 'chat' )) {
            $site .= '
            <script>
                jQuery(document).ready(function() {
                    jQuery(this).open_submenu("submenu_chats");
                })
            </script>';
        }
        
                
    } else {
        $site .= '<div class="ui-widget-header" style="border-top:none; border-left:none; border-right:none; padding:5px 10px; font-weight:normal;">'.COMPANYNAME.' - Admin-Control-Panel</div>';
        include_once(ACP_DIR.'/includes/login.php');
    }    
    $site .= '  
</body>
</html>';

echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>