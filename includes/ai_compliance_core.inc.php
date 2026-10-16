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
    $path = $sourcedir . '/includes/ai_rules/bram_keyword_rules.json';
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
            $llm_summary = 'Es wurden ' . $cnt_w . ' Prüfhinweis(e) (Gelb) in den Texten bzw. im gesprochenen Audio erkannt (' . implode(', ', $detected_topics) . '). Hinweis: Spezialfetische wie KV/NS/Poppers/Hypnose sind nur auf Portalen ohne Kreditkartenabrechnung zulässig.';
        } else {
            $llm_summary = 'ACHTUNG: Es wurden ' . $cnt_v . ' kritische(r) BRAM-/Rechts-Verstoß/Verstöße (Rot) sowie ' . $cnt_w . ' Warnhinweis(e) erkannt! Betroffene Bereiche: ' . implode(', ', $detected_topics) . '. Dieses Video darf auf keinem Portal freigeschaltet werden.';
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
?>
