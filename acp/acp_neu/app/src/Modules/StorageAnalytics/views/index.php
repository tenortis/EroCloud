<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, mixed> $analytics
 * @var array<string, int> $switcherCounts
 */

$sum       = $analytics['summary'] ?? [];
$disk      = $analytics['disk'] ?? [];
$cats      = $analytics['categories'] ?? [];
$formats   = $analytics['formats'] ?? [];
$statuses  = $analytics['statuses'] ?? [];
$traffic   = $analytics['traffic'] ?? [];
$providers = $analytics['providers'] ?? [];
$diskPct   = (float)($disk['used_percent'] ?? 0);
$diskBarCls = $diskPct >= 85 ? 'bg-danger' : ($diskPct >= 70 ? 'bg-warning' : 'bg-primary');
?>

<!-- Content-Online Schnellwechsel-Leiste -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="#/movies-online" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-film me-1"></i>Filme online
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_online'] ?? 0) ?></span>
        </a>
        <a href="#/movies-drafts" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-hourglass-split me-1"></i>Filme in Planung
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_drafts'] ?? 0) ?></span>
        </a>
        <a href="#/movies-blocked" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-slash-circle me-1"></i>Abgelehnt / Gesperrt
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['movies_blocked'] ?? 0) ?></span>
        </a>
        <a href="#/photo-albums-online" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-images me-1"></i>Fotoalben online
            <span class="badge bg-secondary ms-1"><?= (int)($switcherCounts['photo_albums_online'] ?? 0) ?></span>
        </a>
        <a href="#/content-cleanup" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-trash3 me-1"></i>Content-Bereinigung
        </a>
        <a href="#/storage-analytics" class="btn btn-sm btn-primary fw-semibold">
            <i class="bi bi-hdd-network me-1"></i>Speicher &amp; Traffic
        </a>
    </div>

    <div class="d-flex align-items-center gap-2 small">
        <span class="text-muted">
            Stand: <strong id="sa-meta-time"><?= View::e((string)($analytics['generated_at'] ?? '-')) ?></strong>
            <span id="sa-meta-cache">
                <?php if (!empty($analytics['from_cache'])): ?>
                    (aus 24h-Cache, vor <?= (int)($analytics['cache_age_minutes'] ?? 0) ?> Min.)
                <?php else: ?>
                    (Live berechnet in <?= (int)($analytics['duration_ms'] ?? 0) ?> ms)
                <?php endif; ?>
            </span>
        </span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-sa-refresh" onclick="App.StorageAnalytics.refreshAnalytics()">
            <i class="bi bi-arrow-clockwise me-1"></i>Jetzt live neu berechnen
        </button>
    </div>
</div>

<!-- Simulations-Hinweis lokal -->
<div id="sa-sim-notice" class="alert alert-warning small mb-3" style="<?= !empty($analytics['is_local_simulation']) ? '' : 'display:none;' ?>">
    <strong><i class="bi bi-info-circle-fill me-1"></i>Hinweis zur lokalen Entwicklungsumgebung:</strong>
    Auf diesem lokalen System wurden keine physischen Video-Ordner unter <code>cloud_storage/</code> gefunden. Alle angegebenen Video-Speichergrößen und Traffic-Mengen wurden daher automatisch anhand der exakten Spieldauer (<code>playtime_seconds</code>) aus der Datenbank und den konfigurierten FFmpeg-Bitraten berechnet. Auf dem Live-Server werden hier automatisch die exakten physischen Dateigrößen angezeigt.
</div>

