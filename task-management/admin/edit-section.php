<?php
$page_title = 'Edit Section';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->execute([$id]);
$section = $stmt->fetch();

if (!$section) {
    setFlash('danger', 'Section not found.');
    redirect('sections.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil"></i> Edit Section</h2>
    <a href="sections.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/update-section.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
            
            <div class="mb-3">
                <label class="form-label">Section Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?php echo e($section['name']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo e($section['description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?php echo $section['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $section['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-coral"><i class="bi bi-check-lg"></i> Update Section</button>
            <a href="sections.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
