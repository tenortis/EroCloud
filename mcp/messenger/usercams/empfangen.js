
/*
 * 
 * pc1.restartIce();
 * 
 * 
 * 
 * 
 * 
 */

var pc1;
var remoteCandidates = [];

const video = document.getElementById('remoteVideo');

// Beim ersten Aufruf der Datei, SDP und ICE vom Empfänger löschen
//sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&iceCandidate"});
//sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&localDescription"});

var status_old;
status_old = 'offline';

var online_status = setInterval(async function(){
    sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"transmitter="+senderID+"&get_connection_status"}).then(function (xhr) {
        var status = JSON.parse(xhr.responseText);
        //console.log(status_old, status);
        
        if (status.transmitter === 'online' && status.receiver === 'offline' && status_old === 'offline') {
            console.log('Neue Peer-Verbindung zum Sender herstellen.', '##################################');
            openRemoteCam();
            status_old = 'online';
        } else if (status.transmitter === 'offline' && status.receiver === 'online') {
            console.log("X2");
            //console.log("Sender hat beendet. (1)");
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&iceCandidate"});
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&localDescription"});
            closeVideoCall();
            status_old = 'offline';
            window.parent.jQuery("#container").find("#cambar [data-stream_member_id='"+senderID+"']").remove();
            
//        } else if (status.transmitter === 'offline' && status.receiver === 'offline' && status_old === 'online') {
//            console.log("Sender hat beendet. (2)");
//            closeVideoCall();
            
        } else if (status.transmitter === 'online' && status.receiver === 'offline' && status_old === 'online') {
            console.log("X3");
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&iceCandidate"});
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&localDescription"});
            openRemoteCam();
            status_old = 'online';
        } else if (status.transmitter === 'online' && status.receiver === 'online' && status_old === 'offline') {
            console.log("X4");
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&iceCandidate"});
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&localDescription"});
            status_old = 'offline';

            
        } else if (status.transmitter === 'offline' && status.receiver === 'offline' && status_old === 'offline') {
console.log("X5");
            var remoteVideo = document.getElementById("remoteVideo");

            if (remoteVideo.srcObject) {
                remoteVideo.srcObject.getTracks().forEach(track => track.stop());
            }

            remoteVideo.removeAttribute("src");
            remoteVideo.removeAttribute("srcObject");
            jQuery("#stream_loading").show().html("Stream beendet.");
        } else {
            
        }
        
        
    });
}, 2000);
     
/*
if (get_online_status() === 'offline') {
    console.log('offline');
} else {
    console.log('offline');
}
*/

async function openRemoteCam() {
    sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"iceCandidate"});
    
    /**
     * event.localDescription empfangen
     *****************************/ 
    console.log("SDP vom Sender aus DB abholen (createOffer)...");
    await sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"sender_id="+senderID+"&get_localDescription=true"}).then(function (xhr) {
        var xhr_msg = JSON.parse(xhr.responseText);
        console.log("...SDP vom Sender aus DB erhalten.");
        
        //var targetUsername = xhr_msg.name;
        var sdp = xhr_msg.sdp;
        
        //console.log(sdp);
        
        // RTCPeerConnection is prefixed in Blink-based browsers.
        window.RTCPeerConnection = window.RTCPeerConnection || window.webkitRTCPeerConnection;
        pc1 = new RTCPeerConnection(server);
    
        // Der lokale ICE-Agent muss eine Nachricht an den anderen Peer über den Signalisierungsserver senden
        pc1.onicecandidate = gotRemoteIceCandidate;
    
        pc1.ontrack = function(event) {
            //console.log("##################:");
            //console.log(event.streams[0]);
            addStreamToVideoTag(event.streams[0], 'remoteVideo');
          
            /*
            if(event.srcElement.iceConnectionState === 'connected' &&
                e.srcElement.iceGatheringState === 'complete') {
                // attach to video-element
            }
            */
            
        };
        //pc1.onaddstream = gotRemoteStream;

        var desc = new RTCSessionDescription(sdp);
        pc1.setRemoteDescription(desc)
        .then(function() {
          return pc1.createAnswer();
        })
        .then(function(answer) {
            console.log("Lokal SDP fuer Sender in DB speichern...");
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&localDescription="+window.btoa(JSON.stringify(answer))}).then(function (xhr) {
                //console.log("set localDescription:", answer);
                console.log("...Lokal SDP fuer Sender in DB gespeichert.")
                pc1.setLocalDescription(answer);
                
                // Send the answer to the remote peer using the signaling server
                // ICE-Kandidaten vom Sender verarbeiten und hinzufügen
                console.log("IceCandidate vom Sender aus DB abholen...");
                sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"actor_id="+senderID+"&get_IceCandidate=true"}).then(function (xhr) {
                    //console.log("get IceCandidates:", xhr.responseText);
                    console.log("...IceCandidate vom Sender aus DB erhalten.");
                    var senderIceCandidates = JSON.parse(xhr.responseText);
                    //var senderIceText = document.getElementById("senderIceText");
                    //senderIceText.value = xhr.responseText;

                    for (var i in senderIceCandidates) {
                        var senderIceCandidate = new RTCIceCandidate(senderIceCandidates[i]);
                        pc1.addIceCandidate(senderIceCandidate);
                    }
                    
                    
                    // Jedes mal Mal, wenn sich der ICE-Verbindungsstatus während des Aushandlungsprozesses ändert,
                    // steht der neue ICE-Verbindungsstatus zum auslesen bereit
                    pc1.oniceconnectionstatechange = handleICEConnectionStateChangeEvent;

                    // Status des Signalisierungsprozesses loggen
                    pc1.onicegatheringstatechange = handleICEGatheringStateChangeEvent;

                    // Den "ICE candidate gathering process state change" loggen.
                    // Dient aktuell nur zum Debuggen
                    pc1.onsignalingstatechange = handleSignalingStateChangeEvent;
                    
                });
                
                
            })
        })
        /*
        .then(function(answer) {
            // Send the answer to the remote peer using the signaling server
            // ICE-Kandidaten vom Sender verarbeiten und hinzufügen
            console.log("IceCandidate vom Sender aus DB abholen...");
            sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"actor_id="+senderID+"&get_IceCandidate=true"}).then(function (xhr) {
                //console.log("get IceCandidates:", xhr.responseText);
                console.log("...IceCandidate vom Sender aus DB erhalten.");
                var senderIceCandidates = JSON.parse(xhr.responseText);
                //var senderIceText = document.getElementById("senderIceText");
                //senderIceText.value = xhr.responseText;

                for (var i in senderIceCandidates) {
                    var senderIceCandidate = new RTCIceCandidate(senderIceCandidates[i]);
                    pc1.addIceCandidate(senderIceCandidate);
                }
            });
        })
        */
        

    });
}

