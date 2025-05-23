<?php
require_once 'config.php';

$conn = connectDB();

$nim = isset($_GET['nim']) ? trim($_GET['nim']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

$attendances = [];
$error = '';
$success = false;
$totalDurationFormatted = '00 jam 00 menit';

if (!empty($nim)) {
    $sql = "SELECT * FROM public_attendance WHERE nim = ? AND date BETWEEN ? AND ? ORDER BY date DESC, check_in_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nim, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendances = $result->fetch_all(MYSQLI_ASSOC);

    // Calculate duration for each record and accumulate total
    $totalSeconds = 0;
    foreach ($attendances as &$attendance) {
        if (!empty($attendance['check_in_time']) && !empty($attendance['check_out_time'])) {
            $check_in = new DateTime($attendance['check_in_time']);
            $check_out = new DateTime($attendance['check_out_time']);
            $interval = $check_in->diff($check_out);
            $durationSeconds = $interval->h * 3600 + $interval->i * 60 + $interval->s;
            $totalSeconds += $durationSeconds;
            $attendance['duration_seconds'] = $durationSeconds;
            $attendance['duration_formatted'] = $interval->format('%H jam %I menit');
        } else {
            $attendance['duration_seconds'] = 0;
            $attendance['duration_formatted'] = '-';
        }
    }
    unset($attendance); // Break the reference

    // Format total duration
    if ($totalSeconds > 0) {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $totalDurationFormatted = sprintf('%02d jam %02d menit', $hours, $minutes);
    }

    if (count($attendances) === 0) {
        $error = "Tidak ditemukan data absensi untuk NIM tersebut dalam rentang tanggal yang dipilih.";
    } else {
        $success = true;
    }
}

// JSON output
if (isset($_GET['format']) && $_GET['format'] === 'json' && !empty($nim)) {
    header('Content-Type: application/json');
    echo json_encode($attendances);
    exit;
}

// PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf' && !empty($nim) && !empty($attendances)) {
    // PDF generation code here...
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi - Sistem Absensi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: #eef2f7;
            color: #2c3e50;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            padding: 20px;
        }
        .header, .footer {
            background: #34495e;
            color: #ecf0f1;
            padding: 15px 0;
            text-align: center;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .main-content {
            padding: 20px 0;
        }
        .form-container {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }
        .form-container:hover {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .form-header h1 {
            margin: 0 0 15px;
            font-size: 28px;
            color: #34495e;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            font-weight: 700;
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-size: 0.95rem;
        }
        input[type="text"],
        input[type="date"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1.8px solid #bdc3c7;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            font-family: inherit;
            color: #2c3e50;
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        textarea:focus {
            border-color: #2980b9;
            outline: none;
            box-shadow: 0 0 8px rgba(41, 128, 185, 0.3);
        }
        .alert {
            background: #fcebea;
            color: #cc1f1a;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 0.95rem;
            border: 1px solid #f5c6cb;
        }
        .card {
            margin-top: 25px;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-4px);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-weight: 700;
            color: #34495e;
            font-size: 1.15rem;
        }
        .badge {
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-block;
            user-select: none;
        }
        .badge-success {
            background: #27ae60;
            color: #fff;
        }
        .badge-warning {
            background: #f39c12;
            color: #fff;
        }
        .table-responsive {
            overflow-x: auto;
            margin-top: 15px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th,
        .table td {
            padding: 10px 15px;
            border: 1px solid #ddd;
            text-align: center;
            white-space: nowrap;
            font-size: 0.95rem;
            color: #2c3e50;
            transition: background-color 0.2s ease;
        }
        .table th {
            background-color: #2980b9;
            color: white;
            font-weight: 700;
        }
        .table tr:hover td {
            background-color: #d6eaf8;
        }
        .footer p {
            margin: 0;
            font-size: 0.9rem;
            color: #bdc3c7;
        }
        .total-row {
            font-weight: bold;
            text-align: right;
            margin-top: 15px;
            padding: 10px;
            background-color: #f2f2f2;
            border-radius: 5px;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-black {
            background-color: #34495e;
            color: white;
        }
        .btn-secondary {
            background-color: #7f8c8d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .form-container {
                padding: 15px;
            }
            .form-header h1 {
                font-size: 24px;
            }
            .table th, .table td {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <div class="form-header">
                    <h1>Laporan Absensi Mahasiswa</h1>
                    <p>Masukkan NIM Anda untuk melihat riwayat absensi</p>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="get" action="">
                    <div class="form-group">
                        <label for="nim">NIM</label>
                        <input type="text" id="nim" name="nim" value="<?php echo htmlspecialchars($nim); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="start_date">Tanggal Mulai</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Tanggal Akhir</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-black">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <button type="button" id="exportPdfBtn" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Export PDF
                        </button>
                    </div>
                </form>

                <?php if ($success && !empty($attendances)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2>Hasil Pencarian</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama</th>
                                            <th>NIM</th>
                                            <th>Program Studi</th>
                                            <th>Tanggal</th>
                                            <th>Jam Masuk</th>
                                            <th>Jam Pulang</th>
                                            <th>Durasi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($attendances as $attendance): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo $attendance['full_name'];?></td>
                                                <td><?php echo $attendance['nim'];?></td>
                                                <td><?php echo $attendance['prodi'];?></td>
                                                <td><?php echo date('d/m/Y', strtotime($attendance['date'])); ?></td>
                                                <td><?php echo $attendance['check_in_time']; ?></td>
                                                <td><?php echo $attendance['check_out_time'] ? $attendance['check_out_time'] : '-'; ?></td>
                                                <td><?php echo $attendance['duration_formatted']; ?></td>
                                                <td>
                                                    <?php if ($attendance['verified']): ?>
                                                        <span class="badge badge-success"><i class="fas fa-check"></i> Terverifikasi</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Belum Terverifikasi</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="total-row">
                                    Total Durasi: <?php echo $totalDurationFormatted; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-footer">
                    <p><a href="../.." class="btn btn-link">Kembali ke Beranda</a></p>
                </div>
            </div>
        </div>
    </main>

    <!-- jsPDF core -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- jsPDF AutoTable plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script>
    (function(){
        const apiUrl = `${location.pathname}?nim=${encodeURIComponent('<?php echo $nim; ?>')}` +
                      `&start_date=${encodeURIComponent('<?php echo $start_date; ?>')}` +
                      `&end_date=${encodeURIComponent('<?php echo $end_date; ?>')}` +
                      `&format=json`;

        document.getElementById('exportPdfBtn').addEventListener('click', async () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ unit: 'pt', format: 'a4' });

            // ambil data JSON
            const resp = await fetch(apiUrl);
            const data = await resp.json();

            // kelompokkan berdasarkan tanggal (format d/m/Y)
            const grouped = data.reduce((acc, item) => {
                const tgl = item.date.split('-').reverse().join('/'); // dari YYYY-MM-DD ke d/m/Y
                if (!acc[tgl]) acc[tgl] = [];
                acc[tgl].push(item);
                return acc;
            }, {});

            // urutkan tanggal ascending
            const sortedDates = Object.keys(grouped).sort((a, b) => {
                const [da, ma, ya] = a.split('/').map(Number);
                const [db, mb, yb] = b.split('/').map(Number);
                return new Date(ya, ma-1, da) - new Date(yb, mb-1, db);
            });

            // judul
            doc.setFontSize(16);
            doc.text('Laporan Absensi Mahasiswa', 40, 40);
            doc.setFontSize(12);
            doc.text(`Nama: ${data[0]?.full_name || ''}`, 40, 60);
            doc.text(`NIM: ${'<?php echo $nim; ?>'}`, 40, 75);
            doc.text(`Periode: ${'<?php echo date('d/m/Y', strtotime($start_date)); ?>'} - ${'<?php echo date('d/m/Y', strtotime($end_date)); ?>'}`, 40, 90);

            let cursorY = 110;
            let totalHours = 0;
            let totalMinutes = 0;

            sortedDates.forEach(tgl => {
                doc.setFontSize(12);
                doc.text(`Tanggal: ${tgl}`, 40, cursorY);
                cursorY += 20;

                // siapkan body tabel
                const body = grouped[tgl].map(item => [
                    item.full_name,
                    item.nim,
                    item.prodi,
                    item.check_in_time ? item.check_in_time+' WIB' : '-',
                    item.check_out_time ? item.check_out_time+' WIB' : '-',
                    (() => {
                        if (item.check_in_time && item.check_out_time) {
                            const inT = new Date(`1970-01-01T${item.check_in_time}`);
                            const outT = new Date(`1970-01-01T${item.check_out_time}`);
                            const diff = new Date(outT - inT);
                            const hours = diff.getUTCHours();
                            const mins = diff.getUTCMinutes();
                            totalHours += hours;
                            totalMinutes += mins;
                            return `${String(hours).padStart(2,'0')} jam ${String(mins).padStart(2,'0')} menit`;
                        }
                        return '-';
                    })()
                ]);

                doc.autoTable({
                    startY: cursorY,
                    head: [['Nama','NIM','Prodi','Jam Masuk','Jam Pulang','Durasi']],
                    body,
                    theme: 'grid',
                    styles: { fontSize: 10, cellPadding: 4 },
                    headStyles: { fillColor: [220,220,220] },
                    margin: { left: 40, right: 40 }
                });

                cursorY = doc.lastAutoTable.finalY + 30;
                // tambah halaman jika perlu
                if (cursorY > 750) {
                    doc.addPage();
                    cursorY = 40;
                }
            });

            // Adjust totals if minutes exceed 60
            totalHours += Math.floor(totalMinutes / 60);
            totalMinutes = totalMinutes % 60;
            
            const totalDurationStr = `${String(totalHours).padStart(2,'0')} jam ${String(totalMinutes).padStart(2,'0')} menit`;
            
            // Add total row after the table
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text(`Total Durasi: ${totalDurationStr}`, 40, doc.lastAutoTable.finalY + 30);

            doc.save(`laporan_absensi_<?php echo $nim; ?>.pdf`);
        });
    })();
    </script>
</body>
</html>
