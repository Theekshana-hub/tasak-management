<?php

require_once __DIR__ . '/auth.php';

// Only allow admin role
if ($CURRENT_USER['role'] !== 'admin') {
    setFlash('danger', 'Access denied. Admin only area.');
    redirect('../user/dashboard.php');
}
?>
