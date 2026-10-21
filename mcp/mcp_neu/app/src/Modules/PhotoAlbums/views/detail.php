<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, mixed>             $album
 * @var array<int, array<string, mixed>> $photos
 * @var array<int, array<string, mixed>> $actors
 * @var array<string, string>|null       $rejectionInfo
 */

$albumDbId       = (int)($album['id'] ?? 0);
$title           = (string)($album['title'] ?? '');
$description     = (string)($album['description'] ?? '');
$actorId         = (int)($album['actor_id'] ?? 0);
$onlineAt        = (string)($album['online_at'] ?? '');
$onlineAtDisplay = (string)($album['online_at_display'] ?? '-');
$canEditOnlineAt = !empty($album['can_edit_online_at']);
$amountDownload  = (int)($album['amount_download'] ?? 500);
$numPhotos       = (int)($album['number_of_photos'] ?? count($photos));
$hasFsk16        = !empty($album['has_fsk16']);
$hasFsk18        = !empty($album['has_fsk18']);
$posterFsk16Url  = (string)($album['poster_fsk16_url'] ?? '');
$posterFsk18Url  = (string)($album['poster_fsk18_url'] ?? '');
$albumChecked    = (string)($album['album_checked'] ?? '0000-00-00 00:00:00');
$released        = (int)($album['released'] ?? 0);
$isOnline        = !empty($album['is_online']);
$canDelete       = !empty($album['can_delete']);
$metaTitle       = (string)($album['meta_title'] ?? '');
$metaDescription = (string)($album['meta_description'] ?? '');
$seoUrl          = (string)($album['seo_url'] ?? '');

$step1Done = ($numPhotos >= 4 && $hasFsk16 && $hasFsk18);
$step2Done = ($albumChecked !== '0000-00-00 00:00:00' && $released === 1);
$step2Warn = ($albumChecked === '0000-00-00 00:00:00' && $released === 2);
$step3Done = $isOnline;

// Preis-Optionen in Cent (0 = Kostenlos, 100..5000 = 1,00 € bis 50,00 €)
$priceCentsOptions = [0, 100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 1200, 1400, 1500, 1600, 1800, 2000, 2500, 3000, 3500, 4000, 4500, 5000];
?>

<!-- Kopfbereich -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <a href="#/photo-albums" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-arrow-left me-1"></i> Zur Übersicht
      </a>
      <span class="badge bg-secondary bg-opacity-10 text-secondary border">ID #<?= $albumDbId ?></span>
      <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
        <i class="bi bi-camera me-1"></i><span id="badge-album-photo-count"><?= $numPhotos ?></span> Fotos
      </span>
    </div>
    <h2 class="h4 fw-bold mb-0"><?= htmlspecialchars($title !== '' ? $title : ('Foto-Album #' . $albumDbId), ENT_QUOTES, 'UTF-8') ?></h2>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if ($canDelete): ?>
      <button type="button" class="btn btn-outline-danger js-delete-photo-album-btn"
              data-album-id="<?= $albumDbId ?>"
              data-album-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-trash3 me-1"></i> Album löschen
      </button>
    <?php endif; ?>
    <button type="submit" form="kkc-photo-album-edit-form" class="btn btn-kkc-primary" id="btn-save-photo-album-top">
      <i class="bi bi-check2-circle me-1"></i> Änderungen speichern
    </button>
  </div>
</div>

