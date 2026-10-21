<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * EroCloud MCP (v2.0) - Foto-Alben in der Cloud (#/photo-albums)
 * Alle UI-Texte strikt in persönlicher Du-Form (@master.md).
 * Keine Anzeige von Umsätzen, Provisionen oder Verkaufszahlen (@mcp-modernization-architecture.md).
 *
 * @var array<int, array<string, mixed>> $albums
 * @var array<int, string>               $actorsMap
 * @var int                              $countTotal
 * @var int                              $countOnline
 * @var int                              $countPending
 * @var int                              $countRejected
 */
?>

<!-- Kopfbereich -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <h1 class="h4 font-heading fw-bold mb-1">
      <i class="bi bi-images ec-text-primary me-2"></i>Meine Foto-Alben in der EroCloud
    </h1>
    <p class="text-muted small mb-0">
      Verwalte deine hochgeladenen Foto-Alben, Coverbilder (FSK16 / FSK18), Verkaufspreise und Freigaben. Bewege die Maus über ein Coverbild, um es vergrößert anzuzeigen.
    </p>
  </div>
  <div>
    <a href="#/photo-album-upload" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-2">
      <i class="bi bi-plus-circle"></i>
      <span>Neues Foto-Album hochladen</span>
    </a>
  </div>
</div>

<!-- 4 klickbare KPI-Filter-Kacheln (ohne Umsatzzahlen) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card is-filter-card active-kpi-filter h-100 mb-0 js-smart-kpi-filter" data-filter="all" title="Alle Foto-Alben anzeigen">
      <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
        <i class="bi bi-images"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)$countTotal ?></div>
        <div class="kpi-label">Foto-Alben gesamt</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card is-filter-card h-100 mb-0 js-smart-kpi-filter" data-filter="online" title="Nur aktive Online-Alben anzeigen">
      <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div>
        <div class="kpi-val text-success"><?= (int)$countOnline ?></div>
        <div class="kpi-label">Online &amp; aktiv</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card is-filter-card h-100 mb-0 js-smart-kpi-filter" data-filter="pending" title="Alben in Prüfung / Planung anzeigen">
      <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div>
        <div class="kpi-val text-warning"><?= (int)$countPending ?></div>
        <div class="kpi-label">In Prüfung / Geplant</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card is-filter-card h-100 mb-0 js-smart-kpi-filter" data-filter="rejected" title="Abgelehnte Alben anzeigen">
      <div class="kpi-icon-box <?= $countRejected > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary' ?>">
        <i class="bi bi-exclamation-octagon-fill"></i>
      </div>
      <div>
        <div class="kpi-val <?= $countRejected > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)$countRejected ?></div>
        <div class="kpi-label">Korrektur nötig / Abgelehnt</div>
      </div>
    </div>
  </div>
</div>

<?php if (empty($albums)): ?>
<!-- Leerzustand -->
<div class="card-kkc p-5 text-center">
  <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
    <i class="bi bi-images"></i>
  </div>
  <h2 class="h5 font-heading fw-bold mb-2">Du hast noch keine Foto-Alben angelegt</h2>
  <p class="text-muted small mx-auto mb-4" style="max-width: 520px;">
    Erstelle jetzt dein erstes Foto-Album und biete deinen Kunden exklusive Bilderserien zum Download an.
  </p>
  <a href="#/photo-album-upload" class="btn btn-primary-kkc px-4">
    <i class="bi bi-plus-circle me-1"></i> Erstes Foto-Album hochladen
  </a>
</div>
<?php else: ?>

