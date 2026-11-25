<?php

declare(strict_types=1);

/**
 * Zentrale Pay4Coins -> EroCloud Site-Synchronisation und Content-/Kategorien-Policy.
 *
 * Synchronisiert aktive Shops inkl. Pay4Coins Shop-Kategorien (`p4c_shop_categories`)
 * sowie Zahlungsarten-Status (`has_creditcard`, `has_paysafecard`) und liefert die
 * zentrale Freigabe-Logik fuer Sonderkategorien (`poppers`, `kaviar`, `golden_showers`,
 * `breath_control`) im MCP und ACP.
 *
 * Kompatibel mit PHP 8.0.30 & PHP 8.4.28+.
 */

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

if (!defined('EC_P4C_SHOP_CAT_BDSM')) {
    define('EC_P4C_SHOP_CAT_BDSM', 1);
}
if (!defined('EC_P4C_SHOP_CAT_ADULT')) {
    define('EC_P4C_SHOP_CAT_ADULT', 2);
}
if (!defined('EC_P4C_SHOP_CAT_KV_NS_SHIPPING')) {
    define('EC_P4C_SHOP_CAT_KV_NS_SHIPPING', 3);
}
if (!defined('EC_P4C_SHOP_CAT_KV_NS_DIGITAL')) {
    define('EC_P4C_SHOP_CAT_KV_NS_DIGITAL', 4);
}
if (!defined('EC_P4C_SHOP_CAT_ESCORT_ADS')) {
    define('EC_P4C_SHOP_CAT_ESCORT_ADS', 5);
}
if (!defined('EC_P4C_SHOP_CAT_POPPERS')) {
    define('EC_P4C_SHOP_CAT_POPPERS', 6);
}

/**
 * Standard-Label-Mapping der Pay4Coins-Kategorie-IDs (Fallback falls historisches Label leer ist).
 *
 * @return array<int, string>
 */
function ec_p4c_default_shop_category_labels(): array
{
    return [
        EC_P4C_SHOP_CAT_BDSM           => 'BDSM',
        EC_P4C_SHOP_CAT_ADULT          => 'Adult (Erotik / Porno)',
        EC_P4C_SHOP_CAT_KV_NS_SHIPPING => 'KV, NS, Speichel (Versand)',
        EC_P4C_SHOP_CAT_KV_NS_DIGITAL  => 'KV, NS (Digital)',
        EC_P4C_SHOP_CAT_ESCORT_ADS     => 'Escort Ads',
        EC_P4C_SHOP_CAT_POPPERS        => 'Poppers',
    ];
}

/**
 * Stellt sicher, dass Strings aus Legacy-Datenbanken sauber UTF-8-codiert vorliegen.
 */
function ec_p4c_to_utf8(?string $str): string
{
    $val = (string)($str ?? '');
    if ($val === '') {
        return '';
    }
    if (!mb_check_encoding($val, 'UTF-8')) {
        $converted = @mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
        if (is_string($converted)) {
            return $converted;
        }
    }
    return $val;
}

/**
 * Normalisiert einen Domainnamen (kleingeschrieben, ohne Protokoll/Trailing-Slash).
 */
function ec_p4c_normalize_domain(string $domain): string
{
    $d = trim(ec_p4c_to_utf8($domain));
    if ($d === '') {
        return '';
    }
    $d = (string)preg_replace('#^https?://#i', '', $d);
    $slashPos = strpos($d, '/');
    if ($slashPos !== false) {
        $d = substr($d, 0, $slashPos);
    }
    return strtolower(trim($d));
}

/**
 * Selbstheilende Schema-Absicherung fuer die Tabelle `sites`.
 */
