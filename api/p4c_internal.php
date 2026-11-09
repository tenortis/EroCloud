<?php

/**
 * EroCloud Internal Read-Only API for Pay4Coins (api.erocloud.net/p4c_internal.php)
 *
 * Stellt eine streng abgeschottete, rein lesende (Read-Only) interne Schnittstelle
 * exklusiv fuer den Pay4Coins-Server (ve49734318 / 91.184.46.111) und die EroCloud
 * selbst (t199 / 91.184.62.199) bereit.
 *
 * Doppelte Sicherheits-Architektur (Double-Lock):
 * 1. Strikte IP-Whitelist auf TCP-Ebene ($_SERVER['REMOTE_ADDR'] – kein X-Forwarded-For Spoofing moeglich).
 * 2. Kryptografischer 64-Zeichen Secret-Key (Timing-Safe geprueft via hash_equals).
 * 3. Rein lesender Action-Router (moderation_summary, ping, zukuenftig beliebig erweiterbar).
 *
 * Kompatibel mit PHP 8.0.30 & PHP 8.4.27+.
 *
 * @author      Martin Zimmermann
 * @copyright   (C) 2026 Cipa Media S.L.U.
 */

define('SAFE_INC', 1);
@chdir(__DIR__);

include_once("../config.inc.php");
include_once(API_DIR . "/common.inc.php");

// Session-Lock sofort freigeben, damit parallele Requests niemals blockiert werden
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

/**
 * Laedt die interne API-Konfiguration (mit fest verdrahteten Produktions-Defaults
 * fuer Pay4Coins & EroCloud sowie optionalem Override ueber includes/ai_rules/p4c_internal_config.json).
 *
 * @return array<string, mixed>
 */
function ec_p4c_internal_load_config(): array
{
    $defaults = array(
        'api_key' => 'p4c_ec_int_rd_9d4f2a8c1e7b5f3d0a6c8e2b4f9a1d7c3e5b8f0a2d4c6e9b1a3f',
        'allowed_ips' => array(
            '91.184.46.111', // Pay4Coins Web- & ACP-Server (ve49734318)
            '91.184.62.199', // EroCloud Server selbst (t199)
            '127.0.0.1',     // Localhost IPv4 (XAMPP / lokaler Selbstaufruf)
            '::1',           // Localhost IPv6 (XAMPP / lokaler Selbstaufruf)
        ),
    );

    $cfgPath = SOURCEDIR . '/includes/ai_rules/p4c_internal_config.json';
    if (@is_file($cfgPath) && @is_readable($cfgPath)) {
        $raw = (string)@file_get_contents($cfgPath);
        $dec = @json_decode($raw, true);
        if (is_array($dec)) {
            if (!empty($dec['api_key']) && is_string($dec['api_key'])) {
                $defaults['api_key'] = trim($dec['api_key']);
            }
            if (!empty($dec['allowed_ips']) && is_array($dec['allowed_ips'])) {
                $defaults['allowed_ips'] = array_values(array_unique(array_merge(
                    $defaults['allowed_ips'],
                    array_map('strval', $dec['allowed_ips'])
                )));
            }
        }
    }

    return $defaults;
}

/**
 * Extrahiert den uebergebenen API-Key aus HTTP-Header (X-P4C-Internal-Key / Authorization Bearer)
 * oder dem Request-Body (robust unter Apache mod_fcgid & PHP-FPM).
 *
 * @param array<string, mixed> $jsonBody
 */
function ec_p4c_internal_extract_api_key(array $jsonBody): string
{
    if (!empty($_SERVER['HTTP_X_P4C_INTERNAL_KEY'])) {
        return trim((string)$_SERVER['HTTP_X_P4C_INTERNAL_KEY']);
    }

    $authHeader = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($authHeader !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
        return trim((string)$m[1]);
    }

    if (!empty($jsonBody['api_key'])) {
        return trim((string)$jsonBody['api_key']);
    }

    if (!empty($_POST['api_key'])) {
        return trim((string)$_POST['api_key']);
    }

    return '';
}

/**
 * Stellt sicher, dass Strings aus Legacy-Tabellen sauber in UTF-8 fuer JSON vorliegen.
 *
 * @param mixed $str
 */
function ec_p4c_internal_safe_utf8($str): string
{
    if ($str === null || $str === false) {
        return '';
    }
    $s = (string)$str;
    if ($s === '') {
        return '';
    }
    if (!mb_check_encoding($s, 'UTF-8')) {
        return (string)mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
    }
    return $s;
}

// =========================================================================
// 1. SICHERHEITS-SCHICHT 1: Strikte IP-Whitelist (nur $_SERVER['REMOTE_ADDR'])
// =========================================================================
$internalCfg = ec_p4c_internal_load_config();
$remoteAddr  = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';

