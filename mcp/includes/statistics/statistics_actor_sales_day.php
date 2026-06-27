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

if (isset($_GET['day'])) {
    $get_day = preg_replace( '/[^0-9-]/i', '', $_GET['day']);
} else {
    $get_day = date("Y-m-d");
}

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
    <h2 class="ui-widget-header">Tagesums&auml;tze vom '.date("d.m.Y", strtotime($get_day)).' von '.$actor_obj->username.'</h2>
    <div class="ui-widget-content edit_content" style="font-size:20px; padding:10px; margin-bottom:40px; border-top:none;">

        <table id="all_actors_month">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Website</th>

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

                $sum_actor_day = 0;
                $sum_all_actor_day = 0;

                $count_receive_messages_day = 0;
                $sum_receive_messages_day = 0;

                $count_sold_movies_day = 0;
                $sum_sold_movies_day = 0;
                
                $count_sold_albums_day = 0;
                $sum_sold_albums_day = 0;

                $user_ary = array();
                
                // Messenger
                $rs_count_receive_messages = p4c_query("SELECT COUNT(`von`) AS `count`, SUM(`actor_commision`) AS `commision`, `members`.`id`, `members`.`username`, `members`.`domain` FROM `chat_messages_history` INNER JOIN `members` WHERE
                    `chat_messages_history`.`von_id`=`members`.`id` AND
                    `chat_messages_history`.`von`='member' AND
                    `chat_messages_history`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `chat_messages_history`.`an_id`='".abs($actor_id)."' AND
                    `chat_messages_history`.`datetime` LIKE '".$get_day." %' AND
                    `chat_messages_history`.`systemnachricht`='0'
                 GROUP BY `chat_messages_history`.`chat_id`;",__FILE__,__LINE__);

                while($mess_obj = p4c_fetch_object($rs_count_receive_messages)) {
                    $member_id = $mess_obj->id;
                   
                    $user_ary[$member_id]['username'] =  $mess_obj->username;
                    $user_ary[$member_id]['domain'] =  $mess_obj->domain;
                    $user_ary[$member_id]['count_receive_messages'] =  $mess_obj->count;
                    $user_ary[$member_id]['sum_receive_messages'] =  $mess_obj->commision / 100;
                }

                // Videos
                $rs_count_sold_movies = p4c_query("SELECT COUNT(`actor_id`) AS `count`, SUM(`actor_commision`) AS `commision`, `movies_access`.`user_id`, `movies_access`.`shop_id`, `movies`.`actor_id` FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id` WHERE
                    `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `movies`.`actor_id`='".abs($actor_id)."' AND
                    `buy_timestamp` LIKE '".$get_day." %' 
                    GROUP BY `movies_access`.`user_id`, `movies_access`.`shop_id`;",__FILE__,__LINE__);

                while($movie_obj = p4c_fetch_object($rs_count_sold_movies)) {
                    $actor_id = $movie_obj->actor_id;
                    
                    $rs_members = p4c_query("SELECT `username`, `domain`, `id` FROM `members` WHERE
                        `remote_member_id` = '".abs($movie_obj->shop_id)."_".abs($movie_obj->user_id)."'
                    LIMIT 1;",__FILE__,__LINE__);
                    
                    $member_id = p4c_result($rs_members,0,2);
                    
                    $user_ary[$member_id]['username'] = p4c_result($rs_members,0,0);
                    $user_ary[$member_id]['domain'] = p4c_result($rs_members,0,1);
                    $user_ary[$member_id]['count_sold_movies'] =  $movie_obj->count;
                    $user_ary[$member_id]['sum_sold_movies'] =  $movie_obj->commision / 100;
                }

                
                // Fotoalben
                $rs_count_sold_photo_albums = p4c_query("SELECT COUNT(`actor_id`) AS `count`, SUM(`actor_commision`) AS `commision`, `photo_albums_access`.`user_id`, `photo_albums_access`.`shop_id`, `photo_albums`.`actor_id` FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id`  WHERE
                    `photo_albums`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
                    `photo_albums`.`actor_id`='".abs($actor_id)."' AND
                    `buy_timestamp` LIKE '".$get_day." %'
                GROUP BY `photo_albums_access`.`user_id`, `photo_albums_access`.`shop_id`;",__FILE__,__LINE__);
                    
                while($album_obj = p4c_fetch_object($rs_count_sold_photo_albums)) {
                    $actor_id = $album_obj->actor_id;
                    
                    $rs_members = p4c_query("SELECT `username`, `domain`, `id` FROM `members` WHERE
                        `remote_member_id` = '".abs($album_obj->shop_id)."_".abs($album_obj->user_id)."'
                    LIMIT 1;",__FILE__,__LINE__);
                    
                    $member_id = p4c_result($rs_members,0,2);
                    
                    $user_ary[$member_id]['username'] = p4c_result($rs_members,0,0);
                    $user_ary[$member_id]['domain'] = p4c_result($rs_members,0,1);
                    $user_ary[$member_id]['count_sold_albums'] =  $album_obj->count;
                    $user_ary[$member_id]['sum_sold_albums'] =  $album_obj->commision / 100;
                }

                foreach($user_ary as $id => $value) {

                    // Messenger
                    $sum_receive_messages = 0;
                    if (isset($value['sum_receive_messages'])) {
                        $sum_receive_messages = $value['sum_receive_messages'];
                    }

                    $count_receive_messages = 0;
                    if (isset($value['count_receive_messages'])) {
                        $count_receive_messages = $value['count_receive_messages'];
                    }

                    $sum_receive_messages_day = $sum_receive_messages_day + $sum_receive_messages;
                    $count_receive_messages_day = $count_receive_messages_day + $count_receive_messages;

                    // Videos
                    $sum_sold_movies = 0;
                    if (isset($value['sum_sold_movies'])) {
                        $sum_sold_movies = $value['sum_sold_movies'];
                    }

                    $count_sold_movies = 0;
                    if (isset($value['count_sold_movies'])) {
                        $count_sold_movies = $value['count_sold_movies'];
                    }

                    $sum_sold_movies_day = $sum_sold_movies_day + $sum_sold_movies;
                    $count_sold_movies_day = $count_sold_movies_day + $count_sold_movies;

                    // Albums
                    $sum_sold_albums = 0;
                    if (isset($value['sum_sold_albums'])) {
                        $sum_sold_albums = $value['sum_sold_albums'];
                    }

                    $count_sold_albums = 0;
                    if (isset($value['count_sold_albums'])) {
                        $count_sold_albums = $value['count_sold_albums'];
                    }

                    $sum_sold_albums_day = $sum_sold_albums_day + $sum_sold_albums;
                    $count_sold_albums_day = $count_sold_albums_day + $count_sold_albums;
                    
                    
                    $sum_actor_day = $sum_actor_day + $sum_receive_messages + $sum_sold_movies + $sum_sold_albums;
                    $sum_all_actor_day = $sum_all_actor_day + $sum_receive_messages_day + $sum_sold_movies_day + $sum_sold_albums_day;

                    $site .= ' 
                    <tr>
                        <td>'.$value['username'].'</td>
                        <td>'.$value['domain'].'</td>

                        <td>'.$count_receive_messages.'</td>
                        <td>'.number_format($sum_receive_messages, 2, ',', '.').'</td>

                        <td>'.$count_sold_movies.'</td>
                        <td>'.number_format($sum_sold_movies, 2, ',', '.').'</td>

                        <td>'.$count_sold_albums.'</td>
                        <td>'.number_format($sum_sold_albums, 2, ',', '.').'</td>

                        <td style="font-weight:bold;">'.number_format($sum_actor_day, 2, ',', '.').'</td>
                    </tr>
                    ';
                }
                
                $site .= '
            </tbody>


            <tfoot>
                <tr>
                    <th>Gesamt</th>
                    <th></th>

                    <th>'.$count_receive_messages_day.'</th>
                    <th>'.number_format($sum_receive_messages_day, 2, ',', '.').' EUR</th>

                    <th>'.$count_sold_movies_day.'</th>
                    <th>'.number_format($sum_sold_movies_day, 2, ',', '.').' EUR</th>
                        
                    <th>'.$count_sold_albums_day.'</th>
                    <th>'.number_format($sum_sold_albums_day, 2, ',', '.').' EUR</th>

                    <th>'.number_format($sum_all_actor_day, 2, ',', '.').' EUR</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
';
