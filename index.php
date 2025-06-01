<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="src/php-backend/1041px-Unper.png">
    <title>Sistem Absensi KKN Tematik Unper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0056b3; /* A professional blue */
            --primary-hover-color: #004085;
            --secondary-color: #6c757d; /* Gray for secondary elements */
            --secondary-hover-color: #5a6268;
            --light-bg-color: #f8f9fa;
            --white-color: #ffffff;
            --dark-text-color: #343a40;
            --light-text-color: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --font-family: 'Poppins', sans-serif;
            --border-radius: 8px;
        }

        /* Reset dasar */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--light-bg-color);
            color: var(--dark-text-color);
            line-height: 1.6;
            font-weight: 400;
        }

        .container {
            max-width: 1140px; /* Slightly wider for better spacing */
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .site-header { /* Renamed for clarity */
            background-color: var(--white-color);
            padding: 1rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .site-header .container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .site-header .logo-img {
            height: 45px; /* Slightly larger logo */
            width: auto;
        }

        .site-header .site-title { /* Renamed for clarity */
            font-size: 1.5rem; /* Adjusted size */
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        /* Main Content & Hero Section */
        .main-content {
            padding: 40px 0;
        }

        .hero-section { /* Renamed for clarity */
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px; /* Increased gap */
            align-items: center; /* Vertically align items */
        }

        .hero-content {
            flex: 1 1 500px; /* Allow more space for content */
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }


        .hero-content .hero-title { /* Renamed for clarity */
            font-size: 2.8rem; /* Larger heading */
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark-text-color);
            line-height: 1.3;
        }

        .hero-content .hero-subtitle { /* Renamed for clarity */
            font-size: 1.15rem; /* Slightly larger paragraph */
            margin-bottom: 30px; /* More space before buttons */
            color: var(--light-text-color);
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px; /* Increased gap */
        }

        .btn {
            background-color: var(--primary-color);
            color: var(--white-color);
            padding: 12px 25px; /* Adjusted padding */
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500; /* Adjusted font weight */
            display: inline-flex;
            align-items: center;
            gap: 10px; /* Increased gap for icon */
            transition: background-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
        }

        .btn:hover {
            background-color: var(--primary-hover-color);
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 86, 179, 0.3);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
        }
        .btn-secondary:hover {
            background-color: var(--secondary-hover-color);
            box-shadow: 0 4px 10px rgba(0,0,0, 0.2);
        }
        /* Alternative secondary style: Outline button */
        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white-color);
            box-shadow: 0 4px 10px rgba(0, 86, 179, 0.2);
        }


        /* Features Section */
        .features-section { /* Renamed for clarity */
            flex: 1 1 400px; /* Adjust basis */
            display: flex;
            flex-direction: column;
            gap: 25px; /* Increased gap */
            animation: fadeInRight 0.8s ease-out 0.2s; /* Delayed animation */
            animation-fill-mode: backwards; /* Apply start state before animation */
        }

        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .feature-card { /* Renamed for clarity */
            background: var(--white-color);
            padding: 25px; /* Increased padding */
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .feature-icon {
            font-size: 2.8rem; /* Larger icon */
            color: var(--primary-color);
            margin-bottom: 20px; /* More space below icon */
        }

        .feature-card h3 {
            margin-bottom: 10px;
            font-weight: 600; /* Bolder heading */
            font-size: 1.25rem;
            color: var(--dark-text-color);
        }

        .feature-card p {
            color: var(--light-text-color);
            font-size: 0.95rem;
        }

        /* Footer */
        .site-footer { /* Renamed for clarity */
            background-color: var(--dark-text-color); /* Darker footer */
            color: #a0aec0; /* Lighter text for contrast */
            text-align: center;
            padding: 25px 0; /* Increased padding */
            margin-top: 50px; /* More space above footer */
            font-size: 0.9rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) { /* Tablet breakpoint */
            .hero-content .hero-title {
                font-size: 2.4rem;
            }
            .hero-content {
                flex-basis: 100%; /* Stack hero content above features */
                text-align: center;
                margin-bottom: 30px; /* Add space when stacked */
            }
            .hero-buttons {
                justify-content: center;
            }
            .features-section {
                flex-basis: 100%;
                align-items: center; /* Center cards when stacked */
            }
            .feature-card {
                width: 100%;
                max-width: 450px; 
            }
        }

        @media (max-width: 768px) { /* Mobile breakpoint */
            .site-header .site-title {
                font-size: 1.25rem;
            }
             .site-header .logo-img {
                height: 40px;
            }
            .hero-content .hero-title {
                font-size: 2rem;
            }
            .hero-content .hero-subtitle {
                font-size: 1.05rem;
            }
            .hero-buttons {
                flex-direction: column; /* Stack buttons */
                align-items: stretch; /* Make buttons full width of their container */
            }
            .btn {
                width: 100%;
                justify-content: center;
                padding: 14px 20px; /* Larger touch target */
            }
            .features-section {
                gap: 20px;
            }
            .feature-card {
                padding: 20px;
            }
            .feature-icon {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }
            .site-header .site-title {
                font-size: 1.1rem;
            }
            .site-header .logo-img {
                height: 35px;
            }
            .hero-content .hero-title {
                font-size: 1.8rem;
            }
            .hero-content .hero-subtitle {
                font-size: 1rem;
            }
            .feature-icon {
                font-size: 2.2rem;
            }
            .feature-card h3 {
                font-size: 1.1rem;
            }
             .feature-card p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <img src="src/php-backend/1041px-Unper.png" alt="Logo Unper" class="logo-img" />
        <h1 class="site-title">Sistem Absensi KKN Tematik 2025</h1>
    </div>
</header>

<main class="main-content container"> <section class="hero-section"> <div class="hero-content">
            <h1 class="hero-title">Selamat Datang di Sistem Absensi KKN Unper</h1>
            <p class="hero-subtitle">Kelola dan pantau absensi kegiatan Kuliah Kerja Nyata Tematik Universitas Perjuangan dengan mudah, cepat, dan efisien.</p>
            <div class="hero-buttons">
                <a href="src/php-backend/attendance_public.php?mode=check_in" class="btn">
                    <i class="fas fa-right-to-bracket"></i> Absen Masuk
                </a>
                <a href="src/php-backend/attendance_public.php?mode=check_out" class="btn">
                    <i class="fas fa-right-from-bracket"></i> Absen Pulang
                </a>
                <a href="src/php-backend/attendance_report.php" class="btn">
                    <i class="fas fa-chart-line"></i> Lihat Laporan
                </a>
                <a href="src/php-backend/login.php" class="btn btn-outline"> <i class="fas fa-user-shield"></i> Login Admin
                </a>
            </div>
        </div>
        
        <div class="features-section">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users-cog"></i> </div>
                <h3>Manajemen Pengguna</h3>
                <p>Administrasi data pengguna sistem yang terpusat dan mudah dikelola.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Absensi Akurat</h3>
                <p>Pencatatan kehadiran real-time dengan data waktu yang presisi dan otomatis.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <h3>Laporan PDF Praktis</h3>
                <p>Hasilkan dan unduh rekapitulasi absensi dalam format PDF secara instan.</p>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?php echo date("Y"); ?> Sistem Absensi KKN Tematik - Universitas Perjuangan Tasikmalaya. Hak Cipta Dilindungi.</p>
    </div>
</footer>
</body>
</html>