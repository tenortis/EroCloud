<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var array<string, mixed> $performersData */
/** @var string $contentType */
/** @var int $contentId */
/** @var int $merchantId */
/** @var string $panelId */
/** @var bool $readOnly */
/** @var bool $matchStageHeight */
/** @var string $pendingDocsHint */

$performersData   = is_array($performersData ?? null) ? $performersData : [];
$contentType      = (($contentType ?? 'movie') === 'album') ? 'album' : 'movie';
$contentId        = abs((int)($contentId ?? 0));
$merchantId       = abs((int)($merchantId ?? 0));
$defaultPanelId   = ($contentType === 'album') ? 'pac-performers-card' : 'mc-performers-card';
$panelIdRaw       = trim((string)($panelId ?? $defaultPanelId));
$panelIdClean     = preg_replace('/[^a-zA-Z0-9_-]/', '', $panelIdRaw);
$panelId          = ($panelIdClean !== null && $panelIdClean !== '') ? $panelIdClean : $defaultPanelId;
$readOnly         = !empty($readOnly);
$matchStageHeight = isset($matchStageHeight) ? (bool)$matchStageHeight : true;

$defaultPendingHint = ($contentType === 'album')
    ? 'Noch kein ID-Shot hochgeladen: Übergangsphase aktiv – du kannst das Fotoalbum unten trotzdem ganz normal prüfen und veröffentlichen.'
    : 'Noch kein ID-Shot hochgeladen: Übergangsphase aktiv – du kannst den Film unten trotzdem ganz normal prüfen und veröffentlichen.';
$pendingDocsHintRaw = trim((string)($pendingDocsHint ?? ''));
$pendingDocsHint    = $pendingDocsHintRaw !== '' ? $pendingDocsHintRaw : $defaultPendingHint;

$perfMode         = (string)($performersData['performer_mode'] ?? 'solo_owner');
$perfPersons      = is_array($performersData['persons'] ?? null) ? $performersData['persons'] : [];
$merchantFullName = (string)($performersData['merchant_full_name'] ?? ('Merchant #' . $merchantId));

$totalCount    = count($perfPersons);
$verifiedCount = 0;
$reviewCount   = 0;
$rejectedCount = 0;
$pendingCount  = 0;

foreach ($perfPersons as $pCountItem) {
    $stCount = (string)($pCountItem['checked_status'] ?? 'pending_qr');
    $hasDocsCount = !empty($pCountItem['has_uploaded_docs'])
        || !empty($pCountItem['has_id_shot'])
        || !empty($pCountItem['face_ref_url'])
        || !empty($pCountItem['id_shot_url'])
        || !empty($pCountItem['id_front_url']);

    if ($stCount === 'rejected') {
        $rejectedCount++;
    } elseif (!$hasDocsCount) {
        $pendingCount++;
    } elseif ($stCount === 'verified') {
        $verifiedCount++;
    } elseif ($stCount === 'under_review') {
        $reviewCount++;
    } else {
        $pendingCount++;
    }
}

$allVerified       = ($totalCount > 0 && $verifiedCount === $totalCount);
$statusBadgePrefix = ($contentType === 'album') ? 'pac' : 'mc';
$reviewFn          = ($contentType === 'album') ? 'App.reviewAlbumCoActor' : 'App.reviewMovieCoActor';
?>
<?php if ($matchStageHeight): ?>
<div id="<?= View::e($panelId) ?>" class="ec-stage-side-match" data-content-type="<?= View::e($contentType) ?>" data-match-stage-height="1">
  <div class="card-kkc h-100 mb-0">
