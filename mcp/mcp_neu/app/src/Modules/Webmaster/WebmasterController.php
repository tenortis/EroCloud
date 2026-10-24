<?php
declare(strict_types=1);

namespace App\Modules\Webmaster;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Partnerprogramm / Webmaster-Modul
 *
 * Bildet das bestehende Partnerprogramm (EroAds / Kunden werben, Kampagnen,
 * Eigene Banner verwalten, Deine Kunden, Alle Banner, Statistiken, Darsteller werben)
 * 1:1 in der modernen KKC-SPA-Architektur ab.
 */
class WebmasterController
{
    /**
     * Freigeschaltete Partner-IDs für das Partnerprogramm (analog Legacy-MCP).
     */
    public const ALLOWED_PARTNER_IDS = [
        'ABPP2HNMDG',
        'KJDJEEESAX',
        'CCRVWMVD67',
        '9SN2XTH2TU',
    ];

    /**
     * Erlaubte Standard-Bannergrößen (Breite x Höhe in Pixeln) inkl. Formatbezeichnung.
     *
     * @var array<string, array{width: int, height: int, label: string, group: string}>
     */
    public const STANDARD_BANNER_SIZES = [
        // Banner und Buttons
        '80x31'   => ['width' => 80,  'height' => 31,  'label' => 'Micro Bar',                   'group' => 'Banner und Buttons'],
        '120x90'  => ['width' => 120, 'height' => 90,  'label' => 'Button 1',                    'group' => 'Banner und Buttons'],
        '120x60'  => ['width' => 120, 'height' => 60,  'label' => 'Button 2',                    'group' => 'Banner und Buttons'],
        '120x240' => ['width' => 120, 'height' => 240, 'label' => 'Vertical Banner',             'group' => 'Banner und Buttons'],
        '125x125' => ['width' => 125, 'height' => 125, 'label' => 'Square Button',               'group' => 'Banner und Buttons'],
        '234x60'  => ['width' => 234, 'height' => 60,  'label' => 'Half Banner',                 'group' => 'Banner und Buttons'],
        '468x60'  => ['width' => 468, 'height' => 60,  'label' => 'Full Banner',                 'group' => 'Banner und Buttons'],
        '728x90'  => ['width' => 728, 'height' => 90,  'label' => 'Superbanner / Leaderboard',   'group' => 'Banner und Buttons'],
        // Rectangle
        '180x150' => ['width' => 180, 'height' => 150, 'label' => 'Rectangle',                   'group' => 'Rectangle'],
        '300x250' => ['width' => 300, 'height' => 250, 'label' => 'Medium Rectangle',            'group' => 'Rectangle'],
        '240x400' => ['width' => 240, 'height' => 400, 'label' => 'Square Pop-Up',               'group' => 'Rectangle'],
        '250x250' => ['width' => 250, 'height' => 250, 'label' => 'Vertical Rectangle',          'group' => 'Rectangle'],
        '400x400' => ['width' => 400, 'height' => 400, 'label' => 'AdLayer / Superstitial',      'group' => 'Rectangle'],
        // Skyscraper
        '160x600' => ['width' => 160, 'height' => 600, 'label' => 'Wide Skyscraper',             'group' => 'Skyscraper'],
        '160x650' => ['width' => 160, 'height' => 650, 'label' => 'Wide Skyscraper (650px)',     'group' => 'Skyscraper'],
        '120x600' => ['width' => 120, 'height' => 600, 'label' => 'Skyscraper',                  'group' => 'Skyscraper'],
        '200x600' => ['width' => 200, 'height' => 600, 'label' => 'Wide Skyscraper Alternative', 'group' => 'Skyscraper'],
        '300x600' => ['width' => 300, 'height' => 600, 'label' => 'Half Page Ad',                'group' => 'Skyscraper'],
        '420x600' => ['width' => 420, 'height' => 600, 'label' => 'Expandable Skyscraper',       'group' => 'Skyscraper'],
    ];

    private Request $request;
    private int $merchantId = 0;
    private string $partnerId = '';

    public function __construct(Request $request)
    {
        global $mysql;

        $this->request    = $request;
        $this->merchantId = abs((int)($_SESSION['merchant_id'] ?? 0));

        if ($this->merchantId > 0 && class_exists('Merchant')) {
            $merchant = new \Merchant($mysql, $this->merchantId);
            $this->partnerId = (string)$merchant->partner_id();
        }
    }

    /**
     * Prüft, ob der aktuelle Partner für das Partnerprogramm freigeschaltet ist.
     */
    private function checkAccess(bool $json = false): bool
    {
        if (in_array($this->partnerId, self::ALLOWED_PARTNER_IDS, true)) {
            return true;
        }

        if ($json) {
            Response::error('Das Partnerprogramm ist für deinen Account nicht freigeschaltet.', 403);
        }

        $html = View::render('Webmaster', 'access_denied', [
            'partnerId' => $this->partnerId,
        ]);
        Response::html($html);
        return false;
    }

