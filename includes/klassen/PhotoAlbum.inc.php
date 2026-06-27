<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


// $album = new PhotoAlbum($mysql,$album_id);

class PhotoAlbum {
    
    public $mysql;
    public $album_id;

        
    public function __construct($mysql, $album_id='') {
        $this->mysql = $mysql;
        $this->album_id = $album_id;
    }   
    
    private function sql_get_var($var) {
        
        if (is_logged_in('mcp') === true OR is_logged_in('acp') === true) {
            if (is_logged_in('mcp') === true) {
                $rs_albums = $this->mysql->query("SELECT `".p4c_escape_string($var)."` FROM `photo_albums` WHERE `id`='".abs($this->album_id)."' AND `merchant_id`='".abs($_SESSION['merchant_id'])."' LIMIT 1;", __FILE__, __LINE__);
            } else if (is_logged_in('acp') === true) {
                $rs_albums = $this->mysql->query("SELECT `".p4c_escape_string($var)."` FROM `photo_albums` WHERE `id`='".abs($this->album_id)."' LIMIT 1;", __FILE__, __LINE__);
            }
            
            if ($this->mysql->num_rows($rs_albums) > 0) {
                $album_ary = $this->mysql->fetch_object($rs_albums);
                return $album_ary->$var;
            }
        }
    } 

    public function field($var=''){
        return $this -> sql_get_var($var);
    }
    
}

class PhotoAlbumOnline {
    
    public $mysql;
    public $album_id;

        
    public function __construct($mysql, $album_id='') {
        $this->mysql = $mysql;
        $this->album_id = $album_id;
    }   
    
    private function sql_get_var($var) {
        $rs_albums = $this->mysql->query("SELECT `".p4c_escape_string($var)."` FROM `photo_albums_online` WHERE `id`='".abs($this->album_id)."' LIMIT 1;", __FILE__, __LINE__);

        if ($this->mysql->num_rows($rs_albums) > 0) {
            $album_ary = $this->mysql->fetch_object($rs_albums);
            return $album_ary->$var;
        }
    } 

    public function field($var=''){
        return $this -> sql_get_var($var);
    }
    
}


?>