<?php
session_start();
require_once 'config.php';

$errors = [];
$success = false;

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $fullName = trim($_POST['fullName'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validate required fields
    if (empty($fullName)) {
        $errors['fullName'] = 'Nama lengkap harus diisi';
    }
    
    if (empty($username)) {
        $errors['username'] = 'Username harus diisi';
    } elseif (strpos($username, ' ') !== false) {
        $errors['username'] = 'Username tidak boleh mengandung spasi';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email harus diisi';
    } elseif (!preg_match('/@unper\.ac\.id$/', $email)) {
        $errors['email'] = 'Email harus menggunakan domain unper.ac.id';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password harus diisi';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Konfirmasi password tidak sesuai';
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        $conn = connectDB();
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors['username'] = 'Username sudah digunakan';
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors['email'] = 'Email sudah terdaftar';
            } else {
                // All good, register the user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fullName, $username, $email, $password_hash);
                
                if ($stmt->execute()) {
                    $success = true;
                    // Redirect after 2 seconds
                    header("Refresh: 2; URL=login.php?registered=1");
                } else {
                    $errors['general'] = 'Terjadi kesalahan saat mendaftar: ' . $conn->error;
                }
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Sistem Absensi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo">SistemAbsensi</h1>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <div class="form-header">
                    <h1>Daftar Akun Baru</h1>
                    <p>Sistem Absensi Universitas</p>
                </div>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo $errors['general']; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <p>Pendaftaran berhasil! Mengalihkan ke halaman login...</p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="form-group">
                        <label for="fullName">Nama Lengkap</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-user"></i>
                            <input 
                                type="text" 
                                id="fullName" 
                                name="fullName" 
                                class="form-input<?php echo !empty($errors['fullName']) ? ' is-invalid' : ''; ?>" 
                                placeholder="Masukkan nama lengkap"
                                value="<?php echo htmlspecialchars($_POST['fullName'] ?? ''); ?>"
                            >
                        </div>
                        <?php if (!empty($errors['fullName'])): ?>
                            <div class="form-error"><?php echo $errors['fullName']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-user"></i>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input<?php echo !empty($errors['username']) ? ' is-invalid' : ''; ?>" 
                                placeholder="Masukkan username"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            >
                        </div>
                        <?php if (!empty($errors['username'])): ?>
                            <div class="form-error"><?php echo $errors['username']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Unper</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input<?php echo !empty($errors['email']) ? ' is-invalid' : ''; ?>" 
                                placeholder="nama@unper.ac.id"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            >
                        </div>
                        <?php if (!empty($errors['email'])): ?>
                            <div class="form-error"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input<?php echo !empty($errors['password']) ? ' is-invalid' : ''; ?>" 
                                placeholder="Masukkan password"
                            >
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="form-error"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Konfirmasi Password</label>
                        <div class="form-input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="confirmPassword" 
                                name="confirmPassword" 
                                class="form-input<?php echo !empty($errors['confirmPassword']) ? ' is-invalid' : ''; ?>" 
                                placeholder="Konfirmasi password"
                            >
                        </div>
                        <?php if (!empty($errors['confirmPassword'])): ?>
                            <div class="form-error"><?php echo $errors['confirmPassword']; ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="form-button"<?php echo $success ? ' disabled' : ''; ?>>
                        <?php echo $success ? 'Mendaftar...' : 'Daftar'; ?>
                    </button>
                </form>

                <div class="form-links">
                    <p>
                        Sudah punya akun?
                        <a href="login.php">Login</a>
                    </p>
                    <p style="margin-top: 1rem;">
                        <a href="index.php">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Halaman Utama
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>© 2025 SistemAbsensi. Hak Cipta Dilindungi.</p>
        </div>
    </footer>
</body>
</html>
