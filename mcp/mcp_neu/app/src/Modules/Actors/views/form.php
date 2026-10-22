<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Darsteller-Profil Formular (Neuanlage & Bearbeitung)
 * Alle UI-Texte strikt in persönlicher Du-Form (@master.md).
 *
 * @var string $mode             'create' oder 'edit'
 * @var array  $actor            Darsteller-Datensatz
 * @var array  $defs             Auswahl-Definitionen aus ActorsController::getFieldDefinitions()
 * @var int    $countActors      Aktuelle Anzahl der Profile des Partners
 * @var int    $minActorProfiles Maximale Anzahl erlaubter Profile
 */

$isEdit    = ($mode === 'edit');
$actorId   = (int)($actor['id'] ?? 0);
$username  = (string)($actor['username'] ?? '');
$status    = (string)($actor['status'] ?? 'inactive');
$backRoute = ($countActors > 1) ? '#/actors' : '#/startseite';
?>

<!-- Seitenkopf -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?= htmlspecialchars($backRoute, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-kkc" title="Zurück">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h1 class="h4 font-heading fw-bold mb-0">
          <?php if ($isEdit): ?>
            Profil bearbeiten: <span class="ec-text-primary"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
          <?php else: ?>
            Neues Darsteller-Profil anlegen
          <?php endif; ?>
        </h1>

        <?php if ($isEdit): ?>
          <?php if ($status === 'active'): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
              <i class="bi bi-check-circle-fill me-1"></i>Aktiv
            </span>
          <?php elseif ($status === 'inactive'): ?>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Das Profil wird durch unser Support-Team geprüft.">
              <i class="bi bi-hourglass-split me-1"></i>Wird geprüft
            </span>
          <?php elseif ($status === 'locked' || $status === 'blocked'): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
              <i class="bi bi-exclamation-octagon-fill me-1"></i>Gesperrt
            </span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <p class="text-muted small mb-0">
        <?php if ($isEdit): ?>
          Profil-ID #<?= $actorId ?> · Zugeordneter Content: <strong><?= (int)$actor['movies_count'] ?></strong> Filme, <strong><?= (int)$actor['albums_count'] ?></strong> Fotoalben
        <?php else: ?>
          Lege hier ein neues Darsteller-Profil an (<?= (int)$countActors ?> von <?= (int)$minActorProfiles ?> Profilen belegt).
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="d-flex align-items-center gap-2">
    <?php if ($countActors > 1): ?>
    <a href="#/actors" class="btn btn-sm btn-outline-kkc">
      <i class="bi bi-people me-1"></i> Alle Profile
    </a>
    <?php endif; ?>
    <button type="submit" form="form-actor-profile" class="btn btn-sm btn-primary-kkc px-3">
      <i class="bi bi-check2-circle me-1"></i>
      <?= $isEdit ? 'Änderungen speichern' : 'Profil jetzt anlegen' ?>
    </button>
  </div>
</div>

<!-- Status-Hinweise (bei Prüfung oder Sperrung im Edit-Modus) -->
<?php if ($isEdit && ($status === 'locked' || $status === 'blocked')): ?>
<div class="alert alert-danger d-flex align-items-start gap-3 p-3 mb-4 shadow-sm">
  <i class="bi bi-exclamation-octagon-fill fs-4 text-danger flex-shrink-0"></i>
  <div>
    <div class="fw-bold">Dieses Darsteller-Profil wurde gesperrt</div>
    <div class="small">
      <?= $actor['locked_reason'] !== ''
          ? htmlspecialchars((string)$actor['locked_reason'], ENT_QUOTES, 'UTF-8')
          : 'Bitte wende dich bei Rückfragen an unseren Support.' ?>
    </div>
  </div>
</div>
<?php elseif ($isEdit && $status === 'inactive'): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 py-2 px-3 mb-4 small">
  <i class="bi bi-hourglass-split fs-5 text-warning flex-shrink-0"></i>
  <div>
    <strong>Hinweis:</strong> Dein Profil befindet sich aktuell in der Prüfung durch unser Support-Team. Du kannst deine Angaben und Profilbilder jederzeit bearbeiten und ergänzen.
  </div>
</div>
<?php endif; ?>

<?php if ($isEdit): ?>
<!-- ==========================================================================
     SEKTION 1: PROFILBILDER (FSK16 & FSK18) - Direkt-Upload per AJAX
     ========================================================================== -->
<div class="row g-4 mb-4">
  <!-- FSK 16 Profilbild -->
  <div class="col-12 col-lg-6">
    <div class="card-kkc p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 font-heading fw-bold mb-0">
          <i class="bi bi-person-bounding-box ec-text-primary me-2"></i>Profilbild (FSK16)
        </h2>
        <span class="badge bg-secondary bg-opacity-10 text-body border">Jugendschutzkonform</span>
      </div>

      <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3">
        <!-- Bild-Vorschau -->
        <div class="position-relative flex-shrink-0 text-center" style="width: 140px;">
          <div id="actor-img-wrap-16" class="rounded border overflow-hidden d-flex align-items-center justify-content-center" style="width: 140px; height: 172px; background-color: var(--kkc-bg); border-color: var(--kkc-border) !important;">
            <?php if ($actor['fsk16_url'] !== ''): ?>
              <img id="actor-img-preview-16" src="<?= htmlspecialchars((string)$actor['fsk16_url'], ENT_QUOTES, 'UTF-8') ?>" alt="FSK16 Profilbild" class="w-100 h-100 object-fit-cover">
            <?php else: ?>
              <div id="actor-img-placeholder-16" class="text-muted text-center p-2">
                <i class="bi bi-camera fs-2 d-block mb-1"></i>
                <span style="font-size: 0.75rem;">Kein FSK16-Bild</span>
              </div>
              <img id="actor-img-preview-16" src="" alt="FSK16 Profilbild" class="w-100 h-100 object-fit-cover d-none">
            <?php endif; ?>
          </div>
        </div>

        <!-- Upload-Steuerung & Hinweis -->
        <div class="flex-grow-1">
          <div class="alert alert-light border py-2 px-3 small text-muted mb-3" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
            <i class="bi bi-shield-check ec-text-primary me-1"></i>
            Auf diesem Bild dürfen <strong>keine primären Geschlechtsmerkmale</strong> zu sehen sein. Brustwarzen müssen ebenfalls verdeckt sein.
          </div>

          <label for="actor-img-input-16" class="btn btn-sm btn-primary-kkc d-inline-flex align-items-center gap-2 cursor-pointer mb-2">
            <i class="bi bi-cloud-arrow-up-fill"></i>
            <span id="actor-img-btn-text-16">FSK16-Bild auswählen &amp; hochladen</span>
          </label>
          <input type="file" id="actor-img-input-16" class="d-none actor-image-upload-input" data-actor-id="<?= $actorId ?>" data-fsk="16" accept=".jpg,.jpeg,.png,image/jpeg,image/png">

          <div class="text-muted" style="font-size: 0.72rem;">
            Erlaubte Formate: <strong>JPG, JPEG, PNG</strong> · Max. Dateigröße: <strong>5 MB</strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- FSK 18 Profilbild -->
  <div class="col-12 col-lg-6">
    <div class="card-kkc p-4 h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 font-heading fw-bold mb-0">
          <i class="bi bi-fire text-danger me-2"></i>Profilbild (FSK18)
        </h2>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">FSK18 Erwachsenenbereich</span>
      </div>

      <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3">
        <!-- Bild-Vorschau -->
        <div class="position-relative flex-shrink-0 text-center" style="width: 140px;">
          <div id="actor-img-wrap-18" class="rounded border overflow-hidden d-flex align-items-center justify-content-center" style="width: 140px; height: 172px; background-color: var(--kkc-bg); border-color: var(--kkc-border) !important;">
            <?php if ($actor['fsk18_url'] !== ''): ?>
              <img id="actor-img-preview-18" src="<?= htmlspecialchars((string)$actor['fsk18_url'], ENT_QUOTES, 'UTF-8') ?>" alt="FSK18 Profilbild" class="w-100 h-100 object-fit-cover">
            <?php else: ?>
              <div id="actor-img-placeholder-18" class="text-muted text-center p-2">
                <i class="bi bi-camera fs-2 d-block mb-1"></i>
                <span style="font-size: 0.75rem;">Kein FSK18-Bild</span>
              </div>
              <img id="actor-img-preview-18" src="" alt="FSK18 Profilbild" class="w-100 h-100 object-fit-cover d-none">
            <?php endif; ?>
          </div>
        </div>

        <!-- Upload-Steuerung & Hinweis -->
        <div class="flex-grow-1">
          <div class="alert alert-light border py-2 px-3 small text-muted mb-3" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
            <i class="bi bi-stars text-danger me-1"></i>
            Freizügige Darstellung für den FSK18-Bereich ist auf diesem Profilbild <strong>ausdrücklich erwünscht</strong>.
          </div>

          <label for="actor-img-input-18" class="btn btn-sm btn-primary-kkc d-inline-flex align-items-center gap-2 cursor-pointer mb-2">
            <i class="bi bi-cloud-arrow-up-fill"></i>
            <span id="actor-img-btn-text-18">FSK18-Bild auswählen &amp; hochladen</span>
          </label>
          <input type="file" id="actor-img-input-18" class="d-none actor-image-upload-input" data-actor-id="<?= $actorId ?>" data-fsk="18" accept=".jpg,.jpeg,.png,image/jpeg,image/png">

          <div class="text-muted" style="font-size: 0.72rem;">
            Erlaubte Formate: <strong>JPG, JPEG, PNG</strong> · Max. Dateigröße: <strong>5 MB</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================================================
     SEKTION 2: STECKBRIEF & PROFIL-FORMULAR
     ========================================================================== -->
<form id="form-actor-profile" data-mode="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>" novalidate>
  <?php if ($isEdit): ?>
    <input type="hidden" name="actor_id" value="<?= $actorId ?>">
  <?php endif; ?>

  <?php if (!$isEdit): ?>
  <!-- Benutzername (Nur bei Neuanlage editierbar) -->
  <div class="card-kkc p-4 mb-4">
    <h2 class="h6 font-heading fw-bold mb-3">
      <i class="bi bi-person-badge-fill ec-text-primary me-2"></i>1. Darsteller-Benutzername festlegen
    </h2>

    <div class="row g-3 align-items-start">
      <div class="col-12 col-md-6">
        <label for="actor-username-input" class="form-label small fw-semibold">
          Gewünschter Benutzername / Künstlername <span class="text-danger">*</span>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-at"></i></span>
          <input type="text"
                 id="actor-username-input"
                 name="username"
                 class="form-control"
                 placeholder="z.B. SarahSweet"
                 maxlength="40"
                 required
                 autocomplete="off">
          <button type="button" id="btn-check-actor-username" class="btn btn-outline-kkc">
            Verfügbarkeit prüfen
          </button>
        </div>
        <div id="actor-username-feedback" class="small mt-1 text-muted">
          Erlaubt sind Buchstaben, Zahlen, Bindestriche, Unterstriche und Punkte.
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="p-3 rounded border small text-muted" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="fw-semibold text-body mb-1">
            <i class="bi bi-info-circle-fill ec-text-primary me-1"></i>Wichtige Hinweise zur Neuanlage:
          </div>
          <ul class="mb-0 ps-3">
            <li>Der Benutzername kann nach dem Anlegen <strong>nicht mehr geändert</strong> werden.</li>
            <li>Direkt nach dem Speichern kannst du im nächsten Schritt deine <strong>FSK16- und FSK18-Profilbilder</strong> hochladen.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Über mich (Freitext-Beschreibung) -->
  <div class="card-kkc p-4 mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <h2 class="h6 font-heading fw-bold mb-0">
        <i class="bi bi-chat-quote-fill ec-text-primary me-2"></i>Über mich (Profilbeschreibung)
      </h2>
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" role="switch" id="check_about_me" name="check_about_me" value="1" <?= !empty($actor['check_about_me']) ? 'checked' : '' ?>>
        <label class="form-check-label small fw-medium" for="check_about_me">Im Profil anzeigen</label>
      </div>
    </div>

    <p class="text-muted small mb-3">
      Beschreibe dich, deine Vorlieben oder das, was deine Zuschauer über dich wissen sollten, möglichst ausführlich. Gute Beschreibungstexte steigern deine Verkäufe und verbessern die Platzierung in Suchmaschinen.
    </p>

    <textarea name="about_me"
              id="about_me"
              rows="5"
              class="form-control"
              placeholder="Schreibe hier einen ansprechenden Text über dich..."><?= htmlspecialchars((string)$actor['about_me'], ENT_QUOTES, 'UTF-8') ?></textarea>
  </div>

  <!-- Persönliche Angaben & Körperliche Merkmale -->
  <div class="card-kkc p-4 mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h2 class="h6 font-heading fw-bold mb-0">
          <i class="bi bi-sliders ec-text-primary me-2"></i>Steckbrief &amp; körperliche Merkmale
        </h2>
        <p class="text-muted small mb-0">
          Aktiviere den Schalter „Anzeigen“ bei allen Merkmalen, die öffentlich in deinem Steckbrief sichtbar sein sollen.
        </p>
      </div>
    </div>

    <div class="row g-3">
      <!-- 1. Geschlecht -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="gender" class="form-label small fw-semibold mb-0">Geschlecht</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_gender" name="check_gender" value="1" <?= !empty($actor['check_gender']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_gender">Anzeigen</label>
            </div>
          </div>
          <select name="gender" id="gender" class="form-select form-select-sm">
            <?php foreach ($defs['genders'] as $gKey => $gLabel): ?>
              <option value="<?= htmlspecialchars((string)$gKey, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['gender'] === (string)$gKey) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$gLabel, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- 2. Alter -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="age" class="form-label small fw-semibold mb-0">Alter</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_age" name="check_age" value="1" <?= !empty($actor['check_age']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_age">Anzeigen</label>
            </div>
          </div>
          <select name="age" id="age" class="form-select form-select-sm">
            <?php for ($i = 18; $i <= 99; $i++): ?>
              <option value="<?= $i ?>" <?= ((int)$actor['age'] === $i) ? 'selected' : '' ?>><?= $i ?> Jahre</option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <!-- 3. Sternzeichen -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="star_sign" class="form-label small fw-semibold mb-0">Sternzeichen</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_star_sign" name="check_star_sign" value="1" <?= !empty($actor['check_star_sign']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_star_sign">Anzeigen</label>
            </div>
          </div>
          <select name="star_sign" id="star_sign" class="form-select form-select-sm">
            <?php foreach ($defs['star_signs'] as $sign): ?>
              <option value="<?= htmlspecialchars($sign, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['star_sign'] === $sign) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sign, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- 4. Körpergröße -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="body_height" class="form-label small fw-semibold mb-0">Körpergröße</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_body_height" name="check_body_height" value="1" <?= !empty($actor['check_body_height']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_body_height">Anzeigen</label>
            </div>
          </div>
          <select name="body_height" id="body_height" class="form-select form-select-sm">
            <?php for ($i = 100; $i <= 230; $i++): ?>
              <option value="<?= $i ?>" <?= ((int)$actor['body_height'] === $i) ? 'selected' : '' ?>><?= $i ?> cm</option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <!-- 5. Körpergewicht -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="body_weight" class="form-label small fw-semibold mb-0">Gewicht</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_body_weight" name="check_body_weight" value="1" <?= !empty($actor['check_body_weight']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_body_weight">Anzeigen</label>
            </div>
          </div>
          <select name="body_weight" id="body_weight" class="form-select form-select-sm">
            <?php for ($i = 45; $i <= 150; $i++): ?>
              <option value="<?= $i ?>" <?= ((int)$actor['body_weight'] === $i) ? 'selected' : '' ?>><?= $i ?> kg</option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <!-- 6. Körbchengröße -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label small fw-semibold mb-0">Körbchengröße</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_cup_size" name="check_cup_size" value="1" <?= !empty($actor['check_cup_size']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_cup_size">Anzeigen</label>
            </div>
          </div>
          <div class="d-flex gap-2">
            <select name="cup_size_a" class="form-select form-select-sm">
              <?php foreach ($defs['cup_sizes_a'] as $cA): ?>
                <option value="<?= htmlspecialchars($cA, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['cup_size_a'] === $cA) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cA, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select name="cup_size_b" class="form-select form-select-sm">
              <?php foreach ($defs['cup_sizes_b'] as $cB): ?>
                <option value="<?= htmlspecialchars($cB, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['cup_size_b'] === $cB) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cB, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- 7. Haarfarbe -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="hair_color" class="form-label small fw-semibold mb-0">Haarfarbe</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_hair_color" name="check_hair_color" value="1" <?= !empty($actor['check_hair_color']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_hair_color">Anzeigen</label>
            </div>
          </div>
          <select name="hair_color" id="hair_color" class="form-select form-select-sm">
            <?php foreach ($defs['hair_colors'] as $hColor): ?>
              <option value="<?= htmlspecialchars($hColor, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['hair_color'] === $hColor) ? 'selected' : '' ?>>
                <?= htmlspecialchars($hColor, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- 8. Augenfarbe -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="eye_color" class="form-label small fw-semibold mb-0">Augenfarbe</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_eye_color" name="check_eye_color" value="1" <?= !empty($actor['check_eye_color']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_eye_color">Anzeigen</label>
            </div>
          </div>
          <select name="eye_color" id="eye_color" class="form-select form-select-sm">
            <?php foreach ($defs['eye_colors'] as $eColor): ?>
              <option value="<?= htmlspecialchars($eColor, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['eye_color'] === $eColor) ? 'selected' : '' ?>>
                <?= htmlspecialchars($eColor, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- 9. Intimrasur -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="shaven" class="form-label small fw-semibold mb-0">Rasiert</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_shaven" name="check_shaven" value="1" <?= !empty($actor['check_shaven']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_shaven">Anzeigen</label>
            </div>
          </div>
          <select name="shaven" id="shaven" class="form-select form-select-sm">
            <?php foreach ($defs['shaven_options'] as $sVal => $sLabel): ?>
              <option value="<?= (int)$sVal ?>" <?= ((int)$actor['shaven'] === (int)$sVal) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sLabel, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- 10. Sexuelle Orientierung -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="sexual_orientation" class="form-label small fw-semibold mb-0">Sexuelle Orientierung</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_sexual_orientation" name="check_sexual_orientation" value="1" <?= !empty($actor['check_sexual_orientation']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_sexual_orientation">Anzeigen</label>
            </div>
          </div>
          <select name="sexual_orientation" id="sexual_orientation" class="form-select form-select-sm">
            <?php foreach ($defs['sexual_orientations'] as $sOrient): ?>
              <option value="<?= htmlspecialchars($sOrient, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['sexual_orientation'] === $sOrient) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sOrient, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- 11. Familienstand -->
      <div class="col-12 col-md-6 col-xl-4">
        <div class="p-3 rounded border h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label for="marital_status" class="form-label small fw-semibold mb-0">Familienstand</label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="check_marital_status" name="check_marital_status" value="1" <?= !empty($actor['check_marital_status']) ? 'checked' : '' ?>>
              <label class="form-check-label small text-muted" for="check_marital_status">Anzeigen</label>
            </div>
          </div>
          <select name="marital_status" id="marital_status" class="form-select form-select-sm">
            <?php foreach ($defs['marital_statuses'] as $mStatus): ?>
              <option value="<?= htmlspecialchars($mStatus, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$actor['marital_status'] === $mStatus) ? 'selected' : '' ?>>
                <?= htmlspecialchars($mStatus, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Interessen & Auf der Suche nach -->
  <div class="row g-4 mb-4">
    <!-- Interessen -->
    <div class="col-12 col-lg-6">
      <div class="card-kkc p-4 h-100">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h6 font-heading fw-bold mb-0">
            <i class="bi bi-stars ec-text-primary me-2"></i>Interessen
          </h2>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="check_interests" name="check_interests" value="1" <?= !empty($actor['check_interests']) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-medium" for="check_interests">Im Profil anzeigen</label>
          </div>
        </div>

        <div class="row g-2">
          <?php foreach ($defs['interests'] as $idx => $interest): ?>
          <?php $isChecked = in_array($interest, $actor['interests_list'] ?? [], true); ?>
          <div class="col-12 col-sm-6">
            <label class="d-flex align-items-center gap-2 p-2 rounded border small cursor-pointer" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
              <input type="checkbox" class="form-check-input mt-0" name="interests[]" value="<?= htmlspecialchars($interest, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($interest, ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Auf der Suche nach -->
    <div class="col-12 col-lg-6">
      <div class="card-kkc p-4 h-100">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h6 font-heading fw-bold mb-0">
            <i class="bi bi-search-heart ec-text-primary me-2"></i>Auf der Suche nach
          </h2>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="check_looking_for" name="check_looking_for" value="1" <?= !empty($actor['check_looking_for']) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-medium" for="check_looking_for">Im Profil anzeigen</label>
          </div>
        </div>

        <div class="row g-2">
          <?php foreach ($defs['looking_for'] as $idx => $lookItem): ?>
          <?php $isChecked = in_array($lookItem, $actor['looking_for_list'] ?? [], true); ?>
          <div class="col-12 col-sm-6">
            <label class="d-flex align-items-center gap-2 p-2 rounded border small cursor-pointer" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
              <input type="checkbox" class="form-check-input mt-0" name="looking_for[]" value="<?= htmlspecialchars($lookItem, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($lookItem, ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Sexuelle Vorlieben (42 Optionen) -->
  <div class="card-kkc p-4 mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <h2 class="h6 font-heading fw-bold mb-0">
          <i class="bi bi-heart-fill ec-text-primary me-2"></i>Sexuelle Vorlieben
        </h2>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-kkc py-0 px-2" id="btn-prefs-select-all" style="font-size: 0.75rem;">Alle auswählen</button>
          <button type="button" class="btn btn-outline-kkc py-0 px-2" id="btn-prefs-deselect-all" style="font-size: 0.75rem;">Auswahl aufheben</button>
        </div>
      </div>

      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="check_sexual_preferences" name="check_sexual_preferences" value="1" <?= !empty($actor['check_sexual_preferences']) ? 'checked' : '' ?>>
        <label class="form-check-label small fw-medium" for="check_sexual_preferences">Im Profil anzeigen</label>
      </div>
    </div>

    <div class="row g-2" id="sexual-preferences-grid">
      <?php foreach ($defs['sexual_preferences'] as $pref): ?>
      <?php $isChecked = in_array($pref, $actor['sexual_preferences_list'] ?? [], true); ?>
      <div class="col-12 col-sm-6 col-md-4 col-xl-3">
        <label class="d-flex align-items-center gap-2 p-2 rounded border small cursor-pointer h-100" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <input type="checkbox" class="form-check-input mt-0 sexual-pref-checkbox" name="sexual_preferences[]" value="<?= htmlspecialchars($pref, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?>>
          <span class="text-truncate" title="<?= htmlspecialchars($pref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pref, ENT_QUOTES, 'UTF-8') ?></span>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Abschluss-Aktionsleiste -->
  <div class="card-kkc p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="small text-muted">
      <i class="bi bi-shield-lock ec-text-primary me-1"></i>
      Alle Änderungen werden sofort für die EroCloud-Synchronisation gespeichert.
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= htmlspecialchars($backRoute, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-kkc">Abbrechen</a>
      <button type="submit" id="btn-submit-actor-profile" class="btn btn-sm btn-primary-kkc px-4">
        <i class="bi bi-check2-circle me-1"></i>
        <?= $isEdit ? 'Profildaten jetzt speichern' : 'Darsteller-Profil jetzt anlegen' ?>
      </button>
    </div>
  </div>
</form>
