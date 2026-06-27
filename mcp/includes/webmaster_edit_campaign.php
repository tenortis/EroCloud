<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$cid = strip_tags($_GET['cid']);

// Kampagnen-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/ads_campaign.inc.php');

$campaign = new Campaign($mysql,$cid);

if ($campaign->get_var("campaign_id") == '') {
    header('Location: '.MCP_URL.'/Webmaster/Ads');
    exit;
}

if (isset($_POST['delete_campaign'])) {
    if (p4c_query("DELETE FROM `ads_campaigns` WHERE `campaign_id`='". p4c_escape_string($campaign->get_var("campaign_id"))."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;")) {
        header('Location: '.MCP_URL.'/Webmaster/Ads');
        exit;
    }
}


// Site-Klasse einbinden 
include(SOURCEDIR.'/includes/klassen/site.inc.php');

$website = new Site($mysql,$campaign->get_var('site_id'));

if ($website->get_var("id") == '') {
    header('Location: '.MCP_URL.'/Webmaster/Ads');
    exit;
}

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

$c['name']          = $campaign->get_var('name');
$c['description']   = $campaign->get_var('description');
$c['count_clicks']  = $campaign->get_var('count_clicks');
$c['reserve']       = $campaign->get_var('reserve');
$c['reserve_value'] = $campaign->get_var('reserve_value');

$reserve['color'] = '';
$reserve['image'] = '';
if ($campaign->get_var('reserve') == 'image') {
    $reserve['image'] = $c['reserve_value'];
} else if ($campaign->get_var('reserve') == 'color') {
    $reserve['color'] = $c['reserve_value'];
}

$s['id'] = $website->get_var("id");
$s['domain'] = $website->get_var("domain");
$s['webmaster_commision'] = $website->get_var("webmaster_commision");

$replace_name_ary = array('°','^','²','§','§','$','%','{','[',']','}','´','`','~',"'",'_',';','<','>');

