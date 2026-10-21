<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}
?>
<!-- FAQ Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h2 class="h4 fw-bold mb-1">FAQ – Fragen, Antworten und wertvolle Praxis-Tipps</h2>
    <p class="text-muted small mb-0">
      Antworten auf die wichtigsten Fragen rund um Darsteller-Profile, Videoqualität, Render-Einstellungen, Foto-Alben und den API-Import.
    </p>
  </div>
  <div>
    <button type="button" class="btn btn-sm btn-kkc-primary" data-bs-toggle="modal" data-bs-target="#modal-support">
      <i class="bi bi-headset me-1"></i> Support kontaktieren
    </button>
  </div>
</div>

<!-- 3 Kacheln: Film-Qualität, Render-Einstellungen, Foto-Alben-Qualität -->
<div class="row g-4 mb-4">
  <!-- 1. Allgemeine Hinweise zur Qualität deiner Filme -->
  <div class="col-12 col-lg-4">
    <div class="kkc-card h-100">
      <div class="kkc-card-header">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-camera-reels text-primary me-2"></i>Qualität deiner Filme
        </h6>
      </div>
      <div class="kkc-card-body">
        <ul class="list-unstyled mb-0 small d-flex flex-column gap-3">
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-clock text-primary me-1"></i> Empfohlene Mindestlaufzeit</div>
            <div class="text-muted">Ein Film sollte idealerweise eine Mindestlaufzeit von 3 Minuten aufweisen, um für Kunden attraktiv zu sein.</div>
          </li>
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-fonts text-primary me-1"></i> Aussagekräftiger Filmtitel</div>
            <div class="text-muted">Wähle einen Filmtitel, der den konkreten Inhalt und die Szene so treffend wie möglich beschreibt.</div>
          </li>
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-card-text text-primary me-1"></i> Ausführliche Beschreibung (SEO)</div>
            <div class="text-muted">
              Verfasse eine Beschreibung mit möglichst 200 Wörtern oder mehr und achte auf korrekte Rechtschreibung sowie Groß- und Kleinschreibung.
              Je detaillierter dein Text ist, desto besser wird dein Film über Suchmaschinen wie Google gefunden.
            </div>
          </li>
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-brightness-high text-primary me-1"></i> Licht- und Tonverhältnisse</div>
            <div class="text-muted">Achte beim Dreh stets auf eine gute Ausleuchtung und eine klare, störungsfreie Tonaufnahme.</div>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <!-- 2. So renderst du deine Filme richtig -->
  <div class="col-12 col-lg-4">
    <div class="kkc-card h-100">
      <div class="kkc-card-header">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-sliders text-primary me-2"></i>Empfohlene Render-Einstellungen
        </h6>
      </div>
      <div class="kkc-card-body">
        <p class="text-muted small mb-3">
          Mit den folgenden Export-Einstellungen erzielst du in gängigen Schnittprogrammen (z. B. Premiere Pro, DaVinci Resolve, Final Cut) ein optimales MP4-Ergebnis:
        </p>
        <div class="table-responsive">
          <table class="table table-sm small mb-0">
            <tbody>
              <tr>
                <td class="text-muted">Container / Format</td>
                <td class="fw-semibold text-end">MP4 (.mp4)</td>
              </tr>
              <tr>
                <td class="text-muted">Video-Codec</td>
                <td class="fw-semibold text-end">AVC / H.264 (Profil: High)</td>
              </tr>
              <tr>
                <td class="text-muted">Auflösung</td>
                <td class="fw-semibold text-end">1920 &times; 1080 (Full-HD / 1080p)</td>
              </tr>
              <tr>
                <td class="text-muted">Bildrate (Framerate)</td>
                <td class="fw-semibold text-end">25 FPS (PAL) oder 30 FPS</td>
              </tr>
              <tr>
                <td class="text-muted">Halbbilder</td>
                <td class="fw-semibold text-end">Keine (Progressive Scan)</td>
              </tr>
              <tr>
                <td class="text-muted">Video-Bitrate</td>
                <td class="fw-semibold text-end">VBR, ca. 8–10 Mbit/s</td>
              </tr>
              <tr>
                <td class="text-muted">Audio-Codec &amp; Bitrate</td>
                <td class="fw-semibold text-end">AAC, 192 kbit/s Stereo</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Hinweise zur Qualität deiner Foto-Alben -->
  <div class="col-12 col-lg-4">
    <div class="kkc-card h-100">
      <div class="kkc-card-header">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-images text-primary me-2"></i>Qualität deiner Foto-Alben
        </h6>
      </div>
      <div class="kkc-card-body">
        <ul class="list-unstyled mb-0 small d-flex flex-column gap-3">
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-collection text-primary me-1"></i> Empfohlene Bildanzahl</div>
            <div class="text-muted">Technisch sind mindestens 4 Fotos erforderlich – für gute Verkaufszahlen empfehlen wir jedoch mindestens 10 bis 20 hochauflösende Fotos pro Album.</div>
          </li>
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-primary me-1"></i> FSK16- &amp; FSK18-Coverbilder</div>
            <div class="text-muted">Das FSK16-Cover wird im öffentlichen Vorschau-Bereich angezeigt und muss zwingend jugendfrei sein.</div>
          </li>
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-card-heading text-primary me-1"></i> Albumtitel &amp; Beschreibung</div>
            <div class="text-muted">Beschreibe das Shooting und das Outfit ausführlich, damit sowohl Kunden als auch Suchmaschinen dein Album optimal finden.</div>
          </li>
          <li>
            <div class="fw-bold text-dark mb-1"><i class="bi bi-stars text-primary me-1"></i> Schärfe &amp; Auflösung</div>
            <div class="text-muted">Veröffentliche nur scharfe, gut belichtete Aufnahmen, die deinen eigenen Qualitätsansprüchen gerecht werden.</div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Häufig gestellte Fragen (Akkordeon) & Weiterführende Anleitungen -->
