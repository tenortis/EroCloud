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
    'March'     => 'Maerz',
    'May'       => 'Mai',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'October'   => 'Oktober',
    'December'  => 'Dezember',
);

if (isset($_GET['month'])) {
    $get_month = preg_replace( '/[^0-9-]/i', '', $_GET['month']);
} else {
    $get_month = date("Y-m");
}

$month_name_DE = strtr(date("F", strtotime(date($get_month))), $date_DE);
$days_in_month = date('t', strtotime($get_month));


// Videos
$rs_count_sold_movies_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `movies_access` LEFT JOIN `movies` ON `movies_access`.`movie_id`=`movies`.`file_id` 
    WHERE  `movies`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `buy_timestamp` LIKE '".date($get_month.'-')."%' 
        ".$sql_movies_group."
    ;",__FILE__,__LINE__);
$count_sold_movies_day = p4c_result($rs_count_sold_movies_day_all,0,1);
$sum_movies = $count_sold_movies_day/100;
$video_eur = number_format($sum_movies,'2',',','.');

// Fotoalben
$rs_count_sold_photo_albums_day_all = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) FROM `photo_albums_access` LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id`=`photo_albums`.`album_id` 
    WHERE  `photo_albums`.`merchant_id`='".abs($_SESSION['merchant_id'])."' AND
        `buy_timestamp` LIKE '".date($get_month.'-')."%' 
        ".$sql_photo_alben_group."
    ;",__FILE__,__LINE__);
$sum_sold_photo_albums_month_all = p4c_result($rs_count_sold_photo_albums_day_all,0,1);
$sum_albums = $sum_sold_photo_albums_month_all/100;
$albums_eur = number_format($sum_albums,'2',',','.');

// Messenger
$rs_count_receive_messages = p4c_query("SELECT COUNT(*), SUM(`actor_commision`) AS `commision` FROM `chat_messages_history` WHERE
    `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
    `von`='member' AND
    `datetime` LIKE '".date($get_month.'-')."%'
    ".$sql_messenger_group."
;",__FILE__,__LINE__);
$sum_receive_messages_month_all = p4c_result($rs_count_receive_messages,0,1);
$sum_messenger = $sum_receive_messages_month_all/100;
$messenger_eur = number_format($sum_messenger,'2',',','.');

// Webcam
$rs_count_webcam = p4c_query("SELECT SUM(`commision`) AS `commision` FROM `revenue_webcam` WHERE
    `merchant_id`='".abs($_SESSION['merchant_id'])."' AND
    `date` LIKE '".date($get_month.'-')."%'
    ".$sql_webcam_group."
;",__FILE__,__LINE__);
$sum_webcam_month_all = p4c_result($rs_count_webcam,0);
$sum_webcam = $sum_webcam_month_all/100;
$webcam_eur = number_format($sum_webcam,'2',',','.');

$gesamt = number_format(($sum_movies + $sum_albums + $sum_messenger + $sum_webcam),'2',',','.');


$site .= ' 
<style>
    .table_home_stats {
        width: 200px;
    }
    
    .table_home_stats tr td:nth-child(2) {
        padding-left:20px;
        text-align:right;
    }
    
    .table_home_stats tr td {
        padding:2px;
    }
</style>

<div class="ui-widget-header" style="padding:5px 10px;">Deine Ums&auml;tze als Darsteller im '.$month_name_DE.'</div>
<div class="ui-widget-content" style="padding:14px; border-top:none;">
    <table class="table_home_stats">
        <tr><td>Videos</td><td>'.$video_eur.' EUR</td></tr>
        <tr><td>Fotoalben</td><td>'.$albums_eur.' EUR</td></tr>
        <tr><td>Messenger</td><td>'.$messenger_eur.' EUR</td></tr>
        <tr><td>Webcam</td><td>'.$webcam_eur.' EUR</td></tr>
        <tr><td style="border-top:1px dotted;"><b>Gesamt</b></td><td style="border-top:1px dotted;"><b><a href="'.MCP_URL.'/Statistics">'.$gesamt.' EUR</a></b></td></tr>
    <table>
</div>';
