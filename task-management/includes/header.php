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

// Role -> folder mapping (links work from any role folder)
$role_dirs = [
    'super_admin' => 'superadmin',
    'admin'       => 'admin',
    'coordinator' => 'coordinator',
];
$role_dir = $role_dirs[$user_role] ?? 'user';
$profile_link = '../' . $role_dir . '/profile.php';
$notif_link   = '../' . $role_dir . '/notifications.php';

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

    <!-- Super Admin CSS -->
    <link href="<?php echo $css_base; ?>super-admin.css?v=1" rel="stylesheet">

    <style>
        /* ========== Layout + Mobile Sidebar (overrides style.css) ========== */

        /* Navbar inner container: style.css .container-fluid padding navbar ekata apply wenna epa */
        .navbar > .container-fluid {
            padding: 0 1rem !important;
            height: 60px;
        }

        /* ----- Sidebar (desktop default) ----- */
        #sidebar {
            display: block !important;
            position: fixed !important;
            top: 60px !important;
            left: 0;
            width: 260px;
            height: calc(100vh - 60px) !important;
            min-height: 0 !important;
            padding-top: 0 !important;
            overflow-y: auto;
            z-index: 1040;
            transform: none;
            visibility: visible;
            transition: transform 0.3s ease, visibility 0.3s ease;
        }

        /* ----- Page content ----- */
        #page-content {
            margin-left: 260px;
            padding-top: 0;                      /* body already has padding-top:60px */
            min-height: calc(100vh - 60px);
            transition: margin-left 0.3s ease;
        }

        /* ----- Overlay ----- */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 36, 64, 0.55);
            z-index: 1035;
        }

        /* ----- Mobile / Tablet ----- */
        @media (max-width: 991.98px) {
            #sidebar {
                width: 260px;
                max-width: 85vw;
                transform: translateX(-100%);
                visibility: hidden;
                z-index: 1040;
            }
            #sidebar.show {
                transform: translateX(0);
                visibility: visible;
            }

            #page-content {
                margin-left: 0 !important;
                width: 100%;
            }

            .sidebar-overlay {
                display: block;
                top: 60px;
                z-index: 1035;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            body.sidebar-open {
                overflow: hidden;
            }
        }

        /* ----- Desktop: always show sidebar, never overlay ----- */
        @media (min-width: 992px) {
            #sidebar {
                transform: none !important;
                visibility: visible !important;
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

            <!-- Mobile sidebar toggle -->
            <button class="btn btn-link text-white d-lg-none me-2 p-1 border-0"
                    type="button"
                    id="sidebarToggle"
                    aria-label="Toggle sidebar"
                    style="line-height: 1;">
                <i class="bi bi-list fs-2"></i>
            </button>

            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-check2-square"></i> Sipway Tasks
            </a>

            <ul class="navbar-nav ms-auto align-items-center flex-row">

                <!-- Notifications -->
                <li class="nav-item dropdown me-2 me-lg-3">
                    <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-coral">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 320px; max-width: 92vw; max-height: 400px; overflow-y: auto;">
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
                            <a class="dropdown-item text-center small" href="<?php echo $notif_link; ?>">
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
                            <a class="dropdown-item" href="<?php echo $profile_link; ?>">
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

    <!-- Sidebar toggle script (sidebar.php eke elements load una passe wede karanawa) -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('sidebar');
        var toggle  = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');

        if (!sidebar || !toggle || !overlay) return;

        function openSidebar() {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            document.body.classList.add('sidebar-open');
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', closeSidebar);

        // Menu link ekak click kalama close wenna (mobile)
        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) closeSidebar();
            });
        });

        // Desktop size ekata giya nam reset
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) closeSidebar();
        });

        // ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSidebar();
        });
    });
    </script>