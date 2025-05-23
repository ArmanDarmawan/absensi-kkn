<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'config.php';

session_start();

// Check if user is logged in and is admin (in a real app)
// For now we'll skip this check for demo purposes

// Connect to database
$conn = connectDB();

// Get all users except admin (to protect admin account)
$sql = "SELECT id, full_name, username, email, role, registered_at FROM users WHERE role != 'admin'";
$result = $conn->query($sql);

$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'users' => $users
]);

$conn->close();
?>
