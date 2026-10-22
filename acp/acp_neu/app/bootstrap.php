<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud ACP (v2.0) - Zentraler Anwendungs-Bootstrap
 * Unterstützt sowohl den Entwicklungs-Pfad (/acp/acp_neu/app) als auch den späteren Live-Pfad (/acp/app).
 */

// 1. Zeitzone vor dem Laden der config.inc.php verbindlich setzen
date_default_timezone_set('Europe/Berlin');

// 2. Dynamische Suche nach der zentralen config.inc.php (EroCloud Root)
$configCandidates = [
    dirname(__DIR__, 2) . '/config.inc.php', // Späterer Live-Pfad: /htdocs/acp/app -> /htdocs/config.inc.php
    dirname(__DIR__, 3) . '/config.inc.php', // Entwicklungs-Pfad:  /htdocs/acp/acp_neu/app -> /htdocs/config.inc.php
];

foreach ($configCandidates as $candidate) {
    if (file_exists($candidate)) {
        include_once $candidate;
        break;
    }
}

// 3. Globale EroCloud Hilfsfunktionen laden
if (defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/functions.inc.php')) {
    include_once SOURCEDIR . '/includes/functions.inc.php';
}

// 4. Session starten (kompatibel mit bestehender EroCloud ACP-Session für nahtloses Session-Sharing)
if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
    if (defined('TEMP_DIR') && is_dir(TEMP_DIR)) {
        session_save_path(TEMP_DIR . '/');
    }
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_maxlifetime', '10800');
    if (function_exists('p4c_session_start')) {
        p4c_session_start();
    } else {
        @session_start();
    }
}

// 5. Cookie-Manipulation verhindern & Referer-Schutz (analog acp/common.inc.php)
if (isset($_GET['referer'])) {
    $_SERVER['HTTP_REFERER'] = $_GET['referer'];
}
if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] === '') {
    $_COOKIE['PHPSESSID'] = '0x';
}

