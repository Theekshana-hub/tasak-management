<?php
$page_title = 'Assign Task';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = (int)$_SESSION['user_id'];

// Current coordinator details
$stmt = $pdo->prepare("SELECT id, name, section_id FROM users WHERE id = ? AND role = 'coordinator' AND status = 'active' LIMIT 1");
$stmt->execute([$coord_id]);
$currentCoord = $stmt->fetch();

$coord_section_id = null;
if ($currentCoord && $currentCoord['section_id'] !== null) {
    $coord_section_id = (int)$currentCoord['section_id'];
}

// ===== Agents in SAME SECTION (department) =====
// Dashboard එකේ වගේ - වෙන coordinator කෙනෙක් add කළ agents ත් පෙන්වයි
$agents = [];
if ($coord_section_id) {
    $stmt = $pdo->prepare("
        SELECT id, name, section_id 
        FROM users 
        WHERE role = 'user' 
          AND status = 'active' 
          AND section_id = ?
        ORDER BY name ASC
    ");
    $stmt->execute([$coord_section_id]);
    $agents = $stmt->fetchAll();
}

// Assignees list = Coordinator (self) + All agents in same section
$assignees = [];
if ($currentCoord) {
    $assignees[] = [
        'id'         => (int)$currentCoord['id'],
        'name'       => $currentCoord['name'] . ' (Me)',
        'section_id' => $coord_section_id,
        'is_self'    => true,
    ];
}
foreach ($agents as $a) {
    $assignees[] = [
        'id'         => (int)$a['id'],
        'name'       => $a['name'],
        'section_id' => $a['section_id'] !== null ? (int)$a['section_id'] : null,
        'is_self'    => false,
    ];
}

// ===== ONLY Coordinator's own section =====
$sections = [];
if ($coord_section_id) {
    $st = $pdo->prepare("SELECT id, name FROM sections WHERE status = 'active' AND id = ? ORDER BY name");
    $st->execute([$coord_section_id]);
    $sections = $st->fetchAll();
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
    .assignee-item:last-of-type { border-bottom: 0; }
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
    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Assign Task</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (!$coord_section_id): ?>
            <div class="alert alert-warning">
                No section assigned to you. Please contact admin to set your section.
            </div>
        <?php elseif (empty($assignees)): ?>
            <div class="alert alert-warning">
                No one available to assign. Please contact admin.
            </div>
        <?php elseif (empty($sections)): ?>
            <div class="alert alert-warning">
                No section assigned to you. Please contact admin to set your section.
            </div>
        <?php else: ?>
        <form method="POST" action="../actions/create-task-coord.php" enctype="multipart/form-data" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <!-- Section + Assign Type + Assignees -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Section <span class="text-danger">*</span></label>
                    <select name="section_id" id="sectionSelect" class="form-select" required>
                        <?php if (count($sections) === 1): ?>
                            <option value="<?php echo (int)$sections[0]['id']; ?>" selected>
                                <?php echo e($sections[0]['name']); ?>
                            </option>
                        <?php else: ?>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($sections as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small class="text-muted">Your assigned section only</small>
                </div>

                <!-- Assign Type: multi-select checkbox dropdown -->
                <div class="col-md-4">
                    <label class="form-label">Assign Type</label>
                    <div class="dropdown">
                        <button type="button" class="btn form-control role-dropdown-btn dropdown-toggle"
                                id="roleDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            All
                        </button>
                        <div class="dropdown-menu role-menu" id="roleMenu">
                            <label class="role-option fw-semibold">
                                <input type="checkbox" class="form-check-input mt-0" id="roleAll" checked>
                                <span>All (Me + Agents)</span>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            <label class="role-option">
                                <input type="checkbox" class="form-check-input mt-0 role-check" value="self">
                                <span>Me (Coordinator)</span>
                            </label>
                            <label class="role-option">
                                <input type="checkbox" class="form-check-input mt-0 role-check" value="agent">
                                <span>Agents</span>
                            </label>
                        </div>
                    </div>
                    <small class="text-muted">You can tick both. Then pick people one by one from the list.</small>
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
                               style="display:none;"
                               data-role="<?php echo $a['is_self'] ? 'self' : 'agent'; ?>"
                               data-self="<?php echo $a['is_self'] ? '1' : '0'; ?>"
                               data-section="<?php echo (int)($a['section_id'] ?? 0); ?>"
                               data-name="<?php echo e(strtolower($a['name'])); ?>">
                            <input type="checkbox" class="form-check-input assignee-check"
                                   name="assigned_to[]" value="<?php echo (int)$a['id']; ?>">
                            <span><?php echo e($a['name']); ?></span>
                            <span class="badge bg-light text-dark border role-badge"><?php echo $a['is_self'] ? 'Me' : 'Agent'; ?></span>
                        </label>
                        <?php endforeach; ?>
                        <div class="p-3 text-muted text-center" id="noMatch">Select a section first</div>
                    </div>
                    <small class="text-muted" id="agentHint">
                        Select a section first, then tick people one by one.<br>
                        You can also assign to <b>yourself</b>. All agents in your department are shown.
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
                                <option value="2">2 Days</option>
                                <option value="3">3 Days</option>
                                <option value="7">7 Days (Week)</option>
                                <option value="14">14 Days</option>
                                <option value="30">30 Days (Month)</option>
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
const sectionSelect   = document.getElementById('sectionSelect');
const roleAll         = document.getElementById('roleAll');
const roleChecks      = Array.from(document.querySelectorAll('.role-check'));
const roleDropdownBtn = document.getElementById('roleDropdownBtn');
const assigneeSearch  = document.getElementById('assigneeSearch');
const assigneeBox     = document.getElementById('assigneeBox');
const noMatch         = document.getElementById('noMatch');
const selectedCount   = document.getElementById('selectedCount');
const selectAllBtn    = document.getElementById('selectAllBtn');
const clearAllBtn     = document.getElementById('clearAllBtn');
const agentHint       = document.getElementById('agentHint');
const taskForm        = document.getElementById('taskForm');
const tasksContainer  = document.getElementById('tasksContainer');
const addTaskBtn      = document.getElementById('addTaskBtn');
const assigneeItems   = assigneeBox ? Array.from(assigneeBox.querySelectorAll('.assignee-item')) : [];

let taskIndex = 0;

// Does this person belong to the chosen section?
function inSection(item, sectionId) {
    if (!sectionId) return false;
    const itemSection = parseInt(item.getAttribute('data-section') || '0');
    const isSelf      = item.getAttribute('data-self') === '1';
    if (isSelf) return itemSection === 0 || itemSection === sectionId;
    return itemSection === sectionId;
}

// ---------- Assign Type (multi-select dropdown) ----------
function getSelectedRoles() {
    return roleChecks.filter(c => c.checked).map(c => c.value);
}

function updateRoleButtonText() {
    const checked = roleChecks.filter(c => c.checked);
    if (checked.length === 0) {
        roleDropdownBtn.textContent = 'All';
    } else {
        roleDropdownBtn.textContent = checked
            .map(c => c.parentElement.querySelector('span').textContent.trim())
            .join(', ');
    }
}

if (roleAll) {
    roleAll.addEventListener('change', function () {
        if (roleAll.checked) {
            roleChecks.forEach(c => c.checked = false);
        } else if (getSelectedRoles().length === 0) {
            roleAll.checked = true;
        }
        updateRoleButtonText();
        filterAssignees();
    });

    roleChecks.forEach(function (c) {
        c.addEventListener('change', function () {
            roleAll.checked = getSelectedRoles().length === 0;
            updateRoleButtonText();
            filterAssignees();
        });
    });
}

// ---------- Assignee filtering ----------
function filterAssignees() {
    const sectionId = sectionSelect.value ? parseInt(sectionSelect.value) : 0;
    const roles     = getSelectedRoles();
    const q         = assigneeSearch.value.trim().toLowerCase();
    let visible = 0;

    assigneeItems.forEach(function (item) {
        const itemRole = item.getAttribute('data-role');
        const itemName = item.getAttribute('data-name') || '';
        let show = inSection(item, sectionId);

        if (show && roles.length > 0 && roles.indexOf(itemRole) === -1) show = false;
        if (show && q && itemName.indexOf(q) === -1) show = false;

        item.style.display = show ? 'flex' : 'none';
        if (show) visible++;
    });

    if (!sectionId) {
        noMatch.textContent = 'Select a section first';
        noMatch.style.display = 'block';
        agentHint.innerHTML = 'Select a section first, then tick people one by one.<br>You can also assign to <b>yourself</b>. All agents in your department are shown.';
    } else if (visible === 0) {
        noMatch.textContent = 'No matching people';
        noMatch.style.display = 'block';
        agentHint.textContent = 'No assignees found for this filter.';
    } else {
        noMatch.style.display = 'none';
        agentHint.innerHTML = visible + ' person(s) shown. Tick people one by one.<br>You can also assign to <b>yourself</b>. All agents in your department are shown.';
    }
}

function updateSelectedCount() {
    const n = assigneeBox.querySelectorAll('.assignee-check:checked').length;
    selectedCount.textContent = n + ' selected';
    selectedCount.className = 'badge ' + (n > 0 ? 'bg-success' : 'bg-secondary');
}

if (sectionSelect) {
    sectionSelect.addEventListener('change', function () {
        const sectionId = this.value ? parseInt(this.value) : 0;
        assigneeItems.forEach(function (item) {
            if (!inSection(item, sectionId)) {
                item.querySelector('.assignee-check').checked = false;
            }
        });
        filterAssignees();
        updateSelectedCount();
    });
}

if (assigneeSearch) {
    assigneeSearch.addEventListener('input', filterAssignees);
}

if (assigneeBox) {
    assigneeBox.addEventListener('change', function (e) {
        if (e.target.classList.contains('assignee-check')) updateSelectedCount();
    });
}

if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
        assigneeItems.forEach(function (item) {
            if (item.style.display !== 'none') {
                item.querySelector('.assignee-check').checked = true;
            }
        });
        updateSelectedCount();
    });
}

if (clearAllBtn) {
    clearAllBtn.addEventListener('click', function () {
        assigneeBox.querySelectorAll('.assignee-check').forEach(c => c.checked = false);
        updateSelectedCount();
    });
}

if (taskForm) {
    taskForm.addEventListener('submit', function (e) {
        if (assigneeBox.querySelectorAll('.assignee-check:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one person to assign the task(s) to.');
            assigneeBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
}

updateRoleButtonText();
filterAssignees();
updateSelectedCount();

// Auto-trigger filter if section is already selected (only 1 section)
if (sectionSelect && sectionSelect.value) {
    sectionSelect.dispatchEvent(new Event('change'));
}

// ---------- Due date calculation ----------
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
                        <option value="2">2 Days</option>
                        <option value="3">3 Days</option>
                        <option value="7">7 Days (Week)</option>
                        <option value="14">14 Days</option>
                        <option value="30">30 Days (Month)</option>
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