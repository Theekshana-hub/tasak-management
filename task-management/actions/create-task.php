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
    redirect('../admin/create-task.php');
}

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$section_id  = (int)($_POST['section_id'] ?? 0);
$assigned_to = $_POST['assigned_to'] ?? [];
$priority    = $_POST['priority'] ?? 'MEDIUM';
$start_date  = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$created_by  = $_SESSION['user_id'];

if (!is_array($assigned_to)) {
    $assigned_to = $assigned_to !== '' ? [(int)$assigned_to] : [];
}
$assigned_to = array_filter(array_map('intval', $assigned_to));

if (empty($title) || $section_id <= 0 || empty($assigned_to)) {
    setFlash('danger', 'Title, Section and at least one assignee are required.');
    redirect('../admin/create-task.php');
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
    } else {
        setFlash('danger', $upload['message']);
        redirect('../admin/create-task.php');
    }
}

$success_count = 0;

foreach ($assigned_to as $person_id) {
    // Allow both Agent (user) and Coordinator
    $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ? AND role IN ('user','coordinator') AND status = 'active'");
    $stmt->execute([$person_id]);
    $person = $stmt->fetch();

    if (!$person) {
        continue;
    }

    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, section_id, assigned_to, created_by, priority, status, start_date, due_date, attachment) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?)");
    $stmt->execute([$title, $description, $section_id, $person_id, $created_by, $priority, $start_date, $due_date, $attachment]);

    $task_id = $pdo->lastInsertId();
    logActivity($pdo, $task_id, $created_by, "Task created and assigned to " . $person['role']);
    createNotification($pdo, $person_id, 'New Task Assigned', "You have been assigned a new task: \"$title\"", 'task', $task_id);

    $success_count++;
}

if ($success_count > 0) {
    setFlash('success', "Task created and assigned to $success_count person(s).");
} else {
    setFlash('danger', 'Could not assign task. Check selected people.');
}

redirect('../admin/tasks.php');
?>