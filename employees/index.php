<?php
require_once '../includes/auth.php';

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Đếm tổng số nhân viên
    $countQuery = 'SELECT COUNT(*) as total FROM employee';
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
    $totalEmployees = $row['total'];
    $totalPages = ceil($totalEmployees / $perPage);

    // Lấy danh sách nhân viên
    $query = 'SELECT * FROM employee';
    
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
    $employees = mysqli_fetch_all($result, MYSQLI_ASSOC);
} catch (Exception $e) {
    setMessage('Lỗi khi lấy danh sách nhân viên: ' . $e->getMessage(), 'danger');
    $employees = [];
    $totalPages = 0;
}

// Thiết lập tiêu đề trang
$page_title = "Quản Lý Nhân Viên";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản Lý Nhân Viên</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm Nhân Viên
        </a>
    </div>

    <!-- Tìm kiếm -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="search-form">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" 
                           placeholder="Tìm kiếm theo tên, email hoặc số điện thoại" 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách nhân viên -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Email</th>
                            <th>Số Điện Thoại</th>
                            <th>Chức Vụ</th>
                            <th>Ngày Vào Làm</th>
                            <th>Ngày Tạo</th>
                            <th>Role</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Không có nhân viên nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo $employee['id']; ?></td>
                                    <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($employee['hire_date']): ?>
                                            <?php echo date('d/m/Y', strtotime($employee['hire_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($employee['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['role'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $employee['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $employee['id']; ?>" 
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
            if (!confirm('Bạn có chắc chắn muốn xóa nhân viên này?')) {
                e.preventDefault();
            }
        });
    });
</script>
</div>
</body>
</html> 