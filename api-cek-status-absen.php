<?php
include 'error_handler.php';
header('Content-Type: application/json');
require_once "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

$id_user = $_GET['id_user'] ?? null;
$datenow = date('Y-m-d');

if (!$id_user) {
    echo json_encode([
        "success" => false,
        "message" => "id_user tidak ada"
    ]);
    exit;
}

// Cek data absensi
$sql_cek_absen = "SELECT *
        FROM absensi
        WHERE id_user = ?
        AND tanggal = ?
        LIMIT 1";

$stmt = $conn->prepare($sql_cek_absen);
$stmt->bind_param("is", $id_user, $datenow);
$stmt->execute();
$result = $stmt->get_result();

$status_absen = 0;
$label = "Belum Absen";

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();

    if (empty($data['jam_masuk'])) {
        $status_absen = 0;
        $label = "Belum Absen Masuk";
    } else if (empty($data['jam_mulai_istirahat'])) {
        $status_absen = 1;
        $label = "Sudah Masuk";
    } else if (empty($data['jam_selesai_istirahat'])) {
        $status_absen = 2;
        $label = "Sedang Istirahat";
    } else if (empty($data['jam_pulang'])) {
        $status_absen = 3;
        $label = "Selesai Istirahat";
    } else {
        $status_absen = 4;
        $label = "Sudah Pulang";
    }

    if (empty($data['jam_mulai_istirahat']) && !empty($data['jam_pulang'])) {
        $status_absen = 4;
        $label = "Sudah Pulang";
    }
}

$stmt->close();

echo json_encode([
    "success" => true,
    "status" => $status_absen,
    "label" => $label
]);