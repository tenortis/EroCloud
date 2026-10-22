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
                $desc      = self::decodeText((string)($row->description ?? ''));
                $metaTitle = self::decodeText((string)($row->meta_title ?? ''));
                $metaDesc  = self::decodeText((string)($row->meta_description ?? ''));

                // Live BRAM-Textanalyse für die angezeigte Seite
                $bramComp  = 'green';
                $bramScore = 0;
                $bramSum   = 'Unbedenklich';
                if (function_exists('aic_analyze_texts')) {
                    $rep = aic_analyze_texts([
                        'title'            => $title,
                        'description'      => $desc,
                        'meta_title'       => $metaTitle,
                        'meta_description' => $metaDesc,
                    ], [], '', $env);
                    $bramComp  = (string)($rep['compliance_status'] ?? 'green');
                    $bramScore = abs((int)($rep['risk_score'] ?? 0));
                    $bramSum   = (string)($rep['summary_de'] ?? '');
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
        $description = self::decodeText((string)($ao->description ?? ''));
        $metaTitle   = self::decodeText((string)($ao->meta_title ?? ''));
        $metaDesc    = self::decodeText((string)($ao->meta_description ?? ''));
        $seoUrl      = self::decodeText((string)($ao->seo_url ?? ''));
        $adminInfos  = self::decodeText((string)($ao->admin_infos ?? ''));

        // Duplikat-Prüfung (anderes Online-Album mit identischem Titel)
        $duplicateAlbum = null;
        if ($title !== '') {
            $rsDup = p4c_query(
                "SELECT `id`, `album_id`, `title`, `merchant_id`
                 FROM `photo_albums_online`
                 WHERE `title` = '" . p4c_escape_string((string)$ao->title) . "'
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

        // Alle Fotos des Albums laden
        $photos = [];
        $rejectedPhotosCount = 0;
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
                $photos[] = [
                    'id'       => abs((int)$ph->id),
                    'index'    => $idx++,
                    'file_id'  => (string)($ph->file_id ?? ''),
                    'filename' => (string)($ph->filename ?? ''),
                    'rejected' => $isRejected,
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
            while ($h = p4c_fetch_object($rsHist)) {
                $rejectionHistory[] = [
                    'id_name'     => (string)($h->id_name ?? ''),
                    'short_title' => self::decodeText((string)($h->de_short ?? $h->id_name ?? 'Ablehnung')),
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
                    'face_ref_url'        => (string)($p['face_ref_url'] ?? ''),
                    'id_shot_url'         => (string)($p['id_shot_url'] ?? ''),
                    'id_front_url'        => (string)($p['id_front_url'] ?? ''),
                    'certificate_url'     => (string)($p['certificate_url'] ?? ''),
                ];
            }
        }

        // BRAM-Textanalyse
        $env = function_exists('aic_check_environment') ? aic_check_environment() : ['llm_ready' => false];
        $bramReport = [
            'compliance_status' => 'green',
            'risk_score'        => 0,
            'summary_de'        => 'Keine verbotenen Begriffe gefunden.',
            'violations'        => [],
            'engine_used'       => 'keyword_matrix',
        ];
        if (function_exists('aic_analyze_texts')) {
            $bramReport = aic_analyze_texts([
                'title'            => $title,
                'description'      => $description,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
            ], [], '', $env);
        }

        $onlineAtRaw = (string)($ao->online_at ?? '');
        $onlineAtFormatted = ($onlineAtRaw !== '' && $onlineAtRaw !== '0000-00-00 00:00:00')
            ? date('Y-m-d\TH:i', strtotime($onlineAtRaw))
            : date('Y-m-d\TH:i');

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
                'amount_download'      => abs((int)($ao->amount_download ?? 0)),
                'views'                => abs((int)($ao->views ?? 0)),
                'purchases'            => abs((int)($ao->purchases ?? 0)),
                'status'               => (string)($ao->status ?? 'active'),
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

        // 3. Titel & Beschreibung bereinigen + Duplikat-Prüfung
        $title = trim(str_replace(self::REPLACE_TITLE_CHARS, '', (string)$this->request->post('title', '')));
        $title = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;'], 'EUR', $title);
        $title = trim(strip_tags($title));
        if ($title === '') {
            $errors[] = 'Bitte gib einen aussagekräftigen Titel für das Fotoalbum an.';
        } elseif (mb_strlen($title, 'UTF-8') > 65) {
            $errors[] = 'Der Albumtitel darf maximal 65 Zeichen lang sein.';
        } else {
            $rsDup = p4c_query(
                "SELECT `id` FROM `photo_albums_online`
                 WHERE `title` = '" . p4c_escape_string($title) . "'
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

        $allowedTags = '<ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><p><br><a><b><i>';
        $description = trim(strip_tags((string)$this->request->post('description', ''), $allowedTags));
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
            $metaDesc = mb_substr(trim(strip_tags($description)), 0, 156, 'UTF-8');
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
        ];
        $checksum = function_exists('photo_album_checksum') ? (string)photo_album_checksum($checksumData) : md5($albumIdHash . $now);

        // 1. Update photo_albums_online
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
                `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('log_action')) {
            log_action('Online-Fotoalbum #' . $onlineId . ' ("' . $title . '") bearbeitet [Status: ' . $albumStatus . '].');
        }

        Response::success([
            'message'   => 'Die Änderungen am Fotoalbum #' . $onlineId . ' wurden erfolgreich gespeichert.',
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

    private static function decodeText(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (function_exists('utf8_to_latin1swedishci')) {
            $converted = (string)utf8_to_latin1swedishci($str);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        if (!mb_check_encoding($str, 'UTF-8')) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
        }
        return $str;
    }
}
