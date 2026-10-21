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
$customFsk16Url  = (string)($movie['custom_fsk16_url'] ?? '');
$customFsk18Url  = (string)($movie['custom_fsk18_url'] ?? '');
$playerPosterUrl = (string)($movie['player_poster_url'] ?? '');
$playerVideoUrl  = (string)($movie['player_video_url'] ?? '');
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
            <div class="ratio ratio-16x9 rounded-3 overflow-hidden bg-dark shadow-sm">
              <video controls preload="metadata" poster="<?= htmlspecialchars($playerPosterUrl, ENT_QUOTES, 'UTF-8') ?>" class="w-100 h-100">
                <source src="<?= htmlspecialchars($playerVideoUrl, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                Dein Browser unterstützt leider keine HTML5-Videowiedergabe.
              </video>
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
          <h6 class="mb-0 fw-bold"><i class="bi bi-images me-2 text-primary"></i>Vorschaubilder (FSK16 &amp; FSK18)</h6>
        </div>
        <div class="kkc-card-body">
          <p class="text-muted small mb-3">
            Wähle jeweils ein Vorschaubild für den <strong>FSK16-Bereich</strong> (jugendfrei) und den <strong>FSK18-Bereich</strong> aus oder lade eigene Coverbilder (JPG/PNG, max. 6 MB) hoch.
          </p>

          <!-- Aktuelle Auswahl nebeneinander -->
          <div class="row g-3 mb-4">
            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light text-center h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="badge bg-success">FSK 16 Cover</span>
                  <span class="small text-muted" id="badge-fsk16-num">Bild #<?= $fsk16 ?></span>
                </div>
                <img id="current-fsk16-preview"
                     src="<?= htmlspecialchars((defined('MCP_URL') ? MCP_URL : 'https://erocloud.net') . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk16 . '&w=186&t=' . time(), ENT_QUOTES, 'UTF-8') ?>"
                     alt="FSK16"
                     class="img-fluid rounded border mb-2"
                     style="max-height: 115px; object-fit: cover;">
                <div>
                  <label class="btn btn-sm btn-outline-kkc w-100 mb-0">
                    <i class="bi bi-upload me-1"></i> Eigenes FSK16 Bild
                    <input type="file" class="d-none js-movie-poster-upload" data-fsk="16" data-movie-id="<?= $movieId ?>" accept=".jpg,.jpeg,.png">
                  </label>
                </div>
              </div>
            </div>

            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light text-center h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="badge bg-danger">FSK 18 Cover</span>
                  <span class="small text-muted" id="badge-fsk18-num">Bild #<?= $fsk18 ?></span>
                </div>
                <img id="current-fsk18-preview"
                     src="<?= htmlspecialchars((defined('MCP_URL') ? MCP_URL : 'https://erocloud.net') . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $fsk18 . '&w=186&t=' . time(), ENT_QUOTES, 'UTF-8') ?>"
                     alt="FSK18"
                     class="img-fluid rounded border mb-2"
                     style="max-height: 115px; object-fit: cover;">
                <div>
                  <label class="btn btn-sm btn-outline-kkc w-100 mb-0">
                    <i class="bi bi-upload me-1"></i> Eigenes FSK18 Bild
                    <input type="file" class="d-none js-movie-poster-upload" data-fsk="18" data-movie-id="<?= $movieId ?>" accept=".jpg,.jpeg,.png">
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Automatisch generierte Vorschaubilder 1..10 -->
          <?php if (!empty($availableThumbs)): ?>
            <div class="small fw-semibold mb-2">Automatisch generierte Szenenbilder (Klick zum Zuweisen):</div>
            <div class="row g-2">
              <?php foreach ($availableThumbs as $th): ?>
                <?php $tNum = (int)$th['num']; ?>
                <div class="col-6 col-sm-4">
                  <div class="p-1 border rounded-2 bg-white text-center">
                    <img src="<?= htmlspecialchars((string)$th['url'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="Szene #<?= $tNum ?>"
                         class="img-fluid rounded mb-1"
                         style="height: 72px; width: 100%; object-fit: cover;">
                    <div class="d-flex gap-1 justify-content-center">
                      <button type="button"
                              class="btn btn-xs <?= $fsk16 === $tNum ? 'btn-success' : 'btn-outline-secondary' ?> py-0 px-2 small js-select-movie-thumb"
                              data-fsk="16"
                              data-num="<?= $tNum ?>"
                              data-url="<?= htmlspecialchars((string)$th['url'], ENT_QUOTES, 'UTF-8') ?>">
                        FSK16
                      </button>
                      <button type="button"
                              class="btn btn-xs <?= $fsk18 === $tNum ? 'btn-danger' : 'btn-outline-secondary' ?> py-0 px-2 small js-select-movie-thumb"
                              data-fsk="18"
                              data-num="<?= $tNum ?>"
                              data-url="<?= htmlspecialchars((string)$th['url'], ENT_QUOTES, 'UTF-8') ?>">
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
