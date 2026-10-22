<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

include_once SOURCEDIR . '/includes/klassen/EroCloudMailer.inc.php';

$statusMessage = '';
$statusType    = 'success';

// 1. Speichern der Impressums- & Support-Daten in der Datenbank (`settings`)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_imprint_settings') {
    $input = [
        'imprint_company_name'     => (string)($_POST['imprint_company_name'] ?? ''),
        'imprint_address_street'   => (string)($_POST['imprint_address_street'] ?? ''),
        'imprint_address_city'     => (string)($_POST['imprint_address_city'] ?? ''),
        'imprint_register_court'   => (string)($_POST['imprint_register_court'] ?? ''),
        'imprint_register_details' => (string)($_POST['imprint_register_details'] ?? ''),
        'imprint_vat_id'           => (string)($_POST['imprint_vat_id'] ?? ''),
        'imprint_ceo'              => (string)($_POST['imprint_ceo'] ?? ''),
        'imprint_phone'            => (string)($_POST['imprint_phone'] ?? ''),
        'imprint_whatsapp_url'     => (string)($_POST['imprint_whatsapp_url'] ?? ''),
        'imprint_support_email'    => (string)($_POST['imprint_support_email'] ?? ''),
        'imprint_website_url'      => (string)($_POST['imprint_website_url'] ?? ''),
        'imprint_external_url'     => (string)($_POST['imprint_external_url'] ?? ''),
    ];

    if (EroCloudMailer::saveImprintData($input)) {
        if (function_exists('log_action')) {
            log_action('Zentrale Impressums- & Support-Einstellungen aktualisiert.');
        }
        $statusMessage = 'Die zentralen Impressums- und Support-Daten wurden erfolgreich in der Datenbank gespeichert.';
        $statusType    = 'success';
    } else {
        $statusMessage = 'Fehler beim Speichern der Einstellungen in der Datenbank.';
        $statusType    = 'error';
    }
}

// 2. Test-E-Mail an erocms@gmail.com absenden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_test_email') {
    $mailer = new EroCloudMailer();
    $ok = $mailer->sendMovieConvertedNotification([
        'merchant_id'  => 10061,
        'to_email'     => 'creator-beispiel@domain.tld',
        'creator_name' => 'Lara',
        'movie_id'     => 48291,
        'movie_title'  => 'Heisses Shooting am Pool (4K)',
        'playtime'     => '18:45',
        'resolution'   => '1920x1080 (Full-HD)',
    ]);

    if ($ok) {
        $statusMessage = 'Test-E-Mail wurde erfolgreich an ' . htmlspecialchars($mailer->effectiveRecipient, ENT_QUOTES, 'UTF-8') . ' versendet (Message-ID: ' . htmlspecialchars($mailer->lastMessageId, ENT_QUOTES, 'UTF-8') . ').';
        $statusType    = 'success';
    } else {
        $statusMessage = 'Versand fehlgeschlagen: ' . htmlspecialchars($mailer->lastError, ENT_QUOTES, 'UTF-8');
        $statusType    = 'error';
    }
}

$imprint = EroCloudMailer::getImprintData(true);
$isSandbox = (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true);
$sandboxRecipient = defined('MAIL_SANDBOX_RECIPIENT') ? (string)MAIL_SANDBOX_RECIPIENT : 'erocms@gmail.com';
$smtpHost = defined('MAIL_SMTP_HOST') ? (string)MAIL_SMTP_HOST : 'mail.erocloud.net';
$smtpPort = defined('MAIL_SMTP_PORT') ? (int)MAIL_SMTP_PORT : 587;
$smtpUser = defined('MAIL_SMTP_USER') ? (string)MAIL_SMTP_USER : 'noreply@erocloud.net';

$site .= '
<div style="max-width: 1280px; margin-top: 15px; font-family: Arial, sans-serif;">
    <div class="ui-widget-header" style="padding: 12px 16px; font-size: 16px; font-weight: bold;">
        Zentrales Impressum, Support &amp; E-Mail-System (PHPMailer)
    </div>
    <div class="ui-widget-content" style="padding: 20px;">';

