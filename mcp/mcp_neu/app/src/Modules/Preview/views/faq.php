<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}
?>
<!-- FAQ Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Häufig gestellte Fragen (FAQ)</h1>
    <p class="text-muted mb-0">
      Antworten auf die wichtigsten Fragen rund um Darsteller-Profile, Video-Uploads, Fotoalben und den API-Import.
    </p>
  </div>
  <div>
    <button type="button" class="btn btn-sm btn-primary-kkc" data-bs-toggle="modal" data-bs-target="#modal-support">
      <i class="bi bi-headset me-1"></i> Support kontaktieren
    </button>
  </div>
</div>

<div class="card-kkc">
  <div class="card-kkc-header">
    <div class="card-title">
      <i class="bi bi-question-circle-fill ec-text-primary"></i>
      <span>Hilfe &amp; Anleitungen zur EroCloud</span>
    </div>
  </div>
  <div class="card-kkc-body">
    <div class="accordion" id="faqAccordion">
      <div class="accordion-item border rounded mb-2" style="border-color: var(--kkc-border) !important; background-color: var(--kkc-surface);">
        <h2 class="accordion-header">
          <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
            Wie lege ich ein neues Darsteller-Profil an?
          </button>
        </h2>
        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
          <div class="accordion-body text-muted small">
            Wählen Sie in der linken Navigation unter <strong>Profile</strong> den Punkt <strong>Profil anlegen</strong>. Hinterlegen Sie dort Ihren Künstlernamen, Ihre Profilbilder (FSK16 &amp; FSK18) sowie Ihre Steckbrief-Angaben. Nach kurzer Prüfung durch unser Team wird Ihr Profil freigeschaltet.
          </div>
        </div>
      </div>

      <div class="accordion-item border rounded mb-2" style="border-color: var(--kkc-border) !important; background-color: var(--kkc-surface);">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
            In welchem Format sollte ich meine Filme hochladen?
          </button>
        </h2>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body text-muted small">
            Wir empfehlen das Hochladen im <strong>MP4-Format (H.264 Video, AAC Audio)</strong> in Full-HD (1080p) oder HD (720p). Unser Konvertierungssystem optimiert Ihre Videos nach dem Upload automatisch für schnelles Streaming auf allen Endgeräten.
          </div>
        </div>
      </div>

      <div class="accordion-item border rounded" style="border-color: var(--kkc-border) !important; background-color: var(--kkc-surface);">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
            Wofür benötige ich meinen persönlichen API-Key?
          </button>
        </h2>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body text-muted small">
            Mit Ihrem persönlichen API-Key (abrufbar links im Menü unter <strong>Mein API-Key</strong>) können Sie Ihre Darsteller-Profile sowie deren Filme und Fotoalben direkt auf Ihre eigene EroCMS-Webseite importieren. Behandeln Sie diesen Schlüssel bitte stets vertraulich.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
