<?php
 
define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    exit;
}

// Diese Datei wird unter Anderem aufgerufen vom Upload-Movie um den Status vom Merchant immer als eingeloggt zu behalten, so lange der Film hochlädt.


// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());