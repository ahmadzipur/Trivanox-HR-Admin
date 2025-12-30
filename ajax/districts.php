<?php
require '../koneksi.php';

$regency_id = isset($_GET['regency_id']) ? (int)$_GET['regency_id'] : 0;

$stmt = $conn->prepare(
    "SELECT id, name FROM districts WHERE regency_id = ? ORDER BY name"
);

$stmt->bind_param("i", $regency_id);
$stmt->execute();

$result = $stmt->get_result();

echo '<option value="">-- Pilih Kecamatan --</option>';
while ($d = $result->fetch_assoc()) {
    echo "<option value='{$d['id']}'>{$d['name']}</option>";
}

$stmt->close();
