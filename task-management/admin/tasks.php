<?php
$page_title = 'All Tasks';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

$search      = trim($_GET['search'] ?? '');
$section     = $_GET['section'] ?? '';
$user        = $_GET['user'] ?? '';
$assigned_by = $_GET['assigned_by'] ?? '';
$status      = $_GET['status'] ?? '';
$priority    = $_GET['priority'] ?? '';
$day_group   = $_GET['day_group'] ?? '';

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

$sql = "SELECT t.*, 
               u.name AS assigned_name, 
               s.name AS section_name, 
               c.name AS assigned_by_name,
               c.role AS assigned_by_role
        FROM tasks t
        JOIN users u ON u.id = t.assigned_to
        JOIN sections s ON s.id = t.section_id
        JOIN users c ON c.id = t.created_by
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
if ($section !== '') {
    $sql .= " AND t.section_id = ?";
    $params[] = (int)$section;
}
if ($user !== '') {
    $sql .= " AND t.assigned_to = ?";
    $params[] = (int)$user;
}
if ($assigned_by !== '') {
    $sql .= " AND t.created_by = ?";
    $params[] = (int)$assigned_by;
}
if ($status !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}
if ($priority !== '') {
    $sql .= " AND t.priority = ?";
    $params[] = $priority;
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

// Agents + section_id (JS filter එකට)
$users = $pdo->query("SELECT id, name, section_id FROM users WHERE role='user' AND status='active' ORDER BY name")->fetchAll();

// Admins + Coordinators, coordinator ට යටතේ තියෙන section ids
$assigners_raw = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin','coordinator') AND status='active' ORDER BY role, name")->fetchAll();
$assigners = [];
foreach ($assigners_raw as $a) {
    $section_ids = [];
    if ($a['role'] === 'coordinator') {
        $st = $pdo->prepare("SELECT DISTINCT section_id FROM users WHERE coordinator_id = ? AND role = 'user' AND section_id IS NOT NULL");
        $st->execute([$a['id']]);
        $section_ids = array_map('intval', array_column($st->fetchAll(), 'section_id'));
    }
    // admin → හැම section එකටම (empty = all)
    $assigners[] = [
        'id'          => (int)$a['id'],
        'name'        => $a['name'],
        'role'        => $a['role'],
        'section_ids' => $section_ids
    ];
}

if (!function_exists('taskDate')) {
    function taskDate($date, $format = 'd M Y') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '<span class="text-muted">&mdash;</span>';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '<span class="text-muted">&mdash;</span>';
        }
        return '<span class="text-nowrap">' . date($format, $ts) . '</span>';
    }
}

if (!function_exists('taskIsOverdue')) {
    function taskIsOverdue($due_date, $status) {
        if (empty($due_date) || $due_date === '0000-00-00' || $due_date === '0000-00-00 00:00:00') {
            return false;
        }
        if (in_array($status, ['COMPLETED', 'CANCELLED'], true)) {
            return false;
        }
        $ts = strtotime($due_date);
        return $ts !== false && $ts < strtotime('today');
    }
}

function dayGroupUrlAdmin($group, $search, $section, $user, $assigned_by, $status, $priority) {
    $q = [];
    if ($group !== '') $q['day_group'] = $group;
    if ($search !== '') $q['search'] = $search;
    if ($section !== '') $q['section'] = $section;
    if ($user !== '') $q['user'] = $user;
    if ($assigned_by !== '') $q['assigned_by'] = $assigned_by;
    if ($status !== '') $q['status'] = $status;
    if ($priority !== '') $q['priority'] = $priority;
    return 'tasks.php' . ($q ? ('?' . http_build_query($q)) : '');
}

$today = date('Y-m-d');
?>

<style>
    .task-table td,
    .task-table th { white-space: nowrap; }
    .task-table td:nth-child(2),
    .task-table th:nth-child(2) {
        white-space: normal;
        min-width: 200px;
    }
    .task-table .date-col { width: 110px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> All Tasks</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Create Task</a>
</div>

<!-- Day Group Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlAdmin('', $search, $section, $user, $assigned_by, $status, $priority)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group===''?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-dark"><?php echo count($all_tasks); ?></div>
                    <div class="small text-muted">All Tasks</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlAdmin('1', $search, $section, $user, $assigned_by, $status, $priority)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='1'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-primary"><?php echo (int)$group_counts['1']; ?></div>
                    <div class="small text-muted">1 Day</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlAdmin('3', $search, $section, $user, $assigned_by, $status, $priority)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='3'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-info"><?php echo (int)$group_counts['3']; ?></div>
                    <div class="small text-muted">3 Days</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlAdmin('5', $search, $section, $user, $assigned_by, $status, $priority)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='5'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-secondary"><?php echo (int)$group_counts['5']; ?></div>
                    <div class="small text-muted">5 Days</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlAdmin('7', $search, $section, $user, $assigned_by, $status, $priority)); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?php echo $day_group==='7'?'border border-primary':''; ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-success"><?php echo (int)$group_counts['7']; ?></div>
                    <div class="small text-muted">7 Days (Week)</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?php echo e(dayGroupUrlAdmin('14', $search, $section, $user, $assigned_by, $status, $priority)); ?>" class="text-decoration-none">
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

