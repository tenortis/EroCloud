<?php

/**
 * EroCloud High-Performance AI Gateway (api.erocloud.net/ai_gateway.php)
 *
 * Stellt die lokale KI-Infrastruktur des EroCloud-Dediziertservers (t199.nlserver.net,
 * 2x Intel Xeon E5-2690 v2 = 20 Kerne / 40 Threads, 64 GB DDR3 ECC RAM) ueber eine
 * mit Bearer-API-Key geschuetzte HTTPS-Schnittstelle fuer externe Partner-Systeme
 * (insb. Pay4Coins Support-Ticket-System) sowie interne EroCloud-Dienste bereit.
 *
 * Schutzmechanismen fuer den Live-Betrieb auf t199:
 * - Prueft vor jedem KI-Aufruf live den freien RAM (free -k / /proc/meminfo), den CPU-Load,
 *   aktive FFmpeg-Videokonvertierungen (movies.convert_status = '1' / pgrep ffmpeg) sowie
 *   laufende Whisper.cpp-Transkriptionen (erocloud_ai_worker.lock / pgrep whisper-cli).
 * - 3-Stufen-Verkehrsampel:
 *     1. GRUEN (Normalbetrieb): Volle Geschwindigkeit mit 16 von 40 Threads (keep_alive: 24h).
 *     2. GELB (FFmpeg oder Whisper aktiv): Automatische Drosselung auf 6 Threads (Schon-Modus)
 *        oder Pausierung (je nach gewaehlter busy_policy).
 *     3. ROT (Hohe Serverlast / < 2,5 GB freier RAM): Schutzsperre (busy = true).
 *
 * Kompatibel mit PHP 8.0.30 & PHP 8.4.27+.
 *
 * @author      Martin Zimmermann
 * @copyright   (C) 2026 Cipa Media S.L.U.
 */

define('SAFE_INC', 1);
@chdir(__DIR__);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");
include_once(SOURCEDIR . "/includes/ai_compliance_core.inc.php");

// Session-Lock sofort freigeben, damit parallele Requests niemals blockiert werden
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/**
 * Laedt die Gateway-Konfiguration (optional aus includes/ai_rules/ai_gateway_config.json)
 * mit vorab abgestimmtem Standard-API-Key fuer Pay4Coins.
 *
 * @return array<string, mixed>
 */
function ec_ai_gateway_load_config(): array
{
    $defaults = array(
        'api_key'              => 'p4c_ec_ai_gw_7f9a2d4e8b1c6f3a5d0e9b2c4f7a1d8e3b6c9f2a5d8e1b4c',
        'ollama_url'           => 'http://127.0.0.1:11434',
        'default_model'        => 'huihui_ai/gemma-4-abliterated:26b',
        'threads_normal'       => 16,
        'threads_busy'         => 6,
        'min_free_ram_mb'      => 2500,
        'max_load_ratio'       => 0.85,
        'keep_alive'           => '24h',
    );

    $cfgPath = SOURCEDIR . '/includes/ai_rules/ai_gateway_config.json';
    if (@is_file($cfgPath) && @is_readable($cfgPath)) {
        $raw = (string)@file_get_contents($cfgPath);
        $dec = @json_decode($raw, true);
        if (is_array($dec)) {
            $defaults = array_merge($defaults, $dec);
        }
    }

    return $defaults;
}

/**
 * Extrahiert den uebergebenen API-Key aus Header (Authorization Bearer / X-AI-Api-Key) oder Request-Payload.
 * Dadurch funktioniert die Authentifizierung auch unter Apache mod_fcgid zu 100 % zuverlaessig.
 */
function ec_ai_gateway_extract_api_key(array $jsonBody): string
{
    if (!empty($_SERVER['HTTP_X_AI_API_KEY'])) {
        return trim((string)$_SERVER['HTTP_X_AI_API_KEY']);
    }

    $authHeader = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($authHeader !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
        return trim((string)$m[1]);
    }

    if (!empty($jsonBody['api_key'])) {
        return trim((string)$jsonBody['api_key']);
    }

    if (!empty($_POST['api_key'])) {
        return trim((string)$_POST['api_key']);
    }

    return '';
}

/**
 * Ermittelt live die Hardware-, RAM-, CPU-Load-, FFmpeg- und Whisper-Auslastung auf dem EroCloud-Server (t199).
 *
 * @return array<string, mixed>
 */
