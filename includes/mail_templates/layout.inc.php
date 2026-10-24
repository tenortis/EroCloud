<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * Globales HTML- & Plaintext-Rahmenlayout für alle EroCloud System-E-Mails.
 * Nutzt die zentral in der Datenbank (`settings`) gespeicherten Pay4Coins-Impressumsdaten.
 */

$tplSubject     = htmlspecialchars((string)($layoutVars['subject'] ?? 'Deine Benachrichtigung von EroCloud'), ENT_QUOTES, 'UTF-8');
$tplPreheader   = htmlspecialchars((string)($layoutVars['preheader'] ?? ''), ENT_QUOTES, 'UTF-8');
$tplContentHtml = (string)($layoutVars['content_html'] ?? '');
$tplContentText = (string)($layoutVars['content_text'] ?? '');
$tplUnsubUrl    = trim((string)($layoutVars['unsubscribe_url'] ?? ''));
$tplIntendedTo  = trim((string)($layoutVars['intended_email'] ?? ''));
$tplCreatorName = trim((string)($layoutVars['creator_name'] ?? ''));
$tplMerchantId  = isset($layoutVars['merchant_id']) ? abs((int)$layoutVars['merchant_id']) : 0;

$imprint = (isset($layoutVars['imprint']) && is_array($layoutVars['imprint']))
    ? $layoutVars['imprint']
    : (class_exists('EroCloudMailer') ? EroCloudMailer::getImprintData() : []);

$impCompany     = htmlspecialchars((string)($imprint['imprint_company_name'] ?? 'CIPA Media S.L.U.'), ENT_QUOTES, 'UTF-8');
$impStreet      = htmlspecialchars((string)($imprint['imprint_address_street'] ?? 'Av. Emilio Luque Moreno 19 Oficina 6'), ENT_QUOTES, 'UTF-8');
$impCity        = htmlspecialchars((string)($imprint['imprint_address_city'] ?? 'ES-38300 La Orotava (S/C de Tenerife)'), ENT_QUOTES, 'UTF-8');
$impCourt       = htmlspecialchars((string)($imprint['imprint_register_court'] ?? 'Registro Mercantil de Santa Cruz de Tenerife'), ENT_QUOTES, 'UTF-8');
$impRegDetails  = htmlspecialchars((string)($imprint['imprint_register_details'] ?? 'Tomo: 3368, Folio: 43, Sección: 8, Hoja: TF-54807, Inscripción: 8'), ENT_QUOTES, 'UTF-8');
$impVatId       = htmlspecialchars((string)($imprint['imprint_vat_id'] ?? 'CIF: B76659168'), ENT_QUOTES, 'UTF-8');
$impCeo         = htmlspecialchars((string)($imprint['imprint_ceo'] ?? 'Christina Toepel'), ENT_QUOTES, 'UTF-8');
$impSupportMail = htmlspecialchars((string)($imprint['imprint_support_email'] ?? 'support@pay4coins.com'), ENT_QUOTES, 'UTF-8');
$impExternalUrl = htmlspecialchars((string)($imprint['imprint_external_url'] ?? 'https://pay4coins.com/de/impressum'), ENT_QUOTES, 'UTF-8');

$smtpCfg          = class_exists('EroCloudMailer') ? EroCloudMailer::getSmtpConfig() : [];
$isSandboxMode    = isset($smtpCfg['sandbox_mode']) ? (bool)$smtpCfg['sandbox_mode'] : (!defined('MAIL_SANDBOX_MODE') || MAIL_SANDBOX_MODE === true);
$sandboxRecipient = !empty($layoutVars['actual_recipient'])
    ? (string)$layoutVars['actual_recipient']
    : (!empty($smtpCfg['sandbox_recipient']) ? (string)$smtpCfg['sandbox_recipient'] : (defined('MAIL_SANDBOX_RECIPIENT') ? (string)MAIL_SANDBOX_RECIPIENT : 'erocms@gmail.com'));

