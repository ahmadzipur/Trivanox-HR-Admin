<?php
include 'error_handler.php';
include "koneksi.php";
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // jika dibutuhkan CORS
header('Access-Control-Allow-Methods: GET');

// ====================
// Ambil parameter id
// ====================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID absen tidak valid'
    ]);
    exit;
}

// ====================
// Query ke tabel absensi
// ====================
$sql = "SELECT * FROM absensi WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();

    // pastikan foto & lokasi tidak null
    $data['foto_masuk'] = !empty($data['foto_masuk']) ? $data['foto_masuk'] : null;
    $data['foto_pulang'] = !empty($data['foto_pulang']) ? $data['foto_pulang'] : null;
    $data['latitude_masuk'] = !empty($data['latitude_masuk']) ? $data['latitude_masuk'] : null;
    $data['longitude_masuk'] = !empty($data['longitude_masuk']) ? $data['longitude_masuk'] : null;
    $data['latitude_pulang'] = !empty($data['latitude_pulang']) ? $data['latitude_pulang'] : null;
    $data['longitude_pulang'] = !empty($data['longitude_pulang']) ? $data['longitude_pulang'] : null;

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Data absen tidak ditemukan'
    ]);
}

$stmt->close();
$conn->close();
?>