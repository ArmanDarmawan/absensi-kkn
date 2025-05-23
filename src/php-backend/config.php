<?php
// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');

// Establish database connection
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === FALSE) {
        die("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db(DB_NAME);
    
    // Create users table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === FALSE) {
        die("Error creating table: " . $conn->error);
    }
    
    // Check if admin exists, if not create one
    $sql = "SELECT * FROM users WHERE role='admin' LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        // Create default admin
        $admin_pass = password_hash('admin', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (full_name, username, email, password, role) 
                VALUES ('Administrator', 'admin', 'admin@unper.ac.id', '$admin_pass', 'admin')";
        $conn->query($sql);
    }
    
    return $conn;
}
?>
