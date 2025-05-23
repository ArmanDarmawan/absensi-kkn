<?php
session_start();
require_once 'config.php';

// Connect to database
$conn = connectDB();
date_default_timezone_set('Asia/Jakarta');

// Cek apakah user adalah admin
$is_admin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $is_admin = true;
}

// Filter data
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$nim_filter = isset($_GET['nim']) ? $_GET['nim'] : '';

// Query untuk mendapatkan data absensi dengan koordinat
$sql = "SELECT * FROM public_attendance WHERE 1=1";
$params = [];
$types = "";

if (!empty($date_filter)) {
    $sql .= " AND date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

if (!empty($nim_filter)) {
    $sql .= " AND nim = ?";
    $params[] = $nim_filter;
    $types .= "s";
}

$sql .= " ORDER BY date DESC, check_in_time DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$attendances = $result->fetch_all(MYSQLI_ASSOC);

// Jika request JSON
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($attendances);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Lokasi Absensi - Sistem Absensi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #333333;
            --secondary-color: #555555;
            --accent-color: #777777;
            --success-color: #444444;
            --danger-color: #666666;
            --warning-color: #888888;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --white: #ffffff;
            --black: #000000;
            --gray-light: #e9ecef;
            --gray-medium: #ced4da;
            --gray-dark: #adb5bd;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-color);
            color: var(--black);
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--black), var(--dark-color));
            color: var(--white);
            padding: 1.5rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
            z-index: 100;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .header nav ul {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            padding: 0;
            list-style: none;
        }
        
        .header nav a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .header nav a:hover, .header nav a.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .main-content {
            padding: 2rem 0;
        }
        
        .map-container {
            margin: 2rem 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            height: 500px;
        }
        
        .map-container:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        #map {
            height: 100%;
            width: 100%;
           
        }
        
        .filter-card {
            background-color: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .filter-card h2 {
            margin-top: 0;
            color: var(--black);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-card h2 i {
            font-size: 1.3rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--black);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--black);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--black);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-color);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: var(--gray-dark);
            color: var(--white);
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--black);
            margin: 2rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            font-size: 1.3rem;
        }
        
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .attendance-card {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 1.5rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--black);
            display: flex;
            flex-direction: column;
        }
        
        .attendance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .attendance-card h3 {
            margin: 0 0 0.5rem;
            color: var(--black);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .attendance-meta {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            flex-grow: 1;
        }
        
        .attendance-meta p {
            margin: 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .attendance-meta i {
            width: 1rem;
            text-align: center;
        }
        
        .attendance-location {
            background-color: var(--gray-light);
            padding: 1rem;
            border-radius: 8px;
            margin-top: auto;
        }
        
        .attendance-location h4 {
            margin: 0 0 0.75rem;
            font-size: 1rem;
            color: var(--black);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .attendance-location p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .btn-view-map {
            background-color: var(--black);
            color: var(--white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-view-map:hover {
            background-color: var(--dark-color);
            transform: translateY(-2px);
        }
        
        .btn-view-map i {
            font-size: 0.8rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-dark);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: var(--black);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--gray-dark);
            margin-bottom: 1.5rem;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card h3 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
            color: var(--gray-dark);
            font-weight: 500;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--black);
            margin: 0;
        }
        
        .chart-container {
            background-color: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .chart-container h3 {
            margin-top: 0;
            color: var(--black);
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        .footer {
            background-color: var(--black);
            color: var(--white);
            padding: 2rem 0;
            text-align: center;
            margin-top: 3rem;
        }
        
        .footer p {
            margin: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header nav ul {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .attendance-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Custom marker icons */
        .marker-in {
            background-color: var(--white);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: block;
            border: 3px solid var(--black);
            box-shadow: 0 0 0 2px var(--white);
        }
        
        .marker-out {
            background-color: var(--black);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: block;
            border: 3px solid var(--white);
            box-shadow: 0 0 0 2px var(--black);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--black);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-color);
        }

        /* Badge styles */
        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .badge-in {
            background-color: var(--white);
            color: var(--black);
            border: 1px solid var(--black);
        }
        
        .badge-out {
            background-color: var(--black);
            color: var(--white);
        }
        .offcanvas-header {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
<div class="container-fluid">
  
    <main class="main-content">
        <div class="container">
                        <a class="#kembali" href="dashboard.php">
                        Kembali
                        </a>
            <!-- Filter Card -->
            <div class="filter-card fade-in">
                <h2><i class="fas fa-filter"></i> Filter Data</h2>
                <form method="get" action="">
                    <div class="filter-form">
                        <div class="form-group">
                            <label for="date"><i class="far fa-calendar-alt"></i> Tanggal</label>
                            <input type="date" id="date" name="date" value="<?php echo $date_filter; ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="nim"><i class="fas fa-id-card"></i> NIM</label>
                            <input type="text" id="nim" name="nim" value="<?php echo $nim_filter; ?>" placeholder="Masukkan NIM" class="form-control">
                        </div>
                        <div class="form-group btn-group">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                            <a href="attendance_map.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container fade-in">
                <div class="stat-card">
                    <h3>Total Absensi</h3>
                    <p class="stat-value"><?php echo count($attendances); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Tanggal Terakhir</h3>
                    <p class="stat-value"><?php echo count($attendances) > 0 ? date('d M Y', strtotime($attendances[0]['date'])) : '-'; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Paling Awal</h3>
                    <p class="stat-value">
                        <?php 
                        if (count($attendances) > 0) {
                            $earliest = null;
                            foreach ($attendances as $att) {
                                if (!empty($att['check_in_time']) && (is_null($earliest) || $att['check_in_time'] < $earliest['check_in_time'])) {
                                    $earliest = $att;
                                }
                            }
                            echo $earliest ? date('H:i', strtotime($earliest['check_in_time'])) : '-';
                        } else {
                            echo '-';
                        }
                        ?>
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Paling Telat</h3>
                    <p class="stat-value">
                        <?php 
                        if (count($attendances) > 0) {
                            $latest = null;
                            foreach ($attendances as $att) {
                                if (!empty($att['check_in_time']) && (is_null($latest) || $att['check_in_time'] > $latest['check_in_time'])) {
                                    $latest = $att;
                                }
                            }
                            echo $latest ? date('H:i', strtotime($latest['check_in_time'])) : '-';
                        } else {
                            echo '-';
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Map Container -->
            <div class="map-container fade-in">
                <div id="map"></div>
            </div>

            <!-- Attendance List -->
            <h2 class="section-title"><i class="fas fa-list-ul"></i> Daftar Absensi</h2>
            
            <?php if (count($attendances) > 0): ?>
                <div class="attendance-grid">
                    <?php foreach ($attendances as $attendance): ?>
                        <div class="attendance-card fade-in">
                            <h3><?php echo htmlspecialchars($attendance['full_name']); ?></h3>
                            <div class="attendance-meta">
                                <p><i class="fas fa-id-card"></i> <strong>NIM:</strong> <?php echo htmlspecialchars($attendance['nim']); ?></p>
                                <p><i class="fas fa-graduation-cap"></i> <strong>Program Studi:</strong> <?php echo htmlspecialchars($attendance['prodi']); ?></p>
                                <p><i class="far fa-calendar"></i> <strong>Tanggal:</strong> <?php echo date('d F Y', strtotime($attendance['date'])); ?></p>
                                <p>
                                    <i class="far fa-clock"></i> <strong>Waktu:</strong> 
                                    <?php if (!empty($attendance['check_in_time'])): ?>
                                        <span class="badge" style="background-color: #4ade80; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">
                                            Masuk: <?php echo date('H:i', strtotime($attendance['check_in_time'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($attendance['check_out_time'])): ?>
                                        <span class="badge" style="background-color: #f87171; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-left: 5px;">
                                            Pulang: <?php echo date('H:i', strtotime($attendance['check_out_time'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="attendance-location">
                                <h4><i class="fas fa-map-marker-alt"></i> Lokasi Absensi</h4>
                                <?php if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])): ?>
                                    <p>
                                        <span><i class="fas fa-sign-in-alt" style="color: #4ade80;"></i> <strong>Masuk:</strong> 
                                        <?php echo $attendance['latitude_in']; ?>, <?php echo $attendance['longitude_in']; ?></span>
                                        <button class="btn-view-map" onclick="showOnMap(<?php echo $attendance['latitude_in']; ?>, <?php echo $attendance['longitude_in']; ?>, '<?php echo htmlspecialchars($attendance['full_name']); ?> (Masuk)')">
                                            <i class="fas fa-map-marked-alt"></i> Lihat
                                        </button>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($attendance['latitude_out']) && !empty($attendance['longitude_out'])): ?>
                                    <p>
                                        <span><i class="fas fa-sign-out-alt" style="color: #f87171;"></i> <strong>Pulang:</strong> 
                                        <?php echo $attendance['latitude_out']; ?>, <?php echo $attendance['longitude_out']; ?></span>
                                        <button class="btn-view-map" onclick="showOnMap(<?php echo $attendance['latitude_out']; ?>, <?php echo $attendance['longitude_out']; ?>, '<?php echo htmlspecialchars($attendance['full_name']); ?> (Pulang)')">
                                            <i class="fas fa-map-marked-alt"></i> Lihat
                                        </button>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state fade-in">
                    <i class="far fa-folder-open"></i>
                    <h3>Tidak Ada Data Absensi</h3>
                    <p>Silakan coba dengan filter yang berbeda atau tanggal lain.</p>
                    <a href="attendance_map.php" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Muat Ulang</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <footer class="footer">
        <div class="container">
            <p>© 2025 SistemAbsensi. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script>
        // Initialize date picker
        flatpickr("#date", {
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo $date_filter; ?>"
        });

        // Create custom marker icons
        const greenIcon = L.divIcon({
            className: 'marker-in',
            html: '<i class="fas fa-sign-in-alt" style="color: white; font-size: 10px; display: flex; align-items: center; justify-content: center; height: 100%;"></i>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        const redIcon = L.divIcon({
            className: 'marker-out',
            html: '<i class="fas fa-sign-out-alt" style="color: white; font-size: 10px; display: flex; align-items: center; justify-content: center; height: 100%;"></i>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        // Inisialisasi peta dengan view yang lebih dinamis
        const map = L.map('map').setView([-7.3351, 108.3234], 15);

        // Tambahkan layer peta dengan tema yang lebih modern
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Tambahkan marker untuk lokasi Desa Cikondang dengan ikon custom
        const mainLocationIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34]
        });

        L.marker([-7.3351, 108.3234], {icon: mainLocationIcon}).addTo(map)
            .bindPopup('<b>Desa Cikondang</b><br>Lokasi utama absensi')
            .openPopup();

        // Tambahkan lingkaran dengan radius 1km (area yang diizinkan) dengan style yang lebih menarik
        L.circle([-7.3351, 108.3234], {
            color: '#4361ee',
            fillColor: '#4361ee',
            fillOpacity: 0.1,
            radius: 1000 // 1km dalam meter
        }).addTo(map).bindPopup("Area absensi 1km dari titik pusat");

        // Array untuk menyimpan semua marker
        const markers = [];

        // Tambahkan semua marker absensi dengan ikon berbeda untuk masuk dan pulang
        <?php foreach ($attendances as $attendance): ?>
            <?php if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])): ?>
                const markerIn = L.marker([<?php echo $attendance['latitude_in']; ?>, <?php echo $attendance['longitude_in']; ?>], {
                    icon: greenIcon
                }).addTo(map);
                
                markerIn.bindPopup(`
                    <div style="min-width: 200px;">
                        <h4 style="margin: 0 0 5px; color: #4361ee;">${escapeHtml('<?php echo $attendance['full_name']; ?>')}</h4>
                        <p style="margin: 0 0 5px;"><i class="fas fa-id-card" style="width: 15px;"></i> ${escapeHtml('<?php echo $attendance['nim']; ?>')}</p>
                        <p style="margin: 0 0 5px;"><i class="fas fa-calendar-day" style="width: 15px;"></i> ${escapeHtml('<?php echo date('d/m/Y', strtotime($attendance['date'])); ?>')}</p>
                        <p style="margin: 0 0 5px;"><i class="fas fa-sign-in-alt" style="color: #4ade80; width: 15px;"></i> <strong>Masuk:</strong> ${escapeHtml('<?php echo date('H:i', strtotime($attendance['check_in_time'])); ?>')}</p>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                            <button onclick="flyToMarker(${markers.length})" style="background: #4361ee; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-crosshairs"></i> Fokus
                            </button>
                            <a href="https://www.google.com/maps?q=<?php echo $attendance['latitude_in']; ?>,<?php echo $attendance['longitude_in']; ?>" target="_blank" style="background: #4ade80; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; text-decoration: none;">
                                <i class="fas fa-external-link-alt"></i> Google Maps
                            </a>
                        </div>
                    </div>
                `);
                
                markers.push(markerIn);
            <?php endif; ?>
            
            <?php if (!empty($attendance['latitude_out']) && !empty($attendance['longitude_out'])): ?>
                const markerOut = L.marker([<?php echo $attendance['latitude_out']; ?>, <?php echo $attendance['longitude_out']; ?>], {
                    icon: redIcon
                }).addTo(map);
                
                markerOut.bindPopup(`
                    <div style="min-width: 200px;">
                        <h4 style="margin: 0 0 5px; color: #4361ee;">${escapeHtml('<?php echo $attendance['full_name']; ?>')}</h4>
                        <p style="margin: 0 0 5px;"><i class="fas fa-id-card" style="width: 15px;"></i> ${escapeHtml('<?php echo $attendance['nim']; ?>')}</p>
                        <p style="margin: 0 0 5px;"><i class="fas fa-calendar-day" style="width: 15px;"></i> ${escapeHtml('<?php echo date('d/m/Y', strtotime($attendance['date'])); ?>')}</p>
                        <p style="margin: 0 0 5px;"><i class="fas fa-sign-out-alt" style="color: #f87171; width: 15px;"></i> <strong>Pulang:</strong> ${escapeHtml('<?php echo date('H:i', strtotime($attendance['check_out_time'])); ?>')}</p>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                            <button onclick="flyToMarker(${markers.length})" style="background: #4361ee; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-crosshairs"></i> Fokus
                            </button>
                            <a href="https://www.google.com/maps?q=<?php echo $attendance['latitude_out']; ?>,<?php echo $attendance['longitude_out']; ?>" target="_blank" style="background: #f87171; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; text-decoration: none;">
                                <i class="fas fa-external-link-alt"></i> Google Maps
                            </a>
                        </div>
                    </div>
                `);
                
                markers.push(markerOut);
            <?php endif; ?>
        <?php endforeach; ?>

        // Fungsi untuk menampilkan lokasi di peta dengan animasi
        function showOnMap(lat, lng, title) {
            map.flyTo([lat, lng], 17, {
                duration: 1,
                easeLinearity: 0.25
            });
            
            // Buka popup jika ada marker di lokasi tersebut
            markers.forEach(marker => {
                if (marker.getLatLng().lat === lat && marker.getLatLng().lng === lng) {
                    setTimeout(() => {
                        marker.openPopup();
                    }, 1000);
                }
            });
        }
        
        // Fungsi untuk focus ke marker tertentu
        function flyToMarker(index) {
            if (markers[index]) {
                const latLng = markers[index].getLatLng();
                map.flyTo(latLng, 17, {
                    duration: 1,
                    easeLinearity: 0.25
                });
                
                setTimeout(() => {
                    markers[index].openPopup();
                }, 1000);
            }
        }
        
        // Fungsi untuk escape HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Animasi saat scroll
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            const fadeInOnScroll = () => {
                fadeElements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementVisible = 150;
                    
                    if (elementTop < window.innerHeight - elementVisible) {
                        element.style.opacity = "1";
                        element.style.transform = "translateY(0)";
                    }
                });
            };
            
            // Set initial state
            fadeElements.forEach(element => {
                element.style.opacity = "0";
                element.style.transform = "translateY(20px)";
                element.style.transition = "opacity 0.6s ease, transform 0.6s ease";
            });
            
            // Run once on load
            fadeInOnScroll();
            
            // Then run on scroll
            window.addEventListener('scroll', fadeInOnScroll);
        });
        
    </script>
    
</body>
</html>