<?php

/**
 * EroCloud Central Magic-Link & Auto-Login Deep-Link System
 * Compatible with PHP 8.0.30 and PHP 8.4.27
 *
 * Sicherheits-Architektur (Defense-in-Depth):
 * 1. Encrypt-then-MAC Token: Payload (Merchant-ID, Ziel-Route, Ablaufzeit, Max-Uses, 128-Bit Nonce)
 *    wird mit AES-256-CBC verschlüsselt und mit HMAC-SHA256 (konstante Zeitprüfung via hash_equals) signiert.
 * 2. Datenbank-Tracking (`auth_magic_links`): Speichert ausschließlich den SHA-256-Hash des Tokens,
 *    zählt die Nutzungen (`use_count` / `max_uses`), erlaubt sofortigen Widerruf (`revoked = 1`)
 *    und protokolliert Zeitstempel sowie IP-Adresse.
 * 3. Striktes Route-Whitelisting: Verhindert Open-Redirect- und XSS-Angriffe.
 * 4. Scanner- & Prefetch-Schutz: E-Mail-Virenscanner (SafeLinks, Gmail, Messenger-Vorschau)
 *    verbrauchen das Token nicht vorzeitig.
 * 5. Sofortige URL-Bereinigung: Nach erfolgreichem Auto-Login (`session_regenerate_id(true)`)
 *    erfolgt ein 302-Redirect mit `Referrer-Policy: no-referrer` auf `#/<zielroute>`, sodass
 *    das Token sofort aus der Browser-Adresszeile und Historie verschwindet.
 */

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

class EroCloudMagicLink
{
    /**
     * Standard-Gültigkeit: 72 Stunden für E-Mails (in Sekunden)
     */
    public const DEFAULT_TTL_EMAIL = 259200;

    /**
     * Standard-Gültigkeit: 24 Stunden für Push-Benachrichtigungen (in Sekunden)
     */
    public const DEFAULT_TTL_PUSH = 86400;

    /**
     * Standard-Maximalnutzungen pro Link (tolerant gegenüber Mail-Apps / In-App-Browser-Wechsel)
     */
    public const DEFAULT_MAX_USES = 5;

