<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$role = $_SESSION['user_role'] ?? '';

// Only coordinators can edit agents
if (!isset($_SESSION['user_id']) || $role !== 'coordinator') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

// NOTE: coordinator folder name eka oyage project ekata maru karanna
$back_agents = '../coordinator/agents.php';
$back_edit   = '../coordinator/edit-agent.php?id=';

$agent_id = (int)($_POST['agent_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect($back_agents);
}

if ($agent_id <= 0) {
    setFlash('danger', 'Invalid agent.');
    redirect($back_agents);
}

$coord_id = (int)$_SESSION['user_id'];
$pdo      = getDB();

// Coordinator ගේ section එක
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

// Agent එක same section එකේ role = 'user' කෙනෙක්ද?
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'user' AND section_id = ? LIMIT 1");
$stmt->execute([$agent_id, $coord_section_id]);
if (!$stmt->fetch()) {
    setFlash('danger', 'Agent not found in your department.');
    redirect($back_agents);
}

// ---------- Input validation ----------
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$status   = $_POST['status'] ?? 'active';
$password = (string)($_POST['password'] ?? '');

if ($name === '' || mb_strlen($name) > 100) {
    setFlash('danger', 'Please enter a valid name.');
    redirect($back_edit . $agent_id);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    setFlash('danger', 'Please enter a valid email address.');
    redirect($back_edit . $agent_id);
}

if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    setFlash('danger', 'Please enter a valid phone number.');
    redirect($back_edit . $agent_id);
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

if ($password !== '' && strlen($password) < 6) {
    setFlash('danger', 'Password must be at least 6 characters.');
    redirect($back_edit . $agent_id);
}

// Email එක වෙන කෙනෙක් use කරනවද?
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
$stmt->execute([$email, $agent_id]);
if ($stmt->fetch()) {
    setFlash('danger', 'This email is already used by another user.');
    redirect($back_edit . $agent_id);
}

// ---------- Update ----------
try {
    $sql    = "UPDATE users SET name = ?, email = ?, phone = ?, status = ?";
    $params = [$name, $email, ($phone !== '' ? $phone : null), $status];

    // Password දුන්නොත් විතරක් update කරනවා
    // NOTE: oyage users table eke password column eka 'password' nemei nam maru karanna
    if ($password !== '') {
        $sql     .= ", password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql     .= " WHERE id = ? AND role = 'user' AND section_id = ?";
    $params[] = $agent_id;
    $params[] = $coord_section_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    setFlash('success', 'Agent "' . $name . '" updated successfully.');
} catch (Throwable $e) {
    error_log('update-agent.php error: ' . $e->getMessage());
    setFlash('danger', 'Something went wrong while updating the agent.');
    redirect($back_edit . $agent_id);
}

redirect($back_agents);