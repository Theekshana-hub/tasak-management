<?php
$page_title = 'Coordinator Dashboard';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

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


$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ? AND role = 'coordinator' LIMIT 1");
$stmt->execute([$coord_id]);
$coord_section_id = $stmt->fetchColumn();
$coord_section_id = $coord_section_id !== false && $coord_section_id !== null ? (int)$coord_section_id : null;


function deptTaskWhere($aliasT = 't', $aliasU = 'u') {
 
    return true; 
}

if ($coord_section_id) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE role = 'user' AND status = 'active' AND section_id = ?
    ");
    $stmt->execute([$coord_section_id]);
    $total_agents = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        WHERE t.assigned_to = ?
           OR (u.role = 'user' AND u.section_id = ?)
    ");
    $stmt->execute([$coord_id, $coord_section_id]);
    $total_tasks = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        WHERE (t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?))
          AND t.status = 'PENDING'
    ");
    $stmt->execute([$coord_id, $coord_section_id]);
    $pending = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        WHERE (t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?))
          AND t.status = 'IN_PROGRESS'
    ");
    $stmt->execute([$coord_id, $coord_section_id]);
    $in_progress = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        WHERE (t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?))
          AND t.status = 'COMPLETED'
    ");
    $stmt->execute([$coord_id, $coord_section_id]);
    $completed = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        WHERE (t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?))
          AND t.due_date < CURDATE()
          AND t.status NOT IN ('COMPLETED','CANCELLED')
    ");
    $stmt->execute([$coord_id, $coord_section_id]);
    $overdue = $stmt->fetchColumn();

    // Today tasks
    $stmt = $pdo->prepare("
        SELECT t.*, u.name AS assigned_name, s.name AS section_name,
               CASE WHEN t.assigned_to = ? THEN 1 ELSE 0 END AS is_mine
        FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        LEFT JOIN sections s ON s.id = t.section_id
        WHERE (t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?))
          AND (t.due_date = ? OR t.start_date = ?)
        ORDER BY is_mine DESC, t.status ASC, u.name ASC
    ");
    $stmt->execute([$coord_id, $coord_id, $coord_section_id, $today, $today]);
    $today_tasks = $stmt->fetchAll();


    $stmt = $pdo->prepare("
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
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE s.status = 'active'
          AND s.id = ?
          AND (t.id IS NULL OR t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?))
        GROUP BY s.id, s.name
        ORDER BY s.name ASC
    ");
    $stmt->execute([$coord_section_id, $coord_id, $coord_section_id]);
    $section_stats = $stmt->fetchAll();

    // Recent tasks
    $stmt = $pdo->prepare("
        SELECT t.*, u.name AS assigned_name, s.name AS section_name,
               CASE WHEN t.assigned_to = ? THEN 1 ELSE 0 END AS is_mine
        FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        LEFT JOIN sections s ON s.id = t.section_id
        WHERE t.assigned_to = ? OR (u.role = 'user' AND u.section_id = ?)
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$coord_id, $coord_id, $coord_section_id]);
    $recent = $stmt->fetchAll();


    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status IN ('PENDING','IN_PROGRESS')) AS active_tasks
        FROM users u
        WHERE u.role = 'user' AND u.section_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$coord_section_id]);
    $agents = $stmt->fetchAll();

} else {

    $total_agents = 0;
    $total_tasks = $pending = $in_progress = $completed = $overdue = 0;
    $today_tasks = [];
    $section_stats = [];
    $recent = [];
    $agents = [];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks WHERE assigned_to = ?
    ");
    $stmt->execute([$coord_id]);
    $total_tasks = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'PENDING'");
    $stmt->execute([$coord_id]);
    $pending = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'IN_PROGRESS'");
    $stmt->execute([$coord_id]);
    $in_progress = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'COMPLETED'");
    $stmt->execute([$coord_id]);
    $completed = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks 
        WHERE assigned_to = ? AND due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED')
    ");
    $stmt->execute([$coord_id]);
    $overdue = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT t.*, u.name AS assigned_name, s.name AS section_name, 1 AS is_mine
        FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        LEFT JOIN sections s ON s.id = t.section_id
        WHERE t.assigned_to = ? AND (t.due_date = ? OR t.start_date = ?)
        ORDER BY t.status ASC
    ");
    $stmt->execute([$coord_id, $today, $today]);
    $today_tasks = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT t.*, u.name AS assigned_name, s.name AS section_name, 1 AS is_mine
        FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        LEFT JOIN sections s ON s.id = t.section_id
        WHERE t.assigned_to = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$coord_id]);
    $recent = $stmt->fetchAll();
}

$today_total     = count($today_tasks);
$today_pending   = 0;
$today_progress  = 0;
$today_completed = 0;
$today_mine      = 0;

