<?php
include 'error_handler.php';
include "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

$id_user    = $_POST['user_id'];
$latitude   = $_POST['latitude'];
$longitude  = $_POST['longitude'];
$tanggal    = date('Y-m-d');
$jam        = date('H:i:s');
$date_time  = date('Y-m-d H:i:s');
$status     = 'hadir';

// $foto       = $_FILES['foto']['name'];
// $tmp        = $_FILES['foto']['tmp_name'];

// $folder     = "uploads/absensi/";

// $namaFile   = "masuk_{$id_user}_" . $foto;
// $path       = $folder . $namaFile;

// move_uploaded_file($tmp, $path);

try {

    if (!isset($_POST['user_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "user_id tidak dikirim"
        ]);
        exit;
    }

    $id_user   = $_POST['user_id'];
    $tanggal   = date('Y-m-d');
    $jam       = date('H:i:s');
    $date_time = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        UPDATE absensi 
        SET jam_mulai_istirahat = ?
        WHERE id_user = ?
          AND tanggal = ?
          AND jam_mulai_istirahat IS NULL
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("sis", $jam, $id_user, $tanggal);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Istirahat dimulai"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Data tidak ditemukan / sudah istirahat"
        ]);
    }

    $stmt->close();
    exit;

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "message" => "Server error" . $e->getMessage(),
        "error" => $e->getMessage()
    ]);
    exit;
}

?>