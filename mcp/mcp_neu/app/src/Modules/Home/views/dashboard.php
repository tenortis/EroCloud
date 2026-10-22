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
/** @var int $moviesRejected */
/** @var int $albumsTotal */
/** @var int $albumsOnline */
/** @var int $albumsPending */
/** @var int $albumsRejected */
/** @var int $photosTotal */
/** @var int $contentPending */
/** @var array $recentContents */

$moviesRejected = isset($moviesRejected) ? (int)$moviesRejected : 0;
$albumsRejected = isset($albumsRejected) ? (int)$albumsRejected : 0;

$mcpUrl       = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
$domainName   = defined('DOMAIN') ? DOMAIN : 'erocloud.net';
$projectName  = defined('PROJECTNAME') ? PROJECTNAME : 'EroCloud';

$greetingName = $firstname !== ''
    ? $firstname
    : (!empty($actors[0]['username']) ? (string)$actors[0]['username'] : (string)($merchantUsername ?? ''));

$actorNamesSummary = [];
foreach ($actors as $actItem) {
    if (!empty($actItem['username'])) {
        $actorNamesSummary[] = (string)$actItem['username'];
    }
}
$singleCreatorProfileName = (count($actorNamesSummary) === 1) ? $actorNamesSummary[0] : '';
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

<!-- Benachrichtigungs-Banner oben bei neuen Antworten von Martin aus der Entwicklung -->
<div id="ec-feedback-top-alert-container" class="d-none mb-4"></div>

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
      Die Nutzungsrichtlinien zur Vermarktung deines Contents wurden aktualisiert. Bitte überprüfe die Vereinbarungen und bestätige deine Zustimmung.
    </p>
    <p class="text-muted small mb-3">
      Die Nutzungsrichtlinien regeln transparent, dass das Urheberrecht an deinen Inhalten bei dir verbleibt, während Cipa Media eine nicht-exklusive Lizenz zur Vermarktung erhält. Zudem sind darin die Mindestlaufzeit von 365 Tagen, der Käuferschutz für bereits erworbene Inhalte, die automatische Bereinigung alter Entwürfe nach 180 Tagen sowie die Einhaltung von Urheber- und Mitwirkendenrechten klar zusammengefasst.
    </p>
    <button type="button" class="btn btn-primary-kkc" data-bs-toggle="modal" data-bs-target="#modal-content-rules">
      <i class="bi bi-journal-check me-1"></i> Nutzungsrichtlinien anzeigen und akzeptieren
    </button>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Vollständige Nutzungsrichtlinien (jederzeit einsehbar & bestätigbar) -->
