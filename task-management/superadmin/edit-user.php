<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid user ID.');
    redirect('users.php');
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    redirect('users.php');
}


if ($user['role'] === 'super_admin' && $user['id'] != $_SESSION['user_id']) {
    setFlash('danger', 'You cannot edit another Super Admin.');
    redirect('users.php');
}

$sections = $pdo->query("SELECT id, name FROM sections ORDER BY name ASC")->fetchAll();

$page_title = 'Edit User';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil"></i> Edit User</h2>
    <a href="users.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/update-user.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo e($user['name']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?php echo e($user['email']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo e($user['phone'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['id']; ?>" 
                                <?php echo ((string)$user['section_id'] === (string)$s['id']) ? 'selected' : ''; ?>>
                                <?php echo e($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" <?php echo $user['role'] === 'super_admin' ? 'disabled' : ''; ?>>
                        <?php if ($user['role'] === 'super_admin'): ?>
                            <option value="super_admin" selected>Super Admin</option>
                        <?php else: ?>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="coordinator" <?php echo $user['role'] === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Agent / User</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($user['role'] === 'super_admin'): ?>
                        <input type="hidden" name="role" value="super_admin">
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                    <input type="password" name="password" class="form-control" minlength="6">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral">
                    <i class="bi bi-check-lg"></i> Update User
                </button>
                <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>