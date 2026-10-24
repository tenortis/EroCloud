<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<int, array<string, mixed>> $albums
 * @var array<string, mixed> $kpis
 * @var array<string, int> $switcherCounts
 * @var array<string, mixed> $filters
 */

$curSearch  = (string)($filters['q'] ?? '');
$curStatus  = (string)($filters['status'] ?? 'all');
$curCat     = (string)($filters['cat'] ?? 'all');
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
        <i class="bi bi-journal-album ec-text-primary"></i>
        <span>Fotoalben online</span>
      </h1>
      <p class="text-muted small mb-0">
        Gesamtübersicht und Verwaltung aller veröffentlichten Fotoalben im EroCloud-Katalog.
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
            <div class="text-muted small fw-semibold mb-1">Fotoalben Online Gesamt</div>
            <div class="h3 fw-bold mb-0"><?= number_format((int)$kpis['total'], 0, ',', '.') ?></div>
            <small class="text-muted">
              Mit <strong><?= number_format((int)$kpis['total_photos'], 0, ',', '.') ?></strong> Einzelfotos im Katalog
            </small>
          </div>
          <div class="rounded-3 p-3" style="background: var(--kkc-accent-Soft); color: var(--kkc-accent);">
            <i class="bi bi-journal-album fs-3"></i>
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
          <div class="rounded-3 p-3" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
            <i class="bi bi-check2-circle fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Fotos &amp; Hauptkategorien</div>
            <div class="h3 fw-bold mb-0"><?= number_format((int)$kpis['total_photos'], 0, ',', '.') ?> <small class="fs-6 fw-normal text-muted">Fotos</small></div>
            <small class="text-muted">
              Porno: <strong><?= number_format((int)$kpis['porn'], 0, ',', '.') ?></strong> &bull;
              Fetisch: <strong><?= number_format((int)$kpis['fetish'], 0, ',', '.') ?></strong>
            </small>
          </div>
          <div class="rounded-3 p-3" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6;">
            <i class="bi bi-images fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Aufrufe &amp; Käufe Gesamt</div>
            <div class="h3 fw-bold mb-0"><?= number_format((int)$kpis['total_purchases'], 0, ',', '.') ?> <small class="fs-6 fw-normal text-muted">Käufe</small></div>
            <small class="text-muted">
              Seitenaufrufe (Views): <strong><?= number_format((int)$kpis['total_views'], 0, ',', '.') ?></strong>
            </small>
          </div>
          <div class="rounded-3 p-3" style="background: rgba(245, 158, 11, 0.14); color: #f59e0b;">
            <i class="bi bi-cart-check-fill fs-3"></i>
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
      <form id="pao-filter-form" onsubmit="event.preventDefault(); App.applyPhotoAlbumsOnlineFilters();" class="row g-2 align-items-end">
        <div class="col-xl-3 col-md-6">
          <label class="form-label small text-muted mb-1">Server-Suche (Titel, Album-Hash, Darsteller, ID, Merchant-ID)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
            <input type="text" id="pao-filter-q" class="form-control" value="<?= View::e($curSearch) ?>" placeholder="Suchbegriff oder ID eingeben...">
            <?php if ($curSearch !== ''): ?>
              <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('pao-filter-q').value=''; App.applyPhotoAlbumsOnlineFilters();" title="Suche zurücksetzen">
                <i class="bi bi-x-lg"></i>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-xl-2 col-md-3 col-6">
          <label class="form-label small text-muted mb-1">Status</label>
          <select id="pao-filter-status" class="form-select form-select-sm" onchange="App.applyPhotoAlbumsOnlineFilters()">
            <option value="all" <?= $curStatus === 'all' ? 'selected' : '' ?>>Alle Status (<?= (int)$kpis['total'] ?>)</option>
            <option value="active" <?= $curStatus === 'active' ? 'selected' : '' ?>>Aktiv (<?= (int)$kpis['active'] ?>)</option>
            <option value="blocked" <?= $curStatus === 'blocked' ? 'selected' : '' ?>>Gesperrt (<?= (int)$kpis['blocked'] ?>)</option>
            <option value="deleted" <?= $curStatus === 'deleted' ? 'selected' : '' ?>>Gelöscht (<?= (int)$kpis['deleted'] ?>)</option>
          </select>
        </div>

        <div class="col-xl-1 col-md-3 col-6">
          <label class="form-label small text-muted mb-1">Kategorie</label>
          <select id="pao-filter-cat" class="form-select form-select-sm" onchange="App.applyPhotoAlbumsOnlineFilters()">
            <option value="all" <?= $curCat === 'all' ? 'selected' : '' ?>>Alle</option>
            <option value="porn" <?= $curCat === 'porn' ? 'selected' : '' ?>>Porno (<?= (int)$kpis['porn'] ?>)</option>
            <option value="fetish" <?= $curCat === 'fetish' ? 'selected' : '' ?>>Fetisch (<?= (int)$kpis['fetish'] ?>)</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-3 col-6">
          <label class="form-label small text-muted mb-1">BRAM-Status</label>
          <select id="pao-filter-bram" class="form-select form-select-sm" onchange="App.applyPhotoAlbumsOnlineFilters()">
            <option value="all" <?= $curBram === 'all' ? 'selected' : '' ?>>Alle BRAM-Status</option>
            <option value="green" <?= $curBram === 'green' ? 'selected' : '' ?>>Unbedenklich (Grün)</option>
            <option value="attention" <?= $curBram === 'attention' ? 'selected' : '' ?>>Hinweis / Verstoß</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-3 col-6">
          <label class="form-label small text-muted mb-1">Sortierung</label>
          <select id="pao-filter-sort" class="form-select form-select-sm" onchange="App.applyPhotoAlbumsOnlineFilters()">
            <option value="id_desc" <?= $curSort === 'id_desc' ? 'selected' : '' ?>>Online-ID (Neueste zuerst)</option>
            <option value="id_asc" <?= $curSort === 'id_asc' ? 'selected' : '' ?>>Online-ID (Älteste zuerst)</option>
            <option value="online_desc" <?= $curSort === 'online_desc' ? 'selected' : '' ?>>Online-Datum (absteigend)</option>
            <option value="purchases_desc" <?= $curSort === 'purchases_desc' ? 'selected' : '' ?>>Meiste Käufe</option>
            <option value="views_desc" <?= $curSort === 'views_desc' ? 'selected' : '' ?>>Meiste Aufrufe</option>
            <option value="photos_desc" <?= $curSort === 'photos_desc' ? 'selected' : '' ?>>Meiste Fotos</option>
            <option value="title_asc" <?= $curSort === 'title_asc' ? 'selected' : '' ?>>Titel (A–Z)</option>
          </select>
        </div>

        <div class="col-xl-1 col-md-4 col-3">
          <label class="form-label small text-muted mb-1">Pro Seite</label>
          <select id="pao-filter-per-page" class="form-select form-select-sm" onchange="App.applyPhotoAlbumsOnlineFilters()">
            <?php foreach ([50, 100, 250, 500] as $pp): ?>
              <option value="<?= $pp ?>" <?= $curPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-xl-1 col-md-4 col-3 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-kkc flex-grow-1" title="Filter anwenden">
            <i class="bi bi-funnel-fill"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="App.resetPhotoAlbumsOnlineFilters()" title="Alle Filter zurücksetzen">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
        </div>
      </form>

      <!-- Schnell-Filterung innerhalb der aktuellen Seite + Paginierungs-Info -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
        <div class="d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="max-width: 280px;">
            <span class="input-group-text bg-transparent"><i class="bi bi-lightning-charge text-warning"></i></span>
            <input type="text" id="pao-quick-filter" class="form-control" placeholder="Schnellfilter auf dieser Seite..." oninput="App.quickFilterPhotoAlbumsOnlineTable(this.value)">
          </div>
          <span class="text-muted small">
            Zeige <strong><?= count($albums) ?></strong> von <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> Treffern
            (Seite <?= $curPage ?> / <?= $totalPages ?>)
          </span>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-kkc" <?= $curPage <= 1 ? 'disabled' : '' ?> onclick="App.goToPhotoAlbumsOnlinePage(1)">
              <i class="bi bi-chevron-bar-left"></i>
            </button>
            <button type="button" class="btn btn-outline-kkc" <?= $curPage <= 1 ? 'disabled' : '' ?> onclick="App.goToPhotoAlbumsOnlinePage(<?= $curPage - 1 ?>)">
              <i class="bi bi-chevron-left"></i> Zurück
            </button>
            <span class="btn btn-outline-kkc disabled fw-bold"><?= $curPage ?> / <?= $totalPages ?></span>
            <button type="button" class="btn btn-outline-kkc" <?= $curPage >= $totalPages ? 'disabled' : '' ?> onclick="App.goToPhotoAlbumsOnlinePage(<?= $curPage + 1 ?>)">
              Weiter <i class="bi bi-chevron-right"></i>
            </button>
            <button type="button" class="btn btn-outline-kkc" <?= $curPage >= $totalPages ? 'disabled' : '' ?> onclick="App.goToPhotoAlbumsOnlinePage(<?= $totalPages ?>)">
              <i class="bi bi-chevron-bar-right"></i>
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       HAUPTTABELLE: FOTOALBEN ONLINE
       ======================================================================== -->
  <div class="card-kkc">
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0" id="pao-albums-table">
        <thead>
          <tr>
            <th style="width: 115px;">ID / Album-ID</th>
            <th style="width: 120px;">FSK16 / FSK18</th>
            <th>Titel &amp; Album-Hash</th>
            <th style="width: 185px;">Darsteller / Merchant</th>
            <th style="width: 120px;">Fotos &amp; Kat.</th>
            <th style="width: 115px;">Preis</th>
            <th style="width: 120px;">Views / Käufe</th>
            <th style="width: 130px;">BRAM</th>
            <th style="width: 135px;">Online ab</th>
            <th style="width: 105px;">Status</th>
            <th class="text-end" style="width: 125px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($albums)): ?>
            <tr>
              <td colspan="11" class="text-center py-5 text-muted">
                <i class="bi bi-journal-album fs-1 d-block mb-2 opacity-50"></i>
                Keine Online-Fotoalben für die gewählten Filterkriterien gefunden.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($albums as $a):
              $statusBadge = '<span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Aktiv</span>';
              if ($a['status'] === 'blocked') {
                  $statusBadge = '<span class="badge-kkc badge-kkc-warning"><i class="bi bi-pause-circle-fill me-1"></i>Gesperrt</span>';
              } elseif ($a['status'] === 'deleted') {
                  $statusBadge = '<span class="badge-kkc badge-kkc-danger"><i class="bi bi-trash-fill me-1"></i>Gelöscht</span>';
              }

              $bramBadge = '<span class="badge-kkc badge-kkc-success" title="' . View::e((string)$a['bram_summary']) . '"><i class="bi bi-shield-check me-1"></i>Grün (' . (int)$a['bram_score'] . ')</span>';
              if (in_array((string)$a['bram_compliance'], ['violation', 'red'], true)) {
                  $bramBadge = '<span class="badge-kkc badge-kkc-danger" title="' . View::e((string)$a['bram_summary']) . '"><i class="bi bi-shield-exclamation me-1"></i>Verstoß (' . (int)$a['bram_score'] . ')</span>';
              } elseif (in_array((string)$a['bram_compliance'], ['warning', 'yellow'], true)) {
                  $bramBadge = '<span class="badge-kkc badge-kkc-warning" title="' . View::e((string)$a['bram_summary']) . '"><i class="bi bi-exclamation-triangle me-1"></i>Hinweis (' . (int)$a['bram_score'] . ')</span>';
              }

              $posterAlbumId = (int)$a['album_db_id'] > 0 ? (int)$a['album_db_id'] : (int)$a['id'];
              $thumbFsk16    = 'media.php?type=album_poster&album_id=' . $posterAlbumId . '&fsk=16&w=140';
              $thumbFsk18    = 'media.php?type=album_poster&album_id=' . $posterAlbumId . '&fsk=18&w=140';
              $fullFsk16     = 'media.php?type=album_poster&album_id=' . $posterAlbumId . '&fsk=16&w=900';
              $fullFsk18     = 'media.php?type=album_poster&album_id=' . $posterAlbumId . '&fsk=18&w=900';
              $searchBlob    = mb_strtolower($a['id'] . ' ' . $a['album_db_id'] . ' ' . $a['album_id'] . ' ' . $a['title'] . ' ' . $a['actor_name'] . ' ' . $a['merchant_id'], 'UTF-8');
            ?>
              <tr class="pao-album-row" data-search="<?= View::e($searchBlob) ?>">
                <td>
                  <div class="fw-bold">#<?= (int)$a['id'] ?></div>
                  <?php if ((int)$a['album_db_id'] > 0): ?>
                    <small class="text-muted">Album #<?= (int)$a['album_db_id'] ?></small>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="d-flex align-items-center gap-1">
                    <div class="position-relative rounded overflow-hidden border" style="width: 48px; height: 64px; background: #0f172a; cursor: pointer;" onclick="App.openLightbox('<?= View::e($fullFsk16) ?>', 'FSK16 Cover - #<?= (int)$a['id'] ?>')" title="FSK16 Cover vergrößern">
                      <img src="<?= View::e($thumbFsk16) ?>" alt="FSK16" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none';">
                      <span class="position-absolute bottom-0 start-0 px-1 text-white" style="font-size: 9px; background: rgba(16,185,129,0.85);">16</span>
                    </div>
                    <div class="position-relative rounded overflow-hidden border" style="width: 48px; height: 64px; background: #0f172a; cursor: pointer;" onclick="App.openLightbox('<?= View::e($fullFsk18) ?>', 'FSK18 Cover - #<?= (int)$a['id'] ?>')" title="FSK18 Cover vergrößern">
                      <img src="<?= View::e($thumbFsk18) ?>" alt="FSK18" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none';">
                      <span class="position-absolute bottom-0 start-0 px-1 text-white" style="font-size: 9px; background: rgba(239,68,68,0.85);">18</span>
                    </div>
                  </div>
                </td>

                <td>
                  <a href="#/photo-album-edit?id=<?= (int)$a['id'] ?>" class="fw-bold text-decoration-none d-block mb-1" style="color: var(--kkc-text);">
                    <?= View::e((string)$a['title']) ?>
                  </a>
                  <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                    <code class="small"><?= View::e((string)$a['album_id']) ?></code>
                  </div>
                </td>

                <td>
                  <div class="fw-semibold">
                    <i class="bi bi-person-badge me-1 text-primary"></i><?= View::e($a['actor_name'] !== '' ? (string)$a['actor_name'] : 'Kein Profil') ?>
                  </div>
                  <small class="text-muted">Merchant #<?= (int)$a['merchant_id'] ?> &bull; Actor #<?= (int)$a['actor_id'] ?></small>
                </td>

                <td>
                  <div class="fw-bold"><i class="bi bi-images me-1 text-info"></i><?= (int)$a['photos_count'] ?> Fotos</div>
                  <small class="text-muted"><?= $a['category_master'] === 'fetish' ? 'Fetisch' : 'Porno' ?></small>
                </td>

                <td>
                  <?php if ((int)$a['price_coins'] > 0): ?>
                    <div class="fw-bold"><?= (int)$a['price_coins'] ?> Coins</div>
                    <small class="text-muted"><?= View::e((string)$a['price_eur']) ?> EUR</small>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success">Gratis (0 Coins)</span>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="fw-bold"><?= number_format((int)$a['purchases'], 0, ',', '.') ?> <small class="fw-normal text-muted">Käufe</small></div>
                  <small class="text-muted"><?= number_format((int)$a['views'], 0, ',', '.') ?> Views</small>
                </td>

                <td>
                  <?= $bramBadge ?>
                </td>

                <td>
                  <div class="small fw-semibold">
                    <?= $a['online_at'] !== '' && $a['online_at'] !== '0000-00-00 00:00:00' ? date('d.m.Y H:i', strtotime((string)$a['online_at'])) : '-' ?>
                  </div>
                  <?php if ($a['album_checked'] !== '' && $a['album_checked'] !== '0000-00-00 00:00:00'): ?>
                    <small class="text-muted" style="font-size: 11px;">Geprüft: <?= date('d.m.Y', strtotime((string)$a['album_checked'])) ?></small>
                  <?php endif; ?>
                </td>

                <td><?= $statusBadge ?></td>

                <td class="text-end">
                  <a href="#/photo-album-edit?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-kkc">
                    <i class="bi bi-pencil-square me-1"></i> Bearbeiten
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
