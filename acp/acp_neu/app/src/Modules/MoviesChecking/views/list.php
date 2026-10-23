<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var array<int, array<string, mixed>> $movies */
/** @var array<string, int> $kpis */

$firstMovieId = !empty($movies) ? (int)$movies[0]['id'] : 0;
?>

<!-- Seiten-Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
  <div>
    <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-film ec-text-primary"></i>
      <span>Filme prüfen</span>
    </h1>
    <p class="text-muted small mb-0">
      Hier findest du alle neu hochgeladenen und fertig konvertierten Filme, die auf deine Prüfung und Freigabe warten.
    </p>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <?php if ($firstMovieId > 0): ?>
    <a href="#/movie-checking?id=<?= $firstMovieId ?>" class="btn btn-sm btn-kkc">
      <i class="bi bi-play-circle-fill me-1"></i> Nächsten Film prüfen (#<?= $firstMovieId ?>)
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
        <i class="bi bi-film"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)($kpis['total'] ?? 0) ?></div>
        <div class="kpi-label">Filme in Warteschlange</div>
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
        <small class="text-muted">Genauer auf Wortwahl prüfen</small>
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
        <small class="text-muted">Mitwirkende im Film bestätigen</small>
      </div>
    </div>
  </div>
</div>

<!-- 2. Filter- & Suchleiste + Warteschlangen-Tabelle -->
<div class="card-kkc mb-4">
  <div class="card-kkc-header flex-wrap gap-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-kkc mc-filter-btn active" data-mc-filter="all" onclick="App.setMoviesCheckingFilter(this)">
        Alle (<?= count($movies) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc mc-filter-btn" data-mc-filter="bram_ok" onclick="App.setMoviesCheckingFilter(this)">
        <i class="bi bi-check-circle text-success me-1"></i> BRAM OK (<?= (int)($kpis['bram_ok'] ?? 0) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc mc-filter-btn" data-mc-filter="bram_attention" onclick="App.setMoviesCheckingFilter(this)">
        <i class="bi bi-exclamation-triangle text-danger me-1"></i> BRAM Hinweis/Verstoß (<?= (int)($kpis['bram_attention'] ?? 0) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc mc-filter-btn" data-mc-filter="co_actor_open" onclick="App.setMoviesCheckingFilter(this)">
        <i class="bi bi-person-Vcard text-warning me-1"></i> ID-Check offen (<?= (int)($kpis['co_actor_open'] ?? 0) ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc mc-filter-btn" data-mc-filter="porn" onclick="App.setMoviesCheckingFilter(this)">
        Porno
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc mc-filter-btn" data-mc-filter="fetish" onclick="App.setMoviesCheckingFilter(this)">
        Fetisch
      </button>
    </div>

    <div class="ms-auto" style="min-width: 260px;">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-transparent border-end-0">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input type="search" id="mc-search-input" class="form-control border-start-0"
               placeholder="Film-ID, Titel, MID oder Darsteller filtern..."
               oninput="App.filterMoviesCheckingList()">
      </div>
    </div>
  </div>

  <div class="card-kkc-body p-0">
    <?php if (empty($movies)): ?>
    <div class="p-5 text-center">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3" style="width: 64px; height: 64px;">
        <i class="bi bi-check2-all fs-2"></i>
      </div>
      <h2 class="h5 mb-1">Alle Filme sind geprüft!</h2>
      <p class="text-muted small mb-0">
        Aktuell befinden sich keine offenen Video-Uploads in der Prüf-Warteschlange.
      </p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0" id="mc-queue-table">
        <thead>
          <tr>
            <th style="width: 90px;">ID / Status</th>
            <th style="width: 225px;">Vorschau (FSK16 / FSK18)</th>
            <th>Filmtitel &amp; Eckdaten</th>
            <th style="width: 190px;">Merchant &amp; Profil</th>
            <th style="width: 170px;">18+ Mitwirkende</th>
            <th style="width: 200px;">BRAM &amp; KI-Analyse</th>
            <th class="text-end" style="width: 145px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movies as $m):
              $mId       = (int)$m['id'];
              $mid       = (int)$m['merchant_id'];
              $aid       = (int)$m['actor_id'];
              $fileId    = (string)$m['file_id'];
              $status    = (string)$m['status'];
              $catMaster = (string)$m['category_master'];
              $bramComp  = (string)$m['bram_compliance'];
              $bramScore = (int)$m['bram_risk_score'];
              $subSt     = (int)$m['subtitle_status'];
              $detLang   = strtoupper((string)$m['detected_language']);
              $perf      = (array)$m['performers'];

              $statusBadge = '<span class="badge-kkc badge-kkc-success">Aktiv</span>';
              if ($status === 'blocked') {
                  $statusBadge = '<span class="badge-kkc badge-kkc-warning">Gesperrt</span>';
              } elseif ($status === 'deleted') {
                  $statusBadge = '<span class="badge-kkc badge-kkc-danger">Gelöscht</span>';
              }

              $bramFilterTag = ($bramComp === 'ok') ? 'bram_ok' : 'bram_attention';
              $coActorFilterTag = empty($perf['all_verified']) ? 'co_actor_open' : 'co_actor_ok';

              $searchHaystack = strtolower(
                  '#' . $mId . ' ' . $mId . ' mid ' . $mid . ' ' . $m['title'] . ' ' . $m['actor_name'] . ' ' . $fileId
              );
          ?>
          <tr class="mc-queue-row"
              data-mc-bram="<?= View::e($bramFilterTag) ?>"
              data-mc-coactor="<?= View::e($coActorFilterTag) ?>"
              data-mc-cat="<?= View::e($catMaster) ?>"
              data-mc-search="<?= View::e($searchHaystack) ?>">

            <!-- 1. ID & Status -->
            <td>
              <div class="fw-bold font-monospace mb-1">#<?= $mId ?></div>
              <?= $statusBadge ?>
            </td>

            <!-- 2. FSK16 & FSK18 Vorschaubilder -->
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="position-relative rounded overflow-hidden border" style="width: 96px; height: 56px; background: #0f172a; cursor: pointer;"
                     onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=16', 'FSK16 Vorschau – Film #<?= $mId ?>')">
                  <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=16&amp;w=192"
                       alt="FSK16" loading="lazy"
                       style="width: 100%; height: 100%; object-fit: cover;">
                  <span class="position-absolute bottom-0 start-0 px-1 text-white fw-bold"
                        style="font-size: 0.62rem; background: rgba(2,132,199,0.88);">FSK16 (#<?= (int)$m['preview_image_fsk16'] ?>)</span>
                </div>

                <div class="position-relative rounded overflow-hidden border" style="width: 96px; height: 56px; background: #0f172a; cursor: pointer;"
                     onclick="App.openImagePreviewModal('media.php?type=poster&file_id=<?= View::e($fileId) ?>&fsk=18', 'FSK18 Vorschau – Film #<?= $mId ?>')">
                  <img src="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=18&amp;w=192"
                       alt="FSK18" loading="lazy"
                       style="width: 100%; height: 100%; object-fit: cover;">
                  <span class="position-absolute bottom-0 start-0 px-1 text-white fw-bold"
                        style="font-size: 0.62rem; background: rgba(220,38,38,0.88);">FSK18 (#<?= (int)$m['preview_image_fsk18'] ?>)</span>
                </div>
              </div>
            </td>

            <!-- 3. Filmtitel & Eckdaten -->
            <td>
              <div class="mb-1">
                <a href="#/movie-checking?id=<?= $mId ?>" class="fw-bold text-decoration-none text-body">
                  <?= View::e((string)$m['title']) ?>
                </a>
              </div>
              <div class="d-flex flex-wrap align-items-center gap-1 small text-muted">
                <span class="badge-kkc <?= $catMaster === 'fetish' ? 'badge-kkc-warning' : 'badge-kkc-info' ?>">
                  <?= $catMaster === 'fetish' ? 'Fetisch' : 'Porno' ?>
                </span>
                <span class="badge-kkc badge-kkc-neutral">
                  <i class="bi bi-translate me-1"></i><?= View::e((string)$m['language_label']) ?>
                </span>
                <span class="badge-kkc badge-kkc-neutral font-monospace">
                  <?= View::e((string)$m['resolution']) ?> · <?= View::e((string)$m['playtime_formatted']) ?>
                </span>
                <span class="badge-kkc badge-kkc-neutral">
                  <i class="bi bi-coin me-1"></i><?= (int)$m['total_coins'] ?> Coins (<?= number_format((float)$m['amount_second'], 2, ',', '.') ?>/s)
                </span>
                <?php if (!empty($m['as_download'])): ?>
                <span class="badge-kkc badge-kkc-neutral" title="Als Download verfügbar">
                  <i class="bi bi-download me-1"></i><?= (int)$m['amount_download'] ?> Coins
                </span>
                <?php endif; ?>
                <span class="ms-1" title="Online ab Datum">
                  <i class="bi bi-calendar-event me-1"></i>ab <?= View::e((string)$m['online_at']) ?>
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
                  <i class="bi bi-person-circle me-1"></i><?= View::e((string)$m['actor_name']) ?>
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

            <!-- 6. BRAM & KI-Analyse -->
            <td>
              <?php if ($bramComp === 'ok'): ?>
                <span class="badge-kkc badge-kkc-success d-inline-flex align-items-center gap-1 mb-1" title="<?= View::e((string)$m['bram_summary']) ?>">
                  <i class="bi bi-check-circle-fill"></i> BRAM OK (<?= $bramScore ?>/100)
                </span>
              <?php elseif ($bramComp === 'warning'): ?>
                <span class="badge-kkc badge-kkc-warning d-inline-flex align-items-center gap-1 mb-1" title="<?= View::e((string)$m['bram_summary']) ?>">
                  <i class="bi bi-exclamation-triangle-fill"></i> Hinweis (<?= $bramScore ?>/100)
                </span>
              <?php elseif ($bramComp === 'violation'): ?>
                <span class="badge-kkc badge-kkc-danger d-inline-flex align-items-center gap-1 mb-1" title="<?= View::e((string)$m['bram_summary']) ?>">
                  <i class="bi bi-x-octagon-fill"></i> Verstoß (<?= $bramScore ?>/100)
                </span>
              <?php else: ?>
                <span class="badge-kkc badge-kkc-neutral d-inline-flex align-items-center gap-1 mb-1">
                  <i class="bi bi-clock"></i> Offen
                </span>
              <?php endif; ?>

              <div class="small text-muted">
                <?php if ($subSt === 2): ?>
                  <i class="bi bi-mic-fill text-success me-1"></i>Audio (.vtt<?= $detLang !== '' ? ' · ' . View::e($detLang) : '' ?>)
                <?php elseif ($subSt === 3): ?>
                  <i class="bi bi-volume-mute text-muted me-1"></i>Kein Dialog
                <?php else: ?>
                  <i class="bi bi-card-text me-1"></i>Text-Check aktiv
                <?php endif; ?>
              </div>
            </td>

            <!-- 7. Aktion -->
            <td class="text-end">
              <a href="#/movie-checking?id=<?= $mId ?>" class="btn btn-sm btn-kkc text-nowrap">
                <i class="bi bi-play-btn-fill me-1"></i> Jetzt prüfen
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

<!-- Vollbild-Vorschau-Modal für FSK16/FSK18 Bilder -->
<div class="modal fade" id="mcImagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2 px-3">
        <h5 class="modal-title h6 mb-0" id="mcImagePreviewTitle">Vorschaubild</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-2 text-center bg-dark">
        <img src="" id="mcImagePreviewImg" alt="Vorschau" class="img-fluid rounded" style="max-height: 75vh;">
      </div>
    </div>
  </div>
</div>
