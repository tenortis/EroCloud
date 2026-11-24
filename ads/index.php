<?php

define('SAFE_INC', 1);

$no_session = true;
$_SESSION = array();

include_once("../config.inc.php");
include_once(API_DIR."/common.inc.php");

header('Location: '.MCP_URL.'/Startseite#/webmaster-ads');

p4c_close(DB_HOST);
exit;
