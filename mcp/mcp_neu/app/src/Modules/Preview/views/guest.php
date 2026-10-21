<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

$loginUrl = defined('LOGIN_URL') ? LOGIN_URL . '?ref=erocloud' : 'https://login.pay4coins.com?ref=erocloud';
$p4cRegisterCreator = defined('Pay4Coins_MCP_URL')
    ? Pay4Coins_MCP_URL . '/Partner-werden/Content-in-der-EroCloud-vermarkten'
    : 'https://merchant.pay4coins.com/Partner-werden/Content-in-der-EroCloud-vermarkten';
$p4cRegisterWebmaster = defined('Pay4Coins_MCP_URL')
    ? Pay4Coins_MCP_URL . '/Partner-werden/Betreiben-Sie-Ihr-eigenes-Erotikportal'
    : 'https://merchant.pay4coins.com/Partner-werden/Betreiben-Sie-Ihr-eigenes-Erotikportal';
?>
<!-- Vorschau-Hinweis für die ausgeloggte Startseite -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <span class="badge-kkc badge-kkc-info mb-1">
      <i class="bi bi-eye-fill"></i> Vorschau: Öffentliche Startseite (nicht eingeloggt)
    </span>
    <h1 class="h3 mb-1">Vermarkten Sie Ihre Filme und Fotoalben auf mehr als 100 Webseiten</h1>
    <p class="text-muted mb-0">
      Zentrale Cloud-Distribution für Darsteller, Produzenten, Studios und Webmaster – powered by Pay4Coins &amp; EroCMS.
    </p>
  </div>
  <div>
    <a href="#/startseite" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-grid-1x2 me-1"></i> Zurück zur eingeloggten Ansicht
    </a>
  </div>
</div>

<!-- Hero & Pay4Coins Login-Kachel (Ohne alten Studio-Login!) -->
<div class="card-kkc mb-4">
  <div class="card-kkc-body p-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="badge-kkc badge-kkc-info mb-2">
          <i class="bi bi-cloud-check-fill"></i> Gemeinschaftsprojekt von Pay4Coins &amp; EroCMS®
        </span>
        <h2 class="h4 fw-bold mb-3">Einmal hochladen – auf hunderten Portalen verkaufen</h2>
        <p class="text-muted mb-3">
          Die EroCloud ist für Sie die ideale Plattform, wenn Sie als <strong>Darsteller, Produzent oder Studiobetreiber</strong> Ihre Filme und Fotoalben zentral verwalten und automatisiert auf allen angeschlossenen Partnerseiten vermarkten möchten – oder wenn Sie als <strong>Webmaster</strong> hochwertigen Content für Ihr eigenes Portal suchen.
        </p>
        <div class="d-flex flex-wrap gap-3 small text-muted">
          <span><i class="bi bi-check-circle-fill text-success me-1"></i> Keine Duplizierung Ihrer Dateien</span>
          <span><i class="bi bi-check-circle-fill text-success me-1"></i> Automatische Auslieferung via Cloud</span>
          <span><i class="bi bi-check-circle-fill text-success me-1"></i> Zuverlässige Abrechnung über Pay4Coins</span>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="p-4 rounded border text-center" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border-strong) !important;">
          <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.6rem;">
            <i class="bi bi-shield-lock-fill"></i>
          </div>
          <h3 class="h5 fw-bold mb-2">Pay4Coins Partner-Login</h3>
          <p class="text-muted small mb-3">
            Melden Sie sich bequem und sicher mit Ihren bestehenden Pay4Coins-Zugangsdaten an.
          </p>
          <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary-kkc w-100 py-2 mb-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Jetzt über Pay4Coins einloggen
          </a>
          <div class="small text-muted mt-2">
            Noch kein Partner? <a href="<?= htmlspecialchars($p4cRegisterCreator, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="ec-text-primary fw-semibold text-decoration-none">Jetzt kostenlos registrieren</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 3 Zielgruppen-Kacheln -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-person-video ec-text-primary"></i>
          <span>Für Darsteller</span>
        </div>
      </div>
      <div class="card-kkc-body d-flex flex-column justify-content-between">
        <div>
          <p class="text-muted small">
            Sie möchten Ihren eigenen Content wie Filme und Fotoalben erfolgreich verkaufen – auch ohne eine eigene Webseite betreiben zu müssen?
          </p>
          <p class="small fw-semibold ec-text-primary">
            Mit der EroCloud vermarkten Sie sich und Ihre Inhalte sofort auf hunderten angeschlossenen Webseiten.
          </p>
        </div>
        <a href="<?= htmlspecialchars($p4cRegisterCreator, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc w-100 mt-2">
          Als Darsteller registrieren <i class="bi bi-arrow-up-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-globe2 ec-text-primary"></i>
          <span>Für Webmaster</span>
        </div>
      </div>
      <div class="card-kkc-body d-flex flex-column justify-content-between">
        <div>
          <p class="text-muted small">
            Sie möchten ein eigenes Erotik- oder Fetischportal betreiben, verfügen aber über keinen eigenen Content wie Filme oder Fotoalben?
          </p>
          <p class="small fw-semibold ec-text-primary">
            EroCMS in Verbindung mit der EroCloud ermöglicht es Ihnen, in wenigen Minuten ein eigenes Portal mit hunderten Videos online zu stellen.
          </p>
        </div>
        <a href="<?= htmlspecialchars($p4cRegisterWebmaster, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc w-100 mt-2">
          Als Webmaster registrieren <i class="bi bi-arrow-up-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-camera-reels ec-text-primary"></i>
          <span>Für Produzenten &amp; Studios</span>
        </div>
      </div>
      <div class="card-kkc-body d-flex flex-column justify-content-between">
        <div>
          <p class="text-muted small">
            Sie sind Produzent oder arbeiten in Ihrem Studio mit mehreren Darstellern zusammen und verfügen über umfangreichen Video- und Foto-Content?
          </p>
          <p class="small fw-semibold ec-text-primary">
            Verwalten Sie beliebig viele Darsteller-Profile zentral unter einem Account und steigern Sie Ihre Reichweite maximal.
          </p>
        </div>
        <a href="<?= htmlspecialchars($p4cRegisterCreator, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc w-100 mt-2">
          Als Produzent registrieren <i class="bi bi-arrow-up-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>
</div>
