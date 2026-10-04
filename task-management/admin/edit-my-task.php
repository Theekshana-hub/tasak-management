<?php
$page_title = 'Edit My Task';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$user_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid task ID.');
    redirect('my-tasks.php');
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to = ? LIMIT 1");
$stmt->execute([$id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or not allowed.');
    redirect('my-tasks.php');
}

$isOverdue = (
    !empty($task['due_date'])
    && $task['due_date'] < date('Y-m-d')
    && !in_array($task['status'], ['COMPLETED', 'CANCELLED'], true)
);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil"></i> Edit My Task</h2>
    <a href="my-tasks.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($isOverdue): ?>
<div class="alert alert-warning">
    This task is <strong>Overdue</strong>. You can still update it.
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/update-my-task-admin.php">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" value="<?= e($task['title']) ?>" readonly>
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($task['description'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?php foreach (['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($task['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= $s ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <input type="text" class="form-control" value="<?= e($task['priority'] ?? '') ?>" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?= e($task['due_date'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral">
                    <i class="bi bi-check-lg"></i> Update Task
                </button>
                <a href="my-tasks.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>