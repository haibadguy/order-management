<?php
// Khởi tạo session
session_start();

// Định nghĩa BASE_PATH
define('BASE_PATH', '/order-management');

// Include helpers
require_once __DIR__ . '/helpers.php';

// Kết nối database
$conn = mysqli_connect('localhost', 'root', '', 'order_management', 3307);
if (!$conn) {
    die('Lỗi kết nối MySQL: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// Hàm kiểm tra đăng nhập đơn giản
function isLoggedIn() {
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Verify với database
    $user_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, role FROM employee WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $_SESSION['role'] = $user['role']; // Cập nhật role từ database
        return true;
    }
    return false;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

// Hàm kiểm tra quyền truy cập trang
function checkPageAccess($page_type = 'view') {
    if (!isLoggedIn()) {
        setMessage('Vui lòng đăng nhập để tiếp tục', 'warning');
        header('Location: ' . BASE_PATH . '/auth/login.php');
        exit();
    }

    // Nếu là trang edit/add/delete thì kiểm tra quyền admin
    if (in_array($page_type, ['edit', 'add', 'delete'])) {
        requireAdmin();
    }
}

function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/auth/login.php');
        exit();
    }
    
    if (!isAdmin()) {
        setMessage('Bạn không có quyền thực hiện thao tác này', 'danger');
        header('Location: ' . BASE_PATH . '/dashboard/index.php');
        exit();
    }
}

// Hàm đăng nhập
function doLogin($email, $password) {
    global $conn;
    
    $stmt = mysqli_prepare($conn, "SELECT * FROM employee WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    
    return false;
}

// Hàm đăng xuất
function doLogout() {
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hàm tạo CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hàm kiểm tra CSRF token
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Hàm set message
function setMessage($message, $type = 'success') {
    $_SESSION['message'] = [
        'text' => $message,
        'type' => $type
    ];
}

// Hàm get message
function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}

// Redirect nếu chưa đăng nhập
$excluded_paths = [
    BASE_PATH . '/auth/login.php',
    BASE_PATH . '/auth/logout.php',
    BASE_PATH . '/auth/forgot-password.php',
    BASE_PATH . '/auth/reset-password.php'
];

$current_path = $_SERVER['PHP_SELF'];

// Kiểm tra đăng nhập và quyền admin cho các trang cần quyền admin
$admin_paths = [
    BASE_PATH . '/orders/edit.php',
    BASE_PATH . '/orders/view.php',
    BASE_PATH . '/orders/index.php',
    BASE_PATH . '/orders/add.php',
    BASE_PATH . '/orders/delete.php',
    BASE_PATH . '/shipping/edit.php',
    BASE_PATH . '/products/edit.php',
    BASE_PATH . '/categories/edit.php',
    BASE_PATH . '/customers/edit.php',
    BASE_PATH . '/employees/edit.php'
];

if (in_array($current_path, $admin_paths)) {
    requireAdmin();
} elseif (!isLoggedIn() && !in_array($current_path, $excluded_paths)) {
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit();
} 