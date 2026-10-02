<?php
$page_title = 'My Agents';
require_once '../includes/coordinator_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();
$coord_id = (int)$_SESSION['user_id'];

// Coordinator ගේ section (department) එක ගන්නවා
$stmt = $pdo->prepare("
    SELECT section_id 
    FROM users 
    WHERE id = ? AND role = 'coordinator' AND status = 'active' 
    LIMIT 1
");
$stmt->execute([$coord_id]);
$coord = $stmt->fetch();
$coord_section_id = $coord['section_id'] ?? null;

// ===== Same department එකේ ඉන්න ALL agents (ඕනෑම coordinator කෙනෙක් add කළත්) =====
$agents = [];

if (!empty($coord_section_id)) {
    $sql = "
        SELECT u.*, 
               s.name AS section_name,
               c.name AS added_by_name,
               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) AS total_tasks,
               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status IN ('PENDING','IN_PROGRESS')) AS active_tasks
        FROM users u
        LEFT JOIN sections s ON s.id = u.section_id
        LEFT JOIN users c ON c.id = u.coordinator_id
        WHERE u.role = 'user'
          AND u.section_id = ?
        ORDER BY u.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$coord_section_id]);
    $agents = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-people"></i> My Agents</h2>
    <a href="add-agent.php" class="btn btn-coral">
        <i class="bi bi-person-plus"></i> Add Agent
    </a>
</div>

<?php if (empty($coord_section_id)): ?>
    <div class="alert alert-warning">
        No department/section assigned to you. Please contact Admin.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Section</th>
                    <th>Added By</th>
                    <th>Total Tasks</th>
                    <th>Active</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agents)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            No agents found in your department.
                            <?php if (!empty($coord_section_id)): ?>
                                Click <strong>Add Agent</strong> to create one.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($agents as $i => $a): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($a['name']) ?></strong></td>
                        <td><?= e($a['email']) ?></td>
                        <td><?= e($a['phone'] ?? '—') ?></td>
                        <td><?= e($a['section_name'] ?? '—') ?></td>
                        <td>
                            <?php if ((int)$a['coordinator_id'] === $coord_id): ?>
                                <span class="badge bg-info text-dark">You</span>
                            <?php else: ?>
                                <?= e($a['added_by_name'] ?? '—') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$a['total_tasks'] ?></td>
                        <td>
                            <span class="badge bg-primary"><?= (int)$a['active_tasks'] ?></span>
                        </td>
                        <td>
                            <?php if (($a['status'] ?? '') === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="edit-agent.php?id=<?= (int)$a['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger delete-agent-btn"
                                        title="Delete"
                                        data-id="<?= (int)$a['id'] ?>"
                                        data-name="<?= e($a['name']) ?>"
                                        data-tasks="<?= (int)$a['total_tasks'] ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteAgentModal">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Agent Modal -->
<div class="modal fade" id="deleteAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="../actions/delete-agent.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <input type="hidden" name="agent_id" id="deleteAgentId" value="">

            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Delete Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Are you sure you want to delete <strong id="deleteAgentName"></strong>?</p>
                <div class="alert alert-danger mb-0 py-2" id="deleteAgentTaskWarn" style="display:none;">
                    This agent has <strong id="deleteAgentTaskCount">0</strong> task(s).
                    They will be <strong>permanently deleted</strong> together with the agent. This cannot be undone.
                    If you only want to stop the agent from logging in, set the status to <strong>Inactive</strong> in Edit instead.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.delete-agent-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var tasks = parseInt(this.getAttribute('data-tasks') || '0', 10);
        document.getElementById('deleteAgentId').value = this.getAttribute('data-id');
        document.getElementById('deleteAgentName').textContent = this.getAttribute('data-name');
        document.getElementById('deleteAgentTaskCount').textContent = tasks;
        document.getElementById('deleteAgentTaskWarn').style.display = tasks > 0 ? 'block' : 'none';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>