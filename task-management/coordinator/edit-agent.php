<?php
$page_title = 'Edit Agent';
require_once '../includes/coordinator_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$pdo      = getDB();
$coord_id = (int)$_SESSION['user_id'];
$agent_id = (int)($_GET['id'] ?? 0);


$stmt = $pdo->prepare("
    SELECT section_id 
    FROM users 
    WHERE id = ? AND role = 'coordinator' AND status = 'active' 
    LIMIT 1
");
$stmt->execute([$coord_id]);
$coord = $stmt->fetch();
$coord_section_id = (int)($coord['section_id'] ?? 0);


$agent = null;
if ($agent_id > 0 && $coord_section_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.*, s.name AS section_name
        FROM users u
        LEFT JOIN sections s ON s.id = u.section_id
        WHERE u.id = ? AND u.role = 'user' AND u.section_id = ?
        LIMIT 1
    ");
    $stmt->execute([$agent_id, $coord_section_id]);
    $agent = $stmt->fetch();
}

if (!$agent) {
    setFlash('danger', 'Agent not found in your department.');
    redirect('agents.php');
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Agent</h2>
    <a href="agents.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to My Agents</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/update-agent.php" id="editAgentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="agent_id" value="<?php echo (int)$agent['id']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="100"
                           value="<?php echo e($agent['name']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required maxlength="150"
                           value="<?php echo e($agent['email']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" maxlength="20"
                           value="<?php echo e($agent['phone'] ?? ''); ?>" placeholder="e.g. 0771234567">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?php echo ($agent['status'] ?? '') === 'active'   ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($agent['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <input type="text" class="form-control bg-light" readonly
                           value="<?php echo e($agent['section_name'] ?? '—'); ?>">
                    <small class="text-muted">Section can only be changed by Admin.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">New Password <span class="text-muted">(optional)</span></label>
                    <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password"
                           placeholder="Leave empty to keep current password">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral btn-lg">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
                <a href="agents.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>