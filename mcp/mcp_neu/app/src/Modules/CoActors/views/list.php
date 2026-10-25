<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * Hauptansicht: Drehpartner & ID-Check (#/co-actors)
 *
 * @var array $merchantIdent
 * @var array|null $ownerCoActor
 * @var array $coActors
 * @var array $usageCounts
 * @var int   $countTotal
 * @var int   $countVerified
 * @var int   $countPending
 * @var int   $countAiReady
 */

$ownerRealName    = trim((string)($merchantIdent['real_name'] ?? ''));
$ownerCreatorName = trim((string)($merchantIdent['creator_name'] ?? ''));
if ($ownerRealName === '' && is_array($ownerCoActor)) {
    $ownerRealName = trim(((string)($ownerCoActor['first_name'] ?? '')) . ' ' . ((string)($ownerCoActor['last_name'] ?? '')));
}
if ($ownerRealName === '') {
    $ownerRealName = 'Account-Inhaber';
}
$ownerReady = !empty($ownerCoActor['is_ready_for_use']);
$ownerId    = (int)($ownerCoActor['id'] ?? 0);
?>

<div class="container-fluid py-3" id="mcp-co-actors-page"
     data-owner-id="<?= $ownerId ?>"
     data-owner-real-name="<?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?>"
     data-owner-creator-name="<?= htmlspecialchars($ownerCreatorName, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Seiten-Kopfbereich -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1">
        <i class="bi bi-people-fill text-primary me-2"></i>Drehpartner &amp; ID-Check
      </h4>
      <p class="text-muted small mb-0">
        Verwalte hier deinen eigenen <strong>„Ich“-ID-Shot</strong> sowie alle <strong>weiteren Personen</strong>, die in deinen Filmen (und künftig Fotoalben) zu sehen sind – bequem per <strong>Smartphone-QR-Code &amp; Finger-Unterschrift</strong>.
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-primary fw-semibold"
              onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
        <i class="bi bi-person-badge me-1"></i> „Ich“-Button (Eigener ID-Shot)
      </button>
      <button type="button" class="btn btn-outline-secondary"
              onclick="window.MCPCoActors && window.MCPCoActors.openLegacyModal()">
        <i class="bi bi-folder2-open me-1"></i> Alte Dokumente hochladen
      </button>
      <button type="button" class="btn btn-kkc-primary px-3"
              onclick="window.MCPCoActors && window.MCPCoActors.openCreateQrModal()">
        <i class="bi bi-qr-code-scan me-1"></i> + Neuen Drehpartner anlegen (QR-Code)
      </button>
    </div>
  </div>

  <!-- Ausführlicher Hinweis zur kommenden ID-Shot-Pflicht & Übergangsphase -->
  <div class="card-kkc border-start border-4 border-warning mb-4 shadow-sm" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.09) 0%, rgba(2, 132, 199, 0.04) 100%);">
    <div class="card-kkc-body p-3 p-md-4">
      <div class="d-flex align-items-start gap-3">
        <div class="rounded-3 text-warning d-none d-sm-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 48px; height: 48px; background-color: rgba(245, 158, 11, 0.16);">
          <i class="bi bi-person-vcard-fill fs-3"></i>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <span class="badge-kkc badge-kkc-warning">
              <i class="bi bi-megaphone-fill"></i> Wichtige Vorankündigung &amp; Übergangsphase
            </span>
            <h5 class="fw-bold text-body mb-0" style="font-size: 1.02rem;">
              Demnächst Pflicht für alle Filme: Gültiger ID-Shot von dir &amp; jedem Drehpartner (auch rückwirkend)
            </h5>
          </div>
          <p class="small text-body mb-2" style="line-height: 1.55;">
            Aufgrund verschärfter gesetzlicher Vorgaben und Compliance-Richtlinien benötigen wir in Kürze von <strong>jedem Darsteller und jedem Drehpartner</strong>, der in deinen Videos zu sehen ist (inklusive dir selbst als Account-Inhaber), einen gültigen <strong>ID-Shot</strong> (Person hält ihren Ausweis gut lesbar neben ihr Gesicht) sowie eine unterschriebene Einverständniserklärung. Diese Nachweispflicht gilt <strong>auch rückwirkend für alle bereits hochgeladenen Filme</strong>.
          </p>
          <p class="small text-muted mb-0" style="line-height: 1.55;">
            <strong class="text-body">Unser Tipp – starte schon jetzt in der Übergangsphase:</strong>
            Du kannst deine bereits veröffentlichten Filme aktuell wie gewohnt weiterverkaufen und auch neue Filme <strong>übergangsweise noch ohne hinterlegten ID-Shot hochladen</strong>. Wir empfehlen dir jedoch dringend, die ID-Shots deiner Drehpartner (per <strong>Smartphone-QR-Code</strong> oder über <strong>„Alte Dokumente hochladen“</strong>) <strong>schon jetzt nach und nach hier hochzuladen</strong> und in deinen Filmen zuzuordnen. So stellst du sicher, dass deine bestehenden und in der Übergangszeit hochgeladenen Filme später nach Ablauf der Übergangsfrist <strong>nahtlos online bleiben und nicht offline geschaltet werden müssen</strong>!
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- KPI-Übersicht -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card kkc-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 <?= $ownerReady ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning' ?>">
            <i class="bi bi-person-check-fill fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">Mein „Ich“-ID-Shot</div>
            <div class="fw-bold fs-6">
              <?= $ownerReady ? htmlspecialchars((string)($ownerCoActor['status_label'] ?? 'Hinterlegt'), ENT_QUOTES, 'UTF-8') : 'Noch ausstehend' ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card kkc-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-people fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">Weitere Drehpartner</div>
            <div class="fw-bold fs-5"><?= (int)$countTotal ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card kkc-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
            <i class="bi bi-patch-check-fill fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">Geprüft / Eingereicht</div>
            <div class="fw-bold fs-5"><?= (int)$countVerified ?> <span class="text-muted fw-normal small">(+ <?= (int)$countPending ?> in Prüfung)</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card kkc-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info">
            <i class="bi bi-cpu-fill fs-4"></i>
          </div>
          <div>
            <div class="text-muted small">KI-Gesichtsreferenz bereit</div>
            <div class="fw-bold fs-5"><?= (int)$countAiReady ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       1. BEREICH: MEIN EIGENER "ICH"-EINTRAG (ACCOUNT-INHABER)
       ======================================================================== -->
  <div class="card kkc-card border-0 shadow-sm mb-4 border-start border-4 <?= $ownerReady ? 'border-success' : 'border-warning' ?>">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary px-2 py-1">ICH SELBST</span>
        <h6 class="fw-bold mb-0">
          <?= htmlspecialchars((string)($ownerCoActor['display_label'] ?? $ownerRealName), ENT_QUOTES, 'UTF-8') ?>
        </h6>
        <span class="badge <?= htmlspecialchars((string)($ownerCoActor['status_badge_cls'] ?? 'bg-warning text-dark'), ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars((string)($ownerCoActor['status_label'] ?? 'Wartet auf QR-Scan'), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if (!empty($ownerCoActor['ai_face_ready'])): ?>
          <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
            <i class="bi bi-cpu me-1"></i>KI-Gesichtsreferenz aktiv
          </span>
        <?php endif; ?>
      </div>

      <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if ($ownerReady && !empty($ownerCoActor['certificate_url'])): ?>
          <a href="<?= htmlspecialchars((string)$ownerCoActor['certificate_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
             class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Mein PDF / Beleg drucken
          </a>
        <?php endif; ?>
        <button type="button" class="btn btn-sm <?= $ownerReady ? 'btn-outline-primary' : 'btn-warning fw-bold text-dark' ?>"
                onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
          <i class="bi bi-qr-code-scan me-1"></i>
          <?= $ownerReady ? '„Ich“-ID-Shot per Handy-QR erneuern' : 'Jetzt meinen „Ich“-ID-Shot per Handy-QR aufnehmen' ?>
        </button>
      </div>
    </div>

    <div class="card-body p-4">
      <?php if (!$ownerReady): ?>
        <div class="row align-items-center g-3">
          <div class="col-12 col-lg-8">
            <h6 class="fw-bold text-dark mb-1">
              <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
              Bitte hinterlege einmalig deinen eigenen „Ich“-ID-Shot!
            </h6>
            <p class="small text-muted mb-0">
              Auch wenn du als Account-Inhaber (<strong><?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?></strong>) selbst vor der Kamera stehst, benötigen wir vor dem Hochladen deiner Filme einmalig dein frontales Referenz-Selfie, deinen ID-Shot mit Ausweis neben dem Gesicht sowie deine digitale Unterschrift. Klicke einfach rechts auf den Button, scanne den QR-Code mit deinem Smartphone und erledige alles in unter 60 Sekunden direkt auf deinem Handy!
            </p>
          </div>
          <div class="col-12 col-lg-4 text-lg-end">
            <button type="button" class="btn btn-kkc-primary px-4"
                    onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
              <i class="bi bi-qr-code-scan me-1"></i> „Ich“-QR-Code jetzt anzeigen
            </button>
          </div>
        </div>
      <?php else: ?>
        <div class="row align-items-center g-3">
          <div class="col-12 col-md-7">
            <div class="small text-muted mb-2">
              Dein eigener Darsteller-Nachweis ist hinterlegt und wird automatisch verwendet, sobald du bei einem Film <strong>„Bin nur ich im Film zu sehen“</strong> oder in der Mehrfachauswahl <strong>„Ich (<?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?>)“</strong> auswählst.
            </div>
            <div class="d-flex flex-wrap gap-3 small">
              <div><strong>Klarname:</strong> <?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?></div>
              <?php if ($ownerCreatorName !== ''): ?>
                <div><strong>Creator-Name:</strong> <?= htmlspecialchars($ownerCreatorName, ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <?php if (!empty($ownerCoActor['submitted_datetime']) && !str_starts_with((string)$ownerCoActor['submitted_datetime'], '0000')): ?>
                <div><strong>Aufgenommen am:</strong> <?= date('d.m.Y H:i', (int)strtotime((string)$ownerCoActor['submitted_datetime'])) ?> Uhr</div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-12 col-md-5">
            <div class="d-flex justify-content-md-end gap-2 flex-wrap">
              <?php if (!empty($ownerCoActor['face_ref_url'])): ?>
                <a href="<?= htmlspecialchars((string)$ownerCoActor['face_ref_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                   class="text-decoration-none text-center p-1 border rounded-3 bg-light" style="width: 82px;">
                  <img src="<?= htmlspecialchars((string)$ownerCoActor['face_ref_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Selfie"
                       class="rounded-2 d-block mx-auto" style="width: 72px; height: 54px; object-fit: cover;">
                  <span class="d-block text-muted mt-1" style="font-size: 0.65rem;">KI-Selfie</span>
                </a>
              <?php endif; ?>
              <?php if (!empty($ownerCoActor['id_shot_url'])): ?>
                <a href="<?= htmlspecialchars((string)$ownerCoActor['id_shot_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                   class="text-decoration-none text-center p-1 border rounded-3 bg-light" style="width: 82px;">
                  <img src="<?= htmlspecialchars((string)$ownerCoActor['id_shot_url'], ENT_QUOTES, 'UTF-8') ?>" alt="ID-Shot"
                       class="rounded-2 d-block mx-auto" style="width: 72px; height: 54px; object-fit: cover;">
                  <span class="d-block text-muted mt-1" style="font-size: 0.65rem;">ID-Shot</span>
                </a>
              <?php endif; ?>
              <?php if (!empty($ownerCoActor['id_front_url'])): ?>
                <a href="<?= htmlspecialchars((string)$ownerCoActor['id_front_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                   class="text-decoration-none text-center p-1 border rounded-3 bg-light" style="width: 82px;">
                  <img src="<?= htmlspecialchars((string)$ownerCoActor['id_front_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Ausweis"
                       class="rounded-2 d-block mx-auto" style="width: 72px; height: 54px; object-fit: cover;">
                  <span class="d-block text-muted mt-1" style="font-size: 0.65rem;">Ausweis</span>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ========================================================================
       2. BEREICH: WEITERE DREHPARTNER (CREATOR-NAME + KLARNAME IN KLAMMERN)
       ======================================================================== -->
  <div class="card kkc-card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h6 class="fw-bold mb-0">
          <i class="bi bi-people text-primary me-2"></i>Weitere Drehpartner (<?= count($coActors) ?>)
        </h6>
        <div class="text-muted small">
          Alle Personen, die du hier anlegst, stehen dir beim Film-Upload und in der Film-Bearbeitung sofort zur Auswahl.
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="window.MCPCoActors && window.MCPCoActors.openLegacyModal()">
          <i class="bi bi-folder2-open me-1"></i> Alte Dokumente (vergangene Filme)
        </button>
        <button type="button" class="btn btn-sm btn-kkc-primary"
                onclick="window.MCPCoActors && window.MCPCoActors.openCreateQrModal()">
          <i class="bi bi-qr-code-scan me-1"></i> + Neuen Drehpartner anlegen
        </button>
      </div>
    </div>

    <div class="card-body p-0">
      <?php if (empty($coActors)): ?>
        <div class="p-5 text-center">
          <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3"
               style="width: 68px; height: 68px;">
            <i class="bi bi-qr-code-scan fs-2"></i>
          </div>
          <h5 class="fw-bold text-dark">Noch keine weiteren Drehpartner angelegt</h5>
          <p class="text-muted small mx-auto mb-4" style="max-width: 560px;">
            Wenn in deinen Filmen weitere Personen zu sehen sind, lege sie hier an. Du erhältst sofort einen <strong>QR-Code</strong>, den dein Drehpartner mit seinem Smartphone scannt, um seinen ID-Shot aufzunehmen und mit dem Finger auf dem Display zu unterschreiben.
          </p>
          <div class="d-flex justify-content-center flex-wrap gap-2">
            <button type="button" class="btn btn-kkc-primary px-4"
                    onclick="window.MCPCoActors && window.MCPCoActors.openCreateQrModal()">
              <i class="bi bi-qr-code-scan me-1"></i> Ersten Drehpartner per QR-Code anlegen
            </button>
            <button type="button" class="btn btn-outline-secondary"
                    onclick="window.MCPCoActors && window.MCPCoActors.openLegacyModal()">
              <i class="bi bi-folder2-open me-1"></i> Vorhandene Dokumente für älteren Film hochladen
            </button>
          </div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th class="ps-4">Drehpartner (Creator-Name &amp; Klarname)</th>
                <th>Erfassungs-Methode</th>
                <th>Aufnahmen / Dokumente</th>
                <th>Verknüpfte Filme</th>
                <th>Prüfstatus</th>
                <th class="text-end pe-4">Aktionen</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($coActors as $ca): ?>
                <?php
                  $caId      = (int)$ca['id'];
                  $usedCount = (int)($usageCounts[$caId] ?? 0);
                  $isLegacy  = ((string)$ca['verification_method'] === 'manual_legacy');
                  $caReady   = !empty($ca['is_ready_for_use']);
                ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold text-dark"><?= htmlspecialchars((string)$ca['display_label'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted small">
                      <?php if (!empty($ca['birth_date'])): ?>
                        Geb.: <?= htmlspecialchars(date('d.m.Y', (int)strtotime((string)$ca['birth_date'])), ENT_QUOTES, 'UTF-8') ?> ·
                      <?php endif; ?>
                      Angelegt am <?= date('d.m.Y', (int)strtotime((string)$ca['create_datetime'])) ?>
                    </div>
                    <?php if ($ca['checked_status'] === 'rejected' && !empty($ca['rejection_reason'])): ?>
                      <div class="text-danger small mt-1">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>Grund: <?= htmlspecialchars((string)$ca['rejection_reason'], ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                  </td>

                  <td>
                    <?php if (!empty($ca['ai_face_ready'])): ?>
                      <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                        <i class="bi bi-phone-fill me-1"></i>Smartphone Live-QR (KI-Ready)
                      </span>
                    <?php elseif ($isLegacy): ?>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                        <i class="bi bi-folder2-open me-1"></i>Alt-Dokumente (Manuell)
                      </span>
                      <div class="mt-1">
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small"
                                onclick="window.MCPCoActors && window.MCPCoActors.openExistingQrModal(<?= $caId ?>)">
                          <i class="bi bi-qr-code me-1"></i>Beim Wiedersehen per QR upgraden
                        </button>
                      </div>
                    <?php else: ?>
                      <span class="badge bg-light text-dark border">
                        <i class="bi bi-qr-code me-1"></i>QR-Code bereit
                      </span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <?php if (!empty($ca['face_ref_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$ca['face_ref_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           class="badge bg-light text-dark border text-decoration-none">
                          <i class="bi bi-person-bounding-box text-primary me-1"></i>KI-Selfie
                        </a>
                      <?php endif; ?>
                      <?php if (!empty($ca['id_shot_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$ca['id_shot_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           class="badge bg-light text-dark border text-decoration-none">
                          <i class="bi bi-person-vcard text-primary me-1"></i>ID-Shot
                        </a>
                      <?php endif; ?>
                      <?php if (!empty($ca['id_front_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$ca['id_front_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           class="badge bg-light text-dark border text-decoration-none">
                          <i class="bi bi-credit-card-2-front text-primary me-1"></i>Ausweis
                        </a>
                      <?php endif; ?>
                      <?php if ($caReady && !empty($ca['certificate_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$ca['certificate_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-decoration-none">
                          <i class="bi bi-file-earmark-pdf-fill me-1"></i>Einverständnis (PDF/Druck)
                        </a>
                      <?php endif; ?>
                      <?php if (!$caReady): ?>
                        <span class="text-muted small">Noch keine Aufnahmen übertragen</span>
                      <?php endif; ?>
                    </div>
                  </td>

                  <td>
                    <span class="badge bg-light text-dark border">
                      <i class="bi bi-film me-1"></i><?= $usedCount ?> Film<?= $usedCount === 1 ? '' : 'e' ?>
                    </span>
                  </td>

                  <td>
                    <span class="badge <?= htmlspecialchars((string)$ca['status_badge_cls'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars((string)$ca['status_label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>

                  <td class="text-end pe-4">
                    <div class="d-inline-flex align-items-center gap-1">
                      <button type="button" class="btn btn-sm btn-outline-primary"
                              title="QR-Code für Smartphone-Aufnahme anzeigen"
                              onclick="window.MCPCoActors && window.MCPCoActors.openExistingQrModal(<?= $caId ?>)">
                        <i class="bi bi-qr-code-scan me-1"></i> QR-Code
                      </button>
                      <?php if ($caReady && !empty($ca['certificate_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$ca['certificate_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           class="btn btn-sm btn-outline-secondary" title="Unterschriebene Freigabe drucken / als PDF speichern">
                          <i class="bi bi-printer"></i>
                        </a>
                      <?php endif; ?>
                      <?php if ($usedCount === 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                title="Drehpartner entfernen"
                                onclick="window.MCPCoActors && window.MCPCoActors.deleteCoActor(<?= $caId ?>, '<?= htmlspecialchars(addslashes((string)$ca['display_label']), ENT_QUOTES, 'UTF-8') ?>')">
                          <i class="bi bi-trash"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/co_actor_modals.php'; ?>
