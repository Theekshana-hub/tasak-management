<?php
$page_title = 'Manage Users';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT u.*, s.name AS section_name FROM users u LEFT JOIN sections s ON s.id = u.section_id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-people"></i> Users</h2>
    <a href="add-user.php" class="btn btn-coral"><i class="bi bi-person-plus"></i> Add User</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, phone..." value="<?php echo e($search); ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-navy bg-navy text-white">Search</button>
        <a href="users.php" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

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
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo e($u['name']); ?></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><?php echo e($u['phone'] ?? '-'); ?></td>
                    <td><?php echo e($u['section_name'] ?? '-'); ?></td>
                   <td>
    <?php if ($u['role'] === 'admin'): ?>
        <span class="badge bg-danger">Admin</span>
    <?php elseif ($u['role'] === 'coordinator'): ?>
        <span class="badge bg-info text-dark">Coordinator</span>
    <?php else: ?>
        <span class="badge bg-primary">User / Agent</span>
    <?php endif; ?>
</td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit-user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="../actions/delete-user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete this user?" title="Delete"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
