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

$id_user = $_SESSION["user_id"] ?? '';
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

    $id_user = $_SESSION["user_id"];
    $stmt->close();
} else if (!isset($_SESSION["user_id"]) && !isset($_COOKIE["remember"])) {
    $_SESSION["status"] = "error";
    $_SESSION["message"] = "Silakan login terlebih dahulu.";
    header("Location: login");
    exit;
}

$stmt = $conn->prepare("SELECT  u.id, u.id_company, u.name, u.email,
      u.password, u.role, u.jabatan, u.foto_profile, u.remember_token,
      u.created_at AS user_created_at, c.nama_company, c.code_company,
      c.code_verification, c.alamat_company, c.status_company, c.expired_at, c.created_at AS company_created_at
  FROM users u
  LEFT JOIN company c ON u.id_company = c.id_company
  WHERE u.id = ?
  LIMIT 1
");

$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
/* ===============================
   CEK APAKAH USER DITEMUKAN
================================ */
if (!$user) {
    // opsi 1: paksa logout
    session_destroy();
    $_SESSION["status"] = "error";
    $_SESSION["message"] = "Silakan login terlebih dahulu.";
    header("Location: login");
    exit;

    // atau opsi 2: tampilkan pesan
    // die("User tidak ditemukan");
}

/* ===============================
   SET SESSION JIKA DATA ADA
================================ */
$_SESSION["user"] = [
    "id"              => $user["id"],
    "id_company"      => $user["id_company"],
    "name"            => $user["name"],
    "email"           => $user["email"],
    "role"            => $user["role"],
    "jabatan"         => $user["jabatan"],
    "foto_profile"    => $user["foto_profile"],
    "company_name"    => $user["nama_company"] ?? null,
    "company_code"    => $user["code_company"] ?? null,
    "status_company"  => $user["status_company"] ?? null,
    "expired_at"      => $user["expired_at"] ?? null,
    "company_address" => $user["alamat_company"] ?? null
];

$sesi_user = $_SESSION["user"];
$nama_karyawan = $sesi_user['name'];
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

$nama_bulan = date('m');
$nama_tahun = date('Y');
$jumlahHari = cal_days_in_month(CAL_GREGORIAN, $nama_bulan, $nama_tahun);
$query = mysqli_query($conn, " SELECT *
    FROM absensi WHERE id_user = $id_user
    AND MONTH(tanggal) = $nama_bulan
    AND YEAR(tanggal) = $nama_tahun
");

$dataAbsensi = [];
while ($row = mysqli_fetch_assoc($query)) {
    $dataAbsensi[$row['tanggal']] = $row;
}

// Data Bulan Sebelumnya
// Ambil bulan & tahun sebelumnya
$prevMonth = date('m', strtotime('first day of last month'));
$prevYear  = date('Y', strtotime('first day of last month'));

$nama_bulan_sebelumnya = $prevMonth;
$bulan_sebelumnya = $bulan_indonesia[date('F', strtotime('first day of last month'))];

$nama_tahun_sebelumnya = $prevYear;

// Jumlah hari di bulan sebelumnya
$jumlahHari_bulan_sebelumnya = cal_days_in_month(CAL_GREGORIAN, $nama_bulan_sebelumnya, $nama_tahun_sebelumnya);

// Query absensi bulan sebelumnya
$query_bulan_sebelumnya = mysqli_query($conn, "
    SELECT *
    FROM absensi
    WHERE id_user = $id_user
    AND MONTH(tanggal) = $nama_bulan_sebelumnya
    AND YEAR(tanggal) = $nama_tahun_sebelumnya
");

// Susun data absensi per tanggal
$dataAbsensi_bulan_sebelumnya = [];
while ($row_bulan_sebelumnya = mysqli_fetch_assoc($query_bulan_sebelumnya)) {
    $dataAbsensi_bulan_sebelumnya[$row_bulan_sebelumnya['tanggal']] = $row_bulan_sebelumnya;
}
// Akhir Data Sebelumnya

function hitungLembur($jamMasuk, $jamMulaiIstirahat, $jamSelesaiIstirahat, $jamPulang)
{
    if (
        $jamMasuk == '-' || empty($jamMasuk) ||
        $jamMulaiIstirahat == '-' || empty($jamMulaiIstirahat) ||
        $jamSelesaiIstirahat == '-' || empty($jamSelesaiIstirahat) ||
        $jamPulang == '-' || empty($jamPulang)
    ) {
        return '-';
    }

    $jamMasukTime            = strtotime($jamMasuk);
    $jamMulaiIstirahatTime   = strtotime($jamMulaiIstirahat);
    $jamSelesaiIstirahatTime = strtotime($jamSelesaiIstirahat);
    $jamPulangTime           = strtotime($jamPulang);

    // Jam kerja sebelum dan sesudah istirahat (dalam jam)
    $jamSesiPertama = ($jamMulaiIstirahatTime - $jamMasukTime) / 3600;
    $jamSesiKedua   = ($jamPulangTime - $jamSelesaiIstirahatTime) / 3600;

    $totalJamKerja = $jamSesiPertama + $jamSesiKedua;
    $jamKerjaNormal = 7;

    if ($totalJamKerja <= $jamKerjaNormal) {
        return '0 jam';
    }

    $lembur = $totalJamKerja - $jamKerjaNormal;
    return number_format($lembur, 1) . ' jam';
}

function cekJamMasuk($jamMasuk)
{
    if ($jamMasuk === '-' || empty($jamMasuk)) {
        return '-';
    }

    // Pakai tanggal dummy agar konsisten
    $jamMasukTime       = strtotime("1970-01-01 $jamMasuk");
    $jamMasukNormalTime = strtotime("1970-01-01 09:00:00");

    if ($jamMasukTime > $jamMasukNormalTime) {
        return '<span class="btn btn-lg btn-danger py-0 px-1">' . htmlspecialchars($jamMasuk) . '</span>';
    }

    return $jamMasuk;
}

function cekJamPulang($jamPulang)
{
    if ($jamPulang === '-' || empty($jamPulang)) {
        return '-';
    }

    // Pakai tanggal dummy agar konsisten
    $jamPulangTime       = strtotime("1970-01-01 $jamPulang");
    $jamPulangNormalTime = strtotime("1970-01-01 17:00:00");

    if ($jamPulangTime < $jamPulangNormalTime) {
        return '<span class="btn btn-lg btn-danger py-0 px-1">' . htmlspecialchars($jamPulang) . '</span>';
    }

    return $jamPulang;
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
    <title>Data Absensi Karyawan - HR Management</title>
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
        .dataTables_filter {
            display: flex !important;
            align-items: center !important;
            gap: 8px;
        }

        .dataTables_filter label {
            display: flex !important;
            align-items: center !important;
            gap: 6px;
            margin-bottom: 0 !important;
        }

        .dataTables_filter input {
            margin-left: 0 !important;
        }
    </style>

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

        <?php include 'left-sidebar.php'; ?>
        <?php include 'topbar.php'; ?>

        <div class="clearfix"></div>

        <div class="content-wrapper">
            <div class="container-fluid">

                <!-- Page Heading -->
                <h4 class="h4 mb-2 text-gray-800">Data Absensi Karyawan</h4>
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

                <div class="card shadow mb-4">
                    <!-- <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold">Data Absensi <?= $nama_karyawan ?></h5>
                    </div> -->
                    <div class="card-header mt-2 py-0">
                        <h5 class="m-0 font-weight-bold">Data Absensi <?= $nama_karyawan ?></h5>
                        <h5 class="mt-1 font-weight-bold">Bulan: <?= $bulan ?> <?= $nama_tahun ?></h5>
                    </div>
                    <div class="card-body btn-white">
                        <div class="table-responsive">
                            <table border="1" cellpadding="8" cellspacing="0">
                                <tr>
                                    <th>Tanggal</th>
                                    <?php for ($i = 1; $i <= $jumlahHari; $i++): ?>
                                        <th><?= $i ?></th>
                                    <?php endfor; ?>
                                </tr>

                                <?php
                                $rows = [
                                    'Jam Masuk' => 'jam_masuk',
                                    'Mulai Istirahat' => 'jam_mulai_istirahat',
                                    'Selesai Istirahat' => 'jam_selesai_istirahat',
                                    'Jam Pulang' => 'jam_pulang'
                                ];
                                ?>

                                <?php foreach ($rows as $label => $field): ?>
                                    <tr>
                                        <td><?= $label ?></td>
                                        <?php for ($i = 1; $i <= $jumlahHari; $i++):
                                            $nama_tanggal = "$nama_tahun-$nama_bulan-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                            $value = $dataAbsensi[$nama_tanggal][$field] ?? '-';

                                            // Terapkan cekJamMasuk hanya untuk Jam Masuk
                                            if ($field === 'jam_masuk') {
                                                echo "<td>" . cekJamMasuk($value) . "</td>";
                                            } else if ($field === 'jam_pulang') {
                                                echo "<td>" . cekJamPulang($value) . "</td>";
                                            } else {
                                                echo "<td>" . htmlspecialchars($value) . "</td>";
                                            }
                                        endfor; ?>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Baris Lembur -->
                                <tr>
                                    <td>Lembur</td>
                                    <?php for ($i = 1; $i <= $jumlahHari; $i++):
                                        $nama_tanggal = "$nama_tahun-$nama_bulan-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                        $jamMasuk = $dataAbsensi[$nama_tanggal]['jam_masuk'] ?? '-';
                                        $jamMulaiIstirahat = $dataAbsensi[$nama_tanggal]['jam_mulai_istirahat'] ?? '-';
                                        $jamSelesaiIstirahat = $dataAbsensi[$nama_tanggal]['jam_selesai_istirahat'] ?? '-';
                                        $jamPulang = $dataAbsensi[$nama_tanggal]['jam_pulang'] ?? '-';
                                        echo "<td>" . hitungLembur($jamMasuk, $jamMulaiIstirahat, $jamSelesaiIstirahat, $jamPulang) . "</td>";
                                    endfor; ?>
                                </tr>
                            </table>


                        </div>
                    </div>

                    <div class="card-header mt-2 py-0">
                        <h5 class="m-0 font-weight-bold">Data Absensi <?= $nama_karyawan ?></h5>
                        <h5 class="mt-1 font-weight-bold">Bulan: <?= $bulan_sebelumnya ?> <?= $nama_tahun_sebelumnya ?></h5>
                    </div>
                    <div class="card-body btn-white">
                        <div class="table-responsive">
                            <table border="1" cellpadding="8" cellspacing="0">
                                <tr>
                                    <th>Tanggal</th>
                                    <?php for ($i = 1; $i <= $jumlahHari_bulan_sebelumnya; $i++): ?>
                                        <th><?= $i ?></th>
                                    <?php endfor; ?>
                                </tr>

                                <?php
                                $rows = [
                                    'Jam Masuk' => 'jam_masuk',
                                    'Mulai Istirahat' => 'jam_mulai_istirahat',
                                    'Selesai Istirahat' => 'jam_selesai_istirahat',
                                    'Jam Pulang' => 'jam_pulang'
                                ];
                                ?>

                                <?php foreach ($rows as $label => $field): ?>
                                    <tr>
                                        <td><?= $label ?></td>
                                        <?php for ($i = 1; $i <= $jumlahHari_bulan_sebelumnya; $i++):
                                            $nama_tanggal_bulan_sebelumnya = "$nama_tahun_sebelumnya-$nama_bulan_sebelumnya-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                            $value = $dataAbsensi_bulan_sebelumnya[$nama_tanggal_bulan_sebelumnya][$field] ?? '-';

                                            // Terapkan cekJamMasuk hanya untuk Jam Masuk
                                            if ($field === 'jam_masuk') {
                                                echo "<td>" . cekJamMasuk($value) . "</td>";
                                            } else if ($field === 'jam_pulang') {
                                                echo "<td>" . cekJamPulang($value) . "</td>";
                                            } else {
                                                echo "<td>" . htmlspecialchars($value) . "</td>";
                                            }
                                        endfor; ?>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Baris Lembur -->
                                <tr>
                                    <td>Lembur</td>
                                    <?php for ($i = 1; $i <= $jumlahHari_bulan_sebelumnya; $i++):
                                        $nama_tanggal_bulan_sebelumnya = "$nama_tahun_sebelumnya-$nama_bulan_sebelumnya-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                        $jamMasuk = $dataAbsensi_bulan_sebelumnya[$nama_tanggal_bulan_sebelumnya]['jam_masuk'] ?? '-';
                                        $jamMulaiIstirahat = $dataAbsensi_bulan_sebelumnya[$nama_tanggal_bulan_sebelumnya]['jam_mulai_istirahat'] ?? '-';
                                        $jamSelesaiIstirahat = $dataAbsensi_bulan_sebelumnya[$nama_tanggal_bulan_sebelumnya]['jam_selesai_istirahat'] ?? '-';
                                        $jamPulang = $dataAbsensi_bulan_sebelumnya[$nama_tanggal_bulan_sebelumnya]['jam_pulang'] ?? '-';
                                        echo "<td>" . hitungLembur($jamMasuk, $jamMulaiIstirahat, $jamSelesaiIstirahat, $jamPulang) . "</td>";
                                    endfor; ?>
                                </tr>
                            </table>


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



    <!-- template custom js -->
    <script src="js/main.js"></script>
    <!-- Page level plugins -->
    <script src="assets/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/plugins/datatables/dataTables.bootstrap4.min.js"></script>

    <!-- Page level custom scripts -->
    <!-- <script src="js/demo/datatables-demo.js"></script> -->
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                pageLength: 10
            });
        });
    </script>


</body>

</html>