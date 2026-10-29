<?php
declare(strict_types=1);

/**
 * EroCloud Central Co-Actor & Performer Verification Manager (MCP v2 & ACP)
 * Compatible with PHP 8.0.30 and PHP 8.4.27
 *
 * Verwaltet:
 * 1. Den Account-Inhaber selbst ("Ich" / is_owner = 1) inkl. Pflicht-ID-Shot per Smartphone-QR-Code
 * 2. Weitere Drehpartner (Co-Actors / is_owner = 0) mit Creator-Name + Klarname in Klammern
 * 3. Live-Verifizierung per Smartphone-QR-Code (Frontal-Selfie für KI-Bilderkennung + ID-Shot + Ausweis + Finger-Unterschrift)
 * 4. Manueller Dokumenten-Upload für vergangene/ältere Filme + jederzeitiges QR-Upgrade ("Wiedersehen"-Funktion)
 * 5. Universelle Content-Verknüpfung (content_co_actors) für Filme ('movie') und vorbereitet für Fotoalben ('photo_album')
 */

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

class CoActorManager
{
    /**
     * Kategorien aus `number_of_people`, die zwingend mindestens 2 Personen voraussetzen
     */
    public const MULTI_PERSON_CATEGORIES = [
        'couples'      => 'Paar',
        'straight_sex' => 'Hetero (Mann/Frau)',
        'lesbian'      => 'Lesben',
        'gay'          => 'Schwul',
        'bisexual'     => 'Bisexuell',
        'threesome'    => 'Dreier',
        'orgy'         => 'Gruppensex',
    ];

    /**
     * Kategorien, die mindestens 3 Personen voraussetzen
     */
    public const THREE_PLUS_CATEGORIES = [
        'threesome' => 'Dreier',
        'orgy'      => 'Gruppensex',
    ];

    /**
     * Alias für ensureSchema() zur Abwärtskompatibilität.
     */
    public static function ensureTablesExist(): void
    {
        self::ensureSchema();
    }

    /**
     * Stellt sicher, dass alle Tabellen (`co_actors`, `content_co_actors`) und Spalten in `movies` & `photo_albums` existieren.
     */
    public static function ensureSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        // 1. SQL-Mode für Legacy-Tabellen mit '0000-00-00 00:00:00' Defaults temporär lockern
        p4c_query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';", __FILE__, __LINE__);

        // Spalten in `co_actors` prüfen und bei Bedarf ergänzen
        $existingCols = [];
        $rsCols = p4c_query("SHOW COLUMNS FROM `co_actors`;", __FILE__, __LINE__);
        if ($rsCols && p4c_num_rows($rsCols) > 0) {
            while ($col = p4c_fetch_object($rsCols)) {
                $existingCols[strtolower((string)$col->Field)] = (string)$col->Type;
            }
        }

