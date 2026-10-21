<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

/** @var string $firstname */
/** @var string $surname */
/** @var bool $needsRulesAccept */
/** @var string $acceptContentRulesDate */
/** @var bool $hasInactiveOrBlockedActor */
/** @var bool $canCreateActor */
/** @var array $actors */
/** @var int $actorsTotal */
/** @var int $activeActorsCount */
/** @var int $moviesTotal */
/** @var int $moviesOnline */
/** @var int $moviesPending */
/** @var int $albumsTotal */
/** @var int $albumsOnline */
/** @var int $albumsPending */
/** @var int $photosTotal */
/** @var int $contentPending */
/** @var array $recentContents */

$mcpUrl       = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
$domainName   = defined('DOMAIN') ? DOMAIN : 'erocloud.net';
$projectName  = defined('PROJECTNAME') ? PROJECTNAME : 'EroCloud';
?>

<!-- Page Header & Personal Greeting -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">
      <?= $firstname !== '' ? 'Hallo ' . View::e($firstname) . ', schön dass du zurück bist!' : 'Meine Startseite' ?>
    </h1>
    <p class="text-muted mb-0">
      Deine persönlichen Stammdaten wie dein Name und deine Anschrift sind für Endkunden nicht sichtbar. Nutzer sehen ausschließlich die Angaben, die du in deinen Darsteller-Profilen hinterlegt hast.
    </p>
  </div>
</div>

<?php if ($needsRulesAccept): ?>
<!-- Wichtiger Hinweis: Nutzungsrichtlinien noch nicht bestätigt -->
<div class="card-kkc border-primary mb-4" style="border-width: 2px;">
  <div class="card-kkc-header ec-bg-primary-subtle">
    <div class="card-title ec-text-primary">
      <i class="bi bi-file-earmark-check-fill"></i>
      <span>Aktualisierte Nutzungsrichtlinien zur Vermarktung deines Contents</span>
    </div>
    <span class="badge-kkc badge-kkc-warning">Bestätigung erforderlich</span>
  </div>
  <div class="card-kkc-body">
    <p class="mb-2 fw-semibold">
      Die Nutzungsrichtlinien zur Vermarktung deines Contents wurden aktualisiert. Bitte überprüfe die Änderungen und bestätige deine Zustimmung.
    </p>
    <p class="text-muted small mb-3">
      Die aktualisierten Nutzungsrichtlinien stellen klar, dass das Urheberrecht beim Partner verbleibt, während Cipa Media eine nicht-exklusive Lizenz zur Vermarktung erhält. Zudem wurde konkretisiert, dass Inhalte mindestens ein Jahr online bleiben, bevor sie gelöscht werden können, und dass bereits verkaufte Inhalte für Käufer weiterhin zugänglich bleiben, sofern keine technischen Einschränkungen vorliegen. Außerdem wird die Einhaltung gewerblicher Schutz- und Urheberrechte betont, um rechtliche Verstöße zu vermeiden.
    </p>
    <button type="button" class="btn btn-primary-kkc" data-bs-toggle="modal" data-bs-target="#modal-content-rules">
      <i class="bi bi-journal-check me-1"></i> Neue Nutzungsrichtlinien anzeigen und akzeptieren
    </button>
  </div>
</div>

