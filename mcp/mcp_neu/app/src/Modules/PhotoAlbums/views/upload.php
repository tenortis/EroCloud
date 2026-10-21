<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var int                              $step
 * @var array<string, mixed>             $album
 * @var array<int, array<string, mixed>> $photos
 * @var array<int, array<string, mixed>> $actors
 */

$albumDbId       = (int)($album['id'] ?? 0);
$title           = (string)($album['title'] ?? '');
$description     = (string)($album['description'] ?? '');
$actorId         = (int)($album['actor_id'] ?? 0);
$onlineAt        = (string)($album['online_at'] ?? date('Y-m-d\TH:i', strtotime('+90 minutes')));
$amountDownload  = (int)($album['amount_download'] ?? 500);
$hasFsk16        = !empty($album['has_fsk16']);
$hasFsk18        = !empty($album['has_fsk18']);
$posterFsk16Url  = (string)($album['poster_fsk16_url'] ?? '');
$posterFsk18Url  = (string)($album['poster_fsk18_url'] ?? '');
$metaTitle       = (string)($album['meta_title'] ?? '');
$metaDescription = (string)($album['meta_description'] ?? '');
$seoUrl          = (string)($album['seo_url'] ?? '');

$priceCentsOptions = [0, 100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 1200, 1400, 1500, 1600, 1800, 2000, 2500, 3000, 3500, 4000, 4500, 5000];
?>

<!-- Kopfbereich -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <a href="#/photo-albums" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-arrow-left me-1"></i> Zur Übersicht
      </a>
      <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
        Schritt <?= $step ?> von 3
      </span>
    </div>
    <h2 class="h4 fw-bold mb-0">Neues Foto-Album hochladen</h2>
  </div>
</div>

<!-- 3-Schritte Wizard-Anzeige -->
<div class="kkc-card mb-4">
  <div class="kkc-card-body py-3">
    <div class="row g-3 text-center">
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 1 ? 'bg-primary bg-opacity-10 border-primary' : ($step > 1 ? 'bg-success bg-opacity-10 border-success' : 'bg-light') ?>">
          <div class="fw-bold small <?= $step === 1 ? 'text-primary' : ($step > 1 ? 'text-success' : 'text-secondary') ?>">
            <i class="bi <?= $step > 1 ? 'bi-check-circle-fill' : 'bi-1-circle-fill' ?> me-1"></i> Schritt 1: Basisdaten &amp; Preis
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Titel, Beschreibung, Darsteller &amp; Verkaufspreis</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 2 ? 'bg-primary bg-opacity-10 border-primary' : ($step > 2 ? 'bg-success bg-opacity-10 border-success' : 'bg-light') ?>">
          <div class="fw-bold small <?= $step === 2 ? 'text-primary' : ($step > 2 ? 'text-success' : 'text-secondary') ?>">
            <i class="bi <?= $step > 2 ? 'bi-check-circle-fill' : 'bi-2-circle-fill' ?> me-1"></i> Schritt 2: Cover &amp; Fotos hochladen
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">FSK16/FSK18 Cover sowie mindestens 4 Fotos</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-2 rounded-3 border <?= $step === 3 ? 'bg-success bg-opacity-10 border-success' : 'bg-light' ?>">
          <div class="fw-bold small <?= $step === 3 ? 'text-success' : 'text-secondary' ?>">
            <i class="bi <?= $step === 3 ? 'bi-check-circle-fill' : 'bi-3-circle-fill' ?> me-1"></i> Schritt 3: Prüfung &amp; Abschluss
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Bereit zur Freigabe</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="photo-album-alert-box"></div>

