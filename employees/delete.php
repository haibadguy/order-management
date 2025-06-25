<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID nhân viên
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID nhân viên không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Không cho phép xóa tài khoản admin mặc định
    $stmt = mysqli_prepare($conn, 'SELECT * FROM employee WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $employee = mysqli_fetch_assoc($result);

    if (!$employee) {
        setMessage('Không tìm thấy nhân viên', 'danger');
        header('Location: index.php');
        exit();
    }

    if ($employee['email'] === 'admin@example.com') {
        setMessage('Không thể xóa tài khoản admin mặc định', 'danger');
        header('Location: index.php');
        exit();
    }

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM `order` WHERE employee_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $orderCount = $row['total'];

    if ($orderCount > 0) {
        setMessage('Không thể xóa nhân viên này vì đã có đơn hàng liên quan', 'danger');
        header('Location: index.php');
        exit();
    }

    $stmt = mysqli_prepare($conn, 'DELETE FROM employee WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    setMessage('Xóa nhân viên thành công');
} catch (Exception $e) {
    setMessage('Lỗi khi xóa nhân viên: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 