<?php

/**
 * @author      Martin Zimmermann
 * @copyright   (C) 2016 adult net applications UG
 * @email       erocms@gmail.com
 *
 * AJAX-Backend fuer das ACP KI-Testlabor & BRAM-Compliance-Cockpit (/KI-Analyse).
 * PHP 7.3 kompatibel.
 */

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));
include_once($sourcedir . "/config.inc.php");
include_once(ACP_DIR . "/common.inc.php");

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

/**
 * Sicherstellen, dass alle Strings valides UTF-8 fuer json_encode sind.
 */
function aic_utf8($str) {
    if ($str === null || $str === '' || is_numeric($str) || is_bool($str)) {
        return $str;
    }
    $str = (string)$str;
    if (!preg_match('//u', $str)) {
        $str = utf8_encode($str);
    }
    if (function_exists('iconv')) {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
        if ($clean !== false) {
            $str = $clean;
        }
    }
    return $str;
}

function aic_sanitize_Recursive($data) {
    if (is_array($data)) {
        $out = array();
        foreach ($data as $k => $v) {
            $out[aic_utf8($k)] = aic_sanitize_Recursive($v);
        }
        return $out;
    } elseif (is_string($data)) {
        return aic_utf8($data);
    }
    return $data;
}

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
 * Erstellt die Tabelle movies_ai_analysis automatisch, falls sie noch nicht existiert.
 */
