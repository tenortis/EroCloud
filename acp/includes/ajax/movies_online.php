<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 */

define('SAFE_INC', 1);

include_once("../../../config.inc.php");
include_once(ACP_DIR."/common.inc.php");
include_once(SOURCEDIR."/includes/ai_compliance_core.inc.php");

if (is_logged_in('acp') === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'Nicht autorisiert.'));
    exit;
}

@session_write_close();

$flag_dir  = (defined('TEMP_DIR') && is_dir(TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
$aic_flag  = $flag_dir . '/.ec_aic_table_v1.flag';
if (!@is_file($aic_flag)) {
    aic_ensure_table();
    @file_put_contents($aic_flag, date('Y-m-d H:i:s'));
}

// Sicherstellen, dass movies.file_id indiziert ist (verhindert Full-Table-Scan beim LEFT JOIN mit movies_online)
$idx_flag = $flag_dir . '/.ec_movies_file_id_idx_v1.flag';
if (!@is_file($idx_flag)) {
    $has_file_id_idx = false;
    $rs_idx = p4c_query("SHOW INDEX FROM `movies`;", __FILE__, __LINE__);
    if ($rs_idx && p4c_num_rows($rs_idx) > 0) {
        while ($idx_row = p4c_fetch_object($rs_idx)) {
            if ((string)($idx_row->Column_name ?? '') === 'file_id' && abs((int)($idx_row->Seq_in_index ?? 0)) === 1) {
                $has_file_id_idx = true;
                break;
            }
        }
    }
    if (!$has_file_id_idx) {
        p4c_query("ALTER TABLE `movies` ADD INDEX `idx_ec_file_id` (`file_id`);", __FILE__, __LINE__);
    }
    @file_put_contents($idx_flag, date('Y-m-d H:i:s'));
}

function safe_utf8($str) {
    if ($str === null || $str === false) return '';
    if (!mb_check_encoding($str, 'UTF-8')) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
    }
    return $str;
}

$sEcho = isset($_GET['sEcho']) ? intval($_GET['sEcho']) : (isset($_GET['draw']) ? intval($_GET['draw']) : 0);

$rs_movies = p4c_query("SELECT
        mo.`id`, mo.`file_id`, mo.`quality`, mo.`merchant_id`, mo.`actor_id`, mo.`checksum`,
        mo.`title`, mo.`description`, mo.`online_at`, mo.`movie_language`, mo.`movie_checked`,
        mo.`status`, mo.`category_master`,
        a.`username` AS `actor_username`,
        ai.`subtitle_status`, ai.`text_check_status`, ai.`text_compliance`, ai.`text_risk_score`, ai.`text_summary`
    FROM `movies_online` mo
    LEFT JOIN `actors` a ON a.`id` = mo.`actor_id` AND a.`merchant_id` = mo.`merchant_id`
    LEFT JOIN `movies` m ON m.`file_id` = mo.`file_id`
    LEFT JOIN `movies_ai_analysis` ai ON ai.`movie_id` = m.`id`
    ORDER BY mo.`id` DESC", __FILE__, __LINE__);

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

        if (!empty($movie_ary->actor_username)) {
            $actor_name = safe_utf8($movie_ary->actor_username);
            $actor = '<a href="'.ACP_URL.'/Actor/'.abs((int)$movie_ary->actor_id).'" target="_blank">'.$actor_name.'</a>';
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

        $bram_badge = aic_render_bram_score_badge($movie_ary->text_check_status, $movie_ary->text_compliance, $movie_ary->text_risk_score, $movie_ary->text_summary, $movie_ary->subtitle_status);

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
        $row[] = $bram_badge;
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