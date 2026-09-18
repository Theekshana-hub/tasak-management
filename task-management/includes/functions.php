<?php

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}


function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, 
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}


function isOverdue($due_date, $status) {
    if (empty($due_date)) return false;
    if (in_array($status, ['COMPLETED', 'CANCELLED'])) return false;
    return strtotime($due_date) < strtotime(date('Y-m-d'));
}


function formatDate($date) {
    if (empty($date)) return '-';
    return date('d M Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date('d M Y h:i A', strtotime($datetime));
}


 
function statusBadge($status, $due_date = null) {
    if (isOverdue($due_date, $status)) {
        return '<span class="badge bg-danger">🔴 OVERDUE</span>';
    }
    
    $badges = [
        'PENDING'     => '<span class="badge bg-warning text-dark">🟡 Pending</span>',
        'IN_PROGRESS' => '<span class="badge bg-primary">🔵 In Progress</span>',
        'COMPLETED'   => '<span class="badge bg-success">🟢 Completed</span>',
        'CANCELLED'   => '<span class="badge bg-secondary">⚫ Cancelled</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . e($status) . '</span>';
}



function priorityBadge($priority) {
    $badges = [
        'LOW'    => '<span class="badge bg-info text-dark">Low</span>',
        'MEDIUM' => '<span class="badge bg-secondary">Medium</span>',
        'HIGH'   => '<span class="badge bg-warning text-dark">High</span>',
        'URGENT' => '<span class="badge bg-danger">Urgent</span>',
    ];
    
    return $badges[$priority] ?? '<span class="badge bg-secondary">' . e($priority) . '</span>';
}


function createNotification($pdo, $user_id, $title, $message, $type = 'info', $related_task_id = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_task_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $message, $type, $related_task_id]);
}


function logActivity($pdo, $task_id, $user_id, $activity) {
    $stmt = $pdo->prepare("INSERT INTO task_activities (task_id, user_id, activity) VALUES (?, ?, ?)");
    $stmt->execute([$task_id, $user_id, $activity]);
}


function getUnreadCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}


function uploadFile($file, $upload_dir = '../uploads/task-files/') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred.'];
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'File type not allowed.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        return ['success' => false, 'message' => 'File size exceeds 5MB.'];
    }
    
    $new_name = uniqid('task_', true) . '.' . $ext;
    $destination = $upload_dir . $new_name;
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $new_name];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file.'];
}


function base_url($path = '') {
    
    $base = '/task-management';
    return $base . '/' . ltrim($path, '/');
}
?>