function ec_p4c_sites_ensure_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    p4c_query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", __FILE__, __LINE__);

    $rsCols = p4c_query("SHOW COLUMNS FROM `sites`;", __FILE__, __LINE__);
    if (!$rsCols) {
        return;
    }

    $existingCols = [];
    while ($colRow = p4c_fetch_array($rsCols)) {
        $field = (string)($colRow['Field'] ?? ($colRow['field'] ?? ''));
        if ($field !== '') {
            $existingCols[strtolower($field)] = true;
        }
    }

    if (!isset($existingCols['p4c_shop_categories'])) {
        p4c_query(
            "ALTER TABLE `sites` ADD COLUMN `p4c_shop_categories` TEXT NULL DEFAULT NULL;",
            __FILE__,
            __LINE__
        );
    }
    if (!isset($existingCols['has_creditcard'])) {
        p4c_query(
            "ALTER TABLE `sites` ADD COLUMN `has_creditcard` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;",
            __FILE__,
            __LINE__
        );
    }
    if (!isset($existingCols['has_paysafecard'])) {
        p4c_query(
            "ALTER TABLE `sites` ADD COLUMN `has_paysafecard` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;",
            __FILE__,
            __LINE__
        );
    }

    $ensured = true;
}

/**
 * Normalisiert empfangene `shop_categories` (Array oder JSON-String) fuer die Speicherung in `sites`.
 * Liefert `null`, falls kein gueltiger Kategorien-Wert vorhanden ist; `'{}'` bei einer explizit leeren Map.
 *
 * @param mixed $raw
 */
function ec_p4c_normalize_shop_categories_input($raw): ?string
{
    if ($raw === null) {
        return null;
    }

    $decoded = null;
    if (is_array($raw)) {
        $decoded = $raw;
    } elseif (is_string($raw)) {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '{}';
        }
        $decoded = @json_decode($trimmed, true);
        if (!is_array($decoded)) {
            $decoded = @json_decode(ec_p4c_to_utf8($trimmed), true);
        }
    }

    if (!is_array($decoded)) {
        return null;
    }

    $defaults = ec_p4c_default_shop_category_labels();
    $map      = [];

    foreach ($decoded as $cid => $cname) {
        $idInt = (int)$cid;
        if ($idInt <= 0) {
            continue;
        }
        $label = trim(ec_p4c_to_utf8((string)$cname));
        if ($label === '' && isset($defaults[$idInt])) {
            $label = $defaults[$idInt];
        }
        $map[(string)$idInt] = $label;
    }

    if (empty($map)) {
        return '{}';
    }

    ksort($map, SORT_NATURAL);
    $encoded = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : '{}';
}

/**
 * Fuegt eine Pay4Coins-Site ein oder aktualisiert ihren Status, Domain, Kategorien und Zahlungsarten.
 *
 * @param array<string, mixed> $siteAry
 * @return array{action: string, renamed: bool, shop_id: int, domain: string}
 */
