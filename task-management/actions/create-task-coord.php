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

$section_id  = (int)($_POST['section_id'] ?? 0);
$assigned_to = $_POST['assigned_to'] ?? [];
$tasks       = $_POST['tasks'] ?? [];
$created_by  = $_SESSION['user_id'];
$coord_id    = $_SESSION['user_id'];

if (!is_array($assigned_to)) {
    $assigned_to = $assigned_to !== '' ? [(int)$assigned_to] : [];
}
$assigned_to = array_filter(array_map('intval', $assigned_to));

if ($section_id <= 0 || empty($assigned_to) || empty($tasks) || !is_array($tasks)) {
    setFlash('danger', 'Section, at least one Agent and at least one Task are required.');
    redirect('../coordinator/create-task.php');
}

// Clean tasks
$clean_tasks = [];
foreach ($tasks as $t) {
    $title = trim($t['title'] ?? '');
    if ($title === '') continue;

    $start = !empty($t['start_date']) ? $t['start_date'] : date('Y-m-d');
    $days  = max(1, (int)($t['duration_days'] ?? 1));
    $due   = !empty($t['due_date']) ? $t['due_date'] : date('Y-m-d', strtotime($start . ' + ' . ($days - 1) . ' days'));
    if ($due < $start) $due = $start;

    $priority = $t['priority'] ?? 'MEDIUM';
    if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])) {
        $priority = 'MEDIUM';
    }

    $clean_tasks[] = [
        'title'       => $title,
        'description' => trim($t['description'] ?? ''),
        'priority'    => $priority,
        'start_date'  => $start,
        'due_date'    => $due,
        'days'        => $days
    ];
}

if (empty($clean_tasks)) {
    setFlash('danger', 'Please add at least one task with a title.');
    redirect('../coordinator/create-task.php');
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
    if (!$agent) continue;

    foreach ($clean_tasks as $task) {
        $stmt = $pdo->prepare("
            INSERT INTO tasks 
                (title, description, section_id, assigned_to, created_by, priority, status, start_date, due_date, attachment) 
            VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?)
        ");
        $stmt->execute([
            $task['title'],
            $task['description'],
            $section_id,
            $agent_id,
            $created_by,
            $task['priority'],
            $task['start_date'],
            $task['due_date'],
            $attachment
        ]);

        $task_id = $pdo->lastInsertId();
        $extra = $task['days'] > 1 ? " (for {$task['days']} days)" : "";
        logActivity($pdo, $task_id, $created_by, "Task assigned by Coordinator" . $extra);
        createNotification(
            $pdo,
            $agent_id,
            'New Task Assigned',
            "You have been assigned: \"{$task['title']}\"" . $extra,
            'task',
            $task_id
        );
        $success_count++;
    }
}

if ($success_count > 0) {
    setFlash('success', "$success_count task(s) assigned successfully.");
} else {
    setFlash('danger', 'Could not assign tasks. Check selected agents.');
}

redirect('../coordinator/tasks.php');
?>