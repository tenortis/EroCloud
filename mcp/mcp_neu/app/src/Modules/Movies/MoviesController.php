<?php
declare(strict_types=1);

namespace App\Modules\Movies;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Filme in der Cloud & Film-Upload Controller (Movies)
 * Verwaltet die Film-Übersicht, Detailbearbeitung, Vorschaubilder sowie den 3-stufigen Film-Upload.
 */
class MoviesController
{
    private Request $request;

    private const REPLACE_TITLE_CHARS = [
        '°', '^', '²', '§', '$', '%', '{', '[', ']', '}', '´', '`', '~', "'", '_', ';', '<', '>',
    ];

    private const ZERO_WIDTH_SPACES = [
        "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBF\xBD",
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * GET: Alle Filme des Partners in der Cloud anzeigen (#/movies)
     */
    public function list(): string
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }
        @session_write_close();
        \App\Modules\Home\HomeController::ensureSearchIndexes();

        $apiUrl = defined('API_URL') ? API_URL : 'https://api.erocloud.net';

        // 1. Nicht gelöschte Darsteller-Profile des Partners laden
        $actorsMap = [];
        $rsActors = p4c_query("SELECT `id`, `username`, `status`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($aObj = p4c_fetch_object($rsActors)) {
                $actorsMap[abs((int)$aObj->id)] = (string)$aObj->username;
            }
        }

        // 2. Online-Status aller Filme des Partners vorladen
        $onlineMovieFileIds = [];
        $rsOnlineMovies = p4c_query("SELECT `file_id` FROM `movies_online` WHERE `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        if ($rsOnlineMovies && p4c_num_rows($rsOnlineMovies) > 0) {
            while ($omObj = p4c_fetch_object($rsOnlineMovies)) {
                $onlineMovieFileIds[(string)$omObj->file_id] = true;
            }
        }

        // 3. Alle nicht gelöschten Filme des Partners laden (ohne Umsatz-/Verkaufszahlen)
        $movies        = [];
        $countOnline   = 0;
        $countPending  = 0;
        $countRejected = 0;

        $rsMovies = p4c_query("SELECT
                `id`, `status`, `convert_status`, `file_id`, `checksum`, `filename`, `title`,
                `movie_checked`, `released`, `actor_id`, `online_at`, `playtime_string`, `resolution`,
                `amount_second`, `as_download`, `amount_download`, `preview`,
                `preview_image_fsk16`, `preview_image_fsk18`
            FROM `movies`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `id` DESC;", __FILE__, __LINE__);

        $nowTs = time();

        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($m = p4c_fetch_object($rsMovies)) {
                $actorId = abs((int)$m->actor_id);
                if ($actorId > 0 && !isset($actorsMap[$actorId])) {
                    continue;
                }

                $movieId    = abs((int)$m->id);
                $mStatus    = (string)($m->status ?? 'active');
                $fileId     = (string)$m->file_id;
                $isOnline   = isset($onlineMovieFileIds[$fileId]);
                $checksum   = (string)$m->checksum;
                $filename   = trim((string)$m->filename);
                $convStatus = (int)$m->convert_status;
                $checked    = (string)$m->movie_checked;
                $released   = (int)$m->released;

                $statusKey   = 'pending';
                $statusLabel = 'In Bearbeitung';
                $statusBadge = 'badge-kkc-neutral';
                $statusIcon  = 'bi-clock';
                $editRoute   = '#/movie?id=' . $movieId;

                if ($filename === '') {
                    $statusKey   = 'pending';
                    $statusLabel = 'Filmdatei hochladen';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-cloud-arrow-up-fill';
                    $editRoute   = '#/movie-upload?step=2&movie_id=' . $movieId;
                    $countPending++;
                } elseif ($convStatus === 3) {
                    $statusKey   = 'rejected';
                    $statusLabel = 'Konvertierungsfehler';
                    $statusBadge = 'badge-kkc-danger';
                    $statusIcon  = 'bi-exclamation-octagon-fill';
                    $countRejected++;
                } elseif ($convStatus <= 1) {
                    $statusKey   = 'pending';
                    $statusLabel = 'Wird konvertiert';
                    $statusBadge = 'badge-kkc-info';
                    $statusIcon  = 'bi-gear-wide-connected';
                    $countPending++;
                } elseif ($released === 2) {
                    $statusKey   = 'rejected';
                    $statusLabel = 'Abgelehnt';
                    $statusBadge = 'badge-kkc-danger';
                    $statusIcon  = 'bi-x-circle-fill';
                    $countRejected++;
                } elseif ($mStatus === 'blocked') {
                    $statusKey   = 'rejected';
                    $statusLabel = 'Gesperrt';
                    $statusBadge = 'badge-kkc-danger';
                    $statusIcon  = 'bi-slash-circle-fill';
                    $countRejected++;
                } elseif ($checked !== '0000-00-00 00:00:00' && $checked !== '' && $released === 1) {
                    if ($isOnline) {
                        $statusKey   = 'online';
                        $statusLabel = 'Online';
                        $statusBadge = 'badge-kkc-success';
                        $statusIcon  = 'bi-check-circle-fill';
                        $countOnline++;
                    } elseif ($mStatus === 'inactive') {
                        $statusKey   = 'pending';
                        $statusLabel = 'Pausiert';
                        $statusBadge = 'badge-kkc-neutral';
                        $statusIcon  = 'bi-pause-circle-fill';
                        $countPending++;
                    } else {
                        $statusKey   = 'pending';
                        $statusLabel = 'Geplant';
                        $statusBadge = 'badge-kkc-info';
                        $statusIcon  = 'bi-calendar-event';
                        $countPending++;
                    }
                } elseif ($checked !== '0000-00-00 00:00:00' && $checked !== '' && $released === 0) {
                    $statusKey   = 'rejected';
                    $statusLabel = 'Gesperrt / Offline';
                    $statusBadge = 'badge-kkc-danger';
                    $statusIcon  = 'bi-slash-circle';
                    $countRejected++;
                } elseif ($checked === '0000-00-00 00:00:00' && $released === 1) {
                    $statusKey   = 'pending';
                    $statusLabel = 'Wird geprüft';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-hourglass-split';
                    $countPending++;
                } elseif ($convStatus === 2) {
                    $statusKey   = 'pending';
                    $statusLabel = 'Jetzt veröffentlichen';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-exclamation-circle-fill';
                    $countPending++;
                }

                $onlineAtTs = ($m->online_at !== '0000-00-00 00:00:00' && !empty($m->online_at))
                    ? (int)strtotime((string)$m->online_at)
                    : 0;
                $onlineAtFormatted = $onlineAtTs > 0
                    ? date('d.m.Y H:i', $onlineAtTs)
                    : '-';

                $isPreview      = abs((int)($m->preview ?? 0)) === 1;
                $asDownload     = abs((int)($m->as_download ?? 0)) === 1;
                $amountSecond   = (float)($m->amount_second ?? 0.8);
                $amountDownload = abs((int)($m->amount_download ?? 0));

                if ($isPreview || $amountSecond <= 0.0) {
                    $priceSummary = 'Kostenloser Trailer';
                } else {
                    $priceSummary = number_format($amountSecond, 1, ',', '.') . ' Coins/Sek.';
                    if ($asDownload) {
                        $priceSummary .= ' · DL: +' . $amountDownload . ' %';
                    }
                }

                $cs16 = $checksum . '_' . abs((int)($m->preview_image_fsk16 ?? 1));
                $cs18 = $checksum . '_' . abs((int)($m->preview_image_fsk18 ?? 1));

                $movies[] = [
                    'id'              => $movieId,
                    'file_id'         => $fileId,
                    'title'           => self::decodeLegacyText(trim((string)$m->title) !== '' ? (string)$m->title : 'Unbenannter Film #' . $movieId),
                    'actor_id'        => $actorId,
                    'actor_name'      => $actorsMap[$actorId] ?? '-',
                    'status_key'      => $statusKey,
                    'status_label'    => $statusLabel,
                    'status_badge'    => $statusBadge,
                    'status_icon'     => $statusIcon,
                    'edit_route'      => $editRoute,
                    'poster_fsk16'    => $apiUrl . '/PlayerPoster/FSK16/' . rawurlencode($fileId) . '?w=180&cs=' . rawurlencode($cs16),
                    'poster_fsk18'    => $apiUrl . '/PlayerPoster/FSK18/' . rawurlencode($fileId) . '?w=180&cs=' . rawurlencode($cs18),
                    'zoom_fsk16'      => $apiUrl . '/PlayerPoster/FSK16/' . rawurlencode($fileId) . '?w=640&cs=' . rawurlencode($cs16),
                    'zoom_fsk18'      => $apiUrl . '/PlayerPoster/FSK18/' . rawurlencode($fileId) . '?w=640&cs=' . rawurlencode($cs18),
                    'online_at'       => $onlineAtFormatted,
                    'online_at_ts'    => $onlineAtTs,
                    'is_scheduled'    => ($statusKey === 'online' && $onlineAtTs > $nowTs),
                    'playtime_string' => (string)($m->playtime_string ?? ''),
                    'resolution'      => (string)($m->resolution ?? ''),
                    'price_summary'   => $priceSummary,
                ];
            }
        }

