<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Task updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php
$page_title = 'Team Tasks';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

$search    = trim($_GET['search'] ?? '');
$status    = $_GET['status'] ?? '';
$agent     = $_GET['agent'] ?? '';
$day_group = $_GET['day_group'] ?? '';

function parseDayLabel($title) {
    if (preg_match('/\(Day\s*(\d+)\s*\/\s*(\d+)\s*[–\-]\s*([^)]+)\)/i', $title ?? '', $m)) {
        return [
            'day'   => (int)$m[1],
            'total' => (int)$m[2],
            'date'  => trim($m[3]),
            'base'  => trim(preg_replace('/\s*\(Day\s*\d+\s*\/\s*\d+\s*[–\-]\s*[^)]+\)\s*/i', '', $title))
        ];
    }
    return null;
}

$sql = "
    SELECT t.*, u.name AS assigned_name, s.name AS section_name
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    JOIN sections s ON s.id = t.section_id
    WHERE u.coordinator_id = ?
";
$params = [$coord_id];

if ($search !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}
if ($agent !== '') {
    $sql .= " AND t.assigned_to = ?";
    $params[] = (int)$agent;
}

$sql .= " ORDER BY DATE(t.created_at) DESC, t.due_date ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_tasks = $stmt->fetchAll();

$group_counts = ['1' => 0, '3' => 0, '5' => 0, '7' => 0, '14' => 0, 'other' => 0];
$tasks = [];

foreach ($all_tasks as $t) {
    $info = parseDayLabel($t['title'] ?? '');
    $total = $info ? (string)$info['total'] : '1';

    if (!isset($group_counts[$total])) {
        $group_counts['other']++;
        $total_key = 'other';
    } else {
        $group_counts[$total]++;
        $total_key = $total;
    }

    if ($day_group === '') {
        $tasks[] = $t;
    } elseif ($day_group === 'other' && $total_key === 'other') {
        $tasks[] = $t;
    } elseif ($day_group === $total_key) {
        $tasks[] = $t;
    }
}

$agents_stmt = $pdo->prepare("SELECT id, name FROM users WHERE coordinator_id = ? AND role = 'user' ORDER BY name");
$agents_stmt->execute([$coord_id]);
$agents = $agents_stmt->fetchAll();

$today = date('Y-m-d');

function dayGroupUrlCoord($group, $search, $status, $agent) {
    $q = [];
    if ($group !== '') $q['day_group'] = $group;
    if ($search !== '') $q['search'] = $search;
    if ($status !== '') $q['status'] = $status;
    if ($agent !== '') $q['agent'] = $agent;
    return 'tasks.php' . ($q ? ('?' . http_build_query($q)) : '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> Team Tasks</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Assign Task</a>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Task deleted successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php
    switch ($_GET['error']) {
        case 'not_found':
            echo 'Task not found.';
            break;
        case 'not_allowed':
            echo 'You are not allowed to delete this task.';
            break;
        case 'delete_failed':
            echo 'Could not delete task — it may have related records.';
            break;
        case 'invalid_id':
            echo 'Invalid task ID.';
            break;
        default:
            echo 'Something went wrong.';
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Day Group Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlCoord('', $search, $status, $agent)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group===''?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-dark"><?php echo count($all_tasks); ?></div>
                    <div class="small text-muted">All Tasks</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlCoord('1', $search, $status, $agent)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='1'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-primary"><?php echo (int)$group_counts['1']; ?></div>
                    <div class="small text-muted">1 Day</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlCoord('3', $search, $status, $agent)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='3'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-info"><?php echo (int)$group_counts['3']; ?></div>
                    <div class="small text-muted">3 Days</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlCoord('5', $search, $status, $agent)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='5'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-secondary"><?php echo (int)$group_counts['5']; ?></div>
                    <div class="small text-muted">5 Days</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlCoord('7', $search, $status, $agent)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='7'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-success"><?php echo (int)$group_counts['7']; ?></div>
                    <div class="small text-muted">7 Days (Week)</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlCoord('14', $search, $status, $agent)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='14'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-danger"><?php echo (int)$group_counts['14']; ?></div>
                    <div class="small text-muted">14 Days</div>
                </div>
            </div>
        </a>
    </div>
</div>

<?php if ($day_group !== ''): ?>
<div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center">
    <span>
        Showing: <strong>
            <?php
            if ($day_group === '1') echo '1 Day tasks';
            elseif ($day_group === '7') echo '7 Days (Week) tasks';
            else echo $day_group . ' Days tasks';
            ?>
        </strong>
        (<?php echo count($tasks); ?>)
    </span>
    <a href="tasks.php" class="btn btn-sm btn-outline-secondary">Show All</a>
</div>
<?php endif; ?>

<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <?php if ($day_group !== ''): ?>
            <input type="hidden" name="day_group" value="<?php echo e($day_group); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="agent" class="form-select">
                    <option value="">All Agents</option>
                    <?php foreach ($agents as $a): ?>
                    <option value="<?php echo $a['id']; ?>" <?php echo $agent == $a['id'] ? 'selected' : ''; ?>><?php echo e($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="PENDING" <?php echo $status==='PENDING'?'selected':''; ?>>Pending</option>
                    <option value="IN_PROGRESS" <?php echo $status==='IN_PROGRESS'?'selected':''; ?>>In Progress</option>
                    <option value="COMPLETED" <?php echo $status==='COMPLETED'?'selected':''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-navy bg-navy text-white">Filter</button>
                <a href="tasks.php<?php echo $day_group!==''?'?day_group='.urlencode($day_group):''; ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Assigned On</th>
                    <th>Task</th>
                    <th>Day</th>
                    <th>Agent</th>
                    <th>Section</th>
                    <th>Priority</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t):
                    $dayInfo = parseDayLabel($t['title'] ?? '');
                    $isToday = (!empty($t['due_date']) && $t['due_date'] === $today);
                ?>
                <tr class="<?php echo $isToday ? 'table-warning' : ''; ?>">
                    <td>
                        <?php echo !empty($t['created_at']) ? formatDate($t['created_at']) : '—'; ?>
                    </td>
                    <td>
                        <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="text-decoration-none fw-semibold">
                            <?php echo e($dayInfo ? $dayInfo['base'] : $t['title']); ?>
                        </a>
                        <?php if ($isToday): ?>
                            <span class="badge bg-warning text-dark ms-1">Today</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($dayInfo): ?>
                            <span class="badge bg-primary">Day <?php echo $dayInfo['day']; ?>/<?php echo $dayInfo['total']; ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">1 Day</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($t['assigned_name']); ?></td>
                    <td><?php echo e($t['section_name']); ?></td>
                    <td><?php echo priorityBadge($t['priority']); ?></td>
                    <td><?php echo formatDate($t['due_date'] ?? $t['start_date'] ?? null); ?></td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="edit-task.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="delete-task.php?id=<?php echo (int)$t['id']; ?>"
                           class="btn btn-sm btn-outline-danger"
                           title="Delete"
                           onclick="return confirm('Delete this task? This cannot be undone.');">
                           <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No tasks found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>