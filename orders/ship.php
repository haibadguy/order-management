<?php
require_once '../includes/auth.php';
requireAdmin();

// Kiểm tra ID đơn hàng
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID đơn hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Kiểm tra trạng thái đơn hàng
    $stmt = mysqli_prepare($conn, 'SELECT status FROM `order` WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }

    if ($order['status'] !== 'processing') {
        throw new Exception('Chỉ có thể chuyển sang giao hàng với đơn đang xử lý');
    }

    mysqli_begin_transaction($conn);
    try {
        // Cập nhật trạng thái đơn hàng
        $stmt = mysqli_prepare($conn, 'UPDATE `order` SET status = "shipping" WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Không thể cập nhật trạng thái đơn hàng');
        }

        // Cập nhật trạng thái giao hàng
        $stmt = mysqli_prepare($conn, 'UPDATE shipping_details SET shipping_status = "shipped" WHERE order_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Không thể cập nhật trạng thái giao hàng');
        }

        mysqli_commit($conn);
        setMessage('Đã chuyển đơn hàng sang trạng thái đang giao', 'success');
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
} catch (Exception $e) {
    setMessage('Lỗi: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 