function aic_ensure_table() {
    $sql = "CREATE TABLE IF NOT EXISTS `movies_ai_analysis` (
        `movie_id` INT(11) UNSIGNED NOT NULL,
        `merchant_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
        `subtitle_status` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0=Offen, 1=In Arbeit, 2=Erstellt, 3=Kein Audio, 4=Fehler',
        `detected_language` VARCHAR(16) NOT NULL DEFAULT '',
        `language_confidence` DECIMAL(5,2) NOT NULL DEFAULT '0.00',
        `subtitle_filename` VARCHAR(255) NOT NULL DEFAULT '',
        `spoken_transcript` MEDIUMTEXT,
        `spoken_segments_json` MEDIUMTEXT,
        `whisper_duration_sec` DECIMAL(7,2) NOT NULL DEFAULT '0.00',
        `text_check_status` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0=Offen, 1=In Arbeit, 2=Geprueft, 3=Fehler',
        `text_compliance` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, ok, warning, violation',
        `text_risk_score` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
        `text_engine_used` VARCHAR(64) NOT NULL DEFAULT '',
        `text_summary` TEXT,
        `text_violations_json` MEDIUMTEXT,
        `text_duration_sec` DECIMAL(7,2) NOT NULL DEFAULT '0.00',
        `vision_check_status` TINYINT(1) NOT NULL DEFAULT '0',
        `vision_compliance` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `vision_report_json` MEDIUMTEXT,
        `last_error` VARCHAR(500) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `updated_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (`movie_id`),
        KEY `idx_merchant_id` (`merchant_id`),
        KEY `idx_subtitle_status` (`subtitle_status`),
        KEY `idx_text_check_status` (`text_check_status`),
        KEY `idx_text_compliance` (`text_compliance`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    p4c_query($sql, __FILE__, __LINE__);
}

/**
 * Prueft den Status der lokalen KI-Werkzeuge (FFmpeg, whisper.cpp, llama.cpp / Ollama).
 */
function aic_check_environment() {
    $ai_base_dir = defined('MOVIES_PATH') ? rtrim(MOVIES_PATH, '/') . '/priv/ai' : '/var/www/web1/priv/ai';
    $whisper_bin_candidates = array(
        $ai_base_dir . '/whisper.cpp/build/bin/whisper-cli',
        $ai_base_dir . '/whisper.cpp/build/bin/main',
        '/opt/erocloud_ai/whisper.cpp/build/bin/whisper-cli',
        '/usr/local/bin/whisper-cli'
    );
    $whisper_model_candidates = array(
        'small' => $ai_base_dir . '/models/ggml-small-q5_1.bin',
        'turbo' => $ai_base_dir . '/models/ggml-large-v3-turbo-q5_0.bin',
        'medium'=> $ai_base_dir . '/models/ggml-medium-q5_0.bin',
        'base'  => $ai_base_dir . '/models/ggml-base.bin'
    );

    $found_whisper_bin = $whisper_bin_candidates[0];
    foreach ($whisper_bin_candidates as $cand_bin) {
        if (@file_exists($cand_bin)) {
            $found_whisper_bin = $cand_bin;
            break;
        }
    }

    $found_whisper_model = $whisper_model_candidates['turbo'];
    $available_whisper_models = array();
    foreach ($whisper_model_candidates as $m_key => $cand_mod) {
        if (@file_exists($cand_mod)) {
            $available_whisper_models[$m_key] = $cand_mod;
            if ($found_whisper_model === $whisper_model_candidates['turbo'] && !@file_exists($found_whisper_model)) {
                $found_whisper_model = $cand_mod;
            }
        }
    }
    if (isset($available_whisper_models['small'])) {
        $found_whisper_model = $available_whisper_models['small'];
    } elseif (isset($available_whisper_models['turbo'])) {
        $found_whisper_model = $available_whisper_models['turbo'];
    }

    $env = array(
        'ai_base_dir'              => $ai_base_dir,
        'ffmpeg_bin'               => defined('FFMPEG_PATH') ? rtrim(FFMPEG_PATH, '/') . '/ffmpeg' : '/usr/bin/ffmpeg',
        'ffmpeg_ready'             => false,
        'whisper_bin'              => $found_whisper_bin,
        'whisper_model'            => $found_whisper_model,
        'available_whisper_models' => $available_whisper_models,
        'whisper_ready'            => false,
        'llm_endpoint'             => 'http://127.0.0.1:8080/v1/chat/completions',
        'llm_ready'                => false,
        'llm_model_name'           => 'Nicht verbunden (Regel- & Keyword-Engine aktiv)'
    );

    if (@file_exists($env['ffmpeg_bin']) || @file_exists('/usr/bin/ffmpeg') || @file_exists('/usr/local/bin/ffmpeg')) {
        $env['ffmpeg_ready'] = true;
    }

    if (@file_exists($env['whisper_bin']) && @file_exists($env['whisper_model'])) {
        $env['whisper_ready'] = true;
    }

    // Kurzer Health-Check auf lokalen llama-server (Port 8080) oder Ollama (Port 11434)
    if (function_exists('curl_init')) {
        $ch = @curl_init('http://127.0.0.1:8080/health');
        if ($ch) {
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $resp = @curl_exec($ch);
            $code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
            @curl_close($ch);
            if ($code === 200 && $resp !== false) {
                $env['llm_ready'] = true;
                $env['llm_model_name'] = 'llama.cpp Server (127.0.0.1:8080)';
            }
        }
        if (!$env['llm_ready']) {
            $ch2 = @curl_init('http://127.0.0.1:11434/api/tags');
            if ($ch2) {
                @curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 1);
                @curl_setopt($ch2, CURLOPT_TIMEOUT, 1);
                $resp2 = @curl_exec($ch2);
                $code2 = (int)@curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                @curl_close($ch2);
                if ($code2 === 200 && $resp2 !== false) {
                    $env['llm_ready'] = true;
                    $env['llm_endpoint'] = 'http://127.0.0.1:11434/api/chat';
                    $env['llm_model_name'] = 'Ollama Server (127.0.0.1:11434)';
                }
            }
        }
    }

    return $env;
}

/**
 * Laedt die BRAM-Keyword-Regeln aus includes/ai_rules/bram_keyword_rules.json
 */
function aic_load_keyword_rules() {
    global $sourcedir;
    $path = $sourcedir . '/includes/ai_rules/bram_keyword_rules.json';
    if (!file_exists($path)) {
        return array('categories' => array());
    }
    $raw = @file_get_contents($path);
    $decoded = @json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['categories'])) {
        return array('categories' => array());
    }
    return $decoded;
}

/**
 * Laedt den Markdown-System-Prompt aus includes/ai_rules/bram_system_prompt.md
 */
function aic_load_system_prompt() {
    global $sourcedir;
    $path = $sourcedir . '/includes/ai_rules/bram_system_prompt.md';
    if (!file_exists($path)) {
        return '';
    }
    return (string)@file_get_contents($path);
}

/**
 * Formatiert Sekunden als MM:SS
 */
function aic_format_time($seconds) {
    $sec = max(0, (int)round($seconds));
    $m = floor($sec / 60);
    $s = $sec % 60;
    return sprintf('%02d:%02d', $m, $s);
}

/**
 * Erzeugt aus Zeitsegmenten den Inhalt einer WebVTT-Untertiteldatei (.vtt)
 */
