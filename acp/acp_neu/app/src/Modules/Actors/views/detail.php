<?php
declare(strict_types=1);

use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * @var array<string, mixed> $actor
 * @var array<string, mixed> $defs
 * @var array<string, array<int, array<string, string>>> $categoriesByGroup
 * @var array<string, mixed>|null $pendingReassign
 * @var array<int, array<string, mixed>> $completedReassign
 * @var array<int, array<string, mixed>> $allMerchants
 * @var array<int, array<string, mixed>> $actorMovies
 * @var array<int, array<string, mixed>> $actorAlbums
 * @var int $prevId
 * @var int $nextId
 */

$aid       = (int)$actor['id'];
$fsk16File = (string)$actor['profile_image_fsk16'];
$fsk18File = (string)$actor['profile_image_fsk18'];
$fsk16Url  = 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=16';
$fsk18Url  = 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=18';
$activeCats = (array)$actor['actor_categories'];

$statusBadge = match ($actor['status']) {
    'active'   => '<span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Aktiv</span>',
    'inactive' => '<span class="badge bg-secondary fs-6"><i class="bi bi-pause-circle me-1"></i>Inaktiv</span>',
    'blocked'  => '<span class="badge bg-danger fs-6"><i class="bi bi-slash-circle me-1"></i>Gesperrt</span>',
    'deleted'  => '<span class="badge bg-dark fs-6"><i class="bi bi-trash3 me-1"></i>Gelöscht</span>',
    default    => '<span class="badge bg-secondary fs-6">' . View::e((string)$actor['status']) . '</span>',
};
?>

