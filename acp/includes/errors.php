<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

$error_log = trim(file_get_contents(SOURCEDIR.'/log/.errorlog'));

$site .='
<div style="width:1590px; margin-top:20px;">
    <div class="ui-widget-header" style="padding:10px; border-bottom:none;">PHP-Error-Log</div>
    <div class="ui-widget-content error_log" style="font-family:Monaco; padding:10px; white-space: pre; overflow-wrap: normal; overflow:auto; min-height:150px; max-height:800px;">'.$error_log.'</div>
</div>
';



?>