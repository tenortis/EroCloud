<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}
?>
<div class="container-fluid py-4">
  <div class="kkc-card p-4 p-lg-5 text-center mx-auto" style="max-width: 680px;">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
         style="width: 64px; height: 64px; background: rgba(2, 132, 199, 0.12); color: var(--kkc-accent);">
      <i class="bi bi-megaphone fs-2"></i>
    </div>
    <h1 class="h4 fw-bold mb-2">Partnerprogramm</h1>
    <p class="text-muted mb-4">
      Das Partnerprogramm ist für deinen Account (<code><?= View::e($partnerId ?? '') ?></code>) aktuell nicht freigeschaltet.
      Wenn du Fragen dazu hast, wende dich gern an unseren Support.
    </p>
    <a href="#/startseite" class="btn btn-primary">
      <i class="bi bi-arrow-left me-1"></i>Zurück zur Startseite
    </a>
  </div>
</div>
