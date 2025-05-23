<?php
/**
 * Supabase PHP Integration
 * 
 * File ini menyediakan fungsi-fungsi untuk berinteraksi dengan Supabase API dari PHP
 * menggunakan cURL untuk permintaan HTTP.
 */

// Load environment variables from .env file
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Supabase credentials
$supabaseUrl = $_ENV['SUPABASE_URL'];
$supabaseKey = $_ENV['SUPABASE_KEY'];

if (!$supabaseUrl || !$supabaseKey) {
    die('SUPABASE_URL dan SUPABASE_KEY harus diatur dalam file .env');
}

/**
 * Melakukan permintaan ke Supabase API
 * 
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param string $endpoint API endpoint
 * @param array $data Data untuk dikirim (opsional)
 * @return array Response data
 */
function supabaseRequest($method, $endpoint, $data = null) {
    global $supabaseUrl, $supabaseKey;
    
    $url = $supabaseUrl . $endpoint;
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $supabaseKey,
        'apikey: ' . $supabaseKey
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => $error,
            'status' => $statusCode
        ];
    }
    
    return [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'data' => json_decode($response, true),
        'status' => $statusCode
    ];
}

/**
 * Mengambil data dari tabel Supabase
 * 
 * @param string $table Nama tabel
 * @param array $params Parameter query (opsional)
 * @return array Data dari tabel
 */
function getSupabaseData($table, $params = []) {
    $endpoint = '/rest/v1/' . $table;
    
    // Build query string if params provided
    if (!empty($params)) {
        $queryString = http_build_query($params);
        $endpoint .= '?' . $queryString;
    }
    
    return supabaseRequest('GET', $endpoint);
}

/**
 * Menyimpan data ke tabel Supabase
 * 
 * @param string $table Nama tabel
 * @param array $data Data yang akan disimpan
 * @return array Response dari Supabase
 */
function insertSupabaseData($table, $data) {
    $endpoint = '/rest/v1/' . $table;
    return supabaseRequest('POST', $endpoint, $data);
}

/**
 * Memperbarui data di tabel Supabase
 * 
 * @param string $table Nama tabel
 * @param array $data Data yang akan diperbarui
 * @param string $column Nama kolom untuk filter (biasanya 'id')
 * @param mixed $value Nilai untuk filter
 * @return array Response dari Supabase
 */
function updateSupabaseData($table, $data, $column, $value) {
    $endpoint = '/rest/v1/' . $table . '?' . $column . '=eq.' . $value;
    return supabaseRequest('PATCH', $endpoint, $data);
}

/**
 * Menghapus data dari tabel Supabase
 * 
 * @param string $table Nama tabel
 * @param string $column Nama kolom untuk filter (biasanya 'id')
 * @param mixed $value Nilai untuk filter
 * @return array Response dari Supabase
 */
function deleteSupabaseData($table, $column, $value) {
    $endpoint = '/rest/v1/' . $table . '?' . $column . '=eq.' . $value;
    return supabaseRequest('DELETE', $endpoint);
}

// Contoh penggunaan:
/*
// Mengambil semua data absensi
$attendanceData = getSupabaseData('public_attendance');
if ($attendanceData['success']) {
    $records = $attendanceData['data'];
    // Proses data
} else {
    echo "Error: " . json_encode($attendanceData);
}

// Menyimpan data absensi baru
$newAttendance = [
    'full_name' => 'Nama Lengkap',
    'nim' => '12345678',
    'prodi' => 'Teknik Informatika',
    'date' => date('Y-m-d'),
    'check_in_time' => date('H:i:s'),
    'latitude_in' => -6.914744,
    'longitude_in' => 107.609810
];

$result = insertSupabaseData('public_attendance', $newAttendance);
if ($result['success']) {
    echo "Data berhasil disimpan";
} else {
    echo "Error: " . json_encode($result);
}
*/
?>