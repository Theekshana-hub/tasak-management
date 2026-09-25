<?php
require_once '../includes/admin_auth.php';

$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: tasks.php?error=invalid_id');
    exit;
}


$check = $pdo->prepare("SELECT id, title FROM tasks WHERE id = ?");
$check->execute([$id]);
$task = $check->fetch();

if (!$task) {
    header('Location: tasks.php?error=not_found');
    exit;
}

try {
    
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: tasks.php?deleted=1');
    exit;
} catch (PDOException $e) {
    
    header('Location: tasks.php?error=delete_failed');
    exit;
}