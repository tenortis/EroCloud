<?php
declare(strict_types=1);

namespace App\Modules\PhotoAlbumsChecking;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) - PhotoAlbumsCheckingController
 * Steuert die Fotoalben-Prüf-Warteschlange (#/photo-albums-checking) sowie die Einzelprüfung & Freigabe (#/photo-album-checking?id=...).
 */
final class PhotoAlbumsCheckingController
{
    private Request $request;

    /** @var array<int, string> */
    private const REPLACE_TITLE_CHARS = [
        '°', '^', '²', '§', '$', '%', '{', '[', ']', '}', '´', '`', '~', "'", '_', ';', '<', '>',
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 1. Warteschlangen-Übersicht aller zu prüfenden Fotoalben (#/photo-albums-checking)
     */
    public function list(): string
    {
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }

        $env = ['llm_ready' => false];

        $rs = p4c_query(
            "SELECT pa.*,
                    a.`username` AS `actor_name`
             FROM `photo_albums` pa
             LEFT JOIN `actors` a ON a.`id` = pa.`actor_id` AND a.`merchant_id` = pa.`merchant_id`
             WHERE (pa.`album_checked` = '0000-00-00 00:00:00' OR pa.`album_checked` = '000-00-00 00:00:00')
               AND pa.`released` = '1'
               AND pa.`status` != 'deleted'
             ORDER BY pa.`id` ASC;",
            __FILE__,
            __LINE__
        );

        $albums = [];
        $kpis = [
            'total'          => 0,
            'bram_ok'        => 0,
            'bram_attention' => 0,
            'co_actor_open'  => 0,
        ];

        if ($rs && p4c_num_rows($rs) > 0) {
            while ($row = p4c_fetch_object($rs)) {
                $albumDbId   = abs((int)$row->id);
                $albumIdHash = (string)($row->album_id ?? '');
                $merchantId  = abs((int)$row->merchant_id);
                $actorId     = abs((int)$row->actor_id);

                $title       = self::decodeText((string)($row->title ?? ''));
                $description = self::decodeText((string)($row->description ?? ''));
                $metaTitle   = self::decodeText((string)($row->meta_title ?? ''));
                $metaDesc    = self::decodeText((string)($row->meta_description ?? ''));
                $actorName   = self::decodeText((string)($row->actor_name ?? ''));

                // Foto-Zähler & abgelehnte Einzelfotos ermitteln
                $rsPhotoStats = p4c_query(
                    "SELECT COUNT(*) AS `total_cnt`,
                            SUM(CASE WHEN `rejected` = '1' THEN 1 ELSE 0 END) AS `rej_cnt`
                     FROM `photo_albums_photos`
                     WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
                    __FILE__,
                    __LINE__
                );
                $photoStatsObj  = $rsPhotoStats ? p4c_fetch_object($rsPhotoStats) : null;
                $totalPhotos    = $photoStatsObj ? abs((int)($photoStatsObj->total_cnt ?? 0)) : abs((int)($row->number_of_photos ?? 0));
                $rejectedPhotos = $photoStatsObj ? abs((int)($photoStatsObj->rej_cnt ?? 0)) : 0;

                // BRAM-Textprüfung für Albumtitel, Beschreibung & SEO-Felder
                $bramComp    = 'ok';
                $bramScore   = 0;
                $bramSummary = 'Keine Verstöße erkannt.';
                if (function_exists('aic_analyze_texts')) {
                    $rep = aic_analyze_texts([
                        'title'            => $title,
                        'description'      => $description,
                        'meta_title'       => $metaTitle,
                        'meta_description' => $metaDesc,
                    ], [], '', $env, 'photo_album');
                    $bramComp    = (string)($rep['compliance_status'] ?? 'ok');
                    $bramScore   = abs((int)($rep['risk_score'] ?? 0));
                    $bramSummary = (string)($rep['summary_de'] ?? '');
                }

                // 18+ Mitwirkende / ID-Shot Status ermitteln
                $performerSummary = [
                    'mode'         => 'solo_owner',
                    'count'        => 1,
                    'all_verified' => true,
                    'has_rejected' => false,
                ];
                if (class_exists('CoActorManager')) {
                    $perfInfo   = \CoActorManager::getContentPerformers('photo_album', $albumDbId, $merchantId);
                    $performers = is_array($perfInfo['performers'] ?? null) ? $perfInfo['performers'] : [];
                    $allVer     = true;
                    $hasRej     = false;
                    foreach ($performers as $p) {
                        $st = (string)($p['checked_status'] ?? '');
                        if ($st !== 'verified') {
                            $allVer = false;
                        }
                        if ($st === 'rejected' || empty($p['is_ready_for_use'])) {
                            $hasRej = true;
                        }
                    }
                    $performerSummary = [
                        'mode'         => (string)($perfInfo['performer_mode'] ?? 'solo_owner'),
                        'count'        => max(1, count($performers)),
                        'all_verified' => $allVer,
                        'has_rejected' => $hasRej,
                    ];
                }

                $catMaster = (string)($row->category_master ?: 'porn');
                $amountDownloadCent = abs((int)($row->amount_download ?? 0));
                $priceEurFormatted  = number_format($amountDownloadCent / 100, 2, ',', '.') . ' €';

                $kpis['total']++;
                if ($bramComp === 'ok') {
                    $kpis['bram_ok']++;
                } else {
                    $kpis['bram_attention']++;
                }
                if (!$performerSummary['all_verified']) {
                    $kpis['co_actor_open']++;
                }

                $albums[] = [
                    'id'               => $albumDbId,
                    'album_id'         => $albumIdHash,
                    'merchant_id'      => $merchantId,
                    'actor_id'         => $actorId,
                    'actor_name'       => $actorName !== '' ? $actorName : 'Kein Profil gewählt',
                    'title'            => $title !== '' ? $title : '(Ohne Albumtitel)',
                    'status'           => (string)($row->status ?: 'active'),
                    'category_master'  => $catMaster,
                    'number_of_photos' => $totalPhotos,
                    'rejected_photos'  => $rejectedPhotos,
                    'has_fsk16'        => trim((string)($row->preview_image_fsk16 ?? '')) !== '',
                    'has_fsk18'        => trim((string)($row->preview_image_fsk18 ?? '')) !== '',
                    'amount_download'  => $amountDownloadCent,
                    'price_eur'        => $priceEurFormatted,
                    'online_at'        => (string)($row->online_at ?? ''),
                    'create_datetime'  => (string)($row->create_datetime ?? ''),
                    'bram_compliance'  => $bramComp,
                    'bram_risk_score'  => $bramScore,
                    'bram_summary'     => $bramSummary,
                    'performers'       => $performerSummary,
                ];
            }
        }

        return View::render('PhotoAlbumsChecking', 'list', [
            'albums' => $albums,
            'kpis'   => $kpis,
        ]);
    }

    /**
     * 2. Einzelprüfung & Freigabe-Ansicht für ein Fotoalbum (#/photo-album-checking?id=...)
     */
    public function detail(): string
    {
        global $mysql;

        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }

        $albumDbId = abs((int)$this->request->get('id', 0));
        if ($albumDbId <= 0) {
            return $this->renderNotFound('Es wurde keine gültige Fotoalbum-ID übergeben.');
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            return $this->renderNotFound("Das angeforderte Fotoalbum mit der ID #{$albumDbId} existiert nicht in der Datenbank.");
        }

        $a           = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)($a->album_id ?? '');
        $merchantId  = abs((int)$a->merchant_id);
        $actorId     = abs((int)$a->actor_id);

        // Warteschlangen-Navigation (Vorheriges / Nächstes Album in Prüfung)
        $queueIds    = $this->fetchUncheckedQueueIds();
        $queuePos    = array_search($albumDbId, $queueIds, true);
        $prevAlbumId = ($queuePos !== false && $queuePos > 0) ? $queueIds[$queuePos - 1] : 0;
        $nextAlbumId = ($queuePos !== false && isset($queueIds[$queuePos + 1])) ? $queueIds[$queuePos + 1] : ($queueIds[0] ?? 0);
        if ($nextAlbumId === $albumDbId) {
            $nextAlbumId = 0;
        }

        // Merchant-Details laden
        $merchantPartnerId = $merchantId;
        $merchantUsername  = 'Merchant #' . $merchantId;
        if (class_exists('Merchant')) {
            try {
                $merchantObj = new \Merchant($mysql, $merchantId);
                if (!empty($merchantObj->partner_id)) {
                    $merchantPartnerId = abs((int)$merchantObj->partner_id);
                }
                $decUser = (string)$merchantObj->username('aes_decrypt');
                if ($decUser !== '') {
                    $merchantUsername = self::decodeText($decUser);
                }
            } catch (\Throwable $e) {
                // Fallback behalten
            }
        }

        // Alle Darsteller-Profile dieses Merchants laden
        $merchantActors      = [];
        $currentActorExists  = false;
        $currentActorName    = '';
        $currentActorCatKeys = [];
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
                if ($aid === $actorId) {
                    $currentActorExists  = true;
                    $currentActorName    = $aname;
                    $currentActorCatKeys = array_values(array_filter(array_map('trim', explode(',', (string)($act->actor_categories ?? '')))));
                }
                $merchantActors[] = [
                    'id'               => $aid,
                    'username'         => $aname,
                    'actor_categories' => (string)($act->actor_categories ?? ''),
                    'status'           => (string)($act->status ?? ''),
                ];
            }
        }

        // Prüfen, ob das Fotoalbum bereits in photo_albums_online existiert
        $onlineInfo = [
            'is_in_online'  => false,
            'online_status' => '',
            'views'         => abs((int)($a->views ?? 0)),
            'purchases'     => abs((int)($a->purchases ?? 0)),
            'last_purchase' => (string)($a->last_purchase ?? ''),
        ];
        $rsOnline = p4c_query(
            "SELECT `id`, `status`, `views`, `purchases`, `last_purchase`
             FROM `photo_albums_online`
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
            $onObj = p4c_fetch_object($rsOnline);
            $onlineInfo['is_in_online']  = true;
            $onlineInfo['online_status'] = (string)($onObj->status ?? 'active');
            $onlineInfo['views']         = abs((int)($onObj->views ?? $onlineInfo['views']));
            $onlineInfo['purchases']     = abs((int)($onObj->purchases ?? $onlineInfo['purchases']));
            $onlineInfo['last_purchase'] = (string)($onObj->last_purchase ?? $onlineInfo['last_purchase']);
        }

        // Alle Einzelfotos des Albums laden
        $photos        = [];
        $rejectedCount = 0;
        $rsPhotos = p4c_query(
            "SELECT *
             FROM `photo_albums_photos`
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
             ORDER BY `id` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsPhotos && p4c_num_rows($rsPhotos) > 0) {
            $idx = 0;
            while ($ph = p4c_fetch_object($rsPhotos)) {
                $idx++;
                $isRej = abs((int)($ph->rejected ?? 0)) === 1;
                if ($isRej) {
                    $rejectedCount++;
                }
                $photos[] = [
                    'id'              => abs((int)$ph->id),
                    'index'           => $idx,
                    'file_id'         => (string)($ph->file_id ?? ''),
                    'filename'        => (string)($ph->filename ?? ''),
                    'upload_datetime' => (string)($ph->upload_datetime ?? ''),
                    'rejected'        => $isRej,
                ];
            }
        }

        // Texte dekodieren
        $title       = self::decodeText((string)($a->title ?? ''));
        $description = self::decodeText((string)($a->description ?? ''));
        $metaTitle   = self::decodeText((string)($a->meta_title ?? ''));
        $metaDesc    = self::decodeText((string)($a->meta_description ?? ''));
        $seoUrl      = self::decodeText((string)($a->seo_url ?? ''));
        $adminInfos  = self::decodeText((string)($a->admin_infos ?? ''));

        // Prüfen, ob ein anderes Fotoalbum mit identischem Titel existiert
        $duplicateAlbum = null;
        if ($title !== '') {
            $rsDup = p4c_query(
                "SELECT `id`, `album_id`, `merchant_id`, `title`
                 FROM `photo_albums`
                 WHERE `title` = '" . p4c_escape_string($title) . "'
                   AND `id` != '" . $albumDbId . "'
                   AND `status` != 'deleted'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if ($rsDup && p4c_num_rows($rsDup) > 0) {
                $dupObj = p4c_fetch_object($rsDup);
                $duplicateAlbum = [
                    'id'          => abs((int)$dupObj->id),
                    'album_id'    => (string)($dupObj->album_id ?? ''),
                    'merchant_id' => abs((int)$dupObj->merchant_id),
                    'title'       => self::decodeText((string)($dupObj->title ?? '')),
                ];
            }
        }

        // Kategorien aus photo_albums_categories laden und gruppieren
        $selectedSlaves = array_values(array_filter(array_map('trim', explode(',', (string)($a->category_slave ?? '')))));
        $categoriesByGroup = [
            'number_of_people' => [],
            'look_and_body'    => [],
            'fetish'           => [],
            'porn'             => [],
        ];
        $searchHaystack = mb_strtolower($title . ' ' . strip_tags($description), 'UTF-8');

        $rsCats = p4c_query(
            "SELECT `name_id`, `category_group`, `de_name_value`, `de_name_text`, `more_search_words`
             FROM `photo_albums_categories`
             ORDER BY `category_group` ASC, `de_name_value` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsCats && p4c_num_rows($rsCats) > 0) {
            while ($c = p4c_fetch_object($rsCats)) {
                $grp      = (string)($c->category_group ?: 'porn');
                $nameId   = (string)($c->name_id ?? '');
                $deValue  = self::decodeText((string)($c->de_name_value ?? ''));
                $deText   = self::decodeText((string)($c->de_name_text ?? ''));
                $moreWords = self::decodeText((string)($c->more_search_words ?? ''));

                $inText = false;
                if ($nameId !== '' && mb_stripos($searchHaystack, mb_strtolower($nameId, 'UTF-8')) !== false) {
                    $inText = true;
                } elseif ($deValue !== '' && mb_stripos($searchHaystack, mb_strtolower($deValue, 'UTF-8')) !== false) {
                    $inText = true;
                } elseif ($moreWords !== '') {
                    foreach (explode(',', $moreWords) as $w) {
                        $wClean = trim(mb_strtolower($w, 'UTF-8'));
                        if ($wClean !== '' && mb_stripos($searchHaystack, $wClean) !== false) {
                            $inText = true;
                            break;
                        }
                    }
                }

                $inActorProfile = ($grp === 'look_and_body' && in_array($nameId, $currentActorCatKeys, true));
                $isSelected     = in_array($nameId, $selectedSlaves, true)
                    || (empty($selectedSlaves) && ($inActorProfile || $inText));

                if (!isset($categoriesByGroup[$grp])) {
                    $categoriesByGroup[$grp] = [];
                }
                $categoriesByGroup[$grp][] = [
                    'name_id'      => $nameId,
                    'de'           => $deValue,
                    'description'  => $deText,
                    'selected'     => $isSelected,
                    'in_text'      => $inText,
                    'from_profile' => $inActorProfile,
                ];
            }
        }

        // Ablehnungsvorlagen & Historie laden
        $rejectionReasons = [];
        $rsRej = p4c_query(
            "SELECT `id_name`, `de_short`, `de_long`
             FROM `rejection_reason_photo_album`
             ORDER BY `de_short` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rsRej && p4c_num_rows($rsRej) > 0) {
            while ($r = p4c_fetch_object($rsRej)) {
                $rejectionReasons[] = [
                    'id_name'  => (string)($r->id_name ?? ''),
                    'de_short' => self::decodeText((string)($r->de_short ?? '')),
                    'de_long'  => self::decodeText((string)($r->de_long ?? '')),
                ];
            }
        }

        $rejectionHistory = [];
        $rsHist = p4c_query(
            "SELECT h.`id_name`, h.`text`, h.`datetime`, r.`de_short`
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
                    'id_name'  => (string)($h->id_name ?? ''),
                    'de_short' => self::decodeText((string)($h->de_short ?? $h->id_name ?? '')),
                    'text'     => self::decodeText((string)($h->text ?? '')),
                    'datetime' => (string)($h->datetime ?? ''),
                ];
            }
        }

        // 18+ Mitwirkende Personen & ID-Shot Daten laden
        $performersData = $this->loadAlbumPerformersData($albumDbId, $merchantId);

        // BRAM-Compliance-Check auf Album-Texte ausführen
        $aiData = $this->analyzeAlbumTexts([
            'title'            => $title,
            'description'      => $description,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
        ]);

        $onlineAtFormatted = (!empty($a->online_at) && $a->online_at !== '0000-00-00 00:00:00')
            ? date('Y-m-d\TH:i', strtotime((string)$a->online_at))
            : date('Y-m-d\TH:i');

        $albumData = [
            'id'                  => $albumDbId,
            'album_id'            => $albumIdHash,
            'merchant_id'         => $merchantId,
            'merchant_partner_id' => $merchantPartnerId,
            'merchant_username'   => $merchantUsername,
            'actor_id'            => $actorId,
            'actor_exists'        => $currentActorExists,
            'actor_name'          => $currentActorName,
            'storage_location'    => (string)($a->storage_location ?? ''),
            'checksum'            => (string)($a->checksum ?? ''),
            'number_of_photos'    => count($photos),
            'rejected_photos'     => $rejectedCount,
            'preview_image_fsk16' => (string)($a->preview_image_fsk16 ?? ''),
            'preview_image_fsk18' => (string)($a->preview_image_fsk18 ?? ''),
            'has_fsk16'           => trim((string)($a->preview_image_fsk16 ?? '')) !== '',
            'has_fsk18'           => trim((string)($a->preview_image_fsk18 ?? '')) !== '',
            'title'               => $title,
            'description'         => $description,
            'meta_title'          => $metaTitle,
            'meta_description'    => $metaDesc,
            'seo_url'             => $seoUrl,
            'category_master'     => (string)($a->category_master ?: 'porn'),
            'selected_slaves'     => $selectedSlaves,
            'online_at'           => $onlineAtFormatted,
            'amount_webmaster'    => abs((int)($a->amount_webmaster ?: 10)),
            'amount_download'     => abs((int)($a->amount_download ?? 500)),
            'status'              => (string)($a->status ?: 'active'),
            'admin_infos'         => $adminInfos,
            'released'            => abs((int)($a->released ?? 0)),
            'album_checked'       => (string)($a->album_checked ?? ''),
            'create_datetime'     => (string)($a->create_datetime ?? ''),
        ];

        return View::render('PhotoAlbumsChecking', 'detail', [
            'album'             => $albumData,
            'photos'            => $photos,
            'onlineInfo'        => $onlineInfo,
            'duplicateAlbum'    => $duplicateAlbum,
            'merchantActors'    => $merchantActors,
            'categoriesByGroup' => $categoriesByGroup,
            'rejectionReasons'  => $rejectionReasons,
            'rejectionHistory'  => $rejectionHistory,
            'performersData'    => $performersData,
            'aiData'            => $aiData,
            'employeeRule'      => (string)($_SESSION['employee_rule'] ?? ''),
            'queueTotal'        => count($queueIds),
            'queuePosition'     => $queuePos !== false ? ($queuePos + 1) : 0,
            'prevAlbumId'       => $prevAlbumId,
            'nextAlbumId'       => $nextAlbumId,
        ]);
    }

    /**
     * 3. AJAX: Einzelfoto (oder alle Fotos) im Album ablehnen / freigeben (POST ?route=photo-albums-checking/toggle-photo)
     */
    public function togglePhoto(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $albumDbId = abs((int)$this->request->post('album_db_id', 0));
        $photoId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$this->request->post('photo_id', ''));
        $rejected  = abs((int)$this->request->post('rejected', 1)) === 1 ? 1 : 0;
        $batchMode = trim((string)$this->request->post('batch_mode', ''));

        if ($batchMode === 'allow_all' && $albumDbId > 0) {
            $rsAlb = p4c_query("SELECT `album_id` FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' LIMIT 1;", __FILE__, __LINE__);
            if (!$rsAlb || p4c_num_rows($rsAlb) === 0) {
                Response::error('Fotoalbum nicht gefunden.', 404);
                return;
            }
            $albHash = (string)p4c_fetch_object($rsAlb)->album_id;
            p4c_query(
                "UPDATE `photo_albums_photos` SET `rejected` = '0' WHERE `album_id` = '" . p4c_escape_string($albHash) . "';",
                __FILE__,
                __LINE__
            );
            log_action("Alle Fotos in Fotoalbum #{$albumDbId} als zulässig markiert.");
            Response::json([
                'status'         => 'ok',
                'message'        => 'Alle Fotos im Album wurden als zulässig freigegeben.',
                'rejected_count' => 0,
            ]);
            return;
        }

        if ($photoId === '') {
            Response::error('Ungültige Foto-ID.', 400);
            return;
        }

        $rsPhoto = p4c_query(
            "SELECT `id`, `album_id` FROM `photo_albums_photos` WHERE `file_id` = '" . p4c_escape_string($photoId) . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsPhoto || p4c_num_rows($rsPhoto) === 0) {
            Response::error('Das ausgewählte Foto existiert nicht.', 404);
            return;
        }

        $phObj   = p4c_fetch_object($rsPhoto);
        $albHash = (string)$phObj->album_id;

        p4c_query(
            "UPDATE `photo_albums_photos`
             SET `rejected` = '" . $rejected . "'
             WHERE `file_id` = '" . p4c_escape_string($photoId) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        $rsCnt = p4c_query(
            "SELECT COUNT(*) AS `cnt` FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albHash) . "' AND `rejected` = '1';",
            __FILE__,
            __LINE__
        );
        $rejCnt = $rsCnt ? abs((int)(p4c_fetch_object($rsCnt)->cnt ?? 0)) : 0;

        Response::json([
            'status'         => 'ok',
            'photo_id'       => $photoId,
            'rejected'       => $rejected,
            'rejected_count' => $rejCnt,
            'message'        => $rejected === 1 ? 'Foto wurde als abgelehnt markiert.' : 'Ablehnung des Fotos wurde aufgehoben.',
        ]);
    }

    /**
     * 4. AJAX: Fotoalbum prüfen, speichern & veröffentlichen (POST ?route=photo-albums-checking/approve)
     */
    public function approve(): void
    {
        global $mysql;

        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $employeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $employeeRule = (string)($_SESSION['employee_rule'] ?? '');
        $albumDbId    = abs((int)$this->request->post('album_id', 0));

        if ($albumDbId <= 0) {
            Response::error('Ungültige Fotoalbum-ID.', 400);
            return;
        }

        $rsAlbum = p4c_query("SELECT * FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das Fotoalbum wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $a           = p4c_fetch_object($rsAlbum);
        $albumIdHash = (string)$a->album_id;
        $merchantId  = abs((int)$a->merchant_id);

        $errors = [];

        // 1. Darsteller validieren
        $actorId = abs((int)$this->request->post('actor_id', $a->actor_id));
        $rsActor = p4c_query(
            "SELECT `id` FROM `actors` WHERE `id` = '" . $actorId . "' AND `merchant_id` = '" . $merchantId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            $errors[] = 'Bitte wähle ein gültiges Darsteller-Profil dieses Merchants aus.';
        }

        // 2. Hauptkategorie & Subkategorien validieren
        $categoryMaster = trim((string)$this->request->post('category_master', ''));
        if (!in_array($categoryMaster, ['porn', 'fetish'], true)) {
            $errors[] = 'Bitte wähle eine Hauptkategorie (Porno oder Fetisch) aus.';
        }

        $rawCategories = $this->request->post('categories', []);
        if (!is_array($rawCategories)) {
            $rawCategories = [];
        }
        $allowedCatSet = [];
        $rsValidCats = p4c_query("SELECT `name_id` FROM `photo_albums_categories`;", __FILE__, __LINE__);
        if ($rsValidCats) {
            while ($vc = p4c_fetch_object($rsValidCats)) {
                $allowedCatSet[(string)$vc->name_id] = true;
            }
        }
        $validCategories = [];
        foreach ($rawCategories as $catKey) {
            $catKeyClean = trim((string)$catKey);
            if ($catKeyClean !== '' && isset($allowedCatSet[$catKeyClean])) {
                $validCategories[] = $catKeyClean;
            }
        }
        $validCategories = array_values(array_unique($validCategories));
        if (empty($validCategories)) {
            $errors[] = 'Bitte wähle mindestens eine Unterkategorie für das Fotoalbum aus.';
        }

        // 3. Titel & Beschreibung bereinigen & validieren
        $title = trim((string)$this->request->post('title', ''));
        $title = str_replace(self::REPLACE_TITLE_CHARS, '', $title);
        $title = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;', "\xe2\x80\x8b"], ['EUR', 'EUR', 'EUR', 'EUR', 'EUR', 'EUR', ''], $title);
        $title = trim(strip_tags($title));
        if ($title === '') {
            $errors[] = 'Bitte gib einen aussagekräftigen Titel für das Fotoalbum ein.';
        } elseif (mb_strlen($title, 'UTF-8') > 65) {
            $errors[] = 'Der Albumtitel darf maximal 65 Zeichen lang sein.';
        }

        $allowedTags = '<ul><ol><li><u><em><strong><h1><h2><h3><h4><h5><h6><pre><address><p><br><b><i>';
        $description = trim(strip_tags((string)$this->request->post('description', ''), $allowedTags));
        $description = str_replace(['€', "\xe2\x80\x8b"], ['EUR', ''], $description);
        if ($description === '') {
            $errors[] = 'Bitte gib eine aussagekräftige Beschreibung für das Fotoalbum ein.';
        }

        // 4. Online-ab Datum & Preis validieren
        $onlineAtRaw = trim((string)$this->request->post('online_at', ''));
        $onlineAtTs  = $onlineAtRaw !== '' ? strtotime($onlineAtRaw) : false;
        if ($onlineAtTs === false) {
            $errors[] = 'Bitte gib ein gültiges Online-ab-Datum an.';
            $onlineAtSql = date('Y-m-d H:i:s');
        } else {
            $onlineAtSql = date('Y-m-d H:i:s', $onlineAtTs);
        }

        $amountDownload = abs((int)$this->request->post('amount_download', 0));
        if ($amountDownload > 5000) {
            $errors[] = 'Der Album-Preis darf maximal 50,00 EUR (5.000 Cent) betragen.';
        }

        // 5. SEO-Felder
        $metaTitle = trim(strip_tags(str_replace(self::REPLACE_TITLE_CHARS, '', (string)$this->request->post('meta_title', ''))));
        $metaTitle = str_replace(['&#8364;', '&euro;', 'Â€', '€', 'â‚¬', '&#x20AC;', "\n", "\r"], ['EUR', 'EUR', 'EUR', 'EUR', 'EUR', 'EUR', '', ''], $metaTitle);
        if ($metaTitle === '') {
            $metaTitle = mb_substr($title, 0, 65, 'UTF-8');
        } elseif (mb_strlen($metaTitle, 'UTF-8') > 65) {
            $metaTitle = mb_substr($metaTitle, 0, 65, 'UTF-8');
        }

        $metaDesc = trim(strip_tags(str_replace(["\n", "\r"], ' ', (string)$this->request->post('meta_description', ''))));
        if ($metaDesc === '') {
            $metaDesc = mb_substr(trim(strip_tags($description)), 0, 156, 'UTF-8');
        } elseif (mb_strlen($metaDesc, 'UTF-8') > 156) {
            $metaDesc = mb_substr($metaDesc, 0, 156, 'UTF-8');
        }

        $seoUrlRaw = trim((string)$this->request->post('seo_url', ''));
        $seoUrl = function_exists('seo_url')
            ? (string)seo_url($seoUrlRaw !== '' ? $seoUrlRaw : $title)
            : strtolower((string)preg_replace('/[^a-zA-Z0-9-]+/', '-', $seoUrlRaw !== '' ? $seoUrlRaw : $title));

        // 6. Status & Admin-Infos
        $allowedStatuses = ['active', 'blocked'];
        if ($employeeRule === 'write') {
            $allowedStatuses[] = 'deleted';
        }
        $albumStatus = trim((string)$this->request->post('album_status', 'active'));
        if (!in_array($albumStatus, $allowedStatuses, true)) {
            $errors[] = 'Ungültiger Album-Status ausgewählt.';
        }
        $adminInfos = trim(strip_tags((string)$this->request->post('admin_infos', '')));

        // 7. Mitwirkende Personen / 18+ ID-Shot Prüfung
        if (class_exists('CoActorManager')) {
            $perfCheck = \CoActorManager::getContentPerformers('photo_album', $albumDbId, $merchantId);
            foreach ($perfCheck['performers'] ?? [] as $pCheck) {
                if (($pCheck['checked_status'] ?? '') === 'rejected') {
                    $errors[] = 'Mindestens eine mitwirkende Person im Fotoalbum hat einen abgelehnten ID-Shot ('
                        . ($pCheck['display_label'] ?? '')
                        . '). Bitte lehne das Fotoalbum ab oder korrigiere zuerst den Status der Person.';
                    break;
                }
            }
        }

        // Prüfen, ob noch abgelehnte Einzelfotos im Album vorhanden sind
        $rsPhotosCnt = p4c_query(
            "SELECT COUNT(*) AS `total_cnt`,
                    SUM(CASE WHEN `rejected` = '1' THEN 1 ELSE 0 END) AS `rej_cnt`
             FROM `photo_albums_photos`
             WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
            __FILE__,
            __LINE__
        );
        $cntObj         = $rsPhotosCnt ? p4c_fetch_object($rsPhotosCnt) : null;
        $numberOfPhotos = $cntObj ? abs((int)($cntObj->total_cnt ?? 0)) : abs((int)$a->number_of_photos);
        $rejectedPhotos = $cntObj ? abs((int)($cntObj->rej_cnt ?? 0)) : 0;

        if ($rejectedPhotos > 0 && $albumStatus === 'active') {
            $errors[] = "Es sind noch {$rejectedPhotos} abgelehnte(s) Foto(s) in diesem Album markiert. Bitte lehne das Album ab oder hebe die Ablehnung der Einzelfotos auf, bevor du das Album freigibst.";
        }

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 422);
            return;
        }

        // Alle noch auf 'under_review' stehenden, vollständigen Mitwirkenden automatisch verifizieren
        if (class_exists('CoActorManager')) {
            $perfCheck = \CoActorManager::getContentPerformers('photo_album', $albumDbId, $merchantId);
            foreach ($perfCheck['performers'] ?? [] as $pAuto) {
                if (!empty($pAuto['is_ready_for_use']) && ($pAuto['checked_status'] ?? '') === 'under_review') {
                    \CoActorManager::acpReviewCoActor((int)$pAuto['id'], $merchantId, 'verify', '', $employeeId, false);
                }
            }
        }

        $categorySlaveStr = implode(',', $validCategories);
        $now = date('Y-m-d H:i:s');

        // Haupttabelle photo_albums aktualisieren
        p4c_query(
            "UPDATE `photo_albums` SET
                `actor_id`              = '" . $actorId . "',
                `number_of_photos`      = '" . $numberOfPhotos . "',
                `title`                 = '" . p4c_escape_string($title) . "',
                `description`           = '" . p4c_escape_string($description) . "',
                `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                `online_at`             = '" . p4c_escape_string($onlineAtSql) . "',
                `amount_download`       = '" . $amountDownload . "',
                `status`                = '" . p4c_escape_string($albumStatus) . "',
                `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                `album_checked`         = '" . $now . "',
                `released`              = '1',
                `released_from`         = '" . $employeeId . "',
                `released_datetime`     = '" . $now . "',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `id` = '" . $albumDbId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        // Checksumme aktualisieren
        $checksum = (string)($a->checksum ?? '');
        if (function_exists('photo_album_checksum') && class_exists('PhotoAlbum')) {
            $paObj    = new \PhotoAlbum($mysql, $albumDbId);
            $checksum = (string)photo_album_checksum($paObj);
            p4c_query(
                "UPDATE `photo_albums` SET `checksum` = '" . p4c_escape_string($checksum) . "' WHERE `id` = '" . $albumDbId . "' LIMIT 1;",
                __FILE__,
                __LINE__
            );
        }

        // photo_albums_online synchronisieren
        $rsCheckOnline = p4c_query(
            "SELECT `id` FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if ($rsCheckOnline && p4c_num_rows($rsCheckOnline) > 0) {
            p4c_query(
                "UPDATE `photo_albums_online` SET
                    `actor_id`              = '" . $actorId . "',
                    `number_of_photos`      = '" . $numberOfPhotos . "',
                    `checksum`              = '" . p4c_escape_string($checksum) . "',
                    `preview_image_fsk16`   = '" . p4c_escape_string((string)$a->preview_image_fsk16) . "',
                    `preview_image_fsk18`   = '" . p4c_escape_string((string)$a->preview_image_fsk18) . "',
                    `title`                 = '" . p4c_escape_string($title) . "',
                    `description`           = '" . p4c_escape_string($description) . "',
                    `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                    `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                    `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                    `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                    `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                    `online_at`             = '" . p4c_escape_string($onlineAtSql) . "',
                    `amount_download`       = '" . $amountDownload . "',
                    `status`                = '" . p4c_escape_string($albumStatus) . "',
                    `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                    `album_checked`         = '" . $now . "',
                    `last_updated_by`       = '" . $employeeId . "',
                    `last_updated_datetime` = '" . $now . "'
                 WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
        } elseif ($albumStatus === 'active') {
            p4c_query(
                "INSERT INTO `photo_albums_online` SET
                    `merchant_id`           = '" . $merchantId . "',
                    `actor_id`              = '" . $actorId . "',
                    `storage_location`      = '" . p4c_escape_string((string)$a->storage_location) . "',
                    `checksum`              = '" . p4c_escape_string($checksum) . "',
                    `album_id`              = '" . p4c_escape_string($albumIdHash) . "',
                    `number_of_photos`      = '" . $numberOfPhotos . "',
                    `create_datetime`       = '" . p4c_escape_string((string)$a->create_datetime) . "',
                    `preview_image_fsk16`   = '" . p4c_escape_string((string)$a->preview_image_fsk16) . "',
                    `preview_image_fsk18`   = '" . p4c_escape_string((string)$a->preview_image_fsk18) . "',
                    `title`                 = '" . p4c_escape_string($title) . "',
                    `description`           = '" . p4c_escape_string($description) . "',
                    `meta_title`            = '" . p4c_escape_string($metaTitle) . "',
                    `meta_description`      = '" . p4c_escape_string($metaDesc) . "',
                    `seo_url`               = '" . p4c_escape_string($seoUrl) . "',
                    `category_master`       = '" . p4c_escape_string($categoryMaster) . "',
                    `category_slave`        = '" . p4c_escape_string($categorySlaveStr) . "',
                    `online_at`             = '" . p4c_escape_string($onlineAtSql) . "',
                    `amount_webmaster`      = '" . abs((int)$a->amount_webmaster) . "',
                    `amount_download`       = '" . $amountDownload . "',
                    `status`                = '" . p4c_escape_string($albumStatus) . "',
                    `admin_infos`           = '" . p4c_escape_string($adminInfos) . "',
                    `album_checked`         = '" . $now . "',
                    `released`              = '1',
                    `released_from`         = '" . $employeeId . "',
                    `released_datetime`     = '" . $now . "',
                    `last_updated_by`       = '" . $employeeId . "',
                    `last_updated_datetime` = '" . $now . "';",
                __FILE__,
                __LINE__
            );

            // Punktesystem für neues kostenpflichtiges Fotoalbum gutschreiben
            if ($amountDownload > 0 && is_file(KLASSEN_DIR . '/PointsSystem.inc.php')) {
                require_once KLASSEN_DIR . '/PointsSystem.inc.php';
                if (class_exists('PointsSystem')) {
                    try {
                        $ps = new \PointsSystem();
                        $ps->MerchantID($merchantId);
                        $ps->ActorID($actorId);
                        $ps->AddPoints([
                            'group'      => 'photo_album_upload',
                            'points_for' => 'new_photo_album',
                            'info'       => 'ID: #' . $albumDbId . ' / Album-ID: ' . $albumIdHash,
                        ]);
                    } catch (\Throwable $e) {
                        // Nicht-kritischer Fehler beim Punktesystem
                    }
                }
            }
        }

        log_action("Fotoalbum geprüft & gespeichert (ID: #{$albumDbId}, Album-ID: {$albumIdHash}, Status: {$albumStatus}).");

        // Partner per E-Mail über die Freigabe des Foto-Albums informieren
        if ($albumStatus === 'active' && $merchantId > 0 && class_exists('EroCloudMailer')) {
            try {
                \EroCloudMailer::sendContentReviewNotification([
                    'merchant_id'      => $merchantId,
                    'content_type'     => 'photo_album',
                    'content_id'       => $albumDbId,
                    'review_status'    => 'approved',
                    'extra_meta_label' => 'Umfang',
                    'extra_meta_value' => $numberOfPhotos . ' Fotos',
                ]);
            } catch (\Throwable $e) {
                // Nicht-kritischer Fehler beim E-Mail-Versand
            }
        }

        $remainingQueue = $this->fetchUncheckedQueueIds();
        $nextId = $remainingQueue[0] ?? 0;

        Response::json([
            'status'          => 'ok',
            'message'         => "Das Fotoalbum #{$albumDbId} („{$title}“) wurde erfolgreich geprüft und veröffentlicht!",
            'next_album_id'   => $nextId,
            'remaining_count' => count($remainingQueue),
        ]);
    }

    /**
     * 5. AJAX: Fotoalbum ablehnen (POST ?route=photo-albums-checking/reject)
     */
    public function reject(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $albumDbId  = abs((int)$this->request->post('album_id', 0));
        $reasonCode = trim((string)$this->request->post('rejection_reason', '0'));
        $reasonText = trim(strip_tags((string)$this->request->post('rejection_text', '')));

        if ($albumDbId <= 0) {
            Response::error('Ungültige Fotoalbum-ID.', 400);
            return;
        }
        if ($reasonCode === '' || $reasonCode === '0') {
            Response::error('Bitte wähle einen Ablehnungsgrund aus der Liste aus.', 422);
            return;
        }
        if ($reasonText === '') {
            Response::error('Bitte gib eine Begründung für die Ablehnung des Fotoalbums ein.', 422);
            return;
        }

        $rsAlbum = p4c_query("SELECT `id`, `merchant_id`, `album_id`, `title` FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsAlbum || p4c_num_rows($rsAlbum) === 0) {
            Response::error('Das Fotoalbum wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $a           = p4c_fetch_object($rsAlbum);
        $merchantId  = abs((int)($a->merchant_id ?? 0));
        $albumIdHash = (string)$a->album_id;
        $title       = self::decodeText((string)($a->title ?? ''));
        $now         = date('Y-m-d H:i:s');
        $employeeId  = abs((int)($_SESSION['employee_id'] ?? 0));

        $reasonShort = $reasonCode;
        $rsReason = p4c_query(
            "SELECT `de_short` FROM `rejection_reason_photo_album` WHERE `id_name` = '" . p4c_escape_string($reasonCode) . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsReason && p4c_num_rows($rsReason) > 0) {
            $rObj = p4c_fetch_object($rsReason);
            if (!empty($rObj->de_short)) {
                $reasonShort = self::decodeText((string)$rObj->de_short);
            }
        }

        p4c_query(
            "INSERT INTO `rejection_reason_photo_album_history` SET
                `id_name`  = '" . p4c_escape_string($reasonCode) . "',
                `album_id` = '" . p4c_escape_string($albumIdHash) . "',
                `text`     = '" . p4c_escape_string($reasonText) . "',
                `datetime` = '" . $now . "';",
            __FILE__,
            __LINE__
        );

        p4c_query(
            "UPDATE `photo_albums` SET
                `released`              = '2',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `id` = '" . $albumDbId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        log_action("Fotoalbum abgelehnt (ID: #{$albumDbId}, Album-ID: {$albumIdHash}, Grund: {$reasonCode}).");

        // Partner per E-Mail über die Ablehnung / Korrekturbitte des Foto-Albums informieren
        if ($merchantId > 0 && class_exists('EroCloudMailer')) {
            try {
                \EroCloudMailer::sendContentReviewNotification([
                    'merchant_id'   => $merchantId,
                    'content_type'  => 'photo_album',
                    'content_id'    => $albumDbId,
                    'review_status' => 'rejected',
                    'reason_title'  => $reasonShort,
                    'reason_text'   => $reasonText,
                ]);
            } catch (\Throwable $e) {
                // Nicht-kritischer Fehler beim E-Mail-Versand
            }
        }

        $remainingQueue = $this->fetchUncheckedQueueIds();
        $nextId = $remainingQueue[0] ?? 0;

        Response::json([
            'status'          => 'ok',
            'message'         => "Das Fotoalbum #{$albumDbId} („{$title}“) wurde abgelehnt.",
            'next_album_id'   => $nextId,
            'remaining_count' => count($remainingQueue),
        ]);
    }

    /**
     * 6. AJAX: 1-Klick Verifizierung oder Ablehnung eines Drehpartners / ID-Shots (POST ?route=photo-albums-checking/co-actor-action)
     */
    public function coActorAction(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        if (!class_exists('CoActorManager')) {
            Response::error('CoActorManager ist nicht verfügbar.', 500);
            return;
        }

        $employeeId = abs((int)($_SESSION['employee_id'] ?? 0));
        $albumDbId  = abs((int)$this->request->post('album_id', 0));
        $merchantId = abs((int)$this->request->post('merchant_id', 0));
        $coActorId  = abs((int)$this->request->post('co_actor_id', 0));
        $decision   = trim((string)$this->request->post('decision', ''));
        $reason     = trim((string)$this->request->post('reason', ''));

        if ($coActorId <= 0 || $merchantId <= 0 || !in_array($decision, ['verify', 'reject', 'reset_review'], true)) {
            Response::error('Ungültige Parameter für die Drehpartner-Prüfung.', 400);
            return;
        }

        $res = \CoActorManager::acpReviewCoActor($coActorId, $merchantId, $decision, $reason, $employeeId);
        if (empty($res['ok'])) {
            Response::error((string)($res['error'] ?? 'Fehler bei der Drehpartner-Prüfung.'), 422);
            return;
        }

        log_action("Drehpartner / ID-Shot #{$coActorId} (MID #{$merchantId}) bei Fotoalbum-Prüfung #{$albumDbId}: {$decision}.");

        Response::json([
            'status'  => 'ok',
            'message' => (string)($res['message'] ?? 'Status aktualisiert.'),
        ]);
    }

    /**
     * 7. AJAX: BRAM-Textprüfung für Fotoalbum mit aktuellen Formularwerten ausführen (POST ?route=photo-albums-checking/ai-action)
     */
    public function aiAction(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        @session_write_close();

        $albumId = abs((int)$this->request->post('album_id', 0));
        $dbTitle     = '';
        $dbDesc      = '';
        $dbMetaTitle = '';
        $dbMetaDesc  = '';

        if ($albumId > 0) {
            $rsAlb = p4c_query("SELECT `title`, `description`, `meta_title`, `meta_description` FROM `photo_albums` WHERE `id` = '" . $albumId . "' LIMIT 1;", __FILE__, __LINE__);
            if (!$rsAlb || p4c_num_rows($rsAlb) === 0) {
                $rsAlb = p4c_query("SELECT `title`, `description`, `meta_title`, `meta_description` FROM `photo_albums_online` WHERE `id` = '" . $albumId . "' LIMIT 1;", __FILE__, __LINE__);
            }
            if ($rsAlb && p4c_num_rows($rsAlb) > 0) {
                $albObj      = p4c_fetch_object($rsAlb);
                $dbTitle     = self::decodeText((string)($albObj->title ?? ''));
                $dbDesc      = self::decodeText((string)($albObj->description ?? ''));
                $dbMetaTitle = self::decodeText((string)($albObj->meta_title ?? ''));
                $dbMetaDesc  = self::decodeText((string)($albObj->meta_description ?? ''));
            }
        }

        $customTitle     = $this->request->post('title', null);
        $customDesc      = $this->request->post('description', null);
        $customMetaTitle = $this->request->post('meta_title', null);
        $customMetaDesc  = $this->request->post('meta_description', null);

        $titleToCheck     = $customTitle !== null ? trim((string)$customTitle) : $dbTitle;
        $descToCheck      = $customDesc !== null ? trim((string)$customDesc) : $dbDesc;
        $metaTitleToCheck = $customMetaTitle !== null ? trim((string)$customMetaTitle) : $dbMetaTitle;
        $metaDescToCheck  = $customMetaDesc !== null ? trim((string)$customMetaDesc) : $dbMetaDesc;

        $aiData = $this->analyzeAlbumTexts([
            'title'            => $titleToCheck,
            'description'      => $descToCheck,
            'meta_title'       => $metaTitleToCheck,
            'meta_description' => $metaDescToCheck,
        ]);

        Response::json([
            'status'  => 'ok',
            'message' => 'BRAM-Compliance-Prüfung wurde mit den aktuellen Album-Texten aktualisiert.',
            'ai_data' => $aiData,
        ]);
    }

    /**
     * Lädt die IDs aller aktuell offenen Fotoalben in der Prüf-Warteschlange
     *
     * @return array<int, int>
     */
    private function fetchUncheckedQueueIds(): array
    {
        $ids = [];
        $rs = p4c_query(
            "SELECT `id`
             FROM `photo_albums`
             WHERE (`album_checked` = '0000-00-00 00:00:00' OR `album_checked` = '000-00-00 00:00:00')
               AND `released` = '1'
               AND `status` != 'deleted'
             ORDER BY `id` ASC;",
            __FILE__,
            __LINE__
        );
        if ($rs && p4c_num_rows($rs) > 0) {
            while ($r = p4c_fetch_object($rs)) {
                $ids[] = abs((int)$r->id);
            }
        }
        return $ids;
    }

    /**
     * Bereitet alle Mitwirkenden- & 18+ ID-Shot-Informationen für das Fotoalbum auf
     *
     * @return array<string, mixed>
     */
    private function loadAlbumPerformersData(int $albumDbId, int $merchantId): array
    {
        if (!class_exists('CoActorManager')) {
            return [
                'performer_mode'     => 'solo_owner',
                'owner_in_content'   => true,
                'merchant_full_name' => 'Merchant #' . $merchantId,
                'persons'            => [],
                'all_verified'       => true,
            ];
        }

        \CoActorManager::ensureSchema();
        $perfInfo      = \CoActorManager::getContentPerformers('photo_album', $albumDbId, $merchantId);
        $ownerEntry    = \CoActorManager::ensureOwnerCoActor($merchantId);
        $merchantIdent = \CoActorManager::getMerchantIdentity($merchantId);
        $performerMode = (string)($perfInfo['performer_mode'] ?? 'solo_owner');
        $ownerInAlbum  = !empty($perfInfo['owner_in_content']);
        $linkedActors  = is_array($perfInfo['co_actors'] ?? null) ? $perfInfo['co_actors'] : [];

        $personsToReview = [];
        if ($performerMode === 'solo_owner' || $ownerInAlbum) {
            if ($ownerEntry !== null) {
                $personsToReview[(int)$ownerEntry['id']] = $ownerEntry;
            }
        }
        foreach ($linkedActors as $la) {
            $personsToReview[(int)$la['id']] = $la;
        }

        $allVerified = true;
        foreach ($personsToReview as $p) {
            if (($p['checked_status'] ?? '') !== 'verified') {
                $allVerified = false;
                break;
            }
        }

        return [
            'performer_mode'     => $performerMode,
            'owner_in_content'   => $ownerInAlbum,
            'merchant_full_name' => (string)($merchantIdent['full_name'] ?? ('Merchant #' . $merchantId)),
            'persons'            => array_values($personsToReview),
            'all_verified'       => $allVerified,
        ];
    }

    /**
     * Führt die BRAM-Compliance-Textprüfung für das Fotoalbum aus
     *
     * @param array<string, string> $textInput
     * @return array<string, mixed>
     */
    private function analyzeAlbumTexts(array $textInput): array
    {
        if (!function_exists('aic_analyze_texts')) {
            return [
                'text_compliance'  => 'ok',
                'text_risk_score'  => 0,
                'text_summary'     => 'Keine Verstöße erkannt.',
                'text_engine_used' => 'BRAM Keyword-Scanner',
                'updated_at'       => date('d.m.Y H:i:s'),
                'violations'       => [],
            ];
        }

        $env = ['llm_ready' => false];
        $rep = aic_analyze_texts($textInput, [], '', $env, 'photo_album');

        return [
            'text_compliance'  => (string)($rep['compliance_status'] ?? 'ok'),
            'text_risk_score'  => abs((int)($rep['risk_score'] ?? 0)),
            'text_summary'     => (string)($rep['summary_de'] ?? 'Keine Verstöße erkannt.'),
            'text_engine_used' => (string)($rep['engine_used'] ?? 'BRAM-Regel-Engine (JSON v1.1)'),
            'updated_at'       => date('d.m.Y H:i:s'),
            'violations'       => is_array($rep['violations'] ?? null) ? $rep['violations'] : [],
        ];
    }

    private function renderNotFound(string $message): string
    {
        return '<div class="card-kkc m-4"><div class="card-kkc-body p-4 text-center">'
            . '<div class="mb-3"><i class="bi bi-images fs-1 text-muted"></i></div>'
            . '<h2 class="h5 mb-2">Fotoalbum nicht gefunden</h2>'
            . '<p class="text-muted mb-3">' . View::e($message) . '</p>'
            . '<a href="#/photo-albums-checking" class="btn btn-kkc"><i class="bi bi-arrow-left me-1"></i> Zurück zur Fotoalben-Warteschlange</a>'
            . '</div></div>';
    }

    private static function decodeText(string $str): string
    {
        if ($str === '') {
            return '';
        }
        $decoded = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
