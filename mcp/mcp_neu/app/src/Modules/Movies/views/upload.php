<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var int                              $step
 * @var array<string, mixed>             $movie
 * @var array<int, array<string, mixed>> $actors
 * @var array<int, array<string, mixed>> $websites
 * @var array<int, array<string, mixed>> $categoryGroups
 */

$movieId         = (int)($movie['id'] ?? 0);
$title           = (string)($movie['title'] ?? '');
$description     = (string)($movie['description'] ?? '');
$onlineAt        = (string)($movie['online_at'] ?? date('Y-m-d\TH:i', strtotime('+90 minutes')));
$amountSecond    = (string)($movie['amount_second'] ?? '0.8');
$asDownload      = (int)($movie['as_download'] ?? 1);
$amountDownload  = (int)($movie['amount_download'] ?? 10);
$preview         = (int)($movie['preview'] ?? 0);
$actorId         = (int)($movie['actor_id'] ?? 0);
$visibleWebsite  = (string)($movie['visible_for_website'] ?? 'public');
$categoryMaster  = (string)($movie['category_master'] ?? 'porn');
$metaTitle       = (string)($movie['meta_title'] ?? '');
$metaDescription = (string)($movie['meta_description'] ?? '');
$seoUrl          = (string)($movie['seo_url'] ?? '');
$filename        = trim((string)($movie['filename'] ?? ''));
$playtimeString  = (string)($movie['playtime_string'] ?? '');
$resolution      = (string)($movie['resolution'] ?? '');
$released        = (int)($movie['released'] ?? 0);
$notifyOnConvert = (int)($movie['notify_on_convert'] ?? 1);
$hasUploadedFile = ($filename !== '');

$amountSecondOptions = [];
for ($i = 1; $i <= 300; $i++) {
    $amountSecondOptions[] = number_format($i / 10, 1, '.', '');
}
if ($amountSecond !== '0.0' && $amountSecond !== '0' && !in_array($amountSecond, $amountSecondOptions, true)) {
    $amountSecondOptions[] = $amountSecond;
}
$downloadPercentOptions = range(0, 150);
?>

<!-- Kopfbereich -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <a href="#/movies" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-arrow-left me-1"></i> Zur Übersicht
      </a>
      <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
        Schritt <?= $step ?> von 3
      </span>
    </div>
    <h2 class="h4 fw-bold mb-0">Neuen Film hochladen</h2>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-toggle="modal" data-bs-target="#modalMovieQualityTips">
      <i class="bi bi-lightbulb me-1 text-warning"></i> Tipps zur Videoqualität
    </button>
  </div>
</div>

<!-- 3-Schritte Wizard-Anzeige -->
<div class="kkc-card mb-4">
  <div class="kkc-card-body py-3">
    <div class="row g-3 text-center">
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 1 ? 'bg-primary bg-opacity-10 border-primary' : ($step > 1 ? 'bg-success bg-opacity-10 border-success' : 'bg-light') ?>">
          <div class="fw-bold small <?= $step === 1 ? 'text-primary' : ($step > 1 ? 'text-success' : 'text-secondary') ?>">
            <i class="bi <?= $step > 1 ? 'bi-check-circle-fill' : 'bi-1-circle-fill' ?> me-1"></i> Schritt 1: Basisdaten &amp; Preise
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Titel, Beschreibung, Darsteller &amp; Kategorien</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 2 ? 'bg-primary bg-opacity-10 border-primary' : ($step > 2 ? 'bg-success bg-opacity-10 border-success' : 'bg-light') ?>">
          <div class="fw-bold small <?= $step === 2 ? 'text-primary' : ($step > 2 ? 'text-success' : 'text-secondary') ?>">
            <i class="bi <?= $step > 2 ? 'bi-check-circle-fill' : 'bi-2-circle-fill' ?> me-1"></i> Schritt 2: Video &amp; Vorschaubilder-Option
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Videodatei hochladen, Cover-Editor &amp; Freigabe wählen</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 3 ? 'bg-success bg-opacity-10 border-success' : 'bg-light' ?>">
          <div class="fw-bold small <?= $step === 3 ? 'text-success' : 'text-secondary' ?>">
            <i class="bi <?= $step === 3 ? 'bi-check-circle-fill' : 'bi-3-circle-fill' ?> me-1"></i> Schritt 3: Konvertierung &amp; Cover-Studio
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Automatische Szenen-Generierung &amp; Abschluss</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="movie-upload-alert-box"></div>

