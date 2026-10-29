<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/EroCloudMagicLink.inc.php';
require_once __DIR__ . '/EroCloudNotificationCenter.inc.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Zentrale E-Mail-Versandklasse für EroCloud (MCP, ACP & Cronjobs)
 *
 * Funktionen:
 * - Authentifizierter SMTP-Versand über `t199.nlserver.net` (TLS/SPF/DKIM-konform)
 * - Konfigurierbare Postfach- & Sandbox-Einstellungen direkt über das ACP (`settings`-Tabelle mit Fallback auf `config.inc.php`)
 * - Multipart-E-Mails (HTML + Plaintext-Fallback für SpamAssassin / T-Online / GMX / Gmail)
 * - Dynamisches Laden der offiziellen Impressumsdaten aus der `settings`-Tabelle
 * - Vollständiges Versand-Tracking in `notification_log` (inkl. Ziel-Empfänger, Betreff, Auslöser & HTML-Inhalt)
 * - Kompatibel mit PHP 8.0.30 und PHP 8.4.27
 */
class EroCloudMailer
{
    /** @var array<string, string>|null */
    private static ?array $imprintCache = null;

    /** @var array<string, mixed>|null */
    private static ?array $smtpConfigCache = null;

    private static string $lastStaticError = '';
    private static string $lastStaticRecipient = '';
    private static string $lastStaticMessageId = '';

    public function __get(string $name): mixed
    {
        return match ($name) {
            'lastError'          => self::$lastStaticError,
            'effectiveRecipient' => self::$lastStaticRecipient,
            'lastMessageId'      => self::$lastStaticMessageId,
            default              => null,
        };
    }

    /**
     * Stellt sicher, dass die Benachrichtigungs-Spalten in `movies` sowie die Tabellen im NotificationCenter existieren.
     */
    public static function ensureNotificationColumns(): void
    {
        if (class_exists('EroCloudNotificationCenter')) {
            \EroCloudNotificationCenter::ensureTablesExist();
        }

        $rs1 = @p4c_query("SHOW COLUMNS FROM `movies` LIKE 'notify_on_convert';", __FILE__, __LINE__);
        if ($rs1 && p4c_num_rows($rs1) === 0) {
            @p4c_query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", __FILE__, __LINE__);
            @p4c_query("ALTER TABLE `movies` ADD COLUMN `notify_on_convert` TINYINT(1) NOT NULL DEFAULT 1;", __FILE__, __LINE__);
        }

        $rs2 = @p4c_query("SHOW COLUMNS FROM `movies` LIKE 'notify_sent_at';", __FILE__, __LINE__);
        if ($rs2 && p4c_num_rows($rs2) === 0) {
            @p4c_query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", __FILE__, __LINE__);
            @p4c_query("ALTER TABLE `movies` ADD COLUMN `notify_sent_at` DATETIME NULL DEFAULT NULL;", __FILE__, __LINE__);
        }
    }

    /**
     * Gibt zurück, ob der Partner die Benachrichtigung für fertig konvertierte Videos aktiviert hat (1 = Ja, 0 = Nein).
     */
    public static function getMerchantNotifyPreference(int $merchantId): int
    {
        $merchantId = abs($merchantId);
        if ($merchantId <= 0) {
            return 1;
        }
        return \EroCloudNotificationCenter::isEnabled($merchantId, \EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED) ? 1 : 0;
    }

    /**
     * Speichert die Einstellung des Partners für fertig konvertierte Videos.
     */
    public static function setMerchantNotifyPreference(int $merchantId, int $notifyOnConvert): void
    {
        $merchantId = abs($merchantId);
        if ($merchantId <= 0) {
            return;
        }
        \EroCloudNotificationCenter::setPreference(
            $merchantId,
            \EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED,
            $notifyOnConvert === 1,
            'upload_step2'
        );
    }

    /**
     * Generiert die signierte 1-Klick-Abmelde-URL (RFC 8058).
     */
    public static function getUnsubscribeUrl(int $merchantId, string $eventKey = 'movie_converted'): string
    {
        return \EroCloudNotificationCenter::createUnsubscribeUrl(abs($merchantId), $eventKey);
    }

    /**
     * Ermittelt die Basis-URL zum MCP-Webverzeichnis (produktiv direkt MCP_URL / https://erocloud.net).
     */
    public static function getMcpWebUrl(): string
    {
        return defined('MCP_URL') ? rtrim((string)MCP_URL, '/') : 'https://erocloud.net';
    }

