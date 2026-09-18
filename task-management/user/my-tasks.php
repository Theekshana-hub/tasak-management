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
    <h2 class="mb-0"><i class="bi bi-list-task"></i> My Tasks</h2>
</div>

<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
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
                    <th>Start Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td><?php echo e($t['title']); ?></td>
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
                    <td><?php echo formatDate($t['start_date']); ?></td>
                    <td><?php echo formatDate($t['due_date']); ?></td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No tasks found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>