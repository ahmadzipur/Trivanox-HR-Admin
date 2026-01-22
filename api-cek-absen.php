<?php
header("Content-Type: application/json");
include "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

$id_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

try {
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE id_user = ? AND tanggal = ?");
    $stmt->execute([$id_user, $tanggal]);
    $absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $absensi
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil data: ".$e->getMessage()
    ]);
}
