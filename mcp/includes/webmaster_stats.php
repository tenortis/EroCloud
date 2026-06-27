<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

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


$rs_clicks = p4c_query("SELECT COUNT(*) AS `clicks`, `url` FROM `ads_clicks_".date("Ym", strtotime($get_month))."` WHERE `p4c_partner_id`='".$merchant->partner_id()."' GROUP BY `url`;");

$tbody_clicks = '';
$count_clicks_text = '';
$count_clicks = 0;
if (p4c_num_rows($rs_clicks) > 0) {
   
    while($click_obj = p4c_fetch_object($rs_clicks)) {
        $count_clicks = $count_clicks+$click_obj->clicks;
        
        if ($click_obj->url == 'none') {
            $origin = '(direct)';
        } else {
            $origin = '<a href="javascript:;" class="clicks" data-origin="'.$click_obj->url.'">'.$click_obj->url.'</a>';
        }
        
        $tbody_clicks .= '
        <tr>
            <td>'.$origin.'</td>
            <td>'.$click_obj->clicks.'</td>
            <td></td>
        </tr>
        ';
    }

    $count_clicks_text = ' ('.$count_clicks.')';
    
}

$rs_impressions = p4c_query("SELECT SUM(`number_of_impressions`) AS `number_of_impressions`, `url` FROM `ads_impressions_".date("Ym", strtotime($get_month))."` WHERE `p4c_partner_id`='".$merchant->partner_id()."' GROUP BY `url`;");
#echo "SELECT SUM(`number_of_impressions`) AS `number_of_impressions`, `url` FROM `ads_impressions_".date("Ym", strtotime($get_month))."` WHERE `p4c_partner_id`='".$merchant->partner_id()."' GROUP BY `url`;";

$tbody_impressions = '';
$count_impressions_text = '';
$count_impressions = 0;
if (p4c_num_rows($rs_impressions) > 0) {
   
    while($impressions_obj = p4c_fetch_object($rs_impressions)) {
        $count_impressions = $count_impressions+$impressions_obj->number_of_impressions;
        
        if ($impressions_obj->url == 'none') {
            $origin = '(direct)';
        } else {
            $origin = '<a href="javascript:;" class="impressions" data-origin="'.$impressions_obj->url.'">'.$impressions_obj->url.'</a>';
        }
        
        $tbody_impressions .= '
        <tr>
            <td>'.$origin.'</td>
            <td>'.$impressions_obj->number_of_impressions.'</td>
            <td></td>
        </tr>
        ';
    }

    $count_impressions_text = ' ('.number_format($count_impressions, '0', '', '.').')';
    
}


$rs_conversions = p4c_query("SELECT `ads_conversions`.*, `sites`.`domain` FROM `ads_conversions` INNER JOIN `sites` ON `ads_conversions`.`site_id`=`sites`.`id` WHERE `ads_conversions`.`date_time` LIKE '".date("Y-m", strtotime($get_month))."%' AND `ads_conversions`.`wmid`='".$merchant->partner_id()."';",__FILE__,__LINE__);

$tbody_conversions = '';
$count_conv_text = '';
$conversions_sum = 0;
$conversions_sum_text = '';
$count_conv = p4c_num_rows($rs_conversions);
if ($count_conv > 0) {
    $count_conv_text = ' ('.$count_conv;
    
    while($conv_obj = p4c_fetch_object($rs_conversions)) {

        $value = '';
        
        if ($conv_obj->join == 1) {
            $status = 'Registrierung aufgerufen';
        } else if ($conv_obj->reg == 1) {
            $status = 'Registriert';            
        } else if ($conv_obj->first_payment > 0.00) {
            $status = '<span style="color:#008000;">Erstbuchung</span>';
            $value = number_format($conv_obj->first_payment, 2, ',', ',').' EUR';
            $conversions_sum = $conversions_sum + $conv_obj->first_payment;
        } else if ($conv_obj->follow_payment > 0.00) {
            $status = '<span style="color:#008000;">Folgebuchung</span>';
            $value = number_format($conv_obj->follow_payment, 2, ',', ',').' EUR';
            $conversions_sum = $conversions_sum + $conv_obj->follow_payment;
        } else if ($conv_obj->again_credited_payment > 0.00) {
            $status = '<span style="color:#008000;">Gutschrift</span>';
            $value = number_format($conv_obj->again_credited_payment, 2, ',', ',').' EUR';
        } else if ($conv_obj->canceled_payment > 0.00) {
            $status = '<span style="color:#ff0000;">Storno</span>';
            $value = number_format($conv_obj->canceled_payment, 2, ',', ',').' EUR';
        }
        
        if ($conv_obj->campaign_id != '') {
            $campaign = '<a href="'.MCP_URL.'/Webmaster/Edit-Campaign?cid='.$conv_obj->campaign_id.'&activeTabId=3">'.$conv_obj->campaign_id.'</a>';
        } else {
            $campaign = '-';
        }
        
        $tbody_conversions .= '
        <tr>
            <td>'.$conv_obj->domain.'</td>
            <td style="width:110px">'.$conv_obj->date_time.'</td>
            <td style="width:80px">'.$campaign.'</td>
            <td style="width:auto">'.$conv_obj->username.'</td>
            <td style="width:120px">'.$status.'</td>
            <td style="width:60px">'.$value.'</td>
        </tr>
        ';
    }

    
    if ($conversions_sum > 0) {
        $conversions_sum_text = ' = '.number_format($conversions_sum , 2, '.', ',').' EUR)';
    } else {
        $conversions_sum_text = ')';
    }
}

