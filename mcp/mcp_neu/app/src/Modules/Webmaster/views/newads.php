<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var string $partnerId
 * @var list<array<string, mixed>> $banners
 * @var list<string> $domains
 * @var list<string> $sizes
 * @var array<string, string> $types
 */
?>
<div class="container-fluid py-3" id="mcp-webmaster-newads-page">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <span>Partnerprogramm</span>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold">Alle Banner</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-images ec-text-primary"></i>
        <span>Alle Banner</span>
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

  <!-- Hinweis-Box -->
  <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
    <div>
      Für eine bessere Übersicht werden große Banner hier verkleinert dargestellt. Mit einem Klick auf ein Banner kannst du es in Originalgröße betrachten.
    </div>
  </div>

  <!-- Filter-Leiste -->
  <div class="kkc-card p-3 mb-3">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
          <input type="search"
                 id="wm-newads-search"
                 class="form-control"
                 placeholder="Domain, Größe oder Banner-ID suchen...">
        </div>
      </div>
      <div class="col-sm-4 col-md-3">
        <select id="wm-newads-filter-domain" class="form-select form-select-sm">
          <option value="">Alle Websites (<?= count($domains) ?>)</option>
          <?php foreach ($domains as $d): ?>
            <option value="<?= View::e(strtolower($d)) ?>"><?= View::e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-md-2">
        <select id="wm-newads-filter-size" class="form-select form-select-sm">
          <option value="">Alle Größen (<?= count($sizes) ?>)</option>
          <?php foreach ($sizes as $s): ?>
            <option value="<?= View::e(strtolower($s)) ?>"><?= View::e($s) ?> px</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-md-2">
        <select id="wm-newads-filter-type" class="form-select form-select-sm">
          <option value="">Alle Formate</option>
          <?php foreach ($types as $tKey => $tLabel): ?>
            <option value="<?= View::e(strtolower($tLabel)) ?>"><?= View::e($tLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 text-end">
        <span class="badge bg-secondary-subtle text-secondary border" id="wm-newads-count-badge">
          <?= count($banners) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Banner-Tabelle -->
  <div class="kkc-card overflow-hidden">
    <?php if (empty($banners)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-images fs-1 d-block mb-2 opacity-50"></i>
        Es stehen aktuell noch keine freigeschalteten Werbebanner zur Verfügung.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="wm-newads-table">
          <thead class="table-light">
            <tr>
              <th style="width: 110px;" class="text-center">Status / Datum</th>
              <th style="width: 220px;">Website</th>
              <th style="width: 90px;" class="text-center">Typ</th>
              <th style="width: 130px;" class="text-center">Größe</th>
              <th>Banner & Code</th>
            </tr>
          </thead>
          <tbody>
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
                $searchStr = strtolower($b['domain'] . ' ' . $b['size_label'] . ' ' . $b['type'] . ' ' . $b['file_id']);
              ?>
              <tr class="wm-newads-row"
                  data-domain="<?= View::e(strtolower((string)$b['domain'])) ?>"
                  data-size="<?= View::e(strtolower((string)$b['size_label'])) ?>"
                  data-type="<?= View::e(strtolower((string)$b['type'])) ?>"
                  data-search="<?= View::e($searchStr) ?>">
                <td class="text-center">
                  <?php if (!empty($b['is_new'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-block mb-1">NEU</span><br>
                  <?php endif; ?>
                  <span class="small text-muted"><?= View::e($b['upload_date_fmt']) ?></span>
                </td>
                <td>
                  <a href="#/webmaster-ads-domain?domain=<?= urlencode((string)$b['domain']) ?>"
                     class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1">
                    <span><?= View::e($b['domain']) ?></span>
                  </a>
                  <div class="mt-1">
                    <a href="#/webmaster-new-campaign?sid=<?= (int)$b['site_id'] ?>"
                       class="small text-muted text-decoration-none">
                      <i class="bi bi-plus-circle me-1"></i>Kampagne erstellen
                    </a>
                  </div>
                </td>
                <td class="text-center">
                  <span class="badge bg-secondary-subtle text-secondary border"><?= View::e($b['type']) ?></span>
                </td>
                <td class="text-center font-monospace fw-semibold">
                  <?= View::e($b['size_label']) ?>
                </td>
                <td class="py-3">
                  <div class="text-center p-2 bg-body-tertiary rounded border mb-2">
                    <a href="<?= View::e($b['image_url']) ?>"
                       target="_blank"
                       rel="noopener"
                       title="In Originalgröße (<?= View::e($b['size_label']) ?> Pixel) anzeigen">
                      <img src="<?= View::e($b['image_url']) ?>"
                           alt="Banner <?= View::e($b['domain']) ?> (<?= View::e($b['size_label']) ?>)"
                           style="<?= $previewStyle ?>"
                           loading="lazy">
                    </a>
                  </div>

                  <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
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

                  <div class="wm-banner-code-box d-none mt-2" id="wm-code-<?= View::e($b['file_id']) ?>">
                    <textarea class="form-control font-monospace small" rows="3" readonly><?= View::e($b['embed_code']) ?></textarea>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>
