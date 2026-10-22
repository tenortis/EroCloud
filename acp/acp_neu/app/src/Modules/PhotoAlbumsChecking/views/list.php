<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;
use App\Modules\MoviesOnline\MoviesOnlineController;

/** @var array<int, array<string, mixed>> $albums */
/** @var array<string, int> $kpis */

$firstAlbumId   = !empty($albums) ? (int)$albums[0]['id'] : 0;
$switcherCounts = MoviesOnlineController::getContentSwitcherCounts();
?>

<!-- Modulübergreifende Content-Gesamtübersicht (Switcher) -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom" style="border-color: var(--kkc-border) !important;">
  <div class="d-flex flex-wrap align-items-center gap-1">
    <a href="#/movies-online" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-collection-play-fill me-1"></i> Filme online
      <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_online'] ?? 0) ?></span>
    </a>
    <a href="#/movies-drafts" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-clock-history me-1"></i> Filme in Planung
      <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_drafts'] ?? 0) ?></span>
    </a>
    <a href="#/movies-blocked" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-slash-circle me-1"></i> Filme abgelehnt / gesperrt
      <span class="badge bg-danger ms-1"><?= (int)($switcherCounts['movies_blocked'] ?? 0) ?></span>
    </a>
    <a href="#/movies-checking" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-film me-1"></i> Filme prüfen
      <span class="badge bg-warning text-dark ms-1"><?= (int)($switcherCounts['movies_checking'] ?? 0) ?></span>
    </a>
    <span class="text-muted mx-1">|</span>
    <a href="#/photo-albums-online" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-journal-album me-1"></i> Fotoalben online
      <span class="badge bg-primary ms-1"><?= (int)($switcherCounts['albums_online'] ?? 0) ?></span>
    </a>
    <a href="#/photo-albums-checking" class="btn btn-sm btn-kkc fw-bold">
      <i class="bi bi-images me-1"></i> Fotoalben prüfen
      <span class="badge bg-white text-dark ms-1"><?= (int)($switcherCounts['albums_checking'] ?? 0) ?></span>
    </a>
  </div>
</div>

<!-- Page Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge-kkc badge-kkc-info">
        <i class="bi bi-shield-check"></i> Content prüfen
      </span>
      <span class="badge-kkc badge-kkc-neutral">Warteschlange</span>
    </div>
    <h1 class="h3 mb-1">Fotoalben prüfen</h1>
    <p class="text-muted mb-0">
      Hier findest du alle neu eingereichten Fotoalben inkl. FSK16/FSK18-Coverbildern, Einzelfotos, BRAM-Check und 18+ Mitwirkenden-Nachweisen.
    </p>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <?php if ($firstAlbumId > 0): ?>
    <a href="#/photo-album-checking?id=<?= $firstAlbumId ?>" class="btn btn-sm btn-kkc">
      <i class="bi bi-images me-1"></i> Nächstes Album prüfen (#<?= $firstAlbumId ?>)
    </a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
      <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
    </button>
  </div>
</div>

<!-- 1. KPI-Übersicht -->
<div class="row g-3 mb-4">
  <div class="col-xl-3 col-sm-6">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
        <i class="bi bi-images"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)($kpis['total'] ?? 0) ?></div>
        <div class="kpi-label">Alben in Warteschlange</div>
        <small class="text-muted">Bereit zur Endkontrolle</small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
        <i class="bi bi-shield-check"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)($kpis['bram_ok'] ?? 0) ?></div>
        <div class="kpi-label">BRAM Unbedenklich</div>
        <small class="text-success fw-semibold">Keine Keyword-Treffer</small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box <?= (int)($kpis['bram_attention'] ?? 0) > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'ec-bg-primary-subtle ec-text-primary' ?>">
        <i class="bi bi-exclamation-octagon"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)($kpis['bram_attention'] ?? 0) ?></div>
        <div class="kpi-label">BRAM Hinweis / Verstoß</div>
        <small class="text-muted">Titel &amp; Beschreibung prüfen</small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box <?= (int)($kpis['co_actor_open'] ?? 0) > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-success bg-opacity-10 text-success' ?>">
        <i class="bi bi-person-badge"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)($kpis['co_actor_open'] ?? 0) ?></div>
        <div class="kpi-label">18+ ID-Check offen</div>
        <small class="text-muted">Mitwirkende im Album bestätigen</small>
      </div>
    </div>
  </div>
