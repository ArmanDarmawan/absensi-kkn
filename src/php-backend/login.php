<?php
session_start();
require_once 'config.php'; // Pastikan file ini ada dan berfungsi dengan benar

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
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi.';
    } else {
        try {
            $conn = connectDB(); // Fungsi connectDB() harus didefinisikan di config.php
            
            if (!$conn) {
                // Catat error koneksi database secara internal jika perlu
                // error_log("Koneksi database gagal."); 
                throw new Exception("Tidak dapat terhubung ke basis data. Silakan coba lagi nanti.");
            }
            
            $stmt = $conn->prepare("SELECT id, full_name, username, email, password, role FROM users WHERE username = ?");
            if (!$stmt) {
                // error_log("Persiapan query gagal: " . $conn->error);
                throw new Exception("Terjadi kesalahan dalam persiapan data.");
            }
            
            $stmt->bind_param("s", $username);
            $exec = $stmt->execute();
            
            if (!$exec) {
                // error_log("Eksekusi query gagal: " . $stmt->error);
                throw new Exception("Terjadi kesalahan saat memproses login Anda.");
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Username atau password salah.';
            } else {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Username atau password salah.';
                }
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // Untuk pengguna, tampilkan pesan yang lebih umum
            // Pesan detail dari $e->getMessage() bisa dicatat di log server untuk debugging
            // error_log("Login Exception: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.';
            if (strpos($e->getMessage(), "Koneksi database gagal") !== false || strpos($e->getMessage(), "Tidak dapat terhubung") !== false) {
                $error = "Tidak dapat terhubung ke layanan kami saat ini. Mohon coba lagi nanti.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="1041px-Unper.png"> <title>Login - Absensi KKN Tematik 2025</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0056b3; /* Biru yang lebih modern */
            --primary-hover-color: #004085;
            --secondary-color: #6c757d;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --white-color: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d; /* Digunakan untuk teks sekunder */
            --gray-700: #495057; /* Digunakan untuk teks utama */
            --success-color: #28a745;
            --danger-color: #dc3545;
            --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --font-family: 'Poppins', sans-serif;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: var(--font-family);
            background-color: var(--light-color);
            color: var(--gray-700);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-top: 80px; /* Space for fixed header */
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-header {
            background-color: var(--white-color);
            color: var(--dark-color);
            padding: 1rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .page-header .container {
            display: flex;
            align-items: center;
            justify-content: center; /* Default center, adjust if logo on left */
        }
        
        .page-header-logo img {
            height: 40px; /* Sesuaikan ukuran logo */
            margin-right: 15px;
        }
        
        .page-header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem; /* Padding untuk mobile */
            width: 100%;
        }
        
        .form-container {
            background: var(--white-color);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        
        .form-container:hover {
            /* transform: translateY(-5px); */
            /* box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); */
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h1 {
            color: var(--dark-color);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-header p {
            color: var(--gray-600);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .form-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .form-input-wrapper .input-icon {
            position: absolute;
            left: 15px;
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px 12px 45px; /* Padding kiri untuk ikon */
            border: 1px solid var(--gray-400);
            border-radius: calc(var(--border-radius) - 2px);
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: var(--white-color);
            color: var(--gray-700);
        }
        
        .form-input::placeholder {
            color: var(--gray-500);
        }

        .form-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.25);
        }
        
        .form-button {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: var(--white-color);
            border: none;
            border-radius: calc(var(--border-radius) - 2px);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-button i {
            margin-right: 8px;
        }

        .form-button:hover {
            background-color: var(--primary-hover-color);
            transform: translateY(-2px);
        }
        
        .form-button:active {
            transform: translateY(0);
        }
        
        .form-links {
            margin-top: 2rem;
            text-align: center;
        }
        
        .form-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            font-size: 0.9rem;
        }
        
        .form-links a:hover {
            color: var(--primary-hover-color);
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            border-radius: calc(var(--border-radius) - 2px);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
            border: 1px solid transparent;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .alert-error i {
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
         .alert-success i {
            color: #155724;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: var(--gray-500);
            transition: color 0.3s;
            font-size: 0.9rem;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .footer {
            text-align: center;
            padding: 1.5rem 0;
            color: var(--gray-600);
            font-size: 0.9rem;
            background-color: var(--white-color);
            border-top: 1px solid var(--gray-200);
            margin-top: auto; /* Mendorong footer ke bawah */
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .page-header .container {
                flex-direction: column;
                text-align: center;
            }
            .page-header-logo img {
                margin-bottom: 5px;
                margin-right: 0;
            }
            .page-header-title {
                font-size: 1.3rem;
            }
            body {
                padding-top: 100px; /* Adjust for potentially taller header */
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 90px; /* Adjust if header height changes */
            }
            .form-container {
                padding: 1.5rem;
                margin: 0 1rem; /* Margin agar tidak menempel di tepi */
                box-shadow: none;
                border: none;
                 background-color: transparent; /* or var(--light-color) if preferred over page bg */

            }
             .main-content {
                padding: 1rem 0; /* Kurangi padding atas dan bawah */
                align-items: flex-start; /* Form mulai dari atas di mobile */
                padding-top: 1rem;
            }
            .form-header h1 {
                font-size: 1.6rem;
            }
            .form-input {
                font-size: 0.95rem;
            }
            .form-button {
                font-size: 0.95rem;
            }
             .page-header-logo img {
                height: 35px;
            }
            .page-header-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container">
            <div class="page-header-logo">
                <img src="1041px-Unper.png" alt="Logo Universitas Perjuangan">
            </div>
            <div class="page-header-title">
                Absensi KKN Tematik 2025
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="form-container">
            <div class="form-header">
                <h1>Selamat Datang</h1>
                <p>Silakan login untuk melanjutkan.</p>
            </div>
            
            <?php if ($registered): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p>Pendaftaran berhasil! Silakan login dengan akun Anda.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="form-input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="Masukkan username Anda"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            autocomplete="username"
                            required
                            aria-label="Username"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="form-input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Masukkan password Anda"
                            autocomplete="current-password"
                            required
                            aria-label="Password"
                        >
                        <i class="fas fa-eye password-toggle" id="togglePassword" aria-label="Toggle password visibility"></i>
                    </div>
                </div>

                <button type="submit" class="form-button">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="form-links">
                <p>
                    <a href="../../index.php"> <i class="fas fa-arrow-left"></i> Kembali ke Halaman Utama
                    </a>
                </p>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistem Absensi KKN Tematik Unper. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        // Optional: Add focus effects if needed, though CSS :focus is often sufficient
        // const inputs = document.querySelectorAll('.form-input');
        // inputs.forEach(input => {
        //     input.addEventListener('focus', function() {
        //         // this.style.borderColor = 'var(--primary-color)'; // Covered by CSS :focus
        //     });
            
        //     input.addEventListener('blur', function() {
        //         // this.style.borderColor = 'var(--gray-400)'; // Covered by CSS
        //     });
        // });
    </script>
</body>
</html>