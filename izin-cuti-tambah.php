<?php
include 'error_handler.php';
session_start();
require_once "koneksi.php";
date_default_timezone_set('Asia/Jakarta');
$datenow    = date('Y-m-d');
$now    = date('Y-m-d H:i:s');
// Simpan form lama di session supaya tidak hilang
function old($key) {
  return $_SESSION['old'][$key] ?? '';
}
/* =========================
   CEK LOGIN
========================= */

if (isset($_SESSION["user_id"])) {
    $stmt = $conn->prepare("
      SELECT u.*, c.nama_company 
      FROM users u
      LEFT JOIN company c ON u.id_company = c.id_company
      WHERE u.id = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $user = $result->fetch_assoc();

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user"] = [
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
} else if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember"])) {
    $token = $_COOKIE["remember"];
    $stmt = $conn->prepare("
      SELECT u.*, c.nama_company 
      FROM users u
      LEFT JOIN company c ON u.id_company = c.id_company
      WHERE u.remember_token = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $user = $result->fetch_assoc();

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user"] = [
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
} else if (!isset($_SESSION["user_id"]) && !isset($_COOKIE["remember"])) {
    $_SESSION["status"] = "error";
    $_SESSION["message"] = "Silakan login terlebih dahulu.";
    header("Location: login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index");
    exit;
}

$id_user    = $_SESSION['user_id'];
$sesi_user  = $_SESSION['user'];
$id_company = $sesi_user['id_company'];
$_SESSION['old'] = $_POST;

/* =========================
   FUNGSI UPLOAD (SAFE)
========================= */
function uploadFileSafe($file, $folder, $allowedExt, $maxSize = 2097152) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload file gagal");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        throw new Exception("Format file tidak diizinkan");
    }

    if ($file['size'] > $maxSize) {
        throw new Exception("Ukuran file maksimal 2MB");
    }

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $filename = uniqid('file_', true) . "." . $ext;
    $path = $folder . "/" . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new Exception("Gagal menyimpan file");
    }

    return $path;
}

/* =========================
   DB TRANSACTION
========================= */
$conn->begin_transaction();

$newFiles = [];

try {
    /* ===== UPLOAD FILE BARU ===== */
    if (!empty($_FILES['file_pendukung']['name'])) {
        $newFiles['file_pendukung'] = uploadFileSafe(
            $_FILES['file_pendukung'] ?? null,
            'uploads/izin_cuti',
            ['jpg','jpeg','png','webp', 'pdf']
        );
    } else {
        $newFiles['file_pendukung'] = 'uploads/izin_cuti/default.webp';
    }

    $sql = "INSERT INTO request_izin_cuti (
        id_user, id_company, jenis, kategori, tanggal_mulai, tanggal_selesai, jumlah_hari,
        is_half_day, alasan, file_pendukung, created_at, updated_at, created_by, updated_by
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,
        ?,?,?,?
    )";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['status']  = 'error';
        $_SESSION['message'] = "Data prepare failed";
        // die("Prepare failed: " . $conn->error);
    }
    
    $jumlah_hari = (int) ($_POST['jumlah_hari'] ?? 0);
    $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
    
    $jenis     = $_POST['jenis'] ?? '';
    $kategori  = $_POST['kategori'] ?? '';
    $alasan    = $_POST['alasan'] ?? '';
    $tgl_mulai = $_POST['tanggal_mulai'] ?? '';
    $tgl_selesai = $_POST['tanggal_selesai'] ?? '';

    $stmt->bind_param(
        "iissssiissssii",
        $id_user,
        $id_company,
        $jenis,
        $kategori,
        $tgl_mulai,
        $tgl_selesai,
        $jumlah_hari,
        $is_half_day,
        $alasan,
        $newFiles['file_pendukung'],
        $now,
        $now,
        $id_user,
        $id_user
    );
    
    if (!$stmt->execute()) {
        $_SESSION['status']  = 'error';
        $_SESSION['message'] = "Execute failed";
        //die("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();

    $title = 'Peringatan ' . $jenis;
    $message = $_SESSION["user"]['name'] . ' mengajukan ' . $jenis . ' kategori ' . $kategori;
    $target_page = 'data-izin-cuti';
    $icon = 'zmdi zmdi-assignment-check';
    $color = 'warning';
        
    // 1. Ambil semua id staff
    $idStaff = [];
    
    $qStaff = $conn->prepare("SELECT id FROM users WHERE id_company = ? AND role = ?");
    $role = 'staff';
    $qStaff->bind_param("is", $id_company, $role);
    $qStaff->execute();
    
    $result = $qStaff->get_result();
    while ($row = $result->fetch_assoc()) {
        $idStaff[] = $row['id'];
    }
    
    // 2. Insert ke tabel notifications
    $stmtNotif = $conn->prepare("
        INSERT INTO notifications (id_user, title, message, target_page, target_id, icon, color, created_at, created_by, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($idStaff as $targetId) {
        $stmtNotif->bind_param("isssisssis", $id_user, $title, $message, $target_page, $targetId, $icon, $color, $now, $id_user, $now);
        $stmtNotif->execute();
    }
    
    /* ===== COMMIT ===== */
    $conn->commit();
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Form ' . $_POST['jenis'] . ' berhasil diajukan.';
    unset($_SESSION['old']);
} catch (Exception $e) {
    /* ===== ROLLBACK DB ===== */
    $conn->rollback();

    // Simpan error ke session (PRODUKSI MODE)
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';

    // Optional: log ke file
    error_log("[IZIN_CUTI] ".$e->getMessage());
}

/* =========================
   REDIRECT
========================= */
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/app_error.log');
header("Location: index");
exit;