<!-- Modal: Vollständige Nutzungsrichtlinien bestätigen -->
<div class="modal fade" id="modal-content-rules" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-shield-check ec-text-primary me-2"></i>Regeln zum Vermarkten deines Contents über <?= View::e($projectName) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body small">
        <div class="alert alert-warning py-2">
          Bitte lies die nachfolgenden Vereinbarungen sorgfältig durch und bestätige diese am Ende des Dokuments.
        </div>

        <div class="text-center my-3 p-3 rounded border" style="background-color: var(--kkc-surface-hover);">
          <div class="text-muted mb-1">Vereinbarung zwischen</div>
          <div class="fw-bold">Cipa Media S.L.</div>
          <div>Av. Emilio Luque Moreno 19, Oficina 6, ES-38300 La Orotava (S/C de Tenerife)</div>
          <div class="text-muted my-1">– nachfolgend „Cipa Media“ –</div>
          <div class="text-muted mb-1">und</div>
          <div class="fw-bold"><?= View::e(trim($firstname . ' ' . $surname)) ?></div>
          <div class="text-muted mt-1">– nachfolgend „Partner“ –</div>
        </div>

        <h6 class="fw-bold mt-3">1. Urheberrecht und Vermarktungsrechte</h6>
        <p class="text-muted">
          Das Urheberrecht an den hochgeladenen Inhalten (Filme, Bilder und Texte, nachfolgend „Content“ genannt) verbleibt beim Partner. Mit dem Hochladen des Contents auf <strong><?= View::e($domainName) ?></strong> erteilt der Partner Cipa Media eine nicht-exklusive Lizenz zur Vermarktung des Contents für die Dauer des Vertragsverhältnisses.
        </p>

        <h6 class="fw-bold mt-3">2. Verwaltung und Löschfristen von Content</h6>
        <p class="text-muted">
          Filme bleiben mindestens <strong>ein Jahr</strong> zum Verkauf online, bevor sie entfernt werden können. Beantragt ein Partner die Löschung seines Contents, wird dieser für neue Käufe entfernt. Bereits verkaufter Content wird für bestehende Käufer über eine Übergangsfrist von bis zu 365 Tagen (bei aktiver Nutzung innerhalb der letzten 2 Jahre) bzw. 30 Tagen bereitgehalten, bevor die Dateien endgültig vom Server entfernt werden.
        </p>

        <h6 class="fw-bold mt-3">3. Gesetzliche Bestimmungen, Urheberrechte Dritter &amp; Mitwirkende</h6>
        <p class="text-muted">
          Der Partner versichert, dass sämtliche hochgeladenen Inhalte frei von Rechten Dritter sind (insbesondere keine geschützte Hintergrundmusik), keine gesetzlichen Verbote verletzen und für alle im Content sichtbaren Personen ein gültiger Alters- und Einverständnisnachweis (Volljährigkeit zum Zeitpunkt der Aufnahme) vorliegt.
        </p>

        <hr>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="chk-accept-content-rules" value="1">
          <label class="form-check-label fw-semibold" for="chk-accept-content-rules">
            Ich habe die Nutzungsrichtlinien zur Vermarktung meines Contents gelesen und akzeptiere diese verbindlich.
          </label>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
        <button type="button" class="btn btn-sm btn-primary-kkc" id="btn-submit-content-rules">
          <i class="bi bi-check2-circle me-1"></i> Zustimmung verbindlich speichern
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($hasInactiveOrBlockedActor): ?>
<!-- Warnhinweis bei noch nicht freigeschalteten Darsteller-Profilen -->
<div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 shadow-sm">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <span>
      <?php if ($actorsTotal === 1 && !empty($actors)): ?>
        Dein Darsteller-Profil <a href="#/actor?id=<?= (int)$actors[0]['id'] ?>" class="fw-bold text-reset"><?= View::e($actors[0]['username']) ?></a> ist aktuell noch nicht aktiviert. Solltest du Fragen zur Freischaltung haben, wende dich bitte an unseren Support.
      <?php else: ?>
        Mindestens eines deiner <a href="#/actors" class="fw-bold text-reset">Darsteller-Profile</a> ist aktuell noch nicht aktiviert. Solltest du Fragen zur Freischaltung haben, wende dich bitte an unseren Support.
      <?php endif; ?>
    </span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-toggle="modal" data-bs-target="#modal-support">
    <i class="bi bi-headset me-1"></i> Support kontaktieren
  </button>
</div>
<?php endif; ?>

