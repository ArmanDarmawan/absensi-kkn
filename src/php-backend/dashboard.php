<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Connect to database
$conn = connectDB();

if (isset($_POST['edit_attendance'])) {
    $attendance_id = $_POST['attendance_id'];
    $full_name = $_POST['full_name'];
    $nim = $_POST['nim'];
    $date = $_POST['date'];
    $prodi = $_POST['prodi'];
    $check_in_time = $_POST['check_in_time'];
    $check_out_time = $_POST['check_out_time'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

    // Update query with all editable fields
    $sql = "UPDATE public_attendance 
            SET full_name = ?, nim = ?, date = ?, prodi = ?, check_in_time = ?, check_out_time = ?, notes = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
      $stmt->bind_param("sssssssi", $full_name, $nim, $date, $prodi, $check_in_time, $check_out_time, $notes, $attendance_id);

    if ($stmt->execute()) {
        $success_message = "Data absensi berhasil diperbarui!";
    } else {
        $error_message = "Gagal memperbarui absensi: " . $stmt->error;
    }
}

// Handle verification
if (isset($_POST['verify']) && isset($_POST['attendance_id'])) {
    $attendance_id = $_POST['attendance_id'];
    $admin_id = $_SESSION['user_id'];

    $sql = "UPDATE public_attendance SET verified = TRUE, verified_by = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $attendance_id);

    if ($stmt->execute()) {
        $success_message = "Absensi berhasil diverifikasi!";
    } else {
        $error_message = "Gagal memverifikasi absensi: " . $stmt->error;
    }
}

// Handle deletion
if (isset($_POST['delete']) && isset($_POST['attendance_id'])) {
    $attendance_id = $_POST['attendance_id'];

    $sql = "DELETE FROM public_attendance WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $attendance_id);

    if ($stmt->execute()) {
        $success_message = "Absensi berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus absensi: " . $stmt->error;
    }
}

// Get admin name
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT full_name FROM users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$admin_name = $admin['full_name'];

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$verification_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get attendance records with date range filter
$sql = "SELECT * FROM public_attendance WHERE 1=1";
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

// Apply verification filter
if ($verification_filter === 'verified') {
    $sql .= " AND verified = TRUE";
} elseif ($verification_filter === 'unverified') {
    $sql .= " AND verified = FALSE";
}
$sql .= " ORDER BY date DESC, check_in_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$attendances = $result->fetch_all(MYSQLI_ASSOC);

// Get verifier names
$verifier_names = [];
if (!empty($attendances)) {
    $verified_ids = array_filter(array_column($attendances, 'verified_by'));

    if (!empty($verified_ids)) {
        $placeholders = str_repeat('?,', count($verified_ids) - 1) . '?';
        $verifier_sql = "SELECT id, full_name FROM users WHERE id IN ($placeholders)";
        $verifier_stmt = $conn->prepare($verifier_sql);

        $types = str_repeat('i', count($verified_ids));
        $verifier_stmt->bind_param($types, ...$verified_ids);
        $verifier_stmt->execute();
        $verifier_result = $verifier_stmt->get_result();

        while ($row = $verifier_result->fetch_assoc()) {
            $verifier_names[$row['id']] = $row['full_name'];
        }
    }
}
// Setelah session_start() dan sebelum koneksi DB
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    // Merge verifier names with attendance data
    $exportData = array_map(function($attendance) use ($verifier_names) {
        $attendance['verifier_name'] = $attendance['verified_by'] && isset($verifier_names[$attendance['verified_by']]) 
            ? $verifier_names[$attendance['verified_by']] 
            : null;
        return $attendance;
    }, $attendances);
    
    header('Content-Type: application/json');
    echo json_encode($exportData);
    exit;
}

