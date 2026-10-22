<?php

/**
 * EroCloud Central Notification & Subscription Preference Center
 * Compatible with PHP 8.0.30 and PHP 8.4.27
 *
 * Verwaltet zentral und kanalübergreifend (E-Mail, WebPush/Mobile-Push, In-App):
 * 1. `notification_preferences`        – Welcher Creator möchte welches Thema über welchen Kanal erhalten?
 * 2. `notification_log`                – Zentrales Versand-Gedächtnis & Duplikat-Schutz für alle Kanäle
 * 3. `notification_push_subscriptions` – Endgeräte-Registrierungen für WebPush / Smartphone-Push
 */

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

class EroCloudNotificationCenter
{
    public const CHANNEL_EMAIL  = 'email';
    public const CHANNEL_PUSH   = 'push';
    public const CHANNEL_IN_APP = 'in_app';

    public const EVENT_MOVIE_CONVERTED  = 'movie_converted';
    public const EVENT_CONTENT_REVIEWED = 'content_reviewed';
    public const EVENT_NEWSLETTER       = 'newsletter';

    /**
     * Zentraler Katalog aller Benachrichtigungs-Themen (Events) inkl. Standard-Werten pro Kanal.
     * Neue Themen können hier jederzeit ohne Datenbank-Migration ergänzt werden.
     *
     * @return array<string, array{title: string, description: string, badge: string, icon: string, defaults: array<string, int>}>
     */
    public static function getEventCatalog(): array
    {
        return [
            self::EVENT_MOVIE_CONVERTED => [
                'title'       => 'Video fertig konvertiert & Vorschaubilder bereit',
                'description' => 'Informiert dich sofort nach Abschluss der HD-Konvertierung mit einem persönlichen Direkt-Link, sobald die 10 Szenen-Vorschaubilder für deinen Film im Cover-Studio bereitstehen.',
                'badge'       => 'Studio & Upload',
                'icon'        => 'bi-film',
                'defaults'    => [
                    self::CHANNEL_EMAIL => 1,
                    self::CHANNEL_PUSH  => 0,
                ],
            ],
            self::EVENT_CONTENT_REVIEWED => [
                'title'       => 'Prüfungs-Status (Filme, Foto-Alben & Darsteller)',
                'description' => 'Informiert dich, sobald deine eingereichten Filme, Foto-Alben oder Darsteller-Profile von unserem Team freigegeben wurden oder falls eine Rückfrage bzw. Korrektur nötig ist.',
                'badge'       => 'Freigabe & Status',
                'icon'        => 'bi-patch-check',
                'defaults'    => [
                    self::CHANNEL_EMAIL => 1,
                    self::CHANNEL_PUSH  => 0,
                ],
            ],
            self::EVENT_NEWSLETTER => [
                'title'       => 'EroCloud Creator-News & Updates (Newsletter)',
                'description' => 'Wichtige Neuigkeiten für Creator, neue Studio-Funktionen, Tipps für optimale Darstellung und Plattform-Ankündigungen.',
                'badge'       => 'Creator-News',
                'icon'        => 'bi-megaphone',
                'defaults'    => [
                    self::CHANNEL_EMAIL => 1,
                    self::CHANNEL_PUSH  => 0,
                ],
            ],
        ];
    }

