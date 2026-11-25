#!/usr/bin/php
<?php

declare(strict_types=1);

/**
 * Hintergrund-Cronjob zur regelmaessigen Synchronisation aller Webseiten (`sites`)
 * inkl. Pay4Coins Shop-Kategorien und Kreditkarten-/Paysafecard-Status.
 *
 * Aufruf (LiveConfig / Crontab alle 15 Minuten):
 *   * / 15 * * * * /opt/php-8.4/bin/php /var/www/web1/htdocs/cronjobs/sync_p4c_sites.php >/dev/null 2>&1
 */

define('SAFE_INC', 1);

$sourcedir = dirname(__DIR__);
include_once($sourcedir . '/config.inc.php');
include_once(MCP_DIR . '/common.inc.php');
include_once($sourcedir . '/includes/p4c_sites_sync.inc.php');

@set_time_limit(120);

$lockDir  = (defined('TEMP_DIR') && is_dir(TEMP_DIR) && is_writable(TEMP_DIR))
    ? rtrim((string)TEMP_DIR, '/\\')
    : rtrim(sys_get_temp_dir(), '/\\');
$lockPath = $lockDir . '/erocloud_sync_p4c_sites.lock';
$lockFp   = @fopen($lockPath, 'c+');

if ($lockFp !== false && !@flock($lockFp, LOCK_EX | LOCK_NB)) {
    @fclose($lockFp);
    p4c_close();
    exit(0);
}

$exitCode = 0;
try {
    $res = ec_p4c_sync_all_sites();
    if (empty($res['ok'])) {
        $errMsg = 'sync_p4c_sites Cronjob fehlgeschlagen: ' . (string)($res['error'] ?? 'Unbekannter Fehler');
        if (isset($class_errorlog) && is_object($class_errorlog)) {
            $class_errorlog->log($errMsg, __FILE__, __LINE__);
        } else {
            error_log($errMsg);
        }
        $exitCode = 1;
    }
} catch (\Throwable $e) {
    $errMsg = 'sync_p4c_sites Cronjob Exception: ' . $e->getMessage();
    if (isset($class_errorlog) && is_object($class_errorlog)) {
        $class_errorlog->log($errMsg, __FILE__, __LINE__);
    } else {
        error_log($errMsg);
    }
    $exitCode = 1;
} finally {
    if ($lockFp !== false) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
    if (function_exists('p4c_close')) {
        p4c_close();
    }
}

exit($exitCode);
