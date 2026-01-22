<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
date_default_timezone_set('Asia/Jakarta');

session_start();

// Hapus session & cookie
$_SESSION = [];
session_destroy();

if (isset($_COOKIE['remember'])) {
    setcookie('remember', '', time() - 3600, "/");
}

echo json_encode([
    "status" => "success",
    "message" => "Logout berhasil."
]);
