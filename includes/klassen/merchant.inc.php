<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


// $merchant = new Merchant($mysql,$merchant_id);

class Merchant {
    
    public $mysql;
    public $merchant_id;
    
    public function __construct($mysql, $merchant_id='') {
        $this->mysql = $mysql;
        
        $this->merchant_id = '';
        $this->partner_id = '';
        
        if (isset($_SESSION['merchant_id'])) {
            $this->merchant_id = $_SESSION['merchant_id'];
        } else {
            $this->partner_id = $merchant_id;
        }
    }
    
    
    private function sql_get_merchant($var,$decrypt='') {
        
        if ($decrypt=='aes_decrypt') {
            $select = "AES_DECRYPT(`".p4c_escape_string($var)."`, '".AES_KEY."') AS `".p4c_escape_string($var)."`";  
        } else {
            $select = "`".p4c_escape_string($var)."`";
        }
        
        if ($this->partner_id != '' AND strlen($this->partner_id) == 10) {
            #$rs_member = $this->mysql->query("SELECT ".$select." FROM `merchants` WHERE `id`='".abs($this->partner_id)."' OR `partner_id`='".p4c_escape_string($this->partner_id)."' LIMIT 1;", __FILE__, __LINE__);
            $rs_member = $this->mysql->query("SELECT ".$select." FROM `merchants` WHERE `partner_id`='".p4c_escape_string($this->partner_id)."' LIMIT 1;", __FILE__, __LINE__);
        } else if ($this->partner_id != '' AND strlen($this->partner_id) < 10) {
            $rs_member = $this->mysql->query("SELECT ".$select." FROM `merchants` WHERE `id`='".p4c_escape_string($this->partner_id)."' LIMIT 1;", __FILE__, __LINE__);
        } else {
                $rs_member = $this->mysql->query("SELECT ".$select." FROM `merchants` WHERE `id`='".abs($this->merchant_id)."' LIMIT 1;", __FILE__, __LINE__);
        }

        if ($this->mysql->num_rows($rs_member) > 0) {
            $member_ary = $this->mysql->fetch_object($rs_member);
            return $member_ary->$var;
        }
		
    } 
    
    private function sql_get_country($var) {
        if (trim($var) != '') {
            $rs_countries = $this->mysql->query("SELECT * FROM `countries` WHERE `alpha2`='".p4c_escape_string($var)."' LIMIT 1;", __FILE__, __LINE__);
            if ($this->mysql->num_rows($rs_countries) > 0) {
                $country_ary = $this->mysql->fetch_object($rs_countries);
                return $country_ary->country;
            }
        }
    } 
   

    /**
     * Benutzerdaten
     * **/
    public function id(){
        return $this -> sql_get_merchant('id');
    }
    
    public function api_key($decrypt=''){
        return $this -> sql_get_merchant('api_key',$decrypt);
    }

    public function partner_id(){
        return $this -> sql_get_merchant('partner_id');
    }
     
    public function username($decrypt=''){
        return $this -> sql_get_merchant('username',$decrypt);
    }
    
    public function password(){
        return $this -> sql_get_merchant('password');
    }
    
    public function birthday($decrypt=''){
        return $this -> sql_get_merchant('birthday',$decrypt);
    }

    /**
     *  Kontaktdsaten
     * **/
    public function email($decrypt=''){
        return $this -> sql_get_merchant('email',$decrypt);
    }
    
    
    /**
     *  Ansprechpartner
     * **/
    
    public function salutation(){
        return $this -> sql_get_merchant('salutation');
    }

    public function name($decrypt=''){
        return $this -> sql_get_merchant('firstname',$decrypt)." ".$this -> sql_get_merchant('surname',$decrypt);
    }
    
    public function firstname($decrypt=''){
        return $this -> sql_get_merchant('firstname',$decrypt);
    }
    
    public function surname($decrypt=''){
        return $this -> sql_get_merchant('surname',$decrypt);
    }
    
    
    /**
     *  Sonstiges
     * **/

    public function last_ip($decrypt=''){
        return $this -> sql_get_merchant('last_ip',$decrypt);
    }
    
    public function last_ip_datetime(){
        return $this -> sql_get_merchant('last_ip_datetime');
    }  
    
    public function minimum_number_actor_profiles(){
        return $this -> sql_get_merchant('minimum_number_actor_profiles');
    }  
    
    public function accept_content_rules(){
        return $this -> sql_get_merchant('accept_content_rules');
    }  
    
    
    
}


?>