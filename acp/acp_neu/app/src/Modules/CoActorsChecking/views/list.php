<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/**
 * @var array<int, array<string, mixed>> $actors
 * @var array<string, int> $counts
 * @var array<int, array<int, array<string, mixed>>> $linkedContentByActor
 * @var array<string, int> $switcherCounts
 * @var array<string, string> $docTypeLabels
 * @var array<int, array{title: string, text: string}> $rejectionTemplates
 * @var array<string, string> $filters
 */

$curStatus = (string)($filters['status'] ?? 'under_review');
$curRole   = (string)($filters['role'] ?? 'all');
$curMethod = (string)($filters['method'] ?? 'all');
$curSort   = (string)($filters['sort'] ?? 'priority');
$curSearch = (string)($filters['q'] ?? '');
?>
<div class="container-fluid py-3" data-page-width="full">

  <!-- ========================================================================
       MODULÜBERGREIFENDE CONTENT-ÜBERSICHT (SWITCHER)
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div class="d-flex flex-wrap align-items-center gap-1">
      <a href="#/movies-checking" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-film me-1"></i> Filme prüfen
        <span class="badge bg-warning text-dark ms-1"><?= (int)($switcherCounts['movies_checking'] ?? 0) ?></span>
      </a>
      <a href="#/photo-albums-checking" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-images me-1"></i> Fotoalben prüfen
        <span class="badge bg-warning text-dark ms-1"><?= (int)($switcherCounts['albums_checking'] ?? 0) ?></span>
      </a>
      <a href="#/co-actors-checking" class="btn btn-sm btn-kkc fw-bold">
        <i class="bi bi-person-badge-fill me-1"></i> Drehpartner &amp; ID-Check
        <span class="badge bg-white text-dark ms-1" id="cac-switcher-open-cnt"><?= (int)($counts['under_review'] ?? 0) ?></span>
      </a>
      <a href="#/movies-blocked" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-slash-circle me-1"></i> Filme abgelehnt / gesperrt
        <span class="badge bg-danger ms-1"><?= (int)($switcherCounts['movies_blocked'] ?? 0) ?></span>
      </a>
      <span class="text-muted mx-1">|</span>
      <a href="#/movies-online" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-collection-play-fill me-1"></i> Filme online
        <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_online'] ?? 0) ?></span>
      </a>
      <a href="#/photo-albums-online" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-journal-album me-1"></i> Fotoalben online
        <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['albums_online'] ?? 0) ?></span>
      </a>
    </div>
  </div>

  <!-- ========================================================================
       SEITEN-HEADER
       ======================================================================== -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge-kkc badge-kkc-info"><i class="bi bi-shield-check me-1"></i>Content prüfen</span>
        <span class="text-muted small">18+ Altersverifikation, ID-Shot &amp; Model-Release-Verwaltung</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-person-vcard-fill ec-text-primary"></i>
        <span>Drehpartner &amp; ID-Check</span>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
      </button>
    </div>
  </div>

  <!-- ========================================================================
       KPI-KACHELN (4er Grid)
       ======================================================================== -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100" style="cursor: pointer;" onclick="App.setCoActorsStatusFilter('under_review')">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Zur Prüfung offen</div>
            <div class="h3 fw-bold mb-0 text-warning" id="cac-kpi-under-review"><?= number_format((int)$counts['under_review'], 0, ',', '.') ?></div>
            <small class="text-muted">Eingereichte ID-Shots &amp; Unterschriften</small>
          </div>
          <div class="rounded-3 p-3" style="background: rgba(245, 158, 11, 0.14); color: #f59e0b;">
            <i class="bi bi-hourglass-split fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100" style="cursor: pointer;" onclick="App.setCoActorsStatusFilter('verified')">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Geprüft &amp; Verifiziert (OK)</div>
            <div class="h3 fw-bold mb-0 text-success" id="cac-kpi-verified"><?= number_format((int)$counts['verified'], 0, ',', '.') ?></div>
            <small class="text-muted">
              <i class="bi bi-qr-code-scan text-info"></i> <?= (int)$counts['ai_ready'] ?> Live-QR &bull;
              <?= (int)$counts['legacy'] ?> Altbestand
            </small>
          </div>
          <div class="rounded-3 p-3" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
            <i class="bi bi-patch-check-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100" style="cursor: pointer;" onclick="App.setCoActorsStatusFilter('rejected')">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Abgelehnt (Korrektur nötig)</div>
            <div class="h3 fw-bold mb-0 text-danger" id="cac-kpi-rejected"><?= number_format((int)$counts['rejected'], 0, ',', '.') ?></div>
            <small class="text-muted">Warten auf neuen Scan durch Partner</small>
          </div>
          <div class="rounded-3 p-3" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">
            <i class="bi bi-exclamation-octagon-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card-kkc h-100" style="cursor: pointer;" onclick="App.setCoActorsStatusFilter('all')">
        <div class="card-kkc-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold mb-1">Gesamtbestand &amp; Rollen</div>
            <div class="h3 fw-bold mb-0"><?= number_format((int)$counts['all'], 0, ',', '.') ?> <small class="fs-6 fw-normal text-muted">Personen</small></div>
            <small class="text-muted">
              <?= (int)$counts['owners'] ?> Inhaber („Ich“) &bull;
              <?= (int)$counts['co_actors'] ?> Drehpartner &bull;
              <?= (int)$counts['pending_qr'] ?> QR offen
            </small>
          </div>
          <div class="rounded-3 p-3" style="background: var(--kkc-accent-Soft); color: var(--kkc-accent);">
            <i class="bi bi-people-fill fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       STATUS-TABS & FILTER-LEISTE
       ======================================================================== -->
  <div class="card-kkc mb-3">
    <div class="card-kkc-body py-3">
      <!-- Status-Schnellwahl-Pills -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
        <div class="d-flex flex-wrap gap-1.5">
          <button type="button" class="btn btn-sm <?= $curStatus === 'under_review' ? 'btn-warning text-dark fw-bold' : 'btn-outline-kkc' ?>"
                  onclick="App.setCoActorsStatusFilter('under_review')">
            <i class="bi bi-hourglass-split me-1"></i> Zur Prüfung offen
            <span class="badge bg-dark text-white ms-1"><?= (int)$counts['under_review'] ?></span>
          </button>
          <button type="button" class="btn btn-sm <?= $curStatus === 'verified' ? 'btn-success fw-bold' : 'btn-outline-kkc' ?>"
                  onclick="App.setCoActorsStatusFilter('verified')">
            <i class="bi bi-patch-check-fill me-1"></i> Verifiziert (OK)
            <span class="badge bg-dark text-white ms-1"><?= (int)$counts['verified'] ?></span>
          </button>
          <button type="button" class="btn btn-sm <?= $curStatus === 'rejected' ? 'btn-danger fw-bold' : 'btn-outline-kkc' ?>"
                  onclick="App.setCoActorsStatusFilter('rejected')">
            <i class="bi bi-x-octagon-fill me-1"></i> Abgelehnt
            <span class="badge bg-dark text-white ms-1"><?= (int)$counts['rejected'] ?></span>
          </button>
          <button type="button" class="btn btn-sm <?= $curStatus === 'pending_qr' ? 'btn-secondary fw-bold' : 'btn-outline-kkc' ?>"
                  onclick="App.setCoActorsStatusFilter('pending_qr')">
            <i class="bi bi-qr-code-scan me-1"></i> Wartet auf QR-Scan
            <span class="badge bg-dark text-white ms-1"><?= (int)$counts['pending_qr'] ?></span>
          </button>
          <button type="button" class="btn btn-sm <?= $curStatus === 'all' ? 'btn-kkc fw-bold' : 'btn-outline-kkc' ?>"
                  onclick="App.setCoActorsStatusFilter('all')">
            <i class="bi bi-collection me-1"></i> Alle anzeigen
            <span class="badge bg-dark text-white ms-1"><?= (int)$counts['all'] ?></span>
          </button>
        </div>

        <div class="text-muted small">
          Angezeigt: <strong id="cac-visible-count"><?= count($actors) ?></strong> Einträge
        </div>
      </div>

      <!-- Erweiterte Filterung & Suche -->
      <form id="cac-filter-form" onsubmit="event.preventDefault(); App.applyCoActorsFilters();" class="row g-2 align-items-end">
        <input type="hidden" id="cac-filter-status" value="<?= View::e($curStatus) ?>">

        <div class="col-xl-4 col-md-6">
          <label class="form-label small text-muted mb-1">Server-Suche (Klarname, Creator, Ausweis-Nr., Ort, Partner-ID, Merchant)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
            <input type="text" id="cac-filter-q" class="form-control" value="<?= View::e($curSearch) ?>" placeholder="Name, Ausweisnummer, Partner-ID oder #ID...">
            <?php if ($curSearch !== ''): ?>
              <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('cac-filter-q').value=''; App.applyCoActorsFilters();" title="Suche zurücksetzen">
                <i class="bi bi-x-lg"></i>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-xl-2 col-md-3 col-6">
          <label class="form-label small text-muted mb-1">Personen-Rolle</label>
          <select id="cac-filter-role" class="form-select form-select-sm" onchange="App.applyCoActorsFilters()">
            <option value="all" <?= $curRole === 'all' ? 'selected' : '' ?>>Alle Rollen (Ich &amp; Drehpartner)</option>
            <option value="owner" <?= $curRole === 'owner' ? 'selected' : '' ?>>Nur Account-Inhaber („Ich“)</option>
            <option value="co_actor" <?= $curRole === 'co_actor' ? 'selected' : '' ?>>Nur weitere Drehpartner</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-3 col-6">
          <label class="form-label small text-muted mb-1">Nachweis-Methode</label>
          <select id="cac-filter-method" class="form-select form-select-sm" onchange="App.applyCoActorsFilters()">
            <option value="all" <?= $curMethod === 'all' ? 'selected' : '' ?>>Alle Methoden</option>
            <option value="qr_live" <?= $curMethod === 'qr_live' ? 'selected' : '' ?>>Smartphone Live-QR (KI-Ready)</option>
            <option value="manual_legacy" <?= $curMethod === 'manual_legacy' ? 'selected' : '' ?>>Altbestand-Dokumente</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
          <label class="form-label small text-muted mb-1">Sortierung</label>
          <select id="cac-filter-sort" class="form-select form-select-sm" onchange="App.applyCoActorsFilters()">
            <option value="priority" <?= $curSort === 'priority' ? 'selected' : '' ?>>Prüf-Priorität (Offene zuerst)</option>
            <option value="submitted_desc" <?= $curSort === 'submitted_desc' ? 'selected' : '' ?>>Eingereicht am (Neueste zuerst)</option>
            <option value="checked_desc" <?= $curSort === 'checked_desc' ? 'selected' : '' ?>>Geprüft am (Zuletzt geprüft)</option>
            <option value="id_desc" <?= $curSort === 'id_desc' ? 'selected' : '' ?>>ID (Neueste zuerst)</option>
            <option value="name_asc" <?= $curSort === 'name_asc' ? 'selected' : '' ?>>Name (A–Z)</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-4 col-6 d-flex gap-1">
          <input type="text" id="cac-quick-filter" class="form-control form-control-sm" placeholder="Schnellfilter..." oninput="App.quickFilterCoActorsTable(this.value)">
          <button type="submit" class="btn btn-sm btn-kkc" title="Server-Filter anwenden">
            <i class="bi bi-funnel-fill"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="App.resetCoActorsFilters()" title="Filter zurücksetzen">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================================
       HAUPTTABELLE: DREHPARTNER & ID-CHECK
       ======================================================================== -->
  <div class="card-kkc">
    <div class="table-responsive">
      <table class="table table-kkc align-middle mb-0" id="cac-actors-table">
        <thead>
          <tr>
            <th style="width: 240px;">Darsteller, Rolle &amp; Partner</th>
            <th style="width: 295px;">Entschlüsselte Ausweisdaten</th>
            <th>Aufnahmen (KI-Selfie, ID-Shot, Ausweis) &amp; Zertifikat</th>
            <th style="width: 240px;">Verknüpfte Inhalte</th>
            <th style="width: 255px;">Status &amp; Prüf-Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($actors)): ?>
            <tr>
              <td colspan="5" class="text-center py-5 text-muted">
                <i class="bi bi-person-check fs-1 d-block mb-2 opacity-50"></i>
                <div class="fw-semibold">Keine Darsteller- oder ID-Check-Einträge für diesen Filter gefunden.</div>
                <?php if ($curStatus === 'under_review' && (int)$counts['all'] > 0): ?>
                  <div class="small mt-2">
                    Aktuell warten keine neuen Einreichungen auf Prüfung.
                    <a href="javascript:void(0)" onclick="App.setCoActorsStatusFilter('all')" class="ec-text-primary fw-bold">Alle <?= (int)$counts['all'] ?> Einträge anzeigen</a>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($actors as $actor):
              $caId       = (int)$actor['id'];
              $merchantId = (int)$actor['merchant_id'];
              $isOwner    = !empty($actor['is_owner']);
              $status     = (string)$actor['checked_status'];
              $sigData    = is_array($actor['signature_data'] ?? null) ? $actor['signature_data'] : [];
              $linkedList = $linkedContentByActor[$caId] ?? [];

              $addressParts = array_filter([
                  trim((string)$actor['street']),
                  trim((string)$actor['zip_code'] . ' ' . (string)$actor['city']),
                  trim((string)$actor['country']) !== '' ? '(' . (string)$actor['country'] . ')' : '',
              ]);
              $addressText = !empty($addressParts) ? implode(', ', $addressParts) : '-';

              $searchBlob = mb_strtolower(
                  $caId . ' ' . $actor['ref_code'] . ' ' . $actor['display_label'] . ' ' .
                  $actor['real_full_name'] . ' ' . $actor['creator_name'] . ' ' .
                  $actor['id_doc_number'] . ' ' . $actor['city'] . ' ' .
                  $actor['merchant_username'] . ' ' . $actor['merchant_partner_id'] . ' ' . $merchantId,
                  'UTF-8'
              );

              // JSON-Payload für den 4-fach Vergleichs-Inspektor & das Stammdaten-Edit-Modal
              $inspectorPayload = [
                  'id'                 => $caId,
                  'merchant_id'        => $merchantId,
                  'ref_code'           => (string)$actor['ref_code'],
                  'is_owner'           => $isOwner,
                  'display_label'      => (string)$actor['display_label'],
                  'creator_name'       => (string)$actor['creator_name'],
                  'first_name'         => (string)$actor['first_name'],
                  'last_name'          => (string)$actor['last_name'],
                  'real_full_name'     => (string)$actor['real_full_name'],
                  'birth_date'         => (string)$actor['birth_date'],
                  'birth_date_de'      => (string)$actor['birth_date_de'],
                  'age'                => $actor['age'],
                  'street'             => (string)$actor['street'],
                  'zip_code'           => (string)$actor['zip_code'],
                  'city'               => (string)$actor['city'],
                  'country'            => (string)$actor['country'],
                  'address_text'       => $addressText,
                  'id_doc_type'        => (string)$actor['id_doc_type'],
                  'id_doc_type_label'  => (string)$actor['id_doc_type_label'],
                  'id_doc_number'      => (string)$actor['id_doc_number'],
                  'id_doc_valid_until' => (string)$actor['id_doc_valid_until'],
                  'id_doc_valid_label' => (string)$actor['id_doc_valid_label'],
                  'is_doc_expired'     => !empty($actor['is_doc_expired']),
                  'checked_status'     => $status,
                  'status_label'       => (string)$actor['status_label'],
                  'rejection_reason'   => (string)$actor['rejection_reason'],
                  'face_ref_url'       => (string)$actor['face_ref_url'],
                  'id_shot_url'        => (string)$actor['id_shot_url'],
                  'id_front_url'       => (string)$actor['id_front_url'],
                  'id_back_url'        => (string)$actor['id_back_url'],
                  'certificate_url'    => (string)$actor['certificate_url'],
                  'consent_raw_url'    => (string)$actor['consent_raw_url'],
                  'signed_at'          => !empty($sigData['signed_at']) ? date('d.m.Y H:i', strtotime((string)$sigData['signed_at'])) : '',
                  'signed_ip'          => (string)($sigData['ip'] ?? ''),
              ];
              $jsonAttr = View::e((string)json_encode($inspectorPayload, JSON_UNESCAPED_UNICODE));
            ?>
              <tr class="cac-actor-row" id="cac-row-<?= $caId ?>"
                  data-id="<?= $caId ?>"
                  data-status="<?= View::e($status) ?>"
                  data-search="<?= View::e($searchBlob) ?>"
                  data-actor="<?= $jsonAttr ?>"
                  style="<?= $status === 'under_review' ? 'background: rgba(245, 158, 11, 0.04);' : '' ?>">

                <!-- SPALTE 1: DARSTELLER, ROLLE & PARTNER -->
                <td class="align-top py-3">
                  <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                    <?php if ($isOwner): ?>
                      <span class="badge bg-primary"><i class="bi bi-person-fill me-1"></i>Account-Inhaber („Ich“)</span>
                    <?php else: ?>
                      <span class="badge bg-secondary"><i class="bi bi-people-fill me-1"></i>Drehpartner</span>
                    <?php endif; ?>

                    <?php if (!empty($actor['ai_face_ready'])): ?>
                      <span class="badge-kkc badge-kkc-info" title="Smartphone Live-QR Verifizierung mit KI-Referenzgesicht">
                        <i class="bi bi-qr-code-scan me-1"></i>Live-QR (KI-Ready)
                      </span>
                    <?php elseif (!empty($actor['is_legacy_upload'])): ?>
                      <span class="badge-kkc badge-kkc-warning" title="Manuell hochgeladenes Altbestand-Dokument">
                        <i class="bi bi-folder2-open me-1"></i>Altbestand
                      </span>
                    <?php else: ?>
                      <span class="badge-kkc badge-kkc-neutral">
                        <i class="bi bi-phone me-1"></i>QR-Link bereit
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="fw-bold fs-6 text-break mb-0.5" id="cac-label-<?= $caId ?>">
                    <?= View::e((string)$actor['display_label']) ?>
                  </div>
                  <div class="small text-muted font-monospace mb-2">
                    Ref: <strong><?= View::e((string)$actor['ref_code']) ?></strong> (ID #<?= $caId ?>)
                  </div>

                  <div class="p-2 rounded border small" style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="text-muted">Partner:</span>
                      <a href="#/merchant?id=<?= $merchantId ?>" class="fw-bold ec-text-primary text-decoration-none">
                        #<?= $merchantId ?> <?= View::e((string)($actor['merchant_username'] ?: $actor['merchant_partner_id'])) ?>
                      </a>
                    </div>
                    <?php if ($actor['merchant_real_name'] !== ''): ?>
                      <div class="text-muted" style="font-size: 0.75rem;">
                        Inhaber: <?= View::e((string)$actor['merchant_real_name']) ?>
                        <?= $actor['merchant_partner_id'] !== '' ? ' (PID ' . View::e((string)$actor['merchant_partner_id']) . ')' : '' ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- SPALTE 2: ENTSCHLÜSSELTE AUSWEISDATEN -->
                <td class="align-top py-3">
                  <div class="small lh-base">
                    <div class="mb-1">
                      <span class="text-muted">Klarname:</span>
                      <strong class="text-body"><?= View::e((string)($actor['real_full_name'] ?: '-')) ?></strong>
                    </div>

                    <div class="mb-1 d-flex align-items-center flex-wrap gap-1">
                      <span class="text-muted">Geburtsdatum:</span>
                      <strong><?= View::e((string)($actor['birth_date_de'] ?: '-')) ?></strong>
                      <?php if ($actor['age'] !== null): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                          <?= (int)$actor['age'] ?> Jahre (18+)
                        </span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Alter prüfen</span>
                      <?php endif; ?>
                    </div>

                    <div class="mb-1">
                      <span class="text-muted">Anschrift:</span>
                      <span><?= View::e($addressText) ?></span>
                    </div>

                    <div class="mb-1">
                      <span class="text-muted">Dokument:</span>
                      <strong><?= View::e((string)$actor['id_doc_type_label']) ?></strong>
                      &ndash; Nr.: <code class="fw-bold"><?= View::e((string)($actor['id_doc_number'] ?: '-')) ?></code>
                    </div>

                    <div class="mb-2 d-flex align-items-center flex-wrap gap-1">
                      <span class="text-muted">Gültig bis:</span>
                      <span class="fw-semibold <?= !empty($actor['is_doc_expired']) ? 'text-danger' : '' ?>">
                        <?= View::e((string)$actor['id_doc_valid_label']) ?>
                      </span>
                      <?php if (!empty($actor['is_doc_expired'])): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Abgelaufen!</span>
                      <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-1 pt-1 border-top" style="border-color: var(--kkc-border) !important;">
                      <button type="button" class="btn btn-xs btn-outline-kkc py-0.5 px-2" style="font-size: 0.74rem;"
                              onclick="App.openCoActorEditModal(<?= $caId ?>)">
                        <i class="bi bi-pencil-square me-1"></i> Ausweisdaten korrigieren
                      </button>
                      <button type="button" class="btn btn-xs btn-outline-kkc py-0.5 px-2" style="font-size: 0.74rem;"
                              onclick="App.openCoActorQrModal(<?= $caId ?>)" title="Smartphone QR-Code & Verifizierungs-Link anzeigen">
                        <i class="bi bi-qr-code me-1"></i> QR-Link
                      </button>
                    </div>
                  </div>
                </td>

                <!-- SPALTE 3: FOTOS, UNTERSCHRIFT & PDF-ZERTIFIKAT -->
                <td class="align-top py-3">
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <?php
                      $mediaItems = [
                          ['url' => $actor['face_ref_url'], 'label' => '1. KI-Selfie',  'badge' => 'bg-info text-dark'],
                          ['url' => $actor['id_shot_url'],  'label' => '2. ID-Shot',    'badge' => 'bg-primary'],
                          ['url' => $actor['id_front_url'], 'label' => '3. Ausweis V.', 'badge' => 'bg-secondary'],
                          ['url' => $actor['id_back_url'],  'label' => '4. Ausweis R.', 'badge' => 'bg-secondary'],
                      ];
                      $hasAnyPhoto = false;
                      foreach ($mediaItems as $mItem):
                          if (empty($mItem['url'])) {
                              continue;
                          }
                          $hasAnyPhoto = true;
                    ?>
                      <div class="border rounded p-1 text-center" style="background: var(--kkc-surface-hover); width: 88px; cursor: pointer;"
                           onclick="App.openCoActorInspectorModal(<?= $caId ?>)"
                           title="Klicken für 4-fach Vergleichs-Inspektor">
                        <div class="rounded overflow-hidden bg-dark mb-1" style="height: 60px;">
                          <img src="<?= View::e((string)$mItem['url']) ?>" alt="<?= View::e($mItem['label']) ?>"
                               loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                        </div>
                        <span class="badge <?= $mItem['badge'] ?> w-100" style="font-size: 0.65rem;">
                          <?= View::e($mItem['label']) ?>
                        </span>
                      </div>
                    <?php endforeach; ?>

                    <?php if (!$hasAnyPhoto): ?>
                      <div class="alert alert-warning py-1.5 px-2.5 mb-0 small d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Noch keine Kamera-Aufnahmen eingereicht (wartet auf QR-Scan).</span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Aktionsleiste für Vergleichs-Inspektor & PDF-Zertifikat -->
                  <div class="d-flex flex-wrap align-items-center gap-1.5 mb-1">
                    <?php if ($hasAnyPhoto): ?>
                      <button type="button" class="btn btn-sm btn-outline-kkc py-1 px-2"
                              onclick="App.openCoActorInspectorModal(<?= $caId ?>)">
                        <i class="bi bi-arrows-angle-expand me-1"></i> 4-fach Vergleichs-Inspektor
                      </button>
                    <?php endif; ?>

                    <?php if (!empty($actor['certificate_url'])): ?>
                      <a href="<?= View::e((string)$actor['certificate_url']) ?>" target="_blank" rel="noopener"
                         class="btn btn-sm btn-kkc py-1 px-2">
                        <i class="bi bi-file-earmark-check-fill me-1"></i> Einverständnis-Zertifikat / PDF
                      </a>
                    <?php endif; ?>

                    <?php if (!empty($actor['consent_raw_url']) && !empty($actor['is_legacy_upload'])): ?>
                      <a href="<?= View::e((string)$actor['consent_raw_url']) ?>" target="_blank" rel="noopener"
                         class="btn btn-sm btn-outline-secondary py-1 px-2">
                        <i class="bi bi-paperclip me-1"></i> Altbestand-Datei
                      </a>
                    <?php endif; ?>
                  </div>

                  <!-- Signatur-Audit-Zeile -->
                  <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                    <?php if (!empty($sigData['signed_at'])): ?>
                      <i class="bi bi-vector-pen text-success me-1"></i>
                      Touch-Signatur am <strong><?= date('d.m.Y H:i', strtotime((string)$sigData['signed_at'])) ?> Uhr</strong>
                      <?php if (!empty($sigData['ip'])): ?>
                        &bull; IP: <code><?= View::e((string)$sigData['ip']) ?></code>
                      <?php endif; ?>
                    <?php elseif (!empty($actor['submitted_datetime']) && !str_starts_with((string)$actor['submitted_datetime'], '0000')): ?>
                      <i class="bi bi-clock-history me-1"></i>
                      Eingereicht am <strong><?= date('d.m.Y H:i', strtotime((string)$actor['submitted_datetime'])) ?> Uhr</strong>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- SPALTE 4: VERKNÜPFTE FILME & FOTOALBEN -->
                <td class="align-top py-3">
                  <?php if (empty($linkedList)): ?>
                    <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Noch keinem Film/Album zugeordnet</span>
                  <?php else: ?>
                    <div class="d-flex flex-column gap-1.5" style="max-height: 165px; overflow-y: auto;">
                      <?php foreach ($linkedList as $item): ?>
                        <div class="p-1.5 rounded border small d-flex align-items-center justify-content-between gap-1"
                             style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important; padding: 4px 8px;">
                          <a href="<?= View::e((string)$item['route']) ?>" class="text-decoration-none fw-semibold text-truncate ec-text-primary"
                             title="<?= View::e((string)$item['type_label'] . ' #' . (int)$item['id'] . ': ' . (string)$item['title']) ?>">
                            <i class="bi <?= View::e((string)$item['type_icon']) ?> me-1"></i>#<?= (int)$item['id'] ?> <?= View::e((string)$item['title']) ?>
                          </a>
                          <span class="badge-kkc <?= View::e((string)$item['badge_cls']) ?> flex-shrink-0" style="font-size: 0.65rem;">
                            <?= View::e((string)$item['badge']) ?>
                          </span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- SPALTE 5: STATUS & PRÜF-AKTIONEN -->
                <td class="align-top py-3">
                  <?php
                    $stBadgeCls = 'badge-kkc-warning';
                    if ($status === 'verified') {
                        $stBadgeCls = 'badge-kkc-success';
                    } elseif ($status === 'rejected') {
                        $stBadgeCls = 'badge-kkc-danger';
                    } elseif ($status === 'under_review') {
                        $stBadgeCls = 'badge-kkc-info';
                    }
                  ?>
                  <div class="mb-2">
                    <span class="badge-kkc <?= $stBadgeCls ?>" id="cac-status-badge-<?= $caId ?>">
                      <?= View::e((string)$actor['status_label']) ?>
                    </span>
                  </div>

                  <div class="small text-muted mb-2" id="cac-audit-info-<?= $caId ?>" style="font-size: 0.75rem;">
                    <?php if ($actor['checked_by_name'] !== '' && !empty($actor['checked_on']) && !str_starts_with((string)$actor['checked_on'], '0000')): ?>
                      Geprüft von <strong><?= View::e((string)$actor['checked_by_name']) ?></strong>
                      am <?= date('d.m.Y H:i', strtotime((string)$actor['checked_on'])) ?>
                    <?php endif; ?>
                  </div>

                  <div id="cac-reject-box-<?= $caId ?>" class="alert alert-danger py-1.5 px-2 small mb-2 <?= $actor['rejection_reason'] !== '' ? '' : 'd-none' ?>" style="font-size: 0.76rem;">
                    <strong>Grund:</strong> <span id="cac-reject-text-<?= $caId ?>"><?= View::e((string)$actor['rejection_reason']) ?></span>
                  </div>

                  <!-- 1-Klick Aktionsbuttons -->
                  <?php if ($hasAnyPhoto || !empty($actor['has_uploaded_docs'])): ?>
                  <div class="d-flex flex-column gap-1.5">
                    <button type="button" id="cac-btn-verify-<?= $caId ?>"
                            class="btn btn-sm btn-success fw-bold w-100 <?= $status === 'verified' ? 'd-none' : '' ?>"
                            onclick="App.coActorQuickAction(<?= $caId ?>, 'verify')">
                      <i class="bi bi-check2-circle me-1"></i> ID-Shot &amp; Person bestätigen
                    </button>

                    <button type="button" id="cac-btn-reject-<?= $caId ?>"
                            class="btn btn-sm btn-outline-danger fw-semibold w-100 <?= $status === 'rejected' ? 'd-none' : '' ?>"
                            onclick="App.openCoActorRejectModal(<?= $caId ?>)">
                      <i class="bi bi-x-circle me-1"></i> ID-Check ablehnen...
                    </button>

                    <button type="button" id="cac-btn-reset-<?= $caId ?>"
                            class="btn btn-sm btn-outline-secondary w-100 <?= ($status === 'verified' || $status === 'rejected') ? '' : 'd-none' ?>"
                            onclick="App.coActorQuickAction(<?= $caId ?>, 'reset_review')">
                      <i class="bi bi-arrow-counterclockwise me-1"></i> Zurück auf „Offen“ setzen
                    </button>
                  </div>
                  <?php else: ?>
                  <div class="small text-muted p-2 rounded border" style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important; font-size: 0.74rem;">
                    <i class="bi bi-info-circle ec-text-primary me-1"></i> Noch kein ID-Shot eingereicht (Prüf-Buttons erscheinen nach dem Upload).
                  </div>
                  <?php endif; ?>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ==========================================================================
     MODAL 1: 4-FACH VERGLEICHS-INSPEKTOR (KI-SELFIE | ID-SHOT | AUSWEIS V & R)
     ========================================================================== -->
<div class="modal fade" id="cacInspectorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2.5 px-3 d-flex align-items-center justify-content-between">
        <div>
          <h5 class="modal-title h6 fw-bold mb-0" id="cacInspectorTitle">18+ ID-Check &amp; Dokumenten-Inspektor</h5>
          <small class="text-muted" id="cacInspectorSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-success fw-bold" id="cacInspectorVerifyBtn">
            <i class="bi bi-check2-circle me-1"></i> Jetzt bestätigen
          </button>
          <button type="button" class="btn btn-sm btn-danger fw-bold" id="cacInspectorRejectBtn">
            <i class="bi bi-x-circle me-1"></i> Ablehnen...
          </button>
          <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
      </div>
      <div class="modal-body p-3">
        <!-- Entschlüsselte Stammdaten-Leiste zum direkten Abgleich mit dem Ausweisbild -->
        <div class="p-2.5 rounded border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3"
             style="background: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;">
          <div>
            <span class="text-muted small d-block">Bürgerlicher Name laut Eingabe</span>
            <strong class="fs-6" id="cacInspName">-</strong>
          </div>
          <div>
            <span class="text-muted small d-block">Geburtsdatum &amp; Alter</span>
            <strong class="fs-6" id="cacInspBirth">-</strong>
          </div>
          <div>
            <span class="text-muted small d-block">Ausweis-Dokument &amp; Nummer</span>
            <strong class="fs-6 font-monospace" id="cacInspDoc">-</strong>
          </div>
          <div>
            <span class="text-muted small d-block">Gültig bis</span>
            <strong class="fs-6" id="cacInspValid">-</strong>
          </div>
          <div>
            <span class="text-muted small d-block">Anschrift</span>
            <span class="small fw-semibold" id="cacInspAddress">-</span>
          </div>
          <div class="ms-auto d-flex gap-2">
            <a href="#" target="_blank" rel="noopener" id="cacInspCertBtn" class="btn btn-sm btn-outline-kkc d-none">
              <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> PDF-Zertifikat
            </a>
          </div>
        </div>

        <!-- 4-fach Foto-Grid -->
        <div class="row g-3" id="cacInspectorGrid"></div>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     MODAL 2: ID-CHECK ABLEHNEN MIT VORLAGEN-AUSWAHL
     ========================================================================== -->
<div class="modal fade" id="cacRejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2.5 px-3 bg-danger bg-opacity-10">
        <h5 class="modal-title h6 fw-bold text-danger mb-0">
          <i class="bi bi-x-octagon-fill me-1"></i> ID-Check / Drehpartner ablehnen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <form id="cacRejectForm" onsubmit="App.submitCoActorRejectModal(event)">
        <div class="modal-body p-3">
          <input type="hidden" id="cacRejectActorId" value="0">
          <div class="mb-2 small text-muted">
            Darsteller: <strong class="text-body" id="cacRejectActorLabel">-</strong>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold mb-1">Schnellwahl-Vorlage</label>
            <select class="form-select form-select-sm" id="cacRejectTemplateSelect"
                    onchange="if(this.value !== '') document.getElementById('cacRejectReasonInput').value = this.value;">
              <option value="">-- Häufigen Ablehnungsgrund auswählen --</option>
              <?php foreach ($rejectionTemplates as $tpl): ?>
                <option value="<?= View::e($tpl['text']) ?>"><?= View::e($tpl['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-0">
            <label class="form-label small fw-bold mb-1">Begründung / Hinweis für den Partner *</label>
            <textarea class="form-control form-control-sm" id="cacRejectReasonInput" rows="4" required
                      placeholder="Bitte gib an, warum der ID-Shot oder Ausweis abgelehnt wird..."></textarea>
            <small class="text-muted">Dieser Hinweis wird dem Partner direkt im Partnerbereich bei der Person angezeigt.</small>
          </div>
        </div>
        <div class="modal-footer py-2 px-3">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-sm btn-danger fw-bold" id="cacRejectSubmitBtn">
            <i class="bi bi-x-circle me-1"></i> Jetzt verbindlich ablehnen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==========================================================================
     MODAL 3: AUSWEIS- & PERSONENDATEN KORRIGIEREN (AES_ENCRYPT)
     ========================================================================== -->
<div class="modal fade" id="cacEditDataModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2.5 px-3">
        <h5 class="modal-title h6 fw-bold mb-0">
          <i class="bi bi-pencil-square ec-text-primary me-1"></i> Ausweis- &amp; Personendaten korrigieren
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <form id="cacEditDataForm" onsubmit="App.submitCoActorEditData(event)">
        <div class="modal-body p-3">
          <input type="hidden" name="co_actor_id" id="cacEditActorId" value="0">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Creator- / Künstlername</label>
              <input type="text" name="creator_name" id="cacEditCreatorName" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Vorname (laut Ausweis) *</label>
              <input type="text" name="first_name" id="cacEditFirstName" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Nachname (laut Ausweis) *</label>
              <input type="text" name="last_name" id="cacEditLastName" class="form-control form-control-sm" required>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Geburtsdatum</label>
              <input type="date" name="birth_date" id="cacEditBirthDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Ausweis-Dokument</label>
              <select name="id_doc_type" id="cacEditDocType" class="form-select form-select-sm">
                <?php foreach ($docTypeLabels as $dKey => $dLbl): ?>
                  <option value="<?= View::e($dKey) ?>"><?= View::e($dLbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Ausweis-Nummer</label>
              <input type="text" name="id_doc_number" id="cacEditDocNumber" class="form-control form-control-sm font-monospace">
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold mb-1">Ausweis gültig bis</label>
              <input type="date" name="id_doc_valid_until" id="cacEditDocValid" class="form-control form-control-sm">
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold mb-1">Straße &amp; Hausnummer</label>
              <input type="text" name="street" id="cacEditStreet" class="form-control form-control-sm">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold mb-1">PLZ</label>
              <input type="text" name="zip_code" id="cacEditZip" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold mb-1">Ort</label>
              <input type="text" name="city" id="cacEditCity" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold mb-1">Land (ISO)</label>
              <input type="text" name="country" id="cacEditCountry" maxlength="5" class="form-control form-control-sm text-uppercase" value="DE">
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-sm btn-kkc fw-bold" id="cacEditSubmitBtn">
            <i class="bi bi-floppy-fill me-1"></i> Änderungen verschlüsselt speichern
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==========================================================================
     MODAL 4: SMARTPHONE QR-CODE & VERIFIZIERUNGS-LINK
     ========================================================================== -->
<div class="modal fade" id="cacQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: var(--kkc-surface, #ffffff); border: 1px solid var(--kkc-border);">
      <div class="modal-header py-2.5 px-3">
        <h5 class="modal-title h6 fw-bold mb-0">
          <i class="bi bi-qr-code-scan ec-text-primary me-1"></i> Smartphone Live-QR Verifizierung
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-3 text-center">
        <div class="fw-bold mb-1" id="cacQrActorLabel">-</div>
        <div class="small text-muted mb-3">
          Über diesen QR-Code oder Direktlink kann die Person das KI-Referenzselfie, den ID-Shot, die Ausweisfotos und die digitale Unterschrift direkt am Smartphone aufnehmen.
        </div>
        <div id="cacQrSvgBox" class="d-inline-block p-3 bg-white rounded border mb-3"></div>
        <div class="input-group input-group-sm mb-2">
          <input type="text" id="cacQrUrlInput" class="form-control font-monospace" readonly>
          <button type="button" class="btn btn-outline-kkc" onclick="navigator.clipboard?.writeText(document.getElementById('cacQrUrlInput').value); App.toast('Verifizierungs-Link in die Zwischenablage kopiert.', 'success');">
            <i class="bi bi-clipboard me-1"></i> Kopieren
          </button>
        </div>
      </div>
      <div class="modal-footer py-2 px-3 d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-warning" id="cacQrRegenBtn">
          <i class="bi bi-arrow-repeat me-1"></i> Neuen Token / QR-Link erzeugen
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
