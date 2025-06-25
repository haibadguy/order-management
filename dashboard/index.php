<?php
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Kết nối DB
require_once '../includes/helpers.php'; // Giả sử helpers.php có $conn

// Tổng doanh thu tháng này (chỉ tính đơn hoàn thành)
$revenue_sql = "SELECT COALESCE(SUM(total_amount),0) as revenue FROM `order` WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$revenue = mysqli_fetch_assoc(mysqli_query($conn, $revenue_sql));

// Số đơn hàng tháng này
$order_sql = "SELECT COUNT(*) as total FROM `order` WHERE status NOT IN ('cancelled','refunded') AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$order_count = mysqli_fetch_assoc(mysqli_query($conn, $order_sql));

// Khách hàng mới tháng này
$customer_sql = "SELECT COUNT(*) as total FROM customer WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$customer_count = mysqli_fetch_assoc(mysqli_query($conn, $customer_sql));

// Tổng tồn kho
$stock_sql = "SELECT COALESCE(SUM(stock),0) as total FROM product WHERE is_active = 1";
$stock = mysqli_fetch_assoc(mysqli_query($conn, $stock_sql));

// Đơn hàng gần đây
$recent_orders_sql = "SELECT o.id, c.full_name, o.total_amount, o.status, o.created_at FROM `order` o LEFT JOIN customer c ON o.customer_id = c.id ORDER BY o.created_at DESC LIMIT 10";
$recent_orders = mysqli_query($conn, $recent_orders_sql);

// Sản phẩm bán chạy tháng này
$top_products_sql = "SELECT p.id, p.name, SUM(oi.quantity) as sold_qty, SUM(oi.quantity * oi.unit_price) as revenue, p.stock FROM order_item oi JOIN `order` o ON oi.order_id = o.id AND o.status NOT IN ('cancelled','refunded') AND MONTH(o.created_at) = MONTH(CURRENT_DATE()) AND YEAR(o.created_at) = YEAR(CURRENT_DATE()) JOIN product p ON oi.product_id = p.id GROUP BY p.id, p.name, p.stock ORDER BY sold_qty DESC LIMIT 5";
$top_products = mysqli_query($conn, $top_products_sql);

// Khách hàng mới nhất
$new_customers_sql = "SELECT full_name, email, phone, created_at FROM customer ORDER BY created_at DESC LIMIT 5";
$new_customers = mysqli_query($conn, $new_customers_sql);

