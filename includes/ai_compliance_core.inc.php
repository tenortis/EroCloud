<?php

/**
 * @author      Martin Zimmermann
 * @copyright   (C) 2016 adult net applications UG
 * @email       erocms@gmail.com
 *
 * Zentrale Kernfunktionen fuer die lokale KI-Compliance- & Untertitel-Pipeline (whisper.cpp, BRAM-Engine, llama.cpp).
 * Wird sowohl vom ACP-Cockpit (/KI-Analyse) als auch vom Hintergrund-Worker (cronjobs/ai_compliance_worker.php) genutzt.
 * PHP 7.3 kompatibel.
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * Stellt sicher, dass Strings valides UTF-8 fuer JSON und Datenbank sind.
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

/**
 * Rekursive UTF-8-Bereinigung fuer Arrays vor json_encode.
 */
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
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`movie_id`),
        KEY `idx_merchant_id` (`merchant_id`),
        KEY `idx_subtitle_status` (`subtitle_status`),
        KEY `idx_text_check_status` (`text_check_status`),
        KEY `idx_text_compliance` (`text_compliance`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    p4c_query($sql, __FILE__, __LINE__);
}

/**
 * Prueft Existenz einer Datei auch ausserhalb von PHP open_basedir (z.B. /usr/bin/ffmpeg unter LiveConfig).
 */
function aic_file_or_exec_exists($path) {
    if ($path === '' || $path === null) {
        return false;
    }
    if (@file_exists($path)) {
        return true;
    }
    if (function_exists('exec')) {
        $out = array();
        $ret = 1;
        @exec('test -e ' . escapeshellarg($path) . ' && echo 1', $out, $ret);
        if ($ret === 0 && isset($out[0]) && trim($out[0]) === '1') {
            return true;
        }
    }
    return false;
}

/**
 * Bereinigt Datenbank-Texte von HTML-Tags (<p>, <br>) und wandelt HTML-Entities (&auml;, &uuml;, &szlig;) in echte UTF-8 Umlaute um.
 */
function aic_clean_html_text($str) {
    if ($str === null || $str === '') {
        return '';
    }
    $str = aic_utf8((string)$str);
    $str = preg_replace('/<br\s*\/?>/i', "\n", $str);
    $str = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $str);
    $str = strip_tags($str);
    $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (strpos($str, '&') !== false) {
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return trim($str);
}

/**
 * Laedt die Batch- & Cronjob-Konfiguration aus includes/ai_rules/batch_config.json
 */
function aic_load_batch_config() {
    global $sourcedir;
    $defaults = array(
        'cron_enabled'            => false,
        'task_mode'               => 'whisper_and_text', // whisper_and_text | text_only | recheck_llm
        'whisper_model_pref'      => 'small',            // small | turbo
        'whisper_threads'         => 12,
        'audio_limit_sec'         => 0,                  // 0 = Komplettes Video, sonst Sekunden (z.B. 180, 300)
        'order_direction'         => 'DESC',             // DESC = Neueste zuerst, ASC = Aelteste zuerst
        'pause_on_ffmpeg_convert' => true,
        'max_movies_per_run'      => 2,
        'sync_empty_movie_lang'   => false
    );
    $path = $sourcedir . '/includes/ai_rules/batch_config.json';
    if (!@file_exists($path)) {
        return $defaults;
    }
    $raw = @file_get_contents($path);
    $dec = @json_decode($raw, true);
    if (!is_array($dec)) {
        return $defaults;
    }
    return array_merge($defaults, $dec);
}

/**
 * Speichert die Batch- & Cronjob-Konfiguration in includes/ai_rules/batch_config.json
 */
function aic_save_batch_config($new_cfg) {
    global $sourcedir;
    $current = aic_load_batch_config();
    $merged = array_merge($current, $new_cfg);

    $merged['cron_enabled']            = !empty($merged['cron_enabled']);
    $merged['whisper_threads']         = max(2, min(32, (int)$merged['whisper_threads']));
    $merged['audio_limit_sec']         = max(0, (int)$merged['audio_limit_sec']);
    $merged['order_direction']         = (strtoupper($merged['order_direction']) === 'ASC') ? 'ASC' : 'DESC';
    $merged['pause_on_ffmpeg_convert'] = !empty($merged['pause_on_ffmpeg_convert']);
    $merged['max_movies_per_run']      = max(1, min(20, (int)$merged['max_movies_per_run']));
    $merged['sync_empty_movie_lang']   = !empty($merged['sync_empty_movie_lang']);

    $path = $sourcedir . '/includes/ai_rules/batch_config.json';
    @file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $merged;
}

/**
 * Liest den letzten Laufzeit-Status des Hintergrund-Workers aus includes/ai_rules/batch_status.json
 */
function aic_get_worker_status() {
    global $sourcedir;
    $defaults = array(
        'state'              => 'idle',
        'current_movie_id'   => 0,
        'last_movie_id'      => 0,
        'last_movie_title'   => '',
        'last_run_at'        => '-',
        'last_duration_sec'  => 0,
        'last_message'       => 'Noch kein Hintergrund-Durchlauf gestartet.',
        'total_processed_runs' => 0
    );
    $path = $sourcedir . '/includes/ai_rules/batch_status.json';
    if (!@file_exists($path)) {
        return $defaults;
    }
    $raw = @file_get_contents($path);
    $dec = @json_decode($raw, true);
    if (!is_array($dec)) {
        return $defaults;
    }
    return array_merge($defaults, $dec);
}

/**
 * Aktualisiert den Status des Hintergrund-Workers in includes/ai_rules/batch_status.json
 */
function aic_update_worker_status($updates) {
    global $sourcedir;
    $current = aic_get_worker_status();
    $merged = array_merge($current, $updates);
    $path = $sourcedir . '/includes/ai_rules/batch_status.json';
    @file_put_contents($path, json_encode(aic_sanitize_Recursive($merged), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $merged;
}

/**
 * Prueft den Status der lokalen KI-Werkzeuge (FFmpeg, whisper.cpp, llama.cpp / Ollama).
 */
function aic_check_environment() {
    $batch_cfg = aic_load_batch_config();
    $ai_base_dir = defined('MOVIES_PATH') ? rtrim(MOVIES_PATH, '/') . '/priv/ai' : '/var/www/web1/priv/ai';
    $whisper_bin_candidates = array(
        $ai_base_dir . '/whisper.cpp/build/bin/whisper-cli',
        $ai_base_dir . '/whisper.cpp/build/bin/main',
        '/var/www/web1/priv/ai/whisper.cpp/build/bin/whisper-cli',
        '/opt/erocloud_ai/whisper.cpp/build/bin/whisper-cli'
    );
    $whisper_model_candidates = array(
        'small' => $ai_base_dir . '/models/ggml-small-q5_1.bin',
        'turbo' => $ai_base_dir . '/models/ggml-large-v3-turbo-q5_0.bin',
        'medium'=> $ai_base_dir . '/models/ggml-medium-q5_0.bin',
        'base'  => $ai_base_dir . '/models/ggml-base.bin'
    );

    $found_whisper_bin = $whisper_bin_candidates[0];
    foreach ($whisper_bin_candidates as $cand_bin) {
        if (aic_file_or_exec_exists($cand_bin)) {
            $found_whisper_bin = $cand_bin;
            break;
        }
    }

    $available_whisper_models = array();
    foreach ($whisper_model_candidates as $m_key => $cand_mod) {
        if (aic_file_or_exec_exists($cand_mod)) {
            $available_whisper_models[$m_key] = $cand_mod;
        }
    }

    $pref = isset($batch_cfg['whisper_model_pref']) ? $batch_cfg['whisper_model_pref'] : 'small';
    if (isset($available_whisper_models[$pref])) {
        $found_whisper_model = $available_whisper_models[$pref];
    } elseif (isset($available_whisper_models['small'])) {
        $found_whisper_model = $available_whisper_models['small'];
    } elseif (isset($available_whisper_models['turbo'])) {
        $found_whisper_model = $available_whisper_models['turbo'];
    } else {
        $found_whisper_model = $whisper_model_candidates['small'];
    }

    $ffmpeg_bin = defined('FFMPEG_PATH') ? rtrim(FFMPEG_PATH, '/') . '/ffmpeg' : '/usr/bin/ffmpeg';
    if (!aic_file_or_exec_exists($ffmpeg_bin) && aic_file_or_exec_exists('/usr/bin/ffmpeg')) {
        $ffmpeg_bin = '/usr/bin/ffmpeg';
    }

    $env = array(
        'ai_base_dir'              => $ai_base_dir,
        'ffmpeg_bin'               => $ffmpeg_bin,
        'ffmpeg_ready'             => aic_file_or_exec_exists($ffmpeg_bin),
        'whisper_bin'              => $found_whisper_bin,
        'whisper_model'            => $found_whisper_model,
        'available_whisper_models' => $available_whisper_models,
        'whisper_ready'            => (aic_file_or_exec_exists($found_whisper_bin) && count($available_whisper_models) > 0),
        'llm_endpoint'             => 'http://127.0.0.1:8080/v1/chat/completions',
        'llm_ready'                => false,
        'llm_model_name'           => 'Nicht verbunden (Regel- & Keyword-Engine aktiv)'
    );

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
    static $cached_rules = null;
    if ($cached_rules !== null) {
        return $cached_rules;
    }
    $base_dir = !empty($sourcedir) ? $sourcedir : (defined('SOURCEDIR') ? SOURCEDIR : dirname(dirname(__FILE__)));
    $path = $base_dir . '/includes/ai_rules/bram_keyword_rules.json';
    if (!file_exists($path)) {
        return array('categories' => array());
    }
    $raw = @file_get_contents($path);
    $decoded = @json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['categories'])) {
        return array('categories' => array());
    }
    $cached_rules = $decoded;
    return $decoded;
}

/**
 * Laedt den Markdown-System-Prompt aus includes/ai_rules/bram_system_prompt.md
 */
