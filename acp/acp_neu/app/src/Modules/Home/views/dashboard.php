<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var int $employeeId */
/** @var string $employeeUsername */
/** @var string $employeeRule */
/** @var array<string, int> $counts */
/** @var array<int, array<string, mixed>> $onlineUsers */
/** @var array<int, array<string, mixed>> $onlineActors */
/** @var string $adminNotes */
/** @var int $adminNotesEdit */
/** @var string $adminNotesEditorName */
/** @var string $employeeNotes */
/** @var array<int, array<string, mixed>> $historyItems */

$countUsersOnline   = count($onlineUsers);
$countActorsOnline  = count($onlineActors);
$countOnlineTotal   = $countUsersOnline + $countActorsOnline;
$openTasksTotal     = (int)($counts['new_actors'] ?? 0) + (int)($counts['new_banners'] ?? 0);
?>

<!-- Page Header & Admin Greeting -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge-kkc badge-kkc-info">
        <i class="bi bi-shield-check"></i> Admin-Dashboard
      </span>
      <?php if ($employeeRule !== ''): ?>
      <span class="badge-kkc badge-kkc-neutral">Rolle: <?= View::e($employeeRule) ?></span>
      <?php endif; ?>
    </div>
    <h1 class="h3 mb-1">Hallo <?= View::e($employeeUsername) ?>, willkommen im neuen EroCloud ACP!</h1>
    <p class="text-muted mb-0">
      Hier hast du alle offenen Content-Prüfungen, aktuell aktiven Nutzer und Darsteller, Notizen sowie das Aktions-Protokoll im direkten Überblick.
    </p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()" title="Dashboard-Daten aktualisieren">
      <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
    </button>
  </div>
</div>

<!-- ==========================================================================
     1. OBERE KPI-REIHE (4 Live-Kennzahlen-Kacheln)
     ========================================================================== -->
<div class="row g-3 mb-4">
  <!-- KPI 1: Inhalte in Prüfung -->
  <div class="col-xl-3 col-md-6">
    <a href="#/movies-checking" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box <?= (int)($counts['content_checking'] ?? 0) > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'ec-bg-primary-subtle ec-text-primary' ?>">
          <i class="bi bi-shield-exclamation"></i>
        </div>
        <div>
          <div class="kpi-val"><?= (int)($counts['content_checking'] ?? 0) ?></div>
          <div class="kpi-label">Inhalte in Prüfung</div>
          <small class="text-muted">
            Filme: <?= (int)($counts['movies_checking'] ?? 0) ?> · Alben: <?= (int)($counts['photo_albums_checking'] ?? 0) ?> · ID: <?= (int)($counts['co_actors_checking'] ?? 0) ?>
          </small>
        </div>
      </div>
    </a>
  </div>

  <!-- KPI 2: Content Online -->
  <div class="col-xl-3 col-md-6">
    <a href="#/movies-online" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
          <i class="bi bi-cloud-check-fill"></i>
        </div>
        <div>
          <div class="kpi-val"><?= number_format((int)($counts['content_online'] ?? 0), 0, ',', '.') ?></div>
          <div class="kpi-label">Content Online</div>
          <small class="text-success fw-semibold">
            <i class="bi bi-check-circle me-1"></i><?= number_format((int)($counts['movies_online'] ?? 0), 0, ',', '.') ?> Filme · <?= number_format((int)($counts['photo_albums_online'] ?? 0), 0, ',', '.') ?> Alben
          </small>
        </div>
      </div>
    </a>
  </div>

  <!-- KPI 3: Jetzt Online (letzte 10 Minuten) -->
  <div class="col-xl-3 col-md-6">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
        <i class="bi bi-activity"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)$countOnlineTotal ?></div>
        <div class="kpi-label">Aktiv (letzte 10 Min.)</div>
        <small class="text-muted">
          <?= (int)$countUsersOnline ?> Cloud-User · <?= (int)$countActorsOnline ?> Darsteller
        </small>
      </div>
    </div>
  </div>

  <!-- KPI 4: Offene Aufgaben & Profile -->
  <div class="col-xl-3 col-md-6">
    <a href="#/actors" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
          <i class="bi bi-person-bounding-box"></i>
        </div>
        <div>
          <div class="kpi-val"><?= (int)$openTasksTotal ?></div>
          <div class="kpi-label">Offene Profil-/Banner-Checks</div>
          <small class="text-muted">
            Neue Profile: <?= (int)($counts['new_actors'] ?? 0) ?> · Neue Banner: <?= (int)($counts['new_banners'] ?? 0) ?>
          </small>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- ==========================================================================
     2. SCHNELLZUGRIFF – CONTENT & COMPLIANCE
     ========================================================================== -->
