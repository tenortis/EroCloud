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

// 2. Globale EroCloud Hilfsfunktionen laden (falls vorhanden)
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

// 5. PSR-4 Autoloader für App\ Namespace laden und registrieren
require_once __DIR__ . '/src/Core/Autoloader.php';
\App\Core\Autoloader::register();
