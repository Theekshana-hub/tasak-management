<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$pdo = getDB();
$unread_count = 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';

if (isset($_SESSION['user_id'])) {
    $unread_count = getUnreadCount($pdo, $_SESSION['user_id']);
}

// Dynamic CSS path
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (strpos($script_path, '/admin') !== false || 
    strpos($script_path, '/user') !== false || 
    strpos($script_path, '/coordinator') !== false ||
    strpos($script_path, '/superadmin') !== false) {
    $css_base = '../assets/css/';
} else {
    $css_base = 'assets/css/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : ''; ?>Sipway Task Management</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Main Custom CSS -->
    <link href="<?php echo $css_base; ?>style.css?v=3" rel="stylesheet">
    
    <!-- Super Admin CSS (only loads if file exists / always safe) -->
    <link href="<?php echo $css_base; ?>super-admin.css?v=1" rel="stylesheet">

    <style>
        /* ========== Sidebar Mobile Fix ========== */
        #sidebar {
            width: 260px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        #page-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            padding-top: 70px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1035;
        }

        @media (max-width: 991.98px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.show {
                transform: translateX(0);
            }
            #page-content {
                margin-left: 0 !important;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (min-width: 992px) {
            #sidebar {
                transform: translateX(0) !important;
            }
            .sidebar-overlay {
                display: none !important;
            }
        }
    </style>
</head>
<body>
   
    <nav class="navbar navbar-expand-lg navbar-dark bg-navy fixed-top">
        <div class="container-fluid">
           
            <button class="btn btn-link text-white d-lg-none me-2 p-1 border-0" 
                    type="button" 
                    id="sidebarToggle"
                    style="z-index: 1060; position: relative; line-height: 1;">
                <i class="bi bi-list fs-2"></i>
            </button>

            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-check2-square"></i> Sipway Tasks
            </a>
            
            <ul class="navbar-nav ms-auto align-items-center flex-row">
                
                <li class="nav-item dropdown me-2 me-lg-3">
                    <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-coral">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header fw-bold">Notifications</li>
                        <li><hr class="dropdown-divider"></li>
                        <?php
                        if (isset($_SESSION['user_id'])) {
                            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
                            $stmt->execute([$_SESSION['user_id']]);
                            $notifs = $stmt->fetchAll();
                            
                            if (empty($notifs)) {
                                echo '<li class="px-3 py-2 text-muted small">No notifications</li>';
                            } else {
                                foreach ($notifs as $n) {
                                    $bg = $n['is_read'] ? '' : 'bg-light';
                                    echo '<li class="' . $bg . '">';
                                    echo '<a class="dropdown-item small" href="#">';
                                    echo '<strong>' . e($n['title']) . '</strong><br>';
                                    echo '<span class="text-muted">' . e(substr($n['message'], 0, 60)) . '...</span><br>';
                                    echo '<small class="text-muted">' . formatDateTime($n['created_at']) . '</small>';
                                    echo '</a></li>';
                                }
                            }
                        }
                        ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-center small" href="<?php 
                                echo $user_role === 'admin' ? '../admin/notifications.php' : 
                                    ($user_role === 'coordinator' ? 'notifications.php' : '../user/notifications.php'); 
                            ?>">
                                View All Notifications
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <span class="d-none d-sm-inline"><?php echo e($user_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php 
                                if ($user_role === 'admin') echo '../admin/profile.php';
                                elseif ($user_role === 'coordinator') echo 'profile.php';
                                else echo '../user/profile.php';
                            ?>">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../actions/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="d-flex" id="wrapper">