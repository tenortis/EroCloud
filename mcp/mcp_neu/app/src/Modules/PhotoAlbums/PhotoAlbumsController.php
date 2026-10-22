<?php
declare(strict_types=1);

namespace App\Modules\PhotoAlbums;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Controller für die Foto-Alben-Verwaltung im neuen EroCloud MCP:
 * - #/photo-albums        (Übersicht aller Foto-Alben inkl. Status & Schnellaktionen, ohne Umsatzzahlen)
 * - #/photo-album         (Einzelnes Foto-Album bearbeiten, Cover & Fotos verwalten)
 * - #/photo-album-upload  (Neues Foto-Album in 3 Schritten anlegen & Bilder hochladen)
 */
final class PhotoAlbumsController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * GET: Übersicht aller Foto-Alben des eingeloggten Partners (#/photo-albums)
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

        // 1. Darsteller-Namen des Partners für schnelle Zuordnung vorladen
        $actorsMap = [];
        $rsActors  = p4c_query("SELECT `id`, `username`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "'", __FILE__, __LINE__);
        if ($rsActors) {
            while ($act = p4c_fetch_object($rsActors)) {
                $actorsMap[abs((int)$act->id)] = self::decodeLegacyText((string)$act->username);
            }
        }

        // 2. Online-Status aller Foto-Alben dieses Partners in einem Set vorladen
        $onlineAlbumHashes = [];
        $rsOnline          = p4c_query("SELECT `album_id`
            FROM `photo_albums_online`
            WHERE `merchant_id` = '" . $merchantId . "'", __FILE__, __LINE__);
        if ($rsOnline) {
            while ($on = p4c_fetch_object($rsOnline)) {
                $onlineAlbumHashes[(string)$on->album_id] = true;
            }
        }

        // 3. Alle nicht gelöschten Foto-Alben des Partners laden (ohne Umsatz-/Verkaufszahlen)
        $albums        = [];
        $countOnline   = 0;
        $countPending  = 0;
        $countRejected = 0;

        $rsAlbums = p4c_query("SELECT
                `id`, `album_id`, `actor_id`, `checksum`, `number_of_photos`,
                `preview_image_fsk16`, `preview_image_fsk18`, `album_checked`,
                `released`, `online_at`, `amount_download`, `title`, `status`
            FROM `photo_albums`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            ORDER BY `id` DESC;", __FILE__, __LINE__);

        if ($rsAlbums && p4c_num_rows($rsAlbums) > 0) {
            while ($a = p4c_fetch_object($rsAlbums)) {
                $albumDbId   = abs((int)$a->id);
                $albumIdHash = (string)$a->album_id;
                $actorId     = abs((int)$a->actor_id);
                $checksum    = (string)($a->checksum ?? '');
                $numPhotos   = abs((int)$a->number_of_photos);
                $hasFsk16    = trim((string)$a->preview_image_fsk16) !== '';
                $hasFsk18    = trim((string)$a->preview_image_fsk18) !== '';
                $isComplete  = ($numPhotos >= 4 && $hasFsk16 && $hasFsk18);
                $isOnline    = isset($onlineAlbumHashes[$albumIdHash]);
                $albumStatus = (string)($a->status ?? 'active');

                // Status-Logik analog zu legacy ajax/photo_albums.php
                $statusKey   = 'pending';
                $statusLabel = 'Wartet auf Freigabe';
                $statusBadge = 'warning';
                $statusIcon  = 'bi-hourglass-split';
                $editRoute   = '#/photo-album?id=' . $albumDbId;

                if ((int)$a->released === 2) {
                    $statusKey   = 'rejected';
                    $statusLabel = 'Korrektur erforderlich';
                    $statusBadge = 'danger';
                    $statusIcon  = 'bi-exclamation-triangle-fill';
                    if (!$isComplete) {
                        $editRoute = '#/photo-album-upload?step=2&album_id=' . $albumDbId;
                    }
                    $countRejected++;
                } elseif ($albumStatus === 'blocked') {
                    $statusKey   = 'blocked';
                    $statusLabel = 'Vom Support gesperrt';
                    $statusBadge = 'danger';
                    $statusIcon  = 'bi-slash-circle';
                    $countRejected++;
                } elseif (!$isComplete) {
                    $statusKey   = 'incomplete';
                    $statusLabel = 'Fotos fehlen (mind. 4 + Cover)';
                    $statusBadge = 'secondary';
                    $statusIcon  = 'bi-cloud-arrow-up';
                    $editRoute   = '#/photo-album-upload?step=2&album_id=' . $albumDbId;
                    $countPending++;
                } elseif ($albumStatus === 'inactive') {
                    $statusKey   = 'pending';
                    $statusLabel = 'Pausiert (Darsteller inaktiv)';
                    $statusBadge = 'secondary';
                    $statusIcon  = 'bi-pause-circle';
                    $countPending++;
                } elseif ((string)$a->album_checked === '0000-00-00 00:00:00' && (int)$a->released === 0) {
                    $statusKey   = 'pending';
                    $statusLabel = 'Entwurf (nicht eingereicht)';
                    $statusBadge = 'secondary';
                    $statusIcon  = 'bi-pencil-square';
                    $countPending++;
                } elseif ((string)$a->album_checked === '0000-00-00 00:00:00' && (int)$a->released === 1) {
                    $statusKey   = 'pending';
                    $statusLabel = 'In Prüfung';
                    $statusBadge = 'warning';
                    $statusIcon  = 'bi-hourglass-split';
                    $countPending++;
                } elseif ((string)$a->album_checked !== '0000-00-00 00:00:00' && (int)$a->released === 0) {
                    $statusKey   = 'blocked';
                    $statusLabel = 'Gesperrt / Abgelehnt';
                    $statusBadge = 'danger';
                    $statusIcon  = 'bi-slash-circle';
                    $countRejected++;
                } elseif ((string)$a->album_checked !== '0000-00-00 00:00:00' && (int)$a->released === 1 && !$isOnline) {
                    $statusKey   = 'scheduled';
                    $statusLabel = 'Geplant für ' . date('d.m.Y H:i', strtotime((string)$a->online_at));
                    $statusBadge = 'info';
                    $statusIcon  = 'bi-calendar-event';
                    $countPending++;
                } elseif ((string)$a->album_checked !== '0000-00-00 00:00:00' && (int)$a->released === 1 && $isOnline) {
                    $statusKey   = 'online';
                    $statusLabel = 'Online & aktiv';
                    $statusBadge = 'success';
                    $statusIcon  = 'bi-check-circle-fill';
                    $countOnline++;
                }

                $onlineAtTs = ($a->online_at !== '0000-00-00 00:00:00' && !empty($a->online_at))
                    ? (int)strtotime((string)$a->online_at)
                    : 0;
                $onlineAtFormatted = $onlineAtTs > 0
                    ? date('d.m.Y H:i', $onlineAtTs)
                    : '-';

                $priceEur = ((int)$a->amount_download) / 100.0;
                $cs16 = $checksum . '_' . trim((string)$a->preview_image_fsk16);
                $cs18 = $checksum . '_' . trim((string)$a->preview_image_fsk18);

                $albums[] = [
                    'id'               => $albumDbId,
                    'album_id'         => $albumIdHash,
                    'title'            => self::decodeLegacyText(trim((string)$a->title) !== '' ? (string)$a->title : 'Unbenanntes Foto-Album #' . $albumDbId),
                    'actor_id'         => $actorId,
                    'actor_name'       => $actorsMap[$actorId] ?? '-',
                    'number_of_photos' => $numPhotos,
                    'price_eur'        => $priceEur,
                    'status_key'       => $statusKey,
                    'status_label'     => $statusLabel,
                    'status_badge'     => $statusBadge,
                    'status_icon'      => $statusIcon,
                    'edit_route'       => $editRoute,
                    'poster_fsk16'     => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=180&cs=' . rawurlencode($cs16),
                    'poster_fsk18'     => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=180&cs=' . rawurlencode($cs18),
                    'zoom_fsk16'       => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($cs16),
                    'zoom_fsk18'       => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($cs18),
                    'online_at'        => $onlineAtFormatted,
                    'online_at_ts'     => $onlineAtTs,
                    'can_delete'       => ((string)$a->album_checked === '0000-00-00 00:00:00' && (int)$a->released !== 1 && !$isOnline),
                ];
            }
        }

        return View::render('PhotoAlbums', 'list', [
            'albums'        => $albums,
            'actorsMap'     => $actorsMap,
            'countTotal'    => count($albums),
            'countOnline'   => $countOnline,
            'countPending'  => $countPending,
            'countRejected' => $countRejected,
        ]);
    }

    /**
     * GET: Einzelnes Foto-Album bearbeiten (#/photo-album?id=...)
     */
    public function detail(): string
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }
        @session_write_close();

        $albumDbId = abs((int)$this->request->get('id', 0));
        if ($albumDbId === 0) {
            return $this->list();
        }

        $rsAlbum = p4c_query("SELECT *
            FROM `photo_albums`
            WHERE `id` = '" . $albumDbId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            return '
            <div class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 m-4">
              <div>
                <i class="bi bi-exclamation-octagon-fill me-2"></i>
                Das angeforderte Foto-Album wurde nicht gefunden oder wurde bereits gelöscht.
              </div>
              <a href="#/photo-albums" class="btn btn-sm btn-outline-kkc">Zur Foto-Alben-Übersicht</a>
            </div>';
        }

        $aObj        = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$aObj->album_id;
        $actorId     = abs((int)$aObj->actor_id);
        $checksum    = (string)($aObj->checksum ?? '');
        $apiUrl      = defined('API_URL') ? API_URL : 'https://api.erocloud.net';
        $mcpUrl      = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';

        // Prüfen, ob das Album in photo_albums_online steht
        $rsOnline      = p4c_query("SELECT `id` FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
        $isAlbumOnline = ($rsOnline && p4c_num_rows($rsOnline) > 0);

        // Ablehnungsgrund laden, falls released == 2 oder status == 'blocked'
        $rejectionInfo = $this->loadAlbumRejectionInfo($albumIdHash, (int)$aObj->released, (string)($aObj->status ?? 'active'));

        // Alle Fotos des Albums laden
        $photos        = [];
        $rejectedCount = 0;
        $rsPhotos      = p4c_query("SELECT *
            FROM `photo_albums_photos`
            WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
              AND `merchant_id` = '" . $merchantId . "'
            ORDER BY `id` ASC;", __FILE__, __LINE__);

        if ($rsPhotos) {
            $photoIdx = 0;
            while ($p = p4c_fetch_object($rsPhotos)) {
                $photoIdx++;
                $pFileId    = (string)$p->file_id;
                $isRejected = abs((int)($p->rejected ?? 0));
                if ($isRejected === 1) {
                    $rejectedCount++;
                }
                $photos[] = [
                    'id'         => abs((int)$p->id),
                    'index'      => $photoIdx,
                    'num'        => 'photo_' . $pFileId,
                    'label'      => 'Foto #' . $photoIdx,
                    'file_id'    => $pFileId,
                    'filename'   => (string)$p->filename,
                    'rejected'   => $isRejected,
                    'thumb_url'  => $mcpUrl . '/AlbumPhoto/' . rawurlencode($pFileId) . '?w=200',
                    'zoom_url'   => $mcpUrl . '/AlbumPhoto/' . rawurlencode($pFileId) . '?w=900',
                    'editor_url' => $mcpUrl . '/AlbumPhoto/' . rawurlencode($pFileId) . '?w=1024',
                ];
            }
        }

        if ($rejectedCount > 0 && $rejectionInfo === null) {
            $rejectionInfo = [
                'reason' => 'Einzelne Fotos im Album wurden abgelehnt',
                'text'   => 'Mindestens ein Foto in diesem Album entspricht nicht den Inhaltsrichtlinien und wurde rot markiert. Bitte lösche die rot markierten Fotos und reiche das Album erneut zur Prüfung ein.',
            ];
        }

        $onlineAt = ($aObj->online_at !== '0000-00-00 00:00:00' && !empty($aObj->online_at))
            ? date('Y-m-d\TH:i', strtotime((string)$aObj->online_at))
            : date('Y-m-d\TH:i', strtotime('+90 minutes'));

        $actors = $this->loadActiveMerchantActors($merchantId, $actorId);
        $nowTs  = time();

        $albumData = [
            'id'                      => $albumDbId,
            'album_id'                => $albumIdHash,
            'title'                   => self::decodeLegacyText((string)$aObj->title),
            'description'             => self::decodeLegacyText((string)$aObj->description),
            'actor_id'                => $actorId,
            'online_at'               => $onlineAt,
            'online_at_display'       => ($aObj->online_at !== '0000-00-00 00:00:00' && !empty($aObj->online_at))
                ? date('d.m.Y H:i', strtotime((string)$aObj->online_at))
                : '-',
            'can_edit_online_at'      => (((string)$aObj->album_checked === '0000-00-00 00:00:00' && !$isAlbumOnline) || strtotime((string)$aObj->online_at) > time()),
            'amount_download'         => abs((int)$aObj->amount_download), // in Cent (0, 100, 200 ... 5000)
            'number_of_photos'        => count($photos),
            'has_fsk16'               => trim((string)$aObj->preview_image_fsk16) !== '',
            'has_fsk18'               => trim((string)$aObj->preview_image_fsk18) !== '',
            'poster_fsk16_url'        => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=240&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
            'poster_fsk16_zoom_url'   => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
            'poster_fsk16_editor_url' => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=1024&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
            'poster_fsk18_url'        => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=240&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
            'poster_fsk18_zoom_url'   => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
            'poster_fsk18_editor_url' => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=1024&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
            'album_checked'           => (string)$aObj->album_checked,
            'released'                => (int)$aObj->released,
            'status'                  => (string)($aObj->status ?? 'active'),
            'is_online'               => $isAlbumOnline,
            'can_delete'              => ((string)$aObj->album_checked === '0000-00-00 00:00:00' && (int)$aObj->released !== 1 && !$isAlbumOnline),
            'meta_title'              => self::decodeLegacyText((string)$aObj->meta_title),
            'meta_description'        => self::decodeLegacyText((string)$aObj->meta_description),
            'seo_url'                 => (string)$aObj->seo_url,
        ];

        return View::render('PhotoAlbums', 'detail', [
            'album'         => $albumData,
            'photos'        => $photos,
            'actors'        => $actors,
            'rejectionInfo' => $rejectionInfo,
        ]);
    }

    /**
     * GET: Neues Foto-Album in 3 Schritten anlegen (#/photo-album-upload)
     */
    public function upload(): string
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }
        @session_write_close();

        $step      = abs((int)$this->request->get('step', 1));
        $albumDbId = abs((int)$this->request->get('album_id', 0));
        if (!in_array($step, [1, 2, 3], true)) {
            $step = 1;
        }

        if ($albumDbId === 0 && isset($_SESSION['upload_photo_album']['album_id'])) {
            $albumDbId = abs((int)$_SESSION['upload_photo_album']['album_id']);
        }

        $apiUrl = defined('API_URL') ? API_URL : 'https://api.erocloud.net';
        $mcpUrl = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';

        $draft = [
            'id'                      => 0,
            'album_id'                => '',
            'title'                   => '',
            'description'             => '',
            'actor_id'                => 0,
            'online_at'               => date('Y-m-d\TH:i', strtotime('+90 minutes')),
            'amount_download'         => 500, // Standard: 5,00 EUR (500 Cent)
            'has_fsk16'               => false,
            'has_fsk18'               => false,
            'poster_fsk16_url'        => '',
            'poster_fsk16_zoom_url'   => '',
            'poster_fsk16_editor_url' => '',
            'poster_fsk18_url'        => '',
            'poster_fsk18_zoom_url'   => '',
            'poster_fsk18_editor_url' => '',
            'meta_title'              => '',
            'meta_description'        => '',
            'seo_url'                 => '',
        ];
        $photos        = [];
        $rejectionInfo = null;

        if ($albumDbId > 0) {
            $rsDraft = p4c_query("SELECT *
                FROM `photo_albums`
                WHERE `id` = '" . $albumDbId . "'
                  AND `merchant_id` = '" . $merchantId . "'
                  AND `status` != 'deleted'
                LIMIT 1;", __FILE__, __LINE__);

            if ($rsDraft && p4c_num_rows($rsDraft) > 0) {
                $dObj        = p4c_fetch_object($rsDraft);
                $albumIdHash = (string)$dObj->album_id;
                $checksum    = (string)($dObj->checksum ?? '');
                $nowTs       = time();

                $rejectionInfo = $this->loadAlbumRejectionInfo($albumIdHash, (int)$dObj->released, (string)($dObj->status ?? 'active'));

                $draft = [
                    'id'                      => abs((int)$dObj->id),
                    'album_id'                => $albumIdHash,
                    'title'                   => self::decodeLegacyText((string)$dObj->title),
                    'description'             => self::decodeLegacyText((string)$dObj->description),
                    'actor_id'                => abs((int)$dObj->actor_id),
                    'online_at'               => ($dObj->online_at !== '0000-00-00 00:00:00' && !empty($dObj->online_at))
                        ? date('Y-m-d\TH:i', strtotime((string)$dObj->online_at))
                        : date('Y-m-d\TH:i', strtotime('+90 minutes')),
                    'amount_download'         => abs((int)$dObj->amount_download),
                    'has_fsk16'               => trim((string)$dObj->preview_image_fsk16) !== '',
                    'has_fsk18'               => trim((string)$dObj->preview_image_fsk18) !== '',
                    'poster_fsk16_url'        => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=240&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
                    'poster_fsk16_zoom_url'   => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
                    'poster_fsk16_editor_url' => $apiUrl . '/PhotoAlbumPoster/FSK16/' . rawurlencode($albumIdHash) . '?w=1024&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
                    'poster_fsk18_url'        => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=240&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
                    'poster_fsk18_zoom_url'   => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
                    'poster_fsk18_editor_url' => $apiUrl . '/PhotoAlbumPoster/FSK18/' . rawurlencode($albumIdHash) . '?w=1024&cs=' . rawurlencode($checksum) . '&t=' . $nowTs,
                    'meta_title'              => self::decodeLegacyText((string)$dObj->meta_title),
                    'meta_description'        => self::decodeLegacyText((string)$dObj->meta_description),
                    'seo_url'                 => (string)$dObj->seo_url,
                ];

                $rejectedCount = 0;
                $rsPhotos      = p4c_query("SELECT *
                    FROM `photo_albums_photos`
                    WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
                      AND `merchant_id` = '" . $merchantId . "'
                    ORDER BY `id` ASC;", __FILE__, __LINE__);

                if ($rsPhotos) {
                    $photoIdx = 0;
                    while ($p = p4c_fetch_object($rsPhotos)) {
                        $photoIdx++;
                        $pFileId    = (string)$p->file_id;
                        $isRejected = abs((int)($p->rejected ?? 0));
                        if ($isRejected === 1) {
                            $rejectedCount++;
                        }
                        $photos[] = [
                            'id'         => abs((int)$p->id),
                            'index'      => $photoIdx,
                            'num'        => 'photo_' . $pFileId,
                            'label'      => 'Foto #' . $photoIdx,
                            'file_id'    => $pFileId,
                            'filename'   => (string)$p->filename,
                            'rejected'   => $isRejected,
                            'thumb_url'  => $mcpUrl . '/AlbumPhoto/' . rawurlencode($pFileId) . '?w=200',
                            'zoom_url'   => $mcpUrl . '/AlbumPhoto/' . rawurlencode($pFileId) . '?w=900',
                            'editor_url' => $mcpUrl . '/AlbumPhoto/' . rawurlencode($pFileId) . '?w=1024',
                        ];
                    }
                }

                if ($rejectedCount > 0 && $rejectionInfo === null) {
                    $rejectionInfo = [
                        'reason' => 'Einzelne Fotos im Album wurden abgelehnt',
                        'text'   => 'Mindestens ein Foto in diesem Album entspricht nicht den Inhaltsrichtlinien und wurde rot markiert. Bitte lösche die rot markierten Fotos und lade bei Bedarf neue Fotos hoch.',
                    ];
                }
            } elseif ($step === 2) {
                $step      = 1;
                $albumDbId = 0;
            }
        }

        if ($step === 3) {
            unset($_SESSION['upload_photo_album']);
        }

        $actors = $this->loadActiveMerchantActors($merchantId, (int)$draft['actor_id']);
        if ((int)$draft['actor_id'] === 0 && count($actors) === 1) {
            $draft['actor_id'] = (int)$actors[0]['id'];
        }

        return View::render('PhotoAlbums', 'upload', [
            'step'          => $step,
            'album'         => $draft,
            'photos'        => $photos,
            'actors'        => $actors,
            'rejectionInfo' => $rejectionInfo,
        ]);
    }

    /**
     * POST: Speichert Schritt 1 beim Anlegen eines neuen Foto-Albums (photo-albums/save-step1)
     */
    public function saveStep1(): void
    {
        global $mysql;

        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $existingId      = abs((int)$this->request->post('album_id', 0));
        $title           = self::cleanAlbumTitle(trim((string)$this->request->post('title', '')));
        $description     = trim((string)$this->request->post('description', ''));
        $actorId         = abs((int)$this->request->post('actor_id', 0));
        $onlineAtRaw     = trim((string)$this->request->post('online_at', ''));
        $amountDownload  = abs((int)$this->request->post('amount_download', 500));
        $metaTitle       = trim((string)$this->request->post('meta_title', ''));
        $metaDescription = trim((string)$this->request->post('meta_description', ''));
        $seoUrl          = trim((string)$this->request->post('seo_url', ''));

        if ($existingId > 0) {
            $rsCheck = p4c_query("SELECT `id` FROM `photo_albums` WHERE `id` = '" . $existingId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
            if (!$rsCheck || p4c_num_rows($rsCheck) === 0) {
                $existingId = 0;
            }
        }

        $this->validateAlbumInput(
            $merchantId,
            $title,
            $description,
            $actorId,
            $onlineAtRaw,
            true,
            $amountDownload,
            $metaTitle,
            $metaDescription,
            $seoUrl,
            $existingId
        );

        $onlineAtSql = date('Y-m-d H:i:s', strtotime($onlineAtRaw));
        $storageDir  = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';

        if ($metaTitle === '') {
            $metaTitle = function_exists('mb_substr') ? mb_substr($title, 0, 65, 'UTF-8') : substr($title, 0, 65);
        } else {
            $metaTitle = function_exists('mb_substr') ? mb_substr($metaTitle, 0, 65, 'UTF-8') : substr($metaTitle, 0, 65);
        }
        if ($metaDescription === '') {
            $cleanDesc = trim(strip_tags($description));
            $metaDescription = function_exists('mb_substr') ? mb_substr($cleanDesc, 0, 156, 'UTF-8') : substr($cleanDesc, 0, 156);
        } else {
            $metaDescription = function_exists('mb_substr') ? mb_substr($metaDescription, 0, 156, 'UTF-8') : substr($metaDescription, 0, 156);
        }
        if ($seoUrl === '' && function_exists('seo_url')) {
            $seoUrl = (string)seo_url($title);
        }

        if ($existingId === 0) {
            $albumIdHash = strtolower(md5($merchantId . '_' . microtime(true)));
            p4c_query("INSERT INTO `photo_albums` SET
                `merchant_id`         = '" . $merchantId . "',
                `actor_id`            = '" . $actorId . "',
                `album_id`            = '" . p4c_escape_string($albumIdHash) . "',
                `create_datetime`     = '" . date('Y-m-d H:i:s') . "',
                `storage_location`    = '" . p4c_escape_string($storageDir) . "',
                `number_of_photos`    = '0',
                `preview_image_fsk16` = '',
                `preview_image_fsk18` = '',
                `title`               = '" . p4c_escape_string($title) . "',
                `description`         = '" . p4c_escape_string($description) . "',
                `meta_title`          = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`    = '" . p4c_escape_string($metaDescription) . "',
                `seo_url`             = '" . p4c_escape_string($seoUrl) . "',
                `online_at`           = '" . p4c_escape_string($onlineAtSql) . "',
                `amount_webmaster`    = '10',
                `amount_download`     = '" . $amountDownload . "',
                `album_checked`       = '0000-00-00 00:00:00',
                `released`            = '0',
                `admin_infos`         = '';", __FILE__, __LINE__);

            $existingId = abs((int)p4c_insert_id());
        } else {
            p4c_query("UPDATE `photo_albums` SET
                `actor_id`         = '" . $actorId . "',
                `title`            = '" . p4c_escape_string($title) . "',
                `description`      = '" . p4c_escape_string($description) . "',
                `meta_title`       = '" . p4c_escape_string($metaTitle) . "',
                `meta_description` = '" . p4c_escape_string($metaDescription) . "',
                `seo_url`          = '" . p4c_escape_string($seoUrl) . "',
                `online_at`        = '" . p4c_escape_string($onlineAtSql) . "',
                `amount_download`  = '" . $amountDownload . "'
                WHERE `id` = '" . $existingId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        }

        if (function_exists('photo_album_checksum') && class_exists('PhotoAlbum')) {
            $aObj     = new \PhotoAlbum($mysql, $existingId);
            $checksum = photo_album_checksum($aObj);
            p4c_query("UPDATE `photo_albums` SET `checksum` = '" . p4c_escape_string((string)$checksum) . "' WHERE `id` = '" . $existingId . "';", __FILE__, __LINE__);
        }

        $_SESSION['upload_photo_album']['album_id'] = $existingId;

        if (function_exists('log_action')) {
            log_action('Foto-Album Upload Schritt 1 gespeichert (Album-ID: ' . $existingId . ')');
        }

        Response::json([
            'status'   => 'ok',
            'message'  => 'Die Basisdaten wurden gespeichert. Bitte lade nun die Coverbilder und Fotos hoch.',
            'album_id' => $existingId,
            'redirect' => '#/photo-album-upload?step=2&album_id=' . $existingId,
        ]);
    }

    /**
     * POST: Aktualisiert ein bestehendes Foto-Album (photo-albums/update)
     */
    public function update(): void
    {
        global $mysql;

        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $albumDbId = abs((int)$this->request->post('id', 0));
        if ($albumDbId === 0) {
            Response::error('Ungültige Foto-Album-ID.', 400);
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das angeforderte Foto-Album wurde nicht gefunden.', 404);
        }

        $aObj        = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$aObj->album_id;

        $rsOnline      = p4c_query("SELECT `id` FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
        $isAlbumOnline = ($rsOnline && p4c_num_rows($rsOnline) > 0);
        $canEditOnline = (((string)$aObj->album_checked === '0000-00-00 00:00:00' && !$isAlbumOnline) || strtotime((string)$aObj->online_at) > time());

        $title           = self::cleanAlbumTitle(trim((string)$this->request->post('title', '')));
        $description     = trim((string)$this->request->post('description', ''));
        $actorId         = abs((int)$this->request->post('actor_id', 0));
        $onlineAtRaw     = trim((string)$this->request->post('online_at', ''));
        $amountDownload  = abs((int)$this->request->post('amount_download', 500));
        $metaTitle       = trim((string)$this->request->post('meta_title', ''));
        $metaDescription = trim((string)$this->request->post('meta_description', ''));
        $seoUrl          = trim((string)$this->request->post('seo_url', ''));

        $this->validateAlbumInput(
            $merchantId,
            $title,
            $description,
            $actorId,
            $onlineAtRaw,
            $canEditOnline,
            $amountDownload,
            $metaTitle,
            $metaDescription,
            $seoUrl,
            $albumDbId
        );

        $onlineAtSql = $canEditOnline && $onlineAtRaw !== ''
            ? date('Y-m-d H:i:s', strtotime($onlineAtRaw))
            : (string)$aObj->online_at;

        if ($metaTitle === '') {
            $metaTitle = function_exists('mb_substr') ? mb_substr($title, 0, 65, 'UTF-8') : substr($title, 0, 65);
        } else {
            $metaTitle = function_exists('mb_substr') ? mb_substr($metaTitle, 0, 65, 'UTF-8') : substr($metaTitle, 0, 65);
        }
        if ($metaDescription === '') {
            $cleanDesc = trim(strip_tags($description));
            $metaDescription = function_exists('mb_substr') ? mb_substr($cleanDesc, 0, 156, 'UTF-8') : substr($cleanDesc, 0, 156);
        } else {
            $metaDescription = function_exists('mb_substr') ? mb_substr($metaDescription, 0, 156, 'UTF-8') : substr($metaDescription, 0, 156);
        }
        if ($seoUrl === '' && function_exists('seo_url')) {
            $seoUrl = (string)seo_url($title);
        }

        // Nur dann auf released = 1 (In Prüfung) setzen, wenn mind. 4 Fotos sowie FSK16 & FSK18 Cover vorhanden sind
        $totalPhotos = $this->recalculatePhotoCount($albumDbId, $albumIdHash, $merchantId, $mysql);
        $hasFsk16    = trim((string)$aObj->preview_image_fsk16) !== '';
        $hasFsk18    = trim((string)$aObj->preview_image_fsk18) !== '';
        $newReleased = ($totalPhotos >= 4 && $hasFsk16 && $hasFsk18) ? '1' : '0';

        p4c_query("UPDATE `photo_albums` SET
            `actor_id`         = '" . $actorId . "',
            `title`            = '" . p4c_escape_string($title) . "',
            `description`      = '" . p4c_escape_string($description) . "',
            `meta_title`       = '" . p4c_escape_string($metaTitle) . "',
            `meta_description` = '" . p4c_escape_string($metaDescription) . "',
            `seo_url`          = '" . p4c_escape_string($seoUrl) . "',
            `online_at`        = '" . p4c_escape_string($onlineAtSql) . "',
            `amount_download`  = '" . $amountDownload . "',
            `album_checked`    = '0000-00-00 00:00:00',
            `released`         = '" . $newReleased . "'
            WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        if (function_exists('photo_album_checksum') && class_exists('PhotoAlbum')) {
            $aClass   = new \PhotoAlbum($mysql, $albumDbId);
            $checksum = photo_album_checksum($aClass);
            p4c_query("UPDATE `photo_albums` SET `checksum` = '" . p4c_escape_string((string)$checksum) . "' WHERE `id` = '" . $albumDbId . "';", __FILE__, __LINE__);
        }

        if (function_exists('log_action')) {
            log_action('Foto-Album #' . $albumDbId . ' aktualisiert');
        }

        Response::json([
            'status'  => 'ok',
            'message' => 'Deine Änderungen am Foto-Album wurden erfolgreich gespeichert.',
        ]);
    }

    /**
     * POST: Lädt ein FSK16- oder FSK18-Coverbild für das Foto-Album hoch (photo-albums/upload-cover)
     */
    public function uploadCover(): void
    {
        global $mysql;

        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $albumDbId = abs((int)$this->request->post('album_id', 0));
        $fsk       = trim((string)$this->request->post('fsk', ''));

        if ($albumDbId === 0 || !in_array($fsk, ['16', '18'], true)) {
            Response::error('Ungültige Parameter für den Cover-Upload.', 400);
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das angeforderte Foto-Album wurde nicht gefunden.', 404);
        }
        $aObj = p4c_fetch_object($rsAlbum);

        if (!isset($_FILES['cover']) || !is_array($_FILES['cover']) || (int)$_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Bitte wähle eine gültige Bilddatei (JPG oder PNG) aus.', 400);
        }

        $file     = $_FILES['cover'];
        $fileSize = (int)$file['size'];
        if ($fileSize <= 0 || $fileSize > 6 * 1024 * 1024) {
            Response::error('Das Coverbild darf maximal 6 MB groß sein.', 400);
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            Response::error('Es sind nur Bilder im Format JPG, JPEG oder PNG zulässig.', 400);
        }

        $imgInfo = @getimagesize((string)$file['tmp_name']);
        if ($imgInfo === false) {
            Response::error('Die hochgeladene Datei ist kein gültiges Bild.', 400);
        }

        $albumsBasePath = defined('PHOTO_ALBUMS_PATH') ? PHOTO_ALBUMS_PATH : (dirname(__DIR__, 5) . '/priv');
        $storageDir     = (string)$aObj->storage_location !== '' ? (string)$aObj->storage_location : (defined('PHOTO_ALBUMS_DEFAULT_DIR') ? PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums');
        $targetDir      = rtrim($albumsBasePath, '/\\') . '/' . $storageDir . '/' . $merchantId . '/' . $albumDbId;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        // Bisheriges Cover löschen, falls vorhanden
        $oldCol  = ($fsk === '16') ? 'preview_image_fsk16' : 'preview_image_fsk18';
        $oldFile = trim((string)($aObj->$oldCol ?? ''));
        if ($oldFile !== '' && is_file($targetDir . '/' . $oldFile)) {
            @unlink($targetDir . '/' . $oldFile);
        }

        $newFilename = strtolower(md5($albumDbId . '_' . microtime(true))) . '_' . $fsk . '.' . $ext;
        $destFile    = $targetDir . '/' . $newFilename;

        if (!@move_uploaded_file((string)$file['tmp_name'], $destFile)) {
            Response::error('Das Coverbild konnte nicht auf dem Server gespeichert werden.', 500);
        }
        @chmod($destFile, 0777);

        $hasFsk16    = ($fsk === '16') ? true : (trim((string)$aObj->preview_image_fsk16) !== '');
        $hasFsk18    = ($fsk === '18') ? true : (trim((string)$aObj->preview_image_fsk18) !== '');
        $totalPhotos = abs((int)$aObj->number_of_photos);

        // Falls das Album bereits eingereicht/geprüft war, bei Cover-Änderung erneut zur Prüfung markieren
        $extraUpdateSql = '';
        if ((int)$aObj->released !== 0 || (string)$aObj->album_checked !== '0000-00-00 00:00:00') {
            $nextRel        = ($hasFsk16 && $hasFsk18 && $totalPhotos >= 4) ? '1' : '0';
            $extraUpdateSql = ", `album_checked` = '0000-00-00 00:00:00', `released` = '" . $nextRel . "'";
        }

        p4c_query("UPDATE `photo_albums` SET `" . $oldCol . "` = '" . p4c_escape_string($newFilename) . "'" . $extraUpdateSql . " WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        $checksum = (string)($aObj->checksum ?? '');
        if (function_exists('photo_album_checksum') && class_exists('PhotoAlbum')) {
            $aClass   = new \PhotoAlbum($mysql, $albumDbId);
            $checksum = (string)photo_album_checksum($aClass);
            p4c_query("UPDATE `photo_albums` SET `checksum` = '" . p4c_escape_string($checksum) . "' WHERE `id` = '" . $albumDbId . "';", __FILE__, __LINE__);
        }

        $apiUrl      = defined('API_URL') ? API_URL : 'https://api.erocloud.net';
        $albumIdHash = (string)$aObj->album_id;
        $nowTs       = time();
        $previewUrl  = $apiUrl . '/PhotoAlbumPoster/FSK' . $fsk . '/' . rawurlencode($albumIdHash) . '?w=240&cs=' . rawurlencode($checksum) . '&t=' . $nowTs;
        $zoomUrl     = $apiUrl . '/PhotoAlbumPoster/FSK' . $fsk . '/' . rawurlencode($albumIdHash) . '?w=640&cs=' . rawurlencode($checksum) . '&t=' . $nowTs;
        $editorUrl   = $apiUrl . '/PhotoAlbumPoster/FSK' . $fsk . '/' . rawurlencode($albumIdHash) . '?w=1024&cs=' . rawurlencode($checksum) . '&t=' . $nowTs;

        Response::json([
            'status'       => 'ok',
            'message'      => 'Das FSK' . $fsk . '-Coverbild wurde erfolgreich aktualisiert.',
            'fsk'          => $fsk,
            'preview_url'  => $previewUrl,
            'zoom_url'     => $zoomUrl,
            'editor_url'   => $editorUrl,
            'has_fsk16'    => $hasFsk16,
            'has_fsk18'    => $hasFsk18,
            'total_photos' => $totalPhotos,
        ]);
    }

    /**
     * POST: Lädt ein oder mehrere Fotos in ein Foto-Album hoch (photo-albums/upload-photos)
     */
    public function uploadPhotos(): void
    {
        global $mysql;

        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $albumDbId = abs((int)$this->request->post('album_id', 0));
        if ($albumDbId === 0) {
            Response::error('Ungültige Foto-Album-ID.', 400);
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das angeforderte Foto-Album wurde nicht gefunden.', 404);
        }
        $aObj        = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$aObj->album_id;

        if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['name'])) {
            Response::error('Bitte wähle mindestens eine Bilddatei zum Hochladen aus.', 400);
        }

        $albumsBasePath = defined('PHOTO_ALBUMS_PATH') ? PHOTO_ALBUMS_PATH : (dirname(__DIR__, 5) . '/priv');
        $storageDir     = (string)$aObj->storage_location !== '' ? (string)$aObj->storage_location : (defined('PHOTO_ALBUMS_DEFAULT_DIR') ? PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums');
        $imagesDir      = rtrim($albumsBasePath, '/\\') . '/' . $storageDir . '/' . $merchantId . '/' . $albumDbId . '/images';

        if (!is_dir($imagesDir)) {
            @mkdir($imagesDir, 0777, true);
        }

        $mcpUrl         = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
        $uploadedPhotos = [];
        $fileCount      = count($_FILES['photos']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $err = (int)($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName  = (string)$_FILES['photos']['tmp_name'][$i];
            $origName = (string)$_FILES['photos']['name'][$i];
            $size     = (int)$_FILES['photos']['size'][$i];

            if ($size <= 0 || $size > 6 * 1024 * 1024) {
                continue;
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            if (@getimagesize($tmpName) === false) {
                continue;
            }

            $newFileName = 'IMG_' . date('Ymd_His') . '_' . mt_rand(100, 999) . '_' . ($i + 1) . '.' . $ext;
            $destPath    = $imagesDir . '/' . $newFileName;

            if (@move_uploaded_file($tmpName, $destPath)) {
                @chmod($destPath, 0777);
                $photoFileId = strtolower(md5($albumDbId . '_' . $newFileName . '_' . microtime(true)));

                p4c_query("INSERT INTO `photo_albums_photos` SET
                    `file_id`         = '" . p4c_escape_string($photoFileId) . "',
                    `album_id`        = '" . p4c_escape_string($albumIdHash) . "',
                    `merchant_id`     = '" . $merchantId . "',
                    `filename`        = '" . p4c_escape_string($newFileName) . "',
                    `upload_datetime` = '" . date('Y-m-d H:i:s') . "';", __FILE__, __LINE__);

                $uploadedPhotos[] = [
                    'num'        => 'photo_' . $photoFileId,
                    'file_id'    => $photoFileId,
                    'filename'   => $newFileName,
                    'rejected'   => 0,
                    'thumb_url'  => $mcpUrl . '/AlbumPhoto/' . rawurlencode($photoFileId) . '?w=200',
                    'zoom_url'   => $mcpUrl . '/AlbumPhoto/' . rawurlencode($photoFileId) . '?w=900',
                    'editor_url' => $mcpUrl . '/AlbumPhoto/' . rawurlencode($photoFileId) . '?w=1024',
                ];
            }
        }

        if (empty($uploadedPhotos)) {
            Response::error('Es konnten keine gültigen Fotos verarbeitet werden. Bitte achte auf das Format (JPG/PNG) und max. 6 MB pro Bild.', 400);
        }

        $totalPhotos = $this->recalculatePhotoCount($albumDbId, $albumIdHash, $merchantId, $mysql);

        // Falls das Album bereits eingereicht/geprüft war, bei neuen Fotos erneut zur Prüfung markieren
        if ((int)$aObj->released !== 0 || (string)$aObj->album_checked !== '0000-00-00 00:00:00') {
            $hasFsk16 = trim((string)$aObj->preview_image_fsk16) !== '';
            $hasFsk18 = trim((string)$aObj->preview_image_fsk18) !== '';
            $nextRel  = ($hasFsk16 && $hasFsk18 && $totalPhotos >= 4) ? '1' : '0';
            p4c_query("UPDATE `photo_albums` SET `album_checked` = '0000-00-00 00:00:00', `released` = '" . $nextRel . "' WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        }

        Response::json([
            'status'       => 'ok',
            'message'      => count($uploadedPhotos) . ' Foto(s) erfolgreich zum Album hinzugefügt.',
            'photos'       => $uploadedPhotos,
            'total_photos' => $totalPhotos,
        ]);
    }

    /**
     * POST: Löscht ein einzelnes Foto aus einem Foto-Album (photo-albums/delete-photo)
     */
    public function deletePhoto(): void
    {
        global $mysql;

        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $albumDbId   = abs((int)$this->request->post('album_id', 0));
        $photoFileId = trim((string)$this->request->post('file_id', ''));

        if ($albumDbId === 0 || $photoFileId === '') {
            Response::error('Ungültige Parameter zum Löschen des Fotos.', 400);
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das zugehörige Foto-Album wurde nicht gefunden.', 404);
        }
        $aObj        = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$aObj->album_id;

        $rsPhoto = p4c_query("SELECT * FROM `photo_albums_photos` WHERE `file_id` = '" . p4c_escape_string($photoFileId) . "' AND `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsPhoto || p4c_num_rows($rsPhoto) === 0) {
            Response::error('Das gewählte Foto wurde nicht gefunden.', 404);
        }
        $pObj = p4c_fetch_object($rsPhoto);

        // Wenn das Album bereits geprüft/eingereicht ist und das Foto NICHT abgelehnt wurde, müssen mind. 4 Fotos verbleiben
        $rsCount    = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        $cntRow     = $rsCount ? p4c_fetch_object($rsCount) : null;
        $currentCnt = $cntRow ? abs((int)$cntRow->cnt) : 0;

        $isPhotoRejected = abs((int)($pObj->rejected ?? 0)) === 1;
        if ((int)$aObj->released === 1 && !$isPhotoRejected && $currentCnt <= 4) {
            Response::error('Ein freigegebenes Foto-Album muss mindestens 4 Fotos enthalten. Bitte lade zuerst ein neues Foto hoch, bevor du dieses löschst.', 400);
        }

        $albumsBasePath = defined('PHOTO_ALBUMS_PATH') ? PHOTO_ALBUMS_PATH : (dirname(__DIR__, 5) . '/priv');
        $storageDir     = (string)$aObj->storage_location !== '' ? (string)$aObj->storage_location : (defined('PHOTO_ALBUMS_DEFAULT_DIR') ? PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums');
        $filePath       = rtrim($albumsBasePath, '/\\') . '/' . $storageDir . '/' . $merchantId . '/' . $albumDbId . '/images/' . (string)$pObj->filename;

        if (is_file($filePath)) {
            @unlink($filePath);
        }

        p4c_query("DELETE FROM `photo_albums_photos` WHERE `id` = '" . abs((int)$pObj->id) . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        $totalPhotos = $this->recalculatePhotoCount($albumDbId, $albumIdHash, $merchantId, $mysql);

        if ((int)$aObj->released !== 0 || (string)$aObj->album_checked !== '0000-00-00 00:00:00') {
            $hasFsk16 = trim((string)$aObj->preview_image_fsk16) !== '';
            $hasFsk18 = trim((string)$aObj->preview_image_fsk18) !== '';
            $nextRel  = ($hasFsk16 && $hasFsk18 && $totalPhotos >= 4) ? '1' : '0';
            p4c_query("UPDATE `photo_albums` SET `album_checked` = '0000-00-00 00:00:00', `released` = '" . $nextRel . "' WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        }

        Response::json([
            'status'       => 'ok',
            'message'      => 'Das Foto wurde aus dem Album entfernt.',
            'file_id'      => $photoFileId,
            'total_photos' => $totalPhotos,
        ]);
    }

    /**
     * POST: Schließt Schritt 2 des Foto-Album-Uploads ab (photo-albums/publish)
     */
    public function publish(): void
    {
        global $mysql;

        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $albumDbId = abs((int)$this->request->post('album_id', 0));
        if ($albumDbId === 0) {
            Response::error('Ungültige Foto-Album-ID.', 400);
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das angeforderte Foto-Album wurde nicht gefunden.', 404);
        }
        $aObj        = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$aObj->album_id;
        $totalPhotos = $this->recalculatePhotoCount($albumDbId, $albumIdHash, $merchantId, $mysql);

        if (trim((string)$aObj->preview_image_fsk16) === '') {
            Response::error('Bitte lade ein jugendfreies FSK16-Coverbild für dein Foto-Album hoch.', 400);
        }
        if (trim((string)$aObj->preview_image_fsk18) === '') {
            Response::error('Bitte lade ein FSK18-Coverbild für dein Foto-Album hoch.', 400);
        }
        if ($totalPhotos < 4) {
            Response::error('Ein Foto-Album muss mindestens 4 Fotos enthalten. Bitte lade noch weitere Fotos hoch.', 400);
        }

        $rsRejPhotos = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "' AND `rejected` = '1';", __FILE__, __LINE__);
        $rejPhotoRow = $rsRejPhotos ? p4c_fetch_object($rsRejPhotos) : null;
        if ($rejPhotoRow && abs((int)$rejPhotoRow->cnt) > 0) {
            Response::error('Bitte lösche zuerst die rot markierten (abgelehnten) Fotos aus dem Album, bevor du es erneut zur Prüfung einreichst.', 400);
        }

        p4c_query("UPDATE `photo_albums` SET
            `number_of_photos` = '" . $totalPhotos . "',
            `album_checked`    = '0000-00-00 00:00:00',
            `released`         = '1'
            WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        unset($_SESSION['upload_photo_album']);

        if (function_exists('log_action')) {
            log_action('Foto-Album #' . $albumDbId . ' vollständig hochgeladen und zur Prüfung eingereicht');
        }

        Response::json([
            'status'   => 'ok',
            'message'  => 'Dein Foto-Album wurde erfolgreich fertiggestellt und zur Prüfung eingereicht.',
            'redirect' => '#/photo-album-upload?step=3&album_id=' . $albumDbId,
        ]);
    }

    /**
     * POST: Löscht ein noch nicht veröffentlichtes Foto-Album (photo-albums/delete)
     */
    public function delete(): void
    {
        $this->requireValidCsrf();
        $merchantId = $this->requireMerchantId();

        $albumDbId = abs((int)$this->request->post('id', 0));
        if ($albumDbId === 0) {
            Response::error('Ungültige Foto-Album-ID.', 400);
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das angeforderte Foto-Album wurde nicht gefunden.', 404);
        }

        $aObj        = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$aObj->album_id;

        $rsOnline      = p4c_query("SELECT `id` FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;", __FILE__, __LINE__);
        $isAlbumOnline = ($rsOnline && p4c_num_rows($rsOnline) > 0);

        if ((string)$aObj->album_checked !== '0000-00-00 00:00:00' || (int)$aObj->released === 1 || $isAlbumOnline) {
            Response::error('Bereits geprüfte oder veröffentlichte Foto-Alben können aus Sicherheitsgründen nicht direkt gelöscht werden. Bitte wende dich bei Bedarf an den Support.', 400);
        }

        // Physische Dateien des Entwurfs aufräumen
        $albumsBasePath = defined('PHOTO_ALBUMS_PATH') ? PHOTO_ALBUMS_PATH : (dirname(__DIR__, 5) . '/priv');
        $storageDir     = (string)$aObj->storage_location !== '' ? (string)$aObj->storage_location : (defined('PHOTO_ALBUMS_DEFAULT_DIR') ? PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums');
        $albumDir       = rtrim($albumsBasePath, '/\\') . '/' . $storageDir . '/' . $merchantId . '/' . $albumDbId;
        if (is_dir($albumDir)) {
            $imagesDir = $albumDir . '/images';
            if (is_dir($imagesDir)) {
                foreach ((array)@glob($imagesDir . '/*') as $f) {
                    if (is_string($f) && is_file($f)) {
                        @unlink($f);
                    }
                }
                @rmdir($imagesDir);
            }
            foreach ((array)@glob($albumDir . '/*') as $f) {
                if (is_string($f) && is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($albumDir);
        }

        p4c_query("UPDATE `photo_albums` SET `status` = 'deleted', `deleted_datetime` = '" . date('Y-m-d H:i:s') . "' WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        p4c_query("DELETE FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        if (function_exists('log_action')) {
            log_action('Foto-Album #' . $albumDbId . ' gelöscht');
        }

        Response::json([
            'status'   => 'ok',
            'message'  => 'Das Foto-Album wurde erfolgreich gelöscht.',
            'redirect' => '#/photo-albums',
        ]);
    }

    /**
     * Aktualisiert number_of_photos und checksum in photo_albums.
     */
    private function recalculatePhotoCount(int $albumDbId, string $albumIdHash, int $merchantId, $mysql): int
    {
        $rsCnt = p4c_query("SELECT COUNT(*) AS `cnt`
            FROM `photo_albums_photos`
            WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
              AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        $row   = $rsCnt ? p4c_fetch_object($rsCnt) : null;
        $total = $row ? abs((int)$row->cnt) : 0;

        p4c_query("UPDATE `photo_albums` SET `number_of_photos` = '" . $total . "' WHERE `id` = '" . $albumDbId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        if (function_exists('photo_album_checksum') && class_exists('PhotoAlbum')) {
            $aClass   = new \PhotoAlbum($mysql, $albumDbId);
            $checksum = photo_album_checksum($aClass);
            p4c_query("UPDATE `photo_albums` SET `checksum` = '" . p4c_escape_string((string)$checksum) . "' WHERE `id` = '" . $albumDbId . "';", __FILE__, __LINE__);
        }

        return $total;
    }

    /**
     * Lädt den letzten Ablehnungsgrund für ein Foto-Album aus der Historie.
     *
     * @return array<string, string>|null
     */
    private function loadAlbumRejectionInfo(string $albumIdHash, int $released, string $status): ?array
    {
        if ($released !== 2 && $status !== 'blocked') {
            return null;
        }

        $rsRejHist = p4c_query("SELECT * FROM `rejection_reason_photo_album_history` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' ORDER BY `datetime` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsRejHist && p4c_num_rows($rsRejHist) > 0) {
            $rejHistObj = p4c_fetch_object($rsRejHist);
            $shortTitle = '';
            if (!empty($rejHistObj->id_name) && (string)$rejHistObj->id_name !== 'free_text') {
                $rsRejShort = p4c_query("SELECT `de_short` FROM `rejection_reason_photo_album` WHERE `id_name` = '" . p4c_escape_string((string)$rejHistObj->id_name) . "' LIMIT 1;", __FILE__, __LINE__);
                if ($rsRejShort && p4c_num_rows($rsRejShort) > 0) {
                    $shortObj   = p4c_fetch_object($rsRejShort);
                    $shortTitle = self::decodeLegacyText((string)$shortObj->de_short);
                }
            }
            return [
                'reason' => $shortTitle,
                'text'   => self::decodeLegacyText((string)($rejHistObj->text ?? '')),
            ];
        }

        if ($status === 'blocked') {
            return [
                'reason' => 'Foto-Album gesperrt',
                'text'   => 'Dieses Foto-Album wurde durch den Support gesperrt. Bitte wende dich bei Rückfragen an den Support.',
            ];
        }

        return null;
    }

    /**
     * Bereinigt den Albumtitel analog zu Legacy-Regeln (entfernt unzulässige Sonderzeichen, ersetzt € durch EUR).
     */
    private static function cleanAlbumTitle(string $title): string
    {
        global $replace_title_ary;

        $cleaned = trim($title);
        if (isset($replace_title_ary) && is_array($replace_title_ary)) {
            $cleaned = str_replace($replace_title_ary, '', $cleaned);
        } else {
            $cleaned = str_replace(['*', '#', '+', '~', '<', '>', '|', '{', '}', '[', ']', '^', '°', '§', '$', '%', '&', '/', '=', '`', '´', '"', "'"], '', $cleaned);
        }
        $cleaned = str_replace('€', 'EUR', $cleaned);
        return trim((string)preg_replace('/\s{2,}/', ' ', $cleaned));
    }

    /**
     * Validiert die Eingabedaten für ein Foto-Album.
     */
    private function validateAlbumInput(
        int $merchantId,
        string $title,
        string $description,
        int $actorId,
        string $onlineAtRaw,
        bool $validateOnlineAt,
        int $amountDownload,
        string $metaTitle,
        string $metaDescription,
        string $seoUrl,
        int $excludeAlbumId = 0
    ): void {
        $lenTitle = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($lenTitle < 5 || $lenTitle > 65) {
            Response::error('Bitte gib einen Albumtitel zwischen 5 und 65 Zeichen ein.', 400);
        }
        if (function_exists('email_in_string') && email_in_string($title)) {
            Response::error('Der Albumtitel darf keine E-Mail-Adresse enthalten.', 400);
        }
        if (function_exists('url_in_string') && url_in_string($title)) {
            Response::error('Der Albumtitel darf keine Internetadresse (URL) enthalten.', 400);
        }

        // Prüfung auf doppelten Albumtitel beim selben Partner (analog ACP photo_album_checking.php)
        $rsDup = p4c_query("SELECT `id` FROM `photo_albums`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `title` = '" . p4c_escape_string($title) . "'
              AND `status` != 'deleted'
              AND `id` != '" . abs($excludeAlbumId) . "'
            LIMIT 1;", __FILE__, __LINE__);
        if ($rsDup && p4c_num_rows($rsDup) > 0) {
            Response::error('Du verwendest diesen Albumtitel bereits bei einem anderen Foto-Album. Bitte wähle einen eindeutigen Titel.', 400);
        }

        $lenDesc = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
        if ($lenDesc < 30 || $lenDesc > 2000) {
            Response::error('Bitte gib eine ausführliche Beschreibung mit mindestens 30 Zeichen (max. 2.000 Zeichen) ein.', 400);
        }
        if (function_exists('email_in_string') && email_in_string($description)) {
            Response::error('Die Beschreibung darf keine E-Mail-Adresse enthalten.', 400);
        }
        if (function_exists('url_in_string') && url_in_string($description)) {
            Response::error('Die Beschreibung darf keine Internetadresse (URL) enthalten.', 400);
        }

        if ($actorId <= 0) {
            Response::error('Bitte wähle ein Darsteller-Profil für dieses Foto-Album aus.', 400);
        }
        $rsAct = p4c_query("SELECT `id` FROM `actors` WHERE `id` = '" . $actorId . "' AND `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAct || p4c_num_rows($rsAct) === 0) {
            Response::error('Das gewählte Darsteller-Profil ist ungültig.', 400);
        }

        if ($validateOnlineAt) {
            $ts = strtotime($onlineAtRaw);
            if ($ts === false) {
                Response::error('Bitte gib ein gültiges Veröffentlichungsdatum an.', 400);
            }
        }

        // Preis in Cent: 0 (kostenlos) oder 100..5000 (1,00 EUR bis 50,00 EUR in 100er-Schritten)
        if ($amountDownload < 0 || $amountDownload > 5000 || ($amountDownload % 100 !== 0)) {
            Response::error('Bitte wähle einen gültigen Verkaufspreis zwischen 0,00 € und 50,00 € aus.', 400);
        }

        if ($metaTitle !== '') {
            $lenMt = function_exists('mb_strlen') ? mb_strlen($metaTitle, 'UTF-8') : strlen($metaTitle);
            if ($lenMt < 5 || $lenMt > 80) {
                Response::error('Der optionale Meta-Titel muss zwischen 5 und 80 Zeichen lang sein.', 400);
            }
        }
        if ($metaDescription !== '') {
            $lenMd = function_exists('mb_strlen') ? mb_strlen($metaDescription, 'UTF-8') : strlen($metaDescription);
            if ($lenMd < 5 || $lenMd > 160) {
                Response::error('Die optionale Meta-Beschreibung muss zwischen 5 und 160 Zeichen lang sein.', 400);
            }
        }
        if ($seoUrl !== '' && !preg_match('/^[a-zA-Z0-9-]+$/', $seoUrl)) {
            Response::error('Die optionale SEO-URL darf nur aus Buchstaben, Zahlen und Bindestrichen bestehen.', 400);
        }
    }

    /**
     * Lädt alle freigegebenen Darsteller-Profile des Partners.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadActiveMerchantActors(int $merchantId, int $includeActorId = 0): array
    {
        $actors = [];
        $rs     = p4c_query("SELECT `id`, `username`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
              AND (
                  `status` = 'active'
                  OR `id` = '" . abs((int)$includeActorId) . "'
              )
            ORDER BY `username` ASC;", __FILE__, __LINE__);

        if ($rs) {
            while ($row = p4c_fetch_object($rs)) {
                $actors[] = [
                    'id'       => abs((int)$row->id),
                    'username' => self::decodeLegacyText((string)$row->username),
                ];
            }
        }

        return $actors;
    }

    private function requireValidCsrf(): void
    {
        $token = $this->request->header('X-CSRF-Token');
        if ($token === '') {
            $token = (string)$this->request->post('_csrf', '');
        }
        if ($token === '') {
            $token = (string)$this->request->post('csrf_token', '');
        }
        if (!Csrf::validate($token)) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
        }
    }

    private function requireMerchantId(): int
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            Response::error('Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.', 401);
        }
        return $merchantId;
    }

    private static function decodeLegacyText(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = (string)preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $decoded);
        $decoded = (string)preg_replace('/<\/\s*p\s*>\s*<\s*p[^>]*>/i', "\n\n", $decoded);
        $decoded = (string)preg_replace('/<\/?\s*p[^>]*>/i', '', $decoded);
        $decoded = str_replace(["\r\n", "\r"], "\n", $decoded);
        $decoded = (string)preg_replace("/\n{3,}/", "\n\n", $decoded);
        return trim($decoded);
    }
}
