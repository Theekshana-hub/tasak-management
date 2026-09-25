<?php
$page_title = 'Admin Dashboard';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$today = date('Y-m-d');

// Helper: parse Day X/Y from title
function parseDayLabelDash($title) {
    if (preg_match('/\(Day\s*(\d+)\s*\/\s*(\d+)\s*[–\-]\s*([^)]+)\)/i', $title ?? '', $m)) {
        return [
            'day'   => (int)$m[1],
            'total' => (int)$m[2],
            'base'  => trim(preg_replace('/\s*\(Day\s*\d+\s*\/\s*\d+\s*[–\-]\s*[^)]+\)\s*/i', '', $title))
        ];
    }
    return null;
}

// ===== Overall Stats =====
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$pending     = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'PENDING'")->fetchColumn();
$in_progress = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'IN_PROGRESS'")->fetchColumn();
$completed   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'COMPLETED'")->fetchColumn();
$cancelled   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'CANCELLED'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// Overdue count
$overdue = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED')")->fetchColumn();

// ===== TODAY's Tasks =====
$today_tasks = $pdo->query("
    SELECT t.*, u.name AS assigned_name, s.name AS section_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to
    LEFT JOIN sections s ON s.id = t.section_id
    WHERE t.due_date = '$today' OR t.start_date = '$today'
    ORDER BY t.status ASC, u.name ASC
")->fetchAll();

$today_total     = count($today_tasks);
$today_pending   = 0;
$today_progress  = 0;
$today_completed = 0;
foreach ($today_tasks as $tt) {
    if ($tt['status'] === 'PENDING') $today_pending++;
    elseif ($tt['status'] === 'IN_PROGRESS') $today_progress++;
    elseif ($tt['status'] === 'COMPLETED') $today_completed++;
}

// ===== OVERDUE Tasks (list) =====
$overdue_tasks = $pdo->query("
    SELECT t.*, u.name AS assigned_name, s.name AS section_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to
    LEFT JOIN sections s ON s.id = t.section_id
    WHERE t.due_date < CURDATE() 
      AND t.status NOT IN ('COMPLETED','CANCELLED')
    ORDER BY t.due_date ASC
    LIMIT 10
")->fetchAll();

// ===== Section-wise Breakdown (TODAY ONLY) =====
// Join condition itself is restricted to today's tasks (due_date OR start_date = today),
// so every count below reflects only today's tasks per section.
$section_stats = $pdo->query("
    SELECT
        s.id,
        s.name AS section_name,
        COUNT(t.id) AS total_tasks,
        SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS progress_count,
        SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN t.due_date < CURDATE() AND t.status NOT IN ('COMPLETED','CANCELLED') THEN 1 ELSE 0 END) AS overdue_count
    FROM sections s
    LEFT JOIN tasks t 
        ON t.section_id = s.id 
       AND (t.due_date = CURDATE() OR t.start_date = CURDATE())
    WHERE s.status = 'active'
    GROUP BY s.id, s.name
    ORDER BY s.name ASC
")->fetchAll();

// Recent tasks
$recent_tasks = $pdo->query("
    SELECT t.*, u.name AS assigned_name, s.name AS section_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to
    LEFT JOIN sections s ON s.id = t.section_id
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

<!-- ===== TODAY'S TASKS ===== -->
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-calendar-day text-warning"></i>
            Today's Tasks
            <small class="text-muted fw-normal">(<?php echo date('d M Y'); ?>)</small>
        </span>
        <a href="tasks.php" class="small">View All</a>
    </div>
    <div class="card-body">
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
            <p class="text-muted text-center mb-0 py-3">No tasks due today.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Day</th>
                            <th>Agent</th>
                            <th>Section</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_tasks as $t):
                            $dayInfo = parseDayLabelDash($t['title'] ?? '');
                            $isToday = (!empty($t['due_date']) && $t['due_date'] === $today);
                        ?>
                        <tr class="<?php echo $isToday ? 'table-warning' : ''; ?>">
                            <td class="fw-semibold"><?php echo e($dayInfo ? $dayInfo['base'] : ($t['title'] ?? '-')); ?></td>
                            <td>
                                <?php if ($dayInfo): ?>
                                    <span class="badge bg-primary">Day <?php echo $dayInfo['day']; ?>/<?php echo $dayInfo['total']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">1 Day</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($t['assigned_name'] ?? '-'); ?></td>
                            <td><?php echo e($t['section_name'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $pClass = match($t['priority'] ?? '') {
                                    'High' => 'danger', 'Medium' => 'warning', 'Low' => 'success', default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $pClass; ?>"><?php echo e($t['priority'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <?php
                                $sClass = match($t['status'] ?? '') {
                                    'COMPLETED' => 'success', 'IN_PROGRESS' => 'primary', 'CANCELLED' => 'secondary', default => 'warning'
                                };
                                ?>
                                <span class="badge bg-<?php echo $sClass; ?>"><?php echo e($t['status'] ?? '-'); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== SECTION-WISE BREAKDOWN (TODAY ONLY) ===== -->
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-diagram-3 text-info"></i>
            Section-wise Task Breakdown
            <small class="text-muted fw-normal">(<?php echo date('d M Y'); ?>)</small>
        </span>
        <span class="badge bg-info text-dark"><?php echo count($section_stats); ?> Sections</span>
    </div>
    <div class="card-body">
        <?php if (empty($section_stats)): ?>
            <p class="text-muted text-center mb-0 py-4">No sections found.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($section_stats as $sec):
                    $secTotal     = (int)$sec['total_tasks'];
                    $secPending   = (int)$sec['pending_count'];
                    $secProgress  = (int)$sec['progress_count'];
                    $secCompleted = (int)$sec['completed_count'];
                    $secOverdue   = (int)$sec['overdue_count'];
                    $pct = $secTotal > 0 ? round(($secCompleted / $secTotal) * 100) : 0;
                ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100 border">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0"><?php echo e($sec['section_name']); ?></h6>
                                <?php if ($secOverdue > 0): ?>
                                    <span class="badge bg-danger"><?php echo $secOverdue; ?> overdue</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($secTotal === 0): ?>
                                <p class="text-muted small mb-0 py-3 text-center">No tasks today.</p>
                            <?php else: ?>
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Today's Progress</span>
                                    <span><?php echo $secCompleted; ?> / <?php echo $secTotal; ?> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar"
                                         style="width: <?php echo $pct; ?>%;"
                                         aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="row g-2 text-center">
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold"><?php echo $secTotal; ?></div>
                                            <div class="small text-muted" style="font-size: 0.7rem;">Total</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold text-warning"><?php echo $secPending; ?></div>
                                            <div class="small text-muted" style="font-size: 0.7rem;">Pending</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold text-primary"><?php echo $secProgress; ?></div>
                                            <div class="small text-muted" style="font-size: 0.7rem;">Doing</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold text-success"><?php echo $secCompleted; ?></div>
                                            <div class="small text-muted" style="font-size: 0.7rem;">Done</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== OVERDUE TASKS ===== -->
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-danger">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-exclamation-triangle text-danger"></i>
            Overdue Tasks
            <span class="badge bg-danger ms-1"><?php echo $overdue; ?></span>
        </span>
        <a href="tasks.php?status=overdue" class="small">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($overdue_tasks)): ?>
            <p class="text-muted text-center mb-0 py-4">No overdue tasks. Great job!</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Agent</th>
                            <th>Section</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_tasks as $t):
                            $dayInfo = parseDayLabelDash($t['title'] ?? '');
                        ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($dayInfo ? $dayInfo['base'] : ($t['title'] ?? '-')); ?></td>
                            <td><?php echo e($t['assigned_name'] ?? '-'); ?></td>
                            <td><?php echo e($t['section_name'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $pClass = match($t['priority'] ?? '') {
                                    'High' => 'danger', 'Medium' => 'warning', 'Low' => 'success', default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $pClass; ?>"><?php echo e($t['priority'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-danger">OVERDUE</span>
                            </td>
                            <td class="text-danger fw-semibold">
                                <?php echo date('d M Y', strtotime($t['due_date'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Tasks -->
    <div class="col-12">
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
                            <?php foreach ($recent_tasks as $t):
                                $dayInfo = parseDayLabelDash($t['title'] ?? '');
                                $isTodayRecent = (!empty($t['due_date']) && $t['due_date'] === $today);
                            ?>
                            <tr class="<?php echo $isTodayRecent ? 'table-warning' : ''; ?>">
                                <td>
                                    <a href="task-details.php?id=<?php echo $t['id']; ?>" class="text-decoration-none">
                                        <?php echo e($dayInfo ? $dayInfo['base'] : ($t['title'] ?? '-')); ?>
                                    </a>
                                    <?php if ($dayInfo): ?>
                                        <span class="badge bg-primary ms-1">Day <?php echo $dayInfo['day']; ?>/<?php echo $dayInfo['total']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($isTodayRecent): ?>
                                        <span class="badge bg-warning text-dark ms-1">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($t['section_name'] ?? '-'); ?></td>
                                <td><?php echo e($t['assigned_name'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    if (function_exists('statusBadge')) {
                                        echo statusBadge($t['status'], $t['due_date']);
                                    } else {
                                        $sClass = match($t['status'] ?? '') {
                                            'COMPLETED' => 'success', 'IN_PROGRESS' => 'primary', 'CANCELLED' => 'secondary', default => 'warning'
                                        };
                                        echo '<span class="badge bg-' . $sClass . '">' . e($t['status'] ?? '-') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if (function_exists('formatDate')) {
                                        echo formatDate($t['due_date']);
                                    } else {
                                        echo !empty($t['due_date']) ? date('d M Y', strtotime($t['due_date'])) : '-';
                                    }
                                    ?>
                                </td>
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