        return View::render('Movies', 'list', [
            'movies'        => $movies,
            'actorsMap'     => $actorsMap,
            'countTotal'    => count($movies),
            'countOnline'   => $countOnline,
            'countPending'  => $countPending,
            'countRejected' => $countRejected,
        ]);
    }

    /**
     * GET: Einzelnen Film bearbeiten (#/movie?id=...)
     */
    public function detail(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }
        @session_write_close();

        $movieId = abs((int)$this->request->get('id', 0));
        if ($movieId === 0) {
            return $this->list();
        }

        $rsMovie = p4c_query("SELECT *
            FROM `movies`
            WHERE `id` = '" . $movieId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            return '
            <div class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 m-4">
              <div>
                <i class="bi bi-exclamation-octagon-fill me-2"></i>
                Der angeforderte Film wurde nicht gefunden oder wurde bereits gelöscht.
              </div>
              <a href="#/movies" class="btn btn-sm btn-outline-kkc">Zur Film-Übersicht</a>
            </div>';
        }

        $mObj = p4c_fetch_object($rsMovie);

        // Falls noch keine Videodatei hochgeladen wurde, direkt zu Schritt 2 des Film-Uploads leiten
        if (trim((string)$mObj->filename) === '') {
            $_GET['step']     = 2;
            $_GET['movie_id'] = $movieId;
            return $this->upload();
        }

        $mcpUrl    = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
        $fileId    = (string)$mObj->file_id;
        $actorId   = abs((int)$mObj->actor_id);
        $title     = self::decodeLegacyText((string)$mObj->title);
        $desc      = self::decodeLegacyText((string)$mObj->description);
        $onlineAt  = ($mObj->online_at !== '0000-00-00 00:00:00' && !empty($mObj->online_at))
            ? date('Y-m-d\TH:i', strtotime((string)$mObj->online_at))
            : date('Y-m-d\TH:i');

        // Prüfen, ob Film bereits in movies_online eingetragen ist
        $rsOnline      = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id` = '" . p4c_escape_string($fileId) . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
        $isMovieOnline = ($rsOnline && p4c_num_rows($rsOnline) > 0);

        // Ablehnungsgrund laden, falls released == 2 (oder Film manuell gesperrt wurde)
        $rejectionInfo = null;
        if ((int)$mObj->released === 2 || (string)($mObj->status ?? 'active') === 'blocked') {
            $rsRejHist = p4c_query("SELECT * FROM `rejection_reason_movie_history` WHERE `movie_file_id` = '" . p4c_escape_string($fileId) . "' ORDER BY `datetime` DESC LIMIT 1;", __FILE__, __LINE__);
            if ($rsRejHist && p4c_num_rows($rsRejHist) > 0) {
                $rejHistObj = p4c_fetch_object($rsRejHist);
                $shortTitle = '';
                if (!empty($rejHistObj->id_name) && (string)$rejHistObj->id_name !== 'free_text') {
                    $rsRejShort = p4c_query("SELECT `de_short` FROM `rejection_reason_movie` WHERE `id_name` = '" . p4c_escape_string((string)$rejHistObj->id_name) . "' LIMIT 1;", __FILE__, __LINE__);
                    if ($rsRejShort && p4c_num_rows($rsRejShort) > 0) {
                        $shortObj   = p4c_fetch_object($rsRejShort);
                        $shortTitle = self::decodeLegacyText((string)$shortObj->de_short);
                    }
                }
                $rejectionInfo = [
                    'reason' => $shortTitle,
                    'text'   => self::decodeLegacyText((string)($rejHistObj->text ?? '')),
                ];
            } elseif ((int)$mObj->released === 2) {
                $rejectionInfo = [
                    'reason' => 'Prüfungshinweis',
                    'text'   => 'Bitte überprüfe Titel, Beschreibung, Kategorien sowie deine FSK16- und FSK18-Vorschaubilder und reiche den Film erneut ein.',
                ];
            }
        }

        // Verfügbare automatisch generierte Vorschaubilder (1..10) sowie eigene Cover (#11 / #12) ermitteln
        $availableThumbs = [];
        $moviesPath      = defined('MOVIES_PATH') ? MOVIES_PATH : (dirname(__DIR__, 5) . '/priv');
        $storageLoc      = (string)$mObj->storage_location;
        $baseFilename    = substr((string)$mObj->filename, 0, -4);
        $movieDir        = rtrim($moviesPath, '/\\') . '/' . $storageLoc . '/' . $merchantId . '/' . $movieId;

        $hasThumbOnDisk = static function (int $num) use ($movieDir, $movieId, $fileId, $baseFilename): bool {
            $prefixes = [
                $movieDir . '/thumb_' . $movieId . '_' . $fileId . '_' . $num,
                $movieDir . '/thumb_' . $movieId . '_' . strtolower($fileId) . '_' . $num,
                $movieDir . '/thumb_' . $baseFilename . '_' . $num,
            ];
            foreach ($prefixes as $prefix) {
                foreach (['.jpg', '.jpeg', '.png', '.gif'] as $ext) {
                    if (is_file($prefix . $ext)) {
                        return true;
                    }
                }
            }
            return false;
        };

        for ($i = 1; $i <= 10; $i++) {
            if ($hasThumbOnDisk($i)) {
                $availableThumbs[] = [
                    'num'        => $i,
                    'url'        => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $i . '&w=186',
                    'zoom_url'   => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $i . '&w=640',
                    'editor_url' => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=' . $i . '&w=1024',
                ];
            }
        }

        $hasCustomFsk16 = $hasThumbOnDisk(11) || abs((int)$mObj->preview_image_fsk16) === 11;
        $hasCustomFsk18 = $hasThumbOnDisk(12) || abs((int)$mObj->preview_image_fsk18) === 12;

        // Kategorien inkl. System-Auto-Erkennung laden
        $categoryGroups = $this->loadCategoriesWithAutoDetection(
            $actorId,
            $title,
            $desc,
            (string)$mObj->category_slave
        );

        // Aktive Darsteller & Partner-Websites laden
        $merchant = new \Merchant($mysql, $merchantId);
        $actors   = $this->loadActiveMerchantActors($merchantId, $actorId);
        $websites = $this->loadMerchantWebsites($merchant, (string)$mObj->visible_for_website);

        $nowCacheBust = time();
        $movieData = [
            'id'                      => $movieId,
            'file_id'                 => $fileId,
            'title'                   => $title,
            'description'             => $desc,
            'online_at'               => $onlineAt,
            'online_at_display'       => ($mObj->online_at !== '0000-00-00 00:00:00' && !empty($mObj->online_at))
                ? date('d.m.Y H:i', strtotime((string)$mObj->online_at))
                : '-',
            'can_edit_online_at'      => (((string)$mObj->movie_checked === '0000-00-00 00:00:00' && !$isMovieOnline) || strtotime((string)$mObj->online_at) > time()),
            'playtime_seconds'        => round((float)$mObj->playtime_seconds),
            'playtime_string'         => (string)($mObj->playtime_string ?? '0:00'),
            'resolution'              => (string)($mObj->resolution ?? ''),
            'amount_second'           => number_format((float)$mObj->amount_second, 1, '.', ''),
            'streaming_coins'         => (int)round((float)$mObj->playtime_seconds * (float)$mObj->amount_second),
            'as_download'             => abs((int)$mObj->as_download) === 1 ? 1 : 0,
            'amount_download'         => abs((int)$mObj->amount_download),
            'preview'                 => abs((int)$mObj->preview) === 1 ? 1 : 0,
            'fsk16'                   => abs((int)$mObj->preview_image_fsk16),
            'fsk18'                   => abs((int)$mObj->preview_image_fsk18),
            'has_custom_fsk16'        => $hasCustomFsk16,
            'has_custom_fsk18'        => $hasCustomFsk18,
            'custom_fsk16_url'        => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=11&w=186&t=' . $nowCacheBust,
            'custom_fsk16_zoom_url'   => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=11&w=640&t=' . $nowCacheBust,
            'custom_fsk16_editor_url' => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=11&w=1024&t=' . $nowCacheBust,
            'custom_fsk18_url'        => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=12&w=186&t=' . $nowCacheBust,
            'custom_fsk18_zoom_url'   => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=12&w=640&t=' . $nowCacheBust,
            'custom_fsk18_editor_url' => $mcpUrl . '/thumb.php?movie_id=' . rawurlencode($fileId) . '&thumb_number=12&w=1024&t=' . $nowCacheBust,
            'player_poster_url'   => $mcpUrl . '/PlayerPoster/' . rawurlencode($fileId) . '?w=1280&h=720',
            'player_video_url'    => $mcpUrl . '/Player/' . rawurlencode($fileId),
            'status'              => (string)($mObj->status ?? 'active'),
            'convert_status'      => (int)$mObj->convert_status,
            'movie_checked'       => (string)$mObj->movie_checked,
            'released'            => (int)$mObj->released,
            'is_online'           => $isMovieOnline,
            'can_delete'          => ((string)$mObj->movie_checked === '0000-00-00 00:00:00' && (int)$mObj->released !== 1 && !$isMovieOnline),
            'actor_id'            => $actorId,
            'visible_for_website' => (string)$mObj->visible_for_website,
            'category_master'     => (string)$mObj->category_master !== '' ? (string)$mObj->category_master : 'porn',
            'meta_title'          => self::decodeLegacyText((string)$mObj->meta_title),
            'meta_description'    => self::decodeLegacyText((string)$mObj->meta_description),
            'seo_url'             => (string)$mObj->seo_url,
        ];

        $conversionQueueInfo = null;
        if ((int)$mObj->convert_status < 2) {
            $conversionQueueInfo = $this->calculateConversionQueueInfo(
                $movieId,
                (int)$mObj->convert_status,
                (int)round((float)$mObj->playtime_seconds),
                (string)($mObj->convert_starttime ?? '0000-00-00 00:00:00')
            );
        }

        // Darsteller- & Drehpartner-Daten für die Pflichtprüfung laden
        \CoActorManager::ensureSchema();
        $merchantIdent     = \CoActorManager::getMerchantIdentity($merchantId);
        $ownerCoActor      = \CoActorManager::ensureOwnerCoActor($merchantId);
        $coActors          = \CoActorManager::getMerchantCoActors($merchantId, false);
        $contentPerformers = \CoActorManager::getContentPerformers('movie', $movieId, $merchantId);

        return View::render('Movies', 'detail', [
            'movie'               => $movieData,
            'availableThumbs'     => $availableThumbs,
            'categoryGroups'      => $categoryGroups,
            'actors'              => $actors,
            'websites'            => $websites,
            'rejectionInfo'       => $rejectionInfo,
            'conversionQueueInfo' => $conversionQueueInfo,
            'merchantIdent'       => $merchantIdent,
            'ownerCoActor'        => $ownerCoActor,
            'coActors'            => $coActors,
            'contentPerformers'   => $contentPerformers,
        ]);
    }

    /**
     * GET: Neuen Film in 3 Schritten hochladen (#/movie-upload)
     */
    public function upload(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }

        $step            = abs((int)$this->request->get('step', 1));
        $explicitMovieId = abs((int)$this->request->get('movie_id', 0));
        $movieId         = $explicitMovieId;
        if (!in_array($step, [1, 2, 3], true)) {
            $step = 1;
        }

        if ($step === 3) {
            unset($_SESSION['upload_movie']);
        } elseif ($movieId === 0 && isset($_SESSION['upload_movie']['movie_id'])) {
            $movieId = abs((int)$_SESSION['upload_movie']['movie_id']);
        }

        $merchant          = new \Merchant($mysql, $merchantId);
        $isPublicAllowed   = $this->isPublicUploadAllowed($merchant, $merchantId);
        $defaultWebsite    = $isPublicAllowed ? 'public' : '';
        $notifyDefault     = class_exists('EroCloudMailer') ? \EroCloudMailer::getMerchantNotifyPreference($merchantId) : 1;

        $draft = [
            'id'                  => 0,
            'title'               => '',
            'description'         => '',
            'online_at'           => date('Y-m-d\TH:i', strtotime('+90 minutes')),
            'amount_second'       => '0.8',
            'as_download'         => 1,
            'amount_download'     => 10,
            'preview'             => 0,
            'actor_id'            => 0,
            'visible_for_website' => $defaultWebsite,
            'category_master'     => 'porn',
            'category_slave'      => '',
            'meta_title'          => '',
            'meta_description'    => '',
            'seo_url'             => '',
            'filename'            => '',
            'playtime_string'     => '',
            'resolution'          => '',
            'convert_status'      => 0,
            'released'            => 0,
            'notify_on_convert'   => $notifyDefault,
        ];

        if ($movieId > 0) {
            $rsDraft = p4c_query("SELECT *
                FROM `movies`
                WHERE `id` = '" . $movieId . "'
                  AND `merchant_id` = '" . $merchantId . "'
                  AND `status` != 'deleted'
                LIMIT 1;", __FILE__, __LINE__);

            if ($rsDraft && p4c_num_rows($rsDraft) > 0) {
                $dObj          = p4c_fetch_object($rsDraft);
                $draftFilename = trim((string)($dObj->filename ?? ''));

                // Wenn Schritt 1 ohne explizite movie_id aufgerufen wird, aber der Session-Film bereits eine
                // hochgeladene Videodatei besitzt, starten wir sauber einen neuen leeren Film-Entwurf.
                if ($step === 1 && $explicitMovieId === 0 && $draftFilename !== '' && (int)($dObj->convert_status ?? 0) !== 3) {
                    unset($_SESSION['upload_movie']);
                    $movieId = 0;
                } else {
                    $draft = [
                        'id'                  => abs((int)$dObj->id),
                        'title'               => self::decodeLegacyText((string)$dObj->title),
                        'description'         => self::decodeLegacyText((string)$dObj->description),
                        'online_at'           => ($dObj->online_at !== '0000-00-00 00:00:00' && !empty($dObj->online_at))
                            ? date('Y-m-d\TH:i', strtotime((string)$dObj->online_at))
                            : date('Y-m-d\TH:i', strtotime('+90 minutes')),
                        'amount_second'       => number_format((float)$dObj->amount_second, 1, '.', ''),
                        'as_download'         => abs((int)$dObj->as_download) === 1 ? 1 : 0,
                        'amount_download'     => abs((int)$dObj->amount_download),
                        'preview'             => abs((int)$dObj->preview) === 1 ? 1 : 0,
                        'actor_id'            => abs((int)$dObj->actor_id),
                        'visible_for_website' => (string)$dObj->visible_for_website,
                        'category_master'     => (string)$dObj->category_master !== '' ? (string)$dObj->category_master : 'porn',
                        'category_slave'      => (string)$dObj->category_slave,
                        'meta_title'          => self::decodeLegacyText((string)$dObj->meta_title),
                        'meta_description'    => self::decodeLegacyText((string)$dObj->meta_description),
                        'seo_url'             => (string)$dObj->seo_url,
                        'filename'            => $draftFilename,
                        'playtime_seconds'    => (int)round((float)($dObj->playtime_seconds ?? 0)),
                        'playtime_string'     => (string)($dObj->playtime_string ?? ''),
                        'resolution'          => (string)($dObj->resolution ?? ''),
                        'convert_status'      => abs((int)($dObj->convert_status ?? 0)),
                        'convert_starttime'   => (string)($dObj->convert_starttime ?? '0000-00-00 00:00:00'),
                        'released'            => abs((int)($dObj->released ?? 0)),
                        'notify_on_convert'   => isset($dObj->notify_on_convert) ? (abs((int)$dObj->notify_on_convert) === 1 ? 1 : 0) : $notifyDefault,
                    ];
                }
            } else {
                unset($_SESSION['upload_movie']);
                if ($step === 2) {
                    $step    = 1;
                    $movieId = 0;
                }
            }
        }

        @session_write_close();

        $conversionQueueInfo = null;
        if ($step === 3 && (int)$draft['id'] > 0 && (int)$draft['convert_status'] < 2) {
            $conversionQueueInfo = $this->calculateConversionQueueInfo(
                (int)$draft['id'],
                (int)$draft['convert_status'],
                (int)($draft['playtime_seconds'] ?? 0),
                (string)($draft['convert_starttime'] ?? '0000-00-00 00:00:00')
            );
        }

        $actors   = $this->loadActiveMerchantActors($merchantId, (int)$draft['actor_id']);
        $websites = $this->loadMerchantWebsites($merchant, (string)$draft['visible_for_website']);

        // Falls nur 1 Darsteller-Profil existiert, dieses automatisch vorauswählen
        if ((int)$draft['actor_id'] === 0 && count($actors) === 1) {
            $draft['actor_id'] = (int)$actors[0]['id'];
        }

        // Falls noch keine Website gewählt wurde und der Partner eigene Domains hat, erste Domain vorauswählen
        if ($draft['visible_for_website'] === '' && !empty($websites)) {
            $draft['visible_for_website'] = (string)$websites[0]['value'];
        }

        $categoryGroups = $this->loadCategoriesWithAutoDetection(
            (int)$draft['actor_id'],
            (string)$draft['title'],
            (string)$draft['description'],
            (string)$draft['category_slave']
        );

        // Darsteller- & Drehpartner-Daten für Schritt 1 des Film-Uploads laden
        \CoActorManager::ensureSchema();
        $merchantIdent     = \CoActorManager::getMerchantIdentity($merchantId);
        $ownerCoActor      = \CoActorManager::ensureOwnerCoActor($merchantId);
        $coActors          = \CoActorManager::getMerchantCoActors($merchantId, false);
        $contentPerformers = (int)$draft['id'] > 0
            ? \CoActorManager::getContentPerformers('movie', (int)$draft['id'], $merchantId)
            : [
                'performer_mode'   => 'solo_owner',
                'owner_in_content' => 1,
                'co_actor_ids'     => [],
                'co_actors'        => [],
            ];

        return View::render('Movies', 'upload', [
            'step'                => $step,
            'movie'               => $draft,
            'actors'              => $actors,
            'websites'            => $websites,
            'categoryGroups'      => $categoryGroups,
            'conversionQueueInfo' => $conversionQueueInfo,
            'merchantIdent'       => $merchantIdent,
            'ownerCoActor'        => $ownerCoActor,
            'coActors'            => $coActors,
            'contentPerformers'   => $contentPerformers,
        ]);
    }

    /**
     * POST: Speichert Schritt 1 des Film-Uploads (movies/save-step1)
     */
    public function saveStep1(): void
    {
        global $mysql;

        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            Response::error('Deine Sitzung ist abgelaufen.', 401);
            return;
        }

        $movieId  = abs((int)$this->request->post('movie_id', $this->request->post('id', 0)));
        $merchant = new \Merchant($mysql, $merchantId);
        $errors   = [];

        $title = $this->cleanMovieTitle((string)$this->request->post('title', ''));
        if ($title === '') {
            $errors[] = 'Bitte gib einen aussagekräftigen Filmtitel an.';
        } elseif (mb_strlen($title, 'UTF-8') > 65) {
            $errors[] = 'Der Filmtitel darf maximal 65 Zeichen lang sein.';
        }

        $allowedTags = '<ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><p><br>';
        $description = trim(strip_tags((string)$this->request->post('description', ''), $allowedTags));
        $description = str_replace(self::ZERO_WIDTH_SPACES, '', $description);
        $description = str_replace(['#?', '?en'], ['#', 'en'], $description);
        if ($description === '') {
            $errors[] = 'Bitte gib eine aussagekräftige Beschreibung des Films an.';
        }

        $categoryMaster = trim((string)$this->request->post('category_master', 'porn'));
        if ($categoryMaster === 'erotic') {
            $categoryMaster = 'fetish';
        }
        if (!in_array($categoryMaster, ['porn', 'fetish'], true)) {
            $categoryMaster = 'porn';
        }

        $rawSlaves = $_POST['category_slave'] ?? ($_POST['categories'] ?? []);
        $validSlaves = [];
        if (is_array($rawSlaves)) {
            foreach ($rawSlaves as $s) {
                $sClean = trim(strip_tags((string)$s));
                if ($sClean !== '') {
                    $validSlaves[] = $sClean;
                }
            }
        }
        if (count($validSlaves) < 1) {
            $errors[] = 'Bitte wähle mindestens 1 passende Unterkategorie für den Film aus.';
        }
        $categorySlave = implode(',', array_unique($validSlaves));

        $actorId = abs((int)$this->request->post('actor_id', 0));
        if ($actorId <= 0) {
            $actors = $this->loadActiveMerchantActors($merchantId);
            if (count($actors) === 1) {
                $actorId = (int)$actors[0]['id'];
            } else {
                $errors[] = 'Bitte wähle ein aktives Darsteller-Profil aus.';
            }
        }

        $rawOnlineAt = trim((string)$this->request->post('online_at', ''));
        $onlineAt    = $rawOnlineAt !== '' ? date('Y-m-d H:i', strtotime($rawOnlineAt)) : date('Y-m-d H:i', strtotime('+90 minutes'));

        $isTrailer = ($this->request->post('trailer') !== null || $this->request->post('preview') !== null);
        $amountSecond = $isTrailer
            ? '0.0'
            : number_format(max(0.0, min(100.0, (float)$this->request->post('amount_second', 0.8))), 1, '.', '');

        $asDownload = abs((int)$this->request->post('as_download', 0)) === 1 ? 1 : 0;
        $amountDownload = $isTrailer
            ? 0
            : max(0, min(150, abs((int)$this->request->post('amount_download', 10))));

        $visibleWebsite = trim(strip_tags((string)$this->request->post('visible_for_website', '')));
        if ($visibleWebsite === 'public') {
            if (!$this->isPublicUploadAllowed($merchant, $merchantId)) {
                $visibleWebsite = '';
            }
        } elseif ($visibleWebsite !== '') {
            $rsSite = p4c_query("SELECT `id` FROM `sites`
                WHERE `partner_id` = '" . p4c_escape_string((string)$merchant->partner_id()) . "'
                  AND `domain` = '" . p4c_escape_string($visibleWebsite) . "'
                  AND `status` = '1'
                LIMIT 1;", __FILE__, __LINE__);
            if (!$rsSite || p4c_num_rows($rsSite) === 0) {
                $visibleWebsite = '';
            }
        }
        if ($visibleWebsite === '') {
            $errors[] = 'Bitte wähle eine gültige Website für die Veröffentlichung aus.';
        }

        // Prüfung auf doppelten Filmtitel
        if ($title !== '') {
            $rsDup = p4c_query("SELECT `id` FROM `movies`
                WHERE `title` = '" . p4c_escape_string($title) . "'
                  AND `merchant_id` = '" . $merchantId . "'
                  AND `status` != 'deleted'
                  AND `id` != '" . $movieId . "'
                LIMIT 1;", __FILE__, __LINE__);
            if ($rsDup && p4c_num_rows($rsDup) > 0) {
                $errors[] = 'Du hast bereits einen anderen Film mit diesem Titel hochgeladen. Bitte wähle einen eindeutigen Titel.';
            }
        }

        // Darsteller- & Drehpartner-Prüfung (Pflicht vor dem Upload)
        \CoActorManager::ensureSchema();
        $performerMode  = trim((string)$this->request->post('performer_mode', 'solo_owner'));
        $ownerInContent = ($performerMode === 'solo_owner')
            ? true
            : (abs((int)$this->request->post('owner_in_content', 0)) === 1);
        $rawCoActorIds  = isset($_POST['co_actor_ids']) && is_array($_POST['co_actor_ids']) ? $_POST['co_actor_ids'] : [];

        $performerCheck = \CoActorManager::validateContentPerformers(
            $merchantId,
            $performerMode,
            $ownerInContent,
            $rawCoActorIds,
            $categorySlave
        );
        if (!$performerCheck['valid']) {
            foreach ($performerCheck['errors'] as $pErr) {
                $errors[] = $pErr;
            }
        }

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 400);
            return;
        }

        $rawMetaTitle = $this->cleanMovieTitle(strip_tags((string)$this->request->post('meta_title', '')));
        $rawMetaTitle = str_replace(["\n", "\r"], '', $rawMetaTitle);
        $metaTitle    = $rawMetaTitle !== '' ? mb_substr($rawMetaTitle, 0, 65, 'UTF-8') : mb_substr($title, 0, 65, 'UTF-8');

        $rawSeoUrl = trim(strip_tags((string)$this->request->post('seo_url', '')));
        if ($rawSeoUrl !== '') {
            $seoUrl = function_exists('seo_url') ? (string)seo_url($rawSeoUrl) : (string)preg_replace('/[^a-zA-Z0-9\-]/', '', $rawSeoUrl);
        } else {
            $seoUrl = function_exists('seo_url') ? (string)seo_url($title) : '';
        }

        $metaDesc = trim(strip_tags((string)$this->request->post('meta_description', '')));
        $metaDesc = str_replace(["\n", "\r"], ' ', $metaDesc);
        if ($metaDesc === '') {
            $metaDesc = mb_substr(trim(strip_tags($description)), 0, 156, 'UTF-8');
        } else {
            $metaDesc = mb_substr($metaDesc, 0, 156, 'UTF-8');
        }

        $movieExists         = false;
        $existingPlaytimeSec = 0.0;
        if ($movieId > 0) {
            $rsExisting = p4c_query("SELECT `id`, `playtime_seconds` FROM `movies` WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
            if ($rsExisting && p4c_num_rows($rsExisting) > 0) {
                $movieExists         = true;
                $exObj               = p4c_fetch_object($rsExisting);
                $existingPlaytimeSec = (float)($exObj->playtime_seconds ?? 0);
            }
        }

        if ($movieExists) {
            $amountOwnSql = '';
            if ($existingPlaytimeSec > 0.0 || $isTrailer) {
                $recalcOwn    = $isTrailer ? 0 : (int)round($existingPlaytimeSec * (float)$amountSecond);
                $amountOwnSql = "`amount_own` = '" . abs($recalcOwn) . "',";
            }

            p4c_query("UPDATE `movies` SET
                `actor_id`            = '" . $actorId . "',
                `title`               = '" . p4c_escape_string($title) . "',
                `description`         = '" . p4c_escape_string($description) . "',
                `meta_title`          = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`    = '" . p4c_escape_string($metaDesc) . "',
                `seo_url`             = '" . p4c_escape_string($seoUrl) . "',
                `online_at`           = '" . p4c_escape_string($onlineAt) . "',
                `amount_second`       = '" . abs((float)$amountSecond) . "',
                {$amountOwnSql}
                `amount_webmaster`    = '10',
                `as_download`         = '" . $asDownload . "',
                `amount_download`     = '" . $amountDownload . "',
                `category_master`     = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`      = '" . p4c_escape_string($categorySlave) . "',
                `visible_for_website` = '" . p4c_escape_string($visibleWebsite) . "',
                `preview`             = '" . ($isTrailer ? 1 : 0) . "'
                WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);
        } else {
            $fileId        = md5($merchantId . '_' . microtime(true));
            $storageDir    = defined('MOVIES_DEFAULT_DIR') ? MOVIES_DEFAULT_DIR : 'movies';
            $notifyDefault = class_exists('EroCloudMailer') ? \EroCloudMailer::getMerchantNotifyPreference($merchantId) : 1;

            p4c_query("INSERT INTO `movies` SET
                `actor_id`            = '" . $actorId . "',
                `file_id`             = '" . p4c_escape_string($fileId) . "',
                `merchant_id`         = '" . $merchantId . "',
                `storage_location`    = '" . p4c_escape_string($storageDir) . "',
                `title`               = '" . p4c_escape_string($title) . "',
                `description`         = '" . p4c_escape_string($description) . "',
                `meta_title`          = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`    = '" . p4c_escape_string($metaDesc) . "',
                `seo_url`             = '" . p4c_escape_string($seoUrl) . "',
                `online_at`           = '" . p4c_escape_string($onlineAt) . "',
                `amount_second`       = '" . abs((float)$amountSecond) . "',
                `amount_webmaster`    = '10',
                `as_download`         = '" . $asDownload . "',
                `amount_download`     = '" . $amountDownload . "',
                `category_master`     = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`      = '" . p4c_escape_string($categorySlave) . "',
                `visible_for_website` = '" . p4c_escape_string($visibleWebsite) . "',
                `preview`             = '" . ($isTrailer ? 1 : 0) . "',
                `notify_on_convert`   = '" . $notifyDefault . "',
                `admin_infos`         = '';", __FILE__, __LINE__);

            $movieId = abs((int)p4c_insert_id());
        }

        if ($movieId === 0) {
            Response::error('Der Film-Entwurf konnte nicht gespeichert werden.', 500);
            return;
        }

        // Ausgewählte Darsteller & Drehpartner dauerhaft mit dem Film verknüpfen
        \CoActorManager::saveContentPerformers(
            'movie',
            $movieId,
            $merchantId,
            (string)$performerCheck['performer_mode'],
            (int)$performerCheck['owner_in_content'],
            (array)$performerCheck['final_co_actor_ids']
        );

        $_SESSION['upload_movie'] = ['movie_id' => $movieId];

        Response::json([
            'status'   => 'ok',
            'movie_id' => $movieId,
            'redirect' => '#/movie-upload?step=2&movie_id=' . $movieId,
            'message'  => 'Schritt 1 gespeichert. Bitte wähle nun deine Videodatei zum Hochladen aus.',
        ]);
    }

    /**
     * POST: Lädt die eigentliche Videodatei in Schritt 2 hoch (movies/upload-file)
     * Unterstützt sowohl Chunked-Uploads (5 MB Teilabschnitte via FormData oder php://input)
     * als auch den klassischen Single-POST-Modus.
     */
    public function uploadFile(): void
    {
        global $class_errorlog;

        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $movieId    = abs((int)$this->request->post('movie_id', $this->request->post('id', $this->request->get('movie_id', $this->request->get('id', 0)))));

        // Session-Lock sofort freigeben, damit parallele Requests nicht blockieren
        @session_write_close();

        if ($merchantId === 0 || $movieId === 0) {
            Response::error('Ungültige Film-ID oder abgelaufene Sitzung.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT *
            FROM `movies`
            WHERE `id` = '" . $movieId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND (`filename` = '' OR `convert_status` IN ('0', '3'))
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Für diesen Film wurde bereits eine Videodatei hochgeladen oder der Film existiert nicht.', 400);
            return;
        }

        $movieObj    = p4c_fetch_object($rsMovie);
        $moviesPath  = defined('MOVIES_PATH') ? MOVIES_PATH : (dirname(__DIR__, 5) . '/priv');
        $targetDir   = rtrim($moviesPath, '/\\') . '/' . $movieObj->storage_location . '/' . $merchantId . '/' . $movieId;
        $allowedExts = ['avi', 'flv', 'm4v', 'mkv', 'mov', 'mp4', 'mpg', 'wmv'];

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                Response::error('Das Zielverzeichnis für den Film konnte auf dem Server nicht angelegt werden.', 500);
                return;
            }
        }

        $rawChunkIndex  = $this->request->get('chunk_index', $this->request->post('chunk_index', null));
        $rawTotalChunks = $this->request->get('total_chunks', $this->request->post('total_chunks', null));
        $rawUploadUid   = $this->request->get('upload_uid', $this->request->post('upload_uid', null));
        $rawAbortUpload = (int)$this->request->get('abort_upload', $this->request->post('abort_upload', 0));

        // 0. Abbruch-Cleanup: Löscht temporäre .part-Dateien bei abgebrochenem Upload sofort vom Server
        if ($rawAbortUpload === 1 && $rawUploadUid !== null) {
            $cleanUid = (string)preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$rawUploadUid);
            if ($cleanUid !== '') {
                $abortPartPath = $targetDir . '/.part_' . $movieId . '_' . $cleanUid . '.tmp';
                if (is_file($abortPartPath)) {
                    @unlink($abortPartPath);
                }
            }
            Response::json(['status' => 'aborted']);
            return;
        }

        // 1. Chunked-Upload-Modus (Teilabschnitte via php://input oder FormData)
        if ($rawChunkIndex !== null && $rawTotalChunks !== null && $rawUploadUid !== null) {
            $chunkIndex    = abs((int)$rawChunkIndex);
            $totalChunks   = abs((int)$rawTotalChunks);
            $rawOffset     = $this->request->get('chunk_offset', $this->request->post('chunk_offset', null));
            $chunkOffset   = ($rawOffset !== null && is_numeric($rawOffset) && (float)$rawOffset >= 0) ? (int)$rawOffset : ($chunkIndex * 8388608);
            $rawFileSize   = $this->request->get('file_size', $this->request->post('file_size', 0));
            $totalFileSize = (is_numeric($rawFileSize) && (float)$rawFileSize > 0) ? (int)$rawFileSize : 0;
            $uploadUid     = (string)preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$rawUploadUid);
            $fileUpload    = $_FILES['video'] ?? ($_FILES['movie'] ?? null);
            $origFileName  = trim((string)$this->request->get('file_name', $this->request->post('file_name', is_array($fileUpload) ? ($fileUpload['name'] ?? '') : '')));

            if ($totalChunks < 1 || $chunkIndex >= $totalChunks || $uploadUid === '') {
                Response::error('Ungültige Chunk-Upload-Parameter. Bitte lade die Seite neu.', 400);
                return;
            }

            if ($totalFileSize > 2147483648) {
                Response::error('Deine Videodatei überschreitet das Limit von 2,0 GB.', 400);
                return;
            }

            $extension = strtolower((string)pathinfo($origFileName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExts, true)) {
                Response::error('Ungültiges Dateiformat. Erlaubt sind: avi, flv, m4v, mkv, mov, mp4, mpg, wmv.', 400);
                return;
            }

            $partFilePath = $targetDir . '/.part_' . $movieId . '_' . $uploadUid . '.tmp';

            if ($chunkIndex === 0 && $chunkOffset === 0) {
                $staleParts = @glob($targetDir . '/.part_*.tmp');
                if (is_array($staleParts)) {
                    foreach ($staleParts as $stalePart) {
                        if (is_file($stalePart) && $stalePart !== $partFilePath) {
                            @unlink($stalePart);
                        }
                    }
                }
            }

            $inFp = false;
            if (is_array($fileUpload)) {
                $chunkErr = (int)($fileUpload['error'] ?? UPLOAD_ERR_NO_FILE);
                $chunkTmp = (string)($fileUpload['tmp_name'] ?? '');
                if ($chunkErr === UPLOAD_ERR_OK && $chunkTmp !== '' && (is_uploaded_file($chunkTmp) || is_file($chunkTmp))) {
                    $inFp = @fopen($chunkTmp, 'rb');
                } elseif ($chunkErr !== UPLOAD_ERR_OK && $chunkErr !== UPLOAD_ERR_NO_FILE) {
                    Response::error("Fehler beim Datei-Upload (Code: {$chunkErr}).", 400);
                    return;
                }
            }

            if (!$inFp) {
                $inFp = @fopen('php://input', 'rb');
            }

            $outFp = @fopen($partFilePath, ($chunkIndex === 0 && $chunkOffset === 0) ? 'wb' : 'c+b');
            if (!$outFp) {
                if ($inFp) {
                    @fclose($inFp);
                }
                Response::error('Die Videodatei konnte auf dem Server nicht zwischengespeichert werden.', 500);
                return;
            }

            @flock($outFp, LOCK_EX);

            if ($chunkOffset > 0) {
                @fseek($outFp, $chunkOffset, SEEK_SET);
            }

            $bytesWritten = 0;
            if ($inFp) {
                while (!feof($inFp)) {
                    $buf = fread($inFp, 65536);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $written = fwrite($outFp, $buf);
                    if ($written === false) {
                        break;
                    }
                    $bytesWritten += $written;
                }
                @fclose($inFp);
            }

            @fflush($outFp);
            @flock($outFp, LOCK_UN);
            @fclose($outFp);

            if ($bytesWritten <= 0) {
                if (isset($class_errorlog) && is_object($class_errorlog)) {
                    $class_errorlog->log("MCP-Neu Movie-Chunk-Upload abgebrochen (Merchant #{$merchantId}, Movie #{$movieId}, Chunk {$chunkIndex}/{$totalChunks}): 0 Bytes empfangen.", __FILE__, __LINE__);
                }
                Response::error('Es wurden keine Daten für diesen Dateiabschnitt empfangen (0 Bytes).', 400);
                return;
            }

            if ($chunkIndex < $totalChunks - 1) {
                Response::json([
                    'status'      => 'chunk_ok',
                    'chunk_index' => $chunkIndex,
                    'bytes'       => $bytesWritten,
                ]);
                return;
            }

            clearstatcache(true, $partFilePath);
            $actualSize = is_file($partFilePath) ? (int)@filesize($partFilePath) : 0;
            if ($totalFileSize > 0 && $actualSize !== $totalFileSize) {
                @unlink($partFilePath);
                Response::error('Die Übertragung der Videodatei war unvollständig. Bitte starte den Upload erneut.', 400);
                return;
            }

            $targetFileName = $movieId . '_' . $movieObj->file_id . '.' . $extension;
            $targetFilePath = $targetDir . '/' . $targetFileName;

            if (is_file($targetFilePath)) {
                @unlink($targetFilePath);
            }

            if (!@rename($partFilePath, $targetFilePath)) {
                if (!@copy($partFilePath, $targetFilePath)) {
                    Response::error('Die Videodatei konnte nicht final auf dem Server gespeichert werden.', 500);
                    return;
                }
                @unlink($partFilePath);
            }
        } else {
            // 2. Klassischer Single-POST-Modus (Fallback)
            $fileUpload = $_FILES['movie'] ?? ($_FILES['video'] ?? null);
            if (!is_array($fileUpload) || (int)$fileUpload['error'] !== UPLOAD_ERR_OK) {
                $errCode = is_array($fileUpload) && isset($fileUpload['error']) ? (int)$fileUpload['error'] : -1;
                Response::error("Fehler beim Datei-Upload (Code: {$errCode}). Bitte prüfe die Dateigröße (max. 2,0 GB).", 400);
                return;
            }

            $fileName    = (string)$fileUpload['name'];
            $tmpFileName = (string)$fileUpload['tmp_name'];
            $extension   = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExts, true)) {
                Response::error('Ungültiges Dateiformat. Erlaubt sind: avi, flv, m4v, mkv, mov, mp4, mpg, wmv.', 400);
                return;
            }

            $targetFileName = $movieId . '_' . $movieObj->file_id . '.' . $extension;
            $targetFilePath = $targetDir . '/' . $targetFileName;

            if (!@move_uploaded_file($tmpFileName, $targetFilePath) || !is_file($targetFilePath)) {
                Response::error('Die Videodatei konnte nicht auf dem Server gespeichert werden.', 500);
                return;
            }
        }

        // Video-Metadaten mit getID3 aus der fertigen Zieldatei auslesen (analog upload_movie.php)
        $playTime    = '0:00';
        $playTimeSec = 0;
        $width       = 1920;
        $height      = 1080;

        $getId3Path = defined('MCP_DIR') ? MCP_DIR . '/includes/getid3/getid3.php' : (dirname(__DIR__, 4) . '/includes/getid3/getid3.php');
        if (file_exists($getId3Path)) {
            try {
                include_once $getId3Path;
                if (class_exists('getID3')) {
                    $getID3 = new \getID3();
                    $getID3->option_tag_id3v1 = false;
                    $getID3->option_tag_id3v2 = false;
                    $getID3->option_tag_lyrics3 = false;
                    $getID3->option_tag_apetag = false;
                    $getID3->option_save_attachments = false;
                    $fileinfo = @$getID3->analyze($targetFilePath);
                    if (!empty($fileinfo['playtime_string'])) {
                        $playTime = (string)$fileinfo['playtime_string'];
                    }
                    if (isset($fileinfo['playtime_seconds']) && is_numeric($fileinfo['playtime_seconds'])) {
                        $playTimeSec = (int)round((float)$fileinfo['playtime_seconds']);
                    }
                    if (!empty($fileinfo['video']['resolution_x']) && !empty($fileinfo['video']['resolution_y'])) {
                        $rx = (int)round((float)$fileinfo['video']['resolution_x']);
                        $ry = (int)round((float)$fileinfo['video']['resolution_y']);
                        if ($rx > 0 && $ry > 0) {
                            $width  = $rx;
                            $height = $ry;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fallback auf Standard-Metadaten (wird durch FFmpeg-Konvertierung aktualisiert)
            }
        }

        $resolution = $width . 'x' . $height;
        $isPreview  = abs((int)($movieObj->preview ?? 0)) === 1;
        $amountOwn  = $isPreview ? 0 : (int)round((float)$playTimeSec * (float)$movieObj->amount_second);
        $rawRel     = (string)$this->request->post('released', $this->request->get('released', ''));
        $released   = ($rawRel === '1' || $rawRel === 'true');

        $releaseSql = '';
        if ($released) {
            $releaseSql = ", `create_datetime` = '" . date('Y-m-d H:i:s') . "', `movie_checked` = '0000-00-00 00:00:00', `released` = '1'";
        }

        p4c_query("UPDATE `movies` SET
            `filename`          = '" . p4c_escape_string($targetFileName) . "',
            `playtime_string`   = '" . p4c_escape_string($playTime) . "',
            `playtime_seconds`  = '" . p4c_escape_string((string)$playTimeSec) . "',
            `resolution`        = '" . p4c_escape_string($resolution) . "',
            `amount_own`        = '" . abs($amountOwn) . "',
            `convert_status`    = '0',
            `convert_starttime` = '0000-00-00 00:00:00',
            `convert_endtime`   = '0000-00-00 00:00:00'
            {$releaseSql}
            WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (function_exists('log_action')) {
            log_action("Videodatei hochgeladen für Film-ID {$movieId} ({$targetFileName})");
        }

        Response::json([
            'status'          => 'ok',
            'movie_id'        => $movieId,
            'filename'        => $targetFileName,
            'playtime_string' => $playTime,
            'resolution'      => $resolution,
            'message'         => 'Deine Videodatei wurde erfolgreich hochgeladen! Wähle nun unten aus, wie du mit den Vorschaubildern und der Freigabe fortfahren möchtest.',
        ]);
    }

    /**
     * POST: Schließt Schritt 2 nach dem Video-Upload ab (movies/complete-upload)
     * Entscheidet, ob die Creatorin erst die Konvertierung & Vorschaubild-Bearbeitung abwarten möchte
     * oder den Film direkt mit Standard-Vorschaubildern zur Prüfung einreicht.
     */
    public function completeUpload(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $movieId    = abs((int)$this->request->post('movie_id', $this->request->post('id', 0)));

        if ($merchantId === 0 || $movieId === 0) {
            Response::error('Ungültige Film-ID oder abgelaufene Sitzung.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT `id`, `title`, `filename`, `released`
            FROM `movies`
            WHERE `id` = '" . $movieId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Der gewünschte Film wurde nicht gefunden.', 404);
            return;
        }

        $movieObj = p4c_fetch_object($rsMovie);
        if (trim((string)$movieObj->filename) === '') {
            Response::error('Bitte lade zuerst deine Videodatei hoch, bevor du diesen Schritt abschließt.', 400);
            return;
        }

        $releaseMode     = trim((string)$this->request->post('release_mode', 'later'));
        $isDirect        = ($releaseMode === 'direct' || (string)$this->request->post('released', '') === '1');
        $notifyOnConvert = abs((int)$this->request->post('notify_on_convert', 1)) === 1 ? 1 : 0;

        if (class_exists('EroCloudMailer')) {
            \EroCloudMailer::setMerchantNotifyPreference($merchantId, $notifyOnConvert);
        }

        if ($isDirect) {
            p4c_query("UPDATE `movies` SET
                `create_datetime`   = '" . date('Y-m-d H:i:s') . "',
                `movie_checked`     = '0000-00-00 00:00:00',
                `released`          = '1',
                `notify_on_convert` = '" . $notifyOnConvert . "'
                WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);

            if (function_exists('log_action')) {
                log_action("Film direkt nach Upload zur Prüfung eingereicht: ID {$movieId}");
            }
        } else {
            p4c_query("UPDATE `movies` SET
                `released`          = '0',
                `notify_on_convert` = '" . $notifyOnConvert . "'
                WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);
        }

        unset($_SESSION['upload_movie']);

        Response::json([
            'status'       => 'ok',
            'movie_id'     => $movieId,
            'release_mode' => $isDirect ? 'direct' : 'later',
            'redirect'     => '#/movie-upload?step=3&movie_id=' . $movieId,
            'message'      => $isDirect
                ? 'Dein Film wurde zur Prüfung eingereicht und wird nach der Konvertierung veröffentlicht.'
                : 'Super! Dein Film wird nun konvertiert. Sobald die 10 Vorschaubilder bereitstehen, kannst du sie im Cover-Studio bearbeiten.',
        ]);
    }

    /**
     * POST: Aktualisiert einen bestehenden Film und gibt ihn zur Prüfung frei (movies/update)
     */
    public function update(): void
    {
        global $mysql;

        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $movieId    = abs((int)$this->request->post('movie_id', $this->request->post('id', 0)));

        if ($merchantId === 0 || $movieId === 0) {
            Response::error('Ungültige Film-ID oder abgelaufene Sitzung.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT *
            FROM `movies`
            WHERE `id` = '" . $movieId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Du besitzt keine Berechtigung zur Bearbeitung dieses Films.', 403);
            return;
        }

        $mObj   = p4c_fetch_object($rsMovie);
        $errors = [];

        $title = $this->cleanMovieTitle((string)$this->request->post('title', ''));
        if ($title === '') {
            $errors[] = 'Bitte gib einen aussagekräftigen Filmtitel an.';
        } elseif (mb_strlen($title, 'UTF-8') > 65) {
            $errors[] = 'Der Filmtitel darf maximal 65 Zeichen lang sein.';
        }

        // Duplikatsprüfung
        if ($title !== '') {
            $rsDup = p4c_query("SELECT `id` FROM `movies`
                WHERE `title` = '" . p4c_escape_string($title) . "'
                  AND `merchant_id` = '" . $merchantId . "'
                  AND `status` != 'deleted'
                  AND `id` != '" . $movieId . "'
                LIMIT 1;", __FILE__, __LINE__);
            if ($rsDup && p4c_num_rows($rsDup) > 0) {
                $errors[] = 'Du hast bereits einen anderen Film mit diesem Titel hochgeladen.';
            }
        }

        $allowedTags = '<ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><p><br>';
        $description = trim(strip_tags((string)$this->request->post('description', ''), $allowedTags));
        $description = str_replace(self::ZERO_WIDTH_SPACES, '', $description);
        $description = str_replace(['#?', '?en'], ['#', 'en'], $description);
        if ($description === '') {
            $errors[] = 'Bitte gib eine aussagekräftige Beschreibung des Films an.';
        }

        $actorId = abs((int)$this->request->post('actor_id', (int)$mObj->actor_id));
        if ($actorId <= 0) {
            $actors = $this->loadActiveMerchantActors($merchantId, (int)$mObj->actor_id);
            if (count($actors) === 1) {
                $actorId = (int)$actors[0]['id'];
            } else {
                $errors[] = 'Bitte wähle ein Darsteller-Profil aus.';
            }
        }

        $fsk16 = (string)$mObj->preview_image_fsk16;
        $fsk18 = (string)$mObj->preview_image_fsk18;
        if ((int)$mObj->convert_status > 1) {
            $postFsk16 = abs((int)$this->request->post('fsk16', $this->request->post('preview_image_fsk16', (int)$mObj->preview_image_fsk16)));
            $postFsk18 = abs((int)$this->request->post('fsk18', $this->request->post('preview_image_fsk18', (int)$mObj->preview_image_fsk18)));
            if ($postFsk16 <= 0) {
                $errors[] = 'Bitte wähle ein FSK16-Vorschaubild aus.';
            } else {
                $fsk16 = (string)$postFsk16;
            }
            if ($postFsk18 <= 0) {
                $errors[] = 'Bitte wähle ein FSK18-Vorschaubild aus.';
            } else {
                $fsk18 = (string)$postFsk18;
            }
        }

        $categoryMaster = trim((string)$this->request->post('category_master', 'porn'));
        if ($categoryMaster === 'erotic') {
            $categoryMaster = 'fetish';
        }
        if (!in_array($categoryMaster, ['porn', 'fetish'], true)) {
            $errors[] = 'Bitte wähle eine Hauptkategorie (Porno oder Fetisch) aus.';
        }

        $rawSlaves   = $_POST['category_slave'] ?? ($_POST['categories'] ?? []);
        $validSlaves = [];
        if (is_array($rawSlaves)) {
            foreach ($rawSlaves as $s) {
                $sClean = trim(strip_tags((string)$s));
                if ($sClean !== '') {
                    $validSlaves[] = $sClean;
                }
            }
        }
        if (count($validSlaves) < 1) {
            $errors[] = 'Bitte wähle mindestens 1 passende Unterkategorie aus.';
        }
        $categorySlave = implode(',', array_unique($validSlaves));

        // Darsteller- & Drehpartner-Prüfung (Pflicht vor dem Einreichen)
        \CoActorManager::ensureSchema();
        $performerMode  = trim((string)$this->request->post('performer_mode', (string)($mObj->performer_mode ?? 'solo_owner')));
        $ownerInContent = ($performerMode === 'solo_owner')
            ? true
            : (abs((int)$this->request->post('owner_in_content', 0)) === 1);
        $rawCoActorIds  = isset($_POST['co_actor_ids']) && is_array($_POST['co_actor_ids']) ? $_POST['co_actor_ids'] : [];

        $performerCheck = \CoActorManager::validateContentPerformers(
            $merchantId,
            $performerMode,
            $ownerInContent,
            $rawCoActorIds,
            $categorySlave
        );
        if (!$performerCheck['valid']) {
            foreach ($performerCheck['errors'] as $pErr) {
                $errors[] = $pErr;
            }
        }

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 400);
            return;
        }

        $rawOnlineAt = trim((string)$this->request->post('online_at', ''));
        $onlineAt    = $rawOnlineAt !== ''
            ? date('Y-m-d H:i', strtotime($rawOnlineAt))
            : date('Y-m-d H:i', strtotime((string)$mObj->online_at));

        $isTrailer = ($this->request->post('trailer') !== null || $this->request->post('preview') !== null);
        $amountSecond = $isTrailer
            ? '0.0'
            : number_format(max(0.0, min(100.0, (float)$this->request->post('amount_second', $mObj->amount_second))), 1, '.', '');

        $asDownload = abs((int)$this->request->post('as_download', 0)) === 1 ? 1 : 0;
        $amountDownload = $isTrailer
            ? 0
            : max(0, min(150, abs((int)$this->request->post('amount_download', (int)$mObj->amount_download))));

        $merchant       = new \Merchant($mysql, $merchantId);
        $visibleWebsite = trim(strip_tags((string)$this->request->post('visible_for_website', (string)$mObj->visible_for_website)));
        if ($visibleWebsite === 'public') {
            if (!$this->isPublicUploadAllowed($merchant, $merchantId, (string)$mObj->visible_for_website)) {
                $visibleWebsite = '';
            }
        } elseif ($visibleWebsite !== '') {
            $rsSite = p4c_query("SELECT `id` FROM `sites`
                WHERE `partner_id` = '" . p4c_escape_string((string)$merchant->partner_id()) . "'
                  AND `domain` = '" . p4c_escape_string($visibleWebsite) . "'
                  AND `status` = '1'
                LIMIT 1;", __FILE__, __LINE__);
            if (!$rsSite || p4c_num_rows($rsSite) === 0) {
                $visibleWebsite = '';
            }
        }
        if ($visibleWebsite === '') {
            $visibleWebsite = (string)$mObj->visible_for_website;
        }

        $amountOwn = $isTrailer ? 0 : (int)round((float)$mObj->playtime_seconds * (float)$amountSecond);

        $rawMetaTitle = trim(strip_tags((string)$this->request->post('meta_title', '')));
        $metaTitle    = $rawMetaTitle !== '' ? mb_substr($rawMetaTitle, 0, 65, 'UTF-8') : mb_substr($title, 0, 65, 'UTF-8');

        $rawSeoUrl = trim(strip_tags((string)$this->request->post('seo_url', '')));
        if ($rawSeoUrl !== '') {
            $seoUrl = function_exists('seo_url') ? (string)seo_url($rawSeoUrl) : (string)preg_replace('/[^a-zA-Z0-9\-]/', '', $rawSeoUrl);
        } else {
            $seoUrl = function_exists('seo_url') ? (string)seo_url($title) : (string)$mObj->seo_url;
        }

        $metaDesc = trim(strip_tags((string)$this->request->post('meta_description', '')));
        $metaDesc = str_replace(["\n", "\r"], ' ', $metaDesc);
        if ($metaDesc === '') {
            $metaDesc = mb_substr(trim(strip_tags($description)), 0, 156, 'UTF-8');
        } else {
            $metaDesc = mb_substr($metaDesc, 0, 156, 'UTF-8');
        }

        $checksumData = [
            'merchant_id'      => $merchantId,
            'fsk16'            => $fsk16,
            'fsk18'            => $fsk18,
            'title'            => $title,
            'description'      => $description,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'seo_url'          => $seoUrl,
            'online_at'        => $onlineAt,
            'amount_second'    => $amountSecond,
            'amount_own'       => $amountOwn,
            'amount_webmaster' => $mObj->amount_webmaster,
            'as_download'      => $asDownload,
            'amount_download'  => $amountDownload,
        ];
        $checksum = function_exists('movie_checksum') ? (string)movie_checksum($checksumData) : md5(json_encode($checksumData));

        $createDateSql = ((int)$mObj->convert_status === 2 && (int)$mObj->released === 0)
            ? "`create_datetime` = '" . date('Y-m-d H:i:s') . "',"
            : '';

        p4c_query("UPDATE `movies` SET
            {$createDateSql}
            `actor_id`            = '" . $actorId . "',
            `checksum`            = '" . p4c_escape_string($checksum) . "',
            `preview_image_fsk16` = '" . abs((int)$fsk16) . "',
            `preview_image_fsk18` = '" . abs((int)$fsk18) . "',
            `title`               = '" . p4c_escape_string($title) . "',
            `description`         = '" . p4c_escape_string($description) . "',
            `meta_title`          = '" . p4c_escape_string($metaTitle) . "',
            `meta_description`    = '" . p4c_escape_string($metaDesc) . "',
            `seo_url`             = '" . p4c_escape_string($seoUrl) . "',
            `online_at`           = '" . p4c_escape_string($onlineAt) . "',
            `amount_second`       = '" . abs((float)$amountSecond) . "',
            `amount_own`          = '" . abs($amountOwn) . "',
            `as_download`         = '" . $asDownload . "',
            `amount_download`     = '" . $amountDownload . "',
            `category_master`     = '" . p4c_escape_string($categoryMaster) . "',
            `category_slave`      = '" . p4c_escape_string($categorySlave) . "',
            `visible_for_website` = '" . p4c_escape_string($visibleWebsite) . "',
            `preview`             = '" . ($isTrailer ? 1 : 0) . "',
            `movie_checked`       = '0000-00-00 00:00:00',
            `released`            = '1'
            WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        // Ausgewählte Darsteller & Drehpartner dauerhaft mit dem Film verknüpfen
        \CoActorManager::saveContentPerformers(
            'movie',
            $movieId,
            $merchantId,
            (string)$performerCheck['performer_mode'],
            (int)$performerCheck['owner_in_content'],
            (array)$performerCheck['final_co_actor_ids']
        );

        // Falls Film bereits in movies_online existiert, Kategorien synchronisieren
        $fileId = (string)$mObj->file_id;
        p4c_query("UPDATE `movies_online` SET
            `category_master` = '" . p4c_escape_string($categoryMaster) . "',
            `category_slave`  = '" . p4c_escape_string($categorySlave) . "'
            WHERE `file_id` = '" . p4c_escape_string($fileId) . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (function_exists('log_action')) {
            log_action("Film bearbeitet und zur Prüfung eingereicht: {$title} (ID: {$movieId})");
        }

        Response::json([
            'status'   => 'ok',
            'movie_id' => $movieId,
            'message'  => 'Deine Änderungen wurden gespeichert. Der Film wird nun geprüft und veröffentlicht.',
        ]);
    }

    /**
     * POST: Lädt ein eigenes FSK16- oder FSK18-Vorschaubild für einen Film hoch (movies/upload-poster)
     */
    public function uploadPoster(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $movieId    = abs((int)$this->request->post('movie_id', $this->request->post('id', 0)));
        $fsk        = abs((int)$this->request->post('fsk', 0));

        if ($merchantId === 0 || $movieId === 0 || !in_array($fsk, [16, 18], true)) {
            Response::error('Ungültige Parameter für den Vorschaubild-Upload.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT *
            FROM `movies`
            WHERE `id` = '" . $movieId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Du besitzt keine Berechtigung für diesen Film.', 403);
            return;
        }

        $posterUpload = $_FILES['movie_poster'] ?? ($_FILES['poster'] ?? null);
        if (!is_array($posterUpload) || (int)$posterUpload['error'] !== UPLOAD_ERR_OK) {
            Response::error('Bitte wähle eine gültige Bilddatei (JPG oder PNG, max. 6 MB) aus.', 400);
            return;
        }

        $fileTmp  = (string)$posterUpload['tmp_name'];
        $fileName = (string)$posterUpload['name'];
        $fileSize = abs((int)$posterUpload['size']);

        if ($fileSize === 0 || $fileSize > 6291456) {
            Response::error('Die Bilddatei darf maximal 6 MB groß sein.', 400);
            return;
        }

        $extension = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            Response::error('Es sind ausschließlich Bilddateien im Format JPG, JPEG oder PNG erlaubt.', 400);
            return;
        }

        $imgInfo = @getimagesize($fileTmp);
        if ($imgInfo === false || !in_array($imgInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            Response::error('Die hochgeladene Datei ist kein gültiges Bild.', 400);
            return;
        }

        $mObj       = p4c_fetch_object($rsMovie);
        $imgId      = ($fsk === 16) ? 11 : 12;
        $moviesPath = defined('MOVIES_PATH') ? MOVIES_PATH : (dirname(__DIR__, 5) . '/priv');
        $targetDir  = rtrim($moviesPath, '/\\') . '/' . $mObj->storage_location . '/' . $merchantId . '/' . $movieId;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        // Bestehende eigene Vorschaubilder (jpg/jpeg/png) für diese img_id bereinigen
        $baseThumbPrefix = $targetDir . '/thumb_' . $movieId . '_' . strtolower((string)$mObj->file_id) . '_' . $imgId;
        foreach (['jpg', 'jpeg', 'png'] as $ext) {
            if (is_file($baseThumbPrefix . '.' . $ext)) {
                @unlink($baseThumbPrefix . '.' . $ext);
            }
        }

        $targetPath = $baseThumbPrefix . '.' . $extension;
        if (!@move_uploaded_file($fileTmp, $targetPath) || !is_file($targetPath)) {
            Response::error('Das Vorschaubild konnte nicht auf dem Server gespeichert werden.', 500);
            return;
        }

        $colName = ($fsk === 16) ? 'preview_image_fsk16' : 'preview_image_fsk18';
        $checksumData = [
            'merchant_id'      => $merchantId,
            'fsk16'            => time(),
            'fsk18'            => time(),
            'title'            => $mObj->title,
            'description'      => $mObj->description,
            'meta_title'       => $mObj->meta_title,
            'meta_description' => $mObj->meta_description,
            'seo_url'          => $mObj->seo_url,
            'online_at'        => $mObj->online_at,
            'amount_second'    => $mObj->amount_second,
            'amount_own'       => $mObj->amount_own,
            'amount_webmaster' => $mObj->amount_webmaster,
            'as_download'      => $mObj->as_download,
            'amount_download'  => $mObj->amount_download,
        ];
        $checksum = function_exists('movie_checksum') ? (string)movie_checksum($checksumData) : md5(json_encode($checksumData));

        // Nur wenn der Film bereits veröffentlicht oder zur Prüfung eingereicht war, erneut in die Prüfung einreihen.
        // Ein noch unveröffentlichter Entwurf (released == 0) bleibt im Entwurfsmodus, bis der Creator aktiv auf "Einreichen" klickt.
        $wasSubmittedOrChecked = ((int)$mObj->released === 1 || ((string)$mObj->movie_checked !== '0000-00-00 00:00:00' && (string)$mObj->movie_checked !== ''));
        $recheckSql = $wasSubmittedOrChecked
            ? ", `movie_checked` = '0000-00-00 00:00:00', `released` = '1'"
            : '';

        p4c_query("UPDATE `movies` SET
            `" . $colName . "` = '" . $imgId . "',
            `checksum`      = '" . p4c_escape_string($checksum) . "'
            {$recheckSql}
            WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        $mcpUrl    = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
        $ts        = time();
        $thumbUrl  = $mcpUrl . '/thumb.php?movie_id=' . rawurlencode((string)$mObj->file_id) . '&thumb_number=' . $imgId . '&w=186&t=' . $ts;
        $zoomUrl   = $mcpUrl . '/thumb.php?movie_id=' . rawurlencode((string)$mObj->file_id) . '&thumb_number=' . $imgId . '&w=640&t=' . $ts;
        $editorUrl = $mcpUrl . '/thumb.php?movie_id=' . rawurlencode((string)$mObj->file_id) . '&thumb_number=' . $imgId . '&w=1024&t=' . $ts;

        Response::json([
            'status'       => 'ok',
            'fsk'          => $fsk,
            'thumb_number' => $imgId,
            'thumb_url'    => $thumbUrl,
            'preview_url'  => $thumbUrl,
            'zoom_url'     => $zoomUrl,
            'editor_url'   => $editorUrl,
            'message'      => "Dein eigenes FSK{$fsk}-Vorschaubild wurde erfolgreich hochgeladen und ausgewählt.",
        ]);
    }

    /**
     * POST: Soft-Delete eines noch nicht veröffentlichten Films (movies/delete)
     */
    public function delete(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $movieId    = abs((int)$this->request->post('movie_id', $this->request->post('id', 0)));

        if ($merchantId === 0 || $movieId === 0) {
            Response::error('Ungültige Film-ID.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT *
            FROM `movies`
            WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Der Film wurde nicht gefunden.', 404);
            return;
        }

        $mObj   = p4c_fetch_object($rsMovie);
        $fileId = (string)$mObj->file_id;

        // Prüfen, ob der Film jemals freigegeben war, online ist oder Käufe besitzt (analog movie.php)
        $isPublishedOrPurchased = ((string)$mObj->movie_checked !== '0000-00-00 00:00:00' && (string)$mObj->movie_checked !== '');

        if (!$isPublishedOrPurchased) {
            $rsOnline = p4c_query("SELECT `id` FROM `movies_online` WHERE `file_id` = '" . p4c_escape_string($fileId) . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
                $isPublishedOrPurchased = true;
            }
        }

        if (!$isPublishedOrPurchased) {
            $rsAccess = p4c_query("SELECT `id` FROM `movies_access` WHERE `movie_id` = '" . p4c_escape_string($fileId) . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsAccess && p4c_num_rows($rsAccess) > 0) {
                $isPublishedOrPurchased = true;
            }
        }

        $deletedDatetime = $isPublishedOrPurchased ? date('Y-m-d H:i:s') : '0000-00-00 00:00:00';

        p4c_query("UPDATE `movies` SET
            `status`           = 'deleted',
            `deleted_datetime` = '" . $deletedDatetime . "'
            WHERE `id` = '" . $movieId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        p4c_query("UPDATE `movies_online` SET
            `status`           = 'deleted',
            `deleted_datetime` = '" . $deletedDatetime . "'
            WHERE `file_id` = '" . p4c_escape_string($fileId) . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (isset($_SESSION['upload_movie']['movie_id']) && (int)$_SESSION['upload_movie']['movie_id'] === $movieId) {
            unset($_SESSION['upload_movie']);
        }

        if (function_exists('log_action')) {
            log_action("Film gelöscht: ID {$movieId}");
        }

        Response::json([
            'status'  => 'ok',
            'message' => 'Der Film wurde erfolgreich gelöscht.',
        ]);
    }

    /**
     * GET: Liefert den aktuellen Live-Konvertierungsstatus inkl. Warteschlangen-Position und Restzeit-Schätzung (movies/conversion-status)
     */
    public function conversionStatus(): void
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            @session_write_close();
            Response::error('Deine Sitzung ist abgelaufen.', 401);
            return;
        }
        @session_write_close();

        $movieId = abs((int)$this->request->get('id', $this->request->get('movie_id', 0)));
        if ($movieId === 0) {
            Response::error('Ungültige Film-ID.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT `id`, `convert_status`, `playtime_seconds`, `playtime_string`, `resolution`,
                `convert_starttime`, `convert_endtime`, `released`, `movie_checked`, `filename`
            FROM `movies`
            WHERE `id` = '" . $movieId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Der Film wurde nicht gefunden.', 404);
            return;
        }

        $mObj       = p4c_fetch_object($rsMovie);
        $convStatus = (int)$mObj->convert_status;
        $playtime   = (int)round((float)($mObj->playtime_seconds ?? 0));
        $convStart  = (string)($mObj->convert_starttime ?? '0000-00-00 00:00:00');

        $queueInfo = $this->calculateConversionQueueInfo($movieId, $convStatus, $playtime, $convStart);

        Response::json([
            'status'          => 'ok',
            'movie_id'        => $movieId,
            'convert_status'  => $convStatus,
            'released'        => (int)$mObj->released,
            'is_finished'     => ($convStatus === 2),
            'is_error'        => ($convStatus === 3),
            'playtime_string' => (string)($mObj->playtime_string ?? ''),
            'resolution'      => (string)($mObj->resolution ?? ''),
            'queue_info'      => $queueInfo,
        ]);
    }

    /**
     * Berechnet Warteschlangen-Position, durchschnittliche Konvertierungsgeschwindigkeit und geschätzte Restdauer.
     */
    private function calculateConversionQueueInfo(int $movieId, int $convStatus, int $playtimeSeconds, string $convStart): array
    {
        // 1. Durchschnittliche Konvertierungsgeschwindigkeit aus den letzten 25 abgeschlossenen Konvertierungen ermitteln
        $secPerVideoSec = 0.45; // Realistischer Server-Standardwert (~27 Sek. Konvertierung pro Filmminute)
        $rsStats = p4c_query("SELECT
                `playtime_seconds`,
                TIMESTAMPDIFF(SECOND, `convert_starttime`, `convert_endtime`) AS `conv_duration`
            FROM `movies`
            WHERE `convert_status` = '2'
              AND `playtime_seconds` > 30
              AND `convert_starttime` != '0000-00-00 00:00:00'
              AND `convert_endtime` > `convert_starttime`
            ORDER BY `id` DESC
            LIMIT 25;", __FILE__, __LINE__);

        if ($rsStats && p4c_num_rows($rsStats) > 0) {
            $totalVideoSec = 0.0;
            $totalConvSec  = 0.0;
            while ($st = p4c_fetch_object($rsStats)) {
                $vSec = (float)($st->playtime_seconds ?? 0);
                $cSec = (float)($st->conv_duration ?? 0);
                // Ausreißer (z. B. hängengebliebene Prozesse > 4 Std.) ignorieren
                if ($vSec >= 30.0 && $cSec >= 5.0 && $cSec <= 14400.0) {
                    $totalVideoSec += $vSec;
                    $totalConvSec  += $cSec;
                }
            }
            if ($totalVideoSec >= 120.0 && $totalConvSec >= 15.0) {
                $secPerVideoSec = max(0.20, min(1.60, $totalConvSec / $totalVideoSec));
            }
        }

        $avgSecPerFilmMinute = max(12, (int)round($secPerVideoSec * 60));

        // Falls bereits fertig oder Fehler, direkt kompaktes Ergebnis liefern
        if ($convStatus >= 2) {
            return [
                'state'                   => $convStatus === 2 ? 'finished' : 'error',
                'movies_ahead'            => 0,
                'queue_position'          => 0,
                'elapsed_seconds'         => 0,
                'remaining_seconds'       => 0,
                'remaining_formatted'     => 'Abgeschlossen',
                'progress_percent'        => $convStatus === 2 ? 100 : 0,
                'avg_sec_per_film_minute' => $avgSecPerFilmMinute,
                'status_headline'         => $convStatus === 2
                    ? 'Die Konvertierung ist abgeschlossen!'
                    : 'Bei der Konvertierung ist ein Fehler aufgetreten.',
                'status_detail'           => '',
            ];
        }

        // 2. Aktive und wartende Filme in der Warteschlange abfragen (exakt nach Reihenfolge in cronjobs/convert_movie.php)
        $moviesAhead           = 0;
        $aheadRemainingSeconds = 0;

        $rsQueue = p4c_query("SELECT `id`, `convert_status`, `playtime_seconds`, `convert_starttime`
            FROM `movies`
            WHERE `convert_status` IN ('0', '1')
              AND `filename` != ''
              AND `status` != 'deleted'
            ORDER BY `convert_status` DESC, `id` ASC;", __FILE__, __LINE__);

        if ($rsQueue && p4c_num_rows($rsQueue) > 0) {
            while ($qRow = p4c_fetch_object($rsQueue)) {
                $qId = abs((int)$qRow->id);
                if ($qId === $movieId) {
                    break;
                }
                // Nur Filme zählen, die tatsächlich vor dem aktuellen Film dran sind
                $qStatus = (int)$qRow->convert_status;
                if ($convStatus === 1 && $qStatus === 0) {
                    continue;
                }
                if ($convStatus === 0 && $qStatus === 0 && $qId > $movieId) {
                    continue;
                }

                $moviesAhead++;
                $qPlaytime = (float)($qRow->playtime_seconds ?? 0);
                if ($qPlaytime <= 0.0) {
                    $qPlaytime = 300.0;
                }
                $qEstTotal = max(35, (int)round($qPlaytime * $secPerVideoSec + 22));

                if ($qStatus === 1) {
                    $qStart   = (string)($qRow->convert_starttime ?? '0000-00-00 00:00:00');
                    $qElapsed = ($qStart !== '0000-00-00 00:00:00' && $qStart !== '')
                        ? max(0, time() - (int)strtotime($qStart))
                        : 0;
                    $aheadRemainingSeconds += max(15, $qEstTotal - $qElapsed);
                } else {
                    $aheadRemainingSeconds += $qEstTotal;
                }
            }
        }

        // 3. Eigene Konvertierungsdauer & Fortschritt berechnen
        $ownPlaytime = $playtimeSeconds > 0 ? (float)$playtimeSeconds : 300.0;
        $ownEstTotal = max(35, (int)round($ownPlaytime * $secPerVideoSec + 22));
        $ownElapsed  = 0;

        if ($convStatus === 1) {
            if ($convStart !== '0000-00-00 00:00:00' && $convStart !== '') {
                $ownElapsed = max(0, time() - (int)strtotime($convStart));
            }
            $ownRemaining    = max(12, $ownEstTotal - $ownElapsed);
            $progressPercent = min(95, max(12, (int)round(($ownElapsed / max(1, $ownEstTotal)) * 100)));
        } else {
            // Warteschlange (convert_status == 0): + ca. 20 Sek. bis zum nächsten Cronjob-Start
            $ownRemaining    = $ownEstTotal + 20;
            $progressPercent = $moviesAhead === 0 ? 8 : max(3, (int)round(12 / ($moviesAhead + 1)));
        }

        $totalRemainingSeconds = $aheadRemainingSeconds + $ownRemaining;
        $queuePosition         = $moviesAhead + 1;
        $remainingFormatted    = self::formatDurationEstimate($totalRemainingSeconds);

        if ($convStatus === 1) {
            $elapsedStr = $ownElapsed >= 60
                ? ((int)floor($ownElapsed / 60) . ' Min. ' . ($ownElapsed % 60) . ' Sek.')
                : (max(1, $ownElapsed) . ' Sek.');
            $statusHeadline = 'Dein Film wird gerade aktiv in HD konvertiert';
            $statusDetail   = "Läuft seit {$elapsedStr} · 10 Szenen-Vorschaubilder werden generiert (Ø ca. {$avgSecPerFilmMinute} Sek. pro Filmminute).";
        } elseif ($moviesAhead === 0) {
            $statusHeadline = 'Platz 1 in der Warteschlange – Konvertierung startet jeden Moment';
            $statusDetail   = "Es befindet sich kein anderer Film vor dir. Die Verarbeitung beginnt automatisch im nächsten Durchlauf (Ø ca. {$avgSecPerFilmMinute} Sek. pro Filmminute).";
        } elseif ($moviesAhead === 1) {
            $statusHeadline = 'Warteschlange Platz #2 (noch 1 Film vor deinem Video)';
            $statusDetail   = "Vor deinem Film wird aktuell noch 1 anderer Film konvertiert. Danach startet dein Video sofort automatisch (Ø ca. {$avgSecPerFilmMinute} Sek. pro Filmminute).";
        } else {
            $statusHeadline = "Warteschlange Platz #{$queuePosition} (noch {$moviesAhead} Filme vor deinem Video)";
            $statusDetail   = "Vor deinem Film befinden sich noch {$moviesAhead} weitere Filme in der Verarbeitung. Dein Video startet danach automatisch (Ø ca. {$avgSecPerFilmMinute} Sek. pro Filmminute).";
        }

        return [
            'state'                   => $convStatus === 1 ? 'converting' : 'queued',
            'movies_ahead'            => $moviesAhead,
            'queue_position'          => $queuePosition,
            'elapsed_seconds'         => $ownElapsed,
            'remaining_seconds'       => $totalRemainingSeconds,
            'remaining_formatted'     => $remainingFormatted,
            'progress_percent'        => $progressPercent,
            'avg_sec_per_film_minute' => $avgSecPerFilmMinute,
            'status_headline'         => $statusHeadline,
            'status_detail'           => $statusDetail,
        ];
    }

    private static function formatDurationEstimate(int $seconds): string
    {
        if ($seconds <= 45) {
            return 'weniger als 1 Minute';
        }
        if ($seconds <= 95) {
            return 'ca. 1 bis 2 Minuten';
        }
        $minutes = (int)ceil($seconds / 60);
        if ($minutes < 60) {
            return 'ca. ' . $minutes . ' Minuten';
        }
        $hours   = (int)floor($minutes / 60);
        $remMins = $minutes % 60;
        if ($remMins === 0) {
            return 'ca. ' . $hours . ' ' . ($hours === 1 ? 'Stunde' : 'Stunden');
        }
        return 'ca. ' . $hours . ' Std. ' . $remMins . ' Min.';
    }

    /**
     * Lädt alle Film-Kategorien gruppiert inkl. System-Auto-Erkennung (Gelb-Hervorhebung).
     */
    private function loadCategoriesWithAutoDetection(int $actorId, string $title, string $description, string $savedSlavesCsv): array
    {
        $savedSlaves = $savedSlavesCsv !== '' ? array_map('trim', explode(',', $savedSlavesCsv)) : [];

        $actorCategories = [];
        if ($actorId > 0) {
            $rsActorCat = p4c_query("SELECT `actor_categories` FROM `actors` WHERE `id` = '" . $actorId . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsActorCat && p4c_num_rows($rsActorCat) > 0) {
                $aRow = p4c_fetch_object($rsActorCat);
                if (!empty($aRow->actor_categories)) {
                    $actorCategories = array_map('trim', explode(',', (string)$aRow->actor_categories));
                }
            }
        }

        $searchText = mb_strtolower(strip_tags($title . ' ' . $description), 'UTF-8');

        $groupDefs = [
            ['id' => 'cat_people', 'title' => 'Anzahl der Personen & sexuelle Orientierung', 'group' => 'number_of_people'],
            ['id' => 'cat_body',   'title' => 'Körper und Aussehen',                         'group' => 'look_and_body'],
            ['id' => 'cat_fetish', 'title' => 'Fetisch',                                     'group' => 'fetish'],
            ['id' => 'cat_other',  'title' => 'Sonstige',                                    'group' => 'porn'],
        ];

        $result = [];
        foreach ($groupDefs as $gDef) {
            $rsCats = p4c_query("SELECT * FROM `movie_categories` WHERE `category_group` = '" . p4c_escape_string($gDef['group']) . "';", __FILE__, __LINE__);
            $items         = [];
            $selectedCount = 0;

            if ($rsCats && p4c_num_rows($rsCats) > 0) {
                while ($cObj = p4c_fetch_object($rsCats)) {
                    $nameId   = (string)$cObj->name_id;
                    $deVal    = self::decodeLegacyText((string)$cObj->de_name_value);
                    $deText   = self::decodeLegacyText((string)($cObj->de_name_text ?? ''));
                    $moreWords = self::decodeLegacyText((string)($cObj->more_search_words ?? ''));

                    $isSaved = in_array($nameId, $savedSlaves, true);
                    $isSystemDetected = false;

                    if ($searchText !== '') {
                        if (mb_strpos($searchText, mb_strtolower($nameId, 'UTF-8')) !== false
                            || mb_strpos($searchText, mb_strtolower($deVal, 'UTF-8')) !== false
                        ) {
                            $isSystemDetected = true;
                        } elseif ($moreWords !== '') {
                            foreach (explode(',', mb_strtolower($moreWords, 'UTF-8')) as $w) {
                                $w = trim($w);
                                if ($w !== '' && mb_strpos($searchText, $w) !== false) {
                                    $isSystemDetected = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$isSystemDetected && !empty($actorCategories) && in_array($nameId, $actorCategories, true)) {
                        $isSystemDetected = true;
                    }

                    $isChecked = ($isSaved || $isSystemDetected);
                    if ($isChecked) {
                        $selectedCount++;
                    }

                    $items[] = [
                        'name_id'            => $nameId,
                        'label'              => $deVal,
                        'hint'               => $deText,
                        'is_checked'         => $isChecked,
                        'is_system_detected' => $isSystemDetected,
                    ];
                }
            }

            $result[] = [
                'id'             => $gDef['id'],
                'title'          => $gDef['title'],
                'selected_count' => $selectedCount,
                'items'          => $items,
            ];
        }

        return $result;
    }

    /**
     * Lädt alle aktiven Darsteller-Profile des Partners für das Auswahl-Dropdown.
     */
    private function loadActiveMerchantActors(int $merchantId, int $currentActorId = 0): array
    {
        $actors = [];
        $rsActors = p4c_query("SELECT `id`, `username`, `status`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND (`status` = 'active' OR `id` = '" . $currentActorId . "')
              AND `status` != 'deleted'
            ORDER BY `username` ASC;", __FILE__, __LINE__);

        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($aObj = p4c_fetch_object($rsActors)) {
                $actors[] = [
                    'id'       => abs((int)$aObj->id),
                    'username' => (string)$aObj->username,
                ];
            }
        }
        return $actors;
    }

    /**
     * Lädt alle freigeschalteten Websites des Partners inkl. Whitelist-Prüfung für 'public'.
     */
    private function loadMerchantWebsites(\Merchant $merchant, string $currentValue = ''): array
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $websites   = [];

        if ($this->isPublicUploadAllowed($merchant, $merchantId, $currentValue)) {
            $websites[] = [
                'value' => 'public',
                'label' => 'alle Partnerwebsites',
            ];
        }

        $rsSites = p4c_query("SELECT `domain`
            FROM `sites`
            WHERE `partner_id` = '" . p4c_escape_string((string)$merchant->partner_id()) . "'
              AND `status` = '1'
            ORDER BY `domain` ASC;", __FILE__, __LINE__);

        if ($rsSites && p4c_num_rows($rsSites) > 0) {
            while ($sObj = p4c_fetch_object($rsSites)) {
                $websites[] = [
                    'value' => (string)$sObj->domain,
                    'label' => (string)$sObj->domain,
                ];
            }
        }

        return $websites;
    }

    private function isPublicUploadAllowed(\Merchant $merchant, int $merchantId, string $currentValue = ''): bool
    {
        $allowedPartners  = ['CCRVWMVD67'];
        $allowedMerchants = [10061];

        return in_array((string)$merchant->partner_id(), $allowedPartners, true)
            || in_array($merchantId, $allowedMerchants, true)
            || $currentValue === 'public';
    }

    private function cleanMovieTitle(string $rawTitle): string
    {
        $title = trim(str_replace(self::REPLACE_TITLE_CHARS, '', $rawTitle));
        $title = str_replace(self::ZERO_WIDTH_SPACES, '', $title);
        $title = str_replace(['#?', '?en'], ['#', 'en'], $title);
        $title = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;'], 'EUR', $title);
        return trim($title);
    }

    private function hasValidCsrf(): bool
    {
        $token = $this->request->header('X-CSRF-Token');
        if ($token === '') {
            $token = (string)$this->request->post('csrf_token', '');
        }
        if ($token === '') {
            $token = (string)$this->request->post('_csrf', '');
        }
        if ($token === '') {
            $token = (string)$this->request->get('_csrf', '');
        }
        return Csrf::validate($token);
    }

    private static function decodeLegacyText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = (string)preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $decoded);
        $decoded = (string)preg_replace('/<\/\s*p\s*>\s*<\s*p[^>]*>/i', "\n\n", $decoded);
        $decoded = (string)preg_replace('/<\/?\s*p[^>]*>/i', '', $decoded);
        $decoded = str_replace(["\r\n", "\r"], "\n", $decoded);
        $decoded = (string)preg_replace("/\n{3,}/", "\n\n", $decoded);
        return trim($decoded);
    }
}
