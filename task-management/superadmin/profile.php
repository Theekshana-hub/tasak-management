<?php
$page_title = 'My Profile';
require_once '../includes/super_admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<link rel="stylesheet" href="../assets/css/super-admin.css">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-person"></i> My Profile</h2>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="140">Name</th>
                        <td><?php echo e($user['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo e($user['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo e($user['phone'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td><span class="badge bg-dark">Super Admin</span></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>