<!-- 4 echte KPI-Kacheln -->
<div class="row g-3 mb-4">
  <div class="col-xl-3 col-md-6">
    <a href="#/movies" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
          <i class="bi bi-collection-play-fill"></i>
        </div>
        <div>
          <div class="kpi-val"><?= (int)$moviesTotal ?></div>
          <div class="kpi-label">Filme in der Cloud</div>
          <small class="text-success fw-semibold">
            <i class="bi bi-check-circle me-1"></i><?= (int)$moviesOnline ?> online<?= $moviesPending > 0 ? ' / ' . (int)$moviesPending . ' in Bearbeitung' : '' ?>
          </small>
        </div>
      </div>
    </a>
  </div>

  <div class="col-xl-3 col-md-6">
    <a href="#/photo-albums" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
          <i class="bi bi-images"></i>
        </div>
        <div>
          <div class="kpi-val"><?= (int)$albumsTotal ?></div>
          <div class="kpi-label">Fotoalben in der Cloud</div>
          <small class="text-muted">Gesamt: <?= number_format((int)$photosTotal, 0, ',', '.') ?> Fotos (<?= (int)$albumsOnline ?> online)</small>
        </div>
      </div>
    </a>
  </div>

  <div class="col-xl-3 col-md-6">
    <?php
      $actorKpiHref = '#/actors';
      if ($actorsTotal === 1 && !empty($actors)) {
          $actorKpiHref = '#/actor?id=' . (int)$actors[0]['id'];
      } elseif ($actorsTotal === 0) {
          $actorKpiHref = '#/new-actor';
      }
    ?>
    <a href="<?= View::e($actorKpiHref) ?>" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
          <i class="bi bi-people-fill"></i>
        </div>
        <div>
          <div class="kpi-val"><?= (int)$actorsTotal ?></div>
          <div class="kpi-label"><?= $actorsTotal === 1 ? 'Darsteller-Profil' : 'Darsteller-Profile' ?></div>
          <small class="<?= $activeActorsCount === $actorsTotal && $actorsTotal > 0 ? 'text-success' : 'text-warning' ?> fw-semibold">
            <i class="bi bi-shield-check me-1"></i><?= (int)$activeActorsCount ?> von <?= (int)$actorsTotal ?> aktiv
          </small>
        </div>
      </div>
    </a>
  </div>

  <div class="col-xl-3 col-md-6">
    <div class="card-kkc kpi-card h-100 mb-0">
      <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div>
        <div class="kpi-val"><?= (int)$contentPending ?></div>
        <div class="kpi-label">Inhalte in Prüfung</div>
        <small class="text-muted">
          Filme: <?= (int)$moviesPending ?> · Fotoalben: <?= (int)$albumsPending ?>
        </small>
      </div>
    </div>
  </div>
</div>

