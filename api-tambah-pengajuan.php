<?php
// file: api_tambah_pengajuan.php
include 'error_handler.php';
session_start();
require_once "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id_user    = $_POST['id_user'];
$sesi_user = [];
$stmt = $conn->prepare("
      SELECT u.*, c.nama_company 
      FROM users u
      LEFT JOIN company c ON u.id_company = c.id_company
      WHERE u.id = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $id_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $user = $result->fetch_assoc();
        $sesi_user = [
          "id" => $user["id"],
          "id_company" => $user["id_company"],
          "name" => $user["name"],
          "email" => $user["email"],
          "role" => $user["role"],
          "jabatan" => $user["jabatan"],
          "foto_profile" => $user["foto_profile"],
          "company_name" => $user["nama_company"]
        ];
    }

    $stmt->close();
$id_company = $sesi_user['id_company'];

$now = date('Y-m-d H:i:s');

// =========================
// UPLOAD FILE
// =========================
$filePendukungPath = '';
if (isset($_FILES['file_pendukung']) && $_FILES['file_pendukung']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['jpg','jpeg','png','webp','pdf'];
    $ext = strtolower(pathinfo($_FILES['file_pendukung']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['status' => 'error', 'message' => 'Format file tidak diizinkan']);
        exit;
    }
    if (!is_dir('uploads/izin_cuti')) mkdir('uploads/izin_cuti', 0777, true);
    $filename = uniqid('file_', true) . "." . $ext;
    $filePendukungPath = 'uploads/izin_cuti/' . $filename;
    move_uploaded_file($_FILES['file_pendukung']['tmp_name'], $filePendukungPath);
}

// =========================
// AMBIL DATA POST
// =========================
$jenis        = $_POST['jenis'] ?? '';
$kategori     = $_POST['kategori'] ?? '';
$tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
$tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
$jumlah_hari  = (int) ($_POST['jumlah_hari'] ?? 0);
$is_half_day = isset($_POST['is_half_day']) ? (int)$_POST['is_half_day'] : 0;
$alasan       = $_POST['alasan'] ?? '';

// =========================
// SIMPAN KE DB
// =========================
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO request_izin_cuti (
            id_user, id_company, jenis, kategori, tanggal_mulai, tanggal_selesai,
            jumlah_hari, is_half_day, alasan, file_pendukung, created_at, updated_at, created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "iissssiissssii",
        $id_user,
        $id_company,
        $jenis,
        $kategori,
        $tanggal_mulai,
        $tanggal_selesai,
        $jumlah_hari,
        $is_half_day,
        $alasan,
        $filePendukungPath,
        $now,
        $now,
        $id_user,
        $id_user
    );
    $stmt->execute();
    $stmt->close();

    // Kirim notifikasi ke staff
    $title = 'Peringatan ' . $jenis;
    $message = $sesi_user['name'] . ' mengajukan ' . $jenis . ' kategori ' . $kategori;
    $target_page = 'data-izin-cuti';
    $icon = 'zmdi zmdi-assignment-check';
    $color = 'warning';

    $idStaff = [];
    $qStaff = $conn->prepare("SELECT id FROM users WHERE id_company = ? AND role = ?");
    $role = 'staff';
    $qStaff->bind_param("is", $id_company, $role);
    $qStaff->execute();
    $result = $qStaff->get_result();
    while ($row = $result->fetch_assoc()) {
        $idStaff[] = $row['id'];
    }

    $stmtNotif = $conn->prepare("
        INSERT INTO notifications (id_user, title, message, target_page, target_id, icon, color, created_at, created_by, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($idStaff as $targetId) {
        $stmtNotif->bind_param("isssisssis", $id_user, $title, $message, $target_page, $targetId, $icon, $color, $now, $id_user, $now);
        $stmtNotif->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Pengajuan berhasil disimpan']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("[IZIN_CUTI] " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat menyimpan data']);
}