// Thêm mảng ánh xạ trạng thái sang tiếng Việt cho bảng đơn hàng gần đây
$statusText = [
    'pending' => 'Chờ xử lý',
    'processing' => 'Đang xử lý',
    'shipping' => 'Đang giao',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

// --- DỮ LIỆU CHO BIỂU ĐỒ --- //
// 1. Doanh thu theo ngày trong tháng này
$revenue_by_day_sql = "SELECT DAY(created_at) as day, SUM(total_amount) as revenue FROM `order` WHERE status NOT IN ('cancelled','refunded') AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) GROUP BY DAY(created_at) ORDER BY day ASC";
$revenue_by_day_result = mysqli_query($conn, $revenue_by_day_sql);
$revenue_days = [];
$revenue_values = [];
while ($row = mysqli_fetch_assoc($revenue_by_day_result)) {
    $revenue_days[] = $row['day'];
    $revenue_values[] = $row['revenue'];
}

// 2. Tỷ lệ trạng thái đơn hàng tháng này
$status_sql = "SELECT status, COUNT(*) as count FROM `order` WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) GROUP BY status";
$status_result = mysqli_query($conn, $status_sql);
$status_labels = [];
$status_counts = [];
$status_data = [
    'completed' => 0,
    'shipping' => 0,
    'pending' => 0,
    'processing' => 0,
    'cancelled' => 0
];
$total_status_orders = 0;
while ($row = mysqli_fetch_assoc($status_result)) {
    $key = $row['status'];
    if (isset($status_data[$key])) {
        $status_data[$key] = $row['count'];
        $total_status_orders += $row['count'];
    }
    $status_labels[] = $statusText[$row['status']] ?? ucfirst($row['status']);
    $status_counts[] = $row['count'];
}
// Tính phần trăm cho 5 trạng thái
$status_percent = [];
foreach (['completed','shipping','pending','processing','cancelled'] as $key) {
    $status_percent[$key] = $total_status_orders > 0 ? round($status_data[$key] * 100 / $total_status_orders, 1) : 0;
}
?>

<div class="container-fluid py-4">
    <!-- Khu vực tổng quan -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="text-uppercase text-secondary">Doanh thu tháng này</h6>
                    <h3 class="text-primary"><?php echo number_format($revenue['revenue'], 0, ',', '.'); ?>đ</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="text-uppercase text-secondary">Đơn hàng tháng này</h6>
                    <h3 class="text-success"><?php echo number_format($order_count['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="text-uppercase text-secondary">Khách hàng mới tháng</h6>
                    <h3 class="text-info"><?php echo number_format($customer_count['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="text-uppercase text-secondary">Tổng tồn kho</h6>
                    <h3 class="text-warning"><?php echo number_format($stock['total']); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Biểu đồ sinh động -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6>Doanh thu theo ngày trong tháng</h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6>Tỷ lệ trạng thái đơn hàng tháng này</h6>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="120"></canvas>
                    <div class="mt-3">
                        <table class="table table-sm table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Số lượng</th>
                                    <th class="text-end">Tỷ lệ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Hoàn thành</td>
                                    <td class="text-end"><?php echo $status_data['completed']; ?></td>
                                    <td class="text-end"><?php echo $status_percent['completed']; ?>%</td>
                                </tr>
                                <tr>
                                    <td>Đang giao</td>
                                    <td class="text-end"><?php echo $status_data['shipping']; ?></td>
                                    <td class="text-end"><?php echo $status_percent['shipping']; ?>%</td>
                                </tr>
                                <tr>
                                    <td>Đang xử lý</td>
                                    <td class="text-end"><?php echo $status_data['processing']; ?></td>
                                    <td class="text-end"><?php echo $status_percent['processing']; ?>%</td>
                                </tr>
                                <tr>
                                    <td>Chờ xử lý</td>
                                    <td class="text-end"><?php echo $status_data['pending']; ?></td>
                                    <td class="text-end"><?php echo $status_percent['pending']; ?>%</td>
                                </tr>
                                <tr>
                                    <td>Đã hủy</td>
                                    <td class="text-end"><?php echo $status_data['cancelled']; ?></td>
                                    <td class="text-end"><?php echo $status_percent['cancelled']; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Đơn hàng gần đây -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6>Đơn hàng gần đây</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Mã ĐH</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($recent_orders)): ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo number_format($row['total_amount'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo $statusText[$row['status']] ?? $row['status']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Sản phẩm bán chạy -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6>Sản phẩm bán chạy tháng này</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Tên sản phẩm</th>
                                    <th>Số lượng bán</th>
                                    <th>Doanh thu</th>
                                    <th>Tồn kho</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($top_products)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo number_format($row['sold_qty']); ?></td>
                                    <td><?php echo number_format($row['revenue'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo number_format($row['stock']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Khách hàng mới -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6>Khách hàng mới</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Họ tên</th>
                                    <th>Email</th>
                                    <th>Điện thoại</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($new_customers)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Doanh thu theo ngày
const revenueDays = <?php echo json_encode($revenue_days); ?>;
const revenueValues = <?php echo json_encode($revenue_values); ?>;
const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
new Chart(ctxRevenue, {
    type: 'line',
    data: {
        labels: revenueDays.map(day => 'Ngày ' + day),
        datasets: [{
            label: 'Doanh thu (VNĐ)',
            data: revenueValues,
            borderColor: 'rgba(66,135,245,1)',
            backgroundColor: 'rgba(66,135,245,0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            title: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('vi-VN') + 'đ';
                    }
                }
            }
        }
    }
});

// Tỷ lệ trạng thái đơn hàng
const statusLabels = <?php echo json_encode($status_labels); ?>;
const statusCounts = <?php echo json_encode($status_counts); ?>;
const ctxStatus = document.getElementById('statusChart').getContext('2d');
new Chart(ctxStatus, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: [
                '#f6c23e', '#36b9cc', '#4e73df', '#1cc88a', '#e74a3b', '#858796'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            title: { display: false }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>