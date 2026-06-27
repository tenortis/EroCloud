<?php

if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

$actor_id = abs($_GET['actor_id']);

$rs_actors = p4c_query("SELECT * FROM `actors` WHERE `id`='".$actor_id."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`!='deleted' LIMIT 1;",__FILE__,__LINE__);

if (p4c_num_rows($rs_actors) == 0) {
    header('Location: '.MCP_URL.'/Statistics/TotalActorsSales');
    exit;
}

$actor_obj = p4c_fetch_object($rs_actors);


if (isset($_GET['month'])) {
    $get_month = preg_replace( '/[^0-9-]/i', '', $_GET['month']);
} else {
    $get_month = date("Y-m");
}

$days_in_month = date('t', strtotime($get_month));

$site .= '
<script src="https://cdn.datatables.net/fixedcolumns/3.3.2/js/dataTables.fixedColumns.min.js"></script>
<script>
    jQuery(document).ready(function() {

        jQuery("table #all_actors_month").DataTable({
            "order": [[ 0, "asc" ]],
            searching: false,
            paging: false,
            footer: true,
            "bInfo": false,
            fixedColumns: {
                leftColumns: 1,
                rightColumns: 1
            },
            "columns": [
                {"width": "150px"},
                {"width": "100px"},
                {"width": "100px"},
                {"width": "100px"},
                {"width": "100px"},
                {"width": "100px"},
                {"width": "100px"},
                {"width": "100px"}
            ]
        });

    })
</script>

<style>

    #all_actors_month {
        width:100%;
        margin:0;
        padding:0;
    }
    
    table.dataTable {
        margin:0;
        padding:0;
    }

    .DTFC_LeftHeadWrapper,
    .DTFC_LeftFootWrapper,
    .DTFC_RightHeadWrapper,
    .DTFC_RightFootWrapper,
    .DTFC_RightHeadBlocker,
    .DTFC_RightFootBlocker {
        background-color: #ffffff;
    }
    
    

    #all_actors_month th,
    #all_actors_month td {
        white-space: nowrap;
    }

    #all_actors_month_wrapper {
        min-width: 650px;
        width: fit-content;
        max-width: 1200px;
    }

    table.dataTable thead th,
    table.dataTable tfoot th {
        font-weight: bold;
        box-sizing: border-box;
    }
    
    table.dataTable tfoot th:last-child {
        font-size:1.5rem;
    }

    table.dataTable tbody td {
        text-align:center;
    }
</style>