<!-- Filters -->
<form method="GET" class="card border-0 shadow-sm mb-4" id="filterForm">
    <div class="card-body">
        <?php if ($day_group !== ''): ?>
            <input type="hidden" name="day_group" value="<?php echo e($day_group); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-2">
                <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="section" id="sectionFilter" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $section == $s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="assigned_by" id="assignedByFilter" class="form-select">
                    <option value="">Assigned By (All)</option>
                    <?php foreach ($assigners as $a): ?>
                    <option
                        value="<?php echo $a['id']; ?>"
                        data-role="<?php echo e($a['role']); ?>"
                        data-sections="<?php echo e(implode(',', $a['section_ids'])); ?>"
                        <?php echo $assigned_by == $a['id'] ? 'selected' : ''; ?>
                    >
                        <?php echo e($a['name']); ?> (<?php echo $a['role'] === 'admin' ? 'Admin' : 'Coordinator'; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="user" id="agentFilter" class="form-select">
                    <option value="">All Agents</option>
                    <?php foreach ($users as $u): ?>
                    <option
                        value="<?php echo $u['id']; ?>"
                        data-section="<?php echo (int)($u['section_id'] ?? 0); ?>"
                        <?php echo $user == $u['id'] ? 'selected' : ''; ?>
                    >
                        <?php echo e($u['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <select name="status" class="form-select">
                    <option value="">Status</option>
                    <option value="PENDING" <?php echo $status==='PENDING'?'selected':''; ?>>Pending</option>
                    <option value="IN_PROGRESS" <?php echo $status==='IN_PROGRESS'?'selected':''; ?>>In Progress</option>
                    <option value="COMPLETED" <?php echo $status==='COMPLETED'?'selected':''; ?>>Completed</option>
                    <option value="CANCELLED" <?php echo $status==='CANCELLED'?'selected':''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-1">
                <select name="priority" class="form-select">
                    <option value="">Priority</option>
                    <option value="LOW" <?php echo $priority==='LOW'?'selected':''; ?>>Low</option>
                    <option value="MEDIUM" <?php echo $priority==='MEDIUM'?'selected':''; ?>>Medium</option>
                    <option value="HIGH" <?php echo $priority==='HIGH'?'selected':''; ?>>High</option>
                    <option value="URGENT" <?php echo $priority==='URGENT'?'selected':''; ?>>Urgent</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-navy bg-navy text-white">Filter</button>
                <a href="tasks.php<?php echo $day_group!==''?'?day_group='.urlencode($day_group):''; ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle task-table">
            <thead>
                <tr>
                    <th class="date-col">Assigned On</th>
                    <th>Task</th>
                    <th>Day</th>
                    <th>Section</th>
                    <th>Assigned To</th>
                    <th>Assigned By</th>
                    <th>Priority</th>
                    <th class="date-col">Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t):
                    $dayInfo = parseDayLabel($t['title'] ?? '');
                    $overdue = taskIsOverdue($t['due_date'], $t['status']);
                    $isToday = (!empty($t['due_date']) && $t['due_date'] === $today);
                ?>
                <tr class="<?php echo $isToday ? 'table-warning' : ''; ?>">
                    <td class="date-col"><?php echo taskDate($t['created_at'] ?? null); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="text-decoration-none fw-medium">
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
                    <td><?php echo e($t['assigned_name']); ?></td>
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
                    <td class="date-col">
                        <span class="<?php echo $overdue ? 'text-danger fw-semibold' : ''; ?>">
                            <?php echo taskDate($t['due_date'] ?? $t['start_date'] ?? null); ?>
                        </span>
                        <?php if ($overdue): ?>
                            <i class="bi bi-exclamation-triangle-fill text-danger" title="Overdue"></i>
                        <?php endif; ?>
                    </td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="edit-task.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No tasks found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const sectionFilter    = document.getElementById('sectionFilter');
    const assignedByFilter = document.getElementById('assignedByFilter');
    const agentFilter      = document.getElementById('agentFilter');

    function filterDropdowns() {
        const sectionId = sectionFilter.value; // '' = all

        // --- Agents: ඒ section එකේ agents විතරක් ---
        Array.from(agentFilter.options).forEach(function (opt, idx) {
            if (idx === 0) { // "All Agents"
                opt.hidden = false;
                return;
            }
            const agentSection = opt.getAttribute('data-section') || '0';
            if (!sectionId) {
                opt.hidden = false;
            } else {
                opt.hidden = (agentSection !== sectionId && agentSection !== '0');
            }
        });
        // selected agent hidden නම් reset
        if (agentFilter.selectedOptions.length && agentFilter.selectedOptions[0].hidden) {
            agentFilter.value = '';
        }

        // --- Assigned By: Admin හැමදාම + Coordinator ඒ section manage කරනවා නම් ---
        Array.from(assignedByFilter.options).forEach(function (opt, idx) {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }
            const role = opt.getAttribute('data-role') || '';
            const sectionsStr = opt.getAttribute('data-sections') || '';
            const sections = sectionsStr ? sectionsStr.split(',').map(s => s.trim()) : [];

            if (!sectionId) {
                opt.hidden = false;
                return;
            }
            if (role === 'admin') {
                opt.hidden = false; // Admin always visible
                return;
            }
            // Coordinator: ඒ section එක යටතේ agents තියෙනවා නම් පෙන්නන්න
            opt.hidden = sections.indexOf(sectionId) === -1;
        });
        if (assignedByFilter.selectedOptions.length && assignedByFilter.selectedOptions[0].hidden) {
            assignedByFilter.value = '';
        }
    }

    sectionFilter.addEventListener('change', filterDropdowns);
    // page load (GET section තියෙනවා නම්)
    filterDropdowns();
})();
</script>

<?php require_once '../includes/footer.php'; ?>