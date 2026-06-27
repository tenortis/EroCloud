<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");


class ReCaptcha {
    public function is_human($g_recaptcha_response, $secretkey) {
        $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secretkey.'&response='.$g_recaptcha_response.'&remoteip='.getUserIP());
        $response = json_decode($response, true);
        if ($response['success'] === true) {
            return true;
        }
    }
}


?>