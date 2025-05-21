const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const path = require('path');

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

// Serve static files
app.use(express.static(path.join(__dirname)));

// Store connected clients and their data
const clients = new Map();
const users = new Map();

// Broadcast user list to all clients
function broadcastUserList() {
    const userList = Object.fromEntries(users);
    const message = JSON.stringify({
        type: 'userList',
        users: userList
    });
    
    clients.forEach(client => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}

// Update user status
function updateUserStatus(userId, status) {
    const user = users.get(userId);
    if (user) {
        user.status = status;
        user.lastSeen = status === 'offline' ? new Date().toISOString() : null;
        
        // Broadcast status update
        const message = JSON.stringify({
            type: 'userStatus',
            userId: userId,
            status: status,
            lastSeen: user.lastSeen
        });
        
        clients.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(message);
            }
        });
    }
}

wss.on('connection', (ws) => {
    let clientId = null;

    ws.on('message', (message) => {
        const data = JSON.parse(message);

        switch(data.type) {
            case 'register':
                // Register the client
                clientId = data.id;
                clients.set(clientId, ws);
                users.set(clientId, {
                    name: data.name,
                    status: 'online',
                    lastSeen: null
                });
                console.log(`Client ${clientId} connected`);

                // Send confirmation
                ws.send(JSON.stringify({
                    type: 'registered',
                    id: clientId
                }));

                // Broadcast updated user list
                broadcastUserList();
                break;

            case 'message':
                // Send message to target client
                const targetClient = clients.get(data.target);
                if (targetClient) {
                    targetClient.send(JSON.stringify({
                        type: 'message',
                        sender: data.sender,
                        text: data.text,
                        timestamp: data.timestamp
                    }));
                }
                break;

            case 'addUser':
                // Add new user
                const newUserId = `user_${Date.now()}`;
                users.set(newUserId, {
                    name: data.name,
                    status: 'offline',
                    lastSeen: new Date().toISOString()
                });
                broadcastUserList();
                break;

            case 'getUserList':
                // Send current user list
                ws.send(JSON.stringify({
                    type: 'userList',
                    users: Object.fromEntries(users)
                }));
                break;
        }
    });

    ws.on('close', () => {
        if (clientId) {
            clients.delete(clientId);
            updateUserStatus(clientId, 'offline');
            console.log(`Client ${clientId} disconnected`);
        }
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Server is running on port ${PORT}`);
}); 