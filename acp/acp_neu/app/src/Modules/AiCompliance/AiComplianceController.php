<?php
declare(strict_types=1);

namespace App\Modules\AiCompliance;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * KI-Analyse & BRAM-Compliance Cockpit (#/ai-compliance).
 * Vollständige Modernisierung von acp/includes/ai_compliance.php & acp/includes/ajax/ai_compliance_action.php.
 */
final class AiComplianceController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    private function ensureCoreIncluded(): string
    {
        $rootDir = defined('SOURCEDIR') ? (string)SOURCEDIR : dirname(__DIR__, 5);
        require_once $rootDir . '/includes/ai_compliance_core.inc.php';
        \aic_ensure_table();
        return $rootDir;
    }

    /**
     * GET ?route=ai-compliance
     */
    public function index(): string
    {
        $rootDir = $this->ensureCoreIncluded();

        $env          = \aic_check_environment();
        $stats        = \aic_get_catalog_and_batch_stats();
        $batchConfig  = \aic_load_batch_config();
        $workerStatus = \aic_get_worker_status();
        $recentMovies = $this->fetchRecentMovies(25);
        $historyRows  = $this->fetchHistoryRows('all', 'all', '', 150);

        $rulesJsonPath = $rootDir . '/includes/ai_rules/bram_keyword_rules.json';
        $rulesData = [
            'system_prompt_md'   => (string)\aic_load_system_prompt(),
            'keyword_rules_json' => is_file($rulesJsonPath) ? (string)@file_get_contents($rulesJsonPath) : '',
            'parsed_categories'  => \aic_load_keyword_rules(),
        ];

        $initialMovieId = abs((int)$this->request->get('movie_id', 0));
        if ($initialMovieId <= 0) {
            $initialMovieId = abs((int)$this->request->get('id', 0));
        }
        if ($initialMovieId <= 0 && !empty($recentMovies)) {
            $initialMovieId = (int)$recentMovies[0]['id'];
        }
        $initialMovie = $initialMovieId > 0 ? \aic_get_movie_details($initialMovieId) : null;

        // Top-Counts für die Schnellnavigation im Bereich "Content prüfen"
        $navCounts = $this->fetchContentCheckNavCounts();

        return View::render('AiCompliance', 'index', [
            'env'           => $env,
            'stats'         => $stats,
            'batchConfig'   => $batchConfig,
            'workerStatus'  => $workerStatus,
            'recentMovies'  => $recentMovies,
            'historyRows'   => $historyRows,
            'rulesData'     => $rulesData,
            'initialMovie'  => $initialMovie,
            'navCounts'     => $navCounts,
        ]);
    }

    /**
     * GET/POST ?route=ai-compliance/ajax
     */
    public function ajax(): void
    {
        $rootDir = $this->ensureCoreIncluded();
        @session_write_close();

        $env    = \aic_check_environment();
        $action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : 'init_dashboard';

        if ($action === 'init_dashboard') {
            $rulesJsonPath = $rootDir . '/includes/ai_rules/bram_keyword_rules.json';
            $this->sendRawJson([
                'status'        => 'ok',
                'stats'         => \aic_get_catalog_and_batch_stats(),
                'batch_config'  => \aic_load_batch_config(),
                'worker_status' => \aic_get_worker_status(),
                'env'           => $env,
                'recent_movies' => $this->fetchRecentMovies(25),
                'history'       => $this->fetchHistoryRows('all', 'all', '', 150),
                'rules'         => [
                    'system_prompt_md'   => (string)\aic_load_system_prompt(),
                    'keyword_rules_json' => is_file($rulesJsonPath) ? (string)@file_get_contents($rulesJsonPath) : '',
                    'parsed_categories'  => \aic_load_keyword_rules(),
                ],
            ]);
        }

        if ($action === 'poll_worker_status') {
            $ws = \aic_get_worker_status();
            $curMovieInfo = null;
            if (!empty($ws['current_movie_id'])) {
                $cmId = abs((int)$ws['current_movie_id']);
                $rsCm = \p4c_query(
                    "SELECT `id`, `title`, `playtime_seconds` FROM `movies` WHERE `id` = '" . $cmId . "' LIMIT 1;",
                    __FILE__,
                    __LINE__
                );
                if (\p4c_num_rows($rsCm) > 0) {
                    $cm = \p4c_fetch_object($rsCm);
                    $curMovieInfo = [
                        'id'           => (int)$cm->id,
                        'title'        => \aic_clean_html_text($cm->title),
                        'playtime_fmt' => \aic_format_time((int)$cm->playtime_seconds),
                    ];
                }
            }
            $this->sendRawJson([
                'status'        => 'ok',
                'worker_status' => $ws,
                'current_movie' => $curMovieInfo,
            ]);
        }

        if ($action === 'filter_history') {
            $compFilter = isset($_REQUEST['compliance_filter']) ? trim((string)$_REQUEST['compliance_filter']) : 'all';
            $subFilter  = isset($_REQUEST['subtitle_filter']) ? trim((string)$_REQUEST['subtitle_filter']) : 'all';
            $searchQ    = isset($_REQUEST['search_query']) ? trim((string)$_REQUEST['search_query']) : '';

            $this->sendRawJson([
                'status'  => 'ok',
                'history' => $this->fetchHistoryRows($compFilter, $subFilter, $searchQ, 200),
                'stats'   => \aic_get_catalog_and_batch_stats(),
            ]);
        }

        if ($action === 'load_movie') {
            $movieId    = isset($_REQUEST['movie_id']) ? abs((int)$_REQUEST['movie_id']) : 0;
            $merchantId = isset($_REQUEST['merchant_id']) ? abs((int)$_REQUEST['merchant_id']) : 0;
            $mode       = isset($_REQUEST['mode']) ? trim((string)$_REQUEST['mode']) : '';

            if ($mode === 'next_untested') {
                $whereM = ($merchantId > 0) ? " AND `m`.`merchant_id` = '" . $merchantId . "' " : "";
                $rsNext = \p4c_query(
                    "SELECT `m`.`id`
                     FROM `movies` `m`
                     LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
                     WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted' " . $whereM . "
                       AND (`ai`.`movie_id` IS NULL OR `ai`.`subtitle_status` = '0')
                     ORDER BY `m`.`id` DESC
                     LIMIT 1;",
                    __FILE__,
                    __LINE__
                );
                if (\p4c_num_rows($rsNext) > 0) {
                    $movieId = (int)\p4c_fetch_object($rsNext)->id;
                }
            } elseif ($mode === 'find_interesting') {
                $rsScan = \p4c_query(
                    "SELECT `id`, `title`, `description`, `meta_title`, `meta_description`
                     FROM `movies`
                     WHERE `convert_status` = '2' AND `status` != 'deleted'
                     ORDER BY `id` DESC
                     LIMIT 400;",
                    __FILE__,
                    __LINE__
                );
                $fallbackId = 0;
                while ($row = \p4c_fetch_object($rsScan)) {
                    if ($fallbackId === 0) {
                        $fallbackId = (int)$row->id;
                    }
                    $res = \aic_analyze_texts([
                        'title'            => $row->title,
                        'description'      => $row->description,
                        'meta_title'       => isset($row->meta_title) ? $row->meta_title : '',
                        'meta_description' => isset($row->meta_description) ? $row->meta_description : '',
                    ], [], '', ['llm_ready' => false]);
                    if ($res['compliance_status'] !== 'ok') {
                        $movieId = (int)$row->id;
                        break;
                    }
                }
                if ($movieId === 0) {
                    $movieId = $fallbackId;
                }
            } elseif ($movieId === 0 && $merchantId > 0) {
                $rsM = \p4c_query(
                    "SELECT `id` FROM `movies` WHERE `merchant_id` = '" . $merchantId . "' AND `convert_status` = '2' AND `status` != 'deleted' ORDER BY `id` DESC LIMIT 1;",
                    __FILE__,
                    __LINE__
                );
                if (\p4c_num_rows($rsM) > 0) {
                    $movieId = (int)\p4c_fetch_object($rsM)->id;
                }
            }

            $details = \aic_get_movie_details($movieId);
            if (!$details) {
                $this->sendRawJson([
                    'status'  => 'error',
                    'message' => 'Es wurde kein passendes konvertiertes Video zur angegebenen ID gefunden.',
                ]);
            }

            $this->sendRawJson([
                'status' => 'ok',
                'movie'  => $details,
            ]);
        }

        if ($action === 'run_whisper') {
            $movieId       = isset($_POST['movie_id']) ? abs((int)$_POST['movie_id']) : 0;
            $simScenario   = isset($_POST['sim_scenario']) ? trim((string)$_POST['sim_scenario']) : 'clean';
            $audioLimitSec = isset($_POST['audio_limit_sec']) ? abs((int)$_POST['audio_limit_sec']) : 120;
            $details       = \aic_get_movie_details($movieId);

            if (!$details) {
                $this->sendRawJson(['status' => 'error', 'message' => 'Ungültige Film-ID.']);
            }

            if (!empty($details['mp4_exists']) && $simScenario === 'clean') {
                $res = \aic_process_single_movie_pipeline($movieId, $env, [
                    'audio_limit_sec' => $audioLimitSec,
                    'whisper_threads' => 16,
                    'run_text_after'  => false,
                ]);
                if (($res['status'] ?? '') !== 'ok') {
                    $this->sendRawJson([
                        'status'  => 'error',
                        'message' => $res['movie']['ai']['last_error'] ?? 'Fehler bei der Audio-Extraktion.',
                    ]);
                }
                $this->sendRawJson([
                    'status'    => 'ok',
                    'mode_used' => $res['mode_used'],
                    'movie'     => $res['movie'],
                ]);
            }

            // Test- & Simulationsmodus
            $t0        = microtime(true);
            $modeUsed  = 'Testlabor-Simulation (Whisper-Vorschau)';
            $subStatus = 2;

            if ($simScenario === 'bram_violation') {
                $detectedLang = 'de';
                $langConf     = 99.10;
                $segments     = [
                    ['start' => 4.0,  'end' => 11.5, 'text' => 'Hey, schön dass du einschaltest zu meinem neuen privaten Video.'],
                    ['start' => 18.0, 'end' => 27.0, 'text' => 'In diesem Rollenspiel spiele ich deine kleine Stiefschwester, die gerade erst im Kinderzimmer aufgewacht ist.'],
                    ['start' => 35.0, 'end' => 44.0, 'text' => 'Du hast mir heimlich K.O.-Tropfen ins Glas gemischt, sodass ich völlig willenlos und betäubt bin.'],
                    ['start' => 52.0, 'end' => 61.0, 'text' => 'Wenn du noch mehr unzensierte Videos willst, schreib mir einfach auf Telegram oder schick mir Geld per PayPal!'],
                ];
            } elseif ($simScenario === 'bram_warning') {
                $detectedLang = 'de';
                $langConf     = 97.80;
                $segments     = [
                    ['start' => 3.0,  'end' => 10.0, 'text' => 'Hallo Schatz, heute wird es etwas wilder bei uns im Schlafzimmer.'],
                    ['start' => 15.0, 'end' => 24.5, 'text' => 'Ich habe mich als deine Stiefmutter verkleidet und werde jetzt gefesselt.'],
                    ['start' => 30.0, 'end' => 41.0, 'text' => 'Danach probieren wir noch ein bisschen Natursekt und KV unter der Dusche aus.'],
                ];
            } elseif ($simScenario === 'english_clean') {
                $detectedLang = 'en';
                $langConf     = 98.90;
                $segments     = [
                    ['start' => 2.0,  'end' => 9.0,  'text' => 'Welcome to my new exclusive clip, I hope you enjoy watching me relax today.'],
                    ['start' => 12.0, 'end' => 21.0, 'text' => 'I put on my favorite red lingerie just for you, let us have some consensual fun together.'],
                ];
            } else {
                $detectedLang = 'de';
                $langConf     = 98.60;
                $segments     = [
                    ['start' => 2.5,  'end' => 9.0,  'text' => 'Hallo ihr Lieben, willkommen zu meinem neuen Video hier auf meinem Profil!'],
                    ['start' => 11.0, 'end' => 19.5, 'text' => 'Ich habe mir heute meine neuen Dessous angezogen und freue mich auf eine heiße Zeit mit dir.'],
                    ['start' => 22.0, 'end' => 31.0, 'text' => 'Lehn dich zurück und genieße die Show in voller Länge.'],
                ];
            }

            $lines = [];
            foreach ($segments as $seg) {
                $lines[] = '[' . \aic_format_time($seg['start']) . ' - ' . \aic_format_time($seg['end']) . '] ' . $seg['text'];
            }
            $transcript = implode("\n", $lines);

            if (@is_dir($details['movie_dir'])) {
                $vttContent = \aic_build_vtt_content($segments, $detectedLang);
                @file_put_contents($details['movie_dir'] . '/' . $details['ai']['subtitle_filename'], $vttContent);
            }

            $duration = max(0.12, round(microtime(true) - $t0, 2));
            $now      = date('Y-m-d H:i:s');
            $segJson  = (string)json_encode($segments, JSON_UNESCAPED_UNICODE);
            $subFile  = (string)$details['ai']['subtitle_filename'];

            \p4c_query(
                "INSERT INTO `movies_ai_analysis`
                (`movie_id`, `merchant_id`, `subtitle_status`, `detected_language`, `language_confidence`, `subtitle_filename`, `spoken_transcript`, `spoken_segments_json`, `whisper_duration_sec`, `created_at`, `updated_at`)
                VALUES (
                    '" . abs((int)$details['id']) . "',
                    '" . abs((int)$details['merchant_id']) . "',
                    '" . abs((int)$subStatus) . "',
                    '" . \p4c_escape_string($detectedLang) . "',
                    '" . \p4c_escape_string((string)$langConf) . "',
                    '" . \p4c_escape_string($subFile) . "',
                    '" . \p4c_escape_string($transcript) . "',
                    '" . \p4c_escape_string($segJson) . "',
                    '" . \p4c_escape_string((string)$duration) . "',
                    '" . $now . "',
                    '" . $now . "'
                )
                ON DUPLICATE KEY UPDATE
                    `subtitle_status`      = '" . abs((int)$subStatus) . "',
                    `detected_language`    = '" . \p4c_escape_string($detectedLang) . "',
                    `language_confidence`  = '" . \p4c_escape_string((string)$langConf) . "',
                    `subtitle_filename`    = '" . \p4c_escape_string($subFile) . "',
                    `spoken_transcript`    = '" . \p4c_escape_string($transcript) . "',
                    `spoken_segments_json` = '" . \p4c_escape_string($segJson) . "',
                    `whisper_duration_sec` = '" . \p4c_escape_string((string)$duration) . "',
                    `updated_at`           = '" . $now . "';",
                __FILE__,
                __LINE__
            );

            $this->sendRawJson([
                'status'    => 'ok',
                'mode_used' => $modeUsed,
                'movie'     => \aic_get_movie_details($details['id']),
            ]);
        }

        if ($action === 'run_text_check') {
            $movieId          = isset($_POST['movie_id']) ? abs((int)$_POST['movie_id']) : 0;
            $customTranscript = isset($_POST['custom_transcript']) ? trim((string)$_POST['custom_transcript']) : null;
            $customTitle      = isset($_POST['custom_title']) ? trim((string)$_POST['custom_title']) : null;
            $customDesc       = isset($_POST['custom_desc']) ? trim((string)$_POST['custom_desc']) : null;

            $details = \aic_get_movie_details($movieId);
            if (!$details) {
                $this->sendRawJson(['status' => 'error', 'message' => 'Ungültige Film-ID.']);
            }

            $transcriptToCheck = ($customTranscript !== null) ? $customTranscript : (string)$details['ai']['spoken_transcript'];
            $segmentsToCheck   = $details['ai']['segments'];

            if ($customTranscript !== null) {
                $parsedSegs = [];
                $lines = explode("\n", $customTranscript);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (preg_match('/^\[(\d+):(\d+)\s*-\s*(\d+):(\d+)\]\s*(.+)$/', $line, $mTs)) {
                        $sSec = ((int)$mTs[1] * 60) + (int)$mTs[2];
                        $eSec = ((int)$mTs[3] * 60) + (int)$mTs[4];
                        $parsedSegs[] = ['start' => $sSec, 'end' => $eSec, 'text' => $mTs[5]];
                    } else {
                        $parsedSegs[] = ['start' => 0, 'end' => 0, 'text' => $line];
                    }
                }
                $segmentsToCheck = $parsedSegs;
            }

            $movieCheckInput = [
                'title'            => ($customTitle !== null) ? $customTitle : $details['title'],
                'description'      => ($customDesc !== null) ? $customDesc : $details['description'],
                'meta_title'       => $details['meta_title'],
                'meta_description' => $details['meta_description'],
            ];

            $report   = \aic_analyze_texts($movieCheckInput, $segmentsToCheck, $transcriptToCheck, $env);
            $now      = date('Y-m-d H:i:s');
            $violJson = (string)json_encode($report['violations'], JSON_UNESCAPED_UNICODE);
            $segJson  = (string)json_encode($segmentsToCheck, JSON_UNESCAPED_UNICODE);

            \p4c_query(
                "INSERT INTO `movies_ai_analysis`
                (`movie_id`, `merchant_id`, `spoken_transcript`, `spoken_segments_json`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
                VALUES (
                    '" . abs((int)$details['id']) . "',
                    '" . abs((int)$details['merchant_id']) . "',
                    '" . \p4c_escape_string($transcriptToCheck) . "',
                    '" . \p4c_escape_string($segJson) . "',
                    '2',
                    '" . \p4c_escape_string((string)$report['compliance_status']) . "',
                    '" . abs((int)$report['risk_score']) . "',
                    '" . \p4c_escape_string((string)$report['engine_used']) . "',
                    '" . \p4c_escape_string((string)$report['summary_de']) . "',
                    '" . \p4c_escape_string($violJson) . "',
                    '" . \p4c_escape_string((string)$report['duration_sec']) . "',
                    '" . $now . "',
                    '" . $now . "'
                )
                ON DUPLICATE KEY UPDATE
                    `spoken_transcript`    = '" . \p4c_escape_string($transcriptToCheck) . "',
                    `spoken_segments_json` = '" . \p4c_escape_string($segJson) . "',
                    `text_check_status`    = '2',
                    `text_compliance`      = '" . \p4c_escape_string((string)$report['compliance_status']) . "',
                    `text_risk_score`      = '" . abs((int)$report['risk_score']) . "',
                    `text_engine_used`     = '" . \p4c_escape_string((string)$report['engine_used']) . "',
                    `text_summary`         = '" . \p4c_escape_string((string)$report['summary_de']) . "',
                    `text_violations_json` = '" . \p4c_escape_string($violJson) . "',
                    `text_duration_sec`    = '" . \p4c_escape_string((string)$report['duration_sec']) . "',
                    `updated_at`           = '" . $now . "';",
                __FILE__,
                __LINE__
            );

            $this->sendRawJson([
                'status' => 'ok',
                'report' => $report,
                'movie'  => \aic_get_movie_details($details['id']),
            ]);
        }

        if ($action === 'save_batch_config') {
            $newCfg = [
                'cron_enabled'            => !empty($_POST['cron_enabled']),
                'task_mode'               => isset($_POST['task_mode']) ? trim((string)$_POST['task_mode']) : 'whisper_and_text',
                'whisper_model_pref'      => isset($_POST['whisper_model_pref']) ? trim((string)$_POST['whisper_model_pref']) : 'small',
                'whisper_threads'         => isset($_POST['whisper_threads']) ? (int)$_POST['whisper_threads'] : 12,
                'audio_limit_sec'         => isset($_POST['audio_limit_sec']) ? (int)$_POST['audio_limit_sec'] : 0,
                'order_direction'         => isset($_POST['order_direction']) ? trim((string)$_POST['order_direction']) : 'DESC',
                'pause_on_ffmpeg_convert' => !empty($_POST['pause_on_ffmpeg_convert']),
                'max_movies_per_run'      => isset($_POST['max_movies_per_run']) ? (int)$_POST['max_movies_per_run'] : 2,
                'sync_empty_movie_lang'   => !empty($_POST['sync_empty_movie_lang']),
            ];

            $saved = \aic_save_batch_config($newCfg);
            $this->sendRawJson([
                'status'       => 'ok',
                'message'      => 'Deine Batch- und Cronjob-Einstellungen wurden erfolgreich gespeichert.',
                'batch_config' => $saved,
                'env'          => \aic_check_environment(),
            ]);
        }

        if ($action === 'run_batch_step') {
            $batchCfg = \aic_load_batch_config();
            $orderDir = (strtoupper((string)$batchCfg['order_direction']) === 'ASC') ? 'ASC' : 'DESC';

            $rsNext = \p4c_query(
                "SELECT `m`.`id`, `m`.`title`
                 FROM `movies` `m`
                 LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
                 WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
                   AND (`ai`.`movie_id` IS NULL OR `ai`.`subtitle_status` = '0')
                 ORDER BY `m`.`id` " . $orderDir . "
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );

            if (\p4c_num_rows($rsNext) === 0) {
                $this->sendRawJson([
                    'status'        => 'ok',
                    'has_more'      => false,
                    'message'       => 'Alle konvertierten Videos im Katalog besitzen bereits einen Audio-/Untertitel-Status.',
                    'stats'         => \aic_get_catalog_and_batch_stats(),
                    'worker_status' => \aic_get_worker_status(),
                ]);
            }

            $nextObj = \p4c_fetch_object($rsNext);
            $mid     = (int)$nextObj->id;
            $mtitle  = \aic_clean_html_text($nextObj->title);

            \aic_update_worker_status([
                'state'            => 'running',
                'current_movie_id' => $mid,
                'last_run_at'      => date('Y-m-d H:i:s'),
                'last_message'     => 'Browser-Batch verarbeitet Video #' . $mid . ' (' . $mtitle . ')...',
            ]);

            $t0  = microtime(true);
            $res = \aic_process_single_movie_pipeline($mid, $env, [
                'audio_limit_sec' => (int)$batchCfg['audio_limit_sec'],
                'whisper_threads' => (int)$batchCfg['whisper_threads'],
                'run_text_after'  => true,
            ]);
            $dur = round(microtime(true) - $t0, 2);

            $comp = isset($res['movie']['ai']['text_compliance']) ? strtoupper((string)$res['movie']['ai']['text_compliance']) : 'OK';
            $lang = isset($res['movie']['ai']['detected_language']) ? strtoupper((string)$res['movie']['ai']['detected_language']) : '-';
            $msg  = 'Video #' . $mid . ' (' . $mtitle . ') abgeschlossen -> Sprache: ' . $lang . ' | BRAM: ' . $comp . ' (' . $dur . ' Sek.)';

            $curStatus = \aic_get_worker_status();
            $wStatus   = \aic_update_worker_status([
                'state'                => 'idle',
                'current_movie_id'     => 0,
                'last_movie_id'        => $mid,
                'last_movie_title'     => $mtitle,
                'last_run_at'          => date('Y-m-d H:i:s'),
                'last_duration_sec'    => $dur,
                'last_message'         => $msg,
                'total_processed_runs' => ((int)($curStatus['total_processed_runs'] ?? 0)) + 1,
            ]);

            $stats = \aic_get_catalog_and_batch_stats();

            $this->sendRawJson([
                'status'          => 'ok',
                'has_more'        => ($stats['remaining_subtitles'] > 0),
                'message'         => $msg,
                'processed_movie' => $res['movie'],
                'stats'           => $stats,
                'worker_status'   => $wStatus,
            ]);
        }

        if ($action === 'run_bulk_text_precheck') {
            @set_time_limit(120);
            $chunkSize    = isset($_POST['chunk_size']) ? max(10, min(500, (int)$_POST['chunk_size'])) : 250;
            $forceRecheck = !empty($_POST['force_recheck']);
            $lastId       = isset($_POST['last_id']) ? abs((int)$_POST['last_id']) : 0;

            if ($forceRecheck) {
                $whereCursor = ($lastId > 0) ? (" AND `m`.`id` < '" . $lastId . "' ") : "";
                $sqlChunk = "SELECT `m`.`id`, `m`.`merchant_id`, `m`.`file_id`, `m`.`title`, `m`.`description`, `m`.`meta_title`, `m`.`meta_description`,
                        `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`
                    FROM `movies` `m`
                    LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
                    WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted' " . $whereCursor . "
                    ORDER BY `m`.`id` DESC
                    LIMIT " . $chunkSize . ";";
            } else {
                $sqlChunk = "SELECT `m`.`id`, `m`.`merchant_id`, `m`.`file_id`, `m`.`title`, `m`.`description`, `m`.`meta_title`, `m`.`meta_description`,
                        `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`
                    FROM `movies` `m`
                    LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
                    WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
                      AND (`ai`.`movie_id` IS NULL OR `ai`.`text_check_status` = '0')
                    ORDER BY `m`.`id` DESC
                    LIMIT " . $chunkSize . ";";
            }

            $rsChunk          = \p4c_query($sqlChunk, __FILE__, __LINE__);
            $processedInChunk = 0;
            $newLastId        = 0;
            $now              = date('Y-m-d H:i:s');

            $fastEnv              = $env;
            $fastEnv['llm_ready'] = false;

            while ($row = \p4c_fetch_object($rsChunk)) {
                $mid       = (int)$row->id;
                $newLastId = $mid;
                $segs      = [];
                if (!empty($row->spoken_segments_json)) {
                    $dec = @json_decode($row->spoken_segments_json, true);
                    if (is_array($dec)) {
                        $segs = $dec;
                    }
                }
                $spkTxt = !empty($row->spoken_transcript) ? (string)$row->spoken_transcript : '';

                $rep = \aic_analyze_texts([
                    'title'            => $row->title,
                    'description'      => $row->description,
                    'meta_title'       => isset($row->meta_title) ? $row->meta_title : '',
                    'meta_description' => isset($row->meta_description) ? $row->meta_description : '',
                ], $segs, $spkTxt, $fastEnv);

                $violJson = (string)json_encode($rep['violations'], JSON_UNESCAPED_UNICODE);
                $subFile  = 'sub_' . $mid . '_' . $row->file_id . '.vtt';

                \p4c_query(
                    "INSERT INTO `movies_ai_analysis`
                    (`movie_id`, `merchant_id`, `subtitle_filename`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
                    VALUES (
                        '" . abs((int)$mid) . "',
                        '" . abs((int)$row->merchant_id) . "',
                        '" . \p4c_escape_string($subFile) . "',
                        '2',
                        '" . \p4c_escape_string((string)$rep['compliance_status']) . "',
                        '" . abs((int)$rep['risk_score']) . "',
                        '" . \p4c_escape_string((string)$rep['engine_used']) . "',
                        '" . \p4c_escape_string((string)$rep['summary_de']) . "',
                        '" . \p4c_escape_string($violJson) . "',
                        '" . \p4c_escape_string((string)$rep['duration_sec']) . "',
                        '" . $now . "',
                        '" . $now . "'
                    )
                    ON DUPLICATE KEY UPDATE
                        `text_check_status`    = '2',
                        `text_compliance`      = '" . \p4c_escape_string((string)$rep['compliance_status']) . "',
                        `text_risk_score`      = '" . abs((int)$rep['risk_score']) . "',
                        `text_engine_used`     = '" . \p4c_escape_string((string)$rep['engine_used']) . "',
                        `text_summary`         = '" . \p4c_escape_string((string)$rep['summary_de']) . "',
                        `text_violations_json` = '" . \p4c_escape_string($violJson) . "',
                        `text_duration_sec`    = '" . \p4c_escape_string((string)$rep['duration_sec']) . "',
                        `updated_at`           = '" . $now . "';",
                    __FILE__,
                    __LINE__
                );

                $processedInChunk++;
            }

            $stats   = \aic_get_catalog_and_batch_stats();
            $hasMore = $forceRecheck ? ($processedInChunk >= $chunkSize) : ($stats['remaining_text'] > 0 && $processedInChunk > 0);

            $this->sendRawJson([
                'status'             => 'ok',
                'processed_in_chunk' => $processedInChunk,
                'last_id'            => $newLastId,
                'has_more'           => $hasMore,
                'stats'              => $stats,
            ]);
        }

        if ($action === 'save_rules') {
            $promptMd  = isset($_POST['system_prompt_md']) ? (string)$_POST['system_prompt_md'] : '';
            $rulesJson = isset($_POST['keyword_rules_json']) ? (string)$_POST['keyword_rules_json'] : '';

            if (trim($rulesJson) !== '') {
                $testDecode = @json_decode($rulesJson, true);
                if (!is_array($testDecode) || !isset($testDecode['categories'])) {
                    $this->sendRawJson([
                        'status'  => 'error',
                        'message' => 'Deine JSON-Wortliste enthält einen Syntaxfehler. Bitte prüfe Kommas und Anführungszeichen.',
                    ]);
                }
                @file_put_contents($rootDir . '/includes/ai_rules/bram_keyword_rules.json', $rulesJson);
            }

            if (trim($promptMd) !== '') {
                @file_put_contents($rootDir . '/includes/ai_rules/bram_system_prompt.md', $promptMd);
            }

            $this->sendRawJson([
                'status'            => 'ok',
                'message'           => 'Das BRAM-Regelwerk und der KI-System-Prompt wurden erfolgreich gespeichert.',
                'parsed_categories' => json_decode($rulesJson, true),
            ]);
        }

        if ($action === 'inspect_transcripts') {
            $this->handleInspectTranscripts();
        }

        if ($action === 'clean_all_vtt_transcripts') {
            $this->handleCleanAllVttTranscripts();
        }

        if ($action === 'requeue_exotic_lang_movies') {
            $this->handleRequeueExoticLangMovies();
        }

        $this->sendRawJson([
            'status'  => 'error',
            'message' => 'Unbekannte Aktion: ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
        ]);
    }

    private function handleInspectTranscripts(): void
    {
        $rsTr = \p4c_query(
            "SELECT `ai`.`movie_id`, `ai`.`merchant_id`, `ai`.`subtitle_status`, `ai`.`detected_language`,
                    `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`, `m`.`title`, `m`.`playtime_seconds`
             FROM `movies_ai_analysis` `ai`
             LEFT JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
             WHERE `ai`.`subtitle_status` IN (2, 3) OR (`ai`.`spoken_transcript` != '' AND `ai`.`spoken_transcript` IS NOT NULL)
             ORDER BY `ai`.`movie_id` DESC;",
            __FILE__,
            __LINE__
        );

        $langNames = [
            'de' => 'Deutsch',
            'en' => 'Englisch',
            'fr' => 'Französisch',
            'es' => 'Spanisch',
            'it' => 'Italienisch',
            'nl' => 'Niederländisch',
            'pl' => 'Polnisch',
            'ru' => 'Russisch',
            'cs' => 'Tschechisch',
            'pt' => 'Portugiesisch',
            'nn' => 'Norwegisch Nynorsk (Häufiges Whisper-Artefakt bei Stöhnen/Rauschen)',
            'no' => 'Norwegisch (Häufiges Whisper-Artefakt bei Geräuschen)',
            'la' => 'Latein (Whisper-Artefakt)',
            'cy' => 'Walisisch (Whisper-Artefakt)',
            'jw' => 'Javanisch (Whisper-Artefakt)',
        ];

        $totalTranscribed    = 0;
        $totalSilent         = 0;
        $totalOnlyBrackets   = 0;
        $totalWithRealSpeech = 0;
        $totalBracketOcc     = 0;

        $languagesMap = [];
        $bracketsMap  = [];
        $phrasesMap   = [];
        $noDialogList = [];

        while ($row = \p4c_fetch_object($rsTr)) {
            $mid     = (int)$row->movie_id;
            $subSt   = (int)$row->subtitle_status;
            $langRaw = strtolower(trim((string)$row->detected_language));
            if ($langRaw === '') {
                $langRaw = 'unbekannt';
            }
            $mtitle = \aic_clean_html_text($row->title);

            if ($subSt === 3) {
                $totalSilent++;
                continue;
            }
            $totalTranscribed++;

            $segTexts = [];
            if (!empty($row->spoken_segments_json)) {
                $decS = @json_decode($row->spoken_segments_json, true);
                if (is_array($decS)) {
                    foreach ($decS as $sItem) {
                        if (isset($sItem['text']) && trim((string)$sItem['text']) !== '') {
                            $segTexts[] = trim(\aic_utf8((string)$sItem['text']));
                        }
                    }
                }
            }
            if (empty($segTexts) && !empty($row->spoken_transcript)) {
                $rawLines = explode("\n", \aic_utf8((string)$row->spoken_transcript));
                foreach ($rawLines as $rl) {
                    $rl = trim($rl);
                    if ($rl === '') {
                        continue;
                    }
                    $rlClean = (string)preg_replace('/^\[\d{2,}:\d{2}(?:\.\d+)?\s*-\s*\d{2,}:\d{2}(?:\.\d+)?\]\s*/', '', $rl);
                    if (trim($rlClean) !== '') {
                        $segTexts[] = trim($rlClean);
                    }
                }
            }

            $movieHasRealSpeech = false;
            $movieSeenBrackets  = [];
            $movieSeenPhrases   = [];

            foreach ($segTexts as $st) {
                if (stripos($st, 'Kein gesprochener Dialog') !== false || stripos($st, 'besitzt keine Tonspur') !== false) {
                    continue;
                }

                $matchesB = [];
                if (preg_match_all('/(\[[^\]]+\]|\([^\)]+\)|\*[^\*]+\*)/u', $st, $mAll)) {
                    $matchesB = $mAll[1];
                }

                foreach ($matchesB as $bTag) {
                    $bNorm = trim($bTag);
                    if ($bNorm === '' || preg_match('/^\[\d{2,}:\d{2}/', $bNorm)) {
                        continue;
                    }

                    $totalBracketOcc++;
                    if (!isset($bracketsMap[$bNorm])) {
                        $normTarget = \aic_normalize_single_bracket_tag($bNorm);
                        if ($normTarget !== '') {
                            if ($normTarget === $bNorm) {
                                $catType  = 'keep_atmosphere';
                                $catLabel = 'Sauberer Atmosphären-Tag (' . $normTarget . ' -> Behalten)';
                            } else {
                                $catType  = 'convert_moan';
                                $catLabel = 'Wird vereinheitlicht zu -> ' . $normTarget;
                            }
                        } else {
                            $catType  = 'remove_artifact';
                            $catLabel = 'Whisper-Halluzination / Müll (-> Wird restlos entfernt)';
                        }

                        $bracketsMap[$bNorm] = [
                            'tag'               => $bNorm,
                            'normalized_to'     => $normTarget,
                            'total_occurrences' => 0,
                            'movie_count'       => 0,
                            'cat_type'          => $catType,
                            'cat_label'         => $catLabel,
                            'sample_movies'     => [],
                        ];
                    }
                    $bracketsMap[$bNorm]['total_occurrences']++;
                    if (!isset($movieSeenBrackets[$bNorm])) {
                        $movieSeenBrackets[$bNorm] = true;
                        $bracketsMap[$bNorm]['movie_count']++;
                        if (count($bracketsMap[$bNorm]['sample_movies']) < 6) {
                            $bracketsMap[$bNorm]['sample_movies'][] = [
                                'id'    => $mid,
                                'title' => $mtitle,
                            ];
                        }
                    }
                }

                $withoutBrackets = trim((string)preg_replace('/(\[[^\]]*\]|\([^\)]*\)|\*[^\*]*\*)/u', '', $st));
                $onlyLetters     = (string)preg_replace('/[^\p{L}\p{N}]+/u', '', $withoutBrackets);

                if (mb_strlen($onlyLetters, 'UTF-8') >= 2) {
                    $movieHasRealSpeech = true;
                    $pNorm = trim($withoutBrackets);
                    if (mb_strlen($pNorm, 'UTF-8') <= 90) {
                        if (!isset($phrasesMap[$pNorm])) {
                            $phrasesMap[$pNorm] = [
                                'phrase'            => $pNorm,
                                'total_occurrences' => 0,
                                'movie_count'       => 0,
                                'sample_movies'     => [],
                            ];
                        }
                        $phrasesMap[$pNorm]['total_occurrences']++;
                        if (!isset($movieSeenPhrases[$pNorm])) {
                            $movieSeenPhrases[$pNorm] = true;
                            $phrasesMap[$pNorm]['movie_count']++;
                            if (count($phrasesMap[$pNorm]['sample_movies']) < 5) {
                                $phrasesMap[$pNorm]['sample_movies'][] = [
                                    'id'    => $mid,
                                    'title' => $mtitle,
                                ];
                            }
                        }
                    }
                }
            }

            if ($movieHasRealSpeech) {
                $totalWithRealSpeech++;
            } else {
                $totalOnlyBrackets++;
                if (count($noDialogList) < 100) {
                    $previewStr = implode(' | ', array_slice($segTexts, 0, 6));
                    if (mb_strlen($previewStr, 'UTF-8') > 200) {
                        $previewStr = mb_substr($previewStr, 0, 200, 'UTF-8') . '...';
                    }
                    $noDialogList[] = [
                        'movie_id'          => $mid,
                        'merchant_id'       => (int)$row->merchant_id,
                        'title'             => $mtitle,
                        'detected_language' => strtoupper($langRaw),
                        'segment_count'     => count($segTexts),
                        'preview'           => $previewStr,
                    ];
                }
            }

            if (!isset($languagesMap[$langRaw])) {
                $languagesMap[$langRaw] = [
                    'code'                => strtoupper($langRaw),
                    'code_lower'          => $langRaw,
                    'name'                => $langNames[$langRaw] ?? ('ISO-Code: ' . strtoupper($langRaw)),
                    'movie_count'         => 0,
                    'real_speech_count'   => 0,
                    'only_brackets_count' => 0,
                    'sample_movies'       => [],
                ];
            }
            $languagesMap[$langRaw]['movie_count']++;
            if ($movieHasRealSpeech) {
                $languagesMap[$langRaw]['real_speech_count']++;
            } else {
                $languagesMap[$langRaw]['only_brackets_count']++;
            }
            if (count($languagesMap[$langRaw]['sample_movies']) < 6) {
                $languagesMap[$langRaw]['sample_movies'][] = [
                    'id'          => $mid,
                    'title'       => $mtitle,
                    'only_noises' => !$movieHasRealSpeech,
                ];
            }
        }

        $languagesList = array_values($languagesMap);
        usort($languagesList, static fn(array $a, array $b): int => $b['movie_count'] <=> $a['movie_count']);

        $bracketsList = array_values($bracketsMap);
        usort($bracketsList, static function (array $a, array $b): int {
            if ($b['total_occurrences'] !== $a['total_occurrences']) {
                return $b['total_occurrences'] <=> $a['total_occurrences'];
            }
            return $b['movie_count'] <=> $a['movie_count'];
        });

        $phrasesFiltered = [];
        foreach ($phrasesMap as $pm) {
            if ($pm['total_occurrences'] >= 2) {
                $phrasesFiltered[] = $pm;
            }
        }
        usort($phrasesFiltered, static function (array $a, array $b): int {
            if ($b['movie_count'] !== $a['movie_count']) {
                return $b['movie_count'] <=> $a['movie_count'];
            }
            return $b['total_occurrences'] <=> $a['total_occurrences'];
        });
        $phrasesList = array_slice($phrasesFiltered, 0, 80);

        $allowedWl    = \aic_get_allowed_whisper_languages();
        $exoticTotal  = 0;
        $exoticSpeech = 0;
        foreach ($languagesList as $ll) {
            if (!isset($allowedWl[$ll['code_lower']])) {
                $exoticTotal  += (int)$ll['movie_count'];
                $exoticSpeech += (int)$ll['real_speech_count'];
            }
        }

        $this->sendRawJson([
            'status'      => 'ok',
            'exported_at' => date('Y-m-d H:i:s'),
            'summary'     => [
                'total_transcribed'      => $totalTranscribed,
                'total_silent'           => $totalSilent,
                'total_with_real_speech' => $totalWithRealSpeech,
                'total_only_brackets'    => $totalOnlyBrackets,
                'unique_bracket_tags'    => count($bracketsList),
                'total_bracket_occ'      => $totalBracketOcc,
                'exotic_lang_total'      => $exoticTotal,
                'exotic_lang_speech'     => $exoticSpeech,
            ],
            'languages'        => $languagesList,
            'bracket_tags'     => array_slice($bracketsList, 0, 300),
            'repeated_phrases' => $phrasesList,
            'no_dialog_movies' => $noDialogList,
        ]);
    }

    private function handleCleanAllVttTranscripts(): void
    {
        @set_time_limit(600);
        $t0 = microtime(true);

        $rsAll = \p4c_query(
            "SELECT `ai`.`movie_id`, `ai`.`merchant_id`, `ai`.`subtitle_status`, `ai`.`detected_language`,
                    `ai`.`subtitle_filename`, `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`,
                    `m`.`movie_language`, `m`.`storage_location`, `m`.`file_id`
             FROM `movies_ai_analysis` `ai`
             LEFT JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
             WHERE `ai`.`subtitle_status` = '2'
             ORDER BY `ai`.`movie_id` DESC;",
            __FILE__,
            __LINE__
        );

        $totalChecked       = 0;
        $moviesModified     = 0;
        $langsCorrected     = 0;
        $segmentsRemoved    = 0;
        $tagsNormalized     = 0;
        $vttFilesRewritten  = 0;
        $exoticSpeechMovies = [];
        $now                = date('Y-m-d H:i:s');

        while ($row = \p4c_fetch_object($rsAll)) {
            $totalChecked++;
            $mid     = (int)$row->movie_id;
            $merchId = (int)$row->merchant_id;
            $origLng = strtolower(trim((string)$row->detected_language));
            $dbLng   = strtolower(trim((string)$row->movie_language));

            $rawSegs = [];
            if (!empty($row->spoken_segments_json)) {
                $decS = @json_decode($row->spoken_segments_json, true);
                if (is_array($decS)) {
                    $rawSegs = $decS;
                }
            }
            if (empty($rawSegs) && !empty($row->spoken_transcript)) {
                $lines = explode("\n", \aic_utf8((string)$row->spoken_transcript));
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    if ($ln === '') {
                        continue;
                    }
                    if (preg_match('/^\[(\d{2,}):(\d{2})\s*-\s*(\d{2,}):(\d{2})\]\s*(.+)$/u', $ln, $mTs)) {
                        $sSec = ((int)$mTs[1] * 60) + (int)$mTs[2];
                        $eSec = ((int)$mTs[3] * 60) + (int)$mTs[4];
                        $rawSegs[] = ['start' => $sSec, 'end' => $eSec, 'text' => trim($mTs[5])];
                    } else {
                        $rawSegs[] = ['start' => 0, 'end' => 3, 'text' => $ln];
                    }
                }
            }

            $clean = \aic_clean_and_normalize_segments($rawSegs, $origLng, $dbLng);

            $newLng      = (string)$clean['detected_language'];
            $newSegsJson = (string)json_encode($clean['segments'], JSON_UNESCAPED_UNICODE);
            $newTrans    = (string)$clean['transcript'];

            $segmentsRemoved += (int)$clean['removed_count'];
            $tagsNormalized  += (int)$clean['normalized_count'];

            if (!empty($clean['had_exotic_lang']) && (!empty($clean['had_foreign_speech']) || !empty($clean['has_real_speech']))) {
                $exoticSpeechMovies[] = $mid;
            }

            $langChanged  = ($newLng !== $origLng);
            $transChanged = ($newTrans !== trim((string)$row->spoken_transcript) || $newSegsJson !== (string)$row->spoken_segments_json);

            if ($langChanged) {
                $langsCorrected++;
            }

            if ($langChanged || $transChanged) {
                $moviesModified++;
                $errNote = '';
                if (!empty($clean['had_exotic_lang'])) {
                    $errNote = 'OrigLang:' . $origLng . (!empty($clean['had_foreign_speech']) ? ':ForeignSpeech' : '');
                }
                \p4c_query(
                    "UPDATE `movies_ai_analysis` SET
                        `detected_language`    = '" . \p4c_escape_string($newLng) . "',
                        `spoken_transcript`    = '" . \p4c_escape_string($newTrans) . "',
                        `spoken_segments_json` = '" . \p4c_escape_string($newSegsJson) . "',
                        " . ($errNote !== '' ? "`last_error` = '" . \p4c_escape_string($errNote) . "'," : "") . "
                        `updated_at`           = '" . $now . "'
                     WHERE `movie_id` = '" . abs((int)$mid) . "'
                     LIMIT 1;",
                    __FILE__,
                    __LINE__
                );
            }

            $storageLoc = !empty($row->storage_location) ? $row->storage_location : MOVIES_DEFAULT_DIR;
            $movieDir   = MOVIES_PATH . '/' . $storageLoc . '/' . $merchId . '/' . $mid;
            $subFile    = !empty($row->subtitle_filename) ? $row->subtitle_filename : ('sub_' . $mid . '_' . $row->file_id . '.vtt');
            $vttPath    = $movieDir . '/' . $subFile;

            if (@is_dir($movieDir)) {
                $vttContent = \aic_build_vtt_content($clean['segments'], $newLng);
                if (@file_put_contents($vttPath, $vttContent) !== false) {
                    $vttFilesRewritten++;
                }
            }
        }

        $dur = round(microtime(true) - $t0, 2);

        $this->sendRawJson([
            'status'              => 'ok',
            'duration_sec'        => $dur,
            'total_checked'       => $totalChecked,
            'movies_modified'     => $moviesModified,
            'langs_corrected'     => $langsCorrected,
            'segments_removed'    => $segmentsRemoved,
            'tags_normalized'     => $tagsNormalized,
            'vtt_files_rewritten' => $vttFilesRewritten,
            'exotic_speech_count' => count($exoticSpeechMovies),
            'message'             => 'Bereinigung abgeschlossen (' . $dur . ' Sek.): ' . $moviesModified . ' von ' . $totalChecked . ' Transkripten bereinigt, ' . $segmentsRemoved . ' Halluzinations-/Schleifen-Segmente entfernt, ' . $tagsNormalized . ' Atmosphären-Tags auf Deutsch vereinheitlicht, ' . $langsCorrected . ' exotische Sprachcodes korrigiert und ' . $vttFilesRewritten . ' .vtt-Dateien sauber neu geschrieben.',
        ]);
    }

    private function handleRequeueExoticLangMovies(): void
    {
        $requeueMode = isset($_POST['requeue_mode']) ? trim((string)$_POST['requeue_mode']) : 'foreign_speech_only';
        $allowedWl   = array_keys(\aic_get_allowed_whisper_languages());
        $inSql       = "'" . implode("','", array_map('p4c_escape_string', $allowedWl)) . "'";

        $rsEx = \p4c_query(
            "SELECT `movie_id`, `detected_language`, `spoken_segments_json`, `spoken_transcript`, `last_error`
             FROM `movies_ai_analysis`
             WHERE `subtitle_status` = '2'
               AND (LOWER(`detected_language`) NOT IN (" . $inSql . ") OR `last_error` LIKE 'OrigLang:%');",
            __FILE__,
            __LINE__
        );

        $requeuedIds = [];
        $now         = date('Y-m-d H:i:s');

        while ($row = \p4c_fetch_object($rsEx)) {
            $mid     = (int)$row->movie_id;
            $detLow  = strtolower(trim((string)$row->detected_language));
            $lastErr = (string)$row->last_error;

            if ($requeueMode === 'all_exotic') {
                $requeuedIds[] = $mid;
            } else {
                $origCode = $detLow;
                if (preg_match('/^OrigLang:([a-z]+)/i', $lastErr, $mOl)) {
                    $origCode = strtolower($mOl[1]);
                }
                $hadForeignFlag = (stripos($lastErr, 'ForeignSpeech') !== false);
                $hasNonBracket  = false;
                $withoutB       = trim((string)preg_replace('/(\[[^\]]*\]|\([^\)]*\)|\*[^\*]*\*)/u', '', (string)$row->spoken_transcript));
                $withoutB       = (string)preg_replace('/\d{2,}:\d{2}/', '', $withoutB);
                if (stripos($withoutB, 'Kein gesprochener Dialog') === false && mb_strlen((string)preg_replace('/[^\p{L}]+/u', '', $withoutB), 'UTF-8') >= 3) {
                    $hasNonBracket = true;
                }

                if ($hadForeignFlag || ($origCode !== 'nn' && $origCode !== 'no' && $hasNonBracket)) {
                    $requeuedIds[] = $mid;
                }
            }
        }

        if (!empty($requeuedIds)) {
            $idsSql = implode(',', array_map(static fn(int $id): int => abs($id), $requeuedIds));
            \p4c_query(
                "UPDATE `movies_ai_analysis` SET
                    `subtitle_status` = '0',
                    `last_error`      = '',
                    `updated_at`      = '" . $now . "'
                 WHERE `movie_id` IN (" . $idsSql . ");",
                __FILE__,
                __LINE__
            );
        }

        $this->sendRawJson([
            'status'         => 'ok',
            'requeued_count' => count($requeuedIds),
            'requeued_ids'   => array_slice($requeuedIds, 0, 50),
            'stats'          => \aic_get_catalog_and_batch_stats(),
            'message'        => count($requeuedIds) > 0
                ? (count($requeuedIds) . ' Filme wurden auf Status „Offen“ (subtitle_status = 0) zurückgesetzt und werden vom Hintergrund-Cronjob automatisch mit der europäischen Sprach-Whitelist & Fallback-Logik neu durch Whisper transkribiert!')
                : 'Keine passenden Filme zum erneuten Einreihen gefunden (alle betroffenen Filme sind bereits eingereiht oder bereinigt).',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRecentMovies(int $limit = 25): array
    {
        $cleanLimit = max(5, min(100, $limit));
        $rows = [];
        $rsRec = \p4c_query(
            "SELECT `m`.`id`, `m`.`merchant_id`, `m`.`title`, `m`.`playtime_seconds`, `m`.`released`, `m`.`status`,
                    `ai`.`subtitle_status`, `ai`.`text_compliance`, `ai`.`detected_language`
             FROM `movies` `m`
             LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
             WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
             ORDER BY `m`.`id` DESC
             LIMIT " . $cleanLimit . ";",
            __FILE__,
            __LINE__
        );

        while ($r = \p4c_fetch_object($rsRec)) {
            $rows[] = [
                'id'                => (int)$r->id,
                'merchant_id'       => (int)$r->merchant_id,
                'title'             => \aic_clean_html_text($r->title),
                'playtime_fmt'      => \aic_format_time((int)$r->playtime_seconds),
                'subtitle_status'   => $r->subtitle_status !== null ? (int)$r->subtitle_status : 0,
                'text_compliance'   => $r->text_compliance !== null ? (string)$r->text_compliance : 'pending',
                'detected_language' => $r->detected_language !== null ? (string)$r->detected_language : '',
            ];
        }
        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchHistoryRows(string $complianceFilter = 'all', string $subtitleFilter = 'all', string $searchQuery = '', int $limit = 150): array
    {
        $where = ['1=1'];

        if ($complianceFilter === 'violation' || $complianceFilter === 'warning' || $complianceFilter === 'ok') {
            $where[] = "`ai`.`text_compliance` = '" . \p4c_escape_string($complianceFilter) . "'";
        }
        if ($subtitleFilter === 'has_sub') {
            $where[] = "`ai`.`subtitle_status` = '2'";
        } elseif ($subtitleFilter === 'no_audio') {
            $where[] = "`ai`.`subtitle_status` = '3'";
        } elseif ($subtitleFilter === 'error') {
            $where[] = "`ai`.`subtitle_status` = '4'";
        } elseif ($subtitleFilter === 'open') {
            $where[] = "`ai`.`subtitle_status` = '0'";
        }

        $q = trim($searchQuery);
        if ($q !== '') {
            if (ctype_digit($q)) {
                $idNum   = abs((int)$q);
                $where[] = "(`ai`.`movie_id` = '" . $idNum . "' OR `ai`.`merchant_id` = '" . $idNum . "')";
            } else {
                $escQ    = \p4c_escape_string($q);
                $where[] = "(`m`.`title` LIKE '%" . $escQ . "%' OR `ai`.`text_summary` LIKE '%" . $escQ . "%' OR `ai`.`spoken_transcript` LIKE '%" . $escQ . "%' OR `ai`.`detected_language` = '" . $escQ . "')";
            }
        }

        $limitClean  = max(10, min(500, $limit));
        $historyRows = [];
        $rsHist      = \p4c_query(
            "SELECT `ai`.*, `m`.`title`, `m`.`playtime_seconds`
             FROM `movies_ai_analysis` `ai`
             LEFT JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
             WHERE " . implode(' AND ', $where) . "
             ORDER BY `ai`.`updated_at` DESC, `ai`.`movie_id` DESC
             LIMIT " . $limitClean . ";",
            __FILE__,
            __LINE__
        );

        while ($h = \p4c_fetch_object($rsHist)) {
            $historyRows[] = [
                'movie_id'             => (int)$h->movie_id,
                'merchant_id'          => (int)$h->merchant_id,
                'title'                => \aic_clean_html_text($h->title),
                'playtime_fmt'         => \aic_format_time((int)$h->playtime_seconds),
                'subtitle_status'      => (int)$h->subtitle_status,
                'detected_language'    => (string)$h->detected_language,
                'language_confidence'  => (float)$h->language_confidence,
                'subtitle_filename'    => (string)$h->subtitle_filename,
                'whisper_duration_sec' => (float)$h->whisper_duration_sec,
                'text_check_status'    => (int)$h->text_check_status,
                'text_compliance'      => (string)$h->text_compliance,
                'text_risk_score'      => (int)$h->text_risk_score,
                'text_summary'         => (string)$h->text_summary,
                'last_error'           => (string)$h->last_error,
                'updated_at'           => (string)$h->updated_at,
            ];
        }
        return $historyRows;
    }

    /**
     * @return array<string, int>
     */
    private function fetchContentCheckNavCounts(): array
    {
        $rsMov = \p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released` = '0' AND `convert_status` = '2' AND `status` != 'deleted';",
            __FILE__,
            __LINE__
        );
        $moviesCheck = $rsMov ? abs((int)(\p4c_fetch_object($rsMov)->cnt ?? 0)) : 0;

        $rsAlb = \p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `released` = '0' AND `status` != 'deleted';",
            __FILE__,
            __LINE__
        );
        $albumsCheck = $rsAlb ? abs((int)(\p4c_fetch_object($rsAlb)->cnt ?? 0)) : 0;

        $rsCo = \p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `co_actors` WHERE `checked_status` = 'under_review' AND `status` != 'deleted';",
            __FILE__,
            __LINE__
        );
        $coActorsCheck = $rsCo ? abs((int)(\p4c_fetch_object($rsCo)->cnt ?? 0)) : 0;

        $rsAi = \p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `movies_ai_analysis` WHERE `text_compliance` = 'violation';",
            __FILE__,
            __LINE__
        );
        $aiViolations = $rsAi ? abs((int)(\p4c_fetch_object($rsAi)->cnt ?? 0)) : 0;

        return [
            'movies_checking'    => $moviesCheck,
            'albums_checking'    => $albumsCheck,
            'co_actors_checking' => $coActorsCheck,
            'ai_violations'      => $aiViolations,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendRawJson(array $payload): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $clean = \aic_sanitize_Recursive($payload);
        $json  = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode([
                'status'  => 'error',
                'message' => 'JSON-Kodierungsfehler: ' . json_last_error_msg(),
            ]);
        }
        echo $json;
        exit;
    }
}
