<?php
declare(strict_types=1);

namespace App\Modules\Actors;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * EroCloud ACP (v2.0) - Darsteller-Profile Controller (#/actors & #/actor?id=...)
 * Vollständige, N+1-freie Modernisierung von acp/includes/actors.php,
 * acp/includes/ajax/actors.php und acp/includes/actor.php (ohne veraltete 0900-/Chat-Altlasten).
 */
final class ActorsController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Offizielle Auswahl-Optionen für alle Darsteller-Steckbrief-Felder
     *
     * @return array<string, mixed>
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'display_groups' => [
                'only_upload_actor' => 'Filme & Fotoalben',
                'chat_upload_actor' => 'Webcam + Filme & Fotoalben',
                'only_chat_actor'   => 'Nur Webcam',
            ],
            'obolus_types' => [
                'tip'     => 'Trinkgeld senden (Porno / Erotik)',
                'tribute' => 'Tribut zollen (Fetisch / Domina)',
            ],
            'genders' => [
                'f' => 'Weiblich',
                'm' => 'Männlich',
                't' => 'Transgender',
            ],
            'shaven_options' => [
                1 => 'Ja',
                0 => 'Nein',
            ],
            'star_signs' => [
                'Steinbock', 'Wassermann', 'Fische', 'Widder', 'Stier', 'Zwillinge',
                'Krebs', 'Löwe', 'Jungfrau', 'Waage', 'Skorpion', 'Schütze',
            ],
            'hair_colors' => [
                'weiß', 'blond', 'dunkelblond', 'rot', 'hellbraun', 'dunkelbraun', 'schwarz',
            ],
            'eye_colors' => [
                'Hellblau', 'Blaugrau', 'Dunkelblau', 'Hellbraun',
                'Dunkelbraun', 'Hellgrün', 'Grüngrau', 'Dunkelgrün',
            ],
            'cup_sizes_a' => [
                '65', '70', '75', '80', '85', '90', '95', '100', '105', '110',
            ],
            'cup_sizes_b' => [
                'AA', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
            ],
            'sexual_orientations' => [
                'Hetero', 'Homo', 'Bi',
            ],
            'marital_statuses' => [
                'ledig', 'verheiratet', 'geschieden', 'verwitwet', 'in fester Beziehung', 'verlobt',
            ],
            'interests' => [
                'Feste Beziehung',
                'One night stand',
                'Seitensprung',
                'regelmäßige Sextreffen',
                'SM-Spiele',
                'Sexuellen Austausch übers Internet',
            ],
            'looking_for' => [
                'Männer',
                'Frauen',
                'Paare (m,w)',
                'Paare (m,m)',
                'Paare (w,w)',
            ],
            'sexual_preferences' => [
                'Blümchensex', 'Sexy Wäsche', 'Sex im Freien', 'Sex mit Toys',
                'Dicke Titten', 'Oralsex', 'Deepthroating (Extremblasen)', 'Bukkake',
                'Creampie', 'Analsex', 'Doppelte Penetrationen', 'Interracial',
                'Große Schwänze', 'Sex mit 2 Frauen', 'Sex mit 2 Männern', 'Sex-Parties',
                'Swinger-Clubs', 'Gruppensex', 'Face-Sitting', 'Rimming (Polecken)',
                'Uniformen', 'Rollenspiele', 'Voyeur', 'Nylons',
                'High-Heels', 'Füße', 'Trampling', 'Lack und Leder',
                'S/M', 'Bondage', 'Spanking', 'Bizarr',
                'Wachs-Spiele', 'Dominas & Sklaven', 'Dominant', 'Devot',
                'Doktor-Spiele', 'Natursekt', 'Fisting', 'Dicke',
                'Omis', 'Gothic',
            ],
        ];
    }

    /**
     * GET ?route=actors
     * Gesamtübersicht aller Darsteller-Profile inkl. KPI-Kacheln, Filterung, Sortierung & Paginierung.
     */
    public function list(): string
    {
        $statusFilter  = trim((string)$this->request->get('status', 'all'));
        $mainCatFilter = trim((string)$this->request->get('main_cat', 'all'));
        $groupFilter   = trim((string)$this->request->get('display_group', 'all'));
        $imgFilter     = trim((string)$this->request->get('img_filter', 'all'));
        $merchantParam = abs((int)$this->request->get('merchant_id', 0));
        $searchQuery   = trim((string)$this->request->get('q', ''));
        $sortBy        = trim((string)$this->request->get('sort', 'id_desc'));
        $page          = max(1, abs((int)$this->request->get('page', 1)));
        $perPage       = abs((int)$this->request->get('per_page', 50));
        if (!in_array($perPage, [50, 100, 250, 500], true)) {
            $perPage = 50;
        }

        $fiveDaysAgo = date('Y-m-d H:i:s', strtotime('-5 days'));

        // 1. Globale KPI-Zähler in einer einzigen Aggregations-Query laden
        $rsKpi = \p4c_query(
            "SELECT
                COUNT(*) AS `total_cnt`,
                SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) AS `active_cnt`,
                SUM(CASE WHEN `status` = 'inactive' THEN 1 ELSE 0 END) AS `inactive_cnt`,
                SUM(CASE WHEN `status` = 'blocked' THEN 1 ELSE 0 END) AS `blocked_cnt`,
                SUM(CASE WHEN `status` = 'deleted' THEN 1 ELSE 0 END) AS `deleted_cnt`,
                SUM(CASE WHEN `status` = 'inactive' AND `created_datetime` >= '" . \p4c_escape_string($fiveDaysAgo) . "' THEN 1 ELSE 0 END) AS `new_5d_cnt`,
                SUM(CASE WHEN FIND_IN_SET('porn', `actor_categories`) > 0 THEN 1 ELSE 0 END) AS `porn_cnt`,
                SUM(CASE WHEN FIND_IN_SET('fetish', `actor_categories`) > 0 THEN 1 ELSE 0 END) AS `fetish_cnt`,
                SUM(CASE WHEN (`profile_image_fsk16` = '' OR `profile_image_fsk18` = '') AND `status` != 'deleted' THEN 1 ELSE 0 END) AS `missing_img_cnt`
             FROM `actors`;",
            __FILE__,
            __LINE__
        );
        $kpiObj = ($rsKpi && \p4c_num_rows($rsKpi) > 0) ? \p4c_fetch_object($rsKpi) : null;

        $kpis = [
            'total'       => abs((int)($kpiObj->total_cnt ?? 0)),
            'active'      => abs((int)($kpiObj->active_cnt ?? 0)),
            'inactive'    => abs((int)($kpiObj->inactive_cnt ?? 0)),
            'blocked'     => abs((int)($kpiObj->blocked_cnt ?? 0)),
            'deleted'     => abs((int)($kpiObj->deleted_cnt ?? 0)),
            'new_5d'      => abs((int)($kpiObj->new_5d_cnt ?? 0)),
            'porn'        => abs((int)($kpiObj->porn_cnt ?? 0)),
            'fetish'      => abs((int)($kpiObj->fetish_cnt ?? 0)),
            'missing_img' => abs((int)($kpiObj->missing_img_cnt ?? 0)),
        ];

        // 2. Film- & Fotoalben-Zähler pro Darsteller gebündelt vorab laden (0 N+1 Queries)
        $movieStatsByActor = [];
        $rsMovCnt = \p4c_query(
            "SELECT `actor_id`,
                    COUNT(*) AS `total_cnt`,
                    SUM(CASE WHEN `status` = 'active' AND `released` = '1' AND `movie_checked` != '0000-00-00 00:00:00' THEN 1 ELSE 0 END) AS `online_cnt`,
                    SUM(CASE WHEN `released` = '1' AND `movie_checked` = '0000-00-00 00:00:00' AND `convert_status` > '1' THEN 1 ELSE 0 END) AS `checking_cnt`
             FROM `movies`
             WHERE `status` != 'deleted'
             GROUP BY `actor_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsMovCnt) {
            while ($mr = \p4c_fetch_object($rsMovCnt)) {
                $movieStatsByActor[abs((int)$mr->actor_id)] = [
                    'total'    => abs((int)$mr->total_cnt),
                    'online'   => abs((int)$mr->online_cnt),
                    'checking' => abs((int)$mr->checking_cnt),
                ];
            }
        }

        $albumStatsByActor = [];
        $rsAlbCnt = \p4c_query(
            "SELECT `actor_id`,
                    COUNT(*) AS `total_cnt`,
                    SUM(CASE WHEN `status` = 'active' AND `released` = '1' AND `album_checked` != '0000-00-00 00:00:00' THEN 1 ELSE 0 END) AS `online_cnt`,
                    SUM(CASE WHEN `released` = '1' AND `album_checked` = '0000-00-00 00:00:00' THEN 1 ELSE 0 END) AS `checking_cnt`
             FROM `photo_albums`
             WHERE `status` != 'deleted'
             GROUP BY `actor_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbCnt) {
            while ($ar = \p4c_fetch_object($rsAlbCnt)) {
                $albumStatsByActor[abs((int)$ar->actor_id)] = [
                    'total'    => abs((int)$ar->total_cnt),
                    'online'   => abs((int)$ar->online_cnt),
                    'checking' => abs((int)$ar->checking_cnt),
                ];
            }
        }

        // 3. WHERE-Bedingungen aufbauen
        $where = ['1=1'];

        if ($statusFilter === 'new_5d') {
            $where[] = "a.`status` = 'inactive' AND a.`created_datetime` >= '" . \p4c_escape_string($fiveDaysAgo) . "'";
        } elseif (in_array($statusFilter, ['active', 'inactive', 'blocked', 'deleted'], true)) {
            $where[] = "a.`status` = '" . \p4c_escape_string($statusFilter) . "'";
        }

        if ($mainCatFilter === 'porn') {
            $where[] = "FIND_IN_SET('porn', a.`actor_categories`) > 0";
        } elseif ($mainCatFilter === 'fetish') {
            $where[] = "FIND_IN_SET('fetish', a.`actor_categories`) > 0";
        } elseif ($mainCatFilter === 'none') {
            $where[] = "(a.`actor_categories` = '' OR (FIND_IN_SET('porn', a.`actor_categories`) = 0 AND FIND_IN_SET('fetish', a.`actor_categories`) = 0))";
        }

        if (in_array($groupFilter, ['only_upload_actor', 'chat_upload_actor', 'only_chat_actor'], true)) {
            $where[] = "a.`is_displayed_as` = '" . \p4c_escape_string($groupFilter) . "'";
        }

        if ($imgFilter === 'both_ok') {
            $where[] = "(a.`profile_image_fsk16` != '' AND a.`profile_image_fsk18` != '')";
        } elseif ($imgFilter === 'missing_img') {
            $where[] = "(a.`profile_image_fsk16` = '' OR a.`profile_image_fsk18` = '')";
        }

        if ($merchantParam > 0) {
            $where[] = "a.`merchant_id` = '" . $merchantParam . "'";
        }

        if ($searchQuery !== '') {
            $escQ     = \p4c_escape_string($searchQuery);
            $cleanNum = ltrim($searchQuery, '#');
            if ($cleanNum !== '' && ctype_digit($cleanNum)) {
                $numQ    = abs((int)$cleanNum);
                $where[] = "(a.`id` = '{$numQ}' OR a.`merchant_id` = '{$numQ}' OR m.`partner_id` = '{$numQ}' OR a.`username` LIKE '%{$escQ}%')";
            } else {
                $where[] = "(a.`username` LIKE '%{$escQ}%' OR CAST(AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS CHAR) LIKE '%{$escQ}%' OR a.`admin_infos` LIKE '%{$escQ}%')";
            }
        }

        $whereSql = implode(' AND ', $where);

        // Gesamtanzahl für aktuelle Filterung ermitteln
        $rsFilteredCnt = \p4c_query(
            "SELECT COUNT(*) AS `cnt`
             FROM `actors` a
             LEFT JOIN `merchants` m ON a.`merchant_id` = m.`id`
             WHERE {$whereSql};",
            __FILE__,
            __LINE__
        );
        $totalFiltered = ($rsFilteredCnt && \p4c_num_rows($rsFilteredCnt) > 0)
            ? abs((int)(\p4c_fetch_object($rsFilteredCnt)->cnt ?? 0))
            : 0;

        $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $orderSql = match ($sortBy) {
            'id_asc'          => 'a.`id` ASC',
            'username_asc'    => 'a.`username` ASC, a.`id` DESC',
            'lastonline_desc' => 'a.`lastonline` DESC, a.`id` DESC',
            'created_desc'    => 'a.`created_datetime` DESC, a.`id` DESC',
            default           => 'a.`id` DESC',
        };

        $rsActors = \p4c_query(
            "SELECT
                a.`id`,
                a.`merchant_id`,
                a.`created_datetime`,
                a.`status`,
                a.`username`,
                a.`is_displayed_as`,
                a.`profile_image_fsk16`,
                a.`profile_image_fsk18`,
                a.`lastonline`,
                a.`actor_categories`,
                a.`gender`,
                a.`age`,
                a.`body_height`,
                a.`cup_size`,
                a.`hair_color`,
                a.`amoredea_provision_content`,
                a.`admin_infos`,
                m.`partner_id`,
                AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS `merchant_username`
             FROM `actors` a
             LEFT JOIN `merchants` m ON a.`merchant_id` = m.`id`
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT {$offset}, {$perPage};",
            __FILE__,
            __LINE__
        );

        $defs   = self::getFieldDefinitions();
        $actors = [];

        if ($rsActors) {
            while ($row = \p4c_fetch_object($rsActors)) {
                $aid   = abs((int)$row->id);
                $mid   = abs((int)$row->merchant_id);
                $uname = trim($this->cleanUtf8((string)$row->username));
                if ($uname === '') {
                    $uname = '- Unbenannt -';
                }

                $cats = array_filter(array_map('trim', explode(',', (string)$row->actor_categories)));
                $mainCatKey   = 'none';
                $mainCatLabel = '-';
                if (in_array('porn', $cats, true)) {
                    $mainCatKey   = 'porn';
                    $mainCatLabel = 'Porno';
                } elseif (in_array('fetish', $cats, true)) {
                    $mainCatKey   = 'fetish';
                    $mainCatLabel = 'Fetisch';
                }

                $dispKey   = (string)$row->is_displayed_as;
                $dispLabel = $defs['display_groups'][$dispKey] ?? 'Filme & Fotoalben';

                $fsk16 = trim((string)$row->profile_image_fsk16);
                $fsk18 = trim((string)$row->profile_image_fsk18);

                $createdStr = (string)$row->created_datetime;
                $createdTs  = ($createdStr !== '' && $createdStr !== '0000-00-00 00:00:00') ? (int)strtotime($createdStr) : 0;
                $isNew5d    = ($createdTs > 0 && $createdStr >= $fiveDaysAgo && (string)$row->status === 'inactive');

                $lastOnlineTs  = abs((int)$row->lastonline);
                $lastOnlineFmt = $lastOnlineTs > 0 ? date('d.m.Y H:i', $lastOnlineTs) : '-';

                $mStat = $movieStatsByActor[$aid] ?? ['total' => 0, 'online' => 0, 'checking' => 0];
                $aStat = $albumStatsByActor[$aid] ?? ['total' => 0, 'online' => 0, 'checking' => 0];

                $genderKey   = (string)($row->gender ?? 'f');
                $genderLabel = $defs['genders'][$genderKey] ?? 'Weiblich';

                $actors[] = [
                    'id'                => $aid,
                    'merchant_id'       => $mid,
                    'partner_id'        => abs((int)($row->partner_id ?? 0)),
                    'merchant_username' => $this->cleanUtf8((string)($row->merchant_username ?? '')),
                    'username'          => $uname,
                    'status'            => (string)$row->status,
                    'is_new_5d'         => $isNew5d,
                    'created_fmt'       => $createdTs > 0 ? date('d.m.Y H:i', $createdTs) : '-',
                    'lastonline_fmt'    => $lastOnlineFmt,
                    'main_cat_key'      => $mainCatKey,
                    'main_cat_label'    => $mainCatLabel,
                    'display_key'       => $dispKey,
                    'display_label'     => $dispLabel,
                    'has_fsk16'         => ($fsk16 !== ''),
                    'has_fsk18'         => ($fsk18 !== ''),
                    'fsk16_file'        => $fsk16,
                    'fsk18_file'        => $fsk18,
                    'gender_label'      => $genderLabel,
                    'age'               => abs((int)$row->age),
                    'body_height'       => abs((int)$row->body_height),
                    'cup_size'          => $this->cleanUtf8((string)$row->cup_size),
                    'hair_color'        => $this->cleanUtf8((string)$row->hair_color),
                    'provision_content' => abs((int)$row->amoredea_provision_content) ?: 50,
                    'admin_infos'       => $this->cleanUtf8((string)$row->admin_infos),
                    'movies_total'      => $mStat['total'],
                    'movies_online'     => $mStat['online'],
                    'movies_checking'   => $mStat['checking'],
                    'albums_total'      => $aStat['total'],
                    'albums_online'     => $aStat['online'],
                    'albums_checking'   => $aStat['checking'],
                ];
            }
        }

        return View::render('Actors', 'list', [
            'kpis'          => $kpis,
            'actors'        => $actors,
            'totalFiltered' => $totalFiltered,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => $totalPages,
            'filters'       => [
                'status'        => $statusFilter,
                'main_cat'      => $mainCatFilter,
                'display_group' => $groupFilter,
                'img_filter'    => $imgFilter,
                'merchant_id'   => $merchantParam,
                'q'             => $searchQuery,
                'sort'          => $sortBy,
            ],
        ]);
    }

    /**
     * GET ?route=actor&id=...
     * Detail- und Bearbeitungs-Cockpit für ein Darsteller-Profil (3 Tabs: Profil-Stammdaten, Videos, Fotoalben).
     */
    public function detail(): string
    {
        $actorId = abs((int)$this->request->get('id', 0));
        if ($actorId <= 0) {
            return '<div class="alert alert-warning m-4">Bitte wähle ein gültiges Darsteller-Profil aus. <a href="#/actors" class="alert-link ms-2">Zur Profil-Übersicht</a></div>';
        }

        // 1. Darsteller + Merchant-Stammdaten in 1 Query laden
        $rsActor = \p4c_query(
            "SELECT a.*,
                    m.`partner_id`,
                    AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS `merchant_username`,
                    AES_DECRYPT(m.`firstname`, '" . AES_KEY . "') AS `merchant_firstname`,
                    AES_DECRYPT(m.`surname`, '" . AES_KEY . "') AS `merchant_surname`
             FROM `actors` a
             LEFT JOIN `merchants` m ON a.`merchant_id` = m.`id`
             WHERE a.`id` = '" . $actorId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsActor || \p4c_num_rows($rsActor) === 0) {
            return '<div class="alert alert-danger m-4 d-flex justify-content-between align-items-center">'
                . '<div><i class="bi bi-exclamation-octagon-fill me-2"></i>Das Darsteller-Profil <strong>#' . $actorId . '</strong> wurde nicht gefunden.</div>'
                . '<a href="#/actors" class="btn btn-sm btn-outline-danger">Zurück zur Profil-Übersicht</a>'
                . '</div>';
        }

        $obj = \p4c_fetch_object($rsActor);

        // Vorheriges / Nächstes Profil für schnelle Navigation
        $prevId = 0;
        $rsPrev = \p4c_query("SELECT `id` FROM `actors` WHERE `id` > '" . $actorId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && \p4c_num_rows($rsPrev) > 0) {
            $prevId = abs((int)(\p4c_fetch_object($rsPrev)->id ?? 0));
        }

        $nextId = 0;
        $rsNext = \p4c_query("SELECT `id` FROM `actors` WHERE `id` < '" . $actorId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsNext && \p4c_num_rows($rsNext) > 0) {
            $nextId = abs((int)(\p4c_fetch_object($rsNext)->id ?? 0));
        }

        // Körbchengröße ("75B") aufteilen
        $rawCup   = trim($this->cleanUtf8((string)($obj->cup_size ?? '75B')));
        $cupSizeA = '75';
        $cupSizeB = 'B';
        if (preg_match('/^(\d+)\s*([A-Za-z]+)$/', $rawCup, $mCup)) {
            $cupSizeA = $mCup[1];
            $cupSizeB = strtoupper($mCup[2]);
        }

        $actorCategories = array_values(array_filter(array_map('trim', explode(',', (string)$obj->actor_categories))));

        $createdStr = (string)$obj->created_datetime;
        $createdFmt = ($createdStr !== '' && $createdStr !== '0000-00-00 00:00:00')
            ? date('d.m.Y H:i', (int)strtotime($createdStr))
            : '-';

        $lastOnlineTs  = abs((int)$obj->lastonline);
        $lastOnlineFmt = $lastOnlineTs > 0 ? date('d.m.Y H:i', $lastOnlineTs) : '-';

        $actor = [
            'id'                          => $actorId,
            'merchant_id'                 => abs((int)$obj->merchant_id),
            'partner_id'                  => abs((int)($obj->partner_id ?? 0)),
            'merchant_status'             => 'active',
            'merchant_username'           => $this->cleanUtf8((string)($obj->merchant_username ?? '')),
            'merchant_fullname'           => trim($this->cleanUtf8((string)($obj->merchant_firstname ?? '') . ' ' . (string)($obj->merchant_surname ?? ''))),
            'merchant_company'            => '',
            'md5_checksum'                => (string)$obj->md5_checksum,
            'created_fmt'                 => $createdFmt,
            'lastonline_fmt'              => $lastOnlineFmt,
            'status'                      => (string)$obj->status,
            'username'                    => $this->cleanUtf8((string)$obj->username),
            'is_displayed_as'             => (string)($obj->is_displayed_as ?: 'only_upload_actor'),
            'profile_image_fsk16'         => trim((string)$obj->profile_image_fsk16),
            'profile_image_fsk18'         => trim((string)$obj->profile_image_fsk18),
            'admin_infos'                 => $this->cleanUtf8((string)$obj->admin_infos),
            'amoredea_provision_content'  => abs((int)$obj->amoredea_provision_content) >= 40 ? abs((int)$obj->amoredea_provision_content) : 50,
            'amoredea_provision_obolus'   => abs((int)$obj->amoredea_provision_obolus) >= 40 ? abs((int)$obj->amoredea_provision_obolus) : 70,
            'obolus_type'                 => (string)($obj->obolus_type ?: 'tip'),
            'cam_amount'                  => abs((int)$obj->cam_amount),
            'usercam_if_amacam'           => abs((int)$obj->usercam_if_amacam),
            'actor_categories'            => $actorCategories,
            'check_about_me'              => abs((int)$obj->check_about_me),
            'about_me'                    => $this->cleanUtf8((string)$obj->about_me),
            'check_age'                   => abs((int)$obj->check_age),
            'age'                         => max(18, min(99, abs((int)$obj->age) ?: 25)),
            'check_star_sign'             => abs((int)$obj->check_star_sign),
            'star_sign'                   => $this->cleanUtf8((string)($obj->star_sign ?: 'Steinbock')),
            'check_body_height'           => abs((int)$obj->check_body_height),
            'body_height'                 => max(100, min(230, abs((int)$obj->body_height) ?: 168)),
            'check_body_weight'           => abs((int)$obj->check_body_weight),
            'body_weight'                 => max(45, min(150, abs((int)$obj->body_weight) ?: 60)),
            'check_gender'                => abs((int)$obj->check_gender),
            'gender'                      => (string)($obj->gender ?: 'f'),
            'check_shaven'                => abs((int)$obj->check_shaven),
            'shaven'                      => abs((int)$obj->shaven),
            'check_hair_color'            => abs((int)$obj->check_hair_color),
            'hair_color'                  => $this->cleanUtf8((string)($obj->hair_color ?: 'blond')),
            'check_eye_color'             => abs((int)$obj->check_eye_color),
            'eye_color'                   => $this->cleanUtf8((string)($obj->eye_color ?: 'Hellblau')),
            'check_cup_size'              => abs((int)$obj->check_cup_size),
            'cup_size_a'                  => $cupSizeA,
            'cup_size_b'                  => $cupSizeB,
            'check_sexual_orientation'    => abs((int)$obj->check_sexual_orientation),
            'sexual_orientation'          => $this->cleanUtf8((string)($obj->sexual_orientation ?: 'Hetero')),
            'check_marital_status'        => abs((int)$obj->check_marital_status),
            'marital_status'              => $this->cleanUtf8((string)($obj->marital_status ?: 'ledig')),
            'check_interests'             => abs((int)$obj->check_interests),
            'interests_list'              => $this->parsePipeOrCommaList((string)$obj->interests),
            'check_looking_for'           => abs((int)$obj->check_looking_for),
            'looking_for_list'            => $this->parsePipeOrCommaList((string)$obj->looking_for),
            'check_sexual_preferences'    => abs((int)$obj->check_sexual_preferences),
            'sexual_preferences_list'     => $this->parsePipeOrCommaList((string)$obj->sexual_preferences),
        ];

        // 2. Alle Kategorien aus actor_categories nach Gruppe laden
        $categoriesByGroup = [
            'main'       => [],
            'weight'     => [],
            'skin_color' => [],
            'hair_color' => [],
            'gender'     => [],
            'age'        => [],
            'body'       => [],
        ];
        $rsCats = \p4c_query("SELECT * FROM `actor_categories` ORDER BY `category_group` ASC, `name_id` ASC;", __FILE__, __LINE__);
        if ($rsCats) {
            while ($cRow = \p4c_fetch_object($rsCats)) {
                $grp = (string)$cRow->category_group;
                if (!isset($categoriesByGroup[$grp])) {
                    $categoriesByGroup[$grp] = [];
                }
                $categoriesByGroup[$grp][] = [
                    'name_id' => (string)$cRow->name_id,
                    'label'   => $this->cleanUtf8((string)$cRow->de_name_value),
                    'desc'    => $this->cleanUtf8((string)$cRow->de_name_text),
                ];
            }
        }
        // Main-Kategorie absteigend (Porno vor Fetisch wie im Legacy-System)
        usort($categoriesByGroup['main'], static fn(array $a, array $b): int => strcmp($b['name_id'], $a['name_id']));

        // 3. Partner-Umzug (actor_reassign) + Händler-Auswahlliste laden
        $pendingReassign   = null;
        $completedReassign = [];
        $rsReassign = \p4c_query(
            "SELECT ar.*,
                    m_old.`partner_id` AS `old_partner_id`,
                    AES_DECRYPT(m_old.`username`, '" . AES_KEY . "') AS `old_username`,
                    m_new.`partner_id` AS `new_partner_id`,
                    AES_DECRYPT(m_new.`username`, '" . AES_KEY . "') AS `new_username`
             FROM `actor_reassign` ar
             LEFT JOIN `merchants` m_old ON ar.`old_merchant_id` = m_old.`id`
             LEFT JOIN `merchants` m_new ON ar.`new_merchant_id` = m_new.`id`
             WHERE ar.`actor_id` = '" . $actorId . "'
             ORDER BY ar.`id` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsReassign) {
            while ($rRow = \p4c_fetch_object($rsReassign)) {
                $entry = [
                    'id'                      => abs((int)$rRow->id),
                    'old_merchant_id'         => abs((int)$rRow->old_merchant_id),
                    'old_partner_id'          => abs((int)($rRow->old_partner_id ?? 0)),
                    'old_username'            => $this->cleanUtf8((string)($rRow->old_username ?? '')),
                    'new_merchant_id'         => abs((int)$rRow->new_merchant_id),
                    'new_partner_id'          => abs((int)($rRow->new_partner_id ?? 0)),
                    'new_username'            => $this->cleanUtf8((string)($rRow->new_username ?? '')),
                    'order_from'              => (string)$rRow->order_from,
                    'files_mapping_completed' => (string)$rRow->files_mapping_completed,
                ];
                if ((string)$rRow->files_mapping_completed === '0000-00-00 00:00:00') {
                    $pendingReassign = $entry;
                } else {
                    $completedReassign[] = $entry;
                }
            }
        }

        $allMerchants = [];
        $rsMerchants  = \p4c_query(
            "SELECT `id`, `partner_id`, AES_DECRYPT(`username`, '" . AES_KEY . "') AS `username`
             FROM `merchants`
             ORDER BY `username` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsMerchants) {
            while ($mRow = \p4c_fetch_object($rsMerchants)) {
                $allMerchants[] = [
                    'id'         => abs((int)$mRow->id),
                    'partner_id' => abs((int)$mRow->partner_id),
                    'username'   => $this->cleanUtf8((string)$mRow->username),
                ];
            }
        }

        // 4. Zugeordnete Filme in 1 einzigen Query laden (inkl. movies_online.id per LEFT JOIN)
        $actorMovies = [];
        $rsMovies    = \p4c_query(
            "SELECT m.`id`, m.`file_id`, m.`status`, m.`released`, m.`movie_checked`, m.`convert_status`,
                    m.`title`, m.`amount_own`, m.`playtime_seconds`, m.`amount_second`, m.`playtime_string`, m.`online_at`, m.`resolution`,
                    mo.`id` AS `online_id`, mo.`purchases`, mo.`views`
             FROM `movies` m
             LEFT JOIN `movies_online` mo ON m.`file_id` = mo.`file_id`
             WHERE m.`actor_id` = '" . $actorId . "'
             ORDER BY m.`id` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsMovies) {
            while ($mov = \p4c_fetch_object($rsMovies)) {
                $mId      = abs((int)$mov->id);
                $onlineId = abs((int)($mov->online_id ?? 0));
                $released = (int)$mov->released;
                $checked  = (string)$mov->movie_checked;
                $conv     = (int)$mov->convert_status;

                $workflowBadge = '';
                $isChecking    = false;
                if ($released === 0) {
                    $workflowBadge = 'Entwurf';
                } elseif ($released === 1 && $checked === '0000-00-00 00:00:00') {
                    $workflowBadge = $conv > 1 ? 'In Prüfung' : 'Konvertiert...';
                    $isChecking    = ($conv > 1);
                } elseif ($released === 2) {
                    $workflowBadge = 'Abgelehnt';
                }

                $actionHref  = $isChecking
                    ? ('#/movie-checking?id=' . $mId)
                    : ('#/movie-edit?id=' . ($onlineId > 0 ? $onlineId : $mId));
                $actionLabel = $isChecking ? 'Jetzt prüfen' : 'Bearbeiten';

                $coinsOwn = abs((int)($mov->amount_own ?? 0));
                if ($coinsOwn === 0) {
                    $coinsOwn = (int)round(abs((int)($mov->playtime_seconds ?? 0)) * (float)($mov->amount_second ?? 0));
                }

                $actorMovies[] = [
                    'id'             => $mId,
                    'online_id'      => $onlineId,
                    'file_id'        => (string)$mov->file_id,
                    'status'         => (string)$mov->status,
                    'workflow_badge' => $workflowBadge,
                    'is_checking'    => $isChecking,
                    'title'          => $this->cleanUtf8((string)$mov->title),
                    'selling_price'  => (string)$coinsOwn,
                    'playtime'       => (string)($mov->playtime_string ?: '-'),
                    'resolution'     => (string)($mov->resolution ?: 'HD'),
                    'online_at'      => (string)$mov->online_at,
                    'purchases'      => abs((int)($mov->purchases ?? 0)),
                    'views'          => abs((int)($mov->views ?? 0)),
                    'action_href'    => $actionHref,
                    'action_label'   => $actionLabel,
                ];
            }
        }

        // 5. Zugeordnete Fotoalben in 1 einzigen Query laden (inkl. photo_albums_online.id per LEFT JOIN)
        $actorAlbums = [];
        $rsAlbums    = \p4c_query(
            "SELECT pa.`id`, pa.`album_id`, pa.`status`, pa.`released`, pa.`album_checked`,
                    pa.`title`, pa.`number_of_photos`, pa.`amount_download`, pa.`online_at`,
                    pao.`id` AS `online_id`, pao.`purchases`, pao.`views`
             FROM `photo_albums` pa
             LEFT JOIN `photo_albums_online` pao ON pa.`album_id` = pao.`album_id`
             WHERE pa.`actor_id` = '" . $actorId . "'
             ORDER BY pa.`id` DESC;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbums) {
            while ($alb = \p4c_fetch_object($rsAlbums)) {
                $aId      = abs((int)$alb->id);
                $onlineId = abs((int)($alb->online_id ?? 0));
                $released = (int)$alb->released;
                $checked  = (string)$alb->album_checked;

                $workflowBadge = '';
                $isChecking    = false;
                if ($released === 0) {
                    $workflowBadge = 'Entwurf';
                } elseif ($released === 1 && $checked === '0000-00-00 00:00:00') {
                    $workflowBadge = 'In Prüfung';
                    $isChecking    = true;
                } elseif ($released === 2) {
                    $workflowBadge = 'Abgelehnt';
                }

                $actionHref  = $isChecking
                    ? ('#/photo-album-checking?id=' . $aId)
                    : ('#/photo-album-edit?id=' . ($onlineId > 0 ? $onlineId : $aId));
                $actionLabel = $isChecking ? 'Jetzt prüfen' : 'Bearbeiten';

                $actorAlbums[] = [
                    'id'               => $aId,
                    'online_id'        => $onlineId,
                    'album_id'         => (string)$alb->album_id,
                    'status'           => (string)$alb->status,
                    'workflow_badge'   => $workflowBadge,
                    'is_checking'      => $isChecking,
                    'title'            => $this->cleanUtf8((string)$alb->title),
                    'number_of_photos' => abs((int)$alb->number_of_photos),
                    'selling_price'    => number_format(abs((int)($alb->amount_download ?? 0)) / 100, 2, ',', '.'),
                    'online_at'        => (string)$alb->online_at,
                    'purchases'        => abs((int)($alb->purchases ?? 0)),
                    'views'            => abs((int)($alb->views ?? 0)),
                    'action_href'      => $actionHref,
                    'action_label'     => $actionLabel,
                ];
            }
        }

        return View::render('Actors', 'detail', [
            'actor'             => $actor,
            'defs'              => self::getFieldDefinitions(),
            'categoriesByGroup' => $categoriesByGroup,
            'pendingReassign'   => $pendingReassign,
            'completedReassign' => $completedReassign,
            'allMerchants'      => $allMerchants,
            'actorMovies'       => $actorMovies,
            'actorAlbums'       => $actorAlbums,
            'prevId'            => $prevId,
            'nextId'            => $nextId,
        ]);
    }

    /**
     * POST ?route=actors/save
     * Speichert sämtliche Stammdaten, Kategorien, Provisionen und Status eines Darsteller-Profils.
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheits-Token (CSRF) ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $actorId = abs((int)$this->request->post('actor_id', 0));
        if ($actorId <= 0) {
            Response::error('Ungültige Profil-ID.', 400);
            return;
        }

        $rsExists = \p4c_query("SELECT `id`, `username`, `status` FROM `actors` WHERE `id` = '" . $actorId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsExists || \p4c_num_rows($rsExists) === 0) {
            Response::error('Das Darsteller-Profil #' . $actorId . ' existiert nicht.', 404);
            return;
        }

        $defs = self::getFieldDefinitions();

        // 1. Benutzernamen bereinigen & auf Duplikate prüfen (exakt wie in acp/includes/actor.php)
        $rawUsername  = trim((string)$this->request->post('username', ''));
        $searchUml    = ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'];
        $replaceUml   = ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue'];
        $postUsername = str_replace($searchUml, $replaceUml, $rawUsername);
        $postUsername = trim((string)preg_replace('/[^a-zA-Z0-9-_.]/i', '', $postUsername));

        if ($postUsername === '' || strlen($postUsername) < 2) {
            Response::error('Bitte gib einen gültigen Profilnamen (mindestens 2 Zeichen) ein.', 400);
            return;
        }

        $rsCheckName = \p4c_query(
            "SELECT `id` FROM `actors`
             WHERE `username` = '" . \p4c_escape_string($postUsername) . "'
               AND `id` != '" . $actorId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsCheckName && \p4c_num_rows($rsCheckName) > 0) {
            Response::error("Der Profilname „{$postUsername}“ ist leider bereits an ein anderes Profil vergeben.", 400);
            return;
        }

        // 2. Status, Nutzung & Provisionen
        $status = trim((string)$this->request->post('actor_status', 'inactive'));
        if (!in_array($status, ['active', 'inactive', 'blocked', 'deleted'], true)) {
            $status = 'inactive';
        }

        $isDisplayedAs = trim((string)$this->request->post('is_displayed_as', 'only_upload_actor'));
        if (!array_key_exists($isDisplayedAs, $defs['display_groups'])) {
            $isDisplayedAs = 'only_upload_actor';
        }

        $provContent = abs((int)$this->request->post('amoredea_provision_content', 50));
        if ($provContent < 40 || $provContent > 80) {
            $provContent = 50;
        }

        $provObolus = abs((int)$this->request->post('amoredea_provision_obolus', 70));
        if ($provObolus < 40 || $provObolus > 80) {
            $provObolus = 70;
        }

        $obolusType = trim((string)$this->request->post('obolus_type', 'tip'));
        if (!array_key_exists($obolusType, $defs['obolus_types'])) {
            $obolusType = 'tip';
        }

        // 3. Darsteller-Kategorien (categorys[])
        $rawCats = $_POST['categorys'] ?? [];
        if (!is_array($rawCats) || empty($rawCats)) {
            Response::error('Bitte wähle die passenden Kategorien für das Darsteller-Profil aus.', 400);
            return;
        }
        $cleanCats = [];
        foreach ($rawCats as $cVal) {
            $cStr = trim(strip_tags((string)$cVal));
            if ($cStr !== '' && !in_array($cStr, $cleanCats, true)) {
                $cleanCats[] = $cStr;
            }
        }
        $categoriesCsv = implode(',', $cleanCats);

        // 4. Steckbrief-Mehrfachauswahlen (Pipe-separiert wie im Legacy-System)
        $interestsList = $_POST['interests'] ?? [];
        $interestsArr  = [];
        if (is_array($interestsList)) {
            foreach ($interestsList as $val) {
                $v = trim((string)$val);
                if ($v !== '') {
                    $interestsArr[] = $v;
                }
            }
        }
        $interestsPipe = implode('|', $interestsArr);

        $lookingForList = $_POST['looking_for'] ?? [];
        $lookingForArr  = [];
        if (is_array($lookingForList)) {
            foreach ($lookingForList as $val) {
                $v = trim((string)$val);
                if ($v !== '') {
                    $lookingForArr[] = $v;
                }
            }
        }
        $lookingForPipe = implode('|', $lookingForArr);

        $sexPrefsList = $_POST['sexual_preferences'] ?? [];
        $sexPrefsArr  = [];
        if (is_array($sexPrefsList)) {
            foreach ($sexPrefsList as $val) {
                $v = trim((string)$val);
                if ($v !== '') {
                    $sexPrefsArr[] = $v;
                }
            }
        }
        $sexPrefsPipe = implode('|', $sexPrefsArr);

        // 5. Einzelne Steckbrief-Felder & Sichtbarkeits-Schalter
        $cupA    = (string)abs((int)$this->request->post('cup_size_a', 75));
        $cupB    = preg_replace('/[^A-Za-z]/', '', (string)$this->request->post('cup_size_b', 'B'));
        $cupSize = $cupA . strtoupper((string)$cupB);

        $adminInfos        = trim((string)$this->request->post('admin_infos', ''));
        $aboutMe           = trim((string)$this->request->post('about_me', ''));
        $gender            = trim((string)$this->request->post('gender', 'f'));
        if (!array_key_exists($gender, $defs['genders'])) {
            $gender = 'f';
        }
        $age               = max(18, min(99, abs((int)$this->request->post('age', 25))));
        $starSign          = trim((string)$this->request->post('star_sign', 'Steinbock'));
        $bodyHeight        = max(100, min(230, abs((int)$this->request->post('body_height', 168))));
        $bodyWeight        = max(45, min(150, abs((int)$this->request->post('body_weight', 60))));
        $eyeColor          = trim((string)$this->request->post('eye_color', 'Hellblau'));
        $hairColor         = trim((string)$this->request->post('hair_color', 'blond'));
        $shaven            = abs((int)$this->request->post('shaven', 0)) === 1 ? 1 : 0;
        $maritalStatus     = trim((string)$this->request->post('marital_status', 'ledig'));
        $sexualOrientation = trim((string)$this->request->post('sexual_orientation', 'Hetero'));

        $checkGender            = abs((int)$this->request->post('check_gender', 0)) === 1 ? 1 : 0;
        $checkAge               = abs((int)$this->request->post('check_age', 0)) === 1 ? 1 : 0;
        $checkStarSign          = abs((int)$this->request->post('check_star_sign', 0)) === 1 ? 1 : 0;
        $checkBodyHeight        = abs((int)$this->request->post('check_body_height', 0)) === 1 ? 1 : 0;
        $checkBodyWeight        = abs((int)$this->request->post('check_body_weight', 0)) === 1 ? 1 : 0;
        $checkEyeColor          = abs((int)$this->request->post('check_eye_color', 0)) === 1 ? 1 : 0;
        $checkHairColor         = abs((int)$this->request->post('check_hair_color', 0)) === 1 ? 1 : 0;
        $checkCupSize           = abs((int)$this->request->post('check_cup_size', 0)) === 1 ? 1 : 0;
        $checkShaven            = abs((int)$this->request->post('check_shaven', 0)) === 1 ? 1 : 0;
        $checkMaritalStatus     = abs((int)$this->request->post('check_marital_status', 0)) === 1 ? 1 : 0;
        $checkSexualOrientation = abs((int)$this->request->post('check_sexual_orientation', 0)) === 1 ? 1 : 0;
        $checkLookingFor        = abs((int)$this->request->post('check_looking_for', 0)) === 1 ? 1 : 0;
        $checkInterests         = abs((int)$this->request->post('check_interests', 0)) === 1 ? 1 : 0;
        $checkSexualPreferences = abs((int)$this->request->post('check_sexual_preferences', 0)) === 1 ? 1 : 0;
        $checkAboutMe           = abs((int)$this->request->post('check_about_me', 0)) === 1 ? 1 : 0;

        \p4c_query(
            "UPDATE `actors` SET
                `status`                     = '" . \p4c_escape_string($status) . "',
                `admin_infos`                = '" . \p4c_escape_string($adminInfos) . "',
                `amoredea_provision_content` = '" . $provContent . "',
                `amoredea_provision_obolus`  = '" . $provObolus . "',
                `username`                   = '" . \p4c_escape_string($postUsername) . "',
                `is_displayed_as`            = '" . \p4c_escape_string($isDisplayedAs) . "',
                `actor_categories`           = '" . \p4c_escape_string($categoriesCsv) . "',
                `obolus_type`                = '" . \p4c_escape_string($obolusType) . "',
                `check_gender`               = '" . $checkGender . "',
                `gender`                     = '" . \p4c_escape_string($gender) . "',
                `check_age`                  = '" . $checkAge . "',
                `age`                        = '" . $age . "',
                `check_star_sign`            = '" . $checkStarSign . "',
                `star_sign`                  = '" . \p4c_escape_string($starSign) . "',
                `check_body_height`          = '" . $checkBodyHeight . "',
                `body_height`                = '" . $bodyHeight . "',
                `check_eye_color`            = '" . $checkEyeColor . "',
                `eye_color`                  = '" . \p4c_escape_string($eyeColor) . "',
                `check_hair_color`           = '" . $checkHairColor . "',
                `hair_color`                 = '" . \p4c_escape_string($hairColor) . "',
                `check_body_weight`          = '" . $checkBodyWeight . "',
                `body_weight`                = '" . $bodyWeight . "',
                `check_cup_size`             = '" . $checkCupSize . "',
                `cup_size`                   = '" . \p4c_escape_string($cupSize) . "',
                `check_shaven`               = '" . $checkShaven . "',
                `shaven`                     = '" . $shaven . "',
                `check_marital_status`       = '" . $checkMaritalStatus . "',
                `marital_status`             = '" . \p4c_escape_string($maritalStatus) . "',
                `check_sexual_orientation`   = '" . $checkSexualOrientation . "',
                `sexual_orientation`         = '" . \p4c_escape_string($sexualOrientation) . "',
                `check_looking_for`          = '" . $checkLookingFor . "',
                `looking_for`                = '" . \p4c_escape_string($lookingForPipe) . "',
                `check_interests`            = '" . $checkInterests . "',
                `interests`                  = '" . \p4c_escape_string($interestsPipe) . "',
                `check_sexual_preferences`   = '" . $checkSexualPreferences . "',
                `sexual_preferences`         = '" . \p4c_escape_string($sexPrefsPipe) . "',
                `check_about_me`             = '" . $checkAboutMe . "',
                `about_me`                   = '" . \p4c_escape_string($aboutMe) . "'
             WHERE `id` = '" . $actorId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('actor_checksum')) {
            $md5Checksum = (string)\actor_checksum($actorId);
            \p4c_query(
                "UPDATE `actors` SET `md5_checksum` = '" . \p4c_escape_string($md5Checksum) . "' WHERE `id` = '" . $actorId . "' LIMIT 1;",
                __FILE__,
                __LINE__
            );
        }

        \p4c_query(
            "UPDATE `group_actors` SET `is_displayed_as` = '" . \p4c_escape_string($isDisplayedAs) . "' WHERE `actor_id` = '" . $actorId . "';",
            __FILE__,
            __LINE__
        );

        // Wenn das Profil auf 'deleted' gesetzt wird, auch zugehörige Filme & Fotoalben als 'deleted' markieren
        if ($status === 'deleted') {
            $now = date('Y-m-d H:i:s');
            \p4c_query("UPDATE `movies` SET `status` = 'deleted', `deleted_datetime` = '{$now}' WHERE `actor_id` = '" . $actorId . "';", __FILE__, __LINE__);
            \p4c_query("UPDATE `movies_online` SET `status` = 'deleted', `deleted_datetime` = '{$now}' WHERE `actor_id` = '" . $actorId . "';", __FILE__, __LINE__);
            \p4c_query("UPDATE `photo_albums` SET `status` = 'deleted', `deleted_datetime` = '{$now}' WHERE `actor_id` = '" . $actorId . "';", __FILE__, __LINE__);
            \p4c_query("UPDATE `photo_albums_online` SET `status` = 'deleted', `deleted_datetime` = '{$now}' WHERE `actor_id` = '" . $actorId . "';", __FILE__, __LINE__);
        }

        if (function_exists('log_action')) {
            \log_action("Darsteller-Profil #{$actorId} ({$postUsername}) im ACP aktualisiert (Status: {$status}).");
        }

        Response::json([
            'status'   => 'ok',
            'actor_id' => $actorId,
            'username' => $postUsername,
            'message'  => "Das Darsteller-Profil „{$postUsername}“ (#{$actorId}) wurde erfolgreich gespeichert.",
        ]);
    }

    /**
     * POST ?route=actors/delete-image
     * Löscht das FSK16- oder FSK18-Profilbild eines Darsteller-Profils.
     */
    public function deleteImage(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheits-Token (CSRF) ist abgelaufen.', 403);
            return;
        }

        $actorId = abs((int)$this->request->post('actor_id', 0));
        $fsk     = strtoupper(trim((string)$this->request->post('fsk', '')));

        if ($actorId <= 0 || !in_array($fsk, ['FSK16', 'FSK18'], true)) {
            Response::error('Ungültige Parameter zum Löschen des Profilbildes.', 400);
            return;
        }

        $rsActor = \p4c_query(
            "SELECT `id`, `merchant_id`, `username`, `profile_image_fsk16`, `profile_image_fsk18`
             FROM `actors`
             WHERE `id` = '" . $actorId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsActor || \p4c_num_rows($rsActor) === 0) {
            Response::error('Darsteller-Profil nicht gefunden.', 404);
            return;
        }

        $actObj  = \p4c_fetch_object($rsActor);
        $colName = $fsk === 'FSK16' ? 'profile_image_fsk16' : 'profile_image_fsk18';
        $imgFile = trim((string)($actObj->$colName ?? ''));

        if ($imgFile !== '') {
            $filePath = rtrim((string)PROFILE_IMAGE_PATH, '/\\') . '/'
                . (string)MERCHANT_DEFAULT_DIR . '/'
                . abs((int)$actObj->merchant_id) . '/'
                . $actorId . '/'
                . basename($imgFile);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        \p4c_query(
            "UPDATE `actors` SET `{$colName}` = '' WHERE `id` = '" . $actorId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('actor_checksum')) {
            $md5Checksum = (string)\actor_checksum($actorId);
            \p4c_query(
                "UPDATE `actors` SET `md5_checksum` = '" . \p4c_escape_string($md5Checksum) . "' WHERE `id` = '" . $actorId . "' LIMIT 1;",
                __FILE__,
                __LINE__
            );
        }

        if (function_exists('log_action')) {
            \log_action("{$fsk}-Profilbild von Darsteller #{$actorId} ({$actObj->username}) gelöscht.");
        }

        Response::json([
            'status'  => 'ok',
            'message' => "Das {$fsk}-Profilbild wurde gelöscht.",
        ]);
    }

    /**
     * POST ?route=actors/reassign-merchant
     * Ordnet ein Darsteller-Profil über die Tabelle actor_reassign einem neuen Merchant zu.
     */
    public function reassignMerchant(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheits-Token (CSRF) ist abgelaufen.', 403);
            return;
        }

        $actorId       = abs((int)$this->request->post('actor_id', 0));
        $newMerchantId = abs((int)$this->request->post('new_merchant_id', 0));

        if ($actorId <= 0 || $newMerchantId <= 0) {
            Response::error('Bitte wähle einen gültigen neuen Partner (Merchant) aus.', 400);
            return;
        }

        $rsActor = \p4c_query("SELECT `id`, `merchant_id`, `username` FROM `actors` WHERE `id` = '" . $actorId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsActor || \p4c_num_rows($rsActor) === 0) {
            Response::error('Darsteller-Profil nicht gefunden.', 404);
            return;
        }
        $actObj        = \p4c_fetch_object($rsActor);
        $oldMerchantId = abs((int)$actObj->merchant_id);

        if ($oldMerchantId === $newMerchantId) {
            Response::error('Dieses Darsteller-Profil ist bereits diesem Partner zugeordnet.', 400);
            return;
        }

        $rsNewMerch = \p4c_query("SELECT `id` FROM `merchants` WHERE `id` = '" . $newMerchantId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsNewMerch || \p4c_num_rows($rsNewMerch) === 0) {
            Response::error('Der ausgewählte Ziel-Merchant existiert nicht.', 404);
            return;
        }

        $rsPending = \p4c_query(
            "SELECT `id` FROM `actor_reassign`
             WHERE `actor_id` = '" . $actorId . "'
               AND `files_mapping_completed` = '0000-00-00 00:00:00'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsPending && \p4c_num_rows($rsPending) > 0) {
            Response::error('Für dieses Darsteller-Profil läuft bereits eine aktive Besitzer-Umschreibung.', 400);
            return;
        }

        \p4c_query(
            "INSERT INTO `actor_reassign` SET
                `actor_id`        = '" . $actorId . "',
                `old_merchant_id` = '" . $oldMerchantId . "',
                `new_merchant_id` = '" . $newMerchantId . "',
                `order_from`      = '" . date('Y-m-d H:i:s') . "';",
            __FILE__,
            __LINE__
        );

        if (function_exists('log_action')) {
            \log_action("Besitzerwechsel für Darsteller #{$actorId} ({$actObj->username}) von MID #{$oldMerchantId} zu MID #{$newMerchantId} beauftragt.");
        }

        Response::json([
            'status'  => 'ok',
            'message' => "Der Besitzerwechsel für „{$actObj->username}“ an Merchant #{$newMerchantId} wurde eingereiht (Cronjob übernimmt die Datei-Verschiebung automatisch).",
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function parsePipeOrCommaList(string $raw): array
    {
        $decoded = $this->cleanUtf8(trim($raw));
        if ($decoded === '') {
            return [];
        }
        if (str_contains($decoded, '|')) {
            $parts = explode('|', $decoded);
        } else {
            $parts = explode(',', str_replace(', ', ',', $decoded));
        }
        return array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
    }

    private function cleanUtf8(string $val): string
    {
        if ($val === '') {
            return '';
        }
        if (!mb_check_encoding($val, 'UTF-8')) {
            $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }
        return html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
