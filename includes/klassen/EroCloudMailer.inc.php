<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

require_once SOURCEDIR . '/includes/klassen/phpmailer/src/Exception.php';
require_once SOURCEDIR . '/includes/klassen/phpmailer/src/PHPMailer.php';
require_once SOURCEDIR . '/includes/klassen/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * EroCloudMailer - Globaler, zentraler E-Mail-Service für EroCloud (ACP, MCP, API & CLI-Cronjobs).
 * Nutzt PHPMailer v7.1+ über SMTP (mail.erocloud.net) mit vollem Anti-Spam-Header-Set (SPF/DKIM/DMARC-konform),
 * Multipart-MIME (HTML + Plaintext AltBody), RFC 8058 List-Unsubscribe, zentralem Datenbank-Impressum
 * und integriertem Sandbox-Schutz.
 */
class EroCloudMailer
{
    /** @var string */
    public $lastError = '';

    /** @var string */
    public $lastMessageId = '';

    /** @var string */
    public $effectiveRecipient = '';

    /** @var array|null */
    private static $cachedImprint = null;

    /**
     * Liefert die Standard-Impressums- und Supportdaten (analog https://pay4coins.com/de/impressum).
     *
     * @return array
     */
    public static function getDefaultImprintData()
    {
        return [
            'imprint_company_name'     => 'CIPA Media S.L.U.',
            'imprint_address_street'   => 'Av. Emilio Luque Moreno 19 Oficina 6',
            'imprint_address_city'     => 'ES-38300 La Orotava (S/C de Tenerife)',
            'imprint_register_court'   => 'Registro Mercantil de Santa Cruz de Tenerife',
            'imprint_register_details' => 'Tomo: 3368, Folio: 43, Sección: 8, Hoja: TF-54807, Inscripción: 8',
            'imprint_vat_id'           => 'CIF: B76659168',
            'imprint_ceo'              => 'Christina Toepel',
            'imprint_phone'            => '+34 616 571 860',
            'imprint_whatsapp_url'     => 'https://wa.me/34616571860',
            'imprint_support_email'    => 'support@pay4coins.com',
            'imprint_website_url'      => 'https://pay4coins.com',
            'imprint_external_url'     => 'https://pay4coins.com/de/impressum',
        ];
    }

    /**
     * Lädt die zentralen Impressums- und Supportdaten aus der Datenbank-Tabelle `settings`
     * (mit automatischem Fallback auf die Pay4Coins-Standardwerte).
     *
     * @param bool $forceReload
     * @return array
     */
    public static function getImprintData($forceReload = false)
    {
        if (self::$cachedImprint !== null && !$forceReload) {
            return self::$cachedImprint;
        }

        $data = self::getDefaultImprintData();

        global $mysql;
        if (function_exists('p4c_query') && isset($mysql) && is_object($mysql)) {
            $keys = array_keys($data);
            $escapedKeys = [];
            foreach ($keys as $k) {
                $escapedKeys[] = "'" . p4c_escape_string($k) . "'";
            }
            $sql = "SELECT `variable`, `value` FROM `settings` WHERE `variable` IN (" . implode(',', $escapedKeys) . ");";
            $rs  = @p4c_query($sql, __FILE__, __LINE__);
            if ($rs && p4c_num_rows($rs) > 0) {
                while ($row = p4c_fetch_object($rs)) {
                    $varName = (string)($row->variable ?? '');
                    $varVal  = trim((string)($row->value ?? ''));
                    if ($varName !== '' && array_key_exists($varName, $data) && $varVal !== '') {
                        $data[$varName] = $varVal;
                    }
                }
            }
        }

        self::$cachedImprint = $data;
        return $data;
    }

    /**
     * Speichert die zentralen Impressums- und Supportdaten in der Datenbank-Tabelle `settings`.
     *
     * @param array $input
     * @return bool
     */
    public static function saveImprintData(array $input)
    {
        if (!function_exists('p4c_query')) {
            return false;
        }

        $defaults = self::getDefaultImprintData();
        foreach ($defaults as $key => $defaultVal) {
            $val = isset($input[$key]) ? trim((string)$input[$key]) : $defaultVal;
            if ($val === '') {
                $val = $defaultVal;
            }
            $sql = "INSERT INTO `settings` (`variable`, `value`)
                    VALUES ('" . p4c_escape_string($key) . "', '" . p4c_escape_string($val) . "')
                    ON DUPLICATE KEY UPDATE `value` = '" . p4c_escape_string($val) . "';";
            p4c_query($sql, __FILE__, __LINE__);
        }

        self::$cachedImprint = null;
        return true;
    }

    /**
     * Erzeugt einen kryptografisch signierten HMAC-SHA256 Token für den 1-Klick-Abmeldelink ohne Login-Zwang.
     *
     * @param int    $merchantId
     * @param string $notificationType z.B. 'movie_converted'
     * @return string
     */
    public static function generateUnsubscribeToken($merchantId, $notificationType = 'movie_converted')
    {
        $mid  = abs((int)$merchantId);
        $type = trim((string)$notificationType);
        $key  = defined('AES_KEY') ? (string)AES_KEY : 'erocloud_fallback_key';
        $data = $mid . '|' . $type;
        $sig  = hash_hmac('sha256', $data, $key);

        return rtrim(strtr(base64_encode($data . '|' . $sig), '+/', '-_'), '=');
    }

