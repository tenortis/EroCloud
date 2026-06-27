<?php

if (!defined('SAFE_INC'))
	die ("Hacking attempt...");


class Settings {
	
    public $mysql;
    
    public function __construct($mysql) {
        $this->mysql = $mysql;
    }
   
    public function get_var($var) {
        $rs_member = $this->mysql->query("SELECT `value` FROM `settings` WHERE `variable`='".p4c_escape_string($var)."' LIMIT 1;") or die($this->error_log->log('MYSQL-ERROR:  '.$mysql->error(),__FILE__,__LINE__));
        if ($this->mysql->num_rows($rs_member) > 0) {
            $member_ary = $this->mysql->fetch_object($rs_member);

            return $member_ary->value;
        }
    } 
}


?>