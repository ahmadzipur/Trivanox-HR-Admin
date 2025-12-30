<?php
require '../koneksi.php';

$district_id = isset($_GET['district_id']) ? (int)$_GET['district_id'] : 0;

$stmt = $conn->prepare(
    "SELECT id, name FROM villages WHERE district_id = ? ORDER BY name"
);

$stmt->bind_param("i", $district_id);
$stmt->execute();

$result = $stmt->get_result();

echo '<option value="">-- Pilih Desa / Kelurahan --</option>';
while ($v = $result->fetch_assoc()) {
    echo "<option value='{$v['id']}'>{$v['name']}</option>";
}

$stmt->close();
