<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get all users for chat
$usersQuery = "SELECT u.*, 
    CASE WHEN u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online' ELSE 'offline' END as current_status 
    FROM users u";
$usersStmt = $db->prepare($usersQuery);
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Update user's last seen timestamp
if (isset($_SESSION['user_id'])) {
    $updateQuery = "UPDATE users SET last_seen = NOW() WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(":user_id", $_SESSION['user_id']);
    $updateStmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Clone</title>
    <link rel="image/png" href="assets/profile/meetme.png">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --whatsapp-green: #128C7E;
            --whatsapp-light-green: #25D366;
            --whatsapp-dark-green: #075E54;
            --whatsapp-blue: #34B7F1;
            --whatsapp-chat-bg: #E5DDD5;
            --whatsapp-message-out: #DCF8C6;
            --whatsapp-message-in: #FFFFFF;
            --whatsapp-time: #667781;
            --whatsapp-header: #F0F2F5;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
        }
        
        .container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 30%;
            min-width: 300px;
            max-width: 400px;
            background-color: white;
            border-right: 1px solid #e9edef;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            background-color: var(--whatsapp-header);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e9edef;
        }
        
        .current-user {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .status-indicator.online {
            background-color: var(--whatsapp-light-green);
        }
        
        .status-indicator.offline {
            background-color: #a7a9ac;
        }
        
        .username {
            font-weight: 500;
            font-size: 16px;
            color: #111b21;
        }
        
        .search-box {
            padding: 8px 10px;
            background-color: var(--whatsapp-header);
            border-bottom: 1px solid #e9edef;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 20px 8px 32px;
            border-radius: 8px;
            border: none;
            background-color: white;
            font-size: 14px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="%238a8a8a"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 0 0 1.48-5.34c-.47-2.78-2.79-5-5.59-5.34a6.505 6.505 0 0 0-7.27 7.27c.34 2.8 2.56 5.12 5.34 5.59a6.5 6.5 0 0 0 5.34-1.48l.27.28v.79l4.25 4.25c.41.41 1.08.41 1.49 0 .41-.41.41-1.08 0-1.49L15.5 14zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>');
            background-repeat: no-repeat;
            background-position: 10px center;
            outline: none;
        }
        
        .user-list {
            flex: 1;
            overflow-y: auto;
            background-color: white;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #e9edef;
        }
        
        .user-item:hover {
            background-color: #f5f6f6;
        }
        
        .user-item.active {
            background-color: #f0f2f5;
        }
        
        .user-info {
            flex: 1;
            margin-left: 12px;
        }
        
        .user-info .username {
            font-size: 16px;
            margin-bottom: 2px;
        }
        
        .user-info .status {
            font-size: 13px;
            color: var(--whatsapp-time);
        }
        
        /* Chat Area Styles */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #e5ddd5;
            background-image: url('assets/whatsapp-bg.png');
            background-size: contain;
        }
        
        .chat-header {
            background-color: var(--whatsapp-header);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e9edef;
        }
        
        .selected-user {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px 80px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        /* WhatsApp Message Bubbles */
        .message-container {
            max-width: 65%;
            margin-bottom: 8px;
            display: flex;
        }
        
        .message-container.outgoing {
            align-self: flex-end;
            justify-content: flex-end;
        }
        
        .message-container.incoming {
            align-self: flex-start;
            justify-content: flex-start;
        }
        
        .message-bubble {
            padding: 8px 12px;
            border-radius: 7.5px;
            position: relative;
            box-shadow: 0 1px 0.5px rgba(0,0,0,0.1);
            word-wrap: break-word;
        }
        
        .message-container.outgoing .message-bubble {
            background-color: var(--whatsapp-message-out);
            border-top-right-radius: 0;
        }
        
        .message-container.incoming .message-bubble {
            background-color: var(--whatsapp-message-in);
            border-top-left-radius: 0;
        }
        
        .message-text {
            font-size: 14.2px;
            line-height: 19px;
            color: #111b21;
            margin-bottom: 2px;
        }
        
        .message-time {
            font-size: 11px;
            color: var(--whatsapp-time);
            text-align: right;
            margin-left: 8px;
            float: right;
            display: flex;
            align-items: center;
        }
        
        .message-time i {
            margin-left: 4px;
            font-size: 10px;
            color: var(--whatsapp-light-green);
        }
        
        /* Message Form */
        .message-form {
            background-color: var(--whatsapp-header);
            padding: 8px 16px;
            display: flex;
            align-items: center;
            border-top: 1px solid #e9edef;
        }
        
        #messageForm {
            display: flex;
            align-items: center;
            width: 100%;
            background-color: white;
            border-radius: 8px;
            padding: 5px 10px;
        }
        
        #messageInput {
            flex: 1;
            border: none;
            padding: 9px 12px;
            font-size: 15px;
            outline: none;
            border-radius: 8px;
        }
        
        .send-icon {
            width: 24px;
            height: 24px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .message-form button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .message-form button:hover .send-icon {
            opacity: 1;
        }
        
        /* No chat selected state */
        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #667781;
        }
        
        .no-chat-selected .users-icon {
            width: 250px;
            height: 250px;
            opacity: 0.4;
            margin-bottom: 20px;
        }
        
        .no-chat-selected h3 {
            font-size: 24px;
            font-weight: 300;
            margin-bottom: 8px;
            color: #41525d;
        }
        
        .no-chat-selected p {
            font-size: 14px;
            max-width: 450px;
            line-height: 20px;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .container {
                height: 100vh;
            }
            
            .sidebar {
                width: 100%;
                position: absolute;
                z-index: 10;
                left: -100%;
                transition: left 0.3s ease;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .chat-area {
                width: 100%;
            }
            
            .chat-messages {
                padding: 10px 20px;
            }
            
            .message-container {
                max-width: 80%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="current-user">
                    <div class="user-avatar">
                        <?php 
                        $currentUserPic = isset($_SESSION['user_id']) ? $users[array_search($_SESSION['user_id'], array_column($users, 'id'))]['profile_picture'] ?? 'default.png' : 'default.png';
                        $currentUserPath = "assets/profile/" . $currentUserPic;
                        if (file_exists($currentUserPath)) {
                            echo '<img src="' . $currentUserPath . '" alt="Profile">';
                        } else {
                            echo '<img src="assets/profile/default.png" alt="Profile">';
                        }
                        ?>
                        <div class="status-indicator <?php echo isset($_SESSION['user_id']) ? 'online' : 'offline'; ?>"></div>
                    </div>
                    <span class="username"><?php echo isset($_SESSION['user_id']) ? htmlspecialchars($users[array_search($_SESSION['user_id'], array_column($users, 'id'))]['username']) : 'Guest User'; ?></span>
                </div>
            </div>
            
            <div class="search-box">
                <input type="text" placeholder="Search or start new chat" onkeyup="searchUsers(this.value)">
            </div>
            
            <div class="user-list">
                <?php foreach ($users as $user): ?>
                <div class="user-item" data-user-id="<?php echo $user['id']; ?>" onclick="selectChat(<?php echo $user['id']; ?>)">
                    <div class="user-avatar">
                        <?php 
                        $profilePic = $user['profile_picture'];
                        $profilePath = "assets/profile/" . $profilePic;
                        if (file_exists($profilePath)) {
                            echo '<img src="' . $profilePath . '" alt="' . htmlspecialchars($user['username']) . '">';
                        } else {
                            echo '<img src="assets/profile/default.png" alt="' . htmlspecialchars($user['username']) . '">';
                        }
                        ?>
                        <div class="status-indicator <?php echo $user['current_status']; ?>"></div>
                    </div>
                    <div class="user-info">
                        <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="status <?php echo $user['current_status']; ?>">
                            <?php echo ucfirst($user['current_status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <div class="chat-header">
                <div class="selected-user">
                    <div class="user-avatar">
                        <img src="assets/profile/meetme.png" alt="WhatsApp">
                    </div>
                    <div class="user-info">
                       
                        <span class="status" id="selectedUserStatus">Select a chat to start messaging</span>
                    </div>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="no-chat-selected">
                    <img src="assets/profile/whatsapp-logo.png" alt="WhatsApp" class="users-icon">
                    
                </div>
            </div>
            
            <div class="message-form">
                <form id="messageForm" onsubmit="sendMessage(event)">
                    <input type="text" id="messageInput" placeholder="Type a message..." required>
                    <button type="submit">
                        <img src="assets/profile/send-message.png" alt="Send" class="send-icon">
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let selectedUserId = null;
        let ws = null;
        let reconnectAttempts = 0;
        const maxReconnectAttempts = 5;
        const currentUserId = '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest_' + Math.random().toString(36).substr(2, 9); ?>';
        
        function connectWebSocket() {
            try {
                ws = new WebSocket('ws://localhost:8080');
                
                ws.onopen = () => {
                    console.log('WebSocket connected successfully');
                    reconnectAttempts = 0;
                    ws.send(JSON.stringify({
                        type: 'register',
                        userId: currentUserId,
                        username: '<?php echo isset($_SESSION['user_id']) ? htmlspecialchars($users[array_search($_SESSION['user_id'], array_column($users, 'id'))]['username']) : 'Guest User'; ?>'
                    }));
                };

                ws.onmessage = (event) => {
                    console.log('Received message:', event.data);
                    const data = JSON.parse(event.data);
                    switch(data.type) {
                        case 'message':
                            if (data.senderId === selectedUserId || data.receiverId === selectedUserId) {
                                addMessage(data);
                            }
                            break;
                        case 'status':
                            updateUserStatus(data.userId, data.status);
                            break;
                        case 'error':
                            console.error('WebSocket error:', data.message);
                            alert('Error: ' + data.message);
                            break;
                    }
                };

                ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    alert('Connection error. Please check if the server is running.');
                };

                ws.onclose = () => {
                    console.log('WebSocket disconnected');
                    if (reconnectAttempts < maxReconnectAttempts) {
                        setTimeout(() => {
                            reconnectAttempts++;
                            console.log('Attempting to reconnect...', reconnectAttempts);
                            connectWebSocket();
                        }, 1000 * Math.pow(2, reconnectAttempts));
                    } else {
                        alert('Connection lost. Please refresh the page.');
                    }
                };
            } catch (error) {
                console.error('Error creating WebSocket:', error);
                alert('Error connecting to chat server. Please check if the server is running.');
            }
        }

        function selectChat(userId) {
            selectedUserId = userId;
            document.getElementById('chatMessages').innerHTML = '';
            const selectedUser = document.querySelector(`[data-user-id="${userId}"]`);
            const username = selectedUser.querySelector('.username').textContent;
            const status = selectedUser.querySelector('.status').textContent;
            const statusClass = selectedUser.querySelector('.status').className;
            const profilePic = selectedUser.querySelector('.user-avatar img').src;
            
            const chatHeader = document.querySelector('.chat-header');
            chatHeader.innerHTML = `
                <div class="selected-user">
                    <div class="user-avatar">
                        <img src="${profilePic}" alt="${username}">
                        <div class="status-indicator ${statusClass.split(' ')[1]}"></div>
                    </div>
                    <div class="user-info">
                        <span class="username">${username}</span>
                        <span class="status ${statusClass}">${status}</span>
                    </div>
                </div>
            `;
            
            // Load previous messages for this chat
            loadChatHistory(userId);
        }

        function loadChatHistory(userId) {
            // In a real app, you would fetch this from your server
            // For demo purposes, we'll just show a welcome message
            const welcomeMessage = {
                type: 'message',
                senderId: userId,
                message: 'You are now connected! Start chatting.',
                timestamp: new Date().toISOString()
            };
            addMessage(welcomeMessage);
        }

        function sendMessage(event) {
            event.preventDefault();
            if (!selectedUserId) {
                alert('Please select a user to chat with');
                return;
            }
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                alert('Connection lost. Please refresh the page.');
                return;
            }

            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            if (!message) return;

            try {
                const data = {
                    type: 'message',
                    senderId: currentUserId,
                    receiverId: selectedUserId,
                    message: message,
                    timestamp: new Date().toISOString()
                };

                console.log('Sending message:', data);
                ws.send(JSON.stringify(data));
                addMessage(data);
                messageInput.value = '';
                messageInput.focus();
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message. Please try again.');
            }
        }

        function addMessage(data) {
            const chatMessages = document.getElementById('chatMessages');
            
            // Remove the "no chat selected" message if it exists
            const noChatSelected = chatMessages.querySelector('.no-chat-selected');
            if (noChatSelected) {
                noChatSelected.remove();
            }
            
            const messageContainer = document.createElement('div');
            messageContainer.className = `message-container ${data.senderId === currentUserId ? 'outgoing' : 'incoming'}`;
            
            const messageBubble = document.createElement('div');
            messageBubble.className = 'message-bubble';
            
            const messageText = document.createElement('div');
            messageText.className = 'message-text';
            messageText.textContent = data.message;
            
            const messageTime = document.createElement('div');
            messageTime.className = 'message-time';
            messageTime.textContent = formatTime(data.timestamp);
            
            if (data.senderId === currentUserId) {
                const checkmark = document.createElement('i');
                checkmark.className = 'fas fa-check-double';
                messageTime.appendChild(checkmark);
            }
            
            messageBubble.appendChild(messageText);
            messageBubble.appendChild(messageTime);
            messageContainer.appendChild(messageBubble);
            chatMessages.appendChild(messageContainer);
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function searchUsers(query) {
            const userItems = document.querySelectorAll('.user-item');
            query = query.toLowerCase();
            
            userItems.forEach(item => {
                const username = item.querySelector('.username').textContent.toLowerCase();
                item.style.display = username.includes(query) ? 'flex' : 'none';
            });
        }

        function updateUserStatus(userId, status) {
            const userItem = document.querySelector(`[data-user-id="${userId}"]`);
            if (userItem) {
                const statusSpan = userItem.querySelector('.status');
                statusSpan.textContent = status === 'online' ? 'Online' : 'Offline';
                statusSpan.className = `status ${status}`;
                
                const statusIndicator = userItem.querySelector('.status-indicator');
                statusIndicator.className = `status-indicator ${status}`;
                
                if (userId === selectedUserId) {
                    document.getElementById('selectedUserStatus').textContent = status === 'online' ? 'Online' : 'Offline';
                    document.getElementById('selectedUserStatus').className = `status ${status}`;
                }
            }
        }

        // Update last seen every minute
        setInterval(() => {
            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_last_seen'
                })
            });
        }, 60000);

        // Connect to WebSocket when page loads
        connectWebSocket();

        // Mobile menu toggle (if needed)
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>