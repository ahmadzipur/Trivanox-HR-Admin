<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
include 'encryption.php';
require_once 'config_mail.php';
date_default_timezone_set('Asia/Jakarta');
// Kunci rahasia
$key = 'AhmadZaelani23552011179';
// Inisialisasi kelas
$encryption = new Encryption($key);
// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

// Load PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

$key = 'AhmadZaelani23552011179';
$encryption = new Encryption($key);

$status = $_SESSION['status'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['status'], $_SESSION['message']);
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function fail($msg) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = $msg;
    header('Location: reset-password');
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$q = $_GET['q'] ?? null;
$e = $_GET['e'] ?? null;

// ============================
// POST HANDLER
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        fail('Invalid CSRF Token');
    }
    
    // ============================
    // REQUEST RESET (KIRIM EMAIL)
    // ============================
    if (empty($_POST['password'])) {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code_verification'] ?? '');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Format email tidak valid');
        }
    
        $stmt = $conn->prepare("SELECT u.id, u.name, c.code_verification FROM users u JOIN company c ON u.id_company=c.id_company WHERE u.email=?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $name = $data['name'];

        if ((string)$code !== (string)$data['code_verification']) {
            fail('Email atau kode verifikasi salah');
        }

        $token = bin2hex(random_bytes(32));
        $expired = date('Y-m-d H:i:s', time() + 1800);
        $stmt = $conn->prepare("UPDATE users SET remember_token=?, token_expired_at=? WHERE email=?");
        $stmt->bind_param('sss', $token, $expired, $email);
        $stmt->execute();
        $stmt->close();

        $eenc = urlencode($encryption->encrypt($email));
        $link = "https://ryzola.com/trivanox/reset-password?q=$token&e=$eenc";

        $mail = new PHPMailer(true);
        
        try {
            // Konfigurasi SMTP Rumahweb
            $mail->isSMTP();
            $mail->Host       = $config['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['SMTP_USER'];
            $mail->Password   = $config['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
        
            // Pengaturan Email
            $mail->setFrom($config['SMTP_USER'], 'Ryzola');
            $mail->addAddress($email); // Email tujuan
            $mail->addReplyTo($config['SMTP_USER'], "Ryzola Support");
            $mail->addCustomHeader("X-Mailer", "PHP Mailer");
            $mail->CharSet = "UTF-8";

            // Konten Email
            $mail->isHTML(true);
            $mail->Subject = 'Instruksi Atur Ulang Kata Sandi Anda';
            $mail->Body    = "<p>Halo, $name.<br>
            Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda. Jangan khawatir, Anda dapat membuat kata sandi baru dengan mengeklik tombol di bawah ini:</p><br>
            <div style='text-align: center; padding: 20px 0;'>
            <a href='$link' 
                   style='background-color: #007BFF; 
                          color: #ffffff; 
                          padding: 14px 28px; 
                          text-decoration: none; 
                          border-radius: 6px; 
                          display: inline-block; 
                          font-family: Arial, sans-serif; 
                          font-size: 16px; 
                          font-weight: bold;
                          box-shadow: 0 2px 5px rgba(0,0,0,0.2);'>
                    Atur Ulang Kata Sandi Sekarang
                </a>
            </div>
            <p style='font-family: Arial, sans-serif; font-size: 13px; color: #666666; text-align: center; margin-top: 25px;'>
                Jika tombol di atas tidak berfungsi, klik atau salin dan tempel tautan berikut ke browser Anda:<br>
                <a href='$link' style='color: #007BFF;'>$link</a>
            </p>
            <p>Penting untuk diketahui:</p>
            <p>- Tautan ini hanya berlaku selama 30 menit demi keamanan akun Anda.</p>
            <p>- Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini. Kata sandi Anda tidak akan berubah.</p>
            <p>Jika Anda membutuhkan bantuan lebih lanjut, silakan hubungi tim kami melalui Pusat Bantuan Resmi atau balas email ini.</p>
            <p>Salam hangat,<br><br>
            Tim Support Trivanox</p>";
                                
            $mail->AltBody = "Instruksi Atur Ulang Kata Sandi Anda";
        
            // Kirim Email
            $mail->send();
        } catch (Exception $e) {
            // echo "Gagal mengirim email. Error: {$mail->ErrorInfo}";
            header('Location: login');
            exit;
        }

        $_SESSION['status'] = 'success';
        $_SESSION['message'] = 'Link reset password telah dikirim ke email Anda.';
        header('Location: reset-password');
        exit;
    } else {
        // ============================
        // SUBMIT PASSWORD BARU
        // ============================
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $code = $_POST['code_verification'] ?? '';
        
        if ($password !== $confirm) {
            fail('Password dan Ulangi Password tidak sama');
        }
        
        $email = $encryption->decrypt($e);
        
        $stmt = $conn->prepare("SELECT remember_token, token_expired_at, c.code_verification FROM users u JOIN company c ON u.id_company=c.id_company WHERE u.email=?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$data || $data['remember_token'] !== $q) {
            fail('Link tidak valid');
        }
        
        if (strtotime($data['token_expired_at']) < time()) {
            fail('Link sudah kadaluarsa');
        }
        
        if ((string)$code !== (string)$data['code_verification']) {
            fail('Kode verifikasi salah');
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=?, remember_token=NULL, token_expired_at=NULL WHERE email=?");
        $stmt->bind_param('ss', $hash, $email);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = 'Password berhasil direset, silakan login.';
        header('Location: login');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Login HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>Lupa Password - HR Management</title>
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
            
            <?php else: ?>

          <div class="card-title text-uppercase text-center py-2">Lupa Password</div>          
          <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <?php if(empty($q)): ?>
            <div class="form-group">
              <label for="email" class="sr-only">Email</label>
              <div class="position-relative has-icon-right">
                <input type="text" name="email" id="email" class="form-control input-shadow" placeholder="Email">
                <div class="form-control-position">
                  <i class="icon-envelope"></i>
                </div>
              </div>
            </div>
            <?php else: ?>
            <p class="text-center">Buat password baru Anda</p>
            <div class="form-group">
              <label for="password" class="sr-only">Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="password" id="password" class="form-control input-shadow" placeholder="Password">
                <div class="form-control-position">
                  <i class="icon-lock-o"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="confirm_password" class="sr-only">Ulangi Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control input-shadow" placeholder="Ulangi Password">
                <div class="form-control-position">
                  <i class="icon-lock-o"></i>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
              <label for="code_verification" class="sr-only">Kode verifikasi</label>
              <div class="position-relative has-icon-right">
                <input type="text" name="code_verification" id="code_verification" class="form-control input-shadow" placeholder="Kode verifikasi">
                <div class="form-control-position">
                  <i class="icon-lock"></i>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-light btn-block">Reset</button>
          </form>
            <?php endif; ?>
        </div>
      </div>
            
      
      <div class="card-footer text-center py-3">
        <p><a href="login">Login</a></p>
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