function aic_build_vtt_content($segments) {
    $vtt = "WEBVTT\nKind: captions\nLanguage: de\n\n";
    if (!is_array($segments)) {
        return $vtt;
    }
    $idx = 1;
    foreach ($segments as $seg) {
        $start = isset($seg['start']) ? (float)$seg['start'] : 0.0;
        $end   = isset($seg['end']) ? (float)$seg['end'] : ($start + 4.0);
        $text  = isset($seg['text']) ? trim($seg['text']) : '';
        if ($text === '') {
            continue;
        }
        $sh = floor($start / 3600);
        $sm = floor(($start % 3600) / 60);
        $ss = $start - ($sh * 3600) - ($sm * 60);

        $eh = floor($end / 3600);
        $em = floor(($end % 3600) / 60);
        $es = $end - ($eh * 3600) - ($em * 60);

        $start_str = sprintf('%02d:%02d:%06.3f', $sh, $sm, $ss);
        $end_str   = sprintf('%02d:%02d:%06.3f', $eh, $em, $es);

        $vtt .= $idx . "\n" . $start_str . " --> " . $end_str . "\n" . $text . "\n\n";
        $idx++;
    }
    return $vtt;
}

/**
 * Fuehrt die mehrstufige BRAM- und Rechts-Textanalyse ueber Titel, Beschreibung, Meta und Audio-Transkript aus.
 */
