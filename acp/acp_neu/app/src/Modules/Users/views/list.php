<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, int> $kpi
 * @var array<int, array<string, mixed>> $shopOptions
 * @var array<int, array<string, mixed>> $users
 * @var int $totalFiltered
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var array<string, mixed> $filters
 */

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $params = [];
    if ((int)($filters['shop_id'] ?? 0) > 0) {
        $params['shop_id'] = (string)(int)$filters['shop_id'];
    }
    if (($filters['coins'] ?? 'all') !== 'all') {
        $params['coins'] = (string)$filters['coins'];
    }
    if (($filters['device'] ?? 'all') !== 'all') {
        $params['device'] = (string)$filters['device'];
    }
    if (($filters['status'] ?? 'all') !== 'all') {
        $params['status'] = (string)$filters['status'];
    }
    if (($filters['q'] ?? '') !== '') {
        $params['q'] = (string)$filters['q'];
    }
    if (($filters['sort'] ?? 'lastonline_desc') !== 'lastonline_desc') {
        $params['sort'] = (string)$filters['sort'];
    }
    if ((int)($filters['limit'] ?? 50) !== 50) {
        $params['limit'] = (string)(int)$filters['limit'];
    }
    if ($targetPage > 1) {
        $params['page'] = (string)$targetPage;
    }
    $qs = http_build_query($params);
    return '#/users' . ($qs !== '' ? '?' . $qs : '');
};
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <!-- Kopfzeile -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge-kkc badge-kkc-info"><i class="bi bi-people-fill me-1"></i>Cloud-User</span>
        <span class="text-muted small">Zentrale Mitglieder- &amp; Endkunden-Übersicht</span>
      </div>
      <h1 class="h4 fw-bold mb-0">EroCloud-User (Mitglieder)</h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="#/sites" class="btn btn-sm btn-outline-kkc">
        <i class="bi bi-globe2 me-1"></i>Angeschlossene Webseiten
      </a>
      <a href="#/users" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute(); return false;">
        <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
      </a>
    </div>
  </div>

  <!-- 4 KPI-Kacheln -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Registrierte Cloud-User</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(2,132,199,0.12); color: #0284c7;">
            <i class="bi bi-people-fill"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['total_users'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-success">Aktiv: <strong><?= number_format($kpi['active_users'], 0, ',', '.') ?></strong></span>
          <?php if ($kpi['locked_users'] > 0): ?>
            <a href="#/users?status=locked" class="text-danger text-decoration-none fw-semibold">
              Gesperrt: <?= number_format($kpi['locked_users'], 0, ',', '.') ?> &rarr;
            </a>
          <?php else: ?>
            <span class="text-muted">Gesperrt: 0</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Coins-Guthaben im Umlauf</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(245,158,11,0.12); color: #f59e0b;">
            <i class="bi bi-coin"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1 text-warning"><?= number_format($kpi['total_coins'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Coins</span></div>
        <div class="d-flex align-items-center justify-content-between small">
          <span class="text-muted">Mit Guthaben (&gt;0): <strong><?= number_format($kpi['with_coins'], 0, ',', '.') ?></strong></span>
          <a href="#/users?coins=with_coins&sort=coins_desc" class="text-decoration-none fw-semibold ec-text-primary">Filtern &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Endgeräte (`online_device`)</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(16,185,129,0.12); color: #10b981;">
            <i class="bi bi-phone-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2 mb-1">
          <span class="h4 fw-bold mb-0"><i class="bi bi-phone me-1 text-success"></i><?= number_format($kpi['mobile_users'], 0, ',', '.') ?></span>
          <span class="text-muted">/</span>
          <span class="h5 fw-semibold text-muted mb-0"><i class="bi bi-pc-display me-1"></i><?= number_format($kpi['desktop_users'], 0, ',', '.') ?></span>
        </div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Mobile vs. Desktop</span>
          <a href="#/users?device=mobile" class="text-decoration-none fw-semibold ec-text-primary">Mobile &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card-kkc h-100 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Herkunfts-Domains / Shops</span>
          <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(139,92,246,0.12); color: #8b5cf6;">
            <i class="bi bi-globe2"></i>
          </span>
        </div>
        <div class="h3 fw-bold mb-1"><?= number_format($kpi['distinct_shops'], 0, ',', '.') ?></div>
        <div class="d-flex align-items-center justify-content-between small text-muted">
          <span>Aktive P4C-Shops mit Usern</span>
          <a href="#/sites" class="text-decoration-none fw-semibold ec-text-primary">Zu den Shops &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter- & Suchleiste -->
  <div class="card-kkc p-3 mb-4">
    <form id="users-filter-form" onsubmit="return App.Users.submitFilterForm(event);">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3">
          <label class="form-label small fw-semibold mb-1">Volltextsuche (ID, Username, E-Mail, Domain)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= View::e((string)$filters['q']) ?>" placeholder="Username, E-Mail, Domain, Shop-ID...">
          </div>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Herkunfts-Shop (`p4c_shop_id`)</label>
          <select class="form-select form-select-sm" name="shop_id" onchange="App.Users.submitFilterForm(event)">
            <option value="0">Alle Shops / Domains</option>
            <?php foreach ($shopOptions as $so): ?>
              <option value="<?= (int)$so['shop_id'] ?>" <?= (int)$filters['shop_id'] === (int)$so['shop_id'] ? 'selected' : '' ?>>
                #<?= (int)$so['shop_id'] ?> – <?= View::e((string)$so['domain']) ?> (<?= (int)$so['count'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Coin-Guthaben</label>
          <select class="form-select form-select-sm" name="coins" onchange="App.Users.submitFilterForm(event)">
            <option value="all" <?= $filters['coins'] === 'all' ? 'selected' : '' ?>>Alle Guthaben</option>
            <option value="with_coins" <?= $filters['coins'] === 'with_coins' ? 'selected' : '' ?>>Mit Coins (&gt; 0)</option>
            <option value="high_coins" <?= $filters['coins'] === 'high_coins' ? 'selected' : '' ?>>Ab 1.000 Coins</option>
            <option value="zero_coins" <?= $filters['coins'] === 'zero_coins' ? 'selected' : '' ?>>0 Coins</option>
          </select>
        </div>

        <div class="col-6 col-md-2 col-xl-1">
          <label class="form-label small fw-semibold mb-1">Gerät</label>
          <select class="form-select form-select-sm" name="device" onchange="App.Users.submitFilterForm(event)">
            <option value="all" <?= $filters['device'] === 'all' ? 'selected' : '' ?>>Alle</option>
            <option value="mobile" <?= $filters['device'] === 'mobile' ? 'selected' : '' ?>>Mobile</option>
            <option value="desktop" <?= $filters['device'] === 'desktop' ? 'selected' : '' ?>>Desktop</option>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-semibold mb-1">Sortierung</label>
          <select class="form-select form-select-sm" name="sort" onchange="App.Users.submitFilterForm(event)">
            <option value="lastonline_desc" <?= $filters['sort'] === 'lastonline_desc' ? 'selected' : '' ?>>Zuletzt online (Standard)</option>
            <option value="coins_desc" <?= $filters['sort'] === 'coins_desc' ? 'selected' : '' ?>>Meiste Coins zuerst</option>
            <option value="id_desc" <?= $filters['sort'] === 'id_desc' ? 'selected' : '' ?>>ID absteigend (Neueste)</option>
            <option value="id_asc" <?= $filters['sort'] === 'id_asc' ? 'selected' : '' ?>>ID aufsteigend (Älteste)</option>
            <option value="username_asc" <?= $filters['sort'] === 'username_asc' ? 'selected' : '' ?>>Username (A–Z)</option>
          </select>
        </div>

        <div class="col-6 col-md-2 col-xl-1">
          <label class="form-label small fw-semibold mb-1">Pro Seite</label>
          <select class="form-select form-select-sm" name="limit" onchange="App.Users.submitFilterForm(event)">
            <?php foreach ([50, 100, 250, 500] as $lim): ?>
              <option value="<?= $lim ?>" <?= (int)$filters['limit'] === $lim ? 'selected' : '' ?>><?= $lim ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-3 col-xl-1 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-primary-kkc flex-grow-1" title="Filter anwenden">
            <i class="bi bi-funnel-fill"></i>
          </button>
          <a href="#/users" class="btn btn-sm btn-outline-kkc" title="Filter zurücksetzen">
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
        Zeige <strong><?= count($users) ?></strong> von <strong><?= number_format($totalFiltered, 0, ',', '.') ?></strong> Cloud-Usern (Seite <?= $page ?> von <?= $totalPages ?>)
      </div>
      <div class="input-group input-group-sm" style="width: 240px;">
        <span class="input-group-text"><i class="bi bi-lightning-charge"></i></span>
        <input type="text" class="form-control" placeholder="Schnellfilter auf dieser Seite..." oninput="App.Users.quickFilterTable(this.value)">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase text-muted">
          <tr>
            <th style="width: 90px;">ID</th>
            <th>Username &amp; E-Mail</th>
            <th class="text-end" style="width: 140px;">Coins</th>
            <th style="width: 150px;">Geburtsdatum</th>
            <th style="width: 190px;">Shop &amp; Domain</th>
            <th class="text-center" style="width: 120px;">Device</th>
            <th style="width: 165px;">Zuletzt online</th>
            <th class="text-center" style="width: 110px;">Status</th>
            <th class="text-end" style="width: 120px;">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="9" class="text-center text-muted py-5">
                <i class="bi bi-people fs-2 d-block mb-2 opacity-50"></i>
                Keine Cloud-User für die gewählten Filterkriterien gefunden.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <?php
                $hay = mb_strtolower($u['id'] . ' ' . $u['remote_member_id'] . ' ' . $u['username'] . ' ' . $u['email'] . ' ' . $u['domain'] . ' ' . $u['p4c_shop_id'], 'UTF-8');
              ?>
              <tr class="user-list-row" data-search="<?= View::e($hay) ?>">
                <td class="font-monospace">
                  <a href="#/user?id=<?= (int)$u['id'] ?>" class="fw-bold text-decoration-none">#<?= (int)$u['id'] ?></a>
                  <?php if ($u['remote_member_id'] !== ''): ?>
                    <div class="text-muted" style="font-size: 0.7rem;"><?= View::e((string)$u['remote_member_id']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="#/user?id=<?= (int)$u['id'] ?>" class="fw-bold text-body text-decoration-none d-block">
                    <?= View::e((string)$u['username']) ?>
                  </a>
                  <?php if ($u['email'] !== ''): ?>
                    <a href="mailto:<?= View::e((string)$u['email']) ?>" class="small text-muted text-decoration-none">
                      <i class="bi bi-envelope me-1"></i><?= View::e((string)$u['email']) ?>
                    </a>
                  <?php endif; ?>
                </td>
                <td class="text-end font-monospace">
                  <?php if ($u['amount_coins'] > 0): ?>
                    <span class="badge bg-warning bg-opacity-15 text-dark border border-warning px-2 py-1 fw-bold">
                      <i class="bi bi-coin text-warning me-1"></i><?= number_format((int)$u['amount_coins'], 0, ',', '.') ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">0</span>
                  <?php endif; ?>
                </td>
                <td class="small">
                  <div><?= View::e((string)$u['birthday_de']) ?></div>
                  <?php if ($u['age'] !== null): ?>
                    <span class="text-muted" style="font-size: 0.72rem;"><?= (int)$u['age'] ?> Jahre</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($u['p4c_shop_id'] > 0): ?>
                    <a href="#/site?id=<?= (int)$u['p4c_shop_id'] ?>" class="text-decoration-none fw-semibold small d-block">
                      <i class="bi bi-globe2 me-1 ec-text-primary"></i><?= View::e($u['domain'] !== '' ? (string)$u['domain'] : ('Shop #' . (int)$u['p4c_shop_id'])) ?>
                    </a>
                    <span class="text-muted font-monospace" style="font-size: 0.72rem;">Shop-ID #<?= (int)$u['p4c_shop_id'] ?></span>
                  <?php else: ?>
                    <span class="text-muted small">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ($u['online_device'] === 'mobile'): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                      <i class="bi bi-phone me-1"></i>Mobile
                    </span>
                  <?php elseif ($u['online_device'] === 'desktop'): ?>
                    <span class="badge bg-secondary bg-opacity-10 text-body border">
                      <i class="bi bi-pc-display me-1"></i>Desktop
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">-</span>
                  <?php endif; ?>
                </td>
                <td class="small fw-semibold">
                  <?= View::e((string)$u['lastonline_de']) ?>
                </td>
                <td class="text-center">
                  <?php if ($u['locked']): ?>
                    <span class="badge-kkc badge-kkc-danger"><i class="bi bi-lock-fill me-1"></i>Gesperrt</span>
                  <?php else: ?>
                    <span class="badge-kkc badge-kkc-success">Aktiv</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a href="#/user?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-primary-kkc">
                    <i class="bi bi-person-lines-fill me-1"></i>Details
                  </a>
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
        <nav aria-label="Users Paginierung">
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
