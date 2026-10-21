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

$amountSecondOptions = ['0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0', '1.1', '1.2', '1.3', '1.4', '1.5'];
$downloadEurOptions  = [5, 6, 7, 8, 9, 10, 12, 14, 15, 16, 18, 20, 25, 30, 35, 40, 45, 50];
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
            <i class="bi <?= $step > 2 ? 'bi-check-circle-fill' : 'bi-2-circle-fill' ?> me-1"></i> Schritt 2: Videodatei hochladen
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">MP4, MOV, MKV, AVI u.a. (bis 2 GB)</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 3 ? 'bg-success bg-opacity-10 border-success' : 'bg-light' ?>">
          <div class="fw-bold small <?= $step === 3 ? 'text-success' : 'text-secondary' ?>">
            <i class="bi <?= $step === 3 ? 'bi-check-circle-fill' : 'bi-3-circle-fill' ?> me-1"></i> Schritt 3: Konvertierung &amp; Abschluss
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Automatische Verarbeitung &amp; Freigabe</div>
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
        <strong>Hinweis:</strong> Um einen Film hochzuladen, benötigen Sie mindestens ein freigeschaltetes Darsteller-Profil.
      </div>
      <a href="#/actor-new" class="btn btn-sm btn-kkc-primary">Jetzt Darsteller-Profil anlegen</a>
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
                <input type="text" name="title" class="form-control" maxlength="100" required
                       value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Mindestens 5 Zeichen (keine E-Mail-Adressen oder URLs)">
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Filmbeschreibung <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="4" maxlength="2000" required
                          placeholder="Beschreiben Sie die Handlung ausführlich (mindestens 30 Zeichen)..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>

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

              <div class="col-12 col-md-6">
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
                  <option value="porn" <?= $categoryMaster === 'porn' ? 'selected' : '' ?>>Porn (Hardcore / FSK18)</option>
                  <option value="erotic" <?= $categoryMaster === 'erotic' ? 'selected' : '' ?>>Erotik / Softcore</option>
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
            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Streaming-Preis (Coins pro Sekunde)</label>
                <select name="amount_second" class="form-select">
                  <?php foreach ($amountSecondOptions as $opt): ?>
                    <option value="<?= $opt ?>" <?= $amountSecond === $opt ? 'selected' : '' ?>>
                      <?= str_replace('.', ',', $opt) ?> Coins / Sekunde
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="movie-as-download"
                         name="as_download" value="1" <?= $asDownload === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="movie-as-download">
                    Zusätzlich als Download anbieten
                  </label>
                </div>
              </div>

              <div class="col-12 col-md-6" id="movie-download-price-wrap" style="<?= $asDownload === 1 ? '' : 'opacity:0.5;' ?>">
                <label class="form-label fw-semibold">Download-Verkaufspreis (EUR)</label>
                <select name="amount_download" id="movie-amount-download" class="form-select" <?= $asDownload === 1 ? '' : 'disabled' ?>>
                  <?php foreach ($downloadEurOptions as $eur): ?>
                    <option value="<?= $eur ?>" <?= $amountDownload === $eur ? 'selected' : '' ?>>
                      <?= number_format($eur, 2, ',', '.') ?> € (ca. <?= $eur * 32 ?> Coins)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="movie-preview-switch"
                         name="preview" value="1" <?= $preview === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="movie-preview-switch">
                    Kostenlose Vorschau (Trailer) aktivieren
                  </label>
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
                $gId      = (int)$group['id'];
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
                                     name="categories[]"
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
  <!-- SCHRITT 2: Videodatei per XHR + Fortschrittsbalken hochladen -->
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="kkc-card">
        <div class="kkc-card-header">
          <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>Videodatei hochladen</h6>
            <div class="small text-muted">Für Film: <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong> (#<?= $movieId ?>)</div>
          </div>
          <a href="#/movie-upload?step=1&movie_id=<?= $movieId ?>" class="btn btn-sm btn-outline-kkc" id="btn-back-step1">
            <i class="bi bi-pencil me-1"></i> Basisdaten ändern
          </a>
        </div>
        <div class="kkc-card-body p-4 text-center">
          <div class="p-4 rounded-3 border border-2 border-dashed bg-light mb-4">
            <i class="bi bi-file-earmark-play text-primary display-4 d-block mb-2"></i>
            <h5 class="fw-bold mb-1">Wählen Sie Ihre Videodatei aus</h5>
            <p class="text-muted small mb-3">
              Unterstützte Formate: <strong>MP4, MOV, MKV, AVI, WMV, MPG, M4V, FLV</strong> &bull; Maximale Dateigröße: <strong>2,0 GB</strong>
            </p>
            <input type="file" id="movie-video-file-input" class="form-control mx-auto" style="max-width: 440px;"
                   accept=".avi,.flv,.m4v,.mkv,.mov,.mp4,.mpg,.wmv">
          </div>

          <!-- Fortschrittsanzeige (wird beim Upload eingeblendet) -->
          <div id="movie-upload-progress-wrap" class="d-none text-start mb-4 p-3 rounded-3 border bg-white shadow-sm">
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
              <span>Bitte schließen Sie dieses Browserfenster während der Übertragung nicht.</span>
            </div>
          </div>

          <div class="d-flex justify-content-center gap-3">
            <button type="button" class="btn btn-kkc-primary px-4" id="btn-start-movie-file-upload" data-movie-id="<?= $movieId ?>">
              <i class="bi bi-cloud-upload me-2"></i> Upload jetzt starten
            </button>
            <button type="button" class="btn btn-outline-danger d-none" id="btn-cancel-movie-file-upload">
              <i class="bi bi-x-circle me-1"></i> Upload abbrechen
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- SCHRITT 3: Erfolgreicher Abschluss -->
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7">
      <div class="kkc-card text-center p-4">
        <div class="kkc-card-body">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3"
               style="width: 72px; height: 72px;">
            <i class="bi bi-check2-all fs-1"></i>
          </div>
          <h4 class="fw-bold mb-2">Ihr Film wurde erfolgreich hochgeladen!</h4>
          <p class="text-muted mb-4">
            Ihre Videodatei befindet sich nun in der automatischen Konvertierungswarteschlange.
            Sobald die Verarbeitung abgeschlossen ist, generiert das System automatisch Vorschaubilder und Ihr Film wird zur Freigabe geprüft.
          </p>
          <div class="d-flex justify-content-center flex-wrap gap-3">
            <?php if ($movieId > 0): ?>
              <a href="#/movie?id=<?= $movieId ?>" class="btn btn-kkc-primary">
                <i class="bi bi-pencil-square me-1"></i> Film-Details öffnen
              </a>
            <?php endif; ?>
            <a href="#/movie-upload" class="btn btn-outline-kkc">
              <i class="bi bi-plus-circle me-1"></i> Weiteren Film hochladen
            </a>
            <a href="#/movies" class="btn btn-outline-secondary">
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
