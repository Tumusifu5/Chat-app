<?php
require 'vendor/autoload.php';
require_once 'config/database.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class ChatServer implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $users;
    protected $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        
        // Initialize database connection
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        
        switch($data->type) {
            case 'register':
                $this->users[$from->resourceId] = [
                    'userId' => $data->userId,
                    'username' => $data->username,
                    'connection' => $from
                ];
                
                // Update user status in database
                $this->updateUserStatus($data->userId, 'online');
                
                // Broadcast user status
                $this->broadcastStatus($data->userId, 'online');
                break;
                
            case 'message':
                // Save message to database
                $this->saveMessage($data);
                
                // Find receiver's connection
                $receiverConn = null;
                foreach ($this->users as $user) {
                    if ($user['userId'] == $data->receiverId) {
                        $receiverConn = $user['connection'];
                        break;
                    }
                }
                
                if ($receiverConn) {
                    // Send message to receiver
                    $receiverConn->send(json_encode([
                        'type' => 'message',
                        'senderId' => $data->senderId,
                        'receiverId' => $data->receiverId,
                        'message' => $data->message,
                        'timestamp' => $data->timestamp
                    ]));
                }
                
                // Send confirmation to sender
                $from->send(json_encode([
                    'type' => 'message',
                    'senderId' => $data->senderId,
                    'receiverId' => $data->receiverId,
                    'message' => $data->message,
                    'timestamp' => $data->timestamp
                ]));
                break;
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Update user status to offline
        if (isset($this->users[$conn->resourceId])) {
            $userId = $this->users[$conn->resourceId]['userId'];
            $this->updateUserStatus($userId, 'offline');
            $this->broadcastStatus($userId, 'offline');
            unset($this->users[$conn->resourceId]);
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    protected function broadcastStatus($userId, $status) {
        $message = json_encode([
            'type' => 'status',
            'userId' => $userId,
            'status' => $status
        ]);
        
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
    
    protected function updateUserStatus($userId, $status) {
        try {
            $query = "UPDATE users SET status = :status WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":user_id", $userId);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Error updating user status: " . $e->getMessage() . "\n";
        }
    }
    
    protected function saveMessage($data) {
        try {
            $query = "INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                     VALUES (:sender_id, :receiver_id, :message, :created_at)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":sender_id", $data->senderId);
            $stmt->bindParam(":receiver_id", $data->receiverId);
            $stmt->bindParam(":message", $data->message);
            $stmt->bindParam(":created_at", $data->timestamp);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Error saving message: " . $e->getMessage() . "\n";
        }
    }
}

$loop = Factory::create();
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

echo "WebSocket server started on port 8080\n";
$server->run(); 