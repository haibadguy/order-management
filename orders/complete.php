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
    $stmt = mysqli_prepare($conn, '
        SELECT o.status, sd.shipping_status 
        FROM `order` o
        LEFT JOIN shipping_details sd ON o.id = sd.order_id
        WHERE o.id = ?
    ');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }

    if ($order['status'] !== 'shipping') {
        throw new Exception('Chỉ có thể hoàn thành đơn hàng đang giao');
    }

    if ($order['shipping_status'] !== 'shipped') {
        throw new Exception('Đơn hàng chưa được giao đi, không thể hoàn thành');
    }

    mysqli_begin_transaction($conn);
    try {
        // Cập nhật trạng thái đơn hàng
        $stmt = mysqli_prepare($conn, 'UPDATE `order` SET status = "completed", payment_status = "paid" WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Không thể cập nhật trạng thái đơn hàng');
        }

        // Cập nhật trạng thái giao hàng
        $stmt = mysqli_prepare($conn, '
            UPDATE shipping_details 
            SET shipping_status = "delivered", actual_delivery_date = CURRENT_TIMESTAMP 
            WHERE order_id = ?
        ');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Không thể cập nhật trạng thái giao hàng');
        }

        mysqli_commit($conn);
        setMessage('Đã chuyển đơn hàng sang trạng thái hoàn thành', 'success');
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
} catch (Exception $e) {
    setMessage('Lỗi: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 