<div class="modal fade" id="modal-content-rules" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-heading">
          <i class="bi bi-shield-check ec-text-primary me-2"></i>Regeln zum Vermarkten deines Contents über <?= View::e($projectName) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body small" style="line-height: 1.6;">
        <?php if ($needsRulesAccept): ?>
        <div class="alert alert-warning py-2">
          <i class="bi bi-info-circle-fill me-1"></i>Bitte lies dir die nachfolgende Vereinbarung sorgfältig durch und bestätige diese am Ende des Dokuments.
        </div>
        <?php else: ?>
        <div class="alert alert-success py-2">
          <i class="bi bi-check-circle-fill me-1"></i>Du hast diesen Nutzungsrichtlinien am <strong><?= View::e(date('d.m.Y \u\m H:i', strtotime($acceptContentRulesDate))) ?> Uhr</strong> verbindlich zugestimmt.
        </div>
        <?php endif; ?>

        <div class="text-center my-3 p-3 rounded border" style="background-color: var(--kkc-surface-hover);">
          <div class="text-muted mb-1">Vereinbarung zwischen</div>
          <div class="fw-bold">Cipa Media S.L.</div>
          <div>Av. Emilio Luque Moreno 19, Oficina 6, ES-38300 La Orotava (S/C de Tenerife)</div>
          <div class="text-muted my-1">– nachfolgend „Cipa Media“ –</div>
          <div class="text-muted mb-1">und</div>
          <div class="fw-bold"><?= View::e(trim($firstname . ' ' . $surname) !== '' ? trim($firstname . ' ' . $surname) : (string)($merchantUsername ?? 'Partner')) ?></div>
          <div class="text-muted mt-1">– nachfolgend „Partner“ (du) –</div>
        </div>

        <h6 class="fw-bold mt-3">1. Urheberrecht und Vermarktungsrechte</h6>
        <ul class="text-muted ps-3 mb-3">
          <li class="mb-1">Das Urheberrecht an deinen hochgeladenen Inhalten (Filme, Bilder und Texte, nachfolgend „Content“ genannt) verbleibt vollständig bei dir als Partner.</li>
          <li class="mb-1">Mit dem Hochladen deines Contents auf <strong><?= View::e($domainName) ?></strong> erteilst du Cipa Media eine nicht-exklusive Lizenz zur Vermarktung dieses Contents für die Dauer des Vertragsverhältnisses.</li>
          <li class="mb-1">Cipa Media ist berechtigt, deinen Content weltweit im Rahmen des geltenden Rechts über eigene Plattformen, angeschlossene Partnerportale sowie externe Vertriebswege zu bewerben, zu verkaufen und Unterlizenzen an Dritte zu vergeben.</li>
          <li>Cipa Media verpflichtet sich, dir die vereinbarten Provisionen aus der Vermarktung deines Contents gemäß den geltenden Vergütungsregelungen auszuzahlen.</li>
        </ul>

        <h6 class="fw-bold mt-3">2. Verwaltung und Löschung von Content</h6>
        <ul class="text-muted ps-3 mb-3">
          <li class="mb-1">Du entscheidest eigenständig über den Upload deines Contents auf <strong><?= View::e($domainName) ?></strong>. Ein hochgeladener und veröffentlichter Film bleibt mindestens <strong>ein Jahr (365 Tage)</strong> zum Verkauf online, bevor er gelöscht werden kann.</li>
          <li class="mb-1">Beantragst du die Löschung von Content oder wird das Vertragsverhältnis beendet, wird dein Content aus dem aktiven Verkauf genommen, sodass keine neuen Käufe mehr möglich sind.</li>
          <li class="mb-1"><strong>Käuferschutz für bereits erworbene Inhalte:</strong> Bereits verkaufter Content steht Kunden, die diesen rechtmäßig erworben haben, weiterhin zur Verfügung, solange keine technischen oder rechtlichen Gründe dagegensprechen. Nach einer Kontokündigung oder Löschung gewährt Cipa Media bestehenden Käufern eine Übergangsfrist:
            <ul class="mt-1 mb-1">
              <li>Wurde der gekaufte Content innerhalb der letzten <strong>2 Jahre</strong> vom Käufer noch gestreamt, bleibt er für weitere <strong>365 Tage</strong> für diesen Käufer abrufbar und wird danach endgültig gelöscht.</li>
              <li>Wurde der gekaufte Content in den letzten <strong>2 Jahren</strong> nicht mehr genutzt, bleibt er noch <strong>30 Tage</strong> abrufbar und wird anschließend endgültig gelöscht.</li>
            </ul>
            Während dieser Übergangsfrist sind keine Neukäufe möglich.
          </li>
          <li><strong>Automatische Bereinigung nach 180 Tagen:</strong> Unvollständige Film-Entwürfe (zu denen noch keine Videodatei hochgeladen wurde) sowie abgelehnte Filme, die nicht erneut zur Prüfung eingereicht wurden, werden nach Ablauf von <strong>180 Tagen</strong> automatisch und unwiderruflich aus dem System entfernt, um Speicherplatz freizugeben.</li>
        </ul>

        <h6 class="fw-bold mt-3">3. Einhaltung gesetzlicher Bestimmungen</h6>
        <ul class="text-muted ps-3 mb-3">
          <li class="mb-1">Du verpflichtest dich, ausschließlich Content hochzuladen, der nicht gegen geltendes Recht oder behördliche Vorschriften verstößt.</li>
          <li>Cipa Media ist jederzeit berechtigt, Inhalte, die gegen gesetzliche Vorschriften oder diese Nutzungsrichtlinien verstoßen, ohne Vorankündigung zu sperren oder zu löschen.</li>
        </ul>

        <h6 class="fw-bold mt-3">4. Gewerbliche Schutzrechte und Urheberrechte Dritter</h6>
        <ul class="text-muted ps-3 mb-3">
          <li class="mb-1">Du bestätigst und garantierst, dass du über sämtliche erforderlichen Rechte an dem von dir hochgeladenen Content verfügst und durch die Veröffentlichung keine Rechte Dritter (z. B. Urheber-, Marken- oder Persönlichkeitsrechte) verletzt werden.</li>
          <li><strong>Wichtiger Hinweis zu Musik in Videos:</strong> Bitte achte strikt darauf, dass in deinen Filmen keine urheberrechtlich geschützte Musik (auch nicht leise im Hintergrund, z. B. durch Radio, Fernseher oder Streaming-Dienste) zu hören ist.</li>
        </ul>

        <h6 class="fw-bold mt-3">5. Darstellung weiterer Personen (Co-Darsteller)</h6>
        <ul class="text-muted ps-3 mb-3">
          <li class="mb-1">Sind in deinem Content neben dir weitere Personen zu sehen oder zu hören, stellst du sicher, dass dir von jeder beteiligten Person eine schriftliche Einverständniserklärung zur Produktion und kommerziellen Verwertung sowie ein gültiger Altersnachweis (Volljährigkeit zum Zeitpunkt der Aufnahme) vorliegen.</li>
          <li>Auf Anfrage von Cipa Media bist du verpflichtet, diese Nachweise unverzüglich vorzulegen.</li>
        </ul>

        <h6 class="fw-bold mt-3">6. Haftung und Freistellung</h6>
        <ul class="text-muted ps-3 mb-3">
          <li class="mb-1">Sollten Dritte (einschließlich Behörden oder Rechteinhaber) Ansprüche wegen einer Rechtsverletzung durch deinen Content gegen Cipa Media geltend machen, stellst du Cipa Media von sämtlichen Ansprüchen sowie den notwendigen Kosten der Rechtsverteidigung frei.</li>
          <li>Bei begründetem Verdacht auf einen Rechtsverstoß ist Cipa Media berechtigt, Auszahlungen bis zur endgültigen Klärung des Sachverhalts zurückzuhalten und etwaige Schadensersatzforderungen mit bestehenden Guthaben zu verrechnen.</li>
        </ul>

        <h6 class="fw-bold mt-3">7. Schlussbestimmungen</h6>
        <ul class="text-muted ps-3 mb-0">
          <li class="mb-1">Diese Nutzungsrichtlinien ergänzen die bestehenden vertraglichen Vereinbarungen zwischen dir und Cipa Media.</li>
          <li>Sollte eine Bestimmung dieser Vereinbarung unwirksam sein oder werden, bleibt die Wirksamkeit der übrigen Bestimmungen davon unberührt.</li>
        </ul>

        <?php if ($needsRulesAccept): ?>
        <hr>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="chk-accept-content-rules" value="1">
          <label class="form-check-label fw-semibold" for="chk-accept-content-rules">
            Ich habe die Nutzungsrichtlinien zur Vermarktung meines Contents gelesen und akzeptiere diese verbindlich.
          </label>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-sm btn-outline-kkc" data-bs-dismiss="modal">Schließen</button>
        <?php if ($needsRulesAccept): ?>
        <button type="button" class="btn btn-sm btn-primary-kkc" id="btn-submit-content-rules">
          <i class="bi bi-check2-circle me-1"></i> Zustimmung verbindlich speichern
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($moviesRejected > 0 || $albumsRejected > 0): ?>
<!-- Wichtiger Hinweis bei abgelehnten Inhalten oder Konvertierungsfehlern -->
<div class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 shadow-sm">
  <div class="d-flex align-items-center gap-3">
    <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0"></i>
    <div>
      <div class="fw-bold">Bitte überprüfe deine abgelehnten oder fehlerhaften Inhalte</div>
      <div class="small">
        <?php if ($moviesRejected > 0 && $albumsRejected > 0): ?>
          Aktuell <?= $moviesRejected === 1 ? 'erfordert <strong>1 Film</strong>' : 'erfordern <strong>' . $moviesRejected . ' Filme</strong>' ?> sowie <?= $albumsRejected === 1 ? '<strong>1 Fotoalbum</strong>' : '<strong>' . $albumsRejected . ' Fotoalben</strong>' ?> deine Aufmerksamkeit (abgelehnt, gesperrt oder Konvertierungsfehler).
        <?php elseif ($moviesRejected > 0): ?>
          Aktuell <?= $moviesRejected === 1 ? 'erfordert <strong>1 Film</strong>' : 'erfordern <strong>' . $moviesRejected . ' Filme</strong>' ?> deine Aufmerksamkeit (abgelehnt, gesperrt oder Konvertierungsfehler). Klicke auf den jeweiligen Film, um den Grund einzusehen und ihn erneut einzureichen.
        <?php else: ?>
          Aktuell <?= $albumsRejected === 1 ? 'erfordert <strong>1 Fotoalbum</strong>' : 'erfordern <strong>' . $albumsRejected . ' Fotoalben</strong>' ?> deine Aufmerksamkeit (Korrektur nötig oder abgelehnt). Klicke auf das Album, um die Hinweise einzusehen.
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($moviesRejected > 0): ?>
    <a href="#/movies" class="btn btn-sm btn-danger fw-semibold">
      <i class="bi bi-film me-1"></i> Zu meinen Filmen (<?= $moviesRejected ?>)
    </a>
    <?php endif; ?>
    <?php if ($albumsRejected > 0): ?>
    <a href="#/photo-albums" class="btn btn-sm btn-danger fw-semibold">
      <i class="bi bi-images me-1"></i> Zu meinen Fotoalben (<?= $albumsRejected ?>)
    </a>
    <?php endif; ?>
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
  <div class="col-lg-5<?= $actorsTotal > 1 ? ' position-relative' : '' ?>">

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
    <!-- Mehrere Darsteller-Profile im Überblick (Höhe auf linken Block begrenzt & scrollbar) -->
    <div class="ec-actors-col-match">
      <div class="card-kkc mb-0">
        <div class="card-kkc-header flex-shrink-0">
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
        <div class="card-kkc-body p-0 d-flex flex-column min-h-0">
          <div class="table-responsive ec-dashboard-actors-scroll">
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
                      ? $mcpUrl . '/ProfilePicture/' . rawurlencode($actor['avatar_fsk16']) . '?w=60'
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
                      <img src="<?= View::e($avatarUrl) ?>" alt="" class="rounded-circle object-fit-cover border flex-shrink-0" style="width: 28px; height: 28px;" loading="lazy">
                      <?php else: ?>
                      <span class="sidebar-actor-avatar flex-shrink-0" style="width: 28px; height: 28px;"><?= View::e($actorInitials) ?></span>
                      <?php endif; ?>
                      <a href="#/actor?id=<?= (int)$actor['id'] ?>" class="fw-semibold text-decoration-none text-body text-truncate"><?= View::e($actor['username']) ?></a>
                    </div>
                  </td>
                  <td class="text-muted small text-nowrap">
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
    </div>
    <?php endif; ?>

    <?php if ($actorsTotal <= 1): ?>
    <!-- Account-Status & Content-Richtlinien Kachel (bei 0 oder 1 Profil direkt in der rechten Spalte) -->
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
            <div class="text-muted small mb-2">
              Du hast den Nutzungsrichtlinien zur Vermarktung deines Contents zugestimmt (am <?= View::e(date('d.m.Y H:i', strtotime($acceptContentRulesDate))) ?> Uhr).
            </div>
            <button type="button" class="btn btn-sm btn-outline-kkc py-1" data-bs-toggle="modal" data-bs-target="#modal-content-rules">
              <i class="bi bi-journal-text me-1"></i> Richtlinien einsehen
            </button>
          </div>
          <?php else: ?>
          <i class="bi bi-exclamation-circle-fill text-warning fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Zustimmung zu den Content-Richtlinien ausstehend</div>
            <div class="text-muted small mb-2">
              Bitte bestätige die aktuellen Nutzungsrichtlinien zur Vermarktung deines Contents.
            </div>
            <button type="button" class="btn btn-sm btn-primary-kkc py-1" data-bs-toggle="modal" data-bs-target="#modal-content-rules">
              <i class="bi bi-journal-check me-1"></i> Richtlinien anzeigen &amp; bestätigen
            </button>
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
    <?php endif; ?>

  </div>
