<?php
$page_title = 'My Tasks';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Sri Lanka timezone so "today" is always correct
date_default_timezone_set('Asia/Colombo');

$pdo     = getDB();
$user_id = (int)$_SESSION['user_id'];
$today   = date('Y-m-d');

$status_filter  = $_GET['status'] ?? '';
$allowed_status = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'OVERDUE'];

$where  = "t.assigned_to = ?";
$params = [$user_id];

if ($status_filter === 'OVERDUE') {
    $where .= " AND t.due_date < ? AND t.status NOT IN ('COMPLETED','CANCELLED')";
    $params[] = $today;
} elseif ($status_filter !== '' && in_array($status_filter, $allowed_status, true)) {
    $where .= " AND t.status = ?";
    $params[] = $status_filter;
}

// The ORDER BY placeholder comes after the WHERE placeholders, so add it last
$params[] = $today;

$sql = "
    SELECT
        t.id,
        t.title,
        t.description,
        t.priority,
        t.status,
        t.start_date,
        t.due_date,
        t.created_at,
        t.attachment,
        t.completed_at,
        s.name AS section_name,
        u.name AS created_by_name
    FROM tasks t
    LEFT JOIN sections s ON s.id = t.section_id
    LEFT JOIN users u ON u.id = t.created_by
    WHERE $where
    ORDER BY
        CASE
            WHEN t.due_date = ? AND t.status NOT IN ('COMPLETED','CANCELLED') THEN 0
            ELSE 1
        END ASC,
        FIELD(t.priority, 'URGENT', 'HIGH', 'MEDIUM', 'LOW'),
        t.due_date IS NULL,
        t.due_date ASC,
        t.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Status counts
$count_stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM tasks
    WHERE assigned_to = ?
    GROUP BY status
");
$count_stmt->execute([$user_id]);
$status_counts = [];
while ($row = $count_stmt->fetch()) {
    $status_counts[$row['status']] = (int)$row['cnt'];
}
$total_count = array_sum($status_counts);

// Overdue count
$overdue_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks
    WHERE assigned_to = ?
      AND due_date < ?
      AND status NOT IN ('COMPLETED','CANCELLED')
");
$overdue_stmt->execute([$user_id, $today]);
$overdue_count = (int)$overdue_stmt->fetchColumn();

// Due today count
$today_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks
    WHERE assigned_to = ?
      AND due_date = ?
      AND status NOT IN ('COMPLETED','CANCELLED')
");
$today_stmt->execute([$user_id, $today]);
$today_count = (int)$today_stmt->fetchColumn();

if (!function_exists('statusBadgeLocal')) {
    function statusBadgeLocal($status) {
        $map = [
            'PENDING'     => 'bg-secondary',
            'IN_PROGRESS' => 'bg-primary',
            'COMPLETED'   => 'bg-success',
            'CANCELLED'   => 'bg-danger',
        ];
        $cls = $map[$status] ?? 'bg-secondary';
        return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
    }
}

