<?php
$page_title = 'My Tasks';
require_once '../includes/user_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];

$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$section  = $_GET['section'] ?? '';
$day_group = $_GET['day_group'] ?? ''; // 1, 3, 5, 7, 14, or ''

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

// All tasks for this user (filters except day_group)
$sql = "SELECT t.*, 
               s.name AS section_name,
               c.name AS assigned_by_name,
               c.role AS assigned_by_role
        FROM tasks t
        JOIN sections s ON s.id = t.section_id
        JOIN users c ON c.id = t.created_by
        WHERE t.assigned_to = ?";
$params = [$user_id];

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
if ($priority !== '') {
    $sql .= " AND t.priority = ?";
    $params[] = $priority;
}
if ($section !== '') {
    $sql .= " AND t.section_id = ?";
    $params[] = (int)$section;
}

$sql .= " ORDER BY DATE(t.created_at) DESC, t.due_date ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_tasks = $stmt->fetchAll();

// Group counts + filter by day_group
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

    // Filter
    if ($day_group === '') {
        $tasks[] = $t;
    } elseif ($day_group === 'other' && $total_key === 'other') {
        $tasks[] = $t;
    } elseif ($day_group === $total_key) {
        $tasks[] = $t;
    }
}

$sections = $pdo->query("SELECT id, name FROM sections WHERE status='active' ORDER BY name")->fetchAll();
$today = date('Y-m-d');

// Helper for card link
function dayGroupUrl($group, $search, $status, $priority, $section) {
    $q = ['day_group' => $group];
    if ($search !== '') $q['search'] = $search;
    if ($status !== '') $q['status'] = $status;
    if ($priority !== '') $q['priority'] = $priority;
    if ($section !== '') $q['section'] = $section;
    return 'my-tasks.php?' . http_build_query($q);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> My Tasks</h2>
</div>

<!-- ========== Day Group Cards ========== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <a href="my-tasks.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group===''?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-dark"><?php echo count($all_tasks); ?></div>
                    <div class="small text-muted">All Tasks</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrl('1', $search, $status, $priority, $section)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='1'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-primary"><?php echo (int)$group_counts['1']; ?></div>
                    <div class="small text-muted">1 Day</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrl('3', $search, $status, $priority, $section)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='3'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-info"><?php echo (int)$group_counts['3']; ?></div>
                    <div class="small text-muted">3 Days</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrl('5', $search, $status, $priority, $section)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='5'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-secondary"><?php echo (int)$group_counts['5']; ?></div>
                    <div class="small text-muted">5 Days</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrl('7', $search, $status, $priority, $section)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='7'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-success"><?php echo (int)$group_counts['7']; ?></div>
                    <div class="small text-muted">7 Days (Week)</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrl('14', $search, $status, $priority, $section)); ?>" class="text-decoration-none">
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
    <a href="my-tasks.php" class="btn btn-sm btn-outline-secondary">Show All</a>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <?php if ($day_group !== ''): ?>
            <input type="hidden" name="day_group" value="<?php echo e($day_group); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="PENDING" <?php echo $status==='PENDING'?'selected':''; ?>>Pending</option>
                    <option value="IN_PROGRESS" <?php echo $status==='IN_PROGRESS'?'selected':''; ?>>In Progress</option>
                    <option value="COMPLETED" <?php echo $status==='COMPLETED'?'selected':''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select">
                    <option value="">All Priority</option>
                    <option value="LOW" <?php echo $priority==='LOW'?'selected':''; ?>>Low</option>
                    <option value="MEDIUM" <?php echo $priority==='MEDIUM'?'selected':''; ?>>Medium</option>
                    <option value="HIGH" <?php echo $priority==='HIGH'?'selected':''; ?>>High</option>
                    <option value="URGENT" <?php echo $priority==='URGENT'?'selected':''; ?>>Urgent</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="section" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $section==$s['id']?'selected':''; ?>><?php echo e($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-navy bg-navy text-white">Filter</button>
                <a href="my-tasks.php<?php echo $day_group!==''?'?day_group='.urlencode($day_group):''; ?>" class="btn btn-outline-secondary">Reset</a>
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
                    <th>Section</th>
                    <th>Assigned By</th>
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
                    <td><?php echo e($t['section_name']); ?></td>
                    <td>
                        <?php if (($t['assigned_by_role'] ?? '') === 'coordinator'): ?>
                            <span class="badge bg-info text-dark"><?php echo e($t['assigned_by_name']); ?></span>
                            <small class="text-muted d-block">Coordinator</small>
                        <?php elseif (($t['assigned_by_role'] ?? '') === 'admin'): ?>
                            <span class="badge bg-danger"><?php echo e($t['assigned_by_name']); ?></span>
                            <small class="text-muted d-block">Admin</small>
                        <?php else: ?>
                            <?php echo e($t['assigned_by_name'] ?? '—'); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo priorityBadge($t['priority']); ?></td>
                    <td><?php echo formatDate($t['due_date'] ?? $t['start_date'] ?? null); ?></td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
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