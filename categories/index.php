<?php
require_once '../includes/auth.php';


// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Đếm tổng số danh mục
    $countQuery = 'SELECT COUNT(*) as total FROM category';
    $params = [];
    
    if ($search) {
        $countQuery .= ' WHERE name LIKE ?';
        $params[] = "%$search%";
    }
    
    $stmt = mysqli_prepare($conn, $countQuery);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $totalCategories = $row['total'];
    $totalPages = ceil($totalCategories / $perPage);

    // Lấy danh sách danh mục
    $query = 'SELECT * FROM category';
    
    if ($search) {
        $query .= ' WHERE name LIKE ?';
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
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
} catch (Exception $e) {
    setMessage('Lỗi khi lấy danh sách danh mục: ' . $e->getMessage(), 'danger');
    $categories = [];
    $totalPages = 0;
}

// Thiết lập tiêu đề trang
$page_title = "Quản Lý Danh Mục";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản Lý Danh Mục</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm Danh Mục
        </a>
    </div>

    <!-- Tìm kiếm -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="search-form">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" 
                           placeholder="Tìm kiếm theo tên danh mục" 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách danh mục -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên Danh Mục</th>
                            <th>Mô Tả</th>
                            <th>Hình Ảnh</th>
                            <th>Trạng Thái</th>
                            <th>Ngày Tạo</th>
                            <th>Cập Nhật</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Không có danh mục nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($category['image']): ?>
                                            <img src="../<?php echo htmlspecialchars($category['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($category['name']); ?>" 
                                                 class="img-thumbnail" style="max-width: 50px; max-height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="../assets/img/no-image.jpg" 
                                                 alt="No image" 
                                                 class="img-thumbnail" style="max-width: 50px; max-height: 50px; object-fit: cover;">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($category['is_active']): ?>
                                            <?php echo 'Hoạt động'?></span>
                                        <?php else: ?>
                                            <?php echo 'Không hoạt động'?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($category['created_at']); ?></td>
                                    <td><?php echo formatDate($category['updated_at']); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $category['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $category['id']; ?>" 
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
            if (!confirm('Bạn có chắc chắn muốn xóa danh mục này?')) {
                e.preventDefault();
            }
        });
    });
</script>
</div>
</body>
</html> 