<?php

/**
 * @author      Martin Zimmermann
 * @copyright   (C) 2016 adult net applications UG
 * @email       erocms@gmail.com
 *
 * AJAX-Backend fuer das ACP KI-Testlabor, Batch-/Cronjob-Cockpit & BRAM-Compliance (/KI-Analyse).
 * PHP 7.3 kompatibel.
 */

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));
include_once($sourcedir . "/config.inc.php");
include_once(ACP_DIR . "/common.inc.php");
include_once($sourcedir . "/includes/ai_compliance_core.inc.php");

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (is_logged_in('acp') !== true) {
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Nicht autorisiert. Bitte melden Sie sich im ACP an.'
    ));
    exit;
}

// Session-Lock sofort freigeben, damit parallele Status-Abfragen (poll_worker_status) waehrend eines Whisper-Laufs nicht blockieren
@session_write_close();

function aic_send_json($payload) {
    $clean = aic_sanitize_Recursive($payload);
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = json_encode(array(
            'status'  => 'error',
            'message' => 'JSON-Kodierungsfehler: ' . json_last_error_msg()
        ));
    }
    echo $json;
    exit;
}

/**
 * Laedt die Analyse-Historie mit optionalen Filtern (Ampel-Status, Untertitel-Status, Suchbegriff/ID).
 */
