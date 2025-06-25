<?php
require_once '../includes/auth.php';

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Đếm tổng số khách hàng
    $countQuery = 'SELECT COUNT(*) as total FROM customer';
    $params = [];
    
    if ($search) {
        $countQuery .= ' WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?';
        $params = ["%$search%", "%$search%", "%$search%"];
    }
    
    $stmt = mysqli_prepare($conn, $countQuery);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $totalCustomers = $row['total'];
    $totalPages = ceil($totalCustomers / $perPage);

    // Lấy danh sách khách hàng
    $query = 'SELECT * FROM customer';
    
    if ($search) {
        $query .= ' WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?';
    }
    
    $query .= ' ORDER BY id ASC LIMIT ? OFFSET ?';
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customers = mysqli_fetch_all($result, MYSQLI_ASSOC);
} catch (Exception $e) {
    setMessage('Lỗi khi lấy danh sách khách hàng: ' . $e->getMessage(), 'danger');
    $customers = [];
    $totalPages = 0;
}

// Thiết lập tiêu đề trang
$page_title = "Quản Lý Khách Hàng";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản Lý Khách Hàng</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm Khách Hàng
        </a>
    </div>

    <!-- Tìm kiếm -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="search-form">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" 
                           placeholder="Tìm kiếm theo tên, email hoặc số điện thoại..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách khách hàng -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Họ Tên</th>
                            <th>Email</th>
                            <th>Điện Thoại</th>
                            <th>Trạng Thái</th>
                            <th>Ngày Tạo</th>
                            <th>Cập nhật</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Không có khách hàng nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo $customer['id']; ?></td>
                                    <td>
                                        <?php if ($customer['avatar']): ?>
                                            <img src="<?php echo htmlspecialchars($customer['avatar']); ?>" 
                                                 alt="<?php echo htmlspecialchars($customer['full_name']); ?>" 
                                                 class="img-thumbnail" style="max-width: 50px;">
                                        <?php else: ?>
                                            <span class="text-muted">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? 'Chưa cập nhật'); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'active' => 'success',
                                            'inactive' => 'warning',
                                            'blocked' => 'danger'
                                        ];
                                        $statusText = [
                                            'active' => 'Hoạt động',
                                            'inactive' => 'Không hoạt động',
                                            'blocked' => 'Đã khóa'
                                        ];
                                        $status = $customer['status'];
                                        echo $statusText[$status];
                                        ?>
                                    </td>
                                    <td><?php echo formatDate($customer['created_at']); ?></td>
                                    <td><?php echo formatDate($customer['updated_at']); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $customer['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $customer['id']; ?>" 
                                           class="btn btn-sm btn-danger delete-confirm" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Phân trang -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
    // Xác nhận xóa
    document.querySelectorAll('.delete-confirm').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Bạn có chắc chắn muốn xóa khách hàng này?')) {
                e.preventDefault();
            }
        });
    });
</script>
</div>
