<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");
        
/**
 *  MySQLi
 **/
class p4c_mysqli {
    
    private $connect;
    
    public function __construct($dbhost, $dbuser, $dbpass, $dbname) {
        $this->error_log = new p4c_errorlog();
       
        $this->connect = @new mysqli($dbhost, $dbuser, $dbpass, $dbname);
        
        // $connect_error was broken until PHP 5.2.9 and 5.3.0.
        if ($this->connect->connect_error) {
            $this->error_log->log('Kann keine Verbindung zur Datenbank herstellen! ('.$this->connect->connect_errno.') '.$this->connect->connect_error);
            die('Wartungsarbeiten.');
            #die('Kann keine Verbindung zur Datenbank herstellen! ('.$this->connect->connect_errno.') '.$this->connect->connect_error);
        }
        
        // Use this instead of $connect_error if you need to ensure
        // compatibility with PHP versions prior to 5.2.9 and 5.3.0.
        if (mysqli_connect_error()) {
            $this->error_log->log('Kann keine Verbindung zur Datenbank herstellen! (('.mysqli_connect_errno().') '.mysqli_connect_error());
            #die('Kann keine Verbindung zur Datenbank herstellen!');
            die('Wartungsarbeiten.');
        }
    } 
    
    public function change_connect($db='') {
        if ($db == 'amoredea') {
            $this->connect->change_user(DB_USER_AMOREDEA, DB_PASS_AMOREDEA, DB_NAME_AMOREDEA);    
        } else {
            $this->connect->change_user(DB_USER, DB_PASS, DB_NAME);    
        }
    }
    
    public function error($result) {
        if ($this->connect->connect_error) {
            return '('.$this->connect->connect_errno.') '.$this->connect->connect_error;
        } else {
            return '('.mysqli_connect_errno().') '.mysqli_connect_error();
        } 
    }
    
    public function query($query, $file=__FILE__, $line=__LINE__) {
        $rs = $this->connect->query($query) or die ($this->error_log->log("MYSQL-ERROR: ".$this->connect->error."\nMYSQL-QUERY: ".$query, $file, $line));
        return $rs;
        
    }
    
    public function fetch_object($result) {
        return $result->fetch_object();
    }

    public function fetch_array($result) {
        return $result->fetch_array();
    }
    
    public function fetch_row ($result) {
        return $result->fetch_row();
    }

    public function num_rows($result, $file=__FILE__, $line=__LINE__) {
        
        if (isset($result->num_rows)) {
            return $result->num_rows;
        } else {
            $this->error_log->log('MYSQL-ERROR: '.$this->connect->error, $file, $line);
        }
    }
    
    public function result($result, $row, $field=0) { 
        $result->data_seek($row); 
        $datarow = $result->fetch_array(); 
        return $datarow[$field]; 
    }
    
    public function real_escape_string($result) {
        return $this->connect->real_escape_string($result);
    }
    
    public function affected_rows() {
        return $this->connect->affected_rows;
    }

    public function insert_id() {
        return $this->connect->insert_id;
    }
    
    public function close() {
        return $this->connect->close();
    }
}


?>