function ec_p4c_upsert_site(string $domain, array $siteAry, string $now = ''): array
{
    ec_p4c_sites_ensure_schema();

    $domainClean = trim(ec_p4c_to_utf8($domain !== '' ? $domain : (string)($siteAry['domain'] ?? '')));
    $shopId      = abs((int)($siteAry['site_id'] ?? 0));

    if ($shopId <= 0 || $domainClean === '') {
        return [
            'action'  => 'skipped',
            'renamed' => false,
            'shop_id' => $shopId,
            'domain'  => $domainClean,
        ];
    }

    $nowStr       = $now !== '' ? $now : date('Y-m-d H:i:s');
    $partnerId    = trim(ec_p4c_to_utf8((string)($siteAry['partner_id'] ?? '')));
    $eroadsUrl    = trim(ec_p4c_to_utf8((string)($siteAry['eroads_url'] ?? '')));
    $blocked      = abs((int)($siteAry['blocked'] ?? 0));
    $eroadsActive = abs((int)($siteAry['is_eroads_active'] ?? 0)) === 1 ? 1 : 0;
    $wmCommission = abs((float)($siteAry['webmaster_commision'] ?? 0));
    $numArticles  = abs((int)($siteAry['number_of_articles'] ?? 0));
    $numMovies    = abs((int)($siteAry['number_of_movies'] ?? 0));
    $numAlbums    = abs((int)($siteAry['number_of_photoalbums'] ?? 0));
    $shopSystem   = trim(ec_p4c_to_utf8((string)($siteAry['shop_system'] ?? 'own')));

    $hasShopCatsKey = array_key_exists('shop_categories', $siteAry);
    $normShopCats   = $hasShopCatsKey ? ec_p4c_normalize_shop_categories_input($siteAry['shop_categories']) : null;
    $hasCcKey       = array_key_exists('has_creditcard', $siteAry);
    $hasCreditcard  = abs((int)($siteAry['has_creditcard'] ?? 0)) === 1 ? 1 : 0;
    $hasPscKey      = array_key_exists('has_paysafecard', $siteAry);
    $hasPaysafecard = abs((int)($siteAry['has_paysafecard'] ?? 0)) === 1 ? 1 : 0;

    $rsExist = p4c_query(
        "SELECT `id`, `domain` FROM `sites` WHERE `p4c_shop_id` = '" . $shopId . "' LIMIT 1;",
        __FILE__,
        __LINE__
    );
    $existingObj = ($rsExist && p4c_num_rows($rsExist) > 0) ? p4c_fetch_object($rsExist) : null;

    $action  = 'skipped';
    $renamed = false;

    $extraSqlParts = [];
    if ($normShopCats !== null) {
        $extraSqlParts[] = "`p4c_shop_categories` = '" . p4c_escape_string($normShopCats) . "'";
    }
    if ($hasCcKey) {
        $extraSqlParts[] = "`has_creditcard` = '" . $hasCreditcard . "'";
    }
    if ($hasPscKey) {
        $extraSqlParts[] = "`has_paysafecard` = '" . $hasPaysafecard . "'";
    }
    $extraSqlComma = !empty($extraSqlParts) ? (', ' . implode(', ', $extraSqlParts)) : '';

    if ($blocked === 0) {
        if ($existingObj === null) {
            $catsInsertSql = ($normShopCats !== null)
                ? "'" . p4c_escape_string($normShopCats) . "'"
                : 'NULL';

            p4c_query(
                "INSERT INTO `sites` SET
                    `p4c_shop_id`           = '" . $shopId . "',
                    `partner_id`            = '" . p4c_escape_string($partnerId) . "',
                    `domain`                = '" . p4c_escape_string($domainClean) . "',
                    `eroads_url`            = '" . p4c_escape_string($eroadsUrl) . "',
                    `status`                = '1',
                    `is_eroads_active`      = '" . $eroadsActive . "',
                    `webmaster_commision`   = '" . $wmCommission . "',
                    `number_of_articles`    = '" . $numArticles . "',
                    `number_of_movies`      = '" . $numMovies . "',
                    `number_of_photoalbums` = '" . $numAlbums . "',
                    `last_update_timestamp` = '" . p4c_escape_string($nowStr) . "',
                    `shop_system`           = '" . p4c_escape_string($shopSystem) . "',
                    `p4c_shop_categories`   = " . $catsInsertSql . ",
                    `has_creditcard`        = '" . $hasCreditcard . "',
                    `has_paysafecard`       = '" . $hasPaysafecard . "';",
                __FILE__,
                __LINE__
            );
            $action = 'inserted';
        } else {
            p4c_query(
                "UPDATE `sites` SET
                    `partner_id`            = '" . p4c_escape_string($partnerId) . "',
                    `eroads_url`            = '" . p4c_escape_string($eroadsUrl) . "',
                    `status`                = '1',
                    `is_eroads_active`      = '" . $eroadsActive . "',
                    `webmaster_commision`   = '" . $wmCommission . "',
                    `number_of_articles`    = '" . $numArticles . "',
                    `number_of_movies`      = '" . $numMovies . "',
                    `number_of_photoalbums` = '" . $numAlbums . "',
                    `last_update_timestamp` = '" . p4c_escape_string($nowStr) . "',
                    `shop_system`           = '" . p4c_escape_string($shopSystem) . "'"
                    . $extraSqlComma . "
                 WHERE `p4c_shop_id` = '" . $shopId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            $action = 'updated';
        }
    } elseif ($existingObj !== null) {
        p4c_query(
            "UPDATE `sites` SET
                `partner_id`            = '" . p4c_escape_string($partnerId) . "',
                `eroads_url`            = '" . p4c_escape_string($eroadsUrl) . "',
                `status`                = '0',
                `is_eroads_active`      = '0',
                `webmaster_commision`   = '" . $wmCommission . "',
                `number_of_articles`    = '" . $numArticles . "',
                `number_of_movies`      = '" . $numMovies . "',
                `number_of_photoalbums` = '" . $numAlbums . "',
                `last_update_timestamp` = '" . p4c_escape_string($nowStr) . "',
                `shop_system`           = '" . p4c_escape_string($shopSystem) . "'"
                . $extraSqlComma . "
             WHERE `p4c_shop_id` = '" . $shopId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        $action = 'deactivated';

        $domEsc = p4c_escape_string($domainClean);
        $rsTrk  = p4c_query(
            "SELECT `id`, `url_id` FROM `user_tracking_sites`
             WHERE `url` LIKE 'https://" . $domEsc . "%' OR `url` LIKE 'http://" . $domEsc . "%';",
            __FILE__,
            __LINE__
        );
        if ($rsTrk && p4c_num_rows($rsTrk) > 0) {
            while ($trkObj = p4c_fetch_object($rsTrk)) {
                p4c_query(
                    "DELETE FROM `user_tracking_website_access` WHERE `url_id` = '" . p4c_escape_string((string)$trkObj->url_id) . "';",
                    __FILE__,
                    __LINE__
                );
                p4c_query(
                    "DELETE FROM `user_tracking_sites` WHERE `id` = '" . abs((int)$trkObj->id) . "';",
                    __FILE__,
                    __LINE__
                );
            }
        }
    }

    if ($existingObj !== null && trim((string)$existingObj->domain) !== $domainClean) {
        $domEsc = p4c_escape_string($domainClean);
        p4c_query("UPDATE `sites` SET `domain` = '" . $domEsc . "' WHERE `p4c_shop_id` = '" . $shopId . "' LIMIT 1;", __FILE__, __LINE__);
        p4c_query("UPDATE `actor_member_info` SET `domain` = '" . $domEsc . "' WHERE `p4c_shop_id` = '" . $shopId . "';", __FILE__, __LINE__);
        p4c_query("UPDATE `messenger_sync` SET `domain` = '" . $domEsc . "' WHERE `p4c_shop_id` = '" . $shopId . "';", __FILE__, __LINE__);
        $renamed = true;
    }

    return [
        'action'  => $action,
        'renamed' => $renamed,
        'shop_id' => $shopId,
        'domain'  => $domainClean,
    ];
}

/**
 * Synchronisiert alle Webseiten ueber `api.pay4coins.com/remote/index.php` (`get_sites`).
 * Wird sowohl vom ACP-Button (#/sites) als auch vom Cronjob `cronjobs/sync_p4c_sites.php` genutzt.
 *
 * @return array<string, mixed>
 */
function ec_p4c_sync_all_sites(): array
{
    ec_p4c_sites_ensure_schema();

    $apikey           = '';
    $legacyConfigFile = defined('SOURCEDIR')
        ? SOURCEDIR . '/acp/includes/get_p4c_data/config.inc.php'
        : dirname(__DIR__) . '/acp/includes/get_p4c_data/config.inc.php';
    if ($legacyConfigFile !== '' && is_file($legacyConfigFile)) {
        include $legacyConfigFile;
    }
    if ($apikey === '') {
        $apikey = '7RWV9xXfUbD2v2Mwa2eXa4aw2TS7gErw';
    }

    $apiUrl = (defined('Pay4Coins_API_URL') ? rtrim((string)Pay4Coins_API_URL, '/') : 'https://api.pay4coins.com') . '/remote/index.php';
    $ua     = (defined('PROJECTNAME') ? (string)PROJECTNAME : 'EroCloud')
        . ' - ' . (defined('COMPANYNAME') ? (string)COMPANYNAME : 'CIPA Media')
        . ' - ' . (defined('URL') ? (string)URL : '');

    if (!function_exists('curl_init')) {
        return [
            'ok'          => false,
            'http_status' => 500,
            'error'       => 'Die PHP cURL-Erweiterung ist auf diesem System nicht verfügbar.',
        ];
    }

    $ch = curl_init();
    if ($ch === false) {
        return [
            'ok'          => false,
            'http_status' => 500,
            'error'       => 'cURL konnte nicht initialisiert werden.',
        ];
    }

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'api_name=get_sites&api_key=' . rawurlencode((string)$apikey));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);

    $rawResponse = curl_exec($ch);
    $curlErr     = curl_error($ch);
    $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if (!is_string($rawResponse) || trim($rawResponse) === '') {
        return [
            'ok'          => false,
            'http_status' => 502,
            'error'       => 'Die Pay4Coins-API (' . $apiUrl . ') ist von diesem System aus aktuell nicht erreichbar'
                . ($curlErr !== '' ? ': ' . $curlErr : ' (HTTP ' . $httpCode . ').'),
        ];
    }

    $sitesAry = json_decode($rawResponse, true);
    if (!is_array($sitesAry)) {
        return [
            'ok'          => false,
            'http_status' => 502,
            'error'       => 'Ungültige JSON-Antwort von der Pay4Coins-API erhalten.',
        ];
    }

    $inserted    = 0;
    $updated     = 0;
    $deactivated = 0;
    $renamed     = 0;
    $total       = 0;
    $now         = date('Y-m-d H:i:s');

    foreach ($sitesAry as $domainKey => $siteAry) {
        if (!is_array($siteAry)) {
            continue;
        }
        $total++;
        $res = ec_p4c_upsert_site((string)$domainKey, $siteAry, $now);
        if ($res['action'] === 'inserted') {
            $inserted++;
        } elseif ($res['action'] === 'updated') {
            $updated++;
        } elseif ($res['action'] === 'deactivated') {
            $deactivated++;
        }
        if (!empty($res['renamed'])) {
            $renamed++;
        }
    }

    return [
        'ok'          => true,
        'http_status' => 200,
        'inserted'    => $inserted,
        'updated'     => $updated,
        'deactivated' => $deactivated,
        'renamed'     => $renamed,
        'total'       => $total,
        'message'     => "Pay4Coins-Webseiten-Abgleich erfolgreich: {$inserted} neu angelegt, {$updated} aktualisiert, {$deactivated} deaktiviert"
            . ($renamed > 0 ? ", {$renamed} Domains umbenannt." : '.'),
    ];
}

