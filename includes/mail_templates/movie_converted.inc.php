<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * E-Mail-Template: Benachrichtigung an den Creator, sobald sein hochgeladenes Video
 * fertig konvertiert ist und die 10 Vorschaubilder im Cover-Studio bereitstehen.
 *
 * ANTI-SPAM (Option A): 100 % frei von Filmtiteln oder Adult-Begriffen im Betreff und Textkörper.
 * Identifikation erfolgt eindeutig und seriös über Film-ID, Upload-Zeitpunkt, Laufzeit & Auflösung.
 */

$creatorName    = htmlspecialchars(trim((string)($vars['creator_name'] ?? 'Creator')), ENT_QUOTES, 'UTF-8');
$movieId        = isset($vars['movie_id']) ? abs((int)$vars['movie_id']) : 0;
$uploadDatetime = htmlspecialchars(trim((string)($vars['upload_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r'))), ENT_QUOTES, 'UTF-8');
$rawPlaytime    = trim((string)($vars['playtime'] ?? '-'));
$rawPlaytime    = preg_replace('/\s*Min\.?$/i', '', $rawPlaytime);
$playtime       = htmlspecialchars($rawPlaytime !== '' ? $rawPlaytime : '-', ENT_QUOTES, 'UTF-8');
$resolution     = htmlspecialchars(trim((string)($vars['resolution'] ?? 'HD')), ENT_QUOTES, 'UTF-8');
$movieEditUrl   = htmlspecialchars(trim((string)($vars['movie_edit_url'] ?? 'https://erocloud.net/mcp_neu/web/#/movies')), ENT_QUOTES, 'UTF-8');

$emailSubject   = 'Dein Video (#' . $movieId . ') ist fertig konvertiert – Vorschaubilder stehen bereit';
$emailPreheader = 'Deine 10 Vorschaubilder für Video #' . $movieId . ' stehen ab sofort im EroCloud Cover-Studio für dich bereit.';

$emailHtmlContent = '
<div style="margin-bottom: 18px;">
    <span style="display: inline-block; padding: 4px 12px; font-size: 12px; font-weight: 700; color: #15803D; background-color: #DCFCE7; border-radius: 999px; letter-spacing: 0.2px;">
        &#10003; Konvertierung abgeschlossen &bull; 10 Vorschaubilder bereit
    </span>
</div>

<h1 style="margin: 0 0 14px 0; font-size: 21px; line-height: 28px; font-weight: 700; color: #0F172A;">
    Hallo ' . $creatorName . ', dein Video ist bereit!
</h1>

<p style="margin: 0 0 18px 0; font-size: 15px; line-height: 24px; color: #334155;">
    gute Nachrichten: Dein hochgeladenes Video wurde erfolgreich für das HD-Streaming konvertiert. Gleichzeitig hat unser System automatisch <strong>10 Szenen-Vorschaubilder</strong> aus deinem Film erstellt, die dir ab sofort zur Verfügung stehen.
</p>

<!-- Video-Details Box (Neutral & Spam-sicher ohne explizite Titel) -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; margin: 0 0 22px 0;">
    <tr>
        <td style="padding: 18px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B; width: 150px;">Film-ID:</td>
                    <td style="padding: 4px 0; font-size: 14px; font-weight: 700; color: #0F172A;">#' . $movieId . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Hochgeladen am:</td>
                    <td style="padding: 4px 0; font-size: 14px; color: #334155;">' . $uploadDatetime . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Laufzeit &amp; Qualität:</td>
                    <td style="padding: 4px 0; font-size: 14px; color: #334155;">' . $playtime . ' Min. &bull; ' . $resolution . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-size: 13px; color: #64748B;">Vorschaubilder:</td>
                    <td style="padding: 4px 0; font-size: 14px; font-weight: 600; color: #0284C7;">10 Szenen-Thumbnails generiert</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 22px 0; font-size: 15px; line-height: 24px; color: #334155;">
    Du kannst jetzt direkt in den Film-Details deine Wunsch-Szenen für <strong>FSK 16 &amp; FSK 18</strong> auswählen, sie im <strong>Cover-Studio</strong> (z.&nbsp;B. mit Milchglas-Zensur, Verpixelung, Text oder Stickern) gestalten und deinen Film danach zur Prüfung freigeben:
</p>

<!-- Primärer CTA Button (mit automatischem Direkt-Login) -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0;">
    <tr>
        <td align="center">
            <a href="' . $movieEditUrl . '" target="_blank" style="display: inline-block; padding: 14px 28px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #FFFFFF; background-color: #0284C7; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);">
                Vorschaubilder auswählen &amp; Film bearbeiten &rarr;
            </a>
            <div style="margin-top: 8px; font-size: 12px; color: #64748B;">
                &#128274; Persönlicher Direkt-Link: Du wirst automatisch eingeloggt und landest direkt bei deinem Film.
            </div>
        </td>
    </tr>
</table>

<p style="margin: 0; font-size: 13px; line-height: 20px; color: #64748B;">
    Falls sich der Button in deinem E-Mail-Programm nicht anklicken lässt, kopiere einfach diesen Link in deinen Browser:<br>
    <a href="' . $movieEditUrl . '" style="color: #0284C7; word-break: break-all;">' . $movieEditUrl . '</a>
</p>
';

$rawUploadDt = trim((string)($vars['upload_datetime'] ?? date('d.m.Y \u\m H:i \U\h\r')));

$emailTextContent = "Hallo " . trim((string)($vars['creator_name'] ?? 'Creator')) . ",\n\n"
    . "gute Nachrichten: Dein hochgeladenes Video wurde erfolgreich für das HD-Streaming konvertiert!\n"
    . "Gleichzeitig hat unser System automatisch 10 Szenen-Vorschaubilder aus deinem Film erstellt, die dir ab sofort zur Verfügung stehen.\n\n"
    . "Details zu deinem Video:\n"
    . "- Film-ID: #" . $movieId . "\n"
    . "- Hochgeladen am: " . $rawUploadDt . "\n"
    . "- Laufzeit & Qualität: " . trim((string)($vars['playtime'] ?? '-')) . " Min. / " . trim((string)($vars['resolution'] ?? 'HD')) . "\n"
    . "- Vorschaubilder: 10 Szenen-Thumbnails bereit\n\n"
    . "Du kannst jetzt in den Film-Details deine Vorschaubilder für FSK 16 & FSK 18 auswählen, im Cover-Studio gestalten und deinen Film anschließend zur Prüfung freigeben:\n"
    . trim((string)($vars['movie_edit_url'] ?? 'https://erocloud.net/mcp_neu/web/#/movies')) . "\n\n"
    . "Viele Grüße\n"
    . "Dein EroCloud Team";
