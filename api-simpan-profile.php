<?php
// api_update_profile.php

include 'error_handler.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

include 'koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => false,
        'message' => 'Method tidak diizinkan'
    ]);
    exit;
}

try {
    // ======================
    // DATA WAJIB
    // ======================
    $id = $_POST['id'] ?? null;

    if (!$id) {
        throw new Exception("ID user tidak ditemukan");
    }

    // ======================
    // DATA PROFIL
    // ======================
    $nip            = $_POST['nip'] ?? null;
    $nik            = $_POST['nik'] ?? null;
    $name           = $_POST['name'] ?? null;
    $nama_panggilan = $_POST['nama_panggilan'] ?? null;
    $tempat_lahir   = $_POST['tempat_lahir'] ?? null;
    $tanggal_lahir  = $_POST['tanggal_lahir'] ?? null;
    $jenis_kelamin  = $_POST['jenis_kelamin'] ?? null;
    $status_nikah   = $_POST['status_pernikahan'] ?? null;
    $agama          = $_POST['agama'] ?? null;
    $email          = $_POST['email'] ?? null;
    $nomor_hp       = $_POST['nomor_hp'] ?? null;
    $alamat         = $_POST['alamat'] ?? null;
	$village_id     = $_POST['village_id'] ?? null;
	$district_id    = $_POST['district_id'] ?? null;
    $regency_id     = $_POST['regency_id'] ?? null;
	$province_id    = $_POST['province_id'] ?? null;
    $postal_code    = $_POST['postal_code'] ?? null;
    $tanggal_masuk  = $_POST['tanggal_masuk'] ?? null;
    $tanggal_keluar = $_POST['tanggal_keluar'] ?? null;
    $jabatan        = $_POST['jabatan'] ?? null;
    $user_status    = $_POST['user_status'] ?? null;

    $npwp           = $_POST['npwp'] ?? null;
    $bpjs_tk        = $_POST['bpjs_tk'] ?? null;
    $bpjs_kes       = $_POST['bpjs_kes'] ?? null;
    $rekening_bank  = $_POST['rekening_bank'] ?? null;
    $nomor_rekening = $_POST['nomor_rekening'] ?? null;

    // ======================
    // VALIDASI EMAIL UNIK
    // ======================
    $check = $conn->prepare(
        "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
    );
    $check->bind_param("si", $email, $id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        echo json_encode([
            'status' => false,
            'message' => 'Email sudah digunakan oleh pengguna lain'
        ]);
        exit;
    }

    // ======================
    // AMBIL FOTO LAMA
    // ======================
    $oldFoto = null;
    $getOld = $conn->prepare("SELECT foto_profile FROM users WHERE id = ? LIMIT 1");
    $getOld->bind_param("i", $id);
    $getOld->execute();
    $getOld->bind_result($oldFoto);
    $getOld->fetch();
    $getOld->close();

    // ======================
    // FOTO (OPTIONAL)
    // ======================
    $fotoName = null;

    if (
        isset($_FILES['foto_profile']) &&
        $_FILES['foto_profile']['error'] === UPLOAD_ERR_OK
    ) {
        $folder = "uploads/foto_profile/";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        // VALIDASI MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['foto_profile']['tmp_name']);
        finfo_close($finfo);
        
        $allowedMime = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];
        
        if (!in_array($mime, $allowedMime)) {
            throw new Exception("Format foto tidak didukung");
        }
    
        // VALIDASI SIZE (2MB)
        if ($_FILES['foto_profile']['size'] > 1024 * 1024) {
            throw new Exception("Ukuran foto maksimal 1MB");
        }

        $ext = pathinfo($_FILES['foto_profile']['name'], PATHINFO_EXTENSION);
        $fotoName = "profile_" . time() . "_" . rand(100,999) . "." . $ext;
    
        $target = $folder . $fotoName;
    
        if (!move_uploaded_file($_FILES['foto_profile']['tmp_name'], $target)) {
            throw new Exception("Gagal menyimpan foto");
        }
    }


    // ======================
    // QUERY UPDATE
    // ======================
    $sql = "UPDATE users SET
        nik = ?, nip = ?, name = ?, nama_panggilan = ?, tempat_lahir = ?, tanggal_lahir = ?, jenis_kelamin = ?,
        status_pernikahan = ?, agama = ?, email = ?, nomor_hp = ?, alamat = ?, village_id = ?, district_id = ?,
        regency_id = ?, province_id = ?, postal_code = ?, tanggal_masuk = ?, tanggal_keluar = ?, jabatan = ?,
        user_status = ?, npwp = ?, bpjs_tk = ?, bpjs_kes = ?, rekening_bank = ?, nomor_rekening = ?";

    if ($fotoName) {
        $sql .= ", foto_profile = ?";
    }

    $sql .= " WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if ($fotoName) {
        $stmt->bind_param(
            "sssssssssssssssssssssssssssi",
            $nik, $nip, $name, $nama_panggilan, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, 
            $status_nikah, $agama, $email, $nomor_hp, $alamat, $village_id, $district_id, 
            $regency_id, $province_id, $postal_code, $tanggal_masuk, $tanggal_keluar, $jabatan,
            $user_status, $npwp, $bpjs_tk, $bpjs_kes, $rekening_bank, $nomor_rekening,
            $target, $id
        );
    } else {
        $stmt->bind_param(
            "ssssssssssssssssssssssssssi",
            $nik, $nip, $name, $nama_panggilan, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, 
            $status_nikah, $agama, $email, $nomor_hp, $alamat, $village_id, $district_id, 
            $regency_id, $province_id, $postal_code, $tanggal_masuk, $tanggal_keluar, $jabatan,
            $user_status, $npwp, $bpjs_tk, $bpjs_kes, $rekening_bank, $nomor_rekening,
            $id
        );
    }

    $stmt->execute();
    
    // ======================
    // HAPUS FOTO LAMA JIKA ADA FOTO BARU
    // ======================
    if ($fotoName && !empty($oldFoto)) {
        if (file_exists($oldFoto)) {
            if($oldFoto==='uploads/foto_profile/default.webp'){
            } else {
                unlink($oldFoto);
            }
        }
    }

    echo json_encode([
        'status' => true,
        'message' => 'Profil berhasil diperbarui',
        'foto' => $fotoName
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
