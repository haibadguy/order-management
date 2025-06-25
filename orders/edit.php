<?php
require_once '../includes/auth.php';
requireAdmin();

// Kiểm tra ID đơn hàng
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID đơn hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

// Lấy danh sách khách hàng cho dropdown
$customers_result = mysqli_query($conn, 'SELECT id, full_name, phone FROM customer ORDER BY full_name');
$customers = mysqli_fetch_all($customers_result, MYSQLI_ASSOC);

// Lấy danh sách sản phẩm cho dropdown
$products_result = mysqli_query($conn, '
    SELECT p.*, c.name as category_name 
    FROM product p 
    JOIN category c ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY p.name
');
$products = mysqli_fetch_all($products_result, MYSQLI_ASSOC);

// Lấy thông tin đơn hàng
try {
    $stmt = mysqli_prepare($conn, '
        SELECT o.*, c.full_name as customer_name, c.phone as customer_phone,
               e.full_name as employee_name,
               sd.shipping_address, sd.shipping_method, sd.shipping_fee,
               sd.shipping_status, sd.estimated_delivery_date, sd.actual_delivery_date,
               sd.province_code, sd.district_code, sd.ward_code
        FROM `order` o
        JOIN customer c ON o.customer_id = c.id
        JOIN employee e ON o.employee_id = e.id
        LEFT JOIN shipping_details sd ON o.id = sd.order_id
        WHERE o.id = ?
    ');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        setMessage('Không tìm thấy đơn hàng', 'danger');
        header('Location: index.php');
        exit();
    }

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
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order_items = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Parse địa chỉ cũ
    $old_address = $order['shipping_address'];
    $address_parts = array_reverse(explode(', ', $old_address));
    $province_name = $address_parts[0] ?? '';
    $district_name = $address_parts[1] ?? '';
    $ward_name = $address_parts[2] ?? '';
    $street = implode(', ', array_reverse(array_slice($address_parts, 3)));

    // Lấy mã code từ shipping_details hoặc từ API nếu không có
    $province_code = $order['province_code'];
    $district_code = $order['district_code'];
    $ward_code = $order['ward_code'];

    // Nếu không có mã code trong database, thử lấy từ API
    if (!$province_code || !$district_code || !$ward_code) {
        function callAPI($endpoint, $params = []) {
            $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/../api/" . $endpoint;
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            return json_decode($response, true);
        }

        // Lấy danh sách tỉnh/thành phố và tìm mã code
        $provinces = callAPI('address.php', ['action' => 'provinces']);
        if ($provinces) {
            foreach ($provinces as $province) {
                if ($province['name'] === $province_name) {
                    $province_code = $province['code'];
                    
                    // Lấy danh sách quận/huyện
                    $districts = callAPI('address.php', ['action' => 'districts', 'code' => $province_code]);
                    if ($districts) {
                        foreach ($districts as $district) {
                            if ($district['name'] === $district_name) {
                                $district_code = $district['code'];
                                
                                // Lấy danh sách phường/xã
                                $wards = callAPI('address.php', ['action' => 'wards', 'code' => $district_code]);
                                if ($wards) {
                                    foreach ($wards as $ward) {
                                        if ($ward['name'] === $ward_name) {
                                            $ward_code = $ward['code'];
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }
    }

} catch (Exception $e) {
    setMessage('Lỗi khi lấy dữ liệu: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validateCSRFToken($_POST['csrf_token'] ?? '');

    // Lấy dữ liệu từ form
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $payment_status = trim($_POST['payment_status'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $shipping_method = trim($_POST['shipping_method'] ?? 'standard');
    $shipping_status = trim($_POST['shipping_status'] ?? '');
    $shipping_fee = floatval($_POST['shipping_fee'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $products_data = $_POST['products'] ?? [];
    $estimated_delivery_date = trim($_POST['estimated_delivery_date'] ?? '');
    
    // Lấy mã tỉnh/huyện/xã
    $province_code = trim($_POST['province_code'] ?? '');
    $district_code = trim($_POST['district_code'] ?? '');
    $ward_code = trim($_POST['ward_code'] ?? '');

    // Nếu không có ngày giao dự kiến, set mặc định là 3 ngày từ hiện tại
    if (empty($estimated_delivery_date)) {
        $estimated_delivery_date = date('Y-m-d H:i:s', strtotime('+3 days'));
    } else {
        // Chuyển đổi từ datetime-local về định dạng MySQL
        $estimated_delivery_date = date('Y-m-d H:i:s', strtotime($estimated_delivery_date));
    }

    // Validate dữ liệu
    if (!$customer_id) {
        $errors['customer_id'] = 'Vui lòng chọn khách hàng';
    }

    if (empty($status)) {
        $errors['status'] = 'Vui lòng chọn trạng thái đơn hàng';
    }

    if (empty($payment_status)) {
        $errors['payment_status'] = 'Vui lòng chọn trạng thái thanh toán';
    }

    if (empty($shipping_address)) {
        $errors['shipping_address'] = 'Vui lòng nhập địa chỉ giao hàng';
    }

    if (empty($products_data)) {
        $errors['products'] = 'Vui lòng chọn ít nhất một sản phẩm';
    }

    // Nếu không có lỗi thì cập nhật đơn hàng
    if (empty($errors)) {
        try {
            mysqli_begin_transaction($conn);

            // Tính tổng tiền mới
            $subtotal = 0;
            foreach ($products_data as $product) {
                $subtotal += floatval($product['price']) * intval($product['quantity']);
            }

            $tax_amount = round($subtotal * 0.01); // 1% VAT
            $total_amount = $subtotal + $tax_amount + $shipping_fee;

            // Cập nhật đơn hàng
            $stmt = mysqli_prepare($conn, '
                UPDATE `order` SET 
                    customer_id = ?,
                    status = ?, 
                    payment_status = ?,
                    payment_method = ?,
                    subtotal = ?,
                    tax_amount = ?,
                    shipping_fee = ?,
                    total_amount = ?,
                    notes = ?
                WHERE id = ?
            ');
            mysqli_stmt_bind_param($stmt, 'isssddddsi',
                $customer_id, $status, $payment_status, $payment_method,
                $subtotal, $tax_amount, $shipping_fee, $total_amount,
                $notes, $id
            );
            mysqli_stmt_execute($stmt);

            // Cập nhật hoặc thêm mới shipping_details
            $check_stmt = mysqli_prepare($conn, 'SELECT id FROM shipping_details WHERE order_id = ?');
            mysqli_stmt_bind_param($check_stmt, 'i', $id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_fetch_assoc($result)) {
                // Cập nhật shipping_details hiện có
                $stmt = mysqli_prepare($conn, '
                    UPDATE shipping_details 
                    SET shipping_address = ?,
                        shipping_method = ?,
                        shipping_fee = ?,
                        shipping_status = ?,
                        estimated_delivery_date = ?,
                        province_code = ?,
                        district_code = ?,
                        ward_code = ?
                    WHERE order_id = ?
                ');
                mysqli_stmt_bind_param($stmt, 'ssdsssssi',
                    $shipping_address,
                    $shipping_method,
                    $shipping_fee,
                    $shipping_status,
                    $estimated_delivery_date,
                    $province_code,
                    $district_code,
                    $ward_code,
                    $id
                );
            } else {
                // Thêm mới shipping_details nếu chưa tồn tại
                $stmt = mysqli_prepare($conn, '
                    INSERT INTO shipping_details (
                        order_id, shipping_address, shipping_method,
                        shipping_fee, shipping_status, estimated_delivery_date,
                        province_code, district_code, ward_code
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                mysqli_stmt_bind_param($stmt, 'issdssssss',
                    $id,
                    $shipping_address,
                    $shipping_method,
                    $shipping_fee,
                    $shipping_status,
                    $estimated_delivery_date,
                    $province_code,
                    $district_code,
                    $ward_code
                );
            }
            mysqli_stmt_execute($stmt);

            // Xóa các sản phẩm cũ
            $stmt = mysqli_prepare($conn, 'DELETE FROM order_item WHERE order_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);

            // Thêm sản phẩm mới
            $stmt = mysqli_prepare($conn, '
                INSERT INTO order_item (order_id, product_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ');

            foreach ($products_data as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $price = floatval($product['price']);

                mysqli_stmt_bind_param($stmt, 'iiid', $id, $product_id, $quantity, $price);
                mysqli_stmt_execute($stmt);

                // Cập nhật số lượng tồn kho
                $update = mysqli_prepare($conn, '
                    UPDATE product 
                    SET stock = stock - ? 
                    WHERE id = ? AND stock >= ?
                ');
                mysqli_stmt_bind_param($update, 'iii', $quantity, $product_id, $quantity);
                mysqli_stmt_execute($update);
            }

            mysqli_commit($conn);
            setMessage('Cập nhật đơn hàng thành công');
            header('Location: view.php?id=' . $id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors['db'] = 'Lỗi khi cập nhật đơn hàng: ' . $e->getMessage();
        }
    }
}

// Thiết lập tiêu đề trang
$page_title = "Sửa Đơn Hàng #" . $order['id'];
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <h6 class="mb-0">Sửa Đơn Hàng #<?php echo $order['id']; ?></h6>
                        </div>
                        <div class="col-6 text-end">
                            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> Xem Chi Tiết
                            </a>
                            <a href="index.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Quay Lại
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (isset($errors['db'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="orderForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" id="old_province" value="<?php echo htmlspecialchars($province_name); ?>">
                        <input type="hidden" id="old_district" value="<?php echo htmlspecialchars($district_name); ?>">
                        <input type="hidden" id="old_ward" value="<?php echo htmlspecialchars($ward_name); ?>">
                        <input type="hidden" id="old_street" value="<?php echo htmlspecialchars($street); ?>">
                        <input type="hidden" id="old_province_code" value="<?php echo htmlspecialchars($province_code ?? ''); ?>">
                        <input type="hidden" id="old_district_code" value="<?php echo htmlspecialchars($district_code ?? ''); ?>">
                        <input type="hidden" id="old_ward_code" value="<?php echo htmlspecialchars($ward_code ?? ''); ?>">

                        <div class="row">
                            <!-- Thông tin cơ bản -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Thông Tin Cơ Bản</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="customer_id" class="form-label">Khách Hàng</label>
                                            <select class="form-select" id="customer_id" name="customer_id" required>
                                                <option value="">Chọn khách hàng</option>
                                                <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>"
                                                            <?php echo $order['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($customer['full_name'] . ' - ' . $customer['phone']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="status" class="form-label">Trạng Thái Đơn Hàng</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <?php
                                                $statusOptions = [
                                                    'pending' => 'Chờ xử lý',
                                                    'confirmed' => 'Đã xác nhận',
                                                    'processing' => 'Đang xử lý',
                                                    'shipping' => 'Đang giao',
                                                    'completed' => 'Hoàn thành',
                                                    'cancelled' => 'Đã hủy',
                                                    'refunded' => 'Đã hoàn tiền'
                                                ];
                                                foreach ($statusOptions as $value => $label):
                                                ?>
                                                    <option value="<?php echo $value; ?>" 
                                                            <?php echo $order['status'] === $value ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Ghi Chú</label>
                                            <textarea class="form-control" id="notes" name="notes" 
                                                    rows="3"><?php echo htmlspecialchars($order['notes']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Thông tin thanh toán -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Thông Tin Thanh Toán</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Phương Thức Thanh Toán</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="cod" <?php echo $order['payment_method'] == 'cod' ? 'selected' : ''; ?>>
                                                    Thanh toán khi nhận hàng (COD)
                                                </option>
                                                <option value="bank_transfer" <?php echo $order['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>
                                                    Chuyển khoản ngân hàng
                                                </option>
                                                <option value="momo" <?php echo $order['payment_method'] == 'momo' ? 'selected' : ''; ?>>
                                                    Ví MoMo
                                                </option>
                                                <option value="zalopay" <?php echo $order['payment_method'] == 'zalopay' ? 'selected' : ''; ?>>
                                                    ZaloPay
                                                </option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Trạng Thái Thanh Toán</label>
                                            <select name="payment_status" class="form-select" required>
                                                <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>
                                                    Chưa thanh toán
                                                </option>
                                                <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>
                                                    Đã thanh toán
                                                </option>
                                                <option value="failed" <?php echo $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>
                                                    Thanh toán lỗi
                                                </option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Giảm Giá (%)</label>
                                            <input type="number" name="discount_percentage" class="form-control" 
                                                   value="<?php echo $order['discount_percentage']; ?>" 
                                                   min="0" max="100" step="0.1" required>
                                            <small class="text-muted">Nhập % giảm giá (0-100)</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Thuế (%)</label>
                                            <input type="number" name="tax_percentage" class="form-control" 
                                                   value="<?php echo $order['tax_amount'] > 0 ? ($order['tax_amount'] / $order['subtotal'] * 100) : 0; ?>" 
                                                   min="0" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Thông tin giao hàng -->
                            <div class="col-md-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Thông Tin Giao Hàng</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Phương Thức Vận Chuyển</label>
                                                    <select name="shipping_method" class="form-select" required>
                                                        <option value="standard" <?php echo $order['shipping_method'] == 'standard' ? 'selected' : ''; ?>>
                                                            Giao hàng tiêu chuẩn
                                                        </option>
                                                        <option value="express" <?php echo $order['shipping_method'] == 'express' ? 'selected' : ''; ?>>
                                                            Giao hàng nhanh
                                                        </option>
                                                        <option value="same_day" <?php echo $order['shipping_method'] == 'same_day' ? 'selected' : ''; ?>>
                                                            Giao hàng trong ngày
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Tỉnh/Thành phố</label>
                                                    <select class="form-select" id="province" name="province" required>
                                                        <option value="">Chọn Tỉnh/Thành phố</option>
                                                    </select>
                                                    <input type="hidden" name="province_code" id="province_code">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Quận/Huyện</label>
                                    <select class="form-select" id="district" name="district" required>
                                        <option value="">Chọn Quận/Huyện</option>
                                    </select>
                                    <input type="hidden" name="district_code" id="district_code">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phường/Xã</label>
                                    <select class="form-select" id="ward" name="ward" required>
                                        <option value="">Chọn Phường/Xã</option>
                                    </select>
                                    <input type="hidden" name="ward_code" id="ward_code">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Số nhà, đường</label>
                                    <input type="text" class="form-control" id="street" name="street" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Địa Chỉ Đầy Đủ</label>
                                    <input type="hidden" id="shipping_address" name="shipping_address">
                                    <textarea class="form-control" id="address_display" rows="3" readonly></textarea>
                                </div>
                                                    
                                            </div>
                                            <div class="col-md-6">
                                                

                                                <div class="mb-3">
                                                    
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Thời Gian Giao Dự Kiến</label>
                                                    <input type="datetime-local" name="estimated_delivery_date" class="form-control" 
                                                           value="<?php 
                                                           if ($order['estimated_delivery_date']) {
                                                               echo date('Y-m-d\TH:i', strtotime($order['estimated_delivery_date']));
                                                           } else {
                                                               echo date('Y-m-d\TH:i', strtotime('+3 days'));
                                                           }
                                                           ?>" required>
                                                </div>

                                                <?php if ($order['actual_delivery_date']): ?>
                                                <div class="mb-3">
                                                    <label class="form-label">Thời Gian Giao Thực Tế</label>
                                                    <p class="form-control-static">
                                                        <?php 
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
                                                        ?>
                                                    </p>
                                                </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                    <label class="form-label">Phí Vận Chuyển</label>
                                                    <input type="number" name="shipping_fee" class="form-control" 
                                                           value="<?php echo $order['shipping_fee']; ?>" min="0" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Danh sách sản phẩm -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Danh Sách Sản Phẩm</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table" id="productTable">
                                        <thead>
                                            <tr>
                                                <th>Sản Phẩm</th>
                                                <th>Đơn Giá</th>
                                                <th>Số Lượng</th>
                                                <th>Thành Tiền</th>
                                                <th>Thao Tác</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productList">
                                            <?php foreach ($order_items as $index => $item): ?>
                                            <tr class="product-row">
                                                <td>
                                                    <select class="form-select product-select" name="products[<?php echo $index; ?>][id]" required>
                                                        <option value="">Chọn sản phẩm</option>
                                                        <?php foreach ($products as $product): ?>
                                                            <option value="<?php echo $product['id']; ?>" 
                                                                    data-price="<?php echo $product['price']; ?>"
                                                                    data-stock="<?php echo $product['stock']; ?>"
                                                                    <?php echo $item['product_id'] == $product['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($product['name'] . ' - ' . $product['category_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control price-input" 
                                                           name="products[<?php echo $index; ?>][price]" 
                                                           value="<?php echo $item['unit_price']; ?>" min="0" re>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control quantity-input" 
                                                           name="products[<?php echo $index; ?>][quantity]" 
                                                           value="<?php echo $item['quantity']; ?>" min="1" required>
                                                </td>
                                                <td class="row-total">
                                                    <?php echo number_format($item['unit_price'] * $item['quantity'], 0, ',', '.'); ?>đ
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-row">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5">
                                                    <button type="button" class="btn btn-success" id="addProduct">
                                                        <i class="fas fa-plus"></i> Thêm Sản Phẩm
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                                                <td id="totalAmount" colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu Thay Đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/address.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const productTable = document.getElementById('productTable');
    const addProductBtn = document.getElementById('addProduct');
    const productList = document.getElementById('productList');
    let rowCount = <?php echo count($order_items); ?>;

    // Cập nhật tổng tiền
    function updateTotals() {
        let total = 0;
        document.querySelectorAll('.product-row').forEach(row => {
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const quantity = parseInt(row.querySelector('.quantity-input').value) || 0;
            const rowTotal = price * quantity;
            row.querySelector('.row-total').textContent = formatCurrency(rowTotal);
            total += rowTotal;
        });
        document.getElementById('totalAmount').textContent = formatCurrency(total);
    }

    // Format tiền tệ
    function formatCurrency(value) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'decimal',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value) + 'đ';
    }

    // Thêm sản phẩm mới
    addProductBtn.addEventListener('click', function() {
        const firstRow = productList.querySelector('.product-row');
        const newRow = firstRow.cloneNode(true);
        
        // Cập nhật các thuộc tính name
        newRow.querySelector('.product-select').name = `products[${rowCount}][id]`;
        newRow.querySelector('.price-input').name = `products[${rowCount}][price]`;
        newRow.querySelector('.quantity-input').name = `products[${rowCount}][quantity]`;
        
        // Reset giá trị
        newRow.querySelector('.product-select').value = '';
        newRow.querySelector('.price-input').value = '0';
        newRow.querySelector('.quantity-input').value = '1';
        newRow.querySelector('.row-total').textContent = '0đ';
        
        productList.appendChild(newRow);
        rowCount++;
        updateTotals();
    });

    // Xóa sản phẩm
    productTable.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            const rows = productList.querySelectorAll('.product-row');
            if (rows.length > 1) {
                e.target.closest('.product-row').remove();
                updateTotals();
            }
        }
    });

    // Xử lý khi chọn sản phẩm
    productTable.addEventListener('change', function(e) {
        if (e.target.classList.contains('product-select')) {
            const row = e.target.closest('.product-row');
            const option = e.target.selectedOptions[0];
            if (option.dataset.price) {
                row.querySelector('.price-input').value = option.dataset.price;
                updateTotals();
            }
        }
    });

    // Xử lý khi thay đổi giá hoặc số lượng
    productTable.addEventListener('input', function(e) {
        if (e.target.classList.contains('price-input') || e.target.classList.contains('quantity-input')) {
            updateTotals();
        }
    });

    // Khởi tạo địa chỉ
    const setAddress = initAddressSelects(
        document.getElementById('province'),
        document.getElementById('district'),
        document.getElementById('ward'),
        document.getElementById('address_display'),
        function(data) {
            const street = document.getElementById('street').value.trim();
            const fullAddress = street ? street + ', ' + data.fullAddress : data.fullAddress;
            document.getElementById('address_display').value = fullAddress;
            document.getElementById('shipping_address').value = fullAddress;
            
            // Lưu mã code
            document.getElementById('province_code').value = data.province.code;
            document.getElementById('district_code').value = data.district.code;
            document.getElementById('ward_code').value = data.ward.code;
        }
    );

    // Set giá trị địa chỉ cũ
    const oldProvince = document.getElementById('old_province').value;
    const oldDistrict = document.getElementById('old_district').value;
    const oldWard = document.getElementById('old_ward').value;
    const oldStreet = document.getElementById('old_street').value;
    const oldProvinceCode = document.getElementById('old_province_code').value;
    const oldDistrictCode = document.getElementById('old_district_code').value;
    const oldWardCode = document.getElementById('old_ward_code').value;

    // Ưu tiên sử dụng mã code nếu có
    if (oldProvinceCode && oldDistrictCode && oldWardCode) {
        setAddress(oldProvinceCode, oldDistrictCode, oldWardCode, true).then(() => {
            document.getElementById('street').value = oldStreet;
            document.getElementById('street').dispatchEvent(new Event('input'));
        });
    } else if (oldProvince && oldDistrict && oldWard) {
        // Fallback về tên nếu không có mã code
        setAddress(oldProvince, oldDistrict, oldWard, false).then(() => {
            document.getElementById('street').value = oldStreet;
            document.getElementById('street').dispatchEvent(new Event('input'));
        });
    }

    // Cập nhật địa chỉ khi thay đổi số nhà, đường
    document.getElementById('street').addEventListener('input', function() {
        const street = this.value.trim();
        const ward = document.getElementById('ward').selectedOptions[0]?.text || '';
        const district = document.getElementById('district').selectedOptions[0]?.text || '';
        const province = document.getElementById('province').selectedOptions[0]?.text || '';
        
        // Bỏ qua các giá trị mặc định "Chọn..."
        const parts = [street, ward, district, province].filter(part => 
            part && !part.startsWith('Chọn ')
        );
        
        const fullAddress = parts.join(', ');
        document.getElementById('address_display').value = fullAddress;
        document.getElementById('shipping_address').value = fullAddress;

        // Lưu mã code
        document.getElementById('province_code').value = document.getElementById('province').value;
        document.getElementById('district_code').value = document.getElementById('district').value;
        document.getElementById('ward_code').value = document.getElementById('ward').value;
    });

    // Khởi tạo tổng tiền
    updateTotals();
});

function calculateTotal() {
    let subtotal = 0;
    // Tính tổng tiền hàng
    document.querySelectorAll('.product-row').forEach(row => {
        const quantity = parseFloat(row.querySelector('[name="quantity[]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name="unit_price[]"]').value) || 0;
        subtotal += quantity * price;
    });

    // Lấy các giá trị khác
    const shippingFee = parseFloat(document.querySelector('[name="shipping_fee"]').value) || 0;
    const discountPercentage = parseFloat(document.querySelector('[name="discount_percentage"]').value) || 0;
    const taxPercentage = parseFloat(document.querySelector('[name="tax_percentage"]').value) || 0;

    // Tính các khoản tiền
    const discountAmount = subtotal * (discountPercentage / 100);
    const taxAmount = subtotal * (taxPercentage / 100);
    const total = subtotal + taxAmount + shippingFee - discountAmount;

    // Hiển thị
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('discount').textContent = discountPercentage + '%';
    document.getElementById('tax').textContent = formatCurrency(taxAmount);
    document.getElementById('shipping').textContent = formatCurrency(shippingFee);
    document.getElementById('total').textContent = formatCurrency(total);

    // Cập nhật giá trị cho form
    document.querySelector('[name="subtotal"]').value = subtotal;
    document.querySelector('[name="tax_amount"]').value = taxAmount;
    document.querySelector('[name="total_amount"]').value = total;
}
</script>

<?php require_once '../includes/footer.php'; ?> 