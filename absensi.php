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

$id_user = $_SESSION["user_id"];
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
$stmt->bind_param("i", $id_user);
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

//---------------------------------------
// Cek data absensi
$sql_cek_absen = "SELECT *
        FROM absensi
        WHERE id_user = ?
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
        if (empty($data_cek_absen['jam_mulai_istirahat']) && !empty($data_cek_absen['jam_pulang'])) {
            $status_absen = 4; //selesai istirahat
        }
    }
}

$stmt_cek_absen->close();
$hideAll = ($status_absen === 4);

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
                        <?php if (!$hideAll): ?>
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
                WHERE id_user = ?
                  AND tanggal = ?
                LIMIT 1
            ");
                            $stmt->bind_param("is", $id_user, $tanggal);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $absen  = $result->fetch_assoc();
                            $stmt->close();
                            ?>

                            <?php if ($absen): ?>

                                <p class="btn-white mb-3">📋 Absensi Hari Ini</p>
                                <table class="table table-bordered table-sm w-auto">
                                    <tr class="btn-white">
                                        <th>Tanggal</th>
                                        <td>: <?= !empty($absen['tanggal']) ? date('d-m-Y', strtotime($absen['tanggal'])) : '-' ?></td>
                                    </tr>

                                    <tr class="btn-white">
                                        <th>Jam Masuk</th>
                                        <td>:
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
                                        <td>: <?= $absen['jam_mulai_istirahat'] ?? '-' ?></td>
                                    </tr>

                                    <tr class="btn-white">
                                        <th>Selesai Istirahat</th>
                                        <td>: <?= $absen['jam_selesai_istirahat'] ?? '-' ?></td>
                                    </tr>

                                    <tr class="btn-white">
                                        <th>Jam Pulang</th>
                                        <td>:
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
                                        <td>:
                                            <?php if ($absen['status'] === 'hadir'): ?>
                                                Hadir
                                            <?php elseif ($absen['status'] === 'izin'): ?>
                                                Izin
                                            <?php elseif ($absen['status'] === 'sakit'): ?>
                                                Sakit
                                            <?php else: ?>
                                                Alpa
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>

                                <div class="row mb-3">
                                    <?php if (!empty($absen['foto_masuk'])): ?>
                                        <div class="col-md-6 text-center mt-3">
                                            <h6 class="btn-white">📸 Foto Masuk</h6>
                                            <img src="uploads/absensi/<?= $absen['foto_masuk'] ?>"
                                                class="img-fluid rounded"
                                                style="max-height:250px; width:auto;">
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($absen['foto_pulang'])): ?>
                                        <div class="col-md-6 text-center mt-3">
                                            <h6 class="btn-white">📸 Foto Pulang</h6>
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

    <?php if (!$hideAll): ?>
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