$sandboxBannerHtml = '';
if ($isSandboxMode && ($tplIntendedTo !== '' || $tplMerchantId > 0)) {
    $safeIntended     = htmlspecialchars($tplIntendedTo !== '' ? $tplIntendedTo : 'unbekannt', ENT_QUOTES, 'UTF-8');
    $safeCreator      = htmlspecialchars($tplCreatorName !== '' ? $tplCreatorName : 'Creator', ENT_QUOTES, 'UTF-8');
    $safeSandboxRecip = htmlspecialchars($sandboxRecipient, ENT_QUOTES, 'UTF-8');
    $localTestUrl     = '';
    $directActionUrl  = !empty($layoutVars['action_url'])
        ? (string)$layoutVars['action_url']
        : (!empty($layoutVars['movie_edit_url']) ? (string)$layoutVars['movie_edit_url'] : '');
    if ($directActionUrl !== '') {
        $localCandidate = str_replace('https://erocloud.net', 'https://erocloud.local', $directActionUrl);
        $localTestUrl  .= '<br><span style="font-size: 11px;">Lokaler XAMPP Direkt-Login-Test: <a href="' . htmlspecialchars($localCandidate, ENT_QUOTES, 'UTF-8') . '" style="color: #B45309; font-weight: 600;">Hier klicken (erocloud.local)</a></span>';
    }
    if ($tplUnsubUrl !== '') {
        $localUnsubCandidate = str_replace('https://erocloud.net', 'https://erocloud.local', $tplUnsubUrl);
        $localTestUrl       .= ' &bull; <span style="font-size: 11px;">Lokaler 1-Klick-Abmelde-Test: <a href="' . htmlspecialchars($localUnsubCandidate, ENT_QUOTES, 'UTF-8') . '" style="color: #B45309; font-weight: 600;">Abmelde-Seite testen (erocloud.local)</a></span>';
    }
    $sandboxBannerHtml = '
    <tr>
        <td style="padding: 0 0 16px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px;">
                <tr>
                    <td style="padding: 10px 14px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 18px; color: #92400E;">
                        <strong>Test-/Sandbox-Modus aktiv:</strong> Diese E-Mail wurde zu Testzwecken an <strong>' . $safeSandboxRecip . '</strong> zugestellt.<br>
                        Regulärer Empfänger im Live-Betrieb wäre: <strong>' . $safeCreator . '</strong> (' . $safeIntended . ($tplMerchantId > 0 ? ', Partner-ID #' . $tplMerchantId : '') . ')' . $localTestUrl . '
                    </td>
                </tr>
            </table>
        </td>
    </tr>';
}

$unsubscribeBlockHtml = '';
if ($tplUnsubUrl !== '') {
    $safeUnsubUrl = htmlspecialchars($tplUnsubUrl, ENT_QUOTES, 'UTF-8');
    $unsubscribeBlockHtml = '
    <tr>
        <td style="padding: 18px 28px; background-color: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: center;">
            <p style="margin: 0 0 10px 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 18px; color: #64748B;">
                Du möchtest diese automatischen E-Mail-Benachrichtigungen nicht mehr erhalten oder deine Einstellungen anpassen?
            </p>
            <a href="' . $safeUnsubUrl . '" target="_blank" style="display: inline-block; padding: 7px 16px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; font-weight: 600; color: #475569; background-color: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; text-decoration: none;">
                Benachrichtigungen verwalten / abbestellen
            </a>
        </td>
    </tr>';
}

$finalHtml = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . $tplSubject . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F1F5F9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1E293B;">
    ' . ($tplPreheader !== '' ? '<div style="display: none; max-height: 0px; overflow: hidden; mso-hide: all;">' . $tplPreheader . '</div>' : '') . '
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F1F5F9; width: 100%; margin: 0; padding: 32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; margin: 0 auto;">
                    ' . $sandboxBannerHtml . '
                    <!-- Haupt-Card -->
                    <tr>
                        <td style="background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <!-- Header-Balken in Pay4Coins-Blau -->
                                <tr>
                                    <td style="background: linear-gradient(135deg, #0284C7 0%, #4297D7 100%); background-color: #0284C7; padding: 22px 28px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 20px; font-weight: 700; color: #FFFFFF; letter-spacing: -0.3px;">
                                                    EroCloud
                                                </td>
                                                <td align="right" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; font-weight: 600; color: #E0F2FE;">
                                                    Creator Cloud &amp; Media Studio
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- E-Mail-Hauptinhalt -->
                                <tr>
                                    <td style="padding: 30px 28px 26px 28px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 24px; color: #334155;">
                                        ' . $tplContentHtml . '
                                    </td>
                                </tr>
                                <!-- Hinweis: Automatische E-Mail (No-Reply) -->
                                <tr>
                                    <td style="padding: 14px 28px; background-color: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: center;">
                                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 18px; color: #64748B;">
                                            Dies ist eine automatisch generierte System-Nachricht &ndash; bitte antworte nicht direkt auf diese E-Mail.<br>
                                            Bei Fragen erreichst du unseren Support jederzeit unter <a href="mailto:' . $impSupportMail . '" style="color: #0284C7; text-decoration: none; font-weight: 600;">' . $impSupportMail . '</a>.
                                        </p>
                                    </td>
                                </tr>
                                ' . $unsubscribeBlockHtml . '
                            </table>
                        </td>
                    </tr>
                    <!-- Rechtlicher Footer (Zentrales Pay4Coins Impressum aus DB) -->
                    <tr>
                        <td style="padding: 20px 16px 8px 16px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 18px; color: #64748B;">
                            <p style="margin: 0 0 4px 0;">
                                <strong>EroCloud</strong> &ndash; ein Projekt von Pay4Coins (<strong>' . $impCompany . '</strong>)
                            </p>
                            <p style="margin: 0 0 4px 0;">
                                ' . $impStreet . ' &bull; ' . $impCity . '
                            </p>
                            <p style="margin: 0 0 6px 0;">
                                ' . $impCourt . ' (' . $impRegDetails . ') &bull; ' . $impVatId . ' &bull; Geschäftsführung: ' . $impCeo . '
                            </p>
                            <p style="margin: 0;">
                                <a href="https://erocloud.net" target="_blank" style="color: #0284C7; text-decoration: none;">erocloud.net</a> &bull;
                                <a href="' . $impExternalUrl . '" target="_blank" style="color: #0284C7; text-decoration: none;">Impressum</a> &bull;
                                Support: <a href="mailto:' . $impSupportMail . '" style="color: #0284C7; text-decoration: none;">' . $impSupportMail . '</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

$rawCompany    = (string)($imprint['imprint_company_name'] ?? 'CIPA Media S.L.U.');
$rawStreet     = (string)($imprint['imprint_address_street'] ?? 'Av. Emilio Luque Moreno 19 Oficina 6');
$rawCity       = (string)($imprint['imprint_address_city'] ?? 'ES-38300 La Orotava (S/C de Tenerife)');
$rawCourt      = (string)($imprint['imprint_register_court'] ?? 'Registro Mercantil de Santa Cruz de Tenerife');
$rawRegDetails = (string)($imprint['imprint_register_details'] ?? 'Tomo: 3368, Folio: 43, Sección: 8, Hoja: TF-54807, Inscripción: 8');
$rawVatId      = (string)($imprint['imprint_vat_id'] ?? 'CIF: B76659168');
$rawCeo        = (string)($imprint['imprint_ceo'] ?? 'Christina Toepel');
$rawSupport    = (string)($imprint['imprint_support_email'] ?? 'support@pay4coins.com');
$rawImprintUrl = (string)($imprint['imprint_external_url'] ?? 'https://pay4coins.com/de/impressum');

$finalText = trim($tplContentText);
if ($finalText !== '') {
    $finalText .= "\n\n------------------------------------------------------------\n"
        . "Hinweis: Dies ist eine automatisch generierte System-Nachricht - bitte antworte nicht direkt auf diese E-Mail.\n"
        . "Bei Fragen erreichst du unseren Support unter: " . $rawSupport;

    if ($tplUnsubUrl !== '') {
        $finalText .= "\n\nDu möchtest diese E-Mail-Benachrichtigungen nicht mehr erhalten?\n"
            . "Hier mit einem Klick deaktivieren: " . $tplUnsubUrl;
    }

    $finalText .= "\n\n------------------------------------------------------------\n"
        . "EroCloud - ein Projekt von Pay4Coins (" . $rawCompany . ")\n"
        . $rawStreet . " | " . $rawCity . "\n"
        . $rawCourt . " (" . $rawRegDetails . ")\n"
        . $rawVatId . " | Geschäftsführung: " . $rawCeo . "\n"
        . "Impressum: " . $rawImprintUrl . " | Support: " . $rawSupport;
}
