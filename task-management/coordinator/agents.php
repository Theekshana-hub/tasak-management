<?php
$page_title = 'My Agents';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.*, s.name AS section_name,
           (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) AS total_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status IN ('PENDING','IN_PROGRESS')) AS active_tasks
    FROM users u
    LEFT JOIN sections s ON s.id = u.section_id
    WHERE u.coordinator_id = ? AND u.role = 'user'
    ORDER BY u.name
");
$stmt->execute([$coord_id]);
$agents = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-people"></i> My Agents</h2>
    <a href="add-agent.php" class="btn btn-coral"><i class="bi bi-person-plus"></i> Add Agent</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Section</th>
                    <th>Total Tasks</th>
                    <th>Active</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $i => $a): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo e($a['name']); ?></strong></td>
                    <td><?php echo e($a['email']); ?></td>
                    <td><?php echo e($a['phone'] ?? '-'); ?></td>
                    <td><?php echo e($a['section_name'] ?? '-'); ?></td>
                    <td><?php echo $a['total_tasks']; ?></td>
                    <td><span class="badge bg-primary"><?php echo $a['active_tasks']; ?></span></td>
                    <td>
                        <?php if ($a['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit-agent.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($agents)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No agents yet. Click Add Agent to create one.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
