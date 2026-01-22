<?php
include 'error_handler.php';
session_start();
require_once "koneksi.php";
include 'encryption.php';
date_default_timezone_set('Asia/Jakarta');
$now    = date('Y-m-d H:i:s');
    
// Kunci rahasia
$key = 'AhmadZaelani23552011179';
// Inisialisasi kelas
$encryption = new Encryption($key);
// Simpan form lama di session supaya tidak hilang
function old($key) {
  return $_SESSION['old'][$key] ?? '';
}
/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['user_id'])) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    header("Location: login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: karyawan");
    exit;
}

$checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkEmail->bind_param("s", $_POST['email']);
$checkEmail->execute();
$checkEmail->store_result();

if ($checkEmail->num_rows > 0) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Email sudah terdaftar, gunakan email lain.';
    header("Location: tambah-karyawan");
    exit;
}
$checkEmail->close();

$updated_by = $_SESSION['user_id'];
$role = 'karyawan';
$id_company = $_SESSION["user"]["id_company"];
$_SESSION['old'] = $_POST;

/* =========================
   DB TRANSACTION
========================= */
$conn->begin_transaction();

try {	 	
    $sql = "INSERT INTO job_divisions (
        id_company, kode_divisi, nama_divisi, deskripsi, status, created_at, updated_at, created_by, updated_by
    ) VALUES (?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
    "issssssii",
        $id_company, $_POST['kode_divisi'], $_POST['nama_divisi'],
        $_POST['deskripsi'], $_POST['status_divisi'], $now, $now, $updated_by, $updated_by
    );
    
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();

    /* ===== COMMIT ===== */
    $conn->commit();
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Data divisi berhasil ditambahkan.';
    unset($_SESSION['old']);
} catch (Exception $e) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = $e->getMessage();
}

/* =========================
   REDIRECT
========================= */
header("Location: divisi");
exit;
