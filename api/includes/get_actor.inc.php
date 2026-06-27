<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

// Wenn keine actor_id angegeben wurde
if(!isset($_GET['actor_id'])) {
    $api['error'] = 'actor not exists';
    print_xml($api);
}

$get_actor_id = abs(filter_input(INPUT_GET, 'actor_id', FILTER_SANITIZE_NUMBER_INT));

// Profil abfragen
$rs_actor = p4c_query("SELECT  `actors`.*, `merchants`.`partner_id` FROM `actors` INNER JOIN `merchants` ON `merchants`.`id`=`actors`.`merchant_id` WHERE `actors`.`id`='".abs($get_actor_id)."' LIMIT 1;",__FILE__,__LINE__);

if(p4c_num_rows($rs_actor) == '0') {
    $api['error'] = 'actor not exists';
    print_xml($api);
} else {
    $actor_obj = p4c_fetch_object($rs_actor);
    $id = $actor_obj->id;

    $api['actor']['id']             = $id;
    $api['actor']['status']         = $actor_obj->status; // active // blocked // deleted
    $api['actor']['is_displayed_as']= $actor_obj->is_displayed_as;
    $api['actor']['md5_checksum']   = $actor_obj->md5_checksum;
    $api['actor']['p4c_partner_id'] = $actor_obj->partner_id;
    
    if ($actor_obj->erocall_number_de_status == 1) {
        $api['actor']['phone']['de']['status']  = 'active';
        $api['actor']['phone']['de']['number']  = '09005 - '.$actor_obj->erocall_number_de.' - '.$actor_obj->erocall_number_de_ddi;
        $api['actor']['phone']['de']['rate']    = $actor_obj->erocall_number_de_rate;
    } else {
        $api['actor']['phone']['de']['status']  = 'inactive';
        $api['actor']['phone']['de']['number']  = '';
        $api['actor']['phone']['de']['rate']    = '';
    }
   
    $api['actor']['profile_picture_fs16'] = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16;
    $api['actor']['profile_picture_fs18'] = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk18;
    
    $api['actor']['pn_amount']      = $actor_obj->pn_amount;
    $api['actor']['pn_free_if_webcam'] = $actor_obj->pn_free_if_webcam;
    $api['actor']['cam_amount']     = $actor_obj->cam_amount;
    $api['actor']['usercam_if_amacam'] = $actor_obj->usercam_if_amacam;
    
    $api['actor']['username']       = $actor_obj->username;
    
    $api['actor']['check_gender']   = $actor_obj->check_gender;
    $api['actor']['gender']         = $actor_obj->gender;
    if ($api['actor']['check_gender'] === '0') {$api['actor']['gender'] = '';}
        
    $api['actor']['check_age']      = $actor_obj->check_age;
    $api['actor']['age']            = $actor_obj->age;
    if ($api['actor']['check_age'] === '0') {$api['actor']['age'] = '';}
    
    $api['actor']['check_star_sign']= $actor_obj->check_star_sign;
    $api['actor']['star_sign']      = $actor_obj->star_sign;
    if ($api['actor']['check_star_sign'] === '0') {$api['actor']['star_sign'] = '';}
    
    $api['actor']['check_body_height'] = $actor_obj->check_body_height;
    $api['actor']['body_height']    = $actor_obj->body_height;
    if ($api['actor']['check_body_height'] === '0') {$api['actor']['body_height'] = '';}
    
    $api['actor']['check_eye_color']= $actor_obj->check_eye_color;
    $api['actor']['eye_color']      = $actor_obj->eye_color;
    if ($api['actor']['check_eye_color'] === '0') {$api['actor']['eye_color'] = '';}
    
    $api['actor']['check_hair_color'] = $actor_obj->check_hair_color;
    $api['actor']['hair_color']     = $actor_obj->hair_color;
    if ($api['actor']['check_hair_color'] === '0') {$api['actor']['hair_color'] = '';}
    
    $api['actor']['check_body_weight'] = $actor_obj->check_body_weight;
    $api['actor']['body_weight']    = $actor_obj->body_weight;
    if ($api['actor']['check_body_weight'] === '0') {$api['actor']['body_weight'] = '';}
    
    $api['actor']['check_cup_size'] = $actor_obj->check_cup_size;
    $api['actor']['cup_size']       = $actor_obj->cup_size;
    if ($api['actor']['check_cup_size'] === '0') {$api['actor']['cup_size'] = '';}
        
    $api['actor']['check_shaven']   = $actor_obj->check_shaven;
    $api['actor']['shaven']         = $actor_obj->shaven;
    if ($api['actor']['check_shaven'] === '0') {$api['actor']['shaven'] = '';}
    
    $api['actor']['check_marital_status'] = $actor_obj->check_marital_status;
    $api['actor']['marital_status'] = $actor_obj->marital_status;
    if ($api['actor']['check_marital_status'] === '0') {$api['actor']['marital_status'] = '';}
    
    $api['actor']['check_sexual_orientation'] = $actor_obj->check_sexual_orientation;
    $api['actor']['sexual_orientation'] = $actor_obj->sexual_orientation;
    if ($api['actor']['check_sexual_orientation'] === '0') {$api['actor']['sexual_orientation'] = '';}
    
    $api['actor']['check_looking_for'] = $actor_obj->check_looking_for;
    $api['actor']['looking_for']    = $actor_obj->looking_for;
    if ($api['actor']['check_looking_for'] === '0') {$api['actor']['looking_for'] = '';}
    
    $api['actor']['check_interests']= $actor_obj->check_interests;
    $api['actor']['interests']      = $actor_obj->interests;
    if ($api['actor']['check_interests'] === '0') {$api['actor']['interests'] = '';}
    
    $api['actor']['check_sexual_preferences'] = $actor_obj->check_sexual_preferences;
    $api['actor']['sexual_preferences'] = $actor_obj->sexual_preferences;
    if ($api['actor']['check_sexual_preferences'] === '0') {$api['actor']['sexual_preferences'] = '';}
    
    $api['actor']['check_about_me'] = $actor_obj->check_about_me;
    $api['actor']['about_me']       = $actor_obj->about_me;    
    if ($api['actor']['check_about_me'] === '0') {$api['actor']['about_me'] = '';}
   
}