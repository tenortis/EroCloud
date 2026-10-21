<?php
declare(strict_types=1);

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\View;

$monate = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
];
$aktMonatName = $monate[(int)date('n')] ?? date('F');
$aktJahr = date('Y');
?>
<!-- Page Header & Greeting -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Meine Startseite</h1>
    <p class="text-muted mb-0">
      Herzlich willkommen zurück in Ihrer EroCloud. Ihre persönlichen Stammdaten (Name und Anschrift) sind für Endkunden nicht sichtbar – angezeigt werden ausschließlich die Angaben aus Ihren Darsteller-Profilen.
    </p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge-kkc badge-kkc-info px-3 py-2">
      <i class="bi bi-calendar3 me-1"></i> Zeitraum: <?= View::e($aktMonatName . ' ' . $aktJahr) ?>
    </span>
  </div>
</div>

<!-- KPI Cards Row (Kachel-Übersicht) -->
<div class="row g-3 mb-4">
  <div class="col-xl-3 col-md-6">
    <a href="#/movies" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box ec-bg-primary-subtle ec-text-primary">
          <i class="bi bi-collection-play-fill"></i>
        </div>
        <div>
          <div class="kpi-val">24</div>
          <div class="kpi-label">Filme in der Cloud</div>
          <small class="text-success fw-semibold">
            <i class="bi bi-check-circle me-1"></i>22 freigegeben / 2 in Prüfung
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
          <div class="kpi-val">12</div>
          <div class="kpi-label">Fotoalben online</div>
          <small class="text-muted">Gesamt: 340 Fotos in der Cloud</small>
        </div>
      </div>
    </a>
  </div>

  <div class="col-xl-3 col-md-6">
    <a href="#/actors" class="text-decoration-none d-block h-100">
      <div class="card-kkc kpi-card h-100 mb-0">
        <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
          <i class="bi bi-people-fill"></i>
        </div>
        <div>
          <div class="kpi-val">3</div>
          <div class="kpi-label">Darsteller-Profile</div>
          <small class="text-success fw-semibold">
            <i class="bi bi-shield-check me-1"></i>Alle Profile aktiv
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
        <div class="kpi-val">2</div>
        <div class="kpi-label">Inhalte in Prüfung</div>
        <small class="text-muted">Werden zeitnah freigeschaltet</small>
      </div>
    </div>
  </div>
</div>

