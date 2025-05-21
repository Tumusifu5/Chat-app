<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not logged in']));
}

$database = new Database();
$db = $database->getConnection();

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if ($data['action'] === 'update_last_seen') {
    $query = "UPDATE users SET status = 'online' WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $_SESSION['user_id']);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
?> 