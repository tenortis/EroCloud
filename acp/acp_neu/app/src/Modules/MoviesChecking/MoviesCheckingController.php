<?php
declare(strict_types=1);

namespace App\Modules\MoviesChecking;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) - MoviesCheckingController
 * Steuert die Prüf-Warteschlange (#/movies-checking) sowie die Einzelprüfung & Freigabe (#/movie-checking?id=...).
 */
final class MoviesCheckingController
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

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 1. Warteschlangen-Übersicht aller zu prüfenden Filme (#/movies-checking)
     */
    public function list(): string
    {
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        if (function_exists('aic_ensure_table')) {
            aic_ensure_table();
        }
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }

        $env = function_exists('aic_check_environment') ? aic_check_environment() : ['llm_ready' => false];

        $rs = p4c_query(
            "SELECT m.*,
                    a.`username` AS `actor_name`,
                    ai.`subtitle_status`,
                    ai.`detected_language`,
                    ai.`text_check_status`,
                    ai.`text_compliance`,
                    ai.`text_risk_score`,
                    ai.`text_summary`,
                    ai.`text_violations_json`
             FROM `movies` m
             LEFT JOIN `actors` a ON a.`id` = m.`actor_id` AND a.`merchant_id` = m.`merchant_id`
             LEFT JOIN `movies_ai_analysis` ai ON ai.`movie_id` = m.`id`
             WHERE (m.`movie_checked` = '0000-00-00 00:00:00' OR m.`movie_checked` = '000-00-00 00:00:00')
               AND m.`released` = '1'
               AND m.`convert_status` > '1'
             ORDER BY m.`id` ASC;",
            __FILE__,
            __LINE__
        );

        $movies = [];
        $kpis = [
            'total'          => 0,
            'bram_ok'        => 0,
            'bram_attention' => 0,
            'co_actor_open'  => 0,
        ];

        if ($rs && p4c_num_rows($rs) > 0) {
            while ($row = p4c_fetch_object($rs)) {
                $movieId    = abs((int)$row->id);
                $merchantId = abs((int)$row->merchant_id);
                $actorId    = abs((int)$row->actor_id);
                $fileId     = (string)($row->file_id ?? '');

                $title       = self::decodeText((string)($row->title ?? ''));
                $description = self::decodeText((string)($row->description ?? ''));
                $metaTitle   = self::decodeText((string)($row->meta_title ?? ''));
                $metaDesc    = self::decodeText((string)($row->meta_description ?? ''));
                $actorName   = self::decodeText((string)($row->actor_name ?? ''));

                // Automatische BRAM-Erstberechnung falls noch nicht geprüft
                $textCheckStatus = abs((int)($row->text_check_status ?? 0));
                $textCompliance  = (string)($row->text_compliance ?? 'pending');
                $textRiskScore   = abs((int)($row->text_risk_score ?? 0));
                $textSummary     = self::decodeText((string)($row->text_summary ?? ''));
                $subtitleStatus  = abs((int)($row->subtitle_status ?? 0));
                $detectedLang    = (string)($row->detected_language ?? '');

                if ($textCheckStatus !== 2 && function_exists('aic_analyze_texts')) {
                    $rep = aic_analyze_texts([
                        'title'            => $title,
                        'description'      => $description,
                        'meta_title'       => $metaTitle,
                        'meta_description' => $metaDesc,
                    ], [], '', $env);

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
                             `text_duration_sec`    = '" . p4c_escape_string((string)$rep['duration_sec']) . "',
                             `updated_at`           = '" . $now . "';",
                        __FILE__,
                        __LINE__
                    );

                    $textCheckStatus = 2;
                    $textCompliance  = (string)$rep['compliance_status'];
                    $textRiskScore   = abs((int)$rep['risk_score']);
                    $textSummary     = (string)$rep['summary_de'];
                }

                // Drehpartner / 18+ ID-Shot Status ermitteln
                $performerSummary = [
                    'mode'         => 'solo_owner',
                    'count'        => 1,
                    'all_verified' => true,
                    'has_rejected' => false,
                    'has_review'   => false,
                ];
                if (class_exists('CoActorManager')) {
                    $perfInfo = \CoActorManager::getContentPerformers('movie', $movieId, $merchantId);
                    $performers = is_array($perfInfo['performers'] ?? null) ? $perfInfo['performers'] : [];
                    $allVer = true;
                    $hasRej = false;
                    $hasRev = false;
                    foreach ($performers as $p) {
                        $st = (string)($p['checked_status'] ?? '');
                        if ($st !== 'verified') {
                            $allVer = false;
                        }
                        if ($st === 'rejected' || empty($p['is_ready_for_use'])) {
                            $hasRej = true;
                        } elseif ($st === 'under_review') {
                            $hasRev = true;
                        }
                    }
                    $performerSummary = [
                        'mode'         => (string)($perfInfo['performer_mode'] ?? 'solo_owner'),
                        'count'        => max(1, count($performers)),
                        'all_verified' => $allVer,
                        'has_rejected' => $hasRej,
                        'has_review'   => $hasRev,
                    ];
                }

                $playtimeSec  = abs((int)($row->playtime_seconds ?? 0));
                $amountSec    = (float)($row->amount_second ?? 0.0);
                $totalCoins   = (int)round($playtimeSec * $amountSec);
                $langCode     = strtolower(trim((string)($row->movie_language ?: 'de')));
                $catMaster    = (string)($row->category_master ?: 'porn');
                $fsk16Num     = abs((int)($row->preview_image_fsk16 ?: 1));
                $fsk18Num     = abs((int)($row->preview_image_fsk18 ?: 1));

                $kpis['total']++;
                if ($textCompliance === 'ok') {
                    $kpis['bram_ok']++;
                } else {
                    $kpis['bram_attention']++;
                }
                if (!$performerSummary['all_verified']) {
                    $kpis['co_actor_open']++;
                }

                $movies[] = [
                    'id'                  => $movieId,
                    'merchant_id'         => $merchantId,
                    'actor_id'            => $actorId,
                    'actor_name'          => $actorName !== '' ? $actorName : 'Kein Profil gewählt',
                    'file_id'             => $fileId,
                    'title'               => $title !== '' ? $title : '(Ohne Titel)',
                    'status'              => (string)($row->status ?? 'active'),
                    'resolution'          => (string)($row->resolution ?? 'HD'),
                    'playtime_seconds'    => $playtimeSec,
                    'playtime_formatted'  => gmdate('H:i:s', $playtimeSec),
                    'amount_second'       => $amountSec,
                    'total_coins'         => $totalCoins,
                    'as_download'         => abs((int)($row->as_download ?? 0)) === 1,
                    'amount_download'     => abs((int)($row->amount_download ?? 0)),
                    'movie_language'      => $langCode,
                    'language_label'      => self::LANGUAGE_LABELS[$langCode] ?? strtoupper($langCode),
                    'category_master'     => $catMaster,
                    'online_at'           => (string)($row->online_at ?? ''),
                    'create_datetime'     => (string)($row->create_datetime ?? ''),
                    'preview_image_fsk16' => $fsk16Num,
                    'preview_image_fsk18' => $fsk18Num,
                    'bram_compliance'     => $textCompliance,
                    'bram_risk_score'     => $textRiskScore,
                    'bram_summary'        => $textSummary,
                    'subtitle_status'     => $subtitleStatus,
                    'detected_language'   => $detectedLang,
                    'performers'          => $performerSummary,
                ];
            }
        }

        return View::render('MoviesChecking', 'list', [
            'movies' => $movies,
            'kpis'   => $kpis,
        ]);
    }

    /**
     * 2. Einzelprüfung & Freigabe-Ansicht für einen Film (#/movie-checking?id=...)
     */
    public function detail(): string
    {
        global $mysql;

        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        if (function_exists('aic_ensure_table')) {
            aic_ensure_table();
        }
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }

        $movieId = abs((int)$this->request->get('id', 0));
        if ($movieId <= 0) {
            return $this->renderNotFound('Es wurde keine gültige Film-ID übergeben.');
        }

        $rsMovie = p4c_query("SELECT * FROM `movies` WHERE `id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            return $this->renderNotFound("Der angeforderte Film mit der ID #{$movieId} existiert nicht in der Datenbank.");
        }

        $m = p4c_fetch_object($rsMovie);
        $merchantId = abs((int)$m->merchant_id);
        $actorId    = abs((int)$m->actor_id);
        $fileId     = (string)$m->file_id;

        // Warteschlangen-Navigation (Vorheriger / Nächster Film in Prüfung)
        $queueIds = $this->fetchUncheckedQueueIds();
        $queuePos = array_search($movieId, $queueIds, true);
        $prevMovieId = ($queuePos !== false && $queuePos > 0) ? $queueIds[$queuePos - 1] : 0;
        $nextMovieId = ($queuePos !== false && isset($queueIds[$queuePos + 1])) ? $queueIds[$queuePos + 1] : ($queueIds[0] ?? 0);
        if ($nextMovieId === $movieId) {
            $nextMovieId = 0;
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
        $merchantActors = [];
        $currentActorExists = false;
        $currentActorName   = '';
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
                    $currentActorExists = true;
                    $currentActorName   = $aname;
                }
                $merchantActors[] = [
                    'id'               => $aid,
                    'username'         => $aname,
                    'actor_categories' => (string)($a->actor_categories ?? ''),
                    'status'           => (string)($a->status ?? ''),
                ];
            }
        }

        // Prüfen, ob der Film bereits in movies_online existiert
        $onlineInfo = [
            'is_in_movies_online' => false,
            'online_status'       => '',
            'purchase_count'      => 0,
            'last_buy_datetime'   => '',
            'last_view_datetime'  => '',
        ];
        $rsOnline = p4c_query(
            "SELECT `id`, `status` FROM `movies_online` WHERE `file_id` = '" . p4c_escape_string($fileId) . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsOnline && p4c_num_rows($rsOnline) > 0) {
            $onObj = p4c_fetch_object($rsOnline);
            $onlineInfo['is_in_movies_online'] = true;
            $onlineInfo['online_status']       = (string)($onObj->status ?? 'active');

            $rsAcc = p4c_query(
                "SELECT `buy_timestamp`, `access_token_datetime`
                 FROM `movies_access`
                 WHERE `movie_id` = '" . p4c_escape_string($fileId) . "'
                 ORDER BY `buy_timestamp` DESC;",
                __FILE__,
                __LINE__
            );
            if ($rsAcc && p4c_num_rows($rsAcc) > 0) {
                $onlineInfo['purchase_count'] = (int)p4c_num_rows($rsAcc);
                $firstAcc = p4c_fetch_object($rsAcc);
                $onlineInfo['last_buy_datetime']  = (string)($firstAcc->buy_timestamp ?? '');
                $onlineInfo['last_view_datetime'] = (string)($firstAcc->access_token_datetime ?? '');
            }
        }

        // Dateipfad & Existenz von Video + Custom-Thumbs (11 = FSK16, 12 = FSK18) prüfen
        $movieDir = rtrim((string)MOVIES_PATH, '/\\') . '/'
            . (string)$m->storage_location . '/'
            . $merchantId . '/'
            . $movieId;
        $videoFilePath   = $movieDir . '/' . (string)$m->filename;
        $videoFileExists = is_file($videoFilePath);
        $hasCustomFsk16  = is_file($movieDir . '/thumb_' . $movieId . '_' . $fileId . '_11.jpg');
        $hasCustomFsk18  = is_file($movieDir . '/thumb_' . $movieId . '_' . $fileId . '_12.jpg');

        // Angeschlossene Partner-Webseiten laden (für visible_for_website)
        $merchantDomains = [];
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

        // Film-Texte dekodieren
        $title       = self::decodeText((string)($m->title ?? ''));
        $description = self::decodeText((string)($m->description ?? ''));
        $metaTitle   = self::decodeText((string)($m->meta_title ?? ''));
        $metaDesc    = self::decodeText((string)($m->meta_description ?? ''));
        $seoUrl      = self::decodeText((string)($m->seo_url ?? ''));
        $adminInfos  = self::decodeText((string)($m->admin_infos ?? ''));

        // Kategorien aus movie_categories laden und gruppieren
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

        // Ablehnungsgründe & Ablehnungshistorie laden
        $rejectionReasons = [];
        $rsRej = p4c_query("SELECT `id_name`, `de_short`, `de_long` FROM `rejection_reason_movie` ORDER BY `de_short` ASC;", __FILE__, __LINE__);
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
             FROM `rejection_reason_movie_history` h
             LEFT JOIN `rejection_reason_movie` r ON r.`id_name` = h.`id_name`
             WHERE h.`movie_file_id` = '" . p4c_escape_string($fileId) . "'
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

        // Mitwirkende Personen & 18+ ID-Shot Daten laden
        $performersData = $this->loadMoviePerformersData($movieId, $merchantId);

        // BRAM & KI-Analyse laden (und ggf. initial berechnen)
        $aiData = $this->loadAndEnsureMovieAiData($movieId, $merchantId, $fileId, $movieDir, [
            'title'            => $title,
            'description'      => $description,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
        ]);

        $playtimeSec = abs((int)($m->playtime_seconds ?? 0));
        $amountSec   = (float)($m->amount_second ?? 0.0);

        $movieData = [
            'id'                  => $movieId,
            'merchant_id'         => $merchantId,
            'merchant_partner_id' => $merchantPartnerId,
            'merchant_username'   => $merchantUsername,
            'actor_id'            => $actorId,
            'actor_exists'        => $currentActorExists,
            'actor_name'          => $currentActorName,
            'file_id'             => $fileId,
            'filename'            => (string)($m->filename ?? ''),
            'storage_location'    => (string)($m->storage_location ?? ''),
            'resolution'          => (string)($m->resolution ?? 'HD'),
            'playtime_seconds'    => $playtimeSec,
            'playtime_formatted'  => gmdate('H:i:s', $playtimeSec),
            'quality'             => abs((int)($m->quality ?? 3)),
            'preview'             => abs((int)($m->preview ?? 1)),
            'preview_image_fsk16' => abs((int)($m->preview_image_fsk16 ?: 1)),
            'preview_image_fsk18' => abs((int)($m->preview_image_fsk18 ?: 1)),
            'has_custom_fsk16'    => $hasCustomFsk16,
            'has_custom_fsk18'    => $hasCustomFsk18,
            'video_file_exists'   => $videoFileExists,
            'title'               => $title,
            'description'         => $description,
            'meta_title'          => $metaTitle,
            'meta_description'    => $metaDesc,
            'seo_url'             => $seoUrl,
            'movie_language'      => strtolower(trim((string)($m->movie_language ?: 'de'))),
            'category_master'     => (string)($m->category_master ?: 'porn'),
            'selected_slaves'     => $selectedSlaves,
            'online_at'           => (string)($m->online_at ?: date('Y-m-d')),
            'amount_second'       => $amountSec,
            'total_coins'         => (int)round($playtimeSec * $amountSec),
            'as_download'         => abs((int)($m->as_download ?? 0)) === 1,
            'amount_download'     => abs((int)($m->amount_download ?: 10)),
            'status'              => (string)($m->status ?: 'active'),
            'visible_for_website' => (string)($m->visible_for_website ?: 'public'),
            'admin_infos'         => $adminInfos,
            'released'            => abs((int)($m->released ?? 0)),
            'movie_checked'       => (string)($m->movie_checked ?? ''),
            'create_datetime'     => (string)($m->create_datetime ?? ''),
        ];

        return View::render('MoviesChecking', 'detail', [
            'movie'            => $movieData,
            'onlineInfo'       => $onlineInfo,
            'merchantActors'   => $merchantActors,
            'merchantDomains'  => $merchantDomains,
            'categoriesByType' => $categoriesByType,
            'rejectionReasons' => $rejectionReasons,
            'rejectionHistory' => $rejectionHistory,
            'performersData'   => $performersData,
            'aiData'           => $aiData,
            'languages'        => self::LANGUAGE_LABELS,
            'employeeRule'     => (string)($_SESSION['employee_rule'] ?? ''),
            'queueTotal'       => count($queueIds),
            'queuePosition'    => $queuePos !== false ? ($queuePos + 1) : 0,
            'prevMovieId'      => $prevMovieId,
            'nextMovieId'      => $nextMovieId,
        ]);
    }

    /**
     * 3. AJAX: Film prüfen, speichern & veröffentlichen (POST ?r=movies-checking/approve)
     */
    public function approve(): void
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

        $employeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $employeeRule = (string)($_SESSION['employee_rule'] ?? '');
        $movieId      = abs((int)$this->request->post('movie_id', 0));

        if ($movieId <= 0) {
            Response::error('Ungültige Film-ID.', 400);
            return;
        }

        $rsMovie = p4c_query("SELECT * FROM `movies` WHERE `id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Der Film wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $m          = p4c_fetch_object($rsMovie);
        $merchantId = abs((int)$m->merchant_id);
        $fileId     = (string)$m->file_id;
        $playtime   = abs((int)$m->playtime_seconds);

        $errors = [];

        // 1. Darsteller validieren
        $actorId = abs((int)$this->request->post('actor_id', $m->actor_id));
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
            $errors[] = 'Bitte wähle mindestens eine Unterkategorie für den Film aus.';
        }

        // 3. Titel & Beschreibung bereinigen & validieren
        $title = trim((string)$this->request->post('title', ''));
        if (defined('REPLACE_TITLE_CHARS')) {
            $title = str_replace(explode(',', (string)REPLACE_TITLE_CHARS), '', $title);
        }
        $title = str_replace(['€', "\xe2\x80\x8b"], ['Euro', ''], $title);
        $title = trim(strip_tags($title));
        if ($title === '') {
            $errors[] = 'Bitte gib einen Filmtitel ein.';
        }

        $description = trim((string)$this->request->post('description', ''));
        $description = str_replace(['€', "\xe2\x80\x8b"], ['Euro', ''], $description);
        $description = trim(strip_tags($description, '<a><br><p><ul><ol><li><b><i><h1><h2><h3><h4><h5><h6><u><em><strong><pre><address>'));
        if ($description === '') {
            $errors[] = 'Bitte gib eine Filmbeschreibung ein.';
        }

        // 4. Online-ab Datum validieren
        $onlineAtRaw = trim((string)$this->request->post('online_at', ''));
        $onlineAtTs  = $onlineAtRaw !== '' ? strtotime($onlineAtRaw) : false;
        if ($onlineAtTs === false) {
            $errors[] = 'Bitte gib ein gültiges Online-ab-Datum an.';
            $onlineAt = date('Y-m-d H:i:s');
        } else {
            $onlineAt = date('Y-m-d H:i:s', $onlineAtTs);
        }

        // 5. Streaming-Preis & Download-Aufschlag validieren
        $amountSecondRaw = str_replace(',', '.', trim((string)$this->request->post('amount_second', '0.00')));
        $amountSecondVal = (float)$amountSecondRaw;
        if (!is_numeric($amountSecondRaw) || $amountSecondVal < 0.0 || $amountSecondVal > 100.0) {
            $errors[] = 'Bitte wähle einen gültigen Streaming-Preis pro Sekunde.';
        }
        $amountSecondFormatted = number_format($amountSecondVal, 1, '.', '');
        $amountWebmaster = abs((int)($m->amount_webmaster ?? 0));

        $asDownload = abs((int)$this->request->post('as_download', 0)) === 1 ? 1 : 0;
        $amountDownload = abs((int)$this->request->post('amount_download', 0));
        if ($amountDownload > 2500) {
            $amountDownload = 150;
        }

        // 6. Vorschaubilder (FSK16: 1..11, FSK18: 1..10 oder 12)
        $fsk16 = abs((int)$this->request->post('fsk16', 1));
        $fsk18 = abs((int)$this->request->post('fsk18', 1));
        if ($fsk16 < 1 || $fsk16 > 11) {
            $errors[] = 'Bitte wähle ein gültiges FSK16-Vorschaubild (1–11).';
        }
        if ($fsk18 < 1 || ($fsk18 > 10 && $fsk18 !== 12)) {
            $errors[] = 'Bitte wähle ein gültiges FSK18-Vorschaubild (1–10 oder 12).';
        }

        // 7. SEO-Felder
        $metaTitle = trim(strip_tags((string)$this->request->post('meta_title', '')));
        $metaDesc  = trim(strip_tags((string)$this->request->post('meta_description', '')));
        if (mb_strlen($metaTitle) > 65) {
            $metaTitle = mb_substr($metaTitle, 0, 65, 'UTF-8');
        }
        if (mb_strlen($metaDesc) > 156) {
            $metaDesc = mb_substr($metaDesc, 0, 156, 'UTF-8');
        }

        $seoUrlRaw = trim((string)$this->request->post('seo_url', ''));
        $seoUrl = function_exists('seo_url')
            ? (string)seo_url($seoUrlRaw !== '' ? $seoUrlRaw : $title)
            : strtolower((string)preg_replace('/[^a-zA-Z0-9-]+/', '-', $seoUrlRaw !== '' ? $seoUrlRaw : $title));

        // 8. Sprache, Qualität, Vorschau, Sichtbarkeit & Status
        $movieLanguage = strtolower(trim((string)$this->request->post('movie_language', 'de')));
        if (!isset(self::LANGUAGE_LABELS[$movieLanguage])) {
            $errors[] = 'Bitte wähle eine gültige Sprache für den Film.';
        }

        $qualityRaw = strtolower(trim((string)$this->request->post('quality', 'hd')));
        if (!in_array($qualityRaw, ['sd', 'hd', 'fhd', '2k', '4k', '6k'], true)) {
            $qualityRaw = 'hd';
        }
        $quality = $qualityRaw;
        $preview = abs((int)$this->request->post('preview', 0)) === 1 ? 1 : 0;

        $visibleForWebsite = trim((string)$this->request->post('visible_for_website', 'public'));
        if (in_array('poppers', $validCategories, true) && $visibleForWebsite === 'public') {
            $errors[] = 'Filme mit der Kategorie „Poppers“ dürfen nicht auf „Alle Webseiten (public)“ gestellt werden.';
        }

        $allowedStatuses = ['active', 'blocked'];
        if ($employeeRule === 'write') {
            $allowedStatuses[] = 'deleted';
        }
        $movieStatus = trim((string)$this->request->post('movie_status', 'active'));
        if (!in_array($movieStatus, $allowedStatuses, true)) {
            $errors[] = 'Ungültiger Film-Status ausgewählt.';
        }

        $adminInfos = trim(strip_tags((string)$this->request->post('admin_infos', '')));

        // 9. Mitwirkende Personen / 18+ ID-Shot Prüfung
        if (class_exists('CoActorManager')) {
            $perfCheck = \CoActorManager::getContentPerformers('movie', $movieId, $merchantId);
            foreach ($perfCheck['performers'] ?? [] as $pCheck) {
                if (($pCheck['checked_status'] ?? '') === 'rejected') {
                    $errors[] = 'Mindestens eine mitwirkende Person im Film hat einen abgelehnten ID-Shot ('
                        . ($pCheck['display_label'] ?? '')
                        . '). Bitte lehne den Film ab oder korrigiere zuerst den Status der Person.';
                    break;
                }
            }
        }

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 422);
            return;
        }

        // Alle noch auf 'under_review' stehenden, vollständigen Mitwirkenden bei Film-Freigabe automatisch verifizieren
        if (class_exists('CoActorManager')) {
            $perfCheck = \CoActorManager::getContentPerformers('movie', $movieId, $merchantId);
            foreach ($perfCheck['performers'] ?? [] as $pAuto) {
                if (!empty($pAuto['is_ready_for_use']) && ($pAuto['checked_status'] ?? '') === 'under_review') {
                    \CoActorManager::acpReviewCoActor((int)$pAuto['id'], $merchantId, 'verify', '', $employeeId);
                }
            }
        }

        $categorySlaveStr = implode(',', $validCategories);
        $now = date('Y-m-d H:i:s');

        // Checksumme berechnen
        $checksumData = [
            'merchant_id'         => $merchantId,
            'actor_id'            => $actorId,
            'quality'             => $quality,
            'preview'             => $preview,
            'preview_image_fsk16' => $fsk16,
            'preview_image_fsk18' => $fsk18,
            'title'               => $title,
            'description'         => $description,
            'meta_title'          => $metaTitle,
            'meta_description'    => $metaDesc,
            'seo_url'             => $seoUrl,
            'movie_language'      => $movieLanguage,
            'category_master'     => $categoryMaster,
            'category_slave'      => $categorySlaveStr,
            'online_at'           => $onlineAt,
            'amount_second'       => $amountSecondFormatted,
            'amount_own'          => (string)($m->amount_own ?? '0.00'),
            'amount_webmaster'    => $amountWebmaster,
            'as_download'         => $asDownload,
            'amount_download'     => $amountDownload,
            'status'              => $movieStatus,
            'visible_for_website' => $visibleForWebsite,
        ];

        $checksum = function_exists('movie_checksum') ? (string)movie_checksum($checksumData) : md5($fileId . $now);

        // Haupttabelle movies aktualisieren
        p4c_query(
            "UPDATE `movies` SET
                `actor_id`            = '" . $actorId . "',
                `quality`             = '" . $quality . "',
                `preview`             = '" . $preview . "',
                `preview_image_fsk16` = '" . $fsk16 . "',
                `preview_image_fsk18` = '" . $fsk18 . "',
                `title`               = '" . p4c_escape_string($title) . "',
                `description`         = '" . p4c_escape_string($description) . "',
                `meta_title`          = '" . p4c_escape_string($metaTitle) . "',
                `meta_description`    = '" . p4c_escape_string($metaDesc) . "',
                `seo_url`             = '" . p4c_escape_string($seoUrl) . "',
                `movie_language`      = '" . p4c_escape_string($movieLanguage) . "',
                `category_master`     = '" . p4c_escape_string($categoryMaster) . "',
                `category_slave`      = '" . p4c_escape_string($categorySlaveStr) . "',
                `online_at`           = '" . p4c_escape_string($onlineAt) . "',
                `amount_second`       = '" . p4c_escape_string($amountSecondFormatted) . "',
                `amount_own`          = '0.00',
                `amount_webmaster`    = '" . p4c_escape_string($amountWebmaster) . "',
                `as_download`         = '" . $asDownload . "',
                `amount_download`     = '" . $amountDownload . "',
                `status`              = '" . p4c_escape_string($movieStatus) . "',
                `visible_for_website` = '" . p4c_escape_string($visibleForWebsite) . "',
                `admin_infos`         = '" . p4c_escape_string($adminInfos) . "',
                `movie_checked`       = '" . $now . "',
                `released`            = '1',
                `released_from`       = '" . $employeeId . "',
                `released_datetime`   = '" . $now . "',
                `checksum`            = '" . p4c_escape_string($checksum) . "'
             WHERE `id` = '" . $movieId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        // movies_online synchronisieren
        $rsCheckOnline = p4c_query(
            "SELECT `id` FROM `movies_online` WHERE `file_id` = '" . p4c_escape_string($fileId) . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if ($rsCheckOnline && p4c_num_rows($rsCheckOnline) > 0) {
            p4c_query(
                "UPDATE `movies_online` SET
                    `actor_id`            = '" . $actorId . "',
                    `quality`             = '" . $quality . "',
                    `preview`             = '" . $preview . "',
                    `preview_image_fsk16` = '" . $fsk16 . "',
                    `preview_image_fsk18` = '" . $fsk18 . "',
                    `title`               = '" . p4c_escape_string($title) . "',
                    `description`         = '" . p4c_escape_string($description) . "',
                    `meta_title`          = '" . p4c_escape_string($metaTitle) . "',
                    `meta_description`    = '" . p4c_escape_string($metaDesc) . "',
                    `seo_url`             = '" . p4c_escape_string($seoUrl) . "',
                    `movie_language`      = '" . p4c_escape_string($movieLanguage) . "',
                    `category_master`     = '" . p4c_escape_string($categoryMaster) . "',
                    `category_slave`      = '" . p4c_escape_string($categorySlaveStr) . "',
                    `online_at`           = '" . p4c_escape_string($onlineAt) . "',
                    `amount_second`       = '" . p4c_escape_string($amountSecondFormatted) . "',
                    `amount_own`          = '0.00',
                    `amount_webmaster`    = '" . p4c_escape_string($amountWebmaster) . "',
                    `as_download`         = '" . $asDownload . "',
                    `amount_download`     = '" . $amountDownload . "',
                    `status`              = '" . p4c_escape_string($movieStatus) . "',
                    `visible_for_website` = '" . p4c_escape_string($visibleForWebsite) . "',
                    `checksum`            = '" . p4c_escape_string($checksum) . "'
                 WHERE `file_id` = '" . p4c_escape_string($fileId) . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
        } elseif ($movieStatus === 'active') {
            p4c_query(
                "INSERT INTO `movies_online`
                 (`merchant_id`, `actor_id`, `file_id`, `filename`, `storage_location`, `resolution`,
                  `playtime_seconds`, `playtime_string`, `quality`, `preview`, `preview_image_fsk16`, `preview_image_fsk18`,
                  `title`, `description`, `meta_title`, `meta_description`, `seo_url`, `movie_language`,
                  `category_master`, `category_slave`, `online_at`, `amount_second`, `amount_own`, `amount_webmaster`,
                  `as_download`, `amount_download`, `status`, `visible_for_website`, `create_datetime`, `checksum`)
                 VALUES (
                    '" . $merchantId . "',
                    '" . $actorId . "',
                    '" . p4c_escape_string($fileId) . "',
                    '" . p4c_escape_string((string)$m->filename) . "',
                    '" . p4c_escape_string((string)$m->storage_location) . "',
                    '" . p4c_escape_string((string)$m->resolution) . "',
                    '" . $playtime . "',
                    '" . p4c_escape_string((string)$m->playtime_string) . "',
                    '" . $quality . "',
                    '" . $preview . "',
                    '" . $fsk16 . "',
                    '" . $fsk18 . "',
                    '" . p4c_escape_string($title) . "',
                    '" . p4c_escape_string($description) . "',
                    '" . p4c_escape_string($metaTitle) . "',
                    '" . p4c_escape_string($metaDesc) . "',
                    '" . p4c_escape_string($seoUrl) . "',
                    '" . p4c_escape_string($movieLanguage) . "',
                    '" . p4c_escape_string($categoryMaster) . "',
                    '" . p4c_escape_string($categorySlaveStr) . "',
                    '" . p4c_escape_string($onlineAt) . "',
                    '" . p4c_escape_string($amountSecondFormatted) . "',
                    '0.00',
                    '" . p4c_escape_string($amountWebmaster) . "',
                    '" . $asDownload . "',
                    '" . $amountDownload . "',
                    '" . p4c_escape_string($movieStatus) . "',
                    '" . p4c_escape_string($visibleForWebsite) . "',
                    '" . p4c_escape_string((string)$m->create_datetime) . "',
                    '" . p4c_escape_string($checksum) . "'
                 );",
                __FILE__,
                __LINE__
            );

            // Punktesystem für neuen kostenpflichtigen Film gutschreiben
            if ($amountSecondVal > 0 && is_file(KLASSEN_DIR . '/PointsSystem.inc.php')) {
                require_once KLASSEN_DIR . '/PointsSystem.inc.php';
                if (class_exists('PointsSystem')) {
                    try {
                        $ps = new \PointsSystem();
                        $ps->MerchantID($merchantId);
                        $ps->ActorID($actorId);
                        $ps->AddPoints([
                            'group'      => 'movie_upload',
                            'points_for' => 'new_movie',
                            'info'       => 'ID: #' . $movieId . ' / File-ID: ' . $fileId,
                        ]);
                    } catch (\Throwable $e) {
                        // Nicht-kritischer Fehler beim Punktesystem
                    }
                }
            }
        }

        // BRAM-Analyse mit den gespeicherten Texten aktualisieren
        require_once SOURCEDIR . '/includes/ai_compliance_core.inc.php';
        $movieDir = rtrim((string)MOVIES_PATH, '/\\') . '/' . (string)$m->storage_location . '/' . $merchantId . '/' . $movieId;
        $this->loadAndEnsureMovieAiData($movieId, $merchantId, $fileId, $movieDir, [
            'title'            => $title,
            'description'      => $description,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
        ], true);

        // Media-Cache invalidieren
        unset($_SESSION['erocloud_acp_media_cache']['fid_' . $fileId], $_SESSION['erocloud_acp_media_cache']['mid_' . $movieId]);

        log_action("Film geprüft & gespeichert (ID: #{$movieId}, File-ID: {$fileId}, Status: {$movieStatus}).");

        $remainingQueue = $this->fetchUncheckedQueueIds();
        $nextId = $remainingQueue[0] ?? 0;

        Response::json([
            'status'          => 'ok',
            'message'         => "Der Film #{$movieId} („{$title}“) wurde erfolgreich geprüft und freigegeben!",
            'next_movie_id'   => $nextId,
            'remaining_count' => count($remainingQueue),
        ]);
    }

    /**
     * 4. AJAX: Film ablehnen (POST ?r=movies-checking/reject)
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

        $movieId      = abs((int)$this->request->post('movie_id', 0));
        $reasonCode   = trim((string)$this->request->post('rejection_reason', '0'));
        $reasonText   = trim(strip_tags((string)$this->request->post('rejection_text', '')));

        if ($movieId <= 0) {
            Response::error('Ungültige Film-ID.', 400);
            return;
        }
        if ($reasonCode === '' || $reasonCode === '0') {
            Response::error('Bitte wähle einen Ablehnungsgrund aus der Liste aus.', 422);
            return;
        }
        if ($reasonText === '') {
            Response::error('Bitte gib eine Begründung für die Ablehnung des Films ein.', 422);
            return;
        }

        $rsMovie = p4c_query("SELECT `id`, `file_id`, `title` FROM `movies` WHERE `id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Der Film wurde nicht in der Datenbank gefunden.', 404);
            return;
        }

        $m          = p4c_fetch_object($rsMovie);
        $fileId     = (string)$m->file_id;
        $title      = self::decodeText((string)($m->title ?? ''));
        $now        = date('Y-m-d H:i:s');
        $employeeId = abs((int)($_SESSION['employee_id'] ?? 0));

        p4c_query(
            "INSERT INTO `rejection_reason_movie_history`
             (`employee_id`, `id_name`, `movie_file_id`, `text`, `datetime`)
             VALUES (
                '" . $employeeId . "',
                '" . p4c_escape_string($reasonCode) . "',
                '" . p4c_escape_string($fileId) . "',
                '" . p4c_escape_string($reasonText) . "',
                '" . $now . "'
             );",
            __FILE__,
            __LINE__
        );

        p4c_query(
            "UPDATE `movies` SET
                `released`              = '2',
                `last_updated_by`       = '" . $employeeId . "',
                `last_updated_datetime` = '" . $now . "'
             WHERE `id` = '" . $movieId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        log_action("Film abgelehnt (ID: #{$movieId}, File-ID: {$fileId}, Grund: {$reasonCode}).");

        $remainingQueue = $this->fetchUncheckedQueueIds();
        $nextId = $remainingQueue[0] ?? 0;

        Response::json([
            'status'          => 'ok',
            'message'         => "Der Film #{$movieId} („{$title}“) wurde abgelehnt.",
            'next_movie_id'   => $nextId,
            'remaining_count' => count($remainingQueue),
        ]);
    }

    /**
     * 5. AJAX: 1-Klick Verifizierung oder Ablehnung eines Drehpartners / ID-Shots (POST ?r=movies-checking/co-actor-action)
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
        $movieId    = abs((int)$this->request->post('movie_id', 0));
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

        log_action("Drehpartner / ID-Shot #{$coActorId} (MID #{$merchantId}) bei Filmprüfung #{$movieId}: {$decision}.");

        $performersData = $this->loadMoviePerformersData($movieId, $merchantId);
        Response::json([
            'status'          => 'ok',
            'message'         => (string)($res['message'] ?? 'Status aktualisiert.'),
            'performers_data' => $performersData,
        ]);
    }

    /**
     * 6. AJAX: BRAM-Textprüfung oder Whisper-Transkription ausführen (POST ?r=movies-checking/ai-action)
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
        aic_ensure_table();
        $env = aic_check_environment();

        // Session-Lock freigeben, damit längere Whisper-Läufe keine anderen Requests blockieren
        @session_write_close();

        $movieId   = abs((int)$this->request->post('movie_id', 0));
        $subAction = trim((string)$this->request->post('sub_action', 'run_text_check'));

        $rsMovie = p4c_query("SELECT * FROM `movies` WHERE `id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsMovie || p4c_num_rows($rsMovie) === 0) {
            Response::error('Film nicht gefunden.', 404);
            return;
        }
        $m          = p4c_fetch_object($rsMovie);
        $merchantId = abs((int)$m->merchant_id);
        $fileId     = (string)$m->file_id;
        $movieDir   = rtrim((string)MOVIES_PATH, '/\\') . '/' . (string)$m->storage_location . '/' . $merchantId . '/' . $movieId;

        if ($subAction === 'run_whisper') {
            $details = aic_get_movie_details($movieId);
            if ($details && !empty($details['mp4_exists'])) {
                $pipeRes = aic_process_single_movie_pipeline($movieId, $env, [
                    'audio_limit_sec' => 0,
                    'whisper_threads' => 16,
                    'run_text_after'  => true,
                ]);
                if (($pipeRes['status'] ?? '') !== 'ok') {
                    Response::error((string)($pipeRes['movie']['ai']['last_error'] ?? 'Fehler bei der Whisper Audio-Analyse.'), 500);
                    return;
                }
            } else {
                Response::error('Die lokale MP4-Videodatei ist auf diesem System nicht vorhanden, daher kann Whisper hier keine Audiospur extrahieren.', 422);
                return;
            }
        }

        // Aktuelle Formularwerte (oder gespeicherte Werte) für den BRAM-Textcheck heranziehen
        $customTitle      = $this->request->post('title', null);
        $customDesc       = $this->request->post('description', null);
        $customMetaTitle  = $this->request->post('meta_title', null);
        $customMetaDesc   = $this->request->post('meta_description', null);
        $customTranscript = $this->request->post('spoken_transcript', null);

        $titleToCheck     = $customTitle !== null ? trim((string)$customTitle) : self::decodeText((string)$m->title);
        $descToCheck      = $customDesc !== null ? trim((string)$customDesc) : self::decodeText((string)$m->description);
        $metaTitleToCheck = $customMetaTitle !== null ? trim((string)$customMetaTitle) : self::decodeText((string)$m->meta_title);
        $metaDescToCheck  = $customMetaDesc !== null ? trim((string)$customMetaDesc) : self::decodeText((string)$m->meta_description);

        if ($customTranscript !== null) {
            $transcriptStr = trim((string)$customTranscript);
            $parsedSegs = [];
            foreach (explode("\n", $transcriptStr) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^\[(\d+):(\d+)\s*-\s*(\d+):(\d+)\]\s*(.+)$/', $line, $mTs)) {
                    $sSec = ((int)$mTs[1] * 60) + (int)$mTs[2];
                    $eSec = ((int)$mTs[3] * 60) + (int)$mTs[4];
                    $parsedSegs[] = ['start' => $sSec, 'end' => $eSec, 'text' => $mTs[5]];
                } else {
                    $parsedSegs[] = ['start' => 0, 'end' => 0, 'text' => $line];
                }
            }
            $segJson = (string)json_encode($parsedSegs, JSON_UNESCAPED_UNICODE);
            p4c_query(
                "UPDATE `movies_ai_analysis`
                 SET `spoken_transcript` = '" . p4c_escape_string($transcriptStr) . "',
                     `spoken_segments_json` = '" . p4c_escape_string($segJson) . "',
                     `updated_at` = '" . date('Y-m-d H:i:s') . "'
                 WHERE `movie_id` = '" . $movieId . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );
        }

        $aiData = $this->loadAndEnsureMovieAiData($movieId, $merchantId, $fileId, $movieDir, [
            'title'            => $titleToCheck,
            'description'      => $descToCheck,
            'meta_title'       => $metaTitleToCheck,
            'meta_description' => $metaDescToCheck,
        ], true);

        Response::json([
            'status'  => 'ok',
            'message' => $subAction === 'run_whisper'
                ? 'Whisper Audio-Transkription und BRAM-Prüfung wurden erfolgreich durchgeführt.'
                : 'BRAM-Compliance-Prüfung wurde mit den aktuellen Texten aktualisiert.',
            'ai_data' => $aiData,
        ]);
    }

    /**
     * 7. AJAX: Technische Datei-Metadaten via getID3 auslesen (GET ?r=movies-checking/file-meta&id=...)
     */
    public function fileMeta(): void
    {
        $movieId = abs((int)$this->request->get('id', 0));
        if ($movieId <= 0) {
            Response::error('Ungültige Film-ID.', 400);
            return;
        }

        $rs = p4c_query(
            "SELECT `id`, `merchant_id`, `filename`, `storage_location`, `resolution`, `playtime_seconds`
             FROM `movies`
             WHERE `id` = '" . $movieId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rs || p4c_num_rows($rs) === 0) {
            Response::error('Film nicht gefunden.', 404);
            return;
        }

        $m = p4c_fetch_object($rs);
        $videoPath = rtrim((string)MOVIES_PATH, '/\\') . '/'
            . (string)$m->storage_location . '/'
            . abs((int)$m->merchant_id) . '/'
            . abs((int)$m->id) . '/'
            . (string)$m->filename;

        if (!is_file($videoPath)) {
            Response::json([
                'status' => 'ok',
                'exists' => false,
                'summary' => [
                    'Datei'      => (string)$m->filename,
                    'Speicherort'=> (string)$m->storage_location,
                    'Auflösung'  => (string)$m->resolution,
                    'Laufzeit'   => gmdate('H:i:s', abs((int)$m->playtime_seconds)),
                    'Hinweis'    => 'Die physische MP4-Datei liegt auf dieser Entwicklungsumgebung nicht lokal vor.',
                ],
                'raw_dump' => '',
            ]);
            return;
        }

        require_once KLASSEN_DIR . '/getID3/getid3.php';
        $getID3   = new \getID3();
        $fileInfo = $getID3->analyze($videoPath);
        unset($fileInfo['filenamepath'], $fileInfo['filepath']);

        $summary = [
            'Dateiname'      => (string)($fileInfo['filename'] ?? $m->filename),
            'Dateigröße'     => isset($fileInfo['filesize']) ? number_format((float)$fileInfo['filesize'] / 1048576, 2, ',', '.') . ' MB' : '-',
            'Format'         => (string)($fileInfo['fileformat'] ?? 'mp4'),
            'Laufzeit'       => (string)($fileInfo['playtime_string'] ?? gmdate('H:i:s', abs((int)$m->playtime_seconds))),
            'Bitrate gesamt' => isset($fileInfo['bitrate']) ? round((float)$fileInfo['bitrate'] / 1000) . ' kbps' : '-',
            'Video-Codec'    => (string)($fileInfo['video']['fourcc'] ?? $fileInfo['video']['dataformat'] ?? '-'),
            'Auflösung'      => isset($fileInfo['video']['resolution_x'], $fileInfo['video']['resolution_y'])
                ? ((int)$fileInfo['video']['resolution_x'] . ' × ' . (int)$fileInfo['video']['resolution_y'])
                : (string)$m->resolution,
            'Framerate'      => isset($fileInfo['video']['frame_rate']) ? $fileInfo['video']['frame_rate'] . ' fps' : '-',
            'Audio-Codec'    => (string)($fileInfo['audio']['dataformat'] ?? $fileInfo['audio']['codec'] ?? 'Keine Tonspur'),
            'Audio-Kanäle'   => isset($fileInfo['audio']['channels']) ? (int)$fileInfo['audio']['channels'] . ' Kanäle (' . ($fileInfo['audio']['sample_rate'] ?? '') . ' Hz)' : '-',
        ];

        Response::json([
            'status'   => 'ok',
            'exists'   => true,
            'summary'  => $summary,
            'raw_dump' => print_r($fileInfo, true),
        ]);
    }

    /**
     * Lädt die IDs aller aktuell offenen Filme in der Prüf-Warteschlange
     *
     * @return array<int, int>
     */
    private function fetchUncheckedQueueIds(): array
    {
        $ids = [];
        $rs = p4c_query(
            "SELECT `id`
             FROM `movies`
             WHERE (`movie_checked` = '0000-00-00 00:00:00' OR `movie_checked` = '000-00-00 00:00:00')
               AND `released` = '1'
               AND `convert_status` > '1'
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
     * Bereitet alle Mitwirkenden- & 18+ ID-Shot-Informationen für die Detailansicht auf
     *
     * @return array<string, mixed>
     */
    private function loadMoviePerformersData(int $movieId, int $merchantId): array
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
        $perfInfo      = \CoActorManager::getContentPerformers('movie', $movieId, $merchantId);
        $ownerEntry    = \CoActorManager::ensureOwnerCoActor($merchantId);
        $merchantIdent = \CoActorManager::getMerchantIdentity($merchantId);
        $performerMode = (string)($perfInfo['performer_mode'] ?? 'solo_owner');
        $ownerInMovie  = !empty($perfInfo['owner_in_content']);
        $linkedActors  = is_array($perfInfo['co_actors'] ?? null) ? $perfInfo['co_actors'] : [];

        $personsToReview = [];
        if ($performerMode === 'solo_owner' || $ownerInMovie) {
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
            'owner_in_content'   => $ownerInMovie,
            'merchant_full_name' => (string)($merchantIdent['full_name'] ?? ('Merchant #' . $merchantId)),
            'persons'            => array_values($personsToReview),
            'all_verified'       => $allVerified,
        ];
    }

    /**
     * Lädt (und berechnet bei Bedarf) die BRAM-Compliance- & Whisper-Daten eines Films
     *
     * @param array<string, string> $textInput
     * @return array<string, mixed>
     */
    private function loadAndEnsureMovieAiData(
        int $movieId,
        int $merchantId,
        string $fileId,
        string $movieDir,
        array $textInput,
        bool $forceRecheck = false
    ): array {
        $env = function_exists('aic_check_environment') ? aic_check_environment() : ['llm_ready' => false];

        $rsAi = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
        $aiRow = ($rsAi && p4c_num_rows($rsAi) > 0) ? p4c_fetch_object($rsAi) : null;

        if ($forceRecheck || !$aiRow || abs((int)($aiRow->text_check_status ?? 0)) < 2) {
            $segs = [];
            $transcript = '';
            if ($aiRow) {
                $transcript = self::decodeText((string)($aiRow->spoken_transcript ?? ''));
                if (!empty($aiRow->spoken_segments_json)) {
                    $dec = @json_decode((string)$aiRow->spoken_segments_json, true);
                    if (is_array($dec)) {
                        $segs = $dec;
                    }
                }
            }

            if (function_exists('aic_analyze_texts')) {
                $rep = aic_analyze_texts($textInput, $segs, $transcript, $env);
                $now = date('Y-m-d H:i:s');
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
                         `text_duration_sec`    = '" . p4c_escape_string((string)$rep['duration_sec']) . "',
                         `updated_at`           = '" . $now . "';",
                    __FILE__,
                    __LINE__
                );

                $rsAi = p4c_query("SELECT * FROM `movies_ai_analysis` WHERE `movie_id` = '" . $movieId . "' LIMIT 1;", __FILE__, __LINE__);
                $aiRow = ($rsAi && p4c_num_rows($rsAi) > 0) ? p4c_fetch_object($rsAi) : null;
            }
        }

        $violations = [];
        $segments   = [];
        if ($aiRow) {
            if (!empty($aiRow->text_violations_json)) {
                $decV = @json_decode((string)$aiRow->text_violations_json, true);
                if (is_array($decV)) {
                    $violations = $decV;
                }
            }
            if (!empty($aiRow->spoken_segments_json)) {
                $decS = @json_decode((string)$aiRow->spoken_segments_json, true);
                if (is_array($decS)) {
                    $segments = $decS;
                }
            }
        }

        $subFilename = $aiRow && !empty($aiRow->subtitle_filename)
            ? (string)$aiRow->subtitle_filename
            : ('sub_' . $movieId . '_' . $fileId . '.vtt');
        $vttFilePath = $movieDir . '/' . $subFilename;
        $vttContent  = '';
        if (is_file($vttFilePath)) {
            $vttContent = (string)@file_get_contents($vttFilePath);
        } elseif (!empty($segments) && function_exists('aic_build_vtt_content')) {
            $vttContent = (string)aic_build_vtt_content($segments, (string)($aiRow->detected_language ?? 'de'));
        }

        return [
            'subtitle_status'     => $aiRow ? abs((int)$aiRow->subtitle_status) : 0,
            'detected_language'   => $aiRow ? (string)($aiRow->detected_language ?? '') : '',
            'language_confidence' => $aiRow ? (float)($aiRow->language_confidence ?? 0.0) : 0.0,
            'subtitle_filename'   => $subFilename,
            'spoken_transcript'   => $aiRow ? self::decodeText((string)($aiRow->spoken_transcript ?? '')) : '',
            'segments'            => $segments,
            'vtt_content'         => $vttContent,
            'has_vtt'             => ($vttContent !== '' && !empty($segments)),
            'whisper_duration'    => $aiRow ? (float)($aiRow->whisper_duration_sec ?? 0.0) : 0.0,
            'text_check_status'   => $aiRow ? abs((int)$aiRow->text_check_status) : 0,
            'text_compliance'     => $aiRow ? (string)($aiRow->text_compliance ?: 'pending') : 'pending',
            'text_risk_score'     => $aiRow ? abs((int)$aiRow->text_risk_score) : 0,
            'text_engine_used'    => $aiRow ? self::decodeText((string)($aiRow->text_engine_used ?? '')) : '',
            'text_summary'        => $aiRow ? self::decodeText((string)($aiRow->text_summary ?? '')) : '',
            'violations'          => $violations,
            'last_error'          => $aiRow ? self::decodeText((string)($aiRow->last_error ?? '')) : '',
            'updated_at'          => $aiRow ? (string)($aiRow->updated_at ?? '') : '',
        ];
    }

    private function renderNotFound(string $message): string
    {
        return '<div class="card-kkc m-4"><div class="card-kkc-body p-4 text-center">'
            . '<div class="mb-3"><i class="bi bi-film fs-1 text-muted"></i></div>'
            . '<h2 class="h5 mb-2">Film nicht gefunden</h2>'
            . '<p class="text-muted mb-3">' . View::e($message) . '</p>'
            . '<a href="#/movies-checking" class="btn btn-kkc"><i class="bi bi-arrow-left me-1"></i> Zurück zur Prüf-Warteschlange</a>'
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
