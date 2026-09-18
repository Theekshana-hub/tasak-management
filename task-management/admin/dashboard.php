<?php
$page_title = 'Admin Dashboard';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

// Total stats
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$pending     = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'PENDING'")->fetchColumn();
$in_progress = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'IN_PROGRESS'")->fetchColumn();
$completed   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'COMPLETED'")->fetchColumn();
$cancelled   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'CANCELLED'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// Overdue
$overdue_stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED')");
$overdue = $overdue_stmt->fetchColumn();

// Section statistics
$section_stats = $pdo->query("
    SELECT s.name,
           COUNT(t.id) AS total,
           SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
           SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress,
           SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed
    FROM sections s
    LEFT JOIN tasks t ON t.section_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id, s.name
    ORDER BY total DESC
")->fetchAll();

// Recent tasks
$recent_tasks = $pdo->query("
    SELECT t.*, u.name AS assigned_name, s.name AS section_name
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    JOIN sections s ON s.id = t.section_id
    ORDER BY t.created_at DESC
    LIMIT 8
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Create Task</a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-list-task"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Tasks</div>
                    <div class="fs-4 fw-bold"><?php echo $total_tasks; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending</div>
                    <div class="fs-4 fw-bold"><?php echo $pending; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="text-muted small">In Progress</div>
                    <div class="fs-4 fw-bold"><?php echo $in_progress; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Completed</div>
                    <div class="fs-4 fw-bold"><?php echo $completed; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="text-muted small">Overdue</div>
                    <div class="fs-4 fw-bold"><?php echo $overdue; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="text-muted small">Staff Users</div>
                    <div class="fs-4 fw-bold"><?php echo $total_users; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Section Statistics -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-diagram-3"></i> Tasks by Section
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Total</th>
                                <th>Pending</th>
                                <th>In Prog.</th>
                                <th>Done</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($section_stats as $s): ?>
                            <tr>
                                <td><?php echo e($s['name']); ?></td>
                                <td><strong><?php echo $s['total']; ?></strong></td>
                                <td><?php echo $s['pending']; ?></td>
                                <td><?php echo $s['in_progress']; ?></td>
                                <td><?php echo $s['completed']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($section_stats)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No data yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-clock-history"></i> Recent Tasks</span>
                <a href="tasks.php" class="small">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Section</th>
                                <th>Assigned</th>
                                <th>Status</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tasks as $t): ?>
                            <tr>
                                <td>
                                    <a href="task-details.php?id=<?php echo $t['id']; ?>" class="text-decoration-none">
                                        <?php echo e($t['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($t['section_name']); ?></td>
                                <td><?php echo e($t['assigned_name']); ?></td>
                                <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                                <td><?php echo formatDate($t['due_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_tasks)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No tasks yet. Create one!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
