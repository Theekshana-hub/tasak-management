
<?php
require_once __DIR__ . '/auth.php';


if (!in_array($CURRENT_USER['role'], ['admin', 'super_admin'])) {
    setFlash('danger', 'Access denied. Admin only area.');
    redirect('../user/dashboard.php');
}
?>