/**
 * Film-Kategorien (`movie_categories.name_id`), die im MCP fuer Creator komplett ausgeblendet werden
 * (BRAM Hard-Block), im ACP fuer die Altbestandspruefung aber sichtbar bleiben.
 *
 * @return array<int, string>
 */
function ec_movie_get_mcp_hidden_categories(): array
{
    return ['breath_control'];
}

/**
 * Regelwerk fuer Website-gebundene Sonderkategorien (`movie_categories.name_id`).
 *
 * @return array<string, array{
 *   policy_key: string,
 *   label: string,
 *   p4c_cat_id: int,
 *   p4c_cat_label: string,
 *   forbid_cc: bool,
 *   forbid_psc: bool
 * }>
 */
function ec_movie_get_restricted_category_rules(): array
{
    return [
        'poppers' => [
            'policy_key'    => 'allow_poppers',
            'label'         => 'Poppers',
            'p4c_cat_id'    => EC_P4C_SHOP_CAT_POPPERS,
            'p4c_cat_label' => 'Poppers',
            'forbid_cc'     => true,
            'forbid_psc'    => true,
        ],
        'kaviar' => [
            'policy_key'    => 'allow_kv_ns',
            'label'         => 'Kaviar (KV)',
            'p4c_cat_id'    => EC_P4C_SHOP_CAT_KV_NS_DIGITAL,
            'p4c_cat_label' => 'KV, NS (Digital)',
            'forbid_cc'     => true,
            'forbid_psc'    => false,
        ],
        'golden_showers' => [
            'policy_key'    => 'allow_kv_ns',
            'label'         => 'Natursekt (NS)',
            'p4c_cat_id'    => EC_P4C_SHOP_CAT_KV_NS_DIGITAL,
            'p4c_cat_label' => 'KV, NS (Digital)',
            'forbid_cc'     => true,
            'forbid_psc'    => false,
        ],
    ];
}

