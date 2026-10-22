<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) & ACP - Geschützter Medien- & Dokumenten-Proxy für Drehpartner (Co-Actors)
 * Liefert ID-Shots, KI-Referenzbilder, Ausweisfotos sowie das druckbare Einverständnis-Zertifikat
 * ausschließlich an den zugehörigen Partner-Account (MCP), berechtigte Prüfer (ACP) oder während
 * des aktiven QR-Code-Durchlaufs aus.
 */

define('SAFE_INC', 1);

require_once __DIR__ . '/../app/bootstrap.php';

$coActorId = isset($_GET['id']) ? abs((int)$_GET['id']) : 0;
$type      = isset($_GET['type']) ? trim((string)$_GET['type']) : 'id_shot';
$token     = isset($_GET['t']) ? trim((string)$_GET['t']) : '';

if ($coActorId <= 0 && $token === '') {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

$coActor = $token !== ''
    ? \CoActorManager::getCoActorByToken($token)
    : \CoActorManager::getCoActorById($coActorId);

if ($coActor === null) {
    http_response_code(404);
    exit('Eintrag nicht gefunden.');
}

$merchantId = (int)$coActor['merchant_id'];
$coActorId  = (int)$coActor['id'];

// Berechtigungsprüfung:
// 1. Eingeloggter Partner im MCP (nur eigene Drehpartner)
// 2. Eingeloggter Admin im ACP
// 3. Gültiger QR-Token dieses Drehpartners
$isAuthorized = false;
if (function_exists('is_logged_in') && is_logged_in('mcp') === true && abs((int)($_SESSION['merchant_id'] ?? 0)) === $merchantId) {
    $isAuthorized = true;
} elseif (!empty($_SESSION['employee_id']) && isset($_SESSION['logged_id']) && $_SESSION['logged_id'] === 'acp') {
    $isAuthorized = true;
} elseif ($token !== '' && hash_equals((string)$coActor['qr_token'], $token)) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

@session_write_close();

$storageDir = \CoActorManager::getStorageDir($merchantId, $coActorId);

// Fall A: Druckbares / PDF-fähiges Einverständnis-Zertifikat (oder hochgeladenes Alt-Dokument)
if ($type === 'certificate' || $type === 'consent') {
    $consentFile = (string)($coActor['consent_doc_file'] ?? '');
    $sigData     = $coActor['signature_data'] ?? null;

    // Wenn ein manuelles Alt-Dokument (z. B. PDF oder abfotografierter Papiervertrag) hochgeladen wurde und keine digitale Touch-Signatur vorliegt:
    if ($type === 'consent_raw' || ($consentFile !== '' && empty($sigData) && is_file($storageDir . '/' . $consentFile))) {
        $filePath = $storageDir . '/' . $consentFile;
        if (is_file($filePath)) {
            $ext  = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'pdf'         => 'application/pdf',
                'png'         => 'image/png',
                'webp'        => 'image/webp',
                default       => 'image/jpeg',
            };
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="Einverstaendnis_' . $coActor['ref_code'] . '.' . $ext . '"');
            header('Content-Length: ' . (string)filesize($filePath));
            header('Cache-Control: private, max-age=300');
            readfile($filePath);
            exit;
        }
    }

    // Digital unterschriebenes Zertifikat (inkl. eingebetteter Unterschrift als Data-URI) rendern
    $sigDataUri = '';
    if ($consentFile !== '' && is_file($storageDir . '/' . $consentFile)) {
        $rawBytes = @file_get_contents($storageDir . '/' . $consentFile);
        if (is_string($rawBytes) && $rawBytes !== '') {
            $sigDataUri = 'data:image/png;base64,' . base64_encode($rawBytes);
        }
    }

    $merchantIdent = \CoActorManager::getMerchantIdentity($merchantId);
    header('Content-Type: text/html; charset=UTF-8');
    echo \CoActorManager::renderConsentDocumentHtml($coActor, $merchantIdent, $sigDataUri);
    exit;
}

// Fall B: Bilddateien (face_ref, id_shot, id_front, id_back)
$fileColumnMap = [
    'face_ref' => (string)($coActor['face_ref_img'] ?? ''),
    'id_shot'  => (string)($coActor['id_shot_img1'] ?? ''),
    'id_front' => (string)($coActor['id_front_img'] ?? ''),
    'id_back'  => (string)($coActor['id_back_img'] ?? ''),
];

$fileName = $fileColumnMap[$type] ?? '';
if ($fileName === '' && $type === 'avatar') {
    // Für Avatar-Vorschau bevorzugt das saubere KI-Referenzgesicht, sonst den ID-Shot nutzen
    $fileName = (string)($coActor['face_ref_img'] !== '' ? $coActor['face_ref_img'] : $coActor['id_shot_img1']);
}

$filePath = $fileName !== '' ? ($storageDir . '/' . basename($fileName)) : '';
if ($filePath === '' || !is_file($filePath)) {
    // Platzhalter-SVG ausliefern
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">'
        . '<rect width="160" height="160" fill="#F1F5F9"/>'
        . '<circle cx="80" cy="62" r="26" fill="#CBD5E1"/>'
        . '<path d="M34 138c6-26 26-40 46-40s40 14 46 40" fill="#CBD5E1"/>'
        . '</svg>';
    exit;
}

$ext  = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png'   => 'image/png',
    'webp'  => 'image/webp',
    'pdf'   => 'application/pdf',
    default => 'image/jpeg',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($filePath));
header('Cache-Control: private, max-age=300');
readfile($filePath);
exit;
