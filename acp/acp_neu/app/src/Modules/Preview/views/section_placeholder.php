<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var string $icon */
/** @var string $title */
/** @var string $noticeTitle */
/** @var string $noticeText */
/** @var string $futureText */
?>
<!-- Schlichter Hinweis ohne Untermenüs oder Aktions-Elemente -->
<div class="mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
  <h1 class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
    <i class="bi <?= View::e($icon) ?> ec-text-primary"></i>
    <span><?= View::e($title) ?></span>
  </h1>
</div>

<div class="card-kkc" style="max-width: 760px;">
  <div class="card-kkc-body p-4">
    <div class="d-flex align-items-start gap-3">
      <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.35rem;">
        <i class="bi bi-info-circle-fill"></i>
      </div>
      <div>
        <h5 class="fw-bold mb-2"><?= View::e($noticeTitle) ?></h5>
        <p class="text-body mb-2">
          <?= View::e($noticeText) ?>
        </p>
        <p class="text-muted mb-0">
          <?= View::e($futureText) ?>
        </p>
      </div>
    </div>
  </div>
</div>
