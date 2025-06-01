<?php

require_once 'config.php';



// Connect to database

$conn = connectDB();

date_default_timezone_set('Asia/Jakarta');



// Deteksi perangkat iOS

$isIOS = false;

if (isset($_SERVER['HTTP_USER_AGENT'])) {

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $isIOS = (strpos($user_agent, 'iPad') !== false || strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPod') !== false) && strpos($user_agent, 'Windows Phone') === false;

}



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


    // Untuk perangkat iOS, lokasi tidak wajib

    if ($isIOS && (empty($latitude) || empty($longitude))) {

        // Set nilai default untuk lokasi jika tidak ada

        $latitude = $latitude ?: 0;

        $longitude = $longitude ?: 0;

        // Log untuk debugging

        error_log("iOS device detected, location not required: $latitude, $longitude");

    }



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

                    $sql = "INSERT INTO public_attendance (full_name, nim, prodi, date, check_in_time, notes, latitude_in, longitude_in, photo)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param("sssssssss", $full_name, $nim, $prodi, $current_date, $current_time, $notes, $latitude, $longitude, $photo_path);



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

SET check_out_time = ?, notes = CONCAT(COALESCE(notes, ''), ' | ', ?), photo = ?,

latitude_out = ?, longitude_out = ?

WHERE id = ?";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param("ssssss", $current_time, $notes, $photo_path, $latitude, $longitude, $attendance_id);



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
      /* :::::::::::::::: ROOT VARIABLES & RESETS :::::::::::::::: */
:root {
    --primary-color: #0056b3; /* A professional blue */
    --primary-hover-color: #004085;
    --secondary-color: #6c757d; /* Gray for secondary elements */
    --secondary-hover-color: #5a6268;
    --light-bg-color: #f8f9fa;
    --white-color: #ffffff;
    --dark-text-color: #343a40;
    --light-text-color: #6c757d;
    --border-color: #dee2e6;
    --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    --font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Added fallbacks from first CSS */
    --border-radius: 8px;
}

/* Reset dasar */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: var(--font-family);
    background-color: var(--light-bg-color); /* Default from second block */
    color: var(--dark-text-color); /* Default from second block */
    line-height: 1.6;
    font-weight: 400;
}

.container {
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 20px;
}

/* :::::::::::::::: SITE HEADER :::::::::::::::: */
.site-header {
    background-color: var(--white-color);
    padding: 1rem 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.site-header .container {
    display: flex;
    align-items: center;
    justify-content: center; /* <<< Added to center header content */
    gap: 15px;
}

.site-header .logo-img {
    height: 45px;
    width: auto;
}

.site-header .site-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin: 0;
}

/* :::::::::::::::: MAIN CONTENT & HERO SECTION :::::::::::::::: */
.main-content {
    padding: 40px 0;
}

.hero-section {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 40px;
    align-items: center;
}

.hero-content {
    flex: 1 1 500px;
    animation: fadeInUp 0.8s ease-out; /* Uses the general fadeInUp */
}

.hero-content .hero-title {
    font-size: 2.8rem;
    font-weight: 700;
    margin-bottom: 15px;
    color: var(--dark-text-color);
    line-height: 1.3;
}

.hero-content .hero-subtitle {
    font-size: 1.15rem;
    margin-bottom: 30px;
    color: var(--light-text-color);
}

.hero-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

/* :::::::::::::::: BUTTONS (General) :::::::::::::::: */
.btn {
    background-color: var(--primary-color);
    color: var(--white-color);
    padding: 12px 25px;
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: background-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
    cursor: pointer;
    border: none;
    font-size: 0.95rem;
}

.btn:hover {
    background-color: var(--primary-hover-color);
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0, 86, 179, 0.3);
}

.btn:active {
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--secondary-color);
}
.btn-secondary:hover {
    background-color: var(--secondary-hover-color);
    box-shadow: 0 4px 10px rgba(0,0,0, 0.2);
}

