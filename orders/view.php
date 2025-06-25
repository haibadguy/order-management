<?php
// Debug request
error_log('Request to view.php - Time: ' . date('Y-m-d H:i:s'));
error_log('Request URI: ' . $_SERVER['REQUEST_URI']);
error_log('HTTP_REFERER: ' . ($_SERVER['HTTP_REFERER'] ?? 'None'));

require_once '../includes/auth.php';
requireAdmin();

// Kiểm tra ID đơn hàng
$id = intval($_GET['id'] ?? 0);

// Debug
error_log('Order ID: ' . $id);
error_log('GET params: ' . print_r($_GET, true));

if (!$id) {
    setMessage('ID đơn hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

try {
    // Lấy thông tin đơn hàng
    $stmt = mysqli_prepare($conn, '
        SELECT o.*, c.full_name as customer_name, c.phone as customer_phone,
               e.full_name as employee_name,
               sd.shipping_address, sd.shipping_method, sd.shipping_fee,
               sd.shipping_status, sd.estimated_delivery_date, sd.actual_delivery_date
        FROM `order` o
        JOIN customer c ON o.customer_id = c.id
        JOIN employee e ON o.employee_id = e.id
        LEFT JOIN shipping_details sd ON o.id = sd.order_id
        WHERE o.id = ?
    ');

    // Debug query execution
    error_log('Executing order query for ID: ' . $id);
    
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Query execution failed: ' . mysqli_error($conn));
        throw new Exception('Failed to execute order query: ' . mysqli_error($conn));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        error_log('No order found for ID: ' . $id);
        setMessage('Không tìm thấy đơn hàng', 'danger');
        header('Location: index.php');
        exit();
    }

    // Debug order data
    error_log('Order data: ' . print_r($order, true));

    // Lấy chi tiết đơn hàng
    $stmt = mysqli_prepare($conn, '
        SELECT oi.*, p.name as product_name, p.price as current_price,
               c.name as category_name, p.stock as current_stock
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        JOIN category c ON p.category_id = c.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ');

    // Debug order items query
    error_log('Executing order items query for order ID: ' . $id);
    
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Order items query execution failed: ' . mysqli_error($conn));
        throw new Exception('Failed to execute order items query: ' . mysqli_error($conn));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $order_items = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Debug order items data
    error_log('Order items count: ' . count($order_items));

} catch (Exception $e) {
    setMessage('Lỗi khi lấy dữ liệu: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit();
}

// Thiết lập tiêu đề trang
$page_title = "Chi Tiết Đơn Hàng";
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="row">
                        <div class="col-6">
                            <h6>Chi Tiết Đơn Hàng #<?php echo $order['id']; ?></h6>
                        </div>
                        <div class="col-6 text-end">
                            <a href="index.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Quay Lại
                            </a>
                            <?php if (isAdmin() && $order['status'] === 'pending'): ?>
                            <a href="edit.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Sửa
                            </a>
                            <?php endif; ?>
                            <a href="reorder.php?id=<?php echo $order['id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-shopping-cart"></i> Mua Lại
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Thông tin cơ bản -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0">Thông Tin Đơn Hàng</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th>ID đơn hàng:</th>
                                            <td><?php echo $order['id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Ngày tạo:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Cập nhật:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Nhân viên:</th>
                                            <td><?php echo htmlspecialchars($order['employee_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Trạng thái:</th>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'pending' => 'warning',
                                                    'confirmed' => 'info',
                                                    'processing' => 'primary',
                                                    'shipping' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    'refunded' => 'secondary'
                                                ];
                                                $statusText = [
                                                    'pending' => 'Chờ xử lý',
                                                    'processing' => 'Đang xử lý',
                                                    'shipping' => 'Đang giao',
                                                    'completed' => 'Hoàn thành',
                                                    'cancelled' => 'Đã hủy',
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass[$order['status']] ?? 'secondary'; ?>">
                                                    <?php echo $statusText[$order['status']] ?? 'Không xác định'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0">Thông Tin Khách Hàng</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th>Tên khách hàng:</th>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Số điện thoại:</th>
                                            <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phương thức giao hàng:</th>
                                            <td><?php echo htmlspecialchars($order['shipping_method']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Địa chỉ giao hàng:</th>
                                            <td><?php 
                                                $address_parts = explode(', ', $order['shipping_address']);
                                                // Lọc bỏ các phần rỗng và "Chọn..."
                                                $address_parts = array_filter($address_parts, function($part) {
                                                    return !empty(trim($part)) && !str_starts_with($part, 'Chọn');
                                                });
                                                
                                                // Sắp xếp lại thứ tự: số nhà, phường/xã, quận/huyện, tỉnh/thành phố
                                                $sorted_parts = [];
                                                foreach ($address_parts as $part) {
                                                    if (preg_match('/^(số|đường|ngõ|ngách|hẻm)/i', $part) || is_numeric(substr($part, 0, 1))) {
                                                        array_unshift($sorted_parts, $part); // Đặt số nhà, đường lên đầu
                                                    } elseif (preg_match('/(Tỉnh|Thành phố) /i', $part)) {
                                                        $sorted_parts[] = $part; // Đặt tỉnh/thành phố xuống cuối
                                                    } elseif (preg_match('/(Quận|Huyện|Thị xã) /i', $part)) {
                                                        array_splice($sorted_parts, -1, 0, $part); // Đặt quận/huyện trước tỉnh/thành phố
                                                    } else {
                                                        if (count($sorted_parts) == 0) {
                                                            array_unshift($sorted_parts, $part); // Nếu là số nhà không có prefix
                                                        } else {
                                                            array_splice($sorted_parts, 1, 0, $part); // Đặt phường/xã sau số nhà
                                                        }
                                                    }
                                                }
                                                echo nl2br(htmlspecialchars(implode(', ', $sorted_parts))); 
                                            ?></td>
                                        </tr>
                                        <tr>
                                            <th>Thời gian giao dự kiến:</th>
                                            <td>
                                                <?php 
                                                if ($order['estimated_delivery_date']) {
                                                    echo date('d/m/Y H:i', strtotime($order['estimated_delivery_date']));
                                                } else {
                                                    echo 'Chưa xác định';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php if ($order['status'] === 'completed'): ?>
                                        <tr>
                                            <th>Thời gian giao thực tế:</th>
                                            <td>
                                                <?php 
                                                if ($order['actual_delivery_date']) {
                                                    echo date('d/m/Y H:i', strtotime($order['actual_delivery_date']));
                                                    
                                                    // Tính và hiển thị chênh lệch thời gian
                                                    $estimated = new DateTime($order['estimated_delivery_date']);
                                                    $actual = new DateTime($order['actual_delivery_date']);
                                                    $diff = $actual->diff($estimated);
                                                    
                                                    if ($actual > $estimated) {
                                                        echo ' <span class="badge bg-danger">Trễ ' . $diff->format('%d ngày %h giờ') . '</span>';
                                                    } else {
                                                        echo ' <span class="badge bg-success">Đúng hẹn</span>';
                                                    }
                                                } else {
                                                    echo 'Chưa giao';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0">Thông Tin Thanh Toán</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th>Phương thức:</th>
                                            <td>
                                                <?php
                                                $paymentMethods = [
                                                    'cod' => 'Thanh toán khi nhận hàng (COD)',
                                                    'bank_transfer' => 'Chuyển khoản ngân hàng',
                                                    'momo' => 'Ví MoMo',
                                                    'zalopay' => 'ZaloPay'
                                                ];
                                                echo $paymentMethods[$order['payment_method']] ?? $order['payment_method'];
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Trạng thái:</th>
                                            <td>
                                                <?php
                                                $paymentStatusClass = [
                                                    'pending' => 'warning',
                                                    'paid' => 'success',
                                                    'failed' => 'danger',
                                                    'refunded' => 'info'
                                                ];
                                                $paymentStatusText = [
                                                    'pending' => 'Chưa thanh toán',
                                                    'paid' => 'Đã thanh toán',
                                                    'failed' => 'Thanh toán lỗi',
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $paymentStatusClass[$order['payment_status']] ?? 'secondary'; ?>">
                                                    <?php echo $paymentStatusText[$order['payment_status']] ?? $order['payment_status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tổng tiền hàng:</th>
                                            <td class="text-end"><?php echo formatCurrency($order['subtotal']); ?></td>
                                        </tr>
                                        <?php if ($order['discount_percentage'] > 0): ?>
                                        <tr>
                                            <th>Giảm giá (<?php echo number_format($order['discount_percentage'], 1); ?>%):</th>
                                            <td class="text-end text-danger">
                                                -<?php echo formatCurrency($order['subtotal'] * $order['discount_percentage'] / 100); ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Thuế (<?php echo number_format(($order['tax_amount'] / $order['subtotal']) * 100, 1); ?>%):</th>
                                            <td class="text-end"><?php echo formatCurrency($order['tax_amount']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phí vận chuyển:</th>
                                            <td class="text-end"><?php echo formatCurrency($order['shipping_fee']); ?></td>
                                        </tr>
                                        <tr class="border-top">
                                            <th>Tổng cộng:</th>
                                            <td class="text-end">
                                                <strong class="text-primary"><?php echo formatCurrency($order['total_amount']); ?></strong>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ghi chú -->
                    <?php if (!empty($order['notes'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Ghi Chú</h6>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Chi tiết sản phẩm -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Chi Tiết Sản Phẩm</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Sản phẩm</th>
                                            <th>Danh mục</th>
                                            <th class="text-end">Đơn giá</th>
                                            <th class="text-center">Số lượng</th>
                                            <th class="text-end">Thành tiền</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td class="text-end"><?php echo formatCurrency($item['unit_price']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end"><?php echo formatCurrency($item['unit_price'] * $item['quantity']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 