<!-- 3-Tab Hauptkarte -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3 pb-0 px-3">
        <ul class="nav nav-tabs card-header-tabs" id="saMainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="tab-btn-sa-storage" data-bs-toggle="tab" data-bs-target="#tab-sa-storage" type="button" role="tab">
                    <i class="bi bi-hdd-stack me-1"></i>1. Speicherplatz &amp; Dateiformate
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-sa-traffic" data-bs-toggle="tab" data-bs-target="#tab-sa-traffic" type="button" role="tab">
                    <i class="bi bi-graph-up-arrow me-1"></i>2. Streaming- &amp; Download-Traffic (6 Monate)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-sa-cloud" data-bs-toggle="tab" data-bs-target="#tab-sa-cloud" type="button" role="tab">
                    <i class="bi bi-cloud-check me-1"></i>3. Konvertierungs-Check &amp; Cloud-Kosten
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-3 p-lg-4">
        <div class="tab-content" id="saMainTabContent">

            <!-- ============================================================ -->
            <!-- TAB 1: SPEICHERPLATZ & DATEIFORMATE                          -->
            <!-- ============================================================ -->
            <div class="tab-pane fade show active" id="tab-sa-storage" role="tabpanel">
                <!-- 4 KPI-Kacheln -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border h-100 bg-light">
                            <div class="card-body py-3">
                                <div class="text-muted small fw-semibold text-uppercase">Gesamter Cloud-Speicherbedarf</div>
                                <div class="fs-3 fw-bold text-primary mt-1" id="sa-card-total-cloud"><?= View::e((string)($sum['total_cloud_formatted'] ?? '-')) ?></div>
                                <div class="small text-muted">Alle Videos, Fotoalben, Banner &amp; Händler-Dateien</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border h-100 bg-light">
                            <div class="card-body py-3">
                                <div class="text-muted small fw-semibold text-uppercase">Davon Videos Gesamt</div>
                                <div class="fs-3 fw-bold text-info mt-1" id="sa-card-movies-total"><?= View::e((string)($sum['movies_total_formatted'] ?? '-')) ?></div>
                                <div class="small text-muted" id="sa-card-movies-sub"><?= (int)($analytics['total_db_movies'] ?? 0) ?> Filme in der Datenbank</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border border-danger border-opacity-25 h-100 bg-danger bg-opacity-10">
                            <div class="card-body py-3">
                                <div class="text-danger small fw-semibold text-uppercase">Sofortiges Einsparpotenzial (.webm/.ogv)</div>
                                <div class="fs-3 fw-bold text-danger mt-1" id="sa-card-legacy-savings"><?= View::e((string)($sum['legacy_savings_formatted'] ?? '-')) ?></div>
                                <div class="small text-muted">Inkl. Mobile-MP4: <strong id="sa-card-legacy-plus-m"><?= View::e((string)($sum['legacy_with_m_mp4_formatted'] ?? '-')) ?></strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border border-success border-opacity-25 h-100 bg-success bg-opacity-10">
                            <div class="card-body py-3">
                                <div class="text-success small fw-semibold text-uppercase">Speicherbedarf nach Bereinigung</div>
                                <div class="fs-3 fw-bold text-success mt-1" id="sa-card-cleaned-total"><?= View::e((string)($sum['cleaned_cloud_formatted'] ?? '-')) ?></div>
                                <div class="small text-muted">Verbleibendes Volumen nach Löschung von WebM &amp; OGV</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server-Festplattenauslastung -->
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center small mb-1">
                        <span><strong>Server-Partition (<code id="sa-disk-path"><?= View::e((string)($disk['path'] ?? '-')) ?></code>):</strong></span>
                        <span>
                            Belegt: <strong id="sa-disk-used"><?= View::e((string)($disk['used_formatted'] ?? '-')) ?></strong>
                            von <strong id="sa-disk-total"><?= View::e((string)($disk['total_formatted'] ?? '-')) ?></strong>
                            (Frei: <strong class="text-success" id="sa-disk-free"><?= View::e((string)($disk['free_formatted'] ?? '-')) ?></strong> |
                            <strong id="sa-disk-pct"><?= $diskPct ?>%</strong>)
                        </span>
                    </div>
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar <?= $diskBarCls ?>" id="sa-disk-bar" role="progressbar" style="width:<?= min(100, $diskPct) ?>%;"></div>
                    </div>
                </div>

                <!-- 1.1 Speicherverbrauch nach Systembereichen -->
                <h6 class="fw-bold mb-2">1.1 Speicherverbrauch nach Systembereichen</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle small mb-0" id="sa-table-categories">
                        <thead class="table-light">
                            <tr>
                                <th>Systembereich / Verzeichnis</th>
                                <th>Datenbank-Bestand</th>
                                <th>Physische Dateien</th>
                                <th>Physisch auf Server</th>
                                <th>Kalkulierte Größe (Bitrate)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cats as $c): ?>
                                <tr>
                                    <td><strong><?= View::e((string)$c['name']) ?></strong></td>
                                    <td><?= View::e((string)$c['db_info']) ?></td>
                                    <td><?= View::e((string)$c['files_count']) ?></td>
                                    <td><strong><?= View::e((string)$c['actual_fmt']) ?></strong></td>
                                    <td><?= View::e((string)$c['est_fmt']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 1.2 Aufschlüsselung der Video-Dateiformate -->
                <h6 class="fw-bold mb-2">1.2 Aufschlüsselung der Video-Dateiformate (Konvertierungs-Ergebnisse)</h6>
                <div class="alert alert-light border small mb-2">
                    Für bestehende Filme wurden historisch durch <code>cronjobs/convert_movie.php</code> jeweils <strong>4 Videodateien</strong> (Desktop-MP4, Mobile-MP4, Mobile-WebM, Mobile-OGV) sowie <strong>10 Vorschaubilder</strong> angelegt (für Neu-Uploads ist die Konvertierung der 3 Mobil-Formate bereits deaktiviert).
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle small mb-0" id="sa-table-formats">
                        <thead class="table-light">
                            <tr>
                                <th>Dateityp / Muster</th>
                                <th>Technische Parameter (FFmpeg)</th>
                                <th>System-Nutzung</th>
                                <th>Anzahl Dateien</th>
                                <th>Speicherplatz (Live)</th>
                                <th>Speicherplatz (Kalkuliert)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($formats as $f): ?>
                                <tr>
                                    <td><strong><?= View::e((string)$f['type']) ?></strong></td>
                                    <td><?= View::e((string)$f['codec']) ?></td>
                                    <td><span class="badge" style="background:<?= View::e((string)$f['badge_color']) ?>;"><?= View::e((string)$f['usage_status']) ?></span></td>
                                    <td><?= (int)$f['count'] ?></td>
                                    <td><strong><?= View::e((string)$f['actual_fmt']) ?></strong></td>
                                    <td><?= View::e((string)$f['est_fmt']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 1.2b SICHERE BEREINIGUNG VERALTETER MOBIL-DATEIEN -->
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary bg-opacity-10 py-2">
                        <h6 class="fw-bold text-primary mb-0">
                            <i class="bi bi-shield-check me-1"></i>1.2b Sichere Bereinigung veralteter Mobil-Dateien (<code>m_*.mp4</code>, <code>m_*.webm</code>, <code>m_*.ogv</code>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            <strong>7-Stufen-Sicherheitsmodus (Einzel-Video-Prüfung &amp; Kontrollierte Stapel-Bereinigung):</strong>
                            Hier kannst du die nicht mehr benötigten Mobil-Dateien (<code>m_*.mp4</code>, <code>m_*.webm</code>, <code>m_*.ogv</code>) wahlweise für <strong>ein einzelnes Video</strong> oder im <strong>Stapel-Modus (10 / 50 / 100 Videos oder Auto-Durchlauf)</strong> bereinigen. Eine Datei wird nur gelöscht, wenn die Desktop-Hauptdatei (<code>{id}_{file_id}.mp4</code>) physisch existiert (&gt; 0 Bytes) und die zu löschende Datei eindeutig mit dem Präfix <code>m_</code> beginnt.
                        </p>

                        <!-- Such- & Filterleiste für Einzel-Video -->
                        <div class="bg-light border rounded p-3 mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-6 col-sm-3 col-lg-2">
                                    <label class="form-label small fw-semibold mb-1">Gezielte Film-ID:</label>
                                    <input type="number" class="form-control form-control-sm" id="sa-test-movie-id" placeholder="z. B. 21">
                                </div>
                                <div class="col-6 col-sm-3 col-lg-2">
                                    <label class="form-label small fw-semibold mb-1">Händler-ID (optional):</label>
                                    <input type="number" class="form-control form-control-sm" id="sa-test-merchant-id" placeholder="z. B. 5">
                                </div>
                                <div class="col-12 col-sm-6 col-lg-8 d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" id="btn-sa-load-preview" onclick="App.StorageAnalytics.loadSinglePreview(true)">
                                        <i class="bi bi-search me-1"></i>1 Test-Video in Vorschau laden
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-sa-next-preview" style="display:none;" onclick="App.StorageAnalytics.nextSinglePreview()">
                                        <i class="bi bi-arrow-right me-1"></i>Anderes Test-Video anzeigen
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="sa-cleanup-msg" class="alert small py-2 px-3 mb-3" style="display:none;"></div>
                        <div id="sa-cleanup-result-box" class="alert alert-success small p-3 mb-3" style="display:none;"></div>

                        <!-- Einzel-Video Vorschaukarte -->
                        <div id="sa-cleanup-preview-card" class="border rounded p-3 mb-3 bg-white" style="display:none;">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-2 mb-3">
                                <div>
                                    <div class="fw-bold">
                                        Test-Video (<span id="sa-prev-movie-id">-</span>): <span id="sa-prev-title" class="text-primary">-</span>
                                    </div>
                                    <div class="small text-muted">
                                        <strong>Online-ID:</strong> <span id="sa-prev-online-id">-</span> &bull;
                                        <strong>Ordner-ID:</strong> <span id="sa-prev-db-id">-</span> &bull;
                                        <strong>Händler:</strong> <span id="sa-prev-merchant-id">-</span> &bull;
                                        <strong>Darsteller:</strong> <span id="sa-prev-actor">-</span> &bull;
                                        <strong>File-ID:</strong> <code id="sa-prev-file-id">-</code> &bull;
                                        <strong>Auflösung:</strong> <span id="sa-prev-res">-</span> &bull;
                                        <strong>Länge:</strong> <span id="sa-prev-len">-</span>
                                    </div>
                                </div>
                                <span class="badge bg-primary">Vorschau für 1 einzelnes Video</span>
                            </div>

                            <div class="bg-light border rounded p-2 mb-3 small">
                                <strong>Physischer Verzeichnispfad auf dem Server:</strong><br>
                                <code id="sa-prev-folder-path" class="text-break">-</code>
                            </div>

                            <!-- Grüner Bereich: Geschützte Dateien -->
                            <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded p-2 mb-3">
                                <div class="fw-bold text-success small mb-2">
                                    &#x2705; Geschützte Dateien in diesem Ordner (bleiben zu 100 % unangetastet):
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white small mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rolle / Schutzstatus</th>
                                                <th>Dateiname</th>
                                                <th>Vollständiger Serverpfad</th>
                                                <th>Dateigröße</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sa-prev-protected-tbody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Roter Bereich: Zu löschende Mobil-Dateien -->
                            <div class="bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded p-2 mb-3">
                                <div class="fw-bold text-danger small mb-2">
                                    &#x1F5D1; Zur Löschung vorgemerkte veraltete Mobil-Dateien (<span id="sa-prev-del-count">0</span> Dateien | Freizugebender Speicher: <span id="sa-prev-del-size">0 Bytes</span>):
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white small mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Dateiname (Präfix <code>m_</code>)</th>
                                                <th>Format-Typ</th>
                                                <th>Vollständiger Serverpfad (wird per <code>unlink</code> entfernt)</th>
                                                <th>Dateigröße</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sa-prev-deletable-tbody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="sa-prev-action-bar" class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span class="small text-muted">
                                    <strong>Sicherheitshinweis:</strong> Beim Klick werden ausschließlich die oben in der roten Tabelle aufgelisteten <code>m_*</code>-Dateien dieses einen Videos gelöscht.
                                </span>
                                <button type="button" class="btn btn-sm btn-danger fw-semibold" id="btn-sa-delete-single" onclick="App.StorageAnalytics.deleteSingleMovie()">
                                    <i class="bi bi-trash3 me-1"></i>Jetzt NUR für dieses 1 Video (#<span id="sa-btn-movie-id">-</span>) die <span id="sa-btn-del-count">0</span> markierten m_*-Dateien löschen
                                </button>
                            </div>
                        </div>

                        <!-- Kontrollierte Stapel-Bereinigung -->
                        <div class="border-top pt-3 mt-2">
                            <h6 class="fw-bold mb-1"><i class="bi bi-gear-wide-connected text-primary me-1"></i>Kontrollierte Stapel-Bereinigung (Paketweise mit 7-Stufen-Prüfung &amp; Not-Aus-Schutz)</h6>
                            <p class="small text-muted mb-2">
                                Bereinigt mehrere Film-Ordner nacheinander. Wenn oben im Feld <strong>Händler-ID</strong> eine ID eingetragen ist, wird ausschließlich dieser Creator bereinigt; ist das Feld leer, werden alle Creator geprüft.
                            </p>
                            <div class="d-flex flex-wrap align-items-center gap-2 bg-light border rounded p-2 mb-3">
                                <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold btn-sa-batch-run" onclick="App.StorageAnalytics.startBatch(10, false)">
                                    <i class="bi bi-trash3 me-1"></i>10 Videos bereinigen
                                </button>
                                <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold btn-sa-batch-run" onclick="App.StorageAnalytics.startBatch(50, false)">
                                    <i class="bi bi-trash3 me-1"></i>50 Videos bereinigen
                                </button>
                                <button type="button" class="btn btn-sm btn-danger fw-semibold btn-sa-batch-run" onclick="App.StorageAnalytics.startBatch(100, false)">
                                    <i class="bi bi-trash3 me-1"></i>100 Videos bereinigen
                                </button>
                                <button type="button" class="btn btn-sm btn-success fw-semibold btn-sa-batch-run" id="btn-sa-batch-auto" onclick="App.StorageAnalytics.startBatch(50, true)">
                                    <i class="bi bi-play-fill me-1"></i>Auto-Durchlauf starten (in 50er-Paketen)
                                </button>
                                <button type="button" class="btn btn-sm btn-dark fw-semibold" id="btn-sa-batch-stop" style="display:none;" onclick="App.StorageAnalytics.stopBatch()">
                                    <i class="bi bi-stop-fill me-1"></i>Nach aktuellem Paket anhalten (Stopp)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="btn-sa-batch-reset" onclick="App.StorageAnalytics.resetBatch()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Protokoll &amp; Cursor zurücksetzen
                                </button>
                            </div>

                            <div id="sa-batch-panel" class="border rounded p-3 bg-white" style="display:none;">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div id="sa-batch-status-text" class="fw-bold small text-primary">Bereit zur Stapel-Bereinigung...</div>
                                    <div class="small text-muted">Aktueller Such-Cursor (<code>movies.id</code>): <strong id="sa-batch-cursor-label">Start (neueste zuerst)</strong></div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6 col-md-3">
                                        <div class="border rounded p-2 bg-success bg-opacity-10">
                                            <div class="small text-muted text-uppercase fw-semibold">Bereinigte Videos</div>
                                            <div class="fs-5 fw-bold text-success" id="sa-batch-cnt-movies">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="border rounded p-2 bg-danger bg-opacity-10">
                                            <div class="small text-muted text-uppercase fw-semibold">Gelöschte m_*-Dateien</div>
                                            <div class="fs-5 fw-bold text-danger" id="sa-batch-cnt-files">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="border rounded p-2 bg-info bg-opacity-10">
                                            <div class="small text-muted text-uppercase fw-semibold">Freigegebener Speicher</div>
                                            <div class="fs-5 fw-bold text-primary" id="sa-batch-cnt-bytes">0 Bytes</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="small text-muted text-uppercase fw-semibold">Geprüfte / Saubere Ordner</div>
                                            <div class="fs-6 fw-bold mt-1">
                                                <span id="sa-batch-cnt-scanned">0</span> geprüft (<span id="sa-batch-cnt-clean">0</span> sauber)
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="sa-batch-skip-box" class="alert alert-warning small py-2 px-3 mb-3" style="display:none;">
                                    <strong>&#x26A0; Aus Sicherheitsgründen übersprungene Ordner (<span id="sa-batch-cnt-skipped">0</span>):</strong>
                                    <ul id="sa-batch-skip-list" class="mb-0 mt-1 ps-3"></ul>
                                </div>

                                <div class="fw-bold small mb-1">Live-Bereinigungsprotokoll (neueste Einträge oben):</div>
                                <div class="table-responsive border rounded" style="max-height:320px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered align-middle small mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Online-ID / Ordner-ID</th>
                                                <th>MID / Creator</th>
                                                <th>Filmtitel &amp; Pfad</th>
                                                <th>Gelöschte m_*-Dateien</th>
                                                <th>Freigegeben</th>
                                                <th>Desktop-MP4 Nachprüfung</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sa-batch-log-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 1.3 Videos nach Freigabe-Status -->
                <h6 class="fw-bold mb-2">1.3 Videos nach Freigabe-Status</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle small mb-0" id="sa-table-statuses">
                        <thead class="table-light">
                            <tr>
                                <th>Status-Kategorie</th>
                                <th>Anzahl Filme</th>
                                <th>Gesamte Spieldauer</th>
                                <th>Belegter Speicherplatz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statuses as $s): ?>
                                <tr>
                                    <td><strong><?= View::e((string)$s['label']) ?></strong></td>
                                    <td><?= (int)$s['count'] ?> Filme</td>
                                    <td><?= View::e((string)$s['hours']) ?></td>
                                    <td><strong><?= View::e((string)$s['effective_fmt']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: STREAMING- & DOWNLOAD-TRAFFIC (6 MONATE)              -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-sa-traffic" role="tabpanel">
                <div class="alert alert-info small mb-3">
                    Diese Auswertung basiert auf allen Kunden-Zugriffen in der Tabelle <code>movies_access</code> innerhalb der <strong>letzten 6 Monate</strong> (anhand von Kaufzeitpunkt <code>buy_timestamp</code> und letztem Player-Abruf <code>access_token_datetime</code>). Da ein Kunde einen gekauften Film während der Laufzeit teilweise mehrfach startet oder im Video spult, wird neben dem <strong>1x Basis-Wert</strong> auch ein <strong>realistischer 2x Praxis-Wert</strong> ausgewiesen.
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border h-100 bg-light">
                            <div class="card-body py-3">
                                <div class="text-muted small fw-semibold text-uppercase">Abrufe (Letzte 6 Monate)</div>
                                <div class="fs-3 fw-bold text-primary mt-1" id="sa-tr-total-count"><?= View::e((string)($traffic['total_count_6m'] ?? '0')) ?> Zugriffe</div>
                                <div class="small text-muted">
                                    Streams: <strong id="sa-tr-stream-cnt"><?= View::e((string)($traffic['streaming_count_6m'] ?? '0')) ?></strong> &bull;
                                    Downloads: <strong id="sa-tr-dl-cnt"><?= View::e((string)($traffic['download_count_6m'] ?? '0')) ?></strong><br>
                                    Aktive Kunden: <strong id="sa-tr-users"><?= View::e((string)($traffic['unique_users_6m'] ?? '0')) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border h-100 bg-light">
                            <div class="card-body py-3">
                                <div class="text-muted small fw-semibold text-uppercase">Gesamt-Traffic (6M, 1x Basis)</div>
                                <div class="fs-3 fw-bold text-info mt-1" id="sa-tr-total-6m"><?= View::e((string)($traffic['total_bytes_6m_fmt'] ?? '-')) ?></div>
                                <div class="small text-muted">
                                    Streaming: <strong id="sa-tr-stream-6m"><?= View::e((string)($traffic['streaming_bytes_6m_fmt'] ?? '-')) ?></strong> (<span id="sa-tr-stream-hrs"><?= View::e((string)($traffic['streaming_hours_6m'] ?? '-')) ?></span>)<br>
                                    Download: <strong id="sa-tr-dl-6m"><?= View::e((string)($traffic['download_bytes_6m_fmt'] ?? '-')) ?></strong> (<span id="sa-tr-dl-hrs"><?= View::e((string)($traffic['download_hours_6m'] ?? '-')) ?></span>)
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border border-info border-opacity-25 h-100 bg-info bg-opacity-10">
                            <div class="card-body py-3">
                                <div class="text-dark small fw-semibold text-uppercase">&Oslash; Traffic pro Monat (1x Basis)</div>
                                <div class="fs-3 fw-bold text-primary mt-1" id="sa-tr-avg-1x"><?= View::e((string)($traffic['avg_monthly_1x_fmt'] ?? '-')) ?> / Monat</div>
                                <div class="small text-muted">Durchschnittlicher monatlicher Datenverbrauch bei 1x Abspielen pro Kauf</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border border-success border-opacity-25 h-100 bg-success bg-opacity-10">
                            <div class="card-body py-3">
                                <div class="text-success small fw-semibold text-uppercase">&Oslash; Traffic pro Monat (2x Realistisch)</div>
                                <div class="fs-3 fw-bold text-success mt-1" id="sa-tr-avg-2x"><?= View::e((string)($traffic['avg_monthly_2x_fmt'] ?? '-')) ?> / Monat</div>
                                <div class="small text-muted">Empfohlene Kalkulationsbasis inkl. Mehrfach-Ansicht &amp; Spulen</div>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Monatliche Entwicklung (Letzte 6 Monate)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle small mb-0" id="sa-table-monthly">
                        <thead class="table-light">
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
                        <tbody>
                            <?php foreach (($traffic['monthly_rows'] ?? []) as $m): ?>
                                <tr>
                                    <td><strong><?= View::e((string)$m['month_label']) ?></strong></td>
                                    <td><?= (int)$m['streaming_count'] ?></td>
                                    <td><?= (int)$m['download_count'] ?></td>
                                    <td><?= View::e((string)$m['playtime_hours']) ?></td>
                                    <td><?= View::e((string)$m['streaming_formatted']) ?></td>
                                    <td><?= View::e((string)$m['download_formatted']) ?></td>
                                    <td><strong><?= View::e((string)$m['total_formatted']) ?></strong></td>
                                    <td><strong class="text-success"><?= View::e((string)$m['realistic_2x_formatted']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: KONVERTIERUNGS-CHECK & CLOUD-KOSTEN                   -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-sa-cloud" role="tabpanel">
                <h6 class="fw-bold mb-2">3.1 Prüfergebnis zur Video-Konvertierung (<code>cronjobs/convert_movie.php</code>)</h6>
                <div class="bg-light border rounded-3 p-3 mb-4 small">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">
                            <strong class="text-danger">Befund 1: <code>m_*.webm</code> und <code>m_*.ogv</code> werden nicht mehr benötigt und nie ausgeliefert:</strong><br>
                            In <code>api/movie_player.php</code> wird immer ausschließlich die Hauptdatei <code>$movie_ary-&gt;filename</code> (Desktop-MP4) gestreamt. Moderne Desktop- und Mobilbrowser unterstützen zu 100 % nativ MP4 (H.264). Diese Altdateien können über den 7-Stufen-Sicherheitsmodus in Tab 1 gefahrlos bereinigt werden.
                        </li>
                        <li class="mb-2">
                            <strong class="text-warning">Befund 2: <code>m_*.mp4</code> (Mobile MP4 mit 640px) ist ebenfalls ungenutzt:</strong><br>
                            Da <code>api/movie_player.php</code> den Parameter <code>$_GET['mobile']</code> ignoriert, erhalten auch mobile Endgeräte immer die große Desktop-MP4.
                        </li>
                        <li>
                            <strong class="text-success">Befund 3: Optimierungspotenzial beim Desktop-MP4 Encoding:</strong><br>
                            Durch modernes Qualitäts-Encoding (<code>-crf 22 -maxrate 5000k -bufsize 10000k -preset slow -c:a aac -b:a 128k -movflags +faststart</code>) reduziert sich die Dateigröße künftiger Videos bei identischer Bildqualität um ca. 35&ndash;40 %.
                        </li>
                    </ul>
                </div>

                <h6 class="fw-bold mb-2">3.2 Monatliche Kosten-Prognose für externe Cloud-Storage-Anbieter (Adult-Content erlaubt)</h6>
                <p class="small text-muted mb-2">
                    Kalkulation der voraussichtlichen monatlichen Kosten anhand deines aktuellen Speichervolumens sowie des realistischen monatlichen Streaming-Traffics (2x Faktor):
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle small mb-0" id="sa-table-providers">
                        <thead class="table-light">
                            <tr>
                                <th>Cloud-Anbieter</th>
                                <th>Preismodell (Speicher &amp; Traffic)</th>
                                <th>Adult-Content Richtlinie (AUP)</th>
                                <th>Monatliche Kosten (Aktueller Bestand)</th>
                                <th>Monatliche Kosten (Nach WebM/OGV-Bereinigung)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $p): ?>
                                <tr>
                                    <td><strong><?= View::e((string)$p['name']) ?></strong></td>
                                    <td><?= View::e((string)$p['rate_info']) ?></td>
                                    <td><?= View::e((string)$p['adult_policy']) ?></td>
                                    <td><strong><?= View::e((string)$p['cost_now_eur']) ?></strong></td>
                                    <td><strong class="text-success"><?= View::e((string)$p['cost_cleaned_eur']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