</div>

<!-- =========================================================================
     Persönliche Entwickler-Kontakt- & Fehlermeldungs-Box (Testlauf / Beta)
     ========================================================================= -->
<div class="card-kkc mt-4 mb-0 overflow-hidden" id="ec-creator-feedback-card">
  <div class="card-kkc-body p-0">
    <div class="row g-0">
      <!-- Linke Spalte: Persönliches Profil (Martin Zimmermann) & Testlauf-Hinweis -->
      <div class="col-lg-4 p-4 d-flex flex-column justify-content-between" style="background: linear-gradient(160deg, rgba(66, 151, 215, 0.08) 0%, rgba(66, 151, 215, 0.025) 100%); border-right: 1px solid var(--kkc-border);">
        <div>
          <!-- Testlauf-Badge -->
          <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill ec-bg-primary-subtle ec-text-primary fw-semibold small mb-4">
            <i class="bi bi-stars"></i>
            <span>Neuer Bereich im Testlauf &bull; Direkt-Draht</span>
          </div>

          <!-- Martin Zimmermann Avatar & Name (offen & harmonisch ohne Kasten-in-Kasten) -->
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="position-relative flex-shrink-0">
              <img src="assets/img/ceo-150x150.jpg"
                   onerror="this.onerror=null;this.src='https://erocms.net/images/ceo-150x150.jpg';"
                   alt="Martin Zimmermann"
                   class="rounded-circle object-fit-cover"
                   style="width: 82px; height: 82px; border: 3px solid var(--kkc-surface); box-shadow: 0 6px 18px rgba(2, 132, 199, 0.22);">
              <span class="position-absolute" style="bottom: -2px; right: -2px; font-size: 1.4rem; line-height: 1; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">👋</span>
            </div>
            <div>
              <div class="fw-bold text-body" style="font-size: 1.12rem; line-height: 1.2;">Martin Zimmermann</div>
              <div class="ec-text-primary fw-semibold mt-1" style="font-size: 0.86rem;">Chefentwickler (CTO)</div>
              <div class="text-muted mt-1" style="font-size: 0.78rem;">Dein direkter Draht in die Technik</div>
            </div>
          </div>

          <h5 class="fw-bold mb-2">
            <?= $greetingName !== '' ? 'Hallo ' . View::e($greetingName) . ', ist dir etwas aufgefallen?' : 'Fehler entdeckt oder eine Idee?' ?>
          </h5>
          <p class="text-muted mb-3" style="font-size: 0.9rem; line-height: 1.6;">
            Dieser neue EroCloud-Partnerbereich befindet sich aktuell noch im <strong>Testlauf</strong>. Wenn dir hier irgendwo ein Fehler auffällt, etwas noch nicht wie gewohnt funktioniert oder du dir eine Verbesserung wünschst, kannst du es mir hier direkt melden!
          </p>
        </div>

        <div class="d-flex flex-column gap-2 pt-3 border-top small text-muted" style="border-color: var(--kkc-border) !important;">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
            <span>Landet automatisch direkt in meiner Entwicklungsumgebung</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-cpu-fill ec-text-primary flex-shrink-0"></i>
            <span>Zentrales Error-Log &amp; Diagnosedaten werden automatisch mitgesendet</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-reply-fill ec-text-primary flex-shrink-0"></i>
            <span>Meine Antwort erhältst du direkt hier auf deiner Startseite</span>
          </div>
        </div>
      </div>

      <!-- Rechte Spalte: Direkt-Meldeformular -->
      <div class="col-lg-8 p-4">
        <!-- Erfolgs-Ansicht nach dem Absenden -->
        <div id="ec-feedback-success-box" class="d-none py-3 px-2">
          <div class="p-4 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25">
            <div class="d-flex align-items-start gap-3">
              <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 46px; height: 46px;">
                <i class="bi bi-check-lg fs-4"></i>
              </div>
              <div class="flex-grow-1">
                <h5 class="fw-bold text-success mb-1" id="ec-feedback-success-title">Vielen Dank für deine Nachricht!</h5>
                <p class="mb-3 text-body" style="font-size: 0.95rem; line-height: 1.5;" id="ec-feedback-success-text">
                  Deine Meldung ist direkt bei mir in der Entwicklung eingegangen.
                </p>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" id="ec-feedback-reset-btn">
                  <i class="bi bi-plus-circle me-1"></i> Weitere Meldung oder Idee senden
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Eingabe-Formular -->
        <form id="ec-creator-feedback-form" onsubmit="return false;">
          <label class="form-label fw-semibold small text-uppercase text-muted mb-2">1. Worum geht es?</label>
          <div class="row g-2 mb-3">
            <div class="col-sm-4">
              <input type="radio" class="btn-check" name="ec_feedback_category" id="ec_fb_cat_bug" value="bug" checked autocomplete="off">
              <label class="ec-feedback-type-label type-bug rounded-3 p-2 px-3 w-100 d-flex align-items-center gap-2" for="ec_fb_cat_bug">
                <i class="bi bi-bug-fill text-danger fs-5"></i>
                <div class="lh-sm">
                  <div class="fw-bold small">Fehler / Problem</div>
                  <small class="text-muted" style="font-size: 0.72rem;">Etwas klappt nicht</small>
                </div>
              </label>
            </div>
            <div class="col-sm-4">
              <input type="radio" class="btn-check" name="ec_feedback_category" id="ec_fb_cat_idea" value="idea" autocomplete="off">
              <label class="ec-feedback-type-label type-idea rounded-3 p-2 px-3 w-100 d-flex align-items-center gap-2" for="ec_fb_cat_idea">
                <i class="bi bi-lightbulb-fill text-warning fs-5"></i>
                <div class="lh-sm">
                  <div class="fw-bold small">Idee / Wunsch</div>
                  <small class="text-muted" style="font-size: 0.72rem;">Verbesserungsvorschlag</small>
                </div>
              </label>
            </div>
            <div class="col-sm-4">
              <input type="radio" class="btn-check" name="ec_feedback_category" id="ec_fb_cat_question" value="question" autocomplete="off">
              <label class="ec-feedback-type-label type-question rounded-3 p-2 px-3 w-100 d-flex align-items-center gap-2" for="ec_fb_cat_question">
                <i class="bi bi-chat-heart-fill ec-text-primary fs-5"></i>
                <div class="lh-sm">
                  <div class="fw-bold small">Frage / Feedback</div>
                  <small class="text-muted" style="font-size: 0.72rem;">Rückmeldung zum Testlauf</small>
                </div>
              </label>
            </div>
          </div>

          <div class="mb-3">
            <label for="ec-feedback-message" class="form-label fw-semibold small text-uppercase text-muted mb-1" id="ec-feedback-label">
              2. Welches Problem oder welcher Fehler ist dir im neuen Bereich aufgefallen?
            </label>
            <textarea class="form-control rounded-3" id="ec-feedback-message" name="message" rows="6" placeholder="Beschreibe mir kurz, wo das Problem auftritt und was genau passiert (z. B. beim Film-Upload, in den Fotoalben, bei deinen Profilen...)" required style="font-size: 0.92rem; min-height: 145px;"></textarea>
          </div>

          <!-- 3. Dateianhänge (max. 3 Dateien: Screenshots / Fotos / PDF) -->
          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
              <label class="form-label fw-semibold small text-uppercase text-muted mb-0">
                3. Optional: Screenshots oder PDF anhängen <span class="fw-normal text-lowercase">(max. 3 Dateien)</span>
              </label>
              <span class="text-muted" style="font-size: 0.74rem;">
                <i class="bi bi-clipboard-check me-1"></i>Tipp: Du kannst Screenshots auch direkt mit <kbd class="py-0 px-1">Strg+V</kbd> einfügen
              </span>
            </div>
            <input type="file" id="ec-feedback-files" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/jpeg,image/png,image/webp,image/gif,application/pdf" multiple class="d-none">
            <div class="d-flex align-items-center flex-wrap gap-2">
              <button type="button" id="ec-feedback-add-file-btn" class="btn btn-sm btn-outline-kkc rounded-3 px-3 py-1 d-inline-flex align-items-center gap-2">
                <i class="bi bi-paperclip"></i>
                <span>Fotos / PDF auswählen</span>
                <span class="badge bg-secondary bg-opacity-25 text-body rounded-pill" id="ec-feedback-file-count-badge">0 / 3</span>
              </button>
              <span class="text-muted" style="font-size: 0.75rem;">Erlaubt: JPG, PNG, WEBP, GIF, PDF (max. 6 MB pro Datei)</span>
            </div>
            <div id="ec-feedback-file-list" class="d-flex flex-wrap gap-2 mt-2"></div>
          </div>

          <div id="ec-feedback-error-alert" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size: 0.78rem;">
              <i class="bi bi-shield-check text-success me-1"></i>
              <?php if ($singleCreatorProfileName !== ''): ?>
                Creator-Profil: <strong><?= View::e($singleCreatorProfileName) ?></strong> &amp; zentrales Error-Log werden automatisch beigefügt.
              <?php else: ?>
                Zentrales Error-Log &amp; technische Diagnosedaten werden automatisch beigefügt.
              <?php endif; ?>
            </span>
            <button type="submit" id="ec-feedback-submit-btn" class="btn btn-primary-kkc rounded-pill px-4 py-2 fw-semibold shadow-sm">
              <i class="bi bi-send-fill me-2"></i>Nachricht absenden
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Verlauf der gesendeten Nachrichten & Rückmeldungen aus der Entwicklung -->
    <div id="ec-creator-feedback-history-wrap" class="d-none border-top p-4" style="background: rgba(66, 151, 215, 0.025); border-color: var(--kkc-border) !important;">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-chat-left-quote-fill ec-text-primary fs-5"></i>
          <h6 class="fw-bold mb-0">Deine gesendeten Nachrichten &amp; meine Rückmeldungen</h6>
          <span id="ec-fb-history-unread-badge" class="badge rounded-pill bg-danger d-none">Neu</span>
        </div>
        <span class="text-muted small">Hier siehst du den aktuellen Status deiner Meldungen &amp; Ideen</span>
      </div>

      <!-- Aktive Meldungen & ungelesene Rückmeldungen (direkt sichtbar) -->
      <div id="ec-creator-feedback-history-list" class="d-flex flex-column gap-3"></div>

      <!-- Zugeklapptes Akkordeon für bereits als gelesen bestätigte Rückmeldungen -->
      <div id="ec-creator-feedback-read-accordion-wrap" class="d-none">
        <div id="ec-fb-read-accordion-toggle" class="rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm ec-fb-read-accordion-toggle" role="button" tabindex="0" aria-expanded="false">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
              <i class="bi bi-check2-all"></i>
            </span>
            <span class="fw-semibold small text-body" id="ec-fb-read-accordion-title">Bereits gelesene Rückmeldungen</span>
            <span id="ec-fb-read-count-badge" class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25">0</span>
          </div>
          <div class="d-flex align-items-center gap-2 text-muted small">
            <span id="ec-fb-read-toggle-label" style="font-size: 0.78rem;">Bei Bedarf aufklappen</span>
            <i class="bi bi-chevron-down ec-fb-read-chevron"></i>
          </div>
        </div>
        <div id="ec-fb-read-accordion-body" style="display: none;">
          <div id="ec-fb-read-accordion-list" class="d-flex flex-column gap-3 pt-3"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($actorsTotal > 1): ?>
