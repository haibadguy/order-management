<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID khách hàng
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID khách hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Kiểm tra xem khách hàng có đơn hàng nào không
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM `order` WHERE customer_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $orderCount = $row['total'];

    if ($orderCount > 0) {
        setMessage('Không thể xóa khách hàng này vì đã có đơn hàng', 'danger');
        header('Location: index.php');
        exit();
    }

    // Lấy thông tin khách hàng để xóa avatar nếu có
    $stmt = mysqli_prepare($conn, 'SELECT avatar FROM customer WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customer = mysqli_fetch_assoc($result);

    if ($customer) {
        // Xóa file avatar nếu có
        if ($customer['avatar'] && file_exists('../' . $customer['avatar'])) {
            unlink('../' . $customer['avatar']);
        }

        // Xóa khách hàng
        $stmt = mysqli_prepare($conn, 'DELETE FROM customer WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_affected_rows($conn) > 0) {
            setMessage('Xóa khách hàng thành công');
        } else {
            setMessage('Không thể xóa khách hàng', 'danger');
        }
    } else {
        setMessage('Không tìm thấy khách hàng', 'danger');
    }
} catch (Exception $e) {
    setMessage('Lỗi khi xóa khách hàng: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 