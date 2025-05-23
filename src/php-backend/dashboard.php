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
        .table-responsive {
            overflow-x: auto;
        }
        .filter-container .form-select,
        .filter-container input[type="date"] {
            max-width: 200px;
        }
        .sidebar {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .offcanvas-header {
            background-color: #f8f9fa;
        }
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: inline-block;
            }
            .filter-container {
                flex-direction: column;
                gap: 10px;
            }
            .filter-container .form-select,
            .filter-container input[type="date"] {
                max-width: 100%;
            }
        }

@media print {
  body {
    margin: 0;
    padding: 0;
    font-size: 12pt;
    color: black;
    background: white;
  }

  /* Sembunyikan elemen yang tidak perlu dicetak */
  .no-print, nav, footer, .btn {
    display: none !important;
  }

  /* Atur lebar konten agar tidak terpotong */
  .container, .content {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0;
    padding: 0;
  }

  /* Ubah ukuran font atau layout jika perlu */
  table {
    width: 100%;
    border-collapse: collapse;
  }

  table, th, td {
    border: 1px solid black;
    padding: 5px;
  }

  /* Hindari pemutusan halaman di tengah tabel */
  table {
    page-break-inside: avoid;
  }
  @page {
  size: A4;
  margin: 1cm;
}

}

    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar toggle untuk mobile -->
        <div class="d-md-none bg-light p-2">
            <button
                class="btn btn-outline-primary"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasSidebar"
            >
                <i class="fas fa-bars"></i> Menu
            </button>
        </div>

        <!-- Sidebar Offcanvas -->
        <div
            class="offcanvas offcanvas-start d-md-none"
            tabindex="-1"
            id="offcanvasSidebar"
        >
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Menu</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                ></button>
            </div>
            <div class="offcanvas-body sidebar">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link"
                            ><i class="fas fa-home"></i> Dashboard</a>
                        <li class="nav-item">
                            <a class="nav-link active" href="photos.php">
                                <i class="fas fa-images"></i> Foto Absensi
                            </a>
                        </li>
                    </li>
                    <li class="nav-item">
                            <a class="nav-link active" href="attendance_map.php">
                                <i class="fas fa-map-marked-alt"></i> Lokasi
                            </a>
                        </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link"
                            ><i class="fas fa-sign-out-alt"></i> Logout</a >
                    </li>
                </ul>
            </div>
        </div>

        <!-- Sidebar desktop -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="photos.php">
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
  // Buat URL untuk fetch JSON
  const params = new URLSearchParams(window.location.search);
  params.set('format', 'json');
  const apiUrl = `${location.pathname}?${params.toString()}`;

  document.getElementById('exportPdfBtn').addEventListener('click', async () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });

    // Fetch data
    const resp = await fetch(apiUrl);
    const data = await resp.json();

    // Judul dokumen
    doc.setFontSize(16);
    doc.text('Laporan Absensi', 40, 40);

    // Informasi filter tanggal
    const dateFrom = params.get('date_from');
    const dateTo = params.get('date_to');
    let subtitle = 'Tanggal: ';
    if (dateFrom && dateTo) subtitle += `${formatDMY(dateFrom)} s/d ${formatDMY(dateTo)}`;
    else if (dateFrom) subtitle += `Mulai ${formatDMY(dateFrom)}`;
    else if (dateTo) subtitle += `Sampai ${formatDMY(dateTo)}`;
    else subtitle += formatDMY(params.get('date') || new Date().toISOString().slice(0,10));
    doc.setFontSize(12);
    doc.text(subtitle, 40, 60);

    // Ubah bagian body tabel untuk menggunakan verifier_name
    const body = data.map((item, idx) => {
    const dur = (item.check_in_time && item.check_out_time)
        ? getDuration(item.check_in_time, item.check_out_time)
        : '-';
    return [
        idx + 1,
        item.full_name,
        item.nim,
        formatDMY(item.date),
        item.prodi,
        item.check_in_time || '-',
        item.check_out_time || '-',
        dur,
        item.notes || '-',
        item.verified ? 'Terverifikasi' : 'Belum',
        item.verifier_name || '-'
    ];
    });

    // AutoTable
    doc.autoTable({
      startY: 80,
      head: [['No','Nama','NIM','Tanggal','Prodi','Jam Masuk','Jam Pulang','Durasi','Catatan','Status','Verifed By']],
      body,
      theme: 'grid',
      styles: { fontSize: 9, cellPadding: 4 },
      headStyles: { fillColor: [200,200,200] },
      margin: { left: 40, right: 40 }
    });

    doc.save(`laporan_absensi.pdf`);
  });

  // Helper: format YYYY-MM-DD → d-m-Y
  function formatDMY(iso) {
    const [y,m,d] = iso.split('-');
    return `${d}-${m}-${y}`;
  }
  // Helper: hitung durasi jam:menit
  function getDuration(inT, outT) {
    const [ih,im] = inT.split(':').map(Number);
    const [oh,om] = outT.split(':').map(Number);
    let mins = (oh*60+om) - (ih*60+im);
    const h = Math.floor(mins/60);
    const m = mins%60;
    return `${h} jam ${m} menit`;
  }
})();
</script>
<!-- jsPDF core -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<!-- jsPDF AutoTable plugin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

</body>
</html>