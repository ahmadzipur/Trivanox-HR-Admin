<?php
include 'koneksi.php';
session_start();

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE AUTO-CLEAR
|--------------------------------------------------------------------------
*/
$status = $_SESSION['status'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['status'], $_SESSION['message']);

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf'];

/*
|--------------------------------------------------------------------------
| BLOCK AKSES LANGSUNG
|--------------------------------------------------------------------------
*/
/*
if (!isset($_SESSION['code_company'])) {
  header("Location: comp-register");
  exit;
}
*/

$code_company = $_SESSION['code_company'] ?? '';
$code_verification = $_SESSION['code_verification'] ?? '';
$name = $_SESSION['name'] ?? '';
$email = $_SESSION['email'] ?? '';
$jabatan = '';

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Check CSRF
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    die("Invalid CSRF Token");
  }

  if (!isset($_POST['terms'])) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Anda harus menyetujui Terms & Conditions';
    header("Location: register");
    exit;
  }

  // Sanitizing
  $code_company = trim($_POST['code_company']);
  $code_verification = trim($_POST['code_verification']);
  $name = htmlspecialchars(trim($_POST['name']));
  $email = trim($_POST['email']);
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $real_password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $role = "karyawan";
  $created_at = date("Y-m-d H:i:s");

  // Save old values for repopulation
  $_SESSION['name'] = $name;
  $_SESSION['email'] = $email;

  // Validate kode verifikasi
  if (!preg_match('/^[0-9]{6}$/', $code_verification)) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Kode verifikasi harus 6 digit angka';
    header("Location: register");
    exit;
  }
  // Tidak boleh kosong
  if (empty($code_company) || empty($code_verification)) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Kode perusahaan dan kode verifikasi wajib diisi';
    header("Location: register");
    exit;
  }

  // ----------------------------------------------
  // Ambil data perusahaan berdasarkan code_company
  // ----------------------------------------------
  $stmt = $conn->prepare("SELECT id_company, code_company, code_verification FROM company WHERE code_company = ? LIMIT 1");
  $stmt->bind_param("s", $code_company);
  $stmt->execute();
  $result = $stmt->get_result();

  // Jika kode perusahaan tidak ditemukan
  if ($result->num_rows === 0) {
    $_SESSION['status']  = 'error';
    $_SESSION['message'] = 'Kode perusahaan tidak ditemukan';
    header("Location: register");
    exit;
  }

  $row_company = $result->fetch_assoc();
  $stmt->close();

  // Simpan data untuk relasi user → company
  $id_company_db         = $row_company["id_company"];
  $row_code_company      = $row_company["code_company"];
  $row_code_verification = $row_company["code_verification"];

  // ----------------------------------------------
  // Validasi apakah kode verifikasi cocok
  // ----------------------------------------------
  if (
    $code_company      !== $row_code_company &&
    $code_verification !== $row_code_verification
  ) {

    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Kode perusahaan atau kode verifikasi tidak sesuai';
    header("Location: register");
    exit;
  }

  // Validasi kecocokan password & ulangi password
  if ($real_password !== $confirm_password) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Password dan Ulangi Password tidak sama!';
    header("Location: register");
    exit();
  }

  /*
  |--------------------------------------------------------------------------
  | Validasi company + kode verifikasi (Prepared Statement)
  |--------------------------------------------------------------------------
  */
  $stmtCompany = $conn->prepare("SELECT id_company FROM company WHERE code_company = ? AND code_verification = ?");
  $stmtCompany->bind_param("ss", $code_company, $code_verification);
  $stmtCompany->execute();
  $resultCompany = $stmtCompany->get_result();

  if ($resultCompany->num_rows === 0) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Kode perusahaan atau kode verifikasi salah';
    header("Location: register");
    exit;
  }

  $id_company = $resultCompany->fetch_assoc()['id_company'];

  /*
  |--------------------------------------------------------------------------
  | Validasi email unik
  |--------------------------------------------------------------------------
  */
  $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
  $checkEmail->bind_param("s", $email);
  $checkEmail->execute();
  $checkEmail->store_result();

  if ($checkEmail->num_rows > 0) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Email sudah terdaftar, gunakan email lain.';
    header("Location: register");
    exit;
  }

  /*
  |--------------------------------------------------------------------------
  | Upload Foto Profile
  |--------------------------------------------------------------------------
  */
  $foto_profile = "default.png";
  if (!empty($_FILES['foto_profile']['name'])) {
    $ext = pathinfo($_FILES['foto_profile']['name'], PATHINFO_EXTENSION);
    $foto_profile = uniqid() . "." . $ext;
    move_uploaded_file($_FILES['foto_profile']['tmp_name'], "uploads/foto_profile/" . $foto_profile);
  }

  /*
  |--------------------------------------------------------------------------
  | Insert User (Prepared Statement)
  |--------------------------------------------------------------------------
  */
  $stmtUser = $conn->prepare("INSERT INTO users (id_company, name, email, password, role, jabatan, foto_profile, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmtUser->bind_param("isssssss", $id_company, $name, $email, $password, $role, $jabatan, $foto_profile, $created_at);

  if ($stmtUser->execute()) {
    unset($_SESSION['name'], $_SESSION['email'], $_SESSION['jabatan']);

    $_SESSION['status'] = 'success';
    $_SESSION['message'] = 'Pendaftaran berhasil';
    header("Location: login");  // 👉 sesuai permintaan (bukan login)
    exit;
  }

  $_SESSION['status'] = 'error';
  $_SESSION['message'] = 'Pendaftaran gagal, coba lagi.';
  header("Location: register");
  exit;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>HR Register</title>
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet" />
  <script src="assets/js/pace.min.js"></script>
  <!--favicon-->
  <link rel="icon" href="assets/images/logo-trivanox.webp" type="image/x-icon">
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
    <div class="card card-authentication1 mx-auto my-4">
      <div class="card-body">
        <div class="card-content p-2">
          <div class="text-center px-5">
            <img src="assets/images/logo-trivanox-text.webp" style="height: 100px;" class="img-fluid" alt="logo icon">
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
          <div class="card-title text-uppercase text-center mt-3">Pendaftaran Akun Karyawan</div>

          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $csrf_token; ?>">

            <div class="form-group">
              <label for="code_company" class="sr-only">Kode Perusahaan</label>
              <small class="mb-0">Kode Perusahaan</small>
              <div class="position-relative has-icon-right">
                <input type="text" name="code_company" placeholder="Contoh: A-0001" required class="form-control input-shadow" placeholder="Kode Perusahaan" value="<?= $code_company; ?>">
                <div class="form-control-position">
                  <i class="icon-info"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="code_verification" class="sr-only">Kode Verifikasi (6 digit)</label>
              <small class="mb-0">Kode Verifikasi</small>
              <div class="position-relative has-icon-right">
                <input type="number" name="code_verification" required minlength="6" maxlength="6" class="form-control input-shadow" placeholder="Kode Verifikasi Perusahaan (6 Digit)" value="<?= $code_verification; ?>">
                <div class="form-control-position">
                  <i class="icon-info"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="name" class="sr-only">Nama Lengkap</label>
              <div class="position-relative has-icon-right">
                <input type="text" name="name" required class="form-control input-shadow" placeholder="Nama Lengkap" value="<?= $name; ?>">
                <div class="form-control-position">
                  <i class="icon-user"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="email" class="sr-only">Alamat Email</label>
              <div class="position-relative has-icon-right">
                <input type="email" name="email" required class="form-control input-shadow" placeholder="Alamat Email" value="<?= $email; ?>">
                <div class="form-control-position">
                  <i class="icon-envelope-open"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="password" class="sr-only">Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="password" required minlength="6" class="form-control input-shadow" placeholder="Password"></input>
                <div class="form-control-position">
                  <i class="icon-lock"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="confirm_password" class="sr-only">Ulangi Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="confirm_password" required minlength="6" class="form-control input-shadow" placeholder="Ulangi Password"></input>
                <div class="form-control-position">
                  <i class="icon-lock"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="foto_profile" class="sr-only">Jabatan</label>
              <small class="mb-0">Foto Profil</small>
              <div class="position-relative has-icon-right">
                <input type="file" name="foto_profile" accept="image/*" required class="form-control input-shadow" placeholder="Foto Profil">
                <div class="form-control-position">
                  <i class="zmdi zmdi-attachment-alt"></i>
                </div>
              </div>
            </div>

            <div class="form-group">
              <div class="icheck-material-white">
                <input type="checkbox" id="terms" name="terms" value="1" required />
                <label for="terms">Saya menyetujui <a href="#">Terms & Conditions</a></label>
              </div>
            </div>

            <button type="submit" name="submit" class="btn btn-light btn-block waves-effect waves-light">Daftar</button>

          </form>
        </div>
      </div>
      <div class="card-footer text-center py-3">
        <a href="login"><button type="button" class="btn btn-light btn-block">Halaman Login</button></a>
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
    });

  </script>
  <script src="assets/js/app-script.js"></script>

</body>

</html>