<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

$sql = '';
// ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_master = trim((string)($_GET['category_master'] ?? ''));

if (!empty($get_category_master)) {
    $get_category_master = preg_replace ( '/[^a-z]/i', '', $get_category_master);
    
    if ($get_category_master == 'porn' OR $get_category_master == 'fetish') {
        $sql .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($get_category_master)."')";
        #$sql .= "`actor_categories` LIKE '%".p4c_escape_string($get_category_master)."%'";
    }    
}

// age ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_age = trim((string)($_GET['age'] ?? ''));

if (!empty($get_category_age)) {
    $get_category_age = preg_replace ( '/[^\|A-Za-z0-9-_]/i', '', $get_category_age);
    $category_age_ary = explode('|', $get_category_age);
}

if (isset($category_age_ary)) {
   
    // Nur wenn weniger als alle 4 Alterskategorien ausgewählt wurden, nutze die Suche 
    // Wenn alle 4 Ausgewählt sind, ignorriere die Suche -> somit wird alles gesucht
    
    if (count($category_age_ary) < 4) {
    
        $sql_age = '';
        foreach($category_age_ary as $value) {
            $sql_age .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($value)."') OR ";
            #$sql_age .= "`actor_categories` LIKE '%".p4c_escape_string($value)."%' OR ";
        }

        if (!empty($sql)) {
            $sql = $sql.' AND ('.substr($sql_age, 0, -4).')';
        } else {
            $sql = '('.substr($sql_age, 0, -4).')';
        }
    }
}

// body ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_body = trim((string)($_GET['body'] ?? ''));

if (!empty($get_category_body)) {
    $get_category_body = preg_replace ( '/[^\|A-Za-z0-9-_]/i', '', $get_category_body);
    $category_body_ary = explode('|', $get_category_body);
}

if (isset($category_body_ary)) {
   
    // Nur wenn weniger als alle 4 Körpereigenschaften ausgewählt wurden, nutze die Suche 
    // Wenn alle 4 Ausgewählt sind, ignorriere die Suche -> somit wird alles gesucht
    
    if (count($category_body_ary) < 4) {

        $sql_body = '';
        foreach($category_body_ary as $value) {
            $sql_body .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($value)."') OR ";
            #$sql_body .= "`actor_categories` LIKE '%".p4c_escape_string($value)."%' OR ";
        }

        if (!empty($sql)) {
            $sql = $sql.' AND ('.substr($sql_body, 0, -4).')';
        } else {
            $sql = '('.substr($sql_body, 0, -4).')';
        }
    }
}

// gender ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_gender = trim((string)($_GET['gender'] ?? ''));

if (!empty($get_category_gender)) {
    $get_category_gender = preg_replace ( '/[^\|A-Za-z0-9-_]/i', '', $get_category_gender);
    $category_gender_ary = explode('|', $get_category_gender);
}

if (isset($category_gender_ary)) {
   
    $sql_gender = '';
    foreach($category_gender_ary as $value) {
        if ($value == 'woman') {
            $sql_gender .= "MATCH (`actor_categories`) AGAINST ('woman') OR MATCH (`actor_categories`) AGAINST ('woman_devote') OR MATCH (`actor_categories`) AGAINST ('woman_dominant') OR ";
            
        } else if ($value == 'man') {
            $sql_gender .= "MATCH (`actor_categories`) AGAINST ('man') OR MATCH (`actor_categories`) AGAINST ('man_devote') OR MATCH (`actor_categories`) AGAINST ('man_dominant') OR ";
            
        } else {
            $sql_gender .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($value)."') OR ";
        }
    }
    
    if (empty($sql)) {
        $sql = '('.substr($sql_gender, 0, -4).')';
    } else {
        $sql = $sql.' AND ('.substr($sql_gender, 0, -4).')';
    }

    #$sql_not_woman = '';
    /*
    $sql_not_woman = '';
    if (!in_array('woman', $category_gender_ary) AND !in_array('woman_devote', $category_gender_ary) AND !in_array('woman_dominat', $category_gender_ary)) {
        $sql_not_woman .= " AND (`actor_categories` NOT LIKE '%woman%' AND `actor_categories` NOT LIKE '%woman_devote%' AND `actor_categories` NOT LIKE '%woman_dominant%')";
    }
    
    if (!empty($sql)) {
        $sql = $sql.' AND ('.substr($sql_gender, 0, -4).')'.$sql_not_woman;
    } else {
        $sql = '('.substr($sql_gender, 0, -4).')'.$sql_not_woman;
    }
     */
}

// hair color ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_hair_color = trim((string)($_GET['hair_color'] ?? ''));

if (!empty($get_category_hair_color)) {
    $get_category_hair_color = preg_replace ( '/[^\|A-Za-z0-9-_]/i', '', $get_category_hair_color);
    $category_hair_color_ary = explode('|', $get_category_hair_color);
}

