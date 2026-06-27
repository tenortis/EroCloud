#!/usr/bin/php
<?php
 
define('SAFE_INC', 1);

/**
 * jede Minute /usr/bin/php /var/www/web1/htdocs/api/usertracking/cronjob.php >/dev/null 2>&1
 */


// SELECT * FROM `user_tracking_users` GROUP BY `user_browserfingerprint` having count(*) > 1


$sourcedir = dirname(dirname(dirname(__FILE__)));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

function check_website_access() {
    
    $rs_user_tracking_website_access = p4c_query("SELECT * FROM `user_tracking_website_access` WHERE `checked_datetime`='0000-00-00 00:00:00' ORDER BY `log_datetime` ASC LIMIT 10;",__FILE__,__LINE__);
    if (p4c_num_rows($rs_user_tracking_website_access) == 0) {
        p4c_errorlog(error_get_last());
        p4c_close(DB_HOST);
        exit;
    }
    
    while($access_obj = p4c_fetch_object($rs_user_tracking_website_access)) {

        $rs_website = p4c_query("SELECT * FROM `user_tracking_sites` WHERE `url_id`='". p4c_escape_string($access_obj->url_id)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_website) == 1) {

            $website_obj = p4c_fetch_object($rs_website);

            // Wenn die URL das letzte mal vor mehr als einen Monat gecrawlt wurde, jetzt crawlen
            if ($website_obj->last_check_datetime < date("Y-m-d H:i:s", strtotime("-1 month"))) {
                crawl_url($website_obj, $access_obj);

            } else {
                keywords_to_user($access_obj, $website_obj->keywords);
            }
        }
    }        
}


