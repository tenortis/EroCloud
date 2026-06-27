<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    die('Access not allowed!');
}

if (!isset($_POST['actor_id'])) {
    p4c_close(DB_HOST);
    exit;
}

$post_actor_id = abs(filter_input(INPUT_POST, 'actor_id', FILTER_SANITIZE_NUMBER_INT));

/**
 * Punktesystem - Für Upload von kostenpflichtigen Album
 */
include_once(SOURCEDIR.'/includes/klassen/PointsSystem.inc.php');

$points_cls = new PointsSystem;
$points_cls->group = 'webcam';
$points_cls->points_for = 'one_minute_online';
$points_cls->actor_id = $post_actor_id;
$points_cls->date = date("Y-m-d");

if ($points_cls->get_points() != false) {
    $points = $points_cls->get_points();
    $points_cls->set_points($points);
};


p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
