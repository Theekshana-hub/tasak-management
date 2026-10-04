<?php
$page_title = 'My Tasks';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$user_id = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$section  = $_GET['section'] ?? '';

// LEFT JOIN — section/created_by NULL උනත් පෙනෙනවා
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
    $sql .= " AND t.due_date < ? AND t.status NOT IN ('COMPLETED','CANCELLED')";
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

$sql .= " ORDER BY
    CASE
        WHEN t.due_date < CURDATE() AND t.status NOT IN ('COMPLETED','CANCELLED') THEN 0
        WHEN t.priority = 'URGENT' THEN 1
        WHEN t.priority = 'HIGH' THEN 2
        ELSE 3
    END,
    t.due_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$sections = $pdo->query("SELECT id, name FROM sections WHERE status='active' ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-person-check"></i> My Tasks</h2>
    <span class="text-muted">Tasks assigned to me by Admin</span>
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

<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
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
                <a href="my-tasks.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Section</th>
                    <th>Assigned By</th>
                    <th>Priority</th>
                    <th>Start</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th style="width:150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No tasks assigned to you yet</td>
                </tr>
                <?php else: ?>
                <?php foreach ($tasks as $t):
                    $isToday   = (!empty($t['due_date']) && $t['due_date'] === $today);
                    $isOverdue = (!empty($t['due_date']) && $t['due_date'] < $today && !in_array($t['status'], ['COMPLETED','CANCELLED'], true));
                ?>
                <tr class="<?= $isOverdue ? 'table-danger' : ($isToday ? 'table-warning' : '') ?>">
                    <td>
                        <a href="my-task-details.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none fw-medium">
                            <?= e($t['title']) ?>
                        </a>
                        <?php if ($isToday): ?>
                            <span class="badge bg-warning text-dark ms-1">Today</span>
                        <?php endif; ?>
                        <?php if ($isOverdue): ?>
                            <span class="badge bg-danger ms-1">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($t['section_name'] ?? '—') ?></td>
                    <td>
                        <?php if (($t['assigned_by_role'] ?? '') === 'admin'): ?>
                            <span class="badge bg-danger"><?= e($t['assigned_by_name']) ?></span>
                            <small class="text-muted d-block">Admin</small>
                        <?php elseif (($t['assigned_by_role'] ?? '') === 'super_admin'): ?>
                            <span class="badge bg-warning text-dark"><?= e($t['assigned_by_name']) ?></span>
                            <small class="text-muted d-block">Super Admin</small>
                        <?php else: ?>
                            <?= e($t['assigned_by_name'] ?? '—') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= priorityBadge($t['priority']) ?></td>
                    <td><?= formatDate($t['start_date']) ?></td>
                    <td>
                        <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                            <?= formatDate($t['due_date']) ?>
                        </span>
                    </td>
                    <td><?= statusBadge($t['status'], $t['due_date']) ?></td>
                    <td>
                        <!-- Overdue වුණත් View / Edit / Delete enabled -->
                        <div class="d-flex gap-1 flex-nowrap">
                            <a href="my-task-details.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit-my-task.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="../actions/delete-my-task-coord.php" class="d-inline"
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