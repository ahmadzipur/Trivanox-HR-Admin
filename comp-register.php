<?php
//declare(strict_types=1);
// include 'rate-limiter.php';
session_start();
include 'koneksi.php';
include 'error_handler.php';
date_default_timezone_set('Asia/Jakarta');

/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
*/
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
    
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
    
// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
// Simpan form lama di session supaya tidak hilang
function old($key) {
  return $_SESSION['old'][$key] ?? '';
}
// Flash
$status  = $_SESSION['status'] ?? null;
$message = $_SESSION['message'] ?? null;
unset($_SESSION['status'], $_SESSION['message']);
// GET → tampilkan form
if ($isPost) {
    // POST → proses data
    $_SESSION['old'] = $_POST;
    
    /*
    |--------------------------------------------------------------------------
    | GUARD
    |--------------------------------------------------------------------------
    */
    $_SESSION['old'] = $_POST;
    
    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */
    function fail(string $msg): void {
        $_SESSION['status']  = 'error';
        $_SESSION['message'] = $msg;
        header("Location: comp-register");
        exit;
    }
    
    function uploadImage(string $field, string $dir, string $prefix): string {
        if (empty($_FILES[$field]['name'])) {
            return $field === 'company_logo'
                ? 'uploads/company_logo/default.png'
                : 'uploads/foto_profile/default.png';
        }
    
        $allowedExt  = ['jpg','jpeg','png','webp'];
        $allowedMime = ['image/jpeg','image/png','image/webp'];
    
        $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($_FILES[$field]['tmp_name']);
    
        if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
            fail('Format gambar tidak valid');
        }
    
        if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
            fail('Ukuran gambar maksimal 2MB');
        }
    
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    
        $filename = $prefix . '_' . time() . '.' . $ext;
        $path     = $dir . $filename;
    
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $path)) {
            fail('Gagal upload file');
        }
    
        return $path;
    }
    
    function generateCodeCompany(mysqli $conn): string {
        do {
            $code = 'A-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("SELECT 1 FROM company WHERE code_company = ?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $stmt->store_result();
        } while ($stmt->num_rows > 0);
    
        return $code;
    }
    
    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */
    if (!isset($_POST['terms'])) {
        fail('Anda harus menyetujui Terms & Conditions');
    }
    
    if (!preg_match('/^[0-9]{6}$/', $_POST['code_verification'])) {
        fail('Kode verifikasi harus 6 digit');
    }
    
    if ($_POST['password'] !== $_POST['confirm_password']) {
        fail('Password tidak sama');
    }
    
    if (strlen($_POST['password']) < 8) {
        fail('Password minimal 8 karakter');
    }
    
    /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */
    $id_package        = $_POST['id_package'];
    $code_verification = $_POST['code_verification'];
    $nama_company      = strtoupper(trim($_POST['nama_company']));
    $alamat_company    = trim($_POST['alamat_company']);
    $nomor_company     = trim($_POST['nomor_company']);
    $email_company     = trim($_POST['email_company']);
    
    $name     = trim($_POST['name']);
    $jabatan  = trim($_POST['jabatan']);
    $email    = $email_company;
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $status_company = 'nonactive';
    $role           = 'staff';
    $now            = date('Y-m-d H:i:s');
    $datenow        = date('Y-m-d');
    
    /*
    |--------------------------------------------------------------------------
    | FILE UPLOAD
    |--------------------------------------------------------------------------
    */
    $company_logo = uploadImage('company_logo', 'uploads/company_logo/', 'logo');
    $foto_profile = uploadImage('foto_profile', 'uploads/foto_profile/', 'profile');
    
    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI DATABASE
    |--------------------------------------------------------------------------
    */
    $conn->begin_transaction();
    
    try {
    
        // COMPANY
        $code_company = generateCodeCompany($conn);
    
        $stmt = $conn->prepare("
            INSERT INTO company (
                id_package, code_company, code_verification,
                nama_company, alamat_company, nomor_company,
                email_company, company_logo, status_company,
                created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "sssssssssss",
            $id_package, $code_company, $code_verification,
            $nama_company, $alamat_company, $nomor_company,
            $email_company, $company_logo, $status_company,
            $now, $now
        );
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan company');
        }
    
        $id_company = $conn->insert_id;
    
        // BRANCH (KANTOR PUSAT)
        $stmt = $conn->prepare("
            INSERT INTO branch_company (
                id_company, kode_cabang, nama_cabang,
                alamat, telepon, email,
                status, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $kode_cabang = '';
        $nama_cabang = 'Kantor Pusat';
    
        $stmt->bind_param(
            "issssssss",
            $id_company, $kode_cabang, $nama_cabang,
            $alamat_company, $nomor_company, $email_company,
            $status_company, $now, $now
        );
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan cabang');
        }
    
        // USER
        $stmt = $conn->prepare("
            INSERT INTO users (
                id_company, name, tanggal_masuk,
                email, password, role,
                jabatan, foto_profile, created_at
            ) VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "issssssss",
            $id_company, $name, $datenow,
            $email, $password, $role,
            $jabatan, $foto_profile, $now
        );
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan user');
        }
    
        $conn->commit();
    
    } catch (Throwable $e) {
        $conn->rollback();
        fail($e->getMessage());
    }
    
    /*
    |--------------------------------------------------------------------------
    | EMAIL (DI LUAR TRANSAKSI)
    |--------------------------------------------------------------------------
    */
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
    
        $mail->setFrom($_ENV['SMTP_USER'], 'Ryzola');
        $mail->addAddress($email_company);
        $mail->addReplyTo($_ENV['SMTP_USER'], 'Ryzola Support');
    
        $mail->isHTML(true);
        $mail->Subject = 'Pendaftaran Perusahaan Berhasil';
        $mail->Body = "<p>Terima kasih telah melakukan pendaftaran perusahaan Anda di sistem kami.<br>
            Pendaftaran perusahaan Anda telah berhasil, dengan detail sebagai berikut:<br>
            Kode Perusahaan: <div style='font-size: 24px; font-weight: bold; color: #4d01fb; margin: 10px 0;'>$code_company </div>
            Kode Verifikasi: <div style='font-size: 24px; font-weight: bold; color: #4d01fb; margin: 10px 0;'>$code_verification  </div><br>
            Silakan simpan kedua kode tersebut dengan aman, karena akan digunakan untuk:<br>
            - Pendaftaran user<br>
            - Proses verifikasi<br>
            - Akses ke sistem <br>
            Jika Anda belum mendaftarkan karyawan, silakan lakukan registrasi dengan menggunakan Kode Perusahaan dan Kode Verifikasi di halaman karyawan.<br>
            Apabila Anda tidak merasa melakukan pendaftaran ini, mohon abaikan email ini.<br>
            Terima kasih atas kepercayaan Anda menggunakan layanan kami.</p>
            <p>Hormat kami,<br><br>
            Tim Support Trivanox</p>";
        $mail->AltBody = "Terima kasih telah melakukan pendaftaran perusahaan Anda di Trivanox.\nKode Perusahaan: $code_company \nKode Verifikasi: $code_verification ";
    
        $mail->send();
    } catch (Exception $e) {
        error_log('Email gagal: ' . $e->getMessage());
    }
    
    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */
    unset($_SESSION['old']);
    $_SESSION['status']  = 'success';
    $_SESSION['message'] = 'Pendaftaran berhasil';
    header("Location: login");
    exit;
}

