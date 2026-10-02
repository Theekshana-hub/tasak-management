<?php
$page_title = 'Create My Task';
// NOTE: change this to the same auth include your other user pages (e.g. my-tasks.php) use
require_once '../includes/user_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

// Only Agents (role = 'user') should use this page
if (($_SESSION['user_role'] ?? '') !== 'user') {
    header('Location: dashboard.php');
    exit;
}

$my_id = (int)($_SESSION['user_id'] ?? 0);

// Logged-in user's own details
$stmt = $pdo->prepare("SELECT id, name, section_id FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$my_id]);
$me = $stmt->fetch();

if (!$me) {
    header('Location: ../actions/logout.php');
    exit;
}

$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Create My Task(s)</h2>
    <a href="my-tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to My Tasks</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/create-task.php" enctype="multipart/form-data" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <!-- Always assigned to the logged-in user (server must re-check this) -->
            <input type="hidden" name="assigned_to[]" value="<?php echo (int)$me['id']; ?>">
            <input type="hidden" name="self_assign" value="1">

            <!-- Section + Assigned to (self) -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Section <span class="text-muted">(optional)</span></label>
                    <select name="section_id" class="form-select">
                        <option value="">-- No Section --</option>
                        <?php foreach ($sections as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"
                            <?php echo ((int)($me['section_id'] ?? 0) === (int)$s['id']) ? 'selected' : ''; ?>>
                            <?php echo e($s['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assigned To</label>
                    <div class="form-control bg-light d-flex align-items-center gap-2">
                        <i class="bi bi-person-check text-success"></i>
                        <strong><?php echo e($me['name']); ?></strong>
                        <span class="badge bg-primary ms-auto">Myself</span>
                    </div>
                    <small class="text-muted">These tasks will be assigned to you and appear in <b>My Tasks</b>.</small>
                </div>
            </div>

            <hr>

            <!-- Multiple Tasks -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-list-task"></i> Tasks</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addTaskBtn">
                    <i class="bi bi-plus-lg"></i> Add Another Task
                </button>
            </div>

            <div id="tasksContainer">
                <!-- Task #1 -->
                <div class="task-block border rounded p-3 mb-3 bg-light" data-index="0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="task-label">Task #1</strong>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-task" style="display:none;">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="tasks[0][title]" class="form-control" required placeholder="e.g. Prepare weekly report">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="tasks[0][description]" class="form-control" rows="2" placeholder="Describe the task in detail..."></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="tasks[0][priority]" class="form-select" required>
                                <option value="LOW">Low</option>
                                <option value="MEDIUM" selected>Medium</option>
                                <option value="HIGH">High</option>
                                <option value="URGENT">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="tasks[0][start_date]" class="form-control start-date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration</label>
                            <select name="tasks[0][duration_days]" class="form-select duration-days">
                                <option value="1" selected>1 Day</option>
                                <option value="2">2 Days</option>
                                <option value="3">3 Days</option>
                                <option value="7">7 Days (Week)</option>
                                <option value="14">14 Days</option>
                                <option value="30">30 Days (Month)</option>
                            </select>
                            <small class="text-muted">Auto-fills Due Date</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="tasks[0][due_date]" class="form-control due-date" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Attachment (optional – applies to all tasks)</label>
                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
                <div class="form-text">Max 5MB. Allowed: images, PDF, Word, Excel, ZIP, TXT</div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral btn-lg">
                    <i class="bi bi-check-lg"></i> Create My Tasks
                </button>
                <a href="my-tasks.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const tasksContainer = document.getElementById('tasksContainer');
const addTaskBtn     = document.getElementById('addTaskBtn');

let taskIndex = 0;

// ---------- Due date calculation per task block ----------
function calcDueDate(block) {
    const startInput = block.querySelector('.start-date');
    const daysSelect = block.querySelector('.duration-days');
    const dueInput   = block.querySelector('.due-date');
    if (!startInput || !startInput.value) return;

    const d = new Date(startInput.value + 'T00:00:00');
    const days = parseInt(daysSelect.value) || 1;
    d.setDate(d.getDate() + (days - 1));

    const yyyy = d.getFullYear();
    const mm   = String(d.getMonth() + 1).padStart(2, '0');
    const dd   = String(d.getDate()).padStart(2, '0');
    dueInput.value = yyyy + '-' + mm + '-' + dd;
}

function bindDateEvents(block) {
    if (!block) return;
    block.querySelector('.start-date').addEventListener('change', () => calcDueDate(block));
    block.querySelector('.duration-days').addEventListener('change', () => calcDueDate(block));
    calcDueDate(block);
}

// Initial task
bindDateEvents(tasksContainer.querySelector('.task-block'));

// Add another task
addTaskBtn.addEventListener('click', function () {
    taskIndex++;
    const html = `
    <div class="task-block border rounded p-3 mb-3 bg-light" data-index="${taskIndex}">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="task-label">Task #${taskIndex + 1}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-task">
                <i class="bi bi-trash"></i> Remove
            </button>
        </div>
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="tasks[${taskIndex}][title]" class="form-control" required placeholder="e.g. Prepare weekly report">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="tasks[${taskIndex}][description]" class="form-control" rows="2" placeholder="Describe the task in detail..."></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Priority <span class="text-danger">*</span></label>
                <select name="tasks[${taskIndex}][priority]" class="form-select" required>
                    <option value="LOW">Low</option>
                    <option value="MEDIUM" selected>Medium</option>
                    <option value="HIGH">High</option>
                    <option value="URGENT">Urgent</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="tasks[${taskIndex}][start_date]" class="form-control start-date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Duration</label>
                <select name="tasks[${taskIndex}][duration_days]" class="form-select duration-days">
                    <option value="1" selected>1 Day</option>
                    <option value="2">2 Days</option>
                    <option value="3">3 Days</option>
                    <option value="7">7 Days (Week)</option>
                    <option value="14">14 Days</option>
                    <option value="30">30 Days (Month)</option>
                </select>
                <small class="text-muted">Auto-fills Due Date</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="tasks[${taskIndex}][due_date]" class="form-control due-date" readonly>
            </div>
        </div>
    </div>`;
    tasksContainer.insertAdjacentHTML('beforeend', html);
    const newBlock = tasksContainer.querySelector('[data-index="' + taskIndex + '"]');
    bindDateEvents(newBlock);
    updateRemoveButtons();
});

// Remove task
tasksContainer.addEventListener('click', function (e) {
    if (e.target.closest('.remove-task')) {
        e.target.closest('.task-block').remove();
        renumberTasks();
        updateRemoveButtons();
    }
});

function updateRemoveButtons() {
    const blocks = tasksContainer.querySelectorAll('.task-block');
    blocks.forEach(b => {
        const btn = b.querySelector('.remove-task');
        if (btn) btn.style.display = blocks.length > 1 ? 'inline-block' : 'none';
    });
}

function renumberTasks() {
    const blocks = tasksContainer.querySelectorAll('.task-block');
    blocks.forEach((b, i) => {
        b.querySelector('.task-label').textContent = 'Task #' + (i + 1);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>