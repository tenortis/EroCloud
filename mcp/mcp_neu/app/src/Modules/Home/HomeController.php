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
 * Lädt alle echten Partner-, Profil- und Content-Daten für die Startseite (ohne Umsatzzahlen).
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
            return '<div class="alert alert-warning">Deine Sitzung ist abgelaufen. Bitte melde dich erneut über Pay4Coins an.</div>';
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
        $genderLabels = [
            'f' => 'Weiblich',
            'm' => 'Männlich',
            't' => 'Transgender',
        ];

        $rsActors = p4c_query("SELECT `id`, `username`, `status`, `locked_reason`, `profile_image_fsk16`, `profile_image_fsk18`,
                `gender`, `age`, `star_sign`, `body_height`, `body_weight`, `hair_color`, `eye_color`, `cup_size`, `sexual_orientation`, `marital_status`
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

                $genderCode = (string)($row->gender ?? 'f');
                $actorMap[$actorId] = (string)$row->username;
                $actors[$actorId] = [
                    'id'                 => $actorId,
                    'username'           => (string)$row->username,
                    'status'             => $status,
                    'locked_reason'      => self::decodeLegacyText((string)($row->locked_reason ?? '')),
                    'avatar_fsk16'       => trim((string)($row->profile_image_fsk16 ?? '')),
                    'avatar_fsk18'       => trim((string)($row->profile_image_fsk18 ?? '')),
                    'gender_label'       => $genderLabels[$genderCode] ?? 'Weiblich',
                    'age'                => abs((int)($row->age ?? 0)),
                    'star_sign'          => self::decodeLegacyText((string)($row->star_sign ?? '')),
                    'body_height'        => abs((int)($row->body_height ?? 0)),
                    'body_weight'        => abs((int)($row->body_weight ?? 0)),
                    'hair_color'         => self::decodeLegacyText((string)($row->hair_color ?? '')),
                    'eye_color'          => self::decodeLegacyText((string)($row->eye_color ?? '')),
                    'cup_size'           => self::decodeLegacyText((string)($row->cup_size ?? '')),
                    'sexual_orientation' => self::decodeLegacyText((string)($row->sexual_orientation ?? '')),
                    'marital_status'     => self::decodeLegacyText((string)($row->marital_status ?? '')),
                    'movies_count'       => 0,
                    'albums_count'       => 0,
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
        $albumsTotal   = 0;
        $albumsOnline  = 0;
        $albumsPending = 0;
        $photosTotal   = 0;

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

                $checked  = (string)$a->album_checked;
                $released = abs((int)$a->released);
                if ($checked !== '0000-00-00 00:00:00' && $checked !== '') {
                    $albumsOnline++;
                } elseif ($released !== 2) {
                    $albumsPending++;
                }
            }
        }

        $contentPending = $moviesPending + $albumsPending;

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
            'albumsPending'             => $albumsPending,
            'photosTotal'               => $photosTotal,
            'contentPending'            => $contentPending,
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
            Response::error('Bitte bestätige die Nutzungsrichtlinien über das Kontrollkästchen.', 400);
        }

        p4c_query("UPDATE `merchants` SET `accept_content_rules` = '" . date('Y-m-d H:i:s') . "'
            WHERE `id` = '" . $merchantId . "' AND `accept_content_rules` = '0000-00-00 00:00:00' LIMIT 1;", __FILE__, __LINE__);

        log_action('Nutzungsrichtlinien für Content akzeptiert.');

        Response::json([
            'status'  => 'success',
            'message' => 'Vielen Dank! Deine Zustimmung zu den Nutzungsrichtlinien wurde gespeichert.',
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

    /**
     * Dekodiert eventuell vorhandene Legacy-HTML-Entities (z.B. &auml; -> ä) sauber nach UTF-8.
     */
    private static function decodeLegacyText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return str_replace(["\r\n", "\r"], "\n", $decoded);
    }
}
