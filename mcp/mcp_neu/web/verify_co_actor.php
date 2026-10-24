<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) - Mobile Smartphone Live-Verifizierung für Drehpartner & Account-Inhaber ("Ich")
 * Aufrufbar über den einmaligen QR-Code (`verify_co_actor.php?t=<qr_token>`).
 *
 * Features:
 * 1. Erfassung der Klarnamen-, Geburtsdatums- (18+ Check), Adress- und Ausweisdaten
 * 2. Geführte HTML5 Live-Kamera (`getUserMedia` mit SVG-Zielrahmen + Kamera-Capture-Fallback):
 *    - Aufnahme 1: Biometrisches Frontal-Selfie NUR Gesicht (für spätere KI-Gesichtserkennung in Videos)
 *    - Aufnahme 2: Klassischer ID-Shot (Gesicht + Ausweis gemeinsam im Bild)
 *    - Aufnahme 3: Ausweis-Nahaufnahme im Klartext (Vorderseite + optional Rückseite)
 * 3. Rechtsverbindliche Einverständniserklärung mit Finger-Unterschrift auf dem Touchscreen (Signature Pad)
 */

define('SAFE_INC', 1);

require_once __DIR__ . '/../app/bootstrap.php';

$token = isset($_GET['t']) ? trim((string)$_GET['t']) : (isset($_POST['t']) ? trim((string)$_POST['t']) : '');
$coActor = $token !== '' ? \CoActorManager::getCoActorByToken($token) : null;

if ($coActor === null) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Ungültiger oder abgelaufener QR-Code – EroCloud</title>
      <link rel="stylesheet" href="assets/css/bootstrap.min.css">
      <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
      <div class="card border-0 shadow-sm rounded-4 p-4 text-center" style="max-width: 460px;">
        <div class="text-danger mb-3"><i class="bi bi-qr-code-scan" style="font-size: 3rem;"></i></div>
        <h4 class="fw-bold mb-2">Dieser QR-Code ist nicht mehr gültig</h4>
        <p class="text-muted small mb-0">
          Bitte erstelle im EroCloud Partner-Bereich einfach mit einem Klick einen neuen QR-Code und scanne ihn erneut mit deinem Smartphone.
        </p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$coActorId     = (int)$coActor['id'];
$merchantId    = (int)$coActor['merchant_id'];
$isOwner       = !empty($coActor['is_owner']);
$merchantIdent = \CoActorManager::getMerchantIdentity($merchantId);

