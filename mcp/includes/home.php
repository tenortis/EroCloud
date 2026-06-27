<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");
    
$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

//Prüfen ob schon eine Gruppe für die Profile erstellt wurde. Wenn nicht, dann jetzt anlegen.
$rs_groups = p4c_query("SELECT * FROM `groups` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `id` DESC;",__FILE__,__LINE__);
if (p4c_num_rows($rs_groups) === 0) {
    p4c_query("INSERT INTO `groups` SET
        `merchant_id`   = '".abs($_SESSION['merchant_id'])."',
        `group_id`      = '".p4c_escape_string("G-". strtoupper(randomString(8)))."',
        `group_name`    = 'Standartgruppe',
        `group_password`= '".p4c_escape_string(randomString(12))."';
    ",__FILE__,__LINE__);
    
    $group_id = p4c_insert_id();
    
    $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `username` ASC;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_actors) > 0) {
        while($actor_obj = p4c_fetch_object($rs_actors)) {
            $rs_check_group = p4c_query("SELECT * FROM `group_actors` WHERE `actor_id`='".abs($actor_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_check_group) === 0) {
                p4c_query("INSERT INTO `group_actors` SET
                    `merchant_id`   = '".abs($_SESSION['merchant_id'])."',
                    `group_id`      = '".abs($group_id)."',
                    `actor_id`      = '".abs($actor_obj->id)."';",__FILE__,__LINE__);
            }
        }
    }
}

$site .= '
<link rel="stylesheet" type="text/css" href="'.MCP_URL.'/css/uploadfile.css" />
<script type="text/javascript" src="'.MCP_URL.'/js/jquery.uploadfile.min.js"></script>

<link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
<script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
<script src="'.MCP_URL.'/fw/flatpickr/de.js"></script>

<script src="//cdn.ckeditor.com/4.6.2/full/ckeditor.js"></script>

<script type="text/javascript">
// <![CDATA[
	jQuery(document).ready(function() {
       
        jQuery("#api_key").click(function(){
            var copyDiv = jQuery("#api_key");
            copyDiv.focus();
            document.execCommand("SelectAll");
            document.execCommand("Copy", false, null);
            alert("API-Key in Zwischenablage kopiert.");
        });

    })

    
// ]]>
</script>

<style type="text/css">
<!--
    input.button.ui-widget {font-size:16px !important;}
-->
</style>

';

