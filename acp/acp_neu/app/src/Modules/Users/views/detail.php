<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\View;

/**
 * @var array<string, mixed> $user
 * @var int $prevId
 * @var int $nextId
 * @var array<int, array<string, mixed>> $moviePurchases
 * @var array<int, array<string, mixed>> $albumPurchases
 */

$uid = (int)$user['id'];
?>
<div class="container-fluid py-3 px-3 px-xl-4" data-page-width="full">

  <!-- Seiten-Header mit Vor/Zurück-Navigation -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
    <div class="d-flex align-items-start gap-3">
      <a href="#/users" class="btn btn-sm btn-outline-kkc mt-1">
        <i class="bi bi-arrow-left me-1"></i>Zur Übersicht
      </a>
      <div>
        <h1 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-person-lines-fill ec-text-primary"></i>
          <span><?= View::e($user['username'] !== '' ? (string)$user['username'] : ('User #' . $uid)) ?></span>
          <?php if ($user['locked']): ?>
            <span class="badge-kkc badge-kkc-danger ms-1"><i class="bi bi-lock-fill me-1"></i>Konto gesperrt</span>
          <?php else: ?>
            <span class="badge-kkc badge-kkc-success ms-1">Aktiv</span>
          <?php endif; ?>
        </h1>
        <div class="d-flex align-items-center gap-2">
          <span class="badge-kkc badge-kkc-info font-monospace">User #<?= $uid ?></span>
          <?php if (!empty($user['remote_member_id'])): ?>
            <span class="badge bg-secondary bg-opacity-10 text-body border font-monospace">
              Remote-ID: <?= View::e((string)$user['remote_member_id']) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php if ((int)$user['p4c_shop_id'] > 0): ?>
        <a href="#/site?id=<?= (int)$user['p4c_shop_id'] ?>" class="btn btn-sm btn-outline-kkc">
          <i class="bi bi-globe2 me-1"></i>Shop #<?= (int)$user['p4c_shop_id'] ?> (<?= View::e((string)$user['domain']) ?>)
        </a>
      <?php endif; ?>
      <div class="btn-group btn-group-sm">
        <a href="<?= $prevId > 0 ? '#/user?id=' . $prevId : 'javascript:void(0)' ?>" class="btn btn-outline-kkc <?= $prevId <= 0 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-left"></i> Vorheriger
        </a>
        <a href="<?= $nextId > 0 ? '#/user?id=' . $nextId : 'javascript:void(0)' ?>" class="btn btn-outline-kkc <?= $nextId <= 0 ? 'disabled' : '' ?>">
          Nächster <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 4 Schnell-KPI-Kacheln zum User -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Aktuelles Coin-Guthaben</div>
        <div class="h4 fw-bold text-warning mb-0 mt-1">
          <i class="bi bi-coin me-1"></i><?= number_format((int)$user['amount_coins'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Coins</span>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Gekaufte Filme &amp; Fotoalben</div>
        <div class="h4 fw-bold mb-0 mt-1">
          <?= (int)$user['movies_bought_cnt'] ?> <span class="fs-6 fw-normal text-muted">Filme / <?= (int)$user['albums_bought_cnt'] ?> Alben</span>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Content-Kaufvolumen (Brutto)</div>
        <div class="h4 fw-bold text-success mb-0 mt-1"><?= View::e((string)$user['total_spent_eur']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kkc p-3 h-100">
        <div class="text-muted small">Zuletzt online &amp; Endgerät</div>
        <div class="fw-bold mt-1"><?= View::e((string)$user['lastonline_de']) ?></div>
        <div class="small text-muted">
          <i class="bi <?= $user['online_device'] === 'mobile' ? 'bi-phone text-success' : 'bi-pc-display' ?> me-1"></i>
          <?= $user['online_device'] !== '' ? ucfirst((string)$user['online_device']) : 'Unbekannt' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- 3 Bootstrap-Tabs -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold" id="tab-btn-user-info" data-bs-toggle="tab" data-bs-target="#tab-user-info" type="button" role="tab">
        <i class="bi bi-person-vcard me-1"></i>1. Benutzerdaten &amp; Konto-Status
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-user-movies" data-bs-toggle="tab" data-bs-target="#tab-user-movies" type="button" role="tab">
        <i class="bi bi-film me-1"></i>2. Gekaufte Filme (`movies_access`)
        <span class="badge bg-primary rounded-pill ms-1"><?= count($moviePurchases) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-semibold" id="tab-btn-user-albums" data-bs-toggle="tab" data-bs-target="#tab-user-albums" type="button" role="tab">
        <i class="bi bi-images me-1"></i>3. Gekaufte Fotoalben (`photo_albums_access`)
        <span class="badge bg-secondary rounded-pill ms-1"><?= count($albumPurchases) ?></span>
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ================================================================== -->
    <!-- TAB 1: Benutzerdaten & Konto-Status                                -->
    <!-- ================================================================== -->
    <div class="tab-pane fade show active" id="tab-user-info" role="tabpanel">
      <div class="row g-4">

        <div class="col-12 col-xl-7">
          <div class="card-kkc p-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-person-badge me-2 ec-text-primary"></i>Synchronisierte Mitglieds-Stammdaten
            </h2>

            <div class="row g-3">
              <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">EroCloud User-ID</label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="#<?= $uid ?>" readonly>
              </div>
              <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Remote-Member-ID</label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="<?= View::e((string)$user['remote_member_id']) ?>" readonly>
              </div>
              <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Shop-lokale User-ID</label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="<?= (int)$user['shop_user_id'] > 0 ? '#' . (int)$user['shop_user_id'] : '-' ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Username</label>
                <input type="text" class="form-control form-control-sm fw-bold bg-body-tertiary" value="<?= View::e((string)$user['username']) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1 d-flex justify-content-between">
                  <span>E-Mail-Adresse</span>
                  <?php if (!empty($user['email'])): ?>
                    <a href="mailto:<?= View::e((string)$user['email']) ?>" class="text-decoration-none ec-text-primary">
                      <i class="bi bi-envelope-at me-1"></i>E-Mail senden
                    </a>
                  <?php endif; ?>
                </label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$user['email']) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Geburtsdatum</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$user['birthday_de']) ?><?= $user['age'] ? ' (' . (int)$user['age'] . ' Jahre)' : '' ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Aktuelles Coin-Guthaben</label>
                <input type="text" class="form-control form-control-sm fw-bold bg-body-tertiary" value="<?= number_format((int)$user['amount_coins'], 0, ',', '.') ?> Coins" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1 d-flex justify-content-between">
                  <span>Pay4Coins-Shop-ID</span>
                  <?php if ((int)$user['p4c_shop_id'] > 0): ?>
                    <a href="#/site?id=<?= (int)$user['p4c_shop_id'] ?>" class="text-decoration-none ec-text-primary">Shop anzeigen &rarr;</a>
                  <?php endif; ?>
                </label>
                <input type="text" class="form-control form-control-sm font-monospace bg-body-tertiary" value="<?= (int)$user['p4c_shop_id'] ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Registrierte Shop-Domain</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$user['domain']) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Zuletzt online</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$user['lastonline_de']) ?>" readonly>
              </div>

              <div class="col-sm-6">
                <label class="form-label small text-muted mb-1">Letztes Statistik-Update</label>
                <input type="text" class="form-control form-control-sm bg-body-tertiary" value="<?= View::e((string)$user['last_stats_de']) ?>" readonly>
              </div>
            </div>
          </div>
        </div>

        <!-- Rechte Spalte: Konto-Sperre & Avatar -->
        <div class="col-12 col-xl-5">
          <div class="card-kkc p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
              <i class="bi bi-shield-lock me-2 ec-text-primary"></i>Konto-Status &amp; Moderation
            </h2>

            <div class="p-3 rounded border mb-3" style="background: var(--kkc-surface-hover);">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold">Aktueller Status:</span>
                <?php if ($user['locked']): ?>
                  <span class="badge-kkc badge-kkc-danger"><i class="bi bi-lock-fill me-1"></i>Gesperrt (`locked = 1`)</span>
                <?php else: ?>
                  <span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill me-1"></i>Aktiv (`locked = 0`)</span>
                <?php endif; ?>
              </div>
              <p class="small text-muted mb-3">
                Du kannst diesen Cloud-User bei Missbrauch oder Rückbuchungen sperren bzw. jederzeit wieder freischalten.
              </p>

              <?php if ($user['locked']): ?>
                <button type="button" class="btn btn-sm btn-success w-100" onclick="App.Users.toggleLock(<?= $uid ?>, 0)">
                  <i class="bi bi-unlock-fill me-1"></i>Cloud-User wieder entsperren
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="App.Users.toggleLock(<?= $uid ?>, 1)">
                  <i class="bi bi-lock-fill me-1"></i>Cloud-User sperren
                </button>
              <?php endif; ?>
            </div>

            <?php if (!empty($user['avatar_url'])): ?>
              <div class="p-3 rounded border text-center" style="background: var(--kkc-surface-hover);">
                <div class="small fw-semibold text-muted mb-2">Hinterlegtes User-Profilbild (`avatar_url`)</div>
                <img src="<?= View::e((string)$user['avatar_url']) ?>" alt="User Avatar" class="rounded border shadow-sm" style="max-width: 140px; max-height: 140px; object-fit: cover;">
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB 2: Gekaufte Filme (movies_access)                              -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-user-movies" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
          <div>
            <h2 class="h6 fw-bold mb-0">Gekaufte Filme &amp; Streams (`movies_access`)</h2>
            <small class="text-muted">Alle Film-Freischaltungen dieses Mitglieds über Shop #<?= (int)$user['p4c_shop_id'] ?>.</small>
          </div>
          <input type="text" class="form-control form-control-sm" style="width: 240px;" placeholder="Film-Käufe filtern..." oninput="App.Users.filterSubTable('user-mov-row', this.value)">
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th>Zugriff #</th>
                <th>Film &amp; Darsteller</th>
                <th class="text-center">Kaufart</th>
                <th class="text-end">Preis</th>
                <th class="text-end">Creator-Provision</th>
                <th>Gekauft am</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($moviePurchases)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">Keine Film-Käufe für diesen Cloud-User gefunden.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($moviePurchases as $mp): ?>
                  <tr class="user-mov-row" data-search="<?= View::e(mb_strtolower($mp['movie_title'] . ' ' . $mp['actor_name'] . ' ' . $mp['file_id'] . ' ' . $mp['buy_dt_de'], 'UTF-8')) ?>">
                    <td class="font-monospace small text-muted">#<?= (int)$mp['id'] ?></td>
                    <td>
                      <div class="fw-semibold small"><?= View::e($mp['movie_title'] !== '' ? (string)$mp['movie_title'] : ('Datei ' . (string)$mp['file_id'])) ?></div>
                      <?php if ($mp['actor_id'] > 0): ?>
                        <a href="#/actor?id=<?= (int)$mp['actor_id'] ?>" class="text-muted text-decoration-none" style="font-size: 0.75rem;">
                          <i class="bi bi-person me-1"></i><?= View::e((string)$mp['actor_name']) ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <?php if ($mp['buy_as'] === 'download'): ?>
                        <span class="badge bg-primary"><i class="bi bi-download me-1"></i>Download</span>
                      <?php else: ?>
                        <span class="badge bg-info text-dark"><i class="bi bi-play-circle me-1"></i>Stream</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace fw-bold small"><?= View::e((string)$mp['price_eur']) ?></td>
                    <td class="text-end font-monospace small text-muted">
                      <?= View::e((string)$mp['comm_eur']) ?> (<?= (int)$mp['comm_pct'] ?>%)
                    </td>
                    <td class="small"><?= View::e((string)$mp['buy_dt_de']) ?></td>
                    <td class="text-end">
                      <?php if ($mp['online_id'] > 0): ?>
                        <a href="#/movie-edit?id=<?= (int)$mp['online_id'] ?>" class="btn btn-xs btn-outline-kkc py-0.5 px-2 small">Film öffnen</a>
                      <?php else: ?>
                        <span class="text-muted small">-</span>
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
    <!-- TAB 3: Gekaufte Fotoalben (photo_albums_access)                    -->
    <!-- ================================================================== -->
    <div class="tab-pane fade" id="tab-user-albums" role="tabpanel">
      <div class="card-kkc overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--kkc-border) !important;">
          <div>
            <h2 class="h6 fw-bold mb-0">Gekaufte Fotoalben (`photo_albums_access`)</h2>
            <small class="text-muted">Alle Fotoalben-Freischaltungen dieses Mitglieds.</small>
          </div>
          <input type="text" class="form-control form-control-sm" style="width: 240px;" placeholder="Alben-Käufe filtern..." oninput="App.Users.filterSubTable('user-alb-row', this.value)">
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
              <tr>
                <th>Zugriff #</th>
                <th>Fotoalbum &amp; Darsteller</th>
                <th class="text-end">Preis</th>
                <th class="text-end">Creator-Provision</th>
                <th>Gekauft am</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($albumPurchases)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">Keine Fotoalben-Käufe für diesen Cloud-User gefunden.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($albumPurchases as $ap): ?>
                  <tr class="user-alb-row" data-search="<?= View::e(mb_strtolower($ap['album_title'] . ' ' . $ap['actor_name'] . ' ' . $ap['file_id'] . ' ' . $ap['buy_dt_de'], 'UTF-8')) ?>">
                    <td class="font-monospace small text-muted">#<?= (int)$ap['id'] ?></td>
                    <td>
                      <div class="fw-semibold small"><?= View::e($ap['album_title'] !== '' ? (string)$ap['album_title'] : ('Album ' . (string)$ap['file_id'])) ?></div>
                      <?php if ($ap['actor_id'] > 0): ?>
                        <a href="#/actor?id=<?= (int)$ap['actor_id'] ?>" class="text-muted text-decoration-none" style="font-size: 0.75rem;">
                          <i class="bi bi-person me-1"></i><?= View::e((string)$ap['actor_name']) ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace fw-bold small"><?= View::e((string)$ap['price_eur']) ?></td>
                    <td class="text-end font-monospace small text-muted">
                      <?= View::e((string)$ap['comm_eur']) ?> (<?= (int)$ap['comm_pct'] ?>%)
                    </td>
                    <td class="small"><?= View::e((string)$ap['buy_dt_de']) ?></td>
                    <td class="text-end">
                      <?php if ($ap['online_id'] > 0): ?>
                        <a href="#/photo-album-edit?id=<?= (int)$ap['online_id'] ?>" class="btn btn-xs btn-outline-kkc py-0.5 px-2 small">Album öffnen</a>
                      <?php else: ?>
                        <span class="text-muted small">-</span>
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

  </div>
</div>