<div class="card-kkc mb-4">
  <div class="card-kkc-header">
    <div class="card-title">
      <i class="bi bi-lightning-charge-fill ec-text-primary"></i>
      <span>Schnellzugriff – Content &amp; Compliance</span>
    </div>
    <span class="badge-kkc badge-kkc-neutral">Direktwahl</span>
  </div>
  <div class="card-kkc-body">
    <div class="row g-3">
      <div class="col-xl-3 col-sm-6">
        <a href="#/movies-checking" class="action-tile text-decoration-none h-100">
          <div class="action-tile-icon ec-bg-primary-subtle ec-text-primary">
            <i class="bi bi-film"></i>
          </div>
          <div>
            <div class="fw-bold text-body">Filme prüfen (<?= (int)($counts['movies_checking'] ?? 0) ?>)</div>
            <small class="text-muted">Neue Video-Uploads kontrollieren &amp; freigeben</small>
          </div>
        </a>
      </div>
      <div class="col-xl-3 col-sm-6">
        <a href="#/photo-albums-checking" class="action-tile text-decoration-none h-100">
          <div class="action-tile-icon ec-bg-primary-subtle ec-text-primary">
            <i class="bi bi-images"></i>
          </div>
          <div>
            <div class="fw-bold text-body">Fotoalben prüfen (<?= (int)($counts['photo_albums_checking'] ?? 0) ?>)</div>
            <small class="text-muted">Hochgeladene Bilderserien &amp; Cover sichten</small>
          </div>
        </a>
      </div>
      <div class="col-xl-3 col-sm-6">
        <a href="#/co-actors-checking" class="action-tile text-decoration-none h-100">
          <div class="action-tile-icon ec-bg-primary-subtle ec-text-primary">
            <i class="bi bi-person-badge"></i>
          </div>
          <div>
            <div class="fw-bold text-body">Drehpartner &amp; ID-Check (<?= (int)($counts['co_actors_checking'] ?? 0) ?>)</div>
            <small class="text-muted">Ausweise, Selfies &amp; Model-Releases verifizieren</small>
          </div>
        </a>
      </div>
      <div class="col-xl-3 col-sm-6">
        <a href="#/ai-compliance" class="action-tile text-decoration-none h-100">
          <div class="action-tile-icon ec-bg-primary-subtle ec-text-primary">
            <i class="bi bi-cpu"></i>
          </div>
          <div>
            <div class="fw-bold text-body">KI-Analyse &amp; BRAM</div>
            <small class="text-muted">Whisper-Transkripte, Gesichtsabgleich &amp; Compliance</small>
          </div>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     3. LIVE-ONLINE-ÜBERSICHT (Cloud-User & Darsteller in den letzten 10 Min.)
     ========================================================================== -->
