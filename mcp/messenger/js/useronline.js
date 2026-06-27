var xhr_userlist;
var xhr_set_online;
var userlist_is_loadet;

var userlist = function() {

    if (xhr_userlist !== undefined && userlist_is_loadet !== undefined) {
        if (xhr_userlist.readyState > 0 && xhr_userlist.readyState < 4) {
            xhr_userlist.abort();
        }
    }

    xhr_userlist = jQuery.ajax({
        url: mcp_url+"/Messenger/Ajax/userlist.php",
        dataType:"json",
        type: "POST",
        data: "userlist=unread",
        timeout: 15000,
        async: true
    }).fail(function(jqXHR, textStatus){
        /*
        if (jqXHR.status == 0) { // 0=abort / 
            internet_disconnected = true;
                
            jQuery(this).messenger_info({
                title: "ACHTUNG!",
                content: "Dein Internet ist scheinbar gest&ouml;rt.",
                autoClose: false,
                is_error: true
            })
        }
     
         */
    }).done(function(data, status, xhr){
        //success: function (data, status, xhr) {
            if (typeof internet_disconnected !== 'undefined') {
                delete internet_disconnected;
                close_messenger_info();
            }
            
            userlist_is_loadet = true;
            
            jQuery("#count_marked_as_unanswered").text(data.count_marked_as_unanswered);
            jQuery("#count_userunread").text(data.count_user_unread);
            jQuery("#count_useronline").text(data.count_user_online);
            jQuery("#count_useroffline").text(data.count_user_offline);
//console.log("X: "+data.scriptime);
            jQuery(".userlist_marked_as_unanswered").html("");
            jQuery(".userlist_unread").html("");
            jQuery(".userlist_online").html("");
            jQuery(".userlist_offline").html("");

            var count_user_unread = data.count_user_unread;
            var count_marked_as_unanswered = data.count_marked_as_unanswered;
            var member_marked_as_unanswered_ary = data.user_marked_as_unanswered;
            var member_unread_ary = data.user_unread;
            var member_online_ary = data.user_online;
            var member_offline_ary = data.user_offline;
            var nachrichten_gesamt = 0;

            if (count_marked_as_unanswered > 0) {
                jQuery(".userlist_marked_as_unanswered_head").show();
            } else {
                jQuery(".userlist_marked_as_unanswered_head").hide();
            }
            
            jQuery("#playsound").html("");
            
            if (count_user_unread > 0) {
                jQuery(".userlis_unread_head").show();
                
                if ( document.hasFocus() ) {
                    titlemove('stopp');
                    temp_count_user_unread = count_user_unread;
                } else {
                    if (typeof temp_count_user_unread === 'undefined' || count_user_unread > temp_count_user_unread) {
                        titlemove('start');
                        temp_count_user_unread = count_user_unread;
                        
                        jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {get_messenger_sound: 'true'})
                        .done(function(status){
                            if (status === "1") {
                                playsound(mcp_url+'/Messenger/Sound/erocloud_female');
                            }
                        })
                    }
                }
                
            } else {
                jQuery(".userlis_unread_head").hide();
                delete temp_count_user_unread;
                titlemove('stop');
            }

            for(var i in member_marked_as_unanswered_ary) {
                var chat_id     = member_marked_as_unanswered_ary[i]["chat_id"];
                var username    = member_marked_as_unanswered_ary[i]["username"];
                var avatar      = member_marked_as_unanswered_ary[i]["avatar_url"];
                var domain      = member_marked_as_unanswered_ary[i]["domain"];
                var online      = member_marked_as_unanswered_ary[i]["online_status"];
                var unread_messages = member_marked_as_unanswered_ary[i]["unread_messages"];

                var amount_coins = '';
                if (member_marked_as_unanswered_ary[i]["amount_coins"] > 0) {
                    var amount_coins = '<i class=\"material-symbols-outlined coin-icon\" title="Dieser User hat Guthaben.">monetization_on</i>';
                }

                if (online === 1) {
                    jQuery(".userlist_marked_as_unanswered").append("<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                        "<div class=\"userlist-avatar\"><div class=\"userlist-avatar_online\"><img src=\""+avatar+"\" /></div></div>"+
                        "<div class=\"userlist-user\">"+
                            "<div>"+username+"</div>"+
                            "<div>"+domain+"</div>"+
                        "</div>"+
                        "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                        "<div style=\"clear:both\"></div>"+
                    "</div>");
                } else {
                    jQuery(".userlist_marked_as_unanswered").append("<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                        "<div class=\"userlist-avatar\"><div><img src=\""+avatar+"\" /></div></div>"+
                        "<div class=\"userlist-user\">"+
                            "<div>"+username+"</div>"+
                            "<div>"+domain+"</div>"+
                        "</div>"+
                        "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                        "<div style=\"clear:both\"></div>"+
                    "</div>");                    
                }
            } 

            for(var i in member_unread_ary) {
                var chat_id     = member_unread_ary[i]["chat_id"];
                var username    = member_unread_ary[i]["username"];
                var avatar      = member_unread_ary[i]["avatar_url"];
                var domain      = member_unread_ary[i]["domain"];
                var online      = member_unread_ary[i]["online_status"];
                var unread_messages = member_unread_ary[i]["unread_messages"];

                var amount_coins = '';
                if (member_unread_ary[i]["amount_coins"] > 0) {
                    var amount_coins = '<i class=\"material-symbols-outlined coin-icon\" title="Dieser User hat Guthaben.">monetization_on</i>';
                }

                if (unread_messages >= 1) {
                    if (unread_messages > 0) {
                        unread_messages = ' ('+unread_messages+')';
                    }
                    if (online === 1) {
                        var avatar_online = "class=\"userlist-avatar_online\"";
                    } else {
                        var avatar_online = "";
                    }
                    
                    jQuery(".userlist_unread").append("<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                        "<div class=\"userlist-avatar\"><div "+avatar_online+"><img src=\""+avatar+"\" /></div></div>"+
                        "<div class=\"userlist-user\">"+
                            "<div>"+username+unread_messages+"</div>"+
                            "<div>"+domain+"</div>"+
                        "</div>"+
                        "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                        "<div style=\"clear:both\"></div>"+
                    "</div>");
                }
                
                else if (online === 1) {
                    jQuery(".userlist_online").append("<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                        "<div class=\"userlist-avatar\"><div class=\"userlist-avatar_online\"><img src=\""+avatar+"\" /></div></div>"+
                        "<div class=\"userlist-user\">"+
                            "<div>"+username+"</div>"+
                            "<div>"+domain+"</div>"+
                        "</div>"+
                        "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                        "<div style=\"clear:both\"></div>"+
                    "</div>");
                } 
            }  

            for(var i in member_online_ary) {
                var chat_id     = member_online_ary[i]["chat_id"];
                var username    = member_online_ary[i]["username"];
                var avatar      = member_online_ary[i]["avatar_url"];
                var domain      = member_online_ary[i]["domain"];
                var online      = member_online_ary[i]["online_status"];
                var unread_messages = member_online_ary[i]["unread_messages"];

                var amount_coins = '';
                if (member_online_ary[i]["amount_coins"] > 0) {
                    var amount_coins = '<i class=\"material-symbols-outlined coin-icon\" title="Dieser User hat Guthaben.">monetization_on</i>';
                }

                jQuery(".userlist_online").append("<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                    "<div class=\"userlist-avatar\"><div class=\"userlist-avatar_online\"><img src=\""+avatar+"\" /></div></div>"+
                    "<div class=\"userlist-user\">"+
                        "<div>"+username+"</div>"+
                        "<div>"+domain+"</div>"+
                    "</div>"+
                    "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                    "<div style=\"clear:both\"></div>"+
                "</div>");
            }  
            
            for(var i in member_offline_ary) {
                var chat_id     = member_offline_ary[i]["chat_id"];
                var username    = member_offline_ary[i]["username"];
                var avatar      = member_offline_ary[i]["avatar_url"];
                var domain      = member_offline_ary[i]["domain"];
                var online      = member_offline_ary[i]["online_status"];
                var unread_messages = member_offline_ary[i]["unread_messages"];

                var amount_coins = '';
                if (member_offline_ary[i]["amount_coins"] > 0) {
                    var amount_coins = '<i class=\"material-symbols-outlined coin-icon\" title="Dieser User hat Guthaben.">monetization_on</i>';
                }

                jQuery(".userlist_offline").append("<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                    "<div class=\"userlist-avatar\"><div><img src=\""+avatar+"\" /></div></div>"+
                    "<div class=\"userlist-user\">"+
                        "<div>"+username+"</div>"+
                        "<div>"+domain+"</div>"+
                    "</div>"+
                    "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                    "<div style=\"clear:both\"></div>"+
                "</div>");               
            }  
                     
            if (typeof userlist_active !== 'undefined') {
                jQuery("[data-user-id="+userlist_active+"]").addClass("userlist_active");
            };
        //}
    });
}

