<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
/*
function getFolderSize($folderPath) {
    $size = 0;

    if (!is_dir($folderPath)) {
        die("? Fehler: '$folderPath' ist kein g�ltiges Verzeichnis.");
    }

    try {
        // Erst alle Verzeichnisse einlesen und "lost+found" herausfiltern
        $directories = array_diff(scandir($folderPath), array('.', '..', 'lost+found'));

        foreach ($directories as $dir) {
            $subPath = $folderPath . DIRECTORY_SEPARATOR . $dir;

            if (!is_readable($subPath)) {
                echo "?? Hinweis: '$subPath' kann nicht gelesen werden, wird �bersprungen.".PHP_EOL;
                continue;
            }

            if (is_dir($subPath)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($subPath, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    $size += $file->getSize();
                }
            }
        }
    } catch (Exception $e) {
        die("? Fehler beim Zugriff auf das Verzeichnis: " . $e->getMessage());
    }

    return $size;
}

$folder = '/var/www/web1/cloud_storage';

if (!file_exists($folder)) {
    die("? Fehler: Das Verzeichnis '$folder' existiert nicht.");
}

$folder_size = getFolderSize($folder);

echo "? $folder / " . number_format($folder_size / 1024 / 1024 / 1024, 2) . " GB".PHP_EOL;
*/



 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (isset($_POST['admin_notes_abbort']) AND $settings->get_var("admin_notes_edit") == $_SESSION['employee_id']) {
    p4c_query("UPDATE `settings` SET `value`='0' WHERE `variable`='admin_notes_edit' LIMIT 1;");
    header('Location: '.ACP_URL.'/Startseite');
    exit;
} else if (isset($_POST['admin_notes_edit']) AND $settings->get_var("admin_notes_edit") == 0) {
    p4c_query("UPDATE `settings` SET `value`='".abs($_SESSION['employee_id'])."' WHERE `variable`='admin_notes_edit' LIMIT 1;");
    header('Location: '.ACP_URL.'/Startseite');
    exit;
} else if (isset($_POST['admin_notes']) AND isset($_POST['admin_notes_save']) AND $settings->get_var("admin_notes_edit") == $_SESSION['employee_id']) {
    p4c_query("UPDATE `settings` SET `value`='".p4c_escape_string(utf8_encode($_POST['admin_notes']))."' WHERE `variable`='admin_notes' LIMIT 1;");
    p4c_query("UPDATE `settings` SET `value`='0' WHERE `variable`='admin_notes_edit' LIMIT 1;");
    header('Location: '.ACP_URL.'/Startseite');
    exit;    
}

if (isset($_POST['employee_notes'])) {
    p4c_query("UPDATE `employee` SET `notes`='".p4c_escape_string(utf8_encode($_POST['employee_notes']))."' WHERE `id`='".abs($_SESSION['employee_id'])."' LIMIT 1;");
    header('Location: '.ACP_URL.'/Startseite');
    exit;
}

