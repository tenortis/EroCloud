<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, int> $switcherCounts
 * @var int $upcomingCount
 * @var string $upcomingSizeFmt
 * @var array<string, int> $ruleCounts
 * @var array<int, array<string, mixed>> $previewRows
 * @var int $historyCount
 * @var string $totalSavedFmt
 * @var array<int, array<string, mixed>> $historyRows
 */
?>

<!-- Content-Online Schnellwechsel-Leiste -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="#/movies-online" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-film me-1"></i>Filme online
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_online'] ?? 0) ?></span>
        </a>
        <a href="#/movies-drafts" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-hourglass-split me-1"></i>Filme in Planung
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_drafts'] ?? 0) ?></span>
        </a>
        <a href="#/movies-blocked" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-slash-circle me-1"></i>Abgelehnt / Gesperrt
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_blocked'] ?? 0) ?></span>
        </a>
        <a href="#/photo-albums-online" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-images me-1"></i>Fotoalben online
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['photo_albums_online'] ?? 0) ?></span>
        </a>
        <a href="#/content-cleanup" class="btn btn-sm btn-primary fw-semibold">
            <i class="bi bi-trash3 me-1"></i>Content-Bereinigung
            <span class="badge bg-danger ms-1"><?= $upcomingCount ?></span>
        </a>
        <a href="#/storage-analytics" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-hdd-network me-1"></i>Speicher &amp; Traffic
        </a>
    </div>
</div>

