<?php
require_once '../includes/admin_auth.php';

$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: tasks.php?error=invalid_id');
    exit;
}

// Optional: check task exists first (also useful if you want to log/notify)
$check = $pdo->prepare("SELECT id, title FROM tasks WHERE id = ?");
$check->execute([$id]);
$task = $check->fetch();

if (!$task) {
    header('Location: tasks.php?error=not_found');
    exit;
}

try {
    // If there are related rows in other tables (comments, attachments, logs etc.)
    // delete them first to avoid foreign key constraint errors. Example:
    // $pdo->prepare("DELETE FROM task_comments WHERE task_id = ?")->execute([$id]);
    // $pdo->prepare("DELETE FROM task_attachments WHERE task_id = ?")->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: tasks.php?deleted=1');
    exit;
} catch (PDOException $e) {
    // Likely a foreign key constraint from a related table
    header('Location: tasks.php?error=delete_failed');
    exit;
}