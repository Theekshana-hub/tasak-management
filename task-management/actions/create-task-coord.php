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
$created_by  = (int)$_SESSION['user_id'];
$coord_id    = (int)$_SESSION['user_id'];

if (!is_array($assigned_to)) {
    $assigned_to = $assigned_to !== '' ? [(int)$assigned_to] : [];
}
$assigned_to = array_values(array_unique(array_filter(array_map('intval', $assigned_to))));

if ($section_id <= 0 || empty($assigned_to) || empty($tasks) || !is_array($tasks)) {
    setFlash('danger', 'Section, at least one person and at least one Task are required.');
    redirect('../coordinator/create-task.php');
}

// Clean tasks
$clean_tasks = [];
foreach ($tasks as $t) {
    $title = trim($t['title'] ?? '');
    if ($title === '') continue;

    $start = !empty($t['start_date']) ? $t['start_date'] : date('Y-m-d');
    $days  = max(1, (int)($t['duration_days'] ?? 1));
    $priority = $t['priority'] ?? 'MEDIUM';
    if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
        $priority = 'MEDIUM';
    }

    $clean_tasks[] = [
        'title'       => $title,
        'description' => trim($t['description'] ?? ''),
        'priority'    => $priority,
        'start_date'  => $start,
        'days'        => $days
    ];
}

if (empty($clean_tasks)) {
    setFlash('danger', 'Please add at least one task with a title.');
    redirect('../coordinator/create-task.php');
}

$pdo = getDB();

// ===== Coordinator own section =====
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ? AND role = 'coordinator' AND status = 'active' LIMIT 1");
$stmt->execute([$coord_id]);
$coord_section_id = $stmt->fetchColumn();
$coord_section_id = ($coord_section_id !== false && $coord_section_id !== null) ? (int)$coord_section_id : null;

// ===== Allowed sections = own + Super Admin granted =====
$allowed_section_ids = [];
if ($coord_section_id) {
    $allowed_section_ids[] = $coord_section_id;
}

$acc = $pdo->prepare("SELECT section_id FROM coordinator_section_access WHERE coordinator_id = ?");
$acc->execute([$coord_id]);
foreach ($acc->fetchAll(PDO::FETCH_COLUMN) as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && !in_array($sid, $allowed_section_ids, true)) {
        $allowed_section_ids[] = $sid;
    }
}

// Section validation – must be in allowed list
if ($section_id <= 0 || empty($allowed_section_ids) || !in_array($section_id, $allowed_section_ids, true)) {
    setFlash('danger', 'Invalid section. You can only assign tasks in your allowed departments.');
    redirect('../coordinator/create-task.php');
}

// ---------- Attachment ----------
$attachment = null;
if (!empty($_FILES['attachment']['name'])) {
    $upload = uploadFile($_FILES['attachment'], '../uploads/task-files/');
    if ($upload['success']) {
        $attachment = $upload['filename'];
    }
}

$success_count = 0;
$ph = implode(',', array_fill(0, count($allowed_section_ids), '?'));

foreach ($assigned_to as $user_id) {
    // Allow:
    // 1) Self (coordinator)
    // 2) Any active agent in ALLOWED sections (own + granted departments)
    $stmt = $pdo->prepare("
        SELECT id, name, role 
        FROM users 
        WHERE id = ? 
          AND status = 'active'
          AND (
                (id = ? AND role = 'coordinator')
             OR (role = 'user' AND section_id IN ($ph))
          )
        LIMIT 1
    ");
    $params = array_merge([$user_id, $coord_id], $allowed_section_ids);
    $stmt->execute($params);
    $person = $stmt->fetch();

    if (!$person) {
        continue; // invalid / not allowed
    }

    foreach ($clean_tasks as $task) {
        // Duration days → one daily task per day
        for ($i = 0; $i < $task['days']; $i++) {
            $day_date = date('Y-m-d', strtotime($task['start_date'] . " +{$i} days"));
            $day_title = $task['title'];
            if ($task['days'] > 1) {
                $day_title = $task['title'] . ' (Day ' . ($i + 1) . '/' . $task['days'] . ' – ' . $day_date . ')';
            }

            $stmt = $pdo->prepare("
                INSERT INTO tasks 
                    (title, description, section_id, assigned_to, created_by, priority, status, start_date, due_date, attachment) 
                VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?)
            ");
            $stmt->execute([
                $day_title,
                $task['description'],
                $section_id,
                $user_id,
                $created_by,
                $task['priority'],
                $day_date,
                $day_date,
                $attachment
            ]);

            $task_id = $pdo->lastInsertId();

            logActivity(
                $pdo,
                $task_id,
                $created_by,
                "Task assigned by Coordinator (Day " . ($i + 1) . "/{$task['days']})"
            );

            createNotification(
                $pdo,
                $user_id,
                'New Task Assigned',
                "You have been assigned: \"{$day_title}\"",
                'task',
                $task_id
            );

            $success_count++;
        }
    }
}

if ($success_count > 0) {
    setFlash('success', "$success_count task(s) assigned successfully. Can be completed day by day.");
} else {
    setFlash('danger', 'Could not assign tasks. Check selected people (yourself or agents in your allowed departments).');
}

redirect('../coordinator/tasks.php');