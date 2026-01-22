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
$id_karyawan = $encryption->decrypt($_GET['id']);
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
$id_company = $sesi_user["id_company"];

$stmt_karyawan = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt_karyawan->bind_param("i", $id_karyawan);
$stmt_karyawan->execute();
$result_karyawan = $stmt_karyawan->get_result();
$row_karyawan = $result_karyawan->fetch_assoc();
$stmt_karyawan->close();

$provinces = [];
$result_provinces = $conn->query("SELECT id, name FROM provinces ORDER BY name");
while ($row_provinces = $result_provinces->fetch_assoc()) {
    $provinces[] = $row_provinces;
}

$divisi = [];
$status_divisi = 'active';
$result_divisi = $conn->query("
    SELECT id_division, nama_divisi 
    FROM job_divisions WHERE status = '$status_divisi'
    ORDER BY nama_divisi
");

/* cek query berhasil */
if ($result_divisi === false) {
    die("Query error: " . $conn->error);
}

/* cek apakah ada data */
if ($result_divisi->num_rows > 0) {
    while ($row_divisi = $result_divisi->fetch_assoc()) {
        $divisi[] = $row_divisi;
    }
} else {
    // tidak ada data
    $divisi = []; // opsional, sudah kosong
}

$branch = [];
$stmt_branch = $conn->prepare("
    SELECT id_branch, nama_cabang 
    FROM branch_company 
    WHERE id_company = ?
    ORDER BY nama_cabang ASC
");

if (!$stmt_branch) {
    die("Prepare failed: " . $conn->error);
}

$stmt_branch->bind_param("i", $id_company);

if (!$stmt_branch->execute()) {
    die("Execute failed: " . $stmt_branch->error);
}

$result_branch = $stmt_branch->get_result();

/* cek apakah ada data */
if ($result_branch->num_rows > 0) {
    while ($row_branch = $result_branch->fetch_assoc()) {
        $branch[] = $row_branch;
    }
}

$stmt_branch->close();


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Trivanox - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <title>Data Karyawan - HR Management</title>
  <link rel="icon" href="assets/images/logo-trivanox.png" type="image/x-icon">
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet"/>
  <script src="assets/js/pace.min.js"></script>
  <!-- simplebar CSS-->
  <link href="assets/plugins/simplebar/css/simplebar.css" rel="stylesheet"/>
  <!-- Bootstrap core CSS-->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- animate CSS-->
  <link href="assets/css/animate.css" rel="stylesheet" type="text/css"/>
  <!-- Icons CSS-->
  <link href="assets/css/icons.css" rel="stylesheet" type="text/css"/>
  <!-- Sidebar CSS-->
  <link href="assets/css/sidebar-menu.css" rel="stylesheet"/>
  <!-- Custom Style-->
  <link href="assets/css/app-style.css" rel="stylesheet"/>
  
</head>

<body class="bg-theme bg-theme1">

<!-- start loader -->
   <div id="pageloader-overlay" class="visible incoming"><div class="loader-wrapper-outer"><div class="loader-wrapper-inner" ><div class="loader"></div></div></div></div>
   <!-- end loader -->

<!-- Start wrapper-->
 <div id="wrapper">

    <?php include 'left-sidebar.php'; ?>
    <?php include 'topbar.php'; ?>
<div class="clearfix"></div>
	
  <div class="content-wrapper">
    <div class="container-fluid">
    	  
    <!-- Page Heading -->
    <h4 class="h4 mb-2 text-gray-800">Data Karyawan</h4>
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
    <div class="row mt-3">
        <form action="karyawan-update" method="POST" class="w-100" enctype="multipart/form-data"> <!-- form pembungkus -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title">Informasi Identitas</div>
                        <hr>
                        <input type="hidden" name="id_karyawan" value="<?= $id_karyawan ?>">
                        <?php if (!empty($row_karyawan['foto_profile']) && $row_karyawan['foto_profile'] !== null): ?>
                        <div class="avatar">
                            <img class="align-self-start img-fluid mr-3" src="<?= $row_karyawan['foto_profile'] ?>" alt="logo avatar" style="width: 150px; height: auto;">
                        </div>
                        <?php endif; ?>
                        <hr>
                        <div class="form-group">
                            <label for="nik">Nomor Identitas (KTP/Passport)</label>
                            <input type="text" class="form-control" id="nik" name="nik" placeholder="Nomor Identitas" value="<?= $row_karyawan['nik'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="nip">Nomor ID Karyawan</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Nomor ID Karyawan"
                                value="<?= $row_karyawan['nip'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="role">Role User</label>
                            <select class="form-control" id="role" name="role">
                                <option value="staff" <?= $row_karyawan['role']=='staff'?'selected':'' ?>>Staff</option>
                                <option value="karyawan" <?= $row_karyawan['role']=='karyawan'?'selected':'' ?>>Karyawan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="name">Nama Lengkap</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Nama lengkap sesuai Kartu Identitas" value="<?= $row_karyawan['name'] ?>">
                            <!--<small>Nama Lengkap sesuai Kartu Identitas</small>-->
                        </div>
                        <div class="form-group">
                            <label for="nama_panggilan">Nama Panggilan</label>
                            <input type="text" class="form-control" id="nama_panggilan" name="nama_panggilan" placeholder="Nama Panggilan"
                                value="<?= $row_karyawan['nama_panggilan'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="tempat_lahir">Tempat Lahir</label>
                            <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" placeholder="Tempat Lahir"
                                value="<?= $row_karyawan['tempat_lahir'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="tanggal_lahir">Tanggal Lahir</label>
                            <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir"
                                value="<?= $row_karyawan['tanggal_lahir'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="jenis_kelamin">Jenis Kelamin</label>
                            <select class="form-control" id="jenis_kelamin" name="jenis_kelamin">
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki" <?= $row_karyawan['jenis_kelamin']=='Laki-laki'?'selected':'' ?>>Laki-laki</option>
                                <option value="Perempuan" <?= $row_karyawan['jenis_kelamin']=='Perempuan'?'selected':'' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_pernikahan">Status Pernikahan</label>
                            <select class="form-control" id="status_pernikahan" name="status_pernikahan">
                                <option value="">-- Pilih Status --</option>
                                <option value="Single" <?= $row_karyawan['status_pernikahan']=='Single'?'selected':'' ?>>Lajang</option>
                                <option value="Married" <?= $row_karyawan['status_pernikahan']=='Married'?'selected':'' ?>>Menikah</option>
                                <option value="Divorced" <?= $row_karyawan['status_pernikahan']=='Divorced'?'selected':'' ?>>Cerai</option>
                                <option value="Widowed" <?= $row_karyawan['status_pernikahan']=='Widowed'?'selected':'' ?>>Janda/Duda</option>
                                <option value="Separated" <?= $row_karyawan['status_pernikahan']=='Separated'?'selected':'' ?>>Berpisah</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="agama">Agama</label>
                            <select class="form-control" id="agama" name="agama">
                                <option value="">-- Pilih Agama --</option>
                                <?php
                                $agama = ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Lainnya'];
                                foreach ($agama as $a):
                                ?>
                                    <option value="<?= $a ?>" <?= $row_karyawan['agama']==$a?'selected':'' ?>><?= $a ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Foto Profile</label>
                            <input type="file" class="form-control mb-2" name="foto_profile">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title">Kontak</div>
                        <hr>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Alamat Email"
                                value="<?= $row_karyawan['email'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="nomor_hp">Nomor HP</label>
                            <input type="text" class="form-control" id="nomor_hp" name="nomor_hp" placeholder="08xxxxxxxxxx"
                                value="<?= $row_karyawan['nomor_hp'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="alamat">Alamat Lengkap</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3"
                                placeholder="Alamat Lengkap"><?= $row_karyawan['alamat'] ?></textarea>
                        </div>
            
                        <!-- PROVINCE -->
                        <div class="form-group">
                            <label for="province_id">Provinsi</label>
                            <select class="form-control" name="province_id" id="province_id">
                                <option value="">-- Pilih Provinsi --</option>
                                <?php foreach ($provinces as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $p['id']==$row_karyawan['province_id']?'selected':'' ?>><?= $p['name'] ?>
                                </option>
                                <?php endforeach ?>
                            </select>
                        </div>

                        <!-- REGENCY -->
                        <div class="form-group">
                            <label for="regency_id">Kabupaten / Kota</label>
                            <select class="form-control" name="regency_id" id="regency_id"></select>
                        </div>
            
                        <!-- DISTRICT -->
                        <div class="form-group">
                            <label for="district_id">Kecamatan</label>
                            <select class="form-control" name="district_id" id="district_id"></select>
                        </div>
            
                        <!-- VILLAGE -->
                        <div class="form-group">
                            <label for="village_id">Desa / Kelurahan</label>
                            <select class="form-control" name="village_id" id="village_id"></select>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code">Kode Pos</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code"
                                value="<?= $row_karyawan['postal_code'] ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title">Informasi Kepegawaian</div>
                        <hr>
                        <div class="form-group">
                            <label for="tanggal_masuk">Tanggal Masuk</label>
                            <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk" placeholder="Tanggal Masuk" value="<?= $row_karyawan['tanggal_masuk'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="tanggal_keluar">Tanggal Keluar</label>
                            <input type="date" class="form-control" id="tanggal_keluar" name="tanggal_keluar" placeholder="Keluar" value="<?= $row_karyawan['tanggal_keluar'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="user_status">Status Karyawan</label>
                            <select class="form-control" id="user_status" name="user_status">
                                <option value="">-- Pilih Status Karyawan --</option>
                                <option value="part_time" <?= $row_karyawan['user_status']=='part_time'?'selected':'' ?>>Part-time</option>
                                <option value="magang" <?= $row_karyawan['user_status']=='magang'?'selected':'' ?>>Magang</option>
                                <option value="training" <?= $row_karyawan['user_status']=='training'?'selected':'' ?>>Training</option>
                                <option value="probation" <?= $row_karyawan['user_status']=='probation'?'selected':'' ?>>Probation</option>
                                <option value="contract" <?= $row_karyawan['user_status']=='contract'?'selected':'' ?>>Kontrak</option>
                                <option value="permanent" <?= $row_karyawan['user_status']=='permanent'?'selected':'' ?>>Tetap</option>
                                <option value="lainnya" <?= $row_karyawan['user_status']=='lainnya'?'selected':'' ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_division">Divisi</label>
                            <select class="form-control" name="id_division" id="id_division">
                                <option value="">-- Pilih Divisi --</option>
                                <?php if (!empty($divisi)) : ?>
                                    <?php foreach ($divisi as $di) : ?>
                                        <option value="<?= $di['id_division'] ?>"
                                            <?= $di['id_division'] == ($row_karyawan['id_division'] ?? '') ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($di['nama_divisi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <option value="">Divisi belum tersedia.</option>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($divisi)) : ?>
                            <small>Jika divisi belum tersedia, silahkan buat divisi terlebih dahulu di menu Divisi.</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="jabatan">Jabatan</label>
                            <input type="text" class="form-control" id="jabatan" name="jabatan" placeholder="Jabatan" value="<?= $row_karyawan['jabatan'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="id_branch">Lokasi Kerja</label>
                            <select class="form-control" name="id_branch" id="id_branch">
                                <option value="">-- Pilih Lokasi --</option>
                                <?php if (!empty($branch)) : ?>
                                    <?php foreach ($branch as $b) : ?>
                                        <option value="<?= $b['id_branch'] ?>"
                                            <?= $b['id_branch'] == ($row_karyawan['id_branch'] ?? '') ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['nama_cabang']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <option value="">Lokasi belum tersedia.</option>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($branch)) : ?>
                            <small>Jika lokasi kerja belum tersedia, silahkan buat lokasi kerja terlebih dahulu di menu Cabang.</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="status">Status Akun</label>
                            <select class="form-control" id="status" name="status">
                                <option value="Active" <?= $row_karyawan['status']=='Active'?'selected':'' ?>>Active</option>
                                <option value="Inactive" <?= $row_karyawan['status']=='Inactive'?'selected':'' ?>>Inactive</option>
                                <option value="Resign" <?= $row_karyawan['status']=='Resign'?'selected':'' ?>>Resign</option>
                                <option value="End of Contract" <?= $row_karyawan['status']=='End of Contract'?'selected':'' ?>>End of Contract</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Dokumen Karyawan</label>
                            <br>
                            <small>File KTP 
                            <?php if (!empty($row_karyawan['file_ktp']) && $row_karyawan['file_ktp'] !== null): ?>
                            (Sudah ada)
                            <?php else: ?>
                            (Belum ada)
                            <?php endif; ?></small>
                            <input type="file" class="form-control mb-2" name="file_ktp">
                            <small>File KK 
                            <?php if (!empty($row_karyawan['file__kk']) && $row_karyawan['file__kk'] !== null): ?>
                            (Sudah ada)
                            <?php else: ?>
                            (Belum ada)
                            <?php endif; ?></small>
                            <input type="file" class="form-control mb-2" name="file_kk">
                            <small>File CV 
                            <?php if (!empty($row_karyawan['file_cv']) && $row_karyawan['file_cv'] !== null): ?>
                            (Sudah ada)
                            <?php else: ?>
                            (Belum ada)
                            <?php endif; ?></small>
                            <input type="file" class="form-control mb-2" name="file_cv">
                            <small>File Kontrak Kerja 
                            <?php if (!empty($row_karyawan['file_kontrak_kerja']) && $row_karyawan['file_kontrak_kerja'] !== null): ?>
                            (Sudah ada)
                            <?php else: ?>
                            (Belum ada)
                            <?php endif; ?></small>
                            <input type="file" class="form-control mb-2" name="file_kontrak_kerja">
                            <small>File NPWP 
                            <?php if (!empty($row_karyawan['file_npwp']) && $row_karyawan['file_npwp'] !== null): ?>
                            (Sudah ada)
                            <?php else: ?>
                            (Belum ada)
                            <?php endif; ?></small>
                            <input type="file" class="form-control mb-2" name="file_npwp">
                        </div>

                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title">Payroll & Benefit</div>
                        <hr>
                        <div class="form-group">
                            <label for="gaji_pokok">Gaji Pokok</label>
                            <input type="number" class="form-control" id="gaji_pokok" name="gaji_pokok" placeholder="Gaji Pokok"
                                value="<?= $row_karyawan['gaji_pokok'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="tunjangan_transport">Tunjangan Transport</label>
                            <input type="number" class="form-control" id="tunjangan_transport" name="tunjangan_transport" placeholder="Tunjangan Transport"
                                value="<?= $row_karyawan['tunjangan_transport'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="tunjangan_makan">Tunjangan Makan</label>
                            <input type="number" class="form-control" id="tunjangan_makan" name="tunjangan_makan"
                                placeholder="Tunjangan Makan"
                                value="<?= $row_karyawan['tunjangan_makan'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="tunjangan_jabatan">Tunjangan Jabatan</label>
                            <input type="number" class="form-control" id="tunjangan_jabatan" name="tunjangan_jabatan"
                                placeholder="Tunjangan Jabatan"
                                value="<?= $row_karyawan['tunjangan_jabatan'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="tunjangan_lainnya">Tunjangan Lainnya</label>
                            <input type="number" class="form-control" id="tunjangan_lainnya" name="tunjangan_lainnya"
                                placeholder="Tunjangan Lainnya"
                                value="<?= $row_karyawan['tunjangan_lainnya'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="ket_tunjangan_lainnya">Keterangan Tunjangan Lainnya</label>
                            <textarea class="form-control" id="ket_tunjangan_lainnya" name="ket_tunjangan_lainnya" rows="2"
                                placeholder="Keterangan tambahan tunjangan lainnya"><?= $row_karyawan['ket_tunjangan_lainnya'] ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="rekening_bank">Nama Bank</label>
                            <input type="text" class="form-control" id="rekening_bank" name="rekening_bank"
                                placeholder="Nama Bank"
                                value="<?= $row_karyawan['rekening_bank'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nomor_rekening">Nomor Rekening</label>
                            <input type="text" class="form-control" id="nomor_rekening" name="nomor_rekening"
                                placeholder="Nomor Rekening"
                                value="<?= $row_karyawan['nomor_rekening'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="npwp">NPWP</label>
                            <input type="text" class="form-control" id="npwp" name="npwp"
                                placeholder="Nomor NPWP"
                                value="<?= $row_karyawan['npwp'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="bpjs_tk">BPJS Ketenagakerjaan</label>
                            <input type="text" class="form-control" id="bpjs_tk" name="bpjs_tk"
                                placeholder="Nomor BPJS Ketenagakerjaan"
                                value="<?= $row_karyawan['bpjs_tk'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bpjs_kes">BPJS Kesehatan</label>
                            <input type="text" class="form-control" id="bpjs_kes" name="bpjs_kes"
                                placeholder="Nomor BPJS Kesehatan"
                                value="<?= $row_karyawan['bpjs_kes'] ?>">
                        </div>

                    </div>
                </div>
            </div>
        </div>

    <!-- checkbox + submit berada di luar card 
    <div class="form-group py-3 text-center">
      <div class="icheck-material-white d-inline-block me-2">
        <input type="checkbox" id="agree" checked="" />
        <label for="agree">I Agree Terms & Conditions</label>
      </div>
    </div>-->
    
    <div class="form-group text-center">
      <button type="submit" class="btn btn-light px-5">
        <i class="zmdi zmdi-save"></i> Simpan
      </button> 
      <a href="karyawan" class="btn btn-light px-5">
        <i class="zmdi zmdi-close"></i> Batal
      </a>
    </div>

  </form>
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
function loadSelect(url, target, selected = null) {
  $.get(url, function(data) {
    $(target).html(data);
    if (selected) $(target).val(selected);
  });
}

// initial load (edit mode)
loadSelect('ajax/regencies.php?province_id=<?= $row_karyawan['province_id'] ?>', '#regency_id', <?= $row_karyawan['regency_id'] ?>);
loadSelect('ajax/districts.php?regency_id=<?= $row_karyawan['regency_id'] ?>', '#district_id', <?= $row_karyawan['district_id'] ?>);
loadSelect('ajax/villages.php?district_id=<?= $row_karyawan['district_id'] ?>', '#village_id', <?= $row_karyawan['village_id'] ?>);

$('#province_id').change(function() {
  loadSelect('ajax/regencies.php?province_id=' + this.value, '#regency_id');
  $('#district').html('');
  $('#village').html('');
});

$('#regency_id').change(function() {
  loadSelect('ajax/districts.php?regency_id=' + this.value, '#district_id');
  $('#village').html('');
});

$('#district_id').change(function() {
  loadSelect('ajax/villages.php?district_id=' + this.value, '#village_id');
});
</script>
</body>
</html>
