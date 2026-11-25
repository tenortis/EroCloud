<?php
declare(strict_types=1);

namespace App\Modules\Websites;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) – Modul: Webseiten (#/sites & #/site?id=...)
 *
 * Ersetzt:
 *  - acp/includes/sites.php + acp/includes/ajax/sites.php
 *  - acp/includes/site.php
 *  - acp/includes/get_p4c_data/get_sites.php
 *
 * Architektur-Highlights:
 *  - 0 N+1 Queries: Banner-Zähler (ads_media), Cloud-User-Zähler (members) und
 *    Shop-Verkäufe (movies_access & photo_albums_access) werden gebündelt vorab aggregiert.
 *  - Detailansicht (#/site?id=...) unterstützt sowohl die interne Site-ID (sites.id),
 *    die 5-stellige Pay4Coins-Shop-ID (sites.p4c_shop_id) als auch den Domainnamen (sites.domain).
 *  - On-Demand Pay4Coins-Synchronisation (POST ?route=sites/sync-p4c) ersetzt den
 *    unkontrollierten Auto-Request beim Seitenaufruf.
 */
final class WebsitesController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Konvertiert potenziell ISO-8859-1-codierte DB-Strings sicher nach UTF-8
     */
    private function toUtf8(?string $str): string
    {
        $val = (string)($str ?? '');
        if ($val === '') {
            return '';
        }
        if (!mb_check_encoding($val, 'UTF-8')) {
            return (string)mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }
        return $val;
    }

    /**
     * Formatiert ein MySQL-Datum/Datetime ins deutsche Format
     */
    private function formatDateTimeDe(?string $dt, bool $withTime = true): string
    {
        $raw = trim((string)($dt ?? ''));
        if ($raw === '' || str_starts_with($raw, '0000-00-00') || str_starts_with($raw, '000-00-00')) {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false || $ts <= 0) {
            return $raw;
        }
        return date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts);
    }

    // =========================================================================
    // 1. Webseiten-Gesamtübersicht (GET ?route=sites)
    // =========================================================================
    public function list(): string
    {
        require_once SOURCEDIR . '/includes/p4c_sites_sync.inc.php';
        ec_p4c_sites_ensure_schema();

        $statusFilter  = trim((string)$this->request->get('status', 'all'));
        $eroadsFilter  = trim((string)$this->request->get('eroads', 'all'));
        $shopSysFilter = trim((string)$this->request->get('shop_system', 'all'));
        $bannerFilter  = trim((string)$this->request->get('banners', 'all'));
        $catFilter     = trim((string)$this->request->get('category', 'all'));
        $searchQuery   = trim((string)$this->request->get('q', ''));
        $sortBy        = trim((string)$this->request->get('sort', 'id_desc'));
        $page          = max(1, abs((int)$this->request->get('page', 1)));
        $perPage       = abs((int)$this->request->get('limit', 50));
        if (!in_array($perPage, [50, 100, 250, 500], true)) {
            $perPage = 50;
        }

        $aesKeyEsc = defined('AES_KEY') ? p4c_escape_string((string)AES_KEY) : '';

        // 1. Globale KPI-Zähler für alle Webseiten in 1 Query laden
        $kpi = [
            'total_sites'     => 0,
            'active_sites'    => 0,
            'inactive_sites'  => 0,
            'eroads_active'   => 0,
            'shop_erocms'     => 0,
            'shop_own'        => 0,
            'with_banners'    => 0,
            'total_banners'   => 0,
            'total_users'     => 0,
            'total_purchases' => 0,
        ];
        $unsyncedActiveCount = 0;

        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_sites`,
                SUM(CASE WHEN `status` = '1' THEN 1 ELSE 0 END) AS `active_sites`,
                SUM(CASE WHEN `status` != '1' THEN 1 ELSE 0 END) AS `inactive_sites`,
                SUM(CASE WHEN `is_eroads_active` = '1' THEN 1 ELSE 0 END) AS `eroads_active`,
                SUM(CASE WHEN `shop_system` = 'erocms' THEN 1 ELSE 0 END) AS `shop_erocms`,
                SUM(CASE WHEN `shop_system` = 'own' THEN 1 ELSE 0 END) AS `shop_own`,
                SUM(CASE WHEN `status` = '1' AND `p4c_shop_categories` IS NULL THEN 1 ELSE 0 END) AS `unsynced_active`
             FROM `sites`;",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && ($rowKpi = p4c_fetch_object($rsKpi))) {
            $kpi['total_sites']    = (int)($rowKpi->total_sites ?? 0);
            $kpi['active_sites']   = (int)($rowKpi->active_sites ?? 0);
            $kpi['inactive_sites'] = (int)($rowKpi->inactive_sites ?? 0);
            $kpi['eroads_active']  = (int)($rowKpi->eroads_active ?? 0);
            $kpi['shop_erocms']    = (int)($rowKpi->shop_erocms ?? 0);
            $kpi['shop_own']       = (int)($rowKpi->shop_own ?? 0);
            $unsyncedActiveCount   = (int)($rowKpi->unsynced_active ?? 0);
        }

        // 2. Vorab-Aggregation: Banner pro site_id (0 N+1 Queries!)
        $bannersBySiteId = [];
        $rsBanAgg = p4c_query(
            "SELECT
                `site_id`,
                COUNT(*) AS `total_cnt`,
                SUM(CASE WHEN `new_filename` != '' AND `rejected` = '0' THEN 1 ELSE 0 END) AS `pending_cnt`
             FROM `ads_media`
             GROUP BY `site_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsBanAgg) {
            while ($b = p4c_fetch_object($rsBanAgg)) {
                $sid = abs((int)$b->site_id);
                $cnt = (int)($b->total_cnt ?? 0);
                if ($sid > 0 && $cnt > 0) {
                    $bannersBySiteId[$sid] = [
                        'total'   => $cnt,
                        'pending' => (int)($b->pending_cnt ?? 0),
                    ];
                    $kpi['with_banners']++;
                    $kpi['total_banners'] += $cnt;
                }
            }
        }

        // 3. Vorab-Aggregation: Cloud-User pro p4c_shop_id
        $usersByShopId = [];
        $rsUsrAgg = p4c_query(
            "SELECT `p4c_shop_id`, COUNT(*) AS `usr_cnt`
             FROM `members`
             WHERE `p4c_shop_id` > 0
             GROUP BY `p4c_shop_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsUsrAgg) {
            while ($u = p4c_fetch_object($rsUsrAgg)) {
                $shId = abs((int)$u->p4c_shop_id);
                $cnt  = (int)($u->usr_cnt ?? 0);
                if ($shId > 0) {
                    $usersByShopId[$shId] = $cnt;
                    $kpi['total_users']  += $cnt;
                }
            }
        }

        // 4. Vorab-Aggregation: Film- & Fotoalben-Verkäufe pro shop_id
        $salesByShopId = [];
        $rsMovSales = p4c_query(
            "SELECT `shop_id`, COUNT(*) AS `buy_cnt`, COALESCE(SUM(`movie_price`), 0) AS `rev_cents`
             FROM `movies_access`
             WHERE `shop_id` > 0
             GROUP BY `shop_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsMovSales) {
            while ($ms = p4c_fetch_object($rsMovSales)) {
                $shId = abs((int)$ms->shop_id);
                $cnt  = (int)($ms->buy_cnt ?? 0);
                $rev  = (int)($ms->rev_cents ?? 0);
                $salesByShopId[$shId] = [
                    'movies'    => $cnt,
                    'albums'    => 0,
                    'total'     => $cnt,
                    'rev_cents' => $rev,
                ];
                $kpi['total_purchases'] += $cnt;
            }
        }

        $rsAlbSales = p4c_query(
            "SELECT `shop_id`, COUNT(*) AS `buy_cnt`, COALESCE(SUM(`album_price`), 0) AS `rev_cents`
             FROM `photo_albums_access`
             WHERE `shop_id` > 0
             GROUP BY `shop_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbSales) {
            while ($as = p4c_fetch_object($rsAlbSales)) {
                $shId = abs((int)$as->shop_id);
                $cnt  = (int)($as->buy_cnt ?? 0);
                $rev  = (int)($as->rev_cents ?? 0);
                if (!isset($salesByShopId[$shId])) {
                    $salesByShopId[$shId] = ['movies' => 0, 'albums' => 0, 'total' => 0, 'rev_cents' => 0];
                }
                $salesByShopId[$shId]['albums']    += $cnt;
                $salesByShopId[$shId]['total']     += $cnt;
                $salesByShopId[$shId]['rev_cents'] += $rev;
                $kpi['total_purchases']           += $cnt;
            }
        }

        // 5. WHERE-Bedingungen für Filterung aufbauen
        $where = ['1=1'];

        if ($statusFilter === 'active') {
            $where[] = "s.`status` = '1'";
        } elseif ($statusFilter === 'inactive') {
            $where[] = "s.`status` = '0'";
        }

        if ($eroadsFilter === 'active') {
            $where[] = "s.`is_eroads_active` = '1'";
        } elseif ($eroadsFilter === 'inactive') {
            $where[] = "s.`is_eroads_active` = '0'";
        }

        if ($shopSysFilter === 'erocms') {
            $where[] = "s.`shop_system` = 'erocms'";
        } elseif ($shopSysFilter === 'own') {
            $where[] = "s.`shop_system` = 'own'";
        }

        if ($bannerFilter === 'with_banners') {
            $siteIdsWithBanners = array_keys($bannersBySiteId);
            if (!empty($siteIdsWithBanners)) {
                $where[] = 's.`id` IN (' . implode(',', array_map('intval', $siteIdsWithBanners)) . ')';
            } else {
                $where[] = '1=0';
            }
        } elseif ($bannerFilter === 'without_banners') {
            $siteIdsWithBanners = array_keys($bannersBySiteId);
            if (!empty($siteIdsWithBanners)) {
                $where[] = 's.`id` NOT IN (' . implode(',', array_map('intval', $siteIdsWithBanners)) . ')';
            }
        }

        if ($catFilter === 'poppers') {
            $where[] = 's.`p4c_shop_categories` LIKE \'%"6":%\'';
        } elseif ($catFilter === 'kv_ns_digital') {
            $where[] = 's.`p4c_shop_categories` LIKE \'%"4":%\'';
        } elseif ($catFilter === 'kv_ns_shipping') {
            $where[] = 's.`p4c_shop_categories` LIKE \'%"3":%\'';
        } elseif ($catFilter === 'conflicts') {
            $where[] = '(s.`p4c_shop_categories` IS NOT NULL AND (('
                . 's.`p4c_shop_categories` LIKE \'%"6":%\' AND (s.`has_creditcard` = 1 OR s.`has_paysafecard` = 1)'
                . ') OR ('
                . 's.`p4c_shop_categories` LIKE \'%"4":%\' AND s.`has_creditcard` = 1'
                . ')))';
        } elseif ($catFilter === 'unsynced') {
            $where[] = 's.`p4c_shop_categories` IS NULL';
        }

        if ($searchQuery !== '') {
            $qEsc   = p4c_escape_string($searchQuery);
            $qClean = ltrim($searchQuery, '#');
            if (ctype_digit($qClean)) {
                $numId   = abs((int)$qClean);
                $where[] = "(s.`id` = '" . $numId . "' OR s.`p4c_shop_id` = '" . $numId . "' OR s.`partner_id` LIKE '%" . $qEsc . "%' OR s.`domain` LIKE '%" . $qEsc . "%')";
            } else {
                $where[] = "(s.`domain` LIKE '%" . $qEsc . "%'"
                    . " OR s.`partner_id` LIKE '%" . $qEsc . "%'"
                    . " OR s.`eroads_url` LIKE '%" . $qEsc . "%'"
                    . " OR CONVERT(AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') USING utf8mb4) LIKE '%" . $qEsc . "%')";
            }
        }

        $whereSql = implode(' AND ', $where);

        // Gesamtanzahl der gefilterten Treffer
        $rsCount = p4c_query(
            "SELECT COUNT(*) AS `cnt`
             FROM `sites` s
             LEFT JOIN `merchants` m ON m.`partner_id` = s.`partner_id`
             WHERE {$whereSql};",
            __FILE__,
            __LINE__
        );
        $totalFiltered = 0;
        if ($rsCount && ($rCnt = p4c_fetch_object($rsCount))) {
            $totalFiltered = (int)($rCnt->cnt ?? 0);
        }

        $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $orderSql = match ($sortBy) {
            'id_asc'       => 's.`id` ASC',
            'domain_asc'   => 's.`domain` ASC, s.`id` DESC',
            'domain_desc'  => 's.`domain` DESC, s.`id` DESC',
            'comm_desc'    => 's.`webmaster_commision` DESC, s.`id` DESC',
            'updated_desc' => 's.`last_update_timestamp` DESC, s.`id` DESC',
            default        => 's.`id` DESC',
        };

        $rsSites = p4c_query(
            "SELECT
                s.*,
                m.`id` AS `merchant_id`,
                AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') AS `merchant_username`
             FROM `sites` s
             LEFT JOIN `merchants` m ON m.`partner_id` = s.`partner_id`
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT {$offset}, {$perPage};",
            __FILE__,
            __LINE__
        );

        $sites = [];
        if ($rsSites) {
            while ($row = p4c_fetch_object($rsSites)) {
                $sid    = abs((int)$row->id);
                $shopId = abs((int)($row->p4c_shop_id ?? 0));
                $mid    = abs((int)($row->merchant_id ?? 0));
                $bStat  = $bannersBySiteId[$sid] ?? ['total' => 0, 'pending' => 0];
                $uCnt   = $usersByShopId[$shopId] ?? 0;
                $sStat  = $salesByShopId[$shopId] ?? ['movies' => 0, 'albums' => 0, 'total' => 0, 'rev_cents' => 0];
                $policy = ec_site_parse_policy($row);

                $sites[] = [
                    'id'                     => $sid,
                    'p4c_shop_id'            => $shopId,
                    'partner_id'             => $this->toUtf8((string)($row->partner_id ?? '')),
                    'merchant_id'            => $mid,
                    'merchant_username'      => $this->toUtf8((string)($row->merchant_username ?? '')),
                    'domain'                 => $this->toUtf8((string)($row->domain ?? '')),
                    'eroads_url'             => $this->toUtf8((string)($row->eroads_url ?? '')),
                    'status'                 => (int)($row->status ?? 0) === 1,
                    'is_eroads_active'       => (int)($row->is_eroads_active ?? 0) === 1,
                    'webmaster_commision'    => (float)($row->webmaster_commision ?? 0),
                    'shop_system'            => strtolower(trim((string)($row->shop_system ?? 'own'))),
                    'banners_total'          => $bStat['total'],
                    'banners_pending'        => $bStat['pending'],
                    'users_count'            => $uCnt,
                    'sales_total'            => $sStat['total'],
                    'sales_rev_eur'          => number_format($sStat['rev_cents'] / 100, 2, ',', '.') . ' €',
                    'updated_de'             => $this->formatDateTimeDe((string)($row->last_update_timestamp ?? '')),
                    'p4c_synced'             => $policy['synced'],
                    'p4c_categories'         => $policy['categories_map'],
                    'has_creditcard'         => $policy['has_creditcard'],
                    'has_paysafecard'        => $policy['has_paysafecard'],
                    'has_p4c_poppers'        => $policy['has_p4c_poppers'],
                    'has_p4c_kv_ns_digital'  => $policy['has_p4c_kv_ns_digital'],
                    'has_p4c_kv_ns_shipping' => $policy['has_p4c_kv_ns_shipping'],
                    'allow_poppers'          => $policy['allow_poppers'],
                    'allow_kv_ns'            => $policy['allow_kv_ns'],
                    'conflicts'              => $policy['conflicts'],
                ];
            }
        }

        return View::render('Websites', 'list', [
            'kpi'                 => $kpi,
            'sites'               => $sites,
            'totalFiltered'       => $totalFiltered,
            'page'                => $page,
            'perPage'             => $perPage,
            'totalPages'          => $totalPages,
            'hasUnsyncedSites'    => $unsyncedActiveCount > 0,
            'unsyncedActiveCount' => $unsyncedActiveCount,
            'filters'             => [
                'status'      => $statusFilter,
                'eroads'      => $eroadsFilter,
                'shop_system' => $shopSysFilter,
                'banners'     => $bannerFilter,
                'category'    => $catFilter,
                'q'           => $searchQuery,
                'sort'        => $sortBy,
            ],
        ]);
    }

    // =========================================================================
    // 2. Webseiten-Detailansicht (GET ?route=site&id=...)
    // =========================================================================
    public function detail(): string
    {
        require_once SOURCEDIR . '/includes/p4c_sites_sync.inc.php';
        ec_p4c_sites_ensure_schema();

        $rawId = trim((string)$this->request->get('id', ''));
        if ($rawId === '') {
            return '<div class="alert alert-warning m-4"><i class="bi bi-exclamation-triangle me-2"></i>Bitte wähle eine Webseite aus. <a href="#/sites" class="alert-link">Zurück zur Webseiten-Übersicht</a></div>';
        }

        $aesKeyEsc = defined('AES_KEY') ? p4c_escape_string((string)AES_KEY) : '';

        if (ctype_digit($rawId)) {
            $numId = abs((int)$rawId);
            $whereCond = "(s.`id` = '" . $numId . "' OR s.`p4c_shop_id` = '" . $numId . "')";
        } else {
            $esc = p4c_escape_string($rawId);
            $whereCond = "(s.`domain` = '" . $esc . "' OR s.`id` = '" . abs((int)$rawId) . "')";
        }

        // 1. Webseite + zugehörigen Merchant (inkl. entschlüsseltem API-Key) in 1 Query laden
        $rsSite = p4c_query(
            "SELECT
                s.*,
                m.`id` AS `merchant_id`,
                AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') AS `merchant_username`,
                AES_DECRYPT(m.`firstname`, '" . $aesKeyEsc . "') AS `merchant_firstname`,
                AES_DECRYPT(m.`surname`, '" . $aesKeyEsc . "') AS `merchant_surname`,
                AES_DECRYPT(m.`email`, '" . $aesKeyEsc . "') AS `merchant_email`,
                AES_DECRYPT(m.`api_key`, '" . $aesKeyEsc . "') AS `merchant_api_key`
             FROM `sites` s
             LEFT JOIN `merchants` m ON m.`partner_id` = s.`partner_id`
             WHERE {$whereCond}
             ORDER BY (CASE WHEN s.`id` = '" . abs((int)$rawId) . "' THEN 0 ELSE 1 END) ASC
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsSite || p4c_num_rows($rsSite) === 0) {
            return '<div class="alert alert-danger m-4"><i class="bi bi-x-octagon me-2"></i>Die Webseite <strong>' . View::e($rawId) . '</strong> wurde nicht gefunden. <a href="#/sites" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        $sRow   = p4c_fetch_object($rsSite);
        $siteId = abs((int)$sRow->id);
        $shopId = abs((int)($sRow->p4c_shop_id ?? 0));

        // 2. Vorherige / Nächste Webseite für schnelle Navigation
        $prevId = 0;
        $nextId = 0;
        $rsPrev = p4c_query("SELECT `id` FROM `sites` WHERE `id` < '" . $siteId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && ($rP = p4c_fetch_object($rsPrev))) {
            $prevId = abs((int)$rP->id);
        }
        $rsNext = p4c_query("SELECT `id` FROM `sites` WHERE `id` > '" . $siteId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsNext && ($rN = p4c_fetch_object($rsNext))) {
            $nextId = abs((int)$rN->id);
        }

        // 3. Zugeordnete Werbebanner (ads_media) laden
        $banners = [];
        $rsBanners = p4c_query(
            "SELECT *
             FROM `ads_media`
             WHERE `site_id` = '" . $siteId . "'
             ORDER BY `upload_datetime` DESC, `id` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsBanners) {
            while ($b = p4c_fetch_object($rsBanners)) {
                $fid        = $this->toUtf8((string)($b->file_id ?? ''));
                $newFile    = trim((string)($b->new_filename ?? ''));
                $isRejected = (int)($b->rejected ?? 0) === 1;
                $isPending  = ($newFile !== '' && !$isRejected);

                $banners[] = [
                    'id'          => abs((int)$b->id),
                    'file_id'     => $fid,
                    'type'        => strtoupper($this->toUtf8((string)($b->type ?? 'JPG'))),
                    'width'       => abs((int)($b->width ?? 0)),
                    'height'      => abs((int)($b->height ?? 0)),
                    'filename'    => $this->toUtf8((string)($b->filename ?? '')),
                    'new_file'    => $newFile,
                    'is_pending'  => $isPending,
                    'is_rejected' => $isRejected,
                    'upload_de'   => $this->formatDateTimeDe((string)($b->upload_datetime ?? '')),
                    'preview_url' => 'media.php?type=banner&file_id=' . rawurlencode($fid),
                    'new_url'     => $newFile !== '' ? ('media.php?type=banner&file_id=' . rawurlencode($fid) . '&variant=new') : '',
                ];
            }
        }

        // 4. Cloud-User & Shop-Käufe für diese Webseite (p4c_shop_id) laden
        $shopUsersCount = 0;
        $recentUsers    = [];
        if ($shopId > 0) {
            $rsUsrCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `members` WHERE `p4c_shop_id` = '" . $shopId . "';", __FILE__, __LINE__);
            if ($rsUsrCnt && ($rUc = p4c_fetch_object($rsUsrCnt))) {
                $shopUsersCount = (int)($rUc->cnt ?? 0);
            }

            $rsUsers = p4c_query(
                "SELECT `id`, `remote_member_id`, `username`, `email`, `amount_coins`, `lastonline`, `locked`
                 FROM `members`
                 WHERE `p4c_shop_id` = '" . $shopId . "'
                 ORDER BY `id` DESC
                 LIMIT 50;",
                __FILE__,
                __LINE__
            );
            if ($rsUsers) {
                while ($u = p4c_fetch_object($rsUsers)) {
                    $recentUsers[] = [
                        'id'               => abs((int)$u->id),
                        'remote_member_id' => $this->toUtf8((string)($u->remote_member_id ?? '')),
                        'username'         => $this->toUtf8((string)($u->username ?? '')),
                        'email'            => $this->toUtf8((string)($u->email ?? '')),
                        'amount_coins'     => (int)($u->amount_coins ?? 0),
                        'lastonline_de'    => $this->formatDateTimeDe((string)($u->lastonline ?? '')),
                        'locked'           => (int)($u->locked ?? 0) === 1,
                    ];
                }
            }
        }

        $recentSales     = [];
        $totalSalesCount = 0;
        $totalRevCents   = 0;
        if ($shopId > 0) {
            $rsMovStat = p4c_query(
                "SELECT COUNT(*) AS `cnt`, COALESCE(SUM(`movie_price`), 0) AS `rev`
                 FROM `movies_access`
                 WHERE `shop_id` = '" . $shopId . "';",
                __FILE__,
                __LINE__
            );
            if ($rsMovStat && ($rms = p4c_fetch_object($rsMovStat))) {
                $totalSalesCount += (int)($rms->cnt ?? 0);
                $totalRevCents   += (int)($rms->rev ?? 0);
            }

            $rsAlbStat = p4c_query(
                "SELECT COUNT(*) AS `cnt`, COALESCE(SUM(`album_price`), 0) AS `rev`
                 FROM `photo_albums_access`
                 WHERE `shop_id` = '" . $shopId . "';",
                __FILE__,
                __LINE__
            );
            if ($rsAlbStat && ($ras = p4c_fetch_object($rsAlbStat))) {
                $totalSalesCount += (int)($ras->cnt ?? 0);
                $totalRevCents   += (int)($ras->rev ?? 0);
            }

            $rsRecSales = p4c_query(
                "SELECT
                    ma.`id`, ma.`movie_id` AS `file_id`, ma.`user_id`, ma.`buy_as`,
                    ma.`buy_timestamp`, ma.`movie_price`, ma.`actor_commision`,
                    mo.`id` AS `online_id`, mo.`title`, mo.`actor_id`,
                    a.`username` AS `actor_name`
                 FROM `movies_access` ma
                 LEFT JOIN `movies_online` mo ON mo.`file_id` = ma.`movie_id`
                 LEFT JOIN `actors` a ON a.`id` = mo.`actor_id`
                 WHERE ma.`shop_id` = '" . $shopId . "'
                 ORDER BY ma.`id` DESC
                 LIMIT 50;",
                __FILE__,
                __LINE__
            );
            if ($rsRecSales) {
                while ($sl = p4c_fetch_object($rsRecSales)) {
                    $pRaw = (int)($sl->movie_price ?? 0);
                    $cRaw = (int)($sl->actor_commision ?? 0);
                    $recentSales[] = [
                        'id'         => abs((int)$sl->id),
                        'file_id'    => $this->toUtf8((string)($sl->file_id ?? '')),
                        'online_id'  => abs((int)($sl->online_id ?? 0)),
                        'title'      => $this->toUtf8((string)($sl->title ?? '')),
                        'actor_id'   => abs((int)($sl->actor_id ?? 0)),
                        'actor_name' => $this->toUtf8((string)($sl->actor_name ?? '')),
                        'user_id'    => abs((int)($sl->user_id ?? 0)),
                        'buy_as'     => (string)($sl->buy_as ?? 'streaming'),
                        'buy_dt_de'  => $this->formatDateTimeDe((string)($sl->buy_timestamp ?? '')),
                        'price_eur'  => number_format($pRaw / 100, 2, ',', '.') . ' €',
                        'comm_eur'   => number_format($cRaw / 100, 2, ',', '.') . ' €',
                    ];
                }
            }
        }

        $eroadsUrl   = $this->toUtf8((string)($sRow->eroads_url ?? ''));
        $merchApiKey = $this->toUtf8((string)($sRow->merchant_api_key ?? ''));
        $apiTestUrl  = '';
        if ($eroadsUrl !== '' && $merchApiKey !== '') {
            $sep        = str_contains($eroadsUrl, '?') ? '&' : '?';
            $apiTestUrl = $eroadsUrl . $sep . 'apikey=' . rawurlencode($merchApiKey) . '&date=' . date('Y-m-d');
        }

        $p4cAcpBase = defined('Pay4Coins_ACP_URL') ? rtrim((string)Pay4Coins_ACP_URL, '/') : 'https://acp.pay4coins.com';
        $adsBaseUrl = defined('ADS_URL') ? rtrim((string)ADS_URL, '/') : 'https://www1.erocloud.com';

        $site = [
            'id'                  => $siteId,
            'p4c_shop_id'         => $shopId,
            'p4c_shop_acp_url'    => $shopId > 0 ? ($p4cAcpBase . '/Shop/' . $shopId) : '',
            'partner_id'          => $this->toUtf8((string)($sRow->partner_id ?? '')),
            'merchant_id'         => abs((int)($sRow->merchant_id ?? 0)),
            'merchant_username'   => $this->toUtf8((string)($sRow->merchant_username ?? '')),
            'merchant_fullname'   => trim($this->toUtf8((string)($sRow->merchant_firstname ?? '') . ' ' . (string)($sRow->merchant_surname ?? ''))),
            'merchant_email'      => $this->toUtf8((string)($sRow->merchant_email ?? '')),
            'domain'              => $this->toUtf8((string)($sRow->domain ?? '')),
            'eroads_url'          => $eroadsUrl,
            'eroads_test_url'     => $apiTestUrl,
            'ads_base_url'        => $adsBaseUrl,
            'status'              => (int)($sRow->status ?? 0) === 1,
            'is_eroads_active'    => (int)($sRow->is_eroads_active ?? 0) === 1,
            'webmaster_commision' => (float)($sRow->webmaster_commision ?? 0),
            'shop_system'         => strtolower(trim((string)($sRow->shop_system ?? 'own'))),
            'number_of_articles'  => abs((int)($sRow->number_of_articles ?? 0)),
            'number_of_movies'    => abs((int)($sRow->number_of_movies ?? 0)),
            'number_of_albums'    => abs((int)($sRow->number_of_photoalbums ?? 0)),
            'updated_de'          => $this->formatDateTimeDe((string)($sRow->last_update_timestamp ?? '')),
            'conversions_de'      => $this->formatDateTimeDe((string)($sRow->import_conversions_updatetime ?? '')),
            'banners_count'       => count($banners),
            'users_count'         => $shopUsersCount,
            'sales_count'         => $totalSalesCount,
            'sales_rev_eur'       => number_format($totalRevCents / 100, 2, ',', '.') . ' €',
        ];

        $policy = ec_site_parse_policy($sRow);

        return View::render('Websites', 'detail', [
            'site'        => $site,
            'policy'      => $policy,
            'prevId'      => $prevId,
            'nextId'      => $nextId,
            'banners'     => $banners,
            'recentUsers' => $recentUsers,
            'recentSales' => $recentSales,
        ]);
    }

    // =========================================================================
    // 3. On-Demand Synchronisation mit Pay4Coins (POST ?route=sites/sync-p4c)
    // =========================================================================
    public function syncFromP4c(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        // PHP-Session-Lock sofort freigeben, damit F5 und parallele ACP-Tabs während des Syncs niemals blockieren
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        require_once SOURCEDIR . '/includes/p4c_sites_sync.inc.php';
        $syncRes = ec_p4c_sync_all_sites();

        if (empty($syncRes['ok'])) {
            Response::error(
                (string)($syncRes['error'] ?? 'Synchronisation mit Pay4Coins fehlgeschlagen.'),
                (int)($syncRes['http_status'] ?? 502)
            );
            return;
        }

        $inserted    = (int)($syncRes['inserted'] ?? 0);
        $updated     = (int)($syncRes['updated'] ?? 0);
        $deactivated = (int)($syncRes['deactivated'] ?? 0);
        $renamed     = (int)($syncRes['renamed'] ?? 0);

        if (function_exists('log_action')) {
            log_action("Webseiten-Abgleich mit Pay4Coins ausgeführt ({$inserted} neu, {$updated} aktiv aktualisiert, {$deactivated} deaktiviert, {$renamed} umbenannt).");
        }

        Response::json([
            'status'      => 'ok',
            'inserted'    => $inserted,
            'updated'     => $updated,
            'deactivated' => $deactivated,
            'renamed'     => $renamed,
            'message'     => (string)($syncRes['message'] ?? 'Pay4Coins-Webseiten-Abgleich erfolgreich.'),
        ]);
    }
}