    /**
     * Stellt sicher, dass die dynamischen Monats-Tabellen für den aktuellen Monat existieren.
     */
    private function ensureMonthlyTables(): void
    {
        $ym = date('Ym');

        p4c_query("CREATE TABLE IF NOT EXISTS `ads_impressions_" . $ym . "` (
            `id` int(12) NOT NULL auto_increment,
            `p4c_partner_id` varchar(10) NOT NULL,
            `media_id` int(15) NOT NULL,
            `campaign_id` varchar(10) NOT NULL,
            `number_of_impressions` int(15) NOT NULL default '0',
            `timestamp` date DEFAULT NULL,
            `site_id` int(12) NOT NULL,
            `url` varchar(255) NOT NULL default '',
            `referer` varchar(500) NOT NULL default '',
            PRIMARY KEY (`id`),
            KEY `timestamp` (`timestamp`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;", __FILE__, __LINE__);

        p4c_query("CREATE TABLE IF NOT EXISTS `ads_clicks_" . $ym . "` (
            `id` int(12) NOT NULL auto_increment,
            `p4c_partner_id` varchar(10) NOT NULL,
            `site_id` int(12) NOT NULL,
            `campaign_id` varchar(10) NOT NULL,
            `timestamp` datetime DEFAULT NULL,
            `ip` varchar(100) NOT NULL default '',
            `url` varchar(255) NOT NULL default '',
            `referer` varchar(500) NOT NULL default '',
            PRIMARY KEY (`id`),
            KEY `timestamp` (`timestamp`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;", __FILE__, __LINE__);
    }

    /**
     * Prüft, ob eine dynamische Monats-Tabelle in der Datenbank existiert.
     */
    private function tableExists(string $tableName): bool
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName) ?? '';
        if ($cleanName === '') {
            return false;
        }
        $rs = p4c_query("SHOW TABLES LIKE '" . p4c_escape_string($cleanName) . "';", __FILE__, __LINE__);
        return $rs && p4c_num_rows($rs) > 0;
    }

    /**
     * Löst einen gewählten Zeitraum ('YYYY-MM' oder 'YYYY') in existierende Monats-Tabellen auf.
     *
     * @return list<string>
     */
    private function resolveMonthlyTables(string $period, string $prefix): array
    {
        $tables = [];
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            $candidate = $prefix . '_' . $m[1] . $m[2];
            if ($this->tableExists($candidate)) {
                $tables[] = $candidate;
            }
        } elseif (preg_match('/^(\d{4})$/', $period, $m)) {
            $year = $m[1];
            for ($mo = 1; $mo <= 12; $mo++) {
                $candidate = sprintf('%s_%s%02d', $prefix, $year, $mo);
                if ($this->tableExists($candidate)) {
                    $tables[] = $candidate;
                }
            }
        }
        return $tables;
    }

    /**
     * Erzeugt die Liste aller auswählbaren Monate (ab 2018-09 bis heute) inkl. Jahres-Optionen.
     *
     * @return list<array{value: string, label: string, is_year: bool}>
     */
    private function getAvailablePeriods(): array
    {
        $start    = new \DateTime('2018-09-01');
        $end      = new \DateTime(date('Y-m-01'));
        $interval = new \DateInterval('P1M');
        $period   = new \DatePeriod($start, $interval, $end);

        $rawMonths = [];
        foreach ($period as $dt) {
            $ym = $dt->format('Y-m');
            if ($dt->format('m') === '01') {
                $rawMonths[] = $dt->format('Y');
            }
            $rawMonths[] = $ym;
        }

        $currentYm = date('Y-m');
        if (date('m') === '01' && !in_array(date('Y'), $rawMonths, true)) {
            $rawMonths[] = date('Y');
        }
        if (!in_array($currentYm, $rawMonths, true)) {
            $rawMonths[] = $currentYm;
        }

        $reversed = array_reverse($rawMonths);

        $monthNames = [
            '01' => 'Januar',   '02' => 'Februar', '03' => 'März',
            '04' => 'April',    '05' => 'Mai',     '06' => 'Juni',
            '07' => 'Juli',     '08' => 'August',  '09' => 'September',
            '10' => 'Oktober',  '11' => 'November','12' => 'Dezember',
        ];

        $result = [];
        foreach ($reversed as $val) {
            if (strlen($val) === 4) {
                $result[] = [
                    'value'   => $val,
                    'label'   => 'Gesamtjahr ' . $val,
                    'is_year' => true,
                ];
            } else {
                [$y, $m] = explode('-', $val);
                $label   = ($monthNames[$m] ?? $m) . ' ' . $y . ' (' . $val . ')';
                $result[] = [
                    'value'   => $val,
                    'label'   => $label,
                    'is_year' => false,
                ];
            }
        }

        return $result;
    }

    /**
     * Bereitet eine Zeile aus `ads_conversions` für die Tabellen-Ausgabe auf.
     *
     * @return array<string, mixed>
     */
    private function formatConversionRow(object $obj): array
    {
        $join            = abs((int)($obj->join ?? 0));
        $reg             = abs((int)($obj->reg ?? 0));
        $firstPayment    = (float)($obj->first_payment ?? 0.0);
        $followPayment   = (float)($obj->follow_payment ?? 0.0);
        $creditedPayment = (float)($obj->again_credited_payment ?? 0.0);
        $canceledPayment = (float)($obj->canceled_payment ?? 0.0);

        $statusLabel      = 'Unbekannt';
        $statusBadgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
        $amountFormatted  = '—';
        $amountClass      = 'text-muted';
        $netAmount        = 0.0;

        if ($join === 1) {
            $statusLabel      = 'Registrierung aufgerufen';
            $statusBadgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
        } elseif ($reg === 1) {
            $statusLabel      = 'Registriert';
            $statusBadgeClass = 'bg-info-subtle text-info border-info-subtle';
        } elseif ($firstPayment > 0) {
            $statusLabel      = 'Erstbuchung';
            $statusBadgeClass = 'bg-success-subtle text-success border-success-subtle';
            $amountFormatted  = '+ ' . number_format($firstPayment, 2, ',', '.') . ' EUR';
            $amountClass      = 'text-success fw-semibold';
            $netAmount        = $firstPayment;
        } elseif ($followPayment > 0) {
            $statusLabel      = 'Folgebuchung';
            $statusBadgeClass = 'bg-primary-subtle ec-text-primary border-primary-subtle';
            $amountFormatted  = '+ ' . number_format($followPayment, 2, ',', '.') . ' EUR';
            $amountClass      = 'text-success fw-semibold';
            $netAmount        = $followPayment;
        } elseif ($creditedPayment > 0) {
            $statusLabel      = 'Gutschrift';
            $statusBadgeClass = 'bg-success-subtle text-success border-success-subtle';
            $amountFormatted  = '+ ' . number_format($creditedPayment, 2, ',', '.') . ' EUR';
            $amountClass      = 'text-success fw-semibold';
            $netAmount        = $creditedPayment;
        } elseif ($canceledPayment > 0) {
            $statusLabel      = 'Storno';
            $statusBadgeClass = 'bg-danger-subtle text-danger border-danger-subtle';
            $amountFormatted  = '- ' . number_format($canceledPayment, 2, ',', '.') . ' EUR';
            $amountClass      = 'text-danger fw-semibold';
            $netAmount        = -$canceledPayment;
        }

        $wmid        = (string)($obj->wmid ?? '');
        $ownerPid    = (string)($obj->p4c_partner_id ?? '');
        $isSelfRef   = ($wmid !== '' && $wmid === $ownerPid);

        return [
            'id'                 => abs((int)($obj->id ?? 0)),
            'site_id'            => abs((int)($obj->site_id ?? 0)),
            'domain'             => (string)($obj->domain ?? ''),
            'date_time'          => (string)($obj->date_time ?? ''),
            'campaign_id'        => (string)($obj->campaign_id ?? ''),
            'username'           => (string)($obj->username ?? ''),
            'wmid'               => $wmid,
            'is_self_referred'   => $isSelfRef,
            'status_label'       => $statusLabel,
            'status_badge_class' => $statusBadgeClass,
            'amount_formatted'   => $amountFormatted,
            'amount_class'       => $amountClass,
            'net_amount'         => $netAmount,
        ];
    }

    /**
     * Erzeugt den Einbettungscode (HTML + Script) für ein EroAds-Banner.
     */
    public static function buildBannerEmbedCode(string $fileId, int $width, int $height, string $wmid = '', string $cid = ''): string
    {
        $adsUrl  = defined('ADS_URL') ? ADS_URL : 'https://www1.erocloud.net';
        $uriWmid = $wmid !== '' ? '/' . $wmid : '';
        $uriCid  = $cid !== '' ? '/' . $cid : '';

        return '<script src="' . $adsUrl . '/bc/' . $fileId . $uriWmid . $uriCid . '" type="text/javascript"></script>' . "\n"
            . '<ins class="adsbyerocloud" style="display:inline-block;width:' . $width . 'px;height:' . $height . 'px;" data-id="' . $fileId . '"></ins>';
    }

    // =========================================================================
    // 1. Kunden werben (#/webmaster-ads)
    // =========================================================================
    public function ads(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $this->ensureMonthlyTables();

        $partnerIdEsc = p4c_escape_string($this->partnerId);
        $currentYm    = date('Ym');
        $currentMonth = date('Y-m');

        // 1. Eigene aktive EroAds-Websites des Partners prüfen
        $ownSites = [];
        $rsOwnSites = p4c_query("SELECT * FROM `sites`
            WHERE `partner_id` = '" . $partnerIdEsc . "'
              AND `status` = '1'
              AND `is_eroads_active` = '1'
            ORDER BY `domain` ASC;", __FILE__, __LINE__);

        if ($rsOwnSites && p4c_num_rows($rsOwnSites) > 0) {
            while ($siteObj = p4c_fetch_object($rsOwnSites)) {
                $siteId = abs((int)($siteObj->id ?? 0));
                $bannerCount   = 0;
                $pendingCount  = 0;
                $rejectedCount = 0;

                $rsMedia = p4c_query("SELECT `new_filename`, `rejected`
                    FROM `ads_media`
                    WHERE `site_id` = '" . $siteId . "';", __FILE__, __LINE__);
                if ($rsMedia && p4c_num_rows($rsMedia) > 0) {
                    while ($mObj = p4c_fetch_object($rsMedia)) {
                        $bannerCount++;
                        $newFn = (string)($mObj->new_filename ?? '');
                        $rej   = (string)($mObj->rejected ?? '0');
                        if ($newFn !== '' && $rej === '0') {
                            $pendingCount++;
                        } elseif ($newFn !== '' && $rej === '3') {
                            $rejectedCount++;
                        }
                    }
                }

                $ownSites[] = [
                    'id'             => $siteId,
                    'domain'         => (string)($siteObj->domain ?? ''),
                    'banner_count'   => $bannerCount,
                    'pending_count'  => $pendingCount,
                    'rejected_count' => $rejectedCount,
                ];
            }
        }
        $hasOwnSites = count($ownSites) > 0;

        // 2. Alle bewerbbaren Websites laden
        $websites = [];
        $rsSites = p4c_query("SELECT * FROM `sites`
            WHERE `status` = '1'
              AND `is_eroads_active` = '1'
            ORDER BY `domain` ASC;", __FILE__, __LINE__);

        if ($rsSites && p4c_num_rows($rsSites) > 0) {
            while ($siteObj = p4c_fetch_object($rsSites)) {
                $websites[] = [
                    'id'                    => abs((int)($siteObj->id ?? 0)),
                    'domain'                => (string)($siteObj->domain ?? ''),
                    'partner_id'            => (string)($siteObj->partner_id ?? ''),
                    'is_own'                => ((string)($siteObj->partner_id ?? '') === $this->partnerId),
                    'number_of_movies'      => abs((int)($siteObj->number_of_movies ?? 0)),
                    'number_of_photoalbums' => abs((int)($siteObj->number_of_photoalbums ?? 0)),
                    'number_of_articles'    => abs((int)($siteObj->number_of_articles ?? 0)),
                    'webmaster_commision'   => abs((int)($siteObj->webmaster_commision ?? 0)),
                ];
            }
        }

        // 3. Kampagnen des Partners laden
        $campaigns = [];
        $hasImpressionsTable = $this->tableExists('ads_impressions_' . $currentYm);

        $rsCampaigns = p4c_query("SELECT `c`.*, `s`.`domain`, `s`.`status` AS `site_status`
            FROM `ads_campaigns` AS `c`
            LEFT JOIN `sites` AS `s` ON `c`.`site_id` = `s`.`id`
            WHERE `c`.`merchant_id` = '" . $this->merchantId . "'
            ORDER BY `c`.`create_date_time` DESC;", __FILE__, __LINE__);

        if ($rsCampaigns && p4c_num_rows($rsCampaigns) > 0) {
            while ($campObj = p4c_fetch_object($rsCampaigns)) {
                $cid    = (string)($campObj->campaign_id ?? '');
                $cidEsc = p4c_escape_string($cid);

                // Impressionen im aktuellen Monat
                $impressionsMonth = 0;
                if ($hasImpressionsTable && $cid !== '') {
                    $rsImp = p4c_query("SELECT SUM(`number_of_impressions`) AS `sum_imp`
                        FROM `ads_impressions_" . $currentYm . "`
                        WHERE `campaign_id` = '" . $cidEsc . "'
                          AND `p4c_partner_id` = '" . $partnerIdEsc . "';", __FILE__, __LINE__);
                    if ($rsImp && p4c_num_rows($rsImp) > 0) {
                        $impObj = p4c_fetch_object($rsImp);
                        $impressionsMonth = abs((int)($impObj->sum_imp ?? 0));
                    }
                }

                // Conversions gesamt
                $conversionsTotal = 0;
                if ($cid !== '') {
                    $rsConv = p4c_query("SELECT COUNT(*) AS `cnt`
                        FROM `ads_conversions`
                        WHERE `campaign_id` = '" . $cidEsc . "'
                          AND `wmid` = '" . $partnerIdEsc . "';", __FILE__, __LINE__);
                    if ($rsConv && p4c_num_rows($rsConv) > 0) {
                        $convObj = p4c_fetch_object($rsConv);
                        $conversionsTotal = abs((int)($convObj->cnt ?? 0));
                    }
                }

                $campaigns[] = [
                    'id'                => abs((int)($campObj->id ?? 0)),
                    'campaign_id'       => $cid,
                    'name'              => (string)($campObj->name ?? $cid),
                    'description'       => (string)($campObj->description ?? ''),
                    'site_id'           => abs((int)($campObj->site_id ?? 0)),
                    'domain'            => (string)($campObj->domain ?? 'Unbekannt'),
                    'site_active'       => ((int)($campObj->site_status ?? 0) === 1),
                    'impressions_month' => $impressionsMonth,
                    'count_clicks'      => abs((int)($campObj->count_clicks ?? 0)),
                    'conversions_total' => $conversionsTotal,
                    'create_date_time'  => (string)($campObj->create_date_time ?? ''),
                ];
            }
        }

        // 4. Deine Kunden (Conversions auf eigenen Websites des Partners)
        $ownCustomers = [];
        if ($hasOwnSites) {
            $rsCust = p4c_query("SELECT `ads_conversions`.*, `sites`.`domain`
                FROM `ads_conversions`
                INNER JOIN `sites` ON `ads_conversions`.`site_id` = `sites`.`id`
                WHERE `ads_conversions`.`p4c_partner_id` = '" . $partnerIdEsc . "'
                ORDER BY `ads_conversions`.`date_time` DESC;", __FILE__, __LINE__);

            if ($rsCust && p4c_num_rows($rsCust) > 0) {
                while ($custObj = p4c_fetch_object($rsCust)) {
                    $ownCustomers[] = $this->formatConversionRow($custObj);
                }
            }
        }

        // Aktiven Tab bestimmen (Legacy-Logik: Wenn Kampagnen existieren, standardmäßig Tab 'campaigns', sonst 'websites')
        $requestedTab = trim((string)$this->request->get('tab', ''));
        $filterDomain = trim((string)$this->request->get('website', ''));

        $validTabs = ['websites', 'campaigns'];
        if ($hasOwnSites) {
            $validTabs[] = 'my-banners';
            $validTabs[] = 'customers';
        }

        if ($filterDomain !== '' && $requestedTab === '') {
            $activeTab = 'websites';
        } elseif (in_array($requestedTab, $validTabs, true)) {
            $activeTab = $requestedTab;
        } else {
            $activeTab = count($campaigns) > 0 ? 'campaigns' : 'websites';
        }

        $html = View::render('Webmaster', 'ads', [
            'partnerId'           => $this->partnerId,
            'currentMonth'        => $currentMonth,
            'activeTab'           => $activeTab,
            'filterDomain'        => $filterDomain,
            'websites'            => $websites,
            'campaigns'           => $campaigns,
            'hasOwnSites'         => $hasOwnSites,
            'ownSites'            => $ownSites,
            'ownCustomers'        => $ownCustomers,
            'standardBannerSizes' => self::STANDARD_BANNER_SIZES,
        ]);

        Response::html($html);
    }

    // =========================================================================
    // 2. Werbemittel einer Website ohne Kampagne (#/webmaster-ads-domain?domain=...)
    // =========================================================================
    public function adsDomain(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $domain    = trim((string)$this->request->get('domain', ''));
        $siteIdReq = abs((int)$this->request->get('sid', 0));

        if ($domain !== '') {
            $rsSite = p4c_query("SELECT * FROM `sites`
                WHERE `domain` = '" . p4c_escape_string($domain) . "'
                  AND `status` = '1'
                  AND `is_eroads_active` = '1'
                LIMIT 1;", __FILE__, __LINE__);
        } elseif ($siteIdReq > 0) {
            $rsSite = p4c_query("SELECT * FROM `sites`
                WHERE `id` = '" . $siteIdReq . "'
                  AND `status` = '1'
                  AND `is_eroads_active` = '1'
                LIMIT 1;", __FILE__, __LINE__);
        } else {
            $rsSite = false;
        }

        if (!$rsSite || p4c_num_rows($rsSite) === 0) {
            Response::html('<div class="alert alert-warning m-4"><i class="bi bi-exclamation-triangle me-2"></i>Die ausgewählte Website wurde nicht gefunden oder nimmt aktuell nicht am Partnerprogramm teil. <a href="#/webmaster-ads?tab=websites" class="alert-link ms-2">Zurück zur Übersicht</a></div>');
            return;
        }

        $siteObj = p4c_fetch_object($rsSite);
        $siteId  = abs((int)($siteObj->id ?? 0));
        $siteDom = (string)($siteObj->domain ?? '');
        $comm    = abs((int)($siteObj->webmaster_commision ?? 0));

        $adsUrl      = defined('ADS_URL') ? ADS_URL : 'https://www1.erocloud.net';
        $trackingUrl = $adsUrl . '/l/' . $siteId . '/' . $this->partnerId;
        $textLinkCode = '<a href="' . $trackingUrl . '" target="_blank">' . $siteDom . '</a>';
        $xmlApiUrl   = 'https://' . $siteDom . '/api/open-v1/actor_infos';

        $banners = $this->loadSiteBanners($siteId, $this->partnerId, '');

        $html = View::render('Webmaster', 'ads_domain', [
            'siteId'       => $siteId,
            'domain'       => $siteDom,
            'commision'    => $comm,
            'partnerId'    => $this->partnerId,
            'trackingUrl'  => $trackingUrl,
            'textLinkCode' => $textLinkCode,
            'xmlApiUrl'    => $xmlApiUrl,
            'banners'      => $banners,
            'adsUrl'       => $adsUrl,
        ]);

        Response::html($html);
    }

    /**
     * Lädt alle freigegebenen Werbebanner einer Website inkl. fertigem Embed-Code.
     *
     * @return list<array<string, mixed>>
     */
    private function loadSiteBanners(int $siteId, string $wmid, string $cid = ''): array
    {
        $adsUrl  = defined('ADS_URL') ? ADS_URL : 'https://www1.erocloud.net';
        $banners = [];

        $rsAds = p4c_query("SELECT * FROM `ads_media`
            WHERE `site_id` = '" . abs((int)$siteId) . "'
              AND (`rejected` = '0' OR `rejected` = '3')
              AND (`filename` != `new_filename` OR `new_filename` = '')
            ORDER BY `upload_datetime` DESC;", __FILE__, __LINE__);

        if ($rsAds && p4c_num_rows($rsAds) > 0) {
            while ($adsObj = p4c_fetch_object($rsAds)) {
                $fileId = (string)($adsObj->file_id ?? '');
                $width  = abs((int)($adsObj->width ?? 0));
                $height = abs((int)($adsObj->height ?? 0));
                $type   = (string)($adsObj->type ?? '');

                $banners[] = [
                    'file_id'         => $fileId,
                    'width'           => $width,
                    'height'          => $height,
                    'size_label'      => $width . 'x' . $height,
                    'type'            => strtoupper($type),
                    'upload_datetime' => (string)($adsObj->upload_datetime ?? ''),
                    'image_url'       => $adsUrl . '/b/' . $fileId,
                    'embed_code'      => self::buildBannerEmbedCode($fileId, $width, $height, $wmid, $cid),
                ];
            }
        }

        return $banners;
    }

    // =========================================================================
    // 3. Neue Kampagne erstellen (#/webmaster-new-campaign?sid=...)
    // =========================================================================
    public function newCampaign(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $siteId = abs((int)$this->request->get('sid', 0));
        $rsSite = p4c_query("SELECT * FROM `sites`
            WHERE `id` = '" . $siteId . "'
              AND `status` = '1'
              AND `is_eroads_active` = '1'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsSite || p4c_num_rows($rsSite) === 0) {
            Response::html('<div class="alert alert-warning m-4"><i class="bi bi-exclamation-triangle me-2"></i>Bitte wähle zuerst eine aktive Website aus. <a href="#/webmaster-ads?tab=websites" class="alert-link ms-2">Zur Website-Auswahl</a></div>');
            return;
        }

        $siteObj = p4c_fetch_object($rsSite);
        $cid     = function_exists('randomString') ? (string)randomString(10) : strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));

        $html = View::render('Webmaster', 'new_campaign', [
            'siteId'    => abs((int)($siteObj->id ?? 0)),
            'domain'    => (string)($siteObj->domain ?? ''),
            'commision' => abs((int)($siteObj->webmaster_commision ?? 0)),
            'cid'       => $cid,
        ]);

        Response::html($html);
    }

    /**
     * POST: Legt eine neue Kampagne in `ads_campaigns` an.
     */
    public function createCampaign(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        if (!Csrf::validateRequest($this->request)) {
            Response::error('Ungültiger Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $siteId       = abs((int)$this->request->post('sid', 0));
        $cidRaw       = trim((string)$this->request->post('cid', ''));
        $cnameRaw     = trim((string)$this->request->post('cname', ''));
        $cdescription = trim(strip_tags((string)$this->request->post('cdescription', '')));

        $cid = preg_replace('/[^a-zA-Z0-9]/', '', $cidRaw) ?? '';
        if (strlen($cid) < 5 || strlen($cid) > 16) {
            $cid = function_exists('randomString') ? (string)randomString(10) : strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        // Prüfen, ob Website aktiv ist
        $rsSite = p4c_query("SELECT `id` FROM `sites`
            WHERE `id` = '" . $siteId . "'
              AND `status` = '1'
              AND `is_eroads_active` = '1'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsSite || p4c_num_rows($rsSite) === 0) {
            Response::error('Die ausgewählte Website existiert nicht oder ist nicht mehr aktiv.');
            return;
        }

        // Sonderzeichen im Kampagnennamen bereinigen (analog Legacy)
        $cname = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s\-_.]/u', '', $cnameRaw) ?? '';
        $cname = trim($cname);
        if ($cname === '') {
            $cname = $cid;
        }

        // Sicherstellen, dass Kampagnen-ID eindeutig ist
        $rsExists = p4c_query("SELECT `id` FROM `ads_campaigns`
            WHERE `campaign_id` = '" . p4c_escape_string($cid) . "'
            LIMIT 1;", __FILE__, __LINE__);
        if ($rsExists && p4c_num_rows($rsExists) > 0) {
            $cid = function_exists('randomString') ? (string)randomString(10) : strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        p4c_query("INSERT INTO `ads_campaigns` SET
            `merchant_id`      = '" . $this->merchantId . "',
            `p4c_partner_id`   = '" . p4c_escape_string($this->partnerId) . "',
            `site_id`          = '" . $siteId . "',
            `campaign_id`      = '" . p4c_escape_string($cid) . "',
            `name`             = '" . p4c_escape_string($cname) . "',
            `description`      = '" . p4c_escape_string($cdescription) . "',
            `create_date_time` = '" . date('Y-m-d H:i:s') . "';", __FILE__, __LINE__);

        Response::json([
            'status'  => 'ok',
            'cid'     => $cid,
            'message' => 'Deine Kampagne „' . $cname . '“ wurde erfolgreich angelegt.',
        ]);
    }

    // =========================================================================
    // 4. Kampagne bearbeiten & Kampagnen-Statistiken (#/webmaster-edit-campaign?cid=...)
    // =========================================================================
    public function editCampaign(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $this->ensureMonthlyTables();

        $cid    = trim((string)$this->request->get('cid', ''));
        $cidEsc = p4c_escape_string($cid);

        $rsCamp = p4c_query("SELECT `c`.*, `s`.`domain`, `s`.`webmaster_commision`, `s`.`status` AS `site_status`
            FROM `ads_campaigns` AS `c`
            LEFT JOIN `sites` AS `s` ON `c`.`site_id` = `s`.`id`
            WHERE `c`.`campaign_id` = '" . $cidEsc . "'
              AND `c`.`merchant_id` = '" . $this->merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsCamp || p4c_num_rows($rsCamp) === 0) {
            Response::html('<div class="alert alert-warning m-4"><i class="bi bi-exclamation-triangle me-2"></i>Die angeforderte Kampagne wurde nicht gefunden. <a href="#/webmaster-ads?tab=campaigns" class="alert-link ms-2">Zurück zu deinen Kampagnen</a></div>');
            return;
        }

        $campObj   = p4c_fetch_object($rsCamp);
        $siteId    = abs((int)($campObj->site_id ?? 0));
        $domain    = (string)($campObj->domain ?? '');
        $commision = abs((int)($campObj->webmaster_commision ?? 0));

        // Zeitraum (Monat oder Jahr) bestimmen
        $selectedPeriod = trim((string)$this->request->get('month', date('Y-m')));
        if (!preg_match('/^\d{4}(-\d{2})?$/', $selectedPeriod)) {
            $selectedPeriod = date('Y-m');
        }

        // Aktiver Tab (1/edit, 2/ads, 3/conversions, 4/clicks, 5/impressions)
        $tabRaw = trim((string)$this->request->get('tab', '1'));
        $tabMap = [
            '1'           => 'edit',
            'edit'        => 'edit',
            '2'           => 'ads',
            'ads'         => 'ads',
            '3'           => 'conversions',
            'conversions' => 'conversions',
            '4'           => 'clicks',
            'clicks'      => 'clicks',
            '5'           => 'impressions',
            'impressions' => 'impressions',
        ];
        $activeTab = $tabMap[$tabRaw] ?? 'edit';

        $partnerIdEsc = p4c_escape_string($this->partnerId);

        // Impressionen im aktuellen Monat (für KPI-Header in Tab 1)
        $currentYm = date('Ym');
        $currentMonthImpressions = 0;
        if ($this->tableExists('ads_impressions_' . $currentYm)) {
            $rsImpCur = p4c_query("SELECT SUM(`number_of_impressions`) AS `sum_imp`
                FROM `ads_impressions_" . $currentYm . "`
                WHERE `campaign_id` = '" . $cidEsc . "'
                  AND `p4c_partner_id` = '" . $partnerIdEsc . "';", __FILE__, __LINE__);
            if ($rsImpCur && p4c_num_rows($rsImpCur) > 0) {
                $impCurObj = p4c_fetch_object($rsImpCur);
                $currentMonthImpressions = abs((int)($impCurObj->sum_imp ?? 0));
            }
        }

        // Werbemittel für Tab 2
        $adsUrl       = defined('ADS_URL') ? ADS_URL : 'https://www1.erocloud.net';
        $trackingUrl  = $adsUrl . '/l/' . $siteId . '/' . $this->partnerId . '/' . $cid;
        $textLinkCode = '<a href="' . $trackingUrl . '" target="_blank">' . $domain . '</a>';
        $xmlApiUrl    = 'https://' . $domain . '/api/open-v1/actor_infos';
        $banners      = $this->loadSiteBanners($siteId, $this->partnerId, $cid);

        // Conversions im gewählten Zeitraum (Tab 3)
        $conversions    = [];
        $conversionsSum = 0.0;
        $rsConv = p4c_query("SELECT * FROM `ads_conversions`
            WHERE `date_time` LIKE '" . p4c_escape_string($selectedPeriod) . "%'
              AND `campaign_id` = '" . $cidEsc . "'
              AND `wmid` = '" . $partnerIdEsc . "'
            ORDER BY `date_time` DESC;", __FILE__, __LINE__);
        if ($rsConv && p4c_num_rows($rsConv) > 0) {
            while ($convObj = p4c_fetch_object($rsConv)) {
                $convObj->domain = $domain;
                $row = $this->formatConversionRow($convObj);
                $conversionsSum += (float)$row['net_amount'];
                $conversions[]   = $row;
            }
        }

        // Klicks im gewählten Zeitraum (Tab 4)
        $clickRows   = [];
        $totalClicks = 0;
        $clickTables = $this->resolveMonthlyTables($selectedPeriod, 'ads_clicks');
        $clicksByUrl = [];

        foreach ($clickTables as $tbl) {
            $rsClicks = p4c_query("SELECT COUNT(*) AS `clicks`, `url`
                FROM `" . $tbl . "`
                WHERE `campaign_id` = '" . $cidEsc . "'
                  AND `p4c_partner_id` = '" . $partnerIdEsc . "'
                GROUP BY `url`;", __FILE__, __LINE__);
            if ($rsClicks && p4c_num_rows($rsClicks) > 0) {
                while ($cObj = p4c_fetch_object($rsClicks)) {
                    $u   = (string)($cObj->url ?? 'none');
                    $cnt = abs((int)($cObj->clicks ?? 0));
                    $clicksByUrl[$u] = ($clicksByUrl[$u] ?? 0) + $cnt;
                    $totalClicks += $cnt;
                }
            }
        }
        arsort($clicksByUrl);
        foreach ($clicksByUrl as $u => $cnt) {
            $clickRows[] = [
                'url'    => $u,
                'clicks' => $cnt,
            ];
        }

        // Impressionen im gewählten Zeitraum (Tab 5)
        $impressionRows   = [];
        $totalImpressions = 0;
        $impTables        = $this->resolveMonthlyTables($selectedPeriod, 'ads_impressions');
        $impByUrl         = [];

        foreach ($impTables as $tbl) {
            $rsImp = p4c_query("SELECT SUM(`number_of_impressions`) AS `number_of_impressions`, `url`
                FROM `" . $tbl . "`
                WHERE `campaign_id` = '" . $cidEsc . "'
                  AND `p4c_partner_id` = '" . $partnerIdEsc . "'
                GROUP BY `url`;", __FILE__, __LINE__);
            if ($rsImp && p4c_num_rows($rsImp) > 0) {
                while ($iObj = p4c_fetch_object($rsImp)) {
                    $u   = (string)($iObj->url ?? 'none');
                    $cnt = abs((int)($iObj->number_of_impressions ?? 0));
                    $impByUrl[$u] = ($impByUrl[$u] ?? 0) + $cnt;
                    $totalImpressions += $cnt;
                }
            }
        }
        arsort($impByUrl);
        foreach ($impByUrl as $u => $cnt) {
            $impressionRows[] = [
                'url'         => $u,
                'impressions' => $cnt,
            ];
        }

        $reserveType  = (string)($campObj->reserve ?? 'blank');
        if (!in_array($reserveType, ['blank', 'image', 'color'], true)) {
            $reserveType = 'blank';
        }
        $reserveValue = (string)($campObj->reserve_value ?? '');

        $html = View::render('Webmaster', 'edit_campaign', [
            'campaign' => [
                'id'                        => abs((int)($campObj->id ?? 0)),
                'campaign_id'               => $cid,
                'name'                      => (string)($campObj->name ?? $cid),
                'description'               => (string)($campObj->description ?? ''),
                'site_id'                   => $siteId,
                'domain'                    => $domain,
                'site_active'               => ((int)($campObj->site_status ?? 0) === 1),
                'commision'                 => $commision,
                'count_clicks_alltime'      => abs((int)($campObj->count_clicks ?? 0)),
                'current_month_impressions' => $currentMonthImpressions,
                'reserve'                   => $reserveType,
                'reserve_value'             => $reserveValue,
                'create_date_time'          => (string)($campObj->create_date_time ?? ''),
            ],
            'activeTab'        => $activeTab,
            'selectedPeriod'   => $selectedPeriod,
            'availablePeriods' => $this->getAvailablePeriods(),
            'trackingUrl'      => $trackingUrl,
            'textLinkCode'     => $textLinkCode,
            'xmlApiUrl'        => $xmlApiUrl,
            'banners'          => $banners,
            'conversions'      => $conversions,
            'conversionsCount' => count($conversions),
            'conversionsSum'   => $conversionsSum,
            'clickRows'        => $clickRows,
            'totalClicks'      => $totalClicks,
            'impressionRows'   => $impressionRows,
            'totalImpressions' => $totalImpressions,
        ]);

        Response::html($html);
    }

    /**
     * POST: Aktualisiert eine bestehende Kampagne (`ads_campaigns`).
     */
    public function updateCampaign(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        if (!Csrf::validateRequest($this->request)) {
            Response::error('Ungültiger Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $cid          = trim((string)$this->request->post('cid', ''));
        $cnameRaw     = trim((string)$this->request->post('cname', ''));
        $cdescription = trim(strip_tags((string)$this->request->post('cdescription', '')));
        $reserve      = trim((string)$this->request->post('reserve', 'blank'));
        $valImage     = trim(strip_tags((string)$this->request->post('reserve_value_image', '')));
        $valColor     = trim(strip_tags((string)$this->request->post('reserve_value_color', '')));

        $rsCamp = p4c_query("SELECT `id`, `campaign_id` FROM `ads_campaigns`
            WHERE `campaign_id` = '" . p4c_escape_string($cid) . "'
              AND `merchant_id` = '" . $this->merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsCamp || p4c_num_rows($rsCamp) === 0) {
            Response::error('Die Kampagne wurde nicht gefunden.');
            return;
        }

        $cname = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s\-_.]/u', '', $cnameRaw) ?? '';
        $cname = trim($cname);
        if ($cname === '') {
            $cname = $cid;
        }

        if (!in_array($reserve, ['blank', 'image', 'color'], true)) {
            $reserve = 'blank';
        }

        $reserveValue = '';
        if ($reserve === 'image') {
            if ($valImage === '') {
                $reserve = 'blank';
            } elseif (!filter_var($valImage, FILTER_VALIDATE_URL)) {
                Response::error('Bitte gib eine gültige Bild-URL für die Ersatzanzeige ein (z. B. https://...).');
                return;
            } else {
                $reserveValue = $valImage;
            }
        } elseif ($reserve === 'color') {
            if ($valColor === '') {
                $reserve = 'blank';
            } else {
                $reserveValue = $valColor;
            }
        }

        p4c_query("UPDATE `ads_campaigns` SET
            `name`          = '" . p4c_escape_string($cname) . "',
            `reserve`       = '" . p4c_escape_string($reserve) . "',
            `reserve_value` = '" . p4c_escape_string($reserveValue) . "',
            `description`   = '" . p4c_escape_string($cdescription) . "'
        WHERE `campaign_id` = '" . p4c_escape_string($cid) . "'
          AND `merchant_id` = '" . $this->merchantId . "'
        LIMIT 1;", __FILE__, __LINE__);

        Response::json([
            'status'  => 'ok',
            'message' => 'Deine Kampagne wurde erfolgreich gespeichert.',
        ]);
    }

    /**
     * POST: Löscht eine Kampagne (`ads_campaigns`).
     */
    public function deleteCampaign(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        if (!Csrf::validateRequest($this->request)) {
            Response::error('Ungültiger Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $cid = trim((string)$this->request->post('cid', ''));

        $rsCamp = p4c_query("SELECT `id`, `name` FROM `ads_campaigns`
            WHERE `campaign_id` = '" . p4c_escape_string($cid) . "'
              AND `merchant_id` = '" . $this->merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsCamp || p4c_num_rows($rsCamp) === 0) {
            Response::error('Die Kampagne wurde nicht gefunden.');
            return;
        }

        p4c_query("DELETE FROM `ads_campaigns`
            WHERE `campaign_id` = '" . p4c_escape_string($cid) . "'
              AND `merchant_id` = '" . $this->merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        Response::json([
            'status'  => 'ok',
            'message' => 'Deine Kampagne wurde gelöscht.',
        ]);
    }

    // =========================================================================
    // 5. Alle Banner (#/webmaster-newads)
    // =========================================================================
    public function newAds(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $adsUrl  = defined('ADS_URL') ? ADS_URL : 'https://www1.erocloud.net';
        $banners = [];
        $domains = [];
        $sizes   = [];
        $types   = [];

        $rsAds = p4c_query("SELECT
                `ads_media`.`file_id`,
                `ads_media`.`width`,
                `ads_media`.`height`,
                `ads_media`.`site_id`,
                `ads_media`.`type`,
                `ads_media`.`upload_datetime`,
                `sites`.`domain`
            FROM `ads_media`
            INNER JOIN `sites` ON `ads_media`.`site_id` = `sites`.`id`
            WHERE `ads_media`.`new_filename` = ''
              AND `ads_media`.`rejected` = '0'
              AND `sites`.`status` = '1'
              AND `sites`.`is_eroads_active` = '1'
            ORDER BY `ads_media`.`upload_datetime` DESC;", __FILE__, __LINE__);

        $thirtyDaysAgo = strtotime('-30 days');

        if ($rsAds && p4c_num_rows($rsAds) > 0) {
            while ($adsObj = p4c_fetch_object($rsAds)) {
                $fileId    = (string)($adsObj->file_id ?? '');
                $width     = abs((int)($adsObj->width ?? 0));
                $height    = abs((int)($adsObj->height ?? 0));
                $sizeLabel = $width . 'x' . $height;
                $type      = strtolower((string)($adsObj->type ?? ''));
                $domain    = (string)($adsObj->domain ?? '');
                $uploaded  = (string)($adsObj->upload_datetime ?? '');
                $ts        = $uploaded !== '' ? strtotime($uploaded) : false;

                $domains[$domain]   = $domain;
                $sizes[$sizeLabel]  = $sizeLabel;
                if ($type !== '') {
                    $types[$type] = strtoupper($type);
                }

                $banners[] = [
                    'file_id'         => $fileId,
                    'site_id'         => abs((int)($adsObj->site_id ?? 0)),
                    'domain'          => $domain,
                    'type'            => strtoupper($type),
                    'width'           => $width,
                    'height'          => $height,
                    'size_label'      => $sizeLabel,
                    'upload_datetime' => $uploaded,
                    'upload_date_fmt' => $ts ? date('d.m.Y', $ts) : '—',
                    'is_new'          => ($ts !== false && $ts >= $thirtyDaysAgo),
                    'image_url'       => $adsUrl . '/b/' . $fileId,
                    'embed_code'      => self::buildBannerEmbedCode($fileId, $width, $height, $this->partnerId, ''),
                ];
            }
        }

        ksort($domains);
        ksort($sizes);
        ksort($types);

        $html = View::render('Webmaster', 'newads', [
            'partnerId' => $this->partnerId,
            'banners'   => $banners,
            'domains'   => array_values($domains),
            'sizes'     => array_values($sizes),
            'types'     => $types,
        ]);

        Response::html($html);
    }

    // =========================================================================
    // 6. Statistiken (#/webmaster-stats)
    // =========================================================================
    public function stats(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $this->ensureMonthlyTables();

        $selectedPeriod = trim((string)$this->request->get('month', date('Y-m')));
        if (!preg_match('/^\d{4}(-\d{2})?$/', $selectedPeriod)) {
            $selectedPeriod = date('Y-m');
        }

        $tabRaw = trim((string)$this->request->get('tab', 'clicks'));
        $tabMap = [
            '1'           => 'clicks',
            'clicks'      => 'clicks',
            '2'           => 'impressions',
            'impressions' => 'impressions',
            '3'           => 'conversions',
            'conversions' => 'conversions',
        ];
        $activeTab = $tabMap[$tabRaw] ?? 'clicks';

        $partnerIdEsc = p4c_escape_string($this->partnerId);

        // 1. Klicks gruppiert nach Herkunfts-URL
        $clickRows   = [];
        $totalClicks = 0;
        $clickTables = $this->resolveMonthlyTables($selectedPeriod, 'ads_clicks');
        $clicksByUrl = [];

        foreach ($clickTables as $tbl) {
            $rsClicks = p4c_query("SELECT COUNT(*) AS `clicks`, `url`
                FROM `" . $tbl . "`
                WHERE `p4c_partner_id` = '" . $partnerIdEsc . "'
                GROUP BY `url`;", __FILE__, __LINE__);
            if ($rsClicks && p4c_num_rows($rsClicks) > 0) {
                while ($cObj = p4c_fetch_object($rsClicks)) {
                    $u   = (string)($cObj->url ?? 'none');
                    $cnt = abs((int)($cObj->clicks ?? 0));
                    $clicksByUrl[$u] = ($clicksByUrl[$u] ?? 0) + $cnt;
                    $totalClicks += $cnt;
                }
            }
        }
        arsort($clicksByUrl);
        foreach ($clicksByUrl as $u => $cnt) {
            $clickRows[] = [
                'url'    => $u,
                'clicks' => $cnt,
            ];
        }

        // 2. Impressionen gruppiert nach Herkunfts-URL
        $impressionRows   = [];
        $totalImpressions = 0;
        $impTables        = $this->resolveMonthlyTables($selectedPeriod, 'ads_impressions');
        $impByUrl         = [];

        foreach ($impTables as $tbl) {
            $rsImp = p4c_query("SELECT SUM(`number_of_impressions`) AS `number_of_impressions`, `url`
                FROM `" . $tbl . "`
                WHERE `p4c_partner_id` = '" . $partnerIdEsc . "'
                GROUP BY `url`;", __FILE__, __LINE__);
            if ($rsImp && p4c_num_rows($rsImp) > 0) {
                while ($iObj = p4c_fetch_object($rsImp)) {
                    $u   = (string)($iObj->url ?? 'none');
                    $cnt = abs((int)($iObj->number_of_impressions ?? 0));
                    $impByUrl[$u] = ($impByUrl[$u] ?? 0) + $cnt;
                    $totalImpressions += $cnt;
                }
            }
        }
        arsort($impByUrl);
        foreach ($impByUrl as $u => $cnt) {
            $impressionRows[] = [
                'url'         => $u,
                'impressions' => $cnt,
            ];
        }

        // 3. Conversions im gewählten Zeitraum
        $conversions    = [];
        $conversionsSum = 0.0;
        $rsConv = p4c_query("SELECT `ads_conversions`.*, `sites`.`domain`
            FROM `ads_conversions`
            INNER JOIN `sites` ON `ads_conversions`.`site_id` = `sites`.`id`
            WHERE `ads_conversions`.`date_time` LIKE '" . p4c_escape_string($selectedPeriod) . "%'
              AND `ads_conversions`.`wmid` = '" . $partnerIdEsc . "'
            ORDER BY `ads_conversions`.`date_time` DESC;", __FILE__, __LINE__);

        if ($rsConv && p4c_num_rows($rsConv) > 0) {
            while ($convObj = p4c_fetch_object($rsConv)) {
                $row = $this->formatConversionRow($convObj);
                $conversionsSum += (float)$row['net_amount'];
                $conversions[]   = $row;
            }
        }

        $html = View::render('Webmaster', 'stats', [
            'activeTab'        => $activeTab,
            'selectedPeriod'   => $selectedPeriod,
            'availablePeriods' => $this->getAvailablePeriods(),
            'clickRows'        => $clickRows,
            'totalClicks'      => $totalClicks,
            'impressionRows'   => $impressionRows,
            'totalImpressions' => $totalImpressions,
            'conversions'      => $conversions,
            'conversionsCount' => count($conversions),
            'conversionsSum'   => $conversionsSum,
        ]);

        Response::html($html);
    }

    // =========================================================================
    // 7. Darsteller werben (#/webmaster-advertise-partner)
    // =========================================================================
    public function advertisePartner(): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $actorLinks = [];
        $rsActors = p4c_query("SELECT `id`, `username`
            FROM `actors`
            WHERE `merchant_id` = '" . $this->merchantId . "'
              AND `status` != 'deleted'
            ORDER BY `username` ASC;", __FILE__, __LINE__);

        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($actObj = p4c_fetch_object($rsActors)) {
                $uname = (string)($actObj->username ?? '');
                if ($uname === '') {
                    continue;
                }
                $actorLinks[] = [
                    'username' => $uname,
                    'alias'    => $uname,
                    'url'      => 'https://ad5.tv/wm/' . $uname,
                ];
            }
        }

        $p4cUrl      = defined('Pay4Coins_URL') ? Pay4Coins_URL : 'https://pay4coins.com';
        $partnerLink = rtrim($p4cUrl, '/') . '/wm/' . $this->partnerId;

        $html = View::render('Webmaster', 'advertise_partner', [
            'partnerId'   => $this->partnerId,
            'partnerLink' => $partnerLink,
            'actorLinks'  => $actorLinks,
        ]);

        Response::html($html);
    }

    // =========================================================================
    // 8. AJAX: Herkunfts-Details (Referer-Liste für Klicks & Impressionen)
    // =========================================================================
    public function originDetails(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        $type   = trim((string)$this->request->get('type', (string)$this->request->post('type', 'clicks')));
        $period = trim((string)$this->request->get('month', (string)$this->request->post('month', date('Y-m'))));
        $origin = trim(strip_tags((string)$this->request->get('origin', (string)$this->request->post('origin', ''))));
        $cid    = trim(strip_tags((string)$this->request->get('cid', (string)$this->request->post('cid', ''))));

        if ($origin === '') {
            Response::error('Keine Herkunfts-Domain angegeben.');
            return;
        }

        if (!preg_match('/^\d{4}(-\d{2})?$/', $period)) {
            $period = date('Y-m');
        }

        // Wenn Kampagne angegeben wurde, Berechtigung prüfen
        $cidFilterSql = '';
        if ($cid !== '') {
            $rsCamp = p4c_query("SELECT `id` FROM `ads_campaigns`
                WHERE `campaign_id` = '" . p4c_escape_string($cid) . "'
                  AND `merchant_id` = '" . $this->merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);
            if (!$rsCamp || p4c_num_rows($rsCamp) === 0) {
                Response::error('Kampagne nicht gefunden.');
                return;
            }
            $cidFilterSql = " AND `campaign_id` = '" . p4c_escape_string($cid) . "'";
        }

        $partnerIdEsc = p4c_escape_string($this->partnerId);
        $originEsc    = p4c_escape_string($origin);
        $isImpressions = ($type === 'impressions');
        $prefix        = $isImpressions ? 'ads_impressions' : 'ads_clicks';
        $tables        = $this->resolveMonthlyTables($period, $prefix);

        $rows = [];
        foreach ($tables as $tbl) {
            $rs = p4c_query("SELECT * FROM `" . $tbl . "`
                WHERE `url` = '" . $originEsc . "'
                  AND `p4c_partner_id` = '" . $partnerIdEsc . "'" . $cidFilterSql . "
                ORDER BY `timestamp` DESC;", __FILE__, __LINE__);

            if ($rs && p4c_num_rows($rs) > 0) {
                while ($obj = p4c_fetch_object($rs)) {
                    $rows[] = [
                        'timestamp'   => (string)($obj->timestamp ?? ''),
                        'referer'     => (string)($obj->referer ?? ''),
                        'impressions' => $isImpressions ? abs((int)($obj->number_of_impressions ?? 1)) : 1,
                    ];
                }
            }
        }

        Response::json([
            'status' => 'ok',
            'type'   => $isImpressions ? 'impressions' : 'clicks',
            'origin' => $origin,
            'period' => $period,
            'cid'    => $cid,
            'rows'   => $rows,
        ]);
    }

    // =========================================================================
    // 9. AJAX: Eigene Banner einer Website laden & hochladen / ersetzen
    // =========================================================================
    public function mySiteBanners(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        $siteId = abs((int)$this->request->get('site_id', (int)$this->request->post('site_id', 0)));
        $siteObj = $this->verifyOwnSite($siteId);
        if (!$siteObj) {
            Response::error('Diese Website gehört nicht zu deinem Account oder ist nicht aktiv.');
            return;
        }

        $adsUrl  = defined('ADS_URL') ? ADS_URL : 'https://www1.erocloud.net';
        $adsPath = defined('ADS_PATH') ? ADS_PATH : '';
        $adsDir  = defined('ADS_DEFAULT_DIR') ? ADS_DEFAULT_DIR : 'media';

        $banners = [];
        $rsAds = p4c_query("SELECT * FROM `ads_media`
            WHERE `site_id` = '" . $siteId . "'
            ORDER BY `upload_datetime` DESC;", __FILE__, __LINE__);

        if ($rsAds && p4c_num_rows($rsAds) > 0) {
            while ($adsObj = p4c_fetch_object($rsAds)) {
                $fileId      = (string)($adsObj->file_id ?? '');
                $filename    = (string)($adsObj->filename ?? '');
                $newFilename = (string)($adsObj->new_filename ?? '');
                $rejected    = (string)($adsObj->rejected ?? '0');
                $type        = strtolower((string)($adsObj->type ?? 'jpg'));
                $width       = abs((int)($adsObj->width ?? 0));
                $height      = abs((int)($adsObj->height ?? 0));
                $uploaded    = (string)($adsObj->upload_datetime ?? '');
                $ts          = $uploaded !== '' ? strtotime($uploaded) : false;

                $statusKey   = 'active';
                $statusLabel = 'Freigegeben & Aktiv';
                if ($newFilename !== '' && $rejected === '0') {
                    $statusKey   = 'pending';
                    $statusLabel = 'In Prüfung';
                } elseif ($newFilename !== '' && $rejected === '3') {
                    $statusKey   = 'rejected';
                    $statusLabel = 'Abgelehnt';
                }

                // Falls ein neues/ersetztes Banner in Prüfung/Abgelehnt vorliegt, Base64-Vorschau bereitstellen (wie Legacy)
                $newPreviewDataUri = '';
                if ($newFilename !== '' && $adsPath !== '') {
                    $candidatePath = rtrim($adsPath, '/\\') . DIRECTORY_SEPARATOR . $adsDir . DIRECTORY_SEPARATOR . $siteId . DIRECTORY_SEPARATOR . $newFilename;
                    if (is_file($candidatePath) && is_readable($candidatePath)) {
                        $rawBytes = @file_get_contents($candidatePath);
                        if ($rawBytes !== false) {
                            $mimeType = ($type === 'jpg' || $type === 'jpeg') ? 'jpeg' : $type;
                            $newPreviewDataUri = 'data:image/' . $mimeType . ';base64,' . base64_encode($rawBytes);
                        }
                    }
                }

                $banners[] = [
                    'file_id'              => $fileId,
                    'width'                => $width,
                    'height'               => $height,
                    'size_label'           => $width . ' x ' . $height . ' Pixel',
                    'type'                 => strtoupper($type),
                    'upload_datetime_fmt'  => $ts ? date('d.m.Y H:i', $ts) : $uploaded,
                    'status_key'           => $statusKey,
                    'status_label'         => $statusLabel,
                    'image_url'            => $adsUrl . '/b/' . $fileId,
                    'has_live_file'        => ($filename !== '' && $filename !== $newFilename) || $newFilename === '',
                    'new_preview_data_uri' => $newPreviewDataUri,
                ];
            }
        }

        Response::json([
            'status'  => 'ok',
            'site_id' => $siteId,
            'domain'  => (string)($siteObj->domain ?? ''),
            'banners' => $banners,
        ]);
    }

    /**
     * POST: Lädt ein oder mehrere neue Werbebanner für eine eigene Website hoch.
     */
    public function uploadMyBanners(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        if (!Csrf::validateRequest($this->request)) {
            Response::error('Ungültiger Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $siteId  = abs((int)$this->request->post('site_id', (int)$this->request->get('site_id', 0)));
        $siteObj = $this->verifyOwnSite($siteId);
        if (!$siteObj) {
            Response::error('Diese Website existiert nicht oder gehört nicht zu deinem Account.');
            return;
        }

        if (!isset($_FILES['banners']) || empty($_FILES['banners']['name'])) {
            Response::error('Bitte wähle mindestens eine Bilddatei (JPG, PNG oder GIF) aus.');
            return;
        }

        $adsPath = defined('ADS_PATH') ? ADS_PATH : (SOURCEDIR . '/ads');
        $adsDir  = defined('ADS_DEFAULT_DIR') ? ADS_DEFAULT_DIR : 'media';
        $targetDir = rtrim($adsPath, '/\\') . DIRECTORY_SEPARATOR . $adsDir . DIRECTORY_SEPARATOR . $siteId;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $names   = (array)$_FILES['banners']['name'];
        $tmpList = (array)$_FILES['banners']['tmp_name'];
        $errors  = (array)$_FILES['banners']['error'];

        $uploadedCount = 0;
        $i = 1;

        foreach ($names as $idx => $origName) {
            $errCode = (int)($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode !== UPLOAD_ERR_OK) {
                Response::error('Fehler beim Upload der Datei „' . (string)$origName . '“ (Code ' . $errCode . ').');
                return;
            }

            $tmpFile = (string)($tmpList[$idx] ?? '');
            if ($tmpFile === '' || !is_file($tmpFile)) {
                Response::error('Keine gültige Datei ausgewählt.');
                return;
            }

            if ((int)filesize($tmpFile) > 5242880) {
                Response::error('Die Datei „' . (string)$origName . '“ überschreitet die maximale Dateigröße von 5 MB.');
                return;
            }

            $ext = strtolower((string)pathinfo((string)$origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                Response::error('Das Dateiformat von „' . (string)$origName . '“ ist nicht erlaubt. Erlaubt sind nur JPG, PNG und GIF.');
                return;
            }

            $imgInfo = @getimagesize($tmpFile);
            if ($imgInfo === false) {
                Response::error('Die Bildabmessungen von „' . (string)$origName . '“ konnten nicht gelesen werden.');
                return;
            }

            $width  = abs((int)$imgInfo[0]);
            $height = abs((int)$imgInfo[1]);
            $sizeKey = $width . 'x' . $height;

            if (!isset(self::STANDARD_BANNER_SIZES[$sizeKey])) {
                Response::error('Das Banner „' . (string)$origName . '“ (' . $width . ' x ' . $height . ' Pixel) entspricht keiner erlaubten Standard-Bannergröße.');
                return;
            }

            $fileName = date('Ymd_His_') . $i . '.' . $ext;
            $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

            if (!@move_uploaded_file($tmpFile, $filePath) || !is_file($filePath)) {
                Response::error('Die Datei „' . (string)$origName . '“ konnte nicht auf dem Server gespeichert werden.');
                return;
            }

            $fileId = strtolower(md5($siteId . $fileName));

            p4c_query("INSERT INTO `ads_media` SET
                `file_id`          = '" . p4c_escape_string($fileId) . "',
                `storage_location` = '" . p4c_escape_string($adsDir) . "',
                `site_id`          = '" . $siteId . "',
                `type`             = '" . p4c_escape_string($ext) . "',
                `width`            = '" . $width . "',
                `height`           = '" . $height . "',
                `filename`         = '" . p4c_escape_string($fileName) . "',
                `new_filename`     = '" . p4c_escape_string($fileName) . "',
                `upload_datetime`  = '" . date('Y-m-d H:i:s') . "';", __FILE__, __LINE__);

            $uploadedCount++;
            $i++;
        }

        Response::json([
            'status'         => 'ok',
            'uploaded_count' => $uploadedCount,
            'message'        => $uploadedCount === 1
                ? 'Dein Banner wurde erfolgreich hochgeladen und befindet sich nun in der Prüfung.'
                : $uploadedCount . ' Banner wurden erfolgreich hochgeladen und befinden sich nun in der Prüfung.',
        ]);
    }

    /**
     * POST: Ersetzt ein bestehendes eigenes Werbebanner (`file_id`) durch eine neue Grafik gleicher Größe & gleichen Typs.
     */
    public function uploadMyBannerReplace(): void
    {
        if (!$this->checkAccess(true)) {
            return;
        }

        if (!Csrf::validateRequest($this->request)) {
            Response::error('Ungültiger Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $siteId = abs((int)$this->request->post('site_id', 0));
        $fileId = trim((string)$this->request->post('file_id', ''));

        $siteObj = $this->verifyOwnSite($siteId);
        if (!$siteObj) {
            Response::error('Diese Website gehört nicht zu deinem Account.');
            return;
        }

        $rsAds = p4c_query("SELECT * FROM `ads_media`
            WHERE `file_id` = '" . p4c_escape_string($fileId) . "'
              AND `site_id` = '" . $siteId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsAds || p4c_num_rows($rsAds) === 0) {
            Response::error('Das zu ersetzende Banner wurde nicht gefunden.');
            return;
        }

        $adsObj = p4c_fetch_object($rsAds);

        // Datei aus $_FILES['banner'] oder $_FILES['banners'] lesen
        $tmpFile  = '';
        $origName = '';
        $errCode  = UPLOAD_ERR_NO_FILE;

        if (isset($_FILES['banner']) && !empty($_FILES['banner']['name'])) {
            $origName = (string)$_FILES['banner']['name'];
            $tmpFile  = (string)$_FILES['banner']['tmp_name'];
            $errCode  = (int)$_FILES['banner']['error'];
        } elseif (isset($_FILES['banners']) && !empty($_FILES['banners']['name'])) {
            $names    = (array)$_FILES['banners']['name'];
            $tmps     = (array)$_FILES['banners']['tmp_name'];
            $errs     = (array)$_FILES['banners']['error'];
            $origName = (string)($names[0] ?? '');
            $tmpFile  = (string)($tmps[0] ?? '');
            $errCode  = (int)($errs[0] ?? UPLOAD_ERR_NO_FILE);
        }

        if ($errCode !== UPLOAD_ERR_OK || $tmpFile === '' || !is_file($tmpFile)) {
            Response::error('Bitte wähle eine gültige Bilddatei zum Ersetzen aus.');
            return;
        }

        if ((int)filesize($tmpFile) > 5242880) {
            Response::error('Die Datei überschreitet die maximale Dateigröße von 5 MB.');
            return;
        }

        $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
        $expectedType = strtolower((string)($adsObj->type ?? ''));
        $normExt      = ($ext === 'jpeg') ? 'jpg' : $ext;
        $normExpected = ($expectedType === 'jpeg') ? 'jpg' : $expectedType;

        if ($normExt !== $normExpected) {
            Response::error('Die neue Datei muss ebenfalls vom Typ ' . strtoupper($expectedType) . ' sein.');
            return;
        }

        $imgInfo = @getimagesize($tmpFile);
        if ($imgInfo === false) {
            Response::error('Die Bildabmessungen konnten nicht gelesen werden.');
            return;
        }

        $width     = abs((int)$imgInfo[0]);
        $height    = abs((int)$imgInfo[1]);
        $expWidth  = abs((int)($adsObj->width ?? 0));
        $expHeight = abs((int)($adsObj->height ?? 0));

        if ($width !== $expWidth || $height !== $expHeight) {
            Response::error('Das neue Banner (' . $width . ' x ' . $height . ' Pixel) muss exakt dieselbe Größe wie das bisherige Banner (' . $expWidth . ' x ' . $expHeight . ' Pixel) haben.');
            return;
        }

        $adsPath   = defined('ADS_PATH') ? ADS_PATH : (SOURCEDIR . '/ads');
        $adsDir    = defined('ADS_DEFAULT_DIR') ? ADS_DEFAULT_DIR : 'media';
        $targetDir = rtrim($adsPath, '/\\') . DIRECTORY_SEPARATOR . $adsDir . DIRECTORY_SEPARATOR . $siteId;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $fileName = date('Ymd_His_1') . '.' . $ext;
        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        if (!@move_uploaded_file($tmpFile, $filePath) || !is_file($filePath)) {
            Response::error('Die neue Banner-Datei konnte nicht gespeichert werden.');
            return;
        }

        // Falls bereits eine noch nicht freigeschaltete Vorgänger-Datei existiert, diese löschen
        $oldNewFn = (string)($adsObj->new_filename ?? '');
        $liveFn   = (string)($adsObj->filename ?? '');
        if ($oldNewFn !== '' && $oldNewFn !== $liveFn) {
            $oldNewPath = $targetDir . DIRECTORY_SEPARATOR . $oldNewFn;
            if (is_file($oldNewPath)) {
                @unlink($oldNewPath);
            }
        }

        p4c_query("UPDATE `ads_media` SET
            `new_filename` = '" . p4c_escape_string($fileName) . "',
            `rejected`     = '0'
        WHERE `file_id` = '" . p4c_escape_string($fileId) . "'
        LIMIT 1;", __FILE__, __LINE__);

        Response::json([
            'status'  => 'ok',
            'message' => 'Dein neues Banner wurde hochgeladen und wird nach erfolgreicher Prüfung automatisch freigeschaltet.',
        ]);
    }

    /**
     * Prüft, ob eine `site_id` dem aktuellen Partner gehört und für EroAds aktiv ist.
     */
    private function verifyOwnSite(int $siteId): ?object
    {
        if ($siteId <= 0) {
            return null;
        }

        $rs = p4c_query("SELECT * FROM `sites`
            WHERE `id` = '" . $siteId . "'
              AND `status` = '1'
              AND `is_eroads_active` = '1'
              AND `partner_id` = '" . p4c_escape_string($this->partnerId) . "'
            LIMIT 1;", __FILE__, __LINE__);

        if ($rs && p4c_num_rows($rs) > 0) {
            return p4c_fetch_object($rs);
        }

        return null;
    }
}