// ============================================================================
// POST-Handler: Speichert Daten, Live-Kamera-Bilder und digitale Unterschrift
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_verification') {
    header('Content-Type: application/json; charset=UTF-8');

    $firstName   = trim(strip_tags((string)($_POST['first_name'] ?? '')));
    $lastName    = trim(strip_tags((string)($_POST['last_name'] ?? '')));
    $creatorName = trim(strip_tags((string)($_POST['creator_name'] ?? '')));
    $birthDate   = \CoActorManager::normalizeDate((string)($_POST['birth_date'] ?? ''));
    $street      = trim(strip_tags((string)($_POST['street'] ?? '')));
    $zipCode     = trim(strip_tags((string)($_POST['zip_code'] ?? '')));
    $city        = trim(strip_tags((string)($_POST['city'] ?? '')));
    $country     = strtoupper(substr(trim(strip_tags((string)($_POST['country'] ?? 'DE'))), 0, 5));
    $idDocType   = trim(strip_tags((string)($_POST['id_doc_type'] ?? 'id_card')));
    $idDocNumber = trim(strip_tags((string)($_POST['id_doc_number'] ?? '')));
    $idDocValid  = trim(strip_tags((string)($_POST['id_doc_valid_until'] ?? '')));
    $consentOk   = isset($_POST['confirm_consent']) && (string)$_POST['confirm_consent'] === '1';

    $errors = [];
    if ($firstName === '' || $lastName === '') {
        $errors[] = 'Bitte gib deinen vollständigen Vor- und Nachnamen (wie auf deinem Ausweis) an.';
    }
    if ($birthDate === '') {
        $errors[] = 'Bitte gib dein Geburtsdatum an.';
    } else {
        $ageYears = (int)floor((time() - (int)strtotime($birthDate)) / (365.2425 * 86400));
        if ($ageYears < 18 || $ageYears > 105) {
            $errors[] = 'Du musst mindestens 18 Jahre alt (volljährig) sein.';
        }
    }
    if ($street === '' || $zipCode === '' || $city === '') {
        $errors[] = 'Bitte gib deine vollständige Wohnanschrift (Straße, PLZ und Ort) an.';
    }
    if ($idDocNumber === '') {
        $errors[] = 'Bitte gib die Nummer deines Ausweisdokuments an.';
    }
    if (!$consentOk) {
        $errors[] = 'Bitte bestätige die Volljährigkeit und Einverständniserklärung durch Setzen des Häkchens.';
    }

    $storageDir = \CoActorManager::getStorageDir($merchantId, $coActorId);

    // Hilfsfunktion zum Speichern eines Base64-Data-URI-Bildes oder Datei-Uploads
    $saveImageInput = static function (string $fieldKey, string $filePrefix) use ($storageDir): string {
        // 1. Base64 Data-URI aus Live-Kamera / Canvas prüfen
        $b64 = isset($_POST[$fieldKey . '_data']) ? trim((string)$_POST[$fieldKey . '_data']) : '';
        if ($b64 !== '' && preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $b64, $m)) {
            $ext     = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
            $decoded = base64_decode($m[2], true);
            if (is_string($decoded) && strlen($decoded) > 512 && strlen($decoded) <= 15728640) {
                $imgInfo = @getimagesizefromstring($decoded);
                if ($imgInfo !== false && $imgInfo[0] >= 200 && $imgInfo[1] >= 100) {
                    $fileName = $filePrefix . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                    if (@file_put_contents($storageDir . '/' . $fileName, $decoded) !== false) {
                        return $fileName;
                    }
                }
            }
        }

        // 2. Fallback: Direktes Kamera-File-Input (<input type="file" capture="...">)
        if (isset($_FILES[$fieldKey]) && is_array($_FILES[$fieldKey]) && (int)$_FILES[$fieldKey]['error'] === UPLOAD_ERR_OK) {
            $tmpName = (string)$_FILES[$fieldKey]['tmp_name'];
            $imgInfo = @getimagesize($tmpName);
            if ($imgInfo !== false && in_array($imgInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                $ext = match ($imgInfo[2]) {
                    IMAGETYPE_PNG  => 'png',
                    IMAGETYPE_WEBP => 'webp',
                    default        => 'jpg',
                };
                $fileName = $filePrefix . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                if (@move_uploaded_file($tmpName, $storageDir . '/' . $fileName)) {
                    return $fileName;
                }
            }
        }

        return '';
    };

    $faceRefFile = $saveImageInput('face_ref', 'face_ref');
    if ($faceRefFile === '' && $coActor['face_ref_img'] !== '') {
        $faceRefFile = (string)$coActor['face_ref_img'];
    }
    if ($faceRefFile === '') {
        $errors[] = 'Bitte nimm in Schritt 2 das frontale Referenz-Selfie (Aufnahme 1) mit deiner Kamera auf.';
    }

    $idShotFile = $saveImageInput('id_shot', 'id_shot');
    if ($idShotFile === '' && $coActor['id_shot_img1'] !== '') {
        $idShotFile = (string)$coActor['id_shot_img1'];
    }
    if ($idShotFile === '') {
        $errors[] = 'Bitte nimm in Schritt 2 den ID-Shot (Aufnahme 2: Gesicht + Ausweis nebeneinander) auf.';
    }

    $idFrontFile = $saveImageInput('id_front', 'id_front');
    if ($idFrontFile === '' && $coActor['id_front_img'] !== '') {
        $idFrontFile = (string)$coActor['id_front_img'];
    }
    if ($idFrontFile === '') {
        $errors[] = 'Bitte fotografiere in Schritt 2 die Vorderseite deines Ausweises (Aufnahme 3) gut lesbar ab.';
    }

    $idBackFile = $saveImageInput('id_back', 'id_back');
    if ($idBackFile === '' && $coActor['id_back_img'] !== '') {
        $idBackFile = (string)$coActor['id_back_img'];
    }

    // Unterschrift auf dem Display (Base64 PNG) speichern
    $sigDataUri = isset($_POST['signature_data']) ? trim((string)$_POST['signature_data']) : '';
    $sigFile    = (string)($coActor['consent_doc_file'] ?? '');
    if ($sigDataUri !== '' && preg_match('/^data:image\/png;base64,(.+)$/i', $sigDataUri, $sm)) {
        $sigBytes = base64_decode($sm[1], true);
        if (is_string($sigBytes) && strlen($sigBytes) > 300) {
            $sigFile = 'signature_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.png';
            @file_put_contents($storageDir . '/' . $sigFile, $sigBytes);
        }
    }
    if ($sigFile === '') {
        $errors[] = 'Bitte unterschreibe in Schritt 3 mit deinem Finger im Unterschriftenfeld auf dem Display.';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
        exit;
    }

    $now      = date('Y-m-d H:i:s');
    $clientIp = function_exists('getUserIP') ? (string)getUserIP() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $clientUa = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $docHash  = hash('sha256', implode('|', [
        $coActor['ref_code'],
        $firstName,
        $lastName,
        $birthDate,
        $idDocNumber,
        $faceRefFile,
        $idShotFile,
        $sigFile,
        $now,
        $clientIp,
    ]));

    $sigMetaJson = (string)json_encode([
        'signed_at'   => $now,
        'ip'          => $clientIp,
        'user_agent'  => $clientUa,
        'sha256'      => $docHash,
        'method'      => 'qr_smartphone_touch_signature',
        'merchant_id' => $merchantId,
    ], JSON_UNESCAPED_UNICODE);

    if (!in_array($idDocType, ['id_card', 'passport', 'drivers_license'], true)) {
        $idDocType = 'id_card';
    }

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
        `id_doc_type`         = '" . p4c_escape_string($idDocType) . "',
        `id_doc_number`       = AES_ENCRYPT('" . p4c_escape_string($idDocNumber) . "', '" . AES_KEY . "'),
        `id_doc_valid_until`  = '" . p4c_escape_string($idDocValid) . "',
        `verification_method` = 'qr_live',
        `face_ref_img`        = '" . p4c_escape_string($faceRefFile) . "',
        `id_shot_img1`        = '" . p4c_escape_string($idShotFile) . "',
        `id_front_img`        = '" . p4c_escape_string($idFrontFile) . "',
        `id_back_img`         = '" . p4c_escape_string($idBackFile) . "',
        `consent_doc_file`    = '" . p4c_escape_string($sigFile) . "',
        `signature_data_json` = '" . p4c_escape_string($sigMetaJson) . "',
        `ai_face_ready`       = 1,
        `checked_status`      = 'under_review',
        `rejection_reason`    = '',
        `submitted_datetime`  = '" . p4c_escape_string($now) . "'
        WHERE `id` = '" . $coActorId . "' AND `merchant_id` = '" . $merchantId . "'
        LIMIT 1;", __FILE__, __LINE__);

    // Partner per E-Mail über den Eingang der Smartphone-Verifizierung informieren
    if ($merchantId > 0 && class_exists('EroCloudMailer')) {
        try {
            $refCode   = (string)($coActor['ref_code'] ?? sprintf('DP-%d-%03d', $merchantId, $coActorId));
            $roleLabel = $isOwner ? 'Account-Inhaber („Ich“)' : 'Drehpartner';
            $aliasName = $creatorName !== '' ? $creatorName : $refCode;
            \EroCloudMailer::sendCoActorStatusNotification([
                'merchant_id' => $merchantId,
                'co_actor_id' => $coActorId,
                'status_type' => 'submitted',
                'ref_code'    => $refCode,
                'role_label'  => $roleLabel,
                'alias_label' => $aliasName,
            ]);
        } catch (\Throwable $e) {
            // Nicht-kritischer Fehler beim E-Mail-Versand
        }
    }

    echo json_encode([
        'status'          => 'ok',
        'message'         => 'Vielen Dank! Deine Daten, Live-Kamera-Aufnahmen und deine Unterschrift wurden erfolgreich übermittelt.',
        'certificate_url' => 'co_actor_media.php?type=certificate&t=' . rawurlencode($token),
    ]);
    exit;
}