<div class="row g-4">
  <div class="col-12 col-xl-8">
    <div class="kkc-card">
      <div class="kkc-card-header">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-question-circle text-primary me-2"></i>Häufig gestellte Fragen zur EroCloud
        </h6>
      </div>
      <div class="kkc-card-body">
        <div class="accordion" id="faqAccordion">
          <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                Wie lege ich ein neues Darsteller-Profil an?
              </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted small">
                Wähle in der linken Navigation unter <strong>Profile</strong> den Punkt <strong>Profil anlegen</strong>. Hinterlege dort deinen Künstlernamen, deine Profilbilder (FSK16 &amp; FSK18) sowie deine Steckbrief-Angaben. Nach kurzer Prüfung durch unser Team wird dein Profil freigeschaltet.
              </div>
            </div>
          </div>

          <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                Wie funktioniert die automatische Kategorie-Erkennung bei Filmen?
              </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted small">
                Anhand deines Filmtitels, deiner Beschreibung und der Merkmale deines Darsteller-Profils schlägt unser System passende Kategorien automatisch vor und hebt diese <strong>gelb</strong> hervor. Du kannst jederzeit weitere passende Kategorien ergänzen oder anpassen.
              </div>
            </div>
          </div>

          <div class="accordion-item border rounded-3 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                Wofür benötige ich meinen persönlichen API-Key?
              </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted small">
                Mit deinem persönlichen API-Key (abrufbar links im Menü unter <strong>Mein API-Key</strong>) kannst du deine Darsteller-Profile sowie deren Filme und Foto-Alben direkt auf deine eigene EroCMS-Webseite importieren. Behandle diesen Schlüssel bitte stets vertraulich.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Weiterführende Blog-Beiträge -->
  <div class="col-12 col-xl-4">
    <div class="kkc-card h-100">
      <div class="kkc-card-header">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-journal-bookmark text-primary me-2"></i>Weiterführende Anleitungen
        </h6>
      </div>
      <div class="kkc-card-body">
        <div class="d-flex flex-column gap-3 small">
          <a href="https://pay4coins.net/2018/07/11/erocloud-wie-importiere-ich-darsteller-auf-meine-webseite/"
             target="_blank" rel="noopener"
             class="p-3 rounded-3 border bg-light text-decoration-none text-dark d-block">
            <div class="fw-bold text-primary mb-1">
              Darsteller importieren <i class="bi bi-box-arrow-up-right ms-1"></i>
            </div>
            <div class="text-muted">Wie importiere ich Darsteller-Profile und Videos auf meine eigene EroCMS-Webseite?</div>
          </a>

          <a href="https://pay4coins.net/2018/07/08/erocloud-wie-kann-ich-als-webmaster-geld-verdienen/"
             target="_blank" rel="noopener"
             class="p-3 rounded-3 border bg-light text-decoration-none text-dark d-block">
            <div class="fw-bold text-primary mb-1">
              Umsätze &amp; Vermarktung <i class="bi bi-box-arrow-up-right ms-1"></i>
            </div>
            <div class="text-muted">Tipps zur erfolgreichen Vermarktung deiner Inhalte über die EroCloud und Pay4Coins.</div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
