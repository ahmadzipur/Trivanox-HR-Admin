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

$query = "
SELECT 
    r.id,
    u.name AS nama_karyawan,
    r.jenis,
    r.kategori,
    r.tanggal_mulai,
    r.tanggal_selesai,
    r.jumlah_hari,
    r.is_half_day,
    r.alasan,
    r.file_pendukung,
    r.status,
    r.created_at
FROM request_izin_cuti r
JOIN users u ON u.id = r.id_user WHERE u.id_company = '$id_company'
ORDER BY r.created_at DESC
";

$result = $conn->query($query);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Trivanox - HR Management" />
    <meta name="author" content="Ahmad Zaelani" />
    <title>Data Izin, Cuti & Sakit - HR Management</title>
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

                <?php if ($status): ?>
                    <div id="messages">
                        <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mt-3">
                            <div class="text-center p-1">
                                <strong><?= htmlspecialchars($message) ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold">Data Izin, Cuti & Sakit Karyawan</h5>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Jenis</th>
                                        <th>Kategori</th>
                                        <th>Tanggal</th>
                                        <th>Hari</th>
                                        <th>Alasan</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Diajukan</th>
                                        <th>Approval Workflow</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php $no = 1;
                                        while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($row['nama_karyawan']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($row['jenis'])) ?></td>
                                                <td><?= htmlspecialchars($row['kategori']) ?></td>
                                                <td>
                                                    <?= $row['tanggal_mulai'] ?>
                                                    <?php if ($row['tanggal_mulai'] != $row['tanggal_selesai']): ?>
                                                        <br>s/d <?= $row['tanggal_selesai'] ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $row['jumlah_hari'] ?>
                                                    <?php if ($row['is_half_day']): ?>
                                                        <small>(½ Hari)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($row['alasan']) ?></td>
                                                <td>
                                                    <?php if (!empty($row['file_pendukung'])): ?>
                                                        <a href="<?= $row['file_pendukung'] ?>" target="_blank" class="btn btn-sm btn-light">
                                                            Lihat
                                                        </a>
                                                    <?php else: ?>
                                                        <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge = [
                                                        'pending'  => 'warning',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    ?>
                                                    <span class="btn btn-sm btn-<?= $badge[$row['status']] ?? 'secondary' ?>" disable>
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <a href="#" class="btn btn-sm btn-success"><i class="zmdi zmdi-check"></i> Setujui</a>
                                                        <a href="#" class="btn btn-sm btn-danger"><i class="zmdi zmdi-close-circle"></i> Tolak</a>
                                                        <a href="#" class="btn btn-sm btn-warning"><i class="zmdi zmdi-block"></i> Batalkan</a>
                                                    <?php else: ?>
                                                        <?php
                                                        $badge = [
                                                            'pending'  => 'warning',
                                                            'approved' => 'success',
                                                            'rejected' => 'danger'
                                                        ];
                                                        ?>
                                                        <span class="btn btn-sm btn-<?= $badge[$row['status']] ?? 'secondary' ?>" disable>
                                                            <?= ucfirst($row['status']) ?>
                                                        </span>
                                                        <a href="#" class="btn btn-sm btn-light"><i class="zmdi zmdi-edit"></i> Edit</a>

                                                    <?php endif; ?>


                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Belum ada pengajuan
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
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