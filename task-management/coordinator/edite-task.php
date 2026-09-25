<?php

require_once '../includes/coordinator_auth.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: tasks.php?error=invalid_id');
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*
    FROM tasks t
    JOIN users u ON u.id = t.assigned_to
    WHERE t.id = ? AND u.coordinator_id = ?
");
$stmt->execute([$id, $coord_id]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: tasks.php?error=not_allowed');
    exit;
}


$agents_stmt = $pdo->prepare("SELECT id, name FROM users WHERE coordinator_id = ? AND role = 'user' ORDER BY name");
$agents_stmt->execute([$coord_id]);
$agents = $agents_stmt->fetchAll();

$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $priority    = $_POST['priority'] ?? 'MEDIUM';
    $status      = $_POST['status'] ?? 'PENDING';
    $start_date  = $_POST['start_date'] ?? null;
    $due_date    = $_POST['due_date'] ?? null;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    $agent_ok = false;
    foreach ($agents as $a) {
        if ((int)$a['id'] === $assigned_to) {
            $agent_ok = true;
            break;
        }
    }
    if (!$agent_ok) {
        $errors[] = 'Invalid agent selected.';
    }

    $valid_priorities = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
    if (!in_array($priority, $valid_priorities, true)) {
        $errors[] = 'Invalid priority.';
    }

    $valid_statuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];
    if (!in_array($status, $valid_statuses, true)) {
        $errors[] = 'Invalid status.';
    }

    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE tasks
            SET title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, start_date = ?, due_date = ?
            WHERE id = ?
        ");
        $update->execute([
            $title,
            $description,
            $assigned_to,
            $priority,
            $status,
            $start_date ?: null,
            $due_date ?: null,
            $id
        ]);

       
        header('Location: tasks.php?updated=1');
        exit;
    }

   
    $task['title']       = $title;
    $task['description'] = $description;
    $task['assigned_to'] = $assigned_to;
    $task['priority']    = $priority;
    $task['status']      = $status;
    $task['start_date']  = $start_date;
    $task['due_date']    = $due_date;
}

$page_title = 'Edit Task';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Task</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Tasks</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?php echo e($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Task Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo e($task['title']); ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo e($task['description'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select" required>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo (int)$task['assigned_to'] === (int)$a['id'] ? 'selected' : ''; ?>>
                            <?php echo e($a['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="LOW"     <?php echo $task['priority']==='LOW'?'selected':''; ?>>Low</option>
                        <option value="MEDIUM"  <?php echo $task['priority']==='MEDIUM'?'selected':''; ?>>Medium</option>
                        <option value="HIGH"    <?php echo $task['priority']==='HIGH'?'selected':''; ?>>High</option>
                        <option value="URGENT"  <?php echo $task['priority']==='URGENT'?'selected':''; ?>>Urgent</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="PENDING"     <?php echo $task['status']==='PENDING'?'selected':''; ?>>Pending</option>
                        <option value="IN_PROGRESS" <?php echo $task['status']==='IN_PROGRESS'?'selected':''; ?>>In Progress</option>
                        <option value="COMPLETED"   <?php echo $task['status']==='COMPLETED'?'selected':''; ?>>Completed</option>
                        <option value="CANCELLED"   <?php echo $task['status']==='CANCELLED'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?php echo e($task['start_date'] ? date('Y-m-d', strtotime($task['start_date'])) : ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?php echo e($task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : ''); ?>">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-navy bg-navy text-white">
                    <i class="bi bi-save"></i> Save Changes
                </button>
                <a href="tasks.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>