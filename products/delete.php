<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID sản phẩm
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID sản phẩm không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Lấy thông tin sản phẩm để xóa ảnh
    $stmt = mysqli_prepare($conn, 'SELECT image FROM product WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    if (!$product) {
        setMessage('Không tìm thấy sản phẩm', 'danger');
        header('Location: index.php');
        exit();
    }

    // Kiểm tra xem sản phẩm có trong đơn hàng nào không
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM order_item WHERE product_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $orderCount = $row['total'];

    if ($orderCount > 0) {
        setMessage('Không thể xóa sản phẩm này vì đã có trong đơn hàng', 'danger');
        header('Location: index.php');
        exit();
    }

    // Xóa sản phẩm
    $stmt = mysqli_prepare($conn, 'DELETE FROM product WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    // Xóa file ảnh nếu có
    if ($product['image'] && file_exists('../' . $product['image'])) {
        unlink('../' . $product['image']);
    }

    setMessage('Xóa sản phẩm thành công');
} catch (Exception $e) {
    setMessage('Lỗi khi xóa sản phẩm: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 