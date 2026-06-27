<?php

/**
 * points_group  | points_for       | points   | description
 * ------------------------------------------------------------
 * messeger      | till8hours       | 3        |...
 * messeger      | till16hours      | 2        |...
 * messeger      | till24hours      | 1        |...
 * messeger      | contat_new_user  | 5        |...
 * movie_upload  | new_movie        | 15       |...
 * photo_album_upload  | new_photo_album  | 10 |...
 * webcam        | one_minute_online| 5        |...
 * 
 */

/*
 * Beschreibeung
 * ====================
 *
 * $points_cls = new PointsSystem;
 * $points_cls->group = 'messenger';
 * $points_cls->points_for = 'till8hours';
 * $points_cls->actor_id = '257';
 * $points_cls->date = '2020-09-10';

// Gibt Anzahl der zu vergeben Punkte für den gewählten Bonus aus
echo $points_cls->get_points();

// Aktualisiert die Gesamtpunkte des Punkte-Empfängers für die gewählte Gruppe
$points_cls->set_points($points);
 
*/

class PointsSystem {
    
    var $group = '';
    var $points_for = '';
    var $actor_id = 0;
    var $date = '';
     
    /**
     * Punkte ausgeben. Wieviele Punkte gibt es für die gewählte Art?
     */
    function get_points() {
        $rs_points_system = p4c_query("SELECT * FROM `points_system` WHERE `points_group`='".p4c_escape_string($this->group)."' AND `points_for`='".p4c_escape_string($this->points_for)."' LIMIT 1;",__FILE__,__LINE__);
        if (p4c_num_rows($rs_points_system) == 0) {
            return false;
        } else {
            $points = p4c_fetch_object($rs_points_system);
            return $points->points;
        }
    }   
    
    /**
     * Vergebene Punkte loggen
     */
    function set_points($points=0) {
        if ($this->actor_id > 0) {
            
            if ($this->date == '') {
                $date = date("Y-m-d");
            } else {
                $date = $this->date;
            }
            
            $rs_points_system = p4c_query("SELECT * FROM `points_scoring` WHERE `actor_id`='".abs($this->actor_id)."' AND `date`='".$date."' LIMIT 1;",__FILE__,__LINE__);
            if (p4c_num_rows($rs_points_system) == 0) {
                
                p4c_query("INSERT INTO `points_scoring` SET
                    `actor_id`='".abs($this->actor_id)."',
                    `". p4c_escape_string($this->group)."` = '".abs($points)."',
                    `date` = '".$date."'
                ",__FILE__,__LINE__);
                
            } else {
                p4c_query("UPDATE `points_scoring` SET
                    `".p4c_escape_string($this->group)."` = `".p4c_escape_string($this->group)."` + ".abs($points)."
                WHERE 
                    `actor_id` = '".abs($this->actor_id)."' AND
                    `date` = '".$date."'
                LIMIT 1;",__FILE__,__LINE__);
            }
        }
    }    
}

/*
$points_cls = new PointsSystem;
$points_cls->group = 'messenger';
$points_cls->points_for = 'till8hours';
$points_cls->actor_id = '257';
$points_cls->date = '2020-09-10';

if ($points_cls->get_points() != false) {
    $points = $points_cls->get_points();
    $points_cls->set_points($points);
};
*/