<!-- Interaktive Filter-, Sortier- & Tabellen-Kachel -->
<div class="card-kkc mb-0" id="smart-table-container" data-entity-label="Foto-Alben">
  <div class="card-kkc-header flex-wrap gap-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-primary-kkc js-smart-status-btn active" data-filter="all">
        Alle (<?= (int)$countTotal ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc js-smart-status-btn" data-filter="online">
        Online (<?= (int)$countOnline ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc js-smart-status-btn" data-filter="pending">
        In Prüfung / Entwurf (<?= (int)$countPending ?>)
      </button>
      <?php if ($countRejected > 0): ?>
        <button type="button" class="btn btn-sm btn-outline-kkc js-smart-status-btn" data-filter="rejected">
          Korrektur nötig (<?= (int)$countRejected ?>)
        </button>
      <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
      <?php if (!empty($actorsMap) && count($actorsMap) > 1): ?>
      <select id="smart-actor-filter" class="form-select form-select-sm" style="width: auto; min-width: 165px;" title="Nach Darsteller-Profil filtern">
        <option value="all">Alle Darsteller (<?= count($actorsMap) ?>)</option>
        <?php foreach ($actorsMap as $aId => $aName): ?>
          <option value="<?= (int)$aId ?>"><?= htmlspecialchars((string)$aName, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>

      <div class="d-flex align-items-center gap-1">
        <label for="smart-sort-select" class="small text-muted text-nowrap d-none d-md-inline me-1">
          <i class="bi bi-arrow-down-up"></i> Sortieren:
        </label>
        <select id="smart-sort-select" class="form-select form-select-sm" style="width: auto; min-width: 205px;">
          <option value="id:desc" selected>ID: Neueste zuerst (# absteigend)</option>
          <option value="id:asc">ID: Älteste zuerst (# aufsteigend)</option>
          <option value="online:desc">Online ab: Neueste zuerst</option>
          <option value="online:asc">Online ab: Älteste zuerst</option>
          <option value="title:asc">Albumtitel: A bis Z</option>
          <option value="title:desc">Albumtitel: Z bis A</option>
        </select>
      </div>

      <div class="position-relative" style="min-width: 275px;">
        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
        <input type="text"
               id="smart-search-input"
               class="form-control form-control-sm ps-5 pe-4"
               placeholder="Nach #ID, Titel oder Darsteller suchen..."
               autocomplete="off">
        <button type="button"
                id="smart-search-clear"
                class="btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y me-1 p-0 px-1 d-none"
                title="Suche zurücksetzen">
          <i class="bi bi-x-circle-fill"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="card-kkc-body p-0">
    <div class="table-responsive">
      <table class="kkc-table">
        <thead>
          <tr>
            <th class="kkc-sortable-th" data-sort-col="status" style="width: 145px;">
              Status <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="kkc-sortable-th is-sorted" data-sort-col="id" style="width: 90px;">
              ID <i class="bi bi-sort-numeric-down-alt sort-icon"></i>
            </th>
            <th style="width: 205px;">
              Coverbilder (FSK16 / FSK18)
            </th>
            <th class="kkc-sortable-th" data-sort-col="title">
              Albumtitel &amp; Details <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="kkc-sortable-th" data-sort-col="actor" style="width: 145px;">
              Darsteller <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="kkc-sortable-th" data-sort-col="online" style="width: 155px;">
              Online ab <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="text-end" style="width: 140px;">Aktion</th>
          </tr>
        </thead>
        <tbody id="smart-table-body">
          <?php foreach ($albums as $alb): ?>
            <?php
            $aId        = (int)$alb['id'];
            $aTitle     = (string)$alb['title'];
            $aActor     = (string)$alb['actor_name'];
            $statusLbl  = (string)$alb['status_label'];
            $onlineStr  = (string)$alb['online_at'];
            $filterCat  = in_array((string)$alb['status_key'], ['incomplete', 'pending', 'scheduled'], true)
                ? 'pending'
                : (in_array((string)$alb['status_key'], ['rejected', 'blocked'], true) ? 'rejected' : (string)$alb['status_key']);
            $searchText = mb_strtolower('#' . $aId . ' ' . $aId . ' ' . $aTitle . ' ' . $aActor . ' ' . $statusLbl . ' ' . $onlineStr, 'UTF-8');
            ?>
            <tr class="js-smart-row kkc-row-clickable"
                data-id="<?= $aId ?>"
                data-status="<?= htmlspecialchars($filterCat, ENT_QUOTES, 'UTF-8') ?>"
                data-actor-id="<?= (int)$alb['actor_id'] ?>"
                data-actor="<?= htmlspecialchars(mb_strtolower($aActor, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                data-title="<?= htmlspecialchars(mb_strtolower($aTitle, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                data-online="<?= (int)($alb['online_at_ts'] ?? 0) ?>"
                data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                data-edit-route="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>">
              <td>
                <span class="badge bg-<?= htmlspecialchars((string)$alb['status_badge'], ENT_QUOTES, 'UTF-8') ?> bg-opacity-10 text-<?= htmlspecialchars((string)$alb['status_badge'], ENT_QUOTES, 'UTF-8') ?> border border-<?= htmlspecialchars((string)$alb['status_badge'], ENT_QUOTES, 'UTF-8') ?> border-opacity-25">
                  <i class="bi <?= htmlspecialchars((string)$alb['status_icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                  <?= htmlspecialchars($statusLbl, ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td>
                <span class="font-monospace fw-semibold text-muted">#<?= $aId ?></span>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <a href="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                     class="ec-thumb-wrap js-hover-zoom-thumb"
                     data-zoom-src="<?= htmlspecialchars((string)$alb['zoom_fsk16'], ENT_QUOTES, 'UTF-8') ?>"
                     data-zoom-fsk="16"
                     data-zoom-title="<?= htmlspecialchars('#' . $aId . ' – ' . $aTitle, ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars((string)$alb['poster_fsk16'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="FSK16"
                         class="object-fit-cover d-block"
                         style="width: 86px; height: 48px;"
                         loading="lazy">
                    <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 start-0 m-1" style="font-size: 0.58rem; padding: 1px 4px;">16</span>
                    <span class="ec-thumb-zoom-hint"><i class="bi bi-zoom-in"></i></span>
                  </a>
                  <a href="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                     class="ec-thumb-wrap js-hover-zoom-thumb"
                     data-zoom-src="<?= htmlspecialchars((string)$alb['zoom_fsk18'], ENT_QUOTES, 'UTF-8') ?>"
                     data-zoom-fsk="18"
                     data-zoom-title="<?= htmlspecialchars('#' . $aId . ' – ' . $aTitle, ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars((string)$alb['poster_fsk18'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="FSK18"
                         class="object-fit-cover d-block"
                         style="width: 86px; height: 48px;"
                         loading="lazy">
                    <span class="badge bg-danger position-absolute bottom-0 start-0 m-1" style="font-size: 0.58rem; padding: 1px 4px;">18</span>
                    <span class="ec-thumb-zoom-hint"><i class="bi bi-zoom-in"></i></span>
                  </a>
                </div>
              </td>
              <td>
                <a href="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                   class="fw-semibold text-decoration-none text-body d-block mb-1">
                  <?= htmlspecialchars($aTitle, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div class="d-flex align-items-center gap-2 small text-muted" style="font-size: 0.74rem;">
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
                <?php if ((int)$alb['actor_id'] > 0): ?>
                  <a href="#/actor?id=<?= (int)$alb['actor_id'] ?>" class="text-decoration-none ec-text-primary fw-medium js-stop-row-click">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($aActor, ENT_QUOTES, 'UTF-8') ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted text-nowrap"><?= htmlspecialchars($onlineStr, ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <a href="<?= htmlspecialchars((string)$alb['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                     class="btn btn-sm btn-outline-kkc py-1 px-2 text-nowrap row-action-btn"
                     title="Foto-Album bearbeiten">
                    <i class="bi bi-pencil-square me-1"></i> Bearbeiten
                  </a>
                  <?php if (!empty($alb['can_delete'])): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger py-1 px-2 js-delete-photo-album-btn js-stop-row-click"
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

    <!-- Kein-Treffer-Hinweis bei Suche / Filter -->
    <div id="smart-table-empty" class="p-5 text-center text-muted d-none">
      <i class="bi bi-search fs-2 d-block mb-2 ec-text-primary"></i>
      <div class="fw-semibold text-body mb-1">Keine passenden Foto-Alben gefunden</div>
      <div class="small mb-3">Bitte passe deinen Suchbegriff oder den gewählten Status-Filter an.</div>
      <button type="button" id="smart-reset-filters-btn" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Filter &amp; Suche zurücksetzen
      </button>
    </div>

    <!-- Moderne Blätterfunktion (Pagination) & Einträge pro Seite -->
    <div class="kkc-pagination-bar" id="smart-pagination-bar">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="small text-muted" id="smart-pagination-info">
          Zeige 1 bis <?= min(25, (int)$countTotal) ?> von <?= (int)$countTotal ?> Foto-Alben
        </span>
        <div class="d-flex align-items-center gap-1">
          <label for="smart-page-size" class="small text-muted text-nowrap">Pro Seite:</label>
          <select id="smart-page-size" class="form-select form-select-sm py-0 ps-2 pe-4" style="width: auto; height: 28px; font-size: 0.78rem;">
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="999999">Alle</option>
          </select>
        </div>
      </div>

      <div class="d-flex align-items-center gap-1 flex-wrap" id="smart-pagination-buttons"></div>
    </div>
  </div>
</div>
<?php endif; ?>
