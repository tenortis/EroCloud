<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<int, array<string, mixed>> $pendingBanners
 * @var int                              $totalBannersCount
 */
?>
<div class="container-fluid px-0">

    <!-- 1. SEITEN-HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
        <div>
            <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-stars ec-text-primary"></i>
                <span>Neue Banner prüfen</span>
            </h1>
            <p class="text-muted small mb-0">
                Prüfe neu hochgeladene oder ausgetauschte Werbebanner von Seitenbetreibern und schalte sie frei oder lehne sie ab.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-kkc" onclick="App.handleRoute()">
                <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
            </button>
        </div>
    </div>

    <!-- 2. STATUS-HINWEIS -->
    <div class="card-kkc mb-4">
        <div class="card-kkc-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle <?= count($pendingBanners) > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-success bg-opacity-10 text-success' ?> d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                    <i class="bi <?= count($pendingBanners) > 0 ? 'bi-hourglass-split' : 'bi-check2-all' ?> fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold">
                        <?= number_format(count($pendingBanners), 0, ',', '.') ?>
                        <?= count($pendingBanners) === 1 ? 'Banner wartet' : 'Banner warten' ?> auf deine Prüfung
                    </div>
                    <div class="text-muted small">
                        Beim Freischalten wird das bisherige Banner durch die neue Datei ersetzt und sofort für alle Webmaster sichtbar.
                    </div>
                </div>
            </div>
            <?php if (empty($pendingBanners)): ?>
                <span class="badge bg-success py-2 px-3">
                    <i class="bi bi-check-circle me-1"></i>Alle neuen Banner sind abgearbeitet
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. PRÜF-KARTEN -->
    <?php if (empty($pendingBanners)): ?>
        <div class="card-kkc">
            <div class="card-kkc-body p-5 text-center">
                <i class="bi bi-check2-circle text-success d-block mb-2" style="font-size:2.5rem;"></i>
                <h5 class="fw-bold mb-1">Keine offenen Banner-Prüfungen vorhanden</h5>
                <p class="text-muted small mb-3">
                    Aktuell liegen keine neu hochgeladenen Werbebanner zur Freischaltung vor.
                </p>
                <a href="#/banners" class="btn btn-sm btn-primary">
                    <i class="bi bi-images me-1"></i>Zur Banner-Übersicht wechseln
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($pendingBanners as $ban): ?>
                <?php
                $fileId        = (string)$ban['file_id'];
                $w             = (int)$ban['width'];
                $h             = (int)$ban['height'];
                $oldPreviewUrl = 'media.php?type=banner&file_id=' . rawurlencode($fileId);
                $newPreviewUrl = 'media.php?type=banner&file_id=' . rawurlencode($fileId) . '&variant=new';
                ?>
                <div id="pending-banner-card-<?= View::e($fileId) ?>" class="card-kkc">
                    <div class="card-kkc-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <a href="#/site?id=<?= (int)$ban['site_id'] ?>" class="text-white fw-bold text-decoration-none">
                                <i class="bi bi-globe2 me-1"></i><?= View::e((string)$ban['domain']) ?>
                            </a>
                            <span class="badge bg-light text-dark"><?= View::e((string)$ban['type']) ?></span>
                            <span class="badge bg-light text-dark"><?= $w ?> × <?= $h ?> px</span>
                            <span class="badge bg-dark bg-opacity-50 font-monospace">ID: <?= View::e($fileId) ?></span>
                        </div>
                        <div class="small fw-normal opacity-75">
                            Hochgeladen: <strong><?= View::e((string)$ban['upload_datetime']) ?></strong> · Partner: <strong><?= View::e((string)$ban['partner_id']) ?></strong>
                        </div>
                    </div>

                    <div class="card-kkc-body">
                        <div class="row g-3 mb-3">
                            <!-- Aktuelles Banner -->
                            <div class="col-12 col-lg-6">
                                <div class="p-3 rounded border bg-light h-100">
                                    <div class="small fw-bold text-uppercase text-muted mb-2">
                                        <i class="bi bi-image me-1"></i>Aktuelles Banner (Bestand)
                                    </div>
                                    <?php if (!empty($ban['old_exists'])): ?>
                                        <a href="<?= View::e($oldPreviewUrl) ?>" target="_blank" rel="noopener noreferrer" class="d-inline-block">
                                            <img src="<?= View::e($oldPreviewUrl) ?>"
                                                 alt="Aktuelles Banner <?= View::e($fileId) ?>"
                                                 class="rounded border bg-white"
                                                 style="max-width:100%; max-height:240px; display:block;">
                                        </a>
                                    <?php else: ?>
                                        <div class="p-4 text-center text-muted small border rounded bg-white">
                                            Kein bisheriges Banner vorhanden (Neu-Upload)
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted mt-2" style="font-size:0.75rem;">
                                        Datei: <code><?= View::e((string)($ban['filename'] !== '' ? $ban['filename'] : '—')) ?></code>
                                    </div>
                                </div>
                            </div>

                            <!-- Neues Banner -->
                            <div class="col-12 col-lg-6">
                                <div class="p-3 rounded border border-primary border-opacity-50 bg-primary bg-opacity-10 h-100">
                                    <div class="small fw-bold text-uppercase text-primary mb-2">
                                        <i class="bi bi-stars me-1"></i>Neues Banner (Zur Freigabe)
                                    </div>
                                    <?php if (!empty($ban['new_exists'])): ?>
                                        <a href="<?= View::e($newPreviewUrl) ?>" target="_blank" rel="noopener noreferrer" class="d-inline-block">
                                            <img src="<?= View::e($newPreviewUrl) ?>"
                                                 alt="Neues Banner <?= View::e($fileId) ?>"
                                                 class="rounded border border-primary bg-white"
                                                 style="max-width:100%; max-height:240px; display:block;">
                                        </a>
                                    <?php else: ?>
                                        <div class="p-4 text-center text-danger small border border-danger rounded bg-white">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Neue Banner-Datei nicht auf dem Dateisystem gefunden
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-primary mt-2" style="font-size:0.75rem;">
                                        Neue Datei: <code><?= View::e((string)$ban['new_filename']) ?></code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-2 pt-2 border-top">
                            <button type="button"
                                    class="btn btn-sm btn-success fw-semibold"
                                    onclick="App.Affiliate.handleBannerAction('<?= View::e($fileId) ?>', 'unlock', true)"
                                    title="Das Banner wird für alle Webmaster sichtbar.">
                                <i class="bi bi-check-lg me-1"></i>Freischalten
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-warning fw-semibold"
                                    onclick="App.Affiliate.handleBannerAction('<?= View::e($fileId) ?>', 'reject', true)"
                                    title="Das Banner wird abgelehnt. Der Seitenbetreiber bekommt eine Info. Das Banner ist für keinen Webmaster sichtbar.">
                                <i class="bi bi-x-circle me-1"></i>Ablehnen
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="App.Affiliate.handleBannerAction('<?= View::e($fileId) ?>', 'delete', true)"
                                    title="Das Banner wird sofort gelöscht.">
                                <i class="bi bi-trash me-1"></i>Löschen
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
