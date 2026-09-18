<?php


require_once __DIR__ . '/auth.php';


if (!in_array($CURRENT_USER['role'], ['admin', 'user'])) {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}
?>
