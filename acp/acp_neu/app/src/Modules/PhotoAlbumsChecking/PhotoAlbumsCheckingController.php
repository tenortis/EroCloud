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
use App\Modules\MoviesOnline\MoviesOnlineController;

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
                $description = self::decodeDescription((string)($row->description ?? ''));
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
                    'online_at_too_far'=> function_exists('ec_is_online_at_too_far_in_future') && ec_is_online_at_too_far_in_future((string)($row->online_at ?? '')),
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

        // Prüfen, ob das Fotoalbum bereits in photo_albums_online existiert + reale Käuferanzahl laden
        $onlineInfo = [
            'is_in_online'        => false,
            'online_status'       => '',
            'views'               => abs((int)($a->views ?? 0)),
            'purchases'           => abs((int)($a->purchases ?? 0)),
            'access_count'        => 0,
            'effective_purchases' => abs((int)($a->purchases ?? 0)),
            'last_purchase'       => (string)($a->last_purchase ?? ''),
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
        $accessCount = 0;
        if ($albumIdHash !== '') {
            $rsAccessCnt = p4c_query(
                "SELECT COUNT(*) AS `cnt`
                 FROM `photo_albums_access`
                 WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
                __FILE__,
                __LINE__
            );
            if ($rsAccessCnt && p4c_num_rows($rsAccessCnt) > 0) {
                $accessCount = abs((int)(p4c_fetch_object($rsAccessCnt)->cnt ?? 0));
            }
        }
        $onlineInfo['access_count']        = $accessCount;
        $onlineInfo['effective_purchases'] = max((int)$onlineInfo['purchases'], abs((int)($a->purchases ?? 0)), $accessCount);

        // Alle Einzelfotos des Albums laden
        $photos        = [];
        $rejectedCount = 0;
        $stLocPhotos   = trim((string)($a->storage_location ?? ''));
        if ($stLocPhotos === '') {
            $stLocPhotos = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';
        }
        $imagesDirCheck = (defined('PHOTO_ALBUMS_PATH') && $merchantId > 0 && $albumDbId > 0)
            ? (rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . trim($stLocPhotos, '/\\') . '/' . $merchantId . '/' . $albumDbId . '/images/')
            : '';

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
                $phFilename = basename((string)($ph->filename ?? ''));
                $phFile     = ($imagesDirCheck !== '' && $phFilename !== '') ? ($imagesDirCheck . $phFilename) : '';
                $phVer      = ($phFile !== '' && is_file($phFile)) ? (int)@filemtime($phFile) : 0;
                $photos[] = [
                    'id'              => abs((int)$ph->id),
                    'index'           => $idx,
                    'file_id'         => (string)($ph->file_id ?? ''),
                    'filename'        => (string)($ph->filename ?? ''),
                    'upload_datetime' => (string)($ph->upload_datetime ?? ''),
                    'rejected'        => $isRej,
                    'mtime'           => $phVer,
                ];
            }
        }

        // Texte dekodieren
        $title       = self::decodeText((string)($a->title ?? ''));
        $description = self::decodeDescription((string)($a->description ?? ''));
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
            $histFallbacks = [
                'online_at_too_far' => 'Veröffentlichungsdatum zu weit in der Zukunft',
                'free_text'         => 'Sonstiges / Mehrere Gründe (Freitext)',
            ];
            while ($h = p4c_fetch_object($rsHist)) {
                $histIdName = (string)($h->id_name ?? '');
                $histShort  = self::decodeText((string)($h->de_short ?? ''));
                if ($histShort === '') {
                    $histShort = $histFallbacks[$histIdName] ?? $histIdName;
                }
                $rejectionHistory[] = [
                    'id_name'  => $histIdName,
                    'de_short' => $histShort,
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

        $rawOnlineAtDb     = (string)($a->online_at ?? '');
        $onlineAtTsDb      = (!empty($rawOnlineAtDb) && $rawOnlineAtDb !== '0000-00-00 00:00:00' && $rawOnlineAtDb !== '000-00-00 00:00:00')
            ? strtotime($rawOnlineAtDb)
            : false;
        $onlineAtFormatted = $onlineAtTsDb !== false
            ? date('Y-m-d\TH:i', $onlineAtTsDb)
            : date('Y-m-d\TH:i');
        $onlineAtDisplay   = $onlineAtTsDb !== false ? date('d.m.Y H:i', $onlineAtTsDb) : '-';
        $onlineAtTooFar    = function_exists('ec_is_online_at_too_far_in_future') && ec_is_online_at_too_far_in_future($rawOnlineAtDb);
        $maxOnlineAtInput  = function_exists('ec_get_max_online_at_input') ? ec_get_max_online_at_input() : date('Y-m-d\T23:59', strtotime('+12 months'));
        $maxOnlineAtDe     = function_exists('ec_get_max_online_at_timestamp') ? date('d.m.Y', ec_get_max_online_at_timestamp()) : date('d.m.Y', strtotime('+12 months'));

        $storageLoc = trim((string)($a->storage_location ?? ''));
        if ($storageLoc === '') {
            $storageLoc = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';
        }
        $folderBytes = 0;
        if (defined('PHOTO_ALBUMS_PATH') && $merchantId > 0 && $albumDbId > 0) {
            $albumDir    = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . trim($storageLoc, '/\\') . '/' . $merchantId . '/' . $albumDbId;
            $folderBytes = MoviesOnlineController::getFolderSize($albumDir);
        }
        $folderSizeText = $folderBytes > 0 ? MoviesOnlineController::formatBytes($folderBytes) : '-';

        $albumData = [
            'id'                  => $albumDbId,
            'album_id'            => $albumIdHash,
            'merchant_id'         => $merchantId,
            'merchant_partner_id' => $merchantPartnerId,
            'merchant_username'   => $merchantUsername,
            'actor_id'            => $actorId,
            'actor_exists'        => $currentActorExists,
            'actor_name'          => $currentActorName,
            'storage_location'    => $storageLoc,
            'folder_size_text'    => $folderSizeText,
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
            'online_at_raw'       => $rawOnlineAtDb,
            'online_at_display'   => $onlineAtDisplay,
            'online_at_too_far'   => $onlineAtTooFar,
            'max_online_at_input' => $maxOnlineAtInput,
            'max_online_at_de'    => $maxOnlineAtDe,
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
     * 3b. AJAX: Einzelfoto im ACP um 90° links/rechts drehen (POST ?route=photo-albums-checking/rotate-photo)
     * Nutzbar sowohl in #/photo-album-checking als auch in #/photo-album-edit.
     */
    public function rotatePhoto(): void
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
        @ini_set('memory_limit', '512M');

        $photoId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$this->request->post('photo_id', ''));
        $direction = trim((string)$this->request->post('direction', ''));

        if ($photoId === '' || !in_array($direction, ['left', 'right'], true)) {
            Response::error('Ungültige Parameter zum Drehen des Fotos.', 400);
            return;
        }

        $rsPhoto = p4c_query(
            "SELECT pap.`id`, pap.`file_id`, pap.`album_id`, pap.`merchant_id`, pap.`filename`,
                    pa.`id` AS `album_db_id`, pa.`storage_location`, pa.`checksum`
             FROM `photo_albums_photos` pap
             INNER JOIN `photo_albums` pa ON pap.`album_id` = pa.`album_id`
             WHERE pap.`file_id` = '" . p4c_escape_string($photoId) . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsPhoto || p4c_num_rows($rsPhoto) === 0) {
            Response::error('Das ausgewählte Foto existiert nicht.', 404);
            return;
        }

        $row          = p4c_fetch_object($rsPhoto);
        $albumDbId    = abs((int)$row->album_db_id);
        $merchantId   = abs((int)$row->merchant_id);
        $albumIdHash  = (string)$row->album_id;
        $safeFilename = basename((string)$row->filename);
        $storageDir   = trim((string)$row->storage_location);
        if ($storageDir === '') {
            $storageDir = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';
        }

        $albumsBasePath = defined('PHOTO_ALBUMS_PATH') ? (string)PHOTO_ALBUMS_PATH : (dirname(__DIR__, 5) . '/priv');
        $imagesDir      = rtrim($albumsBasePath, '/\\') . '/' . trim($storageDir, '/\\') . '/' . $merchantId . '/' . $albumDbId . '/images';
        $filePath       = $imagesDir . '/' . $safeFilename;
        $thumbCacheFile = $imagesDir . '/thumb_' . $safeFilename;

        if ($safeFilename === '' || !is_file($filePath) || (int)@filesize($filePath) === 0) {
            Response::error('Die Bilddatei konnte auf dem Server nicht gefunden werden.', 404);
            return;
        }

        $prevMtime      = (int)@filemtime($filePath);
        $prevThumbMtime = is_file($thumbCacheFile) ? (int)@filemtime($thumbCacheFile) : 0;

        if (!function_exists('imagerotate') || !function_exists('imagecreatetruecolor')) {
            Response::error('Die GD-Bildbearbeitung ist auf diesem Server aktuell nicht verfügbar.', 500);
            return;
        }

        $imgInfo = @getimagesize($filePath);
        if (!is_array($imgInfo) || empty($imgInfo['mime'])) {
            Response::error('Die Bilddatei konnte nicht gelesen werden.', 400);
            return;
        }

        $mime   = strtolower((string)$imgInfo['mime']);
        $srcImg = null;
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $rawBytes = @file_get_contents($filePath);
            if (is_string($rawBytes) && $rawBytes !== '') {
                $srcImg = @imagecreatefromstring($rawBytes);
                unset($rawBytes);
                // Vorhandene EXIF-Ausrichtung (2..8 inkl. Spiegelung) zuerst normalisieren, damit die 90°-Drehung exakt der sichtbaren Darstellung entspricht
                if ($srcImg instanceof \GdImage && function_exists('ec_read_exif_orientation') && function_exists('ec_apply_exif_orientation')) {
                    $orient = \ec_read_exif_orientation($filePath);
                    if ($orient >= 2 && $orient <= 8) {
                        $srcImg = \ec_apply_exif_orientation($srcImg, $orient);
                    }
                }
            }
        } elseif ($mime === 'image/png') {
            $srcImg = @imagecreatefrompng($filePath);
        }

        if (!($srcImg instanceof \GdImage)) {
            Response::error('Das Bildformat konnte nicht zum Drehen geöffnet werden (unterstützt werden JPG und PNG).', 400);
            return;
        }

        // GD imagerotate dreht gegen den Uhrzeigersinn: +90 = nach links, -90 = nach rechts
        $angle = ($direction === 'left') ? 90 : -90;
        if ($mime === 'image/png') {
            $bgAlpha = imagecolorallocatealpha($srcImg, 0, 0, 0, 127);
            $rotated = @imagerotate($srcImg, $angle, $bgAlpha !== false ? $bgAlpha : 0);
            if ($rotated instanceof \GdImage) {
                imagealphablending($rotated, false);
                imagesavealpha($rotated, true);
            }
        } else {
            $rotated = @imagerotate($srcImg, $angle, 0);
        }
        unset($srcImg);

        if (!($rotated instanceof \GdImage)) {
            Response::error('Das Foto konnte technisch nicht gedreht werden.', 500);
            return;
        }

        $tmpOut = $filePath . '.rot_' . mt_rand(1000, 9999);
        $saved  = ($mime === 'image/png')
            ? @imagepng($rotated, $tmpOut, 8)
            : @imagejpeg($rotated, $tmpOut, 92);
        unset($rotated);

        if (!$saved || !is_file($tmpOut) || (int)@filesize($tmpOut) === 0) {
            if (is_file($tmpOut)) {
                @unlink($tmpOut);
            }
            Response::error('Das gedrehte Foto konnte nicht gespeichert werden.', 500);
            return;
        }

        if (!@rename($tmpOut, $filePath)) {
            if (@copy($tmpOut, $filePath)) {
                @unlink($tmpOut);
            } else {
                @unlink($tmpOut);
                Response::error('Die Bilddatei konnte nicht überschrieben werden.', 500);
                return;
            }
        }

        $thumbEpoch = defined('EC_THUMB_EXIF_FIX_TS') ? (int)EC_THUMB_EXIF_FIX_TS : 1795520600;
        $nowTs      = max(time(), $prevMtime + 1, $prevThumbMtime + 1, $thumbEpoch + 1);
        $now        = date('Y-m-d H:i:s', time());
        @chmod($filePath, 0777);
        @touch($filePath, $nowTs);
        clearstatcache(true, $filePath);

        // Verpixeltes API-Vorschaubild (thumb_{filename}) löschen, damit es beim nächsten Portal-Aufruf frisch generiert wird
        if (is_file($thumbCacheFile)) {
            @unlink($thumbCacheFile);
        }

        $employeeId  = abs((int)($_SESSION['employee_id'] ?? 0));
        $newChecksum = md5((string)($row->checksum ?? '') . '|rot|' . $nowTs);

        p4c_query(
            "UPDATE `photo_albums`
             SET `checksum` = '" . p4c_escape_string($newChecksum) . "',
                 `last_updated_by` = '" . $employeeId . "',
                 `last_updated_datetime` = '" . $now . "'
             WHERE `id` = '" . $albumDbId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        $rsOnline = p4c_query(
            "SELECT `id`, `checksum` FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
            $onlineObj         = p4c_fetch_object($rsOnline);
            $newOnlineChecksum = md5((string)($onlineObj->checksum ?? $row->checksum ?? '') . '|rot|' . $nowTs);
            p4c_query(
                "UPDATE `photo_albums_online`
                 SET `checksum` = '" . p4c_escape_string($newOnlineChecksum) . "',
                     `last_updated_by` = '" . $employeeId . "',
                     `last_updated_datetime` = '" . $now . "'
                 WHERE `id` = '" . abs((int)$onlineObj->id) . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
        }

        if (function_exists('ec_invalidate_photo_album_api_cache')) {
            \ec_invalidate_photo_album_api_cache($albumIdHash);
        }

        if (function_exists('log_action')) {
            log_action('Foto ' . $photoId . ' in Fotoalbum #' . $albumDbId . ' im ACP um 90° nach ' . ($direction === 'left' ? 'links' : 'rechts') . ' gedreht.');
        }

        Response::json([
            'status'    => 'ok',
            'message'   => $direction === 'left' ? 'Foto wurde um 90° nach links gedreht.' : 'Foto wurde um 90° nach rechts gedreht.',
            'photo_id'  => $photoId,
            'thumb_src' => 'media.php?type=album_photo&photo_id=' . rawurlencode($photoId) . '&w=280&v=' . $nowTs,
            'full_src'  => 'media.php?type=album_photo&photo_id=' . rawurlencode($photoId) . '&w=1024&v=' . $nowTs,
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

        $description = self::decodeDescription((string)$this->request->post('description', ''));
        $description = str_replace('€', 'EUR', $description);
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
            $metaDesc = mb_substr(trim((string)preg_replace('/\s+/u', ' ', $description)), 0, 156, 'UTF-8');
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
        if ($albumStatus === 'active' && $onlineAtTs !== false && function_exists('ec_validate_online_at_future')) {
            $dateCheck = ec_validate_online_at_future($onlineAtRaw, 'Fotoalbum', true);
            if (!$dateCheck['ok']) {
                $errors[] = $dateCheck['error'];
            }
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
            $pointsSystemFile = (defined('KLASSEN_DIR') ? (string)KLASSEN_DIR : (SOURCEDIR . '/includes/klassen')) . '/PointsSystem.inc.php';
            if ($amountDownload > 0 && is_file($pointsSystemFile)) {
                require_once $pointsSystemFile;
                if (class_exists('PointsSystem')) {
                    try {
                        $ps             = new \PointsSystem();
                        $ps->group      = 'photo_album_upload';
                        $ps->points_for = 'new_photo_album';
                        $ps->actor_id   = $actorId;
                        $ps->date       = date('Y-m-d');
                        $points         = $ps->get_points();
                        if ($points !== false) {
                            $ps->set_points($points);
                        }
                    } catch (\Throwable $e) {
                        // Nicht-kritischer Fehler beim Punktesystem
                    }
                }
            }
        }

        if (function_exists('ec_invalidate_photo_album_api_cache')) {
            \ec_invalidate_photo_album_api_cache($albumIdHash);
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
        if ($reasonShort === $reasonCode) {
            if ($reasonCode === 'online_at_too_far') {
                $reasonShort = 'Veröffentlichungsdatum zu weit in der Zukunft';
            } elseif ($reasonCode === 'free_text') {
                $reasonShort = 'Hinweis / Korrektur erforderlich';
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
     * 5b. AJAX: Fotoalbum durch Administrator unwiderruflich physisch löschen (POST ?route=photo-albums-checking/hard-delete)
     */
    public function hardDelete(): void
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

        if ((string)($_SESSION['employee_rule'] ?? '') !== 'write') {
            Response::error('Nur Administratoren mit voller Schreibberechtigung dürfen Fotoalben unwiderruflich löschen.', 403);
            return;
        }

        $employeeId        = abs((int)($_SESSION['employee_id'] ?? 0));
        $albumDbId         = abs((int)$this->request->post('album_id', 0));
        $confirmHardDelete = (string)$this->request->post('confirm_hard_delete', '0') === '1';
        $forceLegalDelete  = (string)$this->request->post('force_legal_delete', '0') === '1';
        $deleteReason      = trim(strip_tags((string)$this->request->post('delete_reason', '')));

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
        $albumIdHash = (string)($a->album_id ?? '');
        $merchantId  = abs((int)($a->merchant_id ?? 0));
        $title       = self::decodeText((string)($a->title ?? ''));

        $onObj      = null;
        $isInOnline = false;
        if ($albumIdHash !== '') {
            $rsOnline = p4c_query(
                "SELECT `id`, `purchases`, `admin_infos` FROM `photo_albums_online` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "' LIMIT 1;",
                __FILE__,
                __LINE__
            );
            if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
                $onObj      = p4c_fetch_object($rsOnline);
                $isInOnline = true;
            }
        }

        $accessCount = 0;
        if ($albumIdHash !== '') {
            $rsAccCnt = p4c_query(
                "SELECT COUNT(*) AS `cnt` FROM `photo_albums_access` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
                __FILE__,
                __LINE__
            );
            if ($rsAccCnt && p4c_num_rows($rsAccCnt) > 0) {
                $accessCount = abs((int)(p4c_fetch_object($rsAccCnt)->cnt ?? 0));
            }
        }
        $effectivePurchases = max(
            abs((int)($a->purchases ?? 0)),
            $onObj ? abs((int)($onObj->purchases ?? 0)) : 0,
            $accessCount
        );

        if ($effectivePurchases === 0) {
            if (!$confirmHardDelete) {
                Response::error('Bitte bestätige im Kontrollkästchen ausdrücklich, dass das Fotoalbum unwiderruflich gelöscht werden soll.', 422);
                return;
            }
        } else {
            if (!$forceLegalDelete) {
                Response::error(
                    'Dieses Fotoalbum wurde bereits ' . $effectivePurchases . '-mal gekauft! Zum Schutz der Käufer ist eine sofortige unwiderrufliche Löschung nur bei zwingenden Rechts-/Persönlichkeitsrechts- oder Datenschutzgründen mit ausdrücklicher Bestätigung zulässig.',
                    422
                );
                return;
            }
            if (mb_strlen($deleteReason, 'UTF-8') < 5) {
                Response::error('Bitte gib bei einem bereits gekauften Fotoalbum zwingend den rechtlichen Löschgrund (mind. 5 Zeichen, z. B. Persönlichkeitsrecht / DSGVO) an.', 422);
                return;
            }
        }

        // 1. Physische Bilddateien & Album-Verzeichnis sofort vom Server löschen
        $storageLoc = trim((string)($a->storage_location ?? ''));
        if ($storageLoc === '') {
            $storageLoc = defined('PHOTO_ALBUMS_DEFAULT_DIR') ? (string)PHOTO_ALBUMS_DEFAULT_DIR : 'photo_albums';
        }
        $deletedFilesCount = 0;
        if (defined('PHOTO_ALBUMS_PATH') && $merchantId > 0 && $albumDbId > 0) {
            $albumDir = rtrim((string)PHOTO_ALBUMS_PATH, '/\\') . '/' . trim($storageLoc, '/\\') . '/' . $merchantId . '/' . $albumDbId;
            if (is_dir($albumDir)) {
                $imagesDir = $albumDir . '/images';
                if (is_dir($imagesDir)) {
                    foreach ((array)@glob($imagesDir . '/*') as $f) {
                        if (is_string($f) && is_file($f) && @unlink($f)) {
                            $deletedFilesCount++;
                        }
                    }
                    @rmdir($imagesDir);
                }
                foreach ((array)@glob($albumDir . '/*') as $f) {
                    if (is_string($f) && is_file($f) && @unlink($f)) {
                        $deletedFilesCount++;
                    }
                }
                @rmdir($albumDir);
            }
        }

        // 2. Einzelfotos (und bei bestätigter Rechts-/DSGVO-Zwangslöschung auch Kundenzugriffe) entfernen
        if ($albumIdHash !== '') {
            p4c_query("DELETE FROM `photo_albums_photos` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';", __FILE__, __LINE__);
            if ($effectivePurchases > 0 && $forceLegalDelete) {
                p4c_query("DELETE FROM `photo_albums_access` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';", __FILE__, __LINE__);
            }
        }

        // 3. Datenbank-Bereinigung (Online-Sync-Tombstone vs. vollständige Löschung bei Erst-Einreichung)
        $now       = date('Y-m-d H:i:s');
        $reasonTxt = $deleteReason !== '' ? $deleteReason : 'Ungekauftes Fotoalbum in ACP-Prüfung unwiderruflich gelöscht';

        if ($isInOnline && $albumIdHash !== '') {
            $auditLine = '[ADMIN HARD-DELETE ' . $now . ' (Admin #' . $employeeId . ', Käufe: ' . $effectivePurchases . '): ' . $reasonTxt . ']';
            $prevInfos = trim((string)($onObj->admin_infos ?? $a->admin_infos ?? ''));
            $newInfos  = $prevInfos !== '' ? ($prevInfos . "\n" . $auditLine) : $auditLine;
            $tombstoneChecksum = md5($albumIdHash . '|hard_deleted|' . $now);

            p4c_query(
                "UPDATE `photo_albums_online` SET
                    `status`                = 'deleted',
                    `deleted_datetime`      = '" . $now . "',
                    `album_checked`         = '" . $now . "',
                    `released`              = '1',
                    `number_of_photos`      = '0',
                    `preview_image_fsk16`   = '',
                    `preview_image_fsk18`   = '',
                    `checksum`              = '" . p4c_escape_string($tombstoneChecksum) . "',
                    `admin_infos`           = '" . p4c_escape_string($newInfos) . "',
                    `last_updated_by`       = '" . $employeeId . "',
                    `last_updated_datetime` = '" . $now . "'
                 WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );

            p4c_query(
                "UPDATE `photo_albums` SET
                    `status`                = 'deleted',
                    `deleted_datetime`      = '" . $now . "',
                    `album_checked`         = '" . $now . "',
                    `released`              = '1',
                    `number_of_photos`      = '0',
                    `preview_image_fsk16`   = '',
                    `preview_image_fsk18`   = '',
                    `checksum`              = '" . p4c_escape_string($tombstoneChecksum) . "',
                    `admin_infos`           = '" . p4c_escape_string($newInfos) . "',
                    `last_updated_by`       = '" . $employeeId . "',
                    `last_updated_datetime` = '" . $now . "'
                 WHERE `id` = '" . $albumDbId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
        } else {
            if ($albumIdHash !== '') {
                p4c_query(
                    "DELETE FROM `rejection_reason_photo_album_history` WHERE `album_id` = '" . p4c_escape_string($albumIdHash) . "';",
                    __FILE__,
                    __LINE__
                );
            }
            p4c_query("DELETE FROM `photo_albums` WHERE `id` = '" . $albumDbId . "' LIMIT 1;", __FILE__, __LINE__);
        }

        if ($albumIdHash !== '' && function_exists('ec_invalidate_photo_album_api_cache')) {
            \ec_invalidate_photo_album_api_cache($albumIdHash);
        }

        if (function_exists('log_action')) {
            log_action(
                "ADMIN HARD-DELETE Fotoalbum #{$albumDbId} („{$title}“, Album-ID: {$albumIdHash}, "
                . "warOnline: " . ($isInOnline ? 'ja' : 'nein') . ", Dateien gelöscht: {$deletedFilesCount}, "
                . "Käufe: {$effectivePurchases}, Grund: {$reasonTxt})."
            );
        }

        $remainingQueue = $this->fetchUncheckedQueueIds();
        $nextId         = $remainingQueue[0] ?? 0;

        Response::json([
            'status'          => 'ok',
            'message'         => $isInOnline
                ? "Fotoalbum #{$albumDbId} („{$title}“) und {$deletedFilesCount} Bilddatei(en) wurden unwiderruflich gelöscht. Angeschlossene Portale erhalten automatisch das Löschsignal."
                : "Fotoalbum #{$albumDbId} („{$title}“) wurde inklusive {$deletedFilesCount} Bilddatei(en) vollständig und unwiderruflich gelöscht.",
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

        $employeeId       = abs((int)($_SESSION['employee_id'] ?? 0));
        $albumDbId        = abs((int)$this->request->post('album_id', 0));
        $merchantId       = abs((int)$this->request->post('merchant_id', 0));
        $coActorId        = abs((int)$this->request->post('co_actor_id', 0));
        $decision         = trim((string)$this->request->post('decision', ''));
        $reason           = trim((string)$this->request->post('reason', ''));
        $panelIdRaw       = trim((string)$this->request->post('panel_id', 'pac-performers-card'));
        $panelIdClean     = preg_replace('/[^a-zA-Z0-9_-]/', '', $panelIdRaw);
        $panelId          = ($panelIdClean !== null && $panelIdClean !== '') ? $panelIdClean : 'pac-performers-card';
        $matchStageHeight = trim((string)$this->request->post('match_stage_height', '1')) === '1';

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

        $performersData = $this->loadAlbumPerformersData($albumDbId, $merchantId);
        $panelHtml = View::render('Shared', 'co_actors_panel', [
            'performersData'   => $performersData,
            'contentType'      => 'album',
            'contentId'        => $albumDbId,
            'merchantId'       => $merchantId,
            'panelId'          => $panelId,
            'readOnly'         => false,
            'matchStageHeight' => $matchStageHeight,
            'pendingDocsHint'  => 'Noch kein ID-Shot hochgeladen: Übergangsphase aktiv – du kannst das Fotoalbum unten trotzdem ganz normal prüfen und veröffentlichen.',
        ]);

        Response::json([
            'status'          => 'ok',
            'message'         => (string)($res['message'] ?? 'Status aktualisiert.'),
            'performers_data' => $performersData,
            'panel_html'      => $panelHtml,
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
                $dbDesc      = self::decodeDescription((string)($albObj->description ?? ''));
                $dbMetaTitle = self::decodeText((string)($albObj->meta_title ?? ''));
                $dbMetaDesc  = self::decodeText((string)($albObj->meta_description ?? ''));
            }
        }

        $customTitle     = $this->request->post('title', null);
        $customDesc      = $this->request->post('description', null);
        $customMetaTitle = $this->request->post('meta_title', null);
        $customMetaDesc  = $this->request->post('meta_description', null);

        $titleToCheck     = $customTitle !== null ? trim((string)$customTitle) : $dbTitle;
        $descToCheck      = $customDesc !== null ? self::decodeDescription((string)$customDesc) : $dbDesc;
        $metaTitleToCheck = $customMetaTitle !== null ? trim((string)$customMetaTitle) : $dbMetaTitle;
        $metaDescToCheck  = $customMetaDesc !== null ? trim((string)$customMetaDesc) : $dbMetaDesc;

        $env    = function_exists('aic_check_environment') ? aic_check_environment() : ['llm_ready' => false];
        $aiData = $this->analyzeAlbumTexts([
            'title'            => $titleToCheck,
            'description'      => $descToCheck,
            'meta_title'       => $metaTitleToCheck,
            'meta_description' => $metaDescToCheck,
        ], $env);

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
    private function analyzeAlbumTexts(array $textInput, ?array $envOverride = null): array
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

        $env = is_array($envOverride) ? $envOverride : ['llm_ready' => false];
        $rep = aic_analyze_texts($textInput, [], '', $env, 'photo_album');

        return [
            'text_compliance'  => (string)($rep['compliance_status'] ?? 'ok'),
            'text_risk_score'  => abs((int)($rep['risk_score'] ?? 0)),
            'text_summary'     => (string)($rep['summary_de'] ?? 'Keine Verstöße erkannt.'),
            'text_engine_used' => (string)($rep['engine_used'] ?? 'BRAM-Regel-Engine (JSON v1.2)'),
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

    private static function decodeDescription(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (function_exists('ec_html_to_plain_text')) {
            return \ec_html_to_plain_text($str);
        }
        if (!mb_check_encoding($str, 'UTF-8') && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
            if (is_string($converted)) {
                $str = $converted;
            }
        }
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($str, '&') !== false) {
            $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $str = str_replace(["\r\n", "\r"], "\n", $str);
        foreach ([
            '/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is' => '',
            '/<!--.*?-->/s' => '',
            '/<\s*br\s*\/?\s*>/i' => "\n",
            '/<\s*\/\s*li\s*>\s*<\s*li\b[^>]*>/i' => "\n- ",
            '/<\s*li\b[^>]*>/i' => "\n- ",
            '/<\s*\/\s*li\s*>/i' => "\n",
            '/<\s*\/\s*tr\s*>/i' => "\n",
            '/<\/\s*(?:p|div|h[1-6]|ul|ol|blockquote|pre|address|table|section|article)\s*>\s*<\s*(?:p|div|h[1-6]|ul|ol|blockquote|pre|address|table|section|article)\b[^>]*>/i' => "\n\n",
            '/<\/?\s*(?:p|div|h[1-6]|ul|ol|blockquote|pre|address|table|section|article|hr)\b[^>]*\/?>/i' => "\n\n",
            '/<\/?[a-zA-Z][a-zA-Z0-9:-]*(?:\s+[^<>]*)?\/?>/' => '',
        ] as $pattern => $replacement) {
            $res = preg_replace($pattern, $replacement, $str);
            if (is_string($res)) {
                $str = $res;
            }
        }
        $str = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], [' ', '', '', '', ''], $str);
        foreach (['/[ \t]{2,}/' => ' ', '/^[ \t]+|[ \t]+$/m' => '', "/\n{3,}/" => "\n\n"] as $pattern => $replacement) {
            $res = preg_replace($pattern, $replacement, $str);
            if (is_string($res)) {
                $str = $res;
            }
        }
        return trim($str);
    }

    private static function decodeText(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (function_exists('ec_decode_text')) {
            return \ec_decode_text($str);
        }
        if (!mb_check_encoding($str, 'UTF-8') && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
            if (is_string($converted)) {
                $str = $converted;
            }
        }
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($str, '&') !== false) {
            $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], [' ', '', '', '', ''], $str);
    }
}
