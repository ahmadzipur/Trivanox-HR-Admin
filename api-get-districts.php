<?php
include 'error_handler.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

$regency_id = $_GET['regency_id'] ?? null;

if (!$regency_id) {
    echo json_encode([
        "success" => false,
        "data" => [],
        "message" => "Parameter regency_id diperlukan"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, name FROM districts WHERE regency_id = ? ORDER BY name");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $regency_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $districts = [];
    while ($row = $result->fetch_assoc()) {
        $districts[] = $row;
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "data" => $districts,
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
