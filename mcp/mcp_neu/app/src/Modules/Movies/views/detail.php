<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, mixed>       $movie
 * @var array<int, array<string, mixed>> $availableThumbs
 * @var array<int, array<string, mixed>> $categoryGroups
 * @var array<int, array<string, mixed>> $actors
 * @var array<int, array<string, mixed>> $websites
 * @var array<string, string>|null $rejectionInfo
 */

$movieId         = (int)($movie['id'] ?? 0);
$fileId          = (string)($movie['file_id'] ?? '');
$title           = (string)($movie['title'] ?? '');
$description     = (string)($movie['description'] ?? '');
$onlineAt        = (string)($movie['online_at'] ?? '');
$onlineAtDisplay = (string)($movie['online_at_display'] ?? '-');
$canEditOnlineAt = !empty($movie['can_edit_online_at']);
$playtimeSeconds = (int)($movie['playtime_seconds'] ?? 0);
$playtimeString  = (string)($movie['playtime_string'] ?? '0:00');
$resolution      = (string)($movie['resolution'] ?? '');
$amountSecond    = (string)($movie['amount_second'] ?? '0.8');
$streamingCoins  = (int)($movie['streaming_coins'] ?? 0);
$asDownload      = (int)($movie['as_download'] ?? 1);
$amountDownload  = (int)($movie['amount_download'] ?? 10);
$preview         = (int)($movie['preview'] ?? 0);
$fsk16           = (int)($movie['fsk16'] ?? 1);
$fsk18           = (int)($movie['fsk18'] ?? 2);
$hasCustomFsk16  = !empty($movie['has_custom_fsk16']);
$hasCustomFsk18  = !empty($movie['has_custom_fsk18']);
$customFsk16Url       = (string)($movie['custom_fsk16_url'] ?? '');
$customFsk16ZoomUrl   = (string)($movie['custom_fsk16_zoom_url'] ?? '');
$customFsk16EditorUrl = (string)($movie['custom_fsk16_editor_url'] ?? '');
$customFsk18Url       = (string)($movie['custom_fsk18_url'] ?? '');
$customFsk18ZoomUrl   = (string)($movie['custom_fsk18_zoom_url'] ?? '');
$customFsk18EditorUrl = (string)($movie['custom_fsk18_editor_url'] ?? '');
$playerPosterUrl = (string)($movie['player_poster_url'] ?? '');
$playerVideoUrl  = (string)($movie['player_video_url'] ?? '');

$mcpBaseUrl            = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
$nowTs                 = time();
$currentFsk16ThumbUrl  = $mcpBaseUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk16 . '&w=186&t=' . $nowTs;
$currentFsk16ZoomUrl   = $mcpBaseUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk16 . '&w=640&t=' . $nowTs;
$currentFsk16EditorUrl = $mcpBaseUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk16 . '&w=1024&t=' . $nowTs;

$currentFsk18ThumbUrl  = $mcpBaseUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk18 . '&w=186&t=' . $nowTs;
$currentFsk18ZoomUrl   = $mcpBaseUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk18 . '&w=640&t=' . $nowTs;
$currentFsk18EditorUrl = $mcpBaseUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk18 . '&w=1024&t=' . $nowTs;
$convertStatus   = (int)($movie['convert_status'] ?? 0);
$movieChecked    = (string)($movie['movie_checked'] ?? '0000-00-00 00:00:00');
$released        = (int)($movie['released'] ?? 0);
$isOnline        = !empty($movie['is_online']);
$canDelete       = !empty($movie['can_delete']);
$actorId         = (int)($movie['actor_id'] ?? 0);
$visibleWebsite  = (string)($movie['visible_for_website'] ?? 'public');
$categoryMaster  = (string)($movie['category_master'] ?? 'porn');
$metaTitle       = (string)($movie['meta_title'] ?? '');
$metaDescription = (string)($movie['meta_description'] ?? '');
$seoUrl          = (string)($movie['seo_url'] ?? '');

// Schritt-Status für die 4-stufige Fortschrittsanzeige berechnen
// Step 1: Hochgeladen (immer aktiv, da filename vorhanden)
// Step 2: Konvertiert (convert_status == 2)
// Step 3: Geprüft (movie_checked != '0000-00-00 00:00:00' && released == 1)
// Step 4: Online (isOnline === true)
$step2Done = ($convertStatus === 2);
$step3Done = ($movieChecked !== '0000-00-00 00:00:00' && $released === 1);
$step3Warn = ($movieChecked === '0000-00-00 00:00:00' && $released === 2);
$step4Done = $isOnline;

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
      <span class="badge bg-secondary bg-opacity-10 text-secondary border">ID #<?= $movieId ?></span>
      <?php if ($playtimeString !== ''): ?>
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
          <i class="bi bi-clock me-1"></i><?= htmlspecialchars($playtimeString, ENT_QUOTES, 'UTF-8') ?> Min.
        </span>
      <?php endif; ?>
      <?php if ($resolution !== ''): ?>
        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
          <i class="bi bi-aspect-ratio me-1"></i><?= htmlspecialchars($resolution, ENT_QUOTES, 'UTF-8') ?>p
        </span>
      <?php endif; ?>
    </div>
    <h2 class="h4 fw-bold mb-0"><?= htmlspecialchars($title !== '' ? $title : ('Film #' . $movieId), ENT_QUOTES, 'UTF-8') ?></h2>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if ($canDelete): ?>
      <button type="button" class="btn btn-outline-danger" id="btn-delete-movie-detail"
              data-movie-id="<?= $movieId ?>"
              data-movie-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-trash3 me-1"></i> Film löschen
      </button>
    <?php endif; ?>
    <button type="submit" form="kkc-movie-edit-form" class="btn btn-kkc-primary" id="btn-save-movie-top">
      <i class="bi bi-check2-circle me-1"></i> Änderungen speichern
    </button>
  </div>
</div>

