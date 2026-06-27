<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

$rs_actors = p4c_query("SELECT `actors`.*, `merchants`.`partner_id` FROM `actors` INNER JOIN `merchants` ON `merchants`.`id`=`actors`.`merchant_id` WHERE AES_DECRYPT(`api_key`, '".AES_KEY."') = '".p4c_escape_string($api_key)."' ORDER BY `username` ASC;",__FILE__,__LINE__);

if(p4c_num_rows($rs_actors) == 0) {
    $api['error'] = 'no actors found';
    print_xml($api);
} else {
    while($actor_obj = p4c_fetch_object($rs_actors)) {
        $id = $actor_obj->id;
        
        $api['actor'][$id]['id']             = $id;
        $api['actor'][$id]['status']         = $actor_obj->status; // active // blocked // deleted
        $api['actor'][$id]['is_displayed_as']= $actor_obj->is_displayed_as;
        $api['actor'][$id]['profile_picture_fs16'] = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16;
        $api['actor'][$id]['profile_picture_fs18'] = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk18;
        $api['actor'][$id]['username']       = $actor_obj->username;
        $api['actor'][$id]['md5_checksum']   = $actor_obj->md5_checksum;
        $api['actor'][$id]['pay4coins_partner_id'] = $actor_obj->partner_id;
    }
}

