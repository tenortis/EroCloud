<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

$movie_id = p4c_escape_string($_GET['id']);

$rs_movie = p4c_query("SELECT * FROM `movies` WHERE `file_id`='".$movie_id."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_movie) == 0) {
    exit;   
}

$movie_ary = p4c_fetch_object($rs_movie);

$file = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id.'/'.$movie_ary->filename;

#header("Content-Type: video/mp4");
include(SOURCEDIR.'/includes/klassen/VideoStream.inc.php');        
$stream = new VideoStream($file);
$stream->start();

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>