<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * EroCloudNotificationCenter
 *
 * Verwaltet die E-Mail-Benachrichtigungs-Einstellungen der Partner/Creator (Opt-in / Opt-out),
 * die sicheren 1-Klick-Abmeldelinks (RFC 8058 / Gmail & Yahoo Bulk-Sender-Richtlinien)
 * sowie das zentrale Versand-Protokoll (notification_log) inkl. Detail-Tracking.
 *
 * Vollständig kompatibel mit PHP 8.0.30 und PHP 8.4.27.
 */
class EroCloudNotificationCenter
{
    public const EVENT_MOVIE_CONVERTED  = 'movie_converted';
    public const EVENT_CONTENT_REVIEWED = 'content_reviewed';
    public const EVENT_CO_ACTOR_STATUS  = 'co_actor_status';
    public const EVENT_NEWSLETTER       = 'newsletter';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PUSH  = 'push';

    /** @var bool */
    private static bool $tablesChecked = false;

    /**
     * Liefert die unterstützten Benachrichtigungs-Kanäle.
     *
     * @return array<string, array{key: string, label: string, active: bool}>
     */
    public static function getChannelCatalog(): array
    {
        return [
            self::CHANNEL_EMAIL => [
                'key'    => self::CHANNEL_EMAIL,
                'label'  => 'E-Mail',
                'active' => true,
            ],
            self::CHANNEL_PUSH => [
                'key'    => self::CHANNEL_PUSH,
                'label'  => 'Push (In Kürze)',
                'active' => false,
            ],
        ];
    }

    /**
     * Liefert die Definition aller steuerbaren Benachrichtigungs-Kategorien für das MCP & ACP.
     *
     * @return array<string, array{key: string, title: string, description: string, icon: string, default: int, badge: string}>
     */
    public static function getEventCatalog(): array
    {
        return [
            self::EVENT_MOVIE_CONVERTED => [
                'key'         => self::EVENT_MOVIE_CONVERTED,
                'title'       => 'Video fertig konvertiert & Vorschaubilder bereit',
                'description' => 'Du erhältst eine E-Mail mit einem 1-Klick-Direktlink zu deinem Film, sobald dein hochgeladenes Video fertig konvertiert wurde und die 10 Vorschaubilder im Cover-Studio bereitstehen.',
                'icon'        => 'bi-film',
                'default'     => 1,
                'badge'       => 'Empfohlen',
            ],
            self::EVENT_CONTENT_REVIEWED => [
                'key'         => self::EVENT_CONTENT_REVIEWED,
                'title'       => 'Prüfungs-Status (Filme, Foto-Alben & Profile)',
                'description' => 'Benachrichtigung, sobald deine eingereichten Filme, Foto-Alben oder Darsteller-Profile von unserem Team geprüft und freigegeben wurden oder eine Rückfrage bzw. Korrektur vorliegt.',
                'icon'        => 'bi-patch-check',
                'default'     => 1,
                'badge'     => 'Wichtig',
            ],
            self::EVENT_CO_ACTOR_STATUS => [
                'key'         => self::EVENT_CO_ACTOR_STATUS,
                'title'       => 'Drehpartner & 18+ ID-Check Status',
                'description' => 'Benachrichtigung, sobald ein Drehpartner die Smartphone-Verifizierung per QR-Code abgeschlossen hat oder unser Team einen 18+ ID-Check geprüft hat.',
                'icon'        => 'bi-person-check',
                'default'     => 1,
                'badge'       => 'Compliance',
            ],
            self::EVENT_NEWSLETTER => [
                'key'         => self::EVENT_NEWSLETTER,
                'title'       => 'EroCloud Creator-News & Updates',
                'description' => 'Wichtige Neuigkeiten zu neuen Funktionen, Tipps zur Umsatzsteigerung und Plattform-Updates für Partner.',
                'icon'        => 'bi-megaphone',
                'default'     => 1,
                'badge'       => 'Info',
            ],
        ];
    }

