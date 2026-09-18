<?php
$page_title = 'Task Details';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);


$stmt = $pdo->prepare("
    SELECT t.*, u.name AS assigned_name, s.name AS section_name, c.name AS created_name
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    JOIN sections s ON s.id = t.section_id
    JOIN users c ON c.id = t.created_by
    WHERE t.id = ? AND u.coordinator_id = ?
");
$stmt->execute([$id, $coord_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or access denied.');
    redirect('tasks.php');
}

$comments = $pdo->prepare("SELECT tc.*, u.name AS user_name, u.role FROM task_comments tc JOIN users u ON u.id = tc.user_id WHERE tc.task_id = ? ORDER BY tc.created_at ASC");
$comments->execute([$id]);
$comments = $comments->fetchAll();

$activities = $pdo->prepare("SELECT ta.*, u.name AS user_name FROM task_activities ta JOIN users u ON u.id = ta.user_id WHERE ta.task_id = ? ORDER BY ta.created_at DESC");
$activities->execute([$id]);
$activities = $activities->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-card-checklist"></i> Task Details</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h4><?php echo e($task['title']); ?></h4>
                <div class="mb-3">
                    <?php echo statusBadge($task['status'], $task['due_date']); ?>
                    <?php echo priorityBadge($task['priority']); ?>
                </div>
                <p class="text-muted"><?php echo nl2br(e($task['description'] ?? 'No description')); ?></p>
                <hr>
                <div class="row g-3 small">
                    <div class="col-md-6"><strong>Agent:</strong> <?php echo e($task['assigned_name']); ?></div>
                    <div class="col-md-6"><strong>Section:</strong> <?php echo e($task['section_name']); ?></div>
                    <div class="col-md-6"><strong>Created By:</strong> <?php echo e($task['created_name']); ?></div>
                    <div class="col-md-6"><strong>Due Date:</strong> <?php echo formatDate($task['due_date']); ?></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Comments</div>
            <div class="card-body comment-box">
                <?php foreach ($comments as $c): ?>
                <div class="comment-item <?php echo $c['role'] === 'admin' || $c['role'] === 'coordinator' ? 'admin' : ''; ?>">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo e($c['user_name']); ?></strong>
                        <small class="text-muted"><?php echo formatDateTime($c['created_at']); ?></small>
                    </div>
                    <p class="mb-0 mt-1"><?php echo nl2br(e($c['comment'])); ?></p>
                </div>
                <?php endforeach; ?>
                <?php if (empty($comments)): ?><p class="text-muted">No comments yet.</p><?php endif; ?>
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="../actions/add-comment.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                    <div class="input-group">
                        <textarea name="comment" class="form-control" rows="2" required placeholder="Write a comment..."></textarea>
                        <button type="submit" class="btn btn-coral">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Activity History</div>
            <div class="card-body">
                <?php foreach ($activities as $a): ?>
                <div class="activity-item">
                    <small class="text-muted"><?php echo formatDateTime($a['created_at']); ?></small>
                    <div><strong><?php echo e($a['user_name']); ?></strong> <?php echo e($a['activity']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