if (isset($_POST['edit_campaign'])) {
    
    $cid = p4c_escape_string(strip_tags($_POST['cid']));
    
    $c['name'] = trim(str_replace($replace_name_ary, '', $_POST['cname']));
    if (empty($c['name'])) {
        $c['name'] = $cid;
    }
    
    $c['description'] = trim(strip_tags($_POST['cdescription']));
    
    $reserve = $_POST['reserve'];
    if ($reserve == 'image') {
        $reserve = $_POST['reserve'];
        $reserve_value = strip_tags($_POST['reserve_value_image']);
        
        
    } else if ($reserve == 'color') {
        $reserve = $_POST['reserve'];
        $reserve_value = $_POST['reserve_value_color'];
        
        $reserve_value = preg_replace('/[^#0-9a-z]/i', '', $reserve_value);
        
        if (strlen($reserve_value) > 7) {
            $reserve_value = "#".substr($reserve_value, 1, 6);
        }
        
    } else {
        $reserve = 'blank';
        $reserve_value = '';
    }
    
    if (p4c_query("UPDATE `ads_campaigns` SET 
        `name`          = '".p4c_escape_string($c['name'])."',
        `description`   = '".p4c_escape_string($c['description'])."',
        `reserve`       = '".p4c_escape_string($reserve)."',
        `reserve_value` = '".p4c_escape_string($reserve_value)."'
    WHERE
        `campaign_id`='".p4c_escape_string($cid)."' AND
        `merchant_id`='".abs($_SESSION['merchant_id'])."'
    LIMIT 1;",__FILE__,__LINE__)) {
        header('Location: '.MCP_URL.'/Webmaster/Edit-Campaign?cid='.$cid);
        exit;
    }
}


$rs_conversions = p4c_query("SELECT * FROM `ads_conversions` WHERE `date_time` LIKE '".date("Y-m", strtotime($get_month))."%' AND `campaign_id`='".p4c_escape_string($cid)."' AND `wmid`='".$campaign->get_var('p4c_partner_id')."';");

$tbody_conversions = '';
$count_conv_text = '';
$count_conv = p4c_num_rows($rs_conversions);
if ($count_conv > 0) {
    $count_conv_text = ' ('.$count_conv.')';
    
    while($conv_obj = p4c_fetch_object($rs_conversions)) {

        $value = '';
        
        if ($conv_obj->join == 1) {
            $status = 'Registrierung aufgerufen';
        } else if ($conv_obj->reg == 1) {
            $status = 'Registriert';            
        } else if ($conv_obj->first_payment > 0.00) {
            $status = '<span style="color:#008000;">Erstbuchung</span>';
            $value = number_format($conv_obj->first_payment, 2, ',', ',').' EUR';
        } else if ($conv_obj->follow_payment > 0.00) {
            $status = '<span style="color:#008000;">Folgebuchung</span>';
            $value = number_format($conv_obj->follow_payment, 2, ',', ',').' EUR';
        } else if ($conv_obj->again_credited_payment > 0.00) {
            $status = '<span style="color:#008000;">Gutschrift</span>';
            $value = number_format($conv_obj->again_credited_payment, 2, ',', ',').' EUR';
        } else if ($conv_obj->canceled_payment > 0.00) {
            $status = '<span style="color:#ff0000;">Storno</span>';
            $value = number_format($conv_obj->canceled_payment, 2, ',', ',').' EUR';
        }
        
        $tbody_conversions .= '
        <tr>
            <td>'.$conv_obj->date_time.'</td>
            <td>'.$conv_obj->username.'</td>
            <td>'.$status.'</td>
            <td>'.$value.'</td>
        </tr>
        ';
    }
}

$rs_clicks = p4c_query("SELECT COUNT(*) AS `clicks`, `url` FROM `ads_clicks_".date("Ym", strtotime($get_month))."` WHERE `campaign_id`='".p4c_escape_string($cid)."' AND `p4c_partner_id`='".$campaign->get_var('p4c_partner_id')."' GROUP BY `url`;");
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
$rs_impressions = p4c_query("SELECT SUM(`number_of_impressions`) AS `number_of_impressions`, `url` FROM `ads_impressions_".date("Ym", strtotime($get_month))."` WHERE `campaign_id`='".p4c_escape_string($cid)."' AND `p4c_partner_id`='".$campaign->get_var('p4c_partner_id')."' GROUP BY `url`;");
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

    $count_impressions_text = ' ('.$count_impressions.')';
    
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

    
$site .= '
<div style="width:700px;">
    <h1 class="h4">Kampagne: '.$c['name'].'</h1>';

    if ($website->get_var("status") == 0) {
        $site .= '
        <div class="ui-state-error" style="padding:5px 15px; font-size:1rem; margin-bottom:10px;">
            Diese Website hat den Betrieb eingestellt und nimmt nicht mehr am Partnerprogramm teil.
        </div>
        ';
    }
    
    $site .= ' 
    <script type="text/javascript">
    // <![CDATA[
        jQuery(document).ready(function() {
        
            jQuery(".button_delete").click(function(){
                var r = confirm("Soll die Kampagne wirklich entfernt werden?");
                if (r == true) {
                    return true;
                } else {
                    return false;
                }
            });

        
            jQuery("#reserve").change(function(){
                var reverse = jQuery("#reserve").val();
                
                jQuery(".reserve_value_box, #reserve_value_box_"+reverse).hide();
                
                var placeholder = "";                
                
                if (reverse != "blank") {
                    jQuery("#reserve_value_box_"+reverse).show();
                } else {
                    jQuery("#reserve_value_"+reverse).val("");
                }
            })
            ';

            if ($c['reserve'] != 'blank') {
                $site .= 'jQuery("#reserve_value_box_'.$c['reserve'].'").show();';
            }
            
            $site .= '
            function show_banners(site_id) {
                jQuery.ajax({
                    url: mcp_url+"/Ajax/webmaster_show_site_banners.php",
                    data: "site_id='.$s['id'].'&cid='.$cid.'&wmid='.$campaign->get_var('p4c_partner_id').'",
                    method: "POST",
                    dataType: "html",
                    async: true,
                    success: function (data) {
                        jQuery(".ads").html(data);

                        jQuery(".show-banner-code").click(function(){
                            var banner_id = jQuery(this).attr("data-banner_id");
                            jQuery(\'.banner-code[data-banner_id="\'+banner_id+\'"]\').toggle();
                        })

                    }
                })
            }

            show_banners('.$s['id'].');


            jQuery( "#accordion" ).accordion({
                collapsible: true,
                heightStyle: "content",
                active: false
            });  

    
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
                "order": [[ 0, "asc" ]],
                "bLengthChange" : false,
                "bFiler":false,
                "columns": [
                    {"className": "center"},
                    {"className": "center"},
                    {"className": "center"},
                    {"className": "center"},
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
                                data: "month='.$get_month.'&cid='.$cid.'&origin="+origin,
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
                                data: "month='.$get_month.'&cid='.$cid.'&origin="+origin,
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
               
            })
        })

    // ]]>
    </script>
    
    <style>
        .edit_content{margin-bottom:20px;}
        input.button.ui-widget {font-size:16px !important;}
        
        table#clicks.dataTable td:nth-child(2) {
            padding-right: 30px;
        }
        
        .edit_title i {
            font-size:15px;
        }
        
        .ui-tooltip {
            max-width: 500px !important;
            width: auto !important;
            overflow:auto !important;
        }
        
        .ui-tooltip-content {
            
        }

    </style>';

    if (isset($error) AND !empty($error)) {
        $site .= '<div class="ui-state-error" style="padding:10px; margin-top:10px;">'.$error.'</div>';
    }
    $site .= '
        
    <div class="tabs">
        <ul>
            <li class="tab-link current" data-tab="tab-1">Bearbeiten</li>
            <li class="tab-link" data-tab="tab-2">Werbemittel</li>
            <li class="tab-link" data-tab="tab-3">Conversions'.$count_conv_text.'</li>
            <li class="tab-link" data-tab="tab-4">Klicks'.$count_clicks_text.'</li>
            <li class="tab-link" data-tab="tab-5">Impressionen'.$count_impressions_text.'</li>            
        </ul>

        <div id="tab-1" class="tab-content current">
            <form action="" method="post">
                <div class="ui-widget-content" style="padding:10px;">
                    <table style="width:100%;">
                        <tr>
                            <td style="width:45%;">
                                <div class="edit_title">Website</div>
                                <div class="edit_content" style="font-size:20px; font-weight:400;">
                                    '.$s['domain'].'
                                </div>
                            </td>
                            <td style="width:40%;">
                                <div class="edit_title">Kampagnen-ID (CID)</div>
                                <div class="edit_content" style="font-size:20px; font-weight:400;">
                                    '.$cid.'
                                </div>
                            </td>
                            <td style="width:20%">
                                <div class="edit_title">Ihre Provision</div>
                                <div class="edit_content" style="font-size:20px; font-weight:400;">
                                    '.$s['webmaster_commision'].'%
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;">
                        <tr>
                            <td style="width:30%;">
                                <div class="edit_title">Impressionen ('.$get_month.')</div>
                                <div class="edit_content" style="font-size:20px; font-weight:400;">
                                    '.$count_impressions.'
                                </div>
                            </td>
                            <td style="width:auto;">
                                <div class="edit_title">Klicks gesamt</div>
                                <div class="edit_content" style="font-size:20px; font-weight:400;">
                                    '.$c['count_clicks'].'
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="edit_title">Name der Kampagne</div>
                    <div class="edit_content">
                        <input type="text" name="cname" value="'.$c['name'].'" placeholder="Beispiel: Twitter-Werbung" style="font-size:18px;" />
                    </div>

                    <div class="edit_title">Ersatzanzeigen <i class="material-symbols-outlined" 
                        data-tooltip="
                        Ersatzanzeigen kommen erst dann zum Einsatz, wenn wir Probleme mit dem ausliefern der Anzeige (z.B. Banner) haben.<br >
                        <br />
                        <b>Anzeigenplatzhalter</b><br />
                        ist ein leerer div-Container in der gr&ouml;&szlig;e des Werbemittel.<br />
                        <br />
                        <b>Bild von einer anderen URL anzeigen</b><br />
                        Hier kannst du eine URL zu einer alternativen Grafik angeben. Diese Grafik solle die selben Ma&szlig;e haben, wie das von dir eingebundene Werbemittel.<br />
                        <br />
                        <b>Fl&auml;che mit Deckfarbe f&uuml;llen</b><br />
                        F&uuml;llt die Fl&auml;che mit der ausgew&auml;hlten Farbe.
                    ">help</i></div>
                    <div class="edit_content" style="margin-bottom:10px;">';
                        if ($c['reserve'] == 'blank') {$select_image = ''; $select_color='';}
                        if ($c['reserve'] == 'image') {$select_image = 'selected="selected"'; $select_color='';}
                        if ($c['reserve'] == 'color') {$select_image = ''; $select_color='selected="selected"';}
                         
                        $site .= '
                        <select name="reserve" id="reserve">
                            <option value="blank">Anzeigenplatzhalter</option>
                            <option value="image" '.$select_image.'>Bild von einer anderen URL anzeigen</option>
                            <option value="color" '.$select_color.'>Fl&auml;che mit Deckfarbe f&uuml;llen</option>
                        </select>
                    </div>
                    
                    <div class="reserve_value_box" id="reserve_value_box_color" style="display:none; margin-bottom:20px;">
                        <div>Hex-Farbcode</div>
                        <div class="edit_content">
                            <input type="text" value="'.$reserve['color'].'" id="reserve_value_color" name="reserve_value_color" placeholder="#ffffff"  style="font-size:18px;" />
                        </div>
                    </div>
                    <div class="reserve_value_box" id="reserve_value_box_image" style="display:none; margin-bottom:20px;">
                        <div>Bild-URL</div>
                        <div class="edit_content">
                            <input type="text" value="'.$reserve['image'].'" id="reserve_value_image" name="reserve_value_image" placeholder="https://example.com/image.jpg"  style="font-size:18px;" />
                        </div>
                    </div>


                    <div class="edit_title" style="margin-top:20px;">Beschreibe diese Kampagne oder gib sonstige Informationen zu dieser Kampagne an.<br />
                    Somit wei&szlig;t du auch zu einem sp&auml;teren Zeitpunkt noch, f&uuml;r was du diese Kampagne erstellt hast.</div>
                    <div class="edit_content">
                        <textarea name="cdescription" style="height:150px;">'.$c['description'].'</textarea>
                    </div>
                </div>

                <div class="info_box">
                    Du kannst diese Kampagne jederzeit l&ouml;schen. User die sich weiterhin &uuml;ber die von dir verbreiteten Werbemittel registrieren werden
                    auch nach dem L&ouml;schen dieser Kampagne geloggt und dir zugeordnet. Du hast nach dem L&ouml;schen nur keine Statistiken zu dieser Kampagne mehr.
                </div>

                <div style="margin-top:15px; margin-bottom:30px; text-align:right;">
                    <table style="width:100%;">
                        <tr>
                            <td style="width:50%; text-align:left;">
                                <input type="submit" name="delete_campaign" class="button button_delete" value="Kampange l&ouml;schen" />
                            </td>
                            <td style="width:50%">
                                <input type="hidden" name="cid" value="'.$cid.'" />
                                <input type="submit" name="edit_campaign" class="button" value="Kampange speichern" />
                            </td>
                        </tr>
                    </table>
                </div>
            </form>
        </div>
        
        <div id="tab-2" class="tab-content">
            <div id="accordion">
                <h3>URL / Textlink</h3>
                <div>
                    <div class="ui-widget-header" style="padding:5px 10px; border-bottom:none;">URL</div>
                    <input type="text" value="'.ADS_URL.'/l/'.$s['id'].'/'.$campaign->get_var('p4c_partner_id').'/'.$cid.'" readonly style="padding:3px 5px; width:100%; width: -webkit-fill-available; width: -moz-available; font-size:15px;" />
                        
                    <div class="ui-widget-header" style="margin-top:20px; padding:5px 10px; border-bottom:none;">Textlink</div>
                    <textarea readonly style="padding:3px 5px; height:50px; width:100%; width: -webkit-fill-available; width: -moz-available; font-size:15px;"><a href='.ADS_URL.'/l/'.$s['id'].'/'.$campaign->get_var('p4c_partner_id').'/'.$cid.'" target="_blank">'.$s['domain'].'</a></textarea>
                </div>
                
                <h3>XML-API</h3>
                <div>
                    <div><b>Informationen &uuml;ber den Darsteller</b></div>
                    <input type="text" style="width:100%; padding:2px; 5px; " value="https://'.$s['domain'].'/api/open-v1/actor_infos" />
                    <div>
                        &Uuml;ber diese API, bekommst du informationen wie den Online-/Offlinestatus und auch Details wie Haarfarbe, K&ouml;rbchengr&ouml;&szlig;e, "&Uuml;ber mich" usw.
                    </div>
                </div>

                <h3>Banner</h3>
                <div>
                    <div class="info_box" style="padding:5px 10px; margin:0 0 10px 0;">F&uuml;r eine bessere &Uuml;bersicht werden die Banner hier verkleinert angezeigt.<br />Klicke ein Banner um es in originalgr&ouml;&szlig;e zu sehen.</div>
                    <div class="ui-widget-content ads" style="position:relative; padding: 10px; margin-bottom:20px;"></div>
                </div>
            </div>
        </div>

        <div id="tab-3" class="tab-content">
        
            <form action="" method="post">
                <input type="hidden" id="activeTabId" name="activeTabId" value="3" />
                '.$select_month.'
            </form>

            <table id="conversions" class="display" style="width:100%;">
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>User</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    '.$tbody_conversions.'
                </tbody>
            </table>
        </div>
        
        <div id="tab-4" class="tab-content">        
            <form action="" method="post">
                <input type="hidden" id="activeTabId" name="activeTabId" value="4" />
                '.$select_month.'
            </form>

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
            </table>
        </div>
        
        <div id="tab-5" class="tab-content">
            <form action="" method="post">
                <input type="hidden" id="activeTabId" name="activeTabId" value="4" />
                '.$select_month.'
            </form>

            <table id="impressions" class="display" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:300px; text-align:left;">Quelle</th>
                        <th style="width:100px;">Impressionen</th>
                        <th style="width:auto;"></th>
                    </tr>
                </thead>
                <tbody>
                    '.$tbody_impressions.'
                </tbody>
            </table>
        </div>
    </div>
    
</div>';

?>