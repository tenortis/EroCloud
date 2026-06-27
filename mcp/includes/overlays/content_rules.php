<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$site .= '
<div class="content_ruels_popup">
    <div style="text-align:right; top:20px; right:10px; position:absolute; z-index:1;">
        <a href="#" class="close_overlay" style="border:none; font-size:20px;"><b>&#x2715;</b></a>
    </div>

    <div class="alert alert-warning">Bitte lesen und am Ende best&auml;tigen.</div>

    <div class="container mt-4">
        <div style="font-size:25px; margin-bottom:20px;">Regeln zum Vermarkten meines Content &uuml;ber '.PROJECTNAME.'</div>

        <div style="text-align:center; margin:20px 0">
            <div style="margin:15px 0;">zwischen</div>

            <div style="font-weight:bold;">Cipa Media S.L</div>
            Av. Emilio Luque Moreno 19<br />
            Oficina 6<br />
            ES-38300 La Orotava (S/C de Tenerife)

            <div style="margin:15px 0;">- nachfolgend "Cipa Media" -</div>

            <div>und mir</div>
            <div style="font-weight:bold;">'.$merchant->firstname('aes_decrypt').' '.$merchant->surname('aes_decrypt').'</div>

            <div style="margin:15px 0;">- nachfolgend "Partner" -</div>

            <div style="margin:15px 0;">- gemeinsam "Produzent" genannt -</div>
        </div>

        ';    
        $rules = '
        <div class="alert alert-info">
            Diese Regeln ersetzen alle vorherigen Versionen. Inhalte, die vor Inkrafttreten dieser Regeln hochgeladen wurden, unterliegen ab sofort diesen neuen Bedingungen.
        </div>

        <div class="mb-4">
            <h5 class="fw-bold">Urheberrecht und Vermarktungsrechte</h5>
            <p>Das Urheberrecht an den hochgeladenen Inhalten (Filme, Bilder und Texte, nachfolgend "Content" genannt) verbleibt beim Partner. 
            Mit dem Hochladen des Contents auf <span class="fw-bold">'.DOMAIN.'</span> (nachfolgend "Webseite" genannt) erteilt der Partner 
            Cipa Media eine nicht-exklusive Lizenz zur Vermarktung des Contents. Diese Rechte bleiben für die Dauer des Vertragsverhältnisses 
            bestehen und enden mit der Kündigung durch eine der Vertragsparteien. Nach Beendigung des Vertrags erlischt die Vermarktungsberechtigung 
            von Cipa Media für den Content.</p>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold">Verwaltung von Content</h5>
            <p>Cipa Media behält sich das Recht vor, Content zu löschen, wenn dies aus rechtlichen, ethischen oder anderen berechtigten Gründen 
            erforderlich ist. Rechtliche Gründe umfassen beispielsweise Urheberrechtsverletzungen oder behördliche Anordnungen. 
            Ethische Gründe können Verstöße gegen den Jugendschutz oder diskriminierende Inhalte beinhalten. Filme bleiben jedoch mindestens 
            <span class="fw-bold">ein Jahr</span> zum Verkauf online, bevor sie entfernt werden. Partner werden vorab über die geplante Löschung informiert.</p>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold">Löschung von Content durch den Partner</h5>
            <p>Ein Partner kann jederzeit die Löschung seines Contents beantragen. In diesem Fall wird der Content für neue Käufe entfernt. 
            Content, der bereits von Endkunden erworben wurde, bleibt für diese weiterhin streambar.</p>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold">Nachträgliche Löschung durch Cipa Media</h5>
            <p>Cipa Media behält sich das Recht vor, Content nachträglich zu löschen, wenn dieser innerhalb der letzten 
            <span class="fw-bold">365 Tage</span> nicht gekauft oder angesehen wurde oder aus rechtlichen oder ethischen Gründen entfernt werden muss.</p>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold">Umgang mit Content nach Vertragsbeendigung und technische Einschränkungen</h5>
            <p>Nach Beendigung des Vertragsverhältnisses wird Content, der innerhalb der letzten 
            <span class="fw-bold">365 Tage</span> nicht verkauft oder angesehen wurde, unwiderruflich gelöscht. 
            Der Partner wird vorab über die geplante Löschung informiert. Bereits verkaufter Content bleibt für die jeweiligen Käufer 
            weiterhin streambar, sofern keine technischen Einschränkungen, wie infrastrukturelle oder kapazitätsbedingte Begrenzungen, 
            eine weitere Bereitstellung unmöglich machen.</p>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold">Einhaltung der Schutz- und Urheberrechte</h5>
            <p>Die gewerblichen Schutz- und Urheberrechte Dritter sind zu jeder Zeit zu beachten. Dies umfasst insbesondere die öffentliche 
            Wiedergabe von urheberrechtlich geschützter Musik, Filmaufnahmen und Bildern. Die Nutzung oder Verbreitung von Inhalten, für die 
            keine ausreichende Berechtigung zur öffentlichen Aufführung vorliegt, stellt einen Verstoß gegen diese Nutzungsrichtlinien dar. 
            In solchen Fällen können Schadensersatzforderungen durch die Rechteinhaber entstehen, die an den verantwortlichen Nutzer weitergeleitet werden.</p>
        </div>

        ';

        $site .= utf8_encode($rules);

        $site .= '
    </div>
                
    <div style="text-align:center; margin-top:20px;">
        <input type="button" class="close_overlay button" value="Nicht akzeptieren." />
        <input type="submit" name="upload_content" class="button close_overlay" value="Ich habe die Regeln gelesen, verstanden und akzeptiert." />
    </div>
