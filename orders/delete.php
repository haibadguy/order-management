<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID đơn hàng
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID đơn hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Xóa đơn hàng
    $stmt = mysqli_prepare($conn, 'DELETE FROM `order` WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    if (mysqli_affected_rows($conn) > 0) {
        setMessage('Xóa đơn hàng thành công');
    } else {
        setMessage('Không tìm thấy đơn hàng', 'danger');
    }
} catch (Exception $e) {
    setMessage('Lỗi khi xóa đơn hàng: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 