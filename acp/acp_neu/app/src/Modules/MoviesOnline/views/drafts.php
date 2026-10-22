<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<int, array<string, mixed>> $drafts
 * @var array<string, mixed> $kpis
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
      <a href="#/movies-drafts" class="btn btn-sm btn-kkc fw-bold">
        <i class="bi bi-clock-history me-1"></i> Filme in Planung
        <span class="badge bg-white text-dark ms-1"><?= (int)($switcherCounts['movies_drafts'] ?? 0) ?></span>
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
        <span class="badge-kkc badge-kkc-info">Content Online</span>
        <span class="text-muted small">Film-Entwürfe &amp; Konvertierungs-Pipeline</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-clock-history ec-text-primary"></i>
        <span>Filme in Planung (<?= (int)$kpis['total'] ?>)</span>
      </h1>
      <p class="text-muted small mb-0 mt-1">
        Alle Filme, die von Creatorn hochgeladen wurden oder sich in Vorbereitung befinden, aber noch nicht zur Prüfung eingereicht wurden (`released = 0`).
      </p>
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
            <div class="text-muted small fw-semibold mb-1">Entwürfe Gesamt</div>
            <div class="h3 fw-bold mb-0"><?= (int)$kpis['total'] ?></div>
            <small class="text-muted">Speicher: <strong class="ec-text-primary"><?= View::e((string)$kpis['total_size_text']) ?></strong></small>
          </div>
          <div class="rounded-3 p-3" style="background: var(--kkc-accent-Soft); color: var(--kkc-accent);">
            <i class="bi bi-folder-symlink fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Konvertiert &amp; Bereit</div>
            <div class="h3 fw-bold mb-0 text-success"><?= (int)$kpis['ready'] ?></div>
            <small class="text-muted">Bereit zur Einreichung</small>
          </div>
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
            <i class="bi bi-check2-all fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Warteschlange / Konvertierung</div>
            <div class="h3 fw-bold mb-0 text-warning"><?= (int)$kpis['converting'] ?></div>
            <small class="text-muted">Video-Encoding läuft</small>
          </div>
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
            <i class="bi bi-gear-wide-connected fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Konvertierungs-Fehler</div>
            <div class="h3 fw-bold mb-0 <?= (int)$kpis['error'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)$kpis['error'] ?></div>
            <small class="text-muted">Encoding fehlgeschlagen</small>
          </div>
          <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-exclamation-octagon fs-3"></i>
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
      <div class="d-flex flex-wrap align-items-center gap-1" id="md-filter-pills">
        <button type="button" class="btn btn-sm btn-kkc active" data-filter="all" onclick="App.setMoviesDraftsFilter('all', this)">
          Alle (<?= (int)$kpis['total'] ?>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-filter="ready" onclick="App.setMoviesDraftsFilter('ready', this)">
          <i class="bi bi-check-circle text-success me-1"></i> Bereit (<?= (int)$kpis['ready'] ?>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-filter="converting" onclick="App.setMoviesDraftsFilter('converting', this)">
          <i class="bi bi-hourglass-split text-warning me-1"></i> In Konvertierung (<?= (int)$kpis['converting'] ?>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-kkc" data-filter="error" onclick="App.setMoviesDraftsFilter('error', this)">
          <i class="bi bi-exclamation-triangle text-danger me-1"></i> Fehler (<?= (int)$kpis['error'] ?>)
        </button>
      </div>

      <div class="input-group input-group-sm" style="max-width: 340px;">
        <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
        <input type="search" id="md-search-input" class="form-control"
               placeholder="ID, MID, Darsteller, Filmtitel..."
               oninput="App.filterMoviesDraftsList()">
      </div>
    </div>
  </div>

  <!-- ========================================================================
       TABELLE DER FILM-ENTWÜRFE
       ======================================================================== -->
  <div class="card-kkc">
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0">
        <thead>
          <tr>
            <th style="width: 80px;">ID / MID</th>
            <th style="width: 135px;">Status</th>
            <th style="width: 155px;">Vorschaubilder</th>
            <th>Filmtitel &amp; Laufzeit</th>
            <th style="width: 170px;">Darsteller</th>
            <th style="width: 145px;">Geplant für</th>
            <th style="width: 115px;">Speicherplatz</th>
            <th style="width: 120px;">Sprache / Kat.</th>
            <th class="text-end" style="width: 115px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($drafts)): ?>
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-clock-history fs-2 d-block mb-2 opacity-50"></i>
              Aktuell befinden sich keine Filme in Planung.
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($drafts as $d):
                $id     = (int)$d['id'];
                $mid    = (int)$d['merchant_id'];
                $aid    = (int)$d['actor_id'];
                $fileId = (string)$d['file_id'];
                $cStat  = (int)$d['convert_status'];

                $filterState = 'error';
                if ($cStat === 2) {
                    $filterState = 'ready';
                } elseif ($cStat === 0 || $cStat === 1) {
                    $filterState = 'converting';
                }

                $searchStr = strtolower($id . ' ' . $mid . ' ' . $d['title'] . ' ' . $d['actor_name'] . ' ' . $fileId);
            ?>
            <tr class="md-draft-row" data-state="<?= $filterState ?>" data-search="<?= View::e($searchStr) ?>">
              <td>
                <a href="#/movie-checking?id=<?= $id ?>" class="fw-bold font-monospace ec-text-primary text-decoration-none d-block">#<?= $id ?></a>
                <a href="#/merchant?id=<?= $mid ?>" class="small text-muted text-decoration-none font-monospace">MID #<?= $mid ?></a>
              </td>

              <td>
                <?php if ($cStat === 0): ?>
                  <span class="badge-kkc badge-kkc-info"><i class="bi bi-hourglass me-1"></i>Warteschlange</span>
                <?php elseif ($cStat === 1): ?>
                  <span class="badge-kkc badge-kkc-warning"><i class="bi bi-arrow-repeat me-1"></i>Konvertiert...</span>
                <?php elseif ($cStat === 2): ?>
                  <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Bereit</span>
                <?php else: ?>
                  <span class="badge-kkc badge-kkc-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>Fehler</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($cStat === 2): ?>
                <div class="d-flex align-items-center gap-1">
                  <div class="position-relative rounded overflow-hidden border bg-dark" style="width: 68px; height: 42px; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=16', 'FSK16 – Entwurf #<?= $id ?>')">
                    <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=16" alt="FSK16" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                    <span class="position-absolute bottom-0 start-0 px-1 bg-info text-dark fw-bold" style="font-size: 0.55rem;">16</span>
                  </div>
                  <div class="position-relative rounded overflow-hidden border bg-dark" style="width: 68px; height: 42px; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=18', 'FSK18 – Entwurf #<?= $id ?>')">
                    <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=18" alt="FSK18" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                    <span class="position-absolute bottom-0 start-0 px-1 bg-danger text-white fw-bold" style="font-size: 0.55rem;">18</span>
                  </div>
                </div>
                <?php else: ?>
                  <span class="text-muted small fst-italic">Noch keine Vorschau</span>
                <?php endif; ?>
              </td>

              <td>
                <a href="#/movie-checking?id=<?= $id ?>" class="fw-bold text-body text-decoration-none d-block">
                  <?= View::e((string)$d['title'] ?: '(Noch kein Titel vergeben)') ?>
                </a>
                <small class="text-muted font-monospace">
                  <?= View::e((string)$d['resolution'] ?: '-') ?> &bull; <?= View::e((string)$d['playtime_str'] ?: '-') ?>
                </small>
              </td>

              <td>
                <?php if ($aid > 0 && $d['actor_name'] !== ''): ?>
                  <a href="#/actor?id=<?= $aid ?>" class="fw-semibold ec-text-primary text-decoration-none">
                    <i class="bi bi-person-badge me-1"></i><?= View::e((string)$d['actor_name']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>

              <td>
                <?php
                  $oAt = (string)$d['online_at'];
                  $oValid = ($oAt !== '' && $oAt !== '0000-00-00 00:00:00');
                ?>
                <span class="small font-monospace"><?= $oValid ? date('d.m.Y H:i', strtotime($oAt)) : '-' ?></span>
              </td>

              <td>
                <span class="badge-kkc badge-kkc-neutral font-monospace"><?= View::e((string)$d['size_text']) ?></span>
              </td>

              <td>
                <div class="small"><?= View::e((string)$d['language_label']) ?></div>
                <?php if ($d['category_master'] === 'fetish'): ?>
                  <span class="badge bg-dark text-white" style="font-size: 0.65rem;">Fetisch</span>
                <?php elseif ($d['category_master'] === 'porn'): ?>
                  <span class="badge bg-primary bg-opacity-10 ec-text-primary" style="font-size: 0.65rem;">Porno</span>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>

              <td class="text-end">
                <a href="#/movie-checking?id=<?= $id ?>" class="btn btn-sm btn-outline-kkc">
                  <i class="bi bi-eye me-1"></i> Öffnen
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
