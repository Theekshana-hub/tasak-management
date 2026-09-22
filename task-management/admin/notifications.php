<?php
$page_title = 'Notifications';


require_once '../includes/user_auth.php';  

$pdo = getDB();
$user_id = $_SESSION['user_id'];


if (isset($_GET['mark_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    setFlash('success', 'All notifications marked as read.');
    redirect('notifications.php');  
}


require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-bell"></i> Notifications</h2>
    <a href="?mark_all=1" class="btn btn-outline-primary btn-sm">Mark all as read</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
        <div class="list-group-item <?php echo $n['is_read'] ? '' : 'bg-light'; ?>">
            <div class="d-flex justify-content-between">
                <strong><?php echo e($n['title']); ?></strong>
                <small class="text-muted"><?php echo formatDateTime($n['created_at']); ?></small>
            </div>
            <p class="mb-1"><?php echo e($n['message']); ?></p>
            <?php if ($n['related_task_id']): ?>
            <a href="task-details.php?id=<?php echo $n['related_task_id']; ?>" class="small">View Task</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?>
        <div class="list-group-item text-center text-muted py-4">No notifications</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>