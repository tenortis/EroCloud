<?php

/**
 * Liste mit allen Film-Kategorien
 */

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

header('Content-Type: text/html; charset=utf-8');

$rs_categories = p4c_query("SELECT * FROM `movie_categories` ORDER BY `de_name_text` DESC;",__FILE__,__LINE__);

$api['number_of_categories'] = p4c_num_rows($rs_categories);
$api['category'] = array();
while($categories_obj = p4c_fetch_object($rs_categories)) {
    $api['category'][] = array(
        'id'            => $categories_obj->name_id,
        'group'         => $categories_obj->category_group,
        'de_name'       => $categories_obj->de_name_value,
        'de_desc'       => $categories_obj->de_name_text
    );
}
