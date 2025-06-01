<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Connect to database
$conn = connectDB();

// Handle verification
if (isset($_POST['verify'])) {
    if (!isset($_POST['attendance_id'])) {
        $error_message = "ID Absensi tidak valid";
    } else {
        $attendance_id = $_POST['attendance_id'];
        $admin_id = $_SESSION['user_id'];
        $current_time = date('Y-m-d H:i:s');
        
     $sql = "SELECT pa.id, pa.full_name, pa.nim, pa.prodi, pa.check_in_time, pa.check_out_time, 
               pa.verified, pa.verified_by, pa.verified_at, pa.notes
        FROM public_attendance pa
        WHERE DATE(pa.check_in_time) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $admin_id, $current_time, $attendance_id);

        if ($stmt->execute()) {
            $success_message = "Absensi berhasil diverifikasi!";
        } else {
            $error_message = "Gagal memverifikasi absensi: " . $stmt->error;
        }
    }
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT full_name, email FROM users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Get attendance records
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$verification_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

if ($verification_filter === 'verified') {
    $sql .= " AND pa.verified = TRUE";
} elseif ($verification_filter === 'unverified') {
    $sql .= " AND pa.verified = FALSE";
}

$sql .= " ORDER BY pa.check_in_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date_filter);
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

// Export to PDF
if (isset($_GET['export'])) {
    if ($_GET['export'] === 'pdf') {
        require_once 'php-backend/assets/vendor/tcpdf/tcpdf.php';
        
        // Create new PDF document
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Sistem Absensi');
        $pdf->SetAuthor($admin['full_name']);
        $pdf->SetTitle('Laporan Absensi ' . $date_filter);
        $pdf->SetSubject('Laporan Absensi');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, 'Laporan Absensi', 'Dibuat oleh: ' . $admin['full_name'] . ' (' . $admin['email'] . ')');
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Set margins
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Add a page
        $pdf->AddPage();
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'LAPORAN ABSENSI', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 7, 'Tanggal: ' . date('d F Y', strtotime($date_filter)), 0, 1, 'C');
        $pdf->Cell(0, 7, 'Status: ' . ($verification_filter === 'all' ? 'Semua Data' : ucfirst($verification_filter)), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Column widths - adjusted to accommodate notes properly
        $w = array(10, 30, 25, 35, 20, 20, 25, 25, 25, 35); // Made notes column wider (35mm)
        
        // Header
        $header = array('No', 'Nama', 'NIM', 'Program Studi', 'Masuk', 'Pulang', 'Status', 'Verifikasi', 'Oleh', 'Keterangan');
        
        // Colors, line width and bold font
        $pdf->SetFillColor(57, 106, 177);
        $pdf->SetTextColor(255);
        $pdf->SetDrawColor(57, 106, 177);
        $pdf->SetLineWidth(0.3);
        
        // Header
        for($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Data
        $fill = false;
        $no = 1;
        foreach ($attendances as $attendance) {
            // Set fill color for rows
            $pdf->SetFillColor($fill ? 224 : 255, $fill ? 235 : 255, $fill ? 255 : 255);
            $pdf->SetTextColor(0);
            $pdf->SetFont('helvetica', '', 9);
            
            // No
            $pdf->Cell($w[0], 6, $no, 'LR', 0, 'C', $fill);
            
            // Nama
            $pdf->Cell($w[1], 6, $attendance['full_name'], 'LR', 0, 'L', $fill);
            
            // NIM
            $pdf->Cell($w[2], 6, $attendance['nim'], 'LR', 0, 'C', $fill);
            
            // Program Studi
            $pdf->Cell($w[3], 6, $attendance['prodi'], 'LR', 0, 'L', $fill);
            
            // Jam Masuk
            $check_in = $attendance['check_in_time'] ? date('H:i', strtotime($attendance['check_in_time'])) : '-';
            $pdf->Cell($w[4], 6, $check_in, 'LR', 0, 'C', $fill);
            
            // Jam Pulang
            $check_out = $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : '-';
            $pdf->Cell($w[5], 6, $check_out, 'LR', 0, 'C', $fill);
            
            // Status (Hadir/Sakit/Izin)
            $status = $attendance['verified'] ? 'Terverifikasi' : 'Belum';
            $pdf->Cell($w[6], 6, $status, 'LR', 0, 'C', $fill);
            
            // Status Verifikasi
            $verified_status = $attendance['verified'] ? 'Ya' : 'Tidak';
            $pdf->Cell($w[7], 6, $verified_status, 'LR', 0, 'C', $fill);
            
            // Diverifikasi Oleh
            $verified_by = $attendance['verified'] && isset($verifier_names[$attendance['verified_by']]) ? 
                          $verifier_names[$attendance['verified_by']] : '-';
            $pdf->Cell($w[8], 6, $verified_by, 'LR', 0, 'L', $fill);
            
            // Keterangan (Notes from database)
            $notes = isset($attendance['notes']) && !empty($attendance['notes']) ? $attendance['notes'] : '-';
            $pdf->Cell($w[9], 6, $notes, 'LR', 0, 'L', $fill);
            
            $pdf->Ln();
            $fill = !$fill;
            $no++;
        }
        
        // Closing line
        $pdf->Cell(array_sum($w), 0, '', 'T');
        
        // Add legend/footer note
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Ln(5);
        $pdf->Cell(0, 5, 'Keterangan:', 0, 1);
        $pdf->Cell(0, 5, '- Hadir: Kehadiran normal', 0, 1);
        $pdf->Cell(0, 5, '- Sakit: Wajib melampirkan surat dokter', 0, 1);
        $pdf->Cell(0, 5, '- Izin: Wajib melampirkan surat izin', 0, 1);
        
        // Output PDF
        $pdf->Output('laporan_absensi_' . $date_filter . '.pdf', 'D');
        exit();
    }
}
// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="1041px-Unper.png">
    <title>Database Abensi</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
    <style>
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <div class="sidebar-header text-center text-white mb-4">
                        <div class="sidebar-avatar mb-3">
                            <i class="fas fa-user-circle fa-4x"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($admin['full_name']); ?></h4>
                        <small>Administrator</small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-calendar mr-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_attendance.php">
                                <i class="fas fa-clipboard-check mr-2"></i>
                                Verifikasi Absensi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-file-alt mr-2"></i>
                                Laporan Absensi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_users.php">
                                <i class="fas fa-users mr-2"></i>
                                Kelola Pengguna
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Verifikasi Absensi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="admin_attendance.php?export=pdf&date=<?php echo $date_filter; ?>&filter=<?php echo $verification_filter; ?>" class="btn btn-danger">
                            <i class="fas fa-file-pdf mr-1"></i> Export PDF
                        </a>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-filter mr-2"></i> Filter Data
                    </div>
                    <div class="card-body">
                        <form method="get" action="">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="date">Tanggal</label>
                                    <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="filter">Status Verifikasi</label>
                                    <select class="form-control" id="filter" name="filter">
                                        <option value="all" <?php echo $verification_filter === 'all' ? 'selected' : ''; ?>>Semua Data</option>
                                        <option value="verified" <?php echo $verification_filter === 'verified' ? 'selected' : ''; ?>>Terverifikasi</option>
                                        <option value="unverified" <?php echo $verification_filter === 'unverified' ? 'selected' : ''; ?>>Belum Terverifikasi</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter mr-2"></i> Terapkan Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-list mr-2"></i> Daftar Absensi - <?php echo date('d F Y', strtotime($date_filter)); ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendances)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>Tidak ada data absensi</h4>
                                <p>Belum ada data absensi untuk tanggal yang dipilih.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>No</th>
                                            <th>Nama</th>
                                            <th>NIM</th>
                                            <th>Program Studi</th>
                                            <th>Jam Masuk</th>
                                            <th>Jam Pulang</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($attendances as $attendance): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($attendance['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($attendance['nim']); ?></td>
                                                <td><?php echo htmlspecialchars($attendance['prodi']); ?></td>
                                                <td><?php echo $attendance['check_in_time']; ?></td>
                                                <td><?php echo $attendance['check_out_time'] ? $attendance['check_out_time'] : '-'; ?></td>
                                                <td>
                                                    <?php if ($attendance['verified']): ?>
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-check mr-1"></i> Terverifikasi
                                                            <?php if (isset($verifier_names[$attendance['verified_by']])): ?>
                                                                <br><small>oleh <?php echo htmlspecialchars($verifier_names[$attendance['verified_by']]); ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">
                                                            <i class="fas fa-clock mr-1"></i> Belum Terverifikasi
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$attendance['verified']): ?>
                                                        <form method="post" action="" onsubmit="return confirm('Anda yakin ingin memverifikasi absensi ini?')">
                                                            <input type="hidden" name="attendance_id" value="<?php echo $attendance['id']; ?>">
                                                            <button type="submit" name="verify" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check mr-1"></i> Verifikasi
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">Terverifikasi</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/jquery-3.5.1.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set default date to today if empty
        $(document).ready(function() {
            if($('#date').val() === '') {
                $('#date').val(new Date().toISOString().substr(0, 10));
            }
        });
    </script>
</body>
</html>