<!-- 4-Stufen Status-Fortschrittsanzeige -->
<div class="kkc-card mb-4">
  <div class="kkc-card-body py-3">
    <div class="row g-3 text-center">
      <!-- Schritt 1: Hochgeladen -->
      <div class="col-6 col-md-3">
        <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
          <div class="fw-bold text-success small">
            <i class="bi bi-check-circle-fill me-1"></i> 1. Hochgeladen
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">Videodatei übertragen</div>
        </div>
      </div>
      <!-- Schritt 2: Konvertiert -->
      <div class="col-6 col-md-3">
        <?php if ($step2Done): ?>
          <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
            <div class="fw-bold text-success small">
              <i class="bi bi-check-circle-fill me-1"></i> 2. Konvertiert
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">HD-Streaming bereit</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-warning bg-opacity-10 border-warning border-opacity-25">
            <div class="fw-bold text-warning small">
              <i class="bi bi-gear-wide-connected me-1"></i> 2. Konvertierung
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Wird verarbeitet...</div>
          </div>
        <?php endif; ?>
      </div>
      <!-- Schritt 3: Geprüft -->
      <div class="col-6 col-md-3">
        <?php if ($step3Done): ?>
          <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
            <div class="fw-bold text-success small">
              <i class="bi bi-check-circle-fill me-1"></i> 3. Geprüft
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Freigabe erteilt</div>
          </div>
        <?php elseif ($step3Warn): ?>
          <div class="p-2 rounded-3 border bg-danger bg-opacity-10 border-danger border-opacity-25">
            <div class="fw-bold text-danger small">
              <i class="bi bi-exclamation-triangle-fill me-1"></i> 3. Korrektur nötig
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Siehe Hinweis unten</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-light">
            <div class="fw-bold text-secondary small">
              <i class="bi bi-hourglass-split me-1"></i> 3. Prüfung
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">In Warteschlange</div>
          </div>
        <?php endif; ?>
      </div>
      <!-- Schritt 4: Online -->
      <div class="col-6 col-md-3">
        <?php if ($step4Done): ?>
          <div class="p-2 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25">
            <div class="fw-bold text-success small">
              <i class="bi bi-broadcast me-1"></i> 4. Online
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Aktiv im Verkauf</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-light">
            <div class="fw-bold text-secondary small">
              <i class="bi bi-clock-history me-1"></i> 4. Geplant
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
      <h6 class="fw-bold mb-1">Dein Film konnte leider noch nicht freigegeben werden</h6>
      <?php if ($rejectionInfo['reason'] !== ''): ?>
        <div class="fw-semibold mb-1">Grund: <?= htmlspecialchars($rejectionInfo['reason'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($rejectionInfo['text'] !== ''): ?>
        <p class="mb-1 small"><?= nl2br(htmlspecialchars($rejectionInfo['text'], ENT_QUOTES, 'UTF-8')) ?></p>
      <?php endif; ?>
      <div class="small text-muted mt-1">
        Bitte passe die Angaben entsprechend an und klicke anschließend auf <strong>„Änderungen speichern"</strong>, damit dein Film erneut geprüft werden kann.
      </div>
    </div>
  </div>
<?php endif; ?>

<div id="movie-detail-alert-box"></div>

<form id="kkc-movie-edit-form" data-movie-id="<?= $movieId ?>" data-playtime-seconds="<?= $playtimeSeconds ?>">
  <input type="hidden" name="movie_id" value="<?= $movieId ?>">
  <input type="hidden" name="id" value="<?= $movieId ?>">
  <input type="hidden" name="fsk16" id="input-preview-fsk16" value="<?= $fsk16 ?>">
  <input type="hidden" name="fsk18" id="input-preview-fsk18" value="<?= $fsk18 ?>">

  <div class="row g-4">
    <!-- Linke Spalte: Video-Player + Vorschaubilder (FSK16 / FSK18) -->
    <div class="col-12 col-xl-5">
      <!-- Video-Player Kachel -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-play-btn me-2 text-primary"></i>Video-Vorschau</h6>
          <span class="badge bg-light text-dark border"><?= htmlspecialchars($playtimeString, ENT_QUOTES, 'UTF-8') ?> Min.</span>
        </div>
        <div class="kkc-card-body">
          <?php if ($convertStatus === 2): ?>
            <div class="ratio ratio-16x9 rounded-3 overflow-hidden bg-dark shadow-sm mb-2">
              <video id="movie-detail-video-player" controls preload="metadata" poster="<?= htmlspecialchars($playerPosterUrl, ENT_QUOTES, 'UTF-8') ?>" class="w-100 h-100">
                <source src="<?= htmlspecialchars($playerVideoUrl, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                Dein Browser unterstützt leider keine HTML5-Videowiedergabe.
              </video>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-1">
              <span class="text-muted small">
                <i class="bi bi-camera me-1 text-primary"></i>Pausiere das Video an deiner Wunschstelle für einen Live-Schnappschuss:
              </span>
              <div class="d-flex gap-1">
                <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 small js-capture-video-to-studio" data-target-fsk="16">
                  <i class="bi bi-camera-fill me-1"></i>Als FSK 16 im Studio öffnen
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 small js-capture-video-to-studio" data-target-fsk="18">
                  <i class="bi bi-camera-fill me-1"></i>Als FSK 18 im Studio öffnen
                </button>
              </div>
            </div>
          <?php else: ?>
            <div class="p-4 rounded-3 bg-light border text-center">
              <div class="spinner-border text-primary mb-2" role="status"></div>
              <h6 class="fw-bold mb-1">Video wird aktuell konvertiert</h6>
              <p class="text-muted small mb-0">
                Sobald die Konvertierung abgeschlossen ist, stehen dir hier der Video-Player sowie die automatisch generierten Vorschaubilder zur Verfügung.
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Vorschaubilder (FSK16 & FSK18) -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-images me-2 text-primary"></i>Vorschaubilder &amp; Cover-Studio</h6>
          <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small">
            <i class="bi bi-stars me-1"></i>Fullscreen Cover-Studio
          </span>
        </div>
        <div class="kkc-card-body">
          <p class="text-muted small mb-3">
            Fahre mit der Maus über ein Bild für die <strong>Live-Lupe</strong> oder öffne das <strong>Fullscreen Cover-Studio</strong> (Milchglas, Verpixeln, Sticker, Licht &amp; Farbe, Badges, Text &amp; Logo).
          </p>

          <!-- Aktuelle Auswahl nebeneinander -->
          <div class="row g-3 mb-4">
            <!-- Aktives FSK 16 Cover -->
            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-success">FSK 16 Cover</span>
                    <span class="small text-muted fw-semibold" id="badge-fsk16-num">
                      <?= $fsk16 === 11 ? 'Eigenes Bild (#11)' : ('Szene #' . $fsk16) ?>
                    </span>
                  </div>
                  <div class="position-relative d-inline-block w-100 mb-2 cursor-pointer js-hover-zoom-thumb js-open-thumb-editor-modal"
                       id="current-fsk16-wrap"
                       data-movie-id="<?= $movieId ?>"
                       data-num="<?= $fsk16 ?>"
                       data-fsk="16"
                       data-target-fsk="16"
                       data-thumb-url="<?= htmlspecialchars($currentFsk16ThumbUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-src="<?= htmlspecialchars($currentFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-editor-url="<?= htmlspecialchars($currentFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-fsk="16"
                       data-zoom-title="Aktuelles FSK 16 Cover (Klick für FSK 16 Cover-Studio)">
                    <img id="current-fsk16-preview"
                         src="<?= htmlspecialchars($currentFsk16ThumbUrl, ENT_QUOTES, 'UTF-8') ?>"
                         alt="FSK16"
                         class="img-fluid rounded border w-100"
                         style="height: 115px; object-fit: cover;">
                    <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 end-0 m-1 small">
                      <i class="bi bi-magic me-1"></i>FSK 16 Studio
                    </span>
                  </div>
                </div>
                <div class="d-grid gap-1">
                  <button type="button" class="btn btn-sm btn-success fw-semibold js-open-active-fsk-editor" data-fsk="16">
                    <i class="bi bi-shield-check me-1"></i> FSK 16 im Studio bearbeiten
                  </button>
                  <label class="btn btn-sm btn-outline-kkc mb-0">
                    <i class="bi bi-upload me-1"></i> Eigenes FSK16 direkt hochladen
                    <input type="file" class="d-none js-movie-poster-upload" data-fsk="16" data-movie-id="<?= $movieId ?>" accept=".jpg,.jpeg,.png">
                  </label>
                  <button type="button"
                          id="btn-restore-custom-fsk16"
                          class="btn btn-sm btn-outline-success js-select-movie-thumb <?= ($hasCustomFsk16 && $fsk16 !== 11) ? '' : 'd-none' ?>"
                          data-fsk="16"
                          data-num="11"
                          data-has-custom="<?= $hasCustomFsk16 ? '1' : '0' ?>"
                          data-url="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                          data-zoom-url="<?= htmlspecialchars($customFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                          data-editor-url="<?= htmlspecialchars($customFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Wieder eigenes FSK16 (#11)
                  </button>
                </div>
              </div>
            </div>

            <!-- Aktives FSK 18 Cover -->
            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-danger">FSK 18 Cover</span>
                    <span class="small text-muted fw-semibold" id="badge-fsk18-num">
                      <?= $fsk18 === 12 ? 'Eigenes Bild (#12)' : ('Szene #' . $fsk18) ?>
                    </span>
                  </div>
                  <div class="position-relative d-inline-block w-100 mb-2 cursor-pointer js-hover-zoom-thumb js-open-thumb-editor-modal"
                       id="current-fsk18-wrap"
                       data-movie-id="<?= $movieId ?>"
                       data-num="<?= $fsk18 ?>"
                       data-fsk="18"
                       data-target-fsk="18"
                       data-thumb-url="<?= htmlspecialchars($currentFsk18ThumbUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-src="<?= htmlspecialchars($currentFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-editor-url="<?= htmlspecialchars($currentFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-fsk="18"
                       data-zoom-title="Aktuelles FSK 18 Cover (Klick für FSK 18 Cover-Studio)">
                    <img id="current-fsk18-preview"
                         src="<?= htmlspecialchars($currentFsk18ThumbUrl, ENT_QUOTES, 'UTF-8') ?>"
                         alt="FSK18"
                         class="img-fluid rounded border w-100"
                         style="height: 115px; object-fit: cover;">
                    <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 end-0 m-1 small">
                      <i class="bi bi-stars me-1"></i>FSK 18 Studio
                    </span>
                  </div>
                </div>
                <div class="d-grid gap-1">
                  <button type="button" class="btn btn-sm btn-danger fw-semibold js-open-active-fsk-editor" data-fsk="18">
                    <i class="bi bi-fire me-1"></i> FSK 18 im Studio bearbeiten
                  </button>
                  <label class="btn btn-sm btn-outline-kkc mb-0">
                    <i class="bi bi-upload me-1"></i> Eigenes FSK18 direkt hochladen
                    <input type="file" class="d-none js-movie-poster-upload" data-fsk="18" data-movie-id="<?= $movieId ?>" accept=".jpg,.jpeg,.png">
                  </label>
                  <button type="button"
                          id="btn-restore-custom-fsk18"
                          class="btn btn-sm btn-outline-danger js-select-movie-thumb <?= ($hasCustomFsk18 && $fsk18 !== 12) ? '' : 'd-none' ?>"
                          data-fsk="18"
                          data-num="12"
                          data-has-custom="<?= $hasCustomFsk18 ? '1' : '0' ?>"
                          data-url="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                          data-zoom-url="<?= htmlspecialchars($customFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                          data-editor-url="<?= htmlspecialchars($customFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Wieder eigenes FSK18 (#12)
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Bereits hochgeladene eigene Bilder (#11 & #12) zum schnellen Zurückwechseln -->
          <div id="custom-uploaded-thumbs-section" class="mb-3 <?= ($hasCustomFsk16 || $hasCustomFsk18) ? '' : 'd-none' ?>">
            <div class="small fw-semibold mb-2 text-primary">
              <i class="bi bi-cloud-check me-1"></i>Deine gespeicherten eigenen Coverbilder (jederzeit wieder wählbar):
            </div>
            <div class="row g-2">
              <div class="col-6 <?= $hasCustomFsk16 ? '' : 'd-none' ?>" id="custom-thumb-card-11">
                <div class="p-2 border rounded-2 bg-white text-center shadow-sm">
                  <div class="position-relative cursor-pointer mb-1 js-hover-zoom-thumb js-open-thumb-editor-modal"
                       id="custom-thumb-wrap-11"
                       data-movie-id="<?= $movieId ?>"
                       data-num="11"
                       data-fsk="16"
                       data-thumb-url="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-src="<?= htmlspecialchars($customFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-editor-url="<?= htmlspecialchars($customFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-fsk="16"
                       data-zoom-title="Eigenes FSK 16 Bild (#11)">
                    <img id="custom-thumb-img-11"
                         src="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                         alt="Eigenes FSK16 (#11)"
                         class="img-fluid rounded"
                         style="height: 76px; width: 100%; object-fit: cover;">
                    <span class="badge bg-success position-absolute top-0 start-0 m-1" style="font-size: 0.65rem;">Eigenes #11</span>
                  </div>
                  <div class="d-flex gap-1 justify-content-center">
                    <button type="button"
                            id="btn-select-custom-11"
                            class="btn btn-xs <?= $fsk16 === 11 ? 'btn-success' : 'btn-outline-secondary' ?> py-0 px-2 small js-select-movie-thumb"
                            data-fsk="16"
                            data-num="11"
                            data-url="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                            data-zoom-url="<?= htmlspecialchars($customFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                            data-editor-url="<?= htmlspecialchars($customFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                      Als FSK16 nutzen
                    </button>
                  </div>
                </div>
              </div>

              <div class="col-6 <?= $hasCustomFsk18 ? '' : 'd-none' ?>" id="custom-thumb-card-12">
                <div class="p-2 border rounded-2 bg-white text-center shadow-sm">
                  <div class="position-relative cursor-pointer mb-1 js-hover-zoom-thumb js-open-thumb-editor-modal"
                       id="custom-thumb-wrap-12"
                       data-movie-id="<?= $movieId ?>"
                       data-num="12"
                       data-fsk="18"
                       data-thumb-url="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-src="<?= htmlspecialchars($customFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-editor-url="<?= htmlspecialchars($customFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-fsk="18"
                       data-zoom-title="Eigenes FSK 18 Bild (#12)">
                    <img id="custom-thumb-img-12"
                         src="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                         alt="Eigenes FSK18 (#12)"
                         class="img-fluid rounded"
                         style="height: 76px; width: 100%; object-fit: cover;">
                    <span class="badge bg-danger position-absolute top-0 start-0 m-1" style="font-size: 0.65rem;">Eigenes #12</span>
                  </div>
                  <div class="d-flex gap-1 justify-content-center">
                    <button type="button"
                            id="btn-select-custom-12"
                            class="btn btn-xs <?= $fsk18 === 12 ? 'btn-danger' : 'btn-outline-secondary' ?> py-0 px-2 small js-select-movie-thumb"
                            data-fsk="18"
                            data-num="12"
                            data-url="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                            data-zoom-url="<?= htmlspecialchars($customFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                            data-editor-url="<?= htmlspecialchars($customFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                      Als FSK18 nutzen
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Automatisch generierte Vorschaubilder 1..10 -->
          <?php if (!empty($availableThumbs)): ?>
            <div class="small fw-semibold mb-2">
              Automatisch generierte Szenenbilder <span class="text-muted fw-normal">(Maus = Lupe, Klick auf Bild = im Cover-Studio öffnen)</span>:
            </div>
            <div class="row g-2">
              <?php foreach ($availableThumbs as $th): ?>
                <?php
                $tNum       = (int)$th['num'];
                $tUrl       = (string)$th['url'];
                $tZoomUrl   = (string)($th['zoom_url'] ?? $tUrl);
                $tEditorUrl = (string)($th['editor_url'] ?? $tZoomUrl);
                ?>
                <div class="col-6 col-sm-4">
                  <div class="p-1 border rounded-2 bg-white text-center h-100">
                    <div class="position-relative cursor-pointer mb-1 js-hover-zoom-thumb js-open-thumb-editor-modal"
                         data-movie-id="<?= $movieId ?>"
                         data-num="<?= $tNum ?>"
                         data-fsk="16"
                         data-target-fsk="16"
                         data-thumb-url="<?= htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') ?>"
                         data-zoom-src="<?= htmlspecialchars($tZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                         data-editor-url="<?= htmlspecialchars($tEditorUrl, ENT_QUOTES, 'UTF-8') ?>"
                         data-zoom-fsk="18"
                         data-zoom-title="Szene #<?= $tNum ?> (Klicken für Fullscreen Cover-Studio)">
                      <img src="<?= htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') ?>"
                           alt="Szene #<?= $tNum ?>"
                           class="img-fluid rounded"
                           style="height: 76px; width: 100%; object-fit: cover;">
                      <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-1" style="font-size: 0.65rem;">#<?= $tNum ?></span>
                      <span class="badge bg-primary bg-opacity-75 position-absolute bottom-0 end-0 m-1" style="font-size: 0.65rem;" title="Im Fullscreen Cover-Studio öffnen">
                        <i class="bi bi-stars"></i> Studio
                      </span>
                    </div>
                    <div class="d-flex gap-1 justify-content-center">
                      <button type="button"
                              class="btn btn-xs <?= $fsk16 === $tNum ? 'btn-success' : 'btn-outline-secondary' ?> py-0 px-2 small js-select-movie-thumb"
                              data-fsk="16"
                              data-num="<?= $tNum ?>"
                              data-url="<?= htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') ?>"
                              data-zoom-url="<?= htmlspecialchars($tZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                              data-editor-url="<?= htmlspecialchars($tEditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                        FSK16
                      </button>
                      <button type="button"
                              class="btn btn-xs <?= $fsk18 === $tNum ? 'btn-danger' : 'btn-outline-secondary' ?> py-0 px-2 small js-select-movie-thumb"
                              data-fsk="18"
                              data-num="<?= $tNum ?>"
                              data-url="<?= htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') ?>"
                              data-zoom-url="<?= htmlspecialchars($tZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                              data-editor-url="<?= htmlspecialchars($tEditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                        FSK18
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="alert alert-light border small mb-0">
              <i class="bi bi-info-circle me-1 text-primary"></i>
              Automatische Szenenbilder werden während der Video-Konvertierung erstellt. Du kannst jedoch jederzeit eigene Coverbilder für FSK16 und FSK18 hochladen.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Rechte Spalte: Basisdaten, Preisgestaltung, Kategorien, SEO -->
    <div class="col-12 col-xl-7">
      <!-- Basisdaten -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Basisdaten &amp; Veröffentlichung</h6>
        </div>
        <div class="kkc-card-body">
          <div class="row g-3">
            <!-- Titel -->
            <div class="col-12">
              <label class="form-label fw-semibold">Filmtitel <span class="text-danger">*</span></label>
              <input type="text" name="title" id="movie-title-input" class="form-control"
                     maxlength="100" required
                     value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Gib einen aussagekräftigen Filmtitel ein (mind. 5 Zeichen)">
            </div>

            <!-- Beschreibung -->
            <div class="col-12">
              <label class="form-label fw-semibold">Filmbeschreibung <span class="text-danger">*</span></label>
              <textarea name="description" id="movie-desc-input" class="form-control" rows="10"
                        style="min-height: 220px; line-height: 1.6;"
                        maxlength="2000" required
                        placeholder="Beschreibe den Inhalt des Films ausführlich (mind. 30 Zeichen)..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Darsteller-Profil (nur anzeigen, wenn mehrere Darsteller vorhanden sind) -->
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

            <!-- Sichtbar für Website -->
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

            <!-- Veröffentlichungszeitpunkt -->
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Veröffentlichungsdatum (Online ab)</label>
              <?php if ($canEditOnlineAt): ?>
                <input type="datetime-local" name="online_at" class="form-control"
                       value="<?= htmlspecialchars($onlineAt, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-text">Mindestens 90 Minuten in der Zukunft für geplante Veröffentlichungen.</div>
              <?php else: ?>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($onlineAtDisplay, ENT_QUOTES, 'UTF-8') ?>" readonly>
                <div class="form-text">Der Film ist bereits online. Das Startdatum kann nicht mehr geändert werden.</div>
              <?php endif; ?>
            </div>

            <!-- Hauptkategorie -->
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

      <!-- Preisgestaltung (Streaming, Download & Trailer-Option) -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-coin me-2 text-primary"></i>Preise &amp; Verkaufsoptionen</h6>
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
                <!-- Streaming-Preis pro Sekunde -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-semibold">Streaming-Preis (Coins pro Sekunde)</label>
                  <select name="amount_second" id="movie-amount-second" class="form-select" <?= $preview === 1 ? 'disabled' : '' ?>>
                    <?php foreach ($amountSecondOptions as $opt): ?>
                      <option value="<?= $opt ?>" <?= $amountSecond === $opt ? 'selected' : '' ?>>
                        <?= str_replace('.', ',', $opt) ?> Coins / Sekunde
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Live-Berechnung Gesamtpreis für kompletten Stream -->
                <div class="col-12 col-md-6">
                  <div class="p-2 px-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 d-flex align-items-center justify-content-between">
                    <span class="small fw-semibold text-primary">Gesamtpreis Stream:</span>
                    <span class="fw-bold text-primary fs-6">
                      ca. <span id="movie-calc-coins"><?= $preview === 1 ? 0 : $streamingCoins ?></span> Coins
                    </span>
                  </div>
                </div>

                <!-- Als Download anbieten -->
                <div class="col-12 col-md-6">
                  <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="movie-as-download"
                           name="as_download" value="1" <?= $asDownload === 1 ? 'checked' : '' ?> <?= $preview === 1 ? 'disabled' : '' ?>>
                    <label class="form-check-label fw-semibold" for="movie-as-download">
                      Film zusätzlich als Komplett-Download anbieten
                    </label>
                  </div>
                </div>

                <!-- Download-Preis in EUR -->
                <div class="col-12 col-md-6" id="movie-download-price-wrap" style="<?= $asDownload === 1 ? '' : 'opacity:0.5;' ?>">
                  <label class="form-label fw-semibold">Download-Verkaufspreis (EUR)</label>
                  <select name="amount_download" id="movie-amount-download" class="form-select" <?= ($asDownload === 1 && $preview !== 1) ? '' : 'disabled' ?>>
                    <?php foreach ($downloadEurOptions as $eur): ?>
                      <option value="<?= $eur ?>" <?= $amountDownload === $eur ? 'selected' : '' ?>>
                        <?= number_format($eur, 2, ',', '.') ?> € (ca. <?= $eur * 32 ?> Coins)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Kategorien (4 Gruppen als Akkordeon inkl. gelber Auto-Erkennung) -->
      <div class="kkc-card mb-4">
        <div class="kkc-card-header">
          <h6 class="mb-0 fw-bold"><i class="bi bi-tags me-2 text-primary"></i>Kategorien (mindestens 1 erforderlich)</h6>
          <span class="badge bg-warning text-dark border">Gelb = vom System erkannt</span>
        </div>
        <div class="kkc-card-body">
          <div class="accordion" id="movieCategoriesAccordion">
            <?php foreach ($categoryGroups as $idx => $group): ?>
              <?php
              $gId      = htmlspecialchars((string)$group['id'], ENT_QUOTES, 'UTF-8');
              $isOpen   = ($idx === 0 || (int)$group['selected_count'] > 0);
              $collapse = 'catCollapse_' . $gId;
              ?>
              <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                <h2 class="accordion-header" id="catHeading_<?= $gId ?>">
                  <button class="accordion-button <?= $isOpen ? '' : 'collapsed' ?> py-2 px-3 fw-semibold"
                          type="button"
                          data-bs-toggle="collapse"
                          data-bs-target="#<?= $collapse ?>"
                          aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                    <span><?= htmlspecialchars((string)$group['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge bg-primary ms-2 js-cat-group-count" data-group-id="<?= $gId ?>">
                      <?= (int)$group['selected_count'] ?> gewählt
                    </span>
                  </button>
                </h2>
                <div id="<?= $collapse ?>" class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>">
                  <div class="accordion-body py-3">
                    <div class="row g-2">
                      <?php foreach ($group['items'] as $catItem): ?>
                        <?php
                        $nameId    = (string)$catItem['name_id'];
                        $isChecked = !empty($catItem['is_checked']);
                        $isSys     = !empty($catItem['is_system_detected']);
                        $boxStyle  = $isSys ? 'background-color: #fff6d0; border: 1px solid #ffe8a1;' : '';
                        ?>
                        <div class="col-12 col-sm-6 col-md-4">
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
                     placeholder="Wird automatisch generiert, falls leer">
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
                        placeholder="Kurze Zusammenfassung für Suchmaschinen (max. 160 Zeichen)"><?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Speichern-Leiste unten -->
      <div class="d-flex justify-content-end gap-2">
        <a href="#/movies" class="btn btn-outline-kkc">Abbrechen</a>
        <button type="submit" class="btn btn-kkc-primary px-4" id="btn-save-movie-bottom">
          <i class="bi bi-check2-circle me-1"></i> Änderungen speichern
        </button>
      </div>
    </div>
  </div>
</form>

<!-- Fullscreen Cover-Studio Modal (FSK 16 & FSK 18) -->
<div class="modal fade ec-cover-studio-modal" id="modalMovieThumbEditor" tabindex="-1" aria-hidden="true" data-movie-id="<?= $movieId ?>">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content d-flex flex-column h-100">

      <!-- 1. Studio Header -->
      <div class="ec-studio-header">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-2 py-1">
            <i class="bi bi-stars me-1"></i>Cover-Studio
          </span>
          <h5 class="modal-title fw-bold mb-0 fs-6 text-white text-truncate" style="max-width: 320px;" id="thumb-editor-modal-title">
            <?= htmlspecialchars($title !== '' ? $title : ('Film #' . $movieId), ENT_QUOTES, 'UTF-8') ?>
          </h5>
          <span class="badge bg-dark text-info border border-info border-opacity-50 small" id="studio-source-badge">
            Szene #1
          </span>
        </div>

        <!-- Ziel-Modus Umschalter: FSK 16 vs. FSK 18 -->
        <div class="d-flex align-items-center gap-2">
          <span class="ec-studio-subtext fw-semibold d-none d-md-inline">Bearbeitungs-Ziel:</span>
          <div class="ec-studio-mode-switch" role="group" aria-label="Ziel-Cover FSK Modus">
            <button type="button" class="ec-studio-mode-btn active-fsk16" id="btn-studio-mode-fsk16" data-target-fsk="16">
              <i class="bi bi-shield-check"></i>
              <span>FSK 16 Cover (Jugendfrei)</span>
            </button>
            <button type="button" class="ec-studio-mode-btn" id="btn-studio-mode-fsk18" data-target-fsk="18">
              <i class="bi bi-fire"></i>
              <span>FSK 18 Cover (Uncut)</span>
            </button>
          </div>
        </div>

        <!-- Globale Studio-Aktionen (Reset / Schließen) -->
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-warning" id="btn-studio-reset-all" title="Alle Bearbeitungen auf diesem Bild zurücksetzen">
            <i class="bi bi-arrow-repeat me-1"></i><span class="d-none d-sm-inline">Alles zurücksetzen</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal" aria-label="Schließen">
            <i class="bi bi-x-lg me-1"></i>Schließen
          </button>
        </div>
      </div>

      <!-- 2. Studio Hauptbereich (Links: Bühne + Filmstreifen | Rechts: Werkzeuge) -->
      <div class="ec-studio-body">

        <!-- Linke Spalte: Interaktive Zeichenfläche + Filmstreifen -->
        <div class="ec-studio-main">

          <!-- Canvas- & Video-Bühne -->
          <div class="ec-studio-stage">
            <!-- Ladeanzeige -->
            <div id="thumb-editor-loading" class="text-center p-5 text-light">
              <div class="spinner-border text-info mb-2" role="status"></div>
              <div class="ec-studio-subtext">Hochauflösendes Coverbild wird im Studio geladen...</div>
            </div>

            <!-- Live-Video-Schnappschuss direkt zentriert auf der Bühne (ersetzt temporär das Canvas ohne Platzverlust) -->
            <?php if ($convertStatus === 2 && $playerVideoUrl !== ''): ?>
              <div id="studio-video-capture-drawer" class="ec-studio-video-stage-card d-none">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                  <div>
                    <h6 class="fw-bold text-info mb-0">
                      <i class="bi bi-camera-reels me-1"></i>Live-Schnappschuss aus deinem Video aufnehmen
                    </h6>
                    <div class="ec-studio-subtext">
                      Spule im Video exakt zu deiner Wunsch-Sekunde, pausiere kurz und übernimm das Standbild direkt auf die Studio-Leinwand.
                    </div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-light" id="btn-studio-close-video-drawer">
                    <i class="bi bi-arrow-left me-1"></i>Zurück zur Leinwand
                  </button>
                </div>

                <div class="ec-studio-video-player-box mb-3">
                  <video id="studio-capture-video" controls preload="metadata" poster="<?= htmlspecialchars($playerPosterUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <source src="<?= htmlspecialchars($playerVideoUrl, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                  </video>
                </div>

                <div class="d-flex justify-content-end gap-2">
                  <button type="button" class="btn btn-outline-light" id="btn-studio-cancel-video-drawer">
                    Abbrechen
                  </button>
                  <button type="button" class="btn btn-info fw-bold text-dark px-4" id="btn-studio-grab-video-frame">
                    <i class="bi bi-camera-fill me-1"></i> Diesen Moment auf die Leinwand übernehmen
                  </button>
                </div>
              </div>
            <?php endif; ?>

            <!-- Haupt-Zeichenfläche (Canvas) -->
            <div class="ec-studio-canvas-wrap d-none" id="studio-canvas-container">
              <canvas id="movie-thumb-editor-canvas" style="cursor: crosshair;"></canvas>
            </div>
          </div>

          <!-- Unterer Filmstreifen (Quellen-Auswahl: Eigenes Bild, Video-Frame, #11, #12, Szenen #1..#10) -->
          <div class="ec-studio-filmstrip" id="studio-filmstrip">
            <!-- Aktion 1: Lokales Bild vom Gerät in den Editor laden -->
            <label class="ec-filmstrip-item mb-0"
                   style="border-style: dashed;"
                   title="Eigenes Foto von deinem Gerät im Studio öffnen">
              <div class="d-flex align-items-center justify-content-center" style="height: 48px;">
                <i class="bi bi-folder2-open text-info fs-5"></i>
              </div>
              <div class="ec-filmstrip-label text-info">Eigenes Foto</div>
              <input type="file" id="studio-local-image-input" class="d-none" accept=".jpg,.jpeg,.png,.webp">
            </label>

            <!-- Aktion 2: Live-Schnappschuss aus dem Video -->
            <?php if ($convertStatus === 2 && $playerVideoUrl !== ''): ?>
              <div class="ec-filmstrip-item"
                   id="btn-studio-toggle-video-drawer"
                   style="border-style: dashed;"
                   title="Live-Schnappschuss direkt aus dem Video aufnehmen">
                <div class="d-flex align-items-center justify-content-center" style="height: 48px;">
                  <i class="bi bi-camera-reels text-warning fs-5"></i>
                </div>
                <div class="ec-filmstrip-label text-warning">Aus Video</div>
              </div>
            <?php endif; ?>

            <!-- Gespeichertes eigenes FSK 16 Bild (#11) -->
            <div class="ec-filmstrip-item js-studio-filmstrip-thumb <?= $hasCustomFsk16 ? '' : 'd-none' ?>"
                 id="studio-filmstrip-custom-11"
                 data-num="11"
                 data-thumb-url="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                 data-zoom-url="<?= htmlspecialchars($customFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                 data-editor-url="<?= htmlspecialchars($customFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
              <img id="studio-filmstrip-img-11" src="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>" alt="Eigenes #11">
              <div class="ec-filmstrip-label text-info">FSK16 (#11)</div>
            </div>

            <!-- Gespeichertes eigenes FSK 18 Bild (#12) -->
            <div class="ec-filmstrip-item js-studio-filmstrip-thumb <?= $hasCustomFsk18 ? '' : 'd-none' ?>"
                 id="studio-filmstrip-custom-12"
                 data-num="12"
                 data-thumb-url="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                 data-zoom-url="<?= htmlspecialchars($customFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                 data-editor-url="<?= htmlspecialchars($customFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
              <img id="studio-filmstrip-img-12" src="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>" alt="Eigenes #12">
              <div class="ec-filmstrip-label text-warning">FSK18 (#12)</div>
            </div>

            <!-- Automatisch generierte Szenen #1..#10 -->
            <?php foreach ($availableThumbs as $th): ?>
              <?php
              $tNum       = (int)$th['num'];
              $tUrl       = (string)$th['url'];
              $tZoomUrl   = (string)($th['zoom_url'] ?? $tUrl);
              $tEditorUrl = (string)($th['editor_url'] ?? $tZoomUrl);
              ?>
              <div class="ec-filmstrip-item js-studio-filmstrip-thumb"
                   data-num="<?= $tNum ?>"
                   data-thumb-url="<?= htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') ?>"
                   data-zoom-url="<?= htmlspecialchars($tZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                   data-editor-url="<?= htmlspecialchars($tEditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Szene #<?= $tNum ?>">
                <div class="ec-filmstrip-label">Szene #<?= $tNum ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Rechte Spalte: Studio Werkzeuge (4 Tabs + Objekt-Inspektor) -->
        <div class="ec-studio-sidebar">

          <!-- Tab-Navigation -->
          <div class="ec-studio-tabs" role="tablist">
            <button type="button" class="ec-studio-tab-btn active js-studio-tab-btn" data-tab="censor">
              <i class="bi bi-shield-shaded"></i>
              <span>1. Zensur</span>
            </button>
            <button type="button" class="ec-studio-tab-btn js-studio-tab-btn" data-tab="adjust">
              <i class="bi bi-sliders"></i>
              <span>2. Licht/Farbe</span>
            </button>
            <button type="button" class="ec-studio-tab-btn js-studio-tab-btn" data-tab="badges">
              <i class="bi bi-award"></i>
              <span>3. Text/Badges</span>
            </button>
            <button type="button" class="ec-studio-tab-btn js-studio-tab-btn" data-tab="logo">
              <i class="bi bi-badge-tm"></i>
              <span>4. Dein Logo</span>
            </button>
          </div>

          <div class="ec-studio-sidebar-content">

            <!-- Dynamischer Inspektor für aktuell ausgewähltes Sticker/Text/Badge/Logo-Objekt -->
            <div class="ec-studio-panel border-info d-none" id="studio-selected-object-panel">
              <div class="ec-studio-panel-title text-info">
                <span><i class="bi bi-arrows-move me-1"></i>Ausgewähltes Element</span>
                <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" id="btn-studio-delete-selected-obj" title="Ausgewähltes Element entfernen">
                  <i class="bi bi-trash3 me-1"></i>Löschen
                </button>
              </div>
              <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                  <span class="text-light">Größe / Skalierung</span>
                  <span class="text-info fw-semibold" id="lbl-studio-obj-scale">100%</span>
                </div>
                <input type="range" class="form-range" id="rng-studio-obj-scale" min="30" max="280" step="5" value="100">
              </div>
              <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                  <span class="text-light">Drehung</span>
                  <span class="text-info fw-semibold" id="lbl-studio-obj-rotate">0°</span>
                </div>
                <input type="range" class="form-range" id="rng-studio-obj-rotate" min="-45" max="45" step="1" value="0">
              </div>
              <div>
                <div class="d-flex justify-content-between small mb-1">
                  <span class="text-light">Deckkraft (Transparenz)</span>
                  <span class="text-info fw-semibold" id="lbl-studio-obj-opacity">100%</span>
                </div>
                <input type="range" class="form-range" id="rng-studio-obj-opacity" min="15" max="100" step="5" value="100">
              </div>
            </div>

            <!-- ============================================================
                 TAB 1: Zensur (Milchglas, Pixel-Mosaik, Pinsel & Sticker)
                 ============================================================ -->
            <div class="js-studio-tab-pane" id="studio-tab-censor">
              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-vector-pen me-1 text-info"></i>Zensur-Zeichenwerkzeug</span>
                  <span class="badge bg-secondary" id="thumb-editor-shapes-badge">0 Bereiche</span>
                </div>

                <p class="ec-studio-subtext mb-2">
                  Ziehe mit gedrückter Maustaste direkt auf dem Bild über sensible Stellen, um sie unkenntlich zu machen:
                </p>

                <!-- Werkzeug-Auswahl: Viereck, Kreis, Freihand-Pinsel -->
                <label class="small text-light fw-semibold mb-1 d-block">Werkzeug-Art:</label>
                <div class="btn-group btn-group-sm w-100 mb-3" role="group">
                  <button type="button" class="btn btn-info text-dark fw-semibold active js-editor-tool-btn" data-shape-tool="rect">
                    <i class="bi bi-bounding-box-circles me-1"></i>Viereck
                  </button>
                  <button type="button" class="btn btn-outline-light js-editor-tool-btn" data-shape-tool="circle">
                    <i class="bi bi-circle me-1"></i>Kreis / Oval
                  </button>
                  <button type="button" class="btn btn-outline-light js-editor-tool-btn" data-shape-tool="brush">
                    <i class="bi bi-brush me-1"></i>Pinsel
                  </button>
                </div>

                <!-- Pinsel-Größe (nur sichtbar bei Freihand-Pinsel) -->
                <div class="mb-3 d-none" id="studio-brush-size-wrap">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-light">Pinsel-Breite:</span>
                    <span class="text-info fw-semibold" id="lbl-studio-brush-size">45 px</span>
                  </div>
                  <input type="range" class="form-range" id="rng-studio-brush-size" min="16" max="120" step="2" value="45">
                </div>

                <!-- Effekt-Typ: Milchglas vs. Pixel-Mosaik -->
                <label class="small text-light fw-semibold mb-1 d-block">Zensur-Effekt:</label>
                <div class="btn-group btn-group-sm w-100 mb-3" role="group">
                  <button type="button" class="btn btn-info text-dark fw-semibold active js-editor-effect-btn" data-effect="frosted">
                    <i class="bi bi-droplet-half me-1"></i>Milchglas (Weich)
                  </button>
                  <button type="button" class="btn btn-outline-light js-editor-effect-btn" data-effect="pixel">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i>Pixel-Mosaik
                  </button>
                </div>

                <!-- Intensität: Mittel, Stark, Maximal -->
                <label class="small text-light fw-semibold mb-1 d-block">Effekt-Stärke:</label>
                <div class="btn-group btn-group-sm w-100 mb-2" role="group">
                  <button type="button" class="btn btn-outline-light js-editor-intensity-btn" data-intensity="1">Mittel</button>
                  <button type="button" class="btn btn-info text-dark fw-semibold active js-editor-intensity-btn" data-intensity="2">Stark</button>
                  <button type="button" class="btn btn-outline-light js-editor-intensity-btn" data-intensity="3">Maximal</button>
                </div>

                <!-- Gezeichnete Bereiche verwalten (erscheint automatisch, sobald mind. 1 Bereich gezeichnet wurde) -->
                <div id="studio-censor-actions-wrap" class="border-top border-secondary pt-2 mt-2 d-none">
                  <div id="thumb-editor-shapes-list" class="d-flex flex-wrap gap-1 mb-2"></div>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-xs btn-outline-light flex-fill" id="btn-thumb-editor-undo">
                      <i class="bi bi-arrow-counterclockwise me-1"></i>Letzte rückgängig
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-warning flex-fill" id="btn-thumb-editor-clear">
                      <i class="bi bi-trash3 me-1"></i>Alle entfernen
                    </button>
                  </div>
                </div>
              </div>

              <!-- Zensur-Sticker zum Aufkleben -->
              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-emoji-heart-eyes me-1 text-warning"></i>Zensur-Sticker aufkleben</span>
                </div>
                <p class="ec-studio-subtext mb-2">
                  Klicke auf einen Sticker, um ihn auf das Bild zu setzen. Du kannst ihn danach mit der Maus frei verschieben und größer/kleiner ziehen:
                </p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="❤️" title="Herz">❤️</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="🔥" title="Flamme">🔥</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="⭐" title="Stern">⭐</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="💋" title="Kussmund">💋</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="🍑" title="Pfirsich">🍑</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="🍒" title="Kirschen">🍒</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="🍌" title="Banane">🍌</button>
                  <button type="button" class="ec-sticker-btn js-studio-add-sticker" data-sticker="🔞" title="18+ Symbol">🔞</button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="ec-badge-preset-btn border-info text-info js-studio-add-stamp" data-stamp="CENSORED" data-bg="#EF4444" data-color="#FFFFFF">
                    CENSORED Balken
                  </button>
                  <button type="button" class="ec-badge-preset-btn border-warning text-warning js-studio-add-stamp" data-stamp="FSK 16 PREVIEW" data-bg="#10B981" data-color="#FFFFFF">
                    FSK 16 Stempel
                  </button>
                </div>
              </div>
            </div>

            <!-- ============================================================
                 TAB 2: 1-Klick Bild-Optimierung (Licht, Kontrast, Sättigung, Vignette)
                 ============================================================ -->
            <div class="js-studio-tab-pane d-none" id="studio-tab-adjust">
              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-magic me-1 text-info"></i>1-Klick Look-Presets</span>
                </div>
                <div class="row g-2">
                  <div class="col-6">
                    <button type="button" class="btn btn-sm btn-outline-light w-100 js-studio-preset-btn active"
                            data-bright="0" data-contrast="0" data-sat="0" data-vignette="0">
                      Original
                    </button>
                  </div>
                  <div class="col-6">
                    <button type="button" class="btn btn-sm btn-outline-info w-100 js-studio-preset-btn"
                            data-bright="14" data-contrast="12" data-sat="10" data-vignette="0">
                      ☀️ Hell &amp; Klar
                    </button>
                  </div>
                  <div class="col-6">
                    <button type="button" class="btn btn-sm btn-outline-warning w-100 js-studio-preset-btn"
                            data-bright="6" data-contrast="10" data-sat="24" data-vignette="25">
                      🔥 Warm &amp; Sinnlich
                    </button>
                  </div>
                  <div class="col-6">
                    <button type="button" class="btn btn-sm btn-outline-light w-100 js-studio-preset-btn"
                            data-bright="4" data-contrast="26" data-sat="12" data-vignette="45">
                      🎬 Kino-Kontrast
                    </button>
                  </div>
                </div>
              </div>

              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-sliders me-1 text-info"></i>Manuelle Feinabstimmung</span>
                  <button type="button" class="btn btn-xs btn-link text-info p-0 text-decoration-none" id="btn-studio-reset-adjust">
                    Zurücksetzen
                  </button>
                </div>

                <!-- Helligkeit -->
                <div class="mb-3">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-light"><i class="bi bi-brightness-high me-1"></i>Helligkeit</span>
                    <span class="text-info fw-semibold" id="lbl-studio-bright">0</span>
                  </div>
                  <input type="range" class="form-range js-studio-adjust-slider" id="rng-studio-bright" min="-50" max="50" step="1" value="0">
                </div>

                <!-- Kontrast -->
                <div class="mb-3">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-light"><i class="bi bi-circle-half me-1"></i>Kontrast</span>
                    <span class="text-info fw-semibold" id="lbl-studio-contrast">0</span>
                  </div>
                  <input type="range" class="form-range js-studio-adjust-slider" id="rng-studio-contrast" min="-50" max="50" step="1" value="0">
                </div>

                <!-- Sättigung -->
                <div class="mb-3">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-light"><i class="bi bi-palette me-1"></i>Farbsättigung</span>
                    <span class="text-info fw-semibold" id="lbl-studio-sat">0</span>
                  </div>
                  <input type="range" class="form-range js-studio-adjust-slider" id="rng-studio-sat" min="-50" max="50" step="1" value="0">
                </div>

                <!-- Kino-Vignette -->
                <div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-light"><i class="bi bi-vignette me-1"></i>Kino-Vignette (Randabdunklung)</span>
                    <span class="text-info fw-semibold" id="lbl-studio-vignette">0%</span>
                  </div>
                  <input type="range" class="form-range js-studio-adjust-slider" id="rng-studio-vignette" min="0" max="100" step="5" value="0">
                </div>
              </div>
            </div>

            <!-- ============================================================
                 TAB 3: Verkaufs-Badges & Eigener Text-Overlay
                 ============================================================ -->
            <div class="js-studio-tab-pane d-none" id="studio-tab-badges">
              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-award me-1 text-warning"></i>1-Klick Verkaufs-Badges</span>
                </div>
                <p class="ec-studio-subtext mb-2">
                  Füge hochwertige Badges hinzu und schiebe sie mit der Maus in deine Wunschecke:
                </p>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="4K ULTRA HD" data-bg="#0284C7" data-color="#FFFFFF">
                    4K ULTRA HD
                  </button>
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="FULL HD" data-bg="#0F172A" data-color="#38BDF8">
                    FULL HD
                  </button>
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="NEU" data-bg="#10B981" data-color="#FFFFFF">
                    NEU
                  </button>
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="EXKLUSIV" data-bg="#D97706" data-color="#FFFFFF">
                    EXKLUSIV
                  </button>
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="POV" data-bg="#7C3AED" data-color="#FFFFFF">
                    POV
                  </button>
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="UNCUT" data-bg="#EF4444" data-color="#FFFFFF">
                    UNCUT
                  </button>
                  <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="TRAILER" data-bg="#334155" data-color="#F8FAFC">
                    TRAILER
                  </button>
                </div>
              </div>

              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-fonts me-1 text-info"></i>Eigenen Text einfügen</span>
                </div>
                <div class="mb-2">
                  <input type="text" id="inp-studio-custom-text" class="form-control form-control-sm bg-dark text-white border-secondary"
                         maxlength="40" placeholder="Kurzer Cover-Text (z.B. Teil 1, Hotel-Date...)">
                  <div class="ec-studio-subtext mt-1" style="font-size: 0.72rem;">
                    Hinweis: Keine Internetadressen (URLs) oder E-Mail-Adressen erlaubt.
                  </div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-6">
                    <label class="ec-studio-subtext d-block mb-1">Textfarbe:</label>
                    <select id="sel-studio-text-color" class="form-select form-select-sm bg-dark text-white border-secondary">
                      <option value="#FFFFFF">Weiß (Klassisch)</option>
                      <option value="#FACC15">Gold-Gelb</option>
                      <option value="#F43F5E">Hot Pink / Rot</option>
                      <option value="#38BDF8">EroCloud Blau</option>
                      <option value="#10B981">Smaragdgrün</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="ec-studio-subtext d-block mb-1">Hintergrund:</label>
                    <select id="sel-studio-text-bg" class="form-select form-select-sm bg-dark text-white border-secondary">
                      <option value="shadow">Nur Schlagschatten</option>
                      <option value="rgba(15,23,42,0.85)">Dunkler Balken</option>
                      <option value="rgba(225,29,72,0.9)">Rotes Banner</option>
                      <option value="rgba(2,132,199,0.9)">Blaues Banner</option>
                    </select>
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-info text-dark fw-semibold w-100" id="btn-studio-add-text">
                  <i class="bi bi-plus-circle me-1"></i>Text auf Cover platzieren
                </button>
              </div>
            </div>

            <!-- ============================================================
                 TAB 4: Eigenes Creator-Logo / Wasserzeichen
                 ============================================================ -->
            <div class="js-studio-tab-pane d-none" id="studio-tab-logo">
              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-badge-tm me-1 text-info"></i>Dein Creator-Logo / Wasserzeichen</span>
                </div>
                <p class="ec-studio-subtext mb-2">
                  Lade dein eigenes Logo (am besten als transparentes PNG) auf das Coverbild. Dein Logo wird lokal in deinem Browser gemerkt, damit du es bei jedem Film sofort parat hast!
                </p>

                <div class="d-grid gap-2 mb-3">
                  <label class="btn btn-sm btn-outline-info mb-0">
                    <i class="bi bi-upload me-1"></i>Logo-Datei (PNG / JPG) auswählen
                    <input type="file" id="inp-studio-logo-upload" class="d-none" accept=".png,.jpg,.jpeg,.webp">
                  </label>
                  <button type="button" class="btn btn-sm btn-info text-dark fw-semibold d-none" id="btn-studio-insert-saved-logo">
                    <i class="bi bi-stars me-1"></i>Gespeichertes Creator-Logo einfügen
                  </button>
                  <button type="button" class="btn btn-xs btn-outline-light d-none" id="btn-studio-clear-saved-logo">
                    Gespeichertes Browser-Logo entfernen
                  </button>
                </div>

                <div class="border-top border-secondary pt-2 mt-2">
                  <label class="ec-studio-subtext d-block mb-1">Ausgewähltes Objekt schnell in Ecke setzen:</label>
                  <div class="row g-1">
                    <div class="col-6">
                      <button type="button" class="btn btn-xs btn-outline-light w-100 js-studio-snap-corner" data-corner="tl">↖ Oben links</button>
                    </div>
                    <div class="col-6">
                      <button type="button" class="btn btn-xs btn-outline-light w-100 js-studio-snap-corner" data-corner="tr">↗ Oben rechts</button>
                    </div>
                    <div class="col-6">
                      <button type="button" class="btn btn-xs btn-outline-light w-100 js-studio-snap-corner" data-corner="bl">↙ Unten links</button>
                    </div>
                    <div class="col-6">
                      <button type="button" class="btn btn-xs btn-outline-light w-100 js-studio-snap-corner" data-corner="br">↘ Unten rechts</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- 3. Studio Footer -->
      <div class="ec-studio-footer">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <i class="bi bi-info-circle text-info"></i>
          <span class="ec-studio-subtext" id="studio-edit-summary-text">
            Unverändertes Originalbild wird verwendet.
          </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button type="button" class="btn btn-success fw-bold px-4 py-2 shadow" id="btn-studio-save-primary">
            <i class="bi bi-shield-check me-1" id="ico-studio-save-primary"></i>
            <span id="lbl-studio-save-primary">Als FSK 16 Cover speichern &amp; aktivieren</span>
          </button>
        </div>
      </div>

    </div>
  </div>
</div>
