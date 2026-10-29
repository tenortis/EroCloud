<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) - Modernisiertes Merchant-Control-Panel
 * Produktions-Shell (Single Page Application Wrapper nach KKC-Vorbild)
 * Markenfarbe: Pay4Coins-Blau (#4297D7 / #0284C7) | Standard: Hellmodus (Light Mode)
 */

define('SAFE_INC', 1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Csrf;

global $mysql;

// 1. Logout verarbeiten (analog mcp/index.php, mit Sonderschutz bei Admin-Session-Übernahme)
if (isset($_GET['logout'])) {
    // Falls die /tmp-Session bereits durch den GC gelöscht wurde, Infos aus dem signierten Auth-Cookie rekonstruieren
    if (empty($_SESSION['merchant_id']) && !empty($_COOKIE['erocloud_mcp_auth']) && defined('AES_KEY')) {
        $rawAuth = @base64_decode((string)$_COOKIE['erocloud_mcp_auth'], true);
        $authArr = is_string($rawAuth) ? @json_decode($rawAuth, true) : null;
        if (is_array($authArr) && isset($authArr['mid'], $authArr['exp'], $authArr['usr'], $authArr['sig'])) {
            $cMid     = abs((int)$authArr['mid']);
            $cExp     = abs((int)$authArr['exp']);
            $cUsr     = (string)$authArr['usr'];
            $cAdmId   = abs((int)($authArr['adm_id'] ?? 0));
            $cAdmUsr  = (string)($authArr['adm_usr'] ?? '');
            $cDataStr = $cMid . '|' . $cExp . '|' . $cUsr . ($cAdmId > 0 ? '|' . $cAdmId . '|' . $cAdmUsr : '');
            if ($cMid > 0 && hash_equals(hash_hmac('sha256', $cDataStr, (string)AES_KEY), (string)$authArr['sig'])) {
                $_SESSION['merchant_id']  = $cMid;
                $_SESSION['logged_in_as'] = 'merchant';
                if ($cAdmId > 0) {
                    $_SESSION['mcp_impersonated_by_admin'] = ['id' => $cAdmId, 'username' => $cAdmUsr ?: 'Admin'];
                }
            }
        }
    }

    $isAdminImp = !empty($_SESSION['mcp_impersonated_by_admin']) && is_array($_SESSION['mcp_impersonated_by_admin']);

    if (!$isAdminImp && !empty($_SESSION['merchant_id'])) {
        if ($_GET['logout'] === 'auto') {
            log_action('Vom System abgemeldet. (auto)');
        } else {
            log_action('Vom System abgemeldet. (manuell)');
        }
    }

    if (function_exists('mcp_neu_clear_auth_cookie')) {
        mcp_neu_clear_auth_cookie();
    }

    if ($isAdminImp) {
        // Nur MCP-spezifische Session-Keys entfernen, damit eine parallele ACP-Session unberührt bleibt
        unset(
            $_SESSION['merchant_id'],
            $_SESSION['merchant_username'],
            $_SESSION['logged_in_as'],
            $_SESSION['mcp_impersonated_by_admin'],
            $_SESSION['erocloud_stream_cache']
        );
        if (!empty($_SESSION['employee_id'])) {
            $_SESSION['logged_id'] = 'acp';
        } else {
            $_SESSION = [];
            @session_destroy();
        }
        $acpTarget = (defined('ACP_URL') ? rtrim((string)ACP_URL, '/') : 'https://acp.erocloud.net') . '/Startseite#/merchants';
        header('Location: ' . $acpTarget);
        exit;
    }

    $isLegacyGroupLogin = (isset($_SESSION['logged_in_as']) && $_SESSION['logged_in_as'] === 'group');
    $logoutUrl          = $isLegacyGroupLogin ? MCP_URL : (LOGIN_URL . '/?logout&ref=erocloud');

    if (ini_get('session.use_cookies') && !headers_sent()) {
        $sessParams = session_get_cookie_params();
        @setcookie(session_name(), '', [
            'expires'  => time() - 86400,
            'path'     => $sessParams['path'] ?: '/',
            'domain'   => $sessParams['domain'] ?: '',
            'secure'   => (bool)$sessParams['secure'],
            'httponly' => (bool)$sessParams['httponly'],
            'samesite' => $sessParams['samesite'] ?: 'Lax',
        ]);
    }

    $_SESSION = [];
    @session_destroy();
    header('Location: ' . $logoutUrl);
    exit;
}

// 1b. Direkt-Login über gesicherten Magic-Link (E-Mail / Push-Benachrichtigung)
if (isset($_GET['ml']) && trim((string)$_GET['ml']) !== '' && class_exists('EroCloudMagicLink')) {
    EroCloudMagicLink::handleRequest((string)$_GET['ml']);
}

// 2. Authentifizierungs-Prüfung (inkl. automatischer Session-Recovery aus bootstrap.php)
if (!function_exists('is_logged_in') || is_logged_in('mcp') !== true || empty($_SESSION['merchant_id'])) {
    // 2a. Öffentliche Präsentations-/Landingpage für Gäste ausschließlich auf der Root-URL (https://erocloud.net/)
    //     Alle übrigen Pfade (z. B. /Startseite-Lesezeichen) leiten wie bisher direkt zum Pay4Coins-Login weiter.
    $guestReqPath   = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $guestScriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($guestScriptDir !== '' && stripos($guestReqPath, $guestScriptDir) === 0) {
        $guestReqPath = (string)substr($guestReqPath, strlen($guestScriptDir));
    }
    if (in_array($guestReqPath, ['', '/', '/index.php'], true)) {
        $guestRawBase = $guestScriptDir;
        $guestUri     = (string)($_SERVER['REQUEST_URI'] ?? '');
        $guestBase    = (stripos($guestUri, '/mcp_neu') === 0 || stripos($guestUri, '/mcp/mcp_neu') !== false) ? $guestRawBase : '';
        echo \App\Core\View::render('Home', 'guest', [
            'basePath'    => $guestBase,
            'imprintData' => class_exists('EroCloudMailer') ? EroCloudMailer::getImprintData() : [],
        ]);
        exit;
    }

    header('Location: ' . LOGIN_URL . '?ref=erocloud');
    exit;
}

// 3. Echte Partner- und Profildaten für Topbar, Sidebar und Modals laden
$merchantId       = abs((int)$_SESSION['merchant_id']);
$merchant         = new Merchant($mysql, $merchantId);
$merchantUsername = isset($_SESSION['merchant_username']) && trim((string)$_SESSION['merchant_username']) !== ''
    ? (string)$_SESSION['merchant_username']
    : (string)$merchant->username('aes_decrypt');

$initials = mb_strtoupper(mb_substr(trim($merchantUsername), 0, 2));
if ($initials === '') {
    $initials = 'EC';
}

$apiKey           = (string)$merchant->api_key('aes_decrypt');
$partnerId        = (string)$merchant->partner_id();
$minActorProfiles = abs((int)$merchant->minimum_number_actor_profiles());

// Darsteller-Profile des Partners für die Sidebar laden
$sidebarActors = [];
$rsActors = p4c_query("SELECT `id`, `username`, `profile_image_fsk16`
    FROM `actors`
    WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
    ORDER BY `username` ASC;", __FILE__, __LINE__);

if ($rsActors && p4c_num_rows($rsActors) > 0) {
    while ($actorObj = p4c_fetch_object($rsActors)) {
        $sidebarActors[] = [
            'id'           => abs((int)$actorObj->id),
            'username'     => (string)$actorObj->username,
            'avatar_fsk16' => (string)$actorObj->profile_image_fsk16,
        ];
    }
}
$countActors = count($sidebarActors);

// Anzahl der Filme in der Cloud für den Sidebar-Zähler laden
$countMovies = 0;
if ($countActors > 0) {
    $rsMoviesCount = p4c_query("SELECT COUNT(*) AS `cnt`
        FROM `movies`
        WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
    if ($rsMoviesCount && p4c_num_rows($rsMoviesCount) > 0) {
        $cntObj = p4c_fetch_object($rsMoviesCount);
        $countMovies = abs((int)($cntObj->cnt ?? 0));
    }
}

// Anzahl der angelegten Drehpartner für den Sidebar-Zähler laden
\CoActorManager::ensureSchema();
$countCoActors = 0;
$rsCoActorsCount = p4c_query("SELECT COUNT(*) AS `cnt`
    FROM `co_actors`
    WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' AND `is_owner` = 0;", __FILE__, __LINE__);
if ($rsCoActorsCount && p4c_num_rows($rsCoActorsCount) > 0) {
    $cntCaObj = p4c_fetch_object($rsCoActorsCount);
    $countCoActors = abs((int)($cntCaObj->cnt ?? 0));
}

// Prüfen, ob Partnerprogramm für diesen Partner aktiv ist
$showWebmasterMenu = in_array($partnerId, ['ABPP2HNMDG', 'KJDJEEESAX', 'CCRVWMVD67', '9SN2XTH2TU'], true);

$imprintData  = class_exists('EroCloudMailer') ? EroCloudMailer::getImprintData() : [];
$notifMatrix  = class_exists('EroCloudNotificationCenter') ? EroCloudNotificationCenter::getPreferencesMatrix($merchantId) : [];
$mcpUrl       = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
$p4cMcpUrl    = defined('Pay4Coins_MCP_URL') ? Pay4Coins_MCP_URL : 'https://merchant.pay4coins.com';
$supportEmail = (string)($imprintData['imprint_support_email'] ?? (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@pay4coins.com'));
$supportPhone = (string)($imprintData['imprint_phone'] ?? (defined('SUPPORT_PHONE_NUMBER') ? SUPPORT_PHONE_NUMBER : '+34 616 571 860'));
$whatsappUrl  = (string)($imprintData['imprint_whatsapp_url'] ?? 'https://wa.me/34616571860');
$companyName  = (string)($imprintData['imprint_company_name'] ?? (defined('COMPANYNAME') ? COMPANYNAME : 'CIPA Media S.L.U.'));
$projectName  = defined('PROJECTNAME') ? PROJECTNAME : 'EroCloud';
$siteUrl      = defined('URL') ? URL : 'https://erocloud.net';
$imprintUrl   = (string)($imprintData['imprint_external_url'] ?? 'https://pay4coins.com/de/impressum');

$csrfToken   = Csrf::token();
$rawBasePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$requestUri  = (string)($_SERVER['REQUEST_URI'] ?? '');
$basePath    = (stripos($requestUri, '/mcp_neu') === 0 || stripos($requestUri, '/mcp/mcp_neu') !== false) ? $rawBasePath : '';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meine <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> – Partner-Bereich</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="96x96" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/favicon-96x96.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/apple-touch-icon.png">

  <!-- CSRF-Schutz Token & Basis-Pfad für Vanilla JS fetch() Anfragen -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="app-base-path" content="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Lokale Stylesheets (100% DSGVO / Keine externen CDNs) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/fonts.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/theme.css') ?>">

  <!-- Theme-Soforterkennung (Standard: Light Mode) -->
  <script>
    (function() {
      const savedTheme = localStorage.getItem('ec-mcp-theme') || 'light';
      document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
  </script>
</head>
<body>

<div class="app-wrapper">

  <!-- ========================================================================
       SIDEBAR (Echte Partner-Navigation)
       ======================================================================== -->
  <aside class="app-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
      <a href="#/startseite" class="d-flex align-items-center gap-2 text-decoration-none">
        <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/erocloud_logo.png" alt="EroCloud" style="height: 34px; width: auto; object-fit: contain;">
        <div>
          <div class="fw-bold lh-1" style="font-size: 0.95rem; color: var(--kkc-text);">Meine EroCloud</div>
          <small style="font-size: 0.68rem; color: var(--kkc-text-muted);">Partner Control Panel</small>
        </div>
      </a>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <div class="sidebar-heading">Hauptmenü</div>

      <!-- 1. Meine Startseite -->
      <a href="#/startseite" class="sidebar-link active" data-route="#/startseite">
        <i class="bi bi-house-door-fill"></i>
        <span>Meine Startseite</span>
      </a>

      <?php if ($countActors > 0): ?>
      <!-- 2. Content hochladen (Standardmäßig geschlossen) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-upload" role="button" aria-expanded="false" aria-controls="submenu-upload">
        <i class="bi bi-cloud-arrow-up-fill"></i>
        <span class="text-nowrap">Content hochladen</span>
        <i class="bi bi-chevron-right chevron-icon"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-upload">
        <a href="#/movie-upload" class="sidebar-link" data-route="#/movie-upload">
          <i class="bi bi-plus-square-fill"></i>
          <span>Film hochladen</span>
        </a>
        <a href="#/photo-album-upload" class="sidebar-link" data-route="#/photo-album-upload">
          <i class="bi bi-file-earmark-image-fill"></i>
          <span>Fotos hochladen</span>
        </a>
      </div>

      <!-- 3. Content in der Cloud (Standardmäßig geschlossen, mit echtem Zähler) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-cloud" role="button" aria-expanded="false" aria-controls="submenu-cloud">
        <i class="bi bi-cloud-fill"></i>
        <span class="text-nowrap">Content in der Cloud</span>
        <span id="sidebar-movies-count" class="sidebar-count-badge ms-auto me-1" title="Anzahl deiner Filme in der Cloud"><?= (int)$countMovies ?></span>
        <i class="bi bi-chevron-right chevron-icon ms-0"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-cloud">
        <a href="#/movies" class="sidebar-link" data-route="#/movies">
          <i class="bi bi-collection-play-fill"></i>
          <span>Filme in der Cloud</span>
        </a>
        <a href="#/photo-albums" class="sidebar-link" data-route="#/photo-albums">
          <i class="bi bi-images"></i>
          <span>Fotos in der Cloud</span>
        </a>
      </div>
      <?php endif; ?>

      <!-- 4. Profile (Standardmäßig geschlossen, mit echten Darsteller-Profilen) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-actors" role="button" aria-expanded="false" aria-controls="submenu-actors">
        <i class="bi bi-person-circle"></i>
        <span class="text-nowrap">Profile</span>
        <i class="bi bi-chevron-right chevron-icon"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-actors">
        <?php if ($countActors > 1): ?>
        <a href="#/actors" class="sidebar-link" data-route="#/actors">
          <i class="bi bi-people-fill"></i>
          <span>Alle Profile anzeigen</span>
        </a>
        <?php endif; ?>

        <?php if ($countActors < $minActorProfiles): ?>
        <a href="#/new-actor" class="sidebar-link" data-route="#/new-actor">
          <i class="bi bi-person-plus-fill"></i>
          <span>Profil anlegen</span>
        </a>
        <?php endif; ?>

        <?php foreach ($sidebarActors as $sActor): ?>
        <?php
          $sAvatarUrl = $sActor['avatar_fsk16'] !== ''
              ? $mcpUrl . '/ProfilePicture/' . rawurlencode($sActor['avatar_fsk16']) . '?w=60'
              : '';
          $sInitials = mb_strtoupper(mb_substr($sActor['username'], 0, 2));
          $sRoute = '#/actor?id=' . (int)$sActor['id'];
        ?>
        <a href="<?= htmlspecialchars($sRoute, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-link" data-route="<?= htmlspecialchars($sRoute, ENT_QUOTES, 'UTF-8') ?>" data-actor-id="<?= (int)$sActor['id'] ?>">
          <?php if ($sAvatarUrl !== ''): ?>
          <img src="<?= htmlspecialchars($sAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="sidebar-actor-img rounded-circle object-fit-cover flex-shrink-0 border" style="width: 22px; height: 22px;" loading="lazy">
          <?php else: ?>
          <span class="sidebar-actor-avatar"><?= htmlspecialchars($sInitials, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <span class="text-truncate"><?= htmlspecialchars($sActor['username'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- 4b. Drehpartner & ID-Check (Separater Menüpunkt in der Sidebar) -->
      <a href="#/co-actors" class="sidebar-link" data-route="#/co-actors">
        <i class="bi bi-people-fill"></i>
        <span class="text-nowrap">Drehpartner</span>
        <span id="sidebar-co-actors-count" class="sidebar-count-badge ms-auto" title="Anzahl deiner angelegten Drehpartner"><?= (int)$countCoActors ?></span>
      </a>

      <?php if ($showWebmasterMenu): ?>
      <!-- 5. Partnerprogramm (Nur für freigeschaltete Bestands-Partner) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-webmaster" role="button" aria-expanded="false" aria-controls="submenu-webmaster">
        <i class="bi bi-currency-euro"></i>
        <span class="text-nowrap">Partnerprogramm</span>
        <i class="bi bi-chevron-right chevron-icon"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-webmaster">
        <a href="#/webmaster-ads" class="sidebar-link" data-route="#/webmaster-ads">
          <i class="bi bi-megaphone-fill"></i>
          <span>Kunden werben</span>
        </a>
        <a href="#/webmaster-newads" class="sidebar-link" data-route="#/webmaster-newads">
          <i class="bi bi-layers-fill"></i>
          <span>Alle Banner</span>
        </a>
        <a href="#/webmaster-stats" class="sidebar-link" data-route="#/webmaster-stats">
          <i class="bi bi-graph-up-arrow"></i>
          <span>Statistiken</span>
        </a>
        <a href="#/webmaster-advertise-partner" class="sidebar-link" data-route="#/webmaster-advertise-partner">
          <i class="bi bi-person-heart"></i>
          <span>Darsteller werben</span>
        </a>
      </div>
      <?php endif; ?>

      <!-- 6. Abrechnungen und Meine Daten (Pay4Coins Box) -->
      <div class="sidebar-heading mt-3">Abrechnungen &amp; Meine Daten</div>
      <div class="sidebar-p4c-box text-center">
        <a href="<?= htmlspecialchars($p4cMcpUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-decoration-none d-block">
          <div class="fw-bold mb-1" style="font-size: 0.95rem; color: var(--kkc-text);">
            pay<span style="color: #4297d7;">4</span><span class="fw-normal">coins</span>
          </div>
          <span class="btn btn-sm btn-outline-kkc w-100 py-1" style="font-size: 0.75rem;">
            <i class="bi bi-box-arrow-up-right me-1"></i> Pay4Coins öffnen
          </span>
        </a>
      </div>

      <!-- 7. Support & Rechtliches -->
      <div class="sidebar-heading mt-3">Hilfe &amp; Informationen</div>

      <a href="#/faq" class="sidebar-link" data-route="#/faq">
        <i class="bi bi-question-circle-fill"></i>
        <span>FAQ's</span>
      </a>

      <a href="javascript:void(0)" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#modal-notifications">
        <i class="bi bi-bell-fill"></i>
        <span>Benachrichtigungen</span>
      </a>

      <a href="javascript:void(0)" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#modal-api-key">
        <i class="bi bi-key-fill"></i>
        <span>Mein API-Key</span>
      </a>

      <a href="javascript:void(0)" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#modal-support">
        <i class="bi bi-headset"></i>
        <span>Support &amp; Kontakt</span>
      </a>

      <a href="javascript:void(0)" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#modal-imprint">
        <i class="bi bi-info-circle-fill"></i>
        <span>Impressum</span>
      </a>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer text-center">
      <div class="fw-semibold" style="color: var(--kkc-text);"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> ist ein Service der</div>
      <div style="font-size: 0.72rem; color: var(--kkc-text-muted);"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </aside>

  <!-- ========================================================================
       MAIN APP AREA
       ======================================================================== -->
  <div class="app-main">

    <!-- Topbar -->
    <header class="app-topbar">
      <!-- Schnellsuche (Filme & Fotoalben mit Live-Dropdown) -->
      <div class="topbar-search position-relative d-none d-sm-block" id="global-search-wrapper">
        <i class="bi bi-search search-icon"></i>
        <input type="text"
               id="global-search-input"
               class="form-control pe-4"
               placeholder="Schnellsuche nach Filmen &amp; Fotoalben (#ID, Titel, Darsteller)..."
               autocomplete="off"
               aria-label="Schnellsuche nach Filmen und Fotoalben">
        <button type="button"
                id="global-search-clear"
                class="btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y me-1 p-0 px-1 d-none"
                title="Suche zurücksetzen">
          <i class="bi bi-x-circle-fill"></i>
        </button>
        <div id="global-search-dropdown" class="ec-global-search-dropdown d-none" role="listbox" aria-label="Suchergebnisse"></div>
      </div>

      <!-- Quick-Actions & Partner-Menü -->
      <div class="d-flex align-items-center gap-2 gap-md-3 ms-auto">
        <?php if (!empty($_SESSION['mcp_impersonated_by_admin']) && is_array($_SESSION['mcp_impersonated_by_admin'])): ?>
        <?php
          $impAdminName = (string)($_SESSION['mcp_impersonated_by_admin']['username'] ?? 'Admin');
        ?>
        <div class="d-flex align-items-center gap-2 px-2 py-1 rounded border border-warning bg-warning bg-opacity-10 small"
             title="Du bist als Admin (<?= htmlspecialchars($impAdminName, ENT_QUOTES, 'UTF-8') ?>) in der Partner-Session von <?= htmlspecialchars($merchantUsername, ENT_QUOTES, 'UTF-8') ?> (MID #<?= (int)$merchantId ?>) eingeloggt">
          <span class="d-none d-lg-inline text-body">
            <i class="bi bi-shield-lock-fill text-warning me-1"></i>Admin-Ansicht: <strong><?= htmlspecialchars($merchantUsername, ENT_QUOTES, 'UTF-8') ?> (#<?= (int)$merchantId ?>)</strong>
          </span>
          <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php?logout=admin"
             class="btn btn-xs btn-warning py-0 px-2 fw-semibold text-decoration-none"
             style="font-size: 0.75rem;"
             title="Partner-Session beenden und zurück ins ACP wechseln">
            <i class="bi bi-box-arrow-left me-1"></i>Session beenden
          </a>
        </div>
        <?php endif; ?>

        <?php if ($countActors > 0): ?>
        <a href="#/movie-upload" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-1">
          <i class="bi bi-cloud-arrow-up-fill"></i>
          <span>Film hochladen</span>
        </a>
        <div class="vr my-2"></div>
        <?php endif; ?>

        <!-- Benachrichtigungs-Einstellungen (E-Mail & Push) -->
        <button type="button" class="btn btn-outline-kkc" data-bs-toggle="modal" data-bs-target="#modal-notifications" title="Deine Benachrichtigungen verwalten">
          <i class="bi bi-bell-fill text-secondary"></i>
        </button>

        <!-- Theme Toggle Button (Hell / Dunkel) -->
        <button id="theme-toggle" class="btn btn-outline-kkc" title="Design wechseln">
          <i id="theme-icon" class="bi bi-moon-stars-fill text-secondary"></i>
        </button>

        <!-- Partner Dropdown -->
        <div class="dropdown">
          <button class="btn btn-sm btn-link text-decoration-none dropdown-toggle d-flex align-items-center gap-2 p-0 text-body" data-bs-toggle="dropdown">
            <div class="rounded-circle ec-bg-primary-subtle ec-text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.82rem;">
              <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text-start d-none d-md-block">
              <div class="fw-semibold lh-1" style="font-size: 0.825rem; color: var(--kkc-text);">
                <?= htmlspecialchars($merchantUsername, ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size: 0.7rem; color: var(--kkc-text-muted);">Eingeloggt als Partner</div>
            </div>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg border-secondary border-opacity-25">
            <li><h6 class="dropdown-header">Eingeloggt als: <?= htmlspecialchars($merchantUsername, ENT_QUOTES, 'UTF-8') ?></h6></li>
            <li>
              <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modal-notifications">
                <i class="bi bi-bell me-2 text-muted"></i>Benachrichtigungen
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modal-api-key">
                <i class="bi bi-key me-2 text-muted"></i>Mein API-Key
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= htmlspecialchars($p4cMcpUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="bi bi-credit-card me-2 text-muted"></i>Abrechnungen (Pay4Coins)
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-danger" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php?logout">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <!-- App Content Body (SPA Container für dynamische Fragmente ohne Reload) -->
    <main id="app-content" class="app-content">
      <div class="d-flex align-items-center justify-content-center p-5">
        <div class="spinner-border ec-text-primary me-2" role="status"></div>
        <span class="text-muted">Inhalte werden geladen...</span>
      </div>
    </main>
  </div>
</div>

<!-- ========================================================================
     MODALS (Mein API-Key, Support & Kontakt, Impressum)
     ======================================================================== -->

<!-- Modal: Benachrichtigungs-Einstellungen (Zentrales E-Mail & Push Center) -->
<div class="modal fade" id="modal-notifications" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-bell-fill ec-text-primary me-2"></i>Meine Benachrichtigungen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-mcp-notifications">
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Lege hier fest, über welche Ereignisse und Neuigkeiten wir dich per <strong>E-Mail</strong> informieren dürfen. Deine Einstellungen gelten sofort für dein gesamtes Creator-Konto:
          </p>
          <div class="list-group mb-3">
            <?php foreach ($notifMatrix as $evKey => $meta): ?>
              <?php
              $emailChecked = !empty($meta['channels']['email']);
              $safeEvKey    = htmlspecialchars((string)$evKey, ENT_QUOTES, 'UTF-8');
              $safeIcon     = htmlspecialchars((string)($meta['icon'] ?? 'bi-bell'), ENT_QUOTES, 'UTF-8');
              $safeBadge    = htmlspecialchars((string)($meta['badge'] ?? ''), ENT_QUOTES, 'UTF-8');
              $safeTitle    = htmlspecialchars((string)($meta['title'] ?? ''), ENT_QUOTES, 'UTF-8');
              $safeDesc     = htmlspecialchars((string)($meta['description'] ?? ''), ENT_QUOTES, 'UTF-8');
              ?>
              <div class="list-group-item p-3">
                <div class="row align-items-center g-3">
                  <div class="col-12 col-md-7">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                        <i class="bi <?= $safeIcon ?> me-1"></i><?= $safeBadge ?>
                      </span>
                    </div>
                    <div class="fw-bold text-body mb-1"><?= $safeTitle ?></div>
                    <div class="small text-muted"><?= $safeDesc ?></div>
                  </div>
                  <div class="col-12 col-md-5">
                    <div class="d-flex justify-content-md-end align-items-center gap-4 pt-2 pt-md-0">
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="notif_email_<?= $safeEvKey ?>"
                               name="email_events[]"
                               value="<?= $safeEvKey ?>"
                               <?= $emailChecked ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold" for="notif_email_<?= $safeEvKey ?>">
                          <i class="bi bi-envelope me-1 text-primary"></i>E-Mail
                        </label>
                      </div>
                      <div class="form-check form-switch mb-0 opacity-50" title="Push-Benachrichtigungen folgen in Kürze">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="notif_push_<?= $safeEvKey ?>"
                               disabled>
                        <label class="form-check-label small text-muted" for="notif_push_<?= $safeEvKey ?>">
                          <i class="bi bi-bell me-1"></i>Push <span class="badge bg-secondary bg-opacity-10 text-secondary border ms-1" style="font-size: 0.65rem;">In Kürze</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3 border bg-light small text-muted">
            <i class="bi bi-info-circle text-primary"></i>
            <span><strong>Ausblick:</strong> Push-Benachrichtigungen direkt auf dein Smartphone oder deinen Browser stehen dir in Kürze zusätzlich zur Verfügung.</span>
          </div>
        </div>
        <div class="modal-footer border-top d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-sm btn-primary-kkc px-4" id="btn-save-mcp-notifications">
            <i class="bi bi-check2-circle me-1"></i> Einstellungen speichern
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Mein API-Key (Mit echtem entschlüsselten API-Key aus der DB) -->
<div class="modal fade" id="modal-api-key" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-key-fill ec-text-primary me-2"></i>Mein <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?>-API-Key
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small text-muted">Dein persönlicher API-Schlüssel:</label>
        <div class="input-group mb-2">
          <input type="text" id="input-api-key" class="form-control font-monospace text-center fw-bold" value="<?= htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') ?>" readonly>
          <button type="button" id="btn-copy-api-key" class="btn btn-primary-kkc">
            <i class="bi bi-clipboard-check me-1"></i> Kopieren
          </button>
        </div>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 small mb-3">
          <i class="bi bi-exclamation-triangle-fill fs-5"></i>
          <span>Behandle den API-Key bitte stets vertraulich wie die PIN für dein Bankkonto.</span>
        </div>
        <p class="text-muted small mb-0">
          Der API-Key ermöglicht es dir, Darsteller und deren Content aus der EroCloud direkt auf deine eigene Webseite zu importieren.
        </p>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Support & Kontakt -->
<div class="modal fade" id="modal-support" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-headset ec-text-primary me-2"></i>Support &amp; Kontakt
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Bei Fragen zu deinem Account, zur Freischaltung von Profilen oder zum Upload von Filmen und Fotoalben steht dir unser Support-Team gerne zur Verfügung:
        </p>
        <div class="p-3 rounded border mb-3" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center gap-3 mb-3">
            <i class="bi bi-envelope-fill ec-text-primary fs-5"></i>
            <div>
              <div class="small text-muted">E-Mail Support:</div>
              <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="fw-semibold text-decoration-none ec-text-primary">
                <?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-whatsapp text-success fs-5"></i>
            <div>
              <div class="small text-muted">Tel / WhatsApp (Business):</div>
              <a href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="fw-semibold text-decoration-none text-success">
                <?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Impressum (Zentral aus Datenbank settings geladen) -->
<div class="modal fade" id="modal-imprint" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-info-circle-fill ec-text-primary me-2"></i>Impressum
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body small text-muted">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="fw-bold text-body"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
            <div><?= htmlspecialchars((string)($imprintData['imprint_address_street'] ?? 'Av. Emilio Luque Moreno 19 Oficina 6'), ENT_QUOTES, 'UTF-8') ?></div>
            <div><?= htmlspecialchars((string)($imprintData['imprint_address_city'] ?? 'ES-38300 La Orotava (S/C de Tenerife)'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-6">
            <div class="fw-bold text-body">Handelsregister Teneriffa:</div>
            <div><?= htmlspecialchars((string)($imprintData['imprint_register_court'] ?? 'Registro Mercantil de Santa Cruz de Tenerife'), ENT_QUOTES, 'UTF-8') ?></div>
            <div><?= htmlspecialchars((string)($imprintData['imprint_register_details'] ?? 'Tomo: 3368, Folio: 43, Sección: 8, Hoja: TF-54807, Inscripción: 8'), ENT_QUOTES, 'UTF-8') ?></div>
            <div><?= htmlspecialchars((string)($imprintData['imprint_vat_id'] ?? 'CIF: B76659168'), ENT_QUOTES, 'UTF-8') ?></div>
            <div>Geschäftsführung: <?= htmlspecialchars((string)($imprintData['imprint_ceo'] ?? 'Christina Toepel'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>

        <div class="mb-3">
          <div class="fw-bold text-body">Kontakt &amp; Informationen für Kunden, Verkäufer und Händler:</div>
          <div>Tel / WhatsApp (Business): <a href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="ec-text-primary"><?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?></a></div>
          <div>E-Mail: <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="ec-text-primary"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a></div>
          <div>Website: <a href="<?= htmlspecialchars((string)($imprintData['imprint_website_url'] ?? 'https://pay4coins.com'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="ec-text-primary"><?= htmlspecialchars((string)($imprintData['imprint_website_url'] ?? 'https://pay4coins.com'), ENT_QUOTES, 'UTF-8') ?></a></div>
        </div>

        <div class="mb-2">
          <div class="fw-bold text-body">Inhaltliche Verantwortung</div>
          <div>Die Inhalte dieser Website wurden mit Sorgfalt erstellt und gepflegt. Wir übernehmen jedoch keine Gewähr für die Richtigkeit, Vollständigkeit oder Aktualität der bereitgestellten Informationen.</div>
        </div>

        <div class="mb-2">
          <div class="fw-bold text-body">Externe Links</div>
          <div>Diese Website kann Links zu externen Websites Dritter enthalten. Auf deren Inhalte haben wir keinen Einfluss und übernehmen daher keine Gewähr für diese fremden Inhalte.</div>
        </div>

        <div>
          <div class="fw-bold text-body">Urheberrecht</div>
          <div>Alle auf dieser Website veröffentlichten Inhalte sind urheberrechtlich geschützt. Jede Nutzung außerhalb der Grenzen des Urheberrechts bedarf der vorherigen schriftlichen Zustimmung der <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>.</div>
        </div>
      </div>
      <div class="modal-footer border-top d-flex justify-content-between">
        <a href="<?= htmlspecialchars($imprintUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-box-arrow-up-right me-1"></i> Vollständiges Impressum auf Pay4Coins öffnen
        </a>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Globales MCP Dialog-Modal (Info / Success / Warning / Danger für Confirm, Prompt & Alert) -->
<div class="modal fade" id="ec-global-modal" tabindex="-1" aria-labelledby="ec-global-modal-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content card-kkc border-0 shadow-lg">
      <div class="modal-header border-bottom py-3 px-4" style="border-color: var(--kkc-border) !important;">
        <div class="d-flex align-items-center gap-2">
          <span id="ec-global-modal-icon-box" class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
            <i id="ec-global-modal-icon" class="bi bi-info-circle-fill fs-5"></i>
          </span>
          <h5 class="modal-title fw-bold mb-0" id="ec-global-modal-title">Hinweis</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-4">
        <div id="ec-global-modal-message" class="text-body"></div>

        <!-- Optionale Texteingabe (für App.prompt) -->
        <div id="ec-global-modal-prompt-wrap" class="mt-3 d-none">
          <textarea id="ec-global-modal-prompt-input" class="form-control" rows="3"></textarea>
        </div>

        <!-- Optionale Checkbox (z. B. Zusatz-Häkchen bei App.confirm) -->
        <div id="ec-global-modal-checkbox-wrap" class="form-check d-flex align-items-start gap-2 p-3 rounded border mt-3 mb-0 d-none" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <input class="form-check-input mt-1 ms-0 flex-shrink-0" type="checkbox" value="1" id="ec-global-modal-checkbox">
          <label class="form-check-label" for="ec-global-modal-checkbox" style="cursor: pointer;">
            <span id="ec-global-modal-checkbox-label" class="fw-semibold d-block"></span>
            <span id="ec-global-modal-checkbox-subtext" class="small text-muted d-block mt-1 d-none"></span>
          </label>
        </div>
      </div>
      <div class="modal-footer border-top py-3 px-4 d-flex justify-content-between" style="border-color: var(--kkc-border) !important;">
        <button type="button" class="btn btn-sm btn-outline-kkc" id="ec-global-modal-cancel" data-bs-dismiss="modal">
          Abbrechen
        </button>
        <button type="button" class="btn btn-sm btn-primary-kkc fw-semibold ms-auto" id="ec-global-modal-confirm">
          Bestätigen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Lokale JavaScripts (100% DSGVO / No-CDN) -->
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/app.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>

</body>
</html>
<?php
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
?>
