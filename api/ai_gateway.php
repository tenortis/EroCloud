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
        'default_model'        => 'huihui_ai/gemma-4-abliterated:e4b',
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
        @exec('pgrep -fc "whisper-cli" 2>/dev/null', $whOut);
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
    $workerStateRunning = (isset($workerStatus['state']) && (string)$workerStatus['state'] === 'running');

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
 * Prueft das lokale Ollama-Daemon auf dem EroCloud-Server (127.0.0.1:11434).
 *
 * @return array{reachable: bool, models: array<int, string>, error: string|null}
 */
function ec_ai_gateway_check_local_ollama(string $ollamaUrl): array
{
    $url = rtrim($ollamaUrl, '/') . '/api/tags';
    $ch  = @curl_init($url);
    if ($ch === false) {
        return array('reachable' => false, 'models' => array(), 'error' => 'cURL konnte auf EroCloud nicht initialisiert werden.');
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
            'reachable' => false,
            'models'    => array(),
            'error'     => $curlErr !== '' ? $curlErr : ('Lokaler Ollama-Dienst auf EroCloud (127.0.0.1:11434) noch nicht erreichbar (HTTP ' . $httpCode . ')'),
        );
    }

    $decoded = @json_decode((string)$resp, true);
    $models  = array();
    if (is_array($decoded) && isset($decoded['models']) && is_array($decoded['models'])) {
        foreach ($decoded['models'] as $m) {
            if (!empty($m['name'])) {
                $models[] = (string)$m['name'];
            }
        }
    }

    return array(
        'reachable' => true,
        'models'    => $models,
        'error'     => null,
    );
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

// 1. HEALTH & WORKLOAD CHECK (fuer Pay4Coins /ki-einstellungen & Pre-Flight)
if ($action === 'health' || $action === 'status') {
    $ollamaHealth = ec_ai_gateway_check_local_ollama($ollamaUrl);

    echo json_encode(array(
        'success'         => true,
        'gateway_ok'      => true,
        'server_hostname' => $serverState['hostname'],
        'cpu_model'       => $serverState['cpu_model'],
        'cpu_threads'     => $serverState['cpu_threads'],
        'memory_stats'    => $serverState['memory_stats'],
        'workload'        => $serverState['workload'],
        'ollama'          => $ollamaHealth,
    ), JSON_UNESCAPED_UNICODE);
    p4c_close(DB_HOST);
    exit;
}

// 2. CHAT COMPLETION (Fuehrt KI-Inferenz auf dem EroCloud-Server mit automatischer FFmpeg/Whisper-Rücksichtnahme aus)
if ($action === 'chat') {
    @ignore_user_abort(true);
    @set_time_limit(300);

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
    // damit waehrend der 5-25s Rechenzeit keine MySQL-Connection auf EroCloud belegt bleibt!
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
    $numPredict   = isset($jsonBody['num_predict']) ? max(120, min(2048, (int)$jsonBody['num_predict'])) : 700;
    $timeoutSec   = isset($jsonBody['timeout_seconds']) ? max(30, min(240, (int)$jsonBody['timeout_seconds'])) : 180;

    if ($userPrompt === '') {
        echo json_encode(array(
            'success' => false,
            'error'   => 'Leerer User-Prompt an EroCloud KI-Gateway übergeben.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Output-Buffering deaktivieren, damit wir alle 2 Sekunden ein Leerzeichen (" ") als Keep-Alive-Heartbeat
    // an Pay4Coins senden koennen (verhindert jegliche Proxy-/Firewall-Timeouts auch bei grossen Modellen)
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    flush();

    $startMs = (int)round(microtime(true) * 1000);
    $chatUrl = $ollamaUrl . '/api/chat';

    $ollamaPayload = array(
        'model'      => $modelName,
        'format'     => 'json',
        'think'      => false,
        'stream'     => true,
        'keep_alive' => (string)($gwCfg['keep_alive'] ?? '24h'),
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
        echo json_encode(array(
            'success' => false,
            'error'   => 'cURL-Initialisierung zum lokalen Ollama auf EroCloud fehlgeschlagen.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $streamedContent  = '';
    $streamedThinking = '';
    $lineBuffer       = '';
    $lastHeartbeatTs  = microtime(true);

    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json; charset=utf-8'),
        CURLOPT_POSTFIELDS     => json_encode($ollamaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$streamedContent, &$streamedThinking, &$lineBuffer, &$lastHeartbeatTs): int {
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

            // Alle 2 Sekunden ein einzelnes Leerzeichen senden, um die HTTPS-Verbindung aktiv zu halten
            $nowTs = microtime(true);
            if (($nowTs - $lastHeartbeatTs) >= 2.0) {
                $lastHeartbeatTs = $nowTs;
                echo " ";
                @flush();
            }

            return strlen($chunk);
        },
    ));

    $execOk   = @curl_exec($ch);
    $httpCode = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = (string)@curl_error($ch);
    unset($ch);

    if (trim($lineBuffer) !== '') {
        $decoded = @json_decode(trim($lineBuffer), true);
        if (is_array($decoded) && isset($decoded['message']['content'])) {
            $streamedContent .= (string)$decoded['message']['content'];
        }
    }

    $durationMs = max(1, (int)round(microtime(true) * 1000) - $startMs);

    if ($execOk === false && trim($streamedContent) === '') {
        echo json_encode(array(
            'success'        => false,
            'duration_ms'    => $durationMs,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
            'error'          => $curlErr !== '' ? $curlErr : ('EroCloud Ollama HTTP-Fehler ' . $httpCode),
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $content = trim($streamedContent);
    if ($content === '' && trim($streamedThinking) !== '') {
        $content = trim($streamedThinking);
    }

    if ($content === '') {
        echo json_encode(array(
            'success'        => false,
            'duration_ms'    => $durationMs,
            'threads_used'   => $threadsToUse,
            'throttle_state' => $throttleState,
            'error'          => 'Leere Antwort vom Ollama-Sprachmodell auf dem EroCloud-Server erhalten.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(array(
        'success'        => true,
        'raw_text'       => $content,
        'duration_ms'    => $durationMs,
        'threads_used'   => $threadsToUse,
        'throttle_state' => $throttleState,
        'workload'       => $workload,
        'model_used'     => $modelName,
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array(
    'success' => false,
    'error'   => 'Unbekannte Gateway-Aktion: ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
), JSON_UNESCAPED_UNICODE);
p4c_close(DB_HOST);
exit;
