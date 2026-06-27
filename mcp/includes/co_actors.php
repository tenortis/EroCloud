<?php
 
if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

$rs_actors = p4c_query("SELECT * FROM `co_actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' ORDER BY `nickname_not_public`, `id` ASC;",__FILE__,__LINE__);
$count_actors = p4c_num_rows($rs_actors);

$site .= '
<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        

    })
   
// ]]>
</script>

<div id="site_actors">
    <h1 class="h4">Meine Drehpartner</h1>
    
    <div style="margin-bottom:20px; max-width:1000px;">
        Um sicherzustellen, dass die Inhalte, die du hochl&auml;dst, authentisch sind und den Richtlinien unserer Plattform entsprechen, ben&ouml;tigen wir einen sogenannten "ID-Shot" der Mitwirkenden in deinen Filmen.
        Ein ID-Shot ist ein Identifikationsfoto der Personen, die in deinen Filmen auftreten. Dies dient dazu, ihre Identit&auml;t zu best&auml;tigen und sicherzustellen, dass alle Beteiligten einverstanden sind, in deinen Filmen gezeigt zu werden.
        <br />
        <br />
        Bitte lade daher f&uuml;r jede Person, die in deinen Filmen mitwirkt, einen ID-Shot hoch. Dies muss ein Portr&auml;tfoto sein, auf dem die Person ein Ausweisbild neben ihr Gesicht h&auml;t und die Person und das Ausweisdokument klar erkennbar ist.
        Wir respektieren die Privatsph&auml;re unserer Benutzer und verwenden die hochgeladenen ID-Shots ausschlie&szlig;lich zum Zweck der Identit&auml;tsbest&auml;tigung.
    </div>



    ';

    $site .= '<h3><a href="'.MCP_URL.'/New-CoActor">Drehpartner hinzuf&uuml;gen</a></h3>';

    if ($count_actors > 1) {
        $site .= '<ul id="actors">';
        
        while($actor_obj = p4c_fetch_object($rs_actors)) {
            
            $avatar = MCP_URL.'/ProfilePicture/'.$actor_obj->id_shot_img1;
            
            $site .= '
            <li>
                <a href="'.MCP_URL.'/Actor/'.$actor_obj->id.'">
                    <div class="avatar">
                        <img src="'.$avatar.'" alt="" />
                    </div>
                    <div class="username">'.$actor_obj->nickname.'</div>
                </a>
            </li>';                
        }
        $site .= '</ul>';
    }
    $site .= '
</div>
';


?>
