<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!isset($_GET['id'])) {
    header('Location: '.ACP_URL.'/alle-Nachrichten');
    exit;
}

$chat_id = preg_replace ( '/[^a-z0-9_]/i', '', $_GET['id']);

$rs_count_messages = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE `chat_id`='". p4c_escape_string($chat_id)."'", __FILE__,__LINE__);
$count_messanges = p4c_result($rs_count_messages,0);

$rs_partners = p4c_query("SELECT `von`, `von_id`, `an_id`, `p4c_shop_id` FROM `chat_messages_history` WHERE `chat_id`='". p4c_escape_string($chat_id)."';", __FILE__,__LINE__);

$partners_obj = p4c_fetch_object($rs_partners);

if ($partners_obj->von == 'actor') {
    $actor_id = $partners_obj->von_id;
    $member_id =  $partners_obj->an_id;

    $rs_actor = p4c_query("SELECT * FROM `actors` WHERE `id`='".abs($actor_id)."' LIMIT 1;",__FILE__,__LINE__);
    $user_obj = p4c_fetch_object($rs_actor);

    $username = '<span style="color:#cd0a0a">'.$user_obj->username.'</span>';

} else {
    $actor_id = $partners_obj->an_id;
    $member_id =  $partners_obj->von_id;

    $rs_users = p4c_query("SELECT * FROM `members` WHERE `id`='".abs($user_id)."' LIMIT 1;",__FILE__,__LINE__);
    $user_obj = p4c_fetch_object($rs_users);

    $username = '<span style="color:#3399ff">'.$user_obj->username.'</span>';
}

$p4c_shop_id = $partners_obj->p4c_shop_id;

// Site-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/site.inc.php');
$website = new Site($mysql,$p4c_shop_id);

include(SOURCEDIR.'/includes/klassen/member.inc.php');
$member = new Member($member_id, false);

include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
$actor = new Actor($actor_id);

$site .= '
<div class="ui-widget-header" style="padding:10px; font-size:20px;">Gespr&auml;ch zw. <span style="color:#cd0a0a;">'.$actor->get("username").'</span> und <span style="color:#3399ff;">'.$member->username().'</span> auf '.$website->get_var('domain').'</div>
<div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
    Geschriebene Nachrichten: '.$count_messanges.'
</div>


<div id="tabs">
    <ul>
        <li><a href="#actors">alle Nachrichten</a></li>
    </ul>
   
    <div id="actors">
        
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
                               
               
            	oTable = jQuery("#table_actors").dataTable({
                    "bJQueryUI": true,
                    "iDisplayLength": 100,
                    "aaSorting": [[ 0, "desc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,
                    
                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/chat.php?id='.$chat_id.'",
                    "aoColumns": [
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "left"}
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

        <table id="table_actors" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:150px;">gesendet am</th>
                    <th style="width:90px;">User</th>
                    <th style="width:auto;">Nachricht</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

    </div>


</div>        
';
