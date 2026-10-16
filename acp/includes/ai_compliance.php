<?php

/**
 * @author      Martin Zimmermann
 * @copyright   (C) 2016 adult net applications UG
 * @email       erocms@gmail.com
 *
 * ACP Frontend: Lokales KI-Testlabor, Batch-/Cronjob-Steuerung & Visa/Mastercard BRAM-Compliance-Cockpit (/KI-Analyse)
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<style type="text/css">
    .aic-card-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 18px;
    }
    .aic-card {
        flex: 1 1 220px;
        background: #fcfdfd;
        border: 1px solid #D1D1D1;
        border-radius: 4px;
        padding: 15px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .aic-card-title {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        font-weight: bold;
    }
    .aic-card-value {
        font-size: 22px;
        font-weight: bold;
        color: #1c94c4;
        margin-bottom: 5px;
    }
    .aic-card-sub {
        font-size: 12px;
        color: #555;
        line-height: 1.4;
    }
    .aic-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin-bottom: 18px;
        background: #fff;
    }
    .aic-table th {
        background: #e9e9e9;
        color: #333;
        font-weight: bold;
        text-align: left;
        padding: 10px 12px;
        border: 1px solid #ccc;
    }
    .aic-table td {
        padding: 9px 12px;
        border: 1px solid #ddd;
        vertical-align: top;
    }
    .aic-table tr:nth-child(even) {
        background: #f9f9f9;
    }
    .aic-badge {
        display: inline-block;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: bold;
        color: #fff;
        border-radius: 3px;
    }
    .aic-step-box {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 18px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .aic-step-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    .aic-step-title {
        font-size: 16px;
        font-weight: bold;
        color: #222;
    }
    .aic-input, .aic-select, .aic-textarea {
        border: 1px solid #bbb;
        border-radius: 3px;
        padding: 6px 10px;
        font-size: 13px;
        font-family: Arial, sans-serif;
    }
    .aic-textarea-mono {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #bbb;
        border-radius: 3px;
        padding: 10px;
        font-size: 12px;
        font-family: Consolas, Monaco, monospace;
        line-height: 1.45;
        background: #fafafa;
    }
    .aic-btn-primary {
        background: #1c94c4;
        color: #fff;
        border: 1px solid #157ba3;
        padding: 8px 15px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
    }
    .aic-btn-primary:hover {
        background: #157ba3;
    }
    .aic-btn-success {
        background: #28a745;
        color: #fff;
        border: 1px solid #1e7e34;
        padding: 8px 15px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
    }
    .aic-btn-success:hover {
        background: #218838;
    }
    .aic-btn-danger {
        background: #dc3545;
        color: #fff;
        border: 1px solid #bd2130;
        padding: 8px 15px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
    }
    .aic-btn-secondary {
        background: #f4f4f4;
        color: #333;
        border: 1px solid #bbb;
        padding: 7px 13px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        font-size: 12px;
    }
    .aic-btn-secondary:hover {
        background: #e6e6e6;
    }
    .aic-kw-pill {
        display: inline-block;
        background: #f0f4f8;
        border: 1px solid #c9d8e6;
        color: #2c4a67;
        padding: 2px 7px;
        margin: 2px 3px 2px 0;
        border-radius: 3px;
        font-size: 11px;
        font-family: Consolas, monospace;
    }
    .aic-progress-outer {
        width: 100%;
        height: 22px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        border: 1px solid #ced4da;
        position: relative;
    }
    .aic-progress-inner {
        height: 100%;
        background: linear-gradient(90deg, #1c94c4, #28a745);
        width: 0%;
        transition: width 0.3s ease;
    }
    .aic-progress-label {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        line-height: 22px;
        text-align: center;
        font-size: 12px;
        font-weight: bold;
        color: #222;
        text-shadow: 0 0 3px #fff;
    }
</style>

<div id="ai_compliance_tabs">
    <ul>
        <li><a href="#tab-aic-lab">1. Einzel-Video-Testlabor</a></li>
        <li><a href="#tab-aic-batch">2. Batch- &amp; Cronjob-Steuerung (Alle Videos)</a></li>
        <li><a href="#tab-aic-rules">3. Visa/Mastercard BRAM-Regelwerk &amp; Prompt</a></li>
        <li><a href="#tab-aic-history">4. Analyse-Historie &amp; Filter (<span id="aic-hist-count">0</span>)</a></li>
    </ul>

    <!-- KOPF-STATISTIKEN & SYSTEM-STATUS -->
    <div style="padding: 15px 15px 0 15px;">
        <div class="aic-card-grid">
            <div class="aic-card">
                <div class="aic-card-title">Konvertierte Videos (Bestand)</div>
                <div class="aic-card-value" id="aic-stat-total">-</div>
                <div class="aic-card-sub">Bereit f&uuml;r Audio- &amp; KI-Analyse (<code>convert_status = 2</code>)</div>
            </div>
            <div class="aic-card">
                <div class="aic-card-title">1. Audio &amp; Untertitel (Whisper)</div>
                <div class="aic-card-value"><span id="aic-stat-subtitled">-</span> <small style="font-size: 13px; color: #666;">(<span id="aic-stat-sub-pct">0</span>%)</small></div>
                <div class="aic-card-sub">Noch offen: <strong id="aic-stat-sub-rem">-</strong> Videos ohne <code>.vtt</code> / Transkript</div>
            </div>
            <div class="aic-card">
                <div class="aic-card-title">2. BRAM- &amp; Text-Compliance</div>
                <div class="aic-card-value"><span id="aic-stat-checked">-</span> <small style="font-size: 13px; color: #666;">(<span id="aic-stat-txt-pct">0</span>%)</small></div>
                <div class="aic-card-sub">Noch ungepr&uuml;ft: <strong id="aic-stat-txt-rem">-</strong> Videos</div>
            </div>
            <div class="aic-card" style="border-color: #c3e6cb; background: #f8fff9;">
                <div class="aic-card-title" style="color: #155724;">Compliance-Bilanz (Gepr&uuml;fte Filme)</div>
                <div style="font-size: 14px; font-weight: bold; margin-top: 6px; line-height: 1.7;">
                    <span class="aic-badge" style="background: #28a745; cursor: pointer;" onclick="jQuery(\'#ai_compliance_tabs\').tabs(\'option\', \'active\', 3); jQuery(\'#aic-filter-comp\').val(\'ok\').trigger(\'change\');" title="Klicken zum Filtern">OK: <span id="aic-stat-ok">0</span></span>
                    <span class="aic-badge" style="background: #e0a800; color: #111; margin-left: 5px; cursor: pointer;" onclick="jQuery(\'#ai_compliance_tabs\').tabs(\'option\', \'active\', 3); jQuery(\'#aic-filter-comp\').val(\'warning\').trigger(\'change\');" title="Klicken zum Filtern">Gelb (Ohne KK): <span id="aic-stat-warn">0</span></span>
                    <span class="aic-badge" style="background: #dc3545; margin-left: 5px; cursor: pointer;" onclick="jQuery(\'#ai_compliance_tabs\').tabs(\'option\', \'active\', 3); jQuery(\'#aic-filter-comp\').val(\'violation\').trigger(\'change\');" title="Klicken zum Filtern">Rot (Versto&szlig;): <span id="aic-stat-viol">0</span></span>
                </div>
                <div class="aic-card-sub" style="margin-top: 6px;">Klicken Sie auf ein Badge, um die Liste zu filtern</div>
            </div>
        </div>

        <!-- SYSTEM-BEREITSCHAFTSANZEIGE -->
        <div style="background: #f4f8fb; border: 1px solid #c5dbec; border-radius: 4px; padding: 10px 15px; margin-bottom: 10px; font-size: 12px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px;">
            <div>
                <strong>System-Komponenten:</strong>
                <span id="aic-env-rules" class="aic-badge" style="background: #28a745; margin-left: 8px;">BRAM-Regel-Engine: Aktiv</span>
                <span id="aic-env-ffmpeg" class="aic-badge" style="background: #6c757d; margin-left: 6px;">FFmpeg: Pr&uuml;fe...</span>
                <span id="aic-env-whisper" class="aic-badge" style="background: #6c757d; margin-left: 6px;">whisper.cpp: Pr&uuml;fe...</span>
                <span id="aic-env-llm" class="aic-badge" style="background: #6c757d; margin-left: 6px;">Lokale KI (llama.cpp): Pr&uuml;fe...</span>
                <span id="aic-env-cron" class="aic-badge" style="background: #6c757d; margin-left: 6px;">Hintergrund-Cronjob: Pr&uuml;fe...</span>
            </div>
            <div>
                <button type="button" id="btn-aic-reload-dash" class="aic-btn-secondary">&#x21bb; Status aktualisieren</button>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 1: EINZEL-VIDEO-TESTLABOR                                      -->
    <!-- ================================================================== -->
    <div id="tab-aic-lab">
        <div class="ui-widget-header" style="padding:10px; font-size:18px; margin-bottom:15px;">
            Einzel-Video-Testlabor: Schrittweise Pr&uuml;fung an einem ausgew&auml;hlten Creator-Video
        </div>

        <!-- AUSWAHL-BOX -->
        <div class="aic-step-box" style="background: #fcfdfd;">
            <div class="aic-step-header">
                <div class="aic-step-title">&#x1F50D; Video zum Testen ausw&auml;hlen</div>
                <div style="font-size: 12px; color: #666;">W&auml;hlen Sie ein einzelnes Video aus dem Bestand aus, um Audio-Extraktion, Untertitel und BRAM-Pr&uuml;fung zu testen.</div>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Schnellauswahl (Neueste konvertierte Videos):</label>
                    <select id="aic-select-recent" class="aic-select" style="min-width: 340px;">
                        <option value="">-- Bitte Video w&auml;hlen --</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Oder gezielte Movie-ID:</label>
                    <input type="number" id="aic-input-movie-id" class="aic-input" placeholder="z. B. 8613" style="width: 120px;" />
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Oder H&auml;ndler-ID:</label>
                    <input type="number" id="aic-input-merchant-id" class="aic-input" placeholder="z. B. 10061" style="width: 120px;" />
                </div>
                <div>
                    <button type="button" id="btn-aic-load-movie" class="aic-btn-primary">Video laden</button>
                </div>
                <div>
                    <button type="button" id="btn-aic-load-untested" class="aic-btn-secondary">N&auml;chstes Video ohne Untertitel</button>
                </div>
                <div>
                    <button type="button" id="btn-aic-find-interesting" class="aic-btn-secondary" style="border-color: #e0a800; background: #fff9e6; color: #856404;" title="Sucht in der Datenbank nach einem echten Video, dessen Titel oder Beschreibung einen BRAM-Pr&uuml;fbegriff enth&auml;lt">
                        &#x26A0; Video mit potenziellem BRAM-Treffer aus Bestand laden
                    </button>
                </div>
            </div>
        </div>

        <!-- CONTAINER FUER DAS GELADENE VIDEO -->
        <div id="aic-loaded-movie-wrap" style="display: none;">

            <!-- GRUNDINFOS & EDITIERBARE TEXTE DES VIDEOS -->
            <div class="aic-step-box">
                <div class="aic-step-header">
                    <div class="aic-step-title">
                        &#x1F3AC; Geladenes Test-Video: <span id="aic-m-title-head" style="color: #1c94c4;">-</span>
                        (Movie-ID: <strong id="aic-m-id">-</strong> | H&auml;ndler-ID: <strong id="aic-m-merchant">-</strong>)
                    </div>
                    <div>
                        <a id="aic-m-check-link" href="#" target="_blank" class="aic-btn-secondary" style="text-decoration: none;">Im ACP Film-Check &ouml;ffnen &#x2197;</a>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 15px; font-size: 13px; background: #f8f9fa; padding: 10px 14px; border-radius: 4px; border: 1px solid #e2e6ea;">
                    <div><strong>Spieldauer:</strong> <span id="aic-m-duration">-</span> Min.</div>
                    <div><strong>Video-Datei:</strong> <code id="aic-m-filepath">-</code></div>
                    <div><strong>Physische MP4:</strong> <span id="aic-m-mp4-status">-</span></div>
                    <div><strong>Freigabe-Status:</strong> <span id="aic-m-online-status">-</span></div>
                </div>

                <div style="margin-bottom: 10px; font-size: 12px; color: #555;">
                    <em>Tipp f&uuml;r das Testlabor: Sie k&ouml;nnen Titel und Beschreibung unten zu Testzwecken anpassen (ohne den Originalfilm in der Tabelle <code>movies</code> zu &uuml;berschreiben), um live zu testen, wie die BRAM-Pr&uuml;fung auf kritische Begriffe reagiert.</em>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                    <div style="flex: 1 1 350px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Filmtitel (Creator-Eingabe):</label>
                        <input type="text" id="aic-edit-title" class="aic-input" style="width: 100%; box-sizing: border-box;" />
                        <div style="margin-top: 8px; font-size: 12px; color: #666;">
                            <strong>SEO Meta-Title:</strong> <span id="aic-m-meta-title">-</span><br/>
                            <strong>SEO Meta-Description:</strong> <span id="aic-m-meta-desc">-</span>
                        </div>
                    </div>
                    <div style="flex: 2 1 450px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Filmbeschreibung (Creator-Eingabe):</label>
                        <textarea id="aic-edit-desc" class="aic-textarea" rows="4" style="width: 100%; box-sizing: border-box;"></textarea>
                    </div>
                </div>
            </div>

            <!-- ========================================================== -->
            <!-- SCHRITT 1: AUDIO-EXTRAKTION, SPRACHERKENNUNG & UNTERTITEL  -->
            <!-- ========================================================== -->
            <div class="aic-step-box" style="border-left: 4px solid #1c94c4;">
                <div class="aic-step-header">
                    <div class="aic-step-title">
                        Schritt 1: Gesprochenen Text aus Video auslesen, Sprache erkennen &amp; Untertitel (<code>.vtt</code>) erstellen
                    </div>
                    <div id="aic-step1-badge-wrap">
                        <span class="aic-badge" style="background: #6c757d;">Noch nicht ausgef&uuml;hrt</span>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 15px; background: #f4f8fb; padding: 12px; border-radius: 4px;">
                    <div>
                        <label style="font-size: 12px; font-weight: bold; margin-right: 6px;">Modus:</label>
                        <select id="aic-sim-scenario" class="aic-select">
                            <option value="clean">Live-Audio aus echter MP4 auswerten (whisper.cpp)</option>
                            <option value="bram_violation">Test-Szenario ROT: Gesprochener BRAM-Versto&szlig; im Video (K.O.-Tropfen / Kinderzimmer / PayPal)</option>
                            <option value="bram_warning">Test-Szenario GELB: Gesprochene Grauzone im Video (Stiefmutter / KV / Natursekt)</option>
                            <option value="english_clean">Test-Szenario EN: Englischer Video-Dialog (Spracherkennung Englisch)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: bold; margin-right: 6px;">Audio-Umfang:</label>
                        <select id="aic-audio-limit" class="aic-select">
                            <option value="120">Erste 2 Min. (Schnell-Test ~10 Sek.)</option>
                            <option value="300">Erste 5 Min. (~25 Sek.)</option>
                            <option value="0">Komplettes Video</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" id="btn-aic-run-whisper" class="aic-btn-primary">
                            &#x1F3A4; 1. Audio jetzt auswerten &amp; Untertitel erzeugen
                        </button>
                    </div>
                    <div id="aic-whisper-timer" style="font-size: 12px; color: #555;"></div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                    <div style="flex: 3 1 480px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold;">
                                Erkanntes gesprochenes Transkript mit Zeitstempeln (direkt editierbar f&uuml;r Schritt 2):
                            </label>
                            <span style="font-size: 12px;">
                                Erkannte Sprache: <strong id="aic-out-lang" style="color: #1c94c4;">-</strong> |
                                Untertitel-Datei: <code id="aic-out-subfile">-</code>
                            </span>
                        </div>
                        <textarea id="aic-edit-transcript" class="aic-textarea-mono" rows="7" placeholder="Hier erscheint nach Schritt 1 der im Video gesprochene Text mit Zeitstempeln (z. B. [00:12 - 00:19] ...). Sie k&ouml;nnen hier auch beliebige eigene S&auml;tze zum Testen der KI eingeben."></textarea>
                    </div>
                    <div style="flex: 2 1 320px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">
                            Erzeugte WebVTT-Untertiteldatei (<code>.vtt</code> f&uuml;r Videoplayer):
                        </label>
                        <textarea id="aic-out-vtt" class="aic-textarea-mono" rows="7" readonly="readonly" style="background: #f3f4f6; color: #444;" placeholder="WEBVTT Untertitel-Vorschau..."></textarea>
                    </div>
                </div>
            </div>

            <!-- ========================================================== -->
            <!-- SCHRITT 2: BRAM- & RECHTS-PRUEFUNG (TEXT + AUDIO)          -->
            <!-- ========================================================== -->
            <div class="aic-step-box" style="border-left: 4px solid #28a745;">
                <div class="aic-step-header">
                    <div class="aic-step-title">
                        Schritt 2: Texte (Titel, Beschreibung, SEO) &amp; gesprochenes Audio auf Visa/Mastercard BRAM pr&uuml;fen
                    </div>
                    <div>
                        <button type="button" id="btn-aic-run-textcheck" class="aic-btn-success">
                            &#x1F6E1; 2. Jetzt BRAM- &amp; Rechts-Pr&uuml;fung starten
                        </button>
                    </div>
                </div>

                <!-- ERGEBNIS-BANNER -->
                <div id="aic-report-box" style="display: none;">
                    <div id="aic-report-banner" style="padding: 14px 18px; border-radius: 4px; margin-bottom: 15px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                        <div>
                            <div id="aic-report-status-title" style="font-size: 17px; font-weight: bold; margin-bottom: 4px;">-</div>
                            <div id="aic-report-summary" style="font-size: 13px; line-height: 1.45;">-</div>
                        </div>
                        <div style="text-align: right; min-width: 180px;">
                            <div style="font-size: 12px; opacity: 0.85;">BRAM Risiko-Score:</div>
                            <div style="font-size: 26px; font-weight: bold;"><span id="aic-report-score">0</span> / 100</div>
                            <div style="font-size: 11px; opacity: 0.8;" id="aic-report-engine">-</div>
                        </div>
                    </div>

                    <h4 style="font-size: 14px; margin: 12px 0 8px 0; color: #333;">Detaillierte Fundstellen in Titel, Beschreibung &amp; gesprochenem Video-Audio:</h4>
                    <table class="aic-table" id="aic-table-violations">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Einstufung</th>
                                <th style="width: 240px;">BRAM- / Rechts-Kategorie</th>
                                <th style="width: 170px;">Fundort &amp; Zeitstempel</th>
                                <th style="width: 150px;">Erkannter Begriff</th>
                                <th>Textstelle (Zitat) &amp; Erl&auml;uterung</th>
                            </tr>
                        </thead>
                        <tbody id="aic-tbody-violations">
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666;">Noch keine Pr&uuml;fung durchgef&uuml;hrt.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 2: BATCH- & CRONJOB-STEUERUNG (ALLE >5.500 VIDEOS)             -->
    <!-- ================================================================== -->
    <div id="tab-aic-batch" style="display: none;">
        <div class="ui-widget-header" style="padding:10px; font-size:18px; margin-bottom:15px;">
            Automatische Katalog-Verarbeitung: Untertitel-Generierung (<code>.vtt</code>) &amp; BRAM-Compliance f&uuml;r alle Videos
        </div>

        <!-- FORTSCHRITTSBALKEN FUER STUFE 1 UND STUFE 2 -->
        <div class="aic-step-box" style="background: #fcfdfd;">
            <div class="aic-step-header">
                <div class="aic-step-title">&#x1F4CA; Gesamtfortschritt des Videokatalogs</div>
                <div id="aic-worker-live-badge">
                    <span class="aic-badge" style="background: #6c757d;">Worker: Bereit</span>
                </div>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; margin-bottom: 5px;">
                    <span>Stufe 1: Audio-Transkription, Spracherkennung &amp; <code>.vtt</code>-Untertitel (<code>whisper.cpp</code>)</span>
                    <span id="aic-prog-sub-text">0 / 0 Videos (0%)</span>
                </div>
                <div class="aic-progress-outer">
                    <div class="aic-progress-inner" id="aic-prog-sub-bar" style="width: 0%;"></div>
                    <div class="aic-progress-label" id="aic-prog-sub-label">0%</div>
                </div>
                <div style="font-size: 12px; color: #555; margin-top: 5px; display: flex; gap: 18px; flex-wrap: wrap;">
                    <span>&#x2705; Mit <code>.vtt</code> &amp; Transkript: <strong id="aic-det-sub-ok">0</strong></span>
                    <span>&#x1F507; Stummfilm (Kein Audio): <strong id="aic-det-sub-noaudio">0</strong></span>
                    <span>&#x26A0; MP4 fehlt / Fehler: <strong id="aic-det-sub-err">0</strong></span>
                    <span>&#x23F3; Noch offen: <strong id="aic-det-sub-rem">0</strong></span>
                </div>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; margin-bottom: 5px;">
                    <span>Stufe 2: BRAM- &amp; Rechts-Textpr&uuml;fung (Titel, Beschreibung, SEO &amp; Transkript)</span>
                    <span id="aic-prog-txt-text">0 / 0 Videos (0%)</span>
                </div>
                <div class="aic-progress-outer">
                    <div class="aic-progress-inner" id="aic-prog-txt-bar" style="width: 0%; background: linear-gradient(90deg, #28a745, #20c997);"></div>
                    <div class="aic-progress-label" id="aic-prog-txt-label">0%</div>
                </div>
                <div style="font-size: 12px; color: #555; margin-top: 5px;">
                    Letzte Worker-Aktivit&auml;t: <strong id="aic-worker-last-msg">-</strong> (<span id="aic-worker-last-time">-</span>)
                </div>
            </div>
        </div>

        <!-- AKTION A: SCHNELLER VORAB-SCAN ALLER TEXT-METADATEN -->
        <div class="aic-step-box" style="border-left: 4px solid #e0a800;">
            <div class="aic-step-header">
                <div class="aic-step-title">&#x26A1; Schnell-Scan: Alle >5.500 Video-Titel, Beschreibungen &amp; vorhandene Transkripte sofort pr&uuml;fen</div>
                <div style="font-size: 12px; color: #666;">Dauert f&uuml;r den gesamten Katalog nur ca. 15 Sekunden (250 Filme pro Paket)</div>
            </div>
            <p style="font-size: 13px; color: #444; margin-top: 0; line-height: 1.5;">
                Mit dieser Funktion k&ouml;nnen Sie <strong>sofort alle bestehenden Videos</strong> anhand ihrer Titel, Beschreibungen, SEO-Angaben und bereits vorhandenen Audio-Transkripte gegen das aktuelle BRAM-Regelwerk pr&uuml;fen – ohne darauf warten zu m&uuml;ssen, bis Whisper alle Videodateien transkribiert hat. Sobald Whisper sp&auml;ter f&uuml;r ein Video die Audiospur transkribiert, wird die Pr&uuml;fung automatisch inklusive des gesprochenen Textes aktualisiert.
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                <button type="button" id="btn-aic-bulk-precheck" class="aic-btn-primary" style="background: #e0a800; border-color: #c69500; color: #111;">
                    &#x26A1; Alle noch offenen Video-Texte jetzt im Schnelllauf pr&uuml;fen
                </button>
                <button type="button" id="btn-aic-bulk-recheck-all" class="aic-btn-secondary" title="Pr&uuml;ft alle bereits analysierten Filme noch einmal mit den aktuell gespeicherten BRAM-Regeln (z. B. nach &Auml;nderung der Wortliste)">
                    &#x1F504; Gesamten Katalog mit aktuellem BRAM-Regelwerk neu bewerten
                </button>
                <span id="aic-bulk-precheck-status" style="font-size: 13px; font-weight: bold; color: #155724;"></span>
            </div>
        </div>

        <!-- AKTION B: LIVE-BROWSER-BATCH & SERVER-CRONJOB KONFIGURATION -->
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">

            <!-- LINKE SPALTE: LIVE-BATCH IM BROWSER -->
            <div class="aic-step-box" style="flex: 1 1 440px; border-left: 4px solid #1c94c4;">
                <div class="aic-step-header">
                    <div class="aic-step-title">&#x25B6; Modus 1: Live-Whisper-Batch hier im Browser</div>
                </div>
                <p style="font-size: 13px; color: #444; margin-top: 0; line-height: 1.5;">
                    Verarbeitet ein offenes Video nach dem anderen (Audio-Extraktion &rarr; <code>whisper.cpp</code> &rarr; <code>.vtt</code>-Untertitel &rarr; BRAM-Pr&uuml;fung) und zeigt den Fortschritt live im Protokollfenster an. Sie k&ouml;nnen den Durchlauf jederzeit pausieren.
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                    <button type="button" id="btn-aic-start-live-batch" class="aic-btn-success">
                        &#x25B6; Live-Batch starten (Auto-Loop)
                    </button>
                    <button type="button" id="btn-aic-stop-live-batch" class="aic-btn-danger" style="display: none;">
                        &#x23F8; Nach aktuellem Video pausieren
                    </button>
                    <button type="button" id="btn-aic-step-live-batch" class="aic-btn-secondary">
                        &#x23ED; Nur 1 n&auml;chstes Video jetzt abarbeiten
                    </button>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="font-size: 12px; font-weight: bold;">Live-Verarbeitungsprotokoll:</label>
                    <span id="aic-live-batch-ticker" style="font-size: 12px; font-weight: bold; color: #1c94c4;"></span>
                </div>
                <textarea id="aic-live-batch-log" class="aic-textarea-mono" rows="9" readonly="readonly" style="background: #1e2227; color: #a6e22e;" placeholder="Bereit. Klicken Sie auf &bdquo;Live-Batch starten&ldquo; oder &bdquo;Nur 1 n&auml;chstes Video jetzt abarbeiten&ldquo;..."></textarea>
            </div>

            <!-- RECHTE SPALTE: HINTERGRUND-CRONJOB EINSTELLUNGEN -->
            <div class="aic-step-box" style="flex: 1 1 480px; border-left: 4px solid #28a745;">
                <div class="aic-step-header">
                    <div class="aic-step-title">&#x2699; Modus 2: Automatischer Hintergrund-Cronjob (Server)</div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                    <div style="background: #f4f8fb; padding: 10px 12px; border-radius: 4px; border: 1px solid #c5dbec;">
                        <label style="font-weight: bold; cursor: pointer;">
                            <input type="checkbox" id="aic-cfg-cron-enabled" />
                            Hintergrund-Cronjob aktivieren (<code>cronjobs/ai_compliance_worker.php</code>)
                        </label>
                    </div>

                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1 1 200px;">
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Whisper-Sprachmodell:</label>
                            <select id="aic-cfg-model" class="aic-select" style="width: 100%;">
                                <option value="small">ggml-small-q5_1.bin (Empfohlen: ~20s / Video, 430 MB RAM)</option>
                                <option value="turbo">ggml-large-v3-turbo-q5_0.bin (Maximal: ~110s / Video, 900 MB RAM)</option>
                            </select>
                        </div>
                        <div style="flex: 1 1 200px;">
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Audio-Umfang f&uuml;r Batch &amp; <code>.vtt</code>:</label>
                            <select id="aic-cfg-audiolimit" class="aic-select" style="width: 100%;">
                                <option value="0">Komplettes Video (Vollst&auml;ndige .vtt-Untertitel)</option>
                                <option value="300">Erste 5 Minuten (300 Sek.)</option>
                                <option value="180">Erste 3 Minuten (180 Sek.)</option>
                                <option value="120">Erste 2 Minuten (120 Sek.)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1 1 140px;">
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">CPU-Threads (von 40):</label>
                            <select id="aic-cfg-threads" class="aic-select" style="width: 100%;">
                                <option value="8">8 Threads (Sehr sanft)</option>
                                <option value="12">12 Threads (Empfohlen)</option>
                                <option value="16">16 Threads (Schnell)</option>
                                <option value="20">20 Threads (Maximal)</option>
                            </select>
                        </div>
                        <div style="flex: 1 1 140px;">
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Videos pro Cron-Lauf:</label>
                            <select id="aic-cfg-maxruns" class="aic-select" style="width: 100%;">
                                <option value="1">1 Video pro Aufruf</option>
                                <option value="2">2 Videos pro Aufruf</option>
                                <option value="3">3 Videos pro Aufruf</option>
                                <option value="5">5 Videos pro Aufruf</option>
                            </select>
                        </div>
                        <div style="flex: 1 1 160px;">
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Reihenfolge:</label>
                            <select id="aic-cfg-order" class="aic-select" style="width: 100%;">
                                <option value="DESC">Neueste Videos zuerst</option>
                                <option value="ASC">&Auml;lteste Videos zuerst</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; cursor: pointer; margin-bottom: 5px;">
                            <input type="checkbox" id="aic-cfg-pause-ffmpeg" />
                            Automatisch pausieren, solange <code>convert_movie.php</code> gerade ein neues Video konvertiert (<code>convert_status = 1</code>)
                        </label>
                        <label style="display: block; cursor: pointer;">
                            <input type="checkbox" id="aic-cfg-sync-lang" />
                            Erkannte Sprache (<code>de</code>, <code>en</code>, ...) automatisch in <code>movies.movie_language</code> eintragen, falls dort noch leer
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                        <span id="aic-save-batch-msg" style="font-size: 12px; font-weight: bold;"></span>
                        <button type="button" id="btn-aic-save-batch-cfg" class="aic-btn-primary">
                            &#x1F4BE; Batch- &amp; Cronjob-Einstellungen speichern
                        </button>
                    </div>

                    <div style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 10px; font-size: 11px; color: #444; margin-top: 5px;">
                        <strong>Befehl zum einmaligen Testen auf der Server-Konsole:</strong><br/>
                        <code style="display: block; background: #fff; padding: 4px 6px; border: 1px solid #ccc; margin: 4px 0;">/usr/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php --force --limit=1</code>
                        <strong>Eintrag f&uuml;r die Server-Crontab (alle 2 Minuten im Hintergrund):</strong><br/>
                        <code style="display: block; background: #fff; padding: 4px 6px; border: 1px solid #ccc; margin: 4px 0;">*/2 * * * * /usr/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php &gt;/dev/null 2&gt;&amp;1</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 3: VISA/MASTERCARD BRAM-REGELWERK & KI-SYSTEM-PROMPT           -->
    <!-- ================================================================== -->
    <div id="tab-aic-rules" style="display: none;">
        <div class="ui-widget-header" style="padding:10px; font-size:18px; margin-bottom:15px;">
            Visa GBPP / Mastercard BRAM &amp; StGB Compliance-Regelwerk (Wissensbasis der lokalen KI)
        </div>

        <div style="background: #fcfdfd; border: 1px solid #d1d1d1; border-radius: 4px; padding: 15px; margin-bottom: 18px; font-size: 13px; line-height: 1.5;">
            <strong>Einstufungs-Logik f&uuml;r EroCloud &amp; Partnerportale:</strong><br/>
            &#x1F534; <strong>ROT (<code>violation</code> / Hard-Block):</strong> Absolut verbotene Inhalte auf <strong>allen</strong> Portalen (Minderj&auml;hrige/CSAM, Vergewaltigung/Zwang, K.O.-Tropfen/Tiefschlaf, echter Inzest, Blut/Waffen/Atemkontrolle, Tiersex).<br/>
            &#x1F7E1; <strong>GELB (<code>warning</code> / Pr&uuml;fhinweis):</strong> Verst&ouml;&szlig;t zwar gegen Visa/Mastercard BRAM, ist aber auf Portalen <strong>ohne Kreditkartenabrechnung</strong> zul&auml;ssig (insbesondere <strong>KV / Kaviar / Schei&szlig;e</strong>, <strong>NS / Natursekt</strong>, <strong>Poppers</strong>, <strong>Hypnose</strong> sowie Stief-Familien-Rollenspiele) oder erfordert eine kurze Sichtung (PayPal/Telegram-Abwerbung).
        </div>

        <h3 style="font-size: 15px; margin: 15px 0 8px 0;">Aktive Pr&uuml;fkategorien &amp; hinterlegte Schl&uuml;sselbegriffe (DE / EN)</h3>
        <table class="aic-table">
            <thead>
                <tr>
                    <th style="width: 260px;">Kategorie</th>
                    <th style="width: 140px;">Stufe &amp; Score</th>
                    <th style="width: 280px;">Beschreibung</th>
                    <th>Hinterlegte Pr&uuml;fbegriffe / Wortst&auml;mme</th>
                </tr>
            </thead>
            <tbody id="aic-tbody-rule-cats">
                <tr><td colspan="4">Lade Regelwerk...</td></tr>
            </tbody>
        </table>

        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
            <div style="flex: 1 1 480px;">
                <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px;">
                    1. KI-System-Prompt (<code>includes/ai_rules/bram_system_prompt.md</code>):
                </label>
                <textarea id="aic-rules-md-editor" class="aic-textarea-mono" rows="22"></textarea>
            </div>
            <div style="flex: 1 1 480px;">
                <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px;">
                    2. Strukturierter BRAM-Keyword-Katalog (<code>includes/ai_rules/bram_keyword_rules.json</code>):
                </label>
                <textarea id="aic-rules-json-editor" class="aic-textarea-mono" rows="22"></textarea>
            </div>
        </div>

        <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;">
            <span id="aic-save-rules-msg" style="font-size: 13px; font-weight: bold;"></span>
            <button type="button" id="btn-aic-save-rules" class="aic-btn-primary">
                &#x1F4BE; BRAM-Regelwerk &amp; KI-Prompt speichern
            </button>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 4: ANALYSE-HISTORIE & FILTER (MOVIES_AI_ANALYSIS)              -->
    <!-- ================================================================== -->
    <div id="tab-aic-history" style="display: none;">
        <div class="ui-widget-header" style="padding:10px; font-size:18px; margin-bottom:15px;">
            Bereits analysierte Videos in der Datenbank (<code>movies_ai_analysis</code>)
        </div>

        <!-- FILTER-LEISTE -->
        <div class="aic-step-box" style="background: #fcfdfd; padding: 12px 15px; margin-bottom: 15px;">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Filter nach BRAM-Ampel:</label>
                    <select id="aic-filter-comp" class="aic-select">
                        <option value="all">Alle Status anzeigen</option>
                        <option value="violation">&#x1F534; Nur ROT (Hard-Block / Versto&szlig;)</option>
                        <option value="warning">&#x1F7E1; Nur GELB (Pr&uuml;fhinweis / Nur ohne KK)</option>
                        <option value="ok">&#x1F7E2; Nur GR&Uuml;N (Unbedenklich / OK)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Filter nach Untertitel / Audio:</label>
                    <select id="aic-filter-sub" class="aic-select">
                        <option value="all">Alle anzeigen</option>
                        <option value="has_sub">&#x1F3A4; Mit .vtt-Untertitel &amp; Transkript</option>
                        <option value="no_audio">&#x1F507; Stummfilm (Kein Audio)</option>
                        <option value="open">&#x23F3; Noch ohne Audio-Transkript (Nur Text gepr&uuml;ft)</option>
                        <option value="error">&#x26A0; MP4 fehlt / Fehler</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Suche (Movie-ID, H&auml;ndler-ID, Begriff im Text/Audio):</label>
                    <input type="text" id="aic-filter-search" class="aic-input" placeholder="z. B. 8613 oder KV oder PayPal..." style="width: 240px;" />
                </div>
                <div>
                    <button type="button" id="btn-aic-apply-filter" class="aic-btn-primary">Filtern</button>
                    <button type="button" id="btn-aic-reset-filter" class="aic-btn-secondary">Zur&uuml;cksetzen</button>
                </div>
            </div>
        </div>

        <table class="aic-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Movie-ID</th>
                    <th style="width: 90px;">H&auml;ndler-ID</th>
                    <th>Filmtitel &amp; Dauer</th>
                    <th style="width: 165px;">Audio / Untertitel</th>
                    <th style="width: 155px;">BRAM-Status</th>
                    <th>KI-Zusammenfassung / Befund</th>
                    <th style="width: 130px;">Gepr&uuml;ft am</th>
                    <th style="width: 120px;">Aktion</th>
                </tr>
            </thead>
            <tbody id="aic-tbody-history">
                <tr>
                    <td colspan="8" style="text-align: center; color: #666;">Noch keine Videos in der Analyse-Historie vorhanden.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function() {
    jQuery("#ai_compliance_tabs").tabs();

    var currentMovieId = 0;
    var liveBatchRunning = false;

    function escHtml(str) {
        if (str === null || str === undefined) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function renderComplianceBadge(status, score) {
        var s = score !== undefined ? " (" + score + "/100)" : "";
        if (status === "ok") {
            return "<span class=\"aic-badge\" style=\"background:#28a745;\">OK / Unbedenklich" + s + "</span>";
        } else if (status === "warning") {
            return "<span class=\"aic-badge\" style=\"background:#e0a800; color:#111;\">Gelb / Ohne KK" + s + "</span>";
        } else if (status === "violation") {
            return "<span class=\"aic-badge\" style=\"background:#dc3545;\">BRAM-Versto&szlig; (Rot)" + s + "</span>";
        }
        return "<span class=\"aic-badge\" style=\"background:#6c757d;\">Noch ungepr&uuml;ft</span>";
    }

    function updateStatsUI(stats, workerStatus, batchCfg) {
        if (!stats) return;
        jQuery("#aic-stat-total").text(stats.total_converted);
        jQuery("#aic-stat-subtitled").text(stats.total_subtitled);
        jQuery("#aic-stat-sub-pct").text(stats.pct_subtitles);
        jQuery("#aic-stat-sub-rem").text(stats.remaining_subtitles);
        jQuery("#aic-stat-checked").text(stats.total_text_checked);
        jQuery("#aic-stat-txt-pct").text(stats.pct_text);
        jQuery("#aic-stat-txt-rem").text(stats.remaining_text);
        jQuery("#aic-stat-ok").text(stats.cnt_ok);
        jQuery("#aic-stat-warn").text(stats.cnt_warn);
        jQuery("#aic-stat-viol").text(stats.cnt_viol);

        // Fortschrittsbalken in Tab 2
        jQuery("#aic-prog-sub-text").text(stats.sub_processed_total + " / " + stats.total_converted + " Videos (" + stats.pct_subtitles + "%)");
        jQuery("#aic-prog-sub-bar").css("width", stats.pct_subtitles + "%");
        jQuery("#aic-prog-sub-label").text(stats.pct_subtitles + "%");
        jQuery("#aic-det-sub-ok").text(stats.sub_created);
        jQuery("#aic-det-sub-noaudio").text(stats.sub_no_audio);
        jQuery("#aic-det-sub-err").text(stats.sub_error);
        jQuery("#aic-det-sub-rem").text(stats.remaining_subtitles);

        jQuery("#aic-prog-txt-text").text(stats.total_text_checked + " / " + stats.total_converted + " Videos (" + stats.pct_text + "%)");
        jQuery("#aic-prog-txt-bar").css("width", stats.pct_text + "%");
        jQuery("#aic-prog-txt-label").text(stats.pct_text + "%");

        if (workerStatus) {
            jQuery("#aic-worker-last-msg").text(workerStatus.last_message || "-");
            jQuery("#aic-worker-last-time").text(workerStatus.last_run_at || "-");
            if (workerStatus.state === "running") {
                jQuery("#aic-worker-live-badge").html("<span class=\"aic-badge\" style=\"background:#28a745;\">&#x2699; Verarbeitet Video #" + workerStatus.current_movie_id + "</span>");
            } else if (workerStatus.state === "waiting_for_ffmpeg") {
                jQuery("#aic-worker-live-badge").html("<span class=\"aic-badge\" style=\"background:#e0a800; color:#111;\">Wartet auf Video-Konvertierung</span>");
            } else {
                jQuery("#aic-worker-live-badge").html("<span class=\"aic-badge\" style=\"background:#1c94c4;\">Bereit (Gesamt-Durchl&auml;ufe: " + (workerStatus.total_processed_runs || 0) + ")</span>");
            }
        }

        if (batchCfg) {
            if (batchCfg.cron_enabled) {
                jQuery("#aic-env-cron").css("background", "#28a745").text("Hintergrund-Cronjob: Aktiv");
            } else {
                jQuery("#aic-env-cron").css("background", "#6c757d").text("Hintergrund-Cronjob: Pausiert");
            }
        }
    }

    function renderRuleCategories(parsed) {
        var cats = (parsed && parsed.categories) ? parsed.categories : [];
        if (!cats.length) {
            jQuery("#aic-tbody-rule-cats").html("<tr><td colspan=\"4\">Keine Kategorien gefunden.</td></tr>");
            return;
        }
        var html = "";
        for (var i = 0; i < cats.length; i++) {
            var c = cats[i];
            var badge = c.severity === "violation"
                ? "<span class=\"aic-badge\" style=\"background:#dc3545;\">Hard-Block (Rot)</span><br/><small>Basis-Score: " + escHtml(c.base_risk_score) + "</small>"
                : "<span class=\"aic-badge\" style=\"background:#e0a800; color:#111;\">Pr&uuml;fhinweis (Gelb)</span><br/><small>Basis-Score: " + escHtml(c.base_risk_score) + "</small>";
            var kws = c.keywords || [];
            var kwHtml = "";
            for (var k = 0; k < kws.length; k++) {
                kwHtml += "<span class=\"aic-kw-pill\">" + escHtml(kws[k]) + "</span>";
            }
            html += "<tr>"
                + "<td><strong>" + escHtml(c.name) + "</strong></td>"
                + "<td>" + badge + "</td>"
                + "<td>" + escHtml(c.description) + "</td>"
                + "<td>" + kwHtml + "</td>"
                + "</tr>";
        }
        jQuery("#aic-tbody-rule-cats").html(html);
    }

    function renderHistoryTable(rows) {
        jQuery("#aic-hist-count").text(rows ? rows.length : 0);
        if (!rows || !rows.length) {
            jQuery("#aic-tbody-history").html("<tr><td colspan=\"8\" style=\"text-align:center; color:#666;\">Keine Videos f&uuml;r die gew&auml;hlten Filterkriterien gefunden.</td></tr>");
            return;
        }
        var html = "";
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var subBadge = "";
            if (r.subtitle_status === 2) {
                subBadge = "<span class=\"aic-badge\" style=\"background:#1c94c4;\">Sprache: " + escHtml(r.detected_language.toUpperCase()) + " (" + r.whisper_duration_sec + "s)</span><br/><small><code>" + escHtml(r.subtitle_filename) + "</code></small>";
            } else if (r.subtitle_status === 3) {
                subBadge = "<span class=\"aic-badge\" style=\"background:#6c757d;\">Stummfilm (Kein Audio)</span>";
            } else if (r.subtitle_status === 4) {
                subBadge = "<span class=\"aic-badge\" style=\"background:#dc3545;\" title=\"" + escHtml(r.last_error) + "\">MP4 fehlt / Fehler</span>";
            } else {
                subBadge = "<span class=\"aic-badge\" style=\"background:#6c757d;\">Audio noch offen</span>";
            }
            html += "<tr>"
                + "<td><strong>#" + r.movie_id + "</strong></td>"
                + "<td>" + r.merchant_id + "</td>"
                + "<td><strong>" + escHtml(r.title) + "</strong><br/><small style=\"color:#666;\">Dauer: " + escHtml(r.playtime_fmt) + " Min.</small></td>"
                + "<td>" + subBadge + "</td>"
                + "<td>" + renderComplianceBadge(r.text_compliance, r.text_risk_score) + "</td>"
                + "<td style=\"font-size:12px;\">" + escHtml(r.text_summary || "-") + "</td>"
                + "<td style=\"font-size:12px;\">" + escHtml(r.updated_at) + "</td>"
                + "<td><button type=\"button\" class=\"aic-btn-secondary btn-aic-open-hist\" data-id=\"" + r.movie_id + "\">Im Labor &ouml;ffnen</button></td>"
                + "</tr>";
        }
        jQuery("#aic-tbody-history").html(html);
    }

    function initDashboard(autoLoadFirst) {
        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "GET",
            data: { action: "init_dashboard" },
            dataType: "json",
            success: function(res) {
                if (!res || res.status !== "ok") return;

                updateStatsUI(res.stats, res.worker_status, res.batch_config);

                // Batch-Konfiguration in Formularfelder laden
                if (res.batch_config) {
                    jQuery("#aic-cfg-cron-enabled").prop("checked", !!res.batch_config.cron_enabled);
                    jQuery("#aic-cfg-model").val(res.batch_config.whisper_model_pref || "small");
                    jQuery("#aic-cfg-audiolimit").val(String(res.batch_config.audio_limit_sec || 0));
                    jQuery("#aic-cfg-threads").val(String(res.batch_config.whisper_threads || 12));
                    jQuery("#aic-cfg-maxruns").val(String(res.batch_config.max_movies_per_run || 2));
                    jQuery("#aic-cfg-order").val(res.batch_config.order_direction || "DESC");
                    jQuery("#aic-cfg-pause-ffmpeg").prop("checked", !!res.batch_config.pause_on_ffmpeg_convert);
                    jQuery("#aic-cfg-sync-lang").prop("checked", !!res.batch_config.sync_empty_movie_lang);
                }

                // System-Badges
                if (res.env.ffmpeg_ready) {
                    jQuery("#aic-env-ffmpeg").css("background", "#28a745").text("FFmpeg: Bereit");
                } else {
                    jQuery("#aic-env-ffmpeg").css("background", "#e0a800").css("color", "#111").text("FFmpeg: Simulationsmodus (Lokal)");
                }
                if (res.env.whisper_ready) {
                    jQuery("#aic-env-whisper").css("background", "#28a745").text("whisper.cpp: Bereit");
                } else {
                    jQuery("#aic-env-whisper").css("background", "#1c94c4").text("whisper.cpp: Testlabor-Simulation bereit");
                }
                if (res.env.llm_ready) {
                    jQuery("#aic-env-llm").css("background", "#28a745").text("Lokale KI: " + res.env.llm_model_name);
                } else {
                    jQuery("#aic-env-llm").css("background", "#1c94c4").text("KI-Modus: BRAM-Regel-Engine aktiv (llama.cpp noch nicht gestartet)");
                }

                // Dropdown fuellen
                var opts = "<option value=\"\">-- Bitte Video aus den neuesten 25 Uploads w&auml;hlen --</option>";
                if (res.recent_movies && res.recent_movies.length) {
                    for (var i = 0; i < res.recent_movies.length; i++) {
                        var m = res.recent_movies[i];
                        var flag = m.text_compliance === "violation" ? " [ROT]" : (m.text_compliance === "warning" ? " [GELB]" : (m.text_compliance === "ok" ? " [OK]" : ""));
                        opts += "<option value=\"" + m.id + "\">#" + m.id + " (H&auml;ndler " + m.merchant_id + " | " + m.playtime_fmt + ") - " + escHtml(m.title) + flag + "</option>";
                    }
                }
                jQuery("#aic-select-recent").html(opts);

                // Regelwerk-Tab fuellen
                if (res.rules) {
                    jQuery("#aic-rules-md-editor").val(res.rules.system_prompt_md || "");
                    jQuery("#aic-rules-json-editor").val(res.rules.keyword_rules_json || "");
                    renderRuleCategories(res.rules.parsed_categories);
                }

                // Historie fuellen
                renderHistoryTable(res.history);

                // Beim ersten Start automatisch ein Video ins Testlabor laden
                if (autoLoadFirst && currentMovieId === 0 && res.recent_movies && res.recent_movies.length > 0) {
                    loadMovieIntoLab({ movie_id: res.recent_movies[0].id });
                }
            }
        });
    }

    function displayMovieInLab(m) {
        currentMovieId = m.id;
        jQuery("#aic-loaded-movie-wrap").show();
        jQuery("#aic-input-movie-id").val(m.id);
        jQuery("#aic-select-recent").val(String(m.id));

        jQuery("#aic-m-title-head").text(m.title);
        jQuery("#aic-m-id").text(m.id);
        jQuery("#aic-m-merchant").text(m.merchant_id);
        jQuery("#aic-m-duration").text(m.playtime_fmt + " (" + m.playtime_seconds + " Sek.)");
        jQuery("#aic-m-filepath").text(m.mp4_path);
        jQuery("#aic-m-check-link").attr("href", "' . ACP_URL . '/Film-pruefen/" + m.id);

        if (m.mp4_exists) {
            jQuery("#aic-m-mp4-status").html("<span class=\"aic-badge\" style=\"background:#28a745;\">Vorhanden (" + m.mp4_size_mb + " MB)</span>");
        } else {
            jQuery("#aic-m-mp4-status").html("<span class=\"aic-badge\" style=\"background:#1c94c4;\">Lokale DB-Ansicht (MP4 auf Live-Server)</span>");
        }

        if (m.is_online) {
            jQuery("#aic-m-online-status").html("<span class=\"aic-badge\" style=\"background:#28a745;\">Online</span>");
        } else {
            jQuery("#aic-m-online-status").html("<span class=\"aic-badge\" style=\"background:#6c757d;\">Status: " + escHtml(m.status) + " (Released: " + m.released + ")</span>");
        }

        jQuery("#aic-edit-title").val(m.title);
        jQuery("#aic-edit-desc").val(m.description);
        jQuery("#aic-m-meta-title").text(m.meta_title || "-");
        jQuery("#aic-m-meta-desc").text(m.meta_description || "-");

        // Schritt 1 Daten (Whisper)
        if (m.ai && m.ai.subtitle_status === 2) {
            jQuery("#aic-step1-badge-wrap").html("<span class=\"aic-badge\" style=\"background:#28a745;\">Transkript &amp; .vtt vorhanden (" + m.ai.whisper_duration_sec + " Sek.)</span>");
            jQuery("#aic-out-lang").text(m.ai.detected_language.toUpperCase() + " (" + m.ai.language_confidence + " % Sicherheit)");
            jQuery("#aic-out-subfile").text(m.ai.subtitle_filename);
            jQuery("#aic-edit-transcript").val(m.ai.spoken_transcript);
            jQuery("#aic-out-vtt").val(m.ai.vtt_preview);
        } else if (m.ai && m.ai.subtitle_status === 3) {
            jQuery("#aic-step1-badge-wrap").html("<span class=\"aic-badge\" style=\"background:#6c757d;\">Stummfilm (Keine Tonspur im Video)</span>");
            jQuery("#aic-out-lang").text("-");
            jQuery("#aic-out-subfile").text("-");
            jQuery("#aic-edit-transcript").val(m.ai.spoken_transcript);
            jQuery("#aic-out-vtt").val("");
        } else {
            jQuery("#aic-step1-badge-wrap").html("<span class=\"aic-badge\" style=\"background:#6c757d;\">Noch kein Untertitel / Audio-Transkript erstellt</span>");
            jQuery("#aic-out-lang").text("Noch nicht ermittelt");
            jQuery("#aic-out-subfile").text(m.ai ? m.ai.subtitle_filename : "-");
            jQuery("#aic-edit-transcript").val("");
            jQuery("#aic-out-vtt").val("");
        }

        // Schritt 2 Daten (BRAM-Report)
        if (m.ai && m.ai.text_check_status === 2) {
            renderComplianceReport({
                compliance_status: m.ai.text_compliance,
                risk_score: m.ai.text_risk_score,
                engine_used: m.ai.text_engine_used,
                summary_de: m.ai.text_summary,
                violations: m.ai.violations,
                duration_sec: m.ai.text_duration_sec
            });
        } else {
            jQuery("#aic-report-box").hide();
        }
    }

    function renderComplianceReport(rep) {
        jQuery("#aic-report-box").show();
        var banner = jQuery("#aic-report-banner");
        var statusTitle = jQuery("#aic-report-status-title");

        if (rep.compliance_status === "ok") {
            banner.css({ background: "#d4edda", border: "1px solid #c3e6cb", color: "#155724" });
            statusTitle.html("&#x2705; FREIGABE-EMPFEHLUNG: UNBEDENKLICH (KEINE BRAM-VERST&Ouml;SSE ERKANNT)");
        } else if (rep.compliance_status === "warning") {
            banner.css({ background: "#fff3cd", border: "1px solid #ffeeba", color: "#856404" });
            statusTitle.html("&#x26A0; PR&Uuml;FHINWEIS (GELB): NUR AUF PORTALEN OHNE KREDITKARTE ZUL&Auml;SSIG / MANUELLE SICHTUNG");
        } else {
            banner.css({ background: "#f8d7da", border: "1px solid #f5c6cb", color: "#721c24" });
            statusTitle.html("&#x1F6D1; STOPP (ROT): KRITISCHER VISA/MASTERCARD BRAM- ODER RECHTS-VERSTOSS ERKANNT!");
        }

        jQuery("#aic-report-summary").text(rep.summary_de || "");
        jQuery("#aic-report-score").text(rep.risk_score || 0);
        jQuery("#aic-report-engine").text((rep.engine_used || "BRAM-Engine") + " (" + (rep.duration_sec || 0) + " Sek.)");

        var viols = rep.violations || [];
        if (!viols.length) {
            jQuery("#aic-tbody-violations").html("<tr><td colspan=\"5\" style=\"text-align:center; color:#155724; font-weight:bold;\">&#x2714; Es wurden in keiner Textquelle (Titel, Beschreibung, SEO, gesprochenes Video-Audio) verbotene Begriffe oder Verst&ouml;&szlig;e gefunden.</td></tr>");
            return;
        }

        var html = "";
        for (var i = 0; i < viols.length; i++) {
            var v = viols[i];
            var sevBadge = v.severity === "violation"
                ? "<span class=\"aic-badge\" style=\"background:#dc3545;\">VERSTOSS (ROT)</span>"
                : "<span class=\"aic-badge\" style=\"background:#e0a800; color:#111;\">HINWEIS (GELB)</span>";
            var tsInfo = (v.timestamp && v.timestamp !== "-") ? "<br/><strong>Zeit im Video: <code>" + escHtml(v.timestamp) + "</code></strong>" : "";
            html += "<tr>"
                + "<td>" + sevBadge + "</td>"
                + "<td><strong>" + escHtml(v.category) + "</strong></td>"
                + "<td>" + escHtml(v.source_label || v.source) + tsInfo + "</td>"
                + "<td><code style=\"color:#c00000; font-weight:bold; font-size:13px;\">" + escHtml(v.matched_term || "-") + "</code></td>"
                + "<td><div style=\"background:#fff8f8; border-left:3px solid #dc3545; padding:4px 8px; margin-bottom:4px; font-style:italic;\">&bdquo;" + escHtml(v.matched_quote || "") + "&ldquo;</div>"
                + "<small style=\"color:#555;\">" + escHtml(v.reason || "") + "</small></td>"
                + "</tr>";
        }
        jQuery("#aic-tbody-violations").html(html);
    }

    function loadMovieIntoLab(params) {
        params.action = "load_movie";
        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "GET",
            data: params,
            dataType: "json",
            success: function(res) {
                if (!res || res.status !== "ok") {
                    alert(res && res.message ? res.message : "Fehler beim Laden des Videos.");
                    return;
                }
                displayMovieInLab(res.movie);
            }
        });
    }

    function applyHistoryFilter() {
        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "GET",
            data: {
                action: "filter_history",
                compliance_filter: jQuery("#aic-filter-comp").val(),
                subtitle_filter: jQuery("#aic-filter-sub").val(),
                search_query: jQuery("#aic-filter-search").val()
            },
            dataType: "json",
            success: function(res) {
                if (!res || res.status !== "ok") return;
                renderHistoryTable(res.history);
                updateStatsUI(res.stats, null, null);
            }
        });
    }

    function appendBatchLog(line) {
        var box = jQuery("#aic-live-batch-log");
        var now = new Date().toLocaleTimeString("de-DE");
        var prev = box.val();
        box.val((prev ? prev + "\n" : "") + "[" + now + "] " + line);
        box.scrollTop(box[0].scrollHeight);
    }

    var batchStepTimer = null;
    var batchStepElapsedSec = 0;
    var batchAnnouncedMovieId = 0;

    function stopBatchStepPoller() {
        if (batchStepTimer) {
            clearInterval(batchStepTimer);
            batchStepTimer = null;
        }
        jQuery("#aic-live-batch-ticker").text("");
    }

    function executeNextLiveBatchStep(autoContinue) {
        if (autoContinue && !liveBatchRunning) {
            stopBatchStepPoller();
            appendBatchLog("Live-Batch wurde vom Administrator pausiert.");
            jQuery("#btn-aic-start-live-batch").show().prop("disabled", false);
            jQuery("#btn-aic-stop-live-batch").hide();
            jQuery("#btn-aic-step-live-batch").prop("disabled", false);
            return;
        }

        appendBatchLog("Starte nächstes offenes Video (Whisper Audio-Extraktion + .vtt + BRAM-Check)...");
        jQuery("#btn-aic-step-live-batch").prop("disabled", true);

        batchStepElapsedSec = 0;
        batchAnnouncedMovieId = 0;
        stopBatchStepPoller();
        jQuery("#aic-live-batch-ticker").text("⏳ Whisper läuft (0 Sek.)...");

        batchStepTimer = setInterval(function() {
            batchStepElapsedSec += 2;
            jQuery.ajax({
                url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
                type: "GET",
                data: { action: "poll_worker_status" },
                dataType: "json",
                success: function(pRes) {
                    if (pRes && pRes.status === "ok" && pRes.current_movie && pRes.current_movie.id) {
                        var cm = pRes.current_movie;
                        jQuery("#aic-live-batch-ticker").text("⏳ Verarbeite #" + cm.id + " (Dauer: " + cm.playtime_fmt + " Min.) – läuft seit " + batchStepElapsedSec + " Sek.");
                        if (batchAnnouncedMovieId !== cm.id) {
                            batchAnnouncedMovieId = cm.id;
                            appendBatchLog("   ↳ Aktuell in Arbeit: Video #" + cm.id + " \"" + cm.title + "\" (Spieldauer: " + cm.playtime_fmt + " Min.)...");
                        }
                    } else {
                        jQuery("#aic-live-batch-ticker").text("⏳ Whisper läuft (" + batchStepElapsedSec + " Sek.)...");
                    }
                }
            });
        }, 2000);

        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "POST",
            data: { action: "run_batch_step" },
            dataType: "json",
            success: function(res) {
                stopBatchStepPoller();
                if (!res || res.status !== "ok") {
                    appendBatchLog("FEHLER: " + (res && res.message ? res.message : "Unbekannter Fehler."));
                    liveBatchRunning = false;
                    jQuery("#btn-aic-start-live-batch").show().prop("disabled", false);
                    jQuery("#btn-aic-stop-live-batch").hide();
                    jQuery("#btn-aic-step-live-batch").prop("disabled", false);
                    return;
                }

                appendBatchLog("✔ " + res.message);
                updateStatsUI(res.stats, res.worker_status, null);

                if (!res.has_more) {
                    appendBatchLog("FERTIG! Alle konvertierten Videos wurden verarbeitet.");
                    liveBatchRunning = false;
                    jQuery("#btn-aic-start-live-batch").show().prop("disabled", false);
                    jQuery("#btn-aic-stop-live-batch").hide();
                    jQuery("#btn-aic-step-live-batch").prop("disabled", false);
                    applyHistoryFilter();
                    return;
                }

                if (autoContinue && liveBatchRunning) {
                    setTimeout(function() {
                        executeNextLiveBatchStep(true);
                    }, 600);
                } else {
                    jQuery("#btn-aic-step-live-batch").prop("disabled", false);
                    applyHistoryFilter();
                }
            },
            error: function() {
                stopBatchStepPoller();
                appendBatchLog("Netzwerk- oder Timeout-Fehler beim Batch-Schritt. Pausiere.");
                liveBatchRunning = false;
                jQuery("#btn-aic-start-live-batch").show().prop("disabled", false);
                jQuery("#btn-aic-stop-live-batch").hide();
                jQuery("#btn-aic-step-live-batch").prop("disabled", false);
            }
        });
    }

    function runBulkPrecheckLoop(forceRecheck, lastId, totalProcessedSoFar) {
        jQuery("#btn-aic-bulk-precheck, #btn-aic-bulk-recheck-all").prop("disabled", true);
        jQuery("#aic-bulk-precheck-status").text("Prüfe Katalog-Paket... (" + totalProcessedSoFar + " Filme geprüft)");

        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "POST",
            data: {
                action: "run_bulk_text_precheck",
                chunk_size: 250,
                force_recheck: forceRecheck ? 1 : 0,
                last_id: lastId || 0
            },
            dataType: "json",
            success: function(res) {
                if (!res || res.status !== "ok") {
                    jQuery("#btn-aic-bulk-precheck, #btn-aic-bulk-recheck-all").prop("disabled", false);
                    jQuery("#aic-bulk-precheck-status").css("color", "#dc3545").text("Fehler beim Schnell-Scan.");
                    return;
                }
                var newTotal = totalProcessedSoFar + (res.processed_in_chunk || 0);
                updateStatsUI(res.stats, null, null);

                if (res.has_more && res.processed_in_chunk > 0) {
                    runBulkPrecheckLoop(forceRecheck, res.last_id, newTotal);
                } else {
                    jQuery("#btn-aic-bulk-precheck, #btn-aic-bulk-recheck-all").prop("disabled", false);
                    jQuery("#aic-bulk-precheck-status").css("color", "#155724").text("✔ Katalog-Schnellscan abgeschlossen! (" + newTotal + " Videos in diesem Durchlauf bewertet)");
                    applyHistoryFilter();
                }
            },
            error: function() {
                jQuery("#btn-aic-bulk-precheck, #btn-aic-bulk-recheck-all").prop("disabled", false);
                jQuery("#aic-bulk-precheck-status").css("color", "#dc3545").text("Netzwerkfehler beim Schnell-Scan.");
            }
        });
    }

    // Event-Handler Tab 1
    jQuery("#aic-select-recent").on("change", function() {
        var val = jQuery(this).val();
        if (val) {
            loadMovieIntoLab({ movie_id: val });
        }
    });

    jQuery("#btn-aic-load-movie").on("click", function() {
        var mid = jQuery("#aic-input-movie-id").val();
        var merch = jQuery("#aic-input-merchant-id").val();
        loadMovieIntoLab({ movie_id: mid, merchant_id: merch });
    });

    jQuery("#btn-aic-load-untested").on("click", function() {
        var merch = jQuery("#aic-input-merchant-id").val();
        loadMovieIntoLab({ mode: "next_untested", merchant_id: merch });
    });

    jQuery("#btn-aic-find-interesting").on("click", function() {
        loadMovieIntoLab({ mode: "find_interesting" });
    });

    jQuery("#btn-aic-reload-dash").on("click", function() {
        initDashboard(false);
    });

    jQuery(document).on("click", ".btn-aic-open-hist", function() {
        var mid = jQuery(this).data("id");
        jQuery("#ai_compliance_tabs").tabs("option", "active", 0);
        loadMovieIntoLab({ movie_id: mid });
    });

    // Event-Handler Tab 2 (Batch & Cronjob)
    jQuery("#btn-aic-bulk-precheck").on("click", function() {
        runBulkPrecheckLoop(false, 0, 0);
    });

    jQuery("#btn-aic-bulk-recheck-all").on("click", function() {
        runBulkPrecheckLoop(true, 0, 0);
    });

    jQuery("#btn-aic-start-live-batch").on("click", function() {
        liveBatchRunning = true;
        jQuery(this).hide();
        jQuery("#btn-aic-stop-live-batch").show();
        appendBatchLog("=== LIVE-BATCH AUTO-LOOP GESTARTET ===");
        executeNextLiveBatchStep(true);
    });

    jQuery("#btn-aic-stop-live-batch").on("click", function() {
        liveBatchRunning = false;
        appendBatchLog("Stopp angefordert – warte auf Abschluss des aktuell laufenden Videos...");
    });

    jQuery("#btn-aic-step-live-batch").on("click", function() {
        liveBatchRunning = false;
        executeNextLiveBatchStep(false);
    });

    jQuery("#btn-aic-save-batch-cfg").on("click", function() {
        var btn = jQuery(this);
        btn.prop("disabled", true);
        jQuery("#aic-save-batch-msg").css("color", "#555").text("Speichere...");

        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "POST",
            data: {
                action: "save_batch_config",
                cron_enabled: jQuery("#aic-cfg-cron-enabled").is(":checked") ? 1 : 0,
                whisper_model_pref: jQuery("#aic-cfg-model").val(),
                audio_limit_sec: jQuery("#aic-cfg-audiolimit").val(),
                whisper_threads: jQuery("#aic-cfg-threads").val(),
                max_movies_per_run: jQuery("#aic-cfg-maxruns").val(),
                order_direction: jQuery("#aic-cfg-order").val(),
                pause_on_ffmpeg_convert: jQuery("#aic-cfg-pause-ffmpeg").is(":checked") ? 1 : 0,
                sync_empty_movie_lang: jQuery("#aic-cfg-sync-lang").is(":checked") ? 1 : 0
            },
            dataType: "json",
            success: function(res) {
                btn.prop("disabled", false);
                if (!res || res.status !== "ok") {
                    jQuery("#aic-save-batch-msg").css("color", "#dc3545").text("Fehler beim Speichern.");
                    return;
                }
                jQuery("#aic-save-batch-msg").css("color", "#28a745").text(res.message);
                updateStatsUI(null, null, res.batch_config);
            },
            error: function() {
                btn.prop("disabled", false);
                jQuery("#aic-save-batch-msg").css("color", "#dc3545").text("Netzwerkfehler.");
            }
        });
    });

    // Event-Handler Tab 4 (Filter in Analyse-Historie)
    jQuery("#btn-aic-apply-filter").on("click", function() {
        applyHistoryFilter();
    });
    jQuery("#aic-filter-comp, #aic-filter-sub").on("change", function() {
        applyHistoryFilter();
    });
    jQuery("#aic-filter-search").on("keypress", function(e) {
        if (e.which === 13) {
            e.preventDefault();
            applyHistoryFilter();
        }
    });
    jQuery("#btn-aic-reset-filter").on("click", function() {
        jQuery("#aic-filter-comp").val("all");
        jQuery("#aic-filter-sub").val("all");
        jQuery("#aic-filter-search").val("");
        applyHistoryFilter();
    });

    // Schritt 1: Whisper starten
    jQuery("#btn-aic-run-whisper").on("click", function() {
        if (!currentMovieId) return;
        var btn = jQuery(this);
        var scenario = jQuery("#aic-sim-scenario").val();
        var audioLimit = jQuery("#aic-audio-limit").val();
        btn.prop("disabled", true).text("Audio & Untertitel werden verarbeitet...");
        jQuery("#aic-whisper-timer").text("Bitte warten (Whisper transkribiert echtes Audio)...");

        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "POST",
            data: {
                action: "run_whisper",
                movie_id: currentMovieId,
                sim_scenario: scenario,
                audio_limit_sec: audioLimit
            },
            dataType: "json",
            success: function(res) {
                btn.prop("disabled", false).html("&#x1F3A4; 1. Audio jetzt auswerten &amp; Untertitel erzeugen");
                if (!res || res.status !== "ok") {
                    alert(res && res.message ? res.message : "Fehler bei der Audio-Auswertung.");
                    return;
                }
                jQuery("#aic-whisper-timer").html("<strong style=\"color:#28a745;\">&#x2714; Abgeschlossen (" + escHtml(res.mode_used) + ")</strong>");
                displayMovieInLab(res.movie);
                initDashboard(false);
            },
            error: function() {
                btn.prop("disabled", false).html("&#x1F3A4; 1. Audio jetzt auswerten &amp; Untertitel erzeugen");
                alert("Netzwerkfehler bei Schritt 1.");
            }
        });
    });

    // Schritt 2: BRAM Text- & Transkript-Check starten
    jQuery("#btn-aic-run-textcheck").on("click", function() {
        if (!currentMovieId) return;
        var btn = jQuery(this);
        btn.prop("disabled", true).text("Prüfe Texte & Audio-Transkript...");

        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "POST",
            data: {
                action: "run_text_check",
                movie_id: currentMovieId,
                custom_title: jQuery("#aic-edit-title").val(),
                custom_desc: jQuery("#aic-edit-desc").val(),
                custom_transcript: jQuery("#aic-edit-transcript").val()
            },
            dataType: "json",
            success: function(res) {
                btn.prop("disabled", false).html("&#x1F6E1; 2. Jetzt BRAM- &amp; Rechts-Pr&uuml;fung starten");
                if (!res || res.status !== "ok") {
                    alert(res && res.message ? res.message : "Fehler bei der BRAM-Prüfung.");
                    return;
                }
                renderComplianceReport(res.report);
                initDashboard(false);
            },
            error: function() {
                btn.prop("disabled", false).html("&#x1F6E1; 2. Jetzt BRAM- &amp; Rechts-Pr&uuml;fung starten");
                alert("Netzwerkfehler bei Schritt 2.");
            }
        });
    });

    // Tab 3: Regelwerk speichern
    jQuery("#btn-aic-save-rules").on("click", function() {
        var btn = jQuery(this);
        btn.prop("disabled", true);
        jQuery("#aic-save-rules-msg").css("color", "#555").text("Speichere Regelwerk...");

        jQuery.ajax({
            url: "' . ACP_URL . '/includes/ajax/ai_compliance_action.php",
            type: "POST",
            data: {
                action: "save_rules",
                system_prompt_md: jQuery("#aic-rules-md-editor").val(),
                keyword_rules_json: jQuery("#aic-rules-json-editor").val()
            },
            dataType: "json",
            success: function(res) {
                btn.prop("disabled", false);
                if (!res || res.status !== "ok") {
                    jQuery("#aic-save-rules-msg").css("color", "#dc3545").text(res && res.message ? res.message : "Fehler beim Speichern.");
                    return;
                }
                jQuery("#aic-save-rules-msg").css("color", "#28a745").text(res.message);
                renderRuleCategories(res.parsed_categories);
            },
            error: function() {
                btn.prop("disabled", false);
                jQuery("#aic-save-rules-msg").css("color", "#dc3545").text("Netzwerkfehler beim Speichern.");
            }
        });
    });

    // Initialer Start
    initDashboard(true);
});
</script>
';
?>