<div class="row g-4 mb-4">
  <!-- Links: Cloud-User online -->
  <div class="col-lg-6">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-people-fill ec-text-primary"></i>
          <span><?= (int)$countUsersOnline ?> User waren in den letzten 10 Minuten online</span>
        </div>
        <span class="badge-kkc badge-kkc-info">Live (10 Min.)</span>
      </div>
      <div class="card-kkc-body p-0">
        <?php if (empty($onlineUsers)): ?>
        <div class="p-4 text-center text-muted small">
          <i class="bi bi-person-dash fs-4 d-block mb-1"></i>
          In den letzten 10 Minuten waren keine Cloud-User aktiv.
        </div>
        <?php else: ?>
        <div class="table-responsive" style="max-height: 320px;">
          <table class="table table-kkc align-middle mb-0">
            <thead>
              <tr>
                <th>Username</th>
                <th>Zeitpunkt</th>
                <th class="text-end">Coins</th>
                <th>Domain</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($onlineUsers as $u): ?>
              <tr>
                <td>
                  <a href="#/user?id=<?= (int)$u['id'] ?>" class="fw-semibold text-decoration-none ec-text-primary">
                    <?= View::e($u['username']) ?>
                  </a>
                </td>
                <td class="font-monospace small text-muted"><?= View::e($u['lastonline']) ?></td>
                <td class="text-end fw-semibold"><?= number_format((int)$u['amount_coins'], 0, ',', '.') ?></td>
                <td>
                  <a href="#/site?id=<?= View::e($u['domain']) ?>" class="small text-decoration-none">
                    <?= View::e($u['domain']) ?>
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
  </div>

  <!-- Rechts: Darsteller online -->
  <div class="col-lg-6">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-person-video2 ec-text-primary"></i>
          <span><?= (int)$countActorsOnline ?> Darsteller waren in den letzten 10 Minuten online</span>
        </div>
        <span class="badge-kkc badge-kkc-info">Live (10 Min.)</span>
      </div>
      <div class="card-kkc-body p-0">
        <?php if (empty($onlineActors)): ?>
        <div class="p-4 text-center text-muted small">
          <i class="bi bi-person-dash fs-4 d-block mb-1"></i>
          In den letzten 10 Minuten waren keine Darsteller online.
        </div>
        <?php else: ?>
        <div class="table-responsive" style="max-height: 320px;">
          <table class="table table-kkc align-middle mb-0">
            <thead>
              <tr>
                <th>Username</th>
                <th>Zeitpunkt</th>
                <th class="text-end">Merchant (MID)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($onlineActors as $act): ?>
              <tr>
                <td>
                  <a href="#/actor?id=<?= (int)$act['id'] ?>" class="fw-semibold text-decoration-none ec-text-primary">
                    <?= View::e($act['username']) ?>
                  </a>
                </td>
                <td class="font-monospace small text-muted"><?= View::e($act['lastonline']) ?></td>
                <td class="text-end">
                  <a href="#/merchant?id=<?= (int)$act['merchant_id'] ?>" class="badge-kkc badge-kkc-neutral text-decoration-none font-monospace">
                    MID #<?= (int)$act['merchant_id'] ?>
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
  </div>
</div>

<!-- ==========================================================================
     4. GEMEINSAME ADMIN-NOTIZEN & MEINE NOTIZEN (AJAX ohne Seiten-Reload)
     ========================================================================== -->