// 6. Error-Handler, Datenbank ($mysql), Settings ($settings) & Kernklassen initialisieren
if (defined('SOURCEDIR')) {
    include_once SOURCEDIR . '/includes/klassen/errorlog.inc.php';
    global $class_errorlog, $mysql, $settings, $config;
    $class_errorlog = new p4c_errorlog();

    include_once SOURCEDIR . '/includes/klassen/mysqli.inc.php';
    $mysql = new p4c_mysqli(DB_HOST, $config['db_user'], $config['db_pass'], $config['db_name']);
    p4c_query("SET NAMES 'utf8mb4';", __FILE__, __LINE__);

    if (file_exists(SOURCEDIR . '/includes/klassen/settings.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/settings.inc.php';
        $settings = new Settings($mysql);
    }

    if (file_exists(SOURCEDIR . '/includes/klassen/merchant.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/merchant.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/actor.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/actor.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/movie.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/movie.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/PhotoAlbum.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/PhotoAlbum.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/sendSMTPmail.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/sendSMTPmail.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/ip2country.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/ip2country.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/CoActorManager.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/CoActorManager.inc.php';
    }
    if (file_exists(SOURCEDIR . '/includes/klassen/EroCloudMailer.inc.php')) {
        include_once SOURCEDIR . '/includes/klassen/EroCloudMailer.inc.php';
    }
}

// 7. Mitarbeiter-Historie Logging-Funktion bereitstellen (analog acp/common.inc.php)
if (!function_exists('log_action')) {
    function log_action(string $string): void
    {
        $employee_id = isset($_SESSION['employee_id']) ? abs((int)$_SESSION['employee_id']) : 0;
        $userIp      = function_exists('getUserIP') ? (string)getUserIP() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        p4c_query("INSERT INTO `employee_history` SET 
            `employee_id` = '" . abs((int)$employee_id) . "',
            `description` = '" . p4c_escape_string($string) . "',
            `datetime`    = '" . date('Y-m-d H:i:s') . "',
            `ip`          = '" . p4c_escape_string($userIp) . "';", __FILE__, __LINE__);
    }
}

/**
 * 8. Prüft, ob der eingeloggte Admin Zugriff auf einen bestimmten ACP-Bereich hat
 */
if (!function_exists('acp_has_access')) {
    function acp_has_access(string $area): bool
    {
        if ($area === '' || $area === 'home' || $area === 'startseite') {
            return true;
        }
        $accessList = isset($_SESSION['employee_access_area']) && is_array($_SESSION['employee_access_area'])
            ? $_SESSION['employee_access_area']
            : [];
        if (in_array('all', $accessList, true)) {
            return true;
        }
        foreach (explode(',', $area) as $reqArea) {
            $reqArea = trim($reqArea);
            if ($reqArea !== '' && in_array($reqArea, $accessList, true)) {
                return true;
            }
        }
        return false;
    }
}

/**
 * 9. Intelligenter Session-Schutz & Auto-Recovery für acp_neu (30 Minuten analog ACP_SESSION_DURATION):
 * Verhindert vorzeitigen Session-Verlust durch den PHP Garbage Collector im geteilten TEMP_DIR.
 */
if (!function_exists('acp_neu_sync_auth_cookie')) {
    function acp_neu_sync_auth_cookie(int $employeeId, string $username): void
    {
        if (headers_sent() || $employeeId <= 0 || !defined('AES_KEY')) {
            return;
        }
        $exp     = time() + 1800; // 30 Minuten analog ACP_SESSION_DURATION
        $dataStr = $employeeId . '|' . $exp . '|' . $username;
        $sig     = hash_hmac('sha256', $dataStr, (string)AES_KEY);
        $token   = base64_encode((string)json_encode([
            'eid' => $employeeId,
            'exp' => $exp,
            'usr' => $username,
            'sig' => $sig,
        ]));
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
        @setcookie('erocloud_acp_auth', $token, [
            'expires'  => $exp,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('acp_neu_clear_auth_cookie')) {
    function acp_neu_clear_auth_cookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
        @setcookie('erocloud_acp_auth', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('acp_neu_ensure_session')) {
    function acp_neu_ensure_session(): bool
    {
        if (isset($_GET['logout'])) {
            return false;
        }

        // 1. Reguläre PHP-Session prüfen
        if (function_exists('is_logged_in') && is_logged_in('acp') === true && !empty($_SESSION['employee_id'])) {
            $eid = abs((int)$_SESSION['employee_id']);
            $usr = (string)($_SESSION['employee_username'] ?? '');
            if (!isset($_SESSION['employee_access_area']) || !is_array($_SESSION['employee_access_area'])) {
                $rsEmp = p4c_query("SELECT `rule`, `access_area`, `username` FROM `employee` WHERE `id` = '" . $eid . "' LIMIT 1;", __FILE__, __LINE__);
                if ($rsEmp && p4c_num_rows($rsEmp) > 0) {
                    $empRow = p4c_fetch_object($rsEmp);
                    $_SESSION['employee_rule']        = (string)($empRow->rule ?? '');
                    $_SESSION['employee_access_area'] = array_map('trim', explode(',', (string)($empRow->access_area ?? '')));
                    $_SESSION['employee_username']    = (string)($empRow->username ?? $usr);
                    $usr = $_SESSION['employee_username'];
                } else {
                    $_SESSION['employee_access_area'] = [];
                }
            }
            acp_neu_sync_auth_cookie($eid, $usr);
            return true;
        }

        // 2. Wiederherstellung aus HMAC-signiertem 30-Minuten-Cookie (falls GC die /tmp-Datei vorzeitig gelöscht hat)
        if (!empty($_COOKIE['erocloud_acp_auth']) && defined('AES_KEY')) {
            $rawJson = @base64_decode((string)$_COOKIE['erocloud_acp_auth'], true);
            $payload = is_string($rawJson) ? @json_decode($rawJson, true) : null;
            if (is_array($payload) && isset($payload['eid'], $payload['exp'], $payload['usr'], $payload['sig'])) {
                $eid      = abs((int)$payload['eid']);
                $exp      = abs((int)$payload['exp']);
                $usr      = (string)$payload['usr'];
                $sig      = (string)$payload['sig'];
                $expected = hash_hmac('sha256', $eid . '|' . $exp . '|' . $usr, (string)AES_KEY);

                if ($eid > 0 && $exp >= time() && hash_equals($expected, $sig)) {
                    $rsCheck = p4c_query("SELECT `id`, `username`, `rule`, `access_area` FROM `employee` WHERE `id` = '" . $eid . "' LIMIT 1;", __FILE__, __LINE__);
                    if ($rsCheck && p4c_num_rows($rsCheck) > 0) {
                        $empObj = p4c_fetch_object($rsCheck);
                        $_SESSION['logged_id']            = 'acp';
                        $_SESSION['employee_id']          = abs((int)$empObj->id);
                        $_SESSION['employee_rule']        = (string)($empObj->rule ?? '');
                        $_SESSION['employee_access_area'] = array_map('trim', explode(',', (string)($empObj->access_area ?? '')));
                        $_SESSION['employee_username']    = (string)($empObj->username ?? $usr);
                        $_SESSION['last_activity']        = time();
                        acp_neu_sync_auth_cookie($_SESSION['employee_id'], $_SESSION['employee_username']);
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

// Session-Prüfung & Auto-Recovery direkt beim Bootstrapping ausführen
acp_neu_ensure_session();

// 10. Automatischer Shutdown-Handler für Error-Logging & DB-Cleanup
register_shutdown_function(static function (): void {
    if (function_exists('p4c_errorlog')) {
        p4c_errorlog(error_get_last());
    }
    if (function_exists('p4c_close') && defined('DB_HOST')) {
        @p4c_close(DB_HOST);
    }
});

// 11. PSR-4 Autoloader für App\ Namespace laden und registrieren
require_once __DIR__ . '/src/Core/Autoloader.php';
\App\Core\Autoloader::register();
