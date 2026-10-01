<?php
$page_title = 'Edit User';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    redirect('users.php');
}

// Admin ට Super Admin edit කරන්න ඉඩ නෑ
if (($user['role'] ?? '') === 'super_admin') {
    setFlash('danger', 'You cannot edit a Super Admin account.');
    redirect('users.php');
}

$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name")->fetchAll();
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
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           value="<?= e($user['name']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required 
                           value="<?= e($user['email']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= e($user['phone'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= $s['id'] ?>" 
                                <?= ((int)$user['section_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="user" <?= ($user['role'] === 'user') ? 'selected' : '' ?>>
                            Agent
                        </option>
                        <option value="coordinator" <?= ($user['role'] === 'coordinator') ? 'selected' : '' ?>>
                            Coordinator / Executive
                        </option>
                        <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>
                            Admin
                        </option>
                    </select>
                    <div class="form-text">Super Admin role cannot be assigned from here.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($user['status'] === 'active') ? 'selected' : '' ?>>
                            Active
                        </option>
                        <option value="inactive" <?= ($user['status'] === 'inactive') ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        New Password 
                        <small class="text-muted">(leave blank to keep current)</small>
                    </label>
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