<!-- Kopfbereich & Schnell-Navigation -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="#/actors" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Alle Profile
        </a>
        <h4 class="fw-bold mb-0">
            Profil <span class="text-primary"><?= View::e((string)$actor['username']) ?></span>
            <small class="text-muted font-monospace">#<?= $aid ?></small>
        </h4>
        <?= $statusBadge ?>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php if ($prevId > 0): ?>
            <a href="#/actor?id=<?= $prevId ?>" class="btn btn-sm btn-outline-secondary" title="Nächsthöhere Profil-ID (#<?= $prevId ?>)">
                <i class="bi bi-chevron-left"></i> Vorheriges (#<?= $prevId ?>)
            </a>
        <?php endif; ?>
        <?php if ($nextId > 0): ?>
            <a href="#/actor?id=<?= $nextId ?>" class="btn btn-sm btn-outline-secondary" title="Nächstniedrigere Profil-ID (#<?= $nextId ?>)">
                Nächstes (#<?= $nextId ?>) <i class="bi bi-chevron-right"></i>
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="document.getElementById('btn-actor-save-submit')?.click()">
            <i class="bi bi-floppy-fill me-1"></i>Profildaten speichern
        </button>
    </div>
</div>

<!-- 3-Tab Hauptkarte -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3 pb-0 px-3">
        <ul class="nav nav-tabs card-header-tabs" id="actorDetailTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="tab-btn-act-profile" data-bs-toggle="tab" data-bs-target="#tab-act-profile" type="button" role="tab">
                    <i class="bi bi-person-vcard me-1"></i>1. Profil-Daten &amp; Kategorisierung
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-act-movies" data-bs-toggle="tab" data-bs-target="#tab-act-movies" type="button" role="tab">
                    <i class="bi bi-film me-1"></i>2. Zugeordnete Videos
                    <span class="badge bg-primary ms-1"><?= count($actorMovies) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="tab-btn-act-albums" data-bs-toggle="tab" data-bs-target="#tab-act-albums" type="button" role="tab">
                    <i class="bi bi-images me-1"></i>3. Zugeordnete Fotoalben
                    <span class="badge bg-info text-dark ms-1"><?= count($actorAlbums) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-3 p-lg-4">
        <div class="tab-content" id="actorDetailTabContent">

            <!-- ============================================================ -->
            <!-- TAB 1: PROFIL-DATEN, KATEGORIEN & BESITZERWECHSEL            -->
            <!-- ============================================================ -->
            <div class="tab-pane fade show active" id="tab-act-profile" role="tabpanel">
                <form id="actorEditForm" onsubmit="return App.Actors.saveActor(event);">
                    <input type="hidden" name="actor_id" value="<?= $aid ?>">

                    <div class="row g-4">
                        <!-- LINKE SPALTE: PROFILNAME, BILDER & STECKBRIEF -->
                        <div class="col-12 col-xl-7">

                            <!-- 1.1 Profilname & Profilbilder (FSK16 / FSK18) -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-person-bounding-box text-primary me-1"></i>Profilname &amp; Profilbilder (FSK16 / FSK18)
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold mb-1">Profilname (Username):</label>
                                        <input type="text" class="form-control fw-bold" name="username" id="act-input-username" value="<?= View::e((string)$actor['username']) ?>" required>
                                        <div class="form-text small">Erlaubt sind Buchstaben, Zahlen, Bindestrich, Unterstrich und Punkt (Umlaute werden automatisch umgewandelt).</div>
                                    </div>

                                    <div class="row g-3">
                                        <!-- FSK16 Bild -->
                                        <div class="col-12 col-sm-6">
                                            <div class="border rounded p-3 text-center bg-light h-100 d-flex flex-column justify-content-between">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="badge bg-success">FSK16 (Jugendschutz-konform)</span>
                                                        <?php if ($fsk16File !== ''): ?>
                                                            <code class="small text-muted text-truncate" style="max-width:130px;" title="<?= View::e($fsk16File) ?>"><?= View::e($fsk16File) ?></code>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Kein Bild</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-2" style="cursor:pointer;" onclick="App.openImageLightbox('<?= View::e($fsk16Url) ?>', '<?= View::e($actor['username'] . ' – FSK16 Profilbild') ?>')">
                                                        <img src="<?= View::e($fsk16Url) ?>" alt="FSK16" class="img-fluid rounded border shadow-sm" style="max-height:220px;object-fit:cover;">
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($fsk16File !== ''): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="App.Actors.deleteProfileImage(<?= $aid ?>, 'FSK16')">
                                                            <i class="bi bi-trash3 me-1"></i>FSK16-Profilbild löschen
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Vom Partner noch kein FSK16-Bild hinterlegt</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- FSK18 Bild -->
                                        <div class="col-12 col-sm-6">
                                            <div class="border rounded p-3 text-center bg-light h-100 d-flex flex-column justify-content-between">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="badge bg-danger">FSK18 (Erwachsenen-Ansicht)</span>
                                                        <?php if ($fsk18File !== ''): ?>
                                                            <code class="small text-muted text-truncate" style="max-width:130px;" title="<?= View::e($fsk18File) ?>"><?= View::e($fsk18File) ?></code>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Kein Bild</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-2" style="cursor:pointer;" onclick="App.openImageLightbox('<?= View::e($fsk18Url) ?>', '<?= View::e($actor['username'] . ' – FSK18 Profilbild') ?>')">
                                                        <img src="<?= View::e($fsk18Url) ?>" alt="FSK18" class="img-fluid rounded border shadow-sm" style="max-height:220px;object-fit:cover;">
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($fsk18File !== ''): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="App.Actors.deleteProfileImage(<?= $aid ?>, 'FSK18')">
                                                            <i class="bi bi-trash3 me-1"></i>FSK18-Profilbild löschen
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Vom Partner noch kein FSK18-Bild hinterlegt</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 1.2 Über mich & Obolus-Bezeichnung -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small"><i class="bi bi-chat-quote text-primary me-1"></i>Über mich &amp; Obolus-Bezeichnung</span>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="chk-about-me" name="check_about_me" value="1" <?= (int)$actor['check_about_me'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="chk-about-me">„Über mich“ im Shop anzeigen</label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold mb-1">Soll der User im Profil „Trinkgeld senden“ (Porno/Erotik) oder „Tribut zollen“ (Fetisch/Domina) sehen?</label>
                                        <select class="form-select form-select-sm" name="obolus_type">
                                            <?php foreach ($defs['obolus_types'] as $oKey => $oLabel): ?>
                                                <option value="<?= View::e((string)$oKey) ?>" <?= $actor['obolus_type'] === $oKey ? 'selected' : '' ?>><?= View::e((string)$oLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Freitext „Über mich“:</label>
                                        <textarea class="form-control form-control-sm" name="about_me" rows="4"><?= View::e((string)$actor['about_me']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- 1.3 Körper & Aussehen (Steckbrief-Attribute) -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-sliders text-primary me-1"></i>Körper &amp; Aussehen (Steckbrief-Angaben &amp; Sichtbarkeit)
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <!-- Alter -->
                                        <div class="col-12 col-md-4">
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-bold">Alter (Jahre)</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_age" value="1" <?= (int)$actor['check_age'] === 1 ? 'checked' : '' ?> title="Im Profil anzeigen">
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="number" class="form-control form-control-sm fw-bold" style="width:70px;" name="age" id="act-num-age" min="18" max="99" value="<?= (int)$actor['age'] ?>" oninput="document.getElementById('act-rng-age').value = this.value">
                                                    <input type="range" class="form-range" id="act-rng-age" min="18" max="99" value="<?= (int)$actor['age'] ?>" oninput="document.getElementById('act-num-age').value = this.value">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Körpergröße -->
                                        <div class="col-12 col-md-4">
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-bold">Größe (cm)</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_body_height" value="1" <?= (int)$actor['check_body_height'] === 1 ? 'checked' : '' ?> title="Im Profil anzeigen">
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="number" class="form-control form-control-sm fw-bold" style="width:75px;" name="body_height" id="act-num-height" min="100" max="230" value="<?= (int)$actor['body_height'] ?>" oninput="document.getElementById('act-rng-height').value = this.value">
                                                    <input type="range" class="form-range" id="act-rng-height" min="100" max="230" value="<?= (int)$actor['body_height'] ?>" oninput="document.getElementById('act-num-height').value = this.value">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Körpergewicht -->
                                        <div class="col-12 col-md-4">
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-bold">Gewicht (kg)</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_body_weight" value="1" <?= (int)$actor['check_body_weight'] === 1 ? 'checked' : '' ?> title="Im Profil anzeigen">
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="number" class="form-control form-control-sm fw-bold" style="width:70px;" name="body_weight" id="act-num-weight" min="45" max="150" value="<?= (int)$actor['body_weight'] ?>" oninput="document.getElementById('act-rng-weight').value = this.value">
                                                    <input type="range" class="form-range" id="act-rng-weight" min="45" max="150" value="<?= (int)$actor['body_weight'] ?>" oninput="document.getElementById('act-num-weight').value = this.value">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Geschlecht -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Geschlecht</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_gender" value="1" <?= (int)$actor['check_gender'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="gender">
                                                    <?php foreach ($defs['genders'] as $gKey => $gLabel): ?>
                                                        <option value="<?= View::e((string)$gKey) ?>" <?= $actor['gender'] === $gKey ? 'selected' : '' ?>><?= View::e((string)$gLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Sternzeichen -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Sternzeichen</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_star_sign" value="1" <?= (int)$actor['check_star_sign'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="star_sign">
                                                    <?php foreach ($defs['star_signs'] as $ss): ?>
                                                        <option value="<?= View::e((string)$ss) ?>" <?= $actor['star_sign'] === $ss ? 'selected' : '' ?>><?= View::e((string)$ss) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Intimrasur -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Intimrasur</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_shaven" value="1" <?= (int)$actor['check_shaven'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="shaven">
                                                    <?php foreach ($defs['shaven_options'] as $shKey => $shLabel): ?>
                                                        <option value="<?= (int)$shKey ?>" <?= (int)$actor['shaven'] === (int)$shKey ? 'selected' : '' ?>><?= View::e((string)$shLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Haarfarbe -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Haarfarbe</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_hair_color" value="1" <?= (int)$actor['check_hair_color'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="hair_color">
                                                    <?php foreach ($defs['hair_colors'] as $hc): ?>
                                                        <option value="<?= View::e((string)$hc) ?>" <?= $actor['hair_color'] === $hc ? 'selected' : '' ?>><?= View::e((string)$hc) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Augenfarbe -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Augenfarbe</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_eye_color" value="1" <?= (int)$actor['check_eye_color'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="eye_color">
                                                    <?php foreach ($defs['eye_colors'] as $ec): ?>
                                                        <option value="<?= View::e((string)$ec) ?>" <?= $actor['eye_color'] === $ec ? 'selected' : '' ?>><?= View::e((string)$ec) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Körbchengröße -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Körbchengröße</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_cup_size" value="1" <?= (int)$actor['check_cup_size'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <select class="form-select form-select-sm" name="cup_size_a">
                                                        <?php foreach ($defs['cup_sizes_a'] as $ca): ?>
                                                            <option value="<?= View::e((string)$ca) ?>" <?= (string)$actor['cup_size_a'] === (string)$ca ? 'selected' : '' ?>><?= View::e((string)$ca) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <select class="form-select form-select-sm" name="cup_size_b">
                                                        <?php foreach ($defs['cup_sizes_b'] as $cb): ?>
                                                            <option value="<?= View::e((string)$cb) ?>" <?= (string)$actor['cup_size_b'] === (string)$cb ? 'selected' : '' ?>><?= View::e((string)$cb) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sexuelle Orientierung -->
                                        <div class="col-12 col-sm-6">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Sexuelle Orientierung</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_sexual_orientation" value="1" <?= (int)$actor['check_sexual_orientation'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="sexual_orientation">
                                                    <?php foreach ($defs['sexual_orientations'] as $so): ?>
                                                        <option value="<?= View::e((string)$so) ?>" <?= $actor['sexual_orientation'] === $so ? 'selected' : '' ?>><?= View::e((string)$so) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Familienstand -->
                                        <div class="col-12 col-sm-6">
                                            <div class="border rounded p-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="small fw-semibold">Familienstand</label>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="check_marital_status" value="1" <?= (int)$actor['check_marital_status'] === 1 ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                                <select class="form-select form-select-sm" name="marital_status">
                                                    <?php foreach ($defs['marital_statuses'] as $ms): ?>
                                                        <option value="<?= View::e((string)$ms) ?>" <?= $actor['marital_status'] === $ms ? 'selected' : '' ?>><?= View::e((string)$ms) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 1.4 Interessen & Ich suche -->
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small">Interessen</span>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="check_interests" value="1" <?= (int)$actor['check_interests'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small">Anzeigen</label>
                                            </div>
                                        </div>
                                        <div class="card-body py-2">
                                            <?php foreach ($defs['interests'] as $idx => $intVal):
                                                $isChecked = in_array($intVal, (array)$actor['interests_list'], true);
                                            ?>
                                                <div class="form-check small mb-1">
                                                    <input class="form-check-input" type="checkbox" id="act-int-<?= $idx ?>" name="interests[]" value="<?= View::e((string)$intVal) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="act-int-<?= $idx ?>"><?= View::e((string)$intVal) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small">Ich suche</span>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="check_looking_for" value="1" <?= (int)$actor['check_looking_for'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small">Anzeigen</label>
                                            </div>
                                        </div>
                                        <div class="card-body py-2">
                                            <?php foreach ($defs['looking_for'] as $idx => $lfVal):
                                                $isChecked = in_array($lfVal, (array)$actor['looking_for_list'], true);
                                            ?>
                                                <div class="form-check small mb-1">
                                                    <input class="form-check-input" type="checkbox" id="act-lf-<?= $idx ?>" name="looking_for[]" value="<?= View::e((string)$lfVal) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="act-lf-<?= $idx ?>"><?= View::e((string)$lfVal) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 1.5 Sexuelle Vorlieben -->
                            <div class="card border">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small">Sexuelle Vorlieben (<?= count($defs['sexual_preferences']) ?> Optionen)</span>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="check_sexual_preferences" value="1" <?= (int)$actor['check_sexual_preferences'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label small">Im Profil anzeigen</label>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row g-1">
                                        <?php foreach ($defs['sexual_preferences'] as $idx => $spVal):
                                            $isChecked = in_array($spVal, (array)$actor['sexual_preferences_list'], true);
                                        ?>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check small">
                                                    <input class="form-check-input" type="checkbox" id="act-sp-<?= $idx ?>" name="sexual_preferences[]" value="<?= View::e((string)$spVal) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="act-sp-<?= $idx ?>"><?= View::e((string)$spVal) ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- RECHTE SPALTE: STATUS, MERCHANT, PROVISIONEN, KATEGORIEN & UMZUG -->
                        <div class="col-12 col-xl-5">

                            <!-- 2.1 Profil-Status -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-toggle-on text-primary me-1"></i>Profil-Status &amp; Freigabe
                                </div>
                                <div class="card-body">
                                    <div class="row g-2">
                                        <?php
                                        $statusOptions = [
                                            'active'   => ['label' => 'Aktiv (Online)', 'cls' => 'border-success bg-success bg-opacity-10 text-success'],
                                            'inactive' => ['label' => 'Inaktiv', 'cls' => 'border-secondary bg-light text-secondary'],
                                            'blocked'  => ['label' => 'Gesperrt', 'cls' => 'border-danger bg-danger bg-opacity-10 text-danger'],
                                        ];
                                        if ((string)($employeeRule ?? '') === 'write' || $actor['status'] === 'deleted') {
                                            $statusOptions['deleted'] = ['label' => 'Gelöscht', 'cls' => 'border-dark bg-dark bg-opacity-10 text-dark'];
                                        }
                                        foreach ($statusOptions as $stKey => $stMeta):
                                        ?>
                                            <div class="col-6">
                                                <label class="border rounded p-2 d-flex align-items-center gap-2 w-100 <?= $stMeta['cls'] ?>" style="cursor:pointer;">
                                                    <input type="radio" class="form-check-input mt-0" name="actor_status" value="<?= $stKey ?>"
                                                           <?= $actor['status'] === $stKey ? 'checked' : '' ?>
                                                           onchange="App.Actors.onStatusChange(this.value)">
                                                    <span class="fw-bold small"><?= View::e($stMeta['label']) ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div id="act-status-delete-warning" class="alert alert-danger small mt-3 mb-0" style="<?= $actor['status'] === 'deleted' ? '' : 'display:none;' ?>">
                                        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>ACHTUNG beim Status „Gelöscht“:</strong><br>
                                        Beim Speichern mit dem Status „Gelöscht“ werden alle zugeordneten Filme und Fotoalben dieses Profils ebenfalls als gelöscht markiert. Bereits von Kunden gekaufte Filme/Alben bleiben im Kundenkonto weiterhin abrufbar (Kundenschutz-Frist).
                                    </div>
                                </div>
                            </div>

                            <!-- 2.2 Merchant- & Partner-Infos -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-briefcase text-primary me-1"></i>Zugeordneter Pay4Coins-Partner (Merchant)
                                </div>
                                <div class="card-body small">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <span class="text-muted">Actor-ID:</span><br>
                                            <strong class="font-monospace">#<?= $aid ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">Merchant-ID:</span><br>
                                            <a href="#/merchant?id=<?= (int)$actor['merchant_id'] ?>" class="fw-bold text-decoration-none">MID #<?= (int)$actor['merchant_id'] ?></a>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">P4C-Partner-ID:</span><br>
                                            <a href="#/merchant?id=<?= (int)$actor['merchant_id'] ?>" class="fw-bold text-decoration-none"><?= (int)$actor['partner_id'] ?></a>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">P4C-Username:</span><br>
                                            <a href="#/merchant?id=<?= (int)$actor['merchant_id'] ?>" class="fw-bold text-decoration-none"><?= View::e((string)$actor['merchant_username']) ?></a>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">Erstellt am:</span><br>
                                            <strong><?= View::e((string)$actor['created_fmt']) ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">Zuletzt online:</span><br>
                                            <strong><?= View::e((string)$actor['lastonline_fmt']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2.3 Admin-Infos -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-journal-text text-primary me-1"></i>Interne Admin-Notizen zum Profil
                                </div>
                                <div class="card-body">
                                    <textarea class="form-control form-control-sm" name="admin_infos" rows="3" placeholder="Interne Notizen für Mitarbeiter..."><?= View::e((string)$actor['admin_infos']) ?></textarea>
                                </div>
                            </div>

                            <!-- 2.4 Provisionen (amoredea.com) & Profil-Einordnung -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-percent text-primary me-1"></i>Provisionen (amoredea.com) &amp; Profil-Einordnung
                                </div>
                                <div class="card-body">
                                    <div class="row g-2 mb-3">
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-semibold mb-1">Provision Content (Standard: 50%):</label>
                                            <select class="form-select form-select-sm" name="amoredea_provision_content">
                                                <?php for ($i = 40; $i <= 80; $i++): ?>
                                                    <option value="<?= $i ?>" <?= (int)$actor['amoredea_provision_content'] === $i ? 'selected' : '' ?>><?= $i ?> %</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-semibold mb-1">Provision Obolus (Standard: 70%):</label>
                                            <select class="form-select form-select-sm" name="amoredea_provision_obolus">
                                                <?php for ($i = 40; $i <= 80; $i++): ?>
                                                    <option value="<?= $i ?>" <?= (int)$actor['amoredea_provision_obolus'] === $i ? 'selected' : '' ?>><?= $i ?> %</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label small fw-semibold mb-1">Für was soll das Profil genutzt werden?</label>
                                        <select class="form-select form-select-sm" name="is_displayed_as">
                                            <?php foreach ($defs['display_groups'] as $dKey => $dLabel): ?>
                                                <option value="<?= View::e((string)$dKey) ?>" <?= $actor['is_displayed_as'] === $dKey ? 'selected' : '' ?>><?= View::e((string)$dLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 2.5 Darsteller kategorisieren (actor_categories) -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2 fw-bold small">
                                    <i class="bi bi-tags-fill text-primary me-1"></i>Darsteller kategorisieren (Shop-Zuordnung)
                                </div>
                                <div class="card-body">
                                    <div class="row g-2 mb-3">
                                        <!-- Hauptkategorie -->
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-primary mb-1">Hauptkategorie:</label>
                                            <select class="form-select form-select-sm fw-bold" name="categorys[]">
                                                <?php foreach ($categoriesByGroup['main'] as $cat): ?>
                                                    <option value="<?= View::e($cat['name_id']) ?>" <?= in_array($cat['name_id'], $activeCats, true) ? 'selected' : '' ?>><?= View::e($cat['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Gewicht -->
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Gewicht:</label>
                                            <select class="form-select form-select-sm" name="categorys[]">
                                                <?php foreach ($categoriesByGroup['weight'] as $cat): ?>
                                                    <option value="<?= View::e($cat['name_id']) ?>" <?= in_array($cat['name_id'], $activeCats, true) ? 'selected' : '' ?>><?= View::e($cat['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Hauttyp -->
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Hauttyp:</label>
                                            <select class="form-select form-select-sm" name="categorys[]">
                                                <?php foreach ($categoriesByGroup['skin_color'] as $cat): ?>
                                                    <option value="<?= View::e($cat['name_id']) ?>" <?= in_array($cat['name_id'], $activeCats, true) ? 'selected' : '' ?>><?= View::e($cat['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Haarfarbe -->
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Haarfarbe (Kategorie):</label>
                                            <select class="form-select form-select-sm" name="categorys[]">
                                                <?php foreach ($categoriesByGroup['hair_color'] as $cat): ?>
                                                    <option value="<?= View::e($cat['name_id']) ?>" <?= in_array($cat['name_id'], $activeCats, true) ? 'selected' : '' ?>><?= View::e($cat['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Geschlecht -->
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Geschlecht (Kategorie):</label>
                                            <select class="form-select form-select-sm" name="categorys[]">
                                                <?php foreach ($categoriesByGroup['gender'] as $cat): ?>
                                                    <option value="<?= View::e($cat['name_id']) ?>" <?= in_array($cat['name_id'], $activeCats, true) ? 'selected' : '' ?>><?= View::e($cat['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Alter-Kategorien (Checkboxen mit Beschreibung) -->
                                    <div class="fw-bold small border-bottom pb-1 mb-2">Alters-Kategorien:</div>
                                    <div class="mb-3">
                                        <?php foreach ($categoriesByGroup['age'] as $cat):
                                            $isChecked = in_array($cat['name_id'], $activeCats, true);
                                        ?>
                                            <div class="form-check small mb-2">
                                                <input class="form-check-input" type="checkbox" id="act-cat-<?= View::e($cat['name_id']) ?>" name="categorys[]" value="<?= View::e($cat['name_id']) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-semibold" for="act-cat-<?= View::e($cat['name_id']) ?>">
                                                    <?= View::e($cat['label']) ?>
                                                </label>
                                                <?php if ($cat['desc'] !== ''): ?>
                                                    <div class="text-muted" style="font-size:0.75rem;"><?= View::e($cat['desc']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Besondere Körpereigenschaften -->
                                    <div class="fw-bold small border-bottom pb-1 mb-2">Besondere Körpereigenschaften:</div>
                                    <div>
                                        <?php foreach ($categoriesByGroup['body'] as $cat):
                                            $isChecked = in_array($cat['name_id'], $activeCats, true);
                                        ?>
                                            <div class="form-check small mb-2">
                                                <input class="form-check-input" type="checkbox" id="act-cat-<?= View::e($cat['name_id']) ?>" name="categorys[]" value="<?= View::e($cat['name_id']) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-semibold" for="act-cat-<?= View::e($cat['name_id']) ?>">
                                                    <?= View::e($cat['label']) ?>
                                                </label>
                                                <?php if ($cat['desc'] !== ''): ?>
                                                    <div class="text-muted" style="font-size:0.75rem;"><?= View::e($cat['desc']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Haupt-Speicherbutton -->
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary fw-bold py-2" id="btn-actor-save-submit">
                                    <i class="bi bi-floppy-fill me-1"></i>Alle Profildaten &amp; Kategorien speichern
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- 2.6 Darsteller einem neuen Partner zuordnen (actor_reassign) -->
                <div class="card border border-warning mt-2">
                    <div class="card-header bg-warning bg-opacity-10 py-2 fw-bold small">
                        <i class="bi bi-arrow-left-right text-dark me-1"></i>Darsteller einem neuen Partner (Merchant) zuordnen
                    </div>
                    <div class="card-body">
                        <?php if ($pendingReassign !== null): ?>
                            <div class="alert alert-warning small mb-2">
                                <strong><i class="bi bi-hourglass-split me-1"></i>Aktiver Umzugsauftrag läuft:</strong>
                                Dieses Darsteller-Profil wird aktuell von <strong>MID #<?= (int)$pendingReassign['old_merchant_id'] ?></strong> an
                                <strong>MID #<?= (int)$pendingReassign['new_merchant_id'] ?> (<?= View::e((string)$pendingReassign['new_username']) ?>)</strong> übertragen
                                (beauftragt am <?= View::e((string)$pendingReassign['order_from']) ?>). Der Server-Cronjob (<code>cronjobs/actor_reassign.php</code>) übernimmt die Datei-Verschiebung alle 5 Minuten automatisch.
                            </div>
                        <?php elseif ((string)($employeeRule ?? '') !== 'write'): ?>
                            <div class="alert alert-secondary small mb-0">
                                <i class="bi bi-shield-lock me-1"></i>
                                Die Übertragung eines Darsteller-Profils an einen anderen Partner (Merchant) ist nur für Administratoren mit Schreibberechtigung freigeschaltet.
                            </div>
                        <?php else: ?>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label small fw-semibold mb-1">
                                        Neuen Besitzer (Username / Partner-ID / MID) auswählen:
                                    </label>
                                    <select class="form-select form-select-sm" id="act-reassign-merchant-select">
                                        <option value="0">-- ACHTUNG: Nur auswählen, wenn du den Besitzer wechseln möchtest --</option>
                                        <?php foreach ($allMerchants as $mItem):
                                            if ((int)$mItem['id'] === (int)$actor['merchant_id']) {
                                                continue;
                                            }
                                        ?>
                                            <option value="<?= (int)$mItem['id'] ?>">
                                                <?= View::e((string)$mItem['username']) ?> (Partner-ID: <?= (int)$mItem['partner_id'] ?> | MID #<?= (int)$mItem['id'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold w-100" id="btn-act-reassign" onclick="App.Actors.reassignMerchant(<?= $aid ?>)">
                                        <i class="bi bi-person-gear me-1"></i>Besitzerwechsel beauftragen
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($completedReassign)): ?>
                            <div class="mt-3 pt-2 border-top small">
                                <div class="fw-bold text-muted mb-1">Bisherige Besitzer-Historie (abgeschlossene Umzüge):</div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($completedReassign as $cr): ?>
                                        <li>
                                            <span class="font-monospace"><?= View::e((string)$cr['files_mapping_completed']) ?></span> &ndash;
                                            verschoben von <a href="#/merchant?id=<?= (int)$cr['old_merchant_id'] ?>">MID #<?= (int)$cr['old_merchant_id'] ?> (<?= View::e((string)$cr['old_username']) ?>)</a>
                                            zu <a href="#/merchant?id=<?= (int)$cr['new_merchant_id'] ?>">MID #<?= (int)$cr['new_merchant_id'] ?> (<?= View::e((string)$cr['new_username']) ?>)</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: ZUGEORDNETE VIDEOS                                    -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-act-movies" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0">Alle Filme von <?= View::e((string)$actor['username']) ?> (<?= count($actorMovies) ?>)</h6>
                        <small class="text-muted">Direktzugriff auf Prüfung und Bearbeitung aller Filme dieses Darsteller-Profils.</small>
                    </div>
                    <input type="text" class="form-control form-control-sm" style="max-width:260px;" placeholder="Filme nach ID oder Titel filtern..." oninput="App.Actors.filterSubTable('act-movie-row', this.value)">
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80px;">Film-ID</th>
                                <th style="width:85px;">Online-ID</th>
                                <th style="width:100px;">Status</th>
                                <th style="width:95px;">Vorschau</th>
                                <th>Titel</th>
                                <th style="width:100px;">Preis</th>
                                <th style="width:90px;">Dauer</th>
                                <th style="width:140px;">Veröffentlichung</th>
                                <th style="width:130px;" class="text-end">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($actorMovies)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Diesem Darsteller-Profil sind noch keine Filme zugeordnet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($actorMovies as $mv):
                                    $stBadge = match ($mv['status']) {
                                        'active'  => '<span class="badge bg-success">Aktiv</span>',
                                        'blocked' => '<span class="badge bg-danger">Gesperrt</span>',
                                        'deleted' => '<span class="badge bg-dark">Gelöscht</span>',
                                        default   => '<span class="badge bg-secondary">' . View::e((string)$mv['status']) . '</span>',
                                    };
                                    $posterUrl = 'media.php?type=poster&fsk=16&movie_id=' . (int)$mv['id'] . '&w=120';
                                    $mSearch   = strtolower($mv['id'] . ' ' . $mv['online_id'] . ' ' . $mv['title'] . ' ' . $mv['workflow_badge']);
                                ?>
                                    <tr class="act-movie-row" data-search="<?= View::e($mSearch) ?>">
                                        <td class="font-monospace fw-bold">#<?= (int)$mv['id'] ?></td>
                                        <td class="font-monospace"><?= (int)$mv['online_id'] > 0 ? ('#' . (int)$mv['online_id']) : '-' ?></td>
                                        <td><?= $stBadge ?></td>
                                        <td>
                                            <img src="<?= View::e($posterUrl) ?>" alt="" class="rounded border" style="width:76px;height:44px;object-fit:cover;">
                                        </td>
                                        <td>
                                            <a href="<?= View::e((string)$mv['action_href']) ?>" class="fw-bold text-decoration-none"><?= View::e((string)$mv['title']) ?></a>
                                            <?php if ($mv['workflow_badge'] !== ''): ?>
                                                <span class="badge <?= $mv['is_checking'] ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1"><?= View::e((string)$mv['workflow_badge']) ?></span>
                                            <?php endif; ?>
                                            <?php if ((int)$mv['purchases'] > 0 || (int)$mv['views'] > 0): ?>
                                                <div class="text-muted" style="font-size:0.72rem;"><?= (int)$mv['purchases'] ?> Käufe &bull; <?= (int)$mv['views'] ?> Views</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= View::e((string)$mv['selling_price']) ?> Coins</td>
                                        <td><?= View::e((string)$mv['playtime']) ?></td>
                                        <td><?= View::e((string)$mv['online_at']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= View::e((string)$mv['action_href']) ?>" class="btn btn-sm <?= $mv['is_checking'] ? 'btn-warning text-dark fw-semibold' : 'btn-outline-primary' ?>">
                                                <?= View::e((string)$mv['action_label']) ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: ZUGEORDNETE FOTOALBEN                                 -->
            <!-- ============================================================ -->
            <div class="tab-pane fade" id="tab-act-albums" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-0">Alle Fotoalben von <?= View::e((string)$actor['username']) ?> (<?= count($actorAlbums) ?>)</h6>
                        <small class="text-muted">Direktzugriff auf Prüfung und Bearbeitung aller Fotoalben dieses Darsteller-Profils.</small>
                    </div>
                    <input type="text" class="form-control form-control-sm" style="max-width:260px;" placeholder="Fotoalben nach ID oder Titel filtern..." oninput="App.Actors.filterSubTable('act-album-row', this.value)">
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80px;">Album-ID</th>
                                <th style="width:85px;">Online-ID</th>
                                <th style="width:100px;">Status</th>
                                <th style="width:95px;">Vorschau</th>
                                <th>Titel</th>
                                <th style="width:110px;">Anzahl Fotos</th>
                                <th style="width:140px;">Veröffentlichung</th>
                                <th style="width:130px;" class="text-end">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($actorAlbums)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Diesem Darsteller-Profil sind noch keine Fotoalben zugeordnet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($actorAlbums as $al):
                                    $stBadge = match ($al['status']) {
                                        'active'  => '<span class="badge bg-success">Aktiv</span>',
                                        'blocked' => '<span class="badge bg-danger">Gesperrt</span>',
                                        'deleted' => '<span class="badge bg-dark">Gelöscht</span>',
                                        default   => '<span class="badge bg-secondary">' . View::e((string)$al['status']) . '</span>',
                                    };
                                    $posterUrl = 'media.php?type=album_poster&fsk=16&album_id=' . (int)$al['id'] . '&w=120';
                                    $aSearch   = strtolower($al['id'] . ' ' . $al['online_id'] . ' ' . $al['title'] . ' ' . $al['workflow_badge']);
                                ?>
                                    <tr class="act-album-row" data-search="<?= View::e($aSearch) ?>">
                                        <td class="font-monospace fw-bold">#<?= (int)$al['id'] ?></td>
                                        <td class="font-monospace"><?= (int)$al['online_id'] > 0 ? ('#' . (int)$al['online_id']) : '-' ?></td>
                                        <td><?= $stBadge ?></td>
                                        <td>
                                            <img src="<?= View::e($posterUrl) ?>" alt="" class="rounded border" style="width:64px;height:48px;object-fit:cover;">
                                        </td>
                                        <td>
                                            <a href="<?= View::e((string)$al['action_href']) ?>" class="fw-bold text-decoration-none"><?= View::e((string)$al['title']) ?></a>
                                            <?php if ($al['workflow_badge'] !== ''): ?>
                                                <span class="badge <?= $al['is_checking'] ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1"><?= View::e((string)$al['workflow_badge']) ?></span>
                                            <?php endif; ?>
                                            <?php if ((int)$al['purchases'] > 0 || (int)$al['views'] > 0): ?>
                                                <div class="text-muted" style="font-size:0.72rem;"><?= (int)$al['purchases'] ?> Käufe &bull; <?= (int)$al['views'] ?> Views</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)$al['number_of_photos'] ?> Fotos</td>
                                        <td><?= View::e((string)$al['online_at']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= View::e((string)$al['action_href']) ?>" class="btn btn-sm <?= $al['is_checking'] ? 'btn-warning text-dark fw-semibold' : 'btn-outline-primary' ?>">
                                                <?= View::e((string)$al['action_label']) ?>
                                            </a>
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
