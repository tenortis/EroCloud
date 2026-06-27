<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

// $member = new Member($member_id);
class Member {
    
    function __construct($member_id, $is_remote_member_id=true) {
        global $mysql, $class_errorlog;
        $this->class_errorlog = $class_errorlog;
        
        $this->member_id = $member_id;
        $this->is_remote_member_id = $is_remote_member_id;
    }
    
    private function sql_get_member($var,$decrypt='') {
        
        if ($decrypt=='aes_decrypt') {
            $select = "AES_DECRYPT(`".p4c_escape_string($var)."`, '".AES_KEY."') AS `".p4c_escape_string($var)."`";  
        } else {
            $select = "`".p4c_escape_string($var)."`";
        }
        
        if ($this->is_remote_member_id === true) {
            $sql_id = "`remote_member_id`='".p4c_escape_string($this->member_id)."'";
        } else {
            $sql_id = "`id`='".abs($this->member_id)."'";
        }

        $rs_member = p4c_query("SELECT ".$select." FROM `members` WHERE ".$sql_id." LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_member) > 0) {
            $member_ary = p4c_fetch_object($rs_member);
            return $member_ary->$var;
        }
    }
    
    
    
    public function id(){
        return $this -> sql_get_member('id');
    }

    public function remote_member_id(){
        return $this -> sql_get_member('remote_member_id');
    }
     
    public function p4c_shop_id(){
        return $this -> sql_get_member('p4c_shop_id');
    }
    
    public function username(){
        return $this -> sql_get_member('username');
    }

    public function amount_coins(){
        return $this -> sql_get_member('amount_coins');
    }
  
    public function birthday(){
        return $this -> sql_get_member('birthday');
    }

    public function email(){
        return $this -> sql_get_member('email');
    }
    
    public function online_device(){
        return $this -> sql_get_member('online_device');
    }

    public function avatar_url(){
        return $this -> sql_get_member('avatar_url');
    }
    
    public function lastonline(){
        return $this -> sql_get_member('lastonline');
    }  
    
    public function locked(){
        return $this -> sql_get_member('locked');
    }  
    
}
