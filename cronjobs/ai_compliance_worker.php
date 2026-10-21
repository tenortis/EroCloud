#!/usr/bin/php
<?php

/**
 * @author      Martin Zimmermann
 * @copyright   (C) 2016 adult net applications UG
 * @email       erocms@gmail.com
 *
 * Hintergrund-Cronjob / CLI-Worker fuer die lokale KI-Compliance- & Untertitel-Pipeline (whisper.cpp + BRAM-Engine + llama.cpp).
 *
 * Aufruf-Beispiele (CLI / Crontab):
 *   Alle 2 Min.: * / 2 * * * * /usr/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php >/dev/null 2>&1
 *   Manuell:     /usr/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php --force --limit=1
 *
 * PHP 7.3 kompatibel.
 */

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir . "/config.inc.php");
include_once(MCP_DIR . "/common.inc.php");
include_once($sourcedir . "/includes/ai_compliance_core.inc.php");

@set_time_limit(1800);

// CLI-Parameter auslesen (--force, --limit=N, --mode=...)
$cli_force = false;
$cli_limit = null;
$cli_mode  = null;

if (isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--force') {
            $cli_force = true;
        } elseif (strpos($arg, '--limit=') === 0) {
            $cli_limit = max(1, min(50, (int)substr($arg, 8)));
        } elseif (strpos($arg, '--mode=') === 0) {
            $cli_mode = trim(substr($arg, 7));
        }
    }
}

aic_ensure_table();
$batch_cfg = aic_load_batch_config();

// 1. Pruefen, ob der Hintergrund-Cronjob im ACP aktiviert wurde (sofern nicht --force uebergeben wurde)
if (!$cli_force && empty($batch_cfg['cron_enabled'])) {
    echo "[AI-Worker] Cronjob ist im ACP aktuell pausiert (cron_enabled = false). Nutzen Sie --force zum manuellen Testen.\n";
    p4c_close(DB_HOST);
    exit(0);
}

// 2. Exklusives Lockfile pruefen, damit niemals zwei Whisper-Worker gleichzeitig laufen
$tmp_dir   = (defined('TEMP_DIR') && @is_dir(TEMP_DIR) && @is_writable(TEMP_DIR)) ? rtrim(TEMP_DIR, '/') : rtrim(sys_get_temp_dir(), '/');
$lock_file = $tmp_dir . '/erocloud_ai_worker.lock';
$lock_fp   = @fopen($lock_file, 'c+');

if (!$lock_fp || !@flock($lock_fp, LOCK_EX | LOCK_NB)) {
    echo "[AI-Worker] Ein anderer KI-Worker-Prozess laeuft bereits. Beende diesen Aufruf.\n";
    if ($lock_fp) {
        @fclose($lock_fp);
    }
    p4c_close(DB_HOST);
    exit(0);
}

@ftruncate($lock_fp, 0);
@fwrite($lock_fp, (string)getmypid() . ' | ' . date('Y-m-d H:i:s'));
@fflush($lock_fp);

// 3. Verwaiste "In Arbeit"-Eintraege (aelter als 30 Minuten) zuruecksetzen
$stale_cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));
p4c_query("UPDATE `movies_ai_analysis` SET `subtitle_status` = '0' WHERE `subtitle_status` = '1' AND `updated_at` < '" . $stale_cutoff . "';", __FILE__, __LINE__);

