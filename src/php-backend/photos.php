<?php
session_start();
require_once 'config.php';
date_default_timezone_set('Asia/Jakarta');
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Connect to database
$conn = connectDB();
// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    
    // Delete the record
    $delete_stmt = $conn->prepare("DELETE FROM public_attendance WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();
    
    // Redirect to avoid resubmission
    header("Location: photos.php?deleted=1");
    exit();
}

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$name_filter = isset($_GET['name']) ? $_GET['name'] : '';
$nim_filter = isset($_GET['nim']) ? $_GET['nim'] : '';

// Get attendance records with photo
$sql = "SELECT id, full_name, nim, date, check_out_time, photo FROM public_attendance WHERE photo IS NOT NULL";
$params = [];
$types = "";

// Apply date range filter if both dates are provided
if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";
} 
// Apply single date filter if only one date is provided
elseif (!empty($date_from)) {
    $sql .= " AND date >= ?";
    $params[] = $date_from;
    $types .= "s";
} 
elseif (!empty($date_to)) {
    $sql .= " AND date <= ?";
    $params[] = $date_to;
    $types .= "s";
} 
// Default to today's date if no range is specified
else {
    $sql .= " AND date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

// Apply name filter
if (!empty($name_filter)) {
    $sql .= " AND full_name LIKE ?";
    $params[] = "%$name_filter%";
    $types .= "s";
}

// Apply NIM filter
if (!empty($nim_filter)) {
    $sql .= " AND nim LIKE ?";
    $params[] = "%$nim_filter%";
    $types .= "s";
}

$sql .= " ORDER BY date DESC, full_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$attendances = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foto Absensi - Sistem Absensi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .photo-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .photo-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .photo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .photo-img-container {
            height: 250px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
        }
        .photo-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .photo-info {
            padding: 15px;
            background: white;
        }
        .photo-info h5 {
            margin-bottom: 5px;
            color: #333;
        }
        .photo-info p {
            margin: 3px 0;
            color: #666;
            font-size: 0.9rem;
        }
        .filter-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-container .form-group {
            margin-bottom: 10px;
        }
        .modal-img {
            max-width: 100%;
            max-height: 80vh;
        }
        @media (max-width: 768px) {
            .photo-container {
                grid-template-columns: 1fr;
            }
            .filter-container {
                flex-direction: column;
            }
        }
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
                <h5 class="offcanvas-title" id="offcanvasSidebarLabel">Menu Photo</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"
                ></button>
            </div>
            <div class="offcanvas-body sidebar"> 
                <ul class="nav flex-column">
                    <li class="nav-item ">
                        <a href="dashboard.php" class="nav-link"
                            ><i class="fas fa-home"></i> Dashboard</a
                        >
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="photos.php">
                            <i class="fas fa-images"></i> Foto Absensi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link " href="attendance_map.php"> 
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
                <h4 class="px-3 mb-3">Menu Photo</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link " href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="photos.php">
                            <i class="fas fa-images"></i> Foto Absensi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link " href="attendance_map.php">
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

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h2><i class="fas fa-images"></i> Foto Absensi</h2>
                
                <!-- Filter section -->
                <div class="filter-container mb-4">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Dari Tanggal</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="name" class="form-label">Nama</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name_filter) ?>" placeholder="Cari nama...">
                        </div>
                        <div class="col-md-3">
                            <label for="nim" class="form-label">NIM</label>
                            <input type="text" class="form-control" id="nim" name="nim" value="<?= htmlspecialchars($nim_filter) ?>" placeholder="Cari NIM...">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="photos.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Results count -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Ditemukan <?= count($attendances) ?> foto absensi
                </div>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-trash"></i> Foto berhasil dihapus.
                    </div>
                <?php endif; ?>


                <!-- Photos grid -->
                <?php if (empty($attendances)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Tidak ada foto absensi yang ditemukan
                    </div>
                <?php else: ?>
                    <div class="photo-container">
                        <?php foreach ($attendances as $attendance): ?>
                            <div class="photo-card">
                                <div class="photo-img-container">
                                    <img src="<?= htmlspecialchars($attendance['photo']) ?>" 
                                         alt="Foto absensi <?= htmlspecialchars($attendance['full_name']) ?>" 
                                         class="photo-img"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#photoModal<?= $attendance['id'] ?>">
                                </div>
                                <div class="photo-info">
                                    <h5><?= htmlspecialchars($attendance['full_name']) ?></h5>
                                    <p><strong>NIM:</strong> <?= htmlspecialchars($attendance['nim']) ?></p>
                                    <p><strong>Tanggal & Jam:</strong> <?= date('d-m-Y ', strtotime($attendance['date'])) ?> </p>
                                    <?php if (!empty($attendance['check_out_time'])): ?>
                                    <p><strong>Check Out:</strong> <?= date('H:i', strtotime($attendance['check_out_time'])) ?> WIB</p>
                                <?php else: ?>
                                    <p><strong>Check Out:</strong> <span class="text-muted">Belum checkout</span></p>
                                <?php endif; ?>

                                    <p>
                                        <a href="<?= htmlspecialchars($attendance['photo']) ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Buka di Tab Baru
                                        </a>
                                    </p>
                                </div>
                            </div>

                            <!-- Modal for enlarged photo -->
                            <div class="modal fade" id="photoModal<?= $attendance['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Foto Absensi - <?= htmlspecialchars($attendance['full_name']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?= htmlspecialchars($attendance['photo']) ?>" 
                                                 class="modal-img" 
                                                 alt="Foto absensi <?= htmlspecialchars($attendance['full_name']) ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <div class="me-auto">
                                                <p class="mb-1"><strong>NIM:</strong> <?= htmlspecialchars($attendance['nim']) ?></p>
                                                <p class="mb-1"><strong>Tanggal:</strong> <?= date('d-m-Y', strtotime($attendance['date'])) ?> </p>
                                                <?php if (!empty($attendance['check_out_time'])): ?>
                                                <p class="mb-1"><strong>Check Out:</strong> <?= date('H:i', strtotime($attendance['check_out_time'])) ?> WIB</p>
                                            <?php else: ?>
                                                <p class="mb-1 text-muted"><strong>Check Out:</strong> Belum checkout</p>
                                            <?php endif; ?>
                                            </div>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus foto ini?');">
                                            <input type="hidden" name="delete_id" value="<?= $attendance['id'] ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </button>
                                        </form>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="fas fa-times"></i> Tutup
                                            </button>
                                            <a href="<?= htmlspecialchars($attendance['photo']) ?>" 
                                            download="Absensi_<?= preg_replace('/\s+/', '_', htmlspecialchars($attendance['full_name'])) ?>_<?= date('Ymd_His', strtotime($attendance['date'])) ?>.jpg" 
                                            class="btn btn-primary">
                                                <i class="fas fa-download"></i> Unduh
                                            </a>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-close alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 1s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 1000);
                }, 5000);
            });
        });
    </script>
</body>
</html>