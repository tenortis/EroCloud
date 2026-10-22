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
$numPhotos            = (int)($album['number_of_photos'] ?? count($photos));
$hasFsk16             = !empty($album['has_fsk16']);
$hasFsk18             = !empty($album['has_fsk18']);
$posterFsk16Url       = (string)($album['poster_fsk16_url'] ?? '');
$posterFsk16ZoomUrl   = (string)($album['poster_fsk16_zoom_url'] ?? $posterFsk16Url);
$posterFsk16EditorUrl = (string)($album['poster_fsk16_editor_url'] ?? $posterFsk16Url);
$posterFsk18Url       = (string)($album['poster_fsk18_url'] ?? '');
$posterFsk18ZoomUrl   = (string)($album['poster_fsk18_zoom_url'] ?? $posterFsk18Url);
$posterFsk18EditorUrl = (string)($album['poster_fsk18_editor_url'] ?? $posterFsk18Url);
$albumChecked         = (string)($album['album_checked'] ?? '0000-00-00 00:00:00');
$released        = (int)($album['released'] ?? 0);
$isOnline        = !empty($album['is_online']);
$canDelete       = !empty($album['can_delete']);
$metaTitle       = (string)($album['meta_title'] ?? '');
$metaDescription = (string)($album['meta_description'] ?? '');
$seoUrl          = (string)($album['seo_url'] ?? '');

$step1Done = ($numPhotos >= 4 && $hasFsk16 && $hasFsk18);
$step2Done = ($albumChecked !== '0000-00-00 00:00:00' && $released === 1 && $rejectionInfo === null);
$step2Warn = ($released === 2 || (string)($album['status'] ?? 'active') === 'blocked' || $rejectionInfo !== null);
$step3Done = $isOnline;

