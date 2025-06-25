<?php
require_once '../includes/auth.php';
// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID danh mục
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID danh mục không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Kiểm tra xem danh mục có sản phẩm nào không
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM product WHERE category_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $productCount = $row['total'];

    if ($productCount > 0) {
        setMessage('Không thể xóa danh mục này vì đã có sản phẩm', 'danger');
        header('Location: index.php');
        exit();
    }

    // Lấy thông tin danh mục để xóa ảnh nếu có
    $stmt = mysqli_prepare($conn, 'SELECT image FROM category WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $category = mysqli_fetch_assoc($result);

    if (!$category) {
        setMessage('Không tìm thấy danh mục', 'danger');
        header('Location: index.php');
        exit();
    }

    // Xóa danh mục
    $stmt = mysqli_prepare($conn, 'DELETE FROM category WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    // Xóa file ảnh nếu có
    if ($category['image'] && file_exists('../' . $category['image'])) {
        unlink('../' . $category['image']);
    }

    setMessage('Xóa danh mục thành công');
} catch (Exception $e) {
    setMessage('Lỗi khi xóa danh mục: ' . $e->getMessage(), 'danger');
}

header('Location: index.php');
exit(); 