// Export to PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once 'assets/vendor/tcpdf/tcpdf.php';

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistem Absensi');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Laporan Absensi ' . $date_filter);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $title = 'Laporan Absensi';
    if (!empty($date_from) && !empty($date_to)) {
        $title .= ' Periode ' . date('d-m-Y', strtotime($date_from)) . ' s/d ' . date('d-m-Y', strtotime($date_to));
    } elseif (!empty($date_from)) {
        $title .= ' Mulai ' . date('d-m-Y', strtotime($date_from));
    } elseif (!empty($date_to)) {
        $title .= ' Sampai ' . date('d-m-Y', strtotime($date_to));
    } else {
        $title .= ' Tanggal ' . date('d-m-Y', strtotime($date_filter));
    }
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(5);

    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(10, 7, 'No', 1, 0, 'C');
    $pdf->Cell(50, 7, 'Nama', 1, 0, 'C');
    $pdf->Cell(30, 7, 'NIM', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Program Studi', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Tanggal', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Jam Masuk', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Jam Pulang', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Durasi', 1, 0, 'C');
    $pdf->Cell(40, 7, $attendance['notes'] ? $attendance['notes'] : '-', 1, 0, 'L');
    $pdf->Cell(20, 7, 'Status', 1, 1, 'C');

    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $no = 1;
    foreach ($attendances as $attendance) {
        $duration = '-';
        if ($attendance['check_in_time'] && $attendance['check_out_time']) {
            $check_in = new DateTime($attendance['check_in_time']);
            $check_out = new DateTime($attendance['check_out_time']);
            $interval = $check_in->diff($check_out);
            $duration = $interval->format('%H:%I:%S');
        }

        $pdf->Cell(10, 7, $no, 1, 0, 'C');
        $pdf->Cell(50, 7, $attendance['full_name'], 1, 0, 'L');
        $pdf->Cell(30, 7, $attendance['nim'], 1, 0, 'L');
        $pdf->Cell(30, 7, $attendance['prodi'], 1, 0, 'L');
        $pdf->Cell(25, 7, date('d-m-Y', strtotime($attendance['date'])), 1, 0, 'C');
        $pdf->Cell(25, 7, $attendance['check_in_time'], 1, 0, 'C');
        $pdf->Cell(25, 7, $attendance['check_out_time'] ? $attendance['check_out_time'] : '-', 1, 0, 'C');
        $pdf->Cell(25, 7, $duration, 1, 0, 'C');
        $pdf->Cell(20, 7, $attendance['verified'] ? 'Terverifikasi' : 'Belum', 1, 1, 'C');
        $no++;
    }

    $filename = 'laporan_absensi';
    if (!empty($date_from) && !empty($date_to)) {
        $filename .= '_' . $date_from . '_to_' . $date_to;
    } elseif (!empty($date_from)) {
        $filename .= '_from_' . $date_from;
    } elseif (!empty($date_to)) {
        $filename .= '_to_' . $date_to;
    } else {
        $filename .= '_' . $date_filter;
    }
    $filename .= '.pdf';

    $pdf->Output($filename, 'D');
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="1041px-Unper.png">
    <title>Database Abensi</title>
    
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
                <h5 class="offcanvas-title" id="offcanvasSidebarLabel">Menu Dashboard</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"
                ></button>
            </div>
            <div class="offcanvas-body sidebar"> 
                <ul class="nav flex-column">
                    <li class="nav-item active">
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
                <h4 class="px-3 mb-3">Menu Navigasi</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
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
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 pt-3">
            <h3>Halo, <?php echo htmlspecialchars($admin_name); ?></h3>
            <h5>Verifikasi Absensi</h5>

            <?php if (isset($success_message)) : ?>
                <div class="alert alert-success">
                    <?= $success_message ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)) : ?>
                <div class="alert alert-danger">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-3 flex-wrap gap-2 filter-container">
                <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="d-flex gap-2 align-items-center">
                        <label for="date_from" class="form-label mb-0">Dari:</label>
                        <input
                            type="date"
                            name="date_from"
                            id="date_from"
                            class="form-control"
                            value="<?= htmlspecialchars($date_from) ?>"
                        />
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <label for="date_to" class="form-label mb-0">Sampai:</label>
                        <input
                            type="date"
                            name="date_to"
                            id="date_to"
                            class="form-control"
                            value="<?= htmlspecialchars($date_to) ?>"
                        />
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <label for="filter" class="form-label mb-0">Status:</label>
                        <select
                            name="filter"
                            id="filter"
                            class="form-select"
                        >
                            <option value="all" <?= $verification_filter === 'all' ? 'selected' : '' ?>>Semua</option>
                            <option value="verified" <?= $verification_filter === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                            <option value="unverified" <?= $verification_filter === 'unverified' ? 'selected' : '' ?>>Belum Terverifikasi</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
        <button type="button" id="exportPdfBtn" class="btn btn-secondary">
  <i class="fas fa-file-export"></i> Export PDF