/**
 * Berechnet die vollständige Freigabe-Policy aus einem `sites`-Datensatz (ohne DB-Aufruf).
 *
 * @return array{
 *   domain: string,
 *   is_public: bool,
 *   synced: bool,
 *   categories_map: array<int, string>,
 *   category_ids: array<int, int>,
 *   has_creditcard: bool,
 *   has_paysafecard: bool,
 *   has_p4c_poppers: bool,
 *   has_p4c_kv_ns_digital: bool,
 *   has_p4c_kv_ns_shipping: bool,
 *   allow_poppers: bool,
 *   allow_kv_ns: bool,
 *   conflicts: array<int, string>
 * }
 */
function ec_site_parse_policy(?object $siteRow, string $domainFallback = ''): array
{
    $domainRaw = $siteRow !== null && !empty($siteRow->domain)
        ? (string)$siteRow->domain
        : $domainFallback;
    $domainClean = trim(ec_p4c_to_utf8($domainRaw));
    $isPublic    = ($domainClean === '' || strcasecmp($domainClean, 'public') === 0);

    $synced = (!$isPublic
        && $siteRow !== null
        && property_exists($siteRow, 'p4c_shop_categories')
        && $siteRow->p4c_shop_categories !== null
    );

    $categoriesMap = [];
    if ($synced) {
        $decoded = @json_decode((string)$siteRow->p4c_shop_categories, true);
        if (is_array($decoded)) {
            $defaults = ec_p4c_default_shop_category_labels();
            foreach ($decoded as $cid => $cname) {
                $idInt = (int)$cid;
                if ($idInt <= 0) {
                    continue;
                }
                $label = trim(ec_p4c_to_utf8((string)$cname));
                if ($label === '' && isset($defaults[$idInt])) {
                    $label = $defaults[$idInt];
                }
                $categoriesMap[$idInt] = $label;
            }
            ksort($categoriesMap, SORT_NUMERIC);
        }
    }

    $categoryIds         = array_keys($categoriesMap);
    $hasCreditcard       = (!$isPublic && $siteRow !== null && !empty($siteRow->has_creditcard));
    $hasPaysafecard      = (!$isPublic && $siteRow !== null && !empty($siteRow->has_paysafecard));
    $hasP4cPoppers       = in_array(EC_P4C_SHOP_CAT_POPPERS, $categoryIds, true);
    $hasP4cKvNsDigital   = in_array(EC_P4C_SHOP_CAT_KV_NS_DIGITAL, $categoryIds, true);
    $hasP4cKvNsShipping  = in_array(EC_P4C_SHOP_CAT_KV_NS_SHIPPING, $categoryIds, true);

    $allowPoppers = (!$isPublic && $synced && $hasP4cPoppers && !$hasCreditcard && !$hasPaysafecard);
    $allowKvNs    = (!$isPublic && $synced && $hasP4cKvNsDigital && !$hasCreditcard);

    $conflicts = [];
    if ($synced && $hasP4cPoppers && ($hasCreditcard || $hasPaysafecard)) {
        $blockedBy = [];
        if ($hasCreditcard) {
            $blockedBy[] = 'Kreditkarte';
        }
        if ($hasPaysafecard) {
            $blockedBy[] = 'Paysafecard';
        }
        $conflicts[] = 'Konflikt in Pay4Coins: Shop-Kategorie „Poppers“ ist aktiv, aber '
            . implode(' und ', $blockedBy)
            . ' ist eingeschaltet – daher bleibt „Poppers“ in EroCloud gesperrt.';
    }
    if ($synced && $hasP4cKvNsDigital && $hasCreditcard) {
        $conflicts[] = 'Konflikt in Pay4Coins: Shop-Kategorie „KV, NS (Digital)“ ist aktiv, aber Kreditkarte ist eingeschaltet – daher bleiben Kaviar (KV) und Natursekt (NS) in EroCloud gesperrt.';
    }

    return [
        'domain'                 => $isPublic ? 'public' : $domainClean,
        'is_public'              => $isPublic,
        'synced'                 => $synced,
        'categories_map'         => $categoriesMap,
        'category_ids'           => $categoryIds,
        'has_creditcard'         => $hasCreditcard,
        'has_paysafecard'        => $hasPaysafecard,
        'has_p4c_poppers'        => $hasP4cPoppers,
        'has_p4c_kv_ns_digital'  => $hasP4cKvNsDigital,
        'has_p4c_kv_ns_shipping' => $hasP4cKvNsShipping,
        'allow_poppers'          => $allowPoppers,
        'allow_kv_ns'            => $allowKvNs,
        'conflicts'              => $conflicts,
    ];
}

