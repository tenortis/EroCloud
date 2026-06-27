<?php

if (!defined('SAFE_INC'))
	die ("Hacking attempt...");

/** 
 *  Aufruf wie folgt:
 *  $class_errorlog->log('Meine Fehlermeldung',__FILE__,__LINE__);
 * **/

class p4c_errorlog {
    public function __construct() {
        $this->date = date("d-M-Y H:i:s");
    }
   
    function log($error,$file,$line) {
        error_log("[".$this->date."] ".$error." in ".$file." on line ".$line."\n", 3,  SOURCEDIR.'/log/.errorlog');
        return "Sorry! Es ist ein Fehler aufgetreten.";
    }   
}

?>