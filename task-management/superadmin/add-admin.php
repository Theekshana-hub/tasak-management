<?php
$page_title = 'Add Admin';
require_once '../includes/super_admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-person-plus"></i> Add New Admin</h2>
    <a href="admins.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
<form action="../actions/add-admin-process.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control">
    </div>

    <!-- ===== Role Field (NEW) ===== -->
    <div class="mb-3">
        <label class="form-label">Role <span class="text-danger">*</span></label>
        <select name="role" class="form-select" required>
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
        </select>
        <div class="form-text">Only Super Admins can create other Super Admins.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" name="confirm_password" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">Create</button>
    <a href="admins.php" class="btn btn-secondary">Cancel</a>
</form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>