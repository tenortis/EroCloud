<?php
 
if (!defined('SAFE_INC')) {
    die ("Access denied!");
}

$rs_actors = p4c_query("SELECT * FROM `actors` WHERE `merchant_id`='".abs($_SESSION['merchant_id'])."' AND `status`!='deleted' ORDER BY `username` ASC;",__FILE__,__LINE__);
$count_actors = p4c_num_rows($rs_actors);

$site .= '
<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {
        

    })
   
// ]]>
</script>

<div id="site_actors">
    <h1 class="h4">Profile verwalten</h1>
    ';

    if ($count_actors == 1) {
        $actor_obj = p4c_fetch_object($rs_actors);
        header('Location: '.MCP_URL.'/Actor/'.$actor_obj->id);
        exit;
    } else if ($count_actors > 1) {
        $site .= '<ul id="actors">';
        
        while($actor_obj = p4c_fetch_object($rs_actors)) {
            
            $avatar = MCP_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16;
            
            if ($actor_obj->status != 'deleted') {
                $site .= '
                <li>
                    <a href="'.MCP_URL.'/Actor/'.$actor_obj->id.'">
                        <div class="avatar">
                            <img src="'.$avatar.'" alt="" />
                        </div>
                        <div class="username">'.$actor_obj->username.'</div>
                    </a>
                </li>';                
            }
        }
        $site .= '</ul>';
    } else if ($count_actors == 0) {
        $site .= '<a href="'.MCP_URL.'/New-Actor">Bitte erstelle jetzt dein Darsteller-Profil.</a>';
    
        
    } else if ($count_actors < $merchant->minimum_number_actor_profiles()) {
        $site .= '<h3><a href="'.MCP_URL.'/New-Actor">Ein weiteres Profil anlegen.</a></h3>';
    }
    $site .= '
</div>
';


?>
