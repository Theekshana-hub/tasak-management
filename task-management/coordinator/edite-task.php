<?php
$page_title = 'Edit Task';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid task ID.');
    redirect('tasks.php');
}

// Coordinator section
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ? AND role = 'coordinator' LIMIT 1");
$stmt->execute([$coord_id]);
$coord_section_id = $stmt->fetchColumn();
$coord_section_id = ($coord_section_id !== false && $coord_section_id !== null) ? (int)$coord_section_id : null;

if (!$coord_section_id) {
    setFlash('danger', 'No section assigned.');
    redirect('tasks.php');
}

// Load task only if in department
$stmt = $pdo->prepare("
    SELECT t.*
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE t.id = ?
      AND u.role = 'user'
      AND (u.section_id = ? OR t.section_id = ?)
    LIMIT 1
");
$stmt->execute([$id, $coord_section_id, $coord_section_id]);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or not allowed.');
    redirect('tasks.php');
}

// Agents in same section
$agents_stmt = $pdo->prepare("
    SELECT id, name FROM users
    WHERE role = 'user' AND status = 'active' AND section_id = ?
    ORDER BY name
");
$agents_stmt->execute([$coord_section_id]);
$agents = $agents_stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil"></i> Edit Task</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/update-task-coord.php">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required
                           value="<?= e($task['title']) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($task['description'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Assign To <span class="text-danger">*</span></label>
                    <select name="assigned_to" class="form-select" required>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"
                            <?= (int)$task['assigned_to'] === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e($a['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach (['LOW','MEDIUM','HIGH','URGENT'] as $p): ?>
                        <option value="<?= $p ?>" <?= strtoupper($task['priority'] ?? '') === $p ? 'selected' : '' ?>>
                            <?= ucfirst(strtolower($p)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['PENDING','IN_PROGRESS','COMPLETED','CANCELLED'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($task['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= $s ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?= e($task['start_date'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?= e($task['due_date'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral">
                    <i class="bi bi-check-lg"></i> Update Task
                </button>
                <a href="tasks.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>