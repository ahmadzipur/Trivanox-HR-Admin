<?php
include 'error_handler.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$district_id = $_GET['district_id'] ?? null;

if (!$district_id) {
    echo json_encode([
        "success" => false,
        "data" => [],
        "message" => "Parameter district_id diperlukan"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, name FROM villages WHERE district_id = ? ORDER BY name");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $district_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $villages = [];
    while ($row = $result->fetch_assoc()) {
        $villages[] = $row;
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "data" => $villages,
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
