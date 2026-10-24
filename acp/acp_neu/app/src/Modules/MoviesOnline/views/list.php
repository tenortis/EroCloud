<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<int, array<string, mixed>> $movies
 * @var array<string, mixed> $kpis
 * @var array<string, int> $switcherCounts
 * @var array<string, mixed> $filters
 * @var array<string, string> $languages
 * @var array<string, string> $qualities
 */

$curSearch  = (string)($filters['q'] ?? '');
$curStatus  = (string)($filters['status'] ?? 'all');
$curCat     = (string)($filters['cat'] ?? 'all');
$curQuality = (string)($filters['quality'] ?? 'all');
$curLang    = (string)($filters['lang'] ?? 'all');
$curBram    = (string)($filters['bram'] ?? 'all');
$curSort    = (string)($filters['sort'] ?? 'id_desc');
$curPage    = (int)($filters['page'] ?? 1);
$curPerPage = (int)($filters['per_page'] ?? 100);
$totalRows  = (int)($filters['total_rows'] ?? 0);
$totalPages = (int)($filters['total_page'] ?? 1);
?>
<div class="container-fluid py-3" data-page-width="full">

  <!-- ========================================================================
       SEITEN-HEADER
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div>
      <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-collection-play-fill ec-text-primary"></i>
        <span>Filme online</span>
      </h1>
      <p class="text-muted small mb-0">
        Gesamtübersicht und Verwaltung aller veröffentlichten Filme im EroCloud-Katalog.
      </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
      </button>
    </div>
  </div>

  <!-- ========================================================================
       KPI-KACHELN (4er Grid)
       ======================================================================== -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Filme Online Gesamt</div>
            <div class="h3 fw-bold mb-0" id="mo-total-header-cnt"><?= number_format((int)$kpis['total'], 0, ',', '.') ?></div>
            <small class="text-muted">
              Porno: <strong><?= number_format((int)$kpis['porn'], 0, ',', '.') ?></strong> &bull;
              Fetisch: <strong><?= number_format((int)$kpis['fetish'], 0, ',', '.') ?></strong>
            </small>
          </div>
          <div class="rounded-3 p-3" style="background: var(--kkc-accent-Soft); color: var(--kkc-accent);">
            <i class="bi bi-collection-play-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Status-Verteilung</div>
            <div class="h3 fw-bold mb-0 text-success"><?= number_format((int)$kpis['active'], 0, ',', '.') ?> <small class="fs-6 fw-normal text-muted">aktiv</small></div>
            <small class="text-muted">
              <span class="text-warning fw-semibold"><?= (int)$kpis['blocked'] ?> gesperrt</span> &bull;
              <span class="text-danger fw-semibold"><?= (int)$kpis['deleted'] ?> gelöscht</span>
            </small>
          </div>
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
            <i class="bi bi-check2-circle fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">BRAM &amp; Kategorien</div>
            <div class="h3 fw-bold mb-0 <?= (int)$kpis['bram_attention'] > 0 ? 'text-warning' : 'text-success' ?>">
              <?= number_format((int)$kpis['bram_green'], 0, ',', '.') ?> <small class="fs-6 fw-normal text-muted">grün</small>
            </div>
            <small class="text-muted">
              <?= (int)$kpis['bram_attention'] ?> Hinweis/Verstoß &bull; <?= (int)$kpis['porn'] ?> Porno / <?= (int)$kpis['fetish'] ?> Fetisch
            </small>
          </div>
          <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info">
            <i class="bi bi-shield-check fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Käufe &amp; Umsatz Gesamt</div>
            <div class="h3 fw-bold mb-0 ec-text-primary"><?= number_format((int)$kpis['total_buys'], 0, ',', '.') ?>x</div>
            <small class="text-muted">
              Umsatz: <strong class="text-success"><?= number_format((int)$kpis['total_revenue'], 0, ',', '.') ?> Coins</strong>
            </small>
          </div>
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 ec-text-primary">
            <i class="bi bi-coin fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       FILTER-, SORTIER- & SUCHLEISTE
       ======================================================================== -->
  <div class="card-kkc mb-3">
    <div class="card-kkc-body py-3">
      <form id="mo-filter-form" onsubmit="App.applyMoviesOnlineFilters(event)" class="row g-2 align-items-end">
        <!-- Suche -->
        <div class="col-xl-3 col-md-6">
          <label class="form-label small fw-bold mb-1 text-muted">Volltext- &amp; Schnellsuche</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
            <input type="search" id="mo-filter-q" value="<?= View::e($curSearch) ?>" class="form-control"
                   placeholder="ID, MID, File-ID, Titel, Darsteller..."
                   oninput="App.quickFilterMoviesOnlineTable(this.value)">
          </div>
        </div>

        <!-- Status -->
        <div class="col-xl-1 col-md-3 col-6">
          <label class="form-label small fw-bold mb-1 text-muted">Status</label>
          <select id="mo-filter-status" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()">
            <option value="all" <?= $curStatus === 'all' ? 'selected' : '' ?>>Alle</option>
            <option value="active" <?= $curStatus === 'active' ? 'selected' : '' ?>>Aktiv</option>
            <option value="blocked" <?= $curStatus === 'blocked' ? 'selected' : '' ?>>Gesperrt</option>
            <option value="deleted" <?= $curStatus === 'deleted' ? 'selected' : '' ?>>Gelöscht</option>
          </select>
        </div>

        <!-- Hauptkategorie -->
        <div class="col-xl-1 col-md-3 col-6">
          <label class="form-label small fw-bold mb-1 text-muted">Kategorie</label>
          <select id="mo-filter-cat" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()">
            <option value="all" <?= $curCat === 'all' ? 'selected' : '' ?>>Alle</option>
            <option value="porn" <?= $curCat === 'porn' ? 'selected' : '' ?>>Porno</option>
            <option value="fetish" <?= $curCat === 'fetish' ? 'selected' : '' ?>>Fetisch</option>
          </select>
        </div>

        <!-- Qualität -->
        <div class="col-xl-1 col-md-3 col-6">
          <label class="form-label small fw-bold mb-1 text-muted">Qualität</label>
          <select id="mo-filter-quality" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()">
            <option value="all" <?= $curQuality === 'all' ? 'selected' : '' ?>>Alle</option>
            <?php foreach ($qualities as $qK => $qL): ?>
            <option value="<?= View::e($qK) ?>" <?= $curQuality === $qK ? 'selected' : '' ?>><?= View::e($qL) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Sprache -->
        <div class="col-xl-1 col-md-3 col-6">
          <label class="form-label small fw-bold mb-1 text-muted">Sprache</label>
          <select id="mo-filter-lang" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()">
            <option value="all" <?= $curLang === 'all' ? 'selected' : '' ?>>Alle</option>
            <?php foreach ($languages as $lK => $lN): ?>
            <option value="<?= View::e($lK) ?>" <?= $curLang === $lK ? 'selected' : '' ?>><?= View::e($lN) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- BRAM -->
        <div class="col-xl-2 col-md-4 col-6">
          <label class="form-label small fw-bold mb-1 text-muted">BRAM-Status</label>
          <select id="mo-filter-bram" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()">
            <option value="all" <?= $curBram === 'all' ? 'selected' : '' ?>>Alle BRAM-Stati</option>
            <option value="green" <?= $curBram === 'green' ? 'selected' : '' ?>>BRAM Grün (OK)</option>
            <option value="attention" <?= $curBram === 'attention' ? 'selected' : '' ?>>Warnung / Verstoß</option>
            <option value="unchecked" <?= $curBram === 'unchecked' ? 'selected' : '' ?>>Noch ungeprüft</option>
          </select>
        </div>

        <!-- Sortierung -->
        <div class="col-xl-2 col-md-4 col-6">
          <label class="form-label small fw-bold mb-1 text-muted">Sortierung</label>
          <select id="mo-filter-sort" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()">
            <option value="id_desc" <?= $curSort === 'id_desc' ? 'selected' : '' ?>>Neueste ID zuerst</option>
            <option value="id_asc" <?= $curSort === 'id_asc' ? 'selected' : '' ?>>Älteste ID zuerst</option>
            <option value="online_desc" <?= $curSort === 'online_desc' ? 'selected' : '' ?>>Online-Datum (absteigend)</option>
            <option value="checked_desc" <?= $curSort === 'checked_desc' ? 'selected' : '' ?>>Zuletzt geprüft zuerst</option>
            <option value="risk_desc" <?= $curSort === 'risk_desc' ? 'selected' : '' ?>>Höchster BRAM-Risiko-Score</option>
            <option value="title_asc" <?= $curSort === 'title_asc' ? 'selected' : '' ?>>Filmtitel (A–Z)</option>
          </select>
        </div>

        <!-- Pro Seite & Buttons -->
        <div class="col-xl-1 col-md-4 col-12 d-flex gap-1">
          <select id="mo-filter-per-page" class="form-select form-select-sm" onchange="App.applyMoviesOnlineFilters()" title="Einträge pro Seite">
            <?php foreach ([50, 100, 250, 500] as $pp): ?>
            <option value="<?= $pp ?>" <?= $curPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-kkc px-2" title="Filter anwenden">
            <i class="bi bi-funnel-fill"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-kkc px-2" onclick="App.resetMoviesOnlineFilters()" title="Filter zurücksetzen">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================================
       TABELLEN-ANSICHT ALLER VERÖFFENTLICHTEN FILME
       ======================================================================== -->
  <div class="card-kkc">
    <div class="card-kkc-header d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="card-title">
        <i class="bi bi-table ec-text-primary"></i>
        <span>Veröffentlichte Filme</span>
        <span class="badge-kkc badge-kkc-neutral ms-1">
          Zeige <?= count($movies) ?> von <?= number_format($totalRows, 0, ',', '.') ?> Treffern (Seite <?= $curPage ?> / <?= $totalPages ?>)
        </span>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-kkc" <?= $curPage <= 1 ? 'disabled' : '' ?>
                onclick="App.goToMoviesOnlinePage(<?= $curPage - 1 ?>)">
          <i class="bi bi-chevron-left"></i> Zurück
        </button>
        <span class="btn btn-outline-kkc disabled fw-bold">Seite <?= $curPage ?> / <?= $totalPages ?></span>
        <button type="button" class="btn btn-outline-kkc" <?= $curPage >= $totalPages ? 'disabled' : '' ?>
                onclick="App.goToMoviesOnlinePage(<?= $curPage + 1 ?>)">
          Weiter <i class="bi bi-chevron-right"></i>
        </button>
      </div>
      <?php endif; ?>
    </div>

    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0" id="mo-movies-table">
        <thead>
          <tr>
            <th style="width: 85px;">ID / MID</th>
            <th style="width: 90px;">Status</th>
            <th style="width: 155px;">FSK16 / FSK18</th>
            <th>Filmtitel &amp; Details</th>
            <th style="width: 165px;">Darsteller</th>
            <th style="width: 110px;">Qualität / Kat.</th>
            <th style="width: 125px;">Käufe / Umsatz</th>
            <th style="width: 150px;">Geprüft / Online</th>
            <th style="width: 120px;">BRAM Score</th>
            <th class="text-end" style="width: 115px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movies)): ?>
          <tr>
            <td colspan="10" class="text-center py-5 text-muted">
              <i class="bi bi-film fs-2 d-block mb-2 opacity-50"></i>
              Keine veröffentlichten Filme für die gewählten Filterkriterien gefunden.
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($movies as $m):
                $onlineId = (int)$m['id'];
                $fileId   = (string)$m['file_id'];
                $mid      = (int)$m['merchant_id'];
                $aid      = (int)$m['actor_id'];
                $st       = (string)$m['status'];

                $stBadge = 'badge-kkc-success';
                $stLabel = 'Aktiv';
                $stIcon  = 'bi-check-circle-fill';
                if ($st === 'blocked') {
                    $stBadge = 'badge-kkc-warning';
                    $stLabel = 'Gesperrt';
                    $stIcon  = 'bi-pause-circle-fill';
                } elseif ($st === 'deleted') {
                    $stBadge = 'badge-kkc-danger';
                    $stLabel = 'Gelöscht';
                    $stIcon  = 'bi-trash3-fill';
                }

                $bramComp  = (string)$m['bram_compliance'];
                $bramScore = (int)$m['bram_score'];
                $bramSt    = (int)$m['bram_status'];
                $bramCls   = 'badge-kkc-neutral';
                $bramText  = 'Offen';
                if ($bramSt === 2) {
                    if (in_array($bramComp, ['ok', 'green'], true)) {
                        $bramCls  = 'badge-kkc-success';
                        $bramText = 'Grün (' . $bramScore . ')';
                    } elseif (in_array($bramComp, ['warning', 'yellow'], true)) {
                        $bramCls  = 'badge-kkc-warning';
                        $bramText = 'Hinweis (' . $bramScore . ')';
                    } else {
                        $bramCls  = 'badge-kkc-danger';
                        $bramText = 'Verstoß (' . $bramScore . ')';
                    }
                }

                $searchBlob = strtolower(
                    $onlineId . ' ' . $m['movie_db_id'] . ' ' . $mid . ' ' . $fileId . ' '
                    . $m['title'] . ' ' . $m['actor_name'] . ' ' . $m['description']
                );
            ?>
            <tr class="mo-movie-row" data-search="<?= View::e($searchBlob) ?>">
              <!-- ID & MID -->
              <td>
                <a href="#/movie-edit?id=<?= $onlineId ?>" class="fw-bold font-monospace ec-text-primary text-decoration-none d-block">
                  #<?= $onlineId ?>
                </a>
                <a href="#/merchant?id=<?= $mid ?>" class="small text-muted text-decoration-none font-monospace" title="Merchant öffnen">
                  MID #<?= $mid ?>
                </a>
              </td>

              <!-- Status -->
              <td>
                <span class="badge-kkc <?= $stBadge ?>">
                  <i class="bi <?= $stIcon ?> me-1"></i><?= $stLabel ?>
                </span>
              </td>

              <!-- FSK16 & FSK18 Vorschaubild -->
              <td>
                <div class="d-flex align-items-center gap-1">
                  <div class="position-relative rounded overflow-hidden border bg-dark" style="width: 68px; height: 42px; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=16', 'FSK16 Poster – Film #<?= $onlineId ?>')"
                       title="FSK16 Poster (#<?= (int)$m['fsk16'] ?>)">
                    <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=16"
                         alt="FSK16" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                    <span class="position-absolute bottom-0 start-0 px-1 bg-info text-dark fw-bold" style="font-size: 0.55rem;">16</span>
                  </div>
                  <div class="position-relative rounded overflow-hidden border bg-dark" style="width: 68px; height: 42px; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=18', 'FSK18 Poster – Film #<?= $onlineId ?>')"
                       title="FSK18 Poster (#<?= (int)$m['fsk18'] ?>)">
                    <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=18"
                         alt="FSK18" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                    <span class="position-absolute bottom-0 start-0 px-1 bg-danger text-white fw-bold" style="font-size: 0.55rem;">18</span>
                  </div>
                </div>
              </td>

              <!-- Filmtitel & Details -->
              <td>
                <a href="#/movie-edit?id=<?= $onlineId ?>" class="fw-bold text-body text-decoration-none d-block mb-0.5">
                  <?= View::e((string)$m['title'] ?: '(Ohne Titel)') ?>
                </a>
                <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                  <span class="font-monospace"><i class="bi bi-clock me-1"></i><?= View::e((string)$m['playtime_str']) ?></span>
                  <span>&bull;</span>
                  <span class="fw-semibold text-body"><?= (int)$m['total_coins'] ?> Coins</span>
                  <span>(<?= View::e((string)$m['amount_second']) ?>/s)</span>
                  <span>&bull;</span>
                  <span><i class="bi bi-translate me-1"></i><?= View::e((string)$m['language_label']) ?></span>
                </div>
              </td>

              <!-- Darsteller -->
              <td>
                <?php if ($aid > 0 && $m['actor_name'] !== ''): ?>
                  <a href="#/actor?id=<?= $aid ?>" class="fw-semibold ec-text-primary text-decoration-none d-block text-truncate" style="max-width: 155px;">
                    <i class="bi bi-person-badge me-1"></i><?= View::e((string)$m['actor_name']) ?>
                  </a>
                  <small class="text-muted font-monospace">Profil #<?= $aid ?></small>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>

              <!-- Qualität & Hauptkategorie -->
              <td>
                <div class="d-flex flex-column gap-1 align-items-start">
                  <span class="badge-kkc badge-kkc-neutral font-monospace">
                    <?= View::e((string)$m['quality_label']) ?>
                  </span>
                  <?php if ($m['category_master'] === 'fetish'): ?>
                    <span class="badge bg-dark text-white" style="font-size: 0.68rem;">Fetisch</span>
                  <?php else: ?>
                    <span class="badge bg-primary bg-opacity-10 ec-text-primary" style="font-size: 0.68rem;">Porno</span>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Käufe & Umsatz -->
              <td>
                <?php if ((int)$m['buys_count'] > 0): ?>
                  <div class="fw-bold ec-text-primary"><?= (int)$m['buys_count'] ?>x gekauft</div>
                  <small class="text-success fw-semibold"><?= number_format((int)$m['buys_revenue'], 0, ',', '.') ?> Coins</small>
                <?php else: ?>
                  <span class="text-muted small">0 Käufe</span>
                <?php endif; ?>
              </td>

              <!-- Geprüft am / Online seit -->
              <td>
                <div class="small">
                  <div title="Online seit"><i class="bi bi-globe2 text-success me-1"></i><?= View::e((string)$m['online_at']) ?></div>
                  <div class="text-muted" style="font-size: 0.72rem;" title="Geprüft am">
                    <i class="bi bi-shield-check me-1"></i><?= View::e((string)$m['movie_checked']) ?>
                  </div>
                </div>
              </td>

              <!-- BRAM Score -->
              <td>
                <span class="badge-kkc <?= $bramCls ?>" title="<?= View::e((string)$m['bram_summary']) ?>">
                  <?= View::e($bramText) ?>
                </span>
                <?php if ((int)$m['subtitle_status'] === 2): ?>
                  <div class="small text-muted mt-0.5" style="font-size: 0.68rem;">
                    <i class="bi bi-Soundwave text-info"></i> Audio/VTT OK
                  </div>
                <?php endif; ?>
              </td>

              <!-- Aktion -->
              <td class="text-end">
                <a href="#/movie-edit?id=<?= $onlineId ?>" class="btn btn-sm btn-kkc">
                  <i class="bi bi-pencil-square me-1"></i> Bearbeiten
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginierungs-Footer -->
    <?php if ($totalPages > 1): ?>
    <div class="card-kkc-body border-top py-2 d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
      <small class="text-muted">
        Seite <strong><?= $curPage ?></strong> von <strong><?= $totalPages ?></strong> (<?= number_format($totalRows, 0, ',', '.') ?> Filme gesamt)
      </small>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-kkc" <?= $curPage <= 1 ? 'disabled' : '' ?>
                onclick="App.goToMoviesOnlinePage(1)">
          <i class="bi bi-chevron-double-left"></i>
        </button>
        <button type="button" class="btn btn-outline-kkc" <?= $curPage <= 1 ? 'disabled' : '' ?>
                onclick="App.goToMoviesOnlinePage(<?= $curPage - 1 ?>)">
          <i class="bi bi-chevron-left"></i> Zurück
        </button>
        <button type="button" class="btn btn-outline-kkc" <?= $curPage >= $totalPages ? 'disabled' : '' ?>
                onclick="App.goToMoviesOnlinePage(<?= $curPage + 1 ?>)">
          Weiter <i class="bi bi-chevron-right"></i>
        </button>
        <button type="button" class="btn btn-outline-kkc" <?= $curPage >= $totalPages ? 'disabled' : '' ?>
                onclick="App.goToMoviesOnlinePage(<?= $totalPages ?>)">
          <i class="bi bi-chevron-double-right"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Bild-Lightbox Modal -->
<div class="modal fade" id="mc-image-preview-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold" id="mc-image-preview-title">Vorschaubild</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-2 text-center bg-black">
        <img src="" id="mc-image-preview-img" alt="Vorschau" class="img-fluid rounded" style="max-height: 78vh;">
      </div>
    </div>
  </div>
</div>
