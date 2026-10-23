<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<string, mixed> $album
 * @var int $prevOnlineId
 * @var int $nextOnlineId
 * @var array<string, mixed>|null $duplicateAlbum
 * @var array<int, array<string, mixed>> $photos
 * @var array<int, array<string, mixed>> $merchantActors
 * @var array<string, array<int, array<string, mixed>>> $categoriesByType
 * @var array<int, array<string, mixed>> $rejectionHistory
 * @var array<string, mixed> $performersData
 * @var array<string, mixed> $bramReport
 */

$onlineId    = (int)$album['online_id'];
$albumDbId   = (int)$album['album_db_id'];
$albumIdHash = (string)$album['album_id'];
$merchantId  = (int)$album['merchant_id'];
$actorId     = (int)$album['actor_id'];

$bramComp   = (string)($bramReport['compliance_status'] ?? 'green');
$bramScore  = (int)($bramReport['risk_score'] ?? 0);
$violations = is_array($bramReport['violations'] ?? null) ? $bramReport['violations'] : [];

$bramBannerCls = 'border-success bg-success bg-opacity-10';
$bramBadgeCls  = 'badge-kkc-success';
$bramLabel     = 'BRAM Unbedenklich (Grün)';
$bramIcon      = 'bi-shield-check';
if ($bramComp === 'red') {
    $bramBannerCls = 'border-danger bg-danger bg-opacity-10';
    $bramBadgeCls  = 'badge-kkc-danger';
    $bramLabel     = 'BRAM Verstoß / Kritisch (Rot)';
    $bramIcon      = 'bi-exclamation-octagon-fill';
} elseif ($bramComp === 'yellow') {
    $bramBannerCls = 'border-warning bg-warning bg-opacity-10';
    $bramBadgeCls  = 'badge-kkc-warning';
    $bramLabel     = 'BRAM Hinweis / Prüfen (Gelb)';
    $bramIcon      = 'bi-exclamation-triangle-fill';
}

$categoryGroupLabels = [
    'number_of_people' => ['label' => 'Anzahl der Personen & Orientierung', 'icon' => 'bi-people-fill'],
    'look_and_body'    => ['label' => 'Körper & Aussehen',                  'icon' => 'bi-person-heart'],
    'fetish'           => ['label' => 'Fetisch & BDSM',                     'icon' => 'bi-fire'],
    'porn'             => ['label' => 'Porno & Sonstige',                   'icon' => 'bi-images'],
];