$maxBirthDate = date('Y-m-d', strtotime('-18 years'));
$certUrl      = 'co_actor_media.php?type=certificate&t=' . rawurlencode($token);
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= $isOwner ? 'Mein eigener ID-Check (Ich)' : 'Drehpartner-Verifizierung & Freigabe' ?> – EroCloud</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/fonts.css">
  <style>
    body {
      background: #f1f5f9;
      color: #0f172a;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      padding-bottom: 48px;
    }
    .verify-container {
      max-width: 640px;
      margin: 0 auto;
      padding: 16px;
    }
    .step-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
      margin-bottom: 18px;
      overflow: hidden;
    }
    .step-header {
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .step-badge {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #0284c7;
      color: #fff;
      font-weight: 700;
      font-size: 0.85rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .cam-slot {
      border: 2px dashed #cbd5e1;
      border-radius: 14px;
      padding: 16px;
      background: #f8fafc;
      transition: all 0.2s ease;
    }
    .cam-slot.captured {
      border-style: solid;
      border-color: #16a34a;
      background: #f0fdf4;
    }
    .sig-canvas-wrap {
      border: 2px dashed #0284c7;
      border-radius: 12px;
      background: #ffffff;
      position: relative;
      touch-action: none;
    }
    #signature-canvas {
      width: 100%;
      height: 190px;
      display: block;
      cursor: crosshair;
      touch-action: none;
    }
    /* Fullscreen Live-Kamera Modal mit Visier-Rahmen */
    .live-cam-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.96);
      z-index: 9999;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 16px;
      color: #fff;
    }
    .live-cam-stage {
      position: relative;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border-radius: 16px;
      background: #000;
      margin: 10px 0;
    }
    #live-cam-video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .cam-guide-svg {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
    }
  </style>
</head>
<body>