function crawl_url($website_obj, $access_obj) {
    global $user_agents, $class_errorlog;

    $id = $website_obj->id;
    $url = trim($website_obj->url);
    
    $gesamt = (count($user_agents) - 1);

    if ($url == '' OR $id == 0) {
        p4c_errorlog(error_get_last());
        p4c_close(DB_HOST);
        exit;
    }

    #$url = filter_var($url, FILTER_SANITIZE_URL);
    
    $options = array(
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => true,
        CURLOPT_USERAGENT => $user_agents[rand(0, $gesamt)],
        CURLOPT_URL => $url,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_NOSIGNAL => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_RETURNTRANSFER => true, // Antwort aus cURL-Aufruf zurückgeben
        CURLOPT_FOLLOWLOCATION => true, // Weiterleitungen automatisch folgen
        CURLOPT_MAXREDIRS => 3 // Maximale Anzahl von Weiterleitungen festlegen
    );

    // cURL-Session starten
    $ch = curl_init();

    // Optionen setzen
    curl_setopt_array($ch, $options);

    if (false === $result=curl_exec($ch)) {
        $class_errorlog->log('Crawler-Error: '.curl_error($ch)."\nURL: ".$url,__FILE__,__LINE__);
    }
    
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if(curl_errno($ch)){   
        $class_errorlog->log('Crawler-Error: '.curl_error($ch)."\nHTTP-Code: ".$httpcode."\nURL: ".$url,__FILE__,__LINE__);
        
        if ($httpcode == 0) {
            // Wenn Seite nicht mehr Aktiv
            p4c_query("DELETE FROM `user_tracking_website_access` WHERE `url_id`='".p4c_escape_string($website_obj->url_id)."';", __FILE__,__LINE__);
            p4c_query("DELETE FROM `user_tracking_sites` WHERE `id`='".$website_obj->id."';",__FILE__,__LINE__);                                
        }
    }
    
    if($httpcode==200) {
       
        $redirectedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        
        #$class_errorlog->log('Debug: '.$url,__FILE__,__LINE__);
        
        // Wenn keine Weiterleitung zu einer anderen URL
        #if ($url == $redirectedUrl) {
            libxml_use_internal_errors(true);
            
            $dom = new DOMDocument();
            #$dom->recover = true;
            #$dom->strictErrorChecking = false;

            $dom->loadHTML($result);
            $xpath = new DOMXpath($dom);
            
            // Find p-Tags
            $all_divs = $xpath->query("//p");

            $search_text = '';
            foreach ($all_divs as $divs) {
                $text = trim ($divs->textContent);
                if (strlen($text) > 100) {
                    $search_text .= $text;
                }
            } 

            // Find Meta-Description
            $meta_desc = $xpath->query('/html/head/meta[@name="description"]/@content');
            if ($meta_desc->length > 0) {
                foreach ($meta_desc as $decripton) {
                    $text = trim ($decripton->value);
                    if (strlen($text) > 25) {
                        $search_text .= $text;
                    }
                }
            }

            // Find Meta-Title
            $meta_title = $xpath->query('/html/head/meta[@name="title"]/@content');
            if ($meta_title->length > 0) {
                foreach ($meta_title as $title) {
                    $text = trim ($title->value);
                    if (strlen($text) > 25) {
                        $search_text .= $text;
                    }
                }
            }
            
            find_keywords($website_obj, $access_obj, $search_text);
            
            p4c_query("UPDATE `user_tracking_sites` SET
                `status_code`='".abs($httpcode)."',
                `last_check_datetime`='".date("Y-m-d H:i:s")."'
            WHERE `id`='".abs($id)."' LIMIT 1;",__FILE__,__LINE__);
            
        #}
    }
    
    curl_close($ch);
}

function find_keywords($website_obj, $access_obj, $search_text) {
    $id = $website_obj->id;
    
    if (trim($search_text) != '' AND $id > 0) {
        $keywords_ary = array();        
        
        $rs_keywords = p4c_query("SELECT * FROM `movie_categories`;",__FILE__,__LINE__);
        while($keyword_obj = p4c_fetch_object($rs_keywords)) {
            
            $search_keyword_string = strtolower($search_text);
            if (strpos($search_keyword_string, strtolower($keyword_obj->name_id)) !== FALSE OR strpos($search_keyword_string, strtolower($keyword_obj->de_name_value)) !== FALSE) {
                $keywords_ary[] = $keyword_obj->name_id;
            }

            if (trim($keyword_obj->more_search_words) != '') {      
                $more_search_words_ary = explode(',',strtolower($keyword_obj->more_search_words));
                foreach ($more_search_words_ary as $value) {
                    if (strpos($search_keyword_string, strtolower(trim($value))) !== FALSE AND !in_array($keyword_obj->name_id, $keywords_ary)) {
                        $keywords_ary[] = $keyword_obj->name_id;
                    }
                }
            }
            
        }
        
        // Wenn Keywords auf Website gefunden wurden
        $keywords = '';
        if (count($keywords_ary) > 0) {
            $keywords = implode(',', $keywords_ary);
        }

        p4c_query("UPDATE `user_tracking_sites` SET
            `keywords`='". p4c_escape_string($keywords)."',
            `status_code` = '200',
            `last_check_datetime`='".date("Y-m-d H:i:s")."'
        WHERE `id`='". p4c_escape_string($id)."' LIMIT 1;",__FILE__,__LINE__);

        keywords_to_user($access_obj, $keywords);
        
    }    
}

function keywords_to_user($access_obj, $keywords='') {
    $rs_user = p4c_query("SELECT * FROM `user_tracking_users` WHERE `id`='".abs($access_obj->user_id)."' LIMIT 1;",__FILE__,__LINE__);
    
    if (p4c_num_rows($rs_user) > 0) {
        p4c_query("UPDATE `user_tracking_website_access` SET
            `checked_datetime` = '".date("Y-m-d H:i:s")."'
        WHERE `id`='".abs($access_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
        
        if ($keywords != '') {
            $user_obj = p4c_fetch_object($rs_user);

            $old_user_interests = $user_obj->interests;
            if ($old_user_interests != '') {
                $interests_ary = json_decode($old_user_interests, true);
            } else {
                $interests_ary = array();
            }

            $new_interests_ary = array();
            $keyword_ary = explode(',', $keywords);
            foreach($keyword_ary as $keyword) {
                if (array_key_exists($keyword, $interests_ary)) {
                    $new_interests_ary[$keyword] = $interests_ary[$keyword]++ ;
                } else {
                    $new_interests_ary[$keyword] = 1;
                }
            }

            
            $new_interests_ary_merge = array_merge($new_interests_ary,$interests_ary);
            #$new_interests_ary_merge = array_unique($new_interests_ary_merge);
            $interests_json = json_encode($new_interests_ary_merge);
            
            p4c_query("UPDATE `user_tracking_users` SET
                `interests`='". p4c_escape_string($interests_json)."',
                `update_time`='".date("Y-m-d H:i:s")."'
            WHERE `id`='".abs($user_obj->id)."' LIMIT 1;",__FILE__,__LINE__);
        }
    }
}

check_website_access();

p4c_errorlog(error_get_last());
p4c_close(DB_HOST);
