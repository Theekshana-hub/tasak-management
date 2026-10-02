<?php
$page_title = 'My Tasks';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo     = getDB();
$user_id = (int)$_SESSION['user_id'];

// Status filter from URL
$status_filter  = $_GET['status'] ?? '';
$allowed_status = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

$where  = "t.assigned_to = ?";
$params = [$user_id];

if ($status_filter !== '' && in_array($status_filter, $allowed_status, true)) {
    $where .= " AND t.status = ?";
    $params[] = $status_filter;
}

// Tasks assigned to current user
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
        FIELD(t.priority, 'URGENT', 'HIGH', 'MEDIUM', 'LOW'),
        t.due_date IS NULL,
        t.due_date ASC,
        t.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Status counts
$count_sql = "
    SELECT status, COUNT(*) AS cnt
    FROM tasks
    WHERE assigned_to = ?
    GROUP BY status
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute([$user_id]);
$status_counts = [];
while ($row = $count_stmt->fetch()) {
    $status_counts[$row['status']] = (int)$row['cnt'];
}
$total_count = array_sum($status_counts);

// Helper functions (safe if file included once)
if (!function_exists('statusBadge')) {
    function statusBadge($status) {
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

if (!function_exists('priorityBadge')) {
    function priorityBadge($priority) {
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-person-check"></i> My Tasks</h2>
</div>

<!-- Status Filter Tabs -->
<div class="mb-4">
    <div class="btn-group flex-wrap" role="group">
        <a href="my-tasks.php"
           class="btn <?php echo $status_filter === '' ? 'btn-coral' : 'btn-outline-secondary'; ?>">
            All <span class="badge bg-dark ms-1"><?php echo $total_count; ?></span>
        </a>
        <a href="my-tasks.php?status=PENDING"
           class="btn <?php echo $status_filter === 'PENDING' ? 'btn-coral' : 'btn-outline-secondary'; ?>">
            Pending <span class="badge bg-dark ms-1"><?php echo $status_counts['PENDING'] ?? 0; ?></span>
        </a>
        <a href="my-tasks.php?status=IN_PROGRESS"
           class="btn <?php echo $status_filter === 'IN_PROGRESS' ? 'btn-coral' : 'btn-outline-secondary'; ?>">
            In Progress <span class="badge bg-dark ms-1"><?php echo $status_counts['IN_PROGRESS'] ?? 0; ?></span>
        </a>
        <a href="my-tasks.php?status=COMPLETED"
           class="btn <?php echo $status_filter === 'COMPLETED' ? 'btn-coral' : 'btn-outline-secondary'; ?>">
            Completed <span class="badge bg-dark ms-1"><?php echo $status_counts['COMPLETED'] ?? 0; ?></span>
        </a>
        <a href="my-tasks.php?status=CANCELLED"
           class="btn <?php echo $status_filter === 'CANCELLED' ? 'btn-coral' : 'btn-outline-secondary'; ?>">
            Cancelled <span class="badge bg-dark ms-1"><?php echo $status_counts['CANCELLED'] ?? 0; ?></span>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($tasks)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No tasks found<?php echo $status_filter ? ' with status <b>' . e($status_filter) . '</b>' : ''; ?>.
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
                            <th style="width:100px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $i => $task): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <a href="my-task-details.php?id=<?php echo (int)$task['id']; ?>" class="text-decoration-none fw-semibold">
                                    <?php echo e($task['title']); ?>
                                </a>
                                <?php if (!empty($task['attachment'])): ?>
                                    <i class="bi bi-paperclip text-muted ms-1" title="Has attachment"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($task['section_name'] ?? '—'); ?></td>
                            <td><?php echo priorityBadge($task['priority']); ?></td>
                            <td><?php echo statusBadge($task['status']); ?></td>
                            <td><?php echo $task['start_date'] ? date('d M Y', strtotime($task['start_date'])) : '—'; ?></td>
                            <td>
                                <?php
                                if ($task['due_date']) {
                                    $due   = strtotime($task['due_date']);
                                    $today = strtotime('today');
                                    $cls   = ($due < $today && $task['status'] !== 'COMPLETED') ? 'text-danger fw-bold' : '';
                                    echo '<span class="' . $cls . '">' . date('d M Y', $due) . '</span>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo e($task['created_by_name'] ?? '—'); ?></td>
                            <td>
                                <a href="my-task-details.php?id=<?php echo (int)$task['id']; ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
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