    /**
     * Lädt die aktuelle SMTP- & Sandbox-Konfiguration aus der Datenbank (`settings`),
     * mit Fallback auf die Konstanten aus `includes/config.inc.php`.
     *
     * @return array{
     *   smtp_host: string,
     *   smtp_port: int,
     *   smtp_secure: string,
     *   smtp_user: string,
     *   smtp_pass: string,
     *   has_password: bool,
     *   from_address: string,
     *   from_name: string,
     *   sandbox_mode: bool,
     *   sandbox_recipient: string
     * }
     */
    public static function getSmtpConfig(bool $forceReload = false): array
    {
        if (self::$smtpConfigCache !== null && !$forceReload) {
            return self::$smtpConfigCache;
        }

        $defaults = [
            'smtp_host'         => defined('MAIL_SMTP_HOST') ? (string)MAIL_SMTP_HOST : 'mail.erocloud.net',
            'smtp_port'         => defined('MAIL_SMTP_PORT') ? abs((int)MAIL_SMTP_PORT) : 587,
            'smtp_secure'       => defined('MAIL_SMTP_SECURE') ? strtolower(trim((string)MAIL_SMTP_SECURE)) : 'tls',
            'smtp_user'         => defined('MAIL_SMTP_USER') ? (string)MAIL_SMTP_USER : 'noreply@erocloud.net',
            'smtp_pass'         => defined('MAIL_SMTP_PASS') ? (string)MAIL_SMTP_PASS : '',
            'from_address'      => defined('MAIL_FROM_ADDRESS') ? (string)MAIL_FROM_ADDRESS : 'noreply@erocloud.net',
            'from_name'         => defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'EroCloud',
            'sandbox_mode'      => (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true),
            'sandbox_recipient' => defined('MAIL_SANDBOX_RECIPIENT') ? (string)MAIL_SANDBOX_RECIPIENT : 'erocms@gmail.com',
        ];

        $keys = [
            'mail_smtp_host',
            'mail_smtp_port',
            'mail_smtp_secure',
            'mail_smtp_user',
            'mail_smtp_pass_enc',
            'mail_from_address',
            'mail_from_name',
            'mail_sandbox_mode',
            'mail_sandbox_recipient',
        ];

        $inList = "'" . implode("','", $keys) . "'";
        $rs = p4c_query(
            "SELECT `variable`, `value`,
                    CASE WHEN `variable` = 'mail_smtp_pass_enc' AND `value` != ''
                         THEN CAST(AES_DECRYPT(UNHEX(`value`), '" . p4c_escape_string((string)AES_KEY) . "') AS CHAR)
                         ELSE NULL
                    END AS `decrypted_pass`
             FROM `settings`
             WHERE `variable` IN ($inList);",
            __FILE__,
            __LINE__
        );

        $dbVals = [];
        $decPass = null;
        if ($rs && p4c_num_rows($rs) > 0) {
            while ($row = p4c_fetch_object($rs)) {
                $varName = (string)($row->variable ?? '');
                $valStr  = trim((string)($row->value ?? ''));
                $dbVals[$varName] = $valStr;
                if ($varName === 'mail_smtp_pass_enc' && !empty($row->decrypted_pass)) {
                    $decPass = (string)$row->decrypted_pass;
                }
            }
        }

        $host = (!empty($dbVals['mail_smtp_host'])) ? $dbVals['mail_smtp_host'] : $defaults['smtp_host'];
        $port = (!empty($dbVals['mail_smtp_port']) && abs((int)$dbVals['mail_smtp_port']) > 0)
            ? abs((int)$dbVals['mail_smtp_port'])
            : $defaults['smtp_port'];
        $sec  = (!empty($dbVals['mail_smtp_secure']) && in_array(strtolower($dbVals['mail_smtp_secure']), ['tls', 'ssl', 'none'], true))
            ? strtolower($dbVals['mail_smtp_secure'])
            : $defaults['smtp_secure'];
        $user = (!empty($dbVals['mail_smtp_user'])) ? $dbVals['mail_smtp_user'] : $defaults['smtp_user'];
        $pass = ($decPass !== null && $decPass !== '') ? $decPass : $defaults['smtp_pass'];
        $fromAddr = (!empty($dbVals['mail_from_address'])) ? $dbVals['mail_from_address'] : $defaults['from_address'];
        $fromName = (!empty($dbVals['mail_from_name'])) ? $dbVals['mail_from_name'] : $defaults['from_name'];

        $sandboxMode = array_key_exists('mail_sandbox_mode', $dbVals)
            ? ($dbVals['mail_sandbox_mode'] === '1' || strtolower($dbVals['mail_sandbox_mode']) === 'true')
            : $defaults['sandbox_mode'];

        $sandboxRecipient = (!empty($dbVals['mail_sandbox_recipient']))
            ? $dbVals['mail_sandbox_recipient']
            : $defaults['sandbox_recipient'];

        self::$smtpConfigCache = [
            'smtp_host'         => $host,
            'smtp_port'         => $port,
            'smtp_secure'       => $sec,
            'smtp_user'         => $user,
            'smtp_pass'         => $pass,
            'has_password'      => ($pass !== ''),
            'from_address'      => $fromAddr,
            'from_name'         => $fromName,
            'sandbox_mode'      => $sandboxMode,
            'sandbox_recipient' => $sandboxRecipient,
        ];

        return self::$smtpConfigCache;
    }

