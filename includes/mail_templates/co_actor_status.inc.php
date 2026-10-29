<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * E-Mail-Template: Benachrichtigung an den Creator / Partner zum Status eines
 * Drehpartners bzw. 18+ ID-Checks (Smartphone-QR eingereicht, im ACP bestätigt oder abgelehnt).
 *
 * ANTI-SPAM (Option A): Seriöse Compliance-Benachrichtigung ohne sensible Ausweisnummern
 * oder Klarnamen im unverschlüsselten E-Mail-Text (Identifikation über Referenz-Code & Künstlername/Rolle).
 */

$creatorName   = htmlspecialchars(trim((string)($vars['creator_name'] ?? 'Creator')), ENT_QUOTES, 'UTF-8');
$coActorId     = isset($vars['co_actor_id']) ? abs((int)$vars['co_actor_id']) : 0;
$refCode       = htmlspecialchars(trim((string)($vars['ref_code'] ?? ('DP-' . $coActorId))), ENT_QUOTES, 'UTF-8');
$roleLabel     = htmlspecialchars(trim((string)($vars['role_label'] ?? 'Drehpartner')), ENT_QUOTES, 'UTF-8');
$aliasLabel    = htmlspecialchars(trim((string)($vars['alias_label'] ?? ('Eintrag #' . $coActorId))), ENT_QUOTES, 'UTF-8');
$statusType    = strtolower(trim((string)($vars['status_type'] ?? 'verified'))); // submitted | verified | rejected
$eventDatetime = htmlspecialchars(trim((string)($vars['event_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r'))), ENT_QUOTES, 'UTF-8');
$reasonText    = nl2br(htmlspecialchars(trim((string)($vars['reason_text'] ?? '')), ENT_QUOTES, 'UTF-8'));
$actionUrl     = htmlspecialchars(trim((string)($vars['action_url'] ?? 'https://erocloud.net/Startseite#/co-actors')), ENT_QUOTES, 'UTF-8');

if ($statusType === 'submitted') {
    $emailSubject   = 'Smartphone-Verifizierung (' . $refCode . ') erfolgreich eingereicht';
    $emailPreheader = 'Die 18+ Kamera-Aufnahmen und die digitale Unterschrift für ' . $refCode . ' wurden erfolgreich übermittelt.';
    $badgeBg        = '#E0F2FE';
    $badgeColor     = '#0369A1';
    $badgeText      = '&#128241; Smartphone QR-Check abgeschlossen &bull; Bereit zur Nutzung';
    $headline       = 'Hallo ' . $creatorName . ', der QR-Check (' . $refCode . ') ist eingegangen!';
    $introHtml      = 'die Smartphone-Verifizierung inklusive der 3 Live-Kamera-Aufnahmen und der digitalen Unterschrift auf dem Display wurde soeben vollständig übermittelt. Du kannst die Person ab sofort bei deinen Filmen und Foto-Alben auswählen.';
    $statusRowHtml  = '<span style="color: #0369A1; font-weight: 700;">&#10003; Vollständig eingereicht (In Prüfung)</span>';
    $ctaText        = 'Drehpartner &amp; Freigabe-Zertifikat ansehen &rarr;';
} elseif ($statusType === 'rejected') {
    $emailSubject   = 'Rückfrage zum 18+ ID-Check (' . $refCode . ') – Bitte kurz erneuern';
    $emailPreheader = 'Bei der Prüfung des 18+ ID-Checks (' . $refCode . ') ist eine Neuaufnahme per QR-Code erforderlich.';
    $badgeBg        = '#FEF2F2';
    $badgeColor     = '#B91C1C';
    $badgeText      = '&#9888; ID-Check abgelehnt &bull; Neue Aufnahme erforderlich';
    $headline       = 'Hallo ' . $creatorName . ', bitte erneuere kurz den ID-Check (' . $refCode . ')';
    $introHtml      = 'unser Compliance-Team konnte den eingereichten 18+ ID-Check leider noch nicht freigeben. Bitte erstelle in deiner Drehpartner-Verwaltung mit einem Klick einen neuen QR-Code für eine scharfe Neuaufnahme.';
    $statusRowHtml  = '<span style="color: #B91C1C; font-weight: 700;">&#9888; Abgelehnt – Korrektur nötig</span>';
    $ctaText        = 'Neuen Smartphone-QR-Code generieren &rarr;';
} else {
    $emailSubject   = '18+ ID-Check & Model-Release (' . $refCode . ') erfolgreich bestätigt';
    $emailPreheader = 'Der 18+ ID-Check (' . $refCode . ') wurde von unserem Team geprüft und dauerhaft freigegeben.';
    $badgeBg        = '#DCFCE7';
    $badgeColor     = '#15803D';
    $badgeText      = '&#10003; 18+ ID-Check bestätigt &bull; Dauerhaft verifiziert';
    $headline       = 'Hallo ' . $creatorName . ', der 18+ ID-Check (' . $refCode . ') ist bestätigt!';
    $introHtml      = 'unser Team hat die eingereichten Ausweis-Aufnahmen, den ID-Shot und die unterzeichnete Einverständniserklärung (Model Release) geprüft und dauerhaft freigegeben.';
    $statusRowHtml  = '<span style="color: #15803D; font-weight: 700;">&#10003; Geprüft &amp; Dauerhaft Freigegeben</span>';
    $ctaText        = 'Zur Drehpartner-Übersicht im Partner-Bereich &rarr;';
}