    /**
     * Liefert das vollständige Verzeichnis aller konkreten E-Mail-Auslöser (Trigger) im System
     * für die Admin-Übersicht im ACP (#/system-settings).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTriggerCatalog(): array
    {
        return [
            [
                'trigger_key'       => 'movie_converted',
                'event_key'         => self::EVENT_MOVIE_CONVERTED,
                'template'          => 'movie_converted',
                'preview_variant'   => 'movie_converted',
                'title'             => 'Video fertig konvertiert (10 Vorschaubilder bereit)',
                'when'              => 'Automatisch durch den Konvertierungs-Cronjob (cronjobs/convert_movie.php), sobald FFmpeg die MP4-Konvertierung und alle 10 Szenen-Thumbnails abgeschlossen hat.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/movie?id={ID} (Direkt-Login in die Film-Bearbeitung & Cover-Studio)',
                'badge_label'       => 'Automatisch (Cronjob)',
                'badge_cls'         => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',
                'icon'              => 'bi-film',
            ],
            [
                'trigger_key'       => 'movie_approved',
                'event_key'         => self::EVENT_CONTENT_REVIEWED,
                'template'          => 'content_reviewed',
                'preview_variant'   => 'movie_approved',
                'title'             => 'Film geprüft & veröffentlicht (Freigabe)',
                'when'              => 'Sobald ein Admin/Moderator im ACP unter „Filme prüfen“ (#/movie-checking) einen eingereichten Film prüft und freigibt.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/movie?id={ID} (Direkt-Login zu den Film-Details)',
                'badge_label'       => 'ACP: Filme prüfen',
                'badge_cls'         => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                'icon'              => 'bi-check-circle-fill',
            ],
            [
                'trigger_key'       => 'movie_rejected',
                'event_key'         => self::EVENT_CONTENT_REVIEWED,
                'template'          => 'content_reviewed',
                'preview_variant'   => 'movie_rejected',
                'title'             => 'Film abgelehnt / Korrektur erforderlich',
                'when'              => 'Sobald ein Admin/Moderator im ACP unter „Filme prüfen“ (#/movie-checking) einen Film mit Ablehnungsgrund und Hinweis-Text zur Korrektur zurückweist.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/movie?id={ID} (Direkt-Login zur Korrektur des Films)',
                'badge_label'       => 'ACP: Filme prüfen',
                'badge_cls'         => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                'icon'              => 'bi-exclamation-triangle-fill',
            ],
            [
                'trigger_key'       => 'album_approved',
                'event_key'         => self::EVENT_CONTENT_REVIEWED,
                'template'          => 'content_reviewed',
                'preview_variant'   => 'album_approved',
                'title'             => 'Foto-Album geprüft & veröffentlicht (Freigabe)',
                'when'              => 'Sobald ein Admin/Moderator im ACP unter „Fotoalben prüfen“ (#/photo-album-checking) ein eingereichtes Foto-Album prüft und veröffentlicht.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/photo-album?id={ID} (Direkt-Login zum Foto-Album)',
                'badge_label'       => 'ACP: Fotoalben prüfen',
                'badge_cls'         => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                'icon'              => 'bi-images',
            ],
            [
                'trigger_key'       => 'album_rejected',
                'event_key'         => self::EVENT_CONTENT_REVIEWED,
                'template'          => 'content_reviewed',
                'preview_variant'   => 'album_rejected',
                'title'             => 'Foto-Album abgelehnt / Korrektur erforderlich',
                'when'              => 'Sobald ein Admin/Moderator im ACP unter „Fotoalben prüfen“ (#/photo-album-checking) ein Foto-Album mit Begründung zur Nachbesserung ablehnt.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/photo-album?id={ID} (Direkt-Login zur Korrektur des Foto-Albums)',
                'badge_label'       => 'ACP: Fotoalben prüfen',
                'badge_cls'         => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                'icon'              => 'bi-exclamation-triangle-fill',
            ],
            [
                'trigger_key'       => 'actor_approved',
                'event_key'         => self::EVENT_CONTENT_REVIEWED,
                'template'          => 'content_reviewed',
                'preview_variant'   => 'actor_approved',
                'title'             => 'Darsteller-Profil geprüft & aktiviert',
                'when'              => 'Sobald ein Admin im ACP unter „Darsteller-Profile“ (#/actor) den Status eines neuen oder überarbeiteten Profils auf „Aktiv“ setzt.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/actor?id={ID} (Direkt-Login zum Darsteller-Profil)',
                'badge_label'       => 'ACP: Darsteller-Profile',
                'badge_cls'         => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                'icon'              => 'bi-person-badge-fill',
            ],
            [
                'trigger_key'       => 'actor_rejected',
                'event_key'         => self::EVENT_CONTENT_REVIEWED,
                'template'          => 'content_reviewed',
                'preview_variant'   => 'actor_rejected',
                'title'             => 'Darsteller-Profil gesperrt / Rückfrage',
                'when'              => 'Sobald ein Admin im ACP unter „Darsteller-Profile“ (#/actor) ein Profil auf „Gesperrt“ setzt oder einen Korrekturhinweis hinterlegt.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/actor?id={ID} (Direkt-Login zum Darsteller-Profil)',
                'badge_label'       => 'ACP: Darsteller-Profile',
                'badge_cls'         => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                'icon'              => 'bi-person-x-fill',
            ],
            [
                'trigger_key'       => 'co_actor_submitted',
                'event_key'         => self::EVENT_CO_ACTOR_STATUS,
                'template'          => 'co_actor_status',
                'preview_variant'   => 'co_actor_submitted',
                'title'             => 'Drehpartner hat Smartphone-QR-Verifizierung eingereicht',
                'when'              => 'Sobald ein Drehpartner (oder der Creator selbst) auf dem Smartphone unter verify_co_actor.php alle 3 Kamera-Aufnahmen und die digitale Unterschrift absendet.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/co-actors (Direkt-Login zur Drehpartner-Übersicht)',
                'badge_label'       => 'Smartphone QR-Check',
                'badge_cls'         => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
                'icon'              => 'bi-qr-code-scan',
            ],
            [
                'trigger_key'       => 'co_actor_verified',
                'event_key'         => self::EVENT_CO_ACTOR_STATUS,
                'template'          => 'co_actor_status',
                'preview_variant'   => 'co_actor_verified',
                'title'             => 'Drehpartner / 18+ ID-Check geprüft & bestätigt',
                'when'              => 'Sobald ein Admin im ACP unter „Drehpartner & ID-Check“ (#/co-actors-checking) den Ausweis, den ID-Shot und das Model-Release als gültig bestätigt.',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/co-actors (Direkt-Login zur Drehpartner-Übersicht)',
                'badge_label'       => 'ACP: 18+ ID-Check',
                'badge_cls'         => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                'icon'              => 'bi-patch-check-fill',
            ],
            [
                'trigger_key'       => 'co_actor_rejected',
                'event_key'         => self::EVENT_CO_ACTOR_STATUS,
                'template'          => 'co_actor_status',
                'preview_variant'   => 'co_actor_rejected',
                'title'             => 'Drehpartner / 18+ ID-Check abgelehnt (Neuaufnahme nötig)',
                'when'              => 'Sobald ein Admin im ACP unter „Drehpartner & ID-Check“ (#/co-actors-checking) einen ID-Shot ablehnt (z. B. Ausweis unscharf oder abgelaufen).',
                'recipient'         => 'Partner / Creator (Account-Inhaber)',
                'magic_link_target' => '#/co-actors (Direkt-Login zum Erneuern des QR-Codes)',
                'badge_label'       => 'ACP: 18+ ID-Check',
                'badge_cls'         => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                'icon'              => 'bi-shield-exclamation',
            ],
        ];
    }

    /**
     * Legt die Tabellen `merchant_notification_prefs` und `notification_log` automatisch an,
     * falls sie noch nicht existieren, und erweitert bestehende Tabellen um neue Tracking-Spalten.
     */
    public static function ensureTablesExist(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        self::$tablesChecked = true;

        $sqlPrefs = "CREATE TABLE IF NOT EXISTS `merchant_notification_prefs` (
            `merchant_id` INT UNSIGNED NOT NULL,
            `event_key` VARCHAR(50) NOT NULL,
            `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `updated_at` DATETIME NOT NULL,
            `updated_via` VARCHAR(30) NOT NULL DEFAULT 'mcp',
            PRIMARY KEY (`merchant_id`, `event_key`),
            KEY `idx_event_enabled` (`event_key`, `enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        p4c_query($sqlPrefs, __FILE__, __LINE__);

        $sqlLog = "CREATE TABLE IF NOT EXISTS `notification_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `merchant_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `event_key` VARCHAR(50) NOT NULL DEFAULT '',
            `trigger_detail` VARCHAR(64) NOT NULL DEFAULT '',
            `reference_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `recipient` VARCHAR(190) NOT NULL DEFAULT '',
            `intended_recipient` VARCHAR(190) NOT NULL DEFAULT '',
            `subject` VARCHAR(255) NOT NULL DEFAULT '',
            `body_html` MEDIUMTEXT NULL DEFAULT NULL,
            `sandbox_mode` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
            `error_message` VARCHAR(500) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_merchant_event` (`merchant_id`, `event_key`, `reference_id`),
            KEY `idx_created` (`created_at`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        p4c_query($sqlLog, __FILE__, __LINE__);

        // Prüfen, ob in einer bestehenden notification_log Tabelle bereits alle neuen Spalten vorhanden sind
        $existingCols = [];
        $rsCols = p4c_query("SHOW COLUMNS FROM `notification_log`;", __FILE__, __LINE__);
        if ($rsCols && p4c_num_rows($rsCols) > 0) {
            while ($cObj = p4c_fetch_object($rsCols)) {
                $existingCols[strtolower((string)$cObj->Field)] = true;
            }
        }

        $alterParts = [];
        if (!isset($existingCols['trigger_detail'])) {
            $alterParts[] = "ADD COLUMN `trigger_detail` VARCHAR(64) NOT NULL DEFAULT '' AFTER `event_key`";
        }
        if (!isset($existingCols['intended_recipient'])) {
            $alterParts[] = "ADD COLUMN `intended_recipient` VARCHAR(190) NOT NULL DEFAULT '' AFTER `recipient`";
        }
        if (!isset($existingCols['subject'])) {
            $alterParts[] = "ADD COLUMN `subject` VARCHAR(255) NOT NULL DEFAULT '' AFTER `intended_recipient`";
        }
        if (!isset($existingCols['body_html'])) {
            $alterParts[] = "ADD COLUMN `body_html` MEDIUMTEXT NULL DEFAULT NULL AFTER `subject`";
        }
        if (!isset($existingCols['sandbox_mode'])) {
            $alterParts[] = "ADD COLUMN `sandbox_mode` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `body_html`";
        }
        if (!empty($alterParts)) {
            p4c_query("ALTER TABLE `notification_log` " . implode(', ', $alterParts) . ";", __FILE__, __LINE__);
        }
    }

    /**
     * Prüft, ob ein Partner eine bestimmte Benachrichtigungs-Kategorie aktiviert hat.
     * Standardmäßig sind alle Kategorien aus dem Katalog aktiviert (Opt-out Prinzip für Bestandspartner).
     */
    public static function isEnabled(int $merchantId, string $eventKey): bool
    {
        $merchantId = abs($merchantId);
        if ($merchantId === 0) {
            return false;
        }

        self::ensureTablesExist();
        $catalog = self::getEventCatalog();
        $default = isset($catalog[$eventKey]) ? (int)$catalog[$eventKey]['default'] : 1;

        $rs = p4c_query(
            "SELECT `enabled` FROM `merchant_notification_prefs`
             WHERE `merchant_id` = '" . $merchantId . "'
               AND `event_key` = '" . p4c_escape_string($eventKey) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if ($rs && p4c_num_rows($rs) > 0) {
            $row = p4c_fetch_object($rs);
            return abs((int)$row->enabled) === 1;
        }

        return $default === 1;
    }

    /**
     * Liefert alle Benachrichtigungs-Einstellungen eines Partners für die Dashboard-/Einstellungs-Ansicht.
     *
     * @return array<string, array{key: string, title: string, description: string, icon: string, badge: string, enabled: bool}>
     */
    public static function getMerchantPreferences(int $merchantId): array
    {
        $merchantId = abs($merchantId);
        self::ensureTablesExist();

        $catalog = self::getEventCatalog();
        $stored  = [];

        if ($merchantId > 0) {
            $rs = p4c_query(
                "SELECT `event_key`, `enabled`
                 FROM `merchant_notification_prefs`
                 WHERE `merchant_id` = '" . $merchantId . "';",
                __FILE__,
                __LINE__
            );
            if ($rs && p4c_num_rows($rs) > 0) {
                while ($row = p4c_fetch_object($rs)) {
                    $stored[(string)$row->event_key] = (abs((int)$row->enabled) === 1);
                }
            }
        }

        $result = [];
        foreach ($catalog as $key => $meta) {
            $isEnabled = array_key_exists($key, $stored) ? $stored[$key] : ((int)$meta['default'] === 1);
            $result[$key] = [
                'key'         => $key,
                'title'       => $meta['title'],
                'description' => $meta['description'],
                'icon'        => $meta['icon'],
                'badge'       => $meta['badge'],
                'enabled'     => $isEnabled,
                'channels'    => [
                    self::CHANNEL_EMAIL => $isEnabled,
                    self::CHANNEL_PUSH  => false,
                ],
            ];
        }

        return $result;
    }

    /**
     * Alias für getMerchantPreferences() inkl. Kanal-Matrix für das MCP-Modal (#modal-notifications).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getPreferencesMatrix(int $merchantId): array
    {
        return self::getMerchantPreferences($merchantId);
    }

    /**
     * Speichert eine einzelne Einstellung eines Partners.
     * Unterstützt sowohl (int $merchantId, string $eventKey, bool $enabled, string $via = 'mcp')
     * als auch (int $merchantId, string $eventKey, string $channel, bool $enabled, string $via = 'mcp').
     *
     * @param bool|string $channelOrEnabled
     * @param bool|string $enabledOrVia
     */
    public static function setPreference(
        int $merchantId,
        string $eventKey,
        $channelOrEnabled,
        $enabledOrVia = 'mcp',
        string $via = 'mcp'
    ): bool {
        if (is_string($channelOrEnabled)) {
            $channel = strtolower(trim($channelOrEnabled));
            if ($channel !== self::CHANNEL_EMAIL) {
                // Push ist aktuell noch deaktiviert ("In Kürze")
                return true;
            }
            $enabled  = (bool)$enabledOrVia;
            $finalVia = $via;
        } else {
            $enabled  = (bool)$channelOrEnabled;
            $finalVia = is_string($enabledOrVia) ? $enabledOrVia : 'mcp';
        }

        $merchantId = abs($merchantId);
        $catalog    = self::getEventCatalog();
        if ($merchantId === 0 || !isset($catalog[$eventKey])) {
            return false;
        }

        self::ensureTablesExist();
        $enabledInt = $enabled ? 1 : 0;
        $viaClean   = substr(trim($finalVia), 0, 30);

        p4c_query(
            "INSERT INTO `merchant_notification_prefs`
             (`merchant_id`, `event_key`, `enabled`, `updated_at`, `updated_via`)
             VALUES (
                 '" . $merchantId . "',
                 '" . p4c_escape_string($eventKey) . "',
                 '" . $enabledInt . "',
                 NOW(),
                 '" . p4c_escape_string($viaClean) . "'
             )
             ON DUPLICATE KEY UPDATE
                 `enabled`     = '" . $enabledInt . "',
                 `updated_at`  = NOW(),
                 `updated_via` = '" . p4c_escape_string($viaClean) . "';",
            __FILE__,
            __LINE__
        );

        return true;
    }

    /**
     * Speichert mehrere Einstellungen gleichzeitig (z.B. aus dem MCP-Dashboard).
     *
     * @param array<string, mixed> $prefsMap Assoziatives Array [event_key => bool/int]
     */
    public static function saveMerchantPreferences(int $merchantId, array $prefsMap, string $via = 'mcp'): bool
    {
        $merchantId = abs($merchantId);
        if ($merchantId === 0) {
            return false;
        }

        $catalog = self::getEventCatalog();
        foreach ($catalog as $eventKey => $meta) {
            $isEnabled = !empty($prefsMap[$eventKey]);
            self::setPreference($merchantId, $eventKey, $isEnabled, $via);
        }

        return true;
    }

    /**
     * Erzeugt einen kryptografisch signierten (HMAC-SHA256) Abmeldelink für den E-Mail-Footer
     * sowie für den `List-Unsubscribe`-Header (keine Datenbank-Abfrage für die Token-Prüfung nötig,
     * zeitlich unbegrenzt gültig gemäß RFC 8058).
     */
    public static function createUnsubscribeUrl(int $merchantId, string $eventKey = self::EVENT_MOVIE_CONVERTED): string
    {
        $merchantId = abs($merchantId);
        $eventKey   = trim($eventKey);
        $sig        = self::computeHmac($merchantId, $eventKey);

        $mcpBase = defined('MCP_URL') ? rtrim((string)MCP_URL, '/') : 'https://erocloud.net';
        $subPath = (defined('MCP_DIR') && is_dir(MCP_DIR . '/mcp_neu/web')) ? '/mcp_neu/web' : '';

        return $mcpBase . $subPath . '/unsubscribe.php?m=' . $merchantId
            . '&e=' . rawurlencode($eventKey)
            . '&s=' . rawurlencode($sig);
    }

    /**
     * Überprüft die HMAC-Signatur eines Abmeldelinks konstantzeit-sicher.
     */
    public static function verifyUnsubscribeSignature(int $merchantId, string $eventKey, string $signature): bool
    {
        $merchantId = abs($merchantId);
        if ($merchantId === 0 || $eventKey === '' || $signature === '') {
            return false;
        }

        $expected = self::computeHmac($merchantId, $eventKey);
        return hash_equals($expected, $signature);
    }

    /**
     * Prüft, ob für ein bestimmtes Event + Referenz-ID (z.B. Film #1234) bereits erfolgreich
     * eine E-Mail versendet wurde (verhindert doppelte Mails bei erneutem Cronjob-Lauf).
     */
    public static function hasAlreadyBeenSent(int $merchantId, string $eventKey, int $refId): bool
    {
        $merchantId = abs($merchantId);
        $refId      = abs($refId);
        if ($merchantId === 0 || $refId === 0) {
            return false;
        }

        self::ensureTablesExist();
        $rs = p4c_query(
            "SELECT `id` FROM `notification_log`
             WHERE `merchant_id` = '" . $merchantId . "'
               AND `event_key` = '" . p4c_escape_string($eventKey) . "'
               AND `reference_id` = '" . $refId . "'
               AND `status` IN ('sent', 'sandbox_sent')
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        return ($rs && p4c_num_rows($rs) > 0);
    }

    /**
     * Protokolliert jeden E-Mail-Versand (oder Fehler) revisionssicher in `notification_log`.
     */
    public static function logDelivery(
        int $merchantId,
        string $eventKey,
        int $refId,
        string $recipient,
        bool $sandboxMode,
        string $status,
        string $errorMsg = '',
        string $intendedRecipient = '',
        string $subject = '',
        string $triggerDetail = '',
        string $bodyHtml = ''
    ): void {
        self::ensureTablesExist();

        if ($intendedRecipient === '') {
            $intendedRecipient = $recipient;
        }
        if ($triggerDetail === '') {
            $triggerDetail = $eventKey;
        }

        p4c_query(
            "INSERT INTO `notification_log`
             (`merchant_id`, `event_key`, `trigger_detail`, `reference_id`, `recipient`, `intended_recipient`, `subject`, `body_html`, `sandbox_mode`, `status`, `error_message`, `created_at`)
             VALUES (
                 '" . abs($merchantId) . "',
                 '" . p4c_escape_string(substr($eventKey, 0, 50)) . "',
                 '" . p4c_escape_string(substr($triggerDetail, 0, 64)) . "',
                 '" . abs($refId) . "',
                 '" . p4c_escape_string(substr($recipient, 0, 190)) . "',
                 '" . p4c_escape_string(substr($intendedRecipient, 0, 190)) . "',
                 '" . p4c_escape_string(substr($subject, 0, 255)) . "',
                 '" . p4c_escape_string($bodyHtml) . "',
                 '" . ($sandboxMode ? 1 : 0) . "',
                 '" . p4c_escape_string(substr($status, 0, 20)) . "',
                 '" . p4c_escape_string(substr($errorMsg, 0, 500)) . "',
                 NOW()
             );",
            __FILE__,
            __LINE__
        );
    }

    /**
     * Liefert Opt-In / Opt-Out Statistiken pro Benachrichtigungs-Kategorie über alle aktiven Partner.
     *
     * @return array<string, array{total_merchants: int, enabled_count: int, disabled_count: int}>
     */
    public static function getOptInStats(): array
    {
        self::ensureTablesExist();

        $totalMerchants = 0;
        $rsTotal = p4c_query("SELECT COUNT(*) AS `cnt` FROM `merchants`;", __FILE__, __LINE__);
        if ($rsTotal && p4c_num_rows($rsTotal) > 0) {
            $totalMerchants = abs((int)(p4c_fetch_object($rsTotal)->cnt ?? 0));
        }

        $optOutsByKey = [];
        $rsOptOut = p4c_query(
            "SELECT p.`event_key`, COUNT(*) AS `cnt`
             FROM `merchant_notification_prefs` p
             INNER JOIN `merchants` m ON m.`id` = p.`merchant_id`
             WHERE p.`enabled` = 0
             GROUP BY p.`event_key`;",
            __FILE__,
            __LINE__
        );
        if ($rsOptOut && p4c_num_rows($rsOptOut) > 0) {
            while ($r = p4c_fetch_object($rsOptOut)) {
                $optOutsByKey[(string)$r->event_key] = abs((int)$r->cnt);
            }
        }

        $stats = [];
        foreach (self::getEventCatalog() as $key => $meta) {
            $disabled = $optOutsByKey[$key] ?? 0;
            $enabled  = max(0, $totalMerchants - $disabled);
            $stats[$key] = [
                'total_merchants' => $totalMerchants,
                'active'          => $enabled,
                'opted_out'       => $disabled,
                'enabled_count'   => $enabled,
                'disabled_count'  => $disabled,
            ];
        }

        return $stats;
    }

    /**
     * Berechnet den HMAC-SHA256 Schlüssel für Abmeldelinks.
     */
    private static function computeHmac(int $merchantId, string $eventKey): string
    {
        $secret = (defined('AES_KEY') ? (string)AES_KEY : 'erocloud_default_secret') . '|erocloud_unsub_v1';
        $data   = abs($merchantId) . '|' . strtolower(trim($eventKey));
        return hash_hmac('sha256', $data, $secret);
    }
}
