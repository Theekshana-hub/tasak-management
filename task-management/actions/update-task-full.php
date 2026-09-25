<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Session role key එක project එක අනුව වෙනස් වෙන්න පුළුවන්
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';

if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin'], true)) {
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

if ($title === '' || $task_id <= 0) {
    setFlash('danger', 'Title is required.');
    redirect('../admin/edit-task.php?id=' . $task_id);
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$old = $stmt->fetch();

if (!$old) {
    setFlash('danger', 'Task not found.');
    redirect('../admin/tasks.php');
}

// ===== Optional: Attachment upload =====
$attachment = $old['attachment'] ?? null;

if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/tasks/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext      = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
    $filename = 'task_' . $task_id . '_' . time() . '.' . $ext;
    $target   = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
        // Delete old file if exists
        if (!empty($old['attachment']) && file_exists($uploadDir . $old['attachment'])) {
            @unlink($uploadDir . $old['attachment']);
        }
        $attachment = $filename;
    }
}

// ===== Update task =====
$stmt = $pdo->prepare("
    UPDATE tasks 
    SET title = ?, description = ?, section_id = ?, assigned_to = ?, 
        priority = ?, status = ?, start_date = ?, due_date = ?, attachment = ?
    WHERE id = ?
");
$stmt->execute([
    $title,
    $description,
    $section_id,
    $assigned_to,
    $priority,
    $status,
    $start_date,
    $due_date,
    $attachment,
    $task_id
]);

$user_id = $_SESSION['user_id'];
logActivity($pdo, $task_id, $user_id, "Task updated by Admin");

// Notify if assigned user changed
if ((int)$old['assigned_to'] !== (int)$assigned_to) {
    createNotification(
        $pdo,
        $assigned_to,
        'Task Assigned to You',
        "You have been assigned task: \"$title\"",
        'task',
        $task_id
    );
}

setFlash('success', 'Task updated successfully.');
redirect("../admin/task-details.php?id=$task_id");