    /**
     * Speichert die Postfach- & Sandbox-Konfiguration aus dem ACP in der Tabelle `settings`.
     * Das SMTP-Passwort wird AES-verschlüsselt (HEX) in `mail_smtp_pass_enc` abgelegt.
     *
     * @param array<string, mixed> $input
     */
    public static function saveSmtpConfig(array $input): bool
    {
        $current = self::getSmtpConfig(true);

        $host      = trim((string)($input['smtp_host'] ?? $current['smtp_host']));
        $port      = abs((int)($input['smtp_port'] ?? $current['smtp_port']));
        if ($port <= 0 || $port > 65535) {
            $port = 587;
        }
        $secure    = strtolower(trim((string)($input['smtp_secure'] ?? $current['smtp_secure'])));
        if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
            $secure = 'tls';
        }
        $user      = trim((string)($input['smtp_user'] ?? $current['smtp_user']));
        $fromAddr  = trim((string)($input['from_address'] ?? $current['from_address']));
        $fromName  = trim((string)($input['from_name'] ?? $current['from_name']));
        $sandbox   = !empty($input['sandbox_mode']) ? '1' : '0';
        $sbRecip   = trim((string)($input['sandbox_recipient'] ?? $current['sandbox_recipient']));

        $pairs = [
            'mail_smtp_host'         => $host,
            'mail_smtp_port'         => (string)$port,
            'mail_smtp_secure'       => $secure,
            'mail_smtp_user'         => $user,
            'mail_from_address'      => $fromAddr,
            'mail_from_name'         => $fromName,
            'mail_sandbox_mode'      => $sandbox,
            'mail_sandbox_recipient' => $sbRecip,
        ];

        foreach ($pairs as $var => $val) {
            $varEsc = p4c_escape_string($var);
            $valEsc = p4c_escape_string($val);
            p4c_query(
                "INSERT INTO `settings` (`variable`, `value`) VALUES ('{$varEsc}', '{$valEsc}')
                 ON DUPLICATE KEY UPDATE `value` = '{$valEsc}';",
                __FILE__,
                __LINE__
            );
        }

        $newPass = isset($input['smtp_pass']) ? trim((string)$input['smtp_pass']) : '';
        if ($newPass !== '' && $newPass !== '********') {
            $passEsc = p4c_escape_string($newPass);
            $aesKey  = p4c_escape_string((string)AES_KEY);
            p4c_query(
                "INSERT INTO `settings` (`variable`, `value`)
                 VALUES ('mail_smtp_pass_enc', HEX(AES_ENCRYPT('{$passEsc}', '{$aesKey}')))
                 ON DUPLICATE KEY UPDATE `value` = HEX(AES_ENCRYPT('{$passEsc}', '{$aesKey}'));",
                __FILE__,
                __LINE__
            );
        }

