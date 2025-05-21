<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Array of profile pictures
$profilePictures = [
    'profile1.jpg',
    'profile2.jpg',
    'profile3.jpg',
    'profile4.jpg',
    'profile5.jpg',
    'profile6.jpg',
    'profile7.jpg',
    'profile8.jpg'
];

// Get all users
$query = "SELECT id, username, profile_picture FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update each user with a different profile picture
foreach ($users as $index => $user) {
    $pictureIndex = $index % count($profilePictures);
    $profilePicture = $profilePictures[$pictureIndex];
    
    $updateQuery = "UPDATE users SET profile_picture = :profile_picture WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(":profile_picture", $profilePicture);
    $updateStmt->bindParam(":user_id", $user['id']);
    $updateStmt->execute();
    
    echo "Updated user {$user['id']} with profile picture: {$profilePicture}\n";
}

echo "All user profiles have been updated successfully!";
?> 