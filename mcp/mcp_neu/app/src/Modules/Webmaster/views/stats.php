<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var string $activeTab
 * @var string $selectedPeriod
 * @var list<array{value: string, label: string, is_year: bool}> $availablePeriods
 * @var list<array{url: string, clicks: int}> $clickRows
 * @var int $totalClicks
 * @var list<array{url: string, impressions: int}> $impressionRows
 * @var int $totalImpressions
 * @var list<array<string, mixed>> $conversions
 * @var int $conversionsCount
 * @var float $conversionsSum
 */
?>
<div class="container-fluid py-3" id="mcp-webmaster-stats-page"
     data-period="<?= View::e($selectedPeriod) ?>">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <span>Partnerprogramm</span>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold">Statistiken</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-line ec-text-primary"></i>
        <span>Statistiken</span>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <label for="wm-stats-period-select" class="small text-muted fw-semibold mb-0">
        <i class="bi bi-calendar3 me-1"></i>Zeitraum:
      </label>
      <select id="wm-stats-period-select" class="form-select form-select-sm" style="min-width: 220px;">
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

  <!-- KPI-Übersichtskarten -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="kkc-card p-3 h-100 d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Klicks (<?= View::e($selectedPeriod) ?>)</div>
          <div class="fs-4 fw-bold"><?= number_format($totalClicks, 0, ',', '.') ?></div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center"
             style="width: 44px; height: 44px; background: rgba(2, 132, 199, 0.12); color: var(--kkc-accent);">
          <i class="bi bi-cursor fs-5"></i>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="kkc-card p-3 h-100 d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Impressionen (<?= View::e($selectedPeriod) ?>)</div>
          <div class="fs-4 fw-bold"><?= number_format($totalImpressions, 0, ',', '.') ?></div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center"
             style="width: 44px; height: 44px; background: rgba(100, 116, 139, 0.12); color: var(--bs-secondary-color);">
          <i class="bi bi-eye fs-5"></i>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="kkc-card p-3 h-100 d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Conversions (<?= View::e($selectedPeriod) ?>)</div>
          <div class="fs-4 fw-bold text-success">
            <?= number_format($conversionsCount, 0, ',', '.') ?>
            <span class="fs-6 fw-semibold text-muted ms-1">(<?= number_format($conversionsSum, 2, ',', '.') ?> EUR)</span>
          </div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success-subtle text-success"
             style="width: 44px; height: 44px;">
          <i class="bi bi-currency-euro fs-5"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Haupt-Card mit 3 Reitern -->
  <div class="kkc-card overflow-hidden">
    <div class="card-header bg-transparent border-bottom px-3 pt-3 pb-0">
      <ul class="nav nav-tabs border-bottom-0 gap-1" id="wmStatsTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'clicks' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-stats-clicks"
                  data-tab-key="clicks"
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
                  data-bs-target="#pane-stats-impressions"
                  data-tab-key="impressions"
                  type="button"
                  role="tab">
            <i class="bi bi-eye me-1"></i>Impressionen
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border ms-1">
              <?= number_format($totalImpressions, 0, ',', '.') ?>
            </span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'conversions' ? 'active' : '' ?> fw-semibold"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-stats-conversions"
                  data-tab-key="conversions"
                  type="button"
                  role="tab">
            <i class="bi bi-currency-euro me-1"></i>Conversions
            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle ms-1">
              <?= $conversionsCount ?> = <?= number_format($conversionsSum, 2, ',', '.') ?> EUR
            </span>
          </button>
        </li>
      </ul>
    </div>

    <div class="tab-content p-3 p-lg-4">

      <!-- ================================================================== -->
      <!-- TAB 1: Klicks                                                      -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'clicks' ? 'show active' : '' ?>" id="pane-stats-clicks" role="tabpanel">
        <?php if (empty($clickRows)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-cursor fs-1 d-block mb-2 opacity-50"></i>
            Im Zeitraum <strong><?= View::e($selectedPeriod) ?></strong> wurden keine Klicks aufgezeichnet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 150px;" class="text-center">Klicks</th>
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
                           data-cid="">
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
                                data-cid="">
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
      <!-- TAB 2: Impressionen                                                -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'impressions' ? 'show active' : '' ?>" id="pane-stats-impressions" role="tabpanel">
        <?php if (empty($impressionRows)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-eye fs-1 d-block mb-2 opacity-50"></i>
            Im Zeitraum <strong><?= View::e($selectedPeriod) ?></strong> wurden keine Impressionen aufgezeichnet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 150px;" class="text-center">Impressionen</th>
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
                           data-cid="">
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
                                data-cid="">
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
      <!-- TAB 3: Conversions                                                 -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'conversions' ? 'show active' : '' ?>" id="pane-stats-conversions" role="tabpanel">
        <?php if (empty($conversions)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-currency-euro fs-1 d-block mb-2 opacity-50"></i>
            Im Zeitraum <strong><?= View::e($selectedPeriod) ?></strong> liegen keine Conversions vor.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Website</th>
                  <th style="width: 175px;" class="text-center">Zeitpunkt</th>
                  <th style="width: 150px;" class="text-center">Kampagne</th>
                  <th>User</th>
                  <th style="width: 200px;" class="text-center">Status</th>
                  <th style="width: 150px;" class="text-end">Betrag</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($conversions as $conv): ?>
                  <tr>
                    <td class="fw-semibold"><?= View::e($conv['domain']) ?></td>
                    <td class="text-center small text-muted"><?= View::e($conv['date_time']) ?></td>
                    <td class="text-center">
                      <?php if (!empty($conv['campaign_id'])): ?>
                        <a href="#/webmaster-edit-campaign?cid=<?= urlencode((string)$conv['campaign_id']) ?>&tab=3"
                           class="font-monospace text-decoration-none">
                          <?= View::e($conv['campaign_id']) ?>
                        </a>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td><?= View::e($conv['username']) ?></td>
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
