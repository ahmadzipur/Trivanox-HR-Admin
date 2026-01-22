<?php
include 'error_handler.php';
include "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

$id_user    = $_POST['user_id'];
$latitude   = $_POST['latitude'];
$longitude  = $_POST['longitude'];
$tanggal    = date('Y-m-d');
$jam_masuk  = date('H:i:s');
$date_time  = date('Y-m-d H:i:s');
$status     = 'hadir';

$foto       = $_FILES['foto']['name'];
$tmp        = $_FILES['foto']['tmp_name'];

$folder     = "uploads/absensi/";

$namaFile   = "masuk_{$id_user}_" . $foto;
$path       = $folder . $namaFile;

move_uploaded_file($tmp, $path);

$query = mysqli_query($conn, "INSERT INTO absensi 
(id_user, tanggal, jam_masuk, foto_masuk, latitude_masuk, longitude_masuk, status, created_at)
VALUES
('$id_user','$tanggal','$jam_masuk','$namaFile','$latitude','$longitude', '$status', '$date_time')");

echo json_encode([
    "success" => true,
    "message" => "Absen masuk berhasil"
]);
?>