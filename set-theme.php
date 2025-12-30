<?php
session_start();

// Cek apakah ada data tema yang dikirim via POST
if (isset($_POST['theme'])) {
    $_SESSION['selectedTheme'] = $_POST['theme']; // Simpan tema yang dipilih di session
    echo "Tema berhasil diperbarui!";
} else {
    echo "Tidak ada tema yang dipilih.";
}
