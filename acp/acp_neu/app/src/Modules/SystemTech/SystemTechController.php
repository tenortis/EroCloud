<?php
declare(strict_types=1);

namespace App\Modules\SystemTech;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) – Modul: System & Technik (#/system-settings & #/errors)
 *
 * Ersetzt:
 *  - acp/includes/system_settings.php (Impressum, Support, PHPMailer-Konfiguration & Testversand)
 *  - acp/includes/errors.php          (Zentrales PHP-Error-Log /var/www/web1/htdocs/log/.errorlog)
 */
final class SystemTechController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Stellt sicher, dass EroCloudMailer und EroCloudNotificationCenter geladen sind
     */
    private function ensureMailClasses(): void
    {
        if (!class_exists('EroCloudNotificationCenter') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/EroCloudNotificationCenter.inc.php')) {
            include_once SOURCEDIR . '/includes/klassen/EroCloudNotificationCenter.inc.php';
        }
        if (!class_exists('EroCloudMailer') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/EroCloudMailer.inc.php')) {
            include_once SOURCEDIR . '/includes/klassen/EroCloudMailer.inc.php';
        }
        if (class_exists('EroCloudNotificationCenter')) {
            \EroCloudNotificationCenter::ensureTablesExist();
        }
    }

    /**
     * Formatiert Bytes lesbar (KB, MB, GB)
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Bytes';
        }
        $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $pow   = (int)floor(log($bytes, 1024));
        $pow   = min($pow, count($units) - 1);
        $val   = $bytes / (1024 ** $pow);
        return number_format($val, $pow === 0 ? 0 : 2, ',', '.') . ' ' . $units[$pow];
    }

    // =========================================================================
    // 1. Impressum, Support & E-Mail-System (GET ?route=system-settings)
    // =========================================================================
    public function settings(): string
    {
        $this->ensureMailClasses();

        $imprint  = class_exists('EroCloudMailer') ? \EroCloudMailer::getImprintData(true) : [];
        $defaults = class_exists('EroCloudMailer') ? \EroCloudMailer::getDefaultImprintData() : [];

        $smtpConfig = [
            'is_sandbox'        => (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true),
            'sandbox_recipient' => defined('MAIL_SANDBOX_RECIPIENT') ? (string)MAIL_SANDBOX_RECIPIENT : 'erocms@gmail.com',
            'smtp_host'         => defined('MAIL_SMTP_HOST') ? (string)MAIL_SMTP_HOST : 'mail.erocloud.net',
            'smtp_port'         => defined('MAIL_SMTP_PORT') ? (int)MAIL_SMTP_PORT : 587,
            'smtp_secure'       => defined('MAIL_SMTP_SECURE') ? strtoupper((string)MAIL_SMTP_SECURE) : 'STARTTLS',
            'smtp_user'         => defined('MAIL_SMTP_USER') ? (string)MAIL_SMTP_USER : 'noreply@erocloud.net',
            'from_address'      => defined('MAIL_FROM_ADDRESS') ? (string)MAIL_FROM_ADDRESS : 'noreply@erocloud.net',
            'from_name'         => defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'EroCloud',
        ];

        // Versand-Statistiken aus notification_log
        $mailStats = [
            'total_sent'    => 0,
            'total_sandbox' => 0,
            'total_failed'  => 0,
            'sent_today'    => 0,
            'prefs_count'   => 0,
            'push_devices'  => 0,
        ];

        $todayStart = date('Y-m-d 00:00:00');
        $rsStats = @p4c_query(
            "SELECT
                SUM(CASE WHEN `status` = 'sent' THEN 1 ELSE 0 END) AS `cnt_sent`,
                SUM(CASE WHEN `status` = 'sandbox' THEN 1 ELSE 0 END) AS `cnt_sandbox`,
                SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) AS `cnt_failed`,
                SUM(CASE WHEN `created_at` >= '" . p4c_escape_string($todayStart) . "' AND `status` IN ('sent','sandbox') THEN 1 ELSE 0 END) AS `cnt_today`
             FROM `notification_log`;",
            __FILE__,
            __LINE__
        );
        if ($rsStats && ($rS = p4c_fetch_object($rsStats))) {
            $mailStats['total_sent']    = (int)($rS->cnt_sent ?? 0);
            $mailStats['total_sandbox'] = (int)($rS->cnt_sandbox ?? 0);
            $mailStats['total_failed']  = (int)($rS->cnt_failed ?? 0);
            $mailStats['sent_today']    = (int)($rS->cnt_today ?? 0);
        }

        $rsPrefCnt = @p4c_query("SELECT COUNT(*) AS `cnt` FROM `notification_preferences`;", __FILE__, __LINE__);
        if ($rsPrefCnt && ($rP = p4c_fetch_object($rsPrefCnt))) {
            $mailStats['prefs_count'] = (int)($rP->cnt ?? 0);
        }

        $rsPushCnt = @p4c_query("SELECT COUNT(*) AS `cnt` FROM `notification_push_subscriptions` WHERE `is_active` = 1;", __FILE__, __LINE__);
        if ($rsPushCnt && ($rPu = p4c_fetch_object($rsPushCnt))) {
            $mailStats['push_devices'] = (int)($rPu->cnt ?? 0);
        }

        // Letzte 100 Einträge aus notification_log
        $deliveryLogs = [];
        $rsLogs = @p4c_query(
            "SELECT
                `id`, `merchant_id`, `channel`, `event_key`, `reference_type`, `reference_id`,
                `recipient`, `status`, `message_id`, `error_message`, `created_at`
             FROM `notification_log`
             ORDER BY `id` DESC
             LIMIT 100;",
            __FILE__,
            __LINE__
        );
        if ($rsLogs) {
            while ($row = p4c_fetch_object($rsLogs)) {
                $ts = strtotime((string)($row->created_at ?? ''));
                $deliveryLogs[] = [
                    'id'             => abs((int)$row->id),
                    'merchant_id'    => abs((int)$row->merchant_id),
                    'channel'        => (string)($row->channel ?? 'email'),
                    'event_key'      => (string)($row->event_key ?? ''),
                    'reference_type' => (string)($row->reference_type ?? ''),
                    'reference_id'   => abs((int)$row->reference_id),
                    'recipient'      => (string)($row->recipient ?? ''),
                    'status'         => (string)($row->status ?? ''),
                    'message_id'     => (string)($row->message_id ?? ''),
                    'error_message'  => (string)($row->error_message ?? ''),
                    'created_at_de'  => ($ts !== false && $ts > 0) ? date('d.m.Y H:i:s', $ts) : (string)($row->created_at ?? '-'),
                ];
            }
        }

        // System- & PHP-Umgebungsinformationen
        $logFile = defined('SOURCEDIR') ? SOURCEDIR . '/log/.errorlog' : '';
        $errorLogSize = ($logFile !== '' && is_file($logFile)) ? (int)@filesize($logFile) : 0;

        $envInfo = [
            'php_version'         => PHP_VERSION,
            'php_sapi'            => PHP_SAPI,
            'server_hostname'     => php_uname('n'),
            'os_info'             => PHP_OS_FAMILY . ' (' . php_uname('s') . ' ' . php_uname('r') . ')',
            'timezone'            => date_default_timezone_get(),
            'server_time'         => date('d.m.Y H:i:s'),
            'memory_limit'        => (string)ini_get('memory_limit'),
            'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
            'post_max_size'       => (string)ini_get('post_max_size'),
            'max_execution_time'  => (string)ini_get('max_execution_time') . 's',
            'error_log_path'      => $logFile,
            'error_log_size_fmt'  => $this->formatBytes($errorLogSize),
            'extensions'          => [
                'mysqli'   => extension_loaded('mysqli'),
                'openssl'  => extension_loaded('openssl'),
                'mbstring' => extension_loaded('mbstring'),
                'curl'     => extension_loaded('curl'),
                'gd'       => extension_loaded('gd'),
                'intl'     => extension_loaded('intl'),
                'zip'      => extension_loaded('zip'),
            ],
        ];

        // Initiale Template-Vorschau für Tab 2 rendern
        $initialPreview = ['subject' => '', 'html' => '', 'text' => ''];
        if (class_exists('EroCloudMailer')) {
            $initialPreview = \EroCloudMailer::renderTemplate('movie_converted', [
                'merchant_id'     => 10061,
                'creator_name'    => 'Lara',
                'intended_email'  => 'creator-beispiel@domain.tld',
                'movie_id'        => 48291,
                'upload_datetime' => date('d.m.Y \u\m H:i \U\h\r'),
                'playtime'        => '18:45',
                'resolution'      => '1920x1080 (Full-HD)',
                'movie_edit_url'  => 'https://erocloud.net/mcp_neu/web/index.php#/movie?id=48291',
                'unsubscribe_url' => \EroCloudMailer::getUnsubscribeUrl(10061, 'movie_converted'),
            ]);
        }

        return View::render('SystemTech', 'settings', [
            'imprint'        => $imprint,
            'defaults'       => $defaults,
            'smtpConfig'     => $smtpConfig,
            'mailStats'      => $mailStats,
            'deliveryLogs'   => $deliveryLogs,
            'envInfo'        => $envInfo,
            'initialPreview' => $initialPreview,
        ]);
    }

    // =========================================================================
    // 2. Impressums- & Support-Daten speichern (POST ?route=system-settings/save-imprint)
    // =========================================================================
    public function saveImprint(): void
    {
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $this->ensureMailClasses();
        if (!class_exists('EroCloudMailer')) {
            Response::error('EroCloudMailer-Klasse konnte nicht geladen werden.', 500);
            return;
        }

        $resetDefaults = (int)$this->request->post('reset_defaults', 0) === 1;
        if ($resetDefaults) {
            $input = \EroCloudMailer::getDefaultImprintData();
        } else {
            $input = [
                'imprint_company_name'     => trim((string)$this->request->post('imprint_company_name', '')),
                'imprint_address_street'   => trim((string)$this->request->post('imprint_address_street', '')),
                'imprint_address_city'     => trim((string)$this->request->post('imprint_address_city', '')),
                'imprint_register_court'   => trim((string)$this->request->post('imprint_register_court', '')),
                'imprint_register_details' => trim((string)$this->request->post('imprint_register_details', '')),
                'imprint_vat_id'           => trim((string)$this->request->post('imprint_vat_id', '')),
                'imprint_ceo'              => trim((string)$this->request->post('imprint_ceo', '')),
                'imprint_phone'            => trim((string)$this->request->post('imprint_phone', '')),
                'imprint_whatsapp_url'     => trim((string)$this->request->post('imprint_whatsapp_url', '')),
                'imprint_support_email'    => trim((string)$this->request->post('imprint_support_email', '')),
                'imprint_website_url'      => trim((string)$this->request->post('imprint_website_url', '')),
                'imprint_external_url'     => trim((string)$this->request->post('imprint_external_url', '')),
            ];
        }

        $ok = \EroCloudMailer::saveImprintData($input);
        if (!$ok) {
            Response::error('Fehler beim Speichern der Impressums- und Support-Daten in der Datenbank.', 500);
            return;
        }

        if (function_exists('log_action')) {
            log_action($resetDefaults
                ? 'Zentrale Impressums- & Support-Einstellungen auf Standardwerte zurückgesetzt.'
                : 'Zentrale Impressums- & Support-Einstellungen aktualisiert.');
        }

        Response::json([
            'status'  => 'ok',
            'message' => $resetDefaults
                ? 'Die Impressums- und Support-Daten wurden auf die Pay4Coins-Standardwerte zurückgesetzt.'
                : 'Die zentralen Impressums- und Support-Daten wurden erfolgreich gespeichert.',
            'imprint' => \EroCloudMailer::getImprintData(true),
        ]);
    }

    // =========================================================================
    // 3. Test-E-Mail versenden / Vorschau rendern (POST ?route=system-settings/send-test-mail)
    // =========================================================================
    public function sendTestMail(): void
    {
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $this->ensureMailClasses();
        if (!class_exists('EroCloudMailer')) {
            Response::error('EroCloudMailer-Klasse konnte nicht geladen werden.', 500);
            return;
        }

        $mode        = trim((string)$this->request->post('mode', 'send')); // 'send' oder 'preview'
        $creatorName = trim((string)$this->request->post('creator_name', 'Lara')) ?: 'Lara';
        $toEmail     = trim((string)$this->request->post('to_email', 'creator-beispiel@domain.tld')) ?: 'creator-beispiel@domain.tld';
        $movieId     = max(1, abs((int)$this->request->post('movie_id', 48291)));
        $playtime    = trim((string)$this->request->post('playtime', '18:45')) ?: '18:45';
        $resolution  = trim((string)$this->request->post('resolution', '1920x1080 (Full-HD)')) ?: '1920x1080 (Full-HD)';
        $merchantId  = max(1, abs((int)$this->request->post('merchant_id', 10061)));

        $rendered = \EroCloudMailer::renderTemplate('movie_converted', [
            'merchant_id'     => $merchantId,
            'creator_name'    => $creatorName,
            'intended_email'  => $toEmail,
            'movie_id'        => $movieId,
            'upload_datetime' => date('d.m.Y \u\m H:i \U\h\r'),
            'playtime'        => $playtime,
            'resolution'      => $resolution,
            'movie_edit_url'  => 'https://erocloud.net/mcp_neu/web/index.php#/movie?id=' . $movieId,
            'unsubscribe_url' => \EroCloudMailer::getUnsubscribeUrl($merchantId, 'movie_converted'),
        ]);

        if ($mode === 'preview') {
            Response::json([
                'status'  => 'ok',
                'subject' => $rendered['subject'],
                'html'    => $rendered['html'],
                'text'    => $rendered['text'],
            ]);
            return;
        }

        $mailer = new \EroCloudMailer();
        $ok = $mailer->sendMovieConvertedNotification([
            'merchant_id'  => $merchantId,
            'to_email'     => $toEmail,
            'creator_name' => $creatorName,
            'movie_id'     => $movieId,
            'playtime'     => $playtime,
            'resolution'   => $resolution,
        ]);

        if (!$ok) {
            Response::error('Test-E-Mail-Versand fehlgeschlagen: ' . $mailer->lastError, 500);
            return;
        }

        if (function_exists('log_action')) {
            log_action('Test-E-Mail (movie_converted) an ' . $mailer->effectiveRecipient . ' gesendet.');
        }

        Response::json([
            'status'              => 'ok',
            'message'             => 'Test-E-Mail erfolgreich an ' . $mailer->effectiveRecipient . ' versendet (Message-ID: ' . $mailer->lastMessageId . ').',
            'effective_recipient' => $mailer->effectiveRecipient,
            'message_id'          => $mailer->lastMessageId,
            'subject'             => $rendered['subject'],
            'html'                => $rendered['html'],
            'text'                => $rendered['text'],
        ]);
    }

    // =========================================================================
    // 4. Zentrales PHP-Error-Log (GET ?route=errors)
    // =========================================================================
    public function errors(): string
    {
        $logFile = defined('SOURCEDIR') ? SOURCEDIR . '/log/.errorlog' : '';
        $bakFile = defined('SOURCEDIR') ? SOURCEDIR . '/log/.errorlog.bak' : '';

        $fileExists   = ($logFile !== '' && is_file($logFile));
        $fileWritable = $fileExists ? is_writable($logFile) : ($logFile !== '' && is_writable(dirname($logFile)));
        $fileSize     = $fileExists ? (int)@filesize($logFile) : 0;
        $fileMtime    = $fileExists ? (int)@filemtime($logFile) : 0;
        $hasBackup    = ($bakFile !== '' && is_file($bakFile));
        $backupSize   = $hasBackup ? (int)@filesize($bakFile) : 0;

        // Sicher bis zu den letzten 1,5 MB einlesen (verhindert RAM-Überlauf bei riesigen Logs)
        $maxReadBytes = 1536 * 1024;
        $rawContent   = '';
        $isTruncated  = false;

        if ($fileExists && $fileSize > 0) {
            $fp = @fopen($logFile, 'rb');
            if (is_resource($fp)) {
                if ($fileSize > $maxReadBytes) {
                    $isTruncated = true;
                    @fseek($fp, $fileSize - $maxReadBytes);
                    // Erste angeschnittene Zeile verwerfen
                    @fgets($fp);
                }
                $rawContent = (string)@stream_get_contents($fp);
                @fclose($fp);
            }
        }

        if ($rawContent !== '' && !mb_check_encoding($rawContent, 'UTF-8')) {
            $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'ISO-8859-1');
        }

        // Log-Einträge strukturiert parsen
        $parsed = $this->parseErrorLogContent($rawContent);

        return View::render('SystemTech', 'errors', [
            'logMeta' => [
                'path'          => $logFile,
                'exists'        => $fileExists,
                'writable'      => $fileWritable,
                'size_bytes'    => $fileSize,
                'size_fmt'      => $this->formatBytes($fileSize),
                'mtime_de'      => $fileMtime > 0 ? date('d.m.Y H:i:s', $fileMtime) : 'Noch keine Einträge',
                'is_truncated'  => $isTruncated,
                'has_backup'    => $hasBackup,
                'backup_size'   => $this->formatBytes($backupSize),
            ],
            'kpi'            => $parsed['kpi'],
            'entries'        => $parsed['entries'],
            'groupedEntries' => $parsed['grouped'],
            'rawContent'     => trim($rawContent),
        ]);
    }

    /**
     * Parst den Inhalt von `.errorlog` in strukturierte Einzel- und Gruppeneinträge
     *
     * @return array{kpi: array<string, int>, entries: array<int, array<string, mixed>>, grouped: array<int, array<string, mixed>>}
     */
    private function parseErrorLogContent(string $rawContent): array
    {
        $kpi = [
            'total_entries' => 0,
            'fatal_errors'  => 0,
            'warnings'      => 0,
            'deprecations'  => 0,
            'sql_errors'    => 0,
            'cli_entries'   => 0,
            'http_entries'  => 0,
        ];

        $trimmed = trim($rawContent);
        if ($trimmed === '') {
            return ['kpi' => $kpi, 'entries' => [], 'grouped' => []];
        }

        $lines      = explode("\n", str_replace(["\r\n", "\r"], "\n", $trimmed));
        $rawBlocks  = [];
        $currentBlk = '';

        foreach ($lines as $line) {
            if (preg_match('/^\[[^\]]+\]\s/', $line)) {
                if ($currentBlk !== '') {
                    $rawBlocks[] = $currentBlk;
                }
                $currentBlk = $line;
            } else {
                if ($currentBlk === '') {
                    $currentBlk = $line;
                } else {
                    $currentBlk .= "\n" . $line;
                }
            }
        }
        if ($currentBlk !== '') {
            $rawBlocks[] = $currentBlk;
        }

        $entries = [];
        $groups  = [];

        foreach ($rawBlocks as $idx => $block) {
            $timestamp = '';
            $context   = 'System';
            $isCli     = false;
            $body      = $block;

            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $block, $m)) {
                $timestamp = trim($m[1]);
                $body      = trim($m[2]);
            }

            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $body, $mCtx)) {
                $context = trim($mCtx[1]);
                $body    = trim($mCtx[2]);
                if (str_starts_with(strtoupper($context), 'CLI:')) {
                    $isCli = true;
                }
            }

            $file = '';
            $lineNo = 0;
            $message = $body;

            if (preg_match('/^(.*)\s+in\s+([^\s]+)\s+on\s+line\s+(\d+)\s*$/s', $body, $mLoc)) {
                $message = trim($mLoc[1]);
                $file    = trim($mLoc[2]);
                $lineNo  = (int)$mLoc[3];
            } elseif (preg_match('/^(.*)\s+in\s+([^\s:]+):(\d+)\s*$/s', $body, $mLoc2)) {
                $message = trim($mLoc2[1]);
                $file    = trim($mLoc2[2]);
                $lineNo  = (int)$mLoc2[3];
            }

            // Schweregrad klassifizieren
            $msgLower = strtolower($message);
            $severity = 'info';
            $severityLabel = 'Info / Hinweis';
            $badgeClass = 'badge-kkc-info';

            if (str_contains($msgLower, 'fatal error') || str_contains($msgLower, 'uncaught') || str_contains($msgLower, 'parse error') || str_contains($msgLower, 'typeerror') || str_contains($msgLower, 'exception')) {
                $severity = 'fatal';
                $severityLabel = 'Fatal / Exception';
                $badgeClass = 'badge-kkc-danger';
                $kpi['fatal_errors']++;
            } elseif (str_contains($msgLower, 'mysql') || str_contains($msgLower, 'sql') || str_contains($msgLower, 'query') || str_contains($msgLower, 'duplicate entry')) {
                $severity = 'sql';
                $severityLabel = 'SQL / Datenbank';
                $badgeClass = 'badge-kkc-danger';
                $kpi['sql_errors']++;
            } elseif (str_contains($msgLower, 'deprecated') || str_contains($msgLower, 'notice') || str_contains($msgLower, 'undefined')) {
                $severity = 'deprecated';
                $severityLabel = 'Deprecated / Notice';
                $badgeClass = 'badge-kkc-neutral';
                $kpi['deprecations']++;
            } elseif (str_contains($msgLower, 'warning') || str_contains($msgLower, 'fehler') || str_contains($msgLower, 'error')) {
                $severity = 'warning';
                $severityLabel = 'Warning / Fehler';
                $badgeClass = 'badge-kkc-warning';
                $kpi['warnings']++;
            } else {
                $kpi['warnings']++;
            }

            $kpi['total_entries']++;
            if ($isCli) {
                $kpi['cli_entries']++;
            } else {
                $kpi['http_entries']++;
            }

            $shortFile = $file !== '' ? basename(str_replace('\\', '/', $file)) : '';
            $entryItem = [
                'index'          => $idx + 1,
                'timestamp'      => $timestamp !== '' ? $timestamp : '-',
                'context'        => $context,
                'is_cli'         => $isCli,
                'severity'       => $severity,
                'severity_label' => $severityLabel,
                'badge_class'    => $badgeClass,
                'message'        => $message,
                'file'           => $file,
                'short_file'     => $shortFile,
                'line'           => $lineNo,
                'raw'            => $block,
            ];

            $entries[] = $entryItem;

            // Gruppierung nach (severity + file + line + gekürzter Meldung)
            $groupKey = md5($severity . '|' . $file . '|' . $lineNo . '|' . mb_substr($message, 0, 140));
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'count'          => 1,
                    'first_seen'     => $entryItem['timestamp'],
                    'last_seen'      => $entryItem['timestamp'],
                    'severity'       => $severity,
                    'severity_label' => $severityLabel,
                    'badge_class'    => $badgeClass,
                    'context'        => $context,
                    'message'        => $message,
                    'file'           => $file,
                    'short_file'     => $shortFile,
                    'line'           => $lineNo,
                ];
            } else {
                $groups[$groupKey]['count']++;
                $groups[$groupKey]['last_seen'] = $entryItem['timestamp'];
            }
        }

        // Neueste zuerst (max. 300 in der Tabelle für flüssiges DOM-Rendering)
        $entriesDesc = array_slice(array_reverse($entries), 0, 300);

        $groupedList = array_values($groups);
        usort($groupedList, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'kpi'     => $kpi,
            'entries' => $entriesDesc,
            'grouped' => array_slice($groupedList, 0, 150),
        ];
    }

    // =========================================================================
    // 5. Wartungs-Aktionen für das PHP-Error-Log (POST ?route=errors/action)
    // =========================================================================
    public function errorLogAction(): void
    {
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $action  = trim((string)$this->request->post('action_type', ''));
        $logFile = defined('SOURCEDIR') ? SOURCEDIR . '/log/.errorlog' : '';
        $bakFile = defined('SOURCEDIR') ? SOURCEDIR . '/log/.errorlog.bak' : '';

        if ($logFile === '') {
            Response::error('Log-Pfad (SOURCEDIR) ist nicht definiert.', 500);
            return;
        }

        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        if ($action === 'clear_log') {
            if (is_file($logFile) && (int)@filesize($logFile) > 0) {
                @copy($logFile, $bakFile);
            }
            $ok = @file_put_contents($logFile, '') !== false;
            if (!$ok) {
                Response::error('Die Log-Datei konnte nicht geleert werden (fehlende Schreibrechte).', 500);
                return;
            }
            if (function_exists('log_action')) {
                log_action('Zentrales PHP-Error-Log (.errorlog) geleert (Backup .errorlog.bak erstellt).');
            }
            Response::json([
                'status'  => 'ok',
                'message' => 'Das zentrale PHP-Error-Log wurde erfolgreich geleert (Sicherungskopie unter .errorlog.bak gespeichert).',
            ]);
            return;
        }

        if ($action === 'restore_backup') {
            if (!is_file($bakFile)) {
                Response::error('Es ist keine Sicherungskopie (.errorlog.bak) vorhanden.', 404);
                return;
            }
            @copy($bakFile, $logFile);
            if (function_exists('log_action')) {
                log_action('PHP-Error-Log aus Sicherungskopie (.errorlog.bak) wiederhergestellt.');
            }
            Response::json([
                'status'  => 'ok',
                'message' => 'Das Backup (.errorlog.bak) wurde erfolgreich wiederhergestellt.',
            ]);
            return;
        }

        if ($action === 'write_test_entry') {
            global $class_errorlog;
            $adminUser = (string)($_SESSION['employee_username'] ?? 'Admin');
            $testMsg   = '[ACP Diagnose-Test] Manueller Test-Eintrag ausgelöst von ' . $adminUser . ' (PHP ' . PHP_VERSION . ')';
            if (isset($class_errorlog) && is_object($class_errorlog) && method_exists($class_errorlog, 'log')) {
                $class_errorlog->log($testMsg, __FILE__, __LINE__);
            } else {
                @error_log('[' . date('d-M-Y H:i:s T') . '] ' . $testMsg . ' in ' . __FILE__ . ' on line ' . __LINE__ . "\n", 3, $logFile);
            }
            Response::json([
                'status'  => 'ok',
                'message' => 'Diagnose-Testeintrag erfolgreich in .errorlog geschrieben.',
            ]);
            return;
        }

        Response::error('Unbekannte Aktion: ' . $action, 400);
    }
}