$site .='
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
                       
       
    	oTable = jQuery("#table_employee_history").dataTable({
            "bJQueryUI": true,
            "iDisplayLength": 10,
            "aaSorting": [[ 1, "desc" ]],
            "bProcessing": true,
            
            "aoColumns": [
                {"sClass": "center"},
                {"sClass": "center"},
                {"sClass": "center"},
                {"sClass": "center", "sType": "title-string"},
            ],
            
            "oLanguage": {
                "sProcessing":   "Bitte warten...",
                "sLengthMenu":   "_MENU_ Einträge anzeigen",
                "sZeroRecords":  "Keine Einträge vorhanden.",
                "sInfo":         "_START_ bis _END_ von _TOTAL_ Einträgen",
                "sInfoEmpty":    "0 bis 0 von 0 Einträgen",
                "sInfoFiltered": "(gefiltert von _MAX_  Einträgen)",
                "sInfoPostFix":  "",
                "sSearch":       "Suchen",
                "sUrl":          "",
                "oPaginate": {
                    "sFirst":    "Erster",
                    "sPrevious": "Zurück",
                    "sNext":     "Nächster",
                    "sLast":     "Letzter"
                }
            }                            
    	});
        

    	oTable = jQuery("#table_users_online").dataTable({
            "bJQueryUI": true,
            "iDisplayLength": 10,
            "aaSorting": [[ 1, "desc" ]],
            "bProcessing": true,
            
            "aoColumns": [
                {"sClass": "center"},
                {"sClass": "center"},
                {"sClass": "center"},
                {"sClass": "center", "sType": "title-string"},
            ],
            
            "oLanguage": {
                "sProcessing":   "Bitte warten...",
                "sLengthMenu":   "_MENU_ Einträge anzeigen",
                "sZeroRecords":  "Keine Einträge vorhanden.",
                "sInfo":         "_START_ bis _END_ von _TOTAL_ Einträgen",
                "sInfoEmpty":    "0 bis 0 von 0 Einträgen",
                "sInfoFiltered": "(gefiltert von _MAX_  Einträgen)",
                "sInfoPostFix":  "",
                "sSearch":       "Suchen",
                "sUrl":          "",
                "oPaginate": {
                    "sFirst":    "Erster",
                    "sPrevious": "Zurück",
                    "sNext":     "Nächster",
                    "sLast":     "Letzter"
                }
            }                            
    	});
        
    	oTable = jQuery("#table_actors_online").dataTable({
            "bJQueryUI": true,
            "iDisplayLength": 10,
            "aaSorting": [[ 1, "desc" ]],
            "bProcessing": true,
            
            "aoColumns": [
                {"sClass": "center"},
                {"sClass": "center"},
                {"sClass": "center", "sType": "title-string"},
            ],
            
            "oLanguage": {
                "sProcessing":   "Bitte warten...",
                "sLengthMenu":   "_MENU_ Einträge anzeigen",
                "sZeroRecords":  "Keine Einträge vorhanden.",
                "sInfo":         "_START_ bis _END_ von _TOTAL_ Einträgen",
                "sInfoEmpty":    "0 bis 0 von 0 Einträgen",
                "sInfoFiltered": "(gefiltert von _MAX_  Einträgen)",
                "sInfoPostFix":  "",
                "sSearch":       "Suchen",
                "sUrl":          "",
                "oPaginate": {
                    "sFirst":    "Erster",
                    "sPrevious": "Zurück",
                    "sNext":     "Nächster",
                    "sLast":     "Letzter"
                }
            }                            
    	});

    })
// ]]>
</script>
';
$rs_last_online_members = p4c_query("SELECT * FROM `members` WHERE `lastonline` >= '".date("Y-m-d H:i:s", strtotime("-10 minutes"))."' ORDER BY `lastonline` DESC;",__FILE__,__LINE__);
$count_last_online_users = p4c_num_rows($rs_last_online_members);

$rs_last_online_actors = p4c_query("SELECT * FROM `actors` WHERE `lastonline` >= '".strtotime("-10 minutes")."' ORDER BY `lastonline` DESC;",__FILE__,__LINE__);
$count_last_online_actors = p4c_num_rows($rs_last_online_actors);

$site .= ' 
<table style="width:1200px;">
    <tr>
        <td style="width:600px; padding-right:10px;  vertical-align:top;">
            <div class="ui-widget-header" style="padding:10px; border-bottom:none;">'.$count_last_online_users.' User waren in den letzten 10 Minuten online</div>
            ';
            if ($count_last_online_users > 0) {
                $site .= '
                <table id="table_users_online" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:100px;">Username</th>
                            <th style="width:150px;">Zeitpunkt</th>
                            <th style="width:120px;">Coins</th>
                            <th style="width:auto;">Domain</th>
                        </tr>
                    </thead>
                    <tbody>';
                        while($member_obj = p4c_fetch_object($rs_last_online_members)) {
                            $site .='
                            <tr>
                                <td><a href="'.ACP_URL.'/User/'.$member_obj->id.'" target="_blank">'.$member_obj->username.'</a></td>
                                <td>'.$member_obj->lastonline.'</td>
                                <td>'.$member_obj->amount_coins.'</td>
                                <td><a href="'.ACP_URL.'/Site/'.$member_obj->domain.'" target="_blank">'.$member_obj->domain.'</a></td>
                            </tr>
                            ';
                        }
                    $site .= '
                    </tbody>
                </table>';
            }
            $site .= '                
        </td>
        <td style="width:auto; vertical-align:top;">
            <div class="ui-widget-header" style="padding:10px; border-bottom:none;">'.$count_last_online_actors.' Darsteller waren in den letzten 10 Minuten online</div>
            ';
            if ($count_last_online_actors > 0) {
                $site .= '
                <table id="table_actors_online" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:180px;">Username</th>
                            <th style="width:150px;">Zeitpunkt</th>
                            <th style="width:auto;">MID</th>
                        </tr>
                    </thead>
                    <tbody>';
                        while($actor_obj = p4c_fetch_object($rs_last_online_actors)) {
                            $site .='
                            <tr>
                                <td><a href="'.ACP_URL.'/Actor/'.$actor_obj->id.'" target="_blank">'.$actor_obj->username.'</a></td>
                                <td>'.date("Y-m-d H:i:s",$actor_obj->lastonline).'</td>
                                <td><a href="'.ACP_URL.'/Haendler/'.$actor_obj->merchant_id.'" target="_blank">'.$actor_obj->merchant_id.'</a></td>
                            </tr>
                            ';
                        }
                    $site .= '
                    </tbody>
                </table>';
            }
            $site .= '    
        </td>
        
    </tr>
