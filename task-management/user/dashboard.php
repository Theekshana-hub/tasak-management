<?php
$page_title = 'My Dashboard';
require_once '../includes/user_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

function parseDayLabelUser($title) {
    if (preg_match('/\(Day\s*(\d+)\s*\/\s*(\d+)\s*[–\-]\s*([^)]+)\)/i', $title ?? '', $m)) {
        return [
            'day'   => (int)$m[1],
            'total' => (int)$m[2],
            'base'  => trim(preg_replace('/\s*\(Day\s*\d+\s*\/\s*\d+\s*[–\-]\s*[^)]+\)\s*/i', '', $title))
        ];
    }
    return null;
}

// Overall stats
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

// TODAY's tasks
$stmt = $pdo->prepare("
    SELECT t.*, s.name AS section_name
    FROM tasks t
    JOIN sections s ON s.id = t.section_id
    WHERE t.assigned_to = ?
      AND (t.due_date = ? OR t.start_date = ?)
    ORDER BY 
        CASE t.status
            WHEN 'PENDING' THEN 1
            WHEN 'IN_PROGRESS' THEN 2
            WHEN 'COMPLETED' THEN 3
            ELSE 4
        END,
        t.id ASC
");
$stmt->execute([$user_id, $today, $today]);
$today_tasks = $stmt->fetchAll();

$today_total     = count($today_tasks);
$today_pending   = 0;
$today_progress  = 0;
$today_completed = 0;

foreach ($today_tasks as $tt) {
    if ($tt['status'] === 'PENDING') $today_pending++;
    elseif ($tt['status'] === 'IN_PROGRESS') $today_progress++;
    elseif ($tt['status'] === 'COMPLETED') $today_completed++;
}

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
    <a href="my-tasks.php" class="btn btn-outline-primary btn-sm">View All Tasks</a>
</div>

<!-- Overall Stats -->
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

<!-- ========== TODAY'S DAILY TASKS ========== -->
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-calendar-day text-warning"></i>
            Today's Tasks
            <small class="text-muted fw-normal">(<?php echo date('d M Y'); ?>)</small>
        </span>
        <a href="my-tasks.php" class="small">View All</a>
    </div>
    <div class="card-body">
        <!-- Today counts -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light text-center">
                    <div class="fs-5 fw-bold"><?php echo $today_total; ?></div>
                    <div class="small text-muted">Total Today</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light text-center">
                    <div class="fs-5 fw-bold text-warning"><?php echo $today_pending; ?></div>
                    <div class="small text-muted">Pending</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light text-center">
                    <div class="fs-5 fw-bold text-primary"><?php echo $today_progress; ?></div>
                    <div class="small text-muted">In Progress</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light text-center">
                    <div class="fs-5 fw-bold text-success"><?php echo $today_completed; ?></div>
                    <div class="small text-muted">Completed</div>
                </div>
            </div>
        </div>

        <?php if (empty($today_tasks)): ?>
            <p class="text-muted text-center mb-0 py-3">No tasks for today. Great job staying clear!</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Day</th>
                            <th>Section</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_tasks as $t):
                            $dayInfo = parseDayLabelUser($t['title'] ?? '');
                        ?>
                        <tr class="<?php echo $t['status'] !== 'COMPLETED' ? 'table-warning' : ''; ?>">
                            <td>
                                <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="text-decoration-none fw-semibold">
                                    <?php echo e($dayInfo ? $dayInfo['base'] : $t['title']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($dayInfo): ?>
                                    <span class="badge bg-primary">Day <?php echo $dayInfo['day']; ?>/<?php echo $dayInfo['total']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">1 Day</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($t['section_name']); ?></td>
                            <td><?php echo priorityBadge($t['priority']); ?></td>
                            <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                            <td>
                                <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <?php echo $t['status'] === 'COMPLETED' ? 'View' : 'Open'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Tasks -->
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
                    <th>Day</th>
                    <th>Section</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $t):
                    $dayInfo = parseDayLabelUser($t['title'] ?? '');
                ?>
                <tr>
                    <td>
                        <?php echo e($dayInfo ? $dayInfo['base'] : $t['title']); ?>
                    </td>
                    <td>
                        <?php if ($dayInfo): ?>
                            <span class="badge bg-primary">Day <?php echo $dayInfo['day']; ?>/<?php echo $dayInfo['total']; ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">1 Day</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($t['section_name']); ?></td>
                    <td><?php echo priorityBadge($t['priority']); ?></td>
                    <td><?php echo formatDate($t['due_date']); ?></td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No tasks assigned to you yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>