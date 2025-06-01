<?php
session_start();
require_once 'config.php'; // Make sure this file exists and connects to your DB

// Connect to database
$conn = connectDB(); // Assuming connectDB() is in config.php and returns a mysqli connection
date_default_timezone_set('Asia/Jakarta');

// Cek apakah user adalah admin
$is_admin = false; // This variable is declared but not used in this specific file's logic path.
                  // If it were to restrict data, the SQL query would need adjustment.
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $is_admin = true;
}

// Filter data
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$nim_filter = isset($_GET['nim']) ? trim($_GET['nim']) : '';

// Query untuk mendapatkan data absensi dengan koordinat
// Explicitly list columns for clarity and security
$sql = "SELECT id, nim, full_name, prodi, date, check_in_time, latitude_in, longitude_in, check_out_time, latitude_out, longitude_out FROM public_attendance WHERE 1=1";
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
$stmt->close();

// Jika request JSON
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($attendances); // For API usage, consider JSON_HEX_TAG etc. if data could be rendered in HTML by client
    $conn->close(); // Close connection for JSON response
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Lokasi Absensi - Sistem Absensi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        :root {
            --primary-color: #333333; /* Consider Bootstrap's default or theme colors */
            --secondary-color: #555555;
            --accent-color: #777777;
            --success-color: #198754; /* Bootstrap's success color */
            --danger-color: #dc3545;  /* Bootstrap's danger color */
            --warning-color: #ffc107; /* Bootstrap's warning color */
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
            color: var(--black); /* Changed to var(--dark-color) for better contrast on light bg */
            line-height: 1.6;
        }
        
        /* .container class is provided by Bootstrap, custom styles might conflict or be redundant */
        /* .main-content class not explicitly used, styles might be for general <main> or other elements */

        .sidebar {
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            height: calc(100vh - 0px); 
            padding-top: 1rem;
            overflow-x: hidden;
            overflow-y: auto; 
        }

        .sidebar .nav-link {
            font-weight: 500;
            color: var(--dark-color);
        }

        .sidebar .nav-link .fas, .sidebar .nav-link .far {
            margin-right: 0.5rem;
        }

        .sidebar .nav-link.active {
            color: var(--bs-primary); /* Using Bootstrap primary color variable */
            background-color: var(--gray-light);
            border-radius: 0.25rem;
        }
        
        /* .map-container class styles are good, but #map is styled directly with height. Consider consolidating. */
        #map {
            height: 100%; /* Takes height from parent, ensure parent (.card-body) has height or #map has fixed height */
            width: 100%;
        }
        
        .filter-container {
            background-color: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .filter-container h2 { 
            margin-top: 0;
            color: var(--dark-color);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* .form-group is a custom class; Bootstrap uses .mb-3 for margin. */
        /* .form-control is styled by Bootstrap; custom :focus styles are fine. */
        .form-control:focus {
            outline: none;
            border-color: var(--bs-primary); 
            box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.25); /* Using Bootstrap focus shadow */
        }
        
        /* .btn styling: Bootstrap .btn is quite comprehensive. Overriding needs care. */
        /* Your .btn-primary override changes Bootstrap's default. */
        .btn-primary { 
            background-color: var(--dark-color); /* Custom primary button color */
            color: var(--white);
            border-color: var(--dark-color);
        }
        
        .btn-primary:hover {
            background-color: var(--black); /* Darken custom primary */
            border-color: var(--black);
            transform: translateY(-2px);
        }
        
        /* .attendance-card is defined but HTML uses Bootstrap's .card. */
        .card { /* Adding transitions to Bootstrap cards */
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .footer {
            background-color: var(--black);
            color: var(--white);
            padding: 2rem 0;
            text-align: center;
            margin-top: 3rem;
        }
        
        @media (max-width: 767.98px) { 
            .filter-form .col-md-4 { /* Adjusted to col-md-4 used in form */
                flex: 0 0 100%;
                max-width: 100%;
            }
            .btn-group { 
                flex-direction: column;
            }
             .btn-group .btn + .btn {
                margin-top: 0.5rem;
                margin-left: 0;
            }
        }
        
        .fade-in {
            /* animation defined below is used via JS IntersectionObserver for better control */
        }
        
        @keyframes fadeInAnimation { /* Renamed to avoid conflict if JS uses "fadeIn" */
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .marker-in {
            background-color: var(--success-color); /* Bootstrap success green */
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--white);
            box-shadow: 0 0 0 2px var(--success-color);
        }
        
        .marker-out {
            background-color: var(--danger-color); /* Bootstrap danger red */
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--white);
            box-shadow: 0 0 0 2px var(--danger-color);
        }
        .marker-in i, .marker-out i {
            color: white;
            font-size: 10px;
        }
        
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

        .badge.bg-success, .badge.bg-danger { 
            color: white !important; /* Ensures text visibility */
        }
        .offcanvas-header {
            background-color: var(--light-color);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="d-md-none bg-light p-2 sticky-top shadow-sm"> 
            <button
                class="btn btn-outline-primary"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasSidebar"
                aria-controls="offcanvasSidebar"
            >
                <i class="fas fa-bars"></i> Menu
            </button>
        </div>

        <div
            class="offcanvas offcanvas-start d-md-none"
            tabindex="-1"
            id="offcanvasSidebar"
            aria-labelledby="offcanvasSidebarLabel"
        >
            <div class="offcanvas-header border-bottom"> 
                <h5 class="offcanvas-title" id="offcanvasSidebarLabel">Menu Navigasi</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"
                ></button>
            </div>
            <div class="offcanvas-body sidebar"> 
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link"
                            ><i class="fas fa-home"></i> Dashboard</a
                        >
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="photos.php">
                            <i class="fas fa-images"></i> Foto Absensi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="attendance_map.php"> 
                            <i class="fas fa-map-marked-alt"></i> Lokasi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link"
                            ><i class="fas fa-sign-out-alt"></i> Logout</a
                        >
                    </li>
                </ul>
            </div>
        </div>

        <nav class="col-md-2 d-none d-md-block bg-light sidebar shadow-sm"> 
            <div class="position-sticky pt-3">
                <h4 class="px-3 mb-3">Menu Navigasi</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="photos.php">
                            <i class="fas fa-images"></i> Foto Absensi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="attendance_map.php">
                            <i class="fas fa-map-marked-alt"></i> Lokasi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    
    <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 py-4 px-3"> 
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-map-marked-alt"></i> Data Lokasi Absensi</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                 <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">
                     <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                 </a>
            </div>
        </div>
            <div class="filter-container mb-4">
                <form method="get" class="row g-3 align-items-end filter-form">
                    <div class="col-md-4"> 
                        <label for="date" class="form-label"><i class="far fa-calendar-alt"></i> Tanggal</label>
                        <input type="text" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div class="col-md-4"> 
                        <label for="nim" class="form-label"><i class="fas fa-id-card"></i> NIM</label>
                        <input type="text" class="form-control" id="nim" name="nim" value="<?php echo htmlspecialchars($nim_filter); ?>" placeholder="Masukkan NIM">
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex flex-column flex-sm-row">
                            <button type="submit" class="btn btn-primary w-100 me-sm-2 mb-2 mb-sm-0">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="attendance_map.php" class="btn btn-secondary w-100">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Ditemukan <?= count($attendances) ?> data absensi.
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Total Absensi</h5>
                            <p class="card-text fs-2 fw-bold"><?php echo count($attendances); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Tanggal Terpilih</h5>
                            <p class="card-text fs-2 fw-bold"><?php echo !empty($date_filter) ? date('d M Y', strtotime($date_filter)) : (count($attendances) > 0 && isset($attendances[0]['date']) ? date('d M Y', strtotime($attendances[0]['date'])) : '-'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Check-in Terawal</h5>
                            <p class="card-text fs-2 fw-bold">
                                <?php 
                                if (count($attendances) > 0) {
                                    $earliestTime = null;
                                    foreach ($attendances as $att) {
                                        if (!empty($att['check_in_time'])) {
                                            if (is_null($earliestTime) || strtotime($att['check_in_time']) < strtotime($earliestTime)) {
                                                $earliestTime = $att['check_in_time'];
                                            }
                                        }
                                    }
                                    echo $earliestTime ? date('H:i', strtotime($earliestTime)) : '-';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Check-in Terakhir</h5>
                            <p class="card-text fs-2 fw-bold">
                                <?php 
                                if (count($attendances) > 0) {
                                    $latestTime = null;
                                    foreach ($attendances as $att) {
                                         if (!empty($att['check_in_time'])) { 
                                            if (is_null($latestTime) || strtotime($att['check_in_time']) > strtotime($latestTime)) {
                                                $latestTime = $att['check_in_time'];
                                            }
                                        }
                                    }
                                    echo $latestTime ? date('H:i', strtotime($latestTime)) : '-';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-map-marked-alt"></i> Peta Lokasi Absensi
                </div>
                <div class="card-body p-0" style="height: 500px;"> 
                    <div id="map" style="border-radius: 0 0 .375rem .375rem;"></div> 
                </div>
            </div>

            <h3 class="mb-3"><i class="fas fa-list-ul"></i> Daftar Detail Absensi</h3>
            
            <?php if (count($attendances) > 0): ?>
                <div class="row">
                    <?php foreach ($attendances as $attendance): ?>
                        <div class="col-md-6 col-lg-4 mb-4 fade-in">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white"> 
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($attendance['full_name']); ?></h5>
                                    <small><?php echo htmlspecialchars($attendance['nim']); ?> - <?php echo htmlspecialchars($attendance['prodi']); ?></small>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><i class="far fa-calendar text-primary"></i> <strong>Tanggal:</strong> <?php echo date('d F Y', strtotime($attendance['date'])); ?></p>
                                    <p class="card-text">
                                        <i class="far fa-clock text-primary"></i> <strong>Waktu:</strong> 
                                        <?php if (!empty($attendance['check_in_time'])): ?>
                                            <span class="badge bg-success">
                                                Masuk: <?php echo date('H:i', strtotime($attendance['check_in_time'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($attendance['check_out_time'])): ?>
                                            <span class="badge bg-danger ms-1">
                                                Pulang: <?php echo date('H:i', strtotime($attendance['check_out_time'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="mt-3 p-3 bg-light rounded border">
                                        <h6><i class="fas fa-map-marker-alt text-primary"></i> Lokasi Absensi</h6>
                                        <?php if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span><i class="fas fa-sign-in-alt text-success"></i> <strong>Masuk:</strong></span>
                                                <button class="btn btn-sm btn-outline-primary" onclick="focusOnMap(<?php echo $attendance['latitude_in']; ?>, <?php echo $attendance['longitude_in']; ?>, <?php echo htmlspecialchars(json_encode($attendance['full_name'] . ' (Masuk)'), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="fas fa-map-pin"></i> Lihat
                                                </button>
                                            </div>
                                            <p class="small text-muted mb-2 fst-italic"><?php echo htmlspecialchars($attendance['latitude_in']); ?>, <?php echo htmlspecialchars($attendance['longitude_in']); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($attendance['latitude_out']) && !empty($attendance['longitude_out'])): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span><i class="fas fa-sign-out-alt text-danger"></i> <strong>Pulang:</strong></span>
                                                <button class="btn btn-sm btn-outline-primary" onclick="focusOnMap(<?php echo $attendance['latitude_out']; ?>, <?php echo $attendance['longitude_out']; ?>, <?php echo htmlspecialchars(json_encode($attendance['full_name'] . ' (Pulang)'), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="fas fa-map-pin"></i> Lihat
                                                </button>
                                            </div>
                                            <p class="small text-muted fst-italic"><?php echo htmlspecialchars($attendance['latitude_out']); ?>, <?php echo htmlspecialchars($attendance['longitude_out']); ?></p>
                                        <?php endif; ?>
                                         <?php if (empty($attendance['latitude_in']) && empty($attendance['longitude_in']) && empty($attendance['latitude_out']) && empty($attendance['longitude_out'])): ?>
                                            <p class="small text-muted fst-italic">Data lokasi tidak tersedia.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning empty-state">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h3>Tidak Ada Data</h3>
                    <p>Tidak ada data absensi yang ditemukan. Silakan coba dengan filter yang berbeda atau tanggal lain.</p>
                     <a href="attendance_map.php" class="btn btn-primary">
                         <i class="fas fa-sync-alt"></i> Muat Ulang Data
                     </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</div>

    <footer class="footer">
        <div class="container">
            <p>© <?php echo date('Y'); ?> Sistem Absensi KKN Cikondang. All rights reserved.</p>
        </div>
    </footer>


    <script>
        flatpickr("#date", {
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo htmlspecialchars($date_filter, ENT_QUOTES, 'UTF-8'); ?>"
        });

        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') {
                return unsafe; 
            }
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        const markerIconIn = L.divIcon({
            className: 'marker-in',
            html: '<i class="fas fa-sign-in-alt"></i>',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });

        const markerIconOut = L.divIcon({
            className: 'marker-out',
            html: '<i class="fas fa-sign-out-alt"></i>',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });

        const map = L.map('map').setView([-7.4236961, 108.3434041], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const mainLocationIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        L.marker([-7.4236961, 108.3434041], { icon: mainLocationIcon }).addTo(map)
            .bindPopup('<b>Desa Cikondang</b><br>Titik pusat area absensi.')
            .openPopup();

        L.circle([-7.4236961, 108.3434041], {
            color: 'var(--bs-primary)', // Using Bootstrap primary via CSS var
            fillColor: 'var(--bs-primary)',
            fillOpacity: 0.1,
            radius: 1150 
        }).addTo(map).bindPopup("Area absensi yang diizinkan (radius ~1.15km)");

        const allMarkers = []; 

        <?php foreach ($attendances as $idx => $attendance): ?>
            <?php
            // JSON_HEX_TAG and JSON_HEX_APOS are crucial for safety when embedding in <script> that generates HTML.
            // Though we use escapeHtml client-side too, defense in depth is good.
            $fullNameJs = json_encode($attendance['full_name'], JSON_HEX_TAG | JSON_HEX_APOS);
            $nimJs = json_encode($attendance['nim'], JSON_HEX_TAG | JSON_HEX_APOS);
            $prodiJs = json_encode($attendance['prodi'], JSON_HEX_TAG | JSON_HEX_APOS); // Added Prodi
            $dateJs = json_encode(date('d/m/Y', strtotime($attendance['date'])), JSON_HEX_TAG | JSON_HEX_APOS);
            $checkInTimeJs = !empty($attendance['check_in_time']) ? json_encode(date('H:i', strtotime($attendance['check_in_time'])), JSON_HEX_TAG | JSON_HEX_APOS) : 'null';
            $checkOutTimeJs = !empty($attendance['check_out_time']) ? json_encode(date('H:i', strtotime($attendance['check_out_time'])), JSON_HEX_TAG | JSON_HEX_APOS) : 'null';
            ?>

            <?php if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])): ?>
                (function() { 
                    const lat = <?php echo $attendance['latitude_in']; ?>;
                    const lng = <?php echo $attendance['longitude_in']; ?>;
                    const name = <?php echo $fullNameJs; ?>;
                    const nim = <?php echo $nimJs; ?>;
                    const prodi = <?php echo $prodiJs; ?>;
                    const dateStr = <?php echo $dateJs; ?>;
                    const checkInTime = <?php echo $checkInTimeJs; ?>;

                    const markerIn = L.marker([lat, lng], { icon: markerIconIn }).addTo(map);
                    let popupContentIn = `
                        <div style="min-width: 230px; font-size: 0.9rem;">
                            <h5 style="margin: 0 0 8px; color: var(--bs-primary); font-size: 1.1rem;">${escapeHtml(name)}</h5>
                            <p style="margin: 3px 0;"><i class="fas fa-id-card" style="width: 18px; color: #6c757d;"></i> ${escapeHtml(nim)} (${escapeHtml(prodi)})</p>
                            <p style="margin: 3px 0;"><i class="fas fa-calendar-day" style="width: 18px; color: #6c757d;"></i> ${escapeHtml(dateStr)}</p>
                            <p style="margin: 3px 0;"><i class="fas fa-sign-in-alt" style="color: var(--success-color); width: 18px;"></i> <strong>Masuk:</strong> ${checkInTime ? escapeHtml(checkInTime) : '-'}</p>
                            <p style="margin: 3px 0; font-size: 0.8rem; color: #6c757d;"><i class="fas fa-map-marker-alt" style="width: 18px;"></i> ${lat}, ${lng}</p>
                            <div style="display: flex; justify-content: space-between; margin-top: 10px; gap: 5px;">
                                <button onclick="focusOnMap(${lat}, ${lng}, escapeHtml(name) + ' (Masuk)')" class="btn btn-sm btn-primary" style="font-size: 0.8rem;">
                                    <i class="fas fa-crosshairs"></i> Fokus
                                </button>
                                <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="btn btn-sm btn-success" style="font-size: 0.8rem;">
                                    <i class="fab fa-google"></i> Google Maps
                                </a>
                            </div>
                        </div>
                    `;
                    markerIn.bindPopup(popupContentIn);
                    allMarkers.push(markerIn);
                })();
            <?php endif; ?>
            
            <?php if (!empty($attendance['latitude_out']) && !empty($attendance['longitude_out'])): ?>
                (function() { 
                    const lat = <?php echo $attendance['latitude_out']; ?>;
                    const lng = <?php echo $attendance['longitude_out']; ?>;
                    const name = <?php echo $fullNameJs; ?>;
                    const nim = <?php echo $nimJs; ?>;
                    const prodi = <?php echo $prodiJs; ?>;
                    const dateStr = <?php echo $dateJs; ?>;
                    const checkOutTime = <?php echo $checkOutTimeJs; ?>;

                    const markerOut = L.marker([lat, lng], { icon: markerIconOut }).addTo(map);
                    let popupContentOut = `
                        <div style="min-width: 230px; font-size: 0.9rem;">
                            <h5 style="margin: 0 0 8px; color: var(--bs-primary); font-size: 1.1rem;">${escapeHtml(name)}</h5>
                            <p style="margin: 3px 0;"><i class="fas fa-id-card" style="width: 18px; color: #6c757d;"></i> ${escapeHtml(nim)} (${escapeHtml(prodi)})</p>
                            <p style="margin: 3px 0;"><i class="fas fa-calendar-day" style="width: 18px; color: #6c757d;"></i> ${escapeHtml(dateStr)}</p>
                            <p style="margin: 3px 0;"><i class="fas fa-sign-out-alt" style="color: var(--danger-color); width: 18px;"></i> <strong>Pulang:</strong> ${checkOutTime ? escapeHtml(checkOutTime) : '-'}</p>
                             <p style="margin: 3px 0; font-size: 0.8rem; color: #6c757d;"><i class="fas fa-map-marker-alt" style="width: 18px;"></i> ${lat}, ${lng}</p>
                            <div style="display: flex; justify-content: space-between; margin-top: 10px; gap: 5px;">
                                 <button onclick="focusOnMap(${lat}, ${lng}, escapeHtml(name) + ' (Pulang)')" class="btn btn-sm btn-primary" style="font-size: 0.8rem;">
                                     <i class="fas fa-crosshairs"></i> Fokus
                                 </button>
                                 <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="btn btn-sm btn-danger" style="font-size: 0.8rem;"> {/* Matched button style with marker */}
                                     <i class="fab fa-google"></i> Google Maps
                                 </a>
                            </div>
                        </div>
                    `;
                    markerOut.bindPopup(popupContentOut);
                    allMarkers.push(markerOut);
                })();
            <?php endif; ?>
        <?php endforeach; ?>

        // Optional: Fit map to markers if any exist and are not too widespread.
        // if (allMarkers.length > 0) {
        //     const group = new L.featureGroup(allMarkers);
        //     // Add the main Cikondang marker to the group if you always want it included in bounds
        //     // group.addLayer(L.marker([-7.4236961, 108.3434041])); 
        //     map.fitBounds(group.getBounds().pad(0.3)); 
        // } else {
        //     map.setView([-7.4236961, 108.3434041], 15);
        // }


        function focusOnMap(lat, lng, title) {
            map.flyTo([lat, lng], 17, { 
                duration: 1.5, 
                easeLinearity: 0.25
            });
            
            // Create a temporary, simple popup when focusing from the card list.
            // The detailed popups are already bound to the markers themselves.
            L.popup()
              .setLatLng([lat, lng])
              .setContent(title ? `<strong>${escapeHtml(title)}</strong><br><small>${lat}, ${lng}</small>` : `${lat}, ${lng}`)
              .openOn(map);
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = "1";
                            entry.target.style.transform = "translateY(0)";
                            observer.unobserve(entry.target); 
                        }
                    });
                }, { threshold: 0.1 });

                fadeElements.forEach(element => {
                    element.style.opacity = "0";
                    element.style.transform = "translateY(20px)";
                    element.style.transition = "opacity 0.6s ease-out, transform 0.6s ease-out";
                    observer.observe(element);
                });
            } else { // Fallback for older browsers
                fadeElements.forEach(element => {
                    element.style.opacity = "1";
                    element.style.transform = "translateY(0)";
                });
            }

            // Initialize Bootstrap Offcanvas
            var offcanvasElementList = [].slice.call(document.querySelectorAll('.offcanvas'));
            offcanvasElementList.map(function (offcanvasEl) {
                return new bootstrap.Offcanvas(offcanvasEl);
            });
        });
        
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close(); // Close the database connection at the very end
?>