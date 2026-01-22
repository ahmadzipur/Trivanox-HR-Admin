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
    if (!empty($_FILES['foto_profile']['name'])) {
        $newFiles['foto_profile'] = uploadFileSafe(
            $_FILES['foto_profile'] ?? null,
            'uploads/foto_profile',
            ['jpg','jpeg','png','webp']
        );
    } else {
        $newFiles['foto_profile'] = 'uploads/foto_profile/default.webp';
    }

    $newFiles['file_ktp'] = uploadFileSafe(
        $_FILES['file_ktp'] ?? null,
        'uploads/file_ktp',
        ['jpg','jpeg','png','pdf']
    );

    $newFiles['file_kk'] = uploadFileSafe(
        $_FILES['file_kk'] ?? null,
        'uploads/file_kk',
        ['jpg','jpeg','png','pdf']
    );

    $newFiles['file_cv'] = uploadFileSafe(
        $_FILES['file_cv'] ?? null,
        'uploads/file_cv',
        ['pdf','doc','docx']
    );

    $newFiles['file_npwp'] = uploadFileSafe(
        $_FILES['file_npwp'] ?? null,
        'uploads/file_npwp',
        ['jpg','jpeg','png','pdf']
    );

    $sql = "INSERT INTO users (
        id_company, nik, nip, name, nama_panggilan, tempat_lahir, tanggal_lahir,
        jenis_kelamin, status_pernikahan, agama, email, nomor_hp, alamat,
        province_id, regency_id, district_id, village_id, postal_code,
        tanggal_masuk, tanggal_keluar, user_status, id_division, id_branch,
        gaji_pokok, tunjangan_transport, tunjangan_makan, tunjangan_jabatan,
        tunjangan_lainnya, ket_tunjangan_lainnya, rekening_bank, nomor_rekening,
        npwp, bpjs_tk, bpjs_kes, foto_profile, file_ktp, file_kk, file_cv, file_npwp,
        role, jabatan, created_at, updated_at, created_by, updated_by
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
    )";

    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $tanggal_masuk = !empty($_POST['tanggal_masuk'])
    ? $_POST['tanggal_masuk']
    : $datenow;
    if(empty($_POST['user_status'])){
        $user_status = 'training';
    } else {
        $user_status = $_POST['user_status'];
    }
    $stmt->bind_param(
    "issssssssssssiiiissssiidddddsssssssssssssssss",
        $id_company, $_POST['nik'], $_POST['nip'],
        $_POST['name'], $_POST['nama_panggilan'],
        $_POST['tempat_lahir'], $_POST['tanggal_lahir'],
        $_POST['jenis_kelamin'], $_POST['status_pernikahan'],
        $_POST['agama'], $_POST['email'],
        $_POST['nomor_hp'], $_POST['alamat'],
        $_POST['province_id'], $_POST['regency_id'],
        $_POST['district_id'], $_POST['village_id'],
        $_POST['postal_code'], $tanggal_masuk,
        $_POST['tanggal_keluar'], $user_status,
        $_POST['id_division'], $_POST['id_branch'],
        $_POST['gaji_pokok'], $_POST['tunjangan_transport'],
        $_POST['tunjangan_makan'], $_POST['tunjangan_jabatan'],
        $_POST['tunjangan_lainnya'], $_POST['ket_tunjangan_lainnya'],
        $_POST['rekening_bank'], $_POST['nomor_rekening'],
        $_POST['npwp'], $_POST['bpjs_tk'], $_POST['bpjs_kes'], $newFiles['foto_profile'],
        $newFiles['file_ktp'], $newFiles['file_kk'], $newFiles['file_cv'], $newFiles['file_npwp'],
        $role, $_POST['jabatan'], $now, $now, $updated_by, $updated_by
    );
    
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();


    /* ===== COMMIT ===== */
    $conn->commit();
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Data karyawan berhasil ditambahkan.';
    unset($_SESSION['old']);
} catch (Exception $e) {
    /* ===== ROLLBACK DB ===== */
    $conn->rollback();

    /* ===== HAPUS FILE BARU (JIKA ADA) ===== */
    foreach ($newFiles as $file) {
        if ($file && file_exists($file)) {
            unlink($file);
        }
    }

    $_SESSION['status']  = 'error';
    $_SESSION['message'] = $e->getMessage();
}

/* =========================
   REDIRECT
========================= */
header("Location: karyawan");
exit;
