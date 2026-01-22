<?php
include 'error_handler.php';
session_start();
require_once "koneksi.php";
include 'encryption.php';
date_default_timezone_set('Asia/Jakarta');
$datenow    = date('Y-m-d');
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
    header("Location: branch");
    exit;
}

$updated_by = $_SESSION['user_id'];
$role = 'karyawan';
$id_company = $_SESSION["user"]["id_company"];
$_SESSION['old'] = $_POST;
             
/* =========================
   DB TRANSACTION
========================= */
$conn->begin_transaction();

try {
    $sql = "INSERT INTO branch_company (
        id_company,	kode_cabang, nama_cabang, alamat, village_id, district_id, regency_id, province_id, postal_code, telepon, email, status, created_at, updated_at, created_by, updated_by
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
    )";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param(
    "isssiiiissssssii",
        $id_company, $_POST['kode_cabang'], $_POST['nama_cabang'], $_POST['alamat'], $_POST['village_id'],
        $_POST['district_id'], $_POST['regency_id'],
        $_POST['province_id'], $_POST['postal_code'],
        $_POST['telepon'], $newFiles['email'],
        $_POST['status'], $now, $now, $updated_by, $updated_by
    );
    
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();

    /* ===== COMMIT ===== */
    $conn->commit();
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Data cabang perusahaan berhasil ditambahkan.';
    unset($_SESSION['old']);
} catch (Exception $e) {
    /* ===== ROLLBACK DB ===== */
    $conn->rollback();

    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Gagal menyimpan data cabang perusahaan'; // $e->getMessage();
}

/* =========================
   REDIRECT
========================= */
header("Location: branch");
exit;
