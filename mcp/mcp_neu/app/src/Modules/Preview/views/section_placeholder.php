<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var string $icon */
/** @var string $badge */
/** @var string $title */
/** @var string $subtitle */
/** @var string $routeKey */
/** @var string $legacyRoute */
?>
<!-- Section Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge-kkc badge-kkc-info">
        <i class="bi <?= View::e($icon) ?>"></i> <?= View::e($badge) ?>
      </span>
      <span class="badge-kkc badge-kkc-neutral font-monospace">Route: #/<?= View::e($routeKey) ?></span>
    </div>
    <h1 class="h3 mb-1"><?= View::e($title) ?></h1>
    <p class="text-muted mb-0"><?= View::e($subtitle) ?></p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="#/startseite" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-arrow-left me-1"></i> Zurück zur Startseite
    </a>
  </div>
</div>

<!-- Layout-Platzhalter Kachel für das gewählte Modul -->
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi <?= View::e($icon) ?> ec-text-primary"></i>
          <span>Arbeitsbereich: <?= View::e($title) ?> (Statischer Layout-Platzhalter)</span>
        </div>
        <span class="badge-kkc badge-kkc-warning">
          <i class="bi bi-tools"></i> Bereit für Modul-Umsetzung
        </span>
      </div>
      <div class="card-kkc-body">
        <div class="p-4 rounded border text-center mb-4" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.75rem;">
            <i class="bi <?= View::e($icon) ?>"></i>
          </div>
          <h5 class="fw-bold mb-2"><?= View::e($title) ?></h5>
          <p class="text-muted small mx-auto mb-3" style="max-width: 540px;">
            Diese Ansicht wurde per <strong>SPA-Fetch ohne Seiten-Reload</strong> geladen. Sobald du mit dem Grundgerüst und dem Farbschema zufrieden bist, wird hier die vollständige Geschäftslogik der bisherigen Route <code><?= View::e($legacyRoute) ?></code> implementiert.
          </p>
          <div class="d-flex justify-content-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary-kkc" onclick="App.toast('Beispiel-Aktion für „<?= View::e($title) ?>“ erfolgreich ausgelöst.', 'info')">
              <i class="bi bi-check2-circle me-1"></i> Interaktions-Test ausführen
            </button>
            <a href="#/startseite" class="btn btn-sm btn-outline-kkc">
              <i class="bi bi-house-door me-1"></i> Zum Dashboard
            </a>
          </div>
        </div>

        <!-- Beispielhafte Struktur-Vorschau je nach Bereich -->
        <div class="row g-3">
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="border-color: var(--kkc-border) !important;">
              <div class="fw-semibold mb-1"><i class="bi bi-check2-square ec-text-primary me-1"></i> Schritt 1: Basisdaten</div>
              <div class="text-muted small">Übersichtliche Formulareingaben mit direkter Validierung in der Du-Form.</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="border-color: var(--kkc-border) !important;">
              <div class="fw-semibold mb-1"><i class="bi bi-sliders ec-text-primary me-1"></i> Schritt 2: Optionen</div>
              <div class="text-muted small">Kategorien, Vorschau-Bilder (FSK16 / FSK18) und Preise komfortabel steuern.</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="border-color: var(--kkc-border) !important;">
              <div class="fw-semibold mb-1"><i class="bi bi-cloud-check ec-text-primary me-1"></i> Schritt 3: Cloud-Sync</div>
              <div class="text-muted small">Sofortige Speicherung ohne kompletten Seiten-Reload per AJAX.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-info-circle ec-text-primary"></i>
          <span>Modul-Informationen</span>
        </div>
      </div>
      <div class="card-kkc-body">
        <ul class="list-unstyled mb-0 small">
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">Menübereich:</span>
            <span class="fw-semibold"><?= View::e($badge) ?></span>
          </li>
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">SPA-Route:</span>
            <span class="font-monospace">#/<?= View::e($routeKey) ?></span>
          </li>
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">Alte MCP-URL:</span>
            <span class="font-monospace"><?= View::e($legacyRoute) ?></span>
          </li>
          <li class="d-flex justify-content-between py-2">
            <span class="text-muted">Ladeverhalten:</span>
            <span class="text-success fw-semibold"><i class="bi bi-lightning-fill"></i> Ohne Reload (AJAX)</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
