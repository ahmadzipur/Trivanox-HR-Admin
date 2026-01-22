<?php
if (isset($_SESSION["user_id"])) {
    $stmt = $conn->prepare("
      SELECT u.*, c.nama_company 
      FROM users u
      LEFT JOIN company c ON u.id_company = c.id_company
      WHERE u.id = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $user = $result->fetch_assoc();

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user"] = [
          "id" => $user["id"],
          "id_company" => $user["id_company"],
          "name" => $user["name"],
          "email" => $user["email"],
          "role" => $user["role"],
          "jabatan" => $user["jabatan"],
          "foto_profile" => $user["foto_profile"],
          "company_name" => $user["nama_company"]
        ];
    }

    $stmt->close();
} else if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember"])) {
    $token = $_COOKIE["remember"];
    $stmt = $conn->prepare("
      SELECT u.*, c.nama_company 
      FROM users u
      LEFT JOIN company c ON u.id_company = c.id_company
      WHERE u.remember_token = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $user = $result->fetch_assoc();

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user"] = [
          "id" => $user["id"],
          "id_company" => $user["id_company"],
          "name" => $user["name"],
          "email" => $user["email"],
          "role" => $user["role"],
          "jabatan" => $user["jabatan"],
          "foto_profile" => $user["foto_profile"],
          "company_name" => $user["nama_company"]
        ];
    }

    $stmt->close();
} else if (!isset($_SESSION["user_id"]) && !isset($_COOKIE["remember"])) {
    $_SESSION["status"] = "error";
    $_SESSION["message"] = "Silakan login terlebih dahulu.";
    header("Location: login");
    exit;
}

$user_id = $_SESSION["user_id"]; 
$stmt = $conn->prepare("SELECT u.id, u.id_company, u.name,
      u.email, u.password, u.role, u.jabatan, u.foto_profile, u.remember_token,
      u.created_at AS user_created_at, c.nama_company, c.code_company, c.code_verification,
      c.alamat_company, c.status_company, c.expired_at, c.created_at AS company_created_at
  FROM users u LEFT JOIN company c ON u.id_company = c.id_company
  WHERE u.id = ? LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$_SESSION["user"] = [
  "id"              => $user["id"],
  "id_company"      => $user["id_company"],
  "name"            => $user["name"],
  "email"           => $user["email"],
  "role"            => $user["role"],
  "jabatan"         => $user["jabatan"],
  "foto_profile"    => $user["foto_profile"],
  "company_name"    => $user["nama_company"],
  "company_code"    => $user["code_company"],
  "status_company"  => $user["status_company"],
  "expired_at"      => $user["expired_at"],
  "company_address" => $user["alamat_company"]
];

$sesi_user = $_SESSION["user"];
$id_company = $sesi_user["id_company"];
