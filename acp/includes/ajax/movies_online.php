<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 */

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'Nicht autorisiert.'));
    exit;
}

function safe_utf8($str) {
    if ($str === null || $str === false) return '';
    if (!mb_check_encoding($str, 'UTF-8')) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
    }
    return $str;
}

$sEcho = isset($_GET['sEcho']) ? intval($_GET['sEcho']) : (isset($_GET['draw']) ? intval($_GET['draw']) : 0);

$rs_movies = p4c_query("SELECT `id`, `file_id`, `quality`, `merchant_id`, `actor_id`, `checksum`, `title`, `description`, `online_at`, `movie_language`, `movie_checked`, `status`, `category_master` FROM `movies_online` ORDER BY `id` DESC", __FILE__, __LINE__);

$output = array(
    "sEcho" => $sEcho,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "aaData" => array()
);

if (p4c_num_rows($rs_movies) > 0) {
    $total_count = p4c_num_rows($rs_movies);
    $output["iTotalRecords"] = $total_count;
    $output["iTotalDisplayRecords"] = $total_count;

    $select_quality_ary = array(
        'sd' => 'SD',
        'hd' => 'HD',
        'fhd' => 'Full HD',
        '2k' => '2K',
        '4k' => '4K',
        '6k' => '6K'
    );

    $movie_language_ary = array(
        'de' => 'Deutsch',
        'en' => 'Englisch',
        'fr' => 'Französisch',
        'es' => 'Spanisch',
        'nl' => 'Niederländisch',
        'ru' => 'Russisch',
        'pl' => 'Polnisch'
    );

    while ($movie_ary = p4c_fetch_object($rs_movies)) {
        $title = safe_utf8($movie_ary->title);
        $description = safe_utf8($movie_ary->description);
        
        $quality = isset($select_quality_ary[$movie_ary->quality]) ? $select_quality_ary[$movie_ary->quality] : '';

        $rs_actors = p4c_query("SELECT `username` FROM `actors` WHERE
            `id`='".abs($movie_ary->actor_id)."' AND
            `merchant_id` = '".abs($movie_ary->merchant_id)."';",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_actors) > 0) {
            $actor_name = safe_utf8(p4c_result($rs_actors, 0));
            $actor = '<a href="'.ACP_URL.'/Actor/'.$movie_ary->actor_id.'" target="_blank">'.$actor_name.'</a>';
        } else {
            $actor = '-';
        }
            
        if ($movie_ary->status == 'active') {
            $status = '<img src="'.ACP_URL.'/images/icons/on.png" alt="" title="aktiv" class="status" />';
        } else if ($movie_ary->status == 'blocked') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gesperrt" class="status" />';
        } else if ($movie_ary->status == 'deleted') {
            $status = '<img src="'.ACP_URL.'/images/icons/off.png" alt="" title="gelöscht" class="status" />';
        } else {
            $status = '-';
        }
        
        if ($movie_ary->category_master == 'porn') {
            $category_master = 'Porno';
        } else if ($movie_ary->category_master == 'fetish') {
            $category_master = 'Fetisch';
        } else {
            $category_master = '-';
        }
        
        $lang = isset($movie_language_ary[$movie_ary->movie_language]) ? $movie_language_ary[$movie_ary->movie_language] : $movie_ary->movie_language;

        $row = array();
        $row[] = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$movie_ary->id.'">'.$movie_ary->id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$movie_ary->merchant_id.'">'.$movie_ary->merchant_id.'</a>';
        $row[] = $status;
        $row[] = $actor;
        $row[] = safe_utf8($quality);
        $row[] = safe_utf8($movie_ary->movie_checked);
        $row[] = safe_utf8($movie_ary->online_at);
        $row[] = safe_utf8($lang);
        $row[] = safe_utf8($category_master);
        $row[] = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$movie_ary->id.'">'.$title.'</a>';
        $row[] = $description;
   
        $output['aaData'][] = $row;
    }
}

p4c_close(DB_HOST);

while (ob_get_level() > 0) { @ob_end_clean(); }

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output, JSON_UNESCAPED_UNICODE);
exit;

?>