// Preis-Optionen in Cent (0 = Kostenlos, 100..5000 = 1,00 € bis 50,00 € in 1-Euro-Schritten)
$priceCentsOptions = range(0, 5000, 100);
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
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <button type="button" class="btn btn-outline-kkc" data-bs-toggle="modal" data-bs-target="#modalPhotoAlbumQualityTips">
      <i class="bi bi-lightbulb text-warning me-1"></i> Tipps für mehr Verkäufe
    </button>
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
      <div class="col-12 col-md-4" id="photo-album-step1-status-box">
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
    <!-- Coverbilder & Cover-Studio -->
    <div class="kkc-card mb-4" id="photo-album-covers-card"
         data-album-id="<?= $albumDbId ?>"
         data-has-fsk16="<?= $hasFsk16 ? '1' : '0' ?>"
         data-has-fsk18="<?= $hasFsk18 ? '1' : '0' ?>">
      <div class="kkc-card-header">
        <div>
          <h6 class="mb-0 fw-bold"><i class="bi bi-card-image me-2 text-primary"></i>Album-Coverbilder &amp; Cover-Studio</h6>
          <div class="small text-muted">Gestalte dein FSK16- &amp; FSK18-Cover direkt aus deinen Album-Fotos oder lade ein eigenes Bild hoch</div>
        </div>
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
          <i class="bi bi-sliders me-1"></i>Vollbild Cover-Studio
        </span>
      </div>
      <div class="kkc-card-body">
        <!-- Hinweis bei noch fehlendem FSK16/FSK18 Cover -->
        <div id="fsk16-missing-warning" class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3 mb-3 small <?= ($hasFsk16 && $hasFsk18) ? 'd-none' : '' ?>">
          <i class="bi bi-shield-exclamation text-warning fs-5 mt-1"></i>
          <div>
            <strong>Wichtiger Hinweis zu FSK 16 &amp; FSK 18:</strong>
            Für die Freigabe deines Foto-Albums wird sowohl ein <strong>jugendfreies FSK 16 Cover</strong> (ohne sichtbare primäre Geschlechtsmerkmale) als auch ein <strong>FSK 18 Cover</strong> benötigt.
            Du kannst jedes deiner Album-Fotos unten mit einem Klick im <strong>Cover-Studio</strong> öffnen, sensible Stellen verpixeln oder mit Milchglas unkenntlich machen, Badges/Texte einfügen und direkt als Cover speichern!
          </div>
        </div>

        <div class="row g-3">
          <!-- FSK 16 Cover -->
          <div class="col-12 col-sm-6">
            <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column justify-content-between" id="card-fsk16-box">
              <div>
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                  <span class="badge bg-success">FSK 16 (Jugendfrei)</span>
                  <span class="badge <?= $hasFsk16 ? 'bg-primary' : 'bg-warning text-dark' ?>" id="badge-fsk16-source">
                    <?= $hasFsk16 ? 'Eigenes Cover' : 'Fehlt noch' ?>
                  </span>
                </div>

                <!-- Bild-Box (wenn FSK 16 Cover vorhanden) -->
                <div class="position-relative mb-2 <?= $hasFsk16 ? '' : 'd-none' ?>" id="preview-fsk16-img-wrap">
                  <img id="preview-fsk16-img"
                       src="<?= htmlspecialchars($posterFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK 16 Cover"
                       class="img-fluid rounded border bg-white js-hover-zoom-thumb js-open-thumb-editor-modal"
                       data-zoom-src="<?= htmlspecialchars($posterFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-title="Aktuelles FSK 16 Album-Cover (Jugendfrei)"
                       data-scene="cover_16"
                       data-label="FSK16 Cover"
                       data-editor-url="<?= htmlspecialchars($posterFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-target-fsk="16"
                       data-default-tab="censor"
                       style="height: 150px; width: 100%; object-fit: cover; cursor: pointer;">
                </div>

                <!-- Platzhalter-Box (wenn noch kein FSK 16 Cover gewählt wurde) -->
                <div id="empty-fsk16-placeholder"
                     class="rounded border border-2 border-warning border-dashed bg-warning bg-opacity-10 d-flex flex-column align-items-center justify-content-center p-2 mb-2 js-open-active-fsk-editor <?= $hasFsk16 ? 'd-none' : '' ?>"
                     data-target-fsk="16"
                     data-scene="cover_16"
                     data-label="FSK16 Cover"
                     data-editor-url="<?= htmlspecialchars($posterFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                     data-studio-ready="<?= $hasFsk16 ? '1' : '0' ?>"
                     style="height: 150px; cursor: pointer;"
                     title="Klicken, um ein FSK 16 Cover im Studio zu erstellen">
                  <i class="bi bi-shield-exclamation fs-2 text-warning mb-1"></i>
                  <span class="fw-bold small text-dark">Kein FSK 16 Cover</span>
                  <span class="text-muted" style="font-size: 0.7rem;">Im Studio aus Album-Foto erstellen oder Bild hochladen</span>
                </div>
              </div>

              <div class="d-grid gap-1">
                <button type="button"
                        class="btn btn-xs btn-kkc-primary w-100 py-1 js-open-active-fsk-editor"
                        id="btn-open-studio-fsk16"
                        data-target-fsk="16"
                        data-scene="cover_16"
                        data-label="FSK16 Cover"
                        data-editor-url="<?= htmlspecialchars($posterFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                        data-studio-ready="<?= $hasFsk16 ? '1' : '0' ?>">
                  <i class="bi bi-magic me-1"></i> FSK 16 im Studio bearbeiten
                </button>
                <label class="btn btn-xs btn-outline-secondary w-100 py-1 mb-0" style="font-size: 0.72rem;">
                  <i class="bi bi-upload me-1"></i> Fertiges Bild direkt hochladen
                  <input type="file" class="d-none js-album-cover-upload"
                         data-album-id="<?= $albumDbId ?>"
                         data-fsk="16"
                         accept=".jpg,.jpeg,.png">
                </label>
              </div>
            </div>
          </div>

          <!-- FSK 18 Cover -->
          <div class="col-12 col-sm-6">
            <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column justify-content-between" id="card-fsk18-box">
              <div>
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                  <span class="badge bg-danger">FSK 18 (Unzensiert)</span>
                  <span class="badge <?= $hasFsk18 ? 'bg-primary' : 'bg-warning text-dark' ?>" id="badge-fsk18-source">
                    <?= $hasFsk18 ? 'Eigenes Cover' : 'Fehlt noch' ?>
                  </span>
                </div>

                <!-- Bild-Box (wenn FSK 18 Cover vorhanden) -->
                <div class="position-relative mb-2 <?= $hasFsk18 ? '' : 'd-none' ?>" id="preview-fsk18-img-wrap">
                  <img id="preview-fsk18-img"
                       src="<?= htmlspecialchars($posterFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                       alt="FSK 18 Cover"
                       class="img-fluid rounded border bg-white js-hover-zoom-thumb js-open-thumb-editor-modal"
                       data-zoom-src="<?= htmlspecialchars($posterFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-title="Aktuelles FSK 18 Album-Cover (Hardcore)"
                       data-scene="cover_18"
                       data-label="FSK18 Cover"
                       data-editor-url="<?= htmlspecialchars($posterFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-target-fsk="18"
                       data-default-tab="adjust"
                       style="height: 150px; width: 100%; object-fit: cover; cursor: pointer;">
                </div>

                <!-- Platzhalter-Box (wenn noch kein FSK 18 Cover gewählt wurde) -->
                <div id="empty-fsk18-placeholder"
                     class="rounded border border-2 border-danger border-dashed bg-danger bg-opacity-10 d-flex flex-column align-items-center justify-content-center p-2 mb-2 js-open-active-fsk-editor <?= $hasFsk18 ? 'd-none' : '' ?>"
                     data-target-fsk="18"
                     data-scene="cover_18"
                     data-label="FSK18 Cover"
                     data-editor-url="<?= htmlspecialchars($posterFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                     data-studio-ready="<?= $hasFsk18 ? '1' : '0' ?>"
                     style="height: 150px; cursor: pointer;"
                     title="Klicken, um ein FSK 18 Cover im Studio zu erstellen">
                  <i class="bi bi-fire fs-2 text-danger mb-1"></i>
                  <span class="fw-bold small text-dark">Kein FSK 18 Cover</span>
                  <span class="text-muted" style="font-size: 0.7rem;">Im Studio aus Album-Foto erstellen oder Bild hochladen</span>
                </div>
              </div>

              <div class="d-grid gap-1">
                <button type="button"
                        class="btn btn-xs btn-outline-kkc w-100 py-1 js-open-active-fsk-editor"
                        id="btn-open-studio-fsk18"
                        data-target-fsk="18"
                        data-scene="cover_18"
                        data-label="FSK18 Cover"
                        data-editor-url="<?= htmlspecialchars($posterFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                        data-studio-ready="<?= $hasFsk18 ? '1' : '0' ?>">
                  <i class="bi bi-sliders me-1"></i> FSK 18 im Studio bearbeiten
                </button>
                <label class="btn btn-xs btn-outline-secondary w-100 py-1 mb-0" style="font-size: 0.72rem;">
                  <i class="bi bi-upload me-1"></i> Fertiges Bild direkt hochladen
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

    <!-- Enthaltene Fotos im Album -->
    <div class="kkc-card mb-4">
      <div class="kkc-card-header">
        <div>
          <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Fotos im Album (<span id="album-photos-grid-count"><?= $numPhotos ?></span>)</h6>
          <div class="small text-muted">Klicke bei einem Foto auf <strong>FSK16</strong> oder <strong>FSK18</strong>, um es direkt im Cover-Studio als Cover-Vorlage zu öffnen</div>
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
            <?php foreach ($photos as $idx => $ph): ?>
              <?php
                $phNum       = (string)($ph['num'] ?? ('photo_' . ($ph['file_id'] ?? $idx)));
                $phLabel     = (string)($ph['label'] ?? ('Foto #' . ($idx + 1)));
                $phThumbUrl  = (string)($ph['thumb_url'] ?? '');
                $phZoomUrl   = (string)($ph['zoom_url'] ?? $phThumbUrl);
                $phEditorUrl = (string)($ph['editor_url'] ?? $phZoomUrl);
                $phFileId    = (string)($ph['file_id'] ?? '');
                $phRejected  = !empty($ph['rejected']);
              ?>
              <div class="col-6 col-sm-4 col-md-3 js-album-photo-card"
                   data-file-id="<?= htmlspecialchars($phFileId, ENT_QUOTES, 'UTF-8') ?>"
                   data-scene-num="<?= htmlspecialchars($phNum, ENT_QUOTES, 'UTF-8') ?>">
                <div class="border <?= $phRejected ? 'border-danger border-2' : '' ?> rounded-3 overflow-hidden bg-light shadow-sm h-100 d-flex flex-column justify-content-between">
                  <div class="position-relative">
                    <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-1 js-album-photo-num-badge" style="font-size: 0.65rem; pointer-events: none;">
                      #<?= (int)($idx + 1) ?>
                    </span>
                    <a href="#" class="js-zoom-album-photo d-block"
                       data-zoom-url="<?= htmlspecialchars($phZoomUrl, ENT_QUOTES, 'UTF-8') ?>">
                      <img src="<?= htmlspecialchars($phThumbUrl, ENT_QUOTES, 'UTF-8') ?>"
                           alt="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>"
                           class="img-fluid w-100 js-hover-zoom-thumb"
                           data-zoom-src="<?= htmlspecialchars($phZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                           data-zoom-title="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>"
                           style="height: 110px; object-fit: cover; <?= $phRejected ? 'opacity: 0.65;' : '' ?>"
                           loading="lazy">
                    </a>
                    <?php if ($phRejected): ?>
                      <div class="position-absolute bottom-0 start-0 end-0 bg-danger text-white text-center py-1 px-1 fw-bold" style="font-size: 0.65rem;">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>Abgelehnt – bitte löschen
                      </div>
                    <?php endif; ?>
                    <button type="button"
                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1 js-delete-album-photo-btn"
                            data-album-id="<?= $albumDbId ?>"
                            data-file-id="<?= htmlspecialchars($phFileId, ENT_QUOTES, 'UTF-8') ?>"
                            title="Dieses Foto löschen">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                  <div class="p-1 bg-white border-top d-flex gap-1">
                    <button type="button"
                            class="btn btn-xs btn-outline-success flex-fill py-0 px-1 js-open-thumb-editor-modal"
                            data-scene="<?= htmlspecialchars($phNum, ENT_QUOTES, 'UTF-8') ?>"
                            data-label="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>"
                            data-editor-url="<?= htmlspecialchars($phEditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                            data-target-fsk="16"
                            data-default-tab="censor"
                            style="font-size: 0.68rem;"
                            title="Dieses Foto im Cover-Studio als FSK 16 Cover bearbeiten &amp; verpixeln">
                      <i class="bi bi-magic"></i> FSK16
                    </button>
                    <button type="button"
                            class="btn btn-xs btn-outline-danger flex-fill py-0 px-1 js-open-thumb-editor-modal"
                            data-scene="<?= htmlspecialchars($phNum, ENT_QUOTES, 'UTF-8') ?>"
                            data-label="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>"
                            data-editor-url="<?= htmlspecialchars($phEditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                            data-target-fsk="18"
                            data-default-tab="adjust"
                            style="font-size: 0.68rem;"
                            title="Dieses Foto im Cover-Studio als FSK 18 Cover bearbeiten">
                      <i class="bi bi-sliders"></i> FSK18
                    </button>
                  </div>
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
              <input type="text" name="title" class="form-control" maxlength="65" required
                     value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Aussagekräftiger Titel des Foto-Albums (5 bis 65 Zeichen)">
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