<div class="verify-container">
  <!-- Marken-Kopfbereich -->
  <div class="d-flex align-items-center justify-content-between bg-white p-3 rounded-4 border shadow-sm mb-3">
    <div class="d-flex align-items-center gap-2">
      <img src="assets/img/erocloud_logo.png" alt="EroCloud" style="height: 32px;">
      <div>
        <div class="fw-bold lh-1" style="font-size: 0.95rem;">EroCloud ID- &amp; Freigabe-Check</div>
        <small class="text-muted" style="font-size: 0.72rem;">Ref: <?= htmlspecialchars((string)$coActor['ref_code'], ENT_QUOTES, 'UTF-8') ?></small>
      </div>
    </div>
    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
      <i class="bi bi-shield-lock-fill me-1"></i>AES-Verschlüsselt
    </span>
  </div>

  <!-- Erfolgs-Ansicht (falls gerade eingereicht) -->
  <div id="verify-success-screen" class="<?= $coActor['is_complete'] ? '' : 'd-none' ?> step-card p-4 text-center">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3" style="width: 68px; height: 68px;">
      <i class="bi bi-patch-check-fill fs-1"></i>
    </div>
    <h4 class="fw-bold text-success mb-2">Verifizierung &amp; Unterschrift erfolgreich gespeichert!</h4>
    <p class="text-muted small mb-3">
      Deine Daten, Live-Kamera-Aufnahmen und deine digitale Unterschrift wurden sicher übermittelt. Das Fenster im Partner-Account aktualisiert sich automatisch.
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <a href="<?= htmlspecialchars($certUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" id="btn-open-cert-link" class="btn btn-primary fw-semibold">
        <i class="bi bi-file-earmark-check me-1"></i> Einverständniserklärung ansehen / PDF drucken
      </a>
      <button type="button" class="btn btn-outline-secondary" onclick="window.openEditVerificationForm && window.openEditVerificationForm()">
        <i class="bi bi-pencil me-1"></i> Angaben oder Fotos aktualisieren
      </button>
    </div>
  </div>

  <!-- Hauptformular -->
  <form id="verify-main-form" class="<?= $coActor['is_complete'] ? 'd-none' : '' ?>" novalidate>
    <input type="hidden" name="action" value="submit_verification">
    <input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Einleitung -->
    <div class="alert alert-primary border-0 shadow-sm rounded-4 mb-3">
      <?php if ($isOwner): ?>
        <div class="fw-bold mb-1"><i class="bi bi-person-badge-fill me-1"></i>Hallo <?= htmlspecialchars((string)$merchantIdent['full_name'], ENT_QUOTES, 'UTF-8') ?> (Eigen-Verifizierung „Ich“)</div>
        <div class="small">
          Bitte nimm hier einmalig mit deinem Smartphone dein <strong>Referenz-Selfie</strong>, deinen <strong>ID-Shot (Ausweis neben deinem Gesicht)</strong> sowie deine <strong>Unterschrift</strong> auf. Danach kannst du dich bei all deinen Filmen direkt auswählen.
        </div>
      <?php else: ?>
        <div class="fw-bold mb-1"><i class="bi bi-people-fill me-1"></i>Drehpartner-Freigabe für <?= htmlspecialchars((string)$merchantIdent['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="small">
          Damit gemeinsame Filme veröffentlicht werden dürfen, trage bitte deine Ausweisdaten ein, mache kurz <strong>3 Live-Fotos mit deiner Handykamera</strong> und <strong>unterschreibe unten mit dem Finger auf dem Display</strong>.
        </div>
      <?php endif; ?>
    </div>

    <div id="verify-error-alert" class="alert alert-danger d-none rounded-3 small"></div>

    <!-- SCHRITT 1: Persönliche Daten -->
    <div class="step-card">
      <div class="step-header">
        <span class="step-badge">1</span>
        <div>
          <div class="fw-bold lh-1">Deine persönlichen Daten (laut Ausweis)</div>
          <small class="text-muted">Alle Angaben werden streng vertraulich &amp; verschlüsselt gespeichert</small>
        </div>
      </div>
      <div class="p-3">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small fw-semibold mb-1">Vorname (laut Ausweis) <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control" required
                   value="<?= htmlspecialchars((string)$coActor['first_name'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="z. B. Lisa">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold mb-1">Nachname (laut Ausweis) <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" required
                   value="<?= htmlspecialchars((string)$coActor['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="z. B. Müller">
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label small fw-semibold mb-1">Creator- / Künstlername <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" name="creator_name" class="form-control"
                   value="<?= htmlspecialchars((string)$coActor['creator_name'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="z. B. LillyLove (falls vorhanden)">
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label small fw-semibold mb-1">Geburtsdatum (mind. 18 J.) <span class="text-danger">*</span></label>
            <input type="date" name="birth_date" class="form-control" required
                   max="<?= $maxBirthDate ?>"
                   value="<?= htmlspecialchars((string)$coActor['birth_date'], ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold mb-1">Straße &amp; Hausnummer <span class="text-danger">*</span></label>
            <input type="text" name="street" class="form-control" required
                   value="<?= htmlspecialchars((string)$coActor['street'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Musterstraße 12">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold mb-1">PLZ <span class="text-danger">*</span></label>
            <input type="text" name="zip_code" class="form-control" required
                   value="<?= htmlspecialchars((string)$coActor['zip_code'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="10115">
          </div>
          <div class="col-5">
            <label class="form-label small fw-semibold mb-1">Wohnort <span class="text-danger">*</span></label>
            <input type="text" name="city" class="form-control" required
                   value="<?= htmlspecialchars((string)$coActor['city'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Berlin">
          </div>
          <div class="col-3">
            <label class="form-label small fw-semibold mb-1">Land <span class="text-danger">*</span></label>
            <select name="country" class="form-select">
              <?php foreach (['DE' => 'DE', 'AT' => 'AT', 'CH' => 'CH', 'ES' => 'ES', 'OTHER' => 'Sonst.'] as $cCode => $cLabel): ?>
                <option value="<?= $cCode ?>" <?= $coActor['country'] === $cCode ? 'selected' : '' ?>><?= $cLabel ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label small fw-semibold mb-1">Ausweis-Dokument <span class="text-danger">*</span></label>
            <select name="id_doc_type" class="form-select">
              <option value="id_card" <?= $coActor['id_doc_type'] === 'id_card' ? 'selected' : '' ?>>Personalausweis</option>
              <option value="passport" <?= $coActor['id_doc_type'] === 'passport' ? 'selected' : '' ?>>Reisepass</option>
              <option value="drivers_license" <?= $coActor['id_doc_type'] === 'drivers_license' ? 'selected' : '' ?>>Führerschein</option>
            </select>
          </div>
          <div class="col-7 col-sm-5">
            <label class="form-label small fw-semibold mb-1">Ausweis-Nummer <span class="text-danger">*</span></label>
            <input type="text" name="id_doc_number" class="form-control font-monospace" required
                   value="<?= htmlspecialchars((string)$coActor['id_doc_number'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="z. B. L01X00T47">
          </div>
          <div class="col-5 col-sm-3">
            <label class="form-label small fw-semibold mb-1">Gültig bis</label>
            <input type="date" name="id_doc_valid_until" class="form-control"
                   value="<?= htmlspecialchars((string)$coActor['id_doc_valid_until'], ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- SCHRITT 2: 3 Geführte Live-Kamera-Aufnahmen -->
    <div class="step-card">
      <div class="step-header">
        <span class="step-badge">2</span>
        <div>
          <div class="fw-bold lh-1">Frische Live-Aufnahmen mit deiner Handykamera</div>
          <small class="text-muted">Bitte achte auf gutes Licht und gestochen scharfe Lesbarkeit</small>
        </div>
      </div>
      <div class="p-3 d-grid gap-3">

        <!-- Aufnahme 2.1: Frontal-Selfie NUR Gesicht (für KI-Gesichtserkennung) -->
        <div class="cam-slot <?= $coActor['face_ref_img'] !== '' ? 'captured' : '' ?>" id="slot-face_ref">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <span class="badge bg-primary mb-1"><i class="bi bi-person-bounding-box me-1"></i>Aufnahme 1 (Pflicht)</span>
              <h6 class="fw-bold mb-1">Frontal-Selfie (Nur dein Gesicht – ohne Ausweis)</h6>
              <p class="small text-muted mb-0">
                Schaue frontal in die Kamera. <strong>Wichtig:</strong> Noch <u>ohne</u> Ausweis im Bild, keine Sonnenbrille und das Gesicht vollständig ausgeleuchtet (wird für den automatischen Gesichtsabgleich benötigt).
              </p>
            </div>
            <img id="preview-face_ref"
                 src="<?= $coActor['face_ref_img'] !== '' ? ('co_actor_media.php?type=face_ref&t=' . rawurlencode($token)) : '' ?>"
                 class="rounded-3 border <?= $coActor['face_ref_img'] !== '' ? '' : 'd-none' ?>"
                 style="width: 78px; height: 78px; object-fit: cover; flex-shrink: 0;" alt="Selfie">
          </div>
          <input type="hidden" name="face_ref_data" id="input-data-face_ref" value="">
          <input type="file" name="face_ref" id="file-fallback-face_ref" accept="image/*" capture="user" class="d-none" onchange="handleFallbackFile(this, 'face_ref')">
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-primary flex-grow-1 fw-semibold" onclick="openLiveCamera('face_ref', 'user', 'Halte dein Gesicht frontal in den ovalen Rahmen (ohne Ausweis)')">
              <i class="bi bi-camera-fill me-1"></i> Selfie jetzt mit Kamera aufnehmen
            </button>
          </div>
        </div>

        <!-- Aufnahme 2.2: Klassischer ID-Shot (Gesicht + Ausweis nebeneinander) -->
        <div class="cam-slot <?= $coActor['id_shot_img1'] !== '' ? 'captured' : '' ?>" id="slot-id_shot">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <span class="badge bg-primary mb-1"><i class="bi bi-person-vcard-fill me-1"></i>Aufnahme 2 (Pflicht)</span>
              <h6 class="fw-bold mb-1">ID-Shot: Dein Gesicht + Ausweis in deiner Hand</h6>
              <p class="small text-muted mb-0">
                Halte deinen Ausweis <strong>direkt neben dein Gesicht</strong>. Sowohl dein Gesicht als auch das Foto und die Daten auf dem Ausweis müssen klar erkennbar sein (Finger dürfen keine Schrift verdecken).
              </p>
            </div>
            <img id="preview-id_shot"
                 src="<?= $coActor['id_shot_img1'] !== '' ? ('co_actor_media.php?type=id_shot&t=' . rawurlencode($token)) : '' ?>"
                 class="rounded-3 border <?= $coActor['id_shot_img1'] !== '' ? '' : 'd-none' ?>"
                 style="width: 78px; height: 78px; object-fit: cover; flex-shrink: 0;" alt="ID-Shot">
          </div>
          <input type="hidden" name="id_shot_data" id="input-data-id_shot" value="">
          <input type="file" name="id_shot" id="file-fallback-id_shot" accept="image/*" capture="user" class="d-none" onchange="handleFallbackFile(this, 'id_shot')">
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-primary flex-grow-1 fw-semibold" onclick="openLiveCamera('id_shot', 'user', 'Links dein Gesicht – rechts daneben deinen Ausweis halten')">
              <i class="bi bi-camera-fill me-1"></i> ID-Shot (Gesicht + Ausweis) aufnehmen
            </button>
          </div>
        </div>

        <!-- Aufnahme 2.3: Ausweis im Klartext (Nahaufnahme) -->
        <div class="cam-slot <?= $coActor['id_front_img'] !== '' ? 'captured' : '' ?>" id="slot-id_front">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <span class="badge bg-primary mb-1"><i class="bi bi-credit-card-2-front-fill me-1"></i>Aufnahme 3 (Pflicht)</span>
              <h6 class="fw-bold mb-1">Ausweis-Vorderseite im Klartext (Nahaufnahme)</h6>
              <p class="small text-muted mb-0">
                Fotografiere deinen Personalausweis oder Reisepass aus der Nähe, sodass <strong>jede Ziffer, dein Name und dein Geburtsdatum gestochen scharf lesbar</strong> sind (ohne Blitz-Spiegelung).
              </p>
            </div>
            <img id="preview-id_front"
                 src="<?= $coActor['id_front_img'] !== '' ? ('co_actor_media.php?type=id_front&t=' . rawurlencode($token)) : '' ?>"
                 class="rounded-3 border <?= $coActor['id_front_img'] !== '' ? '' : 'd-none' ?>"
                 style="width: 96px; height: 64px; object-fit: cover; flex-shrink: 0;" alt="Ausweis Vorderseite">
          </div>
          <input type="hidden" name="id_front_data" id="input-data-id_front" value="">
          <input type="file" name="id_front" id="file-fallback-id_front" accept="image/*" capture="environment" class="d-none" onchange="handleFallbackFile(this, 'id_front')">
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-primary flex-grow-1 fw-semibold" onclick="openLiveCamera('id_front', 'environment', 'Fotografiere deinen Ausweis formatfüllend & gestochen scharf')">
              <i class="bi bi-camera-fill me-1"></i> Ausweis-Vorderseite abfotografieren
            </button>
          </div>
        </div>

        <!-- Aufnahme 2.4: Ausweis-Rückseite (Optional) -->
        <div class="cam-slot <?= $coActor['id_back_img'] !== '' ? 'captured' : '' ?>" id="slot-id_back">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <div>
              <span class="badge bg-secondary mb-1">Optional</span>
              <h6 class="fw-bold mb-1">Ausweis-Rückseite (mit Anschrift)</h6>
            </div>
            <img id="preview-id_back"
                 src="<?= $coActor['id_back_img'] !== '' ? ('co_actor_media.php?type=id_back&t=' . rawurlencode($token)) : '' ?>"
                 class="rounded-3 border <?= $coActor['id_back_img'] !== '' ? '' : 'd-none' ?>"
                 style="width: 96px; height: 64px; object-fit: cover; flex-shrink: 0;" alt="Ausweis Rückseite">
          </div>
          <input type="hidden" name="id_back_data" id="input-data-id_back" value="">
          <input type="file" name="id_back" id="file-fallback-id_back" accept="image/*" capture="environment" class="d-none" onchange="handleFallbackFile(this, 'id_back')">
          <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-1" onclick="openLiveCamera('id_back', 'environment', 'Fotografiere die Rückseite deines Ausweises')">
            <i class="bi bi-camera me-1"></i> Ausweis-Rückseite aufnehmen (optional)
          </button>
        </div>

      </div>
    </div>

    <!-- SCHRITT 3: Einverständniserklärung & Finger-Unterschrift -->
    <div class="step-card">
      <div class="step-header">
        <span class="step-badge">3</span>
        <div>
          <div class="fw-bold lh-1">Einverständniserklärung &amp; Unterschrift</div>
          <small class="text-muted">Unterschreibe direkt mit deinem Finger im weißen Feld unten</small>
        </div>
      </div>
      <div class="p-3">
        <div class="p-3 rounded-3 border bg-light small mb-3" style="max-height: 195px; overflow-y: auto; font-size: 0.8rem; line-height: 1.5;">
          <strong>Verbindliche Dreh- &amp; Veröffentlichungsgenehmigung (Model Release):</strong><br>
          1. Ich bestätige hiermit, dass ich sowohl zum Zeitpunkt der Entstehung sämtlicher Foto-, Audio- und Videoaufnahmen als auch heute mindestens <strong>18 Jahre alt (volljährig)</strong> bin.<br>
          2. Ich wirke aus freiem Willen an den Produktionen von <strong><?= htmlspecialchars((string)$merchantIdent['full_name'], ENT_QUOTES, 'UTF-8') ?></strong> (Partner-Account: <?= htmlspecialchars((string)$merchantIdent['username'], ENT_QUOTES, 'UTF-8') ?>) mit.<br>
          3. Ich stimme ausdrücklich und unwiderruflich zu, dass sämtliche Foto-, Audio- und Videoaufnahmen, in denen ich zu sehen oder zu hören bin, über die Plattform <strong>EroCloud</strong> sowie die angeschlossenen Websites zeitlich und räumlich unbeschränkt veröffentlicht, gestreamt, zum Download angeboten und beworben werden dürfen.<br>
          4. Ich willige ein, dass meine Ausweisdaten, mein ID-Shot und mein Referenz-Selfie zum gesetzlichen Alters- und Einverständnisnachweis sowie zum automatisierten KI-Bildabgleich der Videos verschlüsselt gespeichert werden.
        </div>

        <div class="form-check mb-3 p-3 rounded-3 border bg-primary bg-opacity-10 border-primary border-opacity-25">
          <input class="form-check-input ms-0 me-2" type="checkbox" id="confirm-consent-check" name="confirm_consent" value="1" required>
          <label class="form-check-label small fw-bold text-dark" for="confirm-consent-check">
            Ich bin mindestens 18 Jahre alt, habe die Einverständniserklärung gelesen und stimme der Veröffentlichung meiner Aufnahmen ausdrücklich zu.
          </label>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label small fw-bold mb-0">
            <i class="bi bi-pen-fill text-primary me-1"></i>Deine Unterschrift (mit dem Finger auf dem Display): <span class="text-danger">*</span>
          </label>
          <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0 small" onclick="clearSignaturePad()">
            <i class="bi bi-eraser me-1"></i>Neu unterschreiben
          </button>
        </div>

        <div class="sig-canvas-wrap mb-2">
          <canvas id="signature-canvas"></canvas>
        </div>
        <input type="hidden" name="signature_data" id="input-signature-data" value="">

        <div class="d-grid mt-3">
          <button type="submit" id="btn-submit-verification" class="btn btn-success btn-lg fw-bold shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Jetzt verbindlich unterschreiben &amp; absenden
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- Fullscreen Live-Kamera Modal -->
<div id="live-cam-modal" class="live-cam-overlay d-none">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <div class="fw-bold" id="live-cam-title">Kamera-Aufnahme</div>
      <div class="small text-white-50" id="live-cam-subtitle">Bitte halte das Gerät ruhig</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-light" onclick="closeLiveCamera()">
      <i class="bi bi-x-lg me-1"></i> Schließen
    </button>
  </div>

  <div class="live-cam-stage">
    <video id="live-cam-video" autoplay playsinline muted></video>
    <!-- Dynamischer Zielrahmen (SVG) -->
    <svg id="live-cam-guide" class="cam-guide-svg" viewBox="0 0 400 500" preserveAspectRatio="none"></svg>
  </div>

  <div class="d-flex justify-content-between align-items-center gap-2">
    <button type="button" class="btn btn-outline-light btn-sm" onclick="switchCameraFacing()">
      <i class="bi bi-arrow-repeat me-1"></i> Kamera wechseln
    </button>
    <button type="button" class="btn btn-primary btn-lg px-4 rounded-pill fw-bold shadow" onclick="captureLiveFrame()">
      <i class="bi bi-camera-fill me-1"></i> Foto jetzt auslösen
    </button>
    <button type="button" class="btn btn-outline-light btn-sm" onclick="triggerNativeCameraFallback()">
      System-Kamera
    </button>
  </div>
</div>

<script>
(function() {
  // ==========================================================================
  // 1. HTML5 Touch Signature Pad
  // ==========================================================================
  const sigCanvas = document.getElementById('signature-canvas');
  const sigCtx    = sigCanvas.getContext('2d');
  let isDrawing   = false;
  let hasDrawnSig = false;
  let lastCanvasW = 0;

  function resizeSigCanvas(force) {
    const rect = sigCanvas.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    // Ignoriere reine Höhenänderungen der mobilen Adressleiste beim Scrollen, damit die Unterschrift nicht gelöscht wird
    if (!force && lastCanvasW > 0 && Math.abs(rect.width - lastCanvasW) < 8) {
      return;
    }
    const prevDataUrl = hasDrawnSig ? sigCanvas.toDataURL('image/png') : '';
    lastCanvasW = rect.width;
    const dpr   = window.devicePixelRatio || 1;
    sigCanvas.width  = rect.width * dpr;
    sigCanvas.height = rect.height * dpr;
    sigCtx.scale(dpr, dpr);
    sigCtx.lineWidth   = 2.6;
    sigCtx.lineCap     = 'round';
    sigCtx.lineJoin    = 'round';
    sigCtx.strokeStyle = '#0f172a';

    if (prevDataUrl) {
      const restoreImg = new Image();
      restoreImg.onload = function() {
        sigCtx.drawImage(restoreImg, 0, 0, rect.width, rect.height);
      };
      restoreImg.src = prevDataUrl;
    }
  }
  window.addEventListener('resize', function() { resizeSigCanvas(false); });
  setTimeout(function() { resizeSigCanvas(true); }, 80);

  window.openEditVerificationForm = function() {
    document.getElementById('verify-success-screen').classList.add('d-none');
    document.getElementById('verify-main-form').classList.remove('d-none');
    setTimeout(function() { resizeSigCanvas(true); }, 50);
  };

  function getPointerPos(e) {
    const rect = sigCanvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
  }

  sigCanvas.addEventListener('pointerdown', function(e) {
    e.preventDefault();
    isDrawing = true;
    hasDrawnSig = true;
    const pos = getPointerPos(e);
    sigCtx.beginPath();
    sigCtx.moveTo(pos.x, pos.y);
  });

  sigCanvas.addEventListener('pointermove', function(e) {
    if (!isDrawing) return;
    e.preventDefault();
    const pos = getPointerPos(e);
    sigCtx.lineTo(pos.x, pos.y);
    sigCtx.stroke();
  });

  window.addEventListener('pointerup', function() {
    if (isDrawing) {
      isDrawing = false;
      document.getElementById('input-signature-data').value = sigCanvas.toDataURL('image/png');
    }
  });

  window.clearSignaturePad = function() {
    sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
    hasDrawnSig = false;
    document.getElementById('input-signature-data').value = '';
  };

  // ==========================================================================
  // 2. Geführte HTML5 Live-Kamera mit SVG-Zielrahmen
  // ==========================================================================
  let currentSlot   = '';
  let currentFacing = 'user';
  let activeStream  = null;

  function renderGuideSvg(slot) {
    const svg = document.getElementById('live-cam-guide');
    if (slot === 'face_ref') {
      // Ovaler Rahmen in der Mitte für biometrisches KI-Referenzgesicht
      svg.innerHTML = `
        <ellipse cx="200" cy="230" rx="115" ry="155" fill="none" stroke="#38bdf8" stroke-width="4" stroke-dasharray="10,6"/>
        <text x="200" y="435" fill="#ffffff" font-size="14" font-weight="bold" text-anchor="middle">Gesicht frontal im Oval (OHNE Ausweis)</text>
      `;
    } else if (slot === 'id_shot') {
      // Links Gesicht-Oval + Rechts Ausweis-Rechteck
      svg.innerHTML = `
        <ellipse cx="130" cy="235" rx="82" ry="115" fill="none" stroke="#38bdf8" stroke-width="3.5" stroke-dasharray="8,5"/>
        <rect x="225" y="175" width="155" height="115" rx="10" fill="none" stroke="#22c55e" stroke-width="3.5" stroke-dasharray="8,5"/>
        <text x="130" y="380" fill="#38bdf8" font-size="13" font-weight="bold" text-anchor="middle">Dein Gesicht</text>
        <text x="302" y="315" fill="#22c55e" font-size="13" font-weight="bold" text-anchor="middle">Dein Ausweis</text>
      `;
    } else {
      // Großer Ausweis-Rahmen für Nahaufnahme
      svg.innerHTML = `
        <rect x="30" y="120" width="340" height="230" rx="16" fill="none" stroke="#22c55e" stroke-width="4" stroke-dasharray="10,6"/>
        <text x="200" y="390" fill="#ffffff" font-size="14" font-weight="bold" text-anchor="middle">Ausweis scharf &amp; lesbar im Rahmen platzieren</text>
      `;
    }
  }

  async function startStream() {
    if (activeStream) {
      activeStream.getTracks().forEach(t => t.stop());
      activeStream = null;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      triggerNativeCameraFallback();
      return;
    }
    try {
      activeStream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: currentFacing },
          width: { ideal: 1920 },
          height: { ideal: 1080 }
        },
        audio: false
      });
      const videoEl = document.getElementById('live-cam-video');
      videoEl.srcObject = activeStream;
      await videoEl.play();
    } catch (err) {
      triggerNativeCameraFallback();
    }
  }

  window.openLiveCamera = function(slot, facing, subtitle) {
    currentSlot   = slot;
    currentFacing = facing || 'user';
    document.getElementById('live-cam-subtitle').textContent = subtitle || '';
    renderGuideSvg(slot);
    document.getElementById('live-cam-modal').classList.remove('d-none');
    startStream();
  };

  window.closeLiveCamera = function() {
    if (activeStream) {
      activeStream.getTracks().forEach(t => t.stop());
      activeStream = null;
    }
    document.getElementById('live-cam-modal').classList.add('d-none');
  };

  window.switchCameraFacing = function() {
    currentFacing = (currentFacing === 'user') ? 'environment' : 'user';
    startStream();
  };

  window.triggerNativeCameraFallback = function() {
    closeLiveCamera();
    const fileInput = document.getElementById('file-fallback-' + currentSlot);
    if (fileInput) {
      fileInput.click();
    }
  };

  window.captureLiveFrame = function() {
    const videoEl = document.getElementById('live-cam-video');
    if (!videoEl.videoWidth || !videoEl.videoHeight) {
      triggerNativeCameraFallback();
      return;
    }
    const maxDim = 1600;
    let w = videoEl.videoWidth;
    let h = videoEl.videoHeight;
    if (w > maxDim || h > maxDim) {
      if (w > h) {
        h = Math.round(h * (maxDim / w));
        w = maxDim;
      } else {
        w = Math.round(w * (maxDim / h));
        h = maxDim;
      }
    }
    const c = document.createElement('canvas');
    c.width  = w;
    c.height = h;
    const ctx = c.getContext('2d');
    ctx.drawImage(videoEl, 0, 0, w, h);
    const dataUri = c.toDataURL('image/jpeg', 0.90);

    applyCapturedImage(currentSlot, dataUri);
    closeLiveCamera();
  };

  function applyCapturedImage(slot, dataUri) {
    const hiddenInput = document.getElementById('input-data-' + slot);
    const previewImg  = document.getElementById('preview-' + slot);
    const slotBox     = document.getElementById('slot-' + slot);

    if (hiddenInput) hiddenInput.value = dataUri;
    if (previewImg) {
      previewImg.src = dataUri;
      previewImg.classList.remove('d-none');
    }
    if (slotBox) {
      slotBox.classList.add('captured');
    }
  }

  window.handleFallbackFile = function(inputEl, slot) {
    if (!inputEl.files || !inputEl.files[0]) return;
    const file = inputEl.files[0];
    const reader = new FileReader();
    reader.onload = function(ev) {
      const img = new Image();
      img.onload = function() {
        const maxDim = 1600;
        let w = img.width;
        let h = img.height;
        if (w > maxDim || h > maxDim) {
          if (w > h) {
            h = Math.round(h * (maxDim / w));
            w = maxDim;
          } else {
            w = Math.round(w * (maxDim / h));
            h = maxDim;
          }
        }
        const c = document.createElement('canvas');
        c.width  = w;
        c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        applyCapturedImage(slot, c.toDataURL('image/jpeg', 0.90));
      };
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  };

  // ==========================================================================
  // 3. Formular-Submit via AJAX
  // ==========================================================================
  const form = document.getElementById('verify-main-form');
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const errBox = document.getElementById('verify-error-alert');
    errBox.classList.add('d-none');

    if (hasDrawnSig) {
      document.getElementById('input-signature-data').value = sigCanvas.toDataURL('image/png');
    }

    const btn = document.getElementById('btn-submit-verification');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Wird verschlüsselt übertragen...';

    try {
      const fd = new FormData(form);
      const res = await fetch(window.location.href, {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'ok') {
        throw new Error(data.message || 'Bitte überprüfe deine Eingaben.');
      }

      form.classList.add('d-none');
      const successScreen = document.getElementById('verify-success-screen');
      successScreen.classList.remove('d-none');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      errBox.textContent = err.message || 'Fehler beim Speichern.';
      errBox.classList.remove('d-none');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      btn.disabled = false;
      btn.innerHTML = oldHtml;
    }
  });
})();
</script>
</body>
</html>
