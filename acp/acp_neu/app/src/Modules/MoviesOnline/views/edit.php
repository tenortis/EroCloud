<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<string, mixed> $movie
 * @var int $prevOnlineId
 * @var int $nextOnlineId
 * @var array<int, array<string, mixed>> $merchantActors
 * @var array<int, string> $merchantDomains
 * @var array<string, array<int, array<string, mixed>>> $categoriesByType
 * @var array<int, array<string, mixed>> $rejectionHistory
 * @var array<string, mixed> $performersData
 * @var array<string, mixed> $aiData
 * @var array<string, mixed> $purchasesData
 * @var array<string, string> $languages
 * @var array<string, string> $qualities
 * @var string $employeeRule
 */

$onlineId   = (int)$movie['online_id'];
$movieId    = (int)$movie['movie_id'];
$fileId     = (string)$movie['file_id'];
$merchantId = (int)$movie['merchant_id'];
$actorId    = (int)$movie['actor_id'];
$playtime   = (int)$movie['playtime_seconds'];

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
if ($textCompliance === 'red') {
    $bramBannerClass = 'alert-danger border-danger';
    $bramIconClass   = 'bi-shield-fill-exclamation text-danger';
    $bramTitle       = 'BRAM-Status: Kritischer Verstoß erkannt (Rot)';
} elseif ($textCompliance === 'yellow') {
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

$buysCount = (int)($purchasesData['total_buys'] ?? 0);
?>
<div class="container-fluid py-3" data-page-width="full">

  <!-- ========================================================================
       STICKY ACTION HEADER (Navigation & Haupt-Tabs)
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div class="d-flex align-items-center gap-3">
      <a href="#/movies-online" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-arrow-left me-1"></i> Zur Übersicht
      </a>
      <div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge-kkc badge-kkc-info font-monospace">Online-ID #<?= $onlineId ?></span>
          <span class="badge-kkc badge-kkc-neutral font-monospace">Film-ID #<?= $movieId ?></span>
          <span class="badge-kkc badge-kkc-neutral font-monospace">MID #<?= $merchantId ?></span>
          <?php if ($movie['status'] === 'active'): ?>
            <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Aktiv</span>
          <?php elseif ($movie['status'] === 'blocked'): ?>
            <span class="badge-kkc badge-kkc-warning"><i class="bi bi-pause-circle-fill me-1"></i>Gesperrt</span>
          <?php else: ?>
            <span class="badge-kkc badge-kkc-danger"><i class="bi bi-trash3-fill me-1"></i>Gelöscht</span>
          <?php endif; ?>
        </div>
        <h1 class="h4 fw-bold mb-0 mt-1 text-truncate" style="max-width: 720px;">
          <?= View::e((string)$movie['title'] ?: '(Ohne Titel)') ?>
        </h1>
      </div>
    </div>

    <!-- Vorheriger / Nächster Online-Film -->
    <div class="d-flex align-items-center gap-2">
      <?php if ($prevOnlineId > 0): ?>
        <a href="#/movie-edit?id=<?= $prevOnlineId ?>" class="btn btn-sm btn-outline-kkc" title="Vorheriger Online-Film (#<?= $prevOnlineId ?>)">
          <i class="bi bi-chevron-left"></i> Vorheriger (#<?= $prevOnlineId ?>)
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-kkc" disabled><i class="bi bi-chevron-left"></i> Vorheriger</button>
      <?php endif; ?>

      <?php if ($nextOnlineId > 0): ?>
        <a href="#/movie-edit?id=<?= $nextOnlineId ?>" class="btn btn-sm btn-outline-kkc" title="Nächster Online-Film (#<?= $nextOnlineId ?>)">
          Nächster (#<?= $nextOnlineId ?>) <i class="bi bi-chevron-right"></i>
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-kkc" disabled>Nächster <i class="bi bi-chevron-right"></i></button>
      <?php endif; ?>
    </div>
  </div>

  <!-- ========================================================================
       5-TAB NAVIGATION (Bearbeiten | BRAM | Transkript | Käufe | Technik)
       ======================================================================== -->
  <ul class="nav nav-pills gap-2 mb-3" id="movieEditTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-edit"
              data-bs-toggle="pill" data-bs-target="#tab-me-edit" type="button" role="tab">
        <i class="bi bi-pencil-square"></i>
        <span>1. Bearbeiten</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-bram"
              data-bs-toggle="pill" data-bs-target="#tab-me-bram" type="button" role="tab">
        <i class="bi bi-shield-exclamation"></i>
        <span>2. BRAM &amp; KI-Analyse</span>
        <span class="badge <?= $textCompliance === 'red' ? 'bg-danger' : ($textCompliance === 'yellow' ? 'bg-warning text-dark' : 'bg-success') ?>">
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
      <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-purchases"
              data-bs-toggle="pill" data-bs-target="#tab-me-purchases" type="button" role="tab">
        <i class="bi bi-cart-check-fill"></i>
        <span>4. Käufe &amp; Zugriffe</span>
        <span class="badge bg-primary"><?= $buysCount ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-btn-me-tech"
              data-bs-toggle="pill" data-bs-target="#tab-me-tech" type="button" role="tab">
        <i class="bi bi-cpu"></i>
        <span>5. Technische Details</span>
      </button>
    </li>
  </ul>

  <div class="tab-content" id="movieEditTabContent">

    <!-- ======================================================================
         TAB 1: BEARBEITEN (Haupt-Cockpit)
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
                $vTerm = (string)($v['term'] ?? '');
                $vTime = isset($v['timestamp_sec']) && $v['timestamp_sec'] !== null ? (float)$v['timestamp_sec'] : null;
            ?>
              <?php if ($vTime !== null): ?>
                <button type="button" class="btn btn-xs btn-danger py-0 px-2" style="font-size: 0.75rem;"
                        onclick="App.seekMoviePlayerTo(<?= $vTime ?>)">
                  <i class="bi bi-play-fill"></i> „<?= View::e($vTerm) ?>“ (<?= gmdate('i:s', (int)$vTime) ?>)
                </button>
              <?php else: ?>
                <span class="badge bg-danger">„<?= View::e($vTerm) ?>“ (<?= View::e((string)($v['source'] ?? 'Text')) ?>)</span>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-outline-dark py-0.5 px-2 ms-1"
                  onclick="document.getElementById('tab-btn-me-bram').click()">
            Details öffnen <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </div>

      <form id="movie-online-save-form" onsubmit="App.submitMovieOnlineSave(event)">
        <input type="hidden" name="online_id" value="<?= $onlineId ?>">
        <input type="hidden" name="movie_id" value="<?= $movieId ?>">
        <input type="hidden" name="file_id" value="<?= View::e($fileId) ?>">
        <input type="hidden" name="fsk16" id="mc-input-fsk16" value="<?= (int)$movie['preview_image_fsk16'] ?>">
        <input type="hidden" name="fsk18" id="mc-input-fsk18" value="<?= (int)$movie['preview_image_fsk18'] ?>">

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
                  <span class="badge-kkc badge-kkc-neutral font-monospace"><?= View::e((string)$movie['resolution']) ?> (<?= View::e((string)$movie['detected_quality']) ?>)</span>
                  <span class="badge-kkc badge-kkc-neutral font-monospace"><?= View::e((string)$movie['playtime_formatted']) ?></span>
                  <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" onclick="App.openMovieFileMetaModal(<?= $movieId ?>)">
                    <i class="bi bi-code-square me-1"></i> getID3 Infos
                  </button>
                </div>
              </div>
              <div class="card-kkc-body p-3">
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

                <!-- Datei- & Merchant-Kurzinfos -->
                <div class="row g-2 mt-2 pt-2 border-top small text-muted" style="border-color: var(--kkc-border) !important;">
                  <div class="col-sm-6">
                    <div><strong>Merchant:</strong> <a href="#/merchant?id=<?= $merchantId ?>" class="ec-text-primary text-decoration-none"><?= View::e((string)$movie['merchant_username']) ?> (MID #<?= $merchantId ?>)</a></div>
                    <div><strong>File-ID:</strong> <span class="font-monospace"><?= View::e($fileId) ?></span></div>
                  </div>
                  <div class="col-sm-6">
                    <div><strong>Dateigröße:</strong> <span class="font-monospace"><?= View::e((string)$movie['file_size_text']) ?></span></div>
                    <div><strong>Käufe:</strong> <a href="javascript:void(0)" onclick="document.getElementById('tab-btn-me-purchases').click()" class="ec-text-primary fw-bold text-decoration-none"><?= $buysCount ?>x gekauft</a></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 2. Mitwirkende Personen & 18+ ID-Shot-Prüfung (CoActorManager) -->
            <?php
              $perfMode    = (string)($performersData['performer_mode'] ?? 'solo_owner');
              $perfPersons = is_array($performersData['persons'] ?? null) ? $performersData['persons'] : [];
            ?>
            <div class="card-kkc mb-4">
              <div class="card-kkc-header flex-wrap gap-2">
                <div class="card-title">
                  <i class="bi bi-person-badge-fill ec-text-primary"></i>
                  <span>Mitwirkende Personen &amp; ID-Shot-Prüfung (18+ / Model Release)</span>
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
                        <span class="badge-kkc <?= $pBadgeCls ?>" id="mc-coactor-status-<?= $pId ?>">
                          <?= View::e((string)($p['status_label'] ?? $pStatus)) ?>
                        </span>
                      </div>

                      <?php if ($hasDocs): ?>
                      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
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

                      <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                        <button type="button" class="btn btn-sm btn-success py-1 px-2"
                                onclick="App.reviewMovieCoActor(<?= $movieId ?>, <?= $merchantId ?>, <?= $pId ?>, 'verify')">
                          <i class="bi bi-check-lg me-1"></i> Bestätigen
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2"
                                onclick="App.reviewMovieCoActor(<?= $movieId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reject')">
                          <i class="bi bi-x-lg me-1"></i> Ablehnen
                        </button>
                      </div>
                      <?php else: ?>
                      <div class="alert alert-secondary py-2 px-3 mb-0 small d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill ec-text-primary flex-shrink-0"></i>
                        <span><strong>Noch kein ID-Shot hochgeladen:</strong> Übergangsphase aktiv – Film bleibt unabhängig davon regulär online/bearbeitbar.</span>
                      </div>
                      <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- 3. Szenen-Vorschaubilder (FSK16 & FSK18 Auswahl 1..12) -->
            <div class="card-kkc mb-4">
              <div class="card-kkc-header flex-wrap gap-2">
                <div class="card-title">
                  <i class="bi bi-images ec-text-primary"></i>
                  <span>Vorschaubild auswählen (FSK16 &amp; FSK18)</span>
                </div>
                <div class="d-flex align-items-center gap-2 small">
                  <span class="badge bg-info text-dark" id="mc-badge-selected-fsk16">FSK16: Bild #<?= (int)$movie['preview_image_fsk16'] ?></span>
                  <span class="badge bg-danger text-white" id="mc-badge-selected-fsk18">FSK18: Bild #<?= (int)$movie['preview_image_fsk18'] ?></span>
                </div>
              </div>
              <div class="card-kkc-body">
                <div class="row g-2">
                  <?php for ($i = 1; $i <= 10; $i++):
                      $isFsk16 = ((int)$movie['preview_image_fsk16'] === $i);
                      $isFsk18 = ((int)$movie['preview_image_fsk18'] === $i);
                  ?>
                  <div class="col-md-4 col-6">
                    <div class="p-2 rounded border h-100 mc-thumb-card <?= ($isFsk16 || $isFsk18) ? 'border-primary' : '' ?>"
                         id="mc-thumb-card-<?= $i ?>" style="background: var(--kkc-surface-hover);">
                      <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                        <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=<?= $i ?>&amp;w=320"
                             alt="Szene #<?= $i ?>" loading="lazy"
                             class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                             onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=<?= $i ?>', 'Szene #<?= $i ?> – Film #<?= $onlineId ?>')">
                        <span class="position-absolute top-0 start-0 m-1 px-1.5 py-0.5 rounded bg-dark bg-opacity-75 text-white font-monospace" style="font-size: 0.7rem;">#<?= $i ?></span>
                      </div>
                      <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm flex-fill py-1 mc-btn-fsk16 <?= $isFsk16 ? 'btn-info text-dark fw-bold' : 'btn-outline-kkc' ?>"
                                data-thumb-num="<?= $i ?>" onclick="App.selectMovieThumb(16, <?= $i ?>)">FSK16</button>
                        <button type="button" class="btn btn-sm flex-fill py-1 mc-btn-fsk18 <?= $isFsk18 ? 'btn-danger text-white fw-bold' : 'btn-outline-kkc' ?>"
                                data-thumb-num="<?= $i ?>" onclick="App.selectMovieThumb(18, <?= $i ?>)">FSK18</button>
                      </div>
                    </div>
                  </div>
                  <?php endfor; ?>

                  <!-- Individuelles FSK16 (#11) & FSK18 (#12) -->
                  <div class="col-md-6 col-6">
                    <?php $isCustom16 = ((int)$movie['preview_image_fsk16'] === 11); ?>
                    <div class="p-2 rounded border h-100 mc-thumb-card <?= $isCustom16 ? 'border-primary' : '' ?>" id="mc-thumb-card-11">
                      <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                        <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=11&amp;w=320"
                             alt="Individuelles FSK16 (#11)" class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                             onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=11', 'Individuelles FSK16 (#11)')">
                        <span class="position-absolute top-0 start-0 m-1 px-2 py-0.5 rounded bg-info text-dark fw-bold" style="font-size: 0.7rem;">Individuelles FSK16 (#11)</span>
                      </div>
                      <button type="button" class="btn btn-sm w-100 py-1 mc-btn-fsk16 <?= $isCustom16 ? 'btn-info text-dark fw-bold' : 'btn-outline-kkc' ?>"
                              data-thumb-num="11" onclick="App.selectMovieThumb(16, 11)">Als FSK16 wählen (#11)</button>
                    </div>
                  </div>

                  <div class="col-md-6 col-6">
                    <?php $isCustom18 = ((int)$movie['preview_image_fsk18'] === 12); ?>
                    <div class="p-2 rounded border h-100 mc-thumb-card <?= $isCustom18 ? 'border-primary' : '' ?>" id="mc-thumb-card-12">
                      <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                        <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=12&amp;w=320"
                             alt="Individuelles FSK18 (#12)" class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                             onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=12', 'Individuelles FSK18 (#12)')">
                        <span class="position-absolute top-0 start-0 m-1 px-2 py-0.5 rounded bg-danger text-white fw-bold" style="font-size: 0.7rem;">Individuelles FSK18 (#12)</span>
                      </div>
                      <button type="button" class="btn btn-sm w-100 py-1 mc-btn-fsk18 <?= $isCustom18 ? 'btn-danger text-white fw-bold' : 'btn-outline-kkc' ?>"
                              data-thumb-num="12" onclick="App.selectMovieThumb(18, 12)">Als FSK18 wählen (#12)</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- ================================================================
               RECHTE SPALTE: METADATEN, STATUS, KATEGORIEN & HISTORIE
               ================================================================ -->
          <div class="col-xl-6">

            <!-- 1. Film-Status & Freischaltungs-/Bearbeitungs-Historie -->
            <div class="card-kkc mb-4">
              <div class="card-kkc-header">
                <div class="card-title">
                  <i class="bi bi-toggle-on ec-text-primary"></i>
                  <span>Film-Status &amp; Bearbeitungs-Protokoll</span>
                </div>
                <span class="badge-kkc badge-kkc-neutral">Online-Steuerung</span>
              </div>
              <div class="card-kkc-body">
                <div class="d-flex flex-wrap gap-3 mb-2">
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                    <input type="radio" name="movie_status" value="active" class="form-check-input mt-0"
                           <?= $movie['status'] === 'active' ? 'checked' : '' ?>
                           onchange="document.getElementById('me-delete-warning').classList.add('d-none')">
                    <span class="fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i>Aktiv</span>
                  </label>
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                    <input type="radio" name="movie_status" value="blocked" class="form-check-input mt-0"
                           <?= $movie['status'] === 'blocked' ? 'checked' : '' ?>
                           onchange="document.getElementById('me-delete-warning').classList.add('d-none')">
                    <span class="fw-bold text-warning"><i class="bi bi-pause-circle-fill me-1"></i>Gesperrt</span>
                  </label>
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                    <input type="radio" name="movie_status" value="deleted" class="form-check-input mt-0"
                           <?= $movie['status'] === 'deleted' ? 'checked' : '' ?>
                           onchange="document.getElementById('me-delete-warning').classList.remove('d-none')">
                    <span class="fw-bold text-danger"><i class="bi bi-trash3-fill me-1"></i>Gelöscht</span>
                  </label>
                </div>

                <div id="me-delete-warning" class="alert alert-danger py-2 px-3 small mb-2 <?= $movie['status'] === 'deleted' ? '' : 'd-none' ?>">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i>
                  <strong>ACHTUNG:</strong> Sofern der Film noch nicht gekauft wurde, wird er unwiderruflich gelöscht. Sollte er bereits gekauft worden sein, wird er nur nicht mehr öffentlich angezeigt und steht bestehenden Käufern weiterhin zur Verfügung.
                </div>

                <div class="d-flex flex-wrap gap-3 small text-muted pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <?php if ($movie['released_from_name'] !== '-'): ?>
                  <div>
                    <i class="bi bi-shield-check text-success me-1"></i>
                    Freigeschaltet am <strong><?= View::e($movie['released_datetime'] !== '' && $movie['released_datetime'] !== '0000-00-00 00:00:00' ? date('d.m.Y \u\m H:i', strtotime((string)$movie['released_datetime'])) : '-') ?></strong>
                    von <strong><?= View::e((string)$movie['released_from_name']) ?></strong>
                  </div>
                  <?php endif; ?>
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

            <!-- 2. Angaben zum Film, Preise & SEO -->
            <div class="card-kkc mb-4">
              <div class="card-kkc-header">
                <div class="card-title">
                  <i class="bi bi-sliders ec-text-primary"></i>
                  <span>Angaben zum Film, Preise &amp; SEO</span>
                </div>
              </div>
              <div class="card-kkc-body">
                <div class="row g-3">

                  <!-- Hauptdarsteller -->
                  <div class="col-md-6">
                    <label class="form-label small fw-bold mb-1">Hauptdarsteller im Film *</label>
                    <div class="input-group input-group-sm">
                      <select name="actor_id" class="form-select" required>
                        <option value="0">-- Bitte wählen --</option>
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
                    <label class="form-label small fw-bold mb-1">Haupt-Filmsprache *</label>
                    <select name="movie_language" class="form-select form-select-sm">
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
                      <label class="form-label small fw-bold mb-0">Filmtitel * <span class="text-muted fw-normal">(empf. max. 65 Zeichen)</span></label>
                      <small class="text-muted font-monospace" id="mc-title-counter"><?= mb_strlen((string)$movie['title']) ?> Zeichen</small>
                    </div>
                    <input type="text" name="title" id="mc-input-title" class="form-control form-control-sm"
                           value="<?= View::e((string)$movie['title']) ?>" required
                           oninput="App.updateMovieFormCounters()">
                  </div>

                  <!-- Beschreibung -->
                  <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <label class="form-label small fw-bold mb-0">Filmbeschreibung *</label>
                      <small class="text-muted font-monospace" id="mc-desc-counter"><?= mb_strlen((string)$movie['description']) ?> Zeichen</small>
                    </div>
                    <textarea name="description" id="mc-input-description" class="form-control form-control-sm" rows="6" required
                              oninput="App.updateMovieFormCounters()"><?= View::e((string)$movie['description']) ?></textarea>
                  </div>

                  <!-- Online ab & Streaming-Preis -->
                  <div class="col-md-5">
                    <label class="form-label small fw-bold mb-1">Frühestens online ab *</label>
                    <input type="datetime-local" name="online_at" class="form-control form-control-sm"
                           value="<?= View::e((string)$movie['online_at_input']) ?>" required>
                  </div>

                  <div class="col-md-7">
                    <label class="form-label small fw-bold mb-1">Streaming-Preis (Cent / Coins je Sekunde) *</label>
                    <div class="input-group input-group-sm">
                      <select name="amount_second" id="mc-input-amount-second" class="form-select"
                              data-playtime="<?= $playtime ?>" onchange="App.updateMovieStreamingPrice()">
                        <?php
                          $stepVal = 0.0;
                          while ($stepVal <= 100.001):
                              $valStr = number_format($stepVal, 1, '.', '');
                              $sel    = ($valStr === (string)$movie['amount_second']) ? 'selected' : '';
                              $lbl    = $valStr . ' Cent/Sek.';
                              if ($valStr === '0.0') {
                                  $lbl = 'kostenlos (0.0)';
                              } elseif ($valStr === '0.8') {
                                  $lbl = '0.8 Cent/Sek. (Empfohlen)';
                              }
                        ?>
                        <option value="<?= $valStr ?>" <?= $sel ?>><?= View::e($lbl) ?></option>
                        <?php
                              $stepVal = round($stepVal + 0.1, 1);
                          endwhile;
                        ?>
                      </select>
                      <span class="input-group-text fw-bold ec-text-primary" id="mc-live-total-coins">
                        = <?= (int)$movie['total_coins'] ?> Coins gesamt
                      </span>
                    </div>
                  </div>

                  <!-- Download-Option & Download-Aufschlag -->
                  <div class="col-md-5">
                    <label class="form-label small fw-bold mb-1">Zum Download anbieten?</label>
                    <select name="as_download" id="mc-input-as-download" class="form-select form-select-sm"
                            onchange="App.toggleMovieDownloadPrice()">
                      <option value="1" <?= !empty($movie['as_download']) ? 'selected' : '' ?>>Ja (Empfohlen)</option>
                      <option value="0" <?= empty($movie['as_download']) ? 'selected' : '' ?>>Nein (Nur Stream)</option>
                    </select>
                  </div>

                  <div class="col-md-7" id="mc-download-price-wrap" style="<?= empty($movie['as_download']) ? 'opacity: 0.5;' : '' ?>">
                    <label class="form-label small fw-bold mb-1">Download-Aufschlag (%)</label>
                    <select name="amount_download" id="mc-input-amount-download" class="form-select form-select-sm">
                      <?php for ($i = 0; $i <= 150; $i++): ?>
                      <option value="<?= $i ?>" <?= (int)$movie['amount_download'] === $i ? 'selected' : '' ?>>
                        +<?= $i ?>%<?= $i === 0 ? ' (genau so teuer wie Stream)' : ($i === 10 ? ' (Empfohlen)' : '') ?>
                      </option>
                      <?php endfor; ?>
                    </select>
                  </div>

                  <!-- Qualität, Trailer & Sichtbarkeit -->
                  <div class="col-md-4">
                    <label class="form-label small fw-bold mb-1">Qualität</label>
                    <select name="quality" class="form-select form-select-sm">
                      <?php foreach ($qualities as $qKey => $qLabel): ?>
                      <option value="<?= View::e($qKey) ?>" <?= $movie['quality'] === $qKey ? 'selected' : '' ?>><?= View::e($qLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label small fw-bold mb-1">Ist der Film ein Trailer?</label>
                    <select name="preview" class="form-select form-select-sm">
                      <option value="0" <?= (int)$movie['preview'] === 0 ? 'selected' : '' ?>>Nein</option>
                      <option value="1" <?= (int)$movie['preview'] === 1 ? 'selected' : '' ?>>Ja</option>
                    </select>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label small fw-bold mb-1">Sichtbar auf</label>
                    <select name="visible_for_website" id="mc-visible-website" class="form-select form-select-sm">
                      <option value="public" id="mc-visible-public-opt" <?= $movie['visible_for_website'] === 'public' ? 'selected' : '' ?>>
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
                      <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" style="font-size: 0.72rem;"
                              onclick="App.autofillMovieSeoFields()">
                        <i class="bi bi-magic me-1"></i> Aus Titel/Beschreibung füllen
                      </button>
                    </div>

                    <div class="row g-2">
                      <div class="col-md-6">
                        <div class="d-flex justify-content-between small mb-1">
                          <label class="text-muted">Meta-Title (max. 65)</label>
                          <span class="font-monospace text-muted" id="mc-meta-title-counter"><?= mb_strlen((string)$movie['meta_title']) ?>/65</span>
                        </div>
                        <input type="text" name="meta_title" id="mc-input-meta-title" maxlength="65" class="form-control form-control-sm"
                               value="<?= View::e((string)$movie['meta_title']) ?>" oninput="App.updateMovieFormCounters()">
                      </div>

                      <div class="col-md-6">
                        <div class="d-flex justify-content-between small mb-1">
                          <label class="text-muted">SEO-URL (URL-Name)</label>
                          <span class="text-danger" style="font-size: 0.68rem;">Achtung bei Änderung: Ranking-Verlust!</span>
                        </div>
                        <input type="text" name="seo_url" id="mc-input-seo-url" class="form-control form-control-sm font-monospace"
                               value="<?= View::e((string)$movie['seo_url']) ?>">
                      </div>

                      <div class="col-12">
                        <div class="d-flex justify-content-between small mb-1">
                          <label class="text-muted">Meta-Description (max. 156)</label>
                          <span class="font-monospace text-muted" id="mc-meta-desc-counter"><?= mb_strlen((string)$movie['meta_description']) ?>/156</span>
                        </div>
                        <textarea name="meta_description" id="mc-input-meta-desc" maxlength="156" rows="2" class="form-control form-control-sm"
                                  oninput="App.updateMovieFormCounters()"><?= View::e((string)$movie['meta_description']) ?></textarea>
                      </div>
                    </div>
                  </div>

                  <!-- Admin-Infos -->
                  <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                    <label class="form-label small fw-bold mb-1">Interne Admin-Infos zum Film (`admin_infos`)</label>
                    <textarea name="admin_infos" class="form-control form-control-sm" rows="2"
                              placeholder="Interne Notizen zum Film..."><?= View::e((string)$movie['admin_infos']) ?></textarea>
                  </div>

                </div>
              </div>
            </div>

            <!-- 3. Kategorisierung -->
            <div class="card-kkc mb-4">
              <div class="card-kkc-header flex-wrap gap-2">
                <div class="card-title">
                  <i class="bi bi-tags-fill ec-text-primary"></i>
                  <span>Film kategorisieren</span>
                </div>
                <span class="badge-kkc badge-kkc-info" id="mc-selected-cats-badge">
                  <?= count($movie['selected_slaves']) ?> Unterkategorien gewählt
                </span>
              </div>
              <div class="card-kkc-body">
                <div class="mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
                  <label class="form-label small fw-bold d-block mb-2">Hauptkategorie *</label>
                  <div class="d-flex gap-3">
                    <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                      <input type="radio" name="category_master" value="porn" class="form-check-input mt-0"
                             <?= $movie['category_master'] === 'porn' ? 'checked' : '' ?>>
                      <span class="fw-bold">Porno</span>
                    </label>
                    <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                      <input type="radio" name="category_master" value="fetish" class="form-check-input mt-0"
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
                               style="cursor: pointer; padding: 5px 8px;" title="<?= View::e($deText) ?>">
                          <input type="checkbox" name="categories[]" value="<?= View::e($idName) ?>"
                                 class="form-check-input mt-0 mc-cat-checkbox"
                                 <?= $isSel ? 'checked' : '' ?>
                                 onchange="App.onMovieCategoryChange(this)">
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

                <div class="mt-4 pt-3 border-top d-flex justify-content-end" style="border-color: var(--kkc-border) !important;">
                  <button type="submit" id="me-submit-save-btn" class="btn btn-kkc px-4 py-2 fw-bold">
                    <i class="bi bi-floppy-fill me-1"></i> Änderungen speichern
                  </button>
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
      </form>
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
          <button type="button" id="mc-recheck-bram-btn" class="btn btn-sm btn-kkc"
                  onclick="App.runMovieAiAction(<?= $movieId ?>, 'recheck_bram')">
            <i class="bi bi-arrow-repeat me-1"></i> BRAM-Textprüfung mit aktuellen Formularwerten neu ausführen
          </button>
        </div>
        <div class="card-kkc-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
                <div class="text-muted small mb-1">Compliance-Status</div>
                <div class="h4 fw-bold mb-1 <?= $textCompliance === 'red' ? 'text-danger' : ($textCompliance === 'yellow' ? 'text-warning' : 'text-success') ?>">
                  <?= $textCompliance === 'red' ? 'Kritischer Verstoß (Rot)' : ($textCompliance === 'yellow' ? 'Warnung / Prüfen (Gelb)' : 'Unbedenklich (Grün)') ?>
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
                      $sev   = (string)($v['severity'] ?? 'warning');
                      $vTime = isset($v['timestamp_sec']) && $v['timestamp_sec'] !== null ? (float)$v['timestamp_sec'] : null;
                  ?>
                  <tr>
                    <td class="fw-bold text-danger">„<?= View::e((string)($v['term'] ?? '')) ?>“</td>
                    <td><span class="badge-kkc badge-kkc-neutral"><?= View::e((string)($v['category_label'] ?? $v['category'] ?? '')) ?></span></td>
                    <td>
                      <span class="badge-kkc <?= $sev === 'critical' ? 'badge-kkc-danger' : 'badge-kkc-warning' ?>">
                        <?= $sev === 'critical' ? 'Kritisch' : 'Warnung' ?>
                      </span>
                    </td>
                    <td><?= View::e((string)($v['source'] ?? 'Text')) ?></td>
                    <td>
                      <?php if ($vTime !== null): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 font-monospace"
                                onclick="document.getElementById('tab-btn-me-edit').click(); App.seekMoviePlayer(<?= $vTime ?>)">
                          <i class="bi bi-play-fill"></i> <?= gmdate('i:s', (int)$vTime) ?>
                        </button>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td class="small text-muted font-monospace"><?= View::e((string)($v['snippet'] ?? '')) ?></td>
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
          <button type="button" id="mc-run-whisper-btn" class="btn btn-sm btn-kkc"
                  onclick="App.runMovieAiAction(<?= $movieId ?>, 'generate_subtitles')">
            <i class="bi bi-cpu me-1"></i> Audio-Analyse (Whisper) starten &amp; BRAM neu prüfen
          </button>
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
                <div class="fw-bold font-monospace small"><?= View::e((string)$aiData['subtitle_filename']) ?></div>
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
              <label class="form-label small fw-bold">Volltext-Transkript (editierbar für BRAM-Nachprüfung)</label>
              <textarea id="mc-transcript-textarea" class="form-control form-control-sm font-monospace mb-3" rows="7"><?= View::e((string)$aiData['transcript_text']) ?></textarea>

              <label class="form-label small fw-bold">WebVTT-Dateiinhalt (`.vtt`)</label>
              <textarea class="form-control form-control-sm font-monospace" rows="6" readonly><?= View::e((string)$aiData['vtt_content']) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================
         TAB 4: KÄUFE & ZUGRIFFE (movies_access)
         ====================================================================== -->
    <div class="tab-pane fade" id="tab-me-purchases" role="tabpanel">
      <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
          <div class="card-kkc h-100">
            <div class="card-kkc-body py-3">
              <div class="text-muted small">Käufe Gesamt</div>
              <div class="h4 fw-bold mb-0 ec-text-primary"><?= (int)$purchasesData['total_buys'] ?>x</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
          <div class="card-kkc h-100">
            <div class="card-kkc-body py-3">
              <div class="text-muted small">Gesamteinnahmen</div>
              <div class="h4 fw-bold mb-0 text-success"><?= number_format((float)$purchasesData['total_price'], 0, ',', '.') ?> Coins</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
          <div class="card-kkc h-100">
            <div class="card-kkc-body py-3">
              <div class="text-muted small">Provision Creator</div>
              <div class="h4 fw-bold mb-0 text-warning"><?= number_format((float)$purchasesData['total_commission'], 0, ',', '.') ?> Coins</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
          <div class="card-kkc h-100">
            <div class="card-kkc-body py-3">
              <div class="text-muted small">Stream / Download</div>
              <div class="h4 fw-bold mb-0"><?= (int)$purchasesData['count_streaming'] ?> / <?= (int)$purchasesData['count_download'] ?></div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
          <div class="card-kkc h-100">
            <div class="card-kkc-body py-3">
              <div class="text-muted small">Zuletzt gekauft am</div>
              <div class="fw-bold small font-monospace mt-1"><?= View::e((string)$purchasesData['last_buy']) ?></div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
          <div class="card-kkc h-100">
            <div class="card-kkc-body py-3">
              <div class="text-muted small">Zuletzt angeschaut</div>
              <div class="fw-bold small font-monospace mt-1"><?= View::e((string)$purchasesData['last_view']) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card-kkc">
        <div class="card-kkc-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="card-title">
            <i class="bi bi-receipt-cutoff ec-text-primary"></i>
            <span>Einzelkäufe &amp; Zugriffe für „<?= View::e((string)$movie['title']) ?>“</span>
          </div>
          <div class="input-group input-group-sm" style="max-width: 300px;">
            <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Benutzer, Shop oder Datum suchen..."
                   oninput="App.filterMoviePurchasesTable(this.value)">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-kkc align-middle mb-0" id="me-purchases-table">
            <thead>
              <tr>
                <th style="width: 60px;">#</th>
                <th>Kaufdatum</th>
                <th>Benutzer (Cloud-User)</th>
                <th>Shop / Webseite</th>
                <th>Typ</th>
                <th>Preis</th>
                <th>Provision Creator</th>
                <th>Letzter Zugriff</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($purchasesData['rows'])): ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  Dieser Film wurde bisher noch nicht gekauft.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($purchasesData['rows'] as $bRow):
                    $searchRow = strtolower($bRow['username'] . ' ' . $bRow['shop_domain'] . ' ' . $bRow['buy_time'] . ' ' . $bRow['buy_type']);
                ?>
                <tr class="me-purchase-row" data-search="<?= View::e($searchRow) ?>">
                  <td class="font-monospace text-muted">#<?= (int)$bRow['index'] ?></td>
                  <td class="font-monospace small"><?= View::e((string)$bRow['buy_time']) ?></td>
                  <td>
                    <a href="#/user?id=<?= (int)$bRow['user_id'] ?>" class="fw-semibold ec-text-primary text-decoration-none">
                      <i class="bi bi-person me-1"></i><?= View::e((string)$bRow['username']) ?>
                    </a>
                  </td>
                  <td>
                    <a href="#/site?id=<?= View::e((string)$bRow['shop_domain']) ?>" class="text-decoration-none font-monospace small">
                      <i class="bi bi-globe2 me-1"></i><?= View::e((string)$bRow['shop_domain']) ?>
                    </a>
                  </td>
                  <td>
                    <span class="badge-kkc <?= $bRow['buy_as'] === 'download' ? 'badge-kkc-info' : 'badge-kkc-neutral' ?>">
                      <?= View::e((string)$bRow['buy_type']) ?>
                    </span>
                  </td>
                  <td class="fw-bold text-success"><?= number_format((float)$bRow['price_coins'], 0, ',', '.') ?> Coins</td>
                  <td class="small">
                    <strong><?= number_format((float)$bRow['commission_coins'], 0, ',', '.') ?> Coins</strong>
                    <span class="text-muted">(<?= number_format((float)$bRow['commission_percent'], 0) ?>%)</span>
                  </td>
                  <td class="font-monospace small text-muted"><?= View::e((string)$bRow['last_view']) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ======================================================================
         TAB 5: TECHNISCHE DETAILS
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
                <th>Online-ID (Tabelle `movies_online`):</th>
                <td class="font-monospace">#<?= $onlineId ?></td>
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
                <th>Freigeschaltet am:</th>
                <td class="font-monospace"><?= View::e((string)$movie['released_datetime'] ?: '-') ?> (von <?= View::e((string)$movie['released_from_name']) ?>)</td>
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
