<?php
require_once 'config.php'; // Ensure this path is correct

$conn = connectDB();
date_default_timezone_set('Asia/Jakarta'); // Set timezone for date functions

$nim = isset($_GET['nim']) ? trim($_GET['nim']) : '';
$start_date_default = date('Y-m-01');
$end_date_default = date('Y-m-t');

// Use submitted dates if available, otherwise default
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : $start_date_default;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $end_date_default;


$attendances = [];
$error = '';
$success = false;
$totalDurationFormatted = '00 jam 00 menit';
$studentName = ''; // To store student name for PDF title

if (!empty($nim)) {
    // Validate NIM format (optional, but good practice)
    if (!preg_match('/^\d{8,12}$/', $nim)) {
        $error = "Format NIM tidak valid. Harap masukkan 8-12 digit angka.";
    } else {
        $sql = "SELECT * FROM public_attendance WHERE nim = ? AND date BETWEEN ? AND ? ORDER BY date DESC, check_in_time DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sss", $nim, $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $attendances = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($attendances)) {
                $studentName = htmlspecialchars($attendances[0]['full_name']); // Get name from first record
            }

            $totalSeconds = 0;
            foreach ($attendances as &$attendance) { // Use reference to modify array directly
                if (!empty($attendance['check_in_time']) && !empty($attendance['check_out_time'])) {
                    try {
                        // Ensure date is combined with time for accurate DateTime objects
                        $check_in_datetime_str = $attendance['date'] . ' ' . $attendance['check_in_time'];
                        $check_out_datetime_str = $attendance['date'] . ' ' . $attendance['check_out_time'];

                        $check_in = new DateTime($check_in_datetime_str);
                        $check_out = new DateTime($check_out_datetime_str);
                        
                        if ($check_out > $check_in) {
                            $interval = $check_in->diff($check_out);
                            $durationSeconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                            $totalSeconds += $durationSeconds;
                            $attendance['duration_seconds'] = $durationSeconds;
                            $attendance['duration_formatted'] = $interval->format('%H jam %I menit');
                        } else {
                             // Check-out time is earlier than or same as check-in time on the same day
                            $attendance['duration_seconds'] = 0;
                            $attendance['duration_formatted'] = 'Invalid'; // Or handle as error
                        }
                    } catch (Exception $e) {
                        error_log("Error calculating duration for NIM $nim, Date {$attendance['date']}: " . $e->getMessage());
                        $attendance['duration_seconds'] = 0;
                        $attendance['duration_formatted'] = 'Error';
                    }
                } else {
                    $attendance['duration_seconds'] = 0;
                    $attendance['duration_formatted'] = '-';
                }
            }
            unset($attendance); 

            if ($totalSeconds > 0) {
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                $totalDurationFormatted = sprintf('%02d jam %02d menit', $hours, $minutes);
            }

            if (count($attendances) === 0 && empty($error)) {
                $error = "Tidak ditemukan data absensi untuk NIM '$nim' dalam rentang tanggal yang dipilih.";
            } elseif (count($attendances) > 0) {
                $success = true;
            }
        } else {
            $error = "Gagal mempersiapkan query: " . $conn->error;
            error_log("DB Prepare Error: " . $conn->error);
        }
    }
} elseif (isset($_GET['nim'])) { // NIM was submitted but empty
     $error = "NIM tidak boleh kosong.";
}


// JSON output for PDF generation or API use
if (isset($_GET['format']) && $_GET['format'] === 'json' && !empty($nim) && empty($error)) {
    header('Content-Type: application/json');
    echo json_encode([
        'attendances' => $attendances,
        'studentName' => $studentName,
        'nim' => $nim,
        'startDate' => $start_date,
        'endDate' => $end_date,
        'totalDurationFormatted' => $totalDurationFormatted
    ]);
    $conn->close();
    exit;
}


