<?php
declare(strict_types=1);

namespace App\Modules\CoActorsChecking;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\MoviesOnline\MoviesOnlineController;

/**
 * EroCloud ACP (v2.0) - CoActorsCheckingController
 * Steuert das Prüf- & Verwaltungscockpit für Drehpartner & 18+ ID-Checks (#/co-actors-checking).
 * Umfasst Model-Release-Zertifikate, 4-fach Dokumenten-Inspektor (Selfie, ID-Shot, Ausweis V/R),
 * Ausweisdaten-Korrektur, QR-Code-Generator und Content-Verknüpfungen (Filme & Fotoalben).
 */
final class CoActorsCheckingController
{
    private Request $request;

    /** @var array<string, string> */
    public const DOC_TYPE_LABELS = [
        'id_card'         => 'Personalausweis',
        'passport'        => 'Reisepass',
        'drivers_license' => 'Führerschein',
    ];

    /** @var array<int, array{title: string, text: string}> */
    public const REJECTION_TEMPLATES = [
        [
            'title' => 'Ausweis auf ID-Shot unscharf / unlesbar',
            'text'  => 'Der Personalausweis ist auf dem ID-Shot leider nicht scharf genug lesbar. Bitte nimm den ID-Shot per Smartphone-QR-Code bei guter Beleuchtung erneut auf, sodass Name, Geburtsdatum und Ausweisbild klar erkennbar sind.',
        ],
        [
            'title' => 'Gesicht auf ID-Shot verdeckt oder angeschnitten',
            'text'  => 'Auf dem ID-Shot müssen sowohl dein vollständiges Gesicht als auch die Vorderseite deines Ausweises gleichzeitig und unverdeckt neben deinem Kopf sichtbar sein.',
        ],
        [
            'title' => 'Ausweisdokument ist abgelaufen',
            'text'  => 'Das eingereichte Ausweisdokument hat das Gültigkeitsdatum überschritten. Bitte führe den ID-Check mit einem gültigen amtlichen Lichtbildausweis (Personalausweis, Reisepass oder Führerschein) erneut durch.',
        ],
        [
            'title' => 'Vor-/Nachname oder Geburtsdatum weicht vom Ausweis ab',
            'text'  => 'Die eingegebenen Personendaten (Vorname, Nachname oder Geburtsdatum) stimmen nicht exakt mit den Angaben auf dem vorgelegten Ausweisdokument überein.',
        ],
        [
            'title' => 'Ausweis-Vorderseite oder Rückseite fehlt / unvollständig',
            'text'  => 'Die Detailaufnahme des Ausweisdokuments ist unvollständig oder unscharf. Bitte fotografiere den Ausweis nochmals vollständig und gut ausgeleuchtet ab.',
        ],
        [
            'title' => 'Unterschrift auf Model-Release unvollständig',
            'text'  => 'Die digitale Unterschrift auf der Einverständniserklärung fehlt oder besteht nur aus einem Punkt. Bitte unterschreibe die Freigabe per Smartphone-QR-Code vollständig mit deinem Namen.',
        ],
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 1. Hauptübersicht & Prüfcockpit für Drehpartner & ID-Check (#/co-actors-checking)
     */
    public function list(): string
    {
        if (!class_exists('CoActorManager') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/CoActorManager.inc.php')) {
            require_once SOURCEDIR . '/includes/klassen/CoActorManager.inc.php';
        }
        \CoActorManager::ensureSchema();

        // Filter- & Sortier-Parameter
        $statusFilter = trim((string)$this->request->get('status', 'under_review'));
        if (!in_array($statusFilter, ['under_review', 'verified', 'rejected', 'pending_qr', 'all'], true)) {
            $statusFilter = 'under_review';
        }

        $roleFilter = trim((string)$this->request->get('role', 'all'));
        if (!in_array($roleFilter, ['all', 'owner', 'co_actor'], true)) {
            $roleFilter = 'all';
        }

        $methodFilter = trim((string)$this->request->get('method', 'all'));
        if (!in_array($methodFilter, ['all', 'qr_live', 'manual_legacy'], true)) {
            $methodFilter = 'all';
        }

        $sortFilter  = trim((string)$this->request->get('sort', 'priority'));
        $searchQuery = trim((string)$this->request->get('q', ''));

        // 1. Globale KPI-Zähler über alle aktiven co_actors ermitteln
        $counts = [
            'under_review' => 0,
            'verified'     => 0,
            'rejected'     => 0,
            'pending_qr'   => 0,
            'all'          => 0,
            'owners'       => 0,
            'co_actors'    => 0,
            'ai_ready'     => 0,
            'legacy'       => 0,
        ];

        $rsKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_cnt`,
                SUM(CASE WHEN `checked_status` = 'under_review' THEN 1 ELSE 0 END) AS `review_cnt`,
                SUM(CASE WHEN `checked_status` = 'verified' THEN 1 ELSE 0 END) AS `verified_cnt`,
                SUM(CASE WHEN `checked_status` = 'rejected' THEN 1 ELSE 0 END) AS `rejected_cnt`,
                SUM(CASE WHEN `checked_status` NOT IN ('under_review', 'verified', 'rejected') THEN 1 ELSE 0 END) AS `pending_cnt`,
                SUM(CASE WHEN `is_owner` = 1 THEN 1 ELSE 0 END) AS `owners_cnt`,
                SUM(CASE WHEN `is_owner` = 0 THEN 1 ELSE 0 END) AS `coactors_cnt`,
                SUM(CASE WHEN `ai_face_ready` = 1 OR (`verification_method` = 'qr_live' AND `face_ref_img` != '') THEN 1 ELSE 0 END) AS `ai_cnt`,
                SUM(CASE WHEN `verification_method` = 'manual_legacy' THEN 1 ELSE 0 END) AS `legacy_cnt`
             FROM `co_actors`
             WHERE `status` != 'deleted';",
            __FILE__,
            __LINE__
        );
        if ($rsKpi && p4c_num_rows($rsKpi) > 0) {
            $kRow = p4c_fetch_object($rsKpi);
            $counts['all']          = abs((int)($kRow->total_cnt ?? 0));
            $counts['under_review'] = abs((int)($kRow->review_cnt ?? 0));
            $counts['verified']     = abs((int)($kRow->verified_cnt ?? 0));
            $counts['rejected']     = abs((int)($kRow->rejected_cnt ?? 0));
            $counts['pending_qr']   = abs((int)($kRow->pending_cnt ?? 0));
            $counts['owners']       = abs((int)($kRow->owners_cnt ?? 0));
            $counts['co_actors']    = abs((int)($kRow->coactors_cnt ?? 0));
            $counts['ai_ready']     = abs((int)($kRow->ai_cnt ?? 0));
            $counts['legacy']       = abs((int)($kRow->legacy_cnt ?? 0));
        }

        // Mitarbeiter-Namen für Audit-Anzeige („Geprüft von ...“)
        $employeeNames = [];
        $rsEmps = p4c_query("SELECT `id`, `username` FROM `employee`;", __FILE__, __LINE__);
        if ($rsEmps) {
            while ($eObj = p4c_fetch_object($rsEmps)) {
                $employeeNames[abs((int)$eObj->id)] = \CoActorManager::cleanUtf8((string)($eObj->username ?? ''));
            }
        }

        // 2. SQL-Where für Filterung aufbauen
        $whereParts = ["c.`status` != 'deleted'"];
        if ($statusFilter !== 'all') {
            $whereParts[] = "c.`checked_status` = '" . p4c_escape_string($statusFilter) . "'";
        }
        if ($roleFilter === 'owner') {
            $whereParts[] = "c.`is_owner` = 1";
        } elseif ($roleFilter === 'co_actor') {
            $whereParts[] = "c.`is_owner` = 0";
        }
        if ($methodFilter === 'qr_live') {
            $whereParts[] = "(c.`ai_face_ready` = 1 OR c.`verification_method` = 'qr_live')";
        } elseif ($methodFilter === 'manual_legacy') {
            $whereParts[] = "c.`verification_method` = 'manual_legacy'";
        }

        if ($searchQuery !== '') {
            $escQ = p4c_escape_string($searchQuery);
            $numQ = ctype_digit($searchQuery) ? abs((int)$searchQuery) : 0;
            $searchConds = [
                "c.`creator_name` LIKE '%" . $escQ . "%'",
                "CAST(AES_DECRYPT(c.`nickname_not_public`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
                "CAST(AES_DECRYPT(c.`first_name`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
                "CAST(AES_DECRYPT(c.`last_name`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
                "CAST(AES_DECRYPT(c.`id_doc_number`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
                "CAST(AES_DECRYPT(c.`city`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
                "m.`partner_id` LIKE '%" . $escQ . "%'",
                "CAST(AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS CHAR) LIKE '%" . $escQ . "%'",
            ];
            if ($numQ > 0) {
                $searchConds[] = "c.`id` = '" . $numQ . "'";
                $searchConds[] = "c.`merchant_id` = '" . $numQ . "'";
            }
            $whereParts[] = '(' . implode(' OR ', $searchConds) . ')';
        }

        $whereSql = implode(' AND ', $whereParts);

        // Sortierung
        $orderBySql = "CASE c.`checked_status`
                WHEN 'under_review' THEN 1
                WHEN 'rejected' THEN 2
                WHEN 'verified' THEN 3
                ELSE 4
            END ASC,
            c.`submitted_datetime` DESC,
            c.`id` DESC";

        if ($sortFilter === 'id_desc') {
            $orderBySql = "c.`id` DESC";
        } elseif ($sortFilter === 'submitted_desc') {
            $orderBySql = "c.`submitted_datetime` DESC, c.`id` DESC";
        } elseif ($sortFilter === 'checked_desc') {
            $orderBySql = "c.`checked_on` DESC, c.`id` DESC";
        } elseif ($sortFilter === 'name_asc') {
            $orderBySql = "c.`creator_name` ASC, c.`id` DESC";
        }

        $sqlList = "SELECT
                c.`id`, c.`merchant_id`, c.`is_owner`, c.`creator_name`,
                AES_DECRYPT(c.`nickname_not_public`, '" . AES_KEY . "') AS `dec_nickname`,
                AES_DECRYPT(c.`first_name`, '" . AES_KEY . "') AS `dec_first_name`,
                AES_DECRYPT(c.`last_name`, '" . AES_KEY . "') AS `dec_last_name`,
                AES_DECRYPT(c.`birth_date`, '" . AES_KEY . "') AS `dec_birth_date`,
                AES_DECRYPT(c.`street`, '" . AES_KEY . "') AS `dec_street`,
                AES_DECRYPT(c.`zip_code`, '" . AES_KEY . "') AS `dec_zip_code`,
                AES_DECRYPT(c.`city`, '" . AES_KEY . "') AS `dec_city`,
                c.`country`, c.`id_doc_type`,
                AES_DECRYPT(c.`id_doc_number`, '" . AES_KEY . "') AS `dec_id_doc_number`,
                c.`id_doc_valid_until`, c.`verification_method`,
                c.`qr_token`, c.`qr_token_expires_at`,
                c.`face_ref_img`, c.`id_shot_img1`, c.`id_front_img`, c.`id_back_img`,
                c.`consent_doc_file`, c.`signature_data_json`,
                c.`ai_face_ready`, c.`checked_status`, c.`rejection_reason`,
                c.`create_datetime`, c.`submitted_datetime`, c.`checked_on`, c.`checked_by_employee`, c.`status`,
                m.`partner_id` AS `merchant_partner_id`,
                AES_DECRYPT(m.`username`, '" . AES_KEY . "') AS `merchant_username`,
                AES_DECRYPT(m.`firstname`, '" . AES_KEY . "') AS `merchant_firstname`,
                AES_DECRYPT(m.`surname`, '" . AES_KEY . "') AS `merchant_surname`
            FROM `co_actors` c
            LEFT JOIN `merchants` m ON m.`id` = c.`merchant_id`
            WHERE " . $whereSql . "
            ORDER BY " . $orderBySql . "
            LIMIT 250;";

        $rsList     = p4c_query($sqlList, __FILE__, __LINE__);
        $rows       = [];
        $coActorIds = [];

        if ($rsList) {
            while ($obj = p4c_fetch_object($rsList)) {
                $formatted = \CoActorManager::formatCoActorRow($obj);
                $caId      = (int)$formatted['id'];
                $vHash     = substr(md5((string)($obj->submitted_datetime ?? '') . (string)($obj->id_shot_img1 ?? '') . (string)($obj->face_ref_img ?? '')), 0, 8);

                // Lokale ACP-Medien-Proxy-URLs (media.php?type=co_actor) für verzögerungsfreie Darstellung ohne Cross-Domain-Hürden
                $formatted['face_ref_url']    = $formatted['face_ref_img'] !== '' ? ('media.php?type=co_actor&id=' . $caId . '&sub=face_ref&v=' . $vHash) : '';
                $formatted['id_shot_url']     = $formatted['id_shot_img1'] !== '' ? ('media.php?type=co_actor&id=' . $caId . '&sub=id_shot&v=' . $vHash) : '';
                $formatted['id_front_url']    = $formatted['id_front_img'] !== '' ? ('media.php?type=co_actor&id=' . $caId . '&sub=id_front&v=' . $vHash) : '';
                $formatted['id_back_url']     = $formatted['id_back_img'] !== '' ? ('media.php?type=co_actor&id=' . $caId . '&sub=id_back&v=' . $vHash) : '';
                $formatted['certificate_url'] = !empty($formatted['is_complete']) ? ('media.php?type=co_actor&id=' . $caId . '&sub=certificate&v=' . $vHash) : '';
                $formatted['consent_raw_url'] = $formatted['consent_doc_file'] !== '' ? ('media.php?type=co_actor&id=' . $caId . '&sub=consent_raw&v=' . $vHash) : '';

                $formatted['merchant_partner_id'] = \CoActorManager::cleanUtf8((string)($obj->merchant_partner_id ?? ''));
                $formatted['merchant_username']   = \CoActorManager::cleanUtf8((string)($obj->merchant_username ?? ''));
                $formatted['merchant_real_name']  = trim(
                    \CoActorManager::cleanUtf8((string)($obj->merchant_firstname ?? '')) . ' ' .
                    \CoActorManager::cleanUtf8((string)($obj->merchant_surname ?? ''))
                );

                // Ausweis-Ablaufprüfung
                $validRaw   = trim((string)$formatted['id_doc_valid_until']);
                $isExpired  = false;
                $validLabel = $validRaw !== '' ? $validRaw : 'Unbegrenzt / k.A.';
                if ($validRaw !== '' && !str_starts_with($validRaw, '0000')) {
                    $vTs = strtotime($validRaw);
                    if ($vTs !== false) {
                        $validLabel = date('d.m.Y', $vTs);
                        if ($vTs < strtotime('today')) {
                            $isExpired = true;
                        }
                    }
                }
                $formatted['id_doc_valid_label'] = $validLabel;
                $formatted['is_doc_expired']     = $isExpired;
                $formatted['id_doc_type_label']  = self::DOC_TYPE_LABELS[(string)$formatted['id_doc_type']] ?? 'Lichtbildausweis';

                $empIdChecked = (int)($formatted['checked_by_employee'] ?? 0);
                $formatted['checked_by_name'] = $empIdChecked > 0
                    ? ($employeeNames[$empIdChecked] ?? ('Mitarbeiter #' . $empIdChecked))
                    : '';

                $rows[]       = $formatted;
                $coActorIds[] = $caId;
            }
        }

        // 3. Verknüpfte Filme & Fotoalben zu diesen Darstellern nachladen
        $linkedContentByActor = [];
        if (!empty($coActorIds)) {
            $idListSql = implode(',', array_map('intval', $coActorIds));

            // 3a. Filme (content_type = 'movie')
            $rsMovieLinks = p4c_query(
                "SELECT
                    cc.`co_actor_id`,
                    cc.`content_id` AS `item_id`,
                    mv.`title`,
                    mv.`status`,
                    mv.`released`,
                    mv.`movie_checked` AS `checked_dt`
                 FROM `content_co_actors` cc
                 INNER JOIN `movies` mv ON mv.`id` = cc.`content_id`
                 WHERE cc.`content_type` = 'movie'
                   AND cc.`co_actor_id` IN (" . $idListSql . ")
                   AND mv.`status` != 'deleted'
                 ORDER BY mv.`id` DESC;",
                __FILE__,
                __LINE__
            );
            if ($rsMovieLinks) {
                while ($lnk = p4c_fetch_object($rsMovieLinks)) {
                    $caId        = abs((int)$lnk->co_actor_id);
                    $mvId        = abs((int)$lnk->item_id);
                    $mvTitle     = \CoActorManager::cleanUtf8((string)($lnk->title ?? ''));
                    $mvStatus    = (string)($lnk->status ?? '');
                    $mvReleased  = abs((int)($lnk->released ?? 0));
                    $mvChecked   = (string)($lnk->checked_dt ?? '');
                    $isUnchecked = (($mvChecked === '' || str_starts_with($mvChecked, '0000') || str_starts_with($mvChecked, '000-')) && $mvReleased === 1);

                    $route = '#/movie-edit?id=' . $mvId;
                    $badge = 'Online';
                    $bCls  = 'badge-kkc-success';
                    if ($isUnchecked) {
                        $route = '#/movie-checking?id=' . $mvId;
                        $badge = 'In Prüfung';
                        $bCls  = 'badge-kkc-warning';
                    } elseif ($mvReleased === 2 || $mvStatus === 'blocked') {
                        $route = '#/movies-blocked';
                        $badge = $mvReleased === 2 ? 'Abgelehnt' : 'Gesperrt';
                        $bCls  = 'badge-kkc-danger';
                    } elseif ($mvReleased === 0) {
                        $route = '#/movies-drafts';
                        $badge = 'Entwurf';
                        $bCls  = 'badge-kkc-neutral';
                    }

                    $linkedContentByActor[$caId][] = [
                        'type'       => 'movie',
                        'type_icon'  => 'bi-film',
                        'type_label' => 'Film',
                        'id'         => $mvId,
                        'title'      => $mvTitle !== '' ? $mvTitle : ('Film #' . $mvId),
                        'route'      => $route,
                        'badge'      => $badge,
                        'badge_cls'  => $bCls,
                    ];
                }
            }

            // 3b. Fotoalben (content_type = 'photo_album')
            $rsAlbumLinks = p4c_query(
                "SELECT
                    cc.`co_actor_id`,
                    cc.`content_id` AS `item_id`,
                    pa.`title`,
                    pa.`status`,
                    pa.`released`,
                    pa.`album_checked` AS `checked_dt`
                 FROM `content_co_actors` cc
                 INNER JOIN `photo_albums` pa ON pa.`id` = cc.`content_id`
                 WHERE cc.`content_type` = 'photo_album'
                   AND cc.`co_actor_id` IN (" . $idListSql . ")
                   AND pa.`status` != 'deleted'
                 ORDER BY pa.`id` DESC;",
                __FILE__,
                __LINE__
            );
            if ($rsAlbumLinks) {
                while ($lnk = p4c_fetch_object($rsAlbumLinks)) {
                    $caId        = abs((int)$lnk->co_actor_id);
                    $paId        = abs((int)$lnk->item_id);
                    $paTitle     = \CoActorManager::cleanUtf8((string)($lnk->title ?? ''));
                    $paStatus    = (string)($lnk->status ?? '');
                    $paReleased  = abs((int)($lnk->released ?? 0));
                    $paChecked   = (string)($lnk->checked_dt ?? '');
                    $isUnchecked = (($paChecked === '' || str_starts_with($paChecked, '0000') || str_starts_with($paChecked, '000-')) && $paReleased === 1);

                    $route = '#/photo-album-edit?id=' . $paId;
                    $badge = 'Online';
                    $bCls  = 'badge-kkc-success';
                    if ($isUnchecked) {
                        $route = '#/photo-album-checking?id=' . $paId;
                        $badge = 'In Prüfung';
                        $bCls  = 'badge-kkc-warning';
                    } elseif ($paReleased === 2 || $paStatus === 'blocked') {
                        $badge = $paReleased === 2 ? 'Abgelehnt' : 'Gesperrt';
                        $bCls  = 'badge-kkc-danger';
                    } elseif ($paReleased === 0) {
                        $badge = 'Entwurf';
                        $bCls  = 'badge-kkc-neutral';
                    }

                    $linkedContentByActor[$caId][] = [
                        'type'       => 'photo_album',
                        'type_icon'  => 'bi-journal-album',
                        'type_label' => 'Album',
                        'id'         => $paId,
                        'title'      => $paTitle !== '' ? $paTitle : ('Album #' . $paId),
                        'route'      => $route,
                        'badge'      => $badge,
                        'badge_cls'  => $bCls,
                    ];
                }
            }
        }

        $switcherCounts = MoviesOnlineController::getContentSwitcherCounts();
        $switcherCounts['co_actors_checking'] = $counts['under_review'];

        return View::render('CoActorsChecking', 'list', [
            'actors'               => $rows,
            'counts'               => $counts,
            'linkedContentByActor' => $linkedContentByActor,
            'switcherCounts'       => $switcherCounts,
            'docTypeLabels'        => self::DOC_TYPE_LABELS,
            'rejectionTemplates'   => self::REJECTION_TEMPLATES,
            'filters'              => [
                'status' => $statusFilter,
                'role'   => $roleFilter,
                'method' => $methodFilter,
                'sort'   => $sortFilter,
                'q'      => $searchQuery,
            ],
        ]);
    }

    /**
     * 2. AJAX-Aktion: Drehpartner bestätigen, ablehnen, zurücksetzen oder QR-Code erneuern (?route=co-actors-checking/action)
     */
    public function action(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        if (!class_exists('CoActorManager') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/CoActorManager.inc.php')) {
            require_once SOURCEDIR . '/includes/klassen/CoActorManager.inc.php';
        }
        \CoActorManager::ensureSchema();

        $coActorId = abs((int)$this->request->post('co_actor_id', 0));
        $actType   = trim((string)$this->request->post('action_type', ''));
        $empId     = abs((int)($_SESSION['employee_id'] ?? 0));
        $empName   = (string)($_SESSION['employee_username'] ?? ('Mitarbeiter #' . $empId));

        if ($coActorId <= 0 || !in_array($actType, ['verify', 'reject', 'reset_review', 'regenerate_qr', 'get_qr'], true)) {
            Response::error('Ungültige Parameter für die Drehpartner-Aktion.', 400);
            return;
        }

        $ca = \CoActorManager::getCoActorById($coActorId);
        if ($ca === null) {
            Response::error('Der angeforderte Drehpartner-Eintrag wurde nicht gefunden.', 404);
            return;
        }

        $merchantId   = (int)$ca['merchant_id'];
        $displayLabel = (string)$ca['display_label'];
        $now          = date('Y-m-d H:i:s');

        // Fall A: QR-Code abrufen oder neu generieren
        if ($actType === 'get_qr' || $actType === 'regenerate_qr') {
            $token = (string)$ca['qr_token'];
            if ($actType === 'regenerate_qr' || $token === '') {
                $token = \CoActorManager::regenerateQrToken($coActorId, $merchantId);
                if (function_exists('log_action')) {
                    log_action('hat den QR-Verifizierungs-Link für Drehpartner #' . $coActorId . ' (' . $displayLabel . ', Merchant #' . $merchantId . ') erneuert');
                }
            }
            $verifyUrl = \CoActorManager::getPublicVerifyUrl($token);
            $qrSvg     = \CoActorManager::generateQrSvg($verifyUrl, 240);

            Response::success([
                'co_actor_id'   => $coActorId,
                'display_label' => $displayLabel,
                'ref_code'      => (string)$ca['ref_code'],
                'qr_token'      => $token,
                'qr_verify_url' => $verifyUrl,
                'qr_svg'        => $qrSvg,
                'message'       => $actType === 'regenerate_qr'
                    ? 'Neuer Smartphone-QR-Link für „' . $displayLabel . '“ wurde generiert.'
                    : 'Smartphone-QR-Code geladen.',
            ]);
            return;
        }

        // Fall B: Bestätigen (verify)
        if ($actType === 'verify') {
            p4c_query(
                "UPDATE `co_actors` SET
                    `checked_status`      = 'verified',
                    `checked_by_employee` = '" . $empId . "',
                    `checked_on`          = '" . $now . "',
                    `rejection_reason`    = ''
                 WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if (function_exists('log_action')) {
                log_action('hat den 18+ ID-Check / Drehpartner #' . $coActorId . ' (' . $displayLabel . ', Merchant #' . $merchantId . ') als geprüft bestätigt');
            }

            Response::success([
                'co_actor_id'    => $coActorId,
                'checked_status' => 'verified',
                'status_label'   => 'Geprüft & Freigegeben',
                'checked_by'     => $empName,
                'checked_on_de'  => date('d.m.Y H:i'),
                'message'        => 'ID-Shot & Freigabe für „' . $displayLabel . '“ wurden als geprüft bestätigt.',
            ]);
            return;
        }

        // Fall C: Ablehnen (reject)
        if ($actType === 'reject') {
            $reason = trim(strip_tags((string)$this->request->post('rejection_reason', '')));
            if ($reason === '') {
                $reason = 'Bitte nimm einen neuen, scharfen ID-Shot per Smartphone-QR-Code auf (Ausweis muss gut lesbar neben dem Gesicht gehalten werden).';
            }

            p4c_query(
                "UPDATE `co_actors` SET
                    `checked_status`      = 'rejected',
                    `checked_by_employee` = '" . $empId . "',
                    `checked_on`          = '" . $now . "',
                    `rejection_reason`    = '" . p4c_escape_string($reason) . "'
                 WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if (function_exists('log_action')) {
                log_action('hat den 18+ ID-Check / Drehpartner #' . $coActorId . ' (' . $displayLabel . ', Merchant #' . $merchantId . ') abgelehnt: ' . $reason);
            }

            Response::success([
                'co_actor_id'      => $coActorId,
                'checked_status'   => 'rejected',
                'status_label'     => 'Abgelehnt – Korrektur nötig',
                'rejection_reason' => $reason,
                'checked_by'       => $empName,
                'checked_on_de'    => date('d.m.Y H:i'),
                'message'          => 'ID-Check für „' . $displayLabel . '“ wurde abgelehnt.',
            ]);
            return;
        }

        // Fall D: Status zurück auf „Offen“ setzen (reset_review)
        if ($actType === 'reset_review') {
            p4c_query(
                "UPDATE `co_actors` SET
                    `checked_status`   = 'under_review',
                    `rejection_reason` = ''
                 WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if (function_exists('log_action')) {
                log_action('hat den Status von Drehpartner #' . $coActorId . ' (' . $displayLabel . ', Merchant #' . $merchantId . ') wieder auf „Zur Prüfung offen“ zurückgesetzt');
            }

            Response::success([
                'co_actor_id'    => $coActorId,
                'checked_status' => 'under_review',
                'status_label'   => 'Eingereicht (In Prüfung)',
                'message'        => 'Status von „' . $displayLabel . '“ wurde wieder auf „Zur Prüfung offen“ gesetzt.',
            ]);
            return;
        }
    }

    /**
     * 3. AJAX-Aktion: Entschlüsselte Ausweis- & Personendaten direkt im ACP korrigieren (?route=co-actors-checking/update-data)
     */
    public function updateData(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        if (!class_exists('CoActorManager') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/CoActorManager.inc.php')) {
            require_once SOURCEDIR . '/includes/klassen/CoActorManager.inc.php';
        }
        \CoActorManager::ensureSchema();

        $coActorId = abs((int)$this->request->post('co_actor_id', 0));
        if ($coActorId <= 0) {
            Response::error('Ungültige Drehpartner-ID.', 400);
            return;
        }

        $ca = \CoActorManager::getCoActorById($coActorId);
        if ($ca === null) {
            Response::error('Der Drehpartner-Eintrag wurde nicht gefunden.', 404);
            return;
        }

        $merchantId  = (int)$ca['merchant_id'];
        $creatorName = trim(strip_tags((string)$this->request->post('creator_name', '')));
        $firstName   = trim(strip_tags((string)$this->request->post('first_name', '')));
        $lastName    = trim(strip_tags((string)$this->request->post('last_name', '')));
        $birthDate   = \CoActorManager::normalizeDate(trim((string)$this->request->post('birth_date', '')));
        $docType     = trim((string)$this->request->post('id_doc_type', 'id_card'));
        if (!isset(self::DOC_TYPE_LABELS[$docType])) {
            $docType = 'id_card';
        }
        $docNumber   = trim(strip_tags((string)$this->request->post('id_doc_number', '')));
        $docValid    = \CoActorManager::normalizeDate(trim((string)$this->request->post('id_doc_valid_until', '')));
        $street      = trim(strip_tags((string)$this->request->post('street', '')));
        $zipCode     = trim(strip_tags((string)$this->request->post('zip_code', '')));
        $city        = trim(strip_tags((string)$this->request->post('city', '')));
        $country     = strtoupper(substr(trim(strip_tags((string)$this->request->post('country', 'DE'))), 0, 5)) ?: 'DE';

        if ($firstName === '' && $lastName === '') {
            Response::error('Bitte gib mindestens den Vornamen oder Nachnamen der Person an.', 422);
            return;
        }

        p4c_query(
            "UPDATE `co_actors` SET
                `creator_name`        = '" . p4c_escape_string($creatorName) . "',
                `nickname_not_public` = AES_ENCRYPT('" . p4c_escape_string($creatorName) . "', '" . AES_KEY . "'),
                `first_name`          = AES_ENCRYPT('" . p4c_escape_string($firstName) . "', '" . AES_KEY . "'),
                `last_name`           = AES_ENCRYPT('" . p4c_escape_string($lastName) . "', '" . AES_KEY . "'),
                `birth_date`          = AES_ENCRYPT('" . p4c_escape_string($birthDate) . "', '" . AES_KEY . "'),
                `street`              = AES_ENCRYPT('" . p4c_escape_string($street) . "', '" . AES_KEY . "'),
                `zip_code`            = AES_ENCRYPT('" . p4c_escape_string($zipCode) . "', '" . AES_KEY . "'),
                `city`                = AES_ENCRYPT('" . p4c_escape_string($city) . "', '" . AES_KEY . "'),
                `country`             = '" . p4c_escape_string($country) . "',
                `id_doc_type`         = '" . p4c_escape_string($docType) . "',
                `id_doc_number`       = AES_ENCRYPT('" . p4c_escape_string($docNumber) . "', '" . AES_KEY . "'),
                `id_doc_valid_until`  = '" . p4c_escape_string($docValid) . "'
             WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (function_exists('log_action')) {
            log_action('hat die Ausweis-/Personendaten von Drehpartner #' . $coActorId . ' (' . $firstName . ' ' . $lastName . ', Merchant #' . $merchantId . ') aktualisiert');
        }

        Response::success([
            'co_actor_id' => $coActorId,
            'message'     => 'Ausweis- und Personendaten für #' . $coActorId . ' wurden erfolgreich gespeichert.',
        ]);
    }
}
