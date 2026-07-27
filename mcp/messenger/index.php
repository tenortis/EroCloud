<?php

define('SAFE_INC', 1);

include_once("../../config.inc.php");
include_once(MCP_DIR."/common.inc.php");

header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <title><?php echo PROJECTNAME; ?> - Messenger eingestellt</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card border-0 shadow-lg text-center p-4 p-md-5" style="max-width: 600px; border-radius: 1rem;">
        <div class="card-body">
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle p-4" style="width: 90px; height: 90px;">
                    <i class="bi bi-chat-left-slash display-4"></i>
                </div>
            </div>
            <h2 class="fw-bold text-dark mb-3">Messenger-Dienst eingestellt</h2>
            <p class="lead text-muted mb-4">
                Der Messenger-Dienst wurde im <strong>Juli 2026</strong> eingestellt und steht nicht mehr zur Verf&uuml;gung.
            </p>
            <p class="text-secondary mb-4">
                Sollten Sie Fragen hierzu haben oder Unterst&uuml;tzung ben&ouml;tigen, wenden Sie sich bitte an unseren Support.
            </p>
            <div>
                <a href="<?php echo MCP_URL; ?>/Startseite" class="btn btn-primary btn-lg shadow-sm px-4">
                    <i class="bi bi-house-door me-2"></i>Zur&uuml;ck zur Startseite
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
exit;
?>