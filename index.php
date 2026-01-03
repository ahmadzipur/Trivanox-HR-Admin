<?php
include 'error_handler.php';
session_start();
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

// Mendapatkan waktu saat ini
$jam = date("H");  // Jam dalam format 24 jam
$hari = date("l");  // Nama hari dalam bahasa Inggris
$tanggal = date("d F Y");  // Tanggal dalam format dd F yyyy
$waktu_salam = "";
$datenow = date("Y-m-d");

// Mengubah nama hari ke bahasa Indonesia
$hari_indonesia = [
  "Sunday" => "Minggu",
  "Monday" => "Senin",
  "Tuesday" => "Selasa",
  "Wednesday" => "Rabu",
  "Thursday" => "Kamis",
  "Friday" => "Jumat",
  "Saturday" => "Sabtu"
];

// Mengubah nama bulan ke bahasa Indonesia
$bulan_indonesia = [
  "January" => "Januari",
  "February" => "Februari",
  "March" => "Maret",
  "April" => "April",
  "May" => "Mei",
  "June" => "Juni",
  "July" => "Juli",
  "August" => "Agustus",
  "September" => "September",
  "October" => "Oktober",
  "November" => "November",
  "December" => "Desember"
];

// Ubah nama hari dan bulan ke bahasa Indonesia
$hari = $hari_indonesia[$hari];
$bulan = $bulan_indonesia[date("F")];
$tanggal = date("d") . " " . $bulan . " " . date("Y");

