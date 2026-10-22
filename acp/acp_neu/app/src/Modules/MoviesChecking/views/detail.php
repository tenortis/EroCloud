<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var array<string, mixed> $movie */
/** @var array<string, mixed> $onlineInfo */
/** @var array<int, array<string, mixed>> $merchantActors */
/** @var array<int, string> $merchantDomains */
/** @var array<string, array<int, array<string, mixed>>> $categoriesByType */
/** @var array<int, array<string, mixed>> $rejectionReasons */
/** @var array<int, array<string, mixed>> $rejectionHistory */
/** @var array<string, mixed> $performersData */
/** @var array<string, mixed> $aiData */
/** @var array<string, string> $languages */
/** @var string $employeeRule */
/** @var int $queueTotal */
/** @var int $queuePosition */
/** @var int $prevMovieId */
/** @var int $nextMovieId */

$movieId    = (int)$movie['id'];
$merchantId = (int)$movie['merchant_id'];
$actorId    = (int)$movie['actor_id'];
$fileId     = (string)$movie['file_id'];
$playtime   = (int)$movie['playtime_seconds'];

$bramComp   = (string)($aiData['text_compliance'] ?? 'pending');
$bramScore  = (int)($aiData['text_risk_score'] ?? 0);
$violations = is_array($aiData['violations'] ?? null) ? $aiData['violations'] : [];
$subStatus  = (int)($aiData['subtitle_status'] ?? 0);
$detLang    = strtoupper((string)($aiData['detected_language'] ?? ''));

$bramBannerCls = 'border-success bg-success bg-opacity-10';
$bramBadgeCls  = 'badge-kkc-success';
$bramLabel     = 'BRAM Unbedenklich (Grün)';
$bramIcon      = 'bi-shield-check';
if ($bramComp === 'violation') {
    $bramBannerCls = 'border-danger bg-danger bg-opacity-10';
    $bramBadgeCls  = 'badge-kkc-danger';
    $bramLabel     = 'BRAM Verstoß / Kritisch (Rot)';
    $bramIcon      = 'bi-exclamation-octagon-fill';
} elseif ($bramComp === 'warning') {
    $bramBannerCls = 'border-warning bg-warning bg-opacity-10';
    $bramBadgeCls  = 'badge-kkc-warning';
    $bramLabel     = 'BRAM Hinweis / Prüfen (Gelb)';
    $bramIcon      = 'bi-exclamation-triangle-fill';
}

$categoryGroupLabels = [
    'number_of_people' => ['label' => 'Anzahl der Personen', 'icon' => 'bi-people-fill'],
    'look_and_body'    => ['label' => 'Aussehen & Körper',   'icon' => 'bi-person-heart'],
    'fetish'           => ['label' => 'Fetisch & BDSM',      'icon' => 'bi-fire'],
    'porn'             => ['label' => 'Porno & Praktiken',   'icon' => 'bi-film'],
];
?>

