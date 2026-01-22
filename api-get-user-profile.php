<?php
include 'error_handler.php';
header('Content-Type: application/json');
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$id_user = $_GET['id_user'] ?? null;

if (!$id_user) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

// JOIN users dengan company untuk mendapatkan nama_company
$stmt = $conn->prepare("
    SELECT u.*, c.nama_company
    FROM users u 
    JOIN company c ON u.id_company = c.id_company
    WHERE u.id = ?
");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo json_encode(['success' => true, 'data' => $user]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>
