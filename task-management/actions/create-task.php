<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$role = $_SESSION['user_role'] ?? '';


if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin', 'user'], true)) {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

$is_self_only = ($role === 'user'); 

if ($role === 'super_admin') {
    $back_create = '../superadmin/create-task.php';
    $back_tasks  = '../superadmin/tasks.php';
} elseif ($role === 'admin') {
    $back_create = '../admin/create-task.php';
    $back_tasks  = '../admin/tasks.php';
} else {
    $back_create = '../user/create-task.php';
    $back_tasks  = '../user/my-tasks.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect($back_create);
}

$section_id  = (int)($_POST['section_id'] ?? 0);
$section_id  = $section_id > 0 ? $section_id : null;
$assigned_to = $_POST['assigned_to'] ?? [];
$tasks       = $_POST['tasks'] ?? [];
$created_by  = (int)$_SESSION['user_id'];


if (!is_array($assigned_to)) {
    $assigned_to = $assigned_to !== '' ? [(int)$assigned_to] : [];
}
$assigned_to = array_values(array_unique(array_filter(array_map('intval', $assigned_to))));


if ($is_self_only) {
    $assigned_to = [$created_by];
}

if (empty($assigned_to)) {
    setFlash('danger', 'At least one assignee is required.');
    redirect($back_create);
}

if (empty($tasks) || !is_array($tasks)) {
    setFlash('danger', 'At least one task is required.');
    redirect($back_create);
}


function isValidDate($d) {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $dt && $dt->format('Y-m-d') === $d;
}

$clean_tasks = [];
foreach ($tasks as $t) {
    if (!is_array($t)) continue;

    $title = trim($t['title'] ?? '');
    if ($title === '') continue;

    $priority = $t['priority'] ?? 'MEDIUM';
    if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
        $priority = 'MEDIUM';
    }

    $start = (!empty($t['start_date']) && isValidDate($t['start_date'])) ? $t['start_date'] : date('Y-m-d');
    $days  = max(1, (int)($t['duration_days'] ?? 1));

    
    if (!empty($t['due_date']) && isValidDate($t['due_date'])) {
        $due = $t['due_date'];
    } else {
        $due = date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'));
    }

    $clean_tasks[] = [
        'title'       => $title,
        'description' => trim($t['description'] ?? ''),
        'priority'    => $priority,
        'start_date'  => $start,
        'due_date'    => $due,
    ];
}

if (empty($clean_tasks)) {
    setFlash('danger', 'Please add at least one task with a title.');
    redirect($back_create);
}

$pdo = getDB();


if ($section_id !== null) {
    $stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND status = 'active'");
    $stmt->execute([$section_id]);
    if (!$stmt->fetch()) {
        setFlash('danger', 'Invalid section selected.');
        redirect($back_create);
    }
}


$attachment = null;
if (!empty($_FILES['attachment']['name'])) {
    $upload = uploadFile($_FILES['attachment'], '../uploads/task-files/');
    if ($upload['success']) {
        $attachment = $upload['filename'];
    } else {
        setFlash('danger', $upload['message']);
        redirect($back_create);
    }
}


if ($role === 'super_admin') {
    
    $allowed_roles = ['super_admin', 'admin', 'coordinator', 'user'];
    $creator_label = 'Managing Director';
} elseif ($role === 'admin') {
    
    $allowed_roles = ['admin', 'coordinator', 'user'];
    $creator_label = 'Management';
} else {
   
    $allowed_roles = ['user'];
    $creator_label = 'Agent (self-assigned)';
}

$placeholders = implode(',', array_fill(0, count($allowed_roles), '?'));
$personStmt = $pdo->prepare("
    SELECT id, name, role
    FROM users
    WHERE id = ?
      AND role IN ($placeholders)
      AND status = 'active'
    LIMIT 1
");

$insertStmt = $pdo->prepare("
    INSERT INTO tasks
        (title, description, section_id, assigned_to, created_by, priority, status, start_date, due_date, attachment)
    VALUES
        (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?)
");

$success_count = 0;
$people_count  = 0;

try {
    $pdo->beginTransaction();

    foreach ($assigned_to as $person_id) {
       
        if ($is_self_only && $person_id !== $created_by) {
            continue;
        }

        $personStmt->execute(array_merge([$person_id], $allowed_roles));
        $person = $personStmt->fetch();

        if (!$person) {
            continue;
        }
        $people_count++;

        
        foreach ($clean_tasks as $task) {
            $insertStmt->execute([
                $task['title'],
                $task['description'],
                $section_id,
                $person_id,
                $created_by,
                $task['priority'],
                $task['start_date'],
                $task['due_date'],
                $attachment
            ]);

            $task_id = (int)$pdo->lastInsertId();

            logActivity(
                $pdo,
                $task_id,
                $created_by,
                "Task created by $creator_label and assigned to " . $person['name'] . " (" . $person['role'] . ")"
            );

           
            if (!$is_self_only) {
                createNotification(
                    $pdo,
                    $person_id,
                    'New Task Assigned',
                    "You have been assigned a new task: \"" . $task['title'] . "\"",
                    'task',
                    $task_id
                );
            }

            $success_count++;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('create-task.php error: ' . $e->getMessage());
    setFlash('danger', 'Something went wrong while creating tasks. Nothing was saved.');
    redirect($back_create);
}

if ($success_count > 0) {
    if ($is_self_only) {
        setFlash('success', count($clean_tasks) . ' task(s) added to your list.');
        redirect($back_tasks);
    }
    setFlash('success', count($clean_tasks) . " task(s) assigned to $people_count person(s) ($success_count total).");
} else {
    setFlash('danger', 'Could not assign tasks. Check selected people.');
}

redirect($back_create);