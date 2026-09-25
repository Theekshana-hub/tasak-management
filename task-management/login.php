<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';


if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    
    if ($role === 'super_admin') {
        redirect('superadmin/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($role === 'coordinator') {
        redirect('coordinator/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrf)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            // Login success
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'super_admin') {
                redirect('superadmin/dashboard.php');
            } elseif ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } elseif ($user['role'] === 'coordinator') {
                redirect('coordinator/dashboard.php');
            } else {
                redirect('user/dashboard.php');
            }
        } else {
            // Login failed
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sipway Task Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
    <div class="card login-card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-check2-square text-navy" style="font-size: 3rem; color: #1a365d;"></i>
                <h3 class="mt-2 fw-bold" style="color: #1a365d;">Sipway Tasks</h3>
                <p class="text-muted">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="admin@sipway.com" required value="<?php echo e($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-coral w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>

            <div class="mt-4 text-center small text-muted">
                <p class="mb-1"><strong>Super Admin:</strong> superadmin@sipway.com / Super@123</p>
                <p class="mb-1"><strong>Admin:</strong> admin@sipway.com / Admin@123</p>
                <p class="mb-1"><strong>Coordinator:</strong> coord@sipway.com / Coord@123</p>
                <p class="mb-0"><strong>Agent:</strong> agent@sipway.com / Agent@123</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>