<?php

if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

/*
SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
            WHERE `movies`.`merchant_id`='4' AND
                `buy_timestamp` LIKE '2018-07%';
*/

$sql_movies_group = '';
$sql_photo_alben_group = '';
$sql_messenger_group = ''; 
$sql_webcam_group = '';

if ($_SESSION['logged_in_as'] == 'group' AND isset($_SESSION['my_chatgroup'])) {
    $sql_movies_group = "AND `movies`.`actor_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs($_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs($_SESSION['merchant_id'])."')";
    $sql_photo_alben_group = "AND `photo_albums`.`actor_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs($_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs($_SESSION['merchant_id'])."')";
    $sql_messenger_group = "AND `chat_messages_history`.`an_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs($_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs($_SESSION['merchant_id'])."')";
    $sql_webcam_group = "AND `revenue_webcam`.`actor_id` IN (SELECT `actor_id` FROM `group_actors` WHERE `group_id` = '".abs($_SESSION['my_chatgroup'])."' AND `merchant_id` = '".abs($_SESSION['merchant_id'])."')";
}


$site .= ' 
<div style="width:650px;">
    <h2 class="ui-widget-header">Ums&auml;tze nach Tage</h2>
    <div class="ui-widget-content edit_content" style="font-size:20px; padding:10px; margin-bottom:40px; border-top:none;">
        <div class="info_box" style="margin:0 0 10px 0">
            Die angezeigten Ums&auml;tze, k&ouml;nnen von den Ums&auml;tzen in deinem Pay4Coins-Account abweichen. Es z&auml;hlen die Ums&auml;tze als korrekt, die im Pay4Coins-Account unter "<a href="'.Pay4Coins_MCP_URL.'/Abrechnungen?activeTabId=1" target="_blank">Abrechnungen - Sie als Darsteller</a> angezeigt werden.
        </div>';


        if (isset($_GET['month'])) {
            $get_month = preg_replace( '/[^0-9-]/i', '', $_GET['month']);
        } else {
            $get_month = date("Y-m");
        }

        $days_in_month = date('t', strtotime($get_month));

        $tbody = '';
        $count_sold_movies_month_all = 0;
        $count_sold_movies_month_stream_all = 0;
        $count_sold_movies_month_download_all = 0;
        $sum_sold_movies_month_all = 0;

        for($i=1;$i<=$days_in_month;$i++) {
            $day = $i;
            if (strlen($day) == 1) {$day = '0'.$day;}

            /*
            $rs_count_sold_movies_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
                WHERE `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%';",__FILE__,__LINE__);
            */

            $rs_count_sold_movies_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id` 
                WHERE  `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%' 
                    ".$sql_movies_group."
                ;",__FILE__,__LINE__);

            $rs_count_sold_movies_day_stream = p4c_query("SELECT COUNT(*) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
                WHERE `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_as`='streaming' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%'
                    ".$sql_movies_group."
                ;",__FILE__,__LINE__);

            $rs_count_sold_movies_day_download = p4c_query("SELECT COUNT(*) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
                WHERE `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_as`='download' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%'
                    ".$sql_movies_group."
                ;",__FILE__,__LINE__);

            $count_sold_movies_day = p4c_result($rs_count_sold_movies_day_all,0,0);
            $count_sold_movies_month_all = $count_sold_movies_month_all + $count_sold_movies_day;

            $count_sold_movies_day_stream = p4c_result($rs_count_sold_movies_day_stream,0);
            $count_sold_movies_month_stream_all = $count_sold_movies_month_stream_all + $count_sold_movies_day_stream;

            $count_sold_movies_day_download = p4c_result($rs_count_sold_movies_day_download,0);
            $count_sold_movies_month_download_all = $count_sold_movies_month_download_all + $count_sold_movies_day_download;   

            $sum_sold_movies_month_all = $sum_sold_movies_month_all + p4c_result($rs_count_sold_movies_day_all,0,1);

            if (p4c_num_rows($rs_count_sold_movies_day_all) == 1) {
                $tbody .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>'.number_format((p4c_result($rs_count_sold_movies_day_all,0,1)/100),'2',',','.').' EUR</td>
                    <td>'.p4c_result($rs_count_sold_movies_day_all,0,0).'</td>
                    <td>'.p4c_result($rs_count_sold_movies_day_stream,0).'</td>
                    <td>'.p4c_result($rs_count_sold_movies_day_download,0).'</td>
                </tr>
                ';
            } else {
                $tbody .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                ';
            }
        }  

        $video_eur = number_format(($sum_sold_movies_month_all/100),'2',',','.');

        $tbody .= '
        <tr>
            <td style="font-weight:bold;">gesamt</td>
            <td style="font-weight:bold;">'.$video_eur.' EUR</td>
            <td style="font-weight:bold;">'.$count_sold_movies_month_all.'</td>
            <td style="font-weight:bold;">'.$count_sold_movies_month_stream_all.'</td>
            <td style="font-weight:bold;">'.$count_sold_movies_month_download_all.'</td>
        </tr>
        ';

        $tbody_photo_albums = '';
        $count_sold_photo_albums_month_all = 0;
        $count_sold_photo_albums_month_stream_all = 0;
        $count_sold_photo_albums_month_download_all = 0;
        $sum_sold_photo_albums_month_all = 0;

        for($i=1;$i<=$days_in_month;$i++) {
            $day = $i;
            if (strlen($day) == 1) {$day = '0'.$day;}

            /*
            $rs_count_sold_movies_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
                WHERE `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%';",__FILE__,__LINE__);
            */

            $rs_count_sold_photo_albums_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id` 
                WHERE  `photo_albums`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%' 
                    ".$sql_photo_alben_group."
                ;",__FILE__,__LINE__);

            $rs_count_sold_photo_albums_day_download = p4c_query("SELECT COUNT(*) FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id`
                WHERE `photo_albums`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `buy_as`='download' AND
                    `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%'
                    ".$sql_photo_alben_group."
                ;",__FILE__,__LINE__);

            $count_sold_photo_albums_day = p4c_result($rs_count_sold_photo_albums_day_all,0,0);
            $count_sold_photo_albums_month_all = $count_sold_photo_albums_month_all + $count_sold_photo_albums_day;

            $count_sold_photo_albums_day_download = p4c_result($rs_count_sold_photo_albums_day_download,0);
            $count_sold_photo_albums_month_download_all = $count_sold_photo_albums_month_download_all + $count_sold_photo_albums_day_download;   

            $sum_sold_photo_albums_month_all = $sum_sold_photo_albums_month_all + p4c_result($rs_count_sold_photo_albums_day_all,0,1);

            if (p4c_num_rows($rs_count_sold_photo_albums_day_all) == 1) {
                $tbody_photo_albums .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>'.number_format((p4c_result($rs_count_sold_photo_albums_day_all,0,1)/100),'2',',','.').' EUR</td>
                    <td>'.p4c_result($rs_count_sold_photo_albums_day_all,0,0).'</td>
                    <td>'.p4c_result($rs_count_sold_photo_albums_day_download,0).'</td>
                </tr>
                ';
            } else {
                $tbody_photo_albums .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                ';
            }
        }  

        $albums_eur = number_format(($sum_sold_photo_albums_month_all/100),'2',',','.');

        $tbody_photo_albums .= '
        <tr>
            <td style="font-weight:bold;">gesamt</td>
            <td style="font-weight:bold;">'.$albums_eur.' EUR</td>
            <td style="font-weight:bold;">'.$count_sold_photo_albums_month_all.'</td>
            <td style="font-weight:bold;">'.$count_sold_photo_albums_month_download_all.'</td>
        </tr>
        ';

        $tbody_messenger = '';
        $count_receive_messages = 0;
        $count_receive_messages_month_all = 0;
        $sum_receive_messages_month_all = 0;

        for($i=1;$i<=$days_in_month;$i++) {
            $day = $i;
            if (strlen($day) == 1) {$day = '0'.$day;}

            $rs_count_receive_messages = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) AS `commision` FROM `chat_messages_history` WHERE
                `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                `von`='member' AND
                `datetime` LIKE '".date($get_month.'-'.$day)."%'
                ".$sql_messenger_group."
            ;",__FILE__,__LINE__);

            $count_receive_messages_day = p4c_result($rs_count_receive_messages,0,0);
            $count_receive_messages_month_all = $count_receive_messages_month_all + $count_receive_messages_day;
            $sum_receive_messages_month_all = $sum_receive_messages_month_all + p4c_result($rs_count_receive_messages,0,1);

            if (p4c_num_rows($rs_count_receive_messages) == 1) {
                $tbody_messenger .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>'.number_format((p4c_result($rs_count_receive_messages,0,1)/100),'2',',','.').' EUR</td>
                    <td>'.p4c_result($rs_count_receive_messages,0,0).'</td>
                </tr>
                ';
            } else {
                $tbody_messenger .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                ';
            }
        }  

        $messenger_eur = number_format(($sum_receive_messages_month_all/100),'2',',','.');

        $tbody_messenger .= '
        <tr>
            <td style="font-weight:bold;">gesamt</td>
            <td style="font-weight:bold;">'.$messenger_eur.' EUR</td>
            <td style="font-weight:bold;">'.$count_receive_messages_month_all.'</td>
        </tr>
        ';

        $tbody_webcam = '';
        $count_webcam = 0;
        $count_webcam_month_all = 0;
        $sum_webcam_month_all = 0;

        for($i=1;$i<=$days_in_month;$i++) {
            $day = $i;
            if (strlen($day) == 1) {$day = '0'.$day;}

            $rs_count_webcam = p4c_query("SELECT SUM(`commision`) AS `commision` FROM `revenue_webcam` WHERE
                `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                `date` LIKE '".date($get_month.'-'.$day)."%'
                ".$sql_webcam_group."
            ;",__FILE__,__LINE__);

            $count_webcam_day = p4c_result($rs_count_webcam,0);
            $sum_webcam_month_all = $sum_webcam_month_all + p4c_result($rs_count_webcam,0);

            if (p4c_num_rows($rs_count_webcam) == 1) {
                $tbody_webcam .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>'.number_format((p4c_result($rs_count_webcam,0)/100),'2',',','.').' EUR</td>
                    <td></td>
                </tr>
                ';
            } else {
                $tbody_webcam .= '
                <tr>
                    <td>'.$day.'.</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                ';
            }
        }  

        $webcam_eur = number_format(($sum_webcam_month_all/100),'2',',','.');

        $tbody_webcam .= '
        <tr>
            <td style="font-weight:bold;">gesamt</td>
            <td style="font-weight:bold;">'.$webcam_eur.' EUR</td>
            <td style="font-weight:bold;"></td>
        </tr>
        ';

        $total_amaount = $sum_webcam_month_all + $sum_receive_messages_month_all + $sum_sold_movies_month_all + $sum_sold_photo_albums_month_all;
        $total_eur = number_format(($total_amaount/100),'2',',','.');


        $site .= '
        <div style="margin-bottom:10px;">
            <form action="/Statistics/TotalSales" method="get">
                Monat <select name="month" style="font-size:12px; width:100px; padding:2px;"> ';
                    $start_year = 2018;
                    $end_year = date("Y");

                    for($year = $start_year; $year <= $end_year; $year++) {
                        if ($year == $end_year) {
                            $end_month = date("m");
                            $start_month = 1;
                        } else {
                            $end_month = 12;
                            $start_month = date("m");
                        }

                        $site .= '<optgroup label="'.$year.'">';
                        for($month = $end_month; $month >= $start_month; $month--) {
                            if (strlen($month) == 1) {$month_with_zero = '0'.$month;} else {$month_with_zero = $month;}
                            if ($year.'-'.$month_with_zero == $get_month) {$selected='selected';} else {$selected='';}
                            $site .= '<option '.$selected.' value="'.$year.'-'.$month_with_zero.'">'.$formattedMonthArray[$month_with_zero].'</option>';
                        }
                        $site .= '</optgroup>';

                    }
                    $site .= '
                </select> <input type="submit" value="Anzeigen" /> = <b>'.$total_eur.' EUR</b>
            </form>
        </div>

        <div class="tabs" style="margin-top:10px; padding-bottom:20px; border-bottom:1px solid #4297d7">
            <script>
                jQuery(document).ready(function() {

                    jQuery.get("messenger/cronjobs/crawl_erocms_revenues.php", {
                        merchant_id: "'.$_SESSION['merchant_id'].'",
                        type: "webcam",
                        month: "'.date("Y-m-d").'"
                    });

                    jQuery("table #videos").DataTable({
                        "pageLength": '.($days_in_month+1).',
                        "order": [[ 0, "asc" ]],
                        "bLengthChange" : false,
                        "bInfo":false,  
                        "bFiler":false,
                        searching: false,
                        paging: false,
                        "columns": [
                            {"className": "center", "width": "50px"},
                            {"className": "center", "width": "110px"},
                            {"className": "center", "width": "70px"},
                            {"className": "center", "width": "100px"},
                            {"className": "center", "width": "100px"}
                        ]
                    });

                    jQuery("table #photo_albums").DataTable({
                        "pageLength": '.($days_in_month+1).',
                        "order": [[ 0, "asc" ]],
                        "bLengthChange" : false,
                        "bInfo":false,  
                        "bFiler":false,
                        searching: false,
                        paging: false,
                        "columns": [
                            {"className": "center", "width": "50px"},
                            {"className": "center", "width": "110px"},
                            {"className": "center", "width": "70px"},
                            {"className": "center", "width": "100px"}
                        ]
                    });

                    jQuery("table #messenger").DataTable({
                        "pageLength": '.($days_in_month+1).',
                        "order": [[ 0, "asc" ]],
                        "bLengthChange" : false,
                        "bInfo":false,  
                        "bFiler":false,
                        searching: false,
                        paging: false,
                        "columns": [
                            {"className": "center"},
                            {"className": "center"},
                            {"className": "center"}
                        ]
                    });

                    jQuery("table #webcam").DataTable({
                        "pageLength": '.($days_in_month+1).',
                        "order": [[ 0, "asc" ]],
                        "bLengthChange" : false,
                        "bInfo":false,  
                        "bFiler":false,
                        searching: false,
                        paging: false,
                        "columns": [
                            {"className": "center"},
                            {"className": "center"},
                            {"className": "center"}
                        ]
                    });
                } );
            </script>

            <ul>
                <li class="tab-link current" data-tab="tab-1">Videos ('.$video_eur.'&euro;)</li>
                <li class="tab-link" data-tab="tab-2">Fotoalben ('.$albums_eur.'&euro;)</li>
                <li class="tab-link" data-tab="tab-3">Messenger ('.$messenger_eur.'&euro;)</li>
                <li class="tab-link" data-tab="tab-4">Webcam ('.$webcam_eur.'&euro;)</li>
            </ul>

            <div id="tab-1" class="tab-content current">
                <table id="videos" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Provision</th>
                            <th>Verk&auml;ufe</th>
                            <th>als Streaming</th>
                            <th>als Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody.'
                    </tbody>
                </table>
            </div>

            <div id="tab-2" class="tab-content">
                <table id="photo_albums" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Provision</th>
                            <th>Verk&auml;ufe</th>
                            <th>als Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_photo_albums.'
                    </tbody>
                </table>
            </div>

            <div id="tab-3" class="tab-content">
                <table id="messenger" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Provision</th>
                            <th>empfangen</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_messenger.'
                    </tbody>
                </table>
            </div>

            <div id="tab-4" class="tab-content">
                <table id="webcam" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Provision</th>
                            <th>-</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody_webcam.'
                    </tbody>
                </table>
            </div>

        </div>';

        $rs_count_sold_movies_all = p4c_query("SELECT * FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
            WHERE `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."';",__FILE__,__LINE__);

        $rs_count_sold_movies_streaming = p4c_query("SELECT * FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
            WHERE
            `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
            `buy_as`='streaming';",__FILE__,__LINE__);

        $rs_count_sold_movies_download = p4c_query("SELECT * FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id`
            WHERE
            `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
            `buy_as`='download';",__FILE__,__LINE__);            

        $site .= '
        <div style="margin-top:10px;"><b>Bisher verkaufte Filme:</b> '.p4c_num_rows($rs_count_sold_movies_all).'</div>
        <div style="margin-left:10px;">&bull; als Streaming: '. p4c_num_rows($rs_count_sold_movies_streaming).'</div>
        <div style="margin-left:10px;">&bull; als Download: '. p4c_num_rows($rs_count_sold_movies_download).'</div>
        ';


        $rs_count_sold_photo_albums_download = p4c_query("SELECT * FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id`
            WHERE
            `photo_albums`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
            `buy_as`='download';",__FILE__,__LINE__);            

        $site .= '
        <div style="margin-top:10px;"><b>Bisher verkaufte Fotoalben:</b> '.p4c_num_rows($rs_count_sold_photo_albums_download).'</div>';

        $rs_messeges = p4c_query("SELECT count(*), SUM(message_price) FROM `chat_messages_history` WHERE 
            `merchant_id`='".abs($_SESSION['merchant_id'])."' AND 
            `von`='member';",__FILE__,__LINE__);

        $site .= '
        <div style="margin-top:10px;"><b>Bisher empfangene Nachrichten:</b> '.p4c_result($rs_messeges,0,0).'</div>

    </div>
</div>
';
