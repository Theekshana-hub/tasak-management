<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Please login.');
}

$filename = basename($_GET['file'] ?? '');
$task_id  = (int)($_GET['task_id'] ?? 0);

if ($filename === '' || $task_id <= 0) {
    http_response_code(400);
    die('Invalid request.');
}

if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(400);
    die('Invalid filename.');
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['user_role'] ?? '';

$stmt = $pdo->prepare("SELECT t.*, u.coordinator_id FROM tasks t JOIN users u ON u.id = t.assigned_to WHERE t.id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    die('Task not found.');
}

$allowed_files = array_filter([
    $task['attachment'] ?? null,
    $task['completion_file'] ?? null,
]);

if (!in_array($filename, $allowed_files, true)) {
    http_response_code(403);
    die('File not linked to this task.');
}

$allowed = false;
if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'coordinator' && ((int)$task['coordinator_id'] === (int)$user_id || (int)$task['created_by'] === (int)$user_id)) {
    $allowed = true;
} elseif ((int)$task['assigned_to'] === (int)$user_id) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    die('Access denied.');
}

$path = __DIR__ . '/../uploads/task-files/' . $filename;

if (!is_file($path)) {
    http_response_code(404);
    die('File not found on server. Check uploads/task-files folder.');
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip' => 'application/zip', 'txt' => 'text/plain',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($path);
exit;
?>