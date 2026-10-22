<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * Gemeinsame Bootstrap-5 Modals für:
 * 1. Neuen Drehpartner anlegen / QR-Code für Smartphone-Live-Check anzeigen (#modalCoActorQr)
 * 2. Alte Dokumente für vergangene/bestehende Filme manuell hochladen (#modalCoActorLegacy)
 *
 * Funktioniert sowohl auf #/co-actors als auch direkt inline in #/movie-upload und #/movie!
 */
$maxBirthDateModal = date('Y-m-d', strtotime('-18 years'));
?>

<!-- ==========================================================================
     MODAL 1: QR-Code Smartphone-Verifizierung (Standard für "Ich" & Drehpartner)
     ========================================================================== -->
<div class="modal fade" id="modalCoActorQr" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg border-0 rounded-4">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" id="modalCoActorQrTitle">
          <i class="bi bi-qr-code-scan text-primary me-2"></i>Drehpartner per Smartphone &amp; QR-Code verifizieren
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div id="co-actor-qr-alert" class="alert alert-danger d-none small"></div>

        <!-- Schritt A: Namen eingeben (wird übersprungen, wenn direkt QR für "Ich" oder bestehenden Drehpartner geöffnet wird) -->
        <div id="co-actor-qr-step-form">
          <div class="alert alert-info d-flex align-items-start gap-3 mb-3">
            <i class="bi bi-phone-vibrate fs-3 text-primary flex-shrink-0"></i>
            <div class="small">
              <strong>So funktioniert der schnelle Smartphone-Check (Best Practice):</strong><br>
              Gib hier kurz den Namen der Person ein. Direkt danach erscheint ein <strong>QR-Code</strong>, den dein Drehpartner mit seinem Smartphone scannt. Auf seinem Handy öffnet sich automatisch die Seite für die <strong>Live-Kamera-Aufnahme (ID-Shot &amp; Ausweis)</strong> und die <strong>Unterschrift mit dem Finger auf dem Display</strong>!
            </div>
          </div>

          <!-- Hinweis für vergangene Filme / Wiedersehen -->
          <div class="p-3 rounded-3 border bg-warning bg-opacity-10 border-warning border-opacity-25 small mb-3">
            <i class="bi bi-lightbulb-fill text-warning me-1"></i>
            <strong>Tipp für ältere Filme:</strong> Wenn du diesen Drehpartner demnächst wiedersiehst, nutze am besten ebenfalls diesen <strong>QR-Code-Check</strong> statt alte Papierdokumente hochzuladen! Nur frische Live-Aufnahmen über den QR-Code sind kompatibel mit unserer automatischen KI-Gesichtserkennung.
            <div class="mt-2">
              <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 small" onclick="window.MCPCoActors && window.MCPCoActors.switchToLegacyModal()">
                <i class="bi bi-folder2-open me-1"></i> Ich sehe die Person nicht mehr, habe aber die alten Dokumente für einen vergangenen Film vorliegen
              </button>
            </div>
          </div>

          <form id="form-create-co-actor-qr">
            <input type="hidden" name="co_actor_id" id="qr-form-co-actor-id" value="0">
            <input type="hidden" name="is_owner" id="qr-form-is-owner" value="0">
            <div class="row g-3">
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small">Creator- / Künstlername <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="creator_name" id="qr-form-creator-name" class="form-control" placeholder="z. B. LillyLove">
                <div class="form-text" style="font-size: 0.72rem;">Falls vorhanden (sonst leer lassen)</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small">Vorname (laut Ausweis) <span class="text-danger">*</span></label>
                <input type="text" name="first_name" id="qr-form-first-name" class="form-control" required placeholder="z. B. Lisa">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small">Nachname (laut Ausweis) <span class="text-danger">*</span></label>
                <input type="text" name="last_name" id="qr-form-last-name" class="form-control" required placeholder="z. B. Müller">
              </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
              <button type="button" class="btn btn-outline-kkc" data-bs-dismiss="modal">Abbrechen</button>
              <button type="submit" class="btn btn-kkc-primary px-4" id="btn-generate-co-actor-qr">
                <i class="bi bi-qr-code me-1"></i> Weiter &amp; QR-Code fürs Handy anzeigen
              </button>
            </div>
          </form>
        </div>

        <!-- Schritt B: QR-Code Anzeige + Live-Synchronisation -->
        <div id="co-actor-qr-step-display" class="d-none text-center">
          <div class="mb-2">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1" id="qr-display-person-badge">
              Drehpartner
            </span>
          </div>
          <h5 class="fw-bold mb-1" id="qr-display-headline">Jetzt QR-Code mit dem Smartphone scannen</h5>
          <p class="text-muted small mb-3" id="qr-display-subtext">
            Öffne die Kamera-App auf dem Smartphone, halte sie auf diesen QR-Code und tippe auf den eingeblendeten Link:
          </p>

          <div class="d-inline-block p-3 bg-white border border-2 rounded-4 shadow-sm mb-3" id="qr-display-svg-box">
            <!-- SVG QR-Code wird hier eingesetzt -->
          </div>

          <!-- Live-Status-Ticker während das Smartphone genutzt wird -->
          <div id="qr-live-waiting-box" class="p-3 rounded-3 border bg-primary bg-opacity-10 border-primary border-opacity-25 mx-auto mb-3" style="max-width: 520px;">
            <div class="d-flex align-items-center justify-content-center gap-2 text-primary fw-semibold small">
              <div class="spinner-border spinner-border-sm" role="status"></div>
              <span>Wartet auf Abschluss am Smartphone (aktualisiert sich automatisch live)...</span>
            </div>
          </div>

          <!-- Erfolgs-Banner sobald am Handy unterschrieben wurde -->
          <div id="qr-live-completed-box" class="p-3 rounded-3 border bg-success bg-opacity-10 border-success mx-auto mb-3 d-none" style="max-width: 520px;">
            <div class="fw-bold text-success mb-1">
              <i class="bi bi-patch-check-fill me-1"></i> Perfekt! ID-Shot &amp; Unterschrift wurden erfolgreich vom Smartphone übertragen!
            </div>
            <div class="small text-dark">
              Die Person ist jetzt sofort für deine Filme auswählbar. Du kannst dir die unterschriebene Einverständniserklärung jederzeit für deine Unterlagen als PDF speichern oder ausdrucken.
            </div>
          </div>

          <!-- Direkt-Link zum Kopieren / Teilen (falls auf demselben Gerät oder per WhatsApp) -->
          <div class="mx-auto text-start" style="max-width: 520px;">
            <label class="form-label small text-muted mb-1">Oder Link direkt kopieren / am Smartphone öffnen:</label>
            <div class="input-group input-group-sm mb-2">
              <input type="text" id="qr-display-url-input" class="form-control font-monospace" readonly value="">
              <button type="button" class="btn btn-outline-primary" onclick="window.MCPCoActors && window.MCPCoActors.copyVerifyUrl()">
                <i class="bi bi-clipboard me-1"></i> Link kopieren
              </button>
              <a href="#" target="_blank" id="qr-display-open-btn" class="btn btn-primary">
                <i class="bi bi-box-arrow-up-right me-1"></i> Direkt öffnen
              </a>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-qr-back-to-edit" onclick="window.MCPCoActors && window.MCPCoActors.showQrEditStep()">
              <i class="bi bi-pencil me-1"></i> Namen ändern
            </button>
            <button type="button" class="btn btn-kkc-primary px-4" data-bs-dismiss="modal">
              Fertig &amp; Schließen
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     MODAL 2: Manueller Dokumenten-Upload für vergangene / ältere Filme
     ========================================================================== -->
<div class="modal fade" id="modalCoActorLegacy" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg border-0 rounded-4">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-folder2-open text-primary me-2"></i>Vorhandene Dokumente für vergangene Filme hochladen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-co-actor-legacy" enctype="multipart/form-data">
        <input type="hidden" name="co_actor_id" id="legacy-form-co-actor-id" value="0">
        <div class="modal-body p-4">
          <div id="co-actor-legacy-alert" class="alert alert-danger d-none small"></div>

          <!-- Wichtiger Hinweis zum Wiedersehen & QR-Code-Vorteil -->
          <div class="alert alert-warning d-flex align-items-start gap-3 mb-3">
            <i class="bi bi-info-circle-fill fs-4 text-warning flex-shrink-0"></i>
            <div class="small">
              <strong>Wichtiger Hinweis für bestehende / ältere Filme:</strong><br>
              Hier kannst du für bereits abgedrehte Filme deinen vorhandenen ID-Shot und die unterschriebene Einverständniserklärung (als PDF oder Foto) hochladen.<br>
              <strong>Tipp:</strong> Falls du diese Person demnächst wiedersiehst, musst du die alten Dokumente gar nicht heraussuchen – nutze dann einfach den <strong>Smartphone-QR-Code</strong>! Auch wenn du jetzt erstmal die alten Dokumente hochlädst, kannst du die Person später beim nächsten Wiedersehen jederzeit per QR-Code auf einen frischen, KI-fähigen Live-ID-Shot aktualisieren.
            </div>
          </div>

          <div class="row g-3">
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Creator-Name <span class="text-muted fw-normal">(optional)</span></label>
              <input type="text" name="creator_name" id="legacy-creator-name" class="form-control" placeholder="z. B. LillyLove">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Vorname (laut Ausweis) <span class="text-danger">*</span></label>
              <input type="text" name="first_name" id="legacy-first-name" class="form-control" required placeholder="Vorname">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Nachname (laut Ausweis) <span class="text-danger">*</span></label>
              <input type="text" name="last_name" id="legacy-last-name" class="form-control" required placeholder="Nachname">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Geburtsdatum (mind. 18 J.)</label>
              <input type="date" name="birth_date" id="legacy-birth-date" class="form-control" max="<?= $maxBirthDateModal ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Ausweis-Nummer</label>
              <input type="text" name="id_doc_number" id="legacy-doc-number" class="form-control font-monospace" placeholder="Ausweis-Nr.">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Wohnort / Land</label>
              <input type="text" name="city" id="legacy-city" class="form-control" placeholder="z. B. Berlin">
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <!-- Upload 1: ID-Shot -->
            <div class="col-12 col-md-6">
              <div class="p-3 rounded-3 border bg-light h-100">
                <label class="form-label small fw-bold text-dark mb-1">
                  <i class="bi bi-person-vcard text-primary me-1"></i>1. ID-Shot (Person hält Ausweis neben Gesicht) <span class="text-danger">*</span>
                </label>
                <p class="text-muted mb-2" style="font-size: 0.75rem;">
                  Porträtfoto, auf dem die Person ihren Personalausweis gut lesbar im Klartext neben ihr Gesicht hält (JPG, PNG, WEBP).
                </p>
                <input type="file" name="id_shot_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
              </div>
            </div>

            <!-- Upload 2: Unterschriebenes Dokument -->
            <div class="col-12 col-md-6">
              <div class="p-3 rounded-3 border bg-light h-100">
                <label class="form-label small fw-bold text-dark mb-1">
                  <i class="bi bi-file-earmark-check text-primary me-1"></i>2. Unterschriebene Einverständniserklärung <span class="text-danger">*</span>
                </label>
                <p class="text-muted mb-2" style="font-size: 0.75rem;">
                  Vom Drehpartner ausgefülltes &amp; unterschriebenes Dokument, dass er der Veröffentlichung zustimmt (PDF, JPG, PNG).
                </p>
                <input type="file" name="consent_doc_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
              </div>
            </div>

            <!-- Upload 3: Optionale separate Ausweis-Nahaufnahme -->
            <div class="col-12">
              <div class="p-2 px-3 rounded-3 border bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="small">
                  <strong>Optional:</strong> Separate Nahaufnahme des Personalausweises (falls der Ausweis auf dem ID-Shot klein ist)
                </div>
                <input type="file" name="id_front_file" class="form-control form-control-sm" style="max-width: 280px;" accept=".jpg,.jpeg,.png,.webp,.pdf">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.MCPCoActors && window.MCPCoActors.openCreateQrModal()">
            <i class="bi bi-qr-code me-1"></i> Lieber per Smartphone-QR-Code machen
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-kkc" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-kkc-primary px-4" id="btn-save-co-actor-legacy">
              <i class="bi bi-cloud-arrow-up me-1"></i> Dokumente speichern &amp; einreichen
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
