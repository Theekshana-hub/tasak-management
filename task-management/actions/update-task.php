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
    redirect('../admin/tasks.php');
}

$task_id = (int)($_POST['task_id'] ?? 0);
$action  = $_POST['action'] ?? '';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found.');
    redirect('../admin/tasks.php');
}

$user_id = $_SESSION['user_id'];

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE tasks SET admin_reviewed = 1 WHERE id = ?");
    $stmt->execute([$task_id]);
    logActivity($pdo, $task_id, $user_id, "Completion approved by Admin");
    createNotification($pdo, $task['assigned_to'], 'Task Approved', "Your task \"{$task['title']}\" has been approved by Admin.", 'success', $task_id);
    setFlash('success', 'Task completion approved.');
} elseif ($action === 'reopen') {
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'IN_PROGRESS', admin_reviewed = 0, completed_at = NULL, completed_by = NULL, completion_comment = NULL WHERE id = ?");
    $stmt->execute([$task_id]);
    logActivity($pdo, $task_id, $user_id, "Task reopened by Admin");
    createNotification($pdo, $task['assigned_to'], 'Task Reopened', "Your task \"{$task['title']}\" has been reopened by Admin.", 'warning', $task_id);
    setFlash('success', 'Task has been reopened.');
}

redirect("../admin/task-details.php?id=$task_id");
?>
