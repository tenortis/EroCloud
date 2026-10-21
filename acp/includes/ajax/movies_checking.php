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

aic_ensure_table();

function safe_utf8($str) {
    if ($str === null || $str === false) return '';
    if (!mb_check_encoding($str, 'UTF-8')) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
    }
    return $str;
}

$sEcho = isset($_GET['sEcho']) ? intval($_GET['sEcho']) : (isset($_GET['draw']) ? intval($_GET['draw']) : 0);

$rs_movies = p4c_query("SELECT m.`id`, m.`file_id`, m.`merchant_id`, m.`actor_id`, m.`checksum`, m.`title`, m.`description`, m.`meta_title`, m.`meta_description`, m.`online_at`, m.`status`, m.`movie_language`, m.`category_master`, ai.`subtitle_status`, ai.`spoken_transcript`, ai.`spoken_segments_json`, ai.`text_check_status`, ai.`text_compliance`, ai.`text_risk_score`, ai.`text_summary` FROM `movies` m LEFT JOIN `movies_ai_analysis` ai ON ai.`movie_id` = m.`id` WHERE m.`movie_checked`='000-00-00 00:00:00' AND m.`released`='1' AND m.`convert_status` > '1' ORDER BY m.`id` DESC", __FILE__, __LINE__);

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
        
        $rs_actors = p4c_query("SELECT `username` FROM `actors` WHERE
            `id`='".abs((int)$movie_ary->actor_id)."' AND
            `merchant_id` = '".abs((int)$movie_ary->merchant_id)."';",__FILE__,__LINE__);
        
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

        // Falls ein neuer Film in der Prüf-Warteschlange noch keinen BRAM-Check in movies_ai_analysis besitzt, sofort bewerten & speichern
        $check_status = (int)$movie_ary->text_check_status;
        $compliance   = (string)$movie_ary->text_compliance;
        $risk_score   = (int)$movie_ary->text_risk_score;
        $summary_de   = (string)$movie_ary->text_summary;
        $sub_status   = (int)$movie_ary->subtitle_status;

        if ($check_status !== 2) {
            $segs = array();
            if (!empty($movie_ary->spoken_segments_json)) {
                $dec = @json_decode($movie_ary->spoken_segments_json, true);
                if (is_array($dec)) {
                    $segs = $dec;
                }
            }
            $spk_txt = !empty($movie_ary->spoken_transcript) ? (string)$movie_ary->spoken_transcript : '';
            $rep = aic_analyze_texts(array(
                'title'            => $movie_ary->title,
                'description'      => $movie_ary->description,
                'meta_title'       => isset($movie_ary->meta_title) ? $movie_ary->meta_title : '',
                'meta_description' => isset($movie_ary->meta_description) ? $movie_ary->meta_description : ''
            ), $segs, $spk_txt, array('llm_ready' => false));

            $now       = date('Y-m-d H:i:s');
            $viol_json = json_encode($rep['violations'], JSON_UNESCAPED_UNICODE);
            $sub_file  = 'sub_' . abs((int)$movie_ary->id) . '_' . $movie_ary->file_id . '.vtt';

            p4c_query("INSERT INTO `movies_ai_analysis`
                (`movie_id`, `merchant_id`, `subtitle_filename`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
                VALUES (
                    '" . abs((int)$movie_ary->id) . "',
                    '" . abs((int)$movie_ary->merchant_id) . "',
                    '" . p4c_escape_string($sub_file) . "',
                    '2',
                    '" . p4c_escape_string($rep['compliance_status']) . "',
                    '" . abs((int)$rep['risk_score']) . "',
                    '" . p4c_escape_string($rep['engine_used']) . "',
                    '" . p4c_escape_string($rep['summary_de']) . "',
                    '" . p4c_escape_string($viol_json) . "',
                    '" . p4c_escape_string($rep['duration_sec']) . "',
                    '" . $now . "',
                    '" . $now . "'
                )
                ON DUPLICATE KEY UPDATE
                    `text_check_status`    = '2',
                    `text_compliance`      = '" . p4c_escape_string($rep['compliance_status']) . "',
                    `text_risk_score`      = '" . abs((int)$rep['risk_score']) . "',
                    `text_engine_used`     = '" . p4c_escape_string($rep['engine_used']) . "',
                    `text_summary`         = '" . p4c_escape_string($rep['summary_de']) . "',
                    `text_violations_json` = '" . p4c_escape_string($viol_json) . "',
                    `text_duration_sec`    = '" . p4c_escape_string($rep['duration_sec']) . "',
                    `updated_at`           = '" . $now . "';", __FILE__, __LINE__);

            $check_status = 2;
            $compliance   = $rep['compliance_status'];
            $risk_score   = (int)$rep['risk_score'];
            $summary_de   = $rep['summary_de'];
        }

        $bram_badge = aic_render_bram_score_badge($check_status, $compliance, $risk_score, $summary_de, $sub_status);
        
        $row = array();
        $row[] = '<a href="'.ACP_URL.'/Film-pruefen/'.$movie_ary->id.'">'.$movie_ary->id.'</a>';
        $row[] = '<a href="'.ACP_URL.'/Haendler/'.$movie_ary->merchant_id.'">'.$movie_ary->merchant_id.'</a>';
        $row[] = $status;
        $row[] = '<div><img src="'.API_URL.'/PlayerPoster/FSK16/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div><div><img src="'.API_URL.'/PlayerPoster/FSK18/'.$movie_ary->file_id.'?w=100&cs='.$movie_ary->checksum.'" /></div>';
        $row[] = $actor;
        $row[] = safe_utf8($movie_ary->online_at);
        $row[] = safe_utf8($lang);
        $row[] = safe_utf8($category_master);
        $row[] = $bram_badge;
        $row[] = '<a href="'.ACP_URL.'/Film-pruefen/'.$movie_ary->id.'">'.$title.'</a>';
   
        $output['aaData'][] = $row;
    }
}

p4c_close(DB_HOST);

// Purge any output buffers to guarantee clean JSON response
while (ob_get_level() > 0) { @ob_end_clean(); }

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output, JSON_UNESCAPED_UNICODE);
exit;

?>