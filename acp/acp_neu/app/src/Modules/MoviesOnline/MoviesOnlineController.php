<?php
declare(strict_types=1);

namespace App\Modules\MoviesOnline;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) - MoviesOnlineController
 * Steuert die Film-Gesamtübersicht (#/movies-online), die Einzelbearbeitung (#/movie-edit?id=...),
 * Filme in Planung (#/movies-drafts) sowie abgelehnte/gesperrte Filme (#/movies-blocked).
 */
final class MoviesOnlineController
{
    private Request $request;

    /** @var array<string, string> */
    private const LANGUAGE_LABELS = [
        'de' => 'Deutsch',
        'en' => 'Englisch',
        'fr' => 'Französisch',
        'es' => 'Spanisch',
        'nl' => 'Niederländisch',
        'ru' => 'Russisch',
        'pl' => 'Polnisch',
    ];

    /** @var array<string, string> */
    private const QUALITY_LABELS = [
        'sd'  => 'SD',
        'hd'  => 'HD (720p)',
        'fhd' => 'Full HD (1080p)',
        '2k'  => '2K',
        '4k'  => '4K',
        '6k'  => '6K',
    ];

    /** @var array<int, string> */
    private const REPLACE_TITLE_CHARS = ['°', '^', '²', '§', '$', '%', '{', '[', ']', '}', '´', '`', '~', "'", '_', ';', '<', '>'];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 1. Gesamtübersicht aller veröffentlichten Filme (#/movies-online)
     */
    public function list(): string
    {
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        self::ensureIndexesAndTables();

        // Filter- & Paginierungs-Parameter
        $search  = trim((string)$this->request->get('q', ''));
        $status  = trim((string)$this->request->get('status', 'all'));
        $cat     = trim((string)$this->request->get('cat', 'all'));
        $quality = trim((string)$this->request->get('quality', 'all'));
        $lang    = trim((string)$this->request->get('lang', 'all'));
        $bram    = trim((string)$this->request->get('bram', 'all'));
        $sort    = trim((string)$this->request->get('sort', 'id_desc'));
        $page    = max(1, abs((int)$this->request->get('page', 1)));
        $perPage = abs((int)$this->request->get('per_page', 100));
        if (!in_array($perPage, [50, 100, 250, 500], true)) {
            $perPage = 100;
        }

        // 1. Globale KPIs & Verkäufe aggregieren
        $kpis = [
            'total'          => 0,
            'active'         => 0,
            'blocked'        => 0,
            'deleted'        => 0,
            'porn'           => 0,
            'fetish'         => 0,
            'bram_green'     => 0,
            'bram_attention' => 0,
            'total_buys'     => 0,
            'total_revenue'  => 0,
            'storage_text'   => self::getCachedOnlineStorageText(),
        ];

        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_cnt`,
                SUM(CASE WHEN mo.`status` = 'active' THEN 1 ELSE 0 END) AS `active_cnt`,
                SUM(CASE WHEN mo.`status` = 'blocked' THEN 1 ELSE 0 END) AS `blocked_cnt`,
                SUM(CASE WHEN mo.`status` = 'deleted' THEN 1 ELSE 0 END) AS `deleted_cnt`,
                SUM(CASE WHEN mo.`category_master` = 'porn' THEN 1 ELSE 0 END) AS `porn_cnt`,
                SUM(CASE WHEN mo.`category_master` = 'fetish' THEN 1 ELSE 0 END) AS `fetish_cnt`,
                SUM(CASE WHEN ai.`text_check_status` = 2 AND ai.`text_compliance` IN ('ok', 'green') THEN 1 ELSE 0 END) AS `bram_green_cnt`,
                SUM(CASE WHEN ai.`text_check_status` = 2 AND ai.`text_compliance` IN ('warning', 'violation', 'yellow', 'red') THEN 1 ELSE 0 END) AS `bram_att_cnt`
             FROM `movies_online` mo
             LEFT JOIN `movies` m ON m.`file_id` = mo.`file_id`
             LEFT JOIN `movies_ai_analysis` ai ON ai.`movie_id` = m.`id`;",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && p4c_num_rows($rsKpi) > 0) {
            $kRow = p4c_fetch_object($rsKpi);
            $kpis['total']          = abs((int)($kRow->total_cnt ?? 0));
            $kpis['active']         = abs((int)($kRow->active_cnt ?? 0));
            $kpis['blocked']        = abs((int)($kRow->blocked_cnt ?? 0));
            $kpis['deleted']        = abs((int)($kRow->deleted_cnt ?? 0));
            $kpis['porn']           = abs((int)($kRow->porn_cnt ?? 0));
            $kpis['fetish']         = abs((int)($kRow->fetish_cnt ?? 0));
            $kpis['bram_green']     = abs((int)($kRow->bram_green_cnt ?? 0));
            $kpis['bram_attention'] = abs((int)($kRow->bram_att_cnt ?? 0));
        }

        // Käufe & Umsatz pro file_id vorab aggregieren
        $buysByFileId = [];
        $rsBuysAgg = p4c_query(
            "SELECT `movie_id`, COUNT(*) AS `buy_cnt`, COALESCE(SUM(`movie_price`), 0) AS `rev_sum`
             FROM `movies_access`
             GROUP BY `movie_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsBuysAgg && p4c_num_rows($rsBuysAgg) > 0) {
            while ($bRow = p4c_fetch_object($rsBuysAgg)) {
                $fid  = (string)($bRow->movie_id ?? '');
                $bcnt = abs((int)($bRow->buy_cnt ?? 0));
                $brev = abs((int)round((float)($bRow->rev_sum ?? 0)));
                if ($fid !== '') {
                    $buysByFileId[$fid] = [
                        'count'   => $bcnt,
                        'revenue' => $brev,
                    ];
                    $kpis['total_buys']    += $bcnt;
                    $kpis['total_revenue'] += $brev;
                }
            }
        }

        // 2. WHERE-Bedingungen für Filterung aufbauen
        $where = ['1=1'];
        if (in_array($status, ['active', 'blocked', 'deleted'], true)) {
            $where[] = "mo.`status` = '" . p4c_escape_string($status) . "'";
        }
        if (in_array($cat, ['porn', 'fetish'], true)) {
            $where[] = "mo.`category_master` = '" . p4c_escape_string($cat) . "'";
        }
        if (isset(self::QUALITY_LABELS[$quality])) {
            $where[] = "mo.`quality` = '" . p4c_escape_string($quality) . "'";
        }
        if (isset(self::LANGUAGE_LABELS[$lang])) {
            $where[] = "mo.`movie_language` = '" . p4c_escape_string($lang) . "'";
        }
        if ($bram === 'green') {
            $where[] = "(ai.`text_check_status` = 2 AND ai.`text_compliance` IN ('ok', 'green'))";
        } elseif ($bram === 'attention') {
            $where[] = "(ai.`text_check_status` = 2 AND ai.`text_compliance` IN ('warning', 'violation', 'yellow', 'red'))";
        } elseif ($bram === 'unchecked') {
            $where[] = "(ai.`text_check_status` IS NULL OR ai.`text_check_status` != 2)";
        }

        if ($search !== '') {
            $escSearch = p4c_escape_string($search);
            $searchConds = [
                "mo.`title` LIKE '%" . $escSearch . "%'",
                "mo.`description` LIKE '%" . $escSearch . "%'",
                "mo.`file_id` LIKE '%" . $escSearch . "%'",
                "a.`username` LIKE '%" . $escSearch . "%'",
            ];
            $cleanNum = ltrim($search, '#');
            if ($cleanNum !== '' && ctype_digit($cleanNum)) {
                $numSearch = abs((int)$cleanNum);
                $searchConds[] = "mo.`id` = '" . $numSearch . "'";
                $searchConds[] = "m.`id` = '" . $numSearch . "'";
                $searchConds[] = "mo.`merchant_id` = '" . $numSearch . "'";
                $searchConds[] = "mo.`actor_id` = '" . $numSearch . "'";
            }
            $where[] = '(' . implode(' OR ', $searchConds) . ')';
        }

        $whereSql = implode(' AND ', $where);

        // Sortierung
        $orderBySql = 'mo.`id` DESC';
        if ($sort === 'id_asc') {
            $orderBySql = 'mo.`id` ASC';
        } elseif ($sort === 'online_desc') {
            $orderBySql = 'mo.`online_at` DESC, mo.`id` DESC';
        } elseif ($sort === 'checked_desc') {
            $orderBySql = 'mo.`movie_checked` DESC, mo.`id` DESC';
        } elseif ($sort === 'risk_desc') {
            $orderBySql = 'COALESCE(ai.`text_risk_score`, 0) DESC, mo.`id` DESC';
        } elseif ($sort === 'title_asc') {
            $orderBySql = 'mo.`title` ASC';
        }

        // Gefilterte Gesamtanzahl ermitteln
        $rsCount = p4c_query(
            "SELECT COUNT(*) AS `cnt`
             FROM `movies_online` mo
             LEFT JOIN `actors` a ON a.`id` = mo.`actor_id` AND a.`merchant_id` = mo.`merchant_id`
             LEFT JOIN `movies` m ON m.`file_id` = mo.`file_id`
             LEFT JOIN `movies_ai_analysis` ai ON ai.`movie_id` = m.`id`
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

        $rsMovies = p4c_query(
            "SELECT
                mo.`id`, mo.`file_id`, mo.`quality`, mo.`merchant_id`, mo.`actor_id`, mo.`checksum`,
                mo.`title`, mo.`description`, mo.`online_at`, mo.`movie_language`, mo.`movie_checked`,
                mo.`status`, mo.`category_master`, mo.`playtime_seconds`, mo.`playtime_string`,
                mo.`amount_second`, mo.`as_download`, mo.`amount_download`, mo.`resolution`,
                mo.`preview_image_fsk16`, mo.`preview_image_fsk18`,
                m.`id` AS `movie_db_id`,
                a.`username` AS `actor_username`,
                ai.`subtitle_status`, ai.`text_check_status`, ai.`text_compliance`, ai.`text_risk_score`, ai.`text_summary`
             FROM `movies_online` mo
             LEFT JOIN `actors` a ON a.`id` = mo.`actor_id` AND a.`merchant_id` = mo.`merchant_id`
             LEFT JOIN `movies` m ON m.`file_id` = mo.`file_id`
             LEFT JOIN `movies_ai_analysis` ai ON ai.`movie_id` = m.`id`
             WHERE " . $whereSql . "
             ORDER BY " . $orderBySql . "
             LIMIT " . $offset . ", " . $perPage . ";",
            __FILE__,
            __LINE__
        );

        $movies = [];
        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($row = p4c_fetch_object($rsMovies)) {
                $onlineId   = abs((int)$row->id);
                $movieDbId  = abs((int)($row->movie_db_id ?? 0));
                $fileId     = (string)($row->file_id ?? '');
                $playtime   = abs((int)($row->playtime_seconds ?? 0));
                $amountSec  = (float)($row->amount_second ?? 0);
                $totalCoins = (int)round($playtime * $amountSec);
                $qKey       = strtolower(trim((string)($row->quality ?? '')));

                $buyInfo = $buysByFileId[$fileId] ?? ['count' => 0, 'revenue' => 0];

                $movies[] = [
                    'id'              => $onlineId,
                    'movie_db_id'     => $movieDbId,
                    'file_id'         => $fileId,
                    'merchant_id'     => abs((int)$row->merchant_id),
                    'actor_id'        => abs((int)$row->actor_id),
                    'actor_name'      => self::decodeText((string)($row->actor_username ?? '')),
                    'title'           => self::decodeText((string)($row->title ?? '')),
                    'description'     => self::decodeText((string)($row->description ?? '')),
                    'status'          => (string)($row->status ?? 'active'),
                    'quality_key'     => $qKey,
                    'quality_label'   => self::QUALITY_LABELS[$qKey] ?? strtoupper($qKey ?: '-'),
                    'resolution'      => (string)($row->resolution ?? ''),
                    'playtime_str'    => (string)($row->playtime_string ?? gmdate('H:i:s', $playtime)),
                    'total_coins'     => $totalCoins,
                    'amount_second'   => number_format($amountSec, 2, ',', '.'),
                    'as_download'     => abs((int)($row->as_download ?? 0)) === 1,
                    'language_key'    => (string)($row->movie_language ?? 'de'),
                    'language_label'  => self::LANGUAGE_LABELS[(string)($row->movie_language ?? 'de')] ?? (string)($row->movie_language ?? '-'),
                    'category_master' => (string)($row->category_master ?? 'porn'),
                    'movie_checked'   => (string)($row->movie_checked ?? ''),
                    'online_at'       => (string)($row->online_at ?? ''),
                    'fsk16'           => abs((int)($row->preview_image_fsk16 ?? 1)),
                    'fsk18'           => abs((int)($row->preview_image_fsk18 ?? 1)),
                    'buys_count'      => $buyInfo['count'],
                    'buys_revenue'    => $buyInfo['revenue'],
                    'bram_status'     => abs((int)($row->text_check_status ?? 0)),
                    'bram_compliance' => (string)($row->text_compliance ?? 'pending'),
                    'bram_score'      => abs((int)($row->text_risk_score ?? 0)),
                    'bram_summary'    => self::decodeText((string)($row->text_summary ?? '')),
                    'subtitle_status' => abs((int)($row->subtitle_status ?? 0)),
                ];
            }
        }

        // Navigations-Zähler für den oberen Content-Switcher
        $switcherCounts = self::getContentSwitcherCounts($kpis['total']);

        return View::render('MoviesOnline', 'list', [
            'movies'         => $movies,
            'kpis'           => $kpis,
            'switcherCounts' => $switcherCounts,
            'filters'        => [
                'q'          => $search,
                'status'     => $status,
                'cat'        => $cat,
                'quality'    => $quality,
                'lang'       => $lang,
                'bram'       => $bram,
                'sort'       => $sort,
                'page'       => $page,
                'per_page'   => $perPage,
                'total_rows' => $filteredTotal,
                'total_page' => $totalPages,
            ],
            'languages'      => self::LANGUAGE_LABELS,
            'qualities'      => self::QUALITY_LABELS,
        ]);
    }

    /**
     * 2. AJAX-Aktion: Speicherplatz aller Online-Filme neu berechnen (?route=movies-online/recalc-size)
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
            "SELECT m.`id`, m.`merchant_id`, m.`storage_location`
             FROM `movies_online` mo
             INNER JOIN `movies` m ON mo.`file_id` = m.`file_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
            while ($mRow = p4c_fetch_object($rsOnline)) {
                $folder = rtrim((string)MOVIES_PATH, '/\\') . '/'
                    . (string)$mRow->storage_location . '/'
                    . abs((int)$mRow->merchant_id) . '/'
                    . abs((int)$mRow->id);
                $totalBytes += self::getFolderSize($folder);
            }
        }

        $cacheDir  = (defined('TEMP_DIR') && is_dir((string)TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
        $cacheFile = $cacheDir . '/erocloud_online_movies_size_cache.txt';
        $legacyFile = sys_get_temp_dir() . '/erocloud_online_movies_size_cache.txt';

        @file_put_contents($cacheFile, (string)$totalBytes);
        if ($legacyFile !== $cacheFile) {
            @file_put_contents($legacyFile, (string)$totalBytes);
        }

        $formatted = self::formatBytes($totalBytes);

        Response::success([
            'bytes'     => $totalBytes,
            'formatted' => $formatted,
            'message'   => 'Speicherplatz der Online-Filme wurde neu berechnet: ' . $formatted,
        ]);
    }

    /**
     * 3. Einzelbearbeitung eines veröffentlichten Films (#/movie-edit?id=...)
     *    5-Tab-Cockpit: Bearbeiten | BRAM & KI | Audio & Transkript | Käufe & Zugriffe | Technische Details
     */
    public function edit(): string
    {
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        self::ensureIndexesAndTables();
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }

        $rawId = trim((string)$this->request->get('id', ''));
        if ($rawId === '') {
            return '<div class="alert alert-warning m-4">Keine Film-ID angegeben. <a href="#/movies-online" class="alert-link">Zurück zur Film-Übersicht</a></div>';
        }

        // Flexibles Lookup: unterstützt movies_online.id, file_id (32 Zeichen) oder movies.id
        $mo = null;
        if (strlen($rawId) === 32) {
            $rsMo = p4c_query("SELECT * FROM `movies_online` WHERE `file_id` = '" . p4c_escape_string($rawId) . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsMo && p4c_num_rows($rsMo) > 0) {
                $mo = p4c_fetch_object($rsMo);
            }
        } else {
            $numId = abs((int)$rawId);
            $rsMo = p4c_query("SELECT * FROM `movies_online` WHERE `id` = '" . $numId . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsMo && p4c_num_rows($rsMo) > 0) {
                $mo = p4c_fetch_object($rsMo);
            } else {
                // Fallback: falls eine movies.id übergeben wurde
                $rsMById = p4c_query("SELECT `file_id` FROM `movies` WHERE `id` = '" . $numId . "' LIMIT 1;", __FILE__, __LINE__);
                if ($rsMById && p4c_num_rows($rsMById) > 0) {
                    $fId = (string)(p4c_fetch_object($rsMById)->file_id ?? '');
                    if ($fId !== '') {
                        $rsMo2 = p4c_query("SELECT * FROM `movies_online` WHERE `file_id` = '" . p4c_escape_string($fId) . "' LIMIT 1;", __FILE__, __LINE__);
                        if ($rsMo2 && p4c_num_rows($rsMo2) > 0) {
                            $mo = p4c_fetch_object($rsMo2);
                        }
                    }
                }
            }
        }

        if (!$mo) {
            return '<div class="alert alert-danger m-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>Der angeforderte Online-Film (#' . View::e($rawId) . ') wurde nicht in <code>movies_online</code> gefunden. <a href="#/movies-online" class="alert-link">Zurück zur Film-Übersicht</a></div>';
        }

        $onlineId   = abs((int)$mo->id);
        $fileId     = (string)$mo->file_id;
        $merchantId = abs((int)$mo->merchant_id);
        $actorId    = abs((int)$mo->actor_id);

        // Zugehörigen Datensatz aus movies laden
        $rsM = p4c_query("SELECT * FROM `movies` WHERE `file_id` = '" . p4c_escape_string($fileId) . "' LIMIT 1;", __FILE__, __LINE__);
        $m   = ($rsM && p4c_num_rows($rsM) > 0) ? p4c_fetch_object($rsM) : $mo;
        $movieDbId = abs((int)($m->id ?? $onlineId));

        // Vorheriger & nächster Online-Film für schnelle Navigation
        $prevOnlineId = 0;
        $nextOnlineId = 0;
        $rsPrev = p4c_query("SELECT `id` FROM `movies_online` WHERE `id` < '" . $onlineId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && p4c_num_rows($rsPrev) > 0) {
            $prevOnlineId = abs((int)p4c_fetch_object($rsPrev)->id);
        }
        $rsNext = p4c_query("SELECT `id` FROM `movies_online` WHERE `id` > '" . $onlineId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
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
        $rsActors = p4c_query(
            "SELECT `id`, `username`, `actor_categories`, `status`
             FROM `actors`
             WHERE `merchant_id` = '" . $merchantId . "'
             ORDER BY `username` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($a = p4c_fetch_object($rsActors)) {
                $aid   = abs((int)$a->id);
                $aname = self::decodeText((string)($a->username ?? ''));
                if ($aid === $actorId) {
                    $currentActorName = $aname;
                }
                $merchantActors[] = [
                    'id'               => $aid,
                    'username'         => $aname,
                    'actor_categories' => (string)($a->actor_categories ?? ''),
                    'status'           => (string)($a->status ?? ''),
                ];
            }
        }

        // Freischaltungs- & Bearbeitungs-Mitarbeiter auflösen
        $releasedFromName = '-';
        $releasedFromId   = abs((int)($mo->released_from ?? $m->released_from ?? 0));
        if ($releasedFromId > 0) {
            $rsEmp = p4c_query("SELECT `username` FROM `employee` WHERE `id` = '" . $releasedFromId . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsEmp && p4c_num_rows($rsEmp) > 0) {
                $releasedFromName = self::decodeText((string)(p4c_fetch_object($rsEmp)->username ?? '-'));
            }
        }

        $lastUpdatedByName = '-';
        $lastUpdatedById   = abs((int)($mo->last_updated_by ?? $m->last_updated_by ?? 0));
        if ($lastUpdatedById > 0) {
            $rsEmp2 = p4c_query("SELECT `username` FROM `employee` WHERE `id` = '" . $lastUpdatedById . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsEmp2 && p4c_num_rows($rsEmp2) > 0) {
                $lastUpdatedByName = self::decodeText((string)(p4c_fetch_object($rsEmp2)->username ?? '-'));
            }
        }

        // Partner-Domains laden
        $merchantDomains = [];
        if ($merchantPartnerId > 0) {
            $rsSites = p4c_query(
                "SELECT `domain` FROM `sites` WHERE `partner_id` = '" . $merchantPartnerId . "' AND `status` = '1' ORDER BY `domain` ASC;",
                __FILE__,
                __LINE__
            );
            if ($rsSites && p4c_num_rows($rsSites) > 0) {
                while ($s = p4c_fetch_object($rsSites)) {
                    $dom = trim((string)($s->domain ?? ''));
                    if ($dom !== '') {
                        $merchantDomains[] = $dom;
                    }
                }
            }
        }

        // Dateipfad & technische Details
        $storageLoc    = (string)($mo->storage_location ?? $m->storage_location ?? '');
        $filename      = (string)($mo->filename ?? $m->filename ?? '');
        $movieDir      = rtrim((string)MOVIES_PATH, '/\\') . '/' . $storageLoc . '/' . $merchantId . '/' . $movieDbId;
        $videoFilePath = $movieDir . '/' . $filename;
        $fileExists    = is_file($videoFilePath);
        $fileSizeBytes = $fileExists ? (int)@filesize($videoFilePath) : 0;
        $fileSizeText  = $fileExists ? self::formatBytes($fileSizeBytes) : 'Datei existiert nicht auf dem Server';

        $hasCustomFsk16 = is_file($movieDir . '/thumb_' . $movieDbId . '_' . $fileId . '_11.jpg');
        $hasCustomFsk18 = is_file($movieDir . '/thumb_' . $movieDbId . '_' . $fileId . '_12.jpg');

        $convertStatusVal = abs((int)($m->convert_status ?? 1));
        $convertStatusMap = [
            0 => 'Warteschlange (nicht konvertiert)',
            1 => 'In Konvertierung',
            2 => 'Erfolgreich konvertiert',
            3 => 'Fehler (Konvertierung fehlgeschlagen)',
        ];
        $convertStatusText = $convertStatusMap[$convertStatusVal] ?? ('Status #' . $convertStatusVal);

        $convertDurationText = '-';
        $cStart = (string)($m->convert_starttime ?? '');
        $cEnd   = (string)($m->convert_endtime ?? '');
        if ($cStart !== '' && $cStart !== '0000-00-00 00:00:00' && $cEnd !== '' && $cEnd !== '0000-00-00 00:00:00') {
            $diffSec = strtotime($cEnd) - strtotime($cStart);
            if ($diffSec > 0) {
                $hrs  = (int)floor($diffSec / 3600);
                $mins = (int)floor(($diffSec % 3600) / 60);
                $secs = (int)($diffSec % 60);
                $parts = [];
                if ($hrs > 0) {
                    $parts[] = $hrs . ' Std.';
                }
                if ($mins > 0) {
                    $parts[] = $mins . ' Min.';
                }
                if ($secs > 0 || empty($parts)) {
                    $parts[] = $secs . ' Sek.';
                }
                $convertDurationText = implode(' ', $parts);
            }
        }

        // Auflösung & Auto-Qualitäts-Erkennung (analog movie_edit.php)
        $resolution = (string)($mo->resolution ?? $m->resolution ?? '');
        $resParts   = explode('x', $resolution);
        $resW       = isset($resParts[0]) ? abs((int)$resParts[0]) : 0;
        $resH       = isset($resParts[1]) ? abs((int)$resParts[1]) : 0;
        $maxDim     = max($resW, $resH);
        $minDim     = min($resW, $resH);
        if ($maxDim >= 5500 || $minDim >= 2800) {
            $detectedQuality = '6k';
        } elseif ($maxDim >= 3200 || $minDim >= 1800) {
            $detectedQuality = '4k';
        } elseif ($maxDim >= 2000 || $minDim >= 1300) {
            $detectedQuality = '2k';
        } elseif ($maxDim >= 1600 || $minDim >= 900) {
            $detectedQuality = 'fhd';
        } elseif ($maxDim >= 1000 || $minDim >= 650) {
            $detectedQuality = 'hd';
        } else {
            $detectedQuality = 'sd';
        }

        $currentQuality = strtolower(trim((string)($mo->quality ?? '')));
        if (!isset(self::QUALITY_LABELS[$currentQuality])) {
            $currentQuality = $detectedQuality;
        }

        // Texte dekodieren
        $title       = self::decodeText((string)($mo->title ?? ''));
        $description = self::decodeText((string)($mo->description ?? ''));
        $metaTitle   = self::decodeText((string)($mo->meta_title ?? ''));
        $metaDesc    = self::decodeText((string)($mo->meta_description ?? ''));
        $seoUrl      = self::decodeText((string)($mo->seo_url ?? ''));
        $adminInfos  = self::decodeText((string)($mo->admin_infos ?? ''));

        // Kategorien aus movie_categories laden
        $selectedSlaves = array_values(array_filter(array_map('trim', explode(',', (string)($mo->category_slave ?? '')))));
        $categoriesByType = [
            'number_of_people' => [],
            'look_and_body'    => [],
            'fetish'           => [],
            'porn'             => [],
        ];
        $searchHaystack = mb_strtolower($title . ' ' . strip_tags($description), 'UTF-8');
        $rsCats = p4c_query("SELECT * FROM `movie_categories` ORDER BY `category_group` ASC, `name_id` ASC;", __FILE__, __LINE__);
        if ($rsCats && p4c_num_rows($rsCats) > 0) {
            while ($c = p4c_fetch_object($rsCats)) {
                $cType  = (string)($c->category_group ?? 'porn');
                $idName = (string)($c->name_id ?? '');
                $deName = self::decodeText((string)($c->de_name_value ?? ''));
                $deText = self::decodeText((string)($c->de_name_text ?? ''));
                $moreSw = self::decodeText((string)($c->more_search_words ?? ''));

                $inText = false;
                if ($idName !== '' && mb_strpos($searchHaystack, mb_strtolower($idName, 'UTF-8')) !== false) {
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

                if (!isset($categoriesByType[$cType])) {
                    $categoriesByType[$cType] = [];
                }
                $categoriesByType[$cType][] = [
                    'id_name'  => $idName,
                    'de'       => $deName,
                    'de_text'  => $deText,
                    'selected' => in_array($idName, $selectedSlaves, true),
                    'in_text'  => $inText,
                ];
            }
        }

        // Ablehnungs-Historie laden
        $rejectionHistory = [];
        $rsHist = p4c_query(
            "SELECT h.*, r.`de_short`, e.`username` AS `employee_name`
             FROM `rejection_reason_movie_history` h
             LEFT JOIN `rejection_reason_movie` r ON r.`id_name` = h.`id_name`
             LEFT JOIN `employee` e ON e.`id` = h.`employee_id`
             WHERE h.`movie_file_id` = '" . p4c_escape_string($fileId) . "'
             ORDER BY h.`datetime` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsHist && p4c_num_rows($rsHist) > 0) {
            while ($h = p4c_fetch_object($rsHist)) {
                $rejectionHistory[] = [
                    'id_name'       => (string)($h->id_name ?? ''),
                    'short_title'   => self::decodeText((string)($h->de_short ?? $h->id_name ?? 'Ablehnung')),
                    'text'          => self::decodeText((string)($h->text ?? '')),
                    'datetime'      => (string)($h->datetime ?? ''),
                    'employee_name' => self::decodeText((string)($h->employee_name ?? '-unbekannt-')),
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
            $perfRaw = \CoActorManager::getContentPerformers('movie', $movieDbId, $merchantId);
            $performersData['performer_mode']     = (string)($perfRaw['performer_mode'] ?? 'solo_owner');
            $performersData['merchant_full_name'] = self::decodeText((string)($perfRaw['merchant_full_name'] ?? $merchantUsername));

            foreach ($perfRaw['performers'] ?? [] as $p) {
                $performersData['persons'][] = [
                    'id'                  => abs((int)($p['id'] ?? 0)),
                    'is_owner'            => !empty($p['is_owner']),
                    'display_label'       => self::decodeText((string)($p['display_label'] ?? '')),
                    'stage_name'          => self::decodeText((string)($p['stage_name'] ?? '')),
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
                    'certificate_url'     => (string)($p['certificate_url'] ?? ''),
                ];
            }
        }

        // BRAM & KI-Analyse + VTT-Transkript laden
        $aiData = self::loadMovieAiAndTranscriptData($movieDbId, $merchantId, $fileId, $movieDir, [
            'title'            => $title,
            'description'      => $description,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
        ]);

        // Käufe & Zugriffe (movies_access) laden
        $purchasesData = self::loadMoviePurchasesData($fileId);

        $playtimeSeconds = abs((int)($mo->playtime_seconds ?? $m->playtime_seconds ?? 0));
        $amountSecond    = (float)($mo->amount_second ?? 0);
        $totalCoins      = (int)round($playtimeSeconds * $amountSecond);

        $onlineAtRaw = (string)($mo->online_at ?? '');
        $onlineAtFormatted = ($onlineAtRaw !== '' && $onlineAtRaw !== '0000-00-00 00:00:00')
            ? date('Y-m-d\TH:i', strtotime($onlineAtRaw))
            : date('Y-m-d\TH:i');

        $employeeRule = (string)($_SESSION['employee_rule'] ?? '');

        return View::render('MoviesOnline', 'edit', [
            'movie' => [
                'online_id'            => $onlineId,
                'movie_id'             => $movieDbId,
                'file_id'              => $fileId,
                'merchant_id'          => $merchantId,
                'merchant_partner_id'  => $merchantPartnerId,
                'merchant_username'    => $merchantUsername,
                'actor_id'             => $actorId,
                'actor_name'           => $currentActorName,
                'filename'             => $filename,
                'storage_location'     => $storageLoc,
                'video_path'           => $videoFilePath,
                'file_exists'          => $fileExists,
                'file_size_text'       => $fileSizeText,
                'resolution'           => $resolution,
                'detected_quality'     => self::QUALITY_LABELS[$detectedQuality] ?? strtoupper($detectedQuality),
                'playtime_seconds'     => $playtimeSeconds,
                'playtime_formatted'   => (string)($mo->playtime_string ?? gmdate('H:i:s', $playtimeSeconds)),
                'quality'              => $currentQuality,
                'preview'              => abs((int)($mo->preview ?? 0)),
                'preview_image_fsk16'  => abs((int)($mo->preview_image_fsk16 ?? 1)) ?: 1,
                'preview_image_fsk18'  => abs((int)($mo->preview_image_fsk18 ?? 1)) ?: 1,
                'has_custom_fsk16'     => $hasCustomFsk16,
                'has_custom_fsk18'     => $hasCustomFsk18,
                'title'                => $title,
                'description'          => $description,
                'meta_title'           => $metaTitle,
                'meta_description'     => $metaDesc,
                'seo_url'              => $seoUrl,
                'movie_language'       => (string)($mo->movie_language ?? 'de'),
                'category_master'      => (string)($mo->category_master ?? 'porn') ?: 'porn',
                'selected_slaves'      => $selectedSlaves,
                'online_at_input'      => $onlineAtFormatted,
                'online_at_raw'        => $onlineAtRaw,
                'amount_second'        => number_format($amountSecond, 1, '.', ''),
                'total_coins'          => $totalCoins,
                'as_download'          => abs((int)($mo->as_download ?? 0)),
                'amount_download'      => abs((int)($mo->amount_download ?? 0)),
                'status'               => (string)($mo->status ?? 'active'),
                'visible_for_website'  => (string)($mo->visible_for_website ?? 'public'),
                'admin_infos'          => $adminInfos,
                'create_datetime'      => (string)($m->create_datetime ?? ''),
                'movie_checked'        => (string)($mo->movie_checked ?? ''),
                'released_datetime'    => (string)($mo->released_datetime ?? $m->released_datetime ?? ''),
                'released_from_name'   => $releasedFromName,
                'last_updated_date'    => (string)($mo->last_updated_datetime ?? $m->last_updated_datetime ?? ''),
                'last_updated_by_name' => $lastUpdatedByName,
                'checksum'             => (string)($mo->checksum ?? $m->checksum ?? ''),
                'convert_status_text'  => $convertStatusText,
                'convert_starttime'    => $cStart,
                'convert_endtime'      => $cEnd,
                'convert_duration'     => $convertDurationText,
            ],
            'prevOnlineId'     => $prevOnlineId,
            'nextOnlineId'     => $nextOnlineId,
            'merchantActors'   => $merchantActors,
            'merchantDomains'  => $merchantDomains,
            'categoriesByType' => $categoriesByType,
            'rejectionHistory' => $rejectionHistory,
            'performersData'   => $performersData,
            'aiData'           => $aiData,
            'purchasesData'    => $purchasesData,
            'languages'        => self::LANGUAGE_LABELS,
            'qualities'        => self::QUALITY_LABELS,
            'employeeRule'     => $employeeRule,
        ]);
    }

    /**
     * 4. AJAX-Aktion: Änderungen an einem veröffentlichten Film speichern (?route=movies-online/save)
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
        $fileId     = trim((string)$this->request->post('file_id', ''));

        if ($onlineId <= 0 && $fileId === '') {
            Response::error('Ungültige Film-ID.', 400);
            return;
        }

        $whereMo = $onlineId > 0
            ? "`id` = '" . $onlineId . "'"
            : "`file_id` = '" . p4c_escape_string($fileId) . "'";

        $rsMo = p4c_query("SELECT * FROM `movies_online` WHERE " . $whereMo . " LIMIT 1;", __FILE__, __LINE__);
        if (!$rsMo || p4c_num_rows($rsMo) === 0) {
            Response::error('Der Online-Film wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $mo         = p4c_fetch_object($rsMo);
        $onlineId   = abs((int)$mo->id);
        $fileId     = (string)$mo->file_id;
        $merchantId = abs((int)$mo->merchant_id);

        $rsM       = p4c_query("SELECT * FROM `movies` WHERE `file_id` = '" . p4c_escape_string($fileId) . "' LIMIT 1;", __FILE__, __LINE__);
        $m         = ($rsM && p4c_num_rows($rsM) > 0) ? p4c_fetch_object($rsM) : $mo;
        $movieDbId = abs((int)($m->id ?? 0));

        $errors = [];

        // 1. Darsteller validieren
        $actorId = abs((int)$this->request->post('actor_id', $mo->actor_id));
        $rsActor = p4c_query(
            "SELECT `id` FROM `actors` WHERE `id` = '" . $actorId . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            $errors[] = 'Bitte wähle den Hauptdarsteller für diesen Film aus.';
        }

        // 2. Hauptkategorie & Unterkategorien validieren
        $categoryMaster = trim((string)$this->request->post('category_master', 'porn'));
        if (!in_array($categoryMaster, ['porn', 'fetish'], true)) {
            $errors[] = 'Bitte wähle eine Hauptkategorie (Porno oder Fetisch) aus.';
        }

        $rawCategories = $this->request->post('categories', []);
        if (!is_array($rawCategories)) {
            $rawCategories = [];
        }
        $validCategories = [];
        $rsValidCats = p4c_query("SELECT `name_id` FROM `movie_categories`;", __FILE__, __LINE__);
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
            $errors[] = 'Bitte wähle alle Kategorien aus, die zum Film passen.';
        }

        // 3. Titel & Beschreibung bereinigen
        $zeroWidthChars = ["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBF\xBD"];
        $title = trim(str_replace(self::REPLACE_TITLE_CHARS, '', (string)$this->request->post('title', '')));
        $title = str_replace($zeroWidthChars, '', $title);
        $title = str_replace(['#?', '?en'], ['#', 'en'], $title);
        $title = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;'], 'EUR', $title);
        $title = trim(strip_tags($title));
        if ($title === '') {
            $errors[] = 'Bitte gib einen aussagekräftigen Filmtitel an.';
        }

        $allowedTags = '<p><ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><br><a><b><i>';
        $description = trim(strip_tags((string)$this->request->post('description', ''), $allowedTags));
        $description = str_replace($zeroWidthChars, '', $description);
        $description = str_replace(['#?', '?en'], ['#', 'en'], $description);
        if ($description === '') {
            $errors[] = 'Bitte gib eine aussagekräftige Beschreibung des Films an.';
        }

        // 4. Online-ab Datum & Preise
        $onlineAtRaw = trim((string)$this->request->post('online_at', ''));
        $onlineAtTs  = $onlineAtRaw !== '' ? strtotime($onlineAtRaw) : false;
        $onlineAt    = $onlineAtTs !== false ? date('Y-m-d H:i:s', $onlineAtTs) : (string)$mo->online_at;

        $amountSecondRaw = str_replace(',', '.', trim((string)$this->request->post('amount_second', '0.0')));
        $amountSecondVal = (float)$amountSecondRaw;
        if (!is_numeric($amountSecondRaw) || $amountSecondVal < 0.0 || $amountSecondVal > 100.0) {
            $errors[] = 'Bitte gib einen gültigen Streaming-Preis pro Sekunde an.';
        }
        $amountSecondFormatted = number_format($amountSecondVal, 1, '.', '');

        $asDownload = abs((int)$this->request->post('as_download', 0)) === 1 ? 1 : 0;
        $amountDownload = abs((int)$this->request->post('amount_download', 0));
        if ($amountDownload > 150) {
            $amountDownload = 150;
        }

        $amountWebmaster = abs((int)$this->request->post('amount_webmaster', $mo->amount_webmaster ?? 0));
        if (!in_array($amountWebmaster, [0, 5, 10, 15, 20, 25], true)) {
            $amountWebmaster = abs((int)($mo->amount_webmaster ?? 0));
        }

        // 5. Vorschaubilder
        $fsk16 = abs((int)$this->request->post('fsk16', $mo->preview_image_fsk16));
        $fsk18 = abs((int)$this->request->post('fsk18', $mo->preview_image_fsk18));
        if ($fsk16 < 1 || $fsk16 > 11) {
            $errors[] = 'Bitte wähle ein FSK16 Vorschaubild aus.';
        }
        if ($fsk18 < 1 || ($fsk18 > 10 && $fsk18 !== 12)) {
            $errors[] = 'Bitte wähle ein FSK18 Vorschaubild aus.';
        }

        // 6. SEO-Felder
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

        // 7. Sprache, Qualität, Trailer, Sichtbarkeit & Status
        $movieLanguage = strtolower(trim((string)$this->request->post('movie_language', 'de')));
        if (!isset(self::LANGUAGE_LABELS[$movieLanguage])) {
            $movieLanguage = 'de';
        }

        $quality = strtolower(trim((string)$this->request->post('quality', 'hd')));
        if (!isset(self::QUALITY_LABELS[$quality])) {
            $quality = 'hd';
        }

        $preview = abs((int)$this->request->post('preview', 0)) === 1 ? 1 : 0;

        $visibleForWebsite = trim(strip_tags((string)$this->request->post('visible_for_website', 'public')));
        if (in_array('poppers', $validCategories, true) && $visibleForWebsite === 'public') {
            $errors[] = 'Filme mit der Kategorie „Poppers“ dürfen nicht auf „allen Partnerwebsites (public)“ vermarktet werden.';
        }

        $movieStatus = trim((string)$this->request->post('movie_status', 'active'));
        if (!in_array($movieStatus, ['active', 'blocked', 'deleted'], true)) {
            $errors[] = 'Ungültiger Film-Status ausgewählt.';
        } elseif (
            $movieStatus === 'deleted'
            && (string)($_SESSION['employee_rule'] ?? '') !== 'write'
            && (string)($m->status ?? '') !== 'deleted'
        ) {
            $errors[] = 'Du besitzt keine Schreib-/Löschberechtigung (Administrator-Status), um Filme auf den Status „Gelöscht“ zu setzen.';
        }

        $adminInfos = trim(strip_tags((string)$this->request->post('admin_infos', '')));

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 422);
            return;
        }

        $categorySlaveStr = implode(',', $validCategories);
        $now              = date('Y-m-d H:i:s');
        $deletedDatetime  = ($movieStatus === 'deleted') ? $now : '0000-00-00 00:00:00';

        $checksumData = [
            'merchant_id'         => $merchantId,
            'actor_id'            => $actorId,
            'fsk16'               => $fsk16,
            'fsk18'               => $fsk18,
            'preview_image_fsk16' => $fsk16,
            'preview_image_fsk18' => $fsk18,
            'title'               => $title,
            'description'         => $description,
            'meta_title'          => $metaTitle,
            'meta_description'    => $metaDesc,
            'seo_url'             => $seoUrl,
            'online_at'           => $onlineAt,
            'amount_second'       => $amountSecondFormatted,
            'amount_own'          => (string)($m->amount_own ?? '0.00'),
            'amount_webmaster'    => $amountWebmaster,
            'as_download'         => $asDownload,
            'amount_download'     => $amountDownload,
        ];
        $checksum = function_exists('movie_checksum') ? (string)movie_checksum($checksumData) : md5($fileId . $now);

        // 1. Update movies
        p4c_query(
            "UPDATE `movies` SET
                `actor_id`              = '" . $actorId . "',
                `checksum`              = '" . p4c_escape_string($checksum) . "',
                `quality`               = '" . p4c_escape_string($quality) . "',
                `preview_image_fsk16`   = '" . $fsk16 . "',
                `preview_image_fsk18`   = '" . $fsk18 . "',
                `title`                 = '" . p4c_escape_string($title) . "',
                `description`           = '" . p4c_escape_string($description) . "',
                `movie_language`        = '" . p4c_escape_string($movieLanguage) . "',
                `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                `online_at`             = '" . p4c_escape_string($onlineAt) . "',
                `amount_second`         = '" . p4c_escape_string($amountSecondFormatted) . "',
                `amount_webmaster`      = '" . $amountWebmaster . "',
                `as_download`           = '" . $asDownload . "',
                `amount_download`       = '" . $amountDownload . "',
                `preview`               = '" . $preview . "',
                `status`                = '" . p4c_escape_string($movieStatus) . "',
                `deleted_datetime`      = '" . $deletedDatetime . "',
                `visible_for_website`   = '" . p4c_escape_string($visibleForWebsite) . "',
                `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `file_id` = '" . p4c_escape_string($fileId) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        // 2. Update movies_online
        p4c_query(
            "UPDATE `movies_online` SET
                `actor_id`              = '" . $actorId . "',
                `checksum`              = '" . p4c_escape_string($checksum) . "',
                `quality`               = '" . p4c_escape_string($quality) . "',
                `preview_image_fsk16`   = '" . $fsk16 . "',
                `preview_image_fsk18`   = '" . $fsk18 . "',
                `title`                 = '" . p4c_escape_string($title) . "',
                `description`           = '" . p4c_escape_string($description) . "',
                `movie_language`        = '" . p4c_escape_string($movieLanguage) . "',
                `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                `online_at`             = '" . p4c_escape_string($onlineAt) . "',
                `amount_second`         = '" . p4c_escape_string($amountSecondFormatted) . "',
                `amount_webmaster`      = '" . $amountWebmaster . "',
                `as_download`           = '" . $asDownload . "',
                `amount_download`       = '" . $amountDownload . "',
                `preview`               = '" . $preview . "',
                `status`                = '" . p4c_escape_string($movieStatus) . "',
                `deleted_datetime`      = '" . $deletedDatetime . "',
                `visible_for_website`   = '" . p4c_escape_string($visibleForWebsite) . "',
                `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `file_id` = '" . p4c_escape_string($fileId) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        // 3. BRAM-Compliance nach dem Speichern direkt aktualisieren (analog movie_edit.php)
        if ($movieDbId > 0) {
            require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
            if (function_exists('aic_analyze_texts')) {
                $env = function_exists('aic_check_environment') ? aic_check_environment() : ['llm_ready' => false];
                $rsAi = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movieDbId . "' LIMIT 1;", __FILE__, __LINE__);
                $aiRow = ($rsAi && p4c_num_rows($rsAi) > 0) ? p4c_fetch_object($rsAi) : null;
                $segs  = $aiRow ? (json_decode((string)($aiRow->transcript_segments_json ?? '[]'), true) ?: []) : [];
                $trTxt = $aiRow ? (string)($aiRow->transcript_text ?? '') : '';

                $rep = aic_analyze_texts([
                    'title'            => $title,
                    'description'      => $description,
                    'meta_title'       => $metaTitle,
                    'meta_description' => $metaDesc,
                ], $segs, $trTxt, $env);

                $violJson = (string)json_encode($rep['violations'] ?? [], JSON_UNESCAPED_UNICODE);
                p4c_query(
                    "UPDATE `movies_ai_analysis` SET
                        `text_check_status`    = '2',
                        `text_compliance`      = '" . p4c_escape_string((string)$rep['compliance_status']) . "',
                        `text_risk_score`      = '" . abs((int)$rep['risk_score']) . "',
                        `text_engine_used`     = '" . p4c_escape_string((string)$rep['engine_used']) . "',
                        `text_summary`         = '" . p4c_escape_string((string)$rep['summary_de']) . "',
                        `text_violations_json` = '" . p4c_escape_string($violJson) . "',
                        `updated_at`           = '" . $now . "'
                     WHERE `movie_id` = '" . $movieDbId . "'
                     LIMIT 1;",
                    __FILE__,
                    __LINE__
                );
            }
        }

        if (function_exists('log_action')) {
            log_action('Online-Film #' . $onlineId . ' ("' . $title . '") bearbeitet [Status: ' . $movieStatus . '].');
        }

        Response::success([
            'message'   => 'Die Änderungen am Film #' . $onlineId . ' wurden erfolgreich gespeichert.',
            'online_id' => $onlineId,
        ]);
    }

    /**
     * 5. Filme in Planung / Entwürfe (#/movies-drafts)
     */
    public function drafts(): string
    {
        $rsMovies = p4c_query(
            "SELECT m.`id`, m.`file_id`, m.`merchant_id`, m.`actor_id`, m.`checksum`,
                    m.`title`, m.`online_at`, m.`status`, m.`convert_status`,
                    m.`movie_language`, m.`category_master`, m.`storage_location`,
                    m.`playtime_seconds`, m.`playtime_string`, m.`resolution`,
                    a.`username` AS `actor_username`
             FROM `movies` m
             LEFT JOIN `actors` a ON a.`id` = m.`actor_id` AND a.`merchant_id` = m.`merchant_id`
             WHERE m.`released` = '0'
               AND m.`status` != 'deleted'
             ORDER BY m.`id` DESC;",
            __FILE__,
            __LINE__
        );

        $drafts = [];
        $kpis = [
            'total'       => 0,
            'ready'       => 0,
            'converting'  => 0,
            'error'       => 0,
            'total_bytes' => 0,
        ];

        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($row = p4c_fetch_object($rsMovies)) {
                $movieId       = abs((int)$row->id);
                $merchantId    = abs((int)$row->merchant_id);
                $convertStatus = abs((int)$row->convert_status);
                $storageLoc    = (string)($row->storage_location ?? '');

                $folder    = rtrim((string)MOVIES_PATH, '/\\') . '/' . $storageLoc . '/' . $merchantId . '/' . $movieId;
                $sizeBytes = self::getFolderSize($folder);

                $kpis['total']++;
                $kpis['total_bytes'] += $sizeBytes;

                if ($convertStatus === 2) {
                    $kpis['ready']++;
                } elseif ($convertStatus === 0 || $convertStatus === 1) {
                    $kpis['converting']++;
                } else {
                    $kpis['error']++;
                }

                $drafts[] = [
                    'id'              => $movieId,
                    'file_id'         => (string)($row->file_id ?? ''),
                    'merchant_id'     => $merchantId,
                    'actor_id'        => abs((int)$row->actor_id),
                    'actor_name'      => self::decodeText((string)($row->actor_username ?? '')),
                    'title'           => self::decodeText((string)($row->title ?? '')),
                    'online_at'       => (string)($row->online_at ?? ''),
                    'convert_status'  => $convertStatus,
                    'language_key'    => (string)($row->movie_language ?? 'de'),
                    'language_label'  => self::LANGUAGE_LABELS[(string)($row->movie_language ?? 'de')] ?? (string)($row->movie_language ?? '-'),
                    'category_master' => (string)($row->category_master ?? ''),
                    'size_bytes'      => $sizeBytes,
                    'size_text'       => $sizeBytes > 0 ? self::formatBytes($sizeBytes) : '-',
                    'resolution'      => (string)($row->resolution ?? ''),
                    'playtime_str'    => (string)($row->playtime_string ?? ''),
                ];
            }
        }

        $kpis['total_size_text'] = self::formatBytes($kpis['total_bytes']);
        $switcherCounts = self::getContentSwitcherCounts();

        return View::render('MoviesOnline', 'drafts', [
            'drafts'         => $drafts,
            'kpis'           => $kpis,
            'switcherCounts' => $switcherCounts,
        ]);
    }

    /**
     * 5b. Film in Planung – Reine Lese-Detailansicht (#/movie-draft?id=...)
     */
    public function draftDetail(): string
    {
        self::ensureIndexesAndTables();

        $movieId = abs((int)$this->request->get('id', 0));
        if ($movieId <= 0) {
            return '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle-fill me-2"></i>Ungültige Film-ID übergeben. <a href="#/movies-drafts" class="alert-link ms-2">Zurück zu Filme in Planung</a></div>';
        }

        $rsM = p4c_query("SELECT * FROM `movies` WHERE `id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsM || p4c_num_rows($rsM) === 0) {
            return '<div class="alert alert-warning m-3"><i class="bi bi-info-circle-fill me-2"></i>Der angeforderte Film wurde nicht in der Datenbank gefunden. <a href="#/movies-drafts" class="alert-link ms-2">Zurück zu Filme in Planung</a></div>';
        }

        $m          = p4c_fetch_object($rsM);
        $movieDbId  = abs((int)$m->id);
        $fileId     = (string)($m->file_id ?? '');
        $merchantId = abs((int)($m->merchant_id ?? 0));
        $actorId    = abs((int)($m->actor_id ?? 0));

        // Vorheriger / Nächster Entwurf (Liste ist nach id DESC sortiert)
        $prevDraftId = 0;
        $rsPrev = p4c_query(
            "SELECT `id` FROM `movies` WHERE `released` = '0' AND `status` != 'deleted' AND `id` > '" . $movieDbId . "' ORDER BY `id` ASC LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsPrev && p4c_num_rows($rsPrev) > 0) {
            $prevDraftId = abs((int)p4c_fetch_object($rsPrev)->id);
        }

        $nextDraftId = 0;
        $rsNext = p4c_query(
            "SELECT `id` FROM `movies` WHERE `released` = '0' AND `status` != 'deleted' AND `id` < '" . $movieDbId . "' ORDER BY `id` DESC LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsNext && p4c_num_rows($rsNext) > 0) {
            $nextDraftId = abs((int)p4c_fetch_object($rsNext)->id);
        }

        // Merchant-Infos laden (exakt wie in edit())
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
        $rsActors = p4c_query(
            "SELECT `id`, `username`, `actor_categories`, `status`
             FROM `actors`
             WHERE `merchant_id` = '" . $merchantId . "'
             ORDER BY `username` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($a = p4c_fetch_object($rsActors)) {
                $aid   = abs((int)$a->id);
                $aname = self::decodeText((string)($a->username ?? ''));
                if ($aid === $actorId) {
                    $currentActorName = $aname;
                }
                $merchantActors[] = [
                    'id'               => $aid,
                    'username'         => $aname,
                    'actor_categories' => (string)($a->actor_categories ?? ''),
                    'status'           => (string)($a->status ?? ''),
                ];
            }
        }

        // Bearbeiter-Mitarbeiter auflösen
        $lastUpdatedByName = '-';
        $lastUpdatedById   = abs((int)($m->last_updated_by ?? 0));
        if ($lastUpdatedById > 0) {
            $rsEmp2 = p4c_query("SELECT `username` FROM `employee` WHERE `id` = '" . $lastUpdatedById . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsEmp2 && p4c_num_rows($rsEmp2) > 0) {
                $lastUpdatedByName = self::decodeText((string)(p4c_fetch_object($rsEmp2)->username ?? '-'));
            }
        }

        // Partner-Domains laden
        $merchantDomains = [];
        if ($merchantPartnerId > 0) {
            $rsSites = p4c_query(
                "SELECT `domain` FROM `sites` WHERE `partner_id` = '" . $merchantPartnerId . "' AND `status` = '1' ORDER BY `domain` ASC;",
                __FILE__,
                __LINE__
            );
            if ($rsSites && p4c_num_rows($rsSites) > 0) {
                while ($s = p4c_fetch_object($rsSites)) {
                    $dom = trim((string)($s->domain ?? ''));
                    if ($dom !== '') {
                        $merchantDomains[] = $dom;
                    }
                }
            }
        }

        // Dateipfad & technische Details
        $storageLoc    = (string)($m->storage_location ?? '');
        $filename      = (string)($m->filename ?? '');
        $movieDir      = rtrim((string)MOVIES_PATH, '/\\') . '/' . $storageLoc . '/' . $merchantId . '/' . $movieDbId;
        $videoFilePath = $movieDir . '/' . $filename;
        $fileExists    = ($filename !== '' && is_file($videoFilePath));
        $fileSizeBytes = $fileExists ? (int)@filesize($videoFilePath) : self::getFolderSize($movieDir);
        $fileSizeText  = $fileSizeBytes > 0 ? self::formatBytes($fileSizeBytes) : 'Datei existiert nicht auf dem Server';

        $hasCustomFsk16 = is_file($movieDir . '/thumb_' . $movieDbId . '_' . $fileId . '_11.jpg');
        $hasCustomFsk18 = is_file($movieDir . '/thumb_' . $movieDbId . '_' . $fileId . '_12.jpg');

        $convertStatusVal = abs((int)($m->convert_status ?? 0));
        $convertStatusMap = [
            0 => 'Warteschlange (nicht konvertiert)',
            1 => 'In Konvertierung',
            2 => 'Erfolgreich konvertiert',
            3 => 'Fehler (Konvertierung fehlgeschlagen)',
        ];
        $convertStatusText = $convertStatusMap[$convertStatusVal] ?? ('Status #' . $convertStatusVal);

        $convertDurationText = '-';
        $cStart = (string)($m->convert_starttime ?? '');
        $cEnd   = (string)($m->convert_endtime ?? '');
        if ($cStart !== '' && $cStart !== '0000-00-00 00:00:00' && $cEnd !== '' && $cEnd !== '0000-00-00 00:00:00') {
            $diffSec = strtotime($cEnd) - strtotime($cStart);
            if ($diffSec > 0) {
                $hrs  = (int)floor($diffSec / 3600);
                $mins = (int)floor(($diffSec % 3600) / 60);
                $secs = (int)($diffSec % 60);
                $parts = [];
                if ($hrs > 0) {
                    $parts[] = $hrs . ' Std.';
                }
                if ($mins > 0) {
                    $parts[] = $mins . ' Min.';
                }
                if ($secs > 0 || empty($parts)) {
                    $parts[] = $secs . ' Sek.';
                }
                $convertDurationText = implode(' ', $parts);
            }
        }

        // Auflösung & Auto-Qualitäts-Erkennung
        $resolution = (string)($m->resolution ?? '');
        $resParts   = explode('x', $resolution);
        $resW       = isset($resParts[0]) ? abs((int)$resParts[0]) : 0;
        $resH       = isset($resParts[1]) ? abs((int)$resParts[1]) : 0;
        $maxDim     = max($resW, $resH);
        $minDim     = min($resW, $resH);
        if ($maxDim >= 5500 || $minDim >= 2800) {
            $detectedQuality = '6k';
        } elseif ($maxDim >= 3200 || $minDim >= 1800) {
            $detectedQuality = '4k';
        } elseif ($maxDim >= 2000 || $minDim >= 1300) {
            $detectedQuality = '2k';
        } elseif ($maxDim >= 1600 || $minDim >= 900) {
            $detectedQuality = 'fhd';
        } elseif ($maxDim >= 1000 || $minDim >= 650) {
            $detectedQuality = 'hd';
        } else {
            $detectedQuality = 'sd';
        }

        $currentQuality = strtolower(trim((string)($m->quality ?? '')));
        if (!isset(self::QUALITY_LABELS[$currentQuality])) {
            $currentQuality = $detectedQuality;
        }

        // Texte dekodieren
        $title       = self::decodeText((string)($m->title ?? ''));
        $description = self::decodeText((string)($m->description ?? ''));
        $metaTitle   = self::decodeText((string)($m->meta_title ?? ''));
        $metaDesc    = self::decodeText((string)($m->meta_description ?? ''));
        $seoUrl      = self::decodeText((string)($m->seo_url ?? ''));
        $adminInfos  = self::decodeText((string)($m->admin_infos ?? ''));

        // Kategorien aus movie_categories laden
        $selectedSlaves = array_values(array_filter(array_map('trim', explode(',', (string)($m->category_slave ?? '')))));
        $categoriesByType = [
            'number_of_people' => [],
            'look_and_body'    => [],
            'fetish'           => [],
            'porn'             => [],
        ];
        $searchHaystack = mb_strtolower($title . ' ' . strip_tags($description), 'UTF-8');
        $rsCats = p4c_query("SELECT * FROM `movie_categories` ORDER BY `category_group` ASC, `name_id` ASC;", __FILE__, __LINE__);
        if ($rsCats && p4c_num_rows($rsCats) > 0) {
            while ($c = p4c_fetch_object($rsCats)) {
                $cType  = (string)($c->category_group ?? 'porn');
                $idName = (string)($c->name_id ?? '');
                $deName = self::decodeText((string)($c->de_name_value ?? ''));
                $deText = self::decodeText((string)($c->de_name_text ?? ''));
                $moreSw = self::decodeText((string)($c->more_search_words ?? ''));

                $inText = false;
                if ($idName !== '' && mb_strpos($searchHaystack, mb_strtolower($idName, 'UTF-8')) !== false) {
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

                if (!isset($categoriesByType[$cType])) {
                    $categoriesByType[$cType] = [];
                }
                $categoriesByType[$cType][] = [
                    'id_name'  => $idName,
                    'de'       => $deName,
                    'de_text'  => $deText,
                    'selected' => in_array($idName, $selectedSlaves, true),
                    'in_text'  => $inText,
                ];
            }
        }

        // Ablehnungs-Historie laden
        $rejectionHistory = [];
        if ($fileId !== '') {
            $rsHist = p4c_query(
                "SELECT h.*, r.`de_short`, e.`username` AS `employee_name`
                 FROM `rejection_reason_movie_history` h
                 LEFT JOIN `rejection_reason_movie` r ON r.`id_name` = h.`id_name`
                 LEFT JOIN `employee` e ON e.`id` = h.`employee_id`
                 WHERE h.`movie_file_id` = '" . p4c_escape_string($fileId) . "'
                 ORDER BY h.`datetime` DESC;",
                __FILE__,
                __LINE__
            );
            if ($rsHist && p4c_num_rows($rsHist) > 0) {
                while ($h = p4c_fetch_object($rsHist)) {
                    $rejectionHistory[] = [
                        'id_name'       => (string)($h->id_name ?? ''),
                        'short_title'   => self::decodeText((string)($h->de_short ?? $h->id_name ?? 'Ablehnung')),
                        'text'          => self::decodeText((string)($h->text ?? '')),
                        'datetime'      => (string)($h->datetime ?? ''),
                        'employee_name' => self::decodeText((string)($h->employee_name ?? '-unbekannt-')),
                    ];
                }
            }
        }

        // 18+ Mitwirkende / ID-Shot Daten laden
        $performersData = [
            'performer_mode'     => 'solo_owner',
            'merchant_full_name' => $merchantUsername,
            'persons'            => [],
        ];
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
            $perfRaw = \CoActorManager::getContentPerformers('movie', $movieDbId, $merchantId);
            $performersData['performer_mode']     = (string)($perfRaw['performer_mode'] ?? 'solo_owner');
            $performersData['merchant_full_name'] = self::decodeText((string)($perfRaw['merchant_full_name'] ?? $merchantUsername));

            foreach ($perfRaw['performers'] ?? [] as $p) {
                $performersData['persons'][] = [
                    'id'                  => abs((int)($p['id'] ?? 0)),
                    'is_owner'            => !empty($p['is_owner']),
                    'display_label'       => self::decodeText((string)($p['display_label'] ?? '')),
                    'stage_name'          => self::decodeText((string)($p['stage_name'] ?? '')),
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
                    'certificate_url'     => (string)($p['certificate_url'] ?? ''),
                ];
            }
        }

        // BRAM & KI-Analyse + VTT-Transkript laden
        $aiData = self::loadMovieAiAndTranscriptData($movieDbId, $merchantId, $fileId, $movieDir, [
            'title'            => $title,
            'description'      => $description,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
        ]);

        $playtimeSeconds = abs((int)($m->playtime_seconds ?? 0));
        $amountSecond    = (float)($m->amount_second ?? 0);
        $totalCoins      = (int)round($playtimeSeconds * $amountSecond);

        $onlineAtRaw = (string)($m->online_at ?? '');
        $onlineAtFormatted = ($onlineAtRaw !== '' && $onlineAtRaw !== '0000-00-00 00:00:00')
            ? date('Y-m-d\TH:i', strtotime($onlineAtRaw))
            : '';

        return View::render('MoviesOnline', 'draft_detail', [
            'movie' => [
                'movie_id'             => $movieDbId,
                'file_id'              => $fileId,
                'merchant_id'          => $merchantId,
                'merchant_partner_id'  => $merchantPartnerId,
                'merchant_username'    => $merchantUsername,
                'actor_id'             => $actorId,
                'actor_name'           => $currentActorName,
                'filename'             => $filename,
                'storage_location'     => $storageLoc,
                'video_path'           => $videoFilePath,
                'file_exists'          => $fileExists,
                'file_size_text'       => $fileSizeText,
                'resolution'           => $resolution,
                'detected_quality'     => self::QUALITY_LABELS[$detectedQuality] ?? strtoupper($detectedQuality),
                'playtime_seconds'     => $playtimeSeconds,
                'playtime_formatted'   => (string)($m->playtime_string ?? gmdate('H:i:s', $playtimeSeconds)),
                'quality'              => $currentQuality,
                'preview'              => abs((int)($m->preview ?? 0)),
                'preview_image_fsk16'  => abs((int)($m->preview_image_fsk16 ?? 1)) ?: 1,
                'preview_image_fsk18'  => abs((int)($m->preview_image_fsk18 ?? 1)) ?: 1,
                'has_custom_fsk16'     => $hasCustomFsk16,
                'has_custom_fsk18'     => $hasCustomFsk18,
                'title'                => $title,
                'description'          => $description,
                'meta_title'           => $metaTitle,
                'meta_description'     => $metaDesc,
                'seo_url'              => $seoUrl,
                'movie_language'       => (string)($m->movie_language ?? 'de'),
                'category_master'      => (string)($m->category_master ?? 'porn') ?: 'porn',
                'selected_slaves'      => $selectedSlaves,
                'online_at_input'      => $onlineAtFormatted,
                'online_at_raw'        => $onlineAtRaw,
                'amount_second'        => number_format($amountSecond, 1, '.', ''),
                'total_coins'          => $totalCoins,
                'as_download'          => abs((int)($m->as_download ?? 0)),
                'amount_download'      => abs((int)($m->amount_download ?? 0)),
                'status'               => (string)($m->status ?? 'active'),
                'released'             => abs((int)($m->released ?? 0)),
                'convert_status'       => $convertStatusVal,
                'convert_status_text'  => $convertStatusText,
                'convert_starttime'    => $cStart,
                'convert_endtime'      => $cEnd,
                'convert_duration'     => $convertDurationText,
                'visible_for_website'  => (string)($m->visible_for_website ?? 'public'),
                'admin_infos'          => $adminInfos,
                'create_datetime'      => (string)($m->create_datetime ?? ''),
                'movie_checked'        => (string)($m->movie_checked ?? ''),
                'last_updated_date'    => (string)($m->last_updated_datetime ?? ''),
                'last_updated_by_name' => $lastUpdatedByName,
                'checksum'             => (string)($m->checksum ?? ''),
            ],
            'prevDraftId'      => $prevDraftId,
            'nextDraftId'      => $nextDraftId,
            'merchantActors'   => $merchantActors,
            'merchantDomains'  => $merchantDomains,
            'categoriesByType' => $categoriesByType,
            'rejectionHistory' => $rejectionHistory,
            'performersData'   => $performersData,
            'aiData'           => $aiData,
            'languages'        => self::LANGUAGE_LABELS,
            'qualities'        => self::QUALITY_LABELS,
        ]);
    }

    /**
     * 6. Abgelehnte & gesperrte Filme (#/movies-blocked)
     */
    public function blocked(): string
    {
        $rsMovies = p4c_query(
            "SELECT m.`id`, m.`file_id`, m.`merchant_id`, m.`actor_id`, m.`checksum`,
                    m.`title`, m.`online_at`, m.`status`, m.`released`, m.`convert_status`,
                    m.`movie_language`, m.`category_master`, m.`movie_checked`,
                    m.`deleted_datetime`, m.`last_updated_datetime`, m.`playtime_string`, m.`resolution`,
                    a.`username` AS `actor_username`
             FROM `movies` m
             LEFT JOIN `actors` a ON a.`id` = m.`actor_id` AND a.`merchant_id` = m.`merchant_id`
             WHERE m.`convert_status` > '1'
               AND (m.`released` = '2' OR m.`status` != 'active')
             ORDER BY m.`id` DESC;",
            __FILE__,
            __LINE__
        );

        // Letzten Ablehnungsgrund pro movie_file_id vorab laden
        $latestRejectionByFileId = [];
        $rsRej = p4c_query(
            "SELECT h.`movie_file_id`, h.`id_name`, h.`text`, h.`datetime`, r.`de_short`
             FROM `rejection_reason_movie_history` h
             LEFT JOIN `rejection_reason_movie` r ON r.`id_name` = h.`id_name`
             ORDER BY h.`datetime` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsRej && p4c_num_rows($rsRej) > 0) {
            while ($rRow = p4c_fetch_object($rsRej)) {
                $fid = (string)($rRow->movie_file_id ?? '');
                if ($fid !== '' && !isset($latestRejectionByFileId[$fid])) {
                    $latestRejectionByFileId[$fid] = [
                        'short'    => self::decodeText((string)($rRow->de_short ?? $rRow->id_name ?? 'Abgelehnt')),
                        'text'     => self::decodeText((string)($rRow->text ?? '')),
                        'datetime' => (string)($rRow->datetime ?? ''),
                    ];
                }
            }
        }

        $movies = [];
        $kpis = [
            'total'    => 0,
            'rejected' => 0,
            'blocked'  => 0,
            'deleted'  => 0,
        ];

        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($row = p4c_fetch_object($rsMovies)) {
                $movieId  = abs((int)$row->id);
                $fileId   = (string)($row->file_id ?? '');
                $status   = (string)($row->status ?? 'active');
                $released = (string)($row->released ?? '0');

                $kpis['total']++;
                if ($status === 'deleted') {
                    $kpis['deleted']++;
                    $stateKey = 'deleted';
                } elseif ($status === 'blocked') {
                    $kpis['blocked']++;
                    $stateKey = 'blocked';
                } else {
                    $kpis['rejected']++;
                    $stateKey = 'rejected';
                }

                $actionDate = '';
                if ($status === 'deleted' && (string)$row->deleted_datetime !== '0000-00-00 00:00:00') {
                    $actionDate = (string)$row->deleted_datetime;
                } elseif ((string)$row->movie_checked !== '0000-00-00 00:00:00' && (string)$row->movie_checked !== '000-00-00 00:00:00') {
                    $actionDate = (string)$row->movie_checked;
                } elseif ((string)$row->last_updated_datetime !== '0000-00-00 00:00:00') {
                    $actionDate = (string)$row->last_updated_datetime;
                }

                $rejInfo = $latestRejectionByFileId[$fileId] ?? null;

                $movies[] = [
                    'id'              => $movieId,
                    'file_id'         => $fileId,
                    'merchant_id'     => abs((int)$row->merchant_id),
                    'actor_id'        => abs((int)$row->actor_id),
                    'actor_name'      => self::decodeText((string)($row->actor_username ?? '')),
                    'title'           => self::decodeText((string)($row->title ?? '')),
                    'status'          => $status,
                    'released'        => $released,
                    'state_key'       => $stateKey,
                    'online_at'       => (string)($row->online_at ?? ''),
                    'action_date'     => $actionDate,
                    'language_key'    => (string)($row->movie_language ?? 'de'),
                    'language_label'  => self::LANGUAGE_LABELS[(string)($row->movie_language ?? 'de')] ?? (string)($row->movie_language ?? '-'),
                    'category_master' => (string)($row->category_master ?? ''),
                    'resolution'      => (string)($row->resolution ?? ''),
                    'playtime_str'    => (string)($row->playtime_string ?? ''),
                    'rejection_short' => $rejInfo['short'] ?? '',
                    'rejection_text'  => $rejInfo['text'] ?? '',
                ];
            }
        }

        $switcherCounts = self::getContentSwitcherCounts();

        return View::render('MoviesOnline', 'blocked', [
            'movies'         => $movies,
            'kpis'           => $kpis,
            'switcherCounts' => $switcherCounts,
        ]);
    }

    // =========================================================================
    // INTERNE HILFSMETHODEN
    // =========================================================================

    /**
     * Stellt sicher, dass die KI-Analyse-Tabelle und der Index auf movies.file_id existieren.
     */
    private static function ensureIndexesAndTables(): void
    {
        $flagDir = (defined('TEMP_DIR') && is_dir((string)TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();

        $aicFlag = $flagDir . '/.ec_aic_table_v1.flag';
        if (!@is_file($aicFlag) && function_exists('aic_ensure_table')) {
            aic_ensure_table();
            @file_put_contents($aicFlag, date('Y-m-d H:i:s'));
        }

        $idxFlag = $flagDir . '/.ec_movies_file_id_idx_v1.flag';
        if (!@is_file($idxFlag)) {
            $hasIdx = false;
            $rsIdx = p4c_query("SHOW INDEX FROM `movies`;", __FILE__, __LINE__);
            if ($rsIdx && p4c_num_rows($rsIdx) > 0) {
                while ($idxRow = p4c_fetch_object($rsIdx)) {
                    if ((string)($idxRow->Column_name ?? '') === 'file_id' && abs((int)($idxRow->Seq_in_index ?? 0)) === 1) {
                        $hasIdx = true;
                        break;
                    }
                }
            }
            if (!$hasIdx) {
                p4c_query("ALTER TABLE `movies` ADD INDEX `idx_ec_file_id` (`file_id`);", __FILE__, __LINE__);
            }
            @file_put_contents($idxFlag, date('Y-m-d H:i:s'));
        }
    }

    /**
     * Liest den gecachten Speicherplatz aller Online-Filme.
     */
    private static function getCachedOnlineStorageText(): string
    {
        $cacheDir   = (defined('TEMP_DIR') && is_dir((string)TEMP_DIR)) ? rtrim((string)TEMP_DIR, '/\\') : sys_get_temp_dir();
        $cacheFile  = $cacheDir . '/erocloud_online_movies_size_cache.txt';
        $legacyFile = sys_get_temp_dir() . '/erocloud_online_movies_size_cache.txt';

        $totalBytes = null;
        if (is_file($cacheFile)) {
            $totalBytes = (float)@file_get_contents($cacheFile);
        } elseif (is_file($legacyFile)) {
            $totalBytes = (float)@file_get_contents($legacyFile);
            @file_put_contents($cacheFile, (string)$totalBytes);
        }

        if ($totalBytes === null) {
            return 'bitte neu berechnen';
        }

        return self::formatBytes((int)$totalBytes);
    }

    /**
     * Berechnet die Ordnergröße rekursiv in Bytes.
     */
    public static function getFolderSize(string $folderPath): int
    {
        if (!is_dir($folderPath)) {
            return 0;
        }
        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folderPath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += (int)$file->getSize();
                }
            }
        } catch (\Throwable $e) {
            // Ignorieren falls Verzeichnis während Iteration nicht lesbar
        }
        return $size;
    }

    /**
     * Formatiert Bytes in KB / MB / GB / TB.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2, ',', '.') . ' TB';
        }
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' Bytes';
    }

    /**
     * Lädt die Zähler für die modulübergreifende Content-Navigationsleiste.
     *
     * @return array<string, int>
     */
    public static function getContentSwitcherCounts(?int $knownMoviesOnline = null): array
    {
        if ($knownMoviesOnline === null) {
            $rsMo = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_online`;", __FILE__, __LINE__);
            $knownMoviesOnline = $rsMo ? abs((int)(p4c_fetch_object($rsMo)->cnt ?? 0)) : 0;
        }

        $rsMc = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE (`movie_checked` = '0000-00-00 00:00:00' OR `movie_checked` = '000-00-00 00:00:00') AND `released` = '1' AND `convert_status` > '1';", __FILE__, __LINE__);
        $moviesChecking = $rsMc ? abs((int)(p4c_fetch_object($rsMc)->cnt ?? 0)) : 0;

        $rsMd = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released` = '0' AND `status` != 'deleted';", __FILE__, __LINE__);
        $moviesDrafts = $rsMd ? abs((int)(p4c_fetch_object($rsMd)->cnt ?? 0)) : 0;

        $rsMb = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` > '1' AND (`released` = '2' OR `status` != 'active');", __FILE__, __LINE__);
        $moviesBlocked = $rsMb ? abs((int)(p4c_fetch_object($rsMb)->cnt ?? 0)) : 0;

        $rsAo = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_online`;", __FILE__, __LINE__);
        $albumsOnline = $rsAo ? abs((int)(p4c_fetch_object($rsAo)->cnt ?? 0)) : 0;

        $rsAc = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE (`album_checked` = '0000-00-00 00:00:00' OR `album_checked` = '000-00-00 00:00:00') AND `released` = '1' AND `status` != 'deleted';", __FILE__, __LINE__);
        $albumsChecking = $rsAc ? abs((int)(p4c_fetch_object($rsAc)->cnt ?? 0)) : 0;

        return [
            'movies_online'   => $knownMoviesOnline,
            'movies_drafts'   => $moviesDrafts,
            'movies_blocked'  => $moviesBlocked,
            'movies_checking' => $moviesChecking,
            'albums_online'   => $albumsOnline,
            'albums_checking' => $albumsChecking,
        ];
    }

    /**
     * Lädt alle Käufe & Zugriffe (movies_access) für einen Film inkl. Käufer- und Shop-Auflösung.
     *
     * @return array<string, mixed>
     */
    private static function loadMoviePurchasesData(string $fileId): array
    {
        $rsBuys = p4c_query(
            "SELECT ma.*,
                    mem.`username` AS `buyer_username`
             FROM `movies_access` ma
             LEFT JOIN `members` mem ON mem.`id` = ma.`user_id`
             WHERE ma.`movie_id` = '" . p4c_escape_string($fileId) . "'
             ORDER BY ma.`buy_timestamp` DESC;",
            __FILE__,
            __LINE__
        );

        // Shops vorab als Map laden
        $shopsMap = [];
        $rsShops = p4c_query("SELECT `p4c_shop_id`, `domain` FROM `sites`;", __FILE__, __LINE__);
        if ($rsShops && p4c_num_rows($rsShops) > 0) {
            while ($sRow = p4c_fetch_object($rsShops)) {
                $sid = abs((int)($sRow->p4c_shop_id ?? 0));
                if ($sid > 0) {
                    $shopsMap[$sid] = (string)($sRow->domain ?? '');
                }
            }
        }

        $totalBuys       = 0;
        $totalPrice      = 0;
        $totalCommission = 0;
        $countStreaming  = 0;
        $countDownload   = 0;
        $lastBuy         = '-';
        $lastView        = '-';
        $rows            = [];

        if ($rsBuys && p4c_num_rows($rsBuys) > 0) {
            $totalBuys = (int)p4c_num_rows($rsBuys);
            $idx       = $totalBuys;

            while ($b = p4c_fetch_object($rsBuys)) {
                $price = (float)($b->movie_price ?? 0);
                $comm  = (float)($b->actor_commision ?? 0);
                $buyAs = (string)($b->buy_as ?? 'streaming');

                $totalPrice      += $price;
                $totalCommission += $comm;

                if ($buyAs === 'streaming') {
                    $countStreaming++;
                } elseif ($buyAs === 'download') {
                    $countDownload++;
                }

                $buyTsRaw = (string)($b->buy_timestamp ?? '');
                $buyTimeFormatted = ($buyTsRaw !== '' && $buyTsRaw !== '0000-00-00 00:00:00')
                    ? date('d.m.Y H:i:s', strtotime($buyTsRaw))
                    : '-';
                if ($lastBuy === '-' && $buyTimeFormatted !== '-') {
                    $lastBuy = $buyTimeFormatted;
                }

                $viewTsRaw = (string)($b->access_token_datetime ?? '');
                $viewTimeFormatted = ($viewTsRaw !== '' && $viewTsRaw !== '0000-00-00 00:00:00')
                    ? date('d.m.Y H:i:s', strtotime($viewTsRaw))
                    : '-';
                if ($lastView === '-' && $viewTimeFormatted !== '-') {
                    $lastView = $viewTimeFormatted;
                }

                $userId   = abs((int)($b->user_id ?? 0));
                $username = self::decodeText((string)($b->buyer_username ?? ''));
                if ($username === '') {
                    $username = 'ID: ' . $userId;
                }

                $shopId     = abs((int)($b->shop_id ?? 0));
                $shopDomain = $shopsMap[$shopId] ?? ('Shop #' . $shopId);

                $rows[] = [
                    'index'              => $idx,
                    'buy_time'           => $buyTimeFormatted,
                    'user_id'            => $userId,
                    'username'           => $username,
                    'shop_id'            => $shopId,
                    'shop_domain'        => $shopDomain,
                    'buy_type'           => $buyAs === 'streaming' ? 'Streaming' : 'Download',
                    'buy_as'             => $buyAs,
                    'price_coins'        => $price,
                    'commission_coins'   => $comm,
                    'commission_percent' => (float)($b->actor_commision_percent ?? 0),
                    'last_view'          => $viewTimeFormatted,
                ];
                $idx--;
            }
        }

        return [
            'total_buys'       => $totalBuys,
            'total_price'      => $totalPrice,
            'total_commission' => $totalCommission,
            'count_streaming'  => $countStreaming,
            'count_download'   => $countDownload,
            'last_buy'         => $lastBuy,
            'last_view'        => $lastView,
            'rows'             => $rows,
        ];
    }

    /**
     * Lädt BRAM-Compliance & Transkript-Daten eines Films.
     *
     * @param array<string, string> $texts
     * @return array<string, mixed>
     */
    private static function loadMovieAiAndTranscriptData(int $movieId, int $merchantId, string $fileId, string $movieDir, array $texts): array
    {
        $env = ['llm_ready' => false];

        $rsAi = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        $aiRow = ($rsAi && p4c_num_rows($rsAi) > 0) ? p4c_fetch_object($rsAi) : null;

        if (!$aiRow && function_exists('aic_analyze_texts')) {
            $rep      = aic_analyze_texts($texts, [], '', $env);
            $now      = date('Y-m-d H:i:s');
            $violJson = (string)json_encode($rep['violations'] ?? [], JSON_UNESCAPED_UNICODE);
            $subFile  = 'sub_' . $movieId . '_' . $fileId . '.vtt';

            p4c_query(
                "INSERT INTO `movies_ai_analysis`
                 (`movie_id`, `merchant_id`, `subtitle_filename`, `text_check_status`, `text_compliance`, `text_risk_score`, `text_engine_used`, `text_summary`, `text_violations_json`, `text_duration_sec`, `created_at`, `updated_at`)
                 VALUES (
                     '" . $movieId . "',
                     '" . $merchantId . "',
                     '" . p4c_escape_string($subFile) . "',
                     '2',
                     '" . p4c_escape_string((string)$rep['compliance_status']) . "',
                     '" . abs((int)$rep['risk_score']) . "',
                     '" . p4c_escape_string((string)$rep['engine_used']) . "',
                     '" . p4c_escape_string((string)$rep['summary_de']) . "',
                     '" . p4c_escape_string($violJson) . "',
                     '" . p4c_escape_string((string)$rep['duration_sec']) . "',
                     '" . $now . "',
                     '" . $now . "'
                 )
                 ON DUPLICATE KEY UPDATE
                     `text_check_status`    = '2',
                     `text_compliance`      = '" . p4c_escape_string((string)$rep['compliance_status']) . "',
                     `text_risk_score`      = '" . abs((int)$rep['risk_score']) . "',
                     `text_engine_used`     = '" . p4c_escape_string((string)$rep['engine_used']) . "',
                     `text_summary`         = '" . p4c_escape_string((string)$rep['summary_de']) . "',
                     `text_violations_json` = '" . p4c_escape_string($violJson) . "',
                     `updated_at`           = '" . $now . "';",
                __FILE__,
                __LINE__
            );

            $rsAi  = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
            $aiRow = ($rsAi && p4c_num_rows($rsAi) > 0) ? p4c_fetch_object($rsAi) : null;
        }

        $subFilename = $aiRow && !empty($aiRow->subtitle_filename)
            ? (string)$aiRow->subtitle_filename
            : ('sub_' . $movieId . '_' . $fileId . '.vtt');
        $vttFilePath = $movieDir . '/' . $subFilename;
        $hasVtt      = is_file($vttFilePath) && filesize($vttFilePath) > 0;
        $vttContent  = $hasVtt ? (string)@file_get_contents($vttFilePath) : '';

        $violations = [];
        if ($aiRow && !empty($aiRow->text_violations_json)) {
            $decodedViol = json_decode((string)$aiRow->text_violations_json, true);
            if (is_array($decodedViol)) {
                $violations = $decodedViol;
            }
        }

        $segments = [];
        if ($aiRow && !empty($aiRow->transcript_segments_json)) {
            $decodedSegs = json_decode((string)$aiRow->transcript_segments_json, true);
            if (is_array($decodedSegs)) {
                $segments = $decodedSegs;
            }
        }

        return [
            'subtitle_status'       => $aiRow ? abs((int)($aiRow->subtitle_status ?? 0)) : 0,
            'subtitle_filename'     => $subFilename,
            'has_vtt'               => $hasVtt,
            'vtt_content'           => $vttContent,
            'detected_language'     => $aiRow ? (string)($aiRow->detected_language ?? '') : '',
            'language_probability'  => $aiRow ? (float)($aiRow->language_probability ?? 0.0) : 0.0,
            'transcript_text'       => $aiRow ? self::decodeText((string)($aiRow->transcript_text ?? '')) : '',
            'transcript_segments'   => $segments,
            'subtitle_duration_sec' => $aiRow ? (float)($aiRow->subtitle_duration_sec ?? 0.0) : 0.0,
            'text_check_status'     => $aiRow ? abs((int)($aiRow->text_check_status ?? 0)) : 0,
            'text_compliance'       => $aiRow ? (string)($aiRow->text_compliance ?? 'pending') : 'pending',
            'text_risk_score'       => $aiRow ? abs((int)($aiRow->text_risk_score ?? 0)) : 0,
            'text_engine_used'      => $aiRow ? (string)($aiRow->text_engine_used ?? 'keyword_matrix') : 'keyword_matrix',
            'text_summary'          => $aiRow ? self::decodeText((string)($aiRow->text_summary ?? '')) : '',
            'text_violations'       => $violations,
            'error_message'         => $aiRow ? self::decodeText((string)($aiRow->error_message ?? '')) : '',
            'updated_at'            => $aiRow ? (string)($aiRow->updated_at ?? '') : '',
        ];
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
