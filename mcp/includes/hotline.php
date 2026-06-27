<?php

 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$erocall_number_de_dest_landline = '';
$my_data_checked = '';

if (isset($_SESSION['erocall_number_de_dest_landline'])) {
    $erocall_number_de_dest_landline = $_SESSION['erocall_number_de_dest_landline'];
}

if (isset($_POST['submit_erocall']) AND isset($_POST['actor_id'])) {

    
    if (isset($_POST['my_data']) AND $_POST['my_data'] == 'on') {
        $my_data_checked = 'checked="checked"';
    } else {
        $error = 'Bitte best&auml;tige noch, dass wir deine Adress- und Bankdaten nur f&uuml;r die Bereitstellung und Abrechnung einer eigenen 09005-Rufnummer
                an adult net applications UG weitergegeben werden d&uuml;rfen';
    }
    
    $erocall_number_de_dest_landline = $_POST['erocall_number_de_dest_landline'];
    $erocall_number_de_dest_landline = str_replace(' ', '', $erocall_number_de_dest_landline);
    
    if (substr($erocall_number_de_dest_landline,0,3) == '+49') {
        $erocall_number_de_dest_landline = substr($erocall_number_de_dest_landline, 3);
    }
    
    if (substr($erocall_number_de_dest_landline,0,4) == '0049') {
        $erocall_number_de_dest_landline = substr($erocall_number_de_dest_landline, 4);
    }
    
    $erocall_number_de_dest_landline = abs($erocall_number_de_dest_landline);

    if ($erocall_number_de_dest_landline == "0") {
        $error = 'Deine Zielrufnummer scheint keine g&uuml;ltige Festnetzrufnummer zu sein.';
        $erocall_number_de_dest_landline = '';
    }
    
    if (substr($erocall_number_de_dest_landline,0,1) == '1' OR strlen($erocall_number_de_dest_landline) < 6) {
        $error = 'Deine Zielrufnummer scheint keine g&uuml;ltige Festnetzrufnummer zu sein.';
    }
    
    if (!isset($error) OR empty($error)) {
        $_SESSION['erocall_number_de_dest_landline'] = $erocall_number_de_dest_landline;
        $_SESSION['erocal_actor_id'] = abs($_POST['actor_id']);
        header('Location: '.MCP_URL.'/Hotline?step=2');
        exit;
    }
    
}


