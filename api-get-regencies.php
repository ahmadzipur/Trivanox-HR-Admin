<?php
include 'error_handler.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$province_id = $_GET['province_id'] ?? null;

if (!$province_id) {
    echo json_encode([
        "success" => false,
        "data" => [],
        "message" => "Parameter province_id diperlukan"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, name FROM regencies WHERE province_id = ? ORDER BY name");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $province_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $regencies = [];
    while ($row = $result->fetch_assoc()) {
        $regencies[] = $row;
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "data" => $regencies,
        "message" => null
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "data" => [],
        "message" => $e->getMessage()
    ]);
}
?>
