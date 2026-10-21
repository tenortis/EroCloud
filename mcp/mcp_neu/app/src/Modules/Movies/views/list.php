<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Filme in der Cloud (#/movies)
 * Alle UI-Texte strikt in persönlicher Du-Form (@master.md).
 * Keine Anzeige von Umsätzen, Provisionen oder Verkaufszahlen (@mcp-modernization-architecture.md).
 *
 * @var array $movies
 * @var array $actorsMap
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
      Verwalte hier alle hochgeladenen Filme, Vorschaubilder (FSK16 / FSK18), Preise und Kategorien. Bewege die Maus über ein Vorschaubild, um es vergrößert anzuzeigen.
    </p>
  </div>

  <div class="d-flex align-items-center gap-2">
    <a href="#/movie-upload" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-2">
      <i class="bi bi-cloud-arrow-up-fill"></i>
      <span>Neuen Film hochladen</span>
    </a>
  </div>
</div>

<!-- 4 klickbare KPI-Filter-Kacheln (ohne Umsatzzahlen) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card-kkc kpi-card is-filter-card active-kpi-filter h-100 mb-0 js-smart-kpi-filter" data-filter="all" title="Alle Filme anzeigen">
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
    <div class="card-kkc kpi-card is-filter-card h-100 mb-0 js-smart-kpi-filter" data-filter="online" title="Nur Online-Filme anzeigen">
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
    <div class="card-kkc kpi-card is-filter-card h-100 mb-0 js-smart-kpi-filter" data-filter="pending" title="Filme in Prüfung / Konvertierung anzeigen">
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
    <div class="card-kkc kpi-card is-filter-card h-100 mb-0 js-smart-kpi-filter" data-filter="rejected" title="Abgelehnte Filme anzeigen">
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
  <h2 class="h5 font-heading fw-bold mb-2">Du hast noch keine Filme hochgeladen</h2>
  <p class="text-muted small mx-auto mb-4" style="max-width: 520px;">
    Lade jetzt deinen ersten Film in die EroCloud hoch und verwalte deine Inhalte zentral an einem Ort.
  </p>
  <a href="#/movie-upload" class="btn btn-primary-kkc px-4">
    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Jetzt ersten Film hochladen
  </a>
</div>
<?php else: ?>

<!-- Interaktive Filter-, Sortier- & Tabellen-Kachel -->
<div class="card-kkc mb-0" id="smart-table-container" data-entity-label="Filmen">
  <div class="card-kkc-header flex-wrap gap-3">
    <!-- Status-Filter-Buttons -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-primary-kkc js-smart-status-btn active" data-filter="all">
        Alle (<?= (int)$countTotal ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc js-smart-status-btn" data-filter="online">
        Online (<?= (int)$countOnline ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-kkc js-smart-status-btn" data-filter="pending">
        In Bearbeitung (<?= (int)$countPending ?>)
      </button>
      <?php if ($countRejected > 0): ?>
      <button type="button" class="btn btn-sm btn-outline-kkc js-smart-status-btn" data-filter="rejected">
        Abgelehnt (<?= (int)$countRejected ?>)
      </button>
      <?php endif; ?>
    </div>

    <!-- Rechts: Darsteller-Filter (optional), Sortier-Auswahl & Live-Suche -->
    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
      <?php if (!empty($actorsMap) && count($actorsMap) > 1): ?>
      <select id="smart-actor-filter" class="form-select form-select-sm" style="width: auto; min-width: 165px;" title="Nach Darsteller-Profil filtern">
        <option value="all">Alle Darsteller (<?= count($actorsMap) ?>)</option>
        <?php foreach ($actorsMap as $aId => $aName): ?>
          <option value="<?= (int)$aId ?>"><?= htmlspecialchars((string)$aName, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>

      <!-- Sortier-Dropdown -->
      <div class="d-flex align-items-center gap-1">
        <label for="smart-sort-select" class="small text-muted text-nowrap d-none d-md-inline me-1">
          <i class="bi bi-arrow-down-up"></i> Sortieren:
        </label>
        <select id="smart-sort-select" class="form-select form-select-sm" style="width: auto; min-width: 205px;">
          <option value="id:desc" selected>ID: Neueste zuerst (# absteigend)</option>
          <option value="id:asc">ID: Älteste zuerst (# aufsteigend)</option>
          <option value="online:desc">Online ab: Neueste zuerst</option>
          <option value="online:asc">Online ab: Älteste zuerst</option>
          <option value="title:asc">Filmtitel: A bis Z</option>
          <option value="title:desc">Filmtitel: Z bis A</option>
        </select>
      </div>

      <!-- Live-Suche (inkl. ID, Titel, Darsteller, Datum) -->
      <div class="position-relative" style="min-width: 275px;">
        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
        <input type="text"
               id="smart-search-input"
               class="form-control form-control-sm ps-5 pe-4"
               placeholder="Nach #ID, Filmtitel oder Darsteller suchen..."
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
            <th class="kkc-sortable-th" data-sort-col="status" style="width: 130px;">
              Status <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="kkc-sortable-th is-sorted" data-sort-col="id" style="width: 90px;">
              ID <i class="bi bi-sort-numeric-down-alt sort-icon"></i>
            </th>
            <th style="width: 205px;">
              Vorschaubilder (FSK16 / FSK18)
            </th>
            <th class="kkc-sortable-th" data-sort-col="title">
              Filmtitel &amp; Details <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="kkc-sortable-th" data-sort-col="actor" style="width: 145px;">
              Darsteller <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="kkc-sortable-th" data-sort-col="online" style="width: 155px;">
              Online ab <i class="bi bi-arrow-down-up sort-icon"></i>
            </th>
            <th class="text-end" style="width: 120px;">Aktion</th>
          </tr>
        </thead>
        <tbody id="smart-table-body">
          <?php foreach ($movies as $m): ?>
          <?php
            $movieId    = (int)$m['id'];
            $titleStr   = (string)$m['title'];
            $actorStr   = (string)$m['actor_name'];
            $statusLbl  = (string)$m['status_label'];
            $onlineStr  = (string)$m['online_at'];
            $searchText = mb_strtolower('#' . $movieId . ' ' . $movieId . ' ' . $titleStr . ' ' . $actorStr . ' ' . $statusLbl . ' ' . $onlineStr, 'UTF-8');
          ?>
          <tr class="js-smart-row kkc-row-clickable"
              data-id="<?= $movieId ?>"
              data-status="<?= htmlspecialchars((string)$m['status_key'], ENT_QUOTES, 'UTF-8') ?>"
              data-actor-id="<?= (int)$m['actor_id'] ?>"
              data-actor="<?= htmlspecialchars(mb_strtolower($actorStr, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
              data-title="<?= htmlspecialchars(mb_strtolower($titleStr, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
              data-online="<?= (int)$m['online_at_ts'] ?>"
              data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
              data-edit-route="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>">
            <td>
              <span class="badge-kkc <?= htmlspecialchars((string)$m['status_badge'], ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi <?= htmlspecialchars((string)$m['status_icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <?= htmlspecialchars($statusLbl, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td>
              <span class="font-monospace fw-semibold text-muted">#<?= $movieId ?></span>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <!-- FSK 16 Vorschaubild mit Hover-Lupe -->
                <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                   class="ec-thumb-wrap js-hover-zoom-thumb"
                   data-zoom-src="<?= htmlspecialchars((string)$m['zoom_fsk16'], ENT_QUOTES, 'UTF-8') ?>"
                   data-zoom-fsk="16"
                   data-zoom-title="<?= htmlspecialchars('#' . $movieId . ' – ' . $titleStr, ENT_QUOTES, 'UTF-8') ?>">
                  <img src="<?= htmlspecialchars((string)$m['poster_fsk16'], ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK16"
                       class="object-fit-cover d-block"
                       style="width: 86px; height: 48px;"
                       loading="lazy">
                  <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 start-0 m-1" style="font-size: 0.58rem; padding: 1px 4px;">16</span>
                  <span class="ec-thumb-zoom-hint"><i class="bi bi-zoom-in"></i></span>
                </a>

                <!-- FSK 18 Vorschaubild mit Hover-Lupe -->
                <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>"
                   class="ec-thumb-wrap js-hover-zoom-thumb"
                   data-zoom-src="<?= htmlspecialchars((string)$m['zoom_fsk18'], ENT_QUOTES, 'UTF-8') ?>"
                   data-zoom-fsk="18"
                   data-zoom-title="<?= htmlspecialchars('#' . $movieId . ' – ' . $titleStr, ENT_QUOTES, 'UTF-8') ?>">
                  <img src="<?= htmlspecialchars((string)$m['poster_fsk18'], ENT_QUOTES, 'UTF-8') ?>"
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
              <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>" class="fw-semibold text-decoration-none text-body d-block mb-1">
                <?= htmlspecialchars($titleStr, ENT_QUOTES, 'UTF-8') ?>
              </a>
              <div class="d-flex align-items-center flex-wrap gap-2 text-muted" style="font-size: 0.74rem;">
                <?php if ($m['playtime_string'] !== ''): ?>
                  <span><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string)$m['playtime_string'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($m['resolution'] !== ''): ?>
                  <span><i class="bi bi-aspect-ratio me-1"></i><?= htmlspecialchars((string)$m['resolution'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if (!empty($m['price_summary'])): ?>
                  <span class="text-secondary"><i class="bi bi-tag me-1"></i><?= htmlspecialchars((string)$m['price_summary'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ((int)$m['actor_id'] > 0): ?>
                <a href="#/actor?id=<?= (int)$m['actor_id'] ?>" class="text-decoration-none ec-text-primary fw-medium js-stop-row-click">
                  <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($actorStr, ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <div class="small text-muted"><?= htmlspecialchars($onlineStr, ENT_QUOTES, 'UTF-8') ?></div>
              <?php if (!empty($m['is_scheduled'])): ?>
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 mt-1" style="font-size: 0.65rem;">
                  <i class="bi bi-calendar-event me-1"></i>Geplant
                </span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="<?= htmlspecialchars((string)$m['edit_route'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-kkc py-1 px-2 text-nowrap row-action-btn">
                <i class="bi bi-pencil-square me-1"></i> Bearbeiten
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Kein-Treffer-Hinweis bei Suche / Filter -->
    <div id="smart-table-empty" class="p-5 text-center text-muted d-none">
      <i class="bi bi-search fs-2 d-block mb-2 ec-text-primary"></i>
      <div class="fw-semibold text-body mb-1">Keine passenden Filme gefunden</div>
      <div class="small mb-3">Bitte passe deinen Suchbegriff oder den gewählten Status-Filter an.</div>
      <button type="button" id="smart-reset-filters-btn" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Filter &amp; Suche zurücksetzen
      </button>
    </div>

    <!-- Moderne Blätterfunktion (Pagination) & Einträge pro Seite -->
    <div class="kkc-pagination-bar" id="smart-pagination-bar">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="small text-muted" id="smart-pagination-info">
          Zeige 1 bis <?= min(25, (int)$countTotal) ?> von <?= (int)$countTotal ?> Filmen
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
