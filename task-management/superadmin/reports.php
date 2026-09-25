<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Reports';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

// ===== Summary Stats =====
$totalUsers        = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins       = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalCoordinators = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'coordinator'")->fetchColumn();
$totalAgents       = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('agent','user')")->fetchColumn();

$totalTasks        = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$completedTasks    = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'COMPLETED'")->fetchColumn();
$pendingTasks      = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'PENDING'")->fetchColumn();
$inProgressTasks   = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'IN_PROGRESS'")->fetchColumn();
$overdueTasks      = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED')")->fetchColumn();

$completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

// ===== Chart Data =====
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
    WHERE u.role IN ('user', 'agent')
    GROUP BY u.id, u.name
    ORDER BY cnt DESC
    LIMIT 10
")->fetchAll();

$by_status = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM tasks
    GROUP BY status
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-bar-chart"></i> Reports</h2>
</div>

<!-- ===== Top Stats ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Total Users</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $totalUsers; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Admins</div>
                <div class="fs-3 fw-bold text-danger"><?php echo $totalAdmins; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Coordinators</div>
                <div class="fs-3 fw-bold text-info"><?php echo $totalCoordinators; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Agents</div>
                <div class="fs-3 fw-bold text-success"><?php echo $totalAgents; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Total Tasks</div>
                <div class="fs-3 fw-bold"><?php echo $totalTasks; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Completion Rate</div>
                <div class="fs-3 fw-bold text-success"><?php echo $completionRate; ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Task Status Stats ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Completed</div>
                <div class="fs-3 fw-bold text-success"><?php echo $completedTasks; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">In Progress</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $inProgressTasks; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Pending</div>
                <div class="fs-3 fw-bold text-warning"><?php echo $pendingTasks; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Overdue</div>
                <div class="fs-3 fw-bold text-danger"><?php echo $overdueTasks; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Charts ===== -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pie-chart"></i> Tasks by Section
            </div>
            <div class="card-body">
                <canvas id="sectionChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart"></i> Tasks by Agent (Top 10)
            </div>
            <div class="card-body">
                <canvas id="userChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const sectionLabels = <?php echo json_encode(array_column($by_section, 'name')); ?>;
const sectionData   = <?php echo json_encode(array_column($by_section, 'cnt')); ?>;

const userLabels = <?php echo json_encode(array_column($by_user, 'name')); ?>;
const userData   = <?php echo json_encode(array_column($by_user, 'cnt')); ?>;

// Section Chart
new Chart(document.getElementById('sectionChart'), {
    type: 'bar',
    data: {
        labels: sectionLabels,
        datasets: [{
            label: 'Tasks',
            data: sectionData,
            backgroundColor: '#1a365d',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// User Chart
new Chart(document.getElementById('userChart'), {
    type: 'bar',
    data: {
        labels: userLabels,
        datasets: [{
            label: 'Tasks',
            data: userData,
            backgroundColor: '#e85a4f',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>