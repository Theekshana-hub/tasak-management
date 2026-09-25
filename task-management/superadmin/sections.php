<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$message = '';
$error = '';

// ===== Handle Form Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Add Section ----
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = 'Section name is required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO sections (name, description, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $description]);
            $message = 'Section added successfully!';
        }
    }

    // ---- Edit Section ----
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($id <= 0 || $name === '') {
            $error = 'Invalid data.';
        } else {
            $stmt = $pdo->prepare("UPDATE sections SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            $message = 'Section updated successfully!';
        }
    }

    // ---- Delete Section ----
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Optional: Check if section has tasks before deleting
            $check = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE section_id = ?");
            $check->execute([$id]);
            $taskCount = $check->fetchColumn();

            if ($taskCount > 0) {
                $error = "Cannot delete. This section has $taskCount task(s).";
            } else {
                $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Section deleted successfully!';
            }
        }
    }
}

// Fetch all sections
$stmt = $pdo->query("SELECT * FROM sections ORDER BY id DESC");
$sections = $stmt->fetchAll();

$page_title = 'Sections';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-diagram-3"></i> Sections</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Section
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo e($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo e($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ===== Sections Table ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Section Name</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th style="width:140px" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sections)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>
                                No sections found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $i = 1; foreach ($sections as $s): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td class="fw-semibold"><?php echo e($s['name'] ?? '-'); ?></td>
                                <td><?php echo e($s['description'] ?? '-'); ?></td>
                                <td><?php echo isset($s['created_at']) ? date('Y-m-d', strtotime($s['created_at'])) : '-'; ?></td>
                                <td class="text-end">
                                    <!-- Edit Button -->
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editModal"
                                            data-id="<?php echo $s['id']; ?>"
                                            data-name="<?php echo e($s['name']); ?>"
                                            data-description="<?php echo e($s['description'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <!-- Delete Button -->
                                    <button class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal"
                                            data-id="<?php echo $s['id']; ?>"
                                            data-name="<?php echo e($s['name']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== Add Modal ===== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Section Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Section</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Edit Modal ===== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header">
                <h5 class="modal-title">Edit Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Section Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Section</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Delete Modal ===== -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete-id">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Delete Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the section <strong id="delete-name"></strong>?</p>
                <p class="text-muted small mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// Fill Edit Modal
document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('edit-id').value = button.getAttribute('data-id');
    document.getElementById('edit-name').value = button.getAttribute('data-name');
    document.getElementById('edit-description').value = button.getAttribute('data-description');
});

// Fill Delete Modal
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('delete-id').value = button.getAttribute('data-id');
    document.getElementById('delete-name').textContent = button.getAttribute('data-name');
});
</script>

<?php require_once '../includes/footer.php'; ?>