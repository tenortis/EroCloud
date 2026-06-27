<?php

 
define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=ISO-8859-15');

$site = '<!DOCTYPE html>
<html lang="de">
<head>
    <title>'.PROJECTNAME.' - Messenger</title>
    <meta charset="iso-8859-15" />

    <script src="https://cdn.ckeditor.com/4.8.0/standard/ckeditor.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    
    <script src="https://vjs.zencdn.net/6.6.3/video.js"></script>
    <script src="'.MCP_URL.'/Messenger/js/videojs-contrib-hls.js"></script>

    <script>
        jQuery.noConflict();
        var SeitenTitel = "'.PROJECTNAME.' - Messenger";

    </script>

    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/Messenger/css/style.css?id=8" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" type="text/css" href="https://vjs.zencdn.net/6.6.3/video-js.css" />
    
    <script src="'.MCP_URL.'/fw/jquery-ui/jquery-ui.min.js"></script>

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

</head>
<body>
';
    if (is_logged_in('mcp') === true) {
        
        $merchant = new Merchant($mysql,$_SESSION['merchant_id']);
        
        $media_server_url = 'rtmp://stream.me-on.de/convert';

        // Dise Funktion muss identisch mit der in /messenger/livecam/submit.php sein
        function stream_key() { 
            global $merchant;

            $string = $merchant->partner_id().hash('crc32b', $merchant->api_key('aes_decrypt').$_SESSION['merchant_id'].'erocloud');
            $str_to_hash = str_replace(array('0','O'), 'W', $string);
            $str_to_hash = str_replace('I', 'L', $str_to_hash);
            $str_to_hash = strtoupper($str_to_hash);
            $str_to_hash = str_split($str_to_hash, 6);
            $str_to_hash = implode('-', $str_to_hash);
            
            return $str_to_hash;
        }
        $streamname = stream_key();
        
        $site .= '
        <div id="messenger_parent">
            <div id="messenger_head" class="ui-widget-header">
                <div class="ui-widget-content home_button" onclick="jQuery(this).checkParentIsOpen(\''.MCP_URL.'/Startseite\');" data-tooltip="'.PROJECTNAME.' - Startseite"><i class="material-symbols-outlined">home</i></div>
                <div class="ui-widget-content open_home" data-tooltip="Messenger - Startseite"><a href="javascript:;">Startseite</a></div>
                <div class="ui-widget-content open_webcam" data-tooltip="Webcam &ouml;ffnen"><i class="material-symbols-outlined">videocam</i></div>
                <div class="ui-widget-content notification_sound" data-notification-sound-status="1" data-tooltip=""><i class="material-symbols-outlined">notifications_active</i></div>
                <div class="ui-widget-content commision"><a href="javascript:;" onclick="jQuery(this).checkParentIsOpen(\''.MCP_URL.'/Startseite\');" data-tooltip="Deine Provision im aktuellem Monat.<br />Diese Anzeige aktualisiert sich alle 30 Sekunden automatisch."><span>-</span> EUR</a></div>
                <div class="ui-widget-content logout"><a href="javascript:;" onclick="location.href=\''.MCP_URL.'/index.php?logout\'" data-tooltip="Jetzt abmelden">Logout</a></div>
            </div>
            <div id="messenger_main" class="fill-area">
                <div id="messenger_left">
                    <div id="left_search">
                        <input type="text" id="search" placeholder="Suche...">
                    </div>
                    <div class="ui-widget-header userlist_search_head">
                        <i class="material-symbols-outlined">keyboard_arrow_down</i> <span id="count_search_results">0</span>&nbsp;Suchergebnisse
                    </div>
                    <div class="userlist_search"></div>

                    <div class="ui-widget-header userlist_marked_as_unanswered_head">
                        <i class="material-symbols-outlined">keyboard_arrow_down</i> <span id="count_marked_as_unanswered">0</span>&nbsp;unbeantwortete Chats
                    </div>
                    <div class="userlist_marked_as_unanswered"></div>
                    
                    <div class="ui-widget-header userlist_unread_head">
                        <i class="material-symbols-outlined">keyboard_arrow_down</i> <span id="count_userunread">0</span>&nbsp;ungelesene Chats
                    </div>
                    <div class="userlist_unread"></div>

                    <div class="ui-widget-header userlist_online_head">
                        <i class="material-symbols-outlined">keyboard_arrow_down</i> <span id="count_useronline">0</span>&nbsp;User Online
                    </div>
                    <div class="userlist_online"></div>
                    
                    <div class="ui-widget-header userlist_offline_head">
                        <i class="material-symbols-outlined">keyboard_arrow_up</i> <span id="count_useroffline">0</span>&nbsp; User offline
                    </div>
                    <div class="userlist_offline"></div>
                </div>
                <div id="messenger_right">
                    <div id="welcome" style="width:600px; padding:10px;">
                        ';
                        if (isset($_GET['test']) OR isset($_SESSION['test'])) {
                            $_SESSION['test'] = true;
                            
                            $site .= '
                            <div id="my_chats">
                                <div class="ui-widget-header">Deine letzten Chats</div>
                                <div style="padding:10px; margin-bottom:20px;" class="ui-widget-content">
                                <script type="text/javascript">
                                // <![CDATA[
                                    jQuery(document).ready(function() {

                                        jQuery.extend( jQuery.fn.dataTableExt.oSort, {
                                            "numeric-comma-pre": function ( a ) {
                                                var x = (a == "-") ? 0 : a.replace( /,/, "." );
                                                return parseFloat( x );
                                            },

                                            "numeric-comma-asc": function ( a, b ) {
                                                return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                                            },

                                            "numeric-comma-desc": function ( a, b ) {
                                                return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                                            },

                                            "num-html-pre": function ( a ) {
                                                var x = String(a).replace( /<[\s\S]*?>/g, "" );
                                                return parseFloat( x );
                                            },

                                            "num-html-asc": function ( a, b ) {
                                                return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                                            },

                                            "num-html-desc": function ( a, b ) {
                                                return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                                            },

                                            "title-string-pre": function ( a ) {
                                                return a.match(/title="(.*?)"/)[1].toLowerCase();
                                            },

                                            "title-string-asc": function ( a, b ) {
                                                return ((a < b) ? -1 : ((a > b) ? 1 : 0));
                                            },

                                            "title-string-desc": function ( a, b ) {
                                                return ((a < b) ? 1 : ((a > b) ? -1 : 0));
                                            }
                                        } );


                                        oTable = jQuery("#table_my_chats").dataTable({
                                            "bJQueryUI": true,
                                            "iDisplayLength": 50,
                                            "aaSorting": [[ 1, "desc" ]],
                                            //"bProcessing": true,
                                            //"bDeferRender": true,

                                            "bProcessing": true,
                                            //"bServerSide": true,
                                            "sAjaxSource": "'.MCP_URL.'/Messenger/Ajax/my_chats.php",

                                            "bAutoWidth": false,
                                            "aoColumns": [
                                                {sWidth: "150px", "sClass": "left", "sType": "title-string"},
                                                {sWidth: "150px", "sClass": "center"},
                                                {sWidth: "auto", "sClass": "left", "sType": "title-string"}
                                            ],

                                            "oLanguage": {
                                                "sProcessing":   "Bitte warten...",
                                                "sLengthMenu":   "_MENU_ Eintr&auml;ge anzeigen",
                                                "sZeroRecords":  "Keine Eintr&auml;ge vorhanden.",
                                                "sInfo":         "_START_ bis _END_ von _TOTAL_ Eintr&auml;gen",
                                                "sInfoEmpty":    "0 bis 0 von 0 Eintr&auml;gen",
                                                "sInfoFiltered": "(gefiltert von _MAX_  Eintr&auml;gen)",
                                                "sInfoPostFix":  "",
                                                "sSearch":       "Suchen",
                                                "sUrl":          "",
                                                "oPaginate": {
                                                    "sFirst":    "Erster",
                                                    "sPrevious": "Zur&uuml;ck",
                                                    "sNext":     "N&auml;chster",
                                                    "sLast":     "Letzter"
                                                }
                                            }                            
                                        });

                                    })

                                // ]]>
                                </script>
                                
                                <table id="table_my_chats" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;">Username</th>
                                            <th style="text-align:left;">letzte Nachricht am</th>
                                            <th>Nachricht</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>

                            </div>';
                            
                        }
        
                        $site .= '
                        <div id="groups">';
                            if ($_SESSION['logged_in_as'] == 'group') {
                                $rs_groups = p4c_query("SELECT `groups`.`group_id` AS `group_id_name`, `group_actors`.`group_id`, `groups`.`group_name` FROM `groups` INNER JOIN `group_actors` ON `groups`.`id`=`group_actors`.`group_id` WHERE `groups`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND `groups`.`id`='".abs($_SESSION['my_chatgroup'])."' GROUP BY `group_actors`.`group_id` ORDER BY `groups`.`id` DESC LIMIT 1;",__FILE__,__LINE__);

                                $num_row_groups = p4c_num_rows($rs_groups);
                                if ($num_row_groups == 0) {
                                    header('Location: '.MCP_URL.'/index.php?logout');
                                    exit;
                                } else {
                                    $site .= '<div><h2>Du bist mit folgenden Profilen online:</h2></div>';
                                    
                                    while($group_obj = p4c_fetch_object($rs_groups)) {
                                        $rs_group_actors = p4c_query("SELECT * FROM `group_actors` LEFT JOIN `actors` ON `group_actors`.`actor_id`=`actors`.`id` WHERE `group_actors`.`group_id`='".abs($group_obj->group_id)."' AND (`actors`.`is_displayed_as`='only_chat_actor' OR `actors`.`is_displayed_as`='chat_upload_actor');",__FILE__,__LINE__);
                                        if (isset($_SESSION['my_chatgroup']) AND $_SESSION['my_chatgroup'] == $group_obj->group_id) {
                                            $checked='check_box';
                                        } else {
                                            $checked='check_box_blank';
                                        }

                                        if ($group_obj->group_name === '') {
                                            $group_name = $group_obj->group_id_name;
                                        } else {
                                            $group_name = $group_obj->group_name;
                                        }

                                        $site .= '
                                        <div class="group">
                                            <div class="ui-widget-header">
                                                <i data-group-id="'.$group_obj->group_id.'" id="my_chat_group_'.$group_obj->group_id.'" class="'.$checked.'_group material-symbols-outlined">'.$checked.'</i>
                                                '.$group_name.'
                                            </div>
                                            <div class="ui-widget-content">';
                                                while($actor_obj = p4c_fetch_object($rs_group_actors)) {
                                                    $site .= '
                                                    <diV>
                                                        <table>
                                                            <tr>
                                                                <td>
                                                                    <a href="'.MCP_URL.'/Actor/'.$actor_obj->actor_id.'" target="WinEroCloud">
                                                                        <img src="'.MCP_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16.'" style="vertical-align:middle; width:30px; height:30px; margin-right:5px;" /> '.$actor_obj->username.'
                                                                    </a>
                                                                </td>
                                                                <td data-group-id="'.$group_obj->group_id.'">
                                                                    <div class="pause" data-pausestatus="" data-actor-id="'.$actor_obj->actor_id.'" title="">
                                                                        <span class="pause_text">text</span>
                                                                    </div>
                                                                    <div class="online_status" data-online_status="" data-actor-id="'.$actor_obj->actor_id.'" title="">
                                                                        <span class="online_status_text">text</span>
                                                                    </div>
                                                                    <script>
                                                                        jQuery(document).ready(function() {
                                                                            pause_status('.$actor_obj->messenger_takes_a_break.','.$actor_obj->actor_id.');
                                                                            online_status('.$actor_obj->messenger_online_status.','.$actor_obj->actor_id.');
                                                                        })
                                                                    </script>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>';
                                                }
                                                $site .= '
                                            </div>
                                        </div>';
                                    }
                                }
                            } else {
                                $rs_groups = p4c_query("SELECT `groups`.`group_id` AS `group_id_name`, `group_actors`.`group_id`, `groups`.`group_name` FROM `groups` INNER JOIN `group_actors` ON `groups`.`id`=`group_actors`.`group_id` WHERE `groups`.`merchant_id`='".abs($_SESSION['merchant_id'])."' GROUP BY `group_actors`.`group_id` ORDER BY `groups`.`id` DESC;",__FILE__,__LINE__);
                               
                                $num_row_groups = p4c_num_rows($rs_groups);
                                if ($num_row_groups > 0) {
                                    $site .= '<div><h2>W&auml;hle eine Gruppe mit der du online gehen willst.</h2></div>';
                                    
                                    while($group_obj = p4c_fetch_object($rs_groups)) {
                                        $rs_group_actors = p4c_query("SELECT * FROM `group_actors` LEFT JOIN `actors` ON `group_actors`.`actor_id`=`actors`.`id` WHERE (`actors`.`status`='active' OR `actors`.`status`='inactive') AND  (`actors`.`is_displayed_as`='only_chat_actor' OR `actors`.`is_displayed_as`='chat_upload_actor') AND `group_actors`.`group_id`='".abs($group_obj->group_id)."';",__FILE__,__LINE__);
                                        
                                        if (p4c_num_rows($rs_group_actors) > 0) {
                                        
                                            if (isset($_SESSION['my_chatgroup']) AND $_SESSION['my_chatgroup'] == $group_obj->group_id) {
                                                $checked='check_box';
                                            } else {
                                                $checked='check_box_blank';
                                            }

                                            if ($group_obj->group_name === '') {
                                                $group_name = $group_obj->group_id_name;
                                            } else {
                                                $group_name = $group_obj->group_name;
                                            }

                                            $rs_count_chats = p4c_query("SELECT * FROM `group_actors`, `chat_messages` WHERE
                                                `group_actors`.`actor_id`=`chat_messages`.`an_id` AND
                                                `group_actors`.`group_id` = '".abs($group_obj->group_id)."' AND
                                                `group_actors`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                                                `chat_messages`.`von`='member' AND
                                                `chat_messages`.`gelesen` != '1'
                                            GROUP BY `chat_messages`.`chat_id` ASC;",__FILE__,__LINE__);

                                            $count_chats = p4c_num_rows($rs_count_chats);

                                            $number_of_chats = '';
                                            if ($count_chats > 0) {
                                                $number_of_chats = ' - '.$count_chats.' ungelesene Chats';
                                            }

                                            $site .= '
                                            <div class="group">
                                                <div class="ui-widget-header">
                                                    <i data-group-id="'.$group_obj->group_id.'" id="my_chat_group_'.$group_obj->group_id.'" class="'.$checked.'_group material-symbols-outlined">'.$checked.'</i>
                                                    '.$group_name.$number_of_chats.'
                                                </div>
                                                <div class="ui-widget-content">';
                                                    while($actor_obj = p4c_fetch_object($rs_group_actors)) {
                                                        $site .= '
                                                        <diV>
                                                            <table>
                                                                <tr>
                                                                    <td>
                                                                        <a href="'.MCP_URL.'/Actor/'.$actor_obj->actor_id.'" target="WinEroCloud">
                                                                            <img src="'.MCP_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16.'" style="vertical-align:middle; width:30px; height:30px; margin-right:5px;" /> '.$actor_obj->username.'
                                                                        </a>
                                                                    </td>
                                                                    <td data-group-id="'.$group_obj->group_id.'">
                                                                        <div class="pause" data-pausestatus="" data-actor-id="'.$actor_obj->actor_id.'" title="">
                                                                            <span class="pause_text">text</span>
                                                                        </div>
                                                                        <div class="online_status" data-online_status="" data-actor-id="'.$actor_obj->actor_id.'" title="">
                                                                            <span class="online_status_text">text</span>
                                                                        </div>
                                                                        <script>
                                                                            jQuery(document).ready(function() {
                                                                                pause_status('.$actor_obj->messenger_takes_a_break.','.$actor_obj->actor_id.');
                                                                                online_status('.$actor_obj->messenger_online_status.','.$actor_obj->actor_id.');
                                                                            })
                                                                        </script>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>';
                                                    }
                                                    $site .= '
                                                </div>
                                            </div>';
                                        }
                                    }
                                }
                            }
                            $site .= '
                        </div>';
                            
                        if ($_SESSION['logged_in_as'] == 'merchant' AND isset($_GET['sync'])) {
                            $site .= '
                            <div class="ui-widget-header" style="padding:5px 10px;">Messenger syncronisieren</div>
                            <div class="ui-widget-content" style="padding:5px 10px; border-top:none;">
                                <div style="margin-bottom:8px;">
                                    Bevor du loslegen kannst, musst du dich einmal mit deinem Gastamateur-Account, von der Webseite auf der du als Gastamateur registriert bist,
                                    syncronisieren.<br />
                                    <br />
                                    Nachdem die Syncronisierung abgeschlo&szlig;en ist, kann es m&ouml;glich sein, dass dir einige Chats als ungelesen angezeigt werden.
                                    Klicke dich einfach einmal durch diese Chats durch.
                                </div>
                                <div id="check_url_connect_info" style="display:none; margin-bottom:5px;"></div>
                                <div style="margin-bottom:5px;"><input type="input" placeholder="Webseite auf der du registriert bist" id="check_url_connect" style="width:250px; padding:2px 5px;" /> Bsp.: webseite.com</div>
                                <div style="margin-bottom:5px;"><input type="input" placeholder="Dein Username auf der Webseite" id="check_url_username" style="width:250px; padding:2px 5px;" /> Bsp.: AmateurName</div>
                                <div style="margin-bottom:5px;"><input type="input" placeholder="Dein Passwort auf der Webseite" id="check_url_password" style="width:250px; padding:2px 5px;" /> Bsp.: 12345</div>
                                <div style="margin-bottom:5px;">';
                                    $rs_groups = p4c_query("SELECT * FROM `groups` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC;",__FILE__,__LINE__);
                                    if (p4c_num_rows($rs_groups) > 0) {
                                        $site .= '<select id="check_url_group" style="width:264px;">';
                                        while($group_obj = p4c_fetch_object($rs_groups)) {
                                            if ($group_obj->group_name === '') {
                                                $group_name = $group_obj->group_id;
                                            } else {
                                                $group_name = $group_obj->group_name;
                                            }
                                            $site .= '<option value="'.$group_obj->id.'">'.$group_name.'</option>';
                                        }
                                        $site .= '</select>';
                                    }
                                    $site .= '
                                </div>
                                <input id="submit_check_url_connect" type="button" value=" Verbindung herstellen " />
                            </div>';

                            $rs_check_sync = p4c_query("SELECT * FROM `messenger_sync` LEFT JOIN `actors` ON `messenger_sync`.`actor_id`=`actors`.`id` WHERE `messenger_sync`.`merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
                            if (p4c_num_rows($rs_check_sync) > 0) {
                                $site .= '
                                <div class="ui-widget-header" style="padding:5px 10px; border-top:none;">bereits verbunden mit:</div>
                                <div class="ui-widget-content" style="padding:5px 10px; border-top:none;">';
                                while($sync_ary = p4c_fetch_object($rs_check_sync)) {
                                    if ($sync_ary->sync_time == '0000-00-00 00:00:00' AND $sync_ary->chats_total > 0) {
                                        $site .= '<div>&bull; '.$sync_ary->domain.' ('.$sync_ary->username.') - <span style="color:#FFA500">Syncronisierung l&auml;uft... ('.$sync_ary->chats_synct.' von '.$sync_ary->chats_total.' Chats)</span></div>';
                                    } else if ($sync_ary->sync_time == '0000-00-00 00:00:00') {
                                        $site .= '<div>&bull; '.$sync_ary->domain.' ('.$sync_ary->username.') - <span style="color:#FFA500">Syncronisierung l&auml;uft...</span></div>';
                                    } else {
                                        $site .= '<div>&bull; '.$sync_ary->domain.' ('.$sync_ary->username.')</div>';
                                    }
                                }
                                $site .= '
                                </div>';
                            }
                        }
                        
                        $site .= '
                    </div>
                    <div id="chat">
                        <div id="chat_head">
                            <table>
                                <tr>
                                    <td id="member_avatar">
                                        <div id="username" class="ui-widget-header"><span class="username"></span></div>
                                        <div id="avatar"><img src="" /></div>
                                    </td>
                                    <td id="member_infos">
                                        <div style="border:none;" class="ui-widget-content">
                                            <div style="padding:10px 20px;">
                                                <div id="count_messeges">
                                                    Nachrichten vom User: <span id="count_mess_from_member"></span><br />
                                                    Nachrichten an User: <span id="count_mess_from_actor"></span>
                                                </div>
                                                <div id="member_infos_birthday">Geburtstag: <span id="birthday"></span></div>
                                                <div id="member_infos_lastonline">zuletzt Online: <i class="material-symbols-outlined device"></i> <span id="lastonline"></span></div>

                                                <div style="color:#cc0000; padding:17px 13px 5px 13px; text-align:center;">
                                                    Das Abwerben von Usern, das Empfangen von Geld via PayPal,<br />
                                                    Amazon-Gutschein und dergleichen ist verboten und f&uuml;hrt zu<br />
                                                    einer sofortigen Sperrung deines Profils!
                                                </div>
                                            </div>
                                            <div class="ui-widget-header" style="padding-bottom:3px; font-weight:normal; border-left:none; border-bottom:0; border-right:none; text-align:center;">
                                                Du chattest mit diesem User auf <span id="domain"></span>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div id="chat_history_gradient" style="display: block;"></div>
                        
                        <div id="chat_history">
                            <div id="chat_history_old"></div>
                            <div id="chat_history_new"></div>
                        </div>
                        <div id="chat_send_mess">
                            <div id="message_box">
                                <div id="textarea" contenteditable="true" class="wdt-emoji-bundle-enabled" placeholder="Schreibe eine Nachricht und dr&uuml;cke ENTER um diese zu senden."></div>
                                <div id="message_tools">
                                    <div class="ui-widget-content" id="smileys"></div>
                                    <div class="ui-widget-content markanswered" id="markunanswered">
                                        <i class="material-symbols-outlined" data-tooltip="Als unbeantwortet markieren.">mail</i>
                                    </div>
                                    <div class="ui-widget-content" id="send_file">
                                        <i class="material-symbols-outlined" data-tooltip="Datei senden.">file_upload</i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wdt-emoji-popup">
                            <a href="#" class="wdt-emoji-popup-mobile-closer"> &times; </a>
                            <div class="wdt-emoji-menu-content">
                                <div id="wdt-emoji-menu-header">
                                    <a class="wdt-emoji-tab active" data-group-name="Recent"></a>
                                    <a class="wdt-emoji-tab" data-group-name="People"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Nature"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Foods"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Activity"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Places"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Objects"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Symbols"></a>
                                    <a class="wdt-emoji-tab" data-group-name="Flags"></a>
                                </div>
                                <div class="wdt-emoji-scroll-wrapper">
                                    <div id="wdt-emoji-menu-items">
                                        <div class="wdt-emoji-sections"></div>
                                        <div id="wdt-emoji-no-result">No emoji found</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="send_file_box">
                            <div class="ui-widget-header" id="send_file_title">
                                <span>Datei senden</span> <i class="material-symbols-outlined">close</i>
                            </div>
                            <div class="ui-widget-content" id="send_file_content">
                                <div style="margin-bottom:15px;">
                                    Es sind nur Bilder (jpg, png) und PDF-Dateien erlaubt.<br />
                                    Maximale Dateigr&ouml;&szlig;e: 5 MB
                                </div>
 
                                <div class="upload">
                                    Datei ausw&auml;hlen
                                    <form id="form_send_file" action="'.MCP_URL.'/includes/uploader/send_messenger_file.php" method="post" enctype="multipart/form-data">
                                        <input type="hidden" id="upload_file_chat_id" name="chat_id" />
                                        <input type="file" class="upload_file" id="upload_file" name="send_file" accept="image/x-png,image/jpeg,application/pdf">
                                    </form>
                                </div>
                                <div class="upload_abort ui-state-error">Abbrechen</div>

                                <div class="upload_progress">
                                    <div class="upload_bar"></div >
                                    <div class="upload_percent">0%</div >
                                </div>

                                <div class="upload_error ui-state-error"></div>
                                <div class="upload_status"></div>

                                <script type="text/javascript">
                                    // <![CDATA[
                                    jQuery(document).ready(function() {
                                        var bar = jQuery(".upload_progress .upload_bar");
                                        var percent = jQuery(".upload_progress .upload_percent");
                                        var status = jQuery(".upload_status");
                                        var progress = jQuery(".upload_progress");
                                        var abort = jQuery(".upload_abort");
                                        var upload = jQuery(".upload");
                                        
                                        jQuery("#form_send_file").ajaxForm({
                                            clearForm: true,
                                            resetForm: true,
                                            forceSync: true,
                                            dataType:  "json",
                                            beforeSend: function(xhr) {
                                                jQuery(".upload_error").hide().html("");
                                                progress.show();
                                                upload.hide();
                                                abort.show();
                                                abort.click(function () {
                                                    xhr.abort();
                                                    jQuery("#upload_file").val("");
                                                    upload.show();
                                                    abort.hide();
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
                                                    progress.hide();

                                                } else {
                                                    jQuery("#send_file_box").hide();
                                                    jQuery(".upload_file").val("");
                                                    upload.show();
                                                    abort.hide();
                                                    progress.hide();
                                                    
                                                    jQuery(this).messenger_info({
                                                        title: "Info",
                                                        content: "Die Datei wurde erfolgreich gesendet.",
                                                        autoClose: true
                                                    })

                                                }
                                            },
                                            complete: function(data) {

                                            }
                                        }); 


                                        var s = jQuery.extend({
                                            allowedTypes: "jpg,jpeg,png,pdf",
                                            maxFileSize: 5242880 // 5 MB
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
                                                error = "Datei zu gro&szlig;.";
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
                                            jQuery("#upload_file_chat_id").val(userlist_active);
                                            if (!uploadMovie(this)) {
                                                jQuery(".upload_error").show().html(error);
                                            } else {
                                                jQuery("#form_send_file").trigger("submit");
                                            }
                                        });
                                    })
                                // ]]>
                                </script>


                            </div>
                        </div>

                    </div>
                    
                    <div id="chat_infos">
                        <div id="my_profil">
                            <div id="actorname" class="ui-widget-header"><span class="actorname"></span></div>
                            <div class="ui-widget-content">
                                <table>
                                    <tr>
                                        <td id="actor_avatar">
                                            <img src="" />
                                        </td>
                                        <td>
                                            <div class="pause" data-pausestatus="" data-actor-id="" title="">
                                                <span class="pause_text"></span>
                                            </div>
                                            <div class="online_status" data-online_status="" data-actor-id="" title="">
                                                <span class="online_status_text"></span>
                                            </div>
                                            
                                            <div id="my_profil_infos">
                                                <div>Du bist <span id="actor_age"></span> Jahre und <span id="actor_marital_status"></span>.</div>
                                                <div>Du suchst: <span id="actor_looking_for"></span></div>
                                                <div>Du bist interessiert an: <span id="actor_interests"></span></div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                <div id="edit_profil" class="ui-widget-content" data-tooltip="Profil bearbeiten">
                                    <i class="material-symbols-outlined">mode_edit</i>
                                </div>
                            </div>

                        </div>


                        <div id="user_settings">
                            <div class="ui-widget-header">Einstellungen zum User <span class="username"></span></div>
                            <div class="ui-widget-content">
                                <div>1 Nachricht kostet dem User <input type="text" id="pn_amount" maxlength="3"> Coins</div>
                                <div>Deine Webcam kostet dem User <input type="text" id="cam_amount" maxlength="3"> Coins pro Minute.</div>
                                <div>Der User bezahlt <input type="text" id="cam2cam_amount" maxlength="3"> Coins pro Minute wenn er seine Webcam sendet.</div>
                                
                                <div id="save_user_settings" class="ui-widget-content" data-tooltip="Einstellungen speichern">
                                    <i class="material-symbols-outlined">save</i>
                                </div>
                            </div>
                        </div>
                        
                        <div id="user_notes">
                            <div class="ui-widget-header">Notizen zum User <span class="username"></span></div>
                            <textarea id="edit_user_notes" ></textarea>
                            <script>
                                CKEDITOR.replace("edit_user_notes", {
                                    customConfig: "'.MCP_URL.'/fw/ckeditor/simple_config.js",
                                    resize_enabled: false,
                                    allowedContent: true,
                                    contentsCss : ".cke_editable{background-color:#ffffcc;font-family:verdana,arial;font-size:13px;}"
                                });
                            </script>
                            
                            <div id="save_user_notes" class="ui-widget-content" data-tooltip="Notizen speichern">
                                <i class="material-symbols-outlined">save</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="cambar" class="ui-widget-content"></div>
            <!--
            <div id="all_usercams" class="ui-widget-content">
            //-->

            </div>
        </div>
       
        <div id="playsound"></div>

        <div class="messenger_info_box" data-chat-id="">
            <div class="messenger_info">
                <div class="ui-widget-header" id="messenger_info_title"><span></span><i class="material-symbols-outlined">close</i></div>
                <div class="ui-widget-content" id="messenger_info_content"></div>
            </div>
        </div>
        
        <!--
        <div id="usercam_box">
            <div class="ui-widget-header" id="livecam_header">
                <table cellspacing="0" cellpadding="0">
                    <tr>
                        <td>Webcam vom User <span id="username"></span></td>
                        <td><i data-tooltip="Fenster schlie&szlig;en" class="material-symbols-outlined close_usercam">close</i></td>
                    </tr>
                </table>
            </div>
            <div class="ui-widget-content" id="livecam_content"></div>
        </div>
        //-->
        
        <div id="webcam_box">
            <div class="ui-widget-header" id="livecam_header">
                <table cellspacing="0" cellpadding="0">
                    <tr>
                        <td>Livecam - Konsole</td>
                        <td><i data-tooltip="Fenster minimieren" class="material-symbols-outlined minimize_webcam">expand_less</i></td>
                        <td><i data-tooltip="Livecam-Konsole schlie&szlig;en" class="material-symbols-outlined close_webcam">close</i></td>
                    </tr>
                </table>
            </div>
            <div class="ui-widget-content" id="livecam_content">

                <div id="publisher_box">
                    <div id="status">Press start to stream your Webcam.</div>
                    <div id="status_bar"></div>
                    <div id="publisher_container"></div>
                        <div id="controls" class="ui-widget-header">
                            <table>
                                <tr>
                                    <td>
                                        <div id="livecam_group_actors">
                                            Profil w&auml;hlen: <select></select>
                                        </div>
                                    </td>
                                    <td>
                                        <span id="microfone">
                                            <i id="mic_on" style="display:none;" class="material-symbols-outlined" data-tooltip="Mikrofon ausschalten">mic</i>
                                            <i id="mic_off" style="display:none;" class="material-symbols-outlined" data-tooltip="Mikrofon einschalten">mic_off</i>
                                        </div>

                                        <i class="webcam_stop start-btn material-symbols-outlined" data-tooltip="Webcam senden">videocam_off</i>
                                        <i class="start-btn material-symbols-outlined" data-tooltip="Webcam senden">play_arrow</i>
                                        <i style="display:none;" class="webcam_play stop-btn material-symbols-outlined" data-tooltip="Webcam beenden">videocam</i>
                                        <i style="display:none;" class="stop-btn material-symbols-outlined" data-tooltip="Webcam beenden">stop</i>
                                    </td>
                                    <td style="width:140px; text-align:center;">
                                        <div id="status_cam_off">Offline</div>
                                        <div id="status_cam_on" style="display:none;">Online</div>
                                    </td>
                                </tr>
                            </table>
                        </div>


                        <div style=" max-height: 278px; height: 100%;">
                            <div style="border-right:1px solid var(--borderColor, #cccccc); float:left; height:205px; width:235px; padding:2px">
                                <div style="padding:10px;">
                                    <div style="margin-bottom:5px; font-size:0.8rem;">Tragen Sie vor dem Senden, folgende Daten in Ihrer Streaming-Software ein. Streamen Sie dann Ihre
                                    Webcam mit Ihrer Software. Klicken Sie anschlie&szlig;end auf "Webcam senden".</div>
                                    <form id="publisher-inputs" class="form-horizontal">
                                        <div><b>Streamserver-URL (RTMP):</b></div>
                                        <div style="margin-bottom:10px;"><input type="text" id="serverUrl" name="serverUrl" value="'.$media_server_url.'"></div>
                                        <div><b>Stream-Key:</b></div>
                                        <div><input type="text" id="streamName" name="streamName" value="'.$streamname.'"></div>
                                    </form>
                                </div>


                                <!--
                                <div style="padding:10px;">
                                    <form id="publisher-inputs" class="form-horizontal">
                                        <input type="hidden" id="serverUrl" name="serverUrl" value="'.STREAMSERVER['server1']['rtmp_url'].'">
                                        <input type="hidden" id="streamName" name="streamName" value="">
                                        <input type="hidden" id="keyFrameInterval" name="keyFrameInterval" value="1">

                                        <div>
                                            <select style="width:180px" id="streamResolution">
                                                <optgroup label="16:9">
                                                    <option value="640x360">640x360 (nHD)</option>
                                                    <option selected value="1280x720">1280x720 (HD) - empfohlen &nbsp;</option>
                                                    <option value="1920x1080">1920x1080 (Full HD)</option>
                                                </optgroup>
                                                <optgroup label="4:3">
                                                    <option value="640x480">640x480 (VGA)</option>
                                                    <option value="800x600">800x600 (SVGA)</option>
                                                    <option value="1280x960">1280x960 - empfohlen &nbsp;</option>
                                                    <option value="1600x1200">1600x1200 (UXGA)</option>
                                                </optgroup>
                                            </select> <i class="help_icon material-symbols-outlined" data-tooltip="Hier kannst du angeben, mit welcher Aufl&ouml;sung du deine Webcam senden willst.<br /><br />Wenn du mit dem User per Cam2Cam interagieren m&ouml;chtest, empfehlen wir die kleinste Aufl&ouml;sung zu nutzen.<br /><br /><b>Gruns&auml;tzlich gilt:</b> Je h&ouml;her die Aufl&ouml;sung, um so l&auml;nger dauert es bis der Kunde dein Bild sieht.">help</i>
                                        </div>

                                        <div>
                                            <select style="width:180px" id="quality">
                                                <option value="low">Standard Qualit&auml;t - empfohlen f&uuml;r Cam2Cam</option>
                                                <option selected value="standard">Hohe Qualit&auml;t</option>
                                            </select> <i class="help_icon material-symbols-outlined" data-tooltip="Mit der <b>Standard Qualit&auml;t</b> wird dein Stream am schnellsten zum User &uuml;bertragen. Diese Einstellung ist zu empfehlen wenn du mit dem User per Cam2Cam interagieren m&ouml;chtest.<br /><br /><b>Hohe Qualit&auml;t</b> solltest du nur nutzen, wenn der Stream bis zum Kunden auch etwas l&auml;nger dauern darf.<br /><br /><b>Gruns&auml;tzlich gilt:</b> Je h&ouml;her die Qualit&auml;t, um so l&auml;nger dauert es bis der Kunde dein Bild sieht.">help</i>
                                        </div>

                                        <div>
                                            <a class="splitcam_info" href="javascript:;">Infos zum WebCam-Splitter</a>
                                        </div>

                                    </form>
                                </div>
                                //-->
                            </div>

                            <div class="cam_user">
                                <div id="cam_user_auto_on" class="ui-widget-header"><i class="material-symbols-outlined">check_box</i> User automatisch einschalten</div>
                                <div id="user" style="width:auto; height:200px; overflow-x:hidden; overflow-y:scroll;">Loading...</div>
                            </div>
                        </div>
                </div>
            </div>
        </div>

        <div class="splitcam_info_popup">
            <div style="text-align:right; top:20px; right:10px; position:absolute;">
                <a href="#" class="close_overlay" style="width:35px; padding:0px; text-align:center;"><b>&#x2715;</b></a>
            </div>';        
            include_once(MCP_DIR.'/includes/overlays/splitcam_info.php');
            $site .= '
            <div style="text-align:right; float:right;">
                <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
            </div>
        </div>
        <div id="overlay"></div>


        <script>
            
            var api_url = "'.API_URL.'";
            var mcp_url = "'.MCP_URL.'";            

            jQuery(document).ready(function() {
                jQuery(".group .ui-widget-content td:last-child").hide();
                ';
                if (!isset($_SESSION['my_chatgroup'])) {
                    $site .= '
                    var group_id = jQuery(".group i:first-child").attr("data-group-id");
                    jQuery(".group i[data-group-id=\""+group_id+"\"]").text("check_box_blank").removeClass("check_box_group").removeClass("check_box_blank_group");
                  
                    jQuery(".group .ui-widget-content td[data-group-id=\""+group_id+"\"]").show();

                    jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {set_my_chatgroup: group_id}).done(function(data){
                        jQuery(".group i[data-group-id=\""+group_id+"\"]").text("check_box").addClass("check_box_group");
                        jQuery(this).load_group_actors();
                    });
                    ';
                }
                $site .= '
                var active_group_id = jQuery(".group").find(".check_box_group").attr("data-group-id");

                jQuery(".group .ui-widget-content td[data-group-id=\""+active_group_id+"\"]").show();

                jQuery(".group .ui-widget-header").click(function() {
                    jQuery(".group .ui-widget-content td:last-child").hide();

                    var group_id = jQuery("i", this).attr("data-group-id");
                    jQuery(".group i").text("check_box_blank").removeClass("check_box_group").removeClass("check_box_blank_group");
                    jQuery("i", this).text("check_box").addClass("check_box_group");

                    jQuery(".group .ui-widget-content td[data-group-id=\""+group_id+"\"]").show();

                    jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {set_my_chatgroup: group_id});
                    
                    userlist();
                    get_notification_sound();
                    jQuery(this).load_group_actors();

                    if (jQuery("#webcam_box").is(":visible")) {
                        var publisher = jQuery("#publisher")[0]

                        if (typeof publisher.stop === "function") {
                            publisher.stop();
                            stop_button(event);
                        }
                    }

                })
            })
        </script>


        <script src="'.MCP_URL.'/Messenger/js/script.js?id=7"></script>
        <script src="'.MCP_URL.'/Messenger/js/useronline.js?id=9"></script>
        <script src="'.MCP_URL.'/Messenger/js/webcam.js?id=3"></script>
        <script src="'.MCP_URL.'/Messenger/js/usercams_bar.js?5"></script>
        <!-- <script src="'.MCP_URL.'/Messenger/js/usercam.js?id=3"></script> //-->
        <script src="'.MCP_URL.'/Messenger/js/chat_history_box.js?id=3"></script>
        <script src="'.MCP_URL.'/js/jquery.form.js"></script>

        <script type="text/javascript" src="//cdn.jsdelivr.net/emojione/1.5.2/lib/js/emojione.min.js"></script>

        <script type="text/javascript" src="//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>

        <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/fw/DataTables/media/css/jquery.dataTables_themeroller.css" />
        <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" />
    
        <link rel="stylesheet" href="'.MCP_URL.'/Messenger/emojiarea/wdt-emoji-bundle.css"/>
        <script type="text/javascript" src="'.MCP_URL.'/Messenger/emojiarea/emoji.min.js"></script>
        <script type="text/javascript" src="'.MCP_URL.'/Messenger/emojiarea/wdt-emoji-bundle.js"></script>
        ';
                
    } else {
       
        header('Location: '.MCP_DOMAIN);
        exit;
    }
    
    $site .= '
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-9606436-40"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag("js", new Date());

        gtag("config", "UA-9606436-40");
    </script>
</body>
</html>';

echo $site;

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>