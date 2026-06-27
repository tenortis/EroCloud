<?php


if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!in_array('all', $_SESSION['employee_access_area']) AND !in_array('user', $_SESSION['employee_access_area'])) {
    header('Location: '.ACP_URL);
    exit;        
}


$member_id = abs($_GET['id']);

include(SOURCEDIR.'/includes/klassen/member.inc.php');
$member = new Member($member_id, false);

if ($member->id() == '') {
    echo 'Dieser User existiert nicht';
    exit;
}

$m['username']      = $member->username();
$m['email']         = $member->email();
$m['birthday']      = $member->birthday();
$m['lastonline']    = $member->lastonline();
$m['amount_coins']  = $member->amount_coins();
$m['online_device'] = $member->online_device();
$m['p4c_shop_id']   = $member->p4c_shop_id();
$m['amount_coins']   = $member->amount_coins();

$rs_count_messages = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE `von`='member' AND `von_id`='".abs($member_id)."'", __FILE__,__LINE__);
$count_messanges = p4c_num_rows($rs_count_messages);

$rs_count_chats = p4c_query("SELECT COUNT(*) FROM `chat_messages_history` WHERE (`von`='member' AND `von_id`='".abs($member_id)."') OR (`von`='actor' AND `an_id`='".abs($member_id)."') GROUP BY `chat_id`", __FILE__,__LINE__);
$count_chats = p4c_num_rows($rs_count_chats);

// Site-Klasse einbinden 
include_once(SOURCEDIR.'/includes/klassen/site.inc.php');
$website = new Site($mysql,$m['p4c_shop_id']);

$site .= '
<div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:20px;">'.$m['username'].'</div>

<div id="tabs">
    <ul>
        <li><a href="#infos">User-Infos</a></li>
        <li><a href="#actors">Chats ('.$count_chats.')</a></li>
    </ul>

    <div id="infos">
        <table>
            <tbody>
                <tr>
                    <td style="width:500px; vertical-align:top;">

                        <div class="ui-widget-header" style="padding:5px 10px;">Benutzerdaten</div>
                        <div class="ui-widget-content" style="padding:10px; border-top:none;">
                            <div class="edit_title">ID:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$member_id.'" readonly disabled="disabled" /></div>

                            <div class="edit_title">Username:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['username'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">E-Mail-Adresse: (<a href="mailto:'.$m['email'].'">E-Mail senden</a>)</div>
                            <div class="edit_content">
                                <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['email'].'" readonly disabled="disabled" /></div>
                            </div>
                                    
                            <div class="edit_title">Geburtsdatum:</div>
                            <div class="edit_content">
                                <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['birthday'].'" readonly disabled="disabled" /></div>
                            </div>
                        </div>

                        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Sonstiges</div>
                        <div class="ui-widget-content" style="padding:10px; border-top:none;">
                            <div class="edit_title">Coins:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['amount_coins'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">Pay4Coins-Shop-ID: (<a href="'.ACP_URL.'/Site/'.$m['p4c_shop_id'].'" target="_blank">anzeigen</a>):</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['p4c_shop_id'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">Domain:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$website->get_var("domain").'" readonly disabled="disabled" /></div>                        

                            <div class="edit_title">zuletzt online:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['lastonline'].'" readonly disabled="disabled" /></div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
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
                    "iDisplayLength": 50,
                    "aaSorting": [[ 3, "desc" ]],
                    //"bProcessing": true,
                    //"bDeferRender": true,
                    
                    "bProcessing": true,
                    //"bServerSide": true,
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/user_chats.php?member_id='.abs($member_id).'",
                    "aoColumns": [
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"},
                        {"sClass": "center"}
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
        
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.$count_chats.' Chats</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle Chats mit des User aufgelistet.
        </div>

        <table id="table_actors" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:20px;">Chat-ID</th>
                    <th style="width:150px;">Darsteller</th>
                    <th style="width:100px;">Profil-ID</th>
                    <th style="width:90px;">Nachrichten</th>
                    <th style="width:150px;">letzte Nachricht am</th>
                    <th style="width:auto;">letzte Nachricht</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

    </div>


</div>        
';
