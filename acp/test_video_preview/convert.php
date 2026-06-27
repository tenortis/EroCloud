<?php



#ffmpeg -i original.mp4 -ss 00:00:30 -t 00:00:01.5 -vf scale=-1:500 -an -c:v libx264 -y cut1.mp4
#ffmpeg -i original.mp4 -ss 00:03:45 -t 00:00:01.5 -vf scale=-1:500 -an -c:v libx264 -y cut2.mp4
#ffmpeg -i original.mp4 -ss 00:07:00 -t 00:00:01.5 -vf scale=-1:500 -an -c:v libx264 -y cut3.mp4

define('FFMPEG_PATH', '/usr/bin');
define('FFMPEG_SIMULTANEOUS_CONV', 2);
$ffmpeg_pfad = FFMPEG_PATH.'/';
$sourcedir = dirname(__FILE__);
$list_file = $sourcedir.'/parts_list.txt';

$split_to_parts = 6;
$part_duration = 1.3;
$file = 'org.mp4';
$new_movie = $sourcedir.'/new.mp4';


function get_video_duration_in_sec($file) {
    $output = shell_exec("ffmpeg -i " . escapeshellarg($file) . " 2>&1");
    if (preg_match("/Duration: (.*?), start:/", $output, $matches)) {
        list($hours, $minutes, $seconds) = explode(':', $matches[1]);
        
        if (preg_match("/Stream.*Video:.*(\d+)x(\d+)/", $output, $matches)) {
            echo $output;

            $width = $matches[1];
            $height = $matches[2];
        }
        return array(
            'seconds' => floor(($hours * 3600) + ($minutes * 60) + $seconds),
            'duration' => "$hours:$minutes:".floor($seconds),
            'width' => $width,
            'height' => $height
        );
    }
    return false;
}

function split_video_time($time_in_sec, $split_to_pieces, $part_duration) {
    $interval = $time_in_sec / $split_to_pieces;
    $cut_times = array();
    for ($i = 2; $i <= $split_to_pieces-1; $i++) {
        $cut_times[] = floor($interval * $i);
    }
    return $cut_times;
}

function convert_to_time_format($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}


if (file_exists($file)) {

    
    include_once($sourcedir.'/getid3/getid3.php');
    $getID3 = new getID3;
    $fileinfo = $getID3->analyze($file);
    
    #getid3_lib::CopyTagsToComments($fileinfo);
    $duration = convert_to_time_format($fileinfo['playtime_seconds']);
    $seconds = round($fileinfo['playtime_seconds']);
    
    if (isset($fileinfo['video']['resolution_x']) AND isset($fileinfo['video']['resolution_y'])) {
        $width = round($fileinfo['video']['resolution_x']);
        $height = round($fileinfo['video']['resolution_y']);
    }
    
    if ($seconds > ($split_to_parts * $part_duration)) {
        $cut_times = split_video_time($seconds, $split_to_parts, $part_duration);
        
        // Aus originalfilm einzelnen Sequenzen auschneiden
        $parts_ary = array();
        foreach ($cut_times as $key => $part) {
            
            $new_file = "part".$key.".mp4";
            $parts_ary[$key] = $new_file;
            
            #$command = $ffmpeg_pfad."ffmpeg -i ".$file." -ss ".convert_to_time_format($part)." -t 00:00:0".$part_duration." -vf \"scale=-1:500\" -c:v vp9 -speed 1 -an -y ".$new_file."  &> ffmpeg.log";
            $command = $ffmpeg_pfad."ffmpeg -i ".$file." -ss ".convert_to_time_format($part)." -t 00:00:0".$part_duration." -c:v vp9 -speed 9 -an -y ".$new_file."  &> ffmpeg.log";
            
            echo $command.'<br />';

            $output = array();
            $return_var = 0;

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                echo "Error: ffmpeg command failed.<br />";
                // Fallback code here
            } else {
                echo "Command executed successfully.<br />";
            }
        }
        
        // Neue Film aus den einzelnen Sequenzen erstellen
        // Erstelle die Listendatei
        $list_contents = '';
        foreach ($parts_ary as $part) {
            $list_contents .= "file $part\n";
        }        
        file_put_contents($list_file, $list_contents);

        if ($width > 500) {
            echo __LINE__;
            $command = "ffmpeg -f concat -i $list_file -c:v vp9 -vf \"scale=-1:500\" -y $new_movie";
        } else {
            echo __LINE__;
            $command = "ffmpeg -f concat -i $list_file -c:v vp9 -y $new_movie";
        }
        
        echo $command.'<br />';
        exec($command, $output, $return_var);
        
        // Lösche die Listendatei
        #unlink($list_file);
        
        // Lösche einzelne Squenzen
        foreach ($parts_ary as $key => $part) {
            #unlink($sourcedir.'/part'.$key.'.mp4');
        }  
        
        
    }
}