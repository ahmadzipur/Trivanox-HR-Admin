<?php
session_start();
include "koneksi.php";

// Mengambil tema yang disimpan (jika ada)
// Ambil tema yang disimpan di session
$savedTheme = isset($_SESSION['selectedTheme']) ? $_SESSION['selectedTheme'] : 'bg-theme bg-theme1'; // Default tema jika tidak ada

// Flash message (auto remove setelah tampil)
$status = $_SESSION['status'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['status'], $_SESSION['message']);

// Simpan form lama di session supaya tidak hilang
function old($key)
{
    return $_SESSION['old'][$key] ?? '';
}

// Generate CSRF Token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

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

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Invalid CSRF Token");
    }

    $_SESSION['old'] = $_POST;

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
      u.created_at AS user_created_at,
      c.nama_company,
      c.code_company,
      c.code_verification,
      c.alamat_company,
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
    "name"            => $user["name"],
    "email"           => $user["email"],
    "role"            => $user["role"],
    "jabatan"         => $user["jabatan"],
    "foto_profile"    => $user["foto_profile"],
    "company_name"    => $user["nama_company"],
    "company_code"    => $user["code_company"],
    "verification_code"    => $user["code_verification"],
    "status_company"  => $user["status_company"],
    "expired_at"      => $user["expired_at"],
    "company_address" => $user["alamat_company"]
];

$sesi_user = $_SESSION["user"];

$companyCode = $sesi_user['company_code'];
$verificationCode = $sesi_user['verification_code'];

$query_company = "
SELECT 
  c.*,
  p.name AS province_name,
  r.name AS regency_name,
  d.name AS district_name,
  v.name AS village_name
FROM company c
LEFT JOIN provinces p ON c.province_id = p.id
LEFT JOIN regencies r ON c.regency_id = r.id
LEFT JOIN districts d ON c.district_id = d.id
LEFT JOIN villages v ON c.village_id = v.id
WHERE c.code_company = ?
LIMIT 1
";

$stmt_company = $conn->prepare($query_company);
$stmt_company->bind_param("s", $companyCode);
$stmt_company->execute();
$result_company = $stmt_company->get_result();
$company = $result_company->fetch_assoc();
$stmt_company->close();

$provinces = [];
$result_provinces = $conn->query("SELECT id, name FROM provinces ORDER BY name");
while ($row_provinces = $result_provinces->fetch_assoc()) {
    $provinces[] = $row_provinces;
}

