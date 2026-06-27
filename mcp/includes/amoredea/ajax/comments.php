<?php
 
define('SAFE_INC', 1);

include_once("../../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

$merchant_id = abs($_SESSION['merchant_id']);

if (isset($_POST['checked']) AND isset($_POST['comment_id']) AND isset($_POST['month'])) {

    $comment_id = abs($_POST['comment_id']);
    $checked = abs($_POST['checked']);
    $month = abs($_POST['month']);
    
    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");

    $rs_comments = p4c_query("SELECT * FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_comments) > 0) {
        p4c_query("UPDATE `timeline_comments_".abs($month)."` SET
            `checked` = '".abs($checked)."'
        WHERE
            `id` = '".abs($comment_id)."' AND
            `merchant_id`='".abs($merchant_id)."'
        LIMIT 1;",__FILE__,__LINE__);  
    }
    
    // DB von EroCloud auswählen
    $mysql -> change_connect("");
    
    echo $checked;

    exit;
}

if (isset($_POST['delete_id'])) {
    
    $comment_id = abs($_POST['delete_id']);
    $month = abs($_POST['month']);
    
    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");
    
    $rs_comments = p4c_query("SELECT * FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_comments) > 0) {
        $comment_obj = p4c_fetch_object($rs_comments);
        
        p4c_query("DELETE FROM `timeline_comments_".abs($month)."` WHERE `id`='".abs($comment_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;",__FILE__,__LINE__);  
    
        $rs_content = p4c_query("SELECT * FROM `timeline_".abs($month)."` WHERE `id`='".abs($comment_obj->content_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_content)) {
            $content_obj = p4c_fetch_object($rs_content);
            
            $number_of_comments = $content_obj->number_of_comments - 1;
            if ($number_of_comments < 0) {$number_of_comments = 0;}        

            p4c_query("UPDATE `timeline_".abs($month)."` SET `number_of_comments`='".$number_of_comments."' WHERE `id`='".abs($comment_obj->content_id)."' LIMIT 1;",__FILE__,__LINE__);
        }
    }

    // DB von EroCloud auswählen
    $mysql -> change_connect("");

    echo "ok";
    exit;
}

$datetime1 = date_create('2018-01');
$datetime2 = date_create(date("Y-m"));

$interval = date_diff($datetime1, $datetime2);
$month_total = ($interval->format("%y") * 12) + $interval->format("%m");

$count_total_commants = 0;


for($i=0;$i<=$month_total;$i++) {
    #$month = date("Ym", strtotime("2019-09-01 -".$i." month"));
    $month = date("Ym", strtotime("-".$i." month"));

    if ($month < 201801) {
        break;
    }

    // DB von amoredea auswählen
    $mysql -> change_connect("amoredea");

    $check_tbl_exists = p4c_query("SHOW TABLES LIKE 'timeline_comments_".$month."';",__FILE__,__LINE__);
    if (p4c_num_rows($check_tbl_exists) > 0) {
        $rs_comments = p4c_query("SELECT * FROM `timeline_comments_".$month."` WHERE `merchant_id`='".abs($merchant_id)."' ORDER BY `id` DESC", __FILE__, __LINE__);
        if (p4c_num_rows($rs_comments) > 0) {
            while($comment_obj = p4c_fetch_object($rs_comments)) {
                
                if ($comment_obj->checked == 1) {
                    $status = '<i onclick="jQuery(this).checked('.$comment_obj->id.', 0, '.$month.');" title="Als ungelesen markieren" data-status="1" class="checked material-symbols-outlined">check_circle</i>';
                } else {
                    $status = '<i onclick="jQuery(this).checked('.$comment_obj->id.', 1, '.$month.');" title="Als gelesen markieren" data-status="1" class="unchecked material-symbols-outlined">check_circle</i>';
                }

                $username = trim($comment_obj->username);

                if (empty($username)) {
                    $username = '<i>UID'.$comment_obj->member_id.'</i>';
                }
                
                $row = array();
                $row[] = $status;
                $row[] = $comment_obj->content_id;
                $row[] = $username;
                $row[] = $comment_obj->date_time;
                $row[] = $comment_obj->comment;
                $row[] = '<i title="Auf Kommentar antworten" onclick="jQuery(this).reply_comment('.$comment_obj->id.', '.$month.');" class="reply_comment material-symbols-outlined">reply</i>
                          <a href="RedirectContent/'.$comment_obj->content_id.'_'.$month.'" target="show_comment" class="content_open material-symbols-outlined" title="Beitrag anzeigen">open_in_new</a>
                          <i title="Kommentar l&ouml;schen" onclick="jQuery(this).delete('.$comment_obj->id.', '.$month.');" class="delete material-symbols-outlined">delete_forever</i>';
                

                $output['aaData'][] = $row;

                $count_total_commants++;
            }
        }
    }
    
    // DB von EroCloud auswählen
    $mysql -> change_connect("");
}

if ($count_total_commants == 0) {
    $output = array(
        "sEcho" => 0,
        "iTotalRecords" => $count_total_commants,
        "iTotalDisplayRecords" => 25,
        "aaData" => array()
    );
}
/*
else {
    $output = array(
        "data"=>array()
    );
}
*/
echo json_encode($output);