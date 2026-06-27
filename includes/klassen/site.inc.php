<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


// $site = new Site($mysql,[$site_id OR $domain]);

class Site {
    
    public $mysql;
    public $site_id;
    
    public function __construct($mysql, $site_id='') {
        $this->mysql = $mysql;
        $this->site_id = $site_id;
    }
    
    public function get_var($var) {
        
        if (strlen($this->site_id) == 5 AND substr($this->site_id,0)) {
            $rs_site = $this->mysql->query("SELECT `". p4c_escape_string($var)."` FROM `sites` WHERE `p4c_shop_id`='".p4c_escape_string($this->site_id)."' LIMIT 1;", __FILE__, __LINE__);
        } else if (strlen($this->site_id) > 5) {
            $rs_site = $this->mysql->query("SELECT `". p4c_escape_string($var)."` FROM `sites` WHERE `domain`='".p4c_escape_string($this->site_id)."' LIMIT 1;", __FILE__, __LINE__);
        } else {
            $rs_site = $this->mysql->query("SELECT `". p4c_escape_string($var)."` FROM `sites` WHERE `id`='".p4c_escape_string($this->site_id)."' LIMIT 1;", __FILE__, __LINE__);
        }
        
        if ($this->mysql->num_rows($rs_site) > 0) {
            $site_obj = $this->mysql->fetch_object($rs_site);
            return $site_obj->$var;
        }
    }     
}


?>