if (!isset($_GET['test'])) {

$site .= '
<div style="width:650px;">';
    $username = '';

    if ($_SESSION['logged_in_as'] == 'merchant') {
        
        include_once(MCP_DIR.'/includes/birthday_greeting.php');

        $site .= '
        <div style="margin-bottom:10px;">Hallo '.$merchant->firstname('aes_decrypt').', sch&ouml;n dass du zur&uuml;ck bist.</div>
        <div style="margin-bottom:15px;">
            Deine echten Daten, wie dein Name und deine Adresse sind f&uuml;r User nicht sichtbar.<br />
            User sehen nur die Daten, die du in deinem Darsteller-Profil hinterlegt hast.
        </div>
        ';

        /*
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-2"></i>
            <span>Aktuell gibt es technische Probleme beim Upload von Filmen. Wir arbeiten mit Hochdruck daran, das Problem zu beheben.</span>
        </div>
         */
        
        if ($merchant->accept_content_rules() == '0000-00-00 00:00:00') {
            if (isset($_POST['accept_content_rules']) AND isset($_POST['upload_content'])) {

                p4c_query("UPDATE `merchants` SET `accept_content_rules`='".date("Y-m-d H:i:s")."' WHERE
                    `id`='".abs($_SESSION['merchant_id'])."' AND `accept_content_rules`='0000-00-00 00:00:00' LIMIT 1;",__FILE__,__LINE__);
                
                header('Location: '.MCP_URL.'/Startseite');
                exit;
            }
            
            $site .= ' 
            <script>
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
                });
            </script>
            <form action="" method="post">
            
                <div class="alert alert-info" role="alert">
                    <span class="d-flex align-items-center">
                        <i class="bi bi-pencil-square me-3 fs-2"></i>
                        <span><strong>Die Nutzungsrichtlinien zur Vermarktung deines Contents wurden aktualisiert. Bitte &uuml;berpr&uuml;fe die &Auml;nderungen und best&auml;tige deine Zustimmung.</strong></span>
                    </span>
                    <hr>
                    '.
                    utf8_encode('<p>
                        Die aktualisierten Nutzungsrichtlinien stellen klar, dass das Urheberrecht beim Partner verbleibt, während Cipa Media eine nicht-exklusive Lizenz zur Vermarktung erhält.
                        Zudem wurde konkretisiert, dass Inhalte mindestens ein Jahr online bleiben, bevor sie gelöscht werden können, und dass bereits verkaufte Inhalte für Käufer weiterhin zugänglich bleiben,
                        sofern keine technischen Einschränkungen vorliegen. Außerdem wird die Einhaltung gewerblicher Schutz- und Urheberrechte betont, um rechtliche Verstöße zu vermeiden.
                    </p>').'
                     
                    <a class="btn btn-success content_ruels">neue Nutzungsrichtlinien anzeigen und akzepieren</a>
                </div>
                <input type="hidden" name="accept_content_rules" value="true">
                ';
                include_once(MCP_DIR.'/includes/overlays/content_rules.php');
                $site .= '
            </form>
            ';
        }
        
        $rs_actors = p4c_query("SELECT * FROM `actors`WHERE (`status`='blocked' OR `status`='inactive') AND `merchant_id`='".abs($_SESSION['merchant_id'])."'LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_actors) > 0) {
            $site .= '
            <div class="alert alert-warning">
                Mindestens ein <a href="'.MCP_URL.'/Actors">Profil</a> von dir ist noch nicht aktiviert. So lange das Profil nicht aktiviert ist, siehst du im Messenger keine Kunden.
                Du kannst weder mit dem Profil chatten noch deine Webcam senden. Solltest du Fragen dazu haben, wende dich bitte an den <a class="contact_footer" href="#">Support</a>.
            </div>
            ';
        }
        
        if ($count_actors > 0) {
            include_once(MCP_DIR.'/includes/home_stats.inc.php');
        }

    } else {
        $rs_group = p4c_query("SELECT * FROM `groups` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($_SESSION['my_chatgroup'])."' LIMIT 1;",__FILE__,__LINE__);
        // Wenn die Gruppe nicht mehr existiert
        if (p4c_num_rows($rs_group) == 0) {
            header('Location: '.MCP_URL.'/index.php?logout');
            exit;
        }
        
        $group_obj = p4c_fetch_object($rs_group);

        $site .= '
        <div style="margin-bottom:15px;">Hallo'.$username.', sch&ouml;n dass du zur&uuml;ck bist.</div>';

        if ($count_actors > 0 AND $group_obj->show_commision == '1') {
            include_once(MCP_DIR.'/includes/home_stats.inc.php');
        }
        
    }
    
    $site .= '  
</div>
';
}  
    
if (isset($_GET['test'])) {

$site .= ' 
<style>

    .home_grid {
        max-width: 1050px;
        display: grid;
        grid-gap: 15px;
        grid-template-columns: 1fr 1fr 1fr;
    }
    
    .column_welcome {
        grid-column: 1 / 4;
    }
    
    .column_stats {
        grid-column: 3 / 4;
    }
    .column_amoredea {
        grid-column: 1 / 3;
    }
    
    .column_actor_error {
        grid-column: 1 / 4;
    }

    @media only screen and (max-width: 1000px){
        .home_grid {
            min-width:350px;
            grid-template-columns: 1fr 1fr;
        } 
    
        .column_welcome {
            grid-column: 1 / 3;
        }
       
        .column_amoredea {
            grid-column: 1 / 3;
        }
        
        .column_stats {
            grid-column: 1 / 3;
        }
        
        .column_actor_error {
            grid-column: 1 / 3;
        }

    }
</style>

<div class="home_grid">';
    $username = '';

    if ($_SESSION['logged_in_as'] == 'merchant') {
        
        include_once(MCP_DIR.'/includes/birthday_greeting.php');

        $site .= '
        <div class="column_welcome">
            <div style="margin-bottom:10px;">Hallo '.$merchant->firstname('aes_decrypt').', sch&ouml;n dass du zur&uuml;ck bist.</div>
            <div>
                Deine echten Daten, wie dein Name und deine Adresse sind f&uuml;r User nicht sichtbar.<br />
                User sehen nur die Daten, die du in deinem Darsteller-Profil hinterlegt hast.
            </div>
        </div>
        ';
        
        $rs_actors = p4c_query("SELECT * FROM `actors`WHERE (`status`='blocked' OR `status`='inactive') AND `merchant_id`='".abs($_SESSION['merchant_id'])."'LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_actors) > 0) {
            $site .= '
            <div class="column_actor_error ui-state-error" style="padding:10px;">
                Mindestens ein <a href="'.MCP_URL.'/Actors">Profil</a> von dir ist noch nicht aktiviert. So lange das Profil nicht aktiviert ist, siehst du im Messenger keine Kunden.
                Du kannst weder mit dem Profil chatten noch deine Webcam senden. Solltest du Fragen dazu haben, wende dich bitte an den <a class="contact_footer" href="#">Support</a>.
            </div>
            ';
        }
        
        $site .= '
        <style>
           .column_amoredea #logo {
                font-size: 2.5em;
                display: inline-block;
            }

            .column_amoredea .header {
                padding:5px 10px;
                background-color:#1e1e1e;
            }

            .column_amoredea .header table {width:100%;}
            .column_amoredea .header table td {text-align:center; width:33%;}

            .column_amoredea #logo span:first-child {
                color: #fff;
            }
            
            .column_amoredea  #logo span:last-child {
                color: #ff5400;
            }

            .column_amoredea #neu,
            .column_amoredea #neu2 {
                font-size: 2.5em;
                display: inline-block;
                color: #8bc34a;
            }

            .column_amoredea #neu2 {
                
            }


        </style>

        <div class="column_amoredea">
            ';
            if ($_SESSION['logged_in_as'] == 'merchant' AND $count_actors > 0) {
                $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`!='deleted' ORDER BY `username` ASC;",__FILE__,__LINE__);
                $actor_obj = p4c_fetch_object($rs_actors);
                
                $site .= '
                <div class="ui-widget-content">
                    <div class="header">
                        <table>
                            <tr>
                                <td id="neu">Jetzt NEU!</div></td>
                                <td id="logo"><span>amore</span><span>dea</span></td>
                                <td id="neu2">Jetzt NEU!</td>
                            </tr>
                        </table>      
                    </div>
                    <div style="padding:10px; font-size:15px; text-align:justify;">
                        <b>amoredea</b> ist ein neues Projekt von uns und bietet dir die M&ouml;glichkeit, deinen Content auf eine neue Art und Weise zu vermarkten. 
                        Deine User k&ouml;nnen dir Trinkgeld/ <b>Tribut zahlen ohne sich zu registrieren</b> und ohne sich einzuloggen. 
                        Sie k&ouml;nnen deine Filme und Fotoalben kommentieren und auch liken. Links im Men&uuml; kannst du die Kommentare deiner User einsehen und verwalten.
                        <div style="margin-top:8px;  font-size:15px; ">
                            Starte jetzt mit deiner eigenen Seite von amoredea: <a href="'.amoredea_URL.'/'.$actor_obj->username.'" target="_blank">'.amoredea_URL.'/'.$actor_obj->username.'</a>
                        </div>';
                        if ($count_actors > 1) {
                            $site .= 'Alle Links zu deinen Profilen findest du im Men&uuml; unter "<a href="https://erocloud.net/Actors">Alle Profile anzeigen</a>".';
                        }
                        $site .= ' 
                    </div>
                </div>';
            }
            $site .= '
        </div>
        <div class="column_stats">';
            if ($count_actors > 0) {
                include_once(MCP_DIR.'/includes/home_stats.inc.php');
            }
            $site .= ' 
        </div>';
        
    } else {
        $rs_group = p4c_query("SELECT * FROM `groups` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `id`='".abs($_SESSION['my_chatgroup'])."' LIMIT 1;",__FILE__,__LINE__);
        // Wenn die Gruppe nicht mehr existiert
        if (p4c_num_rows($rs_group) == 0) {
            header('Location: '.MCP_URL.'/index.php?logout');
            exit;
        }
        
        $group_obj = p4c_fetch_object($rs_group);

        $site .= '
        <div class="column_welcome" style="margin-bottom:15px;">
            Hallo'.$username.', sch&ouml;n dass du zur&uuml;ck bist.
        </div>
        <div class="column_amoredea">
            <div class="ui-widget-content" style="height:150px;">
                
            </div>
        </div>
        <div class="column_stats">';
            if ($count_actors > 0 AND $group_obj->show_commision == '1') {
                include_once(MCP_DIR.'/includes/home_stats.inc.php');
            }
            $site .= ' 
        </div>';            
    }
    
    $site .= '

 </div>
';

}
            
?>