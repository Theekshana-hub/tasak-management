<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Invalid section.');
    redirect('../admin/sections.php');
}

$pdo = getDB();

// Check usage
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE section_id = ?");
$stmt->execute([$id]);
$tasks = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id = ?");
$stmt->execute([$id]);
$users = $stmt->fetchColumn();

if ($tasks > 0 || $users > 0) {
    setFlash('danger', 'Cannot delete section. It is being used by tasks or users.');
    redirect('../admin/sections.php');
}

$stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
$stmt->execute([$id]);

setFlash('success', 'Section deleted successfully.');
redirect('../admin/sections.php');
?>