<!-- Main Dashboard Grid -->
<div class="row g-4">
  <!-- Linke Spalte: Schnellstart & Zuletzt hochgeladene Filme -->
  <div class="col-lg-7">

    <!-- Schnellzugriff Kachel -->
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-lightning-charge-fill ec-text-primary"></i>
          <span>Schnellzugriff</span>
        </div>
        <span class="badge-kkc badge-kkc-neutral">Direktwahl</span>
      </div>
      <div class="card-kkc-body">
        <div class="row g-3">
          <?php if ($actorsTotal > 0): ?>
          <div class="col-sm-6">
            <a href="#/movie-upload" class="action-tile text-decoration-none">
              <div class="action-tile-icon ec-bg-primary-subtle ec-text-primary">
                <i class="bi bi-cloud-arrow-up-fill"></i>
              </div>
              <div>
                <div class="fw-bold text-body">Neuen Film hochladen</div>
                <small class="text-muted">MP4/MOV Video in 3 Schritten veröffentlichen</small>
              </div>
            </a>
          </div>
          <div class="col-sm-6">
            <a href="#/photo-album-upload" class="action-tile text-decoration-none">
              <div class="action-tile-icon ec-bg-primary-subtle ec-text-primary">
                <i class="bi bi-file-earmark-image-fill"></i>
              </div>
              <div>
                <div class="fw-bold text-body">Fotos hochladen</div>
                <small class="text-muted">Neues Fotoalbum anlegen &amp; Bilder hochladen</small>
              </div>
            </a>
          </div>
          <?php endif; ?>

          <?php if ($canCreateActor): ?>
          <div class="col-sm-6">
            <a href="#/new-actor" class="action-tile text-decoration-none">
              <div class="action-tile-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-person-plus-fill"></i>
              </div>
              <div>
                <div class="fw-bold text-body">Profil anlegen</div>
                <small class="text-muted">Weiteres Darsteller-Profil hinzufügen</small>
              </div>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Zuletzt hochgeladene Filme des Partners -->
    <div class="card-kkc mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-clock-history ec-text-primary"></i>
          <span>Zuletzt hochgeladene Filme</span>
        </div>
        <?php if ($moviesTotal > 0): ?>
        <a href="#/movies" class="btn btn-sm btn-outline-kkc py-1" style="font-size: 0.78rem;">
          Alle <?= (int)$moviesTotal ?> Filme anzeigen <i class="bi bi-arrow-right ms-1"></i>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-kkc-body p-0">
        <?php if (empty($recentContents)): ?>
        <div class="p-4 text-center text-muted">
          <i class="bi bi-film fs-2 d-block mb-2 ec-text-primary"></i>
          Du hast aktuell noch keine Filme in die EroCloud hochgeladen.
          <?php if ($actorsTotal > 0): ?>
          <div class="mt-3">
            <a href="#/movie-upload" class="btn btn-sm btn-primary-kkc">
              <i class="bi bi-cloud-arrow-up me-1"></i> Ersten Film hochladen
            </a>
          </div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="kkc-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Profil</th>
                <th>Status</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentContents as $item): ?>
              <tr>
                <td class="font-monospace text-muted">#<?= (int)$item['id'] ?></td>
                <td class="fw-semibold"><?= View::e($item['title']) ?></td>
                <td>
                  <?php if ($item['actor_id'] > 0): ?>
                  <a href="#/actor?id=<?= (int)$item['actor_id'] ?>" class="text-decoration-none ec-text-primary fw-medium">
                    <?= View::e($item['actor_name']) ?>
                  </a>
                  <?php else: ?>
                  <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-kkc <?= View::e($item['status_badge']) ?>">
                    <i class="bi <?= View::e($item['status_icon']) ?>"></i> <?= View::e($item['status_label']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="<?= View::e($item['route']) ?>" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Film bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Rechte Spalte: Darsteller-Profil(e) & Richtlinien-Box -->
  <div class="col-lg-5">

    <?php if (empty($actors)): ?>
    <!-- Leerzustand: Noch kein Darsteller-Profil vorhanden -->
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-person-plus-fill ec-text-primary"></i>
          <span>Dein Darsteller-Profil</span>
        </div>
      </div>
      <div class="card-kkc-body p-4 text-center text-muted">
        <i class="bi bi-person-plus fs-2 d-block mb-2 ec-text-primary"></i>
        Du hast noch kein Darsteller-Profil angelegt. Bitte erstelle zunächst ein Profil, um Filme und Fotoalben hochladen zu können.
        <div class="mt-3">
          <a href="#/new-actor" class="btn btn-sm btn-primary-kkc">
            <i class="bi bi-person-plus-fill me-1"></i> Jetzt Profil anlegen
          </a>
        </div>
      </div>
    </div>

    <?php elseif ($actorsTotal === 1): ?>
    <!-- Einzelnes Darsteller-Profil: Direkte Profil-Card (keine Liste/Tabelle) -->
    <?php
      $singleActor    = $actors[0];
      $singleActorId  = (int)$singleActor['id'];
      $singleEditHref = '#/actor?id=' . $singleActorId;
      $singleAvatar   = $singleActor['avatar_fsk16'] !== ''
          ? $mcpUrl . '/ProfilePicture/' . rawurlencode($singleActor['avatar_fsk16'])
          : '';
      $singleInitials = mb_strtoupper(mb_substr($singleActor['username'], 0, 2));

      if ($singleActor['status'] === 'active') {
          $sBadge = 'badge-kkc-success';
          $sLabel = 'Aktiv';
          $sIcon  = 'bi-check-circle-fill';
      } elseif ($singleActor['status'] === 'locked' || $singleActor['status'] === 'blocked') {
          $sBadge = 'badge-kkc-danger';
          $sLabel = 'Gesperrt';
          $sIcon  = 'bi-exclamation-octagon-fill';
      } else {
          $sBadge = 'badge-kkc-warning';
          $sLabel = 'Wird geprüft';
          $sIcon  = 'bi-hourglass-split';
      }
    ?>
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-person-badge-fill ec-text-primary"></i>
          <span>Dein Darsteller-Profil</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge-kkc <?= $sBadge ?>">
            <i class="bi <?= $sIcon ?>"></i> <?= $sLabel ?>
          </span>
          <?php if ($canCreateActor): ?>
          <a href="#/new-actor" class="btn btn-sm btn-outline-kkc py-1" style="font-size: 0.78rem;" title="Weiteres Profil anlegen">
            <i class="bi bi-plus-lg"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-kkc-body">
        <?php if (($singleActor['status'] === 'locked' || $singleActor['status'] === 'blocked') && ($singleActor['locked_reason'] ?? '') !== ''): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3">
          <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Grund der Sperrung:</div>
          <div><?= View::e($singleActor['locked_reason']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Profilbild & Haupt-Infos -->
        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
          <a href="<?= View::e($singleEditHref) ?>" class="text-decoration-none flex-shrink-0">
            <?php if ($singleAvatar !== ''): ?>
            <img src="<?= View::e($singleAvatar) ?>"
                 alt="<?= View::e($singleActor['username']) ?>"
                 class="rounded-3 object-fit-cover border shadow-sm"
                 style="width: 84px; height: 84px;">
            <?php else: ?>
            <div class="rounded-3 ec-bg-primary-subtle ec-text-primary fw-bold d-flex align-items-center justify-content-center border"
                 style="width: 84px; height: 84px; font-size: 1.5rem;">
              <?= View::e($singleInitials) ?>
            </div>
            <?php endif; ?>
          </a>

          <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <a href="<?= View::e($singleEditHref) ?>" class="h5 fw-bold text-body text-decoration-none mb-0 text-truncate">
                <?= View::e($singleActor['username']) ?>
              </a>
              <span class="font-monospace text-muted small">#<?= $singleActorId ?></span>
            </div>

            <div class="text-muted small mb-2">
              <i class="bi bi-film ec-text-primary me-1"></i><strong><?= (int)$singleActor['movies_count'] ?></strong> Filme
              <span class="mx-1">·</span>
              <i class="bi bi-images ec-text-primary me-1"></i><strong><?= (int)$singleActor['albums_count'] ?></strong> Fotoalben
            </div>

            <div class="d-flex flex-wrap gap-2 small">
              <?php if ($singleActor['avatar_fsk16'] !== ''): ?>
              <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle fw-normal">
                <i class="bi bi-check-circle-fill me-1"></i>FSK16-Bild
              </span>
              <?php else: ?>
              <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle fw-normal">
                <i class="bi bi-exclamation-circle-fill me-1"></i>FSK16-Bild fehlt
              </span>
              <?php endif; ?>

              <?php if (($singleActor['avatar_fsk18'] ?? '') !== ''): ?>
              <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle fw-normal">
                <i class="bi bi-check-circle-fill me-1"></i>FSK18-Bild
              </span>
              <?php else: ?>
              <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle fw-normal">
                <i class="bi bi-exclamation-circle-fill me-1"></i>FSK18-Bild fehlt
              </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Steckbrief-Merkmale -->
        <div class="d-flex flex-wrap gap-1 mb-3">
          <?php if (!empty($singleActor['gender_label'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= View::e($singleActor['gender_label']) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['age'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= (int)$singleActor['age'] ?> Jahre
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['body_height'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= (int)$singleActor['body_height'] ?> cm
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['body_weight'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= (int)$singleActor['body_weight'] ?> kg
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['hair_color'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            Haare: <?= View::e($singleActor['hair_color']) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['eye_color'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            Augen: <?= View::e($singleActor['eye_color']) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['cup_size'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            BH: <?= View::e($singleActor['cup_size']) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($singleActor['star_sign'])): ?>
          <span class="badge bg-secondary bg-opacity-10 text-body border fw-normal">
            <?= View::e($singleActor['star_sign']) ?>
          </span>
          <?php endif; ?>
        </div>

        <!-- Direkt-Button zum Profil bearbeiten -->
        <a href="<?= View::e($singleEditHref) ?>" class="btn btn-sm btn-primary-kkc w-100 d-flex align-items-center justify-content-center gap-2">
          <i class="bi bi-pencil-square"></i>
          <span>Profil &amp; Steckbrief bearbeiten</span>
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- Mehrere Darsteller-Profile im Überblick -->
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-people-fill ec-text-primary"></i>
          <span>Deine Darsteller-Profile (<?= (int)$actorsTotal ?>)</span>
        </div>
        <?php if ($canCreateActor): ?>
        <a href="#/new-actor" class="btn btn-sm btn-outline-kkc py-1" style="font-size: 0.78rem;">
          <i class="bi bi-plus-lg me-1"></i> Profil anlegen
        </a>
        <?php endif; ?>
      </div>
      <div class="card-kkc-body p-0">
        <div class="table-responsive">
          <table class="kkc-table">
            <thead>
              <tr>
                <th>Profil</th>
                <th>Inhalte</th>
                <th>Status</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($actors as $actor): ?>
              <?php
                $avatarUrl = $actor['avatar_fsk16'] !== ''
                    ? $mcpUrl . '/ProfilePicture/' . rawurlencode($actor['avatar_fsk16'])
                    : '';
                $actorInitials = mb_strtoupper(mb_substr($actor['username'], 0, 2));
                if ($actor['status'] === 'active') {
                    $aBadge = 'badge-kkc-success';
                    $aLabel = 'Aktiv';
                } elseif ($actor['status'] === 'locked' || $actor['status'] === 'blocked') {
                    $aBadge = 'badge-kkc-danger';
                    $aLabel = 'Gesperrt';
                } else {
                    $aBadge = 'badge-kkc-warning';
                    $aLabel = 'Wird geprüft';
                }
              ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <?php if ($avatarUrl !== ''): ?>
                    <img src="<?= View::e($avatarUrl) ?>" alt="" class="rounded-circle object-fit-cover border" style="width: 28px; height: 28px;">
                    <?php else: ?>
                    <span class="sidebar-actor-avatar" style="width: 28px; height: 28px;"><?= View::e($actorInitials) ?></span>
                    <?php endif; ?>
                    <a href="#/actor?id=<?= (int)$actor['id'] ?>" class="fw-semibold text-decoration-none text-body"><?= View::e($actor['username']) ?></a>
                  </div>
                </td>
                <td class="text-muted small">
                  <?= (int)$actor['movies_count'] ?> Filme · <?= (int)$actor['albums_count'] ?> Alben
                </td>
                <td><span class="badge-kkc <?= $aBadge ?>"><?= $aLabel ?></span></td>
                <td class="text-end">
                  <a href="#/actor?id=<?= (int)$actor['id'] ?>" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Profil bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Account-Status & Content-Richtlinien Kachel -->
    <div class="card-kkc mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-shield-check ec-text-primary"></i>
          <span>Account-Status &amp; Content-Richtlinien</span>
        </div>
        <?php if (!$needsRulesAccept): ?>
        <span class="badge-kkc badge-kkc-success">Bestätigt</span>
        <?php else: ?>
        <span class="badge-kkc badge-kkc-warning">Offen</span>
        <?php endif; ?>
      </div>
      <div class="card-kkc-body">
        <div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
          <?php if (!$needsRulesAccept): ?>
          <i class="bi bi-check-circle-fill text-success fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Content-Richtlinien akzeptiert</div>
            <div class="text-muted small">
              Du hast den Nutzungsrichtlinien zur Vermarktung deines Contents zugestimmt (am <?= View::e(date('d.m.Y H:i', strtotime($acceptContentRulesDate))) ?> Uhr).
            </div>
          </div>
          <?php else: ?>
          <i class="bi bi-exclamation-circle-fill text-warning fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Zustimmung zu den Content-Richtlinien ausstehend</div>
            <div class="text-muted small">
              Bitte bestätige oben die aktuellen Nutzungsrichtlinien zur Vermarktung deines Contents.
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="d-flex align-items-start gap-3">
          <i class="bi bi-info-circle-fill ec-text-primary fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Support &amp; Hilfe bei Fragen</div>
            <div class="text-muted small mb-2">
              Bei Fragen zur Freischaltung deiner Darsteller-Profile oder zur Konvertierung deiner Filme steht dir unser Support jederzeit zur Verfügung.
            </div>
            <button type="button" class="btn btn-sm btn-outline-kkc py-1" data-bs-toggle="modal" data-bs-target="#modal-support">
              <i class="bi bi-headset me-1"></i> Support kontaktieren
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
