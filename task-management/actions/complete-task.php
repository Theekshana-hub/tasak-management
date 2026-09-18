<?php
require_once '../includes/auth.php';   // any logged-in user
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../user/my-tasks.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid CSRF token';
    header('Location: ../user/my-tasks.php');
    exit;
}

$task_id = (int)($_POST['task_id'] ?? 0);
$notes   = trim($_POST['notes'] ?? '');
$user_id = $_SESSION['user_id'];

if ($task_id <= 0) {
    $_SESSION['error'] = 'Invalid task';
    header('Location: ../user/my-tasks.php');
    exit;
}

$pdo = getDB();
$pdo->beginTransaction();

try {
    // Check this user is actually assigned
    $check = $pdo->prepare("
        SELECT id, status FROM task_assignees 
        WHERE task_id = ? AND user_id = ?
    ");
    $check->execute([$task_id, $user_id]);
    $assignee = $check->fetch();

    if (!$assignee) {
        throw new Exception('You are not assigned to this task');
    }

    if ($assignee['status'] === 'completed') {
        throw new Exception('You have already completed this task');
    }

    // Mark this user's part as completed
    $upd = $pdo->prepare("
        UPDATE task_assignees 
        SET status = 'completed', completed_at = NOW(), notes = ?
        WHERE task_id = ? AND user_id = ?
    ");
    $upd->execute([$notes, $task_id, $user_id]);

    // Check how many are still pending / in_progress
    $count = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM task_assignees 
        WHERE task_id = ?
    ");
    $count->execute([$task_id]);
    $stats = $count->fetch();

    // Update main task status
    if ($stats['completed'] == $stats['total']) {
        // Everyone finished
        $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?")
            ->execute([$task_id]);
    } else {
        // At least one person finished → mark as in_progress
        $pdo->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ? AND status = 'pending'")
            ->execute([$task_id]);
    }

    $pdo->commit();
    $_SESSION['success'] = 'Task marked as completed by you.';
    header('Location: ../user/my-tasks.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../user/my-tasks.php');
    exit;
}