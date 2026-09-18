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

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = $_POST['status'] ?? 'active';

if (empty($name)) {
    setFlash('danger', 'Section name is required.');
    redirect('../admin/add-section.php');
}

$pdo = getDB();
$stmt = $pdo->prepare("INSERT INTO sections (name, description, status) VALUES (?, ?, ?)");
$stmt->execute([$name, $description, $status]);

setFlash('success', 'Section created successfully.');
redirect('../admin/sections.php');
?>
