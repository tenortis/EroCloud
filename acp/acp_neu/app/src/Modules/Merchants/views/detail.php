<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, mixed> $merchant
 * @var int $prevId
 * @var int $nextId
 * @var array<int, array<string, mixed>> $actors
 * @var array<string, int> $movieSummary
 * @var array<int, array<string, mixed>> $recentMovies
 * @var array<int, array<string, mixed>> $coActors
 * @var array<int, array<string, mixed>> $history
 * @var bool $canImpersonate
 */

$mid            = (int)$merchant['id'];
$partnerId      = (string)$merchant['partner_id'];
$minLimit       = max(1, (int)$merchant['actors_count']);
$curLimit       = max($minLimit, (int)$merchant['min_profiles_limit']);
$canImpersonate = !empty($canImpersonate);
$merchDisplay   = $merchant['username'] !== '' ? (string)$merchant['username'] : ('Merchant #' . $mid);
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <!-- Seiten-Header mit Vor/Zurück-Navigation -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div class="d-flex align-items-start gap-3">
      <a href="#/merchants" class="btn btn-sm btn-outline-kkc mt-1">
        <i class="bi bi-arrow-left me-1"></i>Zur Übersicht
      </a>
      <div>
        <h1 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-person-workspace ec-text-primary"></i>
          <span><?= View::e($merchant['username'] !== '' ? (string)$merchant['username'] : ('Merchant #' . $mid)) ?></span>
        </h1>
        <div class="d-flex align-items-center gap-2">
          <span class="badge-kkc badge-kkc-info font-monospace">MID #<?= $mid ?></span>
          <?php if ($partnerId !== ''): ?>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace">
              PID: <?= View::e($partnerId) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php if ($canImpersonate): ?>
        <button type="button"
                class="btn btn-sm btn-primary-kkc fw-semibold"
                title="In die Partner-Session (MCP) von <?= View::e($merchDisplay) ?> (MID #<?= $mid ?>) einloggen"
                onclick="App.Merchants.impersonateMerchant(<?= $mid ?>, '<?= View::e(addslashes($merchDisplay)) ?>')">
          <i class="bi bi-box-arrow-in-right me-1"></i>Session übernehmen (Als Partner einloggen)
        </button>
      <?php endif; ?>
      <?php if (!empty($merchant['p4c_acp_url'])): ?>
        <a href="<?= View::e((string)$merchant['p4c_acp_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc">
          <i class="bi bi-box-arrow-up-right me-1"></i>In Pay4Coins öffnen
        </a>
      <?php endif; ?>
      <div class="btn-group btn-group-sm">
        <a href="<?= $prevId > 0 ? '#/merchant?id=' . $prevId : 'javascript:void(0)' ?>" class="btn btn-outline-kkc <?= $prevId <= 0 ? 'disabled' : '' ?>" title="Vorheriger Merchant">
          <i class="bi bi-chevron-left"></i> Vorheriger
        </a>
        <a href="<?= $nextId > 0 ? '#/merchant?id=' . $nextId : 'javascript:void(0)' ?>" class="btn btn-outline-kkc <?= $nextId <= 0 ? 'disabled' : '' ?>" title="Nächster Merchant">
          Nächster <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Schnell-KPI-Leiste zum Händler -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Darsteller-Profile / Limit</div>
        <div class="h4 fw-bold mb-0 mt-1">
          <?= (int)$merchant['actors_count'] ?> <span class="text-muted fs-6 fw-normal">von max. <span id="merch-kpi-limit"><?= $curLimit ?></span></span>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Videos (Online / Gesamt)</div>
        <div class="h4 fw-bold mb-0 mt-1 text-success">
          <?= (int)$movieSummary['online'] ?> <span class="text-muted fs-6 fw-normal">/ <?= (int)$movieSummary['total'] ?></span>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">18+ ID-Nachweise (Inhaber &amp; Drehpartner)</div>
        <div class="h4 fw-bold mb-0 mt-1">
          <?= count($coActors) ?>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Letzter Login &amp; Content-Richtlinien</div>
        <div class="fw-bold mt-1"><?= View::e((string)$merchant['last_ip_dt_de']) ?></div>
        <div class="small <?= $merchant['rules_accepted'] ? 'text-success' : 'text-warning' ?>">
          <i class="bi <?= $merchant['rules_accepted'] ? 'bi-check-circle-fill' : 'bi-exclamation-circle' ?> me-1"></i>
          Richtlinien: <?= View::e((string)$merchant['rules_dt_de']) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- 4 Bootstrap-Tabs -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold" id="tab-btn-merch-info" data-bs-toggle="tab" data-bs-target="#tab-merch-info" type="button" role="tab">
        <i class="bi bi-person-vcard me-1"></i>1. Händler-Infos &amp; Einstellungen
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-merch-actors" data-bs-toggle="tab" data-bs-target="#tab-merch-actors" type="button" role="tab">
        <i class="bi bi-person-bounding-box me-1"></i>2. Darsteller-Profile
        <span class="badge bg-primary rounded-pill ms-1"><?= count($actors) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-merch-content" data-bs-toggle="tab" data-bs-target="#tab-merch-content" type="button" role="tab">
        <i class="bi bi-collection-play me-1"></i>3. Filme &amp; 18+ Nachweise
        <span class="badge bg-secondary rounded-pill ms-1"><?= (int)$movieSummary['total'] ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-merch-history" data-bs-toggle="tab" data-bs-target="#tab-merch-history" type="button" role="tab">
        <i class="bi bi-clock-history me-1"></i>4. Login- &amp; Aktivitäts-Historie
        <span class="badge bg-secondary rounded-pill ms-1"><?= count($history) ?></span>
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ================================================================== -->
    <!-- TAB 1: Händler-Infos & Einstellungen                               -->
    <!-- ================================================================== -->
    <div class="tab-pane fade show active" id="tab-merch-info" role="tabpanel">
      <div class="row g-4">

        <!-- Linke Spalte: Benutzerdaten & API -->
        <div class="col-12 col-xl-7">
          <div class="card-kkc p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-person-badge me-2 ec-text-primary"></i>Pay4Coins-Stammdaten (Synchronisiert)
            </h2>

            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Interne EroCloud-ID (MID)</label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="#<?= $mid ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1 d-flex justify-content-between">
                  <span>Pay4Coins-Partner-ID (PID)</span>
                  <?php if (!empty($merchant['p4c_acp_url'])): ?>
                    <a href="<?= View::e((string)$merchant['p4c_acp_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none ec-text-primary">
                      In Pay4Coins aufrufen <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                  <?php endif; ?>
                </label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="<?= View::e($partnerId) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">P4C-Username</label>
                <input type="text" class="form-control form-control-sm fw-semibold bg-body-tertiary" value="<?= View::e((string)$merchant['username']) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1 d-flex justify-content-between">
                  <span>E-Mail-Adresse</span>
                  <?php if (!empty($merchant['email'])): ?>
                    <a href="mailto:<?= View::e((string)$merchant['email']) ?>" class="text-decoration-none ec-text-primary">
                      <i class="bi bi-envelope-at me-1"></i>E-Mail senden
                    </a>
                  <?php endif; ?>
                </label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['email']) ?>" readonly>
              </div>

              <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Anrede</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['salutation']) ?>" readonly>
              </div>

              <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Vorname (entschlüsselt)</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['firstname']) ?>" readonly>
              </div>

              <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Nachname (entschlüsselt)</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['surname']) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Geburtsdatum</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['birthday_de']) ?><?= $merchant['age'] ? ' (' . (int)$merchant['age'] . ' Jahre)' : '' ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Content-Richtlinien akzeptiert</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['rules_dt_de']) ?>" readonly>
              </div>
            </div>
          </div>

          <!-- API-Key & Letzte IP -->
          <div class="card-kkc p-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-key-fill me-2 ec-text-primary"></i>API-Schlüssel &amp; Netzwerk-Daten
            </h2>

            <div class="mb-3">
              <label class="form-label small text-muted mb-1">Persönlicher EroCloud API-Key (AES-entschlüsselt)</label>
              <div class="input-group input-group-sm">
                <input type="password" id="merchant-api-key-input" class="form-control font-monospace bg-body-tertiary" value="<?= View::e((string)$merchant['api_key']) ?>" readonly>
                <button type="button" class="btn btn-outline-kkc" onclick="App.Merchants.toggleApiKeyVisibility()" title="API-Key ein-/ausblenden">
                  <i class="bi bi-eye" id="merchant-api-key-icon"></i>
                </button>
                <button type="button" class="btn btn-outline-kkc" onclick="App.Merchants.copyApiKey()" title="API-Key in Zwischenablage kopieren">
                  <i class="bi bi-clipboard"></i> Kopieren
                </button>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Letzte bekannte IP-Adresse</label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="<?= View::e((string)$merchant['last_ip']) ?>" readonly>
              </div>
              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">IP geloggt am</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$merchant['last_ip_dt_de']) ?>" readonly>
              </div>
            </div>
          </div>
        </div>

        <!-- Rechte Spalte: Einstellungen (Mindest-/Maximalanzahl Darsteller-Profile) -->
        <div class="col-12 col-xl-5">
          <div class="card-kkc p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-sliders me-2 ec-text-primary"></i>Profil-Kontingent &amp; Einstellungen
            </h2>

            <form id="merchant-settings-form" onsubmit="return App.Merchants.saveSettings(event);">
              <input type="hidden" name="merchant_id" value="<?= $mid ?>">

              <div class="p-3 rounded border mb-3" style="background: var(--kkc-surface-hover);">
                <label class="form-label fw-semibold mb-1" for="merchant-min-profiles-select">
                  Wie viele Darsteller-Profile darf dieser Merchant anlegen?
                </label>
                <p class="small text-muted mb-3">
                  Aktuell hat dieser Händler bereits <strong><?= (int)$merchant['actors_count'] ?></strong> Darsteller-Profile angelegt. Das Limit kann nicht unter die bereits angelegte Profilanzahl gesenkt werden.
                </p>

                <select class="form-select mb-3" name="minimum_number_actor_profiles" id="merchant-min-profiles-select">
                  <?php
                    $maxDropdown = max(100, $curLimit);
                    for ($i = $minLimit; $i <= $maxDropdown; $i++):
                      $suffix = ($i === $minLimit && (int)$merchant['actors_count'] > 0) ? '   (...wurden bereits angelegt)' : '';
                  ?>
                    <option value="<?= $i ?>" <?= $i === $curLimit ? 'selected' : '' ?>>
                      <?= $i ?> Profile<?= View::e($suffix) ?>
                    </option>
                  <?php endfor; ?>
                </select>

                <button type="submit" id="btn-save-merchant-settings" class="btn btn-primary-kkc w-100">
                  <i class="bi bi-floppy-fill me-1"></i>Einstellungen speichern
                </button>
              </div>
            </form>

            <hr class="my-3">

            <h3 class="small fw-bold text-uppercase text-muted mb-2">Schnellzugriffe für diesen Händler</h3>
            <div class="d-grid gap-2">
              <?php if ($canImpersonate): ?>
                <button type="button"
                        class="btn btn-sm btn-primary-kkc text-start fw-semibold"
                        onclick="App.Merchants.impersonateMerchant(<?= $mid ?>, '<?= View::e(addslashes($merchDisplay)) ?>')">
                  <i class="bi bi-box-arrow-in-right me-2"></i>In die Partner-Session (MCP) von <?= View::e($merchDisplay) ?> einloggen
                </button>
              <?php endif; ?>
              <a href="#/actors?merchant_id=<?= $mid ?>" class="btn btn-sm btn-outline-kkc text-start">
                <i class="bi bi-person-bounding-box me-2 ec-text-primary"></i>Alle Darsteller-Profile von MID #<?= $mid ?> im Profil-Manager öffnen
              </a>
              <a href="#/co-actors-checking?q=<?= $mid ?>" class="btn btn-sm btn-outline-kkc text-start">
                <i class="bi bi-person-vcard-fill me-2 ec-text-primary"></i>Alle 18+ Ausweis- &amp; Drehpartner-Nachweise von MID #<?= $mid ?> prüfen
              </a>
              <a href="#/movies-online?q=<?= View::e((string)$merchant['username']) ?>" class="btn btn-sm btn-outline-kkc text-start">
                <i class="bi bi-film me-2 ec-text-primary"></i>Veröffentlichte Filme dieses Händlers durchsuchen
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 2: Darsteller-Profile dieses Händlers                          -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-merch-actors" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
          <div>
            <h2 class="h6 fw-bold mb-0">Angelegte Darsteller-Profile (<?= count($actors) ?>)</h2>
            <small class="text-muted">Klicke auf ein Profilbild für die Großansicht oder auf „Profil bearbeiten“ für das vollständige Profil-Cockpit.</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            <input type="text" class="form-control form-control-sm" style="width: 220px;" placeholder="Profile filtern..." oninput="App.Merchants.filterSubTable('merch-actor-row', this.value)">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th style="width: 80px;">ID</th>
                <th style="width: 150px;">FSK16 / FSK18</th>
                <th>Profilname &amp; Gruppe</th>
                <th class="text-center">Kategorie</th>
                <th class="text-center">Inhalte</th>
                <th class="text-center">Status</th>
                <th>Angelegt / Zuletzt Online</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($actors)): ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-5">
                    Dieser Händler hat noch keine Darsteller-Profile angelegt.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($actors as $a): ?>
                  <tr class="merch-actor-row" data-search="<?= View::e(mb_strtolower($a['id'] . ' ' . $a['username'] . ' ' . $a['status'], 'UTF-8')) ?>">
                    <td class="font-monospace fw-bold">
                      <a href="#/actor?id=<?= (int)$a['id'] ?>" class="text-decoration-none">#<?= (int)$a['id'] ?></a>
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <img src="<?= View::e((string)$a['avatar_fsk16']) ?>"
                             alt="FSK16"
                             class="rounded border"
                             style="width: 44px; height: 44px; object-fit: cover; cursor: pointer;"
                             onclick="App.openImageLightbox('<?= View::e((string)$a['avatar_fsk16_lg']) ?>', 'FSK16 – <?= View::e((string)$a['username']) ?>')">
                        <img src="<?= View::e((string)$a['avatar_fsk18']) ?>"
                             alt="FSK18"
                             class="rounded border"
                             style="width: 44px; height: 44px; object-fit: cover; cursor: pointer;"
                             onclick="App.openImageLightbox('<?= View::e((string)$a['avatar_fsk18_lg']) ?>', 'FSK18 – <?= View::e((string)$a['username']) ?>')">
                      </div>
                    </td>
                    <td>
                      <a href="#/actor?id=<?= (int)$a['id'] ?>" class="fw-bold text-body text-decoration-none d-block">
                        <?= View::e((string)$a['username']) ?>
                      </a>
                      <span class="small text-muted"><?= View::e((string)$a['is_displayed_as']) ?></span>
                    </td>
                    <td class="text-center">
                      <?php if ($a['main_category'] === 'porn'): ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Porn</span>
                      <?php elseif ($a['main_category'] === 'fetish'): ?>
                        <span class="badge bg-dark">Fetisch</span>
                      <?php else: ?>
                        <span class="text-muted small">-</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-secondary bg-opacity-10 text-body border me-1"><?= (int)$a['movies_count'] ?> Videos</span>
                      <span class="badge bg-secondary bg-opacity-10 text-body border"><?= (int)$a['albums_count'] ?> Alben</span>
                    </td>
                    <td class="text-center">
                      <?php if ($a['status'] === 'active'): ?>
                        <span class="badge-kkc badge-kkc-success">Aktiv</span>
                      <?php elseif ($a['status'] === 'blocked'): ?>
                        <span class="badge-kkc badge-kkc-warning">Gesperrt</span>
                      <?php elseif ($a['status'] === 'deleted'): ?>
                        <span class="badge-kkc badge-kkc-danger">Gelöscht</span>
                      <?php else: ?>
                        <span class="badge-kkc badge-kkc-neutral"><?= View::e((string)$a['status']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="small">
                      <div>Erstellt: <?= View::e((string)$a['create_date_de']) ?></div>
                      <div class="text-muted">Online: <?= View::e((string)$a['lastonline_de']) ?></div>
                    </td>
                    <td class="text-end">
                      <a href="#/actor?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-primary-kkc">
                        <i class="bi bi-pencil-square me-1"></i>Profil bearbeiten
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

    <!-- ================================================================== -->
    <!-- TAB 3: Filme & 18+ Nachweise                                       -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-merch-content" role="tabpanel">
      <div class="row g-4">

        <!-- 18+ Nachweise (co_actors) -->
        <div class="col-12 col-xl-5">
          <div class="card-kkc overflow-hidden h-100">
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--kkc-border) !important;">
              <h2 class="h6 fw-bold mb-0"><i class="bi bi-person-vcard-fill me-2 ec-text-primary"></i>18+ ID-Checks &amp; Drehpartner (<?= count($coActors) ?>)</h2>
              <a href="#/co-actors-checking?q=<?= $mid ?>" class="btn btn-xs btn-outline-kkc py-0.5 px-2 small">Im ID-Cockpit öffnen</a>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                  <tr>
                    <th>ID / Rolle</th>
                    <th>Name / Künstlername</th>
                    <th>Status</th>
                    <th>Geprüft</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($coActors)): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">Keine 18+ Nachweise hinterlegt.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($coActors as $ca): ?>
                      <tr>
                        <td class="font-monospace small">
                          #<?= (int)$ca['id'] ?>
                          <?php if ($ca['is_owner']): ?>
                            <span class="badge bg-primary ms-1">Inhaber</span>
                          <?php else: ?>
                            <span class="badge bg-secondary ms-1">Partner</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="fw-semibold small"><?= View::e($ca['creator_name'] !== '' ? (string)$ca['creator_name'] : '-') ?></div>
                          <div class="text-muted" style="font-size: 0.75rem;"><?= View::e((string)$ca['real_name']) ?></div>
                        </td>
                        <td>
                          <?php if ($ca['checked_status'] === 'verified'): ?>
                            <span class="badge-kkc badge-kkc-success">Verifiziert</span>
                          <?php elseif ($ca['checked_status'] === 'under_review'): ?>
                            <span class="badge-kkc badge-kkc-warning">Offen</span>
                          <?php elseif ($ca['checked_status'] === 'rejected'): ?>
                            <span class="badge-kkc badge-kkc-danger">Abgelehnt</span>
                          <?php else: ?>
                            <span class="badge-kkc badge-kkc-neutral"><?= View::e((string)$ca['checked_status']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                          <?= View::e((string)$ca['checked_on_de']) ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Neueste 30 Videos -->
        <div class="col-12 col-xl-7">
          <div class="card-kkc overflow-hidden h-100">
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--kkc-border) !important;">
              <h2 class="h6 fw-bold mb-0"><i class="bi bi-film me-2 ec-text-primary"></i>Neueste 30 Filme dieses Händlers</h2>
              <div class="small text-muted">
                Online: <strong><?= (int)$movieSummary['online'] ?></strong> |
                Prüfung: <strong><?= (int)$movieSummary['checking'] ?></strong> |
                Planung: <strong><?= (int)$movieSummary['drafts'] ?></strong>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                  <tr>
                    <th>ID</th>
                    <th>Titel &amp; Profil</th>
                    <th>Spielzeit / Preis</th>
                    <th>Status</th>
                    <th class="text-end">Aktion</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentMovies)): ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted py-4">Keine Videos vorhanden.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recentMovies as $mv): ?>
                      <tr>
                        <td class="font-monospace small">
                          #<?= (int)$mv['id'] ?>
                          <?php if ($mv['online_id'] > 0): ?>
                            <div class="text-success" style="font-size: 0.7rem;">On #<?= (int)$mv['online_id'] ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="fw-semibold small text-truncate" style="max-width: 280px;"><?= View::e((string)$mv['title']) ?></div>
                          <div class="text-muted" style="font-size: 0.75rem;"><?= View::e((string)$mv['actor_name']) ?> &bull; <?= View::e((string)$mv['date_de']) ?></div>
                        </td>
                        <td class="small">
                          <div><?= View::e((string)$mv['playtime']) ?></div>
                          <div class="text-muted"><?= View::e((string)$mv['price_fmt']) ?></div>
                        </td>
                        <td>
                          <?php if ($mv['is_online'] && $mv['status'] === 'active'): ?>
                            <span class="badge-kkc badge-kkc-success">Online</span>
                          <?php elseif ($mv['is_checking']): ?>
                            <span class="badge-kkc badge-kkc-warning">In Prüfung</span>
                          <?php elseif ($mv['status'] === 'deleted'): ?>
                            <span class="badge-kkc badge-kkc-danger">Gelöscht</span>
                          <?php else: ?>
                            <span class="badge-kkc badge-kkc-neutral"><?= View::e((string)$mv['status']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end">
                          <a href="<?= View::e((string)$mv['target_url']) ?>" class="btn btn-xs btn-outline-kkc py-0.5 px-2 small">
                            Öffnen
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
    <!-- TAB 4: Login- & Aktivitäts-Historie (merchant_history)             -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-merch-history" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
          <div>
            <h2 class="h6 fw-bold mb-0">Letzte 200 Händler-Aktivitäten (`merchant_history`)</h2>
            <small class="text-muted">Protokollierte Logins und Aktionen dieses Händlers im Partner-Cockpit (MCP).</small>
          </div>
          <input type="text" class="form-control form-control-sm" style="width: 240px;" placeholder="Historie durchsuchen..." oninput="App.Merchants.filterSubTable('merch-hist-row', this.value)">
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th style="width: 100px;">Eintrag #</th>
                <th style="width: 170px;">Zeitpunkt</th>
                <th>Aktion / Beschreibung</th>
                <th style="width: 160px;">IP-Adresse</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($history)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Keine Historieneinträge für diesen Händler vorhanden.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($history as $h): ?>
                  <tr class="merch-hist-row" data-search="<?= View::e(mb_strtolower($h['description'] . ' ' . $h['datetime_de'] . ' ' . $h['ip'], 'UTF-8')) ?>">
                    <td class="font-monospace small text-muted">#<?= (int)$h['id'] ?></td>
                    <td class="small fw-semibold"><?= View::e((string)$h['datetime_de']) ?></td>
                    <td class="small"><?= View::e((string)$h['description']) ?></td>
                    <td><code class="small text-muted"><?= View::e((string)$h['ip']) ?></code></td>
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
