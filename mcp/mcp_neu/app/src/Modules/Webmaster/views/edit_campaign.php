<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var array<string, mixed> $campaign
 * @var string $activeTab
 * @var string $selectedPeriod
 * @var list<array{value: string, label: string, is_year: bool}> $availablePeriods
 * @var string $trackingUrl
 * @var string $textLinkCode
 * @var string $xmlApiUrl
 * @var list<array<string, mixed>> $banners
 * @var list<array<string, mixed>> $conversions
 * @var int $conversionsCount
 * @var float $conversionsSum
 * @var list<array{url: string, clicks: int}> $clickRows
 * @var int $totalClicks
 * @var list<array{url: string, impressions: int}> $impressionRows
 * @var int $totalImpressions
 */

$cid          = (string)$campaign['campaign_id'];
$reserveType  = (string)$campaign['reserve'];
$reserveValue = (string)$campaign['reserve_value'];
$valImage     = $reserveType === 'image' ? $reserveValue : '';
$valColor     = ($reserveType === 'color' && $reserveValue !== '') ? $reserveValue : '#0284c7';
?>
<div class="container-fluid py-3" id="mcp-webmaster-edit-campaign-page"
     data-cid="<?= View::e($cid) ?>"
     data-period="<?= View::e($selectedPeriod) ?>">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <a href="#/webmaster-ads?tab=campaigns" class="text-decoration-none text-muted">Kunden werben</a>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <a href="#/webmaster-ads?tab=campaigns" class="text-decoration-none text-muted">Kampagnen</a>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold"><?= View::e($campaign['name']) ?></span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-megaphone ec-text-primary"></i>
        <span>Kampagne: <?= View::e($campaign['name']) ?></span>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="#/webmaster-ads?tab=campaigns" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu deinen Kampagnen
      </a>
    </div>
  </div>

  <!-- Eckdaten-Leiste (KPIs) -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md">
      <div class="kkc-card p-3 h-100">
        <div class="text-muted small">Website</div>
        <div class="fw-bold text-truncate">
          <?php if ($campaign['site_active']): ?>
            <a href="https://<?= View::e($campaign['domain']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
              <?= View::e($campaign['domain']) ?>
            </a>
          <?php else: ?>
            <span class="text-danger text-decoration-line-through"><?= View::e($campaign['domain']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-6 col-md">
      <div class="kkc-card p-3 h-100">
        <div class="text-muted small">Kampagnen-ID (CID)</div>
        <div><code class="fw-bold"><?= View::e($cid) ?></code></div>
      </div>
    </div>
    <div class="col-6 col-md">
      <div class="kkc-card p-3 h-100">
        <div class="text-muted small">Deine Provision</div>
        <div class="fw-bold text-success"><?= (int)$campaign['commision'] ?> %</div>
      </div>
    </div>
    <div class="col-6 col-md">
      <div class="kkc-card p-3 h-100">
        <div class="text-muted small">Impressionen (<?= date('Y-m') ?>)</div>
        <div class="fw-bold"><?= number_format((int)$campaign['current_month_impressions'], 0, ',', '.') ?></div>
      </div>
    </div>
    <div class="col-12 col-md">
      <div class="kkc-card p-3 h-100">
        <div class="text-muted small">Klicks (gesamt)</div>
        <div class="fw-bold"><?= number_format((int)$campaign['count_clicks_alltime'], 0, ',', '.') ?></div>
      </div>
    </div>
  </div>

  <!-- Haupt-Card mit 5 Reitern -->
  <div class="kkc-card overflow-hidden">
    <div class="card-header bg-transparent border-bottom px-3 pt-3 pb-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <ul class="nav nav-tabs border-bottom-0 gap-1" id="wmCampaignDetailTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'edit' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-camp-edit"
                  type="button"
                  role="tab">
            <i class="bi bi-sliders me-1"></i>Bearbeiten
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'ads' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-camp-ads"
                  type="button"
                  role="tab">
            <i class="bi bi-code-slash me-1"></i>Werbemittel
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border ms-1"><?= count($banners) ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'conversions' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-camp-conversions"
                  type="button"
                  role="tab">
            <i class="bi bi-currency-euro me-1"></i>Conversions
            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle ms-1">
              <?= $conversionsCount ?> = <?= number_format($conversionsSum, 2, ',', '.') ?> EUR
            </span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'clicks' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-camp-clicks"
                  type="button"
                  role="tab">
            <i class="bi bi-cursor me-1"></i>Klicks
            <span class="badge rounded-pill bg-primary-subtle ec-text-primary border border-primary-subtle ms-1">
              <?= number_format($totalClicks, 0, ',', '.') ?>
            </span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'impressions' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-camp-impressions"
                  type="button"
                  role="tab">
            <i class="bi bi-eye me-1"></i>Impressionen
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border ms-1">
              <?= number_format($totalImpressions, 0, ',', '.') ?>
            </span>
          </button>
        </li>
      </ul>

      <!-- Zeitraum-Auswahl für die Statistik-Reiter -->
      <div class="d-flex align-items-center gap-2 pb-2">
        <label for="wm-campaign-period-select" class="small text-muted fw-semibold text-nowrap mb-0">
          <i class="bi bi-calendar3 me-1"></i>Zeitraum:
        </label>
        <select id="wm-campaign-period-select" class="form-select form-select-sm" style="min-width: 210px;">
          <?php foreach ($availablePeriods as $p): ?>
            <option value="<?= View::e($p['value']) ?>"
                    <?= $p['value'] === $selectedPeriod ? 'selected' : '' ?>
                    class="<?= $p['is_year'] ? 'fw-bold' : '' ?>">
              <?= View::e($p['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="tab-content p-3 p-lg-4">

      <!-- ================================================================== -->
      <!-- TAB 1: Bearbeiten                                                  -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'edit' ? 'show active' : '' ?>" id="pane-camp-edit" role="tabpanel">
        <form id="form-wm-update-campaign" style="max-width: 780px;">
          <input type="hidden" name="cid" value="<?= View::e($cid) ?>">

          <div class="mb-3">
            <label for="wm-edit-cname" class="form-label fw-semibold">Name der Kampagne</label>
            <input type="text"
                   class="form-control"
                   id="wm-edit-cname"
                   name="cname"
                   maxlength="120"
                   value="<?= View::e($campaign['name']) ?>"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold d-flex align-items-center gap-2">
              <span>Ersatzanzeigen</span>
              <span class="text-muted small fw-normal">(Falls die beworbene Website nicht mehr am Partnerprogramm teilnimmt)</span>
            </label>
            <div class="border rounded p-3 bg-body-tertiary bg-opacity-50">
              <div class="form-check mb-2">
                <input class="form-check-input wm-reserve-radio"
                       type="radio"
                       name="reserve"
                       id="reserve-blank"
                       value="blank"
                       <?= $reserveType === 'blank' ? 'checked' : '' ?>>
                <label class="form-check-label" for="reserve-blank">
                  <strong>Anzeigenplatzhalter (Standard):</strong> Leere Fläche anstelle des Banners anzeigen
                </label>
              </div>

              <div class="form-check mb-2">
                <input class="form-check-input wm-reserve-radio"
                       type="radio"
                       name="reserve"
                       id="reserve-image"
                       value="image"
                       <?= $reserveType === 'image' ? 'checked' : '' ?>>
                <label class="form-check-label" for="reserve-image">
                  <strong>Eigenes Ersatzbild (Bild-URL):</strong> Alternatives Bild anzeigen
                </label>
                <div class="mt-2 ps-1 <?= $reserveType === 'image' ? '' : 'd-none' ?>" id="wm-reserve-image-wrap">
                  <input type="url"
                         class="form-control form-control-sm"
                         name="reserve_value_image"
                         placeholder="https://deine-domain.de/ersatzbanner.jpg"
                         value="<?= View::e($valImage) ?>">
                </div>
              </div>

              <div class="form-check">
                <input class="form-check-input wm-reserve-radio"
                       type="radio"
                       name="reserve"
                       id="reserve-color"
                       value="color"
                       <?= $reserveType === 'color' ? 'checked' : '' ?>>
                <label class="form-check-label" for="reserve-color">
                  <strong>Hintergrundfarbe (Hex-Farbcode):</strong> Fläche mit einer festen Farbe füllen
                </label>
                <div class="mt-2 ps-1 d-flex align-items-center gap-2 <?= $reserveType === 'color' ? '' : 'd-none' ?>" id="wm-reserve-color-wrap">
                  <input type="color"
                         class="form-control form-control-color form-control-sm"
                         id="wm-reserve-color-picker"
                         value="<?= View::e(preg_match('/^#[0-9a-fA-F]{6}$/', $valColor) ? $valColor : '#0284c7') ?>">
                  <input type="text"
                         class="form-control form-control-sm font-monospace"
                         id="wm-reserve-color-input"
                         name="reserve_value_color"
                         style="max-width: 140px;"
                         placeholder="#0284c7"
                         value="<?= View::e($valColor) ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label for="wm-edit-cdescription" class="form-label fw-semibold">Beschreibung</label>
            <textarea class="form-control"
                      id="wm-edit-cdescription"
                      name="cdescription"
                      rows="4"><?= View::e($campaign['description']) ?></textarea>
          </div>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
            <button type="button"
                    class="btn btn-outline-danger btn-sm"
                    id="btn-wm-delete-campaign"
                    data-cid="<?= View::e($cid) ?>"
                    data-name="<?= View::e($campaign['name']) ?>">
              <i class="bi bi-trash me-1"></i>Kampagne löschen
            </button>

            <button type="submit" class="btn btn-primary" id="btn-wm-save-campaign">
              <i class="bi bi-check-lg me-1"></i>Kampagne speichern
            </button>
          </div>
        </form>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 2: Werbemittel                                                 -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'ads' ? 'show active' : '' ?>" id="pane-camp-ads" role="tabpanel">
        <div class="row g-3 mb-4">
          <div class="col-lg-7">
            <div class="border rounded p-3 h-100">
              <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-link-45deg ec-text-primary"></i>
                <span>Kampagnen-URL & HTML-Textlink</span>
              </h3>
              <div class="mb-3">
                <label class="form-label small fw-semibold text-muted mb-1">Direkte Kampagnen-URL</label>
                <div class="input-group input-group-sm">
                  <input type="text" class="form-control font-monospace" value="<?= View::e($trackingUrl) ?>" readonly>
                  <button type="button" class="btn btn-outline-primary btn-wm-copy" data-copy-text="<?= View::e($trackingUrl) ?>">
                    <i class="bi bi-clipboard me-1"></i>Kopieren
                  </button>
                </div>
              </div>
              <div>
                <label class="form-label small fw-semibold text-muted mb-1">HTML-Textlink</label>
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
            <div class="border rounded p-3 h-100">
              <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-filetype-xml ec-text-primary"></i>
                <span>XML-API Schnittstelle</span>
              </h3>
              <p class="small text-muted mb-2">
                Offene XML-Schnittstelle mit den aktuellen Darsteller-Informationen dieser Website:
              </p>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control font-monospace" value="<?= View::e($xmlApiUrl) ?>" readonly>
                <button type="button" class="btn btn-outline-primary btn-wm-copy" data-copy-text="<?= View::e($xmlApiUrl) ?>">
                  <i class="bi bi-clipboard me-1"></i>Kopieren
                </button>
                <a href="<?= View::e($xmlApiUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary" title="Öffnen">
                  <i class="bi bi-box-arrow-up-right"></i>
                </a>
              </div>
            </div>
          </div>
        </div>

        <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-images ec-text-primary"></i>
          <span>Werbebanner für diese Kampagne (<?= count($banners) ?>)</span>
        </h3>

        <?php if (empty($banners)): ?>
          <div class="text-center py-4 text-muted">
            Es stehen aktuell noch keine Banner für diese Website zur Verfügung.
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
                    <span class="badge bg-secondary-subtle text-secondary border"><?= View::e($b['type']) ?></span>
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
                     title="In Originalgröße anzeigen">
                    <img src="<?= View::e($b['image_url']) ?>"
                         alt="Banner <?= View::e($b['size_label']) ?>"
                         style="<?= $previewStyle ?>"
                         loading="lazy">
                  </a>
                </div>

                <div class="wm-banner-code-box d-none mt-2" id="wm-code-<?= View::e($b['file_id']) ?>">
                  <label class="form-label small fw-semibold text-muted mb-1">HTML-Bannercode (inkl. Kampagnen-ID <?= View::e($cid) ?>):</label>
                  <textarea class="form-control font-monospace small" rows="3" readonly><?= View::e($b['embed_code']) ?></textarea>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 3: Conversions                                                 -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'conversions' ? 'show active' : '' ?>" id="pane-camp-conversions" role="tabpanel">
        <?php if (empty($conversions)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-currency-euro fs-1 d-block mb-2 opacity-50"></i>
            Im ausgewählten Zeitraum (<strong><?= View::e($selectedPeriod) ?></strong>) liegen keine Conversions für diese Kampagne vor.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 180px;" class="text-center">Zeitpunkt</th>
                  <th>User</th>
                  <th style="width: 210px;" class="text-center">Status</th>
                  <th style="width: 160px;" class="text-end">Betrag</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($conversions as $conv): ?>
                  <tr>
                    <td class="text-center small text-muted"><?= View::e($conv['date_time']) ?></td>
                    <td class="fw-semibold"><?= View::e($conv['username']) ?></td>
                    <td class="text-center">
                      <span class="badge border <?= View::e($conv['status_badge_class']) ?>">
                        <?= View::e($conv['status_label']) ?>
                      </span>
                    </td>
                    <td class="text-end <?= View::e($conv['amount_class']) ?>">
                      <?= View::e($conv['amount_formatted']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 4: Klicks                                                      -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'clicks' ? 'show active' : '' ?>" id="pane-camp-clicks" role="tabpanel">
        <?php if (empty($clickRows)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-cursor fs-1 d-block mb-2 opacity-50"></i>
            Im ausgewählten Zeitraum (<strong><?= View::e($selectedPeriod) ?></strong>) wurden keine Klicks für diese Kampagne aufgezeichnet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 140px;" class="text-center">Klicks</th>
                  <th>Herkunft (Domain)</th>
                  <th style="width: 180px;" class="text-end">Details</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clickRows as $cr): ?>
                  <tr>
                    <td class="text-center fw-bold"><?= number_format((int)$cr['clicks'], 0, ',', '.') ?></td>
                    <td>
                      <?php if ($cr['url'] === 'none' || $cr['url'] === ''): ?>
                        <span class="text-muted fst-italic">Direktaufruf / Kein Referer übermittelt (none)</span>
                      <?php else: ?>
                        <a href="javascript:void(0)"
                           class="fw-semibold text-decoration-none btn-wm-origin-details"
                           data-type="clicks"
                           data-origin="<?= View::e($cr['url']) ?>"
                           data-month="<?= View::e($selectedPeriod) ?>"
                           data-cid="<?= View::e($cid) ?>">
                          <?= View::e($cr['url']) ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <?php if ($cr['url'] !== 'none' && $cr['url'] !== ''): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary btn-wm-origin-details"
                                data-type="clicks"
                                data-origin="<?= View::e($cr['url']) ?>"
                                data-month="<?= View::e($selectedPeriod) ?>"
                                data-cid="<?= View::e($cid) ?>">
                          <i class="bi bi-list-ul me-1"></i>Referer anzeigen
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 5: Impressionen                                                -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'impressions' ? 'show active' : '' ?>" id="pane-camp-impressions" role="tabpanel">
        <?php if (empty($impressionRows)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-eye fs-1 d-block mb-2 opacity-50"></i>
            Im ausgewählten Zeitraum (<strong><?= View::e($selectedPeriod) ?></strong>) wurden keine Impressionen für diese Kampagne aufgezeichnet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 140px;" class="text-center">Impressionen</th>
                  <th>Herkunft (Domain)</th>
                  <th style="width: 180px;" class="text-end">Details</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($impressionRows as $ir): ?>
                  <tr>
                    <td class="text-center fw-bold"><?= number_format((int)$ir['impressions'], 0, ',', '.') ?></td>
                    <td>
                      <?php if ($ir['url'] === 'none' || $ir['url'] === ''): ?>
                        <span class="text-muted fst-italic">Kein Referer übermittelt (none)</span>
                      <?php else: ?>
                        <a href="javascript:void(0)"
                           class="fw-semibold text-decoration-none btn-wm-origin-details"
                           data-type="impressions"
                           data-origin="<?= View::e($ir['url']) ?>"
                           data-month="<?= View::e($selectedPeriod) ?>"
                           data-cid="<?= View::e($cid) ?>">
                          <?= View::e($ir['url']) ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <?php if ($ir['url'] !== 'none' && $ir['url'] !== ''): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary btn-wm-origin-details"
                                data-type="impressions"
                                data-origin="<?= View::e($ir['url']) ?>"
                                data-month="<?= View::e($selectedPeriod) ?>"
                                data-cid="<?= View::e($cid) ?>">
                          <i class="bi bi-list-ul me-1"></i>Referer anzeigen
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

</div>

<!-- ======================================================================== -->
<!-- MODAL: Herkunfts-Details (Referer für Klicks & Impressionen)             -->
<!-- ======================================================================== -->
<div class="modal fade" id="modal-wm-origin-details" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="wm-origin-modal-title">Herkunfts-Details</h5>
          <div class="small text-muted mt-1" id="wm-origin-modal-subtitle"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body" id="wm-origin-modal-body">
        <div class="text-center py-4 text-muted">
          <div class="spinner-border spinner-border-sm ec-text-primary me-2"></div>Daten werden geladen...
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
