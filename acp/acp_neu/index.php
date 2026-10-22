<?php
declare(strict_types=1);

// Komfort-Weiterleitung falls /acp_neu/ direkt ohne /web/ aufgerufen wird
header('Location: web/Startseite');
exit;