if ($remoteAddr === '' || !in_array($remoteAddr, $internalCfg['allowed_ips'], true)) {
    http_response_code(403);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Forbidden (IP not authorized).',
    ));
    p4c_close();
    exit;
}

// =========================================================================
// 2. SICHERHEITS-SCHICHT 2: Kryptografischer API-Key (hash_equals)
// =========================================================================
$rawInput = (string)@file_get_contents('php://input');
$jsonBody = ($rawInput !== '') ? @json_decode($rawInput, true) : array();
if (!is_array($jsonBody)) {
    $jsonBody = array();
}

$expectedKey = (string)$internalCfg['api_key'];
$providedKey = ec_p4c_internal_extract_api_key($jsonBody);

if ($expectedKey === '' || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Forbidden (Invalid API key).',
    ));
    p4c_close();
    exit;
}

// =========================================================================
// 3. ACTION-ROUTER (Read-Only)
// =========================================================================
$action = 'moderation_summary';
if (!empty($jsonBody['action'])) {
    $action = trim((string)$jsonBody['action']);
} elseif (!empty($_POST['action'])) {
    $action = trim((string)$_POST['action']);
} elseif (!empty($_GET['action'])) {
    $action = trim((string)$_GET['action']);
}

if ($action === 'ping') {
    echo json_encode(array(
        'ok'        => true,
        'action'    => 'ping',
        'timestamp' => time(),
        'client_ip' => $remoteAddr,
    ));
    p4c_close();
    exit;
}

