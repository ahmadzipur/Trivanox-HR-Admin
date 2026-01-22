<?php
header('Content-Type: application/json');
require_once "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

// Ambil id_user dari POST
$id_user = $_POST['id_user'] ?? null;

if (!$id_user) {
    echo json_encode([
        'status' => 'error',
        'message' => 'id_user tidak ditemukan'
    ]);
    exit;
}

// Ambil data user
$stmt = $conn->prepare("
    SELECT id, id_company, id_branch, id_division, name, nama_panggilan, nik, nip,
           tempat_lahir, tanggal_lahir, status_pernikahan, agama, nomor_hp, alamat,
           village_id, district_id, regency_id, province_id, postal_code,
           tanggal_masuk, tanggal_keluar, gaji_pokok, tunjangan_transport, tunjangan_makan,
           tunjangan_jabatan, tunjangan_lainnya, ket_tunjangan_lainnya, email,
           rekening_bank, nomor_rekening, npwp, bpjs_tk, bpjs_kes, role, jabatan,
           jenis_kelamin, foto_profile, file_ktp, file_kk, file_cv, file_kontrak_kerja,
           file_npwp, user_status, status, last_login
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows) {
    $user = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'data' => $user
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'User tidak ditemukan'
    ]);
}

$stmt->close();
$conn->close();
?>
