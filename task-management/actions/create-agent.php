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
    redirect('../coordinator/agents.php');
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$section  = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$coord_id = $_SESSION['user_id'];

if (empty($name) || empty($email) || empty($password)) {
    setFlash('danger', 'Name, email and password are required.');
    redirect('../coordinator/add-agent.php');
}

if ($password !== $confirm) {
    setFlash('danger', 'Passwords do not match.');
    redirect('../coordinator/add-agent.php');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    setFlash('danger', 'Email already exists.');
    redirect('../coordinator/add-agent.php');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, section_id, coordinator_id, status) VALUES (?, ?, ?, ?, 'user', ?, ?, 'active')");
$stmt->execute([$name, $email, $phone, $hash, $section, $coord_id]);

setFlash('success', 'Agent created successfully.');
redirect('../coordinator/agents.php');
?>
