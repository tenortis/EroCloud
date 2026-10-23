<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, mixed> $logMeta
 * @var array<string, int> $kpi
 * @var array<int, array<string, mixed>> $entries
 * @var array<int, array<string, mixed>> $groupedEntries
 * @var string $rawContent
 */
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <!-- Seiten-Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div>
      <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-bug-fill ec-text-primary"></i>
        <span>PHP-Error-Log</span>
      </h1>
      <p class="text-muted small mb-0">
        Zentrale Überwachung und Auswertung der PHP-Fehlerdatei (<code><?= View::e((string)$logMeta['path']) ?></code>).
      </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.SystemTech.errorLogAction('write_test_entry')" title="Schreibt einen Test-Eintrag in .errorlog">
        <i class="bi bi-Flask me-1"></i>Test-Eintrag schreiben
      </button>

      <?php if (!empty($logMeta['has_backup'])): ?>
        <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.SystemTech.errorLogAction('restore_backup')" title="Backup (.errorlog.bak) wiederherstellen">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Backup (<?= View::e((string)$logMeta['backup_size']) ?>)
        </button>
      <?php endif; ?>

      <button type="button" class="btn btn-sm btn-outline-danger" onclick="App.SystemTech.errorLogAction('clear_log')" <?= (int)$logMeta['size_bytes'] === 0 ? 'disabled' : '' ?>>
        <i class="bi bi-trash3 me-1"></i>Log leeren (mit Backup)
      </button>

      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i>Neu laden
      </button>
    </div>
  </div>

  <!-- 4 KPI-Kacheln -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Log-Datei &amp; Status</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(2,132,199,0.12); color: #0284c7;">
            <i class="bi bi-file-earmark-code-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= View::e((string)$logMeta['size_fmt']) ?></div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Stand: <?= View::e((string)$logMeta['mtime_de']) ?></span>
          <span class="badge <?= $logMeta['writable'] ? 'bg-success bg-opacity-10 text-success' : 'bg-danger' ?>">
            <?= $logMeta['writable'] ? 'Beschreibbar' : 'Schreibgeschützt' ?>
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3" style="cursor: pointer;" onclick="App.SystemTech.setErrorFilterSeverity('fatal')">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Fatal Errors &amp; Exceptions</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(239,68,68,0.12); color: #ef4444;">
            <i class="bi bi-exclamation-octagon-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1 <?= $kpi['fatal_errors'] > 0 ? 'text-danger' : 'text-success' ?>">
          <?= number_format($kpi['fatal_errors'], 0, ',', '.') ?>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">SQL-Fehler: <strong><?= number_format($kpi['sql_errors'], 0, ',', '.') ?></strong></span>
          <span class="ec-text-primary fw-semibold">Filtern &rarr;</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3" style="cursor: pointer;" onclick="App.SystemTech.setErrorFilterSeverity('warning')">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Warnings &amp; Laufzeitfehler</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(245,158,11,0.12); color: #f59e0b;">
            <i class="bi bi-exclamation-triangle-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1 <?= $kpi['warnings'] > 0 ? 'text-warning' : '' ?>">
          <?= number_format($kpi['warnings'], 0, ',', '.') ?>
        </div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Web: <?= number_format($kpi['http_entries'], 0, ',', '.') ?> | CLI: <?= number_format($kpi['cli_entries'], 0, ',', '.') ?></span>
          <span class="ec-text-primary fw-semibold">Filtern &rarr;</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3" style="cursor: pointer;" onclick="App.SystemTech.setErrorFilterSeverity('deprecated')">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Deprecated &amp; Notices (PHP 8.4)</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(139,92,246,0.12); color: #8b5cf6;">
            <i class="bi bi-info-circle-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['deprecations'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Gesamt-Einträge: <strong><?= number_format($kpi['total_entries'], 0, ',', '.') ?></strong></span>
          <span class="ec-text-primary fw-semibold">Filtern &rarr;</span>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($logMeta['is_truncated'])): ?>
    <div class="alert alert-info py-2 px-3 small mb-3">
      <i class="bi bi-info-circle-fill me-1"></i>
      Da die Log-Datei größer als 1,5 MB ist, werden aus Performance-Gründen automatisch die aktuellsten 1,5 MB ausgewertet.
    </div>
  <?php endif; ?>

  <!-- 3 Tabs: Einzel-Einträge, Gruppierte Häufigkeit, Roh-Terminal -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold" id="tab-btn-err-list" data-bs-toggle="tab" data-bs-target="#tab-err-list" type="button" role="tab">
        <i class="bi bi-list-ul me-1"></i>1. Chronologische Fehlerliste (Neueste zuerst)
        <span class="badge bg-primary rounded-pill ms-1"><?= count($entries) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-err-grouped" data-bs-toggle="tab" data-bs-target="#tab-err-grouped" type="button" role="tab">
        <i class="bi bi-layers-fill me-1"></i>2. Gruppiert nach Häufigkeit
        <span class="badge bg-secondary rounded-pill ms-1"><?= count($groupedEntries) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-err-raw" data-bs-toggle="tab" data-bs-target="#tab-err-raw" type="button" role="tab">
        <i class="bi bi-terminal-fill me-1"></i>3. Original `.errorlog` Konsole
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ================================================================== -->
    <!-- TAB 1: Strukturierte Einzel-Einträge                               -->
    <!-- ================================================================== -->
    <div class="tab-pane fade show active" id="tab-err-list" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <select id="err-filter-severity" class="form-select form-select-sm" style="width: 190px;" onchange="App.SystemTech.applyErrorFilters()">
              <option value="all">Alle Schweregrade</option>
              <option value="fatal">Fatal / Exception</option>
              <option value="sql">SQL / Datenbank</option>
              <option value="warning">Warning / Fehler</option>
              <option value="deprecated">Deprecated / Notice</option>
            </select>

            <select id="err-filter-context" class="form-select form-select-sm" style="width: 170px;" onchange="App.SystemTech.applyErrorFilters()">
              <option value="all">Alle Quellen (Web &amp; CLI)</option>
              <option value="http">Nur Web / HTTP</option>
              <option value="cli">Nur CLI / Cronjobs</option>
            </select>
          </div>

          <div class="input-group input-group-sm" style="width: 300px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="err-filter-q" class="form-control" placeholder="Nach Datei, Zeile oder Text filtern..." oninput="App.SystemTech.applyErrorFilters()">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th style="width: 165px;">Zeitstempel</th>
                <th style="width: 135px;">Typ</th>
                <th style="width: 180px;">Kontext / Quelle</th>
                <th>Fehlermeldung</th>
                <th style="width: 240px;">Datei &amp; Zeile</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($entries)): ?>
                <tr>
                  <td colspan="5" class="text-center text-success py-5">
                    <i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>
                    <strong>Keine Einträge im PHP-Error-Log vorhanden!</strong>
                    <div class="small text-muted mt-1">Das zentrale Fehlerprotokoll ist aktuell vollständig sauber.</div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($entries as $e): ?>
                  <?php
                    $searchHay = mb_strtolower($e['timestamp'] . ' ' . $e['context'] . ' ' . $e['message'] . ' ' . $e['file'] . ':' . $e['line'], 'UTF-8');
                  ?>
                  <tr class="err-entry-row"
                      data-severity="<?= View::e((string)$e['severity']) ?>"
                      data-context="<?= $e['is_cli'] ? 'cli' : 'http' ?>"
                      data-search="<?= View::e($searchHay) ?>">
                    <td class="font-monospace small text-muted"><?= View::e((string)$e['timestamp']) ?></td>
                    <td>
                      <span class="badge-kkc <?= View::e((string)$e['badge_class']) ?>"><?= View::e((string)$e['severity_label']) ?></span>
                    </td>
                    <td class="small">
                      <?php if ($e['is_cli']): ?>
                        <span class="badge bg-dark font-monospace"><?= View::e((string)$e['context']) ?></span>
                      <?php else: ?>
                        <code class="small text-muted text-break"><?= View::e((string)$e['context']) ?></code>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="font-monospace small text-break" style="white-space: pre-wrap; max-height: 140px; overflow-y: auto;"><?= View::e((string)$e['message']) ?></div>
                    </td>
                    <td class="small font-monospace">
                      <?php if ($e['file'] !== ''): ?>
                        <div class="fw-bold text-body" title="<?= View::e((string)$e['file']) ?>">
                          <?= View::e((string)$e['short_file']) ?><?= $e['line'] > 0 ? ':' . (int)$e['line'] : '' ?>
                        </div>
                        <div class="text-muted text-truncate" style="max-width: 220px; font-size: 0.7rem;"><?= View::e((string)$e['file']) ?></div>
                      <?php else: ?>
                        <span class="text-muted">-</span>
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
    <!-- TAB 2: Gruppierte Fehler-Häufigkeit                                -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-err-grouped" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom" style="border-color: var(--kkc-border) !important;">
          <h2 class="h6 fw-bold mb-0">Gruppierte Fehler-Ursachen (Top <?= count($groupedEntries) ?>)</h2>
          <small class="text-muted">Identische Fehlermeldungen an derselben Code-Stelle werden zusammengefasst, um wiederkehrende Probleme sofort zu erkennen.</small>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th class="text-center" style="width: 95px;">Anzahl</th>
                <th style="width: 140px;">Typ</th>
                <th>Fehlermeldung</th>
                <th style="width: 230px;">Ursprungsdatei</th>
                <th style="width: 170px;">Zuletzt gesehen</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($groupedEntries)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">Keine gruppierten Einträge vorhanden.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($groupedEntries as $g): ?>
                  <tr>
                    <td class="text-center">
                      <span class="badge bg-danger rounded-pill px-2.5 py-1"><?= number_format((int)$g['count'], 0, ',', '.') ?>&times;</span>
                    </td>
                    <td>
                      <span class="badge-kkc <?= View::e((string)$g['badge_class']) ?>"><?= View::e((string)$g['severity_label']) ?></span>
                    </td>
                    <td>
                      <div class="font-monospace small text-break" style="white-space: pre-wrap; max-height: 110px; overflow-y: auto;"><?= View::e((string)$g['message']) ?></div>
                    </td>
                    <td class="small font-monospace">
                      <?php if ($g['file'] !== ''): ?>
                        <div class="fw-bold"><?= View::e((string)$g['short_file']) ?><?= $g['line'] > 0 ? ':' . (int)$g['line'] : '' ?></div>
                        <div class="text-muted text-truncate" style="max-width: 210px; font-size: 0.7rem;"><?= View::e((string)$g['file']) ?></div>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                    <td class="font-monospace small text-muted">
                      <?= View::e((string)$g['last_seen']) ?>
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
    <!-- TAB 3: Original .errorlog Rohansicht                               -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-err-raw" role="tabpanel">
      <div class="card-kkc p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-semibold text-muted">Originaldatei: <code><?= View::e((string)$logMeta['path']) ?></code></span>
          <button type="button" class="btn btn-xs btn-outline-kkc py-1 px-2 small" onclick="App.SystemTech.copyRawErrorLog()">
            <i class="bi bi-clipboard me-1"></i>Gesamten Log-Text kopieren
          </button>
        </div>
        <pre id="err-raw-console" class="p-3 rounded bg-dark text-light font-monospace small mb-0" style="min-height: 260px; max-height: 750px; overflow: auto; white-space: pre;"><?= $rawContent !== '' ? View::e($rawContent) : '--- Log-Datei ist aktuell leer ---' ?></pre>
      </div>
    </div>

  </div>
</div>
