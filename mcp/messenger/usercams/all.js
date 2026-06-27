"use strict";

// http://www.wolter.biz/2013/12/echtzeitkommunikation-mit-webrtc-entrtselt-und-ohne-schnickschnack/
// https://www.computerweekly.com/de/antwort/Was-ist-ein-WebRTC-Server-und-welche-Funktion-hat-er
// 
// one-2-many
// https://doc-kurento.readthedocs.io/en/stable/tutorials/java/tutorial-one2many.html


const server = {
    "iceServers": [
        //{"urls": "stun:stun.l.google.com:19302" },
        //{"urls": "stun:stun1.l.google.com:19302" },
        {"urls": "stun:turn.me-on.de:3478", "credential": "ruiefjkdsa", "username": "coturn"},
        {"urls": "turn:turn.me-on.de:3478", "credential": "ruiefjkdsa", "username": "coturn"}  // TURN Server address
    ],
    iceTransportPolicy: "relay"
    //iceCandidatePoolSize: 10,
};


 function sendHttpRequest(data, callback) {
    
    // Beispiel Aufruf:
    // ---------------------
    // sendHttpRequest.send({url:"receive_send_sdp.php", params:"param1=foo&param2=bar"});
    
    // GET responseText:
    // ---------------------
    // sendHttpRequest({url:"receive_send_sdp.php", params:"candidate=foo&param2=bareeeee"}).then(function (return_xhr) {
    //    console.log(return_xhr.responseText);
    // });
   
    // default Settings:
    // =============================
    // method = POST
    // params = ""
    // async = true
   
    
    var url = typeof data.url == "undefined" ? console.log("URL angeben.") : data.url;
    var parameter = typeof data.params == "undefined" ? null : data.params;
    var async = typeof data.async == "undefined" ? true : data.async;
    var method = typeof data.method == "undefined" ? "POST" : data.method;
    
    return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, async);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.send(parameter);
        xhr.onload = function () {
            if (this.status >= 200 && this.status < 300) {
                resolve(xhr);
            }
        };
    });
};

// Hilfsfunktion zum Debuggen
function print_r(o) {
    var debug = JSON.stringify(o,null,'\t').replace(/\n/g,"\n").replace(/\t/g,'   ');
    return debug.replace(/\r/g,"\n"); 
}

// Videostream im Video HTML-Tag schreiben/anzeigen
async function addStreamToVideoTag(stream, tag) {
    var mediaControl = document.getElementById(tag);
    if ('srcObject' in mediaControl) {
        mediaControl.srcObject = stream;
    } else if (navigator.mozGetUserMedia) {
        mediaControl.mozSrcObject = stream;
    } else {
        mediaControl.src = (window.URL || window.webkitURL).createObjectURL(stream);
    }
   
}


async function getUserMedia(options, successCallback, failureCallback) {

    var api = navigator.mediaDevices.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
    //var api = navigator.getUserMedia || navigator.mediaDevices.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;

    if (api) {
        return api.bind(navigator)(options, successCallback, failureCallback);
    }
}