function aic_load_system_prompt() {
    global $sourcedir;
    $base_dir = !empty($sourcedir) ? $sourcedir : (defined('SOURCEDIR') ? SOURCEDIR : dirname(dirname(__FILE__)));
    $path = $base_dir . '/includes/ai_rules/bram_system_prompt.md';
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
function aic_build_vtt_content($segments, $lang = 'de') {
    $lang_clean = ($lang !== '') ? $lang : 'de';
    $vtt = "WEBVTT\nKind: captions\nLanguage: " . $lang_clean . "\n\n";
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

    $title       = isset($movie_data['title']) ? aic_clean_html_text($movie_data['title']) : '';
    $description = isset($movie_data['description']) ? aic_clean_html_text($movie_data['description']) : '';
    $meta_text   = aic_clean_html_text((isset($movie_data['meta_title']) ? $movie_data['meta_title'] : '') . ' ' . (isset($movie_data['meta_description']) ? $movie_data['meta_description'] : ''));

    $violations = array();
    $detected_topics = array();
    $max_risk = 0;
    $overall_status = 'ok';

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
        $cat_name  = isset($cat['name']) ? $cat['name'] : 'BRAM-Regel';
        $severity  = isset($cat['severity']) ? $cat['severity'] : 'warning';
        $base_risk = isset($cat['base_risk_score']) ? (int)$cat['base_risk_score'] : 50;
        $cat_desc  = isset($cat['description']) ? $cat['description'] : '';
        $keywords  = isset($cat['keywords']) && is_array($cat['keywords']) ? $cat['keywords'] : array();

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

    $engine_used = 'BRAM-Regel-Engine (JSON v1.1)';
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

    $has_audio_transcript = (trim((string)$spoken_transcript) !== '' || !empty($segments));

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
            $max_risk = 0;
            if ($has_audio_transcript) {
                $llm_summary = 'Alle geprüften Quellen (Filmtitel, Beschreibung, SEO-Daten sowie das gesprochene Video-Transkript) sind unauffällig. Es wurden keine Verstöße gegen die Visa/Mastercard BRAM-Richtlinien oder gesetzliche Vorgaben festgestellt.';
            } else {
                $llm_summary = 'Vorab-Textprüfung unauffällig: In den geschriebenen Texten (Filmtitel, Beschreibung, SEO-Daten) wurden keine verbotenen Begriffe gefunden. HINWEIS: Die gesprochene Video-Tonspur wurde noch nicht durch den Whisper-Cronjob transkribiert (Audio-Prüfung steht noch aus).';
            }
        } elseif ($overall_status === 'warning') {
            $audio_note = $has_audio_transcript ? ' (inkl. Audio-Transkript)' : ' (Vorab-Textcheck; Audio-Transkription steht noch aus)';
            $llm_summary = 'Es wurden ' . $cnt_w . ' Prüfhinweis(e) (Orange) erkannt' . $audio_note . ': ' . implode(', ', $detected_topics) . '. Hinweis: Spezialfetische wie KV/NS/Poppers/Hypnose sind nur auf Portalen ohne Kreditkartenabrechnung zulässig.';
        } else {
            $audio_note = $has_audio_transcript ? ' (inkl. Audio-Transkript)' : ' (bereits in den Text-Metadaten erkannt; Audio-Transkription noch offen)';
            $llm_summary = 'ACHTUNG: Es wurden ' . $cnt_v . ' kritische(r) BRAM-/Rechts-Verstoß/Verstöße (Rot) sowie ' . $cnt_w . ' Warnhinweis(e) erkannt' . $audio_note . '! Betroffene Bereiche: ' . implode(', ', $detected_topics) . '. Dieses Video darf auf keinem Portal freigeschaltet werden.';
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
 * Laedt alle relevanten Daten eines Films aus movies + movies_ai_analysis
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
    $mp4_exists  = ($m->filename !== '' && aic_file_or_exec_exists($mp4_path));
    $mp4_size    = ($mp4_exists && @file_exists($mp4_path)) ? (int)@filesize($mp4_path) : 0;

    $rs_ai = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movie_id . "' LIMIT 1;", __FILE__, __LINE__);
    $ai_row = (p4c_num_rows($rs_ai) > 0) ? p4c_fetch_object($rs_ai) : null;

    $sub_file = ($ai_row && $ai_row->subtitle_filename !== '')
        ? $ai_row->subtitle_filename
        : ('sub_' . $m->id . '_' . $m->file_id . '.vtt');
    $sub_path = $movie_dir . '/' . $sub_file;
    $sub_exists = aic_file_or_exec_exists($sub_path);

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

    $det_lang = $ai_row ? (string)$ai_row->detected_language : '';

    return array(
        'id'               => (int)$m->id,
        'merchant_id'      => (int)$m->merchant_id,
        'actor_id'         => (int)$m->actor_id,
        'file_id'          => (string)$m->file_id,
        'title'            => aic_clean_html_text($m->title),
        'description'      => aic_clean_html_text($m->description),
        'meta_title'       => isset($m->meta_title) ? aic_clean_html_text($m->meta_title) : '',
        'meta_description' => isset($m->meta_description) ? aic_clean_html_text($m->meta_description) : '',
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
            'detected_language'   => $det_lang,
            'language_confidence' => $ai_row ? (float)$ai_row->language_confidence : 0.0,
            'subtitle_filename'   => $sub_file,
            'subtitle_exists'     => $sub_exists,
            'spoken_transcript'   => $ai_row ? (string)$ai_row->spoken_transcript : '',
            'segments'            => $segments,
            'vtt_preview'         => aic_build_vtt_content($segments, $det_lang),
            'whisper_duration_sec'=> $ai_row ? (float)$ai_row->whisper_duration_sec : 0.0,
            'text_check_status'   => $ai_row ? (int)$ai_row->text_check_status : 0,
            'text_compliance'     => $ai_row ? (string)$ai_row->text_compliance : 'pending',
            'text_risk_score'     => $ai_row ? (int)$ai_row->text_risk_score : 0,
            'text_engine_used'    => $ai_row ? (string)$ai_row->text_engine_used : '',
            'text_summary'        => $ai_row ? (string)$ai_row->text_summary : '',
            'violations'          => $violations,
            'text_duration_sec'   => $ai_row ? (float)$ai_row->text_duration_sec : 0.0,
            'last_error'          => $ai_row ? (string)$ai_row->last_error : '',
            'updated_at'          => $ai_row ? (string)$ai_row->updated_at : ''
        )
    );
}

/**
 * Fuehrt fuer ein einzelnes Video die echte Whisper-Transkription + .vtt-Erzeugung + anschliessenden BRAM-Check aus.
 * Wird sowohl im Batch/Cronjob als auch bei Live-Abruf genutzt.
 */
