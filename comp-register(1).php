<?php
include 'error_handler.php';
// include 'rate-limiter.php';
session_start();
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

// Load PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Flash message (auto remove setelah tampil)
$status = $_SESSION['status'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['status'], $_SESSION['message']);

// Simpan form lama di session supaya tidak hilang
function old($key) {
  return $_SESSION['old'][$key] ?? '';
}

// Generate Code Company
function generateCodeCompany($conn){
  do {
    $code = 'A-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("SELECT id_company FROM company WHERE code_company = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();
  } while ($stmt->num_rows > 0);

  return $code;
}

// ==== FORM SUBMIT ====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $_SESSION['old'] = $_POST;
    
    if (!isset($_POST['terms'])) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Anda harus menyetujui Terms & Conditions';
        header("Location: comp-register");
        exit;
    }

    $id_package         = trim($_POST['id_package']);
    $code_verification  = trim($_POST['code_verification']);
    $nama_company       = strtoupper(trim($_POST['nama_company']));
    $alamat_company     = ucwords(trim($_POST['alamat_company']));
    $nomor_company      = trim($_POST['nomor_company']);
    $name               = htmlspecialchars(trim($_POST['name']));
    $jabatan            = htmlspecialchars(trim($_POST['jabatan']));
    $email              = trim($_POST['email_company']);
    $password           = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $real_password      = $_POST['password'];
    $confirm_password   = $_POST['confirm_password'];
    $role               = "staff";
    $created_at         = date("Y-m-d H:i:s");
    $datenow            = date("Y-m-d");

    if (!preg_match('/^[0-9]{6}$/', $code_verification)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Kode verifikasi harus 6 digit angka';
        header("Location: comp-register");
        exit;
    }

    if (strlen($nomor_company) < 8 || strlen($nomor_company) > 14) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Nomor HP harus 8–14 digit';
        header("Location: comp-register");
        exit;
    }

    // 🔐 Cek email sudah ada atau belum
    $checkEmail = $conn->prepare("SELECT id_company FROM company WHERE email_company = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Email sudah terdaftar. Gunakan email lain.';
        header("Location: comp-register");
        exit;
    }
        
    // Validasi kecocokan password & ulangi password
    if ($real_password !== $confirm_password) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Password dan Ulangi Password tidak sama!';
        header("Location: comp-register");
        exit();
    }
    
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
        header("Location: comp-register");
        exit;
    }
    
    $company_logo = "uploads/company_logo/default.png";
    if (!empty($_FILES['company_logo']['name'])) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Format logo tidak diizinkan';
            header("Location: comp-register");
            exit;
        }

        if ($_FILES['company_logo']['size'] > 2 * 1024 * 1024) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Ukuran logo maksimal 2MB';
            header("Location: comp-register");
            exit;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['company_logo']['tmp_name']);
        finfo_close($finfo);
        
        $allowedMime = ['image/jpeg','image/png','image/webp'];
        
        if (!in_array($mime, $allowedMime)) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'File bukan gambar valid';
            header("Location: comp-register");
            exit;
        }

        $uploadDir = 'uploads/company_logo/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'logo_company_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        $old_logo = $company_logo; // simpan logo lama
        if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $filepath)) {
            $company_logo = $filepath;
        
            // hapus logo lama (kecuali default)
            if ($old_logo 
                && file_exists($old_logo) 
                && $old_logo !== 'uploads/company_logo/default.png') {
                unlink($old_logo);
            }
        }
    }
    
    if ($_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Gagal upload logo perusahaan';
        header("Location: comp-register");
        exit;
    }

    $code_company = generateCodeCompany($conn);
    $now = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    
    $cek = $conn->prepare("
        SELECT id_company 
        FROM company 
        WHERE code_company = ? 
        LIMIT 1
    ");
    $cek->bind_param("s", $code_company);
    $cek->execute();
    $result = $cek->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // =============================
        // UPDATE
        // =============================
        $id_company = $row['id_company'];
        $query = "UPDATE company SET
            id_package = ?,
            code_verification = ?,
            nama_company = ?,
            alamat_company = ?,
            nomor_company = ?,
            email_company = ?,
            company_logo = ?,
            status_company = ?,
            updated_at = ?
            WHERE id_company = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sssssssssi",
            $id_package,
            $code_verification,
            $nama_company,
            $alamat_company,
            $nomor_company,
            $email,
            $company_logo,
            $status_company,
            $now,
            $id_company
        );
        $stmt->execute();
        if ($stmt->execute()) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Gagal memperbarui perusahaan';
            header("Location: comp-register");
            exit;
        }
        $stmt->close();
    } else {
        // =============================
        // INSERT
        // =============================
        $query = "INSERT INTO company(
            id_package, code_company, code_verification, nama_company,
            alamat_company, nomor_company, email_company, company_logo,
            status_company, created_at, expired_at, updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssssssss",
            $id_package,
            $code_company,
            $code_verification,
            $nama_company,
            $alamat_company,
            $nomor_company,
            $email,
            $company_logo,
            $status_company,
            $now,
            $now,
            $now
        );
        $stmt->execute();
        if ($stmt->execute()) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Gagal menyimpan data perusahaan';
            header("Location: comp-register");
            exit;
        }
        $stmt->close();
    }
    
    $cek->close();
    $conn->commit();

    $conn->begin_transaction();
    $cek = $conn->prepare("
        SELECT id_company 
        FROM branch_company 
        WHERE code_company = ? 
        LIMIT 1
    ");
    $cek->bind_param("s", $code_company);
    $cek->execute();
    $result = $cek->get_result();
    
    $kode_cabang = '';
    $nama_cabang = 'Kantor Pusat';
    if ($row = $result->fetch_assoc()) {
        // =============================
        // UPDATE
        // =============================
        $id_company = $row['id_company'];
        $query = "UPDATE branch_company SET
            kode_cabang = ?, nama_cabang = ?, alamat = ?, telepon = ?, email = ?, status = ?, created_at = ?, updated_at = ?
            WHERE id_company = ?";
    
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssssi",
            $kode_cabang,
            $nama_cabang,
            $alamat_company,
            $nomor_company,
            $email,
            $status_company,
            $now,
            $now,
            $id_company
        );
        $stmt->execute();
        if ($stmt->execute()) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Gagal memperbarui perusahaan pusat';
            header("Location: comp-register");
            exit;
        }
        $stmt->close();
    } else {
        // =============================
        // INSERT
        // =============================
        $query = "INSERT INTO branch_company(
            id_company,	kode_cabang, nama_cabang, alamat, telepon, email, status, created_at, updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sssssssss",
            $id_company,
            $kode_cabang,
            $nama_cabang,
            $alamat_company,
            $nomor_company,
            $email,
            $status_company,
            $now,
            $now
        );
        $stmt->execute();
        if ($stmt->execute()) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Gagal menyimpan data perusahaan pusat';
            header("Location: comp-register");
            exit;
        }
        $stmt->close();
    }
    
    $cek->close();
    $conn->commit();

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
        header("Location: comp-register");
        exit;
    }
    $id_company = $resultCompany->fetch_assoc()['id_company'];
    /*
    |--------------------------------------------------------------------------
    | Upload Foto Profile
    |--------------------------------------------------------------------------
    */
    $foto_profile = "uploads/foto_profile/default.png";
    if (!empty($_FILES['foto_profile']['name'])) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['foto_profile']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Format foto tidak diizinkan';
            header("Location: comp-register");
            exit;
        }
        if ($_FILES['foto_profile']['size'] > 2 * 1024 * 1024) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Ukuran foto maksimal 2MB';
            header("Location: comp-register");
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['foto_profile']['tmp_name']);
        finfo_close($finfo);
        $allowedMime = ['image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $allowedMime)) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'File bukan gambar valid';
            header("Location: comp-register");
            exit;
        }
        $uploadDir = 'uploads/foto_profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = 'profile_' . $id_company . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        $foto_profile_lama = $foto_profile; // simpan foto lama
        if (move_uploaded_file($_FILES['foto_profile']['tmp_name'], $filepath)) {
            $foto_profile = $filepath;
            // hapus foto lama (kecuali default)
            if ($foto_profile_lama &&
                file_exists($foto_profile_lama) &&
                $foto_profile_lama !== 'uploads/foto_profile/default.png'
            ) {
                unlink($foto_profile_lama);
            }
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = 'Foto profil berhasil disimpan.';
        }
    }
    if ($_FILES['foto_profile']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Gagal upload foto profile';
        header("Location: comp-register");
        exit;
    }
    /*
    |--------------------------------------------------------------------------
    | Insert User (Prepared Statement)
    |--------------------------------------------------------------------------
    */
    $stmtUser = $conn->prepare("INSERT INTO users (id_company, name, tanggal_masuk, email, password, role, jabatan, foto_profile, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtUser->bind_param("issssssss", $id_company, $name, $datenow, $email, $password, $role, $jabatan, $foto_profile, $created_at);
    if ($stmtUser->execute()) {
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = 'Pendaftaran berhasil';
    }
    $mail = new PHPMailer(true);
    try {
        // Konfigurasi SMTP Rumahweb
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; // Outgoing Server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@ryzola.com'; // Email di cPanel
        $mail->Password   = 'Lieur@hteuing90'; // Ganti dengan password email dari cPanel
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL/TLS
        $mail->Port       = 465; // Port SMTP SSL
        // Pengaturan Email
        $mail->setFrom('info@ryzola.com', 'Ryzola');
        $mail->addAddress($email); // Email tujuan
        $mail->addReplyTo("info@ryzola.com", "Ryzola Support");
        $mail->addCustomHeader("X-Mailer", "PHP Mailer");
        $mail->CharSet = "UTF-8";
        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = 'Pendaftaran Perusahaan Berhasil';
        $mail->Body    = "<p>Terima kasih telah melakukan pendaftaran perusahaan Anda di sistem kami.<br>
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
        // Kirim Email
        $mail->send();
        // echo "Email konfirmasi berhasil dikirim ke $email_company!";
    } catch (Exception $e) {
        // echo "Gagal mengirim email. Error: {$mail->ErrorInfo}";
        header('Location: login');
        exit;
    }
    header("Location: login");  // 👉 sesuai permintaan (bukan login)
    exit;
}

// Ambil Paket
$packages = mysqli_query($conn, "SELECT id_package,nama_package FROM package");

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