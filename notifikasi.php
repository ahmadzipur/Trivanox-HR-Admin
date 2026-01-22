<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

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

if (!isset($_SESSION["user_id"])) {
  //$_SESSION["status"] = "error";
  //$_SESSION["message"] = "Silakan login terlebih dahulu.";
  header("Location: login");
  exit;
}

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
      u.tanggal_masuk,
      u.created_at AS user_created_at,
      c.nama_company,
      c.id_package,
      c.code_company,
      c.code_verification,
      c.alamat_company,
      c.company_logo,
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
  "id_package"      => $user["id_package"],
  "name"            => $user["name"],
  "email"           => $user["email"],
  "role"            => $user["role"],
  "jabatan"         => $user["jabatan"],
  "foto_profile"    => $user["foto_profile"],
  "tanggal_masuk"    => $user["tanggal_masuk"],
  "company_name"    => $user["nama_company"],
  "company_code"    => $user["code_company"],
  "company_logo"    => $user["company_logo"],
  "status_company"  => $user["status_company"],
  "expired_at"      => $user["expired_at"],
  "company_address" => $user["alamat_company"]
];

$sesi_user = $_SESSION["user"];
$id_user = $_SESSION["user"]["id"];
$id_company = $sesi_user['id_company'];

// cek Notifikasi
// Query untuk menghitung jumlah data dengan status 'unread' berdasarkan id_user
$query_count = "SELECT COUNT(*) AS total FROM notifications WHERE target_id = ? AND status = 'unread'";
$stmt = $conn->prepare($query_count);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->bind_result($total_notifications); // Menyimpan hasil COUNT
$stmt->fetch(); // Menjalankan fetch untuk mendapatkan hasil
$_SESSION["user"]['jumlah_notifications'] = $total_notifications ?? '';

// Menutup statement setelah digunakan
$stmt->close();

// Query untuk mengambil data notifikasi yang 'unread' berdasarkan id_user dan diurutkan berdasarkan tanggal terbaru
$query_notifications = "SELECT * FROM notifications WHERE target_id = ? ORDER BY created_at DESC LIMIT 50 ";
$stmt_notifications = $conn->prepare($query_notifications);
$stmt_notifications->bind_param("i", $_SESSION["user"]['id']);
$stmt_notifications->execute();

// Mengambil hasil query
$result_notifications = $stmt_notifications->get_result();

// Menutup statement dan koneksi
$stmt_notifications->close();

$query_update = "UPDATE notifications SET status = 'read' WHERE target_id = ?";
$stmt_update = $conn->prepare($query_update);
$stmt_update->bind_param("i", $id_user);
$stmt_update->execute();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>HR Management</title>
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet" />
  <script src="assets/js/pace.min.js"></script>
  <!--favicon-->
  <link rel="icon" href="assets/images/logo-trivanox.ico" type="image/x-icon">
  <!-- Vector CSS -->
  <link href="assets/plugins/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet" />
  <!-- simplebar CSS-->
  <link href="assets/plugins/simplebar/css/simplebar.css" rel="stylesheet" />
  <!-- Bootstrap core CSS-->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
  <!-- animate CSS-->
  <link href="assets/css/animate.css" rel="stylesheet" type="text/css" />
  <!-- Icons CSS-->
  <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
  <!-- Sidebar CSS-->
  <link href="assets/css/sidebar-menu.css" rel="stylesheet" />
  <!-- Custom Style-->
  <link href="assets/css/app-style.css" rel="stylesheet" />

  <style>
    .table-scroll {
      max-height: 150px;
      /* scroll atas–bawah */
      overflow-y: auto;
      overflow-x: auto;
      /* scroll kiri–kanan */
      white-space: nowrap;
      /* mencegah teks turun baris */
    }

    .camera-wrapper {
      width: 240px;
      /* bebas, asal konsisten */
      aspect-ratio: 4 / 5;
      /* kunci rasio */
      position: relative;
    }

    #video,
    #canvas {
      width: 100%;
      height: 100%;
      object-fit: cover;
      /* potong rapi */
      border-radius: 12px;
    }
  </style>

