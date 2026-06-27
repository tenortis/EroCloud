<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<div style="font-size:25px; margin-bottom:20px;">Hilfe &amp; Kontakt</div>
<div style="margin-bottom:5px;">Danke, dass du dich f&uuml;r '.PROJECTNAME.' entschieden hast.</div>
<div>Wenn du Fragen oder Probleme hast, setze dich bitte direkt mit uns in Verbindung.</div>
<div style="margin-bottom:20px;">Wir werden dir umgehend Antorten und bei Problemen versuchen zu helfen.</div>
<div style="margin-bottom:20px;">
    <b>Dein Ansprechpatner: Martin</b><br />
    <div>WhatsApp.: +49 (0) 172 97 33 521 (Keine Anrufe!)</div>
    <div>E-Mail: <a href="mailto:'.SUPPORT_EMAIL.'">'.SUPPORT_EMAIL.'</a></div>
</div>';

?>