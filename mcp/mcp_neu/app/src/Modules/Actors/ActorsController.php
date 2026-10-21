<?php
declare(strict_types=1);

namespace App\Modules\Actors;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Darsteller-Profile Controller (Actors)
 * Verwaltet die Profil-Übersicht, Neuanlage, Bearbeitung sowie den FSK16/FSK18-Profilbild-Upload.
 */
class ActorsController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Liefert die offiziellen Auswahl-Optionen für alle Steckbrief-Felder.
     */
    public static function getFieldDefinitions(): array
    {
        return [
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
     * GET: Alle Darsteller-Profile des Partners anzeigen (#/actors)
     */
    public function list(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.</div>';
        }

        $merchant         = new \Merchant($mysql, $merchantId);
        $minActorProfiles = abs((int)$merchant->minimum_number_actor_profiles());
        $mcpUrl           = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';

        // Film-Zähler pro Darsteller vorab gruppiert abfragen
        $movieCounts = [];
        $rsMovies = p4c_query("SELECT `actor_id`, COUNT(*) AS `cnt`
            FROM `movies`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            GROUP BY `actor_id`;", __FILE__, __LINE__);
        if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
            while ($mRow = p4c_fetch_object($rsMovies)) {
                $movieCounts[abs((int)$mRow->actor_id)] = abs((int)$mRow->cnt);
            }
        }

        // Fotoalben-Zähler pro Darsteller vorab gruppiert abfragen
        $albumCounts = [];
        $rsAlbums = p4c_query("SELECT `actor_id`, COUNT(*) AS `cnt`
            FROM `photo_albums`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            GROUP BY `actor_id`;", __FILE__, __LINE__);
        if ($rsAlbums && p4c_num_rows($rsAlbums) > 0) {
            while ($aRow = p4c_fetch_object($rsAlbums)) {
                $albumCounts[abs((int)$aRow->actor_id)] = abs((int)$aRow->cnt);
            }
        }

        // Alle nicht gelöschten Darsteller-Profile laden
        $actors      = [];
        $countActive = 0;
        $countReview = 0;
        $countLocked = 0;

        $rsActors = p4c_query("SELECT *
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `username` ASC;", __FILE__, __LINE__);

        $defs = self::getFieldDefinitions();

        if ($rsActors && p4c_num_rows($rsActors) > 0) {
            while ($obj = p4c_fetch_object($rsActors)) {
                $actorId = abs((int)$obj->id);
                $status  = (string)$obj->status;

                if ($status === 'active') {
                    $countActive++;
                } elseif ($status === 'inactive') {
                    $countReview++;
                } elseif ($status === 'locked') {
                    $countLocked++;
                }

                $fsk16File = trim((string)($obj->profile_image_fsk16 ?? ''));
                $fsk18File = trim((string)($obj->profile_image_fsk18 ?? ''));

                $genderCode  = (string)($obj->gender ?? 'f');
                $genderLabel = $defs['genders'][$genderCode] ?? 'Weiblich';

                $actors[] = [
                    'id'            => $actorId,
                    'username'      => (string)$obj->username,
                    'status'        => $status,
                    'locked_reason' => self::decodeLegacyText((string)($obj->locked_reason ?? '')),
                    'fsk16_file'    => $fsk16File,
                    'fsk18_file'    => $fsk18File,
                    'fsk16_url'     => $fsk16File !== '' ? $mcpUrl . '/ProfilePicture/' . rawurlencode($fsk16File) : '',
                    'fsk18_url'     => $fsk18File !== '' ? $mcpUrl . '/ProfilePicture/' . rawurlencode($fsk18File) : '',
                    'gender_label'  => $genderLabel,
                    'age'           => abs((int)($obj->age ?? 25)),
                    'body_height'   => abs((int)($obj->body_height ?? 165)),
                    'hair_color'    => self::decodeLegacyText((string)($obj->hair_color ?? '')),
                    'cup_size'      => self::decodeLegacyText((string)($obj->cup_size ?? '')),
                    'movies_count'  => $movieCounts[$actorId] ?? 0,
                    'albums_count'  => $albumCounts[$actorId] ?? 0,
                ];
            }
        }

        $countActors  = count($actors);
        $canCreateNew = ($countActors < $minActorProfiles);

        return View::render('Actors', 'list', [
            'actors'           => $actors,
            'countActors'      => $countActors,
            'countActive'      => $countActive,
            'countReview'      => $countReview,
            'countLocked'      => $countLocked,
            'minActorProfiles' => $minActorProfiles,
            'canCreateNew'     => $canCreateNew,
        ]);
    }

    /**
     * GET: Neues Darsteller-Profil anlegen (#/new-actor)
     */
    public function newActor(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.</div>';
        }

        $merchant         = new \Merchant($mysql, $merchantId);
        $minActorProfiles = abs((int)$merchant->minimum_number_actor_profiles());

        $rsCount = p4c_query("SELECT COUNT(*) AS `cnt`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
        $cntObj      = ($rsCount && p4c_num_rows($rsCount) > 0) ? p4c_fetch_object($rsCount) : null;
        $countActors = abs((int)($cntObj->cnt ?? 0));

        if ($countActors >= $minActorProfiles) {
            return '
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-3 p-4 shadow-sm">
              <div class="d-flex align-items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                <div>
                  <div class="fw-bold">Profil-Limit erreicht (' . $countActors . ' von ' . $minActorProfiles . ' Profilen belegt)</div>
                  <div class="small">Sie haben die maximale Anzahl Ihrer freigeschalteten Darsteller-Profile erreicht. Bitte kontaktieren Sie unseren Support, falls Sie weitere Profile anlegen möchten.</div>
                </div>
              </div>
              <a href="#/actors" class="btn btn-sm btn-primary-kkc">Zur Profil-Übersicht</a>
            </div>';
        }

        $defaultActor = [
            'id'                       => 0,
            'username'                 => '',
            'status'                   => 'inactive',
            'locked_reason'            => '',
            'fsk16_file'               => '',
            'fsk18_file'               => '',
            'fsk16_url'                => '',
            'fsk18_url'                => '',
            'about_me'                 => '',
            'check_about_me'           => 1,
            'age'                      => 25,
            'check_age'                => 1,
            'star_sign'                => 'Steinbock',
            'check_star_sign'          => 1,
            'body_height'              => 165,
            'check_body_height'        => 1,
            'body_weight'              => 65,
            'check_body_weight'        => 1,
            'gender'                   => 'f',
            'check_gender'             => 1,
            'shaven'                   => 1,
            'check_shaven'             => 1,
            'hair_color'               => 'blond',
            'check_hair_color'         => 1,
            'eye_color'                => 'Hellblau',
            'check_eye_color'          => 1,
            'cup_size_a'               => '75',
            'cup_size_b'               => 'B',
            'check_cup_size'           => 1,
            'sexual_orientation'       => 'Hetero',
            'check_sexual_orientation' => 1,
            'marital_status'           => 'ledig',
            'check_marital_status'     => 1,
            'interests_list'           => [],
            'check_interests'          => 1,
            'looking_for_list'         => [],
            'check_looking_for'        => 1,
            'sexual_preferences_list'  => [],
            'check_sexual_preferences' => 1,
            'movies_count'             => 0,
            'albums_count'             => 0,
        ];

        return View::render('Actors', 'form', [
            'mode'             => 'create',
            'actor'            => $defaultActor,
            'defs'             => self::getFieldDefinitions(),
            'countActors'      => $countActors,
            'minActorProfiles' => $minActorProfiles,
        ]);
    }

    /**
     * GET: Bestehendes Darsteller-Profil bearbeiten (#/actor?id=...)
     */
    public function detail(): string
    {
        global $mysql;

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.</div>';
        }

        $actorId = abs((int)$this->request->get('id', 0));

        // Falls keine ID übergeben wurde, automatisch das erste Profil des Partners laden
        if ($actorId === 0) {
            $rsFirst = p4c_query("SELECT `id`
                FROM `actors`
                WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
                ORDER BY `username` ASC
                LIMIT 1;", __FILE__, __LINE__);
            if ($rsFirst && p4c_num_rows($rsFirst) > 0) {
                $firstObj = p4c_fetch_object($rsFirst);
                $actorId  = abs((int)$firstObj->id);
            } else {
                return $this->newActor();
            }
        }

        $rsActor = p4c_query("SELECT *
            FROM `actors`
            WHERE `id` = '" . $actorId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            return '
            <div class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 m-4">
              <div>
                <i class="bi bi-exclamation-octagon-fill me-2"></i>
                Das angeforderte Darsteller-Profil wurde nicht gefunden oder Sie besitzen keine Berechtigung.
              </div>
              <a href="#/actors" class="btn btn-sm btn-outline-kkc">Zur Profil-Übersicht</a>
            </div>';
        }

        $obj    = p4c_fetch_object($rsActor);
        $mcpUrl = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';

        // Körbchengröße (z.B. "75B" oder "100C") in Zahl und Buchstabe aufteilen
        $rawCup   = trim((string)($obj->cup_size ?? '75B'));
        $cupSizeA = '75';
        $cupSizeB = 'B';
        if (preg_match('/^(\d+)([A-Za-z]+)$/', $rawCup, $matches)) {
            $cupSizeA = $matches[1];
            $cupSizeB = strtoupper($matches[2]);
        }

        $fsk16File = trim((string)($obj->profile_image_fsk16 ?? ''));
        $fsk18File = trim((string)($obj->profile_image_fsk18 ?? ''));

        // Zugehörige Filme und Fotoalben zählen
        $rsMCount = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `actor_id` = '" . $actorId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
        $mCntObj  = ($rsMCount && p4c_num_rows($rsMCount) > 0) ? p4c_fetch_object($rsMCount) : null;

        $rsACount = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `actor_id` = '" . $actorId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
        $aCntObj  = ($rsACount && p4c_num_rows($rsACount) > 0) ? p4c_fetch_object($rsACount) : null;

        $actorData = [
            'id'                       => $actorId,
            'username'                 => (string)$obj->username,
            'status'                   => (string)$obj->status,
            'locked_reason'            => self::decodeLegacyText((string)($obj->locked_reason ?? '')),
            'fsk16_file'               => $fsk16File,
            'fsk18_file'               => $fsk18File,
            'fsk16_url'                => $fsk16File !== '' ? $mcpUrl . '/ProfilePicture/' . rawurlencode($fsk16File) : '',
            'fsk18_url'                => $fsk18File !== '' ? $mcpUrl . '/ProfilePicture/' . rawurlencode($fsk18File) : '',
            'about_me'                 => self::decodeLegacyText((string)($obj->about_me ?? '')),
            'check_about_me'           => abs((int)($obj->check_about_me ?? 0)),
            'age'                      => abs((int)($obj->age ?? 25)) >= 18 ? abs((int)$obj->age) : 25,
            'check_age'                => abs((int)($obj->check_age ?? 0)),
            'star_sign'                => self::decodeLegacyText((string)($obj->star_sign ?? 'Steinbock')),
            'check_star_sign'          => abs((int)($obj->check_star_sign ?? 0)),
            'body_height'              => abs((int)($obj->body_height ?? 165)) >= 100 ? abs((int)$obj->body_height) : 165,
            'check_body_height'        => abs((int)($obj->check_body_height ?? 0)),
            'body_weight'              => abs((int)($obj->body_weight ?? 65)) >= 45 ? abs((int)$obj->body_weight) : 65,
            'check_body_weight'        => abs((int)($obj->check_body_weight ?? 0)),
            'gender'                   => (string)($obj->gender ?? 'f'),
            'check_gender'             => abs((int)($obj->check_gender ?? 0)),
            'shaven'                   => abs((int)($obj->shaven ?? 0)),
            'check_shaven'             => abs((int)($obj->check_shaven ?? 0)),
            'hair_color'               => self::decodeLegacyText((string)($obj->hair_color ?? 'blond')),
            'check_hair_color'         => abs((int)($obj->check_hair_color ?? 0)),
            'eye_color'                => self::decodeLegacyText((string)($obj->eye_color ?? 'Hellblau')),
            'check_eye_color'          => abs((int)($obj->check_eye_color ?? 0)),
            'cup_size_a'               => $cupSizeA,
            'cup_size_b'               => $cupSizeB,
            'check_cup_size'           => abs((int)($obj->check_cup_size ?? 0)),
            'sexual_orientation'       => self::decodeLegacyText((string)($obj->sexual_orientation ?? 'Hetero')),
            'check_sexual_orientation' => abs((int)($obj->check_sexual_orientation ?? 0)),
            'marital_status'           => self::decodeLegacyText((string)($obj->marital_status ?? 'ledig')),
            'check_marital_status'     => abs((int)($obj->check_marital_status ?? 0)),
            'interests_list'           => self::parsePipeList((string)($obj->interests ?? '')),
            'check_interests'          => abs((int)($obj->check_interests ?? 0)),
            'looking_for_list'         => self::parsePipeList((string)($obj->looking_for ?? '')),
            'check_looking_for'        => abs((int)($obj->check_looking_for ?? 0)),
            'sexual_preferences_list'  => self::parsePipeList((string)($obj->sexual_preferences ?? '')),
            'check_sexual_preferences' => abs((int)($obj->check_sexual_preferences ?? 0)),
            'movies_count'             => abs((int)($mCntObj->cnt ?? 0)),
            'albums_count'             => abs((int)($aCntObj->cnt ?? 0)),
        ];

        $merchant         = new \Merchant($mysql, $merchantId);
        $minActorProfiles = abs((int)$merchant->minimum_number_actor_profiles());

        $rsCount = p4c_query("SELECT COUNT(*) AS `cnt`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
        $cntObj      = ($rsCount && p4c_num_rows($rsCount) > 0) ? p4c_fetch_object($rsCount) : null;
        $countActors = abs((int)($cntObj->cnt ?? 0));

        return View::render('Actors', 'form', [
            'mode'             => 'edit',
            'actor'            => $actorData,
            'defs'             => self::getFieldDefinitions(),
            'countActors'      => $countActors,
            'minActorProfiles' => $minActorProfiles,
        ]);
    }

    /**
     * POST/GET: Prüft live, ob ein Darsteller-Benutzername noch frei ist (actors/check-username)
     */
    public function checkUsername(): void
    {
        $rawUsername = trim((string)($this->request->post('username') ?? $this->request->get('username', '')));
        $sanitized   = self::sanitizeUsername($rawUsername);

        if ($sanitized === '' || strlen($sanitized) < 3) {
            Response::json([
                'status'    => 'success',
                'available' => false,
                'sanitized' => $sanitized,
                'message'   => 'Bitte geben Sie mindestens 3 gültige Zeichen (Buchstaben, Zahlen, Bindestrich oder Punkt) ein.',
            ]);
            return;
        }

        $rsCheck = p4c_query("SELECT `username`
            FROM `actors`
            WHERE `username` = '" . p4c_escape_string($sanitized) . "'
            LIMIT 1;", __FILE__, __LINE__);

        $isAvailable = (!$rsCheck || p4c_num_rows($rsCheck) === 0);

        Response::json([
            'status'    => 'success',
            'available' => $isAvailable,
            'sanitized' => $sanitized,
            'message'   => $isAvailable
                ? "Der Benutzername „{$sanitized}“ ist verfügbar."
                : "Der Benutzername „{$sanitized}“ ist leider bereits vergeben.",
        ]);
    }

    /**
     * POST: Legt ein neues Darsteller-Profil an (actors/create)
     */
    public function create(): void
    {
        global $mysql;

        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrfToken = (string)$this->request->post('csrf_token', '');
        if (!Csrf::validate($csrfToken)) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            Response::error('Ihre Sitzung ist abgelaufen.', 401);
            return;
        }

        $merchant         = new \Merchant($mysql, $merchantId);
        $minActorProfiles = abs((int)$merchant->minimum_number_actor_profiles());

        $rsCount = p4c_query("SELECT COUNT(*) AS `cnt`
            FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted';", __FILE__, __LINE__);
        $cntObj      = ($rsCount && p4c_num_rows($rsCount) > 0) ? p4c_fetch_object($rsCount) : null;
        $countActors = abs((int)($cntObj->cnt ?? 0));

        if ($countActors >= $minActorProfiles) {
            Response::error('Sie haben das Limit Ihrer freigeschalteten Darsteller-Profile bereits erreicht.', 400);
            return;
        }

        $rawUsername = trim((string)$this->request->post('username', ''));
        $username    = self::sanitizeUsername($rawUsername);

        if ($username === '' || strlen($username) < 3) {
            Response::error('Bitte geben Sie einen gültigen Benutzernamen (mindestens 3 Zeichen) ein.', 400);
            return;
        }

        $rsCheck = p4c_query("SELECT `username`
            FROM `actors`
            WHERE `username` = '" . p4c_escape_string($username) . "'
            LIMIT 1;", __FILE__, __LINE__);

        if ($rsCheck && p4c_num_rows($rsCheck) > 0) {
            Response::error("Der Benutzername „{$username}“ ist bereits vergeben. Bitte wählen Sie einen anderen Namen.", 400);
            return;
        }

        $fields = $this->extractValidatedProfileFields();

        p4c_query("INSERT INTO `actors` SET
            `created_datetime`         = '" . date('Y-m-d H:i:s') . "',
            `merchant_id`              = '" . $merchantId . "',
            `status`                   = 'inactive',
            `username`                 = '" . p4c_escape_string($username) . "',
            `check_gender`             = '" . $fields['check_gender'] . "',
            `gender`                   = '" . p4c_escape_string($fields['gender']) . "',
            `check_age`                = '" . $fields['check_age'] . "',
            `age`                      = '" . $fields['age'] . "',
            `check_star_sign`          = '" . $fields['check_star_sign'] . "',
            `star_sign`                = '" . p4c_escape_string($fields['star_sign']) . "',
            `check_body_height`        = '" . $fields['check_body_height'] . "',
            `body_height`              = '" . $fields['body_height'] . "',
            `check_eye_color`          = '" . $fields['check_eye_color'] . "',
            `eye_color`                = '" . p4c_escape_string($fields['eye_color']) . "',
            `check_hair_color`         = '" . $fields['check_hair_color'] . "',
            `hair_color`               = '" . p4c_escape_string($fields['hair_color']) . "',
            `check_body_weight`        = '" . $fields['check_body_weight'] . "',
            `body_weight`              = '" . $fields['body_weight'] . "',
            `check_cup_size`           = '" . $fields['check_cup_size'] . "',
            `cup_size`                 = '" . p4c_escape_string($fields['cup_size']) . "',
            `check_shaven`             = '" . $fields['check_shaven'] . "',
            `shaven`                   = '" . $fields['shaven'] . "',
            `check_marital_status`     = '" . $fields['check_marital_status'] . "',
            `marital_status`           = '" . p4c_escape_string($fields['marital_status']) . "',
            `check_sexual_orientation` = '" . $fields['check_sexual_orientation'] . "',
            `sexual_orientation`       = '" . p4c_escape_string($fields['sexual_orientation']) . "',
            `check_looking_for`        = '" . $fields['check_looking_for'] . "',
            `looking_for`              = '" . p4c_escape_string($fields['looking_for']) . "',
            `check_interests`          = '" . $fields['check_interests'] . "',
            `interests`                = '" . p4c_escape_string($fields['interests']) . "',
            `check_sexual_preferences` = '" . $fields['check_sexual_preferences'] . "',
            `sexual_preferences`       = '" . p4c_escape_string($fields['sexual_preferences']) . "',
            `check_about_me`           = '" . $fields['check_about_me'] . "',
            `about_me`                 = '" . p4c_escape_string($fields['about_me']) . "';", __FILE__, __LINE__);

        $actorId = abs((int)p4c_insert_id());
        if ($actorId === 0) {
            Response::error('Das Darsteller-Profil konnte nicht in der Datenbank angelegt werden.', 500);
            return;
        }

        // MD5-Prüfsumme für API-Synchronisation berechnen und speichern
        if (function_exists('actor_checksum')) {
            $md5 = actor_checksum($actorId);
            p4c_query("UPDATE `actors` SET `md5_checksum` = '" . p4c_escape_string((string)$md5) . "' WHERE `id` = '" . $actorId . "';", __FILE__, __LINE__);
        }

        // Zuordnung zur Standard-Gruppe des Partners (analog new_actor.php)
        $rsGroup = p4c_query("SELECT `id` FROM `groups` WHERE `merchant_id` = '" . $merchantId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsGroup && p4c_num_rows($rsGroup) > 0) {
            $groupObj = p4c_fetch_object($rsGroup);
            $groupId  = abs((int)$groupObj->id);

            $rsGroupActor = p4c_query("SELECT `id` FROM `group_actors` WHERE `actor_id` = '" . $actorId . "' LIMIT 1;", __FILE__, __LINE__);
            if (!$rsGroupActor || p4c_num_rows($rsGroupActor) === 0) {
                p4c_query("INSERT INTO `group_actors` SET
                    `merchant_id` = '" . $merchantId . "',
                    `group_id`    = '" . $groupId . "',
                    `actor_id`    = '" . $actorId . "';", __FILE__, __LINE__);
            }
        }

        if (function_exists('log_action')) {
            log_action("Darsteller-Profil angelegt: {$username} (ID: {$actorId})");
        }

        Response::json([
            'status'   => 'success',
            'actor_id' => $actorId,
            'username' => $username,
            'message'  => "Das Profil „{$username}“ wurde erfolgreich angelegt. Bitte laden Sie nun Ihre FSK16- und FSK18-Profilbilder hoch.",
        ]);
    }

    /**
     * POST: Aktualisiert ein bestehendes Darsteller-Profil (actors/update)
     */
    public function update(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrfToken = (string)$this->request->post('csrf_token', '');
        if (!Csrf::validate($csrfToken)) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $actorId    = abs((int)$this->request->post('actor_id', 0));

        if ($merchantId === 0 || $actorId === 0) {
            Response::error('Ungültige Profil-ID oder abgelaufene Sitzung.', 400);
            return;
        }

        $rsActor = p4c_query("SELECT `id`, `username`, `status`
            FROM `actors`
            WHERE `id` = '" . $actorId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            Response::error('Sie besitzen keine Berechtigung zur Bearbeitung dieses Profils.', 403);
            return;
        }

        $actorObj = p4c_fetch_object($rsActor);
        $fields   = $this->extractValidatedProfileFields();

        // Analog actor.php: Wenn Status noch 'inactive' ist, created_datetime auf 0000-00-00 00:00:00 setzen
        $createdSql = '';
        if ((string)$actorObj->status === 'inactive') {
            $createdSql = "`created_datetime` = '0000-00-00 00:00:00',";
        }

        p4c_query("UPDATE `actors` SET
            {$createdSql}
            `check_gender`             = '" . $fields['check_gender'] . "',
            `gender`                   = '" . p4c_escape_string($fields['gender']) . "',
            `check_age`                = '" . $fields['check_age'] . "',
            `age`                      = '" . $fields['age'] . "',
            `check_star_sign`          = '" . $fields['check_star_sign'] . "',
            `star_sign`                = '" . p4c_escape_string($fields['star_sign']) . "',
            `check_body_height`        = '" . $fields['check_body_height'] . "',
            `body_height`              = '" . $fields['body_height'] . "',
            `check_eye_color`          = '" . $fields['check_eye_color'] . "',
            `eye_color`                = '" . p4c_escape_string($fields['eye_color']) . "',
            `check_hair_color`         = '" . $fields['check_hair_color'] . "',
            `hair_color`               = '" . p4c_escape_string($fields['hair_color']) . "',
            `check_body_weight`        = '" . $fields['check_body_weight'] . "',
            `body_weight`              = '" . $fields['body_weight'] . "',
            `check_cup_size`           = '" . $fields['check_cup_size'] . "',
            `cup_size`                 = '" . p4c_escape_string($fields['cup_size']) . "',
            `check_shaven`             = '" . $fields['check_shaven'] . "',
            `shaven`                   = '" . $fields['shaven'] . "',
            `check_marital_status`     = '" . $fields['check_marital_status'] . "',
            `marital_status`           = '" . p4c_escape_string($fields['marital_status']) . "',
            `check_sexual_orientation` = '" . $fields['check_sexual_orientation'] . "',
            `sexual_orientation`       = '" . p4c_escape_string($fields['sexual_orientation']) . "',
            `check_looking_for`        = '" . $fields['check_looking_for'] . "',
            `looking_for`              = '" . p4c_escape_string($fields['looking_for']) . "',
            `check_interests`          = '" . $fields['check_interests'] . "',
            `interests`                = '" . p4c_escape_string($fields['interests']) . "',
            `check_sexual_preferences` = '" . $fields['check_sexual_preferences'] . "',
            `sexual_preferences`       = '" . p4c_escape_string($fields['sexual_preferences']) . "',
            `check_about_me`           = '" . $fields['check_about_me'] . "',
            `about_me`                 = '" . p4c_escape_string($fields['about_me']) . "'
            WHERE `id` = '" . $actorId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (function_exists('actor_checksum')) {
            $md5 = actor_checksum($actorId);
            p4c_query("UPDATE `actors` SET `md5_checksum` = '" . p4c_escape_string((string)$md5) . "' WHERE `id` = '" . $actorId . "';", __FILE__, __LINE__);
        }

        if (function_exists('log_action')) {
            log_action("Darsteller-Profil aktualisiert: " . (string)$actorObj->username . " (ID: {$actorId})");
        }

        Response::json([
            'status'   => 'success',
            'actor_id' => $actorId,
            'message'  => 'Ihre Profildaten wurden erfolgreich gespeichert.',
        ]);
    }

    /**
     * POST: Lädt ein neues FSK16- oder FSK18-Profilbild hoch (actors/upload-image)
     */
    public function uploadImage(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrfToken = (string)$this->request->post('csrf_token', '');
        if (!Csrf::validate($csrfToken)) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $actorId    = abs((int)$this->request->post('actor_id', 0));
        $fsk        = abs((int)$this->request->post('fsk', 0));

        if ($merchantId === 0 || $actorId === 0 || !in_array($fsk, [16, 18], true)) {
            Response::error('Ungültige Parameter für den Profilbild-Upload.', 400);
            return;
        }

        $rsActor = p4c_query("SELECT `id`, `username`, `profile_image_fsk16`, `profile_image_fsk18`
            FROM `actors`
            WHERE `id` = '" . $actorId . "'
              AND `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsActor || p4c_num_rows($rsActor) === 0) {
            Response::error('Sie besitzen keine Berechtigung für dieses Darsteller-Profil.', 403);
            return;
        }

        $actorObj = p4c_fetch_object($rsActor);

        if (!isset($_FILES['image']) || !is_array($_FILES['image']) || (int)$_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Bitte wählen Sie eine gültige Bilddatei (JPG oder PNG) zum Hochladen aus.', 400);
            return;
        }

        $fileTmp  = (string)$_FILES['image']['tmp_name'];
        $fileName = (string)$_FILES['image']['name'];
        $fileSize = abs((int)$_FILES['image']['size']);

        // Max. 5 MB (5242880 Bytes)
        if ($fileSize === 0 || $fileSize > 5242880) {
            Response::error('Die Bilddatei darf maximal 5 MB groß sein.', 400);
            return;
        }

        $extension = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            Response::error('Es sind ausschließlich Bilddateien im Format JPG, JPEG oder PNG erlaubt.', 400);
            return;
        }

        $imgInfo = @getimagesize($fileTmp);
        if ($imgInfo === false || !in_array($imgInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            Response::error('Die hochgeladene Datei ist kein gültiges Bild.', 400);
            return;
        }

        // Zielverzeichnis: /var/www/web1/priv/merchant_files/{merchant_id}/{actor_id}
        $baseDir   = defined('PROFILE_IMAGE_PATH') ? PROFILE_IMAGE_PATH : (dirname(__DIR__, 5) . '/priv');
        $subDir    = defined('MERCHANT_DEFAULT_DIR') ? MERCHANT_DEFAULT_DIR : 'merchant_files';
        $targetDir = rtrim($baseDir, '/\\') . '/' . $subDir . '/' . $merchantId . '/' . $actorId;

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                Response::error('Das Zielverzeichnis für Ihr Profilbild konnte nicht erstellt werden.', 500);
                return;
            }
        }

        $newFilename = strtolower(md5($actorId . '_' . microtime(true))) . '_' . $fsk . '.' . $extension;
        $destination = $targetDir . '/' . $newFilename;

        $oldColumn = ($fsk === 16) ? 'profile_image_fsk16' : 'profile_image_fsk18';
        $oldFile   = trim((string)($actorObj->$oldColumn ?? ''));

        if (!@move_uploaded_file($fileTmp, $destination)) {
            Response::error('Beim Speichern des Profilbildes auf dem Server ist ein Fehler aufgetreten.', 500);
            return;
        }

        // Altes Bild löschen, falls vorhanden
        if ($oldFile !== '' && $oldFile !== $newFilename) {
            $oldPath = $targetDir . '/' . basename($oldFile);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        p4c_query("UPDATE `actors`
            SET `" . $oldColumn . "` = '" . p4c_escape_string($newFilename) . "'
            WHERE `id` = '" . $actorId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        if (function_exists('actor_checksum')) {
            $md5 = actor_checksum($actorId);
            p4c_query("UPDATE `actors` SET `md5_checksum` = '" . p4c_escape_string((string)$md5) . "' WHERE `id` = '" . $actorId . "';", __FILE__, __LINE__);
        }

        if (function_exists('log_action')) {
            log_action("Profilbild (FSK{$fsk}) aktualisiert für " . (string)$actorObj->username . " (ID: {$actorId})");
        }

        $mcpUrl   = defined('MCP_URL') ? MCP_URL : 'https://erocloud.net';
        $imageUrl = $mcpUrl . '/ProfilePicture/' . rawurlencode($newFilename);

        Response::json([
            'status'    => 'success',
            'fsk'       => $fsk,
            'filename'  => $newFilename,
            'image_url' => $imageUrl,
            'message'   => "Ihr FSK{$fsk}-Profilbild wurde erfolgreich hochgeladen und gespeichert.",
        ]);
    }

    /**
     * Extrahiert und validiert alle Steckbrief-Felder aus dem POST-Request.
     */
    private function extractValidatedProfileFields(): array
    {
        $defs = self::getFieldDefinitions();

        $aboutMe = trim(strip_tags((string)$this->request->post('about_me', '')));
        if (function_exists('utf8_to_latin1swedishci')) {
            $aboutMe = (string)utf8_to_latin1swedishci($aboutMe);
        }

        $gender = (string)$this->request->post('gender', 'f');
        if (!array_key_exists($gender, $defs['genders'])) {
            $gender = 'f';
        }

        $age = abs((int)$this->request->post('age', 25));
        if ($age < 18 || $age > 99) {
            $age = 25;
        }

        $starSign = trim((string)$this->request->post('star_sign', 'Steinbock'));
        if (!in_array($starSign, $defs['star_signs'], true)) {
            $starSign = 'Steinbock';
        }

        $bodyHeight = abs((int)$this->request->post('body_height', 165));
        if ($bodyHeight < 100 || $bodyHeight > 230) {
            $bodyHeight = 165;
        }

        $bodyWeight = abs((int)$this->request->post('body_weight', 65));
        if ($bodyWeight < 45 || $bodyWeight > 150) {
            $bodyWeight = 65;
        }

        $hairColor = trim((string)$this->request->post('hair_color', 'blond'));
        if (!in_array($hairColor, $defs['hair_colors'], true)) {
            $hairColor = 'blond';
        }

        $eyeColor = trim((string)$this->request->post('eye_color', 'Hellblau'));
        if (!in_array($eyeColor, $defs['eye_colors'], true)) {
            $eyeColor = 'Hellblau';
        }

        $cupSizeA = trim((string)$this->request->post('cup_size_a', '75'));
        if (!in_array($cupSizeA, $defs['cup_sizes_a'], true)) {
            $cupSizeA = '75';
        }

        $cupSizeB = strtoupper(trim((string)$this->request->post('cup_size_b', 'B')));
        if (!in_array($cupSizeB, $defs['cup_sizes_b'], true)) {
            $cupSizeB = 'B';
        }

        $shaven = abs((int)$this->request->post('shaven', 0)) === 1 ? 1 : 0;

        $sexualOrientation = trim((string)$this->request->post('sexual_orientation', 'Hetero'));
        if (!in_array($sexualOrientation, $defs['sexual_orientations'], true)) {
            $sexualOrientation = 'Hetero';
        }

        $maritalStatus = trim((string)$this->request->post('marital_status', 'ledig'));
        if (!in_array($maritalStatus, $defs['marital_statuses'], true)) {
            $maritalStatus = 'ledig';
        }

        // Mehrfachauswahl-Listen validieren und als Pipe-String (|) zusammenführen
        $rawInterests = $_POST['interests'] ?? [];
        $validInterests = [];
        if (is_array($rawInterests)) {
            foreach ($rawInterests as $item) {
                $itemStr = trim((string)$item);
                if (in_array($itemStr, $defs['interests'], true)) {
                    $validInterests[] = $itemStr;
                }
            }
        }

        $rawLookingFor = $_POST['looking_for'] ?? [];
        $validLookingFor = [];
        if (is_array($rawLookingFor)) {
            foreach ($rawLookingFor as $item) {
                $itemStr = trim((string)$item);
                if (in_array($itemStr, $defs['looking_for'], true)) {
                    $validLookingFor[] = $itemStr;
                }
            }
        }

        $rawPrefs = $_POST['sexual_preferences'] ?? [];
        $validPrefs = [];
        if (is_array($rawPrefs)) {
            foreach ($rawPrefs as $item) {
                $itemStr = trim((string)$item);
                if (in_array($itemStr, $defs['sexual_preferences'], true)) {
                    $validPrefs[] = $itemStr;
                }
            }
        }

        return [
            'about_me'                 => $aboutMe,
            'check_about_me'           => $this->request->post('check_about_me') !== null ? 1 : 0,
            'gender'                   => $gender,
            'check_gender'             => $this->request->post('check_gender') !== null ? 1 : 0,
            'age'                      => $age,
            'check_age'                => $this->request->post('check_age') !== null ? 1 : 0,
            'star_sign'                => $starSign,
            'check_star_sign'          => $this->request->post('check_star_sign') !== null ? 1 : 0,
            'body_height'              => $bodyHeight,
            'check_body_height'        => $this->request->post('check_body_height') !== null ? 1 : 0,
            'body_weight'              => $bodyWeight,
            'check_body_weight'        => $this->request->post('check_body_weight') !== null ? 1 : 0,
            'hair_color'               => $hairColor,
            'check_hair_color'         => $this->request->post('check_hair_color') !== null ? 1 : 0,
            'eye_color'                => $eyeColor,
            'check_eye_color'          => $this->request->post('check_eye_color') !== null ? 1 : 0,
            'cup_size'                 => $cupSizeA . $cupSizeB,
            'check_cup_size'           => $this->request->post('check_cup_size') !== null ? 1 : 0,
            'shaven'                   => $shaven,
            'check_shaven'             => $this->request->post('check_shaven') !== null ? 1 : 0,
            'sexual_orientation'       => $sexualOrientation,
            'check_sexual_orientation' => $this->request->post('check_sexual_orientation') !== null ? 1 : 0,
            'marital_status'           => $maritalStatus,
            'check_marital_status'     => $this->request->post('check_marital_status') !== null ? 1 : 0,
            'interests'                => implode('|', $validInterests),
            'check_interests'          => $this->request->post('check_interests') !== null ? 1 : 0,
            'looking_for'              => implode('|', $validLookingFor),
            'check_looking_for'        => $this->request->post('check_looking_for') !== null ? 1 : 0,
            'sexual_preferences'       => implode('|', $validPrefs),
            'check_sexual_preferences' => $this->request->post('check_sexual_preferences') !== null ? 1 : 0,
        ];
    }

    /**
     * Bereinigt den Darsteller-Benutzernamen exakt nach den EroCloud-Vorgaben (analog new_actor.php).
     */
    public static function sanitizeUsername(string $username): string
    {
        $search   = ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'];
        $replace  = ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue'];
        $username = str_replace($search, $replace, trim($username));
        return (string)preg_replace('/[^a-zA-Z0-9-_.]/', '', $username);
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

    /**
     * Zerlegt einen Pipe-separierten String (|) in ein Array dekodierter UTF-8-Strings.
     */
    private static function parsePipeList(string $pipeString): array
    {
        if (trim($pipeString) === '') {
            return [];
        }
        $parts  = explode('|', $pipeString);
        $result = [];
        foreach ($parts as $part) {
            $decoded = trim(self::decodeLegacyText($part));
            if ($decoded !== '') {
                $result[] = $decoded;
            }
        }
        return array_values(array_unique($result));
    }
}
