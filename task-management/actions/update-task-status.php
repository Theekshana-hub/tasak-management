<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    setFlash('danger', 'Please login.');
    redirect('../login.php');
}

$role    = $_SESSION['user_role'] ?? '';
$user_id = (int)$_SESSION['user_id'];

function detailsUrl($role, $task_id) {
    $map = [
        'super_admin' => '../super_admin/my-task-details.php',
        'admin'       => '../admin/my-task-details.php',
        'coordinator' => '../coordinator/my-task-details.php',
        'user'        => '../user/task-details.php',
    ];
    $base = $map[$role] ?? '../user/task-details.php';
    return $base . '?id=' . (int)$task_id;
}

function listUrl($role) {
    $map = [
        'super_admin' => '../super_admin/my-tasks.php',
        'admin'       => '../admin/my-tasks.php',
        'coordinator' => '../coordinator/my-tasks.php',
        'user'        => '../user/my-tasks.php',
    ];
    return $map[$role] ?? '../user/my-tasks.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect(listUrl($role));
}

$task_id    = (int)($_POST['task_id'] ?? 0);
$new_status = $_POST['new_status'] ?? $_POST['status'] ?? '';

if ($task_id <= 0 || $new_status === '') {
    setFlash('danger', 'Invalid task or status.');
    redirect(listUrl($role));
}

$pdo = getDB();

// No coordinator_id — works even if that column does not exist
$stmt = $pdo->prepare("
    SELECT t.*
    FROM tasks t
    WHERE t.id = ? AND t.assigned_to = ?
");
$stmt->execute([$task_id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or access denied.');
    redirect(listUrl($role));
}

function notifySupervisors($pdo, $task, $title, $message, $type = 'info') {
    $notified = [];

    // Creator
    if (!empty($task['created_by'])) {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$task['created_by']]);
        $creator = $stmt->fetch();
        if ($creator && in_array($creator['role'], ['admin', 'coordinator', 'super_admin'], true)) {
            createNotification($pdo, $creator['id'], $title, $message, $type, $task['id']);
            $notified[] = (int)$creator['id'];
        }
    }

    // Admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $admin) {
        if (!in_array((int)$admin['id'], $notified, true)) {
            createNotification($pdo, $admin['id'], $title, $message, $type, $task['id']);
            $notified[] = (int)$admin['id'];
        }
    }

    // Super admins
    $supers = $pdo->query("SELECT id FROM users WHERE role = 'super_admin' AND status = 'active'")->fetchAll();
    foreach ($supers as $sa) {
        if (!in_array((int)$sa['id'], $notified, true)) {
            createNotification($pdo, $sa['id'], $title, $message, $type, $task['id']);
        }
    }
}

$user_name = $_SESSION['user_name'] ?? 'User';

// ---- START → IN_PROGRESS ----
if ($new_status === 'IN_PROGRESS' && $task['status'] === 'PENDING') {

    $stmt = $pdo->prepare("UPDATE tasks SET status = 'IN_PROGRESS', updated_at = NOW() WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $user_id]);

    logActivity($pdo, $task_id, $user_id, "Changed status from Pending to In Progress");

    notifySupervisors(
        $pdo,
        $task,
        'Task Started',
        "$user_name started task: \"{$task['title']}\"",
        'info'
    );

    setFlash('success', 'Task marked as In Progress.');

// ---- COMPLETE ----
} elseif ($new_status === 'COMPLETED' && in_array($task['status'], ['PENDING', 'IN_PROGRESS'], true)) {

    $comment = trim($_POST['completion_comment'] ?? '');
    $file    = null;

    if (!empty($_FILES['completion_file']['name'])) {
        $upload = uploadFile($_FILES['completion_file'], '../uploads/task-files/');
        if (!empty($upload['success'])) {
            $file = $upload['filename'];
        }
    }

    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET status = 'COMPLETED',
            completed_at = NOW(),
            completed_by = ?,
            completion_comment = ?,
            completion_file = ?,
            admin_reviewed = 0,
            updated_at = NOW()
        WHERE id = ? AND assigned_to = ?
    ");
    $stmt->execute([$user_id, $comment !== '' ? $comment : null, $file, $task_id, $user_id]);

    logActivity($pdo, $task_id, $user_id, "Marked the task as Completed");

    notifySupervisors(
        $pdo,
        $task,
        'Task Completed',
        "$user_name completed task: \"{$task['title']}\". Waiting for review.",
        'success'
    );

    setFlash('success', 'Task marked as Completed.');

} else {
    setFlash('danger', 'Invalid status change. Current: ' . $task['status'] . ' → ' . $new_status);
}

redirect(detailsUrl($role, $task_id));