    /**
     * Stellt sicher, dass die Tabelle `auth_magic_links` in der Datenbank existiert.
     */
    public static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        p4c_query("CREATE TABLE IF NOT EXISTS `auth_magic_links` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `merchant_id` INT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `target_route` VARCHAR(255) NOT NULL DEFAULT 'startseite',
            `channel` VARCHAR(32) NOT NULL DEFAULT 'email',
            `context` VARCHAR(100) NOT NULL DEFAULT '',
            `max_uses` TINYINT UNSIGNED NOT NULL DEFAULT 5,
            `use_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `revoked` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `last_used_at` DATETIME NULL DEFAULT NULL,
            `last_used_ip` VARCHAR(64) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_token_hash` (`token_hash`),
            KEY `idx_merchant_expires` (`merchant_id`, `expires_at`),
            KEY `idx_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", __FILE__, __LINE__);
    }

    /**
     * Prüft und bereinigt eine interne SPA-Zielroute gegen eine strikte Whitelist.
     * Verhindert Open-Redirect- und XSS-Angriffe über manipulierte Routen.
     */
    public static function sanitizeTargetRoute(string $route): string
    {
        $clean = trim($route);
        $clean = ltrim($clean, '#/ ');

        if ($clean === '' || str_contains($clean, '://') || str_starts_with($clean, '//') || str_contains($clean, "\n") || str_contains($clean, "\r")) {
            return 'startseite';
        }

        $parts     = explode('?', $clean, 2);
        $pathPart  = trim($parts[0]);
        $queryPart = isset($parts[1]) ? trim($parts[1]) : '';

        $allowedRoutes = [
            'startseite',
            'home',
            'movies',
            'movie',
            'movie-upload',
            'photo-albums',
            'photo-album',
            'photo-album-upload',
            'actors',
            'actor',
            'new-actor',
            'actor-new',
            'co-actors',
            'co-actor',
            'preview',
            'webmaster',
        ];

        if (!in_array($pathPart, $allowedRoutes, true)) {
            return 'startseite';
        }

        if ($queryPart === '') {
            return $pathPart;
        }

        parse_str($queryPart, $queryParams);
        $safeParams = [];
        foreach ($queryParams as $key => $val) {
            $safeKey = (string)preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$key);
            if ($safeKey === '' || is_array($val)) {
                continue;
            }
            if ($safeKey === 'id' || str_ends_with($safeKey, '_id')) {
                $intVal = abs((int)$val);
                if ($intVal > 0) {
                    $safeParams[$safeKey] = $intVal;
                }
            } else {
                $safeVal = (string)preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)$val);
                if ($safeVal !== '') {
                    $safeParams[$safeKey] = $safeVal;
                }
            }
        }

        if (empty($safeParams)) {
            return $pathPart;
        }

        return $pathPart . '?' . http_build_query($safeParams);
    }

    /**
     * Erzeugt einen kryptografisch gesicherten Auto-Login-Link für E-Mails, Push-Benachrichtigungen etc.
     *
     * @param int    $merchantId  ID des Creators/Merchants aus `merchants.id`
     * @param string $targetRoute Interne Zielroute im MCP (z.B. 'movie?id=48291' oder 'movies')
     * @param string $channel     Kanal ('email', 'push', 'system', etc.)
     * @param string $context     Fachlicher Kontext (z.B. 'movie_converted:48291')
     * @param int    $ttlSeconds  Gültigkeitsdauer in Sekunden (Standard: 72h)
     * @param int    $maxUses     Maximale Aufrufe innerhalb der Gültigkeit (Standard: 5)
     * @return string Vollständige HTTPS-URL zum direkten Einloggen und Weiterleiten
     */
    public static function createUrl(
        int $merchantId,
        string $targetRoute = 'startseite',
        string $channel = 'email',
        string $context = '',
        int $ttlSeconds = self::DEFAULT_TTL_EMAIL,
        int $maxUses = self::DEFAULT_MAX_USES,
        ?bool $forcePublicDomain = null
    ): string {
        $merchantId  = abs((int)$merchantId);
        $safeRoute   = self::sanitizeTargetRoute($targetRoute);
        $safeChannel = (string)preg_replace('/[^a-z0-9_-]/i', '', strtolower(trim($channel))) ?: 'email';
        $safeContext = mb_substr(trim($context), 0, 100);
        $ttlSeconds  = max(60, min(2592000, abs((int)$ttlSeconds))); // 1 Min. bis max. 30 Tage
        $maxUses     = max(1, min(50, abs((int)$maxUses)));

        $usePublicDomain = $forcePublicDomain ?? ($safeChannel !== 'acp_impersonate');
        $baseUrl = self::getMcpEntryUrl($usePublicDomain);

        if ($merchantId <= 0) {
            return $baseUrl . '#/' . $safeRoute;
        }

        self::ensureTableExists();

        // Gelegentliche Bereinigung abgelaufener Links (10% Wahrscheinlichkeit)
        try {
            if (random_int(1, 10) === 1) {
                self::cleanupExpired();
            }
            $nonce = bin2hex(random_bytes(16)); // 128-Bit Zufalls-Nonce
        } catch (\Throwable $e) {
            $nonce = substr(hash('sha256', uniqid((string)$merchantId, true) . mt_rand() . microtime(true)), 0, 32);
        }

        $nowTs     = time();
        $expTs     = $nowTs + $ttlSeconds;
        $nowStr    = date('Y-m-d H:i:s', $nowTs);
        $expStr    = date('Y-m-d H:i:s', $expTs);

        $payload = [
            'mid' => $merchantId,
            'rte' => $safeRoute,
            'ch'  => $safeChannel,
            'ctx' => $safeContext,
            'exp' => $expTs,
            'max' => $maxUses,
            'rnd' => $nonce,
        ];

        $fullToken = self::sealPayload($payload);

        // In der Datenbank wird NUR der SHA-256 Hash des Tokens gespeichert!
        $tokenHash = hash('sha256', $fullToken);

        p4c_query("INSERT INTO `auth_magic_links` SET
            `merchant_id`  = '" . $merchantId . "',
            `token_hash`   = '" . p4c_escape_string($tokenHash) . "',
            `target_route` = '" . p4c_escape_string($safeRoute) . "',
            `channel`      = '" . p4c_escape_string($safeChannel) . "',
            `context`      = '" . p4c_escape_string($safeContext) . "',
            `max_uses`     = '" . $maxUses . "',
            `use_count`    = 0,
            `revoked`      = 0,
            `created_at`   = '" . $nowStr . "',
            `expires_at`   = '" . $expStr . "';", __FILE__, __LINE__);

        return $baseUrl . '?ml=' . rawurlencode($fullToken);
    }

    /**
     * Komfort-Helper: Erstellt einen Direkt-Login-Link zu einem bestimmten Film (#/movie?id=...).
     */
    public static function forMovie(
        int $merchantId,
        int $movieId,
        string $channel = 'email',
        string $context = 'movie_converted',
        int $ttlSeconds = self::DEFAULT_TTL_EMAIL
    ): string {
        $movieId = abs((int)$movieId);
        $route   = $movieId > 0 ? ('movie?id=' . $movieId) : 'movies';
        $ctx     = $context !== '' ? ($context . ':' . $movieId) : ('movie:' . $movieId);

        return self::createUrl($merchantId, $route, $channel, $ctx, $ttlSeconds);
    }

    /**
     * Komfort-Helper: Erstellt einen Direkt-Login-Link zu einem bestimmten Foto-Album (#/photo-album?id=...).
     */
    public static function forPhotoAlbum(
        int $merchantId,
        int $albumId,
        string $channel = 'email',
        string $context = 'photo_album',
        int $ttlSeconds = self::DEFAULT_TTL_EMAIL
    ): string {
        $albumId = abs((int)$albumId);
        $route   = $albumId > 0 ? ('photo-album?id=' . $albumId) : 'photo-albums';
        $ctx     = $context !== '' ? ($context . ':' . $albumId) : ('album:' . $albumId);

        return self::createUrl($merchantId, $route, $channel, $ctx, $ttlSeconds);
    }

    /**
     * Komfort-Helper: Erstellt einen Direkt-Login-Link zu einem Darsteller-Profil (#/actor?id=...).
     */
    public static function forActor(
        int $merchantId,
        int $actorId,
        string $channel = 'email',
        string $context = 'actor_profile',
        int $ttlSeconds = self::DEFAULT_TTL_EMAIL
    ): string {
        $actorId = abs((int)$actorId);
        $route   = $actorId > 0 ? ('actor?id=' . $actorId) : 'actors';
        $ctx     = $context !== '' ? ($context . ':' . $actorId) : ('actor:' . $actorId);

        return self::createUrl($merchantId, $route, $channel, $ctx, $ttlSeconds);
    }

    /**
     * Prüft, ob der aktuelle Request ein automatischer E-Mail-Scanner / Link-Prefetcher ist,
     * damit Virenscanner (Outlook SafeLinks, Gmail, WhatsApp Preview etc.) das Token nicht verbrauchen.
     */
    public static function isPrefetchOrScannerRequest(): bool
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD' || $method === 'OPTIONS') {
            return true;
        }

        $purpose = strtolower((string)($_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_X_MOZ'] ?? ''));
        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')) {
            return true;
        }

        $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $scannerSignatures = [
            'whatsapp',
            'telegrambot',
            'slackbot',
            'discordbot',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'skypeuripreview',
            'ms-office',
            'microsoft office',
            'safelinks',
            'barracuda',
            'mimecast',
            'proofpoint',
            'googleimageproxy',
        ];

        foreach ($scannerSignatures as $sig) {
            if (str_contains($ua, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verarbeitet einen eingehenden `?ml=...` Request im MCP Entrypoint (`index.php`).
     * Führt bei gültigem Token den sicheren Auto-Login durch und leitet sofort auf die saubere
     * Ziel-Route (`#/movie?id=...`) weiter, sodass das Token aus der Browser-Adresszeile verschwindet.
     */
    public static function handleRequest(string $rawToken): void
    {
        global $mysql;

        $baseUrl  = self::getMcpEntryUrl();
        $rawToken = trim($rawToken);

        // 1. Schutz vor Link-Scannern & Prefetching (Token nicht entwerten!)
        if (self::isPrefetchOrScannerRequest()) {
            header('Referrer-Policy: no-referrer');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            http_response_code(200);
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>EroCloud</title></head><body></body></html>';
            exit;
        }

        // 2. Kryptografische Prüfung & Entschlüsselung (Encrypt-then-MAC)
        $payload = self::unsealPayload($rawToken);
        if (!is_array($payload)) {
            self::redirectInvalid($baseUrl);
        }

        $merchantId  = abs((int)($payload['mid'] ?? 0));
        $targetRoute = self::sanitizeTargetRoute((string)($payload['rte'] ?? 'startseite'));
        $channel     = (string)preg_replace('/[^a-z0-9_-]/i', '', (string)($payload['ch'] ?? 'email')) ?: 'email';
        $context     = mb_substr(trim((string)($payload['ctx'] ?? '')), 0, 100);
        $expiresTs   = abs((int)($payload['exp'] ?? 0));
        $maxUses     = max(1, min(50, abs((int)($payload['max'] ?? self::DEFAULT_MAX_USES))));

        if ($merchantId <= 0 || $expiresTs <= 0) {
            self::redirectInvalid($baseUrl);
        }

        self::ensureTableExists();

        // 3. Datenbank-Prüfung (`auth_magic_links` anhand SHA-256 Token-Hash)
        $tokenHash = hash('sha256', $rawToken);
        $rsLink    = p4c_query("SELECT *
            FROM `auth_magic_links`
            WHERE `token_hash` = '" . p4c_escape_string($tokenHash) . "'
            LIMIT 1;", __FILE__, __LINE__);

        $linkId   = 0;
        $useCount = 0;
        $revoked  = 0;

        if ($rsLink && p4c_num_rows($rsLink) > 0) {
            $linkObj     = p4c_fetch_object($rsLink);
            $linkId      = abs((int)$linkObj->id);
            $merchantId  = abs((int)$linkObj->merchant_id);
            $targetRoute = self::sanitizeTargetRoute((string)$linkObj->target_route);
            $channel     = (string)$linkObj->channel;
            $context     = (string)$linkObj->context;
            $maxUses     = abs((int)$linkObj->max_uses);
            $useCount    = abs((int)$linkObj->use_count);
            $revoked     = abs((int)$linkObj->revoked);
            $dbExpTs     = strtotime((string)$linkObj->expires_at);
            if ($dbExpTs !== false && $dbExpTs > 0) {
                $expiresTs = $dbExpTs;
            }
        }

        // 4. Prüfen, ob Token abgelaufen, widerrufen oder maximale Nutzung erreicht ist
        $isExpiredOrUsed = ($revoked !== 0 || $expiresTs < time() || $useCount >= $maxUses);

        if ($isExpiredOrUsed) {
            // Komfort-Fallback: Ist derselbe Creator im aktuellen Browser bereits regulär eingeloggt,
            // leiten wir ihn trotzdem direkt zur Ziel-Ansicht weiter.
            if (function_exists('is_logged_in') && is_logged_in('mcp') === true && abs((int)($_SESSION['merchant_id'] ?? 0)) === $merchantId) {
                self::redirectToRoute($baseUrl, $targetRoute);
            }
            self::redirectInvalid($baseUrl);
        }

        // 5. Prüfen, ob der Merchant in der Datenbank existiert
        $rsMerchant = p4c_query("SELECT `id`, `partner_id`
            FROM `merchants`
            WHERE `id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMerchant || p4c_num_rows($rsMerchant) === 0) {
            self::redirectInvalid($baseUrl);
        }

        $merchantRow = p4c_fetch_object($rsMerchant);
        $merchant    = new Merchant($mysql, $merchantId);
        $username    = (string)$merchant->username('aes_decrypt');
        $partnerId   = (string)($merchantRow->partner_id ?? $merchant->partner_id());
        $userIp      = function_exists('getUserIP') ? (string)getUserIP() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $now         = date('Y-m-d H:i:s');

        // 6. Nutzungszähler in `auth_magic_links` aktualisieren (oder anlegen, falls aus anderer Umgebung erzeugt)
        if ($linkId > 0) {
            p4c_query("UPDATE `auth_magic_links` SET
                `use_count`    = `use_count` + 1,
                `last_used_at` = '" . $now . "',
                `last_used_ip` = '" . p4c_escape_string($userIp) . "'
                WHERE `id` = '" . $linkId . "' LIMIT 1;", __FILE__, __LINE__);
        } else {
            p4c_query("INSERT INTO `auth_magic_links` SET
                `merchant_id`  = '" . $merchantId . "',
                `token_hash`   = '" . p4c_escape_string($tokenHash) . "',
                `target_route` = '" . p4c_escape_string($targetRoute) . "',
                `channel`      = '" . p4c_escape_string($channel) . "',
                `context`      = '" . p4c_escape_string($context) . "',
                `max_uses`     = '" . $maxUses . "',
                `use_count`    = 1,
                `revoked`      = 0,
                `created_at`   = '" . $now . "',
                `expires_at`   = '" . date('Y-m-d H:i:s', $expiresTs) . "',
                `last_used_at` = '" . $now . "',
                `last_used_ip` = '" . p4c_escape_string($userIp) . "';", __FILE__, __LINE__);
        }

        $isAdminImpersonation = ($channel === 'acp_impersonate');

        // 7. Sichere Session-Initialisierung (Session Fixation Schutz)
        // Bei lokaler Admin-Übernahme auf demselben Host (localhost) bestehende ACP-Session-ID nicht zerstören
        if (!$isAdminImpersonation && session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $_SESSION['initiated']         = true;
        $_SESSION['logged_in_as']      = 'merchant';
        $_SESSION['logged_id']         = 'mcp';
        $_SESSION['merchant_id']       = $merchantId;
        $_SESSION['merchant_username'] = $username;
        $_SESSION['last_activity']     = time();

        if ($isAdminImpersonation) {
            $adminId   = 0;
            $adminName = 'Admin';
            if (str_starts_with($context, 'admin:')) {
                $ctxParts  = explode(':', $context, 3);
                $adminId   = abs((int)($ctxParts[1] ?? 0));
                $adminName = trim((string)($ctxParts[2] ?? 'Admin')) ?: 'Admin';
            }
            $_SESSION['mcp_impersonated_by_admin'] = [
                'id'         => $adminId,
                'username'   => $adminName,
                'started_at' => time(),
            ];
        } else {
            unset($_SESSION['mcp_impersonated_by_admin']);
        }

        // 8. HMAC-gesichertes 3-Stunden-Cookie für mcp_neu synchronisieren
        if (function_exists('mcp_neu_sync_auth_cookie')) {
            mcp_neu_sync_auth_cookie($merchantId, $username);
        }

        // 9. Bestehendes EroCloud-Cookie, letzte IP & Partner-Historie NUR bei echtem Partner-Login aktualisieren
        if (!$isAdminImpersonation) {
            if (!headers_sent() && $partnerId !== '' && defined('MCP_DOMAIN')) {
                $cookiePayload = base64_encode((string)json_encode([
                    'pid' => $partnerId,
                    'sid' => session_id(),
                ]));
                @setcookie('erocloud', $cookiePayload, [
                    'expires'  => strtotime('+3 month'),
                    'path'     => '/',
                    'domain'   => '.' . ltrim((string)MCP_DOMAIN, '.'),
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            if (defined('AES_KEY')) {
                p4c_query("UPDATE `merchants` SET
                    `last_ip`          = AES_ENCRYPT('" . p4c_escape_string($userIp) . "', '" . AES_KEY . "'),
                    `last_ip_datetime` = '" . $now . "'
                    WHERE `id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
            }

            if (function_exists('log_action')) {
                $logLabel = $context !== '' ? "{$channel}: {$context}" : $channel;
                log_action("Am System angemeldet (Direkt-Link / {$logLabel}).");
            }
        }

        // 11. Sauberer Redirect ohne Token in der URL auf die SPA-Zielroute
        self::redirectToRoute($baseUrl, $targetRoute);
    }

    /**
     * Verschlüsselt und signiert ein Payload-Array nach dem Encrypt-then-MAC Verfahren (AES-256-CBC + HMAC-SHA256).
     */
    private static function sealPayload(array $payload): string
    {
        $masterSecret = defined('AES_KEY') ? (string)AES_KEY : 'erocloud_magic_default_secret';
        $encKey       = hash('sha256', 'ec_ml_enc|' . $masterSecret, true); // 32 Bytes
        $macKey       = hash('sha256', 'ec_ml_mac|' . $masterSecret, true); // 32 Bytes

        $json = (string)json_encode($payload, JSON_UNESCAPED_SLASHES);

        try {
            $iv = random_bytes(16);
        } catch (\Throwable $e) {
            $iv = substr(hash('sha256', uniqid('iv', true) . microtime(true), true), 0, 16);
        }

        $ciphertext = openssl_encrypt($json, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            $ciphertext = $json;
        }

        $ivAndCipher = $iv . $ciphertext;
        $mac         = substr(hash_hmac('sha256', $ivAndCipher, $macKey, true), 0, 16); // 128-Bit HMAC Tag

        return rtrim(strtr(base64_encode($mac . $ivAndCipher), '+/', '-_'), '=');
    }

    /**
     * Prüft die HMAC-Signatur in konstanter Zeit und entschlüsselt das Token-Payload.
     */
    private static function unsealPayload(string $token): ?array
    {
        if ($token === '' || strlen($token) < 40 || strlen($token) > 512) {
            return null;
        }

        $b64 = strtr($token, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $raw = @base64_decode($b64, true);
        if (!is_string($raw) || strlen($raw) <= 32) {
            return null;
        }

        $masterSecret = defined('AES_KEY') ? (string)AES_KEY : 'erocloud_magic_default_secret';
        $encKey       = hash('sha256', 'ec_ml_enc|' . $masterSecret, true);
        $macKey       = hash('sha256', 'ec_ml_mac|' . $masterSecret, true);

        $providedMac = substr($raw, 0, 16);
        $ivAndCipher = substr($raw, 16);
        $expectedMac = substr(hash_hmac('sha256', $ivAndCipher, $macKey, true), 0, 16);

        if (!hash_equals($expectedMac, $providedMac)) {
            return null;
        }

        $iv         = substr($ivAndCipher, 0, 16);
        $ciphertext = substr($ivAndCipher, 16);

        $json = openssl_decrypt($ciphertext, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $data = @json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Entwertet alle offenen Magic-Links eines Merchants (z.B. bei Sicherheits-Reset).
     */
    public static function revokeAllForMerchant(int $merchantId): void
    {
        $merchantId = abs((int)$merchantId);
        if ($merchantId <= 0) {
            return;
        }
        self::ensureTableExists();
        p4c_query("UPDATE `auth_magic_links` SET `revoked` = 1 WHERE `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
    }

    /**
     * Löscht abgelaufene Magic-Links, die älter als 14 Tage sind, aus der Datenbank.
     */
    public static function cleanupExpired(): void
    {
        self::ensureTableExists();
        $cutoff = date('Y-m-d H:i:s', time() - (14 * 86400));
        p4c_query("DELETE FROM `auth_magic_links` WHERE `expires_at` < '" . $cutoff . "';", __FILE__, __LINE__);
    }

    /**
     * Ermittelt die Basis-URL des neuen MCP Entrypoints (`index.php`).
     *
     * @param bool $forcePublicDomain Wenn true (z.B. für E-Mail-Links), wird immer `https://erocloud.net`
     *                                statt `.local` genutzt, damit Webmailer (Gmail) den Link nicht blockieren
     *                                und Domain-Alignment zu `noreply@erocloud.net` gewahrt bleibt.
     */
    public static function getMcpEntryUrl(bool $forcePublicDomain = false): string
    {
        if ($forcePublicDomain) {
            $mcpBase = 'https://erocloud.net';
        } else {
            $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
            $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
            if (($host === 'localhost' || str_starts_with($host, 'localhost:') || $host === '127.0.0.1' || str_starts_with($host, '127.0.0.1:'))
                && (str_contains($scriptName, '/acp/') || str_contains($scriptName, '/mcp/'))
            ) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
                $scheme = $isHttps ? 'https' : 'http';
                $pos    = strpos($scriptName, '/acp/');
                if ($pos === false) {
                    $pos = strpos($scriptName, '/mcp/');
                }
                $rootPrefix = $pos !== false ? substr($scriptName, 0, $pos) : '';
                $mcpBase    = $scheme . '://' . $host . $rootPrefix . '/mcp';
            } else {
                $mcpBase = defined('MCP_URL') ? rtrim((string)MCP_URL, '/') : 'https://erocloud.net';
            }
        }

        if (defined('MCP_DIR') && is_dir(MCP_DIR . '/mcp_neu/web')) {
            return $mcpBase . '/mcp_neu/web/index.php';
        }
        return $mcpBase . '/index.php';
    }

    /**
     * Leitet mit No-Referrer-Header zur internen SPA-Route weiter.
     */
    private static function redirectToRoute(string $baseUrl, string $targetRoute): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: ' . $baseUrl . '#/' . ltrim($targetRoute, '#/'));
        exit;
    }

    /**
     * Leitet bei abgelaufenem oder ungültigem Token zum regulären Login weiter.
     */
    private static function redirectInvalid(string $baseUrl): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (function_exists('is_logged_in') && is_logged_in('mcp') === true && !empty($_SESSION['merchant_id'])) {
            header('Location: ' . $baseUrl . '#/startseite');
            exit;
        }

        $loginUrl = defined('LOGIN_URL') ? (LOGIN_URL . '?ref=erocloud') : $baseUrl;
        header('Location: ' . $loginUrl);
        exit;
    }
}
