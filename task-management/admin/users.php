<?php
$page_title = 'Manage Users';
require_once '../includes/admin_auth.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$pdo = getDB();

$search     = trim($_GET['search'] ?? '');
$section_id = trim($_GET['section_id'] ?? '');
$role_filter = trim($_GET['role'] ?? '');

// Admin ට Super Admin users පෙන්නන්න එපා
$sql = "SELECT u.*, s.name AS section_name 
        FROM users u 
        LEFT JOIN sections s ON s.id = u.section_id 
        WHERE u.role != 'super_admin'";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($section_id !== '') {
    $sql .= " AND u.section_id = ?";
    $params[] = (int)$section_id;
}

if ($role_filter !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Sections for filter
$sections = $pdo->query("SELECT id, name FROM sections WHERE status = 'active' ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-people"></i> Users</h2>
    <a href="add-user.php" class="btn btn-coral">
        <i class="bi bi-person-plus"></i> Add User
    </a>
</div>

<form method="GET" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search name, email, phone..." 
                       value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="section_id" class="form-select">
                    <option value="">-- All Sections --</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($section_id == $s['id']) ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="role" class="form-select">
                    <option value="">-- All Roles --</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="coordinator" <?= $role_filter === 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                    <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>Agent / User</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-navy bg-navy text-white">Search</button>
                <a href="users.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

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
                    <th>Role</th>
                    <th>Status</th>
                    <th style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No users found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['phone'] ?? '—') ?></td>
                        <td><?= e($u['section_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php elseif ($u['role'] === 'coordinator'): ?>
                                <span class="badge bg-info text-dark">Coordinator</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Agent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($u['status'] ?? '') === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <!-- Edit Button -->
                                <a href="edit-user.php?id=<?= (int)$u['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <!-- Delete Button (own account එකට නැහැ) -->
                                <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                    <form method="POST" 
                                          action="../actions/delete-user.php" 
                                          onsubmit="return confirm('Are you sure you want to delete this user?');" 
                                          class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small align-self-center">You</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>