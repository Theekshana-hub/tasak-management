<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../admin/sections.php');
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = $_POST['status'] ?? 'active';

if (empty($name) || $id <= 0) {
    setFlash('danger', 'Invalid data.');
    redirect('../admin/sections.php');
}

$pdo = getDB();
$stmt = $pdo->prepare("UPDATE sections SET name=?, description=?, status=? WHERE id=?");
$stmt->execute([$name, $description, $status, $id]);

setFlash('success', 'Section updated successfully.');
redirect('../admin/sections.php');
?>
