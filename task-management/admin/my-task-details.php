<?php
$page_title = 'My Task Details';
require_once '../includes/auth.php';

$pdo     = getDB();
$user_id = (int)$_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? $_POST['task_id'] ?? 0);

// ---------- Handle Start / Complete on THIS page ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid request.');
        redirect('my-task-details.php?id=' . $id);
    }

    $new_status = $_POST['new_status'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$id, $user_id]);
    $taskRow = $stmt->fetch();

    if (!$taskRow) {
        setFlash('danger', 'Task not found or access denied.');
        redirect('my-tasks.php');
    }

    // START → IN_PROGRESS
    if ($new_status === 'IN_PROGRESS' && $taskRow['status'] === 'PENDING') {
        $stmt = $pdo->prepare("UPDATE tasks SET status = 'IN_PROGRESS', updated_at = NOW() WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$id, $user_id]);
        logActivity($pdo, $id, $user_id, "Changed status from Pending to In Progress");
        setFlash('success', 'Task marked as In Progress.');
        redirect('my-task-details.php?id=' . $id);
    }

    // COMPLETE
    if ($new_status === 'COMPLETED' && in_array($taskRow['status'], ['PENDING', 'IN_PROGRESS'], true)) {
        $comment = trim($_POST['completion_comment'] ?? '');
        $file    = null;

        if (!empty($_FILES['completion_file']['name'])) {
            $upload = uploadFile($_FILES['completion_file'], '../uploads/task-files/');
            if (!empty($upload['success'])) {
                $file = $upload['filename'];
            }
        }

        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = 'COMPLETED',
                completed_at = NOW(),
                completed_by = ?,
                completion_comment = ?,
                completion_file = ?,
                admin_reviewed = 0,
                updated_at = NOW()
            WHERE id = ? AND assigned_to = ?
        ");
        $stmt->execute([$user_id, $comment !== '' ? $comment : null, $file, $id, $user_id]);

        logActivity($pdo, $id, $user_id, "Marked the task as Completed");
        setFlash('success', 'Task marked as Completed.');
        redirect('my-task-details.php?id=' . $id);
    }

    setFlash('danger', 'Invalid status change.');
    redirect('my-task-details.php?id=' . $id);
}