<?php if ($step === 1): ?>
  <?php if (empty($actors)): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Hinweis:</strong> Um ein Foto-Album anzulegen, benötigst du mindestens ein freigeschaltetes Darsteller-Profil.
      </div>
      <a href="#/new-actor" class="btn btn-sm btn-kkc-primary">Jetzt Darsteller-Profil anlegen</a>
    </div>
  <?php endif; ?>

  <form id="kkc-photo-album-step1-form">
    <input type="hidden" name="album_id" value="<?= $albumDbId ?>">

    <div class="row g-4">
      <div class="col-12 col-xl-8">
        <div class="kkc-card mb-4">
          <div class="kkc-card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-images me-2 text-primary"></i>1. Angaben zum Foto-Album</h6>
          </div>
          <div class="kkc-card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Albumtitel <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" maxlength="100" required
                       value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Mindestens 5 Zeichen (keine E-Mail-Adressen oder URLs)">
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Beschreibung <span class="text-danger">*</span></label>
                <textarea name="description" class="form-control" rows="10"
                          style="min-height: 220px; line-height: 1.6;"
                          maxlength="2000" required
                          placeholder="Beschreibe dein Foto-Album ausführlich (mindestens 30 Zeichen)..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
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

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Verkaufspreis (Download)</label>
                <select name="amount_download" class="form-select">
                  <?php foreach ($priceCentsOptions as $cents): ?>
                    <?php $eurVal = $cents / 100.0; ?>
                    <option value="<?= $cents ?>" <?= $amountDownload === $cents ? 'selected' : '' ?>>
                      <?php if ($cents === 0): ?>
                        Kostenlos (0,00 € / 0 Coins)
                      <?php else: ?>
                        <?= number_format($eurVal, 2, ',', '.') ?> € (ca. <?= (int)($eurVal * 32) ?> Coins)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 <?= count($actors) === 1 ? 'col-md-6' : '' ?>">
                <label class="form-label fw-semibold">Veröffentlichungsdatum (Online ab)</label>
                <input type="datetime-local" name="online_at" class="form-control"
                       value="<?= htmlspecialchars($onlineAt, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-text">Geplante Veröffentlichung mindestens 90 Minuten in der Zukunft.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="kkc-card mb-4">
          <div class="kkc-card-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-search me-2 text-primary"></i>2. Optionale SEO-Angaben</h6>
          </div>
          <div class="kkc-card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Meta-Titel</label>
              <input type="text" name="meta_title" class="form-control" maxlength="80"
                     value="<?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Optionaler SEO-Titel">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">SEO-URL (Slug)</label>
              <input type="text" name="seo_url" class="form-control" maxlength="100"
                     value="<?= htmlspecialchars($seoUrl, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Nur Buchstaben, Zahlen und Bindestriche">
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Meta-Beschreibung</label>
              <textarea name="meta_description" class="form-control" rows="3" maxlength="160"
                        placeholder="Optionale Kurzbeschreibung für Suchmaschinen"><?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-kkc-primary btn-lg" id="btn-submit-photo-album-step1">
                Weiter zu Schritt 2: Bilder hochladen <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