</div>';



/*
 Nutzungsrichtlinien für hochgeladene Filme

Urheberrecht und VermarktungsrechteDas Urheberrecht an den hochgeladenen Inhalten (Filme, Bilder und Texte, nachfolgend "Content" genannt) verbleibt beim Partner. Mit dem Hochladen des Contents auf erocloud.net (nachfolgend "Webseite" genannt) erteilt der Partner Cipa Media eine nicht-exklusive Lizenz zur Vermarktung des Contents. Diese Rechte bleiben für die Dauer des Vertragsverhältnisses bestehen und enden mit der Kündigung durch eine der Vertragsparteien. Nach Beendigung des Vertrags erlischt die Vermarktungsberechtigung von Cipa Media für den Content.

Verwaltung von ContentCipa Media behält sich das Recht vor, Content zu löschen, wenn dies aus rechtlichen, ethischen oder anderen berechtigten Gründen erforderlich ist. Rechtliche Gründe umfassen beispielsweise Urheberrechtsverletzungen oder behördliche Anordnungen. Ethische Gründe können Verstöße gegen den Jugendschutz oder diskriminierende Inhalte beinhalten. Filme bleiben jedoch mindestens ein Jahr zum Verkauf online, bevor sie entfernt werden. Partner werden vorab über die geplante Löschung informiert.

Löschung von Content durch den PartnerEin Partner kann jederzeit die Löschung seines Contents beantragen. In diesem Fall wird der Content für neue Käufe entfernt. Content, der bereits von Endkunden erworben wurde, bleibt für diese weiterhin streambar.

Nachträgliche Löschung durch Cipa MediaCipa Media behält sich das Recht vor, Content nachträglich zu löschen, wenn dieser innerhalb der letzten 365 Tage nicht gekauft oder angesehen wurde oder aus rechtlichen oder ethischen Gründen entfernt werden muss.

Umgang mit Content nach Vertragsbeendigung und technische EinschränkungenNach Beendigung des Vertragsverhältnisses wird Content, der innerhalb der letzten 365 Tage nicht verkauft oder angesehen wurde, unwiderruflich gelöscht. Der Partner wird vorab über die geplante Löschung informiert. Bereits verkaufter Content bleibt für die jeweiligen Käufer weiterhin streambar, sofern keine technischen Einschränkungen, wie infrastrukturelle oder kapazitätsbedingte Begrenzungen, eine weitere Bereitstellung unmöglich machen.

Einhaltung der Schutz- und UrheberrechteDie gewerblichen Schutz- und Urheberrechte Dritter sind zu jeder Zeit zu beachten. Dies umfasst insbesondere die öffentliche Wiedergabe von urheberrechtlich geschützter Musik, Filmaufnahmen und Bildern. Die Nutzung oder Verbreitung von Inhalten, für die keine ausreichende Berechtigung zur öffentlichen Aufführung vorliegt, stellt einen Verstoß gegen diese Nutzungsrichtlinien dar. In solchen Fällen können Schadensersatzforderungen durch die Rechteinhaber entstehen, die an den verantwortlichen Nutzer weitergeleitet werden. 
 * 
 */