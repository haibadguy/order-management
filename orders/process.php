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
    $stmt = mysqli_prepare($conn, 'SELECT status, payment_method FROM `order` WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }

    if ($order['status'] !== 'pending') {
        throw new Exception('Đơn hàng không ở trạng thái chờ xử lý');
    }

    mysqli_begin_transaction($conn);

    // Cập nhật trạng thái đơn hàng
    $stmt = mysqli_prepare($conn, 'UPDATE `order` SET status = "processing", payment_status = ? WHERE id = ?');
    $payment_status = ($order['payment_method'] === 'cod') ? 'pending' : 'paid';
    mysqli_stmt_bind_param($stmt, 'si', $payment_status, $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Không thể cập nhật trạng thái đơn hàng');
    }

    // Cập nhật thời gian giao hàng dự kiến (3 ngày từ hiện tại)
    $estimated_delivery = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    // Kiểm tra xem đã có shipping_details chưa
    $stmt = mysqli_prepare($conn, 'SELECT id FROM shipping_details WHERE order_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Cập nhật estimated_delivery nếu đã có shipping_details
        $stmt = mysqli_prepare($conn, 'UPDATE shipping_details SET estimated_delivery = ? WHERE order_id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $estimated_delivery, $id);
    } else {
        // Thêm mới shipping_details nếu chưa có
        $stmt = mysqli_prepare($conn, 'INSERT INTO shipping_details (order_id, estimated_delivery) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'is', $id, $estimated_delivery);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Không thể cập nhật thời gian giao hàng dự kiến');
    }

    mysqli_commit($conn);
    setMessage('Đã chuyển đơn hàng sang trạng thái đang xử lý', 'success');
} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    setMessage('Lỗi: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 