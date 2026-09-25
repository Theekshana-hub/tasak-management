<?php
require_once '../includes/admin_auth.php';

$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: tasks.php?error=invalid_id');
    exit;
}

// Task එක තියෙනවද කියලා check කරනවා
$check = $pdo->prepare("SELECT id, title FROM tasks WHERE id = ?");
$check->execute([$id]);
$task = $check->fetch();

if (!$task) {
    header('Location: tasks.php?error=not_found');
    exit;
}

try {
    // Related tables තියෙනවා නම් (comments, attachments, logs)
    // ඒවා task delete කරන්න කලින් delete කරන්න. උදාහරණ:
    // $pdo->prepare("DELETE FROM task_comments WHERE task_id = ?")->execute([$id]);
    // $pdo->prepare("DELETE FROM task_attachments WHERE task_id = ?")->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: tasks.php?deleted=1');
    exit;
} catch (PDOException $e) {
    // Foreign key constraint එකක් නම් මෙතන catch වෙනවා
    header('Location: tasks.php?error=delete_failed');
    exit;
}