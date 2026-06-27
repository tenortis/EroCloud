<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}

#Beispiel: https://api.erocloud.net/index.php?api_name=get_actor_categories&api_key=MRT9NGSS9UACCP8E2EKGAMCISUPVBGGN

// Alle Darsteller-Kategorien abfragen
$rs_actor_categories = p4c_query("SELECT * FROM `actor_categories` ORDER BY `category_group` ASC;",__FILE__,__LINE__);
while($cat_obj = p4c_fetch_object($rs_actor_categories)) {
    $group = $cat_obj->category_group;
    $name = $cat_obj->name_id;

    $api['category'][$group][$name]['id']             = $cat_obj->name_id;
    $api['category'][$group][$name]['group']          = $cat_obj->category_group;
    $api['category'][$group][$name]['de_name_value']  = $cat_obj->de_name_value;
    $api['category'][$group][$name]['de_name_text']   = $cat_obj->de_name_text;
}