/**
 * Laedt die Policy einer Website aus `sites` anhand des Domainnamens (oder `'public'`).
 *
 * @return array<string, mixed>
 */
function ec_site_get_policy_by_domain(string $domain): array
{
    ec_p4c_sites_ensure_schema();

    $trimmed = trim($domain);
    if ($trimmed === '' || strcasecmp($trimmed, 'public') === 0) {
        return ec_site_parse_policy(null, 'public');
    }

    $norm   = ec_p4c_normalize_domain($trimmed);
    $noWww  = str_starts_with($norm, 'www.') ? substr($norm, 4) : $norm;
    $withWww = $noWww !== '' ? ('www.' . $noWww) : '';

    $candidates = array_values(array_unique(array_filter([
        strtolower($trimmed),
        $norm,
        $noWww,
        $withWww,
    ])));

    $siteRow = null;
    if (!empty($candidates)) {
        $escaped = array_map(
            static fn (string $c): string => "'" . p4c_escape_string($c) . "'",
            $candidates
        );
        $rs = p4c_query(
            "SELECT `id`, `domain`, `status`, `p4c_shop_categories`, `has_creditcard`, `has_paysafecard`
             FROM `sites`
             WHERE LOWER(TRIM(`domain`)) IN (" . implode(',', $escaped) . ")
             ORDER BY `status` DESC, `id` DESC
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rs && p4c_num_rows($rs) > 0) {
            $siteRow = p4c_fetch_object($rs) ?: null;
        }
    }

    return ec_site_parse_policy($siteRow, $trimmed);
}

