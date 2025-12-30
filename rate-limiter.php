<?php
// ===== rate_limiter.php =====
// Mencegah akses terlalu sering dari perangkat yang sama (delay 30 detik)

$limit_file = __DIR__ . '/rate_limit_data.json';
$delay = 1; // dalam detik

// Identifikasi perangkat (bisa pakai kombinasi IP + User-Agent)
$device_id = md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

// Baca data lama
$data = [];
if (file_exists($limit_file)) {
    $json = file_get_contents($limit_file);
    $data = json_decode($json, true) ?: [];
}

// Cek waktu akses terakhir
$now = time();
$last_access = $data[$device_id]['last_access'] ?? 0;
$diff = $now - $last_access;

if ($diff < $delay) {
    $remaining = $delay - $diff;
    header('Content-Type: text/plain');
    header('Location: wait');
    // echo "⚠️ Terlalu sering mengakses. Coba lagi dalam {$remaining} detik.";
    exit;
}

// Simpan waktu akses terbaru
$data[$device_id] = [
    'last_access' => $now,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];

file_put_contents($limit_file, json_encode($data, JSON_PRETTY_PRINT));

// Jika lolos, lanjutkan script lain
?>
