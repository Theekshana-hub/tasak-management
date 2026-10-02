<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set to true ONLY while debugging to see the real error on screen
$debug = false;

// Only Super Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    redirect('../superadmin/users.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    redirect('../superadmin/add-user.php');
}

$name       = trim($_POST['name'] ?? '');
$email      = strtolower(trim($_POST['email'] ?? ''));
$phone      = trim($_POST['phone'] ?? '');
$section_id = ($_POST['section_id'] ?? '') !== '' ? (int)$_POST['section_id'] : null;
$role       = $_POST['role'] ?? 'user';
$status     = $_POST['status'] ?? 'active';
$password   = $_POST['password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

// Keep old input so the form can be re-filled on error (password never stored)
$_SESSION['old_input'] = [
    'name'       => $name,
    'email'      => $email,
    'phone'      => $phone,
    'section_id' => $section_id,
    'role'       => $role,
    'status'     => $status,
];

$allowedRoles    = ['user', 'coordinator', 'admin', 'super_admin'];
$allowedStatuses = ['active', 'inactive'];

// ===== Validation =====
if ($name === '' || $email === '' || $password === '') {
    setFlash('danger', 'Name, email and password are required.');
    redirect('../superadmin/add-user.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('danger', 'Please enter a valid email address.');
    redirect('../superadmin/add-user.php');
}

if (!in_array($role, $allowedRoles, true)) {
    setFlash('danger', 'Invalid role selected.');
    redirect('../superadmin/add-user.php');
}

if (!in_array($status, $allowedStatuses, true)) {
    setFlash('danger', 'Invalid status selected.');
    redirect('../superadmin/add-user.php');
}

if (strlen($password) < 6) {
    setFlash('danger', 'Password must be at least 6 characters.');
    redirect('../superadmin/add-user.php');
}

if ($password !== $confirm) {
    setFlash('danger', 'Passwords do not match.');
    redirect('../superadmin/add-user.php');
}

$pdo = getDB();

try {
    // Detect which columns really exist in the users table
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    $passCol = null;
    foreach (['password', 'password_hash', 'pass'] as $candidate) {
        if (in_array($candidate, $cols, true)) {
            $passCol = $candidate;
            break;
        }
    }
    if ($passCol === null) {
        throw new Exception('No password column found in users table.');
    }

    // Email must be unique
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        setFlash('danger', 'A user with this email already exists.');
        redirect('../superadmin/add-user.php');
    }

    // Section must exist (if selected)
    if ($section_id !== null) {
        $stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ? LIMIT 1");
        $stmt->execute([$section_id]);
        if (!$stmt->fetch()) {
            setFlash('danger', 'Selected section does not exist.');
            redirect('../superadmin/add-user.php');
        }
    }

    // Build INSERT dynamically based on existing columns
    $data = [
        'name'       => $name,
        'email'      => $email,
        'section_id' => $section_id,
        'role'       => $role,
        'status'     => $status,
        $passCol     => password_hash($password, PASSWORD_DEFAULT),
    ];

    if (in_array('phone', $cols, true)) {
        $data['phone'] = $phone;
    }

    $fields       = [];
    $placeholders = [];
    $values       = [];

    foreach ($data as $col => $val) {
        if (!in_array($col, $cols, true)) {
            continue;
        }
        $fields[]       = "`$col`";
        $placeholders[] = '?';
        $values[]       = $val;
    }

    if (in_array('created_at', $cols, true)) {
        $fields[]       = '`created_at`';
        $placeholders[] = 'NOW()';
    }

    $sql = "INSERT INTO users (" . implode(', ', $fields) . ")
            VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    unset($_SESSION['old_input']);
    setFlash('success', 'User "' . $name . '" created successfully.');
    redirect('../superadmin/users.php');

} catch (Throwable $e) {
    error_log('Create user (super) failed: ' . $e->getMessage());
    $msg = $debug ? 'DB Error: ' . $e->getMessage() : 'Could not create user. Please try again.';
    setFlash('danger', $msg);
    redirect('../superadmin/add-user.php');
}