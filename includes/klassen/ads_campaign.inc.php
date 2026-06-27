<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


// $campaign = new Campaign($mysql,$cid);

class Campaign {
    
    public $mysql;
    public $cid;
    
    public function __construct($mysql, $cid='') {
        $this->mysql= $mysql;
        $this->cid  = $cid;
    }
    
    public function get_var($var) {
        $rs_campaign = $this->mysql->query("SELECT `". p4c_escape_string($var)."` FROM `ads_campaigns` WHERE `campaign_id`='".p4c_escape_string($this->cid)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
        
        if ($this->mysql->num_rows($rs_campaign) > 0) {
            $campaign_obj = $this->mysql->fetch_object($rs_campaign);
            return $campaign_obj->$var;
        }
    }     
}


?>