<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

// -----------------------------------------------------------------------------
// 0. HILFSFUNKTIONEN
// -----------------------------------------------------------------------------
if (!function_exists('cleanup_get_folder_size')) {
    function cleanup_get_folder_size($folderPath) {
        $size = 0;
        if (!is_dir($folderPath)) {
            return 0;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
}

// -----------------------------------------------------------------------------
// 1. REITER 1: LÖSCH-VORSCHAU (Simulationslogik)
// -----------------------------------------------------------------------------
$rs_deleted_movies = p4c_query("
    SELECT * FROM `movies` 
    WHERE `status` = 'deleted'
       OR (
           (`released` = '2' OR `status` = 'blocked')
           AND `status` != 'deleted'
           AND COALESCE(
               NULLIF(`movie_checked`, '0000-00-00 00:00:00'),
               NULLIF(`online_at`, '0000-00-00 00:00:00'),
               NULLIF(`create_datetime`, '0000-00-00 00:00:00'),
               NULLIF(`last_updated_datetime`, '0000-00-00 00:00:00')
           ) < '".date("Y-m-d H:i:s", strtotime("-180 days"))."'
       )
    ORDER BY `deleted_datetime` ASC, `id` ASC;
", __FILE__, __LINE__);

$upcoming_count = 0;
$upcoming_total_size = 0;
$preview_rows_html = '';
while ($row = p4c_fetch_object($rs_deleted_movies)) {
    // Check purchases
    $rs_access = p4c_query("SELECT * FROM `movies_access` WHERE `movie_id` = '".p4c_escape_string($row->file_id)."' ORDER BY `buy_timestamp` DESC;", __FILE__, __LINE__);
    $purchases_count = p4c_num_rows($rs_access);
    
    $last_buy_date = '-';
    $last_buy_ts = 0;
    $last_view_date = '-';
    $last_view_ts = 0;
    $rule = '';
    $planned_date = '';
    $planned_ts = 0;
    
    if ($row->status !== 'deleted') {
        $rule = '<span style="color: #c41cc4; font-weight: bold;">Regel 4: Alt & Abgelehnt</span>';
        $planned_ts = 1;
        $planned_date = '<span style="color: #ff0000; font-weight: bold;">Sofort (n&auml;chster Cronjob-Lauf)</span>';
    } elseif ($purchases_count == 0) {
        $planned_ts = 1;
        // Regel 1: Nie gekauft -> Sofortige Löschung
        $rule = '<span style="color: #d05c00; font-weight: bold;">Regel 1: Nie gekauft</span>';
        $planned_date = '<span style="color: #ff0000; font-weight: bold;">Sofort (Nächster Cronjob-Lauf)</span>';
    } else {
        // Get last purchase
        $buy_obj = p4c_fetch_object($rs_access);
        $last_buy_date = date("d.m.Y H:i:s", strtotime($buy_obj->buy_timestamp));
        
        // Find last view
        $rs_view = p4c_query("SELECT `access_token_datetime` FROM `movies_access` WHERE `movie_id` = '".p4c_escape_string($row->file_id)."' AND `access_token_datetime` != '0000-00-00 00:00:00' ORDER BY `access_token_datetime` DESC LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_view) > 0) {
            $view_obj = p4c_fetch_object($rs_view);
            $last_view_date = date("d.m.Y H:i:s", strtotime($view_obj->access_token_datetime));
            $last_view_ts = strtotime($view_obj->access_token_datetime);
        } else {
            $last_view_ts = 0;
        }
        
        $last_buy_ts = strtotime($buy_obj->buy_timestamp);
        $two_years_ago = strtotime("-2 years");
        
        if ($last_buy_ts < $two_years_ago && $last_view_ts < $two_years_ago) {
            // Regel 2: Inaktiv (Kauf & View > 2 Jahre) -> 30 Tage
            $rule = '<span style="color: #1c94c4;">Regel 2: Inaktiv (> 2 Jahre)</span>';
            $planned_ts = strtotime($row->deleted_datetime . " + 30 days");
            if ($planned_ts < time()) {
                $planned_date = '<span style="color: #ff0000; font-weight: bold;">Sofort (nächster Cronjob-Lauf)</span>';
            } else {
                $planned_date = date("d.m.Y H:i:s", $planned_ts);
            }
        } else {
            // Regel 3: Aktiv -> 365 Tage
            $rule = '<span>Regel 3: Aktiv (< 2 Jahre)</span>';
            $planned_ts = strtotime($row->deleted_datetime . " + 365 days");
            if ($planned_ts < time()) {
                $planned_date = '<span style="color: #ff0000; font-weight: bold;">Sofort (nächster Cronjob-Lauf)</span>';
            } else {
                $planned_date = date("d.m.Y H:i:s", $planned_ts);
            }
        }
    }
    
    // Resolve movie edit link
    $rs_online_id = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id` = '".p4c_escape_string($row->file_id)."' LIMIT 1;", __FILE__, __LINE__);
    if (p4c_num_rows($rs_online_id) > 0) {
        $online_id = p4c_result($rs_online_id, 0);
        $edit_link = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$online_id.'"><b>'.htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8').'</b></a>';
    } else {
        $edit_link = '<a href="'.ACP_URL.'/Film-pruefen/'.$row->id.'"><b>'.htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8').'</b></a>';
    }
    
    // Resolve Actor/Profile name
    $actor_name = '-';
    if ($row->actor_id > 0) {
        $rs_actor = p4c_query("SELECT `username` FROM `actors` WHERE `id` = '".abs($row->actor_id)."' LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_actor) > 0) {
            $actor_name = '<a href="'.ACP_URL.'/Actor/'.$row->actor_id.'" target="_blank">'.htmlspecialchars(p4c_result($rs_actor, 0), ENT_QUOTES, 'UTF-8').'</a>';
        } else {
            $actor_name = 'ID: '.$row->actor_id;
        }
    }

    $online_ts = ($row->online_at != '0000-00-00 00:00:00' && $row->online_at != '') ? strtotime($row->online_at) : 0;
    $online_date_cell = '<span style="display:none;">'.$online_ts.'</span>' . (($online_ts > 0) ? date("d.m.Y H:i:s", $online_ts) : '-');
    
    $deleted_ts = ($row->deleted_datetime != '0000-00-00 00:00:00') ? strtotime($row->deleted_datetime) : 0;
    $deleted_date_cell = '<span style="display:none;">'.$deleted_ts.'</span>' . (($deleted_ts > 0) ? date("d.m.Y H:i:s", $deleted_ts) : '-');
    
    $last_buy_cell = '<span style="display:none;">'.$last_buy_ts.'</span>' . $last_buy_date;
    $last_view_cell = '<span style="display:none;">'.$last_view_ts.'</span>' . $last_view_date;
    $planned_cell = '<span style="display:none;">'.$planned_ts.'</span>' . $planned_date;
    
    if ($purchases_count == 0 || $planned_ts < time()) {
        $upcoming_count++;
        $folder = MOVIES_PATH.'/'.$row->storage_location.'/'.$row->merchant_id.'/'.$row->id;
        $upcoming_total_size += cleanup_get_folder_size($folder);
    }
    
    $preview_rows_html .= '
    <tr>
        <td>'.$row->id.'</td>
        <td>'.$edit_link.'</td>
        <td>'.$actor_name.'</td>
        <td>'.$online_date_cell.'</td>
        <td>'.$deleted_date_cell.'</td>
        <td>'.$last_buy_cell.'</td>
        <td>'.$last_view_cell.'</td>
        <td>'.$planned_cell.'</td>
        <td>'.$rule.'</td>
    </tr>';
}

// Format upcoming total size
if ($upcoming_total_size >= 1099511627776) {
    $upcoming_size_text = number_format($upcoming_total_size / 1099511627776, 2) . ' TB';
} elseif ($upcoming_total_size >= 1073741824) {
    $upcoming_size_text = number_format($upcoming_total_size / 1073741824, 2) . ' GB';
} elseif ($upcoming_total_size >= 1048576) {
    $upcoming_size_text = number_format($upcoming_total_size / 1048576, 2) . ' MB';
} else {
    $upcoming_size_text = number_format($upcoming_total_size / 1024, 2) . ' KB';
}


// -----------------------------------------------------------------------------
// 2. REITER 2: LÖSCH-HISTORIE (Aus movies_deleted)
// -----------------------------------------------------------------------------
$rs_deleted_log = p4c_query("SELECT * FROM `movies_deleted` ORDER BY `deleted_datetime` DESC;", __FILE__, __LINE__);
$count_deleted_log = p4c_num_rows($rs_deleted_log);

$total_saved_bytes = 0;
$history_rows_html = '';

while ($log_row = p4c_fetch_object($rs_deleted_log)) {
    $total_saved_bytes += $log_row->folder_size_bytes;
    
    // Format folder size
    $size_bytes = $log_row->folder_size_bytes;
    if ($size_bytes >= 1073741824) {
        $size_text = number_format($size_bytes / 1073741824, 2) . ' GB';
    } elseif ($size_bytes >= 1048576) {
        $size_text = number_format($size_bytes / 1048576, 2) . ' MB';
    } elseif ($size_bytes >= 1024) {
        $size_text = number_format($size_bytes / 1024, 2) . ' KB';
    } else {
        $size_text = $size_bytes . ' Bytes';
    }
    
    $log_deleted_ts = ($log_row->last_updated_datetime != '0000-00-00 00:00:00' && $log_row->last_updated_datetime != '') 
        ? strtotime($log_row->last_updated_datetime) 
        : strtotime($log_row->deleted_datetime);
    $log_deleted_date = date("d.m.Y H:i:s", $log_deleted_ts);
    $log_deleted_cell = '<span style="display:none;">'.$log_deleted_ts.'</span>' . $log_deleted_date;
        
    $history_rows_html .= '
    <tr>
        <td>'.$log_row->movie_id.'</td>
        <td>'.htmlspecialchars($log_row->title, ENT_QUOTES, 'UTF-8').'</td>
        <td>'.$log_deleted_cell.'</td>
        <td>'.$size_text.'</td>
        <td>'.$log_row->purchases.'x</td>
        <td>'.$log_row->views.'x</td>
    </tr>';
}

// Format total saved bytes
if ($total_saved_bytes >= 1099511627776) {
    $total_saved_text = number_format($total_saved_bytes / 1099511627776, 2) . ' TB';
} elseif ($total_saved_bytes >= 1073741824) {
    $total_saved_text = number_format($total_saved_bytes / 1073741824, 2) . ' GB';
} else {
    $total_saved_text = number_format($total_saved_bytes / 1048576, 2) . ' MB';
}


// Build the page output HTML
$site .= '
<style type="text/css">
.filter-pills-container {
    margin-bottom: 20px;
    padding: 10px;
    background: #fdfdfd;
    border: 1px solid #d1d1d1;
    border-radius: 4px;
    font-size: 13px;
}
.filter-pill {
    display: inline-block;
    padding: 5px 12px;
    margin-left: 8px;
    border-radius: 15px;
    border: 1px solid #ccc;
    background-color: #f6f6f6;
    color: #333;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.filter-pill:hover {
    opacity: 0.8;
}
.filter-pill.active {
    font-weight: bold;
    box-shadow: 0 0 5px rgba(0,0,0,0.15);
    border-width: 2px;
}
</style>

<div id="content_cleanup_tabs" style="margin-top: 10px;">
    <ul>
        <li><a href="#tab-cleanup-preview">Lösch-Vorschau</a></li>
        <li><a href="#tab-cleanup-history">Lösch-Historie</a></li>
    </ul>

    <!-- Tab 1: Lösch-Vorschau -->
    <div id="tab-cleanup-preview">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:20px;">Lösch-Vorschau (Simulation der neuen Löschlogik)</div>
        
        <div class="ui-widget-content" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="width: 30%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2;">Löschung beim nächsten Cronjob-Lauf:</td>
                    <td style="width: 20%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2; font-weight: bold; color: #ff0000;">'.$upcoming_count.' Filme</td>
                    <td style="width: 30%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2;">Freizugebender Speicherplatz:</td>
                    <td style="width: 20%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2; font-weight: bold; color: #d05c00;">'.$upcoming_size_text.'</td>
                </tr>
            </table>
        </div>
        
        <div class="ui-widget-content" style="padding: 12px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fffdf6; font-size: 13px; line-height: 1.5;">
            <strong>Information zur Bereinigungs-Simulation:</strong><br />
            Diese Ansicht zeigt Ihnen alle Filme, die sich im Status "Gelöscht" (Soft-Delete) befinden, und berechnet das geplante physische Löschdatum basierend auf der neuen Löschlogik:<br />
            1. <strong>Regel 1 (Nie gekauft):</strong> Sofortige physische Löschung.<br />
            2. <strong>Regel 2 (Inaktiv - Kauf & View > 2 Jahre her):</strong> Physische Löschung 30 Tage nach Markierung als gelöscht.<br />
            3. <strong>Regel 3 (Aktiv - Kauf/View < 2 Jahre her):</strong> Standard-Löschung nach 365 Tagen ab Löschdatum.<br />
            4. <strong>Regel 4 (Alt & Abgelehnt - Inaktiv > 180 Tage):</strong> Sofortige physische Löschung von abgelehnten/gesperrten Filmen, die seit 6 Monaten nicht mehr bearbeitet wurden.
        </div>

        <div class="filter-pills-container" style="margin-bottom: 15px;">
            <strong>Regel filtern:</strong>
            <span class="filter-pill active" data-rule="">Alle anzeigen</span>
            <span class="filter-pill" data-rule="Regel 1" style="background-color: #ffd2b2; border-color: #d05c00; color: #d05c00;">Regel 1: Nie gekauft</span>
            <span class="filter-pill" data-rule="Regel 2" style="background-color: #d2eaf4; border-color: #1c94c4; color: #1c94c4;">Regel 2: Inaktiv (> 2 Jahre)</span>
            <span class="filter-pill" data-rule="Regel 3" style="background-color: #e5e5e5; border-color: #666; color: #333;">Regel 3: Aktiv (< 2 Jahre)</span>
            <span class="filter-pill" data-rule="Regel 4" style="background-color: #ffd6ff; border-color: #c41cc4; color: #c41cc4;">Regel 4: Alt & Abgelehnt</span>
        </div>

        <table id="table_cleanup_preview" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 70px;">Film-ID</th>
                    <th>Filmtitel</th>
                    <th>Profilname</th>
                    <th>Online seit</th>
                    <th>Soft-Delete seit</th>
                    <th>Letzter Kauf</th>
                    <th>Letzter Zugriff</th>
                    <th>Geplantes Löschdatum</th>
                    <th>Bereinigungs-Regel</th>
                </tr>
            </thead>
            <tbody>
                '.$preview_rows_html.'
            </tbody>
        </table>
    </div>

    <!-- Tab 2: Lösch-Historie -->
    <div id="tab-cleanup-history">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:20px;">Lösch-Historie & Statistiken</div>
        
        <div class="ui-widget-content" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="width: 25%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2;">Gelöschte Filme Gesamt:</td>
                    <td style="width: 25%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2; font-weight: bold; color: #d05c00;">'.$count_deleted_log.' Filme</td>
                    <td style="width: 25%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2;">Eingesparter Speicherplatz:</td>
                    <td style="width: 25%; padding: 8px 5px; border-bottom: 1px solid #e2e2e2; font-weight: bold; color: #008000;">'.$total_saved_text.'</td>
                </tr>
            </table>
        </div>

        <table id="table_cleanup_history" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 70px;">Film-ID</th>
                    <th>Filmtitel</th>
                    <th>Physisch gelöscht am</th>
                    <th>Dateigröße</th>
                    <th>Käufe (Historie)</th>
                    <th>Aufrufe (Historie)</th>
                </tr>
            </thead>
            <tbody>
                '.$history_rows_html.'
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        jQuery("#content_cleanup_tabs").tabs();
        

        
        jQuery(".filter-pill").click(function() {
            jQuery(".filter-pill").removeClass("active");
            jQuery(this).addClass("active");
            
            var rule = jQuery(this).data("rule");
            previewTable.fnFilter(rule, 8);
        });

        var previewTable = jQuery("#table_cleanup_preview").dataTable({
            "bJQueryUI": true,
            "iDisplayLength": 25,
            "aaSorting": [[ 7, "asc" ]],
            "oLanguage": {
                "sSearch": "Suchen:",
                "sLengthMenu": "_MENU_ Einträge anzeigen",
                "sInfo": "Zeige _START_ bis _END_ von _TOTAL_ Einträgen",
                "sInfoEmpty": "Keine Einträge vorhanden",
                "sInfoFiltered": "(gefiltert aus _MAX_ Einträgen)",
                "sZeroRecords": "Keine passenden Einträge gefunden",
                "oPaginate": {
                    "sFirst": "Erste",
                    "sLast": "Letzte",
                    "sNext": "Nächste",
                    "sPrevious": "Zurück"
                }
            }
        });

        jQuery("#table_cleanup_history").dataTable({
            "bJQueryUI": true,
            "iDisplayLength": 25,
            "aaSorting": [[ 2, "desc" ]],
            "oLanguage": {
                "sSearch": "Suchen:",
                "sLengthMenu": "_MENU_ Einträge anzeigen",
                "sInfo": "Zeige _START_ bis _END_ von _TOTAL_ Einträgen",
                "sInfoEmpty": "Keine Einträge vorhanden",
                "sInfoFiltered": "(gefiltert aus _MAX_ Einträgen)",
                "sZeroRecords": "Keine passenden Einträge gefunden",
                "oPaginate": {
                    "sFirst": "Erste",
                    "sLast": "Letzte",
                    "sNext": "Nächste",
                    "sPrevious": "Zurück"
                }
            }
        });
    });
// ]]>
</script>
';