<!-- 4 KPI-Kacheln -->
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Nächster Cronjob-Lauf</div>
                        <div class="fs-3 fw-bold text-danger mt-1"><?= $upcomingCount ?> Filme</div>
                        <div class="small text-muted">Freizugebend: <strong class="text-danger"><?= View::e($upcomingSizeFmt) ?></strong></div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-trash3-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Vorschau-Kandidaten</div>
                        <div class="fs-3 fw-bold text-primary mt-1"><?= (int)($ruleCounts['all'] ?? 0) ?> Filme</div>
                        <div class="small text-muted">
                            R1: <?= (int)($ruleCounts['rule_1'] ?? 0) ?> &bull;
                            R2: <?= (int)($ruleCounts['rule_2'] ?? 0) ?> &bull;
                            R3: <?= (int)($ruleCounts['rule_3'] ?? 0) ?> &bull;
                            R4/5: <?= (int)(($ruleCounts['rule_4'] ?? 0) + ($ruleCounts['rule_5'] ?? 0)) ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-funnel-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Physisch gelöscht (Historie)</div>
                        <div class="fs-3 fw-bold text-warning mt-1"><?= $historyCount ?> Filme</div>
                        <div class="small text-muted">Protokolliert in <code>movies_deleted</code></div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-archive-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Eingesparter Speicherplatz</div>
                        <div class="fs-3 fw-bold text-success mt-1"><?= View::e($totalSavedFmt) ?></div>
                        <div class="small text-muted">Gesamte Historie aller Bereinigungen</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-hdd-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3-Tab Hauptkarte -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3 pb-0 px-3">
        <ul class="nav nav-tabs card-header-tabs" id="ccMainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="tab-btn-cc-preview" data-bs-toggle="tab" data-bs-target="#tab-cc-preview" type="button" role="tab">
                    <i class="bi bi-eye me-1"></i>1. Lösch-Vorschau (Simulation)
                    <span class="badge bg-primary ms-1"><?= (int)($ruleCounts['all'] ?? 0) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-cc-history" data-bs-toggle="tab" data-bs-target="#tab-cc-history" type="button" role="tab">
                    <i class="bi bi-clock-history me-1"></i>2. Lösch-Historie &amp; Statistiken
                    <span class="badge bg-secondary ms-1"><?= $historyCount ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-cc-orphan" data-bs-toggle="tab" data-bs-target="#tab-cc-orphan" type="button" role="tab">
                    <i class="bi bi-search me-1"></i>3. Dateileichen-Suche (Cloud-Storage)
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-3 p-lg-4">
        <div class="tab-content" id="ccMainTabContent">

            <!-- ============================================================ -->
            <!-- TAB 1: LÖSCH-VORSCHAU                                        -->
            <!-- ============================================================ -->
            <div class="tab-pane fade show active" id="tab-cc-preview" role="tabpanel">
                <div class="alert alert-info small mb-3">
                    <div class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i>Information zur Bereinigungs-Simulation (5-Regel-System):</div>
                    Diese Ansicht zeigt dir alle Filme, die sich im Status „Gelöscht“ (Soft-Delete) oder in langzeit-inaktiven Entwürfen/Sperren befinden, und berechnet das geplante physische Löschdatum:<br>
                    <strong>1. Regel 1 (Nie gekauft):</strong> Sofortige physische Löschung beim nächsten Cronjob-Lauf.<br>
                    <strong>2. Regel 2 (Inaktiv &ndash; Kauf &amp; View &gt; 2 Jahre her):</strong> Physische Löschung 30 Tage nach Markierung als gelöscht.<br>
                    <strong>3. Regel 3 (Aktiv &ndash; Kauf/View &lt; 2 Jahre her):</strong> Kundenschutz-Frist von 365 Tagen ab Löschdatum.<br>
                    <strong>4. Regel 4 (Alt &amp; Abgelehnt &ndash; Inaktiv &gt; 180 Tage):</strong> Sofortige physische Löschung von abgelehnten/gesperrten Filmen ohne Bearbeitung seit 6 Monaten.<br>
                    <strong>5. Regel 5 (Inaktive Entwürfe &ndash; Inaktiv &gt; 180 Tage):</strong> Sofortige physische Löschung unbestätigter Creator-Entwürfe ohne Bearbeitung seit 6 Monaten.
                </div>

                <!-- Filter-Leiste -->
                <div class="bg-light border rounded-3 p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-1" id="cc-rule-pills">
                        <span class="small fw-semibold me-1">Regel filtern:</span>
                        <button type="button" class="btn btn-sm btn-primary cc-filter-pill" data-rule="all" onclick="App.ContentCleanup.filterPreview('all', this)">
                            Alle (<?= (int)($ruleCounts['all'] ?? 0) ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger cc-filter-pill" data-rule="immediate" onclick="App.ContentCleanup.filterPreview('immediate', this)">
                            Sofort fällig (<?= (int)($ruleCounts['immediate'] ?? 0) ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark cc-filter-pill" data-rule="rule_1" onclick="App.ContentCleanup.filterPreview('rule_1', this)">
                            Regel 1: Nie gekauft (<?= (int)($ruleCounts['rule_1'] ?? 0) ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info text-dark cc-filter-pill" data-rule="rule_2" onclick="App.ContentCleanup.filterPreview('rule_2', this)">
                            Regel 2: Inaktiv &gt; 2J (<?= (int)($ruleCounts['rule_2'] ?? 0) ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary cc-filter-pill" data-rule="rule_3" onclick="App.ContentCleanup.filterPreview('rule_3', this)">
                            Regel 3: Aktiv &lt; 2J (<?= (int)($ruleCounts['rule_3'] ?? 0) ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark cc-filter-pill" data-rule="rule_4" onclick="App.ContentCleanup.filterPreview('rule_4', this)">
                            Regel 4: Alt &amp; Abgelehnt (<?= (int)($ruleCounts['rule_4'] ?? 0) ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger cc-filter-pill" data-rule="rule_5" onclick="App.ContentCleanup.filterPreview('rule_5', this)">
                            Regel 5: Inaktive Entwürfe (<?= (int)($ruleCounts['rule_5'] ?? 0) ?>)
                        </button>
                    </div>

                    <div style="min-width:250px;">
                        <input type="text" class="form-control form-control-sm" id="cc-preview-search" placeholder="Film-ID, Titel oder Profil suchen..." oninput="App.ContentCleanup.applyPreviewFilter()">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle small mb-0" id="cc-table-preview">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80px;">Film-ID</th>
                                <th>Filmtitel</th>
                                <th style="width:150px;">Profilname</th>
                                <th style="width:130px;">Online seit</th>
                                <th style="width:130px;">Soft-Delete seit</th>
                                <th style="width:140px;">Letzter Kauf</th>
                                <th style="width:130px;">Letzter Zugriff</th>
                                <th style="width:185px;">Geplantes Löschdatum</th>
                                <th style="width:190px;">Bereinigungs-Regel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($previewRows)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Aktuell befinden sich keine Filme in der Lösch-Vorschau.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($previewRows as $r):
                                    $searchStr = strtolower($r['id'] . ' ' . $r['title'] . ' ' . $r['actor_name'] . ' ' . $r['rule_label']);
                                ?>
                                    <tr class="cc-preview-row"
                                        data-rule="<?= View::e((string)$r['rule_key']) ?>"
                                        data-immediate="<?= !empty($r['is_immediate']) ? '1' : '0' ?>"
                                        data-search="<?= View::e($searchStr) ?>">
                                        <td><strong>#<?= (int)$r['id'] ?></strong></td>
                                        <td>
                                            <?php if ((int)$r['online_id'] > 0): ?>
                                                <a href="#/movie-edit?id=<?= (int)$r['online_id'] ?>" class="fw-bold text-decoration-none">
                                                    <?= View::e((string)$r['title']) ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="#/movie-checking?id=<?= (int)$r['id'] ?>" class="fw-bold text-decoration-none">
                                                    <?= View::e((string)$r['title']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($r['folder_size_fmt'] !== '-'): ?>
                                                <span class="badge bg-light text-secondary border ms-1"><?= View::e((string)$r['folder_size_fmt']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$r['actor_id'] > 0): ?>
                                                <a href="#/actor?id=<?= (int)$r['actor_id'] ?>" class="text-decoration-none">
                                                    <?= View::e((string)$r['actor_name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= View::e((string)$r['online_fmt']) ?></td>
                                        <td><?= View::e((string)$r['deleted_fmt']) ?></td>
                                        <td>
                                            <?php if ((int)$r['purchases_count'] > 0): ?>
                                                <span class="badge bg-success me-1"><?= (int)$r['purchases_count'] ?>x</span>
                                                <small><?= View::e((string)$r['last_buy_fmt']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">0 Käufe</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= View::e((string)$r['last_view_fmt']) ?></td>
                                        <td>
                                            <?php if (!empty($r['is_immediate'])): ?>
                                                <span class="text-danger fw-bold"><i class="bi bi-lightning-fill me-1"></i><?= View::e((string)$r['planned_fmt']) ?></span>
                                            <?php else: ?>
                                                <span><?= View::e((string)$r['planned_fmt']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= View::e((string)$r['rule_badge_cls']) ?>"><?= View::e((string)$r['rule_label']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: LÖSCH-HISTORIE                                        -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-cc-history" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0">Protokoll aller physisch gelöschten Filme (<code>movies_deleted</code>)</h6>
                        <small class="text-muted">Insgesamt <?= $historyCount ?> Filme bereinigt &bull; Eingesparter Speicherplatz: <strong><?= View::e($totalSavedFmt) ?></strong></small>
                    </div>
                    <div style="min-width:260px;">
                        <input type="text" class="form-control form-control-sm" id="cc-history-search" placeholder="In Historie nach ID oder Titel suchen..." oninput="App.ContentCleanup.filterHistory(this.value)">
                    </div>
                </div>

                <div class="table-responsive" style="max-height:620px; overflow-y:auto;">
                    <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:90px;">Film-ID</th>
                                <th>Filmtitel</th>
                                <th style="width:170px;">Physisch gelöscht am</th>
                                <th style="width:140px;">Dateigröße</th>
                                <th style="width:130px;">Käufe (Historie)</th>
                                <th style="width:130px;">Aufrufe (Historie)</th>
                            </tr>
                        </thead>
                        <tbody id="cc-tbody-history">
                            <?php if (empty($historyRows)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Noch keine Einträge in der Lösch-Historie vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyRows as $hr):
                                    $hSearch = strtolower($hr['movie_id'] . ' ' . $hr['title'] . ' ' . $hr['deleted_fmt']);
                                ?>
                                    <tr class="cc-history-row" data-search="<?= View::e($hSearch) ?>">
                                        <td><strong>#<?= (int)$hr['movie_id'] ?></strong></td>
                                        <td><?= View::e((string)$hr['title']) ?></td>
                                        <td><?= View::e((string)$hr['deleted_fmt']) ?></td>
                                        <td><span class="fw-semibold text-success"><?= View::e((string)$hr['size_fmt']) ?></span></td>
                                        <td><?= (int)$hr['purchases'] ?>x</td>
                                        <td><?= (int)$hr['views'] ?>x</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: DATEILEICHEN-SUCHE                                    -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-cc-orphan" role="tabpanel">
                <div id="cc-orphan-init-box" class="bg-light border rounded-3 p-4 text-center">
                    <div class="mb-2">
                        <i class="bi bi-folder-symlink fs-1 text-primary"></i>
                    </div>
                    <h5 class="fw-bold">Dateileichen-Suche im Cloud-Speicher</h5>
                    <p class="text-muted small mx-auto" style="max-width:640px;">
                        Hier kannst du prüfen, ob sich im Cloud-Speicher (<code>cloud_storage/</code> für Filme, Fotoalben und Werbebanner) Verzeichnisse oder Dateien befinden, die keinem aktiven Datensatz in der Datenbank mehr zugeordnet sind.
                    </p>
                    <button type="button" class="btn btn-primary fw-semibold px-4" id="btn-cc-start-orphan" onclick="App.ContentCleanup.runOrphanScan()">
                        <i class="bi bi-search me-1"></i>Dateileichen-Scan jetzt starten
                    </button>
                </div>

                <div id="cc-orphan-loading" class="border rounded-3 p-5 text-center bg-light" style="display:none;">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h6 class="fw-bold text-primary">Verzeichnis-Analyse läuft...</h6>
                    <p class="small text-muted mb-0">Das Cloud-Storage-Verzeichnis wird gescannt und mit der Datenbank abgeglichen. Bitte habe einen kurzen Moment Geduld.</p>
                </div>

                <div id="cc-orphan-result-box" style="display:none;">
                    <div id="cc-orphan-local-msg" class="alert alert-warning small mb-3" style="display:none;"></div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-4">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-muted text-uppercase fw-semibold">Gefundene Dateileichen</div>
                                <div class="fs-4 fw-bold text-danger mt-1" id="cc-orphan-stat-count">0 Objekte</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-muted text-uppercase fw-semibold">Speicherverbrauch</div>
                                <div class="fs-4 fw-bold text-warning mt-1" id="cc-orphan-stat-size">0 Bytes</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="border rounded p-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-muted text-uppercase fw-semibold">Analyse-Dauer</div>
                                    <div class="fs-4 fw-bold text-success mt-1" id="cc-orphan-stat-time">0 ms</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="App.ContentCleanup.runOrphanScan()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Erneut scannen
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold small">Gefundene verwaiste Verzeichnisse &amp; Dateien:</span>
                        <input type="text" class="form-control form-control-sm" style="max-width:280px;" placeholder="Pfad oder Typ filtern..." oninput="App.ContentCleanup.filterOrphans(this.value)">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pfad (relativ)</th>
                                    <th style="width:200px;">Typ</th>
                                    <th>Grund / Details</th>
                                    <th style="width:130px;">Größe</th>
                                </tr>
                            </thead>
                            <tbody id="cc-tbody-orphans"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
