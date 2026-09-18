<?php
$page_title = 'Create Task';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name")->fetchAll();


$assignees = $pdo->query("
    SELECT id, name, role, section_id 
    FROM users 
    WHERE role IN ('user', 'coordinator') AND status = 'active' 
    ORDER BY role DESC, name
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Task</h2>
    <a href="tasks.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Tasks</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="../actions/create-task.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Task Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Update Student Registration System">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe the task in detail..."></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section <span class="text-danger">*</span></label>
                    <select name="section_id" id="sectionSelect" class="form-select" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo e($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assign Type</label>
                    <select id="roleFilter" class="form-select">
                        <option value="all">All (Coordinators + Agents)</option>
                        <option value="coordinator">Coordinators only</option>
                        <option value="user">Agents only</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Assign To <span class="text-danger">*</span></label>
                    <select name="assigned_to[]" id="assigneeSelect" class="form-select" multiple required size="8">
                        <?php foreach ($assignees as $a): ?>
                        <option value="<?php echo $a['id']; ?>"
                                data-role="<?php echo e($a['role']); ?>"
                                data-section="<?php echo (int)($a['section_id'] ?? 0); ?>">
                            <?php echo e($a['name']); ?>
                            (<?php echo $a['role'] === 'coordinator' ? 'Coordinator' : 'Agent'; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Hold <b>Ctrl</b> (Windows) or <b>Cmd</b> (Mac) to select multiple people.
                        You can select Coordinators, Agents, or both.
                    </small>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        <option value="LOW">Low</option>
                        <option value="MEDIUM" selected>Medium</option>
                        <option value="HIGH">High</option>
                        <option value="URGENT">Urgent</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label">Attachment (optional)</label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
                    <div class="form-text">Max 5MB. Allowed: images, PDF, Word, Excel, ZIP, TXT</div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-coral btn-lg"><i class="bi bi-check-lg"></i> Create Task</button>
                <a href="tasks.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const sectionSelect = document.getElementById('sectionSelect');
const roleFilter = document.getElementById('roleFilter');
const assigneeSelect = document.getElementById('assigneeSelect');
const allOptions = Array.from(assigneeSelect.options);

function filterAssignees() {
    const sectionId = sectionSelect.value ? parseInt(sectionSelect.value) : 0;
    const role = roleFilter.value;
    const selected = new Set(Array.from(assigneeSelect.selectedOptions).map(o => o.value));

    assigneeSelect.innerHTML = '';

    allOptions.forEach(function (opt) {
        const optRole = opt.getAttribute('data-role');
        const optSection = parseInt(opt.getAttribute('data-section') || '0');

        if (role !== 'all' && optRole !== role) return;
        if (sectionId && optSection !== 0 && optSection !== sectionId) return;

        const clone = opt.cloneNode(true);
        if (selected.has(clone.value)) clone.selected = true;
        assigneeSelect.appendChild(clone);
    });

    if (assigneeSelect.options.length === 0) {
        const empty = document.createElement('option');
        empty.disabled = true;
        empty.textContent = 'No matching people';
        assigneeSelect.appendChild(empty);
    }
}

sectionSelect.addEventListener('change', filterAssignees);
roleFilter.addEventListener('change', filterAssignees);
</script>

<?php require_once '../includes/footer.php'; ?>