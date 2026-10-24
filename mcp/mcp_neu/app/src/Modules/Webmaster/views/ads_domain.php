<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var int $siteId
 * @var string $domain
 * @var int $commision
 * @var string $partnerId
 * @var string $trackingUrl
 * @var string $textLinkCode
 * @var string $xmlApiUrl
 * @var list<array<string, mixed>> $banners
 * @var string $adsUrl
 */
?>
<div class="container-fluid py-3" id="mcp-webmaster-ads-domain-page">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <a href="#/webmaster-ads?tab=websites" class="text-decoration-none text-muted">Kunden werben</a>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold">Werbemittel: <?= View::e($domain) ?></span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-code-slash ec-text-primary"></i>
        <span>Werbemittel für <?= View::e($domain) ?></span>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="#/webmaster-ads?tab=websites" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu den Websites
      </a>
      <a href="#/webmaster-new-campaign?sid=<?= (int)$siteId ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Kampagne für diese Website erstellen
      </a>
    </div>
  </div>

  <!-- Info-Karten (Website & Deine Provision) -->
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="kkc-card p-3 h-100 d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Beworbene Website</div>
          <div class="fs-5 fw-bold">
            <a href="https://<?= View::e($domain) ?>" target="_blank" rel="noopener" class="text-decoration-none">
              <?= View::e($domain) ?> <i class="bi bi-box-arrow-up-right small text-muted"></i>
            </a>
          </div>
        </div>
        <i class="bi bi-globe2 fs-2 ec-text-primary opacity-50"></i>
      </div>
    </div>
    <div class="col-md-6">
      <div class="kkc-card p-3 h-100 d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Deine Provision</div>
          <div class="fs-5 fw-bold text-success"><?= (int)$commision ?> %</div>
        </div>
        <i class="bi bi-percent fs-2 text-success opacity-50"></i>
      </div>
    </div>
  </div>

  <!-- Hinweis: Keine Kampagne gewählt -->
  <div class="alert alert-warning d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-start gap-2">
      <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
      <div>
        <div class="fw-semibold">Du hast keine Kampagne ausgewählt.</div>
        <div class="small">
          Ohne Kampagne sind Statistiken über Klicks, Impressionen und Abschlüsse nur allgemein unter „Statistiken“ einsehbar und keiner einzelnen Kampagne zugeordnet.
        </div>
      </div>
    </div>
    <a href="#/webmaster-new-campaign?sid=<?= (int)$siteId ?>" class="btn btn-sm btn-warning fw-semibold align-self-center">
      <i class="bi bi-megaphone me-1"></i>Jetzt Kampagne anlegen
    </a>
  </div>

  <!-- 1. URL / Textlink & 2. XML-API -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="kkc-card p-3 p-lg-4 h-100">
        <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-link-45deg ec-text-primary"></i>
          <span>Direkter Werbelink & HTML-Textlink</span>
        </h2>

        <div class="mb-3">
          <label class="form-label small fw-semibold text-muted mb-1">Direkte Tracking-URL</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control font-monospace" value="<?= View::e($trackingUrl) ?>" readonly>
            <button type="button" class="btn btn-outline-primary btn-wm-copy" data-copy-text="<?= View::e($trackingUrl) ?>">
              <i class="bi bi-clipboard me-1"></i>Kopieren
            </button>
          </div>
        </div>

        <div>
          <label class="form-label small fw-semibold text-muted mb-1">HTML-Textlink für deine Website</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control font-monospace" value="<?= View::e($textLinkCode) ?>" readonly>
            <button type="button" class="btn btn-outline-primary btn-wm-copy" data-copy-text="<?= View::e($textLinkCode) ?>">
              <i class="bi bi-clipboard me-1"></i>Kopieren
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="kkc-card p-3 p-lg-4 h-100">
        <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-filetype-xml ec-text-primary"></i>
          <span>XML-API Schnittstelle</span>
        </h2>
        <p class="small text-muted mb-3">
          Über diese offene XML-Schnittstelle kannst du aktuelle Darsteller-Informationen von <strong><?= View::e($domain) ?></strong> automatisiert abrufen.
        </p>
        <label class="form-label small fw-semibold text-muted mb-1">XML-API Endpoint</label>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control font-monospace" value="<?= View::e($xmlApiUrl) ?>" readonly>
          <button type="button" class="btn btn-outline-primary btn-wm-copy" data-copy-text="<?= View::e($xmlApiUrl) ?>">
            <i class="bi bi-clipboard me-1"></i>Kopieren
          </button>
          <a href="<?= View::e($xmlApiUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary" title="In neuem Tab öffnen">
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Werbebanner -->
  <div class="kkc-card p-3 p-lg-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-images ec-text-primary"></i>
        <span>Werbebanner (<?= count($banners) ?>)</span>
      </h2>
      <span class="small text-muted">Klicke auf ein Banner, um es in Originalgröße anzuzeigen.</span>
    </div>

    <?php if (empty($banners)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-images fs-1 d-block mb-2 opacity-50"></i>
        Für diese Website stehen aktuell noch keine Werbebanner zur Verfügung.
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($banners as $b): ?>
          <?php
            $w = (int)$b['width'];
            $h = (int)$b['height'];
            $previewStyle = 'max-width: 100%; height: auto;';
            if ($w > 550) {
                $previewStyle = 'max-width: 550px; height: auto;';
            } elseif ($h > 250) {
                $previewStyle = 'width: auto; max-height: 250px;';
            }
          ?>
          <div class="border rounded p-3 bg-body-tertiary bg-opacity-50">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-primary-subtle ec-text-primary border border-primary-subtle font-monospace">
                  <?= View::e($b['size_label']) ?> Pixel
                </span>
                <span class="badge bg-secondary-subtle text-secondary border">
                  <?= View::e($b['type']) ?>
                </span>
                <span class="small text-muted font-monospace">Banner-ID: <?= View::e($b['file_id']) ?></span>
              </div>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary btn-wm-toggle-code"
                        data-banner-id="<?= View::e($b['file_id']) ?>">
                  <i class="bi bi-code-slash me-1"></i>Bannercode anzeigen
                </button>
                <button type="button"
                        class="btn btn-sm btn-primary btn-wm-copy"
                        data-copy-text="<?= View::e($b['embed_code']) ?>">
                  <i class="bi bi-clipboard me-1"></i>Bannercode kopieren
                </button>
              </div>
            </div>

            <div class="text-center p-2 bg-body rounded border mb-2">
              <a href="<?= View::e($b['image_url']) ?>"
                 target="_blank"
                 rel="noopener"
                 title="In Originalgröße (<?= View::e($b['size_label']) ?> Pixel) anzeigen">
                <img src="<?= View::e($b['image_url']) ?>"
                     alt="Banner <?= View::e($b['size_label']) ?>"
                     style="<?= $previewStyle ?>"
                     loading="lazy">
              </a>
            </div>

            <div class="wm-banner-code-box d-none mt-2" id="wm-code-<?= View::e($b['file_id']) ?>">
              <label class="form-label small fw-semibold text-muted mb-1">HTML-Bannercode zum Einbinden:</label>
              <textarea class="form-control font-monospace small" rows="3" readonly><?= View::e($b['embed_code']) ?></textarea>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
