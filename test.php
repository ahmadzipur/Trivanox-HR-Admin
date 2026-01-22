<?php
header("Content-Type: application/json");

include "koneksi.php";

try {
    $query = "SELECT * FROM absensi ORDER BY tanggal DESC, jam_masuk DESC";
    $result = $conn->query($query);

    $absensi = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $absensi[] = [
                "id" => (int)$row['id'],
                "user_id" => (int)$row['user_id'],
                "tanggal" => $row['tanggal'],
                "jam_masuk" => $row['jam_masuk'],
                "jam_mulai_istirahat" => $row['jam_mulai_istirahat'],
                "jam_selesai_istirahat" => $row['jam_selesai_istirahat'],
                "jam_pulang" => $row['jam_pulang'],
                "foto_masuk" => $row['foto_masuk'],
                "foto_pulang" => $row['foto_pulang'],
                "latitude_masuk" => $row['latitude_masuk'],
                "longitude_masuk" => $row['longitude_masuk'],
                "latitude_pulang" => $row['latitude_pulang'],
                "longitude_pulang" => $row['longitude_pulang'],
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $absensi
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil data: " . $e->getMessage()
    ]);
}
