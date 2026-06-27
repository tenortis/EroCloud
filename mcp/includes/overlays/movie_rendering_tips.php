<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<div style="font-size:25px; margin-bottom:20px;">So renderst du deine Filme richtig</div>
<div style="margin-bottom:20px; line-height:1.4">
    Immer wenn ein Film fertig gestellt wurde, kommt die Frage nach den richtigen Einstellungen zum Rendern.<br />
    <br />
    Die optimale Render-Einstellung gibt es leider nicht. Mit den folgenden Settings sollte es dir aber gelingen einen Film vern&uuml;nfig in mp4 zu Rendern.    
</div>

<div>
    <ul style="list-style-type:square; margin-left:40px; line-height:1.4">
        <li>Video-Codec: AVC / H.264</li>
        <li>Audio-Codec: AAC</li>
        <li>Aufl&ouml;sung: 1920x1080 (FullHD / 1080p)</li>
        <li>Profil: Hoch</li>
        <li>Framerate: 25,000 (PAL)</li>
        <li>Keine Halbbilder: Progressive Scan</li>
        <li>Variable Bitrate, 10.000.000 Bit/s</li>
        <li>Audio: 192kBit/s</li>
    </ul>
</div>


';


?>