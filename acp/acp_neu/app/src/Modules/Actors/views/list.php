<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, int> $kpis
 * @var array<int, array<string, mixed>> $actors
 * @var int $totalFiltered
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var array<string, mixed> $filters
 */

$buildPageUrl = static function (int $targetPage) use ($filters, $perPage): string {
    $params = [];
    if (($filters['status'] ?? 'all') !== 'all') {
        $params['status'] = (string)$filters['status'];
    }
    if (($filters['main_cat'] ?? 'all') !== 'all') {
        $params['main_cat'] = (string)$filters['main_cat'];
    }
    if (($filters['display_group'] ?? 'all') !== 'all') {
        $params['display_group'] = (string)$filters['display_group'];
    }
    if (($filters['img_filter'] ?? 'all') !== 'all') {
        $params['img_filter'] = (string)$filters['img_filter'];
    }
    if (!empty($filters['merchant_id'])) {
        $params['merchant_id'] = (int)$filters['merchant_id'];
    }
    if (($filters['q'] ?? '') !== '') {
        $params['q'] = (string)$filters['q'];
    }
    if (($filters['sort'] ?? 'id_desc') !== 'id_desc') {
        $params['sort'] = (string)$filters['sort'];
    }
    if ($perPage !== 50) {
        $params['per_page'] = $perPage;
    }
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }
    return '#/actors' . (!empty($params) ? '?' . http_build_query($params) : '');
};
?>

<!-- Kopfbereich -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-bounding-box text-primary me-2"></i>Darsteller-Profile</h4>
        <small class="text-muted">Zentrale Übersicht, Prüfung und Verwaltung aller angelegten Darsteller-Profile in der EroCloud.</small>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <?php if (($kpis['new_5d'] ?? 0) > 0): ?>
            <a href="#/actors?status=new_5d" class="btn btn-sm btn-warning text-dark fw-semibold">
                <i class="bi bi-stars me-1"></i><?= (int)$kpis['new_5d'] ?> neue Profile (letzte 5 Tage) prüfen
            </a>
        <?php endif; ?>
        <a href="#/co-actors-checking" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-person-badge me-1"></i>Drehpartner &amp; 18+ ID-Check
        </a>
    </div>
</div>

