<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get all users
$usersQuery = "SELECT id FROM users";
$usersStmt = $db->prepare($usersQuery);
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all profile pictures
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

// Update each user's profile picture
$updateQuery = "UPDATE users SET profile_picture = ? WHERE id = ?";
$updateStmt = $db->prepare($updateQuery);

foreach ($users as $index => $user) {
    // Cycle through profile pictures if there are more users than pictures
    $profilePic = $profilePictures[$index % count($profilePictures)];
    
    try {
        $updateStmt->execute([$profilePic, $user['id']]);
        echo "Updated profile picture for user ID {$user['id']} to {$profilePic}\n";
    } catch (PDOException $e) {
        echo "Error updating user ID {$user['id']}: " . $e->getMessage() . "\n";
    }
}

echo "Profile picture update completed!\n";
?> 