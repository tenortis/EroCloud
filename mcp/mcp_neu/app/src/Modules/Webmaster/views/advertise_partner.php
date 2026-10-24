<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var string $partnerId
 * @var string $partnerLink
 * @var list<array{username: string, alias: string, url: string}> $actorLinks
 */
?>
<div class="container-fluid py-3" id="mcp-webmaster-advertise-partner-page">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <span>Partnerprogramm</span>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold">Darsteller werben</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-people ec-text-primary"></i>
        <span>Darsteller & Partner werben</span>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="#/webmaster-ads" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-bullseye me-1"></i>Kunden werben
      </a>
      <a href="#/webmaster-stats" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-bar-chart-line me-1"></i>Statistiken
      </a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Linke Spalte: Funktionsweise & Konditionen -->
    <div class="col-lg-5">
      <div class="kkc-card p-4 h-100">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center bg-success-subtle text-success flex-shrink-0"
               style="width: 48px; height: 48px;">
            <i class="bi bi-gift fs-4"></i>
          </div>
          <div>
            <h2 class="h5 fw-bold mb-0">3 % monatliche Werbe-Gutschrift</h2>
            <div class="small text-muted">Dauerhafte Beteiligung an geworbenen Partnern</div>
          </div>
        </div>

        <ul class="list-unstyled d-flex flex-column gap-3 mb-0 small">
          <li class="d-flex align-items-start gap-2">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <span>
              Wirbt ein Darsteller oder Partner über seinen persönlichen Werbelink einen neuen Partner, erhält der Werber jeden Monat eine <strong>3 % Gutschrift</strong> auf die Auszahlungssumme des geworbenen Partners.
            </span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <span>
              Wird dieser Link auf deiner Website eingebunden, über die Einnahmen generiert werden, erhält der Betreiber der Website die Gutschrift direkt auf seinem <strong>Pay4Coins-Account</strong> gutgeschrieben.
            </span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <span>
              Wird über einen sozialen Kanal (z. B. Instagram, X/Twitter oder Facebook) ein neuer Partner geworben, erhält der Inhaber des jeweiligen Kanals die Gutschrift auf seinem Pay4Coins-Account gutgeschrieben.
            </span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <span>
              Nach der Registrierung des geworbenen Partners über den jeweiligen Link erfolgt die Zuordnung vollautomatisch durch uns.
            </span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Rechte Spalte: Persönliche Werbelinks -->
    <div class="col-lg-7">
      <div class="kkc-card p-4 h-100">
        <h2 class="h5 fw-bold mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-link-45deg ec-text-primary"></i>
          <span>Deine persönlichen Werbelinks</span>
        </h2>
        <p class="small text-muted mb-4">
          Kopiere einfach den passenden Link und teile ihn auf deiner Website, in deinem Profil oder direkt mit interessierten Darstellern und Partnern.
        </p>

        <!-- Als Partner -->
        <div class="border rounded p-3 mb-3 bg-body-tertiary bg-opacity-50">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-semibold d-flex align-items-center gap-2">
              <i class="bi bi-person-badge ec-text-primary"></i>
              <span>Als Partner (<code><?= View::e($partnerId) ?></code>)</span>
            </span>
            <span class="badge bg-primary-subtle ec-text-primary border border-primary-subtle">Pay4Coins</span>
          </div>
          <div class="input-group input-group-sm">
            <input type="text"
                   class="form-control font-monospace"
                   value="<?= View::e($partnerLink) ?>"
                   readonly>
            <button type="button"
                    class="btn btn-primary btn-wm-copy"
                    data-copy-text="<?= View::e($partnerLink) ?>">
              <i class="bi bi-clipboard me-1"></i>Link kopieren
            </button>
          </div>
        </div>

        <!-- Als Darsteller -->
        <?php if (!empty($actorLinks)): ?>
          <h3 class="h6 fw-bold mt-4 mb-2 text-muted">Werbelinks deiner Darsteller-Profile (ad5.tv)</h3>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($actorLinks as $al): ?>
              <div class="border rounded p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-person-video2 ec-text-primary"></i>
                    <span>Als <?= View::e($al['username']) ?></span>
                  </span>
                  <span class="badge bg-secondary-subtle text-secondary border">Darsteller-Link</span>
                </div>
                <div class="input-group input-group-sm">
                  <input type="text"
                         class="form-control font-monospace"
                         value="<?= View::e($al['url']) ?>"
                         readonly>
                  <button type="button"
                          class="btn btn-outline-primary btn-wm-copy"
                          data-copy-text="<?= View::e($al['url']) ?>">
                    <i class="bi bi-clipboard me-1"></i>Link kopieren
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</div>