function gotRemoteIceCandidate(event) {
    //console.log("IceCandidate fuer Sender an DB senden...");

    //if (!event || event.candidate == null || !event.candidate || !event.candidate.candidate) {
    if (event.candidate === null) {
        return;
    }

    //var value = document.getElementById('return_ice_candidate').value;
    //document.getElementById('return_ice_candidate').value=value+"\n\n"+print_r(event.candidate);
    console.log("IceCandidate fuer Sender an DB senden...");
    
    remoteCandidates.push(event.candidate);
    sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&iceCandidate="+window.btoa(JSON.stringify(remoteCandidates))}).then(function (xhr) {
        console.log("IceCandidates fuer Sender in DB gespeichert.");
        //console.log("get IceCandidates:", xhr.responseText);
    })

}

/*
function gotRemoteStream(event) {

    //console.log("gotRemoteStream stream: " + event.stream);
    var remoteVideo = document.getElementById("remoteVideo");
    remoteVideo.src = webkitURL.createObjectURL(event.stream);
    
    //addStreamToVideoTag(event.stream, 'remoteVideo');
    
}
*/

function handleICEConnectionStateChangeEvent(event) {
    console.log("ICE-Verbindungsstatus: ",pc1.iceConnectionState);
    if (pc1.iceConnectionState === "connected") {
        jQuery("#stream_loading").hide();
    
    } else if (pc1.iceConnectionState === "failed") {
        sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&iceCandidate"});
        sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"member_id="+senderID+"&localDescription"});
        closeVideoCall();
        jQuery("#stream_loading").show();

    } else if (pc1.iceConnectionState === "disconnected") {
        jQuery("#stream_loading").show();
        closeVideoCall();
        //alert("Der Stream wurde vom Sender beendet.");
        //pc1.restartIce();
        
    } else if (pc1.iceConnectionState === "checking") {
        jQuery("#stream_loading").show().html("Lade Stream...");
        //document.getElementById("show_webcam").disabled = true;
        //document.getElementById("close_webcam").disabled = false;
        
    } else if (pc1.iceConnectionState === "closed") {
        //console.log("beenden");
        sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"actor_id="+senderID+"&delete_connection"});
        sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"actor_id="+senderID+"&delete_connection"});
        closeVideoCall();
    }
}

function handleICEGatheringStateChangeEvent(event) {
    //console.log(event);
    console.log("Status des Signalisierungsprozesses: ", pc1.signalingState);

    if (pc1.signalingState === "have-local-offer") {

    } else if (pc1.signalingState === "stable") {
        //var remoteVideo = document.getElementById("remoteVideo");
        //console.log(remoteVideo);
        
    } else if (pc1.signalingState === "closed") {
        //closeVideoCall();
    }
}

function handleSignalingStateChangeEvent(event) {
    console.log("ICE candidate gathering process state change: ", pc1.iceGatheringState);
}

function closeVideoCall() {

    var remoteVideo = document.getElementById("remoteVideo");

    if (pc1) {
        pc1.close();
        /*
        pc1.ontrack = null;
        pc1.onremovetrack = null;
        pc1.onremovestream = null;
        pc1.onicecandidate = null;
        pc1.oniceconnectionstatechange = null;
        pc1.onsignalingstatechange = null;
        pc1.onicegatheringstatechange = null;
        pc1.onnegotiationneeded = null;
        */
        if (remoteVideo.srcObject) {
            remoteVideo.srcObject.getTracks().forEach(track => track.stop());
        }
        
        pc1 = null;
    }

    //document.getElementById("show_webcam").disabled = false;
    //document.getElementById("close_webcam").disabled = true;

    remoteVideo.removeAttribute("src");
    remoteVideo.removeAttribute("srcObject");
    
    sendHttpRequest({url:mcp_url+"/Messenger/usercams/receive_send_request.php", params:"iceCandidate"}).then(function (xhr) {
        //console.log("  ---> PHP Antwort: ",xhr.responseText);
    });
}
