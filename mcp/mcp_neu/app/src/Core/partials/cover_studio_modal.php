<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * Universelles EroCloud Fullscreen Cover-Studio Modal (FSK 16 & FSK 18)
 * Wiederverwendbar für Videos (#/movie), Foto-Alben (#/photo-album & #/photo-album-upload) und weitere Bereiche.
 *
 * @var string $studioContext        'movie' | 'photo-album'
 * @var int    $studioEntityId       ID des Films oder Foto-Albums
 * @var string $studioTitle          Titel für die Kopfzeile
 * @var bool   $allowVideoCapture    Ob Live-Video-Schnappschuss verfügbar ist
 * @var string $playerPosterUrl      Video-Poster URL
 * @var string $playerVideoUrl       Video-Stream URL
 * @var bool   $hasCustomFsk16       Ob ein gespeichertes FSK16 Cover existiert
 * @var string $customFsk16Num       Kennung im Filmstreifen ('11' oder 'cover_16')
 * @var string $customFsk16Label     Anzeigename im Filmstreifen ('FSK16 (#11)' oder 'FSK16 Cover')
 * @var string $customFsk16Url       Vorschau-URL FSK16
 * @var string $customFsk16ZoomUrl   Zoom-URL FSK16
 * @var string $customFsk16EditorUrl Hochauflösende Studio-URL FSK16
 * @var bool   $hasCustomFsk18       Ob ein gespeichertes FSK18 Cover existiert
 * @var string $customFsk18Num       Kennung im Filmstreifen ('12' oder 'cover_18')
 * @var string $customFsk18Label     Anzeigename im Filmstreifen ('FSK18 (#12)' oder 'FSK18 Cover')
 * @var string $customFsk18Url       Vorschau-URL FSK18
 * @var string $customFsk18ZoomUrl   Zoom-URL FSK18
 * @var string $customFsk18EditorUrl Hochauflösende Studio-URL FSK18
 * @var array<int, array<string, mixed>> $filmstripItems Liste der Szenen- oder Album-Fotos
 */

$studioContext        = (string)($studioContext ?? 'movie');
$studioEntityId       = (int)($studioEntityId ?? 0);
$studioTitle          = (string)($studioTitle ?? '');
$allowVideoCapture    = !empty($allowVideoCapture);
$playerPosterUrl      = (string)($playerPosterUrl ?? '');
$playerVideoUrl       = (string)($playerVideoUrl ?? '');
$hasCustomFsk16       = !empty($hasCustomFsk16);
$customFsk16Num       = (string)($customFsk16Num ?? ($studioContext === 'photo-album' ? 'cover_16' : '11'));
$customFsk16Label     = (string)($customFsk16Label ?? ($studioContext === 'photo-album' ? 'FSK16 Cover' : 'FSK16 (#11)'));
$customFsk16Url       = (string)($customFsk16Url ?? '');
$customFsk16ZoomUrl   = (string)($customFsk16ZoomUrl ?? $customFsk16Url);
$customFsk16EditorUrl = (string)($customFsk16EditorUrl ?? $customFsk16ZoomUrl);
$hasCustomFsk18       = !empty($hasCustomFsk18);
$customFsk18Num       = (string)($customFsk18Num ?? ($studioContext === 'photo-album' ? 'cover_18' : '12'));
$customFsk18Label     = (string)($customFsk18Label ?? ($studioContext === 'photo-album' ? 'FSK18 Cover' : 'FSK18 (#12)'));
$customFsk18Url       = (string)($customFsk18Url ?? '');
$customFsk18ZoomUrl   = (string)($customFsk18ZoomUrl ?? $customFsk18Url);
$customFsk18EditorUrl = (string)($customFsk18EditorUrl ?? $customFsk18ZoomUrl);
$filmstripItems       = is_array($filmstripItems ?? null) ? $filmstripItems : [];

$defaultSourceBadge = $studioContext === 'photo-album' ? 'Foto #1' : 'Szene #1';
?>
<!-- Fullscreen Cover-Studio Modal (FSK 16 & FSK 18) -->
<div class="modal fade ec-cover-studio-modal"
     id="modalMovieThumbEditor"
     tabindex="-1"
     aria-hidden="true"
     data-studio-context="<?= htmlspecialchars($studioContext, ENT_QUOTES, 'UTF-8') ?>"
     data-entity-id="<?= $studioEntityId ?>"
     data-movie-id="<?= $studioEntityId ?>"
     data-album-id="<?= $studioEntityId ?>">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content d-flex flex-column h-100">

      <!-- 1. Studio Header -->
      <div class="ec-studio-header">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-2 py-1">
            <i class="bi bi-stars me-1"></i>Cover-Studio
          </span>
          <h5 class="modal-title fw-bold mb-0 fs-6 text-white text-truncate" style="max-width: 320px;" id="thumb-editor-modal-title">
            <?= htmlspecialchars($studioTitle, ENT_QUOTES, 'UTF-8') ?>
          </h5>
          <span class="badge bg-dark text-info border border-info border-opacity-50 small" id="studio-source-badge">
            <?= htmlspecialchars($defaultSourceBadge, ENT_QUOTES, 'UTF-8') ?>
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

            <!-- Live-Video-Schnappschuss direkt zentriert auf der Bühne (nur bei Videos) -->
            <?php if ($allowVideoCapture && $playerVideoUrl !== ''): ?>
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

          <!-- Unterer Filmstreifen (Quellen-Auswahl: Eigenes Bild, Video-Frame, gespeicherte Cover, Szenen / Album-Fotos) -->
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

            <!-- Aktion 2: Live-Schnappschuss aus dem Video (nur bei Videos) -->
            <?php if ($allowVideoCapture && $playerVideoUrl !== ''): ?>
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

            <!-- Gespeichertes FSK 16 Cover (#11 bei Video bzw. cover_16 bei Fotoalbum) -->
            <div class="ec-filmstrip-item js-studio-filmstrip-thumb <?= $hasCustomFsk16 ? '' : 'd-none' ?>"
                 id="studio-filmstrip-custom-11"
                 data-num="<?= htmlspecialchars($customFsk16Num, ENT_QUOTES, 'UTF-8') ?>"
                 data-label="<?= htmlspecialchars($customFsk16Label, ENT_QUOTES, 'UTF-8') ?>"
                 data-thumb-url="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>"
                 data-zoom-url="<?= htmlspecialchars($customFsk16ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                 data-editor-url="<?= htmlspecialchars($customFsk16EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
              <img id="studio-filmstrip-img-11" src="<?= htmlspecialchars($customFsk16Url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($customFsk16Label, ENT_QUOTES, 'UTF-8') ?>">
              <div class="ec-filmstrip-label text-info"><?= htmlspecialchars($customFsk16Label, ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <!-- Gespeichertes FSK 18 Cover (#12 bei Video bzw. cover_18 bei Fotoalbum) -->
            <div class="ec-filmstrip-item js-studio-filmstrip-thumb <?= $hasCustomFsk18 ? '' : 'd-none' ?>"
                 id="studio-filmstrip-custom-12"
                 data-num="<?= htmlspecialchars($customFsk18Num, ENT_QUOTES, 'UTF-8') ?>"
                 data-label="<?= htmlspecialchars($customFsk18Label, ENT_QUOTES, 'UTF-8') ?>"
                 data-thumb-url="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>"
                 data-zoom-url="<?= htmlspecialchars($customFsk18ZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                 data-editor-url="<?= htmlspecialchars($customFsk18EditorUrl, ENT_QUOTES, 'UTF-8') ?>">
              <img id="studio-filmstrip-img-12" src="<?= htmlspecialchars($customFsk18Url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($customFsk18Label, ENT_QUOTES, 'UTF-8') ?>">
              <div class="ec-filmstrip-label text-warning"><?= htmlspecialchars($customFsk18Label, ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <!-- Szenenbilder (#1..#10 bei Videos) bzw. Fotos des Albums (Foto #1..#N bei Fotoalben) -->
            <?php foreach ($filmstripItems as $item): ?>
              <?php
              $itemNum       = (string)($item['num'] ?? '1');
              $itemLabel     = (string)($item['label'] ?? ('Szene #' . $itemNum));
              $itemUrl       = (string)($item['url'] ?? '');
              $itemZoomUrl   = (string)($item['zoom_url'] ?? $itemUrl);
              $itemEditorUrl = (string)($item['editor_url'] ?? $itemZoomUrl);
              $itemFileId    = (string)($item['file_id'] ?? '');
              ?>
              <div class="ec-filmstrip-item js-studio-filmstrip-thumb"
                   data-num="<?= htmlspecialchars($itemNum, ENT_QUOTES, 'UTF-8') ?>"
                   data-label="<?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?>"
                   <?= $itemFileId !== '' ? 'data-file-id="' . htmlspecialchars($itemFileId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                   data-thumb-url="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>"
                   data-zoom-url="<?= htmlspecialchars($itemZoomUrl, ENT_QUOTES, 'UTF-8') ?>"
                   data-editor-url="<?= htmlspecialchars($itemEditorUrl, ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?>">
                <div class="ec-filmstrip-label"><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></div>
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

                <!-- Gezeichnete Bereiche verwalten -->
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
                  <?php if ($studioContext === 'photo-album'): ?>
                    <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="FOTO-SET" data-bg="#0284C7" data-color="#FFFFFF">
                      FOTO-SET
                    </button>
                    <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="HIGH-RES" data-bg="#0F172A" data-color="#38BDF8">
                      HIGH-RES
                    </button>
                    <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="NEU" data-bg="#10B981" data-color="#FFFFFF">
                      NEU
                    </button>
                    <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="EXKLUSIV" data-bg="#D97706" data-color="#FFFFFF">
                      EXKLUSIV
                    </button>
                    <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="UNCUT" data-bg="#EF4444" data-color="#FFFFFF">
                      UNCUT
                    </button>
                    <button type="button" class="ec-badge-preset-btn js-studio-add-stamp" data-stamp="VIP SHOOTING" data-bg="#7C3AED" data-color="#FFFFFF">
                      VIP SHOOTING
                    </button>
                  <?php else: ?>
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
                  <?php endif; ?>
                </div>
              </div>

              <div class="ec-studio-panel">
                <div class="ec-studio-panel-title">
                  <span><i class="bi bi-fonts me-1 text-info"></i>Eigenen Text einfügen</span>
                </div>
                <div class="mb-2">
                  <input type="text" id="inp-studio-custom-text" class="form-control form-control-sm bg-dark text-white border-secondary"
                         maxlength="40" placeholder="Kurzer Cover-Text (z.B. Teil 1, Hotel-Shooting...)">
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
                  Lade dein eigenes Logo (am besten als transparentes PNG) auf das Coverbild. Dein Logo wird lokal in deinem Browser gemerkt, damit du es bei jedem Film und Foto-Album sofort parat hast!
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
