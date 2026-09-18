<?php
$page_title = 'My Profile';
require_once '../includes/user_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT u.*, s.name AS section_name FROM users u LEFT JOIN sections s ON s.id = u.section_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<div class="mb-4">
    <h2 class="mb-0"><i class="bi bi-person"></i> My Profile</h2>
</div>

<div class="card border-0 shadow-sm" style="max-width: 600px;">
    <div class="card-body">
        <table class="table table-borderless">
            <tr><th width="140">Name</th><td><?php echo e($user['name']); ?></td></tr>
            <tr><th>Email</th><td><?php echo e($user['email']); ?></td></tr>
            <tr><th>Phone</th><td><?php echo e($user['phone'] ?? '-'); ?></td></tr>
            <tr><th>Section</th><td><?php echo e($user['section_name'] ?? '-'); ?></td></tr>
            <tr><th>Role</th><td><?php echo e(ucfirst($user['role'])); ?></td></tr>
            <tr><th>Status</th><td><?php echo e(ucfirst($user['status'])); ?></td></tr>
            <tr><th>Joined</th><td><?php echo formatDateTime($user['created_at']); ?></td></tr>
        </table>
        <p class="text-muted small">To change password or other details, contact Admin.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