</table>


<table style="width:1200px; margin-top:20px;">
    <tr>
        <td style="width:60%; padding-right:10px;">
            <div class="ui-widget-header" style="padding:10px; border-bottom:none;">letzten 200 Aktionen</div>
            <table id="table_employee_history" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:100px;">User</th>
                        <th style="width:150px;">Zeitpunkt</th>
                        <th style="width:120px;">IP</th>
                        <th style="width:auto;">Aktion</th>
                    </tr>
                </thead>
                <tbody>';
                    $rs_emplyee_hostory = p4c_query("SELECT `employee_history`.*, `employee`.`username` FROM `employee_history`, `employee` WHERE `employee_history`.`employee_id`=`employee`.`id` ORDER BY `employee_history`.`id` DESC LIMIT 200;",__FILE__,__LINE__);
                    while($history_ary = p4c_fetch_object($rs_emplyee_hostory)) {
                        $site .='
                        <tr>
                            <td>'.$history_ary->username.'</td>
                            <td>'.$history_ary->datetime.'</td>
                            <td>'.$history_ary->ip.'</td>
                            <td>'.$history_ary->description.'</td>
                        </tr>
                        ';
                    }
                $site .= '
                </tbody>
            </table>
        </td>
        
        <td style="width:60%; padding-letf:10px; vertical-align:top;">
            <div class="ui-widget-header" style="padding:5px 10px; border-bottom:none;">Folgende Profile haben eine 0900-Rufnummer beantragt</div>
            <div class="ui-widget-content" style="padding:10px;">';
                $rs_check_erocall_exists = p4c_query("SELECT * FROM `actors` WHERE `erocall_number_de_dest_landline`!='0' AND `erocall_number_de`='0';",__FILE__,__LINE__);
                if (p4c_num_rows($rs_check_erocall_exists) == 0) {
                    $site .= 'Aktuell keine offenen Anfragen.';
                } else {
                    $site .= '<ul style="list-style-type:disc; margin-left:15px;">';
                    while($actor_obj = p4c_fetch_object($rs_check_erocall_exists)) {
                        $site .= '<li><a href="'.ACP_URL.'/Actor/'.$actor_obj->id.'">'.$actor_obj->username.'</a></li>';
                    }
                    $site .= '</ul>';
                }
            $site .= '             
            </div>
        </td>
    </tr>
</table>
       

<table style="width:1590px; margin-top:20px;">
    <tr>
        <td style="width:50%; padding-right:10px;">
            <div class="ui-widget-header" style="padding:10px; border-bottom:none;">gemeinsame Notizen</div>
            <div class="ui-widget-content" style="padding:10px;">
                <form action="" method="post">';
                    $readonly_admin_notes = '';
                    if ($settings->get_var("admin_notes_edit") == 0) {
                        $button = '<input type="submit" name="admin_notes_edit" value=" Bearbeiten " />';
                        $readonly_admin_notes = 'readonly';
                    } else if ($settings->get_var("admin_notes_edit") == $_SESSION['employee_id']) {
                        $button = '<input type="submit" name="admin_notes_save" value=" Speichern " /> &nbsp; <input type="submit" name="admin_notes_abbort" value=" Abbrechen " /> ';
                    } else {
                        $button = '<input type="submit" disabled="disabled" value=" Die Notzien werden aktuell bearbeitet. " />';
                    }
                    $site .= '
                    <textarea '.$readonly_admin_notes.' style="width:100%; height:300px; padding:5px; box-sizing:border-box;" name="admin_notes">'.utf8_decode($settings->get_var("admin_notes")).'</textarea>
                    <center>'.$button.'</center>
                </form>
            </div>
        </td>
        
        <td style="width:50%; padding-letf:10px;">
            <div class="ui-widget-header" style="padding:10px; border-bottom:none;">meine Notizen</div>
            <div class="ui-widget-content" style="padding:10px;">
                <form action="" method="post">              
                    <textarea style="width:100%; height:300px; padding:5px; box-sizing:border-box;" name="employee_notes">'.utf8_decode($employee->notes).'</textarea>
                    <center><input type="submit" value=" Speichern " /></center>
                </form>
            </div>        
        </td>
    </tr>
</table>
';



?>