<!-- ==========================================================================
     TOP HEADER & WARTESCHLANGEN-NAVIGATION
     ========================================================================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
      <a href="#/movies-checking" class="btn btn-sm btn-outline-kkc py-0 px-2">
        <i class="bi bi-arrow-left me-1"></i> Warteschlange
      </a>
      <span class="badge-kkc badge-kkc-info font-monospace">Film #<?= $movieId ?></span>
      <span class="badge-kkc badge-kkc-neutral font-monospace">File-ID: <?= View::e($fileId) ?></span>
      <?php if ($queuePosition > 0): ?>
      <span class="badge-kkc badge-kkc-neutral">
        Position <?= $queuePosition ?> von <?= $queueTotal ?> in Prüfung
      </span>
      <?php endif; ?>
    </div>
    <h1 class="h4 mb-0 text-break"><?= View::e((string)$movie['title'] ?: '(Ohne Filmtitel)') ?></h1>
  </div>

  <div class="d-flex flex-wrap align-items-center gap-2">
    <?php if ($prevMovieId > 0): ?>
    <a href="#/movie-checking?id=<?= $prevMovieId ?>" class="btn btn-sm btn-outline-kkc" title="Vorheriger Film (#<?= $prevMovieId ?>)">
      <i class="bi bi-chevron-left"></i> Vorheriger (#<?= $prevMovieId ?>)
    </a>
    <?php endif; ?>
    <?php if ($nextMovieId > 0): ?>
    <a href="#/movie-checking?id=<?= $nextMovieId ?>" class="btn btn-sm btn-outline-kkc" title="Nächster Film (#<?= $nextMovieId ?>)">
      Nächster (#<?= $nextMovieId ?>) <i class="bi bi-chevron-right"></i>
    </a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('mc-reject-card')?.scrollIntoView({behavior:'smooth'})">
      <i class="bi bi-x-circle me-1"></i> Zum Ablehnen
    </button>
    <button type="button" class="btn btn-sm btn-kkc" onclick="document.getElementById('mc-submit-approve-btn')?.scrollIntoView({behavior:'smooth'})">
      <i class="bi bi-check2-circle me-1"></i> Zur Freigabe
    </button>
  </div>
</div>

<!-- ==========================================================================
     NAVIGATION TABS (1. Film prüfen | 2. BRAM & KI-Analyse | 3. Audio & .vtt)
     ========================================================================== -->
<ul class="nav nav-pills gap-2 mb-3 p-2 rounded border" style="background: var(--kkc-card-bg); border-color: var(--kkc-border) !important;" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active d-flex align-items-center gap-2 fw-semibold" id="tab-btn-review"
            data-bs-toggle="pill" data-bs-target="#mc-tab-review" type="button" role="tab">
      <i class="bi bi-play-btn-fill"></i>
      <span>1. Film prüfen &amp; veröffentlichen</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 fw-semibold" id="tab-btn-bram"
            data-bs-toggle="pill" data-bs-target="#mc-tab-bram" type="button" role="tab">
      <i class="bi bi-shield-exclamation"></i>
      <span>2. BRAM &amp; KI-Analyse</span>
      <span class="badge rounded-pill <?= $bramComp === 'ok' ? 'bg-success' : ($bramComp === 'violation' ? 'bg-danger' : 'bg-warning text-dark') ?>" id="mc-tab-bram-badge">
        <?= $bramScore ?>/100
      </span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 fw-semibold" id="tab-btn-audio"
            data-bs-toggle="pill" data-bs-target="#mc-tab-audio" type="button" role="tab">
      <i class="bi bi-mic-fill"></i>
      <span>3. Audio &amp; Transkript (.vtt)</span>
      <span class="badge rounded-pill <?= $subStatus === 2 ? 'bg-success' : 'bg-secondary' ?>" id="mc-tab-audio-badge">
        <?= $subStatus === 2 ? ($detLang ?: 'VTT') : ($subStatus === 3 ? 'Stumm' : 'Offen') ?>
      </span>
    </button>
  </li>
</ul>

<div class="tab-content">
  <!-- ========================================================================
       TAB 1: FILM PRÜFEN & VERÖFFENTLICHEN
       ======================================================================== -->
  <div class="tab-pane fade show active" id="mc-tab-review" role="tabpanel">

    <!-- BRAM-Schnellstatus-Banner -->
    <div class="p-3 rounded border mb-3 <?= $bramBannerCls ?>" id="mc-top-bram-banner">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="badge-kkc <?= $bramBadgeCls ?> fs-6 py-1 px-2">
            <i class="bi <?= $bramIcon ?> me-1"></i> <?= View::e($bramLabel) ?>
          </span>
          <span class="badge-kkc badge-kkc-neutral font-monospace">Risiko-Score: <?= $bramScore ?> / 100</span>
          <span class="small fw-semibold"><?= View::e((string)($aiData['text_summary'] ?? '')) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-kkc bg-body" onclick="App.runMovieAiAction(<?= $movieId ?>, 'run_text_check')">
            <i class="bi bi-arrow-repeat me-1"></i> Text-Check mit Formularwerten aktualisieren
          </button>
          <button type="button" class="btn btn-sm btn-outline-kkc bg-body" onclick="document.getElementById('tab-btn-bram')?.click()">
            Details (<?= count($violations) ?>) <i class="bi bi-arrow-right ms-1"></i>
          </button>
        </div>
      </div>

      <?php if (!empty($violations)): ?>
      <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top" style="border-color: rgba(0,0,0,0.1) !important;">
        <?php foreach ($violations as $v):
            $isCrit = (($v['severity'] ?? '') === 'critical');
            $tsStr  = (string)($v['timestamp'] ?? '-');
            $secJump = 0;
            if (preg_match('/^(\d+):(\d+)$/', $tsStr, $mTs)) {
                $secJump = ((int)$mTs[1] * 60) + (int)$mTs[2];
            }
        ?>
        <span class="badge <?= $isCrit ? 'bg-danger text-white' : 'bg-warning text-dark' ?> d-inline-flex align-items-center gap-1 py-1 px-2"
              style="font-size: 0.78rem; <?= $secJump > 0 ? 'cursor:pointer;' : '' ?>"
              <?php if ($secJump > 0): ?>onclick="App.seekMoviePlayer(<?= $secJump ?>)" title="Klicken, um im Player zu <?= View::e($tsStr) ?> zu springen"<?php endif; ?>>
          <i class="bi <?= $isCrit ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
          <strong><?= View::e((string)($v['category'] ?? 'Regel')) ?>:</strong>
          <span>„<?= View::e((string)($v['matched_term'] ?? '')) ?>“</span>
          <span class="opacity-75">(<?= View::e((string)($v['source'] ?? 'Text')) ?><?= $tsStr !== '-' && $tsStr !== '' ? ' @ ' . View::e($tsStr) : '' ?>)</span>
        </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Warnhinweis falls kein gültiger Darsteller zugeordnet ist -->
    <?php if (empty($movie['actor_exists'])): ?>
    <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-person-x-fill fs-4"></i>
        <div>
          <strong>Kein gültiges Darsteller-Profil zugeordnet!</strong>
          <div class="small">
            Diesem Film ist aktuell kein existierendes Profil des Merchants #<?= $merchantId ?> zugewiesen. Bitte wähle rechts im Formular ein Profil aus oder lehne den Film ab.
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-danger" onclick="App.prefillMovieRejection('no_actor_selected')">
        <i class="bi bi-x-octagon me-1"></i> Mit Grund „Kein Darsteller gewählt“ ablehnen
      </button>
    </div>
    <?php endif; ?>

    <!-- Status-Hinweis falls der Film bereits in movies_online existiert -->
    <?php if (!empty($onlineInfo['is_in_movies_online'])): ?>
      <?php if (($onlineInfo['online_status'] ?? '') === 'active'): ?>
      <div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div>
          <strong>Hinweis:</strong> Dieser Film ist bereits öffentlich in der EroCloud (`movies_online`). Es wurden nachträgliche Änderungen zur erneuten Prüfung eingereicht.
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-warning d-flex align-items-center gap-3 mb-3">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div>
          <strong>Dieser Film war bereits veröffentlicht und ist aktuell gesperrt oder zur Löschung vorgemerkt!</strong>
          <div class="small mt-1">
            Bereits gekaufte Zugriffe bleiben für Käufer weiterhin abrufbar.
            Bisherige Käufe: <strong><?= (int)$onlineInfo['purchase_count'] ?></strong>
            <?php if (!empty($onlineInfo['last_buy_datetime'])): ?>
              · Letzter Kauf: <strong><?= View::e((string)$onlineInfo['last_buy_datetime']) ?></strong>
              · Letzter Abruf: <strong><?= View::e((string)$onlineInfo['last_view_datetime']) ?></strong>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <form id="mc-approve-form" onsubmit="App.submitMovieApprove(event)">
      <input type="hidden" name="movie_id" value="<?= $movieId ?>">
      <input type="hidden" name="fsk16" id="mc-input-fsk16" value="<?= (int)$movie['preview_image_fsk16'] ?>">
      <input type="hidden" name="fsk18" id="mc-input-fsk18" value="<?= (int)$movie['preview_image_fsk18'] ?>">

      <div class="row g-4">
        <!-- ==================================================================
             LINKE SPALTE: VIDEOPLAYER, TECHNIK, 18+ MITWIRKENDE & VORSCHAUBILDER
             ================================================================== -->
        <div class="col-xl-6">

          <!-- 1. Video-Player & Schnellsteuerung -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header">
              <div class="card-title">
                <i class="bi bi-play-circle-fill ec-text-primary"></i>
                <span>Video-Vorschau &amp; Sichtung</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge-kkc badge-kkc-neutral font-monospace"><?= View::e((string)$movie['resolution']) ?></span>
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

              <!-- Schnell-Steuerung für effizientes Prüfen (Spulen & Geschwindigkeit) -->
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-1">
                <div class="btn-group btn-group-sm" role="group" aria-label="Zeitsprung">
                  <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(-10)" title="10 Sekunden zurück">
                    <i class="bi bi-skip-backward"></i> -10s
                  </button>
                  <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(10)" title="10 Sekunden vor">
                    +10s <i class="bi bi-skip-forward"></i>
                  </button>
                  <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(30)" title="30 Sekunden vor">
                    +30s
                  </button>
                  <button type="button" class="btn btn-outline-kkc" onclick="App.skipMoviePlayer(60)" title="60 Sekunden vor">
                    +60s
                  </button>
                </div>

                <div class="d-flex align-items-center gap-1">
                  <span class="small text-muted me-1">Speed:</span>
                  <div class="btn-group btn-group-sm" role="group" aria-label="Abspielgeschwindigkeit">
                    <button type="button" class="btn btn-outline-kkc mc-speed-btn active" onclick="App.setMoviePlayerSpeed(1, this)">1x</button>
                    <button type="button" class="btn btn-outline-kkc mc-speed-btn" onclick="App.setMoviePlayerSpeed(1.5, this)">1.5x</button>
                    <button type="button" class="btn btn-outline-kkc mc-speed-btn" onclick="App.setMoviePlayerSpeed(2, this)">2x</button>
                  </div>
                </div>
              </div>

              <!-- Kompakte Datei- & Merchant-Infos -->
              <div class="row g-2 mt-2 pt-2 border-top small text-muted" style="border-color: var(--kkc-border) !important;">
                <div class="col-sm-6">
                  <div><strong>Merchant:</strong> <a href="#/merchant?id=<?= $merchantId ?>" class="ec-text-primary text-decoration-none"><?= View::e((string)$movie['merchant_username']) ?> (MID #<?= $merchantId ?>)</a></div>
                  <div><strong>Dateiname:</strong> <span class="font-monospace"><?= View::e((string)$movie['filename']) ?></span></div>
                </div>
                <div class="col-sm-6">
                  <div><strong>Storage:</strong> <span class="font-monospace"><?= View::e((string)$movie['storage_location']) ?></span></div>
                  <div><strong>Eingereicht:</strong> <?= View::e((string)$movie['create_datetime']) ?></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. Mitwirkende Personen & 18+ ID-Shot-Prüfung (CoActorManager) -->
          <?php
            $perfMode    = (string)($performersData['performer_mode'] ?? 'solo_owner');
            $perfPersons = is_array($performersData['persons'] ?? null) ? $performersData['persons'] : [];
          ?>
          <div class="card-kkc mb-4" id="mc-performers-card">
            <div class="card-kkc-header flex-wrap gap-2">
              <div class="card-title">
                <i class="bi bi-person-badge-fill ec-text-primary"></i>
                <span>Mitwirkende Personen &amp; ID-Shot-Prüfung (18+ / Model Release)</span>
              </div>
              <?php if ($perfMode === 'solo_owner'): ?>
                <span class="badge-kkc badge-kkc-info">
                  Solo: Nur Account-Inhaber (<?= View::e((string)$performersData['merchant_full_name']) ?>)
                </span>
              <?php else: ?>
                <span class="badge-kkc badge-kkc-warning">
                  Mehrere Personen (<?= count($perfPersons) ?> gemeldet)
                </span>
              <?php endif; ?>
            </div>
            <div class="card-kkc-body">
              <?php if (empty($perfPersons)): ?>
                <div class="alert alert-danger mb-0 py-2 small">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i> Keine mitwirkenden Personen für diesen Film hinterlegt.
                </div>
              <?php else: ?>
                <div class="d-flex flex-column gap-3">
                  <?php foreach ($perfPersons as $p):
                      $pId       = (int)$p['id'];
                      $isOwner   = !empty($p['is_owner']);
                      $pStatus   = (string)($p['checked_status'] ?? 'pending_qr');
                      $isReady   = !empty($p['is_ready_for_use']);
                      $isAiReady = !empty($p['ai_face_ready']);

                      $pBadgeCls = 'badge-kkc-warning';
                      if ($pStatus === 'verified') {
                          $pBadgeCls = 'badge-kkc-success';
                      } elseif ($pStatus === 'rejected') {
                          $pBadgeCls = 'badge-kkc-danger';
                      } elseif ($pStatus === 'under_review') {
                          $pBadgeCls = 'badge-kkc-info';
                      }
                  ?>
                  <div class="p-3 rounded border" style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;" id="mc-coactor-box-<?= $pId ?>">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                      <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge <?= $isOwner ? 'bg-primary' : 'bg-secondary' ?>">
                          <?= $isOwner ? 'ICH (ACCOUNT-INHABER)' : 'DREHPARTNER' ?>
                        </span>
                        <strong class="text-body"><?= View::e((string)($p['display_label'] ?? '')) ?></strong>
                        <?php if ($isAiReady): ?>
                          <span class="badge-kkc badge-kkc-info"><i class="bi bi-qr-code-scan me-1"></i>Live-QR (KI-Ready)</span>
                        <?php elseif (($p['verification_method'] ?? '') === 'manual_legacy'): ?>
                          <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-folder2-open me-1"></i>Alt-Dokumente</span>
                        <?php endif; ?>
                      </div>
                      <span class="badge-kkc <?= $pBadgeCls ?>" id="mc-coactor-status-<?= $pId ?>">
                        <?= View::e((string)($p['status_label'] ?? $pStatus)) ?>
                      </span>
                    </div>

                    <div class="small text-muted mb-2">
                      <?php if (!empty($p['birth_date_de'])): ?>
                        Geb.: <strong><?= View::e((string)$p['birth_date_de']) ?></strong><?= $p['age'] !== null ? ' (' . (int)$p['age'] . ' J.)' : '' ?> &bull;
                      <?php endif; ?>
                      <?php if (!empty($p['id_doc_number'])): ?>
                        Ausweis-Nr.: <strong><?= View::e((string)$p['id_doc_number']) ?></strong> &bull;
                      <?php endif; ?>
                      <?php if (!empty($p['city'])): ?>
                        Ort: <?= View::e((string)$p['city']) ?>
                      <?php endif; ?>
                    </div>

                    <!-- Dokument-Vorschaubilder & PDF-Zertifikat -->
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                      <?php if (!empty($p['face_ref_url'])): ?>
                      <a href="<?= View::e((string)$p['face_ref_url']) ?>" target="_blank" rel="noopener"
                         class="text-decoration-none border rounded p-1 bg-body text-center">
                        <img src="<?= View::e((string)$p['face_ref_url']) ?>" alt="KI-Selfie"
                             style="width: 72px; height: 52px; object-fit: cover; display: block;" class="rounded">
                        <span class="d-block text-muted" style="font-size: 0.68rem;">1. KI-Selfie</span>
                      </a>
                      <?php endif; ?>

                      <?php if (!empty($p['id_shot_url'])): ?>
                      <a href="<?= View::e((string)$p['id_shot_url']) ?>" target="_blank" rel="noopener"
                         class="text-decoration-none border rounded p-1 bg-body text-center">
                        <img src="<?= View::e((string)$p['id_shot_url']) ?>" alt="ID-Shot"
                             style="width: 72px; height: 52px; object-fit: cover; display: block;" class="rounded">
                        <span class="d-block text-muted" style="font-size: 0.68rem;">2. ID-Shot</span>
                      </a>
                      <?php endif; ?>

                      <?php if (!empty($p['id_front_url'])): ?>
                      <a href="<?= View::e((string)$p['id_front_url']) ?>" target="_blank" rel="noopener"
                         class="text-decoration-none border rounded p-1 bg-body text-center">
                        <img src="<?= View::e((string)$p['id_front_url']) ?>" alt="Ausweis"
                             style="width: 72px; height: 52px; object-fit: cover; display: block;" class="rounded">
                        <span class="d-block text-muted" style="font-size: 0.68rem;">3. Ausweis</span>
                      </a>
                      <?php endif; ?>

                      <?php if ($isReady && !empty($p['certificate_url'])): ?>
                      <a href="<?= View::e((string)$p['certificate_url']) ?>" target="_blank" rel="noopener"
                         class="btn btn-sm btn-outline-kkc">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Unterschriebene Freigabe / PDF
                      </a>
                      <?php endif; ?>

                      <?php if (!$isReady): ?>
                      <span class="text-danger small fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Noch kein vollständiger ID-Shot / keine Unterschrift eingereicht!
                      </span>
                      <?php endif; ?>
                    </div>

                    <!-- 1-Klick Admin-Prüfbuttons per AJAX -->
                    <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                      <button type="button" class="btn btn-sm btn-success py-1 px-2"
                              onclick="App.reviewMovieCoActor(<?= $movieId ?>, <?= $merchantId ?>, <?= $pId ?>, 'verify')">
                        <i class="bi bi-check-lg me-1"></i> ID-Shot &amp; Person bestätigen
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2"
                              onclick="App.reviewMovieCoActor(<?= $movieId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reject')">
                        <i class="bi bi-x-lg me-1"></i> ID-Shot ablehnen
                      </button>
                    </div>
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
                <span>Szenen-Vorschaubilder (FSK16 &amp; FSK18 Auswahl)</span>
              </div>
              <div class="d-flex align-items-center gap-2 small">
                <span class="badge bg-info text-dark" id="mc-badge-selected-fsk16">FSK16: Bild #<?= (int)$movie['preview_image_fsk16'] ?></span>
                <span class="badge bg-danger text-white" id="mc-badge-selected-fsk18">FSK18: Bild #<?= (int)$movie['preview_image_fsk18'] ?></span>
              </div>
            </div>
            <div class="card-kkc-body">
              <p class="text-muted small mb-3">
                Wähle für <strong>FSK16</strong> (jugendschutzkonform, keine expliziten Genitalien) und <strong>FSK18</strong> jeweils das passende Vorschaubild per Klick aus. Ein Klick auf das Lupen-Symbol öffnet die Großansicht.
              </p>

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
                           onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=<?= $i ?>', 'Szene #<?= $i ?> – Film #<?= $movieId ?>')">
                      <span class="position-absolute top-0 start-0 m-1 px-1.5 py-0.5 rounded bg-dark bg-opacity-75 text-white font-monospace" style="font-size: 0.7rem;">
                        #<?= $i ?>
                      </span>
                      <button type="button" class="btn btn-sm btn-dark position-absolute top-0 end-0 m-1 py-0 px-1 opacity-75"
                              onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=<?= $i ?>', 'Szene #<?= $i ?> – Film #<?= $movieId ?>')"
                              title="Großansicht">
                        <i class="bi bi-arrows-fullscreen" style="font-size: 0.7rem;"></i>
                      </button>
                    </div>
                    <div class="d-flex gap-1">
                      <button type="button" class="btn btn-sm flex-fill py-1 mc-btn-fsk16 <?= $isFsk16 ? 'btn-info text-dark fw-bold' : 'btn-outline-kkc' ?>"
                              data-thumb-num="<?= $i ?>" onclick="App.selectMovieThumb(16, <?= $i ?>)">
                        FSK16
                      </button>
                      <button type="button" class="btn btn-sm flex-fill py-1 mc-btn-fsk18 <?= $isFsk18 ? 'btn-danger text-white fw-bold' : 'btn-outline-kkc' ?>"
                              data-thumb-num="<?= $i ?>" onclick="App.selectMovieThumb(18, <?= $i ?>)">
                        FSK18
                      </button>
                    </div>
                  </div>
                </div>
                <?php endfor; ?>

                <!-- Eigenes FSK16 Cover (#11) & Eigenes FSK18 Cover (#12) -->
                <?php if (!empty($movie['has_custom_fsk16']) || (int)$movie['preview_image_fsk16'] === 11):
                    $isCustom16 = ((int)$movie['preview_image_fsk16'] === 11);
                ?>
                <div class="col-md-6 col-6">
                  <div class="p-2 rounded border h-100 mc-thumb-card <?= $isCustom16 ? 'border-primary' : '' ?>" id="mc-thumb-card-11">
                    <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                      <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=11&amp;w=320"
                           alt="Eigenes FSK16 (#11)" class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                           onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=11', 'Eigenes FSK16 Cover (#11)')">
                      <span class="position-absolute top-0 start-0 m-1 px-2 py-0.5 rounded bg-info text-dark fw-bold" style="font-size: 0.7rem;">
                        Eigenes FSK16 (#11)
                      </span>
                    </div>
                    <button type="button" class="btn btn-sm w-100 py-1 mc-btn-fsk16 <?= $isCustom16 ? 'btn-info text-dark fw-bold' : 'btn-outline-kkc' ?>"
                            data-thumb-num="11" onclick="App.selectMovieThumb(16, 11)">
                      Als FSK16 wählen (#11)
                    </button>
                  </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($movie['has_custom_fsk18']) || (int)$movie['preview_image_fsk18'] === 12):
                    $isCustom18 = ((int)$movie['preview_image_fsk18'] === 12);
                ?>
                <div class="col-md-6 col-6">
                  <div class="p-2 rounded border h-100 mc-thumb-card <?= $isCustom18 ? 'border-primary' : '' ?>" id="mc-thumb-card-12">
                    <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 16/9;">
                      <img src="media.php?type=thumb&amp;file_id=<?= View::e($fileId) ?>&amp;num=12&amp;w=320"
                           alt="Eigenes FSK18 (#12)" class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                           onclick="App.openImagePreviewModal('media.php?type=thumb&file_id=<?= View::e($fileId) ?>&num=12', 'Eigenes FSK18 Cover (#12)')">
                      <span class="position-absolute top-0 start-0 m-1 px-2 py-0.5 rounded bg-danger text-white fw-bold" style="font-size: 0.7rem;">
                        Eigenes FSK18 (#12)
                      </span>
                    </div>
                    <button type="button" class="btn btn-sm w-100 py-1 mc-btn-fsk18 <?= $isCustom18 ? 'btn-danger text-white fw-bold' : 'btn-outline-kkc' ?>"
                            data-thumb-num="12" onclick="App.selectMovieThumb(18, 12)">
                      Als FSK18 wählen (#12)
                    </button>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>

        <!-- ==================================================================
             RECHTE SPALTE: METADATEN, PREISE, KATEGORIEN, FREIGABE & ABLEHNUNG
             ================================================================== -->
        <div class="col-xl-6">

          <!-- 1. Filmdaten, Preise & Einstellungen -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header">
              <div class="card-title">
                <i class="bi bi-sliders ec-text-primary"></i>
                <span>Filmdaten, Preise &amp; Einstellungen</span>
              </div>
              <span class="badge-kkc badge-kkc-neutral">Metadaten</span>
            </div>
            <div class="card-kkc-body">
              <div class="row g-3">

                <!-- Darsteller-Profil -->
                <div class="col-md-6">
                  <label class="form-label small fw-bold mb-1">Zugeordnetes Darsteller-Profil *</label>
                  <div class="input-group input-group-sm">
                    <select name="actor_id" id="mc-actor-select" class="form-select" required>
                      <option value="0">-- Bitte Profil wählen --</option>
                      <?php foreach ($merchantActors as $ma): ?>
                      <option value="<?= (int)$ma['id'] ?>" <?= (int)$ma['id'] === $actorId ? 'selected' : '' ?>>
                        <?= View::e((string)$ma['username']) ?> (#<?= (int)$ma['id'] ?>)
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ($actorId > 0): ?>
                    <a href="#/actor?id=<?= $actorId ?>" class="btn btn-outline-kkc" title="Zum Darsteller-Profil">
                      <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Sprache -->
                <div class="col-md-6">
                  <label class="form-label small fw-bold mb-1">Sprache im Film *</label>
                  <select name="movie_language" class="form-select form-select-sm">
                    <?php foreach ($languages as $langKey => $langName): ?>
                    <option value="<?= View::e($langKey) ?>" <?= $movie['movie_language'] === $langKey ? 'selected' : '' ?>>
                      <?= View::e($langName) ?> (<?= strtoupper(View::e($langKey)) ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Filmtitel -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-bold mb-0">Filmtitel *</label>
                    <small class="text-muted font-monospace" id="mc-title-counter"><?= mb_strlen((string)$movie['title']) ?> Zeichen</small>
                  </div>
                  <input type="text" name="title" id="mc-input-title" class="form-control form-control-sm"
                         value="<?= View::e((string)$movie['title']) ?>" required
                         oninput="App.updateMovieFormCounters()">
                </div>

                <!-- Filmbeschreibung -->
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
                  <label class="form-label small fw-bold mb-1">Online ab Datum *</label>
                  <input type="date" name="online_at" class="form-control form-control-sm"
                         value="<?= View::e((string)$movie['online_at']) ?>" required>
                </div>

                <div class="col-md-7">
                  <label class="form-label small fw-bold mb-1">Streaming-Preis pro Sekunde *</label>
                  <div class="input-group input-group-sm">
                    <select name="amount_second" id="mc-input-amount-second" class="form-select"
                            data-playtime="<?= $playtime ?>" onchange="App.updateMovieStreamingPrice()">
                      <?php for ($i = 0; $i <= 160; $i++):
                          $val = number_format($i / 100, 2, '.', '');
                          $sel = (number_format((float)$movie['amount_second'], 2, '.', '') === $val) ? 'selected' : '';
                      ?>
                      <option value="<?= $val ?>" <?= $sel ?>><?= number_format($i / 100, 2, ',', '.') ?> Coins / Sek.</option>
                      <?php endfor; ?>
                    </select>
                    <span class="input-group-text fw-bold ec-text-primary" id="mc-live-total-coins">
                      = <?= (int)$movie['total_coins'] ?> Coins gesamt
                    </span>
                  </div>
                </div>

                <!-- Download-Option & Download-Aufschlag -->
                <div class="col-md-5">
                  <label class="form-label small fw-bold mb-1">Als Download anbieten</label>
                  <select name="as_download" id="mc-input-as-download" class="form-select form-select-sm"
                          onchange="App.toggleMovieDownloadPrice()">
                    <option value="0" <?= empty($movie['as_download']) ? 'selected' : '' ?>>Nein (Nur Stream)</option>
                    <option value="1" <?= !empty($movie['as_download']) ? 'selected' : '' ?>>Ja (Stream + Download)</option>
                  </select>
                </div>

                <div class="col-md-7" id="mc-download-price-wrap" style="<?= empty($movie['as_download']) ? 'opacity: 0.5;' : '' ?>">
                  <label class="form-label small fw-bold mb-1">Download-Aufschlag (%)</label>
                  <select name="amount_download" id="mc-input-amount-download" class="form-select form-select-sm">
                    <?php for ($i = 0; $i <= 150; $i++): ?>
                    <option value="<?= $i ?>" <?= (int)$movie['amount_download'] === $i ? 'selected' : '' ?>>
                      +<?= $i ?>%<?= $i === 0 ? ' (gleicher Preis wie Stream)' : ($i === 10 ? ' (Empfohlen)' : '') ?>
                    </option>
                    <?php endfor; ?>
                  </select>
                </div>

                <!-- Qualität, Vorschau & Sichtbarkeit -->
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Qualitäts-Stufe</label>
                  <?php
                    $qualityOptions = ['sd' => 'SD', 'hd' => 'HD (720p)', 'fhd' => 'Full HD (1080p)', '2k' => '2K', '4k' => '4K', '6k' => '6K'];
                    $currentQuality = strtolower(trim((string)($movie['quality'] ?? '')));
                    if (!isset($qualityOptions[$currentQuality])) {
                        $currentQuality = 'hd';
                    }
                  ?>
                  <select name="quality" class="form-select form-select-sm">
                    <?php foreach ($qualityOptions as $qKey => $qLabel): ?>
                    <option value="<?= View::e($qKey) ?>" <?= $currentQuality === $qKey ? 'selected' : '' ?>><?= View::e($qLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Ist der Film ein Trailer?</label>
                  <select name="preview" class="form-select form-select-sm">
                    <option value="0" <?= (int)$movie['preview'] === 0 ? 'selected' : '' ?>>Nein (Vollfilm)</option>
                    <option value="1" <?= (int)$movie['preview'] === 1 ? 'selected' : '' ?>>Ja (Trailer)</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Film-Status *</label>
                  <select name="movie_status" class="form-select form-select-sm">
                    <option value="active" <?= $movie['status'] === 'active' ? 'selected' : '' ?>>Aktiv (Freigeben)</option>
                    <option value="blocked" <?= $movie['status'] === 'blocked' ? 'selected' : '' ?>>Gesperrt</option>
                    <?php if ($employeeRule === 'write'): ?>
                    <option value="deleted" <?= $movie['status'] === 'deleted' ? 'selected' : '' ?>>Gelöscht</option>
                    <?php endif; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label small fw-bold mb-1">Sichtbar für Webseite</label>
                  <select name="visible_for_website" id="mc-visible-website" class="form-select form-select-sm">
                    <option value="public" id="mc-visible-public-opt" <?= $movie['visible_for_website'] === 'public' ? 'selected' : '' ?>>
                      Alle angeschlossenen Webseiten (public)
                    </option>
                    <?php foreach ($merchantDomains as $dom): ?>
                    <option value="<?= View::e($dom) ?>" <?= $movie['visible_for_website'] === $dom ? 'selected' : '' ?>>
                      Nur auf: <?= View::e($dom) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text small text-warning d-none" id="mc-poppers-warning">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Bei Auswahl der Kategorie „Poppers“ darf der Film nicht auf „public“ stehen.
                  </div>
                </div>

                <!-- SEO-Felder (kompakt & mit 1-Klick Generatoren) -->
                <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <span class="small fw-bold text-uppercase text-muted">SEO &amp; URL-Metadaten</span>
                    <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" style="font-size: 0.72rem;"
                            onclick="App.autofillMovieSeoFields()">
                      <i class="bi bi-magic me-1"></i> SEO-Felder automatisch aus Titel/Text füllen
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
                        <label class="text-muted">SEO-URL</label>
                      </div>
                      <input type="text" name="seo_url" id="mc-input-seo-url" class="form-control form-control-sm font-monospace"
                             value="<?= View::e((string)$movie['seo_url']) ?>">
                    </div>

                    <div class="col-12">
                      <div class="d-flex justify-content-between small mb-1">
                        <label class="text-muted">Meta-Description (max. 156)</label>
                        <span class="font-monospace text-muted" id="mc-meta-desc-counter"><?= mb_strlen((string)$movie['meta_description']) ?>/156</span>
                      </div>
                      <input type="text" name="meta_description" id="mc-input-meta-desc" maxlength="156" class="form-control form-control-sm"
                             value="<?= View::e((string)$movie['meta_description']) ?>" oninput="App.updateMovieFormCounters()">
                    </div>
                  </div>
                </div>

                <!-- Interne Admin-Notizen -->
                <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <label class="form-label small fw-bold mb-1">Interne Admin-Notizen zum Film (`admin_infos`)</label>
                  <textarea name="admin_infos" class="form-control form-control-sm" rows="2"
                            placeholder="Optionale interne Hinweise für andere Admins..."><?= View::e((string)$movie['admin_infos']) ?></textarea>
                </div>

              </div>
            </div>
          </div>

          <!-- 2. Kategorisierung (Hauptkategorie & Unterkategorien) -->
          <div class="card-kkc mb-4">
            <div class="card-kkc-header flex-wrap gap-2">
              <div class="card-title">
                <i class="bi bi-tags-fill ec-text-primary"></i>
                <span>Kategorisierung &amp; Freigabe</span>
              </div>
              <span class="badge-kkc badge-kkc-info" id="mc-selected-cats-badge">
                <?= count($movie['selected_slaves']) ?> Unterkategorien gewählt
              </span>
            </div>
            <div class="card-kkc-body">

              <!-- Hauptkategorie (Porno vs. Fetisch) -->
              <div class="mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
                <label class="form-label small fw-bold d-block mb-2">Hauptkategorie *</label>
                <div class="d-flex gap-3">
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border cursor-pointer" style="background: var(--kkc-surface-hover); cursor: pointer;">
                    <input type="radio" name="category_master" value="porn" class="form-check-input mt-0"
                           <?= $movie['category_master'] === 'porn' ? 'checked' : '' ?>>
                    <span class="fw-bold">Porno</span>
                  </label>
                  <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border cursor-pointer" style="background: var(--kkc-surface-hover); cursor: pointer;">
                    <input type="radio" name="category_master" value="fetish" class="form-check-input mt-0"
                           <?= $movie['category_master'] === 'fetish' ? 'checked' : '' ?>>
                    <span class="fw-bold">Fetisch</span>
                  </label>
                </div>
              </div>

              <!-- Schnellsuche für Unterkategorien -->
              <div class="mb-3">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
                  <input type="search" id="mc-cat-search-input" class="form-control"
                         placeholder="Unterkategorie schnell suchen (z.B. Amateur, Blowjob, Outdoor...)"
                         oninput="App.filterMovieCategories(this.value)">
                </div>
              </div>

              <!-- Gruppierte Unterkategorien -->
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
                        $isSel  = !empty($cat['selected']);
                        $inTxt  = !empty($cat['in_text']);
                    ?>
                    <div class="col-sm-6 col-md-4 mc-cat-item" data-cat-label="<?= View::e(strtolower($deName . ' ' . $idName)) ?>">
                      <label class="d-flex align-items-center gap-2 p-1.5 rounded border small w-100 <?= $isSel ? 'border-primary bg-primary bg-opacity-10 fw-semibold' : ($inTxt ? 'border-warning bg-warning bg-opacity-10' : '') ?>"
                             style="cursor: pointer; padding: 5px 8px;">
                        <input type="checkbox" name="categories[]" value="<?= View::e($idName) ?>"
                               class="form-check-input mt-0 mc-cat-checkbox"
                               <?= $isSel ? 'checked' : '' ?>
                               onchange="App.onMovieCategoryChange(this)">
                        <span class="text-truncate" title="<?= View::e($deName) ?>"><?= View::e($deName) ?></span>
                        <?php if ($inTxt && !$isSel): ?>
                        <span class="ms-auto badge bg-warning text-dark" style="font-size: 0.6rem;" title="Begriff kommt im Titel oder Beschreibungstext vor">Text</span>
                        <?php endif; ?>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- HAUPT-SPEICHER- & FREIGABE-BUTTON -->
              <div class="mt-4 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
                <div class="form-check mb-0">
                  <input class="form-check-input" type="checkbox" id="mc-auto-next-check" checked>
                  <label class="form-check-label small text-muted" for="mc-auto-next-check">
                    Nach Freigabe direkt nächsten offenen Film öffnen
                  </label>
                </div>
                <button type="submit" id="mc-submit-approve-btn" class="btn btn-kkc px-4 py-2 fw-bold">
                  <i class="bi bi-check2-circle me-1"></i> Film speichern &amp; freigeben
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </form>

    <!-- ======================================================================
         FILM ABLEHNEN & BISHERIGE ABLEHNUNGS-HISTORIE
         ====================================================================== -->
    <div class="card-kkc border-danger mb-4" id="mc-reject-card">
      <div class="card-kkc-header bg-danger bg-opacity-10">
        <div class="card-title text-danger">
          <i class="bi bi-x-octagon-fill"></i>
          <span>Film ablehnen &amp; an Partner zurückweisen</span>
        </div>
        <?php if (!empty($rejectionHistory)): ?>
        <span class="badge bg-danger"><?= count($rejectionHistory) ?> frühere Ablehnung(en)</span>
        <?php endif; ?>
      </div>
      <div class="card-kkc-body">
        <form id="mc-reject-form" onsubmit="App.submitMovieReject(event)">
          <input type="hidden" name="movie_id" value="<?= $movieId ?>">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label small fw-bold mb-1">Ablehnungsgrund (Vorlage) *</label>
              <select name="rejection_reason" id="mc-rejection-reason-select" class="form-select form-select-sm"
                      onchange="App.onMovieRejectionReasonChange(this)">
                <option value="0">-- Bitte Ablehnungsgrund wählen --</option>
                <?php foreach ($rejectionReasons as $rr): ?>
                <option value="<?= View::e((string)$rr['id_name']) ?>" data-long-text="<?= View::e((string)$rr['de_long']) ?>">
                  <?= View::e((string)$rr['de_short']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label small fw-bold mb-1">Nachricht / Begründung an den Partner *</label>
              <textarea name="rejection_text" id="mc-rejection-text" class="form-control form-control-sm" rows="3"
                        placeholder="Wähle links eine Vorlage oder formuliere den genauen Ablehnungsgrund..."></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button type="submit" id="mc-submit-reject-btn" class="btn btn-danger btn-sm px-3 py-2 fw-bold">
                <i class="bi bi-slash-circle me-1"></i> Film jetzt verbindlich ablehnen
              </button>
            </div>
          </div>
        </form>

        <?php if (!empty($rejectionHistory)): ?>
        <div class="mt-3 pt-3 border-top" style="border-color: var(--kkc-border) !important;">
          <h6 class="small fw-bold text-uppercase text-muted mb-2">
            <i class="bi bi-clock-history me-1"></i> Bisherige Ablehnungs-Historie für diesen Film
          </h6>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($rejectionHistory as $rh): ?>
            <div class="p-2 rounded border small" style="background: var(--kkc-surface-hover);">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge-kkc badge-kkc-danger"><?= View::e((string)$rh['de_short']) ?></span>
                <span class="font-monospace text-muted"><?= View::e((string)$rh['datetime']) ?></span>
              </div>
              <div class="text-muted"><?= nl2br(View::e((string)$rh['text'])) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ========================================================================
       TAB 2: BRAM & KI-ANALYSE DETAILS
       ======================================================================== -->
  <div class="tab-pane fade" id="mc-tab-bram" role="tabpanel">
    <div class="card-kkc mb-4">
      <div class="card-kkc-header flex-wrap gap-2">
        <div class="card-title">
          <i class="bi bi-shield-check ec-text-primary"></i>
          <span>Detaillierter Visa / Mastercard BRAM-Compliance-Report</span>
        </div>
        <button type="button" class="btn btn-sm btn-kkc" onclick="App.runMovieAiAction(<?= $movieId ?>, 'run_text_check')">
          <i class="bi bi-arrow-repeat me-1"></i> BRAM-Textprüfung jetzt neu ausführen
        </button>
      </div>
      <div class="card-kkc-body">
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
              <div class="small text-muted mb-1">BRAM-Ampelstatus</div>
              <div class="h5 mb-1"><span class="badge-kkc <?= $bramBadgeCls ?>"><?= View::e($bramLabel) ?></span></div>
              <div class="small text-muted">Zuletzt geprüft: <?= View::e((string)($aiData['updated_at'] ?: 'Gerade eben')) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
              <div class="small text-muted mb-1">Risiko-Score</div>
              <div class="h4 fw-bold font-monospace mb-1"><?= $bramScore ?> / 100</div>
              <div class="small text-muted">Engine: <?= View::e((string)($aiData['text_engine_used'] ?: 'BRAM Keyword-Scanner')) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border h-100" style="background: var(--kkc-surface-hover);">
              <div class="small text-muted mb-1">Zusammenfassung</div>
              <div class="small fw-semibold"><?= View::e((string)($aiData['text_summary'] ?: 'Keine Verstöße erkannt.')) ?></div>
            </div>
          </div>
        </div>

        <?php if (empty($violations)): ?>
          <div class="p-4 rounded border text-center bg-success bg-opacity-10 border-success">
            <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
            <h3 class="h6 fw-bold mb-1">Keine verbotenen oder kritischen Begriffe gefunden</h3>
            <p class="text-muted small mb-0">
              Weder im Filmtitel, in der Beschreibung, in den SEO-Metadaten noch im Audio-Transkript wurden BRAM-Treffer festgestellt.
            </p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-kkc align-middle mb-0">
              <thead>
                <tr>
                  <th>Kategorie / Regel</th>
                  <th>Schweregrad</th>
                  <th>Quelle</th>
                  <th>Zeitstempel</th>
                  <th>Erkannter Begriff</th>
                  <th>Textstelle / Zitat</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($violations as $v):
                    $isCrit = (($v['severity'] ?? '') === 'critical');
                ?>
                <tr>
                  <td class="fw-bold"><?= View::e((string)($v['category'] ?? '')) ?></td>
                  <td>
                    <span class="badge-kkc <?= $isCrit ? 'badge-kkc-danger' : 'badge-kkc-warning' ?>">
                      <?= $isCrit ? 'Kritischer Verstoß' : 'Warnhinweis' ?>
                    </span>
                  </td>
                  <td><?= View::e((string)($v['source'] ?? 'Text')) ?></td>
                  <td class="font-monospace"><?= View::e((string)($v['timestamp'] ?? '-')) ?></td>
                  <td><code class="fw-bold text-danger"><?= View::e((string)($v['matched_term'] ?? '')) ?></code></td>
                  <td class="small text-muted">„<?= View::e((string)($v['quote'] ?? '')) ?>“</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       TAB 3: AUDIO & TRANSKRIPT (.VTT)
       ======================================================================== -->
  <div class="tab-pane fade" id="mc-tab-audio" role="tabpanel">
    <div class="card-kkc mb-4">
      <div class="card-kkc-header flex-wrap gap-2">
        <div class="card-title">
          <i class="bi bi-mic-fill ec-text-primary"></i>
          <span>Whisper Audio-Transkription &amp; WebVTT-Untertitel</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.runMovieAiAction(<?= $movieId ?>, 'run_text_check')">
            <i class="bi bi-check2-square me-1"></i> Bearbeitetes Transkript speichern &amp; BRAM prüfen
          </button>
          <button type="button" class="btn btn-sm btn-kkc" onclick="App.runMovieAiAction(<?= $movieId ?>, 'run_whisper')">
            <i class="bi bi-soundwave me-1"></i> Whisper Audio-Analyse neu starten
          </button>
        </div>
      </div>
      <div class="card-kkc-body">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="p-3 rounded border" style="background: var(--kkc-surface-hover);">
              <div class="small text-muted">Whisper-Status</div>
              <div class="fw-bold">
                <?php if ($subStatus === 2): ?>
                  <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Transkript &amp; .vtt vorhanden</span>
                <?php elseif ($subStatus === 3): ?>
                  <span class="text-muted"><i class="bi bi-volume-mute-fill me-1"></i>Kein gesprochener Dialog erkannt</span>
                <?php elseif ($subStatus === 4): ?>
                  <span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Fehler: <?= View::e((string)$aiData['last_error']) ?></span>
                <?php else: ?>
                  <span class="text-warning"><i class="bi bi-hourglass-split me-1"></i>Noch keine Audio-Analyse durchgeführt</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border" style="background: var(--kkc-surface-hover);">
              <div class="small text-muted">Erkannte Sprache &amp; Sicherheit</div>
              <div class="fw-bold font-monospace">
                <?= $detLang !== '' ? View::e($detLang) . ' (' . number_format((float)$aiData['language_confidence'], 1, ',', '.') . '%)' : '–' ?>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded border" style="background: var(--kkc-surface-hover);">
              <div class="small text-muted">Untertitel-Zieldatei</div>
              <div class="fw-bold font-monospace small"><?= View::e((string)$aiData['subtitle_filename']) ?></div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-7">
            <label class="form-label small fw-bold">Gesprochenes Transkript (mit Zeitstempeln, direkt editierbar)</label>
            <textarea id="mc-spoken-transcript" class="form-control form-control-sm font-monospace" rows="12"
                      placeholder="Hier erscheint das gesprochene Transkript im Format [00:05 - 00:12] Text..."><?= View::e((string)$aiData['spoken_transcript']) ?></textarea>
          </div>
          <div class="col-lg-5">
            <label class="form-label small fw-bold">Generierte WebVTT-Untertiteldatei (`.vtt` Vorschau)</label>
            <textarea class="form-control form-control-sm font-monospace" rows="12" readonly
                      placeholder="WEBVTT..."><?= View::e((string)$aiData['vtt_content']) ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     MODALS: VOLLBILD-BILDVORSCHAU & GETID3 DATEI-METADATEN
     ========================================================================== -->
<div class="modal fade" id="mcImagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background: var(--kkc-card-bg); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2 px-3">
        <h5 class="modal-title h6 mb-0" id="mcImagePreviewTitle">Vorschaubild</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-2 text-center bg-dark">
        <img src="" id="mcImagePreviewImg" alt="Vorschau" class="img-fluid rounded" style="max-height: 78vh;">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="mcFileMetaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="background: var(--kkc-card-bg); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2 px-3">
        <h5 class="modal-title h6 mb-0"><i class="bi bi-code-square ec-text-primary me-2"></i>Technische Datei-Metadaten (getID3) – Film #<?= $movieId ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-3" id="mcFileMetaModalBody">
        <div class="text-center p-4 text-muted">
          <div class="spinner-border spinner-border-sm me-2"></div> Lade Datei-Metadaten...
        </div>
      </div>
    </div>
  </div>
</div>
