<?php
$role = $_SESSION['user_role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="sidebar bg-navy text-white" id="sidebar">
    <div class="sidebar-header">
        <h5 class="mb-0">
            <i class="bi bi-building"></i> Sipway Group of Companies
        </h5>
        <small class="text-white-50">Task Management System</small>

        <?php if ($role === 'super_admin'): ?>
            <div class="mt-1">
                <span class="badge bg-warning text-dark" style="font-size:0.7rem">Managing Director</span>
            </div>
        <?php elseif ($role === 'coordinator'): ?>
            <div class="mt-1">
                <span class="badge bg-info text-dark" style="font-size:0.7rem">Executive/Coordinator</span>
            </div>
        <?php elseif ($role === 'admin'): ?>
            <div class="mt-1">
                <span class="badge bg-danger" style="font-size:0.7rem">Management</span>
            </div>
        <?php else: ?>
            <div class="mt-1">
                <span class="badge bg-primary" style="font-size:0.7rem">Assistant/Agent</span>
            </div>
        <?php endif; ?>
    </div>

    <ul class="nav flex-column">
        <?php if ($role === 'super_admin'): ?>
            <!-- ========== SUPER ADMIN MENU ========== -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['admins.php','add-admin.php','create-admin.php','edit-admin.php']) ? 'active' : ''; ?>" href="admins.php">
                    <i class="bi bi-shield-lock"></i> Manage Admins
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['users.php','add-user.php','edit-user.php']) ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people"></i> All Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['sections.php','add-section.php','edit-section.php']) ? 'active' : ''; ?>" href="sections.php">
                    <i class="bi bi-diagram-3"></i> Sections
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['tasks.php','create-task.php','edit-task.php','task-details.php']) ? 'active' : ''; ?>" href="tasks.php">
                    <i class="bi bi-list-task"></i> All Tasks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>

        <?php elseif ($role === 'admin'): ?>
            <!-- ========== ADMIN MENU ========== -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['users.php','add-user.php','edit-user.php']) ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people"></i> Users & Coordinators
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['sections.php','add-section.php','edit-section.php']) ? 'active' : ''; ?>" href="sections.php">
                    <i class="bi bi-diagram-3"></i> Sections
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['tasks.php','create-task.php','edit-task.php','task-details.php']) ? 'active' : ''; ?>" href="tasks.php">
                    <i class="bi bi-list-task"></i> All Tasks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'create-task.php' ? 'active' : ''; ?>" href="create-task.php">
                    <i class="bi bi-plus-circle"></i> Create Task
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>

        <?php elseif ($role === 'coordinator'): ?>
            <!-- ========== COORDINATOR MENU ========== -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['agents.php','add-agent.php','edit-agent.php']) ? 'active' : ''; ?>" href="agents.php">
                    <i class="bi bi-people"></i> My Agents
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['my-tasks.php','my-task-details.php']) ? 'active' : ''; ?>" href="my-tasks.php">
                    <i class="bi bi-person-check"></i> My Tasks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['tasks.php','create-task.php','edit-task.php','task-details.php']) ? 'active' : ''; ?>" href="tasks.php">
                    <i class="bi bi-list-task"></i> Team Tasks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'create-task.php' ? 'active' : ''; ?>" href="create-task.php">
                    <i class="bi bi-plus-circle"></i> Assign Task
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>

        <?php else: ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'my-tasks.php' ? 'active' : ''; ?>" href="my-tasks.php">
                    <i class="bi bi-list-task"></i> My Tasks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my-tasks.php?status=PENDING">
                    <i class="bi bi-hourglass"></i> Pending
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my-tasks.php?status=IN_PROGRESS">
                    <i class="bi bi-arrow-repeat"></i> In Progress
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my-tasks.php?status=COMPLETED">
                    <i class="bi bi-check-circle"></i> Completed
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>
        <?php endif; ?>

        <!-- Logout -->
        <li class="nav-item mt-3">
            <a class="nav-link text-danger" href="../actions/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</div>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Page Content -->
<div id="page-content" class="flex-grow-1">
    <div class="container-fluid">

        <?php
        $flash = getFlash();
        if ($flash):
        ?>
        <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo e($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>