<?php
$page_title = 'Coordinator Dashboard';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];


$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE coordinator_id = ? AND role = 'user' AND status = 'active'");
$stmt->execute([$coord_id]);
$total_agents = $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE u.coordinator_id = ?
");
$stmt->execute([$coord_id]);
$total_tasks = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE u.coordinator_id = ? AND t.status = 'PENDING'
");
$stmt->execute([$coord_id]);
$pending = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE u.coordinator_id = ? AND t.status = 'IN_PROGRESS'
");
$stmt->execute([$coord_id]);
$in_progress = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE u.coordinator_id = ? AND t.status = 'COMPLETED'
");
$stmt->execute([$coord_id]);
$completed = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE u.coordinator_id = ? AND t.due_date < CURDATE() AND t.status NOT IN ('COMPLETED','CANCELLED')
");
$stmt->execute([$coord_id]);
$overdue = $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT t.*, u.name AS assigned_name, s.name AS section_name
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    JOIN sections s ON s.id = t.section_id
    WHERE u.coordinator_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$coord_id]);
$recent = $stmt->fetchAll();


$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status IN ('PENDING','IN_PROGRESS')) AS active_tasks
    FROM users u
    WHERE u.coordinator_id = ? AND u.role = 'user'
    ORDER BY u.name
");
$stmt->execute([$coord_id]);
$agents = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-speedometer2"></i> Coordinator Dashboard</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Assign Task</a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">My Agents</div>
                <div class="fs-4 fw-bold"><?php echo $total_agents; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Team Tasks</div>
                <div class="fs-4 fw-bold"><?php echo $total_tasks; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Pending</div>
                <div class="fs-4 fw-bold text-warning"><?php echo $pending; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">In Progress</div>
                <div class="fs-4 fw-bold text-primary"><?php echo $in_progress; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Completed</div>
                <div class="fs-4 fw-bold text-success"><?php echo $completed; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Overdue</div>
                <div class="fs-4 fw-bold text-danger"><?php echo $overdue; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- My Agents -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-people"></i> My Agents</span>
                <a href="agents.php" class="small">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($agents as $a): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo e($a['name']); ?></strong>
                            <br><small class="text-muted"><?php echo e($a['email']); ?></small>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?php echo $a['active_tasks']; ?> active</span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($agents)): ?>
                    <div class="list-group-item text-muted text-center py-4">No agents yet. Add agents from My Agents.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Team Tasks -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-list-task"></i> Recent Team Tasks</span>
                <a href="tasks.php" class="small">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Agent</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $t): ?>
                        <tr>
                            <td>
                                <a href="task-details.php?id=<?php echo $t['id']; ?>">
                                    <?php echo e($t['title']); ?>
                                </a>
                            </td>
                            <td><?php echo e($t['assigned_name']); ?></td>
                            <td><?php echo priorityBadge($t['priority']); ?></td>
                            <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                            <td><?php echo formatDate($t['due_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No tasks yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
