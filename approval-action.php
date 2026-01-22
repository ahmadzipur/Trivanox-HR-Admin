<?php
ob_clean();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);
include 'error_handler.php';
session_start();
include "koneksi.php";
date_default_timezone_set("Asia/Jakarta");  // Set zona waktu ke Jakarta

$id         = $_POST['id'];
$status     = $_POST['status'];
$reason     = $_POST['reason'] ?? null;
$target_id  = $_POST['target_user'];
$id_user    = $_SESSION['user']['id'];

$now = date('Y-m-d H:i:s');

if ($status === 'approved') {
    $sql = "UPDATE request_izin_cuti SET
            status='approved',
            approved_by=?,
            approved_at=?,
            updated_by=?,
            updated_at=?
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isisi", $user_id, $now, $user_id, $now, $id);
    
    $title = 'Pemberitahuan';
    $message = 'Pengajuan Anda telah disetujui.';
    $target_page = 'data-izin-cuti-saya';
    $icon = 'zmdi zmdi-assignment-check';
    $color = 'success';
    
    $stmtNotif = $conn->prepare("
        INSERT INTO notifications (id_user, title, message, target_page, target_id, icon, color, created_at, created_by, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmtNotif->bind_param("isssisssis", $id_user, $title, $message, $target_page, $target_id, $icon, $color, $now, $id_user, $now);
    $stmtNotif->execute();
    
    $_SESSION['status'] = 'success';
    $_SESSION['message'] = 'Pengajuan disetujui.';

} elseif ($status === 'rejected') {
    $sql = "UPDATE request_izin_cuti SET
            status='rejected',
            rejected_reason=?,
            approved_by=?,
            approved_at=?,
            updated_by=?,
            updated_at=?
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisisi", $reason, $user_id, $now, $user_id, $now, $id);
    
    $title = 'Pemberitahuan';
    $message = 'Pengajuan Anda ditolak.';
    $target_page = 'data-izin-cuti-saya';
    $icon = 'zmdi zmdi-assignment-check';
    $color = 'danger';
    
    $stmtNotif = $conn->prepare("
        INSERT INTO notifications (id_user, title, message, target_page, target_id, icon, color, created_at, created_by, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmtNotif->bind_param("isssisssis", $id_user, $title, $message, $target_page, $target_id, $icon, $color, $now, $id_user, $now);
    $stmtNotif->execute();
    
    $_SESSION['status'] = 'success';
    $_SESSION['message'] = 'Pengajuan ditolak.';

} elseif ($status === 'cancelled') {
    $sql = "UPDATE request_izin_cuti SET
            status='cancelled',
            updated_by=?,
            updated_at=?
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $user_id, $now, $id);
    
    $title = 'Pemberitahuan';
    $message = 'Pengajuan Anda dibatalkan.';
    $target_page = 'data-izin-cuti-saya';
    $icon = 'zmdi zmdi-assignment-check';
    $color = 'warning';
    
    $stmtNotif = $conn->prepare("
        INSERT INTO notifications (id_user, title, message, target_page, target_id, icon, color, created_at, created_by, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmtNotif->bind_param("isssisssis", $id_user, $title, $message, $target_page, $target_id, $icon, $color, $now, $id_user, $now);
    $stmtNotif->execute();
    
    $_SESSION['status'] = 'success';
    $_SESSION['message'] = 'Pengajuan dibatalkan.';
}

$stmt->execute();
echo json_encode([
    'status' => 'success'
]);
exit;
