<?php

 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!in_array('all', $_SESSION['employee_access_area']) AND !in_array('merchant', $_SESSION['employee_access_area'])) {
    header('Location: '.ACP_URL);
    exit;        
}

if (is_numeric($_GET['id'])) {
    $partner_id = abs($_GET['id']);
} else {
    $partner_id = p4c_escape_string($_GET['id']);
}

$merchant = new Merchant($mysql,$partner_id);

if ($merchant->id() == '') {
    echo 'Dieser Merchant existiert nicht';
    exit;
}

$merchant_id = $merchant->id();

$m['partner_id']     = $merchant->partner_id();
$m['username']       = $merchant->username('aes_decrypt');
$m['email']          = $merchant->email('aes_decrypt');
$m['birthday']       = $merchant->birthday('aes_decrypt');
$m['firstname']      = $merchant->firstname('aes_decrypt');
$m['surname']        = $merchant->surname('aes_decrypt');
$m['last_ip']        = $merchant->last_ip('aes_decrypt');
$m['last_ip_datetime'] = $merchant->last_ip_datetime();
$m['api_key']        = $merchant->api_key('aes_decrypt');

$rs_count_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($merchant_id)."';", __FILE__,__LINE__);
$count_actors = p4c_num_rows($rs_count_actors);

if (isset($_POST['save_settings'])) {
    
    $minimum_number_actor_profiles = $count_actors;
    
    if (isset($_POST['minimum_number_actor_profiles']) AND abs($_POST['minimum_number_actor_profiles']) > $minimum_number_actor_profiles) {
        $minimum_number_actor_profiles = abs($_POST['minimum_number_actor_profiles']);  
    }
    
    if(p4c_query("UPDATE `merchants` SET 
        `minimum_number_actor_profiles` = '".abs($minimum_number_actor_profiles)."'
        WHERE `id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__)
    ) {
        log_action('H&auml;ndler (PID:'.$m['partner_id'].') bearbeitet.');
        header('Location: '.ACP_URL.'/Haendler/'.$m['partner_id'].'?saved=2');
        exit;        
    }
}

if (isset($_GET['saved'])) {
    $site .= '
    <script type="text/javascript">
    // <![CDATA[
    	jQuery(document).ready(function() {
            jQuery("#tabs").tabs("option", "active", '.$_GET['saved'].');';
    
            if (isset($_GET['saved']) AND $_GET['saved'] == '2') {
                $site .= '
                jQuery(".msg_saved_settings").show();
                setTimeout(function() {jQuery(".msg_saved_settings").fadeToggle("slow", "linear");}, 4000);
                ';
            }
            
            $site .= ' 
        })
    // ]]>
    </script>';
}

$site .= '
<div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:20px;">'.$merchant->username('aes_decrypt').'</div>

<div id="tabs">
    <ul>
        <li><a href="#infos">H&auml;ndler-Infos</a></li>
        <li><a href="#actors">Profile ('.$count_actors.')</a></li>
        <li><a href="#settings">Einstellungen</a></li>
    </ul>

    <div id="infos">
        <table>
            <tbody>
                <tr>
                    <td style="width:500px; vertical-align:top;">

                        <div class="ui-widget-header" style="padding:5px 10px;">Benutzerdaten</div>
                        <div class="ui-widget-content" style="padding:10px; border-top:none;">
                            <div class="edit_title">ID:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$merchant_id.'" readonly disabled="disabled" /></div>

                            <div class="edit_title">PID (Pay4Coins-Partner-ID - <a href="'.Pay4Coins_ACP_URL.'/Haendler/'.$m['partner_id'].'" target="_blank">in Pay4Coins aufrufen</a>):</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['partner_id'].'" readonly disabled="disabled" /></div>

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

                        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">API-Key</div>
                        <div class="ui-widget-content" style="padding:10px; border-top:none;">
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['api_key'].'" readonly disabled="disabled" /></div>
                        </div>

                        <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Sonstiges</div>
                        <div class="ui-widget-content" style="padding:10px; border-top:none;">
                            <div class="edit_title">letzte bekannte IP:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['last_ip'].'" readonly disabled="disabled" /></div>

                            <div class="edit_title">IP geloggt am:</div>
                            <div class="edit_content" style="margin-bottom:8px;"><input type="text" value="'.$m['last_ip_datetime'].'" readonly disabled="disabled" /></div>
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
                    "sAjaxSource": "'.ACP_URL.'/includes/ajax/merchant_actors.php?merchant_id='.$merchant_id.'",
                    
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
        
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.$count_actors.' Profile</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle Darsteller-Profile aufgelistet.
        </div>

        <table id="table_actors" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:20px;">ID</th>
                    <th style="width:150px;">Profilname</th>
                    <th style="width:90px;">FSK16</th>
                    <th style="width:90px;">FSK18</th>
                    <th style="width:60px;">Status</th>
                    <th style="width:auto;">zuletzt Online</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

    </div>
    
    <div id="settings">
        <table>
            <tbody>
                <tr>
                    <td style="width:500px; vertical-align:top;">
                        <form action="'.ACP_URL.'/Haendler/'.$m['partner_id'].'" method="post">
                            
                            <div class="msg_saved_settings ui-state-highlight" style="display:none; padding:10px; margin-bottom:10px;">
                                Einstellungen gespeichert.                            
                            </div>
                            
                            <div class="ui-widget-header" style="padding:5px 10px; margin-top:10px;">Profile</div>
                            <div class="ui-widget-content" style="padding:10px; border-top:none;">
                                <div class="edit_title">Wie viele Profile darf dieser Merchant anlegen?</div>
                                <div class="edit_content" style="margin-bottom:8px;">
                                    <select name="minimum_number_actor_profiles">';
                                        $minimum = 1;

                                        if ($count_actors > 1) {
                                            $minimum = $count_actors;
                                        }
                                        for($i=$minimum; $i<=100; $i++) {
                                            if ($i == $minimum) {
                                                $info = ' &nbsp; ...wurden bereits angelegt.';                                            
                                            } else {
                                                $info = '';                                                
                                            }
                                            
                                            if ($i == $merchant->minimum_number_actor_profiles()) {
                                                $selected='selected="selected"';
                                            } else {
                                                $selected='';
                                            }

                                            $site .= '<option '.$selected.' value="'.$i.'">'.$i.$info.'</option>';
                                        }
                                    $site .= '
                                    </select>
                                </div>

                                <center><input type="submit" name="save_settings" class="button" value="Speichern" /></center>
                            </div>
                        </form>                        
                    </td>
                </tr>
            </tbody>
        </table>
    </div>


</div>        
';