$selectedCatsCount = 0;
foreach ($categoriesByType as $grpItems) {
    foreach ($grpItems as $ci) {
        if (!empty($ci['selected'])) {
            $selectedCatsCount++;
        }
    }
}
?>
<div class="container-fluid py-3" data-page-width="full">

  <!-- ========================================================================
       STICKY HEADER & SCHNELL-NAVIGATION
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <a href="#/photo-albums-online" class="btn btn-sm btn-outline-kkc py-0 px-2">
          <i class="bi bi-arrow-left me-1"></i> Alle Fotoalben online
        </a>
        <span class="badge-kkc badge-kkc-info font-monospace">Online-ID #<?= $onlineId ?></span>
        <span class="badge-kkc badge-kkc-neutral font-monospace">Album #<?= $albumDbId ?></span>
        <span class="badge-kkc badge-kkc-neutral font-monospace">Hash: <?= View::e($albumIdHash) ?></span>
        <?php if ($album['status'] === 'active'): ?>
          <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Aktiv</span>
        <?php elseif ($album['status'] === 'blocked'): ?>
          <span class="badge-kkc badge-kkc-warning"><i class="bi bi-pause-circle-fill me-1"></i>Gesperrt</span>
        <?php else: ?>
          <span class="badge-kkc badge-kkc-danger"><i class="bi bi-trash-fill me-1"></i>Gelöscht</span>
        <?php endif; ?>
      </div>
      <h1 class="h4 fw-bold mb-0 text-break">
        <i class="bi bi-journal-album ec-text-primary me-1"></i>
        <?= View::e((string)$album['title'] ?: '(Ohne Albumtitel)') ?>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php if ($prevOnlineId > 0): ?>
        <a href="#/photo-album-edit?id=<?= $prevOnlineId ?>" class="btn btn-sm btn-outline-kkc" title="Vorheriges Online-Fotoalbum (#<?= $prevOnlineId ?>)">
          <i class="bi bi-chevron-left"></i> Vorheriges (#<?= $prevOnlineId ?>)
        </a>
      <?php endif; ?>
      <?php if ($nextOnlineId > 0): ?>
        <a href="#/photo-album-edit?id=<?= $nextOnlineId ?>" class="btn btn-sm btn-outline-kkc" title="Nächstes Online-Fotoalbum (#<?= $nextOnlineId ?>)">
          Nächstes (#<?= $nextOnlineId ?>) <i class="bi bi-chevron-right"></i>
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-kkc fw-bold" onclick="document.getElementById('pao-edit-form')?.requestSubmit()">
        <i class="bi bi-floppy-fill me-1"></i> Änderungen speichern
      </button>
    </div>
  </div>

  <!-- ========================================================================
       BRAM-SCHNELLSTATUS-BANNER
       ======================================================================== -->
  <div class="p-3 rounded border mb-3 <?= $bramBannerCls ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge-kkc <?= $bramBadgeCls ?> fs-6 py-1 px-2">
          <i class="bi <?= $bramIcon ?> me-1"></i> <?= View::e($bramLabel) ?>
        </span>
        <span class="badge-kkc badge-kkc-neutral font-monospace">Risiko-Score: <?= $bramScore ?> / 100</span>
        <span class="small fw-semibold"><?= View::e((string)($bramReport['summary_de'] ?? '')) ?></span>
      </div>
    </div>

    <?php if (!empty($violations)): ?>
      <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top" style="border-color: rgba(0,0,0,0.1) !important;">
        <?php foreach ($violations as $v):
          $isCrit = (($v['severity'] ?? '') === 'critical');
        ?>
          <span class="badge <?= $isCrit ? 'bg-danger text-white' : 'bg-warning text-dark' ?> d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.78rem;">
            <i class="bi <?= $isCrit ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
            <strong><?= View::e((string)($v['category'] ?? 'Regel')) ?>:</strong>
            <span>„<?= View::e((string)($v['matched_term'] ?? '')) ?>“</span>
            <span class="opacity-75">(<?= View::e((string)($v['source'] ?? 'Text')) ?>)</span>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Warnhinweis bei doppeltem Albumtitel -->
  <?php if ($duplicateAlbum !== null): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-files fs-4"></i>
        <div>
          <strong>Hinweis auf doppelten Albumtitel:</strong>
          <div class="small">
            Es existiert bereits ein anderes Online-Fotoalbum (<a href="#/photo-album-edit?id=<?= (int)$duplicateAlbum['id'] ?>" class="alert-link">#<?= (int)$duplicateAlbum['id'] ?></a>, MID #<?= (int)$duplicateAlbum['merchant_id'] ?>) mit dem exakt gleichen Titel „<?= View::e((string)$duplicateAlbum['title']) ?>“.
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form id="pao-edit-form" onsubmit="App.submitPhotoAlbumOnlineSave(event)">
    <input type="hidden" name="online_id" value="<?= $onlineId ?>">
    <input type="hidden" name="album_id" value="<?= View::e($albumIdHash) ?>">

    <div class="row g-4">
      <!-- ====================================================================
           LINKE SPALTE: COVERBILDER, AUDIT/KPI-INFO, 18+ MITWIRKENDE & FOTOS
           ==================================================================== -->
      <div class="col-xl-6">

        <!-- 1. FSK16 & FSK18 Coverbilder & Performance-Kennzahlen -->
        <div class="card-kkc mb-4">
          <div class="card-kkc-header flex-wrap gap-2">
            <div class="card-title">
              <i class="bi bi-image-fill ec-text-primary"></i>
              <span>Album-Coverbilder (FSK16 &amp; FSK18) &amp; Kennzahlen</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge-kkc badge-kkc-info"><?= (int)$album['number_of_photos'] ?> Fotos</span>
              <span class="badge-kkc badge-kkc-neutral"><?= View::e((string)$album['folder_size_text']) ?></span>
            </div>
          </div>
          <div class="card-kkc-body">
            <div class="row g-3 mb-3">
              <!-- FSK16 Cover -->
              <div class="col-sm-6">
                <div class="p-2 rounded border h-100" style="background: var(--kkc-surface-hover);">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-info text-dark fw-bold">FSK16 Cover (Jugendschutz)</span>
                    <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-lg"></i> Aktiv</span>
                  </div>
                  <div class="position-relative rounded overflow-hidden bg-dark" style="aspect-ratio: 4/3; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=album_poster&album_id=<?= View::e($albumIdHash) ?>&fsk=16', 'FSK16 Cover – Online-Album #<?= $onlineId ?>')">
                    <img src="media.php?type=album_poster&amp;album_id=<?= View::e($albumIdHash) ?>&amp;fsk=16&amp;w=480"
                         alt="FSK16 Cover" class="w-100 h-100" style="object-fit: cover;">
                    <button type="button" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 m-2 opacity-75">
                      <i class="bi bi-arrows-fullscreen me-1"></i> Großansicht
                    </button>
                  </div>
                </div>
              </div>

              <!-- FSK18 Cover -->
              <div class="col-sm-6">
                <div class="p-2 rounded border h-100" style="background: var(--kkc-surface-hover);">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-danger text-white fw-bold">FSK18 Cover (ab 18 J.)</span>
                    <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-lg"></i> Aktiv</span>
                  </div>
                  <div class="position-relative rounded overflow-hidden bg-dark" style="aspect-ratio: 4/3; cursor: pointer;"
                       onclick="App.openImagePreviewModal('media.php?type=album_poster&album_id=<?= View::e($albumIdHash) ?>&fsk=18', 'FSK18 Cover – Online-Album #<?= $onlineId ?>')">
                    <img src="media.php?type=album_poster&amp;album_id=<?= View::e($albumIdHash) ?>&amp;fsk=18&amp;w=480"
                         alt="FSK18 Cover" class="w-100 h-100" style="object-fit: cover;">
                    <button type="button" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 m-2 opacity-75">
                      <i class="bi bi-arrows-fullscreen me-1"></i> Großansicht
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Partner-, Aufruf- & Prüf-Metadaten -->
            <div class="row g-2 pt-3 border-top small" style="border-color: var(--kkc-border) !important;">
              <div class="col-sm-6">
                <div class="mb-1">
                  <span class="text-muted">Merchant:</span>
                  <strong><?= View::e((string)$album['merchant_username']) ?></strong>
                  (MID #<?= $merchantId ?> &bull; PID #<?= (int)$album['merchant_partner_id'] ?>)
                </div>
                <div class="mb-1">
                  <span class="text-muted">Aufrufe / Verkäufe:</span>
                  <strong><?= number_format((int)$album['views'], 0, ',', '.') ?> Views</strong> &bull;
                  <strong class="text-success"><?= number_format((int)$album['purchases'], 0, ',', '.') ?> Käufe</strong>
                </div>
                <div>
                  <span class="text-muted">Speicherort:</span>
                  <code><?= View::e((string)$album['storage_location']) ?>/<?= $merchantId ?>/<?= $albumDbId ?>/</code>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="mb-1">
                  <span class="text-muted">Freigegeben von:</span>
                  <strong><?= View::e((string)$album['released_from_name']) ?></strong>
                  <?php if ($album['released_datetime'] !== '' && $album['released_datetime'] !== '0000-00-00 00:00:00'): ?>
                    am <?= date('d.m.Y H:i', strtotime((string)$album['released_datetime'])) ?>
                  <?php endif; ?>
                </div>
                <div class="mb-1">
                  <span class="text-muted">Zuletzt bearbeitet:</span>
                  <strong><?= View::e((string)$album['last_updated_by_name']) ?></strong>
                  <?php if ($album['last_updated_date'] !== '' && $album['last_updated_date'] !== '0000-00-00 00:00:00'): ?>
                    am <?= date('d.m.Y H:i', strtotime((string)$album['last_updated_date'])) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <span class="text-muted">Checksumme:</span>
                  <code class="small"><?= View::e((string)$album['checksum']) ?></code>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Mitwirkende Personen & 18+ ID-Shot-Prüfung (CoActorManager) -->
        <?php
          $perfMode    = (string)($performersData['performer_mode'] ?? 'solo_owner');
          $perfPersons = is_array($performersData['persons'] ?? null) ? $performersData['persons'] : [];
        ?>
        <div class="card-kkc mb-4" id="pac-performers-card">
          <div class="card-kkc-header flex-wrap gap-2">
            <div class="card-title">
              <i class="bi bi-person-badge-fill ec-text-primary"></i>
              <span>Mitwirkende Personen &amp; 18+ Nachweise</span>
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
              <div class="alert alert-secondary mb-0 py-2 small">
                <i class="bi bi-info-circle me-1"></i> Keine gesonderten Mitwirkenden-Einträge hinterlegt (Solo-Inhaber).
              </div>
            <?php else: ?>
              <div class="d-flex flex-column gap-3">
                <?php foreach ($perfPersons as $p):
                  $pId       = (int)$p['id'];
                  $isOwner   = !empty($p['is_owner']);
                  $pStatus   = (string)($p['checked_status'] ?? 'pending_qr');
                  $isReady   = !empty($p['is_ready_for_use']);
                  $hasDocs   = !empty($p['has_uploaded_docs']) || !empty($p['has_id_shot']) || !empty($p['face_ref_url']) || !empty($p['id_front_url']);
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
                  <div class="p-3 rounded border" style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                      <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge <?= $isOwner ? 'bg-primary' : 'bg-secondary' ?>">
                          <?= $isOwner ? 'ICH (ACCOUNT-INHABER)' : 'DREHPARTNER' ?>
                        </span>
                        <strong class="text-body"><?= View::e((string)($p['display_label'] ?? '')) ?></strong>
                        <?php if ($isAiReady): ?>
                          <span class="badge-kkc badge-kkc-info"><i class="bi bi-qr-code-scan me-1"></i>Live-QR (KI-Ready)</span>
                        <?php endif; ?>
                      </div>
                      <span class="badge-kkc <?= $pBadgeCls ?>" id="pac-coactor-status-<?= $pId ?>">
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

                    <?php if ($hasDocs): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
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
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                      <button type="button" class="btn btn-sm btn-success py-1 px-2"
                              onclick="App.reviewAlbumCoActor(<?= $albumDbId ?>, <?= $merchantId ?>, <?= $pId ?>, 'verify')">
                        <i class="bi bi-check-lg me-1"></i> ID-Shot bestätigen
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2"
                              onclick="App.reviewAlbumCoActor(<?= $albumDbId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reject')">
                        <i class="bi bi-x-lg me-1"></i> ID-Shot ablehnen
                      </button>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-secondary py-2 px-3 mb-0 small d-flex align-items-center gap-2">
                      <i class="bi bi-info-circle-fill ec-text-primary flex-shrink-0"></i>
                      <span><strong>Noch kein ID-Shot hochgeladen:</strong> Übergangsphase aktiv – das Fotoalbum bleibt unabhängig davon regulär online/bearbeitbar.</span>
                    </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- 3. Alle Einzelfotos im Album verwalten -->
        <div class="card-kkc mb-4">
          <div class="card-kkc-header flex-wrap gap-2">
            <div class="card-title">
              <i class="bi bi-grid-3x3-gap-fill ec-text-primary"></i>
              <span><?= count($photos) ?> Einzelfotos im Album verwalten</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge-kkc <?= (int)$album['rejected_photos'] > 0 ? 'badge-kkc-danger' : 'badge-kkc-success' ?>" id="pac-rejected-photos-badge">
                <?= (int)$album['rejected_photos'] > 0 ? ((int)$album['rejected_photos'] . ' Foto(s) abgelehnt') : 'Alle Fotos zulässig' ?>
              </span>
              <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2"
                      onclick="App.batchAllowAllAlbumPhotos(<?= $albumDbId ?>)"
                      title="Alle abgelehnten Fotos in diesem Album wieder freigeben">
                <i class="bi bi-check2-all me-1"></i> Alle zulassen
              </button>
            </div>
          </div>
          <div class="card-kkc-body">
            <?php if (empty($photos)): ?>
              <div class="alert alert-warning mb-0">
                Keine Einzelfotos in <code>photo_albums_photos</code> gefunden.
              </div>
            <?php else: ?>
              <p class="text-muted small mb-3">
                Klicke auf ein Bild für die interaktive Galerie-Großansicht (mit Pfeiltasten durchblätterbar) oder sperre/aktiviere einzelne Fotos per Klick.
              </p>
              <div class="row g-2" id="pac-photos-grid">
                <?php foreach ($photos as $idxZero => $ph):
                  $phFileId = (string)$ph['file_id'];
                  $phIdx    = (int)$ph['index'];
                  $isRej    = !empty($ph['rejected']);
                ?>
                  <div class="col-md-3 col-sm-4 col-6">
                    <div class="p-1.5 rounded border h-100 pac-photo-card <?= $isRej ? 'border-danger bg-danger bg-opacity-10' : '' ?>"
                         id="pac-photo-card-<?= View::e($phFileId) ?>"
                         data-photo-id="<?= View::e($phFileId) ?>"
                         data-photo-index="<?= (int)$idxZero ?>"
                         data-full-src="media.php?type=album_photo&amp;photo_id=<?= View::e($phFileId) ?>&amp;w=1024"
                         data-photo-label="Foto #<?= $phIdx ?> (Online-Album #<?= $onlineId ?>)"
                         style="padding: 6px; background: var(--kkc-surface-hover);">
                      <div class="position-relative rounded overflow-hidden mb-2 bg-dark" style="aspect-ratio: 1/1;">
                        <img src="media.php?type=album_photo&amp;photo_id=<?= View::e($phFileId) ?>&amp;w=280"
                             alt="Foto #<?= $phIdx ?>" loading="lazy"
                             class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                             onclick="App.openAlbumGalleryModal(<?= (int)$idxZero ?>)">
                        <span class="position-absolute top-0 start-0 m-1 px-1.5 py-0.5 rounded bg-dark bg-opacity-75 text-white font-monospace" style="font-size: 0.68rem;">
                          #<?= $phIdx ?>
                        </span>
                        <span class="position-absolute bottom-0 start-0 end-0 text-center py-0.5 bg-danger text-white fw-bold pac-photo-rej-overlay <?= $isRej ? '' : 'd-none' ?>"
                              style="font-size: 0.68rem;">
                          ABGELEHNT
                        </span>
                      </div>

                      <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2"
                                onclick="App.openAlbumGalleryModal(<?= (int)$idxZero ?>)" title="Großansicht öffnen">
                          <i class="bi bi-zoom-in"></i>
                        </button>
                        <button type="button"
                                class="btn btn-sm flex-fill py-0 pac-photo-toggle-btn <?= $isRej ? 'btn-success' : 'btn-outline-danger' ?>"
                                data-rejected="<?= $isRej ? '1' : '0' ?>"
                                onclick="App.toggleAlbumPhotoReject('<?= View::e($phFileId) ?>', this)">
                          <?php if ($isRej): ?>
                            <i class="bi bi-check-lg me-1"></i>Zulassen
                          <?php else: ?>
                            <i class="bi bi-trash3 me-1"></i>Ablehnen
                          <?php endif; ?>
                        </button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- ====================================================================
           RECHTE SPALTE: STAMMDATEN, STATUS, PREIS, SEO, KATEGORIEN & HISTORIE
           ==================================================================== -->
      <div class="col-xl-6">

        <!-- 1. Stammdaten, Status, Preis & SEO -->
        <div class="card-kkc mb-4">
          <div class="card-kkc-header">
            <div class="card-title">
              <i class="bi bi-sliders ec-text-primary"></i>
              <span>Fotoalbum bearbeiten (Status, Texte, Preis &amp; SEO)</span>
            </div>
            <span class="badge-kkc badge-kkc-neutral">Online-Verwaltung</span>
          </div>
          <div class="card-kkc-body">
            <div class="row g-3">

              <!-- Darsteller-Profil -->
              <div class="col-md-7">
                <label class="form-label small fw-bold mb-1">Zugeordnetes Darsteller-Profil *</label>
                <select name="actor_id" class="form-select form-select-sm" required>
                  <option value="0">-- Bitte Profil wählen --</option>
                  <?php foreach ($merchantActors as $ma): ?>
                    <option value="<?= (int)$ma['id'] ?>" <?= (int)$ma['id'] === $actorId ? 'selected' : '' ?>>
                      <?= View::e((string)$ma['username']) ?> (#<?= (int)$ma['id'] ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Album-Status -->
              <div class="col-md-5">
                <label class="form-label small fw-bold mb-1">Album-Status *</label>
                <select name="album_status" id="pao-status-select" class="form-select form-select-sm" onchange="const w = document.getElementById('pao-delete-warning'); if(w) w.classList.toggle('d-none', this.value !== 'deleted');">
                  <option value="active" <?= $album['status'] === 'active' ? 'selected' : '' ?>>Aktiv (Online)</option>
                  <option value="blocked" <?= $album['status'] === 'blocked' ? 'selected' : '' ?>>Gesperrt</option>
                  <?php if ((string)($employeeRule ?? '') === 'write' || $album['status'] === 'deleted'): ?>
                    <option value="deleted" <?= $album['status'] === 'deleted' ? 'selected' : '' ?>>Löschen (Physisch entfernen)</option>
                  <?php endif; ?>
                </select>
              </div>

              <!-- Warnung bei Auswahl von "Löschen" -->
              <div class="col-12 <?= $album['status'] === 'deleted' ? '' : 'd-none' ?>" id="pao-delete-warning">
                <div class="alert alert-danger mb-0 py-2 small">
                  <i class="bi bi-exclamation-octagon-fill me-1"></i>
                  <strong>Achtung:</strong> Wenn du den Status auf „Löschen“ setzt und speicherst, werden alle Bilddateien dieses Fotoalbums auf dem Server unwiderruflich gelöscht und das Album aus <code>photo_albums_online</code> entfernt!
                </div>
              </div>

              <!-- Albumtitel (max. 65 Zeichen) -->
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label small fw-bold mb-0">Albumtitel (max. 65 Zeichen) *</label>
                  <small class="text-muted font-monospace" id="pac-title-counter"><?= mb_strlen((string)$album['title']) ?>/65</small>
                </div>
                <input type="text" name="title" id="pac-input-title" maxlength="65" class="form-control form-control-sm"
                       value="<?= View::e((string)$album['title']) ?>" required
                       oninput="App.updatePhotoAlbumFormCounters()">
              </div>

              <!-- Album-Beschreibung -->
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label small fw-bold mb-0">Beschreibung des Fotoalbums *</label>
                  <small class="text-muted font-monospace" id="pac-desc-counter"><?= mb_strlen((string)$album['description']) ?> Zeichen</small>
                </div>
                <textarea name="description" id="pac-input-description" class="form-control form-control-sm" rows="6" required
                          oninput="App.updatePhotoAlbumFormCounters()"><?= View::e((string)$album['description']) ?></textarea>
              </div>

              <!-- Online ab & Verkaufspreis -->
              <div class="col-md-6">
                <label class="form-label small fw-bold mb-1">Veröffentlichung ab *</label>
                <input type="datetime-local" name="online_at" class="form-control form-control-sm"
                       value="<?= View::e((string)$album['online_at_input']) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold mb-1">Verkaufspreis des Fotoalbums *</label>
                <select name="amount_download" class="form-select form-select-sm">
                  <?php for ($i = 0; $i <= 50; $i++):
                    $centVal = $i * 100;
                    $coins   = $i * 10;
                    $sel     = ((int)$album['amount_download'] === $centVal) ? 'selected' : '';
                  ?>
                    <option value="<?= $centVal ?>" <?= $sel ?>>
                      <?= number_format($i, 2, ',', '.') ?> EUR <?= $i === 0 ? '(Kostenlos)' : '(' . $coins . ' Coins)' ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </div>

              <!-- SEO-Felder -->
              <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                  <span class="small fw-bold text-uppercase text-muted">Suchmaschinenoptimierung (SEO)</span>
                  <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" style="font-size: 0.72rem;"
                          onclick="App.autofillPhotoAlbumSeoFields()">
                    <i class="bi bi-magic me-1"></i> SEO-Felder automatisch aus Titel/Text füllen
                  </button>
                </div>

                <div class="row g-2">
                  <div class="col-md-6">
                    <div class="d-flex justify-content-between small mb-1">
                      <label class="text-muted">Meta-Title (max. 65)</label>
                      <span class="font-monospace text-muted" id="pac-meta-title-counter"><?= mb_strlen((string)$album['meta_title']) ?>/65</span>
                    </div>
                    <input type="text" name="meta_title" id="pac-input-meta-title" maxlength="65" class="form-control form-control-sm"
                           value="<?= View::e((string)$album['meta_title']) ?>" oninput="App.updatePhotoAlbumFormCounters()">
                  </div>

                  <div class="col-md-6">
                    <div class="d-flex justify-content-between small mb-1">
                      <label class="text-muted">SEO-URL</label>
                    </div>
                    <input type="text" name="seo_url" id="pac-input-seo-url" class="form-control form-control-sm font-monospace"
                           value="<?= View::e((string)$album['seo_url']) ?>">
                  </div>

                  <div class="col-12">
                    <div class="d-flex justify-content-between small mb-1">
                      <label class="text-muted">Meta-Description (max. 156, keine Hashtags)</label>
                      <span class="font-monospace text-muted" id="pac-meta-desc-counter"><?= mb_strlen((string)$album['meta_description']) ?>/156</span>
                    </div>
                    <input type="text" name="meta_description" id="pac-input-meta-desc" maxlength="156" class="form-control form-control-sm"
                           value="<?= View::e((string)$album['meta_description']) ?>" oninput="App.updatePhotoAlbumFormCounters()">
                  </div>
                </div>
              </div>

              <!-- Interne Admin-Notizen -->
              <div class="col-12 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                <label class="form-label small fw-bold mb-1">Interne Admin-Notizen (<code>admin_infos</code>)</label>
                <textarea name="admin_infos" class="form-control form-control-sm" rows="2"
                          placeholder="Optionale interne Hinweise für andere Admins..."><?= View::e((string)$album['admin_infos']) ?></textarea>
              </div>

            </div>
          </div>
        </div>

        <!-- 2. Kategorien zuordnen & Speichern -->
        <div class="card-kkc mb-4">
          <div class="card-kkc-header flex-wrap gap-2">
            <div class="card-title">
              <i class="bi bi-tags-fill ec-text-primary"></i>
              <span>Kategorien zuordnen</span>
            </div>
            <span class="badge-kkc badge-kkc-info" id="pac-selected-cats-badge">
              <?= $selectedCatsCount ?> Unterkategorien gewählt
            </span>
          </div>
          <div class="card-kkc-body">

            <!-- Hauptkategorie (Porno vs. Fetisch) -->
            <div class="mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
              <label class="form-label small fw-bold d-block mb-2">Hauptkategorie *</label>
              <div class="d-flex gap-3">
                <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                  <input type="radio" name="category_master" value="porn" class="form-check-input mt-0"
                         <?= $album['category_master'] === 'porn' ? 'checked' : '' ?>>
                  <div>
                    <div class="fw-bold">Porno</div>
                    <small class="text-muted">Klassische Erotik- &amp; Porno-Aufnahmen</small>
                  </div>
                </label>
                <label class="d-flex align-items-center gap-2 px-3 py-2 rounded border" style="background: var(--kkc-surface-hover); cursor: pointer;">
                  <input type="radio" name="category_master" value="fetish" class="form-check-input mt-0"
                         <?= $album['category_master'] === 'fetish' ? 'checked' : '' ?>>
                  <div>
                    <div class="fw-bold">Fetisch</div>
                    <small class="text-muted">Reines Fetisch-, SM- oder BDSM-Album</small>
                  </div>
                </label>
              </div>
            </div>

            <!-- Schnellsuche für Unterkategorien -->
            <div class="mb-3">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
                <input type="search" id="pac-cat-search-input" class="form-control"
                       placeholder="Album-Kategorie schnell suchen (z.B. Dessous, Blond, Outdoor...)"
                       oninput="App.filterPhotoAlbumCategories(this.value)">
              </div>
            </div>

            <!-- Gruppierte Unterkategorien -->
            <div class="d-flex flex-column gap-3" style="max-height: 440px; overflow-y: auto; padding-right: 4px;">
              <?php foreach ($categoryGroupLabels as $cGrp => $grpMeta):
                $catItems = $categoriesByType[$cGrp] ?? [];
                if (empty($catItems)) {
                    continue;
                }
              ?>
                <div class="pac-cat-group">
                  <div class="small fw-bold text-uppercase text-muted mb-2 d-flex align-items-center gap-1">
                    <i class="bi <?= $grpMeta['icon'] ?> ec-text-primary"></i>
                    <span><?= View::e($grpMeta['label']) ?></span>
                  </div>
                  <div class="row g-1">
                    <?php foreach ($catItems as $cat):
                      $nameId  = (string)$cat['name_id'];
                      $deName  = (string)$cat['de'];
                      $descTxt = (string)($cat['de_text'] ?? '');
                      $isSel   = !empty($cat['selected']);
                      $inTxt   = !empty($cat['in_text']);
                    ?>
                      <div class="col-sm-6 col-md-4 pac-cat-item" data-cat-label="<?= View::e(mb_strtolower($deName . ' ' . $nameId . ' ' . $descTxt, 'UTF-8')) ?>">
                        <label class="d-flex align-items-center gap-2 rounded border small w-100 <?= $isSel ? 'border-primary bg-primary bg-opacity-10 fw-semibold' : ($inTxt ? 'border-warning bg-warning bg-opacity-10' : '') ?>"
                               style="cursor: pointer; padding: 5px 8px;" title="<?= View::e($descTxt ?: $deName) ?>">
                          <input type="checkbox" name="category_slave[]" value="<?= View::e($nameId) ?>"
                                 class="form-check-input mt-0 pac-cat-checkbox"
                                 <?= $isSel ? 'checked' : '' ?>
                                 onchange="App.onPhotoAlbumCategoryChange(this)">
                          <span class="text-truncate"><?= View::e($deName) ?></span>
                          <?php if ($inTxt): ?>
                            <span class="ms-auto badge bg-warning text-dark" style="font-size: 0.58rem;">Text</span>
                          <?php endif; ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- SPEICHERN-BUTTON -->
            <div class="mt-4 pt-3 border-top d-flex justify-content-end" style="border-color: var(--kkc-border) !important;">
              <button type="submit" id="pao-save-btn" class="btn btn-kkc px-4 py-2 fw-bold">
                <i class="bi bi-floppy-fill me-1"></i> Änderungen an Fotoalbum #<?= $onlineId ?> speichern
              </button>
            </div>

          </div>
        </div>

        <!-- 3. Bisherige Ablehnungs-Historie (falls vorhanden) -->
        <?php if (!empty($rejectionHistory)): ?>
          <div class="card-kkc mb-4">
            <div class="card-kkc-header">
              <div class="card-title">
                <i class="bi bi-clock-history text-warning"></i>
                <span>Frühere Ablehnungs-Historie (<?= count($rejectionHistory) ?>)</span>
              </div>
            </div>
            <div class="card-kkc-body">
              <div class="d-flex flex-column gap-2">
                <?php foreach ($rejectionHistory as $rh): ?>
                  <div class="p-2 rounded border small" style="background: var(--kkc-surface-hover);">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span class="badge-kkc badge-kkc-danger"><?= View::e((string)$rh['short_title']) ?></span>
                      <span class="font-monospace text-muted"><?= View::e((string)$rh['datetime']) ?></span>
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

<!-- ==========================================================================
     MODALS: EINZELBILD-VORSCHAU & INTERAKTIVE GALERIE-LIGHTBOX
     ========================================================================== -->
<div class="modal fade" id="mcImagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2 px-3">
        <h5 class="modal-title h6 mb-0" id="mcImagePreviewTitle">Vorschaubild</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-2 text-center bg-dark">
        <img src="" id="mcImagePreviewImg" alt="Vorschau" class="img-fluid rounded" style="max-height: 80vh;">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pacGalleryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2 px-3 d-flex align-items-center justify-content-between">
        <h5 class="modal-title h6 mb-0" id="pacGalleryModalTitle">Foto-Vorschau</h5>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" onclick="App.stepAlbumGallery(-1)">
            <i class="bi bi-chevron-left"></i> Zurück
          </button>
          <button type="button" class="btn btn-sm btn-outline-kkc py-0 px-2" onclick="App.stepAlbumGallery(1)">
            Weiter <i class="bi bi-chevron-right"></i>
          </button>
          <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
      </div>
      <div class="modal-body p-2 text-center bg-dark position-relative">
        <img src="" id="pacGalleryModalImg" alt="Album-Foto" class="img-fluid rounded" style="max-height: 80vh;">
      </div>
    </div>
  </div>
</div>
