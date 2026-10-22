<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, string> $imprint
 * @var array<string, string> $defaults
 * @var array<string, mixed> $smtpConfig
 * @var array<string, int> $mailStats
 * @var array<int, array<string, mixed>> $deliveryLogs
 * @var array<string, mixed> $envInfo
 * @var array{subject: string, html: string, text: string} $initialPreview
 */

$isSandbox = (bool)$smtpConfig['is_sandbox'];
$sandboxRecipient = (string)$smtpConfig['sandbox_recipient'];
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <!-- Kopfzeile & System-Switcher -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge-kkc badge-kkc-info"><i class="bi bi-sliders me-1"></i>System &amp; Technik</span>
        <span class="text-muted small">Zentrales Impressum, PHPMailer &amp; Server-Umgebung</span>
      </div>
      <h1 class="h4 fw-bold mb-0">Impressum, Support &amp; E-Mail-System</h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="btn-group btn-group-sm">
        <a href="#/system-settings" class="btn btn-primary-kkc active">
          <i class="bi bi-sliders me-1"></i>Impressum &amp; E-Mail
        </a>
        <a href="#/errors" class="btn btn-outline-kkc">
          <i class="bi bi-bug-fill me-1"></i>PHP-Error-Log (<?= View::e((string)$envInfo['error_log_size_fmt']) ?>)
        </a>
      </div>
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
      </button>
    </div>
  </div>

  <!-- 4 KPI-Kacheln -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">SMTP &amp; PHPMailer Modus</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(2,132,199,0.12); color: #0284c7;">
            <i class="bi bi-envelope-check-fill"></i>
          </span>
        </div>
        <div class="mb-1">
          <?php if ($isSandbox): ?>
            <span class="badge bg-warning text-dark"><i class="bi bi-shield-lock-fill me-1"></i>Sandbox aktiv</span>
          <?php else: ?>
            <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>Live-Versand aktiv</span>
          <?php endif; ?>
        </div>
        <div class="small text-muted text-truncate">
          <?= View::e((string)$smtpConfig['smtp_host']) ?>:<?= (int)$smtpConfig['smtp_port'] ?> (<?= View::e((string)$smtpConfig['smtp_secure']) ?>)
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">E-Mail-Versandbilanz (`notification_log`)</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(16,185,129,0.12); color: #10b981;">
            <i class="bi bi-send-check-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1">
          <?= number_format($mailStats['total_sent'] + $mailStats['total_sandbox'], 0, ',', '.') ?>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Heute: <strong><?= number_format($mailStats['sent_today'], 0, ',', '.') ?></strong></span>
          <span class="<?= $mailStats['total_failed'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
            Fehler: <?= number_format($mailStats['total_failed'], 0, ',', '.') ?>
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Benachrichtigungs-Präferenzen</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(139,92,246,0.12); color: #8b5cf6;">
            <i class="bi bi-bell-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($mailStats['prefs_count'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Gespeicherte Opt-In/Out-Regeln</span>
          <span>Push: <strong><?= number_format($mailStats['push_devices'], 0, ',', '.') ?></strong></span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Server &amp; PHP-Laufzeit</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(245,158,11,0.12); color: #f59e0b;">
            <i class="bi bi-hdd-rack-fill"></i>
          </span>
        </div>
        <div class="h4 fw-bold mb-1 font-monospace">PHP <?= View::e((string)$envInfo['php_version']) ?></div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span class="text-truncate"><?= View::e((string)$envInfo['server_hostname']) ?></span>
          <a href="#/errors" class="text-decoration-none fw-semibold ec-text-primary">Error-Log &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <!-- 3 Bootstrap-Tabs -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold" id="tab-btn-sys-imprint" data-bs-toggle="tab" data-bs-target="#tab-sys-imprint" type="button" role="tab">
        <i class="bi bi-building-gear me-1"></i>1. Impressums- &amp; Support-Stammdaten
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-sys-mailtest" data-bs-toggle="tab" data-bs-target="#tab-sys-mailtest" type="button" role="tab">
        <i class="bi bi-envelope-paper-fill me-1"></i>2. SMTP-Konfiguration, Vorschau &amp; Testversand
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-sys-logs" data-bs-toggle="tab" data-bs-target="#tab-sys-logs" type="button" role="tab">
        <i class="bi bi-journal-check me-1"></i>3. Versand-Protokoll &amp; PHP-Umgebung
        <span class="badge bg-secondary rounded-pill ms-1"><?= count($deliveryLogs) ?></span>
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ================================================================== -->
    <!-- TAB 1: Zentrale Impressums- & Support-Daten + Live-Footer-Vorschau -->
    <!-- ================================================================== -->
    <div class="tab-pane fade show active" id="tab-sys-imprint" role="tabpanel">
      <div class="row g-4">

        <!-- Linke Spalte: Eingabeformular -->
        <div class="col-12 col-xl-7">
          <div class="card-kkc p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div>
                <h2 class="h6 fw-bold mb-1">1. Zentrale Impressums- &amp; Support-Daten (Pay4Coins / EroCloud)</h2>
                <p class="small text-muted mb-0">
                  Diese Angaben werden zentral in der Tabelle <code>settings</code> gespeichert und automatisch im <strong>Footer aller ausgehenden EroCloud System-E-Mails</strong> sowie im <strong>Partner-Bereich (MCP)</strong> verwendet.
                </p>
              </div>
              <button type="button" class="btn btn-xs btn-outline-kkc py-1 px-2 small" onclick="App.SystemTech.resetImprintDefaults()">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Standardwerte laden
              </button>
            </div>

            <form id="sys-imprint-form" onsubmit="return App.SystemTech.saveImprint(event);" oninput="App.SystemTech.updateLiveFooterPreview()">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Betreiberfirma</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_company_name" id="imp-company" value="<?= View::e((string)($imprint['imprint_company_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Geschäftsführung (CEO)</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_ceo" id="imp-ceo" value="<?= View::e((string)($imprint['imprint_ceo'] ?? '')) ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Straße / Büro</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_address_street" id="imp-street" value="<?= View::e((string)($imprint['imprint_address_street'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">PLZ / Ort / Land</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_address_city" id="imp-city" value="<?= View::e((string)($imprint['imprint_address_city'] ?? '')) ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Handelsregister</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_register_court" id="imp-court" value="<?= View::e((string)($imprint['imprint_register_court'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Steuernummer / USt-ID (CIF)</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_vat_id" id="imp-vat" value="<?= View::e((string)($imprint['imprint_vat_id'] ?? '')) ?>">
                </div>

                <div class="col-12">
                  <label class="form-label small fw-semibold mb-1">Register-Eintrag (Tomo / Folio / Hoja / Inscripción)</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_register_details" id="imp-regdetails" value="<?= View::e((string)($imprint['imprint_register_details'] ?? '')) ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Support E-Mail-Adresse</label>
                  <input type="email" class="form-control form-control-sm" name="imprint_support_email" id="imp-email" value="<?= View::e((string)($imprint['imprint_support_email'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Telefon / WhatsApp (Business)</label>
                  <input type="text" class="form-control form-control-sm" name="imprint_phone" id="imp-phone" value="<?= View::e((string)($imprint['imprint_phone'] ?? '')) ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-semibold mb-1">WhatsApp Direktlink</label>
                  <input type="url" class="form-control form-control-sm" name="imprint_whatsapp_url" id="imp-wa-url" value="<?= View::e((string)($imprint['imprint_whatsapp_url'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold mb-1">Haupt-Website URL</label>
                  <input type="url" class="form-control form-control-sm" name="imprint_website_url" id="imp-web-url" value="<?= View::e((string)($imprint['imprint_website_url'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold mb-1">Offizielle Impressum-URL</label>
                  <input type="url" class="form-control form-control-sm" name="imprint_external_url" id="imp-ext-url" value="<?= View::e((string)($imprint['imprint_external_url'] ?? '')) ?>">
                </div>
              </div>

              <div class="mt-4 d-flex justify-content-end gap-2">
                <button type="submit" id="btn-save-imprint" class="btn btn-primary-kkc px-4">
                  <i class="bi bi-floppy-fill me-1"></i>Impressums- &amp; Supportdaten speichern
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Rechte Spalte: Echtzeit-Vorschau des E-Mail-Footers -->
        <div class="col-12 col-xl-5">
          <div class="card-kkc p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-eye-fill me-2 ec-text-primary"></i>Live-Vorschau: E-Mail-Footer &amp; MCP-Kontaktbox
            </h2>

            <div class="p-3 rounded border text-center small" style="background: #F8FAFC; color: #64748B; line-height: 1.6;">
              <div class="p-2.5 rounded border bg-white mb-3 text-body">
                Dies ist eine automatisch generierte System-Nachricht &ndash; bitte antworte nicht direkt auf diese E-Mail.<br>
                Bei Fragen erreichst du unseren Support jederzeit unter <strong id="prev-imp-email-1"><?= View::e((string)($imprint['imprint_support_email'] ?? '')) ?></strong>.
              </div>

              <div>
                <strong>EroCloud</strong> &ndash; ein Projekt von Pay4Coins (<strong id="prev-imp-company"><?= View::e((string)($imprint['imprint_company_name'] ?? '')) ?></strong>)<br>
                <span id="prev-imp-street"><?= View::e((string)($imprint['imprint_address_street'] ?? '')) ?></span> &bull;
                <span id="prev-imp-city"><?= View::e((string)($imprint['imprint_address_city'] ?? '')) ?></span><br>
                <span id="prev-imp-court"><?= View::e((string)($imprint['imprint_register_court'] ?? '')) ?></span>
                (<span id="prev-imp-regdetails"><?= View::e((string)($imprint['imprint_register_details'] ?? '')) ?></span>) &bull;
                <span id="prev-imp-vat"><?= View::e((string)($imprint['imprint_vat_id'] ?? '')) ?></span> &bull;
                Geschäftsführung: <span id="prev-imp-ceo"><?= View::e((string)($imprint['imprint_ceo'] ?? '')) ?></span><br>
                <a href="https://erocloud.net" target="_blank" rel="noopener" class="ec-text-primary text-decoration-none">erocloud.net</a> &bull;
                <a id="prev-imp-ext-link" href="<?= View::e((string)($imprint['imprint_external_url'] ?? '#')) ?>" target="_blank" rel="noopener" class="ec-text-primary text-decoration-none">Impressum</a> &bull;
                Support: <a id="prev-imp-email-link" href="mailto:<?= View::e((string)($imprint['imprint_support_email'] ?? '')) ?>" class="ec-text-primary text-decoration-none"><span id="prev-imp-email-2"><?= View::e((string)($imprint['imprint_support_email'] ?? '')) ?></span></a>
              </div>
            </div>

            <div class="mt-3 p-3 rounded border" style="background: var(--kkc-surface-hover);">
              <div class="small fw-bold mb-2"><i class="bi bi-whatsapp text-success me-1"></i>WhatsApp- &amp; Support-Schnelltest</div>
              <div class="d-flex flex-wrap gap-2">
                <a id="prev-imp-wa-btn" href="<?= View::e((string)($imprint['imprint_whatsapp_url'] ?? '#')) ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-kkc py-1 px-2 small">
                  <i class="bi bi-whatsapp text-success me-1"></i><span id="prev-imp-phone"><?= View::e((string)($imprint['imprint_phone'] ?? '')) ?></span>
                </a>
                <a id="prev-imp-web-btn" href="<?= View::e((string)($imprint['imprint_website_url'] ?? '#')) ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-kkc py-1 px-2 small">
                  <i class="bi bi-globe2 me-1"></i>Haupt-Website öffnen
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 2: SMTP-Konfiguration, HTML-Vorschau & Testversand             -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-sys-mailtest" role="tabpanel">
      <div class="row g-4">

        <!-- Linke Spalte: SMTP-Status & Testversand-Steuerung -->
        <div class="col-12 col-xl-5">
          <div class="card-kkc p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-hdd-network me-2 ec-text-primary"></i>Aktive PHPMailer SMTP-Konfiguration
            </h2>

            <table class="table table-sm align-middle mb-0 small">
              <tbody>
                <tr>
                  <td class="text-muted">SMTP-Server &amp; Port</td>
                  <td class="fw-bold font-monospace text-end"><?= View::e((string)$smtpConfig['smtp_host']) ?>:<?= (int)$smtpConfig['smtp_port'] ?> (<?= View::e((string)$smtpConfig['smtp_secure']) ?>)</td>
                </tr>
                <tr>
                  <td class="text-muted">SMTP-Benutzer</td>
                  <td class="fw-bold font-monospace text-end"><?= View::e((string)$smtpConfig['smtp_user']) ?></td>
                </tr>
                <tr>
                  <td class="text-muted">Absender (From)</td>
                  <td class="fw-semibold text-end"><?= View::e((string)$smtpConfig['from_name']) ?> &lt;<?= View::e((string)$smtpConfig['from_address']) ?>&gt;</td>
                </tr>
                <tr>
                  <td class="text-muted">Reply-To Header</td>
                  <td class="text-success fw-semibold text-end">Deaktiviert (Reine No-Reply Systemmail)</td>
                </tr>
                <tr>
                  <td class="text-muted">List-Unsubscribe</td>
                  <td class="text-end"><span class="badge bg-info text-dark">RFC 8058 One-Click aktiv</span></td>
                </tr>
                <tr>
                  <td class="text-muted">Betriebsmodus</td>
                  <td class="text-end">
                    <?php if ($isSandbox): ?>
                      <span class="badge bg-warning text-dark">Sandbox &rarr; <?= View::e($sandboxRecipient) ?></span>
                    <?php else: ?>
                      <span class="badge bg-success">Live-Versand aktiv</span>
                    <?php endif; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="card-kkc p-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-send-fill me-2 ec-text-primary"></i>Interaktiver Test-Versand &amp; Vorlagen-Labor
            </h2>

            <form id="sys-testmail-form" onsubmit="return App.SystemTech.sendOrPreviewTestMail(event, 'send');">
              <div class="row g-2 mb-3">
                <div class="col-sm-6">
                  <label class="form-label small fw-semibold mb-1">Creator-Name (Anrede in Du-Form)</label>
                  <input type="text" class="form-control form-control-sm" name="creator_name" value="Lara" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small fw-semibold mb-1">Händler-ID (MID für Magic-Link)</label>
                  <input type="number" class="form-control form-control-sm font-monospace" name="merchant_id" value="10061" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-sm-4">
                  <label class="form-label small fw-semibold mb-1">Beispiel Film-ID</label>
                  <input type="number" class="form-control form-control-sm font-monospace" name="movie_id" value="48291" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-sm-4">
                  <label class="form-label small fw-semibold mb-1">Spielzeit</label>
                  <input type="text" class="form-control form-control-sm" name="playtime" value="18:45" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-sm-4">
                  <label class="form-label small fw-semibold mb-1">Auflösung</label>
                  <input type="text" class="form-control form-control-sm" name="resolution" value="1920x1080 (Full-HD)" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold mb-1">Intendierte Ziel-E-Mail-Adresse</label>
                  <input type="email" class="form-control form-control-sm" name="to_email" value="creator-beispiel@domain.tld" oninput="App.SystemTech.debouncedPreviewMail()">
                  <?php if ($isSandbox): ?>
                    <div class="form-text text-warning small">
                      <i class="bi bi-shield-check me-1"></i>Da <code>MAIL_SANDBOX_MODE = true</code> aktiv ist, wird die Test-E-Mail sicher an <strong><?= View::e($sandboxRecipient) ?></strong> zugestellt.
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.SystemTech.sendOrPreviewTestMail(null, 'preview')">
                  <i class="bi bi-eye me-1"></i>Vorschau aktualisieren
                </button>
                <button type="submit" id="btn-send-testmail" class="btn btn-sm btn-success flex-grow-1">
                  <i class="bi bi-send-check-fill me-1"></i>Test-E-Mail („Video konvertiert“) jetzt absenden
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Rechte Spalte: Live HTML- & Plaintext-Vorschau -->
        <div class="col-12 col-xl-7">
          <div class="card-kkc p-4 h-100 d-flex flex-column">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div>
                <h2 class="h6 fw-bold mb-0">Gerenderte E-Mail-Vorschau (Multipart HTML &amp; Plaintext)</h2>
                <div class="small text-muted">Betreff: <strong id="sys-mail-preview-subject"><?= View::e($initialPreview['subject']) ?></strong></div>
              </div>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-primary-kkc" id="btn-view-mail-html" onclick="App.SystemTech.switchMailPreviewMode('html')">HTML-Ansicht</button>
                <button type="button" class="btn btn-outline-kkc" id="btn-view-mail-text" onclick="App.SystemTech.switchMailPreviewMode('text')">Plaintext (AltBody)</button>
              </div>
            </div>

            <div id="sys-mail-preview-html-wrap" class="border rounded overflow-hidden flex-grow-1 bg-white" style="min-height: 520px;">
              <iframe id="sys-mail-preview-iframe" title="E-Mail HTML Vorschau" class="w-100 h-100 border-0" style="min-height: 520px;" srcdoc="<?= View::e($initialPreview['html']) ?>"></iframe>
            </div>

            <div id="sys-mail-preview-text-wrap" class="d-none border rounded p-3 bg-dark text-light font-monospace small flex-grow-1 overflow-auto" style="min-height: 520px; white-space: pre-wrap;"><?= View::e($initialPreview['text']) ?></div>
          </div>
        </div>

      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 3: Versand-Protokoll (notification_log) & PHP-Umgebung         -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-sys-logs" role="tabpanel">
      <div class="row g-4">

        <!-- Linke Spalte: Letzte 100 Einträge aus notification_log -->
        <div class="col-12 col-xl-8">
          <div class="card-kkc overflow-hidden">
            <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
              <div>
                <h2 class="h6 fw-bold mb-0">Ausgehende System-Benachrichtigungen (`notification_log`)</h2>
                <small class="text-muted">Die letzten 100 protokollierten E-Mail- und Push-Zustellungen inkl. Duplikat-Schutz.</small>
              </div>
              <input type="text" class="form-control form-control-sm" style="width: 240px;" placeholder="Protokoll filtern..." oninput="App.SystemTech.filterNotificationLog(this.value)">
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                  <tr>
                    <th>ID / Zeit</th>
                    <th>Händler</th>
                    <th>Event &amp; Referenz</th>
                    <th>Empfänger</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($deliveryLogs)): ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted py-4">Noch keine Einträge im Versand-Protokoll (`notification_log`) vorhanden.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($deliveryLogs as $log): ?>
                      <?php
                        $hay = mb_strtolower($log['id'] . ' ' . $log['merchant_id'] . ' ' . $log['event_key'] . ' ' . $log['recipient'] . ' ' . $log['status'] . ' ' . $log['error_message'], 'UTF-8');
                      ?>
                      <tr class="sys-notif-log-row" data-search="<?= View::e($hay) ?>">
                        <td class="small">
                          <div class="font-monospace fw-bold">#<?= (int)$log['id'] ?></div>
                          <div class="text-muted" style="font-size: 0.72rem;"><?= View::e((string)$log['created_at_de']) ?></div>
                        </td>
                        <td class="font-monospace small">
                          <?php if ($log['merchant_id'] > 0): ?>
                            <a href="#/merchant?id=<?= (int)$log['merchant_id'] ?>" class="text-decoration-none">MID #<?= (int)$log['merchant_id'] ?></a>
                          <?php else: ?>
                            -
                          <?php endif; ?>
                        </td>
                        <td class="small">
                          <span class="badge bg-secondary bg-opacity-10 text-body border font-monospace"><?= View::e((string)$log['event_key']) ?></span>
                          <?php if ($log['reference_id'] > 0): ?>
                            <div class="text-muted mt-0.5" style="font-size: 0.72rem;"><?= View::e((string)$log['reference_type']) ?> #<?= (int)$log['reference_id'] ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="small">
                          <div class="fw-semibold"><?= View::e((string)$log['recipient']) ?></div>
                          <?php if ($log['message_id'] !== ''): ?>
                            <div class="text-muted font-monospace text-truncate" style="max-width: 240px; font-size: 0.7rem;"><?= View::e((string)$log['message_id']) ?></div>
                          <?php endif; ?>
                          <?php if ($log['error_message'] !== ''): ?>
                            <div class="text-danger small mt-0.5"><?= View::e((string)$log['error_message']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($log['status'] === 'sent'): ?>
                            <span class="badge-kkc badge-kkc-success">Gesendet</span>
                          <?php elseif ($log['status'] === 'sandbox'): ?>
                            <span class="badge-kkc badge-kkc-warning">Sandbox</span>
                          <?php elseif ($log['status'] === 'failed'): ?>
                            <span class="badge-kkc badge-kkc-danger">Fehler</span>
                          <?php else: ?>
                            <span class="badge-kkc badge-kkc-neutral"><?= View::e((string)$log['status']) ?></span>
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

        <!-- Rechte Spalte: PHP 8.0 / 8.4 Readiness & Systempfade -->
        <div class="col-12 col-xl-4">
          <div class="card-kkc p-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-cpu-fill me-2 ec-text-primary"></i>Server &amp; PHP-Konfiguration
            </h2>

            <table class="table table-sm align-middle small mb-3">
              <tbody>
                <tr>
                  <td class="text-muted">PHP-Version</td>
                  <td class="fw-bold font-monospace text-end"><?= View::e((string)$envInfo['php_version']) ?> (<?= View::e((string)$envInfo['php_sapi']) ?>)</td>
                </tr>
                <tr>
                  <td class="text-muted">Betriebssystem</td>
                  <td class="text-end"><?= View::e((string)$envInfo['os_info']) ?></td>
                </tr>
                <tr>
                  <td class="text-muted">Zeitzone / Serverzeit</td>
                  <td class="text-end"><?= View::e((string)$envInfo['timezone']) ?> (<?= View::e((string)$envInfo['server_time']) ?>)</td>
                </tr>
                <tr>
                  <td class="text-muted">memory_limit</td>
                  <td class="font-monospace text-end"><?= View::e((string)$envInfo['memory_limit']) ?></td>
                </tr>
                <tr>
                  <td class="text-muted">upload_max_filesize / post_max_size</td>
                  <td class="font-monospace text-end"><?= View::e((string)$envInfo['upload_max_filesize']) ?> / <?= View::e((string)$envInfo['post_max_size']) ?></td>
                </tr>
                <tr>
                  <td class="text-muted">max_execution_time</td>
                  <td class="font-monospace text-end"><?= View::e((string)$envInfo['max_execution_time']) ?></td>
                </tr>
              </tbody>
            </table>

            <div class="small fw-bold text-uppercase text-muted mb-2">Geladene PHP-Erweiterungen</div>
            <div class="d-flex flex-wrap gap-1.5 mb-0">
              <?php foreach ($envInfo['extensions'] as $extName => $loaded): ?>
                <span class="badge <?= $loaded ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger' ?>">
                  <i class="bi <?= $loaded ? 'bi-check-lg' : 'bi-x-lg' ?> me-1"></i><?= View::e((string)$extName) ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>
