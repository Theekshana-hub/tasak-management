<?php
$page_title = 'Sections';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$sections = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM tasks t WHERE t.section_id = s.id) AS task_count,
           (SELECT COUNT(*) FROM users u WHERE u.section_id = s.id) AS user_count
    FROM sections s
    ORDER BY s.name
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-diagram-3"></i> Sections</h2>
    <a href="add-section.php" class="btn btn-coral"><i class="bi bi-plus-lg"></i> Add Section</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Tasks</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $i => $s): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo e($s['name']); ?></strong></td>
                    <td><?php echo e($s['description'] ?? '-'); ?></td>
                    <td><?php echo $s['task_count']; ?></td>
                    <td><?php echo $s['user_count']; ?></td>
                    <td>
                        <?php if ($s['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit-section.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php if ($s['task_count'] == 0 && $s['user_count'] == 0): ?>
                        <a href="../actions/delete-section.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this section?"><i class="bi bi-trash"></i></a>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete - in use"><i class="bi bi-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
