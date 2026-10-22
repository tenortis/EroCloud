<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<int, array<string, mixed>> $movies
 * @var array<string, int> $kpis
 * @var array<string, int> $switcherCounts
 */
?>
<div class="container-fluid py-3" data-page-width="full">

  <!-- ========================================================================
       MODULÜBERGREIFENDE CONTENT-GESAMTÜBERSICHT (SWITCHER)
       ======================================================================== -->
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
      <a href="#/movies-blocked" class="btn btn-sm btn-kkc fw-bold">
        <i class="bi bi-slash-circle me-1"></i> Filme abgelehnt / gesperrt
        <span class="badge bg-white text-dark ms-1"><?= (int)($switcherCounts['movies_blocked'] ?? 0) ?></span>
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
      <a href="#/photo-albums-checking" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-images me-1"></i> Fotoalben prüfen
        <span class="badge bg-warning text-dark ms-1"><?= (int)($switcherCounts['albums_checking'] ?? 0) ?></span>
      </a>
    </div>
  </div>

  <!-- ========================================================================
       SEITEN-HEADER
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge-kkc badge-kkc-danger">Content prüfen</span>
        <span class="text-muted small">Abgelehnte, gesperrte &amp; gelöschte Filme</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-slash-circle ec-text-primary"></i>
        <span>Filme abgelehnt &amp; gesperrt (<?= (int)$kpis['total'] ?>)</span>
      </h1>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
      </button>
    </div>
  </div>

  <!-- ========================================================================
       KPI-KACHELN
       ======================================================================== -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Gesamt nicht aktiv</div>
            <div class="h3 fw-bold mb-0"><?= (int)$kpis['total'] ?></div>
            <small class="text-muted">Konvertierte Filme</small>
          </div>
          <div class="rounded-3 p-3" style="background: var(--kkc-accent-Soft); color: var(--kkc-accent);">
            <i class="bi bi-slash-circle fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">In Prüfung abgelehnt</div>
            <div class="h3 fw-bold mb-0 text-warning"><?= (int)$kpis['rejected'] ?></div>
            <small class="text-muted">Mit Ablehnungsgrund (`released = 2`)</small>
          </div>
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
            <i class="bi bi-exclamation-triangle-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Gesperrt</div>
            <div class="h3 fw-bold mb-0 text-secondary"><?= (int)$kpis['blocked'] ?></div>
            <small class="text-muted">Status `blocked`</small>
          </div>
          <div class="rounded-3 p-3 bg-secondary bg-opacity-10 text-secondary">
            <i class="bi bi-pause-circle-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Zur Löschung markiert</div>
            <div class="h3 fw-bold mb-0 text-danger"><?= (int)$kpis['deleted'] ?></div>
            <small class="text-muted">Status `deleted`</small>
          </div>
          <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-trash3-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       ECHTZEIT-FILTER & SUCHE
       ======================================================================== -->
  <div class="card-kkc mb-3">
    <div class="card-kkc-body py-2.5 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex flex-wrap align-items-center gap-1" id="mb-filter-pills">
        <button type="button" class="btn btn-sm btn-kkc active" data-filter="all" onclick="App.setMoviesBlockedFilter('all', this)">
          Alle (<?= (int)$kpis['total'] ?>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-filter="rejected" onclick="App.setMoviesBlockedFilter('rejected', this)">
          <i class="bi bi-exclamation-triangle text-warning me-1"></i> Abgelehnt (<?= (int)$kpis['rejected'] ?>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-filter="blocked" onclick="App.setMoviesBlockedFilter('blocked', this)">
          <i class="bi bi-pause-circle text-secondary me-1"></i> Gesperrt (<?= (int)$kpis['blocked'] ?>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-filter="deleted" onclick="App.setMoviesBlockedFilter('deleted', this)">
          <i class="bi bi-trash3 text-danger me-1"></i> Löschung (<?= (int)$kpis['deleted'] ?>)
        </button>
      </div>

      <div class="input-group input-group-sm" style="max-width: 360px;">
        <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
        <input type="search" id="mb-search-input" class="form-control"
               placeholder="ID, MID, Darsteller, Filmtitel, Ablehnungsgrund..."
               oninput="App.filterMoviesBlockedList()">
      </div>
    </div>
  </div>

  <!-- ========================================================================
       TABELLE DER ABGELEHNTEN / GESPERRTEN FILME
       ======================================================================== -->
  <div class="card-kkc">
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0">
        <thead>
          <tr>
            <th style="width: 80px;">ID / MID</th>
            <th style="width: 120px;">Status</th>
            <th style="width: 155px;">FSK16 / FSK18</th>
            <th>Filmtitel &amp; Letzter Ablehnungsgrund</th>
            <th style="width: 165px;">Darsteller</th>
            <th style="width: 145px;">Geht online am</th>
            <th style="width: 145px;">Bearbeitet am</th>
            <th style="width: 115px;">Sprache / Kat.</th>
            <th class="text-end" style="width: 115px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movies)): ?>
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>
              Aktuell sind keine abgelehnten oder gesperrten Filme vorhanden.
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($movies as $m):
                $id       = (int)$m['id'];
                $mid      = (int)$m['merchant_id'];
                $aid      = (int)$m['actor_id'];
                $fileId   = (string)$m['file_id'];
                $stateKey = (string)$m['state_key'];

                $searchStr = strtolower(
                    $id . ' ' . $mid . ' ' . $m['title'] . ' ' . $m['actor_name']
                    . ' ' . $m['rejection_short'] . ' ' . $m['rejection_text']
                );
            ?>
            <tr class="mb-blocked-row" data-state="<?= View::e($stateKey) ?>" data-search="<?= View::e($searchStr) ?>">
              <td>
                <a href="#/movie-checking?id=<?= $id ?>" class="fw-bold font-monospace ec-text-primary text-decoration-none d-block">#<?= $id ?></a>
                <a href="#/merchant?id=<?= $mid ?>" class="small text-muted text-decoration-none font-monospace">MID #<?= $mid ?></a>
              </td>

              <td>
                <?php if ($stateKey === 'deleted'): ?>
                  <span class="badge-kkc badge-kkc-danger"><i class="bi bi-trash3-fill me-1"></i>Löschung</span>
                <?php elseif ($stateKey === 'blocked'): ?>
                  <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-pause-circle-fill me-1"></i>Gesperrt</span>
                <?php else: ?>
                  <span class="badge-kkc badge-kkc-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Abgelehnt</span>
                <?php endif; ?>
              </td>

              <td>
                <div class="d-flex align-items-center gap-1">
                  <div class="position-relative rounded overflow-hidden border bg-dark" style="width: 68px; height: 42px; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=16', 'FSK16 – Film #<?= $id ?>')">
                    <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=16" alt="FSK16" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                    <span class="position-absolute bottom-0 start-0 px-1 bg-info text-dark fw-bold" style="font-size: 0.55rem;">16</span>
                  </div>
                  <div class="position-relative rounded overflow-hidden border bg-dark" style="width: 68px; height: 42px; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=18', 'FSK18 – Film #<?= $id ?>')">
                    <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=18" alt="FSK18" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                    <span class="position-absolute bottom-0 start-0 px-1 bg-danger text-white fw-bold" style="font-size: 0.55rem;">18</span>
                  </div>
                </div>
              </td>

              <td>
                <a href="#/movie-checking?id=<?= $id ?>" class="fw-bold text-body text-decoration-none d-block mb-0.5">
                  <?= View::e((string)$m['title'] ?: '(Ohne Titel)') ?>
                </a>
                <?php if ($m['rejection_short'] !== '' || $m['rejection_text'] !== ''): ?>
                  <div class="small text-danger">
                    <i class="bi bi-chat-left-quote-fill me-1"></i>
                    <strong><?= View::e((string)$m['rejection_short']) ?>:</strong>
                    <span class="text-muted"><?= View::e(mb_strimwidth((string)$m['rejection_text'], 0, 110, '…')) ?></span>
                  </div>
                <?php else: ?>
                  <small class="text-muted font-monospace"><?= View::e((string)$m['resolution'] ?: '-') ?> &bull; <?= View::e((string)$m['playtime_str'] ?: '-') ?></small>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($aid > 0 && $m['actor_name'] !== ''): ?>
                  <a href="#/actor?id=<?= $aid ?>" class="fw-semibold ec-text-primary text-decoration-none">
                    <i class="bi bi-person-badge me-1"></i><?= View::e((string)$m['actor_name']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>

              <td>
                <?php
                  $oAt = (string)$m['online_at'];
                  $oValid = ($oAt !== '' && $oAt !== '0000-00-00 00:00:00');
                ?>
                <span class="small font-monospace"><?= $oValid ? date('d.m.Y H:i', strtotime($oAt)) : '-' ?></span>
              </td>

              <td>
                <?php
                  $aAt = (string)$m['action_date'];
                  $aValid = ($aAt !== '' && $aAt !== '0000-00-00 00:00:00');
                ?>
                <span class="small font-monospace"><?= $aValid ? date('d.m.Y H:i', strtotime($aAt)) : '-' ?></span>
              </td>

              <td>
                <div class="small"><?= View::e((string)$m['language_label']) ?></div>
                <?php if ($m['category_master'] === 'fetish'): ?>
                  <span class="badge bg-dark text-white" style="font-size: 0.65rem;">Fetisch</span>
                <?php elseif ($m['category_master'] === 'porn'): ?>
                  <span class="badge bg-primary bg-opacity-10 ec-text-primary" style="font-size: 0.65rem;">Porno</span>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>

              <td class="text-end">
                <a href="#/movie-checking?id=<?= $id ?>" class="btn btn-sm btn-kkc">
                  <i class="bi bi-search me-1"></i> Prüfen
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
