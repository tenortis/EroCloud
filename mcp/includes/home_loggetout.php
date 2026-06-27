<?php

/**
 * @author		Martin Zimmermann
 * @copyright	(C) 2016 adult net applications UG
 * @email 		erocms@gmail.com
  */
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$meta_title = 'Vermarkte deine Filme und Fotoalben auf mehr als 100 Webseiten.';
$meta_description = 'Als Darsteller kannst du deine Webcam senden und im Messenger mit Usern chatten um Geld zu verdienen.';

$site .= '
<div id="site_home_loggetout">
    
    <div id="banner_top">
        <div id="headline_picture">
            <img src="'.URL.'/images/erocloud_welcome.jpg" alt="'.$meta_title.'" title="'.$meta_title.'" />
        </div>
        
        <div id="headline_box">
            <h1 class="h4">'.$meta_title.'</h1>
        </div>
        
        <div id="login_box">

            <div class="col ui-widget-content">
                <h2>Pay4Coins-Login</h2>
                <p>
                    Hier kannst du dich mit deinen Zugangsdaten von Pay4Coins einloggen.
                </p>
                <a href="'.LOGIN_URL.'?ref=erocloud">
                    <div class="sign-up">Jetzt einloggen</div>
                </a>
            </div>

            <div class="col ui-widget-content">
                <h2>Studio-Login</h2>
                <p>
                    Hier kannst du dich einloggen wenn du einen Studio-Login bekommen hast.
                </p>
                <a href="'.MCP_URL.'/StudioLogin">
                    <div class="sign-up">Jetzt einloggen</div>
                </a>
            </div>
        </div>
    </div>
    
    <div style="margin-bottom:60px; text-align:center;">
        <p style="font-size:20px">
            <strong><span style="color:#AE4741">Ero</span><span style="color:#5a5a59">Cloud</span></strong> ist ein Gemeinschaftsprojekt zwischen <b>pay</b><span style="color:#4297d7;">4</span><span style="font-weight:100;">coins</span> und <strong><span style="color:#AE4741">Ero</span><span style="color:#5a5a59">CMS</span></strong><sup style="font-size:13px;">&reg;</sup>
        </p>
        <img src="'.URL.'/erocloud_logo.png" alt="'.$meta_title.'" title="'.$meta_title.'" style="width: 250px; height: auto;" />
    </div>

    <div style="margin-bottom:60px;">
        <h2>Die EroCloud ist f&uuml;r dich interessant<h2>
        <ul style="list-style-type:disc; margin-left:40px;">
            <li>wenn du <b>als Darsteller</b> &uuml;ber keine eigene Webseite verf&uuml;gst oder keine eigene Webseite betreiben willst.</li>
            <li>wenn du <b>als Produzent oder Studiobetreiber</b> mit verschiedenen Darstellern zusammen arbeitest und/oder &uuml;ber viel Content wie Filme und Fotoalben verf&uuml;gst.</li>
            <li>wenn du <b>als Webmaster</b> auf der Suche nach hochwertigen Content bist und dein eigenes Erotikportal oder Fetischportal mit verschiedenen Darstellern, aufbauen willst.</li>
            <li>wenn du <b>als Webmaster</b> Geld, mit Werben von neuen Kunden, f&uuml;r verschiedene Webseiten oder an den Ums&auml;tzen von geworbenen Partnern, mit verdienen willst.</li>
        </ul>
    </div>

    <div id="erocloud_for">
        <div class="col">
            <h2>EroCloud f&uuml;r Darsteller</h3>
            <p>Du willst <strong>als Darsteller</strong> deinen eigenen Content -wie Filme und Fotoalben- verkaufen oder mit Usern chatten und auch deine Webcam zeigen um Geld zu verdienen?</p>
            <p style="margin-bottom:20px;"><span style="color: #339966;">EroCloud erm&ouml;glicht es dir <strong>-auch ohne eigene Webseite-</strong>&nbsp;dich und deinen Content erfolgreich auf hunderten Webseiten zu vermarkten.</span></p>
            <a href="'.Pay4Coins_MCP_URL.'/Partner-werden/Content-in-der-EroCloud-vermarkten">
                <div class="sign-up">Jetzt als Darsteller Registrieren</div>
            </a>
        </div>

        <div class="col">
            <h2>EroCloud f&uuml;r Webmaster</h3>
            <p>Du willst Geld mit einer Erotikwebseite verdienen, hast aber <strong>keinen eigenen Content</strong> wie Filme oder Fotoalben?</p>
            <p style="margin-bottom:20px;"><span style="color: #339966;">EroCMS in Verbindung mit EroCloud erm&ouml;glicht es dir, <strong>in nur wenigen Minuten eine eigene Webseite</strong>, mit <strong>verschiedenen Darstellern</strong> und <strong>hunderten Filmen und Fotoalben</strong> online zu stellen.</span> [<a href="https://erocloud.net/Webmaster" target="_blank">mehr erfahren</a>]</p>
            <a href="'.Pay4Coins_MCP_URL.'/Partner-werden/Betreiben-Sie-Ihr-eigenes-Erotikportal">
                <div class="sign-up">Jetzt als Webmaster Registrieren</div>
            </a>
        </div>

        <div class="col">
            <h2>F&uuml;r Produzenten und Studios</h3>
            <p><strong>Du bist Produzent</strong> und verf&uuml;gst &uuml;ber Content von verschieden Darstellern <strong>oder du hast ein Studio</strong> und arbeitest mit mehreren Darstellern zusammen?</p>
            <p style="margin-bottom:20px;"><span style="color: #339966;">EroCloud erm&ouml;glicht es dir, deine Darsteller inklusive deren Webcams, Chats und Content wie Filme und Fotoalben, erfolgreich auf hunderten Webseiten zu vermarkten.</span></p>
            <a href="'.Pay4Coins_MCP_URL.'/Partner-werden/Content-in-der-EroCloud-vermarkten">
                <div class="sign-up">Jetzt als Produzent Registrieren</div>
            </a>
        </div>
    </div>

    <h3>Deine Vorteile als Darsteller in der EroCloud</h3>
    
    <div id="vorteile">
        <div class="col ui-widget-content">
            <i class="material-symbols-outlined">forum</i>
            <div>
                Chatte <b>in nur einem Messenger</b> auf allen teilnehmenden Seiten mit deinen Kunden.<br />
                <a href="https://pay4coins.net/2018/07/08/erocloud-wie-sieht-der-messenger-aus/" target="_blank">Hier erf&auml;hrst du mehr.</a>
            </div>
        </div>

        <div class="col ui-widget-content">
            <i class="material-symbols-outlined">videocam</i>
            <div>
                Sende deine <b>Webcam nur einmal in der EroCloud</b> an alle teilnehmenden Seiten gleichzeitig.<br />
                
            </div>
        </div>

        <div class="col ui-widget-content">
            <i class="material-symbols-outlined">video_library</i>
            <div>
                Lade <b>alle Filme und Fotoalben nur einmal</b> in der EroCloud hoch und stelle diese auf allen Seiten online.<br />
                <a href="https://pay4coins.net/2018/07/16/erocloud-wie-lade-ich-videos-hoch/" target="_blank">Hier erf&auml;hrst du mehr.</a>
            </div>
        </div>

        <div class="col ui-widget-content">
            <i class="material-symbols-outlined">phone</i>
            <div>
                Du bekommst auf wunsch eine <b>eigene 09005-Hotline</b> mit einer Auszahlung von 1,00 EUR/Min.
            </div>
        </div>
            </div>
    
    <h3 style="margin-top:10px;">Weitere Vorteile</h3>
    <ul>
        <li><i class="material-symbols-outlined md-25">done</i> <span>Deine Filme und Fotoalben werden automatisch auf hunderten Webseiten verkauft.</span></li>
        <li><i class="material-symbols-outlined md-25">done</i> <span>Deine Filme und Fotoalben werden nicht dubliziert! Sie bleiben zentral in der EroCloud.</span></li>
    </ul>

    <h3 style="margin-top:50px;">Deine Verg&uuml;tung</h3>
    <div>
        <p><strong>Als Darsteller, Produzent oder Studiobetreiber erh&auml;ltst du 25-30%* vom Umsatz</strong> der verkauften Filme und Fotoalben, der vom Kunden gesendeten Nachrichten oder der gesendeten Webcam.</p>
        <p><strong>Als Webmaster erh&auml;ltst du im Schnitt 25-40%* der Ums&auml;tze</strong> die auf deiner Seite, durch deine Kunden entstanden sind.</p>
        <p>*Die Ums&auml;tze variieren je nach Zahlungsart mit die der Kunde bezahlt hat.</p>
    </div>
    
    <h3 style="margin-top:40px;">So startest du mit deiner EroCloud</h3>
    <div>
        <p>Ob als Darsteller, Webmaster, Produzent oder Studiobetreiber, im ersten Schritt musst du dich bei Pay4Coins registrieren.<br>
        Nachdem deine Registrierung erfolgreich war und du dich erfolgreich legitimiert hast, hast du &uuml;ber deine Zugangsdaten von Pay4Coins, Zugriff auf deine EroCloud.</p>
        <p>Nun kannst du entscheiden ob du als Darsteller, Produzent oder Studiobetreiber Filme und Fotoalben verkaufen, mit Kunden chatten und/oder deine Webcam senden willst.<br>
        Oder du verf&uuml;gst &uuml;ber keinen eigenen Content und willst als Webmaster, mit deiner eigenen EroCMS-Webseite dein eigenes Erotik- oder Fetischportal aufbauen.</p>
        <p>Jetzt als Partner bei Pay4Coins registrieren: <a href="'.Pay4Coins_MCP_URL.'/Partner-werden" target="_blank" rel="noopener">'.Pay4Coins_MCP_URL.'/Partner-werden</a></p>
    </div>

</div>';
    

?>