// Placeholder for PDF export trigger (actual generation is client-side)
// if (isset($_GET['export']) && $_GET['export'] === 'pdf' && !empty($nim) && !empty($attendances)) {
// No server-side PDF generation needed if client-side is used
// }

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi - KKN Unper</title>
    <link rel="icon" type="image/png" href="1041px-Unper.png"> <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #0056b3; /* Biru Unper (Contoh) */
            --primary-hover-color: #004085;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg-color: #f8f9fa; /* Latar belakang halaman */
            --white-color: #ffffff;
            --dark-text-color: #343a40;
            --light-text-color: #6c757d;
            --border-color: #dee2e6;
            --border-radius: 0.375rem; /* 6px */
            --card-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.075);
            --font-family: 'Poppins', sans-serif;
        }

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
            padding-top: 80px; /* Space for fixed header */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .page-header {
            background-color: var(--white-color);
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        .page-header .container-fluid { /* Use container-fluid for full width header content */
            display: flex;
            align-items: center;
            padding-left: 20px;
            padding-right: 20px;
            max-width: 1200px; /* Optional: constrain header content width */
            margin: 0 auto;
        }
        .page-header .logo-img {
            height: 40px;
            margin-right: 15px;
        }
        .page-header .page-title-main {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .main-content {
            flex-grow: 1;
            padding: 2rem 0;
        }

        .container {
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .form-section-card { /* Renamed from form-container */
            background: var(--white-color);
            padding: 2.5rem; /* Increased padding */
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-header h1 {
            margin: 0 0 0.5rem;
            font-size: 1.75rem; /* Adjusted size */
            font-weight: 600;
            color: var(--dark-text-color);
        }
        .form-header p {
            color: var(--light-text-color);
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem; /* Spacing between form groups */
        }
        .form-group {
            margin-bottom: 1.25rem; /* Default bottom margin */
            flex: 1 1 100%; /* Default to full width */
        }
        .form-group.nim-group { flex-basis: 100%; } /* NIM full width */
        .form-group.date-group { flex: 1 1 calc(50% - 0.75rem); min-width: 200px;} /* Date groups side-by-side */

        label {
            font-weight: 500;
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-text-color);
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="date"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-family: var(--font-family);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background-color: var(--white-color);
            color: var(--dark-text-color);
        }
        input[type="text"]::placeholder { color: #adb5bd; }

        input[type="text"]:focus,
        input[type="date"]:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.25);
        }

        .alert-message { /* Renamed from .alert */
            background-color: var(--danger-color);
            color: var(--white-color);
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            border: 1px solid transparent; /* For consistency with other alert types if added */
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-message.info {
            background-color: #cfe2ff; color: #084298; border-color: #b6d4fe;
        }


        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500; /* Slightly lighter for buttons */
            font-size: 0.95rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            line-height: 1.5; /* Ensure text aligns well with icon */
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white-color);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-secondary {
            background-color: var(--secondary-color);
            color: var(--white-color);
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .form-actions {
            margin-top: 1rem; /* Space above buttons if form groups have margin */
            display: flex;
            gap: 0.75rem;
        }

        /* Results Card and Table */
        .results-card {
            margin-top: 2.5rem;
            background: var(--white-color);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }
        .results-card-header {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-text-color);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .table th,
        .table td {
            padding: 0.85rem 1rem; /* Adjusted padding */
            border: 1px solid var(--border-color);
            text-align: left; /* Align text left for readability */
            font-size: 0.9rem;
            color: var(--dark-text-color);
        }
        .table th {
            background-color: var(--light-bg-color); /* Lighter header */
            color: var(--dark-text-color);
            font-weight: 600;
            white-space: nowrap;
        }
        .table tr:nth-child(even) td {
            background-color: #fdfdfe; /* Very subtle striping */
        }
        .table tr:hover td {
            background-color: #e9ecef; /* Hover effect */
        }
        .table td.text-center { text-align: center; }
        .table td.text-right { text-align: right; }


        .badge {
            padding: 0.35em 0.65em; /* Use em for padding relative to font-size */
            border-radius: 10rem; /* Pill shape */
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex; /* For icon alignment */
            align-items: center;
            gap: 0.3em;
            line-height: 1;
            user-select: none;
        }
        .badge-success {
            background-color: var(--success-color);
            color: var(--white-color);
        }
        .badge-warning {
            background-color: var(--warning-color);
            color: var(--dark-text-color); /* Dark text on yellow for contrast */
        }
        
        .total-duration-summary { /* Renamed from .total-row */
            font-weight: 600;
            text-align: right;
            margin-top: 1.5rem;
            padding: 1rem;
            background-color: var(--light-bg-color);
            border-radius: var(--border-radius);
            font-size: 1.05rem;
            border: 1px solid var(--border-color);
        }

        .page-footer-nav { /* Renamed from .form-footer */
            margin-top: 2rem;
            text-align: center;
        }
        .btn-link-home { /* Renamed */
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 0;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .btn-link-home:hover {
            text-decoration: underline;
            color: var(--primary-hover-color);
        }

        .site-footer {
            background-color: var(--dark-text-color);
            color: #a0aec0;
            text-align: center;
            padding: 1.5rem 0;
            margin-top: auto; /* Push footer to bottom */
            font-size: 0.875rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) { /* Tablets */
            .form-group.date-group { 
                flex-basis: 100%; /* Stack date pickers */
            }
        }
        @media (max-width: 768px) { /* Smaller tablets and large phones */
            body { padding-top: 70px; }
            .page-header .page-title-main { font-size: 1.25rem; }
            .page-header .logo-img { height: 35px; }

            .form-section-card { padding: 1.5rem; }
            .form-header h1 { font-size: 1.5rem; }
            .form-row { gap: 0; } /* Remove gap if groups stack */
            .form-group { margin-bottom: 1rem; }
            
            .results-card { padding: 1.5rem; }
            .results-card-header { font-size: 1.3rem; }

            .table th, .table td {
                font-size: 0.85rem;
                padding: 0.7rem 0.8rem;
            }
            .btn {
                padding: 0.65rem 1rem;
                font-size: 0.9rem;
            }
            .form-actions { flex-direction: column; gap: 0.5rem; align-items: stretch;}
            .form-actions .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 576px) { /* Small phones */
             body { padding-top: 65px; }
            .page-header .page-title-main { font-size: 1.1rem; }
            .page-header .logo-img { height: 30px; }
            .container { padding: 0 15px; }
            .form-section-card { padding: 1rem; }
            .form-header h1 { font-size: 1.3rem; }
            .form-header p { font-size: 0.9rem; margin-bottom: 1.5rem; }
             input[type="text"], input[type="date"] { padding: 0.6rem 0.8rem; font-size: 0.95rem; }
             .table th, .table td { font-size: 0.8rem; padding: 0.5rem 0.6rem; }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container-fluid">
            <img src="1041px-Unper.png" alt="Logo Unper" class="logo-img"> <h1 class="page-title-main">Laporan Absensi KKN</h1>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <section class="form-section-card">
                <div class="form-header">
                    <h1>Rekapitulasi Absensi Mahasiswa</h1>
                    <p>Masukkan NIM dan rentang tanggal untuk melihat riwayat absensi Anda.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-message info"> <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="get" action="">
                    <div class="form-row">
                        <div class="form-group nim-group">
                            <label for="nim">Nomor Induk Mahasiswa (NIM)</label>
                            <input type="text" id="nim" name="nim" placeholder="Contoh: 202101001" value="<?php echo htmlspecialchars($nim); ?>" required>
                        </div>
                        <div class="form-group date-group">
                            <label for="start_date">Tanggal Mulai</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="form-group date-group">
                            <label for="end_date">Tanggal Akhir</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan Laporan
                        </button>
                        <button type="button" id="exportPdfBtn" class="btn btn-secondary" <?php echo (empty($nim) || !$success || empty($attendances)) ? 'disabled' : ''; ?>>
                            <i class="fas fa-file-pdf"></i> Export ke PDF
                        </button>
                    </div>
                </form>

                <?php if ($success && !empty($attendances)): ?>
                    <div class="results-card">
                        <h2 class="results-card-header">Rincian Absensi: <?php echo $studentName; ?> (NIM: <?php echo htmlspecialchars($nim); ?>)</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Jam Masuk</th>
                                        <th>Jam Pulang</th>
                                        <th>Durasi</th>
                                        <th class="text-center">Status</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($attendances as $attendance): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($attendance['date']))); ?></td>
                                            <td><?php echo htmlspecialchars($attendance['check_in_time'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($attendance['check_out_time'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($attendance['duration_formatted']); ?></td>
                                            <td class="text-center">
                                                <?php if ($attendance['verified']): ?>
                                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Terverifikasi</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Belum Diverifikasi</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="total-duration-summary">
                            Total Akumulasi Durasi: <?php echo htmlspecialchars($totalDurationFormatted); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="page-footer-nav">
                    <a href="../.." class="btn-link-home"><i class="fas fa-arrow-left"></i> Kembali ke Halaman Utama</a>
                </div>
            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Sistem Absensi KKN Tematik - Universitas Perjuangan. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script> 
    <script>
    (function(){
        const nimInput = document.getElementById('nim');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const exportPdfButton = document.getElementById('exportPdfBtn');

        // Disable export button initially if NIM is empty or no successful data
        if (!nimInput.value || <?php echo json_encode(!$success || empty($attendances)); ?>) {
            exportPdfButton.disabled = true;
        }

        exportPdfButton.addEventListener('click', async () => {
            const currentNIM = nimInput.value.trim();
            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;

            if (!currentNIM) {
                alert('Silakan masukkan NIM terlebih dahulu dan klik "Tampilkan Laporan".');
                return;
            }
            if (<?php echo json_encode(empty($attendances)); ?>) {
                alert('Tidak ada data untuk diexport. Harap tampilkan laporan terlebih dahulu.');
                return;
            }

            exportPdfButton.disabled = true;
            exportPdfButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';

            const apiUrl = `${location.pathname}?nim=${encodeURIComponent(currentNIM)}` +
                          `&start_date=${encodeURIComponent(currentStartDate)}` +
                          `&end_date=${encodeURIComponent(currentEndDate)}` +
                          `&format=json`;
            try {
                const resp = await fetch(apiUrl);
                if (!resp.ok) {
                    throw new Error(`Failed to fetch data: ${resp.statusText}`);
                }
                const reportData = await resp.json();

                if (!reportData || !reportData.attendances || reportData.attendances.length === 0) {
                    alert('Tidak ada data absensi untuk diexport ke PDF.');
                    exportPdfButton.disabled = false;
                    exportPdfButton.innerHTML = '<i class="fas fa-file-pdf"></i> Export ke PDF';
                    return;
                }
                
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });

                // Add Poppins font (ensure the font file is accessible or use a standard font)
                // For simplicity, we'll use a standard font if Poppins isn't embedded.
                // Embedding custom fonts in jsPDF is more advanced.
                doc.setFont('helvetica', 'normal');


                // --- Page Header ---
                // Placeholder for Logo - If you have a base64 string of your logo:
                // const imgData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...'; // Your base64 logo
                // doc.addImage(imgData, 'PNG', 40, 25, 60, 60); // Adjust x, y, width, height

                doc.setFontSize(18);
                doc.setFont('helvetica', 'bold');
                doc.text('Laporan Absensi Mahasiswa KKN', doc.internal.pageSize.getWidth() / 2, 50, { align: 'center' });
                doc.setFontSize(12);
                doc.setFont('helvetica', 'normal');
                doc.text('Universitas Perjuangan Tasikmalaya', doc.internal.pageSize.getWidth() / 2, 70, { align: 'center' });
                doc.setLineWidth(0.5);
                doc.line(40, 85, doc.internal.pageSize.getWidth() - 40, 85); // Horizontal line

                // --- Student Info ---
                let infoY = 110;
                doc.setFontSize(10);
                doc.text(`Nama Mahasiswa:`, 40, infoY);
                doc.setFont('helvetica', 'bold');
                doc.text(reportData.studentName || 'N/A', 150, infoY);
                infoY += 15;
                doc.setFont('helvetica', 'normal');
                doc.text(`NIM:`, 40, infoY);
                doc.setFont('helvetica', 'bold');
                doc.text(reportData.nim, 150, infoY);
                infoY += 15;
                doc.setFont('helvetica', 'normal');
                doc.text(`Program Studi:`, 40, infoY);
                doc.setFont('helvetica', 'bold');
                 // Assuming prodi is consistent, take from first attendance record
                doc.text(reportData.attendances[0]?.prodi || 'N/A', 150, infoY);
                infoY += 15;
                doc.setFont('helvetica', 'normal');
                const periodeAwal = new Date(reportData.startDate).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
                const periodeAkhir = new Date(reportData.endDate).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
                doc.text(`Periode Laporan:`, 40, infoY);
                doc.setFont('helvetica', 'bold');
                doc.text(`${periodeAwal} - ${periodeAkhir}`, 150, infoY);
                
                infoY += 25; // Space before table

                // --- Table ---
                const tableColumn = ["No", "Tanggal", "Jam Masuk", "Jam Pulang", "Durasi Harian", "Status"];
                const tableRows = [];
                let counter = 1;

                reportData.attendances.forEach(item => {
                    const attendanceRow = [
                        counter++,
                        new Date(item.date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}),
                        item.check_in_time || '-',
                        item.check_out_time || '-',
                        item.duration_formatted || '-',
                        item.verified ? 'Terverifikasi' : 'Belum Diverifikasi'
                    ];
                    tableRows.push(attendanceRow);
                });

                doc.autoTable({
                    head: [tableColumn],
                    body: tableRows,
                    startY: infoY,
                    theme: 'grid', // 'striped', 'grid', 'plain'
                    styles: {
                        font: 'helvetica',
                        fontSize: 9,
                        cellPadding: 5,
                    },
                    headStyles: {
                        fillColor: [0, 86, 179], // Primary color
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    columnStyles: {
                        0: { halign: 'center', cellWidth: 30 }, // No
                        1: { halign: 'left', cellWidth: 90 }, // Tanggal
                        2: { halign: 'center', cellWidth: 70 }, // Jam Masuk
                        3: { halign: 'center', cellWidth: 70 }, // Jam Pulang
                        4: { halign: 'center', cellWidth: 90 }, // Durasi
                        5: { halign: 'center' }                 // Status
                    },
                    didDrawPage: function (data) {
                        // Footer for each page
                        doc.setFontSize(8);
                        doc.setTextColor(150);
                        doc.text('Laporan Absensi KKN - Universitas Perjuangan', data.settings.margin.left, doc.internal.pageSize.getHeight() - 15);
                        doc.text('Halaman ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.getWidth() - data.settings.margin.right, doc.internal.pageSize.getHeight() - 15, { align: 'right' });
                    }
                });
                
                let finalY = doc.lastAutoTable.finalY + 20;

                // --- Total Duration ---
                doc.setFontSize(10);
                doc.setFont('helvetica', 'bold');
                doc.text(`Total Akumulasi Durasi Kehadiran: ${reportData.totalDurationFormatted}`, 40, finalY);
                finalY += 30;

                // --- Signature Placeholder (Example) ---
                const signatureX = doc.internal.pageSize.getWidth() - 40 - 150; // Align right
                if (finalY > doc.internal.pageSize.getHeight() - 100) { // Check if space is enough
                    doc.addPage();
                    finalY = 60;
                }
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text('Tasikmalaya, ' + new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }), signatureX, finalY);
                finalY += 15;
                doc.text('Dosen Pembimbing Lapangan,', signatureX, finalY);
                finalY += 60; // Space for signature
                doc.setLineWidth(0.5);
                doc.line(signatureX, finalY, signatureX + 150, finalY); // Signature line
                finalY += 15;
                doc.text('(_________________________)', signatureX, finalY); // Name placeholder
                // doc.text('NIDN: ........................', signatureX, finalY + 15);

                doc.save(`Laporan_Absensi_KKN_${reportData.nim}_${reportData.startDate}_sd_${reportData.endDate}.pdf`);

            } catch (e) {
                console.error("Error generating PDF:", e);
                alert("Gagal membuat PDF. Silakan coba lagi. Error: " + e.message);
            } finally {
                exportPdfButton.disabled = false;
                exportPdfButton.innerHTML = '<i class="fas fa-file-pdf"></i> Export ke PDF';
            }
        });
    })();
    </script>
</body>
</html>