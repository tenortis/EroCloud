<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<int, array<string, mixed>> $albums
 * @var int                              $countTotal
 * @var int                              $countOnline
 * @var int                              $countPending
 * @var int                              $countRejected
 */
?>

<!-- Kopfbereich -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <h2 class="h4 fw-bold mb-1">Foto-Alben</h2>
    <p class="text-muted small mb-0">
      Verwalten Sie Ihre hochgeladenen Foto-Alben, Coverbilder, Verkaufspreise und Freigaben.
    </p>
  </div>
  <div>
    <a href="#/photo-album-upload" class="btn btn-kkc-primary">
      <i class="bi bi-plus-circle me-2"></i> Neues Foto-Album hochladen
    </a>
  </div>
</div>

<!-- KPI-Kacheln (ohne Umsatzzahlen) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="kkc-stat-card">
      <div class="kkc-stat-icon bg-primary bg-opacity-10 text-primary">
        <i class="bi bi-images"></i>
      </div>
      <div>
        <div class="kkc-stat-label">Foto-Alben gesamt</div>
        <div class="kkc-stat-value"><?= (int)$countTotal ?></div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="kkc-stat-card">
      <div class="kkc-stat-icon bg-success bg-opacity-10 text-success">
        <i class="bi bi-check2-circle"></i>
      </div>
      <div>
        <div class="kkc-stat-label">Online &amp; aktiv</div>
        <div class="kkc-stat-value"><?= (int)$countOnline ?></div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="kkc-stat-card">
      <div class="kkc-stat-icon bg-warning bg-opacity-10 text-warning">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div>
        <div class="kkc-stat-label">In Prüfung / Geplant</div>
        <div class="kkc-stat-value"><?= (int)$countPending ?></div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="kkc-stat-card">
      <div class="kkc-stat-icon <?= $countRejected > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary' ?>">
        <i class="bi bi-exclamation-octagon-fill"></i>
      </div>
      <div>
        <div class="kkc-stat-label">Korrektur nötig / Abgelehnt</div>
        <div class="kkc-stat-value <?= $countRejected > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)$countRejected ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Filter- & Suchleiste + Tabelle -->