if (!function_exists('priorityBadgeLocal')) {
    function priorityBadgeLocal($priority) {
        $map = [
            'LOW'    => 'bg-info text-dark',
            'MEDIUM' => 'bg-secondary',
            'HIGH'   => 'bg-warning text-dark',
            'URGENT' => 'bg-danger',
        ];
        $cls = $map[$priority] ?? 'bg-secondary';
        return '<span class="badge ' . $cls . '">' . htmlspecialchars($priority) . '</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h2 class="mb-0"><i class="bi bi-person-check"></i> My Tasks</h2>

    <!-- Today's date -->
    <div class="bg-white border rounded-pill shadow-sm px-3 py-2 d-flex align-items-center gap-2">
        <i class="bi bi-calendar-event text-danger"></i>
        <span class="fw-semibold"><?= date('l, d M Y') ?></span>
    </div>
</div>

<?php if ($today_count > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-alarm fs-5"></i>
    <div>You have <strong><?= $today_count ?></strong> task<?= $today_count > 1 ? 's' : '' ?> due today.</div>
</div>
<?php endif; ?>

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

<!-- Status Filter Tabs -->
<div class="mb-4">
    <div class="btn-group flex-wrap" role="group">
        <a href="my-tasks.php"
           class="btn <?= $status_filter === '' ? 'btn-coral' : 'btn-outline-secondary' ?>">
            All <span class="badge bg-dark ms-1"><?= $total_count ?></span>
        </a>
        <a href="my-tasks.php?status=PENDING"
           class="btn <?= $status_filter === 'PENDING' ? 'btn-coral' : 'btn-outline-secondary' ?>">
            Pending <span class="badge bg-dark ms-1"><?= $status_counts['PENDING'] ?? 0 ?></span>
        </a>
        <a href="my-tasks.php?status=IN_PROGRESS"
           class="btn <?= $status_filter === 'IN_PROGRESS' ? 'btn-coral' : 'btn-outline-secondary' ?>">
            In Progress <span class="badge bg-dark ms-1"><?= $status_counts['IN_PROGRESS'] ?? 0 ?></span>
        </a>
        <a href="my-tasks.php?status=COMPLETED"
           class="btn <?= $status_filter === 'COMPLETED' ? 'btn-coral' : 'btn-outline-secondary' ?>">
            Completed <span class="badge bg-dark ms-1"><?= $status_counts['COMPLETED'] ?? 0 ?></span>
        </a>
        <a href="my-tasks.php?status=CANCELLED"
           class="btn <?= $status_filter === 'CANCELLED' ? 'btn-coral' : 'btn-outline-secondary' ?>">
            Cancelled <span class="badge bg-dark ms-1"><?= $status_counts['CANCELLED'] ?? 0 ?></span>
        </a>
        <a href="my-tasks.php?status=OVERDUE"
           class="btn <?= $status_filter === 'OVERDUE' ? 'btn-coral' : 'btn-outline-secondary' ?>">
            Overdue <span class="badge bg-dark ms-1"><?= $overdue_count ?></span>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($tasks)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No tasks found<?= $status_filter ? ' with status <b>' . e($status_filter) . '</b>' : '' ?>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Title</th>
                            <th>Section</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Start</th>
                            <th>Due</th>
                            <th>Created By</th>
                            <th style="width:150px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $i => $task):
                            $isOverdue = (
                                !empty($task['due_date'])
                                && $task['due_date'] < $today
                                && !in_array($task['status'], ['COMPLETED', 'CANCELLED'], true)
                            );
                            $isToday = (
                                !empty($task['due_date'])
                                && $task['due_date'] === $today
                                && !in_array($task['status'], ['COMPLETED', 'CANCELLED'], true)
                            );
                            $rowClass = $isOverdue ? 'table-danger' : ($isToday ? 'table-warning' : '');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= $i + 1 ?></td>
                            <td>
                                <a href="my-task-details.php?id=<?= (int)$task['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= e($task['title']) ?>
                                </a>
                                <?php if (!empty($task['attachment'])): ?>
                                    <i class="bi bi-paperclip text-muted ms-1" title="Has attachment"></i>
                                <?php endif; ?>
                                <?php if ($isToday): ?>
                                    <span class="badge bg-warning text-dark ms-1">Today</span>
                                <?php endif; ?>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger ms-1">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($task['section_name'] ?? '—') ?></td>
                            <td>
                                <?php
                                if (function_exists('priorityBadge')) {
                                    echo priorityBadge($task['priority']);
                                } else {
                                    echo priorityBadgeLocal($task['priority']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (function_exists('statusBadge')) {
                                    echo statusBadge($task['status'], $task['due_date']);
                                } else {
                                    echo statusBadgeLocal($task['status']);
                                }
                                ?>
                            </td>
                            <td><?= $task['start_date'] ? date('d M Y', strtotime($task['start_date'])) : '—' ?></td>
                            <td>
                                <?php if ($task['due_date']): ?>
                                    <span class="<?= $isOverdue ? 'text-danger fw-bold' : ($isToday ? 'fw-bold' : '') ?>">
                                        <?= date('d M Y', strtotime($task['due_date'])) ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e($task['created_by_name'] ?? '—') ?></td>
                            <td>
                                <!-- Overdue වුණත් View / Edit / Delete enabled -->
                                <div class="d-flex gap-1 flex-nowrap">
                                    <a href="my-task-details.php?id=<?= (int)$task['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit-my-task.php?id=<?= (int)$task['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="../actions/delete-my-task-admin.php" class="d-inline"
                                          onsubmit="return confirm('Delete this task? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>