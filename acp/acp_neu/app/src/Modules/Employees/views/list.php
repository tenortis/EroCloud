<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, int> $kpi
 * @var array<int, array<string, mixed>> $employees
 * @var array<string, array<string, string>> $availableAreas
 * @var array<string, array<string, mixed>> $rolePresets
 * @var int $currentEmployeeId
 * @var string $currentEmployeeRule
 * @var array<string, mixed>|null $impersonator
 * @var bool $canImpersonate
 * @var array<string, string> $filters
 */

$canWrite       = (($currentEmployeeRule ?? 'write') === 'write');
$canImpersonate = !empty($canImpersonate);
$impersonator   = is_array($impersonator ?? null) ? $impersonator : null;
$origAdminId    = $impersonator ? abs((int)($impersonator['id'] ?? 0)) : 0;
$origAdminName  = $impersonator ? (string)($impersonator['username'] ?? 'Admin') : '';
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <?php if ($impersonator !== null): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 shadow-sm">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-person-badge-fill fs-5 text-warning"></i>
        <div class="small">
          <strong>Session-Übernahme aktiv:</strong> Du bist aktuell in der Session von <strong><?= View::e((string)($_SESSION['employee_username'] ?? '')) ?> (#<?= (int)$currentEmployeeId ?>)</strong> eingeloggt.
          Dein ursprünglicher Administrator-Account ist <strong><?= View::e($origAdminName) ?> (#<?= $origAdminId ?>)</strong>.
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-warning fw-semibold" onclick="App.Employees.stopImpersonation()">
        <i class="bi bi-arrow-return-left me-1"></i>Zurück zu <?= View::e($origAdminName) ?>
      </button>
    </div>
  <?php endif; ?>

  <!-- Seiten-Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div>
      <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-person-gear ec-text-primary"></i>
        <span>Mitarbeiter &amp; Admins</span>
      </h1>
      <p class="text-muted small mb-0">
        Verwaltung aller Administratoren, Moderatoren, Prüfer und ihrer Zugriffsrechte im ACP.
      </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="#/employee?id=0" class="btn btn-sm btn-primary-kkc fw-semibold">
        <i class="bi bi-person-plus-fill me-1"></i>Neuen Mitarbeiter / Admin anlegen
      </a>
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
      </button>
    </div>
  </div>

  <!-- 4 KPI-Kacheln -->
  <div class="row g-3 mb-4">
    <!-- KPI 1: Team-Mitglieder & Rollen -->
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">ACP Team-Accounts</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(2,132,199,0.12); color: #0284c7;">
            <i class="bi bi-people-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['total_employees'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small">
          <a href="#/employees?rule=write" class="text-decoration-none fw-semibold ec-text-primary">
            <i class="bi bi-shield-lock-fill me-1"></i>Admins: <?= (int)$kpi['admin_write_count'] ?>
          </a>
          <a href="#/employees?rule=read" class="text-decoration-none text-muted">
            <i class="bi bi-person-check me-1"></i>Moderatoren: <strong><?= (int)$kpi['mod_read_count'] ?></strong>
          </a>
        </div>
      </div>
    </div>

    <!-- KPI 2: Berechtigungs-Struktur -->
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Berechtigungs-Umfang</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(16,185,129,0.12); color: #10b981;">
            <i class="bi bi-key-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2 mb-1">
          <span class="h3 fw-bold text-success mb-0"><?= (int)$kpi['full_access_count'] ?></span>
          <span class="small text-muted">Vollzugriff (`all`)</span>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Bereichs-Rollen: <strong><?= (int)$kpi['custom_access_count'] ?></strong></span>
          <a href="#/employees?access=custom" class="text-decoration-none fw-semibold ec-text-primary">Filtern &rarr;</a>
        </div>
      </div>
    </div>

    <!-- KPI 3: Moderations- & Prüf-Leistung -->
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Geprüfte &amp; freigegebene Inhalte</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(245,158,11,0.12); color: #f59e0b;">
            <i class="bi bi-check2-all"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['total_reviews'], 0, ',', '.') ?></div>
        <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
          <span><i class="bi bi-film me-1"></i><?= number_format($kpi['movies_reviewed'], 0, ',', '.') ?> Filme</span>
          <span>&bull;</span>
          <span><i class="bi bi-images me-1"></i><?= number_format($kpi['albums_reviewed'], 0, ',', '.') ?> Alben</span>
          <span>&bull;</span>
          <span><i class="bi bi-person-vcard me-1"></i><?= number_format($kpi['coactors_reviewed'], 0, ',', '.') ?> IDs</span>
        </div>
      </div>
    </div>

    <!-- KPI 4: Audit-Log & Team-Aktivität -->
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Team-Aktivität (`employee_history`)</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(139,92,246,0.12); color: #8b5cf6;">
            <i class="bi bi-clock-history"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['actions_last_7d'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">in 7 Tagen</span></div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Protokolliert gesamt: <strong><?= number_format($kpi['total_history_logs'], 0, ',', '.') ?></strong></span>
          <a href="#/startseite" class="text-decoration-none fw-semibold ec-text-primary">Live-Log &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter- & Suchleiste -->
  <div class="card-kkc p-3 mb-4">
    <form id="employees-filter-form" onsubmit="return App.Employees.submitFilterForm(event);">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-4">
          <label class="form-label small fw-semibold mb-1">Volltextsuche (Name, ID, Bereich, IP, Notiz)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= View::e((string)$filters['q']) ?>" placeholder="Benutzername, Bereich oder IP suchen..." oninput="App.Employees.quickFilterTable(this.value)">
          </div>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">System-Rolle (`rule`)</label>
          <select class="form-select form-select-sm" name="rule" onchange="App.Employees.submitFilterForm(event)">
            <option value="all" <?= $filters['rule'] === 'all' ? 'selected' : '' ?>>Alle Rollen</option>
            <option value="write" <?= $filters['rule'] === 'write' ? 'selected' : '' ?>>Administratoren (`write`)</option>
            <option value="read" <?= $filters['rule'] === 'read' ? 'selected' : '' ?>>Moderatoren / Prüfer (`read`)</option>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-3">
          <label class="form-label small fw-semibold mb-1">Freigeschalteter ACP-Bereich (`access_area`)</label>
          <select class="form-select form-select-sm" name="access" onchange="App.Employees.submitFilterForm(event)">
            <option value="all" <?= $filters['access'] === 'all' ? 'selected' : '' ?>>Alle Berechtigungsstufen</option>
            <option value="full" <?= $filters['access'] === 'full' ? 'selected' : '' ?>>Vollzugriff (`all` – Super-Admin)</option>
            <option value="custom" <?= $filters['access'] === 'custom' ? 'selected' : '' ?>>Eingeschränkte Bereichs-Rollen</option>
            <optgroup label="Gezielter Fachbereich (inkl. Vollzugriff):">
              <?php foreach ($availableAreas as $areaKey => $areaMeta): ?>
                <option value="<?= View::e($areaKey) ?>" <?= $filters['access'] === $areaKey ? 'selected' : '' ?>>
                  <?= View::e($areaMeta['title']) ?> (`<?= View::e($areaKey) ?>`)
                </option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Sortierung</label>
          <select class="form-select form-select-sm" name="sort" onchange="App.Employees.submitFilterForm(event)">
            <option value="id_asc" <?= $filters['sort'] === 'id_asc' ? 'selected' : '' ?>>ID aufsteigend (Standard)</option>
            <option value="id_desc" <?= $filters['sort'] === 'id_desc' ? 'selected' : '' ?>>ID absteigend (Neueste)</option>
            <option value="username_asc" <?= $filters['sort'] === 'username_asc' ? 'selected' : '' ?>>Benutzername (A–Z)</option>
            <option value="activity_desc" <?= $filters['sort'] === 'activity_desc' ? 'selected' : '' ?>>Zuletzt aktiv zuerst</option>
            <option value="checks_desc" <?= $filters['sort'] === 'checks_desc' ? 'selected' : '' ?>>Meiste Prüfungen zuerst</option>
          </select>
        </div>

        <div class="col-6 col-md-2 col-xl-1 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-primary-kkc flex-grow-1" title="Filter anwenden">
            <i class="bi bi-funnel-fill"></i>
          </button>
          <a href="#/employees" class="btn btn-sm btn-outline-kkc" title="Filter zurücksetzen">
            <i class="bi bi-x-lg"></i>
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Mitarbeiter-Tabelle -->
  <div class="card-kkc overflow-hidden mb-4">
    <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
      <div class="small text-muted">
        Zeige <strong><?= count($employees) ?></strong> Team-Mitglieder
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="#/employee?id=0" class="btn btn-xs btn-outline-kkc py-1 px-2 small">
          <i class="bi bi-plus-lg me-1"></i>Mitarbeiter hinzufügen
        </a>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-muted">
          <tr>
            <th style="width: 75px;">ID</th>
            <th style="min-width: 210px;">Mitarbeiter / Account</th>
            <th style="width: 210px;">System-Rolle (`rule`)</th>
            <th>Freigeschaltete ACP-Bereiche (`access_area`)</th>
            <th class="text-center" style="width: 195px;">Prüf-Bilanz (Freigaben)</th>
            <th style="width: 220px;">Letzte Aktivität &amp; Login</th>
            <th class="text-end" style="width: 255px;">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($employees)): ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-people fs-2 d-block mb-2 opacity-50"></i>
                Keine Mitarbeiter für die gewählten Filterkriterien gefunden.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($employees as $emp): ?>
              <?php
                $eid = (int)$emp['id'];
                $searchHay = mb_strtolower(
                    '#' . $eid . ' ' .
                    $emp['username'] . ' ' .
                    $emp['rule_label'] . ' ' .
                    $emp['access_raw'] . ' ' .
                    $emp['notes'] . ' ' .
                    $emp['last_ip']
                );
              ?>
              <tr class="employee-list-row" id="employee-row-<?= $eid ?>" data-search="<?= View::e($searchHay) ?>">
                <td class="font-monospace fw-semibold text-muted">#<?= $eid ?></td>

                <!-- Mitarbeiter / Avatar -->
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle fw-bold d-flex align-items-center justify-content-center flex-shrink-0 <?= $emp['rule'] === 'write' ? 'ec-bg-primary-subtle ec-text-primary' : 'bg-secondary bg-opacity-10 text-secondary' ?>"
                         style="width: 38px; height: 38px; font-size: 0.85rem;">
                      <?= View::e((string)$emp['initials']) ?>
                    </div>
                    <div>
                      <div class="d-flex align-items-center flex-wrap gap-1">
                        <a href="#/employee?id=<?= $eid ?>" class="fw-bold text-decoration-none text-body">
                          <?= View::e((string)$emp['username']) ?>
                        </a>
                        <?php if (!empty($emp['is_self'])): ?>
                          <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" style="font-size: 0.68rem;">
                            <?= $impersonator !== null ? 'Aktive Session' : 'Du selbst' ?>
                          </span>
                        <?php elseif ($origAdminId > 0 && $eid === $origAdminId): ?>
                          <span class="badge bg-warning bg-opacity-15 text-dark border border-warning border-opacity-50 ms-1" style="font-size: 0.68rem;">
                            Dein Admin-Account
                          </span>
                        <?php endif; ?>
                      </div>
                      <?php if ($emp['notes'] !== ''): ?>
                        <div class="small text-muted text-truncate" style="max-width: 220px;" title="<?= View::e((string)$emp['notes']) ?>">
                          <i class="bi bi-journal-text me-1"></i><?= View::e(mb_strimwidth((string)$emp['notes'], 0, 42, '…')) ?>
                        </div>
                      <?php else: ?>
                        <div class="small text-muted">Keine Notiz hinterlegt</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>

                <!-- System-Rolle (write / read) -->
                <td>
                  <?php if ($emp['rule'] === 'write'): ?>
                    <span class="badge-kkc badge-kkc-info d-inline-flex align-items-center gap-1">
                      <i class="bi bi-shield-lock-fill"></i>Administrator (`write`)
                    </span>
                    <div class="small text-muted mt-1" style="font-size: 0.72rem;">Schreib-, Lösch- &amp; Team-Rechte</div>
                  <?php else: ?>
                    <span class="badge-kkc badge-kkc-neutral d-inline-flex align-items-center gap-1">
                      <i class="bi bi-person-check-fill"></i>Moderator (`read`)
                    </span>
                    <div class="small text-muted mt-1" style="font-size: 0.72rem;">Prüf- &amp; Moderationsrechte</div>
                  <?php endif; ?>
                </td>

                <!-- Freigeschaltete Bereiche -->
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($emp['area_badges'] as $badge): ?>
                      <?php if ($badge['key'] === 'all'): ?>
                        <span class="badge-kkc badge-kkc-success">
                          <i class="bi <?= View::e($badge['icon']) ?> me-1"></i><?= View::e($badge['title']) ?>
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
                          <i class="bi <?= View::e($badge['icon']) ?> me-1 ec-text-primary"></i><?= View::e($badge['title']) ?>
                        </span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </td>

                <!-- Prüf-Bilanz -->
                <td class="text-center">
                  <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded border small" style="background: var(--kkc-surface-hover);">
                    <span title="Freigegebene Filme"><i class="bi bi-film text-primary me-1"></i><strong><?= number_format((int)$emp['movies_cnt'], 0, ',', '.') ?></strong></span>
                    <span class="text-muted">|</span>
                    <span title="Freigegebene Fotoalben"><i class="bi bi-images text-info me-1"></i><strong><?= number_format((int)$emp['albums_cnt'], 0, ',', '.') ?></strong></span>
                    <span class="text-muted">|</span>
                    <span title="Geprüfte 18+ Ausweise"><i class="bi bi-person-badge text-success me-1"></i><strong><?= number_format((int)$emp['coactors_cnt'], 0, ',', '.') ?></strong></span>
                  </div>
                  <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                    <?= number_format((int)$emp['actions_30d'], 0, ',', '.') ?> Aktionen (30T) / <?= number_format((int)$emp['total_actions'], 0, ',', '.') ?> ges.
                  </div>
                </td>

                <!-- Letzte Aktivität & IP -->
                <td>
                  <?php if ($emp['last_action_de'] !== '-'): ?>
                    <div class="fw-semibold small"><?= View::e((string)$emp['last_action_de']) ?></div>
                    <div class="small text-muted text-truncate" style="max-width: 210px;" title="<?= View::e((string)$emp['last_desc']) ?>">
                      <?= View::e((string)$emp['last_desc']) ?>
                    </div>
                    <div class="small text-muted font-monospace" style="font-size: 0.72rem;">
                      IP: <?= View::e($emp['last_ip'] !== '' ? (string)$emp['last_ip'] : '-') ?> &bull; Logins: <?= number_format((int)$emp['login_count'], 0, ',', '.') ?>
                    </div>
                  <?php else: ?>
                    <span class="text-muted small">Noch keine Aktivität protokolliert</span>
                  <?php endif; ?>
                </td>

                <!-- Aktionen -->
                <td class="text-end">
                  <div class="d-inline-flex align-items-center gap-1">
                    <?php if ($canImpersonate): ?>
                      <?php if (!empty($emp['is_self'])): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                disabled
                                title="Du bist bereits in dieser Mitarbeiter-Session eingeloggt">
                          <i class="bi bi-person-check-fill me-1"></i>Eingeloggt
                        </button>
                      <?php elseif ($origAdminId > 0 && $eid === $origAdminId): ?>
                        <button type="button"
                                class="btn btn-sm btn-warning fw-semibold"
                                title="Zurück in deine eigene Admin-Session (<?= View::e($origAdminName) ?>) wechseln"
                                onclick="App.Employees.stopImpersonation()">
                          <i class="bi bi-arrow-return-left me-1"></i>Zurück
                        </button>
                      <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-primary-kkc"
                                title="Session übernehmen &amp; als <?= View::e((string)$emp['username']) ?> (#<?= $eid ?>) einloggen"
                                onclick="App.Employees.impersonateEmployee(<?= $eid ?>, '<?= View::e(addslashes((string)$emp['username'])) ?>')">
                          <i class="bi bi-box-arrow-in-right me-1"></i>Session übernehmen
                        </button>
                      <?php endif; ?>
                    <?php endif; ?>

                    <a href="#/employee?id=<?= $eid ?>" class="btn btn-sm btn-outline-kkc" title="Mitarbeiter bearbeiten &amp; Berechtigungen anpassen">
                      <i class="bi bi-pencil-square me-1"></i>Bearbeiten
                    </a>
                    <?php if (empty($emp['is_self']) && ($origAdminId === 0 || $eid !== $origAdminId) && $canWrite): ?>
                      <button type="button"
                              class="btn btn-sm btn-outline-danger"
                              title="Mitarbeiter-Account löschen"
                              onclick="App.Employees.deleteEmployee(<?= $eid ?>, '<?= View::e(addslashes((string)$emp['username'])) ?>')">
                        <i class="bi bi-trash3"></i>
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Du kannst deinen eigenen eingeloggten Account nicht löschen">
                        <i class="bi bi-shield-lock"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Info-Box: Rollen & Sicherheitskonzept -->
  <div class="card-kkc p-3">
    <div class="row g-3 align-items-center">
      <div class="col-12 col-lg-6">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-shield-lock-fill fs-5 ec-text-primary mt-1"></i>
          <div class="small">
            <strong>Unterschied zwischen `write` (Administrator) und `read` (Moderator / Prüfer):</strong><br>
            <span class="text-muted">
              Moderatoren (`read`) können in ihren freigeschalteten Bereichen Filme, Fotoalben, 18+ Ausweise und Profile prüfen, freigeben oder sperren (`active` / `blocked`).
              Das endgültige Löschen von Inhalten (`deleted`) sowie das Anlegen, Bearbeiten und Löschen von Mitarbeitern ist ausschließlich Administratoren (`write`) vorbehalten.
            </span>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-key-fill fs-5 text-success mt-1"></i>
          <div class="small">
            <strong>Granulare Bereichssteuerung (`access_area`):</strong><br>
            <span class="text-muted">
              Sowohl die linke ACP-Sidebar als auch jeder direkte API-Endpunkt (`Router::dispatch()`) prüfen in Echtzeit die zugewiesenen Bereichsschlüssel.
              Änderungen an Berechtigungen oder Passwörtern greifen sofort ohne Server-Neustart.
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
