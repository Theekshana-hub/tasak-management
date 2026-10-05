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

$user_id     = (int)$_SESSION['user_id'];
$id          = (int)($_POST['id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$status      = strtoupper(trim($_POST['status'] ?? 'PENDING'));
$due_date    = $_POST['due_date'] ?? null;

$allowed = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];
if (!in_array($status, $allowed, true)) {
    $status = 'PENDING';
}

if ($id <= 0) {
    redirect('../superadmin/my-tasks.php?error=not_found');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ? LIMIT 1");
$stmt->execute([$id, $user_id]);
if (!$stmt->fetch()) {
    redirect('../superadmin/my-tasks.php?error=not_allowed');
}

$due_date = ($due_date === '') ? null : $due_date;

$stmt = $pdo->prepare("
    UPDATE tasks
    SET description = ?, status = ?, due_date = ?
    WHERE id = ? AND assigned_to = ?
");
$stmt->execute([$description, $status, $due_date, $id, $user_id]);

redirect('../superadmin/my-tasks.php?updated=1');