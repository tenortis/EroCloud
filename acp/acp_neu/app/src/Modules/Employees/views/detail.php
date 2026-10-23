<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, mixed> $employee
 * @var int $prevId
 * @var int $nextId
 * @var array<string, array<string, string>> $availableAreas
 * @var array<string, array<string, mixed>> $rolePresets
 * @var array<int, array<string, mixed>> $recentMovies
 * @var array<int, array<string, mixed>> $recentAlbums
 * @var array<int, array<string, mixed>> $recentCoActors
 * @var array<int, array<string, mixed>> $historyEntries
 * @var int $currentEmployeeId
 * @var string $currentEmployeeRule
 */

$empId          = (int)$employee['id'];
$isNew          = !empty($employee['is_new']);
$isSelf         = !empty($employee['is_self']);
$isAllAccess    = !empty($employee['is_all_access']);
$activeAreas    = is_array($employee['access_areas'] ?? null) ? $employee['access_areas'] : [];
$canWrite       = ($currentEmployeeRule === 'write');
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

  <!-- Seiten-Header mit Navigation -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div class="d-flex align-items-start gap-3">
      <a href="#/employees" class="btn btn-sm btn-outline-kkc mt-1">
        <i class="bi bi-arrow-left me-1"></i>Zur Übersicht
      </a>
      <div>
        <h1 class="h4 fw-bold mb-1 d-flex flex-wrap align-items-center gap-2">
          <?php if ($isNew): ?>
            <i class="bi bi-person-plus-fill ec-text-primary"></i>
            <span>Neuen Mitarbeiter / Administrator / Moderator anlegen</span>
          <?php else: ?>
            <i class="bi bi-person-gear ec-text-primary"></i>
            <span><?= View::e((string)$employee['username']) ?></span>
            <?php if ($employee['rule'] === 'write'): ?>
              <span class="badge-kkc badge-kkc-info ms-1"><i class="bi bi-shield-lock-fill me-1"></i>Administrator (`write`)</span>
            <?php else: ?>
              <span class="badge-kkc badge-kkc-neutral ms-1"><i class="bi bi-person-check-fill me-1"></i>Moderator / Prüfer (`read`)</span>
            <?php endif; ?>
            <?php if ($isAllAccess): ?>
              <span class="badge-kkc badge-kkc-success"><i class="bi bi-check2-all me-1"></i>Vollzugriff (`all`)</span>
            <?php endif; ?>
          <?php endif; ?>
        </h1>
        <div class="d-flex align-items-center gap-2">
          <?php if ($isNew): ?>
            <span class="badge-kkc badge-kkc-success"><i class="bi bi-person-plus-fill me-1"></i>Neuanlage</span>
          <?php else: ?>
            <span class="badge-kkc badge-kkc-info font-monospace">Mitarbeiter #<?= $empId ?></span>
            <?php if ($isSelf): ?>
              <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                <i class="bi bi-person-check-fill me-1"></i>Dein eigener Account
              </span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php if (!$isNew): ?>
        <?php if ($canImpersonate && !$isSelf): ?>
          <?php if ($origAdminId > 0 && $empId === $origAdminId): ?>
            <button type="button"
                    class="btn btn-sm btn-warning fw-semibold"
                    onclick="App.Employees.stopImpersonation()">
              <i class="bi bi-arrow-return-left me-1"></i>Zurück in deine Admin-Session (<?= View::e($origAdminName) ?>)
            </button>
          <?php else: ?>
            <button type="button"
                    class="btn btn-sm btn-primary-kkc fw-semibold"
                    title="In die Session von <?= View::e((string)$employee['username']) ?> einloggen"
                    onclick="App.Employees.impersonateEmployee(<?= $empId ?>, '<?= View::e(addslashes((string)$employee['username'])) ?>')">
              <i class="bi bi-box-arrow-in-right me-1"></i>Session übernehmen (Als <?= View::e((string)$employee['username']) ?> einloggen)
            </button>
          <?php endif; ?>
        <?php endif; ?>
        <a href="#/employee?id=0" class="btn btn-sm btn-outline-kkc">
          <i class="bi bi-person-plus me-1"></i>Weiteren Account anlegen
        </a>
        <div class="btn-group btn-group-sm">
          <a href="<?= $prevId > 0 ? '#/employee?id=' . $prevId : 'javascript:void(0)' ?>" class="btn btn-outline-kkc <?= $prevId <= 0 ? 'disabled' : '' ?>">
            <i class="bi bi-chevron-left"></i> Vorheriger
          </a>
          <a href="<?= $nextId > 0 ? '#/employee?id=' . $nextId : 'javascript:void(0)' ?>" class="btn btn-outline-kkc <?= $nextId <= 0 ? 'disabled' : '' ?>">
            Nächster <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$canWrite): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-exclamation-triangle-fill fs-5"></i>
      <div>
        <strong>Nur-Lese-Ansicht:</strong> Dein Account verfügt über die Rolle <code>read</code> (Moderator).
        Das Anlegen, Bearbeiten oder Löschen von Team-Mitgliedern erfordert die Rolle <code>write</code> (Administrator).
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$isNew): ?>
    <!-- 4 Schnell-KPI-Kacheln zum bestehenden Mitarbeiter -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card-kkc p-3 h-100">
          <div class="text-muted small">Freigegebene Filme (`movies`)</div>
          <div class="h4 fw-bold ec-text-primary mb-0 mt-1">
            <i class="bi bi-film me-1"></i><?= number_format((int)$employee['movies_cnt'], 0, ',', '.') ?>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card-kkc p-3 h-100">
          <div class="text-muted small">Freigegebene Fotoalben (`photo_albums`)</div>
          <div class="h4 fw-bold text-info mb-0 mt-1">
            <i class="bi bi-images me-1"></i><?= number_format((int)$employee['albums_cnt'], 0, ',', '.') ?>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card-kkc p-3 h-100">
          <div class="text-muted small">Geprüfte 18+ Ausweise (`co_actors`)</div>
          <div class="h4 fw-bold text-success mb-0 mt-1">
            <i class="bi bi-person-badge me-1"></i><?= number_format((int)$employee['coactors_cnt'], 0, ',', '.') ?>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card-kkc p-3 h-100">
          <div class="text-muted small">Zuletzt aktiv &amp; Logins</div>
          <div class="fw-bold mt-1"><?= View::e((string)$employee['last_action_de']) ?></div>
          <div class="small text-muted">
            Logins: <strong><?= number_format((int)$employee['login_count'], 0, ',', '.') ?></strong>
            &bull; IP: <span class="font-monospace"><?= View::e((string)$employee['last_ip']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- 3 Bootstrap-Tabs (nur bei bestehendem Mitarbeiter) -->
    <ul class="nav nav-tabs mb-4" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="tab-btn-emp-settings" data-bs-toggle="tab" data-bs-target="#tab-emp-settings" type="button" role="tab">
          <i class="bi bi-sliders me-1"></i>1. Zugangsdaten, Rolle &amp; Berechtigungen
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-btn-emp-reviews" data-bs-toggle="tab" data-bs-target="#tab-emp-reviews" type="button" role="tab">
          <i class="bi bi-check2-circle me-1"></i>2. Geprüfte Inhalte (Filme, Alben &amp; 18+ IDs)
          <span class="badge bg-primary rounded-pill ms-1"><?= count($recentMovies) + count($recentAlbums) + count($recentCoActors) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-btn-emp-history" data-bs-toggle="tab" data-bs-target="#tab-emp-history" type="button" role="tab">
          <i class="bi bi-clock-history me-1"></i>3. Login- &amp; Aktivitäts-Historie (`employee_history`)
          <span class="badge bg-secondary rounded-pill ms-1"><?= number_format((int)$employee['total_actions'], 0, ',', '.') ?></span>
        </button>
      </li>
    </ul>
  <?php endif; ?>

  <div class="tab-content">

    <!-- ================================================================== -->
    <!-- TAB 1: Zugangsdaten, Rolle & Berechtigungs-Matrix                  -->
    <!-- ================================================================== -->
    <div class="tab-pane fade show active" id="tab-emp-settings" role="tabpanel">
      <form id="employee-edit-form" onsubmit="return App.Employees.saveEmployee(event);">
        <input type="hidden" name="employee_id" value="<?= $empId ?>">

        <div class="row g-4">

          <!-- Linke Spalte: Stammdaten, Passwort, System-Rolle & Notizen -->
          <div class="col-12 col-xl-5">

            <!-- Kachel 1: Login-Zugangsdaten -->
            <div class="card-kkc p-4 mb-4">
              <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
                <i class="bi bi-person-lock me-2 ec-text-primary"></i>ACP Login-Zugangsdaten
              </h2>

              <div class="mb-3">
                <label for="emp-username-input" class="form-label small fw-semibold mb-1">
                  Benutzername (`username`) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                  <input type="text"
                         id="emp-username-input"
                         name="username"
                         class="form-control fw-bold"
                         maxlength="25"
                         pattern="[A-Za-z0-9]{2,25}"
                         value="<?= View::e((string)$employee['username']) ?>"
                         placeholder="z.B. Martin, Tina, Daniel..."
                         required
                         <?= !$canWrite ? 'disabled' : '' ?>>
                </div>
                <div class="form-text small">
                  Erlaubt sind <strong>2 bis 25 Buchstaben und Zahlen</strong> (<code>A–Z</code>, <code>a–z</code>, <code>0–9</code>, keine Leer- oder Sonderzeichen).
                </div>
              </div>

              <hr class="my-3" style="border-color: var(--kkc-border);">

              <!-- Passwort-Bereich -->
              <div class="d-flex align-items-center justify-content-between mb-2">
                <label for="emp-password-input" class="form-label small fw-semibold mb-0">
                  <?= $isNew ? 'Passwort vergeben <span class="text-danger">*</span>' : 'Neues Passwort setzen (optional)' ?>
                </label>
                <?php if ($canWrite): ?>
                  <button type="button" class="btn btn-xs btn-outline-kkc py-0 px-2 small" onclick="App.Employees.generatePassword()">
                    <i class="bi bi-magic me-1"></i>Sicheres Passwort generieren
                  </button>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                  <input type="password"
                         id="emp-password-input"
                         name="new_password"
                         class="form-control font-monospace"
                         autocomplete="new-password"
                         placeholder="<?= $isNew ? 'Mindestens 6 Zeichen eingeben...' : 'Leer lassen, um Passwort nicht zu ändern' ?>"
                         <?= $isNew ? 'required' : '' ?>
                         <?= !$canWrite ? 'disabled' : '' ?>>
                  <button type="button" class="btn btn-outline-kkc" onclick="App.Employees.togglePasswordVisibility()" title="Passwort ein-/ausblenden">
                    <i id="emp-pw-eye-icon" class="bi bi-eye"></i>
                  </button>
                  <button type="button" class="btn btn-outline-kkc" onclick="App.Employees.copyPassword()" title="Passwort in die Zwischenablage kopieren">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </div>
              </div>

              <div class="mb-2">
                <label for="emp-password-confirm-input" class="form-label small fw-semibold mb-1">
                  Passwort wiederholen
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-check2-square"></i></span>
                  <input type="password"
                         id="emp-password-confirm-input"
                         name="new_password_confirm"
                         class="form-control font-monospace"
                         autocomplete="new-password"
                         placeholder="<?= $isNew ? 'Passwort zur Bestätigung wiederholen...' : 'Neues Passwort wiederholen (falls geändert)' ?>"
                         <?= !$canWrite ? 'disabled' : '' ?>>
                </div>
                <div class="form-text small">
                  Wird mit individuellem Salt per <code>SHA-512</code> (<code>hash_password()</code>) verschlüsselt gespeichert.
                </div>
              </div>
            </div>

            <!-- Kachel 2: System-Rolle (write vs. read) -->
            <div class="card-kkc p-4 mb-4">
              <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
                <i class="bi bi-shield-shaded me-2 ec-text-primary"></i>System-Rolle &amp; Berechtigungsstufe (`rule`)
              </h2>

              <div class="d-flex flex-column gap-2">
                <label class="p-3 rounded border d-flex align-items-start gap-3 cursor-pointer" style="background: var(--kkc-surface-hover);">
                  <input type="radio"
                         name="rule"
                         id="emp-rule-write"
                         value="write"
                         class="form-check-input mt-1 flex-shrink-0"
                         <?= $employee['rule'] === 'write' ? 'checked' : '' ?>
                         <?= !$canWrite ? 'disabled' : '' ?>>
                  <div>
                    <div class="fw-bold d-flex align-items-center gap-2">
                      <span>Administrator (`write`)</span>
                      <span class="badge-kkc badge-kkc-info">Vollberechtigung</span>
                    </div>
                    <div class="small text-muted mt-1">
                      Darf Inhalte nicht nur prüfen und sperren, sondern auch <strong>endgültig löschen (`deleted`)</strong> sowie Mitarbeiter-Accounts und Systemeinstellungen verwalten.
                    </div>
                  </div>
                </label>

                <label class="p-3 rounded border d-flex align-items-start gap-3 cursor-pointer" style="background: var(--kkc-surface-hover);">
                  <input type="radio"
                         name="rule"
                         id="emp-rule-read"
                         value="read"
                         class="form-check-input mt-1 flex-shrink-0"
                         <?= $employee['rule'] !== 'write' ? 'checked' : '' ?>
                         <?= (!$canWrite || $isSelf) ? 'disabled' : '' ?>>
                  <div>
                    <div class="fw-bold d-flex align-items-center gap-2">
                      <span>Moderator / Content-Prüfer (`read`)</span>
                      <span class="badge-kkc badge-kkc-neutral">Standard-Moderation</span>
                    </div>
                    <div class="small text-muted mt-1">
                      Darf in seinen zugewiesenen Bereichen Filme, Fotoalben, 18+ Ausweise und Profile prüfen, freigeben, bearbeiten oder sperren (<code>active</code> / <code>blocked</code>), aber keine Datensätze endgültig löschen.
                    </div>
                    <?php if ($isSelf): ?>
                      <div class="small text-primary mt-1">
                        <i class="bi bi-info-circle me-1"></i>Du kannst deinem eigenen Account die Administrator-Rolle nicht entziehen.
                      </div>
                    <?php endif; ?>
                  </div>
                </label>
              </div>
            </div>

            <!-- Kachel 3: Notizen -->
            <div class="card-kkc p-4">
              <h2 class="h6 fw-bold text-uppercase text-muted mb-2">
                <i class="bi bi-journal-text me-2 ec-text-primary"></i>Persönliche Mitarbeiter-Notizen (`notes`)
              </h2>
              <p class="small text-muted mb-2">
                Diese Notizen erscheinen auf dem Startseiten-Dashboard des jeweiligen Mitarbeiters.
              </p>
              <textarea name="notes"
                        rows="4"
                        class="form-control form-control-sm"
                        placeholder="Interne Notizen oder Aufgaben für diesen Mitarbeiter..."
                        <?= !$canWrite ? 'disabled' : '' ?>><?= View::e((string)$employee['notes']) ?></textarea>
            </div>

          </div>

          <!-- Rechte Spalte: Rollen-Vorlagen (Presets) & ACP-Bereichs-Matrix -->
          <div class="col-12 col-xl-7">
            <div class="card-kkc p-4 h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                  <div>
                    <h2 class="h6 fw-bold text-uppercase text-muted mb-1">
                      <i class="bi bi-grid-1x2-fill me-2 ec-text-primary"></i>Freigeschaltete ACP-Bereiche (`access_area`)
                    </h2>
                    <div class="small text-muted">
                      Bestimme genau, welche Menüpunkte und Module dieser Mitarbeiter im Admin-Control-Panel sehen und aufrufen darf.
                    </div>
                  </div>
                </div>

                <!-- 1-Klick Rollen-Vorlagen (Presets) -->
                <?php if ($canWrite): ?>
                  <div class="p-3 rounded border mb-4" style="background: var(--kkc-surface-hover);">
                    <div class="small fw-semibold text-muted mb-2">
                      <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Schnell-Vorlagen (1-Klick Rollen-Konfiguration):
                    </div>
                    <div class="row g-2">
                      <?php foreach ($rolePresets as $presetKey => $preset): ?>
                        <div class="col-12 col-sm-6">
                          <button type="button"
                                  class="btn btn-sm btn-outline-kkc w-100 text-start p-2 d-flex align-items-start gap-2"
                                  onclick='App.Employees.applyPreset(<?= json_encode($preset['rule']) ?>, <?= $preset['is_all'] ? 'true' : 'false' ?>, <?= json_encode($preset['areas']) ?>)'>
                            <i class="bi <?= View::e((string)$preset['icon']) ?> ec-text-primary mt-1"></i>
                            <div>
                              <div class="fw-bold small"><?= View::e((string)$preset['label']) ?></div>
                              <div class="text-muted" style="font-size: 0.7rem; white-space: normal; line-height: 1.25;">
                                <?= View::e((string)$preset['desc']) ?>
                              </div>
                            </div>
                          </button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Master-Switch: Vollzugriff (all) -->
                <div class="p-3 rounded border mb-3 <?= $isAllAccess ? 'border-success bg-success bg-opacity-10' : '' ?>" id="emp-all-access-box">
                  <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0 mb-0">
                    <label class="form-check-label cursor-pointer pe-3" for="emp-access-all-switch">
                      <div class="fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-shield-fill-check text-success fs-5"></i>
                        <span>Vollzugriff auf das gesamte ACP (`access_area = all`)</span>
                      </div>
                      <div class="small text-muted mt-1">
                        Wenn aktiviert, hat dieser Account automatisch Zugriff auf <strong>alle bestehenden und zukünftigen ACP-Bereiche</strong>.
                        Wenn deaktiviert, kannst du unten die gewünschten Einzelbereiche gezielt auswählen.
                      </div>
                    </label>
                    <input class="form-check-input ms-0 flex-shrink-0"
                           type="checkbox"
                           role="switch"
                           id="emp-access-all-switch"
                           name="access_all"
                           value="1"
                           style="width: 3rem; height: 1.5rem;"
                           onchange="App.Employees.onToggleAllAccess(this.checked)"
                           <?= $isAllAccess ? 'checked' : '' ?>
                           <?= !$canWrite ? 'disabled' : '' ?>>
                  </div>
                </div>

                <!-- Granulare Bereichs-Matrix (11 Module) -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="small fw-semibold text-muted">Einzelne Fachbereiche auswählen:</span>
                  <?php if ($canWrite): ?>
                    <div class="d-flex gap-2 small">
                      <a href="javascript:void(0)" class="text-decoration-none ec-text-primary" onclick="App.Employees.selectAllIndividualAreas(true)">Alle markieren</a>
                      <span class="text-muted">|</span>
                      <a href="javascript:void(0)" class="text-decoration-none text-muted" onclick="App.Employees.selectAllIndividualAreas(false)">Auswahl aufheben</a>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="row g-2" id="emp-individual-areas-grid" style="<?= $isAllAccess ? 'opacity: 0.55;' : '' ?>">
                  <?php foreach ($availableAreas as $areaKey => $areaMeta): ?>
                    <?php
                      $isChecked = $isAllAccess || in_array($areaKey, $activeAreas, true);
                    ?>
                    <div class="col-12 col-md-6">
                      <label class="p-2 px-3 rounded border d-flex align-items-start gap-2 h-100 cursor-pointer emp-area-card"
                             style="background: var(--kkc-surface-hover);">
                        <input type="checkbox"
                               class="form-check-input mt-1 flex-shrink-0 emp-area-checkbox"
                               name="access_areas[]"
                               value="<?= View::e($areaKey) ?>"
                               <?= $isChecked ? 'checked' : '' ?>
                               <?= !$canWrite ? 'disabled' : '' ?>
                               onchange="App.Employees.onIndividualAreaChange()">
                        <div class="w-100">
                          <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold small">
                              <i class="bi <?= View::e($areaMeta['icon']) ?> me-1 ec-text-primary"></i><?= View::e($areaMeta['title']) ?>
                            </span>
                            <code class="small text-muted" style="font-size: 0.7rem;"><?= View::e($areaKey) ?></code>
                          </div>
                          <div class="text-muted mt-1" style="font-size: 0.73rem; line-height: 1.25;">
                            <?= View::e($areaMeta['sub_items']) ?>
                          </div>
                        </div>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Speichern & Löschen Footer -->
              <?php if ($canWrite): ?>
                <div class="pt-4 mt-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
                  <div>
                    <?php if (!$isNew && !$isSelf): ?>
                      <button type="button"
                              class="btn btn-sm btn-outline-danger"
                              onclick="App.Employees.deleteEmployee(<?= $empId ?>, '<?= View::e(addslashes((string)$employee['username'])) ?>', true)">
                        <i class="bi bi-trash3 me-1"></i>Mitarbeiter-Account löschen
                      </button>
                    <?php endif; ?>
                  </div>

                  <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="#/employees" class="btn btn-sm btn-outline-kkc">Abbrechen</a>
                    <button type="submit" id="btn-save-employee" class="btn btn-sm btn-primary-kkc px-4 py-2 fw-semibold">
                      <i class="bi bi-floppy-fill me-1"></i><?= $isNew ? 'Mitarbeiter jetzt anlegen' : 'Änderungen speichern' ?>
                    </button>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </div>

        </div>
      </form>
    </div>

    <?php if (!$isNew): ?>
      <!-- ================================================================== -->
      <!-- TAB 2: Geprüfte Inhalte (Filme, Fotoalben & 18+ Ausweise)          -->
      <!-- ================================================================== -->
      <div class="tab-pane fade" id="tab-emp-reviews" role="tabpanel">
        <div class="row g-4">

          <!-- Zuletzt geprüfte Filme -->
          <div class="col-12 col-xl-6">
            <div class="card-kkc overflow-hidden h-100">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--kkc-border) !important;">
                <h3 class="h6 fw-bold mb-0">
                  <i class="bi bi-film me-2 ec-text-primary"></i>Zuletzt geprüfte / bearbeitete Filme (<?= count($recentMovies) ?>)
                </h3>
                <span class="badge bg-primary bg-opacity-10 text-primary">Gesamt freigegeben: <?= number_format((int)$employee['movies_cnt'], 0, ',', '.') ?></span>
              </div>
              <div class="table-responsive" style="max-height: 440px;">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light small text-muted">
                    <tr>
                      <th>ID</th>
                      <th>Filmtitel &amp; Profil</th>
                      <th>Status</th>
                      <th>Geprüft / Bearbeitet</th>
                      <th class="text-end">Link</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentMovies)): ?>
                      <tr>
                        <td colspan="5" class="text-center py-4 text-muted small">Noch keine geprüften Filme für diesen Mitarbeiter verzeichnet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($recentMovies as $rm): ?>
                        <tr>
                          <td class="font-monospace small text-muted">#<?= (int)$rm['id'] ?></td>
                          <td>
                            <div class="fw-semibold small text-truncate" style="max-width: 220px;" title="<?= View::e((string)$rm['title']) ?>">
                              <?= View::e($rm['title'] !== '' ? (string)$rm['title'] : ('Film #' . (int)$rm['id'])) ?>
                            </div>
                            <div class="small text-muted" style="font-size: 0.72rem;">
                              Profil: <a href="#/actor?id=<?= (int)$rm['actor_id'] ?>" class="text-decoration-none"><?= View::e($rm['actor_name'] !== '' ? (string)$rm['actor_name'] : ('#' . (int)$rm['actor_id'])) ?></a>
                            </div>
                          </td>
                          <td>
                            <?php if ($rm['status'] === 'active' && (int)$rm['released'] === 1): ?>
                              <span class="badge-kkc badge-kkc-success">Online</span>
                            <?php elseif ($rm['status'] === 'deleted'): ?>
                              <span class="badge-kkc badge-kkc-danger">Gelöscht</span>
                            <?php else: ?>
                              <span class="badge-kkc badge-kkc-warning"><?= View::e((string)$rm['status']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="small text-muted"><?= View::e((string)$rm['dt_de']) ?></td>
                          <td class="text-end">
                            <a href="#/movie-edit?id=<?= (int)$rm['id'] ?>" class="btn btn-xs btn-outline-kkc py-0 px-2">
                              <i class="bi bi-box-arrow-up-right"></i>
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

          <!-- Zuletzt geprüfte Fotoalben & 18+ Ausweise -->
          <div class="col-12 col-xl-6">
            <div class="card-kkc overflow-hidden mb-4">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--kkc-border) !important;">
                <h3 class="h6 fw-bold mb-0">
                  <i class="bi bi-images me-2 text-info"></i>Zuletzt geprüfte Fotoalben (<?= count($recentAlbums) ?>)
                </h3>
                <span class="badge bg-info bg-opacity-10 text-info">Gesamt freigegeben: <?= number_format((int)$employee['albums_cnt'], 0, ',', '.') ?></span>
              </div>
              <div class="table-responsive" style="max-height: 220px;">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light small text-muted">
                    <tr>
                      <th>ID</th>
                      <th>Albumtitel &amp; Fotos</th>
                      <th>Status</th>
                      <th>Datum</th>
                      <th class="text-end">Link</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentAlbums)): ?>
                      <tr>
                        <td colspan="5" class="text-center py-3 text-muted small">Noch keine geprüften Fotoalben verzeichnet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($recentAlbums as $ra): ?>
                        <tr>
                          <td class="font-monospace small text-muted">#<?= (int)$ra['id'] ?></td>
                          <td>
                            <div class="fw-semibold small text-truncate" style="max-width: 210px;">
                              <?= View::e($ra['title'] !== '' ? (string)$ra['title'] : ('Album #' . (int)$ra['id'])) ?>
                            </div>
                            <div class="small text-muted" style="font-size: 0.72rem;">
                              <?= (int)$ra['number_of_photos'] ?> Fotos &bull; <?= View::e((string)$ra['actor_name']) ?>
                            </div>
                          </td>
                          <td>
                            <?php if ($ra['status'] === 'active'): ?>
                              <span class="badge-kkc badge-kkc-success">Aktiv</span>
                            <?php else: ?>
                              <span class="badge-kkc badge-kkc-neutral"><?= View::e((string)$ra['status']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="small text-muted"><?= View::e((string)$ra['dt_de']) ?></td>
                          <td class="text-end">
                            <a href="#/photo-album-edit?id=<?= (int)$ra['id'] ?>" class="btn btn-xs btn-outline-kkc py-0 px-2">
                              <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- 18+ Ausweis-Prüfungen -->
            <div class="card-kkc overflow-hidden">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--kkc-border) !important;">
                <h3 class="h6 fw-bold mb-0">
                  <i class="bi bi-person-badge me-2 text-success"></i>Zuletzt geprüfte 18+ Ausweise (`co_actors`)
                </h3>
                <span class="badge bg-success bg-opacity-10 text-success">Gesamt geprüft: <?= number_format((int)$employee['coactors_cnt'], 0, ',', '.') ?></span>
              </div>
              <div class="table-responsive" style="max-height: 220px;">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light small text-muted">
                    <tr>
                      <th>ID</th>
                      <th>Person / Rolle</th>
                      <th>Prüfstatus</th>
                      <th>Geprüft am</th>
                      <th class="text-end">Link</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($recentCoActors)): ?>
                      <tr>
                        <td colspan="5" class="text-center py-3 text-muted small">Noch keine 18+ Ausweis-Prüfungen verzeichnet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($recentCoActors as $rc): ?>
                        <tr>
                          <td class="font-monospace small text-muted">#<?= (int)$rc['id'] ?></td>
                          <td>
                            <div class="fw-semibold small"><?= View::e($rc['creator_name'] !== '' ? (string)$rc['creator_name'] : ('Person #' . (int)$rc['id'])) ?></div>
                            <div class="small text-muted" style="font-size: 0.72rem;">
                              <?= $rc['is_owner'] ? 'Account-Inhaber' : 'Drehpartner' ?> (Merchant #<?= (int)$rc['merchant_id'] ?>)
                            </div>
                          </td>
                          <td>
                            <?php if ($rc['checked_status'] === 'verified'): ?>
                              <span class="badge-kkc badge-kkc-success">Verifiziert</span>
                            <?php elseif ($rc['checked_status'] === 'rejected'): ?>
                              <span class="badge-kkc badge-kkc-danger">Abgelehnt</span>
                            <?php else: ?>
                              <span class="badge-kkc badge-kkc-warning"><?= View::e((string)$rc['checked_status']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="small text-muted"><?= View::e((string)$rc['checked_on_de']) ?></td>
                          <td class="text-end">
                            <a href="#/co-actors-checking?q=<?= (int)$rc['id'] ?>" class="btn btn-xs btn-outline-kkc py-0 px-2">
                              <i class="bi bi-box-arrow-up-right"></i>
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

        </div>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 3: Login- & Aktivitäts-Historie (`employee_history`)           -->
      <!-- ================================================================== -->
      <div class="tab-pane fade" id="tab-emp-history" role="tabpanel">
        <div class="card-kkc overflow-hidden">
          <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
            <div>
              <h3 class="h6 fw-bold mb-0">
                <i class="bi bi-clock-history me-2 ec-text-primary"></i>Protokollierte Aktionen von <?= View::e((string)$employee['username']) ?>
              </h3>
              <div class="small text-muted">
                Zeige die neuesten <?= count($historyEntries) ?> von insgesamt <?= number_format((int)$employee['total_actions'], 0, ',', '.') ?> Einträgen aus <code>employee_history</code>
              </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
              <div class="input-group input-group-sm" style="width: 240px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Historie filtern (Aktion, IP)..." oninput="App.Employees.filterHistoryTable(this.value)">
              </div>
              <?php if ($canWrite && !empty($historyEntries)): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        onclick="App.Employees.clearEmployeeHistory(<?= $empId ?>, 'older_30d')"
                        title="Einträge älter als 30 Tage löschen">
                  <i class="bi bi-calendar-x me-1"></i>&gt; 30 Tage bereinigen
                </button>
              <?php endif; ?>
            </div>
          </div>

          <div class="table-responsive" style="max-height: 560px;">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light small text-uppercase text-muted">
                <tr>
                  <th style="width: 90px;">Log-ID</th>
                  <th style="width: 170px;">Zeitpunkt</th>
                  <th style="width: 140px;">Kategorie</th>
                  <th>Protokollierte Aktion (`description`)</th>
                  <th style="width: 145px;">IP-Adresse</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($historyEntries)): ?>
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                      Keine Einträge in <code>employee_history</code> für diesen Mitarbeiter vorhanden.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($historyEntries as $he): ?>
                    <?php
                      $hSearch = mb_strtolower($he['datetime_de'] . ' ' . $he['badge_label'] . ' ' . $he['description'] . ' ' . $he['ip']);
                    ?>
                    <tr class="emp-history-row" data-search="<?= View::e($hSearch) ?>">
                      <td class="font-monospace small text-muted">#<?= (int)$he['id'] ?></td>
                      <td class="small fw-semibold"><?= View::e((string)$he['datetime_de']) ?></td>
                      <td>
                        <span class="badge-kkc badge-kkc-<?= View::e((string)$he['badge_type']) ?>">
                          <?= View::e((string)$he['badge_label']) ?>
                        </span>
                      </td>
                      <td class="small"><?= View::e((string)$he['description']) ?></td>
                      <td class="font-monospace small text-muted"><?= View::e((string)$he['ip']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>

</div>