<!-- Qualitäts- & Verkaufstipps Modal für Foto-Alben -->
<div class="modal fade" id="modalPhotoAlbumQualityTips" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-lightbulb-fill text-warning me-2"></i>Tipps zur Qualität &amp; Vermarktung deiner Foto-Alben
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-3">
          Mit diesen Empfehlungen holst du das Maximum aus deinen Foto-Alben heraus und sorgst für eine schnelle Freigabe sowie höhere Verkaufszahlen:
        </p>
        <div class="list-group list-group-flush">
          <div class="list-group-item px-0 py-3">
            <h6 class="fw-bold mb-1"><i class="bi bi-images text-primary me-2"></i>1. Optimale Anzahl der Fotos</h6>
            <p class="small text-muted mb-0">
              Für die technische Freigabe sind mindestens 4 Fotos erforderlich. Für attraktive Verkäufe empfehlen wir dir jedoch, <strong>mindestens 10 bis 20 abwechslungsreiche Fotos</strong> pro Album hochzuladen.
            </p>
          </div>
          <div class="list-group-item px-0 py-3">
            <h6 class="fw-bold mb-1"><i class="bi bi-card-heading text-primary me-2"></i>2. Aussagekräftiger Albumtitel (5 bis 65 Zeichen)</h6>
            <p class="small text-muted mb-0">
              Wähle einen spannenden, treffenden Titel, der das Thema, das Outfit oder die Stimmung deiner Bilderserie genau beschreibt. Verwende jeden Titel nur einmal.
            </p>
          </div>
          <div class="list-group-item px-0 py-3">
            <h6 class="fw-bold mb-1"><i class="bi bi-file-earmark-text text-primary me-2"></i>3. Ausführliche Beschreibung für Kunden &amp; Google (SEO)</h6>
            <p class="small text-muted mb-0">
              Wir empfehlen eine detaillierte Beschreibung (ideal sind 150 bis 200+ Wörter) mit korrekter Rechtschreibung sowie Groß- und Kleinschreibung.
              Eine ausführliche Beschreibung überzeugt nicht nur deine Käufer, sondern sorgt auch dafür, dass Suchmaschinen wie Google dein Foto-Album besser finden.
            </p>
          </div>
          <div class="list-group-item px-0 py-3">
            <h6 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>4. FSK 16 &amp; FSK 18 Coverbilder im Cover-Studio</h6>
            <p class="small text-muted mb-0">
              Auf dem <strong>FSK 16 Cover</strong> dürfen keine primären Geschlechtsmerkmale sichtbar sein. Nutze einfach unser integriertes <strong>Vollbild Cover-Studio</strong>, um ein Album-Foto mit einem Klick als Cover auszuwählen, sensible Stellen zu verpixeln oder weichzuzeichnen und verkaufsstarke Badges hinzuzufügen.
            </p>
          </div>
          <div class="list-group-item px-0 py-3 border-bottom-0">
            <h6 class="fw-bold mb-1"><i class="bi bi-camera text-primary me-2"></i>5. Bildqualität &amp; Auflösung</h6>
            <p class="small text-muted mb-0">
              Achte auf scharfe, gut ausgeleuchtete Aufnahmen im JPG- oder PNG-Format (bis 6 MB pro Foto). Lade nur Fotos hoch, die auch du selbst gerne kaufen würdest.
            </p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-kkc-primary px-4" data-bs-dismiss="modal">Verstanden</button>
      </div>
    </div>
  </div>
</div>

<?= \App\Core\View::renderCoverStudioModal([
    'studioContext'        => 'photo-album',
    'studioEntityId'       => $albumDbId,
    'studioTitle'          => $title !== '' ? $title : ('Foto-Album #' . $albumDbId),
    'allowVideoCapture'    => false,
    'hasCustomFsk16'       => $hasFsk16,
    'customFsk16Num'       => 'cover_16',
    'customFsk16Label'     => 'FSK16 Cover',
    'customFsk16Url'       => $posterFsk16Url,
    'customFsk16ZoomUrl'   => $posterFsk16ZoomUrl,
    'customFsk16EditorUrl' => $posterFsk16EditorUrl,
    'hasCustomFsk18'       => $hasFsk18,
    'customFsk18Num'       => 'cover_18',
    'customFsk18Label'     => 'FSK18 Cover',
    'customFsk18Url'       => $posterFsk18Url,
    'customFsk18ZoomUrl'   => $posterFsk18ZoomUrl,
    'customFsk18EditorUrl' => $posterFsk18EditorUrl,
    'filmstripItems'       => $photos,
]) ?>
