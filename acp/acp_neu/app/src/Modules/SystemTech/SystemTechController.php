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
     * Ermittelt die eigene Standard-Test-E-Mail-Adresse des eingeloggten Admins
     * (Prüft gespeicherten Favoriten `mail_test_recipient`, Admin-Notizen oder `sandbox_recipient` / `erocms@gmail.com`).
     *
     * @param array<string, mixed> $smtpConfig
     */
    private function getOwnAdminEmail(array $smtpConfig = []): string
    {
        $rsCustom = p4c_query(
            "SELECT `value` FROM `settings` WHERE `variable` = 'mail_test_recipient' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsCustom && p4c_num_rows($rsCustom) > 0) {
            $rowCustom = p4c_fetch_object($rsCustom);
            $valCustom = trim((string)($rowCustom->value ?? ''));
            if ($valCustom !== '' && filter_var($valCustom, FILTER_VALIDATE_EMAIL)) {
                return $valCustom;
            }
        }

        $empId = abs((int)($_SESSION['employee_id'] ?? 0));
        if ($empId > 0) {
            $rsEmp = p4c_query(
                "SELECT `notes` FROM `employee` WHERE `id` = '" . $empId . "' LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if ($rsEmp && p4c_num_rows($rsEmp) > 0) {
                $empRow = p4c_fetch_object($rsEmp);
                $notes  = trim((string)($empRow->notes ?? ''));
                if ($notes !== '' && preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $notes, $m)) {
                    if (filter_var($m[0], FILTER_VALIDATE_EMAIL)) {
                        return $m[0];
                    }
                }
            }
        }

        $sbRecip = trim((string)($smtpConfig['sandbox_recipient'] ?? ''));
        if ($sbRecip !== '' && filter_var($sbRecip, FILTER_VALIDATE_EMAIL)) {
            return $sbRecip;
        }

        if (defined('MAIL_SANDBOX_RECIPIENT') && filter_var((string)MAIL_SANDBOX_RECIPIENT, FILTER_VALIDATE_EMAIL)) {
            return (string)MAIL_SANDBOX_RECIPIENT;
        }

        return 'erocms@gmail.com';
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

        $rawSmtp = class_exists('EroCloudMailer') ? \EroCloudMailer::getSmtpConfig(true) : [];
        $smtpConfig = [
            'is_sandbox'        => isset($rawSmtp['sandbox_mode']) ? (bool)$rawSmtp['sandbox_mode'] : (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true),
            'sandbox_mode'      => isset($rawSmtp['sandbox_mode']) ? (bool)$rawSmtp['sandbox_mode'] : (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true),
            'sandbox_recipient' => (string)($rawSmtp['sandbox_recipient'] ?? 'erocms@gmail.com'),
            'smtp_host'         => (string)($rawSmtp['smtp_host'] ?? 't199.nlserver.net'),
            'smtp_port'         => (int)($rawSmtp['smtp_port'] ?? 587),
            'smtp_secure'       => strtolower((string)($rawSmtp['smtp_secure'] ?? 'tls')),
            'smtp_user'         => (string)($rawSmtp['smtp_user'] ?? 'no-reply@erocloud.net'),
            'has_password'      => !empty($rawSmtp['has_password']),
            'from_address'      => (string)($rawSmtp['from_address'] ?? 'no-reply@erocloud.net'),
            'from_name'         => (string)($rawSmtp['from_name'] ?? 'EroCloud Partner-Team'),
        ];

        $eventCatalog   = class_exists('EroCloudNotificationCenter') ? \EroCloudNotificationCenter::getEventCatalog() : [];
        $triggerCatalog = class_exists('EroCloudNotificationCenter') ? \EroCloudNotificationCenter::getTriggerCatalog() : [];
        $optInStats     = class_exists('EroCloudNotificationCenter') ? \EroCloudNotificationCenter::getOptInStats() : [];

        $triggerMap = [];
        foreach ($triggerCatalog as $trig) {
            $triggerMap[(string)$trig['trigger_key']] = $trig;
        }

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
        $rsStats = p4c_query(
            "SELECT
                SUM(CASE WHEN `status` = 'sent' THEN 1 ELSE 0 END) AS `cnt_sent`,
                SUM(CASE WHEN `status` IN ('sandbox', 'sandbox_sent') THEN 1 ELSE 0 END) AS `cnt_sandbox`,
                SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) AS `cnt_failed`,
                SUM(CASE WHEN `created_at` >= '" . p4c_escape_string($todayStart) . "' AND `status` IN ('sent','sandbox','sandbox_sent') THEN 1 ELSE 0 END) AS `cnt_today`
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

        $rsPrefCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `merchant_notification_prefs`;", __FILE__, __LINE__);
        if ($rsPrefCnt && ($rP = p4c_fetch_object($rsPrefCnt))) {
            $mailStats['prefs_count'] = (int)($rP->cnt ?? 0);
        }

        // Letzte 150 Einträge aus notification_log inkl. Partner-Infos für lückenloses Tracking
        $deliveryLogs = [];
        $aesKey = p4c_escape_string((string)AES_KEY);
        $rsLogs = p4c_query(
            "SELECT
                l.`id`,
                l.`merchant_id`,
                l.`event_key`,
                l.`trigger_detail`,
                l.`reference_id`,
                l.`recipient`,
                l.`intended_recipient`,
                l.`subject`,
                (l.`body_html` IS NOT NULL AND l.`body_html` != '') AS `has_body`,
                l.`sandbox_mode`,
                l.`status`,
                l.`error_message`,
                l.`created_at`,
                m.`partner_id`,
                CAST(AES_DECRYPT(m.`username`, '{$aesKey}') AS CHAR) AS `dec_username`,
                CAST(AES_DECRYPT(m.`email`, '{$aesKey}') AS CHAR) AS `dec_email`
             FROM `notification_log` l
             LEFT JOIN `merchants` m ON m.`id` = l.`merchant_id`
             ORDER BY l.`id` DESC
             LIMIT 150;",
            __FILE__,
            __LINE__
        );
        if ($rsLogs) {
            while ($row = p4c_fetch_object($rsLogs)) {
                $ts        = strtotime((string)($row->created_at ?? ''));
                $mid       = abs((int)$row->merchant_id);
                $evKey     = (string)($row->event_key ?? '');
                $trigKey   = (string)($row->trigger_detail ?? '');
                if ($trigKey === '' && $evKey === 'movie_converted') {
                    $trigKey = 'movie_converted';
                }
                $refId     = abs((int)$row->reference_id);
                $evMeta    = $eventCatalog[$evKey] ?? null;
                $trigMeta  = $triggerMap[$trigKey] ?? null;

                $refLabel = '';
                $refLink  = '';
                if ($refId > 0) {
                    if ($evKey === 'movie_converted' || str_starts_with($trigKey, 'movie_')) {
                        $refLabel = 'Film #' . $refId;
                        $refLink  = '#/movie-edit?id=' . $refId;
                    } elseif (str_starts_with($trigKey, 'album_')) {
                        $refLabel = 'Foto-Album #' . $refId;
                        $refLink  = '#/photo-album-edit?id=' . $refId;
                    } elseif (str_starts_with($trigKey, 'actor_')) {
                        $refLabel = 'Darsteller #' . $refId;
                        $refLink  = '#/actor?id=' . $refId;
                    } elseif ($evKey === 'co_actor_status' || str_starts_with($trigKey, 'co_actor_')) {
                        $refLabel = 'Drehpartner #' . $refId;
                        $refLink  = '#/co-actors-checking';
                    } else {
                        $refLabel = 'Ref #' . $refId;
                    }
                }

                $intendedRecip = trim((string)($row->intended_recipient ?? ''));
                if ($intendedRecip === '' && !empty($row->dec_email)) {
                    $intendedRecip = trim((string)$row->dec_email);
                }

                $deliveryLogs[] = [
                    'id'                 => abs((int)$row->id),
                    'merchant_id'        => $mid,
                    'partner_id'         => trim((string)($row->partner_id ?? '')),
                    'merchant_username'  => trim((string)($row->dec_username ?? '')),
                    'event_key'          => $evKey,
                    'event_label'        => $evMeta ? (string)$evMeta['title'] : $evKey,
                    'trigger_detail'     => $trigKey,
                    'trigger_label'      => $trigMeta ? (string)$trigMeta['title'] : ($evMeta ? (string)$evMeta['title'] : $evKey),
                    'trigger_badge_cls'  => $trigMeta ? (string)$trigMeta['badge_cls'] : 'bg-secondary bg-opacity-10 text-body border',
                    'reference_id'       => $refId,
                    'reference_label'    => $refLabel,
                    'reference_link'     => $refLink,
                    'recipient'          => (string)($row->recipient ?? ''),
                    'intended_recipient' => $intendedRecip,
                    'subject'            => (string)($row->subject ?? ''),
                    'has_body'           => (int)($row->has_body ?? 0) === 1,
                    'sandbox_mode'       => (int)($row->sandbox_mode ?? 0) === 1 || in_array((string)($row->status ?? ''), ['sandbox', 'sandbox_sent'], true),
                    'status'             => (string)($row->status ?? ''),
                    'error_message'      => (string)($row->error_message ?? ''),
                    'created_at_de'      => ($ts !== false && $ts > 0) ? date('d.m.Y H:i:s', $ts) : (string)($row->created_at ?? '-'),
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

        $ownAdminEmail = $this->getOwnAdminEmail($smtpConfig);

        // Initiale Template-Vorschau für Tab 1 rendern
        $initialPreview = ['subject' => '', 'html' => '', 'text' => ''];
        if (class_exists('EroCloudMailer')) {
            $initialPreview = \EroCloudMailer::renderTemplate('movie_converted', [
                'merchant_id'      => 10061,
                'creator_name'     => 'Lara',
                'intended_email'   => $ownAdminEmail,
                'actual_recipient' => $ownAdminEmail,
                'movie_id'         => 48291,
                'upload_datetime'  => date('d.m.Y \u\m H:i \U\h\r'),
                'playtime'         => '18:45',
                'resolution'       => '1920x1080 (Full-HD)',
                'movie_edit_url'   => 'https://erocloud.net/mcp_neu/web/index.php#/movie?id=48291',
                'unsubscribe_url'  => \EroCloudMailer::getUnsubscribeUrl(10061, 'movie_converted'),
            ]);
        }

        return View::render('SystemTech', 'settings', [
            'imprint'        => $imprint,
            'defaults'       => $defaults,
            'smtpConfig'     => $smtpConfig,
            'ownAdminEmail'  => $ownAdminEmail,
            'eventCatalog'   => $eventCatalog,
            'triggerCatalog' => $triggerCatalog,
            'optInStats'     => $optInStats,
            'mailStats'      => $mailStats,
            'deliveryLogs'   => $deliveryLogs,
            'envInfo'        => $envInfo,
            'initialPreview' => $initialPreview,
        ]);
    }

    // =========================================================================
    // 1b. Postfach-, SMTP- & Sandbox-Konfiguration speichern (POST ?route=system-settings/save-smtp)
    // =========================================================================
    public function saveSmtp(): void
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

        $smtpHost        = trim((string)$this->request->post('smtp_host', ''));
        $smtpPort        = abs((int)$this->request->post('smtp_port', 587));
        $smtpSecure      = strtolower(trim((string)$this->request->post('smtp_secure', 'tls')));
        $smtpUser        = trim((string)$this->request->post('smtp_user', ''));
        $smtpPass        = trim((string)$this->request->post('smtp_pass', ''));
        $fromAddress     = trim((string)$this->request->post('from_address', ''));
        $fromName        = trim((string)$this->request->post('from_name', ''));
        $sandboxMode     = (int)$this->request->post('sandbox_mode', 0) === 1;
        $sandboxRecip    = trim((string)$this->request->post('sandbox_recipient', ''));

        if ($smtpHost === '' || $smtpUser === '' || $fromAddress === '') {
            Response::error('Bitte fülle mindestens SMTP-Server, SMTP-Benutzer und Absender-E-Mail aus.', 400);
            return;
        }
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            Response::error('Die eingegebene Absender-E-Mail-Adresse (From) ist ungültig.', 400);
            return;
        }
        if ($sandboxMode && ($sandboxRecip === '' || !filter_var($sandboxRecip, FILTER_VALIDATE_EMAIL))) {
            Response::error('Bei aktiviertem Sandbox-Modus muss eine gültige Sandbox-Ziel-E-Mail-Adresse angegeben werden.', 400);
            return;
        }

        $ok = \EroCloudMailer::saveSmtpConfig([
            'smtp_host'         => $smtpHost,
            'smtp_port'         => $smtpPort > 0 ? $smtpPort : 587,
            'smtp_secure'       => $smtpSecure,
            'smtp_user'         => $smtpUser,
            'smtp_pass'         => $smtpPass,
            'from_address'      => $fromAddress,
            'from_name'         => $fromName !== '' ? $fromName : 'EroCloud Partner-Team',
            'sandbox_mode'      => $sandboxMode ? 1 : 0,
            'sandbox_recipient' => $sandboxRecip !== '' ? $sandboxRecip : 'erocms@gmail.com',
        ]);

        if (!$ok) {
            Response::error('Fehler beim Speichern der SMTP- und Postfach-Einstellungen.', 500);
            return;
        }

        if (function_exists('log_action')) {
            log_action('SMTP- & Postfach-Konfiguration aktualisiert (Host: ' . $smtpHost . ':' . $smtpPort . ', Sandbox: ' . ($sandboxMode ? 'AN -> ' . $sandboxRecip : 'AUS / LIVE') . ').');
        }

        $updated = \EroCloudMailer::getSmtpConfig(true);
        unset($updated['smtp_pass']);

        Response::json([
            'status'     => 'ok',
            'message'    => 'Die Postfach- und SMTP-Einstellungen wurden erfolgreich gespeichert.',
            'smtpConfig' => $updated,
        ]);
    }

    // =========================================================================
    // 1c. Gesendete E-Mail im Detail abrufen (GET/POST ?route=system-settings/log-detail)
    // =========================================================================
    public function logDetail(): void
    {
        $this->ensureMailClasses();

        $logId = abs((int)($this->request->get('id', $this->request->post('id', 0))));
        if ($logId <= 0) {
            Response::error('Ungültige Protokoll-ID.', 400);
            return;
        }

        $aesKey = p4c_escape_string((string)AES_KEY);
        $rs = p4c_query(
            "SELECT
                l.`id`, l.`merchant_id`, l.`event_key`, l.`trigger_detail`, l.`reference_id`,
                l.`recipient`, l.`intended_recipient`, l.`subject`, l.`body_html`,
                l.`sandbox_mode`, l.`status`, l.`error_message`, l.`created_at`,
                m.`partner_id`,
                CAST(AES_DECRYPT(m.`username`, '{$aesKey}') AS CHAR) AS `dec_username`
             FROM `notification_log` l
             LEFT JOIN `merchants` m ON m.`id` = l.`merchant_id`
             WHERE l.`id` = '{$logId}'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rs || p4c_num_rows($rs) === 0) {
            Response::error('Protokoll-Eintrag #' . $logId . ' wurde nicht gefunden.', 404);
            return;
        }

        $row = p4c_fetch_object($rs);
        $ts  = strtotime((string)($row->created_at ?? ''));

        Response::json([
            'status' => 'ok',
            'log'    => [
                'id'                 => abs((int)$row->id),
                'merchant_id'        => abs((int)$row->merchant_id),
                'partner_id'         => trim((string)($row->partner_id ?? '')),
                'merchant_username'  => trim((string)($row->dec_username ?? '')),
                'event_key'          => (string)($row->event_key ?? ''),
                'trigger_detail'     => (string)($row->trigger_detail ?? ''),
                'reference_id'       => abs((int)$row->reference_id),
                'recipient'          => (string)($row->recipient ?? ''),
                'intended_recipient' => (string)($row->intended_recipient ?? ''),
                'subject'            => (string)($row->subject ?? ''),
                'body_html'          => (string)($row->body_html ?? ''),
                'status'             => (string)($row->status ?? ''),
                'error_message'      => (string)($row->error_message ?? ''),
                'created_at_de'      => ($ts !== false && $ts > 0) ? date('d.m.Y H:i:s', $ts) : (string)($row->created_at ?? '-'),
            ],
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

        $rawSmtp       = \EroCloudMailer::getSmtpConfig();
        $ownAdminEmail = $this->getOwnAdminEmail($rawSmtp);

        $mode        = trim((string)$this->request->post('mode', 'send')); // 'send', 'preview' oder 'save_default_recipient'
        $templateKey = trim((string)$this->request->post('template_key', 'movie_converted')) ?: 'movie_converted';
        $creatorName = trim((string)$this->request->post('creator_name', 'Lara')) ?: 'Lara';
        $rawToEmail  = trim((string)$this->request->post('test_recipient', $this->request->post('to_email', '')));
        $toEmail     = ($rawToEmail !== '' && $rawToEmail !== 'creator-beispiel@domain.tld') ? $rawToEmail : $ownAdminEmail;
        $movieId     = max(1, abs((int)$this->request->post('movie_id', 48291)));
        $playtime    = trim((string)$this->request->post('playtime', '18:45')) ?: '18:45';
        $resolution  = trim((string)$this->request->post('resolution', '1920x1080 (Full-HD)')) ?: '1920x1080 (Full-HD)';
        $merchantId  = max(1, abs((int)$this->request->post('merchant_id', 10061)));

        if ($mode === 'save_default_recipient') {
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                Response::error('Bitte gib eine gültige E-Mail-Adresse ein.', 400);
                return;
            }
            $emailEsc = p4c_escape_string($toEmail);
            p4c_query(
                "INSERT INTO `settings` (`variable`, `value`) VALUES ('mail_test_recipient', '{$emailEsc}')
                 ON DUPLICATE KEY UPDATE `value` = '{$emailEsc}';",
                __FILE__,
                __LINE__
            );
            if (function_exists('log_action')) {
                log_action('Standard-Test-E-Mail-Adresse auf ' . $toEmail . ' aktualisiert.');
            }
            Response::json([
                'status'         => 'ok',
                'own_admin_mail' => $toEmail,
                'message'        => 'Deine Standard-Test-E-Mail-Adresse wurde auf ' . $toEmail . ' gespeichert.',
            ]);
            return;
        }

        if ($mode === 'send' && !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('Bitte gib eine gültige Empfänger-E-Mail-Adresse für den Testversand ein.', 400);
            return;
        }

        if ((int)$this->request->post('save_default_recipient', 0) === 1 && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $emailEsc = p4c_escape_string($toEmail);
            p4c_query(
                "INSERT INTO `settings` (`variable`, `value`) VALUES ('mail_test_recipient', '{$emailEsc}')
                 ON DUPLICATE KEY UPDATE `value` = '{$emailEsc}';",
                __FILE__,
                __LINE__
            );
            $ownAdminEmail = $toEmail;
        }

        $triggerCatalog = class_exists('EroCloudNotificationCenter') ? \EroCloudNotificationCenter::getTriggerCatalog() : [];
        $triggerTitle   = $templateKey;
        $triggerBadgeCls = 'badge-kkc badge-kkc-info';
        foreach ($triggerCatalog as $tItem) {
            if (($tItem['trigger_key'] ?? '') === $templateKey || ($tItem['preview_variant'] ?? '') === $templateKey) {
                $triggerTitle    = (string)($tItem['title'] ?? $templateKey);
                $triggerBadgeCls = (string)($tItem['badge_cls'] ?? $triggerBadgeCls);
                break;
            }
        }

        // Je nach gewählter Vorlage (alle 10 strategischen Auslöser) Vorschau rendern & ggf. absenden
        if (in_array($templateKey, ['movie_approved', 'movie_rejected', 'album_approved', 'album_rejected', 'actor_approved', 'actor_rejected'], true)) {
            $isApproved  = str_ends_with($templateKey, '_approved');
            $contentType = str_starts_with($templateKey, 'album_')
                ? 'photo_album'
                : (str_starts_with($templateKey, 'actor_') ? 'actor' : 'movie');

            $targetRoute = $contentType === 'photo_album'
                ? '#/photo-album?id=' . $movieId
                : ($contentType === 'actor' ? '#/actor?id=' . $movieId : '#/movie?id=' . $movieId);

            $extraLabel = $contentType === 'photo_album'
                ? 'Anzahl Fotos'
                : ($contentType === 'actor' ? 'Profil-Status' : 'Laufzeit & Qualität');
            $extraVal = $contentType === 'photo_album'
                ? '24 Bilder'
                : ($contentType === 'actor' ? ($isApproved ? 'Aktiv & verifiziert' : 'Gesperrt / Überarbeitung') : ($playtime . ' Min. • ' . $resolution));

            $reasonTitle = $isApproved ? '' : 'Vorschaubild / Angaben unvollständig';
            $reasonText  = $isApproved ? '' : 'Bitte wähle für die FSK-16-Vorschau ein zensiertes Cover im Cover-Studio aus oder ergänze die fehlende Drehpartner-Zuweisung.';

            $rendered = \EroCloudMailer::renderTemplate('content_reviewed', [
                'merchant_id'      => $merchantId,
                'creator_name'     => $creatorName,
                'intended_email'   => $toEmail,
                'actual_recipient' => $toEmail,
                'content_type'     => $contentType,
                'content_id'       => $movieId,
                'review_status'    => $isApproved ? 'approved' : 'rejected',
                'review_datetime'  => date('d.m.Y \u\m H:i \U\h\r'),
                'reason_title'     => $reasonTitle,
                'reason_text'      => $reasonText,
                'extra_meta_label' => $extraLabel,
                'extra_meta_value' => $extraVal,
                'action_url'       => 'https://erocloud.net/mcp_neu/web/' . $targetRoute,
                'unsubscribe_url'  => \EroCloudMailer::getUnsubscribeUrl($merchantId, 'content_reviewed'),
            ]);

            if ($mode === 'preview') {
                Response::json([
                    'status'        => 'ok',
                    'template_key'  => $templateKey,
                    'trigger_title' => $triggerTitle,
                    'subject'       => $rendered['subject'],
                    'html'          => $rendered['html'],
                    'text'          => $rendered['text'],
                ]);
                return;
            }

            $res = \EroCloudMailer::sendContentReviewNotification([
                'merchant_id'        => $merchantId,
                'recipient_email'    => $toEmail,
                'override_recipient' => $toEmail,
                'creator_name'       => $creatorName,
                'content_type'       => $contentType,
                'content_id'         => $movieId,
                'review_status'      => $isApproved ? 'approved' : 'rejected',
                'reason_title'       => $reasonTitle,
                'reason_text'        => $reasonText,
                'extra_meta_label'   => $extraLabel,
                'extra_meta_value'   => $extraVal,
                'force_send'         => true,
            ]);
        } elseif (in_array($templateKey, ['co_actor_submitted', 'co_actor_verified', 'co_actor_rejected'], true)) {
            $statusType = str_replace('co_actor_', '', $templateKey);
            $reasonText = $statusType === 'rejected'
                ? 'Das Ausweisfoto war leider unscharf bzw. gespiegelt. Bitte erstelle über den QR-Code kurz eine neue, gut lesbare Aufnahme.'
                : '';

            $rendered = \EroCloudMailer::renderTemplate('co_actor_status', [
                'merchant_id'      => $merchantId,
                'creator_name'     => $creatorName,
                'intended_email'   => $toEmail,
                'actual_recipient' => $toEmail,
                'co_actor_id'      => $movieId,
                'ref_code'         => sprintf('DP-%d-%03d', $merchantId, $movieId % 1000),
                'role_label'       => 'Co-Darsteller / Drehpartner',
                'alias_label'      => 'Alex M. (@alex_studio)',
                'status_type'      => $statusType,
                'event_datetime'   => date('d.m.Y \u\m H:i \U\h\r'),
                'reason_text'      => $reasonText,
                'action_url'       => 'https://erocloud.net/mcp_neu/web/#/co-actors',
                'unsubscribe_url'  => \EroCloudMailer::getUnsubscribeUrl($merchantId, 'co_actor_status'),
            ]);

            if ($mode === 'preview') {
                Response::json([
                    'status'        => 'ok',
                    'template_key'  => $templateKey,
                    'trigger_title' => $triggerTitle,
                    'subject'       => $rendered['subject'],
                    'html'          => $rendered['html'],
                    'text'          => $rendered['text'],
                ]);
                return;
            }

            $res = \EroCloudMailer::sendCoActorStatusNotification([
                'merchant_id'        => $merchantId,
                'recipient_email'    => $toEmail,
                'override_recipient' => $toEmail,
                'creator_name'       => $creatorName,
                'co_actor_id'        => $movieId,
                'ref_code'           => sprintf('DP-%d-%03d', $merchantId, $movieId % 1000),
                'role_label'         => 'Co-Darsteller / Drehpartner',
                'alias_label'        => 'Alex M. (@alex_studio)',
                'status_type'        => $statusType,
                'reason_text'        => $reasonText,
                'force_send'         => true,
            ]);
        } else {
            $rendered = \EroCloudMailer::renderTemplate('movie_converted', [
                'merchant_id'      => $merchantId,
                'creator_name'     => $creatorName,
                'intended_email'   => $toEmail,
                'actual_recipient' => $toEmail,
                'movie_id'         => $movieId,
                'upload_datetime'  => date('d.m.Y \u\m H:i \U\h\r'),
                'playtime'         => $playtime,
                'resolution'       => $resolution,
                'movie_edit_url'   => 'https://erocloud.net/mcp_neu/web/index.php#/movie?id=' . $movieId,
                'unsubscribe_url'  => \EroCloudMailer::getUnsubscribeUrl($merchantId, 'movie_converted'),
            ]);

            if ($mode === 'preview') {
                Response::json([
                    'status'        => 'ok',
                    'template_key'  => $templateKey,
                    'trigger_title' => $triggerTitle,
                    'subject'       => $rendered['subject'],
                    'html'          => $rendered['html'],
                    'text'          => $rendered['text'],
                ]);
                return;
            }

            $res = \EroCloudMailer::sendMovieConvertedNotification([
                'merchant_id'        => $merchantId,
                'recipient_email'    => $toEmail,
                'override_recipient' => $toEmail,
                'creator_name'       => $creatorName,
                'movie_id'           => $movieId,
                'playtime'           => $playtime,
                'resolution'         => $resolution,
                'force_send'         => true,
            ]);
        }

        if (empty($res['success'])) {
            Response::error('Test-E-Mail-Versand fehlgeschlagen: ' . ($res['error'] ?? 'Unbekannter SMTP-Fehler'), 500);
            return;
        }

        $effectiveRecipient = (string)($res['recipient'] ?? $toEmail);
        $messageId          = (string)($res['message_id'] ?? '');

        if (function_exists('log_action')) {
            log_action('Test-E-Mail (' . $templateKey . ') an ' . $effectiveRecipient . ' gesendet.');
        }

        // Neuesten Log-Eintrag & aktualisierte Zähler für Live-UI-Update abrufen
        $latestLog = null;
        $rsLatest = p4c_query(
            "SELECT `id`, `merchant_id`, `event_key`, `trigger_detail`, `reference_id`, `recipient`, `intended_recipient`, `subject`, `status`, `created_at`
             FROM `notification_log`
             ORDER BY `id` DESC
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsLatest && ($lRow = p4c_fetch_object($rsLatest))) {
            $tsL = strtotime((string)($lRow->created_at ?? ''));
            $latestLog = [
                'id'                => abs((int)$lRow->id),
                'merchant_id'       => abs((int)$lRow->merchant_id),
                'event_key'         => (string)($lRow->event_key ?? ''),
                'trigger_label'     => $triggerTitle,
                'trigger_badge_cls' => $triggerBadgeCls,
                'recipient'         => (string)($lRow->recipient ?? $effectiveRecipient),
                'subject'           => (string)($lRow->subject ?? $rendered['subject']),
                'status'            => (string)($lRow->status ?? 'sent'),
                'created_at_de'     => ($tsL !== false && $tsL > 0) ? date('d.m.Y H:i:s', $tsL) : date('d.m.Y H:i:s'),
            ];
        }

        Response::json([
            'status'              => 'ok',
            'message'             => 'Test-E-Mail „' . $triggerTitle . '“ erfolgreich an ' . $effectiveRecipient . ' gesendet!',
            'effective_recipient' => $effectiveRecipient,
            'own_admin_mail'      => $ownAdminEmail,
            'template_key'        => $templateKey,
            'trigger_title'       => $triggerTitle,
            'message_id'          => $messageId,
            'subject'             => $rendered['subject'],
            'html'                => $rendered['html'],
            'text'                => $rendered['text'],
            'latest_log'          => $latestLog,
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
