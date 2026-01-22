<?php
header("Content-Type: application/json");
include "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

$id_user = $_GET['id_user'] ?? 0;

$sql = "SELECT *
        FROM absensi
        WHERE id_user = ?
        ORDER BY tanggal DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

echo json_encode([
  "success" => true,
  "data" => $data
]);
