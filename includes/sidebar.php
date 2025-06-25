<?php
require_once __DIR__ . '/auth.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit();
}
?>
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-cube"></i>
        <span>TechOrder</span>
    </div>
    <div class="menu">
        <!-- Dashboard -->
        <div class="menu-group">
            <a href="<?php echo BASE_PATH; ?>/dashboard/index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard/index.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Tổng quan</span>
            </a>
        </div>
        <!-- Sales Management -->
        <div class="menu-group">
            <div class="menu-header">Quản lý bán hàng</div>
            <a href="<?php echo BASE_PATH; ?>/orders/index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'orders/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Đơn hàng</span>
            </a>
        </div>
        <!-- Product Management -->
        <div class="menu-group">
            <div class="menu-header">Quản lý sản phẩm</div>
            <a href="<?php echo BASE_PATH; ?>/products/index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'products/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Sản phẩm</span>
            </a>
            <a href="<?php echo BASE_PATH; ?>/categories/index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'categories/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Danh mục</span>
            </a>
        </div>
        <!-- Customer Management -->
        <div class="menu-group">
            <div class="menu-header">Quản lý khách hàng</div>
            <a href="<?php echo BASE_PATH; ?>/customers/index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'customers/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Khách hàng</span>
            </a>
        </div>
        <!-- HR Management -->
        <div class="menu-group">
            <div class="menu-header">Quản lý nhân sự</div>
            <a href="<?php echo BASE_PATH; ?>/employees/index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'employees/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i>
                <span>Nhân viên</span>
            </a>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <?php endif; ?>
        <!-- Logout -->
        <div class="menu-group mt-auto">
            <a href="<?php echo BASE_PATH; ?>/auth/logout.php" class="menu-item text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Đăng xuất</span>
            </a>
        </div>
    </div>
</div> 