</button>

                </form>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-primary text-center">
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>NIM</th>
                            <th>Tanggal</th>
                            <th>Prodi</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Durasi</th>
                            <th>Catatan</th>
                            <th>Status</th>
                            <th>Di Verifikasi Oleh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($attendances)) {
                            echo '<tr><td colspan="11" class="text-center">Data tidak ditemukan</td></tr>';
                        } else {
                            $no = 1;
                            foreach ($attendances as $attendance) {
                                $duration = '-';
                                if ($attendance['check_in_time'] && $attendance['check_out_time']) {
                                    $check_in = new DateTime($attendance['check_in_time']);
                                    $check_out = new DateTime($attendance['check_out_time']);
                                    $interval = $check_in->diff($check_out);
                                    $duration = $interval->format('%H:%I:%S');
                                }
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no ?></td>
                                    <td><?= htmlspecialchars($attendance['full_name']) ?></td>
                                    <td><?= htmlspecialchars($attendance['nim']) ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($attendance['date']))) ?></td>
                                    <td><?= htmlspecialchars($attendance['prodi']) ?></td>
                                    <td class="text-center"><?= $attendance['check_in_time'] ?></td>
                                    <td class="text-center"><?= $attendance['check_out_time'] ?: '-' ?></td>
                                    <td class="text-center"><?= $duration ?></td>
                                    <td><?= htmlspecialchars($attendance['notes'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <?php if ($attendance['verified']): ?>
                                            <span class="badge bg-success">Terverifikasi</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Belum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $attendance['verified_by'] && isset($verifier_names[$attendance['verified_by']]) ? htmlspecialchars($verifier_names[$attendance['verified_by']]) : '-' ?>
                                    </td>
                                    <td class="text-center">
                                        <button
                                            class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editModal<?= $attendance['id'] ?>"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form
                                            method="post"
                                            style="display:inline-block"
                                            onsubmit="return confirm('Hapus data absensi ini?');"
                                        >
                                            <input type="hidden" name="attendance_id" value="<?= $attendance['id'] ?>" />
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php if (!$attendance['verified']): ?>
                                            <form
                                                method="post"
                                                style="display:inline-block"
                                                onsubmit="return confirm('Verifikasi data absensi ini?');"
                                            >
                                                <input type="hidden" name="attendance_id" value="<?= $attendance['id'] ?>" />
                                                <button type="submit" name="verify" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div
                                    class="modal fade"
                                    id="editModal<?= $attendance['id'] ?>"
                                    tabindex="-1"
                                    aria-labelledby="editModalLabel<?= $attendance['id'] ?>"
                                    aria-hidden="true"
                                    data-bs-backdrop="static"
                                    data-bs-keyboard="false"
                                >
                                    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                                        <form method="post" action="">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editModalLabel<?= $attendance['id'] ?>">Edit Absensi</h5>
                                                    <button
                                                        type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Close"
                                                    ></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="attendance_id" value="<?= $attendance['id'] ?>" />
                                                    <div class="mb-3">
                                                        <label for="full_name<?= $attendance['id'] ?>" class="form-label">Nama Lengkap</label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="full_name<?= $attendance['id'] ?>"
                                                            name="full_name"
                                                            value="<?= htmlspecialchars($attendance['full_name']) ?>"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="nim<?= $attendance['id'] ?>" class="form-label">NIM</label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="nim<?= $attendance['id'] ?>"
                                                            name="nim"
                                                            value="<?= htmlspecialchars($attendance['nim']) ?>"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="date<?= $attendance['id'] ?>" class="form-label">Tanggal</label>
                                                        <input
                                                            type="date"
                                                            class="form-control"
                                                            id="date<?= $attendance['id'] ?>"
                                                            name="date"
                                                            value="<?= htmlspecialchars($attendance['date']) ?>"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="prodi<?= $attendance['id'] ?>" class="form-label">Program Studi</label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="prodi<?= $attendance['id'] ?>"
                                                            name="prodi"
                                                            value="<?= htmlspecialchars($attendance['prodi']) ?>"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="check_in_time<?= $attendance['id'] ?>" class="form-label">Jam Masuk</label>
                                                        <input
                                                            type="time"
                                                            class="form-control"
                                                            id="check_in_time<?= $attendance['id'] ?>"
                                                            name="check_in_time"
                                                            value="<?= date('H:i', strtotime($attendance['check_in_time'])) ?>"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="check_out_time<?= $attendance['id'] ?>" class="form-label">Jam Pulang</label>
                                                        <input
                                                            type="time"
                                                            class="form-control"
                                                            id="check_out_time<?= $attendance['id'] ?>"
                                                            name="check_out_time"
                                                            value="<?= $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : '' ?>"
                                                        />
                                                    </div>
                                                     <div class="mb-3">
                                                        <label for="notes<?= $attendance['id'] ?>" class="form-label">Catatan</label>
                                                        <textarea
                                                            class="form-control"
                                                            id="notes<?= $attendance['id'] ?>"
                                                            name="notes"
                                                            rows="3"><?= htmlspecialchars($attendance['notes'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer d-flex flex-wrap justify-content-center justify-content-sm-end gap-2">
                                                    <button type="submit" name="edit_attendance" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <?php
                                $no++;
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script>
    function resetFilter() {
        window.location.href = window.location.pathname;
    }
</script>
<script>
(function(){
  const exportButton = document.getElementById('exportPdfBtn');
  if (!exportButton) {
    console.error("Export PDF button not found!");
    return;
  }

  exportButton.addEventListener('click', async () => {
    exportButton.disabled = true;
    exportButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

    const params = new URLSearchParams(window.location.search);
    params.set('format', 'json'); // Ensure we fetch JSON data
    const apiUrl = `${window.location.pathname}?${params.toString()}`;

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });

        let currentY = 40; // Initial Y position for content

        // Variables for logo dimensions, to be set if logo loads
        let pdfLogoWidth = 0;
        const pdfLogoHeight = 20; // Desired height for the logo in the PDF
        let actualLoadedLogoObject = null; // Will store the loaded HTMLImageElement

        // --- Integrasi Logo ---
        try {
            const logoUrl = '1041px-Unper.png'; // Pastikan path ini benar
            const img = new Image();
            // img.crossOrigin = 'Anonymous'; // Perlu jika logo dari domain berbeda & CORS diatur

            const logoPromise = new Promise((resolve, reject) => {
                img.onload = () => resolve(img);
                img.onerror = (err) => {
                    console.warn("Logo tidak dapat dimuat. Melanjutkan tanpa logo.", err);
                    resolve(null); // Resolve dengan null jika logo gagal dimuat
                };
                img.src = logoUrl;
            });

            actualLoadedLogoObject = await logoPromise;

            if (actualLoadedLogoObject) {
                const aspectRatio = actualLoadedLogoObject.width / actualLoadedLogoObject.height;
                pdfLogoWidth = pdfLogoHeight * aspectRatio;
                // Jika logo dimuat, pdfLogoWidth akan > 0 (kecuali aspect ratio 0)
            }
        } catch (logoError) {
            console.warn("Error saat memproses logo:", logoError);
            actualLoadedLogoObject = null; // Pastikan null jika terjadi error
        }
        // --- Akhir Integrasi Logo ---

        // Set font untuk judul utama
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');

        const titleText = 'Laporan Absensi Mahasiswa';
        const titleFontSize = 16; // Sesuai dengan setFontSize(16)

        if (actualLoadedLogoObject && pdfLogoWidth > 0) {
            // Logo berhasil dimuat, gambar logo dan judul berdampingan
            const logoX = 40; // Margin kiri untuk logo
            const logoY = currentY; // Posisi Y untuk bagian atas logo

            // Hitung posisi X untuk teks judul, di sebelah kanan logo
            const titleX = logoX + pdfLogoWidth + 10; // Padding 10 point setelah logo

            // Hitung Posisi Y untuk baseline judul agar secara vertikal tengah dengan logo
            // Koordinat Y teks di jsPDF mengacu pada baseline teks.
            // Heuristik ini mencoba menyejajarkan pusat visual teks dengan pusat visual logo.
            const titleBaselineY = logoY + (pdfLogoHeight / 2) + (titleFontSize / 3.5);

            doc.addImage(actualLoadedLogoObject, 'PNG', logoX, logoY, pdfLogoWidth, pdfLogoHeight);
            doc.text(titleText, titleX, titleBaselineY);

            // Update currentY agar berada di bawah elemen yang lebih tinggi (logo atau perkiraan tinggi judul), ditambah padding
            currentY += Math.max(pdfLogoHeight, titleFontSize) + 10; // Tambah spasi 10 point setelahnya
        } else {
            // Logo tidak dimuat atau tidak memiliki lebar, cetak judul saja secara normal
            const titleX = 40; // Posisi X default untuk judul
            // Tempatkan baseline teks sehingga bagian utama teks muncul dekat currentY
            const titleBaselineY = currentY + titleFontSize * 0.8; // Sesuaikan baseline agar cap height dekat dengan currentY
            doc.text(titleText, titleX, titleBaselineY);

            // Update currentY agar berada di bawah judul, ditambah padding
            currentY = titleBaselineY + (titleFontSize * 0.2) + 10; // Efektif: currentY (atas) + titleFontSize + padding
        }

        // Set font untuk subjudul dan teks berikutnya
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');

        const dateFrom = params.get('date_from');
        const dateTo = params.get('date_to');
        const dateFilterPHP = params.get('date'); // Ini adalah $date_filter dari PHP jika ada
        
        let filterSubtitle = 'Filter Tanggal: ';
        if (dateFrom && dateTo) {
            filterSubtitle += `${formatDMY(dateFrom)} s/d ${formatDMY(dateTo)}`;
        } else if (dateFrom) {
            filterSubtitle += `Mulai ${formatDMY(dateFrom)}`;
        } else if (dateTo) {
            filterSubtitle += `Sampai ${formatDMY(dateTo)}`;
        } else if (dateFilterPHP) { // Jika filter tunggal dari PHP ada
            filterSubtitle += `${formatDMY(dateFilterPHP)}`;
        } else {
            filterSubtitle += `Hari Ini (${formatDMY(new Date().toISOString().slice(0,10))})`;
        }
        doc.text(filterSubtitle, 40, currentY);
        currentY += 15;

        const verificationFilter = params.get('filter') || 'all';
        let statusText = 'Status Verifikasi: ';
        if (verificationFilter === 'verified') statusText += 'Terverifikasi';
        else if (verificationFilter === 'unverified') statusText += 'Belum Terverifikasi';
        else statusText += 'Semua';
        doc.text(statusText, 40, currentY);
        currentY += 20;


        const resp = await fetch(apiUrl);
        if (!resp.ok) {
            throw new Error(`Gagal mengambil data: ${resp.statusText}`);
        }
        const data = await resp.json();

        const tableBody = data.map((item, idx) => {
            const dur = (item.check_in_time && item.check_out_time)
                ? getDuration(item.check_in_time, item.check_out_time)
                : '-';
            return [
                idx + 1,
                item.full_name || '-',
                item.nim || '-',
                item.date ? formatDMY(item.date) : '-',
                item.prodi || '-',
                item.check_in_time ? item.check_in_time.substring(0,8) : '-', // HH:MM:SS
                item.check_out_time ? item.check_out_time.substring(0,8) : '-', // HH:MM:SS
                dur,
                item.notes || '-',
                item.verified ? 'Terverifikasi' : 'Belum',
                item.verifier_name || '-'
            ];
        });

        doc.autoTable({
          startY: currentY,
          head: [['No','Nama','NIM','Tanggal','Prodi','Masuk','Pulang','Durasi','Catatan','Status','Diverifikasi Oleh']],
          body: tableBody,
          theme: 'grid', // 'striped', 'plain'
          styles: { fontSize: 8, cellPadding: 3, halign: 'left', valign: 'middle' },
          headStyles: { 
              fillColor: [22, 79, 130], // Warna biru tua (misalnya, Unper blue)
              textColor: 255, 
              fontStyle: 'bold', 
              halign: 'center',
              valign: 'middle',
              fontSize: 9
          },
          alternateRowStyles: { fillColor: [240, 245, 250] }, // Warna biru sangat muda untuk baris alternatif
          columnStyles: {
              0: { halign: 'center', cellWidth: 25 }, // No
              1: { cellWidth: 'auto' }, // Nama
              2: { halign: 'center', cellWidth: 60 }, // NIM
              3: { halign: 'center', cellWidth: 60 }, // Tanggal
              4: { cellWidth: 'auto' }, // Prodi
              5: { halign: 'center', cellWidth: 50 }, // Masuk
              6: { halign: 'center', cellWidth: 50 }, // Pulang
              7: { halign: 'center', cellWidth: 50 }, // Durasi
              8: { cellWidth: 100 }, // Catatan (beri sedikit lebar tetap)
              9: { halign: 'center', cellWidth: 65 }, // Status
              10: { halign: 'center', cellWidth: 'auto' } // Diverifikasi Oleh
          },
          margin: { top: 20, right: 30, bottom: 40, left: 30 }, // Margin autoTable
          didDrawPage: function (dataHook) {
            let footerStr = "Laporan Absensi Mahasiswa | Halaman " + doc.internal.getNumberOfPages();
            const genTime = `Dibuat pada: ${new Date().toLocaleString('id-ID')}`;
            doc.setFontSize(8);
            doc.setTextColor(100); // Abu-abu
            doc.text(footerStr, dataHook.settings.margin.left, doc.internal.pageSize.height - 25);
            doc.text(genTime, doc.internal.pageSize.width - dataHook.settings.margin.right - doc.getTextWidth(genTime), doc.internal.pageSize.height - 25);
          }
        });

        let filename = 'laporan_absensi';
        if (dateFrom && dateTo) {
            filename += `_${dateFrom}_sd_${dateTo}`;
        } else if (dateFrom) {
            filename += `_dari_${dateFrom}`;
        } else if (dateTo) {
            filename += `_sampai_${dateTo}`;
        } else if (dateFilterPHP) {
            filename += `_${dateFilterPHP}`;
        }
        if (verificationFilter && verificationFilter !== 'all') {
            filename += `_${verificationFilter}`;
        }
        filename += '.pdf';

        doc.save(filename);

    } catch (error) {
        console.error("Error generating PDF:", error);
        alert("Gagal membuat PDF: " + error.message);
    } finally {
        exportButton.disabled = false;
        exportButton.innerHTML = '<i class="fas fa-file-pdf"></i> Export PDF';
    }
  });

  function formatDMY(isoDateString) {
    if (!isoDateString || !isoDateString.includes('-')) return isoDateString;
    const parts = isoDateString.split('-');
    if (parts.length !== 3) return isoDateString;
    const [y,m,d] = parts;
    return `${d}-${m}-${y}`;
  }

  function getDuration(timeIn, timeOut) {
    if (!timeIn || !timeOut) return '-';
    try {
        const today = new Date().toISOString().slice(0,10); // Dapatkan tanggal hari ini sebagai basis
        const startDateTime = new Date(`${today}T${timeIn}`);
        let endDateTime = new Date(`${today}T${timeOut}`);

        if (isNaN(startDateTime.getTime()) || isNaN(endDateTime.getTime())) {
             return '-'; // Waktu tidak valid
        }
        // Jika waktu pulang melewati tengah malam (misal, shift malam)
        // Ini adalah asumsi sederhana; logika yang lebih kompleks mungkin diperlukan untuk kasus ekstrim
        if (endDateTime < startDateTime) {
            endDateTime.setDate(endDateTime.getDate() + 1); // Tambah satu hari ke waktu pulang
        }

        let diff = endDateTime.getTime() - startDateTime.getTime();
        if (diff < 0) return '-'; // Perbedaan negatif setelah penyesuaian, mungkin data salah

        const hours = Math.floor(diff / (1000 * 60 * 60));
        diff -= hours * (1000 * 60 * 60);
        const minutes = Math.floor(diff / (1000 * 60));
        diff -= minutes * (1000 * 60);
        const seconds = Math.floor(diff / 1000);

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    } catch (e) {
        console.warn("Error menghitung durasi:", timeIn, timeOut, e);
        return '-';
    }
  }
})();
</script>
<!-- jsPDF core -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<!-- jsPDF AutoTable plugin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

</body>
</html>