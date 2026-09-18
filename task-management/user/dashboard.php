<?php
$page_title = 'My Dashboard';
require_once '../includes/user_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Stats for this user only
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
$stmt->execute([$user_id]);
$total = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'PENDING'");
$stmt->execute([$user_id]);
$pending = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'IN_PROGRESS'");
$stmt->execute([$user_id]);
$in_progress = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'COMPLETED'");
$stmt->execute([$user_id]);
$completed = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED')");
$stmt->execute([$user_id]);
$overdue = $stmt->fetchColumn();

// Recent tasks
$stmt = $pdo->prepare("
    SELECT t.*, s.name AS section_name
    FROM tasks t
    JOIN sections s ON s.id = t.section_id
    WHERE t.assigned_to = ?
    ORDER BY t.created_at DESC
    LIMIT 8
");
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-speedometer2"></i> My Dashboard</h2>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">My Tasks</div>
                <div class="fs-3 fw-bold"><?php echo $total; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Pending</div>
                <div class="fs-3 fw-bold text-warning"><?php echo $pending; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">In Progress</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $in_progress; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Completed</div>
                <div class="fs-3 fw-bold text-success"><?php echo $completed; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Overdue</div>
                <div class="fs-3 fw-bold text-danger"><?php echo $overdue; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>Recent Tasks</span>
        <a href="my-tasks.php" class="small">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Section</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $t): ?>
                <tr>
                    <td><?php echo e($t['title']); ?></td>
                    <td><?php echo e($t['section_name']); ?></td>
                    <td><?php echo priorityBadge($t['priority']); ?></td>
                    <td><?php echo formatDate($t['due_date']); ?></td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td><a href="task-details.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No tasks assigned to you yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
