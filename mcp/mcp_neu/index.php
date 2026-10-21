<?php
declare(strict_types=1);

// Komfort-Weiterleitung falls /mcp_neu/ direkt ohne /web/ aufgerufen wird
header('Location: web/Startseite');
exit;