// ===== GET MODE (TAMPILKAN FORM) =====
$packages = mysqli_query($conn, "SELECT id_package, nama_package FROM package ORDER BY id_package ASC");

//if (!$packages) {
//    die("Query error: " . mysqli_error($conn));
//}

//echo "Jumlah package: " . mysqli_num_rows($packages);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <meta name="google" content="notranslate">
  <title>Company Register - HR Management</title>
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet" />
  <script src="assets/js/pace.min.js"></script>
  <!--favicon-->
  <link rel="icon" href="assets/images/logo-trivanox.png" type="image/x-icon">
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

          <div class="card-title text-uppercase text-center py-3">Pendaftaran Perusahaan</div>
          
          <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label for="id_package" class="sr-only">Pilih Paket</label>
              <select class="form-control" name="id_package" id="id_package" required>
                <option value="">-- Pilih Paket --</option>
                <?php while ($p = mysqli_fetch_assoc($packages)): ?>
                  <option value="<?= $p['id_package']; ?>"
                    <?= old('id_package') == $p['id_package'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nama_package']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="code_verification" class="sr-only">Kode Verifikasi Perusahaan</label>
              <input type="number" name="code_verification" id="code_verification" required 
			  minlength="6" maxlength="6"
                class="form-control input-shadow"
                placeholder="Kode Verifikasi Perusahaan (6 Digit)"
                value="<?= old('code_verification'); ?>">
            </div>

            <div class="form-group">
              <label for="nama_company" class="sr-only">Nama Perusahaan</label>
              <input type="text" name="nama_company" id="nama_company" required 
			  class="form-control input-shadow"
                placeholder="Nama Perusahaan" value="<?= old('nama_company'); ?>">
            </div>

            <div class="form-group">
              <label for="alamat_company" class="sr-only">Alamat Perusahaan</label>
              <textarea name="alamat_company" id="alamat_company" required 
			  class="form-control input-shadow"
                placeholder="Alamat Perusahaan"><?= old('alamat_company'); ?></textarea>
            </div>

            <div class="form-group">
              <label for="nomor_company" class="sr-only">Nomor Telepon Perusahaan</label>
              <input type="number" name="nomor_company" id="nomor_company" required 
			  minlength="8" maxlength="14"
                class="form-control input-shadow"
                placeholder="Nomor Telepon Perusahaan" value="<?= old('nomor_company'); ?>">
            </div>

            <div class="form-group">
              <label for="name" class="sr-only">Nama Lengkap</label>
              <div class="position-relative has-icon-right">
                <input type="text" name="name" id="name" required 
				class="form-control input-shadow" placeholder="Nama Lengkap" 
				value="<?= old('name'); ?>">
                <div class="form-control-position">
                  <i class="icon-user"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="jabatan" class="sr-only">Jabatan</label>
              <div class="position-relative has-icon-right">
                <input type="text" name="jabatan" id="jabatan" required 
				class="form-control input-shadow" placeholder="Jabatan" 
				value="<?= old('jabatan'); ?>">
                <div class="form-control-position">
                  <i class="zmdi zmdi-assignment-account"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="foto_profile" class="sr-only">Foto Profil</label>
              <small class="mb-0">Foto Profil</small>
              <div class="position-relative has-icon-right">
                <input type="file" name="foto_profile" id="foto_profile" accept="image/*" 
				required class="form-control input-shadow" placeholder="Foto Profil">
                <div class="form-control-position">
                  <i class="zmdi zmdi-attachment-alt"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="company_logo" class="sr-only">Logo Perusahaan</label>
              <small class="mb-0">Logo Perusahaan</small>
              <div class="position-relative has-icon-right">
                <input type="file" name="company_logo" id="company_logo" accept="image/*" required 
				class="form-control input-shadow" placeholder="Foto Profil">
                <div class="form-control-position">
                  <i class="zmdi zmdi-attachment-alt"></i>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="email_company" class="sr-only">Alamat Email</label>
              <input type="email" name="email_company" id="email_company" required 
			  class="form-control input-shadow"
                placeholder="Alamat Email" value="<?= old('email_company'); ?>">
            </div>
            <div class="form-group">
              <label for="password" class="sr-only">Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="password" id="password" required 
				minlength="6" class="form-control input-shadow" placeholder="Password"></input>
                <div class="form-control-position">
                  <i class="icon-lock"></i>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="confirm_password" class="sr-only">Ulangi Password</label>
              <div class="position-relative has-icon-right">
                <input type="password" name="confirm_password" required minlength="6" 
				class="form-control input-shadow" placeholder="Ulangi Password"></input>
                <div class="form-control-position">
                  <i class="icon-lock"></i>
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <div class="icheck-material-white">
                <input type="checkbox" id="terms" name="terms" value="1" required />
                <label for="terms">Saya menyetujui <a href="#">Terms & Conditions</a></label>
              </div>
            </div>
            <button type="submit" class="btn btn-light btn-block">Daftar</button>
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