    /**
     * Katalog der unterstützten Kanäle.
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public static function getChannelCatalog(): array
    {
        return [
            self::CHANNEL_EMAIL => [
                'label' => 'E-Mail',
                'icon'  => 'bi-envelope-check',
            ],
            self::CHANNEL_PUSH => [
                'label' => 'Push-Nachricht',
                'icon'  => 'bi-bell',
            ],
        ];
    }

    /**
     * Stellt sicher, dass die 3 zentralen Tabellen (`notification_preferences`, `notification_log`,
     * `notification_push_subscriptions`) sowie die Film-Option in der Datenbank existieren.
     */
    public static function ensureTablesExist(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        // 1. Tabelle: notification_preferences (Opt-In / Opt-Out Matrix pro Creator, Kanal & Event)
        p4c_query("CREATE TABLE IF NOT EXISTS `notification_preferences` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `merchant_id` INT UNSIGNED NOT NULL,
            `channel` VARCHAR(20) NOT NULL DEFAULT 'email',
            `event_key` VARCHAR(60) NOT NULL,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `updated_via` VARCHAR(32) NOT NULL DEFAULT 'system',
            `updated_ip` VARCHAR(64) NULL DEFAULT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_merchant_channel_event` (`merchant_id`, `channel`, `event_key`),
            KEY `idx_channel_event_enabled` (`channel`, `event_key`, `is_enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", __FILE__, __LINE__);

        // 2. Tabelle: notification_log (Zentrales Versand-Protokoll & Duplikat-Schutz)
        p4c_query("CREATE TABLE IF NOT EXISTS `notification_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `merchant_id` INT UNSIGNED NOT NULL,
            `channel` VARCHAR(20) NOT NULL DEFAULT 'email',
            `event_key` VARCHAR(60) NOT NULL,
            `reference_type` VARCHAR(32) NOT NULL DEFAULT '',
            `reference_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `recipient` VARCHAR(190) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
            `message_id` VARCHAR(190) NOT NULL DEFAULT '',
            `error_message` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ref_lookup` (`merchant_id`, `channel`, `event_key`, `reference_type`, `reference_id`, `status`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", __FILE__, __LINE__);

        // 3. Tabelle: notification_push_subscriptions (WebPush / Mobile Push Endgeräte)
        p4c_query("CREATE TABLE IF NOT EXISTS `notification_push_subscriptions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `merchant_id` INT UNSIGNED NOT NULL,
            `endpoint_hash` CHAR(64) NOT NULL,
            `endpoint` TEXT NOT NULL,
            `p256dh_key` VARCHAR(255) NOT NULL DEFAULT '',
            `auth_token` VARCHAR(255) NOT NULL DEFAULT '',
            `device_label` VARCHAR(120) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `last_used_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_endpoint_hash` (`endpoint_hash`),
            KEY `idx_merchant_active` (`merchant_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", __FILE__, __LINE__);
    }

    /**
     * Prüft, ob ein Creator für ein bestimmtes Thema (`$eventKey`) auf einem Kanal (`$channel`)
     * Benachrichtigungen erhalten möchte. Greift auf den Katalog-Standard zurück, falls noch kein Eintrag existiert.
     */
    public static function isEnabled(
        int $merchantId,
        string $eventKey = self::EVENT_MOVIE_CONVERTED,
        string $channel = self::CHANNEL_EMAIL
    ): bool {
        $merchantId = abs((int)$merchantId);
        $catalog    = self::getEventCatalog();
        $defaultVal = isset($catalog[$eventKey]['defaults'][$channel])
            ? (int)$catalog[$eventKey]['defaults'][$channel]
            : 1;

        if ($merchantId <= 0) {
            return $defaultVal === 1;
        }

        self::ensureTablesExist();

        $rs = p4c_query("SELECT `is_enabled`
            FROM `notification_preferences`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `channel` = '" . p4c_escape_string($channel) . "'
              AND `event_key` = '" . p4c_escape_string($eventKey) . "'
            LIMIT 1;", __FILE__, __LINE__);

        if ($rs && p4c_num_rows($rs) > 0) {
            $row = p4c_fetch_object($rs);
            return abs((int)$row->is_enabled) === 1;
        }

        return $defaultVal === 1;
    }

    /**
     * Speichert die Einstellung eines Creators für ein bestimmtes Thema und einen Kanal (inkl. DSGVO-IP & Quelle).
     */
    public static function setPreference(
        int $merchantId,
        string $eventKey,
        string $channel,
        bool $enabled,
        string $updatedVia = 'mcp_settings'
    ): void {
        $merchantId = abs((int)$merchantId);
        if ($merchantId <= 0) {
            return;
        }

        $catalog  = self::getEventCatalog();
        $channels = self::getChannelCatalog();
        if (!isset($catalog[$eventKey]) || !isset($channels[$channel])) {
            return;
        }

        self::ensureTablesExist();

        $flag    = $enabled ? 1 : 0;
        $safeVia = mb_substr((string)preg_replace('/[^a-zA-Z0-9_-]/', '', $updatedVia), 0, 32) ?: 'system';
        $userIp  = function_exists('getUserIP') ? (string)getUserIP() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $now     = date('Y-m-d H:i:s');

        p4c_query("INSERT INTO `notification_preferences` SET
            `merchant_id` = '" . $merchantId . "',
            `channel`     = '" . p4c_escape_string($channel) . "',
            `event_key`   = '" . p4c_escape_string($eventKey) . "',
            `is_enabled`  = '" . $flag . "',
            `updated_via` = '" . p4c_escape_string($safeVia) . "',
            `updated_ip`  = '" . p4c_escape_string($userIp) . "',
            `updated_at`  = '" . $now . "'
            ON DUPLICATE KEY UPDATE
            `is_enabled`  = '" . $flag . "',
            `updated_via` = '" . p4c_escape_string($safeVia) . "',
            `updated_ip`  = '" . p4c_escape_string($userIp) . "',
            `updated_at`  = '" . $now . "';", __FILE__, __LINE__);

        // Abwärtskompatible Synchronisation mit merchants.notify_movie_converted
        if ($channel === self::CHANNEL_EMAIL && $eventKey === self::EVENT_MOVIE_CONVERTED) {
            $rsCol = p4c_query("SHOW COLUMNS FROM `merchants` LIKE 'notify_movie_converted';", __FILE__, __LINE__);
            if ($rsCol && p4c_num_rows($rsCol) > 0) {
                p4c_query("UPDATE `merchants` SET `notify_movie_converted` = '" . $flag . "' WHERE `id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
            }
        }
    }

    /**
     * Deaktiviert auf einen Schlag alle E-Mail-Benachrichtigungen für einen Creator.
     */
    public static function unsubscribeAllEmail(int $merchantId, string $updatedVia = 'one_click_unsubscribe_all'): void
    {
        $merchantId = abs((int)$merchantId);
        if ($merchantId <= 0) {
            return;
        }

        foreach (array_keys(self::getEventCatalog()) as $eventKey) {
            self::setPreference($merchantId, $eventKey, self::CHANNEL_EMAIL, false, $updatedVia);
        }
    }

    /**
     * Liefert die vollständige Einstellungs-Matrix eines Creators für alle Themen und Kanäle
     * (z.B. für das Benachrichtigungs-Modal im MCP oder die 1-Klick-Abmeldeseite).
     *
     * @return array<string, array{title: string, description: string, badge: string, icon: string, channels: array<string, bool>}>
     */
    public static function getPreferencesMatrix(int $merchantId): array
    {
        $merchantId = abs((int)$merchantId);
        $catalog    = self::getEventCatalog();
        $channels   = self::getChannelCatalog();
        $matrix     = [];

        foreach ($catalog as $eventKey => $meta) {
            $chState = [];
            foreach (array_keys($channels) as $chKey) {
                $chState[$chKey] = isset($meta['defaults'][$chKey]) && (int)$meta['defaults'][$chKey] === 1;
            }
            $matrix[$eventKey] = [
                'title'       => $meta['title'],
                'description' => $meta['description'],
                'badge'       => $meta['badge'],
                'icon'        => $meta['icon'],
                'channels'    => $chState,
            ];
        }

        if ($merchantId <= 0) {
            return $matrix;
        }

        self::ensureTablesExist();

        $rs = p4c_query("SELECT `channel`, `event_key`, `is_enabled`
            FROM `notification_preferences`
            WHERE `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        if ($rs && p4c_num_rows($rs) > 0) {
            while ($row = p4c_fetch_object($rs)) {
                $ev = (string)$row->event_key;
                $ch = (string)$row->channel;
                if (isset($matrix[$ev]['channels'][$ch])) {
                    $matrix[$ev]['channels'][$ch] = (abs((int)$row->is_enabled) === 1);
                }
            }
        }

        return $matrix;
    }

    /**
     * Prüft im zentralen `notification_log`, ob für ein bestimmtes Referenz-Objekt (z.B. 'movie' #8482)
     * bereits erfolgreich eine Benachrichtigung versendet wurde (Duplikat-Schutz).
     */
    public static function hasBeenSent(
        int $merchantId,
        string $eventKey,
        string $channel,
        string $referenceType,
        int $referenceId
    ): bool {
        $merchantId  = abs((int)$merchantId);
        $referenceId = abs((int)$referenceId);
        if ($merchantId <= 0 || $referenceId <= 0 || $referenceType === '') {
            return false;
        }

        self::ensureTablesExist();

        $rs = p4c_query("SELECT `id`
            FROM `notification_log`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `channel` = '" . p4c_escape_string($channel) . "'
              AND `event_key` = '" . p4c_escape_string($eventKey) . "'
              AND `reference_type` = '" . p4c_escape_string($referenceType) . "'
              AND `reference_id` = '" . $referenceId . "'
              AND `status` IN ('sent', 'sandbox')
            LIMIT 1;", __FILE__, __LINE__);

        return ($rs && p4c_num_rows($rs) > 0);
    }

    /**
     * Protokolliert eine versendete (oder fehlgeschlagene) Benachrichtigung im zentralen `notification_log`.
     */
    public static function logDelivery(
        int $merchantId,
        string $eventKey,
        string $channel,
        string $referenceType,
        int $referenceId,
        string $recipient,
        string $status = 'sent',
        string $messageId = '',
        string $errorMessage = ''
    ): void {
        self::ensureTablesExist();

        $merchantId  = abs((int)$merchantId);
        $referenceId = abs((int)$referenceId);
        $now         = date('Y-m-d H:i:s');

        p4c_query("INSERT INTO `notification_log` SET
            `merchant_id`    = '" . $merchantId . "',
            `channel`        = '" . p4c_escape_string(mb_substr($channel, 0, 20)) . "',
            `event_key`      = '" . p4c_escape_string(mb_substr($eventKey, 0, 60)) . "',
            `reference_type` = '" . p4c_escape_string(mb_substr($referenceType, 0, 32)) . "',
            `reference_id`   = '" . $referenceId . "',
            `recipient`      = '" . p4c_escape_string(mb_substr($recipient, 0, 190)) . "',
            `status`         = '" . p4c_escape_string(mb_substr($status, 0, 20)) . "',
            `message_id`     = '" . p4c_escape_string(mb_substr($messageId, 0, 190)) . "',
            `error_message`  = " . ($errorMessage !== '' ? ("'" . p4c_escape_string($errorMessage) . "'") : "NULL") . ",
            `created_at`     = '" . $now . "';", __FILE__, __LINE__);
    }

    /**
     * Registriert oder aktualisiert ein WebPush-/Smartphone-Push-Endgerät für einen Creator.
     */
    public static function registerPushSubscription(
        int $merchantId,
        string $endpoint,
        string $p256dhKey,
        string $authToken,
        string $deviceLabel = ''
    ): bool {
        $merchantId = abs((int)$merchantId);
        $endpoint   = trim($endpoint);
        if ($merchantId <= 0 || $endpoint === '') {
            return false;
        }

        self::ensureTablesExist();

        $endpointHash = hash('sha256', $endpoint);
        $userAgent    = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $deviceLabel  = mb_substr(trim($deviceLabel), 0, 120);
        $now          = date('Y-m-d H:i:s');

        p4c_query("INSERT INTO `notification_push_subscriptions` SET
            `merchant_id`   = '" . $merchantId . "',
            `endpoint_hash` = '" . p4c_escape_string($endpointHash) . "',
            `endpoint`      = '" . p4c_escape_string($endpoint) . "',
            `p256dh_key`    = '" . p4c_escape_string(mb_substr($p256dhKey, 0, 255)) . "',
            `auth_token`    = '" . p4c_escape_string(mb_substr($authToken, 0, 255)) . "',
            `device_label`  = '" . p4c_escape_string($deviceLabel) . "',
            `user_agent`    = '" . p4c_escape_string($userAgent) . "',
            `is_active`     = 1,
            `created_at`    = '" . $now . "',
            `last_used_at`  = '" . $now . "'
            ON DUPLICATE KEY UPDATE
            `merchant_id`   = '" . $merchantId . "',
            `p256dh_key`    = '" . p4c_escape_string(mb_substr($p256dhKey, 0, 255)) . "',
            `auth_token`    = '" . p4c_escape_string(mb_substr($authToken, 0, 255)) . "',
            `device_label`  = '" . p4c_escape_string($deviceLabel) . "',
            `user_agent`    = '" . p4c_escape_string($userAgent) . "',
            `is_active`     = 1,
            `last_used_at`  = '" . $now . "';", __FILE__, __LINE__);

        return true;
    }

    /**
     * Deaktiviert ein Push-Endgerät anhand seines Endpoints.
     */
    public static function removePushSubscription(int $merchantId, string $endpoint): void
    {
        $merchantId = abs((int)$merchantId);
        $endpoint   = trim($endpoint);
        if ($merchantId <= 0 || $endpoint === '') {
            return;
        }

        self::ensureTablesExist();
        $endpointHash = hash('sha256', $endpoint);

        p4c_query("UPDATE `notification_push_subscriptions`
            SET `is_active` = 0
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `endpoint_hash` = '" . p4c_escape_string($endpointHash) . "'
            LIMIT 1;", __FILE__, __LINE__);
    }
}
