<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) - Pay4Coins Single-Sign-On Endpunkt
 * Verifiziert die Partner-ID (pid) gegen die Pay4Coins-API, synchronisiert die Stammdaten
 * und startet die authentifizierte MCP-Partner-Session.
 */

define('SAFE_INC', 1);
define('MCP_SKIP_AUTO_SESSION_RECOVERY', true);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$rawPid = isset($_GET['pid']) ? (string)$_GET['pid'] : '';
if (strlen($rawPid) !== 10) {
    header('Location: ' . LOGIN_URL . '?ref=erocloud');
    exit;
}

$getPid = trim((string)preg_replace('/[^a-z0-9]/i', '', $rawPid));
$loginHash = loginHash($getPid);

// 1. Login-Status bei Pay4Coins-API prüfen
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, Pay4Coins_API_URL . '/remote/merchant_login.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, 'h=' . $loginHash . '&pid=' . $getPid);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME . ' - ' . COMPANYNAME . ' - ' . URL);
$data = curl_exec($ch);
curl_close($ch);

if ($data === 'ok') {
    // 2. Partner-Stammdaten von Pay4Coins-API abrufen
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, Pay4Coins_API_URL . '/remote/erocloud_merchant_data.php');
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'h=' . $loginHash . '&pid=' . $getPid);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, PROJECTNAME . ' - ' . COMPANYNAME . ' - ' . URL);
    $merchantData = (string)curl_exec($ch);
    curl_close($ch);

    if (preg_match('/<pay4coins>(.*)<\/pay4coins>/smU', $merchantData, $xmlData) && isset($xmlData[1])) {
        $xml = @simplexml_load_string($merchantData);
        if ($xml !== false && isset($xml->user)) {
            $merchantXmlObj = $xml->user;
            $partnerIdEsc   = p4c_escape_string((string)$merchantXmlObj->partner_id);
            $usernameEsc    = p4c_escape_string((string)$merchantXmlObj->username);
            $emailEsc       = p4c_escape_string((string)$merchantXmlObj->email);
            $salutationEsc  = p4c_escape_string((string)$merchantXmlObj->salutation);
            $firstnameEsc   = p4c_escape_string((string)$merchantXmlObj->firstname);
            $surnameEsc     = p4c_escape_string((string)$merchantXmlObj->surname);
            $birthdayEsc    = p4c_escape_string((string)$merchantXmlObj->birthday);
            $userIpEsc      = p4c_escape_string((string)getUserIP());
            $now            = date('Y-m-d H:i:s');

            $merchantId = 0;

            $rsUser = p4c_query("SELECT `id`, `api_key` FROM `merchants` WHERE `partner_id`='" . $partnerIdEsc . "' LIMIT 1;", __FILE__, __LINE__);
            if (p4c_num_rows($rsUser) > 0) {
                $userObj = p4c_fetch_object($rsUser);
                $merchantId = abs((int)$userObj->id);

                if (trim((string)$userObj->api_key) === '') {
                    p4c_query("UPDATE `merchants` SET
                        `api_key` = AES_ENCRYPT('" . strtoupper(randomString(32)) . "','" . AES_KEY . "')
                    WHERE `id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
                }

                p4c_query("UPDATE `merchants` SET
                    `username`         = AES_ENCRYPT('" . $usernameEsc . "','" . AES_KEY . "'),
                    `email`            = AES_ENCRYPT('" . $emailEsc . "','" . AES_KEY . "'),
                    `salutation`       = '" . $salutationEsc . "',
                    `firstname`        = AES_ENCRYPT('" . $firstnameEsc . "','" . AES_KEY . "'),
                    `surname`          = AES_ENCRYPT('" . $surnameEsc . "','" . AES_KEY . "'),
                    `birthday`         = AES_ENCRYPT('" . $birthdayEsc . "','" . AES_KEY . "'),
                    `last_ip`          = AES_ENCRYPT('" . $userIpEsc . "','" . AES_KEY . "'),
                    `last_ip_datetime` = '" . $now . "'
                WHERE `id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
            } else {
                $inserted = p4c_query("INSERT INTO `merchants` SET
                    `api_key`          = AES_ENCRYPT('" . strtoupper(randomString(32)) . "','" . AES_KEY . "'),
                    `partner_id`       = '" . $partnerIdEsc . "',
                    `username`         = AES_ENCRYPT('" . $usernameEsc . "','" . AES_KEY . "'),
                    `email`            = AES_ENCRYPT('" . $emailEsc . "','" . AES_KEY . "'),
                    `salutation`       = '" . $salutationEsc . "',
                    `firstname`        = AES_ENCRYPT('" . $firstnameEsc . "','" . AES_KEY . "'),
                    `surname`          = AES_ENCRYPT('" . $surnameEsc . "','" . AES_KEY . "'),
                    `birthday`         = AES_ENCRYPT('" . $birthdayEsc . "','" . AES_KEY . "'),
                    `last_ip`          = AES_ENCRYPT('" . $userIpEsc . "','" . AES_KEY . "'),
                    `last_ip_datetime` = '" . $now . "';", __FILE__, __LINE__);

                if ($inserted) {
                    $merchantId = abs((int)p4c_insert_id());
                }
            }

            if ($merchantId > 0) {
                // Vorherige Partner-Session sauber entfernen & Session-ID rotieren (verhindert Account-Vermischung beim Partnerwechsel)
                unset(
                    $_SESSION['merchant_id'],
                    $_SESSION['merchant_username'],
                    $_SESSION['logged_in_as'],
                    $_SESSION['logged_id'],
                    $_SESSION['mcp_impersonated_by_admin'],
                    $_SESSION['erocloud_stream_cache']
                );
                if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                    @session_regenerate_id(true);
                }

                $merchant         = new Merchant($mysql, $merchantId);
                $resolvedMid      = abs((int)$merchant->id()) ?: $merchantId;
                $resolvedUsername = (string)$merchant->username('aes_decrypt');
                $resolvedPid      = (string)$merchant->partner_id() ?: (string)$merchantXmlObj->partner_id;

                $_SESSION['initiated']         = true;
                $_SESSION['logged_in_as']      = 'merchant';
                $_SESSION['logged_id']         = 'mcp';
                $_SESSION['merchant_id']       = $resolvedMid;
                $_SESSION['merchant_username'] = $resolvedUsername;
                $_SESSION['last_activity']     = time();

                if (function_exists('mcp_neu_sync_auth_cookie')) {
                    mcp_neu_sync_auth_cookie($resolvedMid, $resolvedUsername);
                }

                $isHttps   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
                $host      = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
                $mcpDomain = defined('MCP_DOMAIN') ? strtolower(ltrim((string)MCP_DOMAIN, '.')) : '';
                $cookieDom = ($mcpDomain !== '' && ($host === $mcpDomain || str_ends_with($host, '.' . $mcpDomain))) ? ('.' . $mcpDomain) : '';

                $cookiePayload = [
                    'pid' => $resolvedPid,
                    'sid' => session_id(),
                ];
                @setcookie('erocloud', base64_encode((string)json_encode($cookiePayload)), [
                    'expires'  => (int)strtotime('+3 month'),
                    'path'     => '/',
                    'domain'   => $cookieDom,
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                log_action('Am System angemeldet.');

                $rawBasePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
                $requestUri  = (string)($_SERVER['REQUEST_URI'] ?? '');
                $basePath    = (stripos($requestUri, '/mcp_neu') === 0 || stripos($requestUri, '/mcp/mcp_neu') !== false) ? $rawBasePath : '';
                header('Location: ' . $basePath . '/Startseite');
                exit;
            }
        }
    }
}

// Fehlgeschlagener SSO-Handshake: Etwaige Alt-Sessions & Auth-Cookies bereinigen, bevor zu Pay4Coins abgemeldet wird
if (function_exists('mcp_neu_clear_auth_cookie')) {
    mcp_neu_clear_auth_cookie();
}
unset(
    $_SESSION['merchant_id'],
    $_SESSION['merchant_username'],
    $_SESSION['logged_in_as'],
    $_SESSION['logged_id'],
    $_SESSION['mcp_impersonated_by_admin'],
    $_SESSION['erocloud_stream_cache']
);
header('Location: ' . LOGIN_URL . '?logout&ref=erocloud');
exit;
