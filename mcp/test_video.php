
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Streaming</title>
    <link href="https://vjs.zencdn.net/6.6.3/video-js.css" rel="stylesheet">
    <script src="https://vjs.zencdn.net/6.6.3/video.js"></script>
    <script src="https://erocloud.net/Messenger/js/videojs-contrib-hls.js"></script>
</head>
<body>
<video id="player" class="video-js vjs-default-skin" height="360" width="640" controls preload="none">
    <source src="https://stream.me-on.de/hls/JLak2WiPmY.m3u8" type="application/x-mpegURL" />
</video>
<script>
    var player = videojs('#player');
    player.play();
</script>
</body>
</html>