/**
 * Validiert gewaehlte Film-Kategorien gegen eine bereits aufgeloeste Website-Policy.
 *
 * @param array<int, string> $selectedCategories
 * @param array<string, mixed> $policy
 * @return array{valid: bool, errors: array<int, string>, policy: array<string, mixed>}
 */
function ec_movie_validate_categories_against_policy(
    array $selectedCategories,
    array $policy,
    bool $blockMcpHidden = false
): array {
    $errors  = [];
    $cleaned = array_values(array_unique(array_filter(array_map(
        static fn ($c): string => trim((string)$c),
        $selectedCategories
    ))));

    if ($blockMcpHidden) {
        $hidden = ec_movie_get_mcp_hidden_categories();
        foreach ($cleaned as $catId) {
            if (in_array($catId, $hidden, true)) {
                $errors[] = 'Die Kategorie „Atemkontrolle“ ist aufgrund von Zahlungsanbieter- und Sicherheitsrichtlinien nicht zulässig und kann nicht ausgewählt werden.';
                break;
            }
        }
    }

    $rules            = ec_movie_get_restricted_category_rules();
    $failedByPolicy   = [];

    foreach ($cleaned as $catId) {
        if (!isset($rules[$catId])) {
            continue;
        }
        $rule      = $rules[$catId];
        $policyKey = $rule['policy_key'];
        if (empty($policy[$policyKey])) {
            if (!isset($failedByPolicy[$policyKey])) {
                $failedByPolicy[$policyKey] = [
                    'rule'   => $rule,
                    'labels' => [],
                ];
            }
            $failedByPolicy[$policyKey]['labels'][] = $rule['label'];
        }
    }

    foreach ($failedByPolicy as $group) {
        $rule       = $group['rule'];
        $labels     = array_values(array_unique($group['labels']));
        $labelsText = count($labels) > 1
            ? '„' . implode('“ und „', $labels) . '“'
            : '„' . $labels[0] . '“';
        $catWord    = count($labels) > 1 ? 'Die Kategorien' : 'Die Kategorie';
        $verbWord   = count($labels) > 1 ? 'dürfen' : 'darf';
        $verbIs     = count($labels) > 1 ? 'sind' : 'ist';

        if (!empty($policy['is_public'])) {
            $errors[] = "{$catWord} {$labelsText} {$verbWord} nicht auf „alle Partnerwebsites (public)“ veröffentlicht werden. Bitte wähle eine dafür freigeschaltete Website aus.";
            continue;
        }

        $domDisplay = (string)($policy['domain'] ?? '');
        if (empty($policy['synced'])) {
            $errors[] = "Die Website „{$domDisplay}“ wurde noch nicht mit den Pay4Coins-Shop-Kategorien synchronisiert. {$catWord} {$labelsText} {$verbIs} daher aktuell nicht freigegeben.";
            continue;
        }

        $catIds = is_array($policy['category_ids'] ?? null) ? $policy['category_ids'] : [];
        if (!in_array((int)$rule['p4c_cat_id'], $catIds, true)) {
            $p4cLabel = (string)$rule['p4c_cat_label'];
            $errors[] = "{$catWord} {$labelsText} {$verbIs} für „{$domDisplay}“ nicht freigeschaltet (in Pay4Coins fehlt die Shop-Kategorie „{$p4cLabel}“).";
            continue;
        }

        $activeMethods = [];
        if (!empty($rule['forbid_cc']) && !empty($policy['has_creditcard'])) {
            $activeMethods[] = 'Kreditkarte';
        }
        if (!empty($rule['forbid_psc']) && !empty($policy['has_paysafecard'])) {
            $activeMethods[] = 'Paysafecard';
        }

        if (!empty($activeMethods)) {
            $methodsStr = implode(' und ', $activeMethods);
            $errors[]   = "{$catWord} {$labelsText} {$verbIs} auf „{$domDisplay}“ nicht zulässig, solange dort {$methodsStr} als Zahlungsart aktiv ist.";
        } else {
            $errors[] = "{$catWord} {$labelsText} {$verbIs} auf „{$domDisplay}“ nicht zulässig.";
        }
    }

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
        'policy' => $policy,
    ];
}