<div class="row g-4 mb-4">
  <!-- Links: Gemeinsame Notizen (mit Bearbeitungs-Sperre admin_notes_edit) -->
  <div class="col-lg-6">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-journal-text ec-text-primary"></i>
          <span>Gemeinsame Notizen (Team)</span>
        </div>
        <span id="admin-notes-status-badge" class="badge-kkc <?= $adminNotesEdit === 0 ? 'badge-kkc-neutral' : ($adminNotesEdit === $employeeId ? 'badge-kkc-info' : 'badge-kkc-warning') ?>">
          <?php if ($adminNotesEdit === 0): ?>
            <i class="bi bi-lock"></i> Schreibgeschützt
          <?php elseif ($adminNotesEdit === $employeeId): ?>
            <i class="bi bi-pencil-square"></i> Du bearbeitest gerade
          <?php else: ?>
            <i class="bi bi-person-lock"></i> Gesperrt durch <?= View::e($adminNotesEditorName) ?>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-kkc-body">
        <textarea id="input-admin-notes"
                  class="form-control font-monospace small mb-3"
                  rows="10"
                  placeholder="Gemeinsame Team-Notizen für alle Administratoren..."
                  <?= $adminNotesEdit === $employeeId ? '' : 'readonly' ?>><?= View::e($adminNotes) ?></textarea>

        <div class="d-flex align-items-center justify-content-end gap-2" id="admin-notes-actions">
          <?php if ($adminNotesEdit === 0): ?>
            <button type="button" class="btn btn-sm btn-primary-kkc" onclick="App.adminNotesAction('lock')">
              <i class="bi bi-pencil-square me-1"></i> Bearbeiten
            </button>
          <?php elseif ($adminNotesEdit === $employeeId): ?>
            <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.adminNotesAction('cancel')">
              <i class="bi bi-x-lg me-1"></i> Abbrechen
            </button>
            <button type="button" class="btn btn-sm btn-primary-kkc" onclick="App.adminNotesAction('save')">
              <i class="bi bi-check2-circle me-1"></i> Speichern
            </button>
          <?php else: ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
              <i class="bi bi-lock-fill me-1"></i> Die Notizen werden aktuell bearbeitet
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Rechts: Meine persönlichen Notizen -->
  <div class="col-lg-6">
    <div class="card-kkc h-100 mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-sticky-fill ec-text-primary"></i>
          <span>Meine Notizen (<?= View::e($employeeUsername) ?>)</span>
        </div>
        <span class="badge-kkc badge-kkc-neutral">Persönlich</span>
      </div>
      <div class="card-kkc-body">
        <form id="form-employee-notes" onsubmit="return App.saveEmployeeNotes(event);">
          <textarea id="input-employee-notes"
                    name="employee_notes"
                    class="form-control font-monospace small mb-3"
                    rows="10"
                    placeholder="Deine persönlichen Admin-Notizen..."><?= View::e($employeeNotes) ?></textarea>
          <div class="d-flex justify-content-end">
            <button type="submit" id="btn-save-employee-notes" class="btn btn-sm btn-primary-kkc">
              <i class="bi bi-check2-circle me-1"></i> Speichern
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     5. LETZTE 200 AKTIONEN (employee_history mit Echtzeit-Filter & Paginierung)
     ========================================================================== -->
<div class="card-kkc mb-0">
  <div class="card-kkc-header flex-wrap gap-2">
    <div class="card-title">
      <i class="bi bi-clock-history ec-text-primary"></i>
      <span>Letzte 200 Aktionen (Mitarbeiter-Protokoll)</span>
    </div>
    <div class="d-flex align-items-center gap-2 ms-auto">
      <div class="input-group input-group-sm" style="width: 260px;">
        <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="acp-history-search" class="form-control" placeholder="Aktionen filtern (User, IP, Text)...">
      </div>
      <select id="acp-history-limit" class="form-select form-select-sm" style="width: auto;">
        <option value="10" selected>10 Einträge</option>
        <option value="25">25 Einträge</option>
        <option value="50">50 Einträge</option>
        <option value="200">Alle 200</option>
      </select>
    </div>
  </div>
  <div class="card-kkc-body p-0">
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0" id="acp-history-table">
        <thead>
          <tr>
            <th style="width: 140px;">User</th>
            <th style="width: 170px;">Zeitpunkt</th>
            <th style="width: 140px;">IP</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($historyItems)): ?>
          <tr>
            <td colspan="4" class="text-center text-muted py-4">Keine Protokolleinträge vorhanden.</td>
          </tr>
          <?php else: ?>
            <?php foreach ($historyItems as $idx => $item): ?>
            <tr class="acp-history-row <?= $idx >= 10 ? 'd-none' : '' ?>"
                data-search="<?= View::e(mb_strtolower($item['username'] . ' ' . $item['datetime'] . ' ' . $item['ip'] . ' ' . $item['description'])) ?>">
              <td>
                <span class="badge-kkc badge-kkc-neutral">
                  <i class="bi bi-person-fill"></i> <?= View::e($item['username']) ?>
                </span>
              </td>
              <td class="font-monospace small text-muted"><?= View::e($item['datetime']) ?></td>
              <td class="font-monospace small text-muted"><?= View::e($item['ip']) ?></td>
              <td><?= View::e($item['description']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between small text-muted" style="border-color: var(--kkc-border) !important;">
      <span id="acp-history-info">Zeige die neuesten Einträge aus <code>employee_history</code></span>
      <span>Gesamt geladen: <?= count($historyItems) ?> Einträge</span>
    </div>
  </div>
</div>
