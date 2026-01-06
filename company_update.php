<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'koneksi.php'; // MySQLi connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Flash message (auto remove setelah tampil)
$status = $_SESSION['status'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['status'], $_SESSION['message']);

// Simpan form lama di session supaya tidak hilang
function old($key)
{
    return $_SESSION['old'][$key] ?? '';
}

// Generate CSRF Token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ==============================
// Keamanan dasar
// ==============================

$_SESSION['old'] = $_POST;

if (!isset($_SESSION['user']['company_code'])) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Akses tidak valid';
    header("Location: company");
    exit;
}

$company_code = $_SESSION['user']['company_code'];

// ==============================
// Ambil & sanitasi data POST
// ==============================
$id_company      = (int) $_POST['id_company'];
$nama_company    = $_POST['nama_company'];
$legal_name      = $_POST['legal_name'];
$company_type    = $_POST['company_type'];
$business_sector = $_POST['business_sector'];
$alamat_company  = $_POST['alamat_company'];
$nomor_company   = $_POST['nomor_company'];
$email_company   = $_POST['email_company'];
$province_id     = (int) $_POST['province_id'];
$regency_id      = (int) $_POST['regency_id'];
$district_id     = (int) $_POST['district_id'];
$village_id      = (int) $_POST['village_id'];
$postal_code     = $_POST['postal_code'];
$website         = $_POST['website'];

// ==============================
// Ambil data company lama (validasi + logo)
// ==============================
$stmt = $conn->prepare("
    SELECT company_logo 
    FROM company 
    WHERE id_company = ? AND code_company = ?
    LIMIT 1
");

if (!$stmt) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'PREPARE ERROR: ' . $conn->error;
    header("Location: company");
    exit;
}
$stmt->bind_param("is", $id_company, $company_code);
$stmt->execute();

$result = $stmt->get_result();
$oldCompany = $result->fetch_assoc();
$stmt->close();

if (!$oldCompany) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Company tidak ditemukan';
    header("Location: company");
    exit;
}

$company_logo = $oldCompany['company_logo'];

// ==============================
// Upload Logo (jika ada)
// ==============================
if (!empty($_FILES['company_logo']['name'])) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Format logo tidak diizinkan';
        header("Location: company");
        exit;
    }

    if ($_FILES['company_logo']['size'] > 2 * 1024 * 1024) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Ukuran logo maksimal 2MB';
        header("Location: company");
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['company_logo']['tmp_name']);
    finfo_close($finfo);

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowedMime)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'File bukan gambar valid';
        header("Location: company");
        exit;
    }

    $uploadDir = 'uploads/company_logo/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'logo_company_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    $old_logo = $company_logo; // simpan logo lama
    if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $filepath)) {
        $company_logo = $filepath;

        // hapus logo lama (kecuali default)
        if (
            $old_logo
            && file_exists($old_logo)
            && $old_logo !== 'uploads/company_logo/default.png'
        ) {
            unlink($old_logo);
        }
    }
}

//    if ($_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
//        $_SESSION['status'] = 'error';
//        $_SESSION['message'] = 'Gagal upload logo perusahaan';
//        header("Location: company");
//        exit;
//   }

// ==============================
// Update Company (MySQLi)
// ==============================
$update = $conn->prepare("
    UPDATE company SET
        nama_company    = ?,
        legal_name      = ?,
        company_type    = ?,
        business_sector = ?,
        alamat_company  = ?,
        nomor_company   = ?,
        email_company   = ?,
        province_id     = ?,
        regency_id      = ?,
        district_id     = ?,
        village_id      = ?,
        postal_code     = ?,
        website         = ?,
        company_logo    = ?,
        updated_at      = NOW()
    WHERE id_company = ?
      AND code_company = ?
");

$update->bind_param(
    "sssssssiiiisssis",
    $nama_company,
    $legal_name,
    $company_type,
    $business_sector,
    $alamat_company,
    $nomor_company,
    $email_company,
    $province_id,
    $regency_id,
    $district_id,
    $village_id,
    $postal_code,
    $website,
    $company_logo,
    $id_company,
    $company_code
);

$update->execute();
if ($update->execute()) {
    $_SESSION['status'] = 'success';
    $_SESSION['message'] = 'Data Perusahaan berhasil disimpan.';
}
$update->close();

// ==============================
// Redirect
// ==============================

header("Location: company");
exit;
