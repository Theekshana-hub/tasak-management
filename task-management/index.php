<?php

session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'coordinator') {
        header('Location: coordinator/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
} else {
    header('Location: login.php');
}
exit;
?>
