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
 * @var string $ownAdminEmail
 * @var array<string, array<string, mixed>> $eventCatalog
 * @var array<int, array<string, string>> $triggerCatalog
 * @var array<string, array{total_merchants: int, opted_out: int, active: int}> $optInStats
 * @var array<string, int> $mailStats
 * @var array<int, array<string, mixed>> $deliveryLogs
 * @var array<string, mixed> $envInfo
 * @var array{subject: string, html: string, text: string} $initialPreview
 */

$isSandbox        = !empty($smtpConfig['is_sandbox']);
$sandboxRecipient = (string)($smtpConfig['sandbox_recipient'] ?? 'erocms@gmail.com');
$smtpSecure       = strtolower((string)($smtpConfig['smtp_secure'] ?? 'tls'));
$ownEmail         = !empty($ownAdminEmail) ? (string)$ownAdminEmail : $sandboxRecipient;
$supportEmail     = (string)($imprint['imprint_support_email'] ?? 'support@pay4coins.com');
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full" data-own-admin-email="<?= View::e($ownEmail) ?>">

  <!-- Seiten-Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div>
      <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-envelope-check-fill ec-text-primary"></i>
        <span>E-Mail-System, Postfach &amp; Impressum</span>
      </h1>
      <p class="text-muted small mb-0">
        Konfiguration des Versand-Postfachs (SMTP &amp; Sandbox), Übersicht aller E-Mail-Auslöser inkl. Direkt-Testversand, lückenloses Versand-Tracking sowie zentrale Impressumsdaten.
      </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="width: auto; max-width: 430px;">
        <span class="input-group-text bg-transparent text-muted small" title="Empfänger für Test-E-Mails (eigene oder beliebige andere Adresse)">
          <i class="bi bi-envelope-at me-1 ec-text-primary"></i>Test an:
        </span>
        <input type="email"
               id="sys-header-test-recipient"
               class="form-control form-control-sm font-monospace"
               style="min-width: 195px;"
               value="<?= View::e($ownEmail) ?>"
               placeholder="E-Mail-Adresse eingeben..."
               oninput="App.SystemTech.syncTestRecipient(this.value, 'header')">
        <button type="button"
                id="btn-header-send-testmail"
                class="btn btn-success fw-semibold"
                onclick="App.SystemTech.quickSendTestMail('movie_converted', this)"
                title="Sendet sofort eine Test-E-Mail an die eingegebene Adresse">
          <i class="bi bi-send-check-fill me-1"></i>Test-Mail senden
        </button>
      </div>

      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
      </button>
    </div>
  </div>

  <!-- 4 KPI-Kacheln -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted small fw-semibold">Versand-Postfach &amp; Modus</span>
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(2,132,199,0.12); color: #0284c7;">
              <i class="bi bi-hdd-network-fill"></i>
            </span>
          </div>
          <div class="h5 fw-bold mb-1 text-truncate font-monospace" id="kpi-smtp-host-line" title="<?= View::e((string)$smtpConfig['smtp_user']) ?> @ <?= View::e((string)$smtpConfig['smtp_host']) ?>:<?= (int)$smtpConfig['smtp_port'] ?>">
            <?= View::e((string)$smtpConfig['smtp_host']) ?>:<?= (int)$smtpConfig['smtp_port'] ?>
          </div>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 pt-1">
          <div id="kpi-smtp-mode-badge">
            <?php if ($isSandbox): ?>
              <span class="badge-kkc badge-kkc-warning"><i class="bi bi-shield-lock-fill"></i>Sandbox (&rarr; <?= View::e($sandboxRecipient) ?>)</span>
            <?php else: ?>
              <span class="badge-kkc badge-kkc-success"><i class="bi bi-broadcast"></i>Live-Versand aktiv</span>
            <?php endif; ?>
          </div>
          <span class="small text-muted font-monospace text-truncate" style="max-width: 150px;" title="Absender: <?= View::e((string)$smtpConfig['from_address']) ?>">
            <?= View::e((string)$smtpConfig['from_address']) ?>
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted small fw-semibold">E-Mail-Versandbilanz <code class="small ms-1">notification_log</code></span>
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(16,185,129,0.12); color: #10b981;">
              <i class="bi bi-send-check-fill"></i>
            </span>
          </div>
          <div class="h3 fw-bold mb-1" id="kpi-mail-total">
            <?= number_format($mailStats['total_sent'] + $mailStats['total_sandbox'], 0, ',', '.') ?>
          </div>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Heute: <strong id="kpi-mail-today"><?= number_format($mailStats['sent_today'], 0, ',', '.') ?></strong> (Sandbox: <span id="kpi-mail-sandbox"><?= number_format($mailStats['total_sandbox'], 0, ',', '.') ?></span>)</span>
          <span class="<?= $mailStats['total_failed'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
            Fehler: <span id="kpi-mail-failed"><?= number_format($mailStats['total_failed'], 0, ',', '.') ?></span>
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted small fw-semibold">Strategische E-Mail-Auslöser</span>
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(139,92,246,0.12); color: #8b5cf6;">
              <i class="bi bi-bell-fill"></i>
            </span>
          </div>
          <div class="h3 fw-bold mb-1"><?= count($triggerCatalog) ?> Auslöser</div>
        </div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>In <?= count($eventCatalog) ?> Opt-Out-Kategorien</span>
          <span>Individuelle Regeln: <strong><?= number_format($mailStats['prefs_count'], 0, ',', '.') ?></strong></span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted small fw-semibold">Server &amp; PHP-Laufzeit</span>
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(245,158,11,0.12); color: #f59e0b;">
              <i class="bi bi-hdd-rack-fill"></i>
            </span>
          </div>
          <div class="h4 fw-bold mb-1 font-monospace">PHP <?= View::e((string)$envInfo['php_version']) ?></div>
        </div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span class="text-truncate"><?= View::e((string)$envInfo['server_hostname']) ?></span>
          <a href="#/errors" class="text-decoration-none fw-semibold ec-text-primary">Error-Log &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <!-- 4 Haupt-Tabs -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold" id="tab-btn-sys-mailtest" data-bs-toggle="tab" data-bs-target="#tab-sys-mailtest" type="button" role="tab">
        <i class="bi bi-mailbox2 me-1"></i>1. Postfach &amp; SMTP-Einstellungen
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-sys-triggers" data-bs-toggle="tab" data-bs-target="#tab-sys-triggers" type="button" role="tab">
        <i class="bi bi-diagram-3-fill me-1"></i>2. Für was werden Mails versendet? (Auslöser)
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill ms-1"><?= count($triggerCatalog) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-sys-logs" data-bs-toggle="tab" data-bs-target="#tab-sys-logs" type="button" role="tab">
        <i class="bi bi-journal-check me-1"></i>3. Versand-Tracking (Wer / Wann / Für was)
        <span class="badge bg-secondary rounded-pill ms-1" id="tab-badge-sys-logs"><?= count($deliveryLogs) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-sys-imprint" data-bs-toggle="tab" data-bs-target="#tab-sys-imprint" type="button" role="tab">
        <i class="bi bi-building-gear me-1"></i>4. Impressum, Support &amp; Server
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ================================================================== -->
    <!-- TAB 1: Postfach & SMTP-Einstellungen + Vorlagen-Labor & Test       -->
    <!-- ================================================================== -->
    <div class="tab-pane fade show active" id="tab-sys-mailtest" role="tabpanel">
      <div class="row g-4">

        <!-- Linke Spalte: Editierbare Postfach-Einstellungen & Testversand -->
        <div class="col-12 col-xl-5">

          <!-- Card 1: Postfach- & SMTP-Einstellungen bearbeiten -->
          <div class="card-kkc p-4 mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div>
                <h2 class="h6 fw-bold mb-1">
                  <i class="bi bi-gear-wide-connected me-1 ec-text-primary"></i>Versand-Postfach &amp; SMTP-Zugangsdaten
                </h2>
                <p class="small text-muted mb-0">
                  Hier kannst du das ausgehende E-Mail-Postfach, die Absenderkennung sowie den Sandbox-Testmodus direkt einsehen und ändern.
                </p>
              </div>
            </div>

            <form id="sys-smtp-form" onsubmit="return App.SystemTech.saveSmtp(event);">
              <div class="row g-2.5">
                <div class="col-sm-6">
                  <label class="form-label small fw-semibold mb-1">SMTP-Server (Host)</label>
                  <input type="text" class="form-control form-control-sm font-monospace" name="smtp_host" value="<?= View::e((string)$smtpConfig['smtp_host']) ?>" placeholder="mail.erocloud.net" required>
                </div>
                <div class="col-sm-3">
                  <label class="form-label small fw-semibold mb-1">Port</label>
                  <input type="number" class="form-control form-control-sm font-monospace" name="smtp_port" value="<?= (int)$smtpConfig['smtp_port'] ?>" min="1" max="65535" required>
                </div>
                <div class="col-sm-3">
                  <label class="form-label small fw-semibold mb-1">Verschlüsselung</label>
                  <select class="form-select form-select-sm" name="smtp_secure">
                    <option value="tls" <?= $smtpSecure === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                    <option value="ssl" <?= $smtpSecure === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option>
                    <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>>Keine</option>
                  </select>
                </div>

                <div class="col-sm-6 mt-2">
                  <label class="form-label small fw-semibold mb-1">SMTP-Benutzername (Postfach)</label>
                  <input type="text" class="form-control form-control-sm font-monospace" name="smtp_user" value="<?= View::e((string)$smtpConfig['smtp_user']) ?>" required>
                </div>
                <div class="col-sm-6 mt-2">
                  <label class="form-label small fw-semibold mb-1">
                    SMTP-Passwort
                    <?php if (!empty($smtpConfig['has_password'])): ?>
                      <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size: 0.65rem;">Hinterlegt (AES)</span>
                    <?php endif; ?>
                  </label>
                  <input type="password" class="form-control form-control-sm font-monospace" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= !empty($smtpConfig['has_password']) ? '•••••••• (leer lassen = beibehalten)' : 'Passwort eingeben...' ?>">
                </div>

                <div class="col-sm-6 mt-2">
                  <label class="form-label small fw-semibold mb-1">Absender-Name (From Name)</label>
                  <input type="text" class="form-control form-control-sm" name="from_name" value="<?= View::e((string)$smtpConfig['from_name']) ?>" required>
                </div>
                <div class="col-sm-6 mt-2">
                  <label class="form-label small fw-semibold mb-1">Absender-Adresse (From E-Mail)</label>
                  <input type="email" class="form-control form-control-sm font-monospace" name="from_address" value="<?= View::e((string)$smtpConfig['from_address']) ?>" required>
                </div>

                <div class="col-12 mt-3">
                  <div class="p-3 rounded border" style="background: <?= $isSandbox ? 'rgba(245, 158, 11, 0.08)' : 'rgba(16, 185, 129, 0.08)' ?>; border-color: <?= $isSandbox ? 'rgba(245, 158, 11, 0.35)' : 'rgba(16, 185, 129, 0.35)' ?> !important;">
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" role="switch" id="smtp-sandbox-switch" name="sandbox_mode" value="1" <?= $isSandbox ? 'checked' : '' ?> onchange="App.SystemTech.debouncedPreviewMail()">
                      <label class="form-check-label small fw-bold" for="smtp-sandbox-switch">
                        Sandbox-Testmodus aktivieren (Automatische Partner-Mails abfangen)
                      </label>
                    </div>
                    <div class="small text-muted mb-2">
                      Solange der Sandbox-Modus aktiv ist, geht im regulären Systembetrieb <strong>keine einzige E-Mail an echte Partner raus</strong>, sondern alle Mails werden sicher an das unten stehende Test-Postfach umgeleitet.
                    </div>
                    <div>
                      <label class="form-label small fw-semibold mb-1">Sandbox-Empfängeradresse (Test-Postfach)</label>
                      <input type="email" class="form-control form-control-sm font-monospace" name="sandbox_recipient" id="smtp-sandbox-recipient-input" value="<?= View::e($sandboxRecipient) ?>" placeholder="erocms@gmail.com">
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.SystemTech.quickSendTestMail(null, this)">
                  <i class="bi bi-send-check me-1 ec-text-primary"></i>Postfach mit Test-Mail prüfen
                </button>
                <button type="submit" id="btn-save-smtp" class="btn btn-sm btn-primary-kkc px-3">
                  <i class="bi bi-floppy-fill me-1"></i>Postfach- &amp; SMTP-Einstellungen speichern
                </button>
              </div>
            </form>
          </div>

          <!-- Card 2: Interaktiver Test-Versand & Vorlagen-Labor -->
          <div class="card-kkc p-4">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
              <h2 class="h6 fw-bold mb-0">
                <i class="bi bi-send-fill me-1 ec-text-primary"></i>Vorlagen-Labor &amp; Live-Testversand
              </h2>
              <span class="badge-kkc badge-kkc-info" id="sys-testmail-recip-badge">
                <i class="bi bi-person-check-fill"></i>Eigene E-Mail aktiv
              </span>
            </div>
            <p class="small text-muted mb-3">
              Wähle eine der 10 System-Vorlagen aus und sende eine echte Test-E-Mail an <strong>deine eigene E-Mail-Adresse</strong> oder optional an eine <strong>beliebige andere E-Mail-Adresse</strong>.
            </p>

            <form id="sys-testmail-form" onsubmit="return App.SystemTech.sendOrPreviewTestMail(event, 'send');">
              <!-- Hervorgehobene Empfänger-Box (Eigene E-Mail oder andere E-Mail) -->
              <div class="p-3 rounded border mb-3" style="background: rgba(2, 132, 199, 0.05); border-color: rgba(2, 132, 199, 0.28) !important;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-1.5">
                  <label class="form-label small fw-bold mb-0" for="sys-testmail-recipient">
                    <i class="bi bi-envelope-at-fill me-1 ec-text-primary"></i>Test-E-Mail senden an (Empfänger-Adresse)
                  </label>
                  <span class="small text-muted" style="font-size: 0.72rem;">Direktversand (unabhängig vom Sandbox-Modus)</span>
                </div>

                <div class="input-group input-group-sm mb-2">
                  <input type="email"
                         class="form-control font-monospace fw-semibold"
                         name="to_email"
                         id="sys-testmail-recipient"
                         value="<?= View::e($ownEmail) ?>"
                         placeholder="Deine E-Mail oder eine andere Zieladresse..."
                         required
                         oninput="App.SystemTech.syncTestRecipient(this.value, 'lab'); App.SystemTech.debouncedPreviewMail();">
                  <button type="button"
                          class="btn btn-outline-kkc"
                          onclick="App.SystemTech.setTestRecipient('<?= View::e($ownEmail) ?>')"
                          title="Auf eigene Standard-E-Mail-Adresse zurücksetzen">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Eigene E-Mail
                  </button>
                </div>

                <!-- 1-Klick Schnellwahl-Pills -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                  <div class="d-flex flex-wrap align-items-center gap-1">
                    <span class="text-muted" style="font-size: 0.72rem;">Schnellwahl:</span>
                    <button type="button"
                            class="btn btn-xs btn-outline-kkc py-0 px-2 font-monospace"
                            style="font-size: 0.72rem;"
                            onclick="App.SystemTech.setTestRecipient('<?= View::e($ownEmail) ?>')">
                      <i class="bi bi-person-fill me-1"></i><?= View::e($ownEmail) ?>
                    </button>
                    <?php if ($supportEmail !== '' && strtolower($supportEmail) !== strtolower($ownEmail)): ?>
                      <button type="button"
                              class="btn btn-xs btn-outline-kkc py-0 px-2 font-monospace"
                              style="font-size: 0.72rem;"
                              onclick="App.SystemTech.setTestRecipient('<?= View::e($supportEmail) ?>')">
                        <i class="bi bi-headset me-1"></i><?= View::e($supportEmail) ?>
                      </button>
                    <?php endif; ?>
                  </div>

                  <button type="button"
                          class="btn btn-link btn-sm p-0 text-decoration-none small ec-text-primary"
                          style="font-size: 0.73rem;"
                          onclick="App.SystemTech.saveDefaultTestRecipient()"
                          title="Speichert die aktuell eingegebene E-Mail-Adresse dauerhaft als deine Standard-Testadresse">
                    <i class="bi bi-star me-1"></i>Als meine Standard-Adresse merken
                  </button>
                </div>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-12">
                  <label class="form-label small fw-semibold mb-1">E-Mail-Vorlage / Auslöser wählen</label>
                  <select class="form-select form-select-sm fw-semibold" name="template_key" id="sys-testmail-template" onchange="App.SystemTech.sendOrPreviewTestMail(null, 'preview')">
                    <optgroup label="Kategorie 1: Video-Konvertierung & Cover-Studio">
                      <option value="movie_converted">1. Video fertig konvertiert &amp; 10 Vorschaubilder bereit</option>
                    </optgroup>
                    <optgroup label="Kategorie 2: Prüfungs-Status (Filme, Foto-Alben & Darsteller)">
                      <option value="movie_approved">2. Film geprüft &amp; freigegeben (Approved)</option>
                      <option value="movie_rejected">3. Film abgelehnt / Korrekturhinweis (Rejected)</option>
                      <option value="album_approved">4. Foto-Album geprüft &amp; freigegeben (Approved)</option>
                      <option value="album_rejected">5. Foto-Album abgelehnt / Korrekturhinweis (Rejected)</option>
                      <option value="actor_approved">6. Darsteller-Profil geprüft &amp; aktiviert (Approved)</option>
                      <option value="actor_rejected">7. Darsteller-Profil gesperrt / Rückfrage (Rejected)</option>
                    </optgroup>
                    <optgroup label="Kategorie 3: Drehpartner & 18+ ID-Check">
                      <option value="co_actor_submitted">8. Drehpartner hat Smartphone-QR-Verifizierung eingereicht</option>
                      <option value="co_actor_verified">9. Drehpartner / 18+ ID-Check im ACP bestätigt (Verified)</option>
                      <option value="co_actor_rejected">10. Drehpartner / 18+ ID-Check abgelehnt (Neuaufnahme nötig)</option>
                    </optgroup>
                  </select>
                </div>

                <div class="col-sm-6">
                  <label class="form-label small fw-semibold mb-1">Creator-Name (Du-Form)</label>
                  <input type="text" class="form-control form-control-sm" name="creator_name" value="Lara" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small fw-semibold mb-1">Händler-ID (MID für Magic-Link)</label>
                  <input type="number" class="form-control form-control-sm font-monospace" name="merchant_id" value="10061" oninput="App.SystemTech.debouncedPreviewMail()">
                </div>
                <div class="col-sm-4">
                  <label class="form-label small fw-semibold mb-1">Referenz-ID (#ID)</label>
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
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.SystemTech.sendOrPreviewTestMail(null, 'preview')">
                  <i class="bi bi-eye me-1"></i>Vorschau aktualisieren
                </button>
                <button type="submit" id="btn-send-testmail" class="btn btn-sm btn-success fw-semibold flex-grow-1">
                  <i class="bi bi-send-check-fill me-1"></i>Ausgewählte Vorlage als Test-E-Mail senden
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
                <h2 class="h6 fw-bold mb-0">Live-E-Mail-Vorschau (Multipart HTML &amp; Plaintext)</h2>
                <div class="small text-muted">Betreff: <strong id="sys-mail-preview-subject"><?= View::e($initialPreview['subject']) ?></strong></div>
              </div>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-primary-kkc" id="btn-view-mail-html" onclick="App.SystemTech.switchMailPreviewMode('html')">HTML-Ansicht</button>
                <button type="button" class="btn btn-outline-kkc" id="btn-view-mail-text" onclick="App.SystemTech.switchMailPreviewMode('text')">Plaintext (AltBody)</button>
              </div>
            </div>

            <div id="sys-mail-preview-html-wrap" class="border rounded overflow-hidden flex-grow-1 bg-white" style="min-height: 640px;">
              <iframe id="sys-mail-preview-iframe" title="E-Mail HTML Vorschau" class="w-100 h-100 border-0" style="min-height: 640px;" srcdoc="<?= View::e($initialPreview['html']) ?>"></iframe>
            </div>

            <div id="sys-mail-preview-text-wrap" class="d-none border rounded p-3 bg-dark text-light font-monospace small flex-grow-1 overflow-auto" style="min-height: 640px; white-space: pre-wrap;"><?= View::e($initialPreview['text']) ?></div>
          </div>
        </div>

      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 2: Für was werden E-Mails versendet? (Auslöser & Kategorien)   -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-sys-triggers" role="tabpanel">

      <!-- Sektion A: Die 4 abwählbaren Partner-Kategorien (Opt-Out Matrix) -->
      <div class="card-kkc p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h2 class="h6 fw-bold mb-1">
              <i class="bi bi-sliders2-vertical me-1 ec-text-primary"></i>Partner-Benachrichtigungs-Kategorien (Opt-Out Möglichkeiten im MCP)
            </h2>
            <p class="small text-muted mb-0">
              Partner können im MCP unter <strong>Mein Profil &rarr; Tab „Benachrichtigungen“</strong> sowie über den <strong>1-Klick-Abmeldelink</strong> in jeder E-Mail selbst entscheiden, welche Kategorien sie erhalten möchten.
            </p>
          </div>
        </div>

        <div class="row g-3">
          <?php foreach ($eventCatalog as $evKey => $evMeta): ?>
            <?php
              $stat       = $optInStats[$evKey] ?? ['total_merchants' => 0, 'opted_out' => 0, 'active' => 0];
              $totalM     = max(1, (int)$stat['total_merchants']);
              $activeM    = (int)$stat['active'];
              $optedOutM  = (int)$stat['opted_out'];
              $activePct  = min(100, max(0, (int)round(($activeM / $totalM) * 100)));
              $evIcon     = (string)($evMeta['icon'] ?? 'bi-bell');
            ?>
            <div class="col-12 col-md-6 col-xl-3">
              <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between" style="border-color: var(--kkc-border) !important; background: var(--kkc-surface-hover);">
                <div>
                  <div class="d-flex align-items-center justify-content-between gap-2 mb-2.5">
                    <div class="d-flex align-items-center gap-2">
                      <span class="rounded d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px; background: rgba(2,132,199,0.12); color: var(--kkc-primary);">
                        <i class="bi <?= View::e($evIcon) ?>"></i>
                      </span>
                      <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace"><?= View::e($evKey) ?></span>
                    </div>
                    <span class="badge-kkc badge-kkc-success" style="font-size: 0.68rem;">Standard: Aktiv</span>
                  </div>
                  <div class="fw-bold mb-1"><?= View::e((string)$evMeta['title']) ?></div>
                  <p class="small text-muted mb-3"><?= View::e((string)$evMeta['description']) ?></p>
                </div>
                <div class="pt-2 border-top" style="border-color: var(--kkc-border) !important;">
                  <div class="progress mb-1.5" style="height: 5px; background: rgba(148,163,184,0.2);">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $activePct ?>%;" aria-valuenow="<?= $activePct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between small">
                    <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i><?= $activeM ?> aktiv (<?= $activePct ?>%)</span>
                    <span class="text-muted"><i class="bi bi-slash-circle me-1"></i><?= $optedOutM ?> abgemeldet</span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Sektion B: Alle 10 strategischen E-Mail-Auslöser im Detail + Direkt-Testversand -->
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-color: var(--kkc-border) !important;">
          <div>
            <h2 class="h6 fw-bold mb-0.5">
              <i class="bi bi-lightning-charge-fill me-1 ec-text-primary"></i>Vollständiges Verzeichnis aller automatischen System-E-Mails (<?= count($triggerCatalog) ?> Auslöser)
            </h2>
            <small class="text-muted">
              Hier kannst du nachlesen, wann welche System-E-Mail ausgelöst wird, und <strong>jede Vorlage direkt als Test-Mail an deine eigene oder eine andere E-Mail-Adresse senden</strong>.
            </small>
          </div>

          <!-- Direkt-Testversand-Leiste für Tab 2 -->
          <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded border" style="background: rgba(2, 132, 199, 0.05); border-color: rgba(2, 132, 199, 0.25) !important;">
            <span class="small fw-semibold text-nowrap ps-1">
              <i class="bi bi-envelope-paper-fill me-1 ec-text-primary"></i>Test-Mail Empfänger:
            </span>
            <div class="input-group input-group-sm" style="width: auto;">
              <input type="email"
                     id="sys-trigger-test-recipient"
                     class="form-control form-control-sm font-monospace fw-semibold"
                     style="min-width: 220px;"
                     value="<?= View::e($ownEmail) ?>"
                     placeholder="Deine oder eine andere E-Mail..."
                     oninput="App.SystemTech.syncTestRecipient(this.value, 'triggers')">
              <button type="button"
                      class="btn btn-outline-kkc"
                      onclick="App.SystemTech.setTestRecipient('<?= View::e($ownEmail) ?>')"
                      title="Auf eigene E-Mail-Adresse (<?= View::e($ownEmail) ?>) setzen">
                <i class="bi bi-person-check me-1"></i>Eigene E-Mail
              </button>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th style="width: 24%;">E-Mail-Anlass / Ereignis</th>
                <th style="width: 31%;">Wann &amp; wodurch wird die E-Mail ausgelöst?</th>
                <th style="width: 16%;">Opt-Out-Kategorie</th>
                <th style="width: 15%;">Magic-Link Ziel (Auto-Login)</th>
                <th class="text-end" style="width: 14%;">Vorschau &amp; Test-Mail</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($triggerCatalog as $trig): ?>
                <?php
                  $evKey    = (string)$trig['event_key'];
                  $evTitle  = isset($eventCatalog[$evKey]) ? (string)$eventCatalog[$evKey]['title'] : $evKey;
                  $pVariant = (string)$trig['preview_variant'];
                  $rawMagic = (string)$trig['magic_link_target'];
                  $magicRoute = $rawMagic;
                  $magicDesc  = '';
                  if (preg_match('/^([^\s]+)\s+\((.+)\)$/', $rawMagic, $mMag)) {
                      $magicRoute = $mMag[1];
                      $magicDesc  = $mMag[2];
                  }
                ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-start gap-2.5">
                      <span class="rounded d-inline-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 32px; height: 32px; background: rgba(2,132,199,0.10); color: var(--kkc-primary);">
                        <i class="bi <?= View::e((string)$trig['icon']) ?>"></i>
                      </span>
                      <div>
                        <div class="fw-bold"><?= View::e((string)$trig['title']) ?></div>
                        <span class="badge <?= View::e((string)$trig['badge_cls']) ?> mt-1"><?= View::e((string)$trig['badge_label']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="small text-muted">
                    <?= View::e((string)$trig['when']) ?>
                  </td>
                  <td class="small">
                    <div class="fw-semibold"><?= View::e($evTitle) ?></div>
                    <code class="small text-muted"><?= View::e($evKey) ?></code>
                  </td>
                  <td class="small">
                    <div class="mb-0.5">
                      <span class="badge bg-secondary bg-opacity-10 text-body border font-monospace">
                        <i class="bi bi-magic me-1 ec-text-primary"></i><?= View::e($magicRoute) ?>
                      </span>
                    </div>
                    <?php if ($magicDesc !== ''): ?>
                      <div class="text-muted" style="font-size: 0.74rem;"><?= View::e($magicDesc) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-wrap">
                      <button type="button"
                              class="btn btn-xs btn-outline-kkc py-1 px-2 small"
                              onclick="App.SystemTech.openTriggerPreviewModal('<?= View::e($pVariant) ?>')"
                              title="Vorschau dieser E-Mail-Vorlage öffnen">
                        <i class="bi bi-eye me-1"></i>Vorschau
                      </button>
                      <button type="button"
                              class="btn btn-xs btn-success py-1 px-2 small fw-semibold"
                              onclick="App.SystemTech.quickSendTestMail('<?= View::e($pVariant) ?>', this)"
                              title="Diese Vorlage jetzt als Test-Mail an die oben eingetragene E-Mail-Adresse senden">
                        <i class="bi bi-send-check me-1"></i>Test-Mail
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ================================================================== -->
    <!-- TAB 3: Versand-Tracking (Wer hat wann welche Mail für was bekommen)-->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-sys-logs" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
          <div>
            <h2 class="h6 fw-bold mb-0">
              <i class="bi bi-list-check me-1 ec-text-primary"></i>Versand-Tracking &amp; Historie <code class="small ms-1">notification_log</code>
            </h2>
            <small class="text-muted">Lückenloser Nachweis, <strong>wer</strong> (Partner) <strong>wann</strong> (Datum/Uhrzeit) <strong>welche E-Mail</strong> <strong>für was</strong> (Auslöser &amp; Objekt-ID) erhalten hat.</small>
          </div>

          <div class="d-flex flex-wrap align-items-center gap-2">
            <select id="sys-notif-filter-event" class="form-select form-select-sm" style="width: 210px;" onchange="App.SystemTech.filterNotificationLog()">
              <option value="">Alle Kategorien</option>
              <option value="movie_converted">Video fertig konvertiert</option>
              <option value="content_reviewed">Prüfungs-Status (Film/Foto/Actor)</option>
              <option value="co_actor_status">Drehpartner / 18+ ID-Check</option>
            </select>

            <select id="sys-notif-filter-status" class="form-select form-select-sm" style="width: 160px;" onchange="App.SystemTech.filterNotificationLog()">
              <option value="">Alle Status</option>
              <option value="sent">Live gesendet</option>
              <option value="sandbox">Sandbox / Test</option>
              <option value="failed">Fehler</option>
            </select>

            <input type="text" id="sys-notif-filter-query" class="form-control form-control-sm" style="width: 240px;" placeholder="Partner, E-Mail, Betreff, #ID..." oninput="App.SystemTech.filterNotificationLog()">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th>ID / Wann</th>
                <th>Wer (Partner / Creator)</th>
                <th>Für was (Auslöser &amp; Objekt)</th>
                <th>Empfänger &amp; E-Mail-Betreff</th>
                <th>Status</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody id="sys-notif-log-tbody">
              <?php if (empty($deliveryLogs)): ?>
                <tr id="sys-notif-log-empty">
                  <td colspan="6" class="text-center text-muted py-4">Noch keine Einträge im Versand-Tracking (<code>notification_log</code>) vorhanden.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($deliveryLogs as $log): ?>
                  <?php
                    $statusRaw = (string)$log['status'];
                    $statusNorm = in_array($statusRaw, ['sandbox', 'sandbox_sent'], true) ? 'sandbox' : $statusRaw;
                    $hay = mb_strtolower(
                        $log['id'] . ' ' .
                        $log['merchant_id'] . ' ' .
                        $log['partner_id'] . ' ' .
                        $log['merchant_username'] . ' ' .
                        $log['event_key'] . ' ' .
                        $log['trigger_label'] . ' ' .
                        $log['reference_label'] . ' ' .
                        $log['recipient'] . ' ' .
                        $log['intended_recipient'] . ' ' .
                        $log['subject'] . ' ' .
                        $log['error_message'],
                        'UTF-8'
                    );
                  ?>
                  <tr class="sys-notif-log-row"
                      data-search="<?= View::e($hay) ?>"
                      data-event="<?= View::e((string)$log['event_key']) ?>"
                      data-status="<?= View::e($statusNorm) ?>">
                    <td class="small">
                      <div class="font-monospace fw-bold">#<?= (int)$log['id'] ?></div>
                      <div class="text-muted" style="font-size: 0.74rem;"><?= View::e((string)$log['created_at_de']) ?></div>
                    </td>

                    <td class="small">
                      <?php if ($log['merchant_id'] > 0): ?>
                        <div class="fw-bold">
                          <a href="#/merchant?id=<?= (int)$log['merchant_id'] ?>" class="text-decoration-none">
                            <?= View::e($log['merchant_username'] !== '' ? $log['merchant_username'] : ('Partner #' . (int)$log['merchant_id'])) ?>
                          </a>
                        </div>
                        <div class="text-muted font-monospace" style="font-size: 0.73rem;">
                          MID #<?= (int)$log['merchant_id'] ?>
                          <?php if ($log['partner_id'] !== ''): ?>
                            &bull; <?= View::e((string)$log['partner_id']) ?>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">System / Test</span>
                      <?php endif; ?>
                    </td>

                    <td class="small">
                      <div>
                        <span class="badge <?= View::e((string)$log['trigger_badge_cls']) ?>"><?= View::e((string)$log['trigger_label']) ?></span>
                      </div>
                      <?php if ($log['reference_label'] !== ''): ?>
                        <div class="mt-1" style="font-size: 0.75rem;">
                          <?php if ($log['reference_link'] !== ''): ?>
                            <a href="<?= View::e((string)$log['reference_link']) ?>" class="font-monospace fw-semibold text-decoration-none">
                              <i class="bi bi-box-arrow-up-right me-1"></i><?= View::e((string)$log['reference_label']) ?>
                            </a>
                          <?php else: ?>
                            <span class="font-monospace text-muted"><?= View::e((string)$log['reference_label']) ?></span>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td class="small">
                      <?php if ($log['subject'] !== ''): ?>
                        <div class="fw-semibold text-truncate" style="max-width: 360px;" title="<?= View::e((string)$log['subject']) ?>">
                          <?= View::e((string)$log['subject']) ?>
                        </div>
                      <?php endif; ?>
                      <div class="font-monospace text-muted" style="font-size: 0.74rem;">
                        <?php if ($log['intended_recipient'] !== '' && $log['intended_recipient'] !== $log['recipient']): ?>
                          Ziel: <strong><?= View::e((string)$log['intended_recipient']) ?></strong>
                          <span class="text-warning">(Sandbox &rarr; <?= View::e((string)$log['recipient']) ?>)</span>
                        <?php else: ?>
                          An: <strong><?= View::e((string)$log['recipient']) ?></strong>
                        <?php endif; ?>
                      </div>
                      <?php if ($log['error_message'] !== ''): ?>
                        <div class="text-danger small mt-0.5"><i class="bi bi-exclamation-octagon me-1"></i><?= View::e((string)$log['error_message']) ?></div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <?php if ($statusRaw === 'sent'): ?>
                        <span class="badge-kkc badge-kkc-success">Live gesendet</span>
                      <?php elseif (in_array($statusRaw, ['sandbox', 'sandbox_sent'], true)): ?>
                        <span class="badge-kkc badge-kkc-warning">Sandbox / Test</span>
                      <?php elseif ($statusRaw === 'failed'): ?>
                        <span class="badge-kkc badge-kkc-danger">Fehler</span>
                      <?php else: ?>
                        <span class="badge-kkc badge-kkc-neutral"><?= View::e($statusRaw) ?></span>
                      <?php endif; ?>
                    </td>

                    <td class="text-end">
                      <?php if (!empty($log['has_body'])): ?>
                        <button type="button" class="btn btn-xs btn-outline-kkc py-1 px-2 small" onclick="App.SystemTech.viewSentMail(<?= (int)$log['id'] ?>)">
                          <i class="bi bi-envelope-open me-1"></i>E-Mail ansehen
                        </button>
                      <?php else: ?>
                        <span class="text-muted small">-</span>
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

    <!-- ================================================================== -->
    <!-- TAB 4: Zentrale Impressums- & Support-Daten + Server-Umgebung      -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-sys-imprint" role="tabpanel">
      <div class="row g-4">

        <!-- Linke Spalte: Eingabeformular -->
        <div class="col-12 col-xl-7">
          <div class="card-kkc p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div>
                <h2 class="h6 fw-bold mb-1">Zentrale Impressums- &amp; Support-Daten (Pay4Coins / EroCloud)</h2>
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

        <!-- Rechte Spalte: Echtzeit-Vorschau des E-Mail-Footers + PHP-Umgebung -->
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
          </div>

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

<!-- Modal 1: Auslöser-Vorschau & Direkt-Testversand (aus Tab 2) -->
<div class="modal fade" id="sysTriggerPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="sysTriggerPreviewModalTitle">E-Mail-Vorlage Vorschau</h5>
          <div class="small text-muted">Betreff: <strong id="sysTriggerPreviewModalSubject"></strong></div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-primary-kkc" id="btn-modal-view-html" onclick="App.SystemTech.switchModalPreviewMode('html')">HTML-Ansicht</button>
            <button type="button" class="btn btn-outline-kkc" id="btn-modal-view-text" onclick="App.SystemTech.switchModalPreviewMode('text')">Plaintext</button>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
      </div>
      <div class="modal-body p-0 bg-light" style="min-height: 540px;">
        <iframe id="sysTriggerPreviewModalIframe" title="E-Mail-Vorlage Vorschau" class="w-100 border-0" style="min-height: 540px; height: 65vh;"></iframe>
        <div id="sysTriggerPreviewModalText" class="d-none p-3 bg-dark text-light font-monospace small overflow-auto" style="min-height: 540px; height: 65vh; white-space: pre-wrap;"></div>
      </div>
      <div class="modal-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="small fw-semibold"><i class="bi bi-envelope-at me-1 ec-text-primary"></i>Test-Mail senden an:</span>
          <div class="input-group input-group-sm" style="width: auto;">
            <input type="email"
                   id="sys-modal-test-recipient"
                   class="form-control form-control-sm font-monospace fw-semibold"
                   style="min-width: 220px;"
                   value="<?= View::e($ownEmail) ?>"
                   placeholder="E-Mail-Adresse..."
                   oninput="App.SystemTech.syncTestRecipient(this.value, 'modal')">
            <button type="button" class="btn btn-outline-kkc" onclick="App.SystemTech.setTestRecipient('<?= View::e($ownEmail) ?>')">
              Eigene E-Mail
            </button>
          </div>
          <button type="button"
                  id="btn-modal-send-testmail"
                  class="btn btn-sm btn-success fw-semibold"
                  onclick="App.SystemTech.quickSendTestMail(App.SystemTech.currentModalTemplateKey, this)">
            <i class="bi bi-send-check-fill me-1"></i>Jetzt als Test-Mail senden
          </button>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.SystemTech.openInLabFromModal()">
            <i class="bi bi-sliders me-1"></i>Im Vorlagen-Labor anpassen
          </button>
          <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal 2: Gesendete E-Mail aus dem Tracking-Protokoll im Detail ansehen -->
<div class="modal fade" id="sysSentMailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="sysSentMailModalTitle">Gesendete E-Mail</h5>
          <div class="small text-muted" id="sysSentMailModalMeta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body p-0 bg-light" style="min-height: 560px;">
        <iframe id="sysSentMailModalIframe" title="Gesendete E-Mail Ansicht" class="w-100 border-0" style="min-height: 560px; height: 68vh;"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
