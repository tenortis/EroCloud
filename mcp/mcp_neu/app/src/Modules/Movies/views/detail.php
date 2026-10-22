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
$movieStatus     = (string)($movie['status'] ?? 'active');
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

$convQueue       = isset($conversionQueueInfo) && is_array($conversionQueueInfo) ? $conversionQueueInfo : null;

// Schritt-Status für die 4-stufige Fortschrittsanzeige berechnen
// Step 1: Hochgeladen (immer aktiv, da filename vorhanden)
// Step 2: Konvertiert (convert_status == 2) bzw. Fehler (convert_status == 3)
// Step 3: Geprüft (movie_checked != '0000-00-00 00:00:00' && released == 1)
// Step 4: Online (isOnline === true)
$step2Done  = ($convertStatus === 2);
$step2Error = ($convertStatus === 3);
$step3Done  = ($movieChecked !== '0000-00-00 00:00:00' && $released === 1 && $movieStatus !== 'blocked');
$step3Warn  = ($released === 2 || $movieStatus === 'blocked' || ($movieChecked !== '0000-00-00 00:00:00' && $released === 0));
$step4Done  = $isOnline;

// Prüfen, ob für FSK 16 bzw. FSK 18 bereits ein Vorschaubild aktiv ausgewählt oder als eigenes Cover hochgeladen wurde
$fsk16StudioReady = ($fsk16 === 11 && $hasCustomFsk16) || ($convertStatus === 2 && $fsk16 >= 1 && $fsk16 <= 10);
$fsk18StudioReady = ($fsk18 === 12 && $hasCustomFsk18) || ($convertStatus === 2 && $fsk18 >= 1 && $fsk18 <= 10);
$studioDisabledHint = 'Studio erst verfügbar, wenn du ein Vorschaubild ausgewählt oder ein eigenes Bild hochgeladen hast.';

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
      <?php if ($released === 0): ?>
        <i class="bi bi-send-check me-1"></i> Film jetzt zur Prüfung einreichen
      <?php else: ?>
        <i class="bi bi-check2-circle me-1"></i> Änderungen speichern
      <?php endif; ?>
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
        <?php elseif ($step2Error): ?>
          <div class="p-2 rounded-3 border bg-danger bg-opacity-10 border-danger border-opacity-25">
            <div class="fw-bold text-danger small">
              <i class="bi bi-exclamation-octagon-fill me-1"></i> 2. Konvertierungsfehler
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Bitte Datei neu hochladen</div>
          </div>
        <?php else: ?>
          <div class="p-2 rounded-3 border bg-warning bg-opacity-10 border-warning border-opacity-25">
            <div class="fw-bold text-warning small">
              <i class="bi bi-gear-wide-connected me-1"></i> 2. Konvertierung
            </div>
            <div class="text-muted" style="font-size: 0.75rem;" id="step2-progress-subtext">
              <?= $convQueue !== null ? ('Restdauer: ' . htmlspecialchars((string)$convQueue['remaining_formatted'], ENT_QUOTES, 'UTF-8')) : 'Wird verarbeitet...' ?>
            </div>
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

<!-- Hinweis-Banner bei Konvertierungsfehler (convert_status == 3), laufender Konvertierung (convert_status < 2) oder noch ausstehender Einreichung (released == 0) -->
<?php if ($convertStatus === 3): ?>
  <div class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 shadow-sm">
    <div class="d-flex align-items-start gap-3">
      <i class="bi bi-exclamation-octagon-fill fs-3 text-danger flex-shrink-0"></i>
      <div>
        <h6 class="fw-bold mb-1">Bei der Konvertierung deiner Videodatei ist ein technischer Fehler aufgetreten</h6>
        <div class="small">
          Die hochgeladene Videodatei konnte leider nicht verarbeitet werden (z. B. wegen einer unvollständigen Datei oder eines defekten Video-Codecs). Bitte lade in Schritt 2 eine intakte Videodatei (empfohlen: <strong>MP4 H.264</strong>) erneut hoch.
        </div>
      </div>
    </div>
    <a href="#/movie-upload?step=2&amp;movie_id=<?= $movieId ?>" class="btn btn-sm btn-danger fw-semibold flex-shrink-0">
      <i class="bi bi-cloud-arrow-up-fill me-1"></i> Neue Videodatei hochladen
    </a>
  </div>
