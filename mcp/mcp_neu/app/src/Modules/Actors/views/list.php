<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Alle Darsteller-Profile (#/actors)
 * Alle UI-Texte strikt in persönlicher Du-Form (@master.md).
 *
 * @var array $actors
 * @var int   $countActors
 * @var int   $countActive
 * @var int   $countReview
 * @var int   $countLocked
 * @var int   $minActorProfiles
 * @var bool  $canCreateNew
 */
?>

<!-- Seitenkopf -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <h1 class="h4 font-heading fw-bold mb-1">
      <i class="bi bi-people-fill ec-text-primary me-2"></i>Meine Darsteller-Profile
    </h1>
    <p class="text-muted small mb-0">
      Verwalte hier deine Darsteller-Profile, Steckbrief-Angaben sowie deine FSK16- und FSK18-Profilbilder.
    </p>
  </div>

  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="badge ec-bg-primary-subtle ec-text-primary border px-3 py-2">
      <i class="bi bi-person-badge me-1"></i>
      <?= (int)$countActors ?> von <?= (int)$minActorProfiles ?> Profilen belegt
    </span>

    <?php if ($canCreateNew): ?>
    <a href="#/new-actor" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-1">
      <i class="bi bi-person-plus-fill"></i>
      <span>Neues Profil anlegen</span>
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI-Kacheln (Übersicht) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card-kkc mb-0 p-3 h-100 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-medium mb-1">Angelegte Profile</div>
        <div class="h4 font-heading fw-bold mb-0"><?= (int)$countActors ?> <span class="fs-6 text-muted fw-normal">/ <?= (int)$minActorProfiles ?></span></div>
      </div>
      <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
        <i class="bi bi-people-fill"></i>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc mb-0 p-3 h-100 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-medium mb-1">Aktive Profile</div>
        <div class="h4 font-heading fw-bold mb-0 text-success"><?= (int)$countActive ?></div>
      </div>
      <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
        <i class="bi bi-check-circle-fill"></i>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc mb-0 p-3 h-100 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-medium mb-1">In Prüfung</div>
        <div class="h4 font-heading fw-bold mb-0 text-warning"><?= (int)$countReview ?></div>
      </div>
      <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
        <i class="bi bi-hourglass-split"></i>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card-kkc mb-0 p-3 h-100 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-medium mb-1">Gesperrte Profile</div>
        <div class="h4 font-heading fw-bold mb-0 <?= $countLocked > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)$countLocked ?></div>
      </div>
      <div class="kpi-icon-box <?= $countLocked > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary' ?>">
        <i class="bi bi-shield-exclamation"></i>
      </div>
    </div>
  </div>
</div>

<?php if (empty($actors)): ?>
<!-- Leerzustand: Noch kein Darsteller-Profil vorhanden -->
<div class="card-kkc mb-0 p-5 text-center">
  <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
    <i class="bi bi-person-plus-fill"></i>
  </div>
  <h2 class="h5 font-heading fw-bold mb-2">Du hast noch kein Darsteller-Profil angelegt</h2>
  <p class="text-muted small mx-auto mb-4" style="max-width: 520px;">
    Um Filme und Fotoalben in deine EroCloud hochzuladen, lege bitte zunächst dein erstes Darsteller-Profil an.
  </p>
  <a href="#/new-actor" class="btn btn-primary-kkc px-4">
    <i class="bi bi-person-plus-fill me-1"></i> Jetzt erstes Profil anlegen
  </a>
</div>
<?php else: ?>

<!-- Darsteller-Profil Kachel-Grid -->
<div class="row g-4">
  <?php foreach ($actors as $actor): ?>
  <?php
    $actorId   = (int)$actor['id'];
    $editRoute = '#/actor?id=' . $actorId;
    $status    = (string)$actor['status'];
  ?>
  <div class="col-12 col-md-6 col-xxl-4">
    <div class="card-kkc mb-0 h-100 d-flex flex-column justify-content-between p-0 overflow-hidden">

      <!-- Kachel-Header: Name & Status -->
      <div class="p-3 border-bottom d-flex align-items-center justify-content-between gap-2" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
        <div class="d-flex align-items-center gap-2 text-truncate">
          <i class="bi bi-person-circle ec-text-primary fs-5"></i>
          <div class="text-truncate">
            <a href="<?= htmlspecialchars($editRoute, ENT_QUOTES, 'UTF-8') ?>" class="fw-bold text-decoration-none text-body text-truncate d-block">
              <?= htmlspecialchars((string)$actor['username'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <div class="text-muted" style="font-size: 0.72rem;">Profil-ID: #<?= $actorId ?></div>
          </div>
        </div>

        <?php if ($status === 'active'): ?>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
            <i class="bi bi-check-circle-fill me-1"></i>Aktiv
          </span>
        <?php elseif ($status === 'inactive'): ?>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" title="Das Profil wird durch unser Support-Team geprüft.">
            <i class="bi bi-hourglass-split me-1"></i>Wird geprüft
          </span>
        <?php elseif ($status === 'locked'): ?>
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
            <i class="bi bi-exclamation-octagon-fill me-1"></i>Gesperrt
          </span>
        <?php endif; ?>
      </div>

      <!-- Kachel-Body: FSK16/FSK18 Bilder & Kurz-Steckbrief -->
      <div class="p-3 flex-grow-1">

        <?php if ($status === 'locked' && $actor['locked_reason'] !== ''): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3">
          <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Grund der Sperrung:</div>
          <div><?= htmlspecialchars((string)$actor['locked_reason'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php elseif ($status === 'inactive'): ?>
        <div class="alert alert-warning py-2 px-3 small mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-info-circle-fill"></i>
          <span>Dieses Profil wird aktuell durch unser Support-Team geprüft.</span>
        </div>
        <?php endif; ?>

        <!-- Dual-Vorschau: FSK16 & FSK18 Profilbild -->
        <div class="row g-2 mb-3">
          <!-- FSK 16 -->
          <div class="col-6">
            <div class="border rounded p-2 text-center h-100" style="background-color: var(--kkc-bg); border-color: var(--kkc-border) !important;">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="badge bg-secondary bg-opacity-10 text-body border" style="font-size: 0.68rem;">FSK 16</span>
                <?php if ($actor['fsk16_url'] !== ''): ?>
                  <i class="bi bi-check-circle-fill text-success small" title="FSK16-Bild vorhanden"></i>
                <?php else: ?>
                  <i class="bi bi-exclamation-circle-fill text-warning small" title="Bitte lade ein FSK16-Bild hoch"></i>
                <?php endif; ?>
              </div>
              <?php if ($actor['fsk16_url'] !== ''): ?>
                <a href="<?= htmlspecialchars($editRoute, ENT_QUOTES, 'UTF-8') ?>" class="d-block">
                  <img src="<?= htmlspecialchars((string)$actor['fsk16_url'], ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK16"
                       class="rounded object-fit-cover border"
                       style="width: 100%; height: 145px;">
                </a>
              <?php else: ?>
                <a href="<?= htmlspecialchars($editRoute, ENT_QUOTES, 'UTF-8') ?>"
                   class="d-flex flex-column align-items-center justify-content-center rounded border text-decoration-none text-muted"
                   style="width: 100%; height: 145px; border-style: dashed !important;">
                  <i class="bi bi-camera fs-4 mb-1"></i>
                  <span style="font-size: 0.72rem;">Bild fehlt</span>
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- FSK 18 -->
          <div class="col-6">
            <div class="border rounded p-2 text-center h-100" style="background-color: var(--kkc-bg); border-color: var(--kkc-border) !important;">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle" style="font-size: 0.68rem;">FSK 18</span>
                <?php if ($actor['fsk18_url'] !== ''): ?>
                  <i class="bi bi-check-circle-fill text-success small" title="FSK18-Bild vorhanden"></i>
                <?php else: ?>
                  <i class="bi bi-exclamation-circle-fill text-warning small" title="Bitte lade ein FSK18-Bild hoch"></i>
                <?php endif; ?>
              </div>
              <?php if ($actor['fsk18_url'] !== ''): ?>
                <a href="<?= htmlspecialchars($editRoute, ENT_QUOTES, 'UTF-8') ?>" class="d-block">
                  <img src="<?= htmlspecialchars((string)$actor['fsk18_url'], ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK18"
                       class="rounded object-fit-cover border"
                       style="width: 100%; height: 145px;">
                </a>
              <?php else: ?>
                <a href="<?= htmlspecialchars($editRoute, ENT_QUOTES, 'UTF-8') ?>"
                   class="d-flex flex-column align-items-center justify-content-center rounded border text-decoration-none text-muted"
                   style="width: 100%; height: 145px; border-style: dashed !important;">
                  <i class="bi bi-camera fs-4 mb-1"></i>
                  <span style="font-size: 0.72rem;">Bild fehlt</span>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Kurz-Steckbrief Merkmale -->
        <div class="d-flex flex-wrap gap-1 mb-3">
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= htmlspecialchars((string)$actor['gender_label'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= (int)$actor['age'] ?> Jahre
          </span>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= (int)$actor['body_height'] ?> cm
          </span>
          <?php if ($actor['hair_color'] !== ''): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            Haare: <?= htmlspecialchars((string)$actor['hair_color'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php endif; ?>
          <?php if ($actor['cup_size'] !== ''): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            BH: <?= htmlspecialchars((string)$actor['cup_size'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php endif; ?>
        </div>

        <!-- Zugeordneter Content -->
        <div class="d-flex align-items-center justify-content-between small text-muted pt-2 border-top" style="border-color: var(--kkc-border) !important;">
          <span>
            <i class="bi bi-film me-1 ec-text-primary"></i>
            <strong><?= (int)$actor['movies_count'] ?></strong> Filme
          </span>
          <span>
            <i class="bi bi-images me-1 ec-text-primary"></i>
            <strong><?= (int)$actor['albums_count'] ?></strong> Fotoalben
          </span>
        </div>
      </div>

      <!-- Kachel-Footer: Aktions-Button -->
      <div class="p-3 border-top" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
        <a href="<?= htmlspecialchars($editRoute, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-primary-kkc w-100 d-flex align-items-center justify-content-center gap-2">
          <i class="bi bi-pencil-square"></i>
          <span>Profil &amp; Bilder bearbeiten</span>
        </a>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
