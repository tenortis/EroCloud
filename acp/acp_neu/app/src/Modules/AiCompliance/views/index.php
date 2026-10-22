<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, mixed> $env
 * @var array<string, mixed> $stats
 * @var array<string, mixed> $batchConfig
 * @var array<string, mixed> $workerStatus
 * @var array<int, array<string, mixed>> $recentMovies
 * @var array<int, array<string, mixed>> $historyRows
 * @var array<string, mixed> $rulesData
 * @var array<string, mixed>|null $initialMovie
 * @var array<string, int> $navCounts
 */

$initMovieJson = json_encode($initialMovie, JSON_UNESCAPED_UNICODE);
$ruleCats      = $rulesData['parsed_categories']['categories'] ?? [];
?>

<!-- Content-Prüfung Schnellwechsel-Leiste -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="#/movies-checking" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-film me-1"></i>Filme prüfen
            <span class="badge bg-secondary ms-1"><?= (int)($navCounts['movies_checking'] ?? 0) ?></span>
        </a>
        <a href="#/photo-albums-checking" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-images me-1"></i>Fotoalben prüfen
            <span class="badge bg-secondary ms-1"><?= (int)($navCounts['albums_checking'] ?? 0) ?></span>
        </a>
        <a href="#/co-actors-checking" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person-badge me-1"></i>Drehpartner &amp; ID-Check
            <span class="badge bg-secondary ms-1"><?= (int)($navCounts['co_actors_checking'] ?? 0) ?></span>
        </a>
        <a href="#/ai-compliance" class="btn btn-sm btn-primary fw-semibold">
            <i class="bi bi-robot me-1"></i>KI-Analyse &amp; BRAM
            <span class="badge bg-danger ms-1"><?= (int)($stats['cnt_viol'] ?? 0) ?></span>
        </a>
    </div>

    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-aic-reload-dash" onclick="App.AiCompliance.reloadDashboard()">
            <i class="bi bi-arrow-clockwise me-1"></i>Daten &amp; Status aktualisieren
        </button>
    </div>
</div>

<!-- 4 KPI-Karten -->
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Konvertierte Videos</div>
                        <div class="fs-3 fw-bold text-primary mt-1" id="aic-stat-total"><?= (int)($stats['total_converted'] ?? 0) ?></div>
                        <div class="small text-muted">Bereit im Katalog (convert_status = 2)</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-camera-reels fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Audio &amp; Untertitel (Whisper)</div>
                        <div class="fs-3 fw-bold text-info mt-1" id="aic-stat-subtitled"><?= (int)($stats['total_subtitled'] ?? 0) ?></div>
                        <div class="small text-muted">
                            <span id="aic-stat-sub-pct"><?= View::e((string)($stats['pct_subtitles'] ?? '0')) ?></span>% verarbeitet &bull;
                            Offen: <strong id="aic-stat-sub-rem"><?= (int)($stats['remaining_subtitles'] ?? 0) ?></strong>
                        </div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-badge-cc fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">BRAM- &amp; Text-Compliance</div>
                        <div class="fs-3 fw-bold text-success mt-1" id="aic-stat-checked"><?= (int)($stats['total_text_checked'] ?? 0) ?></div>
                        <div class="small text-muted">
                            <span id="aic-stat-txt-pct"><?= View::e((string)($stats['pct_text'] ?? '0')) ?></span>% geprüft &bull;
                            Offen: <strong id="aic-stat-txt-rem"><?= (int)($stats['remaining_text'] ?? 0) ?></strong>
                        </div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small fw-semibold text-uppercase mb-2">Compliance-Bilanz (Klick filtert Historie)</div>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-success py-1 px-2" onclick="App.AiCompliance.jumpToHistoryFilter('ok')" title="Alle unbedenklichen Videos anzeigen">
                        Grün / OK: <strong id="aic-stat-ok"><?= (int)($stats['cnt_ok'] ?? 0) ?></strong>
                    </button>
                    <button type="button" class="btn btn-sm btn-warning text-dark py-1 px-2" onclick="App.AiCompliance.jumpToHistoryFilter('warning')" title="Alle Videos mit Prüfhinweis (ohne KK) anzeigen">
                        Gelb / Ohne KK: <strong id="aic-stat-warn"><?= (int)($stats['cnt_warn'] ?? 0) ?></strong>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger py-1 px-2" onclick="App.AiCompliance.jumpToHistoryFilter('violation')" title="Alle Videos mit BRAM-Hard-Block (Rot) anzeigen">
                        Rot / Verstoß: <strong id="aic-stat-viol"><?= (int)($stats['cnt_viol'] ?? 0) ?></strong>
                    </button>
                </div>
                <div class="small text-muted mt-1">Klicke auf eine Ampel für Details in Tab 4</div>
            </div>
        </div>
    </div>
</div>

<!-- System-Komponenten-Statusleiste -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2 small">
            <span class="fw-bold text-secondary"><i class="bi bi-cpu me-1"></i>System-Komponenten:</span>
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>BRAM-Regel-Engine: Aktiv</span>

            <?php if (!empty($env['ffmpeg_ready'])): ?>
                <span class="badge bg-success" id="aic-env-ffmpeg">FFmpeg: Bereit</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark" id="aic-env-ffmpeg">FFmpeg: Simulationsmodus (Lokal)</span>
            <?php endif; ?>

            <?php if (!empty($env['whisper_ready'])): ?>
                <span class="badge bg-success" id="aic-env-whisper">whisper.cpp: Bereit</span>
            <?php else: ?>
                <span class="badge bg-info text-dark" id="aic-env-whisper">whisper.cpp: Testlabor-Simulation bereit</span>
            <?php endif; ?>

            <?php if (!empty($env['llm_ready'])): ?>
                <span class="badge bg-success" id="aic-env-llm">Lokale KI: <?= View::e((string)($env['llm_model_name'] ?? 'Aktiv')) ?></span>
            <?php else: ?>
                <span class="badge bg-primary" id="aic-env-llm">KI-Modus: BRAM-Regel-Engine aktiv</span>
            <?php endif; ?>

            <?php if (!empty($batchConfig['cron_enabled'])): ?>
                <span class="badge bg-success" id="aic-env-cron">Hintergrund-Cronjob: Aktiv</span>
            <?php else: ?>
                <span class="badge bg-secondary" id="aic-env-cron">Hintergrund-Cronjob: Pausiert</span>
            <?php endif; ?>
        </div>
        <div class="small text-muted">
            Worker: <strong id="aic-worker-live-badge">
                <?php if (($workerStatus['state'] ?? '') === 'running'): ?>
                    <span class="badge bg-success">Verarbeitet Video #<?= (int)($workerStatus['current_movie_id'] ?? 0) ?></span>
                <?php elseif (($workerStatus['state'] ?? '') === 'waiting_for_ffmpeg'): ?>
                    <span class="badge bg-warning text-dark">Wartet auf Video-Konvertierung</span>
                <?php else: ?>
                    <span class="badge bg-primary">Bereit (Durchläufe: <?= (int)($workerStatus['total_processed_runs'] ?? 0) ?>)</span>
                <?php endif; ?>
            </strong>
        </div>
    </div>
