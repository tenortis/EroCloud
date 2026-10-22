<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<int, array<string, mixed>> $banners
 * @var array<string, int>               $kpis
 * @var array<int, array<string, mixed>> $sitesList
 * @var array<int, array<string, mixed>> $dimensionsList
 * @var string                           $statusFilter
 * @var int                              $siteIdFilter
 * @var string                           $typeFilter
 * @var string                           $dimensionFilter
 * @var string                           $search
 * @var int                              $totalFiltered
 * @var int                              $page
 * @var int                              $perPage
 * @var int                              $totalPages
 */
?>
<div class="container-fluid px-0">

    <!-- 1. SEITEN-HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-images ec-text-primary"></i>
                <span>Partnerprogramm · Alle Werbebanner</span>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 ms-1">
                    <?= number_format((int)($kpis['total_all'] ?? 0), 0, ',', '.') ?>
                </span>
            </h4>
            <div class="text-muted small">
                Hier sind alle EroAds-Werbebanner aufgelistet, die Webmastern für ihre Kampagnen zur Verfügung stehen.
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="#/webmaster-partner" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Aktive Webmaster
            </a>
            <a href="#/banners-new" class="btn btn-sm <?= ($kpis['pending_new_count'] ?? 0) > 0 ? 'btn-warning fw-semibold' : 'btn-outline-secondary' ?>">
                <i class="bi bi-shield-check me-1"></i>Neue Banner prüfen
                <span class="badge <?= ($kpis['pending_new_count'] ?? 0) > 0 ? 'bg-dark' : 'bg-secondary' ?> ms-1">
                    <?= number_format((int)($kpis['pending_new_count'] ?? 0), 0, ',', '.') ?>
                </span>
            </a>
        </div>
    </div>

    <!-- 2. KPI-KACHELN (KLICKBAR ZUM SCHNELLFILTERN) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $statusFilter === 'published' ? 'border-primary shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Affiliate.quickFilterBanners('published')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Banner Gesamt</div>
                        <div class="fs-4 fw-bold"><?= number_format((int)($kpis['total_all'] ?? 0), 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">Über <?= count($sitesList) ?> Domains</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-collection-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $statusFilter === 'active' ? 'border-success shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Affiliate.quickFilterBanners('active')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Aktiv (Freigeschaltet)</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format((int)($kpis['active_count'] ?? 0), 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">Für Webmaster sichtbar</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $statusFilter === 'rejected' ? 'border-danger shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="App.Affiliate.quickFilterBanners('rejected')">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Abgelehnt / Inaktiv</div>
                        <div class="fs-4 fw-bold text-danger"><?= number_format((int)($kpis['rejected_count'] ?? 0), 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">Gesperrt für Kampagnen</div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-x-octagon-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-kkc h-100 <?= $statusFilter === 'pending_new' ? 'border-warning shadow-sm' : '' ?>"
                 style="cursor:pointer;" onclick="window.location.hash='#/banners-new'">
                <div class="card-kkc-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Offene Prüfungen</div>
                        <div class="fs-4 fw-bold <?= ($kpis['pending_new_count'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                            <?= number_format((int)($kpis['pending_new_count'] ?? 0), 0, ',', '.') ?>
                        </div>
                        <div class="text-primary fw-semibold" style="font-size:0.75rem;">Zur Prüf-Ansicht →</div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. FILTER-LEISTE -->
    <div class="card-kkc mb-4">
        <div class="card-kkc-body">
            <form onsubmit="App.Affiliate.filterBanners(event)" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1" for="ban-status-select">Status</label>
                    <select id="ban-status-select" class="form-select form-select-sm" onchange="App.Affiliate.filterBanners(event)">
                        <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Veröffentlicht (Standard)</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Nur Aktive (Freigeschaltet)</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Abgelehnt / Inaktiv</option>
                        <option value="pending_new" <?= $statusFilter === 'pending_new' ? 'selected' : '' ?>>Mit neuem Ersatz-Banner</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Alle Datensätze</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold mb-1" for="ban-site-select">Domain / Webseite</label>
                    <select id="ban-site-select" class="form-select form-select-sm" onchange="App.Affiliate.filterBanners(event)">
                        <option value="0">Alle Domains (<?= count($sitesList) ?>)</option>
                        <?php foreach ($sitesList as $sItem): ?>
                            <option value="<?= (int)$sItem['id'] ?>" <?= $siteIdFilter === (int)$sItem['id'] ? 'selected' : '' ?>>
                                <?= View::e((string)$sItem['domain']) ?> (<?= (int)$sItem['banner_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1" for="ban-dim-select">Format (Maße)</label>
                    <select id="ban-dim-select" class="form-select form-select-sm" onchange="App.Affiliate.filterBanners(event)">
                        <option value="">Alle Formate</option>
                        <?php foreach ($dimensionsList as $dim): ?>
                            <option value="<?= View::e((string)$dim['key']) ?>" <?= $dimensionFilter === (string)$dim['key'] ? 'selected' : '' ?>>
                                <?= View::e((string)$dim['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label small fw-semibold mb-1" for="ban-type-select">Typ</label>
                    <select id="ban-type-select" class="form-select form-select-sm" onchange="App.Affiliate.filterBanners(event)">
                        <option value="">Alle</option>
                        <option value="jpg" <?= $typeFilter === 'jpg' ? 'selected' : '' ?>>JPG</option>
                        <option value="gif" <?= $typeFilter === 'gif' ? 'selected' : '' ?>>GIF</option>
                        <option value="png" <?= $typeFilter === 'png' ? 'selected' : '' ?>>PNG</option>
                    </select>
                </div>

                <div class="col-8 col-md-2">
                    <label class="form-label small fw-semibold mb-1" for="ban-search-input">Suche (ID / Domain)</label>
                    <input type="text" id="ban-search-input" class="form-control form-control-sm"
                           value="<?= View::e($search) ?>" placeholder="Banner-ID oder Domain...">
                </div>

                <div class="col-4 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filtern
                    </button>
                    <?php if ($statusFilter !== 'published' || $siteIdFilter > 0 || $typeFilter !== '' || $dimensionFilter !== '' || $search !== ''): ?>
                        <a href="#/banners" class="btn btn-sm btn-outline-secondary" title="Filter zurücksetzen">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. BANNER-TABELLE -->
    <div class="card-kkc">
        <div class="card-kkc-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                <i class="bi bi-images me-2"></i>Werbebanner
                <span class="badge bg-light text-dark border ms-1"><?= number_format($totalFiltered, 0, ',', '.') ?></span>
            </span>
            <span class="small fw-normal opacity-75">
                Seite <?= $page ?> von <?= $totalPages ?>
            </span>
        </div>
        <div class="card-kkc-body p-0">
            <?php if (empty($banners)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-image fs-2 d-block mb-2 opacity-50"></i>
                    Keine Banner für die gewählten Filterkriterien gefunden.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:120px;">Status</th>
                                <th style="width:220px;">Domain</th>
                                <th style="width:130px;">Typ &amp; Format</th>
                                <th style="width:150px;">Hochgeladen am</th>
                                <th>Vorschau &amp; Banner-ID</th>
                                <th class="text-end pe-3" style="width:120px;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banners as $ban): ?>
                                <?php
                                $fileId     = (string)$ban['file_id'];
                                $rejected   = (int)$ban['rejected'];
                                $w          = (int)$ban['width'];
                                $h          = (int)$ban['height'];
                                $previewUrl = 'media.php?type=banner&file_id=' . rawurlencode($fileId);
                                ?>
                                <tr id="banner-row-<?= View::e($fileId) ?>">
                                    <td class="ps-3">
                                        <?php if ($rejected === 0): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktiv</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Inaktiv (<?= $rejected ?>)</span>
                                        <?php endif; ?>
                                        <?php if ($ban['new_filename'] !== ''): ?>
                                            <div class="mt-1">
                                                <a href="#/banners-new" class="badge bg-warning text-dark text-decoration-none">
                                                    Ersatz offen
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#/site?id=<?= (int)$ban['site_id'] ?>" class="fw-semibold text-decoration-none ec-text-primary">
                                            <i class="bi bi-globe2 me-1"></i><?= View::e((string)$ban['domain']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size:0.74rem;">
                                            Site #<?= (int)$ban['site_id'] ?> · Partner <?= View::e((string)$ban['partner_id']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border"><?= View::e((string)$ban['type']) ?></span>
                                        <div class="small fw-semibold mt-1"><?= $w ?> × <?= $h ?> px</div>
                                    </td>
                                    <td class="small text-muted">
                                        <?= View::e((string)$ban['upload_datetime']) ?>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex flex-column gap-1">
                                            <a href="<?= View::e($previewUrl) ?>" target="_blank" rel="noopener noreferrer" class="d-inline-block">
                                                <img src="<?= View::e($previewUrl) ?>"
                                                     alt="Banner <?= View::e($fileId) ?>"
                                                     loading="lazy"
                                                     class="rounded border bg-light"
                                                     style="max-width:520px; max-height:220px; width:auto; height:auto; display:block;">
                                            </a>
                                            <div class="small text-muted">
                                                Banner-ID: <code class="fw-semibold text-dark"><?= View::e($fileId) ?></code>
                                                <?php if (empty($ban['file_exists'])): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Lokale Datei fehlt</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="App.Affiliate.handleBannerAction('<?= View::e($fileId) ?>', 'delete', false)"
                                                title="Das Banner wird sofort gelöscht.">
                                            <i class="bi bi-trash me-1"></i>Löschen
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="p-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small text-muted">
                            Zeige Seite <strong><?= $page ?></strong> von <strong><?= $totalPages ?></strong>
                            (<?= number_format($totalFiltered, 0, ',', '.') ?> Banner)
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary"
                                    <?= $page <= 1 ? 'disabled' : '' ?>
                                    onclick="App.Affiliate.goToBannersPage(<?= $page - 1 ?>)">
                                <i class="bi bi-chevron-left"></i> Zurück
                            </button>
                            <button type="button" class="btn btn-outline-secondary"
                                    <?= $page >= $totalPages ? 'disabled' : '' ?>
                                    onclick="App.Affiliate.goToBannersPage(<?= $page + 1 ?>)">
                                Weiter <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
