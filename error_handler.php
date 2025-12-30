<?php
// ====== ERROR HANDLING UNTUK LOGGING ======
error_reporting(E_ALL);   // Report semua jenis error
ini_set('display_errors', 0);   // Matikan tampil error ke layar
ini_set('log_errors', 1);       // Aktifkan log error
ini_set('error_log', __DIR__ . '/error_log.txt'); // File tujuan log
?>
