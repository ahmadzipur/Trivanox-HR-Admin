<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
include 'encryption.php';
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta
// Kunci rahasia
$key = 'AhmadZaelani23552011179';
// Inisialisasi kelas
$encryption = new Encryption($key);
// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada
// Simpan form lama di session supaya tidak hilang
function old($key) {
  return $_SESSION['old'][$key] ?? '';
}

/* ---------------------------------------------------
   FLASH MESSAGE
--------------------------------------------------- */
$status = $_SESSION['status'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['status'], $_SESSION['message']);

/* ---------------------------------------------------
   CSRF TOKEN
--------------------------------------------------- */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ==============================
// CEK LOGIN
// ==============================
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

$provinces = [];
$result_provinces = $conn->query("SELECT id, name FROM provinces ORDER BY name");
while ($row_provinces = $result_provinces->fetch_assoc()) {
    $provinces[] = $row_provinces;
}

$divisi = [];
$result_divisi = $conn->query("
    SELECT id_division, nama_divisi 
    FROM job_divisions 
    ORDER BY nama_divisi
");

/* cek query berhasil */
if ($result_divisi === false) {
    die("Query error: " . $conn->error);
}

/* cek apakah ada data */
if ($result_divisi->num_rows > 0) {
    while ($row_divisi = $result_divisi->fetch_assoc()) {
        $divisi[] = $row_divisi;
    }
} else {
    // tidak ada data
    $divisi = []; // opsional, sudah kosong
}

$branch = [];
$stmt_branch = $conn->prepare("
    SELECT id_branch, nama_cabang 
    FROM branch_company 
    WHERE id_company = ?
    ORDER BY nama_cabang ASC
");

if (!$stmt_branch) {
    die("Prepare failed: " . $conn->error);
}

$stmt_branch->bind_param("i", $id_company);

if (!$stmt_branch->execute()) {
    die("Execute failed: " . $stmt_branch->error);
}

$result_branch = $stmt_branch->get_result();

/* cek apakah ada data */
if ($result_branch->num_rows > 0) {
    while ($row_branch = $result_branch->fetch_assoc()) {
        $branch[] = $row_branch;
    }
}

$stmt_branch->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>Tambah Data Divisi - HR Management</title>
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
    <h4 class="h4 mb-2 text-gray-800">Tambah Data Divisi</h4>
    <p class="h5"><?= $_SESSION["user"]["company_name"] ?></p>
        <?php if ($status): ?>
        <div id="messages">
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mt-3">
                <div class="text-center p-1">
                    <strong><?= htmlspecialchars($message) ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <div class="row mt-3">
        <form action="divisi-tambah" method="POST" class="w-100" enctype="multipart/form-data"> <!-- form pembungkus -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="kode_divisi">Kode Divisi</label>
                            <input type="text" class="form-control" id="kode_divisi" name="kode_divisi" placeholder="Kode Divisi" value="<?= old('kode_divisi'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="nama_divisi">Nama Divisi</label>
                            <input type="text" class="form-control" id="nama_divisi" name="nama_divisi" placeholder="Nama Divisi" value="<?= old('nama_divisi'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"
                                placeholder="Deskripsi lengkap"><?= old('deskripsi') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="status_divisi">Status</label>
                            <select class="form-control" id="status_divisi" name="status_divisi">
                                <option value="">-- Pilih Status --</option>
                                <option value="active" <?= old('status_divisi')=='active'?'selected':'' ?>>Aktif</option>
                                <option value="inactive" <?= old('status_divisi')=='inactive'?'selected':'' ?>>Non Aktif</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- checkbox + submit berada di luar card 
    <div class="form-group py-3 text-center">
      <div class="icheck-material-white d-inline-block me-2">
        <input type="checkbox" id="agree" checked="" />
        <label for="agree">I Agree Terms & Conditions</label>
      </div>
    </div>-->

    <div class="form-group text-center">
      <button type="submit" class="btn btn-light px-5">
        <i class="zmdi zmdi-save"></i> Simpan
      </button> 
      <a href="divisi" class="btn btn-light px-5">
        <i class="zmdi zmdi-close"></i> Batal
      </a>
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
	<script>
function loadSelect(url, target, selected = null) {
  $.get(url, function(data) {
    $(target).html(data);
    if (selected) $(target).val(selected);
  });
}

// initial load (edit mode)
loadSelect('ajax/regencies.php?province_id=<?= old('province_id') ?>', '#regency_id', <?= old('regency_id') ?>);
loadSelect('ajax/districts.php?regency_id=<?= old('regency_id') ?>', '#district_id', <?= old('district_id') ?>);
loadSelect('ajax/villages.php?district_id=<?= old('district_id') ?>', '#village_id', <?= old('village_id') ?>);

$('#province_id').change(function() {
  loadSelect('ajax/regencies.php?province_id=' + this.value, '#regency_id');
  $('#district').html('');
  $('#village').html('');
});

$('#regency_id').change(function() {
  loadSelect('ajax/districts.php?regency_id=' + this.value, '#district_id');
  $('#village').html('');
});

$('#district_id').change(function() {
  loadSelect('ajax/villages.php?district_id=' + this.value, '#village_id');
});
</script>
</body>
</html>
