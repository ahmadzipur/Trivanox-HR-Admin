<?php
include 'koneksi.php';

header('Content-Type: application/json');
// ===== GET MODE (TAMPILKAN FORM) =====
$packages = mysqli_query($conn, "SELECT id_package, nama_package FROM package ORDER BY id_package ASC");

if ($packages->num_rows > 0) {
    $row = $packages->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'data' => $row
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Data karyawan tidak ditemukan'
    ]);
}

$conn->close();
