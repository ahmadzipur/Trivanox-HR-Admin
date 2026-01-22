<?php
include 'error_handler.php';
session_start();
require_once "koneksi.php";
include 'encryption.php';
// Kunci rahasia
$key = 'AhmadZaelani23552011179';
// Inisialisasi kelas
$encryption = new Encryption($key);

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
    header("Location: karyawan");
    exit;
}

$id_user     = (int) $_POST['id_karyawan'];
$updated_by = $_SESSION['user_id'];
$id_karyawan = urlencode($encryption->encrypt($id_user));

$checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkEmail->bind_param("s", $_POST['email']);
$checkEmail->execute();
$checkEmail->bind_result($id);

if ($checkEmail->fetch()) {
    if($id!==$id_user){
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Email sudah terdaftar, gunakan email lain.';
        header("Location: detail-karyawan?id=".$id_karyawan);
        exit;
    };
}

$checkEmail->close();
/* =========================
   AMBIL FILE LAMA
========================= */
$stmtOld = $conn->prepare("
    SELECT foto_profile, file_ktp, file_kk, file_cv, file_npwp 
    FROM users WHERE id = ?
");
$stmtOld->bind_param("i", $id_user);
$stmtOld->execute();
$oldFile = $stmtOld->get_result()->fetch_assoc();
$stmtOld->close();

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
$oldFilesToDelete = [];

try {

    /* ===== UPLOAD FILE BARU ===== */
    $newFiles['foto_profile'] = uploadFileSafe(
        $_FILES['foto_profile'] ?? null,
        'uploads/foto_profile',
        ['jpg','jpeg','png','webp']
    );

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

    /* ===== UPDATE DATA UTAMA ===== */
    $sql = "UPDATE users SET
        nik=?, nip=?, name=?, nama_panggilan=?, tempat_lahir=?, tanggal_lahir=?, jabatan=?,
        jenis_kelamin=?, status_pernikahan=?, agama=?, email=?, nomor_hp=?, alamat=?,
        province_id=?, regency_id=?, district_id=?, village_id=?, postal_code=?,
        tanggal_masuk=?, tanggal_keluar=?, user_status=?, status=?, id_division=?, id_branch=?,
        gaji_pokok=?, tunjangan_transport=?, tunjangan_makan=?, tunjangan_jabatan=?,
        tunjangan_lainnya=?, ket_tunjangan_lainnya=?, rekening_bank=?, nomor_rekening=?,
        npwp=?, bpjs_tk=?, bpjs_kes=?, updated_at=NOW(), updated_by=?
        WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssssiiiisssssiisssssssssssii",
        $_POST['nik'],
        $_POST['nip'],
        $_POST['name'],
        $_POST['nama_panggilan'],
        $_POST['tempat_lahir'],
        $_POST['tanggal_lahir'],
        $_POST['jabatan'],
        $_POST['jenis_kelamin'],
        $_POST['status_pernikahan'],
        $_POST['agama'],
        $_POST['email'],
        $_POST['nomor_hp'],
        $_POST['alamat'],
        $_POST['province_id'],
        $_POST['regency_id'],
        $_POST['district_id'],
        $_POST['village_id'],
        $_POST['postal_code'],
        $_POST['tanggal_masuk'],
        $_POST['tanggal_keluar'],
        $_POST['user_status'],
        $_POST['status'],
        $_POST['id_division'],
        $_POST['id_branch'],
        $_POST['gaji_pokok'],
        $_POST['tunjangan_transport'],
        $_POST['tunjangan_makan'],
        $_POST['tunjangan_jabatan'],
        $_POST['tunjangan_lainnya'],
        $_POST['ket_tunjangan_lainnya'],
        $_POST['rekening_bank'],
        $_POST['nomor_rekening'],
        $_POST['npwp'],
        $_POST['bpjs_tk'],
        $_POST['bpjs_kes'],
        $updated_by,
        $id_user
    );
    $stmt->execute();
    $stmt->close();

    /* ===== UPDATE FILE KE DB ===== */
    $fields = [];
    $params = [];
    $types  = "";

    foreach ($newFiles as $col => $path) {
        if ($path) {
            $fields[] = "$col = ?";
            $params[] = $path;
            $types   .= "s";

            if (!empty($oldFile[$col])) {
                $oldFilesToDelete[] = $oldFile[$col];
            }
        }
    }

    if ($fields) {
        $sqlFile = "UPDATE users SET ".implode(", ", $fields)." WHERE id = ?";
        $params[] = $id_user;
        $types   .= "i";

        $stmtFile = $conn->prepare($sqlFile);
        $stmtFile->bind_param($types, ...$params);
        $stmtFile->execute();
        $stmtFile->close();
    }

    /* ===== COMMIT ===== */
    $conn->commit();

    /* ===== HAPUS FILE LAMA ===== */
    foreach ($oldFilesToDelete as $file) {
        if (file_exists($file)) {
            if($file==='uploads/foto_profile/default.webp'){
            } else {
                unlink($file);
                
            }
        }
    }

    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Data karyawan berhasil diperbarui';

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
header("Location: detail-karyawan?id=".$id_karyawan);
exit;
