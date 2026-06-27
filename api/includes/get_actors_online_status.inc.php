<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

$temp_file = 'get_actors_online_status.tmp';

if (filemtime($temp_file) < time()-10) {

    // Alle Profile abfragen
    $rs_actors = p4c_query("SELECT `actors`.`id`, `erocall_number_de_status`, `stream_id`, `streamserver_url`, `lastonline`, `messenger_takes_a_break`, `actor_cams`.`datetime` AS `last_cam_time` FROM `actors` LEFT JOIN `actor_cams` ON `actors`.`id`=`actor_cams`.`actor_id` WHERE
        `messenger_online_status`='1' AND
        `lastonline` >= '".(time()-60)."'
    ORDER BY `actors`.`id` ASC;",__FILE__,__LINE__);

    if(p4c_num_rows($rs_actors) == 0) {
        $api['error'] = 'no actors online';
        print_xml($api);
    } else {
        $i=0;
        while($actors_obj = p4c_fetch_object($rs_actors)) {

            $webcam_stream_id   = '';
            $webcam_stream_url  = '';

            if ($actors_obj->last_cam_time >= date("Y-m-d H:i:s", strtotime("-60 seconds")) AND $actors_obj->stream_id !== NULL) {
                $webcam_stream_id   = $actors_obj->stream_id;
                $webcam_stream_url  = $actors_obj->streamserver_url;
            }

            $phone_status = 'offline';
            if ($actors_obj->erocall_number_de_status == 1) {
                $phone_status = 'online';

                $rs_check_call = p4c_query("SELECT * FROM `erocall` WHERE `actor_id`='".abs($actors_obj->id)."' ORDER BY `erocall`.`id` DESC LIMIT 1;",__FILE__,__LINE__);
                if (p4c_num_rows($rs_check_call) == 1) {
                    $call_obj = p4c_fetch_object($rs_check_call);
                    if ($call_obj->date_time >= date("Y-m-d H:i:s", strtotime("-60 minutes")) AND $call_obj->event == 'connect') {
                        $phone_status = 'occupied';
                    }
                }
            }

            $api['actors_online'][$i]['actor_id']       = $actors_obj->id;
            $api['actors_online'][$i]['lastonline']     = $actors_obj->lastonline;
            $api['actors_online'][$i]['takes_a_break']  = $actors_obj->messenger_takes_a_break;
            $api['actors_online'][$i]['webcam_stream_id']   = $webcam_stream_id;
            $api['actors_online'][$i]['webcam_stream_url']  = $webcam_stream_url;
            $api['actors_online'][$i]['phone_status']   = $phone_status;
            $i++;

            #$class_errorlog->log('URL: https://'.$domain.'/erocloud_api/update_actor_member_infos.php?'.$query."\n".$data."\n".print_r($_POST,true),__FILE__,__LINE__);   
        }
    }
    
    file_put_contents($temp_file, serialize($api));
    
} else {
    $api = unserialize(file_get_contents($temp_file));
}