.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}
.btn-outline:hover {
    background-color: var(--primary-color);
    color: var(--white-color);
    box-shadow: 0 4px 10px rgba(0, 86, 179, 0.2);
}

/* :::::::::::::::: FEATURES SECTION :::::::::::::::: */
.features-section {
    flex: 1 1 400px;
    display: flex;
    flex-direction: column;
    gap: 25px;
    animation: fadeInRight 0.8s ease-out 0.2s;
    animation-fill-mode: backwards;
}

.feature-card {
    background: var(--white-color);
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.feature-icon {
    font-size: 2.8rem;
    color: var(--primary-color);
    margin-bottom: 20px;
}

.feature-card h3 {
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.25rem;
    color: var(--dark-text-color);
}

.feature-card p {
    color: var(--light-text-color);
    font-size: 0.95rem;
}

/* :::::::::::::::: FORM SPECIFIC STYLES :::::::::::::::: */
/* (Primarily from the first CSS block provided) */
.form-container {
    background-color: #ffffff; /* Form specific background */
    padding: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 500px;
    animation: formFadeInUp 0.7s ease-out; /* Renamed animation */
    border: 1px solid #ddd;
    margin: 2rem auto; /* Added margin for standalone viewing */
}

.form-container h1,
.form-container h2 { /* Scoped to form-container */
    text-align: center;
    color: #000; /* Form specific heading color */
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.form-group {
    margin-bottom: 1.2rem;
}

label { /* Consider scoping if it conflicts: .form-container label */
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 600;
    color: #000; /* Form specific label color */
}

input[type="text"],
textarea,
input[type="file"] {
    width: 100%;
    padding: 0.9rem;
    border: 1px solid #000; /* Form specific border */
    border-radius: 0.3rem;
    font-size: 1rem;
    transition: border-color 0.3s ease;
    background-color: #fff; /* Form specific input background */
    color: #000; /* Form specific input text color */
}

input[type="text"]:focus,
textarea:focus {
    border-color: #000; /* Form specific focus color */
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
}

textarea {
    resize: vertical;
}

/* Form-specific button, if not using .btn class */
.form-container button:not(.btn) {
    width: 100%;
    padding: 1rem;
    background-color: #000; /* Form specific button color */
    color: #fff;
    border: none;
    border-radius: 0.3rem;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-container button:not(.btn):hover {
    background-color: #333;
}

.form-container button:not(.btn):disabled {
    background-color: #666;
    cursor: not-allowed;
}

.location-status {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 3px;
    text-align: center;
    border: 1px solid;
}

.location-info {
    background-color: #f0f0f0;
    color: #000;
    border-color: #000;
}

.location-warning {
    background-color: #f8f8f8;
    color: #000;
    border-color: #000;
}

.location-error {
    background-color: #fff;
    color: #000;
    border-color: #000;
}

.alert {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 3px;
    text-align: center;
    border: 1px solid #000;
}

.alert-success {
    background-color: #f0f0f0;
    color: #000;
}

.alert-error {
    background-color: #fff;
    color: #000;
}

.alert-info {
    background-color: #f8f8f8;
    color: #000;
}

.form-footer {
    margin-top: 20px;
    text-align: center;
    border-top: 1px solid #ddd;
    padding-top: 15px;
}

/* :::::::::::::::: GENERAL LINK STYLE :::::::::::::::: */
/* From first CSS block, applied globally if not overridden */
a {
    color: #000; /* General link color from first block */
    text-decoration: underline;
    font-weight: 600;
}

a:hover {
    text-decoration: none;
}
/* Ensure .btn (which can be an <a> tag) styles override this if needed */
.btn, .btn:hover {
    text-decoration: none; /* Already part of .btn, but good to be explicit */
}


/* :::::::::::::::: FOOTERS :::::::::::::::: */
/* Simple footer style from first CSS block */
.footer {
    text-align: center;
    padding: 20px 0;
    color: #000; /* Footer specific color */
    font-size: 0.9rem;
    background-color: #f0f0f0; /* Example background, adjust as needed */
    margin-top: 30px; /* Space from content */
}

/* Site-wide footer from second CSS block */
.site-footer { /* Renamed for clarity */
    background-color: var(--dark-text-color); /* Darker footer */
    color: #a0aec0; /* Lighter text for contrast */
    text-align: center;
    padding: 25px 0;
    margin-top: 50px;
    font-size: 0.9rem;
}


/* :::::::::::::::: KEYFRAMES :::::::::::::::: */
@keyframes formFadeInUp { /* Renamed from first block's fadeInUp */
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp { /* From second block */
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInRight { /* From second block */
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes btnPulse { /* From first block */
    0% {
        box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(0, 0, 0, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 0, 0, 0);
    }
}
.btn-pulse { /* Class to apply the pulse animation */
    animation: btnPulse 1.5s infinite;
}


/* :::::::::::::::: RESPONSIVE ADJUSTMENTS :::::::::::::::: */
@media (max-width: 992px) { /* Tablet breakpoint (from second block) */
    .hero-content .hero-title {
        font-size: 2.4rem;
    }
    .hero-content {
        flex-basis: 100%;
        text-align: center;
        margin-bottom: 30px;
    }
    .hero-buttons {
        justify-content: center;
    }
    .features-section {
        flex-basis: 100%;
        align-items: center;
    }
    .feature-card {
        width: 100%;
        max-width: 450px;
    }
}

@media (max-width: 768px) { /* Mobile breakpoint (from second block) */
    .site-header .site-title {
        font-size: 1.25rem;
    }
     .site-header .logo-img {
        height: 40px;
    }
    .hero-content .hero-title {
        font-size: 2rem;
    }
    .hero-content .hero-subtitle {
        font-size: 1.05rem;
    }
    .hero-buttons {
        flex-direction: column;
        align-items: stretch;
    }
    .btn { /* Affects all .btn including those in hero */
        width: 100%;
        justify-content: center;
        padding: 14px 20px;
    }
    .features-section {
        gap: 20px;
    }
    .feature-card {
        padding: 20px;
    }
    .feature-icon {
        font-size: 2.5rem;
    }
}

@media (max-width: 600px) { /* From first block, primarily for form */
    .form-container {
        padding: 1.5rem;
    }

    .form-container h2 { /* Adjusted to h2 as h1 might be hero */
        font-size: 1.4rem;
    }
    /* Other mobile styles from second block may also apply here */
}

@media (max-width: 480px) { /* Small mobile breakpoint (from second block) */
    .container {
        padding: 0 15px;
    }
    .site-header .site-title {
        font-size: 1.1rem;
    }
    .site-header .logo-img {
        height: 35px;
    }
    .hero-content .hero-title {
        font-size: 1.8rem;
    }
    .hero-content .hero-subtitle {
        font-size: 1rem;
    }
    .feature-icon {
        font-size: 2.2rem;
    }
    .feature-card h3 {
        font-size: 1.1rem;
    }
     .feature-card p {
        font-size: 0.9rem;
    }
}
    </style>

</head>

<body>
<header class="site-header">
    <div class="container">
        <img src="1041px-Unper.png" alt="Logo Unper" class="logo-img" />
        <h1 class="site-title">Sistem Absensi KKN Tematik 2025</h1>
    </div>
</header>
    <main class="main-content">
    <div class="container">
        <div class="form-container">
            <h1>
                <i class="fas fa-<?php echo ($mode == 'check_in') ? 'user-plus' : 'user-check'; ?>"></i>
                <?php echo ($mode == 'check_in') ? 'Absen Masuk' : 'Absen Pulang'; ?>
            </h1>
            <p style="text-align:center; margin-bottom: 1.5rem; color: #555;">
                Silakan isi data berikut dengan benar.
            </p>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><i class="fas fa-check-circle"></i> <?php echo ($mode == 'check_in') ? 'Absen masuk berhasil!' : 'Absen pulang berhasil!'; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <p><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); // Selalu escape output untuk keamanan ?></p>
                </div>
            <?php endif; ?>

            <?php if ($isIOS && $mode == 'check_in'): // Pesan ini mungkin lebih relevan saat check-in untuk iOS ?>
                <div class="alert alert-info mb-3">
                    <i class="fab fa-apple"></i> <strong>Perangkat iOS Terdeteksi:</strong> Geolocation mungkin memerlukan waktu. Anda dapat melanjutkan jika lokasi tidak terdeteksi setelah beberapa saat.
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="attendanceForm">
                <div class="form-group">
                    <label for="full_name">Nama Lengkap</label>
                    <input type="text" name="full_name" id="full_name"
                           pattern="^[a-zA-Z\s.'-]+$"
                           title="Nama hanya boleh berisi huruf, spasi, titik (.), petik ('), atau tanda hubung (-)."
                           required>
                </div>

                <div class="form-group">
                    <label for="nim">NIM</label>
                    <input type="text" name="nim" id="nim"
                           pattern="^\d{8,12}$"
                           title="NIM harus terdiri dari 8 hingga 12 digit angka."
                           required>
                </div>

                <div class="form-group">
                    <label for="prodi">Program Studi</label>
                    <input type="text" name="prodi" id="prodi"
                           pattern="^[a-zA-Z\s.'-]+$"
                           title="Program Studi hanya boleh berisi huruf, spasi, titik (.), petik ('), atau tanda hubung (-)."
                           required>
                </div>

                <div class="form-group">
                    <label for="notes">Catatan <?php echo ($mode == 'check_out') ? '(Tambahan jika perlu)' : '(Opsional)'; ?></label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Contoh: Izin terlambat karena ban bocor, dll."></textarea>
                </div>

                
        <div class="form-group">
            <label for="photo">Unggah Foto <?= ($mode == 'check_out') ? '(Wajib)' : '(Opsional)'; ?></label>
            <input type="file" name="photo" id="photo" accept="image/*" <?= ($mode == 'check_out') ? 'required' : ''; ?>>
        </div>

                <input type="hidden" name="latitude" id="latitude" <?php echo !$isIOS ? 'required' : ''; ?>>
                <input type="hidden" name="longitude" id="longitude" <?php echo !$isIOS ? 'required' : ''; ?>>
                
                <div id="locationStatus" class="location-status" style="display:none;"></div>

                <div class="form-group">
                    <button type="submit" id="submitBtn" disabled>Submit
                    </button>
                </div>
            </form>

            <div class="form-footer">
                <p>
                    <?php if ($mode == 'check_in'): ?>
                        Sudah absen masuk? <a href="?mode=check_out">Absen Pulang Di Sini</a>
                    <?php else: ?>
                        Belum absen masuk? <a href="?mode=check_in">Absen Masuk Di Sini</a>
                    <?php endif; ?>
                </p>
                <p><a href="../.."><i class="fas fa-home"></i> Kembali ke Beranda</a></p>
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

            // Deteksi apakah perangkat adalah iOS

            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;


            if (navigator.geolocation) {

                updateLocationStatus('Mendeteksi lokasi Anda...', 'location-info');


                // Untuk iOS, gunakan timeout yang lebih pendek

                const timeoutDuration = isIOS ? 5000 : 10000;


                // Jika iOS, tambahkan timeout manual untuk menghindari menunggu terlalu lama

                let timeoutId = null;

                if (isIOS) {

                    timeoutId = setTimeout(function () {

                        // Jika timeout, aktifkan tombol submit untuk iOS dan tampilkan pesan yang jelas

                        console.log('iOS location timeout triggered');

                        updateLocationStatus('<i class="fas fa-info-circle me-2"></i> <strong>Perangkat iOS:</strong> Silakan langsung klik tombol absen di bawah tanpa perlu mengaktifkan lokasi', 'location-info');

                        submitBtn.disabled = false;

                        submitBtn.innerHTML = '<i class="fas fa-' + (<?= json_encode($mode == 'check_in') ?> ? 'sign-in-alt' : 'sign-out-alt') + '"></i> ' + (<?= json_encode($mode == 'check_in') ?> ? 'Absen Masuk' : 'Absen Pulang');


                        // Hapus atribut required untuk iOS

                        if (latitudeInput) latitudeInput.removeAttribute('required');

                        if (longitudeInput) longitudeInput.removeAttribute('required');

                    }, timeoutDuration);

                }


                navigator.geolocation.getCurrentPosition(

                    function (position) {

                        // Jika berhasil mendapatkan lokasi, batalkan timeout untuk iOS

                        if (isIOS && timeoutId) {

                            clearTimeout(timeoutId);

                        }


                        const latitude = position.coords.latitude;

                        const longitude = position.coords.longitude;


                        latitudeInput.value = latitude;

                        longitudeInput.value = longitude;


                        updateLocationStatus('<i class="fas fa-check-circle me-2"></i> Lokasi berhasil dideteksi', 'location-info');

                        submitBtn.disabled = false;

                        console.log('Location detected:', latitude, longitude);

                    },

                    function (error) {

                        // Jika error mendapatkan lokasi, batalkan timeout untuk iOS

                        if (isIOS && timeoutId) {

                            clearTimeout(timeoutId);

                        }


                        let errorMessage = "Lokasi tidak dapat dideteksi: ";

                        switch (error.code) {

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


                        if (isIOS) {

                            // Untuk iOS, tampilkan pesan yang lebih jelas dan positif

                            // Tidak perlu menampilkan detail error teknis yang membingungkan

                            updateLocationStatus('<i class="fas fa-info-circle me-2"></i> Anda dapat melanjutkan absensi tanpa perlu mengaktifkan lokasi pada perangkat iOS.', 'location-info');


                            // Hapus atribut required untuk memastikan form dapat dikirim

                            if (latitudeInput) latitudeInput.removeAttribute('required');

                            if (longitudeInput) longitudeInput.removeAttribute('required');


                            // Aktifkan tombol submit dengan animasi untuk menarik perhatian

                            submitBtn.disabled = false;

                            submitBtn.classList.add('btn-pulse');

                            submitBtn.innerHTML = '<i class="fas fa-' + (<?= json_encode($mode == 'check_in') ?> ? 'sign-in-alt' : 'sign-out-alt') + '"></i> ' + (<?= json_encode($mode == 'check_in') ?> ? 'Absen Masuk' : 'Absen Pulang');

                        } else {

                            // Untuk non-iOS, tampilkan pesan error

                            updateLocationStatus(errorMessage, 'location-warning');

                            submitBtn.disabled = false;

                        }


                        console.log('Location error:', error.code, error.message);

                    },

                    {

                        enableHighAccuracy: true,

                        timeout: timeoutDuration,

                        maximumAge: 0

                    }

                );

            } else {

                updateLocationStatus("Browser Anda tidak mendukung geolokasi", 'location-error');

                submitBtn.disabled = false;

            }

        }


        // Deteksi apakah perangkat adalah iOS

        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;


        // Automatically get location when page loads for all devices

        document.addEventListener('DOMContentLoaded', function () {

            getLocation();


            // Add event listener to form submission

            document.getElementById('attendanceForm').addEventListener('submit', function (e) {

                // Untuk perangkat Android, pastikan lokasi terdeteksi

                if (navigator.userAgent.includes("Android") && (!latitudeInput.value || !longitudeInput.value)) {

                    e.preventDefault();

                    updateLocationStatus('Harap tunggu hingga lokasi terdeteksi...', 'location-error');

                    getLocation();

                }

                // Untuk iOS, izinkan submit form bahkan tanpa lokasi

                else if (isIOS && (!latitudeInput.value || !longitudeInput.value)) {

                    console.log('iOS device detected, allowing form submission without location');

                    // Hapus atribut required untuk memastikan form dapat dikirim

                    if (latitudeInput) latitudeInput.removeAttribute('required');

                    if (longitudeInput) longitudeInput.removeAttribute('required');

                }

            });

        });

    </script>

</body>

</html>