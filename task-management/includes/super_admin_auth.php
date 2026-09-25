<?php
require_once __DIR__ . '/auth.php';


if ($CURRENT_USER['role'] !== 'super_admin') {
    setFlash('danger', 'Access denied. Super Admin only area.');
    if ($CURRENT_USER['role'] === 'admin') {
        redirect('../admin/dashboard.php');
    } elseif ($CURRENT_USER['role'] === 'coordinator') {
        redirect('../coordinator/dashboard.php');
    } else {
        redirect('../user/dashboard.php');
    }
}
?>