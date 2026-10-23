<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/** @var array<string, mixed> $site */
/** @var int $prevId */
/** @var int $nextId */
/** @var array<int, array<string, mixed>> $banners */
/** @var array<int, array<string, mixed>> $recentUsers */
/** @var array<int, array<string, mixed>> $recentSales */

$sid = (int)$site['id'];
$mid = (int)$site['merchant_id'];
?>
<div class="container-fluid px-0">

    <!-- 1. SEITEN-HEADER MIT VOR/ZURÜCK-NAVIGATION -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="#/sites" class="btn btn-sm btn-outline-kkc">
                <i class="bi bi-arrow-left me-1"></i>Zur Übersicht
            </a>
            <h1 class="h4 fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-globe2 ec-text-primary"></i>
                <span><?= View::e((string)$site['domain']) ?></span>
                <span class="badge bg-light text-dark border font-monospace fs-6">#<?= $sid ?></span>
            </h1>
            <?php if ($site['status']): ?>
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktiv</span>
            <?php else: ?>
                <span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Inaktiv</span>
            <?php endif; ?>
            <?php if ($site['is_eroads_active']): ?>
                <span class="badge bg-info text-dark"><i class="bi bi-megaphone-fill me-1"></i>EroAds Aktiv (<?= number_format((float)$site['webmaster_commision'], 0, ',', '.') ?>%)</span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="https://<?= View::e((string)$site['domain']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-box-arrow-up-right me-1"></i>Live-Domain öffnen
            </a>
            <?php if ((string)$site['p4c_shop_acp_url'] !== ''): ?>
                <a href="<?= View::e((string)$site['p4c_shop_acp_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-shop me-1"></i>Shop in Pay4Coins-ACP (#<?= (int)$site['p4c_shop_id'] ?>)
                </a>
            <?php endif; ?>
            <div class="btn-group btn-group-sm">
                <a href="<?= $prevId > 0 ? '#/site?id=' . $prevId : 'javascript:void(0)' ?>"
                   class="btn btn-outline-secondary <?= $prevId <= 0 ? 'disabled' : '' ?>" title="Vorherige Webseite">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="<?= $nextId > 0 ? '#/site?id=' . $nextId : 'javascript:void(0)' ?>"
                   class="btn btn-outline-secondary <?= $nextId <= 0 ? 'disabled' : '' ?>" title="Nächste Webseite">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. 4 KPI-KACHELN ZUR WEBSEITE -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3">
                    <div class="text-muted small fw-semibold">Pay4Coins Shop-ID</div>
                    <div class="fs-4 fw-bold font-monospace">#<?= (int)$site['p4c_shop_id'] ?></div>
                    <div class="small text-muted">Shop-System: <strong><?= View::e(strtoupper((string)$site['shop_system'])) ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3">
                    <div class="text-muted small fw-semibold">EroAds &amp; WM-Provision</div>
                    <div class="fs-4 fw-bold <?= $site['is_eroads_active'] ? 'text-info' : 'text-muted' ?>">
                        <?= number_format((float)$site['webmaster_commision'], 0, ',', '.') ?> %
                    </div>
                    <div class="small text-muted">
                        <?= $site['is_eroads_active'] ? 'Nimmt am Webmasterprogramm teil' : 'Nimmt nicht am Webmasterprogramm teil' ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3">
                    <div class="text-muted small fw-semibold">Werbebanner (ads_media)</div>
                    <div class="fs-4 fw-bold text-warning"><?= number_format((int)$site['banners_count'], 0, ',', '.') ?></div>
                    <div class="small text-muted">Hinterlegte Werbemittel dieser Seite</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-kkc h-100">
                <div class="card-kkc-body py-3">
                    <div class="text-muted small fw-semibold">Cloud-User &amp; Shop-Umsatz</div>
                    <div class="fs-4 fw-bold text-success"><?= number_format((int)$site['users_count'], 0, ',', '.') ?> User</div>
                    <div class="small text-muted"><?= number_format((int)$site['sales_count'], 0, ',', '.') ?> Käufe (<?= View::e((string)$site['sales_rev_eur']) ?>)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. TABS: STAMMDATEN | WERBEBANNER | CLOUD-USER & SHOP-KÄUFE -->
    <div class="card-kkc">
        <div class="card-kkc-header border-bottom pb-0">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="tab-btn-site-info" data-bs-toggle="tab" data-bs-target="#tab-site-info" type="button" role="tab">
                        <i class="bi bi-info-circle me-1"></i>Infos zur Webseite &amp; API
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="tab-btn-site-banners" data-bs-toggle="tab" data-bs-target="#tab-site-banners" type="button" role="tab">
                        <i class="bi bi-images me-1"></i>Banner (<?= (int)$site['banners_count'] ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="tab-btn-site-shop" data-bs-toggle="tab" data-bs-target="#tab-site-shop" type="button" role="tab">
                        <i class="bi bi-people me-1"></i>Cloud-User (<?= (int)$site['users_count'] ?>) &amp; Shop-Käufe (<?= (int)$site['sales_count'] ?>)
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-kkc-body tab-content pt-4">

            <!-- ============================================================ -->
            <!-- TAB 1: INFOS ZUR WEBSEITE & EROADS-API                       -->
            <!-- ============================================================ -->
            <div class="tab-pane fade show active" id="tab-site-info" role="tabpanel">
                <div class="row g-4">
                    <!-- Linke Spalte: Webseiten- & EroAds-Konfiguration -->
                    <div class="col-12 col-lg-7">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2 fw-bold small">
                                <i class="bi bi-globe me-1 text-primary"></i>Webseiten- &amp; EroAds-Stammdaten
                            </div>
                            <div class="card-body">
                                <div class="alert <?= $site['is_eroads_active'] ? 'alert-success' : 'alert-secondary' ?> py-2 small d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <i class="bi <?= $site['is_eroads_active'] ? 'bi-check-circle-fill' : 'bi-dash-circle' ?> me-1"></i>
                                        <strong><?= $site['is_eroads_active'] ? 'Nimmt am Webmasterprogramm (EroAds) teil.' : 'Nimmt aktuell nicht am Webmasterprogramm teil.' ?></strong>
                                    </div>
                                    <span class="badge bg-dark">WM-Provision: <?= number_format((float)$site['webmaster_commision'], 0, ',', '.') ?> %</span>
                                </div>

                                <div class="row g-3 small">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label text-muted mb-1">Interne Site-ID (`sites.id`):</label>
                                        <input type="text" class="form-control form-control-sm font-monospace bg-light" value="#<?= $sid ?>" readonly>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label text-muted mb-1">
                                            Pay4Coins-Shop-ID (`p4c_shop_id`):
                                            <?php if ((string)$site['p4c_shop_acp_url'] !== ''): ?>
                                                <a href="<?= View::e((string)$site['p4c_shop_acp_url']) ?>" target="_blank" rel="noopener" class="ms-1 text-decoration-none">
                                                    Shop in Pay4Coins aufrufen <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </label>
                                        <input type="text" class="form-control form-control-sm font-monospace bg-light" value="<?= (int)$site['p4c_shop_id'] ?>" readonly>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label class="form-label text-muted mb-1">Domain:</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control font-monospace bg-light" value="<?= View::e((string)$site['domain']) ?>" readonly>
                                            <a href="https://<?= View::e((string)$site['domain']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label class="form-label text-muted mb-1">Webmaster-Provision:</label>
                                        <input type="text" class="form-control form-control-sm font-monospace bg-light" value="<?= number_format((float)$site['webmaster_commision'], 0, ',', '.') ?>%" readonly>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label text-muted mb-1">
                                            EroAds-URL:
                                            <?php if ((string)$site['eroads_test_url'] !== ''): ?>
                                                <a href="<?= View::e((string)$site['eroads_test_url']) ?>" target="_blank" rel="noopener" class="ms-2 fw-bold text-decoration-none">
                                                    <i class="bi bi-play-circle-fill me-1"></i>API mit Merchant-Key aufrufen (Datum: <?= date('Y-m-d') ?>)
                                                </a>
                                            <?php endif; ?>
                                        </label>
                                        <input type="text" class="form-control form-control-sm font-monospace bg-light" value="<?= View::e((string)($site['eroads_url'] ?: '- Keine EroAds-URL hinterlegt -')) ?>" readonly>
                                    </div>

                                    <div class="col-6 col-sm-4">
                                        <label class="form-label text-muted mb-1">Shop-System:</label>
                                        <input type="text" class="form-control form-control-sm bg-light" value="<?= View::e((string)$site['shop_system']) ?>" readonly>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <label class="form-label text-muted mb-1">Zuletzt synchronisiert:</label>
                                        <input type="text" class="form-control form-control-sm font-monospace bg-light" value="<?= View::e((string)$site['updated_de']) ?>" readonly>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label text-muted mb-1">Conversions-Import:</label>
                                        <input type="text" class="form-control form-control-sm font-monospace bg-light" value="<?= View::e((string)$site['conversions_de']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rechte Spalte: Zugeordneter Pay4Coins-Partner (Merchant) -->
                    <div class="col-12 col-lg-5">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2 fw-bold small d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-briefcase-fill me-1 text-primary"></i>Zugeordneter Pay4Coins-Merchant</span>
                                <?php if ($mid > 0): ?>
                                    <a href="#/merchant?id=<?= $mid ?>" class="btn btn-xs btn-primary py-0 px-2 small">
                                        Merchant öffnen
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body small">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <span class="text-muted d-block">Pay4Coins Partner-ID (PID):</span>
                                        <a href="#/merchant?id=<?= View::e((string)($mid > 0 ? $mid : $site['partner_id'])) ?>" class="fw-bold font-monospace fs-6 text-decoration-none">
                                            <?= View::e((string)$site['partner_id']) ?>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Interne Merchant-ID (MID):</span>
                                        <?php if ($mid > 0): ?>
                                            <a href="#/merchant?id=<?= $mid ?>" class="fw-bold font-monospace fs-6 text-decoration-none">#<?= $mid ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">Nicht lokal verknüpft</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">P4C-Benutzername:</span>
                                        <strong><?= View::e((string)($site['merchant_username'] ?: '-')) ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Vor- &amp; Nachname:</span>
                                        <strong><?= View::e((string)($site['merchant_fullname'] ?: '-')) ?></strong>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted d-block">E-Mail-Adresse:</span>
                                        <span class="font-monospace"><?= View::e((string)($site['merchant_email'] ?: '-')) ?></span>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="fw-bold text-muted mb-2">Gemeldete Shop-Inhalte (Pay4Coins-Sync):</div>
                                <div class="row g-2 text-center">
                                    <div class="col-4">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="fw-bold fs-6"><?= number_format((int)$site['number_of_movies'], 0, ',', '.') ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;">Filme</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="fw-bold fs-6"><?= number_format((int)$site['number_of_albums'], 0, ',', '.') ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;">Fotoalben</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="fw-bold fs-6"><?= number_format((int)$site['number_of_articles'], 0, ',', '.') ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;">Artikel</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: WERBEBANNER DIESER WEBSEITE (ads_media)               -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-site-banners" role="tabpanel">
                <?php if (empty($banners)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-images fs-2 d-block mb-2"></i>
                        Für die Webseite <strong><?= View::e((string)$site['domain']) ?></strong> sind aktuell keine Werbebanner hinterlegt.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($banners as $b):
                            $fid  = (string)$b['file_id'];
                            $w    = (int)$b['width'];
                            $h    = (int)$b['height'];
                            $code = '<script src="' . (string)$site['ads_base_url'] . '/bc/' . $fid . '" type="text/javascript"></script>' . "\n"
                                  . '<div class="adsbyerocloud" data-id="' . $fid . '" style="display:inline-block;width:' . $w . 'px;height:' . $h . 'px;"></div>';
                        ?>
                            <div class="border rounded p-3 bg-light bg-opacity-50">
                                <div class="row g-3 align-items-center">
                                    <div class="col-12 col-md-4 small">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-primary"><?= View::e((string)$b['type']) ?></span>
                                            <span class="fw-bold font-monospace"><?= $w ?> &times; <?= $h ?> Pixel</span>
                                            <?php if ($b['is_pending']): ?>
                                                <span class="badge bg-warning text-dark">Neues Bild zur Prüfung</span>
                                            <?php elseif ($b['is_rejected']): ?>
                                                <span class="badge bg-danger">Abgelehnt</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Aktiv</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-1">
                                            <span class="text-muted">Banner-ID:</span>
                                            <code class="ms-1"><?= View::e($fid) ?></code>
                                        </div>
                                        <div class="mb-2 text-muted">
                                            Hochgeladen am: <strong><?= View::e((string)$b['upload_de']) ?></strong>
                                        </div>
                                        <div>
                                            <label class="form-label text-muted mb-1" style="font-size:0.72rem;">EroAds-Einbettungscode:</label>
                                            <textarea class="form-control form-control-sm font-monospace" rows="2" style="font-size:0.72rem;" readonly onclick="this.select()"><?= View::e($code) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-8 text-center">
                                        <div class="p-2 bg-dark rounded d-inline-block" style="max-width:100%;">
                                            <img src="<?= View::e((string)$b['preview_url']) ?>"
                                                 alt="Banner <?= View::e($fid) ?>"
                                                 class="img-fluid rounded"
                                                 style="max-height:240px; cursor:pointer;"
                                                 onclick="App.openImageLightbox('<?= View::e((string)$b['preview_url']) ?>', 'Banner <?= View::e($fid) ?> (<?= $w ?>x<?= $h ?> px)')">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: CLOUD-USER & SHOP-VERKÄUFE                            -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-site-shop" role="tabpanel">
                <div class="row g-4">
                    <!-- Linke Spalte: Neueste Cloud-User dieses Shops -->
                    <div class="col-12 col-xl-6">
                        <h6 class="fw-bold mb-2">
                            <i class="bi bi-people-fill text-primary me-1"></i>Neueste Cloud-User auf <?= View::e((string)$site['domain']) ?>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User-ID</th>
                                        <th>Benutzername</th>
                                        <th>Coins</th>
                                        <th>Zuletzt online</th>
                                        <th class="text-end">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentUsers)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Keine registrierten Cloud-User für Shop #<?= (int)$site['p4c_shop_id'] ?> gefunden.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentUsers as $u): ?>
                                            <tr>
                                                <td class="font-monospace fw-bold">#<?= (int)$u['id'] ?></td>
                                                <td>
                                                    <a href="#/user?id=<?= (int)$u['id'] ?>" class="fw-bold text-decoration-none">
                                                        <?= View::e((string)($u['username'] ?: ('User #' . $u['id']))) ?>
                                                    </a>
                                                    <?php if ($u['locked']): ?>
                                                        <span class="badge bg-danger ms-1">Gesperrt</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="font-monospace"><?= number_format((int)$u['amount_coins'], 0, ',', '.') ?></td>
                                                <td class="font-monospace text-muted"><?= View::e((string)$u['lastonline_de']) ?></td>
                                                <td class="text-end">
                                                    <a href="#/user?id=<?= (int)$u['id'] ?>" class="btn btn-xs btn-outline-primary py-0 px-2">Details</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Rechte Spalte: Letzte Film-Käufe über diesen Shop -->
                    <div class="col-12 col-xl-6">
                        <h6 class="fw-bold mb-2">
                            <i class="bi bi-cart-check-fill text-success me-1"></i>Letzte Film-Käufe über Shop #<?= (int)$site['p4c_shop_id'] ?>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Datum</th>
                                        <th>Film &amp; Darsteller</th>
                                        <th>Typ</th>
                                        <th>Preis / Prov.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentSales)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Noch keine Film-Käufe über diesen Shop verzeichnet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentSales as $sl): ?>
                                            <tr>
                                                <td class="font-monospace text-muted"><?= View::e((string)$sl['buy_dt_de']) ?></td>
                                                <td>
                                                    <?php if ((int)$sl['online_id'] > 0): ?>
                                                        <a href="#/movie-edit?id=<?= (int)$sl['online_id'] ?>" class="fw-bold text-decoration-none d-block">
                                                            <?= View::e((string)($sl['title'] ?: $sl['file_id'])) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="fw-semibold d-block"><?= View::e((string)($sl['title'] ?: $sl['file_id'])) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ((int)$sl['actor_id'] > 0): ?>
                                                        <a href="#/actor?id=<?= (int)$sl['actor_id'] ?>" class="text-muted text-decoration-none" style="font-size:0.75rem;">
                                                            <i class="bi bi-person me-1"></i><?= View::e((string)($sl['actor_name'] ?: ('Profil #' . $sl['actor_id']))) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= View::e((string)$sl['buy_as']) ?></span>
                                                </td>
                                                <td class="font-monospace">
                                                    <strong class="text-success"><?= View::e((string)$sl['price_eur']) ?></strong>
                                                    <div class="text-muted" style="font-size:0.72rem;">Prov.: <?= View::e((string)$sl['comm_eur']) ?></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