// 4. Pruefen, ob gerade eine aktive FFmpeg-Videokonvertierung (convert_movie.php) laeuft
if (!empty($batch_cfg['pause_on_ffmpeg_convert'])) {
    $rs_conv = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` = '1' AND `status` != 'deleted';", __FILE__, __LINE__);
    $cnt_conv = (int)p4c_fetch_object($rs_conv)->cnt;
    if ($cnt_conv > 0) {
        aic_update_worker_status(array(
            'state'        => 'waiting_for_ffmpeg',
            'last_run_at'  => date('Y-m-d H:i:s'),
            'last_message' => 'Warte: Es werden aktuell ' . $cnt_conv . ' Video(s) durch convert_movie.php konvertiert.'
        ));
        echo "[AI-Worker] Pausiere, da aktuell " . $cnt_conv . " Video(s) konvertiert werden (convert_status = 1).\n";
        @flock($lock_fp, LOCK_UN);
        @fclose($lock_fp);
        p4c_close(DB_HOST);
        exit(0);
    }
}

$env       = aic_check_environment();
$max_runs  = ($cli_limit !== null) ? $cli_limit : (int)$batch_cfg['max_movies_per_run'];
$task_mode = ($cli_mode !== null) ? $cli_mode : (string)$batch_cfg['task_mode'];
$order_dir = (strtoupper($batch_cfg['order_direction']) === 'ASC') ? 'ASC' : 'DESC';

$processed_count = 0;

for ($i = 0; $i < $max_runs; $i++) {
    // Naechstes offenes Video auswaehlen
    if ($task_mode === 'text_only') {
        $sql_next = "SELECT `m`.`id`, `m`.`title`
            FROM `movies` `m`
            LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
            WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
              AND (`ai`.`movie_id` IS NULL OR `ai`.`text_check_status` = '0')
            ORDER BY `m`.`id` " . $order_dir . "
            LIMIT 1;";
    } elseif ($task_mode === 'recheck_llm') {
        $sql_next = "SELECT `m`.`id`, `m`.`title`
            FROM `movies` `m`
            INNER JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
            WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
              AND `ai`.`subtitle_status` IN (2, 3)
              AND `ai`.`text_engine_used` NOT LIKE '%llama%'
              AND `ai`.`text_engine_used` NOT LIKE '%Ollama%'
            ORDER BY `m`.`id` " . $order_dir . "
            LIMIT 1;";
    } else {
        // Standard (whisper_and_text): Naechstes Video ohne Untertitel / Audio-Transkript
        $sql_next = "SELECT `m`.`id`, `m`.`title`
            FROM `movies` `m`
            LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
            WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
              AND (`ai`.`movie_id` IS NULL OR `ai`.`subtitle_status` = '0')
            ORDER BY `m`.`id` " . $order_dir . "
            LIMIT 1;";
    }

    $rs_next = p4c_query($sql_next, __FILE__, __LINE__);
    if (p4c_num_rows($rs_next) === 0) {
        aic_update_worker_status(array(
            'state'            => 'idle',
            'current_movie_id' => 0,
            'last_run_at'      => date('Y-m-d H:i:s'),
            'last_message'     => 'Alle Videos für den gewählten Modus (' . $task_mode . ') sind vollständig abgearbeitet.'
        ));
        echo "[AI-Worker] Keine weiteren offenen Videos fuer Modus '" . $task_mode . "' gefunden.\n";
        break;
    }

    $next_obj = p4c_fetch_object($rs_next);
    $mid      = (int)$next_obj->id;
    $mtitle   = aic_clean_html_text($next_obj->title);

    aic_update_worker_status(array(
        'state'            => 'running',
        'current_movie_id' => $mid,
        'last_run_at'      => date('Y-m-d H:i:s'),
        'last_message'     => 'Verarbeite aktuell Video #' . $mid . ' (' . $mtitle . ')...'
    ));

    echo "[AI-Worker] Starte Verarbeitung fuer Video #" . $mid . " (" . $mtitle . ")...\n";
    $t_run = microtime(true);

    if ($task_mode === 'text_only' || $task_mode === 'recheck_llm') {
        $details = aic_get_movie_details($mid);
        $report  = aic_analyze_texts(
            array(
                'title'            => $details['title'],
                'description'      => $details['description'],
                'meta_title'       => $details['meta_title'],
                'meta_description' => $details['meta_description']
            ),
            $details['ai']['segments'],
            $details['ai']['spoken_transcript'],
            $env
        );
        $now = date('Y-m-d H:i:s');
        $viol_json = json_encode($report['violations'], JSON_UNESCAPED_UNICODE);
        p4c_query("INSERT INTO `movies_ai_analysis`
            (`movie_id`, `merchant_id`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
            VALUES (
                '" . abs((int)$details['id']) . "',
                '" . abs((int)$details['merchant_id']) . "',
                '2',
                '" . p4c_escape_string($report['compliance_status']) . "',
                '" . abs((int)$report['risk_score']) . "',
                '" . p4c_escape_string($report['engine_used']) . "',
                '" . p4c_escape_string($report['summary_de']) . "',
                '" . p4c_escape_string($viol_json) . "',
                '" . p4c_escape_string($report['duration_sec']) . "',
                '" . $now . "',
                '" . $now . "'
            )
            ON DUPLICATE KEY UPDATE
                `text_check_status`    = '2',
                `text_compliance`      = '" . p4c_escape_string($report['compliance_status']) . "',
                `text_risk_score`      = '" . abs((int)$report['risk_score']) . "',
                `text_engine_used`     = '" . p4c_escape_string($report['engine_used']) . "',
                `text_summary`         = '" . p4c_escape_string($report['summary_de']) . "',
                `text_violations_json` = '" . p4c_escape_string($viol_json) . "',
                `text_duration_sec`    = '" . p4c_escape_string($report['duration_sec']) . "',
                `updated_at`           = '" . $now . "';", __FILE__, __LINE__);
        $dur = round(microtime(true) - $t_run, 2);
        $msg = 'Text-Check für Video #' . $mid . ' abgeschlossen (' . strtoupper($report['compliance_status']) . ', ' . $dur . 's).';
    } else {
        $res = aic_process_single_movie_pipeline($mid, $env, array(
            'audio_limit_sec' => (int)$batch_cfg['audio_limit_sec'],
            'whisper_threads' => (int)$batch_cfg['whisper_threads'],
            'run_text_after'  => true
        ));
        $dur = round(microtime(true) - $t_run, 2);
        $comp = isset($res['movie']['ai']['text_compliance']) ? strtoupper($res['movie']['ai']['text_compliance']) : 'OK';
        $lang = isset($res['movie']['ai']['detected_language']) ? strtoupper($res['movie']['ai']['detected_language']) : '-';
        $msg = 'Video #' . $mid . ' transkribiert & geprüft (Sprache: ' . $lang . ' | BRAM: ' . $comp . ' | Dauer: ' . $dur . 's).';
    }

    $processed_count++;
    $cur_status = aic_get_worker_status();
    aic_update_worker_status(array(
        'state'                => 'idle',
        'current_movie_id'     => 0,
        'last_movie_id'        => $mid,
        'last_movie_title'     => $mtitle,
        'last_run_at'          => date('Y-m-d H:i:s'),
        'last_duration_sec'    => $dur,
        'last_message'         => $msg,
        'total_processed_runs' => ((int)$cur_status['total_processed_runs']) + 1
    ));

    echo "[AI-Worker] " . $msg . "\n";
}

@flock($lock_fp, LOCK_UN);
@fclose($lock_fp);

p4c_close(DB_HOST);
?>