if (isset($category_hair_color_ary)) {
   
    $sql_hair_color = '';
    foreach($category_hair_color_ary as $value) {
        $sql_hair_color .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($value)."') OR ";
        #$sql_hair_color .= "`actor_categories` LIKE '%".p4c_escape_string($value)."%' OR ";
    }
    
    if (!empty($sql)) {
        $sql = $sql.' AND ('.substr($sql_hair_color, 0, -4).')';
    } else {
        $sql = '('.substr($sql_hair_color, 0, -4).')';
    }
}

// skin color ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_skin_color = trim((string)($_GET['skin_color'] ?? ''));

if (!empty($get_category_skin_color)) {
    $get_category_skin_color = preg_replace ( '/[^\|A-Za-z0-9-_]/i', '', $get_category_skin_color);
    $category_skin_color_ary = explode('|', $get_category_skin_color);
}

if (isset($category_skin_color_ary)) {
   
    $sql_skin_color = '';
    foreach($category_skin_color_ary as $value) {
        $sql_skin_color .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($value)."') OR ";
        #$sql_skin_color .= "`actor_categories` LIKE '%".p4c_escape_string($value)."%' OR ";
    }
    
    if (!empty($sql)) {
        $sql = $sql.' AND ('.substr($sql_skin_color, 0, -4).')';
    } else {
        $sql = '('.substr($sql_skin_color, 0, -4).')';
    }
}

// weight ++++++++++++++++++++++++++++++++++++++++++++++
$get_category_weight = trim((string)($_GET['weight'] ?? ''));

if (!empty($get_category_weight)) {
    $get_category_weight = preg_replace ( '/[^\|A-Za-z0-9-_]/i', '', $get_category_weight);
    $category_weight_ary = explode('|', $get_category_weight);
}

if (isset($category_weight_ary)) {
    
    $sql_weight = '';
    foreach($category_weight_ary as $value) {
        $sql_weight .= "MATCH (`actor_categories`) AGAINST ('".p4c_escape_string($value)."') OR ";
        #$sql_weight .= "`actor_categories` LIKE '%".p4c_escape_string($value)."%' OR ";
    }

    if (!empty($sql)) {
        $sql = $sql.' AND ('.substr($sql_weight, 0, -4).')';
    } else {
        $sql = '('.substr($sql_weight, 0, -4).')';
    }
}

if (!empty($sql)) {
    $sql = 'WHERE '.$sql;
}

#echo "SELECT `actors`.*, `merchants`.`partner_id` FROM `actors` INNER JOIN `merchants` ON `merchants`.`id`=`actors`.`merchant_id` ".$sql." ORDER BY `username` ASC;";

#echo $sql;
#echo "SELECT * FROM `actors` ".$sql." ORDER BY `id` ASC;";
#
// Alle Profile abfragen
#$rs_actors = p4c_query("SELECT * FROM `actors`  ORDER BY `id` ASC;",__FILE__,__LINE__);
#$rs_actors = p4c_query("SELECT * FROM `actors` ".$sql."  ORDER BY `username` ASC;",__FILE__,__LINE__);
#$class_errorlog->log("SELECT `actors`.*, `merchants`.`partner_id` FROM `actors` INNER JOIN `merchants` ON `merchants`.`id`=`actors`.`merchant_id` ".$sql." ORDER BY `username` ASC;",__FILE__,__LINE__);

$rs_actors = p4c_query("SELECT `actors`.*, `merchants`.`partner_id` FROM `actors` INNER JOIN `merchants` ON `merchants`.`id`=`actors`.`merchant_id` ".$sql." ORDER BY `username` ASC;",__FILE__,__LINE__);

if(p4c_num_rows($rs_actors) == 0) {
    $api['error'] = 'no actors found';
    print_xml($api);
} else {
    while($actor_obj = p4c_fetch_object($rs_actors)) {
        
        // Wenn Darsteller auf jeder Seite erscheinen darf
        if ($actor_obj->visible_for_website == 'public') {
            $id = $actor_obj->id;

            $api['actor'][$id]['id']             = $id;
            $api['actor'][$id]['status']         = $actor_obj->status; // active // blocked // deleted
            $api['actor'][$id]['profile_picture_fs16'] = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk16;
            $api['actor'][$id]['profile_picture_fs18'] = API_URL.'/ProfilePicture/'.$actor_obj->profile_image_fsk18;
            $api['actor'][$id]['username']       = $actor_obj->username;
            $api['actor'][$id]['md5_checksum']   = $actor_obj->md5_checksum;
            $api['actor'][$id]['pay4coins_partner_id'] = $actor_obj->partner_id;
        }
    }
}

