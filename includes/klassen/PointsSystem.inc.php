<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

/**
 * points_group       | points_for        | points | description
 * -------------------------------------------------------------------
 * messenger          | till8hours        | 3      | ...
 * messenger          | till16hours       | 2      | ...
 * messenger          | till24hours       | 1      | ...
 * messenger          | contat_new_user   | 5      | ...
 * movie_upload       | new_movie         | 15     | ...
 * photo_album_upload | new_photo_album   | 10     | ...
 * webcam             | one_minute_online | 5      | ...
 */

/*
 * Beschreibung
 * ====================
 *
 * $points_cls = new PointsSystem;
 * $points_cls->group = 'messenger';
 * $points_cls->points_for = 'till8hours';
 * $points_cls->actor_id = '257';
 * $points_cls->date = '2020-09-10';
 *
 * // Gibt Anzahl der zu vergebenen Punkte für den gewählten Bonus aus
 * echo $points_cls->get_points();
 *
 * // Aktualisiert die Gesamtpunkte des Punkte-Empfängers für die gewählte Gruppe
 * $points_cls->set_points($points);
 */

class PointsSystem {

    public $group = '';
    public $points_for = '';
    public $actor_id = 0;
    public $merchant_id = 0;
    public $date = '';

    /**
     * Punkte ausgeben. Wieviele Punkte gibt es für die gewählte Art?
     */
    public function get_points() {
        $rs_points_system = p4c_query("SELECT * FROM `points_system` WHERE `points_group`='" . p4c_escape_string((string)$this->group) . "' AND `points_for`='" . p4c_escape_string((string)$this->points_for) . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rs_points_system || p4c_num_rows($rs_points_system) == 0) {
            return false;
        } else {
            $points = p4c_fetch_object($rs_points_system);
            return $points->points;
        }
    }

    /**
     * Vergebene Punkte loggen
     */
    public function set_points($points = 0) {
        if (abs((int)$this->actor_id) > 0) {
            if ((string)$this->date === '') {
                $date = date("Y-m-d");
            } else {
                $date = (string)$this->date;
            }

            $actorId = abs((int)$this->actor_id);
            $groupCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$this->group);
            if ($groupCol === '') {
                return;
            }

            $rs_points_system = p4c_query("SELECT * FROM `points_scoring` WHERE `actor_id`='" . $actorId . "' AND `date`='" . p4c_escape_string($date) . "' LIMIT 1;", __FILE__, __LINE__);
            if (!$rs_points_system || p4c_num_rows($rs_points_system) == 0) {
                p4c_query("INSERT INTO `points_scoring` SET
                    `actor_id`='" . $actorId . "',
                    `" . p4c_escape_string($groupCol) . "` = '" . abs((int)$points) . "',
                    `date` = '" . p4c_escape_string($date) . "'
                ;", __FILE__, __LINE__);
            } else {
                p4c_query("UPDATE `points_scoring` SET
                    `" . p4c_escape_string($groupCol) . "` = `" . p4c_escape_string($groupCol) . "` + " . abs((int)$points) . "
                WHERE
                    `actor_id` = '" . $actorId . "' AND
                    `date` = '" . p4c_escape_string($date) . "'
                LIMIT 1;", __FILE__, __LINE__);
            }
        }
    }

    public function MerchantID($merchantId = 0) {
        $this->merchant_id = abs((int)$merchantId);
    }

    public function ActorID($actorId = 0) {
        $this->actor_id = abs((int)$actorId);
    }

    public function AddPoints(array $params = []) {
        if (!empty($params['group'])) {
            $this->group = (string)$params['group'];
        }
        if (!empty($params['points_for'])) {
            $this->points_for = (string)$params['points_for'];
        }
        if ((string)$this->date === '') {
            $this->date = date('Y-m-d');
        }
        $points = $this->get_points();
        if ($points !== false) {
            $this->set_points($points);
        }
    }
}
