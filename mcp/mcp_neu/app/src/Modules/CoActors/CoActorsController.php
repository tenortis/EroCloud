<?php
declare(strict_types=1);

namespace App\Modules\CoActors;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP (v2.0) - Drehpartner & Identitäts-/Freigabe-Verwaltung (CoActorsController)
 *
 * Verwaltet:
 * - Den eigenen "Ich"-Eintrag des Account-Inhabers (is_owner = 1) per Smartphone-QR-Code
 * - Weitere Drehpartner (is_owner = 0) mit Creator-Name + Klarname in Klammern
 * - Live-QR-Code-Generierung & Echtzeit-Status-Polling während der Smartphone-Aufnahme
 * - Manuellen Dokumenten-Upload für vergangene/bestehende Filme inkl. späterem QR-Upgrade ("Wiedersehen")
 */
class CoActorsController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * GET: Hauptansicht „Meine Drehpartner & ID-Check“ (#/co-actors)
     */
    public function list(): string
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            return '<div class="alert alert-danger m-4">Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }
        @session_write_close();

        \CoActorManager::ensureSchema();
        $merchantIdent = \CoActorManager::getMerchantIdentity($merchantId);
        $allCoActors   = \CoActorManager::getMerchantCoActors($merchantId, true);

        $ownerCoActor = null;
        $otherActors  = [];
        $countVerified = 0;
        $countPending  = 0;
        $countAiReady  = 0;

        foreach ($allCoActors as $ca) {
            if (!empty($ca['ai_face_ready'])) {
                $countAiReady++;
            }
            if ($ca['checked_status'] === 'verified') {
                $countVerified++;
            } elseif ($ca['checked_status'] === 'under_review') {
                $countPending++;
            }

            if (!empty($ca['is_owner'])) {
                $ownerCoActor = $ca;
            } else {
                $otherActors[] = $ca;
            }
        }

        // Ermitteln, in wie vielen Filmen jeder Drehpartner bereits verknüpft ist
        $usageCounts = [];
        $rsUsage = p4c_query("SELECT `co_actor_id`, COUNT(*) AS `cnt`
            FROM `content_co_actors`
            WHERE `merchant_id` = '" . $merchantId . "'
            GROUP BY `co_actor_id`;", __FILE__, __LINE__);
        if ($rsUsage && p4c_num_rows($rsUsage) > 0) {
            while ($uRow = p4c_fetch_object($rsUsage)) {
                $usageCounts[abs((int)$uRow->co_actor_id)] = abs((int)$uRow->cnt);
            }
        }

        return View::render('CoActors', 'list', [
            'merchantIdent' => $merchantIdent,
            'ownerCoActor'  => $ownerCoActor,
            'coActors'      => $otherActors,
            'usageCounts'   => $usageCounts,
            'countTotal'    => count($otherActors),
            'countVerified' => $countVerified,
            'countPending'  => $countPending,
            'countAiReady'  => $countAiReady,
        ]);
    }

    /**
     * POST: Erstellt einen neuen Drehpartner für den QR-Code-Smartphone-Flow
     * oder liefert den QR-Code für einen bestehenden Drehpartner / „Ich“ (co-actors/create-qr)
     */
    public function createQr(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            Response::error('Deine Sitzung ist abgelaufen.', 401);
            return;
        }

        \CoActorManager::ensureSchema();

        $coActorId = abs((int)$this->request->post('co_actor_id', 0));
        $isOwner   = abs((int)$this->request->post('is_owner', 0)) === 1;

        if ($isOwner) {
            $owner = \CoActorManager::ensureOwnerCoActor($merchantId);
            $coActorId = (int)($owner['id'] ?? 0);
        }

        if ($coActorId > 0) {
            // Bestehenden Eintrag laden und ggf. Namen aktualisieren
            $existing = \CoActorManager::getCoActorById($coActorId, $merchantId);
            if ($existing === null) {
                Response::error('Der gewählte Drehpartner wurde nicht gefunden.', 404);
                return;
            }

            $firstName   = trim(strip_tags((string)$this->request->post('first_name', (string)$existing['first_name'])));
            $lastName    = trim(strip_tags((string)$this->request->post('last_name', (string)$existing['last_name'])));
            $creatorName = trim(strip_tags((string)$this->request->post('creator_name', (string)$existing['creator_name'])));

            if ($firstName !== '' || $lastName !== '' || $creatorName !== '') {
                p4c_query("UPDATE `co_actors` SET
                    `creator_name`        = '" . p4c_escape_string($creatorName) . "',
                    `nickname_not_public` = AES_ENCRYPT('" . p4c_escape_string($creatorName !== '' ? $creatorName : ($firstName . ' ' . $lastName)) . "', '" . AES_KEY . "'),
                    `first_name`          = AES_ENCRYPT('" . p4c_escape_string($firstName) . "', '" . AES_KEY . "'),
                    `last_name`           = AES_ENCRYPT('" . p4c_escape_string($lastName) . "', '" . AES_KEY . "')
                    WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                    LIMIT 1;", __FILE__, __LINE__);
            }

            if ((string)$existing['qr_token'] === '') {
                \CoActorManager::regenerateQrToken($coActorId, $merchantId);
            }
        } else {
            // Neuen Drehpartner anlegen
            $firstName   = trim(strip_tags((string)$this->request->post('first_name', '')));
            $lastName    = trim(strip_tags((string)$this->request->post('last_name', '')));
            $creatorName = trim(strip_tags((string)$this->request->post('creator_name', '')));

            if ($firstName === '' || $lastName === '') {
                Response::error('Bitte gib den Vor- und Nachnamen des Drehpartners an.', 400);
                return;
            }

            $token   = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 86400 * 7);

            p4c_query("INSERT INTO `co_actors` SET
                `merchant_id`         = '" . $merchantId . "',
                `is_owner`            = 0,
                `creator_name`        = '" . p4c_escape_string($creatorName) . "',
                `nickname_not_public` = AES_ENCRYPT('" . p4c_escape_string($creatorName !== '' ? $creatorName : ($firstName . ' ' . $lastName)) . "', '" . AES_KEY . "'),
                `first_name`          = AES_ENCRYPT('" . p4c_escape_string($firstName) . "', '" . AES_KEY . "'),
                `last_name`           = AES_ENCRYPT('" . p4c_escape_string($lastName) . "', '" . AES_KEY . "'),
                `birth_date`          = AES_ENCRYPT('', '" . AES_KEY . "'),
                `street`              = AES_ENCRYPT('', '" . AES_KEY . "'),
                `zip_code`            = AES_ENCRYPT('', '" . AES_KEY . "'),
                `city`                = AES_ENCRYPT('', '" . AES_KEY . "'),
                `country`             = 'DE',
                `id_doc_type`         = 'id_card',
                `id_doc_number`       = AES_ENCRYPT('', '" . AES_KEY . "'),
                `verification_method` = 'qr_live',
                `qr_token`            = '" . p4c_escape_string($token) . "',
                `qr_token_expires_at` = '" . p4c_escape_string($expires) . "',
                `checked_status`      = 'pending_qr',
                `create_datetime`     = '" . date('Y-m-d H:i:s') . "',
                `status`              = 'active';", __FILE__, __LINE__);

            $coActorId = abs((int)p4c_insert_id());
            if ($coActorId === 0) {
                Response::error('Der Drehpartner konnte nicht angelegt werden.', 500);
                return;
            }
        }

        $coActor = \CoActorManager::getCoActorById($coActorId, $merchantId);
        if ($coActor === null) {
            Response::error('Fehler beim Laden des Drehpartners.', 500);
            return;
        }

        $verifyUrl = (string)$coActor['qr_verify_url'];
        $qrSvg     = \CoActorManager::generateQrSvg($verifyUrl, 240);

        Response::json([
            'status'      => 'ok',
            'co_actor'    => $coActor,
            'verify_url'  => $verifyUrl,
            'qr_svg'      => $qrSvg,
            'all_actors'  => \CoActorManager::getMerchantCoActors($merchantId, true),
            'message'     => 'QR-Code bereit! Scanne ihn jetzt mit dem Smartphone.',
        ]);
    }

    /**
     * POST: Manueller Dokumenten-Upload für vergangene / bereits hochgeladene Filme (co-actors/save-legacy)
     * Hinweis: Bleibt jederzeit per QR-Code auf einen frischen Live-ID-Shot upgradebar ("Wiedersehen"-Funktion).
     */
    public function saveLegacy(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF). Bitte lade die Seite neu.', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            Response::error('Deine Sitzung ist abgelaufen.', 401);
            return;
        }

        \CoActorManager::ensureSchema();

        $coActorId   = abs((int)$this->request->post('co_actor_id', 0));
        $firstName   = trim(strip_tags((string)$this->request->post('first_name', '')));
        $lastName    = trim(strip_tags((string)$this->request->post('last_name', '')));
        $creatorName = trim(strip_tags((string)$this->request->post('creator_name', '')));
        $birthDate   = \CoActorManager::normalizeDate((string)$this->request->post('birth_date', ''));
        $street      = trim(strip_tags((string)$this->request->post('street', '')));
        $zipCode     = trim(strip_tags((string)$this->request->post('zip_code', '')));
        $city        = trim(strip_tags((string)$this->request->post('city', '')));
        $country     = strtoupper(substr(trim(strip_tags((string)$this->request->post('country', 'DE'))), 0, 5));
        $idDocNumber = trim(strip_tags((string)$this->request->post('id_doc_number', '')));

        $errors = [];
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'Bitte gib den vollständigen Vor- und Nachnamen des Drehpartners an.';
        }
        if ($birthDate !== '') {
            $ageYears = (int)floor((time() - (int)strtotime($birthDate)) / (365.2425 * 86400));
            if ($ageYears < 18) {
                $errors[] = 'Die Person muss mindestens 18 Jahre alt sein.';
            }
        }

        $existing = null;
        if ($coActorId > 0) {
            $existing = \CoActorManager::getCoActorById($coActorId, $merchantId);
            if ($existing === null) {
                Response::error('Der gewählte Drehpartner wurde nicht gefunden.', 404);
                return;
            }
        }

        if (!empty($errors)) {
            Response::error(implode(' ', $errors), 400);
            return;
        }

        if ($existing === null) {
            $token   = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 86400 * 7);
            p4c_query("INSERT INTO `co_actors` SET
                `merchant_id`         = '" . $merchantId . "',
                `is_owner`            = 0,
                `creator_name`        = '" . p4c_escape_string($creatorName) . "',
                `nickname_not_public` = AES_ENCRYPT('" . p4c_escape_string($creatorName !== '' ? $creatorName : ($firstName . ' ' . $lastName)) . "', '" . AES_KEY . "'),
                `first_name`          = AES_ENCRYPT('" . p4c_escape_string($firstName) . "', '" . AES_KEY . "'),
                `last_name`           = AES_ENCRYPT('" . p4c_escape_string($lastName) . "', '" . AES_KEY . "'),
                `birth_date`          = AES_ENCRYPT('" . p4c_escape_string($birthDate) . "', '" . AES_KEY . "'),
                `street`              = AES_ENCRYPT('" . p4c_escape_string($street) . "', '" . AES_KEY . "'),
                `zip_code`            = AES_ENCRYPT('" . p4c_escape_string($zipCode) . "', '" . AES_KEY . "'),
                `city`                = AES_ENCRYPT('" . p4c_escape_string($city) . "', '" . AES_KEY . "'),
                `country`             = '" . p4c_escape_string($country) . "',
                `id_doc_type`         = 'id_card',
                `id_doc_number`       = AES_ENCRYPT('" . p4c_escape_string($idDocNumber) . "', '" . AES_KEY . "'),
                `verification_method` = 'manual_legacy',
                `qr_token`            = '" . p4c_escape_string($token) . "',
                `qr_token_expires_at` = '" . p4c_escape_string($expires) . "',
                `checked_status`      = 'pending_qr',
                `create_datetime`     = '" . date('Y-m-d H:i:s') . "',
                `status`              = 'active';", __FILE__, __LINE__);
            $coActorId = abs((int)p4c_insert_id());
            $existing  = \CoActorManager::getCoActorById($coActorId, $merchantId);
        }

        if ($coActorId <= 0 || $existing === null) {
            Response::error('Fehler beim Speichern des Drehpartners.', 500);
            return;
        }

        $storageDir = \CoActorManager::getStorageDir($merchantId, $coActorId);

        $uploadDoc = static function (string $inputKey, string $prefix, array $allowedExts) use ($storageDir): string {
            if (!isset($_FILES[$inputKey]) || !is_array($_FILES[$inputKey]) || (int)$_FILES[$inputKey]['error'] !== UPLOAD_ERR_OK) {
                return '';
            }
            $tmp  = (string)$_FILES[$inputKey]['tmp_name'];
            $name = (string)$_FILES[$inputKey]['name'];
            $size = abs((int)$_FILES[$inputKey]['size']);
            $ext  = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
            if ($size === 0 || $size > 15728640 || !in_array($ext, $allowedExts, true)) {
                return '';
            }
            $targetName = $prefix . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
            if (@move_uploaded_file($tmp, $storageDir . '/' . $targetName)) {
                return $targetName;
            }
            return '';
        };

        $idShotFile  = $uploadDoc('id_shot_file', 'legacy_id_shot', ['jpg', 'jpeg', 'png', 'webp']);
        $idFrontFile = $uploadDoc('id_front_file', 'legacy_id_front', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
        $consentFile = $uploadDoc('consent_doc_file', 'legacy_consent', ['pdf', 'jpg', 'jpeg', 'png', 'webp']);

        if ($idShotFile === '') {
            $idShotFile = (string)$existing['id_shot_img1'];
        }
        if ($idFrontFile === '') {
            $idFrontFile = (string)$existing['id_front_img'];
        }
        if ($consentFile === '') {
            $consentFile = (string)$existing['consent_doc_file'];
        }

        if ($idShotFile === '' || $consentFile === '') {
            Response::error('Bitte lade für bestehende Filme sowohl den ID-Shot (Person mit Ausweis neben dem Gesicht) als auch die unterschriebene Einverständniserklärung (PDF oder Foto) hoch – oder nutze beim nächsten Wiedersehen einfach den QR-Code!', 400);
            return;
        }

        $now = date('Y-m-d H:i:s');
        p4c_query("UPDATE `co_actors` SET
            `creator_name`        = '" . p4c_escape_string($creatorName) . "',
            `nickname_not_public` = AES_ENCRYPT('" . p4c_escape_string($creatorName !== '' ? $creatorName : ($firstName . ' ' . $lastName)) . "', '" . AES_KEY . "'),
            `first_name`          = AES_ENCRYPT('" . p4c_escape_string($firstName) . "', '" . AES_KEY . "'),
            `last_name`           = AES_ENCRYPT('" . p4c_escape_string($lastName) . "', '" . AES_KEY . "'),
            `birth_date`          = AES_ENCRYPT('" . p4c_escape_string($birthDate) . "', '" . AES_KEY . "'),
            `street`              = AES_ENCRYPT('" . p4c_escape_string($street) . "', '" . AES_KEY . "'),
            `zip_code`            = AES_ENCRYPT('" . p4c_escape_string($zipCode) . "', '" . AES_KEY . "'),
            `city`                = AES_ENCRYPT('" . p4c_escape_string($city) . "', '" . AES_KEY . "'),
            `country`             = '" . p4c_escape_string($country) . "',
            `id_doc_number`       = AES_ENCRYPT('" . p4c_escape_string($idDocNumber) . "', '" . AES_KEY . "'),
            `verification_method` = 'manual_legacy',
            `id_shot_img1`        = '" . p4c_escape_string($idShotFile) . "',
            `id_front_img`        = '" . p4c_escape_string($idFrontFile) . "',
            `consent_doc_file`    = '" . p4c_escape_string($consentFile) . "',
            `ai_face_ready`       = 0,
            `checked_status`      = 'under_review',
            `rejection_reason`    = '',
            `submitted_datetime`  = '" . p4c_escape_string($now) . "'
            WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        $updated = \CoActorManager::getCoActorById($coActorId, $merchantId);

        Response::json([
            'status'     => 'ok',
            'co_actor'   => $updated,
            'all_actors' => \CoActorManager::getMerchantCoActors($merchantId, true),
            'message'    => 'Die Unterlagen für den Drehpartner wurden gespeichert und zur Prüfung eingereicht. Tipp: Wenn du die Person demnächst wiedersiehst, kannst du jederzeit den QR-Code nutzen, um einen frischen KI-fähigen Live-ID-Shot aufzunehmen!',
        ]);
    }

    /**
     * GET: Prüft im Hintergrund live, ob der QR-Code-Durchlauf auf dem Smartphone abgeschlossen wurde (co-actors/poll-status)
     */
    public function pollStatus(): void
    {
        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        if ($merchantId === 0) {
            @session_write_close();
            Response::error('Sitzung abgelaufen.', 401);
            return;
        }
        @session_write_close();

        $coActorId = abs((int)$this->request->get('id', 0));
        $coActor   = $coActorId > 0 ? \CoActorManager::getCoActorById($coActorId, $merchantId) : null;
        $allActors = \CoActorManager::getMerchantCoActors($merchantId, true);

        Response::json([
            'status'     => 'ok',
            'co_actor'   => $coActor,
            'all_actors' => $allActors,
        ]);
    }

    /**
     * POST: Löscht einen noch nicht in veröffentlichten Filmen gebundenen Drehpartner (co-actors/delete)
     */
    public function delete(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }
        if (!$this->hasValidCsrf()) {
            Response::error('Ungültiges Sicherheits-Token (CSRF).', 403);
            return;
        }

        $merchantId = isset($_SESSION['merchant_id']) ? abs((int)$_SESSION['merchant_id']) : 0;
        $coActorId  = abs((int)$this->request->post('co_actor_id', $this->request->post('id', 0)));

        if ($merchantId === 0 || $coActorId === 0) {
            Response::error('Ungültige Parameter.', 400);
            return;
        }

        $existing = \CoActorManager::getCoActorById($coActorId, $merchantId);
        if ($existing === null) {
            Response::error('Drehpartner wurde nicht gefunden.', 404);
            return;
        }
        if (!empty($existing['is_owner'])) {
            Response::error('Dein eigener Account-Inhaber-Eintrag („Ich“) kann nicht gelöscht werden.', 400);
            return;
        }

        $rsUsed = p4c_query("SELECT COUNT(*) AS `cnt`
            FROM `content_co_actors`
            WHERE `co_actor_id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);
        $usedCnt = ($rsUsed && p4c_num_rows($rsUsed) > 0) ? abs((int)p4c_fetch_object($rsUsed)->cnt) : 0;

        if ($usedCnt > 0) {
            Response::error("Dieser Drehpartner ist bereits mit {$usedCnt} Film(en) verknüpft und darf aus rechtlichen Nachweisgründen nicht gelöscht werden.", 400);
            return;
        }

        p4c_query("UPDATE `co_actors` SET `status` = 'deleted'
            WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "' AND `is_owner` = 0
            LIMIT 1;", __FILE__, __LINE__);

        Response::json([
            'status'     => 'ok',
            'all_actors' => \CoActorManager::getMerchantCoActors($merchantId, true),
            'message'    => 'Der Drehpartner wurde entfernt.',
        ]);
    }

    private function hasValidCsrf(): bool
    {
        $token = (string)$this->request->post('_csrf', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        return Csrf::validate($token);
    }
}
