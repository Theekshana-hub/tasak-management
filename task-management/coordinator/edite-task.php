<?php
require_once '../includes/coordinator_auth.php';

$pdo = getDB();
$coord_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid task ID.');
    redirect('tasks.php');
}

// Coordinator own section
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ? AND role = 'coordinator' LIMIT 1");
$stmt->execute([$coord_id]);
$coord_section_id = $stmt->fetchColumn();
$coord_section_id = ($coord_section_id !== false && $coord_section_id !== null) ? (int)$coord_section_id : null;

// Allowed sections = own + granted
$allowed_section_ids = [];
if ($coord_section_id) {
    $allowed_section_ids[] = $coord_section_id;
}
$acc = $pdo->prepare("SELECT section_id FROM coordinator_section_access WHERE coordinator_id = ?");
$acc->execute([$coord_id]);
foreach ($acc->fetchAll(PDO::FETCH_COLUMN) as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && !in_array($sid, $allowed_section_ids, true)) {
        $allowed_section_ids[] = $sid;
    }
}

if (empty($allowed_section_ids)) {
    setFlash('danger', 'No section assigned.');
    redirect('tasks.php');
}

// Load task if in allowed department
$ph = implode(',', array_fill(0, count($allowed_section_ids), '?'));
$stmt = $pdo->prepare("
    SELECT t.*
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE t.id = ?
      AND (
            t.section_id IN ($ph)
         OR u.section_id IN ($ph)
      )
    LIMIT 1
");
$params = array_merge([$id], $allowed_section_ids, $allowed_section_ids);
$stmt->execute($params);
$task = $stmt->fetch();

if (!$task) {
    setFlash('danger', 'Task not found or not allowed.');
    redirect('tasks.php');
}

// Agents in all allowed sections
$agents_stmt = $pdo->prepare("
    SELECT id, name, section_id FROM users
    WHERE role = 'user' AND status = 'active' AND section_id IN ($ph)
    ORDER BY name
");
$agents_stmt->execute($allowed_section_ids);
$agents = $agents_stmt->fetchAll();

$page_title = 'Edit Task';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
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
                    <small class="text-muted">Agents from your department + granted departments</small>
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