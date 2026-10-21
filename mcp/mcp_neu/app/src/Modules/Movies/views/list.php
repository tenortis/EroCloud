<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Filme in der Cloud (#/movies)
 * Alle UI-Texte strikt in höflicher Sie-Form (@master.md).
 *
 * @var array $movies
 * @var int   $countTotal
 * @var int   $countOnline
 * @var int   $countPending
 * @var int   $countRejected
 */
?>

<!-- Seitenkopf -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <h1 class="h4 font-heading fw-bold mb-1">
      <i class="bi bi-collection-play-fill ec-text-primary me-2"></i>Meine Filme in der EroCloud
    </h1>
    <p class="text-muted small mb-0">
      Verwalten Sie hier alle hochgeladenen Filme, Vorschaubilder (FSK16 / FSK18), Preise und Kategorien.
    </p>
  </div>

  <div class="d-flex align-items-center gap-2">
    <a href="#/movie-upload" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-2">
      <i class="bi bi-cloud-arrow-up-fill"></i>
      <span>Neuen Film hochladen</span>
    </a>
  </div>
</div>

<!-- 4 KPI-Kacheln (ohne Umsatzzahlen) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
        <i class="bi bi-collection-play-fill"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)$countTotal ?></div>
        <div class="kpi-label">Filme gesamt</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div>
        <div class="kpi-val text-success"><?= (int)$countOnline ?></div>
        <div class="kpi-label">Filme Online</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div>
        <div class="kpi-val text-warning"><?= (int)$countPending ?></div>
        <div class="kpi-label">In Prüfung / Konvertierung</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box <?= $countRejected > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary' ?>">
        <i class="bi bi-exclamation-octagon-fill"></i>
      </div>
      <div>
        <div class="kpi-val <?= $countRejected > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)$countRejected ?></div>
        <div class="kpi-label">Abgelehnt / Korrektur nötig</div>
      </div>
    </div>
  </div>
</div>

<?php if (empty($movies)): ?>
<!-- Leerzustand -->
<div class="card-kkc p-5 text-center">
  <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
    <i class="bi bi-film"></i>
  </div>
  <h2 class="h5 font-heading fw-bold mb-2">Sie haben noch keine Filme hochgeladen</h2>
  <p class="text-muted small mx-auto mb-4" style="max-width: 520px;">
    Laden Sie jetzt Ihren ersten Film in die EroCloud hoch und verdienen Sie an jedem Streaming-Abruf und Download.
  </p>
  <a href="#/movie-upload" class="btn btn-primary-kkc px-4">
    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Jetzt ersten Film hochladen
  </a>
</div>
<?php else: ?>

<!-- Filter- & Tabellen-Kachel -->
<div class="card-kkc mb-0">
  <div class="card-kkc-header flex-wrap gap-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-primary-kkc content-status-filter active" data-filter="all" data-target="#movies-table-body">
        Alle (<?= (int)$countTotal ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc content-status-filter" data-filter="online" data-target="#movies-table-body">
        Online (<?= (int)$countOnline ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc content-status-filter" data-filter="pending" data-target="#movies-table-body">
        In Bearbeitung (<?= (int)$countPending ?>)
      </button>
      <?php if ($countRejected > 0): ?>
      <button type="button" class="btn btn-sm btn-outline-kkc content-status-filter" data-filter="rejected" data-target="#movies-table-body">
        Abgelehnt (<?= (int)$countRejected ?>)
      </button>
      <?php endif; ?>
    </div>

    <div class="position-relative" style="min-width: 260px;">
      <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
      <input type="text"
             id="movies-search-input"
             class="form-control form-control-sm ps-5 content-table-search"
             data-target="#movies-table-body"
             placeholder="Nach Filmtitel, ID oder Darsteller suchen...">
    </div>
  </div>

  <div class="card-kkc-body p-0">
    <div class="table-responsive">
      <table class="kkc-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>ID</th>
            <th>Vorschaubilder (FSK16 / FSK18)</th>
            <th>Filmtitel</th>
            <th>Darsteller</th>
            <th>Online ab</th>
            <th class="text-end">Aktion</th>
          </tr>
        </thead>
        <tbody id="movies-table-body">
          <?php foreach ($movies as $m): ?>
          <?php
            $searchText = mb_strtolower($m['id'] . ' ' . $m['title'] . ' ' . $m['actor_name'] . ' ' . $m['status_label'], 'UTF-8');
          ?>
          <tr data-status="<?= htmlspecialchars((string)$m['status_key'], ENT_QUOTES, 'UTF-8') ?>"
              data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
            <td>
              <span class="badge-kkc <?= htmlspecialchars((string)$m['status_badge'], ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi <?= htmlspecialchars((string)$m['status_icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <?= htmlspecialchars((string)$m['status_label'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td class="font-monospace text-muted">#<?= (int)$m['id'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>" class="position-relative d-inline-block" title="FSK16 Vorschaubild">
                  <img src="<?= htmlspecialchars((string)$m['poster_fsk16'], ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK16"
                       class="rounded border object-fit-cover"
                       style="width: 78px; height: 44px; background-color: var(--kkc-bg);"
                       loading="lazy">
                  <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 start-0 m-1" style="font-size: 0.58rem; padding: 1px 4px;">16</span>
                </a>
                <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>" class="position-relative d-inline-block" title="FSK18 Vorschaubild">
                  <img src="<?= htmlspecialchars((string)$m['poster_fsk18'], ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK18"
                       class="rounded border object-fit-cover"
                       style="width: 78px; height: 44px; background-color: var(--kkc-bg);"
                       loading="lazy">
                  <span class="badge bg-danger position-absolute bottom-0 start-0 m-1" style="font-size: 0.58rem; padding: 1px 4px;">18</span>
                </a>
              </div>
            </td>
            <td>
              <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>" class="fw-semibold text-decoration-none text-body d-block">
                <?= htmlspecialchars((string)$m['title'], ENT_QUOTES, 'UTF-8') ?>
              </a>
              <div class="text-muted small" style="font-size: 0.74rem;">
                <?php if ($m['playtime_string'] !== ''): ?>
                  <span><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string)$m['playtime_string'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($m['resolution'] !== ''): ?>
                  <span class="ms-2"><i class="bi bi-aspect-ratio me-1"></i><?= htmlspecialchars((string)$m['resolution'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ((int)$m['actor_id'] > 0): ?>
                <a href="#/actor?id=<?= (int)$m['actor_id'] ?>" class="text-decoration-none ec-text-primary fw-medium">
                  <?= htmlspecialchars((string)$m['actor_name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small text-nowrap"><?= htmlspecialchars((string)$m['online_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-end">
              <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-kkc py-1 px-2 text-nowrap">
                <i class="bi bi-pencil-square me-1"></i> Bearbeiten
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
