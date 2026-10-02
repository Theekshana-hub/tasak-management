<?php
$page_title = 'Create Task';
require_once '../includes/super_admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name")->fetchAll();

// Super Admin can assign to: Managing Director, Management, Coordinator, Agent
$assignees = $pdo->query("
    SELECT id, name, role, section_id 
    FROM users 
    WHERE role IN ('super_admin', 'admin', 'coordinator', 'user') 
      AND status = 'active' 
    ORDER BY FIELD(role, 'super_admin', 'admin', 'coordinator', 'user'), name
")->fetchAll();

function roleLabel($role) {
    $map = [
        'super_admin' => 'Managing Director',
        'admin'       => 'Management',
        'coordinator' => 'Executive/Coordinator',
        'user'        => 'Agent',
    ];
    return $map[$role] ?? ucfirst($role);
}
?>

<style>
    .assignee-box {
        max-height: 240px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: .375rem;
        background: #fff;
    }
    .assignee-item {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .4rem .75rem;
        border-bottom: 1px solid #f1f1f1;
        cursor: pointer;
        margin: 0;
    }
    .assignee-item:last-child { border-bottom: 0; }
    .assignee-item:hover { background: #f8f9fa; }
    .assignee-item input { flex-shrink: 0; }
    .assignee-item .role-badge {
        font-size: .72rem;
        margin-left: auto;
    }
    .role-dropdown-btn {
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        background: #fff;
        border: 1px solid #dee2e6;
    }
    .role-dropdown-btn:hover, .role-dropdown-btn.show {
        background: #fff;
        border-color: #86b7fe;
    }
    .role-dropdown-btn::after { float: right; margin-top: .6rem; }
    .role-menu { width: 100%; padding: .25rem 0; }
    .role-menu .role-option {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .4rem 1rem;
        margin: 0;
        cursor: pointer;
    }
    .role-menu .role-option:hover { background: #f8f9fa; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Task(s)</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Tasks</a>
</div>

<?php
// Flash message display
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = $flash['type'] ?? 'info';
    $msg  = $flash['message'] ?? '';
    if ($msg) {
        $alertClass = ($type === 'success') ? 'success' : (($type === 'danger') ? 'danger' : 'info');
        echo '<div class="alert alert-' . e($alertClass) . ' alert-dismissible fade show" role="alert">';
        echo e($msg);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/create-task.php" enctype="multipart/form-data" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <!-- Section + Assign Type + Assignees -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Section <span class="text-muted">(optional)</span></label>
                    <select name="section_id" id="sectionSelect" class="form-select">
                        <option value="">-- All / No Section --</option>
                        <?php foreach ($sections as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Optional. Used only to filter assignees if needed.</small>
                </div>

                <!-- Assign Type: multi-select checkbox dropdown -->
                <div class="col-md-4">
                    <label class="form-label">Assign Type</label>
                    <div class="dropdown">
                        <button type="button" class="btn form-control role-dropdown-btn dropdown-toggle"
                                id="roleDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            All Roles
                        </button>
                        <div class="dropdown-menu role-menu" id="roleMenu">
                            <label class="role-option fw-semibold">
                                <input type="checkbox" class="form-check-input mt-0" id="roleAll" checked>
                                <span>All Roles</span>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            <label class="role-option">
                                <input type="checkbox" class="form-check-input mt-0 role-check" value="super_admin">
                                <span>Managing Director</span>
                            </label>
                            <label class="role-option">
                                <input type="checkbox" class="form-check-input mt-0 role-check" value="admin">
                                <span>Management</span>
                            </label>
                            <label class="role-option">
                                <input type="checkbox" class="form-check-input mt-0 role-check" value="coordinator">
                                <span>Executive/Coordinator</span>
                            </label>
                            <label class="role-option">
                                <input type="checkbox" class="form-check-input mt-0 role-check" value="user">
                                <span>Agents</span>
                            </label>
                        </div>
                    </div>
                    <small class="text-muted">You can tick more than one type. Then pick people one by one from the list.</small>
                </div>

                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">Assign To <span class="text-danger">*</span></label>
                        <span class="badge bg-secondary" id="selectedCount">0 selected</span>
                    </div>

                    <input type="text" id="assigneeSearch" class="form-control form-control-sm mb-2" placeholder="Search name...">

                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">Select All (shown)</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllBtn">Clear All</button>
                    </div>

                    <div class="assignee-box" id="assigneeBox">
                        <?php foreach ($assignees as $a): ?>
                        <label class="assignee-item"
                               data-role="<?php echo e($a['role']); ?>"
                               data-section="<?php echo (int)($a['section_id'] ?? 0); ?>"
                               data-name="<?php echo e(strtolower($a['name'])); ?>">
                            <input type="checkbox" class="form-check-input assignee-check"
                                   name="assigned_to[]" value="<?php echo (int)$a['id']; ?>">
                            <span><?php echo e($a['name']); ?></span>
                            <span class="badge bg-light text-dark border role-badge"><?php echo e(roleLabel($a['role'])); ?></span>
                        </label>
                        <?php endforeach; ?>
                        <div class="p-3 text-muted text-center" id="noMatch" style="display:none;">No matching people</div>
                    </div>

                    <small class="text-muted">
                        Tick people one by one. Your selections stay even when you change the Assign Type.<br>
                        You can assign to yourself (Managing Director) — it will appear in <b>My Tasks</b>.
                    </small>
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
                            <input type="text" name="tasks[0][title]" class="form-control" required placeholder="e.g. Update Student Registration System">
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
                    <i class="bi bi-check-lg"></i> Create All Tasks
                </button>
                <a href="tasks.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const sectionSelect   = document.getElementById('sectionSelect');
const roleAll         = document.getElementById('roleAll');
const roleChecks      = Array.from(document.querySelectorAll('.role-check'));
const roleDropdownBtn = document.getElementById('roleDropdownBtn');
const assigneeSearch  = document.getElementById('assigneeSearch');
const assigneeBox     = document.getElementById('assigneeBox');
const assigneeItems   = Array.from(assigneeBox.querySelectorAll('.assignee-item'));
const noMatch         = document.getElementById('noMatch');
const selectedCount   = document.getElementById('selectedCount');
const selectAllBtn    = document.getElementById('selectAllBtn');
const clearAllBtn     = document.getElementById('clearAllBtn');
const taskForm        = document.getElementById('taskForm');
const tasksContainer  = document.getElementById('tasksContainer');
const addTaskBtn      = document.getElementById('addTaskBtn');

let taskIndex = 0;

// ---------- Assign Type (multi-select dropdown) ----------
function getSelectedRoles() {
    return roleChecks.filter(c => c.checked).map(c => c.value);
}

function updateRoleButtonText() {
    const checked = roleChecks.filter(c => c.checked);
    if (checked.length === 0) {
        roleDropdownBtn.textContent = 'All Roles';
    } else {
        roleDropdownBtn.textContent = checked
            .map(c => c.parentElement.querySelector('span').textContent.trim())
            .join(', ');
    }
}

roleAll.addEventListener('change', function () {
    // "All Roles" ticked -> clear the individual roles
    if (roleAll.checked) {
        roleChecks.forEach(c => c.checked = false);
    } else if (getSelectedRoles().length === 0) {
        // Can't leave nothing ticked; keep "All Roles"
        roleAll.checked = true;
    }
    updateRoleButtonText();
    filterAssignees();
});

roleChecks.forEach(function (c) {
    c.addEventListener('change', function () {
        // Any specific role ticked -> untick "All Roles"; none ticked -> back to "All Roles"
        roleAll.checked = getSelectedRoles().length === 0;
        updateRoleButtonText();
        filterAssignees();
    });
});

// ---------- Assignee filtering (checkbox list) ----------
function filterAssignees() {
    const sectionId = sectionSelect.value ? parseInt(sectionSelect.value) : 0;
    const roles     = getSelectedRoles();           // empty = all roles
    const q         = assigneeSearch.value.trim().toLowerCase();
    let visible = 0;

    assigneeItems.forEach(function (item) {
        const itemRole    = item.getAttribute('data-role');
        const itemSection = parseInt(item.getAttribute('data-section') || '0');
        const itemName    = item.getAttribute('data-name') || '';

        let show = true;
        if (roles.length > 0 && roles.indexOf(itemRole) === -1) show = false;
        if (sectionId && itemSection !== 0 && itemSection !== sectionId) show = false;
        if (q && itemName.indexOf(q) === -1) show = false;

        // Hidden items keep their checked state, so selections persist
        item.style.display = show ? 'flex' : 'none';
        if (show) visible++;
    });

    noMatch.style.display = visible === 0 ? 'block' : 'none';
}

function updateSelectedCount() {
    const n = assigneeBox.querySelectorAll('.assignee-check:checked').length;
    selectedCount.textContent = n + ' selected';
    selectedCount.className = 'badge ' + (n > 0 ? 'bg-success' : 'bg-secondary');
}

sectionSelect.addEventListener('change', filterAssignees);
assigneeSearch.addEventListener('input', filterAssignees);

assigneeBox.addEventListener('change', function (e) {
    if (e.target.classList.contains('assignee-check')) updateSelectedCount();
});

// Select all (only currently visible people)
selectAllBtn.addEventListener('click', function () {
    assigneeItems.forEach(function (item) {
        if (item.style.display !== 'none') {
            item.querySelector('.assignee-check').checked = true;
        }
    });
    updateSelectedCount();
});

// Clear all (everyone, including hidden)
clearAllBtn.addEventListener('click', function () {
    assigneeBox.querySelectorAll('.assignee-check').forEach(c => c.checked = false);
    updateSelectedCount();
});

// Require at least one assignee on submit
taskForm.addEventListener('submit', function (e) {
    if (assigneeBox.querySelectorAll('.assignee-check:checked').length === 0) {
        e.preventDefault();
        alert('Please select at least one person to assign the task(s) to.');
        assigneeBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

updateRoleButtonText();
filterAssignees();
updateSelectedCount();

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
if (tasksContainer) {
    bindDateEvents(tasksContainer.querySelector('.task-block'));
}

// Add another task
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
                    <input type="text" name="tasks[${taskIndex}][title]" class="form-control" required placeholder="e.g. Update Student Registration System">
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
}

// Remove task
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