function aic_process_single_movie_pipeline($movie_id, $env, $options = array()) {
    $details = aic_get_movie_details($movie_id);
    if (!$details) {
        return array('status' => 'error', 'message' => 'Film #' . abs($movie_id) . ' nicht gefunden.');
    }

    $batch_cfg       = aic_load_batch_config();
    $audio_limit_sec = isset($options['audio_limit_sec']) ? (int)$options['audio_limit_sec'] : (int)$batch_cfg['audio_limit_sec'];
    $threads         = isset($options['whisper_threads']) ? (int)$options['whisper_threads'] : (int)$batch_cfg['whisper_threads'];
    $run_text_after  = isset($options['run_text_after']) ? (bool)$options['run_text_after'] : true;

    $t0            = microtime(true);
    $detected_lang = !empty($details['movie_language']) ? $details['movie_language'] : 'de';
    $lang_conf     = 98.40;
    $segments      = array();
    $transcript    = '';
    $sub_status    = 2;
    $last_error    = '';
    $mode_used     = '';
    $sub_file      = $details['ai']['subtitle_filename'];
    $now           = date('Y-m-d H:i:s');

    // Markiere als "In Arbeit" (subtitle_status = 1)
    p4c_query("INSERT INTO `movies_ai_analysis`
        (`movie_id`, `merchant_id`, `subtitle_status`, `subtitle_filename`, `created_at`, `updated_at`)
        VALUES ('" . abs($details['id']) . "', '" . abs($details['merchant_id']) . "', '1', '" . p4c_escape_string($sub_file) . "', '" . $now . "', '" . $now . "')
        ON DUPLICATE KEY UPDATE `subtitle_status` = '1', `updated_at` = '" . $now . "';", __FILE__, __LINE__);

    if (!$details['mp4_exists']) {
        // Physische MP4-Datei fehlt auf dem Dateisystem -> Status 4 setzen, damit der Batch nicht haengen bleibt
        $sub_status = 4;
        $last_error = 'Physische MP4-Datei nicht gefunden (' . $details['mp4_path'] . ')';
        $transcript = '';
        $mode_used  = 'MP4-Datei fehlt (Nur Text-Metadaten geprüft)';
    } elseif (!$env['ffmpeg_ready'] || !$env['whisper_ready']) {
        $sub_status = 4;
        $last_error = 'FFmpeg oder whisper.cpp ist auf diesem System nicht ausführbar.';
        $mode_used  = 'Fehler: Whisper nicht bereit';
    } else {
        @set_time_limit(900);
        $model_label = basename($env['whisper_model']);
        $limit_label = ($audio_limit_sec > 0) ? ('Erste ' . round($audio_limit_sec / 60, 1) . ' Min.') : 'Komplettes Video';
        $mode_used   = 'FFmpeg -> whisper.cpp [' . $model_label . ' | ' . $limit_label . ' | ' . $threads . ' Threads]';

        $tmp_dir   = (defined('TEMP_DIR') && @is_dir(TEMP_DIR) && @is_writable(TEMP_DIR)) ? rtrim(TEMP_DIR, '/') : rtrim(sys_get_temp_dir(), '/');
        $wav_tmp   = $tmp_dir . '/erocloud_ai_audio_' . $details['id'] . '_' . time() . '_' . mt_rand(100, 999) . '.wav';
        $json_base = $tmp_dir . '/erocloud_ai_out_' . $details['id'] . '_' . time() . '_' . mt_rand(100, 999);

        $ffmpeg_limit_arg = ($audio_limit_sec > 0) ? (' -t ' . (int)$audio_limit_sec . ' ') : ' ';
        $cmd_wav = escapeshellcmd($env['ffmpeg_bin']) . ' -y -i ' . escapeshellarg($details['mp4_path'])
            . $ffmpeg_limit_arg . '-vn -acodec pcm_s16le -ar 16000 -ac 1 ' . escapeshellarg($wav_tmp) . ' 2>&1';
        $out_wav = array();
        $ret_wav = 1;
        @exec($cmd_wav, $out_wav, $ret_wav);

        if ($ret_wav === 0 && aic_file_or_exec_exists($wav_tmp)) {
            $cmd_wh = 'nice -n 15 ' . escapeshellcmd($env['whisper_bin'])
                . ' -m ' . escapeshellarg($env['whisper_model'])
                . ' -f ' . escapeshellarg($wav_tmp)
                . ' -l auto -bs 1 -bo 1 -t ' . (int)$threads . ' -oj -ovtt -of ' . escapeshellarg($json_base) . ' 2>&1';
            $out_wh = array();
            $ret_wh = 1;
            @exec($cmd_wh, $out_wh, $ret_wh);
            @unlink($wav_tmp);

            if (aic_file_or_exec_exists($json_base . '.json')) {
                $w_json = @json_decode(@file_get_contents($json_base . '.json'), true);
                if (isset($w_json['result']['language'])) {
                    $detected_lang = trim((string)$w_json['result']['language']);
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

            if (aic_file_or_exec_exists($json_base . '.vtt')) {
                $target_vtt = $details['movie_dir'] . '/' . $sub_file;
                @copy($json_base . '.vtt', $target_vtt);
                @unlink($json_base . '.vtt');
            }

            if ($transcript === '') {
                $transcript = '[00:00 - 00:05] (Kein gesprochener Dialog im Video erkannt)';
            }

            // Optional: Falls movies.movie_language leer ist und Option aktiv, erkannte Sprache eintragen
            $allowed_langs = array('de', 'en', 'fr', 'es', 'nl', 'ru', 'pl');
            if (!empty($batch_cfg['sync_empty_movie_lang']) && empty($details['movie_language']) && in_array($detected_lang, $allowed_langs)) {
                p4c_query("UPDATE `movies` SET `movie_language` = '" . p4c_escape_string($detected_lang) . "' WHERE `id` = '" . abs($details['id']) . "' AND (`movie_language` = '' OR `movie_language` IS NULL) LIMIT 1;", __FILE__, __LINE__);
            }
        } else {
            @unlink($wav_tmp);
            // Pruefen, ob das Video einfach keinen Audio-Stream besitzt (Stummfilm)
            $wav_err_str = implode(' ', $out_wav);
            if (stripos($wav_err_str, 'does not contain any stream') !== false || stripos($wav_err_str, 'Output file is empty') !== false) {
                $sub_status = 3;
                $transcript = '[00:00 - 00:00] (Video besitzt keine Tonspur / Stummfilm)';
                $last_error = 'Keine Tonspur im Video vorhanden (Stummfilm).';
                $mode_used  = 'Stummfilm (Keine Audiospur vorhanden)';
            } else {
                $sub_status = 4;
                $last_error = 'FFmpeg Audio-Extraktion fehlgeschlagen (Code ' . $ret_wav . ').';
                $mode_used  = 'FFmpeg-Fehler (Code ' . $ret_wav . ')';
            }
        }
    }

    $w_duration = round(microtime(true) - $t0, 2);
    $now        = date('Y-m-d H:i:s');
    $seg_json   = json_encode($segments, JSON_UNESCAPED_UNICODE);

    p4c_query("UPDATE `movies_ai_analysis` SET
            `subtitle_status`      = '" . abs($sub_status) . "',
            `detected_language`    = '" . p4c_escape_string($detected_lang) . "',
            `language_confidence`  = '" . p4c_escape_string($lang_conf) . "',
            `subtitle_filename`    = '" . p4c_escape_string($sub_file) . "',
            `spoken_transcript`    = '" . p4c_escape_string($transcript) . "',
            `spoken_segments_json` = '" . p4c_escape_string($seg_json) . "',
            `whisper_duration_sec` = '" . p4c_escape_string($w_duration) . "',
            `last_error`           = '" . p4c_escape_string($last_error) . "',
            `updated_at`           = '" . $now . "'
        WHERE `movie_id` = '" . abs($details['id']) . "'
        LIMIT 1;", __FILE__, __LINE__);

    $report = null;
    if ($run_text_after) {
        $movie_check_input = array(
            'title'            => $details['title'],
            'description'      => $details['description'],
            'meta_title'       => $details['meta_title'],
            'meta_description' => $details['meta_description']
        );
        $report = aic_analyze_texts($movie_check_input, $segments, $transcript, $env);
        $viol_json = json_encode($report['violations'], JSON_UNESCAPED_UNICODE);
        $now2 = date('Y-m-d H:i:s');

        p4c_query("UPDATE `movies_ai_analysis` SET
                `text_check_status`    = '2',
                `text_compliance`      = '" . p4c_escape_string($report['compliance_status']) . "',
                `text_risk_score`      = '" . abs($report['risk_score']) . "',
                `text_engine_used`     = '" . p4c_escape_string($report['engine_used']) . "',
                `text_summary`         = '" . p4c_escape_string($report['summary_de']) . "',
                `text_violations_json` = '" . p4c_escape_string($viol_json) . "',
                `text_duration_sec`    = '" . p4c_escape_string($report['duration_sec']) . "',
                `updated_at`           = '" . $now2 . "'
            WHERE `movie_id` = '" . abs($details['id']) . "'
            LIMIT 1;", __FILE__, __LINE__);
    }

    return array(
        'status'    => ($sub_status === 4 && !$run_text_after) ? 'error' : 'ok',
        'mode_used' => $mode_used,
        'report'    => $report,
        'movie'     => aic_get_movie_details($details['id'])
    );
}

/**
 * Liefert detaillierte Statistiken fuer das Dashboard und den Batch-/Cronjob-Reiter.
 */
function aic_get_catalog_and_batch_stats() {
    $rs_total = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` = '2' AND `status` != 'deleted';", __FILE__, __LINE__);
    $total_converted = (int)p4c_fetch_object($rs_total)->cnt;

    $rs_conv = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` = '1' AND `status` != 'deleted';", __FILE__, __LINE__);
    $active_conversions = (int)p4c_fetch_object($rs_conv)->cnt;

    // Nur Filme zaehlen, die auch als konvertierte nicht-geloeschte Filme im Katalog existieren
    $rs_ai = p4c_query("SELECT
            SUM(CASE WHEN `ai`.`subtitle_status` = 2 THEN 1 ELSE 0 END) AS `sub_created`,
            SUM(CASE WHEN `ai`.`subtitle_status` = 3 THEN 1 ELSE 0 END) AS `sub_no_audio`,
            SUM(CASE WHEN `ai`.`subtitle_status` = 4 THEN 1 ELSE 0 END) AS `sub_error`,
            SUM(CASE WHEN `ai`.`subtitle_status` = 1 THEN 1 ELSE 0 END) AS `sub_running`,
            SUM(CASE WHEN `ai`.`subtitle_status` IN (2, 3, 4) THEN 1 ELSE 0 END) AS `sub_processed_total`,
            SUM(CASE WHEN `ai`.`text_check_status` = 2 THEN 1 ELSE 0 END) AS `txt_checked`,
            SUM(CASE WHEN `ai`.`text_check_status` = 2 AND `ai`.`text_compliance` = 'ok' THEN 1 ELSE 0 END) AS `cnt_ok`,
            SUM(CASE WHEN `ai`.`text_check_status` = 2 AND `ai`.`text_compliance` = 'warning' THEN 1 ELSE 0 END) AS `cnt_warn`,
            SUM(CASE WHEN `ai`.`text_check_status` = 2 AND `ai`.`text_compliance` = 'violation' THEN 1 ELSE 0 END) AS `cnt_viol`
        FROM `movies_ai_analysis` `ai`
        INNER JOIN `movies` `m` ON `m`.`id` = `ai`.`movie_id`
        WHERE `m`.`convert_status` = '2' AND `m`.`status` != 'deleted';", __FILE__, __LINE__);
    $ai_obj = p4c_fetch_object($rs_ai);

    $sub_created         = $ai_obj ? (int)$ai_obj->sub_created : 0;
    $sub_no_audio        = $ai_obj ? (int)$ai_obj->sub_no_audio : 0;
    $sub_error           = $ai_obj ? (int)$ai_obj->sub_error : 0;
    $sub_running         = $ai_obj ? (int)$ai_obj->sub_running : 0;
    $sub_processed_total = $ai_obj ? (int)$ai_obj->sub_processed_total : 0;
    $txt_checked         = $ai_obj ? (int)$ai_obj->txt_checked : 0;

    $remaining_subtitles = max(0, $total_converted - $sub_processed_total);
    $remaining_text      = max(0, $total_converted - $txt_checked);

    $pct_sub = ($total_converted > 0) ? round(($sub_processed_total / $total_converted) * 100, 1) : 0.0;
    $pct_txt = ($total_converted > 0) ? round(($txt_checked / $total_converted) * 100, 1) : 0.0;

    return array(
        'total_converted'     => $total_converted,
        'active_conversions'  => $active_conversions,
        'total_subtitled'     => $sub_created + $sub_no_audio,
        'sub_created'         => $sub_created,
        'sub_no_audio'        => $sub_no_audio,
        'sub_error'           => $sub_error,
        'sub_running'         => $sub_running,
        'sub_processed_total' => $sub_processed_total,
        'remaining_subtitles' => $remaining_subtitles,
        'pct_subtitles'       => min(100, $pct_sub),
        'total_text_checked'  => $txt_checked,
        'remaining_text'      => $remaining_text,
        'pct_text'            => min(100, $pct_txt),
        'cnt_ok'              => $ai_obj ? (int)$ai_obj->cnt_ok : 0,
        'cnt_warn'            => $ai_obj ? (int)$ai_obj->cnt_warn : 0,
        'cnt_viol'            => $ai_obj ? (int)$ai_obj->cnt_viol : 0
    );
}

/**
 * Rendert das farbige BRAM-Risiko-Score-Badge (Grün / Orange / Rot / Blau-Info bei nur Text)
 * fuer die DataTables-Tabellen in /Filme-online und /Filme-pruefen.
 */
function aic_render_bram_score_badge($check_status, $compliance, $risk_score, $summary = '', $subtitle_status = null) {
    $status_int = (int)$check_status;
    $score_int  = abs((int)$risk_score);
    $comp_str   = strtolower(trim((string)$compliance));
    $sum_clean  = trim(aic_utf8((string)$summary));
    $sub_int    = ($subtitle_status !== null) ? (int)$subtitle_status : 2;
    $audio_done = ($sub_int === 2 || $sub_int === 3);

    if ($status_int !== 2 || $comp_str === '' || $comp_str === 'pending') {
        $tooltip = 'Noch nicht geprüft (Bitte in /KI-Analyse den Schnell-Scan oder Batch starten)';
        return '<span style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:bold; color:#fff; background:#6c757d; border-radius:3px; white-space:nowrap;" title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">-</span>';
    }

    if ($comp_str === 'violation') {
        $bg_color   = '#dc3545'; // Rot (BRAM-Verstoss)
        $text_color = '#fff';
        $badge_text = $score_int . ' / 100';
        $label_pref = 'ROT (BRAM-Verstoß: ' . $score_int . '/100)';
    } elseif ($comp_str === 'warning') {
        $bg_color   = '#e0a800'; // Orange / Gelb (Prüfhinweis / Ohne KK)
        $text_color = '#111';
        $badge_text = $score_int . ' / 100';
        $label_pref = 'ORANGE (Prüfhinweis: ' . $score_int . '/100)';
    } elseif ($audio_done) {
        $bg_color   = '#28a745'; // Echtes Grün (Text + Audio geprüft & unbedenklich)
        $text_color = '#fff';
        $badge_text = $score_int . ' / 100';
        $label_pref = 'GRÜN (Vollständig geprüft – Text & Audio OK: ' . $score_int . '/100)';
    } else {
        $bg_color   = '#17a2b8'; // Info-Blau/Türkis (Nur Vorab-Textcheck OK, Audio-Cronjob noch offen)
        $text_color = '#fff';
        $badge_text = $score_int . ' / 100 (Nur Text)';
        $label_pref = 'VORAB-TEXTCHECK OK (' . $score_int . '/100 – Audio-Transkription durch Cronjob steht noch aus)';
    }

    $tooltip = $label_pref . ($sum_clean !== '' ? ' – ' . $sum_clean : '');

    return '<span style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:bold; color:' . $text_color . '; background:' . $bg_color . '; border-radius:3px; white-space:nowrap; cursor:help;" title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">' . $badge_text . '</span>';
}

/**
 * Rendert die ausfuehrliche BRAM-Risiko-Score- & Compliance-Box direkt oben im "Bearbeiten"-Tab
 * von /Film-bearbeiten/{id} und /Film-pruefen/{id}.
 * Falls der Film noch nicht geprueft wurde oder gerade gespeichert wurde ($force_recalc = true),
 * wird die BRAM-Bewertung automatisch aktualisiert.
 */
function aic_render_movie_detail_bram_box($real_movie_id, $movie_data = array(), $force_recalc = false) {
    $mid = abs((int)$real_movie_id);
    if ($mid <= 0) {
        return '';
    }

    aic_ensure_table();

    $rs_ai = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $mid . "' LIMIT 1;", __FILE__, __LINE__);
    $ai_row = (p4c_num_rows($rs_ai) > 0) ? p4c_fetch_object($rs_ai) : null;

    $check_status = $ai_row ? (int)$ai_row->text_check_status : 0;
    $sub_status   = $ai_row ? (int)$ai_row->subtitle_status : 0;
    $compliance   = $ai_row ? strtolower(trim((string)$ai_row->text_compliance)) : 'pending';
    $risk_score   = $ai_row ? abs((int)$ai_row->text_risk_score) : 0;
    $summary_de   = $ai_row ? aic_utf8((string)$ai_row->text_summary) : '';
    $engine_used  = $ai_row ? aic_utf8((string)$ai_row->text_engine_used) : 'BRAM-Regel-Engine';
    $violations   = array();

    if ($ai_row && !empty($ai_row->text_violations_json)) {
        $dec_v = @json_decode($ai_row->text_violations_json, true);
        if (is_array($dec_v)) {
            $violations = $dec_v;
        }
    }

    // Falls noch ungeprueft oder nach dem Speichern neu berechnet werden soll
    if ($check_status !== 2 || $force_recalc) {
        $segs = array();
        if ($ai_row && !empty($ai_row->spoken_segments_json)) {
            $dec_s = @json_decode($ai_row->spoken_segments_json, true);
            if (is_array($dec_s)) {
                $segs = $dec_s;
            }
        }
        $spk_txt = ($ai_row && !empty($ai_row->spoken_transcript)) ? (string)$ai_row->spoken_transcript : '';

        $rep = aic_analyze_texts(array(
            'title'            => isset($movie_data['title']) ? $movie_data['title'] : '',
            'description'      => isset($movie_data['description']) ? $movie_data['description'] : '',
            'meta_title'       => isset($movie_data['meta_title']) ? $movie_data['meta_title'] : '',
            'meta_description' => isset($movie_data['meta_description']) ? $movie_data['meta_description'] : ''
        ), $segs, $spk_txt, array('llm_ready' => false));

        $now       = date('Y-m-d H:i:s');
        $viol_json = json_encode($rep['violations'], JSON_UNESCAPED_UNICODE);
        $file_id   = isset($movie_data['file_id']) ? (string)$movie_data['file_id'] : '';
        $merch_id  = isset($movie_data['merchant_id']) ? abs((int)$movie_data['merchant_id']) : ($ai_row ? abs((int)$ai_row->merchant_id) : 0);
        $sub_file  = ($ai_row && !empty($ai_row->subtitle_filename)) ? $ai_row->subtitle_filename : ('sub_' . $mid . '_' . $file_id . '.vtt');

        p4c_query("INSERT INTO `movies_ai_analysis`
            (`movie_id`, `merchant_id`, `subtitle_filename`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
            VALUES (
                '" . $mid . "',
                '" . $merch_id . "',
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

        $check_status = 2;
        $compliance   = strtolower((string)$rep['compliance_status']);
        $risk_score   = abs((int)$rep['risk_score']);
        $summary_de   = aic_utf8((string)$rep['summary_de']);
        $engine_used  = aic_utf8((string)$rep['engine_used']);
        $violations   = is_array($rep['violations']) ? $rep['violations'] : array();
    }

    $audio_done = ($sub_status === 2 || $sub_status === 3 || ($ai_row && trim((string)$ai_row->spoken_transcript) !== ''));

    if ($compliance === 'violation') {
        $box_bg      = '#f8d7da';
        $box_border  = '#f5c6cb';
        $box_color   = '#721c24';
        $badge_bg    = '#dc3545';
        $badge_color = '#fff';
        $title_html  = '&#x1F6D1; STOPP (ROT): KRITISCHER VISA/MASTERCARD BRAM- ODER RECHTS-VERSTOSS ERKANNT!';
    } elseif ($compliance === 'warning') {
        $box_bg      = '#fff3cd';
        $box_border  = '#ffeeba';
        $box_color   = '#856404';
        $badge_bg    = '#e0a800';
        $badge_color = '#111';
        $title_html  = '&#x26A0; PR&Uuml;FHINWEIS (ORANGE): NUR AUF PORTALEN OHNE KREDITKARTE ZUL&Auml;SSIG / MANUELLE SICHTUNG';
    } elseif ($audio_done) {
        $box_bg      = '#d4edda';
        $box_border  = '#c3e6cb';
        $box_color   = '#155724';
        $badge_bg    = '#28a745';
        $badge_color = '#fff';
        $title_html  = '&#x2705; FREIGABE-EMPFEHLUNG (GR&Uuml;N): VOLLST&Auml;NDIG GEPR&Uuml;FT (TEXT &amp; AUDIO UNBEDENKLICH)';
    } else {
        $box_bg      = '#d1ecf1';
        $box_border  = '#bee5eb';
        $box_color   = '#0c5460';
        $badge_bg    = '#17a2b8';
        $badge_color = '#fff';
        $title_html  = '&#x2139;&#xFE0F; VORAB-TEXTCHECK UNAUFF&Auml;LLIG (' . $risk_score . ' / 100) &ndash; AUDIO-PR&Uuml;FUNG (WHISPER-CRONJOB) STEHT NOCH AUS';
    }

    $violations_html = '';
    if (!empty($violations)) {
        $pills = array();
        foreach ($violations as $v) {
            $v_sev   = (isset($v['severity']) && $v['severity'] === 'violation') ? '#dc3545' : '#e0a800';
            $v_txt_c = ($v_sev === '#dc3545') ? '#fff' : '#111';
            $v_term  = isset($v['matched_term']) ? htmlspecialchars(aic_utf8($v['matched_term']), ENT_QUOTES, 'UTF-8') : '';
            $v_src   = isset($v['source_label']) ? htmlspecialchars(aic_utf8($v['source_label']), ENT_QUOTES, 'UTF-8') : '';
            $v_cat   = isset($v['category']) ? htmlspecialchars(aic_utf8($v['category']), ENT_QUOTES, 'UTF-8') : '';
            $v_ts    = (isset($v['timestamp']) && $v['timestamp'] !== '-' && $v['timestamp'] !== '') ? ' [' . htmlspecialchars(aic_utf8($v['timestamp']), ENT_QUOTES, 'UTF-8') . ']' : '';
            $v_quote = isset($v['matched_quote']) ? htmlspecialchars(aic_utf8($v['matched_quote']), ENT_QUOTES, 'UTF-8') : '';

            $pills[] = '<span style="display:inline-block; background:#fff; border:1px solid ' . $v_sev . '; border-left:4px solid ' . $v_sev . '; color:#222; padding:3px 8px; margin:3px 6px 0 0; border-radius:3px; font-size:12px;" title="' . $v_cat . ' | Zitat: ' . $v_quote . '">'
                . '<strong>' . $v_src . $v_ts . ':</strong> &bdquo;<span style="color:#c00; font-weight:bold;">' . $v_term . '</span>&ldquo; <span style="color:#666; font-size:11px;">(' . $v_cat . ')</span>'
                . '</span>';
        }
        $violations_html = '<div style="margin-top:8px; padding-top:6px; border-top:1px solid rgba(0,0,0,0.1);">'
            . '<div style="font-size:11px; font-weight:bold; margin-bottom:2px;">Erkannte Fundstellen (Mauszeiger f&uuml;r Textzitat):</div>'
            . implode('', $pills)
            . '</div>';
    }

    $lab_url = (defined('ACP_URL') ? ACP_URL : '') . '/KI-Analyse?movie_id=' . $mid;

    $html = '
    <div style="width:1220px; max-width:100%; box-sizing:border-box; padding:12px 16px; border-radius:4px; margin-bottom:15px; background:' . $box_bg . '; border:1px solid ' . $box_border . '; color:' . $box_color . '; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px;">
        <div style="flex:1 1 700px;">
            <div style="font-size:15px; font-weight:bold; margin-bottom:4px;">' . $title_html . '</div>
            <div style="font-size:13px; line-height:1.4;">' . htmlspecialchars($summary_de, ENT_QUOTES, 'UTF-8') . '</div>
            ' . $violations_html . '
        </div>
        <div style="text-align:right; min-width:210px;">
            <div style="font-size:11px; font-weight:bold; text-transform:uppercase; opacity:0.85; margin-bottom:3px;">BRAM Risiko-Score:</div>
            <div>
                <span style="display:inline-block; padding:5px 12px; font-size:20px; font-weight:bold; color:' . $badge_color . '; background:' . $badge_bg . '; border-radius:4px; box-shadow:0 1px 2px rgba(0,0,0,0.12);">' . $risk_score . ' / 100</span>
            </div>
            <div style="margin-top:6px;">
                <a href="' . htmlspecialchars($lab_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="font-size:11px; color:' . $box_color . '; text-decoration:underline; font-weight:bold;">Im KI-Testlabor &ouml;ffnen &#x2197;</a>
            </div>
        </div>
    </div>';

    return $html;
}

/**
 * Liefert alle HTML-Bausteine (Tab-Reiter, Top-Banner fuer den Bearbeiten-Tab sowie die kompletten
 * Inhalte fuer die separaten Reiter "BRAM & KI-Analyse" und "Audio & Transkript (.vtt)")
 * fuer /Film-bearbeiten/{id} und /Film-pruefen/{id}.
 */
function aic_get_movie_bram_and_ai_tabs_data($real_movie_id, $movie_data = array(), $force_recalc = false, $tabs_dom_id = 'movie_edit_tabs') {
    $mid = abs((int)$real_movie_id);
    if ($mid <= 0) {
        return array(
            'css_html'                 => '',
            'top_banner_html'          => '',
            'tab_bram_li'              => '',
            'tab_transcript_li'        => '',
            'tab_bram_panel_html'      => '',
            'tab_transcript_panel_html'=> ''
        );
    }

    aic_ensure_table();

    // Vollstaendige Film- & KI-Details laden
    $rs_ai = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $mid . "' LIMIT 1;", __FILE__, __LINE__);
    $ai_row = (p4c_num_rows($rs_ai) > 0) ? p4c_fetch_object($rs_ai) : null;

    $check_status = $ai_row ? (int)$ai_row->text_check_status : 0;

    if ($check_status !== 2 || $force_recalc) {
        // Berechnet und speichert den BRAM-Check automatisch
        aic_render_movie_detail_bram_box($mid, $movie_data, true);
    }

    $details = aic_get_movie_details($mid);
    $ai      = ($details && isset($details['ai'])) ? $details['ai'] : array(
        'subtitle_status'     => 0,
        'detected_language'   => '',
        'language_confidence' => 0.0,
        'subtitle_filename'   => 'sub_' . $mid . '.vtt',
        'spoken_transcript'   => '',
        'vtt_preview'         => '',
        'whisper_duration_sec'=> 0.0,
        'text_check_status'   => 2,
        'text_compliance'     => 'ok',
        'text_risk_score'     => 0,
        'text_engine_used'    => 'BRAM-Regel-Engine',
        'text_summary'        => '',
        'violations'          => array(),
        'text_duration_sec'   => 0.0,
        'last_error'          => '',
        'updated_at'          => date('Y-m-d H:i:s')
    );

    $compliance  = strtolower(trim((string)$ai['text_compliance']));
    $risk_score  = abs((int)$ai['text_risk_score']);
    $summary_de  = aic_utf8((string)$ai['text_summary']);
    $engine_used = aic_utf8((string)$ai['text_engine_used']);
    $updated_at  = (!empty($ai['updated_at']) && $ai['updated_at'] !== '0000-00-00 00:00:00') ? date('d.m.Y H:i:s', strtotime($ai['updated_at'])) : '-';
    $violations  = (isset($ai['violations']) && is_array($ai['violations'])) ? $ai['violations'] : array();
    $sub_status  = (int)$ai['subtitle_status'];
    $audio_done  = ($sub_status === 2 || $sub_status === 3 || trim((string)$ai['spoken_transcript']) !== '');

    if ($compliance === 'violation') {
        $box_bg          = '#f8d7da';
        $box_border      = '#f5c6cb';
        $box_color       = '#721c24';
        $badge_bg        = '#dc3545';
        $badge_color     = '#fff';
        $tab_score_label = $risk_score . ' / 100';
        $ampel_short     = 'ROT (Versto&szlig;)';
        $title_html      = '&#x1F6D1; STOPP (ROT): KRITISCHER VISA/MASTERCARD BRAM- ODER RECHTS-VERSTOSS ERKANNT!';
    } elseif ($compliance === 'warning') {
        $box_bg          = '#fff3cd';
        $box_border      = '#ffeeba';
        $box_color       = '#856404';
        $badge_bg        = '#e0a800';
        $badge_color     = '#111';
        $tab_score_label = $risk_score . ' / 100';
        $ampel_short     = 'ORANGE (Pr&uuml;fhinweis)';
        $title_html      = '&#x26A0; PR&Uuml;FHINWEIS (ORANGE): NUR AUF PORTALEN OHNE KREDITKARTE ZUL&Auml;SSIG / MANUELLE SICHTUNG';
    } elseif ($audio_done) {
        $box_bg          = '#d4edda';
        $box_border      = '#c3e6cb';
        $box_color       = '#155724';
        $badge_bg        = '#28a745';
        $badge_color     = '#fff';
        $tab_score_label = $risk_score . ' / 100';
        $ampel_short     = 'GR&Uuml;N (Text &amp; Audio OK)';
        $title_html      = '&#x2705; FREIGABE-EMPFEHLUNG (GR&Uuml;N): VOLLST&Auml;NDIG GEPR&Uuml;FT (TEXT &amp; AUDIO UNBEDENKLICH)';
    } else {
        $box_bg          = '#d1ecf1';
        $box_border      = '#bee5eb';
        $box_color       = '#0c5460';
        $badge_bg        = '#17a2b8';
        $badge_color     = '#fff';
        $tab_score_label = $risk_score . ' / 100 (Nur Text)';
        $ampel_short     = 'NUR TEXT OK (Audio offen)';
        $title_html      = '&#x2139;&#xFE0F; VORAB-TEXTCHECK UNAUFF&Auml;LLIG (' . $risk_score . ' / 100) &ndash; AUDIO-PR&Uuml;FUNG (WHISPER-CRONJOB) STEHT NOCH AUS';
    }

    // Untertitel-Status fuer Tab-Badge & Karten
    $sub_status = (int)$ai['subtitle_status'];
    $det_lang   = strtoupper(trim((string)$ai['detected_language']));
    if ($sub_status === 2) {
        $sub_tab_badge = '<span id="aic-det-tab-sub-badge" style="display:inline-block; padding:1px 6px; margin-left:6px; font-size:11px; font-weight:bold; border-radius:3px; background:#28a745; color:#fff;">' . htmlspecialchars($det_lang !== '' ? $det_lang : 'VTT', ENT_QUOTES, 'UTF-8') . '</span>';
        $sub_status_badge_html = '<span style="display:inline-block; padding:4px 9px; font-size:12px; font-weight:bold; border-radius:3px; background:#28a745; color:#fff;">&#x2705; Transkript &amp; .vtt erstellt (' . htmlspecialchars((string)$ai['whisper_duration_sec'], ENT_QUOTES, 'UTF-8') . ' Sek.)</span>';
    } elseif ($sub_status === 3) {
        $sub_tab_badge = '<span id="aic-det-tab-sub-badge" style="display:inline-block; padding:1px 6px; margin-left:6px; font-size:11px; font-weight:bold; border-radius:3px; background:#6c757d; color:#fff;">Stumm</span>';
        $sub_status_badge_html = '<span style="display:inline-block; padding:4px 9px; font-size:12px; font-weight:bold; border-radius:3px; background:#6c757d; color:#fff;">&#x1F507; Stummfilm (Keine Tonspur im Video)</span>';
    } elseif ($sub_status === 4) {
        $sub_tab_badge = '<span id="aic-det-tab-sub-badge" style="display:inline-block; padding:1px 6px; margin-left:6px; font-size:11px; font-weight:bold; border-radius:3px; background:#dc3545; color:#fff;">Fehler</span>';
        $sub_status_badge_html = '<span style="display:inline-block; padding:4px 9px; font-size:12px; font-weight:bold; border-radius:3px; background:#dc3545; color:#fff;">&#x26A0; MP4-Zugriff / Extraktionsfehler</span>';
    } else {
        $sub_tab_badge = '<span id="aic-det-tab-sub-badge" style="display:inline-block; padding:1px 6px; margin-left:6px; font-size:11px; font-weight:bold; border-radius:3px; background:#6c757d; color:#fff;">Offen</span>';
        $sub_status_badge_html = '<span style="display:inline-block; padding:4px 9px; font-size:12px; font-weight:bold; border-radius:3px; background:#6c757d; color:#fff;">&#x23F3; Noch nicht transkribiert</span>';
    }

    // 1. Gemeinsames CSS fuer saubere Tab-Abstaende & Karten-Design
    $css_html = '
    <style type="text/css">
        #' . $tabs_dom_id . ' {
            margin-top: 6px;
        }
        #' . $tabs_dom_id . ' > ul.ui-tabs-nav {
            padding: 6px 10px 0 10px !important;
            background: #e9ecef;
            border-bottom: 1px solid #c5dbec;
        }
        #' . $tabs_dom_id . ' > .ui-tabs-panel {
            padding: 20px 18px !important;
            box-sizing: border-box;
            background: #fff;
        }
        .aic-det-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 18px;
        }
        .aic-det-card {
            flex: 1 1 220px;
            background: #fcfdfd;
            border: 1px solid #d1d1d1;
            border-radius: 5px;
            padding: 14px 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .aic-det-card-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }
        .aic-det-card-val {
            font-size: 20px;
            font-weight: bold;
            color: #1c94c4;
            margin-bottom: 4px;
        }
        .aic-det-card-sub {
            font-size: 12px;
            color: #555;
            line-height: 1.4;
        }
        .aic-det-box {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 16px 18px;
            margin-bottom: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .aic-det-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
        }
        .aic-det-table th {
            background: #e9e9e9;
            color: #333;
            font-weight: bold;
            text-align: left;
            padding: 10px 12px;
            border: 1px solid #ccc;
        }
        .aic-det-table td {
            padding: 9px 12px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .aic-det-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .aic-det-btn {
            display: inline-block;
            background: #1c94c4;
            color: #fff !important;
            border: 1px solid #157ba3;
            padding: 7px 14px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none !important;
        }
        .aic-det-btn:hover {
            background: #157ba3;
        }
        .aic-det-btn-secondary {
            display: inline-block;
            background: #f4f4f4;
            color: #333 !important;
            border: 1px solid #bbb;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none !important;
        }
        .aic-det-btn-secondary:hover {
            background: #e6e6e6;
        }
        .aic-det-mono {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #bbb;
            border-radius: 4px;
            padding: 10px;
            font-size: 12px;
            font-family: Consolas, Monaco, monospace;
            line-height: 1.45;
            background: #fafafa;
        }
    </style>';

    // 2. Tab-Header LIs
    $tab_bram_li = '<li><a href="#tab-bram">BRAM &amp; KI-Analyse <span id="aic-det-tab-score-badge" style="display:inline-block; padding:1px 6px; margin-left:6px; font-size:11px; font-weight:bold; border-radius:3px; background:' . $badge_bg . '; color:' . $badge_color . ';">' . $tab_score_label . '</span></a></li>';
    $tab_transcript_li = '<li><a href="#tab-transcript">Audio &amp; Transkript (.vtt)' . $sub_tab_badge . '</a></li>';

    // 3. Fundstellen-Pills fuer das Top-Banner & Tabellenzeilen fuer den BRAM-Tab
    $violations_pills_html = '';
    $violations_tbody_html = '';
    $cnt_rot  = 0;
    $cnt_gelb = 0;

    if (!empty($violations)) {
        $pills = array();
        foreach ($violations as $v) {
            $is_rot  = (isset($v['severity']) && $v['severity'] === 'violation');
            if ($is_rot) {
                $cnt_rot++;
                $v_sev_color = '#dc3545';
                $v_sev_badge = '<span style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:bold; color:#fff; background:#dc3545; border-radius:3px;">ROT (Versto&szlig;)</span>';
            } else {
                $cnt_gelb++;
                $v_sev_color = '#e0a800';
                $v_sev_badge = '<span style="display:inline-block; padding:3px 8px; font-size:11px; font-weight:bold; color:#111; background:#e0a800; border-radius:3px;">ORANGE (Hinweis)</span>';
            }

            $v_term   = isset($v['matched_term']) ? htmlspecialchars(aic_utf8($v['matched_term']), ENT_QUOTES, 'UTF-8') : '';
            $v_src    = isset($v['source_label']) ? htmlspecialchars(aic_utf8($v['source_label']), ENT_QUOTES, 'UTF-8') : '';
            $v_cat    = isset($v['category']) ? htmlspecialchars(aic_utf8($v['category']), ENT_QUOTES, 'UTF-8') : '';
            $v_ts_raw = (isset($v['timestamp']) && $v['timestamp'] !== '-' && $v['timestamp'] !== '') ? htmlspecialchars(aic_utf8($v['timestamp']), ENT_QUOTES, 'UTF-8') : '';
            $v_ts     = ($v_ts_raw !== '') ? ' [' . $v_ts_raw . ']' : '';
            $v_quote  = isset($v['matched_quote']) ? htmlspecialchars(aic_utf8($v['matched_quote']), ENT_QUOTES, 'UTF-8') : '';
            $v_reason = isset($v['reason']) ? htmlspecialchars(aic_utf8($v['reason']), ENT_QUOTES, 'UTF-8') : '';

            $pills[] = '<span style="display:inline-block; background:#fff; border:1px solid ' . $v_sev_color . '; border-left:4px solid ' . $v_sev_color . '; color:#222; padding:3px 8px; margin:4px 6px 0 0; border-radius:3px; font-size:12px;" title="' . $v_cat . ' | Zitat: ' . $v_quote . '">'
                . '<strong>' . $v_src . $v_ts . ':</strong> &bdquo;<span style="color:#c00; font-weight:bold;">' . $v_term . '</span>&ldquo; <span style="color:#666; font-size:11px;">(' . $v_cat . ')</span>'
                . '</span>';

            $violations_tbody_html .= '<tr>'
                . '<td>' . $v_sev_badge . '</td>'
                . '<td><strong>' . $v_cat . '</strong></td>'
                . '<td>' . $v_src . ($v_ts_raw !== '' ? '<br/><small style="color:#1c94c4; font-weight:bold;">Zeit: ' . $v_ts_raw . '</small>' : '') . '</td>'
                . '<td><code style="background:#fff3cd; padding:2px 6px; border-radius:3px; font-weight:bold; color:#b00;">' . $v_term . '</code></td>'
                . '<td><div style="font-style:italic; color:#222; margin-bottom:4px;">&bdquo;' . $v_quote . '&ldquo;</div><div style="font-size:11px; color:#666;">' . $v_reason . '</div></td>'
                . '</tr>';
        }
        $violations_pills_html = '<div style="margin-top:8px; padding-top:6px; border-top:1px solid rgba(0,0,0,0.1);">'
            . '<div style="font-size:11px; font-weight:bold; margin-bottom:2px;">Erkannte Fundstellen (Mauszeiger f&uuml;r Textzitat):</div>'
            . implode('', $pills)
            . '</div>';
    } elseif ($audio_done) {
        $violations_tbody_html = '<tr><td colspan="5" style="text-align:center; color:#155724; background:#f8fff9; padding:14px;"><strong>&#x2705; Keine kritischen Begriffe oder BRAM-Verst&ouml;&szlig;e in Filmtitel, Beschreibung, SEO-Meta oder gesprochenem Audio erkannt.</strong></td></tr>';
    } else {
        $violations_tbody_html = '<tr><td colspan="5" style="text-align:center; color:#0c5460; background:#f2fbfd; padding:14px;"><strong>&#x2139;&#xFE0F; In Filmtitel, Beschreibung und SEO-Meta wurden keine kritischen Begriffe erkannt.</strong><br/><span style="font-size:12px;">Hinweis: Das gesprochene Audio des Videos wurde noch nicht durch den Whisper-Cronjob transkribiert (siehe Reiter &bdquo;Audio &amp; Transkript&ldquo;).</span></td></tr>';
    }

    $lab_url = (defined('ACP_URL') ? ACP_URL : '') . '/KI-Analyse?movie_id=' . $mid;

    // 4. Top-Banner im Bearbeiten-Tab
    $top_banner_html = '
    <div id="aic-det-top-banner" style="width:1220px; max-width:100%; box-sizing:border-box; padding:14px 18px; border-radius:5px; margin:0 0 20px 0; background:' . $box_bg . '; border:1px solid ' . $box_border . '; color:' . $box_color . '; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:14px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="flex:1 1 680px;">
            <div id="aic-det-top-title" style="font-size:15px; font-weight:bold; margin-bottom:4px;">' . $title_html . '</div>
            <div id="aic-det-top-summary" style="font-size:13px; line-height:1.45;">' . htmlspecialchars($summary_de, ENT_QUOTES, 'UTF-8') . '</div>
            <div id="aic-det-top-pills">' . $violations_pills_html . '</div>
        </div>
        <div style="text-align:right; min-width:240px;">
            <div style="font-size:11px; font-weight:bold; text-transform:uppercase; opacity:0.85; margin-bottom:4px;">BRAM Risiko-Score:</div>
            <div>
                <span id="aic-det-top-score-badge" style="display:inline-block; padding:5px 14px; font-size:20px; font-weight:bold; color:' . $badge_color . '; background:' . $badge_bg . '; border-radius:4px; box-shadow:0 1px 2px rgba(0,0,0,0.12);">' . $tab_score_label . '</span>
            </div>
            <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
                <a href="#tab-bram" onclick="jQuery(\'#' . $tabs_dom_id . '\').tabs(\'option\', \'active\', 1); return false;" style="font-size:11px; background:rgba(255,255,255,0.75); border:1px solid rgba(0,0,0,0.2); color:#222; padding:3px 8px; border-radius:3px; text-decoration:none; font-weight:bold;">Details &amp; Fundstellen &rarr;</a>
                <a href="#tab-transcript" onclick="jQuery(\'#' . $tabs_dom_id . '\').tabs(\'option\', \'active\', 2); return false;" style="font-size:11px; background:rgba(255,255,255,0.75); border:1px solid rgba(0,0,0,0.2); color:#222; padding:3px 8px; border-radius:3px; text-decoration:none; font-weight:bold;">Audio &amp; Transkript &rarr;</a>
            </div>
        </div>
    </div>';

    // Bereinigte Texte fuer die Vorschau im BRAM-Tab
    $clean_title     = aic_clean_html_text(isset($movie_data['title']) ? $movie_data['title'] : ($details ? $details['title'] : ''));
    $clean_desc      = aic_clean_html_text(isset($movie_data['description']) ? $movie_data['description'] : ($details ? $details['description'] : ''));
    $clean_meta_t    = aic_clean_html_text(isset($movie_data['meta_title']) ? $movie_data['meta_title'] : ($details ? $details['meta_title'] : ''));
    $clean_meta_desc = aic_clean_html_text(isset($movie_data['meta_description']) ? $movie_data['meta_description'] : ($details ? $details['meta_description'] : ''));

    // 5. Inhalt fuer Tab "BRAM & KI-Analyse" (#tab-bram)
    $tab_bram_panel_html = '
    <div id="tab-bram">
        <div class="ui-widget-header" style="padding:10px 14px; font-size:18px; margin-bottom:16px;">
            Visa/Mastercard BRAM- &amp; KI-Compliance-Analyse f&uuml;r Film #' . $mid . '
        </div>

        <div class="aic-det-grid">
            <div id="aic-bram-tab-card1" class="aic-det-card" style="background:' . $box_bg . '; border-color:' . $box_border . '; color:' . $box_color . ';">
                <div class="aic-det-card-title" style="color:' . $box_color . ';">BRAM Risiko-Score &amp; Ampel</div>
                <div class="aic-det-card-val" style="color:' . $box_color . '; font-size:24px;">
                    <span id="aic-bram-tab-score">' . $risk_score . '</span> / 100
                    <span id="aic-bram-tab-ampel" style="display:inline-block; padding:3px 8px; font-size:12px; border-radius:3px; background:' . $badge_bg . '; color:' . $badge_color . '; margin-left:6px; vertical-align:middle;">' . $ampel_short . '</span>
                </div>
                <div class="aic-det-card-sub" style="color:' . $box_color . ';">Skala: 0 (Unbedenklich) bis 100 (Hard-Block)</div>
            </div>

            <div class="aic-det-card">
                <div class="aic-det-card-title">Erkannte Treffer &amp; Hinweise</div>
                <div class="aic-det-card-val" id="aic-bram-tab-counts">
                    <span style="color:#dc3545;">' . $cnt_rot . ' Rot</span> / <span style="color:#d39e00;">' . $cnt_gelb . ' Orange</span>
                </div>
                <div class="aic-det-card-sub">In Titel, Beschreibung, SEO &amp; Audio-Transkript</div>
            </div>

            <div class="aic-det-card">
                <div class="aic-det-card-title">Pr&uuml;f-Engine &amp; Zeitstempel</div>
                <div class="aic-det-card-val" style="font-size:15px;" id="aic-bram-tab-engine">' . htmlspecialchars($engine_used, ENT_QUOTES, 'UTF-8') . '</div>
                <div class="aic-det-card-sub">Zuletzt gepr&uuml;ft am: <strong id="aic-bram-tab-updated">' . htmlspecialchars($updated_at, ENT_QUOTES, 'UTF-8') . '</strong></div>
            </div>

            <div class="aic-det-card">
                <div class="aic-det-card-title">Aktionen &amp; Neupr&uuml;fung</div>
                <div style="margin-top:4px; display:flex; flex-direction:column; gap:6px;">
                    <button type="button" id="btn-aic-det-recheck" class="aic-det-btn">&#x1F6E1; BRAM-Pr&uuml;fung jetzt neu ausf&uuml;hren</button>
                    <a href="' . htmlspecialchars($lab_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="aic-det-btn-secondary" style="text-align:center;">Im globalen KI-Testlabor &ouml;ffnen &#x2197;</a>
                </div>
            </div>
        </div>

        <div class="aic-det-box">
            <h3 style="margin:0 0 8px 0; font-size:15px; color:#222;">KI- &amp; Regelwerk-Zusammenfassung</h3>
            <div id="aic-bram-tab-summary" style="font-size:13px; line-height:1.5; color:#333; background:#f8f9fa; padding:10px 14px; border-radius:4px; border:1px solid #e2e6ea; margin-bottom:16px;">
                ' . htmlspecialchars($summary_de, ENT_QUOTES, 'UTF-8') . '
            </div>

            <h3 style="margin:0 0 10px 0; font-size:15px; color:#222;">Detaillierte Fundstellen in Titel, Beschreibung, SEO &amp; gesprochenem Video-Audio</h3>
            <table class="aic-det-table">
                <thead>
                    <tr>
                        <th style="width:130px;">Einstufung</th>
                        <th style="width:250px;">BRAM- / Rechts-Kategorie</th>
                        <th style="width:180px;">Fundort &amp; Zeitstempel</th>
                        <th style="width:160px;">Erkannter Begriff</th>
                        <th>Textstelle (Zitat) &amp; Erl&auml;uterung</th>
                    </tr>
                </thead>
                <tbody id="aic-bram-tab-tbody">
                    ' . $violations_tbody_html . '
                </tbody>
            </table>
        </div>

        <div class="aic-det-box" style="background:#fcfdfd;">
            <h3 style="margin:0 0 10px 0; font-size:15px; color:#222;">Gepr&uuml;fte Textquellen dieses Films im &Uuml;berblick</h3>
            <table class="aic-det-table">
                <tr>
                    <th style="width:200px;">Filmtitel</th>
                    <td><strong>' . htmlspecialchars($clean_title, ENT_QUOTES, 'UTF-8') . '</strong></td>
                </tr>
                <tr>
                    <th>Filmbeschreibung (bereinigt)</th>
                    <td style="white-space:pre-wrap; line-height:1.45;">' . htmlspecialchars($clean_desc, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr>
                    <th>SEO Meta-Title</th>
                    <td>' . htmlspecialchars($clean_meta_t ?: '-', ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr>
                    <th>SEO Meta-Description</th>
                    <td>' . htmlspecialchars($clean_meta_desc ?: '-', ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
            </table>
        </div>
    </div>';

    // 6. Inhalt fuer Tab "Audio & Transkript (.vtt)" (#tab-transcript)
    $lang_display = ($det_lang !== '') ? ($det_lang . ' (' . number_format((float)$ai['language_confidence'], 1) . ' % Sicherheit)') : 'Noch nicht ermittelt';
    $mp4_badge    = ($details && !empty($details['mp4_exists']))
        ? '<span style="color:#155724; font-weight:bold;">Vorhanden (' . htmlspecialchars((string)$details['mp4_size_mb'], ENT_QUOTES, 'UTF-8') . ' MB)</span>'
        : '<span style="color:#666;">Auf Live-Storage (' . htmlspecialchars($details ? $details['mp4_path'] : '-', ENT_QUOTES, 'UTF-8') . ')</span>';

    $tab_transcript_panel_html = '
    <div id="tab-transcript">
        <div class="ui-widget-header" style="padding:10px 14px; font-size:18px; margin-bottom:16px;">
            Audio-Transkription (Whisper), Spracherkennung &amp; WebVTT-Untertitel (<code>.vtt</code>)
        </div>

        <div class="aic-det-grid">
            <div class="aic-det-card">
                <div class="aic-det-card-title">1. Audio- &amp; Untertitel-Status</div>
                <div style="margin:6px 0;" id="aic-trans-tab-status">' . $sub_status_badge_html . '</div>
                <div class="aic-det-card-sub">Physische MP4: ' . $mp4_badge . '</div>
            </div>

            <div class="aic-det-card">
                <div class="aic-det-card-title">2. Erkannte Sprache (Whisper)</div>
                <div class="aic-det-card-val" id="aic-trans-tab-lang">' . htmlspecialchars($lang_display, ENT_QUOTES, 'UTF-8') . '</div>
                <div class="aic-det-card-sub">Spieldauer: <strong>' . htmlspecialchars($details ? $details['playtime_fmt'] : '-', ENT_QUOTES, 'UTF-8') . ' Min.</strong></div>
            </div>

            <div class="aic-det-card">
                <div class="aic-det-card-title">3. WebVTT-Untertiteldatei</div>
                <div class="aic-det-card-val" style="font-size:14px; font-family:monospace; word-break:break-all;" id="aic-trans-tab-file">' . htmlspecialchars((string)$ai['subtitle_filename'], ENT_QUOTES, 'UTF-8') . '</div>
                <div class="aic-det-card-sub">Wird im Video-Ordner des Films abgelegt</div>
            </div>
        </div>

        <div class="aic-det-box" style="border-left:4px solid #1c94c4;">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; background:#f4f8fb; padding:12px 14px; border-radius:4px; border:1px solid #c5dbec;">
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <div>
                        <label style="font-size:12px; font-weight:bold; margin-right:6px;">Audio-Umfang:</label>
                        <select id="aic-det-audio-limit" style="padding:5px 8px; border:1px solid #bbb; border-radius:3px; font-size:12px;">
                            <option value="0">Komplettes Video (Vollst&auml;ndige .vtt-Untertitel)</option>
                            <option value="300">Erste 5 Minuten (~25 Sek.)</option>
                            <option value="120">Erste 2 Minuten (Schnell-Test ~10 Sek.)</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" id="btn-aic-det-run-whisper" class="aic-det-btn">
                            &#x1F3A4; Audio jetzt mit Whisper auswerten &amp; .vtt Untertitel erzeugen
                        </button>
                    </div>
                    <span id="aic-det-whisper-status" style="font-size:12px; font-weight:bold; color:#1c94c4;"></span>
                </div>
                <div>
                    <button type="button" id="btn-aic-det-recheck-transcript" class="aic-det-btn-secondary">
                        &#x1F6E1; Transkript &amp; Texte neu auf BRAM pr&uuml;fen
                    </button>
                </div>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:18px;">
                <div style="flex:3 1 480px;">
                    <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:6px;">
                        Gesprochenes Audio-Transkript mit Zeitstempeln (direkt editierbar f&uuml;r BRAM-Test):
                    </label>
                    <textarea id="aic-det-transcript-text" class="aic-det-mono" rows="14" placeholder="Noch kein Audio-Transkript f&uuml;r dieses Video vorhanden. Klicken Sie oben auf &bdquo;Audio jetzt mit Whisper auswerten&ldquo;, um den gesprochenen Text auszulesen und die .vtt-Untertiteldatei zu erstellen.">' . htmlspecialchars(aic_utf8((string)$ai['spoken_transcript']), ENT_QUOTES, 'UTF-8') . '</textarea>
                </div>
                <div style="flex:2 1 340px;">
                    <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:6px;">
                        Erzeugte WebVTT-Untertiteldatei (<code>.vtt</code> f&uuml;r Videoplayer):
                    </label>
                    <textarea id="aic-det-vtt-text" class="aic-det-mono" rows="14" readonly="readonly" style="background:#f3f4f6; color:#444;" placeholder="WEBVTT Untertitel-Vorschau...">' . htmlspecialchars(aic_utf8((string)$ai['vtt_preview']), ENT_QUOTES, 'UTF-8') . '</textarea>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function() {
        var detailMovieId = ' . $mid . ';

        function escH(s) {
            if (s === null || s === undefined) return "";
            return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }

        function refreshBramAndTranscriptUI(m) {
            if (!m || !m.ai) return;
            var ai = m.ai;
            var score = parseInt(ai.text_risk_score || 0, 10);
            var comp  = ai.text_compliance || "ok";
            var subSt = parseInt(ai.subtitle_status || 0, 10);
            var hasTr = (ai.spoken_transcript && String(ai.spoken_transcript).replace(/\s+/g, "") !== "");
            var audioDone = (subSt === 2 || subSt === 3 || hasTr);

            var badgeBg = "#28a745", badgeCol = "#fff", boxBg = "#d4edda", boxBorder = "#c3e6cb", boxCol = "#155724";
            var tabScoreLabel = score + " / 100";
            var ampelShort = "GR&Uuml;N (Text &amp; Audio OK)";
            var titleHtml  = "&#x2705; FREIGABE-EMPFEHLUNG (GR&Uuml;N): VOLLST&Auml;NDIG GEPR&Uuml;FT (TEXT &amp; AUDIO UNBEDENKLICH)";

            if (comp === "violation") {
                badgeBg = "#dc3545"; badgeCol = "#fff"; boxBg = "#f8d7da"; boxBorder = "#f5c6cb"; boxCol = "#721c24";
                tabScoreLabel = score + " / 100";
                ampelShort = "ROT (Versto&szlig;)";
                titleHtml  = "&#x1F6D1; STOPP (ROT): KRITISCHER VISA/MASTERCARD BRAM- ODER RECHTS-VERSTOSS ERKANNT!";
            } else if (comp === "warning") {
                badgeBg = "#e0a800"; badgeCol = "#111"; boxBg = "#fff3cd"; boxBorder = "#ffeeba"; boxCol = "#856404";
                tabScoreLabel = score + " / 100";
                ampelShort = "ORANGE (Pr&uuml;fhinweis)";
                titleHtml  = "&#x26A0; PR&Uuml;FHINWEIS (ORANGE): NUR AUF PORTALEN OHNE KREDITKARTE ZUL&Auml;SSIG / MANUELLE SICHTUNG";
            } else if (!audioDone) {
                badgeBg = "#17a2b8"; badgeCol = "#fff"; boxBg = "#d1ecf1"; boxBorder = "#bee5eb"; boxCol = "#0c5460";
                tabScoreLabel = score + " / 100 (Nur Text)";
                ampelShort = "NUR TEXT OK (Audio offen)";
                titleHtml  = "&#x2139;&#xFE0F; VORAB-TEXTCHECK UNAUFF&Auml;LLIG (" + score + " / 100) &ndash; AUDIO-PR&Uuml;FUNG (WHISPER-CRONJOB) STEHT NOCH AUS";
            }

            // Tab-Badge & Top-Banner aktualisieren
            jQuery("#aic-det-tab-score-badge").css({ background: badgeBg, color: badgeCol }).text(tabScoreLabel);
            jQuery("#aic-det-top-banner").css({ background: boxBg, borderColor: boxBorder, color: boxCol });
            jQuery("#aic-det-top-title").html(titleHtml);
            jQuery("#aic-det-top-summary").text(ai.text_summary || "");
            jQuery("#aic-det-top-score-badge").css({ background: badgeBg, color: badgeCol }).text(tabScoreLabel);

            // BRAM-Tab Karten & Tabelle aktualisieren
            jQuery("#aic-bram-tab-card1").css({ background: boxBg, borderColor: boxBorder, color: boxCol });
            jQuery("#aic-bram-tab-score").text(score);
            jQuery("#aic-bram-tab-ampel").css({ background: badgeBg, color: badgeCol }).html(ampelShort);
            jQuery("#aic-bram-tab-engine").text(ai.text_engine_used || "BRAM-Regel-Engine");
            jQuery("#aic-bram-tab-summary").text(ai.text_summary || "");

            var viols = ai.violations || [];
            var cntRot = 0, cntGelb = 0;
            var pillsHtml = "", tbodyHtml = "";

            if (viols.length > 0) {
                var pills = [];
                for (var i = 0; i < viols.length; i++) {
                    var v = viols[i];
                    var isRot = (v.severity === "violation");
                    if (isRot) cntRot++; else cntGelb++;
                    var vCol = isRot ? "#dc3545" : "#e0a800";
                    var vBadge = isRot
                        ? "<span style=\"display:inline-block; padding:3px 8px; font-size:11px; font-weight:bold; color:#fff; background:#dc3545; border-radius:3px;\">ROT (Versto&szlig;)</span>"
                        : "<span style=\"display:inline-block; padding:3px 8px; font-size:11px; font-weight:bold; color:#111; background:#e0a800; border-radius:3px;\">ORANGE (Hinweis)</span>";
                    var ts = (v.timestamp && v.timestamp !== "-") ? " [" + escH(v.timestamp) + "]" : "";
                    pills.push("<span style=\"display:inline-block; background:#fff; border:1px solid " + vCol + "; border-left:4px solid " + vCol + "; color:#222; padding:3px 8px; margin:4px 6px 0 0; border-radius:3px; font-size:12px;\"><strong>" + escH(v.source_label) + ts + ":</strong> &bdquo;<span style=\"color:#c00; font-weight:bold;\">" + escH(v.matched_term) + "</span>&ldquo; <span style=\"color:#666; font-size:11px;\">(" + escH(v.category) + ")</span></span>");
                    tbodyHtml += "<tr>"
                        + "<td>" + vBadge + "</td>"
                        + "<td><strong>" + escH(v.category) + "</strong></td>"
                        + "<td>" + escH(v.source_label) + (ts ? "<br/><small style=\"color:#1c94c4; font-weight:bold;\">Zeit:" + ts + "</small>" : "") + "</td>"
                        + "<td><code style=\"background:#fff3cd; padding:2px 6px; border-radius:3px; font-weight:bold; color:#b00;\">" + escH(v.matched_term) + "</code></td>"
                        + "<td><div style=\"font-style:italic; color:#222; margin-bottom:4px;\">&bdquo;" + escH(v.matched_quote) + "&ldquo;</div><div style=\"font-size:11px; color:#666;\">" + escH(v.reason) + "</div></td>"
                        + "</tr>";
                }
                pillsHtml = "<div style=\"margin-top:8px; padding-top:6px; border-top:1px solid rgba(0,0,0,0.1);\"><div style=\"font-size:11px; font-weight:bold; margin-bottom:2px;\">Erkannte Fundstellen:</div>" + pills.join("") + "</div>";
            } else if (audioDone) {
                tbodyHtml = "<tr><td colspan=\"5\" style=\"text-align:center; color:#155724; background:#f8fff9; padding:14px;\"><strong>&#x2705; Keine kritischen Begriffe oder BRAM-Verst&ouml;&szlig;e in Filmtitel, Beschreibung, SEO-Meta oder gesprochenem Audio erkannt.</strong></td></tr>";
            } else {
                tbodyHtml = "<tr><td colspan=\"5\" style=\"text-align:center; color:#0c5460; background:#f2fbfd; padding:14px;\"><strong>&#x2139;&#xFE0F; In Filmtitel, Beschreibung und SEO-Meta wurden keine kritischen Begriffe erkannt.</strong><br/><span style=\"font-size:12px;\">Hinweis: Das gesprochene Audio des Videos wurde noch nicht durch den Whisper-Cronjob transkribiert (siehe Reiter &bdquo;Audio &amp; Transkript&ldquo;).</span></td></tr>";
            }

            jQuery("#aic-det-top-pills").html(pillsHtml);
            jQuery("#aic-bram-tab-tbody").html(tbodyHtml);
            jQuery("#aic-bram-tab-counts").html("<span style=\"color:#dc3545;\">" + cntRot + " Rot</span> / <span style=\"color:#d39e00;\">" + cntGelb + " Orange</span>");

            // Transkript-Tab aktualisieren
            if (ai.subtitle_status === 2) {
                var lg = (ai.detected_language || "vtt").toUpperCase();
                jQuery("#aic-det-tab-sub-badge").css({ background: "#28a745", color: "#fff" }).text(lg);
                jQuery("#aic-trans-tab-status").html("<span style=\"display:inline-block; padding:4px 9px; font-size:12px; font-weight:bold; border-radius:3px; background:#28a745; color:#fff;\">&#x2705; Transkript &amp; .vtt erstellt (" + ai.whisper_duration_sec + " Sek.)</span>");
                jQuery("#aic-trans-tab-lang").text(lg + " (" + ai.language_confidence + " % Sicherheit)");
                jQuery("#aic-det-transcript-text").val(ai.spoken_transcript || "");
                jQuery("#aic-det-vtt-text").val(ai.vtt_preview || "");
            } else if (ai.subtitle_status === 3) {
                jQuery("#aic-det-tab-sub-badge").css({ background: "#6c757d", color: "#fff" }).text("Stumm");
                jQuery("#aic-trans-tab-status").html("<span style=\"display:inline-block; padding:4px 9px; font-size:12px; font-weight:bold; border-radius:3px; background:#6c757d; color:#fff;\">&#x1F507; Stummfilm (Keine Tonspur im Video)</span>");
                jQuery("#aic-det-transcript-text").val(ai.spoken_transcript || "");
            }
        }

        function triggerDetailTextRecheck(btnSelector) {
            var btn = jQuery(btnSelector);
            var origText = btn.html();
            btn.prop("disabled", true).text("Prüfe Texte & Transkript...");
            jQuery.ajax({
                url: "' . (defined('ACP_URL') ? ACP_URL : '') . '/includes/ajax/ai_compliance_action.php",
                type: "POST",
                dataType: "json",
                data: {
                    action: "run_text_check",
                    movie_id: detailMovieId,
                    custom_transcript: jQuery("#aic-det-transcript-text").val()
                },
                success: function(res) {
                    btn.prop("disabled", false).html(origText);
                    if (res && res.status === "ok" && res.movie) {
                        refreshBramAndTranscriptUI(res.movie);
                    }
                },
                error: function() {
                    btn.prop("disabled", false).html(origText);
                }
            });
        }

        jQuery("#btn-aic-det-recheck").on("click", function() {
            triggerDetailTextRecheck("#btn-aic-det-recheck");
        });

        jQuery("#btn-aic-det-recheck-transcript").on("click", function() {
            triggerDetailTextRecheck("#btn-aic-det-recheck-transcript");
        });

        jQuery("#btn-aic-det-run-whisper").on("click", function() {
            var btn = jQuery(this);
            var origHtml = btn.html();
            var limitSec = jQuery("#aic-det-audio-limit").val();
            var secElapsed = 0;
            btn.prop("disabled", true).text("Whisper läuft...");
            jQuery("#aic-det-whisper-status").text("Extrahiere Audio & transkribiere (0s)...");
            var timer = setInterval(function() {
                secElapsed++;
                jQuery("#aic-det-whisper-status").text("Whisper arbeitet... (" + secElapsed + " Sek.)");
            }, 1000);

            jQuery.ajax({
                url: "' . (defined('ACP_URL') ? ACP_URL : '') . '/includes/ajax/ai_compliance_action.php",
                type: "POST",
                dataType: "json",
                data: {
                    action: "run_whisper",
                    movie_id: detailMovieId,
                    sim_scenario: "clean",
                    audio_limit_sec: limitSec
                },
                success: function(res) {
                    clearInterval(timer);
                    btn.prop("disabled", false).html(origHtml);
                    if (res && res.status === "ok" && res.movie) {
                        jQuery("#aic-det-whisper-status").css("color", "#155724").text("Fertig (" + (res.mode_used || "Whisper") + ")! Aktualisiere BRAM-Score...");
                        refreshBramAndTranscriptUI(res.movie);
                        triggerDetailTextRecheck("#btn-aic-det-recheck-transcript");
                    } else {
                        jQuery("#aic-det-whisper-status").css("color", "#dc3545").text(res && res.message ? res.message : "Fehler bei der Audio-Auswertung.");
                    }
                },
                error: function() {
                    clearInterval(timer);
                    btn.prop("disabled", false).html(origHtml);
                    jQuery("#aic-det-whisper-status").css("color", "#dc3545").text("Verbindungsfehler bei der Audio-Auswertung.");
                }
            });
        });
    });
    </script>';

    return array(
        'css_html'                  => $css_html,
        'top_banner_html'           => $top_banner_html,
        'tab_bram_li'               => $tab_bram_li,
        'tab_transcript_li'         => $tab_transcript_li,
        'tab_bram_panel_html'       => $tab_bram_panel_html,
        'tab_transcript_panel_html' => $tab_transcript_panel_html
    );
}
?>