<!-- Account-Status & Content-Richtlinien Kachel (bei mehreren Profilen unterhalb der Kontakt-Box) -->
<div class="card-kkc mt-4 mb-0">
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
    <div class="row g-3 align-items-center">
      <div class="col-lg-6">
        <div class="d-flex align-items-start gap-3">
          <?php if (!$needsRulesAccept): ?>
          <i class="bi bi-check-circle-fill text-success fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Content-Richtlinien akzeptiert</div>
            <div class="text-muted small mb-2">
              Du hast den Nutzungsrichtlinien zur Vermarktung deines Contents zugestimmt (am <?= View::e(date('d.m.Y H:i', strtotime($acceptContentRulesDate))) ?> Uhr).
            </div>
            <button type="button" class="btn btn-sm btn-outline-kkc py-1" data-bs-toggle="modal" data-bs-target="#modal-content-rules">
              <i class="bi bi-journal-text me-1"></i> Richtlinien einsehen
            </button>
          </div>
          <?php else: ?>
          <i class="bi bi-exclamation-circle-fill text-warning fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Zustimmung zu den Content-Richtlinien ausstehend</div>
            <div class="text-muted small mb-2">
              Bitte bestätige die aktuellen Nutzungsrichtlinien zur Vermarktung deines Contents.
            </div>
            <button type="button" class="btn btn-sm btn-primary-kkc py-1" data-bs-toggle="modal" data-bs-target="#modal-content-rules">
              <i class="bi bi-journal-check me-1"></i> Richtlinien anzeigen &amp; bestätigen
            </button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
          <div class="d-flex align-items-start gap-3 flex-grow-1" style="min-width: 240px;">
            <i class="bi bi-info-circle-fill ec-text-primary fs-4 lh-1 mt-1"></i>
            <div>
              <div class="fw-semibold">Support &amp; Hilfe bei Fragen</div>
              <div class="text-muted small">
                Bei Fragen zur Freischaltung deiner Darsteller-Profile oder zur Konvertierung deiner Filme steht dir unser Support jederzeit zur Verfügung.
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-kkc py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modal-support">
            <i class="bi bi-headset me-1"></i> Support kontaktieren
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
