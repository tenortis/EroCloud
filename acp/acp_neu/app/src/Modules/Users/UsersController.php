<?php
declare(strict_types=1);

namespace App\Modules\Users;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) – Modul: Cloud-User / Endkunden (#/users & #/user?id=...)
 *
 * Ersetzt:
 *  - acp/includes/users.php + acp/includes/ajax/users.php
 *  - acp/includes/user.php
 *
 * Architektur-Highlights:
 *  - Vollständige Entfernung aller veralteten Chat-/Nachrichten-Spalten und -Tabs gemäß Vorgabe.
 *  - Serverseitige Filterung, Sortierung und Paginierung statt ungefiltertem Komplett-Dump aller Mitglieder.
 *  - Einzelansicht (#/user?id=...) lädt Stammdaten in 1 Query (statt 9 Einzel-Queries der Legacy-Klasse Member)
 *    und verknüpft zusätzlich alle Film-Käufe (movies_access) und Fotoalben-Käufe (photo_albums_access)
 *    des Endkunden über seine Shop-User-ID.
 */
final class UsersController
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
     * Berechnet das Alter anhand eines Geburtsdatums
     */
    private function calculateAge(string $birthday): ?int
    {
        $raw = trim($birthday);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }
        try {
            $dt  = new \DateTime($raw);
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

    /**
     * Extrahiert die Shop-lokale User-ID aus remote_member_id (Format: "{p4c_shop_id}_{shop_user_id}")
     */
    private function extractShopUserId(string $remoteMemberId): int
    {
        $parts = explode('_', trim($remoteMemberId));
        if (count($parts) >= 2 && ctype_digit($parts[1])) {
            return abs((int)$parts[1]);
        }
        return 0;
    }

    // =========================================================================
    // 1. Cloud-User Gesamtübersicht (GET ?route=users)
    // =========================================================================
    public function list(): string
    {
        $shopIdFilter = abs((int)$this->request->get('shop_id', 0));
        $coinsFilter  = trim((string)$this->request->get('coins', 'all'));
        $deviceFilter = trim((string)$this->request->get('device', 'all'));
        $statusFilter = trim((string)$this->request->get('status', 'all'));
        $searchQuery  = trim((string)$this->request->get('q', ''));
        $sortBy       = trim((string)$this->request->get('sort', 'lastonline_desc'));
        $page         = max(1, abs((int)$this->request->get('page', 1)));
        $perPage      = abs((int)$this->request->get('limit', 50));
        if (!in_array($perPage, [50, 100, 250, 500], true)) {
            $perPage = 50;
        }

        // 1. Globale KPI-Zähler für Cloud-User (1 gebündelte Query)
        $kpi = [
            'total_users'    => 0,
            'active_users'   => 0,
            'locked_users'   => 0,
            'with_coins'     => 0,
            'total_coins'    => 0,
            'mobile_users'   => 0,
            'desktop_users'  => 0,
            'distinct_shops' => 0,
        ];

        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_users`,
                SUM(CASE WHEN `locked` = 0 THEN 1 ELSE 0 END) AS `active_users`,
                SUM(CASE WHEN `locked` = 1 THEN 1 ELSE 0 END) AS `locked_users`,
                SUM(CASE WHEN `amount_coins` > 0 THEN 1 ELSE 0 END) AS `with_coins`,
                COALESCE(SUM(`amount_coins`), 0) AS `total_coins`,
                SUM(CASE WHEN `online_device` = 'mobile' THEN 1 ELSE 0 END) AS `mobile_users`,
                SUM(CASE WHEN `online_device` = 'desktop' THEN 1 ELSE 0 END) AS `desktop_users`,
                COUNT(DISTINCT `p4c_shop_id`) AS `distinct_shops`
             FROM `members`;",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && ($rK = p4c_fetch_object($rsKpi))) {
            $kpi['total_users']    = (int)($rK->total_users ?? 0);
            $kpi['active_users']   = (int)($rK->active_users ?? 0);
            $kpi['locked_users']   = (int)($rK->locked_users ?? 0);
            $kpi['with_coins']     = (int)($rK->with_coins ?? 0);
            $kpi['total_coins']    = (int)($rK->total_coins ?? 0);
            $kpi['mobile_users']   = (int)($rK->mobile_users ?? 0);
            $kpi['desktop_users']  = (int)($rK->desktop_users ?? 0);
            $kpi['distinct_shops'] = (int)($rK->distinct_shops ?? 0);
        }

        // 2. Shop-Filter-Optionen (alle Shops mit Mitgliederanzahl)
        $shopOptions = [];
        $rsShops = p4c_query(
            "SELECT `p4c_shop_id`, MAX(`domain`) AS `domain`, COUNT(*) AS `user_cnt`
             FROM `members`
             WHERE `p4c_shop_id` > 0
             GROUP BY `p4c_shop_id`
             ORDER BY `user_cnt` DESC, `p4c_shop_id` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsShops) {
            while ($sRow = p4c_fetch_object($rsShops)) {
                $sid = abs((int)$sRow->p4c_shop_id);
                $dom = $this->toUtf8((string)($sRow->domain ?? ''));
                $shopOptions[] = [
                    'shop_id' => $sid,
                    'domain'  => $dom !== '' ? $dom : ('Shop #' . $sid),
                    'count'   => (int)($sRow->user_cnt ?? 0),
                ];
            }
        }

        // 3. WHERE-Klausel aufbauen
        $whereParts = ['1=1'];

        if ($shopIdFilter > 0) {
            $whereParts[] = "u.`p4c_shop_id` = '" . $shopIdFilter . "'";
        }

        if ($coinsFilter === 'with_coins') {
            $whereParts[] = "u.`amount_coins` > 0";
        } elseif ($coinsFilter === 'high_coins') {
            $whereParts[] = "u.`amount_coins` >= 1000";
        } elseif ($coinsFilter === 'zero_coins') {
            $whereParts[] = "u.`amount_coins` = 0";
        }

        if ($deviceFilter === 'mobile') {
            $whereParts[] = "u.`online_device` = 'mobile'";
        } elseif ($deviceFilter === 'desktop') {
            $whereParts[] = "u.`online_device` = 'desktop'";
        }

        if ($statusFilter === 'active') {
            $whereParts[] = "u.`locked` = 0";
        } elseif ($statusFilter === 'locked') {
            $whereParts[] = "u.`locked` = 1";
        }

        if ($searchQuery !== '') {
            $qEsc = p4c_escape_string($searchQuery);
            $qParts = [
                "u.`username` LIKE '%" . $qEsc . "%'",
                "u.`email` LIKE '%" . $qEsc . "%'",
                "u.`domain` LIKE '%" . $qEsc . "%'",
                "u.`remote_member_id` LIKE '%" . $qEsc . "%'",
            ];
            if (ctype_digit($searchQuery)) {
                $numQ = abs((int)$searchQuery);
                $qParts[] = "u.`id` = '" . $numQ . "'";
                $qParts[] = "u.`p4c_shop_id` = '" . $numQ . "'";
            }
            $whereParts[] = '(' . implode(' OR ', $qParts) . ')';
        }

        $whereSql = implode(' AND ', $whereParts);

        // Sortierung
        $orderSql = match ($sortBy) {
            'id_desc'       => 'u.`id` DESC',
            'id_asc'        => 'u.`id` ASC',
            'coins_desc'    => 'u.`amount_coins` DESC, u.`id` DESC',
            'username_asc'  => 'u.`username` ASC',
            default         => 'u.`lastonline` DESC, u.`id` DESC',
        };

        // Gesamtanzahl für Paginierung
        $totalFiltered = 0;
        $rsCount = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `members` u WHERE {$whereSql};",
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
        $users = [];
        $rsList = p4c_query(
            "SELECT
                u.`id`,
                u.`remote_member_id`,
                u.`p4c_shop_id`,
                u.`username`,
                u.`amount_coins`,
                u.`birthday`,
                u.`email`,
                u.`online_device`,
                u.`avatar_url`,
                u.`lastonline`,
                u.`locked`,
                u.`domain`
             FROM `members` u
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT " . abs((int)$offset) . ", " . abs((int)$perPage) . ";",
            __FILE__,
            __LINE__
        );

        if ($rsList) {
            while ($row = p4c_fetch_object($rsList)) {
                $uid        = abs((int)$row->id);
                $remoteId   = $this->toUtf8((string)($row->remote_member_id ?? ''));
                $shopId     = abs((int)($row->p4c_shop_id ?? 0));
                $domain     = $this->toUtf8((string)($row->domain ?? ''));
                $username   = $this->toUtf8((string)($row->username ?? ''));
                $email      = $this->toUtf8((string)($row->email ?? ''));
                $birthday   = (string)($row->birthday ?? '');

                $users[] = [
                    'id'               => $uid,
                    'remote_member_id' => $remoteId,
                    'shop_user_id'     => $this->extractShopUserId($remoteId),
                    'p4c_shop_id'      => $shopId,
                    'domain'           => $domain,
                    'username'         => $username !== '' ? $username : ('User #' . $uid),
                    'email'            => $email,
                    'amount_coins'     => (int)($row->amount_coins ?? 0),
                    'birthday_de'      => $this->formatDateTimeDe($birthday, false),
                    'age'              => $this->calculateAge($birthday),
                    'online_device'    => strtolower(trim((string)($row->online_device ?? ''))),
                    'lastonline_de'    => $this->formatDateTimeDe((string)($row->lastonline ?? '')),
                    'locked'           => (int)($row->locked ?? 0) === 1,
                ];
            }
        }

        return View::render('Users', 'list', [
            'kpi'           => $kpi,
            'shopOptions'   => $shopOptions,
            'users'         => $users,
            'totalFiltered' => $totalFiltered,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => $totalPages,
            'filters'       => [
                'shop_id' => $shopIdFilter,
                'coins'   => $coinsFilter,
                'device'  => $deviceFilter,
                'status'  => $statusFilter,
                'q'       => $searchQuery,
                'sort'    => $sortBy,
                'limit'   => $perPage,
            ],
        ]);
    }

    // =========================================================================
    // 2. Cloud-User Detailansicht (GET ?route=user&id=...)
    // =========================================================================
    public function detail(): string
    {
        $rawId = trim((string)$this->request->get('id', ''));
        if ($rawId === '') {
            return '<div class="alert alert-warning m-4"><i class="bi bi-exclamation-triangle me-2"></i>Keine gültige User-ID übergeben. <a href="#/users" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        if (ctype_digit($rawId)) {
            $whereCond = "u.`id` = '" . abs((int)$rawId) . "'";
        } else {
            $esc = p4c_escape_string($rawId);
            $whereCond = "(u.`remote_member_id` = '" . $esc . "' OR u.`id` = '" . abs((int)$rawId) . "')";
        }

        // 1. User-Stammdaten + Shop-Domain in 1 Query laden (statt 9 Einzel-Queries!)
        $rsUser = p4c_query(
            "SELECT
                u.*,
                s.`domain` AS `site_domain`,
                s.`domain` AS `site_name`,
                s.`status` AS `site_status`
             FROM `members` u
             LEFT JOIN `sites` s ON s.`p4c_shop_id` = u.`p4c_shop_id`
             WHERE {$whereCond}
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsUser || p4c_num_rows($rsUser) === 0) {
            return '<div class="alert alert-danger m-4"><i class="bi bi-x-octagon me-2"></i>Der Cloud-User <strong>' . View::e($rawId) . '</strong> existiert nicht. <a href="#/users" class="alert-link">Zurück zur Übersicht</a></div>';
        }

        $uRow     = p4c_fetch_object($rsUser);
        $memberId = abs((int)$uRow->id);
        $shopId   = abs((int)($uRow->p4c_shop_id ?? 0));
        $remoteId = $this->toUtf8((string)($uRow->remote_member_id ?? ''));
        $shopUid  = $this->extractShopUserId($remoteId);

        // 2. Vorheriger / Nächster User
        $prevId = 0;
        $nextId = 0;
        $rsPrev = p4c_query("SELECT `id` FROM `members` WHERE `id` < '" . $memberId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && ($rP = p4c_fetch_object($rsPrev))) {
            $prevId = abs((int)$rP->id);
        }
        $rsNext = p4c_query("SELECT `id` FROM `members` WHERE `id` > '" . $memberId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsNext && ($rN = p4c_fetch_object($rsNext))) {
            $nextId = abs((int)$rN->id);
        }

        // 3. Gekaufte Filme (movies_access) dieses Users laden
        $moviePurchases = [];
        $totalMovieSpentCents = 0;

        $userCondParts = [];
        if ($shopId > 0 && $shopUid > 0) {
            $userCondParts[] = "(ma.`shop_id` = '" . $shopId . "' AND ma.`user_id` = '" . $shopUid . "')";
        }
        $userCondParts[] = "(ma.`shop_id` = '" . $shopId . "' AND ma.`user_id` = '" . $memberId . "')";
        $userAccessWhere = '(' . implode(' OR ', array_unique($userCondParts)) . ')';

        $rsMovAcc = p4c_query(
            "SELECT
                ma.`id`,
                ma.`movie_id` AS `file_id`,
                ma.`user_id`,
                ma.`shop_id`,
                ma.`buy_as`,
                ma.`buy_timestamp`,
                ma.`access_token_datetime`,
                ma.`movie_price`,
                ma.`actor_commision`,
                ma.`actor_commision_percent`,
                mo.`id` AS `online_id`,
                mo.`title` AS `movie_title`,
                mo.`actor_id`,
                a.`username` AS `actor_name`
             FROM `movies_access` ma
             LEFT JOIN `movies_online` mo ON mo.`file_id` = ma.`movie_id`
             LEFT JOIN `actors` a ON a.`id` = mo.`actor_id`
             WHERE {$userAccessWhere}
             ORDER BY ma.`id` DESC
             LIMIT 150;",
            __FILE__,
            __LINE__
        );
        if ($rsMovAcc) {
            while ($ma = p4c_fetch_object($rsMovAcc)) {
                $priceRaw = (int)($ma->movie_price ?? 0);
                $commRaw  = (int)($ma->actor_commision ?? 0);
                $totalMovieSpentCents += $priceRaw;

                $moviePurchases[] = [
                    'id'            => abs((int)$ma->id),
                    'file_id'       => (string)($ma->file_id ?? ''),
                    'online_id'     => abs((int)($ma->online_id ?? 0)),
                    'movie_title'   => $this->toUtf8((string)($ma->movie_title ?? '')),
                    'actor_id'      => abs((int)($ma->actor_id ?? 0)),
                    'actor_name'    => $this->toUtf8((string)($ma->actor_name ?? '')),
                    'buy_as'        => (string)($ma->buy_as ?? 'streaming'),
                    'buy_dt_de'     => $this->formatDateTimeDe((string)($ma->buy_timestamp ?? '')),
                    'token_dt_de'   => $this->formatDateTimeDe((string)($ma->access_token_datetime ?? '')),
                    'price_eur'     => number_format($priceRaw / 100, 2, ',', '.') . ' €',
                    'comm_eur'      => number_format($commRaw / 100, 2, ',', '.') . ' €',
                    'comm_pct'      => (int)($ma->actor_commision_percent ?? 0),
                ];
            }
        }

        // 4. Gekaufte Fotoalben (photo_albums_access) dieses Users laden
        $albumPurchases = [];
        $totalAlbumSpentCents = 0;

        $albumCondParts = [];
        if ($shopId > 0 && $shopUid > 0) {
            $albumCondParts[] = "(pa.`shop_id` = '" . $shopId . "' AND pa.`user_id` = '" . $shopUid . "')";
        }
        $albumCondParts[] = "(pa.`shop_id` = '" . $shopId . "' AND pa.`user_id` = '" . $memberId . "')";
        $albumAccessWhere = '(' . implode(' OR ', array_unique($albumCondParts)) . ')';

        $rsAlbAcc = p4c_query(
            "SELECT
                pa.`id`,
                pa.`album_id` AS `file_id`,
                pa.`buy_as`,
                pa.`buy_timestamp`,
                pa.`album_price`,
                pa.`actor_commision`,
                pa.`actor_commision_percent`,
                pao.`id` AS `online_id`,
                pao.`title` AS `album_title`,
                pao.`actor_id`,
                a.`username` AS `actor_name`
             FROM `photo_albums_access` pa
             LEFT JOIN `photo_albums_online` pao ON pao.`album_id` = pa.`album_id`
             LEFT JOIN `actors` a ON a.`id` = pao.`actor_id`
             WHERE {$albumAccessWhere}
             ORDER BY pa.`id` DESC
             LIMIT 150;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbAcc) {
            while ($pa = p4c_fetch_object($rsAlbAcc)) {
                $priceRaw = (int)($pa->album_price ?? 0);
                $commRaw  = (int)($pa->actor_commision ?? 0);
                $totalAlbumSpentCents += $priceRaw;

                $albumPurchases[] = [
                    'id'          => abs((int)$pa->id),
                    'file_id'     => (string)($pa->file_id ?? ''),
                    'online_id'   => abs((int)($pa->online_id ?? 0)),
                    'album_title' => $this->toUtf8((string)($pa->album_title ?? '')),
                    'actor_id'    => abs((int)($pa->actor_id ?? 0)),
                    'actor_name'  => $this->toUtf8((string)($pa->actor_name ?? '')),
                    'buy_as'      => (string)($pa->buy_as ?? ''),
                    'buy_dt_de'   => $this->formatDateTimeDe((string)($pa->buy_timestamp ?? '')),
                    'price_eur'   => number_format($priceRaw / 100, 2, ',', '.') . ' €',
                    'comm_eur'    => number_format($commRaw / 100, 2, ',', '.') . ' €',
                    'comm_pct'    => (int)($pa->actor_commision_percent ?? 0),
                ];
            }
        }

        $birthdayRaw = (string)($uRow->birthday ?? '');
        $domain      = $this->toUtf8((string)($uRow->domain ?: ($uRow->site_domain ?? '')));

        $user = [
            'id'                => $memberId,
            'remote_member_id'  => $remoteId,
            'shop_user_id'      => $shopUid,
            'p4c_shop_id'       => $shopId,
            'domain'            => $domain,
            'site_name'         => $this->toUtf8((string)($uRow->site_name ?? '')),
            'username'          => $this->toUtf8((string)($uRow->username ?? '')),
            'email'             => $this->toUtf8((string)($uRow->email ?? '')),
            'amount_coins'      => (int)($uRow->amount_coins ?? 0),
            'birthday'          => $birthdayRaw,
            'birthday_de'       => $this->formatDateTimeDe($birthdayRaw, false),
            'age'               => $this->calculateAge($birthdayRaw),
            'online_device'     => strtolower(trim((string)($uRow->online_device ?? ''))),
            'avatar_url'        => $this->toUtf8((string)($uRow->avatar_url ?? '')),
            'lastonline_de'     => $this->formatDateTimeDe((string)($uRow->lastonline ?? '')),
            'last_stats_de'     => $this->formatDateTimeDe((string)($uRow->last_stats_update ?? '')),
            'locked'            => (int)($uRow->locked ?? 0) === 1,
            'movies_bought_cnt' => count($moviePurchases),
            'albums_bought_cnt' => count($albumPurchases),
            'total_spent_eur'   => number_format(($totalMovieSpentCents + $totalAlbumSpentCents) / 100, 2, ',', '.') . ' €',
        ];

        return View::render('Users', 'detail', [
            'user'           => $user,
            'prevId'         => $prevId,
            'nextId'         => $nextId,
            'moviePurchases' => $moviePurchases,
            'albumPurchases' => $albumPurchases,
        ]);
    }

    // =========================================================================
    // 3. Konto-Sperre umschalten (POST ?route=users/toggle-lock)
    // =========================================================================
    public function toggleLock(): void
    {
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $memberId = abs((int)$this->request->post('member_id', 0));
        $lockFlag = (int)$this->request->post('locked', 0) === 1 ? 1 : 0;

        if ($memberId <= 0) {
            Response::error('Ungültige User-ID.', 400);
            return;
        }

        $rsCheck = p4c_query(
            "SELECT `id`, `username` FROM `members` WHERE `id` = '" . $memberId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsCheck || p4c_num_rows($rsCheck) === 0) {
            Response::error('Dieser Cloud-User existiert nicht.', 404);
            return;
        }
        $row = p4c_fetch_object($rsCheck);
        $usr = $this->toUtf8((string)($row->username ?? 'User #' . $memberId));

        p4c_query(
            "UPDATE `members` SET `locked` = '" . $lockFlag . "' WHERE `id` = '" . $memberId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('log_action')) {
            log_action('Cloud-User #' . $memberId . ' (' . $usr . ') ' . ($lockFlag === 1 ? 'gesperrt' : 'entsperrt') . '.');
        }

        Response::json([
            'status'  => 'ok',
            'locked'  => $lockFlag === 1,
            'message' => $lockFlag === 1
                ? "Cloud-User #{$memberId} ({$usr}) wurde gesperrt."
                : "Cloud-User #{$memberId} ({$usr}) wurde wieder freigeschaltet.",
        ]);
    }
}
