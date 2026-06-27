
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>Medien-Player mit HTML5</title>
</head>
<body>


<script src="https://cdn.dashjs.org/latest/dash.mediaplayer.min.js"></script>
    
<video id="videoPlayer" controls></video>

<script>
    (function(){
        var stream = 'https://stream.me-on.de/dash/Kizyg6xs9W.mpd'
        var player = dashjs.MediaPlayer().create();
        player.initialize(document.querySelector("#videoPlayer"), stream, true);
        player.getDebug().setLogToBrowserConsole(false);
    })();
</script>

</body>
</html>