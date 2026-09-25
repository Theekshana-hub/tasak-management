<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';


if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'All Users';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();


$search     = trim($_GET['search'] ?? '');
$role       = $_GET['role'] ?? '';
$status     = $_GET['status'] ?? '';
$section_id = $_GET['section_id'] ?? '';


$sql = "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, s.name AS section_name
        FROM users u
        LEFT JOIN sections s ON s.id = u.section_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}

if ($status !== '') {
    $sql .= " AND u.status = ?";
    $params[] = $status;
}

if ($section_id !== '') {
    $sql .= " AND u.section_id = ?";
    $params[] = $section_id;
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get all sections for dropdown
$sections = $pdo->query("SELECT id, name FROM sections ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-people"></i> All Users</h2>
</div>

<!-- ===== Filter Card ===== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <!-- Search -->
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name or Email..." 
                           value="<?php echo e($search); ?>">
                </div>
            </div>

            <!-- Role Filter -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Role</label>
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="super_admin" <?php echo $role === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="coordinator" <?php echo $role === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Agent / User</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <!-- Section Filter -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Section</label>
                <select name="section_id" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ((string)$section_id === (string)$s['id']) ? 'selected' : ''; ?>>
                            <?php echo e($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Buttons -->
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ===== Users Table ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Section</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No users found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $i = 1; foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td class="fw-semibold"><?php echo e($u['name']); ?></td>
                                <td><?php echo e($u['email']); ?></td>
                                <td><?php echo e($u['section_name'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $roleClass = match($u['role']) {
                                        'super_admin' => 'warning',
                                        'admin'       => 'danger',
                                        'coordinator' => 'info',
                                        default       => 'primary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $roleClass; ?>">
                                        <?php echo e(ucfirst(str_replace('_', ' ', $u['role']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo e(ucfirst($u['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>