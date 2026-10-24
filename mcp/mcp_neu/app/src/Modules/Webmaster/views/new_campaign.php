<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * @var int $siteId
 * @var string $domain
 * @var int $commision
 * @var string $cid
 */
?>
<div class="container-fluid py-3" id="mcp-webmaster-new-campaign-page">

  <!-- Seitenkopf -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 text-muted small mb-1">
        <a href="#/webmaster-ads?tab=websites" class="text-decoration-none text-muted">Kunden werben</a>
        <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
        <span class="ec-text-primary fw-semibold">Neue Kampagne erstellen</span>
      </div>
      <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-plus-circle ec-text-primary"></i>
        <span>Neue Kampagne erstellen</span>
      </h1>
    </div>

    <a href="#/webmaster-ads?tab=websites" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Abbrechen & zurück
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="kkc-card p-4">

        <!-- Eckdaten zur gewählten Website -->
        <div class="row g-3 mb-4 pb-3 border-bottom">
          <div class="col-sm-4">
            <div class="small text-muted">Beworbene Website</div>
            <div class="fw-bold fs-6"><?= View::e($domain) ?></div>
          </div>
          <div class="col-sm-4">
            <div class="small text-muted">Kampagnen-ID (CID)</div>
            <div><code class="px-2 py-1 rounded bg-body-tertiary border fw-bold"><?= View::e($cid) ?></code></div>
          </div>
          <div class="col-sm-4">
            <div class="small text-muted">Deine Provision</div>
            <div class="fw-bold text-success fs-6"><?= (int)$commision ?> %</div>
          </div>
        </div>

        <form id="form-wm-create-campaign">
          <input type="hidden" name="sid" value="<?= (int)$siteId ?>">
          <input type="hidden" name="cid" value="<?= View::e($cid) ?>">

          <div class="mb-3">
            <label for="wm-cname" class="form-label fw-semibold">Name der Kampagne</label>
            <input type="text"
                   class="form-control"
                   id="wm-cname"
                   name="cname"
                   maxlength="120"
                   placeholder="z. B. Header-Banner Hauptseite oder Blog-Sidebar"
                   autofocus>
            <div class="form-text">
              Wenn du keinen eigenen Namen vergibst, wird automatisch die Kampagnen-ID (<code><?= View::e($cid) ?></code>) als Name verwendet.
            </div>
          </div>

          <div class="mb-4">
            <label for="wm-cdescription" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control"
                      id="wm-cdescription"
                      name="cdescription"
                      rows="4"
                      placeholder="Notizen zu deiner Kampagne, Platzierung oder Zielgruppe..."></textarea>
          </div>

          <div class="d-flex align-items-center justify-content-end gap-2">
            <a href="#/webmaster-ads?tab=websites" class="btn btn-outline-secondary">Abbrechen</a>
            <button type="submit" class="btn btn-primary" id="btn-wm-create-campaign-submit">
              <i class="bi bi-check-lg me-1"></i>Kampagne anlegen & zu den Werbemitteln
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>

</div>
