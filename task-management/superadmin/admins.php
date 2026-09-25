<?php
$page_title = 'Manage Admins';
require_once '../includes/super_admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT u.* FROM users u WHERE u.role = 'admin'";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$admins = $stmt->fetchAll();
?>
<link rel="stylesheet" href="../assets/css/super-admin.css">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-shield-lock"></i> Manage Admins</h2>
    <a href="add-admin.php" class="btn btn-coral"><i class="bi bi-person-plus"></i> Add Admin</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, phone..." value="<?php echo e($search); ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-navy bg-navy text-white">Search</button>
        <a href="admins.php" class="btn btn-outline-secondary">Reset</a>
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
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No admins found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($admins as $i => $u): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo e($u['name']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td><?php echo e($u['phone'] ?? '-'); ?></td>
                        <td>
                            <?php if ($u['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($u['created_at'] ?? ''); ?></td>
                        <td>
                            <a href="edit-admin.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="../actions/delete-user.php?id=<?php echo $u['id']; ?>" 
                               class="btn btn-sm btn-outline-danger" 
                               title="Delete"
                               onclick="return confirm('Are you sure you want to delete this admin?');">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>