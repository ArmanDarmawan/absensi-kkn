<?php
require_once 'config.php';

// Connect to database
$conn = connectDB();

// Create attendance table if not exists
$sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'late', 'absent') NOT NULL DEFAULT 'present',
    check_in_time TIME,
    check_out_time TIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, date)
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating attendance table: " . $conn->error);
}

echo "Database setup completed successfully!";
$conn->close();
?>
