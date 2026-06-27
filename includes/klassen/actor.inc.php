<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

// $actor = new Actor($actor_id);

class Actor {
    
    function __construct($actor_id) {
        global $mysql, $class_errorlog;
        $this->class_errorlog = $class_errorlog;
        
        $this->actor_id = $actor_id;
    }
    
    public function get($var,$decrypt='') {
        
        if ($decrypt=='aes_decrypt') {
            $select = "AES_DECRYPT(`".p4c_escape_string($var)."`, '".AES_KEY."') AS `".p4c_escape_string($var)."`";  
        } else {
            $select = "`".p4c_escape_string($var)."`";
        }

        $rs_actor = p4c_query("SELECT ".$select." FROM `actors` WHERE `id`='".abs($this->actor_id)."' LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_actor) > 0) {
            $actor_ary = p4c_fetch_object($rs_actor);
            return $actor_ary->$var;
        }
    }
    
}
