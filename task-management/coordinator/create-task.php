<?php
$page_title = 'Assign Task';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = $_SESSION['user_id'];

$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT id, name, section_id FROM users WHERE coordinator_id = ? AND role = 'user' AND status = 'active' ORDER BY name");
$stmt->execute([$coord_id]);
$agents = $stmt->fetchAll();
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
        <?php else: ?>
        <form method="POST" action="../actions/create-task-coord.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Task Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"></textarea>
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
                    <label class="form-label">Assign to Agent(s) <span class="text-danger">*</span></label>
                    <select name="assigned_to[]" id="agentSelect" class="form-select" multiple required disabled size="6">
                        <option value="" disabled>-- Select Section first --</option>
                    </select>
                    <small class="text-muted" id="agentHint">
                        Select a section first. Hold <b>Ctrl</b> (Windows) or <b>Cmd</b> (Mac) to select multiple agents.
                    </small>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
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
                    <input type="file" name="attachment" class="form-control">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-coral btn-lg"><i class="bi bi-check-lg"></i> Assign Task</button>
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

const sectionSelect = document.getElementById('sectionSelect');
const agentSelect = document.getElementById('agentSelect');
const agentHint = document.getElementById('agentHint');

sectionSelect.addEventListener('change', function () {
    const sectionId = this.value ? parseInt(this.value) : null;
    agentSelect.innerHTML = '';

    if (!sectionId) {
        agentSelect.disabled = true;
        agentSelect.innerHTML = '<option value="" disabled>-- Select Section first --</option>';
        agentHint.innerHTML = 'Select a section first. Hold <b>Ctrl</b> / <b>Cmd</b> to select multiple.';
        return;
    }

    const filtered = allAgents.filter(function (a) {
        return a.section_id === sectionId || a.section_id === null;
    });

    if (filtered.length === 0) {
        agentSelect.disabled = true;
        agentSelect.innerHTML = '<option value="" disabled>No agents in this section</option>';
        agentHint.textContent = 'No agents found for this section.';
        return;
    }

    agentSelect.disabled = false;
    filtered.forEach(function (a) {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.name;
        agentSelect.appendChild(opt);
    });
    agentHint.innerHTML = filtered.length + ' agent(s). Hold <b>Ctrl</b> (Windows) or <b>Cmd</b> (Mac) and click to select multiple.';
});
</script>

<?php require_once '../includes/footer.php'; ?>