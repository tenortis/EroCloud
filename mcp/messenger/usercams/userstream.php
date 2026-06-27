<?php

define('SAFE_INC', 1);

$sourcedir = dirname(dirname(dirname(dirname(__FILE__))));

include_once($sourcedir."/config.inc.php");
include_once(MCP_DIR."/common.inc.php");

if (!isset($_SESSION['merchant_id'])) {
    die('Access not allowed!');
}

if (!isset($_GET['member_id'])) {
    echo 'Keine $_GET["member_id"] angegeben!';
    exit;
}

$sender_id = abs($_GET['member_id']);
$member_id = abs($_SESSION['merchant_id']);

$rs_member = p4c_query("SELECT * FROM `members` WHERE `id`='".abs($sender_id)."' LIMIT 1;",__FILE__,__LINE__);
if (p4c_num_rows($rs_member) == 0) {
    exit;
}

$member_obj = p4c_fetch_object($rs_member);

#$rs_amateur = p4c_query("SELECT `media_server` FROM `amateure` WHERE `id`='".(int)$_SESSION['sender_id']."' LIMIT 1;");
#$amateur_ary = p4c_fetch_object($rs_amateur);


$site = '<html> 
<head>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    
    <script>
        jQuery.noConflict();
    </script>

    <link rel="stylesheet" type="text/css" href="'.MCP_URL.'/Messenger/css/style.css?id=7" />

    <title>'.$member_obj->username.'</title>

    <style type="text/css">
        body {
            overflow-x:hidden;
            overflow-y:hidden;
        }
            
        #stream_loading {
            position: absolute;
            top: 40px;
            text-align: center;
            width: 100%;
        }
        
        #remoteVideo {
            width:100%;
            height:inherit;
        }
        
        #video_container {
            width:100%;
            height: -webkit-fill-available;
            height: -moz-available; 
            height: -webkit-fill-available;
            height: fill-available;
            overflow:hidden;
            position:relative;
        }
    </style>
</head>
<body>
    <div id="video_container">
        <video playsinline autoplay loop muted id="remoteVideo"></video>
        <div id="stream_loading">Lade Stream...</div>
    </div>
    <!--
    <button onclick="openRemoteCam()" id="show_webcam">Webcam anzeigen</button>
    <button onclick="closeVideoCall()" id="">Beenden</button>
    -->

    <script>
        var senderID = '.$sender_id.';
        var receiverID = '.$member_id.';
        const api_url = "'.API_URL.'";
        const mcp_url = "'.MCP_URL.'"; 
    </script>

    <script src="'.MCP_URL.'/Messenger/usercams/adapter.js"></script>       
    <script src="'.MCP_URL.'/Messenger/usercams/all.js"></script>
    <script src="'.MCP_URL.'/Messenger/usercams/empfangen.js?v=9"></script>

</body>
</html>';
    
        
echo $site;

p4c_close($db_server);
p4c_errorlog(error_get_last());