<?php if ($step === 1): ?>
  <?php if (empty($actors)): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Hinweis:</strong> Um einen Film hochzuladen, benötigst du mindestens ein freigeschaltetes Darsteller-Profil.
      </div>
      <a href="#/new-actor" class="btn btn-sm btn-kkc-primary">Jetzt Darsteller-Profil anlegen</a>
    </div>
  <?php endif; ?>

  <form id="kkc-movie-step1-form">
    <input type="hidden" name="movie_id" value="<?= $movieId ?>">

    <div class="row g-4">
      <div class="col-12 col-xl-7">
        <!-- Basisdaten -->
        <div class="kkc-card mb-4">
          <div class="kkc-card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-film me-2 text-primary"></i>1. Angaben zum Film</h6>
          </div>
          <div class="kkc-card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Filmtitel <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" maxlength="65" required
                       value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Maximal 65 Zeichen (keine E-Mail-Adressen oder URLs)">
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Filmbeschreibung <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="10"
                          style="min-height: 220px; line-height: 1.6;"
                          maxlength="2000" required
                          placeholder="Beschreibe die Handlung ausführlich (mindestens 30 Zeichen)..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>

              <?php if (count($actors) === 1): ?>
                <input type="hidden" name="actor_id" value="<?= (int)$actors[0]['id'] ?>">
              <?php else: ?>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Darsteller-Profil <span class="text-danger">*</span></label>
                  <select name="actor_id" class="form-select" required>
                    <option value="0">-- Bitte Darsteller auswählen --</option>
                    <?php foreach ($actors as $act): ?>
                      <option value="<?= (int)$act['id'] ?>" <?= $actorId === (int)$act['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$act['username'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <div class="col-12 <?= count($actors) === 1 ? '' : 'col-md-6' ?>">
                <label class="form-label fw-semibold">Sichtbarkeit / Portal</label>
                <select name="visible_for_website" class="form-select">
                  <?php foreach ($websites as $ws): ?>
                    <option value="<?= htmlspecialchars((string)$ws['value'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $visibleWebsite === (string)$ws['value'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$ws['label'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Veröffentlichungsdatum (Online ab)</label>
                <input type="datetime-local" name="online_at" class="form-control"
                       value="<?= htmlspecialchars($onlineAt, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-text">Geplante Veröffentlichung mind. 90 Minuten in der Zukunft.</div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Hauptkategorie</label>
                <select name="category_master" class="form-select">
                  <option value="porn" <?= $categoryMaster === 'porn' ? 'selected' : '' ?>>Porno (Hardcore / FSK18)</option>
                  <option value="fetish" <?= ($categoryMaster === 'fetish' || $categoryMaster === 'erotic') ? 'selected' : '' ?>>Fetisch (SM, BDSM u.a.)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Preisgestaltung -->
        <div class="kkc-card mb-4">
          <div class="kkc-card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-coin me-2 text-primary"></i>2. Preise &amp; Verkaufsoptionen</h6>
          </div>
          <div class="kkc-card-body">
            <div class="row g-3">
              <!-- Kostenloser Trailer / Vorstellungsvideo -->
              <div class="col-12">
                <div class="p-3 rounded-3 border <?= $preview === 1 ? 'bg-primary bg-opacity-10 border-primary border-opacity-25' : 'bg-light' ?>" id="movie-trailer-box">
                  <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" id="movie-preview-switch"
                           name="trailer" value="1" <?= $preview === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="movie-preview-switch">
                      Dieses Video ist ein kostenloser Trailer / Vorstellungsvideo
                    </label>
                  </div>
                  <div class="text-muted small">
                    Schalte diesen Schalter ein, wenn dieses Video ein Trailer oder ein kurzes Vorstellungsvideo ist und deinen Besuchern komplett kostenlos (0 Coins) angeboten werden soll.
                  </div>
                </div>
              </div>

              <!-- Preis-Felder (werden bei aktivem Trailer-Schalter automatisch deaktiviert) -->
              <div class="col-12" id="movie-pricing-fields-wrap" style="<?= $preview === 1 ? 'opacity:0.45; pointer-events:none;' : '' ?>">
                <div class="row g-3 align-items-end">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Streaming-Preis (Coins pro Sekunde)</label>
                    <select name="amount_second" id="movie-amount-second" class="form-select" <?= $preview === 1 ? 'disabled' : '' ?>>
                      <?php foreach ($amountSecondOptions as $opt): ?>
                        <option value="<?= $opt ?>" <?= $amountSecond === $opt ? 'selected' : '' ?>>
                          <?= str_replace('.', ',', $opt) ?> Coins / Sekunde<?= $opt === '0.8' ? ' (Empfohlen)' : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" role="switch" id="movie-as-download"
                             name="as_download" value="1" <?= $asDownload === 1 ? 'checked' : '' ?> <?= $preview === 1 ? 'disabled' : '' ?>>
                      <label class="form-check-label fw-semibold" for="movie-as-download">
                        Film zusätzlich als Komplett-Download anbieten
                      </label>
                    </div>
                  </div>

                  <div class="col-12 col-md-6" id="movie-download-price-wrap" style="<?= $asDownload === 1 ? '' : 'opacity:0.5;' ?>">
                    <label class="form-label fw-semibold">Aufpreis für Komplett-Download (%)</label>
                    <select name="amount_download" id="movie-amount-download" class="form-select" <?= ($asDownload === 1 && $preview !== 1) ? '' : 'disabled' ?>>
                      <?php foreach ($downloadPercentOptions as $pct): ?>
                        <option value="<?= $pct ?>" <?= $amountDownload === $pct ? 'selected' : '' ?>>
                          <?php if ($pct === 0): ?>
                            +0 % (genau so teuer wie Streaming)
                          <?php elseif ($pct === 10): ?>
                            +10 % (Empfohlen)
                          <?php else: ?>
                            +<?= $pct ?> %
                          <?php endif; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Optionale SEO-Einstellungen -->
        <div class="kkc-card mb-4">
          <div class="kkc-card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-search me-2 text-primary"></i>3. Suchmaschinen-Optimierung (Optional)</h6>
          </div>
          <div class="kkc-card-body">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Meta-Titel</label>
                <input type="text" name="meta_title" class="form-control" maxlength="80"
                       value="<?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Optionaler SEO-Titel">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">SEO-URL (Slug)</label>
                <input type="text" name="seo_url" class="form-control" maxlength="100"
                       value="<?= htmlspecialchars($seoUrl, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Nur Buchstaben, Zahlen und Bindestriche">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Meta-Beschreibung</label>
                <textarea name="meta_description" class="form-control" rows="2" maxlength="160"
                          placeholder="Optionale Kurzbeschreibung für Suchmaschinen"><?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Rechte Spalte: Kategorien -->
      <div class="col-12 col-xl-5">
        <div class="kkc-card mb-4">
          <div class="kkc-card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-tags me-2 text-primary"></i>4. Kategorien (mind. 1 wählen)</h6>
          </div>
          <div class="kkc-card-body">
            <div class="accordion" id="movieUploadCategoriesAccordion">
              <?php foreach ($categoryGroups as $idx => $group): ?>
                <?php
                $gId      = htmlspecialchars((string)$group['id'], ENT_QUOTES, 'UTF-8');
                $isOpen   = ($idx === 0 || (int)$group['selected_count'] > 0);
                $collapse = 'upCatCollapse_' . $gId;
                ?>
                <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                  <h2 class="accordion-header">
                    <button class="accordion-button <?= $isOpen ? '' : 'collapsed' ?> py-2 px-3 fw-semibold"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= $collapse ?>">
                      <span><?= htmlspecialchars((string)$group['title'], ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="badge bg-primary ms-2 js-cat-group-count" data-group-id="<?= $gId ?>">
                        <?= (int)$group['selected_count'] ?> gewählt
                      </span>
                    </button>
                  </h2>
                  <div id="<?= $collapse ?>" class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>">
                    <div class="accordion-body py-2">
                      <div class="row g-2">
                        <?php foreach ($group['items'] as $catItem): ?>
                          <?php
                          $nameId    = (string)$catItem['name_id'];
                          $isChecked = !empty($catItem['is_checked']);
                          $isSys     = !empty($catItem['is_system_detected']);
                          $boxStyle  = $isSys ? 'background-color: #fff6d0; border: 1px solid #ffe8a1;' : '';
                          ?>
                          <div class="col-12 col-sm-6">
                            <label class="d-flex align-items-center gap-2 p-2 rounded-2 border small cursor-pointer"
                                   style="<?= $boxStyle ?>"
                                   title="<?= htmlspecialchars((string)$catItem['hint'], ENT_QUOTES, 'UTF-8') ?>">
                              <input type="checkbox"
                                     class="form-check-input mt-0 js-movie-cat-checkbox"
                                     data-group-id="<?= $gId ?>"
                                     name="category_slave[]"
                                     value="<?= htmlspecialchars($nameId, ENT_QUOTES, 'UTF-8') ?>"
                                     <?= $isChecked ? 'checked' : '' ?>>
                              <span class="text-truncate"><?= htmlspecialchars((string)$catItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-4 d-grid">
              <button type="submit" class="btn btn-kkc-primary btn-lg" id="btn-submit-movie-step1">
                Weiter zu Schritt 2: Videodatei auswählen <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

<?php elseif ($step === 2): ?>
  <!-- SCHRITT 2: 1. Videodatei hochladen -> 2. Hinweis auf Konvertierung & Cover-Studio -> 3. Entscheidung vor Freigabe -->
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <!-- Karte 2.1: Videodatei hochladen -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>1. Videodatei auf den Server übertragen</h6>
            <div class="small text-muted">Für Film: <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong> (#<?= $movieId ?>)</div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <a href="#/movie-upload?step=1&movie_id=<?= $movieId ?>" class="btn btn-sm btn-outline-kkc" id="btn-back-step1">
              <i class="bi bi-pencil me-1"></i> Basisdaten ändern
            </a>
            <?php if ($movieId > 0): ?>
              <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete-movie-detail"
                      data-movie-id="<?= $movieId ?>"
                      data-movie-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-trash3 me-1"></i> Entwurf löschen
              </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="kkc-card-body p-4">
          <!-- Statusbox: Videodatei bereits erfolgreich übertragen -->
          <div id="movie-file-uploaded-box" class="<?= $hasUploadedFile ? '' : 'd-none' ?> p-4 rounded-3 border border-success bg-success bg-opacity-10 mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white flex-shrink-0" style="width: 48px; height: 48px;">
                  <i class="bi bi-check-lg fs-4"></i>
                </div>
                <div>
                  <h6 class="fw-bold text-success mb-1">Deine Videodatei wurde erfolgreich hochgeladen!</h6>
                  <div class="small text-dark">
                    <span class="me-3"><i class="bi bi-file-earmark-play me-1"></i><strong id="uploaded-movie-filename"><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <span class="me-3"><i class="bi bi-clock me-1"></i>Laufzeit: <strong id="uploaded-movie-playtime"><?= htmlspecialchars($playtimeString !== '' ? $playtimeString : '-', ENT_QUOTES, 'UTF-8') ?></strong> Min.</span>
                    <span><i class="bi bi-aspect-ratio me-1"></i>Auflösung: <strong id="uploaded-movie-resolution"><?= htmlspecialchars($resolution !== '' ? $resolution : '-', ENT_QUOTES, 'UTF-8') ?></strong></span>
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary bg-white" id="btn-replace-movie-file">
                <i class="bi bi-arrow-repeat me-1"></i> Andere Videodatei wählen
              </button>
            </div>
          </div>

          <!-- Datei-Auswahlbox (wird ausgeblendet, sobald Datei übertragen wurde) -->
          <div id="movie-file-select-wrap" class="<?= $hasUploadedFile ? 'd-none' : '' ?> text-center">
            <div class="p-4 rounded-3 border border-2 border-dashed bg-light mb-3">
              <i class="bi bi-file-earmark-play text-primary display-4 d-block mb-2"></i>
              <h5 class="fw-bold mb-1">Wähle deine Videodatei aus</h5>
              <p class="text-muted small mb-3">
                Unterstützte Formate: <strong>MP4, MOV, MKV, AVI, WMV, MPG, M4V, FLV</strong> &bull; Maximale Dateigröße: <strong>2,0 GB</strong>
              </p>
              <input type="file" id="movie-video-file-input" class="form-control mx-auto" style="max-width: 440px;"
                     accept=".avi,.flv,.m4v,.mkv,.mov,.mp4,.mpg,.wmv">
            </div>

            <!-- Fortschrittsanzeige (wird beim Upload eingeblendet) -->
            <div id="movie-upload-progress-wrap" class="d-none text-start mb-3 p-3 rounded-3 border bg-white shadow-sm">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small" id="movie-upload-status-text">Videodatei wird hochgeladen...</span>
                <span class="badge bg-primary" id="movie-upload-percent-badge">0 %</span>
              </div>
              <div class="progress mb-2" style="height: 14px;">
                <div id="movie-upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                     role="progressbar" style="width: 0%;"></div>
              </div>
              <div class="d-flex justify-content-between text-muted small">
                <span id="movie-upload-bytes-info">0 MB / 0 MB</span>
                <span>Bitte schließe dieses Browserfenster während der Übertragung nicht.</span>
              </div>
            </div>

            <div class="d-flex justify-content-center flex-wrap gap-3">
              <button type="button" class="btn btn-kkc-primary px-4" id="btn-start-movie-file-upload" data-movie-id="<?= $movieId ?>">
                <i class="bi bi-cloud-upload me-2"></i> Videodatei jetzt hochladen
              </button>
              <button type="button" class="btn btn-outline-danger d-none" id="btn-cancel-movie-file-upload">
                <i class="bi bi-x-circle me-1"></i> Upload abbrechen
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Karte 2.2: Erklärung Konvertierung, 10 Vorschaubilder & Cover-Editor + Entscheidung vor Einreichung -->
      <div class="kkc-card mb-4" id="movie-step2-post-upload-card">
        <div class="kkc-card-header">
          <div class="d-flex align-items-center gap-2">
            <h6 class="mb-0 fw-bold">
              <i class="bi bi-stars me-2 text-primary"></i>2. Was passiert nach dem Upload? (Konvertierung &amp; Cover-Studio)
            </h6>
          </div>
          <span id="movie-step2-decision-badge" class="badge <?= $hasUploadedFile ? 'bg-success' : 'bg-secondary bg-opacity-10 text-secondary border' ?>">
            <?= $hasUploadedFile ? 'Bereit für deine Auswahl' : 'Wird nach dem Datei-Upload freigeschaltet' ?>
          </span>
        </div>
        <div class="kkc-card-body p-4">
          <p class="text-muted small mb-3">
            Bevor du deinen Film endgültig zur Prüfung einreichst, solltest du wissen, welche Möglichkeiten dir direkt nach dem Upload zur Verfügung stehen:
          </p>

          <!-- 3 visuelle Info-Boxen zum Ablauf -->
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-3 border bg-light h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-pill">1</span>
                  <span class="fw-bold small text-dark"><i class="bi bi-gear-wide-connected me-1 text-primary"></i>HD-Konvertierung</span>
                </div>
                <p class="small text-muted mb-0">
                  Dein hochgeladenes Video wird auf unseren Servern automatisch im Hintergrund für flüssiges HD-Streaming auf allen Geräten konvertiert.
                </p>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-3 border bg-light h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-pill">2</span>
                  <span class="fw-bold small text-dark"><i class="bi bi-images me-1 text-primary"></i>10 Vorschaubilder</span>
                </div>
                <p class="small text-muted mb-0">
                  Bei der Konvertierung generiert unser System automatisch <strong>10 verschiedene Vorschaubilder (Szenen)</strong> direkt aus deinem Film.
                </p>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-3 border bg-primary bg-opacity-10 border-primary border-opacity-25 h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-pill">3</span>
                  <span class="fw-bold small text-primary"><i class="bi bi-magic me-1"></i>Moderner Cover-Editor</span>
                </div>
                <p class="small text-dark mb-0">
                  Du kannst deine Wunsch-Szenen für <strong>FSK 16 &amp; FSK 18</strong> wählen, Live-Schnappschüsse aus dem Video machen und sie im <strong>Cover-Studio</strong> (Milchglas, Verpixeln, Sticker, Text &amp; Badges) bearbeiten!
                </p>
              </div>
            </div>
          </div>

          <!-- Hinweis zur Pflicht-Auswahl & FSK 16 Jugendfreiheit (wird nach Datei-Upload aktiv) -->
          <div id="movie-step2-decision-wrap" style="<?= $hasUploadedFile ? '' : 'opacity: 0.55; pointer-events: none;' ?>">
            <div class="p-3 rounded-3 border border-2 border-primary bg-primary bg-opacity-10 mb-4">
              <div class="d-flex align-items-start gap-3">
                <i class="bi bi-shield-check fs-3 text-primary flex-shrink-0"></i>
                <div>
                  <h6 class="fw-bold text-dark mb-1">Wichtig vor der Freigabe: FSK 16 (jugendfrei) &amp; FSK 18 Cover festlegen</h6>
                  <p class="small text-muted mb-0">
                    Damit kein ungeprüftes Hardcore-Szenenbild versehentlich als öffentliches FSK 16 Vorschaubild erscheint, wird dein Film <strong>jetzt zunächst konvertiert</strong>. Sobald die 10 Szenenbilder bereitstehen (oder direkt über deinen eigenen Bild-Upload), wählst du dein <strong>FSK 16 &amp; FSK 18 Cover</strong> selbst aus, verpixelst bei Bedarf intime Stellen im <strong>Cover-Studio</strong> und reichst den Film danach mit einem Klick zur Prüfung ein.
                  </p>
                </div>
              </div>
              <input type="hidden" name="release_mode" class="js-release-mode-radio" value="later" checked>
            </div>

            <!-- E-Mail-Benachrichtigung bei abgeschlossener Konvertierung -->
            <div class="p-3 rounded-3 border bg-white mb-4">
              <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" role="switch" id="movie-notify-on-convert"
                       name="notify_on_convert" value="1" <?= $notifyOnConvert === 1 ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold text-dark" for="movie-notify-on-convert">
                  <i class="bi bi-envelope-check text-primary me-1"></i> Benachrichtige mich per E-Mail, sobald mein Video fertig konvertiert ist und die 10 Vorschaubilder bereitstehen
                </label>
              </div>
              <div class="text-muted small">
                Du erhältst direkt nach Abschluss der HD-Konvertierung eine E-Mail mit einem persönlichen Direkt-Link zu deinem Film. Deine Auswahl wird automatisch für deine nächsten Video-Uploads gemerkt.
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-2 border-top">
              <div class="small text-muted" id="movie-step2-footer-hint">
                <?php if ($hasUploadedFile): ?>
                  <i class="bi bi-check-circle-fill text-success me-1"></i>Videodatei ist bereit – klicke auf „Weiter", um zur Cover-Auswahl &amp; Konvertierung zu gelangen.
                <?php else: ?>
                  <i class="bi bi-info-circle me-1 text-primary"></i>Bitte lade zuerst oben deine Videodatei hoch, um fortzufahren.
                <?php endif; ?>
              </div>
              <button type="button"
                      class="btn btn-kkc-primary btn-lg px-4"
                      id="btn-complete-movie-step2"
                      data-movie-id="<?= $movieId ?>"
                      <?= $hasUploadedFile ? '' : 'disabled' ?>>
                <i class="bi bi-magic me-2" id="btn-complete-movie-step2-icon"></i>
                <span id="btn-complete-movie-step2-label">Weiter: Konvertierung starten &amp; Vorschaubilder gestalten</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- SCHRITT 3: Erfolgreicher Abschluss (angepasst an die gewählte Freigabe-Option) -->
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="kkc-card text-center p-4">
        <div class="kkc-card-body">
          <?php if ($released === 1): ?>
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3"
                 style="width: 72px; height: 72px;">
              <i class="bi bi-send-check fs-1"></i>
            </div>
            <h4 class="fw-bold mb-2">Dein Film wurde erfolgreich hochgeladen &amp; zur Prüfung eingereicht!</h4>
            <p class="text-muted mb-4">
              Deine Videodatei befindet sich nun in der automatischen Konvertierungswarteschlange.
              Sobald die Verarbeitung abgeschlossen ist, generiert das System automatisch die Vorschaubilder und dein Film wird von unserem Team geprüft.
            </p>
          <?php else: ?>
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary mb-3"
                 style="width: 72px; height: 72px;">
              <i class="bi bi-magic fs-1"></i>
            </div>
            <h4 class="fw-bold mb-2">Dein Film wird jetzt konvertiert &amp; 10 Vorschaubilder werden erstellt!</h4>
            <p class="text-muted mb-3">
              Deine Videodatei wurde erfolgreich übertragen und wird nun im Hintergrund verarbeitet.
              <strong>Dein Film wurde wie gewünscht noch nicht zur Prüfung eingereicht</strong>, damit du zuerst deine Vorschaubilder gestalten kannst.
            </p>
            <?php if ($notifyOnConvert === 1): ?>
              <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 small fw-semibold mb-4">
                <i class="bi bi-envelope-check-fill"></i>
                Wir senden dir automatisch eine E-Mail mit Direkt-Link, sobald deine 10 Vorschaubilder bereitstehen.
              </div>
            <?php endif; ?>

            <div class="p-3 rounded-3 border bg-light text-start mx-auto mb-4" style="max-width: 620px;">
              <div class="fw-bold small text-primary mb-2"><i class="bi bi-list-check me-1"></i>Deine nächsten Schritte:</div>
              <ol class="small text-muted mb-0 ps-3">
                <li class="mb-1">Sobald die Konvertierung in wenigen Minuten abgeschlossen ist, stehen dir in den <strong>Film-Details</strong> alle <strong>10 automatisch generierten Szenen-Vorschaubilder</strong> zur Verfügung (eigene Cover kannst du dort auch sofort hochladen).</li>
                <li class="mb-1">Bearbeite deine FSK 16 &amp; FSK 18 Cover nach Wunsch im <strong>Fullscreen Cover-Studio</strong> (Milchglas-Zensur, Verpixeln, Sticker, Text, Badges oder Live-Schnappschuss aus dem Video).</li>
                <li>Klicke anschließend in den Film-Details auf <strong>„Film jetzt zur Prüfung einreichen"</strong>, um deinen Film freizugeben.</li>
              </ol>
            </div>
          <?php endif; ?>

          <div class="d-flex justify-content-center flex-wrap gap-3">
            <?php if ($movieId > 0): ?>
              <a href="#/movie?id=<?= $movieId ?>" class="btn btn-kkc-primary btn-lg">
                <i class="bi bi-magic me-1"></i> Zu den Film-Details &amp; Cover-Studio öffnen
              </a>
            <?php endif; ?>
            <a href="#/movie-upload" class="btn btn-outline-kkc btn-lg">
              <i class="bi bi-plus-circle me-1"></i> Weiteren Film hochladen
            </a>
            <a href="#/movies" class="btn btn-outline-secondary btn-lg">
              <i class="bi bi-collection-play me-1"></i> Zur Film-Übersicht
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Modal: Tipps zur Videoqualität -->
<div class="modal fade" id="modalMovieQualityTips" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-lightbulb text-warning me-2"></i>Empfehlungen für optimale Videoqualität</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="p-3 rounded-3 border bg-light h-100">
              <h6 class="fw-bold text-primary mb-2">Empfohlene Video-Parameter</h6>
              <ul class="small mb-0 ps-3">
                <li><strong>Container-Format:</strong> MP4 (H.264 Video / AAC Audio)</li>
                <li><strong>Auflösung:</strong> Full-HD 1920x1080p oder HD 1280x720p (16:9 Format)</li>
                <li><strong>Bitrate:</strong> 4.000 bis 8.000 kbit/s für gestochen scharfe Wiedergabe</li>
                <li><strong>Bildrate:</strong> 25, 30 oder 60 FPS (konstante Framerate)</li>
              </ul>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="p-3 rounded-3 border bg-light h-100">
              <h6 class="fw-bold text-primary mb-2">Wichtige Richtlinien für die Freigabe</h6>
              <ul class="small mb-0 ps-3">
                <li>Keine fremden URLs, E-Mail-Adressen oder Telefonnummern im Video oder Titel</li>
                <li>Das FSK16-Vorschaubild muss jugendfrei sein (keine primären Geschlechtsmerkmale sichtbar)</li>
                <li>Alle im Video sichtbaren Personen müssen im Account verifiziert sein</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-kkc-primary" data-bs-dismiss="modal">Verstanden</button>
      </div>
    </div>
  </div>
</div>