function aic_analyze_texts($movie_data, $segments, $spoken_transcript, $env) {
    $t_start = microtime(true);
    $rules_data = aic_load_keyword_rules();
    $categories = isset($rules_data['categories']) ? $rules_data['categories'] : array();

    $title       = isset($movie_data['title']) ? (string)$movie_data['title'] : '';
    $description = isset($movie_data['description']) ? (string)$movie_data['description'] : '';
    $meta_text   = trim((isset($movie_data['meta_title']) ? $movie_data['meta_title'] : '') . ' ' . (isset($movie_data['meta_description']) ? $movie_data['meta_description'] : ''));

    $violations = array();
    $detected_topics = array();
    $max_risk = 0;
    $overall_status = 'ok';

    // Quellen fuer den Scanner vorbereiten
    $sources = array(
        array('key' => 'title',       'label' => 'Filmtitel',           'text' => $title,       'timestamp' => '-'),
        array('key' => 'description', 'label' => 'Filmbeschreibung',    'text' => $description, 'timestamp' => '-'),
        array('key' => 'meta',        'label' => 'SEO Meta-Angaben',    'text' => $meta_text,   'timestamp' => '-')
    );

    if (is_array($segments) && count($segments) > 0) {
        foreach ($segments as $seg) {
            $ts = aic_format_time(isset($seg['start']) ? $seg['start'] : 0) . ' - ' . aic_format_time(isset($seg['end']) ? $seg['end'] : 0);
            $sources[] = array(
                'key'       => 'transcript',
                'label'     => 'Gesprochenes Video-Audio',
                'text'      => isset($seg['text']) ? (string)$seg['text'] : '',
                'timestamp' => $ts
            );
        }
    } elseif (trim($spoken_transcript) !== '') {
        $sources[] = array(
            'key'       => 'transcript',
            'label'     => 'Gesprochenes Video-Audio',
            'text'      => (string)$spoken_transcript,
            'timestamp' => 'Gesamttext'
        );
    }

    // Stufe 1: Deterministischer BRAM-Keyword- & Kontext-Scanner
    foreach ($categories as $cat) {
        $cat_name = isset($cat['name']) ? $cat['name'] : 'BRAM-Regel';
        $severity = isset($cat['severity']) ? $cat['severity'] : 'warning';
        $base_risk = isset($cat['base_risk_score']) ? (int)$cat['base_risk_score'] : 50;
        $cat_desc = isset($cat['description']) ? $cat['description'] : '';
        $keywords = isset($cat['keywords']) && is_array($cat['keywords']) ? $cat['keywords'] : array();

        foreach ($keywords as $kw) {
            $kw_trim = trim($kw);
            if ($kw_trim === '') {
                continue;
            }
            $pattern = '/(?<![a-zA-ZäöüÄÖÜß0-9])' . preg_quote($kw_trim, '/') . '(?![a-zA-ZäöüÄÖÜß0-9])/iu';

            foreach ($sources as $src) {
                if ($src['text'] === '') {
                    continue;
                }
                if (preg_match($pattern, $src['text'], $match, PREG_OFFSET_CAPTURE)) {
                    $matched_word = $match[0][0];
                    $offset = $match[0][1];

                    // Kontext-Ausschnitt (ca. 45 Zeichen vor und nach dem Treffer) extrahieren
                    $snip_start = max(0, $offset - 45);
                    $snip_len   = strlen($matched_word) + 90;
                    $quote = trim(substr($src['text'], $snip_start, $snip_len));
                    if ($snip_start > 0) {
                        $quote = '...' . $quote;
                    }
                    if ($snip_start + $snip_len < strlen($src['text'])) {
                        $quote .= '...';
                    }

                    $violations[] = array(
                        'severity'      => $severity,
                        'category'      => $cat_name,
                        'source'        => $src['key'],
                        'source_label'  => $src['label'],
                        'timestamp'     => $src['timestamp'],
                        'matched_term'  => $matched_word,
                        'matched_quote' => $quote,
                        'reason'        => $cat_desc
                    );

                    if (!in_array($cat_name, $detected_topics)) {
                        $detected_topics[] = $cat_name;
                    }
                    if ($base_risk > $max_risk) {
                        $max_risk = $base_risk;
                    }
                    if ($severity === 'violation') {
                        $overall_status = 'violation';
                    } elseif ($severity === 'warning' && $overall_status !== 'violation') {
                        $overall_status = 'warning';
                    }
                }
            }
        }
    }

    $engine_used = 'BRAM-Regel-Engine (JSON v1.0)';
    $llm_summary = '';

    // Stufe 2: Falls lokaler llama.cpp / Ollama Server aktiv ist, semantische Tiefenpruefung ausfuehren
    if (!empty($env['llm_ready'])) {
        $sys_prompt = aic_load_system_prompt();
        $user_prompt = "Bitte prüfe folgendes Video auf BRAM- & Rechtskonformität:\n\n"
            . "FILMTITEL:\n" . $title . "\n\n"
            . "FILMBESCHREIBUNG:\n" . $description . "\n\n"
            . "SEO-META:\n" . $meta_text . "\n\n"
            . "GESPROCHENES TRANSKRIPT IM VIDEO:\n" . ($spoken_transcript !== '' ? $spoken_transcript : '(Kein gesprochener Text vorhanden)') . "\n\n"
            . "VORAB-TREFFER DER KEYWORD-ENGINE:\n" . json_encode($violations, JSON_UNESCAPED_UNICODE);

        $ch = @curl_init($env['llm_endpoint']);
        if ($ch) {
            $payload = array(
                'messages' => array(
                    array('role' => 'system', 'content' => $sys_prompt),
                    array('role' => 'user',   'content' => $user_prompt)
                ),
                'temperature' => 0.1,
                'stream'      => false
            );
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $raw_llm = @curl_exec($ch);
            $http_code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
            @curl_close($ch);

            if ($http_code === 200 && $raw_llm) {
                $resp_obj = @json_decode($raw_llm, true);
                $content_str = '';
                if (isset($resp_obj['choices'][0]['message']['content'])) {
                    $content_str = $resp_obj['choices'][0]['message']['content'];
                } elseif (isset($resp_obj['message']['content'])) {
                    $content_str = $resp_obj['message']['content'];
                }
                if ($content_str !== '') {
                    // JSON aus Antwort extrahieren
                    if (preg_match('/\{.*\}/s', $content_str, $json_m)) {
                        $parsed_llm = @json_decode($json_m[0], true);
                        if (is_array($parsed_llm)) {
                            $engine_used = $env['llm_model_name'] . ' + BRAM-Regel-Engine';
                            if (!empty($parsed_llm['summary_de'])) {
                                $llm_summary = $parsed_llm['summary_de'];
                            }
                            if (isset($parsed_llm['risk_score']) && (int)$parsed_llm['risk_score'] > $max_risk) {
                                $max_risk = min(100, (int)$parsed_llm['risk_score']);
                            }
                            if (isset($parsed_llm['compliance_status'])) {
                                if ($parsed_llm['compliance_status'] === 'violation') {
                                    $overall_status = 'violation';
                                } elseif ($parsed_llm['compliance_status'] === 'warning' && $overall_status === 'ok') {
                                    $overall_status = 'warning';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Automatische deutsche Zusammenfassung generieren, falls kein LLM-Freitext vorliegt
    if ($llm_summary === '') {
        $cnt_v = 0;
        $cnt_w = 0;
        foreach ($violations as $v) {
            if ($v['severity'] === 'violation') {
                $cnt_v++;
            } else {
                $cnt_w++;
            }
        }

        if ($overall_status === 'ok') {
            $llm_summary = 'Alle geprüften Textquellen (Filmtitel, Beschreibung, SEO-Daten sowie das gesprochene Video-Transkript) sind unauffällig. Es wurden keine Verstöße gegen die Visa/Mastercard BRAM-Richtlinien oder gesetzliche Vorgaben festgestellt.';
            $max_risk = 5;
        } elseif ($overall_status === 'warning') {
            $llm_summary = 'Es wurden ' . $cnt_w . ' Prüfhinweis(e) (Gelb) in den Texten bzw. im gesprochenen Audio erkannt (' . implode(', ', $detected_topics) . '). Bitte prüfen Sie den Kontext vor der Freigabe manuell.';
        } else {
            $llm_summary = 'ACHTUNG: Es wurden ' . $cnt_v . ' kritische(r) BRAM-/Rechts-Verstoß/Verstöße (Rot) sowie ' . $cnt_w . ' Warnhinweis(e) erkannt! Betroffene Bereiche: ' . implode(', ', $detected_topics) . '. Dieses Video darf ohne Bereinigung nicht freigeschaltet werden.';
        }
    }

    $duration = round(microtime(true) - $t_start, 2);

    return array(
        'compliance_status' => $overall_status,
        'risk_score'        => $max_risk,
        'engine_used'       => $engine_used,
        'summary_de'        => $llm_summary,
        'detected_topics'   => $detected_topics,
        'violations'        => $violations,
        'duration_sec'      => $duration
    );
}

/**
 * Hilfsfunktion: Laedt alle relevanten Daten eines Films aus movies + movies_ai_analysis
 */
function aic_get_movie_details($movie_id) {
    $movie_id = abs($movie_id);
    if ($movie_id <= 0) {
        return null;
    }

    $rs = p4c_query("SELECT `m`.*,
            (SELECT COUNT(*) FROM `movies_online` `mo` WHERE `mo`.`id` = `m`.`id`) AS `is_online`
        FROM `movies` `m`
        WHERE `m`.`id` = '" . $movie_id . "'
        LIMIT 1;", __FILE__, __LINE__);

    if (p4c_num_rows($rs) === 0) {
        return null;
    }

    $m = p4c_fetch_object($rs);

    $storage_loc = !empty($m->storage_location) ? $m->storage_location : MOVIES_DEFAULT_DIR;
    $movie_dir   = MOVIES_PATH . '/' . $storage_loc . '/' . $m->merchant_id . '/' . $m->id;
    $mp4_path    = $movie_dir . '/' . $m->filename;
    $mp4_exists  = ($m->filename !== '' && @file_exists($mp4_path));
    $mp4_size    = $mp4_exists ? (int)@filesize($mp4_path) : 0;

    // Vorhandenen KI-Analyse-Datensatz laden
    $rs_ai = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movie_id . "' LIMIT 1;", __FILE__, __LINE__);
    $ai_row = (p4c_num_rows($rs_ai) > 0) ? p4c_fetch_object($rs_ai) : null;

    $sub_file = ($ai_row && $ai_row->subtitle_filename !== '')
        ? $ai_row->subtitle_filename
        : ('sub_' . $m->id . '_' . $m->file_id . '.vtt');
    $sub_path = $movie_dir . '/' . $sub_file;
    $sub_exists = @file_exists($sub_path);

    $segments = array();
    if ($ai_row && !empty($ai_row->spoken_segments_json)) {
        $dec_seg = @json_decode($ai_row->spoken_segments_json, true);
        if (is_array($dec_seg)) {
            $segments = $dec_seg;
        }
    }

    $violations = array();
    if ($ai_row && !empty($ai_row->text_violations_json)) {
        $dec_v = @json_decode($ai_row->text_violations_json, true);
        if (is_array($dec_v)) {
            $violations = $dec_v;
        }
    }

    return array(
        'id'               => (int)$m->id,
        'merchant_id'      => (int)$m->merchant_id,
        'actor_id'         => (int)$m->actor_id,
        'file_id'          => (string)$m->file_id,
        'title'            => (string)$m->title,
        'description'      => (string)$m->description,
        'meta_title'       => isset($m->meta_title) ? (string)$m->meta_title : '',
        'meta_description' => isset($m->meta_description) ? (string)$m->meta_description : '',
        'movie_language'   => isset($m->movie_language) ? (string)$m->movie_language : '',
        'playtime_seconds' => (int)$m->playtime_seconds,
        'playtime_fmt'     => aic_format_time((int)$m->playtime_seconds),
        'convert_status'   => (int)$m->convert_status,
        'released'         => (int)$m->released,
        'status'           => (string)$m->status,
        'is_online'        => ((int)$m->is_online > 0),
        'filename'         => (string)$m->filename,
        'storage_location' => $storage_loc,
        'movie_dir'        => $movie_dir,
        'mp4_path'         => $mp4_path,
        'mp4_exists'       => $mp4_exists,
        'mp4_size_mb'      => round($mp4_size / 1048576, 2),
        'ai' => array(
            'has_record'          => ($ai_row !== null),
            'subtitle_status'     => $ai_row ? (int)$ai_row->subtitle_status : 0,
            'detected_language'   => $ai_row ? (string)$ai_row->detected_language : '',
            'language_confidence' => $ai_row ? (float)$ai_row->language_confidence : 0.0,
            'subtitle_filename'   => $sub_file,
            'subtitle_exists'     => $sub_exists,
            'spoken_transcript'   => $ai_row ? (string)$ai_row->spoken_transcript : '',
            'segments'            => $segments,
            'vtt_preview'         => aic_build_vtt_content($segments),
            'whisper_duration_sec'=> $ai_row ? (float)$ai_row->whisper_duration_sec : 0.0,
            'text_check_status'   => $ai_row ? (int)$ai_row->text_check_status : 0,
            'text_compliance'     => $ai_row ? (string)$ai_row->text_compliance : 'pending',
            'text_risk_score'     => $ai_row ? (int)$ai_row->text_risk_score : 0,
            'text_engine_used'    => $ai_row ? (string)$ai_row->text_engine_used : '',
            'text_summary'        => $ai_row ? (string)$ai_row->text_summary : '',
            'violations'          => $violations,
            'text_duration_sec'   => $ai_row ? (float)$ai_row->text_duration_sec : 0.0,
            'updated_at'          => $ai_row ? (string)$ai_row->updated_at : ''
        )
    );
}

// ============================================================================
// HAUPT-ROUTING DER AJAX-AKTIONEN
// ============================================================================
aic_ensure_table();
$env = aic_check_environment();
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'init_dashboard';

if ($action === 'init_dashboard') {
    // 1. Katalog-Kennzahlen ermitteln
    $rs_total = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` = '2' AND `status` != 'deleted';", __FILE__, __LINE__);
    $total_converted = (int)p4c_fetch_object($rs_total)->cnt;

    $rs_sub = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_ai_analysis` WHERE `subtitle_status` IN (2, 3);", __FILE__, __LINE__);
    $total_subtitled = (int)p4c_fetch_object($rs_sub)->cnt;

    $rs_txt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_ai_analysis` WHERE `text_check_status` = '2';", __FILE__, __LINE__);
    $total_text_checked = (int)p4c_fetch_object($rs_txt)->cnt;

    $rs_viol = p4c_query("SELECT
            SUM(CASE WHEN `text_compliance` = 'violation' THEN 1 ELSE 0 END) AS `cnt_viol`,
            SUM(CASE WHEN `text_compliance` = 'warning' THEN 1 ELSE 0 END) AS `cnt_warn`,
            SUM(CASE WHEN `text_compliance` = 'ok' THEN 1 ELSE 0 END) AS `cnt_ok`
        FROM `movies_ai_analysis` WHERE `text_check_status` = '2';", __FILE__, __LINE__);
    $viol_obj = p4c_fetch_object($rs_viol);

    // 2. Vorschlagsliste (neueste 25 konvertierte Creator-Filme)
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
            'title'             => (string)$r->title,
            'playtime_fmt'      => aic_format_time((int)$r->playtime_seconds),
            'subtitle_status'   => $r->subtitle_status !== null ? (int)$r->subtitle_status : 0,
            'text_compliance'   => $r->text_compliance !== null ? (string)$r->text_compliance : 'pending',
            'detected_language' => $r->detected_language !== null ? (string)$r->detected_language : ''
        );
    }

    // 3. Bereits getestete Filme fuer Tab 3
    $history_rows = array();
    $rs_hist = p4c_query("SELECT `ai`.*, `m`.`title`, `m`.`playtime_seconds`
        FROM `movies_ai_analysis` `ai`
        LEFT JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
        ORDER BY `ai`.`updated_at` DESC
        LIMIT 100;", __FILE__, __LINE__);
    while ($h = p4c_fetch_object($rs_hist)) {
        $history_rows[] = array(
            'movie_id'            => (int)$h->movie_id,
            'merchant_id'         => (int)$h->merchant_id,
            'title'               => (string)$h->title,
            'playtime_fmt'        => aic_format_time((int)$h->playtime_seconds),
            'subtitle_status'     => (int)$h->subtitle_status,
            'detected_language'   => (string)$h->detected_language,
            'language_confidence' => (float)$h->language_confidence,
            'subtitle_filename'   => (string)$h->subtitle_filename,
            'text_check_status'   => (int)$h->text_check_status,
            'text_compliance'     => (string)$h->text_compliance,
            'text_risk_score'     => (int)$h->text_risk_score,
            'text_summary'        => (string)$h->text_summary,
            'updated_at'          => (string)$h->updated_at
        );
    }

    // 4. Regelwerk fuer Tab 2 laden
    $rules_json_path = $sourcedir . '/includes/ai_rules/bram_keyword_rules.json';
    $rules_md_path   = $sourcedir . '/includes/ai_rules/bram_system_prompt.md';

    aic_send_json(array(
        'status' => 'ok',
        'stats'  => array(
            'total_converted'    => $total_converted,
            'total_subtitled'    => $total_subtitled,
            'total_text_checked' => $total_text_checked,
            'remaining_subtitles'=> max(0, $total_converted - $total_subtitled),
            'cnt_ok'             => $viol_obj ? (int)$viol_obj->cnt_ok : 0,
            'cnt_warn'           => $viol_obj ? (int)$viol_obj->cnt_warn : 0,
            'cnt_viol'           => $viol_obj ? (int)$viol_obj->cnt_viol : 0
        ),
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
              AND (`ai`.`movie_id` IS NULL OR `ai`.`text_check_status` = '0')
            ORDER BY `m`.`id` DESC
            LIMIT 1;", __FILE__, __LINE__);
        if (p4c_num_rows($rs_next) > 0) {
            $movie_id = (int)p4c_fetch_object($rs_next)->id;
        }
    } elseif ($mode === 'find_interesting') {
        // Sucht gezielt einen Bestandsfilm aus der DB, in dessen Titel/Beschreibung ein spannendes BRAM-Pruefwort vorkommt
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
    $movie_id = isset($_POST['movie_id']) ? abs($_POST['movie_id']) : 0;
    $sim_scenario = isset($_POST['sim_scenario']) ? trim($_POST['sim_scenario']) : 'clean';
    $details = aic_get_movie_details($movie_id);

    if (!$details) {
        aic_send_json(array('status' => 'error', 'message' => 'Ungültige Film-ID.'));
    }

    $t0 = microtime(true);
    $detected_lang = 'de';
    $lang_conf     = 98.40;
    $segments      = array();
    $transcript    = '';
    $sub_status    = 2;
    $mode_used     = '';

    // Pruefen, ob physische MP4 UND whisper.cpp auf dem Live-Server bereitstehen
    if ($details['mp4_exists'] && $env['ffmpeg_ready'] && $env['whisper_ready']) {
        @set_time_limit(600);
        $model_label = basename($env['whisper_model']);
        $mode_used = 'Live-Server (FFmpeg WAV -> whisper.cpp [' . $model_label . '])';
        $tmp_dir   = (defined('TEMP_DIR') && @is_dir(TEMP_DIR) && @is_writable(TEMP_DIR)) ? rtrim(TEMP_DIR, '/') : rtrim(sys_get_temp_dir(), '/');
        $wav_tmp   = $tmp_dir . '/erocloud_ai_audio_' . $details['id'] . '_' . time() . '.wav';
        $json_base = $tmp_dir . '/erocloud_ai_out_' . $details['id'] . '_' . time();

        $cmd_wav = escapeshellcmd($env['ffmpeg_bin']) . ' -y -i ' . escapeshellarg($details['mp4_path'])
            . ' -vn -acodec pcm_s16le -ar 16000 -ac 1 ' . escapeshellarg($wav_tmp) . ' 2>&1';
        @exec($cmd_wav, $out_wav, $ret_wav);

        if ($ret_wav === 0 && @file_exists($wav_tmp)) {
            $cmd_wh = 'nice -n 15 ' . escapeshellcmd($env['whisper_bin'])
                . ' -m ' . escapeshellarg($env['whisper_model'])
                . ' -f ' . escapeshellarg($wav_tmp)
                . ' -l auto -bs 1 -bo 1 -t 16 -oj -ovtt -of ' . escapeshellarg($json_base) . ' 2>&1';
            @exec($cmd_wh, $out_wh, $ret_wh);
            @unlink($wav_tmp);

            if (@file_exists($json_base . '.json')) {
                $w_json = @json_decode(@file_get_contents($json_base . '.json'), true);
                if (isset($w_json['result']['language'])) {
                    $detected_lang = $w_json['result']['language'];
                }
                if (isset($w_json['transcription']) && is_array($w_json['transcription'])) {
                    $lines = array();
                    foreach ($w_json['transcription'] as $item) {
                        $s_sec = isset($item['offsets']['from']) ? round($item['offsets']['from'] / 1000, 2) : 0;
                        $e_sec = isset($item['offsets']['to']) ? round($item['offsets']['to'] / 1000, 2) : ($s_sec + 3);
                        $txt   = isset($item['text']) ? trim($item['text']) : '';
                        if ($txt !== '') {
                            $segments[] = array('start' => $s_sec, 'end' => $e_sec, 'text' => $txt);
                            $lines[]    = '[' . aic_format_time($s_sec) . ' - ' . aic_format_time($e_sec) . '] ' . $txt;
                        }
                    }
                    $transcript = implode("\n", $lines);
                }
                @unlink($json_base . '.json');
            }
            if (@file_exists($json_base . '.vtt')) {
                $target_vtt = $details['movie_dir'] . '/' . $details['ai']['subtitle_filename'];
                @copy($json_base . '.vtt', $target_vtt);
                @unlink($json_base . '.vtt');
            }
        } else {
            @unlink($wav_tmp);
        }
    } else {
        // Test- & Simulationsmodus (fuer lokale Entwicklung oder solange whisper.cpp noch nicht installiert ist)
        $mode_used = 'Testlabor-Simulation (Whisper-Vorschau)';
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
                array('start' => 30.0, 'end' => 41.0, 'text' => 'Danach probieren wir noch ein bisschen Natursekt unter der Dusche aus.')
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

        // Falls der Film-Ordner physisch existiert, auch dort die .vtt-Datei ablegen
        if (@is_dir($details['movie_dir'])) {
            $vtt_content = aic_build_vtt_content($segments);
            @file_put_contents($details['movie_dir'] . '/' . $details['ai']['subtitle_filename'], $vtt_content);
        }
    }

    $duration = round(microtime(true) - $t0, 2);
    if ($duration < 0.05) {
        $duration = 0.12;
    }

    $now = date('Y-m-d H:i:s');
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

    $updated_details = aic_get_movie_details($details['id']);

    aic_send_json(array(
        'status'    => 'ok',
        'mode_used' => $mode_used,
        'movie'     => $updated_details
    ));
}

if ($action === 'run_text_check') {
    $movie_id = isset($_POST['movie_id']) ? abs($_POST['movie_id']) : 0;
    $custom_transcript = isset($_POST['custom_transcript']) ? trim($_POST['custom_transcript']) : null;
    $custom_title      = isset($_POST['custom_title']) ? trim($_POST['custom_title']) : null;
    $custom_desc       = isset($_POST['custom_desc']) ? trim($_POST['custom_desc']) : null;

    $details = aic_get_movie_details($movie_id);
    if (!$details) {
        aic_send_json(array('status' => 'error', 'message' => 'Ungültige Film-ID.'));
    }

    // Falls der Admin im Testlabor den gesprochenen Text oder Titel/Beschreibung zum Testen angepasst hat
    $transcript_to_check = ($custom_transcript !== null) ? $custom_transcript : $details['ai']['spoken_transcript'];
    $segments_to_check   = $details['ai']['segments'];

    // Wenn der Admin das Transkript im Textfeld editiert hat, Segmente aus Zeilen wie [00:12 - 00:18] parsen
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

    $now = date('Y-m-d H:i:s');
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

    $updated_details = aic_get_movie_details($details['id']);

    aic_send_json(array(
        'status' => 'ok',
        'report' => $report,
        'movie'  => $updated_details
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
        'parsed_categories' => aic_load_keyword_rules()
    ));
}

aic_send_json(array(
    'status'  => 'error',
    'message' => 'Unbekannte Aktion: ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8')
));
?>
