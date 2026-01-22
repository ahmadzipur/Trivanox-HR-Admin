<?php
header("Content-Type: application/json");
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta
$now        = date('Y-m-d H:i:s');
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode([
        "success" => false,
        "message" => "Email dan password wajib diisi",
        "debug" => $_POST
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, name, email, password, role FROM users WHERE email=?"
);
$stmt->bind_param("s", $email);
$stmt->execute();

$stmt->bind_result($id, $name, $email_db, $password_hash, $role);

if ($stmt->fetch()) {
    if (password_verify($password, $password_hash)) {
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $id,
                "name" => $name,
                "email" => $email_db,
                "role" => $role
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Email atau password salah"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email tidak ditemukan"
    ]);
}

$stmt->close();

$stmt = $conn->prepare("UPDATE users SET last_login=? WHERE id=?");
    $stmt->bind_param("si", $now, $id);
    $stmt->execute();
    
    
if (!method_exists($stmt, 'get_result')) {
    echo json_encode(["error" => "get_result tidak tersedia"]);
    exit;
}

