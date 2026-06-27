<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

// Erstelle jeden Monat eine neue Tabelle
p4c_query("CREATE TABLE IF NOT EXISTS `ads_impressions_".date('Ym')."` (
    `id` int(12) NOT NULL auto_increment,
    `p4c_partner_id` varchar(10) NOT NULL,
    `media_id` int(15) NOT NULL,
    `campaign_id` varchar(10) NOT NULL,
    `number_of_impressions` int(15) NOT NULL default '0', 
    `timestamp` date NOT NULL default '0000-00-00',
    `site_id` int(12) NOT NULL,
    `url` varchar(255) NOT NULL default '',
    `referer` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;",__FILE__,__LINE__);

// Erstelle jeden Monat eine neue Tabelle
p4c_query("CREATE TABLE IF NOT EXISTS `ads_clicks_".date('Ym')."` (
    `id` int(12) NOT NULL auto_increment,
    `p4c_partner_id` varchar(10) NOT NULL,
    `media_id` int(15) NOT NULL,
    `campaign_id` varchar(10) NOT NULL,
    `session` varchar(32) NOT NULL,
    `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
    `site_id` int(10) NOT NULL,
    `url` varchar(255) NOT NULL default '',
    `referer` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `session` (`session`),
    KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;",__FILE__,__LINE__);

$current_tap_campaigns = '';
$current_tap_sites = 'current';

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

$rs_sites = p4c_query("SELECT * FROM `sites` WHERE `status`='1' AND `is_eroads_active`='1';",__FILE__,__LINE__);

$get_website = '';
if (isset($_GET['website'])) {
    $get_website = strip_tags($_GET['website']);
}

$tbody_sites = '';
while($site_obj = p4c_fetch_object($rs_sites)) {
    
    if ($site_obj->partner_id == $merchant->partner_id()) {
        $domain = '<b style="color:#ae4740;">'.$site_obj->domain.'</b>';
    } else {
        $domain = $site_obj->domain;
    }
    
    
    $tbody_sites .= '
    <tr>
        <td>'.$domain.'</td>
        <td>'.$site_obj->number_of_movies.'</td>
        <td>'.$site_obj->number_of_photoalbums.'</td>
        <td>'.$site_obj->number_of_articles.'</td>
        <td>'.$site_obj->webmaster_commision.'%</td>                    
        <td><a href="'.MCP_URL.'/Webmaster/Ads/'.$site_obj->domain.'">Werbemittel</a></td>
        <td><a href="'.MCP_URL.'/Webmaster/New-Campaign?sid='.$site_obj->id.'">Kampagne erstellen</a></td>
    </tr>
    ';
}


$rs_campaigns = p4c_query("SELECT * FROM `ads_campaigns` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);

$tbody_campaigns = '';
$number_of_campaigns = '';
$count_campaigns = p4c_num_rows($rs_campaigns);
if ($count_campaigns > 0) {
    
    $current_tap_campaigns = 'current';
    $current_tap_sites = '';
    
    $number_of_campaigns = ' ('.$count_campaigns.')';
    
    // Site-Klasse einbinden 
    include(SOURCEDIR.'/includes/klassen/site.inc.php');
    
    while($campaign_obj = p4c_fetch_object($rs_campaigns)) {
        
        $rs_conversions = p4c_query("SELECT * FROM `ads_conversions` WHERE `campaign_id`='".p4c_escape_string($campaign_obj->campaign_id)."' AND `wmid`='".p4c_escape_string($campaign_obj->p4c_partner_id)."';");
        $rs_impressions = p4c_query("SELECT SUM(`number_of_impressions`) AS `number_of_impressions` FROM `ads_impressions_".date("Ym")."` WHERE `campaign_id`='".p4c_escape_string($campaign_obj->campaign_id)."' AND `p4c_partner_id`='".p4c_escape_string($campaign_obj->p4c_partner_id)."';");
        $count_impressions = p4c_result($rs_impressions,0);
        if ($count_impressions == '') {$count_impressions = 0;}
        
        
        $website = new Site($mysql,$campaign_obj->site_id);

        if ($website->get_var("id") != '') {
       
            $domain = $website->get_var("domain");
            if ($website->get_var("status") == 0) {
                $domain = '<s style="color:#ff0000;">'.$website->get_var("domain").'</s>';
            }            
            
            $tbody_campaigns .= '
            <tr>
                <td><a href="'.MCP_URL.'/Webmaster/Edit-Campaign?cid='.$campaign_obj->campaign_id.'">'.$campaign_obj->name.'</a></td>
                <td>'.$domain.' </td>
                <td>'.$campaign_obj->campaign_id.'</td>
                <td>'. $count_impressions.'</td>
                <td>'.$campaign_obj->count_clicks.'</td>
                <td>'. p4c_num_rows($rs_conversions).'</td>
                <td>'.date("Y-m-d H:i", strtotime($campaign_obj->create_date_time)).'</td>
            </tr>
            ';
        }
    }
}

// Prüfen ob Merchant eine eigene Webseite hat
$rs_own_sites = p4c_query("SELECT * FROM `sites` WHERE `partner_id`='". p4c_escape_string($merchant->partner_id())."' AND `status`='1' AND `is_eroads_active`='1';",__FILE__,__LINE__);
$count_own_sites = p4c_num_rows($rs_own_sites);

if ($count_own_sites > 0) {
    $tbody_own_sites = '';
    while($site_obj = p4c_fetch_object($rs_own_sites)) {

        $rs_ads = p4c_query("SELECT * FROM `ads_media` WHERE `site_id` = '".abs($site_obj->id)."' ORDER BY `upload_datetime` DESC;",__FILE__,__LINE__);
        
        $tbody_own_sites .= '
        <tr>
            <td><a href="javascript:;" data-tooltip="Banner bearbeiten oder Neue hochladen." data-site_id="'.$site_obj->id.'" class="my_banners material-symbols-outlined" style="font-size:15px">edit</a></td>
            <td>'.p4c_num_rows($rs_ads).'</td>
            <td>'.$domain = $site_obj->domain.'</td>
        </tr>
        ';
    }
    
    // von Webmatsern geworbene User
    $rs_referrerd_users = p4c_query("SELECT `ads_conversions`.*, `sites`.`domain` FROM `ads_conversions` INNER JOIN `sites` ON `ads_conversions`.`site_id`=`sites`.`id` WHERE `ads_conversions`.`p4c_partner_id`='".$merchant->partner_id()."' ORDER BY `ads_conversions`.`date_time` DESC;",__FILE__,__LINE__);

    $tbody_referrerd_users = '';
    $count_conv_text = '';
    $count_conv = p4c_num_rows($rs_referrerd_users);
    if ($count_conv > 0) {
        $count_conv_text = ' ('.$count_conv.')';

        while($conv_obj = p4c_fetch_object($rs_referrerd_users)) {

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

            if ($conv_obj->campaign_id != '') {
                $campaign = '<a href="'.MCP_URL.'/Webmaster/Edit-Campaign?cid='.$conv_obj->campaign_id.'&activeTabId=3">'.$conv_obj->campaign_id.'</a>';
            } else {
                $campaign = '-';
            }

            $wmid = $conv_obj->wmid;
            if ($conv_obj->wmid == $conv_obj->p4c_partner_id) {
                $wmid = "von dir geworben";
            }
            
            $tbody_referrerd_users .= '
            <tr>
                <td>'.$conv_obj->domain.'</td>
                <td>'.$conv_obj->date_time.'</td>
                <td>'.$conv_obj->username.'</td>
                <td>'.$status.'</td>
                <td>'.$value.'</td>
                <td>'.$wmid.'</td>                    
            </tr>
            ';
        }
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

        jQuery("table #sites").DataTable({
            "iDisplayLength": 25,
            "order": [[ 0, "asc" ]],
            "oSearch": { "sSearch": "'.$get_website.'" },
            "bLengthChange" : false,
            "bFiler":false,
            "columns": [
                {"className": "dt-body-left"},
                {"className": "center"},
                {"className": "center"},
                {"className": "center"},
                {"className": "center"},
                {"className": "center", "orderable": false},
                {"className": "center", "orderable": false}
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
        
        jQuery("table #campaigns").DataTable({
            "iDisplayLength": 25,
            "order": [[ 6, "asc" ]],
            "bLengthChange" : false,
            "bInfo":false,  
            "bFiler":false,
           
            "bAutoWidth": false,
            "aoColumns": [
                {sWidth: "auto", "className": "dt-body-left"},
                {sWidth: "120px", "className": "dt-body-left"},
                {sWidth: "auto", "className": "dt-body-left"},
                {sWidth: "80px", "className": "center"},
                {sWidth: "80px", "className": "center"},
                {sWidth: "80px", "className": "center"},
                {sWidth: "120px", "className": "center"}
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
        
        jQuery("table #own_sites").DataTable({
            "iDisplayLength": 25,
            "order": [[ 2, "asc" ]],
            "bLengthChange" : false,
            "bFiler":false,
            "bAutoWidth": false,
            "aoColumns": [
                {"width": "50px", "className": "center", "orderable": false},
                {"width": "100px", "className": "center"},
                {"width": "auto", "className": "dt-body-left"}
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
                jQuery(".my_banners").click(function() {
                    var site_id = jQuery(this).attr("data-site_id");

                    jQuery("#overlay").show(function() {
                        jQuery.ajax({
                            url: "'.MCP_URL.'/includes/overlays/webmaster_my_banners.php",
                            data: "site_id="+site_id,
                            method: "POST",
                            dataType: "html",
                            async: true,
                            success: function (data) {
                                jQuery("#my_banners_popup_content").html(data);
                                jQuery(".my_banners_popup").show();
                            }
                        })

                    });
                });
            }

        });
        
        jQuery("table #conversions").DataTable({
            "iDisplayLength": 25,
            "order": [[ 1, "desc" ]],
            "bLengthChange" : false,
            "bFiler":false,
            "columns": [
                {"className": "dt-body-left"},
                {"className": "center"},
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
        });

    } );
</script>

<div style="min-width:800px; max-width:1000px;">
    <h1 class="h4">Kunden werben</h1>
    
    <p>
        Verdiene Geld mit werben von Kunden. Erstelle zun&auml;chst eine Webekampagnen. Werbekampagnen erm&ouml;glichen es dir, deine Werbung sp&auml;ter besser zu analysieren um diese noch erfolgreicher zu gestalten.
    </p>
    <div class="ui-widget-header" style="padding:5px 10px; border-bottom:none; margin-top:10px;">Beispiel f&uuml;r Kampagnen</div>
    <div class="ui-widget-content" style="padding:10px; line-height:1.5; margin-bottom:10px;">
        Du willst die Seite "ladyjulina.com" <b>auf Twitter</b> und <b>auf deiner Wordpress-Seite</b> bewerben.<br />
        Suche dazu unter den Tab "Websites" die zu bewerbende Website herraus und klicke auf Kampagne erstellen.<br />
        <br />
        In diesem Fall empfiehlt es sich, dass du <b>zwei Kampagnen</b> erstellst.<br />
        Die eine Kampagne nennst du zum Beispiel "<b>Twitter</b>" und die zweite Kampagne nennst du "<b>Mein Wordpress</b>".<br />
        Jetzt wechselst du in die Kampagne "Twitter" und w&auml;hlst ein passende Werbemittel f&uuml;r die Webseite "ladyjulina.com" aus (in diesem Fall einfach die URL).
        Diese kannst du nun auf Twitter posten. Dann wechselst du in die Kampagne "Mein Wordpress" und erstellst dir ebenfalls die passenden Werbemittel f&uuml;r die Webseite
        "ladyjulina.com" und bindest diese in deinem Wordpress ein.<br />
        <br />
        Nun bewirbst du die Webseite "ladyjulina.com" auf zwei verschiedenen Platformen. Aufgrund der beiden Kampagnen, kannst du sehen, ob die Werbung bei Twitter oder
        auf deinem Blog erfolgreicher ist und welche mehr Klicks generiert.
    </div>
    
    <div class="info_box" style="margin-bottom:20px;">
        Bitte beachte, dass auch deine Klicks gez&auml;hlt werden. Damit deine Statistiken nicht verf&auml;lscht werden, vermeide es die von dir eingesetzten Werbemittel zu klicken.<br />
        Impressionen die durch dich generiert werden, erscheinen nicht in den Statistiken.
    </div>
    
    <div class="tabs">
        <ul>
            <li class="'.$current_tap_sites.' tab-link" data-tab="tab-1">Websites ('. p4c_num_rows($rs_sites).')</li>
            <li class="'.$current_tap_campaigns.' tab-link" data-tab="tab-2">Kampagnen'.$number_of_campaigns.'</li>
            ';
            if ($count_own_sites > 0) {
                $site .= '<li class="tab-link" data-tab="tab-3">eigene Banner verwalten</li>';
                $site .= '<li class="tab-link" data-tab="tab-4">deine Kunden</li>';
            }
            $site .= '
        </ul>

        <div id="tab-1" class="tab-content '.$current_tap_sites.'">
            <table id="sites" class="display" style="width:100%;">
                <thead>
                    <tr>
                        <th style="text-align:left;">Domain</th>
                        <th>Filme</th>
                        <th>Fotoalben</th>
                        <th>Artikel</th>
                        <th>Ihre Provision</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    '.$tbody_sites.'
                </tbody>
            </table>
        </div>
        
        <div id="tab-2" class="tab-content '.$current_tap_campaigns.'">';
            if (empty($tbody_campaigns)) {
                $site .= 'Sie haben noch keine Kampagne erstellt.';
            } else {
                $site .= '
                <table id="campaigns" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="text-align:left; vertical-align:top;">Name</th>
                            <th style="text-align:left; vertical-align:top;">Website</th>
                            <th style="text-align:left; vertical-align:top;">ID</th>
                            <th style="vertical-align:top;">Impressionen<br />('.date("Y-m").')</th>
                            <th style="vertical-align:top;">Klicks<br />(gesamt)</th>
                            <th style="vertical-align:top;">Conversions<br />(gesamt)</th>
                            <th style="vertical-align:top;">Angelegt am</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_campaigns.'
                    </tbody>
                </table>';
            }
            $site .= '
        </div>
        
        ';
        if ($count_own_sites > 0) {
            $site .= '
            <div id="tab-3" class="tab-content">
                <div style="margin-bottom:20px;">
                    Stelle hier Banner f&uuml;r deine Webmaster zur Verf&uuml;gung.<br />
                    Nur wenn du eigene Banner hochl&auml;dst, verbesserst du deine Chancen, dass Webmaster deine Seite bewerben.
                </div>
                <table id="own_sites" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Anzahl Banner</th>
                            <th style="text-align:left;">Website</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_own_sites.'
                    </tbody>
                </table>
            </div>
            
            <div class="my_banners_popup">
                <div style="text-align:right; top:20px; right:10px; position:absolute;">
                    <a href="javascript:;" class="close_overlay" style="width:35px; padding:0px; text-align:center;"><b>&#x2715;</b></a>
                </div>
                <div id="my_banners_popup_content"></div>
                <div style="text-align:right; float:right;">
                    <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
                </div>
            </div>
            
            <div class="my_banner_update_popup">
                <div style="text-align:right; top:20px; right:10px; position:absolute;">
                    <a href="javascript:;" class="close_overlay" style="width:35px; padding:0px; text-align:center;"><b>&#x2715;</b></a>
                </div>
                <div id="my_banner_update_popup_content"></div>
                <div style="text-align:right; float:right;">
                    <a href="javascript:;" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
                </div>
            </div>
            

            <div id="tab-4" class="tab-content">
                Hier findest du alle Kunden die von deinen Webmastern geworben wurden.
                <table id="conversions" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Website</th>
                            <th>Zeitpunkt</th>
                            <th>User</th>
                            <th>Status</th>
                            <th></th>
                            <th>Webmaster</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_referrerd_users.'
                    </tbody>
                </table>
            </div>



            ';
        }
        $site .= '

    </div>

</div>';

?>