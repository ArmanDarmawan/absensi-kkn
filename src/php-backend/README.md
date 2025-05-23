# PHP Backend Integration Instructions

This document provides instructions for integrating the Next.js frontend with a PHP backend for the attendance system.

## Database Schema

Create a MySQL database with the following tables:

### users

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### attendance_records

```sql
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','late','absent') NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date` (`user_id`,`date`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## PHP API Endpoints

Create the following PHP files in your server:

### config.php

```php
<?php
// Database configuration
$servername = "localhost";
$username = "your_db_username";
$password = "your_db_password";
$dbname = "attendance_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Helper functions
function response($status, $message, $data = null) {
    header("HTTP/1.1 " . $status);
    $response = [
        'status' => $status,
        'message' => $message,
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Time related functions
function isLate($time) {
    $lateTime = strtotime('09:00:00');
    $absentTime = strtotime('10:00:00');
    $checkTime = strtotime($time);
    
    if ($checkTime <= $lateTime) {
        return 'present';
    } else if ($checkTime <= $absentTime) {
        return 'late';
    } else {
        return 'absent';
    }
}
```

### login.php

```php
<?php
require_once 'config.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->username) || !isset($data->password)) {
        response(400, "Username and password are required");
    }
    
    $username = $conn->real_escape_string($data->username);
    $password = $data->password;
    
    // Query to check if user exists
    $sql = "SELECT id, username, name, password, role FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // In a real application, you'd use password_verify() here
        // For this demo, we'll assume the password is correct
        if ($password) {
            // Generate a simple token (in production, use a proper JWT library)
            $token = bin2hex(random_bytes(32));
            
            // Return user data and token
            response(200, "Login successful", [
                "user" => [
                    "id" => $user['id'],
                    "username" => $user['username'],
                    "name" => $user['name'],
                    "role" => $user['role']
                ],
                "token" => $token
            ]);
        } else {
            response(401, "Invalid credentials");
        }
    } else {
        response(401, "Invalid credentials");
    }
} else {
    response(405, "Method not allowed");
}
```

### attendance.php

```php
<?php
require_once 'config.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    // Get user_id from query string
    if (!isset($_GET['user_id'])) {
        response(400, "User ID is required");
    }
    
    $user_id = $conn->real_escape_string($_GET['user_id']);
    
    // Check if month filter is provided
    $monthFilter = "";
    if (isset($_GET['month'])) {
        $month = $conn->real_escape_string($_GET['month']);
        $monthFilter = " AND DATE_FORMAT(date, '%Y-%m') = '$month'";
    }
    
    // Query to get attendance records
    $sql = "SELECT id, user_id, date, status, check_in_time, check_out_time, notes 
            FROM attendance_records 
            WHERE user_id = '$user_id' $monthFilter 
            ORDER BY date DESC";
    
    $result = $conn->query($sql);
    
    $records = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $records[] = [
                "id" => $row['id'],
                "user_id" => $row['user_id'],
                "date" => $row['date'],
                "status" => $row['status'],
                "checkInTime" => $row['check_in_time'],
                "checkOutTime" => $row['check_out_time'],
                "notes" => $row['notes']
            ];
        }
    }
    
    response(200, "Attendance records retrieved successfully", ["records" => $records]);
} else {
    response(405, "Method not allowed");
}
```

### check-in.php

```php
<?php
require_once 'config.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->user_id)) {
        response(400, "User ID is required");
    }
    
    $user_id = $conn->real_escape_string($data->user_id);
    $today = date('Y-m-d');
    $now = date('H:i:s');
    
    // Determine attendance status based on time
    $status = isLate($now);
    
    // Check if record already exists for today
    $check_sql = "SELECT id FROM attendance_records WHERE user_id = '$user_id' AND date = '$today'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        response(400, "Attendance already recorded for today");
    }
    
    // Insert new attendance record
    $sql = "INSERT INTO attendance_records (user_id, date, status, check_in_time) 
            VALUES ('$user_id', '$today', '$status', '$now')";
    
    if ($conn->query($sql) === TRUE) {
        $record_id = $conn->insert_id;
        
        // Get the created record
        $get_sql = "SELECT id, user_id, date, status, check_in_time, check_out_time, notes 
                    FROM attendance_records WHERE id = '$record_id'";
        $get_result = $conn->query($get_sql);
        $record = $get_result->fetch_assoc();
        
        response(201, "Attendance recorded successfully", [
            "record" => [
                "id" => $record['id'],
                "user_id" => $record['user_id'],
                "date" => $record['date'],
                "status" => $record['status'],
                "checkInTime" => $record['check_in_time'],
                "checkOutTime" => $record['check_out_time'],
                "notes" => $record['notes']
            ]
        ]);
    } else {
        response(500, "Error recording attendance: " . $conn->error);
    }
} else {
    response(405, "Method not allowed");
}
```

### generate-report.php

```php
<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // For TCPDF or other PDF library

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->user_id) || !isset($data->start_date) || !isset($data->end_date)) {
        response(400, "User ID, start date, and end date are required");
    }
    
    $user_id = $conn->real_escape_string($data->user_id);
    $start_date = $conn->real_escape_string($data->start_date);
    $end_date = $conn->real_escape_string($data->end_date);
    
    // Get user info
    $user_sql = "SELECT username, name, role FROM users WHERE id = '$user_id'";
    $user_result = $conn->query($user_sql);
    
    if ($user_result->num_rows === 0) {
        response(404, "User not found");
    }
    
    $user = $user_result->fetch_assoc();
    
    // Get attendance records
    $sql = "SELECT date, status, check_in_time, check_out_time, notes 
            FROM attendance_records 
            WHERE user_id = '$user_id' AND date BETWEEN '$start_date' AND '$end_date' 
            ORDER BY date ASC";
    
    $result = $conn->query($sql);
    
    $records = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $records[] = [
                "date" => $row['date'],
                "status" => $row['status'],
                "checkInTime" => $row['check_in_time'],
                "checkOutTime" => $row['check_out_time'],
                "notes" => $row['notes']
            ];
        }
    }
    
    // Generate PDF (this is a simplified example)
    // In a real implementation, you'd use a library like TCPDF
    $filename = "attendance_report_{$user['username']}_" . date('Ymd_His') . ".pdf";
    $filepath = "reports/$filename";
    
    // Placeholder for PDF generation
    // In a real implementation, you'd create the PDF here
    // Example: $pdf = new TCPDF(...);
    
    response(200, "Report generated successfully", [
        "reportUrl" => "/api/reports/$filename",
        "filename" => $filename,
        "user" => $user,
        "records" => $records
    ]);
} else {
    response(405, "Method not allowed");
}
```

## Next Steps

1. Save these PHP files to your web server
2. Configure your database connection in config.php
3. Install any required PHP libraries (like TCPDF for PDF generation)
4. Update the frontend API URLs in src/lib/api.ts to point to your PHP backend
5. Test the integration thoroughly

## Security Considerations

This is a basic implementation for demonstration purposes. For a production environment, make sure to:

1. Use proper password hashing with password_hash() and password_verify()
2. Implement proper JWT authentication
3. Validate and sanitize all input data
4. Use prepared statements to prevent SQL injection
5. Implement rate limiting to prevent abuse
6. Set up HTTPS for secure data transmission
