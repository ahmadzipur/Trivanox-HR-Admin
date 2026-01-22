<?php
include 'error_handler.php';
session_start();
require_once 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

/* ================== VALIDASI SESSION ================== */
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$id_user = (int) $_SESSION['user_id'];
$tanggal = date('Y-m-d');
$jam     = date('H:i:s');

/* ================== INPUT ================== */
$lat    = $_POST['latitude']  ?? null;
$lng    = $_POST['longitude'] ?? null;
$foto   = $_POST['foto']      ?? null;
$action = $_POST['action']    ?? null;

/* ================== AMBIL DATA ABSENSI HARI INI ================== */
$stmt = $conn->prepare("
    SELECT *
    FROM absensi
    WHERE id_user = ? AND tanggal = ?
    LIMIT 1
");
$stmt->bind_param("is", $id_user, $tanggal);
$stmt->execute();
$result = $stmt->get_result();
$data   = $result->fetch_assoc();
$stmt->close();

/*
STATUS:
0 = belum absen (data belum ada)
1 = sudah masuk, belum istirahat
2 = sedang istirahat
3 = selesai istirahat
5 = sudah pulang
*/

$absen_id     = $data['id'] ?? null;

/* ================== Jika Lokasi Kosong ================== */
if ($lat === null) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Lokasi tidak diketahui, harap aktifkan izin lokasi pada perangkat Anda.';
    header('Location: index');
    exit;
}
if ($lng === null) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Lokasi tidak diketahui, harap aktifkan izin lokasi pada perangkat Anda.';
    header('Location: index');
    exit;
}

/* ================== FOTO WAJIB UNTUK SEMUA AKSI ================== */
if (empty($foto)) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Foto wajib diambil';
    header('Location: index');
    exit;
}



/* ================== PROSES ABSENSI ================== */
$query   = false;
$message = '';

/* ===== STATUS 0 : ABSEN MASUK ===== */
if ($action === 'absen_masuk') {

/* ================== SIMPAN FOTO ================== */
$folder = 'uploads/absensi/';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$foto = preg_replace('#^data:image/\w+;base64,#i', '', $foto);
$foto = base64_decode($foto);

if ($foto === false) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Format foto tidak valid';
    header('Location: index');
    exit;
}

$nama_file = "absen_{$id_user}_" . time() . ".jpg";
file_put_contents($folder . $nama_file, $foto);

    $stmt = $conn->prepare("
        INSERT INTO absensi (
            id_user, tanggal,
            jam_masuk, foto_masuk,
            latitude_masuk, longitude_masuk,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'hadir', NOW())
    ");
    $stmt->bind_param(
        "isssss",
        $id_user,
        $tanggal,
        $jam,
        $nama_file,
        $lat,
        $lng
    );
    $query = $stmt->execute();
    $stmt->close();

    $message = 'Absen masuk berhasil';
}

/* ===== STATUS 1 : MULAI ISTIRAHAT ATAU PULANG ===== */
elseif ($action === 'mulai_istirahat') {

    $stmt = $conn->prepare("
        UPDATE absensi
        SET jam_mulai_istirahat = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $jam, $absen_id);
    $query = $stmt->execute();
    $stmt->close();

    $message = 'Istirahat dimulai';
}
elseif ($action === 'absen_pulang') {
/* ================== SIMPAN FOTO ================== */
$folder = 'uploads/absensi/';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$foto = preg_replace('#^data:image/\w+;base64,#i', '', $foto);
$foto = base64_decode($foto);

if ($foto === false) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Format foto tidak valid';
    header('Location: index');
    exit;
}

$nama_file = "absen_{$id_user}_" . time() . ".jpg";
file_put_contents($folder . $nama_file, $foto);

    $stmt = $conn->prepare("
        UPDATE absensi
        SET jam_pulang = ?,
            foto_pulang = ?,
            latitude_pulang = ?,
            longitude_pulang = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $jam, $nama_file, $lat, $lng, $absen_id);
    $query = $stmt->execute();
    $stmt->close();

    $message = 'Absen pulang berhasil';
}

/* ===== STATUS 2 : SELESAI ISTIRAHAT ATAU PULANG ===== */
elseif ($action === 'selesai_istirahat') {

    $stmt = $conn->prepare("
        UPDATE absensi
        SET jam_selesai_istirahat = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $jam, $absen_id);
    $query = $stmt->execute();
    $stmt->close();

    $message = 'Istirahat selesai';
}
elseif ($action === 'absen_pulang') {

    $stmt = $conn->prepare("
        UPDATE absensi
        SET jam_pulang = ?,
            foto_pulang = ?,
            latitude_pulang = ?,
            longitude_pulang = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $jam, $nama_file, $lat, $lng, $absen_id);
    $query = $stmt->execute();
    $stmt->close();

    $message = 'Absen pulang berhasil';
}

/* ===== STATUS 3 : PULANG ===== */
elseif ($action === 'absen_pulang') {

    $stmt = $conn->prepare("
        UPDATE absensi
        SET jam_pulang = ?,
            foto_pulang = ?,
            latitude_pulang = ?,
            longitude_pulang = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $jam, $nama_file, $lat, $lng, $absen_id);
    $query = $stmt->execute();
    $stmt->close();

    $message = 'Absen pulang berhasil';
}

/* ===== AKSI TIDAK VALID ===== */
else {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Aksi tidak valid';
    header('Location: index');
    exit;
}

/* ================== RESPONSE ================== */
if ($query) {
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = $message;
} else {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Gagal memproses absensi';
}

header('Location: index');
exit;