else if (isset($_POST['submit_number']) AND isset($_SESSION['erocall_number_de_dest_landline'])) {
    $erocall_number_de_dest_landline = $_SESSION['erocall_number_de_dest_landline'];
    
    p4c_query("UPDATE `actors` SET
        `erocall_number_de_dest_landline`='".abs($erocall_number_de_dest_landline)."'
    WHERE
        `id` = '".abs($_SESSION['erocal_actor_id'])."' AND
        `merchant_id`='".abs($_SESSION['merchant_id'])."'
    LIMIT 1;",__FILE__,__LINE__);
        
    header('Location: '.MCP_URL.'/Hotline?step=3');
    exit;
}


$site .= '

<div id="site_hotline" style="width:800px;">

    <div style="text-align:right;">
        <img src="https://call.erocms.net/images/erocall_logo.svg" style="width:40%; height:auto;">
    </div>
    ';

    if (isset($error) AND !empty($error)) {
        $site .= '<div class="ui-state-error" style="margin-top:20px; padding:10px;">'.$error.'</div>';
    }
    
    if (isset($_GET['step']) AND $_GET['step'] == 3) {
        $site .= '
        <div class="ui-widget-content" style="padding:10px 10px 20px 10px; margin-top:10px;">
            <div style="color:#339966; text-align:center;"><i class="material-symbols-outlined md-80">done</i></div>
            <div style="font-size:80px; color:#339966; font-weight:bold; margin-bottom:15px; text-align:center;">Fertig!</div>
            <div style="text-align:center">
                Deine 09005-Rufnummer wird beantragt und eingerichtet.<br />
                Die Einrichtung deiner 0900-Rufnummer kann bis zu 24 Stunden dauern.
            </div>    
        </div>';
    } else {
    
        $rs_check_erocall_exists = p4c_query("SELECT * FROM `actors` WHERE
            (`erocall_number_de_dest_landline`!='0' OR `erocall_number_de_dest_mobile`!='0') AND
            `merchant_id`='".abs($_SESSION['merchant_id'])."'
        LIMIT 1;",__FILE__,__LINE__);

        if (p4c_num_rows($rs_check_erocall_exists) > 0) {
            $actor_obj = p4c_fetch_object($rs_check_erocall_exists);

            if ($actor_obj->erocall_number_de == 0) {
                $site .= '
                <div class="ui-widget-header" style="margin-top:20px; padding:5px 10px; border-bottom:none;">Nummer beantragt</div>
                <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
                    Deine Nummer wurde beantragt und wird in K&uuml;rze eingerichtet.
                </div>

                <div class="ui-widget-header" style="padding:10px; border-bottom:none;">Mit einer 0900-Rufnummer von EroCall erh&auml;st du eine beispiellos hohe Verg&uuml;tung.</div>
                <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
                    <i class="material-symbols-outlined">check</i> Du bekommst 1,09 EUR brutto (inkl. USt.) ausgezahlt.<br />
                    <i class="material-symbols-outlined">check</i> Deine Auszahlung erfolgt bereits ab den ersten Cent zum Anfang des Folgemonats.
                </div>

                <div class="ui-widget-header" style="padding:10px; border-bottom:none;">Deine Anrufer werden auf folgende deutsche Festnetz-Zielrufnummer weitergeleitet</div>
                <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
                    <span style="font-size:20px;">0'.$actor_obj->erocall_number_de_dest_landline.'<br />
                    <div style="margin-top:5px;">
                        Deine private Rufnummer ist nur uns bekannt und wird nirgends &ouml;ffentlich angezeigt.
                    </div>
                </div>';
            } else if ($actor_obj->erocall_number_de > 0) {
                $site .= '
                <div class="ui-widget-header" style="margin-top:20px; padding:5px 10px; border-bottom:none;">Deine 09005-Rufnummer</div>
                <div class="ui-widget-content" style="padding:10px; box-sizing: border-box;">
                    <div style="display:table; width:100%;">
                        <div style="display:table-cell; width:102px; vertical-align:middle; text-align:left;">
                            <img src="'.MCP_URL.'/images/icons/de.png" alt="" style="height:55px; width:auto;">
                         </div>
                        <div style="display:table-cell; width:auto; vertical-align:middle; text-align:center;">
                            <div style="font-size:35px; font-weight:bold;">09005 - '.$actor_obj->erocall_number_de.' - '.$actor_obj->erocall_number_de_ddi.'</div>
                            <div>'.$actor_obj->erocall_number_de_rate.'&euro;/Min. aus dem dt. Festnetz / Mobilfunkpreise variieren</div>
                        </div>
                        <div style="display:table-cell; width:102px; vertical-align:middle; text-align:right;">
                            <img src="'.MCP_URL.'/images/icons/de.png" alt="" style="height:55px; width:auto;">
                        </div>
                    </div>
                </div>
                <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px; box-sizing: border-box;">';
                    $landline = '-';
                    if ($actor_obj->erocall_number_de_dest_landline != '0') {
                        $landline = '+49 (0)'.$actor_obj->erocall_number_de_dest_landline;
                    }
                    if ($actor_obj->erocall_number_de_dest_mobile != '0') {
                        $mobile = '+49 (0)'.$actor_obj->erocall_number_de_dest_mobile;
                        if ($landline == '') {
                            $landline = $mobile;
                        } else {
                            $landline = $landline.' & '.$mobile;
                        }
                        
                    }
                    $site .= ' 
                    <div>Die Weiterleitung erfolgt auf folgende Rufnummer: <b>'.$landline.'</b></div>
                    <div>Deine private Rufnummer ist nur uns bekannt und wird nirgends &ouml;ffentlich angezeigt.</div>
                </div>
                
                <div class="ui-widget-header" style="margin-top:10px; padding:5px 10px; border-bottom:none;">Dies ist GESETZLICH vorgeschrieben</div>
                <div class="ui-state-error" style="padding:10px; text-align:left; margin-bottom:10px;">
                    <div>
                        Wichtig!! Ob bei Twitter, Facebook, Instagramm oder auf Printmedien egal wo. An jeder Stelle, an der du deine Rufnummer ver&ouml;ffentlichst,
                        musst du folgende Texte (Preis-Kennzeichnung) hinzugef&uuml;gn. Bei Missachtung wird die Nummer gesperrt und und alle entstehenden Kosten an dich weitergeben!
                    </div>
                    <div style="padding-top:20px; text-align:center;">
                        <b>'.$actor_obj->erocall_number_de_rate.'&euro; pro Minute aus dem deutschen Festnetz / Mobilfunkpreise variieren.</b><br />
                        Oder k&uuml;rzer:<br />
                        <b>'.$actor_obj->erocall_number_de_rate.'&euro;/Min. a. d .dt. Festnetz / Mobilfunkpreise variieren.</b>
                    </div>
                </div>
                
                <div class="ui-widget-header" style="margin-top:20px; padding:5px 10px; border-bottom:none;">ACHTUNG - Bitte achte auf folgende Punkte</div>
                <div class="ui-widget-content" style="padding:10px;">
                    <ul style="list-style-type:disc; margin:0 0 10px 20px; line-height:20px;">
                        <li>Es darf kein Anrufbeantworter und keine Mobilbox aktiviert sein, da sonst 09005er-Anrufe dorthin geleitet werden wenn du den Anruf nicht entgegen nimmst.</li>
                        <li>Deine 09005-Rufnummer bleibt immer aktiv. Sobald du diese iher deaktivierst, wird diese nur auf keiner der teilnehmenden Webseiten mehr angezeigt.</li>
                        <li>Sofern du deine 09005-Rufnummer auf Printmedien oder anderen Webseiten bekannt machst, musst du IMMER den oben angegeben Hinweis zum Preis angeben.</li>
                    </ul>
                    <b>Nichtbeachten der genannten Punkte f&uuml;hrt zu sofortiger Sperrung deiner 09005-Rufnummer!</b>
                </div>';
                

                $get_monat = date("Y-m");
                if (isset($_GET['monat'])) {
                    $get_monat = date("Y-m", strtotime($_GET['monat']));
                    if ($get_monat == '1970-01') {
                        $get_monat = date("Y-m");        
                    }
                }

                $rs_jahre = p4c_query("SELECT DISTINCT YEAR(`date_time`) AS `jahr` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER by `date_time` DESC",__FILE__,__LINE__);
                $select = '';
                if (p4c_num_rows($rs_jahre) > 0) {
                    $select = '<option value="'.$get_monat.'">Bitte w&auml;hlen</option>';
                    while($year = p4c_fetch_object($rs_jahre)) {

                        $jahr = $year->jahr;

                        $select .= '<optgroup label="'.$jahr.'">'."\n";
                        $rs_month = p4c_query("SELECT DISTINCT MONTH(`date_time`) AS `monat` FROM `erocall` WHERE `date_time` LIKE '".$jahr."-%' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER by `date_time` DESC",__FILE__,__LINE__);
                        while($month = p4c_fetch_object($rs_month)) {

                            $monat = $month->monat;

                            $montat_name = str_ireplace('12', 'Dezember', $monat);
                            $montat_name = str_ireplace('11', 'November', $montat_name);
                            $montat_name = str_ireplace('10', 'Oktober', $montat_name);
                            $montat_name = str_ireplace('9', 'September', $montat_name);
                            $montat_name = str_ireplace('8', 'August', $montat_name);
                            $montat_name = str_ireplace('7', 'Juli', $montat_name);
                            $montat_name = str_ireplace('6', 'Juni', $montat_name);
                            $montat_name = str_ireplace('5', 'Mai', $montat_name);
                            $montat_name = str_ireplace('4', 'April', $montat_name);
                            $montat_name = str_ireplace('3', 'M&auml;rz', $montat_name);
                            $montat_name = str_ireplace('2', 'Februar', $montat_name);
                            $montat_name = str_ireplace('1', 'Januar', $montat_name);

                            if (strlen($monat) == 1) {
                                $monat = '0'.$monat;
                            }

                            $value = $jahr.'-'.$monat;

                            if ($get_monat == $value) {
                                $selected = 'selected="selected"';
                            } else {
                                $selected = '';
                            }

                            $select .= '<option '.$selected.' value="'.$value.'">'.$montat_name.'</option>'."\n";
                        }

                        $select .= '</optgroup>'."\n";
                    }
                }
                
                $site .= ' 

                <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

                <script type="text/javascript">
                // <![CDATA[

                    jQuery(document).ready(function() {
                        jQuery.fn.show_date = function(id, date_time){
                            jQuery("#"+id).toggle("slow", function() {
                                jQuery.ajax({
                                    type: "POST",
                                    data: "date_time="+date_time,
                                    dataType: "html",
                                    url: "'.MCP_URL.'/Ajax/get_erocall_stats.php",
                                    success: function(data, textStatus) {
                                        if (textStatus == "success") {
                                            jQuery("#"+id+" td").html(data);
                                        }                                                                   
                                    }
                                })           
                            });
                        }
                    })

                    google.charts.load("current", {"packages":["corechart"]});
                    google.charts.setOnLoadCallback(drawChart);

                    function drawChart() {

                        var data_line_month = jQuery.ajax({
                            type: "POST",
                            url: "'.MCP_URL.'/Ajax/get_erocall_stats.php",
                            data: "grafik=true&monat='.$get_monat.'&chart=line",
                            dataType:"html",
                            async: false
                        }).responseText;

                        var dataTable = new google.visualization.DataTable();
                        dataTable.addColumn("string", "Stunde");
                        dataTable.addColumn("number", "Sales");
                        dataTable.addColumn({ type: "string", role: "tooltip", "p": { "html": true} });

                        eval(data_line_month)

                        var options = {
                            vAxis: {
                                minValue:0,
                                viewWindow: {
                                    min: 0
                                }
                            },
                            tooltip: { isHtml: true },
                            legend: "none",
                            colors: ["#058DC7"],
                            areaOpacity: 0.2,
                        };
                        var chart = new google.visualization.AreaChart(document.getElementById("line_month"));
                        chart.draw(dataTable, options);
                    }

                    function customTooltip(day,euro,anzahl_anrufe,minuten,anzahl_mobil,anzahl_festnetz) {
                        return \'<div style="padding:5px; font-size:11px;"><b>\'+day+\'</b><br />Umsatz: <b>\'+euro+\' &euro;</b><br />Gespr&auml;che: <b>\'+anzahl_anrufe+\'</b><br />Minuten: <b>\'+minuten+\'</b><br />von Mobil: <b>\'+anzahl_mobil+\'</b><br />aus Festnetz: <b>\'+anzahl_festnetz+\'</b></div>\';
                    }
                // ]]>

                </script>';
                
                
                $rs_anrufe_monat = p4c_query("SELECT SUM(`merchant_payout`) FROM `erocall` WHERE `date_time` LIKE '".$get_monat."-%' AND `event`='hangup' AND `merchant_id`='".abs($_SESSION['merchant_id'])."';");
                $site .= '
                <div class="ui-widget-header" style="margin-top:10px; padding:5px; border-bottom:none;">
                    <form action="'.MCP_URL.'/Hotline" method="GET">
                        Ums&auml;tze durch Telefonate im Monat <select name="monat" style="padding:0 5px;" onchange="submit()">'.$select.'</select> ('.number_format(p4c_result($rs_anrufe_monat,0), 2, ',', '.').' EURO)
                    </fotm>
                </div>        
                <div class="ui-widget-content">
                    <div id="line_month" style="width:100%; height:200px;">
                        <div style="text-align:center;">Statistik wird erstellt...</div>
                    </div>
                </div>';
                
                $rs_mobil = p4c_query("SELECT COUNT(*) AS `anzahl`, SUM(`merchant_payout`) AS `auszahlung`, SUM(`duration_out`) AS `sekunden` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$get_monat."-%' AND (`caller_netz`='TC80' OR `caller_netz`='mobil') AND `event`='hangup';",__FILE__,__LINE__);
                $mobil_ary = p4c_fetch_object($rs_mobil);
                $umsatz_mobil_sekunde = 0.00;
                if ($mobil_ary->sekunden>0) {
                    $umsatz_mobil_sekunde = $mobil_ary->auszahlung/$mobil_ary->sekunden;
                }
                $umsatz_mobil_minute = round($umsatz_mobil_sekunde*60,2);

                $rs_verpasst_mobil = p4c_query("SELECT COUNT(*) FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$get_monat."-%' AND `caller_netz`='TC80' AND `duration_out`='0' AND `event`='hangup';",__FILE__,__LINE__);
                $verpasst_mobil = p4c_result($rs_verpasst_mobil,0);

                $anzahl_mobil = $mobil_ary->anzahl-$verpasst_mobil;

                $rs_festnetz = p4c_query("SELECT COUNT(*) AS `anzahl`, SUM(`merchant_payout`) AS `auszahlung`, SUM(`duration_out`) AS `sekunden` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$get_monat."-%' AND `caller_netz`='festnetz' AND `event`='hangup';",__FILE__,__LINE__);
                $festnetz_ary = p4c_fetch_object($rs_festnetz);
                $umsatz_festnetz_sekunde = 0.00;
                if ($festnetz_ary->sekunden > 0) {
                    $umsatz_festnetz_sekunde = $festnetz_ary->auszahlung/$festnetz_ary->sekunden;
                }
                $umsatz_festnetz_minute = round($umsatz_festnetz_sekunde*60,2);

                $rs_verpasst_festnetz = p4c_query("SELECT COUNT(*) FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$get_monat."-%' AND `caller_netz`='festnetz' AND `duration_out`='0' AND `event`='hangup';",__FILE__,__LINE__);
                $verpasst_festnetz = p4c_result($rs_verpasst_festnetz,0);

                $anzahl_festnetz = $festnetz_ary->anzahl-$verpasst_festnetz;

                $site .= '
                <div class="ui-widget-header" style="margin-top:10px; border-bottom:none; padding:5px 10px;">Verh&auml;ltnis zwischen Festnetz- und Mobil-Anrufer im gew&auml;hlten Monat</div>
                <div class="ui-widget-content" style="padding:10px;">

                    <table id="tabelle_monat" cellspacing="0" cellpadding="0" style="padding:0; width:100%">
                        <thead>
                            <tr>
                                <td style="border-bottom:1px solid #000; width:80px;"></td>
                                <td style="border-bottom:1px solid #000; width:60px; padding:5px 10px; text-align:center;">Gespr&auml;che</td>
                                <td style="border-bottom:1px solid #000; width:60px; padding:5px 10px; text-align:center;">verpasst</td>
                                <td style="border-bottom:1px solid #000; width:50px; padding:5px 20px; text-align:right;">Minuten</td>
                                <td style="border-bottom:1px solid #000; width:110px; padding:5px 10px; text-align:right;">Umsatz je. Sek</td>
                                <td style="border-bottom:1px solid #000; width:110px; padding:5px 10px; text-align:right;">Umsatz je. Min.</td>
                                <td style="border-bottom:1px solid #000; width:auto; padding:5px 10px; text-align:right;">Umsatz gesamt</td>
                                <td style="auto"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; font-weight:bold;">Festnetz</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$anzahl_festnetz.'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$verpasst_festnetz.'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 20px; text-align:right;">'.(int)($festnetz_ary->sekunden/60).':'.($festnetz_ary->sekunden%60).'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($umsatz_festnetz_sekunde, 4, ',', '.').' EUR</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($umsatz_festnetz_minute, 2, ',', '.').' EUR</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($festnetz_ary->auszahlung, 2, ',', '.').' EUR</td>
                                <td style="auto"></th>
                            </tr>
                            <tr>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; font-weight:bold;">Mobil</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$anzahl_mobil.'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$verpasst_mobil.'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 20px; text-align:right;">'.(int)($mobil_ary->sekunden/60).':'.($mobil_ary->sekunden%60).'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($umsatz_mobil_sekunde, 4, ',', '.').' EUR</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($umsatz_mobil_minute, 2, ',', '.').' EUR</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($mobil_ary->auszahlung, 2, ',', '.').' EUR</td>
                                <td style="auto"></th>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px;"></td>
                                <td style="padding:5px 10px; font-weight:bold; text-align:center;">'.($anzahl_festnetz+$anzahl_mobil).'</td>
                                <td style="padding:5px 10px; font-weight:bold; text-align:center;">'.($verpasst_festnetz+$verpasst_mobil).'</td>
                                <td style="padding:5px 20px; font-weight:bold; text-align:right;">'.(int)(($festnetz_ary->sekunden+$mobil_ary->sekunden)/60).':'.(($festnetz_ary->sekunden+$mobil_ary->sekunden)%60).'</td>
                                <td style="padding:5px 10px; font-weight:bold; text-align:right;">'.number_format($umsatz_festnetz_sekunde+$umsatz_mobil_sekunde, 4, ',', '.').' EUR</td>
                                <td style="padding:5px 10px; font-weight:bold; text-align:right;">'.number_format($umsatz_festnetz_minute+$umsatz_mobil_minute, 2, ',', '.').' EUR</td>
                                <td style="padding:5px 10px; font-weight:bold; text-align:right;">'.number_format($festnetz_ary->auszahlung+$mobil_ary->auszahlung, 2, ',', '.').' EUR</td>
                                <td style="auto"></th>
                            </tr>
                        <tbody>
                    </table>
                    <div style="font-size:10px; margin-top:5px;">* Dies Zahlen in dieser Statistik sind gerundet und k&ouml;nnen zu den tats&auml;chlichen Ums&auml;tzen leicht variiren.</div>

                    ';
                    /*
                    <script type="text/javascript" async="async">

                        google.charts.load("current", {"packages":["corechart"]});
                        google.charts.setOnLoadCallback(doughnut_verhaeltnis);

                        <!-- Monat Doughnut  ############################################## //-->
                        function doughnut_verhaeltnis() {
                            var data_doughnut_verhaeltnis = jQuery.ajax({
                                type: "POST",
                                url: "'.MCP_URL.'/Ajax/get_erocall_stats.php",
                                data: "grafik=true&verhaeltnis=mobil_zu_festnetz&monat='.$get_monat.'",
                                dataType:"html",
                                async: false
                            }).responseText;

                            var data = new google.visualization.DataTable(data_doughnut_verhaeltnis);

                            var options = {
                                legend: "right",
                                fontSize: 12,
                                colors: ["#058DC7", "#50B432"],
                                tooltip: {
                                    fontsize: 15
                                }
                            };

                            var chart = new google.visualization.PieChart(document.getElementById("doughnut_verhaeltnis"));
                            chart.draw(data, options);
                        }

                    </script>

                    <div id="doughnut_verhaeltnis" style="width:200px; height:200px;"></div>
                     * 
                     */
                    $site .= '

                </div>

                <div class="ui-widget-header" style="margin-top:10px; border-bottom:none; padding:5px 10px;">Ums&auml;tze gew&auml;hlter Monat</div>
                <div class="ui-widget-content" style="padding:10px;">
                    <table id="tabelle_monat" cellspacing="0" cellpadding="0" style="padding:0; width:100%">
                        <thead>
                            <tr>
                                <td style="border-bottom:1px solid #000; width:20px; padding:5px 10px;"></td>
                                <td style="border-bottom:1px solid #000; width:100px; padding:5px 10px; text-align:center; font-weight:bold;">Tag</td>
                                <td style="border-bottom:1px solid #000; width:90px; padding:5px 20px; text-align:right; font-weight:bold;">Minuten</td>
                                <td style="border-bottom:1px solid #000; width:120px; padding:5px 10px; text-align:right; font-weight:bold;">Ihre Auszahlung</td>
                                <td style="auto"></th>
                            </tr>
                        </thead>
                        <tbody>';

                        // Monat
                        $auszahlung = 0.00;
                        $sekunden_gesamt = 0;
                        $date_time = $get_monat.'-%';
                        $rs_anrufe = p4c_query("SELECT SUM(`duration_out`) AS `sekunden`, SUM(`merchant_payout`) AS `auszahlung`, `date_time` FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$date_time."' AND `event`='hangup' GROUP BY DATE(`date_time`) ORDER BY `date_time` DESC;") or die(mysql_error());
                        while($anrufe_ary = p4c_fetch_object($rs_anrufe)) {
                            $auszahlung = $auszahlung+$anrufe_ary->auszahlung;
                            $sekunden_gesamt = $sekunden_gesamt+$anrufe_ary->sekunden;
                            $site .='
                            <tr>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center; font-weight:bold; cursor:pointer;" data-tooltip="Tagesansicht" onclick="jQuery(this).show_date(\'date_'.date("Ymd", strtotime($anrufe_ary->date_time)).'\', \''.date("Y-m-d", strtotime($anrufe_ary->date_time)).'\');">&equiv;</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.date("d.m.Y", strtotime($anrufe_ary->date_time)).'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 20px; text-align:right;">'.(int)($anrufe_ary->sekunden/60).':'.($anrufe_ary->sekunden%60).'</td>
                                <td style="border-bottom:1px solid #000; padding:5px 10px; text-align:right;">'.number_format($anrufe_ary->auszahlung,2, ',', '').' EUR</td>
                                <td></td>
                            </tr>
                            <tr id="date_'.date("Ymd", strtotime($anrufe_ary->date_time)).'" style="display:none;">
                                <td colspan="5">Bitte warten...</td>
                            </tr>
                            ';
                        }
                        $site .= '
                            <tr>
                                <td colspan="2"></td>
                                <td style="border-bottom-style:double; padding:5px 20px; text-align:right; font-weight:bold;">'.(int)($sekunden_gesamt/60).':'.($sekunden_gesamt%60).'</td>
                                <td style="border-bottom-style:double; padding:5px 10px; text-align:right; font-weight:bold;">'.number_format($auszahlung,2, ',', '').' EUR</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="ui-widget-header" style="margin-top:10px; border-bottom:none; padding:5px 10px;">'.($verpasst_festnetz+$verpasst_mobil).' verpasste Anrufe</div>
                <div class="ui-widget-content" style="padding:10px;">
                    <div style="margin-bottom:10px;">Hier sehen Sie alle Anrufe die Sie verpasst haben. Anhand dieser Statistik k&ouml;nnen Sie zum Beispiel besser Planen, wann Sie sich Zeit f&uuml;r Telefonate nehmen sollten.</div>

                    <table style="padding:0; width:100%;">
                        <tbody>
                            <tr>
                                <td style="border-bottom:1px solid #000; width:200px; padding:5px 10px; text-align:center; font-weight:bold;">Tag und Uhrzeit</td>
                                <td style="border-bottom:1px solid #000; width:200px; padding:5px 10px; text-align:center; font-weight:bold;">Anrufer</td>
                                <td style="border-bottom:1px solid #000; width:200px; padding:5px 10px; text-align:center; font-weight:bold;">Herkunft</td>
                                <td style="width:auto"></td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="max-height:300px; overflow-x:none; overflow-y:auto; width:100%;">
                        <table style="padding:0; width:100%;">
                            <tbody>';
                                $rs_anrufe = p4c_query("SELECT * FROM `erocall` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `date_time` LIKE '".$get_monat."-%' AND `event`='hangup' AND `duration_out`='0' ORDER BY `date_time` DESC;") or die(mysql_error());
                                while($anrufe_ary = p4c_fetch_object($rs_anrufe)) {
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

                                    $site .= '
                                    <tr>
                                        <td style="width:200px; border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.date("d.m.Y H:i",strtotime($anrufe_ary->date_time)).'</td>
                                        <td style="width:200px; border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$anrufer.'</td>
                                        <td style="width:200px; border-bottom:1px solid #000; padding:5px 10px; text-align:center;">'.$country.'</td>
                                        <td style="width:auto"></td>
                                    </tr>';
                                }
                                $site .= '
                            </tbody>
                        </table>
                    </div>
                </div>';
                
            }
            
        } else {
            include(MCP_DIR.'/includes/hotline_create.inc.php');
        }
    }
    $site .= '
</div>';
