// Change localhost to the name or ip address of the host running the notification server
var notificationUrl = 'ws://localhost:9911';

function displayNotification(from, message) {
    var node = document.createElement("LI");

    if (from) {
        var nameNode = document.createElement("STRONG");
        var nameTextNode = document.createTextNode(from);
        nameNode.appendChild(nameTextNode);
        node.appendChild(nameNode);
    }

    var messageTextNode = document.createTextNode(message);
    node.appendChild(messageTextNode);

    document.getElementById("messageList").appendChild(node);
}

var conn;

function connectToNotification() {
	try {
    conn = new WebSocket(notificationUrl);
	} catch(e) {
		console.log(e);
	}
    conn.onopen = function() {
        document.getElementById('connectFormDialog').style.display = 'none';
        document.getElementById('messageDialog').style.display = 'block';

        var params = {
            'roomId': document.getElementsByName("room.name")[0].value,
            'userName': document.getElementsByName("user.name")[0].value,
            'socket_action': 'connect'
        };
        console.log(params);
        conn.send(JSON.stringify(params));
    };

    conn.onmessage = function(e) {
        console.log(e.data);
        var data = JSON.parse(e.data);

        if (data.hasOwnProperty('notification') && data.hasOwnProperty('from')) {
            displayNotification(data.from.name, data.notification.notification);
        }
        else if (data.hasOwnProperty('notification')) {
            displayNotification(null, data.notification);
        }
        else if (data.hasOwnProperty('type')) {
            if (data.type == 'list-users' && data.hasOwnProperty('clients')) {
                displayNotification(null, 'There are ' + data.clients.length + ' users connected');
            }
        }
    };

    conn.onerror = function(e) {
        console.log(e);
    };

    return false;
}

function sendNotification() {
    var d = new Date();
    var params = {
        'notification': document.getElementsByName("message")[0].value,
        'roomId': document.getElementsByName("room.name")[0].value,
        'userName': document.getElementsByName("user.name")[0].value,		
        'socket_action': 'notification',
        'timestamp': d.getTime()/1000
    };
    conn.send(JSON.stringify(params));

    document.getElementsByName("message")[0].value = '';
    return false;
}