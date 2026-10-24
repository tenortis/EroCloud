<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, int> $kpi
 * @var array<int, array<string, mixed>> $merchants
 * @var int $totalFiltered
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var bool $canImpersonate
 * @var array<string, mixed> $filters
 */

$canImpersonate = !empty($canImpersonate);

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $params = [];
    if (($filters['rules'] ?? 'all') !== 'all') {
        $params['rules'] = (string)$filters['rules'];
    }
    if (($filters['activity'] ?? 'all') !== 'all') {
        $params['activity'] = (string)$filters['activity'];
    }
    if (($filters['q'] ?? '') !== '') {
        $params['q'] = (string)$filters['q'];
    }
    if (($filters['sort'] ?? 'id_desc') !== 'id_desc') {
        $params['sort'] = (string)$filters['sort'];
    }
    if ((int)($filters['limit'] ?? 50) !== 50) {
        $params['limit'] = (string)(int)$filters['limit'];
    }
    if ($targetPage > 1) {
        $params['page'] = (string)$targetPage;
    }
    $qs = http_build_query($params);
    return '#/merchants' . ($qs !== '' ? '?' . $qs : '');
};
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <!-- Seiten-Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div>
      <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-briefcase-fill ec-text-primary"></i>
        <span>P4C-Merchants</span>
      </h1>
      <p class="text-muted small mb-0">
        Verwaltung aller registrierten Pay4Coins-Merchants (Händler), Darsteller-Kontingente, Filme und Aktivitäten.
      </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
        <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
      </button>
    </div>
  </div>

  <!-- 4 KPI-Kacheln -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">P4C-Merchants Gesamt</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(2,132,199,0.12); color: #0284c7;">
            <i class="bi bi-briefcase-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['total_merchants'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Aktiv (30 Tage): <strong><?= number_format($kpi['active_30d'], 0, ',', '.') ?></strong></span>
          <a href="#/merchants?activity=active_30d" class="text-decoration-none fw-semibold ec-text-primary">Filtern &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Richtlinien &amp; Multi-Profil</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(16,185,129,0.12); color: #10b981;">
            <i class="bi bi-shield-check"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2 mb-1">
          <span class="h4 fw-bold text-success mb-0"><?= number_format($kpi['rules_accepted'], 0, ',', '.') ?></span>
          <span class="text-muted small">akzeptiert</span>
          <span class="text-muted">/</span>
          <span class="fw-semibold text-warning"><?= number_format($kpi['rules_pending'], 0, ',', '.') ?> offen</span>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Multi-Profil (&gt;1): <strong><?= number_format($kpi['multi_profile'], 0, ',', '.') ?></strong></span>
          <a href="#/merchants?activity=multi_profile" class="text-decoration-none fw-semibold ec-text-primary">Anzeigen &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Angelegte Darsteller-Profile</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(139,92,246,0.12); color: #8b5cf6;">
            <i class="bi bi-person-bounding-box"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['total_actors'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Zugeordnete Creator-Profile</span>
          <a href="#/merchants?activity=with_actors" class="text-decoration-none fw-semibold ec-text-primary">Mit Profilen &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Veröffentlichter Content &amp; Prüfung</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(245,158,11,0.12); color: #f59e0b;">
            <i class="bi bi-film"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2 mb-1">
          <span class="h5 fw-bold mb-0"><?= number_format($kpi['total_movies_on'], 0, ',', '.') ?> Filme</span>
          <span class="text-muted">/</span>
          <span class="h6 fw-semibold text-muted mb-0"><?= number_format($kpi['total_albums_on'], 0, ',', '.') ?> Alben</span>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-warning fw-semibold"><i class="bi bi-hourglass-split me-1"></i>In Prüfung: <?= number_format($kpi['total_in_checking'], 0, ',', '.') ?></span>
          <a href="#/merchants?activity=with_checking" class="text-decoration-none fw-semibold text-warning">Offene prüfen &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter- & Suchleiste -->
  <div class="card-kkc p-3 mb-4">
    <form id="merchants-filter-form" onsubmit="return App.Merchants.submitFilterForm(event);">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3">
          <label class="form-label small fw-semibold mb-1">Volltextsuche (MID, PID, Username, E-Mail, IP, Profil)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" id="merchants-filter-q" value="<?= View::e((string)$filters['q']) ?>" placeholder="z. B. MID, Partner-ID, Username, E-Mail...">
          </div>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Richtlinien-Status</label>
          <select class="form-select form-select-sm" name="rules" onchange="App.Merchants.submitFilterForm(event)">
            <option value="all" <?= $filters['rules'] === 'all' ? 'selected' : '' ?>>Alle Händler</option>
            <option value="accepted" <?= $filters['rules'] === 'accepted' ? 'selected' : '' ?>>Richtlinien akzeptiert</option>
            <option value="pending" <?= $filters['rules'] === 'pending' ? 'selected' : '' ?>>Noch nicht akzeptiert</option>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Aktivität &amp; Bestand</label>
          <select class="form-select form-select-sm" name="activity" onchange="App.Merchants.submitFilterForm(event)">
            <option value="all" <?= $filters['activity'] === 'all' ? 'selected' : '' ?>>Alle Bestände</option>
            <option value="active_30d" <?= $filters['activity'] === 'active_30d' ? 'selected' : '' ?>>Login letzte 30 Tage</option>
            <option value="with_actors" <?= $filters['activity'] === 'with_actors' ? 'selected' : '' ?>>Mit Darsteller-Profilen</option>
            <option value="without_actors" <?= $filters['activity'] === 'without_actors' ? 'selected' : '' ?>>Ohne Darsteller-Profil</option>
            <option value="with_checking" <?= $filters['activity'] === 'with_checking' ? 'selected' : '' ?>>Hat Content in Prüfung</option>
            <option value="multi_profile" <?= $filters['activity'] === 'multi_profile' ? 'selected' : '' ?>>Multi-Profil (&gt;1 erlaubt)</option>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Sortierung</label>
          <select class="form-select form-select-sm" name="sort" onchange="App.Merchants.submitFilterForm(event)">
            <option value="id_desc" <?= $filters['sort'] === 'id_desc' ? 'selected' : '' ?>>MID absteigend (Neueste)</option>
            <option value="id_asc" <?= $filters['sort'] === 'id_asc' ? 'selected' : '' ?>>MID aufsteigend (Älteste)</option>
            <option value="last_login_desc" <?= $filters['sort'] === 'last_login_desc' ? 'selected' : '' ?>>Zuletzt eingeloggt</option>
            <option value="partner_id_asc" <?= $filters['sort'] === 'partner_id_asc' ? 'selected' : '' ?>>P4C-Partner-ID (A–Z)</option>
            <option value="limit_desc" <?= $filters['sort'] === 'limit_desc' ? 'selected' : '' ?>>Profil-Limit absteigend</option>
          </select>
        </div>

        <div class="col-6 col-md-2 col-xl-1">
          <label class="form-label small fw-semibold mb-1">Pro Seite</label>
          <select class="form-select form-select-sm" name="limit" onchange="App.Merchants.submitFilterForm(event)">
            <?php foreach ([50, 100, 250, 500] as $lim): ?>
              <option value="<?= $lim ?>" <?= (int)$filters['limit'] === $lim ? 'selected' : '' ?>><?= $lim ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-primary-kkc flex-grow-1">
            <i class="bi bi-funnel-fill me-1"></i>Filtern
          </button>
          <a href="#/merchants" class="btn btn-sm btn-outline-kkc" title="Filter zurücksetzen">
            <i class="bi bi-x-lg"></i>
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Tabellen-Card -->
  <div class="card-kkc overflow-hidden">
    <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
      <div class="small text-muted">
        Zeige <strong><?= count($merchants) ?></strong> von <strong><?= number_format($totalFiltered, 0, ',', '.') ?></strong> P4C-Merchants (Seite <?= $page ?> von <?= $totalPages ?>)
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: 240px;">
          <span class="input-group-text"><i class="bi bi-lightning-charge"></i></span>
          <input type="text" class="form-control" placeholder="Schnellfilter auf dieser Seite..." oninput="App.Merchants.quickFilterTable(this.value)">
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-muted">
          <tr>
            <th style="width: 80px;">MID</th>
            <th style="width: 140px;">P4C-Partner-ID</th>
            <th>P4C-Username &amp; Kontakt</th>
            <th class="text-center" style="width: 135px;">Profile</th>
            <th class="text-center" style="width: 135px;">Filme</th>
            <th class="text-center" style="width: 135px;">Fotoalben</th>
            <th style="width: 170px;">Letzter Login &amp; IP</th>
            <th style="width: 165px;">Richtlinien</th>
            <th class="text-end" style="width: <?= $canImpersonate ? '265px' : '130px' ?>;">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($merchants)): ?>
            <tr>
              <td colspan="9" class="text-center text-muted py-5">
                <i class="bi bi-briefcase fs-2 d-block mb-2 opacity-50"></i>
                Keine P4C-Merchants für die gewählten Filterkriterien gefunden.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($merchants as $m): ?>
              <?php
                $searchHaystack = mb_strtolower(
                    $m['id'] . ' ' . $m['partner_id'] . ' ' . $m['username'] . ' ' . $m['email'] . ' ' . $m['full_name'] . ' ' . $m['last_ip'],
                    'UTF-8'
                );
              ?>
              <tr class="merchant-list-row" data-search="<?= View::e($searchHaystack) ?>">
                <td class="font-monospace fw-bold">
                  <a href="#/merchant?id=<?= (int)$m['id'] ?>" class="text-decoration-none">#<?= (int)$m['id'] ?></a>
                </td>
                <td>
                  <a href="#/merchant?id=<?= (int)$m['id'] ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace text-decoration-none px-2 py-1">
                    <?= View::e($m['partner_id'] !== '' ? $m['partner_id'] : '-') ?>
                  </a>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <a href="#/merchant?id=<?= (int)$m['id'] ?>" class="fw-bold text-body text-decoration-none">
                      <?= View::e($m['username']) ?>
                    </a>
                    <?php if ($m['full_name'] !== ''): ?>
                      <span class="small text-muted"><i class="bi bi-person me-1"></i><?= View::e($m['full_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($m['email'] !== ''): ?>
                      <a href="mailto:<?= View::e($m['email']) ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-envelope me-1"></i><?= View::e($m['email']) ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="text-center">
                  <a href="#/actors?merchant_id=<?= (int)$m['id'] ?>" class="text-decoration-none d-inline-flex flex-column align-items-center">
                    <span class="badge <?= $m['actors_total'] > 0 ? 'bg-primary' : 'bg-secondary bg-opacity-25 text-muted' ?> rounded-pill px-2.5">
                      <?= (int)$m['actors_total'] ?> / <?= (int)$m['min_profiles_limit'] ?>
                    </span>
                    <?php if ($m['actors_active'] > 0): ?>
                      <small class="text-success mt-1" style="font-size: 0.72rem;"><?= (int)$m['actors_active'] ?> aktiv</small>
                    <?php endif; ?>
                  </a>
                </td>
                <td class="text-center">
                  <div class="d-flex flex-column align-items-center gap-1">
                    <span class="badge <?= $m['movies_online'] > 0 ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-secondary bg-opacity-10 text-muted' ?>">
                      <i class="bi bi-film me-1"></i><?= (int)$m['movies_online'] ?> online
                    </span>
                    <?php if ($m['movies_checking'] > 0): ?>
                      <a href="#/movies-checking?q=<?= (int)$m['id'] ?>" class="badge bg-warning text-dark text-decoration-none">
                        <?= (int)$m['movies_checking'] ?> in Prüfung
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="text-center">
                  <div class="d-flex flex-column align-items-center gap-1">
                    <span class="badge <?= $m['albums_online'] > 0 ? 'bg-info bg-opacity-10 text-info border border-info border-opacity-25' : 'bg-secondary bg-opacity-10 text-muted' ?>">
                      <i class="bi bi-images me-1"></i><?= (int)$m['albums_online'] ?> online
                    </span>
                    <?php if ($m['albums_checking'] > 0): ?>
                      <a href="#/photo-albums-checking?q=<?= (int)$m['id'] ?>" class="badge bg-warning text-dark text-decoration-none">
                        <?= (int)$m['albums_checking'] ?> in Prüfung
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="small fw-semibold"><?= View::e($m['last_ip_dt_de']) ?></div>
                  <?php if ($m['last_ip'] !== ''): ?>
                    <code class="small text-muted"><?= View::e($m['last_ip']) ?></code>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($m['rules_accepted']): ?>
                    <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Akzeptiert</span>
                    <div class="text-muted mt-0.5" style="font-size: 0.72rem;"><?= View::e($m['rules_dt_de']) ?></div>
                  <?php else: ?>
                    <span class="badge-kkc badge-kkc-neutral"><i class="bi bi-clock me-1"></i>Ausstehend</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex align-items-center gap-1">
                    <?php if ($canImpersonate): ?>
                      <button type="button"
                              class="btn btn-sm btn-primary-kkc"
                              title="Session übernehmen &amp; als Partner <?= View::e((string)$m['username']) ?> (MID #<?= (int)$m['id'] ?>) im MCP einloggen"
                              onclick="App.Merchants.impersonateMerchant(<?= (int)$m['id'] ?>, '<?= View::e(addslashes((string)$m['username'])) ?>')">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Session übernehmen
                      </button>
                    <?php endif; ?>
                    <a href="#/merchant?id=<?= (int)$m['id'] ?>" class="btn btn-sm <?= $canImpersonate ? 'btn-outline-kkc' : 'btn-primary-kkc' ?>" title="Merchant-Details &amp; Einstellungen öffnen">
                      <i class="bi bi-person-workspace me-1"></i>Details
                    </a>
                    <a href="#/actors?merchant_id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-kkc" title="Darsteller-Profile dieses Händlers anzeigen">
                      <i class="bi bi-person-bounding-box"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginierung -->
    <?php if ($totalPages > 1): ?>
      <div class="p-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
        <div class="small text-muted">
          Seite <strong><?= $page ?></strong> von <strong><?= $totalPages ?></strong>
        </div>
        <nav aria-label="Merchants Paginierung">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= View::e($buildPageUrl(1)) ?>">&laquo;</a>
            </li>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= View::e($buildPageUrl(max(1, $page - 1))) ?>">&lsaquo; Zurück</a>
            </li>
            <?php
              $startP = max(1, $page - 3);
              $endP   = min($totalPages, $page + 3);
              for ($p = $startP; $p <= $endP; $p++):
            ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= View::e($buildPageUrl($p)) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= View::e($buildPageUrl(min($totalPages, $page + 1))) ?>">Weiter &rsaquo;</a>
            </li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= View::e($buildPageUrl($totalPages)) ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</div>
