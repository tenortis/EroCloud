<?php

/**
 * Daten zum Film X
 */

if (!defined('SAFE_INC')) {
    die("Access not allowed!");
}
         
$get_gelesen = abs(filter_input(INPUT_GET, 'gelesen', FILTER_SANITIZE_NUMBER_INT));
if ($get_gelesen > 2) {$get_gelesen = 1;}

$get_von = filter_input(INPUT_GET, 'von', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
if ($get_von !== 'actor' AND $get_von !== 'member') {
    $api['error'] = "'von' not allowed";
    $class_errorlog->log($api['error']."\n".print_r(filter_input_array(INPUT_GET), true),__FILE__,__LINE__);
    print_xml($api);
}

$get_von_id = abs(filter_input(INPUT_GET, 'von_id', FILTER_SANITIZE_NUMBER_INT));
$get_an_id  = abs(filter_input(INPUT_GET, 'an_id', FILTER_SANITIZE_NUMBER_INT));

/*
if ($get_von_id === 0 OR $get_an_id === 0) {
    $api['error'] = "'von_id' OR 'an_id' false";
    $class_errorlog->log($api['error']."\n".print_r($_GET, true),__FILE__,__LINE__);
    print_xml($api);    
}
*/

if(p4c_query("UPDATE `chat_messages_history` SET `gelesen` = '".abs($get_gelesen)."' WHERE 
    `p4c_shop_id`   = '".abs($p4c_shop_id)."' AND
    `von`           = '".p4c_escape_string($get_von)."' AND
    `erocms_von_id` = '".abs($get_von_id)."' AND
    `erocms_an_id`  = '".abs($get_an_id)."';
",__FILE__,__LINE__)) {
    
    p4c_query("UPDATE `chat_messages` SET `gelesen` = '".abs($get_gelesen)."' WHERE 
        `p4c_shop_id`   = '".abs($p4c_shop_id)."' AND
        `von`           = '".p4c_escape_string($get_von)."' AND
        `erocms_von_id` = '".abs($get_von_id)."' AND
        `erocms_an_id`  = '".abs($get_an_id)."';
    ",__FILE__,__LINE__);
    
    $api['response'] = 'ok';       
    print_xml($api);
}


