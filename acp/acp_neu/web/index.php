<?php
declare(strict_types=1);

/**
 * EroCloud ACP (v2.0) - Modernisiertes Admin-Control-Panel
 * Produktions-Shell (Single Page Application Wrapper nach KKC/MCP-Vorbild)
 * Markenfarbe: Pay4Coins-Blau (#4297D7 / #0284C7) | Standard: Hellmodus (Light Mode)
 */

define('SAFE_INC', 1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Csrf;

global $mysql;

$basePath    = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$projectName = defined('PROJECTNAME') ? (string)PROJECTNAME : 'EroCloud';
$companyName = defined('COMPANYNAME') ? (string)COMPANYNAME : 'CIPA Media S.L.U.';
$acpUrl      = defined('ACP_URL') ? (string)ACP_URL : 'https://acp.erocloud.net';
$p4cAcpUrl   = defined('Pay4Coins_ACP_URL') ? (string)Pay4Coins_ACP_URL : 'https://acp.pay4coins.com';

// 1. Logout verarbeiten (analog acp/index.php)
if (isset($_GET['logout'])) {
    if (isset($_SESSION['employee_id'])) {
        if ($_GET['logout'] === 'auto') {
            log_action('Vom System abgemeldet. (auto)');
        } else {
            log_action('Vom System abgemeldet. (manuell)');
        }
    }
    $_SESSION = [];
    @session_destroy();
    if (function_exists('acp_neu_clear_auth_cookie')) {
        acp_neu_clear_auth_cookie();
    }
    header('Location: ' . $basePath . '/Startseite');
    exit;
}

// 2. Login-Formular verarbeiten (analog acp/includes/login.php, modernisiert & PHP 8.4 sicher)
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acp_login'])) {
    $user = trim((string)preg_replace('/[^a-z0-9]/i', '', (string)($_POST['user'] ?? '')));
    $pass = trim(htmlspecialchars(strip_tags((string)($_POST['pass'] ?? '')), ENT_QUOTES, 'ISO-8859-1'));

    if ($user !== '' && $pass !== '') {
        $rsUser = p4c_query(
            "SELECT `id`, `username`, `password`, `rule`, `access_area`
             FROM `employee`
             WHERE `username` = '" . p4c_escape_string($user) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if ($rsUser && p4c_num_rows($rsUser) > 0) {
            $userObj = p4c_fetch_object($rsUser);
            $parts   = explode(':', (string)($userObj->password ?? ''));
            $pwdHash = (string)($parts[0] ?? '');
            $salt    = (string)($parts[1] ?? '');

            if ($pwdHash !== '' && function_exists('saltPassword') && hash_equals($pwdHash, (string)saltPassword($pass, $salt))) {
                $_SESSION['logged_id']            = 'acp';
                $_SESSION['employee_id']          = abs((int)$userObj->id);
                $_SESSION['employee_rule']        = (string)($userObj->rule ?? '');
                $_SESSION['employee_access_area'] = array_map('trim', explode(',', (string)($userObj->access_area ?? '')));
                $_SESSION['employee_username']    = (string)($userObj->username ?? $user);
                $_SESSION['last_activity']        = time();

                if (function_exists('acp_neu_sync_auth_cookie')) {
                    acp_neu_sync_auth_cookie($_SESSION['employee_id'], $_SESSION['employee_username']);
                }
                log_action('Am System angemeldet.');
                header('Location: ' . $basePath . '/Startseite');
                exit;
            } else {
                log_action('Login Error - Passwort falsch! - User: ' . $user);
                $loginError = 'Das eingegebene Passwort ist nicht korrekt.';
            }
        } else {
            log_action('Login Error - User unbekannt! - User: ' . $user);
            $loginError = 'Der angegebene Benutzername ist nicht bekannt.';
        }
    } else {
        $loginError = 'Bitte gib deinen Benutzernamen und dein Passwort ein.';
    }
}

