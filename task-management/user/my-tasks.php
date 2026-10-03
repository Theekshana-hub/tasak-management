<?php
$page_title = 'My Tasks';
require_once '../includes/user_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$user_id = (int)$_SESSION['user_id'];

$search    = trim($_GET['search'] ?? '');
$status    = $_GET['status'] ?? '';
$priority  = $_GET['priority'] ?? '';
$section   = $_GET['section'] ?? '';
$day_group = $_GET['day_group'] ?? '';
$today     = date('Y-m-d');

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

// LEFT JOIN — section/created_by NULL උනත් tasks පෙනෙනවා
$sql = "SELECT t.*,
               s.name AS section_name,
               c.name AS assigned_by_name,
               c.role AS assigned_by_role
        FROM tasks t
        LEFT JOIN sections s ON s.id = t.section_id
        LEFT JOIN users c ON c.id = t.created_by
        WHERE t.assigned_to = ?";
$params = [$user_id];

if ($search !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

if ($status === 'OVERDUE') {
    $sql .= " AND t.due_date < ? AND t.status NOT IN ('COMPLETED', 'CANCELLED')";
    $params[] = $today;
} elseif ($status !== '') {
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

$sections = $pdo->query("SELECT id, name FROM sections WHERE status='active' ORDER BY name")->fetchAll();

function dayGroupUrl($group, $search, $status, $priority, $section) {
    $q = [];
    if ($group !== '') $q['day_group'] = $group;
    if ($search !== '') $q['search'] = $search;
    if ($status !== '') $q['status'] = $status;
    if ($priority !== '') $q['priority'] = $priority;
    if ($section !== '') $q['section'] = $section;
    return 'my-tasks.php' . ($q ? ('?' . http_build_query($q)) : '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> My Tasks</h2>
</div>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    Task updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    Task deleted successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?php
    switch ($_GET['error'] ?? '') {
        case 'not_found':     echo 'Task not found.'; break;
        case 'not_allowed':   echo 'You are not allowed to modify this task.'; break;
        case 'delete_failed': echo 'Could not delete task.'; break;
        default:              echo 'Something went wrong.';
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Day Group Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ''   => ['label' => 'All Tasks', 'count' => count($all_tasks), 'class' => 'text-dark'],
        '1'  => ['label' => '1 Day', 'count' => (int)$group_counts['1'], 'class' => 'text-primary'],
        '3'  => ['label' => '3 Days', 'count' => (int)$group_counts['3'], 'class' => 'text-info'],
        '5'  => ['label' => '5 Days', 'count' => (int)$group_counts['5'], 'class' => 'text-secondary'],
        '7'  => ['label' => '7 Days (Week)', 'count' => (int)$group_counts['7'], 'class' => 'text-success'],
        '14' => ['label' => '14 Days', 'count' => (int)$group_counts['14'], 'class' => 'text-danger'],
    ];
    foreach ($cards as $key => $c):
    ?>
    <div class="col-6 col-md-2">
        <a href="<?= e(dayGroupUrl($key, $search, $status, $priority, $section)) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?= $day_group === $key ? 'border border-primary' : '' ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold <?= $c['class'] ?>"><?= $c['count'] ?></div>
                    <div class="small text-muted"><?= $c['label'] ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
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
        (<?= count($tasks) ?>)
    </span>
    <a href="my-tasks.php" class="btn btn-sm btn-outline-secondary">Show All</a>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <?php if ($day_group !== ''): ?>
            <input type="hidden" name="day_group" value="<?= e($day_group) ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="PENDING" <?= $status==='PENDING'?'selected':'' ?>>Pending</option>
                    <option value="IN_PROGRESS" <?= $status==='IN_PROGRESS'?'selected':'' ?>>In Progress</option>
                    <option value="COMPLETED" <?= $status==='COMPLETED'?'selected':'' ?>>Completed</option>
                    <option value="OVERDUE" <?= $status==='OVERDUE'?'selected':'' ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select">
                    <option value="">All Priority</option>
                    <option value="LOW" <?= $priority==='LOW'?'selected':'' ?>>Low</option>
                    <option value="MEDIUM" <?= $priority==='MEDIUM'?'selected':'' ?>>Medium</option>
                    <option value="HIGH" <?= $priority==='HIGH'?'selected':'' ?>>High</option>
                    <option value="URGENT" <?= $priority==='URGENT'?'selected':'' ?>>Urgent</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="section" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $section == $s['id'] ? 'selected' : '' ?>>
                        <?= e($s['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-navy bg-navy text-white">Filter</button>
                <a href="my-tasks.php<?= $day_group!==''?'?day_group='.urlencode($day_group):'' ?>" class="btn btn-outline-secondary">Reset</a>
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
                    <th style="width:150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No tasks found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($tasks as $t):
                    $dayInfo   = parseDayLabel($t['title'] ?? '');
                    $isToday   = (!empty($t['due_date']) && $t['due_date'] === $today);
                    $isOverdue = (!empty($t['due_date']) && $t['due_date'] < $today && !in_array($t['status'], ['COMPLETED', 'CANCELLED'], true));
                ?>
                <tr class="<?= $isOverdue ? 'table-danger' : ($isToday ? 'table-warning' : '') ?>">
                    <td><?= !empty($t['created_at']) ? formatDate($t['created_at']) : '—' ?></td>
                    <td>
                        <a href="task-details.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none fw-semibold">
                            <?= e($dayInfo ? $dayInfo['base'] : $t['title']) ?>
                        </a>
                        <?php if ($isToday): ?>
                            <span class="badge bg-warning text-dark ms-1">Today</span>
                        <?php endif; ?>
                        <?php if ($isOverdue): ?>
                            <span class="badge bg-danger ms-1">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($dayInfo): ?>
                            <span class="badge bg-primary">Day <?= $dayInfo['day'] ?>/<?= $dayInfo['total'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">1 Day</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($t['section_name'] ?? '—') ?></td>
                    <td>
                        <?php if (($t['assigned_by_role'] ?? '') === 'coordinator'): ?>
                            <span class="badge bg-info text-dark"><?= e($t['assigned_by_name']) ?></span>
                            <small class="text-muted d-block">Coordinator</small>
                        <?php elseif (($t['assigned_by_role'] ?? '') === 'admin'): ?>
                            <span class="badge bg-danger"><?= e($t['assigned_by_name']) ?></span>
                            <small class="text-muted d-block">Admin</small>
                        <?php else: ?>
                            <?= e($t['assigned_by_name'] ?? '—') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= priorityBadge($t['priority']) ?></td>
                    <td>
                        <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                            <?= formatDate($t['due_date'] ?? $t['start_date'] ?? null) ?>
                        </span>
                    </td>
                    <td><?= statusBadge($t['status'], $t['due_date']) ?></td>
                    <td>
                        <!-- Overdue වුණත් View / Edit / Delete enabled -->
                        <div class="d-flex gap-1 flex-nowrap">
                            <a href="task-details.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit-task.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="../actions/delete-task-user.php" class="d-inline"
                                  onsubmit="return confirm('Delete this task? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>