// ---------- Load page ----------
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$stmt = $pdo->prepare("
    SELECT t.*, 
           u.name AS assigned_name, 
           u.email AS assigned_email,
           u.role AS assigned_role,
           c.name AS created_name,
           c.role AS created_role,
           s.name AS section_name,
           cb.name AS completed_by_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to
    LEFT JOIN users c ON c.id = t.created_by
    LEFT JOIN sections s ON s.id = t.section_id
    LEFT JOIN users cb ON cb.id = t.completed_by
    WHERE t.id = ? AND t.assigned_to = ?
");
$stmt->execute([$id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or you do not have access.');
    redirect('my-tasks.php');
}

function roleLabel($r) {
    $map = [
        'super_admin' => 'Managing Director',
        'admin'       => 'Management',
        'coordinator' => 'Executive/Coordinator',
        'user'        => 'Agent',
    ];
    return $map[$r] ?? ucfirst($r ?? '-');
}

$comments_stmt = $pdo->prepare("
    SELECT tc.*, u.name AS user_name, u.role 
    FROM task_comments tc
    JOIN users u ON u.id = tc.user_id
    WHERE tc.task_id = ?
    ORDER BY tc.created_at ASC
");
$comments_stmt->execute([$id]);
$comments = $comments_stmt->fetchAll();

$activities_stmt = $pdo->prepare("
    SELECT ta.*, u.name AS user_name
    FROM task_activities ta
    JOIN users u ON u.id = ta.user_id
    WHERE ta.task_id = ?
    ORDER BY ta.created_at DESC
");
$activities_stmt->execute([$id]);
$activities = $activities_stmt->fetchAll();

if (!function_exists('statusBadge')) {
    function statusBadge($status, $due_date = null) {
        $map = [
            'PENDING'     => 'bg-secondary',
            'IN_PROGRESS' => 'bg-primary',
            'COMPLETED'   => 'bg-success',
            'CANCELLED'   => 'bg-danger',
        ];
        $cls = $map[$status] ?? 'bg-secondary';
        $html = '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
        if ($due_date && !in_array($status, ['COMPLETED', 'CANCELLED'], true) && strtotime($due_date) < strtotime('today')) {
            $html .= ' <span class="badge bg-danger">OVERDUE</span>';
        }
        return $html;
    }
}

if (!function_exists('priorityBadge')) {
    function priorityBadge($priority) {
        $map = [
            'LOW'    => 'bg-info text-dark',
            'MEDIUM' => 'bg-secondary',
            'HIGH'   => 'bg-warning text-dark',
            'URGENT' => 'bg-danger',
        ];
        $cls = $map[$priority] ?? 'bg-secondary';
        return '<span class="badge ' . $cls . '">' . htmlspecialchars($priority) . '</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-card-checklist"></i> My Task Details</h2>
    <a href="my-tasks.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to My Tasks
    </a>
</div>

<?php
// Flash message
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = $flash['type'] ?? 'info';
    $msg  = $flash['message'] ?? '';
    if ($msg) {
        $alertClass = ($type === 'success') ? 'success' : (($type === 'danger') ? 'danger' : 'info');
        echo '<div class="alert alert-' . e($alertClass) . ' alert-dismissible fade show" role="alert">';
        echo e($msg);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h4 class="mb-3"><?php echo e($task['title']); ?></h4>

                <div class="mb-3">
                    <?php echo statusBadge($task['status'], $task['due_date']); ?>
                    <?php echo priorityBadge($task['priority']); ?>
                </div>

                <p class="text-muted"><?php echo nl2br(e($task['description'] ?? 'No description')); ?></p>

                <hr>
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <strong>Section:</strong> <?php echo e($task['section_name'] ?? '—'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Assigned To:</strong>
                        <?php echo e($task['assigned_name'] ?? '—'); ?>
                        <span class="badge bg-warning text-dark ms-1"><?php echo e(roleLabel($task['assigned_role'] ?? '')); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Created By:</strong>
                        <?php echo e($task['created_name'] ?? '—'); ?>
                        <span class="badge bg-secondary ms-1"><?php echo e(roleLabel($task['created_role'] ?? '')); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Created:</strong> <?php echo formatDateTime($task['created_at']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Start Date:</strong> <?php echo formatDate($task['start_date']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Due Date:</strong>
                        <?php
                        if ($task['due_date']) {
                            $due   = strtotime($task['due_date']);
                            $today = strtotime('today');
                            $cls   = ($due < $today && !in_array($task['status'], ['COMPLETED', 'CANCELLED'], true)) ? 'text-danger fw-bold' : '';
                            echo '<span class="' . $cls . '">' . formatDate($task['due_date']) . '</span>';
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>

                    <?php if ($task['attachment']): ?>
                    <div class="col-12">
                        <strong>Attachment:</strong>
                        <a href="../actions/download.php?file=<?php echo urlencode($task['attachment']); ?>&task_id=<?php echo (int)$task['id']; ?>"
                           target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($task['status'] === 'COMPLETED'): ?>
                    <div class="col-md-6">
                        <strong>Completed At:</strong> <?php echo formatDateTime($task['completed_at']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Completed By:</strong> <?php echo e($task['completed_by_name'] ?? '—'); ?>
                    </div>
                    <?php if ($task['completion_comment']): ?>
                    <div class="col-12">
                        <strong>Completion Comment:</strong><br>
                        <?php echo nl2br(e($task['completion_comment'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($task['completion_file']): ?>
                    <div class="col-12">
                        <strong>Completion File:</strong>
                        <a href="../actions/download.php?file=<?php echo urlencode($task['completion_file']); ?>&task_id=<?php echo (int)$task['id']; ?>"
                           class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <?php if ($task['status'] === 'PENDING'): ?>
                <hr>
                <form method="POST" action="my-task-details.php?id=<?php echo (int)$task['id']; ?>" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                    <input type="hidden" name="new_status" value="IN_PROGRESS">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-fill"></i> Start Task (Mark as In Progress)
                    </button>
                </form>

                <?php elseif ($task['status'] === 'IN_PROGRESS'): ?>
                <hr>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-primary px-3 py-2">
                        <i class="bi bi-arrow-repeat"></i> In Progress
                    </span>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeModal">
                        <i class="bi bi-check-lg"></i> Mark as Completed
                    </button>
                </div>

                <?php elseif ($task['status'] === 'COMPLETED'): ?>
                <hr>
                <span class="badge bg-success px-3 py-2 fs-6">
                    <i class="bi bi-check-circle"></i> Completed
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Comments</div>
            <div class="card-body comment-box">
                <?php if (empty($comments)): ?>
                    <p class="text-muted mb-0">No comments yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                    <div class="comment-item mb-3 p-2 rounded <?php echo in_array($c['role'], ['admin', 'super_admin'], true) ? 'bg-light' : ''; ?>">
                        <div class="d-flex justify-content-between">
                            <strong>
                                <?php echo e($c['user_name']); ?>
                                <span class="badge bg-secondary ms-1" style="font-size:0.65rem">
                                    <?php echo e(roleLabel($c['role'])); ?>
                                </span>
                            </strong>
                            <small class="text-muted"><?php echo formatDateTime($c['created_at']); ?></small>
                        </div>
                        <p class="mb-0 mt-1"><?php echo nl2br(e($c['comment'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="../actions/add-comment.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                    <div class="input-group">
                        <textarea name="comment" class="form-control" rows="2" placeholder="Write a comment..." required></textarea>
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
                <?php if (empty($activities)): ?>
                    <p class="text-muted small mb-0">No activity yet.</p>
                <?php else: ?>
                    <?php foreach ($activities as $a): ?>
                    <div class="activity-item mb-3 pb-2 border-bottom">
                        <small class="text-muted d-block"><?php echo formatDateTime($a['created_at']); ?></small>
                        <div>
                            <strong><?php echo e($a['user_name']); ?></strong>
                            <?php echo e($a['activity']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="my-task-details.php?id=<?php echo (int)$task['id']; ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                <input type="hidden" name="new_status" value="COMPLETED">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Completion Comment</label>
                        <textarea name="completion_comment" class="form-control" rows="3" placeholder="What did you complete?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Optional File</label>
                        <input type="file" name="completion_file" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Completed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>