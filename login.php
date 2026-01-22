<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada
$now        = date('Y-m-d H:i:s');

$resultRow = null;
$userLat = null;
$userLng = null;
$district = null;
$regency = null;
$province = null;
$id_user = null;
$title = null;
$message = null;
$target_page = null;
$icon = null;
$color = null;
/* ---------------------------------------------------
   AUTO LOGIN VIA COOKIE REMEMBER ME
--------------------------------------------------- */
if (isset($_SESSION['user_id'])) {
  $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
  $stmt->bind_param("s", $_SESSION['user_id']);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows === 1) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    header("Location: index");
    exit;
  }
  $stmt->close();
}

if (isset($_SESSION['user_id']) && isset($_COOKIE['remember'])) {
  $token = $_COOKIE['remember'];

  $stmt = $conn->prepare("SELECT id FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows === 1) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    header("Location: index");
    exit;
  }
  $stmt->close();
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember'])) {
  $token = $_COOKIE['remember'];

  $stmt = $conn->prepare("SELECT id FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows === 1) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    header("Location: index");
    exit;
  }
  $stmt->close();
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

/* ---------------------------------------------------
   PROSES LOGIN (POST)
--------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['status'] = "error";
    $_SESSION['message'] = "Invalid request token!";
    header("Location: login");
    exit;
  }

  $email = trim($_POST['email']);
  $password = trim($_POST['password']);
  $remember = isset($_POST['remember']) ? 1 : 0;

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Format email tidak valid!';
    header('Location: login');
    exit;
  }

  $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows == 0) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Email tidak ditemukan!';
    header('Location: login');
    exit;
  }

  $user = $result->fetch_assoc();
  $stmt->close();

  if (!password_verify($password, $user['password'])) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Password salah!';
    header('Location: login');
    exit;
  }

  $_SESSION['user_id'] = $user['id'];
  $id_user = $user['id'];
  
    $userLat = (float) $_POST['latitude'];
    $userLng = (float) $_POST['longitude'];

    $sql_loc = "
    SELECT 
        d.name AS district,
        r.name AS regency,
        p.name AS province,
        d.latitude AS district_lat,
        d.longitude AS district_lng,
        (
            6371 * acos(
                cos(radians(?))
                * cos(radians(d.latitude))
                * cos(radians(d.longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(d.latitude))
            )
        ) AS distance_km
    FROM districts d
    JOIN regencies r ON d.regency_id = r.id
    JOIN provinces p ON r.province_id = p.id
    ORDER BY distance_km ASC
    LIMIT 1
    ";

    $stmt_loc = $conn->prepare($sql_loc);
    $stmt_loc->bind_param("ddd", $userLat, $userLng, $userLat);
    $stmt_loc->execute();
    $result_loc = $stmt_loc->get_result();

    $resultRow = $result_loc->fetch_assoc();
    
        $title = 'Peringatan Login';
        $message = 'Akun Anda telah mencoba login';
        $target_page = 'notifikasi';
        $icon = 'zmdi zmdi-account';
        $color = 'warning';
    if ($resultRow){
        $district = strtoupper($resultRow['district']);
        $regency = strtoupper($resultRow['regency']);
        $province = strtoupper($resultRow['province']);
        $title = 'Peringatan Login';
        $message = 'Akun Anda telah mencoba login di sekitar '. $district . ', ' . $regency . ', ' . $province;
        $target_page = 'notifikasi';
        $icon = 'zmdi zmdi-account';
        $color = 'warning';
    }

  $query_notif = "INSERT INTO notifications (id_user, title, message, target_page, target_id, icon, color, created_at, created_by, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Menyiapkan statement
    $stmt_notif = $conn->prepare($query_notif);

    // Bind parameter
    $stmt_notif->bind_param("isssisssis", $id_user, $title, $message, $target_page, $id_user, $icon, $color, $now, $id_user, $now);

    // Eksekusi query
    $stmt_notif->execute();
    
    // Menutup statement dan koneksi
    $stmt_notif->close();
    

    $stmt = $conn->prepare("UPDATE users SET last_login=? WHERE id=?");
    $stmt->bind_param("si", $now, $user['id']);
    $stmt->execute();
  /* ---------------------------------------------------
     REMEMBER ME FUNCTIONAL
  --------------------------------------------------- */
  if ($remember) {
    $token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("UPDATE users SET remember_token=? WHERE id=?");
    $stmt->bind_param("si", $token, $user['id']);
    $stmt->execute();

    setcookie(
      "remember",
      $token,
      time() + (86400 * 30),   // 30 hari
      "/",
      "",
      true,   // secure (aktifkan true jika pakai HTTPS)
      true    // httponly
    );

    $stmt->close();
  }

  unset($_SESSION['csrf_token']);
  $_SESSION['status'] = 'success';
  $_SESSION['message'] = 'Login berhasil!';
  header('Location: index');
  exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Login HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>Login - HR Management</title>
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet" />
  <script src="assets/js/pace.min.js"></script>
  <!--favicon-->
  <link rel="icon" href="assets/images/logo-trivanox.ico" type="image/x-icon">
  <!-- simplebar CSS -->
  <link href="assets/plugins/simplebar/css/simplebar.css" rel="stylesheet"/>
  <!-- Bootstrap core CSS-->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
  <!-- animate CSS-->
  <link href="assets/css/animate.css" rel="stylesheet" type="text/css" />
  <!-- Icons CSS-->
  <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
  <!-- Custom Style-->
  <link href="assets/css/app-style.css" rel="stylesheet" />

</head>

<body class="<?= $savedTheme ?>">

  <!-- start loader -->
  <div id="pageloader-overlay" class="visible incoming">
    <div class="loader-wrapper-outer">
      <div class="loader-wrapper-inner">
        <div class="loader"></div>
      </div>
    </div>
  </div>
  <!-- end loader -->

  <!-- Start wrapper-->
  <div id="wrapper">

    <div class="loader-wrapper">
      <div class="lds-ring">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
      </div>
    </div>
    <div class="card card-authentication1 mx-auto my-5">
      <div class="card-body">
        <div class="card-content p-2">
          <div class="text-center px-5">
            <img src="assets/images/logo-trivanox-text-512.webp" style="height: 100px;" class="img-fluid" alt="logo icon">
          </div>
          
        <?php if ($status): ?>
        <div id="messages">
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mt-3">
                <div class="text-center p-1">
                    <strong><?= htmlspecialchars($message) ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>

          <div class="card-title text-uppercase text-center py-2">Login</div>          
          <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            <div class="form-group">
              <label for="email" class="sr-only">Email</label>
              <div class="position-relative has-icon-right">
                <input type="text" name="email" id="email" class="form-control input-shadow" placeholder="Email">
                <div class="form-control-position">
                  <i class="icon-envelope"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="password" class="sr-only">Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="password" id="password" class="form-control input-shadow" placeholder="Password">
                <div class="form-control-position">
                  <i class="icon-lock"></i>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-6">
                <div class="icheck-material-white">
                  <input type="checkbox" id="remember" name="remember"/>
                  <label for="remember">Remember me</label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-light btn-block">Masuk</button>

          </form>
        </div>
      </div>
      <!-- 
      <div class="card-footer text-center py-3">
        <p class="text-warning mb-0">Belum punya Perusahaan? <a href="comp-register"> Daftarkan Perusahaan Anda di sini</a></p>
      </div>
      -->
      
      <div class="card-footer text-center py-3">
        <p><a href="reset-password">Reset Password</a></p>
      </div>
    </div>

    <!--Start Back To Top Button-->
    <a href="javaScript:void();" class="back-to-top"><i class="fa fa-angle-double-up"></i> </a>
    <!--End Back To Top Button-->

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

  </div><!--wrapper-->

  <!-- Bootstrap core JavaScript-->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>

  <!-- sidebar-menu js -->
  <script src="assets/js/sidebar-menu.js"></script>

  <!-- Custom scripts -->
  <script>
      $(document).ready(function () {
        // Fungsi untuk mengubah tema dan mengirimnya ke server
        function changeTheme(themeClass) {
            // Mengubah kelas tema di body
            $('body').removeClass().addClass('bg-theme ' + themeClass);
    
            // Mengirim tema yang dipilih ke server menggunakan AJAX ke set-theme.php
            $.post('set-theme.php', { theme: themeClass }, function (response) {
                console.log('Theme changed to: ', themeClass);
                console.log(response);  // Menampilkan respons dari server
            });
        }
    
        // Event listener untuk setiap tombol tema
        $('#theme1').click(function () {
            changeTheme('bg-theme1');
        });
        $('#theme2').click(function () {
            changeTheme('bg-theme2');
        });
        $('#theme3').click(function () {
            changeTheme('bg-theme3');
        });
        
    $('#theme4').click(function () {
        changeTheme('bg-theme4');
    });
    $('#theme5').click(function () {
        changeTheme('bg-theme5');
    });
    $('#theme6').click(function () {
        changeTheme('bg-theme6');
    });
    $('#theme7').click(function () {
        changeTheme('bg-theme7');
    });
    $('#theme8').click(function () {
        changeTheme('bg-theme8');
    });
    $('#theme9').click(function () {
        changeTheme('bg-theme9');
    });
    $('#theme10').click(function () {
        changeTheme('bg-theme10');
    });
    $('#theme11').click(function () {
        changeTheme('bg-theme11');
    });
    $('#theme12').click(function () {
        changeTheme('bg-theme12');
    });
    $('#theme13').click(function () {
        changeTheme('bg-theme13');
    });
    $('#theme14').click(function () {
        changeTheme('bg-theme14');
    });
    $('#theme15').click(function () {
        changeTheme('bg-theme15');
    });
    $('#theme16').click(function () {
        changeTheme('bg-theme16');
    });
    });

  </script>
  <script src="assets/js/app-script.js"></script>
<script>
navigator.geolocation.getCurrentPosition(
    pos => {
        document.getElementById('latitude').value = pos.coords.latitude;
        document.getElementById('longitude').value = pos.coords.longitude;
    },
    () => alert("Lokasi wajib diaktifkan")
);
</script>
</body>

</html>