        if (empty($existingCols)) {
            p4c_query("CREATE TABLE IF NOT EXISTS `co_actors` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `merchant_id` INT(11) NOT NULL DEFAULT 0,
                `is_owner` TINYINT(1) NOT NULL DEFAULT 0,
                `creator_name` VARCHAR(100) NOT NULL DEFAULT '',
                `nickname_not_public` VARBINARY(128) NULL DEFAULT NULL,
                `first_name` VARBINARY(128) NULL DEFAULT NULL,
                `last_name` VARBINARY(128) NULL DEFAULT NULL,
                `birth_date` VARBINARY(128) NULL DEFAULT NULL,
                `street` VARBINARY(128) NULL DEFAULT NULL,
                `zip_code` VARBINARY(128) NULL DEFAULT NULL,
                `city` VARBINARY(128) NULL DEFAULT NULL,
                `country` VARCHAR(5) NOT NULL DEFAULT 'DE',
                `id_doc_type` VARCHAR(30) NOT NULL DEFAULT 'id_card',
                `id_doc_number` VARBINARY(128) NULL DEFAULT NULL,
                `id_doc_valid_until` VARCHAR(20) NOT NULL DEFAULT '',
                `verification_method` VARCHAR(20) NOT NULL DEFAULT 'qr_live',
                `qr_token` VARCHAR(64) NOT NULL DEFAULT '',
                `qr_token_expires_at` DATETIME NULL DEFAULT NULL,
                `face_ref_img` VARCHAR(120) NOT NULL DEFAULT '',
                `id_shot_img1` VARCHAR(120) NOT NULL DEFAULT '',
                `id_front_img` VARCHAR(120) NOT NULL DEFAULT '',
                `id_back_img` VARCHAR(120) NOT NULL DEFAULT '',
                `consent_doc_file` VARCHAR(120) NOT NULL DEFAULT '',
                `signature_data_json` MEDIUMTEXT NULL DEFAULT NULL,
                `ai_face_ready` TINYINT(1) NOT NULL DEFAULT 0,
                `ai_face_embedding` MEDIUMTEXT NULL DEFAULT NULL,
                `checked_status` VARCHAR(25) NOT NULL DEFAULT 'pending_qr',
                `rejection_reason` TEXT NULL DEFAULT NULL,
                `create_datetime` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `submitted_datetime` DATETIME NULL DEFAULT NULL,
                `checked_on` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `checked_by_employee` INT(11) NOT NULL DEFAULT 0,
                `status` VARCHAR(15) NOT NULL DEFAULT 'active',
                PRIMARY KEY (`id`),
                KEY `idx_merchant_owner` (`merchant_id`, `is_owner`),
                KEY `idx_qr_token` (`qr_token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", __FILE__, __LINE__);
        } else {
            // Bestehende Legacy-Spalten nullable / ausreichend breit machen
            p4c_query("ALTER TABLE `co_actors`
                MODIFY `merchant_id` INT(11) NOT NULL DEFAULT 0,
                MODIFY `nickname_not_public` VARBINARY(128) NULL DEFAULT NULL,
                MODIFY `first_name` VARBINARY(128) NULL DEFAULT NULL,
                MODIFY `last_name` VARBINARY(128) NULL DEFAULT NULL,
                MODIFY `street` VARBINARY(128) NULL DEFAULT NULL,
                MODIFY `city` VARBINARY(128) NULL DEFAULT NULL,
                MODIFY `zip_code` VARBINARY(128) NULL DEFAULT NULL,
                MODIFY `id_shot_img1` VARCHAR(120) NOT NULL DEFAULT '',
                MODIFY `checked_status` VARCHAR(25) NOT NULL DEFAULT 'pending_qr';", __FILE__, __LINE__);

            $alterations = [
                'is_owner'            => "ADD COLUMN `is_owner` TINYINT(1) NOT NULL DEFAULT 0",
                'creator_name'        => "ADD COLUMN `creator_name` VARCHAR(100) NOT NULL DEFAULT ''",
                'birth_date'          => "ADD COLUMN `birth_date` VARBINARY(128) NULL DEFAULT NULL",
                'country'             => "ADD COLUMN `country` VARCHAR(5) NOT NULL DEFAULT 'DE'",
                'id_doc_type'         => "ADD COLUMN `id_doc_type` VARCHAR(30) NOT NULL DEFAULT 'id_card'",
                'id_doc_number'       => "ADD COLUMN `id_doc_number` VARBINARY(128) NULL DEFAULT NULL",
                'id_doc_valid_until'  => "ADD COLUMN `id_doc_valid_until` VARCHAR(20) NOT NULL DEFAULT ''",
                'verification_method' => "ADD COLUMN `verification_method` VARCHAR(20) NOT NULL DEFAULT 'qr_live'",
                'qr_token'            => "ADD COLUMN `qr_token` VARCHAR(64) NOT NULL DEFAULT ''",
                'qr_token_expires_at' => "ADD COLUMN `qr_token_expires_at` DATETIME NULL DEFAULT NULL",
                'face_ref_img'        => "ADD COLUMN `face_ref_img` VARCHAR(120) NOT NULL DEFAULT ''",
                'id_front_img'        => "ADD COLUMN `id_front_img` VARCHAR(120) NOT NULL DEFAULT ''",
                'id_back_img'         => "ADD COLUMN `id_back_img` VARCHAR(120) NOT NULL DEFAULT ''",
                'consent_doc_file'    => "ADD COLUMN `consent_doc_file` VARCHAR(120) NOT NULL DEFAULT ''",
                'signature_data_json' => "ADD COLUMN `signature_data_json` MEDIUMTEXT NULL DEFAULT NULL",
                'ai_face_ready'       => "ADD COLUMN `ai_face_ready` TINYINT(1) NOT NULL DEFAULT 0",
                'ai_face_embedding'   => "ADD COLUMN `ai_face_embedding` MEDIUMTEXT NULL DEFAULT NULL",
                'rejection_reason'    => "ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL",
                'submitted_datetime'  => "ADD COLUMN `submitted_datetime` DATETIME NULL DEFAULT NULL",
                'status'              => "ADD COLUMN `status` VARCHAR(15) NOT NULL DEFAULT 'active'",
            ];

            $toAdd = [];
            foreach ($alterations as $colName => $sqlPart) {
                if (!isset($existingCols[$colName])) {
                    $toAdd[] = $sqlPart;
                }
            }
            if (!empty($toAdd)) {
                p4c_query("ALTER TABLE `co_actors` " . implode(', ', $toAdd) . ";", __FILE__, __LINE__);
            }
        }

        // 2. Universelle Zuordnungstabelle `content_co_actors` (für Filme und vorbereitet für Fotoalben)
        p4c_query("CREATE TABLE IF NOT EXISTS `content_co_actors` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `content_type` VARCHAR(20) NOT NULL DEFAULT 'movie',
            `content_id` INT(11) UNSIGNED NOT NULL,
            `merchant_id` INT(11) UNSIGNED NOT NULL,
            `co_actor_id` INT(11) UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_content_co_actor` (`content_type`, `content_id`, `co_actor_id`),
            KEY `idx_merchant_content` (`merchant_id`, `content_type`, `content_id`),
            KEY `idx_co_actor` (`co_actor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", __FILE__, __LINE__);

        // 3. Spalten `performer_mode` und `owner_in_content` in `movies` und `photo_albums` sicherstellen
        foreach (['movies', 'photo_albums'] as $tbl) {
            $tblCols = [];
            $rsTbl = p4c_query("SHOW COLUMNS FROM `{$tbl}` LIKE 'performer_mode';", __FILE__, __LINE__);
            if (!$rsTbl || p4c_num_rows($rsTbl) === 0) {
                p4c_query("ALTER TABLE `{$tbl}`
                    ADD COLUMN `performer_mode` VARCHAR(20) NOT NULL DEFAULT 'solo_owner',
                    ADD COLUMN `owner_in_content` TINYINT(1) NOT NULL DEFAULT 1;", __FILE__, __LINE__);
            }
        }
    }

    /**
     * Ermittelt den Klarnamen und die Stammdaten des eingeloggten Partner-Accounts (`merchants`).
     *
     * @return array{merchant_id:int, partner_id:string, username:string, first_name:string, last_name:string, full_name:string, birthday:string, email:string}
     */
    public static function getMerchantIdentity(int $merchantId): array
    {
        global $mysql;

        $merchantId = abs($merchantId);
        $merchant   = new \Merchant($mysql, $merchantId);

        $firstName = trim(self::cleanUtf8((string)$merchant->firstname('aes_decrypt')));
        $lastName  = trim(self::cleanUtf8((string)$merchant->surname('aes_decrypt')));
        $username  = trim(self::cleanUtf8((string)$merchant->username('aes_decrypt')));
        $birthday  = trim(self::cleanUtf8((string)$merchant->birthday('aes_decrypt')));
        $email     = trim(self::cleanUtf8((string)$merchant->email('aes_decrypt')));
        $partnerId = trim((string)$merchant->partner_id());

        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $username !== '' ? $username : ('Partner #' . $merchantId);
        }

        // Primären Creator-/Profilnamen aus `actors` ermitteln (falls vorhanden)
        $creatorName = $username;
        $rsActor = p4c_query("SELECT `username` FROM `actors`
            WHERE `merchant_id` = '" . $merchantId . "' AND `status` != 'deleted'
            ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsActor && p4c_num_rows($rsActor) > 0) {
            $aObj = p4c_fetch_object($rsActor);
            if (!empty($aObj->username)) {
                $creatorName = trim(self::cleanUtf8((string)$aObj->username));
            }
        }

        return [
            'merchant_id'  => $merchantId,
            'partner_id'   => $partnerId,
            'username'     => $username,
            'creator_name' => $creatorName,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'full_name'    => $fullName,
            'real_name'    => $fullName,
            'birthday'     => $birthday,
            'email'        => $email,
        ];
    }

    /**
     * Stellt sicher, dass für den Partner-Account selbst der „Ich“-Eintrag (`is_owner = 1`) in `co_actors` existiert.
     *
     * @return array<string, mixed>
     */
    public static function ensureOwnerCoActor(int $merchantId): array
    {
        self::ensureSchema();
        $merchantId = abs($merchantId);
        $identity   = self::getMerchantIdentity($merchantId);

        $rsOwner = p4c_query("SELECT `id` FROM `co_actors`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `is_owner` = 1
              AND `status` != 'deleted'
            ORDER BY `id` ASC
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rsOwner || p4c_num_rows($rsOwner) === 0) {
            $token   = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 86400 * 7);
            $fName   = $identity['first_name'] !== '' ? $identity['first_name'] : $identity['username'];
            $lName   = $identity['last_name'];
            $bDate   = self::normalizeDate($identity['birthday']);

            p4c_query("INSERT INTO `co_actors` SET
                `merchant_id`         = '" . $merchantId . "',
                `is_owner`            = 1,
                `creator_name`        = '" . p4c_escape_string($identity['username']) . "',
                `nickname_not_public` = AES_ENCRYPT('" . p4c_escape_string($identity['username']) . "', '" . AES_KEY . "'),
                `first_name`          = AES_ENCRYPT('" . p4c_escape_string($fName) . "', '" . AES_KEY . "'),
                `last_name`           = AES_ENCRYPT('" . p4c_escape_string($lName) . "', '" . AES_KEY . "'),
                `birth_date`          = AES_ENCRYPT('" . p4c_escape_string($bDate) . "', '" . AES_KEY . "'),
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

            $ownerId = abs((int)p4c_insert_id());
        } else {
            $row     = p4c_fetch_object($rsOwner);
            $ownerId = abs((int)$row->id);
        }

        $ownerData = self::getCoActorById($ownerId, $merchantId);
        return $ownerData ?? [];
    }

    /**
     * Lädt einen einzelnen Drehpartner (inkl. AES-Entschlüsselung).
     * Wenn `$merchantId > 0` angegeben ist, wird zusätzlich die Zugehörigkeit geprüft.
     *
     * @return array<string, mixed>|null
     */
    public static function getCoActorById(int $coActorId, int $merchantId = 0): ?array
    {
        self::ensureSchema();
        $coActorId  = abs($coActorId);
        $merchantId = abs($merchantId);

        if ($coActorId <= 0) {
            return null;
        }

        $whereMerchant = $merchantId > 0 ? " AND `merchant_id` = '" . $merchantId . "'" : '';

        $rs = p4c_query("SELECT
                `id`, `merchant_id`, `is_owner`, `creator_name`,
                AES_DECRYPT(`nickname_not_public`, '" . AES_KEY . "') AS `dec_nickname`,
                AES_DECRYPT(`first_name`, '" . AES_KEY . "') AS `dec_first_name`,
                AES_DECRYPT(`last_name`, '" . AES_KEY . "') AS `dec_last_name`,
                AES_DECRYPT(`birth_date`, '" . AES_KEY . "') AS `dec_birth_date`,
                AES_DECRYPT(`street`, '" . AES_KEY . "') AS `dec_street`,
                AES_DECRYPT(`zip_code`, '" . AES_KEY . "') AS `dec_zip_code`,
                AES_DECRYPT(`city`, '" . AES_KEY . "') AS `dec_city`,
                `country`, `id_doc_type`,
                AES_DECRYPT(`id_doc_number`, '" . AES_KEY . "') AS `dec_id_doc_number`,
                `id_doc_valid_until`, `verification_method`,
                `qr_token`, `qr_token_expires_at`,
                `face_ref_img`, `id_shot_img1`, `id_front_img`, `id_back_img`,
                `consent_doc_file`, `signature_data_json`,
                `ai_face_ready`, `checked_status`, `rejection_reason`,
                `create_datetime`, `submitted_datetime`, `checked_on`, `checked_by_employee`, `status`
            FROM `co_actors`
            WHERE `id` = '" . $coActorId . "'
              AND `status` != 'deleted'
              {$whereMerchant}
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rs || p4c_num_rows($rs) === 0) {
            return null;
        }

        $obj = p4c_fetch_object($rs);
        return self::formatCoActorRow($obj);
    }

    /**
     * Lädt einen Drehpartner anhand seines einmaligen `qr_token` (für die Smartphone-Verifizierungsseite).
     *
     * @return array<string, mixed>|null
     */
    public static function getCoActorByToken(string $token): ?array
    {
        self::ensureSchema();
        $cleanToken = trim((string)preg_replace('/[^a-f0-9]/i', '', $token));
        if (strlen($cleanToken) < 32) {
            return null;
        }

        $rs = p4c_query("SELECT
                `id`, `merchant_id`, `is_owner`, `creator_name`,
                AES_DECRYPT(`nickname_not_public`, '" . AES_KEY . "') AS `dec_nickname`,
                AES_DECRYPT(`first_name`, '" . AES_KEY . "') AS `dec_first_name`,
                AES_DECRYPT(`last_name`, '" . AES_KEY . "') AS `dec_last_name`,
                AES_DECRYPT(`birth_date`, '" . AES_KEY . "') AS `dec_birth_date`,
                AES_DECRYPT(`street`, '" . AES_KEY . "') AS `dec_street`,
                AES_DECRYPT(`zip_code`, '" . AES_KEY . "') AS `dec_zip_code`,
                AES_DECRYPT(`city`, '" . AES_KEY . "') AS `dec_city`,
                `country`, `id_doc_type`,
                AES_DECRYPT(`id_doc_number`, '" . AES_KEY . "') AS `dec_id_doc_number`,
                `id_doc_valid_until`, `verification_method`,
                `qr_token`, `qr_token_expires_at`,
                `face_ref_img`, `id_shot_img1`, `id_front_img`, `id_back_img`,
                `consent_doc_file`, `signature_data_json`,
                `ai_face_ready`, `checked_status`, `rejection_reason`,
                `create_datetime`, `submitted_datetime`, `checked_on`, `checked_by_employee`, `status`
            FROM `co_actors`
            WHERE `qr_token` = '" . p4c_escape_string($cleanToken) . "'
              AND `status` != 'deleted'
            LIMIT 1;", __FILE__, __LINE__);

        if (!$rs || p4c_num_rows($rs) === 0) {
            return null;
        }

        $obj = p4c_fetch_object($rs);
        return self::formatCoActorRow($obj);
    }

    /**
     * Lädt alle Drehpartner eines Partners (`is_owner = 1` zuerst, danach alphabetisch).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getMerchantCoActors(int $merchantId, bool $includeOwner = true): array
    {
        self::ensureSchema();
        $merchantId = abs($merchantId);
        if ($merchantId <= 0) {
            return [];
        }

        // Sicherstellen, dass der „Ich“-Eintrag existiert
        self::ensureOwnerCoActor($merchantId);

        $ownerWhere = $includeOwner ? '' : ' AND `is_owner` = 0';

        $rs = p4c_query("SELECT
                `id`, `merchant_id`, `is_owner`, `creator_name`,
                AES_DECRYPT(`nickname_not_public`, '" . AES_KEY . "') AS `dec_nickname`,
                AES_DECRYPT(`first_name`, '" . AES_KEY . "') AS `dec_first_name`,
                AES_DECRYPT(`last_name`, '" . AES_KEY . "') AS `dec_last_name`,
                AES_DECRYPT(`birth_date`, '" . AES_KEY . "') AS `dec_birth_date`,
                AES_DECRYPT(`street`, '" . AES_KEY . "') AS `dec_street`,
                AES_DECRYPT(`zip_code`, '" . AES_KEY . "') AS `dec_zip_code`,
                AES_DECRYPT(`city`, '" . AES_KEY . "') AS `dec_city`,
                `country`, `id_doc_type`,
                AES_DECRYPT(`id_doc_number`, '" . AES_KEY . "') AS `dec_id_doc_number`,
                `id_doc_valid_until`, `verification_method`,
                `qr_token`, `qr_token_expires_at`,
                `face_ref_img`, `id_shot_img1`, `id_front_img`, `id_back_img`,
                `consent_doc_file`, `signature_data_json`,
                `ai_face_ready`, `checked_status`, `rejection_reason`,
                `create_datetime`, `submitted_datetime`, `checked_on`, `checked_by_employee`, `status`
            FROM `co_actors`
            WHERE `merchant_id` = '" . $merchantId . "'
              AND `status` != 'deleted'
              {$ownerWhere}
            ORDER BY `is_owner` DESC, `id` ASC;", __FILE__, __LINE__);

        $list = [];
        if ($rs && p4c_num_rows($rs) > 0) {
            while ($obj = p4c_fetch_object($rs)) {
                $list[] = self::formatCoActorRow($obj);
            }
        }

        return $list;
    }

    /**
     * Erneuert den QR-Token für einen Drehpartner (oder „Ich“) und gibt die neue Verify-URL + SVG zurück.
     */
    public static function regenerateQrToken(int $coActorId, int $merchantId): string
    {
        $coActorId  = abs($coActorId);
        $merchantId = abs($merchantId);
        $token      = bin2hex(random_bytes(24));
        $expires    = date('Y-m-d H:i:s', time() + 86400 * 7);

        p4c_query("UPDATE `co_actors` SET
            `qr_token`            = '" . p4c_escape_string($token) . "',
            `qr_token_expires_at` = '" . p4c_escape_string($expires) . "'
            WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        return $token;
    }

    /**
     * Formatiert ein Datenbank-Objekt aus `co_actors` in ein konsistentes Array für MCP & ACP.
     *
     * @param object $obj
     * @return array<string, mixed>
     */
    public static function formatCoActorRow(object $obj): array
    {
        $id         = abs((int)$obj->id);
        $merchantId = abs((int)$obj->merchant_id);
        $isOwner    = abs((int)($obj->is_owner ?? 0)) === 1;

        $creatorName = trim(self::cleanUtf8((string)($obj->creator_name ?? '')));
        if ($creatorName === '' && !empty($obj->dec_nickname)) {
            $creatorName = trim(self::cleanUtf8((string)$obj->dec_nickname));
        }

        $firstName = trim(self::cleanUtf8((string)($obj->dec_first_name ?? '')));
        $lastName  = trim(self::cleanUtf8((string)($obj->dec_last_name ?? '')));
        $birthDate = trim(self::cleanUtf8((string)($obj->dec_birth_date ?? '')));
        $street    = trim(self::cleanUtf8((string)($obj->dec_street ?? '')));
        $zipCode   = trim(self::cleanUtf8((string)($obj->dec_zip_code ?? '')));
        $city      = trim(self::cleanUtf8((string)($obj->dec_city ?? '')));
        $country   = trim((string)($obj->country ?? 'DE'));
        $docType   = trim((string)($obj->id_doc_type ?? 'id_card'));
        $docNumber = trim(self::cleanUtf8((string)($obj->dec_id_doc_number ?? '')));
        $docValid  = trim((string)($obj->id_doc_valid_until ?? ''));

        if ($isOwner && ($firstName === '' && $lastName === '')) {
            $mIdent    = self::getMerchantIdentity($merchantId);
            $firstName = $mIdent['first_name'];
            $lastName  = $mIdent['last_name'];
            if ($birthDate === '' && $mIdent['birthday'] !== '') {
                $birthDate = self::normalizeDate($mIdent['birthday']);
            }
        }

        $realFullName = trim($firstName . ' ' . $lastName);
        if ($realFullName === '') {
            $realFullName = $creatorName !== '' ? $creatorName : ('Person #' . $id);
        }

        // Formatierung laut Vorgabe:
        // Wenn Creator-Name vorhanden: "Creator-Name (Klarname)"
        // Bei "Ich": "Ich (Klarname)" bzw. "Ich – Creator-Name (Klarname)"
        if ($isOwner) {
            $displayLabel = 'Ich (' . $realFullName . ')';
            $cardTitle    = 'Ich selbst (' . $realFullName . ')';
        } elseif ($creatorName !== '' && mb_strtolower($creatorName, 'UTF-8') !== mb_strtolower($realFullName, 'UTF-8')) {
            $displayLabel = $creatorName . ' (' . $realFullName . ')';
            $cardTitle    = $creatorName . ' (' . $realFullName . ')';
        } else {
            $displayLabel = $realFullName;
            $cardTitle    = $realFullName;
        }

        $faceRefImg     = trim((string)($obj->face_ref_img ?? ''));
        $idShotImg      = trim((string)($obj->id_shot_img1 ?? ''));
        $idFrontImg     = trim((string)($obj->id_front_img ?? ''));
        $idBackImg      = trim((string)($obj->id_back_img ?? ''));
        $consentDocFile = trim((string)($obj->consent_doc_file ?? ''));
        $sigJsonRaw     = (string)($obj->signature_data_json ?? '');
        $sigData        = $sigJsonRaw !== '' ? @json_decode($sigJsonRaw, true) : null;

        $verMethod   = trim((string)($obj->verification_method ?? 'qr_live'));
        $hasIdShot   = ($idShotImg !== '');
        $hasConsent  = ($consentDocFile !== '' || (is_array($sigData) && !empty($sigData['signed_at'])));
        $hasAnyMedia = ($idShotImg !== '' || $faceRefImg !== '' || $idFrontImg !== '' || $idBackImg !== '' || $consentDocFile !== '');
        $isComplete  = ($hasIdShot && $hasConsent);
        $aiFaceReady = (abs((int)($obj->ai_face_ready ?? 0)) === 1) || ($verMethod === 'qr_live' && $faceRefImg !== '');

        $rawStatus = trim((string)($obj->checked_status ?? 'pending_qr'));
        if (!$hasAnyMedia) {
            // Solange noch überhaupt kein ID-Shot / Dokument hochgeladen wurde, ist der Status immer 'pending_qr'
            $rawStatus = 'pending_qr';
        } elseif (!$isComplete && !in_array($rawStatus, ['verified', 'rejected'], true)) {
            $rawStatus = 'pending_qr';
        }

        $statusLabel    = 'Noch kein ID-Shot hochgeladen';
        $statusBadge    = 'badge-kkc-warning';
        $statusBadgeCls = 'bg-warning text-dark';
        $statusColor    = 'warning';
        $statusIcon     = 'bi-qr-code-scan';

        if ($rawStatus === 'verified') {
            $statusLabel    = 'Geprüft & Freigegeben';
            $statusBadge    = 'badge-kkc-success';
            $statusBadgeCls = 'bg-success';
            $statusColor    = 'success';
            $statusIcon     = 'bi-patch-check-fill';
        } elseif ($rawStatus === 'under_review') {
            $statusLabel    = 'Eingereicht (In Prüfung)';
            $statusBadge    = 'badge-kkc-info';
            $statusBadgeCls = 'bg-info text-dark';
            $statusColor    = 'info';
            $statusIcon     = 'bi-hourglass-split';
        } elseif ($rawStatus === 'rejected') {
            $statusLabel    = 'Abgelehnt – Korrektur nötig';
            $statusBadge    = 'badge-kkc-danger';
            $statusBadgeCls = 'bg-danger';
            $statusColor    = 'danger';
            $statusIcon     = 'bi-exclamation-triangle-fill';
        }

        $qrToken = trim((string)($obj->qr_token ?? ''));
        if ($qrToken === '') {
            $qrToken = self::regenerateQrToken($id, $merchantId);
        }

        // Ob bereits vollständige Dokumente/ID-Shot vorliegen ('under_review' oder 'verified')
        $isReadyForUse = (($isComplete || ($hasAnyMedia && $rawStatus === 'verified')) && in_array($rawStatus, ['verified', 'under_review'], true));

        // Übergangsphase: Creator dürfen Inhalte auch dann hochladen/einreichen, wenn noch kein ID-Shot hochgeladen wurde ('pending_qr').
        // Blockiert wird nur, wenn ein hochgeladener ID-Shot explizit abgelehnt ('rejected') wurde.
        $canSelectInContent = ($rawStatus !== 'rejected');

        $ageYears = null;
        if ($birthDate !== '' && strtotime($birthDate) !== false && !str_starts_with($birthDate, '0000')) {
            $calcAge = (int)floor((time() - (int)strtotime($birthDate)) / (365.2425 * 86400));
            if ($calcAge >= 18 && $calcAge <= 110) {
                $ageYears = $calcAge;
            }
        }

        $cacheVer = (string)($obj->submitted_datetime ?? '');
        $vHash    = $cacheVer !== '' ? substr(md5($cacheVer . $idShotImg . $faceRefImg), 0, 8) : (string)time();

        return [
            'id'                    => $id,
            'ref_code'              => sprintf('DP-%d-%03d', $merchantId, $id),
            'merchant_id'           => $merchantId,
            'is_owner'              => $isOwner,
            'creator_name'          => $creatorName,
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'full_name'             => $realFullName,
            'real_full_name'        => $realFullName,
            'display_label'         => $displayLabel,
            'card_title'            => $cardTitle,
            'birth_date'            => $birthDate,
            'birth_date_de'         => ($birthDate !== '' && strtotime($birthDate) && !str_starts_with($birthDate, '0000')) ? date('d.m.Y', strtotime($birthDate)) : '',
            'age'                   => $ageYears,
            'street'                => $street,
            'zip_code'              => $zipCode,
            'city'                  => $city,
            'country'               => $country,
            'id_doc_type'           => $docType,
            'id_doc_number'         => $docNumber,
            'id_doc_valid_until'    => $docValid,
            'verification_method'   => $verMethod,
            'is_legacy_upload'      => ($verMethod === 'manual_legacy'),
            'qr_token'              => $qrToken,
            'qr_verify_url'         => self::getPublicVerifyUrl($qrToken),
            'face_ref_img'          => $faceRefImg,
            'id_shot_img1'          => $idShotImg,
            'id_front_img'          => $idFrontImg,
            'id_back_img'           => $idBackImg,
            'consent_doc_file'      => $consentDocFile,
            'face_ref_url'          => $faceRefImg !== '' ? self::getMediaProxyUrl($id, 'face_ref', $qrToken, $vHash) : '',
            'id_shot_url'           => $idShotImg !== '' ? self::getMediaProxyUrl($id, 'id_shot', $qrToken, $vHash) : '',
            'id_front_url'          => $idFrontImg !== '' ? self::getMediaProxyUrl($id, 'id_front', $qrToken, $vHash) : '',
            'id_back_url'           => $idBackImg !== '' ? self::getMediaProxyUrl($id, 'id_back', $qrToken, $vHash) : '',
            'certificate_url'       => $isComplete ? self::getMediaProxyUrl($id, 'certificate', $qrToken, $vHash) : '',
            'signature_data'        => is_array($sigData) ? $sigData : null,
            'has_id_shot'           => $hasIdShot,
            'has_consent'           => $hasConsent,
            'has_uploaded_docs'     => $hasAnyMedia,
            'is_complete'           => $isComplete,
            'is_ready_for_use'      => $isReadyForUse,
            'ai_face_ready'         => $aiFaceReady,
            'can_select_in_content' => $canSelectInContent,
            'checked_status'        => $rawStatus,
            'status_label'          => $statusLabel,
            'status_badge'          => $statusBadge,
            'status_badge_cls'      => $statusBadgeCls,
            'status_color'          => $statusColor,
            'status_icon'           => $statusIcon,
            'rejection_reason'      => self::cleanUtf8((string)($obj->rejection_reason ?? '')),
            'create_datetime'       => (string)($obj->create_datetime ?? ''),
            'submitted_datetime'    => (string)($obj->submitted_datetime ?? ''),
            'checked_on'            => (string)($obj->checked_on ?? ''),
            'checked_by_employee'   => abs((int)($obj->checked_by_employee ?? 0)),
        ];
    }

    /**
     * Gibt das geschützte Dateisystem-Verzeichnis für die Dokumente eines Drehpartners zurück.
     */
    public static function getStorageDir(int $merchantId, int $coActorId): string
    {
        $basePath = defined('PROFILE_IMAGE_PATH') ? PROFILE_IMAGE_PATH : dirname(__DIR__, 3);
        $subDir   = defined('MERCHANT_DEFAULT_DIR') ? MERCHANT_DEFAULT_DIR : 'priv/merchant_files';

        $coActorsRoot = rtrim($basePath, '/\\') . '/' . trim($subDir, '/\\') . '/' . abs($merchantId) . '/co_actors';
        if (!is_dir($coActorsRoot)) {
            @mkdir($coActorsRoot, 0775, true);
        }
        $htaccessFile = $coActorsRoot . '/.htaccess';
        if (!is_file($htaccessFile)) {
            @file_put_contents($htaccessFile, "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        }

        $targetDir = $coActorsRoot . '/' . abs($coActorId);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        return $targetDir;
    }

    /**
     * Ermittelt die Basis-URL zum MCP-Webverzeichnis (produktiv direkt MCP_URL / https://erocloud.net).
     */
    public static function getMcpWebBaseUrl(): string
    {
        $mcpUrl     = defined('MCP_URL') ? rtrim((string)MCP_URL, '/') : 'https://erocloud.net';
        $requestUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));

        if (str_contains($requestUri, '/mcp/mcp_neu/web/')) {
            return $mcpUrl . '/mcp/mcp_neu/web';
        }
        return $mcpUrl;
    }

    /**
     * Ermittelt die öffentliche URL für den QR-Code.
     */
    public static function getPublicVerifyUrl(string $qrToken): string
    {
        return self::getMcpWebBaseUrl() . '/verify_co_actor.php?t=' . rawurlencode($qrToken);
    }

    /**
     * Erzeugt die geschützte Medien-/Zertifikats-URL (funktioniert sowohl im MCP als auch domainübergreifend im ACP).
     */
    public static function getMediaProxyUrl(int $coActorId, string $type, string $qrToken = '', string $version = ''): string
    {
        $url = self::getMcpWebBaseUrl() . '/co_actor_media.php?id=' . abs($coActorId) . '&type=' . rawurlencode($type);
        if ($qrToken !== '') {
            $url .= '&t=' . rawurlencode($qrToken);
        }
        if ($version !== '') {
            $url .= '&v=' . rawurlencode($version);
        }
        return $url;
    }

    /**
     * Lädt die zugewiesenen Mitwirkenden für einen Film (`content_type = 'movie'`) oder ein Fotoalbum (`'photo_album'`).
     *
     * @return array{performer_mode:string, owner_in_content:bool, co_actor_ids:array<int,int>, performers:array<int,array<string,mixed>>, all_verified:bool}
     */
    public static function getContentPerformers(string $contentType, int $contentId, int $merchantId): array
    {
        self::ensureSchema();
        $contentType = ($contentType === 'photo_album') ? 'photo_album' : 'movie';
        $contentId   = abs($contentId);
        $merchantId  = abs($merchantId);

        $performerMode  = 'solo_owner';
        $ownerInContent = true;

        if ($contentId > 0) {
            $table = ($contentType === 'photo_album') ? 'photo_albums' : 'movies';
            $rsContent = p4c_query("SELECT `performer_mode`, `owner_in_content`
                FROM `{$table}`
                WHERE `id` = '" . $contentId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);
            if ($rsContent && p4c_num_rows($rsContent) > 0) {
                $cObj = p4c_fetch_object($rsContent);
                $performerMode  = ((string)($cObj->performer_mode ?? 'solo_owner') === 'multiple') ? 'multiple' : 'solo_owner';
                $ownerInContent = abs((int)($cObj->owner_in_content ?? 1)) === 1;
            }
        }

        $allCoActors = self::getMerchantCoActors($merchantId, true);
        $byId        = [];
        $ownerEntry  = null;
        foreach ($allCoActors as $ca) {
            $byId[(int)$ca['id']] = $ca;
            if (!empty($ca['is_owner'])) {
                $ownerEntry = $ca;
            }
        }

        $assignedIds = [];
        if ($contentId > 0) {
            $rsLinks = p4c_query("SELECT `co_actor_id`
                FROM `content_co_actors`
                WHERE `content_type` = '" . p4c_escape_string($contentType) . "'
                  AND `content_id` = '" . $contentId . "'
                  AND `merchant_id` = '" . $merchantId . "'
                ORDER BY `id` ASC;", __FILE__, __LINE__);
            if ($rsLinks && p4c_num_rows($rsLinks) > 0) {
                while ($lObj = p4c_fetch_object($rsLinks)) {
                    $cid = abs((int)$lObj->co_actor_id);
                    if (isset($byId[$cid])) {
                        $assignedIds[] = $cid;
                    }
                }
            }
        }

        // Falls Solo-Owner oder Owner beteiligt ist, sicherstellen, dass Owner in der Liste der aktiven Performer ist
        if (($performerMode === 'solo_owner' || $ownerInContent) && $ownerEntry !== null) {
            $ownerId = (int)$ownerEntry['id'];
            if (!in_array($ownerId, $assignedIds, true)) {
                array_unshift($assignedIds, $ownerId);
            }
        }

        $performers      = [];
        $otherPerformers = [];
        $otherActorIds   = [];
        $allVerified     = true;
        foreach ($assignedIds as $cid) {
            if (isset($byId[$cid])) {
                $performers[] = $byId[$cid];
                if (empty($byId[$cid]['is_owner'])) {
                    $otherPerformers[] = $byId[$cid];
                    $otherActorIds[]   = $cid;
                }
                if ((string)$byId[$cid]['checked_status'] !== 'verified') {
                    $allVerified = false;
                }
            }
        }

        if (empty($performers)) {
            $allVerified = false;
        }

        return [
            'performer_mode'   => $performerMode,
            'owner_in_content' => $ownerInContent ? 1 : 0,
            'co_actor_ids'     => $otherActorIds,
            'all_actor_ids'    => $assignedIds,
            'co_actors'        => $otherPerformers,
            'performers'       => $performers,
            'all_verified'     => $allVerified,
        ];
    }

    /**
     * Validiert die Personen-Auswahl bei einem Film (oder Fotoalbum) vor dem Speichern.
     *
     * @param array<int, mixed>    $rawCoActorIds
     * @param array<int, string>   $selectedCategories
     * @return array{ok:bool, errors:array<int,string>, performer_mode:string, owner_in_content:int, final_co_actor_ids:array<int,int>}
     */
    public static function validateContentPerformers(
        int $merchantId,
        string $performerMode,
        bool $ownerInContent,
        array $rawCoActorIds,
        $selectedCategories = []
    ): array {
        self::ensureSchema();
        $errors        = [];
        $performerMode = ($performerMode === 'multiple') ? 'multiple' : 'solo_owner';
        if (is_string($selectedCategories)) {
            $selectedCategories = array_filter(array_map('trim', explode(',', $selectedCategories)));
        } elseif (!is_array($selectedCategories)) {
            $selectedCategories = [];
        }

        $allCoActors = self::getMerchantCoActors($merchantId, true);
        $byId        = [];
        $ownerEntry  = null;
        foreach ($allCoActors as $ca) {
            $byId[(int)$ca['id']] = $ca;
            if (!empty($ca['is_owner'])) {
                $ownerEntry = $ca;
            }
        }
        if ($ownerEntry === null && $merchantId > 0) {
            $ownerEntry = self::ensureOwnerCoActor($merchantId);
            if ($ownerEntry !== null) {
                $byId[(int)$ownerEntry['id']] = $ownerEntry;
            }
        }

        $finalIds = [];

        if ($performerMode === 'solo_owner') {
            $ownerInContent = true;
            if ($ownerEntry !== null && ($ownerEntry['checked_status'] ?? '') === 'rejected') {
                $errors[] = 'Dein hochgeladener „Ich“-ID-Shot wurde von der Prüfung abgelehnt. Bitte nimm über den „Ich“-Button per Smartphone-QR-Code kurz einen neuen, gut lesbaren ID-Shot auf.';
            } elseif ($ownerEntry !== null) {
                $finalIds[] = (int)$ownerEntry['id'];
            }
        } else {
            // Modus: Es sind (auch) andere Personen im Film zu sehen
            if ($ownerInContent) {
                if ($ownerEntry !== null && ($ownerEntry['checked_status'] ?? '') === 'rejected') {
                    $errors[] = 'Dein hochgeladener „Ich“-ID-Shot wurde von der Prüfung abgelehnt. Bitte nimm über den „Ich“-Button per Smartphone-QR-Code kurz einen neuen, gut lesbaren ID-Shot auf.';
                } elseif ($ownerEntry !== null) {
                    $finalIds[] = (int)$ownerEntry['id'];
                }
            }

            $otherSelectedCount = 0;
            foreach ($rawCoActorIds as $rawId) {
                $cid = abs((int)$rawId);
                if ($cid <= 0 || !isset($byId[$cid])) {
                    continue;
                }
                $ca = $byId[$cid];
                if (!empty($ca['is_owner'])) {
                    continue;
                }
                $otherSelectedCount++;
                if (($ca['checked_status'] ?? '') === 'rejected') {
                    $errors[] = 'Der ID-Shot für den ausgewählten Drehpartner „' . $ca['display_label'] . '“ wurde abgelehnt. Bitte erneuere erst den ID-Shot per QR-Code.';
                } else {
                    $finalIds[] = $cid;
                }
            }

            if ($otherSelectedCount === 0) {
                $errors[] = 'Du hast angegeben, dass noch weitere Personen im Inhalt zu sehen sind. Bitte wähle mindestens einen weiteren Drehpartner aus (oder lege ihn über „+ Neuen Drehpartner anlegen“ an).';
            }
        }

        $finalIds = array_values(array_unique($finalIds));
        $totalPeopleCount = ($ownerInContent ? 1 : 0) + count(array_filter($finalIds, static function (int $id) use ($ownerEntry): bool {
            return $ownerEntry === null || $id !== (int)$ownerEntry['id'];
        }));

        // Kategorie-Plausibilitäts-Check (z. B. Paar / Hetero / Lesben / Dreier gewählt, aber nur 1 Person angegeben)
        foreach ($selectedCategories as $catKey) {
            $catKey = trim((string)$catKey);
            if (isset(self::THREE_PLUS_CATEGORIES[$catKey]) && $totalPeopleCount < 3) {
                $catLabel = self::THREE_PLUS_CATEGORIES[$catKey];
                $errors[] = "Du hast die Kategorie „{$catLabel}“ gewählt, aber weniger als 3 mitwirkende Personen angegeben. Bitte wähle alle im Film sichtbaren Drehpartner aus.";
                break;
            }
            if (isset(self::MULTI_PERSON_CATEGORIES[$catKey]) && $totalPeopleCount < 2) {
                $catLabel = self::MULTI_PERSON_CATEGORIES[$catKey];
                $errors[] = "Du hast die Kategorie „{$catLabel}“ gewählt (mehrere Personen), aber oben angegeben, dass nur 1 Person im Film zu sehen ist. Bitte wähle unter „Mitwirkende Personen“ alle im Film sichtbaren Drehpartner aus.";
                break;
            }
        }

        return [
            'ok'                 => empty($errors),
            'valid'              => empty($errors),
            'errors'             => $errors,
            'performer_mode'     => $performerMode,
            'owner_in_content'   => $ownerInContent ? 1 : 0,
            'final_co_actor_ids' => $finalIds,
        ];
    }

    /**
     * Speichert die validierten Mitwirkenden für einen Film (`'movie'`) oder ein Fotoalbum (`'photo_album'`).
     *
     * @param int|bool        $ownerInContent
     * @param array<int, int> $coActorIds
     */
    public static function saveContentPerformers(
        string $contentType,
        int $contentId,
        int $merchantId,
        string $performerMode,
        $ownerInContent,
        array $coActorIds
    ): void {
        self::ensureSchema();
        $contentType    = ($contentType === 'photo_album') ? 'photo_album' : 'movie';
        $contentId      = abs($contentId);
        $merchantId     = abs($merchantId);
        $performerMode  = ($performerMode === 'multiple') ? 'multiple' : 'solo_owner';
        $ownerInContent = ((int)$ownerInContent === 1 || $ownerInContent === true) ? 1 : 0;

        if ($contentId <= 0 || $merchantId <= 0) {
            return;
        }

        $table = ($contentType === 'photo_album') ? 'photo_albums' : 'movies';
        p4c_query("UPDATE `{$table}` SET
            `performer_mode`   = '" . p4c_escape_string($performerMode) . "',
            `owner_in_content` = '" . $ownerInContent . "'
            WHERE `id` = '" . $contentId . "' AND `merchant_id` = '" . $merchantId . "'
            LIMIT 1;", __FILE__, __LINE__);

        p4c_query("DELETE FROM `content_co_actors`
            WHERE `content_type` = '" . p4c_escape_string($contentType) . "'
              AND `content_id` = '" . $contentId . "'
              AND `merchant_id` = '" . $merchantId . "';", __FILE__, __LINE__);

        $now = date('Y-m-d H:i:s');
        foreach (array_unique($coActorIds) as $cid) {
            $cid = abs((int)$cid);
            if ($cid > 0) {
                p4c_query("INSERT IGNORE INTO `content_co_actors` SET
                    `content_type` = '" . p4c_escape_string($contentType) . "',
                    `content_id`   = '" . $contentId . "',
                    `merchant_id`  = '" . $merchantId . "',
                    `co_actor_id`  = '" . $cid . "',
                    `created_at`   = '" . $now . "';", __FILE__, __LINE__);
            }
        }
    }

    /**
     * Bereinigt Legacy-Strings (UTF-8 / ISO-8859-1).
     */
    public static function cleanUtf8(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
        return trim($str);
    }

    /**
     * Erzeugt ein inline SVG für einen QR-Code (100% lokal über QrCodeSvg).
     */
    public static function generateQrSvg(string $url, int $size = 240): string
    {
        if (!class_exists('QrCodeSvg') && defined('SOURCEDIR') && file_exists(SOURCEDIR . '/includes/klassen/QrCodeSvg.inc.php')) {
            include_once SOURCEDIR . '/includes/klassen/QrCodeSvg.inc.php';
        }
        if (class_exists('QrCodeSvg')) {
            return \QrCodeSvg::render($url, $size);
        }
        return '';
    }

    /**
     * Rendert das offizielle, druck- und PDF-fähige Einverständnis- & Identitäts-Zertifikat (Model Release / § 2257 / BRAM).
     *
     * @param array<string, mixed> $coActor
     * @param array<string, mixed> $merchantIdent
     */
    public static function renderConsentDocumentHtml(array $coActor, array $merchantIdent, string $signatureImgDataUri = ''): string
    {
        $refCode      = htmlspecialchars((string)($coActor['ref_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $isOwner      = !empty($coActor['is_owner']);
        $creatorName  = htmlspecialchars((string)($coActor['creator_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $realFullName = htmlspecialchars((string)($coActor['real_full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $birthDateDe  = htmlspecialchars((string)($coActor['birth_date_de'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $addressStr   = htmlspecialchars(trim((string)($coActor['street'] ?? '') . ', ' . (string)($coActor['zip_code'] ?? '') . ' ' . (string)($coActor['city'] ?? '') . ' (' . (string)($coActor['country'] ?? 'DE') . ')', ', '), ENT_QUOTES, 'UTF-8');
        $docTypeMap   = [
            'id_card'         => 'Personalausweis',
            'passport'        => 'Reisepass',
            'drivers_license' => 'Führerschein',
        ];
        $docTypeLabel = htmlspecialchars($docTypeMap[(string)($coActor['id_doc_type'] ?? 'id_card')] ?? 'Amtlicher Lichtbildausweis', ENT_QUOTES, 'UTF-8');
        $docNumber    = htmlspecialchars((string)($coActor['id_doc_number'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $docValid     = htmlspecialchars((string)($coActor['id_doc_valid_until'] ?? '-'), ENT_QUOTES, 'UTF-8');

        $mFullName    = htmlspecialchars((string)($merchantIdent['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mPartnerId   = htmlspecialchars((string)($merchantIdent['partner_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mUsername    = htmlspecialchars((string)($merchantIdent['username'] ?? ''), ENT_QUOTES, 'UTF-8');

        $sigData      = is_array($coActor['signature_data'] ?? null) ? $coActor['signature_data'] : [];
        $signedAt     = isset($sigData['signed_at']) ? date('d.m.Y H:i:s', strtotime((string)$sigData['signed_at'])) : date('d.m.Y H:i:s');
        $signedIp     = htmlspecialchars((string)($sigData['ip'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $signedUa     = htmlspecialchars((string)($sigData['user_agent'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $docHash      = htmlspecialchars((string)($sigData['sha256'] ?? hash('sha256', $refCode . '|' . $realFullName . '|' . $signedAt)), ENT_QUOTES, 'UTF-8');
        $roleTitle    = $isOwner ? 'Eigen-Verifizierung & Darsteller-Freigabe (Account-Inhaber)' : 'Drehgenehmigung & Einverständniserklärung (Model Release)';

        $sigHtml = $signatureImgDataUri !== ''
            ? '<img src="' . htmlspecialchars($signatureImgDataUri, ENT_QUOTES, 'UTF-8') . '" alt="Digitale Original-Unterschrift" style="max-height:110px; max-width:320px; object-fit:contain;">'
            : '<div style="padding:20px; color:#64748b; font-style:italic;">Unterschrift liegt im Dokumenten-Tresor vor</div>';

        return '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $roleTitle . ' – ' . $refCode . '</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #0f172a; background: #f1f5f9; margin: 0; padding: 24px; line-height: 1.5; }
  .sheet { max-width: 820px; margin: 0 auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 36px 42px; box-shadow: 0 10px 25px rgba(15,23,42,0.06); }
  .toolbar { max-width: 820px; margin: 0 auto 16px auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; border: 1px solid transparent; }
  .btn-primary { background: #0284c7; color: #fff; }
  .btn-outline { background: #fff; color: #334155; border-color: #cbd5e1; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0284c7; padding-bottom: 16px; margin-bottom: 22px; }
  .title { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; }
  .subtitle { font-size: 13px; color: #475569; }
  .ref-box { text-align: right; font-family: monospace; background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 8px; font-size: 13px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
  .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 16px; background: #f8fafc; }
  .box h4 { margin: 0 0 8px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: #0284c7; }
  .row-item { font-size: 13px; margin-bottom: 4px; }
  .row-item strong { color: #334155; display: inline-block; min-width: 125px; }
  .legal { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 18px; font-size: 12.5px; color: #1e293b; background: #fff; margin-bottom: 20px; text-align: justify; }
  .legal ul { margin: 8px 0 0 0; padding-left: 18px; }
  .legal li { margin-bottom: 6px; }
  .sig-section { display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; align-items: stretch; border-top: 1px solid #cbd5e1; padding-top: 18px; }
  .sig-pad-box { border: 1px dashed #94a3b8; border-radius: 8px; padding: 12px; background: #fff; text-align: center; }
  .audit-box { font-size: 11px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-family: monospace; word-break: break-all; }
  @media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none !important; }
    .sheet { border: none; box-shadow: none; padding: 10px; max-width: 100%; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <button type="button" class="btn btn-outline" onclick="window.close()">Fenster schließen</button>
    <div style="display:flex; gap:10px;">
      <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Jetzt drucken / Als PDF speichern</button>
    </div>
  </div>
  <div class="sheet">
    <div class="header">
      <div>
        <h1 class="title">' . $roleTitle . '</h1>
        <div class="subtitle">Rechtsverbindlicher Alters-, Identitäts- &amp; Veröffentlichungsnachweis (18+ / BRAM-Compliance)</div>
      </div>
      <div class="ref-box">
        <div><strong>Referenz:</strong> ' . $refCode . '</div>
        <div><strong>Datum:</strong> ' . htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8') . '</div>
      </div>
    </div>

    <div class="grid">
      <div class="box">
        <h4>1. Mitwirkende Person (Darsteller/in)</h4>
        <div class="row-item"><strong>Vor- &amp; Nachname:</strong> ' . $realFullName . '</div>
        ' . ($creatorName !== '' ? '<div class="row-item"><strong>Creator-Name:</strong> ' . $creatorName . '</div>' : '') . '
        <div class="row-item"><strong>Geburtsdatum:</strong> ' . $birthDateDe . '</div>
        <div class="row-item"><strong>Anschrift:</strong> ' . ($addressStr !== '' ? $addressStr : '-') . '</div>
        <div class="row-item"><strong>Ausweis-Dokument:</strong> ' . $docTypeLabel . '</div>
        <div class="row-item"><strong>Ausweis-Nummer:</strong> ' . $docNumber . ($docValid !== '-' && $docValid !== '' ? ' (gültig bis ' . $docValid . ')' : '') . '</div>
      </div>
      <div class="box">
        <h4>2. Produzierender Partner-Account</h4>
        <div class="row-item"><strong>Account-Inhaber:</strong> ' . $mFullName . '</div>
        <div class="row-item"><strong>Account-Name:</strong> ' . $mUsername . '</div>
        <div class="row-item"><strong>Partner-ID:</strong> ' . $mPartnerId . '</div>
        <div class="row-item"><strong>Plattform:</strong> EroCloud (' . htmlspecialchars(defined('COMPANYNAME') ? COMPANYNAME : 'CIPA Media S.L.U.', ENT_QUOTES, 'UTF-8') . ')</div>
        <div class="row-item"><strong>Verifizierungsart:</strong> ' . ($coActor['verification_method'] === 'qr_live' ? 'Smartphone Live-Kamera &amp; Touch-Signatur' : 'Manueller Dokumenten-Nachweis') . '</div>
      </div>
    </div>

    <div class="legal">
      <strong>Verbindliche Erklärung der mitwirkenden Person:</strong>
      <ul>
        <li><strong>Volljährigkeit &amp; Identität (18+):</strong> Ich bestätige hiermit ausdrücklich, dass ich zum Zeitpunkt sämtlicher Foto-, Audio- und Videoaufnahmen sowie zum Zeitpunkt dieser Unterzeichnung mindestens 18 Jahre alt (volljährig) bin und dass der von mir vorgelegte amtliche Lichtbildausweis meiner wahren Identität entspricht.</li>
        <li><strong>Freiwilligkeit:</strong> Meine Mitwirkung an sämtlichen gemeinsamen Foto-, Audio- und Videoproduktionen mit dem oben genannten Partner-Account erfolgt aus freiem Willen und ohne jeglichen Zwang.</li>
        <li><strong>Einwilligung zur kommerziellen Veröffentlichung &amp; Verbreitung:</strong> Ich erkläre mich unwiderruflich damit einverstanden, dass sämtliche Aufnahmen (Videos, Trailer, Vorschaubilder und Fotoalben), in denen ich zu sehen oder zu hören bin, über die Plattform EroCloud sowie die angeschlossenen Partner-Websites zeitlich und räumlich unbeschränkt öffentlich zugänglich gemacht, gestreamt, zum Download verkauft und zu Werbezwecken genutzt werden dürfen.</li>
        <li><strong>Biometrische Prüfung &amp; Compliance-Archivierung:</strong> Ich willige ein, dass meine Ausweiskopie, mein ID-Shot sowie mein Referenz-Gesichtsbild zur gesetzlichen Alters- und Identitätsprüfung sowie zum automatisierten Abgleich von Video- und Bildinhalten (Schutz vor unautorisierten Veröffentlichungen) verschlüsselt gespeichert und durch berechtigte Prüfer der Plattform eingesehen werden dürfen.</li>
      </ul>
    </div>

    <div class="sig-section">
      <div class="sig-pad-box">
        <div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">Eigenhändige Unterschrift (' . $realFullName . ')</div>
        ' . $sigHtml . '
        <div style="border-top:1px solid #cbd5e1; margin-top:8px; padding-top:4px; font-size:11px; color:#64748b;">
          Unterschrieben am ' . htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8') . '
        </div>
      </div>
      <div class="audit-box">
        <div style="font-weight:700; color:#0f172a; margin-bottom:6px;">Digitales Prüfprotokoll (Audit-Trail)</div>
        <div><strong>Ref-ID:</strong> ' . $refCode . '</div>
        <div><strong>Zeitstempel:</strong> ' . htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8') . '</div>
        <div><strong>IP-Adresse:</strong> ' . $signedIp . '</div>
        <div><strong>Endgerät:</strong> ' . $signedUa . '</div>
        <div style="margin-top:6px;"><strong>SHA-256 Dokument-Hash:</strong><br>' . $docHash . '</div>
      </div>
    </div>
  </div>
</body>
</html>';
    }

    /**
     * Normalisiert ein Datumsformat (z.B. DD.MM.YYYY oder YYYY-MM-DD) nach YYYY-MM-DD.
     */
    public static function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }

    /**
     * Zentraler ACP-Review-Helfer (AJAX & Formular) zum Bestätigen, Ablehnen oder Zurücksetzen eines ID-Shots / Drehpartners.
     *
     * @return array{ok:bool, checked_status?:string, status_label?:string, message?:string, error?:string}
     */
    public static function acpReviewCoActor(
        int $coActorId,
        int $merchantId,
        string $decision,
        string $reason = '',
        int $employeeId = 0,
        bool $sendNotification = true
    ): array {
        self::ensureSchema();
        $coActorId  = abs($coActorId);
        $merchantId = abs($merchantId);
        $employeeId = abs($employeeId);
        $decision   = trim($decision);

        if ($coActorId <= 0 || $merchantId <= 0) {
            return ['ok' => false, 'error' => 'Ungültige Parameter für die Drehpartner-Prüfung.'];
        }

        $ca = self::getCoActorById($coActorId, $merchantId);
        if ($ca === null) {
            return ['ok' => false, 'error' => 'Der gewählte Drehpartner-Eintrag wurde nicht gefunden.'];
        }

        $hasDocs   = !empty($ca['has_uploaded_docs']) || !empty($ca['has_id_shot']) || !empty($ca['face_ref_img']) || !empty($ca['id_front_img']);
        $label     = (string)($ca['display_label'] ?? ('#' . $coActorId));
        $refCode   = (string)($ca['ref_code'] ?? sprintf('DP-%d-%03d', $merchantId, $coActorId));
        $roleLabel = !empty($ca['is_owner']) ? 'Account-Inhaber („Ich“)' : 'Drehpartner';
        $aliasName = trim((string)($ca['creator_name'] ?? ''));
        if ($aliasName === '') {
            $aliasName = $refCode;
        }
        $now       = date('Y-m-d H:i:s');

        if ($decision === 'verify') {
            if (!$hasDocs) {
                // Falls zuvor versehentlich auf 'rejected' gesetzt wurde, Status auf 'pending_qr' bereinigen
                p4c_query("UPDATE `co_actors` SET
                    `checked_status`   = 'pending_qr',
                    `rejection_reason` = ''
                    WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                    LIMIT 1;", __FILE__, __LINE__);

                return [
                    'ok'             => true,
                    'checked_status' => 'pending_qr',
                    'status_label'   => 'Noch kein ID-Shot hochgeladen',
                    'message'        => 'Für „' . $label . '“ wurde noch kein ID-Shot hochgeladen. In der Übergangsphase kannst du den Film/das Fotoalbum direkt über den Haupt-Freigabe-Button veröffentlichen!',
                ];
            }

            p4c_query("UPDATE `co_actors` SET
                `checked_status`      = 'verified',
                `checked_by_employee` = '" . $employeeId . "',
                `checked_on`          = '" . $now . "',
                `rejection_reason`    = ''
                WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);

            if ($sendNotification && class_exists('EroCloudMailer')) {
                try {
                    \EroCloudMailer::sendCoActorStatusNotification([
                        'merchant_id' => $merchantId,
                        'co_actor_id' => $coActorId,
                        'status_type' => 'verified',
                        'ref_code'    => $refCode,
                        'role_label'  => $roleLabel,
                        'alias_label' => $aliasName,
                    ]);
                } catch (\Throwable $e) {
                    // Nicht-kritischer Fehler beim E-Mail-Versand
                }
            }

            return [
                'ok'             => true,
                'checked_status' => 'verified',
                'status_label'   => 'Geprüft & Freigegeben',
                'message'        => 'ID-Shot & Freigabe für „' . $label . '“ wurden als geprüft bestätigt.',
            ];
        }

        if ($decision === 'reject') {
            if (!$hasDocs) {
                return [
                    'ok'    => false,
                    'error' => 'Für „' . $label . '“ wurde noch kein ID-Shot hochgeladen, der abgelehnt werden könnte. (Übergangsphase: Der Inhalt kann auch ohne ID-Shot freigeschaltet werden.)',
                ];
            }

            $cleanReason = trim(strip_tags($reason));
            if ($cleanReason === '') {
                $cleanReason = 'Bitte nimm einen neuen, scharfen ID-Shot per Smartphone-QR-Code auf (Ausweis muss gut lesbar neben dem Gesicht gehalten werden).';
            }

            p4c_query("UPDATE `co_actors` SET
                `checked_status`      = 'rejected',
                `checked_by_employee` = '" . $employeeId . "',
                `checked_on`          = '" . $now . "',
                `rejection_reason`    = '" . p4c_escape_string($cleanReason) . "'
                WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);

            if ($sendNotification && class_exists('EroCloudMailer')) {
                try {
                    \EroCloudMailer::sendCoActorStatusNotification([
                        'merchant_id' => $merchantId,
                        'co_actor_id' => $coActorId,
                        'status_type' => 'rejected',
                        'ref_code'    => $refCode,
                        'role_label'  => $roleLabel,
                        'alias_label' => $aliasName,
                        'reason_text' => $cleanReason,
                    ]);
                } catch (\Throwable $e) {
                    // Nicht-kritischer Fehler beim E-Mail-Versand
                }
            }

            return [
                'ok'             => true,
                'checked_status' => 'rejected',
                'status_label'   => 'Abgelehnt – Korrektur nötig',
                'message'        => 'Der ID-Shot für „' . $label . '“ wurde abgelehnt.',
            ];
        }

        if ($decision === 'reset_review') {
            $newStatus = $hasDocs ? 'under_review' : 'pending_qr';
            p4c_query("UPDATE `co_actors` SET
                `checked_status`   = '" . $newStatus . "',
                `rejection_reason` = ''
                WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
                LIMIT 1;", __FILE__, __LINE__);

            return [
                'ok'             => true,
                'checked_status' => $newStatus,
                'status_label'   => $hasDocs ? 'Eingereicht (In Prüfung)' : 'Noch kein ID-Shot hochgeladen',
                'message'        => 'Status für „' . $label . '“ wurde zurückgesetzt.',
            ];
        }

        return ['ok' => false, 'error' => 'Unbekannte Prüf-Aktion.'];
    }

    /**
     * Verarbeitet 1-Klick-Freigaben oder Ablehnungen von Drehpartnern/ID-Shots direkt im ACP (Film prüfen / bearbeiten).
     */
    public static function handleAcpAction(int $merchantId): string
    {
        if (!isset($_POST['acp_co_actor_action'], $_POST['acp_co_actor_id'])) {
            return '';
        }

        self::ensureSchema();
        $action    = trim((string)$_POST['acp_co_actor_action']);
        $coActorId = abs((int)$_POST['acp_co_actor_id']);
        $empId     = isset($_SESSION['employee_id']) ? abs((int)$_SESSION['employee_id']) : 0;
        $reason    = (string)($_POST['acp_co_actor_reject_reason'] ?? '');

        $res = self::acpReviewCoActor($coActorId, $merchantId, $action, $reason, $empId);
        if (empty($res['ok'])) {
            return '<div class="ui-state-error" style="padding:8px 10px; margin-bottom:8px;">' . htmlspecialchars((string)($res['error'] ?? 'Fehler'), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        if (($res['checked_status'] ?? '') === 'rejected') {
            return '<div class="ui-state-error" style="padding:8px 10px; margin-bottom:8px;">✗ ' . htmlspecialchars((string)($res['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return '<div class="info_box" style="padding:8px 10px; margin-bottom:8px; background:#ecfdf5; border:1px solid #10b981; color:#065f46;">✓ ' . htmlspecialchars((string)($res['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
    }

    /**
     * Rendert die Übersichts- & Prüfbox für alle im Film gemeldeten Personen (Account-Inhaber „Ich“ + Drehpartner) im ACP.
     */
    public static function renderAcpMoviePerformersBox(int $movieId, int $merchantId): string
    {
        self::ensureSchema();
        $noticeHtml = self::handleAcpAction($merchantId);

        $perfInfo      = self::getContentPerformers('movie', $movieId, $merchantId);
        $ownerEntry    = self::ensureOwnerCoActor($merchantId);
        $merchantIdent = self::getMerchantIdentity($merchantId);
        $performerMode = (string)($perfInfo['performer_mode'] ?? 'solo_owner');
        $ownerInMovie  = (int)($perfInfo['owner_in_content'] ?? 1) === 1;
        $linkedActors  = is_array($perfInfo['co_actors'] ?? null) ? $perfInfo['co_actors'] : [];

        // Liste aller zu prüfenden Personen im Film zusammenstellen
        $personsToReview = [];
        if ($performerMode === 'solo_owner' || $ownerInMovie) {
            if ($ownerEntry !== null) {
                $personsToReview[$ownerEntry['id']] = $ownerEntry;
            }
        }
        foreach ($linkedActors as $la) {
            $personsToReview[(int)$la['id']] = $la;
        }

        $modeBadge = $performerMode === 'solo_owner'
            ? '<span style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; padding:2px 8px; border-radius:4px; font-weight:bold; font-size:12px;">Angabe: Nur ich allein im Film (' . htmlspecialchars((string)$merchantIdent['full_name'], ENT_QUOTES, 'UTF-8') . ')</span>'
            : '<span style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; padding:2px 8px; border-radius:4px; font-weight:bold; font-size:12px;">Angabe: Mehrere / andere Personen im Film (' . count($personsToReview) . ' gemeldet)</span>';

        $html = '
        <div class="ui-widget-header" style="padding:6px 10px; margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
            <span>Mitwirkende Personen &amp; ID-Shot-Pr&uuml;fung (18+ / Model Release)</span>
            ' . $modeBadge . '
        </div>
        <div class="ui-widget-content" style="padding:10px; border-top:none; margin-bottom:10px; font-size:12.5px;">
            ' . $noticeHtml;

        if (empty($personsToReview)) {
            $html .= '<div style="color:#b91c1c; font-weight:bold;">Keine mitwirkenden Personen hinterlegt.</div>';
        } else {
            foreach ($personsToReview as $p) {
                $pId       = (int)$p['id'];
                $isOwner   = !empty($p['is_owner']);
                $status    = (string)$p['checked_status'];
                $isReady   = !empty($p['is_ready_for_use']);
                $hasDocs   = !empty($p['has_uploaded_docs']) || !empty($p['has_id_shot']) || !empty($p['face_ref_url']) || !empty($p['id_front_url']);
                $isAiReady = !empty($p['ai_face_ready']);

                $statusStyle = 'background:#fef3c7; color:#92400e; border:1px solid #fde68a;';
                if ($status === 'verified') {
                    $statusStyle = 'background:#dcfce7; color:#166534; border:1px solid #86efac;';
                } elseif ($status === 'rejected') {
                    $statusStyle = 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;';
                } elseif ($status === 'under_review') {
                    $statusStyle = 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;';
                }

                $roleTag = $isOwner
                    ? '<span style="background:#0284c7; color:#fff; padding:1px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-right:5px;">ICH (ACCOUNT-INHABER)</span>'
                    : '<span style="background:#475569; color:#fff; padding:1px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-right:5px;">DREHPARTNER</span>';

                $aiTag = $isAiReady
                    ? '<span style="background:#ecfeff; color:#0e7490; border:1px solid #a5f3fc; padding:1px 6px; border-radius:3px; font-size:10.5px; margin-left:5px;">📱 Live-QR (KI-Ready)</span>'
                    : ($p['verification_method'] === 'manual_legacy'
                        ? '<span style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:1px 6px; border-radius:3px; font-size:10.5px; margin-left:5px;">📁 Alt-Dokumente</span>'
                        : '');

                $html .= '
                <div style="border:1px solid #cbd5e1; border-radius:6px; padding:10px; margin-bottom:8px; background:#f8fafc;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
                        <div>
                            ' . $roleTag . '
                            <strong style="font-size:13.5px;">' . htmlspecialchars((string)$p['display_label'], ENT_QUOTES, 'UTF-8') . '</strong>
                            ' . $aiTag . '
                        </div>
                        <span style="padding:2px 8px; border-radius:4px; font-weight:bold; font-size:11.5px; ' . $statusStyle . '">
                            ' . htmlspecialchars((string)$p['status_label'], ENT_QUOTES, 'UTF-8') . '
                        </span>
                    </div>';

                $metaParts = [];
                if (!empty($p['birth_date_de'])) {
                    $metaParts[] = 'Geb.: <b>' . htmlspecialchars((string)$p['birth_date_de'], ENT_QUOTES, 'UTF-8') . '</b>' . ($p['age'] !== null ? ' (' . (int)$p['age'] . ' J.)' : '');
                }
                if (!empty($p['id_doc_number'])) {
                    $metaParts[] = 'Ausweis-Nr.: <b>' . htmlspecialchars((string)$p['id_doc_number'], ENT_QUOTES, 'UTF-8') . '</b>';
                }
                if (!empty($p['city'])) {
                    $metaParts[] = 'Ort: ' . htmlspecialchars((string)$p['city'], ENT_QUOTES, 'UTF-8');
                }
                if (!empty($metaParts)) {
                    $html .= '<div style="color:#475569; margin-bottom:8px;">' . implode(' &bull; ', $metaParts) . '</div>';
                }

                if ($hasDocs) {
                    // Vorschaubilder & Zertifikat-Links
                    $html .= '<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">';
                    if (!empty($p['face_ref_url'])) {
                        $u = htmlspecialchars((string)$p['face_ref_url'], ENT_QUOTES, 'UTF-8');
                        $html .= '<a href="' . $u . '" target="_blank" style="text-decoration:none; border:1px solid #cbd5e1; background:#fff; padding:3px; border-radius:4px; text-align:center;">
                            <img src="' . $u . '" alt="KI-Selfie" style="width:68px; height:50px; object-fit:cover; display:block; border-radius:3px;">
                            <span style="font-size:10px; color:#334155;">1. KI-Selfie</span>
                        </a>';
                    }
                    if (!empty($p['id_shot_url'])) {
                        $u = htmlspecialchars((string)$p['id_shot_url'], ENT_QUOTES, 'UTF-8');
                        $html .= '<a href="' . $u . '" target="_blank" style="text-decoration:none; border:1px solid #cbd5e1; background:#fff; padding:3px; border-radius:4px; text-align:center;">
                            <img src="' . $u . '" alt="ID-Shot" style="width:68px; height:50px; object-fit:cover; display:block; border-radius:3px;">
                            <span style="font-size:10px; color:#334155;">2. ID-Shot</span>
                        </a>';
                    }
                    if (!empty($p['id_front_url'])) {
                        $u = htmlspecialchars((string)$p['id_front_url'], ENT_QUOTES, 'UTF-8');
                        $html .= '<a href="' . $u . '" target="_blank" style="text-decoration:none; border:1px solid #cbd5e1; background:#fff; padding:3px; border-radius:4px; text-align:center;">
                            <img src="' . $u . '" alt="Ausweis" style="width:68px; height:50px; object-fit:cover; display:block; border-radius:3px;">
                            <span style="font-size:10px; color:#334155;">3. Ausweis</span>
                        </a>';
                    }
                    if ($isReady && !empty($p['certificate_url'])) {
                        $u = htmlspecialchars((string)$p['certificate_url'], ENT_QUOTES, 'UTF-8');
                        $html .= '<a href="' . $u . '" target="_blank" style="display:inline-flex; align-items:center; padding:6px 10px; border-radius:4px; background:#eff6ff; border:1px solid #93c5fd; color:#1d4ed8; font-weight:bold; text-decoration:none;">
                            📄 Unterschriebene Freigabe / PDF öffnen
                        </a>';
                    }
                    $html .= '</div>';

                    // 1-Klick Admin-Prüfbuttons (nur anzeigen, wenn tatsächlich ein ID-Shot hochgeladen wurde!)
                    $html .= '
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; border-top:1px dashed #cbd5e1; padding-top:6px;">
                        <button type="button" onclick="acpVerifyCoActor(' . $pId . ')"
                                style="background:#16a34a; color:#fff; border:none; border-radius:4px; padding:4px 10px; font-weight:bold; cursor:pointer; font-size:11.5px;">
                            ✓ ID-Shot &amp; Person bestätigen
                        </button>
                        <button type="button" onclick="acpRejectCoActor(' . $pId . ')"
                                style="background:#dc2626; color:#fff; border:none; border-radius:4px; padding:4px 10px; font-weight:bold; cursor:pointer; font-size:11.5px;">
                            ✗ ID-Shot ablehnen
                        </button>
                    </div>';
                } else {
                    $html .= '
                    <div style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:6px 10px; border-radius:4px; font-size:11.5px;">
                        ℹ️ <b>Noch kein ID-Shot hochgeladen:</b> Übergangsphase aktiv – dieser Film kann trotzdem regulär geprüft und veröffentlicht werden.
                    </div>';
                }

                $html .= '</div>';
            }
        }

        $html .= '
            <input type="hidden" name="acp_co_actor_action" id="acp_co_actor_action" value="">
            <input type="hidden" name="acp_co_actor_id" id="acp_co_actor_id" value="">
            <input type="hidden" name="acp_co_actor_reject_reason" id="acp_co_actor_reject_reason" value="">
            <script type="text/javascript">
            function acpVerifyCoActor(id) {
                document.getElementById("acp_co_actor_action").value = "verify";
                document.getElementById("acp_co_actor_id").value = id;
                document.getElementById("acp_co_actor_action").form.submit();
            }
            function acpRejectCoActor(id) {
                var r = prompt("Grund für die Ablehnung des ID-Shots / Drehpartners:", "Der Personalausweis ist auf dem ID-Shot leider nicht scharf genug lesbar. Bitte nimm den ID-Shot per Smartphone-QR-Code erneut auf.");
                if (r === null) return;
                document.getElementById("acp_co_actor_action").value = "reject";
                document.getElementById("acp_co_actor_id").value = id;
                document.getElementById("acp_co_actor_reject_reason").value = r;
                document.getElementById("acp_co_actor_action").form.submit();
            }
            </script>
        </div>';

        return $html;
    }
}
