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
    redirect('../user/my-tasks.php');
}

$task_id    = (int)($_POST['task_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';
$user_id    = $_SESSION['user_id'];

$pdo = getDB();

$stmt = $pdo->prepare("SELECT t.*, u.coordinator_id, u.name AS agent_name 
                       FROM tasks t 
                       JOIN users u ON u.id = t.assigned_to 
                       WHERE t.id = ? AND t.assigned_to = ?");
$stmt->execute([$task_id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or access denied.');
    redirect('../user/my-tasks.php');
}

function notifySupervisors($pdo, $task, $title, $message, $type = 'info') {
    $notified = [];

    // Coordinator
    if (!empty($task['coordinator_id'])) {
        createNotification($pdo, $task['coordinator_id'], $title, $message, $type, $task['id']);
        $notified[] = (int)$task['coordinator_id'];
    }

    // Task creator
    if (!empty($task['created_by']) && !in_array((int)$task['created_by'], $notified)) {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$task['created_by']]);
        $creator = $stmt->fetch();
        if ($creator && in_array($creator['role'], ['admin', 'coordinator'])) {
            createNotification($pdo, $creator['id'], $title, $message, $type, $task['id']);
            $notified[] = (int)$creator['id'];
        }
    }

    // Admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $admin) {
        if (!in_array((int)$admin['id'], $notified)) {
            createNotification($pdo, $admin['id'], $title, $message, $type, $task['id']);
        }
    }
}

if ($new_status === 'IN_PROGRESS' && $task['status'] === 'PENDING') {
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'IN_PROGRESS' WHERE id = ?");
    $stmt->execute([$task_id]);
    logActivity($pdo, $task_id, $user_id, "Changed status from Pending to In Progress");

    notifySupervisors($pdo, $task, 'Task Started',
        "{$_SESSION['user_name']} started task: \"{$task['title']}\"", 'info');

    setFlash('success', 'Task marked as In Progress.');

} elseif ($new_status === 'COMPLETED' && $task['status'] === 'IN_PROGRESS') {
    $comment = trim($_POST['completion_comment'] ?? '');
    $file = null;

    if (!empty($_FILES['completion_file']['name'])) {
        $upload = uploadFile($_FILES['completion_file'], '../uploads/task-files/');
        if ($upload['success']) {
            $file = $upload['filename'];
        }
    }

    $stmt = $pdo->prepare("UPDATE tasks SET status = 'COMPLETED', completed_at = NOW(), completed_by = ?, completion_comment = ?, completion_file = ?, admin_reviewed = 0 WHERE id = ?");
    $stmt->execute([$user_id, $comment, $file, $task_id]);

    logActivity($pdo, $task_id, $user_id, "Marked the task as Completed");

    notifySupervisors($pdo, $task, 'Task Completed',
        "{$_SESSION['user_name']} completed task: \"{$task['title']}\". Waiting for review.", 'success');

    setFlash('success', 'Task marked as Completed. Waiting for review.');
} else {
    setFlash('danger', 'Invalid status change.');
}

redirect("../user/task-details.php?id=$task_id");
?>