</head>

<body class="<?= $savedTheme ?>">

  <!-- Start wrapper-->
  <div id="wrapper">
    <?php include 'left-sidebar.php'; ?>
    <?php include 'topbar.php'; ?>
    <div class="clearfix"></div>

    <div class="content-wrapper">
      <div class="container-fluid">
        <?php if ($status): ?>
          <div id="messages">
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mt-3">
              <div class="text-center p-1">
                <strong><?= htmlspecialchars($message) ?></strong>
              </div>
            </div>
          </div>
        <?php endif; ?>
        
          <!--Start Dashboard Content-->
          <div class="card mt-3">
            <div class="card-content">
              <div class="row row-group m-0">
                <div class="col-12 col-lg-12 col-xl-12 border-light">
                  <div class="card-body">
                    <?php if ($result_notifications->num_rows > 0) {
                        // Simpan semua data notifikasi ke dalam array
                        while ($row = $result_notifications->fetch_assoc()) {
                            ?>
                        <div class='h6'><i class="<?= $row['icon'] ?>"></i> <?= $row['title'] ?></div>
                        <p><?= $row['message'] ?></p>
                        <div class="text-right">
                            <?= !empty($row['created_at']) 
                                ? date('H:i:s d-m-Y', strtotime($row['created_at'])) 
                                : '-' 
                            ?>
                        </div>
                        <hr>
        <?php 
    }
} else {
    ?>
    <p>Belum ada notifikasi</p>
    <?php 
}
?>
                  </div>
                </div>
              </div>
            </div>
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
  <!-- loader scripts -->
  <script src="assets/js/jquery.loading-indicator.js"></script>
  <!-- Custom scripts -->
  <script>
    $(document).ready(function() {
      // Fungsi untuk mengubah tema dan mengirimnya ke server
      function changeTheme(themeClass) {
        // Mengubah kelas tema di body
        $('body').removeClass().addClass('bg-theme ' + themeClass);

        // Mengirim tema yang dipilih ke server menggunakan AJAX ke set-theme.php
        $.post('set-theme.php', {
          theme: themeClass
        }, function(response) {
          console.log('Theme changed to: ', themeClass);
          console.log(response); // Menampilkan respons dari server
        });
      }

      // Event listener untuk setiap tombol tema
      $('#theme1').click(function() {
        changeTheme('bg-theme1');
      });
      $('#theme2').click(function() {
        changeTheme('bg-theme2');
      });
      $('#theme3').click(function() {
        changeTheme('bg-theme3');
      });

      $('#theme4').click(function() {
        changeTheme('bg-theme4');
      });
      $('#theme5').click(function() {
        changeTheme('bg-theme5');
      });
      $('#theme6').click(function() {
        changeTheme('bg-theme6');
      });
      $('#theme7').click(function() {
        changeTheme('bg-theme7');
      });
      $('#theme8').click(function() {
        changeTheme('bg-theme8');
      });
      $('#theme9').click(function() {
        changeTheme('bg-theme9');
      });
      $('#theme10').click(function() {
        changeTheme('bg-theme10');
      });
      $('#theme11').click(function() {
        changeTheme('bg-theme11');
      });
      $('#theme12').click(function() {
        changeTheme('bg-theme12');
      });
      $('#theme13').click(function() {
        changeTheme('bg-theme13');
      });
      $('#theme14').click(function() {
        changeTheme('bg-theme14');
      });
      $('#theme15').click(function() {
        changeTheme('bg-theme15');
      });
    });
  </script>
  <script src="assets/js/app-script.js"></script>
  <!-- Chart js -->

  <script src="assets/plugins/Chart.js/Chart.min.js"></script>

  <!-- Index js -->
  <script src="assets/js/index.js"></script>


</body>

</html>