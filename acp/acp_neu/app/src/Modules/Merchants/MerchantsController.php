<?php
declare(strict_types=1);

namespace App\Modules\Merchants;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) – Modul: P4C-Merchants / Händler (#/merchants & #/merchant?id=...)
 *
 * Ersetzt:
 *  - acp/includes/merchants.php + acp/includes/ajax/merchants.php
 *  - acp/includes/merchant.php  + acp/includes/ajax/merchant_actors.php
 *
 * Architektur-Highlights:
 *  - 0 N+1 Queries: Statt 3 Einzel-Queries pro Händler in einer Schleife (~1.500 Queries)
 *    werden alle Profil-, Film-, Fotoalben- und 18+-Nachweis-Zähler in 4 gebündelten
 *    GROUP-BY-Abfragen vorab geladen.
 *  - Detailansicht (#/merchant?id=...) unterstützt sowohl die numerische EroCloud-MID (merchants.id)
 *    als auch die 10-stellige Pay4Coins-Partner-ID (merchants.partner_id) und lädt alle
 *    AES-entschlüsselten Stammdaten in einer einzigen SQL-Abfrage (statt 11 Einzel-Queries).
 *  - Enthält zusätzlich die Darsteller-Übersicht, Content-/18+-Zusammenfassung und
 *    die komplette Händler-Aktivitätshistorie (merchant_history).
 */
final class MerchantsController
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
            return mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
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

    /**
     * Berechnet das Alter anhand eines Geburtsdatums (YYYY-MM-DD oder DD.MM.YYYY)
     */
    private function calculateAge(string $birthday): ?int
    {
        $raw = trim($birthday);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }
        try {
            $dt = new \DateTime($raw);
            $now = new \DateTime();
            $diff = $now->diff($dt);
            if ($diff->y >= 18 && $diff->y <= 110) {
                return (int)$diff->y;
            }
        } catch (\Throwable $e) {
            // Ignorieren
        }
        return null;
    }

    // =========================================================================
    // 1. P4C-Merchants Gesamtübersicht (GET ?route=merchants)
    // =========================================================================
    public function list(): string
    {
        $rulesFilter    = trim((string)$this->request->get('rules', 'all'));
        $activityFilter = trim((string)$this->request->get('activity', 'all'));
        $searchQuery    = trim((string)$this->request->get('q', ''));
        $sortBy         = trim((string)$this->request->get('sort', 'id_desc'));
        $page           = max(1, abs((int)$this->request->get('page', 1)));
        $perPage        = abs((int)$this->request->get('limit', 50));
        if (!in_array($perPage, [50, 100, 250, 500], true)) {
            $perPage = 50;
        }

        $aesKeyEsc = defined('AES_KEY') ? p4c_escape_string((string)AES_KEY) : '';

        // 1. Globale KPI-Zähler für P4C-Merchants
        $kpi = [
            'total_merchants'   => 0,
            'rules_accepted'    => 0,
            'rules_pending'     => 0,
            'active_30d'        => 0,
            'multi_profile'     => 0,
            'total_actors'      => 0,
            'total_movies_on'   => 0,
            'total_albums_on'   => 0,
            'total_in_checking' => 0,
        ];

        $date30dAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_merchants`,
                SUM(CASE WHEN `accept_content_rules` != '0000-00-00 00:00:00' AND `accept_content_rules` IS NOT NULL THEN 1 ELSE 0 END) AS `rules_accepted`,
                SUM(CASE WHEN `accept_content_rules` = '0000-00-00 00:00:00' OR `accept_content_rules` IS NULL THEN 1 ELSE 0 END) AS `rules_pending`,
                SUM(CASE WHEN `last_ip_datetime` >= '" . p4c_escape_string($date30dAgo) . "' THEN 1 ELSE 0 END) AS `active_30d`,
                SUM(CASE WHEN `minimum_number_actor_profiles` > 1 THEN 1 ELSE 0 END) AS `multi_profile`
             FROM `merchants`;",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && ($rowKpi = p4c_fetch_object($rsKpi))) {
            $kpi['total_merchants'] = (int)($rowKpi->total_merchants ?? 0);
            $kpi['rules_accepted']  = (int)($rowKpi->rules_accepted ?? 0);
            $kpi['rules_pending']   = (int)($rowKpi->rules_pending ?? 0);
            $kpi['active_30d']      = (int)($rowKpi->active_30d ?? 0);
            $kpi['multi_profile']   = (int)($rowKpi->multi_profile ?? 0);
        }

        // 2. Vorab-Aggregation pro merchant_id (0 N+1 Queries!)
        $actorsByMerchant = [];
        $rsActAgg = p4c_query(
            "SELECT
                `merchant_id`,
                COUNT(*) AS `total`,
                SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) AS `active_cnt`,
                SUM(CASE WHEN `status` = 'blocked' THEN 1 ELSE 0 END) AS `blocked_cnt`,
                SUM(CASE WHEN `status` = 'deleted' THEN 1 ELSE 0 END) AS `deleted_cnt`
             FROM `actors`
             GROUP BY `merchant_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsActAgg) {
            while ($r = p4c_fetch_object($rsActAgg)) {
                $mid = abs((int)$r->merchant_id);
                $actCnt = (int)($r->active_cnt ?? 0);
                $kpi['total_actors'] += (int)($r->total ?? 0);
                $actorsByMerchant[$mid] = [
                    'total'   => (int)($r->total ?? 0),
                    'active'  => $actCnt,
                    'blocked' => (int)($r->blocked_cnt ?? 0),
                    'deleted' => (int)($r->deleted_cnt ?? 0),
                ];
            }
        }

        $moviesByMerchant = [];
        $rsMovOnAgg = p4c_query(
            "SELECT `merchant_id`, COUNT(*) AS `online_cnt`
             FROM `movies_online`
             WHERE `status` = 'active'
             GROUP BY `merchant_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsMovOnAgg) {
            while ($r = p4c_fetch_object($rsMovOnAgg)) {
                $mid = abs((int)$r->merchant_id);
                $cnt = (int)($r->online_cnt ?? 0);
                $kpi['total_movies_on'] += $cnt;
                $moviesByMerchant[$mid] = [
                    'online'   => $cnt,
                    'checking' => 0,
                    'drafts'   => 0,
                ];
            }
        }

        $rsMovChkAgg = p4c_query(
            "SELECT
                `merchant_id`,
                SUM(CASE WHEN `released` = '1' AND (`movie_checked` = '0000-00-00 00:00:00' OR `movie_checked` = '000-00-00 00:00:00' OR `movie_checked` IS NULL) AND `status` = 'active' AND `convert_status` = '2' THEN 1 ELSE 0 END) AS `checking_cnt`,
                SUM(CASE WHEN `released` = '0' AND `status` != 'deleted' THEN 1 ELSE 0 END) AS `drafts_cnt`
             FROM `movies`
             GROUP BY `merchant_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsMovChkAgg) {
            while ($r = p4c_fetch_object($rsMovChkAgg)) {
                $mid = abs((int)$r->merchant_id);
                $chk = (int)($r->checking_cnt ?? 0);
                $drf = (int)($r->drafts_cnt ?? 0);
                $kpi['total_in_checking'] += $chk;
                if (!isset($moviesByMerchant[$mid])) {
                    $moviesByMerchant[$mid] = ['online' => 0, 'checking' => 0, 'drafts' => 0];
                }
                $moviesByMerchant[$mid]['checking'] = $chk;
                $moviesByMerchant[$mid]['drafts']   = $drf;
            }
        }

        $albumsByMerchant = [];
        $rsAlbOnAgg = p4c_query(
            "SELECT `merchant_id`, COUNT(*) AS `online_cnt`
             FROM `photo_albums_online`
             WHERE `status` = 'active'
             GROUP BY `merchant_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbOnAgg) {
            while ($r = p4c_fetch_object($rsAlbOnAgg)) {
                $mid = abs((int)$r->merchant_id);
                $cnt = (int)($r->online_cnt ?? 0);
                $kpi['total_albums_on'] += $cnt;
                $albumsByMerchant[$mid] = ['online' => $cnt, 'checking' => 0];
            }
        }

        $rsAlbChkAgg = p4c_query(
            "SELECT
                `merchant_id`,
                SUM(CASE WHEN `released` = '1' AND (`album_checked` = '0000-00-00 00:00:00' OR `album_checked` IS NULL) AND `status` = 'active' THEN 1 ELSE 0 END) AS `checking_cnt`
             FROM `photo_albums`
             GROUP BY `merchant_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbChkAgg) {
            while ($r = p4c_fetch_object($rsAlbChkAgg)) {
                $mid = abs((int)$r->merchant_id);
                $chk = (int)($r->checking_cnt ?? 0);
                $kpi['total_in_checking'] += $chk;
                if (!isset($albumsByMerchant[$mid])) {
                    $albumsByMerchant[$mid] = ['online' => 0, 'checking' => 0];
                }
                $albumsByMerchant[$mid]['checking'] = $chk;
            }
        }

        // 3. WHERE-Klausel aufbauen
        $whereParts = ['1=1'];

        if ($rulesFilter === 'accepted') {
            $whereParts[] = "(m.`accept_content_rules` != '0000-00-00 00:00:00' AND m.`accept_content_rules` IS NOT NULL)";
        } elseif ($rulesFilter === 'pending') {
            $whereParts[] = "(m.`accept_content_rules` = '0000-00-00 00:00:00' OR m.`accept_content_rules` IS NULL)";
        }

        if ($activityFilter === 'active_30d') {
            $whereParts[] = "m.`last_ip_datetime` >= '" . p4c_escape_string($date30dAgo) . "'";
        } elseif ($activityFilter === 'with_actors') {
            $whereParts[] = "EXISTS (SELECT 1 FROM `actors` a WHERE a.`merchant_id` = m.`id`)";
        } elseif ($activityFilter === 'without_actors') {
            $whereParts[] = "NOT EXISTS (SELECT 1 FROM `actors` a WHERE a.`merchant_id` = m.`id`)";
        } elseif ($activityFilter === 'with_checking') {
            $whereParts[] = "(
                EXISTS (SELECT 1 FROM `movies` mv WHERE mv.`merchant_id` = m.`id` AND mv.`released` = '1' AND (mv.`movie_checked` = '0000-00-00 00:00:00' OR mv.`movie_checked` = '000-00-00 00:00:00') AND mv.`status` = 'active' AND mv.`convert_status` = '2')
                OR EXISTS (SELECT 1 FROM `photo_albums` pa WHERE pa.`merchant_id` = m.`id` AND pa.`released` = '1' AND pa.`album_checked` = '0000-00-00 00:00:00' AND pa.`status` = 'active')
            )";
        } elseif ($activityFilter === 'multi_profile') {
            $whereParts[] = "m.`minimum_number_actor_profiles` > 1";
        }

        if ($searchQuery !== '') {
            $qEsc = p4c_escape_string($searchQuery);
            $qParts = [
                "m.`partner_id` LIKE '%" . $qEsc . "%'",
                "CAST(AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') AS CHAR) LIKE '%" . $qEsc . "%'",
                "CAST(AES_DECRYPT(m.`email`, '" . $aesKeyEsc . "') AS CHAR) LIKE '%" . $qEsc . "%'",
                "CAST(AES_DECRYPT(m.`firstname`, '" . $aesKeyEsc . "') AS CHAR) LIKE '%" . $qEsc . "%'",
                "CAST(AES_DECRYPT(m.`surname`, '" . $aesKeyEsc . "') AS CHAR) LIKE '%" . $qEsc . "%'",
                "CAST(AES_DECRYPT(m.`last_ip`, '" . $aesKeyEsc . "') AS CHAR) LIKE '%" . $qEsc . "%'",
                "EXISTS (SELECT 1 FROM `actors` a WHERE a.`merchant_id` = m.`id` AND a.`username` LIKE '%" . $qEsc . "%')",
            ];
            if (ctype_digit($searchQuery)) {
                $qParts[] = "m.`id` = '" . abs((int)$searchQuery) . "'";
            }
            $whereParts[] = '(' . implode(' OR ', $qParts) . ')';
        }

        $whereSql = implode(' AND ', $whereParts);

        // Sortierung
        $orderSql = match ($sortBy) {
            'id_asc'          => 'm.`id` ASC',
            'last_login_desc' => 'm.`last_ip_datetime` DESC, m.`id` DESC',
            'partner_id_asc'  => 'm.`partner_id` ASC',
            'limit_desc'      => 'm.`minimum_number_actor_profiles` DESC, m.`id` DESC',
            default           => 'm.`id` DESC',
        };

        // Gesamtanzahl für Paginierung
        $totalFiltered = 0;
        $rsCount = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `merchants` m WHERE {$whereSql};",
            __FILE__,
            __LINE__
        );
        if ($rsCount && ($rCnt = p4c_fetch_object($rsCount))) {
            $totalFiltered = (int)($rCnt->cnt ?? 0);
        }

        $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // Hauptabfrage
        $merchants = [];
        $rsList = p4c_query(
            "SELECT
                m.`id`,
                m.`partner_id`,
                m.`salutation`,
                m.`minimum_number_actor_profiles`,
                m.`last_ip_datetime`,
                m.`accept_content_rules`,
                AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') AS `dec_username`,
                AES_DECRYPT(m.`email`, '" . $aesKeyEsc . "') AS `dec_email`,
                AES_DECRYPT(m.`firstname`, '" . $aesKeyEsc . "') AS `dec_firstname`,
                AES_DECRYPT(m.`surname`, '" . $aesKeyEsc . "') AS `dec_surname`,
                AES_DECRYPT(m.`last_ip`, '" . $aesKeyEsc . "') AS `dec_last_ip`
             FROM `merchants` m
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT " . abs((int)$offset) . ", " . abs((int)$perPage) . ";",
            __FILE__,
            __LINE__
        );

        if ($rsList) {
            while ($row = p4c_fetch_object($rsList)) {
                $mid        = abs((int)$row->id);
                $partnerId  = $this->toUtf8((string)($row->partner_id ?? ''));
                $username   = $this->toUtf8((string)($row->dec_username ?? ''));
                $email      = $this->toUtf8((string)($row->dec_email ?? ''));
                $firstname  = $this->toUtf8((string)($row->dec_firstname ?? ''));
                $surname    = $this->toUtf8((string)($row->dec_surname ?? ''));
                $lastIp     = $this->toUtf8((string)($row->dec_last_ip ?? ''));
                $fullName   = trim($firstname . ' ' . $surname);

                $actStats   = $actorsByMerchant[$mid] ?? ['total' => 0, 'active' => 0, 'blocked' => 0, 'deleted' => 0];
                $movStats   = $moviesByMerchant[$mid] ?? ['online' => 0, 'checking' => 0, 'drafts' => 0];
                $albStats   = $albumsByMerchant[$mid] ?? ['online' => 0, 'checking' => 0];

                $rulesRaw   = (string)($row->accept_content_rules ?? '0000-00-00 00:00:00');
                $hasRules   = ($rulesRaw !== '' && !str_starts_with($rulesRaw, '0000-00-00'));

                $merchants[] = [
                    'id'                 => $mid,
                    'partner_id'         => $partnerId,
                    'username'           => $username !== '' ? $username : ('Merchant #' . $mid),
                    'email'              => $email,
                    'full_name'          => $fullName,
                    'last_ip'            => $lastIp,
                    'last_ip_dt_de'      => $this->formatDateTimeDe((string)($row->last_ip_datetime ?? '')),
                    'rules_accepted'     => $hasRules,
                    'rules_dt_de'        => $hasRules ? $this->formatDateTimeDe($rulesRaw) : 'Nicht akzeptiert',
                    'min_profiles_limit' => max(1, (int)($row->minimum_number_actor_profiles ?? 1)),
                    'actors_total'       => $actStats['total'],
                    'actors_active'      => $actStats['active'],
                    'actors_blocked'     => $actStats['blocked'],
                    'movies_online'      => $movStats['online'],
                    'movies_checking'    => $movStats['checking'],
                    'movies_drafts'      => $movStats['drafts'],
                    'albums_online'      => $albStats['online'],
                    'albums_checking'    => $albStats['checking'],
                ];
            }
        }

        return View::render('Merchants', 'list', [
            'kpi'           => $kpi,
            'merchants'     => $merchants,
            'totalFiltered' => $totalFiltered,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => $totalPages,
            'filters'       => [
                'rules'    => $rulesFilter,
                'activity' => $activityFilter,
                'q'        => $searchQuery,
                'sort'     => $sortBy,
                'limit'    => $perPage,
            ],
        ]);
    }

    // =========================================================================
    // 2. P4C-Merchant Detail- & Bearbeitungs-Cockpit (GET ?route=merchant&id=...)
    // =========================================================================
    public function detail(): string
    {
        $rawId = trim((string)$this->request->get('id', ''));
        if ($rawId === '') {
            return '<div class="alert alert-warning m-4"><i class="bi bi-exclamation-triangle me-2"></i>Keine gültige Merchant-ID (MID) oder Partner-ID (PID) übergeben. <a href="#/merchants" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        $aesKeyEsc = defined('AES_KEY') ? p4c_escape_string((string)AES_KEY) : '';

        // Flexibles Lookup: Numerische MID (merchants.id) ODER 10-stellige Pay4Coins-PID (merchants.partner_id)
        if (ctype_digit($rawId) && strlen($rawId) < 10) {
            $whereCond = "m.`id` = '" . abs((int)$rawId) . "'";
        } else {
            $escId = p4c_escape_string($rawId);
            $whereCond = "(m.`partner_id` = '" . $escId . "' OR m.`id` = '" . abs((int)$rawId) . "')";
        }

        // 1. Komplette Händler-Stammdaten in 1 Query entschlüsseln (statt 11 Einzel-Queries!)
        $rsMerch = p4c_query(
            "SELECT
                m.`id`,
                m.`partner_id`,
                m.`salutation`,
                m.`minimum_number_actor_profiles`,
                m.`last_ip_datetime`,
                m.`accept_content_rules`,
                AES_DECRYPT(m.`username`, '" . $aesKeyEsc . "') AS `dec_username`,
                AES_DECRYPT(m.`email`, '" . $aesKeyEsc . "') AS `dec_email`,
                AES_DECRYPT(m.`firstname`, '" . $aesKeyEsc . "') AS `dec_firstname`,
                AES_DECRYPT(m.`surname`, '" . $aesKeyEsc . "') AS `dec_surname`,
                AES_DECRYPT(m.`birthday`, '" . $aesKeyEsc . "') AS `dec_birthday`,
                AES_DECRYPT(m.`last_ip`, '" . $aesKeyEsc . "') AS `dec_last_ip`,
                AES_DECRYPT(m.`api_key`, '" . $aesKeyEsc . "') AS `dec_api_key`
             FROM `merchants` m
             WHERE {$whereCond}
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsMerch || p4c_num_rows($rsMerch) === 0) {
            return '<div class="alert alert-danger m-4"><i class="bi bi-x-octagon me-2"></i>Der P4C-Merchant <strong>' . View::e($rawId) . '</strong> wurde nicht gefunden. <a href="#/merchants" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        $mRow       = p4c_fetch_object($rsMerch);
        $merchantId = abs((int)$mRow->id);

        // 2. Vorheriger / Nächster Merchant für schnelle Navigation
        $prevId = 0;
        $nextId = 0;
        $rsPrev = p4c_query("SELECT `id` FROM `merchants` WHERE `id` < '" . $merchantId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && ($rP = p4c_fetch_object($rsPrev))) {
            $prevId = abs((int)$rP->id);
        }
        $rsNext = p4c_query("SELECT `id` FROM `merchants` WHERE `id` > '" . $merchantId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsNext && ($rN = p4c_fetch_object($rsNext))) {
            $nextId = abs((int)$rN->id);
        }

        // 3. Zugeordnete Darsteller-Profile inkl. Film- & Fotoalben-Zählern laden
        $actorMovieCounts = [];
        $rsAmc = p4c_query(
            "SELECT `actor_id`, COUNT(*) AS `cnt` FROM `movies` WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' GROUP BY `actor_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsAmc) {
            while ($r = p4c_fetch_object($rsAmc)) {
                $actorMovieCounts[abs((int)$r->actor_id)] = (int)$r->cnt;
            }
        }

        $actorAlbumCounts = [];
        $rsAac = p4c_query(
            "SELECT `actor_id`, COUNT(*) AS `cnt` FROM `photo_albums` WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted' GROUP BY `actor_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsAac) {
            while ($r = p4c_fetch_object($rsAac)) {
                $actorAlbumCounts[abs((int)$r->actor_id)] = (int)$r->cnt;
            }
        }

        $actors = [];
        $rsActors = p4c_query(
            "SELECT
                `id`, `username`, `status`, `is_displayed_as`, `main_category`,
                `profile_image_fsk16`, `profile_image_fsk18`, `create_date`, `lastonline`
             FROM `actors`
             WHERE `merchant_id` = '" . $merchantId . "'
             ORDER BY `id` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsActors) {
            while ($a = p4c_fetch_object($rsActors)) {
                $aid = abs((int)$a->id);
                $f16 = trim((string)($a->profile_image_fsk16 ?? ''));
                $f18 = trim((string)($a->profile_image_fsk18 ?? ''));
                $lastOnTs = abs((int)($a->lastonline ?? 0));
                $actors[] = [
                    'id'              => $aid,
                    'username'        => $this->toUtf8((string)($a->username ?? '')),
                    'status'          => (string)($a->status ?? 'active'),
                    'is_displayed_as' => (string)($a->is_displayed_as ?? ''),
                    'main_category'   => (string)($a->main_category ?? ''),
                    'has_fsk16'       => $f16 !== '',
                    'has_fsk18'       => $f18 !== '',
                    'avatar_fsk16'    => 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=16&w=80',
                    'avatar_fsk18'    => 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=18&w=80',
                    'avatar_fsk16_lg' => 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=16&w=600',
                    'avatar_fsk18_lg' => 'media.php?type=actor_avatar&actor_id=' . $aid . '&fsk=18&w=600',
                    'movies_count'    => $actorMovieCounts[$aid] ?? 0,
                    'albums_count'    => $actorAlbumCounts[$aid] ?? 0,
                    'create_date_de'  => $this->formatDateTimeDe((string)($a->create_date ?? '')),
                    'lastonline_de'   => $lastOnTs > 0 ? date('d.m.Y H:i', $lastOnTs) : '-',
                ];
            }
        }

        // 4. Neueste Filme (max. 50) & Gesamt-Filmstatistik dieses Händlers
        $movieSummary = ['total' => 0, 'online' => 0, 'checking' => 0, 'drafts' => 0, 'blocked' => 0];
        $rsMovSum = p4c_query(
            "SELECT
                COUNT(*) AS `total`,
                SUM(CASE WHEN `released` = '1' AND (`movie_checked` = '0000-00-00 00:00:00' OR `movie_checked` = '000-00-00 00:00:00') AND `status` = 'active' AND `convert_status` = '2' THEN 1 ELSE 0 END) AS `checking_cnt`,
                SUM(CASE WHEN `released` = '0' AND `status` != 'deleted' THEN 1 ELSE 0 END) AS `drafts_cnt`,
                SUM(CASE WHEN `released` = '2' OR `status` IN ('blocked','deleted') THEN 1 ELSE 0 END) AS `blocked_cnt`
             FROM `movies`
             WHERE `merchant_id` = '" . $merchantId . "';",
            __FILE__,
            __LINE__
        );
        if ($rsMovSum && ($rMs = p4c_fetch_object($rsMovSum))) {
            $movieSummary['total']    = (int)($rMs->total ?? 0);
            $movieSummary['checking'] = (int)($rMs->checking_cnt ?? 0);
            $movieSummary['drafts']   = (int)($rMs->drafts_cnt ?? 0);
            $movieSummary['blocked']  = (int)($rMs->blocked_cnt ?? 0);
        }
        $rsMovOnCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_online` WHERE `merchant_id` = '" . $merchantId . "' AND `status` = 'active';", __FILE__, __LINE__);
        if ($rsMovOnCnt && ($rMo = p4c_fetch_object($rsMovOnCnt))) {
            $movieSummary['online'] = (int)($rMo->cnt ?? 0);
        }

        $recentMovies = [];
        $rsRecMov = p4c_query(
            "SELECT
                m.`id`, m.`actor_id`, m.`title`, m.`status`, m.`released`, m.`movie_checked`,
                m.`price`, m.`playtime_string`, m.`create_date`,
                a.`username` AS `actor_name`,
                mo.`id` AS `online_id`
             FROM `movies` m
             LEFT JOIN `actors` a ON a.`id` = m.`actor_id`
             LEFT JOIN `movies_online` mo ON mo.`file_id` = m.`file_id`
             WHERE m.`merchant_id` = '" . $merchantId . "'
             ORDER BY m.`id` DESC
             LIMIT 30;",
            __FILE__,
            __LINE__
        );
        if ($rsRecMov) {
            while ($mv = p4c_fetch_object($rsRecMov)) {
                $mid        = abs((int)$mv->id);
                $onlineId   = abs((int)($mv->online_id ?? 0));
                $isChecking = ((string)$mv->released === '1' && ((string)$mv->movie_checked === '0000-00-00 00:00:00' || (string)$mv->movie_checked === '000-00-00 00:00:00') && (string)$mv->status === 'active');
                $recentMovies[] = [
                    'id'          => $mid,
                    'online_id'   => $onlineId,
                    'actor_id'    => abs((int)$mv->actor_id),
                    'actor_name'  => $this->toUtf8((string)($mv->actor_name ?? '')),
                    'title'       => $this->toUtf8((string)($mv->title ?? '')),
                    'status'      => (string)($mv->status ?? ''),
                    'released'    => (string)($mv->released ?? '0'),
                    'is_online'   => $onlineId > 0,
                    'is_checking' => $isChecking,
                    'price_fmt'   => number_format((float)($mv->price ?? 0), 2, ',', '.') . ' €',
                    'playtime'    => (string)($mv->playtime_string ?? '-'),
                    'date_de'     => $this->formatDateTimeDe((string)($mv->create_date ?? '')),
                    'target_url'  => $onlineId > 0
                        ? '#/movie-edit?id=' . $onlineId
                        : ($isChecking ? '#/movie-checking?id=' . $mid : '#/movies-drafts?q=' . $mid),
                ];
            }
        }

        // 5. 18+ Drehpartner & Account-Inhaber-Nachweise (co_actors) dieses Händlers
        $coActors = [];
        $rsCo = p4c_query(
            "SELECT
                `id`, `is_owner`, `creator_name`, `checked_status`, `checked_by`, `checked_on`, `created_at`,
                AES_DECRYPT(`first_name`, '" . $aesKeyEsc . "') AS `dec_first_name`,
                AES_DECRYPT(`last_name`, '" . $aesKeyEsc . "') AS `dec_last_name`
             FROM `co_actors`
             WHERE `merchant_id` = '" . $merchantId . "'
             ORDER BY `is_owner` DESC, `id` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsCo) {
            while ($ca = p4c_fetch_object($rsCo)) {
                $fn = $this->toUtf8((string)($ca->dec_first_name ?? ''));
                $ln = $this->toUtf8((string)($ca->dec_last_name ?? ''));
                $coActors[] = [
                    'id'             => abs((int)$ca->id),
                    'is_owner'       => (int)($ca->is_owner ?? 0) === 1,
                    'creator_name'   => $this->toUtf8((string)($ca->creator_name ?? '')),
                    'real_name'      => trim($fn . ' ' . $ln),
                    'checked_status' => (string)($ca->checked_status ?? 'pending'),
                    'checked_by'     => $this->toUtf8((string)($ca->checked_by ?? '')),
                    'checked_on_de'  => $this->formatDateTimeDe((string)($ca->checked_on ?? '')),
                    'created_at_de'  => $this->formatDateTimeDe((string)($ca->created_at ?? '')),
                ];
            }
        }

        // 6. Login- & Aktivitätshistorie (merchant_history)
        $history = [];
        $rsHist = p4c_query(
            "SELECT `id`, `description`, `datetime`, `ip`
             FROM `merchant_history`
             WHERE `merchant_id` = '" . $merchantId . "'
             ORDER BY `id` DESC
             LIMIT 200;",
            __FILE__,
            __LINE__
        );
        if ($rsHist) {
            while ($h = p4c_fetch_object($rsHist)) {
                $history[] = [
                    'id'          => abs((int)$h->id),
                    'description' => html_entity_decode($this->toUtf8((string)($h->description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'datetime_de' => $this->formatDateTimeDe((string)($h->datetime ?? '')),
                    'ip'          => $this->toUtf8((string)($h->ip ?? '')),
                ];
            }
        }

        $birthdayRaw = $this->toUtf8((string)($mRow->dec_birthday ?? ''));
        $rulesRaw    = (string)($mRow->accept_content_rules ?? '0000-00-00 00:00:00');
        $hasRules    = ($rulesRaw !== '' && !str_starts_with($rulesRaw, '0000-00-00'));
        $partnerId   = $this->toUtf8((string)($mRow->partner_id ?? ''));
        $p4cAcpUrl   = defined('Pay4Coins_ACP_URL')
            ? rtrim((string)Pay4Coins_ACP_URL, '/') . '/Haendler/' . rawurlencode($partnerId)
            : '';

        $merchant = [
            'id'                 => $merchantId,
            'partner_id'         => $partnerId,
            'p4c_acp_url'        => $p4cAcpUrl,
            'username'           => $this->toUtf8((string)($mRow->dec_username ?? '')),
            'email'              => $this->toUtf8((string)($mRow->dec_email ?? '')),
            'salutation'         => $this->toUtf8((string)($mRow->salutation ?? '')),
            'firstname'          => $this->toUtf8((string)($mRow->dec_firstname ?? '')),
            'surname'            => $this->toUtf8((string)($mRow->dec_surname ?? '')),
            'birthday'           => $birthdayRaw,
            'birthday_de'        => $this->formatDateTimeDe($birthdayRaw, false),
            'age'                => $this->calculateAge($birthdayRaw),
            'last_ip'            => $this->toUtf8((string)($mRow->dec_last_ip ?? '')),
            'last_ip_dt_de'      => $this->formatDateTimeDe((string)($mRow->last_ip_datetime ?? '')),
            'api_key'            => $this->toUtf8((string)($mRow->dec_api_key ?? '')),
            'rules_accepted'     => $hasRules,
            'rules_dt_de'        => $hasRules ? $this->formatDateTimeDe($rulesRaw) : 'Noch nicht akzeptiert',
            'min_profiles_limit' => max(1, (int)($mRow->minimum_number_actor_profiles ?? 1)),
            'actors_count'       => count($actors),
        ];

        return View::render('Merchants', 'detail', [
            'merchant'     => $merchant,
            'prevId'       => $prevId,
            'nextId'       => $nextId,
            'actors'       => $actors,
            'movieSummary' => $movieSummary,
            'recentMovies' => $recentMovies,
            'coActors'     => $coActors,
            'history'      => $history,
        ]);
    }

    // =========================================================================
    // 3. Einstellungen speichern (POST ?route=merchants/save-settings)
    // =========================================================================
    public function saveSettings(): void
    {
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = abs((int)$this->request->post('merchant_id', 0));
        if ($merchantId <= 0) {
            Response::error('Ungültige Händler-ID.', 400);
            return;
        }

        $rsCheck = p4c_query(
            "SELECT `id`, `partner_id` FROM `merchants` WHERE `id` = '" . $merchantId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsCheck || p4c_num_rows($rsCheck) === 0) {
            Response::error('Dieser P4C-Merchant existiert nicht.', 404);
            return;
        }
        $mRow      = p4c_fetch_object($rsCheck);
        $partnerId = (string)($mRow->partner_id ?? '');

        // Anzahl bereits angelegter Darsteller-Profile ermitteln (Darf nicht unterschritten werden, analog Legacy)
        $countActors = 0;
        $rsCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `actors` WHERE `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        if ($rsCnt && ($rC = p4c_fetch_object($rsCnt))) {
            $countActors = (int)($rC->cnt ?? 0);
        }

        $minRequired = max(1, $countActors);
        $postedLimit = abs((int)$this->request->post('minimum_number_actor_profiles', $minRequired));
        $finalLimit  = max($minRequired, min(500, $postedLimit));

        p4c_query(
            "UPDATE `merchants` SET
                `minimum_number_actor_profiles` = '" . abs((int)$finalLimit) . "'
             WHERE `id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('log_action')) {
            log_action('Händler (PID:' . $partnerId . ') bearbeitet.');
        }

        Response::json([
            'status'                        => 'ok',
            'message'                       => 'Händler-Einstellungen (Max. ' . $finalLimit . ' Darsteller-Profile) erfolgreich gespeichert.',
            'minimum_number_actor_profiles' => $finalLimit,
        ]);
    }
}
