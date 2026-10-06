<?php
require_once '../includes/super_admin_auth.php';

$pdo = getDB();

// ===== Save permissions (before HTML) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coord_id   = (int)($_POST['coordinator_id'] ?? 0);
    $section_ids = isset($_POST['section_ids']) && is_array($_POST['section_ids'])
        ? array_map('intval', $_POST['section_ids'])
        : [];

    if ($coord_id > 0) {
        // Verify coordinator
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'coordinator'");
        $check->execute([$coord_id]);
        if ($check->fetch()) {
            // Clear old access
            $del = $pdo->prepare("DELETE FROM coordinator_section_access WHERE coordinator_id = ?");
            $del->execute([$coord_id]);

            // Insert selected departments
            if (!empty($section_ids)) {
                $ins = $pdo->prepare("
                    INSERT INTO coordinator_section_access (coordinator_id, section_id)
                    VALUES (?, ?)
                ");
                foreach ($section_ids as $sid) {
                    if ($sid > 0) {
                        $ins->execute([$coord_id, $sid]);
                    }
                }
            }
            setFlash('success', 'Department access updated successfully.');
        }
    }
    header('Location: coordinator-permissions.php');
    exit;
}

// All active sections (departments)
$sections = $pdo->query("
    SELECT id, name FROM sections WHERE status = 'active' ORDER BY name
")->fetchAll();

// All coordinators
$coordinators = $pdo->query("
    SELECT u.id, u.name, u.email, u.status,
           (SELECT COUNT(*) FROM users a WHERE a.coordinator_id = u.id AND a.role = 'user') AS agent_count
    FROM users u
    WHERE u.role = 'coordinator'
    ORDER BY u.name ASC
")->fetchAll();

// Access map: coordinator_id => [section_id, ...]
$access_map = [];
$rows = $pdo->query("SELECT coordinator_id, section_id FROM coordinator_section_access")->fetchAll();
foreach ($rows as $r) {
    $access_map[(int)$r['coordinator_id']][] = (int)$r['section_id'];
}

$page_title = 'Coordinator Permissions';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-key"></i> Coordinator Permissions</h2>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Each coordinator can always assign tasks to <strong>their own agents</strong>.
    Tick extra <strong>departments (sections)</strong> if they should also assign work in those departments.
</div>

<?php if (empty($coordinators)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">No coordinators found.</div>
    </div>
<?php else: ?>
    <?php foreach ($coordinators as $c):
        $allowed = $access_map[(int)$c['id']] ?? [];
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo e($c['name']); ?></strong>
                <small class="text-muted ms-2"><?php echo e($c['email']); ?></small>
                <span class="badge bg-primary ms-2"><?php echo (int)$c['agent_count']; ?> agents</span>
                <?php if (($c['status'] ?? '') === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><?php echo e($c['status'] ?? '-'); ?></span>
                <?php endif; ?>
            </div>
            <span class="text-muted small">
                <?php echo count($allowed); ?> department(s) allowed
            </span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="coordinator_id" value="<?php echo (int)$c['id']; ?>">

                <?php if (empty($sections)): ?>
                    <p class="text-muted mb-0">No active departments found.</p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($sections as $s):
                            $checked = in_array((int)$s['id'], $allowed, true);
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="section_ids[]"
                                       value="<?php echo (int)$s['id']; ?>"
                                       id="c<?php echo (int)$c['id']; ?>_s<?php echo (int)$s['id']; ?>"
                                       <?php echo $checked ? 'checked' : ''; ?>>
                                <label class="form-check-label"
                                       for="c<?php echo (int)$c['id']; ?>_s<?php echo (int)$s['id']; ?>">
                                    <?php echo e($s['name']); ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-coral btn-sm">
                            <i class="bi bi-save"></i> Save for <?php echo e($c['name']); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>