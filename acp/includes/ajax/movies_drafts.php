<?php

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");

if (is_logged_in('acp') === false) {
    exit;   
}

function private_substr($str, $len=20) {
    if (strlen($str) > $len) {
        $str = substr($str,0,($len-5)).'[...]';
    }
    
    if(mb_detect_encoding($str) != 'UTF-8') {$str = utf8_encode($str);}
    return $str;    
}

if (!function_exists('cleanup_get_folder_size')) {
    function cleanup_get_folder_size($folderPath) {
        $size = 0;
        if (!is_dir($folderPath)) {
            return 0;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
}

$rs_movies = p4c_query("
    SELECT `id`, `file_id`, `merchant_id`, `actor_id`, `checksum`, `title`, `online_at`, `status`, `convert_status`, `movie_language`, `category_master`, `storage_location` 
    FROM `movies` 
    WHERE `released` = '0' AND `status` != 'deleted' 
    ORDER BY `id` DESC;
", __FILE__, __LINE__);

if (p4c_num_rows($rs_movies) > 0) {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => p4c_num_rows($rs_movies),
    	"iTotalDisplayRecords" => 25,
    	"aaData" => array()
    );
    
    $movie_language_ary = array(
        'de' => 'Deutsch',
        'en' => 'Englisch',
        'fr' => 'Franz&ouml;sisch',
        'es' => 'Spanisch',
        'nl' => 'Niederl&auml;ndisch',
        'ru' => 'Russisch',
        'pl' => 'Polnisch'
    );
    
    while($movie_ary = p4c_fetch_object($rs_movies)) {
        $title = $movie_ary->title;
        if(mb_detect_encoding($movie_ary->title, 'UTF-8', false)) {
            $title = utf8_encode($movie_ary->title);
        }
        
        $rs_actors = p4c_query("SELECT `username` FROM `actors` WHERE
            `id`='".abs($movie_ary->actor_id)."' AND
            `merchant_id` = '".abs($movie_ary->merchant_id)."';",__FILE__,__LINE__);
        
        if (p4c_num_rows($rs_actors) > 0) {
            $actor = '<a href="'.ACP_URL.'/Actor/'.$movie_ary->actor_id.'" target="_blank">'. utf8_decode(p4c_result($rs_actors,0)).'</a>';
        } else {
            $actor = '-';
        }
        
        // Status & Konvertierungs-Badges
        if ($movie_ary->convert_status == 0) {
            $status = '<span style="display:inline-block; background-color: #e2f0d9; border: 1px solid #70ad47; color: #385723; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;" title="In Konvertierungs-Warteschlange">Warteschlange</span>';
            $preview = '<span style="color:#888; font-style:italic;">Keine Vorschau</span>';
        } else if ($movie_ary->convert_status == 1) {
            $status = '<span style="display:inline-block; background-color: #fff2cc; border: 1px solid #ffc000; color: #7f6000; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;" title="Video wird aktuell konvertiert">Konvertiert...</span>';
            $preview = '<span style="color:#888; font-style:italic;">In Arbeit</span>';
        } else if ($movie_ary->convert_status == 2) {
            $status = '<span style="display:inline-block; background-color: #d9e1f2; border: 1px solid #4472c4; color: #1f3864; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;" title="Video erfolgreich konvertiert">Bereit</span>';
            $preview = '<div><img src="'.API_URL.'/PlayerPoster/FSK16/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div><div><img src="'.API_URL.'/PlayerPoster/FSK18/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div>';
        } else {
            $status = '<span style="display:inline-block; background-color: #fce4d6; border: 1px solid #c65911; color: #833c0c; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;" title="Konvertierung fehlgeschlagen">Fehler</span>';
            $preview = '<span style="color:#c65911; font-weight:bold;">Fehler</span>';
        }
        
        if ($movie_ary->category_master == 'porn') {
            $category_master = 'Porno';
        } else if ($movie_ary->category_master == 'fetish') {
            $category_master = 'Fetisch';
        } else {
            $category_master = '-';
        }
        
        // Geplant für
        $online_ts = ($movie_ary->online_at != '0000-00-00 00:00:00' && $movie_ary->online_at != '') ? strtotime($movie_ary->online_at) : 0;
        $online_date_cell = '<span style="display:none;">'.$online_ts.'</span>' . (($online_ts > 0) ? date("d.m.Y H:i:s", $online_ts) : '-');
        
        // Ordnergröße berechnen
        $folder = MOVIES_PATH.'/'.$movie_ary->storage_location.'/'.$movie_ary->merchant_id.'/'.$movie_ary->id;
        $size_bytes = cleanup_get_folder_size($folder);
        if ($size_bytes >= 1073741824) {
            $size_text = number_format($size_bytes / 1073741824, 2) . ' GB';
        } elseif ($size_bytes >= 1048576) {
            $size_text = number_format($size_bytes / 1048576, 2) . ' MB';
        } elseif ($size_bytes > 0) {
            $size_text = number_format($size_bytes / 1024, 2) . ' KB';
        } else {
            $size_text = '-';
        }
        $size_cell = '<span style="display:none;">'.$size_bytes.'</span>' . $size_text;
        
    	$row = array();
        
        $row[] = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$movie_ary->id.'">'.$movie_ary->id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$movie_ary->merchant_id.'">'.$movie_ary->merchant_id.'</a>';
        $row[] = $status;
        $row[] = $preview;
        $row[] = $actor;
        $row[] = $online_date_cell;
        $row[] = $size_cell;
        $row[] = isset($movie_language_ary[$movie_ary->movie_language]) ? $movie_language_ary[$movie_ary->movie_language] : '-';
        $row[] = $category_master;
        $row[] = '<a href="'.ACP_URL.'/Film-bearbeiten/'.$movie_ary->id.'"><b>'.utf8_decode($title).'</b></a>';
   
    	$output['aaData'][] = $row;
    }

} else {
    $output = array(
    	"sEcho" => 0,
    	"iTotalRecords" => 0,
    	"iTotalDisplayRecords" => 0,
    	"aaData" => 0
    );
}

echo json_encode($output);

p4c_close(DB_HOST);
?>
