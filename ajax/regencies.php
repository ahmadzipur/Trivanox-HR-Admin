<?php
require '../koneksi.php';

$province_id = $_GET['province_id'];

$stmt = $conn->prepare("SELECT id, name FROM regencies WHERE province_id = ? ORDER BY name");
$stmt->bind_param("i", $province_id);
$stmt->execute();

$result = $stmt->get_result();

echo '<option value="">-- Pilih Kabupaten / Kota --</option>';
while ($r = $result->fetch_assoc()) {
    echo "<option value='{$r['id']}'>{$r['name']}</option>";
}

$stmt->close();
