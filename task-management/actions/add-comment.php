<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    setFlash('danger', 'Please login.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../index.php');
}

$task_id = (int)($_POST['task_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['user_role'];

if ($task_id <= 0 || empty($comment)) {
    setFlash('danger', 'Invalid data.');
    redirect('../index.php');
}

$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT t.*, u.coordinator_id, u.name AS agent_name
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE t.id = ?
");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found.');
    redirect('../index.php');
}

$allowed = false;
if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'coordinator' && ((int)$task['coordinator_id'] === (int)$user_id || (int)$task['created_by'] === (int)$user_id)) {
    $allowed = true;
} elseif ((int)$task['assigned_to'] === (int)$user_id) {
    $allowed = true;
}

if (!$allowed) {
    setFlash('danger', 'You are not authorized to comment on this task.');
    redirect('../user/my-tasks.php');
}

$stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->execute([$task_id, $user_id, $comment]);
logActivity($pdo, $task_id, $user_id, "Added a comment");

if ($role === 'user') {
    $notified = [];
    if (!empty($task['coordinator_id'])) {
        createNotification($pdo, $task['coordinator_id'], 'New Comment on Task',
            "{$_SESSION['user_name']} commented on: \"{$task['title']}\"", 'comment', $task_id);
        $notified[] = (int)$task['coordinator_id'];
    }
    if (!empty($task['created_by']) && !in_array((int)$task['created_by'], $notified)) {
        createNotification($pdo, $task['created_by'], 'New Comment on Task',
            "{$_SESSION['user_name']} commented on: \"{$task['title']}\"", 'comment', $task_id);
        $notified[] = (int)$task['created_by'];
    }
    $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND status='active'")->fetchAll();
    foreach ($admins as $a) {
        if (!in_array((int)$a['id'], $notified)) {
            createNotification($pdo, $a['id'], 'New Comment on Task',
                "{$_SESSION['user_name']} commented on: \"{$task['title']}\"", 'comment', $task_id);
        }
    }
} else {
    createNotification($pdo, $task['assigned_to'], 'New Comment on Task',
        "{$_SESSION['user_name']} commented on: \"{$task['title']}\"", 'comment', $task_id);
}

setFlash('success', 'Comment added.');

if ($role === 'admin') {
    redirect("../admin/task-details.php?id=$task_id");
} elseif ($role === 'coordinator') {
    redirect("../coordinator/task-details.php?id=$task_id");
} else {
    redirect("../user/task-details.php?id=$task_id");
}
?>