if ($action === 'moderation_summary') {
    // 1. Filme zur Pruefung (exakt analog zu EroCloud/acp/index.php)
    $countMoviesChecking = 0;
    $rsMoviesCnt = p4c_query(
        "SELECT COUNT(*) AS `cnt` FROM `movies` WHERE (`movie_checked`='0000-00-00 00:00:00' OR `movie_checked`='000-00-00 00:00:00') AND `released`='1' AND `convert_status` > '1';",
        __FILE__,
        __LINE__
    );
    if ($rsMoviesCnt && p4c_num_rows($rsMoviesCnt) > 0) {
        $countMoviesChecking = (int)p4c_fetch_object($rsMoviesCnt)->cnt;
    }

    // Optional: Filme, die gerade noch konvertieren
    $countMoviesConverting = 0;
    $rsMoviesConv = p4c_query(
        "SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released`='1' AND `convert_status` = '1';",
        __FILE__,
        __LINE__
    );
    if ($rsMoviesConv && p4c_num_rows($rsMoviesConv) > 0) {
        $countMoviesConverting = (int)p4c_fetch_object($rsMoviesConv)->cnt;
    }

    // Vorschau der neuesten bis zu 5 ungeprueften Filme
    $moviesPreview = array();
    if ($countMoviesChecking > 0) {
        $rsMoviesList = p4c_query(
            "SELECT m.`id`, m.`merchant_id`, m.`actor_id`, m.`title`, m.`online_at`, a.`username` AS `actor_username`
             FROM `movies` m
             LEFT JOIN `actors` a ON a.`id` = m.`actor_id` AND a.`merchant_id` = m.`merchant_id`
             WHERE (m.`movie_checked`='0000-00-00 00:00:00' OR m.`movie_checked`='000-00-00 00:00:00')
               AND m.`released`='1'
               AND m.`convert_status` > '1'
             ORDER BY m.`id` DESC
             LIMIT 5;",
            __FILE__,
            __LINE__
        );
        if ($rsMoviesList) {
            while ($mRow = p4c_fetch_object($rsMoviesList)) {
                $moviesPreview[] = array(
                    'id'             => (int)$mRow->id,
                    'merchant_id'    => (int)$mRow->merchant_id,
                    'actor_id'       => (int)$mRow->actor_id,
                    'actor_username' => ec_p4c_internal_safe_utf8($mRow->actor_username ?? ''),
                    'title'          => ec_p4c_internal_safe_utf8($mRow->title ?? ''),
                    'online_at'      => (string)($mRow->online_at ?? ''),
                );
            }
        }
    }

    // 2. Fotoalben zur Pruefung (exakt analog zu EroCloud/acp/index.php)
    $countPhotoAlbumsChecking = 0;
    $rsAlbumsCnt = p4c_query(
        "SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE (`album_checked`='0000-00-00 00:00:00' OR `album_checked`='000-00-00 00:00:00') AND `released`='1';",
        __FILE__,
        __LINE__
    );
    if ($rsAlbumsCnt && p4c_num_rows($rsAlbumsCnt) > 0) {
        $countPhotoAlbumsChecking = (int)p4c_fetch_object($rsAlbumsCnt)->cnt;
    }

    // Vorschau der neuesten bis zu 5 ungeprueften Fotoalben
    $albumsPreview = array();
    if ($countPhotoAlbumsChecking > 0) {
        $rsAlbumsList = p4c_query(
            "SELECT p.`id`, p.`merchant_id`, p.`actor_id`, p.`title`, p.`number_of_photos`, p.`online_at`, a.`username` AS `actor_username`
             FROM `photo_albums` p
             LEFT JOIN `actors` a ON a.`id` = p.`actor_id`
             WHERE (p.`album_checked`='0000-00-00 00:00:00' OR p.`album_checked`='000-00-00 00:00:00')
               AND p.`released`='1'
             ORDER BY p.`id` DESC
             LIMIT 5;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbumsList) {
            while ($aRow = p4c_fetch_object($rsAlbumsList)) {
                $albumsPreview[] = array(
                    'id'               => (int)$aRow->id,
                    'merchant_id'      => (int)$aRow->merchant_id,
                    'actor_id'         => (int)$aRow->actor_id,
                    'actor_username'   => ec_p4c_internal_safe_utf8($aRow->actor_username ?? ''),
                    'title'            => ec_p4c_internal_safe_utf8($aRow->title ?? ''),
                    'number_of_photos' => (int)($aRow->number_of_photos ?? 0),
                    'online_at'        => (string)($aRow->online_at ?? ''),
                );
            }
        }
    }

    // 3. Drehpartner & ID-Checks zur Pruefung (exakt analog zu EroCloud/acp/index.php)
    $countCoActorsChecking = 0;
    $coActorsPreview = array();
    $coActorMgrPath = SOURCEDIR . '/includes/klassen/CoActorManager.inc.php';
    if (@is_file($coActorMgrPath)) {
        require_once($coActorMgrPath);
        if (class_exists('CoActorManager')) {
            CoActorManager::ensureSchema();
        }
        $rsCoCnt = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `co_actors` WHERE `status` != 'deleted' AND `checked_status`='under_review';",
            __FILE__,
            __LINE__
        );
        if ($rsCoCnt && p4c_num_rows($rsCoCnt) > 0) {
            $countCoActorsChecking = (int)p4c_fetch_object($rsCoCnt)->cnt;
        }

        if ($countCoActorsChecking > 0) {
            $rsCoList = p4c_query(
                "SELECT c.`id`, c.`merchant_id`, c.`is_owner`, c.`creator_name`, c.`verification_method`,
                        c.`submitted_datetime`, c.`create_datetime`
                 FROM `co_actors` c
                 WHERE c.`status` != 'deleted' AND c.`checked_status`='under_review'
                 ORDER BY COALESCE(c.`submitted_datetime`, c.`create_datetime`) DESC, c.`id` DESC
                 LIMIT 5;",
                __FILE__,
                __LINE__
            );
            if ($rsCoList) {
                while ($cRow = p4c_fetch_object($rsCoList)) {
                    $coActorsPreview[] = array(
                        'id'                  => (int)$cRow->id,
                        'merchant_id'         => (int)$cRow->merchant_id,
                        'is_owner'            => (int)$cRow->is_owner === 1,
                        'creator_name'        => ec_p4c_internal_safe_utf8($cRow->creator_name ?? ''),
                        'verification_method' => (string)($cRow->verification_method ?? 'qr_live'),
                        'submitted_at'        => (string)($cRow->submitted_datetime ?: $cRow->create_datetime ?: ''),
                    );
                }
            }
        }
    }

    $totalChecking = $countMoviesChecking + $countPhotoAlbumsChecking + $countCoActorsChecking;

    echo json_encode(array(
        'ok'        => true,
        'action'    => 'moderation_summary',
        'timestamp' => time(),
        'counts'    => array(
            'movies_checking'       => $countMoviesChecking,
            'photo_albums_checking' => $countPhotoAlbumsChecking,
            'co_actors_checking'    => $countCoActorsChecking,
            'total_checking'        => $totalChecking,
            'movies_converting'     => $countMoviesConverting,
        ),
        'items'     => array(
            'movies'       => $moviesPreview,
            'photo_albums' => $albumsPreview,
            'co_actors'    => $coActorsPreview,
        ),
        'urls'      => array(
            'acp_base'              => ACP_URL,
            'movies_checking'       => ACP_URL . '/Filme-pruefen',
            'photo_albums_checking' => ACP_URL . '/Fotoalben-pruefen',
            'co_actors_checking'    => ACP_URL . '/Drehpartner-pruefen',
        ),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    p4c_close();
    exit;
}

http_response_code(400);
echo json_encode(array(
    'ok'    => false,
    'error' => 'Unknown action.',
));
p4c_close();
exit;
