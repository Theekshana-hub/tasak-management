<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'coordinator') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    redirect('../coordinator/tasks.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token.');
    redirect('../coordinator/tasks.php');
}

$coord_id = (int)$_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('../coordinator/tasks.php?error=invalid_id');
}

$pdo = getDB();

// Coordinator section
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ? AND role = 'coordinator' LIMIT 1");
$stmt->execute([$coord_id]);
$coord_section_id = $stmt->fetchColumn();
$coord_section_id = ($coord_section_id !== false && $coord_section_id !== null) ? (int)$coord_section_id : null;

if (!$coord_section_id) {
    redirect('../coordinator/tasks.php?error=not_allowed');
}

// Task must be in coordinator's department
$stmt = $pdo->prepare("
    SELECT t.id
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE t.id = ?
      AND u.role = 'user'
      AND (u.section_id = ? OR t.section_id = ?)
    LIMIT 1
");
$stmt->execute([$id, $coord_section_id, $coord_section_id]);
$task = $stmt->fetch();

if (!$task) {
    redirect('../coordinator/tasks.php?error=not_found');
}

try {
    $del = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $del->execute([$id]);
    redirect('../coordinator/tasks.php?deleted=1');
} catch (PDOException $e) {
    redirect('../coordinator/tasks.php?error=delete_failed');
}