function aic_fetch_history_rows($compliance_filter = 'all', $subtitle_filter = 'all', $search_query = '', $limit = 150) {
    $where = array("1=1");

    if ($compliance_filter === 'violation' || $compliance_filter === 'warning' || $compliance_filter === 'ok') {
        $where[] = "`ai`.`text_compliance` = '" . p4c_escape_string($compliance_filter) . "'";
    }
    if ($subtitle_filter === 'has_sub') {
        $where[] = "`ai`.`subtitle_status` = '2'";
    } elseif ($subtitle_filter === 'no_audio') {
        $where[] = "`ai`.`subtitle_status` = '3'";
    } elseif ($subtitle_filter === 'error') {
        $where[] = "`ai`.`subtitle_status` = '4'";
    } elseif ($subtitle_filter === 'open') {
        $where[] = "`ai`.`subtitle_status` = '0'";
    }

    $q = trim((string)$search_query);
    if ($q !== '') {
        if (ctype_digit($q)) {
            $id_num = abs($q);
            $where[] = "(`ai`.`movie_id` = '" . $id_num . "' OR `ai`.`merchant_id` = '" . $id_num . "')";
        } else {
            $esc_q = p4c_escape_string($q);
            $where[] = "(`m`.`title` LIKE '%" . $esc_q . "%' OR `ai`.`text_summary` LIKE '%" . $esc_q . "%' OR `ai`.`spoken_transcript` LIKE '%" . $esc_q . "%' OR `ai`.`detected_language` = '" . $esc_q . "')";
        }
    }

    $limit_clean = max(10, min(500, (int)$limit));
    $history_rows = array();
    $rs_hist = p4c_query("SELECT `ai`.*, `m`.`title`, `m`.`playtime_seconds`
        FROM `movies_ai_analysis` `ai`
        LEFT JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
        WHERE " . implode(' AND ', $where) . "
        ORDER BY `ai`.`updated_at` DESC, `ai`.`movie_id` DESC
        LIMIT " . $limit_clean . ";", __FILE__, __LINE__);

    while ($h = p4c_fetch_object($rs_hist)) {
        $history_rows[] = array(
            'movie_id'            => (int)$h->movie_id,
            'merchant_id'         => (int)$h->merchant_id,
            'title'               => aic_clean_html_text($h->title),
            'playtime_fmt'        => aic_format_time((int)$h->playtime_seconds),
            'subtitle_status'     => (int)$h->subtitle_status,
            'detected_language'   => (string)$h->detected_language,
            'language_confidence' => (float)$h->language_confidence,
            'subtitle_filename'   => (string)$h->subtitle_filename,
            'whisper_duration_sec'=> (float)$h->whisper_duration_sec,
            'text_check_status'   => (int)$h->text_check_status,
            'text_compliance'     => (string)$h->text_compliance,
            'text_risk_score'     => (int)$h->text_risk_score,
            'text_summary'        => (string)$h->text_summary,
            'last_error'          => (string)$h->last_error,
            'updated_at'          => (string)$h->updated_at
        );
    }
    return $history_rows;
}

// ============================================================================
// HAUPT-ROUTING DER AJAX-AKTIONEN
// ============================================================================
aic_ensure_table();
$env    = aic_check_environment();
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'init_dashboard';

if ($action === 'init_dashboard') {
    $stats         = aic_get_catalog_and_batch_stats();
    $batch_config  = aic_load_batch_config();
    $worker_status = aic_get_worker_status();

    // Vorschlagsliste (neueste 25 konvertierte Creator-Filme)
    $recent_movies = array();
    $rs_rec = p4c_query("SELECT `m`.`id`, `m`.`merchant_id`, `m`.`title`, `m`.`playtime_seconds`, `m`.`released`, `m`.`status`,
            `ai`.`subtitle_status`, `ai`.`text_compliance`, `ai`.`detected_language`
        FROM `movies` `m`
        LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
        WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
        ORDER BY `m`.`id` DESC
        LIMIT 25;", __FILE__, __LINE__);
    while ($r = p4c_fetch_object($rs_rec)) {
        $recent_movies[] = array(
            'id'                => (int)$r->id,
            'merchant_id'       => (int)$r->merchant_id,
            'title'             => aic_clean_html_text($r->title),
            'playtime_fmt'      => aic_format_time((int)$r->playtime_seconds),
            'subtitle_status'   => $r->subtitle_status !== null ? (int)$r->subtitle_status : 0,
            'text_compliance'   => $r->text_compliance !== null ? (string)$r->text_compliance : 'pending',
            'detected_language' => $r->detected_language !== null ? (string)$r->detected_language : ''
        );
    }

    $history_rows    = aic_fetch_history_rows('all', 'all', '', 150);
    $rules_json_path = $sourcedir . '/includes/ai_rules/bram_keyword_rules.json';

    aic_send_json(array(
        'status'        => 'ok',
        'stats'         => $stats,
        'batch_config'  => $batch_config,
        'worker_status' => $worker_status,
        'env'           => $env,
        'recent_movies' => $recent_movies,
        'history'       => $history_rows,
        'rules'         => array(
            'system_prompt_md'   => aic_load_system_prompt(),
            'keyword_rules_json' => file_exists($rules_json_path) ? (string)@file_get_contents($rules_json_path) : '',
            'parsed_categories'  => aic_load_keyword_rules()
        )
    ));
}

if ($action === 'poll_worker_status') {
    $ws = aic_get_worker_status();
    $cur_movie_info = null;
    if (!empty($ws['current_movie_id'])) {
        $rs_cm = p4c_query("SELECT `id`, `title`, `playtime_seconds` FROM `movies` WHERE `id` = '" . abs($ws['current_movie_id']) . "' LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_cm) > 0) {
            $cm = p4c_fetch_object($rs_cm);
            $cur_movie_info = array(
                'id'           => (int)$cm->id,
                'title'        => aic_clean_html_text($cm->title),
                'playtime_fmt' => aic_format_time((int)$cm->playtime_seconds)
            );
        }
    }
    aic_send_json(array(
        'status'        => 'ok',
        'worker_status' => $ws,
        'current_movie' => $cur_movie_info
    ));
}

if ($action === 'filter_history') {
    $comp_filter = isset($_REQUEST['compliance_filter']) ? trim($_REQUEST['compliance_filter']) : 'all';
    $sub_filter  = isset($_REQUEST['subtitle_filter']) ? trim($_REQUEST['subtitle_filter']) : 'all';
    $search_q    = isset($_REQUEST['search_query']) ? trim($_REQUEST['search_query']) : '';

    $rows = aic_fetch_history_rows($comp_filter, $sub_filter, $search_q, 200);
    aic_send_json(array(
        'status'  => 'ok',
        'history' => $rows,
        'stats'   => aic_get_catalog_and_batch_stats()
    ));
}

if ($action === 'inspect_transcripts') {
    // Analysiert alle bisher vorhandenen Whisper-Transkripte & .vtt-Segmente in movies_ai_analysis
    $rs_tr = p4c_query("SELECT `ai`.`movie_id`, `ai`.`merchant_id`, `ai`.`subtitle_status`, `ai`.`detected_language`,
            `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`, `m`.`title`, `m`.`playtime_seconds`
        FROM `movies_ai_analysis` `ai`
        LEFT JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
        WHERE `ai`.`subtitle_status` IN (2, 3) OR (`ai`.`spoken_transcript` != '' AND `ai`.`spoken_transcript` IS NOT NULL)
        ORDER BY `ai`.`movie_id` DESC;", __FILE__, __LINE__);

    $lang_names = array(
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
        'jw' => 'Javanisch (Whisper-Artefakt)'
    );

    $total_transcribed       = 0;
    $total_silent            = 0;
    $total_only_brackets     = 0;
    $total_with_real_speech  = 0;
    $total_bracket_occ       = 0;

    $languages_map  = array();
    $brackets_map   = array();
    $phrases_map    = array();
    $no_dialog_list = array();

    while ($row = p4c_fetch_object($rs_tr)) {
        $mid      = (int)$row->movie_id;
        $sub_st   = (int)$row->subtitle_status;
        $lang_raw = strtolower(trim((string)$row->detected_language));
        if ($lang_raw === '') {
            $lang_raw = 'unbekannt';
        }
        $mtitle = aic_clean_html_text($row->title);

        if ($sub_st === 3) {
            $total_silent++;
            continue;
        }
        $total_transcribed++;

        // Segmente extrahieren
        $seg_texts = array();
        if (!empty($row->spoken_segments_json)) {
            $dec_s = @json_decode($row->spoken_segments_json, true);
            if (is_array($dec_s)) {
                foreach ($dec_s as $s_item) {
                    if (isset($s_item['text']) && trim((string)$s_item['text']) !== '') {
                        $seg_texts[] = trim(aic_utf8((string)$s_item['text']));
                    }
                }
            }
        }
        if (empty($seg_texts) && !empty($row->spoken_transcript)) {
            $raw_lines = explode("\n", aic_utf8((string)$row->spoken_transcript));
            foreach ($raw_lines as $rl) {
                $rl = trim($rl);
                if ($rl === '') continue;
                // Entferne Zeitstempel am Zeilenanfang wie [00:00 - 00:05]
                $rl_clean = preg_replace('/^\[\d{2,}:\d{2}(?:\.\d+)?\s*-\s*\d{2,}:\d{2}(?:\.\d+)?\]\s*/', '', $rl);
                if (trim($rl_clean) !== '') {
                    $seg_texts[] = trim($rl_clean);
                }
            }
        }

        $movie_has_real_speech = false;
        $movie_seen_brackets   = array();
        $movie_seen_phrases    = array();

        foreach ($seg_texts as $st) {
            // Ignoriere unseren eigenen Platzhalter fuer Stummfilme / keinen Dialog
            if (stripos($st, 'Kein gesprochener Dialog') !== false || stripos($st, 'besitzt keine Tonspur') !== false) {
                continue;
            }

            // Suche alle Klammer-Ausdruecke [...] und (...)
            $matches_b = array();
            if (preg_match_all('/(\[[^\]]+\]|\([^\)]+\))/u', $st, $m_all)) {
                $matches_b = $m_all[1];
            }

            foreach ($matches_b as $b_tag) {
                $b_norm = trim($b_tag);
                if ($b_norm === '') continue;
                // Zeitstempel-Klammern überspringen falls noch vorhanden
                if (preg_match('/^\[\d{2,}:\d{2}/', $b_norm)) continue;

                $total_bracket_occ++;
                if (!isset($brackets_map[$b_norm])) {
                    // Automatische Kategorisierungs-Empfehlung ermitteln
                    $b_low = mb_strtolower($b_norm, 'UTF-8');
                    $cat_type  = 'review';
                    $cat_label = 'Manuell prüfen';

                    if (preg_match('/(musik|music|lachen|laugh|stöhn|moan|seufz|sigh|applaus|klatsch|atmen|breath|stille|silence|schrei|scream|weinen|cry|stöhnt|lacht)/iu', $b_low)) {
                        $cat_type  = 'keep_atmosphere';
                        $cat_label = 'Atmosphäre / Closed Caption (Behalten)';
                    } elseif (preg_match('/(ɑ|ː|ə|ʊ|ɔ|ɛ|ɪ|ʃ|ʒ|\'[a-z]|bɑ|mmm+|ahh+|ohh+|uhh+|hmm+)/iu', $b_low)) {
                        $cat_type  = 'convert_moan';
                        $cat_label = 'Lautschrift / Stöhn-Laut (-> in [Stöhnen] umwandeln)';
                    } elseif (preg_match('/(giliv|li-cc|cc|subtitles|untertitel|amara|copyright|translated|captioned|blank_audio|inaudible|unverständlich|geräusch)/iu', $b_low)) {
                        $cat_type  = 'remove_artifact';
                        $cat_label = 'Whisper-Artefakt / Halluzination (-> Rausfiltern)';
                    } else {
                        $cat_type  = 'remove_artifact';
                        $cat_label = 'Unbekannter Klammer-Tag (-> Prüfen / Rausfiltern)';
                    }

                    $brackets_map[$b_norm] = array(
                        'tag'               => $b_norm,
                        'total_occurrences' => 0,
                        'movie_count'       => 0,
                        'cat_type'          => $cat_type,
                        'cat_label'         => $cat_label,
                        'sample_movies'     => array()
                    );
                }
                $brackets_map[$b_norm]['total_occurrences']++;
                if (!isset($movie_seen_brackets[$b_norm])) {
                    $movie_seen_brackets[$b_norm] = true;
                    $brackets_map[$b_norm]['movie_count']++;
                    if (count($brackets_map[$b_norm]['sample_movies']) < 6) {
                        $brackets_map[$b_norm]['sample_movies'][] = array(
                            'id'    => $mid,
                            'title' => $mtitle
                        );
                    }
                }
            }

            // Pruefen, ob ausserhalb von Klammern noch echter gesprochener Text in dieser Zeile steht
            $without_brackets = trim(preg_replace('/(\[[^\]]*\]|\([^\)]*\))/u', '', $st));
            // Entferne reine Satzzeichen/Symbole
            $only_letters = preg_replace('/[^\p{L}\p{N}]+/u', '', $without_brackets);

            if (mb_strlen($only_letters, 'UTF-8') >= 2) {
                $movie_has_real_speech = true;

                // Fuer Top-Phrasen / Halluzinations-Check speichern
                $p_norm = trim($without_brackets);
                if (mb_strlen($p_norm, 'UTF-8') <= 90) {
                    if (!isset($phrases_map[$p_norm])) {
                        $phrases_map[$p_norm] = array(
                            'phrase'            => $p_norm,
                            'total_occurrences' => 0,
                            'movie_count'       => 0,
                            'sample_movies'     => array()
                        );
                    }
                    $phrases_map[$p_norm]['total_occurrences']++;
                    if (!isset($movie_seen_phrases[$p_norm])) {
                        $movie_seen_phrases[$p_norm] = true;
                        $phrases_map[$p_norm]['movie_count']++;
                        if (count($phrases_map[$p_norm]['sample_movies']) < 5) {
                            $phrases_map[$p_norm]['sample_movies'][] = array(
                                'id'    => $mid,
                                'title' => $mtitle
                            );
                        }
                    }
                }
            }
        }

        if ($movie_has_real_speech) {
            $total_with_real_speech++;
        } else {
            $total_only_brackets++;
            if (count($no_dialog_list) < 50) {
                $preview_str = implode(' | ', array_slice($seg_texts, 0, 4));
                if (mb_strlen($preview_str, 'UTF-8') > 140) {
                    $preview_str = mb_substr($preview_str, 0, 140, 'UTF-8') . '...';
                }
                $no_dialog_list[] = array(
                    'movie_id'          => $mid,
                    'merchant_id'       => (int)$row->merchant_id,
                    'title'             => $mtitle,
                    'detected_language' => strtoupper($lang_raw),
                    'segment_count'     => count($seg_texts),
                    'preview'           => $preview_str
                );
            }
        }

        // Sprach-Statistik aktualisieren
        if (!isset($languages_map[$lang_raw])) {
            $languages_map[$lang_raw] = array(
                'code'               => strtoupper($lang_raw),
                'code_lower'         => $lang_raw,
                'name'               => isset($lang_names[$lang_raw]) ? $lang_names[$lang_raw] : ('ISO-Code: ' . strtoupper($lang_raw)),
                'movie_count'        => 0,
                'real_speech_count'  => 0,
                'only_brackets_count'=> 0,
                'sample_movies'      => array()
            );
        }
        $languages_map[$lang_raw]['movie_count']++;
        if ($movie_has_real_speech) {
            $languages_map[$lang_raw]['real_speech_count']++;
        } else {
            $languages_map[$lang_raw]['only_brackets_count']++;
        }
        if (count($languages_map[$lang_raw]['sample_movies']) < 6) {
            $languages_map[$lang_raw]['sample_movies'][] = array(
                'id'          => $mid,
                'title'       => $mtitle,
                'only_noises' => !$movie_has_real_speech
            );
        }
    }

    // Sortieren nach Haeufigkeit
    $languages_list = array_values($languages_map);
    usort($languages_list, function($a, $b) {
        return $b['movie_count'] - $a['movie_count'];
    });

    $brackets_list = array_values($brackets_map);
    usort($brackets_list, function($a, $b) {
        if ($b['total_occurrences'] !== $a['total_occurrences']) {
            return $b['total_occurrences'] - $a['total_occurrences'];
        }
        return $b['movie_count'] - $a['movie_count'];
    });

    // Bei Phrasen nur solche zeigen, die mindestens 2x vorkommen
    $phrases_filtered = array();
    foreach ($phrases_map as $pm) {
        if ($pm['total_occurrences'] >= 2) {
            $phrases_filtered[] = $pm;
        }
    }
    usort($phrases_filtered, function($a, $b) {
        if ($b['movie_count'] !== $a['movie_count']) {
            return $b['movie_count'] - $a['movie_count'];
        }
        return $b['total_occurrences'] - $a['total_occurrences'];
    });
    $phrases_list = array_slice($phrases_filtered, 0, 40);

    aic_send_json(array(
        'status' => 'ok',
        'summary' => array(
            'total_transcribed'      => $total_transcribed,
            'total_silent'           => $total_silent,
            'total_with_real_speech' => $total_with_real_speech,
            'total_only_brackets'    => $total_only_brackets,
            'unique_bracket_tags'    => count($brackets_list),
            'total_bracket_occ'      => $total_bracket_occ
        ),
        'languages'        => $languages_list,
        'bracket_tags'     => array_slice($brackets_list, 0, 100),
        'repeated_phrases' => $phrases_list,
        'no_dialog_movies' => $no_dialog_list
    ));
}

if ($action === 'save_batch_config') {
    $new_cfg = array(
        'cron_enabled'            => !empty($_POST['cron_enabled']),
        'task_mode'               => isset($_POST['task_mode']) ? trim($_POST['task_mode']) : 'whisper_and_text',
        'whisper_model_pref'      => isset($_POST['whisper_model_pref']) ? trim($_POST['whisper_model_pref']) : 'small',
        'whisper_threads'         => isset($_POST['whisper_threads']) ? (int)$_POST['whisper_threads'] : 12,
        'audio_limit_sec'         => isset($_POST['audio_limit_sec']) ? (int)$_POST['audio_limit_sec'] : 0,
        'order_direction'         => isset($_POST['order_direction']) ? trim($_POST['order_direction']) : 'DESC',
        'pause_on_ffmpeg_convert' => !empty($_POST['pause_on_ffmpeg_convert']),
        'max_movies_per_run'      => isset($_POST['max_movies_per_run']) ? (int)$_POST['max_movies_per_run'] : 2,
        'sync_empty_movie_lang'   => !empty($_POST['sync_empty_movie_lang'])
    );

    $saved = aic_save_batch_config($new_cfg);
    aic_send_json(array(
        'status'       => 'ok',
        'message'      => 'Die Batch- und Cronjob-Einstellungen wurden erfolgreich gespeichert.',
        'batch_config' => $saved,
        'env'          => aic_check_environment()
    ));
}

if ($action === 'run_batch_step') {
    // Verarbeitet genau 1 naechstes offenes Video (Whisper + .vtt + BRAM-Check) fuer den Live-Batch im Browser
    $batch_cfg = aic_load_batch_config();
    $order_dir = (strtoupper($batch_cfg['order_direction']) === 'ASC') ? 'ASC' : 'DESC';

    $rs_next = p4c_query("SELECT `m`.`id`, `m`.`title`
        FROM `movies` `m`
        LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
        WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
          AND (`ai`.`movie_id` IS NULL OR `ai`.`subtitle_status` = '0')
        ORDER BY `m`.`id` " . $order_dir . "
        LIMIT 1;", __FILE__, __LINE__);

    if (p4c_num_rows($rs_next) === 0) {
        aic_send_json(array(
            'status'        => 'ok',
            'has_more'      => false,
            'message'       => 'Alle konvertierten Videos im Katalog besitzen bereits einen Audio-/Untertitel-Status.',
            'stats'         => aic_get_catalog_and_batch_stats(),
            'worker_status' => aic_get_worker_status()
        ));
    }

    $next_obj = p4c_fetch_object($rs_next);
    $mid      = (int)$next_obj->id;
    $mtitle   = aic_clean_html_text($next_obj->title);

    aic_update_worker_status(array(
        'state'            => 'running',
        'current_movie_id' => $mid,
        'last_run_at'      => date('Y-m-d H:i:s'),
        'last_message'     => 'Browser-Batch verarbeitet Video #' . $mid . ' (' . $mtitle . ')...'
    ));

    $t0  = microtime(true);
    $res = aic_process_single_movie_pipeline($mid, $env, array(
        'audio_limit_sec' => (int)$batch_cfg['audio_limit_sec'],
        'whisper_threads' => (int)$batch_cfg['whisper_threads'],
        'run_text_after'  => true
    ));
    $dur = round(microtime(true) - $t0, 2);

    $comp = isset($res['movie']['ai']['text_compliance']) ? strtoupper($res['movie']['ai']['text_compliance']) : 'OK';
    $lang = isset($res['movie']['ai']['detected_language']) ? strtoupper($res['movie']['ai']['detected_language']) : '-';
    $msg  = 'Video #' . $mid . ' (' . $mtitle . ') abgeschlossen -> Sprache: ' . $lang . ' | BRAM: ' . $comp . ' (' . $dur . ' Sek.)';

    $cur_status = aic_get_worker_status();
    $w_status   = aic_update_worker_status(array(
        'state'                => 'idle',
        'current_movie_id'     => 0,
        'last_movie_id'        => $mid,
        'last_movie_title'     => $mtitle,
        'last_run_at'          => date('Y-m-d H:i:s'),
        'last_duration_sec'    => $dur,
        'last_message'         => $msg,
        'total_processed_runs' => ((int)$cur_status['total_processed_runs']) + 1
    ));

    $stats = aic_get_catalog_and_batch_stats();

    aic_send_json(array(
        'status'          => 'ok',
        'has_more'        => ($stats['remaining_subtitles'] > 0),
        'message'         => $msg,
        'processed_movie' => $res['movie'],
        'stats'           => $stats,
        'worker_status'   => $w_status
    ));
}

if ($action === 'run_bulk_text_precheck') {
    // Prüft in einem schnellen Durchlauf bis zu 250 Filme auf Basis von Titel, Beschreibung, SEO & evtl. vorhandenem Transkript
    @set_time_limit(120);
    $chunk_size   = isset($_POST['chunk_size']) ? max(10, min(500, (int)$_POST['chunk_size'])) : 250;
    $force_recheck = !empty($_POST['force_recheck']);
    $last_id      = isset($_POST['last_id']) ? abs($_POST['last_id']) : 0;

    if ($force_recheck) {
        // Bei Komplett-Neubewertung (z.B. nach Regelwerks-Änderung) per Cursor (m.id < last_id) durch alle Videos laufen
        $where_cursor = ($last_id > 0) ? (" AND `m`.`id` < '" . $last_id . "' ") : "";
        $sql_chunk = "SELECT `m`.`id`, `m`.`merchant_id`, `m`.`file_id`, `m`.`title`, `m`.`description`, `m`.`meta_title`, `m`.`meta_description`,
                `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`
            FROM `movies` `m`
            LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
            WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted' " . $where_cursor . "
            ORDER BY `m`.`id` DESC
            LIMIT " . $chunk_size . ";";
    } else {
        // Nur Filme prüfen, die noch keinen Text-Check (text_check_status = 0 oder NULL) haben
        $sql_chunk = "SELECT `m`.`id`, `m`.`merchant_id`, `m`.`file_id`, `m`.`title`, `m`.`description`, `m`.`meta_title`, `m`.`meta_description`,
                `ai`.`spoken_transcript`, `ai`.`spoken_segments_json`
            FROM `movies` `m`
            LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
            WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted'
              AND (`ai`.`movie_id` IS NULL OR `ai`.`text_check_status` = '0')
            ORDER BY `m`.`id` DESC
            LIMIT " . $chunk_size . ";";
    }

    $rs_chunk = p4c_query($sql_chunk, __FILE__, __LINE__);
    $processed_in_chunk = 0;
    $new_last_id = 0;
    $now = date('Y-m-d H:i:s');

    // Bei Massen-Vorabscan nur die deterministische BRAM-Regel-Engine nutzen, damit 250 Filme in < 1 Sekunde fertig sind
    $fast_env = $env;
    $fast_env['llm_ready'] = false;

    while ($row = p4c_fetch_object($rs_chunk)) {
        $mid = (int)$row->id;
        $new_last_id = $mid;
        $segs = array();
        if (!empty($row->spoken_segments_json)) {
            $dec = @json_decode($row->spoken_segments_json, true);
            if (is_array($dec)) {
                $segs = $dec;
            }
        }
        $spk_txt = !empty($row->spoken_transcript) ? (string)$row->spoken_transcript : '';

        $rep = aic_analyze_texts(array(
            'title'            => $row->title,
            'description'      => $row->description,
            'meta_title'       => isset($row->meta_title) ? $row->meta_title : '',
            'meta_description' => isset($row->meta_description) ? $row->meta_description : ''
        ), $segs, $spk_txt, $fast_env);

        $viol_json = json_encode($rep['violations'], JSON_UNESCAPED_UNICODE);
        $sub_file  = 'sub_' . $mid . '_' . $row->file_id . '.vtt';

        p4c_query("INSERT INTO `movies_ai_analysis`
            (`movie_id`, `merchant_id`, `subtitle_filename`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
            VALUES (
                '" . abs($mid) . "',
                '" . abs($row->merchant_id) . "',
                '" . p4c_escape_string($sub_file) . "',
                '2',
                '" . p4c_escape_string($rep['compliance_status']) . "',
                '" . abs($rep['risk_score']) . "',
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
                `text_risk_score`      = '" . abs($rep['risk_score']) . "',
                `text_engine_used`     = '" . p4c_escape_string($rep['engine_used']) . "',
                `text_summary`         = '" . p4c_escape_string($rep['summary_de']) . "',
                `text_violations_json` = '" . p4c_escape_string($viol_json) . "',
                `text_duration_sec`    = '" . p4c_escape_string($rep['duration_sec']) . "',
                `updated_at`           = '" . $now . "';", __FILE__, __LINE__);

        $processed_in_chunk++;
    }

    $stats = aic_get_catalog_and_batch_stats();
    $has_more = $force_recheck ? ($processed_in_chunk >= $chunk_size) : ($stats['remaining_text'] > 0 && $processed_in_chunk > 0);

    aic_send_json(array(
        'status'             => 'ok',
        'processed_in_chunk' => $processed_in_chunk,
        'last_id'            => $new_last_id,
        'has_more'           => $has_more,
        'stats'              => $stats
    ));
}

if ($action === 'load_movie') {
    $movie_id    = isset($_REQUEST['movie_id']) ? abs($_REQUEST['movie_id']) : 0;
    $merchant_id = isset($_REQUEST['merchant_id']) ? abs($_REQUEST['merchant_id']) : 0;
    $mode        = isset($_REQUEST['mode']) ? trim($_REQUEST['mode']) : '';

    if ($mode === 'next_untested') {
        $where_m = ($merchant_id > 0) ? " AND `m`.`merchant_id` = '" . $merchant_id . "' " : "";
        $rs_next = p4c_query("SELECT `m`.`id`
            FROM `movies` `m`
            LEFT JOIN `movies_ai_analysis` `ai` ON `ai`.`movie_id` = `m`.`id`
            WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted' " . $where_m . "
              AND (`ai`.`movie_id` IS NULL OR `ai`.`subtitle_status` = '0')
            ORDER BY `m`.`id` DESC
            LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_next) > 0) {
            $movie_id = (int)p4c_fetch_object($rs_next)->id;
        }
    } elseif ($mode === 'find_interesting') {
        $rs_scan = p4c_query("SELECT `id`, `title`, `description`, `meta_title`, `meta_description`
            FROM `movies`
            WHERE `convert_status` = '2' AND `status` != 'deleted'
            ORDER BY `id` DESC
            LIMIT 400;", __FILE__, __LINE__);
        $fallback_id = 0;
        while ($row = p4c_fetch_object($rs_scan)) {
            if ($fallback_id === 0) {
                $fallback_id = (int)$row->id;
            }
            $res = aic_analyze_texts(array(
                'title'            => $row->title,
                'description'      => $row->description,
                'meta_title'       => isset($row->meta_title) ? $row->meta_title : '',
                'meta_description' => isset($row->meta_description) ? $row->meta_description : ''
            ), array(), '', array('llm_ready' => false));
            if ($res['compliance_status'] !== 'ok') {
                $movie_id = (int)$row->id;
                break;
            }
        }
        if ($movie_id === 0) {
            $movie_id = $fallback_id;
        }
    } elseif ($movie_id === 0 && $merchant_id > 0) {
        $rs_m = p4c_query("SELECT `id` FROM `movies` WHERE `merchant_id` = '" . $merchant_id . "' AND `convert_status` = '2' AND `status` != 'deleted' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_m) > 0) {
            $movie_id = (int)p4c_fetch_object($rs_m)->id;
        }
    }

    $details = aic_get_movie_details($movie_id);
    if (!$details) {
        aic_send_json(array(
            'status'  => 'error',
            'message' => 'Es wurde kein passendes konvertiertes Video zur angegebenen ID gefunden.'
        ));
    }

    aic_send_json(array(
        'status' => 'ok',
        'movie'  => $details
    ));
}

if ($action === 'run_whisper') {
    $movie_id        = isset($_POST['movie_id']) ? abs($_POST['movie_id']) : 0;
    $sim_scenario    = isset($_POST['sim_scenario']) ? trim($_POST['sim_scenario']) : 'clean';
    $audio_limit_sec = isset($_POST['audio_limit_sec']) ? abs($_POST['audio_limit_sec']) : 120;
    $details         = aic_get_movie_details($movie_id);

    if (!$details) {
        aic_send_json(array('status' => 'error', 'message' => 'Ungültige Film-ID.'));
    }

    if ($details['mp4_exists'] && $sim_scenario === 'clean') {
        $res = aic_process_single_movie_pipeline($movie_id, $env, array(
            'audio_limit_sec' => $audio_limit_sec,
            'whisper_threads' => 16,
            'run_text_after'  => false
        ));
        if ($res['status'] !== 'ok') {
            aic_send_json(array(
                'status'  => 'error',
                'message' => isset($res['movie']['ai']['last_error']) ? $res['movie']['ai']['last_error'] : 'Fehler bei der Audio-Extraktion.'
            ));
        }
        aic_send_json(array(
            'status'    => 'ok',
            'mode_used' => $res['mode_used'],
            'movie'     => $res['movie']
        ));
    }

    // Test- & Simulationsmodus (nur wenn explizit ein Test-Szenario gewaehlt wurde oder lokal ohne MP4)
    $t0 = microtime(true);
    $mode_used = 'Testlabor-Simulation (Whisper-Vorschau)';
    $sub_status = 2;

    if ($sim_scenario === 'bram_violation') {
        $detected_lang = 'de';
        $lang_conf     = 99.10;
        $segments = array(
            array('start' => 4.0,  'end' => 11.5, 'text' => 'Hey, schön dass du einschaltest zu meinem neuen privaten Video.'),
            array('start' => 18.0, 'end' => 27.0, 'text' => 'In diesem Rollenspiel spiele ich deine kleine Stiefschwester, die gerade erst im Kinderzimmer aufgewacht ist.'),
            array('start' => 35.0, 'end' => 44.0, 'text' => 'Du hast mir heimlich K.O.-Tropfen ins Glas gemischt, sodass ich völlig willenlos und betäubt bin.'),
            array('start' => 52.0, 'end' => 61.0, 'text' => 'Wenn du noch mehr unzensierte Videos willst, schreib mir einfach auf Telegram oder schick mir Geld per PayPal!')
        );
    } elseif ($sim_scenario === 'bram_warning') {
        $detected_lang = 'de';
        $lang_conf     = 97.80;
        $segments = array(
            array('start' => 3.0,  'end' => 10.0, 'text' => 'Hallo Schatz, heute wird es etwas wilder bei uns im Schlafzimmer.'),
            array('start' => 15.0, 'end' => 24.5, 'text' => 'Ich habe mich als deine Stiefmutter verkleidet und werde jetzt gefesselt.'),
            array('start' => 30.0, 'end' => 41.0, 'text' => 'Danach probieren wir noch ein bisschen Natursekt und KV unter der Dusche aus.')
        );
    } elseif ($sim_scenario === 'english_clean') {
        $detected_lang = 'en';
        $lang_conf     = 98.90;
        $segments = array(
            array('start' => 2.0,  'end' => 9.0,  'text' => 'Welcome to my new exclusive clip, I hope you enjoy watching me relax today.'),
            array('start' => 12.0, 'end' => 21.0, 'text' => 'I put on my favorite red lingerie just for you, let us have some consensual fun together.')
        );
    } else {
        $detected_lang = 'de';
        $lang_conf     = 98.60;
        $segments = array(
            array('start' => 2.5,  'end' => 9.0,  'text' => 'Hallo ihr Lieben, willkommen zu meinem neuen Video hier auf meinem Profil!'),
            array('start' => 11.0, 'end' => 19.5, 'text' => 'Ich habe mir heute meine neuen Dessous angezogen und freue mich auf eine heiße Zeit mit dir.'),
            array('start' => 22.0, 'end' => 31.0, 'text' => 'Lehn dich zurück und genieße die Show in voller Länge.')
        );
    }

    $lines = array();
    foreach ($segments as $seg) {
        $lines[] = '[' . aic_format_time($seg['start']) . ' - ' . aic_format_time($seg['end']) . '] ' . $seg['text'];
    }
    $transcript = implode("\n", $lines);

    if (@is_dir($details['movie_dir'])) {
        $vtt_content = aic_build_vtt_content($segments, $detected_lang);
        @file_put_contents($details['movie_dir'] . '/' . $details['ai']['subtitle_filename'], $vtt_content);
    }

    $duration = max(0.12, round(microtime(true) - $t0, 2));
    $now      = date('Y-m-d H:i:s');
    $seg_json = json_encode($segments, JSON_UNESCAPED_UNICODE);
    $sub_file = $details['ai']['subtitle_filename'];

    p4c_query("INSERT INTO `movies_ai_analysis`
        (`movie_id`, `merchant_id`, `subtitle_status`, `detected_language`, `language_confidence`, `subtitle_filename`, `spoken_transcript`, `spoken_segments_json`, `whisper_duration_sec`, `created_at`, `updated_at`)
        VALUES (
            '" . abs($details['id']) . "',
            '" . abs($details['merchant_id']) . "',
            '" . abs($sub_status) . "',
            '" . p4c_escape_string($detected_lang) . "',
            '" . p4c_escape_string($lang_conf) . "',
            '" . p4c_escape_string($sub_file) . "',
            '" . p4c_escape_string($transcript) . "',
            '" . p4c_escape_string($seg_json) . "',
            '" . p4c_escape_string($duration) . "',
            '" . $now . "',
            '" . $now . "'
        )
        ON DUPLICATE KEY UPDATE
            `subtitle_status`      = '" . abs($sub_status) . "',
            `detected_language`    = '" . p4c_escape_string($detected_lang) . "',
            `language_confidence`  = '" . p4c_escape_string($lang_conf) . "',
            `subtitle_filename`    = '" . p4c_escape_string($sub_file) . "',
            `spoken_transcript`    = '" . p4c_escape_string($transcript) . "',
            `spoken_segments_json` = '" . p4c_escape_string($seg_json) . "',
            `whisper_duration_sec` = '" . p4c_escape_string($duration) . "',
            `updated_at`           = '" . $now . "';", __FILE__, __LINE__);

    aic_send_json(array(
        'status'    => 'ok',
        'mode_used' => $mode_used,
        'movie'     => aic_get_movie_details($details['id'])
    ));
}

if ($action === 'run_text_check') {
    $movie_id          = isset($_POST['movie_id']) ? abs($_POST['movie_id']) : 0;
    $custom_transcript = isset($_POST['custom_transcript']) ? trim($_POST['custom_transcript']) : null;
    $custom_title      = isset($_POST['custom_title']) ? trim($_POST['custom_title']) : null;
    $custom_desc       = isset($_POST['custom_desc']) ? trim($_POST['custom_desc']) : null;

    $details = aic_get_movie_details($movie_id);
    if (!$details) {
        aic_send_json(array('status' => 'error', 'message' => 'Ungültige Film-ID.'));
    }

    $transcript_to_check = ($custom_transcript !== null) ? $custom_transcript : $details['ai']['spoken_transcript'];
    $segments_to_check   = $details['ai']['segments'];

    if ($custom_transcript !== null) {
        $parsed_segs = array();
        $lines = explode("\n", $custom_transcript);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\[(\d+):(\d+)\s*-\s*(\d+):(\d+)\]\s*(.+)$/', $line, $m_ts)) {
                $s_sec = ((int)$m_ts[1] * 60) + (int)$m_ts[2];
                $e_sec = ((int)$m_ts[3] * 60) + (int)$m_ts[4];
                $parsed_segs[] = array('start' => $s_sec, 'end' => $e_sec, 'text' => $m_ts[5]);
            } else {
                $parsed_segs[] = array('start' => 0, 'end' => 0, 'text' => $line);
            }
        }
        $segments_to_check = $parsed_segs;
    }

    $movie_check_input = array(
        'title'            => ($custom_title !== null) ? $custom_title : $details['title'],
        'description'      => ($custom_desc !== null) ? $custom_desc : $details['description'],
        'meta_title'       => $details['meta_title'],
        'meta_description' => $details['meta_description']
    );

    $report = aic_analyze_texts($movie_check_input, $segments_to_check, $transcript_to_check, $env);

    $now       = date('Y-m-d H:i:s');
    $viol_json = json_encode($report['violations'], JSON_UNESCAPED_UNICODE);
    $seg_json  = json_encode($segments_to_check, JSON_UNESCAPED_UNICODE);

    p4c_query("INSERT INTO `movies_ai_analysis`
        (`movie_id`, `merchant_id`, `spoken_transcript`, `spoken_segments_json`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
        VALUES (
            '" . abs($details['id']) . "',
            '" . abs($details['merchant_id']) . "',
            '" . p4c_escape_string($transcript_to_check) . "',
            '" . p4c_escape_string($seg_json) . "',
            '2',
            '" . p4c_escape_string($report['compliance_status']) . "',
            '" . abs($report['risk_score']) . "',
            '" . p4c_escape_string($report['engine_used']) . "',
            '" . p4c_escape_string($report['summary_de']) . "',
            '" . p4c_escape_string($viol_json) . "',
            '" . p4c_escape_string($report['duration_sec']) . "',
            '" . $now . "',
            '" . $now . "'
        )
        ON DUPLICATE KEY UPDATE
            `spoken_transcript`    = '" . p4c_escape_string($transcript_to_check) . "',
            `spoken_segments_json` = '" . p4c_escape_string($seg_json) . "',
            `text_check_status`    = '2',
            `text_compliance`      = '" . p4c_escape_string($report['compliance_status']) . "',
            `text_risk_score`      = '" . abs($report['risk_score']) . "',
            `text_engine_used`     = '" . p4c_escape_string($report['engine_used']) . "',
            `text_summary`         = '" . p4c_escape_string($report['summary_de']) . "',
            `text_violations_json` = '" . p4c_escape_string($viol_json) . "',
            `text_duration_sec`    = '" . p4c_escape_string($report['duration_sec']) . "',
            `updated_at`           = '" . $now . "';", __FILE__, __LINE__);

    aic_send_json(array(
        'status' => 'ok',
        'report' => $report,
        'movie'  => aic_get_movie_details($details['id'])
    ));
}

if ($action === 'save_rules') {
    $prompt_md  = isset($_POST['system_prompt_md']) ? (string)$_POST['system_prompt_md'] : '';
    $rules_json = isset($_POST['keyword_rules_json']) ? (string)$_POST['keyword_rules_json'] : '';

    if (trim($rules_json) !== '') {
        $test_decode = @json_decode($rules_json, true);
        if (!is_array($test_decode) || !isset($test_decode['categories'])) {
            aic_send_json(array(
                'status'  => 'error',
                'message' => 'Die JSON-Wortliste enthält einen Syntaxfehler. Bitte prüfen Sie Kommas und Anführungszeichen.'
            ));
        }
        @file_put_contents($sourcedir . '/includes/ai_rules/bram_keyword_rules.json', $rules_json);
    }

    if (trim($prompt_md) !== '') {
        @file_put_contents($sourcedir . '/includes/ai_rules/bram_system_prompt.md', $prompt_md);
    }

    aic_send_json(array(
        'status'            => 'ok',
        'message'           => 'Das BRAM-Regelwerk und der KI-System-Prompt wurden erfolgreich gespeichert.',
        'parsed_categories' => json_decode($rules_json, true)
    ));
}

aic_send_json(array(
    'status'  => 'error',
    'message' => 'Unbekannte Aktion: ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8')
));
?>
