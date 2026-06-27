<?php


if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

if (!isset($_GET['step'])) {
    $site .= '
    <div class="ui-widget-header" style="margin-top:20px; padding:10px; border-bottom:none;">Deine eigene EROTIK-, DOMINA-Hotline</div>
    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
        Als Erotikcallerin kannst bequem von zu Hause aus mit deiner Flirtline oder Erotikline hei&szlig;e Gespr&auml;che f&uuml;hren und Geld verdienen. Oder du bist eine Domina,
        Herrin oder Lady und erziehst deine Kunden bzw. "Sklaven" live am Telefon. Mit den 09005-Rufnummern von EroCall ist dies ohne Probleme m&ouml;glich.
    </div>

    <div class="ui-widget-header" style="padding:10px; border-bottom:none;">Mit einer 0900-Rufnummer von EroCall erh&auml;st du eine beispiellos hohe Verg&uuml;tung.</div>
    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
        <i class="material-symbols-outlined">check</i> Du bekommst 1,00 EUR brutto (inkl. USt.) ausgezahlt.<br />
        <i class="material-symbols-outlined">check</i> Deine Auszahlung erfolgt bereits ab den ersten Cent zum Anfang des Folgemonats.
    </div>

    <form action="'.MCP_URL.'/Hotline" method="post">
        <div class="ui-widget-header" style="padding:10px; border-bottom:none;">Jetzt deine eigene 09005-Rufnummer einrichten und beantragen</div>
        <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
            <div style="margin-bottom:15px;">
                <div style="margin-bottom:5px;"><b>Deine deutsche Festnetz-Zielrufnummer:</b></div>
                <span style="font-size:20px;">0049(0)</span> <input type="text" name="erocall_number_de_dest_landline" value="'.$erocall_number_de_dest_landline.'" /><br />
                <div style="margin-top:5px;">
                    Gib hier deine Festnetzrufnummer OHNE f&uuml;hrende 0 (Null) an. Auf diese Nummer leiten wir deine Anrufer weiterleiten. Deine Nummer bleibt geheim und ist nirgends &ouml;ffentlich sichtbar.
                </div>
            </div>';
            

                    
            $rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `username` ASC;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_actors) > 1) {
                $site .= '
                <div style="margin-bottom:15px;">
                    <div style="margin-bottom:5px;"><b>Welchem Darsteller soll die 09005-Rufnummer zugeordnet werden?:</b></div>
                    <select style="font-size:20px;" name="actor_id">';
                    while($actor_obj = p4c_fetch_object($rs_actors)) {
                        $site .= '<option value="'.$actor_obj->id.'">'.$actor_obj->username.'</option>';
                    }
                    $site .= '
                    </select>
                </div>';
            } else {
                $actor_obj = p4c_fetch_object($rs_actors);
                $site .= '<input type="hidden" name="actor_id" value="'.$actor_obj->id.'" />';
            }
            
            $site .= '
            <div class="info_box" style="margin-bottom:15px;">
                <div style="display: table-cell; width: 15px; vertical-align: top; padding-top: 2px;">
                    <input '.$my_data_checked.' type="checkbox" name="my_data" id="my_data" />
                </div>
                <div style="display:table-cell; padding-left:5px; text-align:justify">
                    <label for="my_data">
                        Die Bereitstellung und Auszahlung der 09005-Rufnummer erfolgt &uuml;ber <a href="https://call.erocms.net" target="_blank">EroCall.net</a> bzw. dem Unternehmen "adult net applications UG".
                        Ich best&auml;tige ich hiermit, dass meine Adress- und Bankdaten nur f&uuml;r die Bereitstellung und Abrechnung einer eigenen
                        09005-Rufnummer an adult net applications UG weitergegeben werden d&uuml;rfen.            
                    </label>
                </div>
            </div>
        </div>

        <div style="text-align:right;">
            <input class="button" type="submit" name="submit_erocall" value="Weiter und Daten pr&uuml;fen" />
        </div>
    </form>';
}

else if (isset($_GET['step']) AND $_GET['step'] == 2) {
    if (!isset($_SESSION['erocall_number_de_dest_landline'])) {
        header('Location: '.MCP_URL.'/Hotline');
        exit;
    }

    $site .= '
    <div class="ui-widget-header" style="margin-top:20px; padding:10px; border-bottom:none;">Ist diese Nummer korrekt?</div>
    <div class="ui-widget-content" style="padding:10px; margin-bottom:20px;">
        Pr&uuml;fe noch einmal, ob deine eingegebene, deutsche Festnetz-Zielrufnummer korrekt ist.
        <div style="margin:25px 0 15px 20px; font-size:25px;">0'.$_SESSION['erocall_number_de_dest_landline'].'</div>
    </div>

    <div style="display:table; width:100%;">
        <div style="text-align:left; display:table-cell; width:50%">
            <a class="button" href="'.MCP_URL.'/Hotline">Z&uuml;r&uuml;ck</a>
        </div>

        <div style="text-align:right; display:table-cell; width:50%">
            <form action="'.MCP_URL.'/Hotline?step=2" method="post">
                <input class="button" type="submit" name="submit_number" value="Rufnummer beantragen" />
            </form>
        </div>
    </div>';
}