foreach ($today_tasks as $tt) {
    if ($tt['status'] === 'PENDING') $today_pending++;
    elseif ($tt['status'] === 'IN_PROGRESS') $today_progress++;
    elseif ($tt['status'] === 'COMPLETED') $today_completed++;
    if (!empty($tt['is_mine'])) $today_mine++;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-speedometer2"></i> Coordinator Dashboard</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Assign Task</a>
</div>

<?php if (!$coord_section_id): ?>
<div class="alert alert-warning mb-4">
    No department/section assigned to you. Only your own tasks are shown. Please contact Admin.
</div>
<?php endif; ?>

<!-- Overall Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Department Agents</div>
                <div class="fs-4 fw-bold"><?= (int)$total_agents ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">All Tasks</div>
                <div class="fs-4 fw-bold"><?= (int)$total_tasks ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Pending</div>
                <div class="fs-4 fw-bold text-warning"><?= (int)$pending ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">In Progress</div>
                <div class="fs-4 fw-bold text-primary"><?= (int)$in_progress ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Completed</div>
                <div class="fs-4 fw-bold text-success"><?= (int)$completed ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Overdue</div>
                <div class="fs-4 fw-bold text-danger"><?= (int)$overdue ?></div>
            </div>
        </div>
    </div>
</div>


<div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">
            <i class="bi bi-calendar-day text-warning"></i>
            Today's Daily Tasks
            <small class="text-muted fw-normal">(<?= date('d M Y') ?>)</small>
        </span>
        <div class="d-flex align-items-center gap-2">
            <?php if ($today_mine > 0): ?>
                <span class="badge bg-dark">My tasks: <?= $today_mine ?></span>
            <?php endif; ?>
            <a href="tasks.php" class="small">View All Tasks</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 bg-light border text-center h-100">
                    <div class="fs-4 fw-bold"><?= $today_total ?></div>
                    <div class="small text-muted">Total Today</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-center h-100">
                    <div class="fs-4 fw-bold text-warning"><?= $today_pending ?></div>
                    <div class="small text-muted">Pending</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 text-center h-100">
                    <div class="fs-4 fw-bold text-primary"><?= $today_progress ?></div>
                    <div class="small text-muted">In Progress</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 text-center h-100">
                    <div class="fs-4 fw-bold text-success"><?= $today_completed ?></div>
                    <div class="small text-muted">Completed</div>
                </div>
            </div>
        </div>

        <?php if (empty($today_tasks)): ?>
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                <p class="text-muted mb-0">No tasks due today.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Task</th>
                            <th>Day</th>
                            <th>Assigned To</th>
                            <th>Section</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_tasks as $t):
                            $dayInfo = parseDayLabelDash($t['title'] ?? '');
                            $isMine  = !empty($t['is_mine']);
                        ?>
                        <tr class="<?= $isMine ? 'table-warning' : '' ?>">
                            <td>
                                <a href="task-details.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= e($dayInfo ? $dayInfo['base'] : $t['title']) ?>
                                </a>
                                <?php if ($isMine): ?>
                                    <span class="badge bg-dark ms-1">Me</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dayInfo): ?>
                                    <span class="badge bg-primary">Day <?= $dayInfo['day'] ?>/<?= $dayInfo['total'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">1 Day</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($t['assigned_name']) ?>
                                <?php if ($isMine): ?>
                                    <small class="text-muted">(You)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($t['section_name'] ?? '—') ?></td>
                            <td><?= priorityBadge($t['priority']) ?></td>
                            <td><?= statusBadge($t['status'], $t['due_date']) ?></td>
                            <td>
                                <a href="task-details.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


<div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-diagram-3 text-info"></i>
            Section-wise Task Breakdown
            <small class="text-muted fw-normal">(<?= date('d M Y') ?>)</small>
        </span>
        <span class="badge bg-info text-dark"><?= count($section_stats) ?> Section<?= count($section_stats) === 1 ? '' : 's' ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($section_stats)): ?>
            <p class="text-muted text-center mb-0 py-4">No section found for your account.</p>
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
                                <h6 class="fw-bold mb-0"><?= e($sec['section_name']) ?></h6>
                                <?php if ($secOverdue > 0): ?>
                                    <span class="badge bg-danger"><?= $secOverdue ?> overdue</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($secTotal === 0): ?>
                                <p class="text-muted small mb-0 py-3 text-center">No tasks today.</p>
                            <?php else: ?>
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Today's Progress</span>
                                    <span><?= $secCompleted ?> / <?= $secTotal ?> (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <div class="row g-2 text-center">
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold"><?= $secTotal ?></div>
                                            <div class="small text-muted" style="font-size:0.7rem;">Total</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold text-warning"><?= $secPending ?></div>
                                            <div class="small text-muted" style="font-size:0.7rem;">Pending</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold text-primary"><?= $secProgress ?></div>
                                            <div class="small text-muted" style="font-size:0.7rem;">Doing</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-1 rounded bg-light">
                                            <div class="fw-bold text-success"><?= $secCompleted ?></div>
                                            <div class="small text-muted" style="font-size:0.7rem;">Done</div>
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

<div class="row g-4">
    <!-- Department Agents -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-people"></i> Department Agents</span>
                <a href="agents.php" class="small">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($agents)): ?>
                        <div class="list-group-item text-muted text-center py-4">No agents in your department.</div>
                    <?php else: ?>
                        <?php foreach ($agents as $a): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e($a['name']) ?></strong>
                                <br><small class="text-muted"><?= e($a['email']) ?></small>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?= (int)$a['active_tasks'] ?> active</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-list-task"></i> Recent Tasks</span>
                <a href="tasks.php" class="small">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No tasks yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $t):
                                $dayInfo = parseDayLabelDash($t['title'] ?? '');
                                $isMine  = !empty($t['is_mine']);
                            ?>
                            <tr class="<?= $isMine ? 'table-warning' : '' ?>">
                                <td>
                                    <a href="task-details.php?id=<?= (int)$t['id'] ?>">
                                        <?= e($dayInfo ? $dayInfo['base'] : $t['title']) ?>
                                    </a>
                                    <?php if ($dayInfo): ?>
                                        <span class="badge bg-primary ms-1">Day <?= $dayInfo['day'] ?>/<?= $dayInfo['total'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($isMine): ?>
                                        <span class="badge bg-dark ms-1">Me</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($t['assigned_name']) ?>
                                    <?php if ($isMine): ?>
                                        <small class="text-muted">(You)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= priorityBadge($t['priority']) ?></td>
                                <td><?= statusBadge($t['status'], $t['due_date']) ?></td>
                                <td><?= formatDate($t['due_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>