function ec_ai_gateway_get_server_state(array $gwCfg): array
{
    $cpuThreads = 40; // Baseline fuer t199: 2x Intel Xeon E5-2690 v2 (20 Kerne / 40 Threads)
    $cpuModel   = '2x Intel Xeon E5-2690 v2 @ 3.00GHz (20 Kerne / 40 Threads)';

    if (DIRECTORY_SEPARATOR === '/' && function_exists('exec')) {
        $nprocOut = array();
        $nprocRet = 1;
        @exec('nproc 2>/dev/null', $nprocOut, $nprocRet);
        if ($nprocRet === 0 && !empty($nprocOut[0]) && (int)$nprocOut[0] > 0) {
            $cpuThreads = (int)$nprocOut[0];
        }
    }

    // 1. RAM-Werte ermitteln (/proc/meminfo oder via `free -k` falls open_basedir greift)
    $memTotalMb = 64370;
    $memAvailMb = 56200;
    $swapTotalMb = 4096;
    $swapFreeMb  = 4096;
    $memReadLive = false;

    $openBasedir = trim((string)ini_get('open_basedir'));
    $canReadProc = ($openBasedir === '');
    if ($canReadProc && @is_readable('/proc/meminfo')) {
        $rawMem = (string)@file_get_contents('/proc/meminfo');
        $kbMap  = array();
        foreach (explode("\n", $rawMem) as $line) {
            if (preg_match('/^([A-Za-z0-9_()]+):\s+(\d+)\s*kB/i', trim($line), $m)) {
                $kbMap[$m[1]] = (int)$m[2];
            }
        }
        $totalKb = isset($kbMap['MemTotal']) ? (int)$kbMap['MemTotal'] : 0;
        $availKb = isset($kbMap['MemAvailable'])
            ? (int)$kbMap['MemAvailable']
            : ((isset($kbMap['MemFree']) ? (int)$kbMap['MemFree'] : 0)
                + (isset($kbMap['Buffers']) ? (int)$kbMap['Buffers'] : 0)
                + (isset($kbMap['Cached']) ? (int)$kbMap['Cached'] : 0));
        if ($totalKb > 0) {
            $memTotalMb  = (int)round($totalKb / 1024);
            $memAvailMb  = (int)round($availKb / 1024);
            $swapTotalMb = isset($kbMap['SwapTotal']) ? (int)round($kbMap['SwapTotal'] / 1024) : 0;
            $swapFreeMb  = isset($kbMap['SwapFree']) ? (int)round($kbMap['SwapFree'] / 1024) : 0;
            $memReadLive = true;
        }
    }

    if (!$memReadLive && DIRECTORY_SEPARATOR === '/' && function_exists('exec')) {
        $freeOut = array();
        $freeRet = 1;
        @exec('free -m 2>/dev/null', $freeOut, $freeRet);
        if ($freeRet === 0 && !empty($freeOut)) {
            foreach ($freeOut as $fLine) {
                $fLine = trim((string)$fLine);
                if (preg_match('/^Mem:\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/i', $fLine, $mm)) {
                    $memTotalMb  = (int)$mm[1];
                    $memAvailMb  = (int)$mm[6];
                    $memReadLive = true;
                } elseif (preg_match('/^Swap:\s+(\d+)\s+(\d+)\s+(\d+)/i', $fLine, $sm)) {
                    $swapTotalMb = (int)$sm[1];
                    $swapFreeMb  = (int)$sm[3];
                }
            }
        }
    }

    $memUsedMb      = max(0, $memTotalMb - $memAvailMb);
    $memUsedPercent = $memTotalMb > 0 ? round(($memUsedMb / $memTotalMb) * 100, 1) : 0.0;

    // 2. CPU-Load ermitteln (C-Syscall sys_getloadavg funktioniert auch mit open_basedir)
    $load1m  = 0.0;
    $load5m  = 0.0;
    $load15m = 0.0;
    $loadStr = '0.00 / 0.00 / 0.00';
    if (function_exists('sys_getloadavg')) {
        $la = @sys_getloadavg();
        if (is_array($la) && count($la) >= 3) {
            $load1m  = (float)$la[0];
            $load5m  = (float)$la[1];
            $load15m = (float)$la[2];
            $loadStr = sprintf('%.2f / %.2f / %.2f', $load1m, $load5m, $load15m);
        }
    }

    // 3. Pruefen, ob gerade FFmpeg-Videokonvertierungen laufen (movies.convert_status = '1' oder ffmpeg-Prozess)
    $convertingMoviesCount = 0;
    $rsConv = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` = '1' AND `status` != 'deleted';", __FILE__, __LINE__);
    if ($rsConv && p4c_num_rows($rsConv) > 0) {
        $convObj = p4c_fetch_object($rsConv);
        $convertingMoviesCount = isset($convObj->cnt) ? (int)$convObj->cnt : 0;
    }

    $ffmpegProcCount = 0;
    $whisperProcCount = 0;
    if (DIRECTORY_SEPARATOR === '/' && function_exists('exec')) {
        $ffOut = array();
        @exec('pgrep -c ffmpeg 2>/dev/null', $ffOut);
        if (!empty($ffOut[0])) {
            $ffmpegProcCount = max(0, (int)trim((string)$ffOut[0]));
        }

        $whOut = array();
        @exec('pgrep -c whisper-cli 2>/dev/null', $whOut);
        if (!empty($whOut[0])) {
            $whisperProcCount = max(0, (int)trim((string)$whOut[0]));
        }
    }

    // 4. Pruefen, ob der Whisper-Hintergrund-Worker (ai_compliance_worker.php) gerade aktiv laeuft
    $whisperLockActive = false;
    $tmpDir   = (defined('TEMP_DIR') && @is_dir(TEMP_DIR) && @is_writable(TEMP_DIR)) ? rtrim(TEMP_DIR, '/') : rtrim(sys_get_temp_dir(), '/');
    $lockFile = $tmpDir . '/erocloud_ai_worker.lock';
    if (@is_file($lockFile)) {
        $lockFp = @fopen($lockFile, 'c+');
        if ($lockFp) {
            if (!@flock($lockFp, LOCK_EX | LOCK_NB)) {
                $whisperLockActive = true;
            } else {
                @flock($lockFp, LOCK_UN);
            }
            @fclose($lockFp);
        }
    }

    $workerStatus = function_exists('aic_get_worker_status') ? aic_get_worker_status() : array();
    $statusFilePath = SOURCEDIR . '/includes/ai_rules/batch_status.json';
    $statusFileFresh = (@is_file($statusFilePath) && (time() - (int)@filemtime($statusFilePath)) < 300);
    $workerStateRunning = ($statusFileFresh && isset($workerStatus['state']) && (string)$workerStatus['state'] === 'running');

    $ffmpegActive  = ($convertingMoviesCount > 0 || $ffmpegProcCount > 0);
    $whisperActive = ($whisperLockActive || $whisperProcCount > 0 || $workerStateRunning);

    $threadsNormal = max(2, min(32, (int)($gwCfg['threads_normal'] ?? 16)));
    $threadsBusy   = max(2, min(16, (int)($gwCfg['threads_busy'] ?? 6)));
    $minFreeRamMb  = max(1024, (int)($gwCfg['min_free_ram_mb'] ?? 2500));
    $maxLoadLimit  = round($cpuThreads * (float)($gwCfg['max_load_ratio'] ?? 0.85), 1);

    // 5. 3-Stufen-Verkehrsampel berechnen
    if ($memAvailMb < $minFreeRamMb || $load1m >= $maxLoadLimit) {
        $throttleState      = 'red';
        $recommendedThreads = 0;
        $statusLabel        = sprintf(
            'Hohe Serverlast auf EroCloud (Load: %.2f / RAM frei: %d MB) – KI-Gateway pausiert kurzzeitig',
            $load1m,
            $memAvailMb
        );
    } elseif ($ffmpegActive || $whisperActive || $load1m >= ($cpuThreads * 0.45)) {
        $throttleState      = 'yellow';
        $recommendedThreads = $threadsBusy;
        $activeReasons      = array();
        if ($ffmpegActive) {
            $activeReasons[] = 'FFmpeg Video-Konvertierung aktiv (' . max($convertingMoviesCount, $ffmpegProcCount) . ')';
        }
        if ($whisperActive) {
            $activeReasons[] = 'Whisper Audio-Transkription aktiv';
        }
        if (empty($activeReasons)) {
            $activeReasons[] = sprintf('Erhöhter CPU-Load (%.2f)', $load1m);
        }
        $statusLabel = 'Schon-Modus aktiv (' . implode(' + ', $activeReasons) . ') -> Drosselung auf ' . $threadsBusy . ' von ' . $cpuThreads . ' Threads';
    } else {
        $throttleState      = 'green';
        $recommendedThreads = $threadsNormal;
        $statusLabel        = 'Frei (Kein FFmpeg/Whisper aktiv) -> Volle Leistung mit ' . $threadsNormal . ' von ' . $cpuThreads . ' Threads';
    }

    return array(
        'hostname'     => (string)(@gethostname() ?: 't199.nlserver.net'),
        'os_family'    => PHP_OS_FAMILY,
        'cpu_model'    => $cpuModel,
        'cpu_threads'  => $cpuThreads,
        'memory_stats' => array(
            'os_family'             => PHP_OS_FAMILY,
            'mem_total_mb'          => $memTotalMb,
            'mem_available_mb'      => $memAvailMb,
            'mem_used_mb'           => $memUsedMb,
            'mem_used_percent'      => $memUsedPercent,
            'swap_total_mb'         => $swapTotalMb,
            'swap_free_mb'          => $swapFreeMb,
            'load_avg'              => $loadStr,
            'load_1m'               => $load1m,
            'sufficient_for_4b_llm' => ($memAvailMb >= $minFreeRamMb),
        ),
        'workload' => array(
            'ffmpeg_active'           => $ffmpegActive,
            'ffmpeg_converting_count' => $convertingMoviesCount,
            'ffmpeg_process_count'    => $ffmpegProcCount,
            'whisper_active'          => $whisperActive,
            'whisper_process_count'   => $whisperProcCount,
            'throttle_state'          => $throttleState,
            'recommended_threads'     => $recommendedThreads,
            'threads_normal'          => $threadsNormal,
            'threads_busy'            => $threadsBusy,
            'status_label'            => $statusLabel,
        ),
    );
}

/**
 * Prueft das lokale Ollama-Daemon auf dem EroCloud-Server (127.0.0.1:11434)
 * und gibt alle tatsaechlich installierten & nutzbaren Modelle inkl. Detail-Metadaten zurueck.
 *
 * @return array{reachable: bool, models: array<int, string>, models_detailed: array<int, array<string, mixed>>, error: string|null}
 */
function ec_ai_gateway_check_local_ollama(string $ollamaUrl): array
{
    $url = rtrim($ollamaUrl, '/') . '/api/tags';
    $ch  = @curl_init($url);
    if ($ch === false) {
        return array(
            'reachable'       => false,
            'models'          => array(),
            'models_detailed' => array(),
            'error'           => 'cURL konnte auf EroCloud nicht initialisiert werden.',
        );
    }

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER     => array('Accept: application/json'),
    ));

    $resp     = @curl_exec($ch);
    $httpCode = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = (string)@curl_error($ch);
    unset($ch);

    if ($resp === false || $httpCode !== 200) {
        return array(
            'reachable'       => false,
            'models'          => array(),
            'models_detailed' => array(),
            'error'           => $curlErr !== '' ? $curlErr : ('Lokaler Ollama-Dienst auf EroCloud (127.0.0.1:11434) noch nicht erreichbar (HTTP ' . $httpCode . ')'),
        );
    }

    $decoded        = @json_decode((string)$resp, true);
    $models         = array();
    $modelsDetailed = array();
    if (is_array($decoded) && isset($decoded['models']) && is_array($decoded['models'])) {
        foreach ($decoded['models'] as $m) {
            if (!empty($m['name'])) {
                $mName     = (string)$m['name'];
                $sizeBytes = isset($m['size']) ? (int)$m['size'] : 0;
                $sizeGb    = $sizeBytes > 0 ? round($sizeBytes / 1073741824, 1) : 0.0;
                $details   = (isset($m['details']) && is_array($m['details'])) ? $m['details'] : array();

                $models[] = $mName;
                $modelsDetailed[] = array(
                    'name'           => $mName,
                    'size_bytes'     => $sizeBytes,
                    'size_gb'        => $sizeGb,
                    'parameter_size' => isset($details['parameter_size']) ? (string)$details['parameter_size'] : '',
                    'quantization'   => isset($details['quantization_level']) ? (string)$details['quantization_level'] : '',
                    'family'         => isset($details['family']) ? (string)$details['family'] : '',
                    'modified_at'    => isset($details['modified_at']) ? (string)$details['modified_at'] : (isset($m['modified_at']) ? (string)$m['modified_at'] : ''),
                );
            }
        }
    }

    // 1.1 RUNNING MODELS IM RAM (/api/ps) ABFRAGEN
    $runningModels = array();
    $psUrl = rtrim($ollamaUrl, '/') . '/api/ps';
    $chPs  = @curl_init($psUrl);
    if ($chPs !== false) {
        curl_setopt_array($chPs, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_HTTPHEADER     => array('Accept: application/json'),
        ));
        $psResp = @curl_exec($chPs);
        $psCode = (int)@curl_getinfo($chPs, CURLINFO_HTTP_CODE);
        unset($chPs);
        if ($psResp !== false && $psCode === 200) {
            $psDec = @json_decode((string)$psResp, true);
            if (is_array($psDec) && isset($psDec['models']) && is_array($psDec['models'])) {
                foreach ($psDec['models'] as $rm) {
                    if (!empty($rm['name'])) {
                        $runningModels[] = (string)$rm['name'];
                    }
                }
            }
        }
    }

    return array(
        'reachable'       => true,
        'models'          => $models,
        'models_detailed' => $modelsDetailed,
        'running_models'  => $runningModels,
        'error'           => null,
    );
}

/**
 * Prueft, ob ein lokaler ollama-Prozess auf dem Server gerade noch aktiv mit hoher CPU-Last rechnet
 * (z. B. Nachlauf eines kurz zuvor vom Client abgebrochenen Requests).
 */
function ec_ai_gateway_is_ollama_computing(): bool
{
    if (DIRECTORY_SEPARATOR === '/' && function_exists('exec')) {
        $psOut = array();
        @exec('ps -C ollama -o pcpu= 2>/dev/null', $psOut);
        if (!empty($psOut)) {
            $totalCpu = 0.0;
            foreach ($psOut as $line) {
                $totalCpu += (float)trim((string)$line);
            }
            if ($totalCpu >= 60.0) {
                return true;
            }
        }
    }

    if (function_exists('sys_getloadavg')) {
        $la = @sys_getloadavg();
        if (is_array($la) && isset($la[0]) && (float)$la[0] >= 4.0) {
            return true;
        }
    }

    return false;
}

/**
 * Entlaedt vor einem Modellwechsel (z. B. von 12B auf 26B) gezielt alle anderen
 * aktuell noch im RAM gehaltenen Ollama-Modelle per keep_alive=0, nachdem sichergestellt
 * wurde, dass keine laufende Berechnung mehr aktiv ist.
 *
 * @param string             $ollamaUrl
 * @param string             $targetModel
 * @param array<int, string> $runningModels
 * @return array<int, string> Liste der entladenen Modelle
 */
function ec_ai_gateway_unload_other_models(string $ollamaUrl, string $targetModel, array $runningModels): array
{
    $unloaded = array();
    foreach ($runningModels as $loadedModel) {
        if ($loadedModel === '' || $loadedModel === $targetModel) {
            continue;
        }
        $chUnload = @curl_init(rtrim($ollamaUrl, '/') . '/api/generate');
        if ($chUnload === false) {
            continue;
        }
        curl_setopt_array($chUnload, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json; charset=utf-8'),
            CURLOPT_POSTFIELDS     => json_encode(array(
                'model'      => $loadedModel,
                'prompt'     => '',
                'keep_alive' => 0,
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
        @curl_exec($chUnload);
        unset($chUnload);
        $unloaded[] = $loadedModel;
    }

    return $unloaded;
}

/**
 * Liefert das geschuetzte temporaere Verzeichnis fuer asynchrone KI-Jobs auf t199 (/tmp/ec_ai_jobs).
 */
function ec_ai_gateway_get_jobs_dir(): string
{
    $baseTmp = (defined('TEMP_DIR') && @is_dir(TEMP_DIR) && @is_writable(TEMP_DIR))
        ? rtrim(TEMP_DIR, '/')
        : rtrim(sys_get_temp_dir(), '/');
    $jobsDir = $baseTmp . '/ec_ai_jobs';
    if (!@is_dir($jobsDir)) {
        @mkdir($jobsDir, 0750, true);
    }
    return $jobsDir;
}

/**
 * Bereinigt verwaiste temporaere Job-Dateien auf t199, die aelter als 30 Minuten sind.
 */
function ec_ai_gateway_cleanup_old_jobs(string $jobsDir, int $maxAgeSeconds = 1800): void
{
    $files = @glob($jobsDir . '/job_*.json');
    if (!is_array($files)) {
        return;
    }
    $now = time();
    foreach ($files as $f) {
        $mtime = (int)@filemtime($f);
        if ($mtime > 0 && ($now - $mtime) > $maxAgeSeconds) {
            @unlink($f);
        }
    }
}

/**
 * Schreibt den aktuellen Job-Status atomar in die temporaere Job-Datei auf t199.
 *
 * @param string               $jobFile
 * @param array<string, mixed> $stateData
 */
function ec_ai_gateway_write_job_state(string $jobFile, array $stateData): void
{
    $tmpWrite = $jobFile . '.tmp.' . getmypid();
    $jsonStr  = json_encode($stateData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonStr !== false && @file_put_contents($tmpWrite, $jsonStr) !== false) {
        @rename($tmpWrite, $jobFile);
    } else {
        @file_put_contents($jobFile, (string)$jsonStr, LOCK_EX);
    }
}

/**
 * Fuehrt einen Ollama-Chat-Lauf auf t199 aus (entweder als abgekoppelter Hintergrund-Job mit Live-Fortschritt
 * in $jobStateFile oder synchron im HTTP-Stream).
 *
 * @param array<string, mixed> $jobParams
 * @param string|null          $jobStateFile
 * @param bool                 $emitHttpHeartbeats
 * @return array<string, mixed>
 */
function ec_ai_gateway_execute_ollama_chat(
    array $jobParams,
    ?string $jobStateFile = null,
    bool $emitHttpHeartbeats = false
): array {
    $ollamaUrl      = rtrim((string)($jobParams['ollama_url'] ?? 'http://127.0.0.1:11434'), '/');
    $defaultModel   = (string)($jobParams['default_model'] ?? 'huihui_ai/gemma-4-abliterated:26b');
    $modelName      = !empty($jobParams['model']) ? trim((string)$jobParams['model']) : $defaultModel;
    $systemPrompt   = (string)($jobParams['system_prompt'] ?? '');
    $userPrompt     = (string)($jobParams['user_prompt'] ?? '');
    $numCtx         = max(1024, min(32768, (int)($jobParams['num_ctx'] ?? 8192)));
    $numPredict     = max(120, min(4096, (int)($jobParams['num_predict'] ?? 1000)));
    $threadsToUse   = max(2, min(32, (int)($jobParams['threads_to_use'] ?? 16)));
    $throttleState  = (string)($jobParams['throttle_state'] ?? 'green');
    $workload       = is_array($jobParams['workload'] ?? null) ? $jobParams['workload'] : array();
    $keepAlive      = (string)($jobParams['keep_alive'] ?? '24h');
    $timeoutSec     = max(60, min(540, (int)($jobParams['timeout_seconds'] ?? 360)));
    $jobId          = (string)($jobParams['job_id'] ?? '');
    $createdAt      = (int)($jobParams['created_at'] ?? time());

    $tmpDir       = (defined('TEMP_DIR') && @is_dir(TEMP_DIR) && @is_writable(TEMP_DIR)) ? rtrim(TEMP_DIR, '/') : rtrim(sys_get_temp_dir(), '/');
    $chatLockFile = $tmpDir . '/erocloud_ai_gateway_chat.lock';
    $chatLockFp   = @fopen($chatLockFile, 'c+');
    $lockWaitStart = microtime(true);
    $lastWaitBeat  = $lockWaitStart;

    if ($jobStateFile !== null) {
        ec_ai_gateway_write_job_state($jobStateFile, array(
            'job_id'         => $jobId,
            'status'         => 'running',
            'phase'          => 'waiting_queue',
            'worker_started' => true,
            'pid'            => (int)getmypid(),
            'created_at'     => $createdAt,
            'updated_at'     => time(),
            'elapsed_ms'     => 0,
            'content_chars'  => 0,
            'thinking_chars' => 0,
            'model_used'     => $modelName,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
        ));
    }

    if ($chatLockFp) {
        while (!@flock($chatLockFp, LOCK_EX | LOCK_NB)) {
            usleep(400000);
            $nowWait = microtime(true);
            if ($emitHttpHeartbeats && ($nowWait - $lastWaitBeat) >= 2.0) {
                $lastWaitBeat = $nowWait;
                echo " ";
                @flush();
            }
            if ($jobStateFile !== null && ($nowWait - $lastWaitBeat) >= 2.0) {
                $lastWaitBeat = $nowWait;
                ec_ai_gateway_write_job_state($jobStateFile, array(
                    'job_id'         => $jobId,
                    'status'         => 'running',
                    'phase'          => 'waiting_queue',
                    'worker_started' => true,
                    'pid'            => (int)getmypid(),
                    'created_at'     => $createdAt,
                    'updated_at'     => time(),
                    'elapsed_ms'     => (int)round(($nowWait - $lockWaitStart) * 1000),
                    'content_chars'  => 0,
                    'thinking_chars' => 0,
                    'model_used'     => $modelName,
                    'threads_used'   => $threadsToUse,
                    'throttle_state' => $throttleState,
                ));
            }
            if (($nowWait - $lockWaitStart) >= ($timeoutSec - 15)) {
                break;
            }
        }
    }

    $ollamaCheck = ec_ai_gateway_check_local_ollama($ollamaUrl);
    if (!empty($ollamaCheck['models']) && !in_array($modelName, $ollamaCheck['models'], true)) {
        if (in_array($defaultModel, $ollamaCheck['models'], true)) {
            $modelName = $defaultModel;
        } else {
            $modelName = (string)$ollamaCheck['models'][0];
        }
    }

    $runningModels  = isset($ollamaCheck['running_models']) && is_array($ollamaCheck['running_models']) ? $ollamaCheck['running_models'] : array();
    $needsModelSwap = false;
    foreach ($runningModels as $rmName) {
        if ($rmName !== '' && $rmName !== $modelName) {
            $needsModelSwap = true;
            break;
        }
    }
    if ($needsModelSwap) {
        $busyWaitStart = microtime(true);
        while (ec_ai_gateway_is_ollama_computing() && (microtime(true) - $busyWaitStart) < 45.0) {
            usleep(500000);
            $nowBusy = microtime(true);
            if ($emitHttpHeartbeats && ($nowBusy - $lastWaitBeat) >= 2.0) {
                $lastWaitBeat = $nowBusy;
                echo " ";
                @flush();
            }
        }
    }
    $unloadedModels = ec_ai_gateway_unload_other_models($ollamaUrl, $modelName, $runningModels);

    $startMs = (int)round(microtime(true) * 1000);
    $chatUrl = $ollamaUrl . '/api/chat';

    if ($jobStateFile !== null) {
        ec_ai_gateway_write_job_state($jobStateFile, array(
            'job_id'         => $jobId,
            'status'         => 'running',
            'phase'          => 'prefill',
            'worker_started' => true,
            'pid'            => (int)getmypid(),
            'created_at'     => $createdAt,
            'updated_at'     => time(),
            'elapsed_ms'     => 10,
            'content_chars'  => 0,
            'thinking_chars' => 0,
            'model_used'     => $modelName,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
        ));
    }

    $ollamaPayload = array(
        'model'      => $modelName,
        'format'     => 'json',
        'think'      => false,
        'stream'     => true,
        'keep_alive' => $keepAlive,
        'options'    => array(
            'temperature' => 0.1,
            'num_ctx'     => $numCtx,
            'num_thread'  => $threadsToUse,
            'num_predict' => $numPredict,
        ),
        'messages'   => array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user', 'content' => $userPrompt),
        ),
    );

    $ch = @curl_init($chatUrl);
    if ($ch === false) {
        if ($chatLockFp) {
            @flock($chatLockFp, LOCK_UN);
            @fclose($chatLockFp);
        }
        $errRes = array(
            'job_id'         => $jobId,
            'status'         => 'error',
            'success'        => false,
            'error'          => 'cURL-Initialisierung zum lokalen Ollama auf EroCloud fehlgeschlagen.',
            'duration_ms'    => 0,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
            'model_used'     => $modelName,
        );
        if ($jobStateFile !== null) {
            ec_ai_gateway_write_job_state($jobStateFile, $errRes);
        }
        return $errRes;
    }

    $streamedContent   = '';
    $streamedThinking  = '';
    $lineBuffer        = '';
    $reqStartTs        = microtime(true);
    $lastHeartbeatTs   = $reqStartTs;
    $lastStateWriteTs  = $reqStartTs;
    $abortedByWatchdog = false;
    $watchdogReason    = '';

    curl_setopt_array($ch, array(
        CURLOPT_POST             => true,
        CURLOPT_RETURNTRANSFER   => false,
        CURLOPT_TIMEOUT          => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT   => 5,
        CURLOPT_NOPROGRESS       => false,
        CURLOPT_HTTPHEADER       => array('Content-Type: application/json; charset=utf-8'),
        CURLOPT_POSTFIELDS       => json_encode($ollamaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_PROGRESSFUNCTION => static function ($resource, int $dltotal, int $dlnow, int $ultotal, int $ulnow) use (
            &$lastHeartbeatTs,
            &$lastStateWriteTs,
            $reqStartTs,
            &$abortedByWatchdog,
            &$watchdogReason,
            $emitHttpHeartbeats,
            $jobStateFile,
            $jobId,
            $createdAt,
            &$streamedContent,
            &$streamedThinking,
            $modelName,
            $threadsToUse,
            $throttleState
        ): int {
            $nowTs = microtime(true);
            if ($emitHttpHeartbeats && ($nowTs - $lastHeartbeatTs) >= 2.0) {
                $lastHeartbeatTs = $nowTs;
                echo " ";
                @flush();
            }

            if ($jobStateFile !== null && ($nowTs - $lastStateWriteTs) >= 2.0) {
                $lastStateWriteTs = $nowTs;
                $cLen = strlen($streamedContent);
                ec_ai_gateway_write_job_state($jobStateFile, array(
                    'job_id'         => $jobId,
                    'status'         => 'running',
                    'phase'          => $cLen > 0 ? 'generating' : 'prefill',
                    'worker_started' => true,
                    'pid'            => (int)getmypid(),
                    'created_at'     => $createdAt,
                    'updated_at'     => time(),
                    'elapsed_ms'     => (int)round(($nowTs - $reqStartTs) * 1000),
                    'content_chars'  => $cLen,
                    'thinking_chars' => strlen($streamedThinking),
                    'model_used'     => $modelName,
                    'threads_used'   => $threadsToUse,
                    'throttle_state' => $throttleState,
                ));
            }

            $elapsedSec = $nowTs - $reqStartTs;
            if ($dlnow === 0 && $elapsedSec >= 55.0 && function_exists('sys_getloadavg')) {
                $la = @sys_getloadavg();
                $curLoad = (is_array($la) && isset($la[0])) ? (float)$la[0] : 1.0;
                if ($curLoad < 0.75) {
                    $abortedByWatchdog = true;
                    $watchdogReason    = sprintf(
                        'Ollama-Runner-Queue auf EroCloud reagiert nicht (0 Bytes nach %ds bei CPU-Load %.2f). Bitte Ollama-Dienst prüfen (systemctl restart ollama).',
                        (int)round($elapsedSec),
                        $curLoad
                    );
                    return 1;
                }
            }

            return 0;
        },
        CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (
            &$streamedContent,
            &$streamedThinking,
            &$lineBuffer,
            &$lastHeartbeatTs,
            &$lastStateWriteTs,
            $reqStartTs,
            $emitHttpHeartbeats,
            $jobStateFile,
            $jobId,
            $createdAt,
            $modelName,
            $threadsToUse,
            $throttleState
        ): int {
            $lineBuffer .= $chunk;
            while (($newlinePos = strpos($lineBuffer, "\n")) !== false) {
                $line       = trim(substr($lineBuffer, 0, $newlinePos));
                $lineBuffer = substr($lineBuffer, $newlinePos + 1);
                if ($line === '') {
                    continue;
                }
                $decoded = @json_decode($line, true);
                if (is_array($decoded)) {
                    if (isset($decoded['message']['content'])) {
                        $streamedContent .= (string)$decoded['message']['content'];
                    }
                    if (isset($decoded['message']['thinking'])) {
                        $streamedThinking .= (string)$decoded['message']['thinking'];
                    }
                }
            }

            $nowTs = microtime(true);
            if ($emitHttpHeartbeats && ($nowTs - $lastHeartbeatTs) >= 2.0) {
                $lastHeartbeatTs = $nowTs;
                echo " ";
                @flush();
            }

            if ($jobStateFile !== null && ($nowTs - $lastStateWriteTs) >= 1.5) {
                $lastStateWriteTs = $nowTs;
                $cLen = strlen($streamedContent);
                ec_ai_gateway_write_job_state($jobStateFile, array(
                    'job_id'         => $jobId,
                    'status'         => 'running',
                    'phase'          => $cLen > 0 ? 'generating' : 'prefill',
                    'worker_started' => true,
                    'pid'            => (int)getmypid(),
                    'created_at'     => $createdAt,
                    'updated_at'     => time(),
                    'elapsed_ms'     => (int)round(($nowTs - $reqStartTs) * 1000),
                    'content_chars'  => $cLen,
                    'thinking_chars' => strlen($streamedThinking),
                    'model_used'     => $modelName,
                    'threads_used'   => $threadsToUse,
                    'throttle_state' => $throttleState,
                ));
            }

            return strlen($chunk);
        },
    ));

    $execOk   = @curl_exec($ch);
    $httpCode = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = (string)@curl_error($ch);
    unset($ch);

    if ($chatLockFp) {
        @flock($chatLockFp, LOCK_UN);
        @fclose($chatLockFp);
        $chatLockFp = null;
    }

    if (trim($lineBuffer) !== '') {
        $decoded = @json_decode(trim($lineBuffer), true);
        if (is_array($decoded) && isset($decoded['message']['content'])) {
            $streamedContent .= (string)$decoded['message']['content'];
        }
    }

    $durationMs = max(1, (int)round(microtime(true) * 1000) - $startMs);
    $timedOut   = ($execOk === false);
    if ($abortedByWatchdog && $watchdogReason !== '') {
        $curlErr = $watchdogReason;
    }

    if ($execOk === false && trim($streamedContent) === '' && trim($streamedThinking) === '') {
        $finalRes = array(
            'job_id'          => $jobId,
            'status'          => 'error',
            'success'         => false,
            'timed_out'       => true,
            'curl_error'      => $curlErr,
            'duration_ms'     => $durationMs,
            'threads_used'    => $threadsToUse,
            'throttle_state'  => $throttleState,
            'model_used'      => $modelName,
            'unloaded_models' => $unloadedModels,
            'error'           => $curlErr !== '' ? $curlErr : ('EroCloud Ollama HTTP-Fehler ' . $httpCode),
        );
        if ($jobStateFile !== null) {
            ec_ai_gateway_write_job_state($jobStateFile, $finalRes);
        }
        return $finalRes;
    }

    $content = trim($streamedContent);
    if ($content === '' && trim($streamedThinking) !== '') {
        $content = trim($streamedThinking);
    }

    if ($content === '') {
        $finalRes = array(
            'job_id'         => $jobId,
            'status'         => 'error',
            'success'        => false,
            'timed_out'      => $timedOut,
            'curl_error'     => $curlErr,
            'duration_ms'    => $durationMs,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
            'model_used'     => $modelName,
            'error'          => 'Leere Antwort vom Ollama-Sprachmodell auf dem EroCloud-Server erhalten.',
        );
        if ($jobStateFile !== null) {
            ec_ai_gateway_write_job_state($jobStateFile, $finalRes);
        }
        return $finalRes;
    }

    $finalRes = array(
        'job_id'         => $jobId,
        'status'         => 'done',
        'success'        => true,
        'raw_text'       => $content,
        'timed_out'      => $timedOut,
        'curl_error'     => $curlErr,
        'content_chars'  => strlen($streamedContent),
        'thinking_chars' => strlen($streamedThinking),
        'duration_ms'    => $durationMs,
        'threads_used'   => $threadsToUse,
        'throttle_state' => $throttleState,
        'workload'       => $workload,
        'model_used'     => $modelName,
    );

    if ($jobStateFile !== null) {
        ec_ai_gateway_write_job_state($jobStateFile, $finalRes);
    }

    return $finalRes;
}

// ============================================================================
// 0. CLI BACKGROUND WORKER ENTRYPOINT (--run-job=job_...)
// ============================================================================
if (PHP_SAPI === 'cli' && !empty($argv) && is_array($argv)) {
    foreach ($argv as $cliArg) {
        if (strpos((string)$cliArg, '--run-job=') === 0) {
            p4c_close(DB_HOST);
            @set_time_limit(600);
            $cliJobId = preg_replace('/[^a-zA-Z0-9_]/', '', substr((string)$cliArg, 10));
            $jobsDir  = ec_ai_gateway_get_jobs_dir();
            $reqFile  = $jobsDir . '/' . $cliJobId . '_req.json';
            $stateFile = $jobsDir . '/' . $cliJobId . '.json';
            if ($cliJobId !== '' && @is_file($reqFile)) {
                $reqData = @json_decode((string)@file_get_contents($reqFile), true);
                @unlink($reqFile);
                if (is_array($reqData)) {
                    ec_ai_gateway_execute_ollama_chat($reqData, $stateFile, false);
                }
            }
            exit(0);
        }
    }
}

// ============================================================================
// Request-Verarbeitung
// ============================================================================
$rawBody  = (string)@file_get_contents('php://input');
$jsonBody = array();
if (trim($rawBody) !== '') {
    $decodedBody = @json_decode($rawBody, true);
    if (is_array($decodedBody)) {
        $jsonBody = $decodedBody;
    }
}

$gwCfg       = ec_ai_gateway_load_config();
$expectedKey = (string)$gwCfg['api_key'];
$providedKey = ec_ai_gateway_extract_api_key($jsonBody);

if ($expectedKey === '' || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'error'   => 'Nicht autorisiert: Ungültiger oder fehlender API-Key für das EroCloud KI-Gateway.',
    ), JSON_UNESCAPED_UNICODE);
    p4c_close(DB_HOST);
    exit;
}

$action = '';
if (!empty($jsonBody['action'])) {
    $action = strtolower(trim((string)$jsonBody['action']));
} elseif (!empty($_POST['action'])) {
    $action = strtolower(trim((string)$_POST['action']));
} elseif (!empty($_GET['action'])) {
    $action = strtolower(trim((string)$_GET['action']));
} else {
    $action = 'health';
}

$ollamaUrl   = rtrim((string)$gwCfg['ollama_url'], '/');
$serverState = ec_ai_gateway_get_server_state($gwCfg);

// 1. HEALTH, WORKLOAD & INSTALLED MODELS CHECK (fuer Pay4Coins /ki-einstellungen & Live-Modellauswahl)
if ($action === 'health' || $action === 'status' || $action === 'models') {
    $ollamaHealth = ec_ai_gateway_check_local_ollama($ollamaUrl);

    echo json_encode(array(
        'success'          => true,
        'gateway_ok'       => true,
        'server_hostname'  => $serverState['hostname'],
        'cpu_model'        => $serverState['cpu_model'],
        'cpu_threads'      => $serverState['cpu_threads'],
        'memory_stats'     => $serverState['memory_stats'],
        'workload'         => $serverState['workload'],
        'ollama'           => $ollamaHealth,
        'installed_models' => $ollamaHealth['models'],
        'models_detailed'  => $ollamaHealth['models_detailed'],
    ), JSON_UNESCAPED_UNICODE);
    p4c_close(DB_HOST);
    exit;
}

// 1.5 ASYNC JOB STATUS POLLING & BACKGROUND WORKER ('check_job' & 'run_job')
if ($action === 'check_job' || $action === 'run_job') {
    p4c_close(DB_HOST);

    $rawJobId  = isset($jsonBody['job_id']) ? (string)$jsonBody['job_id'] : (isset($_POST['job_id']) ? (string)$_POST['job_id'] : (isset($_GET['job_id']) ? (string)$_GET['job_id'] : ''));
    $jobId     = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawJobId);
    $jobsDir   = ec_ai_gateway_get_jobs_dir();
    $stateFile = $jobsDir . '/' . $jobId . '.json';
    $reqFile   = $jobsDir . '/' . $jobId . '_req.json';
    $runLock   = $jobsDir . '/' . $jobId . '.run.lock';

    // 1.5A: HINTERGRUND-WORKER STARTEN ('run_job'):
    // Wird von Pay4Coins mit kurzem 2s-Trigger aufgerufen. Laeuft dank ignore_user_abort(true)
    // komplett unabhaengig von der HTTP-Verbindung auf t199 weiter und schreibt das Ergebnis in $stateFile.
    if ($action === 'run_job') {
        @ignore_user_abort(true);
        @set_time_limit(540);

        if ($jobId === '' || (!@is_file($reqFile) && !@is_file($stateFile))) {
            echo json_encode(array(
                'success' => false,
                'error'   => 'Job-Request (' . $jobId . ') auf EroCloud nicht gefunden.',
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $runLockFp = @fopen($runLock, 'c+');
        if ($runLockFp && !@flock($runLockFp, LOCK_EX | LOCK_NB)) {
            // Worker laeuft bereits in einem anderen Prozess
            @fclose($runLockFp);
            echo json_encode(array(
                'success' => true,
                'status'  => 'already_running',
                'job_id'  => $jobId,
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!@is_file($reqFile)) {
            if ($runLockFp) {
                @flock($runLockFp, LOCK_UN);
                @fclose($runLockFp);
            }
            echo json_encode(array(
                'success' => true,
                'status'  => 'already_started',
                'job_id'  => $jobId,
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $reqData = @json_decode((string)@file_get_contents($reqFile), true);
        @unlink($reqFile);

        if (!is_array($reqData)) {
            ec_ai_gateway_write_job_state($stateFile, array(
                'job_id'  => $jobId,
                'status'  => 'error',
                'success' => false,
                'error'   => 'Job-Parameter konnten auf EroCloud nicht gelesen werden.',
            ));
            if ($runLockFp) {
                @flock($runLockFp, LOCK_UN);
                @fclose($runLockFp);
            }
            @unlink($runLock);
            exit;
        }

        ec_ai_gateway_write_job_state($stateFile, array(
            'job_id'         => $jobId,
            'status'         => 'running',
            'phase'          => 'worker_active',
            'worker_started' => true,
            'pid'            => (int)getmypid(),
            'started_at'     => time(),
            'updated_at'     => time(),
            'model_used'     => (string)($reqData['model'] ?? ''),
            'threads_used'   => (int)($reqData['threads_to_use'] ?? 16),
            'throttle_state' => (string)($reqData['throttle_state'] ?? 'green'),
            'content_chars'  => 0,
            'thinking_chars' => 0,
            'elapsed_ms'     => 0,
        ));

        // Wichtig: Waehrend der Berechnung KEINE Bytes an stdout senden ($emitHttpHeartbeats = false),
        // damit PHP niemals durch einen geschlossenen Client-Socket irritiert wird!
        ec_ai_gateway_execute_ollama_chat($reqData, $stateFile, false);

        if ($runLockFp) {
            @flock($runLockFp, LOCK_UN);
            @fclose($runLockFp);
        }
        @unlink($runLock);
        exit;
    }

    // 1.5B: STATUS ABFRAGEN ('check_job'):
    if ($jobId === '' || !@is_file($stateFile)) {
        echo json_encode(array(
            'success' => false,
            'status'  => 'not_found',
            'error'   => 'KI-Job (' . $jobId . ') auf EroCloud nicht gefunden oder bereits abgeholt.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rawState = (string)@file_get_contents($stateFile);
    $jobData  = @json_decode($rawState, true);
    if (!is_array($jobData)) {
        echo json_encode(array(
            'success' => true,
            'status'  => 'running',
            'phase'   => 'reading_state',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $jobStatus = isset($jobData['status']) ? (string)$jobData['status'] : 'running';

    // Sobald das Ergebnis fertig ist (done oder error), temporäre Dateien auf t199 sofort loeschen!
    if ($jobStatus === 'done' || $jobStatus === 'error') {
        @unlink($stateFile);
        @unlink($reqFile);
        @unlink($runLock);
        echo json_encode($jobData, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Schutz gegen verwaiste Jobs, falls der Worker-Prozess unerwartet beendet wurde
    $lastUpdate = isset($jobData['updated_at']) ? (int)$jobData['updated_at'] : (isset($jobData['started_at']) ? (int)$jobData['started_at'] : time());
    if ((time() - $lastUpdate) > 480) {
        @unlink($stateFile);
        @unlink($reqFile);
        @unlink($runLock);
        echo json_encode(array(
            'success'   => false,
            'status'    => 'error',
            'timed_out' => true,
            'error'     => 'Hintergrund-Job auf EroCloud hat seit über 480s keinen Status mehr aktualisiert.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(array(
        'success'        => true,
        'async_job'      => true,
        'job_id'         => $jobId,
        'status'         => 'running',
        'worker_started' => !empty($jobData['worker_started']),
        'phase'          => isset($jobData['phase']) ? (string)$jobData['phase'] : 'computing',
        'content_chars'  => isset($jobData['content_chars']) ? (int)$jobData['content_chars'] : 0,
        'thinking_chars' => isset($jobData['thinking_chars']) ? (int)$jobData['thinking_chars'] : 0,
        'elapsed_ms'     => isset($jobData['elapsed_ms']) ? (int)$jobData['elapsed_ms'] : 0,
        'model_used'     => isset($jobData['model_used']) ? (string)$jobData['model_used'] : '',
        'threads_used'   => isset($jobData['threads_used']) ? (int)$jobData['threads_used'] : 0,
        'throttle_state' => isset($jobData['throttle_state']) ? (string)$jobData['throttle_state'] : 'green',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. CHAT COMPLETION & ASYNC JOB START (Fuehrt KI-Inferenz auf dem EroCloud-Server mit automatischer FFmpeg/Whisper-Rücksichtnahme aus)
if ($action === 'chat' || $action === 'start_job') {
    @ignore_user_abort(true);
    @set_time_limit(420);

    $workload      = $serverState['workload'];
    $throttleState = (string)$workload['throttle_state'];
    $busyPolicy    = isset($jsonBody['busy_policy']) ? strtolower(trim((string)$jsonBody['busy_policy'])) : 'throttle';

    // Schutzsperre bei ROT (Ueberlastung) oder wenn GELB + Policy 'pause'
    if ($throttleState === 'red') {
        echo json_encode(array(
            'success'        => false,
            'busy'           => true,
            'throttle_state' => 'red',
            'workload'       => $workload,
            'error'          => (string)$workload['status_label'],
        ), JSON_UNESCAPED_UNICODE);
        p4c_close(DB_HOST);
        exit;
    }

    if ($throttleState === 'yellow' && $busyPolicy === 'pause') {
        echo json_encode(array(
            'success'        => false,
            'busy'           => true,
            'throttle_state' => 'yellow',
            'workload'       => $workload,
            'error'          => 'EroCloud verarbeitet gerade ein Video (FFmpeg/Whisper) und Einstellung steht auf „Pausieren“.',
        ), JSON_UNESCAPED_UNICODE);
        p4c_close(DB_HOST);
        exit;
    }

    // Datenbank-Verbindung vor dem langen Ollama-Streaming-Aufruf schliessen,
    // damit waehrend der Rechenzeit keine MySQL-Connection auf EroCloud belegt bleibt!
    p4c_close(DB_HOST);

    $requestedNormalThreads = isset($jsonBody['threads_normal']) ? (int)$jsonBody['threads_normal'] : (int)$workload['threads_normal'];
    $requestedBusyThreads   = isset($jsonBody['threads_busy']) ? (int)$jsonBody['threads_busy'] : (int)$workload['threads_busy'];

    $threadsNormal = max(2, min(32, $requestedNormalThreads));
    $threadsBusy   = max(2, min(16, $requestedBusyThreads));
    $threadsToUse  = ($throttleState === 'yellow') ? $threadsBusy : $threadsNormal;

    $modelName    = !empty($jsonBody['model']) ? trim((string)$jsonBody['model']) : (string)$gwCfg['default_model'];
    $systemPrompt = isset($jsonBody['system_prompt']) ? (string)$jsonBody['system_prompt'] : '';
    $userPrompt   = isset($jsonBody['user_prompt']) ? (string)$jsonBody['user_prompt'] : '';
    $numCtx       = isset($jsonBody['num_ctx']) ? max(1024, min(32768, (int)$jsonBody['num_ctx'])) : 8192;
    $numPredict   = isset($jsonBody['num_predict']) ? max(120, min(4096, (int)$jsonBody['num_predict'])) : 1000;
    $timeoutSec   = isset($jsonBody['timeout_seconds']) ? max(30, min(360, (int)$jsonBody['timeout_seconds'])) : 260;

    if ($userPrompt === '') {
        echo json_encode(array(
            'success' => false,
            'error'   => 'Leerer User-Prompt an EroCloud KI-Gateway übergeben.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $jobParams = array(
        'ollama_url'     => $ollamaUrl,
        'default_model'  => (string)$gwCfg['default_model'],
        'keep_alive'     => (string)($gwCfg['keep_alive'] ?? '24h'),
        'model'          => $modelName,
        'system_prompt'  => $systemPrompt,
        'user_prompt'    => $userPrompt,
        'num_ctx'        => $numCtx,
        'num_predict'    => $numPredict,
        'threads_to_use' => $threadsToUse,
        'timeout_sec'    => $timeoutSec,
        'throttle_state' => $throttleState,
        'workload'       => $workload,
    );

    // 2A. ASYNCHRONER JOB-MODUS ('start_job'):
    // Legt den Job temporär unter /tmp/ec_ai_jobs/ auf t199 ab und beendet diesen HTTP-Request SOFORT (exit),
    // damit Apache/Nginx die job_id in < 0.1s an Pay4Coins ausliefert. Pay4Coins triggert danach 'run_job'
    // und fragt alle 4 Sekunden per 'check_job' den Live-Fortschritt ab.
    if ($action === 'start_job') {
        $jobsDir = ec_ai_gateway_get_jobs_dir();
        ec_ai_gateway_cleanup_old_jobs($jobsDir, 1800);

        try {
            $randHex = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $randHex = substr(md5(uniqid((string)mt_rand(), true)), 0, 16);
        }
        $jobId     = 'job_' . $randHex;
        $stateFile = $jobsDir . '/' . $jobId . '.json';
        $reqFile   = $jobsDir . '/' . $jobId . '_req.json';

        // Dem entkoppelten Hintergrund-Worker auf t199 großzügiges Zeitbudget geben (kein HTTP-Timeout-Druck)
        $asyncJobParams = $jobParams;
        $asyncJobParams['timeout_sec'] = max(420, $timeoutSec + 120);

        ec_ai_gateway_write_job_state($stateFile, array(
            'job_id'         => $jobId,
            'status'         => 'running',
            'phase'          => 'queued',
            'worker_started' => false,
            'started_at'     => time(),
            'updated_at'     => time(),
            'model_used'     => $modelName,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
            'content_chars'  => 0,
            'thinking_chars' => 0,
            'elapsed_ms'     => 0,
        ));
        @file_put_contents($reqFile, json_encode($asyncJobParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $spawnedCliWorker = false;
        $disabledFuncs    = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
        if (function_exists('exec') && !in_array('exec', $disabledFuncs, true)) {
            $phpBin = 'php';
            if (defined('PHP_BINARY') && PHP_BINARY !== '' && stripos(PHP_BINARY, 'fpm') === false && stripos(PHP_BINARY, 'cgi') === false && stripos(PHP_BINARY, 'httpd') === false) {
                $phpBin = PHP_BINARY;
            }
            $cmd = 'nohup ' . escapeshellarg($phpBin) . ' ' . escapeshellarg(__FILE__) . ' --run-job=' . escapeshellarg($jobId) . ' >/dev/null 2>&1 &';
            @exec($cmd);
            usleep(120000);
            if (!@is_file($reqFile)) {
                $spawnedCliWorker = true;
            }
        }

        echo json_encode(array(
            'success'        => true,
            'async_job'      => true,
            'job_id'         => $jobId,
            'status'         => 'running',
            'phase'          => $spawnedCliWorker ? 'starting' : 'queued',
            'worker_started' => $spawnedCliWorker,
            'worker_mode'    => $spawnedCliWorker ? 'cli' : 'http_run_job',
            'model_used'     => $modelName,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
            'workload'       => $workload,
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2B. KLASSISCHER SYNCHRONER CHAT-MODUS ('chat') MIT 2-SEKUNDEN KEEP-ALIVE HEARTBEATS:
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    header('X-Accel-Buffering: no');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    flush();

    $result = ec_ai_gateway_execute_ollama_chat($jobParams, null, true);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array(
    'success' => false,
    'error'   => 'Unbekannte Gateway-Aktion: ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
), JSON_UNESCAPED_UNICODE);
p4c_close(DB_HOST);
exit;
