<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Zentraler Anwendungs-Bootstrap
 * Unterstützt sowohl den Entwicklungs-Pfad (/mcp/mcp_neu/app) als auch den späteren Live-Pfad (/mcp/app).
 */

// 1. Zeitzone vor dem Laden der config.inc.php verbindlich setzen
date_default_timezone_set('Europe/Berlin');

// 2. Dynamische Suche nach der zentralen config.inc.php (EroCloud Root)
$configCandidates = [
    dirname(__DIR__, 2) . '/config.inc.php', // Späterer Live-Pfad: /htdocs/mcp/app -> /htdocs/config.inc.php
    dirname(__DIR__, 3) . '/config.inc.php', // Entwicklungs-Pfad:  /htdocs/mcp/mcp_neu/app -> /htdocs/config.inc.php
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

// 4. Session starten (kompatibel mit bestehender EroCloud MCP-Session für nahtloses Session-Sharing)
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

// 5. Cookie-Manipulation verhindern & Referer-Schutz (analog mcp/common.inc.php)
if (isset($_GET['referer'])) {
    $_SERVER['HTTP_REFERER'] = $_GET['referer'];
}
if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] === '') {
    $_COOKIE['PHPSESSID'] = '0x';
}

// 6. Error-Handler, Datenbank ($mysql) & Kernklassen initialisieren
if (defined('SOURCEDIR')) {
    include_once SOURCEDIR . '/includes/klassen/errorlog.inc.php';
    global $class_errorlog, $mysql, $config;
    $class_errorlog = new p4c_errorlog();

    include_once SOURCEDIR . '/includes/klassen/mysqli.inc.php';
    $mysql = new p4c_mysqli(DB_HOST, $config['db_user'], $config['db_pass'], $config['db_name']);
    p4c_query("SET NAMES 'utf8mb4';", __FILE__, __LINE__);

    include_once SOURCEDIR . '/includes/klassen/merchant.inc.php';
    include_once SOURCEDIR . '/includes/klassen/actor.inc.php';
    include_once SOURCEDIR . '/includes/klassen/movie.inc.php';
    include_once SOURCEDIR . '/includes/klassen/PhotoAlbum.inc.php';
    include_once SOURCEDIR . '/includes/klassen/sendSMTPmail.inc.php';
}

