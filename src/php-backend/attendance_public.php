<?php
require_once 'config.php';

// Connect to database
$conn = connectDB();
date_default_timezone_set('Asia/Jakarta');

// Create public_attendance table if not exists
$sql = "CREATE TABLE IF NOT EXISTS public_attendance (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    nim VARCHAR(50) NOT NULL,
    prodi VARCHAR(100) NOT NULL,
    photo VARCHAR(255),
    date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    latitude_in DECIMAL(10,8),
    longitude_in DECIMAL(11,8),
    latitude_out DECIMAL(10,8),
    longitude_out DECIMAL(11,8),
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT(11),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
)";
if ($conn->query($sql) === FALSE) {
    die("Error creating public_attendance table: " . $conn->error);
}

// Jika request JSON untuk NIM tertentu
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['format']) && $_GET['format'] === 'json' && isset($_GET['nim'])) {
    $nim = $_GET['nim'];
    $stmt = $conn->prepare("SELECT * FROM public_attendance WHERE nim = ?");
    $stmt->bind_param("s", $nim);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendances = $result->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($attendances);
    exit;
}

$success = false;
$error = "";
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'check_in';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $nim = trim($_POST['nim']);
    $prodi = trim($_POST['prodi']);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $photo_path = null;
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;

    // Upload photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_name = uniqid() . '_' . basename($_FILES['photo']['name']);
        $target_file = $upload_dir . $file_name;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($imageFileType, $allowed_types)) {
            $error = "Hanya file gambar (JPG, PNG, GIF) yang diperbolehkan.";
        } else {
            if (move_uploaded_file($file_tmp, $target_file)) {
                $photo_path = $target_file;
            } else {
                $error = "Gagal mengunggah foto.";
            }
        }
    }

    // Validasi field
    if (empty($full_name) || empty($nim) || empty($prodi)) {
        $error = "Semua field harus diisi!";
    } else if (!preg_match('/^\d{8,12}$/', $nim)) {
        $error = "NIM harus terdiri dari 8–12 digit angka!";
    } else if (!preg_match("/^[a-zA-Z\s.'-]+$/", $full_name)) {
        $error = "Nama hanya boleh berisi huruf, spasi, titik, petik atau tanda hubung!";
    } else {
        if ($mode == 'check_in') {
            // Cek apakah NIM sudah check_in hari ini
            $check_attendance_sql = "SELECT id, full_name FROM public_attendance WHERE nim = ? AND date = ? AND check_in_time IS NOT NULL";
            $stmt = $conn->prepare($check_attendance_sql);
            $stmt->bind_param("ss", $nim, $current_date);
            $stmt->execute();
            $attendance_result = $stmt->get_result();

            if ($attendance_result->num_rows > 0) {
                $row = $attendance_result->fetch_assoc();
                if (strtolower(trim($row['full_name'])) !== strtolower(trim($full_name))) {
                    $error = "NIM '$nim' sudah terdaftar atas nama '{$row['full_name']}'!";
                } else {
                    $error = "Anda sudah melakukan absen masuk hari ini!";
                }
            } else {
                // Cek jika nama yang sama menggunakan NIM berbeda hari ini
                $check_name_sql = "SELECT nim FROM public_attendance WHERE full_name = ? AND date = ? AND check_in_time IS NOT NULL";
                $stmt = $conn->prepare($check_name_sql);
                $stmt->bind_param("ss", $full_name, $current_date);
                $stmt->execute();
                $name_result = $stmt->get_result();

                if ($name_result->num_rows > 0) {
                    $row = $name_result->fetch_assoc();
                    $error = "Nama '$full_name' sudah terdaftar dengan NIM '{$row['nim']}' hari ini!";
                } else {
                    // Jika validasi berhasil, lanjutkan dengan insert
                    $sql = "INSERT INTO public_attendance (full_name, nim, prodi, date, check_in_time, notes, photo, latitude_in, longitude_in) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssddd", $full_name, $nim, $prodi, $current_date, $current_time, $notes, $photo_path, $latitude, $longitude);

                    if ($stmt->execute()) {
                        $success = true;
                    } else {
                        $error = "Error: " . $stmt->error;
                    }
                }
            }
        } else { // check_out mode
            // Cek apakah sudah check_in hari ini dan belum check_out
            $check_sql = "SELECT id FROM public_attendance WHERE nim = ? AND date = ? AND check_in_time IS NOT NULL AND check_out_time IS NULL";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ss", $nim, $current_date);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                // Cek apakah sudah check_out
                $check_out_sql = "SELECT id FROM public_attendance WHERE nim = ? AND date = ? AND check_out_time IS NOT NULL";
                $stmt = $conn->prepare($check_out_sql);
                $stmt->bind_param("ss", $nim, $current_date);
                $stmt->execute();
                $check_out_result = $stmt->get_result();
                
                if ($check_out_result->num_rows > 0) {
                    $error = "Anda sudah melakukan absen pulang hari ini!";
                } else {
                    $error = "Anda belum melakukan absen masuk hari ini!";
                }
            } else {
                $row = $result->fetch_assoc();
                $attendance_id = $row['id'];
                
                // Verifikasi bahwa nama yang digunakan untuk check_out sama dengan check_in
                $verify_name_sql = "SELECT full_name FROM public_attendance WHERE id = ?";
                $stmt = $conn->prepare($verify_name_sql);
                $stmt->bind_param("i", $attendance_id);
                $stmt->execute();
                $name_result = $stmt->get_result();
                $name_row = $name_result->fetch_assoc();
                
                if (strtolower(trim($name_row['full_name'])) !== strtolower(trim($full_name))) {
                    $error = "Nama harus sama dengan saat absen masuk ('{$name_row['full_name']}')!";
                } else {
                    $sql = "UPDATE public_attendance 
                            SET check_out_time = ?, notes = CONCAT(COALESCE(notes, ''), ' | Catatan pulang: ', ?), photo = ?, 
                                latitude_out = ?, longitude_out = ?
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdddi", $current_time, $notes, $photo_path, $latitude, $longitude, $attendance_id);

                    if ($stmt->execute()) {
                        $success = true;
                    } else {
                        $error = "Error: " . $stmt->error;
                    }
                }
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="1041px-Unper.png">
    <title><?php echo ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?> - Sistem Absensi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="assets/img/favicon.ico">
    <style>
    * {
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .form-container {
      background-color: #ffffff;
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      width: 100%;
      max-width: 500px;
      animation: fadeInUp 0.7s ease-out;
    }

    h2 {
      text-align: center;
      color: #333;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.2rem;
    }

    label {
      display: block;
      margin-bottom: 0.4rem;
      font-weight: 600;
      color: #333;
    }

    input[type="text"],
    textarea {
      width: 100%;
      padding: 0.9rem;
      border: 1px solid #ccc;
      border-radius: 0.6rem;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    input[type="text"]:focus,
    textarea:focus {
      border-color: #007bff;
      outline: none;
    }

    textarea {
      resize: vertical;
    }

    button {
        width: 100%;
        padding: 1rem;
        background-color: #000;
        color: #fff;
        border: none;
        border-radius: 0.6rem;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: #333;
    }

    .location-status {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        text-align: center;
    }
    
    .location-info {
        background-color: #d4edda;
        color: #155724;
    }
    
    .location-warning {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .location-error {
        background-color: #f8d7da;
        color: #721c24;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 600px) {
      .form-container {
        padding: 1.5rem;
      }

      h2 {
        font-size: 1.4rem;
      }
    }
  </style>
</head>
<body>
    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <h1><?= ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?></h1>
                <p>Silakan isi data berikut</p>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <p><?= ($mode == 'check_in') ? 'Absen masuk berhasil!' : 'Absen pulang berhasil!'; ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <p><?= $error; ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" id="attendanceForm">
                    <div class="form-group">
                        <label for="full_name">Nama Lengkap</label>
                        <input type="text" name="full_name" id="full_name" required>
                    </div>

                    <div class="form-group">
                        <label for="nim">NIM</label>
                        <input type="text" name="nim" id="nim" required>
                    </div>

                    <div class="form-group">
                        <label for="prodi">Program Studi</label>
                        <input type="text" name="prodi" id="prodi" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Catatan (opsional)</label>
                        <textarea name="notes" id="notes" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="photo">Unggah Foto <?= ($mode == 'check_out') ? '(Wajib)' : '(Opsional)'; ?></label>
                        <input type="file" name="photo" id="photo" accept="image/*" <?= ($mode == 'check_out') ? 'required' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                        <div id="locationStatus" class="location-status" style="display:none;"></div>
                        <button type="submit" id="submitBtn">
                            <i class="fas fa-<?= ($mode == 'check_in') ? 'sign-in-alt' : 'sign-out-alt'; ?>"></i>
                            <?= ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?>
                        </button>
                    </div>
                </form>
                <div class="form-footer">
                    <p>
                        <?php if ($mode == 'check_in'): ?>
                            Sudah absen masuk? <a href="attendance_public.php?mode=check_out">Absen pulang</a>
                        <?php else: ?>
                            Belum absen masuk? <a href="attendance_public.php?mode=check_in">Absen masuk</a>
                        <?php endif; ?>
                    </p>
                    <p><a href="../.." class="btn btn-link">Kembali ke Beranda</a></p>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>© 2025 SistemAbsensi. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script>
        const locationStatus = document.getElementById('locationStatus');
        const submitBtn = document.getElementById('submitBtn');
        const latitudeInput = document.getElementById('latitude');
        const longitudeInput = document.getElementById('longitude');
        
        // Function to update location status display
        function updateLocationStatus(message, type) {
            locationStatus.style.display = 'block';
            locationStatus.className = 'location-status ' + type;
            locationStatus.innerHTML = message;
        }
        
        // Function to get current location
        function getLocation() {
            if (navigator.geolocation) {
                updateLocationStatus('Mendeteksi lokasi Anda...', 'location-info');
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        
                        latitudeInput.value = latitude;
                        longitudeInput.value = longitude;
                        
                        updateLocationStatus('Lokasi berhasil dideteksi', 'location-info');
                        submitBtn.disabled = false;
                    },
                    function(error) {
                        let errorMessage = "Lokasi tidak dapat dideteksi: ";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += "Izin akses lokasi ditolak.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Informasi lokasi tidak tersedia.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "Permintaan lokasi melebihi waktu tunggu.";
                                break;
                            case error.UNKNOWN_ERROR:
                                errorMessage += "Terjadi kesalahan yang tidak diketahui.";
                                break;
                        }
                        updateLocationStatus(errorMessage, 'location-warning');
                        submitBtn.disabled = false;
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                updateLocationStatus("Browser Anda tidak mendukung geolokasi", 'location-error');
                submitBtn.disabled = false;
            }
        }
        
        // Automatically get location when page loads
        document.addEventListener('DOMContentLoaded', function() {
            getLocation();
            
            // Add event listener to form submission to ensure location is captured
            document.getElementById('attendanceForm').addEventListener('submit', function(e) {
                if (!latitudeInput.value || !longitudeInput.value) {
                    e.preventDefault();
                    updateLocationStatus('Harap tunggu hingga lokasi terdeteksi...', 'location-error');
                    getLocation();
                }
            });
        });
    </script>
</body>
</html>
