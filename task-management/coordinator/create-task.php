<?php
$page_title = 'Assign Task';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

// මේ coordinator ගේ agents විතරක්
$stmt = $pdo->prepare("SELECT id, name, section_id FROM users WHERE coordinator_id = ? AND role = 'user' AND status = 'active' ORDER BY name");
$stmt->execute([$coord_id]);
$agents = $stmt->fetchAll();

// Agents තියෙන sections විතරක් (හැම section එකක්ම නෙමෙයි)
$section_ids = [];
foreach ($agents as $a) {
    if (!empty($a['section_id'])) {
        $section_ids[(int)$a['section_id']] = true;
    }
}
$sections = [];
if (!empty($section_ids)) {
    $placeholders = implode(',', array_fill(0, count($section_ids), '?'));
    $st = $pdo->prepare("SELECT id, name FROM sections WHERE status = 'active' AND id IN ($placeholders) ORDER BY name");
    $st->execute(array_keys($section_ids));
    $sections = $st->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Assign Task to Agent(s)</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($agents)): ?>
            <div class="alert alert-warning">
                You have no agents yet. <a href="add-agent.php">Add an Agent</a> first.
            </div>
        <?php elseif (empty($sections)): ?>
            <div class="alert alert-warning">
                Your agents are not assigned to any section. Please set a section for agents first.
            </div>
        <?php else: ?>
        <form method="POST" action="../actions/create-task-coord.php" enctype="multipart/form-data" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <!-- Section + Agents (අදාළ ඒවා විතරක්) -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Section <span class="text-danger">*</span></label>
                    <select name="section_id" id="sectionSelect" class="form-select" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo e($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Only sections where you have agents</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assign to Agent(s) <span class="text-danger">*</span></label>
                    <select name="assigned_to[]" id="agentSelect" class="form-select" multiple required disabled size="5">
                        <option value="" disabled>-- Select Section first --</option>
                    </select>
                    <small class="text-muted" id="agentHint">
                        Select a section first. Hold <b>Ctrl</b> / <b>Cmd</b> to select multiple agents.
                    </small>
                </div>
            </div>

            <hr>

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
                            <input type="text" name="tasks[0][title]" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="tasks[0][description]" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Priority</label>
                            <select name="tasks[0][priority]" class="form-select">
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
                                <option value="3">3 Days</option>
                                <option value="5">5 Days</option>
                                <option value="7">7 Days (Week)</option>
                                <option value="14">14 Days</option>
                            </select>
                            <small class="text-muted">7 Days = daily tasks for agent</small>
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
                <input type="file" name="attachment" class="form-control">
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral btn-lg">
                    <i class="bi bi-check-lg"></i> Assign All Tasks
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
const allAgents = <?php echo json_encode(array_map(function($a) {
    return [
        'id' => (int)$a['id'],
        'name' => $a['name'],
        'section_id' => $a['section_id'] !== null ? (int)$a['section_id'] : null
    ];
}, $agents)); ?>;

const sectionSelect  = document.getElementById('sectionSelect');
const agentSelect    = document.getElementById('agentSelect');
const agentHint      = document.getElementById('agentHint');
const tasksContainer = document.getElementById('tasksContainer');
const addTaskBtn     = document.getElementById('addTaskBtn');

let taskIndex = 0;

if (sectionSelect) {
sectionSelect.addEventListener('change', function () {
    const sectionId = this.value ? parseInt(this.value) : null;
    agentSelect.innerHTML = '';

    if (!sectionId) {
        agentSelect.disabled = true;
        agentSelect.innerHTML = '<option value="" disabled>-- Select Section first --</option>';
        agentHint.innerHTML = 'Select a section first. Hold <b>Ctrl</b> / <b>Cmd</b> to select multiple.';
        return;
    }

    // ඒ section එකේ + මේ coordinator ගේ agents විතරක්
    const filtered = allAgents.filter(a => a.section_id === sectionId);

    if (filtered.length === 0) {
        agentSelect.disabled = true;
        agentSelect.innerHTML = '<option value="" disabled>No agents in this section</option>';
        agentHint.textContent = 'No agents found for this section.';
        return;
    }

    agentSelect.disabled = false;
    filtered.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.name;
        agentSelect.appendChild(opt);
    });
    agentHint.innerHTML = filtered.length + ' agent(s). Hold <b>Ctrl</b> / <b>Cmd</b> to select multiple.';
});
}

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

if (tasksContainer) {
    bindDateEvents(tasksContainer.querySelector('.task-block'));
}

if (addTaskBtn) {
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
                <input type="text" name="tasks[${taskIndex}][title]" class="form-control" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="tasks[${taskIndex}][description]" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Priority</label>
                <select name="tasks[${taskIndex}][priority]" class="form-select">
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
                    <option value="3">3 Days</option>
                    <option value="5">5 Days</option>
                    <option value="7">7 Days (Week)</option>
                    <option value="14">14 Days</option>
                </select>
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
}

if (tasksContainer) {
tasksContainer.addEventListener('click', function (e) {
    if (e.target.closest('.remove-task')) {
        e.target.closest('.task-block').remove();
        renumberTasks();
        updateRemoveButtons();
    }
});
}

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