<?php else: ?>
<div id="<?= View::e($panelId) ?>" class="card-kkc mb-4" data-content-type="<?= View::e($contentType) ?>" data-match-stage-height="0">
<?php endif; ?>

    <div class="card-kkc-header flex-wrap gap-2">
      <div class="card-title">
        <?php if ($allVerified): ?>
          <i class="bi bi-person-check-fill text-success"></i>
          <span>Mitwirkende &amp; Gesichtsabgleich</span>
        <?php else: ?>
          <i class="bi bi-person-badge-fill ec-text-primary"></i>
          <span>Mitwirkende &amp; ID-Shot-Prüfung (18+)</span>
        <?php endif; ?>
      </div>

      <?php if ($totalCount === 0): ?>
        <span class="badge-kkc badge-kkc-neutral">Keine Personen</span>
      <?php elseif ($allVerified): ?>
        <span class="badge-kkc badge-kkc-success">
          <i class="bi bi-patch-check-fill me-1"></i><?= $totalCount === 1 ? 'Freigegeben · Solo' : ('Alle ' . $totalCount . ' freigegeben') ?>
        </span>
      <?php elseif ($rejectedCount > 0): ?>
        <span class="badge-kkc badge-kkc-danger">
          <i class="bi bi-exclamation-octagon-fill me-1"></i><?= $rejectedCount ?> abgelehnt
        </span>
      <?php elseif ($reviewCount > 0): ?>
        <span class="badge-kkc badge-kkc-warning">
          <i class="bi bi-hourglass-split me-1"></i><?= $reviewCount ?> von <?= $totalCount ?> Prüfung offen
        </span>
      <?php else: ?>
        <span class="badge-kkc badge-kkc-neutral">
          <?= $perfMode === 'solo_owner' ? 'Solo · Übergangsphase' : ($totalCount . ' Pers. · Übergangsphase') ?>
        </span>
      <?php endif; ?>
    </div>

    <div class="card-kkc-body ec-stage-persons-scroll">
      <?php if (empty($perfPersons)): ?>
        <div class="alert alert-secondary mb-0 py-2 small d-flex align-items-center gap-2">
          <i class="bi bi-info-circle-fill ec-text-primary flex-shrink-0"></i>
          <span>Keine mitwirkenden Personen für diesen Inhalt hinterlegt (Account: <?= View::e($merchantFullName) ?>).</span>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($perfPersons as $p):
              $pId           = abs((int)($p['id'] ?? 0));
              $isOwner       = !empty($p['is_owner']);
              $pStatus       = (string)($p['checked_status'] ?? 'pending_qr');
              $isReady       = !empty($p['is_ready_for_use']);
              $isAiReady     = !empty($p['ai_face_ready']);
              $verMethod     = (string)($p['verification_method'] ?? '');
              $displayLabel  = (string)($p['display_label'] ?? ('Person #' . $pId));
              $faceRefUrl    = trim((string)($p['face_ref_url'] ?? ''));
              $idShotUrl     = trim((string)($p['id_shot_url'] ?? ''));
              $idFrontUrl    = trim((string)($p['id_front_url'] ?? ''));
              $idBackUrl     = trim((string)($p['id_back_url'] ?? ''));
              $certUrl       = trim((string)($p['certificate_url'] ?? ''));
              $birthDateDe   = trim((string)($p['birth_date_de'] ?? ''));
              $ageVal        = $p['age'] ?? null;
              $idDocNumber   = trim((string)($p['id_doc_number'] ?? ''));
              $city          = trim((string)($p['city'] ?? ''));
              $rejReason     = trim((string)($p['rejection_reason'] ?? ''));
              $checkedOnRaw  = trim((string)($p['checked_on'] ?? ''));
              $checkedOnTs   = ($checkedOnRaw !== '' && !str_starts_with($checkedOnRaw, '0000') && strtotime($checkedOnRaw) !== false)
                  ? strtotime($checkedOnRaw)
                  : false;
              $checkedOnDe   = $checkedOnTs !== false ? date('d.m.Y H:i', $checkedOnTs) : '';

              $hasDocs = !empty($p['has_uploaded_docs'])
                  || !empty($p['has_id_shot'])
                  || $faceRefUrl !== ''
                  || $idShotUrl !== ''
                  || $idFrontUrl !== '';

              $primaryPhotoUrl           = $faceRefUrl !== '' ? $faceRefUrl : ($idShotUrl !== '' ? $idShotUrl : $idFrontUrl);
              $primaryPhotoIsFallback    = ($faceRefUrl === '' && $primaryPhotoUrl !== '');
              $primaryPhotoFallbackLabel = $idShotUrl !== '' ? 'ID-Shot (kein Selfie)' : 'Ausweisfoto';
              $primaryPhotoTitle         = $displayLabel . ' – ' . ($primaryPhotoIsFallback ? $primaryPhotoFallbackLabel : 'KI-Selfie (Frontal)');

              $statusBadgeId = $statusBadgePrefix . '-coactor-status-' . $pId;
              $collapseId    = $panelId . '-docs-' . $pId;

              $docThumbItems = [];
              if ($faceRefUrl !== '') {
                  $docThumbItems[] = ['url' => $faceRefUrl, 'label' => '1. KI-Selfie', 'title' => $displayLabel . ' – 1. KI-Selfie'];
              }
              if ($idShotUrl !== '') {
                  $docThumbItems[] = ['url' => $idShotUrl, 'label' => '2. ID-Shot', 'title' => $displayLabel . ' – 2. ID-Shot'];
              }
              if ($idFrontUrl !== '') {
                  $docThumbItems[] = ['url' => $idFrontUrl, 'label' => '3. Ausweis V.', 'title' => $displayLabel . ' – 3. Ausweis Vorderseite'];
              }
              if ($idBackUrl !== '') {
                  $docThumbItems[] = ['url' => $idBackUrl, 'label' => '4. Ausweis R.', 'title' => $displayLabel . ' – 4. Ausweis Rückseite'];
              }
          ?>

          <?php if ($pStatus === 'verified' && $hasDocs): ?>
            <?php $colClass = ($totalCount === 1) ? 'col-12' : 'col-sm-6'; ?>
            <div class="<?= $colClass ?>">
              <div class="ec-coactor-card is-verified <?= $totalCount === 1 ? 'p-3' : 'p-2 h-100 d-flex flex-column' ?>">

                <?php if ($totalCount === 1): ?>
                  <!-- Solo Verified: Großes Porträt links + Infos rechts -->
                  <div class="d-flex flex-column flex-sm-row gap-3 align-items-start">
                    <?php if ($primaryPhotoUrl !== ''): ?>
                    <div class="ec-face-compare-wrap ec-face-compare-solo"
                         role="button" tabindex="0"
                         data-lightbox-src="<?= View::e($primaryPhotoUrl) ?>"
                         data-lightbox-title="<?= View::e($primaryPhotoTitle) ?>"
                         onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)"
                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}"
                         title="Klicken für Großansicht">
                      <img class="ec-face-compare-img" src="<?= View::e($primaryPhotoUrl) ?>" alt="Selfie <?= View::e($displayLabel) ?>">
                      <span class="ec-face-role-pill <?= $isOwner ? 'is-owner' : 'is-partner' ?>"><?= $isOwner ? 'ICH' : 'DREHPARTNER' ?></span>
                      <span class="ec-face-status-pill"><i class="bi bi-patch-check-fill"></i> Freigegeben</span>
                      <?php if ($primaryPhotoIsFallback): ?>
                        <span class="ec-face-fallback-pill"><?= View::e($primaryPhotoFallbackLabel) ?></span>
                      <?php endif; ?>
                      <span class="ec-face-zoom-hint"><i class="bi bi-arrows-fullscreen"></i></span>
                    </div>
                    <?php else: ?>
                    <div class="ec-face-compare-wrap ec-face-compare-solo d-flex flex-column align-items-center justify-content-center text-light p-2 text-center" style="cursor: default;">
                      <span class="ec-face-role-pill <?= $isOwner ? 'is-owner' : 'is-partner' ?>"><?= $isOwner ? 'ICH' : 'DREHPARTNER' ?></span>
                      <span class="ec-face-status-pill"><i class="bi bi-patch-check-fill"></i> Freigegeben</span>
                      <i class="bi bi-file-earmark-check fs-1 opacity-75 mb-1"></i>
                      <span class="small opacity-75">Dokument freigegeben</span>
                    </div>
                    <?php endif; ?>

                    <div class="flex-grow-1 min-w-0 w-100">
                      <div class="fw-bold fs-6 text-body text-break mb-1"><?= View::e($displayLabel) ?></div>

                      <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                        <span class="badge-kkc badge-kkc-success" id="<?= View::e($statusBadgeId) ?>">
                          <i class="bi bi-patch-check-fill me-1"></i>Geprüft &amp; Freigegeben
                        </span>
                        <?php if ($isAiReady): ?>
                          <span class="badge-kkc badge-kkc-info"><i class="bi bi-qr-code-scan me-1"></i>Live-QR (KI-Ready)</span>
                        <?php elseif ($verMethod === 'manual_legacy'): ?>
                          <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-folder2-open me-1"></i>Alt-Dokumente</span>
                        <?php endif; ?>
                      </div>

                      <div class="small text-muted mb-2">
                        <?php if ($ageVal !== null): ?>
                          <span class="fw-semibold text-body"><?= (int)$ageVal ?> J.</span>
                          <?php if ($birthDateDe !== ''): ?> (Geb. <?= View::e($birthDateDe) ?>)<?php endif; ?>
                        <?php elseif ($birthDateDe !== ''): ?>
                          Geb.: <strong><?= View::e($birthDateDe) ?></strong>
                        <?php endif; ?>
                        <?php if ($city !== ''): ?>
                          <?= ($ageVal !== null || $birthDateDe !== '') ? ' &bull; ' : '' ?><?= View::e($city) ?>
                        <?php endif; ?>
                        <?php if ($checkedOnDe !== ''): ?>
                          <div class="mt-1"><i class="bi bi-check2-circle text-success me-1"></i>Geprüft am <?= View::e($checkedOnDe) ?></div>
                        <?php endif; ?>
                      </div>

                      <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <?php if ($isReady && $certUrl !== ''): ?>
                        <a href="<?= View::e($certUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc py-1 px-2">
                          <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>PDF-Freigabe
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-kkc py-1 px-2 ec-docs-toggle"
                                data-bs-toggle="collapse" data-bs-target="#<?= View::e($collapseId) ?>"
                                aria-expanded="false" aria-controls="<?= View::e($collapseId) ?>">
                          <i class="bi bi-card-heading me-1"></i>Nachweise &amp; Status <i class="bi bi-chevron-down ms-1 ec-docs-chevron"></i>
                        </button>
                      </div>
                    </div>
                  </div>

                <?php else: ?>
                  <!-- Mehrere Personen Verified: Großes Porträt oben + Kompaktdaten darunter -->
                  <?php if ($primaryPhotoUrl !== ''): ?>
                  <div class="ec-face-compare-wrap"
                       role="button" tabindex="0"
                       data-lightbox-src="<?= View::e($primaryPhotoUrl) ?>"
                       data-lightbox-title="<?= View::e($primaryPhotoTitle) ?>"
                       onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)"
                       onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}"
                       title="Klicken für Großansicht">
                    <img class="ec-face-compare-img" src="<?= View::e($primaryPhotoUrl) ?>" alt="Selfie <?= View::e($displayLabel) ?>">
                    <span class="ec-face-role-pill <?= $isOwner ? 'is-owner' : 'is-partner' ?>"><?= $isOwner ? 'ICH' : 'DREHPARTNER' ?></span>
                    <span class="ec-face-status-pill"><i class="bi bi-patch-check-fill"></i> OK</span>
                    <?php if ($primaryPhotoIsFallback): ?>
                      <span class="ec-face-fallback-pill"><?= View::e($primaryPhotoFallbackLabel) ?></span>
                    <?php endif; ?>
                    <span class="ec-face-zoom-hint"><i class="bi bi-arrows-fullscreen"></i></span>
                  </div>
                  <?php else: ?>
                  <div class="ec-face-compare-wrap d-flex flex-column align-items-center justify-content-center text-light p-2 text-center" style="cursor: default;">
                    <span class="ec-face-role-pill <?= $isOwner ? 'is-owner' : 'is-partner' ?>"><?= $isOwner ? 'ICH' : 'DREHPARTNER' ?></span>
                    <span class="ec-face-status-pill"><i class="bi bi-patch-check-fill"></i> OK</span>
                    <i class="bi bi-file-earmark-check fs-2 opacity-75 mb-1"></i>
                    <span class="small opacity-75">Dokument freigegeben</span>
                  </div>
                  <?php endif; ?>

                  <div class="pt-2 px-1 d-flex flex-column flex-grow-1">
                    <div class="fw-bold small text-body lh-sm mb-1 text-break"><?= View::e($displayLabel) ?></div>
                    <div class="small text-muted mb-2 d-flex flex-wrap align-items-center gap-1">
                      <?php if ($ageVal !== null): ?>
                        <span><?= (int)$ageVal ?> J.</span>
                        <span>&bull;</span>
                      <?php endif; ?>
                      <span class="text-success fw-semibold" id="<?= View::e($statusBadgeId) ?>">
                        <i class="bi bi-patch-check-fill me-1"></i>Freigegeben
                      </span>
                    </div>

                    <div class="mt-auto d-flex flex-wrap align-items-center justify-content-between gap-1 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                      <?php if ($isReady && $certUrl !== ''): ?>
                      <a href="<?= View::e($certUrl) ?>" target="_blank" rel="noopener"
                         class="btn btn-xs btn-outline-kkc py-0 px-2" style="font-size: 0.74rem;"
                         title="Unterschriebene Freigabe / PDF öffnen">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>PDF
                      </a>
                      <?php endif; ?>
                      <button type="button" class="btn btn-xs btn-outline-kkc py-0 px-2 ms-auto ec-docs-toggle"
                              style="font-size: 0.74rem;"
                              data-bs-toggle="collapse" data-bs-target="#<?= View::e($collapseId) ?>"
                              aria-expanded="false" aria-controls="<?= View::e($collapseId) ?>">
                        Nachweise <i class="bi bi-chevron-down ms-1 ec-docs-chevron"></i>
                      </button>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Aufklappbarer Nachweis- & Status-Drawer (standardmäßig eingeklappt) -->
                <div class="collapse mt-2 pt-2 border-top" id="<?= View::e($collapseId) ?>" style="border-color: var(--kkc-border) !important;">
                  <div class="small text-muted mb-2">
                    <?php if ($birthDateDe !== ''): ?>
                      Geb.: <strong><?= View::e($birthDateDe) ?></strong><?= $ageVal !== null ? ' (' . (int)$ageVal . ' J.)' : '' ?>
                    <?php endif; ?>
                    <?php if ($idDocNumber !== ''): ?>
                      <?= $birthDateDe !== '' ? ' &bull; ' : '' ?>Ausweis-Nr.: <strong><?= View::e($idDocNumber) ?></strong>
                    <?php endif; ?>
                    <?php if ($city !== ''): ?>
                      <?= ($birthDateDe !== '' || $idDocNumber !== '') ? ' &bull; ' : '' ?>Ort: <?= View::e($city) ?>
                    <?php endif; ?>
                    <?php if ($checkedOnDe !== ''): ?>
                      <div class="mt-1">Geprüft am <?= View::e($checkedOnDe) ?></div>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($docThumbItems)): ?>
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <?php foreach ($docThumbItems as $dt): ?>
                    <button type="button" class="ec-coactor-thumb-btn"
                            data-lightbox-src="<?= View::e($dt['url']) ?>"
                            data-lightbox-title="<?= View::e($dt['title']) ?>"
                            onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)">
                      <img src="<?= View::e($dt['url']) ?>" alt="<?= View::e($dt['label']) ?>">
                      <span><?= View::e($dt['label']) ?></span>
                    </button>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php if (!$readOnly): ?>
                  <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-1" style="border-color: var(--kkc-border) !important;">
                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size: 0.72rem;"
                            onclick="<?= $reviewFn ?>(<?= $contentId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reject', '<?= View::e($panelId) ?>')">
                      <i class="bi bi-x-circle me-1"></i>Nachträglich ablehnen
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;"
                            onclick="<?= $reviewFn ?>(<?= $contentId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reset_review', '<?= View::e($panelId) ?>')">
                      <i class="bi bi-arrow-counterclockwise me-1"></i>Auf „In Prüfung“
                    </button>
                  </div>
                  <?php endif; ?>
                </div>

              </div>
            </div>

          <?php elseif ($pStatus === 'under_review' && $hasDocs): ?>
            <!-- Prüfung offen: klar hervorgehoben mit großem Selfie + Prüfnachweisen + Aktionsbuttons -->
            <div class="col-12">
              <div class="ec-coactor-card is-review p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge <?= $isOwner ? 'bg-primary' : 'bg-secondary' ?>">
                      <?= $isOwner ? 'ICH (ACCOUNT-INHABER)' : 'DREHPARTNER' ?>
                    </span>
                    <strong class="text-body"><?= View::e($displayLabel) ?></strong>
                    <?php if ($isAiReady): ?>
                      <span class="badge-kkc badge-kkc-info"><i class="bi bi-qr-code-scan me-1"></i>Live-QR (KI-Ready)</span>
                    <?php elseif ($verMethod === 'manual_legacy'): ?>
                      <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-folder2-open me-1"></i>Alt-Dokumente</span>
                    <?php endif; ?>
                  </div>
                  <span class="badge-kkc badge-kkc-warning" id="<?= View::e($statusBadgeId) ?>">
                    <i class="bi bi-hourglass-split me-1"></i>Prüfung offen
                  </span>
                </div>

                <div class="small text-muted mb-2">
                  <?php if ($birthDateDe !== ''): ?>
                    Geb.: <strong><?= View::e($birthDateDe) ?></strong><?= $ageVal !== null ? ' (' . (int)$ageVal . ' J.)' : '' ?>
                  <?php endif; ?>
                  <?php if ($idDocNumber !== ''): ?>
                    <?= $birthDateDe !== '' ? ' &bull; ' : '' ?>Ausweis-Nr.: <strong><?= View::e($idDocNumber) ?></strong>
                  <?php endif; ?>
                  <?php if ($city !== ''): ?>
                    <?= ($birthDateDe !== '' || $idDocNumber !== '') ? ' &bull; ' : '' ?>Ort: <?= View::e($city) ?>
                  <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap align-items-start gap-2 mb-3">
                  <?php if ($faceRefUrl !== ''): ?>
                  <button type="button" class="ec-face-review-thumb"
                          data-lightbox-src="<?= View::e($faceRefUrl) ?>"
                          data-lightbox-title="<?= View::e($displayLabel . ' – 1. KI-Selfie') ?>"
                          onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)">
                    <img src="<?= View::e($faceRefUrl) ?>" alt="KI-Selfie <?= View::e($displayLabel) ?>">
                    <span>1. KI-Selfie</span>
                  </button>
                  <?php endif; ?>

                  <?php if ($idShotUrl !== ''): ?>
                  <button type="button" class="ec-coactor-thumb-btn"
                          data-lightbox-src="<?= View::e($idShotUrl) ?>"
                          data-lightbox-title="<?= View::e($displayLabel . ' – 2. ID-Shot') ?>"
                          onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)">
                    <img src="<?= View::e($idShotUrl) ?>" alt="ID-Shot">
                    <span>2. ID-Shot</span>
                  </button>
                  <?php endif; ?>

                  <?php if ($idFrontUrl !== ''): ?>
                  <button type="button" class="ec-coactor-thumb-btn"
                          data-lightbox-src="<?= View::e($idFrontUrl) ?>"
                          data-lightbox-title="<?= View::e($displayLabel . ' – 3. Ausweis Vorderseite') ?>"
                          onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)">
                    <img src="<?= View::e($idFrontUrl) ?>" alt="Ausweis V.">
                    <span>3. Ausweis V.</span>
                  </button>
                  <?php endif; ?>

                  <?php if ($idBackUrl !== ''): ?>
                  <button type="button" class="ec-coactor-thumb-btn"
                          data-lightbox-src="<?= View::e($idBackUrl) ?>"
                          data-lightbox-title="<?= View::e($displayLabel . ' – 4. Ausweis Rückseite') ?>"
                          onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)">
                    <img src="<?= View::e($idBackUrl) ?>" alt="Ausweis R.">
                    <span>4. Ausweis R.</span>
                  </button>
                  <?php endif; ?>

                  <?php if ($isReady && $certUrl !== ''): ?>
                  <a href="<?= View::e($certUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc align-self-center">
                    <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>Unterschriebene Freigabe / PDF
                  </a>
                  <?php endif; ?>
                </div>

                <?php if (!$readOnly): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <button type="button" class="btn btn-sm btn-success py-1 px-2"
                          onclick="<?= $reviewFn ?>(<?= $contentId ?>, <?= $merchantId ?>, <?= $pId ?>, 'verify', '<?= View::e($panelId) ?>')">
                    <i class="bi bi-check-lg me-1"></i>ID-Shot &amp; Person bestätigen
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2"
                          onclick="<?= $reviewFn ?>(<?= $contentId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reject', '<?= View::e($panelId) ?>')">
                    <i class="bi bi-x-lg me-1"></i>ID-Shot ablehnen
                  </button>
                </div>
                <?php endif; ?>
              </div>
            </div>

          <?php elseif ($pStatus === 'rejected'): ?>
            <!-- Abgelehnt: klar rot markiert mit Begründung + Korrektur-Buttons -->
            <div class="col-12">
              <div class="ec-coactor-card is-rejected p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge <?= $isOwner ? 'bg-primary' : 'bg-secondary' ?>">
                      <?= $isOwner ? 'ICH (ACCOUNT-INHABER)' : 'DREHPARTNER' ?>
                    </span>
                    <strong class="text-body"><?= View::e($displayLabel) ?></strong>
                    <?php if ($isAiReady): ?>
                      <span class="badge-kkc badge-kkc-info"><i class="bi bi-qr-code-scan me-1"></i>Live-QR (KI-Ready)</span>
                    <?php elseif ($verMethod === 'manual_legacy'): ?>
                      <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-folder2-open me-1"></i>Alt-Dokumente</span>
                    <?php endif; ?>
                  </div>
                  <span class="badge-kkc badge-kkc-danger" id="<?= View::e($statusBadgeId) ?>">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Abgelehnt – Korrektur nötig
                  </span>
                </div>

                <?php if ($rejReason !== ''): ?>
                <div class="alert alert-danger py-2 px-3 small mb-2">
                  <strong>Ablehnungsgrund:</strong> <?= View::e($rejReason) ?>
                </div>
                <?php endif; ?>

                <div class="small text-muted mb-2">
                  <?php if ($birthDateDe !== ''): ?>
                    Geb.: <strong><?= View::e($birthDateDe) ?></strong><?= $ageVal !== null ? ' (' . (int)$ageVal . ' J.)' : '' ?>
                  <?php endif; ?>
                  <?php if ($idDocNumber !== ''): ?>
                    <?= $birthDateDe !== '' ? ' &bull; ' : '' ?>Ausweis-Nr.: <strong><?= View::e($idDocNumber) ?></strong>
                  <?php endif; ?>
                  <?php if ($city !== ''): ?>
                    <?= ($birthDateDe !== '' || $idDocNumber !== '') ? ' &bull; ' : '' ?>Ort: <?= View::e($city) ?>
                  <?php endif; ?>
                </div>

                <?php if (!empty($docThumbItems) || $certUrl !== ''): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                  <?php foreach ($docThumbItems as $dt): ?>
                  <button type="button" class="ec-coactor-thumb-btn"
                          data-lightbox-src="<?= View::e($dt['url']) ?>"
                          data-lightbox-title="<?= View::e($dt['title']) ?>"
                          onclick="App.openImageLightbox(this.dataset.lightboxSrc, this.dataset.lightboxTitle)">
                    <img src="<?= View::e($dt['url']) ?>" alt="<?= View::e($dt['label']) ?>">
                    <span><?= View::e($dt['label']) ?></span>
                  </button>
                  <?php endforeach; ?>

                  <?php if ($certUrl !== ''): ?>
                  <a href="<?= View::e($certUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-kkc">
                    <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>PDF-Freigabe
                  </a>
                  <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!$readOnly): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <?php if ($hasDocs): ?>
                  <button type="button" class="btn btn-sm btn-outline-success py-1 px-2"
                          onclick="<?= $reviewFn ?>(<?= $contentId ?>, <?= $merchantId ?>, <?= $pId ?>, 'verify', '<?= View::e($panelId) ?>')">
                    <i class="bi bi-check-lg me-1"></i>Doch bestätigen
                  </button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2"
                          onclick="<?= $reviewFn ?>(<?= $contentId ?>, <?= $merchantId ?>, <?= $pId ?>, 'reset_review', '<?= View::e($panelId) ?>')">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Zurück auf „In Prüfung“
                  </button>
                </div>
                <?php endif; ?>
              </div>
            </div>

          <?php else: ?>
            <!-- Noch kein ID-Shot hochgeladen (Übergangsphase) -->
            <div class="col-12">
              <div class="ec-coactor-card is-pending p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge <?= $isOwner ? 'bg-primary' : 'bg-secondary' ?>">
                      <?= $isOwner ? 'ICH (ACCOUNT-INHABER)' : 'DREHPARTNER' ?>
                    </span>
                    <strong class="text-body"><?= View::e($displayLabel) ?></strong>
                  </div>
                  <span class="badge-kkc badge-kkc-neutral" id="<?= View::e($statusBadgeId) ?>">
                    <?= View::e((string)($p['status_label'] ?? 'Noch kein ID-Shot hochgeladen')) ?>
                  </span>
                </div>

                <div class="alert alert-secondary py-2 px-3 mb-0 small d-flex align-items-center gap-2">
                  <i class="bi bi-info-circle-fill ec-text-primary flex-shrink-0"></i>
                  <span><?= View::e($pendingDocsHint) ?></span>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

<?php if ($matchStageHeight): ?>
  </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
