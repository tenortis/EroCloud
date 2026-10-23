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
/** @var string $legacyFile */
/** @var string $itemId */

$acpUrl = defined('ACP_URL') ? (string)ACP_URL : 'https://acp.erocloud.net';
?>
<!-- Seiten-Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
  <div>
    <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
      <i class="bi <?= View::e($icon) ?> ec-text-primary"></i>
      <span><?= View::e($title) ?></span>
    </h1>
    <p class="text-muted small mb-0"><?= View::e($subtitle) ?></p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="<?= View::e($acpUrl . $legacyRoute) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc" title="Ansicht im bisherigen ACP zum Vergleich öffnen">
      <i class="bi bi-box-arrow-up-right me-1"></i> Im alten ACP öffnen
    </a>
  </div>
</div>

<!-- Layout-Platzhalter Kachel für das gewählte ACP-Modul -->
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi <?= View::e($icon) ?> ec-text-primary"></i>
          <span>ACP-Modul: <?= View::e($title) ?> (Grundgerüst-Platzhalter)</span>
        </div>
        <span class="badge-kkc badge-kkc-warning">
          <i class="bi bi-tools"></i> Bereit für nächsten Modernisierungs-Step
        </span>
      </div>
      <div class="card-kkc-body">
        <div class="p-4 rounded border text-center mb-4" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.75rem;">
            <i class="bi <?= View::e($icon) ?>"></i>
          </div>
          <h5 class="fw-bold mb-2"><?= View::e($title) ?></h5>
          <p class="text-muted small mx-auto mb-3" style="max-width: 560px;">
            Diese Admin-Ansicht wurde per <strong>SPA-Fetch ohne Seiten-Reload</strong> geladen. Sobald du das Grundgerüst abgenommen hast, migrieren wir hier die vollständige Geschäftslogik aus <code><?= View::e($legacyFile) ?></code> (bisherige Route <code><?= View::e($legacyRoute) ?></code>) in einen eigenen modernen Controller.
          </p>
          <div class="d-flex justify-content-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary-kkc" onclick="App.toast('Interaktions-Test für „<?= View::e($title) ?>“ erfolgreich ausgelöst.', 'info')">
              <i class="bi bi-check2-circle me-1"></i> Interaktions-Test ausführen
            </button>
            <a href="<?= View::e($acpUrl . $legacyRoute) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc">
              <i class="bi bi-box-arrow-up-right me-1"></i> Bisherige Ansicht vergleichen
            </a>
            <a href="#/startseite" class="btn btn-sm btn-outline-kkc">
              <i class="bi bi-house-door me-1"></i> Zum Admin-Dashboard
            </a>
          </div>
        </div>

        <!-- Geplante Modernisierungs-Schwerpunkte -->
        <div class="row g-3">
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="border-color: var(--kkc-border) !important;">
              <div class="fw-semibold mb-1"><i class="bi bi-lightning-charge ec-text-primary me-1"></i> Ohne Reload (SPA)</div>
              <div class="text-muted small">Schnelle Filter, Tabellen-Sortierung und Prüf-Aktionen per Fetch-API statt veralteter jQuery-UI-Tabellen.</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="border-color: var(--kkc-border) !important;">
              <div class="fw-semibold mb-1"><i class="bi bi-shield-check ec-text-primary me-1"></i> PHP 8.4 &amp; UTF-8</div>
              <div class="text-muted small">Strikte Typisierung, <code>p4c_query(..., __FILE__, __LINE__)</code>, CSRF-Schutz und sauberes UTF-8 ohne Altlasten.</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="border-color: var(--kkc-border) !important;">
              <div class="fw-semibold mb-1"><i class="bi bi-palette ec-text-primary me-1"></i> Einheitliches Design</div>
              <div class="text-muted small">Identisches Kachel-Layout in Pay4Coins-Blau inkl. Hell-/Dunkelmodus wie im neuen Partnerbereich.</div>
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
          <span>Technische Modul-Daten</span>
        </div>
      </div>
      <div class="card-kkc-body">
        <ul class="list-unstyled mb-0 small">
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">Menübereich:</span>
            <span class="fw-semibold"><?= View::e($badge) ?></span>
          </li>
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">Neue SPA-Route:</span>
            <span class="font-monospace">#/<?= View::e($routeKey) ?></span>
          </li>
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">Alte ACP-URL:</span>
            <span class="font-monospace"><?= View::e($legacyRoute) ?></span>
          </li>
          <li class="d-flex justify-content-between py-2 border-bottom" style="border-color: var(--kkc-border) !important;">
            <span class="text-muted">Legacy-Quelldatei:</span>
            <span class="font-monospace"><?= View::e($legacyFile) ?></span>
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
