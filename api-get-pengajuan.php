<?php
header('Content-Type: application/json');
include 'error_handler.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ================= VALIDASI =================
if (!isset($_GET['id_user']) || empty($_GET['id_user'])) {
    echo json_encode([
        "success" => false,
        "message" => "Belum ada pengajuan."
    ]);
    exit;
}

$id_user = intval($_GET['id_user']);

// ================= QUERY =================
$stmt = $conn->prepare("
    SELECT 
        r.*,
        ua.name AS approved_by_name,
        uc.name AS created_by_name,
        uu.name AS updated_by_name
    FROM request_izin_cuti r
    LEFT JOIN users ua ON r.approved_by = ua.id
    LEFT JOIN users uc ON r.created_by = uc.id
    LEFT JOIN users uu ON r.updated_by = uu.id
    WHERE id_user = ?
    ORDER BY created_at DESC LIMIT 25
");

$stmt->bind_param("i", $id_user);
$stmt->execute();

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();

// ================= RESPONSE =================
echo json_encode([
    "success" => true,
    "data" => $data
]);
