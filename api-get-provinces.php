<?php
// file: api-get-provinces.php
include 'error_handler.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json'); // Pastikan output JSON

try {
    $stmt = $conn->prepare("SELECT id, name FROM provinces ORDER BY name");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $provinces = [];
    while ($row = $result->fetch_assoc()) {
        $provinces[] = $row;
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "data" => $provinces,
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