$isLoggedIn = function_exists('is_logged_in') && is_logged_in('acp') === true && !empty($_SESSION['employee_id']);
$csrfToken  = Csrf::token();

// Falls nicht eingeloggt -> Modernes ACP Login-Portal rendern
if (!$isLoggedIn):
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ACP Login – <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/fonts.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/theme.css') ?>">
  <script>
    (function() {
      const savedTheme = localStorage.getItem('ec-acp-theme') || 'light';
      document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
  </script>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card-kkc shadow-lg" style="width: 100%; max-width: 420px;">
    <div class="card-kkc-header d-flex align-items-center justify-content-between py-3">
      <div class="d-flex align-items-center gap-2">
        <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/erocloud_logo.png" alt="EroCloud" style="height: 32px; width: auto;">
        <div>
          <div class="fw-bold lh-1" style="font-size: 0.95rem;"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> ACP</div>
          <small class="text-muted" style="font-size: 0.72rem;">Admin-Control-Panel (v2.0)</small>
        </div>
      </div>
      <span class="badge-kkc badge-kkc-info"><i class="bi bi-shield-lock-fill"></i> Admin</span>
    </div>
    <div class="card-kkc-body p-4">
      <h5 class="fw-bold mb-1">Willkommen im neuen ACP</h5>
      <p class="text-muted small mb-4">Melde dich mit deinen Admin-Zugangsdaten an, um fortzufahren.</p>

      <?php if ($loginError !== ''): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>

      <form method="post" action="" autocomplete="on">
        <input type="hidden" name="acp_login" value="1">
        <div class="mb-3">
          <label for="acp-login-user" class="form-label small fw-semibold">Benutzername</label>
          <div class="input-group">
            <span class="input-group-text bg-transparent"><i class="bi bi-person-fill text-muted"></i></span>
            <input type="text" id="acp-login-user" name="user" class="form-control" placeholder="Dein Admin-Username" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label for="acp-login-pass" class="form-label small fw-semibold">Passwort</label>
          <div class="input-group">
            <span class="input-group-text bg-transparent"><i class="bi bi-key-fill text-muted"></i></span>
            <input type="password" id="acp-login-pass" name="pass" class="form-control" placeholder="••••••••" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary-kkc w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-1"></i> Jetzt im ACP einloggen
        </button>
      </form>
    </div>
    <div class="px-4 py-3 border-top text-center small text-muted" style="border-color: var(--kkc-border) !important;">
      <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> ist ein Service der <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>
</body>
</html>
<?php
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
exit;
endif;

// ============================================================================
// 3. EINGELOGGTER ZUSTAND: Admin-Stammdaten & Live-Zähler für die Sidebar laden
// ============================================================================
$employeeId       = abs((int)$_SESSION['employee_id']);
$employeeUsername = (string)($_SESSION['employee_username'] ?? 'Admin');
$employeeRule     = (string)($_SESSION['employee_rule'] ?? 'Administrator');

$initials = mb_strtoupper(mb_substr(trim($employeeUsername), 0, 2));
if ($initials === '') {
    $initials = 'AD';
}

// Live-Zähler für die ACP-Sidebar (exakt wie in acp/index.php)
$rsMoviesChecking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `movie_checked`='0000-00-00 00:00:00' AND `released`='1' AND `convert_status` > '1';", __FILE__, __LINE__);
$countMoviesChecking = $rsMoviesChecking ? abs((int)(p4c_fetch_object($rsMoviesChecking)->cnt ?? 0)) : 0;

$rsMoviesBlocked = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` > '1' AND (`released`='2' OR `status`!='active');", __FILE__, __LINE__);
$countMoviesBlocked = $rsMoviesBlocked ? abs((int)(p4c_fetch_object($rsMoviesBlocked)->cnt ?? 0)) : 0;

$rsPhotoAlbumsChecking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `album_checked`='0000-00-00 00:00:00' AND `released`='1';", __FILE__, __LINE__);
$countPhotoAlbumsChecking = $rsPhotoAlbumsChecking ? abs((int)(p4c_fetch_object($rsPhotoAlbumsChecking)->cnt ?? 0)) : 0;

$countCoActorsChecking = 0;
if (class_exists('CoActorManager')) {
    \CoActorManager::ensureSchema();
    $rsCoActorsChecking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `co_actors` WHERE `status` != 'deleted' AND `checked_status`='under_review';", __FILE__, __LINE__);
    $countCoActorsChecking = $rsCoActorsChecking ? abs((int)(p4c_fetch_object($rsCoActorsChecking)->cnt ?? 0)) : 0;
}

$countContentChecking = $countMoviesChecking + $countPhotoAlbumsChecking + $countCoActorsChecking;

$rsMoviesOnline = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_online`;", __FILE__, __LINE__);
$countMoviesOnline = $rsMoviesOnline ? abs((int)(p4c_fetch_object($rsMoviesOnline)->cnt ?? 0)) : 0;

$rsMoviesDrafts = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released`='0' AND `status`!='deleted';", __FILE__, __LINE__);
$countMoviesDrafts = $rsMoviesDrafts ? abs((int)(p4c_fetch_object($rsMoviesDrafts)->cnt ?? 0)) : 0;

$rsPhotoAlbumsOnline = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_online`;", __FILE__, __LINE__);
$countPhotoAlbumsOnline = $rsPhotoAlbumsOnline ? abs((int)(p4c_fetch_object($rsPhotoAlbumsOnline)->cnt ?? 0)) : 0;

$countContentOnline = $countMoviesOnline + $countPhotoAlbumsOnline;

$rsNewBanners = p4c_query("SELECT COUNT(*) AS `cnt` FROM `ads_media` WHERE `new_filename`!='' AND `rejected`='0';", __FILE__, __LINE__);
$countNewBanners = $rsNewBanners ? abs((int)(p4c_fetch_object($rsNewBanners)->cnt ?? 0)) : 0;

$rsActorCams = p4c_query("SELECT COUNT(*) AS `cnt` FROM `actor_cams` WHERE `datetime` >= '" . date('Y-m-d H:i:s', strtotime('-80 seconds')) . "';", __FILE__, __LINE__);
$countActorCams = $rsActorCams ? abs((int)(p4c_fetch_object($rsActorCams)->cnt ?? 0)) : 0;

$rsNewActors = p4c_query("SELECT COUNT(*) AS `cnt` FROM `actors` WHERE `created_datetime` >= '" . date('Y-m-d H:i:s', strtotime('-5 days')) . "' AND `status`='inactive';", __FILE__, __LINE__);
$countNewActors = $rsNewActors ? abs((int)(p4c_fetch_object($rsNewActors)->cnt ?? 0)) : 0;

$sessionRemainingSeconds = 1800;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ACP – <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> Admin-Bereich</title>

  <!-- CSRF-Schutz Token, Basis-Pfad & Session-Dauer für Vanilla JS -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="app-base-path" content="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="acp-session-seconds" content="<?= (int)$sessionRemainingSeconds ?>">

  <!-- Lokale Stylesheets (100% DSGVO / Keine externen CDNs) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/fonts.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/theme.css') ?>">

  <!-- Theme-Soforterkennung (Standard: Light Mode) -->
  <script>
    (function() {
      const savedTheme = localStorage.getItem('ec-acp-theme') || 'light';
      document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
  </script>
</head>
<body>

<div class="app-wrapper">

  <!-- ========================================================================
       SIDEBAR (Vollständige Admin-Navigation mit Rechteprüfung & Live-Badges)
       ======================================================================== -->
  <aside class="app-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
      <a href="#/startseite" class="d-flex align-items-center gap-2 text-decoration-none">
        <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/img/erocloud_logo.png" alt="EroCloud" style="height: 34px; width: auto; object-fit: contain;">
        <div>
          <div class="fw-bold lh-1" style="font-size: 0.95rem; color: var(--kkc-text);">EroCloud ACP</div>
          <small style="font-size: 0.68rem; color: var(--kkc-text-muted);">Admin Control Panel</small>
        </div>
      </a>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <div class="sidebar-heading">Administration</div>

      <!-- 1. Startseite -->
      <a href="#/startseite" class="sidebar-link active" data-route="#/startseite">
        <i class="bi bi-house-door-fill"></i>
        <span class="text-nowrap">Startseite</span>
      </a>

      <?php if (acp_has_access('content_check')): ?>
      <!-- 2. Content prüfen (Standardmäßig geschlossen, mit echtem Zähler) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-checking" role="button" aria-expanded="false" aria-controls="submenu-checking">
        <i class="bi bi-shield-check"></i>
        <span class="text-nowrap">Content prüfen</span>
        <span id="sidebar-checking-count" class="sidebar-count-badge ms-auto me-1 <?= $countContentChecking > 0 ? 'bg-warning text-dark border-warning' : '' ?>" title="Inhalte in Prüfung"><?= (int)$countContentChecking ?></span>
        <i class="bi bi-chevron-right chevron-icon ms-0"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-checking">
        <a href="#/movies-checking" class="sidebar-link" data-route="#/movies-checking">
          <i class="bi bi-film"></i>
          <span class="text-nowrap">Filme prüfen</span>
          <span id="sidebar-movies-checking-count" class="sidebar-count-badge ms-auto"><?= (int)$countMoviesChecking ?></span>
        </a>
        <a href="#/photo-albums-checking" class="sidebar-link" data-route="#/photo-albums-checking">
          <i class="bi bi-images"></i>
          <span class="text-nowrap">Fotoalben prüfen</span>
          <span id="sidebar-albums-checking-count" class="sidebar-count-badge ms-auto"><?= (int)$countPhotoAlbumsChecking ?></span>
        </a>
        <a href="#/co-actors-checking" class="sidebar-link" data-route="#/co-actors-checking">
          <i class="bi bi-person-badge"></i>
          <span class="text-nowrap">Drehpartner &amp; ID</span>
          <span id="sidebar-coactors-checking-count" class="sidebar-count-badge ms-auto"><?= (int)$countCoActorsChecking ?></span>
        </a>
        <a href="#/movies-blocked" class="sidebar-link" data-route="#/movies-blocked">
          <i class="bi bi-slash-circle"></i>
          <span class="text-nowrap">Filme abgelehnt</span>
          <span id="sidebar-movies-blocked-count" class="sidebar-count-badge ms-auto"><?= (int)$countMoviesBlocked ?></span>
        </a>
        <a href="#/ai-compliance" class="sidebar-link" data-route="#/ai-compliance">
          <i class="bi bi-cpu"></i>
          <span class="text-nowrap">KI-Analyse &amp; BRAM</span>
        </a>
      </div>
      <?php endif; ?>

      <?php if (acp_has_access('content_online')): ?>
      <!-- 3. Content Online (Standardmäßig geschlossen) -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-online" role="button" aria-expanded="false" aria-controls="submenu-online">
        <i class="bi bi-cloud-check-fill"></i>
        <span class="text-nowrap">Content Online</span>
        <span id="sidebar-online-count" class="sidebar-count-badge ms-auto me-1" title="Filme &amp; Fotoalben online"><?= (int)$countContentOnline ?></span>
        <i class="bi bi-chevron-right chevron-icon ms-0"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-online">
        <a href="#/movies-online" class="sidebar-link" data-route="#/movies-online">
          <i class="bi bi-collection-play-fill"></i>
          <span class="text-nowrap">Filme online</span>
          <span class="sidebar-count-badge ms-auto"><?= (int)$countMoviesOnline ?></span>
        </a>
        <a href="#/movies-drafts" class="sidebar-link" data-route="#/movies-drafts">
          <i class="bi bi-clock-history"></i>
          <span class="text-nowrap">Filme in Planung</span>
          <span class="sidebar-count-badge ms-auto"><?= (int)$countMoviesDrafts ?></span>
        </a>
        <a href="#/photo-albums-online" class="sidebar-link" data-route="#/photo-albums-online">
          <i class="bi bi-journal-album"></i>
          <span class="text-nowrap">Fotoalben online</span>
          <span class="sidebar-count-badge ms-auto"><?= (int)$countPhotoAlbumsOnline ?></span>
        </a>
        <a href="#/content-cleanup" class="sidebar-link" data-route="#/content-cleanup">
          <i class="bi bi-trash3"></i>
          <span class="text-nowrap">Content-Bereinigung</span>
        </a>
        <a href="#/storage-analytics" class="sidebar-link" data-route="#/storage-analytics">
          <i class="bi bi-hdd-network"></i>
          <span class="text-nowrap">Speicher &amp; Traffic</span>
        </a>
      </div>
      <?php endif; ?>

      <?php if (acp_has_access('actors')): ?>
      <!-- 4. Profile -->
      <a href="#/actors" class="sidebar-link" data-route="#/actors">
        <i class="bi bi-person-bounding-box"></i>
        <span class="text-nowrap">Profile</span>
        <?php if ($countNewActors > 0): ?>
        <span class="sidebar-count-badge ms-auto bg-warning text-dark border-warning" title="<?= (int)$countNewActors ?> neue Profile in den letzten 5 Tagen inaktiv"><?= (int)$countNewActors ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if (acp_has_access('websites')): ?>
      <!-- 5. Webseiten -->
      <a href="#/sites" class="sidebar-link" data-route="#/sites">
        <i class="bi bi-globe2"></i>
        <span class="text-nowrap">Webseiten</span>
      </a>
      <?php endif; ?>

      <?php if (acp_has_access('affiliate_program')): ?>
      <!-- 6. Partnerprogramm -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-banners" role="button" aria-expanded="false" aria-controls="submenu-banners">
        <i class="bi bi-megaphone-fill"></i>
        <span class="text-nowrap">Partnerprogramm</span>
        <span class="sidebar-count-badge ms-auto me-1"><?= (int)$countNewBanners ?></span>
        <i class="bi bi-chevron-right chevron-icon ms-0"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-banners">
        <a href="#/webmaster-partner" class="sidebar-link" data-route="#/webmaster-partner">
          <i class="bi bi-people-fill"></i>
          <span class="text-nowrap">Aktive Webmaster</span>
        </a>
        <a href="#/banners" class="sidebar-link" data-route="#/banners">
          <i class="bi bi-layers-fill"></i>
          <span class="text-nowrap">Aktive Banner</span>
        </a>
        <a href="#/banners-new" class="sidebar-link" data-route="#/banners-new">
          <i class="bi bi-stars"></i>
          <span class="text-nowrap">Neue Banner</span>
          <span class="sidebar-count-badge ms-auto"><?= (int)$countNewBanners ?></span>
        </a>
      </div>
      <?php endif; ?>

      <?php if (acp_has_access('webcams')): ?>
      <!-- 7. Webcams -->
      <a href="#/webcams" class="sidebar-link" data-route="#/webcams">
        <i class="bi bi-camera-video-fill"></i>
        <span class="text-nowrap">Webcams</span>
        <span class="sidebar-count-badge ms-auto"><?= (int)$countActorCams ?></span>
      </a>
      <?php endif; ?>

      <?php if (acp_has_access('merchants')): ?>
      <!-- 8. P4C-Merchants -->
      <a href="#/merchants" class="sidebar-link" data-route="#/merchants">
        <i class="bi bi-briefcase-fill"></i>
        <span class="text-nowrap">P4C-Merchants</span>
      </a>
      <?php endif; ?>

      <?php if (acp_has_access('users')): ?>
      <!-- 9. Cloud-User -->
      <a href="#/users" class="sidebar-link" data-route="#/users">
        <i class="bi bi-people"></i>
        <span class="text-nowrap">Cloud-User</span>
      </a>
      <?php endif; ?>

      <?php if (acp_has_access('point_system')): ?>
      <!-- 10. Punktesystem -->
      <a href="#/pointssystem" class="sidebar-link" data-route="#/pointssystem">
        <i class="bi bi-star-fill"></i>
        <span class="text-nowrap">Punktesystem</span>
      </a>
      <?php endif; ?>

      <?php if (acp_has_access('chats') || acp_has_access('all_messages')): ?>
      <!-- 11. Chats -->
      <a class="sidebar-link sidebar-submenu-toggle" data-bs-toggle="collapse" href="#submenu-chats" role="button" aria-expanded="false" aria-controls="submenu-chats">
        <i class="bi bi-chat-dots-fill"></i>
        <span class="text-nowrap">Chats</span>
        <i class="bi bi-chevron-right chevron-icon"></i>
      </a>
      <div class="collapse sidebar-submenu" id="submenu-chats">
        <?php if (acp_has_access('all_messages')): ?>
        <a href="#/all-chats" class="sidebar-link" data-route="#/all-chats">
          <i class="bi bi-chat-left-text-fill"></i>
          <span class="text-nowrap">Alle Nachrichten</span>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (acp_has_access('error_log')): ?>
      <!-- 12. System & Technik -->
      <div class="sidebar-heading mt-3">System &amp; Technik</div>

      <a href="#/system-settings" class="sidebar-link" data-route="#/system-settings">
        <i class="bi bi-sliders"></i>
        <span class="text-nowrap">Impressum &amp; E-Mail</span>
      </a>

      <a href="#/errors" class="sidebar-link" data-route="#/errors">
        <i class="bi bi-bug-fill"></i>
        <span class="text-nowrap">Error-Log</span>
      </a>
      <?php endif; ?>

      <!-- 13. Pay4Coins ACP Verknüpfung -->
      <div class="sidebar-heading mt-3">Externe Systeme</div>
      <div class="sidebar-p4c-box text-center">
        <a href="<?= htmlspecialchars($p4cAcpUrl, ENT_QUOTES, 'UTF-8') ?>" target="pay4coins_acp" rel="noopener" class="text-decoration-none d-block">
          <div class="fw-bold mb-1" style="font-size: 0.95rem; color: var(--kkc-text);">
            pay<span style="color: #4297d7;">4</span><span class="fw-normal">coins</span> <small class="text-muted">ACP</small>
          </div>
          <span class="btn btn-sm btn-outline-kkc w-100 py-1" style="font-size: 0.75rem;">
            <i class="bi bi-box-arrow-up-right me-1"></i> Pay4Coins ACP
          </span>
        </a>
      </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer text-center">
      <div class="fw-semibold" style="color: var(--kkc-text);"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> ist ein Service der</div>
      <div style="font-size: 0.72rem; color: var(--kkc-text-muted);"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </aside>

  <!-- ========================================================================
       MAIN APP AREA
       ======================================================================== -->
  <div class="app-main">

    <!-- Topbar -->
    <header class="app-topbar">
      <!-- Globale Admin-Schnellsuche (Profile, Merchants, Filme, Webseiten) -->
      <div class="topbar-search position-relative d-none d-sm-block" id="global-search-wrapper">
        <i class="bi bi-search search-icon"></i>
        <input type="text"
               id="global-search-input"
               class="form-control pe-4"
               placeholder="Admin-Schnellsuche (Profil, Merchant, Film #ID, Domain)..."
               autocomplete="off"
               aria-label="Admin-Schnellsuche">
        <button type="button"
                id="global-search-clear"
                class="btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y me-1 p-0 px-1 d-none"
                title="Suche zurücksetzen">
          <i class="bi bi-x-circle-fill"></i>
        </button>
        <div id="global-search-dropdown" class="ec-global-search-dropdown d-none" role="listbox" aria-label="Suchergebnisse"></div>
      </div>

      <!-- Quick-Actions, Auto-Logout-Countdown & Admin-Menü -->
      <div class="d-flex align-items-center gap-2 gap-md-3 ms-auto">
        <?php if (acp_has_access('content_check') && $countContentChecking > 0): ?>
        <a href="#/movies-checking" class="btn btn-sm btn-primary-kkc d-flex align-items-center gap-1" title="Direkt zu den offenen Content-Prüfungen">
          <i class="bi bi-shield-check"></i>
          <span class="d-none d-lg-inline">Prüfung offen</span>
          <span class="badge bg-white text-primary rounded-pill ms-1"><?= (int)$countContentChecking ?></span>
        </a>
        <?php endif; ?>

        <!-- Live Auto-Logout Countdown (30 Minuten analog acp/index.php) -->
        <div class="d-none d-md-flex align-items-center gap-1 px-2 py-1 rounded border small text-muted" style="background-color: var(--kkc-surface-hover); border-color: var(--kkc-border) !important;" title="Automatischer Logout nach 30 Minuten Inaktivität">
          <i class="bi bi-clock-history ec-text-primary"></i>
          <span>Auto-Logout:</span>
          <strong id="acp-logout-timer" class="font-monospace text-body">30:00</strong>
        </div>

        <!-- Theme Toggle Button (Hell / Dunkel) -->
        <button id="theme-toggle" class="btn btn-outline-kkc" title="Design wechseln">
          <i id="theme-icon" class="bi bi-moon-stars-fill text-secondary"></i>
        </button>

        <!-- Admin Dropdown -->
        <div class="dropdown">
          <button class="btn btn-sm btn-link text-decoration-none dropdown-toggle d-flex align-items-center gap-2 p-0 text-body" data-bs-toggle="dropdown">
            <div class="rounded-circle ec-bg-primary-subtle ec-text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.82rem;">
              <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text-start d-none d-md-block">
              <div class="fw-semibold lh-1" style="font-size: 0.825rem; color: var(--kkc-text);">
                <?= htmlspecialchars($employeeUsername, ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size: 0.7rem; color: var(--kkc-text-muted);">Eingeloggt als Admin</div>
            </div>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg border-secondary border-opacity-25">
            <li>
              <h6 class="dropdown-header">
                Eingeloggt als: <?= htmlspecialchars($employeeUsername, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($employeeRule !== ''): ?>
                <div class="small text-muted fw-normal mt-1">Rolle: <?= htmlspecialchars($employeeRule, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
              </h6>
            </li>
            <li>
              <a class="dropdown-item" href="#/startseite">
                <i class="bi bi-speedometer2 me-2 text-muted"></i>Admin-Dashboard
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= htmlspecialchars($acpUrl, ENT_QUOTES, 'UTF-8') ?>/Startseite" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-2 text-muted"></i>Altes ACP öffnen (Vergleich)
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= htmlspecialchars($p4cAcpUrl, ENT_QUOTES, 'UTF-8') ?>" target="pay4coins_acp" rel="noopener">
                <i class="bi bi-bank me-2 text-muted"></i>Pay4Coins ACP
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-danger" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php?logout">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <!-- App Content Body (SPA Container für dynamische Fragmente ohne Reload) -->
    <main id="app-content" class="app-content">
      <div class="d-flex align-items-center justify-content-center p-5">
        <div class="spinner-border ec-text-primary me-2" role="status"></div>
        <span class="text-muted">Inhalte werden geladen...</span>
      </div>
    </main>
  </div>
</div>

<!-- Lokale JavaScripts (100% DSGVO / No-CDN) -->
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/app.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>

</body>
</html>
<?php
p4c_close(DB_HOST);
p4c_errorlog(error_get_last());
?>
