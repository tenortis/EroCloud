#!/usr/bin/php
<?php
 
define('SAFE_INC', 1);

/**
 * convert_status = 0  => muss noch konvertiert werden
 * convert_status = 1  => wird aktuell konvertiert
 * convert_status = 2  => konvertierung abgeschlossen
 * convert_status = 3  => konvertierung abgebrochen / fehlerhaft
 */

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

$db_settings_ary['videothek_ffmpeg_on_off'] = 1;

// Wenn ffmpeg im ACP ausgeschalten ist, nicht ausf?hren.
if (isset($db_settings_ary['videothek_ffmpeg_on_off']) AND $db_settings_ary['videothek_ffmpeg_on_off'] == 1) {

    $ffmpeg_pfad = FFMPEG_PATH.'/';
    
    // Anzahl der gleichzeitigen Konvertierungen
    $anzahl_convert = FFMPEG_SIMULTANEOUS_CONV;
    
    #UPDATE `movies_convert_log` SET `datetime_start`='0000-00-00 00:00:00', `datetime_end`='0000-00-00 00:00:00' WHERE `art`='mobile' AND `mime`='mp4' AND `datetime_start` < '2014-12-29 16:00:00'
    
    $rs_check_movies = p4c_query("SELECT * FROM `movies` WHERE `convert_status`='1' AND `status`!='deleted';", __FILE__, __LINE__);
    
    $count_convert = p4c_num_rows($rs_check_movies);
    
    // Wenn bereits Filme konvertieren
    if ($count_convert > 0) {
        while($movie_obj = p4c_fetch_object($rs_check_movies)) {
            // Wenn die Konbvertierung l?nger dauert als 4 Stunden, dann abbrechen
            if ($movie_obj->convert_endtime == '0000-00-00 00:00:00' AND date("Y-m-d H:i:s", strtotime("-4 hours")) > $movie_obj->convert_starttime) {
                p4c_query("UPDATE `movies` SET `convert_status`='3', `convert_starttime`='0000-00-00 00:00:00' WHERE `id`='".abs($movie_obj->id)."' LIMIT 1;", __FILE__, __LINE__);
            }
        }
    }
    
    // Wenn kein Film konvertiert oder aktuell weniger Filme konveriteren als unter $anzahl_convert angegeben
    if ($count_convert < $anzahl_convert) {
    
        // Neue Filme
        $rs_movies = p4c_query("SELECT * FROM `movies` WHERE `convert_status`='0' AND `filename`!='' AND `status`!='deleted' ORDER BY `id` ASC LIMIT 1", __FILE__, __LINE__);
        
        if (p4c_num_rows($rs_movies) > 0) {
            while($movie_ary = p4c_fetch_object($rs_movies)) {
                $file_path = MOVIES_PATH.'/'.$movie_ary->storage_location."/".$movie_ary->merchant_id."/".$movie_ary->id."/";
                
                $org_file = $file_path.$movie_ary->filename;

                // Wenn Datei existiert
                if (file_exists($org_file)) {
                    $file_name = strtolower(substr($movie_ary->filename,0, -3)); // mit . (Punkt) am Ende7
                    $file_name_neu = $file_name.'mp4';
                
                    p4c_query("UPDATE `movies` SET `convert_status`='1', `convert_starttime`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
        
                    // Logge Start der Konvertierung
                    /*
                    p4c_query("INSERT INTO `movies_convert_log` (`movie_id`, `art`, `mime`, `datetime_start`)
                                    SELECT `d`.* FROM (SELECT '".abs($movie_ary->id)."' AS `movie_id`, 'desktop' AS `art`, 'mp4' AS `mime`, '".date("Y-m-d H:i:s")."' AS `datetime_start`) AS `d`
                                    WHERE '' IN (SELECT COUNT(*) FROM `movies_convert_log` WHERE `movie_id` = '".abs($movie_ary->id)."' AND `art`='desktop' AND `mime`='mp4');", __FILE__, __LINE__);
                    */
                    
                    $explode_resolution = explode("x",$movie_ary->resolution);
                    if (isset($explode_resolution[0])) {
                        $width = $explode_resolution[0];
                    } else {
                        $width = 1024;
                    }                    

                    // Erstelle neue MP4 Datei
                    exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 8000k -vf "scale='.$width.':trunc(ow/a/2)*2" -vcodec libx264 -preset veryslow -g 30  '.$file_path.'neu_'.$file_name_neu, $output, $return_desktop_mp4);
                    #exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 8000k -vf "scale='.$width.':-1" -vcodec libx264 -preset veryslow -g 30  '.$file_path.'neu_'.$file_name_neu, $output, $return_desktop_mp4);
                    #exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 1500k -vf "scale='.$width.':-1" -vcodec libx264 -preset slow -g 30  '.$file_path.'neu_'.$file_name_neu, $output, $return_desktop_mp4);
                    #exec($ffmpeg_pfad.'ffmpeg -i '.$org_file.' -vcodec libx264 -preset slow -g 30 -b 1500k '.$file_path.'neu_'.$file_name_neu, $output, $return_desktop_mp4);

                    // Wenn exec fertig ist 
                    if (!$return_desktop_mp4) {
                                
                        // Prüfe ob neuer Film existiert
                        // Wenn nicht, Setzte Status in DB zurück
                        if (!file_exists($file_path."neu_".$file_name_neu)) {
                            p4c_query("UPDATE `movies` SET `convert_status`='0' WHERE `id`='".abs($movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);

                        // Prüfe ob neuer Film existiert    
                        } else {
        
                            /** qt-faststart auf neue Desktop MP4 Datei anwenden */
                            exec($ffmpeg_pfad."qt-faststart ".$file_path."neu_".$file_name_neu." ".$file_path."neu_qt_".$file_name_neu, $output, $return_desktop_qt);
                            
                            // Wenn exec fertig ist 
                            if (!$return_desktop_qt) {

                                // Logge ende der Konvertierung
                                #p4c_query("UPDATE `movies_convert_log` SET `datetime_end`= '".date("Y-m-d H:i:s")."' WHERE `movie_id`='".abs($movie_ary->id)."' AND `art`='desktop' AND `mime`='mp4' LIMIT 1;", __FILE__, __LINE__);
                
                                // Aktualisiere Dateiname in Datenbank
                                p4c_query("UPDATE `movies` SET `filename`='".p4c_escape_string($file_name_neu)."' WHERE `id`='".abs($movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                                
                                // Lösche (temporäre) neu erstellte MP4 weil eben durch qt-faststart, mit neuem index anggelgt 
                                unlink($file_path."neu_".$file_name_neu);
                  
                                // Zur Sicherheit prüfen ob neue Datei existiert, wenn ja löschen (wird gleich neu erstellt)  
                                if (file_exists($file_path.$file_name_neu)) {unlink($file_path.$file_name_neu);}
                                
                                // Erstelle fertige Desktop MP4 => Kopie der neue erstellten MP4+qt
                                if(copy($file_path."neu_qt_".$file_name_neu, $file_path.$file_name_neu)) {
                
                                    // Lösche temporäre qt-datei, weil neue MP4 soeben durch kopieren erstellt wurde
                                    unlink($file_path."neu_qt_".$file_name_neu);
                                    
                                    $out = $file_path.'m_'.$file_name;
                        
                                    // MP4 640:[prop]
                                    // Logge Start der Konvertierung
                                    /*
                                    p4c_query("INSERT INTO `movies_convert_log` (`movie_id`, `art`, `mime`, `datetime_start`)
                                        SELECT `d`.* FROM (SELECT '".abs($movie_ary->id)."' AS `movie_id`, 'mobile' AS `art`, 'mp4' AS `mime`, '".date("Y-m-d H:i:s")."' AS `datetime_start`) AS `d`
                                        WHERE '' IN (SELECT COUNT(*) FROM `movies_convert_log` WHERE `movie_id` = '".abs($movie_ary->id)."' AND `art`='mobile' AND `mime`='mp4');", __FILE__, __LINE__);
                                    */

                                    exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libx264 -b:a 196k -g 30 '.$out.'mp4', $output, $return_mobil_mp4);
                                    #exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libx264 -g 30 '.$out.'mp4', $output, $return_mobil_mp4);
                                    
                                    // Wenn exec fertig ist 
                                    if (!$return_mobil_mp4) {                              
                                    
                                        if (file_exists($out.'mp4')) {
                                            /** qt-faststart auf neue Mobile MP4 Datei anwenden */
                                            $return = exec($ffmpeg_pfad.'qt-faststart '.$out.'mp4 '.$out.'mp4_mobile');
                                            // Erstelle fertige Desktop MP4 => Kopie der neue erstellten MP4+qt  
                                            if (copy($out.'mp4_mobile', $out.'mp4')) {
                                                // Logge ende der Konvertierung
                                                #p4c_query("UPDATE `movies_convert_log` SET `datetime_end`= '".date("Y-m-d H:i:s")."' WHERE `movie_id`='".abs($movie_ary->id)."' AND `art`='mobile' AND `mime`='mp4' LIMIT 1;", __FILE__, __LINE__);
                                                
                                                // Lösche temporäre qt-datei
                                                unlink($out.'mp4_mobile');
                                            }
                                        }
                
                                        //WebM 640:[prop]
                                        // Logge Start der Konvertierung
                                        /*
                                        p4c_query("INSERT INTO `movies_convert_log` (`movie_id`, `art`, `mime`, `datetime_start`)
                                            SELECT `d`.* FROM (SELECT '".abs($movie_ary->id)."' AS `movie_id`, 'mobile' AS `art`, 'webm' AS `mime`, '".date("Y-m-d H:i:s")."' AS `datetime_start`) AS `d`
                                            WHERE '' IN (SELECT COUNT(*) FROM `movies_convert_log` WHERE `movie_id` = '".abs($movie_ary->id)."' AND `art`='mobile' AND `mime`='webm');", __FILE__, __LINE__);
                                        */  
                                        
                                        exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libvpx -acodec libvorbis -f webm -g 30 '.$out.'webm', $output, $return_mobil_webm);
                                        
                                        # funktionierte vorher
                                        # exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libvpx -acodec libvorbis -b:a 196k -f webm -g 30 '.$out.'webm', $output, $return_mobil_webm);
                                        
                                        #exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libvpx -acodec libvorbis -f webm -g 30 '.$out.'webm', $output, $return_mobil_webm);
                                        
                                        // Wenn exec fertig ist 
                                        if (!$return_mobil_webm) {
                                        
                                            if (file_exists($out.'webm')) {
                                                // Logge ende der Konvertierung
                                                #p4c_query("UPDATE `movies_convert_log` SET `datetime_end`= '".date("Y-m-d H:i:s")."' WHERE `movie_id`='".abs($movie_ary->id)."' AND `art`='mobile' AND `mime`='webm' LIMIT 1;", __FILE__, __LINE__);
                                            }
                                    
                                            //ogv 640:[prop]
                                            // Logge Start der Konvertierung
                                            /*
                                            p4c_query("INSERT INTO `movies_convert_log` (`movie_id`, `art`, `mime`, `datetime_start`)
                                                SELECT `d`.* FROM (SELECT '".abs($movie_ary->id)."' AS `movie_id`, 'mobile' AS `art`, 'ogg' AS `mime`, '".date("Y-m-d H:i:s")."' AS `datetime_start`) AS `d`
                                                WHERE '' IN (SELECT COUNT(*) FROM `movies_convert_log` WHERE `movie_id` = '".abs($movie_ary->id)."' AND `art`='mobile' AND `mime`='ogg');", __FILE__, __LINE__);
                                            */  

                                            exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libtheora -acodec libvorbis -g 30 '.$out.'ogv', $output, $return_mobil_ogv);
                                            # funktionierte vorher
                                            # exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libtheora -acodec libvorbis -b:a 196k -g 30 '.$out.'ogv', $output, $return_mobil_ogv);
                                            #exec($ffmpeg_pfad.'ffmpeg -y -i '.$org_file.' -b 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libtheora -acodec libvorbis -g 30 '.$out.'ogv', $output, $return_mobil_ogv);
                    
                                            // Wenn exec fertig ist 
                                            if (!$return_mobil_ogv) {
            
                                                if (file_exists($out.'ogv')) {
                                                    // Logge ende der Konvertierung
                                                    #p4c_query("UPDATE `movies_convert_log` SET `datetime_end`= '".date("Y-m-d H:i:s")."' WHERE `movie_id`='".abs($movie_ary->id)."' AND `art`='mobile' AND `mime`='ogg' LIMIT 1;", __FILE__, __LINE__);
                                                }
                                            }
                                        }
                                    }
            
                                    // Wenn Desktop-MP4 erstellt werden konnte und alle Formate für Mobil exisztieren  
                                    if (file_exists($out.'mp4') AND file_exists($out.'webm') AND file_exists($out.'ogv')) {
                                    
                                        // setzte neuen Status für den Film                          
                                        p4c_query("UPDATE `movies` SET `convert_status`='2', `convert_endtime`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs($movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                                        
                                        $file_name = 'thumb_'.strtolower(substr($movie_ary->filename,0, -4)); // mit . (Punkt) am Ende
                                        $out = $file_path.$file_name;
                                        $all_x_seconds = ceil($movie_ary->playtime_seconds/10);
                                        exec($ffmpeg_pfad.'ffmpeg -y -i '.$file_path.$file_name_neu.' -ss '.gmdate("H:i:s", $all_x_seconds).'.000 -vf fps=1/'.$all_x_seconds.',scale="1024:-1" -vframes 10 '.$out.'_%d.jpg', $output, $return_thumb);
                                        #exec($ffmpeg_pfad.'ffmpeg -y -i '.$file_path.$file_name_neu.' -ss '.gmdate("H:i:s", $all_x_seconds).'.000 -vf fps=1/'.$all_x_seconds.' -vframes 10 '.$out.'%d.jpg', $output, $return_thumb);
                                        
                                        print_r($output);
                                        print_r($return_thumb);
                                        
                                        /*
                                        // Wenn Film fertig Konvertiert wurde
                                        if (isset($db_settings_ary['videothek_email_bei_upload_on_off']) AND $db_settings_ary['videothek_email_bei_upload_on_off'] == 1) {
                                            p4c_query("INSERT INTO `email_bei_upload` SET `movie_id` = '".abs($movie_ary->id)."', `amateur_id` = '".abs($movie_ary->merchant_id)."';", __FILE__, __LINE__);
                                        }
                                        */
                                    
                                        // Lösche Original hochgeladenen File
                                        if ($file_name_neu != $movie_ary->filename) {
                                            unlink($org_file);
                                        }
                                    }                        
                                }
        
                            }  
                            
                        }
                    } // exec fertig
                } else {
                    p4c_query("UPDATE `movies` SET `convert_status`='3' WHERE `id`='".abs($movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                }
            }
        }
    }
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>