$companyTypes = [
    'BUMN' => 'Badan Usaha Milik Negara',
    'BUMD' => 'Badan Usaha Milik Daerah',
    'BUMDes' => 'Badan Usaha Milik Desa',
    'IKM' => 'Industri Kecil dan Menengah',
    'UMKM' => 'Usaha Mikro, Kecil, dan Menengah',
    'Usaha Perseorangan' => 'Usaha Perseorangan',
    'Lainnya' => 'Lainnya'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Trivanox - HR Management" />
    <meta name="author" content="Ahmad Zaelani" />
    <title>Data Perusahaan - HR Management</title>
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
                <h4 class="h4 mb-2 text-gray-800">Data Perusahaan</h4>

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5><?= $company['nama_company'] ?></h5>

                    </div>
                    <div class="card-body">
                        <?php if ($status): ?>
                            <div id="messages">
                                <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mt-3">
                                    <div class="text-center p-1">
                                        <strong><?= htmlspecialchars($message) ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="company_update.php" enctype="multipart/form-data">
                            <input type="hidden" name="id_company" value="<?= $company['id_company'] ?>">
                            <?php if (!empty($company['company_logo']) && $company['company_logo'] !== null): ?>
                                <div class="avatar">
                                    <img class="align-self-start img-fluid mr-3" src="<?= $company['company_logo'] ?>" alt="logo avatar" style="width: 150px; height: auto;">
                                </div>
                            <?php endif; ?>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Nama Perusahaan</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control input-shadow" name="nama_company" id="nama_company" placeholder="Nama Perusahaan" value="<?= $company['nama_company'] ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Kode Perusahaan</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control input-shadow" placeholder="Kode Perusahaan" value="<?= $companyCode ?>" readonly>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Kode Verifikasi</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control input-shadow" placeholder="Kode Verifikasi" value="<?= $verificationCode ?>" readonly>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Legal Name</label>
                                <div class="col-md-9">
                                    <select class="form-control" name="legal_name" id="legal_name">
                                        <option value="">-- Pilih Badan Hukum --</option>
                                        <option value="PT" <?= ($company['legal_name'] === 'PT') ? 'selected' : '' ?>>
                                            Perseroan Terbatas (PT)
                                        </option>
                                        <option value="CV" <?= ($company['legal_name'] === 'CV') ? 'selected' : '' ?>>
                                            Commanditaire Vennootschap (CV)
                                        </option>
                                        <option value="Yayasan" <?= ($company['legal_name'] === 'Yayasan') ? 'selected' : '' ?>>
                                            Yayasan
                                        </option>
                                        <option value="-" <?= ($company['legal_name'] === '-') ? 'selected' : '' ?>>
                                            Tidak ada
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Company Type</label>
                                <div class="col-md-9">
                                    <select class="form-control" name="company_type" id="company_type">
                                        <option value="">-- Pilih Jenis Perusahaan --</option>
                                        <?php foreach ($companyTypes as $value => $label): ?>
                                            <option value="<?= $value ?>"
                                                <?= ($company['company_type'] === $value) ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Business Sector</label>
                                <div class="col-md-9">
                                    <input type="text" name="business_sector" id="business_sector" class="form-control input-shadow" placeholder="Business Sector" value="<?= $company['business_sector'] ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Email</label>
                                <div class="col-md-9">
                                    <input type="email" name="email_company" id="email_company" class="form-control input-shadow" placeholder="Email" value="<?= $company['email_company'] ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Nomor Telepon</label>
                                <div class="col-md-9">
                                    <input type="text" name="nomor_company" id="nomor_company" class="form-control input-shadow" placeholder="Nomor Telepon" value="<?= $company['nomor_company'] ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Alamat</label>
                                <div class="col-md-9">
                                    <textarea type="text" name="alamat_company" id="alamat_company" class="form-control input-shadow" placeholder="Alamat"><?= $company['alamat_company'] ?></textarea>
                                </div>
                            </div>

                            <!-- PROVINCE -->
                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Provinsi</label>
                                <div class="col-md-9">
                                    <select class="form-control" name="province_id" id="province">
                                        <option value="">-- Pilih Provinsi --</option>
                                        <?php foreach ($provinces as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $company['province_id'] ? 'selected' : '' ?>><?= $p['name'] ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                            </div>

                            <!-- REGENCY -->
                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Kabupaten / Kota</label>
                                <div class="col-md-9">
                                    <select class="form-control" name="regency_id" id="regency"></select>
                                </div>
                            </div>

                            <!-- DISTRICT -->
                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Kecamatan</label>
                                <div class="col-md-9">
                                    <select class="form-control" name="district_id" id="district"></select>
                                </div>
                            </div>

                            <!-- VILLAGE -->
                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Desa / Kelurahan</label>
                                <div class="col-md-9">
                                    <select class="form-control" name="village_id" id="village"></select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Kode Pos</label>
                                <div class="col-md-9">
                                    <input type="text" name="postal_code" id="postal_code" class="form-control input-shadow" placeholder="Kode Pos" value="<?= $company['postal_code'] ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Website</label>
                                <div class="col-md-9">
                                    <input type="text" name="website" id="website" class="form-control input-shadow" placeholder="Website" value="<?= $company['website'] ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">Logo</label>
                                <div class="col-md-9">
                                    <input type="file" name="company_logo" id="company_logo" class="form-control input-shadow">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-light btn-block">Simpan Perubahan</button>

                        </form>

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

    <!-- Ajax Alamat -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function loadSelect(url, target, selected = null) {
            $.get(url, function(data) {
                $(target).html(data);
                if (selected) $(target).val(selected);
            });
        }

        // initial load (edit mode)
        loadSelect('ajax/regencies.php?province_id=<?= $company['province_id'] ?>', '#regency', <?= $company['regency_id'] ?>);
        loadSelect('ajax/districts.php?regency_id=<?= $company['regency_id'] ?>', '#district', <?= $company['district_id'] ?>);
        loadSelect('ajax/villages.php?district_id=<?= $company['district_id'] ?>', '#village', <?= $company['village_id'] ?>);

        $('#province').change(function() {
            loadSelect('ajax/regencies.php?province_id=' + this.value, '#regency');
            $('#district').html('');
            $('#village').html('');
        });

        $('#regency').change(function() {
            loadSelect('ajax/districts.php?regency_id=' + this.value, '#district');
            $('#village').html('');
        });

        $('#district').change(function() {
            loadSelect('ajax/villages.php?district_id=' + this.value, '#village');
        });
    </script>

</body>

</html>