</div>

<!-- 2. Filter- & Suchleiste + Warteschlangen-Tabelle -->
<div class="card-kkc mb-4">
  <div class="card-kkc-header flex-wrap gap-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-kkc pac-filter-btn active" data-pac-filter="all" onclick="App.setPhotoAlbumsCheckingFilter(this)">
        Alle (<?= count($albums) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc pac-filter-btn" data-pac-filter="bram_ok" onclick="App.setPhotoAlbumsCheckingFilter(this)">
        <i class="bi bi-check-circle text-success me-1"></i> BRAM OK (<?= (int)($kpis['bram_ok'] ?? 0) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc pac-filter-btn" data-pac-filter="bram_attention" onclick="App.setPhotoAlbumsCheckingFilter(this)">
        <i class="bi bi-exclamation-triangle text-danger me-1"></i> BRAM Hinweis/Verstoß (<?= (int)($kpis['bram_attention'] ?? 0) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc pac-filter-btn" data-pac-filter="co_actor_open" onclick="App.setPhotoAlbumsCheckingFilter(this)">
        <i class="bi bi-person-vcard text-warning me-1"></i> ID-Check offen (<?= (int)($kpis['co_actor_open'] ?? 0) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc pac-filter-btn" data-pac-filter="porn" onclick="App.setPhotoAlbumsCheckingFilter(this)">
        Porno
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc pac-filter-btn" data-pac-filter="fetish" onclick="App.setPhotoAlbumsCheckingFilter(this)">
        Fetisch
      </button>
    </div>

    <div class="ms-auto" style="min-width: 260px;">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-transparent border-end-0">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input type="search" id="pac-search-input" class="form-control border-start-0"
               placeholder="Album-ID, Titel, MID oder Darsteller filtern..."
               oninput="App.filterPhotoAlbumsCheckingList()">
      </div>
    </div>
  </div>

  <div class="card-kkc-body p-0">
    <?php if (empty($albums)): ?>
    <div class="p-5 text-center">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3" style="width: 64px; height: 64px;">
        <i class="bi bi-check2-all fs-2"></i>
      </div>
      <h2 class="h5 mb-1">Alle Fotoalben sind geprüft!</h2>
      <p class="text-muted small mb-0">
        Aktuell befinden sich keine offenen Fotoalben in der Prüf-Warteschlange.
      </p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0" id="pac-queue-table">
        <thead>
          <tr>
            <th style="width: 90px;">ID / Status</th>
            <th style="width: 225px;">Cover (FSK16 / FSK18)</th>
            <th>Albumtitel &amp; Eckdaten</th>
            <th style="width: 190px;">Merchant &amp; Profil</th>
            <th style="width: 170px;">18+ Mitwirkende</th>
            <th style="width: 190px;">BRAM-Compliance</th>
            <th class="text-end" style="width: 145px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($albums as $a):
              $aId       = (int)$a['id'];
              $albumHash = (string)$a['album_id'];
              $mid       = (int)$a['merchant_id'];
              $aid       = (int)$a['actor_id'];
              $status    = (string)$a['status'];
              $catMaster = (string)$a['category_master'];
              $bramComp  = (string)$a['bram_compliance'];
              $bramScore = (int)$a['bram_risk_score'];
              $perf      = (array)$a['performers'];

              $statusBadge = '<span class="badge-kkc badge-kkc-success">Aktiv</span>';
              if ($status === 'blocked') {
                  $statusBadge = '<span class="badge-kkc badge-kkc-warning">Gesperrt</span>';
              } elseif ($status === 'deleted') {
                  $statusBadge = '<span class="badge-kkc badge-kkc-danger">Gelöscht</span>';
              }

              $bramFilterTag    = ($bramComp === 'ok') ? 'bram_ok' : 'bram_attention';
              $coActorFilterTag = empty($perf['all_verified']) ? 'co_actor_open' : 'co_actor_ok';

              $searchHaystack = strtolower(
                  '#' . $aId . ' ' . $aId . ' mid ' . $mid . ' ' . $a['title'] . ' ' . $a['actor_name'] . ' ' . $albumHash
              );
          ?>
          <tr class="pac-queue-row"
              data-pac-bram="<?= View::e($bramFilterTag) ?>"
              data-pac-coactor="<?= View::e($coActorFilterTag) ?>"
              data-pac-cat="<?= View::e($catMaster) ?>"
              data-pac-search="<?= View::e($searchHaystack) ?>">

            <!-- 1. ID & Status -->
            <td>
              <div class="fw-bold font-monospace mb-1">#<?= $aId ?></div>
              <?= $statusBadge ?>
            </td>

            <!-- 2. FSK16 & FSK18 Cover-Vorschaubilder -->
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="position-relative rounded overflow-hidden border" style="width: 96px; height: 64px; background: #0f172a; cursor: pointer;"
                     onclick="App.openImagePreviewModal('media.php?type=album_poster&album_id=<?= View::e($albumHash) ?>&fsk=16', 'FSK16 Cover – Fotoalbum #<?= $aId ?>')">
                  <img src="media.php?type=album_poster&amp;album_id=<?= View::e($albumHash) ?>&amp;fsk=16&amp;w=192"
                       alt="FSK16" loading="lazy"
                       style="width: 100%; height: 100%; object-fit: cover;">
                  <span class="position-absolute bottom-0 start-0 px-1 text-white fw-bold"
                        style="font-size: 0.62rem; background: rgba(2,132,199,0.88);">FSK16</span>
                </div>

                <div class="position-relative rounded overflow-hidden border" style="width: 96px; height: 64px; background: #0f172a; cursor: pointer;"
                     onclick="App.openImagePreviewModal('media.php?type=album_poster&album_id=<?= View::e($albumHash) ?>&fsk=18', 'FSK18 Cover – Fotoalbum #<?= $aId ?>')">
                  <img src="media.php?type=album_poster&amp;album_id=<?= View::e($albumHash) ?>&amp;fsk=18&amp;w=192"
                       alt="FSK18" loading="lazy"
                       style="width: 100%; height: 100%; object-fit: cover;">
                  <span class="position-absolute bottom-0 start-0 px-1 text-white fw-bold"
                        style="font-size: 0.62rem; background: rgba(220,38,38,0.88);">FSK18</span>
                </div>
              </div>
            </td>

            <!-- 3. Albumtitel & Eckdaten -->
            <td>
              <div class="mb-1">
                <a href="#/photo-album-checking?id=<?= $aId ?>" class="fw-bold text-decoration-none text-body">
                  <?= View::e((string)$a['title']) ?>
                </a>
              </div>
              <div class="d-flex flex-wrap align-items-center gap-1 small text-muted">
                <span class="badge-kkc <?= $catMaster === 'fetish' ? 'badge-kkc-warning' : 'badge-kkc-info' ?>">
                  <?= $catMaster === 'fetish' ? 'Fetisch' : 'Porno' ?>
                </span>
                <span class="badge-kkc badge-kkc-neutral">
                  <i class="bi bi-images me-1"></i><?= (int)$a['number_of_photos'] ?> Fotos
                </span>
                <?php if ((int)$a['rejected_photos'] > 0): ?>
                <span class="badge-kkc badge-kkc-danger">
                  <i class="bi bi-exclamation-circle me-1"></i><?= (int)$a['rejected_photos'] ?> abgelehnt
                </span>
                <?php endif; ?>
                <span class="badge-kkc badge-kkc-neutral">
                  <i class="bi bi-tag me-1"></i><?= View::e((string)$a['price_eur']) ?>
                </span>
                <span class="ms-1" title="Online ab Datum">
                  <i class="bi bi-calendar-event me-1"></i>ab <?= View::e((string)$a['online_at']) ?>
                </span>
              </div>
            </td>

            <!-- 4. Merchant & Profil -->
            <td>
              <div class="mb-1">
                <a href="#/merchant?id=<?= $mid ?>" class="badge-kkc badge-kkc-neutral text-decoration-none font-monospace">
                  <i class="bi bi-briefcase me-1"></i>MID #<?= $mid ?>
                </a>
              </div>
              <div class="small">
                <?php if ($aid > 0): ?>
                <a href="#/actor?id=<?= $aid ?>" class="fw-semibold text-decoration-none ec-text-primary">
                  <i class="bi bi-person-circle me-1"></i><?= View::e((string)$a['actor_name']) ?>
                </a>
                <?php else: ?>
                <span class="text-danger fw-semibold">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i>Kein Profil gewählt
                </span>
                <?php endif; ?>
              </div>
            </td>

            <!-- 5. 18+ Mitwirkende / ID-Check -->
            <td>
              <?php if (!empty($perf['all_verified'])): ?>
                <span class="badge-kkc badge-kkc-success d-inline-flex align-items-center gap-1 mb-1">
                  <i class="bi bi-patch-check-fill"></i> ID-Check OK
                </span>
              <?php elseif (!empty($perf['has_rejected'])): ?>
                <span class="badge-kkc badge-kkc-danger d-inline-flex align-items-center gap-1 mb-1">
                  <i class="bi bi-exclamation-triangle-fill"></i> Dokument fehlt/abgel.
                </span>
              <?php else: ?>
                <span class="badge-kkc badge-kkc-warning d-inline-flex align-items-center gap-1 mb-1">
                  <i class="bi bi-hourglass-split"></i> In Prüfung
                </span>
              <?php endif; ?>
              <div class="small text-muted">
                <?= ($perf['mode'] ?? 'solo_owner') === 'solo_owner' ? 'Solo (Account-Inhaber)' : ((int)($perf['count'] ?? 1) . ' Personen gemeldet') ?>
              </div>
            </td>

            <!-- 6. BRAM-Compliance -->
            <td>
              <?php if ($bramComp === 'ok'): ?>
                <span class="badge-kkc badge-kkc-success d-inline-flex align-items-center gap-1 mb-1" title="<?= View::e((string)$a['bram_summary']) ?>">
                  <i class="bi bi-check-circle-fill"></i> BRAM OK (<?= $bramScore ?>/100)
                </span>
              <?php elseif ($bramComp === 'warning'): ?>
                <span class="badge-kkc badge-kkc-warning d-inline-flex align-items-center gap-1 mb-1" title="<?= View::e((string)$a['bram_summary']) ?>">
                  <i class="bi bi-exclamation-triangle-fill"></i> Hinweis (<?= $bramScore ?>/100)
                </span>
              <?php else: ?>
                <span class="badge-kkc badge-kkc-danger d-inline-flex align-items-center gap-1 mb-1" title="<?= View::e((string)$a['bram_summary']) ?>">
                  <i class="bi bi-x-octagon-fill"></i> Verstoß (<?= $bramScore ?>/100)
                </span>
              <?php endif; ?>
              <div class="small text-muted text-truncate" style="max-width: 175px;">
                <?= View::e((string)$a['bram_summary']) ?>
              </div>
            </td>

            <!-- 7. Aktion -->
            <td class="text-end">
              <a href="#/photo-album-checking?id=<?= $aId ?>" class="btn btn-sm btn-kkc text-nowrap">
                <i class="bi bi-journal-check me-1"></i> Jetzt prüfen
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Vollbild-Vorschau-Modal für Coverbilder -->
<div class="modal fade" id="mcImagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background: var(--kkc-card-bg); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2 px-3">
        <h5 class="modal-title h6 mb-0" id="mcImagePreviewTitle">Vorschaubild</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-2 text-center bg-dark">
        <img src="" id="mcImagePreviewImg" alt="Vorschau" class="img-fluid rounded" style="max-height: 78vh;">
      </div>
    </div>
  </div>
</div>
