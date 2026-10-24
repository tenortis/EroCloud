<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * E-Mail-Template: Benachrichtigung an den Creator / Partner über das Ergebnis der
 * Inhalts-Prüfung (Film, Foto-Album oder Darsteller-Profil: Freigegeben vs. Abgelehnt/Korrektur).
 *
 * ANTI-SPAM (Option A): 100 % frei von expliziten Adult-Filmtiteln im Betreff und Textkörper.
 * Identifikation erfolgt eindeutig und seriös über Typ, ID, Prüfzeitpunkt, Status & ggf. Korrektur-Hinweis.
 */

$creatorName    = htmlspecialchars(trim((string)($vars['creator_name'] ?? 'Creator')), ENT_QUOTES, 'UTF-8');
$contentType    = strtolower(trim((string)($vars['content_type'] ?? 'movie')));
$contentId      = isset($vars['content_id']) ? abs((int)$vars['content_id']) : 0;
$reviewStatus   = strtolower(trim((string)($vars['review_status'] ?? 'approved')));
$isApproved     = ($reviewStatus === 'approved');
$reviewDatetime = htmlspecialchars(trim((string)($vars['review_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r'))), ENT_QUOTES, 'UTF-8');
$reasonTitle    = htmlspecialchars(trim((string)($vars['reason_title'] ?? '')), ENT_QUOTES, 'UTF-8');
$reasonText     = nl2br(htmlspecialchars(trim((string)($vars['reason_text'] ?? '')), ENT_QUOTES, 'UTF-8'));
$extraMetaLabel = htmlspecialchars(trim((string)($vars['extra_meta_label'] ?? '')), ENT_QUOTES, 'UTF-8');
$extraMetaValue = htmlspecialchars(trim((string)($vars['extra_meta_value'] ?? '')), ENT_QUOTES, 'UTF-8');
$actionUrl      = htmlspecialchars(trim((string)($vars['action_url'] ?? 'https://erocloud.net/mcp_neu/web/#/home')), ENT_QUOTES, 'UTF-8');

if ($contentType === 'photo_album') {
    $typeLabel     = 'Foto-Album';
    $typeLabelDat  = 'deinem Foto-Album';
    $typeLabelPoss = 'Dein Foto-Album';
    $ctaText       = $isApproved ? 'Foto-Album im Partner-Bereich öffnen &rarr;' : 'Foto-Album jetzt bearbeiten &amp; korrigieren &rarr;';
} elseif ($contentType === 'actor') {
    $typeLabel     = 'Darsteller-Profil';
    $typeLabelDat  = 'deinem Darsteller-Profil';
    $typeLabelPoss = 'Dein Darsteller-Profil';
    $ctaText       = $isApproved ? 'Darsteller-Profil im Partner-Bereich öffnen &rarr;' : 'Darsteller-Profil prüfen &amp; anpassen &rarr;';
} else {
    $typeLabel     = 'Film';
    $typeLabelDat  = 'deinem Film';
    $typeLabelPoss = 'Dein Film';
    $ctaText       = $isApproved ? 'Film-Details im Partner-Bereich öffnen &rarr;' : 'Film jetzt bearbeiten &amp; erneut einreichen &rarr;';
}

if ($isApproved) {
    $emailSubject   = $typeLabelPoss . ' (#' . $contentId . ') wurde geprüft und freigegeben';
    $emailPreheader = 'Gute Nachrichten: ' . $typeLabelPoss . ' #' . $contentId . ' wurde von unserem Team geprüft und ist ab sofort aktiv.';
    $badgeBg        = '#DCFCE7';
    $badgeColor     = '#15803D';
    $badgeText      = '&#10003; Prüfung erfolgreich &bull; Freigegeben &amp; Aktiv';
    $headline       = 'Hallo ' . $creatorName . ', ' . strtolower($typeLabelPoss) . ' ist freigegeben!';
    $introHtml      = 'unser Prüf-Team hat <strong>' . strtolower($typeLabelPoss) . ' (#' . $contentId . ')</strong> erfolgreich geprüft und veröffentlicht. Dein Inhalt ist damit ab sofort gemäß deinen Einstellungen online freigeschaltet.';
    $statusTextRow  = '<span style="color: #15803D; font-weight: 700;">&#10003; Geprüft &amp; Freigegeben</span>';
} else {
    $emailSubject   = 'Rückfrage / Korrektur erforderlich zu ' . $typeLabelDat . ' (#' . $contentId . ')';
    $emailPreheader = 'Bitte prüfe kurz unseren Hinweis zu ' . $typeLabelDat . ' #' . $contentId . ', damit wir deinen Inhalt freigeben können.';
    $badgeBg        = '#FEF2F2';
    $badgeColor     = '#B91C1C';
    $badgeText      = '&#9888; Korrektur erforderlich &bull; Bitte kurz prüfen';
    $headline       = 'Hallo ' . $creatorName . ', bitte prüfe kurz ' . strtolower($typeLabelPoss) . ' (#' . $contentId . ')';
    $introHtml      = 'bei der Prüfung von <strong>' . $typeLabelDat . ' (#' . $contentId . ')</strong> ist unserem Team noch ein Punkt aufgefallen, bevor wir den Inhalt endgültig freischalten können.';
    $statusTextRow  = '<span style="color: #B91C1C; font-weight: 700;">&#9888; Rückfrage / Korrektur erforderlich</span>';
}

