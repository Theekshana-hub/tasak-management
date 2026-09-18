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

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$sections  = $pdo->query("SELECT id, name FROM sections WHERE status='active' ORDER BY name")->fetchAll();
$users     = $pdo->query("SELECT id, name FROM users WHERE role='user' AND status='active' ORDER BY name")->fetchAll();
$assigners = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin','coordinator') AND status='active' ORDER BY role, name")->fetchAll();


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


 * True when a task is past its due date and still open.
 */
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
?>

<style>
    /* Keep date columns on a single line */
    .task-table td,
    .task-table th {
        white-space: nowrap;
    }
    /* Task title column can wrap normally */
    .task-table td:first-child,
    .task-table th:first-child {
        white-space: normal;
        min-width: 220px;
    }
    .task-table .date-col {
        width: 110px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> All Tasks</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Create Task</a>
</div>

<!-- Filters -->
<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2">
                <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="section" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $section == $s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="assigned_by" class="form-select">
                    <option value="">Assigned By (All)</option>
                    <?php foreach ($assigners as $a): ?>
                    <option value="<?php echo $a['id']; ?>" <?php echo $assigned_by == $a['id'] ? 'selected' : ''; ?>>
                        <?php echo e($a['name']); ?> (<?php echo $a['role'] === 'admin' ? 'Admin' : 'Coordinator'; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="user" class="form-select">
                    <option value="">All Agents</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $user == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['name']); ?></option>
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
                <a href="tasks.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle task-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Section</th>
                    <th>Assigned To</th>
                    <th>Assigned By</th>
                    <th>Priority</th>
                    <th class="date-col">Start</th>
                    <th class="date-col">Due</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <?php $overdue = taskIsOverdue($t['due_date'], $t['status']); ?>
                <tr>
                    <td>
                        <a href="task-details.php?id=<?php echo $t['id']; ?>" class="text-decoration-none fw-medium">
                            <?php echo e($t['title']); ?>
                        </a>
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
                    <td class="date-col"><?php echo taskDate($t['start_date']); ?></td>
                    <td class="date-col">
                        <span class="<?php echo $overdue ? 'text-danger fw-semibold' : ''; ?>">
                            <?php echo taskDate($t['due_date']); ?>
                        </span>
                        <?php if ($overdue): ?>
                            <i class="bi bi-exclamation-triangle-fill text-danger" title="Overdue"></i>
                        <?php endif; ?>
                    </td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="edit-task.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
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