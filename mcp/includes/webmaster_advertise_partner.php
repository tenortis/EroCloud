<?php

if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

$site .= '
<div class="card border-0 shadow-sm text-center p-4 p-md-5 my-4">
    <div class="card-body">
        <div class="mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle p-4" style="width: 90px; height: 90px;">
                <i class="bi bi-exclamation-triangle display-4"></i>
            </div>
        </div>
        <h2 class="fw-bold text-dark mb-3">Partnerprogramm eingestellt</h2>
        <p class="lead text-muted mb-4">
            Das <strong>Partnerprogramm</strong> wurde im <strong>Juli 2026</strong> eingestellt und steht nicht mehr zur Verf&uuml;gung.
        </p>
        <p class="text-secondary mb-4">
            Sollten Sie Fragen hierzu haben oder Unterst&uuml;tzung ben&ouml;tigen, wenden Sie sich bitte an unseren Support.
        </p>
        <div>
            <a href="'.MCP_URL.'/Startseite" class="btn btn-primary btn-lg shadow-sm px-4">
                <i class="bi bi-house-door me-2"></i>Zur&uuml;ck zur Startseite
            </a>
        </div>
    </div>
</div>';

?>
