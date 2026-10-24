<?php
declare(strict_types=1);

/**
 * EroCloud MCP (v2.0) - Öffentliches 1-Klick Abmelde- & Benachrichtigungs-Center (RFC 8058)
 * Aufrufbar über den HMAC-signierten Abmeldelink im E-Mail-Footer oder List-Unsubscribe-Header:
 *   unsubscribe.php?m=<merchant_id>&e=<event_key>&s=<hmac_signature>
 *
 * Alle Texte in persönlicher Du-Form gemäß Master Rules.
 */

define('SAFE_INC', 1);

require_once __DIR__ . '/../app/bootstrap.php';

$merchantId = isset($_REQUEST['m']) ? abs((int)$_REQUEST['m']) : 0;
$eventKey   = isset($_REQUEST['e']) ? trim((string)$_REQUEST['e']) : '';
$signature  = isset($_REQUEST['s']) ? trim((string)$_REQUEST['s']) : '';

$isValid = ($merchantId > 0 && $eventKey !== '' && $signature !== '')
    && \EroCloudNotificationCenter::verifyUnsubscribeSignature($merchantId, $eventKey, $signature);

// RFC 8058 One-Click POST durch E-Mail-Clients (Gmail, Yahoo, Apple Mail)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['List-Unsubscribe']) && $isValid) {
    \EroCloudNotificationCenter::setPreference($merchantId, $eventKey, false, 'rfc8058_oneclick');
    http_response_code(200);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OK - Unsubscribed';
    exit;
}

$statusMessage = '';
$statusType    = 'success';

if ($isValid) {
    $catalog = \EroCloudNotificationCenter::getEventCatalog();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
        $enabledKeys = isset($_POST['enabled_events']) && is_array($_POST['enabled_events']) ? $_POST['enabled_events'] : [];
        foreach (array_keys($catalog) as $catKey) {
            $on = in_array($catKey, $enabledKeys, true);
            \EroCloudNotificationCenter::setPreference($merchantId, $catKey, $on, 'unsubscribe_page');
        }
        $statusMessage = 'Deine Benachrichtigungs-Einstellungen wurden erfolgreich aktualisiert.';
    } else {
        // Direkt beim ersten Aufruf per 1-Klick die angeklickte Kategorie abbestellen
        if (isset($catalog[$eventKey])) {
            \EroCloudNotificationCenter::setPreference($merchantId, $eventKey, false, 'email_link');
            $statusMessage = 'Du hast die E-Mail-Benachrichtigung „' . $catalog[$eventKey]['title'] . '“ erfolgreich abbestellt.';
        }
    }
}

$prefs   = $isValid ? \EroCloudNotificationCenter::getMerchantPreferences($merchantId) : [];
$mInfo   = $isValid && class_exists('EroCloudMailer') ? \EroCloudMailer::getMerchantRecipientInfo($merchantId) : ['creator_name' => 'Creator'];
$baseUrl = class_exists('EroCloudMailer') ? \EroCloudMailer::getMcpWebUrl() : 'index.php';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Mail-Benachrichtigungen verwalten – EroCloud</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/fonts.css">
  <style>
    body {
      background: #f1f5f9;
      color: #0f172a;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .unsub-card {
      max-width: 600px;
      margin: 40px auto;
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
      overflow: hidden;
    }
  </style>
</head>
<body class="p-3">

<div class="unsub-card">
  <div class="p-4 border-bottom bg-light d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <img src="assets/img/erocloud_logo.png" alt="EroCloud" style="height: 32px;">
      <div>
        <div class="fw-bold lh-1">Meine EroCloud</div>
        <small class="text-muted">E-Mail-Benachrichtigungen verwalten</small>
      </div>
    </div>
    <a href="<?= htmlspecialchars($baseUrl . '/#/startseite', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">
      Zum Partner-Bereich &rarr;
    </a>
  </div>

  <div class="p-4">
    <?php if (!$isValid): ?>
      <div class="alert alert-danger mb-0">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Ungültiger oder unvollständiger Link</h6>
        <p class="small mb-2">Dieser Abmeldelink konnte leider nicht verifiziert werden. Du kannst deine Benachrichtigungen jederzeit direkt in deinem Partner-Bereich über das Glocken-Symbol oben rechts anpassen.</p>
        <a href="<?= htmlspecialchars($baseUrl . '/#/startseite', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-danger">Zum Partner-Bereich</a>
      </div>
    <?php else: ?>
      <?php if ($statusMessage !== ''): ?>
        <div class="alert alert-<?= $statusType ?> d-flex align-items-center gap-2">
          <i class="bi bi-check-circle-fill fs-5"></i>
          <div><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      <?php endif; ?>

      <p class="text-muted small mb-3">
        Hallo <strong><?= htmlspecialchars((string)$mInfo['creator_name'], ENT_QUOTES, 'UTF-8') ?></strong>, hier kannst du jederzeit selbst festlegen, über welche Ereignisse wir dich per E-Mail informieren dürfen:
      </p>

      <form method="post" action="">
        <input type="hidden" name="m" value="<?= (int)$merchantId ?>">
        <input type="hidden" name="e" value="<?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="s" value="<?= htmlspecialchars($signature, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="save_prefs" value="1">

        <div class="list-group mb-3">
          <?php foreach ($prefs as $k => $item): ?>
            <label class="list-group-item d-flex align-items-start justify-content-between gap-3 p-3" style="cursor: pointer;">
              <div>
                <div class="fw-bold mb-1">
                  <i class="bi <?= htmlspecialchars((string)$item['icon'], ENT_QUOTES, 'UTF-8') ?> text-primary me-1"></i>
                  <?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="small text-muted"><?= htmlspecialchars((string)$item['description'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" role="switch"
                       name="enabled_events[]"
                       value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>"
                       <?= !empty($item['enabled']) ? 'checked' : '' ?>
                       style="width: 2.5em; height: 1.3em;">
              </div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary fw-semibold px-4">
            <i class="bi bi-check2-circle me-1"></i> Auswahl speichern
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