if ($statusMessage !== '') {
    $bgColor     = ($statusType === 'success') ? '#DCFCE7' : '#FEE2E2';
    $borderColor = ($statusType === 'success') ? '#22C55E' : '#EF4444';
    $textColor   = ($statusType === 'success') ? '#166534' : '#991B1B';
    $site .= '
        <div style="background-color: ' . $bgColor . '; border: 1px solid ' . $borderColor . '; color: ' . $textColor . '; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: bold;">
            ' . $statusMessage . '
        </div>';
}

$site .= '
        <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start;">

            <!-- Linke Spalte: Zentrales Impressum & Support Formular -->
            <div style="flex: 1 1 640px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px; padding: 20px;">
                <h3 style="margin: 0 0 8px 0; font-size: 16px; color: #0F172A;">
                    1. Zentrale Impressums- &amp; Support-Daten (Pay4Coins / EroCloud)
                </h3>
                <p style="margin: 0 0 18px 0; font-size: 13px; color: #64748B; line-height: 1.5;">
                    Diese Angaben werden zentral in der Datenbank (Tabelle <code>settings</code>) gespeichert und automatisch im <strong>Footer aller ausgehenden EroCloud System-E-Mails</strong> sowie im <strong>Partner-Bereich (MCP)</strong> verwendet.
                </p>

                <form method="post" action="">
                    <input type="hidden" name="action" value="save_imprint_settings">

                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 7px 10px 7px 0; width: 220px; font-weight: bold; font-size: 13px; color: #334155;">Betreiberfirma:</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_company_name" value="' . htmlspecialchars((string)$imprint['imprint_company_name'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Straße / Büro:</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_address_street" value="' . htmlspecialchars((string)$imprint['imprint_address_street'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">PLZ / Ort / Land:</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_address_city" value="' . htmlspecialchars((string)$imprint['imprint_address_city'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Handelsregister:</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_register_court" value="' . htmlspecialchars((string)$imprint['imprint_register_court'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Register-Eintrag (Tomo/Folio/Hoja):</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_register_details" value="' . htmlspecialchars((string)$imprint['imprint_register_details'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Steuernummer / USt-ID (CIF):</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_vat_id" value="' . htmlspecialchars((string)$imprint['imprint_vat_id'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Geschäftsführung:</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_ceo" value="' . htmlspecialchars((string)$imprint['imprint_ceo'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Support E-Mail-Adresse:</td>
                            <td style="padding: 7px 0;">
                                <input type="email" name="imprint_support_email" value="' . htmlspecialchars((string)$imprint['imprint_support_email'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Tel / WhatsApp (Business):</td>
                            <td style="padding: 7px 0;">
                                <input type="text" name="imprint_phone" value="' . htmlspecialchars((string)$imprint['imprint_phone'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">WhatsApp Direktlink:</td>
                            <td style="padding: 7px 0;">
                                <input type="url" name="imprint_whatsapp_url" value="' . htmlspecialchars((string)$imprint['imprint_whatsapp_url'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Haupt-Website URL:</td>
                            <td style="padding: 7px 0;">
                                <input type="url" name="imprint_website_url" value="' . htmlspecialchars((string)$imprint['imprint_website_url'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 10px 7px 0; font-weight: bold; font-size: 13px; color: #334155;">Offizielle Impressum-URL:</td>
                            <td style="padding: 7px 0;">
                                <input type="url" name="imprint_external_url" value="' . htmlspecialchars((string)$imprint['imprint_external_url'], ENT_QUOTES, 'UTF-8') . '" style="width: 100%; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 4px; box-sizing: border-box;">
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 16px; text-align: right;">
                        <button type="submit" style="background-color: #0284C7; color: #FFFFFF; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer;">
                            Impressums- &amp; Supportdaten speichern
                        </button>
                    </div>
                </form>
            </div>

            <!-- Rechte Spalte: SMTP-Status, Sandbox-Info & Testversand -->
            <div style="flex: 1 1 420px; display: flex; flex-direction: column; gap: 20px;">
                <div style="background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px; padding: 20px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #0F172A;">
                        2. SMTP-Konfiguration &amp; Testversand
                    </h3>
                    <table style="width: 100%; font-size: 13px; margin-bottom: 16px;">
                        <tr>
                            <td style="padding: 4px 0; color: #64748B;">SMTP-Server:</td>
                            <td style="padding: 4px 0; font-weight: bold;">' . htmlspecialchars($smtpHost, ENT_QUOTES, 'UTF-8') . ':' . $smtpPort . ' (STARTTLS)</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #64748B;">Absender-Postfach:</td>
                            <td style="padding: 4px 0; font-weight: bold;">' . htmlspecialchars($smtpUser, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #64748B;">Reply-To Header:</td>
                            <td style="padding: 4px 0; color: #166534; font-weight: bold;">Deaktiviert (Reine No-Reply Systemmail)</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #64748B;">Betriebsmodus:</td>
                            <td style="padding: 4px 0;">
                                ' . ($isSandbox
                                    ? '<span style="background: #FEF3C7; color: #92400E; padding: 2px 8px; border-radius: 4px; font-weight: bold;">Sandbox aktiv &rarr; ' . htmlspecialchars($sandboxRecipient, ENT_QUOTES, 'UTF-8') . '</span>'
                                    : '<span style="background: #DCFCE7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: bold;">Live-Versand aktiv</span>') . '
                            </td>
                        </tr>
                    </table>

                    <form method="post" action="">
                        <input type="hidden" name="action" value="send_test_email">
                        <button type="submit" style="width: 100%; background-color: #16A34A; color: #FFFFFF; border: none; padding: 11px 16px; border-radius: 6px; font-weight: bold; cursor: pointer;">
                            Test-E-Mail (&bdquo;Video konvertiert&ldquo;) an ' . htmlspecialchars($sandboxRecipient, ENT_QUOTES, 'UTF-8') . ' senden
                        </button>
                    </form>
                </div>

                <!-- Live-Vorschau des E-Mail-Footers -->
                <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px; padding: 18px; text-align: center; font-size: 12px; line-height: 18px; color: #64748B;">
                    <div style="font-weight: bold; color: #0F172A; margin-bottom: 8px;">Vorschau: Aktueller E-Mail-Footer</div>
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px; margin-bottom: 12px;">
                        Dies ist eine automatisch generierte System-Nachricht &ndash; bitte antworte nicht direkt auf diese E-Mail.<br>
                        Bei Fragen erreichst du unseren Support jederzeit unter <strong>' . htmlspecialchars((string)$imprint['imprint_support_email'], ENT_QUOTES, 'UTF-8') . '</strong>.
                    </div>
                    <strong>EroCloud</strong> &ndash; ein Projekt von Pay4Coins (<strong>' . htmlspecialchars((string)$imprint['imprint_company_name'], ENT_QUOTES, 'UTF-8') . '</strong>)<br>
                    ' . htmlspecialchars((string)$imprint['imprint_address_street'], ENT_QUOTES, 'UTF-8') . ' &bull; ' . htmlspecialchars((string)$imprint['imprint_address_city'], ENT_QUOTES, 'UTF-8') . '<br>
                    ' . htmlspecialchars((string)$imprint['imprint_register_court'], ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars((string)$imprint['imprint_register_details'], ENT_QUOTES, 'UTF-8') . ') &bull; ' . htmlspecialchars((string)$imprint['imprint_vat_id'], ENT_QUOTES, 'UTF-8') . ' &bull; Geschäftsführung: ' . htmlspecialchars((string)$imprint['imprint_ceo'], ENT_QUOTES, 'UTF-8') . '<br>
                    <a href="https://erocloud.net" target="_blank" style="color: #0284C7;">erocloud.net</a> &bull;
                    <a href="' . htmlspecialchars((string)$imprint['imprint_external_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color: #0284C7;">Impressum</a> &bull;
                    Support: <a href="mailto:' . htmlspecialchars((string)$imprint['imprint_support_email'], ENT_QUOTES, 'UTF-8') . '" style="color: #0284C7;">' . htmlspecialchars((string)$imprint['imprint_support_email'], ENT_QUOTES, 'UTF-8') . '</a>
                </div>
            </div>

        </div>
    </div>
</div>';
