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

// Wenn ffmpeg im ACP ausgeschalten ist, nicht ausführen.
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
            // Wenn die Konvertierung länger dauert als 4 Stunden, dann abbrechen
            if ($movie_obj->convert_endtime == '0000-00-00 00:00:00' AND date("Y-m-d H:i:s", strtotime("-4 hours")) > $movie_obj->convert_starttime) {
                p4c_query("UPDATE `movies` SET `convert_status`='3', `convert_starttime`='0000-00-00 00:00:00' WHERE `id`='".abs((int)$movie_obj->id)."' LIMIT 1;", __FILE__, __LINE__);
            }
        }
    }
    
    // Wenn kein Film konvertiert oder aktuell weniger Filme konvertieren als unter $anzahl_convert angegeben
    if ($count_convert < $anzahl_convert) {
    
        // Neue Filme
        $rs_movies = p4c_query("SELECT * FROM `movies` WHERE `convert_status`='0' AND `filename`!='' AND `status`!='deleted' ORDER BY `id` ASC LIMIT 1", __FILE__, __LINE__);
        
        if (p4c_num_rows($rs_movies) > 0) {
            while($movie_ary = p4c_fetch_object($rs_movies)) {
                $file_path = MOVIES_PATH.'/'.$movie_ary->storage_location."/".$movie_ary->merchant_id."/".$movie_ary->id."/";
                
                $org_file = $file_path.$movie_ary->filename;

                // Wenn Datei existiert
                if (file_exists($org_file)) {
                    $base_filename = pathinfo($movie_ary->filename, PATHINFO_FILENAME);
                    $file_name_neu = strtolower($base_filename) . '.mp4';
                
                    p4c_query("UPDATE `movies` SET `convert_status`='1', `convert_starttime`='".date("Y-m-d H:i:s")."' WHERE `id`='".abs((int)$movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
        
                    // Logge Start der Konvertierung
                    /*
                    p4c_query("INSERT INTO `movies_convert_log` (`movie_id`, `art`, `mime`, `datetime_start`)
                                    SELECT `d`.* FROM (SELECT '".abs($movie_ary->id)."' AS `movie_id`, 'desktop' AS `art`, 'mp4' AS `mime`, '".date("Y-m-d H:i:s")."' AS `datetime_start`) AS `d`
                                    WHERE '' IN (SELECT COUNT(*) FROM `movies_convert_log` WHERE `movie_id` = '".abs($movie_ary->id)."' AND `art`='desktop' AND `mime`='mp4');", __FILE__, __LINE__);
                    */
                    
                    // A) Auflösung der Quelldatei ermitteln (mit Fallback 1920x1080 bei fehlenden Metadaten)
                    $pre_width  = 0;
                    $pre_height = 0;

                    include_once(MCP_DIR . '/includes/getid3/getid3.php');
                    $getID3 = new getID3;
                    $pre_fileinfo = @$getID3->analyze($org_file);
                    if (isset($pre_fileinfo['video']['resolution_x']) && isset($pre_fileinfo['video']['resolution_y'])) {
                        $pre_width  = (int)round($pre_fileinfo['video']['resolution_x']);
                        $pre_height = (int)round($pre_fileinfo['video']['resolution_y']);
                    }

                    if ($pre_width <= 0 || $pre_height <= 0) {
                        $explode_resolution = explode("x", $movie_ary->resolution);
                        if (isset($explode_resolution[0]) && isset($explode_resolution[1])) {
                            $pre_width  = (int)$explode_resolution[0];
                            $pre_height = (int)$explode_resolution[1];
                        }
                    }

                    if ($pre_width <= 0 || $pre_height <= 0) {
                        $pre_width  = 1920;
                        $pre_height = 1080;
                    }

                    // Hochkant-Videos (9:16) wie Querformat (16:9) einstufen
                    $max_dim = max($pre_width, $pre_height);
                    $min_dim = min($pre_width, $pre_height);

                    $crf = 23;
                    if ($max_dim >= 2560 || $min_dim >= 1440) {
                        // 2K / 4K Ultra-HD
                        $maxrate = '6500k';
                        $bufsize = '13000k';
                        $preset  = 'medium';
                    } elseif ($max_dim >= 1600 || $min_dim >= 900) {
                        // Full-HD 1080p (zugleich Fallback bei fehlenden Metadaten)
                        $maxrate = '3200k';
                        $bufsize = '6400k';
                        $preset  = 'slow';
                    } elseif ($max_dim >= 1000 || $min_dim >= 650) {
                        // HD 720p
                        $maxrate = '2000k';
                        $bufsize = '4000k';
                        $preset  = 'slow';
                    } else {
                        // SD (< 720p)
                        $maxrate = '1200k';
                        $bufsize = '2400k';
                        $preset  = 'slow';
                    }

                    $temp_encoded_file = $file_path . 'neu_' . $file_name_neu;
                    $temp_qt_file      = $file_path . 'neu_qt_' . $file_name_neu;
                    $final_mp4_file    = $file_path . $file_name_neu;

                    // B) Fehlertoleranter FFmpeg-Befehl (inkl. -strict -2 für ältere FFmpeg-Pakete)
                    $ffmpeg_cmd = $ffmpeg_pfad . 'ffmpeg -y -fflags +genpts+igndts -i ' . escapeshellarg($org_file)
                        . ' -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2"'
                        . ' -vcodec libx264 -pix_fmt yuv420p'
                        . ' -crf ' . $crf . ' -maxrate ' . $maxrate . ' -bufsize ' . $bufsize
                        . ' -preset ' . $preset . ' -g 60'
                        . ' -acodec aac -strict -2 -b:a 128k -ac 2 -ar 44100'
                        . ' -max_muxing_queue_size 4096 -movflags +faststart '
                        . escapeshellarg($temp_encoded_file);

                    exec($ffmpeg_cmd, $output, $return_desktop_mp4);

                    // Wenn exec erfolgreich und Ausgabedatei vorhanden (> 0 Bytes)
                    if (!$return_desktop_mp4 && file_exists($temp_encoded_file) && filesize($temp_encoded_file) > 0) {
                        
                        // C) Umgang mit qt-faststart (falls auf dem System vorhanden)
                        exec($ffmpeg_pfad . 'qt-faststart ' . escapeshellarg($temp_encoded_file) . ' ' . escapeshellarg($temp_qt_file), $output, $return_desktop_qt);
                        
                        // Da -movflags +faststart das moov-Atom bereits an den Anfang setzt, legt qt-faststart oft keine Zieldatei an.
                        if (file_exists($temp_qt_file) && filesize($temp_qt_file) > 0) {
                            $source_mp4 = $temp_qt_file;
                        } else {
                            $source_mp4 = $temp_encoded_file;
                        }

                        // Erstelle fertige Desktop-MP4 durch Kopieren der validierten Temp-Datei
                        if (copy($source_mp4, $final_mp4_file)) {
                            
                            // Temporäre Dateien erst NACH erfolgreichem Kopieren löschen
                            if (file_exists($temp_encoded_file)) {
                                @unlink($temp_encoded_file);
                            }
                            if (file_exists($temp_qt_file)) {
                                @unlink($temp_qt_file);
                            }

                            // Wenn finale Desktop-MP4 existiert und gültig ist
                            if (file_exists($final_mp4_file) && filesize($final_mp4_file) > 0) {
                                
                                // Auflösung & Spieldauer frisch aus der fertigen .mp4-Datei für die Datenbank auslesen
                                $getID3_post = new getID3;
                                $post_fileinfo = @$getID3_post->analyze($final_mp4_file);

                                $post_width  = (isset($post_fileinfo['video']['resolution_x']) && round($post_fileinfo['video']['resolution_x']) > 0)
                                    ? (int)round($post_fileinfo['video']['resolution_x'])
                                    : $pre_width;
                                $post_height = (isset($post_fileinfo['video']['resolution_y']) && round($post_fileinfo['video']['resolution_y']) > 0)
                                    ? (int)round($post_fileinfo['video']['resolution_y'])
                                    : $pre_height;
                                $post_resolution = $post_width . 'x' . $post_height;

                                $post_playtime_sec = (isset($post_fileinfo['playtime_seconds']) && round($post_fileinfo['playtime_seconds']) > 0)
                                    ? (int)round($post_fileinfo['playtime_seconds'])
                                    : (int)$movie_ary->playtime_seconds;
                                $post_playtime_str = (isset($post_fileinfo['playtime_string']) && $post_fileinfo['playtime_string'] !== '')
                                    ? $post_fileinfo['playtime_string']
                                    : $movie_ary->playtime_string;

                                // Falls die Spieldauer beim Upload wegen fehlender Metadaten 0 war, Filmpreis (amount_own) nachberechnen
                                $amount_own_sql = '';
                                if ((int)$movie_ary->playtime_seconds <= 0 && $post_playtime_sec > 0 && (float)$movie_ary->amount_second > 0) {
                                    $recalc_amount_own = round($post_playtime_sec * (float)$movie_ary->amount_second);
                                    $amount_own_sql = ", `amount_own`='" . abs((int)$recalc_amount_own) . "'";
                                }

                                // Aktualisiere Dateiname, frisch ausgelesene Metadaten und Konvertierungs-Status in Datenbank
                                p4c_query("UPDATE `movies` SET 
                                    `filename`='" . p4c_escape_string($file_name_neu) . "',
                                    `resolution`='" . p4c_escape_string($post_resolution) . "',
                                    `playtime_seconds`='" . p4c_escape_string($post_playtime_sec) . "',
                                    `playtime_string`='" . p4c_escape_string($post_playtime_str) . "',
                                    `convert_status`='2',
                                    `convert_endtime`='" . date("Y-m-d H:i:s") . "'"
                                    . $amount_own_sql . "
                                    WHERE `id`='" . abs((int)$movie_ary->id) . "' LIMIT 1;", __FILE__, __LINE__);
                                
                                // 10 Vorschaubilder (Thumbnails) erzeugen – mit Schutz gegen Division durch 0
                                $thumb_prefix = 'thumb_' . strtolower($base_filename);
                                $out = $file_path . $thumb_prefix;
                                $effective_playtime = ($post_playtime_sec > 0) ? $post_playtime_sec : max(10, (int)$movie_ary->playtime_seconds);
                                $all_x_seconds = max(1, (int)ceil($effective_playtime / 10));
                                exec($ffmpeg_pfad . 'ffmpeg -y -i ' . escapeshellarg($final_mp4_file) . ' -ss ' . gmdate("H:i:s", $all_x_seconds) . '.000 -vf "fps=1/' . $all_x_seconds . ',scale=min(1024\,iw):-2" -vframes 10 ' . escapeshellarg($out . '_%d.jpg'), $output, $return_thumb);
                                
                                print_r($output);
                                print_r($return_thumb);
                                
                                /*
                                // Wenn Film fertig Konvertiert wurde
                                if (isset($db_settings_ary['videothek_email_bei_upload_on_off']) AND $db_settings_ary['videothek_email_bei_upload_on_off'] == 1) {
                                    p4c_query("INSERT INTO `email_bei_upload` SET `movie_id` = '".abs($movie_ary->id)."', `amateur_id` = '".abs($movie_ary->merchant_id)."';", __FILE__, __LINE__);
                                }
                                */
                            
                                // Lösche Original hochgeladenes File, falls es eine andere Dateiendung hatte
                                if ($file_name_neu != $movie_ary->filename && file_exists($org_file)) {
                                    @unlink($org_file);
                                }
                            } else {
                                p4c_query("UPDATE `movies` SET `convert_status`='3' WHERE `id`='".abs((int)$movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                            }
                        } else {
                            // Kopieren fehlgeschlagen -> Temp-Dateien aufräumen und Fehlerstatus setzen
                            if (file_exists($temp_encoded_file)) {
                                @unlink($temp_encoded_file);
                            }
                            if (file_exists($temp_qt_file)) {
                                @unlink($temp_qt_file);
                            }
                            p4c_query("UPDATE `movies` SET `convert_status`='3' WHERE `id`='".abs((int)$movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                        }
                    } else {
                        // FFmpeg fehlgeschlagen oder leere Datei -> Temp-Datei aufräumen und Status auf 3 (fehlerhaft), damit Queue nicht 4h blockiert
                        if (file_exists($temp_encoded_file)) {
                            @unlink($temp_encoded_file);
                        }
                        p4c_query("UPDATE `movies` SET `convert_status`='3' WHERE `id`='".abs((int)$movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                    }
                } else {
                    p4c_query("UPDATE `movies` SET `convert_status`='3' WHERE `id`='".abs((int)$movie_ary->id)."' LIMIT 1;", __FILE__, __LINE__);
                }
            }
        }
    }
}

p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());

?>
