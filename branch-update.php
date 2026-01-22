<?php
include 'error_handler.php';
session_start();
require_once "koneksi.php";
include 'encryption.php';
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta
$now = date('Y-m-d H:i:s');
// Kunci rahasia
$key = 'AhmadZaelani23552011179';
// Inisialisasi kelas
$encryption = new Encryption($key);

$id_branch    = (int) $_POST['id_branch'];
$updated_by = $_SESSION['user_id'];
$id_branch_enc = urlencode($encryption->encrypt($id_branch));
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

$user_id = $_SESSION["user_id"]; 
$stmt = $conn->prepare("SELECT u.id, u.id_company, u.name,
      u.email, u.password, u.role, u.jabatan, u.foto_profile, u.remember_token,
      u.created_at AS user_created_at, c.nama_company, c.code_company, c.code_verification,
      c.alamat_company, c.status_company, c.expired_at, c.created_at AS company_created_at
  FROM users u LEFT JOIN company c ON u.id_company = c.id_company
  WHERE u.id = ? LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$_SESSION["user"] = [
  "id"              => $user["id"],
  "id_company"      => $user["id_company"],
  "name"            => $user["name"],
  "email"           => $user["email"],
  "role"            => $user["role"],
  "jabatan"         => $user["jabatan"],
  "foto_profile"    => $user["foto_profile"],
  "company_name"    => $user["nama_company"],
  "company_code"    => $user["code_company"],
  "status_company"  => $user["status_company"],
  "expired_at"      => $user["expired_at"],
  "company_address" => $user["alamat_company"]
];

$sesi_user = $_SESSION["user"];
$id_company = $sesi_user["id_company"];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: divisi");
    exit;
}

$updated_by     = $_SESSION['user_id'];
/* =========================
   DB TRANSACTION
========================= */
$conn->begin_transaction();

try {	
    /* ===== UPDATE DATA UTAMA ===== */	
    $sql = "UPDATE branch_company SET
        kode_cabang=?, nama_cabang=?, alamat=?, village_id=?, district_id=?, regency_id=?, province_id=?, postal_code=?, telepon=?, email=?, status=?, updated_at=?, updated_by=?
        WHERE id_branch=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssiiiisssssii",
        $_POST['kode_cabang'],
        $_POST['nama_cabang'],
        $_POST['alamat'],
        $_POST['village_id'],
        $_POST['district_id'],
        $_POST['regency_id'],
        $_POST['province_id'],
        $_POST['postal_code'],
        $_POST['telepon'],
        $_POST['email'],
        $_POST['status'],
        $now,
        $updated_by,
        $id_branch
    );
    $stmt->execute();
    $stmt->close();
    /* ===== COMMIT ===== */
    $conn->commit();
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Data divisi perusahaan berhasil diperbarui';

} catch (Exception $e) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = $e->getMessage();
}

/* =========================
   REDIRECT
========================= */
header("Location: detail-branch?id=".$id_branch_enc);
exit;
