<?php
$page_title = 'Team Tasks';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$agent  = $_GET['agent'] ?? '';

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

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$agents_stmt = $pdo->prepare("SELECT id, name FROM users WHERE coordinator_id = ? AND role = 'user' ORDER BY name");
$agents_stmt->execute([$coord_id]);
$agents = $agents_stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-list-task"></i> Team Tasks</h2>
    <a href="create-task.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Assign Task</a>
</div>

<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
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
                <a href="tasks.php" class="btn btn-outline-secondary">Reset</a>
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
                    <th>Agent</th>
                    <th>Section</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td>
                        <a href="task-details.php?id=<?php echo $t['id']; ?>">
                            <?php echo e($t['title']); ?>
                        </a>
                    </td>
                    <td><?php echo e($t['assigned_name']); ?></td>
                    <td><?php echo e($t['section_name']); ?></td>
                    <td><?php echo priorityBadge($t['priority']); ?></td>
                    <td><?php echo formatDate($t['due_date']); ?></td>
                    <td><?php echo statusBadge($t['status'], $t['due_date']); ?></td>
                    <td>
                        <a href="task-details.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No tasks found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>