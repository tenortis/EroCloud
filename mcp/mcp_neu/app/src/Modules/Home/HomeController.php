<?php
declare(strict_types=1);

namespace App\Modules\Home;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Merchant;

/**
 * EroCloud MCP (v2.0) - HomeController
 * Lädt alle echten Partner-, Profil- und Content-Daten für die Startseite (ohne Umsatzzahlen).
 */
class HomeController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Rendert das eingeloggte Partner-Dashboard (#/startseite) mit echten DB-Daten
     */
    public function view(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            return '<div class="alert alert-warning">Deine Sitzung ist abgelaufen. Bitte melde dich erneut über Pay4Coins an.</div>';
        }

        $merchant = new Merchant($mysql, $merchantId);

        // 1. Standardgruppe für Profile prüfen/anlegen (analog mcp/includes/home.php)
        $this->ensureDefaultGroup($merchantId);

        // 2. Stammdaten & Richtlinien-Status des Partners
        $firstname          = trim((string)$merchant->firstname('aes_decrypt'));
        $surname            = trim((string)$merchant->surname('aes_decrypt'));
        $merchantUsername   = trim((string)$merchant->username('aes_decrypt'));
        $partnerId          = trim((string)$merchant->partner_id());
        $acceptContentRules = (string)$merchant->accept_content_rules();
        $needsRulesAccept   = ($acceptContentRules === '' || $acceptContentRules === '0000-00-00 00:00:00');
        $minActorProfiles   = abs((int)$merchant->minimum_number_actor_profiles());

        // 3. Darsteller-Profile des Partners laden
        $actors = [];
        $activeActorsCount = 0;
        $hasInactiveOrBlockedActor = false;
        $actorMap = [];
        $genderLabels = [
            'f' => 'Weiblich',
            'm' => 'Männlich',
            't' => 'Transgender',
        ];

        $rsActors = p4c_query("SELECT `id`, `username`, `status`, `profile_image_fsk16`, `profile_image_fsk18`,
                `gender`, `age`, `star_sign`, `body_height`, `body_weight`, `hair_color`, `eye_color`, `cup_size`, `sexual_orientation`, `marital_status`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `username` ASC;", __FILE__, __LINE__);

        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($row = p4c_fetch_object($rsActors)) {
                $actorId = abs((int)$row->id);
                $status  = (string)$row->status;

                if ($status === 'active') {
                    $activeActorsCount++;
                }
                if ($status === 'locked' || $status === 'blocked' || $status === 'inactive') {
                    $hasInactiveOrBlockedActor = true;
                }

                $genderCode = (string)($row->gender ?? 'f');
                $actorMap[$actorId] = (string)$row->username;
                $actors[$actorId] = [
                    'id'                 => $actorId,
                    'username'           => (string)$row->username,
                    'status'             => $status,
                    'locked_reason'      => self::decodeLegacyText((string)($row->locked_reason ?? '')),
                    'avatar_fsk16'       => trim((string)($row->profile_image_fsk16 ?? '')),
                    'avatar_fsk18'       => trim((string)($row->profile_image_fsk18 ?? '')),
                    'gender_label'       => $genderLabels[$genderCode] ?? 'Weiblich',
                    'age'                => abs((int)($row->age ?? 0)),
                    'star_sign'          => self::decodeLegacyText((string)($row->star_sign ?? '')),
                    'body_height'        => abs((int)($row->body_height ?? 0)),
                    'body_weight'        => abs((int)($row->body_weight ?? 0)),
                    'hair_color'         => self::decodeLegacyText((string)($row->hair_color ?? '')),
                    'eye_color'          => self::decodeLegacyText((string)($row->eye_color ?? '')),
                    'cup_size'           => self::decodeLegacyText((string)($row->cup_size ?? '')),
                    'sexual_orientation' => self::decodeLegacyText((string)($row->sexual_orientation ?? '')),
                    'marital_status'     => self::decodeLegacyText((string)($row->marital_status ?? '')),
                    'movies_count'       => 0,
                    'albums_count'       => 0,
                    'latest_movie_id'    => 0,
                ];
            }
        }

        // 4. Filme des Partners laden & KPIs berechnen
        $moviesTotal    = 0;
        $moviesOnline   = 0;
        $moviesPending  = 0;
        $recentContents = [];

        $rsMovies = p4c_query("SELECT `id`, `convert_status`, `file_id`, `filename`, `title`, `movie_checked`, `released`, `actor_id`, `online_at`
            FROM `movies`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `id` DESC;", __FILE__, __LINE__);

        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($m = p4c_fetch_object($rsMovies)) {
                $movieId = abs((int)$m->id);
                $actorId = abs((int)$m->actor_id);
                // Falls das zugehörige Profil gelöscht wurde, überspringen (analog ajax/movies.php)
                if ($actorId > 0 && !isset($actorMap[$actorId])) {
                    continue;
                }

                $moviesTotal++;
                if (isset($actors[$actorId])) {
                    $actors[$actorId]['movies_count']++;
                    if ($movieId > $actors[$actorId]['latest_movie_id']) {
                        $actors[$actorId]['latest_movie_id'] = $movieId;
                    }
                }

                $checked      = (string)$m->movie_checked;
                $released     = abs((int)$m->released);
                $convStatus   = (int)$m->convert_status;
                $filename     = trim((string)$m->filename);

                $statusLabel = 'In Bearbeitung';
                $statusBadge = 'badge-kkc-neutral';
                $statusIcon  = 'bi-clock';

                if ($filename === '') {
                    $statusLabel = 'Datei fehlt';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-cloud-arrow-up';
                    $moviesPending++;
                } elseif ($convStatus <= 1) {
                    $statusLabel = 'Wird konvertiert';
                    $statusBadge = 'badge-kkc-info';
                    $statusIcon  = 'bi-gear-wide-connected';
                    $moviesPending++;
                } elseif ($checked !== '0000-00-00 00:00:00' && $checked !== '') {
                    $statusLabel = 'Online';
                    $statusBadge = 'badge-kkc-success';
                    $statusIcon  = 'bi-check-circle-fill';
                    $moviesOnline++;
                } elseif ($checked === '0000-00-00 00:00:00' && $released === 1) {
                    $statusLabel = 'Wird geprüft';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-hourglass-split';
                    $moviesPending++;
                } elseif ($checked === '0000-00-00 00:00:00' && $released === 2) {
                    $statusLabel = 'Abgelehnt';
                    $statusBadge = 'badge-kkc-danger';
                    $statusIcon  = 'bi-x-circle-fill';
                } elseif ($convStatus === 2) {
                    $statusLabel = 'Bereit zur Freigabe';
                    $statusBadge = 'badge-kkc-warning';
                    $statusIcon  = 'bi-exclamation-circle';
                    $moviesPending++;
                }

                if (count($recentContents) < 6) {
                    $recentContents[] = [
                        'id'           => abs((int)$m->id),
                        'title'        => trim((string)$m->title) !== '' ? (string)$m->title : 'Unbenannter Film #' . abs((int)$m->id),
                        'actor_id'     => $actorId,
                        'actor_name'   => $actorMap[$actorId] ?? '-',
                        'type'         => 'movie',
                        'status_label' => $statusLabel,
                        'status_badge' => $statusBadge,
                        'status_icon'  => $statusIcon,
                        'route'        => '#/movie?id=' . abs((int)$m->id),
                    ];
                }
            }
        }

        // 5. Fotoalben des Partners laden & KPIs berechnen
        $albumsTotal   = 0;
        $albumsOnline  = 0;
        $albumsPending = 0;
        $photosTotal   = 0;

        $rsAlbums = p4c_query("SELECT `id`, `album_id`, `number_of_photos`, `title`, `album_checked`, `released`, `actor_id`
            FROM `photo_albums`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `id` DESC;", __FILE__, __LINE__);

        if ($rsAlbums && p4c_num_rows($rsAlbums) > 0) {
            while ($a = p4c_fetch_object($rsAlbums)) {
                $actorId = abs((int)$a->actor_id);
                if ($actorId > 0 && !isset($actorMap[$actorId])) {
                    continue;
                }

                $albumsTotal++;
                $numPhotos = abs((int)$a->number_of_photos);
                $photosTotal += $numPhotos;

                if (isset($actors[$actorId])) {
                    $actors[$actorId]['albums_count']++;
                }

                $checked  = (string)$a->album_checked;
                $released = abs((int)$a->released);
                if ($checked !== '0000-00-00 00:00:00' && $checked !== '') {
                    $albumsOnline++;
                } elseif ($released !== 2) {
                    $albumsPending++;
                }
            }
        }

        $contentPending = $moviesPending + $albumsPending;

        // Darsteller-Profile nach aktuellstem Film-Upload sortieren (aktiv genutzte Profile zuerst, sonst alphabetisch)
        $sortedActors = array_values($actors);
        usort($sortedActors, static function (array $a, array $b): int {
            $latestA = (int)($a['latest_movie_id'] ?? 0);
            $latestB = (int)($b['latest_movie_id'] ?? 0);
            if ($latestA !== $latestB) {
                return $latestB <=> $latestA;
            }
            return strnatcasecmp((string)($a['username'] ?? ''), (string)($b['username'] ?? ''));
        });

        return View::render('Home', 'dashboard', [
            'merchantId'                => $merchantId,
            'partnerId'                 => $partnerId,
            'merchantUsername'          => $merchantUsername,
            'firstname'                 => $firstname,
            'surname'                   => $surname,
            'needsRulesAccept'          => $needsRulesAccept,
            'acceptContentRulesDate'    => $acceptContentRules,
            'hasInactiveOrBlockedActor' => $hasInactiveOrBlockedActor,
            'canCreateActor'            => count($actors) < $minActorProfiles,
            'actors'                    => $sortedActors,
            'actorsTotal'               => count($actors),
            'activeActorsCount'         => $activeActorsCount,
            'moviesTotal'               => $moviesTotal,
            'moviesOnline'              => $moviesOnline,
            'moviesPending'             => $moviesPending,
            'albumsTotal'               => $albumsTotal,
            'albumsOnline'              => $albumsOnline,
            'albumsPending'             => $albumsPending,
            'photosTotal'               => $photosTotal,
            'contentPending'            => $contentPending,
            'recentContents'            => $recentContents,
        ]);
    }

    /**
     * Sendet eine Creator-Meldung (Fehler im Testlauf, Idee oder Frage) inkl. Account-Daten,
     * Darsteller-Profilen, Dateianhängen und zentralem Error-Log (/log/.errorlog) an das KKC.
     */
    public function sendFeedback(): void
    {
        global $mysql;

        if (!$this->request->isPost()) {
            Response::json([
                'success' => false,
                'message' => 'Ungültige Anfrage-Methode.',
            ], 405);
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'Deine Sitzung ist abgelaufen. Bitte melde dich erneut über Pay4Coins an.',
            ], 401);
        }

        $now = time();

        // Rate-Limiting über Session (max. 1 Nachricht alle 15 Sekunden gegen Doppelklicks)
        if (isset($_SESSION['last_ec_feedback_sent']) && ($now - abs((int)$_SESSION['last_ec_feedback_sent'])) < 15) {
            Response::json([
                'success' => false,
                'message' => 'Bitte warte einen kurzen Moment, bevor du eine weitere Nachricht absendest.',
            ]);
        }

        $rawCategory = trim((string)$this->request->post('category', 'bug'));
        $allowedCategories = ['idea', 'bug', 'question'];
        $category = in_array($rawCategory, $allowedCategories, true) ? $rawCategory : 'bug';

        $rawMessage = trim((string)$this->request->post('message', ''));
        $rawMessage = html_entity_decode($rawMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawMessage = trim(strip_tags($rawMessage));

        if (mb_strlen($rawMessage, 'UTF-8') < 5) {
            Response::json([
                'success' => false,
                'message' => 'Bitte gib eine kurze Beschreibung (mindestens 5 Zeichen) ein.',
            ]);
        }

        if (mb_strlen($rawMessage, 'UTF-8') > 10000) {
            $rawMessage = mb_substr($rawMessage, 0, 10000, 'UTF-8');
        }

        // =====================================================================
        // Stufe-1-Dateiprüfung (max. 3 Dateien à 6 MB: JPG, PNG, WEBP, GIF, PDF)
        // =====================================================================
        $validatedAttachments = [];
        $maxAttachments = 3;
        $maxFileSize = 6 * 1024 * 1024; // 6 MB
        $allowedMimeMap = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/gif'       => 'gif',
            'application/pdf' => 'pdf',
        ];

        if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['tmp_name'])) {
            $fileCount = count($_FILES['attachments']['tmp_name']);
            if ($fileCount > $maxAttachments) {
                Response::json([
                    'success' => false,
                    'message' => 'Du kannst maximal 3 Dateien anhängen.',
                ]);
            }

            $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;

            for ($i = 0; $i < $fileCount; $i++) {
                $errCode = (int)($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($errCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $origName = (string)($_FILES['attachments']['name'][$i] ?? ('anhang-' . ($i + 1)));
                if ($errCode !== UPLOAD_ERR_OK) {
                    if ($finfo && PHP_VERSION_ID < 80100) {
                        @finfo_close($finfo);
                    }
                    Response::json([
                        'success' => false,
                        'message' => 'Fehler beim Upload der Datei "' . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8') . '".',
                    ]);
                }

                $tmpPath  = (string)($_FILES['attachments']['tmp_name'][$i] ?? '');
                $fileSize = abs((int)($_FILES['attachments']['size'][$i] ?? 0));

                if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $fileSize <= 0 || $fileSize > $maxFileSize) {
                    if ($finfo && PHP_VERSION_ID < 80100) {
                        @finfo_close($finfo);
                    }
                    Response::json([
                        'success' => false,
                        'message' => 'Die Datei "' . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8') . '" überschreitet das Limit von 6 MB oder ist ungültig.',
                    ]);
                }

                $realMime = '';
                if ($finfo !== false) {
                    $realMime = (string)@finfo_file($finfo, $tmpPath);
                } elseif (function_exists('mime_content_type')) {
                    $realMime = (string)@mime_content_type($tmpPath);
                }

                if (!isset($allowedMimeMap[$realMime])) {
                    if ($finfo && PHP_VERSION_ID < 80100) {
                        @finfo_close($finfo);
                    }
                    Response::json([
                        'success' => false,
                        'message' => 'Die Datei "' . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8') . '" wurde aus Sicherheitsgründen abgelehnt. Erlaubt sind nur echte Bilder (JPG, PNG, WEBP, GIF) und PDF-Dokumente.',
                    ]);
                }

                $rawBytes = (string)@file_get_contents($tmpPath);
                if ($rawBytes === '' || strlen($rawBytes) !== $fileSize) {
                    continue;
                }

                // Schutz gegen eingebetteten PHP-/Script-Code oder Phar-Stubs
                if (preg_match('/(<\?php|<\?=|<script\b|__HALT_COMPILER|phar:\/\/)/i', $rawBytes)) {
                    if ($finfo && PHP_VERSION_ID < 80100) {
                        @finfo_close($finfo);
                    }
                    Response::json([
                        'success' => false,
                        'message' => 'Die Datei "' . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8') . '" enthält nicht erlaubten Code und wurde blockiert.',
                    ]);
                }

                $safeExt = $allowedMimeMap[$realMime];

                if ($safeExt === 'pdf') {
                    if (strncmp($rawBytes, '%PDF-1.', 7) !== 0 && strncmp($rawBytes, '%PDF-2.', 7) !== 0) {
                        if ($finfo && PHP_VERSION_ID < 80100) {
                            @finfo_close($finfo);
                        }
                        Response::json([
                            'success' => false,
                            'message' => 'Die PDF-Datei "' . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8') . '" besitzt keinen gültigen PDF-Header.',
                        ]);
                    }
                } else {
                    $imgInfo = @getimagesize($tmpPath);
                    if (!is_array($imgInfo) || empty($imgInfo[0]) || empty($imgInfo[1])) {
                        if ($finfo && PHP_VERSION_ID < 80100) {
                            @finfo_close($finfo);
                        }
                        Response::json([
                            'success' => false,
                            'message' => 'Die Bilddatei "' . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8') . '" ist beschädigt oder kein gültiges Bild.',
                        ]);
                    }
                }

                $cleanBase = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string)pathinfo($origName, PATHINFO_FILENAME));
                $cleanBase = trim((string)preg_replace('/_+/', '_', (string)$cleanBase), '_.');
                if ($cleanBase === '') {
                    $cleanBase = 'anhang_' . ($i + 1);
                }
                $safeOriginalName = substr($cleanBase, 0, 80) . '.' . $safeExt;

                $validatedAttachments[] = [
                    'name'        => $safeOriginalName,
                    'mime'        => $realMime,
                    'ext'         => $safeExt,
                    'size'        => $fileSize,
                    'data_base64' => base64_encode($rawBytes),
                ];
            }

            if ($finfo && PHP_VERSION_ID < 80100) {
                @finfo_close($finfo);
            }
        }

        // =====================================================================
        // Creator- & Partner-Kontext aus EroCloud DB ermitteln
        // =====================================================================
        $merchant         = new Merchant($mysql, $merchantId);
        $partnerId        = trim((string)$merchant->partner_id());
        $merchantUsername = trim((string)$merchant->username('aes_decrypt'));
        $firstname        = trim((string)$merchant->firstname('aes_decrypt'));
        $surname          = trim((string)$merchant->surname('aes_decrypt'));
        $realName         = trim($firstname . ' ' . $surname);
        $email            = trim((string)$merchant->email('aes_decrypt'));

        // Darsteller-Profile des Partners laden
        $actorsList  = [];
        $actorLabels = [];
        $rsActors = p4c_query("SELECT `id`, `username`, `status`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `username` ASC;", __FILE__, __LINE__);
        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($aRow = p4c_fetch_object($rsActors)) {
                $aId     = abs((int)$aRow->id);
                $aName   = trim((string)$aRow->username);
                $aStatus = trim((string)$aRow->status);
                $actorsList[] = [
                    'id'       => $aId,
                    'username' => $aName,
                    'status'   => $aStatus,
                ];
                $actorLabels[] = $aName . ' (#' . $aId . ')';
            }
        }

        // Verknüpfte Webseiten aus EroCloud `sites` ermitteln
        $connectedDomains = [];
        if ($partnerId !== '') {
            $rsSites = p4c_query("SELECT `domain`, `shop_system`
                FROM `sites`
                WHERE `partner_id` = '" . p4c_escape_string($partnerId) . "'
                ORDER BY `id` ASC;", __FILE__, __LINE__);
            if ($rsSites && p4c_num_rows($rsSites) > 0) {
                while ($sRow = p4c_fetch_object($rsSites)) {
                    $sDom = trim((string)$sRow->domain);
                    if ($sDom !== '' && !in_array($sDom, $connectedDomains, true)) {
                        $connectedDomains[] = $sDom;
                    }
                }
            }
        }

        // Aussagekräftigen Creator-Namen für die KKC-Übersicht aufbauen
        if (count($actorLabels) === 1) {
            $primaryLabel = $actorLabels[0];
        } elseif (count($actorLabels) > 1) {
            $baseOwner = $merchantUsername !== '' ? $merchantUsername : ($realName !== '' ? $realName : ('Merchant #' . $merchantId));
            $primaryLabel = $baseOwner . ' (' . count($actorLabels) . ' Profile)';
        } else {
            $primaryLabel = $merchantUsername !== '' ? $merchantUsername : ('Merchant #' . $merchantId);
        }

        $creatorNameParts = [$primaryLabel];
        if ($realName !== '' && stripos($primaryLabel, $realName) === false) {
            $creatorNameParts[] = $realName;
        }
        if ($partnerId !== '') {
            $creatorNameParts[] = 'P4C: ' . $partnerId;
        }
        $creatorName = implode(' | ', $creatorNameParts);

        // Zentrales Error-Log (/log/.errorlog) inkl. EroCloud-Diagnosekopf auslesen
        $errorLogSnippet = $this->buildErrorLogDiagnostic($merchantId, $partnerId, $merchantUsername, $realName, $email, $actorsList, $connectedDomains);

        $domain     = 'erocloud.net';
        $creatorKey = 'ec_merchant_' . $merchantId;
        $secret     = 'erocms_creator_feedback_v1_secret_2026';
        $signature  = hash_hmac('sha256', $domain . '|' . $now . '|' . $category, $secret);

        $pageContext = trim((string)$this->request->post('page_context', 'mcp_neu/web/#/startseite'));
        if ($pageContext === '') {
            $pageContext = 'mcp_neu/web/#/startseite';
        }

        $payload = [
            'domain'            => $domain,
            'mieter_id'         => 0,
            'creator_name'      => $creatorName,
            'creator_key'       => $creatorKey,
            'category'          => $category,
            'message'           => $rawMessage,
            'erocms_version'    => 'EroCloud 2.0',
            'template_engine'   => 'MCP-NEU',
            'server'            => 't199.nlserver.net (web1)',
            'php_version'       => PHP_VERSION,
            'user_agent'        => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'page_context'      => substr($pageContext, 0, 255),
            'meta'              => [
                'source'            => 'erocloud_mcp',
                'merchant_id'       => $merchantId,
                'partner_id'        => $partnerId,
                'merchant_username' => $merchantUsername,
                'real_name'         => $realName,
                'email'             => $email,
                'actors'            => $actorsList,
                'connected_domains' => $connectedDomains,
            ],
            'error_log_snippet' => $errorLogSnippet,
            'attachments'       => $validatedAttachments,
            'timestamp'         => $now,
            'signature'         => $signature,
        ];

        $apiResult = $this->callFeedbackApi($payload, $signature, !empty($validatedAttachments) ? 25 : 10);

        if (!empty($apiResult['success'])) {
            $_SESSION['last_ec_feedback_sent'] = $now;
            log_action('Feedback / Meldung an Entwicklung gesendet (' . $category . ').');

            if ($category === 'bug') {
                $thankYouTitle = 'Vielen Dank für deine Fehlermeldung!';
                $thankYouText  = 'Dein Hinweis zum neuen EroCloud-Partnerbereich ist inklusive deiner Account-Daten und des System-Error-Logs direkt in meiner Entwicklungsumgebung eingegangen. Ich schaue mir das zeitnah an und antworte dir direkt hier auf deiner Startseite!';
            } elseif ($category === 'question') {
                $thankYouTitle = 'Vielen Dank für deine Rückmeldung!';
                $thankYouText  = 'Deine Nachricht zum neuen Partnerbereich wurde direkt an mich übermittelt. Sobald ich dir geantwortet habe, siehst du meine Rückmeldung direkt hier auf deiner Startseite!';
            } else {
                $thankYouTitle = 'Vielen Dank für deine Idee!';
                $thankYouText  = 'Dein Vorschlag für den neuen EroCloud-Partnerbereich ist direkt auf meiner Update- & ToDo-Liste gelandet. Ich prüfe deine Idee sehr gerne und gebe dir hier direkt Bescheid!';
            }

            Response::json([
                'success'  => true,
                'category' => $category,
                'title'    => $thankYouTitle,
                'message'  => $thankYouText,
            ]);
        }

        Response::json([
            'success' => false,
            'message' => 'Deine Nachricht konnte im Moment leider nicht übertragen werden. Bitte versuche es gleich noch einmal.',
        ]);
    }

    /**
     * Lädt den Verlauf der gesendeten Meldungen & Martins Antworten für diesen EroCloud-Partner
     */
    public function feedbackHistory(): void
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            Response::json([
                'success'      => false,
                'unread_count' => 0,
                'items'        => [],
            ], 401);
        }

        $now        = time();
        $domain     = 'erocloud.net';
        $creatorKey = 'ec_merchant_' . $merchantId;
        $secret     = 'erocms_creator_feedback_v1_secret_2026';
        $signature  = hash_hmac('sha256', 'get_creator_history|' . $domain . '|' . $now, $secret);

        $payload = [
            'action'      => 'get_creator_history',
            'domain'      => $domain,
            'creator_key' => $creatorKey,
            'timestamp'   => $now,
            'signature'   => $signature,
        ];

        $result = $this->callFeedbackApi($payload, $signature, 6);
        if (!empty($result['success'])) {
            Response::json($result);
        }

        Response::json([
            'success'      => false,
            'unread_count' => 0,
            'items'        => [],
        ]);
    }

    /**
     * Markiert eine Entwickler-Antwort durch die Creatorin als gelesen
     */
    public function markFeedbackRead(): void
    {
        if (!$this->request->isPost()) {
            Response::json(['success' => false], 405);
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            Response::json(['success' => false], 401);
        }

        $feedbackId = abs((int)$this->request->post('feedback_id', 0));
        $now        = time();
        $domain     = 'erocloud.net';
        $creatorKey = 'ec_merchant_' . $merchantId;
        $secret     = 'erocms_creator_feedback_v1_secret_2026';
        $signature  = hash_hmac('sha256', 'mark_reply_read|' . $domain . '|' . $feedbackId . '|' . $now, $secret);

        $payload = [
            'action'      => 'mark_reply_read',
            'domain'      => $domain,
            'creator_key' => $creatorKey,
            'feedback_id' => $feedbackId,
            'timestamp'   => $now,
            'signature'   => $signature,
        ];

        $result = $this->callFeedbackApi($payload, $signature, 6);
        if (!empty($result['success'])) {
            Response::json($result);
        }

        Response::json([
            'success'              => true,
            'feedback_id'          => $feedbackId,
            'reply_read_formatted' => date('d.m.Y \u\m H:i'),
        ]);
    }

    /**
     * Erstellt den technischen Diagnose-Block inklusive Auszug aus dem zentralen Error-Log (/log/.errorlog)
     */
    private function buildErrorLogDiagnostic(
        int $merchantId,
        string $partnerId,
        string $merchantUsername,
        string $realName,
        string $email,
        array $actorsList,
        array $connectedDomains
    ): string {
        $diagLines = [];
        $diagLines[] = '=== EROCLOUD MCP (v2.0) DIAGNOSE & CREATOR-KONTEXT ===';
        $diagLines[] = 'Zeitpunkt:          ' . date('d.m.Y H:i:s T');
        $diagLines[] = 'Merchant-ID:        #' . $merchantId . ($partnerId !== '' ? ' (Pay4Coins Partner-ID: ' . $partnerId . ')' : '');
        $diagLines[] = 'Account / Name:     ' . ($merchantUsername !== '' ? $merchantUsername : '-') . ($realName !== '' ? ' (' . $realName . ')' : '');
        if ($email !== '') {
            $diagLines[] = 'Partner E-Mail:     ' . $email;
        }

        if (!empty($actorsList)) {
            $aParts = [];
            foreach ($actorsList as $a) {
                $aParts[] = $a['username'] . ' (#' . $a['id'] . ', Status: ' . $a['status'] . ')';
            }
            $diagLines[] = 'Darsteller-Profile: ' . implode(', ', $aParts);
        } else {
            $diagLines[] = 'Darsteller-Profile: Keine angelegt';
        }

        if (!empty($connectedDomains)) {
            $diagLines[] = 'Verknüpfte Domains: ' . implode(', ', $connectedDomains);
        }

        $diagLines[] = 'PHP / Umgebung:     PHP ' . PHP_VERSION . ' | Host: ' . ($_SERVER['HTTP_HOST'] ?? 'erocloud.net');
        $diagLines[] = '';
        $diagLines[] = '=== ZENTRALES ERROR-LOG (' . (defined('SOURCEDIR') ? SOURCEDIR . '/log/.errorlog' : '/log/.errorlog') . ') ===';

        $errorLogFile = defined('SOURCEDIR') ? (SOURCEDIR . '/log/.errorlog') : '';
        $logTail      = '';

        if ($errorLogFile !== '' && @is_file($errorLogFile) && @is_readable($errorLogFile)) {
            $fileSize = (int)@filesize($errorLogFile);
            if ($fileSize > 0) {
                $maxReadBytes = 12288; // Letzte 12 KB
                $fp = @fopen($errorLogFile, 'rb');
                if ($fp !== false) {
                    if ($fileSize > $maxReadBytes) {
                        @fseek($fp, -$maxReadBytes, SEEK_END);
                    }
                    $rawLog = (string)@fread($fp, $maxReadBytes);
                    @fclose($fp);
                    if (!mb_check_encoding($rawLog, 'UTF-8')) {
                        $rawLog = mb_convert_encoding($rawLog, 'UTF-8', 'ISO-8859-1');
                    }
                    $lines = explode("\n", trim($rawLog));
                    if (count($lines) > 45) {
                        $lines = array_slice($lines, -45);
                    }
                    $logTail = trim(implode("\n", $lines));
                }
            }
        }

        if ($logTail !== '') {
            $diagLines[] = $logTail;
        } else {
            $diagLines[] = '(Keine aktuellen Fehlereinträge in /log/.errorlog vorhanden)';
        }

        return implode("\n", $diagLines);
    }

    /**
     * Kommuniziert mit https://remote.erocms.net/feedback.php (inkl. lokalem XAMPP-Direkt-Include für Tests)
     */
    private function callFeedbackApi(array $payload, string $signature, int $timeout = 10): array
    {
        $apiUrl  = 'https://remote.erocms.net/feedback.php';
        $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            || stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
            || stripos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false
            || DIRECTORY_SEPARATOR === '\\';

        $localFeedbackScript = defined('SOURCEDIR')
            ? dirname(SOURCEDIR) . '/erocms.net/remote/feedback.php'
            : '';

        if ($isLocal && $localFeedbackScript !== '' && @is_file($localFeedbackScript)) {
            $erocmsFeedbackInternalPayload = $payload;
            $GLOBALS['erocmsFeedbackInternalPayload'] = $payload;
            ob_start();
            include $localFeedbackScript;
            $localOutput = (string)ob_get_clean();
            unset($GLOBALS['erocmsFeedbackInternalPayload']);
            $localJson = @json_decode($localOutput, true);
            if (is_array($localJson)) {
                return $localJson;
            }
        }

        if (function_exists('curl_init')) {
            $ch = @curl_init($apiUrl);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => (string)json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json; charset=utf-8',
                        'Accept: application/json',
                        'X-EroCMS-Signature: ' . $signature,
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => $timeout,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $responseBody = curl_exec($ch);
                $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($responseBody !== false && $httpCode >= 200 && $httpCode < 300) {
                    $decoded = @json_decode((string)$responseBody, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        }

        return ['success' => false];
    }

    /**
     * Speichert die Zustimmung zu den Content-Richtlinien per AJAX-POST
     */
    public function acceptRules(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            Response::error('Nicht angemeldet.', 401);
        }

        $confirmed = (string)$this->request->post('upload_content', '');
        if ($confirmed !== '1' && $confirmed !== 'true' && $confirmed !== 'on') {
            Response::error('Bitte bestätige die Nutzungsrichtlinien über das Kontrollkästchen.', 400);
        }

        p4c_query("UPDATE `merchants` SET `accept_content_rules` = '" . date('Y-m-d H:i:s') . "'
            WHERE `id` = '" . $merchantId . "' AND `accept_content_rules` = '0000-00-00 00:00:00' LIMIT 1;", __FILE__, __LINE__);

        log_action('Nutzungsrichtlinien für Content akzeptiert.');

        Response::json([
            'status'  => 'success',
            'message' => 'Vielen Dank! Deine Zustimmung zu den Nutzungsrichtlinien wurde gespeichert.',
        ]);
    }

    /**
     * Speichert die zentralen E-Mail- und Push-Benachrichtigungs-Einstellungen des Creators per AJAX-POST
     */
    public function saveNotifications(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId <= 0) {
            Response::error('Deine Sitzung ist abgelaufen.', 401);
            return;
        }

        if (!class_exists('EroCloudNotificationCenter')) {
            Response::error('Das Benachrichtigungs-Center ist aktuell nicht verfügbar.', 500);
            return;
        }

        $catalog  = \EroCloudNotificationCenter::getEventCatalog();
        $channels = \EroCloudNotificationCenter::getChannelCatalog();

        $postedEmail = isset($_POST['email_events']) && is_array($_POST['email_events']) ? $_POST['email_events'] : [];
        $postedPush  = isset($_POST['push_events']) && is_array($_POST['push_events']) ? $_POST['push_events'] : [];

        foreach (array_keys($catalog) as $evKey) {
            if (isset($channels[\EroCloudNotificationCenter::CHANNEL_EMAIL])) {
                $emailOn = in_array($evKey, $postedEmail, true);
                \EroCloudNotificationCenter::setPreference(
                    $merchantId,
                    $evKey,
                    \EroCloudNotificationCenter::CHANNEL_EMAIL,
                    $emailOn,
                    'mcp_modal'
                );

                if ($evKey === \EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED) {
                    $flag = $emailOn ? 1 : 0;
                    p4c_query("UPDATE `movies` SET `notify_on_convert` = '" . $flag . "'
                        WHERE `merchant_id` = '" . $merchantId . "'
                          AND `convert_status` IN ('0', '1')
                          AND `notify_sent_at` IS NULL;", __FILE__, __LINE__);
                }
            }

            if (isset($channels[\EroCloudNotificationCenter::CHANNEL_PUSH])) {
                $pushOn = in_array($evKey, $postedPush, true);
                \EroCloudNotificationCenter::setPreference(
                    $merchantId,
                    $evKey,
                    \EroCloudNotificationCenter::CHANNEL_PUSH,
                    $pushOn,
                    'mcp_modal'
                );
            }
        }

        if (function_exists('log_action')) {
            log_action('Benachrichtigungs-Einstellungen (E-Mail / Push) im MCP aktualisiert.');
        }

        Response::json([
            'status'  => 'ok',
            'message' => 'Deine Benachrichtigungs-Einstellungen wurden gespeichert.',
        ]);
    }

    /**
     * Stellt sicher, dass für den Partner eine Standardgruppe existiert (analog mcp/includes/home.php)
     */
    private function ensureDefaultGroup(int $merchantId): void
    {
        $rsGroups = p4c_query("SELECT `id` FROM `groups` WHERE `merchant_id` = '" . $merchantId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsGroups && p4c_num_rows($rsGroups) === 0) {
            p4c_query("INSERT INTO `groups` SET
                `merchant_id`    = '" . $merchantId . "',
                `group_id`       = '" . p4c_escape_string('G-' . strtoupper(randomString(8))) . "',
                `group_name`     = 'Standartgruppe',
                `group_password` = '" . p4c_escape_string(randomString(12)) . "';", __FILE__, __LINE__);

            $groupId = abs((int)p4c_insert_id());
            if ($groupId > 0) {
                $rsActors = p4c_query("SELECT `id` FROM `actors` WHERE `merchant_id` = '" . $merchantId . "' ORDER BY `username` ASC;", __FILE__, __LINE__);
                if ($rsActors && p4c_num_rows($rsActors) > 0) {
                    while ($actorObj = p4c_fetch_object($rsActors)) {
                        $actorId = abs((int)$actorObj->id);
                        $rsCheck = p4c_query("SELECT `id` FROM `group_actors` WHERE `actor_id` = '" . $actorId . "' LIMIT 1;", __FILE__, __LINE__);
                        if ($rsCheck && p4c_num_rows($rsCheck) === 0) {
                            p4c_query("INSERT INTO `group_actors` SET
                                `merchant_id` = '" . $merchantId . "',
                                `group_id`    = '" . $groupId . "',
                                `actor_id`    = '" . $actorId . "';", __FILE__, __LINE__);
                        }
                    }
                }
            }
        }
    }

    /**
     * Dekodiert eventuell vorhandene Legacy-HTML-Entities (z.B. &auml; -> ä) sauber nach UTF-8.
     */
    private static function decodeLegacyText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return str_replace(["\r\n", "\r"], "\n", $decoded);
    }
}
