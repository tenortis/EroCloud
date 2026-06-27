<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


// $movie = new Movie($mysql,$movie_id);

class Movie {
    
    public $mysql;
    public $movie_id;
        
    public function __construct($mysql, $movie_id='') {
        $this->mysql = $mysql;
        $this->movie_id = $movie_id;
    }   
    
    private function sql_get_var($var) {
        
        if (is_logged_in('mcp') === true OR is_logged_in('acp') === true) {
            
            // Wenn movie_id = file_id ist
            if (strlen($this->movie_id) == 32) {
                $from = "`file_id`='". p4c_escape_string($this->movie_id)."'";
            } else {
                $from = "`id`='".abs($this->movie_id)."'";
            }
            
            if (is_logged_in('mcp') === true) {
                $rs_movies = $this->mysql->query("SELECT `".p4c_escape_string($var)."` FROM `movies` WHERE ".$from." AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
            } else if (is_logged_in('acp') === true) {
                $rs_movies = $this->mysql->query("SELECT `".p4c_escape_string($var)."` FROM `movies` WHERE ".$from." LIMIT 1;", __FILE__, __LINE__);
            }
            
            if ($this->mysql->num_rows($rs_movies) > 0) {
                $movie_ary = $this->mysql->fetch_object($rs_movies);
                return $movie_ary->$var;
            }
        }
    } 

    public function field($var=''){
        return $this -> sql_get_var($var);
    }
    
}

class MovieOnline {
    
    public $mysql;
    public $movie_id;

        
    public function __construct($mysql, $movie_id='') {
        $this->mysql = $mysql;
        $this->movie_id = $movie_id;
    }   
    
    private function sql_get_var($var) {
        
        // Wenn movie_id = file_id ist
        if (strlen($this->movie_id) == 32) {
            $from = "`file_id`='".p4c_escape_string($this->movie_id)."'";
        } else {
            $from = "`id`='".abs($this->movie_id)."'";
        }
        
        $rs_movies = $this->mysql->query("SELECT `".p4c_escape_string($var)."` FROM `movies_online` WHERE ".$from." LIMIT 1;", __FILE__, __LINE__);

        if ($this->mysql->num_rows($rs_movies) > 0) {
            $movie_ary = $this->mysql->fetch_object($rs_movies);
            return $movie_ary->$var;
        }
    } 

    public function field($var=''){
        return $this -> sql_get_var($var);
    }
    
}



?>