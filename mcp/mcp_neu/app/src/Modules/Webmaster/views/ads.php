<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var string $partnerId
 * @var string $currentMonth
 * @var string $activeTab
 * @var string $filterDomain
 * @var list<array<string, mixed>> $websites
 * @var list<array<string, mixed>> $campaigns
 * @var bool $hasOwnSites
 * @var list<array<string, mixed>> $ownSites
 * @var list<array<string, mixed>> $ownCustomers
 * @var array<string, array{width: int, height: int, label: string, group: string}> $standardBannerSizes
 */

// Standardgrößen nach Gruppen strukturieren
$groupedSizes = [];
foreach ($standardBannerSizes as $sizeKey => $info) {
    if ($sizeKey === '160x650') {
        continue; // Alias für Legacy-Kompatibilität nicht doppelt in der Tabelle listen
    }
    $groupedSizes[$info['group']][$sizeKey] = $info['label'];
}
?>
<div class="container-fluid py-3" id="mcp-webmaster-ads-page">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <span>Partnerprogramm</span>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold">Kunden werben</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-bullseye ec-text-primary"></i>
        <span>Kunden werben</span>
      </h1>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="#/webmaster-newads" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-images me-1"></i>Alle Banner
      </a>
      <a href="#/webmaster-stats" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-bar-chart-line me-1"></i>Statistiken
      </a>
      <a href="#/webmaster-advertise-partner" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-people me-1"></i>Darsteller werben
      </a>
    </div>
  </div>

  <!-- Haupt-Card mit Reitern (Tabs) -->
  <div class="kkc-card overflow-hidden">
    <div class="card-header bg-transparent border-bottom px-3 pt-3 pb-0">
      <ul class="nav nav-tabs border-bottom-0 gap-1" id="webmasterAdsTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'websites' ? 'active' : '' ?> d-flex align-items-center gap-2 fw-semibold"
                  id="tab-btn-websites"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-websites"
                  type="button"
                  role="tab">
            <i class="bi bi-globe2"></i>
            <span>Websites</span>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border"><?= count($websites) ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'campaigns' ? 'active' : '' ?> d-flex align-items-center gap-2 fw-semibold"
                  id="tab-btn-campaigns"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-campaigns"
                  type="button"
                  role="tab">
            <i class="bi bi-megaphone"></i>
            <span>Kampagnen</span>
            <span class="badge rounded-pill bg-primary-subtle ec-text-primary border border-primary-subtle"><?= count($campaigns) ?></span>
          </button>
        </li>
        <?php if ($hasOwnSites): ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'my-banners' ? 'active' : '' ?> d-flex align-items-center gap-2 fw-semibold"
                  id="tab-btn-my-banners"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-my-banners"
                  type="button"
                  role="tab">
            <i class="bi bi-palette"></i>
            <span>Eigene Banner verwalten</span>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border"><?= count($ownSites) ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'customers' ? 'active' : '' ?> d-flex align-items-center gap-2 fw-semibold"
                  id="tab-btn-customers"
                  data-bs-toggle="tab"
                  data-bs-target="#pane-customers"
                  type="button"
                  role="tab">
            <i class="bi bi-person-check"></i>
            <span>Deine Kunden</span>
            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle"><?= count($ownCustomers) ?></span>
          </button>
        </li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="tab-content p-3 p-lg-4" id="webmasterAdsTabContent">

      <!-- ================================================================== -->
      <!-- TAB 1: Websites                                                    -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'websites' ? 'show active' : '' ?>" id="pane-websites" role="tabpanel">
        <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
          <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
          <div>
            <div>In dieser Liste findest du alle Websites, die du bewerben kannst.</div>
            <?php if ($hasOwnSites): ?>
              <div class="small mt-1">Nimmt deine eigene Website am Partnerprogramm teil, wird sie anderen Webmastern hier ebenfalls aufgelistet und ist für dich farblich hervorgehoben.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div class="input-group input-group-sm" style="max-width: 340px;">
            <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
            <input type="search"
                   id="wm-websites-search"
                   class="form-control"
                   placeholder="Website / Domain filtern..."
                   value="<?= View::e($filterDomain) ?>">
          </div>
          <span class="text-muted small">Anzahl Websites: <strong><?= count($websites) ?></strong></span>
        </div>

        <?php if (empty($websites)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-globe2 fs-1 d-block mb-2 opacity-50"></i>
            Aktuell stehen keine Websites im Partnerprogramm zur Verfügung.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="wm-websites-table">
              <thead class="table-light">
                <tr>
                  <th>Domain</th>
                  <th class="text-center" style="width: 110px;">Filme</th>
                  <th class="text-center" style="width: 110px;">Fotoalben</th>
                  <th class="text-center" style="width: 110px;">Artikel</th>
                  <th class="text-center" style="width: 150px;">Deine Provision</th>
                  <th class="text-end" style="width: 320px;">Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($websites as $site): ?>
                  <tr data-domain="<?= View::e(strtolower((string)$site['domain'])) ?>"
                      class="<?= !empty($site['is_own']) ? 'table-primary bg-opacity-10' : '' ?>">
                    <td>
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="https://<?= View::e($site['domain']) ?>"
                           target="_blank"
                           rel="noopener"
                           class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1">
                          <span><?= View::e($site['domain']) ?></span>
                          <i class="bi bi-box-arrow-up-right small text-muted"></i>
                        </a>
                        <?php if (!empty($site['is_own'])): ?>
                          <span class="badge bg-primary-subtle ec-text-primary border border-primary-subtle">Deine Website</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="text-center"><?= number_format((int)$site['number_of_movies'], 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format((int)$site['number_of_photoalbums'], 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format((int)$site['number_of_articles'], 0, ',', '.') ?></td>
                    <td class="text-center">
                      <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-2 py-1">
                        <?= (int)$site['webmaster_commision'] ?> %
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                        <a href="#/webmaster-ads-domain?domain=<?= urlencode((string)$site['domain']) ?>"
                           class="btn btn-sm btn-outline-secondary">
                          <i class="bi bi-code-slash me-1"></i>Werbemittel
                        </a>
                        <a href="#/webmaster-new-campaign?sid=<?= (int)$site['id'] ?>"
                           class="btn btn-sm btn-primary">
                          <i class="bi bi-plus-lg me-1"></i>Kampagne erstellen
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 2: Kampagnen                                                   -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'campaigns' ? 'show active' : '' ?>" id="pane-campaigns" role="tabpanel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div class="input-group input-group-sm" style="max-width: 340px;">
            <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
            <input type="search"
                   id="wm-campaigns-search"
                   class="form-control"
                   placeholder="Kampagne, ID oder Website suchen...">
          </div>
          <button type="button"
                  class="btn btn-sm btn-primary"
                  id="btn-switch-to-websites-tab">
            <i class="bi bi-plus-lg me-1"></i>Neue Kampagne erstellen
          </button>
        </div>

        <?php if (empty($campaigns)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-megaphone fs-1 d-block mb-2 opacity-50"></i>
            <p class="mb-3">Du hast noch keine Kampagne erstellt.</p>
            <button type="button" class="btn btn-primary btn-sm" id="btn-empty-create-campaign">
              <i class="bi bi-globe2 me-1"></i>Website auswählen & Kampagne starten
            </button>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="wm-campaigns-table">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th>Website</th>
                  <th class="text-center" style="width: 130px;">Kampagnen-ID</th>
                  <th class="text-center" style="width: 170px;">Impressionen (<?= View::e($currentMonth) ?>)</th>
                  <th class="text-center" style="width: 140px;">Klicks (gesamt)</th>
                  <th class="text-center" style="width: 160px;">Conversions (gesamt)</th>
                  <th class="text-center" style="width: 165px;">Angelegt am</th>
                  <th class="text-end" style="width: 180px;">Aktion</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($campaigns as $camp): ?>
                  <?php
                    $searchHaystack = strtolower($camp['name'] . ' ' . $camp['campaign_id'] . ' ' . $camp['domain'] . ' ' . $camp['description']);
                  ?>
                  <tr data-search="<?= View::e($searchHaystack) ?>">
                    <td>
                      <a href="#/webmaster-edit-campaign?cid=<?= urlencode((string)$camp['campaign_id']) ?>"
                         class="fw-semibold text-decoration-none">
                        <?= View::e($camp['name']) ?>
                      </a>
                      <?php if (!empty($camp['description'])): ?>
                        <div class="small text-muted text-truncate" style="max-width: 260px;" title="<?= View::e($camp['description']) ?>">
                          <?= View::e($camp['description']) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($camp['site_active']): ?>
                        <span><?= View::e($camp['domain']) ?></span>
                      <?php else: ?>
                        <span class="text-danger text-decoration-line-through"
                              title="Diese Website nimmt aktuell nicht mehr am Partnerprogramm teil.">
                          <?= View::e($camp['domain']) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <code class="px-2 py-1 rounded bg-body-tertiary border"><?= View::e($camp['campaign_id']) ?></code>
                    </td>
                    <td class="text-center"><?= number_format((int)$camp['impressions_month'], 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format((int)$camp['count_clicks'], 0, ',', '.') ?></td>
                    <td class="text-center">
                      <?php if ((int)$camp['conversions_total'] > 0): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                          <?= number_format((int)$camp['conversions_total'], 0, ',', '.') ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">0</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center small text-muted"><?= View::e($camp['create_date_time']) ?></td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a href="#/webmaster-edit-campaign?cid=<?= urlencode((string)$camp['campaign_id']) ?>&tab=2"
                           class="btn btn-outline-secondary"
                           title="Werbemittel & Bannercode">
                          <i class="bi bi-code-slash me-1"></i>Werbemittel
                        </a>
                        <a href="#/webmaster-edit-campaign?cid=<?= urlencode((string)$camp['campaign_id']) ?>"
                           class="btn btn-outline-primary"
                           title="Kampagne bearbeiten & Statistiken">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($hasOwnSites): ?>
      <!-- ================================================================== -->
      <!-- TAB 3: Eigene Banner verwalten                                     -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'my-banners' ? 'show active' : '' ?>" id="pane-my-banners" role="tabpanel">
        <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
          <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
          <div>
            Damit deine Website optimal von anderen Webmastern beworben werden kann, solltest du hier Werbebanner in den gängigen Standardgrößen für deine Website hinterlegen.
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 90px;" class="text-center">Website-ID</th>
                <th>Domain</th>
                <th style="width: 240px;" class="text-center">Hinterlegte Banner</th>
                <th style="width: 240px;" class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ownSites as $os): ?>
                <tr>
                  <td class="text-center text-muted">#<?= (int)$os['id'] ?></td>
                  <td class="fw-semibold"><?= View::e($os['domain']) ?></td>
                  <td class="text-center">
                    <span class="badge bg-secondary-subtle text-secondary border me-1">
                      <?= (int)$os['banner_count'] ?> gesamt
                    </span>
                    <?php if ((int)$os['pending_count'] > 0): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle me-1">
                        <?= (int)$os['pending_count'] ?> in Prüfung
                      </span>
                    <?php endif; ?>
                    <?php if ((int)$os['rejected_count'] > 0): ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <?= (int)$os['rejected_count'] ?> abgelehnt
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button type="button"
                            class="btn btn-sm btn-primary btn-open-my-banners-modal"
                            data-site-id="<?= (int)$os['id'] ?>"
                            data-domain="<?= View::e($os['domain']) ?>">
                      <i class="bi bi-images me-1"></i>Banner hinzufügen & bearbeiten
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ================================================================== -->
      <!-- TAB 4: Deine Kunden                                                -->
      <!-- ================================================================== -->
      <div class="tab-pane fade <?= $activeTab === 'customers' ? 'show active' : '' ?>" id="pane-customers" role="tabpanel">
        <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
          <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
          <div>
            Hier werden alle Conversions (Registrierungen und Buchungen) zu deinen eigenen Websites aufgelistet.
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div class="input-group input-group-sm" style="max-width: 340px;">
            <span class="input-group-text bg-transparent"><i class="bi bi-search text-muted"></i></span>
            <input type="search"
                   id="wm-customers-search"
                   class="form-control"
                   placeholder="Website, User oder Webmaster filtern...">
          </div>
          <span class="text-muted small">Einträge gesamt: <strong><?= count($ownCustomers) ?></strong></span>
        </div>

        <?php if (empty($ownCustomers)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-person-check fs-1 d-block mb-2 opacity-50"></i>
            Es liegen noch keine Conversions zu deinen eigenen Websites vor.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="wm-customers-table">
              <thead class="table-light">
                <tr>
                  <th>Website</th>
                  <th class="text-center" style="width: 170px;">Zeitpunkt</th>
                  <th>User</th>
                  <th class="text-center" style="width: 190px;">Status</th>
                  <th class="text-end" style="width: 150px;">Betrag</th>
                  <th class="text-center" style="width: 180px;">Webmaster</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ownCustomers as $cust): ?>
                  <?php
                    $custSearch = strtolower($cust['domain'] . ' ' . $cust['username'] . ' ' . $cust['status_label'] . ' ' . $cust['wmid']);
                  ?>
                  <tr data-search="<?= View::e($custSearch) ?>">
                    <td class="fw-semibold"><?= View::e($cust['domain']) ?></td>
                    <td class="text-center small text-muted"><?= View::e($cust['date_time']) ?></td>
                    <td><?= View::e($cust['username']) ?></td>
                    <td class="text-center">
                      <span class="badge border <?= View::e($cust['status_badge_class']) ?>">
                        <?= View::e($cust['status_label']) ?>
                      </span>
                    </td>
                    <td class="text-end <?= View::e($cust['amount_class']) ?>">
                      <?= View::e($cust['amount_formatted']) ?>
                    </td>
                    <td class="text-center">
                      <?php if ($cust['is_self_referred']): ?>
                        <span class="badge bg-primary-subtle ec-text-primary border border-primary-subtle">von dir geworben</span>
                      <?php else: ?>
                        <code><?= View::e($cust['wmid']) ?></code>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php if ($hasOwnSites): ?>
<!-- ======================================================================== -->
<!-- MODAL: Eigene Banner hinzufügen und bearbeiten                           -->
<!-- ======================================================================== -->
<div class="modal fade" id="modal-wm-my-banners" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0">
            <i class="bi bi-images ec-text-primary me-2"></i>Banner hinzufügen und bearbeiten
          </h5>
          <div class="small text-muted mt-1">
            Website: <strong id="wm-modal-site-domain">—</strong>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>

      <div class="modal-body">
        <!-- Hinweis & In-Picture.de Grafikservice + erlaubte Standardgrößen -->
        <div class="accordion mb-4" id="accordionBannerGuidelines">
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed py-2 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBannerSizes">
                <i class="bi bi-aspect-ratio me-2 ec-text-primary"></i>Erlaubte Standard-Bannergrößen & Grafikservice anzeigen
              </button>
            </h2>
            <div id="collapseBannerSizes" class="accordion-collapse collapse" data-bs-parent="#accordionBannerGuidelines">
              <div class="accordion-body">
                <p class="small text-muted mb-3">
                  Es sind ausschließlich Banner, Skyscraper und Buttons in den bekanntesten Standardgrößen erlaubt
                  (<a href="https://de.wikipedia.org/wiki/Werbebanner#Standardgr%C3%B6%C3%9Fen" target="_blank" rel="noopener">Wikipedia: Standard-Bannergrößen</a>).
                </p>

                <div class="alert alert-secondary small mb-3">
                  <div class="fw-bold mb-1"><i class="bi bi-brush me-1"></i>Bannerset erstellen lassen</div>
                  Über unseren Partner <strong>In-Picture.de</strong> kannst du dir ein komplettes Bannerset mit <strong>6 Bannern für nur 150,00 EUR</strong> erstellen lassen (statisch, einheitliches Design, Sonderwünsche möglich).<br>
                  Kontaktiere deinen Grafiker Rico einfach per E-Mail an <a href="mailto:kontakt@in-picture.de">kontakt@in-picture.de</a>
                  oder direkt per WhatsApp unter <a href="https://wa.me/+4917660406280" target="_blank" rel="noopener" id="wm-whatsapp-inpicture">+49 (0)176 604 062 80</a>.
                </div>

                <div class="row g-3">
                  <?php foreach ($groupedSizes as $groupTitle => $sizesMap): ?>
                    <div class="col-md-4">
                      <div class="border rounded p-2 h-100">
                        <div class="fw-semibold small border-bottom pb-1 mb-2"><?= View::e($groupTitle) ?></div>
                        <table class="table table-sm table-borderless small mb-0">
                          <tbody>
                            <?php foreach ($sizesMap as $dim => $lbl): ?>
                              <tr>
                                <td class="font-monospace fw-semibold" style="width: 95px;"><?= View::e(str_replace('x', ' x ', $dim)) ?></td>
                                <td class="text-muted"><?= View::e($lbl) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Neue Banner hochladen -->
        <div class="kkc-card p-3 mb-4 border">
          <h6 class="fw-bold mb-2">
            <i class="bi bi-cloud-arrow-up ec-text-primary me-1"></i>Neue Banner hochladen
          </h6>
          <div class="small text-muted mb-3">
            Erlaubte Dateiformate: <strong>JPG, PNG, GIF</strong> &bull; Maximale Dateigröße: <strong>5 MB</strong> &bull;
            <strong>Bitte beachte:</strong> Wir prüfen jedes neue Banner, bevor es anderen Webmastern zur Verfügung steht.
          </div>

          <form id="form-wm-upload-banners" enctype="multipart/form-data">
            <input type="hidden" name="site_id" id="wm-upload-site-id" value="">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <input type="file"
                     class="form-control form-control-sm"
                     id="wm-upload-files-input"
                     name="banners[]"
                     multiple
                     accept="image/png,image/jpeg,image/gif"
                     style="max-width: 420px;"
                     required>
              <button type="submit" class="btn btn-sm btn-primary" id="btn-wm-upload-submit">
                <i class="bi bi-upload me-1"></i>Ausgewählte Banner hochladen
              </button>
            </div>
          </form>
        </div>

        <!-- Bestehende Banner der Website -->
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="fw-bold mb-0">Vorhandene Banner dieser Website</h6>
          <span class="small text-muted">Für eine bessere Übersicht werden große Banner verkleinert dargestellt.</span>
        </div>

        <div id="wm-my-banners-list-container" class="py-3">
          <div class="text-center text-muted py-4">
            <div class="spinner-border spinner-border-sm ec-text-primary me-2"></div>Banner werden geladen...
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