<div class="kkc-card">
  <div class="kkc-card-header flex-wrap gap-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-kkc-primary js-album-filter-btn active" data-filter="all">
        Alle (<?= (int)$countTotal ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc js-album-filter-btn" data-filter="online">
        Online (<?= (int)$countOnline ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc js-album-filter-btn" data-filter="pending">
        In Prüfung / Entwurf (<?= (int)$countPending ?>)
      </button>
      <?php if ($countRejected > 0): ?>
        <button type="button" class="btn btn-sm btn-outline-danger js-album-filter-btn" data-filter="rejected">
          Korrektur nötig (<?= (int)$countRejected ?>)
        </button>
      <?php endif; ?>
    </div>

    <div class="ms-auto" style="min-width: 260px;">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-transparent border-end-0">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input type="search" id="kkc-albums-search" class="form-control border-start-0"
               placeholder="Titel, Darsteller oder ID suchen...">
      </div>
    </div>
  </div>

  <div class="kkc-card-body p-0">
    <?php if (empty($albums)): ?>
      <div class="p-5 text-center">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary mb-3"
             style="width: 64px; height: 64px;">
          <i class="bi bi-images fs-2"></i>
        </div>
        <h5 class="fw-bold mb-2">Sie haben noch keine Foto-Alben angelegt</h5>
        <p class="text-muted small mb-4">
          Erstellen Sie jetzt Ihr erstes Foto-Album und bieten Sie Ihren Kunden exklusive Bilderserien zum Download an.
        </p>
        <a href="#/photo-album-upload" class="btn btn-kkc-primary">
          <i class="bi bi-plus-circle me-2"></i> Erstes Foto-Album hochladen
        </a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table kkc-table align-middle mb-0" id="kkc-albums-table">
          <thead>
            <tr>
              <th class="ps-4" style="width: 85px;">ID</th>
              <th style="width: 155px;">Cover (16 / 18)</th>
              <th>Titel &amp; Details</th>
              <th>Darsteller</th>
              <th>Online ab</th>
              <th>Status</th>
              <th class="text-end pe-4">Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($albums as $alb): ?>
              <?php
              $aId        = (int)$alb['id'];
              $aTitle     = (string)$alb['title'];
              $aActor     = (string)$alb['actor_name'];
              $filterCat  = in_array((string)$alb['status_key'], ['incomplete', 'pending', 'scheduled'], true)
                  ? 'pending'
                  : (in_array((string)$alb['status_key'], ['rejected', 'blocked'], true) ? 'rejected' : (string)$alb['status_key']);
              $searchText = mb_strtolower($aId . ' ' . $aTitle . ' ' . $aActor . ' ' . (string)$alb['status_label'], 'UTF-8');
              ?>
              <tr class="js-album-row"
                  data-status="<?= htmlspecialchars($filterCat, ENT_QUOTES, 'UTF-8') ?>"
                  data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                <td class="ps-4 fw-semibold text-muted">#<?= $aId ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="position-relative">
                      <img src="<?= htmlspecialchars((string)$alb['poster_fsk16'], ENT_QUOTES, 'UTF-8') ?>"
                           alt="FSK16"
                           class="rounded border"
                           style="width: 60px; height: 42px; object-fit: cover;"
                           loading="lazy">
                      <span class="badge bg-success position-absolute bottom-0 start-0 m-1" style="font-size: 0.6rem; padding: 1px 4px;">16</span>
                    </div>
                    <div class="position-relative">
                      <img src="<?= htmlspecialchars((string)$alb['poster_fsk18'], ENT_QUOTES, 'UTF-8') ?>"
                           alt="FSK18"
                           class="rounded border"
                           style="width: 60px; height: 42px; object-fit: cover;"
                           loading="lazy">
                      <span class="badge bg-danger position-absolute bottom-0 start-0 m-1" style="font-size: 0.6rem; padding: 1px 4px;">18</span>
                    </div>
                  </div>
                </td>
                <td>
                  <a href="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                     class="fw-bold text-decoration-none text-dark d-block mb-1">
                    <?= htmlspecialchars($aTitle, ENT_QUOTES, 'UTF-8') ?>
                  </a>
                  <div class="d-flex align-items-center gap-2 small text-muted">
                    <span><i class="bi bi-camera me-1"></i><?= (int)$alb['number_of_photos'] ?> Fotos</span>
                    <span>&bull;</span>
                    <span>
                      <?php if ((float)$alb['price_eur'] <= 0): ?>
                        Kostenlos
                      <?php else: ?>
                        VK: <?= number_format((float)$alb['price_eur'], 2, ',', '.') ?> €
                      <?php endif; ?>
                    </span>
                  </div>
                </td>
                <td>
                  <span class="badge bg-light text-dark border">
                    <i class="bi bi-person-badge me-1 text-primary"></i><?= htmlspecialchars($aActor, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="small text-muted"><?= htmlspecialchars((string)$alb['online_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <span class="badge bg-<?= htmlspecialchars((string)$alb['status_badge'], ENT_QUOTES, 'UTF-8') ?> bg-opacity-10 text-<?= htmlspecialchars((string)$alb['status_badge'], ENT_QUOTES, 'UTF-8') ?> border border-<?= htmlspecialchars((string)$alb['status_badge'], ENT_QUOTES, 'UTF-8') ?> border-opacity-25">
                    <i class="bi <?= htmlspecialchars((string)$alb['status_icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                    <?= htmlspecialchars((string)$alb['status_label'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="text-end pe-4">
                  <div class="d-inline-flex gap-1">
                    <a href="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                       class="btn btn-sm btn-outline-kkc"
                       title="Foto-Album bearbeiten">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <?php if (!empty($alb['can_delete'])): ?>
                      <button type="button"
                              class="btn btn-sm btn-outline-danger js-delete-photo-album-btn"
                              data-album-id="<?= $aId ?>"
                              data-album-title="<?= htmlspecialchars($aTitle, ENT_QUOTES, 'UTF-8') ?>"
                              title="Foto-Album löschen">
                        <i class="bi bi-trash3"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
