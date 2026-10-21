<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Zentraler Anwendungs-Bootstrap
 * Unterstützt sowohl den Entwicklungs-Pfad (/mcp/mcp_neu/app) als auch den späteren Live-Pfad (/mcp/app).
 */

// 1. Dynamische Suche nach der zentralen config.inc.php (EroCloud Root)
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

// 2. Globale EroCloud Hilfsfunktionen laden
if (defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/functions.inc.php')) {
    include_once SOURCEDIR . '/includes/functions.inc.php';
}

// 3. Zeitzone verbindlich setzen
date_default_timezone_set('Europe/Berlin');

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

// 8. Automatischer Shutdown-Handler für Error-Logging & DB-Cleanup
register_shutdown_function(static function (): void {
    if (function_exists('p4c_errorlog')) {
        p4c_errorlog(error_get_last());
    }
    if (function_exists('p4c_close') && defined('DB_HOST')) {
        @p4c_close(DB_HOST);
    }
});

// 9. PSR-4 Autoloader für App\ Namespace laden und registrieren
require_once __DIR__ . '/src/Core/Autoloader.php';
\App\Core\Autoloader::register();
