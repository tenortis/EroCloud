<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<style type="text/css">
    .sa-card-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }
    .sa-card {
        flex: 1 1 220px;
        background: #fcfdfd;
        border: 1px solid #D1D1D1;
        border-radius: 4px;
        padding: 15px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .sa-card-title {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        font-weight: bold;
    }
    .sa-card-value {
        font-size: 22px;
        font-weight: bold;
        color: #1c94c4;
        margin-bottom: 5px;
    }
    .sa-card-sub {
        font-size: 12px;
        color: #555;
        line-height: 1.4;
    }
    .sa-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin-bottom: 20px;
        background: #fff;
    }
    .sa-table th {
        background: #e9e9e9;
        color: #333;
        font-weight: bold;
        text-align: left;
        padding: 10px 12px;
        border: 1px solid #ccc;
    }
    .sa-table td {
        padding: 9px 12px;
        border: 1px solid #ddd;
        vertical-align: middle;
    }
    .sa-table tr:nth-child(even) {
        background: #f9f9f9;
    }
    .sa-badge {
        display: inline-block;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: bold;
        color: #fff;
        border-radius: 3px;
    }
    .sa-progress-wrap {
        width: 100%;
        background: #e2e2e2;
        height: 18px;
        border-radius: 9px;
        overflow: hidden;
        margin-top: 6px;
    }
    .sa-progress-bar {
        height: 100%;
        background: #1c94c4;
        transition: width 0.4s ease;
    }
    .sa-spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #1c94c4;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        animation: sa-spin 1s linear infinite;
        margin: 0 auto;
    }
    @keyframes sa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div id="storage_analytics_tabs">
    <ul>
        <li><a href="#tab-sa-storage">1. Speicherplatz &amp; Dateiformate</a></li>
        <li><a href="#tab-sa-traffic">2. Streaming- &amp; Download-Traffic (6 Monate)</a></li>
        <li><a href="#tab-sa-cloud">3. Konvertierungs-Check &amp; Cloud-Kosten</a></li>
    </ul>

    <!-- LADEANZEIGE -->
    <div id="sa-loading-box" style="padding: 40px; text-align: center; border: 1px solid #D1D1D1; background: #fcfdfd; border-radius: 4px; margin: 15px;">
        <div style="font-size: 18px; font-weight: bold; margin-bottom: 12px; color: #1c94c4;">Speicher- und Traffic-Daten werden ermittelt...</div>
        <div style="color: #666; font-size: 14px; margin-bottom: 20px;">Bitte haben Sie einen Moment Geduld, w&auml;hrend das Dateisystem und die Streaming-Historie ausgewertet werden.</div>
        <div class="sa-spinner"></div>
    </div>

    <!-- SIMULATIONS-HINWEIS (FALLS LOKAL OHNE VIDEODATEIEN) -->
    <div id="sa-sim-notice" style="display: none; margin: 15px 15px 0 15px; padding: 12px 15px; background: #FFF9E6; border: 1px solid #FFE0B2; border-radius: 4px; color: #8a5a00; font-size: 13px;">
        <strong>Hinweis zur lokalen Entwicklungsumgebung:</strong> Auf diesem lokalen System wurden keine physischen Video-Ordner unter <code>cloud_storage/</code> gefunden. Alle angegebenen Video-Speichergr&ouml;&szlig;en und Traffic-Mengen wurden daher automatisch anhand der exakten Spieldauer (<code>playtime_seconds</code>) aus der Datenbank und den konfigurierten FFmpeg-Bitraten berechnet. Auf dem Live-Server werden hier automatisch die exakten physischen Dateigr&ouml;&szlig;en angezeigt.
    </div>

    <!-- KOPFZEILE MIT CACHE-INFO & AKTUALISIEREN-BUTTON -->
    <div id="sa-header-bar" style="display: none; margin: 15px 15px 5px 15px; padding: 10px 15px; background: #f4f8fb; border: 1px solid #c5dbec; border-radius: 4px; font-size: 13px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>Stand der Analyse:</strong> <span id="sa-meta-time">-</span>
            <span id="sa-meta-cache" style="color: #666; margin-left: 8px;"></span>
        </div>
        <div>
            <button type="button" id="btn-sa-refresh" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" style="padding: 5px 12px; font-size: 12px; font-weight: bold; cursor: pointer;">
                <span class="ui-button-text">&#x21bb; Jetzt live neu berechnen</span>
            </button>
        </div>
    </div>

    <!-- TAB 1: SPEICHERPLATZ & DATEIFORMATE -->
    <div id="tab-sa-storage" style="display: none;">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:15px;">Physische Speicherplatz-Analyse</div>

        <div class="sa-card-grid">
            <div class="sa-card">
                <div class="sa-card-title">Gesamter Cloud-Speicherbedarf</div>
                <div class="sa-card-value" id="sa-card-total-cloud">-</div>
                <div class="sa-card-sub">Alle Videos, Fotoalben, Banner &amp; H&auml;ndler-Dateien</div>
            </div>
            <div class="sa-card">
                <div class="sa-card-title">Davon Videos Gesamt</div>
                <div class="sa-card-value" id="sa-card-movies-total">-</div>
                <div class="sa-card-sub" id="sa-card-movies-sub">-</div>
            </div>
            <div class="sa-card" style="border-color: #f5c6cb; background: #fff8f8;">
                <div class="sa-card-title" style="color: #c00000;">Sofortiges Einsparpotenzial (.webm &amp; .ogv)</div>
                <div class="sa-card-value" style="color: #c00000;" id="sa-card-legacy-savings">-</div>
                <div class="sa-card-sub">Veraltete Formate, die vom Player nie ausgeliefert werden (<span id="sa-card-legacy-plus-m">-</span> inkl. Mobile-MP4)</div>
            </div>
            <div class="sa-card" style="border-color: #c3e6cb; background: #f8fff9;">
                <div class="sa-card-title" style="color: #008000;">Speicherbedarf nach Bereinigung</div>
                <div class="sa-card-value" style="color: #008000;" id="sa-card-cleaned-total">-</div>
                <div class="sa-card-sub">Verbleibendes Volumen nach L&ouml;schung von WebM &amp; OGV</div>
            </div>
        </div>

        <!-- Server-Festplattenauslastung -->
        <div class="ui-widget-content" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd;">
            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px;">
                <strong>Server-Partition (<code id="sa-disk-path">-</code>):</strong>
                <span>Belegt: <strong id="sa-disk-used">-</strong> von <strong id="sa-disk-total">-</strong> (Frei: <strong style="color: #008000;" id="sa-disk-free">-</strong> | <strong id="sa-disk-pct">0%</strong>)</span>
            </div>
            <div class="sa-progress-wrap">
                <div id="sa-disk-bar" class="sa-progress-bar" style="width: 0%;"></div>
            </div>
        </div>

        <h3 style="font-size: 16px; margin: 20px 0 10px 0; color: #333;">1.1 Speicherverbrauch nach Systembereichen</h3>
        <table class="sa-table" id="sa-table-categories">
            <thead>
                <tr>
                    <th>Systembereich / Verzeichnis</th>
                    <th>Datenbank-Bestand</th>
                    <th>Physische Dateien</th>
                    <th>Physisch auf Server</th>
                    <th>Kalkulierte Gr&ouml;&szlig;e (Bitrate)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <h3 style="font-size: 16px; margin: 25px 0 10px 0; color: #333;">1.2 Aufschl&uuml;sselung der Video-Dateiformate (Konvertierungs-Ergebnisse)</h3>
        <div class="ui-widget-content" style="padding: 12px 15px; margin-bottom: 12px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd; font-size: 13px; line-height: 1.5;">
            Pro hochgeladenem Film erzeugt der Cronjob <code>cronjobs/convert_movie.php</code> derzeit <strong>4 Videodateien</strong> (Desktop-MP4, Mobile-MP4, Mobile-WebM, Mobile-OGV) sowie <strong>10 Vorschaubilder</strong>. Die nachfolgende Tabelle zeigt exakt, wie viel Speicherplatz jede dieser Dateigruppen verbraucht und ob sie im System tats&auml;chlich ben&ouml;tigt wird:
        </div>
        <table class="sa-table" id="sa-table-formats">
            <thead>
                <tr>
                    <th>Dateityp / Muster</th>
                    <th>Technische Parameter (FFmpeg)</th>
                    <th>System-Nutzung</th>
                    <th>Anzahl Dateien</th>
                    <th>Speicherplatz (Live)</th>
                    <th>Speicherplatz (Kalkuliert)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <h3 style="font-size: 16px; margin: 25px 0 10px 0; color: #333;">1.3 Videos nach Freigabe-Status</h3>
        <table class="sa-table" id="sa-table-statuses">
            <thead>
                <tr>
                    <th>Status-Kategorie</th>
                    <th>Anzahl Filme</th>
                    <th>Gesamte Spieldauer</th>
                    <th>Belegter Speicherplatz</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- TAB 2: STREAMING & DOWNLOAD TRAFFIC -->
    <div id="tab-sa-traffic" style="display: none;">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:15px;">Streaming- &amp; Download-Traffic (Letzte 6 Monate)</div>

        <div class="ui-widget-content" style="padding: 12px 15px; margin-bottom: 18px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd; font-size: 13px; line-height: 1.5;">
            Diese Auswertung basiert auf allen Kunden-Zugriffen in der Tabelle <code>movies_access</code> innerhalb der <strong>letzten 6 Monate</strong> (anhand von Kaufzeitpunkt <code>buy_timestamp</code> und letztem Player-Abruf <code>access_token_datetime</code>). Da ein Kunde einen gekauften Film w&auml;hrend der Laufzeit teilweise mehrfach startet oder im Video spult, wird neben dem <strong>1x Basis-Wert</strong> auch ein <strong>realistischer 2x Praxis-Wert</strong> ausgewiesen.
        </div>

        <div class="sa-card-grid">
            <div class="sa-card">
                <div class="sa-card-title">Abrufe (Letzte 6 Monate)</div>
                <div class="sa-card-value" id="sa-tr-total-count">-</div>
                <div class="sa-card-sub">Streams: <strong id="sa-tr-stream-cnt">-</strong> | Downloads: <strong id="sa-tr-dl-cnt">-</strong><br />Aktive Kunden: <strong id="sa-tr-users">-</strong></div>
            </div>
            <div class="sa-card">
                <div class="sa-card-title">Gesamt-Traffic (6 Monate, 1x Basis)</div>
                <div class="sa-card-value" id="sa-tr-total-6m">-</div>
                <div class="sa-card-sub">Streaming: <strong id="sa-tr-stream-6m">-</strong> (<span id="sa-tr-stream-hrs">-</span>)<br />Download: <strong id="sa-tr-dl-6m">-</strong> (<span id="sa-tr-dl-hrs">-</span>)</div>
            </div>
            <div class="sa-card" style="border-color: #bee5eb; background: #f4fafc;">
                <div class="sa-card-title" style="color: #0c5460;">&Oslash; Traffic pro Monat (1x Basis)</div>
                <div class="sa-card-value" style="color: #0c5460;" id="sa-tr-avg-1x">-</div>
                <div class="sa-card-sub">Durchschnittlicher monatlicher Datenverbrauch bei 1x Abspielen pro Kauf</div>
            </div>
            <div class="sa-card" style="border-color: #c3e6cb; background: #f8fff9;">
                <div class="sa-card-title" style="color: #008000;">&Oslash; Traffic pro Monat (2x Realistisch)</div>
                <div class="sa-card-value" style="color: #008000;" id="sa-tr-avg-2x">-</div>
                <div class="sa-card-sub">Empfohlene Kalkulationsbasis inkl. Mehrfach-Ansicht &amp; Spulen</div>
            </div>
        </div>

        <h3 style="font-size: 16px; margin: 20px 0 10px 0; color: #333;">Monatliche Entwicklung (Letzte 6 Monate)</h3>
        <table class="sa-table" id="sa-table-monthly">
            <thead>
                <tr>
                    <th>Monat</th>
                    <th>Streaming-Abrufe</th>
                    <th>Download-Abrufe</th>
                    <th>Gesamtdauer der Videos</th>
                    <th>Streaming-Volumen (1x)</th>
                    <th>Download-Volumen (1x)</th>
                    <th>Gesamt-Traffic (1x Basis)</th>
                    <th>Realistischer Traffic (2x Faktor)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- TAB 3: KONVERTIERUNGS-CHECK & CLOUD-KOSTEN -->
    <div id="tab-sa-cloud" style="display: none;">
        <div class="ui-widget-header" style="padding:10px; font-size:20px; margin-bottom:15px;">Technische Pr&uuml;fung der Konvertierung &amp; Externe Cloud-Kosten</div>

        <h3 style="font-size: 16px; margin: 10px 0 10px 0; color: #333;">3.1 Pr&uuml;fergebnis zur aktuellen Video-Konvertierung (<code>cronjobs/convert_movie.php</code>)</h3>
        <div class="ui-widget-content" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd; font-size: 13px; line-height: 1.6;">
            <ul style="margin: 0; padding-left: 20px;">
                <li style="margin-bottom: 10px;">
                    <strong style="color: #c00000;">Befund 1: <code>m_*.webm</code> und <code>m_*.ogv</code> werden nicht mehr ben&ouml;tigt und nie ausgeliefert:</strong><br />
                    In <code>cronjobs/convert_movie.php</code> werden f&uuml;r jedes Video eine WebM-Datei (<code>libvpx</code>) und eine OGV-Datei (<code>libtheora</code>) in 640px Breite gerendert. In <code>api/movie_player.php</code> wird der GET-Parameter <code>mobile=webm</code> bzw. <code>mobile=ogg</code> jedoch &uuml;berhaupt nicht ausgewertet &ndash; das Skript streamt immer ausschlie&szlig;lich die Hauptdatei <code>$movie_ary-&gt;filename</code> (Desktop-MP4). Zudem unterst&uuml;tzen heute 100 % aller Desktop- und Mobilbrowser nativ MP4 (H.264). Diese beiden Formate k&ouml;nnen aus dem Cronjob entfernt und auf dem Server gel&ouml;scht werden.
                </li>
                <li style="margin-bottom: 10px;">
                    <strong style="color: #d05c00;">Befund 2: <code>m_*.mp4</code> (Mobile MP4 mit 640px) ist derzeit ebenfalls ungenutzt:</strong><br />
                    Obwohl <code>api/get_movie_url.php</code> die URL <code>...&amp;mobile=mp4</code> generiert, ignoriert <code>api/movie_player.php</code> den Parameter <code>$_GET[\'mobile\']</code>. Dadurch erhalten auch mobile Endger&auml;te aktuell immer die gro&szlig;e Desktop-MP4. Da moderne Smartphones hochaufl&ouml;sende Displays und schnelle Verbindungen besitzen, kann entweder auch auf die Generierung von <code>m_*.mp4</code> verzichtet werden, oder der Parameter wird in <code>movie_player.php</code> aktiviert, um Traffic zu sparen.
                </li>
                <li>
                    <strong style="color: #008000;">Befund 3: Optimierungspotenzial beim Desktop-MP4 Encoding:</strong><br />
                    Aktuell nutzt der Cronjob eine starre Bitrate von <code>-b:v 8000k -preset veryslow</code> und kopiert die Datei anschlie&szlig;end separat per <code>qt-faststart</code>. Durch Umstellung auf modernes Qualit&auml;ts-Encoding (z. B. <code>-crf 22 -maxrate 5000k -bufsize 10000k -preset slow -c:a aac -b:a 128k -movflags +faststart</code>) reduziert sich die Dateigr&ouml;&szlig;e k&uuml;nftiger Videos bei identischer Bildqualit&auml;t um ca. 35&ndash;40 %, und der separate Kopiervorgang f&uuml;r <code>qt-faststart</code> entf&auml;llt.
                </li>
            </ul>
        </div>

        <h3 style="font-size: 16px; margin: 20px 0 10px 0; color: #333;">3.2 Monatliche Kosten-Prognose f&uuml;r externe Cloud-Storage-Anbieter (Adult-Content erlaubt)</h3>
        <div class="ui-widget-content" style="padding: 12px 15px; margin-bottom: 12px; border-radius: 4px; border: 1px solid #D1D1D1; background: #fcfdfd; font-size: 13px;">
            Die folgende Kalkulation berechnet die voraussichtlichen monatlichen Kosten anhand Ihres aktuellen Speichervolumens sowie des realistischen monatlichen Streaming-Traffics (2x Faktor):
        </div>
        <table class="sa-table" id="sa-table-providers">
            <thead>
                <tr>
                    <th>Cloud-Anbieter</th>
                    <th>Preismodell (Speicher &amp; Traffic)</th>
                    <th>Adult-Content Richtlinie (AUP)</th>
                    <th>Monatliche Kosten (Aktueller Bestand)</th>
                    <th>Monatliche Kosten (Nach WebM/OGV-Bereinigung)</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        jQuery("#storage_analytics_tabs").tabs();

        function loadStorageAnalytics(forceRefresh) {
            jQuery("#sa-loading-box").show();
            jQuery("#tab-sa-storage, #tab-sa-traffic, #tab-sa-cloud, #sa-header-bar, #sa-sim-notice").hide();

            var url = "' . ACP_URL . '/Ajax/storage_analytics_scan.php";
            if (forceRefresh) {
                url += "?force_refresh=1";
            }

            jQuery.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                success: function(res) {
                    jQuery("#sa-loading-box").hide();

                    if (res.error) {
                        alert("Fehler beim Laden der Analyse: " + res.error);
                        return;
                    }

                    if (res.is_local_simulation) {
                        jQuery("#sa-sim-notice").show();
                    }

                    jQuery("#sa-meta-time").text(res.generated_at);
                    if (res.from_cache) {
                        jQuery("#sa-meta-cache").text("(aus 24h-Cache, vor " + res.cache_age_minutes + " Min. berechnet)");
                    } else {
                        jQuery("#sa-meta-cache").text("(Live neu berechnet in " + res.duration_ms + " ms)");
                    }
                    jQuery("#sa-header-bar").css("display", "flex");

                    // Summary Cards Tab 1
                    jQuery("#sa-card-total-cloud").text(res.summary.total_cloud_formatted);
                    jQuery("#sa-card-movies-total").text(res.summary.movies_total_formatted);
                    jQuery("#sa-card-movies-sub").text(res.total_db_movies + " Filme in der Datenbank");
                    jQuery("#sa-card-legacy-savings").text(res.summary.legacy_savings_formatted);
                    jQuery("#sa-card-legacy-plus-m").text(res.summary.legacy_with_m_mp4_formatted);
                    jQuery("#sa-card-cleaned-total").text(res.summary.cleaned_cloud_formatted);

                    // Disk Bar
                    jQuery("#sa-disk-path").text(res.disk.path);
                    jQuery("#sa-disk-used").text(res.disk.used_formatted);
                    jQuery("#sa-disk-total").text(res.disk.total_formatted);
                    jQuery("#sa-disk-free").text(res.disk.free_formatted);
                    jQuery("#sa-disk-pct").text(res.disk.used_percent + "%");
                    jQuery("#sa-disk-bar").css("width", Math.min(100, res.disk.used_percent) + "%");
                    if (res.disk.used_percent >= 85) {
                        jQuery("#sa-disk-bar").css("background", "#c00000");
                    } else if (res.disk.used_percent >= 70) {
                        jQuery("#sa-disk-bar").css("background", "#d05c00");
                    } else {
                        jQuery("#sa-disk-bar").css("background", "#1c94c4");
                    }

                    // Categories Table
                    var catBody = jQuery("#sa-table-categories tbody");
                    catBody.empty();
                    jQuery.each(res.categories, function(i, c) {
                        var tr = jQuery("<tr>");
                        tr.append(jQuery("<td>").html("<strong>" + c.name + "</strong>"));
                        tr.append(jQuery("<td>").text(c.db_info));
                        tr.append(jQuery("<td>").text(c.files_count));
                        tr.append(jQuery("<td>").html("<strong>" + c.actual_fmt + "</strong>"));
                        tr.append(jQuery("<td>").text(c.est_fmt));
                        catBody.append(tr);
                    });

                    // Formats Table
                    var fmtBody = jQuery("#sa-table-formats tbody");
                    fmtBody.empty();
                    jQuery.each(res.formats, function(i, f) {
                        var tr = jQuery("<tr>");
                        tr.append(jQuery("<td>").html("<strong>" + f.type + "</strong>"));
                        tr.append(jQuery("<td>").text(f.codec));
                        tr.append(jQuery("<td>").html(\'<span class="sa-badge" style="background:\' + f.badge_color + \';">\' + f.usage_status + \'</span>\'));
                        tr.append(jQuery("<td>").text(f.count));
                        tr.append(jQuery("<td>").html("<strong>" + f.actual_fmt + "</strong>"));
                        tr.append(jQuery("<td>").text(f.est_fmt));
                        fmtBody.append(tr);
                    });

                    // Statuses Table
                    var stBody = jQuery("#sa-table-statuses tbody");
                    stBody.empty();
                    jQuery.each(res.statuses, function(i, s) {
                        var tr = jQuery("<tr>");
                        tr.append(jQuery("<td>").html("<strong>" + s.label + "</strong>"));
                        tr.append(jQuery("<td>").text(s.count + " Filme"));
                        tr.append(jQuery("<td>").text(s.hours));
                        tr.append(jQuery("<td>").html("<strong>" + s.effective_fmt + "</strong>"));
                        stBody.append(tr);
                    });

                    // Traffic Tab 2
                    jQuery("#sa-tr-total-count").text(res.traffic.total_count_6m + " Zugriffe");
                    jQuery("#sa-tr-stream-cnt").text(res.traffic.streaming_count_6m);
                    jQuery("#sa-tr-dl-cnt").text(res.traffic.download_count_6m);
                    jQuery("#sa-tr-users").text(res.traffic.unique_users_6m);
                    jQuery("#sa-tr-total-6m").text(res.traffic.total_bytes_6m_fmt);
                    jQuery("#sa-tr-stream-6m").text(res.traffic.streaming_bytes_6m_fmt);
                    jQuery("#sa-tr-stream-hrs").text(res.traffic.streaming_hours_6m);
                    jQuery("#sa-tr-dl-6m").text(res.traffic.download_bytes_6m_fmt);
                    jQuery("#sa-tr-dl-hrs").text(res.traffic.download_hours_6m);
                    jQuery("#sa-tr-avg-1x").text(res.traffic.avg_monthly_1x_fmt + " / Monat");
                    jQuery("#sa-tr-avg-2x").text(res.traffic.avg_monthly_2x_fmt + " / Monat");

                    var mBody = jQuery("#sa-table-monthly tbody");
                    mBody.empty();
                    jQuery.each(res.traffic.monthly_rows, function(i, m) {
                        var tr = jQuery("<tr>");
                        tr.append(jQuery("<td>").html("<strong>" + m.month_label + "</strong>"));
                        tr.append(jQuery("<td>").text(m.streaming_count));
                        tr.append(jQuery("<td>").text(m.download_count));
                        tr.append(jQuery("<td>").text(m.playtime_hours));
                        tr.append(jQuery("<td>").text(m.streaming_formatted));
                        tr.append(jQuery("<td>").text(m.download_formatted));
                        tr.append(jQuery("<td>").html("<strong>" + m.total_formatted + "</strong>"));
                        tr.append(jQuery("<td>").html(\'<strong style="color:#008000;">\' + m.realistic_2x_formatted + \'</strong>\'));
                        mBody.append(tr);
                    });

                    // Cloud Providers Tab 3
                    var provBody = jQuery("#sa-table-providers tbody");
                    provBody.empty();
                    jQuery.each(res.providers, function(i, p) {
                        var tr = jQuery("<tr>");
                        tr.append(jQuery("<td>").html("<strong>" + p.name + "</strong>"));
                        tr.append(jQuery("<td>").text(p.rate_info));
                        tr.append(jQuery("<td>").text(p.adult_policy));
                        tr.append(jQuery("<td>").html("<strong>" + p.cost_now_eur + "</strong>"));
                        tr.append(jQuery("<td>").html(\'<strong style="color:#008000;">\' + p.cost_cleaned_eur + \'</strong>\'));
                        provBody.append(tr);
                    });

                    jQuery("#tab-sa-storage, #tab-sa-traffic, #tab-sa-cloud").show();
                    jQuery("#storage_analytics_tabs").tabs("refresh");
                },
                error: function(xhr, status, err) {
                    jQuery("#sa-loading-box").hide();
                    alert("Es ist ein Fehler bei der Abfrage aufgetreten: " + err);
                }
            });
        }

        jQuery("#btn-sa-refresh").click(function() {
            loadStorageAnalytics(true);
        });

        loadStorageAnalytics(false);
    });
// ]]>
</script>
';

?>
