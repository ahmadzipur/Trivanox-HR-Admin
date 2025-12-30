<?php
// Halaman timer PHP – bisa dinamai timer.php
// Tidak perlu session atau koneksi database
// Jika ingin delay dinamis, bisa juga pakai GET misalnya: ?t=45
$waktu = isset($_GET['t']) ? intval($_GET['t']) : 30;
?>

<!DOCTYPE html>
<html lang="en" class="pink-theme">

<head>
  
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="Please wait... - HR Management" />
  <meta name="author" content="Ahmad Zaelani" />
  <meta name="google" content="notranslate">
  <title>Mohon Tunggu...</title>
  <meta name="author" content="Ahmad Zaelani">
  <meta name="google" content="notranslate">
  <!-- loader-->
  <link href="assets/css/pace.min.css" rel="stylesheet" />
  <script src="assets/js/pace.min.js"></script>
  <!--favicon-->
  <link rel="icon" href="assets/images/logo-trivanox.ico" type="image/x-icon">
  <!-- Bootstrap core CSS-->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
  <!-- animate CSS-->
  <link href="assets/css/animate.css" rel="stylesheet" type="text/css" />
  <!-- Icons CSS-->
  <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
  <!-- Custom Style-->
  <link href="assets/css/app-style.css" rel="stylesheet" />
  <style>
    .timer {
      font-size: 48px;
      font-weight: bold;
      color: #4040ff;
      margin-top: 10px;
    }

    .progress {
      width: 100%;
      height: 10px;
      background-color: #ddd;
      border-radius: 5px;
      overflow: hidden;
      margin-top: 30px;
    }

    .progress-bar {
      height: 10px;
      background-color: #4040ff;
      width: 100%;
      transition: width 1s linear;
    }
  </style>
  <!-- Custom styles for this template -->
  <link href="css/style.css" rel="stylesheet">
</head>

<body>
  <div class="row no-gutters vh-100 proh bg-template">
    <div class="form-signin shadow col align-self-center p-3 text-center">
      <p class="text-center m-2">Kamu terlalu cepat mengakses halaman ini. Kamu akan dialihkan ke beranda dalam...</p>
      <div class="timer" id="timer"><?= htmlspecialchars($waktu) ?></div>
      <div class="progress">
        <div class="progress-bar" id="progress-bar"></div>
      </div>
      <br>
      <p class="text-center m-2">Bosan menunggu?</p>
      <a href="/" class="btn btn-sm btn-default btn-rounded shadow">Ke beranda sekarang</a>

    </div>
  </div>

  <script>
    let timeLeft = <?= json_encode($waktu) ?>;
    const timerElement = document.getElementById('timer');
    const progressBar = document.getElementById('progress-bar');
    const totalTime = timeLeft;

    const countdown = setInterval(() => {
      timeLeft--;
      timerElement.textContent = timeLeft;
      const progressWidth = (timeLeft / totalTime) * 100;
      progressBar.style.width = progressWidth + "%";

      if (timeLeft <= 0) {
        clearInterval(countdown);
        window.location.href = "/"; // Redirect ke home
      }
    }, 1000);
  </script>

  <!-- jquery, popper and bootstrap js -->
  <script src="js/jquery-3.3.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="vendor/bootstrap-4.4.1/js/bootstrap.min.js"></script>

  <!-- swiper js -->
  <script src="vendor/swiper/js/swiper.min.js"></script>

  <!-- template custom js -->
  <script src="js/main.js"></script>

</body>

</html>