/**
 * Validiert gewaehlte Film-Kategorien gegen eine Domain (`visible_for_website`).
 *
 * @param array<int, string> $selectedCategories
 * @return array{valid: bool, errors: array<int, string>, policy: array<string, mixed>}
 */
function ec_movie_validate_categories_for_website(
    array $selectedCategories,
    string $visibleWebsite,
    bool $blockMcpHidden = false
): array {
    $policy = ec_site_get_policy_by_domain($visibleWebsite);
    return ec_movie_validate_categories_against_policy($selectedCategories, $policy, $blockMcpHidden);
}

/**
 * Rendert HTML-`data-*`-Attribute fuer ein `<option>`-Element in ACP-Film-Dropdowns.
 *
 * @param array<string, mixed> $policy
 */
function ec_site_policy_data_attributes(array $policy): string
{
    $catsMap   = isset($policy['categories_map']) && is_array($policy['categories_map']) ? $policy['categories_map'] : [];
    $conflicts = isset($policy['conflicts']) && is_array($policy['conflicts']) ? $policy['conflicts'] : [];

    $attrs = [
        'data-allow-poppers'      => !empty($policy['allow_poppers']) ? '1' : '0',
        'data-allow-kv-ns'        => !empty($policy['allow_kv_ns']) ? '1' : '0',
        'data-has-p4c-poppers'    => !empty($policy['has_p4c_poppers']) ? '1' : '0',
        'data-has-p4c-kv-ns'      => !empty($policy['has_p4c_kv_ns_digital']) ? '1' : '0',
        'data-has-cc'             => !empty($policy['has_creditcard']) ? '1' : '0',
        'data-has-psc'            => !empty($policy['has_paysafecard']) ? '1' : '0',
        'data-synced'             => !empty($policy['synced']) ? '1' : '0',
        'data-p4c-cats'           => implode(', ', array_values($catsMap)),
        'data-conflicts'          => implode(' | ', $conflicts),
    ];

    $parts = [];
    foreach ($attrs as $name => $val) {
        $parts[] = $name . '="' . htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') . '"';
    }
    return implode(' ', $parts);
}
