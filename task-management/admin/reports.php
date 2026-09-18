<?php
$page_title = 'Reports';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

$total     = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$completed = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='COMPLETED'")->fetchColumn();
$pending   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='PENDING'")->fetchColumn();
$progress  = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='IN_PROGRESS'")->fetchColumn();
$overdue   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED')")->fetchColumn();

$rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

$by_section = $pdo->query("
    SELECT s.name, COUNT(t.id) AS cnt
    FROM sections s
    LEFT JOIN tasks t ON t.section_id = s.id
    GROUP BY s.id, s.name
    ORDER BY cnt DESC
")->fetchAll();

$by_user = $pdo->query("
    SELECT u.name, COUNT(t.id) AS cnt
    FROM users u
    LEFT JOIN tasks t ON t.assigned_to = u.id
    WHERE u.role = 'user'
    GROUP BY u.id, u.name
    ORDER BY cnt DESC
")->fetchAll();
?>

<div class="mb-4">
    <h2 class="mb-0"><i class="bi bi-bar-chart"></i> Reports</h2>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card stat-card"><div class="card-body text-center"><div class="text-muted small">Total</div><div class="fs-3 fw-bold"><?php echo $total; ?></div></div></div></div>
    <div class="col-md-2"><div class="card stat-card"><div class="card-body text-center"><div class="text-muted small">Completed</div><div class="fs-3 fw-bold text-success"><?php echo $completed; ?></div></div></div></div>
    <div class="col-md-2"><div class="card stat-card"><div class="card-body text-center"><div class="text-muted small">Pending</div><div class="fs-3 fw-bold text-warning"><?php echo $pending; ?></div></div></div></div>
    <div class="col-md-2"><div class="card stat-card"><div class="card-body text-center"><div class="text-muted small">In Progress</div><div class="fs-3 fw-bold text-primary"><?php echo $progress; ?></div></div></div></div>
    <div class="col-md-2"><div class="card stat-card"><div class="card-body text-center"><div class="text-muted small">Overdue</div><div class="fs-3 fw-bold text-danger"><?php echo $overdue; ?></div></div></div></div>
    <div class="col-md-2"><div class="card stat-card"><div class="card-body text-center"><div class="text-muted small">Completion Rate</div><div class="fs-3 fw-bold"><?php echo $rate; ?>%</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Tasks by Section</div>
            <div class="card-body">
                <canvas id="sectionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Tasks by User</div>
            <div class="card-body">
                <canvas id="userChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const sectionLabels = <?php echo json_encode(array_column($by_section, 'name')); ?>;
const sectionData = <?php echo json_encode(array_column($by_section, 'cnt')); ?>;
const userLabels = <?php echo json_encode(array_column($by_user, 'name')); ?>;
const userData = <?php echo json_encode(array_column($by_user, 'cnt')); ?>;

new Chart(document.getElementById('sectionChart'), {
    type: 'bar',
    data: { labels: sectionLabels, datasets: [{ label: 'Tasks', data: sectionData, backgroundColor: '#1a365d' }] },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('userChart'), {
    type: 'bar',
    data: { labels: userLabels, datasets: [{ label: 'Tasks', data: userData, backgroundColor: '#e85a4f' }] },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