$extraMetaRowHtml = '';
if ($extraMetaLabel !== '' && $extraMetaValue !== '') {
    $extraMetaRowHtml = '
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">' . $extraMetaLabel . ':</td>
                    <td style="padding: 4px 0; font-size: 14px; color: #334155;">' . $extraMetaValue . '</td>
                </tr>';
}

$feedbackBoxHtml = '';
if (!$isApproved && ($reasonTitle !== '' || $reasonText !== '')) {
    $feedbackBoxHtml = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #FFFBEB; border: 1px solid #FDE68A; border-left: 4px solid #F59E0B; border-radius: 8px; margin: 0 0 22px 0;">
    <tr>
        <td style="padding: 16px 18px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #B45309; margin-bottom: 6px;">
                Hinweis unseres Prüf-Teams' . ($reasonTitle !== '' ? ' &bull; ' . $reasonTitle : '') . '
            </div>
            <div style="font-size: 14px; line-height: 22px; color: #78350F;">
                ' . ($reasonText !== '' ? $reasonText : $reasonTitle) . '
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
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B; width: 150px;">Bereich &amp; ID:</td>
                    <td style="padding: 4px 0; font-size: 14px; font-weight: 700; color: #0F172A;">' . $typeLabel . ' #' . $contentId . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Geprüft am:</td>
                    <td style="padding: 4px 0; font-size: 14px; color: #334155;">' . $reviewDatetime . '</td>
                </tr>' . $extraMetaRowHtml . '
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Prüfungs-Status:</td>
                    <td style="padding: 4px 0; font-size: 14px;">' . $statusTextRow . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

' . $feedbackBoxHtml . '

<p style="margin: 0 0 22px 0; font-size: 15px; line-height: 24px; color: #334155;">
    ' . ($isApproved
        ? 'Über den folgenden Button gelangst du per 1-Klick-Direktlogin ohne Passworteingabe direkt zu ' . $typeLabelDat . ' in deinem Partner-Bereich:'
        : 'Du kannst die gewünschte Anpassung direkt in deinem Partner-Bereich vornehmen und den Inhalt danach sofort wieder zur Prüfung freigeben:') . '
</p>

<!-- Primärer CTA Button (mit automatischem Direkt-Login) -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0;">
    <tr>
        <td align="center">
            <a href="' . $actionUrl . '" target="_blank" style="display: inline-block; padding: 14px 28px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #FFFFFF; background-color: #0284C7; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);">
                ' . $ctaText . '
            </a>
            <div style="margin-top: 8px; font-size: 12px; color: #64748B;">
                &#128274; Persönlicher Direkt-Link: Du wirst automatisch eingeloggt und landest direkt beim Eintrag.
            </div>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 13px; line-height: 20px; color: #64748B;">
    Falls sich der Button in deinem E-Mail-Programm nicht anklicken lässt, kopiere einfach diesen Link in deinen Browser:<br>
    <a href="' . $actionUrl . '" style="color: #0284C7; word-break: break-all;">' . $actionUrl . '</a>
</p>
';

$rawReasonTitle = trim((string)($vars['reason_title'] ?? ''));
$rawReasonText  = trim((string)($vars['reason_text'] ?? ''));

$emailTextContent = "Hallo " . trim((string)($vars['creator_name'] ?? 'Creator')) . ",\n\n"
    . ($isApproved
        ? "gute Nachrichten: " . $typeLabelPoss . " (#" . $contentId . ") wurde von unserem Team erfolgreich geprüft und freigegeben!\n\n"
        : "bei der Prüfung von " . $typeLabelDat . " (#" . $contentId . ") ist unserem Team noch ein Punkt aufgefallen, bevor wir den Inhalt freigeben können.\n\n")
    . "Details zur Prüfung:\n"
    . "- Bereich & ID: " . $typeLabel . " #" . $contentId . "\n"
    . "- Geprüft am: " . trim((string)($vars['review_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r'))) . "\n"
    . "- Status: " . ($isApproved ? "Geprüft & Freigegeben" : "Korrektur erforderlich") . "\n"
    . ((!$isApproved && ($rawReasonTitle !== '' || $rawReasonText !== ''))
        ? "\nHinweis unseres Prüf-Teams" . ($rawReasonTitle !== '' ? " (" . $rawReasonTitle . ")" : "") . ":\n" . ($rawReasonText !== '' ? $rawReasonText : $rawReasonTitle) . "\n"
        : "")
    . "\nDirektlink zum Eintrag (automatischer 1-Klick-Login):\n"
    . trim((string)($vars['action_url'] ?? 'https://erocloud.net/mcp_neu/web/#/home')) . "\n\n"
    . "Viele Grüße\n"
    . "Dein EroCloud Team";
