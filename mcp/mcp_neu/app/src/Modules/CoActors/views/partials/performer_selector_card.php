<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * Wiederverwendbares Partial für die Darsteller- & Drehpartner-Auswahl
 * Eingebunden in:
 * - #/movie-upload (Schritt 1)
 * - #/movie (Film-Detailansicht & Bearbeitung)
 * - Vorbereitet für künftige Fotoalben (#/photo-album)
 *
 * Erwartete Variablen:
 * @var array  $merchantIdent      Aus CoActorManager::getMerchantIdentity()
 * @var array  $ownerCoActor       Eigener "Ich"-Eintrag (is_owner = 1)
 * @var array  $coActors           Alle weiteren Drehpartner (is_owner = 0)
 * @var array  $contentPerformers  Aktuelle Auswahl: ['performer_mode' => 'solo_owner'|'multiple', 'owner_in_content' => 0|1, 'co_actor_ids' => int[]]
 * @var string $contentLabel       Standard: 'Film' (später auch 'Fotoalbum')
 */

$contentLabel     = isset($contentLabel) && $contentLabel !== '' ? (string)$contentLabel : 'Film';
$ownerRealName    = trim((string)($merchantIdent['real_name'] ?? ''));
if ($ownerRealName === '') {
    $ownerRealName = trim(((string)($ownerCoActor['first_name'] ?? '')) . ' ' . ((string)($ownerCoActor['last_name'] ?? '')));
}
if ($ownerRealName === '') {
    $ownerRealName = 'Account-Inhaber';
}
$ownerCreatorName = trim((string)($merchantIdent['creator_name'] ?? ''));
$ownerDisplayLabel = $ownerCreatorName !== '' && strcasecmp($ownerCreatorName, $ownerRealName) !== 0
    ? $ownerCreatorName . ' (' . $ownerRealName . ')'
    : $ownerRealName;

$performerMode   = (string)($contentPerformers['performer_mode'] ?? 'solo_owner');
if (!in_array($performerMode, ['solo_owner', 'multiple'], true)) {
    $performerMode = 'solo_owner';
}
$ownerInContent  = isset($contentPerformers['owner_in_content']) ? (int)$contentPerformers['owner_in_content'] : 1;
$selectedIds     = isset($contentPerformers['co_actor_ids']) && is_array($contentPerformers['co_actor_ids'])
    ? array_map('intval', $contentPerformers['co_actor_ids'])
    : [];

$ownerReady      = !empty($ownerCoActor['is_ready_for_use']);
$ownerId         = (int)($ownerCoActor['id'] ?? 0);
$ownerStatus     = (string)($ownerCoActor['checked_status'] ?? 'pending_qr');
?>

<div class="card kkc-card border-0 shadow-sm mb-4" id="mcp-performer-selector-card"
     data-owner-id="<?= $ownerId ?>"
     data-owner-real-name="<?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?>"
     data-owner-creator-name="<?= htmlspecialchars($ownerCreatorName, ENT_QUOTES, 'UTF-8') ?>">
  <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h6 class="fw-bold mb-0">
        <i class="bi bi-people-fill text-primary me-2"></i>Darsteller &amp; Drehpartner-Angabe
      </h6>
      <div class="text-muted small mt-1">
        Gib bitte wahrheitsgemäß an, wer in diesem <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8') ?> zu sehen ist. Deinen eigenen ID-Shot sowie Drehpartner-Freigaben kannst du jederzeit bequem per Smartphone-QR-Code hinterlegen.
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-primary"
              onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
        <i class="bi bi-person-badge me-1"></i> „Ich“-Button (Eigener ID-Shot)
      </button>
      <a href="#/co-actors" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-box-arrow-up-right me-1"></i> Drehpartner-Verwaltung
      </a>
    </div>
  </div>

  <div class="card-body p-4">
    <!-- Status-Hinweis zum eigenen "Ich"-Eintrag des Account-Inhabers -->
    <div id="mcp-owner-self-status-banner" class="mb-3">
      <?php if (!$ownerReady): ?>
        <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between flex-wrap gap-3 mb-0 shadow-sm">
          <div class="d-flex align-items-start gap-3">
            <i class="bi bi-person-bounding-box fs-3 text-warning flex-shrink-0"></i>
            <div class="small">
              <strong class="d-block text-dark mb-1">Empfehlung: Deinen eigenen „Ich“-ID-Shot hinterlegen</strong>
              Du kannst deinen <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8') ?> während der aktuellen Übergangsphase bereits problemlos hochladen und zur Freischaltung einreichen. Wir empfehlen dir aber, deinen eigenen ID-Shot + Finger-Unterschrift für <strong><?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?></strong> schon jetzt einmalig per Smartphone-QR-Code aufzunehmen.
            </div>
          </div>
          <button type="button" class="btn btn-warning fw-bold btn-sm px-3 text-dark flex-shrink-0"
                  onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
            <i class="bi bi-qr-code-scan me-1"></i> „Ich“ jetzt per Handy-QR verifizieren
          </button>
        </div>
      <?php else: ?>
        <div class="p-2 px-3 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="small d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <span>
              <strong>Dein eigener „Ich“-ID-Shot (<?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?>) ist hinterlegt</strong>
              <span class="badge <?= htmlspecialchars((string)($ownerCoActor['status_badge_cls'] ?? 'bg-success'), ENT_QUOTES, 'UTF-8') ?> ms-1">
                <?= htmlspecialchars((string)($ownerCoActor['status_label'] ?? 'Bereit'), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <?php if (!empty($ownerCoActor['certificate_url'])): ?>
              <a href="<?= htmlspecialchars((string)$ownerCoActor['certificate_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-1 px-2 small">
                <i class="bi bi-file-earmark-pdf me-1"></i> Mein PDF/Beleg
              </a>
            <?php endif; ?>
            <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 small"
                    onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
              <i class="bi bi-qr-code me-1"></i> „Ich“-ID-Shot erneuern
            </button>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Hauptfrage: Bin nur ich zu sehen oder auch andere Personen? -->
    <label class="form-label fw-bold text-dark mb-2">
      Wer ist in diesem <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8') ?> zu sehen? <span class="text-danger">*</span>
    </label>

    <div class="row g-3 mb-3">
      <!-- Option 1: Bin nur ich im Film zu sehen? (Klarname) -->
      <div class="col-12 col-md-6">
        <label class="d-block p-3 rounded-3 border h-100 cursor-pointer transition-all performer-mode-card <?= $performerMode === 'solo_owner' ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : 'bg-light' ?>"
               id="card-performer-mode-solo" style="cursor: pointer;">
          <div class="d-flex align-items-start gap-3">
            <input class="form-check-input mt-1 flex-shrink-0" type="radio"
                   name="performer_mode" id="radio-performer-solo" value="solo_owner"
                   <?= $performerMode === 'solo_owner' ? 'checked' : '' ?>
                   onchange="window.MCPCoActors && window.MCPCoActors.onModeChange(this.value)">
            <div>
              <div class="fw-bold text-dark">
                <i class="bi bi-person-check-fill text-primary me-1"></i>
                Bin nur ich im <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8') ?> zu sehen?
                <span class="text-primary">(<?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?>)</span>
              </div>
              <div class="small text-muted mt-1">
                Wähle diese Option, wenn ausschließlich du allein im Video zu sehen bist und keine weiteren Personen auftauchen.
              </div>
            </div>
          </div>
        </label>
      </div>

      <!-- Option 2: Sind (auch) andere Personen im Film zu sehen? -->
      <div class="col-12 col-md-6">
        <label class="d-block p-3 rounded-3 border h-100 cursor-pointer transition-all performer-mode-card <?= $performerMode === 'multiple' ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : 'bg-light' ?>"
               id="card-performer-mode-multiple" style="cursor: pointer;">
          <div class="d-flex align-items-start gap-3">
            <input class="form-check-input mt-1 flex-shrink-0" type="radio"
                   name="performer_mode" id="radio-performer-multiple" value="multiple"
                   <?= $performerMode === 'multiple' ? 'checked' : '' ?>
                   onchange="window.MCPCoActors && window.MCPCoActors.onModeChange(this.value)">
            <div>
              <div class="fw-bold text-dark">
                <i class="bi bi-people-fill text-primary me-1"></i>
                Sind auch andere Personen im <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8') ?> zu sehen?
              </div>
              <div class="small text-muted mt-1">
                Wähle diese Option, sobald mindestens ein weiterer Drehpartner (oder nur andere Personen) im Video zu sehen sind.
              </div>
            </div>
          </div>
        </label>
      </div>
    </div>

    <!-- Aufklappbarer Auswahlbereich für "Sind auch andere Personen im Film zu sehen?" -->
    <div id="mcp-multiple-performers-panel" class="<?= $performerMode === 'multiple' ? '' : 'd-none' ?> mt-3 p-3 rounded-4 border bg-light">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
          <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-check2-square text-primary me-1"></i>Welche Personen sind im <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8') ?> zu sehen?
          </h6>
          <div class="text-muted small">
            Wähle alle Personen aus, die im Video auftauchen. Neue Drehpartner kannst du direkt hier per Smartphone-QR-Code anlegen.
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-kkc-primary"
                  onclick="window.MCPCoActors && window.MCPCoActors.openCreateQrModal()">
            <i class="bi bi-qr-code-scan me-1"></i> + Neuen Drehpartner anlegen (QR-Code)
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary"
                  onclick="window.MCPCoActors && window.MCPCoActors.openLegacyModal()">
            <i class="bi bi-folder2-open me-1"></i> Alte Dokumente hochladen
          </button>
        </div>
      </div>

      <!-- 1. Auswahlzeile: "Ich (Klarname vom Partner-Account)" -->
      <div class="p-3 rounded-3 border bg-white mb-2 shadow-xs d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="form-check mb-0 d-flex align-items-center gap-2">
          <input class="form-check-input" type="checkbox"
                 name="owner_in_content" id="chk-owner-in-content" value="1"
                 <?= $ownerInContent === 1 ? 'checked' : '' ?>
                 style="width: 1.2rem; height: 1.2rem;">
          <label class="form-check-label fw-bold text-dark ms-1" for="chk-owner-in-content" style="cursor: pointer;">
            <span class="badge bg-primary me-1">ICH</span>
            Ich (<?= htmlspecialchars($ownerRealName, ENT_QUOTES, 'UTF-8') ?>)
            <?php if ($ownerCreatorName !== '' && strcasecmp($ownerCreatorName, $ownerRealName) !== 0): ?>
              <span class="text-muted fw-normal small">– Creator: <?= htmlspecialchars($ownerCreatorName, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </label>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge <?= htmlspecialchars((string)($ownerCoActor['status_badge_cls'] ?? 'bg-warning text-dark'), ENT_QUOTES, 'UTF-8') ?>" id="chk-owner-status-badge">
            <?= htmlspecialchars((string)($ownerCoActor['status_label'] ?? 'Wartet auf QR-Scan'), ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php if (!$ownerReady): ?>
            <button type="button" class="btn btn-xs btn-warning fw-semibold py-1 px-2 small"
                    onclick="window.MCPCoActors && window.MCPCoActors.openOwnerQrModal()">
              <i class="bi bi-qr-code-scan me-1"></i> „Ich“ jetzt verifizieren
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- 2. Liste der weiteren Drehpartner -->
      <div class="small fw-semibold text-muted mt-3 mb-2">
        Weitere Drehpartner auswählen (Anzeige als <code>Creator-Name (Klarname)</code>):
      </div>

      <div id="mcp-co-actors-checklist-body" class="d-flex flex-column gap-2"
           data-selected-ids="<?= htmlspecialchars(json_encode($selectedIds), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (empty($coActors)): ?>
          <div class="p-4 rounded-3 border border-dashed bg-white text-center text-muted" id="mcp-no-co-actors-placeholder">
            <i class="bi bi-person-plus fs-2 text-primary d-block mb-2"></i>
            <div class="fw-semibold text-dark">Du hast noch keine weiteren Drehpartner angelegt.</div>
            <div class="small mb-3">
              Klicke auf <strong>„+ Neuen Drehpartner anlegen (QR-Code)“</strong>, lass deinen Drehpartner den QR-Code mit seinem Smartphone scannen und direkt auf dem Handy-Display unterschreiben!
            </div>
            <div class="d-flex justify-content-center flex-wrap gap-2">
              <button type="button" class="btn btn-sm btn-kkc-primary"
                      onclick="window.MCPCoActors && window.MCPCoActors.openCreateQrModal()">
                <i class="bi bi-qr-code-scan me-1"></i> + Neuen Drehpartner per QR-Code anlegen
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary"
                      onclick="window.MCPCoActors && window.MCPCoActors.openLegacyModal()">
                <i class="bi bi-folder2-open me-1"></i> Vorhandene Dokumente für älteren Film hochladen
              </button>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($coActors as $ca): ?>
            <?php
              $caId       = (int)$ca['id'];
              $isChecked  = in_array($caId, $selectedIds, true);
              $caReady    = !empty($ca['is_ready_for_use']);
              $caLabel    = (string)($ca['display_label'] ?? '');
              $caMethod   = (string)($ca['verification_method'] ?? 'qr_live');
            ?>
            <div class="p-3 rounded-3 border bg-white d-flex align-items-center justify-content-between flex-wrap gap-2 co-actor-select-row <?= $isChecked ? 'border-primary' : '' ?>"
                 data-co-actor-id="<?= $caId ?>">
              <div class="form-check mb-0 d-flex align-items-center gap-2">
                <input class="form-check-input co-actor-checkbox" type="checkbox"
                       name="co_actor_ids[]" id="chk-co-actor-<?= $caId ?>" value="<?= $caId ?>"
                       <?= $isChecked ? 'checked' : '' ?>
                       style="width: 1.2rem; height: 1.2rem;">
                <label class="form-check-label ms-1" for="chk-co-actor-<?= $caId ?>" style="cursor: pointer;">
                  <span class="fw-bold text-dark"><?= htmlspecialchars($caLabel, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (!empty($ca['ai_face_ready'])): ?>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-1" style="font-size: 0.68rem;">
                      <i class="bi bi-cpu me-1"></i>KI-Ready
                    </span>
                  <?php elseif ($caMethod === 'manual_legacy'): ?>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border ms-1" style="font-size: 0.68rem;"
                          title="Manuell hochgeladene Alt-Dokumente. Beim nächsten Wiedersehen einfach per QR-Code upgraden!">
                      <i class="bi bi-file-earmark-arrow-up me-1"></i>Alt-Dokumente
                    </span>
                  <?php endif; ?>
                </label>
              </div>

              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge <?= htmlspecialchars((string)$ca['status_badge_cls'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string)$ca['status_label'], ENT_QUOTES, 'UTF-8') ?>
                </span>

                <?php if (!$caReady): ?>
                  <button type="button" class="btn btn-xs btn-primary py-1 px-2 small"
                          onclick="window.MCPCoActors && window.MCPCoActors.openExistingQrModal(<?= $caId ?>)">
                    <i class="bi bi-qr-code-scan me-1"></i> QR-Code scannen lassen
                  </button>
                <?php elseif ($caMethod === 'manual_legacy'): ?>
                  <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 small"
                          title="Du siehst diesen Drehpartner wieder? Jetzt frischen Live-ID-Shot per QR-Code aufnehmen!"
                          onclick="window.MCPCoActors && window.MCPCoActors.openExistingQrModal(<?= $caId ?>)">
                    <i class="bi bi-qr-code me-1"></i> Wiedersehen: QR-Upgrade
                  </button>
                <?php endif; ?>

                <?php if (!empty($ca['certificate_url']) && $caReady): ?>
                  <a href="<?= htmlspecialchars((string)$ca['certificate_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                     class="btn btn-xs btn-outline-secondary py-1 px-2 small" title="Einverständniserklärung anzeigen / als PDF drucken">
                    <i class="bi bi-file-earmark-pdf"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