<!-- 3-Stufen Status-Fortschrittsanzeige -->
<div class="kkc-card mb-4">
  <div class="kkc-card-body py-3">
    <div class="row g-3 text-center">
      <!-- Schritt 1: Fotos & Cover -->
      <div class="col-12 col-md-4">
        <?php if ($step1Done): ?>
          <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
            <div class="fw-bold text-success small">
              <i class="bi bi-check-circle-fill me-1"></i> 1. Fotos &amp; Cover komplett
            </div>
            <div class="text-muted" style="font-size: 0.75rem;"><?= $numPhotos ?> Fotos sowie FSK16 &amp; FSK18 Cover vorhanden</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-warning bg-opacity-10 border-warning border-opacity-25">
            <div class="fw-bold text-warning small">
              <i class="bi bi-exclamation-circle me-1"></i> 1. Bilder unvollständig
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Mind. 4 Fotos sowie FSK16 &amp; FSK18 Cover erforderlich</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Schritt 2: Prüfung -->
      <div class="col-12 col-md-4">
        <?php if ($step2Done): ?>
          <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
            <div class="fw-bold text-success small">
              <i class="bi bi-check-circle-fill me-1"></i> 2. Geprüft
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Freigabe erteilt</div>
          </div>
        <?php elseif ($step2Warn): ?>
          <div class="p-2 rounded-3 border bg-danger bg-opacity-10 border-danger border-opacity-25">
            <div class="fw-bold text-danger small">
              <i class="bi bi-exclamation-triangle-fill me-1"></i> 2. Korrektur nötig
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Siehe Hinweis unten</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-light">
            <div class="fw-bold text-secondary small">
              <i class="bi bi-hourglass-split me-1"></i> 2. In Prüfung
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Wartet auf Freigabe</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Schritt 3: Online -->
      <div class="col-12 col-md-4">
        <?php if ($step3Done): ?>
          <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
            <div class="fw-bold text-success small">
              <i class="bi bi-broadcast me-1"></i> 3. Online
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Aktiv im Verkauf</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-light">
            <div class="fw-bold text-secondary small">
              <i class="bi bi-clock-history me-1"></i> 3. Geplant
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Start: <?= htmlspecialchars($onlineAtDisplay, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Ablehnungs-Hinweis falls released == 2 -->
<?php if ($rejectionInfo !== null): ?>
  <div class="alert alert-danger d-flex align-items-start gap-3 mb-4 shadow-sm">
    <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
    <div>
      <h6 class="fw-bold mb-1">Dein Foto-Album konnte leider noch nicht freigegeben werden</h6>
      <?php if ($rejectionInfo['reason'] !== ''): ?>
        <div class="fw-semibold mb-1">Grund: <?= htmlspecialchars($rejectionInfo['reason'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($rejectionInfo['text'] !== ''): ?>
        <p class="mb-1 small"><?= nl2br(htmlspecialchars($rejectionInfo['text'], ENT_QUOTES, 'UTF-8')) ?></p>
      <?php endif; ?>
      <div class="small text-muted mt-1">
        Bitte korrigiere die Angaben oder Bilder und klicke anschließend auf <strong>„Änderungen speichern"</strong>.
      </div>
    </div>
  </div>
<?php endif; ?>

<div id="photo-album-alert-box"></div>

<div class="row g-4">
  <!-- Linke Spalte: Coverbilder (FSK16 & FSK18) + Fotogalerie -->
  <div class="col-12 col-xl-6">
    <!-- Coverbilder -->
    <div class="kkc-card mb-4">
      <div class="kkc-card-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-card-image me-2 text-primary"></i>Album-Coverbilder (FSK16 &amp; FSK18)</h6>
      </div>
      <div class="kkc-card-body">
        <p class="text-muted small mb-3">
          Lade jeweils ein Coverbild für den <strong>FSK16-Bereich</strong> (jugendfrei) und den <strong>FSK18-Bereich</strong> hoch (JPG oder PNG, max. 6 MB).
        </p>
        <div class="row g-3">
          <!-- FSK 16 Cover -->
          <div class="col-12 col-sm-6">
            <div class="p-3 border rounded-3 bg-light text-center h-100">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-success">FSK 16 Cover (jugendfrei)</span>
              </div>
              <img id="album-cover-fsk16-img"
                   src="<?= htmlspecialchars($posterFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                   alt="FSK 16 Cover"
                   class="img-fluid rounded border mb-3 bg-white"
                   style="height: 150px; width: 100%; object-fit: cover;">
              <label class="btn btn-sm btn-outline-kkc w-100 mb-0">
                <i class="bi bi-upload me-1"></i> FSK16 Cover hochladen
                <input type="file" class="d-none js-album-cover-upload"
                       data-album-id="<?= $albumDbId ?>"
                       data-fsk="16"
                       accept=".jpg,.jpeg,.png">
              </label>
            </div>
          </div>

          <!-- FSK 18 Cover -->
          <div class="col-12 col-sm-6">
            <div class="p-3 border rounded-3 bg-light text-center h-100">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-danger">FSK 18 Cover (Hardcore)</span>
              </div>
              <img id="album-cover-fsk18-img"
                   src="<?= htmlspecialchars($posterFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                   alt="FSK 18 Cover"
                   class="img-fluid rounded border mb-3 bg-white"
                   style="height: 150px; width: 100%; object-fit: cover;">
              <label class="btn btn-sm btn-outline-kkc w-100 mb-0">
                <i class="bi bi-upload me-1"></i> FSK18 Cover hochladen
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

    <!-- Enthaltene Fotos im Album -->
    <div class="kkc-card mb-4">
      <div class="kkc-card-header">
        <div>
          <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Fotos im Album (<span id="album-photos-grid-count"><?= $numPhotos ?></span>)</h6>
        </div>
        <label class="btn btn-sm btn-kkc-primary mb-0">
          <i class="bi bi-plus-lg me-1"></i> Weitere Fotos hinzufügen
          <input type="file" class="d-none js-album-photos-upload"
                 data-album-id="<?= $albumDbId ?>"
                 multiple
                 accept=".jpg,.jpeg,.png">
        </label>
      </div>
      <div class="kkc-card-body">
        <div id="album-photos-upload-status" class="d-none alert alert-info py-2 small mb-3">
          <span class="spinner-border spinner-border-sm me-2"></span> Fotos werden hochgeladen und verarbeitet...
        </div>

        <div class="row g-2" id="album-photos-grid">
          <?php if (empty($photos)): ?>
            <div class="col-12 text-center py-4 text-muted small" id="album-photos-empty-hint">
              Noch keine Fotos im Album vorhanden. Bitte lade mindestens 4 Fotos hoch.
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
      </div>
    </div>
  </div>

  <!-- Rechte Spalte: Basisdaten, Preis & SEO -->
  <div class="col-12 col-xl-6">
    <form id="kkc-photo-album-edit-form" data-album-id="<?= $albumDbId ?>">
      <input type="hidden" name="id" value="<?= $albumDbId ?>">

      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Basisdaten &amp; Verkaufspreis</h6>
        </div>
        <div class="kkc-card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Albumtitel <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" maxlength="100" required
                     value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Aussagekräftiger Titel des Foto-Albums (mind. 5 Zeichen)">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Beschreibung <span class="text-danger">*</span></label>
              <textarea name="description" class="form-control" rows="10"
                        style="min-height: 220px; line-height: 1.6;"
                        maxlength="2000" required
                        placeholder="Beschreibe dein Foto-Album ausführlich (mind. 30 Zeichen)..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
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
              <?php if ($canEditOnlineAt): ?>
                <input type="datetime-local" name="online_at" class="form-control"
                       value="<?= htmlspecialchars($onlineAt, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-text">Geplante Veröffentlichung mindestens 90 Minuten in der Zukunft.</div>
              <?php else: ?>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($onlineAtDisplay, ENT_QUOTES, 'UTF-8') ?>" readonly>
                <div class="form-text">Das Foto-Album ist bereits online. Das Startdatum kann nicht mehr geändert werden.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Optionale SEO-Einstellungen -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-search me-2 text-primary"></i>Suchmaschinen-Optimierung (Optional)</h6>
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

      <div class="d-flex justify-content-end gap-2">
        <a href="#/photo-albums" class="btn btn-outline-kkc">Abbrechen</a>
        <button type="submit" class="btn btn-kkc-primary px-4" id="btn-save-photo-album-bottom">
          <i class="bi bi-check2-circle me-1"></i> Änderungen speichern
        </button>
      </div>
    </form>
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
