<?php

$import_conversion_date = date("Y-m-d");


if (isset($_POST['import_conversions'])) {
    if (!strtotime($_POST['import_conversions'])) {
        echo 'import_conversions isn\'t date';
        exit;        
    }

    $import_conversion_date = date("Y-m-d", strtotime($_POST['import_conversions']));
    
    exec('/usr/bin/php '.SOURCEDIR.'/cronjobs/import_conversions.php --date='.escapeshellarg($import_conversion_date), $output, $retval);
    print_r($output);
    print_r($retval);

}


if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (isset($_POST['month'])) {
    if (strlen($_POST['month']) == 4) {
        $get_month = $_POST['month'];
    } else {
        $get_month = date("Y-m", strtotime($_POST['month']));
    }
} else if (isset($_GET['month'])) {
    if (strlen($_GET['month']) == 4) {
        $get_month = $_GET['month'];
    } else {
        $get_month = date("Y-m", strtotime($_GET['month']));
    }
} else {
    $get_month = date("Y-m");
}

if (date("Ym", strtotime($get_month)) < '201809') {
    $get_month = date("Y-m");
}

$select_month = '
Monat <select name="month" onchange="submit();">';
    $start = strtotime("2018-09-01");
    $end = time();

    $y1 = date("Y", $start);
    $y2 = date("Y", $end);
    $m1 = date("m", $start);
    $m2 = date("m", $end);

    $start_year = date("Y");
    $year_plus1 = $y1;

    $count_month = (($y2 - $y1) * 12) + ($m2 - $m1);
    for($i=$count_month;$i>=0;$i--) {

        $year = date("Y", strtotime( "18-09-01 +".$i." month"));
        if ($year_plus1 != $year) {
            if ((int)$get_month == (int)$year) {$selected = 'selected';} else {$selected='';}
            $select_month .= '<option '.$selected.' value="'.$year.'" style="font-weight:bold;">'.$year.'</option>';
            $year_plus1 = date("Y", strtotime( "18-09-01 +".$i." month"));
        }

        $m = date("Y-m", strtotime( "2018-09-01 +".$i." month"));
        if ($get_month == $m) {$selected = 'selected';} else {$selected='';}

        $select_month .= '<option '.$selected.' value="'.$m.'">'.$m.'</option>';
    }
    $select_month .= '
</select>';

$get_month2 = str_replace('-', '', $get_month);

$rs_webmaster = p4c_query("SELECT `merchant_id`, `p4c_partner_id`, SUM(`count_clicks`) AS `all_clicks`, COUNT(*) AS `all_campaigns` FROM `ads_campaigns` GROUP BY `p4c_partner_id`;",__FILE__,__LINE__);

$tbody_campaigns = '';
$number_of_campaigns = '';
$count_webmaster = p4c_num_rows($rs_webmaster);
if ($count_webmaster > 0) {
    
    $number_of_webmasters = ' ('.$count_webmaster.')';
        
    while($wm_obj = p4c_fetch_object($rs_webmaster)) {
        
        $p4c_partner_id = $wm_obj->p4c_partner_id;
        
        $rs_conversions = p4c_query("SELECT * FROM `ads_conversions` WHERE `p4c_partner_id`='".p4c_escape_string($wm_obj->p4c_partner_id)."' AND `date_time` LIKE '".$get_month."%';");
        $rs_impressions = p4c_query("SELECT SUM(`number_of_impressions`) AS `number_of_impressions` FROM `ads_impressions_".$get_month2."` WHERE `p4c_partner_id`='".p4c_escape_string($wm_obj->p4c_partner_id)."';");
        $count_impressions = p4c_result($rs_impressions,0);
        if ($count_impressions == '') {$count_impressions = 0;}
          
        $merchant = new Merchant($mysql,$wm_obj->p4c_partner_id);
        if ($merchant->id() == '') {
            $p4c_username = 'unbekannt';
        } else {
            $p4c_username = '<a href="'.ACP_URL.'/Haendler/'.$wm_obj->p4c_partner_id.'">'.$merchant->username('aes_decrypt').'</a>';
            $p4c_partner_id = '<a href="'.ACP_URL.'/Haendler/'.$wm_obj->p4c_partner_id.'">'.$wm_obj->p4c_partner_id.'</a>';
        }
        
        $merchant_id = $merchant->id();
        
        $tbody_campaigns .= '
        <tr>
            <td>'.$p4c_partner_id.'</td>
            <td>'.$p4c_username.'</td>
            <td>'.$wm_obj->all_campaigns.'</td>
            <td>'. p4c_num_rows($rs_conversions).'</td>
            <td>'.$wm_obj->all_clicks.'</td>
            <td>'. $count_impressions.'</td>
            <td></td>
        </tr>
        ';

    }
}


$site .= '
<div id="tabs">
    <ul>
        <li><a href="#a">Aktive Webmaster</a></li>
    </ul>
    <div id="a">

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
                               
               
                jQuery("table #partner").DataTable({
                
                    "bJQueryUI": true,
                    "iDisplayLength": 30,
                    "aaSorting": [[ 3, "desc" ]],
                    "bLengthChange" : false,


                    "bAutoWidth": false,
                    "aoColumns": [
                        {sWidth: "150px", "sClass": "center"},
                        {sWidth: "150px", "sClass": "center"},
                        {sWidth: "80px", "sClass": "center"},
                        {sWidth: "80px", "sClass": "center"},
                        {sWidth: "80px", "sClass": "center"},
                        {sWidth: "80px", "sClass": "center"},
                        {sWidth: "auto", "sClass": "center"}
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
        
        <div class="ui-widget-header" style="padding:10px; font-size:20px;">'.p4c_num_rows($rs_webmaster).' Webmaster</div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:20px;">
            Hier sind alle aktiven Webmaster aufgelistet.
        </div>

        <table style="width:100%;">
            <tr>
                <td style="width:300px;">
                    <div style="font-size:1rem; margin-bottom:20px;">
                        <form action="" method="">
                            '.$select_month.'
                        </form>
                    </div>
                </td>
                <td style="width:auto; vertical-align:top;">
                    <form action="" method="post">
                        Conversions erneut crawlen vom Tag <input type="date" name="import_conversions" value="'.$import_conversion_date.'" max-date="'.date("Y-m-d").'" />
                            <input type="submit" value="abrufen" />
                    </form>
                </td>
            </tr>
        </table>

        ';
        if (empty($tbody_campaigns)) {
            $site .= 'Sie haben noch keine Kampagne erstellt.';
        } else {
            $site .= '
            <table id="partner" class="display" style="width:100%;">
                <thead>
                    <tr>
                        <th style="vertical-align:top;">P4C-Partner-ID</th>
                        <th style="vertical-align:top;">P4C-Username</th>
                        <th style="vertical-align:top;">Kampagnen<br />gesamt</th>
                        <th style="vertical-align:top;">Conversions<br />'.$get_month.'</th>
                        <th style="vertical-align:top;">Klicks<br />gesamt</th>
                        <th style="vertical-align:top;">Impressionen<br />'.$get_month.'</th>
                        <th style="vertical-align:top;">-</th>
                        
                    </tr>
                </thead>
                <tbody>
                    '.$tbody_campaigns.'
                </tbody>
            </table>';
        }
        $site .= '
    </div>
</div>

';




?>