<div style="width:fit-content; max-width:1800px;">
    <h2 class="ui-widget-header">Monatsums&auml;tze von '.$actor_obj->username.'</h2>
    <div class="ui-widget-content edit_content" style="font-size:20px; padding:10px; margin-bottom:40px; border-top:none;">

        <div style="margin-bottom:10px;">
            <form action="/Statistics/TotalActorSales/'.$actor_id.'" method="get">
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
                </select> <input type="submit" value="Anzeigen" />
            </form>
        </div>

        <table id="all_actors_month">
            <thead>
                <tr>
                    <th>Tag</th>

                    <th>Empfangen<br />Nachrichten</th>
                    <th>Provision</br>Messenger</th>

                    <th>Verkaufte<br />Filme</th>
                    <th>Provision<br />Filme</th>

                    <th>Verkaufte<br />Fotoalben</th>
                    <th>Provision<br />Fotoalben</th>

                    <th>Provision &sum;</th>
                </tr>
            </thead>

            <tbody>';

                $sum_actor_month = 0;
                $sum_all_actor_month = 0;

                $count_receive_messages_month = 0;
                $sum_receive_messages_month = 0;

                $count_sold_movies_month = 0;
                $sum_sold_movies_month = 0;
                
                $count_sold_movies_month = 0;
                $sum_sold_movies_month = 0;
                
                $count_sold_albums_month = 0;
                $sum_sold_albums_month = 0;



                for($i=1;$i<=$days_in_month;$i++) {
                    $day = $i;
                    if (strlen($day) == 1) {$day = '0'.$day;}
                    
                    // Messenger
                    $rs_count_receive_messages = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) AS `commision` FROM `chat_messages_history` WHERE
                        `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                        `von`='member' AND
                        `an_id`='".abs($actor_id)."' AND
                        `datetime` LIKE '".date($get_month.'-'.$day)."%' AND
                        `systemnachricht`='0'
                    ;",__FILE__,__LINE__);

                    $count_receive_messages = p4c_result($rs_count_receive_messages,0,0);
                    $sum_receive_messages = p4c_result($rs_count_receive_messages,0,1) / 100;

                    $count_receive_messages_month = $count_receive_messages_month + $count_receive_messages;
                    $sum_receive_messages_month = $sum_receive_messages_month + $sum_receive_messages;

                    // Videos
                    $rs_count_sold_movies = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id` WHERE
                        `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                        `movies`.`actor_id`='".abs($actor_id)."' AND
                        `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%' 
                    ;",__FILE__,__LINE__);

                    $count_sold_movies = p4c_result($rs_count_sold_movies,0,0);
                    $sum_sold_movies = p4c_result($rs_count_sold_movies,0,1) / 100;

                    $count_sold_movies_month = $count_sold_movies_month + $count_sold_movies;
                    $sum_sold_movies_month = $sum_sold_movies_month + $sum_sold_movies;

                    // Fotoalben
                    $rs_count_sold_photo_albums = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id`  WHERE
                        `photo_albums`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                        `photo_albums`.`actor_id`='".abs($actor_id)."' AND
                        `buy_timestamp` LIKE '".date($get_month.'-'.$day)."%' 
                    ;",__FILE__,__LINE__);
                    
                    
                    $count_sold_albums = p4c_result($rs_count_sold_photo_albums,0,0);
                    $sum_sold_albums = p4c_result($rs_count_sold_photo_albums,0,1) / 100;

                    $count_sold_albums_month = $count_sold_albums_month + $count_sold_albums;
                    $sum_sold_albums_month = $sum_sold_albums_month + $sum_sold_albums;

                    
                    // Gesamt
                    $sum_actor_month = $sum_receive_messages + $sum_sold_movies + $sum_sold_albums;
                    $sum_all_actor_month = $sum_receive_messages_month + $sum_sold_movies_month + $sum_sold_albums_month;

                    $site .= ' 
                    <tr>
                        <td><a href="'.MCP_URL.'/Statistics/TotalActorSales/'.$actor_id.'/Day/'.$get_month.'-'.$day.'">'.$day.'.</a></td>

                        <td>'.$count_receive_messages.'</td>
                        <td>'.number_format($sum_receive_messages, 2, ',', '.').'</td>

                        <td>'.$count_sold_movies.'</td>
                        <td>'.number_format($sum_sold_movies, 2, ',', '.').'</td>

                        <td>'.$count_sold_albums.'</td>
                        <td>'.number_format($sum_sold_albums, 2, ',', '.').'</td>

                        <td style="font-weight:bold;">'.number_format($sum_actor_month, 2, ',', '.').'</td>
                    </tr>
                    ';

                }
                $site .= '
            </tbody>


            <tfoot>
                <tr>
                    <th>Gesamt</th>

                    <th>'.$count_receive_messages_month.'</th>
                    <th>'.number_format($sum_receive_messages_month, 2, ',', '.').' EUR</th>

                    <th>'.$count_sold_movies_month.'</th>
                    <th>'.number_format($sum_sold_movies_month, 2, ',', '.').' EUR</th>
                        
                    <th>'.$count_sold_albums_month.'</th>
                    <th>'.number_format($sum_sold_albums_month, 2, ',', '.').' EUR</th>

                    <th>'.number_format($sum_all_actor_month, 2, ',', '.').' EUR</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
';
