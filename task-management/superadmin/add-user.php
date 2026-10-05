<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';


if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

$page_title = 'Add User';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name ASC")->fetchAll();


$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-person-plus"></i> Add New User</h2>
    <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/create-user-super.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?php echo e($old['name'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo e($old['email'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?php echo e($old['phone'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['id']; ?>"
                                <?php echo ((string)($old['section_id'] ?? '') === (string)$s['id']) ? 'selected' : ''; ?>>
                                <?php echo e($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <?php
                        $roles = [
                            'user'        => 'User / Agent',
                            'coordinator' => 'Coordinator',
                            'admin'       => 'Admin',
                            'super_admin' => 'Super Admin',
                        ];
                        $selectedRole = $old['role'] ?? 'user';
                        foreach ($roles as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $selectedRole === $val ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo ($old['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($old['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="6"
                           autocomplete="new-password">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6"
                           autocomplete="new-password">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral"><i class="bi bi-check-lg"></i> Create User</button>
                <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>