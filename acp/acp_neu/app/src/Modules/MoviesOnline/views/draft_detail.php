<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<string, mixed> $movie
 * @var int $prevDraftId
 * @var int $nextDraftId
 * @var array<int, array<string, mixed>> $merchantActors
 * @var array<int, string> $merchantDomains
 * @var array<string, array<int, array<string, mixed>>> $categoriesByType
 * @var array<int, array<string, mixed>> $rejectionHistory
 * @var array<string, mixed> $performersData
 * @var array<string, mixed> $aiData
 * @var array<string, string> $languages
 * @var array<string, string> $qualities
 */

$movieId    = (int)$movie['movie_id'];
$fileId     = (string)$movie['file_id'];
$merchantId = (int)$movie['merchant_id'];
$actorId    = (int)$movie['actor_id'];
$playtime   = (int)$movie['playtime_seconds'];
$cStat      = (int)$movie['convert_status'];

$textCompliance = (string)($aiData['text_compliance'] ?? 'pending');
$textRiskScore  = (int)($aiData['text_risk_score'] ?? 0);
$textSummary    = (string)($aiData['text_summary'] ?? '');
$violations     = is_array($aiData['text_violations'] ?? null) ? $aiData['text_violations'] : [];
$segments       = is_array($aiData['transcript_segments'] ?? null) ? $aiData['transcript_segments'] : [];
$subStatus      = (int)($aiData['subtitle_status'] ?? 0);
$detLang        = strtoupper((string)($aiData['detected_language'] ?? ''));

$bramBannerClass = 'alert-success border-success';
$bramIconClass   = 'bi-shield-check text-success';
$bramTitle       = 'BRAM-Status: Unbedenklich (Grün)';
if (in_array($textCompliance, ['violation', 'red'], true)) {
    $bramBannerClass = 'alert-danger border-danger';
    $bramIconClass   = 'bi-shield-fill-exclamation text-danger';
    $bramTitle       = 'BRAM-Status: Kritischer Verstoß erkannt (Rot)';
} elseif (in_array($textCompliance, ['warning', 'yellow'], true)) {
    $bramBannerClass = 'alert-warning border-warning';
    $bramIconClass   = 'bi-exclamation-triangle-fill text-warning';
    $bramTitle       = 'BRAM-Status: Manuelle Prüfung empfohlen (Gelb)';
}