var set_online = function() {
    
    if (xhr_set_online !== undefined) {
        if (xhr_set_online.readyState > 0 && xhr_set_online.readyState < 4) {
            xhr_set_online.abort();
        }
    }

    xhr_set_online = jQuery.post(mcp_url+"/Messenger/Ajax/submit.php", {set_online: 'true'});               
}


function playsound(file) {    
    jQuery("#playsound").html('<audio>'+
        //'<source src="'+file+'.ogg" type="audio/ogg">'+
        '<source src="'+file+'.mp3" type="audio/mpeg">'+
    '</audio>');

    jQuery("#playsound audio").trigger('play');
}


// if new message, than scroll <title>
var repeat=1; //enter 0 to not repeat scrolling after 1 run, othersise, enter 1;
var title="* Neue Nachricht! *";
var leng=title.length;
var start=1;

function titlemove(status) {
    if (status == "start") {
       
        var titl = title.substring(start, leng) + title.substring(0, start);
        document.title=titl;
        start++;
        if (start==leng+1) {
            start=0;
            if (repeat==0) {
                return;
            }
        }

        title_new_message = setTimeout( function() {
            titlemove("stop");
            titlemove("start");
        }, 140);

    } else {
        
        if(typeof title_new_message != "undefined") {
            clearTimeout(title_new_message);
            delete title_new_message;
            document.title=SeitenTitel;
        }
    }
}

