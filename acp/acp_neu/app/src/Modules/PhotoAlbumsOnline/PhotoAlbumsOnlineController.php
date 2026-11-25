<?php
declare(strict_types=1);

namespace App\Modules\PhotoAlbumsOnline;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\MoviesOnline\MoviesOnlineController;

/**
 * EroCloud ACP (v2.0) - PhotoAlbumsOnlineController
 * Steuert die Fotoalben-Gesamtübersicht (#/photo-albums-online) sowie die Einzelbearbeitung (#/photo-album-edit?id=...).
 */
final class PhotoAlbumsOnlineController
{
    private Request $request;

    /** @var array<int, string> */
    private const REPLACE_TITLE_CHARS = ['°', '^', '²', '§', '$', '%', '{', '[', ']', '}', '´', '`', '~', "'", '_', ';', '<', '>'];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 1. Gesamtübersicht aller veröffentlichten Fotoalben (#/photo-albums-online)
     */
    public function list(): string
    {
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        $env = function_exists('aic_check_environment') ? aic_check_environment() : ['llm_ready' => false];

        // Filter- & Paginierungs-Parameter
        $search  = trim((string)$this->request->get('q', ''));
        $status  = trim((string)$this->request->get('status', 'all'));
        $cat     = trim((string)$this->request->get('cat', 'all'));
        $bram    = trim((string)$this->request->get('bram', 'all'));
        $sort    = trim((string)$this->request->get('sort', 'id_desc'));
        $page    = max(1, abs((int)$this->request->get('page', 1)));
        $perPage = abs((int)$this->request->get('per_page', 100));
        if (!in_array($perPage, [50, 100, 250, 500], true)) {
            $perPage = 100;
        }

        // 1. Globale KPIs ermitteln
        $kpis = [
            'total'           => 0,
            'active'          => 0,
            'blocked'         => 0,
            'deleted'         => 0,
            'porn'            => 0,
            'fetish'          => 0,
            'total_photos'    => 0,
            'total_views'     => 0,
            'total_purchases' => 0,
            'storage_text'    => self::getCachedOnlineAlbumsStorageText(),
        ];

        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_cnt`,
                SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) AS `active_cnt`,
                SUM(CASE WHEN `status` = 'blocked' THEN 1 ELSE 0 END) AS `blocked_cnt`,
                SUM(CASE WHEN `status` = 'deleted' THEN 1 ELSE 0 END) AS `deleted_cnt`,
                SUM(CASE WHEN `category_master` = 'porn' THEN 1 ELSE 0 END) AS `porn_cnt`,
                SUM(CASE WHEN `category_master` = 'fetish' THEN 1 ELSE 0 END) AS `fetish_cnt`,
                COALESCE(SUM(`number_of_photos`), 0) AS `photos_sum`,
                COALESCE(SUM(`views`), 0) AS `views_sum`,
                COALESCE(SUM(`purchases`), 0) AS `purchases_sum`
             FROM `photo_albums_online`;",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && p4c_num_rows($rsKpi) > 0) {
            $kRow = p4c_fetch_object($rsKpi);
            $kpis['total']           = abs((int)($kRow->total_cnt ?? 0));
            $kpis['active']          = abs((int)($kRow->active_cnt ?? 0));
            $kpis['blocked']         = abs((int)($kRow->blocked_cnt ?? 0));
            $kpis['deleted']         = abs((int)($kRow->deleted_cnt ?? 0));
            $kpis['porn']            = abs((int)($kRow->porn_cnt ?? 0));
            $kpis['fetish']          = abs((int)($kRow->fetish_cnt ?? 0));
            $kpis['total_photos']    = abs((int)($kRow->photos_sum ?? 0));
            $kpis['total_views']     = abs((int)($kRow->views_sum ?? 0));
            $kpis['total_purchases'] = abs((int)($kRow->purchases_sum ?? 0));
        }

        // 2. WHERE-Bedingungen für Filterung aufbauen
        $where = ['1=1'];
        if (in_array($status, ['active', 'blocked', 'deleted'], true)) {
            $where[] = "ao.`status` = '" . p4c_escape_string($status) . "'";
        }
        if (in_array($cat, ['porn', 'fetish'], true)) {
            $where[] = "ao.`category_master` = '" . p4c_escape_string($cat) . "'";
        }

        if ($search !== '') {
            $escSearch = p4c_escape_string($search);
            $searchConds = [
                "ao.`title` LIKE '%" . $escSearch . "%'",
                "ao.`description` LIKE '%" . $escSearch . "%'",
                "ao.`album_id` LIKE '%" . $escSearch . "%'",
                "a.`username` LIKE '%" . $escSearch . "%'",
            ];
            $cleanNum = ltrim($search, '#');
            if ($cleanNum !== '' && ctype_digit($cleanNum)) {
                $numSearch = abs((int)$cleanNum);
                $searchConds[] = "ao.`id` = '" . $numSearch . "'";
                $searchConds[] = "pa.`id` = '" . $numSearch . "'";
                $searchConds[] = "ao.`merchant_id` = '" . $numSearch . "'";
                $searchConds[] = "ao.`actor_id` = '" . $numSearch . "'";
            }
            $where[] = '(' . implode(' OR ', $searchConds) . ')';
        }

        $whereSql = implode(' AND ', $where);

        // Sortierung
        $orderBySql = 'ao.`id` DESC';
        if ($sort === 'id_asc') {
            $orderBySql = 'ao.`id` ASC';
        } elseif ($sort === 'online_desc') {
            $orderBySql = 'ao.`online_at` DESC, ao.`id` DESC';
        } elseif ($sort === 'photos_desc') {
            $orderBySql = 'ao.`number_of_photos` DESC, ao.`id` DESC';
        } elseif ($sort === 'purchases_desc') {
            $orderBySql = 'ao.`purchases` DESC, ao.`id` DESC';
        } elseif ($sort === 'views_desc') {
            $orderBySql = 'ao.`views` DESC, ao.`id` DESC';
        } elseif ($sort === 'title_asc') {
            $orderBySql = 'ao.`title` ASC';
        }

        // Gefilterte Gesamtanzahl ermitteln
        $rsCount = p4c_query(
            "SELECT COUNT(*) AS `cnt`
             FROM `photo_albums_online` ao
             LEFT JOIN `actors` a ON a.`id` = ao.`actor_id`
             LEFT JOIN `photo_albums` pa ON pa.`album_id` = ao.`album_id`
             WHERE " . $whereSql . ";",
            __FILE__,
            __LINE__
        );
        $filteredTotal = $rsCount ? abs((int)(p4c_fetch_object($rsCount)->cnt ?? 0)) : 0;
        $totalPages    = max(1, (int)ceil($filteredTotal / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $rsAlbums = p4c_query(
            "SELECT
                ao.`id`, ao.`album_id`, ao.`merchant_id`, ao.`actor_id`, ao.`checksum`,
                ao.`number_of_photos`, ao.`title`, ao.`description`, ao.`meta_title`, ao.`meta_description`,
                ao.`online_at`, ao.`album_checked`, ao.`status`, ao.`category_master`,
                ao.`amount_download`, ao.`views`, ao.`purchases`,
                pa.`id` AS `album_db_id`,
                a.`username` AS `actor_username`
             FROM `photo_albums_online` ao
             LEFT JOIN `actors` a ON a.`id` = ao.`actor_id`
             LEFT JOIN `photo_albums` pa ON pa.`album_id` = ao.`album_id`
             WHERE " . $whereSql . "
             ORDER BY " . $orderBySql . "
             LIMIT " . $offset . ", " . $perPage . ";",
            __FILE__,
            __LINE__
        );

        $albums = [];
        if ($rsAlbums && p4c_num_rows($rsAlbums) > 0) {
            while ($row = p4c_fetch_object($rsAlbums)) {
                $onlineId  = abs((int)$row->id);
                $albumDbId = abs((int)($row->album_db_id ?? 0));
                $albumHash = (string)($row->album_id ?? '');
                $title     = self::decodeText((string)($row->title ?? ''));
                $desc      = self::decodeDescription((string)($row->description ?? ''));
                $metaTitle = self::decodeText((string)($row->meta_title ?? ''));
                $metaDesc  = self::decodeText((string)($row->meta_description ?? ''));

                // Live BRAM-Textanalyse für die angezeigte Seite
                $bramComp  = 'ok';
                $bramScore = 0;
                $bramSum   = 'Unbedenklich';
                if (function_exists('aic_analyze_texts')) {
                    $rep = aic_analyze_texts([
                        'title'            => $title,
                        'description'      => $desc,
                        'meta_title'       => $metaTitle,
                        'meta_description' => $metaDesc,
                    ], [], '', $env, 'photo_album');
                    $bramComp  = (string)($rep['compliance_status'] ?? 'ok');
                    $bramScore = abs((int)($rep['risk_score'] ?? 0));
                    $bramSum   = (string)($rep['summary_de'] ?? '');
                }

                if ($bram === 'green' && !in_array($bramComp, ['ok', 'green'], true)) {
                    continue;
                }
                if ($bram === 'attention' && in_array($bramComp, ['ok', 'green'], true)) {
                    continue;
                }

                $amountDownload = abs((int)($row->amount_download ?? 0));
                $priceCoins     = (int)round($amountDownload / 10);
                $priceEur       = number_format($amountDownload / 100, 2, ',', '.');

                $albums[] = [
                    'id'              => $onlineId,
                    'album_db_id'     => $albumDbId,
                    'album_id'        => $albumHash,
                    'merchant_id'     => abs((int)$row->merchant_id),
                    'actor_id'        => abs((int)$row->actor_id),
                    'actor_name'      => self::decodeText((string)($row->actor_username ?? '')),
                    'title'           => $title,
                    'description'     => $desc,
                    'status'          => (string)($row->status ?? 'active'),
                    'category_master' => (string)($row->category_master ?? 'porn'),
                    'photos_count'    => abs((int)($row->number_of_photos ?? 0)),
                    'price_coins'     => $priceCoins,
                    'price_eur'       => $priceEur,
                    'views'           => abs((int)($row->views ?? 0)),
                    'purchases'       => abs((int)($row->purchases ?? 0)),
                    'online_at'       => (string)($row->online_at ?? ''),
                    'album_checked'   => (string)($row->album_checked ?? ''),
                    'bram_compliance' => $bramComp,
                    'bram_score'      => $bramScore,
                    'bram_summary'    => $bramSum,
                ];
            }
        }

        $switcherCounts = MoviesOnlineController::getContentSwitcherCounts();

        return View::render('PhotoAlbumsOnline', 'list', [
            'albums'         => $albums,
            'kpis'           => $kpis,
            'switcherCounts' => $switcherCounts,
            'filters'        => [
                'q'          => $search,
                'status'     => $status,
                'cat'        => $cat,
                'bram'       => $bram,
                'sort'       => $sort,
                'page'       => $page,
                'per_page'   => $perPage,
                'total_rows' => $filteredTotal,
                'total_page' => $totalPages,
            ],
        ]);
    }

    /**
     * 2. AJAX-Aktion: Speicherplatz aller Online-Fotoalben neu berechnen (?route=photo-albums-online/recalc-size)
     */
    public function recalcSize(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF).', 403);
            return;
        }

        @session_write_close();

        $totalBytes = 0;
        $rsOnline = p4c_query(
            "SELECT pa.`id`, pa.`merchant_id`, pa.`storage_location`
             FROM `photo_albums_online` ao
             INNER JOIN `photo_albums` pa ON ao.`album_id` = pa.`album_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
            while ($aRow = p4c_fetch_object($rsOnline)) {
                $folder = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/'
                    . (string)$aRow->storage_location . '/'
                    . abs((int)$aRow->merchant_id) . '/'
                    . abs((int)$aRow->id);
                $totalBytes += MoviesOnlineController::getFolderSize($folder);
            }
        }

        $cacheDir  = (defined('TEMP_DIR') && is_dir((string)TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
        $cacheFile = $cacheDir . '/erocloud_online_albums_size_cache.txt';
        @file_put_contents($cacheFile, (string)$totalBytes);

        $formatted = MoviesOnlineController::formatBytes($totalBytes);

        Response::success([
            'bytes'     => $totalBytes,
            'formatted' => $formatted,
            'message'   => 'Speicherplatz der Online-Fotoalben wurde neu berechnet: ' . $formatted,
        ]);
    }

    /**
     * 3. Einzelbearbeitung eines veröffentlichten Fotoalbums (#/photo-album-edit?id=...)
     */
    public function edit(): string
    {
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }

        $rawId = trim((string)$this->request->get('id', ''));
        if ($rawId === '') {
            return '<div class="alert alert-warning m-4">Keine Fotoalbum-ID angegeben. <a href="#/photo-albums-online" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        // Flexibles Lookup: photo_albums_online.id, album_id (32 Zeichen) oder photo_albums.id
        $ao = null;
        if (strlen($rawId) === 32) {
            $rsAo = p4c_query("SELECT * FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($rawId) . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsAo && p4c_num_rows($rsAo) > 0) {
                $ao = p4c_fetch_object($rsAo);
            }
        } else {
            $numId = abs((int)$rawId);
            $rsAo = p4c_query("SELECT * FROM `photo_albums_online` WHERE `id` = '" . $numId . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsAo && p4c_num_rows($rsAo) > 0) {
                $ao = p4c_fetch_object($rsAo);
            } else {
                $rsPaById = p4c_query("SELECT `album_id` FROM `photo_albums` WHERE `id` = '" . $numId . "' LIMIT 1;", __FILE__, __LINE__);
                if ($rsPaById && p4c_num_rows($rsPaById) > 0) {
                    $aHash = (string)(p4c_fetch_object($rsPaById)->album_id ?? '');
                    if ($aHash !== '') {
                        $rsAo2 = p4c_query("SELECT * FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($aHash) . "' LIMIT 1;", __FILE__, __LINE__);
                        if ($rsAo2 && p4c_num_rows($rsAo2) > 0) {
                            $ao = p4c_fetch_object($rsAo2);
                        }
                    }
                }
            }
        }

        if (!$ao) {
            return '<div class="alert alert-danger m-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>Das angeforderte Online-Fotoalbum (#' . View::e($rawId) . ') wurde nicht in <code>photo_albums_online</code> gefunden. <a href="#/photo-albums-online" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        $onlineId    = abs((int)$ao->id);
        $albumIdHash = (string)$ao->album_id;
        $merchantId  = abs((int)$ao->merchant_id);
        $actorId     = abs((int)$ao->actor_id);

        // Zugehörigen Datensatz aus photo_albums laden
        $rsPa = p4c_query("SELECT * FROM `photo_albums` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' LIMIT 1;", __FILE__, __LINE__);
        $pa   = ($rsPa && p4c_num_rows($rsPa) > 0) ? p4c_fetch_object($rsPa) : $ao;
        $albumDbId = abs((int)($pa->id ?? $onlineId));

        // Vorheriges & nächstes Online-Fotoalbum
        $prevOnlineId = 0;
        $nextOnlineId = 0;
        $rsPrev = p4c_query("SELECT `id` FROM `photo_albums_online` WHERE `id` < '" . $onlineId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && p4c_num_rows($rsPrev) > 0) {
            $prevOnlineId = abs((int)p4c_fetch_object($rsPrev)->id);
        }
        $rsNext = p4c_query("SELECT `id` FROM `photo_albums_online` WHERE `id` > '" . $onlineId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsNext && p4c_num_rows($rsNext) > 0) {
            $nextOnlineId = abs((int)p4c_fetch_object($rsNext)->id);
        }

        // Merchant-Infos laden
        $merchantPartnerId = 0;
        $merchantUsername  = 'Unbekannt';
        if (class_exists('Merchant')) {
            global $mysql;
            $merchantObj       = new \Merchant($mysql, $merchantId);
            $merchantPartnerId = abs((int)$merchantObj->partner_id());
            $merchantUsername  = self::decodeText((string)$merchantObj->username('aes_decrypt'));
        }

        // Darsteller-Profile des Merchants laden
        $merchantActors = [];
        $currentActorName = '';
        $currentActorCategories = [];
        $rsActors = p4c_query(
            "SELECT `id`, `username`, `actor_categories`, `status`
             FROM `actors`
             WHERE `merchant_id` = '" . $merchantId . "'
             ORDER BY `username` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($act = p4c_fetch_object($rsActors)) {
                $aid   = abs((int)$act->id);
                $aname = self::decodeText((string)($act->username ?? ''));
                $acats = (string)($act->actor_categories ?? '');
                if ($aid === $actorId) {
                    $currentActorName       = $aname;
                    $currentActorCategories = array_values(array_filter(array_map('trim', explode(',', $acats))));
                }
                $merchantActors[] = [
                    'id'               => $aid,
                    'username'         => $aname,
                    'actor_categories' => $acats,
                    'status'           => (string)($act->status ?? ''),
                ];
            }
        }

        // Freischaltungs- & Bearbeitungs-Mitarbeiter auflösen
        $releasedFromName = '-';
        $releasedFromId   = abs((int)($ao->released_from ?? $pa->released_from ?? 0));
        if ($releasedFromId > 0) {
            $rsEmp = p4c_query("SELECT `username` FROM `employee` WHERE `id` = '" . $releasedFromId . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsEmp && p4c_num_rows($rsEmp) > 0) {
                $releasedFromName = self::decodeText((string)(p4c_fetch_object($rsEmp)->username ?? '-'));
            }
        }

        $lastUpdatedByName = '-';
        $lastUpdatedById   = abs((int)($ao->last_updated_by ?? $pa->last_updated_by ?? 0));
        if ($lastUpdatedById > 0) {
            $rsEmp2 = p4c_query("SELECT `username` FROM `employee` WHERE `id` = '" . $lastUpdatedById . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsEmp2 && p4c_num_rows($rsEmp2) > 0) {
                $lastUpdatedByName = self::decodeText((string)(p4c_fetch_object($rsEmp2)->username ?? '-'));
            }
        }

        // Texte dekodieren
        $title       = self::decodeText((string)($ao->title ?? ''));
        $description = self::decodeDescription((string)($ao->description ?? ''));
        $metaTitle   = self::decodeText((string)($ao->meta_title ?? ''));
        $metaDesc    = self::decodeText((string)($ao->meta_description ?? ''));
        $seoUrl      = self::decodeText((string)($ao->seo_url ?? ''));
        $adminInfos  = self::decodeText((string)($ao->admin_infos ?? ''));

        // Duplikat-Prüfung (anderes aktives/gesperrtes Online-Album mit identischem Titel; gelöschte Alben ignorieren)
        $duplicateAlbum = null;
        if ($title !== '' && (string)($ao->status ?? '') !== 'deleted') {
            $rsDup = p4c_query(
                "SELECT `id`, `album_id`, `title`, `merchant_id`
                 FROM `photo_albums_online`
                 WHERE `title` = '" . p4c_escape_string((string)$ao->title) . "'
                   AND `status` != 'deleted'
                   AND `id` != '" . $onlineId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if ($rsDup && p4c_num_rows($rsDup) > 0) {
                $dupRow = p4c_fetch_object($rsDup);
                $duplicateAlbum = [
                    'id'          => abs((int)$dupRow->id),
                    'album_id'    => (string)($dupRow->album_id ?? ''),
                    'title'       => self::decodeText((string)($dupRow->title ?? '')),
                    'merchant_id' => abs((int)($dupRow->merchant_id ?? 0)),
                ];
            }
        }

        // Reale Käuferanzahl in photo_albums_access prüfen (verbindlicher Käuferschutz)
        $accessCount = 0;
        $lastAccessBuy = '';
        $rsAccessCnt = p4c_query(
            "SELECT COUNT(*) AS `cnt`, MAX(`buy_timestamp`) AS `last_buy`
             FROM `photo_albums_access`
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
            __FILE__,
            __LINE__
        );
        if ($rsAccessCnt && p4c_num_rows($rsAccessCnt) > 0) {
            $accRow        = p4c_fetch_object($rsAccessCnt);
            $accessCount   = abs((int)($accRow->cnt ?? 0));
            $lastAccessBuy = (string)($accRow->last_buy ?? '');
        }
        $effectivePurchases = max(abs((int)($ao->purchases ?? 0)), $accessCount);

        // Alle Fotos des Albums laden
        $photos = [];
        $rejectedPhotosCount = 0;
        $stLocPhotos = trim((string)($ao->storage_location ?? $pa->storage_location ?? ''));
        if ($stLocPhotos === '') {
            $stLocPhotos = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';
        }
        $imagesDirOnline = (defined('PHOTO_ALBUMS_PATH') && $merchantId > 0 && $albumDbId > 0)
            ? (rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . trim($stLocPhotos, '/\\') . '/' . $merchantId . '/' . $albumDbId . '/images/')
            : '';

        $rsPhotos = p4c_query(
            "SELECT * FROM `photo_albums_photos`
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
             ORDER BY `id` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsPhotos && p4c_num_rows($rsPhotos) > 0) {
            $idx = 1;
            while ($ph = p4c_fetch_object($rsPhotos)) {
                $isRejected = abs((int)($ph->rejected ?? 0)) === 1;
                if ($isRejected) {
                    $rejectedPhotosCount++;
                }
                $phFilename = basename((string)($ph->filename ?? ''));
                $phFile     = ($imagesDirOnline !== '' && $phFilename !== '') ? ($imagesDirOnline . $phFilename) : '';
                $phVer      = ($phFile !== '' && is_file($phFile)) ? (int)@filemtime($phFile) : 0;
                $photos[] = [
                    'id'       => abs((int)$ph->id),
                    'index'    => $idx++,
                    'file_id'  => (string)($ph->file_id ?? ''),
                    'filename' => (string)($ph->filename ?? ''),
                    'rejected' => $isRejected,
                    'mtime'    => $phVer,
                ];
            }
        }

        $totalPhotosCount = count($photos);
        if ($totalPhotosCount === 0) {
            $totalPhotosCount = abs((int)($ao->number_of_photos ?? 0));
        }

        // Ordnergröße auf Disk
        $storageLoc = (string)($ao->storage_location ?? $pa->storage_location ?? '');
        $albumDir   = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . $storageLoc . '/' . $merchantId . '/' . $albumDbId;
        $folderBytes = MoviesOnlineController::getFolderSize($albumDir);
        $folderSizeText = $folderBytes > 0 ? MoviesOnlineController::formatBytes($folderBytes) : '-';

        // Kategorien aus photo_albums_categories laden
        $selectedSlaves = array_values(array_filter(array_map('trim', explode(',', (string)($ao->category_slave ?? '')))));
        $categoriesByType = [
            'number_of_people' => [],
            'look_and_body'    => [],
            'fetish'           => [],
            'porn'             => [],
        ];
        $searchHaystack = mb_strtolower($title . ' ' . strip_tags($description), 'UTF-8');
        $rsCats = p4c_query("SELECT * FROM `photo_albums_categories` ORDER BY `category_group` ASC, `name_id` ASC;", __FILE__, __LINE__);
        if ($rsCats && p4c_num_rows($rsCats) > 0) {
            while ($c = p4c_fetch_object($rsCats)) {
                $cGroup   = (string)($c->category_group ?? 'porn');
                $nameId   = (string)($c->name_id ?? '');
                $deName   = self::decodeText((string)($c->de_name_value ?? ''));
                $deText   = self::decodeText((string)($c->de_name_text ?? ''));
                $moreSw   = self::decodeText((string)($c->more_search_words ?? ''));

                $isSelected = in_array($nameId, $selectedSlaves, true);
                if (!$isSelected && $cGroup === 'look_and_body' && in_array($nameId, $currentActorCategories, true)) {
                    $isSelected = true;
                }

                $inText = false;
                if ($nameId !== '' && mb_strpos($searchHaystack, mb_strtolower($nameId, 'UTF-8')) !== false) {
                    $inText = true;
                } elseif ($deName !== '' && mb_strpos($searchHaystack, mb_strtolower($deName, 'UTF-8')) !== false) {
                    $inText = true;
                } elseif ($moreSw !== '') {
                    foreach (explode(',', $moreSw) as $sw) {
                        $swTrim = trim(mb_strtolower($sw, 'UTF-8'));
                        if ($swTrim !== '' && mb_strpos($searchHaystack, $swTrim) !== false) {
                            $inText = true;
                            break;
                        }
                    }
                }

                if (!isset($categoriesByType[$cGroup])) {
                    $categoriesByType[$cGroup] = [];
                }
                $categoriesByType[$cGroup][] = [
                    'name_id'  => $nameId,
                    'de'       => $deName,
                    'de_text'  => $deText,
                    'selected' => $isSelected,
                    'in_text'  => $inText,
                ];
            }
        }

        // Ablehnungs-Historie laden
        $rejectionHistory = [];
        $rsHist = p4c_query(
            "SELECT h.*, r.`de_short`
             FROM `rejection_reason_photo_album_history` h
             LEFT JOIN `rejection_reason_photo_album` r ON r.`id_name` = h.`id_name`
             WHERE h.`album_id` = '" . p4c_escape_string($albumIdHash) . "'
             ORDER BY h.`datetime` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsHist && p4c_num_rows($rsHist) > 0) {
            $histFallbacks = [
                'online_at_too_far' => 'Veröffentlichungsdatum zu weit in der Zukunft',
                'free_text'         => 'Sonstiges / Mehrere Gründe (Freitext)',
            ];
            while ($h = p4c_fetch_object($rsHist)) {
                $histIdName = (string)($h->id_name ?? '');
                $histShort  = self::decodeText((string)($h->de_short ?? ''));
                if ($histShort === '') {
                    $histShort = $histFallbacks[$histIdName] ?? ($histIdName !== '' ? $histIdName : 'Ablehnung');
                }
                $rejectionHistory[] = [
                    'id_name'     => $histIdName,
                    'short_title' => $histShort,
                    'text'        => self::decodeText((string)($h->text ?? '')),
                    'datetime'    => (string)($h->datetime ?? ''),
                ];
            }
        }

        // 18+ Mitwirkende / ID-Shot Daten laden
        $performersData = [
            'performer_mode'     => 'solo_owner',
            'merchant_full_name' => $merchantUsername,
            'persons'            => [],
        ];
        if (class_exists('CoActorManager')) {
            $perfRaw = \CoActorManager::getContentPerformers('photo_album', $albumDbId, $merchantId);
            $performersData['performer_mode']     = (string)($perfRaw['performer_mode'] ?? 'solo_owner');
            $performersData['merchant_full_name'] = self::decodeText((string)($perfRaw['merchant_full_name'] ?? $merchantUsername));

            foreach ($perfRaw['performers'] ?? [] as $p) {
                $performersData['persons'][] = [
                    'id'                  => abs((int)($p['id'] ?? 0)),
                    'is_owner'            => !empty($p['is_owner']),
                    'display_label'       => self::decodeText((string)($p['display_label'] ?? '')),
                    'birth_date_de'       => (string)($p['birth_date_de'] ?? ''),
                    'age'                 => $p['age'] ?? null,
                    'id_doc_number'       => self::decodeText((string)($p['id_doc_number'] ?? '')),
                    'city'                => self::decodeText((string)($p['city'] ?? '')),
                    'checked_status'      => (string)($p['checked_status'] ?? 'pending_qr'),
                    'status_label'        => self::decodeText((string)($p['status_label'] ?? '')),
                    'is_ready_for_use'    => !empty($p['is_ready_for_use']),
                    'ai_face_ready'       => !empty($p['ai_face_ready']),
                    'verification_method' => (string)($p['verification_method'] ?? ''),
                    'rejection_reason'    => self::decodeText((string)($p['rejection_reason'] ?? '')),
                    'face_ref_url'        => (string)($p['face_ref_url'] ?? ''),
                    'id_shot_url'         => (string)($p['id_shot_url'] ?? ''),
                    'id_front_url'        => (string)($p['id_front_url'] ?? ''),
                    'id_back_url'         => (string)($p['id_back_url'] ?? ''),
                    'certificate_url'     => (string)($p['certificate_url'] ?? ''),
                    'has_uploaded_docs'   => !empty($p['has_uploaded_docs']),
                    'has_id_shot'         => !empty($p['has_id_shot']),
                    'checked_on'          => (string)($p['checked_on'] ?? ''),
                ];
            }
        }

        // BRAM-Textanalyse
        $env = ['llm_ready' => false];
        $bramReport = [
            'compliance_status' => 'ok',
            'risk_score'        => 0,
            'summary_de'        => 'Alle geprüften Album-Texte (Albumtitel, Beschreibung, SEO-Daten) sind unauffällig. Es wurden keine verbotenen Begriffe oder Verstöße gegen die Visa/Mastercard BRAM-Richtlinien festgestellt.',
            'violations'        => [],
            'engine_used'       => 'BRAM-Regel-Engine (JSON v1.1)',
            'updated_at'        => date('d.m.Y H:i:s'),
        ];
        if (function_exists('aic_analyze_texts')) {
            $bramReport = aic_analyze_texts([
                'title'            => $title,
                'description'      => $description,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
            ], [], '', $env, 'photo_album');
            $bramReport['updated_at'] = date('d.m.Y H:i:s');
        }

        $onlineAtRaw = (string)($ao->online_at ?? '');
        $onlineAtTs  = ($onlineAtRaw !== '' && $onlineAtRaw !== '0000-00-00 00:00:00' && $onlineAtRaw !== '000-00-00 00:00:00')
            ? strtotime($onlineAtRaw)
            : false;
        $onlineAtFormatted = $onlineAtTs !== false
            ? date('Y-m-d\TH:i', $onlineAtTs)
            : date('Y-m-d\TH:i');
        $onlineAtTooFar    = function_exists('ec_is_online_at_too_far_in_future') && ec_is_online_at_too_far_in_future($onlineAtRaw);
        $maxOnlineAtInput  = function_exists('ec_get_max_online_at_input') ? ec_get_max_online_at_input() : date('Y-m-d\T23:59', strtotime('+12 months'));
        $maxOnlineAtDe     = function_exists('ec_get_max_online_at_timestamp') ? date('d.m.Y', ec_get_max_online_at_timestamp()) : date('d.m.Y', strtotime('+12 months'));

        return View::render('PhotoAlbumsOnline', 'edit', [
            'album' => [
                'online_id'            => $onlineId,
                'album_db_id'          => $albumDbId,
                'album_id'             => $albumIdHash,
                'merchant_id'          => $merchantId,
                'merchant_partner_id'  => $merchantPartnerId,
                'merchant_username'    => $merchantUsername,
                'actor_id'             => $actorId,
                'actor_name'           => $currentActorName,
                'storage_location'     => $storageLoc,
                'folder_size_text'     => $folderSizeText,
                'number_of_photos'     => $totalPhotosCount,
                'rejected_photos'      => $rejectedPhotosCount,
                'title'                => $title,
                'description'          => $description,
                'meta_title'           => $metaTitle,
                'meta_description'     => $metaDesc,
                'seo_url'              => $seoUrl,
                'category_master'      => (string)($ao->category_master ?? 'porn') ?: 'porn',
                'selected_slaves'      => $selectedSlaves,
                'online_at_input'      => $onlineAtFormatted,
                'online_at_raw'        => $onlineAtRaw,
                'online_at_too_far'    => $onlineAtTooFar,
                'max_online_at_input'  => $maxOnlineAtInput,
                'max_online_at_de'     => $maxOnlineAtDe,
                'amount_download'      => abs((int)($ao->amount_download ?? 0)),
                'views'                => abs((int)($ao->views ?? 0)),
                'purchases'            => abs((int)($ao->purchases ?? 0)),
                'access_count'         => $accessCount,
                'effective_purchases'  => $effectivePurchases,
                'last_access_buy'      => $lastAccessBuy,
                'status'               => (string)($ao->status ?? 'active'),
                'deleted_datetime'     => (string)($ao->deleted_datetime ?? $pa->deleted_datetime ?? ''),
                'admin_infos'          => $adminInfos,
                'create_datetime'      => (string)($ao->create_datetime ?? $pa->create_datetime ?? ''),
                'album_checked'        => (string)($ao->album_checked ?? ''),
                'released_datetime'    => (string)($ao->released_datetime ?? $pa->released_datetime ?? ''),
                'released_from_name'   => $releasedFromName,
                'last_updated_date'    => (string)($ao->last_updated_datetime ?? $pa->last_updated_datetime ?? ''),
                'last_updated_by_name' => $lastUpdatedByName,
                'checksum'             => (string)($ao->checksum ?? ''),
            ],
            'prevOnlineId'     => $prevOnlineId,
            'nextOnlineId'     => $nextOnlineId,
            'duplicateAlbum'   => $duplicateAlbum,
            'photos'           => $photos,
            'merchantActors'   => $merchantActors,
            'categoriesByType' => $categoriesByType,
            'rejectionHistory' => $rejectionHistory,
            'performersData'   => $performersData,
            'bramReport'       => $bramReport,
            'employeeRule'     => (string)($_SESSION['employee_rule'] ?? ''),
        ]);
    }

    /**
     * 4. AJAX-Aktion: Änderungen an einem veröffentlichten Fotoalbum speichern (?route=photo-albums-online/save)
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $employeeId = abs((int)($_SESSION['employee_id'] ?? 0));
        $onlineId   = abs((int)$this->request->post('online_id', 0));
        $albumHash  = trim((string)$this->request->post('album_id', ''));

        if ($onlineId <= 0 && $albumHash === '') {
            Response::error('Ungültige Fotoalbum-ID.', 400);
            return;
        }

        $whereAo = $onlineId > 0
            ? "`id` = '" . $onlineId . "'"
            : "`album_id` = '" . p4c_escape_string($albumHash) . "'";

        $rsAo = p4c_query("SELECT * FROM `photo_albums_online` WHERE " . $whereAo . " LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAo || p4c_num_rows($rsAo) === 0) {
            Response::error('Das Online-Fotoalbum wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $ao          = p4c_fetch_object($rsAo);
        $onlineId    = abs((int)$ao->id);
        $albumIdHash = (string)$ao->album_id;
        $merchantId  = abs((int)$ao->merchant_id);

        $errors = [];

        // 1. Darsteller validieren
        $actorId = abs((int)$this->request->post('actor_id', $ao->actor_id));
        $rsActor = p4c_query(
            "SELECT `id` FROM `actors` WHERE `id` = '" . $actorId . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            $errors[] = 'Bitte wähle den Hauptdarsteller für dieses Fotoalbum aus.';
        }

        // 2. Hauptkategorie & Unterkategorien validieren
        $categoryMaster = trim((string)$this->request->post('category_master', 'porn'));
        if (!in_array($categoryMaster, ['porn', 'fetish'], true)) {
            $errors[] = 'Bitte wähle eine Hauptkategorie (Porno oder Fetisch) aus.';
        }

        $rawCategories = $this->request->post('category_slave', []);
        if (!is_array($rawCategories)) {
            $rawCategories = [];
        }
        $validCategories = [];
        $rsValidCats = p4c_query("SELECT `name_id` FROM `photo_albums_categories`;", __FILE__, __LINE__);
        $allowedCatSet = [];
        if ($rsValidCats) {
            while ($vc = p4c_fetch_object($rsValidCats)) {
                $allowedCatSet[(string)$vc->name_id] = true;
            }
        }
        foreach ($rawCategories as $catKey) {
            $catKeyClean = trim((string)$catKey);
            if ($catKeyClean !== '' && isset($allowedCatSet[$catKeyClean])) {
                $validCategories[] = $catKeyClean;
            }
        }
        $validCategories = array_values(array_unique($validCategories));
        if (empty($validCategories)) {
            $errors[] = 'Bitte wähle alle Kategorien aus, die zum Fotoalbum passen.';
        }

        // 3. Titel & Beschreibung bereinigen + Duplikat-Prüfung (gelöschte Alben ignorieren)
        $requestedStatusPreview = trim((string)$this->request->post('album_status', $this->request->post('status', 'active')));
        $title = trim(str_replace(self::REPLACE_TITLE_CHARS, '', (string)$this->request->post('title', '')));
        $title = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;'], 'EUR', $title);
        $title = trim(strip_tags($title));
        if ($title === '') {
            $errors[] = 'Bitte gib einen aussagekräftigen Titel für das Fotoalbum an.';
        } elseif (mb_strlen($title, 'UTF-8') > 65) {
            $errors[] = 'Der Albumtitel darf maximal 65 Zeichen lang sein.';
        } elseif ($requestedStatusPreview !== 'deleted') {
            $rsDup = p4c_query(
                "SELECT `id` FROM `photo_albums_online`
                 WHERE `title` = '" . p4c_escape_string($title) . "'
                   AND `status` != 'deleted'
                   AND `id` != '" . $onlineId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if ($rsDup && p4c_num_rows($rsDup) > 0) {
                $dupId = abs((int)p4c_fetch_object($rsDup)->id);
                $errors[] = 'Es existiert bereits ein Online-Fotoalbum mit diesem Titel (Online-ID #' . $dupId . '). Bitte wähle einen eindeutigen Titel.';
            }
        }

        $description = self::decodeDescription((string)$this->request->post('description', ''));
        $description = str_replace('€', 'EUR', $description);
        if ($description === '') {
            $errors[] = 'Bitte gib eine aussagekräftige Beschreibung für das Fotoalbum an.';
        }

        // 4. Online-ab Datum & Preis
        $onlineAtRaw = trim((string)$this->request->post('online_at', ''));
        $onlineAtTs  = $onlineAtRaw !== '' ? strtotime($onlineAtRaw) : false;
        $onlineAtSql = $onlineAtTs !== false ? date('Y-m-d H:i:s', $onlineAtTs) : (string)$ao->online_at;

        $amountDownload = abs((int)$this->request->post('amount_download', 0));
        if ($amountDownload > 5000) {
            $errors[] = 'Der Album-Preis darf maximal 50,00 EUR (5.000 Cent) betragen.';
        }

        // 5. SEO-Felder
        $metaTitle = trim(strip_tags(str_replace(self::REPLACE_TITLE_CHARS, '', (string)$this->request->post('meta_title', ''))));
        $metaTitle = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;', "\n", "\r"], ['EUR', 'EUR', 'EUR', 'EUR', 'EUR', 'EUR', '', ''], $metaTitle);
        if ($metaTitle === '') {
            $metaTitle = mb_substr($title, 0, 65, 'UTF-8');
        } else {
            $metaTitle = mb_substr($metaTitle, 0, 65, 'UTF-8');
        }

        $metaDesc = trim(strip_tags(str_replace(["\n", "\r"], ' ', (string)$this->request->post('meta_description', ''))));
        if ($metaDesc === '') {
            $metaDesc = mb_substr(trim((string)preg_replace('/\s+/u', ' ', $description)), 0, 156, 'UTF-8');
        } else {
            $metaDesc = mb_substr($metaDesc, 0, 156, 'UTF-8');
        }

        $seoUrlRaw = trim((string)$this->request->post('seo_url', ''));
        $seoUrl = function_exists('seo_url')
            ? (string)seo_url($seoUrlRaw !== '' ? $seoUrlRaw : $title)
            : strtolower((string)preg_replace('/[^a-zA-Z0-9-]+/', '-', $seoUrlRaw !== '' ? $seoUrlRaw : $title));

        // 6. Status & Admin-Infos
        $albumStatus = trim((string)$this->request->post('album_status', $this->request->post('status', 'active')));
        if (!in_array($albumStatus, ['active', 'blocked', 'deleted'], true)) {
            $errors[] = 'Ungültiger Album-Status ausgewählt.';
        } elseif (
            $albumStatus === 'deleted'
            && (string)($_SESSION['employee_rule'] ?? '') !== 'write'
            && (string)($ao->status ?? '') !== 'deleted'
        ) {
            $errors[] = 'Du besitzt keine Schreib-/Löschberechtigung (Administrator-Status), um Fotoalben auf den Status „Gelöscht“ zu setzen.';
        }
        if ($albumStatus === 'active' && function_exists('ec_validate_online_at_future')) {
            $toCheckOnlineAt = $onlineAtRaw !== '' ? $onlineAtRaw : (string)($ao->online_at ?? '');
            $dateCheck       = ec_validate_online_at_future($toCheckOnlineAt, 'Fotoalbum', true);
            if (!empty($dateCheck['too_far'])) {
                $errors[] = $dateCheck['error'];
            }
        }
        $adminInfos = trim(strip_tags((string)$this->request->post('admin_infos', '')));

        // Fotos zählen & prüfen ob abgelehnte Fotos vorhanden sind
        $rsPhotosCnt = p4c_query(
            "SELECT COUNT(*) AS `total_cnt`,
                    SUM(CASE WHEN `rejected` = '1' THEN 1 ELSE 0 END) AS `rej_cnt`
             FROM `photo_albums_photos`
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
            __FILE__,
            __LINE__
        );
        $cntObj         = $rsPhotosCnt ? p4c_fetch_object($rsPhotosCnt) : null;
        $numberOfPhotos = $cntObj ? abs((int)($cntObj->total_cnt ?? 0)) : abs((int)$ao->number_of_photos);
        $rejectedPhotos = $cntObj ? abs((int)($cntObj->rej_cnt ?? 0)) : 0;

        if ($rejectedPhotos > 0 && $albumStatus === 'active') {
            $errors[] = 'Im Fotoalbum sind aktuell ' . $rejectedPhotos . ' abgelehnte Einzelbilder markiert. Bitte gib die Fotos frei oder stelle den Album-Status auf „Gesperrt“.';
        }

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 422);
            return;
        }

        $categorySlaveStr = implode(',', $validCategories);
        $now              = date('Y-m-d H:i:s');
        $prevDeletedDt    = (string)($ao->deleted_datetime ?? '');
        $deletedDtSql     = ($albumStatus === 'deleted')
            ? (($prevDeletedDt !== '' && $prevDeletedDt !== '0000-00-00 00:00:00') ? $prevDeletedDt : $now)
            : '0000-00-00 00:00:00';

        $checksumData = [
            'merchant_id'         => $merchantId,
            'number_of_photos'    => $numberOfPhotos,
            'preview_image_fsk16' => (string)($ao->preview_image_fsk16 ?? ''),
            'preview_image_fsk18' => (string)($ao->preview_image_fsk18 ?? ''),
            'title'               => $title,
            'description'         => $description,
            'meta_title'          => $metaTitle,
            'meta_description'    => $metaDesc,
            'seo_url'             => $seoUrl,
            'online_at'           => $onlineAtSql,
            'amount_webmaster'    => abs((int)($ao->amount_webmaster ?? 0)),
            'amount_download'     => $amountDownload,
            'status'              => $albumStatus,
        ];
        $checksum = function_exists('photo_album_checksum') ? (string)photo_album_checksum($checksumData) : md5($albumIdHash . $now . $albumStatus);

        // 1. Update photo_albums_online (Soft-Delete belässt Zeile mit status='deleted' zur Synchronisation an EroCMS-Portale)
        p4c_query(
            "UPDATE `photo_albums_online` SET
                `actor_id`              = '" . $actorId . "',
                `number_of_photos`      = '" . $numberOfPhotos . "',
                `checksum`              = '" . p4c_escape_string($checksum) . "',
                `title`                 = '" . p4c_escape_string($title) . "',
                `description`           = '" . p4c_escape_string($description) . "',
                `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                `online_at`             = '" . p4c_escape_string($onlineAtSql) . "',
                `amount_download`       = '" . $amountDownload . "',
                `album_checked`         = '" . $now . "',
                `status`                = '" . p4c_escape_string($albumStatus) . "',
                `deleted_datetime`      = '" . p4c_escape_string($deletedDtSql) . "',
                `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `id` = '" . $onlineId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        // 2. Update photo_albums
        p4c_query(
            "UPDATE `photo_albums` SET
                `actor_id`              = '" . $actorId . "',
                `number_of_photos`      = '" . $numberOfPhotos . "',
                `checksum`              = '" . p4c_escape_string($checksum) . "',
                `title`                 = '" . p4c_escape_string($title) . "',
                `description`           = '" . p4c_escape_string($description) . "',
                `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                `online_at`             = '" . p4c_escape_string($onlineAtSql) . "',
                `amount_download`       = '" . $amountDownload . "',
                `album_checked`         = '" . $now . "',
                `status`                = '" . p4c_escape_string($albumStatus) . "',
                `deleted_datetime`      = '" . p4c_escape_string($deletedDtSql) . "',
                `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('ec_invalidate_photo_album_api_cache')) {
            \ec_invalidate_photo_album_api_cache($albumIdHash);
        }

        if (function_exists('log_action')) {
            log_action('Online-Fotoalbum #' . $onlineId . ' ("' . $title . '") bearbeitet [Status: ' . $albumStatus . '].');
        }

        Response::success([
            'message'   => $albumStatus === 'deleted'
                ? 'Fotoalbum #' . $onlineId . ' wurde auf „Gelöscht“ (Soft-Delete mit Käuferschutz) gesetzt.'
                : 'Die Änderungen am Fotoalbum #' . $onlineId . ' wurden erfolgreich gespeichert.',
            'online_id' => $onlineId,
        ]);
    }

    /**
     * 5. AJAX-Aktion: Manuelle physische Endgültig-Löschung (Hard-Delete) durch einen Administrator (?route=photo-albums-online/hard-delete)
     *
     * Ausschließlich zulässig für Administratoren (employee_rule === 'write'):
     *  - Bei 0 Käufen: sofortige physische Entfernung aller Bilddateien (z. B. versehentliche Dubletten/Testalben).
     *  - Bei vorhandenen Käufen (> 0): nur mit expliziter Rechts-/Persönlichkeitsrechts-/DSGVO-Bestätigung + Pflichtbegründung.
     *  - In photo_albums_online und photo_albums verbleibt ein leerer Tombstone (status='deleted', 0 Fotos), damit angeschlossene
     *    EroCMS-Seiten das Löschsignal sicher erhalten; nach 30 Tagen räumt cronjobs/delete_photo_album.php den Tombstone ab.
     */
    public function hardDelete(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }
        if ((string)($_SESSION['employee_rule'] ?? '') !== 'write') {
            Response::error('Nur Administratoren mit voller Schreibberechtigung dürfen Fotoalben manuell endgültig physisch löschen.', 403);
            return;
        }

        $employeeId       = abs((int)($_SESSION['employee_id'] ?? 0));
        $onlineId         = abs((int)$this->request->post('online_id', 0));
        $albumHash        = trim((string)$this->request->post('album_id', ''));
        $forceLegalDelete = (string)$this->request->post('force_legal_delete', '0') === '1';
        $deleteReason     = trim(strip_tags((string)$this->request->post('delete_reason', '')));

        if ($onlineId <= 0 && $albumHash === '') {
            Response::error('Ungültige Fotoalbum-ID.', 400);
            return;
        }

        $whereAo = $onlineId > 0
            ? "`id` = '" . $onlineId . "'"
            : "`album_id` = '" . p4c_escape_string($albumHash) . "'";

        $rsAo = p4c_query("SELECT * FROM `photo_albums_online` WHERE " . $whereAo . " LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAo || p4c_num_rows($rsAo) === 0) {
            Response::error('Das Online-Fotoalbum wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $ao          = p4c_fetch_object($rsAo);
        $onlineId    = abs((int)$ao->id);
        $albumIdHash = (string)$ao->album_id;
        $merchantId  = abs((int)$ao->merchant_id);

        $rsPa      = p4c_query("SELECT * FROM `photo_albums` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' LIMIT 1;", __FILE__, __LINE__);
        $pa        = ($rsPa && p4c_num_rows($rsPa) > 0) ? p4c_fetch_object($rsPa) : null;
        $albumDbId = $pa ? abs((int)$pa->id) : 0;

        $rsAccCnt = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `photo_albums_access` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
            __FILE__,
            __LINE__
        );
        $accessCount        = ($rsAccCnt && p4c_num_rows($rsAccCnt) > 0) ? abs((int)(p4c_fetch_object($rsAccCnt)->cnt ?? 0)) : 0;
        $effectivePurchases = max(abs((int)($ao->purchases ?? 0)), $accessCount);

        if ($effectivePurchases > 0) {
            if (!$forceLegalDelete) {
                Response::error(
                    'Dieses Fotoalbum wurde bereits ' . $effectivePurchases . '-mal gekauft! Zum Schutz der Käufer ist nur Soft-Delete („Gelöscht“) erlaubt – es sei denn, du bestätigst ausdrücklich eine zwingende Löschung aus Rechts-/Persönlichkeitsrechts- oder Datenschutzgründen.',
                    422
                );
                return;
            }
            if (mb_strlen($deleteReason, 'UTF-8') < 5) {
                Response::error('Bitte gib bei einem bereits gekauften Fotoalbum zwingend den rechtlichen Löschgrund (mind. 5 Zeichen, z. B. Persönlichkeitsrecht / DSGVO) an.', 422);
                return;
            }
        }

        // 1. Physische Bilddateien und Verzeichnis vollständig löschen
        $storageLoc = trim((string)($pa->storage_location ?? $ao->storage_location ?? ''));
        if ($storageLoc === '') {
            $storageLoc = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';
        }
        $deletedFilesCount = 0;
        if ($albumDbId > 0 && $merchantId > 0 && defined('PHOTO_ALBUMS_PATH')) {
            $albumDir = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . trim($storageLoc, '/\\') . '/' . $merchantId . '/' . $albumDbId;
            if (is_dir($albumDir)) {
                $imagesDir = $albumDir . '/images';
                if (is_dir($imagesDir)) {
                    foreach ((array)@glob($imagesDir . '/*') as $f) {
                        if (is_string($f) && is_file($f) && @unlink($f)) {
                            $deletedFilesCount++;
                        }
                    }
                    @rmdir($imagesDir);
                }
                foreach ((array)@glob($albumDir . '/*') as $f) {
                    if (is_string($f) && is_file($f) && @unlink($f)) {
                        $deletedFilesCount++;
                    }
                }
                @rmdir($albumDir);
            }
        }

        // 2. Einzelfoto-Datensätze (und bei Rechts-/DSGVO-Zwangslöschung auch Kundenzugriffe) entfernen
        p4c_query("DELETE FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';", __FILE__, __LINE__);
        if ($effectivePurchases > 0 && $forceLegalDelete) {
            p4c_query("DELETE FROM `photo_albums_access` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';", __FILE__, __LINE__);
        }

        // 3. Synchronisations-Tombstone in photo_albums_online & photo_albums setzen
        $now       = date('Y-m-d H:i:s');
        $reasonTxt = $deleteReason !== '' ? $deleteReason : 'Ungekauftes Album manuell durch Admin gelöscht';
        $auditLine = '[ADMIN HARD-DELETE ' . $now . ' (Admin #' . $employeeId . ', Käufe: ' . $effectivePurchases . '): ' . $reasonTxt . ']';
        $prevInfos = trim((string)($ao->admin_infos ?? ''));
        $newInfos  = $prevInfos !== '' ? ($prevInfos . "\n" . $auditLine) : $auditLine;
        $tombstoneChecksum = md5($albumIdHash . '|hard_deleted|' . $now);

        p4c_query(
            "UPDATE `photo_albums_online` SET
                `status`                = 'deleted',
                `deleted_datetime`      = '" . $now . "',
                `number_of_photos`      = '0',
                `preview_image_fsk16`   = '',
                `preview_image_fsk18`   = '',
                `checksum`              = '" . p4c_escape_string($tombstoneChecksum) . "',
                `admin_infos`           = '" . p4c_escape_string($newInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `id` = '" . $onlineId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        p4c_query(
            "UPDATE `photo_albums` SET
                `status`                = 'deleted',
                `deleted_datetime`      = '" . $now . "',
                `number_of_photos`      = '0',
                `preview_image_fsk16`   = '',
                `preview_image_fsk18`   = '',
                `checksum`              = '" . p4c_escape_string($tombstoneChecksum) . "',
                `admin_infos`           = '" . p4c_escape_string($newInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('ec_invalidate_photo_album_api_cache')) {
            \ec_invalidate_photo_album_api_cache($albumIdHash);
        }

        if (function_exists('log_action')) {
            log_action('ADMIN HARD-DELETE Fotoalbum Online-ID #' . $onlineId . ' (Album-ID: ' . $albumIdHash . ', Dateien gelöscht: ' . $deletedFilesCount . ', Käufe: ' . $effectivePurchases . ', Grund: ' . $reasonTxt . ').');
        }

        Response::success([
            'message'   => 'Das Fotoalbum #' . $onlineId . ' und alle zugehörigen Bilddateien (' . $deletedFilesCount . ') wurden endgültig physisch gelöscht.',
            'deleted'   => true,
            'online_id' => $onlineId,
        ]);
    }

    private static function getCachedOnlineAlbumsStorageText(): string
    {
        $cacheDir  = (defined('TEMP_DIR') && is_dir((string)TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
        $cacheFile = $cacheDir . '/erocloud_online_albums_size_cache.txt';
        if (!is_file($cacheFile)) {
            return 'bitte neu berechnen';
        }
        $bytes = (int)@file_get_contents($cacheFile);
        return MoviesOnlineController::formatBytes($bytes);
    }

    private static function decodeDescription(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (function_exists('ec_html_to_plain_text')) {
            return \ec_html_to_plain_text($str);
        }
        if (!mb_check_encoding($str, 'UTF-8') && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
            if (is_string($converted)) {
                $str = $converted;
            }
        }
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($str, '&') !== false) {
            $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $str = str_replace(["\r\n", "\r"], "\n", $str);
        foreach ([
            '/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is' => '',
            '/<!--.*?-->/s' => '',
            '/<\s*br\s*\/?\s*>/i' => "\n",
            '/<\s*\/\s*li\s*>\s*<\s*li\b[^>]*>/i' => "\n- ",
            '/<\s*li\b[^>]*>/i' => "\n- ",
            '/<\s*\/\s*li\s*>/i' => "\n",
            '/<\s*\/\s*tr\s*>/i' => "\n",
            '/<\/\s*(?:p|div|h[1-6]|ul|ol|blockquote|pre|address|table|section|article)\s*>\s*<\s*(?:p|div|h[1-6]|ul|ol|blockquote|pre|address|table|section|article)\b[^>]*>/i' => "\n\n",
            '/<\/?\s*(?:p|div|h[1-6]|ul|ol|blockquote|pre|address|table|section|article|hr)\b[^>]*\/?>/i' => "\n\n",
            '/<\/?[a-zA-Z][a-zA-Z0-9:-]*(?:\s+[^<>]*)?\/?>/' => '',
        ] as $pattern => $replacement) {
            $res = preg_replace($pattern, $replacement, $str);
            if (is_string($res)) {
                $str = $res;
            }
        }
        $str = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], [' ', '', '', '', ''], $str);
        foreach (['/[ \t]{2,}/' => ' ', '/^[ \t]+|[ \t]+$/m' => '', "/\n{3,}/" => "\n\n"] as $pattern => $replacement) {
            $res = preg_replace($pattern, $replacement, $str);
            if (is_string($res)) {
                $str = $res;
            }
        }
        return trim($str);
    }

    private static function decodeText(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (function_exists('ec_decode_text')) {
            return \ec_decode_text($str);
        }
        if (!mb_check_encoding($str, 'UTF-8') && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
            if (is_string($converted)) {
                $str = $converted;
            }
        }
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($str, '&') !== false) {
            $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], [' ', '', '', '', ''], $str);
    }
}
