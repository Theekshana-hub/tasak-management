<?php
require_once '../includes/coordinator_auth.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: tasks.php?error=invalid_id');
    exit;
}


$check = $pdo->prepare("
    SELECT t.id
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE t.id = ? AND u.coordinator_id = ?
");
$check->execute([$id, $coord_id]);
$task = $check->fetch();

if (!$task) {
    
    header('Location: tasks.php?error=not_allowed');
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