    /**
     * Prüft einen 1-Klick-Abmelde-Token und gibt bei Gültigkeit ['merchant_id' => int, 'type' => string] zurück.
     *
     * @param string $token
     * @return array|null
     */
    public static function verifyUnsubscribeToken($token)
    {
        $rawToken = trim((string)$token);
        if ($rawToken === '') {
            return null;
        }

        $b64 = strtr($rawToken, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = @base64_decode($b64, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        $mid  = abs((int)$parts[0]);
        $type = trim((string)$parts[1]);
        $sig  = trim((string)$parts[2]);

        if ($mid <= 0 || $type === '' || $sig === '') {
            return null;
        }

        $key      = defined('AES_KEY') ? (string)AES_KEY : 'erocloud_fallback_key';
        $expected = hash_hmac('sha256', $mid . '|' . $type, $key);

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        return [
            'merchant_id' => $mid,
            'type'        => $type,
        ];
    }

    /**
     * Stellt sicher, dass die zentralen Benachrichtigungs-Tabellen (`EroCloudNotificationCenter`)
     * sowie die Spalten in `merchants` und `movies` existieren.
     * Wird idempotent beim ersten Aufruf pro Request geprüft.
     */
    public static function ensureNotificationColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (!class_exists('EroCloudNotificationCenter') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/EroCloudNotificationCenter.inc.php')) {
            include_once SOURCEDIR . '/includes/klassen/EroCloudNotificationCenter.inc.php';
        }
        if (class_exists('EroCloudNotificationCenter')) {
            EroCloudNotificationCenter::ensureTablesExist();
        }

        $rsM = p4c_query("SHOW COLUMNS FROM `merchants` LIKE 'notify_movie_converted';", __FILE__, __LINE__);
        if ($rsM && p4c_num_rows($rsM) === 0) {
            p4c_query("SET SESSION sql_mode = '';", __FILE__, __LINE__);
            p4c_query("ALTER TABLE `merchants` ADD COLUMN `notify_movie_converted` TINYINT(1) NOT NULL DEFAULT 1;", __FILE__, __LINE__);
        }

        $rsMov1 = p4c_query("SHOW COLUMNS FROM `movies` LIKE 'notify_on_convert';", __FILE__, __LINE__);
        if ($rsMov1 && p4c_num_rows($rsMov1) === 0) {
            p4c_query("SET SESSION sql_mode = '';", __FILE__, __LINE__);
            p4c_query("ALTER TABLE `movies` ADD COLUMN `notify_on_convert` TINYINT(1) NOT NULL DEFAULT 1;", __FILE__, __LINE__);
        }

        $rsMov2 = p4c_query("SHOW COLUMNS FROM `movies` LIKE 'notify_sent_at';", __FILE__, __LINE__);
        if ($rsMov2 && p4c_num_rows($rsMov2) === 0) {
            p4c_query("SET SESSION sql_mode = '';", __FILE__, __LINE__);
            p4c_query("ALTER TABLE `movies` ADD COLUMN `notify_sent_at` DATETIME NULL DEFAULT NULL;", __FILE__, __LINE__);
        }
    }

    /**
     * Liest die gespeicherte Standard-Präferenz des Creators für Konvertierungs-E-Mails (1 = aktiv, 0 = aus)
     * über das zentrale `EroCloudNotificationCenter`.
     */
    public static function getMerchantNotifyPreference(int $merchantId): int
    {
        $merchantId = abs((int)$merchantId);
        if ($merchantId <= 0) {
            return 1;
        }

        self::ensureNotificationColumns();

        if (class_exists('EroCloudNotificationCenter')) {
            return EroCloudNotificationCenter::isEnabled(
                $merchantId,
                EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED,
                EroCloudNotificationCenter::CHANNEL_EMAIL
            ) ? 1 : 0;
        }

        $rs = p4c_query("SELECT `notify_movie_converted` FROM `merchants` WHERE `id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
        if ($rs && p4c_num_rows($rs) > 0) {
            $row = p4c_fetch_object($rs);
            return isset($row->notify_movie_converted) ? (abs((int)$row->notify_movie_converted) === 1 ? 1 : 0) : 1;
        }

        return 1;
    }

    /**
     * Speichert die Präferenz des Creators für Konvertierungs-E-Mails (1 = aktiv, 0 = deaktiviert)
     * im zentralen `EroCloudNotificationCenter` (und synchron in `merchants`).
     */
    public static function setMerchantNotifyPreference(int $merchantId, int $enabled, string $updatedVia = 'upload_checkbox'): void
    {
        $merchantId = abs((int)$merchantId);
        $flag       = ($enabled === 1) ? 1 : 0;
        if ($merchantId <= 0) {
            return;
        }

        self::ensureNotificationColumns();

        if (class_exists('EroCloudNotificationCenter')) {
            EroCloudNotificationCenter::setPreference(
                $merchantId,
                EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED,
                EroCloudNotificationCenter::CHANNEL_EMAIL,
                $flag === 1,
                $updatedVia
            );
            return;
        }

        p4c_query("UPDATE `merchants` SET `notify_movie_converted` = '" . $flag . "' WHERE `id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
    }

    /**
     * Verarbeitet den 1-Klick-Abmeldelink (`route=email-unsubscribe&token=...`) OHNE Login-Zwang.
     * Unterstützt:
     * - RFC 8058 One-Click POST Requests (Gmail/Apple Mail)
     * - Browser-Aufrufe (GET) zum Abbestellen des geklickten Themas
     * - Sofort-Wiederaktivierung (`&action=resubscribe`)
     * - Komplett-Abmeldung aller E-Mails (`&action=unsubscribe_all`)
     * - Granulares Speichern einzelner Themen (`POST action=save_preferences`)
     */
    public static function handleUnsubscribeRequest(string $token, string $action = 'unsubscribe', bool $isOneClickPost = false): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        self::ensureNotificationColumns();

        $verified = self::verifyUnsubscribeToken($token);
        if (!is_array($verified) || empty($verified['merchant_id'])) {
            if ($isOneClickPost) {
                http_response_code(400);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Invalid token';
                exit;
            }
            self::renderUnsubscribePage(false, 0, $token, 'invalid', '');
            exit;
        }

        $merchantId = abs((int)$verified['merchant_id']);
        $eventKey   = trim((string)($verified['type'] ?? EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED));
        $catalog    = class_exists('EroCloudNotificationCenter') ? EroCloudNotificationCenter::getEventCatalog() : [];
        if (!isset($catalog[$eventKey])) {
            $eventKey = EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED;
        }

        $act = strtolower(trim($action));

        if ($isOneClickPost) {
            if (class_exists('EroCloudNotificationCenter')) {
                EroCloudNotificationCenter::setPreference($merchantId, $eventKey, EroCloudNotificationCenter::CHANNEL_EMAIL, false, 'rfc8058_one_click');
            } else {
                self::setMerchantNotifyPreference($merchantId, 0, 'rfc8058_one_click');
            }
            if ($eventKey === EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED) {
                p4c_query("UPDATE `movies` SET `notify_on_convert` = '0'
                    WHERE `merchant_id` = '" . $merchantId . "'
                      AND `convert_status` IN ('0', '1')
                      AND `notify_sent_at` IS NULL;", __FILE__, __LINE__);
            }
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'OK';
            exit;
        }

        $statusMode = 'unsubscribed_single';

        if ($act === 'save_preferences' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $enabledEvents = isset($_POST['email_events']) && is_array($_POST['email_events']) ? $_POST['email_events'] : [];
            foreach (array_keys($catalog) as $evKey) {
                $isOn = in_array($evKey, $enabledEvents, true);
                EroCloudNotificationCenter::setPreference($merchantId, $evKey, EroCloudNotificationCenter::CHANNEL_EMAIL, $isOn, 'unsubscribe_page_matrix');
                if ($evKey === EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED) {
                    $flag = $isOn ? 1 : 0;
                    p4c_query("UPDATE `movies` SET `notify_on_convert` = '" . $flag . "'
                        WHERE `merchant_id` = '" . $merchantId . "'
                          AND `convert_status` IN ('0', '1')
                          AND `notify_sent_at` IS NULL;", __FILE__, __LINE__);
                }
            }
            $statusMode = 'saved_matrix';
        } elseif ($act === 'unsubscribe_all') {
            if (class_exists('EroCloudNotificationCenter')) {
                EroCloudNotificationCenter::unsubscribeAllEmail($merchantId, 'one_click_unsubscribe_all');
            }
            p4c_query("UPDATE `movies` SET `notify_on_convert` = '0'
                WHERE `merchant_id` = '" . $merchantId . "'
                  AND `convert_status` IN ('0', '1')
                  AND `notify_sent_at` IS NULL;", __FILE__, __LINE__);
            $statusMode = 'unsubscribed_all';
        } elseif ($act === 'resubscribe') {
            if (class_exists('EroCloudNotificationCenter')) {
                EroCloudNotificationCenter::setPreference($merchantId, $eventKey, EroCloudNotificationCenter::CHANNEL_EMAIL, true, 'one_click_resubscribe');
            }
            if ($eventKey === EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED) {
                p4c_query("UPDATE `movies` SET `notify_on_convert` = '1'
                    WHERE `merchant_id` = '" . $merchantId . "'
                      AND `convert_status` IN ('0', '1')
                      AND `notify_sent_at` IS NULL;", __FILE__, __LINE__);
            }
            $statusMode = 'resubscribed_single';
        } else {
            if (class_exists('EroCloudNotificationCenter')) {
                EroCloudNotificationCenter::setPreference($merchantId, $eventKey, EroCloudNotificationCenter::CHANNEL_EMAIL, false, 'one_click_unsubscribe');
            } else {
                self::setMerchantNotifyPreference($merchantId, 0, 'one_click_unsubscribe');
            }
            if ($eventKey === EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED) {
                p4c_query("UPDATE `movies` SET `notify_on_convert` = '0'
                    WHERE `merchant_id` = '" . $merchantId . "'
                      AND `convert_status` IN ('0', '1')
                      AND `notify_sent_at` IS NULL;", __FILE__, __LINE__);
            }
            $statusMode = 'unsubscribed_single';
        }

        if (function_exists('log_action')) {
            $oldSessionMid = $_SESSION['merchant_id'] ?? null;
            $_SESSION['merchant_id'] = $merchantId;
            log_action("E-Mail-Präferenzen über Abmelde-Link geändert ({$statusMode}: {$eventKey}).");
            if ($oldSessionMid === null) {
                unset($_SESSION['merchant_id']);
            } else {
                $_SESSION['merchant_id'] = $oldSessionMid;
            }
        }

        self::renderUnsubscribePage(true, $merchantId, $token, $statusMode, $eventKey);
        exit;
    }

    /**
     * Rendert das öffentliche 1-Klick-E-Mail-Präferenz-Center (ohne Login-Zwang).
     */
    private static function renderUnsubscribePage(bool $isValid, int $merchantId, string $token, string $statusMode, string $eventKey): void
    {
        $imprint        = self::getImprintData();
        $impCompany     = htmlspecialchars((string)($imprint['imprint_company_name'] ?? 'CIPA Media S.L.U.'), ENT_QUOTES, 'UTF-8');
        $impStreet      = htmlspecialchars((string)($imprint['imprint_address_street'] ?? 'Av. Emilio Luque Moreno 19 Oficina 6'), ENT_QUOTES, 'UTF-8');
        $impCity        = htmlspecialchars((string)($imprint['imprint_address_city'] ?? 'ES-38300 La Orotava (S/C de Tenerife)'), ENT_QUOTES, 'UTF-8');
        $impSupportMail = htmlspecialchars((string)($imprint['imprint_support_email'] ?? 'support@pay4coins.com'), ENT_QUOTES, 'UTF-8');
        $impExternalUrl = htmlspecialchars((string)($imprint['imprint_external_url'] ?? 'https://pay4coins.com/de/impressum'), ENT_QUOTES, 'UTF-8');

        $mcpUrl = defined('MCP_URL') ? rtrim((string)MCP_URL, '/') : 'https://erocloud.net';
        if (defined('MCP_DIR') && is_dir(MCP_DIR . '/mcp_neu/web')) {
            $dashboardUrl = $mcpUrl . '/mcp_neu/web/index.php';
            $apiBaseUrl   = $mcpUrl . '/mcp_neu/web/api.php';
        } else {
            $dashboardUrl = $mcpUrl . '/index.php';
            $apiBaseUrl   = $mcpUrl . '/api.php';
        }

        $safeToken       = rawurlencode($token);
        $formActionUrl   = htmlspecialchars($apiBaseUrl . '?route=email-unsubscribe&token=' . $safeToken, ENT_QUOTES, 'UTF-8');
        $unsubAllHref    = htmlspecialchars($apiBaseUrl . '?route=email-unsubscribe&token=' . $safeToken . '&action=unsubscribe_all', ENT_QUOTES, 'UTF-8');
        $safeDashUrl     = htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8');

        $matrix     = ($isValid && class_exists('EroCloudNotificationCenter')) ? EroCloudNotificationCenter::getPreferencesMatrix($merchantId) : [];
        $eventTitle = isset($matrix[$eventKey]['title']) ? (string)$matrix[$eventKey]['title'] : 'Video fertig konvertiert & Vorschaubilder bereit';

        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deine E-Mail-Einstellungen – EroCloud</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background-color: #F1F5F9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1E293B; }
        .card { max-width: 600px; margin: 16px auto 24px auto; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06); }
        .card-header { background: linear-gradient(135deg, #0284C7 0%, #4297D7 100%); padding: 20px 28px; color: #FFFFFF; display: flex; justify-content: space-between; align-items: center; }
        .card-header .brand { font-size: 20px; font-weight: 700; }
        .card-header .sub { font-size: 12px; color: #E0F2FE; font-weight: 600; }
        .card-body { padding: 28px; }
        .status-banner { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; border-radius: 10px; margin-bottom: 24px; }
        .status-info { background-color: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; }
        .status-success { background-color: #F0FDF4; border: 1px solid #BBF7D0; color: #14532D; }
        .status-error { background-color: #FEF2F2; border: 1px solid #FECACA; color: #7F1D1D; }
        .status-icon { font-size: 22px; line-height: 1; flex-shrink: 0; margin-top: 2px; }
        .pref-list { border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; margin-bottom: 22px; }
        .pref-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 16px 18px; border-bottom: 1px solid #E2E8F0; background: #FFFFFF; }
        .pref-item:last-child { border-bottom: none; }
        .pref-badge { display: inline-block; font-size: 11px; font-weight: 700; color: #0284C7; background: #E0F2FE; padding: 2px 8px; border-radius: 999px; margin-bottom: 4px; }
        .pref-title { font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 4px 0; }
        .pref-desc { font-size: 12.5px; line-height: 18px; color: #64748B; margin: 0; }
        .switch-wrap { flex-shrink: 0; padding-top: 4px; }
        .switch-input { width: 20px; height: 20px; accent-color: #0284C7; cursor: pointer; }
        .btn-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; }
        .btn { display: inline-block; padding: 11px 20px; font-size: 14px; font-weight: 600; border-radius: 8px; text-decoration: none; border: none; cursor: pointer; transition: opacity 0.15s; }
        .btn:hover { opacity: 0.92; }
        .btn-primary { background-color: #0284C7; color: #FFFFFF; }
        .btn-outline { background-color: #FFFFFF; color: #475569; border: 1px solid #CBD5E1; }
        .btn-danger-outline { background-color: #FFFFFF; color: #DC2626; border: 1px solid #FCA5A5; }
        .footer { max-width: 600px; margin: 0 auto; text-align: center; font-size: 12px; line-height: 18px; color: #64748B; }
        .footer a { color: #0284C7; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <span class="brand">EroCloud</span>
            <span class="sub">Benachrichtigungs-Center</span>
        </div>
        <div class="card-body">';

        if (!$isValid) {
            echo '
            <div class="status-banner status-error">
                <div class="status-icon">&#10007;</div>
                <div>
                    <strong>Ungültiger oder beschädigter Link</strong><br>
                    <span style="font-size: 13.5px;">Dieser Link konnte leider nicht verifiziert werden. Bitte prüfe, ob die URL vollständig übernommen wurde.</span>
                </div>
            </div>
            <a href="' . $safeDashUrl . '" class="btn btn-primary">Zum EroCloud Partner-Bereich</a>';
        } else {
            if ($statusMode === 'saved_matrix') {
                echo '
                <div class="status-banner status-success">
                    <div class="status-icon">&#10003;</div>
                    <div>
                        <strong>Deine E-Mail-Einstellungen wurden gespeichert!</strong><br>
                        <span style="font-size: 13.5px;">Deine Auswahl wurde sofort für dein EroCloud Creator-Konto übernommen.</span>
                    </div>
                </div>';
            } elseif ($statusMode === 'unsubscribed_all') {
                echo '
                <div class="status-banner status-info">
                    <div class="status-icon">&#10003;</div>
                    <div>
                        <strong>Alle E-Mail-Benachrichtigungen deaktiviert</strong><br>
                        <span style="font-size: 13.5px;">Du erhältst ab sofort keine automatischen Benachrichtigungen oder Creator-News mehr per E-Mail. Unten kannst du einzelne Themen jederzeit wieder einschalten.</span>
                    </div>
                </div>';
            } elseif ($statusMode === 'resubscribed_single') {
                echo '
                <div class="status-banner status-success">
                    <div class="status-icon">&#10003;</div>
                    <div>
                        <strong>Benachrichtigung wieder aktiviert!</strong><br>
                        <span style="font-size: 13.5px;">Du erhältst für <strong>„' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '“</strong> ab sofort wieder E-Mails.</span>
                    </div>
                </div>';
            } else {
                echo '
                <div class="status-banner status-info">
                    <div class="status-icon">&#10003;</div>
                    <div>
                        <strong>Abmeldung erfolgreich gespeichert</strong><br>
                        <span style="font-size: 13.5px;">Du erhältst ab sofort keine E-Mails mehr zum Thema <strong>„' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '“</strong>.</span>
                    </div>
                </div>';
            }

            echo '
            <p style="font-size: 14px; color: #475569; margin: 0 0 14px 0;">
                Hier kannst du ohne Login genau festlegen, über welche Themen dich EroCloud per E-Mail informieren darf:
            </p>
            <form method="post" action="' . $formActionUrl . '">
                <input type="hidden" name="action" value="save_preferences">
                <div class="pref-list">';

            foreach ($matrix as $evKey => $meta) {
                $isChecked = !empty($meta['channels']['email']);
                $safeKey   = htmlspecialchars((string)$evKey, ENT_QUOTES, 'UTF-8');
                $safeBadge = htmlspecialchars((string)$meta['badge'], ENT_QUOTES, 'UTF-8');
                $safeTitle = htmlspecialchars((string)$meta['title'], ENT_QUOTES, 'UTF-8');
                $safeDesc  = htmlspecialchars((string)$meta['description'], ENT_QUOTES, 'UTF-8');

                echo '
                    <label class="pref-item" style="cursor: pointer;">
                        <div>
                            <span class="pref-badge">' . $safeBadge . '</span>
                            <div class="pref-title">' . $safeTitle . '</div>
                            <p class="pref-desc">' . $safeDesc . '</p>
                        </div>
                        <div class="switch-wrap">
                            <input type="checkbox" class="switch-input" name="email_events[]" value="' . $safeKey . '" ' . ($isChecked ? 'checked' : '') . '>
                        </div>
                    </label>';
            }

            echo '
                </div>
                <div class="btn-row">
                    <div>
                        <button type="submit" class="btn btn-primary">Auswahl speichern</button>
                        <a href="' . $safeDashUrl . '" class="btn btn-outline">Zum Partner-Bereich</a>
                    </div>
                    <a href="' . $unsubAllHref . '" class="btn btn-danger-outline" style="font-size: 12.5px; padding: 9px 14px;">Alle abbestellen</a>
                </div>
            </form>';
        }

        echo '
        </div>
    </div>
    <div class="footer">
        <strong>EroCloud</strong> &ndash; ein Projekt von Pay4Coins (' . $impCompany . ')<br>
        ' . $impStreet . ' &bull; ' . $impCity . '<br>
        <a href="' . $impExternalUrl . '" target="_blank">Impressum</a> &bull; Support: <a href="mailto:' . $impSupportMail . '">' . $impSupportMail . '</a>
    </div>
</body>
</html>';
    }

    /**
     * Erzeugt die vollständige 1-Klick-Abmelde-URL für eine Creator-Benachrichtigung.
     *
     * @param int    $merchantId
     * @param string $notificationType
     * @return string
     */
    public static function getUnsubscribeUrl($merchantId, $notificationType = 'movie_converted')
    {
        $token = self::generateUnsubscribeToken($merchantId, $notificationType);
        return 'https://erocloud.net/mcp_neu/web/api.php?route=email-unsubscribe&token=' . rawurlencode($token);
    }

    /**
     * Rendert ein E-Mail-Inhalts-Template innerhalb des globalen EroCloud HTML-Rahmen-Layouts.
     *
     * @param string $templateName Name der Datei unter includes/mail_templates/ (z.B. 'movie_converted')
     * @param array  $vars         Variablen für das Template
     * @return array               ['subject' => string, 'html' => string, 'text' => string]
     */
    public static function renderTemplate($templateName, array $vars = [])
    {
        $safeName     = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$templateName);
        $templateFile = SOURCEDIR . '/includes/mail_templates/' . $safeName . '.inc.php';
        $layoutFile   = SOURCEDIR . '/includes/mail_templates/layout.inc.php';

        $imprintData      = self::getImprintData();
        $vars['imprint']  = $imprintData;

        $emailSubject     = isset($vars['subject']) ? (string)$vars['subject'] : 'Deine Benachrichtigung von EroCloud';
        $emailPreheader   = isset($vars['preheader']) ? (string)$vars['preheader'] : '';
        $emailHtmlContent = '';
        $emailTextContent = '';

        if (file_exists($templateFile)) {
            include $templateFile;
        } else {
            $emailHtmlContent = isset($vars['body_html']) ? (string)$vars['body_html'] : '';
            $emailTextContent = isset($vars['body_text']) ? (string)$vars['body_text'] : '';
        }

        $layoutVars = array_merge($vars, [
            'subject'      => $emailSubject,
            'preheader'    => $emailPreheader,
            'content_html' => $emailHtmlContent,
            'content_text' => $emailTextContent,
            'imprint'      => $imprintData,
        ]);

        $finalHtml = $emailHtmlContent;
        $finalText = $emailTextContent;

        if (file_exists($layoutFile)) {
            include $layoutFile;
        }

        if (trim((string)$finalText) === '') {
            $finalText = self::htmlToPlainText($finalHtml);
        }

        return [
            'subject' => $emailSubject,
            'html'    => $finalHtml,
            'text'    => $finalText,
        ];
    }

    /**
     * Versendet eine E-Mail über den zentralen EroCloud SMTP-Server (inkl. Sandbox-Schutz & Error-Logging).
     *
     * @param string $toEmail     Ziel-E-Mail-Adresse (wird bei aktivem MAIL_SANDBOX_MODE auf erocms@gmail.com umgebogen)
     * @param string $toName      Name des Empfängers
     * @param string $subject     E-Mail-Betreff
     * @param string $htmlBody    HTML-Inhalt
     * @param string $textBody    Optionaler Plaintext-Inhalt (wird sonst automatisch aus $htmlBody erzeugt)
     * @param array  $options     Optionale Zusatzparameter: ['unsubscribe_url' => string, 'reply_to' => string, 'attachments' => array]
     * @return bool
     */
    public function send($toEmail, $toName, $subject, $htmlBody, $textBody = '', array $options = [])
    {
        $this->lastError          = '';
        $this->lastMessageId      = '';
        $this->effectiveRecipient = '';

        $intendedEmail = trim((string)$toEmail);
        $intendedName  = trim((string)$toName);
        $subjectStr    = trim((string)$subject);
        $htmlStr       = (string)$htmlBody;
        $textStr       = trim((string)$textBody);

        // 1. Zentraler Sandbox-/Test-Schutz:
        // Solange MAIL_SANDBOX_MODE === true ist, geht KEINE E-Mail an echte Creator raus!
        $isSandbox = (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true);
        $sandboxTo = defined('MAIL_SANDBOX_RECIPIENT') ? trim((string)MAIL_SANDBOX_RECIPIENT) : 'erocms@gmail.com';

        if ($isSandbox) {
            $actualToEmail = ($sandboxTo !== '') ? $sandboxTo : 'erocms@gmail.com';
            $actualToName  = ($intendedName !== '') ? $intendedName . ' (Test-Empfänger)' : 'EroCloud Test';
        } else {
            $actualToEmail = $intendedEmail;
            $actualToName  = $intendedName;
        }

        $this->effectiveRecipient = $actualToEmail;

        if ($actualToEmail === '' || !filter_var($actualToEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Ungültige Empfänger-E-Mail-Adresse: ' . $actualToEmail;
            $this->logMailError($this->lastError, __FILE__, __LINE__);
            return false;
        }

        if ($textStr === '') {
            $textStr = self::htmlToPlainText($htmlStr);
        }

        try {
            $mail = new PHPMailer(true);

            // Sprache für Fehlermeldungen auf Deutsch setzen
            $langDir = SOURCEDIR . '/includes/klassen/phpmailer/language/';
            if (is_dir($langDir)) {
                $mail->setLanguage('de', $langDir);
            }

            // SMTP-Grundeinstellungen
            $mail->isSMTP();
            $mail->Host       = defined('MAIL_SMTP_HOST') ? (string)MAIL_SMTP_HOST : 'mail.erocloud.net';
            $mail->Port       = defined('MAIL_SMTP_PORT') ? (int)MAIL_SMTP_PORT : 587;
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('MAIL_SMTP_USER') ? (string)MAIL_SMTP_USER : 'noreply@erocloud.net';
            $mail->Password   = defined('MAIL_SMTP_PASS') ? (string)MAIL_SMTP_PASS : '';
            $mail->Timeout    = 15;

            $secureMode = defined('MAIL_SMTP_SECURE') ? strtolower((string)MAIL_SMTP_SECURE) : 'tls';
            if ($secureMode === 'ssl' || (int)$mail->Port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Anti-Spam & Header-Hygiene:
            // Hostname explizit auf erocloud.net setzen, damit Message-ID auch im CLI-Cronjob
            // niemals @localhost lautet (verhindert SpamAssassin INVALID_MSGID).
            $mail->Hostname = 'erocloud.net';
            $mail->CharSet  = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->XMailer  = ' '; // Unterdrückt den Standard X-Mailer Header

            // Absender (noreply@erocloud.net)
            $fromAddress = defined('MAIL_FROM_ADDRESS') ? (string)MAIL_FROM_ADDRESS : 'noreply@erocloud.net';
            $fromName    = defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'EroCloud';
            $mail->setFrom($fromAddress, $fromName);

            // Best Practice für noreply-Systemmails:
            // Kein abweichender Reply-To-Header, außer er wurde explizit übergeben (verhindert Domain-Mismatch bei Spamfiltern).
            $replyToAddr = isset($options['reply_to']) && trim((string)$options['reply_to']) !== ''
                ? trim((string)$options['reply_to'])
                : (defined('MAIL_REPLY_TO_ADDRESS') ? trim((string)MAIL_REPLY_TO_ADDRESS) : '');
            $replyToName = isset($options['reply_to_name']) && trim((string)$options['reply_to_name']) !== ''
                ? trim((string)$options['reply_to_name'])
                : (defined('MAIL_REPLY_TO_NAME') ? trim((string)MAIL_REPLY_TO_NAME) : $fromName);

            if ($replyToAddr !== '' && filter_var($replyToAddr, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyToAddr, $replyToName);
            }

            // Empfänger hinzufügen
            if ($actualToName !== '') {
                $mail->addAddress($actualToEmail, $actualToName);
            } else {
                $mail->addAddress($actualToEmail);
            }

            // RFC 8058 One-Click List-Unsubscribe Header (wichtig für Gmail/Apple Mail Zustellbarkeit)
            if (!empty($options['unsubscribe_url'])) {
                $unsubUrl = trim((string)$options['unsubscribe_url']);
                if ($unsubUrl !== '') {
                    $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubUrl . '>');
                    $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            }

            // Optionale Dateianhänge
            if (!empty($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $att) {
                    if (is_array($att) && !empty($att[0]) && file_exists((string)$att[0])) {
                        $attName = isset($att[1]) ? (string)$att[1] : basename((string)$att[0]);
                        $mail->addAttachment((string)$att[0], $attName);
                    } elseif (is_string($att) && file_exists($att)) {
                        $mail->addAttachment($att);
                    }
                }
            }

            // Inhalt (HTML + Plaintext AltBody)
            $mail->isHTML(true);
            $mail->Subject = $subjectStr;
            $mail->Body    = $htmlStr;
            $mail->AltBody = $textStr;

            $mail->send();
            $this->lastMessageId = (string)$mail->getLastMessageID();

            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = 'PHPMailer Fehler: ' . $e->getMessage();
            $this->logMailError($this->lastError . ' (Ziel: ' . $actualToEmail . ', Betreff: ' . $subjectStr . ')', __FILE__, __LINE__);
            return false;
        } catch (\Throwable $e) {
            $this->lastError = 'Allgemeiner Mail-Fehler: ' . $e->getMessage();
            $this->logMailError($this->lastError . ' (Ziel: ' . $actualToEmail . ', Betreff: ' . $subjectStr . ')', __FILE__, __LINE__);
            return false;
        }
    }

    /**
     * Komfort-Methode: Versendet direkt die Benachrichtigungs-E-Mail für ein fertig konvertiertes Video.
     *
     * @param array $data ['merchant_id' => int, 'to_email' => string, 'creator_name' => string, 'movie_id' => int, 'upload_datetime' => string, 'playtime' => string, 'resolution' => string]
     * @return bool
     */
    public function sendMovieConvertedNotification(array $data)
    {
        $merchantId  = isset($data['merchant_id']) ? abs((int)$data['merchant_id']) : 0;
        $toEmail     = isset($data['to_email']) ? trim((string)$data['to_email']) : '';
        $creatorName = isset($data['creator_name']) ? trim((string)$data['creator_name']) : 'Creator';
        $movieId     = isset($data['movie_id']) ? abs((int)$data['movie_id']) : 0;
        $playtime    = isset($data['playtime']) ? trim((string)$data['playtime']) : '-';
        $resolution  = isset($data['resolution']) ? trim((string)$data['resolution']) : 'HD';

        $rawDt = isset($data['upload_datetime']) ? trim((string)$data['upload_datetime']) : '';
        if ($rawDt !== '' && $rawDt !== '0000-00-00 00:00:00') {
            $ts = @strtotime($rawDt);
            $uploadDatetime = ($ts !== false && $ts > 0) ? date('d.m.Y \u\m H:i \U\h\r', $ts) : $rawDt;
        } else {
            $uploadDatetime = date('d.m.Y \u\m H:i \U\h\r');
        }

        $unsubscribeUrl = self::getUnsubscribeUrl($merchantId, 'movie_converted');

        if (!class_exists('EroCloudMagicLink') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/EroCloudMagicLink.inc.php')) {
            include_once SOURCEDIR . '/includes/klassen/EroCloudMagicLink.inc.php';
        }

        if (class_exists('EroCloudMagicLink') && $merchantId > 0) {
            $movieEditUrl = EroCloudMagicLink::forMovie($merchantId, $movieId, 'email', 'movie_converted');
        } else {
            $movieEditUrl = 'https://erocloud.net/mcp_neu/web/index.php#/movie?id=' . $movieId;
        }

        $rendered = self::renderTemplate('movie_converted', [
            'merchant_id'     => $merchantId,
            'creator_name'    => $creatorName,
            'intended_email'  => $toEmail,
            'movie_id'        => $movieId,
            'upload_datetime' => $uploadDatetime,
            'playtime'        => $playtime,
            'resolution'      => $resolution,
            'movie_edit_url'  => $movieEditUrl,
            'unsubscribe_url' => $unsubscribeUrl,
        ]);

        $sent = $this->send(
            $toEmail,
            $creatorName,
            $rendered['subject'],
            $rendered['html'],
            $rendered['text'],
            [
                'unsubscribe_url' => $unsubscribeUrl,
            ]
        );

        if (class_exists('EroCloudNotificationCenter') && $merchantId > 0) {
            $isSandbox = (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true);
            $statusStr = $sent ? ($isSandbox ? 'sandbox' : 'sent') : 'failed';
            EroCloudNotificationCenter::logDelivery(
                $merchantId,
                EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED,
                EroCloudNotificationCenter::CHANNEL_EMAIL,
                'movie',
                $movieId,
                $this->effectiveRecipient !== '' ? $this->effectiveRecipient : $toEmail,
                $statusStr,
                $this->lastMessageId,
                $this->lastError
            );
        }

        return $sent;
    }

    /**
     * Konvertiert HTML-E-Mail-Inhalt sauber in lesbaren Plaintext für den Multipart-AltBody.
     *
     * @param string $html
     * @return string
     */
    public static function htmlToPlainText($html)
    {
        $text = (string)$html;
        $text = (string)preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        $text = (string)preg_replace('/<head\b[^>]*>(.*?)<\/head>/is', '', $text);
        $text = (string)preg_replace('/<(br|\/p|\/div|\/tr|\/h[1-6]|\/li)\b[^>]*>/i', "\n", $text);
        $text = (string)preg_replace('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $clean = [];
        foreach ($lines as $line) {
            $clean[] = trim($line);
        }
        $result = implode("\n", $clean);
        $result = (string)preg_replace("/\n{3,}/", "\n\n", $result);

        return trim($result);
    }

    /**
     * Schreibt Mail-Fehler in die zentrale EroCloud Error-Log-Datei (/var/www/web1/htdocs/log/.errorlog).
     *
     * @param string $message
     * @param string $file
     * @param int    $line
     * @return void
     */
    private function logMailError($message, $file, $line)
    {
        global $class_errorlog;
        if (isset($class_errorlog) && is_object($class_errorlog) && method_exists($class_errorlog, 'log')) {
            $class_errorlog->log('[EroCloudMailer] ' . (string)$message, (string)$file, (int)$line);
        } else {
            error_log('[EroCloudMailer] ' . (string)$message . ' in ' . (string)$file . ':' . (int)$line);
        }
    }
}
