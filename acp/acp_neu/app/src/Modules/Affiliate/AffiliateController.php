<?php
declare(strict_types=1);

namespace App\Modules\Affiliate;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) – Modul: Partnerprogramm / EroAds
 *
 * Ersetzt:
 *  - acp/includes/webmaster_partner.php (#/webmaster-partner)
 *  - acp/includes/banners.php + acp/includes/ajax/banners.php (#/banners)
 *  - acp/includes/banners_new.php + acp/includes/ajax/banners_new.php (#/banners-new)
 *
 * Architektur-Highlights:
 *  - 0 N+1 Queries: Kampagnen (ads_campaigns), Conversions (ads_conversions) und Impressionen
 *    (ads_impressions_YYYYMM) werden gebündelt vorab aggregiert.
 *  - Fehlerfreie Unterstützung sowohl für Monats-Auswahl (YYYY-MM) als auch Gesamtjahres-Auswahl (YYYY),
 *    inkl. Prüfung existierender Impressionstabellen via SHOW TABLES LIKE 'ads_impressions_%'.
 *  - On-Demand Conversion-Crawler (POST ?route=affiliate/import-conversions) und
 *    AJAX Banner-Moderation (POST ?route=banners/action für unlock, reject, delete) mit Audit-Log.
 */
final class AffiliateController
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
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return '–';
        }
        $ts = strtotime($raw);
        if ($ts === false || $ts <= 0) {
            return $raw;
        }
        return date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts);
    }

    // =========================================================================
    // 1. Aktive Webmaster (GET ?route=webmaster-partner)
    // =========================================================================
    public function webmasters(): string
    {
        $rawPeriod      = trim((string)$this->request->get('month', date('Y-m')));
        $selectedPeriod = $this->normalizePeriod($rawPeriod);
        $isFullYear     = strlen($selectedPeriod) === 4;

        $search = trim((string)$this->request->get('q', ''));
        $sort   = trim((string)$this->request->get('sort', 'conversions_desc'));

        $periodOptions = $this->buildPeriodOptions();

        if ($isFullYear) {
            $periodStart = $selectedPeriod . '-01-01 00:00:00';
            $periodEnd   = $selectedPeriod . '-12-31 23:59:59';
        } else {
            $periodStart = $selectedPeriod . '-01 00:00:00';
            $lastDay     = date('t', (int)strtotime($selectedPeriod . '-01'));
            $periodEnd   = $selectedPeriod . '-' . $lastDay . ' 23:59:59';
        }

        // 1. Conversions im gewählten Zeitraum pro p4c_partner_id aggregieren
        $escStart       = p4c_escape_string($periodStart);
        $escEnd         = p4c_escape_string($periodEnd);
        $conversionsMap = [];

        $rsConv = p4c_query(
            "SELECT
                `p4c_partner_id`,
                COUNT(*) AS `conversions_count`,
                COALESCE(SUM(`join`), 0) AS `joins_count`,
                COALESCE(SUM(`reg`), 0) AS `regs_count`,
                COALESCE(SUM(`first_payment`), 0) AS `first_payment_sum`,
                COALESCE(SUM(`follow_payment`), 0) AS `follow_payment_sum`,
                COALESCE(SUM(`again_credited_payment`), 0) AS `credited_payment_sum`,
                COALESCE(SUM(`canceled_payment`), 0) AS `canceled_payment_sum`
             FROM `ads_conversions`
             WHERE `date_time` >= '" . $escStart . "'
               AND `date_time` <= '" . $escEnd . "'
             GROUP BY `p4c_partner_id`;",
            __FILE__,
            __LINE__
        );

        if ($rsConv) {
            while ($row = p4c_fetch_object($rsConv)) {
                $pid         = (string)($row->p4c_partner_id ?? '');
                $firstPay    = (float)($row->first_payment_sum ?? 0.0);
                $followPay   = (float)($row->follow_payment_sum ?? 0.0);
                $creditedPay = (float)($row->credited_payment_sum ?? 0.0);
                $canceledPay = (float)($row->canceled_payment_sum ?? 0.0);
                $netRevenue  = $firstPay + $followPay + $creditedPay - $canceledPay;

                $conversionsMap[$pid] = [
                    'conversions' => (int)($row->conversions_count ?? 0),
                    'joins'       => (int)($row->joins_count ?? 0),
                    'regs'        => (int)($row->regs_count ?? 0),
                    'first_pay'   => $firstPay,
                    'follow_pay'  => $followPay,
                    'net_revenue' => $netRevenue,
                ];
            }
        }

        // 2. Impressionen im gewählten Zeitraum pro p4c_partner_id aus ads_impressions_YYYYMM laden
        $impressionsMap = $this->loadImpressionsByPartner($selectedPeriod, $isFullYear);

        // 3. Aktive Webmaster aus ads_campaigns inkl. Merchant-Stammdaten laden
        $aesKeyEsc = defined('AES_KEY') ? p4c_escape_string((string)AES_KEY) : '';
        $rsWm      = p4c_query(
            "SELECT
                c.`p4c_partner_id`,
                MAX(c.`merchant_id`) AS `campaign_merchant_id`,
                COUNT(*) AS `all_campaigns`,
                COALESCE(SUM(c.`count_clicks`), 0) AS `all_clicks`,
                COUNT(DISTINCT c.`site_id`) AS `distinct_sites`,
                MAX(c.`create_date_time`) AS `last_campaign_at`,
                m.`id` AS `merchant_db_id`,
                AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') AS `merchant_username`
             FROM `ads_campaigns` c
             LEFT JOIN `merchants` m ON m.`partner_id` = c.`p4c_partner_id`
             GROUP BY c.`p4c_partner_id`, m.`id`, m.`username`;",
            __FILE__,
            __LINE__
        );

        $webmasters = [];
        $totals     = [
            'webmasters'  => 0,
            'campaigns'   => 0,
            'clicks'      => 0,
            'conversions' => 0,
            'regs'        => 0,
            'impressions' => 0,
            'net_revenue' => 0.0,
        ];

        if ($rsWm) {
            while ($wm = p4c_fetch_object($rsWm)) {
                $pid          = $this->toUtf8((string)($wm->p4c_partner_id ?? ''));
                $username     = trim($this->toUtf8((string)($wm->merchant_username ?? '')));
                $merchantDbId = abs((int)($wm->merchant_db_id ?? 0));

                $convData = $conversionsMap[$pid] ?? [
                    'conversions' => 0,
                    'joins'       => 0,
                    'regs'        => 0,
                    'first_pay'   => 0.0,
                    'follow_pay'  => 0.0,
                    'net_revenue' => 0.0,
                ];
                $impressions    = (int)($impressionsMap[$pid] ?? 0);
                $campaignsCount = (int)($wm->all_campaigns ?? 0);
                $clicksCount    = (int)($wm->all_clicks ?? 0);

                $totals['webmasters']++;
                $totals['campaigns']   += $campaignsCount;
                $totals['clicks']      += $clicksCount;
                $totals['conversions'] += $convData['conversions'];
                $totals['regs']        += $convData['regs'];
                $totals['impressions'] += $impressions;
                $totals['net_revenue'] += $convData['net_revenue'];

                if ($search !== '') {
                    $needle   = mb_strtolower($search, 'UTF-8');
                    $haystack = mb_strtolower($pid . ' ' . $username, 'UTF-8');
                    if (mb_strpos($haystack, $needle, 0, 'UTF-8') === false) {
                        continue;
                    }
                }

                $webmasters[] = [
                    'p4c_partner_id'   => $pid,
                    'merchant_db_id'   => $merchantDbId,
                    'username'         => $username !== '' ? $username : 'unbekannt',
                    'has_merchant'     => $merchantDbId > 0 && $username !== '',
                    'all_campaigns'    => $campaignsCount,
                    'distinct_sites'   => (int)($wm->distinct_sites ?? 0),
                    'all_clicks'       => $clicksCount,
                    'conversions'      => $convData['conversions'],
                    'joins'            => $convData['joins'],
                    'regs'             => $convData['regs'],
                    'first_pay'        => $convData['first_pay'],
                    'follow_pay'       => $convData['follow_pay'],
                    'net_revenue'      => $convData['net_revenue'],
                    'impressions'      => $impressions,
                    'last_campaign_de' => $this->formatDateTimeDe((string)($wm->last_campaign_at ?? ''), false),
                ];
            }
        }

        usort($webmasters, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'impressions_desc' => ($b['impressions'] <=> $a['impressions']) ?: ($b['conversions'] <=> $a['conversions']),
                'clicks_desc'      => ($b['all_clicks'] <=> $a['all_clicks']) ?: ($b['conversions'] <=> $a['conversions']),
                'campaigns_desc'   => ($b['all_campaigns'] <=> $a['all_campaigns']) ?: ($b['conversions'] <=> $a['conversions']),
                'revenue_desc'     => ($b['net_revenue'] <=> $a['net_revenue']) ?: ($b['conversions'] <=> $a['conversions']),
                'partner_asc'      => strcasecmp((string)$a['p4c_partner_id'], (string)$b['p4c_partner_id']),
                default            => ($b['conversions'] <=> $a['conversions']) ?: ($b['impressions'] <=> $a['impressions']),
            };
        });

        // Banner-Zähler für Header-Navigation
        $pendingBannersCount = 0;
        $totalBannersCount   = 0;
        $rsBanCnt            = p4c_query(
            "SELECT
                SUM(CASE WHEN `new_filename` = '' THEN 1 ELSE 0 END) AS `published_cnt`,
                SUM(CASE WHEN `new_filename` != '' AND `rejected` = '0' THEN 1 ELSE 0 END) AS `pending_cnt`
             FROM `ads_media`;",
            __FILE__,
            __LINE__
        );
        if ($rsBanCnt && ($bCnt = p4c_fetch_object($rsBanCnt))) {
            $totalBannersCount   = (int)($bCnt->published_cnt ?? 0);
            $pendingBannersCount = (int)($bCnt->pending_cnt ?? 0);
        }

        return View::render('Affiliate', 'webmaster_partner', [
            'webmasters'          => $webmasters,
            'totals'              => $totals,
            'selectedPeriod'      => $selectedPeriod,
            'periodOptions'       => $periodOptions,
            'search'              => $search,
            'sort'                => $sort,
            'crawlDefaultDate'    => date('Y-m-d'),
            'pendingBannersCount' => $pendingBannersCount,
            'totalBannersCount'   => $totalBannersCount,
        ]);
    }

    // =========================================================================
    // 2. Conversion-Crawler manuell anstoßen (POST ?route=affiliate/import-conversions)
    // =========================================================================
    public function importConversions(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $rawDate = trim((string)$this->request->post('import_date', date('Y-m-d')));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            Response::error('Bitte gib ein gültiges Datum im Format JJJJ-MM-TT an.');
            return;
        }

        $importDate = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        if ($importDate > date('Y-m-d')) {
            Response::error('Das gewählte Datum darf nicht in der Zukunft liegen.');
            return;
        }

        $cronScript = (defined('SOURCEDIR') ? rtrim((string)SOURCEDIR, '/\\') : dirname(__DIR__, 4))
            . DIRECTORY_SEPARATOR . 'cronjobs' . DIRECTORY_SEPARATOR . 'import_conversions.php';

        if (!is_file($cronScript)) {
            Response::error('Das Conversion-Import-Skript wurde nicht gefunden.', 500);
            return;
        }

        $phpCli = '/usr/bin/php';
        if (is_executable('/opt/php-8.4/bin/php')) {
            $phpCli = '/opt/php-8.4/bin/php';
        } elseif (is_executable('/opt/php-8.0/bin/php')) {
            $phpCli = '/opt/php-8.0/bin/php';
        } elseif (defined('PHP_BINARY') && PHP_BINARY !== '' && is_executable(PHP_BINARY)) {
            $phpCli = PHP_BINARY;
        }

        $output = [];
        $retval = 0;
        if (function_exists('exec')) {
            $cmd = escapeshellcmd($phpCli) . ' ' . escapeshellarg($cronScript) . ' --date=' . escapeshellarg($importDate);
            @exec($cmd, $output, $retval);
        } else {
            Response::error('Die PHP-Funktion exec() ist auf diesem System deaktiviert.', 500);
            return;
        }

        $conversionsForDay = 0;
        $rsDay             = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `ads_conversions`
             WHERE `date_time` >= '" . p4c_escape_string($importDate . ' 00:00:00') . "'
               AND `date_time` <= '" . p4c_escape_string($importDate . ' 23:59:59') . "';",
            __FILE__,
            __LINE__
        );
        if ($rsDay && ($rDay = p4c_fetch_object($rsDay))) {
            $conversionsForDay = (int)($rDay->cnt ?? 0);
        }

        if (function_exists('log_action')) {
            log_action("EroAds Conversion-Crawler für den Tag {$importDate} manuell ausgeführt ({$conversionsForDay} Conversions erfasst).");
        }

        Response::json([
            'status'              => 'ok',
            'date'                => $importDate,
            'exit_code'           => (int)$retval,
            'conversions_for_day' => $conversionsForDay,
            'message'             => sprintf(
                'Conversion-Crawler für den %s erfolgreich ausgeführt (%d Conversions für diesen Tag gespeichert).',
                date('d.m.Y', (int)strtotime($importDate)),
                $conversionsForDay
            ),
        ]);
    }

    // =========================================================================
    // 3. Alle Werbebanner (GET ?route=banners)
    // =========================================================================
    public function banners(): string
    {
        $statusFilter    = trim((string)$this->request->get('status', 'published'));
        $siteIdFilter    = abs((int)$this->request->get('site_id', 0));
        $typeFilter      = strtolower(trim((string)$this->request->get('type', '')));
        $dimensionFilter = trim((string)$this->request->get('dim', ''));
        $search          = trim((string)$this->request->get('q', ''));
        $page            = max(1, abs((int)$this->request->get('page', 1)));
        $perPage         = 50;

        // KPI-Zähler über ads_media
        $kpis  = [
            'total_all'         => 0,
            'active_count'      => 0,
            'rejected_count'    => 0,
            'pending_new_count' => 0,
        ];
        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_all`,
                SUM(CASE WHEN `new_filename` = '' AND `rejected` = 0 THEN 1 ELSE 0 END) AS `active_count`,
                SUM(CASE WHEN `rejected` != 0 THEN 1 ELSE 0 END) AS `rejected_count`,
                SUM(CASE WHEN `new_filename` != '' AND `rejected` = 0 THEN 1 ELSE 0 END) AS `pending_new_count`
             FROM `ads_media`;",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && ($kpiObj = p4c_fetch_object($rsKpi))) {
            $kpis['total_all']         = (int)($kpiObj->total_all ?? 0);
            $kpis['active_count']      = (int)($kpiObj->active_count ?? 0);
            $kpis['rejected_count']    = (int)($kpiObj->rejected_count ?? 0);
            $kpis['pending_new_count'] = (int)($kpiObj->pending_new_count ?? 0);
        }

        // Domains mit Bannern für Filter-Dropdown
        $sitesList = [];
        $rsSites   = p4c_query(
            "SELECT s.`id`, s.`domain`, COUNT(a.`id`) AS `banner_count`
             FROM `sites` s
             INNER JOIN `ads_media` a ON a.`site_id` = s.`id`
             GROUP BY s.`id`, s.`domain`
             ORDER BY s.`domain` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsSites) {
            while ($sRow = p4c_fetch_object($rsSites)) {
                $sitesList[] = [
                    'id'           => (int)$sRow->id,
                    'domain'       => $this->toUtf8((string)$sRow->domain),
                    'banner_count' => (int)$sRow->banner_count,
                ];
            }
        }

        // Formate (width x height) für Filter-Dropdown
        $dimensionsList = [];
        $rsDims         = p4c_query(
            "SELECT `width`, `height`, COUNT(*) AS `cnt`
             FROM `ads_media`
             GROUP BY `width`, `height`
             ORDER BY `width` ASC, `height` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsDims) {
            while ($dRow = p4c_fetch_object($rsDims)) {
                $w                = (int)$dRow->width;
                $h                = (int)$dRow->height;
                $dimensionsList[] = [
                    'key'   => $w . 'x' . $h,
                    'label' => $w . ' × ' . $h . ' px (' . (int)$dRow->cnt . ')',
                ];
            }
        }

        // WHERE-Klausel aufbauen
        $where = ['1=1'];
        if ($statusFilter === 'published') {
            $where[] = "a.`new_filename` = ''";
        } elseif ($statusFilter === 'active') {
            $where[] = "a.`new_filename` = '' AND a.`rejected` = 0";
        } elseif ($statusFilter === 'rejected') {
            $where[] = "a.`rejected` != 0";
        } elseif ($statusFilter === 'pending_new') {
            $where[] = "a.`new_filename` != '' AND a.`rejected` = 0";
        }

        if ($siteIdFilter > 0) {
            $where[] = "a.`site_id` = '" . $siteIdFilter . "'";
        }

        if ($typeFilter !== '' && in_array($typeFilter, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $where[] = "LOWER(a.`type`) = '" . p4c_escape_string($typeFilter) . "'";
        }

        if ($dimensionFilter !== '' && preg_match('/^(\d+)x(\d+)$/', $dimensionFilter, $dm)) {
            $where[] = 'a.`width` = ' . abs((int)$dm[1]) . ' AND a.`height` = ' . abs((int)$dm[2]);
        }

        if ($search !== '') {
            $escSearch = p4c_escape_string($search);
            $where[]   = "(a.`file_id` LIKE '%" . $escSearch . "%' OR s.`domain` LIKE '%" . $escSearch . "%' OR a.`filename` LIKE '%" . $escSearch . "%')";
        }

        $whereSql = implode(' AND ', $where);

        $rsCount = p4c_query(
            "SELECT COUNT(*) AS `cnt`
             FROM `ads_media` a
             INNER JOIN `sites` s ON a.`site_id` = s.`id`
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

        $rsBanners = p4c_query(
            "SELECT
                a.`id`,
                a.`file_id`,
                a.`storage_location`,
                a.`site_id`,
                a.`type`,
                a.`width`,
                a.`height`,
                a.`upload_datetime`,
                a.`filename`,
                a.`new_filename`,
                a.`rejected`,
                s.`domain`,
                s.`partner_id`
             FROM `ads_media` a
             INNER JOIN `sites` s ON a.`site_id` = s.`id`
             WHERE {$whereSql}
             ORDER BY a.`rejected` DESC, a.`upload_datetime` DESC
             LIMIT {$offset}, {$perPage};",
            __FILE__,
            __LINE__
        );

        $banners = [];
        $adsBase = (defined('ADS_PATH') ? rtrim((string)ADS_PATH, '/\\') : '')
            . '/' . (defined('ADS_DEFAULT_DIR') ? trim((string)ADS_DEFAULT_DIR, '/\\') : 'banners');

        if ($rsBanners) {
            while ($b = p4c_fetch_object($rsBanners)) {
                $siteId      = abs((int)($b->site_id ?? 0));
                $filename    = (string)($b->filename ?? '');
                $newFilename = (string)($b->new_filename ?? '');
                $diskPath    = $adsBase . '/' . $siteId . '/' . $filename;

                $banners[] = [
                    'id'              => (int)$b->id,
                    'file_id'         => (string)($b->file_id ?? ''),
                    'site_id'         => $siteId,
                    'domain'          => $this->toUtf8((string)($b->domain ?? '')),
                    'partner_id'      => $this->toUtf8((string)($b->partner_id ?? '')),
                    'type'            => strtoupper((string)($b->type ?? '')),
                    'width'           => (int)($b->width ?? 0),
                    'height'          => (int)($b->height ?? 0),
                    'upload_datetime' => $this->formatDateTimeDe((string)($b->upload_datetime ?? '')),
                    'filename'        => $filename,
                    'new_filename'    => $newFilename,
                    'rejected'        => (int)($b->rejected ?? 0),
                    'file_exists'     => $filename !== '' && is_file($diskPath),
                ];
            }
        }

        return View::render('Affiliate', 'banners', [
            'banners'         => $banners,
            'kpis'            => $kpis,
            'sitesList'       => $sitesList,
            'dimensionsList'  => $dimensionsList,
            'statusFilter'    => $statusFilter,
            'siteIdFilter'    => $siteIdFilter,
            'typeFilter'      => $typeFilter,
            'dimensionFilter' => $dimensionFilter,
            'search'          => $search,
            'totalFiltered'   => $totalFiltered,
            'page'            => $page,
            'perPage'         => $perPage,
            'totalPages'      => $totalPages,
        ]);
    }

    // =========================================================================
    // 4. Neue Banner prüfen (GET ?route=banners-new)
    // =========================================================================
    public function bannersNew(): string
    {
        $rsPending = p4c_query(
            "SELECT
                a.`id`,
                a.`file_id`,
                a.`storage_location`,
                a.`site_id`,
                a.`type`,
                a.`width`,
                a.`height`,
                a.`upload_datetime`,
                a.`filename`,
                a.`new_filename`,
                a.`rejected`,
                s.`domain`,
                s.`partner_id`
             FROM `ads_media` a
             INNER JOIN `sites` s ON a.`site_id` = s.`id`
             WHERE a.`new_filename` != '' AND a.`rejected` = 0
             ORDER BY a.`upload_datetime` DESC;",
            __FILE__,
            __LINE__
        );

        $pendingBanners = [];
        $adsBase        = (defined('ADS_PATH') ? rtrim((string)ADS_PATH, '/\\') : '')
            . '/' . (defined('ADS_DEFAULT_DIR') ? trim((string)ADS_DEFAULT_DIR, '/\\') : 'banners');

        if ($rsPending) {
            while ($b = p4c_fetch_object($rsPending)) {
                $siteId      = abs((int)($b->site_id ?? 0));
                $filename    = (string)($b->filename ?? '');
                $newFilename = (string)($b->new_filename ?? '');
                $baseDir     = $adsBase . '/' . $siteId . '/';

                $pendingBanners[] = [
                    'id'              => (int)$b->id,
                    'file_id'         => (string)($b->file_id ?? ''),
                    'site_id'         => $siteId,
                    'domain'          => $this->toUtf8((string)($b->domain ?? '')),
                    'partner_id'      => $this->toUtf8((string)($b->partner_id ?? '')),
                    'type'            => strtoupper((string)($b->type ?? '')),
                    'width'           => (int)($b->width ?? 0),
                    'height'          => (int)($b->height ?? 0),
                    'upload_datetime' => $this->formatDateTimeDe((string)($b->upload_datetime ?? '')),
                    'filename'        => $filename,
                    'new_filename'    => $newFilename,
                    'rejected'        => (int)($b->rejected ?? 0),
                    'old_exists'      => $filename !== '' && is_file($baseDir . $filename),
                    'new_exists'      => $newFilename !== '' && is_file($baseDir . $newFilename),
                ];
            }
        }

        $totalBannersCount = 0;
        $rsTot             = p4c_query("SELECT COUNT(*) AS `cnt` FROM `ads_media` WHERE `new_filename` = '';", __FILE__, __LINE__);
        if ($rsTot && ($rTot = p4c_fetch_object($rsTot))) {
            $totalBannersCount = (int)($rTot->cnt ?? 0);
        }

        return View::render('Affiliate', 'banners_new', [
            'pendingBanners'    => $pendingBanners,
            'totalBannersCount' => $totalBannersCount,
        ]);
    }

    // =========================================================================
    // 5. Banner-Aktion ausführen: unlock, reject, delete (POST ?route=banners/action)
    // =========================================================================
    public function bannerAction(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $action = trim((string)$this->request->post('action', ''));
        $fileId = trim((string)$this->request->post('file_id', ''));

        if ($fileId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
            Response::error('Ungültige Banner-ID.');
            return;
        }

        if (!in_array($action, ['unlock', 'reject', 'delete'], true)) {
            Response::error('Ungültige Aktion.');
            return;
        }

        $escFileId = p4c_escape_string($fileId);
        $rsBanner  = p4c_query(
            "SELECT * FROM `ads_media` WHERE `file_id` = '" . $escFileId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        $bannerObj = $rsBanner ? p4c_fetch_object($rsBanner) : null;
        if (!$bannerObj) {
            Response::error('Das angeforderte Banner wurde nicht gefunden.', 404);
            return;
        }

        $siteId      = abs((int)($bannerObj->site_id ?? 0));
        $oldFilename = basename((string)($bannerObj->filename ?? ''));
        $newFilename = basename((string)($bannerObj->new_filename ?? ''));
        $baseDir     = (defined('ADS_PATH') ? rtrim((string)ADS_PATH, '/\\') : '')
            . '/' . (defined('ADS_DEFAULT_DIR') ? trim((string)ADS_DEFAULT_DIR, '/\\') : 'banners')
            . '/' . $siteId . '/';

        if ($action === 'unlock') {
            if ($newFilename === '') {
                Response::error('Für dieses Banner liegt keine neue Datei zur Freischaltung vor.');
                return;
            }

            $pathNew = $baseDir . $newFilename;
            if (!is_file($pathNew)) {
                Response::error('Die neue Banner-Datei wurde auf dem Speichersystem nicht gefunden.');
                return;
            }

            p4c_query(
                "UPDATE `ads_media` SET
                    `filename`        = '" . p4c_escape_string($newFilename) . "',
                    `new_filename`    = '',
                    `upload_datetime` = '" . date('Y-m-d H:i:s') . "',
                    `rejected`        = '0'
                 WHERE `file_id` = '" . $escFileId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );

            if ($oldFilename !== '' && $oldFilename !== $newFilename) {
                $pathOld = $baseDir . $oldFilename;
                if (is_file($pathOld)) {
                    @unlink($pathOld);
                }
            }

            if (function_exists('log_action')) {
                log_action("Werbebanner {$fileId} (Site #{$siteId}) freigeschaltet.");
            }

            Response::json([
                'status'  => 'ok',
                'file_id' => $fileId,
                'action'  => 'unlock',
                'message' => "Banner {$fileId} wurde erfolgreich freigeschaltet.",
            ]);
            return;
        }

        if ($action === 'reject') {
            p4c_query(
                "UPDATE `ads_media` SET
                    `rejected` = '3'
                 WHERE `file_id` = '" . $escFileId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );

            if (function_exists('log_action')) {
                log_action("Werbebanner {$fileId} (Site #{$siteId}) abgelehnt.");
            }

            Response::json([
                'status'  => 'ok',
                'file_id' => $fileId,
                'action'  => 'reject',
                'message' => "Banner {$fileId} wurde abgelehnt.",
            ]);
            return;
        }

        // action === 'delete'
        p4c_query(
            "DELETE FROM `ads_media` WHERE `file_id` = '" . $escFileId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if ($oldFilename !== '') {
            $pathOld = $baseDir . $oldFilename;
            if (is_file($pathOld)) {
                @unlink($pathOld);
            }
        }
        if ($newFilename !== '') {
            $pathNew = $baseDir . $newFilename;
            if (is_file($pathNew)) {
                @unlink($pathNew);
            }
        }

        if (function_exists('log_action')) {
            log_action("Werbebanner {$fileId} (Site #{$siteId}) gelöscht.");
        }

        Response::json([
            'status'  => 'ok',
            'file_id' => $fileId,
            'action'  => 'delete',
            'message' => "Banner {$fileId} wurde unwiderruflich gelöscht.",
        ]);
    }

    /**
     * Normalisiert den gewählten Zeitraum auf YYYY-MM (>= 2018-09) oder YYYY (>= 2018).
     */
    private function normalizePeriod(string $raw): string
    {
        $currentYear = (int)date('Y');
        $currentYm   = date('Y-m');

        if (preg_match('/^(\d{4})$/', $raw, $m)) {
            $year = (int)$m[1];
            if ($year >= 2018 && $year <= $currentYear) {
                return (string)$year;
            }
            return $currentYm;
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            $year  = (int)$m[1];
            $month = (int)$m[2];
            $ym    = sprintf('%04d-%02d', $year, $month);
            if ($month >= 1 && $month <= 12 && $ym >= '2018-09' && $ym <= $currentYm) {
                return $ym;
            }
        }

        return $currentYm;
    }

    /**
     * Erzeugt die Dropdown-Optionen für Monate (ab 2018-09) und Gesamtjahre.
     *
     * @return array<int, array{value: string, label: string, is_year: bool}>
     */
    private function buildPeriodOptions(): array
    {
        $options    = [];
        $startYear  = 2018;
        $startMonth = 9;
        $endYear    = (int)date('Y');
        $endMonth   = (int)date('n');

        $totalMonths   = (($endYear - $startYear) * 12) + ($endMonth - $startMonth);
        $lastYearAdded = null;

        for ($i = $totalMonths; $i >= 0; $i--) {
            $ts   = (int)strtotime(sprintf('2018-09-01 +%d months', $i));
            $year = date('Y', $ts);
            $ym   = date('Y-m', $ts);

            if ($lastYearAdded !== $year) {
                $options[] = [
                    'value'   => $year,
                    'label'   => 'Gesamtjahr ' . $year,
                    'is_year' => true,
                ];
                $lastYearAdded = $year;
            }

            $options[] = [
                'value'   => $ym,
                'label'   => $ym,
                'is_year' => false,
            ];
        }

        return $options;
    }

    /**
     * Lädt die Impressionen pro p4c_partner_id aus den existierenden ads_impressions_YYYYMM Tabellen.
     *
     * @return array<string, int>
     */
    private function loadImpressionsByPartner(string $selectedPeriod, bool $isFullYear): array
    {
        $existingTables = [];
        $rsTables       = p4c_query("SHOW TABLES LIKE 'ads_impressions_%';", __FILE__, __LINE__);
        if ($rsTables) {
            while ($row = p4c_fetch_array($rsTables)) {
                if (!empty($row[0])) {
                    $existingTables[(string)$row[0]] = true;
                }
            }
        }

        $targetTables = [];
        if ($isFullYear) {
            for ($m = 1; $m <= 12; $m++) {
                $tbl = sprintf('ads_impressions_%s%02d', $selectedPeriod, $m);
                if (isset($existingTables[$tbl])) {
                    $targetTables[] = $tbl;
                }
            }
        } else {
            $tbl = 'ads_impressions_' . str_replace('-', '', $selectedPeriod);
            if (isset($existingTables[$tbl])) {
                $targetTables[] = $tbl;
            }
        }

        if (empty($targetTables)) {
            return [];
        }

        $impressionsMap = [];
        foreach ($targetTables as $tbl) {
            if (!preg_match('/^ads_impressions_\d{6}$/', $tbl)) {
                continue;
            }
            $rsImp = p4c_query(
                "SELECT `p4c_partner_id`, COALESCE(SUM(`number_of_impressions`), 0) AS `total_imp`
                 FROM `" . $tbl . "`
                 GROUP BY `p4c_partner_id`;",
                __FILE__,
                __LINE__
            );
            if ($rsImp) {
                while ($impRow = p4c_fetch_object($rsImp)) {
                    $pid                  = (string)($impRow->p4c_partner_id ?? '');
                    $impressionsMap[$pid] = ($impressionsMap[$pid] ?? 0) + (int)($impRow->total_imp ?? 0);
                }
            }
        }

        return $impressionsMap;
    }
}
