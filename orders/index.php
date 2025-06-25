<?php
require_once '../includes/auth.php';
requireAdmin();

// Thiết lập tiêu đề trang
$page_title = "Quản Lý Đơn Hàng";

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Khởi tạo các biến để tương thích với code cũ
$status = '';
$payment_status = '';
$date_range = '';

// Xây dựng điều kiện WHERE và tham số
$where_clause = '';
$params = [];

if ($search) {
    $where_clause = 'WHERE (c.full_name LIKE ? OR c.phone LIKE ? OR sd.shipping_address LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}

try {
    // Khởi tạo giá trị mặc định cho stats
    $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'processing_orders' => 0,
        'shipping_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'unpaid_orders' => 0,
        'total_revenue' => 0
    ];
    $revenue_change = 0;

    // Lấy thống kê tổng quan
    $stats_query = "
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN o.status = 'shipping' THEN 1 ELSE 0 END) as shipping_orders,
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(CASE WHEN o.payment_status = 'pending' THEN 1 ELSE 0 END) as unpaid_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM `order` o
        LEFT JOIN customer c ON o.customer_id = c.id
        LEFT JOIN shipping_details sd ON sd.order_id = o.id
        $where_clause
    ";
    
    $stmt = mysqli_prepare($conn, $stats_query);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats_result = mysqli_fetch_assoc($result);

    // Cập nhật stats với kết quả truy vấn
    if ($stats_result) {
        $stats = array_merge($stats, $stats_result);
    }

    // Tính toán doanh thu tháng trước để so sánh
    $last_month_query = "
        SELECT COALESCE(SUM(total_amount), 0) as last_month_revenue
        FROM `order` 
        WHERE YEAR(created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
        AND MONTH(created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
        AND status != 'cancelled'
    ";
    $last_month_result = mysqli_query($conn, $last_month_query);
    $last_month = mysqli_fetch_assoc($last_month_result);
    $last_month_revenue = $last_month['last_month_revenue'] ?: 0;

    // Tính phần trăm thay đổi
    if ($last_month_revenue > 0) {
        $current_month_revenue = $stats['total_revenue'] ?: 0;
        $revenue_change = round((($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100, 1);
    }

    // Đếm tổng số đơn hàng theo điều kiện lọc
    $count_query = "SELECT COUNT(DISTINCT o.id) as total FROM `order` o 
                    LEFT JOIN customer c ON o.customer_id = c.id 
                    LEFT JOIN shipping_details sd ON sd.order_id = o.id
                    $where_clause";
    
    $stmt = mysqli_prepare($conn, $count_query);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $row = mysqli_fetch_assoc($result);
    $totalOrders = $row['total'];
    $totalPages = ceil($totalOrders / $perPage);

    // Lấy danh sách đơn hàng
    $query = "
        SELECT DISTINCT o.id, o.status, o.payment_status, o.created_at, o.updated_at,
               o.subtotal, o.tax_amount, o.shipping_fee, o.discount_percentage, o.total_amount,
               c.full_name as customer_name, c.phone as customer_phone,
               sd.shipping_address,
               (SELECT COUNT(*) FROM order_item WHERE order_id = o.id) as total_items
        FROM `order` o
        LEFT JOIN customer c ON o.customer_id = c.id
        LEFT JOIN shipping_details sd ON sd.order_id = o.id
        $where_clause
        ORDER BY o.created_at DESC
        LIMIT ?, ?
    ";

    // Tạo bản sao của mảng tham số tìm kiếm
    $orderParams = $params;
    // Thêm tham số phân trang
    $orderParams[] = $offset;
    $orderParams[] = $perPage;

    $stmt = mysqli_prepare($conn, $query);
    if (!empty($orderParams)) {
        $types = str_repeat('s', count($orderParams));
        mysqli_stmt_bind_param($stmt, $types, ...$orderParams);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);

} catch (Exception $e) {
    setMessage('Lỗi khi lấy danh sách đơn hàng: ' . $e->getMessage(), 'danger');
    $orders = [];
    $totalPages = 0;
}

require_once '../includes/header.php';
?>

<div class="container-fluid my-4">
    <!-- Thống kê tổng quan -->
    <div class="row mb-3">
        <div class="col-6 col-md-3 mb-2">
            <div class="card stat-card text-center bg-light">
                <h6>Tổng đơn hàng</h6>
                <h3><?php echo number_format($stats['total_orders']); ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card stat-card text-center bg-light">
                <h6>Đơn chờ xử lý</h6>
                <h3><?php echo number_format($stats['pending_orders']); ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card stat-card text-center bg-light">
                <h6>Đang giao hàng</h6>
                <h3><?php echo number_format($stats['shipping_orders']); ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card stat-card text-center bg-light">
                <h6>Đơn hoàn thành</h6>
                <h3><?php echo number_format($stats['completed_orders']); ?></h3>
            </div>
        </div>
    </div>
    <style>
    .stat-card {
        min-height: 90px;
        padding: 1rem 0.5rem;
        border-radius: 0.5rem;
        box-shadow: none;
        border: 1px solid #e3e6f0;
    }
    .stat-card h6 {
        font-size: 0.95rem;
        margin-bottom: 0.25rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-card h3 {
        font-size: 1.5rem;
        margin-bottom: 0;
        color: #344767;
        font-weight: 600;
    }
    @media (max-width: 767.98px) {
        .stat-card h3 { font-size: 1.2rem; }
        .stat-card h6 { font-size: 0.85rem; }
    }
    </style>

    <!-- Header với nút thêm mới -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Danh sách đơn hàng</h4>
            <small class="text-muted">Quản lý tất cả đơn hàng trong hệ thống</small>
        </div>
        <?php if (isAdmin()): ?>
        <div>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm đơn hàng mới
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tìm kiếm -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="search" name="search" class="form-control" 
                           placeholder="Tìm theo tên, số điện thoại hoặc địa chỉ" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách đơn hàng -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Địa chỉ</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thanh toán</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Không có đơn hàng nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong class="text-primary">
                                                <?php echo formatCurrency($order['total_amount']); ?>
                                            </strong>
                                            <small class="text-muted">
                                                Tiền hàng: <?php echo formatCurrency($order['subtotal']); ?>
                                                <?php if ($order['discount_percentage'] > 0): ?>
                                                <br>Giảm giá: <?php echo number_format($order['discount_percentage'], 1); ?>%
                                                <?php endif; ?>
                                            </small>
                                        </div>  
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'shipping' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $statusText = [
                                            'pending' => 'Chờ xử lý',
                                            'processing' => 'Đang xử lý',
                                            'shipping' => 'Đang giao',
                                            'completed' => 'Hoàn thành',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo isset($statusClass[$order['status']]) ? $statusClass[$order['status']] : 'secondary'; ?>">
                                            <?php echo isset($statusText[$order['status']]) ? $statusText[$order['status']] : 'Không xác định'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $paymentClass = [
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger'
                                        ];
                                        $paymentText = [
                                            'pending' => 'Chưa thanh toán',
                                            'paid' => 'Đã thanh toán',
                                            'failed' => 'Thanh toán lỗi'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo isset($paymentClass[$order['payment_status']]) ? $paymentClass[$order['payment_status']] : 'secondary'; ?>">
                                            <?php echo isset($paymentText[$order['payment_status']]) ? $paymentText[$order['payment_status']] : $order['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($order['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-sm btn-info" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (isAdmin()): ?>
                                                <a href="edit.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-primary" title="Sửa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Xóa"
                                                   onclick="return confirm('Bạn có chắc muốn xóa đơn hàng này?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
    // Xác nhận hủy đơn
    document.querySelectorAll('.cancel-confirm').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) {
                e.preventDefault();
            }
        });
    });

    // Xác nhận xóa đơn
    document.querySelectorAll('.delete-confirm').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Bạn có chắc chắn muốn xóa đơn hàng này? Hành động này không thể hoàn tác!')) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?> 