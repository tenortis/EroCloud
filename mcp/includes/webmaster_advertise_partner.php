<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$merchant = new Merchant($mysql,$_SESSION['merchant_id']);

/*
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
*/


$site .= '
<script>
    jQuery(document).ready(function() {

    } );
</script>

<div style="min-width:800px; max-width:1000px;">
    <h1 class="h4">Darsteller werben</h1>
    
    <p>
        Mit unserem Partnerprogramm kannst du nicht nur Neukunden f&uuml;r eine Website werben, sondern auch Darsteller (neue Partner) f&uuml;r Pay4Coins.<br />
        Erz&auml;hle einer Darstellerin oder einen Produzenten von uns und teile ihr/ ihm deinen Werbelink mit. Sollte sich Dieser, als Partner bei Pay4Coins registrieren,
        erh&auml;ltst du monatlich eine Gutschrift in H&ouml;he von 3% seiner Auszahlungssumme.
    </p>
    
    <div class="info_box" style="margin-top:20px; margin-bottom:20px;">
        Wenn du den Link selber testen m&ouml;chtest, musst du dich zu erst ausloggen.
    </div>
    
    <div class="ui-widget-content" style="padding:10px; line-height:1.5;">';
        $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`!='deleted' ORDER BY `username` ASC;",__FILE__,__LINE__);
        $count_actors = p4c_num_rows($rs_actors);
        
        if ($count_actors == 0) {
            $site .= '        
            <table style="width:auto;">
                <tr>
                    <td style="padding-right:20px;">Werbelink</td>
                    <td><input style="width:500px; padding:2px 5px;" readonly type="text" value="'.Pay4Coins_URL.'/wm/'.$merchant->partner_id().'" /></td>
                </tr>
            </table>';
            
        } else if ($count_actors >= 1) {
            $site .= '
            <table style="width:auto;">
                <tr>
                    <td style="padding-right:20px;"><b>als Darsteller</b></td>
                    <td><b>Werbelink</b></td>
                </tr>
                ';
                while($actors_obj = p4c_fetch_object($rs_actors)) {
                    $site .= '
                    <tr>
                        <td style="padding:0 20px 5px 0;">'.$actors_obj->username.'</td>
                        <td style="padding-bottom:5px;"><input style="width:500px; padding:2px 5px;" readonly type="text" value="https://ad5.tv/wm/'.$actors_obj->username.'" /></td>
                    </tr>';
                }
            $site .= '
            <tr>
                <td style="padding:10px 20px 15px 0;"><b>als Partner</b></td>
                <td style="padding:10px 0 15px 0;"><input style="width:500px; padding:2px 5px;" readonly type="text" value="'.Pay4Coins_URL.'/wm/'.$merchant->partner_id().'" /></td>
            </tr></table>';
        }
    $site .= ' 
    </div>


</div>';

?>