$feedbackBoxHtml = '';
if ($statusType === 'rejected' && $reasonText !== '') {
    $feedbackBoxHtml = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #FFFBEB; border: 1px solid #FDE68A; border-left: 4px solid #F59E0B; border-radius: 8px; margin: 0 0 22px 0;">
    <tr>
        <td style="padding: 16px 18px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #B45309; margin-bottom: 6px;">
                Grund für die Rückfrage / Ablehnung
            </div>
            <div style="font-size: 14px; line-height: 22px; color: #78350F;">
                ' . $reasonText . '
            </div>
        </td>
    </tr>
</table>';
}

$emailHtmlContent = '
<div style="margin-bottom: 18px;">
    <span style="display: inline-block; padding: 4px 12px; font-size: 12px; font-weight: 700; color: ' . $badgeColor . '; background-color: ' . $badgeBg . '; border-radius: 999px; letter-spacing: 0.2px;">
        ' . $badgeText . '
    </span>
</div>

<h1 style="margin: 0 0 14px 0; font-size: 21px; line-height: 28px; font-weight: 700; color: #0F172A;">
    ' . $headline . '
</h1>

<p style="margin: 0 0 18px 0; font-size: 15px; line-height: 24px; color: #334155;">
    ' . $introHtml . '
</p>

<!-- Details-Box -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; margin: 0 0 20px 0;">
    <tr>
        <td style="padding: 18px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B; width: 150px;">Referenz-Code:</td>
                    <td style="padding: 4px 0; font-size: 14px; font-weight: 700; color: #0F172A;">' . $refCode . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Rolle / Bezeichnung:</td>
                    <td style="padding: 4px 0; font-size: 14px; color: #334155;">' . $roleLabel . ' (' . $aliasLabel . ')</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Zeitpunkt:</td>
                    <td style="padding: 4px 0; font-size: 14px; color: #334155;">' . $eventDatetime . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Aktueller Status:</td>
                    <td style="padding: 4px 0; font-size: 14px;">' . $statusRowHtml . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

' . $feedbackBoxHtml . '

<!-- Primärer CTA Button -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0;">
    <tr>
        <td align="center">
            <a href="' . $actionUrl . '" target="_blank" style="display: inline-block; padding: 14px 28px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #FFFFFF; background-color: #0284C7; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);">
                ' . $ctaText . '
            </a>
            <div style="margin-top: 8px; font-size: 12px; color: #64748B;">
                &#128274; Persönlicher Direkt-Link: Du wirst automatisch eingeloggt und landest direkt in der Drehpartner-Verwaltung.
            </div>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 13px; line-height: 20px; color: #64748B;">
    Falls sich der Button in deinem E-Mail-Programm nicht anklicken lässt, kopiere einfach diesen Link in deinen Browser:<br>
    <a href="' . $actionUrl . '" style="color: #0284C7; word-break: break-all;">' . $actionUrl . '</a>
</p>
';

$rawReason = trim((string)($vars['reason_text'] ?? ''));

$emailTextContent = "Hallo " . trim((string)($vars['creator_name'] ?? 'Creator')) . ",\n\n"
    . ($statusType === 'submitted'
        ? "die Smartphone-Verifizierung (" . trim((string)($vars['ref_code'] ?? '')) . ") inklusive Kamera-Aufnahmen und digitaler Unterschrift wurde erfolgreich übermittelt.\n\n"
        : ($statusType === 'rejected'
            ? "bei der Prüfung des 18+ ID-Checks (" . trim((string)($vars['ref_code'] ?? '')) . ") ist unserem Team ein Punkt aufgefallen. Bitte nimm den ID-Shot per Smartphone-QR-Code kurz neu auf.\n\n"
            : "unser Team hat den 18+ ID-Check und das Model-Release (" . trim((string)($vars['ref_code'] ?? '')) . ") geprüft und dauerhaft bestätigt!\n\n"))
    . "Details:\n"
    . "- Referenz-Code: " . trim((string)($vars['ref_code'] ?? '')) . "\n"
    . "- Rolle: " . trim((string)($vars['role_label'] ?? 'Drehpartner')) . " (" . trim((string)($vars['alias_label'] ?? '')) . ")\n"
    . "- Zeitpunkt: " . trim((string)($vars['event_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r'))) . "\n"
    . (($statusType === 'rejected' && $rawReason !== '') ? "\nGrund für die Rückfrage:\n" . $rawReason . "\n" : "")
    . "\nDirektlink zur Drehpartner-Verwaltung (automatischer 1-Klick-Login):\n"
    . trim((string)($vars['action_url'] ?? 'https://erocloud.net/Startseite#/co-actors')) . "\n\n"
    . "Viele Grüße\n"
    . "Dein EroCloud Team";