jQuery(document).ready(function() {
    userlist();
    set_online();
    
    messenger_left = setInterval(function() {
        set_online();
        userlist();
    }, 10000);

    jQuery(".userlist_search_head").click(function() {
        jQuery(".userlist_search").animate({height: 'toggle'}, 400, function(){
            var icon = jQuery(".userlist_search_head .material-symbols-outlined");
            if (icon.html() === 'keyboard_arrow_up') {
                icon.html("keyboard_arrow_down");
            } else {
                icon.html("keyboard_arrow_up");
            } 
        });
    });

    jQuery(".userlist_marked_as_unanswered_head").click(function() {
        jQuery(".userlist_marked_as_unanswered").animate({height: 'toggle'}, 400, function(){
            var icon = jQuery(".userlist_marked_as_unanswered_head .material-symbols-outlined");
            if (icon.html() === 'keyboard_arrow_up') {
                icon.html("keyboard_arrow_down");
            } else {
                icon.html("keyboard_arrow_up");
            } 
        });
    });

    jQuery(".userlist_unread_head").click(function() {
        jQuery(".userlist_unread").animate({height: 'toggle'}, 400, function(){
            var icon = jQuery(".userlist_unread_head .material-symbols-outlined");
            if (icon.html() === 'keyboard_arrow_up') {
                icon.html("keyboard_arrow_down");
            } else {
                icon.html("keyboard_arrow_up");
            } 
        });
    });

    jQuery(".userlist_online_head").click(function() {
        jQuery(".userlist_online").animate({height: 'toggle'}, 400, function(){
            var icon = jQuery(".userlist_online_head .material-symbols-outlined");
            if (icon.html() === 'keyboard_arrow_up') {
                icon.html("keyboard_arrow_down");
            } else {
                icon.html("keyboard_arrow_up");
            } 
        });
    });   

    jQuery(".userlist_offline_head").click(function() {
        jQuery(".userlist_offline").animate({height: 'toggle'}, 400, function(){
            var icon = jQuery(".userlist_offline_head .material-symbols-outlined");
            if (icon.html() === 'keyboard_arrow_up') {
                icon.html("keyboard_arrow_down");
            } else {
                icon.html("keyboard_arrow_up");
            } 
        });
    });
    
    jQuery(".userlist_search_head").hide();
    jQuery("#messenger_left #left_search #search").keyup(function(e) {
        var search_word = jQuery("#messenger_left #left_search #search").val();
        
        if (search_word.length === 0) {
            jQuery(".userlist_search").html("");
            jQuery(".userlist_search_head").hide();
        }

        jQuery.ajax({
            url: mcp_url+"/Messenger/Ajax/submit.php",
            dataType:"json",
            type: "POST",
            data: "search="+encodeURIComponent(search_word),
            async: true,
            success: function (data) {
                var member_search_ary = data.search_users;
                var count_user_search = parseInt(data.count_user_search);
                
                jQuery("#count_search_results").text(count_user_search);
                
                if(count_user_search === 0) {
                    jQuery(".userlist_search_head").hide();
                } else {
                    jQuery(".userlist_search_head").show();
                }
                
                jQuery(".userlist_search").html("");
                
                var userlist_search = '';
                
                for(var i in member_search_ary) {
                    var chat_id     = member_search_ary[i]["chat_id"];
                    var username    = member_search_ary[i]["username"];
                    var avatar      = member_search_ary[i]["avatar_url"];
                    var domain      = member_search_ary[i]["domain"];
                    var online      = member_search_ary[i]["online_status"];

                    if (member_search_ary[i]["amount_coins"] > 0) {
                        var amount_coins = '<i class=\"material-symbols-outlined coin-icon\" title="Dieser User hat Guthaben.">monetization_on</i>';
                    } else {
                        var amount_coins = '';
                    }
                    
                    if(online === 1) {
                        var addOnline = 'class=\"userlist-avatar_online\"';
                    } else {
                        var addOnline = '';
                    }


                    userlist_search += "<div class=\"userlist\" data-user-id=\""+chat_id+"\" onclick=\"jQuery(this).open_chat();\" >"+
                        "<div class=\"userlist-avatar\"><div "+addOnline+"><img src=\""+avatar+"\" /></div></div>"+
                        "<div class=\"userlist-user\">"+
                            "<div>"+username+"</div>"+
                            "<div>"+domain+"</div>"+
                        "</div>"+
                        "<div class=\"userlist-icons\">"+amount_coins+"</div>"+
                        "<div style=\"clear:both\"></div>"+
                    "</div>";
                    
                }  
                
                jQuery(".userlist_search").append(userlist_search);
            }
        })
    });
    
});