<?php

require_once __DIR__ . '/auth.php';


if ($CURRENT_USER['role'] !== 'coordinator') {
    setFlash('danger', 'Access denied. Coordinator only area.');
    if ($CURRENT_USER['role'] === 'admin') {
        redirect('../admin/dashboard.php');
    } else {
        redirect('../user/dashboard.php');
    }
}
?>
