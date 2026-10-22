<?php

if (!defined('SAFE_INC'))
	die ("Hacking attempt...");

/** 
 *  Aufruf wie folgt:
 *  $class_errorlog->log('Meine Fehlermeldung',__FILE__,__LINE__);
 * **/

class p4c_errorlog {
    public $date;
    public function __construct() {
        $this->date = date("d-M-Y H:i:s");
    }
   
    function log($error, $file=__FILE__, $line=__LINE__) {
        $this->date = date("d-M-Y H:i:s T");
        $context = '';
        if (PHP_SAPI === 'cli') {
            $script = isset($_SERVER['argv'][0]) ? basename((string)$_SERVER['argv'][0]) : basename((string)$file);
            $context = '[CLI: ' . $script . '] ';
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $uri = isset($_SERVER['REQUEST_URI']) ? ' ' . $_SERVER['REQUEST_URI'] : '';
            $context = '[' . $_SERVER['HTTP_HOST'] . $uri . '] ';
        }
        $logDir = SOURCEDIR . '/log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        error_log("[" . $this->date . "] " . $context . $error . " in " . $file . " on line " . $line . "\n", 3, $logDir . '/.errorlog');
        return "Sorry! Es ist ein Fehler aufgetreten.";
    }   
}

?>