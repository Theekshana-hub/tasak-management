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

$task_id     = (int)($_POST['task_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$section_id  = (int)($_POST['section_id'] ?? 0);
$assigned_to = (int)($_POST['assigned_to'] ?? 0);
$priority    = $_POST['priority'] ?? 'MEDIUM';
$status      = $_POST['status'] ?? 'PENDING';
$start_date  = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$old = $stmt->fetch();

if (!$old) {
    setFlash('danger', 'Task not found.');
    redirect('../admin/tasks.php');
}

$stmt = $pdo->prepare("UPDATE tasks SET title=?, description=?, section_id=?, assigned_to=?, priority=?, status=?, start_date=?, due_date=? WHERE id=?");
$stmt->execute([$title, $description, $section_id, $assigned_to, $priority, $status, $start_date, $due_date, $task_id]);

$user_id = $_SESSION['user_id'];
logActivity($pdo, $task_id, $user_id, "Task updated by Admin");

// Notify if assigned user changed
if ($old['assigned_to'] != $assigned_to) {
    createNotification($pdo, $assigned_to, 'Task Assigned to You', "You have been assigned task: \"$title\"", 'task', $task_id);
}

setFlash('success', 'Task updated successfully.');
redirect("../admin/task-details.php?id=$task_id");
?>