// 7. Partner-Historie Logging-Funktion bereitstellen
if (!function_exists('log_action')) {
    function log_action(string $string): void
    {
        $merchant_id = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        p4c_query("INSERT INTO `merchant_history` SET 
            `merchant_id` = '" . abs((int)$merchant_id) . "',
            `description` = '" . p4c_escape_string($string) . "',
            `datetime`    = '" . date('Y-m-d H:i:s') . "',
            `ip`          = '" . p4c_escape_string((string)getUserIP()) . "';", __FILE__, __LINE__);
    }
}

/**
 * 8. Intelligenter Session-Schutz & Auto-Recovery für mcp_neu:
 * Da api/common.inc.php und acp/common.inc.php im Altsystem denselben TEMP_DIR-Ordner ohne
 * gesetztes session.gc_maxlifetime (Standard: 24 Min. statt 180 Min.) nutzen, kann der PHP
 * Garbage Collector inaktive Session-Dateien vorzeitig löschen.
 *
 * - Stufe A: Solange die 3-Stunden-Frist (10800s) nicht abgelaufen ist, stellt ein mit AES_KEY
 *   HMAC-signiertes Cookie (`erocloud_mcp_auth`) die PHP-Session nahtlos wieder her.
 * - Stufe B: Ist auch dieses abgelaufen, aber das Pay4Coins-SSO-Cookie (`erocloud`) noch gültig,
 *   wird die Sitzung direkt gegen die Pay4Coins-API verifiziert – ohne Umweg über das alte
 *   /login.php -> /Startseite!
 */
if (!function_exists('mcp_neu_sync_auth_cookie')) {
    function mcp_neu_sync_auth_cookie(int $merchantId, string $username): void
    {
        if (headers_sent() || $merchantId <= 0 || !defined('AES_KEY')) {
            return;
        }
        $exp     = time() + 10800; // 180 Minuten analog MCP_SESSION_DURATION
        $dataStr = $merchantId . '|' . $exp . '|' . $username;
        $sig     = hash_hmac('sha256', $dataStr, (string)AES_KEY);
        $token   = base64_encode((string)json_encode([
            'mid' => $merchantId,
            'exp' => $exp,
            'usr' => $username,
            'sig' => $sig,
        ]));
        $domain = defined('MCP_DOMAIN') ? ('.' . ltrim((string)MCP_DOMAIN, '.')) : '';
        @setcookie('erocloud_mcp_auth', $token, [
            'expires'  => $exp,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('mcp_neu_clear_auth_cookie')) {
    function mcp_neu_clear_auth_cookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $domain = defined('MCP_DOMAIN') ? ('.' . ltrim((string)MCP_DOMAIN, '.')) : '';
        @setcookie('erocloud_mcp_auth', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('mcp_neu_ensure_session')) {
    function mcp_neu_ensure_session(): bool
    {
        global $mysql;

        if (isset($_GET['logout'])) {
            return false;
        }

        // 1. Reguläre PHP-Session prüfen
        if (function_exists('is_logged_in') && is_logged_in('mcp') === true && !empty($_SESSION['merchant_id'])) {
            $mid = abs((int)$_SESSION['merchant_id']);
            $usr = (string)($_SESSION['merchant_username'] ?? '');
            mcp_neu_sync_auth_cookie($mid, $usr);
            return true;
        }

        // 2. Stufe A: Wiederherstellung aus HMAC-signiertem 3-Stunden-Cookie (falls GC die /tmp-Datei gelöscht hat)
        if (!empty($_COOKIE['erocloud_mcp_auth']) && defined('AES_KEY')) {
            $rawJson = @base64_decode((string)$_COOKIE['erocloud_mcp_auth'], true);
            $payload = is_string($rawJson) ? @json_decode($rawJson, true) : null;
            if (is_array($payload) && isset($payload['mid'], $payload['exp'], $payload['usr'], $payload['sig'])) {
                $mid     = abs((int)$payload['mid']);
                $exp     = abs((int)$payload['exp']);
                $usr     = (string)$payload['usr'];
                $sig     = (string)$payload['sig'];
                $expected = hash_hmac('sha256', $mid . '|' . $exp . '|' . $usr, (string)AES_KEY);

                if ($mid > 0 && $exp >= time() && hash_equals($expected, $sig)) {
                    $rsCheck = p4c_query("SELECT `id` FROM `merchants` WHERE `id` = '" . $mid . "' LIMIT 1;", __FILE__, __LINE__);
                    if ($rsCheck && p4c_num_rows($rsCheck) > 0) {
                        $_SESSION['logged_in_as']      = 'merchant';
                        $_SESSION['logged_id']         = 'mcp';
                        $_SESSION['merchant_id']       = $mid;
                        $_SESSION['merchant_username'] = $usr;
                        $_SESSION['last_activity']     = time();
                        mcp_neu_sync_auth_cookie($mid, $usr);
                        return true;
                    }
                }
            }
        }

        // 3. Stufe B: Stille Pay4Coins-SSO-Prüfung über das bestehende `erocloud`-Cookie (verhindert Redirect ins alte MCP bei F5)
        if (!empty($_COOKIE['erocloud']) && defined('Pay4Coins_API_URL') && function_exists('loginHash')) {
            $rawCookie = @base64_decode((string)$_COOKIE['erocloud'], true);
            $cookieArr = is_string($rawCookie) ? @json_decode($rawCookie, true) : null;
            $pid       = is_array($cookieArr) && isset($cookieArr['pid'])
                ? trim((string)preg_replace('/[^a-z0-9]/i', '', (string)$cookieArr['pid']))
                : '';

            if (strlen($pid) === 10) {
                $loginHash = loginHash($pid);
                $ch        = curl_init();
                curl_setopt($ch, CURLOPT_URL, Pay4Coins_API_URL . '/remote/merchant_login.php');
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'h=' . $loginHash . '&pid=' . $pid);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                curl_setopt($ch, CURLOPT_USERAGENT, (defined('PROJECTNAME') ? PROJECTNAME : 'EroCloud') . ' - ' . (defined('COMPANYNAME') ? COMPANYNAME : '') . ' - ' . (defined('URL') ? URL : ''));
                $resp = curl_exec($ch);
                curl_close($ch);

                if ($resp === 'ok') {
                    $rsMerchant = p4c_query("SELECT `id` FROM `merchants` WHERE `partner_id` = '" . p4c_escape_string($pid) . "' LIMIT 1;", __FILE__, __LINE__);
                    if ($rsMerchant && p4c_num_rows($rsMerchant) > 0) {
                        $mRow     = p4c_fetch_object($rsMerchant);
                        $mid      = abs((int)$mRow->id);
                        $merchant = new Merchant($mysql, $mid);
                        $usr      = (string)$merchant->username('aes_decrypt');

                        $_SESSION['logged_in_as']      = 'merchant';
                        $_SESSION['logged_id']         = 'mcp';
                        $_SESSION['merchant_id']       = $mid;
                        $_SESSION['merchant_username'] = $usr;
                        $_SESSION['last_activity']     = time();

                        mcp_neu_sync_auth_cookie($mid, $usr);
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

// Session-Prüfung & Auto-Recovery direkt beim Bootstrapping ausführen
mcp_neu_ensure_session();

// 9. Automatischer Shutdown-Handler für Error-Logging & DB-Cleanup
register_shutdown_function(static function (): void {
    if (function_exists('p4c_errorlog')) {
        p4c_errorlog(error_get_last());
    }
    if (function_exists('p4c_close') && defined('DB_HOST')) {
        @p4c_close(DB_HOST);
    }
});

// 10. PSR-4 Autoloader für App\ Namespace laden und registrieren
require_once __DIR__ . '/src/Core/Autoloader.php';
\App\Core\Autoloader::register();