// Menentukan ucapan berdasarkan jam
if ($jam >= 5 && $jam < 10) {
  $waktu_salam = "Selamat Pagi";
} elseif ($jam >= 10 && $jam < 15) {
  $waktu_salam = "Selamat Siang";
} elseif ($jam >= 15 && $jam < 18) {
  $waktu_salam = "Selamat Sore";
} else {
  $waktu_salam = "Selamat Malam";
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

if (!isset($_SESSION["user_id"])) {
  $_SESSION["status"] = "error";
  $_SESSION["message"] = "Silakan login terlebih dahulu.";
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
$id_package = $sesi_user['id_package'];

// Query untuk menghitung total karyawan sebelum bulan ini (dari awal hingga bulan sebelumnya)
$query_total_sebelum_bulan_ini = "
    SELECT COUNT(*) as total_karyawan_sebelum_bulan_ini
    FROM users
    WHERE id_company = ? 
    AND tanggal_masuk < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')"; // Mengambil data hingga akhir bulan sebelumnya

$stmt = $conn->prepare($query_total_sebelum_bulan_ini);
$stmt->bind_param("i", $id_company); // Pastikan id_company sudah didefinisikan
$stmt->execute();
$result_total_sebelum_bulan_ini = $stmt->get_result();
$row_total_sebelum_bulan_ini = $result_total_sebelum_bulan_ini->fetch_assoc();
$total_karyawan_sebelum_bulan_ini = $row_total_sebelum_bulan_ini['total_karyawan_sebelum_bulan_ini'];

// Query untuk menghitung total karyawan hingga bulan ini
$query_total_hingga_bulan_ini = "
    SELECT COUNT(*) as total_karyawan_hingga_bulan_ini
    FROM users
    WHERE id_company = ? 
    AND status = 'Active'
    AND tanggal_masuk <= DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)"; // Mengambil data hingga bulan ini

$stmt = $conn->prepare($query_total_hingga_bulan_ini);
$stmt->bind_param("i", $id_company); // Pastikan id_company sudah didefinisikan
$stmt->execute();
$result_total_hingga_bulan_ini = $stmt->get_result();
$row_total_hingga_bulan_ini = $result_total_hingga_bulan_ini->fetch_assoc();
$total_karyawan_hingga_bulan_ini = $row_total_hingga_bulan_ini['total_karyawan_hingga_bulan_ini'];

// Menghitung perubahan persentase
if ($total_karyawan_sebelum_bulan_ini == 0 && $total_karyawan_hingga_bulan_ini == 0) {
  // Jika sebelum bulan ini dan hingga bulan ini tidak ada karyawan
  $perubahan_persentase = 0;
  $keterangan = "Tidak Ada Perubahan";
} elseif ($total_karyawan_sebelum_bulan_ini == 0 && $total_karyawan_hingga_bulan_ini > 0) {
  // Jika sebelum bulan ini tidak ada karyawan dan hingga bulan ini ada karyawan
  $perubahan_persentase = 100;  // Kenaikan 100%
  $keterangan = "Naik";
} else {
  // Jika sebelum bulan ini ada karyawan, hitung perubahan persentase
  if ($total_karyawan_hingga_bulan_ini > $total_karyawan_sebelum_bulan_ini) {
    // Jika jumlah karyawan hingga bulan ini lebih banyak
    $perubahan_persentase = (($total_karyawan_hingga_bulan_ini - $total_karyawan_sebelum_bulan_ini) / $total_karyawan_sebelum_bulan_ini) * 100;
    $keterangan = "Naik";
  } elseif ($total_karyawan_hingga_bulan_ini < $total_karyawan_sebelum_bulan_ini) {
    // Jika jumlah karyawan hingga bulan ini lebih sedikit
    $perubahan_persentase = (($total_karyawan_sebelum_bulan_ini - $total_karyawan_hingga_bulan_ini) / $total_karyawan_sebelum_bulan_ini) * 100;
    $keterangan = "Turun";
  } else {
    // Jika jumlah karyawan sama
    $perubahan_persentase = 0;
    $keterangan = "Tidak Ada Perubahan";
  }
}

// Menampilkan hasil
//echo "Total Karyawan Sebelum Bulan Ini: " . $total_karyawan_sebelum_bulan_ini . "<br>";
//echo "Total Karyawan Hingga Bulan Ini: " . $total_karyawan_hingga_bulan_ini . "<br>";
//echo "Perubahan Persentase: " . round($perubahan_persentase, 2) . "%<br>";

// Query untuk mendapatkan max_users dari tabel package
$query_package = "SELECT * FROM package WHERE id_package = ?";
$stmt = $conn->prepare($query_package);
$stmt->bind_param("i", $id_package); // Pastikan id_package sudah didefinisikan
$stmt->execute();
$result_package = $stmt->get_result();
$row_package = $result_package->fetch_assoc();
$max_karyawan = $row_package['max_users'];
$nama_paket = $row_package['nama_package'];

// Menghitung persentase penggunaan max_karyawan
if ($max_karyawan > 0) {
  $persentase = ($total_karyawan_hingga_bulan_ini / $max_karyawan) * 100;
} else {
  $persentase = 0; // Jika max_karyawan 0, persentase menjadi 0
}

// ---------------------------------------------------
// AMBIL DATA STATUS Karyawan
// Query untuk menghitung jumlah berdasarkan user_status
$query_status_karyawan = "
    SELECT user_status, COUNT(*) as jumlah
    FROM users
    WHERE id_company = ? 
    AND user_status IN ('part_time', 'magang', 'training', 'probation', 'contract', 'permanent', 'lainnya')
    AND status = 'active' 
    GROUP BY user_status
";

$stmt = $conn->prepare($query_status_karyawan);
$stmt->bind_param("i", $id_company); // Pastikan id_company sudah terdefisikan
$stmt->execute();
$result_status_karyawan = $stmt->get_result();

// Menyimpan data hasil query dalam array untuk digunakan di JS
$data_status_karyawan = [
  'part_time' => 0,
  'magang' => 0,
  'training' => 0,
  'probation' => 0,
  'contract' => 0,
  'permanent' => 0,
  'lainnya' => 0,
];
$data_status_karyawan['part_time'] = 0;
$data_status_karyawan['magang'] = 0;
$data_status_karyawan['training'] = 0;
$data_status_karyawan['probation'] = 0;
$data_status_karyawan['contract'] = 0;
$data_status_karyawan['permanent'] = 0;
$data_status_karyawan['lainnya'] = 0;
// Proses hasil query dan simpan ke dalam array
while ($row_status_karyawan = $result_status_karyawan->fetch_assoc()) {
  switch ($row_status_karyawan['user_status']) {
    case 'part_time':
      $data_status_karyawan['part_time'] = $row_status_karyawan['jumlah'];
      break;
    case 'magang':
      $data_status_karyawan['magang'] = $row_status_karyawan['jumlah'];
      break;
    case 'training':
      $data_status_karyawan['training'] = $row_status_karyawan['jumlah'];
      break;
    case 'probation':
      $data_status_karyawan['probation'] = $row_status_karyawan['jumlah'];
      break;
    case 'contract':
      $data_status_karyawan['contract'] = $row_status_karyawan['jumlah'];
      break;
    case 'permanent':
      $data_status_karyawan['permanent'] = $row_status_karyawan['jumlah'];
      break;
    case 'lainnya':
      $data_status_karyawan['lainnya'] = $row_status_karyawan['jumlah'];
      break;
  }
}

// Kirim data ke JavaScript
//---------------------------------------
// ambil data lama bekerja Karyawan

// Query untuk menghitung jumlah karyawan dalam rentang waktu tertentu
$query_lama_bekerja = "
    SELECT 
        COUNT(*) AS jumlah,
        CASE
            WHEN TIMESTAMPDIFF(MONTH, tanggal_masuk, NOW()) <= 6 THEN '0-6 bulan'
            WHEN TIMESTAMPDIFF(MONTH, tanggal_masuk, NOW()) BETWEEN 7 AND 12 THEN '7-12 bulan'
            WHEN TIMESTAMPDIFF(MONTH, tanggal_masuk, NOW()) BETWEEN 13 AND 36 THEN '1-3 tahun'
            WHEN TIMESTAMPDIFF(MONTH, tanggal_masuk, NOW()) BETWEEN 37 AND 60 THEN '3-5 tahun'
            WHEN TIMESTAMPDIFF(MONTH, tanggal_masuk, NOW()) BETWEEN 61 AND 120 THEN '5-10 tahun'
            WHEN TIMESTAMPDIFF(MONTH, tanggal_masuk, NOW()) > 120 THEN '> 10 tahun'
        END AS kategori
    FROM users WHERE id_company = ? 
    AND status = 'active' 
    GROUP BY kategori
";

// Eksekusi query dan ambil hasilnya
$stmt = $conn->prepare($query_lama_bekerja);
$stmt->bind_param("i", $id_company); // Pastikan id_company sudah didefinisikan
$stmt->execute();
$result_lama_bekerja = $stmt->get_result();

// Inisialisasi array untuk menyimpan hasilnya
$data_lama_bekerja = [
  '0_6_bulan' => 0,
  '7_12_bulan' => 0,
  '1_3_tahun' => 0,
  '3_5_tahun' => 0,
  '5_10_tahun' => 0,
  '>10_tahun' => 0,
];

// Proses hasil query dan simpan ke dalam array
if ($result_lama_bekerja->num_rows > 0) {
  while ($row = $result_lama_bekerja->fetch_assoc()) {
    // Menyimpan jumlah karyawan berdasarkan kategori
    switch ($row['kategori']) {
      case '0-6 bulan':
        $data_lama_bekerja['0_6_bulan'] = $row['jumlah'];
        break;
      case '7-12 bulan':
        $data_lama_bekerja['7_12_bulan'] = $row['jumlah'];
        break;
      case '1-3 tahun':
        $data_lama_bekerja['1_3_tahun'] = $row['jumlah'];
        break;
      case '3-5 tahun':
        $data_lama_bekerja['3_5_tahun'] = $row['jumlah'];
        break;
      case '5-10 tahun':
        $data_lama_bekerja['5_10_tahun'] = $row['jumlah'];
        break;
      case '> 10 tahun':
        $data_lama_bekerja['>10_tahun'] = $row['jumlah'];
        break;
    }
  }
}

//---------------------------------------
// Ambil Data Jenis Kelamin
// Query untuk menghitung jumlah berdasarkan user_status
$query_jk_karyawan = "
    SELECT jenis_kelamin, COUNT(*) as jumlah
    FROM users
    WHERE id_company = ? 
    AND status = 'active' 
    GROUP BY jenis_kelamin
";

$stmt = $conn->prepare($query_jk_karyawan);
$stmt->bind_param("i", $id_company); // Pastikan id_company sudah terdefisikan
$stmt->execute();
$result_jk_karyawan = $stmt->get_result();

// Menyimpan data hasil query dalam array untuk digunakan di JS
$data_jk_karyawan = [
  'laki_laki' => 0,
  'perempuan' => 0,
  'lainnya' => 0,
];

// Menarik data dari query
while ($row_jk_karyawan = $result_jk_karyawan->fetch_assoc()) {
  switch ($row_jk_karyawan['jenis_kelamin']) {
    case 'Laki-laki':
      $data_jk_karyawan['laki_laki'] = $row_jk_karyawan['jumlah'];
      break;
    case 'Perempuan':
      $data_jk_karyawan['perempuan'] = $row_jk_karyawan['jumlah'];
      break;
    case '':
      // Jika data jenis kelamin kosong, kita abaikan
      break;
    default:
      // Jika ada jenis kelamin lain yang tidak terduga, simpan ke kategori 'lainnya'
      $data_jk_karyawan['lainnya'] += $row_jk_karyawan['jumlah'];
      break;
  }
}

// Hitung 'lainnya' setelah mendapatkan jumlah laki-laki dan perempuan
$data_jk_karyawan['lainnya'] = $total_karyawan_hingga_bulan_ini - ($data_jk_karyawan['laki_laki'] + $data_jk_karyawan['perempuan']);

// Jika hasilnya negatif (misalnya karena data yang tidak tepat), pastikan 'lainnya' tidak menjadi negatif
if ($data_jk_karyawan['lainnya'] < 0) {
  $data_jk_karyawan['lainnya'] = 0;
}

//---------------------------------------
// Cek data absensi
$sql_cek_absen = "SELECT *
        FROM absensi
        WHERE user_id = ?
        AND tanggal = '$datenow'
        LIMIT 1";

$stmt_cek_absen = $conn->prepare($sql_cek_absen);
$stmt_cek_absen->bind_param("i", $id_user); // i = integer
$stmt_cek_absen->execute();
$result_cek_absen = $stmt_cek_absen->get_result();

$status_absen = '';
if ($result_cek_absen->num_rows == 0) {
  $status_absen = 0;
} else {
  $data_cek_absen = $result_cek_absen->fetch_assoc();

  if (empty($data_cek_absen['jam_masuk'])) {
    $status_absen = 0; // belum masuk
  } else {
    if (empty($data_cek_absen['jam_mulai_istirahat']) && !empty($data_cek_absen['jam_pulang'])) {
      $status_absen = 4; //selesai istirahat
    }
    if (empty($data_cek_absen['jam_mulai_istirahat'])) {
      $status_absen = 1; // mau istirahat
    } else {
      if (empty($data_cek_absen['jam_selesai_istirahat'])) {
        $status_absen = 2; //sedang istirahat
      } else {
        if (empty($data_cek_absen['jam_pulang'])) {
          $status_absen = 3; //selesai istirahat
        } else {
          $status_absen = 4; // sudah pulang
        }
      }
    }
  }
}

$stmt_cek_absen->close();
$hideAll = ($status_absen === 4);
$isStaff = isset($sesi_user['role']) && $sesi_user['role'] === 'staff';

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

        <h4 class="text-white mb-0"><?php echo $waktu_salam; ?>, <?= $sesi_user['name'] ?></h4>
        <?php if ($sesi_user['role'] !== 'staff'): ?>
          <div class="row btn-white btn-round m-2 p-2">
            <div class="col-12">
              <?php if ($status_absen === 0): ?>
                <p class="text-center mb-0">Jangan lupa absen hari ini!</p>
              <?php endif; ?>
              <div class="h5 text-center btn-white mt-2">
                Hari ini <span id="datetime"></span>
              </div>
            </div>

            <!-- FORM -->
            <div class="col-12 d-flex flex-column align-items-center">
              <?php if (!$hideAll && !$isStaff): ?>
                <!-- CAMERA -->
                <div class="camera-wrapper my-3 d-flex justify-content-center position-relative"
                  style="width:240px;height:300px;">

                  <video id="video" autoplay playsinline style="width:240px;height:300px; object-fit:cover; border-radius:8px; position:absolute; top:0;left:0;"></video>

                  <canvas id="canvas" width="240" height="300" style="display:none; border-radius:8px; position:absolute; top:0;left:0;"></canvas>

                </div>

              <?php endif; ?>

              <?php if (!$hideAll): ?>

                <form method="POST" action="absen">

                  <input type="hidden" name="foto" id="foto">
                  <input type="hidden" name="latitude" id="latitude">
                  <input type="hidden" name="longitude" id="longitude">

                  <?php if ($status_absen !== 5): ?>
                    <!-- TOMBOL FOTO -->
                    <button type="button"
                      class="btn btn-secondary mb-2"
                      id="btnFoto"
                      onclick="takePhoto()">
                      📸 Ambil Foto
                    </button>
                  <?php endif; ?>

                  <!-- TOMBOL AKSI -->
                  <div id="actionButtons">

                    <?php if ($status_absen === 0): ?>
                      <button type="submit" name="action" value="absen_masuk"
                        class="btn btn-success mb-2 d-none">
                        Absen Masuk
                      </button>

                    <?php elseif ($status_absen === 1): ?>
                      <button type="submit" name="action" value="mulai_istirahat"
                        class="btn btn-warning mb-2 d-none">
                        Mulai Istirahat
                      </button>

                      <button type="submit" name="action" value="absen_pulang"
                        class="btn btn-danger mb-2 d-none">
                        Absen Pulang
                      </button>

                    <?php elseif ($status_absen === 2): ?>
                      <button type="submit" name="action" value="selesai_istirahat"
                        class="btn btn-primary mb-2 d-none">
                        Selesai Istirahat
                      </button>

                      <button type="submit" name="action" value="absen_pulang"
                        class="btn btn-danger mb-2 d-none">
                        Absen Pulang
                      </button>

                    <?php elseif ($status_absen === 3): ?>
                      <button type="submit" name="action" value="absen_pulang"
                        class="btn btn-danger mb-2 d-none">
                        Absen Pulang
                      </button>
                    <?php endif; ?>

                  </div>
                </form>

              <?php else: ?>
                <?php
                $tanggal = date('Y-m-d');

                $stmt = $conn->prepare("
                SELECT *
                FROM absensi
                WHERE user_id = ?
                  AND tanggal = ?
                LIMIT 1
            ");
                $stmt->bind_param("is", $_SESSION['user_id'], $tanggal);
                $stmt->execute();
                $result = $stmt->get_result();
                $absen  = $result->fetch_assoc();
                $stmt->close();
                ?>

                <?php if ($absen): ?>


                  <p class="btn-white mb-3">📋 Absensi Hari Ini</p>

                  <table class="table table-bordered">
                    <tr class="btn-white">
                      <th>Tanggal</th>
                      <td><?= htmlspecialchars($absen['tanggal']) ?></td>
                    </tr>

                    <tr class="btn-white">
                      <th>Jam Masuk</th>
                      <td>
                        <?= $absen['jam_masuk'] ?? '-' ?>
                        <?php if (!empty($absen['latitude_masuk'])): ?>
                          <br>
                          <a target="_blank"
                            class="btn btn-sm btn-outline-primary mt-1"
                            href="https://www.google.com/maps?q=<?= $absen['latitude_masuk'] ?>,<?= $absen['longitude_masuk'] ?>">
                            📍 Lihat Lokasi<br>Masuk
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>

                    <tr class="btn-white">
                      <th>Mulai Istirahat</th>
                      <td><?= $absen['jam_mulai_istirahat'] ?? '-' ?></td>
                    </tr>

                    <tr class="btn-white">
                      <th>Selesai Istirahat</th>
                      <td><?= $absen['jam_selesai_istirahat'] ?? '-' ?></td>
                    </tr>

                    <tr class="btn-white">
                      <th>Jam Pulang</th>
                      <td>
                        <?= $absen['jam_pulang'] ?? '-' ?>
                        <?php if (!empty($absen['latitude_pulang'])): ?>
                          <br>
                          <a target="_blank"
                            class="btn btn-sm btn-outline-danger mt-1"
                            href="https://www.google.com/maps?q=<?= $absen['latitude_pulang'] ?>,<?= $absen['longitude_pulang'] ?>">
                            📍 Lihat Lokasi<br>Pulang
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>

                    <tr class="btn-white">
                      <th>Status</th>
                      <td>
                        <?= $absen['status']; ?>
                      </td>
                    </tr>
                  </table>

                  <div class="row m-3">
                    <?php if (!empty($absen['foto_masuk'])): ?>
                      <div class="col-md-6 text-center">
                        <h6>📸 Foto Masuk</h6>
                        <img src="uploads/absensi/<?= $absen['foto_masuk'] ?>"
                          class="img-fluid rounded"
                          style="max-height:250px; width:auto;">
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($absen['foto_pulang'])): ?>
                      <div class="col-md-6 text-center">
                        <h6>📸 Foto Pulang</h6>
                        <img src="uploads/absensi/<?= $absen['foto_pulang'] ?>"
                          class="img-fluid rounded"
                          style="max-height:250px; width:auto;">
                      </div>
                    <?php endif; ?>

                  </div>
                <?php else: ?>
                  <div class="alert alert-info mt-3">
                    Belum ada data absensi hari ini.
                  </div>
                <?php endif; ?>

              <?php endif; ?>

            </div>

          </div>

        <?php else: ?>

          <!--Start Dashboard Content-->
          <div class="card mt-3">
            <div class="card-content">
              <div class="row row-group m-0">
                <div class="col-12 col-lg-12 col-xl-12 border-light">
                  <div class="card-body">
                    <p class="h5 text-center">Hari ini <span id="datetime"></span></p>
                    <?php if ($status_absen === 0): ?>
                      <div id="messages">
                        <div class="alert alert-warning mt-3">
                          <div class="h5 text-center p-1">
                            <strong>Anda belum absen!</strong>
                          </div>
                        </div>
                      </div>
                      <div class="text-center">
                        <a href="absensi" class="btn btn-info border-dark"><i class="zmdi zmdi-sign-in"></i> Absen</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-content">
              <div class="row row-group m-0">
                <div class="col-12 col-lg-12 col-xl-12 border-light">
                  <div class="card-body">
                    <h5 class="text-white mb-0"><?= $total_karyawan_hingga_bulan_ini ?> <span class="float-right"><i class="zmdi zmdi-face"></i></span></h5>
                    <div class="progress my-3" style="height:5px;">
                      <div class="progress-bar" style="width:<?= $persentase ?>%"></div>
                    </div>
                    <p class="mb-0 text-white small-font">Jumlah Karyawan <span class="float-right">
                        <?php if ($keterangan === 'Naik'): ?>
                          Naik <?= round($perubahan_persentase, 2) ?>% <i class="zmdi zmdi-long-arrow-up"></i>
                        <?php elseif ($keterangan === 'Turun'): ?>
                          Turun <?= round($perubahan_persentase, 2) ?>% <i class="zmdi zmdi-long-arrow-down"></i>
                        <?php else: ?>
                          Tidak ada perubahan.
                        <?php endif; ?>
                      </span></p>
                    <!--<p class="mb-0 text-white small-font">Maksimal <?= $max_karyawan ?> user (Paket <?= $nama_paket ?>)</p>-->
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">

            <div class="col-12 col-lg-4 col-xl-4 col-md-6">
              <div class="card">
                <div class="card-header">Status Karyawan
                </div>
                <div class="card-body">
                  <div class="chart-container-2">
                    <canvas id="chartStatusKaryawan"></canvas>
                  </div>
                </div>
                <div class="table-responsive table-scroll">
                  <table class="table align-items-center">
                    <thead>
                      <tr>
                        <th class="mb-0">
                          <h6>Total</h6>
                        </th>
                        <th> </th>
                        <th class="mb-0">
                          <h6><?= $total_karyawan_hingga_bulan_ini ?></h6>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 75, 132, 1);"></i> Part Time</td>
                        <td class="small-font"><?= $data_status_karyawan['part_time'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['part_time'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(54, 162, 235, 1);"></i> Magang</td>
                        <td class="small-font"><?= $data_status_karyawan['magang'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['magang'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 206, 86, 1);"></i> Training</td>
                        <td class="small-font"><?= $data_status_karyawan['training'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['training'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(75, 192, 192, 1);"></i> Probation</td>
                        <td class="small-font"><?= $data_status_karyawan['probation'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['probation'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(153, 102, 255, 1);"></i> Kontrak</td>
                        <td class="small-font"><?= $data_status_karyawan['contract'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['contract'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 120, 64, 1);"></i> Tetap</td>
                        <td class="small-font"><?= $data_status_karyawan['permanent'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['permanent'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 99, 255, 1);"></i> Lainnya</td>
                        <td class="small-font"><?= $data_status_karyawan['lainnya'] ?></td>
                        <td class="small-font"><?= round($data_status_karyawan['lainnya'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4 col-xl-4 col-md-6">
              <div class="card">
                <div class="card-header">Lama Bekerja
                </div>
                <div class="card-body">
                  <div class="chart-container-2">
                    <canvas id="chartLamaBekerja"></canvas>
                  </div>
                </div>
                <div class="table-responsive table-scroll">
                  <table class="table align-items-center">
                    <thead>
                      <tr>
                        <th class="mb-0">
                          <h6>Total</h6>
                        </th>
                        <th> </th>
                        <th class="mb-0">
                          <h6><?= $total_karyawan_hingga_bulan_ini ?></h6>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 99, 132, 1);"></i> 0-6 bulan</td>
                        <td class="small-font"><?= $data_lama_bekerja['0_6_bulan'] ?></td>
                        <td class="small-font"><?= round($data_lama_bekerja['0_6_bulan'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(54, 162, 235, 1);"></i> 7-12 bulan</td>
                        <td class="small-font"><?= $data_lama_bekerja['7_12_bulan'] ?></td>
                        <td class="small-font"><?= round($data_lama_bekerja['7_12_bulan'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 206, 86, 1);"></i> 1-3 tahun</td>
                        <td class="small-font"><?= $data_lama_bekerja['1_3_tahun'] ?></td>
                        <td class="small-font"><?= round($data_lama_bekerja['1_3_tahun'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(75, 192, 192, 1);"></i> 3-5 tahun</td>
                        <td class="small-font"><?= $data_lama_bekerja['3_5_tahun'] ?></td>
                        <td class="small-font"><?= round($data_lama_bekerja['3_5_tahun'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(153, 102, 255, 1);"></i> 5-10 tahun</td>
                        <td class="small-font"><?= $data_lama_bekerja['5_10_tahun'] ?></td>
                        <td class="small-font"><?= round($data_lama_bekerja['5_10_tahun'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 159, 64, 1);"></i> >10 tahun</td>
                        <td class="small-font"><?= $data_lama_bekerja['>10_tahun'] ?></td>
                        <td class="small-font"><?= round($data_lama_bekerja['>10_tahun'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4 col-xl-4 col-md-6">
              <div class="card">
                <div class="card-header">Jenis Kelamin
                </div>
                <div class="card-body">
                  <div class="chart-container-2">
                    <canvas id="chartJenisKelamin"></canvas>
                  </div>
                </div>
                <div class="table-responsive table-scroll">
                  <table class="table align-items-center">
                    <thead>
                      <tr>
                        <th class="mb-0">
                          <h6>Total</h6>
                        </th>
                        <th> </th>
                        <th class="mb-0">
                          <h6><?= $total_karyawan_hingga_bulan_ini ?></h6>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(50, 99, 255, 1);"></i> Laki-laki</td>
                        <td class="small-font"><?= $data_jk_karyawan['laki_laki'] ?></td>
                        <td class="small-font"><?= round($data_jk_karyawan['laki_laki'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(255, 145, 175, 1);"></i> Perempuan</td>
                        <td class="small-font"><?= $data_jk_karyawan['perempuan'] ?></td>
                        <td class="small-font"><?= round($data_jk_karyawan['perempuan'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                      <tr>
                        <td class="small-font"><i class="fa fa-circle" style="color: rgba(200, 200, 255, 1);"></i> Lainnya</td>
                        <td class="small-font"><?= $data_jk_karyawan['lainnya'] ?></td>
                        <td class="small-font"><?= round($data_jk_karyawan['lainnya'] / $total_karyawan_hingga_bulan_ini * 100, 2) ?>%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!--
        <div class="row">
          <div class="col-12 col-lg-12">
            <div class="card">
              <div class="card-header">Recent Order Tables
                <div class="card-action">
                  <div class="dropdown">
                    <a href="javascript:void();" class="dropdown-toggle dropdown-toggle-nocaret" data-toggle="dropdown">
                      <i class="icon-options"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item" href="javascript:void();">Action</a>
                      <a class="dropdown-item" href="javascript:void();">Another action</a>
                      <a class="dropdown-item" href="javascript:void();">Something else here</a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="javascript:void();">Separated link</a>
                    </div>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table align-items-center table-flush table-borderless">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Photo</th>
                      <th>Product ID</th>
                      <th>Amount</th>
                      <th>Date</th>
                      <th>Shipping</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Iphone 5</td>
                      <td><img src="https://via.placeholder.com/110x110" class="product-img" alt="product img"></td>
                      <td>#9405822</td>
                      <td>$ 1250.00</td>
                      <td>03 Aug 2017</td>
                      <td>
                        <div class="progress shadow" style="height: 3px;">
                          <div class="progress-bar" role="progressbar" style="width: 90%"></div>
                        </div>
                      </td>
                    </tr>

                    <tr>
                      <td>Earphone GL</td>
                      <td><img src="https://via.placeholder.com/110x110" class="product-img" alt="product img"></td>
                      <td>#9405820</td>
                      <td>$ 1500.00</td>
                      <td>03 Aug 2017</td>
                      <td>
                        <div class="progress shadow" style="height: 3px;">
                          <div class="progress-bar" role="progressbar" style="width: 60%"></div>
                        </div>
                      </td>
                    </tr>

                    <tr>
                      <td>HD Hand Camera</td>
                      <td><img src="https://via.placeholder.com/110x110" class="product-img" alt="product img"></td>
                      <td>#9405830</td>
                      <td>$ 1400.00</td>
                      <td>03 Aug 2017</td>
                      <td>
                        <div class="progress shadow" style="height: 3px;">
                          <div class="progress-bar" role="progressbar" style="width: 70%"></div>
                        </div>
                      </td>
                    </tr>

                    <tr>
                      <td>Clasic Shoes</td>
                      <td><img src="https://via.placeholder.com/110x110" class="product-img" alt="product img"></td>
                      <td>#9405825</td>
                      <td>$ 1200.00</td>
                      <td>03 Aug 2017</td>
                      <td>
                        <div class="progress shadow" style="height: 3px;">
                          <div class="progress-bar" role="progressbar" style="width: 100%"></div>
                        </div>
                      </td>
                    </tr>

                    <tr>
                      <td>Hand Watch</td>
                      <td><img src="https://via.placeholder.com/110x110" class="product-img" alt="product img"></td>
                      <td>#9405840</td>
                      <td>$ 1800.00</td>
                      <td>03 Aug 2017</td>
                      <td>
                        <div class="progress shadow" style="height: 3px;">
                          <div class="progress-bar" role="progressbar" style="width: 40%"></div>
                        </div>
                      </td>
                    </tr>

                    <tr>
                      <td>Clasic Shoes</td>
                      <td><img src="https://via.placeholder.com/110x110" class="product-img" alt="product img"></td>
                      <td>#9405825</td>
                      <td>$ 1200.00</td>
                      <td>03 Aug 2017</td>
                      <td>
                        <div class="progress shadow" style="height: 3px;">
                          <div class="progress-bar" role="progressbar" style="width: 100%"></div>
                        </div>
                      </td>
                    </tr>

                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        End Dashboard Content-->

        <?php endif; ?>

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

  <script>
    var statusData = <?php echo json_encode($data_status_karyawan); ?>;

    var ctx = document.getElementById("chartStatusKaryawan").getContext('2d');
    var myChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ["Part-time", "Magang", "Training", "Probation", "Contract", "Permanent", "Lainnya"], // Labels untuk chart
        datasets: [{
          backgroundColor: [
            "rgba(255, 75, 132, 1)", // Part-time
            "rgba(54, 162, 235, 1)", // Magang
            "rgba(255, 206, 86, 1)", // Training
            "rgba(75, 192, 192, 1)", // Probation
            "rgba(153, 102, 255, 1)", // Contract
            "rgba(255, 120, 64, 1)", // Permanent
            "rgba(255, 99, 255, 1)" // Lainnya
          ],
          data: [
            statusData.part_time, // Jumlah Part-time
            statusData.magang, // Jumlah Magang
            statusData.training, // Jumlah Training
            statusData.probation, // Jumlah Probation
            statusData.contract, // Jumlah Contract
            statusData.permanent, // Jumlah Permanent
            statusData.lainnya // Jumlah Lainnya
          ],
          borderWidth: [0, 0, 0, 0, 0, 0, 0] // Border width untuk masing-masing bagian chart
        }]
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        legend: {
          position: "bottom",
          display: false,
          labels: {
            fontColor: '#ddd',
            boxWidth: 15
          }
        },
        tooltips: {
          displayColors: false
        }
      }
    });
  </script>
  <script>
    var lamaBekerjaData = <?php echo json_encode($data_lama_bekerja); ?>;
    var ctx = document.getElementById("chartLamaBekerja").getContext('2d');
    var myChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['', '', '', '', '', ''], // Rentang waktu
        datasets: [{
          data: [
            lamaBekerjaData['0_6_bulan'], // Jumlah karyawan yang bekerja 0-6 bulan
            lamaBekerjaData['7_12_bulan'], // Jumlah karyawan yang bekerja 7-12 bulan
            lamaBekerjaData['1_3_tahun'], // Jumlah karyawan yang bekerja 1-3 tahun
            lamaBekerjaData['3_5_tahun'], // Jumlah karyawan yang bekerja 3-5 tahun
            lamaBekerjaData['5_10_tahun'], // Jumlah karyawan yang bekerja 5-10 tahun
            lamaBekerjaData['>10_tahun'] // Jumlah karyawan yang bekerja > 10 tahun
          ],
          backgroundColor: [
            "rgba(255, 99, 132, 1)", // 0-6 bulan
            "rgba(54, 162, 235, 1)", // 7-12 bulan
            "rgba(255, 206, 86, 1)", // 1-3 tahun
            "rgba(75, 192, 192, 1)", // 3-5 tahun
            "rgba(153, 102, 255, 1)", // 5-10 tahun
            "rgba(255, 159, 64, 1)" // > 10 tahun
          ],
          borderWidth: 1
        }]
      },
      options: {
        maintainAspectRatio: false,
        hover: {
          mode: null // Menonaktifkan hover effect
        },
        legend: {
          position: 'bottom',
          display: false,
          labels: {
            fontColor: '#ddd',
            boxWidth: 50
          }
        },
        tooltips: {
          enabled: false
        },
        scales: {
          xAxes: [{
            barPercentage: 0.9,
            ticks: {
              beginAtZero: true,
              fontColor: '#ddd'
            },
            gridLines: {
              display: true,
              color: "rgba(221, 221, 221, 0.08)"
            },
          }],
          yAxes: [{
            ticks: {
              beginAtZero: true,
              fontColor: '#ddd',
              // Menyesuaikan skala Y secara otomatis
              suggestedMax: Math.max(
                lamaBekerjaData['0_6_bulan'],
                lamaBekerjaData['7_12_bulan'],
                lamaBekerjaData['1_3_tahun'],
                lamaBekerjaData['3_5_tahun'],
                lamaBekerjaData['5_10_tahun'],
                lamaBekerjaData['>10_tahun']
              ) * 1.1 // Menambahkan sedikit buffer untuk tampilan yang lebih baik
            },
            gridLines: {
              display: true,
              color: "rgba(221, 221, 221, 0.08)"
            },
          }]
        }
      }
    });
  </script>

  <script>
    var statusData = <?php echo json_encode($data_jk_karyawan); ?>;

    var ctx = document.getElementById("chartJenisKelamin").getContext('2d');
    var myChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ["Laki-laki", "Perempuan", "Lainnya"], // Labels untuk chart
        datasets: [{
          backgroundColor: [
            "rgba(50, 99, 255, 1)", // Warna untuk Laki-laki
            "rgba(255, 145, 175, 1)", // Warna untuk Perempuan
            "rgba(200, 200, 255, 1)" // Warna untuk Lainnya
          ],
          data: [
            statusData['laki_laki'], // Jumlah Laki-laki
            statusData['perempuan'], // Jumlah Perempuan
            statusData['lainnya'] // Jumlah Lainnya
          ],
          borderWidth: [0, 0, 0] // Border width untuk masing-masing bagian chart
        }]
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        legend: {
          position: "bottom",
          display: false,
          labels: {
            fontColor: '#ddd',
            boxWidth: 15
          }
        },
        tooltips: {
          displayColors: false
        }
      }
    });
  </script>
  <script>
    function updateDateTime() {
      const now = new Date();

      const tanggal = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });

      const jam = String(now.getHours()).padStart(2, '0');
      const menit = String(now.getMinutes()).padStart(2, '0');
      const detik = String(now.getSeconds()).padStart(2, '0');

      document.getElementById('datetime').innerHTML =
        `${tanggal} ${jam}:${menit}:${detik}`;
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();
  </script>

  <?php if (!$hideAll && !$isStaff): ?>
    <script>
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const fotoInput = document.getElementById('foto');
      const btnAbsen = document.getElementById('btnAbsen');

      navigator.mediaDevices.getUserMedia({
          video: true
        })
        .then(stream => {
          video.srcObject = stream;
        })
        .catch(() => {
          alert("Kamera tidak dapat diakses");
        });
    </script>
  <?php endif; ?>

  <script>
    navigator.geolocation.getCurrentPosition(
      pos => {
        document.getElementById('latitude').value = pos.coords.latitude;
        document.getElementById('longitude').value = pos.coords.longitude;
      },
      () => alert("Lokasi wajib diaktifkan")
    );
  </script>
  <script>
    function takePhoto() {
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const foto = document.getElementById('foto');

      const CW = 240;
      const CH = 300;

      if (!video.videoWidth || !video.videoHeight) {
        alert('Kamera belum siap');
        return;
      }

      canvas.width = CW;
      canvas.height = CH;

      const ctx = canvas.getContext('2d');

      const vw = video.videoWidth;
      const vh = video.videoHeight;

      const videoRatio = vw / vh;
      const canvasRatio = CW / CH;

      let sx, sy, sw, sh;

      if (videoRatio > canvasRatio) {
        // video lebih lebar → crop kiri kanan
        sh = vh;
        sw = vh * canvasRatio;
        sx = (vw - sw) / 2;
        sy = 0;
      } else {
        // video lebih tinggi → crop atas bawah
        sw = vw;
        sh = vw / canvasRatio;
        sx = 0;
        sy = (vh - sh) / 2;
      }

      ctx.drawImage(video, sx, sy, sw, sh, 0, 0, CW, CH);

      foto.value = canvas.toDataURL('image/jpeg', 0.9);

      // tampilkan hasil
      video.style.visibility = 'hidden';
      canvas.style.display = 'block';

      document.getElementById('btnFoto')?.classList.add('d-none');
      document.querySelectorAll('#actionButtons .d-none')
        .forEach(btn => btn.classList.remove('d-none'));
    }
  </script>

</body>

</html>