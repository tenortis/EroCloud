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

// Benutzerdaten aus aktiver MCP-Session übernehmen (oder Vorschau-Werte für den Layout-Test)
$merchantUsername = isset($_SESSION['merchant_username']) && trim((string)$_SESSION['merchant_username']) !== ''
    ? (string)$_SESSION['merchant_username']
    : 'Mustermann-Partner';

$initials = mb_strtoupper(mb_substr($merchantUsername, 0, 2));
if ($initials === '') {
    $initials = 'EC';
}

$p4cMcpUrl = defined('Pay4Coins_MCP_URL') ? Pay4Coins_MCP_URL : 'https://merchant.pay4coins.com';
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'techsupport@pay4coins.com';
$supportPhone = defined('SUPPORT_PHONE_NUMBER') ? SUPPORT_PHONE_NUMBER : '+49 (0)6106 625 918 27';
$companyName = defined('COMPANYNAME') ? COMPANYNAME : 'CIPA MEDIA S.L.';
$projectName = defined('PROJECTNAME') ? PROJECTNAME : 'EroCloud';

$csrfToken = Csrf::token();
$basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meine <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> – Partner-Bereich</title>

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
       SIDEBAR (Alle Menüeinträge aus der aktuellen MCP-Version)
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

      <!-- 3. Content in der Cloud (Standardmäßig geschlossen, einzeilig mit Zähler-Badge) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-cloud" role="button" aria-expanded="false" aria-controls="submenu-cloud">
        <i class="bi bi-cloud-fill"></i>
        <span class="text-nowrap">Content in der Cloud</span>
        <span class="sidebar-count-badge ms-auto me-1" title="Anzahl Filme in der Cloud">24</span>
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

      <!-- 4. Profile (Standardmäßig geschlossen) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-actors" role="button" aria-expanded="false" aria-controls="submenu-actors">
        <i class="bi bi-person-circle"></i>
        <span class="text-nowrap">Profile</span>
        <i class="bi bi-chevron-right chevron-icon"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-actors">
        <a href="#/actors" class="sidebar-link" data-route="#/actors">
          <i class="bi bi-people-fill"></i>
          <span>Alle Profile anzeigen</span>
        </a>
        <a href="#/new-actor" class="sidebar-link" data-route="#/new-actor">
          <i class="bi bi-person-plus-fill"></i>
          <span>Profil anlegen</span>
        </a>
        <!-- Beispiel-Darstellerprofile (wie im aktuellen MCP mit Avatar) -->
        <a href="#/actor?id=1&amp;name=SophiaLove" class="sidebar-link" data-route="#/actor?id=1&name=SophiaLove">
          <span class="sidebar-actor-avatar">SL</span>
          <span class="text-truncate">SophiaLove</span>
        </a>
        <a href="#/actor?id=2&amp;name=MiaSweet" class="sidebar-link" data-route="#/actor?id=2&name=MiaSweet">
          <span class="sidebar-actor-avatar">MS</span>
          <span class="text-truncate">MiaSweet</span>
        </a>
      </div>

      <!-- 5. Partnerprogramm (Aufklappbar) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-webmaster" role="button" aria-expanded="false" aria-controls="submenu-webmaster">
        <i class="bi bi-currency-euro"></i>
        <span>Partnerprogramm</span>
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
    <div class="sidebar-footer d-flex align-items-center justify-content-between">
      <div>
        <div class="fw-bold" style="color: var(--kkc-text);"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> v2.0</div>
        <div style="font-size: 0.68rem; color: var(--kkc-text-muted);"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <span class="badge-kkc badge-kkc-info">PHP 8.4 Ready</span>
    </div>
  </aside>

  <!-- ========================================================================
       MAIN APP AREA
       ======================================================================== -->
  <div class="app-main">

    <!-- Topbar -->
    <header class="app-topbar">
      <!-- Schnellsuche -->
      <div class="topbar-search position-relative d-none d-sm-block">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="global-search-input" class="form-control" placeholder="Schnellsuche nach Filmen, Fotoalben oder Profilen...">
      </div>

      <!-- Quick-Actions, Vorschau-Schalter & Partner-Menü -->
      <div class="d-flex align-items-center gap-2 gap-md-3 ms-auto">
        <!-- Vorschau-Schalter für die ausgeloggte Startseite -->
        <a href="#/guest" class="btn btn-sm btn-outline-kkc d-none d-lg-flex align-items-center gap-1" title="Vorschau der öffentlichen Startseite (nicht eingeloggt)">
          <i class="bi bi-eye ec-text-primary"></i>
          <span>Gast-Startseite testen</span>
        </a>

        <!-- Direktbutton: Film hochladen -->
        <a href="#/movie-upload" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-1">
          <i class="bi bi-cloud-arrow-up-fill"></i>
          <span>Film hochladen</span>
        </a>

        <div class="vr my-2"></div>

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
            <li><h6 class="dropdown-header">Partner: <?= htmlspecialchars($merchantUsername, ENT_QUOTES, 'UTF-8') ?></h6></li>
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
              <a class="dropdown-item text-danger" href="/index.php?logout">
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

<!-- Modal: Mein API-Key -->
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
        <label class="form-label small text-muted">Ihr persönlicher API-Schlüssel:</label>
        <div class="input-group mb-2">
          <input type="text" id="input-api-key" class="form-control font-monospace text-center fw-bold" value="A9F4K2M8X7P1Q6W3E5R0T2Y8U4I6O9L1" readonly>
          <button type="button" id="btn-copy-api-key" class="btn btn-primary-kkc">
            <i class="bi bi-clipboard-check me-1"></i> Kopieren
          </button>
        </div>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 small mb-3">
          <i class="bi bi-exclamation-triangle-fill fs-5"></i>
          <span>Behandeln Sie Ihren API-Key bitte stets vertraulich wie die PIN für Ihr Bankkonto.</span>
        </div>
        <p class="text-muted small mb-0">
          Der API-Key ermöglicht es Ihnen, Darsteller-Profile sowie deren Filme und Fotoalben aus der EroCloud direkt auf Ihre eigene Webseite zu importieren.
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
          Bei Fragen zu Ihrem Account, zur Freischaltung von Profilen oder zum Upload von Filmen und Fotoalben steht Ihnen unser Support-Team gerne zur Verfügung:
        </p>
        <div class="p-3 rounded border mb-3" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center gap-3 mb-2">
            <i class="bi bi-envelope-fill ec-text-primary fs-5"></i>
            <div>
              <div class="small text-muted">E-Mail Support:</div>
              <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="fw-semibold text-decoration-none ec-text-primary">
                <?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-telephone-fill ec-text-primary fs-5"></i>
            <div>
              <div class="small text-muted">Telefonischer Support:</div>
              <span class="fw-semibold"><?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?></span>
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

<!-- Modal: Impressum -->
<div class="modal fade" id="modal-imprint" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-info-circle-fill ec-text-primary me-2"></i>Impressum
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body small text-muted">
        <p class="fw-bold text-body mb-1"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> ist ein Service der <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="mb-2">
          Kontakt E-Mail: <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="ec-text-primary"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a><br>
          Telefon: <?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>

<!-- Lokale JavaScripts (100% DSGVO / No-CDN) -->
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/app.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>

</body>
</html>
