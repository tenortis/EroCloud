<?php

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in('mcp') === false) {
    @session_write_close();
    exit;
}

// Diese Datei wird unter anderem vom Movie-Upload aufgerufen, um die Session während des Uploads aktiv zu halten.
// Session-Lock sofort freigeben, damit laufende Datei-Uploads nicht blockiert werden.
@session_write_close();

// Garbage Collection
p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());