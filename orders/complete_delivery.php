<?php
require_once '../includes/auth.php';
requireAdmin();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID đơn hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    $stmt = mysqli_prepare($conn, 'SELECT status FROM `order` WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }

    if ($order['status'] !== 'shipping') {
        throw new Exception('Đơn hàng không ở trạng thái đang giao');
    }

    mysqli_begin_transaction($conn);

    // Cập nhật trạng thái đơn hàng thành completed
    $stmt = mysqli_prepare($conn, 'UPDATE `order` SET status = "completed" WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Không thể cập nhật trạng thái đơn hàng');
    }

    // Cập nhật thời gian giao hàng thực tế
    $actual_delivery = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conn, 'UPDATE shipping_details SET actual_delivery = ? WHERE order_id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $actual_delivery, $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Không thể cập nhật thời gian giao hàng thực tế');
    }

    mysqli_commit($conn);
    setMessage('Đã xác nhận giao hàng thành công', 'success');
} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    setMessage('Lỗi: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 