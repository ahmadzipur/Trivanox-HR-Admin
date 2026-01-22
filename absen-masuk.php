<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta

$user_id = $_SESSION['user_id'];
$tanggal = date('Y-m-d');
$jam     = date('H:i:s');

$lat = $_POST['latitude'];
$lng = $_POST['longitude'];
$foto = $_POST['foto'];

// ====== SIMPAN FOTO ======
$folder = "uploads/absensi/";
if (!is_dir($folder)) mkdir($folder, 0777, true);

$foto = str_replace('data:image/jpeg;base64,', '', $foto);
$foto = base64_decode($foto);

$nama_file = "masuk_{$user_id}_" . time() . ".jpg";
file_put_contents($folder . $nama_file, $foto);

// ====== INSERT DATABASE ======
$query = mysqli_query($conn, "
    INSERT INTO absensi (
        user_id, tanggal, jam_masuk,
        foto_masuk,
        latitude_masuk, longitude_masuk,
        status, created_at
    ) VALUES (
        '$user_id', '$tanggal', '$jam',
        '$nama_file',
        '$lat', '$lng',
        'hadir', NOW()
    )
");

if ($query) {
    // jika berhasil
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Absen masuk berhasil';
    header("Location: index");
    exit;
} else {
    // jika gagal
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Absen gagal: ' . mysqli_error($conn);
    header("Location: index");
    exit;
}
