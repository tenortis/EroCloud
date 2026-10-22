<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/** @var array<string, int> $kpi */
/** @var array<int, array<string, mixed>> $sites */
/** @var int $totalFiltered */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
/** @var array<string, string> $filters */

$stFilter  = $filters['status'] ?? 'all';
$adsFilter = $filters['eroads'] ?? 'all';
$sysFilter = $filters['shop_system'] ?? 'all';
$banFilter = $filters['banners'] ?? 'all';
$qVal      = $filters['q'] ?? '';
$sortVal   = $filters['sort'] ?? 'id_desc';
?>
<div class="container-fluid px-0">

    <!-- 1. SEITEN-HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-globe2 ec-text-primary"></i>
                <span>Webseiten &amp; Partner-Shops</span>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 ms-1">
                    <?= number_format((int)$kpi['total_sites'], 0, ',', '.') ?>
                </span>
            </h4>
            <div class="text-muted small">
                Übersicht aller angeschlossenen Pay4Coins-Webseiten, EroCMS-/Eigen-Shops, EroAds-Teilnahmen, Werbebanner und Shop-Verkäufe.
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btn-sites-sync-p4c" onclick="App.Websites.syncFromP4c(this)">
                <i class="bi bi-arrow-repeat me-1"></i>Mit Pay4Coins synchronisieren
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="App.Websites.resetFilters()">
                <i class="bi bi-x-circle me-1"></i>Filter zurücksetzen
            </button>
        </div>
    </div>

    <!-- 2. 8 KPI-KACHELN (KLICKBAR ZUM SCHNELLFILTERN) -->
    <div class="row g-3 mb-4">
        <!-- 1. Alle Webseiten -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= ($stFilter === 'all' && $adsFilter === 'all' && $sysFilter === 'all' && $banFilter === 'all') ? 'border-primary shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('all', 'all', 'all', 'all')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Webseiten Gesamt</div>
                        <div class="fs-4 fw-bold"><?= number_format((int)$kpi['total_sites'], 0, ',', '.') ?></div>
                        <div class="small text-muted">Alle registrierten Domains</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-globe2 fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Aktive Webseiten -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $stFilter === 'active' ? 'border-success shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('active', 'all', 'all', 'all')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Aktive Webseiten</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format((int)$kpi['active_sites'], 0, ',', '.') ?></div>
                        <div class="small text-muted">Status: Freigegeben (Aktiv)</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Inaktive Webseiten -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $stFilter === 'inactive' ? 'border-secondary shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('inactive', 'all', 'all', 'all')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Inaktiv / Deaktiviert</div>
                        <div class="fs-4 fw-bold text-secondary"><?= number_format((int)$kpi['inactive_sites'], 0, ',', '.') ?></div>
                        <div class="small text-muted">Status: Inaktiv (0)</div>
                    </div>
                    <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-slash-circle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. EroAds aktiv -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $adsFilter === 'active' ? 'border-info shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('all', 'active', 'all', 'all')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">EroAds-Partnerprogramm</div>
                        <div class="fs-4 fw-bold text-info"><?= number_format((int)$kpi['eroads_active'], 0, ',', '.') ?></div>
                        <div class="small text-muted">Nimmt am Werbeprogramm teil</div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-megaphone-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Shop-System: EroCMS -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $sysFilter === 'erocms' ? 'border-primary shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('all', 'all', 'erocms', 'all')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Shop-System: EroCMS</div>
                        <div class="fs-4 fw-bold ec-text-primary"><?= number_format((int)$kpi['shop_erocms'], 0, ',', '.') ?></div>
                        <div class="small text-muted">Standard EroCMS-Installation</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-shop fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Shop-System: Eigenes System (own) -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $sysFilter === 'own' ? 'border-primary shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('all', 'all', 'own', 'all')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Eigenes Shop-System</div>
                        <div class="fs-4 fw-bold"><?= number_format((int)$kpi['shop_own'], 0, ',', '.') ?></div>
                        <div class="small text-muted">Custom / API-Integration (own)</div>
                    </div>
                    <div class="rounded-circle bg-dark bg-opacity-10 text-dark d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-code-slash fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Webseiten mit Bannern -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $banFilter === 'with_banners' ? 'border-warning shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Websites.quickFilter('all', 'all', 'all', 'with_banners')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Mit Werbebannern</div>
                        <div class="fs-4 fw-bold text-warning"><?= number_format((int)$kpi['with_banners'], 0, ',', '.') ?></div>
                        <div class="small text-muted"><?= number_format((int)$kpi['total_banners'], 0, ',', '.') ?> Banner insgesamt</div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-images fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 8. Cloud-User & Shop-Käufe -->
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Cloud-User &amp; Käufe</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format((int)$kpi['total_users'], 0, ',', '.') ?></div>
                        <div class="small text-muted"><?= number_format((int)$kpi['total_purchases'], 0, ',', '.') ?> Content-Käufe gesamt</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. FILTER- & SUCHLEISTE -->
    <div class="card-kkc mb-4">
        <div class="card-kkc-body py-3">
            <form id="sites-filter-form" onsubmit="event.preventDefault(); App.Websites.applyFilters(1);">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Suche (Domain, Site-/Shop-ID, Partner-ID, Merchant)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="sites-filter-q" value="<?= View::e($qVal) ?>" placeholder="z. B. domain.com, 50730, KJDJEEESAX...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Status</label>
                        <select class="form-select form-select-sm" id="sites-filter-status" onchange="App.Websites.applyFilters(1)">
                            <option value="all" <?= $stFilter === 'all' ? 'selected' : '' ?>>Alle Status</option>
                            <option value="active" <?= $stFilter === 'active' ? 'selected' : '' ?>>Aktiv (Freigegeben)</option>
                            <option value="inactive" <?= $stFilter === 'inactive' ? 'selected' : '' ?>>Inaktiv (Gesperrt)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">EroAds-Teilnahme</label>
                        <select class="form-select form-select-sm" id="sites-filter-eroads" onchange="App.Websites.applyFilters(1)">
                            <option value="all" <?= $adsFilter === 'all' ? 'selected' : '' ?>>Alle</option>
                            <option value="active" <?= $adsFilter === 'active' ? 'selected' : '' ?>>EroAds Aktiv</option>
                            <option value="inactive" <?= $adsFilter === 'inactive' ? 'selected' : '' ?>>EroAds Inaktiv</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Shop-System &amp; Banner</label>
                        <div class="d-flex gap-1">
                            <select class="form-select form-select-sm" id="sites-filter-shopsys" onchange="App.Websites.applyFilters(1)">
                                <option value="all" <?= $sysFilter === 'all' ? 'selected' : '' ?>>Alle Systeme</option>
                                <option value="erocms" <?= $sysFilter === 'erocms' ? 'selected' : '' ?>>EroCMS</option>
                                <option value="own" <?= $sysFilter === 'own' ? 'selected' : '' ?>>Eigenes (own)</option>
                            </select>
                            <select class="form-select form-select-sm" id="sites-filter-banners" onchange="App.Websites.applyFilters(1)">
                                <option value="all" <?= $banFilter === 'all' ? 'selected' : '' ?>>Banner: Alle</option>
                                <option value="with_banners" <?= $banFilter === 'with_banners' ? 'selected' : '' ?>>Mit Bannern</option>
                                <option value="without_banners" <?= $banFilter === 'without_banners' ? 'selected' : '' ?>>Ohne Banner</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Sortierung</label>
                        <select class="form-select form-select-sm" id="sites-filter-sort" onchange="App.Websites.applyFilters(1)">
                            <option value="id_desc" <?= $sortVal === 'id_desc' ? 'selected' : '' ?>>Neueste zuerst (ID ↓)</option>
                            <option value="id_asc" <?= $sortVal === 'id_asc' ? 'selected' : '' ?>>Älteste zuerst (ID ↑)</option>
                            <option value="domain_asc" <?= $sortVal === 'domain_asc' ? 'selected' : '' ?>>Domain (A → Z)</option>
                            <option value="domain_desc" <?= $sortVal === 'domain_desc' ? 'selected' : '' ?>>Domain (Z → A)</option>
                            <option value="comm_desc" <?= $sortVal === 'comm_desc' ? 'selected' : '' ?>>WM-Provision ↓</option>
                            <option value="updated_desc" <?= $sortVal === 'updated_desc' ? 'selected' : '' ?>>Zuletzt aktualisiert ↓</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                            <i class="bi bi-funnel-fill me-1"></i>Filtern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. ERGEBNISTABELLE -->
    <div class="card-kkc">
        <div class="card-kkc-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="card-title">
                <i class="bi bi-table ec-text-primary"></i>
                <span>Gefundene Webseiten (<?= number_format($totalFiltered, 0, ',', '.') ?>)</span>
            </div>
            <div class="d-flex align-items-center gap-2 small">
                <span class="text-muted">Einträge pro Seite:</span>
                <select class="form-select form-select-sm" style="width:auto;" id="sites-filter-limit" onchange="App.Websites.applyFilters(1)">
                    <?php foreach ([50, 100, 250, 500] as $lim): ?>
                        <option value="<?= $lim ?>" <?= $perPage === $lim ? 'selected' : '' ?>><?= $lim ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width:110px;">ID / Shop-ID</th>
                        <th style="width:95px;">Status</th>
                        <th>Domain &amp; Shop-System</th>
                        <th style="width:210px;">P4C-Partner (Merchant)</th>
                        <th style="width:150px;">EroAds &amp; Provision</th>
                        <th style="width:110px;">Banner</th>
                        <th style="width:165px;">Cloud-User &amp; Käufe</th>
                        <th style="width:140px;">Aktualisiert am</th>
                        <th style="width:105px;" class="text-end">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sites)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-globe2 fs-3 d-block mb-2"></i>
                                Keine Webseiten für die gewählten Filterkriterien gefunden.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sites as $s):
                            $sid = (int)$s['id'];
                            $mid = (int)$s['merchant_id'];
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold font-monospace">#<?= $sid ?></div>
                                    <div class="text-muted font-monospace" style="font-size:0.75rem;">Shop: <?= (int)$s['p4c_shop_id'] ?></div>
                                </td>
                                <td>
                                    <?php if ($s['status']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktiv</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="#/site?id=<?= $sid ?>" class="fw-bold text-decoration-none fs-6">
                                            <?= View::e((string)$s['domain']) ?>
                                        </a>
                                        <a href="https://<?= View::e((string)$s['domain']) ?>" target="_blank" rel="noopener" class="text-muted" title="Webseite in neuem Tab öffnen">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </div>
                                    <div class="mt-0.5">
                                        <?php if ($s['shop_system'] === 'erocms'): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">EroCMS</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark bg-opacity-10 text-dark border">Eigenes System (<?= View::e((string)$s['shop_system']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($mid > 0): ?>
                                        <a href="#/merchant?id=<?= $mid ?>" class="fw-bold text-decoration-none d-block">
                                            <?= View::e((string)($s['merchant_username'] ?: ('Merchant #' . $mid))) ?>
                                        </a>
                                        <span class="text-muted font-monospace" style="font-size:0.75rem;">
                                            PID: <?= View::e((string)$s['partner_id']) ?> &bull; MID #<?= $mid ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="#/merchant?id=<?= View::e((string)$s['partner_id']) ?>" class="font-monospace fw-semibold text-decoration-none">
                                            <?= View::e((string)$s['partner_id']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['is_eroads_active']): ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-megaphone-fill me-1"></i>EroAds Aktiv</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">EroAds Inaktiv</span>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-0.5">
                                        WM-Provision: <strong><?= number_format((float)$s['webmaster_commision'], 0, ',', '.') ?>%</strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ((int)$s['banners_total'] > 0): ?>
                                        <a href="#/site?id=<?= $sid ?>" class="badge bg-warning bg-opacity-15 text-dark border border-warning text-decoration-none">
                                            <i class="bi bi-images me-1"></i><?= (int)$s['banners_total'] ?> Banner
                                        </a>
                                        <?php if ((int)$s['banners_pending'] > 0): ?>
                                            <div class="small text-danger fw-bold mt-0.5"><?= (int)$s['banners_pending'] ?> neu</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <i class="bi bi-people text-primary me-1"></i>
                                        <strong><?= number_format((int)$s['users_count'], 0, ',', '.') ?></strong> User
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        <?= number_format((int)$s['sales_total'], 0, ',', '.') ?> Käufe (<?= View::e((string)$s['sales_rev_eur']) ?>)
                                    </div>
                                </td>
                                <td class="font-monospace text-muted">
                                    <?= View::e((string)$s['updated_de']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="#/site?id=<?= $sid ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 5. PAGINIERUNG -->
        <?php if ($totalPages > 1): ?>
            <div class="card-kkc-body border-top py-2 d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                <div class="text-muted">
                    Seite <strong><?= $page ?></strong> von <strong><?= $totalPages ?></strong>
                    (<?= number_format($totalFiltered, 0, ',', '.') ?> Webseiten)
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?>
                            onclick="App.Websites.applyFilters(<?= $page - 1 ?>)">
                        <i class="bi bi-chevron-left"></i> Zurück
                    </button>
                    <?php
                    $startP = max(1, $page - 2);
                    $endP   = min($totalPages, $page + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                    ?>
                        <button type="button" class="btn <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
                                onclick="App.Websites.applyFilters(<?= $p ?>)">
                            <?= $p ?>
                        </button>
                    <?php endfor; ?>
                    <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?>
                            onclick="App.Websites.applyFilters(<?= $page + 1 ?>)">
                        Weiter <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>