        self::$smtpConfigCache = null;
        return true;
    }

    /**
     * Ermittelt die entschlüsselte E-Mail-Adresse sowie den Anzeigenamen eines Partners (`merchants`).
     *
     * @return array{merchant_id: int, partner_id: string, email: string, creator_name: string, username: string, first_name: string}
     */
    public static function getMerchantRecipientInfo(int $merchantId): array
    {
        $merchantId = abs($merchantId);
        $info = [
            'merchant_id'  => $merchantId,
            'partner_id'   => '',
            'email'        => '',
            'creator_name' => 'Creator',
            'username'     => '',
            'first_name'   => '',
        ];

        if ($merchantId <= 0) {
            return $info;
        }

        $aesKey = p4c_escape_string((string)AES_KEY);
        $rs = p4c_query(
            "SELECT `id`, `partner_id`,
                    CAST(AES_DECRYPT(`email`, '{$aesKey}') AS CHAR) AS `dec_email`,
                    CAST(AES_DECRYPT(`username`, '{$aesKey}') AS CHAR) AS `dec_username`,
                    CAST(AES_DECRYPT(`firstname`, '{$aesKey}') AS CHAR) AS `dec_firstname`
             FROM `merchants`
             WHERE `id` = '{$merchantId}'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if ($rs && p4c_num_rows($rs) > 0) {
            $m = p4c_fetch_object($rs);
            $email     = trim(self::cleanUtf8((string)($m->dec_email ?? '')));
            $username  = trim(self::cleanUtf8((string)($m->dec_username ?? '')));
            $firstName = trim(self::cleanUtf8((string)($m->dec_firstname ?? '')));
            $partnerId = trim((string)($m->partner_id ?? ''));

            $creatorName = $username !== '' ? $username : ($firstName !== '' ? $firstName : ('Partner #' . $merchantId));

            $info['partner_id']   = $partnerId;
            $info['email']        = $email;
            $info['username']     = $username;
            $info['first_name']   = $firstName;
            $info['creator_name'] = $creatorName;
        }

        return $info;
    }

    /**
     * Gibt die offiziellen Pay4Coins / EroCloud Standard-Impressumsdaten zurück.
     *
     * @return array<string, string>
     */
    public static function getDefaultImprintData(): array
    {
        return [
            'imprint_company_name'     => 'CIPA Media S.L.U.',
            'imprint_address_street'   => 'Av. Emilio Luque Moreno 19 Oficina 6',
            'imprint_address_city'     => 'ES-38300 La Orotava (S/C de Tenerife)',
            'imprint_register_court'   => 'Registro Mercantil de Santa Cruz de Tenerife',
            'imprint_register_details' => 'Tomo: 3368, Folio: 43, Sección: 8, Hoja: TF-54807, Inscripción: 8',
            'imprint_vat_id'           => 'CIF: B76659168',
            'imprint_ceo'              => 'Christina Toepel',
            'imprint_phone'            => '+34 641 25 22 08',
            'imprint_whatsapp_url'     => 'https://wa.me/34641252208',
            'imprint_support_email'    => 'support@pay4coins.com',
            'imprint_website_url'      => 'https://pay4coins.com',
            'imprint_external_url'     => 'https://pay4coins.com/de/impressum',
        ];
    }

    /**
     * Lädt die Impressums- und Support-Kontaktdaten aus der `settings`-Tabelle
     * (mit sauberen Fallbacks, falls ein Feld leer ist).
     *
     * @return array<string, string>
     */
    public static function getImprintData(bool $forceReload = false): array
    {
        if (self::$imprintCache !== null && !$forceReload) {
            return self::$imprintCache;
        }

        $defaults = self::getDefaultImprintData();

        $keys = "'" . implode("','", array_keys($defaults)) . "'";
        $rs   = p4c_query("SELECT `variable`, `value` FROM `settings` WHERE `variable` IN ($keys);", __FILE__, __LINE__);
        if ($rs && p4c_num_rows($rs) > 0) {
            while ($row = p4c_fetch_object($rs)) {
                $var = (string)($row->variable ?? '');
                $val = trim((string)($row->value ?? ''));
                if ($var !== '' && $val !== '' && array_key_exists($var, $defaults)) {
                    $defaults[$var] = $val;
                }
            }
        }

        $defaults['support_email'] = $defaults['imprint_support_email'];

        self::$imprintCache = $defaults;
        return self::$imprintCache;
    }

    /**
     * Speichert die zentralen Impressums- und Support-Daten in der Tabelle `settings`.
     *
     * @param array<string, string> $input
     */
    public static function saveImprintData(array $input): bool
    {
        $defaults = self::getDefaultImprintData();
        foreach ($defaults as $key => $fallback) {
            $val = isset($input[$key]) && trim((string)$input[$key]) !== ''
                ? trim((string)$input[$key])
                : $fallback;
            $keyEsc = p4c_escape_string($key);
            $valEsc = p4c_escape_string($val);
            p4c_query(
                "INSERT INTO `settings` (`variable`, `value`) VALUES ('{$keyEsc}', '{$valEsc}')
                 ON DUPLICATE KEY UPDATE `value` = '{$valEsc}';",
                __FILE__,
                __LINE__
            );
        }

        self::$imprintCache = null;
        return true;
    }

    /**
     * Baut aus einem Template in `includes/mail_templates/<template>.inc.php`
     * Betreff, HTML-Body (im edlen EroCloud Layout) und Plaintext-Body zusammen.
     *
     * @param string               $templateName Name des Templates (z.B. 'movie_converted', 'content_reviewed', 'co_actor_status')
     * @param array<string, mixed> $vars         Variablen für das Template
     * @return array{subject: string, html: string, text: string}
     */
    public static function renderTemplate(string $templateName, array $vars = []): array
    {
        $safeName     = preg_replace('/[^a-zA-Z0-9_-]/', '', $templateName);
        $templateFile = dirname(__DIR__) . '/mail_templates/' . $safeName . '.inc.php';
        $layoutFile   = dirname(__DIR__) . '/mail_templates/layout.inc.php';

        if (!is_file($templateFile)) {
            throw new \RuntimeException('E-Mail-Template nicht gefunden: ' . $safeName);
        }

        $imprint = self::getImprintData();

        // Standard-Variablen bereitstellen
        $emailSubject     = 'Nachricht von EroCloud';
        $emailPreheader   = '';
        $emailHtmlContent = '';
        $emailTextContent = '';

        // Template ausführen (füllt $emailSubject, $emailPreheader, $emailHtmlContent, $emailTextContent)
        include $templateFile;

        $finalHtml = $emailHtmlContent;
        $finalText = trim($emailTextContent);

        if (is_file($layoutFile)) {
            $layoutVars = [
                'subject'          => $emailSubject,
                'preheader'        => $emailPreheader,
                'content_html'     => $emailHtmlContent,
                'content_text'     => $emailTextContent,
                'unsubscribe_url'  => (string)($vars['unsubscribe_url'] ?? ''),
                'intended_email'   => (string)($vars['intended_email'] ?? $vars['recipient_email'] ?? $vars['to_email'] ?? ''),
                'actual_recipient' => (string)($vars['actual_recipient'] ?? ''),
                'creator_name'     => (string)($vars['creator_name'] ?? 'Creator'),
                'merchant_id'      => isset($vars['merchant_id']) ? abs((int)$vars['merchant_id']) : 0,
                'imprint'          => $imprint,
                'movie_edit_url'   => (string)($vars['movie_edit_url'] ?? ''),
                'action_url'       => (string)($vars['action_url'] ?? $vars['movie_edit_url'] ?? ''),
            ];
            include $layoutFile;
        }

        return [
            'subject' => $emailSubject,
            'html'    => $finalHtml,
            'text'    => $finalText,
        ];
    }

    /**
     * Versendet eine E-Mail über SMTP (mit automatischem Sandbox-Schutz).
     *
     * @param string               $toEmail   Eigentliche Empfänger-Adresse (Partner/Creator)
     * @param string               $toName    Empfänger-Name
     * @param string               $subject   Betreffzeile
     * @param string               $htmlBody  Vollständiger HTML-Body
     * @param string               $textBody  Reiner Text-Body (Multipart/Alternative)
     * @param array<string, mixed> $options   Optionale Zusatzdaten (z.B. 'reply_to', 'unsubscribe_url')
     * @return array{success: bool, recipient: string, intended_recipient: string, sandbox: bool, message_id: string, error: string}
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array $options = []
    ): array {
        $cfg = self::getSmtpConfig();

        $isSandbox       = (bool)$cfg['sandbox_mode'];
        $sandboxTarget   = trim((string)$cfg['sandbox_recipient']);
        if ($sandboxTarget === '') {
            $sandboxTarget = 'erocms@gmail.com';
        }

        $intendedRecipient = trim($toEmail);
        $overrideRecipient = !empty($options['override_recipient']) ? trim((string)$options['override_recipient']) : '';
        if ($overrideRecipient !== '' && filter_var($overrideRecipient, FILTER_VALIDATE_EMAIL)) {
            $actualRecipient = $overrideRecipient;
        } else {
            $actualRecipient = $isSandbox ? $sandboxTarget : $intendedRecipient;
        }
        $actualSubject     = $subject;

        self::$lastStaticRecipient = $actualRecipient;
        self::$lastStaticError     = '';
        self::$lastStaticMessageId = '';

        if ($isSandbox && $intendedRecipient !== '') {
            $textBody = "[SANDBOX-TESTMODUS | Ursprünglicher Empfänger: $toName <$intendedRecipient>]\n\n" . $textBody;
        }

        if ($actualRecipient === '' || !filter_var($actualRecipient, FILTER_VALIDATE_EMAIL)) {
            $err = 'Ungültige Empfänger-E-Mail-Adresse: ' . $actualRecipient;
            self::$lastStaticError = $err;
            return [
                'success'            => false,
                'recipient'          => $actualRecipient,
                'intended_recipient' => $intendedRecipient,
                'sandbox'            => $isSandbox,
                'message_id'         => '',
                'error'              => $err,
            ];
        }

        $imprint     = self::getImprintData();
        $fromAddress = (string)$cfg['from_address'];
        $fromName    = (string)$cfg['from_name'];
        $replyTo     = !empty($options['reply_to'])
            ? (string)$options['reply_to']
            : (defined('MAIL_REPLY_TO_ADDRESS') ? trim((string)MAIL_REPLY_TO_ADDRESS) : '');

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet  = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->setLanguage('de');

            // SMTP-Konfiguration aus getSmtpConfig()
            $mail->isSMTP();
            $mail->Host       = (string)$cfg['smtp_host'];
            $mail->Port       = (int)$cfg['smtp_port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = (string)$cfg['smtp_user'];
            $mail->Password   = (string)$cfg['smtp_pass'];
            $mail->Timeout    = 15;

            $secureMode = strtolower((string)$cfg['smtp_secure']);
            if ($secureMode === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secureMode === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            // Auf lokaler XAMPP-Entwicklungsumgebung selbstsignierte Zertifikate tolerieren
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($actualRecipient, $toName);

            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $fromName);
            }

            if (!empty($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $att) {
                    if (is_array($att) && !empty($att[0]) && is_file((string)$att[0])) {
                        $mail->addAttachment((string)$att[0], (string)($att[1] ?? ''));
                    } elseif (is_string($att) && $att !== '' && is_file($att)) {
                        $mail->addAttachment($att);
                    }
                }
            }

            // Anti-Spam & Bulk-Sender Header (RFC 8058 List-Unsubscribe für Gmail, GMX, Web.de, T-Online)
            $unsubEmail = $imprint['support_email'] ?? 'support@pay4coins.com';
            if (!empty($options['unsubscribe_url'])) {
                $unsubUrl = (string)$options['unsubscribe_url'];
                $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubUrl . '>, <mailto:' . $unsubEmail . '?subject=Unsubscribe>');
                $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            } else {
                $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . $unsubEmail . '?subject=Unsubscribe>');
            }
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

            $mail->isHTML(true);
            $mail->Subject = $actualSubject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            $mail->send();
            $msgId = trim((string)$mail->getLastMessageID(), '<> ');
            self::$lastStaticMessageId = $msgId;

            return [
                'success'            => true,
                'recipient'          => $actualRecipient,
                'intended_recipient' => $intendedRecipient,
                'sandbox'            => $isSandbox,
                'message_id'         => $msgId,
                'error'              => '',
            ];
        } catch (PHPMailerException|\Throwable $e) {
            $errMsg = 'Mailer Error (' . $actualRecipient . '): ' . $e->getMessage();
            self::$lastStaticError = $e->getMessage();
            if (function_exists('p4c_errorlog')) {
                p4c_errorlog($errMsg);
            }
            return [
                'success'            => false,
                'recipient'          => $actualRecipient,
                'intended_recipient' => $intendedRecipient,
                'sandbox'            => $isSandbox,
                'message_id'         => '',
                'error'              => $e->getMessage(),
            ];
        }
    }

    /**
     * Komfort-Methode: Versendet die „Video fertig konvertiert & Vorschaubilder bereit“-E-Mail
     * inklusive Magic-Link (1-Klick-Auto-Login), Opt-Out-Prüfung und Versand-Protokollierung.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, recipient: string, sandbox: bool, message_id?: string, error: string, skipped?: bool}
     */
    public static function sendMovieConvertedNotification(array $data): array
    {
        $merchantId = isset($data['merchant_id']) ? abs((int)$data['merchant_id']) : 0;
        $movieId    = isset($data['movie_id']) ? abs((int)$data['movie_id']) : 0;
        $forceSend  = !empty($data['force_send']);
        $eventKey   = \EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED;

        if (empty($data['recipient_email']) && !empty($data['to_email'])) {
            $data['recipient_email'] = (string)$data['to_email'];
        }

        if ($merchantId > 0 && (empty($data['recipient_email']) || empty($data['creator_name']))) {
            $mInfo = self::getMerchantRecipientInfo($merchantId);
            if (empty($data['recipient_email'])) {
                $data['recipient_email'] = $mInfo['email'];
            }
            if (empty($data['creator_name'])) {
                $data['creator_name'] = $mInfo['creator_name'];
            }
        }

        $intendedEmail = trim((string)($data['recipient_email'] ?? ''));

        // 1. Prüfen, ob der Partner diese Benachrichtigung aktiviert hat (Opt-out Check)
        if (!$forceSend && $merchantId > 0) {
            if (!\EroCloudNotificationCenter::isEnabled($merchantId, $eventKey)) {
                return [
                    'success'   => false,
                    'skipped'   => true,
                    'recipient' => $intendedEmail,
                    'sandbox'   => false,
                    'error'     => 'Partner hat diese Benachrichtigungs-Kategorie deaktiviert.',
                ];
            }

            // Doppel-Versand für denselben Film verhindern
            if ($movieId > 0 && \EroCloudNotificationCenter::hasAlreadyBeenSent($merchantId, $eventKey, $movieId)) {
                return [
                    'success'   => true,
                    'skipped'   => true,
                    'recipient' => $intendedEmail,
                    'sandbox'   => false,
                    'error'     => '',
                ];
            }
        }

        // 2. 1-Klick Auto-Login Magic-Link generieren (falls merchant_id vorhanden)
        $targetRoute = '#/movie?id=' . $movieId;
        if (!empty($data['movie_edit_url'])) {
            $editUrl = (string)$data['movie_edit_url'];
        } elseif ($merchantId > 0 && class_exists('EroCloudMagicLink')) {
            $editUrl = \EroCloudMagicLink::createUrl($merchantId, $targetRoute, 72);
        } else {
            $editUrl = self::getMcpWebUrl() . '/' . $targetRoute;
        }

        // 3. 1-Klick-Abmeldelink für Header & Footer erzeugen
        $unsubscribeUrl = '';
        if ($merchantId > 0 && class_exists('EroCloudNotificationCenter')) {
            $unsubscribeUrl = \EroCloudNotificationCenter::createUnsubscribeUrl($merchantId, $eventKey);
        }

        $overrideRecip = !empty($data['override_recipient']) ? trim((string)$data['override_recipient']) : '';

        $rendered = self::renderTemplate('movie_converted', [
            'merchant_id'      => $merchantId,
            'intended_email'   => $intendedEmail,
            'actual_recipient' => $overrideRecip,
            'creator_name'     => (string)($data['creator_name'] ?? 'Creator'),
            'movie_id'         => $movieId,
            'upload_datetime'  => (string)($data['upload_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r')),
            'playtime'         => (string)($data['playtime'] ?? '-'),
            'resolution'       => (string)($data['resolution'] ?? 'HD'),
            'movie_edit_url'   => $editUrl,
            'unsubscribe_url'  => $unsubscribeUrl,
        ]);

        $sendResult = self::send(
            $intendedEmail,
            (string)($data['creator_name'] ?? 'Creator'),
            $rendered['subject'],
            $rendered['html'],
            $rendered['text'],
            [
                'unsubscribe_url'    => $unsubscribeUrl,
                'override_recipient' => $overrideRecip,
            ]
        );

        // 4. Versand in `notification_log` protokollieren
        if (class_exists('EroCloudNotificationCenter')) {
            $logStatus = $sendResult['success']
                ? ($sendResult['sandbox'] ? 'sandbox_sent' : 'sent')
                : 'failed';
            \EroCloudNotificationCenter::logDelivery(
                $merchantId,
                $eventKey,
                $movieId,
                $sendResult['recipient'],
                $sendResult['sandbox'],
                $logStatus,
                $sendResult['error'],
                $intendedEmail !== '' ? $intendedEmail : $sendResult['recipient'],
                $rendered['subject'],
                'movie_converted',
                $rendered['html']
            );
        }

        return $sendResult;
    }

    /**
     * Komfort-Methode: Versendet eine Prüfungs-Status-Benachrichtigung für Filme, Foto-Alben oder Darsteller-Profile
     * (Freigabe oder Ablehnung/Korrekturhinweis) inkl. 1-Klick Magic-Link und Tracking.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, recipient: string, sandbox: bool, message_id?: string, error: string, skipped?: bool}
     */
    public static function sendContentReviewNotification(array $data): array
    {
        $merchantId   = isset($data['merchant_id']) ? abs((int)$data['merchant_id']) : 0;
        $contentType  = strtolower(trim((string)($data['content_type'] ?? 'movie'))); // movie | photo_album | actor
        $contentId    = isset($data['content_id']) ? abs((int)$data['content_id']) : 0;
        $reviewStatus = strtolower(trim((string)($data['review_status'] ?? 'approved'))); // approved | rejected
        $forceSend    = !empty($data['force_send']);
        $eventKey     = \EroCloudNotificationCenter::EVENT_CONTENT_REVIEWED;

        if (empty($data['recipient_email']) && !empty($data['to_email'])) {
            $data['recipient_email'] = (string)$data['to_email'];
        }

        $mInfo = self::getMerchantRecipientInfo($merchantId);
        $intendedEmail = !empty($data['recipient_email']) ? trim((string)$data['recipient_email']) : $mInfo['email'];
        $creatorName   = !empty($data['creator_name']) ? trim((string)$data['creator_name']) : $mInfo['creator_name'];

        if (!$forceSend && $merchantId > 0) {
            if (!\EroCloudNotificationCenter::isEnabled($merchantId, $eventKey)) {
                return [
                    'success'   => false,
                    'skipped'   => true,
                    'recipient' => $intendedEmail,
                    'sandbox'   => false,
                    'error'     => 'Partner hat diese Benachrichtigungs-Kategorie deaktiviert.',
                ];
            }
        }

        if ($contentType === 'photo_album') {
            $targetRoute = '#/photo-album?id=' . $contentId;
            $triggerPrefix = 'album_';
        } elseif ($contentType === 'actor') {
            $targetRoute = '#/actor?id=' . $contentId;
            $triggerPrefix = 'actor_';
        } else {
            $targetRoute = '#/movie?id=' . $contentId;
            $triggerPrefix = 'movie_';
        }

        $triggerDetail = $triggerPrefix . ($reviewStatus === 'approved' ? 'approved' : 'rejected');

        if ($merchantId > 0 && class_exists('EroCloudMagicLink')) {
            $actionUrl = \EroCloudMagicLink::createUrl($merchantId, $targetRoute, 72);
        } else {
            $actionUrl = self::getMcpWebUrl() . '/' . $targetRoute;
        }

        $unsubscribeUrl = '';
        if ($merchantId > 0 && class_exists('EroCloudNotificationCenter')) {
            $unsubscribeUrl = \EroCloudNotificationCenter::createUnsubscribeUrl($merchantId, $eventKey);
        }

        $overrideRecip = !empty($data['override_recipient']) ? trim((string)$data['override_recipient']) : '';

        $rendered = self::renderTemplate('content_reviewed', [
            'merchant_id'      => $merchantId,
            'intended_email'   => $intendedEmail,
            'actual_recipient' => $overrideRecip,
            'creator_name'     => $creatorName,
            'content_type'     => $contentType,
            'content_id'       => $contentId,
            'review_status'    => $reviewStatus,
            'review_datetime'  => (string)($data['review_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r')),
            'reason_title'     => (string)($data['reason_title'] ?? ''),
            'reason_text'      => (string)($data['reason_text'] ?? ''),
            'extra_meta_label' => (string)($data['extra_meta_label'] ?? ''),
            'extra_meta_value' => (string)($data['extra_meta_value'] ?? ''),
            'action_url'       => $actionUrl,
            'unsubscribe_url'  => $unsubscribeUrl,
        ]);

        $sendResult = self::send(
            $intendedEmail,
            $creatorName,
            $rendered['subject'],
            $rendered['html'],
            $rendered['text'],
            [
                'unsubscribe_url'    => $unsubscribeUrl,
                'override_recipient' => $overrideRecip,
            ]
        );

        if (class_exists('EroCloudNotificationCenter')) {
            $logStatus = $sendResult['success']
                ? ($sendResult['sandbox'] ? 'sandbox_sent' : 'sent')
                : 'failed';
            \EroCloudNotificationCenter::logDelivery(
                $merchantId,
                $eventKey,
                $contentId,
                $sendResult['recipient'],
                $sendResult['sandbox'],
                $logStatus,
                $sendResult['error'],
                $intendedEmail !== '' ? $intendedEmail : $sendResult['recipient'],
                $rendered['subject'],
                $triggerDetail,
                $rendered['html']
            );
        }

        return $sendResult;
    }

    /**
     * Komfort-Methode: Versendet eine Status-Benachrichtigung zum 18+ ID-Check / Drehpartner
     * (Smartphone QR-Check eingereicht, im ACP bestätigt oder abgelehnt).
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, recipient: string, sandbox: bool, message_id?: string, error: string, skipped?: bool}
     */
    public static function sendCoActorStatusNotification(array $data): array
    {
        $merchantId = isset($data['merchant_id']) ? abs((int)$data['merchant_id']) : 0;
        $coActorId  = isset($data['co_actor_id']) ? abs((int)$data['co_actor_id']) : 0;
        $statusType = strtolower(trim((string)($data['status_type'] ?? 'verified'))); // submitted | verified | rejected
        $forceSend  = !empty($data['force_send']);
        $eventKey   = \EroCloudNotificationCenter::EVENT_CO_ACTOR_STATUS;

        if (empty($data['recipient_email']) && !empty($data['to_email'])) {
            $data['recipient_email'] = (string)$data['to_email'];
        }

        $mInfo = self::getMerchantRecipientInfo($merchantId);
        $intendedEmail = !empty($data['recipient_email']) ? trim((string)$data['recipient_email']) : $mInfo['email'];
        $creatorName   = !empty($data['creator_name']) ? trim((string)$data['creator_name']) : $mInfo['creator_name'];

        if (!$forceSend && $merchantId > 0) {
            if (!\EroCloudNotificationCenter::isEnabled($merchantId, $eventKey)) {
                return [
                    'success'   => false,
                    'skipped'   => true,
                    'recipient' => $intendedEmail,
                    'sandbox'   => false,
                    'error'     => 'Partner hat diese Benachrichtigungs-Kategorie deaktiviert.',
                ];
            }
        }

        $targetRoute   = '#/co-actors';
        $triggerDetail = 'co_actor_' . (in_array($statusType, ['submitted', 'verified', 'rejected'], true) ? $statusType : 'verified');

        if ($merchantId > 0 && class_exists('EroCloudMagicLink')) {
            $actionUrl = \EroCloudMagicLink::createUrl($merchantId, $targetRoute, 72);
        } else {
            $actionUrl = self::getMcpWebUrl() . '/' . $targetRoute;
        }

        $unsubscribeUrl = '';
        if ($merchantId > 0 && class_exists('EroCloudNotificationCenter')) {
            $unsubscribeUrl = \EroCloudNotificationCenter::createUnsubscribeUrl($merchantId, $eventKey);
        }

        $refCode = !empty($data['ref_code']) ? (string)$data['ref_code'] : sprintf('DP-%d-%03d', $merchantId, $coActorId);

        $overrideRecip = !empty($data['override_recipient']) ? trim((string)$data['override_recipient']) : '';

        $rendered = self::renderTemplate('co_actor_status', [
            'merchant_id'      => $merchantId,
            'intended_email'   => $intendedEmail,
            'actual_recipient' => $overrideRecip,
            'creator_name'     => $creatorName,
            'co_actor_id'      => $coActorId,
            'ref_code'         => $refCode,
            'role_label'       => (string)($data['role_label'] ?? 'Drehpartner'),
            'alias_label'      => (string)($data['alias_label'] ?? ('Eintrag #' . $coActorId)),
            'status_type'      => $statusType,
            'event_datetime'   => (string)($data['event_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r')),
            'reason_text'      => (string)($data['reason_text'] ?? ''),
            'action_url'       => $actionUrl,
            'unsubscribe_url'  => $unsubscribeUrl,
        ]);

        $sendResult = self::send(
            $intendedEmail,
            $creatorName,
            $rendered['subject'],
            $rendered['html'],
            $rendered['text'],
            [
                'unsubscribe_url'    => $unsubscribeUrl,
                'override_recipient' => $overrideRecip,
            ]
        );

        if (class_exists('EroCloudNotificationCenter')) {
            $logStatus = $sendResult['success']
                ? ($sendResult['sandbox'] ? 'sandbox_sent' : 'sent')
                : 'failed';
            \EroCloudNotificationCenter::logDelivery(
                $merchantId,
                $eventKey,
                $coActorId,
                $sendResult['recipient'],
                $sendResult['sandbox'],
                $logStatus,
                $sendResult['error'],
                $intendedEmail !== '' ? $intendedEmail : $sendResult['recipient'],
                $rendered['subject'],
                $triggerDetail,
                $rendered['html']
            );
        }

        return $sendResult;
    }

    private static function cleanUtf8(string $val): string
    {
        if ($val === '') {
            return '';
        }
        if (!mb_check_encoding($val, 'UTF-8')) {
            $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }
        return html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