<?php elseif ($step === 2): ?>
  <!-- SCHRITT 2: Coverbilder (FSK16 & FSK18) + Albumfotos hochladen -->
  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-card-image me-2 text-primary"></i>1. Coverbilder hochladen</h6>
            <div class="small text-muted">Für Album: <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong></div>
          </div>
          <a href="#/photo-album-upload?step=1&album_id=<?= $albumDbId ?>" class="btn btn-sm btn-outline-kkc">
            <i class="bi bi-pencil me-1"></i> Basisdaten ändern
          </a>
        </div>
        <div class="kkc-card-body">
          <p class="text-muted small mb-3">
            Bitte lade sowohl ein jugendfreies <strong>FSK16-Coverbild</strong> als auch ein <strong>FSK18-Coverbild</strong> hoch (JPG oder PNG, max. 6 MB).
          </p>
          <div class="row g-3">
            <div class="col-12 col-sm-6">
              <div class="p-3 border rounded-3 bg-light text-center h-100">
                <span class="badge bg-success mb-2">FSK 16 Cover (jugendfrei)</span>
                <img id="album-cover-fsk16-img"
                     src="<?= htmlspecialchars($posterFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                     alt="FSK 16 Cover"
                     class="img-fluid rounded border mb-3 bg-white d-block mx-auto"
                     style="height: 145px; width: 100%; object-fit: cover;">
                <label class="btn btn-sm btn-outline-kkc w-100 mb-0">
                  <i class="bi bi-upload me-1"></i> FSK16 Cover wählen
                  <input type="file" class="d-none js-album-cover-upload"
                         data-album-id="<?= $albumDbId ?>"
                         data-fsk="16"
                         accept=".jpg,.jpeg,.png">
                </label>
              </div>
            </div>

            <div class="col-12 col-sm-6">
              <div class="p-3 border rounded-3 bg-light text-center h-100">
                <span class="badge bg-danger mb-2">FSK 18 Cover (Hardcore)</span>
                <img id="album-cover-fsk18-img"
                     src="<?= htmlspecialchars($posterFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                     alt="FSK 18 Cover"
                     class="img-fluid rounded border mb-3 bg-white d-block mx-auto"
                     style="height: 145px; width: 100%; object-fit: cover;">
                <label class="btn btn-sm btn-outline-kkc w-100 mb-0">
                  <i class="bi bi-upload me-1"></i> FSK18 Cover wählen
                  <input type="file" class="d-none js-album-cover-upload"
                         data-album-id="<?= $albumDbId ?>"
                         data-fsk="18"
                         accept=".jpg,.jpeg,.png">
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-7">
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <div>
            <h6 class="mb-0 fw-bold">
              <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>2. Fotos des Albums (<span id="album-photos-grid-count"><?= count($photos) ?></span> / mind. 4)
            </h6>
          </div>
          <label class="btn btn-sm btn-kkc-primary mb-0">
            <i class="bi bi-cloud-arrow-up me-1"></i> Fotos auswählen &amp; hochladen
            <input type="file" class="d-none js-album-photos-upload"
                   data-album-id="<?= $albumDbId ?>"
                   multiple
                   accept=".jpg,.jpeg,.png">
          </label>
        </div>
        <div class="kkc-card-body">
          <div id="album-photos-upload-status" class="d-none alert alert-info py-2 small mb-3">
            <span class="spinner-border spinner-border-sm me-2"></span> Fotos werden hochgeladen...
          </div>

          <div class="row g-2 mb-4" id="album-photos-grid">
            <?php if (empty($photos)): ?>
              <div class="col-12 text-center py-5 border rounded-3 bg-light text-muted small" id="album-photos-empty-hint">
                <i class="bi bi-images fs-2 d-block mb-2 text-primary"></i>
                Wähle oben über <strong>„Fotos auswählen &amp; hochladen"</strong> mindestens 4 Fotos (JPG oder PNG) aus.
                Du kannst mehrere Dateien gleichzeitig markieren.
              </div>
            <?php else: ?>
              <?php foreach ($photos as $ph): ?>
                <div class="col-6 col-sm-4 col-md-3 js-album-photo-card" data-file-id="<?= htmlspecialchars((string)$ph['file_id'], ENT_QUOTES, 'UTF-8') ?>">
                  <div class="position-relative border rounded-3 overflow-hidden bg-light shadow-sm">
                    <a href="#" class="js-zoom-album-photo d-block"
                       data-zoom-url="<?= htmlspecialchars((string)$ph['zoom_url'], ENT_QUOTES, 'UTF-8') ?>">
                      <img src="<?= htmlspecialchars((string)$ph['thumb_url'], ENT_QUOTES, 'UTF-8') ?>"
                           alt="Album Foto"
                           class="img-fluid w-100"
                           style="height: 110px; object-fit: cover;"
                           loading="lazy">
                    </a>
                    <button type="button"
                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1 js-delete-album-photo-btn"
                            data-album-id="<?= $albumDbId ?>"
                            data-file-id="<?= htmlspecialchars((string)$ph['file_id'], ENT_QUOTES, 'UTF-8') ?>"
                            title="Dieses Foto löschen">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-kkc-primary btn-lg px-4" id="btn-publish-photo-album" data-album-id="<?= $albumDbId ?>">
              <i class="bi bi-check2-circle me-1"></i> Foto-Album fertigstellen &amp; zur Prüfung einreichen
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal für Foto-Großansicht -->
  <div class="modal fade" id="modalAlbumPhotoZoom" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header py-2">
          <h6 class="modal-title fw-bold">Foto-Vorschau</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body text-center bg-dark p-2">
          <img src="" id="modal-album-photo-zoom-img" alt="Vorschau" class="img-fluid rounded" style="max-height: 75vh;">
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
          <h4 class="fw-bold mb-2">Dein Foto-Album wurde erfolgreich eingereicht!</h4>
          <p class="text-muted mb-4">
            Vielen Dank! Dein Foto-Album befindet sich nun in der Freigabeprüfung und geht nach erfolgreicher Kontrolle zum gewählten Startzeitpunkt automatisch online.
          </p>
          <div class="d-flex justify-content-center flex-wrap gap-3">
            <?php if ($albumDbId > 0): ?>
              <a href="#/photo-album?id=<?= $albumDbId ?>" class="btn btn-kkc-primary">
                <i class="bi bi-pencil-square me-1"></i> Foto-Album öffnen
              </a>
            <?php endif; ?>
            <a href="#/photo-album-upload" class="btn btn-outline-kkc">
              <i class="bi bi-plus-circle me-1"></i> Weiteres Foto-Album anlegen
            </a>
            <a href="#/photo-albums" class="btn btn-outline-secondary">
              <i class="bi bi-images me-1"></i> Zur Foto-Alben-Übersicht
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
