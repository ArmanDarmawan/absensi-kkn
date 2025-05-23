<?php
session_start();
require_once 'config.php';

$error = '';
$registered = false;

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Check if coming from registration
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $registered = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        try {
            // Connect to database
            $conn = connectDB();
            
            if (!$conn) {
                throw new Exception("Koneksi database gagal");
            }
            
            // Get user data
            $stmt = $conn->prepare("SELECT id, full_name, username, email, password, role FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Persiapan query gagal: " . $conn->error);
            }
            
            $stmt->bind_param("s", $username);
            $exec = $stmt->execute();
            
            if (!$exec) {
                throw new Exception("Eksekusi query gagal: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Username atau password salah';
            } else {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Start session and store user data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Username atau password salah';
                }
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="1041px-Unper.png">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo">Absensi KKN Tematik 2025</h1>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <div class="form-header">
                    <h1>Sistem Absensi</h1>
                    <p>Login untuk melanjutkan</p>
                </div>
                
                <?php if ($registered): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <p>Pendaftaran berhasil! Silakan login.</p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . ($registered ? '?registered=1' : ''); ?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-user"></i>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input" 
                                placeholder="Masukkan username"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Masukkan password"
                            >
                        </div>
                    </div>

                    <button type="submit" class="form-button">Login</button>
                </form>

                <div class="form-links">
                    <p>
                    </p>
                    <p>
                    </p>
                    <p style="margin-top: 1rem;">
                        <a href="../../index.php">
                            Kembali ke Halaman Utama
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </main>


</body>
</html>
