<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$role = $_SESSION['user_role'] ?? '';


if (!isset($_SESSION['user_id']) || $role !== 'coordinator') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}


$back_agents = '../coordinator/agents.php';


$show_debug_errors = true;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect($back_agents);
}

$coord_id = (int)$_SESSION['user_id'];
$agent_id = (int)($_POST['agent_id'] ?? 0);

if ($agent_id <= 0) {
    setFlash('danger', 'Invalid agent.');
    redirect($back_agents);
}

$pdo = getDB();


$stmt = $pdo->prepare("
    SELECT section_id 
    FROM users 
    WHERE id = ? AND role = 'coordinator' AND status = 'active' 
    LIMIT 1
");
$stmt->execute([$coord_id]);
$coord = $stmt->fetch();
$coord_section_id = (int)($coord['section_id'] ?? 0);

if ($coord_section_id <= 0) {
    setFlash('danger', 'No section assigned to you.');
    redirect($back_agents);
}


$stmt = $pdo->prepare("
    SELECT id, name 
    FROM users 
    WHERE id = ? AND role = 'user' AND section_id = ? 
    LIMIT 1
");
$stmt->execute([$agent_id, $coord_section_id]);
$agent = $stmt->fetch();

if (!$agent) {
    setFlash('danger', 'Agent not found in your department.');
    redirect($back_agents);
}


function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function referencingColumns(PDO $pdo, string $refTable): array {
    $stmt = $pdo->prepare("
        SELECT TABLE_NAME AS t, COLUMN_NAME AS c
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME = ?
          AND REFERENCED_COLUMN_NAME = 'id'
    ");
    $stmt->execute([$refTable]);
    return $stmt->fetchAll();
}


function deleteWhereIn(PDO $pdo, string $table, string $column, array $ids): void {
    if (empty($ids) || !columnExists($pdo, $table, $column)) return;
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$column` IN ($ph)");
    $stmt->execute(array_values($ids));
}

$deleted_tasks = 0;

try {
    $pdo->beginTransaction();


    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$agent_id]);
    $task_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));


    $stmt = $pdo->prepare("UPDATE tasks SET created_by = ? WHERE created_by = ? AND assigned_to <> ?");
    $stmt->execute([$coord_id, $agent_id, $agent_id]);


    if (!empty($task_ids)) {
      
        foreach (referencingColumns($pdo, 'tasks') as $ref) {
            if ($ref['t'] === 'tasks') continue;
            deleteWhereIn($pdo, $ref['t'], $ref['c'], $task_ids);
        }
       
        foreach (['task_comments', 'task_activities', 'activity_logs', 'task_attachments'] as $tbl) {
            deleteWhereIn($pdo, $tbl, 'task_id', $task_ids);
        }

      
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE assigned_to = ?");
        $stmt->execute([$agent_id]);
        $deleted_tasks = $stmt->rowCount();
    }

   
    foreach (referencingColumns($pdo, 'users') as $ref) {
        if ($ref['t'] === 'tasks') continue; 
        if ($ref['t'] === 'users') {
            
            $stmt = $pdo->prepare("UPDATE `users` SET `{$ref['c']}` = NULL WHERE `{$ref['c']}` = ?");
            $stmt->execute([$agent_id]);
            continue;
        }
        deleteWhereIn($pdo, $ref['t'], $ref['c'], [$agent_id]);
    }
   
    foreach (['notifications', 'task_comments', 'task_activities', 'activity_logs'] as $tbl) {
        deleteWhereIn($pdo, $tbl, 'user_id', [$agent_id]);
    }

  
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user' AND section_id = ?");
    $stmt->execute([$agent_id, $coord_section_id]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Agent row was not deleted.');
    }

    $pdo->commit();

    $msg = 'Agent "' . $agent['name'] . '" deleted successfully';
    if ($deleted_tasks > 0) {
        $msg .= " (along with $deleted_tasks task(s))";
    }
    setFlash('success', $msg . '.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('delete-agent.php error: ' . $e->getMessage());
    $detail = $show_debug_errors ? ' Details: ' . $e->getMessage() : '';
    setFlash('danger', 'Could not delete the agent. Nothing was changed.' . $detail);
}

redirect($back_agents);