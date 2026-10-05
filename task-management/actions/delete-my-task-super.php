<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../superadmin/my-tasks.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token.');
    redirect('../superadmin/my-tasks.php');
}

$user_id = (int)$_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('../superadmin/my-tasks.php?error=not_found');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ? LIMIT 1");
$stmt->execute([$id, $user_id]);
if (!$stmt->fetch()) {
    redirect('../superadmin/my-tasks.php?error=not_allowed');
}

try {
    $del = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND assigned_to = ?");
    $del->execute([$id, $user_id]);
    redirect('../superadmin/my-tasks.php?deleted=1');
} catch (PDOException $e) {
    redirect('../superadmin/my-tasks.php?error=delete_failed');
}