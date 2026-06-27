<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");


if (is_logged_in('mcp') === false) {
    exit;
}

$date_DE = array(
    'Monday'    => 'Montag',
    'Tuesday'   => 'Dienstag',
    'Wednesday' => 'Mittwoch',
    'Thursday'  => 'Donnerstag',
    'Friday'    => 'Freitag',
    'Saturday'  => 'Samstag',
    'Sunday'    => 'Sonntag',
    'Mon'       => 'Mo',
    'Tue'       => 'Di',
    'Wed'       => 'Mi',
    'Thu'       => 'Do',
    'Fri'       => 'Fr',
    'Sat'       => 'Sa',
    'Sun'       => 'So',
    'January'   => 'Januar',
    'February'  => 'Februar',
    'March'     => 'M&auml;rz',
    'May'       => 'Mai',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'October'   => 'Oktober',
    'December'  => 'Dezember',
);

$site = '';

#$_POST['grafik'] = true;
#$_POST['monat'] = '2018-07';
#$_POST['chart'] = 'line';

#$_POST['grafik'] = true;
#$_POST['verhaeltnis'] = 'mobil_zu_festnetz';
#$_POST['monat'] = '2018-07';

if (isset($_POST['grafik'])) {
       
    if (isset($_POST['monat'])) {
        
        $monat = date("Y-m",strtotime($_POST['monat']));
        
        if (isset($_POST['verhaeltnis']) AND $_POST['verhaeltnis'] == 'mobil_zu_festnetz') {
            
            $date = date("Y-m",strtotime($_POST['monat']));

            $rs_anzahl_mobil = p4c_query("SELECT COUNT(*) AS `anzahl` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$date."-%' AND `caller_netz`='TC80' AND `duration_out`>'0' AND `event`='hangup';",__FILE__,__LINE__);
            $anzahl_mobil = p4c_result($rs_anzahl_mobil,0);
            $anzahl_mobil = '{"c":[{"v":"Mobil"}, {"v":'.$anzahl_mobil.', "f":"'.$anzahl_mobil.' '.utf8_encode('Gespräche').'"}]}';

            $rs_anzahl_festnetz = p4c_query("SELECT COUNT(*) AS `anzahl` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$date."-%' AND `caller_netz`='festnetz' AND `duration_out`>'0' AND `event`='hangup';",__FILE__,__LINE__);
            $anzahl_festnetz = p4c_result($rs_anzahl_festnetz,0);    
            $anzahl_festnetz = '{"c":[{"v":"Festnetz"}, {"v":'.$anzahl_festnetz.', "f":"'.$anzahl_festnetz.' '.utf8_encode('Gespräche').'"}]}';

            echo '{
              "cols": [
                    {"id":"","label":"Topping","pattern":"","type":"string"},
                    {"id":"","label":"Slices","pattern":"","type":"number"}
                  ],
              "rows": [
                    '.$anzahl_mobil.',
                    '.$anzahl_festnetz.'
                  ]
            }';

            
        } else {
        
            if (isset($_POST['chart']) AND $_POST['chart'] == 'line') {
        
                $total_ary = array();
                for ($i=1; $i<=date("t"); $i++) {
                    $tag = $i;
                    if (strlen($tag) == 1) {$tag = "0$i";}
                    
                	$rs_anrufe = p4c_query("SELECT SUM(`merchant_payout`) AS `payout`, COUNT(*) AS `anzahl_anrufe`, SUM(`duration_out`) AS `sekunden` FROM `erocall` WHERE `date_time` LIKE '".p4c_escape_string($monat."-".$tag)."%' AND `event`='hangup' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
                    if (p4c_num_rows($rs_anrufe) > 0) {
                        $anrufe_ary = p4c_fetch_object($rs_anrufe);
                    	$total = $anrufe_ary->payout;
                        $anzahl_anrufe = $anrufe_ary->anzahl_anrufe;
                        $minuten = (int)($anrufe_ary->sekunden/60).':'.($anrufe_ary->sekunden%60);
                    } else {
                        $total = 0.00;
                        $anzahl_anrufe = 0;
                        $minuten = 0;
                    } 
    
                	$rs_caller_festnetznetz = p4c_query("SELECT COUNT(*) FROM `erocall` WHERE `caller_netz`='festnetz' AND `date_time` LIKE '".p4c_escape_string($monat."-".$tag)."%' AND `event`='hangup' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
                    if (p4c_num_rows($rs_caller_festnetznetz) > 0) {
                        $anzahl_festnetz = p4c_result($rs_caller_festnetznetz,0);
                    } else {
                        $anzahl_festnetz = 0;
                    }
    
                	$rs_caller_mobil = p4c_query("SELECT COUNT(*) FROM `erocall` WHERE `caller_netz`='TC80' AND `date_time` LIKE '".p4c_escape_string($monat."-".$tag)."%' AND `event`='hangup' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
                    if (p4c_num_rows($rs_caller_mobil) > 0) {
                        $anzahl_mobil = p4c_result($rs_caller_mobil,0);
                    } else {
                        $anzahl_mobil = 0;
                    }
            
                    $wochentag = strtr(date("l, $i. F Y", strtotime(date("Y-m-$tag"))), $date_DE);
            
                    $total_ary[] = '["'.$i.'.", '.(float)$total.', customTooltip("'.$wochentag.'", "'.number_format(($total), 2, ',', '.').'", "'.$anzahl_anrufe.'", "'.$minuten.'", "'.$anzahl_mobil.'", "'.$anzahl_festnetz.'")]'; 
                }
                
                echo "dataTable.addRows([".implode(",", $total_ary)."]);";
                
                exit;   
            }
        
            $von = date("Y-m-01 00:00:00",strtotime($_POST['monat']));
            $bis = date("Y-m-t 23:59:59",strtotime($_POST['monat']));
    
    
            $rs_mobil = p4c_query("SELECT SUM(`merchant_payout`) FROM `erocall` WHERE `date_time`>='".p4c_escape_string($von)."' AND `date_time`<='".p4c_escape_string($bis)."' AND `caller_netz`='TC80' AND `event`='hangup' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
            $sum_mobil = p4c_result($rs_mobil,0);
            $total_mobil = '{"c":[{"v":"Mobil"}, {"v":'.$sum_mobil.', "f":"'.number_format($sum_mobil, 2, ',', '.').' EUR"}]}';
        
            $rs_festnetz = p4c_query("SELECT SUM(`merchant_payout`) FROM `erocall` WHERE `date_time`>='".p4c_escape_string($von)."' AND `date_time`<='".p4c_escape_string($bis)."' AND `caller_netz`='Festnetz' AND `event`='hangup' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);
            $sum_festnetz = p4c_result($rs_festnetz,0);
            $total_festnetz = '{"c":[{"v":"Festnetz"}, {"v":'.$sum_festnetz.', "f":"'.number_format($sum_festnetz, 2, ',', '.').' EUR"}]}';
    
            $sum_total = round(($sum_mobil + $sum_festnetz),2);
            
            echo '{
              "cols": [
                    {"id":"","label":"Topping","pattern":"","type":"string"},
                    {"id":"","label":"Slices","pattern":"","type":"number"}
                  ],
              "rows": [
                    '.$total_mobil.',
                    '.$total_festnetz.'
                  ]
            }';
        }
    }

} else if (isset($_POST['date_time'])) {
    $get_tag = date("Y-m-d", strtotime($_POST['date_time']));
    if ($get_tag == '1970-01-01') {
        exit;        
    }
    
    $site = '
    <div style="margin:10px 10px 10px 30px; width:700px;">
        <table id="tabelle_monat" cellspacing="0" cellpadding="0" style="padding:0; width:100%">
            <thead>
                <tr>
                    <td style="border-bottom:1px solid #000; padding:5px 10px; width:140px; text-align:center; font-weight:bold;">Tag & Uhrzeit</td>
                    <td style="border-bottom:1px solid #000; padding:5px 20px; width:170px; text-align:right; font-weight:bold;">Minuten</td>
                    <td style="border-bottom:1px solid #000; padding:5px 10px; width:120px; text-align:center; font-weight:bold;">Anrufer</td>
                    <td style="border-bottom:1px solid #000; padding:5px 10px; width:120px; text-align:center; font-weight:bold;">Herkunft</td>
                    <td style="border-bottom:1px solid #000; padding:5px 10px; width:150px; text-align:right; font-weight:bold;">Ihre Auszahlung</td>
                </tr>
            </thead>
            <tbody>';
    
                $auszahlung = 0.00;
                $sekunden_gesamt = 0;
                $date_time = $get_tag.' %';
                $rs_anrufe = p4c_query("SELECT `duration_out` AS `sekunden`, `merchant_payout`, `date_time`, `caller`, `rufnummer`, `destination_number` AS `ziel` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$date_time."' AND `event`='hangup' AND `duration_out`>'0' ORDER BY `date_time` DESC;",__FILE__,__LINE__);
                while($anrufe_ary = p4c_fetch_object($rs_anrufe)) {
                    $auszahlung = $auszahlung+$anrufe_ary->merchant_payout;
                    $sekunden_gesamt = $sekunden_gesamt+$anrufe_ary->sekunden;
                    
                    /*
                    if ($anrufe_ary->member_id > 0) {
                        $anrufer = new Member($mysql,$anrufe_ary->member_id);
                        $anrufer = '<a href="index.php?site=member&id='.$anrufe_ary->member_id.'" target="userprofil">'.$anrufer->username().'</a>';
                        
                    } else {
                        $anrufer = substr($anrufe_ary->caller,0,-3).'xxx';
                    }
                     */
                    $anrufer = substr($anrufe_ary->caller,0,-3).'xxx';
                    
                    $vorwahl = substr($anrufe_ary->rufnummer,0,4);
                    if ($vorwahl == '0900') {
                        $country = '<img src="'.MCP_URL.'/images/icons/de.png" alt="" data-tooltip="Deutschland = '.$anrufe_ary->rufnummer.'" style="height:16px; width:auto;" />';
                    } else if ($vorwahl == '0930'){
                        $country = '<img src="'.MCP_URL.'/images/icons/at.png" alt="" data-tooltip="&Ouml;sterreich = '.$anrufe_ary->rufnummer.'" style="height:16px; width:auto;" />';
                    } else {
                        $country = '???';
                    }
                   
                    $sekunden = ($anrufe_ary->sekunden%60);
                    if ($sekunden < 10) {$sekunden = "0".$sekunden;}
                   
                    $site .='
                    <tr>
                        <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.date("d.m.Y H:i", strtotime($anrufe_ary->date_time)).'</td>
                        <td style="border-bottom:1px solid #000; padding:5px 20px; text-align:right;">'.(int)($anrufe_ary->sekunden/60).':'.$sekunden.'</td>
                        <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$anrufer.'</td>
                        <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$country.'</td>
                        <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($anrufe_ary->merchant_payout,2, ',', '').' EUR</td>
                    </tr>
                    ';
                }
                $site .= '
                <tr>
                    <td></td>
                    <td style="border-bottom:1px solid #000; padding:5px 20px; text-align:right; font-weight:bold;">'.(int)($sekunden_gesamt/60).':'.($sekunden_gesamt%60).'</td>
                    <td colspan="2"></td>
                    <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right; font-weight:bold;">'.number_format($auszahlung,2, ',', '').' EUR</td>
                </tr>
            <tbody>
        </table>
    </div>';
}
echo $site;

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

