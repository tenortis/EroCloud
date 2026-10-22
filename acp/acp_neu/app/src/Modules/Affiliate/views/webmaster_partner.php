<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<int, array<string, mixed>> $webmasters
 * @var array<string, int|float>         $totals
 * @var string                           $selectedPeriod
 * @var array<int, array<string, mixed>> $periodOptions
 * @var string                           $search
 * @var string                           $sort
 * @var string                           $crawlDefaultDate
 * @var int                              $pendingBannersCount
 * @var int                              $totalBannersCount
 */
?>
<div class="container-fluid px-0">

    <!-- 1. SEITEN-HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-megaphone-fill ec-text-primary"></i>
                <span>Partnerprogramm · Aktive Webmaster</span>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 ms-1">
                    <?= number_format((int)($totals['webmasters'] ?? 0), 0, ',', '.') ?>
                </span>
            </h4>
            <div class="text-muted small">
                Übersicht aller aktiven EroAds-Webmaster, Kampagnen, Klicks, Impressionen und Conversions für den Zeitraum <strong><?= View::e($selectedPeriod) ?></strong>.
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="#/banners" class="btn btn-sm btn-outline-secondary fw-semibold">
                <i class="bi bi-images me-1"></i>Alle Banner
                <span class="badge bg-secondary ms-1"><?= number_format((int)$totalBannersCount, 0, ',', '.') ?></span>
            </a>
            <a href="#/banners-new" class="btn btn-sm <?= $pendingBannersCount > 0 ? 'btn-warning fw-semibold' : 'btn-outline-secondary' ?>">
                <i class="bi bi-shield-check me-1"></i>Neue Banner prüfen
                <span class="badge <?= $pendingBannersCount > 0 ? 'bg-dark' : 'bg-secondary' ?> ms-1"><?= number_format((int)$pendingBannersCount, 0, ',', '.') ?></span>
            </a>
        </div>
    </div>

    <!-- 2. KPI-KACHELN -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Aktive Webmaster</div>
                        <div class="fs-4 fw-bold"><?= number_format((int)($totals['webmasters'] ?? 0), 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            <?= number_format((int)($totals['campaigns'] ?? 0), 0, ',', '.') ?> Kampagnen gesamt
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Conversions (<?= View::e($selectedPeriod) ?>)</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format((int)($totals['conversions'] ?? 0), 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            davon <?= number_format((int)($totals['regs'] ?? 0), 0, ',', '.') ?> Registrierungen
                        </div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-graph-up-arrow fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Impressionen (<?= View::e($selectedPeriod) ?>)</div>
                        <div class="fs-4 fw-bold"><?= number_format((int)($totals['impressions'] ?? 0), 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            <?= number_format((int)($totals['clicks'] ?? 0), 0, ',', '.') ?> Klicks gesamt
                        </div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-eye-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Netto-Umsatz (<?= View::e($selectedPeriod) ?>)</div>
                        <div class="fs-4 fw-bold text-primary"><?= number_format((float)($totals['net_revenue'] ?? 0.0), 2, ',', '.') ?> €</div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            Erst- &amp; Folgezahlungen abzgl. Stornos
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-currency-euro fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. FILTER & CONVERSION-CRAWLER TOOLBAR -->
    <div class="card-kkc mb-4">
        <div class="card-kkc-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-xl-8">
                    <form onsubmit="App.Affiliate.filterWebmasters(event)" class="row g-2 align-items-end">
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1" for="aff-period-select">Zeitraum (Monat / Jahr)</label>
                            <select id="aff-period-select" class="form-select form-select-sm" onchange="App.Affiliate.filterWebmasters(event)">
                                <?php foreach ($periodOptions as $opt): ?>
                                    <option value="<?= View::e((string)$opt['value']) ?>"
                                            <?= ((string)$opt['value'] === $selectedPeriod) ? 'selected' : '' ?>
                                            style="<?= !empty($opt['is_year']) ? 'font-weight:700;background:#eef4fb;' : '' ?>">
                                        <?= View::e((string)$opt['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1" for="aff-sort-select">Sortierung</label>
                            <select id="aff-sort-select" class="form-select form-select-sm" onchange="App.Affiliate.filterWebmasters(event)">
                                <option value="conversions_desc" <?= $sort === 'conversions_desc' ? 'selected' : '' ?>>Conversions (absteigend)</option>
                                <option value="impressions_desc" <?= $sort === 'impressions_desc' ? 'selected' : '' ?>>Impressionen (absteigend)</option>
                                <option value="clicks_desc" <?= $sort === 'clicks_desc' ? 'selected' : '' ?>>Klicks gesamt (absteigend)</option>
                                <option value="campaigns_desc" <?= $sort === 'campaigns_desc' ? 'selected' : '' ?>>Kampagnen (absteigend)</option>
                                <option value="revenue_desc" <?= $sort === 'revenue_desc' ? 'selected' : '' ?>>Umsatz (absteigend)</option>
                                <option value="partner_asc" <?= $sort === 'partner_asc' ? 'selected' : '' ?>>Partner-ID (A–Z)</option>
                            </select>
                        </div>

                        <div class="col-8 col-md-4">
                            <label class="form-label small fw-semibold mb-1" for="aff-search-input">Suche (Partner-ID / Username)</label>
                            <input type="text" id="aff-search-input" class="form-control form-control-sm"
                                   value="<?= View::e($search) ?>" placeholder="P4C-Partner-ID oder Benutzername...">
                        </div>

                        <div class="col-4 col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="bi bi-funnel me-1"></i>Filtern
                            </button>
                            <?php if ($search !== '' || $sort !== 'conversions_desc' || $selectedPeriod !== date('Y-m')): ?>
                                <a href="#/webmaster-partner" class="btn btn-sm btn-outline-secondary" title="Filter zurücksetzen">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Conversion-Crawler vom Tag -->
                <div class="col-12 col-xl-4 border-start-xl ps-xl-3">
                    <form onsubmit="App.Affiliate.crawlConversions(event)" class="d-flex flex-wrap align-items-end gap-2">
                        <div class="flex-grow-1">
                            <label class="form-label small fw-semibold mb-1" for="aff-crawl-date">
                                <i class="bi bi-cloud-arrow-down me-1 text-primary"></i>Conversions erneut crawlen vom Tag
                            </label>
                            <input type="date" id="aff-crawl-date" class="form-control form-control-sm"
                                   value="<?= View::e($crawlDefaultDate) ?>" max="<?= View::e(date('Y-m-d')) ?>" required>
                        </div>
                        <button type="submit" id="aff-crawl-btn" class="btn btn-sm btn-outline-primary fw-semibold">
                            <i class="bi bi-arrow-repeat me-1"></i>Abrufen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. WEBMASTER-TABELLE -->
    <div class="card-kkc">
        <div class="card-kkc-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                <i class="bi bi-table me-2"></i>Aktive Webmaster
                <span class="badge bg-light text-dark border ms-1"><?= number_format(count($webmasters), 0, ',', '.') ?></span>
            </span>
            <span class="small fw-normal opacity-75">
                Ausgewählter Zeitraum: <strong><?= View::e($selectedPeriod) ?></strong>
            </span>
        </div>
        <div class="card-kkc-body p-0">
            <?php if (empty($webmasters)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                    Für die aktuelle Auswahl wurden keine aktiven Webmaster-Kampagnen gefunden.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">P4C-Partner-ID</th>
                                <th>P4C-Username</th>
                                <th class="text-end">Kampagnen<br><span class="fw-normal text-muted" style="font-size:0.72rem;">gesamt</span></th>
                                <th class="text-end">Conversions<br><span class="fw-normal text-muted" style="font-size:0.72rem;"><?= View::e($selectedPeriod) ?></span></th>
                                <th class="text-end">Registrierungen<br><span class="fw-normal text-muted" style="font-size:0.72rem;"><?= View::e($selectedPeriod) ?></span></th>
                                <th class="text-end">Netto-Umsatz<br><span class="fw-normal text-muted" style="font-size:0.72rem;"><?= View::e($selectedPeriod) ?></span></th>
                                <th class="text-end">Klicks<br><span class="fw-normal text-muted" style="font-size:0.72rem;">gesamt</span></th>
                                <th class="text-end">Impressionen<br><span class="fw-normal text-muted" style="font-size:0.72rem;"><?= View::e($selectedPeriod) ?></span></th>
                                <th class="text-end pe-3">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webmasters as $wm): ?>
                                <?php
                                $pid         = (string)$wm['p4c_partner_id'];
                                $mId         = (int)$wm['merchant_db_id'];
                                $hasMerchant = !empty($wm['has_merchant']);
                                $conversions = (int)$wm['conversions'];
                                $regs        = (int)$wm['regs'];
                                $netRev      = (float)$wm['net_revenue'];
                                $impressions = (int)$wm['impressions'];
                                ?>
                                <tr>
                                    <td class="ps-3 font-monospace fw-semibold">
                                        <?php if ($hasMerchant): ?>
                                            <a href="#/merchant?id=<?= $mId ?>" class="text-decoration-none ec-text-primary">
                                                <?= View::e($pid) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted"><?= View::e($pid) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasMerchant): ?>
                                            <a href="#/merchant?id=<?= $mId ?>" class="fw-semibold text-decoration-none">
                                                <i class="bi bi-person-badge me-1 text-primary"></i><?= View::e((string)$wm['username']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">unbekannt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-semibold"><?= number_format((int)$wm['all_campaigns'], 0, ',', '.') ?></span>
                                        <?php if ((int)$wm['distinct_sites'] > 0): ?>
                                            <div class="text-muted" style="font-size:0.72rem;">
                                                <?= (int)$wm['distinct_sites'] ?> <?= (int)$wm['distinct_sites'] === 1 ? 'Webseite' : 'Webseiten' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($conversions > 0): ?>
                                            <span class="badge bg-success"><?= number_format($conversions, 0, ',', '.') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end <?= $regs > 0 ? 'fw-semibold' : 'text-muted' ?>">
                                        <?= number_format($regs, 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end <?= $netRev > 0 ? 'fw-bold text-success' : ($netRev < 0 ? 'fw-bold text-danger' : 'text-muted') ?>">
                                        <?= number_format($netRev, 2, ',', '.') ?> €
                                    </td>
                                    <td class="text-end fw-semibold">
                                        <?= number_format((int)$wm['all_clicks'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end <?= $impressions > 0 ? 'fw-semibold' : 'text-muted' ?>">
                                        <?= number_format($impressions, 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ($hasMerchant): ?>
                                            <a href="#/merchant?id=<?= $mId ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Händler
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
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
