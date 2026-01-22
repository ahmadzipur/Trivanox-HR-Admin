<?php
include 'error_handler.php';
include 'koneksi.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Jakarta');
$id_user = $_GET['user_id'] ?? null;

if (!$id_user) {
    echo json_encode([
        "success" => false,
        "message" => "Data user tidak ditemukan"
    ]);
    exit;
}

$query = mysqli_query($conn, "
    SELECT bc.nama_cabang
    FROM users u
    JOIN branch_company bc ON u.id_branch = bc.id_branch
    WHERE u.id = '$id_user'
    LIMIT 1
");

if ($row = mysqli_fetch_assoc($query)) {
    echo json_encode([
        "success" => true,
        "nama_cabang" => $row['nama_cabang']
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Cabang tidak ditemukan"
    ]);
}