<!-- Main Dashboard Grid -->
<div class="row g-4">
  <!-- Linke Spalte: Schnellstart, Content-Übersicht & Statistiken -->
  <div class="col-lg-7">

    <!-- Schnellaktionen Kachel -->
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-lightning-charge-fill ec-text-primary"></i>
          <span>Schnellzugriff: Content &amp; Profile verwalten</span>
        </div>
        <span class="badge-kkc badge-kkc-neutral">Direktwahl</span>
      </div>
      <div class="card-kkc-body">
        <div class="row g-3">
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
          <div class="col-sm-6">
            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modal-api-key" class="action-tile text-decoration-none">
              <div class="action-tile-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-key-fill"></i>
              </div>
              <div>
                <div class="fw-bold text-body">Mein API-Key</div>
                <small class="text-muted">Schlüssel für den Import auf eigene Webseiten</small>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Aktuelle Aktivitäten / Content-Status (Platzhalter-Tabelle wie im KKC) -->
    <div class="card-kkc mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-clock-history ec-text-primary"></i>
          <span>Zuletzt hochgeladene Inhalte (Platzhalter-Vorschau)</span>
        </div>
        <a href="#/movies" class="btn btn-sm btn-outline-kkc py-1" style="font-size: 0.78rem;">
          Alle Filme anzeigen <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
      <div class="card-kkc-body p-0">
        <div class="table-responsive">
          <table class="kkc-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Darsteller</th>
                <th>Typ</th>
                <th>Status</th>
                <th class="text-end">Aktion</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="font-monospace text-muted">#1048</td>
                <td class="fw-semibold">Heißes Sommer-Date am Pool (Full HD)</td>
                <td>SophiaLove</td>
                <td><span class="badge-kkc badge-kkc-info"><i class="bi bi-film"></i> Film</span></td>
                <td><span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill"></i> Online</span></td>
                <td class="text-end">
                  <a href="#/movie?id=1048" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Film bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
              <tr>
                <td class="font-monospace text-muted">#1047</td>
                <td class="fw-semibold">Exklusive Dessous-Serie (45 Fotos)</td>
                <td>MiaSweet</td>
                <td><span class="badge-kkc badge-kkc-neutral"><i class="bi bi-images"></i> Fotoalbum</span></td>
                <td><span class="badge-kkc badge-kkc-success"><i class="bi bi-check-circle-fill"></i> Online</span></td>
                <td class="text-end">
                  <a href="#/photo-album?id=1047" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Album bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
              <tr>
                <td class="font-monospace text-muted">#1046</td>
                <td class="fw-semibold">Behind the Scenes – Studio Session</td>
                <td>SophiaLove</td>
                <td><span class="badge-kkc badge-kkc-info"><i class="bi bi-film"></i> Film</span></td>
                <td><span class="badge-kkc badge-kkc-warning"><i class="bi bi-hourglass-split"></i> In Prüfung</span></td>
                <td class="text-end">
                  <a href="#/movie?id=1046" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Film bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <!-- Rechte Spalte: Darsteller-Profile im Überblick & Account-Status -->
  <div class="col-lg-5">

    <!-- Darsteller-Profile im Überblick (Statt Amoredea) -->
    <div class="card-kkc">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-people-fill ec-text-primary"></i>
          <span>Ihre Darsteller-Profile</span>
        </div>
        <a href="#/new-actor" class="btn btn-sm btn-outline-kkc py-1" style="font-size: 0.78rem;">
          <i class="bi bi-plus-lg me-1"></i> Profil anlegen
        </a>
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
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="sidebar-actor-avatar">SL</span>
                    <span class="fw-semibold">SophiaLove</span>
                  </div>
                </td>
                <td class="text-muted small">16 Filme · 8 Alben</td>
                <td><span class="badge-kkc badge-kkc-success">Aktiv</span></td>
                <td class="text-end">
                  <a href="#/actor?id=1&amp;name=SophiaLove" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Profil bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="sidebar-actor-avatar">MS</span>
                    <span class="fw-semibold">MiaSweet</span>
                  </div>
                </td>
                <td class="text-muted small">8 Filme · 4 Alben</td>
                <td><span class="badge-kkc badge-kkc-success">Aktiv</span></td>
                <td class="text-end">
                  <a href="#/actor?id=2&amp;name=MiaSweet" class="btn btn-sm btn-outline-kkc py-1 px-2" title="Profil bearbeiten">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Status-Hinweis & Content-Richtlinien Kachel -->
    <div class="card-kkc mb-0">
      <div class="card-kkc-header">
        <div class="card-title">
          <i class="bi bi-shield-check ec-text-primary"></i>
          <span>Account-Status &amp; Content-Richtlinien</span>
        </div>
        <span class="badge-kkc badge-kkc-info">Geprüft</span>
      </div>
      <div class="card-kkc-body">
        <div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom" style="border-color: var(--kkc-border) !important;">
          <i class="bi bi-check-circle-fill text-success fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Content-Richtlinien akzeptiert</div>
            <div class="text-muted small">
              Sie haben den Richtlinien für das Hochladen von Filmen und Fotoalben zugestimmt.
            </div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3">
          <i class="bi bi-info-circle-fill ec-text-primary fs-4 lh-1 mt-1"></i>
          <div>
            <div class="fw-semibold">Hinweis zur Profil-Freischaltung</div>
            <div class="text-muted small mb-2">
              Sobald Sie ein neues Darsteller-Profil anlegen oder ändern, wird dieses durch unser Team geprüft und zeitnah für alle angeschlossenen Partnerportale freigeschaltet.
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
