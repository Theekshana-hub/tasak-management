<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../coordinator/create-task.php');
}

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$section_id  = (int)($_POST['section_id'] ?? 0);
$assigned_to = $_POST['assigned_to'] ?? [];
$priority    = $_POST['priority'] ?? 'MEDIUM';
$start_date  = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$created_by  = $_SESSION['user_id'];
$coord_id    = $_SESSION['user_id'];

if (!is_array($assigned_to)) {
    $assigned_to = $assigned_to !== '' ? [(int)$assigned_to] : [];
}
$assigned_to = array_filter(array_map('intval', $assigned_to));

if (empty($title) || $section_id <= 0 || empty($assigned_to)) {
    setFlash('danger', 'Title, Section and at least one Agent are required.');
    redirect('../coordinator/create-task.php');
}

if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])) {
    $priority = 'MEDIUM';
}

$pdo = getDB();

$attachment = null;
if (!empty($_FILES['attachment']['name'])) {
    $upload = uploadFile($_FILES['attachment'], '../uploads/task-files/');
    if ($upload['success']) {
        $attachment = $upload['filename'];
    }
}

$success_count = 0;

foreach ($assigned_to as $agent_id) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND coordinator_id = ? AND role = 'user' AND status = 'active'");
    $stmt->execute([$agent_id, $coord_id]);
    $agent = $stmt->fetch();

    if (!$agent) {
        continue;
    }

    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, section_id, assigned_to, created_by, priority, status, start_date, due_date, attachment) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?)");
    $stmt->execute([$title, $description, $section_id, $agent_id, $created_by, $priority, $start_date, $due_date, $attachment]);

    $task_id = $pdo->lastInsertId();
    logActivity($pdo, $task_id, $created_by, "Task assigned by Coordinator");
    createNotification($pdo, $agent_id, 'New Task Assigned', "You have been assigned a new task: \"$title\"", 'task', $task_id);

    $success_count++;
}

if ($success_count > 0) {
    setFlash('success', "Task assigned to $success_count agent(s) successfully.");
} else {
    setFlash('danger', 'Could not assign task. Check selected agents.');
}

redirect('../coordinator/tasks.php');
?>