</div>

<!-- 5-Tab Hauptnavigation -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3 pb-0 px-3">
        <ul class="nav nav-tabs card-header-tabs" id="aicMainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="tab-btn-aic-lab" data-bs-toggle="tab" data-bs-target="#tab-aic-lab" type="button" role="tab">
                    <i class="bi bi-sliders me-1"></i>1. Einzel-Video-Testlabor
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-aic-batch" data-bs-toggle="tab" data-bs-target="#tab-aic-batch" type="button" role="tab">
                    <i class="bi bi-gear-wide-connected me-1"></i>2. Batch- &amp; Cronjob-Steuerung
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-aic-rules" data-bs-toggle="tab" data-bs-target="#tab-aic-rules" type="button" role="tab">
                    <i class="bi bi-journal-code me-1"></i>3. Visa/Mastercard BRAM-Regelwerk &amp; Prompt
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-aic-history" data-bs-toggle="tab" data-bs-target="#tab-aic-history" type="button" role="tab">
                    <i class="bi bi-clock-history me-1"></i>4. Analyse-Historie &amp; Filter
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-aic-inspector" data-bs-toggle="tab" data-bs-target="#tab-aic-inspector" type="button" role="tab">
                    <i class="bi bi-search me-1"></i>5. Transkript- &amp; VTT-Inspektor
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-3 p-lg-4">
        <div class="tab-content" id="aicMainTabContent">

            <!-- ============================================================ -->
            <!-- TAB 1: EINZEL-VIDEO-TESTLABOR                                -->
            <!-- ============================================================ -->
            <div class="tab-pane fade show active" id="tab-aic-lab" role="tabpanel">
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-film me-2 text-primary"></i>Video für das Testlabor auswählen</h6>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-5">
                            <label class="form-label small fw-semibold mb-1">Schnellauswahl (neueste 25 konvertierte Uploads):</label>
                            <select class="form-select form-select-sm" id="aic-select-recent" onchange="App.AiCompliance.onSelectRecent(this.value)">
                                <option value="">-- Bitte Video aus den neuesten 25 Uploads wählen --</option>
                                <?php foreach ($recentMovies as $rm):
                                    $flag = $rm['text_compliance'] === 'violation' ? ' [ROT]' : ($rm['text_compliance'] === 'warning' ? ' [GELB]' : ($rm['text_compliance'] === 'ok' ? ' [OK]' : ''));
                                    $sel = ($initialMovie && (int)$initialMovie['id'] === (int)$rm['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= (int)$rm['id'] ?>" <?= $sel ?>>
                                        #<?= (int)$rm['id'] ?> (Händler <?= (int)$rm['merchant_id'] ?> | <?= View::e((string)$rm['playtime_fmt']) ?>) - <?= View::e((string)$rm['title']) ?><?= $flag ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Direkte Film-ID:</label>
                            <input type="number" class="form-control form-control-sm" id="aic-input-movie-id" placeholder="z. B. 101869" value="<?= $initialMovie ? (int)$initialMovie['id'] : '' ?>">
                        </div>
                        <div class="col-6 col-sm-3 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Händler-ID (optional):</label>
                            <input type="number" class="form-control form-control-sm" id="aic-input-merchant-id" placeholder="z. B. 11108">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-primary flex-grow-1" onclick="App.AiCompliance.loadMovieByInput()">
                                <i class="bi bi-box-arrow-in-down me-1"></i>Video laden
                            </button>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="App.AiCompliance.loadMovieMode('next_untested')">
                            <i class="bi bi-skip-forward me-1"></i>Nächstes Video ohne Untertitel laden
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="App.AiCompliance.loadMovieMode('find_interesting')">
                            <i class="bi bi-exclamation-Diamond me-1"></i>Video mit potenziellem BRAM-Treffer aus Bestand suchen
                        </button>
                    </div>
                </div>

                <!-- Geladenes Video -->
                <div id="aic-loaded-movie-wrap" style="<?= $initialMovie ? '' : 'display:none;' ?>">
                    <div class="row g-3 mb-4">
                        <!-- Linke Spalte: Metadaten -->
                        <div class="col-12 col-lg-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small text-uppercase text-secondary">Geladenes Video im Testlabor</span>
                                    <div class="d-flex gap-1">
                                        <a href="#/movie-checking?id=<?= $initialMovie ? (int)$initialMovie['id'] : 0 ?>" id="aic-m-check-link" class="btn btn-xs btn-outline-primary py-0 px-2 small">
                                            <i class="bi bi-shield-check me-1"></i>Film-Prüfung
                                        </a>
                                        <a href="#/movie-edit?id=<?= $initialMovie ? (int)$initialMovie['id'] : 0 ?>" id="aic-m-edit-link" class="btn btn-xs btn-outline-secondary py-0 px-2 small">
                                            <i class="bi bi-pencil-square me-1"></i>Film bearbeiten
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="fw-bold text-primary mb-3" id="aic-m-title-head"><?= View::e((string)($initialMovie['title'] ?? '-')) ?></h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless mb-0 small">
                                            <tbody>
                                                <tr>
                                                    <th class="text-muted" style="width:140px;">Film-ID:</th>
                                                    <td><strong id="aic-m-id"><?= (int)($initialMovie['id'] ?? 0) ?></strong></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-muted">Händler-ID:</th>
                                                    <td><strong id="aic-m-merchant"><?= (int)($initialMovie['merchant_id'] ?? 0) ?></strong></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-muted">Spieldauer:</th>
                                                    <td id="aic-m-duration"><?= View::e((string)($initialMovie['playtime_fmt'] ?? '00:00')) ?> (<?= (int)($initialMovie['playtime_seconds'] ?? 0) ?> Sek.)</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-muted">MP4-Datei:</th>
                                                    <td id="aic-m-mp4-status">
                                                        <?php if (!empty($initialMovie['mp4_exists'])): ?>
                                                            <span class="badge bg-success">Vorhanden (<?= View::e((string)($initialMovie['mp4_size_mb'] ?? '0')) ?> MB)</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info text-dark">Lokale DB-Ansicht (MP4 auf Live-Server)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-muted">Plattform-Status:</th>
                                                    <td id="aic-m-online-status">
                                                        <?php if (!empty($initialMovie['is_online'])): ?>
                                                            <span class="badge bg-success">Online</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Status: <?= View::e((string)($initialMovie['status'] ?? '-')) ?> (Released: <?= (int)($initialMovie['released'] ?? 0) ?>)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-muted">Server-Pfad:</th>
                                                    <td><code class="small text-break" id="aic-m-filepath"><?= View::e((string)($initialMovie['mp4_path'] ?? '-')) ?></code></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rechte Spalte: Editierbare Textfelder für Live-Simulation -->
                        <div class="col-12 col-lg-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light py-2">
                                    <span class="fw-bold small text-uppercase text-secondary">Text-Quellen (für Live-Tests frei editierbar)</span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1">Filmtitel (Test-Eingabe):</label>
                                        <input type="text" class="form-control form-control-sm" id="aic-edit-title" value="<?= View::e((string)($initialMovie['title'] ?? '')) ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1">Filmbeschreibung (Test-Eingabe):</label>
                                        <textarea class="form-control form-control-sm" id="aic-edit-desc" rows="4"><?= View::e((string)($initialMovie['description'] ?? '')) ?></textarea>
                                    </div>
                                    <div class="small text-muted bg-light p-2 rounded border">
                                        <div><strong>SEO Meta-Title:</strong> <span id="aic-m-meta-title"><?= View::e((string)($initialMovie['meta_title'] ?: '-')) ?></span></div>
                                        <div class="mt-1"><strong>SEO Meta-Description:</strong> <span id="aic-m-meta-desc"><?= View::e((string)($initialMovie['meta_description'] ?: '-')) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SCHRITT 1: WHISPER AUDIO & VTT -->
                    <div class="card border mb-4">
                        <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="fw-bold"><i class="bi bi-mic-fill text-primary me-1"></i>Schritt 1: Audio-Analyse &amp; automatische Untertitel (.vtt) via Whisper</span>
                            <div id="aic-step1-badge-wrap">
                                <?php
                                $aiSubStatus = (int)($initialMovie['ai']['subtitle_status'] ?? 0);
                                if ($aiSubStatus === 2): ?>
                                    <span class="badge bg-success">Transkript &amp; .vtt vorhanden (<?= View::e((string)($initialMovie['ai']['whisper_duration_sec'] ?? 0)) ?> Sek.)</span>
                                <?php elseif ($aiSubStatus === 3): ?>
                                    <span class="badge bg-secondary">Stummfilm (Keine Tonspur im Video)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Noch kein Untertitel / Audio-Transkript erstellt</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small fw-semibold mb-1">Modus / Simulations-Szenario:</label>
                                    <select class="form-select form-select-sm" id="aic-sim-scenario">
                                        <option value="clean">Standard: Echtes Video-Audio auswerten (bzw. saubere Vorschau lokal)</option>
                                        <option value="bram_violation">Simulation: Gesprochener BRAM-Verstoß im Audio (Inzest / K.O.-Tropfen / Telegram)</option>
                                        <option value="bram_warning">Simulation: Gesprochener Prüfhinweis / Gelb (Stiefmutter / Fesseln / KV)</option>
                                        <option value="english_clean">Simulation: Englisches Video (Spracherkennung EN + englische .vtt)</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Audio-Limit (Testlauf):</label>
                                    <select class="form-select form-select-sm" id="aic-audio-limit">
                                        <option value="120">Erste 2 Minuten (Schnelltest)</option>
                                        <option value="300">Erste 5 Minuten</option>
                                        <option value="0">Komplettes Video (Volle Länge)</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4 d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" id="btn-aic-run-whisper" onclick="App.AiCompliance.runWhisper()">
                                        <i class="bi bi-mic me-1"></i>1. Audio jetzt auswerten &amp; Untertitel erzeugen
                                    </button>
                                    <span id="aic-whisper-timer" class="small text-muted"></span>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                                        <span class="fw-semibold">Erkanntes Audio-Transkript mit Zeitstempeln (für Schritt 2 editierbar):</span>
                                        <span>Erkannte Sprache: <strong id="aic-out-lang" class="text-primary">
                                            <?= $aiSubStatus === 2 ? View::e(strtoupper((string)($initialMovie['ai']['detected_language'] ?? '')) . ' (' . ($initialMovie['ai']['language_confidence'] ?? 0) . ' %)') : 'Noch nicht ermittelt' ?>
                                        </strong></span>
                                    </div>
                                    <textarea class="form-control form-control-sm font-monospace" id="aic-edit-transcript" rows="6" placeholder="Hier erscheint nach Schritt 1 der gesprochene Text aus dem Video. Du kannst auch manuell Sätze eintragen, um das BRAM-Regelwerk in Schritt 2 live zu testen..."><?= View::e((string)($initialMovie['ai']['spoken_transcript'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                                        <span class="fw-semibold">Generierte WebVTT-Untertiteldatei (für Video-Player):</span>
                                        <span>Zieldatei: <code id="aic-out-subfile"><?= View::e((string)($initialMovie['ai']['subtitle_filename'] ?? '-')) ?></code></span>
                                    </div>
                                    <textarea class="form-control form-control-sm font-monospace bg-dark text-success" id="aic-out-vtt" rows="6" readonly placeholder="WEBVTT Vorschau..."><?= View::e((string)($initialMovie['ai']['vtt_preview'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SCHRITT 2: BRAM & RECHTS-PRÜFUNG -->
                    <div class="card border">
                        <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="fw-bold"><i class="bi bi-shield-exclamation text-danger me-1"></i>Schritt 2: Visa/Mastercard BRAM- &amp; Rechts-Compliance-Prüfung</span>
                            <button type="button" class="btn btn-sm btn-success fw-semibold" id="btn-aic-run-textcheck" onclick="App.AiCompliance.runTextCheck()">
                                <i class="bi bi-shield-check me-1"></i>2. Jetzt BRAM- &amp; Rechts-Prüfung starten (Titel + Beschreibung + Audio)
                            </button>
                        </div>
                        <div class="card-body">
                            <?php
                            $hasReport = ((int)($initialMovie['ai']['text_check_status'] ?? 0) === 2);
                            $repComp   = (string)($initialMovie['ai']['text_compliance'] ?? 'ok');
                            $repScore  = (int)($initialMovie['ai']['text_risk_score'] ?? 0);
                            $repEngine = (string)($initialMovie['ai']['text_engine_used'] ?? 'BRAM-Engine');
                            $repDur    = (float)($initialMovie['ai']['text_duration_sec'] ?? 0);
                            $repSum    = (string)($initialMovie['ai']['text_summary'] ?? '');
                            $repViols  = $initialMovie['ai']['violations'] ?? [];
                            ?>
                            <div id="aic-report-box" style="<?= $hasReport ? '' : 'display:none;' ?>">
                                <div id="aic-report-banner" class="alert <?= $repComp === 'ok' ? 'alert-success' : ($repComp === 'warning' ? 'alert-warning' : 'alert-danger') ?> d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                    <div>
                                        <div class="fw-bold fs-6" id="aic-report-status-title">
                                            <?php if ($repComp === 'ok'): ?>
                                                &#x2705; FREIGABE-EMPFEHLUNG: UNBEDENKLICH (KEINE BRAM-VERSTÖSSE ERKANNT)
                                            <?php elseif ($repComp === 'warning'): ?>
                                                &#x26A0; PRÜFHINWEIS (GELB): NUR AUF PORTALEN OHNE KREDITKARTE ZULÄSSIG / MANUELLE SICHTUNG
                                            <?php else: ?>
                                                &#x1F6D1; STOPP (ROT): KRITISCHER VISA/MASTERCARD BRAM- ODER RECHTS-VERSTOSS ERKANNT!
                                            <?php endif; ?>
                                        </div>
                                        <div class="small mt-1" id="aic-report-summary"><?= View::e($repSum) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small">Risiko-Score: <strong class="fs-5" id="aic-report-score"><?= $repScore ?></strong> / 100</div>
                                        <div class="small opacity-75">Engine: <span id="aic-report-engine"><?= View::e($repEngine) ?> (<?= $repDur ?> Sek.)</span></div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:140px;">Einstufung</th>
                                                <th style="width:220px;">Kategorie</th>
                                                <th style="width:180px;">Fundort &amp; Zeitstempel</th>
                                                <th style="width:160px;">Erkannter Begriff</th>
                                                <th>Original-Zitat im Kontext &amp; Erläuterung</th>
                                            </tr>
                                        </thead>
                                        <tbody id="aic-tbody-violations">
                                            <?php if (empty($repViols)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-success fw-semibold py-3">
                                                        &#x2714; Es wurden in keiner Textquelle (Titel, Beschreibung, SEO, gesprochenes Video-Audio) verbotene Begriffe oder Verstöße gefunden.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($repViols as $v): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if (($v['severity'] ?? '') === 'violation'): ?>
                                                                <span class="badge bg-danger">VERSTOSS (ROT)</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">HINWEIS (GELB)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><strong><?= View::e((string)($v['category'] ?? '')) ?></strong></td>
                                                        <td>
                                                            <?= View::e((string)($v['source_label'] ?? $v['source'] ?? '')) ?>
                                                            <?php if (!empty($v['timestamp']) && $v['timestamp'] !== '-'): ?>
                                                                <br><strong>Zeit im Video: <code><?= View::e((string)$v['timestamp']) ?></code></strong>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><code class="text-danger fw-bold"><?= View::e((string)($v['matched_term'] ?? '-')) ?></code></td>
                                                        <td>
                                                            <div class="bg-danger bg-opacity-10 border-start border-danger border-3 px-2 py-1 fst-italic mb-1">
                                                                &bdquo;<?= View::e((string)($v['matched_quote'] ?? '')) ?>&ldquo;
                                                            </div>
                                                            <small class="text-muted"><?= View::e((string)($v['reason'] ?? '')) ?></small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: BATCH- & CRONJOB-STEUERUNG                            -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-aic-batch" role="tabpanel">
                <!-- Fortschritts-Übersicht -->
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Gesamt-Fortschritt der Bestands-Verarbeitung (alle konvertierten Videos)</h6>
                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <div class="d-flex justify-content-between small fw-semibold mb-1">
                                <span>Stufe 1: Whisper Audio-Transkription &amp; .vtt-Untertitel</span>
                                <span id="aic-prog-sub-text"><?= (int)($stats['sub_processed_total'] ?? 0) ?> / <?= (int)($stats['total_converted'] ?? 0) ?> Videos (<?= View::e((string)($stats['pct_subtitles'] ?? '0')) ?>%)</span>
                            </div>
                            <div class="progress" style="height:20px;">
                                <div class="progress-bar bg-info" id="aic-prog-sub-bar" role="progressbar" style="width:<?= (float)($stats['pct_subtitles'] ?? 0) ?>%;">
                                    <span id="aic-prog-sub-label"><?= View::e((string)($stats['pct_subtitles'] ?? '0')) ?>%</span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
                                <span>&#x2705; .vtt erstellt: <strong id="aic-det-sub-ok"><?= (int)($stats['sub_created'] ?? 0) ?></strong></span>
                                <span>&#x1F507; Stummfilm / Kein Audio: <strong id="aic-det-sub-noaudio"><?= (int)($stats['sub_no_audio'] ?? 0) ?></strong></span>
                                <span>&#x26A0; MP4 fehlt / Fehler: <strong id="aic-det-sub-err"><?= (int)($stats['sub_error'] ?? 0) ?></strong></span>
                                <span>&#x23F3; Noch offen: <strong id="aic-det-sub-rem"><?= (int)($stats['remaining_subtitles'] ?? 0) ?></strong></span>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="d-flex justify-content-between small fw-semibold mb-1">
                                <span>Stufe 2: BRAM-Text- &amp; Transkript-Compliance</span>
                                <span id="aic-prog-txt-text"><?= (int)($stats['total_text_checked'] ?? 0) ?> / <?= (int)($stats['total_converted'] ?? 0) ?> Videos (<?= View::e((string)($stats['pct_text'] ?? '0')) ?>%)</span>
                            </div>
                            <div class="progress" style="height:20px;">
                                <div class="progress-bar bg-success" id="aic-prog-txt-bar" role="progressbar" style="width:<?= (float)($stats['pct_text'] ?? 0) ?>%;">
                                    <span id="aic-prog-txt-label"><?= View::e((string)($stats['pct_text'] ?? '0')) ?>%</span>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                Letzte Worker-Meldung: <strong id="aic-worker-last-msg"><?= View::e((string)($workerStatus['last_message'] ?? '-')) ?></strong>
                                (<span id="aic-worker-last-time"><?= View::e((string)($workerStatus['last_run_at'] ?? '-')) ?></span>)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schnell-Scan -->
                <div class="alert alert-warning border mb-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-lightning-charge-fill me-1"></i>Schnell-Scan: Gesamten Video-Katalog vorab auf Text-Verstöße prüfen (ohne auf Whisper zu warten)</h6>
                    <p class="small mb-2">
                        Da die Audio-Transkription aller Videos per Whisper im Hintergrund mehrere Tage dauert, kannst du hier <strong>sofort alle Filme anhand von Titel, Beschreibung, SEO-Texten und bereits vorhandenen Transkripten</strong> in Paketen à 250 Filmen gegen dein aktuelles BRAM-Regelwerk prüfen lassen.
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="btn-aic-bulk-precheck" onclick="App.AiCompliance.runBulkPrecheck(false)">
                            <i class="bi bi-lightning me-1"></i>Alle noch offenen Filme jetzt per Schnell-Scan prüfen
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="btn-aic-bulk-recheck-all" onclick="App.AiCompliance.runBulkPrecheck(true)">
                            <i class="bi bi-arrow-repeat me-1"></i>Gesamten Katalog komplett neu bewerten (z. B. nach Regelwerks-Änderung)
                        </button>
                        <span id="aic-bulk-precheck-status" class="small fw-bold"></span>
                    </div>
                </div>

                <!-- Modus 1 & Modus 2 nebeneinander -->
                <div class="row g-3">
                    <!-- Modus 1: Live-Whisper-Batch im Browser -->
                    <div class="col-12 col-lg-6">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2">
                                <span class="fw-bold"><i class="bi bi-play-circle text-success me-1"></i>Modus 1: Live-Whisper-Batch direkt im Browser ausführen</span>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted">
                                    Ideal zum direkten Zuschauen und Testen: Der Browser ruft nacheinander jeweils das nächste noch offene Video auf, führt auf dem Server FFmpeg + <code>whisper.cpp</code> + BRAM-Check aus und zeigt dir das Live-Protokoll an.
                                </p>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <button type="button" class="btn btn-sm btn-success" id="btn-aic-start-live-batch" onclick="App.AiCompliance.startLiveBatch()">
                                        <i class="bi bi-play-fill me-1"></i>Live-Batch starten (Auto-Loop)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" id="btn-aic-stop-live-batch" style="display:none;" onclick="App.AiCompliance.stopLiveBatch()">
                                        <i class="bi bi-stop-fill me-1"></i>Nach aktuellem Video stoppen
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-aic-step-live-batch" onclick="App.AiCompliance.stepLiveBatch()">
                                        <i class="bi bi-skip-end-fill me-1"></i>Nur 1 nächstes Video verarbeiten
                                    </button>
                                    <span id="aic-live-batch-ticker" class="small fw-bold text-primary"></span>
                                </div>
                                <label class="form-label small fw-semibold mb-1">Live-Protokoll:</label>
                                <textarea class="form-control form-control-sm font-monospace bg-dark text-light" id="aic-live-batch-log" rows="9" readonly placeholder="Bereit. Klicke auf 'Nur 1 nächstes Video verarbeiten' oder 'Live-Batch starten'..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Modus 2: Hintergrund-Cronjob -->
                    <div class="col-12 col-lg-6">
                        <div class="card border h-100">
                            <div class="card-header bg-light py-2">
                                <span class="fw-bold"><i class="bi bi-clock-history text-primary me-1"></i>Modus 2: Automatischer Hintergrund-Cronjob (24/7 Server-Worker)</span>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted">
                                    Der Server-Worker (<code>cronjobs/ai_compliance_worker.php</code>) arbeitet im Hintergrund alle vorhandenen und neuen Videos ab, ohne die normale Video-Konvertierung zu stören.
                                </p>

                                <div class="form-check form-switch bg-light border rounded p-2 ps-5 mb-3">
                                    <input class="form-check-input" type="checkbox" id="aic-cfg-cron-enabled" <?= !empty($batchConfig['cron_enabled']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold small text-success" for="aic-cfg-cron-enabled">
                                        Hintergrund-Cronjob aktivieren (automatische Abarbeitung)
                                    </label>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small fw-semibold mb-1">Whisper-Sprachmodell:</label>
                                        <select class="form-select form-select-sm" id="aic-cfg-model">
                                            <option value="small" <?= ($batchConfig['whisper_model_pref'] ?? 'small') === 'small' ? 'selected' : '' ?>>Multilingual Small (Schnell, ~10x Echtzeit)</option>
                                            <option value="turbo" <?= ($batchConfig['whisper_model_pref'] ?? '') === 'turbo' ? 'selected' : '' ?>>Large-v3-Turbo (Maximale Präzision)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small fw-semibold mb-1">Audio-Limit pro Video:</label>
                                        <select class="form-select form-select-sm" id="aic-cfg-audiolimit">
                                            <?php $al = (int)($batchConfig['audio_limit_sec'] ?? 0); ?>
                                            <option value="0" <?= $al === 0 ? 'selected' : '' ?>>Komplettes Video (100% Untertitel)</option>
                                            <option value="300" <?= $al === 300 ? 'selected' : '' ?>>Erste 5 Minuten (300 Sek.)</option>
                                            <option value="180" <?= $al === 180 ? 'selected' : '' ?>>Erste 3 Minuten (180 Sek.)</option>
                                            <option value="120" <?= $al === 120 ? 'selected' : '' ?>>Erste 2 Minuten (120 Sek.)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small fw-semibold mb-1">CPU-Threads:</label>
                                        <select class="form-select form-select-sm" id="aic-cfg-threads">
                                            <?php $th = (int)($batchConfig['whisper_threads'] ?? 12); ?>
                                            <option value="8" <?= $th === 8 ? 'selected' : '' ?>>8 Threads (Schonend)</option>
                                            <option value="12" <?= $th === 12 ? 'selected' : '' ?>>12 Threads (Empfohlen)</option>
                                            <option value="16" <?= $th === 16 ? 'selected' : '' ?>>16 Threads (Schnell)</option>
                                            <option value="20" <?= $th === 20 ? 'selected' : '' ?>>20 Threads (Maximal)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small fw-semibold mb-1">Videos pro Lauf:</label>
                                        <select class="form-select form-select-sm" id="aic-cfg-maxruns">
                                            <?php $mr = (int)($batchConfig['max_movies_per_run'] ?? 2); ?>
                                            <option value="1" <?= $mr === 1 ? 'selected' : '' ?>>1 Video pro Aufruf</option>
                                            <option value="2" <?= $mr === 2 ? 'selected' : '' ?>>2 Videos pro Aufruf</option>
                                            <option value="3" <?= $mr === 3 ? 'selected' : '' ?>>3 Videos pro Aufruf</option>
                                            <option value="5" <?= $mr === 5 ? 'selected' : '' ?>>5 Videos pro Aufruf</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small fw-semibold mb-1">Reihenfolge:</label>
                                        <select class="form-select form-select-sm" id="aic-cfg-order">
                                            <?php $od = strtoupper((string)($batchConfig['order_direction'] ?? 'DESC')); ?>
                                            <option value="DESC" <?= $od === 'DESC' ? 'selected' : '' ?>>Neueste zuerst (DESC)</option>
                                            <option value="ASC" <?= $od === 'ASC' ? 'selected' : '' ?>>Älteste zuerst (ASC)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-check mb-1 small">
                                    <input class="form-check-input" type="checkbox" id="aic-cfg-pause-ffmpeg" <?= !empty($batchConfig['pause_on_ffmpeg_convert']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aic-cfg-pause-ffmpeg">
                                        Automatisch pausieren, solange ein neuer Creator-Upload durch FFmpeg konvertiert wird (Vorrang für Uploads)
                                    </label>
                                </div>
                                <div class="form-check mb-3 small">
                                    <input class="form-check-input" type="checkbox" id="aic-cfg-sync-lang" <?= !empty($batchConfig['sync_empty_movie_lang']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aic-cfg-sync-lang">
                                        Erkannte Sprache (DE / EN / ES / FR / IT) automatisch in <code>movies.movie_language</code> eintragen, falls noch leer
                                    </label>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" id="btn-aic-save-batch-cfg" onclick="App.AiCompliance.saveBatchConfig()">
                                        <i class="bi bi-floppy me-1"></i>Cronjob-Konfiguration speichern
                                    </button>
                                    <span id="aic-save-batch-msg" class="small fw-bold"></span>
                                </div>

                                <div class="mt-3 p-2 bg-light border rounded small text-muted">
                                    <strong>Server-Cronjob Befehl (alle 2 Minuten):</strong><br>
                                    <code>*/2 * * * * /opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php &gt;/dev/null 2&gt;&amp;1</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: BRAM-REGELWERK & SYSTEM-PROMPT                        -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-aic-rules" role="tabpanel">
                <div class="alert alert-info small mb-3">
                    <strong>Zentrales Visa/Mastercard BRAM- &amp; Rechts-Regelwerk:</strong>
                    Alle hier hinterlegten Regeln, Kategorien und Wortlisten werden bei jeder Prüfung (Einzel-Testlabor, Schnell-Scan, Live-Batch und Hintergrund-Cronjob) sofort live angewendet.
                </div>

                <h6 class="fw-bold mb-2">Aktive Prüfkategorien &amp; Schlagwörter-Übersicht</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th style="width:220px;">Kategorie</th>
                                <th style="width:150px;">Einstufung &amp; Score</th>
                                <th style="width:280px;">Bedeutung &amp; Richtlinie</th>
                                <th>Überwachte Schlüsselwörter / Phrasen (Auszug)</th>
                            </tr>
                        </thead>
                        <tbody id="aic-tbody-rule-cats">
                            <?php if (empty($ruleCats)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Keine Kategorien gefunden.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ruleCats as $c): ?>
                                    <tr>
                                        <td><strong><?= View::e((string)($c['name'] ?? '')) ?></strong></td>
                                        <td>
                                            <?php if (($c['severity'] ?? '') === 'violation'): ?>
                                                <span class="badge bg-danger">Hard-Block (Rot)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Prüfhinweis (Gelb)</span>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-1">Basis-Score: <?= (int)($c['base_risk_score'] ?? 0) ?></div>
                                        </td>
                                        <td><?= View::e((string)($c['description'] ?? '')) ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach (($c['keywords'] ?? []) as $kw): ?>
                                                    <span class="badge bg-light text-dark border fw-normal"><?= View::e((string)$kw) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label small fw-bold">1. KI-System-Prompt &amp; Richtlinien (<code>includes/ai_rules/bram_system_prompt.md</code>):</label>
                        <textarea class="form-control form-control-sm font-monospace" id="aic-rules-md-editor" rows="16"><?= View::e((string)($rulesData['system_prompt_md'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label small fw-bold">2. Deterministische BRAM-Wortliste &amp; Kategorien (<code>includes/ai_rules/bram_keyword_rules.json</code>):</label>
                        <textarea class="form-control form-control-sm font-monospace" id="aic-rules-json-editor" rows="16"><?= View::e((string)($rulesData['keyword_rules_json'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="mt-3 d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-success fw-semibold" id="btn-aic-save-rules" onclick="App.AiCompliance.saveRules()">
                        <i class="bi bi-floppy me-1"></i>Regelwerk &amp; System-Prompt jetzt speichern
                    </button>
                    <span id="aic-save-rules-msg" class="small fw-bold"></span>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 4: ANALYSE-HISTORIE & FILTER                             -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-aic-history" role="tabpanel">
                <div class="bg-light border rounded-3 p-3 mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Filter nach BRAM-Status:</label>
                            <select class="form-select form-select-sm" id="aic-filter-comp" onchange="App.AiCompliance.applyHistoryFilter()">
                                <option value="all">Alle Status anzeigen</option>
                                <option value="violation">&#x1F6D1; Nur BRAM-Verstöße (Rot / Hard-Block)</option>
                                <option value="warning">&#x26A0; Nur Prüfhinweise (Gelb / Ohne KK)</option>
                                <option value="ok">&#x2705; Nur Unbedenklich (Grün / OK)</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Filter nach Untertitel-Status:</label>
                            <select class="form-select form-select-sm" id="aic-filter-sub" onchange="App.AiCompliance.applyHistoryFilter()">
                                <option value="all">Alle</option>
                                <option value="has_sub">Mit .vtt-Untertitel (Whisper fertig)</option>
                                <option value="no_audio">Stummfilm (Kein Audio)</option>
                                <option value="open">Audio noch offen</option>
                                <option value="error">MP4 fehlt / Fehler</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-8 col-md-4">
                            <label class="form-label small fw-semibold mb-1">Suche (Film-ID, Händler-ID oder Begriff in Text/Audio):</label>
                            <input type="text" class="form-control form-control-sm" id="aic-filter-search" placeholder="z. B. 101869 oder Suchbegriff..." onkeydown="if(event.key==='Enter'){event.preventDefault();App.AiCompliance.applyHistoryFilter();}">
                        </div>
                        <div class="col-12 col-sm-4 col-md-2 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-primary flex-grow-1" onclick="App.AiCompliance.applyHistoryFilter()">
                                <i class="bi bi-funnel me-1"></i>Filtern
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="App.AiCompliance.resetHistoryFilter()" title="Filter zurücksetzen">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        Zeige <strong id="aic-hist-count"><?= count($historyRows) ?></strong> Einträge (max. 200 pro Ansicht)
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:85px;">Film-ID</th>
                                <th style="width:85px;">Händler</th>
                                <th>Filmtitel &amp; Dauer</th>
                                <th style="width:190px;">Audio / .vtt-Status</th>
                                <th style="width:175px;">BRAM-Ampel &amp; Score</th>
                                <th>Zusammenfassung / Befund</th>
                                <th style="width:135px;">Geprüft am</th>
                                <th style="width:130px;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody id="aic-tbody-history">
                            <?php if (empty($historyRows)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Keine Videos für die gewählten Filterkriterien gefunden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyRows as $r): ?>
                                    <tr>
                                        <td><strong>#<?= (int)$r['movie_id'] ?></strong></td>
                                        <td><?= (int)$r['merchant_id'] ?></td>
                                        <td>
                                            <strong><?= View::e((string)$r['title']) ?></strong><br>
                                            <small class="text-muted">Dauer: <?= View::e((string)$r['playtime_fmt']) ?> Min.</small>
                                        </td>
                                        <td>
                                            <?php if ((int)$r['subtitle_status'] === 2): ?>
                                                <span class="badge bg-info text-dark">Sprache: <?= View::e(strtoupper((string)$r['detected_language'])) ?> (<?= (float)$r['whisper_duration_sec'] ?>s)</span><br>
                                                <small><code><?= View::e((string)$r['subtitle_filename']) ?></code></small>
                                            <?php elseif ((int)$r['subtitle_status'] === 3): ?>
                                                <span class="badge bg-secondary">Stummfilm (Kein Audio)</span>
                                            <?php elseif ((int)$r['subtitle_status'] === 4): ?>
                                                <span class="badge bg-danger" title="<?= View::e((string)$r['last_error']) ?>">MP4 fehlt / Fehler</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Audio noch offen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['text_compliance'] === 'ok'): ?>
                                                <span class="badge bg-success">OK / Unbedenklich (<?= (int)$r['text_risk_score'] ?>/100)</span>
                                            <?php elseif ($r['text_compliance'] === 'warning'): ?>
                                                <span class="badge bg-warning text-dark">Gelb / Ohne KK (<?= (int)$r['text_risk_score'] ?>/100)</span>
                                            <?php elseif ($r['text_compliance'] === 'violation'): ?>
                                                <span class="badge bg-danger">BRAM-Verstoß (<?= (int)$r['text_risk_score'] ?>/100)</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Noch ungeprüft</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= View::e((string)($r['text_summary'] ?: '-')) ?></td>
                                        <td><?= View::e((string)$r['updated_at']) ?></td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 small" onclick="App.AiCompliance.openMovieInLab(<?= (int)$r['movie_id'] ?>)">
                                                    Im Labor öffnen
                                                </button>
                                                <a href="#/movie-edit?id=<?= (int)$r['movie_id'] ?>" class="btn btn-xs btn-outline-secondary py-0 px-2 small">
                                                    Film bearbeiten
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 5: TRANSKRIPT- & VTT-INSPEKTOR                           -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-aic-inspector" role="tabpanel">
                <div class="bg-light border rounded-3 p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="fw-bold mb-1"><i class="bi bi-filetype-aac text-primary me-1"></i>Gesamtauswertung aller erzeugten Whisper-Transkripte &amp; .vtt-Untertitel</h6>
                            <p class="small text-muted mb-0">
                                Untersucht sämtliche bereits transkribierten Filme in <code>movies_ai_analysis</code> auf <strong>Klammer-Ausdrücke</strong> (z. B. <code>[Musik]</code>, <code>[Stöhnen]</code>), <strong>erkannte Sprachen</strong>, <strong>reine Geräusch-Transkripte ohne echten Dialog</strong> sowie <strong>potenzielle Whisper-Halluzinationen</strong>.
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="btn-aic-run-inspector" onclick="App.AiCompliance.runInspector()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Transkript-Analyse starten / aktualisieren
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-aic-copy-inspector" style="display:none;" onclick="App.AiCompliance.copyInspectorJson()">
                                <i class="bi bi-clipboard me-1"></i>Komplett-Auswertung kopieren
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-aic-download-inspector" style="display:none;" onclick="App.AiCompliance.downloadInspectorJson()">
                                <i class="bi bi-download me-1"></i>Als JSON herunterladen
                            </button>
                        </div>
                    </div>

                    <div class="border-top mt-3 pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small">
                            <strong class="text-success">&#x2728; Rückwirkende Bestands-Bereinigung &amp; Whisper-Neu-Einreihung:</strong><br>
                            <span class="text-muted">Bereinigt alle bereits erzeugten Transkripte &amp; <code>.vtt</code>-Dateien rückwirkend (entfernt Halluzinationen/Wiederholungsschleifen, vereinheitlicht Regieanweisungen auf Deutsch und korrigiert exotische Sprachcodes).</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-success" id="btn-aic-clean-all-vtt" onclick="App.AiCompliance.cleanAllVtt()">
                                <i class="bi bi-magic me-1"></i>1. Alle vorhandenen Transkripte &amp; .vtt rückwirkend bereinigen
                            </button>
                            <select class="form-select form-select-sm" id="aic-requeue-exotic-mode" style="width:auto;">
                                <option value="foreign_speech_only">Nur exotische Filme mit Fremdsprachen-Text (KO/JA/JW/LA/ZH)</option>
                                <option value="all_exotic">Alle ehemals exotischen Filme (inkl. NN/NO)</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold" id="btn-aic-requeue-exotic" onclick="App.AiCompliance.requeueExotic()">
                                <i class="bi bi-arrow-repeat me-1"></i>2. Betroffene Filme für Whisper-Neu-Transkription einreihen
                            </button>
                        </div>
                    </div>

                    <div id="aic-insp-export-msg" class="alert alert-success py-2 px-3 mt-2 mb-0 small" style="display:none;"></div>
                    <div id="aic-insp-clean-msg" class="alert py-2 px-3 mt-2 mb-0 small" style="display:none;"></div>
                </div>

                <!-- 6 Inspector-KPI-Boxen -->
                <div class="row g-2 mb-4">
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 bg-white text-center">
                            <div class="small text-muted">Transkribierte Videos (.vtt)</div>
                            <div class="fs-4 fw-bold text-primary" id="aic-insp-total-sub">-</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 bg-white text-center">
                            <div class="small text-muted">Stummfilme (Kein Audio)</div>
                            <div class="fs-4 fw-bold text-secondary" id="aic-insp-total-silent">-</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 bg-white text-center">
                            <div class="small text-muted">Mit echtem Dialog</div>
                            <div class="fs-4 fw-bold text-success" id="aic-insp-real-speech">-</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 bg-white text-center">
                            <div class="small text-muted">Nur Geräusche / Klammern</div>
                            <div class="fs-4 fw-bold text-warning" id="aic-insp-only-brackets">-</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 bg-white text-center">
                            <div class="small text-muted">Verschiedene Klammer-Tags</div>
                            <div class="fs-4 fw-bold text-info" id="aic-insp-unique-brackets">-</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 bg-white text-center">
                            <div class="small text-muted">Klammer-Treffer gesamt</div>
                            <div class="fs-4 fw-bold text-danger" id="aic-insp-occ-brackets">-</div>
                        </div>
                    </div>
                </div>

                <!-- 1. Klammer-Ausdrücke -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-xl-7">
                        <h6 class="fw-bold mb-2">1. Gefundene Klammer- &amp; Geräusch-Ausdrücke in <code>.vtt</code> (<code>[...]</code>, <code>(...)</code>, <code>*...*</code>)</h6>
                        <div class="table-responsive border rounded" style="max-height:420px; overflow-y:auto;">
                            <table class="table table-sm table-bordered align-middle small mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Klammer-Ausdruck / Tag</th>
                                        <th style="width:85px;">Vorkommen</th>
                                        <th style="width:85px;">Filme</th>
                                        <th style="width:190px;">Einschätzung / Typ</th>
                                        <th>Beispiel-Filme (Klick öffnet Labor)</th>
                                    </tr>
                                </thead>
                                <tbody id="aic-tbody-insp-brackets">
                                    <tr><td colspan="5" class="text-center text-muted py-3">Klicke oben auf „Transkript-Analyse starten“, um alle vorhandenen Untertitel auszuwerten.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. Sprachen -->
                    <div class="col-12 col-xl-5">
                        <h6 class="fw-bold mb-2">2. Erkannte Sprachen &amp; Whisper-Sprachcode-Verteilung</h6>
                        <div class="table-responsive border rounded" style="max-height:420px; overflow-y:auto;">
                            <table class="table table-sm table-bordered align-middle small mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:65px;">Code</th>
                                        <th>Sprache / Hinweis</th>
                                        <th style="width:70px;">Filme</th>
                                        <th style="width:85px;">Mit Dialog</th>
                                        <th style="width:95px;">Nur Geräusche</th>
                                        <th>Beispiele</th>
                                    </tr>
                                </thead>
                                <tbody id="aic-tbody-insp-langs">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Noch nicht geladen.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. Filme ohne echten Dialog & 4. Wiederholte Kurz-Phrasen -->
                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <h6 class="fw-bold mb-2">3. Filme ohne echten gesprochenen Dialog (nur Geräusch-Tags / Musik / Stöhnen)</h6>
                        <div class="table-responsive border rounded" style="max-height:360px; overflow-y:auto;">
                            <table class="table table-sm table-bordered align-middle small mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:75px;">Film-ID</th>
                                        <th>Titel</th>
                                        <th style="width:65px;">Sprache</th>
                                        <th style="width:65px;">Segmente</th>
                                        <th>Inhalt in .vtt (Vorschau)</th>
                                        <th style="width:110px;">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody id="aic-tbody-insp-nodialog">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Noch nicht geladen.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <h6 class="fw-bold mb-2">4. Häufig wiederholte Kurz-Phrasen (Top-Sätze &amp; potenzielle Whisper-Schleifen)</h6>
                        <div class="table-responsive border rounded" style="max-height:360px; overflow-y:auto;">
                            <table class="table table-sm table-bordered align-middle small mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Gesprochener Satz / Phrase</th>
                                        <th style="width:85px;">Gesamt</th>
                                        <th style="width:85px;">In Filmen</th>
                                        <th>Beispiel-Filme</th>
                                    </tr>
                                </thead>
                                <tbody id="aic-tbody-insp-phrases">
                                    <tr><td colspan="4" class="text-center text-muted py-3">Noch nicht geladen.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
window.__aicInitialMovie = <?= $initMovieJson ?: 'null' ?>;
</script>
