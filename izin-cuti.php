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
function old($key)
{
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
$id_user = $sesi_user["id"];
$id_company = $sesi_user["id_company"];

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>Izin, Cuti & Sakit - HR Management</title>
  <link rel="icon" href="assets/images/logo-trivanox.png" type="image/x-icon">
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet" />
  <script src="assets/js/pace.min.js"></script>
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
    .info-box {
      border-left: 4px solid #0d6efd;
      padding: 12px;
      margin-top: 8px;
      border-radius: 6px;
    }

    ul {
      margin: 0;
      padding-left: 18px;
    }
  </style>

</head>

<body class="bg-theme bg-theme1">

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

    <?php include 'left-sidebar.php'; ?>
    <?php include 'topbar.php'; ?>
    <div class="clearfix"></div>

    <div class="content-wrapper">
      <div class="container-fluid">

        <!-- Page Heading -->
        <h4 class="h4 mb-2 text-gray-800">Izin, Cuti & Sakit</h4>
        <p class="h5"><?= $_SESSION["user"]["name"] ?></p>
        <?php if ($status): ?>
          <div id="messages">
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mt-3">
              <div class="text-center p-1">
                <strong><?= htmlspecialchars($message) ?></strong>
              </div>
            </div>
          </div>
        <?php endif; ?>


        <div class="card mx-auto">
          <div class="card-body">
            <div class="card-content p-2">
              <div class="card-title">Form Pengajuan Izin, Cuti & Sakit</div>
              <form action="" method="POST">
                <input type="hidden" name="id_user" id="id_user" value="<?= $id_user; ?>">
                <div class="form-group">
                  <label for="jenis">Jenis</label>
                  <select class="form-control" id="jenis" name="jenis" onchange="ubahKategori()">
                    <option value="">-- Pilih Jenis --</option>
                    <option value="cuti" <?= old('jenis') == 'cuti' ? 'selected' : '' ?>>Cuti</option>
                    <option value="izin" <?= old('jenis') == 'izin' ? 'selected' : '' ?>>Izin</option>
                    <option value="sakit" <?= old('jenis') == 'sakit' ? 'selected' : '' ?>>Sakit</option>
                  </select>
                </div>
                <div class="form-group" id="kategori-group" style="display:none;">
                  <label for="kategori">Kategori</label>
                  <select class="form-control" id="kategori" name="kategori" onchange="tampilListSakit()">
                    <option value="">-- Pilih Kategori --</option>
                    <option value="Cuti Bulanan" <?= old('kategori') == 'Cuti Bulanan' ? 'selected' : '' ?>>Cuti Bulanan</option>
                    <option value="Cuti Tahunan" <?= old('kategori') == 'Cuti Tahunan' ? 'selected' : '' ?>>Cuti Tahunan</option>
                    <option value="Cuti Melahirkan" <?= old('kategori') == 'Cuti Melahirkan' ? 'selected' : '' ?>>Cuti Melahirkan</option>
                    <option value="Cuti Khusus" <?= old('kategori') == 'Cuti Khusus' ? 'selected' : '' ?>>Cuti Khusus</option>
                    <option value="Cuti Penting" <?= old('kategori') == 'Cuti Penting' ? 'selected' : '' ?>>Cuti Penting</option>
                    <option value="Izin Datang Terlambat" <?= old('kategori') == 'Izin Datang Terlambat' ? 'selected' : '' ?>>Izin Datang Terlambat</option>
                    <option value="Izin Pulang Lebih Awal" <?= old('kategori') == 'Izin Pulang Lebih Awal' ? 'selected' : '' ?>>Izin Pulang Lebih Awal</option>
                    <option value="Izin Dinas Luar" <?= old('kategori') == 'Izin Dinas Luar' ? 'selected' : '' ?>>Izin Dinas Luar</option>
                    <option value="Izin Tidak Masuk Kerja" <?= old('kategori') == 'Izin Tidak Masuk Kerja' ? 'selected' : '' ?>>Izin Tidak Masuk Kerja</option>
                    <option value="Izin Keperluan Kantor" <?= old('kategori') == 'Izin Keperluan Kantor' ? 'selected' : '' ?>>Izin Keperluan Kantor</option>
                    <option value="Izin Keperluan Pribadi" <?= old('kategori') == 'Izin Keperluan Pribadi' ? 'selected' : '' ?>>Izin Keperluan Pribadi</option>
                    <option value="Sakit Umum" <?= old('kategori') == 'Sakit Umum' ? 'selected' : '' ?>>Sakit Umum</option>
                    <option value="Sakit dengan Bukti Medis" <?= old('kategori') == 'Sakit dengan Bukti Medis' ? 'selected' : '' ?>>Sakit dengan Bukti Medis</option>
                    <option value="Sakit Khusus" <?= old('kategori') == 'Sakit Khusus' ? 'selected' : '' ?>>Sakit Khusus</option>
                    <option value="Sakit Psikologis" <?= old('kategori') == 'Sakit Psikologis' ? 'selected' : '' ?>>Sakit Psikologis</option>
                  </select>
                  <!-- List Text -->
                  <div id="listBox" class="info-box" style="display:none;">
                    <ul id="listDetail"></ul>
                  </div>
                </div>
                <div class="form-group">
                  <label for="tanggal_mulai">Tanggal Mulai</label>
                  <input type="date" style="width:170px" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?= old('tanggal_mulai') ?>" onchange="hitungHari()">
                </div>
                <div class="form-group">
                  <label for="tanggal_selesai">Tanggal Selesai</label>
                  <input type="date" style="width:170px" class="form-control" id="tanggal_selesai" name="tanggal_selesai" value="<?= old('tanggal_selesai') ?>" onchange="hitungHari()">
                </div>
                <!-- Half Day -->
                <div class="form-group">
                  <div class="icheck-material-white d-inline-block me-2">
                    <input type="checkbox" id="is_half_day" name="is_half_day" value="1" />
                    <label for="is_half_day">Setengah Hari (Half Day)</label>
                  </div>
                </div>
                <div class="form-group">
                  <label for="jumlah_hari">Jumlah Hari</label>
                  <input type="number" class="form-control" id="jumlah_hari" name="jumlah_hari" placeholder="Jumlah hari" value="<?= old('jumlah_hari'); ?>" required readonly>
                </div>
                <div class="form-group">
                  <label for="alasan">Alasan</label>
                  <textarea class="form-control" id="alasan" name="alasan" rows="3"
                    placeholder="Alasan Lengkap"><?= old('alasan') ?></textarea>
                </div>
                <div class="form-group">
                  <label>File Pendukung</label>
                  <input type="file" class="form-control mb-2" id="file_pendukung" name="file_pendukung">
                </div>

                <div class="form-group text-center">
                  <button type="submit" class="btn btn-light mx-2">
                    <i class="zmdi zmdi-save"></i> Ajukan
                  </button>
                  <a href="index" class="btn btn-light mx-2">
                    <i class="zmdi zmdi-close"></i> Batal
                  </a>
                </div>

              </form>
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

  <!-- Custom scripts -->
  <script src="assets/js/app-script.js"></script>


  <script>
    function ubahKategori() {
      const jenis = document.getElementById("jenis").value;
      const kategoriGroup = document.getElementById("kategori-group");
      const kategori = document.getElementById("kategori");

      // Reset opsi
      kategori.innerHTML = '<option value="">-- Pilih Kategori --</option>';

      if (jenis === "cuti") {
        kategoriGroup.style.display = "block";

        const cutiOptions = [
          "Cuti Bulanan",
          "Cuti Tahunan",
          "Cuti Melahirkan",
          "Cuti Khusus",
          "Cuti Penting"
        ];

        cutiOptions.forEach(item => {
          const option = document.createElement("option");
          option.value = item;
          option.text = item;
          kategori.appendChild(option);
        });

      } else if (jenis === "izin") {
        kategoriGroup.style.display = "block";

        const izinOptions = [
          "Izin Datang Terlambat",
          "Izin Pulang Lebih Awal",
          "Izin Dinas Luar",
          "Izin Tidak Masuk Kerja",
          "Izin Keperluan Kantor",
          "Izin Keperluan Pribadi"
        ];

        izinOptions.forEach(item => {
          const option = document.createElement("option");
          option.value = item;
          option.text = item;
          kategori.appendChild(option);
        });

      } else if (jenis === "sakit") {
        kategoriGroup.style.display = "block";

        const izinOptions = [
          "Sakit Umum",
          "Sakit dengan Bukti Medis",
          "Sakit Khusus",
          "Sakit Psikologis"
        ];

        izinOptions.forEach(item => {
          const option = document.createElement("option");
          option.value = item;
          option.text = item;
          kategori.appendChild(option);
        });

      } else {
        kategoriGroup.style.display = "none";
      }
    }
  </script>

  <script>
    function tampilListSakit() {
      const kategori = document.getElementById("kategori").value;
      const listBox = document.getElementById("listBox");
      const listDetail = document.getElementById("listDetail");

      listDetail.innerHTML = "";

      const data = {
        "Sakit Umum": [
          "Sakit Ringan (flu, demam, pusing)",
          "Sakit Berat (rawat inap)",
          "Sakit Menular",
          "Sakit Kronis"
        ],
        "Sakit dengan Bukti Medis": [
          "Sakit dengan Surat Dokter",
          "Sakit dengan Rekam Medis"
        ],
        "Sakit Khusus": [
          "Sakit Akibat Kecelakaan Kerja",
          "Sakit Akibat Kecelakaan di Luar Kerja",
          "Sakit Pasca Operasi",
          "Sakit Kehamilan (di luar cuti melahirkan)",
          "Istirahat Medis (Medical Leave)"
        ],
        "Sakit Psikologis": [
          "Kesehatan Mental (rekomendasi profesional)",
          "Burnout / Kelelahan Kerja"
        ]
      };

      if (data[kategori]) {
        listBox.style.display = "block";
        data[kategori].forEach(item => {
          const li = document.createElement("li");
          li.textContent = item;
          listDetail.appendChild(li);
        });
      } else {
        listBox.style.display = "none";
      }
    }
  </script>

  <script>
    function hitungHari() {
      const mulai = document.getElementById("tanggal_mulai").value;
      const selesai = document.getElementById("tanggal_selesai").value;
      const jumlahHari = document.getElementById("jumlah_hari");

      if (mulai && selesai) {
        const tglMulai = new Date(mulai);
        const tglSelesai = new Date(selesai);

        // Validasi jika tanggal selesai < tanggal mulai
        if (tglSelesai < tglMulai) {
          alert("Tanggal selesai tidak boleh lebih kecil dari tanggal mulai");
          jumlahHari.value = "";
          return;
        }

        // Hitung selisih hari (+1 agar inklusif)
        const selisih = Math.floor(
          (tglSelesai - tglMulai) / (1000 * 60 * 60 * 24)
        ) + 1;

        jumlahHari.value = selisih;
      }
    }
  </script>

</body>

</html>