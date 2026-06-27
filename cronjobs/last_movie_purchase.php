#!/usr/bin/php
<?php
 

// Diese Datei ist dafür zuständig, das Datum des letzten Kaufes eines Filmes herauszufinden und entsprechend beim Film zu speichern. 


define('SAFE_INC', 1);

$sourcedir = dirname(dirname(__FILE__));
include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

/*
gesamte Filme: 6596
gekaufte Filme: 5189
    
Filme die innerhalb der letzten 2 Jahre angeschaut wurden: 2537
    
Filme die das letzte mal vor 2 Jahren (05.02.2023) angeschaut wurden: 2008
Filme die das letzte mal vor 3 Jahren (05.02.2022) angeschaut wurden: 1408
Filme die das letzte mal vor 4 Jahren (05.02.2021) angeschaut wurden: 906
Filme die das letzte mal vor 5 Jahren (05.02.2020) angeschaut wurden: 491 
*/    



/*
SELECT ma.*
FROM movies_access ma
JOIN (
    -- Subquery, um den neuesten Kauf pro movie_id vor dem Stichtag zu finden
    SELECT movie_id, MAX(buy_timestamp) AS latest_buy
    FROM movies_access
    WHERE buy_timestamp < '2020-02-05 12:55:00'
    GROUP BY movie_id
) sub ON ma.movie_id = sub.movie_id AND ma.buy_timestamp = sub.latest_buy
WHERE ma.movie_id NOT IN (
    -- Subquery, um alle Filme zu finden, die nach dem Stichtag gekauft oder angesehen wurden
    SELECT DISTINCT movie_id 
    FROM movies_access
    WHERE buy_timestamp >= '2020-02-05 12:55:00'
       OR access_token_datetime >= '2020-02-05 12:55:00'
)
ORDER BY ma.buy_timestamp DESC
 */

$rs_movies_access = p4c_query("SELECT * FROM `movies_access` GROUP BY `movie_id`");


p4c_close(DB_HOST);

// PHP Fehlermeldung loggen
p4c_errorlog(error_get_last());