<!-- 4 KPI-Kacheln -->
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Profile Gesamt</div>
                        <div class="fs-3 fw-bold text-primary mt-1"><?= number_format((int)$kpis['total'], 0, ',', '.') ?></div>
                        <div class="small text-muted">
                            Porno: <strong><?= (int)$kpis['porn'] ?></strong> &bull;
                            Fetisch: <strong><?= (int)$kpis['fetish'] ?></strong>
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Status-Verteilung</div>
                        <div class="fs-3 fw-bold text-success mt-1"><?= number_format((int)$kpis['active'], 0, ',', '.') ?> Aktiv</div>
                        <div class="small text-muted">
                            Inaktiv: <strong><?= (int)$kpis['inactive'] ?></strong> &bull;
                            Gesperrt: <strong class="text-danger"><?= (int)$kpis['blocked'] ?></strong> &bull;
                            Gelöscht: <strong><?= (int)$kpis['deleted'] ?></strong>
                        </div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-check-circle-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Neu &amp; Inaktiv (&le; 5 Tage)</div>
                        <div class="fs-3 fw-bold <?= (int)$kpis['new_5d'] > 0 ? 'text-warning' : 'text-secondary' ?> mt-1">
                            <?= (int)$kpis['new_5d'] ?> Profile
                        </div>
                        <div class="small">
                            <a href="#/actors?status=new_5d" class="text-decoration-none fw-semibold">Nur neue Profile anzeigen &rarr;</a>
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-person-plus-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Fehlende Profilbilder</div>
                        <div class="fs-3 fw-bold <?= (int)$kpis['missing_img'] > 0 ? 'text-danger' : 'text-success' ?> mt-1">
                            <?= (int)$kpis['missing_img'] ?> Profile
                        </div>
                        <div class="small">
                            <a href="#/actors?img_filter=missing_img" class="text-decoration-none fw-semibold">Ohne FSK16/18 filtern &rarr;</a>
                        </div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-person-bounding-box fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter- & Suchleiste -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form onsubmit="return App.Actors.submitFilterForm(event);" class="row g-2 align-items-end">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label small fw-semibold mb-1">Suche (Profilname, ID, Partner-ID, Händler):</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="act-filter-q" value="<?= View::e((string)$filters['q']) ?>" placeholder="z. B. Profilname oder #ID...">
                </div>
            </div>

            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-semibold mb-1">Status:</label>
                <select class="form-select form-select-sm" id="act-filter-status" onchange="App.Actors.submitFilterForm()">
                    <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>Alle Status (<?= (int)$kpis['total'] ?>)</option>
                    <option value="new_5d" <?= $filters['status'] === 'new_5d' ? 'selected' : '' ?>>Neu &le; 5 Tage &amp; Inaktiv (<?= (int)$kpis['new_5d'] ?>)</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktiv (<?= (int)$kpis['active'] ?>)</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inaktiv (<?= (int)$kpis['inactive'] ?>)</option>
                    <option value="blocked" <?= $filters['status'] === 'blocked' ? 'selected' : '' ?>>Gesperrt (<?= (int)$kpis['blocked'] ?>)</option>
                    <option value="deleted" <?= $filters['status'] === 'deleted' ? 'selected' : '' ?>>Gelöscht (<?= (int)$kpis['deleted'] ?>)</option>
                </select>
            </div>

            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-semibold mb-1">Hauptkategorie:</label>
                <select class="form-select form-select-sm" id="act-filter-cat" onchange="App.Actors.submitFilterForm()">
                    <option value="all" <?= $filters['main_cat'] === 'all' ? 'selected' : '' ?>>Alle Kategorien</option>
                    <option value="porn" <?= $filters['main_cat'] === 'porn' ? 'selected' : '' ?>>Porno (<?= (int)$kpis['porn'] ?>)</option>
                    <option value="fetish" <?= $filters['main_cat'] === 'fetish' ? 'selected' : '' ?>>Fetisch (<?= (int)$kpis['fetish'] ?>)</option>
                    <option value="none" <?= $filters['main_cat'] === 'none' ? 'selected' : '' ?>>Noch ohne Hauptkategorie</option>
                </select>
            </div>

            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-semibold mb-1">Profilbilder:</label>
                <select class="form-select form-select-sm" id="act-filter-img" onchange="App.Actors.submitFilterForm()">
                    <option value="all" <?= $filters['img_filter'] === 'all' ? 'selected' : '' ?>>Alle Profile</option>
                    <option value="both_ok" <?= $filters['img_filter'] === 'both_ok' ? 'selected' : '' ?>>FSK16 &amp; FSK18 vorhanden</option>
                    <option value="missing_img" <?= $filters['img_filter'] === 'missing_img' ? 'selected' : '' ?>>Mind. 1 Profilbild fehlt</option>
                </select>
            </div>

            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-semibold mb-1">Sortierung:</label>
                <select class="form-select form-select-sm" id="act-filter-sort" onchange="App.Actors.submitFilterForm()">
                    <option value="id_desc" <?= $filters['sort'] === 'id_desc' ? 'selected' : '' ?>>ID absteigend (Neueste)</option>
                    <option value="id_asc" <?= $filters['sort'] === 'id_asc' ? 'selected' : '' ?>>ID aufsteigend (Älteste)</option>
                    <option value="username_asc" <?= $filters['sort'] === 'username_asc' ? 'selected' : '' ?>>Profilname (A–Z)</option>
                    <option value="lastonline_desc" <?= $filters['sort'] === 'lastonline_desc' ? 'selected' : '' ?>>Zuletzt online</option>
                    <option value="created_desc" <?= $filters['sort'] === 'created_desc' ? 'selected' : '' ?>>Erstelldatum</option>
                </select>
            </div>

            <div class="col-12 col-xl-1 d-flex gap-1">
                <input type="hidden" id="act-filter-merchant" value="<?= (int)$filters['merchant_id'] ?>">
                <input type="hidden" id="act-filter-perpage" value="<?= (int)$perPage ?>">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold" title="Filter anwenden">
                    <i class="bi bi-funnel-fill"></i>
                </button>
                <a href="#/actors" class="btn btn-sm btn-outline-secondary" title="Filter zurücksetzen">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>

        <?php if (!empty($filters['merchant_id'])): ?>
            <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between small">
                <span>
                    <i class="bi bi-funnel-fill text-primary me-1"></i>
                    Gefiltert nach <strong>Merchant-ID #<?= (int)$filters['merchant_id'] ?></strong>
                </span>
                <a href="#/actors" class="btn btn-xs btn-outline-secondary py-0 px-2">Händler-Filter aufheben</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ergebnistabelle -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small">
            Zeige <strong><?= count($actors) ?></strong> von <strong><?= number_format($totalFiltered, 0, ',', '.') ?></strong> passenden Darsteller-Profilen (Seite <?= $page ?> / <?= $totalPages ?>)
        </div>
        <div class="d-flex align-items-center gap-2">
            <input type="text" class="form-control form-control-sm" style="width:220px;" placeholder="Schnellfilter auf dieser Seite..." oninput="App.Actors.quickFilterTable(this.value)">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:75px;">ID</th>
                    <th style="width:135px;">FSK16 / FSK18</th>
                    <th>Profilname &amp; Steckbrief</th>
                    <th>P4C-Partner / Händler</th>
                    <th>Hauptkat. &amp; Nutzung</th>
                    <th>Content-Bestand</th>
                    <th style="width:105px;">Status</th>
                    <th style="width:140px;">Zuletzt online</th>
                    <th style="width:125px;" class="text-end">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($actors)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-person-slash fs-2 d-block mb-2"></i>
                            Keine Darsteller-Profile für deine aktuellen Filterkriterien gefunden.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($actors as $a):
                        $aid       = (int)$a['id'];
                        $fsk16Url  = 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=16&w=100';
                        $fsk16Full = 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=16';
                        $fsk18Url  = 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=18&w=100';
                        $fsk18Full = 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=18';

                        $statusBadge = match ($a['status']) {
                            'active'   => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktiv</span>',
                            'inactive' => '<span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Inaktiv</span>',
                            'blocked'  => '<span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Gesperrt</span>',
                            'deleted'  => '<span class="badge bg-dark"><i class="bi bi-trash3 me-1"></i>Gelöscht</span>',
                            default    => '<span class="badge bg-secondary">' . View::e((string)$a['status']) . '</span>',
                        };

                        $catBadge = match ($a['main_cat_key']) {
                            'porn'   => '<span class="badge bg-primary">Porno</span>',
                            'fetish' => '<span class="badge bg-dark">Fetisch</span>',
                            default  => '<span class="badge bg-light text-muted border">-</span>',
                        };

                        $rowSearch = strtolower(
                            $aid . ' ' . $a['username'] . ' ' . $a['merchant_username'] . ' ' .
                            $a['partner_id'] . ' ' . $a['merchant_id'] . ' ' . $a['main_cat_label']
                        );
                    ?>
                        <tr class="act-table-row" data-search="<?= View::e($rowSearch) ?>">
                            <td class="font-monospace fw-bold">
                                <a href="#/actor?id=<?= $aid ?>" class="text-decoration-none">#<?= $aid ?></a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <div class="position-relative" title="FSK16 Profilbild (Klicken zum Vergrößern)" style="cursor:pointer;" onclick="App.openImageLightbox('<?= View::e($fsk16Full) ?>', '<?= View::e($a['username'] . ' – FSK16') ?>')">
                                        <img src="<?= View::e($fsk16Url) ?>" alt="FSK16" class="rounded border" style="width:52px;height:52px;object-fit:cover;">
                                        <span class="badge <?= $a['has_fsk16'] ? 'bg-success' : 'bg-danger' ?> position-absolute bottom-0 start-0" style="font-size:0.58rem;padding:1px 3px;">16</span>
                                    </div>
                                    <div class="position-relative" title="FSK18 Profilbild (Klicken zum Vergrößern)" style="cursor:pointer;" onclick="App.openImageLightbox('<?= View::e($fsk18Full) ?>', '<?= View::e($a['username'] . ' – FSK18') ?>')">
                                        <img src="<?= View::e($fsk18Url) ?>" alt="FSK18" class="rounded border" style="width:52px;height:52px;object-fit:cover;">
                                        <span class="badge <?= $a['has_fsk18'] ? 'bg-danger' : 'bg-secondary' ?> position-absolute bottom-0 start-0" style="font-size:0.58rem;padding:1px 3px;">18</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <a href="#/actor?id=<?= $aid ?>" class="fw-bold text-decoration-none fs-6"><?= View::e((string)$a['username']) ?></a>
                                    <?php if (!empty($a['is_new_5d'])): ?>
                                        <span class="badge bg-warning text-dark">NEU</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    <?= View::e((string)$a['gender_label']) ?>
                                    <?php if ((int)$a['age'] > 0): ?> &bull; <?= (int)$a['age'] ?> J.<?php endif; ?>
                                    <?php if ((int)$a['body_height'] > 0): ?> &bull; <?= (int)$a['body_height'] ?> cm<?php endif; ?>
                                    <?php if ($a['cup_size'] !== ''): ?> &bull; Cup <?= View::e((string)$a['cup_size']) ?><?php endif; ?>
                                </div>
                                <?php if ($a['admin_infos'] !== ''): ?>
                                    <div class="small text-danger mt-1" title="<?= View::e((string)$a['admin_infos']) ?>">
                                        <i class="bi bi-Pin-angle-fill me-1"></i><?= View::e(mb_strimwidth((string)$a['admin_infos'], 0, 55, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$a['merchant_id'] > 0): ?>
                                    <div class="fw-semibold">
                                        <a href="#/merchant?id=<?= (int)$a['merchant_id'] ?>" class="text-decoration-none">
                                            <?= View::e($a['merchant_username'] !== '' ? (string)$a['merchant_username'] : ('MID #' . (int)$a['merchant_id'])) ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted">
                                        Partner-ID: <strong><?= (int)$a['partner_id'] ?></strong> &bull;
                                        <a href="#/actors?merchant_id=<?= (int)$a['merchant_id'] ?>" class="text-decoration-none" title="Alle Profile dieses Händlers filtern">MID #<?= (int)$a['merchant_id'] ?></a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Kein Merchant zugeordnet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="mb-1"><?= $catBadge ?> <small class="text-muted ms-1">(Prov.: <?= (int)$a['provision_content'] ?>%)</small></div>
                                <div class="small text-muted"><?= View::e((string)$a['display_label']) ?></div>
                            </td>
                            <td>
                                <div class="small">
                                    <i class="bi bi-film text-primary me-1"></i><strong><?= (int)$a['movies_online'] ?></strong> / <?= (int)$a['movies_total'] ?> Filme
                                    <?php if ((int)$a['movies_checking'] > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1"><?= (int)$a['movies_checking'] ?> in Prüfung</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small mt-1">
                                    <i class="bi bi-images text-info me-1"></i><strong><?= (int)$a['albums_online'] ?></strong> / <?= (int)$a['albums_total'] ?> Alben
                                    <?php if ((int)$a['albums_checking'] > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1"><?= (int)$a['albums_checking'] ?> in Prüfung</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= $statusBadge ?></td>
                            <td class="small">
                                <div><?= View::e((string)$a['lastonline_fmt']) ?></div>
                                <?php if ($a['created_fmt'] !== '-'): ?>
                                    <div class="text-muted" style="font-size:0.72rem;">Erstellt: <?= View::e((string)$a['created_fmt']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="#/actor?id=<?= $aid ?>" class="btn btn-sm btn-primary fw-semibold">
                                    <i class="bi bi-pencil-square me-1"></i>Bearbeiten
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted">
                Seite <strong><?= $page ?></strong> von <strong><?= $totalPages ?></strong>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= View::e($buildPageUrl(1)) ?>">&laquo;</a>
                    </li>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= View::e($buildPageUrl(max(1, $page - 1))) ?>">Zurück</a>
                    </li>
                    <?php
                    $startP = max(1, $page - 2);
                    $endP   = min($totalPages, $page + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                    ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= View::e($buildPageUrl($p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= View::e($buildPageUrl(min($totalPages, $page + 1))) ?>">Weiter</a>
                    </li>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= View::e($buildPageUrl($totalPages)) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
