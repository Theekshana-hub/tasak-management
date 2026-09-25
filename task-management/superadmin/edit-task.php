<?php
// ============================================
// 1. Auth + DB (no HTML yet)
// ============================================
require_once '../includes/admin_auth.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid task ID.');
    redirect('tasks.php');
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found.');
    redirect('tasks.php');
}

$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name")->fetchAll();
$users    = $pdo->query("SELECT id, name FROM users WHERE role = 'user' AND status = 'active' ORDER BY name")->fetchAll();

// ============================================
// 2. NOW safe to include header / sidebar
// ============================================
$page_title = 'Edit Task';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil"></i> Edit Task</h2>
    <a href="task-details.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/update-task-full.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Task Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required value="<?php echo e($task['title']); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo e($task['description'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select" required>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $task['section_id'] == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo e($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select" required>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $task['assigned_to'] == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo e($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach (['LOW','MEDIUM','HIGH','URGENT'] as $p): ?>
                            <option value="<?php echo $p; ?>" <?php echo $task['priority'] === $p ? 'selected' : ''; ?>>
                                <?php echo $p; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['PENDING','IN_PROGRESS','COMPLETED','CANCELLED'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $task['status'] === $st ? 'selected' : ''; ?>>
                                <?php echo $st; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?php echo e($task['due_date'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?php echo e($task['start_date'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Attachment</label>
                    <?php if (!empty($task['attachment'])): ?>
                        <div class="mb-2">
                            <a href="../actions/download.php?file=<?php echo urlencode($task['attachment']); ?>&task_id=<?php echo $task['id']; ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-paperclip"></i> <?php echo e($task['attachment']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="attachment" class="form-control">
                    <small class="text-muted">Uploading a new file replaces the current attachment.</small>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral">
                    <i class="bi bi-check-lg"></i> Update Task
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>