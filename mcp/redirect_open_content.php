<?php


/**
 * Weiterleitung zu Amoredea einen Eintrag in der Timeline 
 */


define('SAFE_INC', 1);

include_once("../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_GET['content_id']) OR !isset($_GET['month'])) {
    header('Location: '.URL.'/Comments');
    exit;
}

$month = abs($_GET['month']);
$content_id = abs($_GET['content_id']);
$merchant_id = abs($_SESSION['merchant_id']);

// DB von amoredea auswählen
$mysql -> change_connect("amoredea");

$rs_comments = p4c_query("SELECT * FROM `timeline_comments_".$month."` WHERE `content_id`='".abs($content_id)."' AND `merchant_id`='".abs($merchant_id)."' LIMIT 1;", __FILE__, __LINE__);
if (p4c_num_rows($rs_comments) == 0) {
    header('Location: '.URL.'/Comments');
    exit;
}

$comment_obj = p4c_fetch_object($rs_comments);
$actor_id = $comment_obj->actor_id;


// DB von EroCloud auswählen
$mysql -> change_connect("");

include_once(SOURCEDIR.'/includes/klassen/actor.inc.php');
$actor = new Actor($actor_id);

if ($actor->get("id") == '') {
    header('Location: '.URL.'/Comments');
    exit;    
}

header('Location: '.amoredea_URL.'/'.$actor->get('username').'/?content_id='.$content_id.'_'.$month);
exit;
