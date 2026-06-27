<?php

if (!defined('SAFE_INC')) {
    die ("Access not allowed");
}


$api['status'] = 'ok';
print_xml($api);