<?php
/**
 * Contoh Integrasi Supabase dengan Sistem Absensi
 * 
 * File ini menunjukkan cara menggunakan Supabase untuk menyimpan dan mengambil data absensi
 * sebagai alternatif dari database MySQL yang ada.
 */

// Load Supabase PHP integration
require_once 'supabase-php.php';

// Contoh halaman absensi yang menggunakan Supabase

// Fungsi untuk menyimpan data absensi ke Supabase
function saveAttendanceToSupabase($full_name, $nim, $prodi, $notes = '', $photo_path = null, $latitude = null, $longitude = null) {
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    
    $attendanceData = [
        'full_name' => $full_name,
        'nim' => $nim,
        'prodi' => $prodi,
        'date' => $current_date,
        'check_in_time' => $current_time,
        'notes' => $notes,
        'photo' => $photo_path,
        'latitude_in' => $latitude,
        'longitude_in' => $longitude
    ];
    
    return insertSupabaseData('public_attendance', $attendanceData);
}

// Fungsi untuk memeriksa apakah pengguna sudah absen hari ini
function checkAttendanceExists($nim, $date) {
    $params = [
        'select' => 'id,full_name',
        'nim' => 'eq.' . $nim,
        'date' => 'eq.' . $date
    ];
    
    $result = getSupabaseData('public_attendance', $params);
    
    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }
    
    return false;
}

// Fungsi untuk memperbarui data absensi (check-out)
function updateAttendanceCheckout($id, $notes = '', $photo_path = null, $latitude = null, $longitude = null) {
    $current_time = date('H:i:s');
    
    $updateData = [
        'check_out_time' => $current_time,
        'latitude_out' => $latitude,
        'longitude_out' => $longitude
    ];
    
    if ($notes) {
        $updateData['notes'] = $notes;
    }
    
    if ($photo_path) {
        $updateData['photo'] = $photo_path;
    }
    
    return updateSupabaseData('public_attendance', $updateData, 'id', $id);
}

// Contoh penggunaan dalam proses form
$success = false;
$error = "";
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'check_in';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $nim = trim($_POST['nim']);
    $prodi = trim($_POST['prodi']);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $current_date = date('Y-m-d');
    $photo_path = null;
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    
    // Upload photo logic (sama seperti di attendance_public.php)
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
            $attendance = checkAttendanceExists($nim, $current_date);
            
            if ($attendance) {
                if (strtolower(trim($attendance['full_name'])) !== strtolower(trim($full_name))) {
                    $error = "NIM '$nim' sudah terdaftar atas nama '{$attendance['full_name']}'!";
                } else {
                    $error = "Anda sudah melakukan absen masuk hari ini!";
                }
            } else {
                // Simpan data absensi ke Supabase
                $result = saveAttendanceToSupabase($full_name, $nim, $prodi, $notes, $photo_path, $latitude, $longitude);
                
                if ($result['success']) {
                    $success = true;
                } else {
                    $error = "Error: " . json_encode($result['data']);
                }
            }
        } else { // check_out mode
            // Cek apakah sudah check_in hari ini dan belum check_out
            $attendance = checkAttendanceExists($nim, $current_date);
            
            if (!$attendance) {
                $error = "Anda belum melakukan absen masuk hari ini!";
            } else {
                // Cek apakah sudah check_out
                if (isset($attendance['check_out_time']) && $attendance['check_out_time']) {
                    $error = "Anda sudah melakukan absen pulang hari ini!";
                } else {
                    // Verifikasi bahwa nama yang digunakan untuk check_out sama dengan check_in
                    if (strtolower(trim($attendance['full_name'])) !== strtolower(trim($full_name))) {
                        $error = "Nama harus sama dengan saat absen masuk ('{$attendance['full_name']}')!";
                    } else {
                        // Update data absensi dengan check_out
                        $result = updateAttendanceCheckout($attendance['id'], $notes, $photo_path, $latitude, $longitude);
                        
                        if ($result['success']) {
                            $success = true;
                        } else {
                            $error = "Error: " . json_encode($result['data']);
                        }
                    }
                }
            }
        }
    }
}

// HTML dan tampilan form sama seperti di attendance_public.php
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?> (Supabase) - Sistem Absensi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    /* CSS sama seperti di attendance_public.php */
    </style>
</head>
<body>
    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <h1><?= ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?> (Supabase)</h1>
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
                            <?= ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?>
                        </button>
                    </div>
                </form>
                <div class="form-footer">
                    <p>
                        <?php if ($mode == 'check_in'): ?>
                            Sudah absen masuk? <a href="attendance_supabase_example.php?mode=check_out">Absen pulang</a>
                        <?php else: ?>
                            Belum absen masuk? <a href="attendance_supabase_example.php?mode=check_in">Absen masuk</a>
                        <?php endif; ?>
                    </p>
                    <p><a href="../.." class="btn btn-link">Kembali ke Beranda</a></p>
                </div>
            </div>
        </div>
    </main>

    <script>
    // JavaScript untuk geolokasi sama seperti di attendance_public.php
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