<?php elseif ($convertStatus < 2): ?>
  <?php
  $qState      = (string)($convQueue['state'] ?? ($convertStatus === 1 ? 'converting' : 'queued'));
  $qPos        = (int)($convQueue['queue_position'] ?? 1);
  $qAhead      = (int)($convQueue['movies_ahead'] ?? 0);
  $qRemText    = (string)($convQueue['remaining_formatted'] ?? 'wenige Minuten');
  $qPct        = (int)($convQueue['progress_percent'] ?? ($convertStatus === 1 ? 25 : 8));
  $qHeadline   = (string)($convQueue['status_headline'] ?? 'Dein Video wird aktuell konvertiert & 10 Vorschaubilder werden erstellt');
  $qDetail     = (string)($convQueue['status_detail'] ?? '');
  ?>
  <div class="alert alert-info mb-4 shadow-sm p-3 p-md-4"
       id="movie-conversion-live-box"
       data-movie-id="<?= $movieId ?>"
       data-poll-context="detail"
       data-released="<?= $released ?>">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
      <div class="d-flex align-items-start gap-3">
        <div class="spinner-border text-primary flex-shrink-0 mt-1" role="status" style="width: 1.75rem; height: 1.75rem;"></div>
        <div>
          <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
            <h6 class="fw-bold mb-0" id="movie-conv-headline"><?= htmlspecialchars($qHeadline, ENT_QUOTES, 'UTF-8') ?></h6>
            <span class="badge <?= $qState === 'converting' ? 'bg-primary' : 'bg-warning text-dark' ?>" id="movie-conv-state-badge">
              <?= $qState === 'converting' ? 'Aktiv in Konvertierung' : ('Warteschlange Platz #' . $qPos) ?>
            </span>
          </div>
          <div class="small text-dark mb-1" id="movie-conv-detail">
            <?= htmlspecialchars($qDetail, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="small text-muted">
            <i class="bi bi-arrow-repeat me-1 text-primary"></i><strong>Automatische Live-Aktualisierung aktiv:</strong> Du kannst diese Seite einfach geöffnet lassen – sobald die Konvertierung fertig ist, lädt sich diese Ansicht automatisch neu und schaltet alle 10 Vorschaubilder sowie das Cover-Studio frei!
          </div>
        </div>
      </div>
      <div class="d-flex flex-column align-items-md-end gap-2 flex-shrink-0">
        <div class="px-3 py-1 rounded-pill bg-white border shadow-sm small fw-semibold text-primary">
          <i class="bi bi-hourglass-split me-1"></i>Geschätzte Restdauer: <strong id="movie-conv-remaining"><?= htmlspecialchars($qRemText, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary bg-white" id="btn-refresh-conversion-status">
          <i class="bi bi-arrow-clockwise me-1"></i> Status jetzt aktualisieren
        </button>
      </div>
    </div>

    <div class="progress bg-white border" style="height: 10px;">
      <div id="movie-conv-progress-bar"
           class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
           role="progressbar"
           style="width: <?= max(5, min(98, $qPct)) ?>%;"
           aria-valuenow="<?= $qPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
  </div>
<?php elseif ($released === 0): ?>
  <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 shadow-sm">
    <div class="d-flex align-items-start gap-3">
      <i class="bi bi-magic fs-3 text-warning flex-shrink-0"></i>
      <div>
        <h6 class="fw-bold mb-1">Konvertierung abgeschlossen – Vorschaubilder bereit für das Cover-Studio!</h6>
        <div class="small">
          Dein Film ist fertig konvertiert und alle <strong>10 Szenen-Vorschaubilder</strong> stehen bereit. Wähle oder bearbeite jetzt links deine <strong>FSK 16 &amp; FSK 18 Cover</strong> im <strong>Cover-Studio</strong> und klicke danach auf <strong>„Film jetzt zur Prüfung einreichen"</strong>.
        </div>
      </div>
    </div>
    <button type="submit" form="kkc-movie-edit-form" class="btn btn-sm btn-kkc-primary flex-shrink-0">
      <i class="bi bi-send-check me-1"></i> Film jetzt zur Prüfung einreichen
    </button>
  </div>
<?php endif; ?>

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
          <?php elseif ($convertStatus === 3): ?>
            <div class="p-4 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 text-center">
              <i class="bi bi-exclamation-octagon-fill text-danger fs-2 d-block mb-2"></i>
              <h6 class="fw-bold text-danger mb-1">Konvertierung fehlgeschlagen</h6>
              <p class="text-muted small mb-3">
                Deine hochgeladene Videodatei konnte leider nicht verarbeitet werden. Bitte lade eine neue Videodatei hoch.
              </p>
              <a href="#/movie-upload?step=2&amp;movie_id=<?= $movieId ?>" class="btn btn-sm btn-danger">
                <i class="bi bi-cloud-arrow-up-fill me-1"></i> Neue Videodatei hochladen
              </a>
            </div>
          <?php else: ?>
            <div class="p-4 rounded-3 bg-light border text-center">
              <div class="spinner-border text-primary mb-2" role="status"></div>
              <h6 class="fw-bold mb-1">Video wird aktuell konvertiert</h6>
              <p class="text-muted small mb-2">
                Sobald die Konvertierung abgeschlossen ist, lädt diese Seite automatisch neu und schaltet den Video-Player sowie die 10 Vorschaubilder frei.
              </p>
              <?php if ($convQueue !== null): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" id="movie-player-remaining-badge">
                  <i class="bi bi-clock-history me-1"></i>Geschätzte Restdauer: <?= htmlspecialchars((string)$convQueue['remaining_formatted'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
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

          <!-- Wichtiger Hinweis, solange noch kein FSK 16 oder FSK 18 Cover ausgewählt/hochgeladen wurde -->
          <div id="missing-covers-warning" class="alert alert-warning py-2 px-3 small mb-3 <?= ($fsk16StudioReady && $fsk18StudioReady) ? 'd-none' : '' ?>">
            <div class="fw-bold mb-1">
              <i class="bi bi-exclamation-triangle-fill me-1"></i>Bitte wähle noch dein FSK 16 und FSK 18 Cover aus:
            </div>
            <ul class="mb-0 ps-3" style="font-size: 0.78rem; line-height: 1.45;">
              <li id="missing-fsk16-hint" class="<?= $fsk16StudioReady ? 'd-none' : '' ?>">
                <strong>FSK 16 Cover (Jugendfrei-Pflicht):</strong> Wähle unten eines der generierten Szenenbilder (oder lade ein eigenes Bild hoch). <strong>Wichtig:</strong> Das FSK 16 Bild muss <strong>jugendfrei (verpixelt / Milchglas)</strong> sein – nutze dafür nach der Auswahl direkt das <strong>Cover-Studio</strong>!
              </li>
              <li id="missing-fsk18-hint" class="<?= $fsk18StudioReady ? 'd-none' : '' ?>">
                <strong>FSK 18 Cover:</strong> Wähle unten eines der generierten Szenenbilder über den Button <strong>„FSK18"</strong> aus oder lade ein eigenes FSK 18 Cover hoch.
              </li>
            </ul>
          </div>

          <!-- Aktuelle Auswahl nebeneinander -->
          <div class="row g-3 mb-4">
            <!-- Aktives FSK 16 Cover -->
            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-success">FSK 16 Cover</span>
                    <span class="small <?= $fsk16StudioReady ? 'text-muted' : 'text-danger' ?> fw-semibold" id="badge-fsk16-num">
                      <?php if (!$fsk16StudioReady): ?>
                        Bitte auswählen
                      <?php elseif ($fsk16 === 11): ?>
                        Eigenes Bild (#11)
                      <?php else: ?>
                        Szene #<?= $fsk16 ?>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="position-relative d-inline-block w-100 mb-2 js-hover-zoom-thumb js-open-thumb-editor-modal"
                       id="current-fsk16-wrap"
                       style="<?= $fsk16StudioReady ? 'cursor: pointer;' : 'cursor: not-allowed;' ?>"
                       title="<?= htmlspecialchars($fsk16StudioReady ? 'Klicken, um das FSK 16 Cover im Studio zu bearbeiten (Verpixeln / Milchglas)' : $studioDisabledHint, ENT_QUOTES, 'UTF-8') ?>"
                       data-studio-ready="<?= $fsk16StudioReady ? '1' : '0' ?>"
                       data-movie-id="<?= $movieId ?>"
                       data-num="<?= $fsk16 ?>"
                       data-fsk="16"
                       data-target-fsk="16"
                       data-thumb-url="<?= htmlspecialchars($fsk16StudioReady ? $currentFsk16ThumbUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-src="<?= htmlspecialchars($fsk16StudioReady ? $currentFsk16ZoomUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                       data-editor-url="<?= htmlspecialchars($fsk16StudioReady ? $currentFsk16EditorUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-fsk="16"
                       data-zoom-title="Aktuelles FSK 16 Cover (Klick für FSK 16 Cover-Studio)">
                    <div id="empty-fsk16-placeholder"
                         class="d-flex flex-column align-items-center justify-content-center rounded border border-2 border-dashed bg-white p-2 text-center <?= $fsk16StudioReady ? 'd-none' : '' ?>"
                         style="height: 115px;">
                      <i class="bi bi-shield-exclamation text-success fs-4 mb-1"></i>
                      <div class="fw-bold text-dark" style="font-size: 0.75rem;">Kein FSK 16 Cover gewählt</div>
                      <div class="text-muted" style="font-size: 0.67rem; line-height: 1.2;">Unten Szene wählen &amp; verpixeln (jugendfrei!) oder eigenes Bild laden</div>
                    </div>
                    <img id="current-fsk16-preview"
                         src="<?= htmlspecialchars($fsk16StudioReady ? $currentFsk16ThumbUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                         alt="FSK16"
                         class="img-fluid rounded border w-100 <?= $fsk16StudioReady ? '' : 'd-none' ?>"
                         style="height: 115px; object-fit: cover;">
                    <span id="badge-fsk16-studio-overlay"
                          class="badge bg-dark bg-opacity-75 position-absolute bottom-0 end-0 m-1 small <?= $fsk16StudioReady ? '' : 'd-none' ?>">
                      <i class="bi bi-magic me-1"></i>FSK 16 Studio
                    </span>
                  </div>
                </div>
                <div class="d-grid gap-1">
                  <button type="button"
                          id="btn-open-studio-fsk16"
                          class="btn btn-sm <?= $fsk16StudioReady ? 'btn-success' : 'btn-secondary opacity-50' ?> fw-semibold js-open-active-fsk-editor"
                          style="<?= $fsk16StudioReady ? 'cursor: pointer;' : 'cursor: not-allowed;' ?>"
                          title="<?= htmlspecialchars($fsk16StudioReady ? 'FSK 16 Cover im Studio bearbeiten (Verpixeln / Milchglas)' : $studioDisabledHint, ENT_QUOTES, 'UTF-8') ?>"
                          data-studio-ready="<?= $fsk16StudioReady ? '1' : '0' ?>"
                          data-fsk="16">
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
                    <span class="small <?= $fsk18StudioReady ? 'text-muted' : 'text-danger' ?> fw-semibold" id="badge-fsk18-num">
                      <?php if (!$fsk18StudioReady): ?>
                        Bitte auswählen
                      <?php elseif ($fsk18 === 12): ?>
                        Eigenes Bild (#12)
                      <?php else: ?>
                        Szene #<?= $fsk18 ?>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="position-relative d-inline-block w-100 mb-2 js-hover-zoom-thumb js-open-thumb-editor-modal"
                       id="current-fsk18-wrap"
                       style="<?= $fsk18StudioReady ? 'cursor: pointer;' : 'cursor: not-allowed;' ?>"
                       title="<?= htmlspecialchars($fsk18StudioReady ? 'Klicken, um das FSK 18 Cover im Studio zu bearbeiten' : $studioDisabledHint, ENT_QUOTES, 'UTF-8') ?>"
                       data-studio-ready="<?= $fsk18StudioReady ? '1' : '0' ?>"
                       data-movie-id="<?= $movieId ?>"
                       data-num="<?= $fsk18 ?>"
                       data-fsk="18"
                       data-target-fsk="18"
                       data-thumb-url="<?= htmlspecialchars($fsk18StudioReady ? $currentFsk18ThumbUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-src="<?= htmlspecialchars($fsk18StudioReady ? $currentFsk18ZoomUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                       data-editor-url="<?= htmlspecialchars($fsk18StudioReady ? $currentFsk18EditorUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                       data-zoom-fsk="18"
                       data-zoom-title="Aktuelles FSK 18 Cover (Klick für FSK 18 Cover-Studio)">
                    <div id="empty-fsk18-placeholder"
                         class="d-flex flex-column align-items-center justify-content-center rounded border border-2 border-dashed bg-white p-2 text-center <?= $fsk18StudioReady ? 'd-none' : '' ?>"
                         style="height: 115px;">
                      <i class="bi bi-image text-danger fs-4 mb-1"></i>
                      <div class="fw-bold text-dark" style="font-size: 0.75rem;">Kein FSK 18 Cover gewählt</div>
                      <div class="text-muted" style="font-size: 0.67rem; line-height: 1.2;">Unten eine Szene per „FSK18"-Button wählen oder eigenes Bild laden</div>
                    </div>
                    <img id="current-fsk18-preview"
                         src="<?= htmlspecialchars($fsk18StudioReady ? $currentFsk18ThumbUrl : '', ENT_QUOTES, 'UTF-8') ?>"
                         alt="FSK18"
                         class="img-fluid rounded border w-100 <?= $fsk18StudioReady ? '' : 'd-none' ?>"
                         style="height: 115px; object-fit: cover;">
                    <span id="badge-fsk18-studio-overlay"
                          class="badge bg-dark bg-opacity-75 position-absolute bottom-0 end-0 m-1 small <?= $fsk18StudioReady ? '' : 'd-none' ?>">
                      <i class="bi bi-stars me-1"></i>FSK 18 Studio
                    </span>
                  </div>
                </div>
                <div class="d-grid gap-1">
                  <button type="button"
                          id="btn-open-studio-fsk18"
                          class="btn btn-sm <?= $fsk18StudioReady ? 'btn-danger' : 'btn-secondary opacity-50' ?> fw-semibold js-open-active-fsk-editor"
                          style="<?= $fsk18StudioReady ? 'cursor: pointer;' : 'cursor: not-allowed;' ?>"
                          title="<?= htmlspecialchars($fsk18StudioReady ? 'FSK 18 Cover im Studio bearbeiten' : $studioDisabledHint, ENT_QUOTES, 'UTF-8') ?>"
                          data-studio-ready="<?= $fsk18StudioReady ? '1' : '0' ?>"
                          data-fsk="18">
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
                     maxlength="65" required
                     value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Gib einen aussagekräftigen Filmtitel ein (maximal 65 Zeichen)">
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

      <!-- Darsteller & Drehpartner-Prüfung (Pflichtangabe vor Veröffentlichung) -->
      <?php
      $contentLabel = 'Film';
      require dirname(__DIR__, 2) . '/CoActors/views/partials/performer_selector_card.php';
      ?>

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
                        <?= str_replace('.', ',', $opt) ?> Coins / Sekunde<?= $opt === '0.8' ? ' (Empfohlen)' : '' ?>
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

                <!-- Download-Aufpreis in Prozent -->
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

      <!-- Speichern- & Löschen-Leiste unten -->
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <?php if ($canDelete): ?>
            <button type="button" class="btn btn-outline-danger js-delete-movie-btn" id="btn-delete-movie-bottom"
                    data-movie-id="<?= $movieId ?>"
                    data-movie-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
              <i class="bi bi-trash3 me-1"></i> Film löschen
            </button>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2 ms-auto">
          <a href="#/movies" class="btn btn-outline-kkc">Abbrechen</a>
          <button type="submit" class="btn btn-kkc-primary px-4" id="btn-save-movie-bottom">
            <?php if ($released === 0): ?>
              <i class="bi bi-send-check me-1"></i> Film jetzt zur Prüfung einreichen
            <?php else: ?>
              <i class="bi bi-check2-circle me-1"></i> Änderungen speichern
            <?php endif; ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<?= \App\Core\View::renderCoverStudioModal([
    'studioContext'        => 'movie',
    'studioEntityId'       => $movieId,
    'studioTitle'          => $title !== '' ? $title : ('Film #' . $movieId),
    'allowVideoCapture'    => ($convertStatus === 2 && $playerVideoUrl !== ''),
    'playerPosterUrl'      => $playerPosterUrl,
    'playerVideoUrl'       => $playerVideoUrl,
    'hasCustomFsk16'       => $hasCustomFsk16,
    'customFsk16Num'       => '11',
    'customFsk16Label'     => 'FSK16 (#11)',
    'customFsk16Url'       => $customFsk16Url,
    'customFsk16ZoomUrl'   => $customFsk16ZoomUrl,
    'customFsk16EditorUrl' => $customFsk16EditorUrl,
    'hasCustomFsk18'       => $hasCustomFsk18,
    'customFsk18Num'       => '12',
    'customFsk18Label'     => 'FSK18 (#12)',
    'customFsk18Url'       => $customFsk18Url,
    'customFsk18ZoomUrl'   => $customFsk18ZoomUrl,
    'customFsk18EditorUrl' => $customFsk18EditorUrl,
    'filmstripItems'       => $availableThumbs,
]) ?>

<?php require dirname(__DIR__, 2) . '/CoActors/views/partials/co_actor_modals.php'; ?>
