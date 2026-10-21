<?php
declare(strict_types=1);

namespace App\Modules\Home;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Merchant;

/**
 * EroCloud MCP (v2.0) - HomeController
 * Lädt alle echten Partner-, Profil-, Content- und Umsatzdaten für die Startseite.
 */
class HomeController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Rendert das eingeloggte Partner-Dashboard (#/startseite) mit echten DB-Daten
     */
    public function view(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            return '<div class="alert alert-warning">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut über Pay4Coins an.</div>';
        }

        $merchant = new Merchant($mysql, $merchantId);

        // 1. Standardgruppe für Profile prüfen/anlegen (analog mcp/includes/home.php)
        $this->ensureDefaultGroup($merchantId);

        // 2. Stammdaten & Richtlinien-Status des Partners
        $firstname          = trim((string)$merchant->firstname('aes_decrypt'));
        $surname            = trim((string)$merchant->surname('aes_decrypt'));
        $acceptContentRules = (string)$merchant->accept_content_rules();
        $needsRulesAccept   = ($acceptContentRules === '' || $acceptContentRules === '0000-00-00 00:00:00');
        $minActorProfiles   = abs((int)$merchant->minimum_number_actor_profiles());

        // 3. Darsteller-Profile des Partners laden
        $actors = [];
        $activeActorsCount = 0;
        $hasInactiveOrBlockedActor = false;
        $actorMap = [];

        $rsActors = p4c_query("SELECT `id`, `username`, `status`, `profile_image_fsk16`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `username` ASC;", __FILE__, __LINE__);

        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($row = p4c_fetch_object($rsActors)) {
                $actorId = abs((int)$row->id);
                $status  = (string)$row->status;

                if ($status === 'active') {
                    $activeActorsCount++;
                }
                if ($status === 'locked' || $status === 'blocked' || $status === 'inactive') {
                    $hasInactiveOrBlockedActor = true;
                }

                $actorMap[$actorId] = (string)$row->username;
                $actors[$actorId] = [
                    'id'           => $actorId,
                    'username'     => (string)$row->username,
                    'status'       => $status,
                    'avatar_fsk16' => (string)$row->profile_image_fsk16,
                    'movies_count' => 0,
                    'albums_count' => 0,
                ];
            }
        }

        // 4. Filme des Partners laden & KPIs berechnen
        $moviesTotal    = 0;
        $moviesOnline   = 0;
        $moviesPending  = 0;
        $recentContents = [];

        $rsMovies = p4c_query("SELECT `id`, `convert_status`, `file_id`, `filename`, `title`, `movie_checked`, `released`, `actor_id`, `online_at`
            FROM `movies`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `id` DESC;", __FILE__, __LINE__);

        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($m = p4c_fetch_object($rsMovies)) {
                $actorId = abs((int)$m->actor_id);
                // Falls das zugehörige Profil gelöscht wurde, überspringen (analog ajax/movies.php)
                if ($actorId > 0 && !isset($actorMap[$actorId])) {
                    continue;
                }

                $moviesTotal++;
                if (isset($actors[$actorId])) {
                    $actors[$actorId]['movies_count']++;
                }

                $checked      = (string)$m->movie_checked;
                $released     = abs((int)$m->released);
                $convStatus   = (int)$m->convert_status;
                $filename     = trim((string)$m->filename);

                $statusLabel = 'In Bearbeitung';
                $statusBadge = 'badge-kkc-neutral';
                $statusIcon  = 'bi-clock';

                if ($filename === '') {
                    $statusLabel = 'Datei fehlt';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-cloud-arrow-up';
                    $moviesPending++;
                } elseif ($convStatus <= 1) {
                    $statusLabel = 'Wird konvertiert';
                    $statusBadge = 'badge-kkc-info';
                    $statusIcon  = 'bi-gear-wide-connected';
                    $moviesPending++;
                } elseif ($checked !== '0000-00-00 00:00:00' && $checked !== '') {
                    $statusLabel = 'Online';
                    $statusBadge = 'badge-kkc-success';
                    $statusIcon  = 'bi-check-circle-fill';
                    $moviesOnline++;
                } elseif ($checked === '0000-00-00 00:00:00' && $released === 1) {
                    $statusLabel = 'Wird geprüft';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-hourglass-split';
                    $moviesPending++;
                } elseif ($checked === '0000-00-00 00:00:00' && $released === 2) {
                    $statusLabel = 'Abgelehnt';
                    $statusBadge = 'badge-kkc-danger';
                    $statusIcon  = 'bi-x-circle-fill';
                } elseif ($convStatus === 2) {
                    $statusLabel = 'Bereit zur Freigabe';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-exclamation-circle';
                    $moviesPending++;
                }

                if (count($recentContents) < 6) {
                    $recentContents[] = [
                        'id'           => abs((int)$m->id),
                        'title'        => trim((string)$m->title) !== '' ? (string)$m->title : 'Unbenannter Film #' . abs((int)$m->id),
                        'actor_id'     => $actorId,
                        'actor_name'   => $actorMap[$actorId] ?? '-',
                        'type'         => 'movie',
                        'status_label' => $statusLabel,
                        'status_badge' => $statusBadge,
                        'status_icon'  => $statusIcon,
                        'route'        => '#/movie?id=' . abs((int)$m->id),
                    ];
                }
            }
        }

        // 5. Fotoalben des Partners laden & KPIs berechnen
        $albumsTotal  = 0;
        $albumsOnline = 0;
        $photosTotal  = 0;

        $rsAlbums = p4c_query("SELECT `id`, `album_id`, `number_of_photos`, `title`, `album_checked`, `released`, `actor_id`
            FROM `photo_albums`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `id` DESC;", __FILE__, __LINE__);

        if ($rsAlbums && p4c_num_rows($rsAlbums) > 0) {
            while ($a = p4c_fetch_object($rsAlbums)) {
                $actorId = abs((int)$a->actor_id);
                if ($actorId > 0 && !isset($actorMap[$actorId])) {
                    continue;
                }

                $albumsTotal++;
                $numPhotos = abs((int)$a->number_of_photos);
                $photosTotal += $numPhotos;

                if (isset($actors[$actorId])) {
                    $actors[$actorId]['albums_count']++;
                }

                $checked = (string)$a->album_checked;
                if ($checked !== '0000-00-00 00:00:00' && $checked !== '') {
                    $albumsOnline++;
                }
            }
        }

        // 6. Monatsumsatz berechnen (Videos + Fotoalben im aktuellen Monat analog home_stats.inc.php)
        $currentMonthPrefix = date('Y-m-');

        $rsVideoRev = p4c_query("SELECT SUM(`movies_access`.`actor_commision`) AS `comm`
            FROM `movies_access`
            LEFT JOIN `movies` ON `movies_access`.`movie_id` = `movies`.`file_id`
            WHERE `movies`.`merchant_id` = '" . $merchantId . "'
              AND `movies_access`.`buy_timestamp` LIKE '" . p4c_escape_string($currentMonthPrefix) . "%';", __FILE__, __LINE__);
        $videoCents = 0.0;
        if ($rsVideoRev && p4c_num_rows($rsVideoRev) > 0) {
            $revObj = p4c_fetch_object($rsVideoRev);
            $videoCents = (float)($revObj->comm ?? 0);
        }

        $rsAlbumRev = p4c_query("SELECT SUM(`photo_albums_access`.`actor_commision`) AS `comm`
            FROM `photo_albums_access`
            LEFT JOIN `photo_albums` ON `photo_albums_access`.`album_id` = `photo_albums`.`album_id`
            WHERE `photo_albums`.`merchant_id` = '" . $merchantId . "'
              AND `photo_albums_access`.`buy_timestamp` LIKE '" . p4c_escape_string($currentMonthPrefix) . "%';", __FILE__, __LINE__);
        $albumCents = 0.0;
        if ($rsAlbumRev && p4c_num_rows($rsAlbumRev) > 0) {
            $revAlbumObj = p4c_fetch_object($rsAlbumRev);
            $albumCents = (float)($revAlbumObj->comm ?? 0);
        }

        $videoEur = $videoCents / 100.0;
        $albumEur = $albumCents / 100.0;
        $totalEur = $videoEur + $albumEur;

        return View::render('Home', 'dashboard', [
            'firstname'                 => $firstname,
            'surname'                   => $surname,
            'needsRulesAccept'          => $needsRulesAccept,
            'acceptContentRulesDate'    => $acceptContentRules,
            'hasInactiveOrBlockedActor' => $hasInactiveOrBlockedActor,
            'canCreateActor'            => count($actors) < $minActorProfiles,
            'actors'                    => array_values($actors),
            'actorsTotal'               => count($actors),
            'activeActorsCount'         => $activeActorsCount,
            'moviesTotal'               => $moviesTotal,
            'moviesOnline'              => $moviesOnline,
            'moviesPending'             => $moviesPending,
            'albumsTotal'               => $albumsTotal,
            'albumsOnline'              => $albumsOnline,
            'photosTotal'               => $photosTotal,
            'videoEur'                  => $videoEur,
            'albumEur'                  => $albumEur,
            'totalEur'                  => $totalEur,
            'recentContents'            => $recentContents,
        ]);
    }

    /**
     * Speichert die Zustimmung zu den Content-Richtlinien per AJAX-POST
     */
    public function acceptRules(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            Response::error('Nicht angemeldet.', 401);
        }

        $confirmed = (string)$this->request->post('upload_content', '');
        if ($confirmed !== '1' && $confirmed !== 'true' && $confirmed !== 'on') {
            Response::error('Bitte bestätigen Sie die Nutzungsrichtlinien über das Kontrollkästchen.', 400);
        }

        p4c_query("UPDATE `merchants` SET `accept_content_rules` = '" . date('Y-m-d H:i:s') . "'
            WHERE `id` = '" . $merchantId . "' AND `accept_content_rules` = '0000-00-00 00:00:00' LIMIT 1;", __FILE__, __LINE__);

        log_action('Nutzungsrichtlinien für Content akzeptiert.');

        Response::json([
            'status'  => 'success',
            'message' => 'Vielen Dank! Ihre Zustimmung zu den Nutzungsrichtlinien wurde gespeichert.',
        ]);
    }

    /**
     * Stellt sicher, dass für den Partner eine Standardgruppe existiert (analog mcp/includes/home.php)
     */
    private function ensureDefaultGroup(int $merchantId): void
    {
        $rsGroups = p4c_query("SELECT `id` FROM `groups` WHERE `merchant_id` = '" . $merchantId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsGroups && p4c_num_rows($rsGroups) === 0) {
            p4c_query("INSERT INTO `groups` SET
                `merchant_id`    = '" . $merchantId . "',
                `group_id`       = '" . p4c_escape_string('G-' . strtoupper(randomString(8))) . "',
                `group_name`     = 'Standartgruppe',
                `group_password` = '" . p4c_escape_string(randomString(12)) . "';", __FILE__, __LINE__);

            $groupId = abs((int)p4c_insert_id());
            if ($groupId > 0) {
                $rsActors = p4c_query("SELECT `id` FROM `actors` WHERE `merchant_id` = '" . $merchantId . "' ORDER BY `username` ASC;", __FILE__, __LINE__);
                if ($rsActors && p4c_num_rows($rsActors) > 0) {
                    while ($actorObj = p4c_fetch_object($rsActors)) {
                        $actorId = abs((int)$actorObj->id);
                        $rsCheck = p4c_query("SELECT `id` FROM `group_actors` WHERE `actor_id` = '" . $actorId . "' LIMIT 1;", __FILE__, __LINE__);
                        if ($rsCheck && p4c_num_rows($rsCheck) === 0) {
                            p4c_query("INSERT INTO `group_actors` SET
                                `merchant_id` = '" . $merchantId . "',
                                `group_id`    = '" . $groupId . "',
                                `actor_id`    = '" . $actorId . "';", __FILE__, __LINE__);
                        }
                    }
                }
            }
        }
    }
}