$categoryGroupLabels = [
    'number_of_people' => ['label' => 'Anzahl der Personen & sexuelle Orientierung', 'icon' => 'bi-people-fill'],
    'look_and_body'    => ['label' => 'Körper und Aussehen', 'icon' => 'bi-person-bounding-box'],
    'fetish'           => ['label' => 'Fetisch', 'icon' => 'bi-fire'],
    'porn'             => ['label' => 'Sonstige Kategorien', 'icon' => 'bi-tags-fill'],
];
?>
<div class="container-fluid py-3" data-page-width="full">

  <!-- ========================================================================
       SEITEN-HEADER (Reine Lese-Ansicht für Filme in Planung)
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div class="d-flex align-items-start gap-3">
      <a href="#/movies-drafts" class="btn btn-sm btn-outline-kkc mt-1">
        <i class="bi bi-arrow-left me-1"></i> Zur Übersicht
      </a>
      <div>
        <h1 class="h4 fw-bold mb-1 text-truncate" style="max-width: 720px;">
          <?= View::e((string)$movie['title'] ?: '(Noch kein Filmtitel vergeben)') ?>
        </h1>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="badge-kkc badge-kkc-neutral font-monospace">Film-ID #<?= $movieId ?></span>
          <span class="badge-kkc badge-kkc-neutral font-monospace">MID #<?= $merchantId ?></span>
          <span class="badge-kkc badge-kkc-neutral">
            <i class="bi bi-lock-fill me-1"></i>In Planung (Nur-Lesen)
          </span>
          <?php if ($cStat === 0): ?>
            <span class="badge-kkc badge-kkc-info"><i class="bi bi-hourglass me-1"></i>Warteschlange</span>
          <?php elseif ($cStat === 1): ?>
            <span class="badge-kkc badge-kkc-warning"><i class="bi bi-arrow-repeat me-1"></i>Konvertiert...</span>
          <?php elseif ($cStat === 2): ?>
            <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Konvertiert &amp; Bereit</span>
          <?php else: ?>
            <span class="badge-kkc badge-kkc-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>Konvertierungs-Fehler</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Vorheriger / Nächster Entwurf -->
    <div class="d-flex align-items-center gap-2">
      <?php if ($prevDraftId > 0): ?>
        <a href="#/movie-draft?id=<?= $prevDraftId ?>" class="btn btn-sm btn-outline-kkc" title="Vorheriger Entwurf (#<?= $prevDraftId ?>)">
          <i class="bi bi-chevron-left"></i> Vorheriger (#<?= $prevDraftId ?>)
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-kkc" disabled><i class="bi bi-chevron-left"></i> Vorheriger</button>
      <?php endif; ?>

      <?php if ($nextDraftId > 0): ?>
        <a href="#/movie-draft?id=<?= $nextDraftId ?>" class="btn btn-sm btn-outline-kkc" title="Nächster Entwurf (#<?= $nextDraftId ?>)">
          Nächster (#<?= $nextDraftId ?>) <i class="bi bi-chevron-right"></i>
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-kkc" disabled>Nächster <i class="bi bi-chevron-right"></i></button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Hinweis-Banner: Reine Lese-Ansicht -->
  <div class="alert alert-secondary py-2 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2 small">
    <div>
      <i class="bi bi-eye-fill me-2 ec-text-primary"></i>
      <strong>Reine Lese-Ansicht:</strong> Dieser Film befindet sich noch beim Creator in Planung (<code>released = 0</code>) und wurde noch nicht zur Prüfung eingereicht. Alle Felder können eingesehen, aber nicht bearbeitet werden.
    </div>
    <span class="badge bg-secondary bg-opacity-25 text-body font-monospace">released = <?= (int)$movie['released'] ?></span>
  </div>

  <!-- ========================================================================
       4-TAB NAVIGATION (Ansicht | BRAM | Transkript | Technik)
       ======================================================================== -->
  <ul class="nav nav-pills gap-2 mb-3" id="movieDraftTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-edit"
              data-bs-toggle="pill" data-bs-target="#tab-me-edit" type="button" role="tab">
        <i class="bi bi-eye"></i>
        <span>1. Film-Angaben &amp; Vorschau</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-bram"
              data-bs-toggle="pill" data-bs-target="#tab-me-bram" type="button" role="tab">
        <i class="bi bi-shield-exclamation"></i>
        <span>2. BRAM &amp; KI-Analyse</span>
        <span class="badge <?= in_array($textCompliance, ['violation', 'red'], true) ? 'bg-danger' : (in_array($textCompliance, ['warning', 'yellow'], true) ? 'bg-warning text-dark' : 'bg-success') ?>">
          Score: <?= $textRiskScore ?>
        </span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-transcript"
              data-bs-toggle="pill" data-bs-target="#tab-me-transcript" type="button" role="tab">
        <i class="bi bi-card-text"></i>
        <span>3. Audio &amp; Transkript (.vtt)</span>
        <span class="badge <?= $subStatus === 2 ? 'bg-info text-dark' : 'bg-secondary' ?>">
          <?= $subStatus === 2 ? ($detLang ?: 'Bereit') : 'Kein VTT' ?>
        </span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-tech"
              data-bs-toggle="pill" data-bs-target="#tab-me-tech" type="button" role="tab">
        <i class="bi bi-cpu"></i>
        <span>4. Technische Details</span>
      </button>
    </li>
  </ul>

  <div class="tab-content" id="movieDraftTabContent">

    <!-- ======================================================================
         TAB 1: FILM-ANGABEN & VORSCHAU (NUR-LESEN, WIE BEARBEITEN)
         ====================================================================== -->
    <div class="tab-pane fade show active" id="tab-me-edit" role="tabpanel">

      <!-- BRAM Schnellstatus-Banner -->
      <div class="alert <?= $bramBannerClass ?> d-flex flex-wrap align-items-center justify-content-between gap-2 py-2.5 px-3 mb-3">
        <div class="d-flex align-items-center gap-2">
          <i class="bi <?= $bramIconClass ?> fs-4"></i>
          <div>
            <div class="fw-bold"><?= View::e($bramTitle) ?> <span class="badge bg-dark ms-1">Risiko-Score: <?= $textRiskScore ?>/100</span></div>
            <div class="small"><?= View::e($textSummary ?: 'Keine verbotenen Begriffe in Titel, Beschreibung oder Untertiteln gefunden.') ?></div>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-1">
          <?php if (!empty($violations)): ?>
            <?php foreach (array_slice($violations, 0, 5) as $v):
                $vTerm = (string)($v['matched_term'] ?? $v['term'] ?? '');
                $vSrc  = (string)($v['source_label'] ?? $v['source'] ?? 'Text');
                $vTime = isset($v['timestamp_sec']) && $v['timestamp_sec'] !== null ? (float)$v['timestamp_sec'] : null;
            ?>
              <?php if ($vTime !== null): ?>
                <button type="button" class="btn btn-xs btn-danger py-0 px-2" style="font-size: 0.75rem;"
                        onclick="App.seekMoviePlayerTo(<?= $vTime ?>)">
                  <i class="bi bi-play-fill"></i> „<?= View::e($vTerm) ?>“ (<?= gmdate('i:s', (int)$vTime) ?>)
                </button>
              <?php else: ?>
                <span class="badge bg-danger">„<?= View::e($vTerm) ?>“ (<?= View::e($vSrc) ?>)</span>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-outline-dark py-0.5 px-2 ms-1"
                  onclick="document.getElementById('tab-btn-me-bram').click()">
            Details öffnen <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </div>

      <div class="row g-4">
        <!-- ================================================================
             LINKE SPALTE: VIDEOPLAYER, 18+ MITWIRKENDE & VORSCHAUBILDER
             ================================================================ -->
        <div class="col-xl-6">

          <!-- 1. Video-Player & Schnellsteuerung -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header">
              <div class="card-title">
                <i class="bi bi-play-circle-fill ec-text-primary"></i>
                <span>Video-Player &amp; Sichtung</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge-kkc badge-kkc-neutral font-monospace"><?= View::e((string)$movie['resolution'] ?: '-') ?> (<?= View::e((string)$movie['detected_quality']) ?>)</span>
                <span class="badge-kkc badge-kkc-neutral font-monospace"><?= View::e((string)$movie['playtime_formatted']) ?></span>
                <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" onclick="App.openMovieFileMetaModal(<?= $movieId ?>)">
                  <i class="bi bi-code-square me-1"></i> getID3 Infos
                </button>
              </div>
            </div>
            <div class="card-kkc-body p-3">
              <?php if ($cStat === 2): ?>
                <div class="rounded overflow-hidden bg-black mb-2 position-relative">
                  <video id="mc-video-player" class="w-100 d-block" style="max-height: 400px;" controls preload="metadata"
                         poster="media.php?type=poster&amp;file_id=<?= View::e($fileId) ?>&amp;fsk=18">
                    <source src="media.php?type=video&amp;file_id=<?= View::e($fileId) ?>" type="video/mp4">
                    <?php if (!empty($aiData['has_vtt'])): ?>
                    <track label="Transkript (<?= View::e($detLang ?: 'DE') ?>)" kind="subtitles"
                           srclang="<?= View::e(strtolower($detLang ?: 'de')) ?>"
                           src="media.php?type=vtt&amp;movie_id=<?= $movieId ?>" default>
                    <?php endif; ?>
                    Dein Browser unterstützt kein HTML5-Video.
                  </video>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-1">
                  <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(-10)"><i class="bi bi-skip-backward"></i> -10s</button>
                    <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(10)">+10s <i class="bi bi-skip-forward"></i></button>
                    <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(30)">+30s</button>
                    <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(60)">+60s</button>
                  </div>

                  <div class="d-flex align-items-center gap-1">
                    <span class="small text-muted me-1">Speed:</span>
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-kkc mc-speed-btn active" onclick="App.setMoviePlayerSpeed(1, this)">1x</button>
                      <button type="button" class="btn btn-outline-kkc mc-speed-btn" onclick="App.setMoviePlayerSpeed(1.5, this)">1.5x</button>
                      <button type="button" class="btn btn-outline-kkc mc-speed-btn" onclick="App.setMoviePlayerSpeed(2, this)">2x</button>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="p-4 text-center text-muted border rounded mb-2">
                  <i class="bi bi-gear-wide-connected fs-2 d-block mb-2 text-warning"></i>
                  <?= View::e((string)$movie['convert_status_text']) ?>
                </div>
              <?php endif; ?>

              <!-- Datei- & Merchant-Kurzinfos -->
              <div class="row g-2 mt-2 pt-2 border-top small text-muted" style="border-color: var(--kkc-border) !important;">
                <div class="col-sm-6">
                  <div><strong>Merchant:</strong> <a href="#/merchant?id=<?= $merchantId ?>" class="ec-text-primary text-decoration-none"><?= View::e((string)$movie['merchant_username']) ?> (MID #<?= $merchantId ?>)</a></div>
                  <div><strong>File-ID:</strong> <span class="font-monospace"><?= View::e($fileId) ?></span></div>
                </div>
                <div class="col-sm-6">
                  <div><strong>Dateigröße:</strong> <span class="font-monospace"><?= View::e((string)$movie['file_size_text']) ?></span></div>
                  <div><strong>Konvertierung:</strong> <span class="font-monospace"><?= View::e((string)$movie['convert_status_text']) ?></span></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. Mitwirkende Personen & 18+ ID-Shot-Übersicht (Nur-Lesen) -->
          <?php
            $perfMode    = (string)($performersData['performer_mode'] ?? 'solo_owner');
            $perfPersons = is_array($performersData['persons'] ?? null) ? $performersData['persons'] : [];
          ?>
          <div class="card-kkc mb-4">
            <div class="card-kkc-header flex-wrap gap-2">
              <div class="card-title">
                <i class="bi bi-person-badge-fill ec-text-primary"></i>
                <span>Mitwirkende Personen &amp; ID-Shot-Nachweise (18+ / Model Release)</span>
              </div>
              <?php if ($perfMode === 'solo_owner'): ?>
                <span class="badge-kkc badge-kkc-info">Solo: Nur Account-Inhaber (<?= View::e((string)$performersData['merchant_full_name']) ?>)</span>
              <?php else: ?>
                <span class="badge-kkc badge-kkc-warning">Mehrere Personen (<?= count($perfPersons) ?> gemeldet)</span>
              <?php endif; ?>
            </div>
            <div class="card-kkc-body">
              <?php if (empty($perfPersons)): ?>
                <div class="alert alert-secondary mb-0 py-2 small">
                  Keine zusätzlichen Drehpartner für diesen Film hinterlegt.
                </div>
              <?php else: ?>
                <div class="d-flex flex-column gap-3">
                  <?php foreach ($perfPersons as $p):
                      $pId     = (int)$p['id'];
                      $isOwner = !empty($p['is_owner']);
                      $pStatus = (string)($p['checked_status'] ?? 'pending_qr');
                      $isReady = !empty($p['is_ready_for_use']);
                      $hasDocs = !empty($p['has_uploaded_docs']) || !empty($p['has_id_shot']) || !empty($p['face_ref_url']) || !empty($p['id_front_url']);

                      $pBadgeCls = 'badge-kkc-warning';
                      if ($pStatus === 'verified') {
                          $pBadgeCls = 'badge-kkc-success';
                      } elseif ($pStatus === 'rejected') {
                          $pBadgeCls = 'badge-kkc-danger';
                      } elseif ($pStatus === 'under_review') {
                          $pBadgeCls = 'badge-kkc-info';
                      }
                  ?>
                  <div class="p-3 rounded border" style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                      <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge <?= $isOwner ? 'bg-primary' : 'bg-secondary' ?>">
                          <?= $isOwner ? 'ICH (ACCOUNT-INHABER)' : 'DREHPARTNER' ?>
                        </span>
                        <strong class="text-body"><?= View::e((string)($p['display_label'] ?? '')) ?></strong>
                      </div>
                      <span class="badge-kkc <?= $pBadgeCls ?>">
                        <?= View::e((string)($p['status_label'] ?? $pStatus)) ?>
                      </span>
                    </div>

                    <?php if ($hasDocs): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <?php if (!empty($p['face_ref_url'])): ?>
                      <a href="<?= View::e((string)$p['face_ref_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none border rounded p-1 bg-body text-center">
                        <img src="<?= View::e((string)$p['face_ref_url']) ?>" alt="KI-Selfie" style="width: 70px; height: 50px; object-fit: cover; display: block;" class="rounded">
                        <span class="d-block text-muted" style="font-size: 0.65rem;">1. KI-Selfie</span>
                      </a>
                      <?php endif; ?>
                      <?php if (!empty($p['id_shot_url'])): ?>
                      <a href="<?= View::e((string)$p['id_shot_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none border rounded p-1 bg-body text-center">
                        <img src="<?= View::e((string)$p['id_shot_url']) ?>" alt="ID-Shot" style="width: 70px; height: 50px; object-fit: cover; display: block;" class="rounded">
                        <span class="d-block text-muted" style="font-size: 0.65rem;">2. ID-Shot</span>
                      </a>
                      <?php endif; ?>
                      <?php if (!empty($p['id_front_url'])): ?>
                      <a href="<?= View::e((string)$p['id_front_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none border rounded p-1 bg-body text-center">
                        <img src="<?= View::e((string)$p['id_front_url']) ?>" alt="Ausweis" style="width: 70px; height: 50px; object-fit: cover; display: block;" class="rounded">
                        <span class="d-block text-muted" style="font-size: 0.65rem;">3. Ausweis</span>
                      </a>
                      <?php endif; ?>
                      <?php if ($isReady && !empty($p['certificate_url'])): ?>
                      <a href="<?= View::e((string)$p['certificate_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> PDF-Freigabe
                      </a>
                      <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-secondary py-2 px-3 mb-0 small d-flex align-items-center gap-2">
                      <i class="bi bi-info-circle-fill ec-text-primary flex-shrink-0"></i>
                      <span>Noch keine ID-Shot-Dokumente hochgeladen.</span>
                    </div>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- 3. Szenen-Vorschaubilder (FSK16 & FSK18 Ansicht 1..12, Nur-Lesen) -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header flex-wrap gap-2">
              <div class="card-title">
                <i class="bi bi-images ec-text-primary"></i>
                <span>Vorschaubilder (FSK16 &amp; FSK18 – Nur-Lesen)</span>
              </div>
              <div class="d-flex align-items-center gap-2 small">
                <span class="badge bg-info text-dark">FSK16: Bild #<?= (int)$movie['preview_image_fsk16'] ?></span>
                <span class="badge bg-danger text-white">FSK18: Bild #<?= (int)$movie['preview_image_fsk18'] ?></span>
              </div>
            </div>
            <div class="card-kkc-body">
              <?php if ($cStat === 2): ?>
              <div class="row g-2">
                <?php for ($i = 1; $i <= 10; $i++):
                    $isFsk16 = ((int)$movie['preview_image_fsk16'] === $i);
                    $isFsk18 = ((int)$movie['preview_image_fsk18'] === $i);
                ?>
                <div class="col-md-4 col-6">
                  <div class="p-2 rounded border h-100 <?= ($isFsk16 || $isFsk18) ? 'border-primary' : '' ?>"
                       style="background: var(--kkc-surface-hover);">
                    <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                      <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=<?= $i ?>&amp;w=320"
                           alt="Szene #<?= $i ?>" loading="lazy"
                           class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                           onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=<?= $i ?>', 'Szene #<?= $i ?> – Entwurf #<?= $movieId ?>')">
                      <span class="position-absolute top-0 start-0 m-1 px-1.5 py-0.5 rounded bg-dark bg-opacity-75 text-white font-monospace" style="font-size: 0.7rem;">#<?= $i ?></span>
                    </div>
                    <div class="d-flex gap-1">
                      <button type="button" class="btn btn-sm flex-fill py-1 <?= $isFsk16 ? 'btn-info text-dark fw-bold' : 'btn-outline-kkc' ?>" disabled>FSK16</button>
                      <button type="button" class="btn btn-sm flex-fill py-1 <?= $isFsk18 ? 'btn-danger text-white fw-bold' : 'btn-outline-kkc' ?>" disabled>FSK18</button>
                    </div>
                  </div>
                </div>
                <?php endfor; ?>

                <!-- Individuelles FSK16 (#11) & FSK18 (#12) -->
                <div class="col-md-6 col-6">
                  <?php $isCustom16 = ((int)$movie['preview_image_fsk16'] === 11); ?>
                  <div class="p-2 rounded border h-100 <?= $isCustom16 ? 'border-primary' : '' ?>">
                    <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                      <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=11&amp;w=320"
                           alt="Individuelles FSK16 (#11)" class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                           onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=11', 'Individuelles FSK16 (#11)')">
                      <span class="position-absolute top-0 start-0 m-1 px-2 py-0.5 rounded bg-info text-dark fw-bold" style="font-size: 0.7rem;">Individuelles FSK16 (#11)</span>
                    </div>
                    <button type="button" class="btn btn-sm w-100 py-1 <?= $isCustom16 ? 'btn-info text-dark fw-bold' : 'btn-outline-kkc' ?>" disabled>FSK16 (#11)</button>
                  </div>
                </div>

                <div class="col-md-6 col-6">
                  <?php $isCustom18 = ((int)$movie['preview_image_fsk18'] === 12); ?>
                  <div class="p-2 rounded border h-100 <?= $isCustom18 ? 'border-primary' : '' ?>">
                    <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                      <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=12&amp;w=320"
                           alt="Individuelles FSK18 (#12)" class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                           onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=12', 'Individuelles FSK18 (#12)')">
                      <span class="position-absolute top-0 start-0 m-1 px-2 py-0.5 rounded bg-danger text-white fw-bold" style="font-size: 0.7rem;">Individuelles FSK18 (#12)</span>
                    </div>
                    <button type="button" class="btn btn-sm w-100 py-1 <?= $isCustom18 ? 'btn-danger text-white fw-bold' : 'btn-outline-kkc' ?>" disabled>FSK18 (#12)</button>
                  </div>
                </div>
              </div>
              <?php else: ?>
                <div class="text-muted small fst-italic py-2">
                  Vorschaubilder werden erst nach Abschluss der Video-Konvertierung bereitgestellt.
                </div>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <!-- ================================================================
             RECHTE SPALTE: METADATEN, STATUS, KATEGORIEN & HISTORIE (NUR-LESEN)
             ================================================================ -->
        <div class="col-xl-6">

          <!-- 1. Film-Status & Bearbeitungs-Protokoll (Nur-Lesen) -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header">
              <div class="card-title">
                <i class="bi bi-toggle-on ec-text-primary"></i>
                <span>Film-Status &amp; Entwurfs-Protokoll</span>
              </div>
              <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-lock me-1"></i>Nur-Lesen</span>
            </div>
            <div class="card-kkc-body">
              <div class="d-flex flex-wrap gap-3 mb-2">
                <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover);">
                  <input type="radio" class="form-check-input mt-0" disabled <?= $movie['status'] === 'active' ? 'checked' : '' ?>>
                  <span class="fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i>Aktiv (In Planung)</span>
                </label>
                <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover);">
                  <input type="radio" class="form-check-input mt-0" disabled <?= $movie['status'] === 'blocked' ? 'checked' : '' ?>>
                  <span class="fw-bold text-warning"><i class="bi bi-pause-circle-fill me-1"></i>Gesperrt</span>
                </label>
                <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover);">
                  <input type="radio" class="form-check-input mt-0" disabled <?= $movie['status'] === 'deleted' ? 'checked' : '' ?>>
                  <span class="fw-bold text-danger"><i class="bi bi-trash3-fill me-1"></i>Gelöscht</span>
                </label>
              </div>

              <div class="d-flex flex-wrap gap-3 small text-muted pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                <div>
                  <i class="bi bi-calendar-plus ec-text-primary me-1"></i>
                  Hochgeladen am <strong><?= View::e($movie['create_datetime'] !== '' && $movie['create_datetime'] !== '0000-00-00 00:00:00' ? date('d.m.Y \u\m H:i', strtotime((string)$movie['create_datetime'])) : '-') ?></strong>
                </div>
                <?php if ($movie['last_updated_by_name'] !== '-'): ?>
                <div>
                  <i class="bi bi-clock-history ec-text-primary me-1"></i>
                  Zuletzt bearbeitet am <strong><?= View::e($movie['last_updated_date'] !== '' && $movie['last_updated_date'] !== '0000-00-00 00:00:00' ? date('d.m.Y \u\m H:i', strtotime((string)$movie['last_updated_date'])) : '-') ?> Uhr</strong>
                  von <strong><?= View::e((string)$movie['last_updated_by_name']) ?></strong>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- 2. Angaben zum Film, Preise & SEO (Nur-Lesen) -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header">
              <div class="card-title">
                <i class="bi bi-sliders ec-text-primary"></i>
                <span>Angaben zum Film, Preise &amp; SEO (Nur-Lesen)</span>
              </div>
            </div>
            <div class="card-kkc-body">
              <div class="row g-3">

                <!-- Hauptdarsteller -->
                <div class="col-md-6">
                  <label class="form-label small fw-bold mb-1">Hauptdarsteller im Film</label>
                  <div class="input-group input-group-sm">
                    <select class="form-select" disabled>
                      <option value="0">-- Noch kein Darsteller gewählt --</option>
                      <?php foreach ($merchantActors as $ma): ?>
                      <option value="<?= (int)$ma['id'] ?>" <?= (int)$ma['id'] === $actorId ? 'selected' : '' ?>>
                        <?= View::e((string)$ma['username']) ?> (#<?= (int)$ma['id'] ?>)
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ($actorId > 0): ?>
                    <a href="#/actor?id=<?= $actorId ?>" class="btn btn-outline-kkc" title="Profil anzeigen">
                      <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Haupt-Filmsprache -->
                <div class="col-md-6">
                  <label class="form-label small fw-bold mb-1">Haupt-Filmsprache</label>
                  <select class="form-select form-select-sm" disabled>
                    <?php foreach ($languages as $lKey => $lName): ?>
                    <option value="<?= View::e($lKey) ?>" <?= $movie['movie_language'] === $lKey ? 'selected' : '' ?>>
                      <?= View::e($lName) ?> (<?= strtoupper(View::e($lKey)) ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Filmtitel -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-bold mb-0">Filmtitel</label>
                    <small class="text-muted font-monospace"><?= mb_strlen((string)$movie['title']) ?> Zeichen</small>
                  </div>
                  <input type="text" class="form-control form-control-sm"
                         value="<?= View::e((string)$movie['title']) ?>" readonly disabled placeholder="(Noch kein Filmtitel vergeben)">
                </div>

                <!-- Beschreibung -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-bold mb-0">Filmbeschreibung</label>
                    <small class="text-muted font-monospace"><?= mb_strlen((string)$movie['description']) ?> Zeichen</small>
                  </div>
                  <textarea class="form-control form-control-sm" rows="6" readonly disabled placeholder="(Noch keine Beschreibung hinterlegt)"><?= View::e((string)$movie['description']) ?></textarea>
                </div>

                <!-- Online ab & Streaming-Preis -->
                <div class="col-md-5">
                  <label class="form-label small fw-bold mb-1">Frühestens online ab (Geplant für)</label>
                  <input type="datetime-local" class="form-control form-control-sm"
                         value="<?= View::e((string)$movie['online_at_input']) ?>" readonly disabled>
                </div>

                <div class="col-md-7">
                  <label class="form-label small fw-bold mb-1">Streaming-Preis (Cent / Coins je Sekunde)</label>
                  <div class="input-group input-group-sm">
                    <input type="text" class="form-control font-monospace" value="<?= View::e((string)$movie['amount_second']) ?> Cent/Sek." readonly disabled>
                    <span class="input-group-text fw-bold ec-text-primary">
                      = <?= (int)$movie['total_coins'] ?> Coins gesamt
                    </span>
                  </div>
                </div>

                <!-- Download-Option & Download-Aufschlag -->
                <div class="col-md-5">
                  <label class="form-label small fw-bold mb-1">Zum Download anbieten?</label>
                  <select class="form-select form-select-sm" disabled>
                    <option value="1" <?= !empty($movie['as_download']) ? 'selected' : '' ?>>Ja</option>
                    <option value="0" <?= empty($movie['as_download']) ? 'selected' : '' ?>>Nein (Nur Stream)</option>
                  </select>
                </div>

                <div class="col-md-7" style="<?= empty($movie['as_download']) ? 'opacity: 0.5;' : '' ?>">
                  <label class="form-label small fw-bold mb-1">Download-Aufschlag (%)</label>
                  <input type="text" class="form-control form-control-sm font-monospace" value="+<?= (int)$movie['amount_download'] ?>%" readonly disabled>
                </div>

                <!-- Qualität, Trailer & Sichtbarkeit -->
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Qualität</label>
                  <select class="form-select form-select-sm" disabled>
                    <?php foreach ($qualities as $qKey => $qLabel): ?>
                    <option value="<?= View::e($qKey) ?>" <?= $movie['quality'] === $qKey ? 'selected' : '' ?>><?= View::e($qLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Ist der Film ein Trailer?</label>
                  <select class="form-select form-select-sm" disabled>
                    <option value="0" <?= (int)$movie['preview'] === 0 ? 'selected' : '' ?>>Nein</option>
                    <option value="1" <?= (int)$movie['preview'] === 1 ? 'selected' : '' ?>>Ja</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Sichtbar auf</label>
                  <select class="form-select form-select-sm" disabled>
                    <option value="public" <?= $movie['visible_for_website'] === 'public' ? 'selected' : '' ?>>
                      allen Partnerwebsites (public)
                    </option>
                    <?php foreach ($merchantDomains as $dom): ?>
                    <option value="<?= View::e($dom) ?>" <?= $movie['visible_for_website'] === $dom ? 'selected' : '' ?>>
                      <?= View::e($dom) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- SEO-Felder -->
                <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <span class="small fw-bold text-uppercase text-muted">Suchmaschinenoptimierung (SEO)</span>
                  </div>

                  <div class="row g-2">
                    <div class="col-md-6">
                      <div class="d-flex justify-content-between small mb-1">
                        <label class="text-muted">Meta-Title (max. 65)</label>
                        <span class="font-monospace text-muted"><?= mb_strlen((string)$movie['meta_title']) ?>/65</span>
                      </div>
                      <input type="text" class="form-control form-control-sm"
                             value="<?= View::e((string)$movie['meta_title']) ?>" readonly disabled>
                    </div>

                    <div class="col-md-6">
                      <div class="d-flex justify-content-between small mb-1">
                        <label class="text-muted">SEO-URL (URL-Name)</label>
                      </div>
                      <input type="text" class="form-control form-control-sm font-monospace"
                             value="<?= View::e((string)$movie['seo_url']) ?>" readonly disabled>
                    </div>

                    <div class="col-12">
                      <div class="d-flex justify-content-between small mb-1">
                        <label class="text-muted">Meta-Description (max. 156)</label>
                        <span class="font-monospace text-muted"><?= mb_strlen((string)$movie['meta_description']) ?>/156</span>
                      </div>
                      <textarea rows="2" class="form-control form-control-sm" readonly disabled><?= View::e((string)$movie['meta_description']) ?></textarea>
                    </div>
                  </div>
                </div>

                <!-- Admin-Infos -->
                <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <label class="form-label small fw-bold mb-1">Interne Admin-Infos zum Film (`admin_infos`)</label>
                  <textarea class="form-control form-control-sm" rows="2" readonly disabled
                            placeholder="Keine internen Notizen hinterlegt..."><?= View::e((string)$movie['admin_infos']) ?></textarea>
                </div>

              </div>
            </div>
          </div>

          <!-- 3. Kategorisierung (Nur-Lesen) -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header flex-wrap gap-2">
              <div class="card-title">
                <i class="bi bi-tags-fill ec-text-primary"></i>
                <span>Gewählte Kategorien (Nur-Lesen)</span>
              </div>
              <span class="badge-kkc badge-kkc-info">
                <?= count($movie['selected_slaves']) ?> Unterkategorien gewählt
              </span>
            </div>
            <div class="card-kkc-body">
              <div class="mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
                <label class="form-label small fw-bold d-block mb-2">Hauptkategorie</label>
                <div class="d-flex gap-3">
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover);">
                    <input type="radio" class="form-check-input mt-0" disabled
                           <?= $movie['category_master'] === 'porn' ? 'checked' : '' ?>>
                    <span class="fw-bold">Porno</span>
                  </label>
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover);">
                    <input type="radio" class="form-check-input mt-0" disabled
                           <?= $movie['category_master'] === 'fetish' ? 'checked' : '' ?>>
                    <span class="fw-bold">Fetisch</span>
                  </label>
                </div>
              </div>

              <div class="mb-3">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
                  <input type="search" id="mc-cat-search-input" class="form-control"
                         placeholder="Kategorien filtern (z.B. Amateur, Blowjob, BDSM...)"
                         oninput="App.filterMovieCategories(this.value)">
                </div>
              </div>

              <div class="d-flex flex-column gap-3" style="max-height: 440px; overflow-y: auto; padding-right: 4px;">
                <?php foreach ($categoryGroupLabels as $cType => $grpMeta):
                    $catItems = $categoriesByType[$cType] ?? [];
                    if (empty($catItems)) {
                        continue;
                    }
                ?>
                <div class="mc-cat-group">
                  <div class="small fw-bold text-uppercase text-muted mb-2 d-flex align-items-center gap-1">
                    <i class="bi <?= $grpMeta['icon'] ?> ec-text-primary"></i>
                    <span><?= View::e($grpMeta['label']) ?></span>
                  </div>
                  <div class="row g-1">
                    <?php foreach ($catItems as $cat):
                        $idName = (string)$cat['id_name'];
                        $deName = (string)$cat['de'];
                        $deText = (string)($cat['de_text'] ?? '');
                        $isSel  = !empty($cat['selected']);
                        $inTxt  = !empty($cat['in_text']);
                    ?>
                    <div class="col-sm-6 col-md-4 mc-cat-item" data-cat-label="<?= View::e(strtolower($deName . ' ' . $idName . ' ' . $deText)) ?>">
                      <label class="d-flex align-items-center gap-2 rounded border small w-100 <?= $isSel ? 'border-primary bg-primary bg-opacity-10 fw-semibold' : ($inTxt ? 'border-warning bg-warning bg-opacity-10' : '') ?>"
                             style="padding: 5px 8px;" title="<?= View::e($deText) ?>">
                        <input type="checkbox" class="form-check-input mt-0" disabled
                               <?= $isSel ? 'checked' : '' ?>>
                        <span class="text-truncate"><?= View::e($deName) ?></span>
                        <?php if ($inTxt && !$isSel): ?>
                        <span class="ms-auto badge bg-warning text-dark" style="font-size: 0.6rem;">Text</span>
                        <?php endif; ?>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- 4. Ablehnungs-Historie (falls vorhanden) -->
          <?php if (!empty($rejectionHistory)): ?>
          <div class="card-kkc mb-4 border-warning">
            <div class="card-kkc-header">
              <div class="card-title text-warning">
                <i class="bi bi-clock-history"></i>
                <span>Bisherige Ablehnungs-Historie (<?= count($rejectionHistory) ?>)</span>
              </div>
            </div>
            <div class="card-kkc-body">
              <div class="d-flex flex-column gap-2">
                <?php foreach ($rejectionHistory as $rh): ?>
                <div class="p-2.5 rounded border small" style="background: var(--kkc-surface-hover);">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="text-danger"><?= View::e((string)$rh['short_title']) ?></strong>
                    <span class="text-muted font-monospace" style="font-size: 0.72rem;">
                      <?= View::e((string)$rh['datetime']) ?> &bull; von <?= View::e((string)$rh['employee_name']) ?>
                    </span>
                  </div>
                  <div class="text-muted"><?= nl2br(View::e((string)$rh['text'])) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- ======================================================================
         TAB 2: BRAM & KI-ANALYSE
         ====================================================================== -->
    <div class="tab-pane fade" id="tab-me-bram" role="tabpanel">
      <div class="card-kkc">
        <div class="card-kkc-header flex-wrap gap-2">
          <div class="card-title">
            <i class="bi bi-shield-check ec-text-primary"></i>
            <span>Visa / Mastercard BRAM &amp; KI-Compliance-Report</span>
          </div>
        </div>
        <div class="card-kkc-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small mb-1">Compliance-Status</div>
                <div class="h4 fw-bold mb-1 <?= in_array($textCompliance, ['violation', 'red'], true) ? 'text-danger' : (in_array($textCompliance, ['warning', 'yellow'], true) ? 'text-warning' : 'text-success') ?>">
                  <?= in_array($textCompliance, ['violation', 'red'], true) ? 'Kritischer Verstoß (Rot)' : (in_array($textCompliance, ['warning', 'yellow'], true) ? 'Warnung / Prüfen (Gelb)' : 'Unbedenklich (Grün)') ?>
                </div>
                <small class="text-muted"><?= View::e($textSummary) ?></small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small mb-1">Risiko-Score</div>
                <div class="h3 fw-bold mb-1 font-monospace"><?= $textRiskScore ?> / 100</div>
                <div class="progress" style="height: 8px;">
                  <div class="progress-bar <?= $textRiskScore >= 80 ? 'bg-danger' : ($textRiskScore >= 35 ? 'bg-warning' : 'bg-success') ?>"
                       style="width: <?= min(100, $textRiskScore) ?>%;"></div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small mb-1">Analyse-Engine &amp; Stand</div>
                <div class="fw-bold font-monospace mb-1"><?= View::e((string)($aiData['text_engine_used'] ?? 'keyword_matrix')) ?></div>
                <small class="text-muted">Letzter Lauf: <?= View::e((string)($aiData['updated_at'] ?: '-')) ?></small>
              </div>
            </div>
          </div>

          <?php if (empty($violations)): ?>
            <div class="alert alert-success mb-0 d-flex align-items-center gap-2">
              <i class="bi bi-check-circle-fill fs-5"></i>
              <span>Keine bedenklichen Begriffe in Titel, Beschreibung, SEO-Daten oder gesprochenem Audio-Transkript gefunden.</span>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-kkc align-middle mb-0">
                <thead>
                  <tr>
                    <th>Begriff / Treffer</th>
                    <th>Kategorie</th>
                    <th>Schweregrad</th>
                    <th>Quelle</th>
                    <th>Zeitstempel</th>
                    <th>Kontext / Zitat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($violations as $v):
                      $sev    = (string)($v['severity'] ?? 'warning');
                      $isCrit = in_array($sev, ['violation', 'critical'], true);
                      $vTime  = isset($v['timestamp_sec']) && $v['timestamp_sec'] !== null ? (float)$v['timestamp_sec'] : null;
                      $vTerm  = (string)($v['matched_term'] ?? $v['term'] ?? '');
                      $vSrc   = (string)($v['source_label'] ?? $v['source'] ?? 'Text');
                      $vQuote = (string)($v['matched_quote'] ?? $v['snippet'] ?? $v['quote'] ?? '');
                  ?>
                  <tr>
                    <td class="fw-bold text-danger">„<?= View::e($vTerm) ?>“</td>
                    <td><span class="badge-kkc badge-kkc-neutral"><?= View::e((string)($v['category_label'] ?? $v['category'] ?? '')) ?></span></td>
                    <td>
                      <span class="badge-kkc <?= $isCrit ? 'badge-kkc-danger' : 'badge-kkc-warning' ?>">
                        <?= $isCrit ? 'Kritisch' : 'Warnung' ?>
                      </span>
                    </td>
                    <td><?= View::e($vSrc) ?></td>
                    <td>
                      <?php if ($vTime !== null): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 font-monospace"
                                onclick="document.getElementById('tab-btn-me-edit').click(); App.seekMoviePlayer(<?= $vTime ?>)">
                          <i class="bi bi-play-fill"></i> <?= gmdate('i:s', (int)$vTime) ?>
                        </button>
                      <?php else: ?>
                        <span class="text-muted"><?= View::e((string)($v['timestamp'] ?? '-')) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="small text-muted font-monospace"><?= View::e($vQuote) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ======================================================================
         TAB 3: AUDIO & TRANSKRIPT (.VTT)
         ====================================================================== -->
    <div class="tab-pane fade" id="tab-me-transcript" role="tabpanel">
      <div class="card-kkc">
        <div class="card-kkc-header flex-wrap gap-2">
          <div class="card-title">
            <i class="bi bi-mic-fill ec-text-primary"></i>
            <span>Lokale KI-Spracherkennung (faster-whisper) &amp; Untertitel (.vtt)</span>
          </div>
        </div>
        <div class="card-kkc-body">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="p-3 rounded border" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small">Transkript-Status</div>
                <div class="fw-bold"><?= $subStatus === 2 ? 'Erfolgreich transkribiert' : ($subStatus === 3 ? 'Fehler' : 'Noch nicht erstellt') ?></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 rounded border" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small">Erkannte Sprache &amp; Konfidenz</div>
                <div class="fw-bold font-monospace"><?= View::e($detLang ?: '-') ?> (<?= number_format(((float)$aiData['language_probability']) * 100, 1, ',', '.') ?>%)</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 rounded border" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small">Untertitel-Datei</div>
                <div class="fw-bold font-monospace"><?= !empty($aiData['has_vtt']) ? View::e((string)$aiData['vtt_filename']) : 'Keine .vtt Datei' ?></div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label small fw-bold">Klickbare Zeitstempel-Segmente (<?= count($segments) ?>)</label>
              <div class="border rounded p-2" style="max-height: 360px; overflow-y: auto; background: var(--kkc-surface-hover);">
                <?php if (empty($segments)): ?>
                  <div class="text-muted small p-3 text-center">Keine Zeitstempel-Segmente vorhanden.</div>
                <?php else: ?>
                  <?php foreach ($segments as $seg):
                      $sTime = (float)($seg['start'] ?? 0);
                      $eTime = (float)($seg['end'] ?? 0);
                      $sText = (string)($seg['text'] ?? '');
                  ?>
                  <div class="d-flex align-items-start gap-2 py-1.5 px-2 border-bottom small">
                    <button type="button" class="btn btn-xs btn-outline-kkc py-0 px-1.5 font-monospace flex-shrink-0"
                            onclick="document.getElementById('tab-btn-me-edit').click(); App.seekMoviePlayer(<?= $sTime ?>)">
                      <?= gmdate('i:s', (int)$sTime) ?>–<?= gmdate('i:s', (int)$eTime) ?>
                    </button>
                    <span><?= View::e($sText) ?></span>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-lg-6">
              <label class="form-label small fw-bold">Volltext-Transkript (Nur-Lesen)</label>
              <textarea class="form-control form-control-sm font-monospace mb-3" rows="7" readonly><?= View::e((string)$aiData['transcript_text']) ?></textarea>

              <label class="form-label small fw-bold">WebVTT-Dateiinhalt (`.vtt`)</label>
              <textarea class="form-control form-control-sm font-monospace" rows="6" readonly><?= View::e((string)$aiData['vtt_content']) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================
         TAB 4: TECHNISCHE DETAILS
         ====================================================================== -->
    <div class="tab-pane fade" id="tab-me-tech" role="tabpanel">
      <div class="card-kkc">
        <div class="card-kkc-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="card-title">
            <i class="bi bi-hdd-rack-fill ec-text-primary"></i>
            <span>Technische Filmdetails &amp; Konvertierungs-Metadaten</span>
          </div>
          <button type="button" class="btn btn-sm btn-kkc" onclick="App.openMovieFileMetaModal(<?= $movieId ?>)">
            <i class="bi bi-code-square me-1"></i> Vollständige getID3-Analyse öffnen
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-kkc mb-0">
            <tbody>
              <tr>
                <th style="width: 280px;">Film-ID (Tabelle `movies`):</th>
                <td class="font-monospace">#<?= $movieId ?></td>
              </tr>
              <tr>
                <th>Eindeutige File-ID (Hash):</th>
                <td class="font-monospace"><?= View::e($fileId) ?></td>
              </tr>
              <tr>
                <th>Hochgeladen / Erstellt am:</th>
                <td class="font-monospace"><?= View::e((string)$movie['create_datetime'] ?: '-') ?></td>
              </tr>
              <tr>
                <th>Absoluter Dateipfad:</th>
                <td class="font-monospace text-break"><?= View::e((string)$movie['video_path']) ?></td>
              </tr>
              <tr>
                <th>Dateiname:</th>
                <td class="font-monospace"><?= View::e((string)$movie['filename']) ?></td>
              </tr>
              <tr>
                <th>Dateigröße auf Server:</th>
                <td>
                  <span class="badge-kkc <?= $movie['file_exists'] ? 'badge-kkc-success' : 'badge-kkc-danger' ?> font-monospace">
                    <?= View::e((string)$movie['file_size_text']) ?>
                  </span>
                </td>
              </tr>
              <tr>
                <th>Auflösung:</th>
                <td class="font-monospace"><?= View::e((string)$movie['resolution']) ?> (Erkannt: <?= View::e((string)$movie['detected_quality']) ?>)</td>
              </tr>
              <tr>
                <th>Spieldauer:</th>
                <td class="font-monospace"><?= View::e((string)$movie['playtime_formatted']) ?> (<?= $playtime ?> Sekunden)</td>
              </tr>
              <tr>
                <th>Checksumme (MD5):</th>
                <td class="font-monospace"><?= View::e((string)$movie['checksum']) ?></td>
              </tr>
              <tr>
                <th>Konvertierungsstatus:</th>
                <td><span class="badge-kkc badge-kkc-info"><?= View::e((string)$movie['convert_status_text']) ?></span></td>
              </tr>
              <tr>
                <th>Konvertierungsstart:</th>
                <td class="font-monospace"><?= View::e((string)$movie['convert_starttime'] ?: '-') ?></td>
              </tr>
              <tr>
                <th>Konvertierungsende:</th>
                <td class="font-monospace"><?= View::e((string)$movie['convert_endtime'] ?: '-') ?></td>
              </tr>
              <tr>
                <th>Konvertierungsdauer:</th>
                <td class="font-monospace fw-bold"><?= View::e((string)$movie['convert_duration']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</div>

<!-- Bild-Lightbox Modal -->
<div class="modal fade" id="mc-image-preview-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold" id="mc-image-preview-title">Vorschaubild</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-2 text-center bg-black">
        <img src="" id="mc-image-preview-img" alt="Vorschau" class="img-fluid rounded" style="max-height: 78vh;">
      </div>
    </div>
  </div>
</div>

<!-- getID3 Datei-Metadaten Modal -->
<div class="modal fade" id="mc-file-meta-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-code-square ec-text-primary me-2"></i>Technische Datei-Metadaten (getID3)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body" id="mc-file-meta-body">
        <div class="text-center py-4 text-muted">Lade Datei-Metadaten...</div>
      </div>
    </div>
  </div>
</div>