$site .= '
<script>
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

        jQuery("table #conversions").DataTable({
            "iDisplayLength": 25,
            "order": [[ 1, "desc" ]],
            "bLengthChange" : false,
            "bFiler":false,
            "columns": [
                {sWidth: "200px", "className": "dt-body-left"},
                {sWidth: "120px", "className": "center"},
                {sWidth: "120px", "className": "center"},
                {sWidth: "auto", "className": "center"},
                {sWidth: "100px", "className": "center"},
                {sWidth: "100px", "className": "center"},
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
        })
        
        jQuery("table #clicks").DataTable({
            "iDisplayLength": 25,
            "order": [[ 0, "asc" ]],
            "bLengthChange" : false,
            "bFiler":false,
            "columns": [
                {"className": "dt-body-left"},
                {"swidth":"80px", "className": "center"},
                {"orderable": false}
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
            },

            "fnInitComplete": function(settings, json) {
                jQuery(".clicks").click(function() {
                    var origin = jQuery(this).attr("data-origin");

                    jQuery("#overlay").show(function() {
                        jQuery.ajax({
                            url: "'.MCP_URL.'/includes/ajax/webmaster_origin_details.php",
                            data: "month='.$get_month.'&origin="+origin,
                            method: "POST",
                            dataType: "html",
                            async: true,
                            success: function (data) {
                                jQuery("#clicks_popup_content").html(data);
                                jQuery(".clicks_popup").show();
                            }
                        })

                    });
                });

            }
        })
        
        jQuery("table #impressions").DataTable({
            "iDisplayLength": 25,
            "order": [[ 0, "asc" ]],
            "bLengthChange" : false,
            "bFiler":false,
            "columns": [
                {"className": "dt-body-left"},
                {"swidth":"80px", "className": "center"},
                {"orderable": false}
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
            },

            "fnInitComplete": function(settings, json) {
                jQuery(".impressions").click(function() {
                    var origin = jQuery(this).attr("data-origin");

                    jQuery("#overlay").show(function() {
                        jQuery.ajax({
                            url: "'.MCP_URL.'/includes/ajax/webmaster_origin_details_impressions.php",
                            data: "month='.$get_month.'&origin="+origin,
                            method: "POST",
                            dataType: "html",
                            async: true,
                            success: function (data) {
                                jQuery("#impressions_popup_content").html(data);
                                jQuery(".impressions_popup").show();
                            }
                        })

                    });
                });

            }

        });
    } );
</script>

<div style="min-width:800px; max-width:1000px;">
    <h1 class="h4">Deine Webmaster-Statistiken f&uuml;r "Kunden werben"</h1>
    
    <p>
        Hier findest du alle Klicks, Impressionen und Conversions (inklusive die deiner Kampagnen) die durch deine Werbemittel generiert wurden.<br />
        Deine erzielte Provision findest du in deinem Merchant-Bereich bei Pay4Coins unter "<a href="'.Pay4Coins_MCP_URL.'/Abrechnungen?activeTabId=2" target="_balnk">Abrechnung - Sie als Webmaster</a>".
    </p>
    
    <div class="info_box" style="margin-bottom:10px;">
        Bitte beachte, dass auch deine Klicks gez&auml;hlt werden. Damit deine Statistiken nicht verf&auml;lscht werden, vermeide es die von dir eingesetzten Werbemittel zu klicken.<br />
        Impressionen die durch dich generiert werden, erscheinen nicht in den Statistiken.
    </div>
    
    <div style="font-size:15px; width:100%; text-align:right;">
        <form action="" method="">
            '.$select_month.'
        </form>
    </div>
    
    <div class="tabs">
        <ul>
            <li class="current tab-link" data-tab="tab-1">Klicks'.$count_clicks_text.'</li>
            <li class="tab-link" data-tab="tab-2">Impressionen'.$count_impressions_text.'</li>
            <li class="tab-link" data-tab="tab-3">Conversions'.$count_conv_text.$conversions_sum_text.'</li>
        </ul>

        <div id="tab-1" class="tab-content current">';
            if (empty($tbody_clicks)) {
                $site .= 'Es wurden noch keine Klicks erzielt.';
            } else {
                $site .= ' 
                <table id="clicks" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:300px; text-align:left;">Quelle</th>
                            <th style="width:100px;">Klicks</th>
                            <th style="width:auto;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_clicks.'
                    </tbody>
                </table>';
            }
            $site .= '
        </div>
        
        <div id="tab-2" class="tab-content">';
            if (empty($tbody_impressions)) {
                $site .= 'Du hast noch keine Impressions generiert.';
            } else {
                $site .= '
                <table id="impressions" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:300px; text-align:left;">Quelle</th>
                            <th style="width:100px;">Impressions</th>
                            <th style="width:auto;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_impressions.'
                    </tbody>
                </table>';
            }
            $site .= '
        </div>

        <div id="tab-3" class="tab-content">';
            if (empty($tbody_conversions)) {
                $site .= 'Du hast noch keine Conversion generiert.';
            } else {
                $site .= '
                <table id="conversions" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Website</th>
                            <th>Zeitpunkt</th>
                            <th>Kampagne</th>
                            <th>User</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_conversions.'
                    </tbody>
                </table>';
            }
            $site .= '
        </div>
    </div>

</div>';

?>