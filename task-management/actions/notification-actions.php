<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    setFlash('danger', 'Please login.');
    redirect('../login.php');
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];


if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$nid, $user_id]);
}


if (isset($_GET['mark_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    setFlash('success', 'All notifications marked as read.');
}


if ($role === 'admin') {
    redirect('../admin/notifications.php');
} else {
    redirect('../user/notifications.php');
}
?>
