<?php
 
if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

# ZrGbeJpnGvnb
# G-U3DF7UWU
# G-VREPPZTA
# XjFwPtUzYFr4


if (isset($_POST['add_actor'])) {
    #print_r($_POST);
    #exit;
    
    $actor_id = abs($_POST['actor_id']);
    $group_id = abs($_POST['group_id']);
    
    if ($actor_id > 0 AND $group_id > 0) {
        $rs_check_group = p4c_query("SELECT * FROM `group_actors` WHERE `actor_id`='".abs($actor_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_check_group) == 0) {
            p4c_query("INSERT INTO `group_actors` SET
                `merchant_id`   = '".abs($_SESSION['merchant_id'])."',
                `group_id`      = '".abs($group_id)."',
                `actor_id`      = '".abs($actor_id)."';",__FILE__,__LINE__);
        } else {
            
            p4c_query("UPDATE `group_actors` SET
                `group_id`      = '".p4c_escape_string($group_id)."'
            WHERE 
                `merchant_id`   = '".abs($_SESSION['merchant_id'])."' AND
                `actor_id`      = '".abs($actor_id)."' LIMIT 1;
            ",__FILE__,__LINE__);
        }

        header('Location: '.MCP_URL.'/Groups?ok=new-group');
        exit;
    }    
}

if (isset($_POST['new_group'])) {
    p4c_query("INSERT INTO `groups` SET
        `merchant_id`   = '".abs($_SESSION['merchant_id'])."',
        `group_id`      = '".p4c_escape_string("G-". strtoupper(randomString(8)))."',
        `group_password`= '".p4c_escape_string(randomString(12))."';
    ",__FILE__,__LINE__);
    
    header('Location: '.MCP_URL.'/Groups?ok=new-group');
    exit;
}

if (isset($_POST['delete_group'])) {
    
    $group_id = abs($_POST['group_id']);
    
    $rs_check_group = p4c_query("SELECT * FROM `group_actors` WHERE `group_id`='".abs($group_id)."' LIMIT 1;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_check_group) > 0) {
        header('Location: '.MCP_URL.'/Groups?error=del-group-not-empty');
        exit;        
    }
    p4c_query("DELETE FROM `groups` WHERE 
        `merchant_id`   = '".abs($_SESSION['merchant_id'])."' AND
        `id`            = '".abs($group_id)."' LIMIT 1;
    ",__FILE__,__LINE__);
    
    header('Location: '.MCP_URL.'/Groups?ok=del-group');
    exit;
}

if (isset($_POST['edit_group'])) {
    
    $post_email = '';
    
    if (isset($_POST['group_email']) AND trim($_POST['group_email']) != '') {
        $post_email = filter_input(INPUT_POST, 'group_email', FILTER_VALIDATE_EMAIL);
        if (trim($post_email) === '') {
            header('Location: '.MCP_URL.'/Groups?error=email-false');
            exit;        
        }
    }
    
    if (isset($_POST['show_commision'])) {
        $show_commision = 1;
    } else {$show_commision = 0;}
    
    $post_pass = trim($_POST['group_password']);
    
    $post_name = filter_input(INPUT_POST, 'group_name', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    
    p4c_query("UPDATE `groups` SET 
        `group_email`   = '".p4c_escape_string($post_email)."',
        `group_password`= '".p4c_escape_string($post_pass)."',
        `group_name`    = '".p4c_escape_string($post_name)."',
        `show_commision`= '".abs($show_commision)."'
    WHERE 
        `merchant_id`   = '".abs($_SESSION['merchant_id'])."' AND
        `id`            = '".p4c_escape_string($_POST['group_id'])."' LIMIT 1;
    ",__FILE__,__LINE__);

    header('Location: '.MCP_URL.'/Groups?ok=edit-group');
    exit;

}

$site .= '
<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        
        jQuery(".show_pass").click(function(){
            var show_pass_icon = jQuery(this).text();
            var group_id = jQuery(this).attr("data-group-id");
            if (show_pass_icon === "visibility_off") {
                jQuery(this).text("visibility");
                jQuery("#g_"+group_id+" [name=\'group_password\']").attr("type","text");
                
            } else {
                jQuery(this).text("visibility_off");
                jQuery("#g_"+group_id+" [name=\'group_password\']").attr("type","password");
            }
        })

    })
   
// ]]>
</script>

<style type="text/css">
    .show_pass {
        font-size:12px;
        cursor:pointer;
    }
    
    .group_name {
        width:200px;
        font-size:12px;
        padding:0;
    }
    
    .edit_content input[type="text"],
    .edit_content input[type="password"]{
        font-size:14px !important;
    }
</style>

<div style="width:710px;">
    <h1 class="h4">Gruppen verwalten</h1>

    <div class="ui-state-error" style="padding:10px; margin-bottom:20px;">
        Der Bereich "Gruppen" ist nur f&uuml;r dich interessant wenn du mehrere Darstellerprofile benutzt oder Studiobetreiber bist.
    </div>

    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
        Solltest du mehrere Profile im Messenger nutzen, geben dir "Gruppen" die M&ouml;glichkeit diese Profile in verschiedene Gruppen zu unterteilen.
        Bist du ein Studiobetreiber, hast du mit "Gruppen" die M&ouml;glichkeit, jedem Darsteller (Profil) ein eigenes "Studio-Login" zu vergeben.<br />
        <br />
        Gruppen bekommen eigene Zugangsdaten (einen "Studio-Login"). Somit k&ouml;nnen sich Gruppen mit diesen Zugangsdaten in einen eigenen Bereich einloggen.<br />
        <br />
        <b>Als "Studio-Login" wird die Gruppen-ID und das vergebene Passwort benutzt.</b>
        <div style="margin-top:10px;">Studio-Login: <span style="font-family:monospace; color:#3399ff">'.MCP_URL.'</span></div>
    </div>
    
    <div style="margin-bottom:20px;">
        <form action="" method="post">
            <input type="submit" name="new_group" class="button" value="Neue Gruppe erstellen" />
        </form>
    </div>
    ';
    
    if (isset($_GET['ok'])) {
        if ($_GET['ok'] === 'new-group') {
            $mess = 'Gruppe erstellt.';
        } else if ($_GET['ok'] === 'edit-group') {
            $mess = '&Auml;nderungen gespeichert.';
        } else if ($_GET['ok'] === 'del-group') {
            $mess = 'Gruppe gel&ouml;scht.';
        }
        
        if (isset($mess) AND trim($mess) != '') {
            $site .= '
            <script>// <![CDATA[
                jQuery(document).ready(function() {
                    setTimeout(function() {
                        jQuery(".ok").fadeOut("slow");
                    },5000);

                    if (typeof EroCloudMessenger == "undefined" || EroCloudMessenger.closed) {
                        EroCloudMessenger = window.open("'.MCP_URL.'/Messenger", "EroCloudMessenger");
                    } else {
                        EroCloudMessenger.focus();
                    }

                })
            // ]]></script>
            <div class="ui-state-highlight ok" style="padding:10px; margin-bottom:20px">'.$mess.'</div>';
        }
    }
    
    if (isset($_GET['error'])) {
        if ($_GET['error'] === 'email-false') {
            $mess = 'Bitte gib eine korrekte E-Mail-Adresse an.';
        } else if ($_GET['error'] === 'del-group-not-empty') {
            $mess = 'Die Gruppe kann nicht gel&ouml;scht werden. Es befindet sich noch mindesten ein Profil in der Gruppe.';
        }
        
        if (isset($mess) AND trim($mess) != '') {
            $site .= '
            <div class="ui-state-error error" style="padding:10px; margin-bottom:20px">'.$mess.'</div>';
        }
    }

    $rs_groups = p4c_query("SELECT * FROM `groups` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_groups) > 0) {
        while($group_obj = p4c_fetch_object($rs_groups)) {
            if ($group_obj->group_name === '') {
                $group_name = $group_obj->group_id;
            } else {
                $group_name = $group_obj->group_name;
            }
            #G-RFI6LGBY
            #120718
            $site .= '
            <form action="'.MCP_URL.'/Groups" method="post">
                <div class="ui-widget-header" style="padding:5px 10px;">
                    <table style="width:100%">
                        <tr>
                            <td style="width:70%; text-align:left;">
                                <span style="font-weight:normal;">Gruppenname:</span> <input autocomplete="off" type="text" class="group_name" name="group_name" placeholder="Gruppenname" value="'.$group_name.'" />
                            </td>
                            <td style="width:30%; text-align:right;">
                                <span style="font-weight:normal;">Gruppen-ID:</span> '.$group_obj->group_id.'
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="ui-widget-content" id="g_'.abs($group_obj->id).'" style="padding:10px; border-top:none; margin-bottom:20px;">
                    <div class="edit_title">Hier kannst du eine E-Mail-Adresse angeben.<br />
                    Diese wird unteranderem ben&ouml;tigt um das Passwort zu versenden.</div>
                    <div class="edit_content" style="margin-bottom:8px;">
                        <input type="text" name="group_email" value="'.strip_tags($group_obj->group_email).'" placeholder="E-Mail-Adresse" style="width:calc(100% - 20px); font-size:18px;" />
                    </div>

                    <div class="edit_title">Hier kannst du ein neues Passwort f&uuml;r diese Gruppe vergeben.</div>
                    <div class="edit_content" style="margin-bottom:40px;">
                        <input autocomplete="new-password" type="password" name="group_password" value="'.strip_tags($group_obj->group_password).'" placeholder="Bitte ein Passwort angeben." style="width:calc(100% - 20px); font-size:18px;" />
                        <i class="material-symbols-outlined show_pass" data-group-id="'.abs($group_obj->id).'">visibility_off</i>
                    </div>
                    
                    <div style="margin-bottom:10px;">
                        <div style="display:inline-block; width:61%; vertical-align:top;">
                            <h2>Profile in dieser Gruppe</h2>';
                            $rs_group_actors = p4c_query("SELECT * FROM `group_actors` LEFT JOIN `actors` ON `group_actors`.`actor_id`=`actors`.`id` WHERE `group_actors`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND `group_actors`.`group_id`='".p4c_escape_string($group_obj->id)."';",__FILE__,__LINE__);
                            if (p4c_num_rows($rs_group_actors) === 0) {
                                $site .= '<div style="color:#ff0000;">Diesr Gruppe wurde noch kein Profil zugeordnet.</div>';

                            } else {
                                while($profil_obj = p4c_fetch_object($rs_group_actors)) {
                                    $avatar = MCP_URL.'/ProfilePicture/'.$profil_obj->profile_image_fsk16;


                                    $site .= '
                                    <div style="margin-top:3px;">
                                        <img src="'.$avatar.'" style="vertical-align:middle; width:30px; height:30px; margin-right:5px;" /> <a href="/Actor/'.$profil_obj->id.'"">'.$profil_obj->username.'</a>
                                    </div>';
                                }
                            }
                            $rs_actors = p4c_query("SELECT * FROM `actors` LEFT JOIN `group_actors` ON `actors`.`id`=`group_actors`.`actor_id` WHERE `group_actors`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND `group_actors`.`group_id`!='".abs($group_obj->id)."';",__FILE__,__LINE__);
                            if (p4c_num_rows($rs_actors) > 0) {
                                $site .= '
                                <div style="margin:10px 0;">
                                    Profil <select name="actor_id"><option value="0">---</option>';
                                    while ($actor_obj = p4c_fetch_object($rs_actors)) {
                                        $site .= '<option value="'.$actor_obj->actor_id.'">'.$actor_obj->username.'</option>';
                                    }
                                    $site .= '</select> in diese Gruppe <input type="submit" name="add_actor" value="verschieben" />
                                </div>';
                            }
                            $site .= '
                        </div>';
                                
                        if ($group_obj->show_commision == '1') {$checked_show_commision='checked';} else {$checked_show_commision='';}
                        $site .= '
                        <div style="display:inline-block;  width:37%;  vertical-align:top;">
                            <h2>Gruppenrechte</h2>
                            <input '.$checked_show_commision.' style="vertical-align:text-top;" type="checkbox" id="show_commision" name="show_commision" /> <label for="show_commision">Provision anzeigen</label>
                        </div>
                    </div>

                    <div style="text-align:right; padding-top:10px; border-top:1px solid">
                        <input type="hidden" value="'.abs($group_obj->id).'" name="group_id" />
                        <table style="width:100%">
                            <tr>
                                <td style="width:50%; text-align:left;">
                                    <input type="submit" name="delete_group" class="button" value="Gruppe l&ouml;schen" />
                                </td>
                                <td style="width:50%">
                                    <input type="submit" name="edit_group" class="button" value="Speichern" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </form>';
        }
    }
    $site .= '
</div>
';


?>
