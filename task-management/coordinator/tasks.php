<?php
$page_title = 'Team Tasks';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// ===== Coordinator own section =====
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ? AND role = 'coordinator' LIMIT 1");
$stmt->execute([$coord_id]);
$coord_section_id = $stmt->fetchColumn();
$coord_section_id = ($coord_section_id !== false && $coord_section_id !== null) ? (int)$coord_section_id : null;

// ===== Allowed sections = own + granted =====
$allowed_section_ids = [];
if ($coord_section_id) {
    $allowed_section_ids[] = $coord_section_id;
}
$acc = $pdo->prepare("SELECT section_id FROM coordinator_section_access WHERE coordinator_id = ?");
$acc->execute([$coord_id]);
foreach ($acc->fetchAll(PDO::FETCH_COLUMN) as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && !in_array($sid, $allowed_section_ids, true)) {
        $allowed_section_ids[] = $sid;
    }
}

// Filters
$status_filter  = trim($_GET['status'] ?? '');
$section_filter = (int)($_GET['section_id'] ?? 0);
$q              = trim($_GET['q'] ?? '');

$valid_statuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'overdue'];

$tasks = [];
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];
$today_count = 0;

if (!empty($allowed_section_ids)) {
    $ph = implode(',', array_fill(0, count($allowed_section_ids), '?'));

    $where  = ["t.section_id IN ($ph)"];
    $params = $allowed_section_ids;

    if ($status_filter === 'overdue') {
        $where[] = "t.due_date < CURDATE() AND t.status NOT IN ('COMPLETED','CANCELLED')";
    } elseif ($status_filter !== '' && in_array($status_filter, $valid_statuses, true)) {
        $where[] = "t.status = ?";
        $params[] = $status_filter;
    }

    if ($section_filter > 0 && in_array($section_filter, $allowed_section_ids, true)) {
        $where[] = "t.section_id = ?";
        $params[] = $section_filter;
    }

    if ($q !== '') {
        $where[] = "(t.title LIKE ? OR u.name LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    // අද add කරපු / අද due / අද start → උඩින්
    $sql = "
        SELECT t.*, u.name AS assigned_name, s.name AS section_name,
               CASE
                   WHEN DATE(t.created_at) = CURDATE() THEN 0
                   WHEN t.due_date = CURDATE() OR t.start_date = CURDATE() THEN 1
                   ELSE 2
               END AS sort_today
        FROM tasks t
        LEFT JOIN users u ON u.id = t.assigned_to
        LEFT JOIN sections s ON s.id = t.section_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            sort_today ASC,
            t.created_at DESC,
            CASE t.status
                WHEN 'PENDING' THEN 1
                WHEN 'IN_PROGRESS' THEN 2
                WHEN 'COMPLETED' THEN 3
                ELSE 4
            END,
            t.due_date ASC,
            t.id DESC
        LIMIT 300
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    foreach ($tasks as $t) {
        $created = !empty($t['created_at']) ? date('Y-m-d', strtotime($t['created_at'])) : '';
        if ($created === $today) {
            $today_count++;
        }
    }

    $st = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED') THEN 1 ELSE 0 END) AS overdue
        FROM tasks
        WHERE section_id IN ($ph)
    ");
    $st->execute($allowed_section_ids);
    $row = $st->fetch();
    if ($row) {
        $stats = [
            'total'       => (int)$row['total'],
            'pending'     => (int)$row['pending'],
            'in_progress' => (int)$row['in_progress'],
            'completed'   => (int)$row['completed'],
            'overdue'     => (int)$row['overdue'],
        ];
    }
}

$sections = [];
if (!empty($allowed_section_ids)) {
    $ph = implode(',', array_fill(0, count($allowed_section_ids), '?'));
    $st = $pdo->prepare("SELECT id, name FROM sections WHERE status = 'active' AND id IN ($ph) ORDER BY name");
    $st->execute($allowed_section_ids);
    $sections = $st->fetchAll();
}

function parseDayLabel($title) {
    if (preg_match('/\(Day\s*(\d+)\s*\/\s*(\d+)\s*[–\-]\s*([^)]+)\)/i', $title ?? '', $m)) {
        return [
            'day'   => (int)$m[1],
            'total' => (int)$m[2],
            'base'  => trim(preg_replace('/\s*\(Day\s*\d+\s*\/\s*\d+\s*[–\-]\s*[^)]+\)\s*/i', '', $title))
        ];
    }
    return null;
}

function isAddedToday($t, $today) {
    if (!empty($t['created_at']) && date('Y-m-d', strtotime($t['created_at'])) === $today) {
        return true;
    }
    return false;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> Team Tasks</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Assign Task</a>
</div>

<?php if ($today_count > 0): ?>
<div class="alert alert-warning border-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-sun-fill fs-5"></i>
    <div>
        <strong><?php echo $today_count; ?></strong> task(s) added today — shown at the top and highlighted in yellow.
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md">
        <div class="card stat-card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Total</div>
                <div class="fs-4 fw-bold"><?php echo $stats['total']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Pending</div>
                <div class="fs-4 fw-bold text-warning"><?php echo $stats['pending']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">In Progress</div>
                <div class="fs-4 fw-bold text-primary"><?php echo $stats['in_progress']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Completed</div>
                <div class="fs-4 fw-bold text-success"><?php echo $stats['completed']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Overdue</div>
                <div class="fs-4 fw-bold text-danger"><?php echo $stats['overdue']; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Task or agent..." value="<?php echo e($q); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Department</label>
                <select name="section_id" class="form-select">
                    <option value="">All allowed</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo $section_filter === (int)$s['id'] ? 'selected' : ''; ?>>
                        <?php echo e($s['name']); ?>
                        <?php if ($coord_section_id && (int)$s['id'] === $coord_section_id): ?> (My dept)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="PENDING" <?php echo $status_filter === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                    <option value="IN_PROGRESS" <?php echo $status_filter === 'IN_PROGRESS' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="COMPLETED" <?php echo $status_filter === 'COMPLETED' ? 'selected' : ''; ?>>Completed</option>
                    <option value="CANCELLED" <?php echo $status_filter === 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-navy text-white"><i class="bi bi-funnel"></i> Filter</button>
                <a href="tasks.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Task list -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table"></i> Tasks (<?php echo count($tasks); ?>)</span>
        <?php if ($today_count > 0): ?>
            <span class="badge bg-warning text-dark"><?php echo $today_count; ?> added today</span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Day</th>
                    <th>Department</th>
                    <th>Assigned To</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        No tasks found in your allowed departments.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t):
                        $dayInfo   = parseDayLabel($t['title'] ?? '');
                        $addedToday = isAddedToday($t, $today);
                        $isOverdue = !empty($t['due_date'])
                            && $t['due_date'] < $today
                            && !in_array($t['status'], ['COMPLETED', 'CANCELLED'], true);

                        // Yellow for today-added; red only if overdue and NOT added today
                        $rowClass = '';
                        if ($addedToday) {
                            $rowClass = 'table-warning';
                        } elseif ($isOverdue) {
                            $rowClass = 'table-danger';
                        }
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td class="fw-semibold">
                            <?php if ($addedToday): ?>
                                <span class="badge bg-warning text-dark me-1">NEW</span>
                            <?php endif; ?>
                            <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="text-decoration-none text-dark">
                                <?php echo e($dayInfo ? $dayInfo['base'] : ($t['title'] ?? '-')); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($dayInfo): ?>
                                <span class="badge bg-primary">Day <?php echo $dayInfo['day']; ?>/<?php echo $dayInfo['total']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">1 Day</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo e($t['section_name'] ?? '-'); ?>
                            <?php if ($coord_section_id && (int)$t['section_id'] === $coord_section_id): ?>
                                <span class="badge bg-light text-dark border">My dept</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">Other dept</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($t['assigned_name'] ?? '-'); ?></td>
                        <td>
                            <?php
                            if (function_exists('priorityBadge')) {
                                echo priorityBadge($t['priority']);
                            } else {
                                $pClass = match(strtoupper($t['priority'] ?? '')) {
                                    'HIGH', 'URGENT' => 'danger', 'MEDIUM' => 'warning', 'LOW' => 'success', default => 'secondary'
                                };
                                echo '<span class="badge bg-' . $pClass . '">' . e($t['priority'] ?? '-') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($isOverdue && !$addedToday) {
                                echo '<span class="badge bg-danger">OVERDUE</span>';
                            } elseif (function_exists('statusBadge')) {
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
                            echo !empty($t['due_date']) ? e(date('d M Y', strtotime($t['due_date']))) : '-';
                            ?>
                        </td>
                        <td class="text-nowrap">
                            <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="edit-task.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>