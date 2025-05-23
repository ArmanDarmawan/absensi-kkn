<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
     <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="src/php-backend/1041px-Unper.png">
    <title>Abensi</title>
    <link rel="stylesheet" href="src/php-backend/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
</head>
<style>
  /* Reset dasar */
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: Arial, sans-serif;
    background-color: #f0f2f5;
    color: #222;
    line-height: 1.5;
  }

  .container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 15px;
  }

  header.header {
    background-color: #222;
    padding: 15px 0;
    text-align: center;
    color: #fff;
  }

  header .logo {
    font-size: 20px;
    font-weight: bold;
  }

  main.main-content {
    padding: 30px 0;
  }

  .hero {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 30px;
    align-items: flex-start;
  }

  .hero-content {
    flex: 1 1 350px;
  }

  .hero-content h1 {
    font-size: 2rem;
    margin-bottom: 10px;
  }

  .hero-content p {
    font-size: 1.1rem;
    margin-bottom: 20px;
    color: #555;
  }

  .hero-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .btn {
    background-color: #333;
    color: white;
    padding: 12px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s ease;
    cursor: pointer;
  }

  .btn:hover {
    background-color: #555;
  }

  .btn-secondary {
    background-color: #666;
  }

  .btn-secondary:hover {
    background-color: #888;
  }

  .features {
    flex: 1 1 300px;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .feature {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    text-align: center;
  }

  .feature-icon {
    font-size: 2.5rem;
    color: #007bff;
    margin-bottom: 15px;
  }

  .feature h3 {
    margin-bottom: 10px;
    font-weight: bold;
  }

  .feature p {
    color: #666;
    font-size: 1rem;
  }

  footer.footer {
    background-color: #222;
    color: #ccc;
    text-align: center;
    padding: 15px 0;
    margin-top: 40px;
    font-size: 0.9rem;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .hero {
      flex-direction: column;
    }

    .hero-buttons {
      flex-direction: column;
    }

    .btn {
      width: 100%;
      justify-content: center;
    }
  }
</style>

<body>
<header class="header">
  <div class="container" style="display: flex; align-items: center; gap: 10px;">
    <img src="src/php-backend/1041px-Unper.png" alt="Logo" style="height: 40px; width: auto;" />
    <h1 class="logo" style="font-size: 20px; margin: 0;">Sistem Absensi KKN Tematik 2025</h1>
  </div>
</header>


    <main class="main-content">
        <div class="hero">
            <div class="hero-content">
                <h1>Sistem Absensi KKN</h1>
                <p>Kelola absensi KKN Tematik dengan mudah dan efisien</p>
                <div class="hero-buttons">
                    <a href="src/php-backend/attendance_public.php?mode=check_in" class="btn btn-primary">
                        Absen Masuk <i class="fas fa-sign-in-alt"></i>
                    </a>
                    
                    <a href="src/php-backend/attendance_public.php?mode=check_out" class="btn btn-primary">
                        Absen Pulang <i class="fas fa-sign-out-alt"></i>
                    </a>
                    
                    <a href="src/php-backend/attendance_report.php" class="btn btn-primary">
                        Lihat Laporan <i class="fas fa-file-alt"></i>
                    </a>
                    
                    <a href="src/php-backend/login.php" class="btn btn-secondary">
                        Login Admin <i class="fas fa-user-shield"></i>
                    </a>
                </div>
            </div>
            
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Manajemen User</h3>
                    <p>Kelola pengguna sistem dengan mudah. Tambah, edit, atau hapus data pengguna.</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Absensi Real-time</h3>
                    <p>Catat kehadiran secara otomatis berdasarkan waktu dengan sistem yang akurat.</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3>Laporan PDF</h3>
                    <p>Generate dan unduh laporan absensi dalam format PDF dengan mudah.</p>
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
