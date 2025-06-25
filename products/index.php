<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Thiết lập tiêu đề trang
$page_title = "Quản Lý Sản Phẩm";

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$category_id = intval($_GET['category_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Lấy danh sách danh mục để lọc    
    $categories = [];
    $result = mysqli_query($conn, 'SELECT id, name FROM category WHERE is_active = 1 ORDER BY name');
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[$row['id']] = $row['name'];
    }

    // Đếm tổng số sản phẩm
    $countQuery = 'SELECT COUNT(*) as total FROM product p';
    $params = [];
    $conditions = [];
    
    if ($search) {
        $conditions[] = '(p.name LIKE ? OR p.brand LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($category_id) {
        $conditions[] = 'p.category_id = ?';
        $params[] = $category_id;
    }
    
    if (!empty($conditions)) {
        $countQuery .= ' WHERE ' . implode(' AND ', $conditions);
    }
    
    $stmt = mysqli_prepare($conn, $countQuery);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $totalProducts = $row['total'];
    $totalPages = ceil($totalProducts / $perPage);

    // Lấy danh sách sản phẩm
    $query = '
        SELECT p.*, c.name as category_name 
        FROM product p 
        LEFT JOIN category c ON p.category_id = c.id
    ';
    
    if (!empty($conditions)) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }
    
    $query .= ' ORDER BY p.id ASC LIMIT ? OFFSET ?';
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
} catch (Exception $e) {
    setMessage('Lỗi khi lấy danh sách sản phẩm: ' . $e->getMessage(), 'danger');
    $products = [];
    $totalPages = 0;
}

require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản Lý Sản Phẩm</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm Sản Phẩm
        </a>
    </div>

    <!-- Tìm kiếm và lọc -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" 
                               placeholder="Tìm theo tên sản phẩm" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category_id" class="form-select">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $category_id == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Tìm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách sản phẩm -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên Sản Phẩm</th>
                            <th>Danh Mục</th>
                            <th>Giá Bán</th>
                            <th>Giá KM</th>
                            <th>Tồn Kho</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" class="text-center">Không có sản phẩm nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <?php
                                            // Kiểm tra nếu đường dẫn ảnh đã có 'uploads/products/'
                                            $imagePath = $product['image'];
                                            if (strpos($imagePath, 'uploads/products/') === 0) {
                                                $imagePath = '../' . $imagePath;
                                            } else {
                                                $imagePath = '../uploads/products/' . $imagePath;
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 class="img-thumbnail" style="max-width: 50px;">
                                        <?php else: ?>
                                            <span class="text-muted">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                        <?php if ($product['is_featured']): ?>
                                            <span class="badge bg-warning">Nổi bật</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Chưa phân loại'); ?></td>
                                    <td><?php echo number_format($product['price']); ?>đ</td>
                                    <td>
                                        <?php if ($product['sale_price']): ?>
                                            <?php echo number_format($product['sale_price']); ?>đ
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['stock'] <= 0): ?>
                                            <span class="text-danger"><?php echo $product['stock']; ?></span>
                                        <?php else: ?>
                                            <?php echo $product['stock']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['is_active']): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-danger delete-confirm" title="Xóa"
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?');">
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
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>">
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
            if (!confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
                e.preventDefault();
            }
        });
    });
</script>
</div>
</body>
</html> 