<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

// ==============================
// CEK LOGIN
// ==============================
$user_id = $_SESSION["user_id"]; 
if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember"])) {

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


$stmt = $conn->prepare("
  SELECT 
      u.id,
      u.id_company,
      u.name,
      u.email,
      u.password,
      u.role,
      u.jabatan,
      u.foto_profile,
      u.remember_token,
      u.created_at AS user_created_at,
      c.nama_company,
      c.code_company,
      c.code_verification,
      c.alamat_company,
      c.status_company,
      c.expired_at,
      c.created_at AS company_created_at
  FROM users u
  LEFT JOIN company c ON u.id_company = c.id_company
  WHERE u.id = ?
  LIMIT 1
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>Tambah Data Karyawan - HR Management</title>
  <link rel="icon" href="assets/images/logo-trivanox.png" type="image/x-icon">
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet"/>
  <script src="assets/js/pace.min.js"></script>
  <!-- simplebar CSS-->
  <link href="assets/plugins/simplebar/css/simplebar.css" rel="stylesheet"/>
  <!-- Bootstrap core CSS-->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- animate CSS-->
  <link href="assets/css/animate.css" rel="stylesheet" type="text/css"/>
  <!-- Icons CSS-->
  <link href="assets/css/icons.css" rel="stylesheet" type="text/css"/>
  <!-- Sidebar CSS-->
  <link href="assets/css/sidebar-menu.css" rel="stylesheet"/>
  <!-- Custom Style-->
  <link href="assets/css/app-style.css" rel="stylesheet"/>
  
</head>

<body class="bg-theme bg-theme1">

<!-- start loader -->
   <div id="pageloader-overlay" class="visible incoming"><div class="loader-wrapper-outer"><div class="loader-wrapper-inner" ><div class="loader"></div></div></div></div>
   <!-- end loader -->

<!-- Start wrapper-->
 <div id="wrapper">

    <?php include 'left-sidebar.php'; ?>
    <?php include 'topbar.php'; ?>
<div class="clearfix"></div>
	
  <div class="content-wrapper">
    <div class="container-fluid">
    	  
    <!-- Page Heading -->
    <h4 class="h4 mb-2 text-gray-800">Data Karyawan</h4>
    <p class="h5"><?= $_SESSION["user"]["company_name"] ?></p>
<div class="row mt-3">
  <form action="" method="POST" class="w-100"> <!-- form pembungkus -->
    <div class="row">

      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <div class="card-title">Vertical Form</div>
            <hr>

            <div class="form-group">
              <label for="input-1">Name</label>
              <input type="text" class="form-control" id="input-1" name="name" placeholder="Enter Your Name">
              <small>Nama Lengkap sesuai Kartu Identitas</small>
            </div>
            <div class="form-group">
              <label for="input-2">Email</label>
              <input type="text" class="form-control" id="input-2" name="email" placeholder="Enter Your Email Address">
            </div>
            <div class="form-group">
              <label for="input-3">Mobile</label>
              <input type="text" class="form-control" id="input-3" name="mobile" placeholder="Enter Your Mobile Number">
            </div>
            <div class="form-group">
              <label for="input-4">Password</label>
              <input type="password" class="form-control" id="input-4" name="password" placeholder="Enter Password">
            </div>
            <div class="form-group">
              <label for="input-5">Confirm Password</label>
              <input type="password" class="form-control" id="input-5" name="confirm_password" placeholder="Confirm Password">
            </div>

          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <div class="card-title">Round Vertical Form</div>
            <hr>

            <div class="form-group">
              <label for="input-6">Name</label>
              <input type="text" class="form-control form-control-rounded" id="input-6" name="name_round" placeholder="Enter Your Name">
            </div>
            <div class="form-group">
              <label for="input-7">Email</label>
              <input type="text" class="form-control form-control-rounded" id="input-7" name="email_round" placeholder="Enter Your Email Address">
            </div>
            <div class="form-group">
              <label for="input-8">Mobile</label>
              <input type="text" class="form-control form-control-rounded" id="input-8" name="mobile_round" placeholder="Enter Your Mobile Number">
            </div>
            <div class="form-group">
              <label for="input-9">Password</label>
              <input type="password" class="form-control form-control-rounded" id="input-9" name="password_round" placeholder="Enter Password">
            </div>
            <div class="form-group">
              <label for="input-10">Confirm Password</label>
              <input type="password" class="form-control form-control-rounded" id="input-10" name="confirm_password_round" placeholder="Confirm Password">
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- checkbox + submit berada di luar card -->
    <div class="form-group py-3 text-center">
      <div class="icheck-material-white d-inline-block me-2">
        <input type="checkbox" id="agree" checked="" />
        <label for="agree">I Agree Terms & Conditions</label>
      </div>
    </div>

    <div class="form-group text-center">
      <button type="submit" class="btn btn-light px-5 btn-round">
        <i class="icon-lock"></i> Submit Form
      </button>
    </div>

  </form>
</div>


	<!--start overlay-->
		  <div class="overlay toggle-menu"></div>
		<!--end overlay-->

    </div>
    <!-- End container-fluid-->
    
   </div><!--End content-wrapper-->
   <!--Start Back To Top Button-->
    <a href="javaScript:void();" class="back-to-top"><i class="fa fa-angle-double-up"></i> </a>
    <!--End Back To Top Button-->
	
    <?php include 'footer.php'; ?>
	
	<!--start color switcher-->
   <div class="right-sidebar">
    <div class="switcher-icon">
      <i class="zmdi zmdi-settings zmdi-hc-spin"></i>
    </div>
    <div class="right-sidebar-content">

      <p class="mb-0">Gaussion Texture</p>
      <hr>
      
      <ul class="switcher">
        <li id="theme1"></li>
        <li id="theme2"></li>
        <li id="theme3"></li>
        <li id="theme4"></li>
        <li id="theme5"></li>
        <li id="theme6"></li>
      </ul>

      <p class="mb-0">Gradient Background</p>
      <hr>
      
      <ul class="switcher">
        <li id="theme7"></li>
        <li id="theme8"></li>
        <li id="theme9"></li>
        <li id="theme10"></li>
        <li id="theme11"></li>
        <li id="theme12"></li>
		<li id="theme13"></li>
        <li id="theme14"></li>
        <li id="theme15"></li>
      </ul>
      
     </div>
   </div>
  <!--end color switcher-->
   
  </div><!--End wrapper-->


  <!-- Bootstrap core JavaScript-->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
	
 <!-- simplebar js -->
  <script src="assets/plugins/simplebar/js/simplebar.js"></script>
  <!-- sidebar-menu js -->
  <script src="assets/js/sidebar-menu.js"></script>
  
  <!-- Custom scripts -->
  <script src="assets/js/app-script.js"></script>
	
</body>
</html>
